<?php

namespace App\Http\Controllers\Property\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPortalAction;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\LandlordLedger;
use App\Services\Property\LandlordPortalNotifications;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyMoney;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordPortalController extends Controller
{
    public function portfolio(Request $request): View
    {
        $user = $request->user();
        $properties = $user->landlordProperties()->with(['units'])->withCount('units')->get();
        $propertyIds = $properties->pluck('id');
        $units = PropertyUnit::query()->whereIn('property_id', $propertyIds)->get();
        $unitIds = $units->pluck('id');

        $totalU = $units->count();
        $occupiedUnits = $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $vacantUnits = $units->where('status', PropertyUnit::STATUS_VACANT)->count();
        $noticeUnits = $units->where('status', PropertyUnit::STATUS_NOTICE)->count();
        $occRate = $totalU > 0 ? round(100 * $occupiedUnits / $totalU, 1) : null;

        $mtdInvoicesBase = $unitIds->isNotEmpty()
            ? PmInvoice::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereYear('issue_date', now()->year)
                ->whereMonth('issue_date', now()->month)
            : null;

        $gross = $mtdInvoicesBase ? (float) (clone $mtdInvoicesBase)->sum('amount') : 0.0;
        $mtdCollected = $mtdInvoicesBase ? (float) (clone $mtdInvoicesBase)->sum('amount_paid') : 0.0;
        $collectionRate = $gross > 0 ? round(($mtdCollected / $gross) * 100, 1) : null;

        $arrears = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()->whereIn('property_unit_id', $unitIds)
                ->whereColumn('amount_paid', '<', 'amount')
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t')
            : 0.0;

        $dueNext30Days = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereBetween('due_date', [now()->startOfDay(), now()->copy()->addDays(30)->endOfDay()])
                ->whereColumn('amount_paid', '<', 'amount')
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t')
            : 0.0;

        $openMaintenanceCount = $unitIds->isNotEmpty()
            ? PmMaintenanceRequest::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereNotIn('status', ['done', 'closed'])
                ->count()
            : 0;

        $recentInvoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()
                ->with(['unit.property'])
                ->whereIn('property_unit_id', $unitIds)
                ->orderByDesc('issue_date')
                ->limit(6)
                ->get()
            : collect();

        $propertyBreakdown = $properties->map(function ($property) {
            $pUnits = $property->units;
            $pUnitIds = $pUnits->pluck('id');

            $pArrears = $pUnitIds->isNotEmpty()
                ? (float) PmInvoice::query()
                    ->whereIn('property_unit_id', $pUnitIds)
                    ->whereColumn('amount_paid', '<', 'amount')
                    ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                    ->value('t')
                : 0.0;

            $pMtdBilled = $pUnitIds->isNotEmpty()
                ? (float) PmInvoice::query()
                    ->whereIn('property_unit_id', $pUnitIds)
                    ->whereYear('issue_date', now()->year)
                    ->whereMonth('issue_date', now()->month)
                    ->sum('amount')
                : 0.0;

            $pOcc = $pUnits->count() > 0
                ? round(($pUnits->where('status', PropertyUnit::STATUS_OCCUPIED)->count() / $pUnits->count()) * 100, 1)
                : null;

            return [
                'name' => $property->name,
                'units' => $pUnits->count(),
                'occupancy' => $pOcc,
                'mtd_billed' => $pMtdBilled,
                'arrears' => $pArrears,
            ];
        })->sortByDesc('mtd_billed')->values();

        $digest = LandlordPortalNotifications::recent($user, 99);

        return view('property.landlord.portfolio', [
            'incomeMonth' => PropertyMoney::kes($gross),
            'incomeCollectedMonth' => PropertyMoney::kes($mtdCollected),
            'collectionRateDisplay' => $collectionRate !== null ? $collectionRate.'%' : '—',
            'occupancyDisplay' => $occRate !== null ? $occRate.'%' : '—',
            'occupiedUnitsCount' => $occupiedUnits,
            'vacantUnitsCount' => $vacantUnits,
            'noticeUnitsCount' => $noticeUnits,
            'arrearsImpact' => PropertyMoney::kes($arrears),
            'dueNext30Days' => PropertyMoney::kes($dueNext30Days),
            'openMaintenanceCount' => $openMaintenanceCount,
            'netEarnings' => PropertyMoney::kes(LandlordLedger::balance($user)),
            'propertyCount' => $properties->count(),
            'totalUnitsCount' => $totalU,
            'propertyBreakdown' => $propertyBreakdown,
            'recentInvoices' => $recentInvoices,
            'digestCount' => count($digest),
        ]);
    }

    public function earnings(Request $request): View
    {
        $user = $request->user();
        $bal = LandlordLedger::balance($user);
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $pending = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereColumn('amount_paid', '<', 'amount')
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t')
            : 0.0;

        $payoutPrefs = $this->latestActionContext($user, 'landlord_payout_preferences');

        return view('property.landlord.earnings.index', [
            'available' => PropertyMoney::kes($bal),
            'pending' => PropertyMoney::kes($pending),
            'payoutPrefs' => $payoutPrefs,
        ]);
    }

    public function withdraw(Request $request): View
    {
        $prefs = $this->latestActionContext($request->user(), 'landlord_payout_preferences');

        return view('property.landlord.earnings.withdraw', [
            'available' => PropertyMoney::kes(LandlordLedger::balance($request->user())),
            'payoutPrefs' => $prefs,
        ]);
    }

    public function withdrawStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_destination' => ['required', 'in:bank,mpesa'],
            'destination_detail' => ['required', 'string', 'max:120'],
            'reference_note' => ['nullable', 'string', 'max:120'],
        ]);
        $user = $request->user();
        $bal = LandlordLedger::balance($user);
        if ((float) $data['amount'] > $bal) {
            return back()->withErrors(['amount' => 'Amount exceeds available balance.'])->withInput();
        }

        $dest = $data['payout_destination'];
        $detail = trim((string) $data['destination_detail']);
        $refNote = trim((string) ($data['reference_note'] ?? ''));

        if ($dest === 'mpesa' && ! preg_match('/^\+?\d[\d\s\-]{7,}$/', $detail)) {
            return back()->withErrors(['destination_detail' => 'Enter a valid M-Pesa phone number.'])->withInput();
        }

        LandlordLedger::post(
            $user,
            PmLandlordLedgerEntry::DIRECTION_DEBIT,
            (float) $data['amount'],
            'Withdrawal ('.$dest.' - '.$detail.')'.($refNote !== '' ? ' ref: '.$refNote : '').' — manual ledger',
        );

        $this->recordLandlordAction($request, 'landlord_withdrawal_request', 'Withdrawal request submitted', [
            'amount' => (float) $data['amount'],
            'destination' => $dest,
            'destination_detail' => $detail,
            'reference_note' => $refNote !== '' ? $refNote : null,
            'status' => 'submitted',
        ]);

        return redirect()->route('property.landlord.earnings.index')->with('success', 'Withdrawal posted to ledger.');
    }

    public function payoutSettings(Request $request): View
    {
        return view('property.landlord.earnings.settings', [
            'payoutPrefs' => $this->latestActionContext($request->user(), 'landlord_payout_preferences'),
        ]);
    }

    public function savePayoutSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_destination' => ['required', 'in:bank,mpesa'],
            'destination_detail' => ['nullable', 'string', 'max:120'],
            'auto_withdraw_enabled' => ['nullable', 'boolean'],
            'auto_withdraw_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'minimum_payout_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $context = [
            'default_destination' => $data['default_destination'],
            'destination_detail' => $data['destination_detail'] ?? null,
            'auto_withdraw_enabled' => $request->boolean('auto_withdraw_enabled'),
            'auto_withdraw_day' => isset($data['auto_withdraw_day']) ? (int) $data['auto_withdraw_day'] : null,
            'minimum_payout_amount' => isset($data['minimum_payout_amount']) ? (float) $data['minimum_payout_amount'] : null,
        ];

        $this->recordLandlordAction($request, 'landlord_payout_preferences', 'Updated payout preferences', $context);

        return back()->with('success', 'Payout settings saved.');
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        $entries = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->with('property')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $mtdBase = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereYear('occurred_at', now()->year)
            ->whereMonth('occurred_at', now()->month);
        $creditMtd = (float) (clone $mtdBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)->sum('amount');
        $debitMtd = (float) (clone $mtdBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)->sum('amount');

        $rows = $entries->map(fn (PmLandlordLedgerEntry $e) => [
            $e->occurred_at->format('Y-m-d H:i'),
            ucfirst($e->direction),
            $e->description,
            $e->property?->name ?? '—',
            $e->direction === PmLandlordLedgerEntry::DIRECTION_DEBIT ? number_format((float) $e->amount, 2) : '—',
            $e->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT ? number_format((float) $e->amount, 2) : '—',
            number_format((float) $e->balance_after, 2),
            '—',
        ])->all();

        return view('property.landlord.earnings.history', [
            'stats' => [
                ['label' => 'Ledger balance', 'value' => PropertyMoney::kes(LandlordLedger::balance($user)), 'hint' => 'Latest'],
                ['label' => 'Credits (MTD)', 'value' => PropertyMoney::kes($creditMtd), 'hint' => 'To you'],
                ['label' => 'Debits (MTD)', 'value' => PropertyMoney::kes($debitMtd), 'hint' => 'Fees & repairs'],
                ['label' => 'Movements', 'value' => (string) count($rows), 'hint' => 'Shown'],
            ],
            'columns' => ['Posted', 'Type', 'Description', 'Property', 'Debit', 'Credit', 'Balance', 'Document'],
            'tableRows' => $rows,
        ]);
    }

    public function exportHistoryCsv(Request $request): StreamedResponse
    {
        $entries = PmLandlordLedgerEntry::query()
            ->where('user_id', $request->user()->id)
            ->with('property')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        return $this->streamCsv('landlord-ledger-history.csv', [
            'Posted', 'Type', 'Description', 'Property', 'Debit', 'Credit', 'Balance',
        ], $entries->map(fn (PmLandlordLedgerEntry $e) => [
            $e->occurred_at->format('Y-m-d H:i'),
            $e->direction,
            $e->description,
            $e->property?->name ?? '',
            $e->direction === PmLandlordLedgerEntry::DIRECTION_DEBIT ? (string) $e->amount : '',
            $e->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT ? (string) $e->amount : '',
            (string) $e->balance_after,
        ])->all());
    }

    public function exportIncomeReportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $invoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()->with(['unit.property'])->whereIn('property_unit_id', $unitIds)->orderByDesc('issue_date')->limit(2000)->get()
            : collect();

        return $this->streamCsv('landlord-income-invoices.csv', [
            'Invoice no', 'Issue date', 'Due date', 'Property', 'Amount', 'Paid', 'Status',
        ], $invoices->map(fn (PmInvoice $i) => [
            $i->invoice_no,
            $i->issue_date->format('Y-m-d'),
            $i->due_date->format('Y-m-d'),
            $i->unit->property->name,
            (string) $i->amount,
            (string) $i->amount_paid,
            $i->status,
        ])->all());
    }

    public function exportExpensesReportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $reqIds = $unitIds->isNotEmpty()
            ? PmMaintenanceRequest::query()->whereIn('property_unit_id', $unitIds)->pluck('id')
            : collect();

        $jobs = $reqIds->isNotEmpty()
            ? PmMaintenanceJob::query()->with(['request.unit.property', 'vendor'])->whereIn('pm_maintenance_request_id', $reqIds)->orderByDesc('id')->limit(2000)->get()
            : collect();

        return $this->streamCsv('landlord-maintenance-expenses.csv', [
            'Job ID', 'Date', 'Category', 'Property', 'Vendor', 'Quote amount', 'Status',
        ], $jobs->map(fn (PmMaintenanceJob $j) => [
            (string) $j->id,
            $j->completed_at?->format('Y-m-d') ?? '',
            $j->request->category,
            $j->request->unit->property->name,
            $j->vendor?->name ?? '',
            $j->quote_amount !== null ? (string) $j->quote_amount : '',
            $j->status,
        ])->all());
    }

    public function exportPropertiesCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $properties = $user->landlordProperties()->withCount('units')->orderBy('name')->get();

        return $this->streamCsv('landlord-properties-summary.csv', [
            'Property', 'Code', 'City', 'Units',
        ], $properties->map(fn ($p) => [
            $p->name,
            $p->code ?? '',
            $p->city ?? '',
            (string) $p->units_count,
        ])->all());
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    protected function streamCsv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function properties(Request $request): View
    {
        $user = $request->user();
        $properties = $user->landlordProperties()->with('units')->withCount('units')->get();
        $rows = $properties->map(function ($p) {
            $units = $p->units;
            $occ = $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
            $vac = $units->where('status', PropertyUnit::STATUS_VACANT)->count();
            $gross = (float) $units->sum(fn ($u) => (float) $u->rent_amount);

            return [
                $p->name,
                (string) $units->count(),
                (string) $occ,
                (string) $vac,
                PropertyMoney::kes($gross),
                '—',
                '—',
            ];
        })->all();

        return view('property.landlord.properties', [
            'stats' => [
                ['label' => 'Properties', 'value' => (string) $properties->count(), 'hint' => ''],
                ['label' => 'Units', 'value' => (string) $properties->sum('units_count'), 'hint' => ''],
            ],
            'columns' => ['Property', 'Units', 'Occupied', 'Vacant', 'Gross rent', 'NOI (est)', 'Actions'],
            'tableRows' => $rows,
        ]);
    }

    public function notifications(Request $request): View
    {
        $user = $request->user();
        $items = LandlordPortalNotifications::recent($user);
        $prefs = $this->latestActionContext($user, 'landlord_notification_preferences');
        $showRent = (bool) ($prefs['notify_rent_collected'] ?? true);
        $showOverdue = (bool) ($prefs['notify_overdue'] ?? true);
        $showMaintenance = (bool) ($prefs['notify_maintenance'] ?? true);
        $showLease = (bool) ($prefs['notify_lease_expiry'] ?? true);

        $items = array_values(array_filter($items, static function (array $n) use ($showRent, $showOverdue, $showMaintenance, $showLease): bool {
            $title = strtolower((string) ($n['title'] ?? ''));
            if (str_contains($title, 'rent') && ! $showRent) {
                return false;
            }
            if (str_contains($title, 'overdue') && ! $showOverdue) {
                return false;
            }
            if (str_contains($title, 'maintenance') && ! $showMaintenance) {
                return false;
            }
            if (str_contains($title, 'lease') && ! $showLease) {
                return false;
            }

            return true;
        }));

        return view('property.landlord.notifications', [
            'notifications' => $items,
            'notificationPrefs' => $prefs,
        ]);
    }

    public function saveNotificationPreferences(Request $request): RedirectResponse
    {
        $context = [
            'notify_rent_collected' => $request->boolean('notify_rent_collected'),
            'notify_overdue' => $request->boolean('notify_overdue'),
            'notify_maintenance' => $request->boolean('notify_maintenance'),
            'notify_lease_expiry' => $request->boolean('notify_lease_expiry'),
        ];
        $this->recordLandlordAction($request, 'landlord_notification_preferences', 'Updated notification preferences', $context);

        return back()->with('success', 'Notification preferences saved.');
    }

    public function maintenance(Request $request): View
    {
        $user = $request->user();
        $propIds = $this->landlordPropertyIds($user);
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');

        $threshold = (float) ($this->latestActionContext($user, 'landlord_maintenance_threshold')['approval_threshold'] ?? 15000);
        $pendingOnly = $request->boolean('pending_only');
        $jobs = $unitIds->isNotEmpty()
            ? PmMaintenanceJob::query()
                ->with(['request.unit.property', 'vendor'])
                ->whereHas('request', fn ($q) => $q->whereIn('property_unit_id', $unitIds))
                ->when($pendingOnly, fn ($q) => $q->whereIn('status', ['quoted', 'approved', 'in_progress']))
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get()
            : collect();

        $jobsForApproval = $jobs->filter(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0) >= $threshold);
        $stats = [
            ['label' => 'Awaiting your approval', 'value' => (string) $jobsForApproval->whereIn('status', ['quoted', 'approved'])->count(), 'hint' => 'Over threshold'],
            ['label' => 'Spend (YTD)', 'value' => PropertyMoney::kes((float) $jobs->where('completed_at', '>=', now()->startOfYear())->sum(fn ($j) => (float) ($j->quote_amount ?? 0))), 'hint' => 'Quoted jobs'],
            ['label' => 'Open jobs', 'value' => (string) $jobs->whereNotIn('status', ['done', 'cancelled'])->count(), 'hint' => 'In progress'],
        ];

        return view('property.landlord.maintenance', [
            'stats' => $stats,
            'jobs' => $jobs,
            'approvalThreshold' => $threshold,
            'pendingOnly' => $pendingOnly,
        ]);
    }

    public function approveMaintenanceJob(Request $request, PmMaintenanceJob $job): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $propIds = $this->landlordPropertyIds($request->user());
        $isOwnerJob = PmMaintenanceRequest::query()
            ->whereKey($job->pm_maintenance_request_id)
            ->whereHas('unit', fn ($q) => $q->whereIn('property_id', $propIds))
            ->exists();

        if (! $isOwnerJob) {
            abort(403);
        }

        $nextStatus = $data['decision'] === 'approve' ? 'approved' : 'cancelled';
        $job->update([
            'status' => $nextStatus,
            'notes' => trim(($job->notes ? $job->notes."\n" : '').'Landlord '.$data['decision'].($data['note'] ? ': '.$data['note'] : '')),
        ]);

        $context = [
            'job_id' => $job->id,
            'decision' => $data['decision'],
            'quote_amount' => (float) ($job->quote_amount ?? 0),
            'note' => $data['note'] ?? null,
        ];
        $this->recordLandlordAction($request, 'landlord_maintenance_approval', 'Maintenance job decision', $context);

        if (array_key_exists('approval_threshold', $data)) {
            $this->recordLandlordAction($request, 'landlord_maintenance_threshold', 'Updated maintenance approval threshold', [
                'approval_threshold' => (float) $data['approval_threshold'],
            ]);
        }

        return back()->with('success', 'Maintenance decision recorded.');
    }

    public function saveMaintenanceThreshold(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'approval_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $this->recordLandlordAction($request, 'landlord_maintenance_threshold', 'Updated maintenance approval threshold', [
            'approval_threshold' => (float) $data['approval_threshold'],
        ]);

        return redirect()->route('property.landlord.maintenance', [
            'pending_only' => $request->boolean('pending_only') ? 1 : null,
        ])->with('success', 'Approval threshold updated.');
    }

    public function reportIncome(Request $request): View
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $invoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()->with(['unit.property'])->whereIn('property_unit_id', $unitIds)->orderByDesc('issue_date')->limit(150)->get()
            : collect();

        $rows = $invoices->map(fn (PmInvoice $i) => [
            $i->invoice_no,
            $i->unit->property->name,
            PropertyMoney::kes((float) $i->amount),
            ucfirst($i->status),
        ])->all();

        return view('property.landlord.reports.income', [
            'stats' => [
                ['label' => 'Billed', 'value' => PropertyMoney::kes((float) $invoices->sum('amount')), 'hint' => 'Listed'],
            ],
            'columns' => ['Invoice', 'Property', 'Amount', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function reportExpenses(Request $request): View
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $reqIds = $unitIds->isNotEmpty()
            ? PmMaintenanceRequest::query()->whereIn('property_unit_id', $unitIds)->pluck('id')
            : collect();

        $jobs = $reqIds->isNotEmpty()
            ? PmMaintenanceJob::query()->with(['request.unit.property', 'vendor'])->whereIn('pm_maintenance_request_id', $reqIds)->orderByDesc('id')->limit(100)->get()
            : collect();

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            $j->completed_at?->format('Y-m-d') ?? '—',
            $j->request->category,
            $j->request->unit->property->name,
            $j->vendor?->name ?? '—',
            $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
            '—',
            ucfirst(str_replace('_', ' ', $j->status)),
        ])->all();

        return view('property.landlord.reports.expenses', [
            'stats' => [
                ['label' => 'Maintenance booked', 'value' => PropertyMoney::kes((float) $jobs->sum(fn ($j) => (float) ($j->quote_amount ?? 0))), 'hint' => ''],
            ],
            'columns' => ['Date', 'Category', 'Property', 'Vendor', 'Amount', 'Invoice', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function reportCashFlow(Request $request): View
    {
        $user = $request->user();
        $mtdBase = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereYear('occurred_at', now()->year)
            ->whereMonth('occurred_at', now()->month);
        $cashIn = (float) (clone $mtdBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)->sum('amount');
        $cashOut = (float) (clone $mtdBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)->sum('amount');

        $rows = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('occurred_at')
            ->limit(80)
            ->get()
            ->map(fn (PmLandlordLedgerEntry $e) => [
                $e->occurred_at->format('Y-m-d'),
                $e->description,
                $e->property?->name ?? '—',
                $e->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT ? PropertyMoney::kes((float) $e->amount) : '—',
                $e->direction === PmLandlordLedgerEntry::DIRECTION_DEBIT ? PropertyMoney::kes((float) $e->amount) : '—',
                PropertyMoney::kes((float) $e->balance_after),
            ])->all();

        $monthly = PropertyChartSeries::landlordLedgerMonthlyNet($user);
        $barSeries = array_map(static fn ($m) => [
            'label' => $m['label'],
            'value' => $m['net'],
        ], $monthly);

        $cumul = PropertyChartSeries::landlordCumulativeCash($user);
        $dualSeries = [];
        foreach ($monthly as $idx => $m) {
            $dualSeries[] = [
                'label' => $m['label'],
                'a' => $m['in'],
                'b' => $m['out'],
            ];
        }

        return view('property.landlord.reports.cash_flow', [
            'stats' => [
                ['label' => 'Cash in (MTD)', 'value' => PropertyMoney::kes($cashIn), 'hint' => 'Credits'],
                ['label' => 'Cash out (MTD)', 'value' => PropertyMoney::kes($cashOut), 'hint' => 'Debits'],
                ['label' => 'Net (MTD)', 'value' => PropertyMoney::kes($cashIn - $cashOut), 'hint' => ''],
                ['label' => 'Balance', 'value' => PropertyMoney::kes(LandlordLedger::balance($user)), 'hint' => 'Ledger'],
            ],
            'columns' => ['Date', 'Description', 'Property', 'In', 'Out', 'Running cash'],
            'tableRows' => $rows,
            'cashNetBars' => $barSeries,
            'cashCumulative' => $cumul,
            'cashInOutDual' => $dualSeries,
        ]);
    }

    public function statement(Request $request): View
    {
        $user = $request->user();
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        [$year, $monthNum] = array_pad(explode('-', $month), 2, null);
        $year = (int) $year;
        $monthNum = (int) $monthNum;
        if ($year < 2000 || $monthNum < 1 || $monthNum > 12) {
            $year = (int) now()->format('Y');
            $monthNum = (int) now()->format('m');
            $month = sprintf('%04d-%02d', $year, $monthNum);
        }

        $propIds = $this->landlordPropertyIds($user);
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $invoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()->with('unit.property')
                ->whereIn('property_unit_id', $unitIds)
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $monthNum)
                ->orderBy('issue_date')
                ->get()
            : collect();
        $jobs = $unitIds->isNotEmpty()
            ? PmMaintenanceJob::query()
                ->with(['request.unit.property', 'vendor'])
                ->whereHas('request', fn ($q) => $q->whereIn('property_unit_id', $unitIds))
                ->whereYear('updated_at', $year)
                ->whereMonth('updated_at', $monthNum)
                ->orderBy('updated_at')
                ->get()
            : collect();

        $ledgerBase = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereYear('occurred_at', $year)
            ->whereMonth('occurred_at', $monthNum);
        $ledgerCredits = (float) (clone $ledgerBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)->sum('amount');
        $ledgerDebits = (float) (clone $ledgerBase)->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)->sum('amount');
        $opening = (float) PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '<', sprintf('%04d-%02d-01 00:00:00', $year, $monthNum))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('balance_after');
        $closing = $opening + $ledgerCredits - $ledgerDebits;

        return view('property.landlord.reports.statement', [
            'month' => $month,
            'openingBalance' => PropertyMoney::kes($opening),
            'closingBalance' => PropertyMoney::kes($closing),
            'incomeBilled' => PropertyMoney::kes((float) $invoices->sum('amount')),
            'incomeCollected' => PropertyMoney::kes((float) $invoices->sum('amount_paid')),
            'maintenanceBooked' => PropertyMoney::kes((float) $jobs->sum(fn ($j) => (float) ($j->quote_amount ?? 0))),
            'ledgerCredits' => PropertyMoney::kes($ledgerCredits),
            'ledgerDebits' => PropertyMoney::kes($ledgerDebits),
            'invoiceRows' => $invoices,
            'jobRows' => $jobs,
        ]);
    }

    public function exportStatementCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        [$year, $monthNum] = array_pad(explode('-', $month), 2, null);
        $year = (int) $year;
        $monthNum = (int) $monthNum;

        $propIds = $this->landlordPropertyIds($user);
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $invoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()->with('unit.property')
                ->whereIn('property_unit_id', $unitIds)
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $monthNum)
                ->orderBy('issue_date')
                ->get()
            : collect();

        $rows = $invoices->map(fn (PmInvoice $i) => [
            'Invoice',
            $i->issue_date->format('Y-m-d'),
            $i->invoice_no,
            $i->unit->property->name.' / '.$i->unit->label,
            (string) $i->amount,
            (string) $i->amount_paid,
            (string) max(0, (float) $i->amount - (float) $i->amount_paid),
        ])->all();

        return $this->streamCsv('landlord-monthly-statement-'.$month.'.csv', [
            'Type', 'Date', 'Reference', 'Property / Unit', 'Amount', 'Paid', 'Outstanding',
        ], $rows);
    }

    public function documents(Request $request): View
    {
        $user = $request->user();
        $propIds = $this->landlordPropertyIds($user);
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');

        $latestInvoices = $unitIds->isNotEmpty()
            ? PmInvoice::query()->with('unit.property')->whereIn('property_unit_id', $unitIds)->orderByDesc('issue_date')->limit(12)->get()
            : collect();
        $latestJobs = $unitIds->isNotEmpty()
            ? PmMaintenanceJob::query()->with(['request.unit.property'])->whereHas('request', fn ($q) => $q->whereIn('property_unit_id', $unitIds))->orderByDesc('updated_at')->limit(12)->get()
            : collect();

        return view('property.landlord.documents', [
            'invoiceDocs' => $latestInvoices,
            'maintenanceDocs' => $latestJobs,
        ]);
    }

    public function auditTrail(Request $request): View
    {
        $user = $request->user();
        $actionKey = trim((string) $request->query('action_key', ''));
        $q = trim((string) $request->query('q', ''));

        $actionsQuery = PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->orderByDesc('id');

        if ($actionKey !== '') {
            $actionsQuery->where('action_key', $actionKey);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $actionsQuery->where(function ($sub) use ($like) {
                $sub->where('action_key', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereRaw('CAST(context AS CHAR) like ?', [$like]);
            });
        }

        $actions = $actionsQuery->paginate(25)->withQueryString();
        $actionKeys = PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->distinct()
            ->orderBy('action_key')
            ->pluck('action_key');

        return view('property.landlord.audit_trail', [
            'actions' => $actions,
            'actionKeys' => $actionKeys,
            'actionKey' => $actionKey,
            'q' => $q,
            'stats' => [
                ['label' => 'Actions logged', 'value' => (string) $actions->total(), 'hint' => 'Filtered results'],
                ['label' => 'Withdrawal requests', 'value' => (string) collect($actions->items())->where('action_key', 'landlord_withdrawal_request')->count(), 'hint' => 'This page'],
                ['label' => 'Maintenance decisions', 'value' => (string) collect($actions->items())->where('action_key', 'landlord_maintenance_approval')->count(), 'hint' => 'This page'],
            ],
        ]);
    }

    public function exportAuditTrailCsv(Request $request): StreamedResponse
    {
        $actionKey = trim((string) $request->query('action_key', ''));
        $q = trim((string) $request->query('q', ''));

        $actionsQuery = PmPortalAction::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_role', 'landlord')
            ->orderByDesc('id');

        if ($actionKey !== '') {
            $actionsQuery->where('action_key', $actionKey);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $actionsQuery->where(function ($sub) use ($like) {
                $sub->where('action_key', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereRaw('CAST(context AS CHAR) like ?', [$like]);
            });
        }

        $actions = $actionsQuery->limit(5000)->get();

        return $this->streamCsv('landlord-audit-trail.csv', [
            'When', 'Action key', 'Notes', 'Context',
        ], $actions->map(fn (PmPortalAction $a) => [
            optional($a->created_at)->format('Y-m-d H:i:s') ?? '',
            $a->action_key,
            $a->notes ?? '',
            is_array($a->context) ? json_encode($a->context, JSON_UNESCAPED_SLASHES) : '',
        ])->all());
    }

    private function landlordPropertyIds(User $user): Collection
    {
        return $user->landlordProperties()->pluck('properties.id');
    }

    /**
     * @return array<string,mixed>
     */
    private function latestActionContext(User $user, string $actionKey): array
    {
        return (array) (PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->where('action_key', $actionKey)
            ->latest('id')
            ->value('context') ?? []);
    }

    private function recordLandlordAction(Request $request, string $actionKey, ?string $notes, array $context): void
    {
        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'landlord',
            'action_key' => $actionKey,
            'notes' => $notes,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
