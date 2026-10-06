<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmTenantCreditBalance;
use App\Models\PmTenantCreditTransaction;
use App\Models\Property;
use App\Services\Property\PropertyMoney;
use App\Services\Property\TenantCreditService;
use App\Support\Property\PhoneLink;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmTenantCreditController extends Controller
{
    public function report(Request $request): View|StreamedResponse
    {
        $filters = $this->creditReportFilters($request);
        $creditService = app(TenantCreditService::class);
        PmTenantCreditBalance::query()
            ->where('balance', '>', 0)
            ->orderBy('id')
            ->pluck('pm_tenant_id')
            ->each(function ($tenantId) use ($creditService): void {
                $creditService->reconcileBalanceToStatement((int) $tenantId);
            });
        $query = $this->creditReportQuery($filters);

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, TabularExport::TABLE_FORMATS, true)) {
            $rows = (clone $query)->get();

            return TabularExport::stream(
                'tenant-advance-credits-'.now()->format('Ymd_His'),
                ['Tenant', 'Ac/No', 'Phone', 'Property / unit', 'Credit balance', 'Updated'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        $unit = $this->creditTenantUnitLabel($row->tenant);
                        yield [
                            (string) ($row->tenant?->name ?? ''),
                            (string) ($row->tenant?->account_number ?? ''),
                            (string) ($row->tenant?->phone ?? ''),
                            $unit,
                            number_format((float) $row->balance, 2, '.', ''),
                            $row->updated_at?->format('Y-m-d') ?? '',
                        ];
                    }
                },
                $export,
                [
                    'title' => 'Tenant advance credits',
                    'subtitle' => $rows->count().' tenant'.($rows->count() === 1 ? '' : 's').' with credit',
                ],
            );
        }

        $balances = $query->paginate(30)->withQueryString();
        $totalUnapplied = (float) PmTenantCreditBalance::query()->where('balance', '>', 0)->sum('balance');
        $rows = $balances->getCollection()->map(function (PmTenantCreditBalance $row) {
            $tenant = $row->tenant;
            $name = $tenant
                ? new HtmlString('<a href="'.route('property.tenants.show', $tenant).'" class="font-medium text-slate-800 hover:text-indigo-700 hover:underline">'.e($tenant->name).'</a>')
                : '—';
            $ledger = $tenant
                ? new HtmlString('<a href="'.route('property.tenants.credit.ledger', $tenant).'" class="text-indigo-600 font-medium hover:underline">Ledger</a>')
                : '—';

            return [
                $name,
                $tenant?->account_number ?: '—',
                $tenant ? PhoneLink::html($tenant->phone) : '—',
                $this->creditTenantUnitLabel($tenant) ?: '—',
                new HtmlString('<span class="tabular-nums font-semibold text-emerald-700">'.e(PropertyMoney::kes((float) $row->balance)).'</span>'),
                $row->updated_at?->format('Y-m-d') ?? '—',
                $ledger,
            ];
        })->all();

        return property_view('property.agent.revenue.tenant_credits_report', [
            'balances' => $balances,
            'totalUnapplied' => $totalUnapplied,
            'filters' => $filters,
            'columns' => ['Tenant', 'Ac/No', 'Phone', 'Property / unit', 'Credit balance', 'Updated', 'Actions'],
            'tableRows' => $rows,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'tenantsForAdvance' => PmTenant::query()->orderBy('name')->get(['id', 'name']),
            'advanceCreditsEnabled' => $creditService->isEnabled(),
        ]);
    }

    public function ledger(Request $request, PmTenant $tenant): View|StreamedResponse
    {
        $creditService = app(TenantCreditService::class);
        $creditService->balanceForTenant((int) $tenant->id);
        $type = strtolower(trim((string) $request->query('type', '')));
        $allowedTypes = [
            PmTenantCreditTransaction::TYPE_CREDIT_CREATED,
            PmTenantCreditTransaction::TYPE_CREDIT_APPLIED,
            PmTenantCreditTransaction::TYPE_CREDIT_REFUNDED,
            PmTenantCreditTransaction::TYPE_CREDIT_REVERSED,
            PmTenantCreditTransaction::TYPE_MANUAL_ADJUSTMENT,
        ];
        if (! in_array($type, $allowedTypes, true)) {
            $type = '';
        }

        $txnQuery = PmTenantCreditTransaction::query()
            ->with(['invoice', 'payment', 'creator'])
            ->where('pm_tenant_id', $tenant->id)
            ->when($type !== '', fn (Builder $query) => $query->where('type', $type))
            ->orderByDesc('id');

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, TabularExport::TABLE_FORMATS, true)) {
            $txns = (clone $txnQuery)->get();

            return TabularExport::stream(
                'tenant-credit-ledger-'.$tenant->id.'-'.now()->format('Ymd_His'),
                ['Date', 'Type', 'Amount', 'Invoice / ref', 'Mode', 'Notes'],
                function () use ($txns) {
                    foreach ($txns as $txn) {
                        yield [
                            $txn->created_at?->format('Y-m-d H:i') ?? '',
                            $txn->typeLabel(),
                            number_format((float) $txn->amount, 2, '.', ''),
                            (string) ($txn->invoice?->invoice_no ?? $txn->reference ?? ''),
                            (string) ($txn->application_mode ?? ''),
                            (string) ($txn->notes ?? ''),
                        ];
                    }
                },
                $export,
                [
                    'title' => 'Credit ledger — '.$tenant->name,
                    'subtitle' => 'Balance '.PropertyMoney::kes($creditService->balanceForTenant((int) $tenant->id)),
                ],
            );
        }

        $transactions = $txnQuery->paginate(40)->withQueryString();
        $balance = $creditService->balanceForTenant((int) $tenant->id);
        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return property_view('property.agent.tenants.credit_ledger', [
            'tenant' => $tenant,
            'balance' => $balance,
            'transactions' => $transactions,
            'openInvoices' => $openInvoices,
            'advanceCreditsEnabled' => $creditService->isEnabled(),
            'filters' => ['type' => $type],
        ]);
    }

    /**
     * @return array{q:string,property_id:int,min_balance:string,sort:string,dir:string}
     */
    private function creditReportFilters(Request $request): array
    {
        $sort = strtolower(trim((string) $request->query('sort', 'balance')));
        if (! in_array($sort, ['balance', 'updated', 'name'], true)) {
            $sort = 'balance';
        }
        $dir = strtolower(trim((string) $request->query('dir', 'desc'))) === 'asc' ? 'asc' : 'desc';

        return [
            'q' => trim((string) $request->query('q', '')),
            'property_id' => max(0, (int) $request->query('property_id', 0)),
            'min_balance' => trim((string) $request->query('min_balance', '')),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * @param  array{q:string,property_id:int,min_balance:string,sort:string,dir:string}  $filters
     * @return Builder<PmTenantCreditBalance>
     */
    private function creditReportQuery(array $filters): Builder
    {
        $query = PmTenantCreditBalance::query()
            ->with(['tenant.leases' => function ($leases): void {
                $leases->where('status', PmLease::STATUS_ACTIVE)->with('units.property');
            }])
            ->where('balance', '>', 0);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('tenant', function (Builder $tenant) use ($q): void {
                $tenant->where('name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('account_number', 'like', '%'.$q.'%');
            });
        }

        if ($filters['property_id'] > 0) {
            $propertyId = $filters['property_id'];
            $query->whereHas('tenant.leases.units', fn (Builder $unit) => $unit->where('property_units.property_id', $propertyId));
        }

        if ($filters['min_balance'] !== '' && is_numeric($filters['min_balance'])) {
            $query->where('balance', '>=', (float) $filters['min_balance']);
        }

        if ($filters['sort'] === 'name') {
            $query->orderBy(
                PmTenant::query()->select('name')->whereColumn('pm_tenants.id', 'pm_tenant_credit_balances.pm_tenant_id'),
                $filters['dir']
            );
        } elseif ($filters['sort'] === 'updated') {
            $query->orderBy('updated_at', $filters['dir']);
        } else {
            $query->orderBy('balance', $filters['dir']);
        }

        return $query->orderByDesc('pm_tenant_credit_balances.id');
    }

    private function creditTenantUnitLabel(?PmTenant $tenant): string
    {
        $lease = $tenant?->leases?->first();
        $unit = $lease?->units?->first();
        if (! $unit) {
            return '';
        }
        $property = trim((string) ($unit->property?->name ?? ''));
        $label = trim((string) ($unit->label ?? ''));

        return trim($property.($property !== '' && $label !== '' ? ' / ' : '').$label);
    }

    public function apply(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'pm_invoice_id' => ['required', 'exists:pm_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = PmInvoice::query()->findOrFail($data['pm_invoice_id']);
        try {
            app(TenantCreditService::class)->applyToInvoiceManual(
                (int) $tenant->id,
                $invoice,
                (float) $data['amount'],
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        $hubRedirect = \App\Support\Property\TenantHubRedirect::toShow(
            $request,
            (int) $tenant->id,
            'credit',
            'Credit applied to invoice '.$invoice->invoice_no.'.'
        );
        if ($hubRedirect) {
            return $hubRedirect;
        }

        return back()->with('success', 'Credit applied to invoice '.$invoice->invoice_no.'.');
    }

    public function refund(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            app(TenantCreditService::class)->refundCredit(
                (int) $tenant->id,
                (float) $data['amount'],
                $request->user(),
                $data['notes'] ?? null,
                $data['reference'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        $hubRedirect = \App\Support\Property\TenantHubRedirect::toShow(
            $request,
            (int) $tenant->id,
            'credit',
            'Refunded '.PropertyMoney::kes((float) $data['amount']).' to tenant.'
        );
        if ($hubRedirect) {
            return $hubRedirect;
        }

        return back()->with('success', 'Refunded '.PropertyMoney::kes((float) $data['amount']).' to tenant.');
    }

    public function autoApply(Request $request, PmTenant $tenant): RedirectResponse
    {
        $applied = app(TenantCreditService::class)->autoApplyForTenant((int) $tenant->id, $request->user());
        $total = array_sum(array_column($applied, 'amount'));
        $message = $total > 0
            ? 'Applied '.PropertyMoney::kes($total).' across '.count($applied).' invoice(s).'
            : 'No open invoices or no credit available to apply.';

        $hubRedirect = \App\Support\Property\TenantHubRedirect::toShow(
            $request,
            (int) $tenant->id,
            'credit',
            $message
        );
        if ($hubRedirect) {
            return $hubRedirect;
        }

        return back()->with('success', $message);
    }
}
