<?php

namespace App\Http\Controllers\Property\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PropertyUnit;
use App\Services\Property\LandlordLedger;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordPortalController extends Controller
{
    public function portfolio(Request $request): View
    {
        $user = $request->user();
        $properties = $user->landlordProperties()->withCount('units')->get();
        $propertyIds = $properties->pluck('id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propertyIds)->pluck('id');

        $gross = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()->whereIn('property_unit_id', $unitIds)
                ->whereMonth('issue_date', now()->month)
                ->sum('amount')
            : 0.0;

        $arrears = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()->whereIn('property_unit_id', $unitIds)
                ->whereColumn('amount_paid', '<', 'amount')
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t')
            : 0.0;

        $occ = PropertyUnit::query()->whereIn('property_id', $propertyIds);
        $totalU = (clone $occ)->count();
        $occRate = $totalU > 0 ? round(100 * (clone $occ)->where('status', PropertyUnit::STATUS_OCCUPIED)->count() / $totalU, 1) : null;

        return view('property.landlord.portfolio', [
            'incomeMonth' => PropertyMoney::kes($gross),
            'occupancyDisplay' => $occRate !== null ? $occRate.'%' : '—',
            'arrearsImpact' => PropertyMoney::kes($arrears),
            'netEarnings' => PropertyMoney::kes(LandlordLedger::balance($user)),
            'propertyCount' => $properties->count(),
        ]);
    }

    public function earnings(Request $request): View
    {
        $user = $request->user();
        $bal = LandlordLedger::balance($user);
        $propIds = $user->landlordProperties()->pluck('id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $pending = $unitIds->isNotEmpty()
            ? (float) PmInvoice::query()
                ->whereIn('property_unit_id', $unitIds)
                ->whereColumn('amount_paid', '<', 'amount')
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as t')
                ->value('t')
            : 0.0;

        return view('property.landlord.earnings.index', [
            'available' => PropertyMoney::kes($bal),
            'pending' => PropertyMoney::kes($pending),
        ]);
    }

    public function withdraw(Request $request): View
    {
        return view('property.landlord.earnings.withdraw', [
            'available' => PropertyMoney::kes(LandlordLedger::balance($request->user())),
        ]);
    }

    public function withdrawStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_destination' => ['nullable', 'string', 'max:64'],
        ]);
        $user = $request->user();
        $bal = LandlordLedger::balance($user);
        if ((float) $data['amount'] > $bal) {
            return back()->withErrors(['amount' => 'Amount exceeds available balance.'])->withInput();
        }

        LandlordLedger::post(
            $user,
            PmLandlordLedgerEntry::DIRECTION_DEBIT,
            (float) $data['amount'],
            'Withdrawal request (manual ledger)',
        );

        return redirect()->route('property.landlord.earnings.index')->with('success', 'Withdrawal posted to ledger.');
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
        $propIds = $user->landlordProperties()->pluck('id');
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
        $propIds = $user->landlordProperties()->pluck('id');
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
        $properties = $user->landlordProperties()->withCount('units')->get();
        $rows = $properties->map(fn ($p) => [
            $p->name,
            (string) $p->units_count,
            '—',
            '—',
            '—',
            '—',
            '—',
        ])->all();

        return view('property.landlord.properties', [
            'stats' => [
                ['label' => 'Properties', 'value' => (string) $properties->count(), 'hint' => ''],
                ['label' => 'Units', 'value' => (string) $properties->sum('units_count'), 'hint' => ''],
            ],
            'columns' => ['Property', 'Units', 'Occupied', 'Vacant', 'Gross rent', 'NOI (est)', 'Actions'],
            'tableRows' => $rows,
        ]);
    }

    public function maintenance(Request $request): View
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('properties.id');
        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');

        $requests = PmMaintenanceRequest::query()
            ->with(['unit.property'])
            ->whereIn('property_unit_id', $unitIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $rows = $requests->map(fn (PmMaintenanceRequest $r) => [
            '#'.$r->id,
            $r->unit->property->name.'/'.$r->unit->label,
            '—',
            '—',
            '—',
            ucfirst(str_replace('_', ' ', $r->status)),
            $r->updated_at->format('Y-m-d'),
        ])->all();

        return view('property.landlord.maintenance', [
            'stats' => [
                ['label' => 'Open', 'value' => (string) $requests->where('status', '!=', 'done')->count(), 'hint' => ''],
                ['label' => 'Listed', 'value' => (string) $requests->count(), 'hint' => ''],
            ],
            'columns' => ['Job', 'Property / unit', 'Vendor', 'Quote', 'Your approval', 'Status', 'Updated'],
            'tableRows' => $rows,
        ]);
    }

    public function reportIncome(Request $request): View
    {
        $user = $request->user();
        $propIds = $user->landlordProperties()->pluck('id');
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
        $propIds = $user->landlordProperties()->pluck('id');
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
        $rows = PmLandlordLedgerEntry::query()
            ->where('user_id', $request->user()->id)
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

        return view('property.landlord.reports.cash_flow', [
            'stats' => [
                ['label' => 'Balance', 'value' => PropertyMoney::kes(LandlordLedger::balance($request->user())), 'hint' => ''],
            ],
            'columns' => ['Date', 'Description', 'Property', 'In', 'Out', 'Running cash'],
            'tableRows' => $rows,
        ]);
    }
}
