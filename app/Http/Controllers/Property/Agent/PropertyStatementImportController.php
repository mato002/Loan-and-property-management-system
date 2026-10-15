<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Employee;
use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\PmVendor;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\Property\CoopBankAccountStatementImportService;
use App\Services\Property\PropertyStatementAutoAssignService;
use App\Services\Property\PropertyStatementMissingPaymentRecoveryService;
use App\Services\Property\PropertyStatementUploadService;
use App\Services\Property\PropertyHrEmployeeService;
use App\Support\ListPageSize;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyStatementImportController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = PmBankStatement::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where(function (Builder $w) use ($q): void {
                $w->where('bank_name', 'like', '%'.$q.'%')
                    ->orWhere('account_no', 'like', '%'.$q.'%')
                    ->orWhere('account_name', 'like', '%'.$q.'%')
                    ->orWhere('source_filename', 'like', '%'.$q.'%');
            });
        }

        $export = strtolower(trim((string) $request->query('export', '')));
        if (Schema::hasTable('pm_bank_statements') && in_array($export, TabularExport::TABLE_FORMATS, true)) {
            $rows = (clone $query)->limit(2000)->get();

            return TabularExport::stream(
                'bank-statements-'.now()->format('Ymd_His'),
                ['Bank', 'Account', 'Account name', 'Period from', 'Period to', 'Opening', 'Closing', 'Debits', 'Credits', 'File'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            (string) $row->bank_name,
                            (string) ($row->account_no ?? ''),
                            (string) ($row->account_name ?? ''),
                            $row->period_from?->format('Y-m-d') ?? '',
                            $row->period_to?->format('Y-m-d') ?? '',
                            number_format((float) $row->opening_balance, 2, '.', ''),
                            number_format((float) $row->closing_balance, 2, '.', ''),
                            number_format((float) $row->total_debit, 2, '.', ''),
                            number_format((float) $row->total_credit, 2, '.', ''),
                            (string) ($row->source_filename ?? ''),
                        ];
                    }
                },
                $export,
                [
                    'title' => 'Uploaded bank statements',
                    'subtitle' => $rows->count().' statement'.($rows->count() === 1 ? '' : 's'),
                ],
            );
        }

        $statements = Schema::hasTable('pm_bank_statements')
            ? $query->paginate(30)->withQueryString()
            : collect();

        return property_view('property.agent.revenue.statement_upload', [
            'statements' => $statements,
            'filters' => ['q' => $q],
            'providers' => [
                PropertyStatementUploadService::PROVIDER_AUTO => 'Auto-detect',
                PropertyStatementUploadService::PROVIDER_COOP => 'Co-operative Bank statement',
                PropertyStatementUploadService::PROVIDER_EQUITY => 'Equity Bank statement',
                PropertyStatementUploadService::PROVIDER_SAFARICOM_C2B => 'Safaricom C2B CSV',
            ],
        ]);
    }

    public function store(Request $request, PropertyStatementUploadService $uploader): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'in:auto,coop_bank,equity_bank,safaricom_c2b'],
            'statement_file' => ['required', 'file', 'mimes:csv,txt,pdf,xls,xlsx', 'max:10240'],
            'recover_missing' => ['nullable', 'boolean'],
        ]);

        $agentUserId = (int) $request->user()->id;

        try {
            $result = $uploader->uploadAndImport(
                $request->file('statement_file'),
                $agentUserId,
                (string) ($data['provider'] ?? PropertyStatementUploadService::PROVIDER_AUTO),
                $request->boolean('recover_missing', true),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['statement_file' => $e->getMessage()])->withInput();
        }

        $import = $result['import'];
        $recovery = $result['recovery'];
        $msg = sprintf(
            'Imported %s (%d lines): %d matched, %d unmatched, %d bank-only.',
            $result['provider'],
            (int) ($import['parsed'] ?? 0),
            (int) ($import['matched'] ?? 0),
            (int) ($import['unmatched'] ?? 0),
            (int) ($import['bank_only'] ?? 0),
        );
        if ($recovery) {
            $msg .= sprintf(' Recovered %d missing credits into Unmatched for assignment.', (int) $recovery['recovered']);
        }
        $auto = $result['auto'] ?? null;
        if (is_array($auto) && ((int) ($auto['posted'] ?? 0) > 0 || (int) ($auto['linked'] ?? 0) > 0)) {
            $msg .= sprintf(
                ' Auto-posted %d receipts where the phone identified one tenant (or the payer name disambiguated a shared phone). Linked %d lines that already had a receipt.',
                (int) ($auto['posted'] ?? 0),
                (int) ($auto['linked'] ?? 0),
            );
        }

        $statementId = (int) ($import['statement_id'] ?? 0);
        if ($statementId > 0) {
            return redirect()
                ->route('property.revenue.statements.show', $statementId)
                ->with('status', $msg);
        }

        return redirect()
            ->route('property.revenue.statements.index')
            ->with('status', $msg);
    }

    public function show(Request $request, PmBankStatement $statement): View|StreamedResponse
    {
        $this->authorizeStatement($request, $statement);
        $this->reconcilePreviouslySavedSplitShares($statement);

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $allowed = [
            PmBankStatementLine::MATCH_MATCHED,
            PmBankStatementLine::MATCH_UNMATCHED,
            PmBankStatementLine::MATCH_BANK_ONLY,
        ];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $linesQuery = PmBankStatementLine::query()
            ->with([
                'ezenReceipt.tenant',
                'payment.tenant',
                'unassignedPayment',
            ])
            ->where('pm_bank_statement_id', $statement->id)
            ->when($status === PmBankStatementLine::MATCH_MATCHED, fn (Builder $query) => $query->allocatedToTenant())
            ->when($status === PmBankStatementLine::MATCH_UNMATCHED, fn (Builder $query) => $query->awaitingTenant())
            ->when($status === PmBankStatementLine::MATCH_BANK_ONLY, fn (Builder $query) => $query->where('match_status', PmBankStatementLine::MATCH_BANK_ONLY))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $w) use ($q): void {
                    $w->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('counterparty', 'like', '%'.$q.'%')
                        ->orWhere('narration', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('txn_date')
            ->orderByDesc('id');

        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, TabularExport::TABLE_FORMATS, true)) {
            $rows = (clone $linesQuery)->limit(8000)->get();

            return TabularExport::stream(
                'bank-statement-'.$statement->id.'-'.now()->format('Ymd_His'),
                ['Date', 'Reference', 'Phone', 'Payer', 'Tenant account', 'Tenant', 'Unit', 'Direction', 'Amount', 'Status', 'Match reason', 'Paid to', 'Payee note', 'Narration'],
                function () use ($rows) {
                    foreach ($rows as $line) {
                        yield [
                            $line->txn_date?->format('Y-m-d') ?? '',
                            (string) ($line->reference ?? ''),
                            $line->displayPhone(),
                            (string) ($line->counterparty ?? ''),
                            $line->matchedTenantAccount(),
                            $line->matchedTenantName(),
                            $line->matchedUnitLabel(),
                            (string) ($line->direction ?? ''),
                            number_format((float) $line->amount, 2, '.', ''),
                            $line->displayMatchStatus(),
                            $line->matchReason(),
                            (string) ($line->paid_to_name ?? ''),
                            (string) ($line->paid_to_note ?? ''),
                            (string) ($line->narration ?? ''),
                        ];
                    }
                },
                $export,
                [
                    'title' => 'Statement · '.$statement->bank_name,
                    'subtitle' => trim($statement->account_name.' · '.$statement->periodLabel()),
                ],
            );
        }

        $lineTotal = (clone $linesQuery)->count();
        $requestedPageSize = $request->query('per_page');
        $perPage = ListPageSize::resolve($requestedPageSize, 100);
        $perPageValue = is_scalar($requestedPageSize) && strtolower(trim((string) $requestedPageSize)) === ListPageSize::ALL
            ? ListPageSize::ALL
            : (string) $perPage;
        $lines = $linesQuery->paginate($perPage)->withQueryString();

        $countBase = PmBankStatementLine::query()->where('pm_bank_statement_id', $statement->id);
        $counts = [
            'matched' => (clone $countBase)->allocatedToTenant()->count(),
            'unmatched' => (clone $countBase)->awaitingTenant()->count(),
            'bank_only' => (clone $countBase)->where('match_status', PmBankStatementLine::MATCH_BANK_ONLY)->count(),
        ];

        return property_view('property.agent.revenue.statement_show', [
            'statement' => $statement,
            'lines' => $lines,
            'counts' => $counts,
            'status' => $status,
            'assignLandlords' => User::query()
                ->where('property_portal_role', 'landlord')
                ->whereHas('landlordProperties')
                ->orderBy('name')
                ->get(['id', 'name', 'phone'])
                ->map(fn (User $landlord) => [
                    'id' => (int) $landlord->id,
                    'label' => trim($landlord->name.($landlord->phone ? ' · '.$landlord->phone : '')),
                ])
                ->values()
                ->all(),
            'assignEmployees' => app(PropertyHrEmployeeService::class)->queryForActor($request->user())
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'phone'])
                ->map(fn (Employee $employee) => [
                    'id' => (int) $employee->id,
                    'label' => trim($employee->full_name.($employee->employee_number ? ' · '.$employee->employee_number : '').($employee->phone ? ' · '.$employee->phone : '')),
                ])
                ->values()
                ->all(),
            'assignVendors' => PmVendor::query()
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'category'])
                ->map(fn (PmVendor $vendor) => [
                    'id' => (int) $vendor->id,
                    'label' => trim($vendor->name.($vendor->category ? ' · '.$vendor->category : '').($vendor->phone ? ' · '.$vendor->phone : '')),
                ])
                ->values()
                ->all(),
            'filters' => ['q' => $q, 'status' => $status, 'per_page' => $perPageValue],
            'perPageOptions' => ListPageSize::options($lineTotal, $perPage),
        ]);
    }

    public function searchTenants(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = PmTenant::query();

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $query->where(function (Builder $builder) use ($q, $digits): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('account_number', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('national_id', 'like', '%'.$q.'%');

                if ($digits !== '') {
                    $builder->orWhere('phone', 'like', '%'.$digits.'%');
                    if (str_starts_with($digits, '254') && strlen($digits) >= 10) {
                        $builder->orWhere('phone', 'like', '%0'.substr($digits, 3).'%');
                    } elseif (str_starts_with($digits, '0') && strlen($digits) >= 10) {
                        $builder->orWhere('phone', 'like', '%254'.substr($digits, 1).'%');
                    }
                } else {
                    $builder->orWhere('phone', 'like', '%'.$q.'%');
                }

                if (Schema::hasTable('pm_leases')
                    && Schema::hasTable('pm_lease_unit')
                    && Schema::hasTable('property_units')
                    && Schema::hasColumn('property_units', 'label')) {
                    $builder->orWhereHas('leases.units', function (Builder $uq) use ($q): void {
                        $uq->where('label', 'like', '%'.$q.'%');
                    });
                }
            });
        }

        $tenants = $query->orderBy('name')->limit(30)->get(['id', 'name', 'phone', 'account_number']);

        $items = $tenants->map(function (PmTenant $tenant) {
            $acct = trim((string) ($tenant->account_number ?? ''));
            $phone = trim((string) ($tenant->phone ?? ''));
            $parts = array_filter([$tenant->name, $acct, $phone]);

            return [
                'id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'phone' => $phone,
                'account_number' => $acct,
                'label' => implode(' · ', $parts),
            ];
        })->values()->all();

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    public function searchLandlords(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = User::query()
            ->where('property_portal_role', 'landlord')
            ->whereHas('landlordProperties');

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $query->where(function (Builder $builder) use ($q, $digits): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
                if ($digits !== '') {
                    $builder->orWhere('phone', 'like', '%'.$digits.'%');
                } else {
                    $builder->orWhere('phone', 'like', '%'.$q.'%');
                }
            });
        }

        $landlords = $query->orderBy('name')->limit(30)->get(['id', 'name', 'phone']);

        $items = $landlords->map(function (User $landlord) {
            $phone = trim((string) ($landlord->phone ?? ''));
            $parts = array_filter([$landlord->name, $phone]);

            return [
                'id' => (int) $landlord->id,
                'name' => (string) $landlord->name,
                'phone' => $phone,
                'label' => implode(' · ', $parts),
            ];
        })->values()->all();

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    public function recover(
        Request $request,
        PmBankStatement $statement,
        PropertyStatementMissingPaymentRecoveryService $recovery,
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);
        $result = $recovery->recoverStatement($statement, (int) $request->user()->id);

        $msg = sprintf('Recovered %d credits into Unmatched (%d skipped).', $result['recovered'], $result['skipped']);
        if ($result['errors'] !== []) {
            return back()->with('status', $msg)->withErrors(['recovery' => implode('; ', array_slice($result['errors'], 0, 5))]);
        }

        return back()->with('status', $msg.' Open Collections → Unmatched to assign tenants.');
    }

    public function recoverLine(
        Request $request,
        PmBankStatement $statement,
        PmBankStatementLine $line,
        PropertyStatementMissingPaymentRecoveryService $recovery,
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);

        if ((int) $line->pm_bank_statement_id !== (int) $statement->id) {
            abort(404);
        }

        $result = $recovery->recoverLine($line, (int) $request->user()->id);
        $unassignedId = (int) ($result['unassigned_payment_id'] ?? 0);

        if ($result['errors'] !== []) {
            return back()->withErrors(['recovery' => implode('; ', array_slice($result['errors'], 0, 3))]);
        }

        if ($unassignedId > 0) {
            return redirect()
                ->route('property.equity.unmatched.show', $unassignedId)
                ->with('status', 'Line sent to Unmatched. Assign a tenant to settle.');
        }

        if (($result['recovered'] ?? 0) > 0) {
            return back()->with('status', 'Line recovered into Unmatched.');
        }

        return back()->with('status', 'Nothing to recover for this line.');
    }

    public function assignLine(
        Request $request,
        PmBankStatement $statement,
        PmBankStatementLine $line,
        PropertyStatementMissingPaymentRecoveryService $recovery,
        EquityPaymentRepository $payments,
        PaymentAuditLogRepository $auditLogs,
    ): RedirectResponse|JsonResponse {
        $this->authorizeStatement($request, $statement);
        if ((int) $line->pm_bank_statement_id !== (int) $statement->id) {
            abort(404);
        }

        if ($request->boolean('split')) {
            return $this->splitLineAcrossTenants($request, $statement, $line, $recovery, $payments, $auditLogs);
        }

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:pm_tenants,id'],
        ]);

        $tenant = PmTenant::query()->find($data['tenant_id']);
        if (! $tenant) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'That tenant is not in this workspace.'], 422);
            }

            return back()->withErrors(['tenant_id' => 'That tenant is not in this workspace.'])->withInput();
        }

        if ($line->isAllocatedToTenant()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'This line is already on a tenant.'], 422);
            }

            return back()->with('status', 'This line is already on a tenant.');
        }

        $unassignedId = (int) ($line->unassigned_payment_id ?? 0);
        if ($unassignedId <= 0) {
            $prepared = $recovery->recoverLine($line, (int) $request->user()->id);
            $unassignedId = (int) ($prepared['unassigned_payment_id'] ?? 0);
            if ($unassignedId <= 0) {
                $message = $prepared['errors'][0] ?? 'This line could not be prepared for assignment.';

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['ok' => false, 'message' => $message], 422);
                }

                return back()->withErrors(['tenant_id' => $message])->withInput();
            }
            $line->refresh();
        }

        $unassigned = UnassignedPayment::query()->find($unassignedId);
        if (! $unassigned) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'The unmatched payment for this line is no longer available.'], 422);
            }

            return back()->withErrors(['tenant_id' => 'The unmatched payment for this line is no longer available.'])->withInput();
        }

        $method = (string) ($unassigned->payment_method ?: 'statement_import');
        $tx = [
            'transaction_id' => (string) $unassigned->transaction_id,
            'amount' => (float) $unassigned->amount,
            'account_number' => (string) ($unassigned->account_number ?? ''),
            'reference' => '',
            'phone' => (string) ($unassigned->phone ?? ''),
            'transaction_date' => $line->txn_date ?? $unassigned->created_at ?? now(),
            'raw_payload' => [
                'manual_assigned' => true,
                'unassigned_payment_id' => (int) $unassigned->id,
                'pm_bank_statement_line_id' => (int) $line->id,
                'reason' => (string) ($unassigned->reason ?? ''),
            ],
        ];

        Payment::query()
            ->where('transaction_id', (string) $unassigned->transaction_id)
            ->where('status', 'unmatched')
            ->delete();

        $payment = $payments->storeMatched($tx, (int) $tenant->id, 'manual', [
            'payment_method' => $method,
            'channel' => 'bank_statement',
            'source' => 'statement_import',
            'provider' => 'mpesa',
            'message' => 'Assigned to a tenant from the bank statement.',
            'agent_user_id' => (int) $request->user()->id,
        ]);

        $auditLogs->decision('success', [
            'stage' => 'manual_assign',
            'decision' => 'assigned',
            'unassigned_payment_id' => (int) $unassigned->id,
            'transaction_id' => (string) $unassigned->transaction_id,
            'tenant_id' => (int) $tenant->id,
            'payment_id' => (int) $payment->id,
            'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0),
            'pm_bank_statement_line_id' => (int) $line->id,
        ], 'manual_assign_decision');

        $line->update([
            'match_status' => PmBankStatementLine::MATCH_MATCHED,
            'matched_type' => 'payment',
            'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0) ?: null,
            'unassigned_payment_id' => null,
        ]);

        $unassigned->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Assigned to '.$tenant->name.' and posted.',
                'line_id' => (int) $line->id,
                'tenant' => [
                    'id' => (int) $tenant->id,
                    'name' => (string) $tenant->name,
                    'account_number' => (string) ($tenant->account_number ?? ''),
                    'show_url' => route('property.tenants.show', $tenant->id),
                ],
                'receipt_url' => (int) ($payment->pm_payment_id ?? 0) > 0
                    ? route('property.payments.receipt.show', (int) $payment->pm_payment_id)
                    : null,
            ]);
        }

        return back()->with('status', 'Assigned to '.$tenant->name.' and posted.');
    }

    private function splitLineAcrossTenants(
        Request $request,
        PmBankStatement $statement,
        PmBankStatementLine $line,
        PropertyStatementMissingPaymentRecoveryService $recovery,
        EquityPaymentRepository $payments,
        PaymentAuditLogRepository $auditLogs,
    ): RedirectResponse {
        if ((string) $line->direction !== 'credit' || $line->isAllocatedToTenant()) {
            return back()->withErrors(['allocations' => 'Only an unmatched credit can be split across tenants.']);
        }

        $data = $request->validate([
            'allocations' => ['required', 'array', $line->matched_type === 'payment_split_partial' ? 'min:1' : 'min:2', 'max:20'],
            'allocations.*.tenant_id' => ['required', 'integer', 'distinct', 'exists:pm_tenants,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $tenantIds = array_map('intval', array_column($data['allocations'], 'tenant_id'));
        $tenants = PmTenant::query()->whereIn('id', $tenantIds)->get()->keyBy('id');
        if ($tenants->count() !== count($tenantIds)) {
            return back()->withErrors(['allocations' => 'One or more tenants are outside your workspace.'])->withInput();
        }

        $unassignedId = (int) ($line->unassigned_payment_id ?? 0);
        if ($unassignedId <= 0) {
            $prepared = $recovery->recoverLine($line, (int) $request->user()->id);
            $unassignedId = (int) ($prepared['unassigned_payment_id'] ?? 0);
            if ($unassignedId <= 0) {
                return back()->withErrors(['allocations' => $prepared['errors'][0] ?? 'This statement line could not be prepared for assignment.']);
            }
            $line->refresh();
        }

        $unassigned = UnassignedPayment::query()->lockForUpdate()->find($unassignedId);
        if (! $unassigned) {
            return back()->withErrors(['allocations' => 'The unmatched payment is no longer available.']);
        }
        $availableCents = (int) round((float) $unassigned->amount * 100);
        $allocationCents = array_sum(array_map(
            static fn (array $allocation): int => (int) round((float) $allocation['amount'] * 100),
            $data['allocations'],
        ));
        if ($allocationCents > $availableCents) {
            return back()->withErrors(['allocations' => 'The unmatched payment amount changed. Reload the statement and try again.']);
        }

        $originalTransactionId = (string) $unassigned->transaction_id;
        $splitGroupId = 'bank-line-'.$line->id.'-'.substr(hash('sha256', $originalTransactionId), 0, 12);
        $newRecipients = [];
        foreach ($data['allocations'] as $allocation) {
            $tenant = $tenants->get((int) $allocation['tenant_id']);
            $newRecipients[] = [
                'tenant_id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'account_number' => (string) ($tenant->account_number ?? ''),
                'amount' => round((float) $allocation['amount'], 2),
            ];
        }

        $method = (string) ($unassigned->payment_method ?: 'statement_import');
        $options = [
            'payment_method' => $method,
            'channel' => 'bank_statement',
            'source' => 'statement_import',
            'provider' => 'mpesa',
            'message' => 'Split bank-statement receipt assigned to tenants.',
            'agent_user_id' => (int) $request->user()->id,
        ];

        $created = [];
        DB::transaction(function () use (
            $data, $line, $unassigned, $request, $payments, $auditLogs, $tenants,
            $originalTransactionId, $splitGroupId, $newRecipients, $options,
            $availableCents, $allocationCents, &$created
        ): void {
            Payment::query()
                ->where('transaction_id', $originalTransactionId)
                ->where('status', 'unmatched')
                ->delete();

            $existingShares = Payment::query()
                ->with(['pmPayment.tenant'])
                ->where('external_transaction_reference', $originalTransactionId)
                ->where('transaction_id', 'like', $originalTransactionId.':SPLIT:%')
                ->where('status', 'matched')
                ->get()
                ->filter(function (Payment $payment) use ($line): bool {
                    $sourceLineId = (int) data_get($payment->raw_payload, 'pm_bank_statement_line_id', 0);

                    return ($sourceLineId === 0 || $sourceLineId === (int) $line->id)
                        && $payment->pmPayment?->status === PmPayment::STATUS_COMPLETED;
                })
                ->values();
            $recipients = array_merge($existingShares->map(fn (Payment $payment) => [
                'tenant_id' => (int) $payment->tenant_id,
                'name' => (string) ($payment->pmPayment?->tenant?->name ?? $payment->payer_name ?? 'Tenant'),
                'account_number' => (string) ($payment->account_number ?? ''),
                'amount' => round((float) $payment->amount, 2),
            ])->all(), $newRecipients);
            $partOffset = $existingShares->count();
            $count = count($recipients);
            foreach (array_values($data['allocations']) as $index => $allocation) {
                $tenant = $tenants->get((int) $allocation['tenant_id']);
                $partNumber = $partOffset + $index + 1;
                $suffix = ':SPLIT:'.substr(hash('sha256', $originalTransactionId), 0, 12).':'.str_pad((string) $partNumber, 2, '0', STR_PAD_LEFT);
                $partTransactionId = substr($originalTransactionId, 0, 255 - strlen($suffix)).$suffix;
                $amount = round((float) $allocation['amount'], 2);
                $tx = [
                    'transaction_id' => $partTransactionId,
                    'external_transaction_reference' => $originalTransactionId,
                    'amount' => $amount,
                    'account_number' => (string) ($tenant->account_number ?? ''),
                    'tenant_account_number' => (string) ($tenant->account_number ?? ''),
                    'phone' => (string) ($unassigned->phone ?? ''),
                    'payer_name' => (string) ($line->counterparty ?? ''),
                    'reference' => (string) ($line->reference ?? ''),
                    'transaction_date' => $line->txn_date ?? $unassigned->created_at ?? now(),
                    'split_group_id' => $splitGroupId,
                    'split_payment' => true,
                    'split_index' => $partNumber,
                    'split_count' => $count,
                    'split_recipients' => $recipients,
                    'raw_payload' => [
                        'manual_assigned' => true,
                        'split_payment' => true,
                        'split_group_id' => $splitGroupId,
                        'original_transaction_id' => $originalTransactionId,
                        'unassigned_payment_id' => (int) $unassigned->id,
                        'pm_bank_statement_line_id' => (int) $line->id,
                        'split_index' => $partNumber,
                        'split_count' => $count,
                        'split_recipients' => $recipients,
                    ],
                ];
                $payment = $payments->storeMatched($tx, (int) $tenant->id, 'manual_split', $options);
                $created[] = [
                    'tenant_id' => (int) $tenant->id,
                    'tenant_name' => (string) $tenant->name,
                    'amount' => $amount,
                    'payment_id' => (int) $payment->id,
                    'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0),
                ];
            }

            $firstPaymentId = (int) ($existingShares->first()?->pm_payment_id ?? $created[0]['pm_payment_id'] ?? 0);
            $firstPmPayment = $firstPaymentId > 0 ? PmPayment::query()->find($firstPaymentId) : null;
            if ($firstPmPayment) {
                $firstMeta = is_array($firstPmPayment->meta) ? $firstPmPayment->meta : [];
                $firstMeta['split_payment'] = true;
                $firstMeta['split_recipients'] = $recipients;
                $firstMeta['split_count'] = count($recipients);
                $firstPmPayment->meta = $firstMeta;
                $firstPmPayment->save();
            }

            $remainingCents = $availableCents - $allocationCents;
            if ($remainingCents > 0) {
                $unassigned->update(['amount' => $remainingCents / 100]);
            } else {
                $unassigned->delete();
            }
            $line->update([
                'match_status' => $remainingCents > 0 ? PmBankStatementLine::MATCH_UNMATCHED : PmBankStatementLine::MATCH_MATCHED,
                'matched_type' => $remainingCents > 0 ? 'payment_split_partial' : 'payment_split',
                'pm_payment_id' => $firstPaymentId > 0 ? $firstPaymentId : null,
                'unassigned_payment_id' => $remainingCents > 0 ? $unassigned->id : null,
            ]);

            $auditLogs->decision('success', [
                'stage' => 'manual_split_assign',
                'decision' => 'split_assigned',
                'unassigned_payment_id' => (int) $unassigned->id,
                'transaction_id' => $originalTransactionId,
                'split_group_id' => $splitGroupId,
                'pm_bank_statement_line_id' => (int) $line->id,
                'allocations' => $created,
                'remaining_amount' => $remainingCents / 100,
            ], 'manual_split_assign_decision');

        });

        $remainingAmount = max(0, ($availableCents - $allocationCents) / 100);
        return back()->with('status', $remainingAmount > 0
            ? 'Posted '.count($created).' tenant allocation(s). KES '.number_format($remainingAmount, 2).' remains available to allocate.'
            : 'Receipt fully allocated across '.count($created).' tenant(s). Each tenant payment keeps the same bank receipt reference.');
    }

    /**
     * Repair statement rows left unmatched by an earlier split attempt. Split shares
     * are real completed tenant payments; derive the consumed and remaining amounts
     * from those records rather than asking the operator to allocate them again.
     */
    private function reconcilePreviouslySavedSplitShares(PmBankStatement $statement): void
    {
        $lines = PmBankStatementLine::query()
            ->where('pm_bank_statement_id', $statement->id)
            ->where('direction', 'credit')
            ->where('match_status', PmBankStatementLine::MATCH_UNMATCHED)
            ->whereNotNull('reference')
            ->get();
        $references = $lines->pluck('reference')->map(fn ($ref) => strtoupper(trim((string) $ref)))->filter()->unique()->values();
        if ($references->isEmpty()) {
            return;
        }

        $sharesByReference = Payment::query()
            ->with(['pmPayment.tenant'])
            ->whereIn('external_transaction_reference', $references)
            ->where('transaction_id', 'like', '%:SPLIT:%')
            ->where('status', 'matched')
            ->get()
            ->groupBy(fn (Payment $payment) => strtoupper(trim((string) $payment->external_transaction_reference)));

        foreach ($lines as $line) {
            $reference = strtoupper(trim((string) $line->reference));
            $shares = ($sharesByReference->get($reference) ?? collect())
                ->filter(function (Payment $payment) use ($line): bool {
                    $sourceLineId = (int) data_get($payment->raw_payload, 'pm_bank_statement_line_id', 0);

                    return $sourceLineId === 0 || $sourceLineId === (int) $line->id;
                })
                ->filter(fn (Payment $payment) => $payment->pmPayment?->status === PmPayment::STATUS_COMPLETED)
                ->values();
            if ($shares->isEmpty()) {
                continue;
            }

            $allocatedCents = (int) $shares->sum(fn (Payment $payment) => (int) round((float) $payment->amount * 100));
            $grossCents = (int) round((float) $line->amount * 100);
            if ($allocatedCents <= 0 || $allocatedCents > $grossCents) {
                continue;
            }

            DB::transaction(function () use ($line, $shares, $allocatedCents, $grossCents, $reference): void {
                $line = PmBankStatementLine::query()->whereKey($line->id)->lockForUpdate()->first();
                if (! $line || $line->match_status !== PmBankStatementLine::MATCH_UNMATCHED) {
                    return;
                }

                $recipients = $shares->map(fn (Payment $payment) => [
                    'tenant_id' => (int) $payment->tenant_id,
                    'name' => (string) ($payment->pmPayment?->tenant?->name ?? $payment->payer_name ?? 'Tenant'),
                    'account_number' => (string) ($payment->account_number ?? ''),
                    'amount' => round((float) $payment->amount, 2),
                ])->all();
                $firstPayment = $shares->first();
                $firstPmPayment = $firstPayment?->pmPayment;
                if (! $firstPmPayment) {
                    return;
                }
                $meta = is_array($firstPmPayment->meta) ? $firstPmPayment->meta : [];
                $meta['split_payment'] = true;
                $meta['split_recipients'] = $recipients;
                $meta['split_count'] = count($recipients);
                $firstPmPayment->meta = $meta;
                $firstPmPayment->save();

                $remainingCents = $grossCents - $allocatedCents;
                $unassigned = $line->unassigned_payment_id
                    ? UnassignedPayment::query()->find($line->unassigned_payment_id)
                    : UnassignedPayment::query()->where('transaction_id', $reference)->first();

                if ($remainingCents > 0) {
                    if (! $unassigned) {
                        $unassigned = UnassignedPayment::query()->create([
                            'transaction_id' => $reference,
                            'amount' => $remainingCents / 100,
                            'phone' => $line->phone,
                            'agent_user_id' => $line->agent_user_id,
                            'payment_method' => 'statement_import',
                            'reason' => 'Remaining balance after a previously saved split allocation.',
                            'created_at' => now(),
                        ]);
                    } else {
                        $unassigned->update(['amount' => $remainingCents / 100]);
                    }
                } else {
                    $unassigned?->delete();
                    $unassigned = null;
                }

                $line->update([
                    'match_status' => $remainingCents > 0 ? PmBankStatementLine::MATCH_UNMATCHED : PmBankStatementLine::MATCH_MATCHED,
                    'matched_type' => $remainingCents > 0 ? 'payment_split_partial' : 'payment_split',
                    'pm_payment_id' => (int) $firstPayment->pm_payment_id ?: null,
                    'unassigned_payment_id' => $unassigned?->id,
                ]);
            });
        }
    }

    public function classifyPayee(Request $request, PmBankStatement $statement, PmBankStatementLine $line): RedirectResponse
    {
        $this->authorizeStatement($request, $statement);
        if ((int) $line->pm_bank_statement_id !== (int) $statement->id) {
            abort(404);
        }
        if ((string) $line->match_status !== PmBankStatementLine::MATCH_BANK_ONLY) {
            return back()->withErrors(['paid_to_kind' => 'Only bank-only lines can record a payee here.']);
        }
        if (! Schema::hasColumn('pm_bank_statement_lines', 'paid_to_kind')) {
            return back()->withErrors(['paid_to_kind' => 'The payee columns are not on this database yet. Run migrations.']);
        }

        $data = $request->validate([
            'paid_to_kind' => ['required', 'in:landlord,employee,vendor,bank_charge,other'],
            'landlord_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'paid_to_name' => ['nullable', 'string', 'max:191'],
            'paid_to_note' => ['nullable', 'string', 'max:255'],
        ]);

        $landlordId = null;
        $employeeId = null;
        $vendorId = null;
        $name = '';
        $matchedType = 'other_payee';

        if ($data['paid_to_kind'] === 'landlord') {
            $landlord = User::query()
                ->where('property_portal_role', 'landlord')
                ->whereKey((int) ($data['landlord_id'] ?? 0))
                ->whereHas('landlordProperties')
                ->first();
            if (! $landlord) {
                return back()->withErrors(['landlord_id' => 'Choose a landlord from the list.'])->withInput();
            }
            $landlordId = (int) $landlord->id;
            $name = (string) $landlord->name;
            $matchedType = 'landlord_payout';
        } elseif ($data['paid_to_kind'] === 'employee') {
            $employee = app(PropertyHrEmployeeService::class)->queryForActor($request->user())
                ->whereKey((int) ($data['employee_id'] ?? 0))
                ->first();
            if (! $employee) {
                return back()->withErrors(['employee_id' => 'Choose an employee from this workspace.'])->withInput();
            }
            $employeeId = (int) $employee->id;
            $name = $employee->full_name;
            $matchedType = 'employee_payment';
        } elseif ($data['paid_to_kind'] === 'vendor') {
            $vendor = PmVendor::query()->whereKey((int) ($data['vendor_id'] ?? 0))->first();
            if (! $vendor) {
                return back()->withErrors(['vendor_id' => 'Choose a vendor from this workspace.'])->withInput();
            }
            $vendorId = (int) $vendor->id;
            $name = (string) $vendor->name;
            $matchedType = 'vendor_payment';
        } elseif ($data['paid_to_kind'] === 'bank_charge') {
            $name = 'Bank charge';
            $matchedType = 'bank_charge';
        } else {
            $name = trim((string) ($data['paid_to_name'] ?? ''));
            if ($name === '') {
                return back()->withErrors(['paid_to_name' => 'Enter who was paid.'])->withInput();
            }
        }

        $line->update([
            'paid_to_kind' => $data['paid_to_kind'],
            'paid_to_landlord_id' => $landlordId,
            'paid_to_employee_id' => $employeeId,
            'paid_to_vendor_id' => $vendorId,
            'paid_to_name' => $name,
            'paid_to_note' => trim((string) ($data['paid_to_note'] ?? '')) ?: null,
            'matched_type' => $matchedType,
        ]);

        return back()->with('status', 'Recorded '.$line->reference.' as paid to '.$name.'.');
    }

    public function autoAssign(
        Request $request,
        PmBankStatement $statement,
        PropertyStatementAutoAssignService $autoAssign,
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);

        $result = $autoAssign->assignStatement($statement, (int) $request->user()->id);
        $msg = sprintf(
            'Auto-posted %d receipts where the phone identified one tenant (or the payer name disambiguated a shared phone). Linked %d lines that already had a receipt. %d lines stayed unmatched.',
            $result['posted'],
            $result['linked'],
            $result['skipped'],
        );
        if ($result['errors'] !== []) {
            return back()->with('status', $msg)->withErrors(['auto_assign' => implode('; ', $result['errors'])]);
        }

        return back()->with('status', $msg);
    }

    public function rematch(
        Request $request,
        PmBankStatement $statement,
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);

        $cleared = PmBankStatementLine::query()
            ->where('pm_bank_statement_id', $statement->id)
            ->where('match_status', PmBankStatementLine::MATCH_MATCHED)
            ->where('matched_type', 'unassigned')
            ->whereNull('pm_payment_id')
            ->update([
                'match_status' => PmBankStatementLine::MATCH_UNMATCHED,
                'matched_type' => null,
                'unassigned_payment_id' => null,
            ]);

        return back()->with('status', "Reset {$cleared} statement-only matches. Use Recover missing to land them in Unmatched again, or re-upload the file to rematch against receipts.");
    }

    public function enrichPayers(
        Request $request,
        PmBankStatement $statement,
        CoopBankAccountStatementImportService $import,
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);

        $path = $this->storedStatementPath($statement);
        if ($path === null) {
            return back()->withErrors([
                'statement_file' => 'The original upload file is no longer on the server. Upload the same Co-op PDF again, then click Fill missing phones & names.',
            ]);
        }

        $result = $import->enrichPayersFromPath($statement, $path);

        return back()->with(
            'status',
            sprintf(
                'Filled phones/names on %d lines from the PDF. %d lines still have a blank phone or payer name (usually a page break in the bank PDF text).',
                $result['updated'],
                $result['still_blank'],
            )
        );
    }

    private function storedStatementPath(PmBankStatement $statement): ?string
    {
        $filename = basename((string) ($statement->source_filename ?? ''));
        if ($filename === '') {
            return null;
        }

        $disk = Storage::disk('local');
        foreach ($disk->allFiles('pm-bank-imports') as $relative) {
            if (strcasecmp(basename($relative), $filename) === 0) {
                return $disk->path($relative);
            }
        }

        return null;
    }

    private function authorizeStatement(Request $request, PmBankStatement $statement): void
    {
        $user = $request->user();
        if (($user->is_super_admin ?? false) === true) {
            return;
        }
        if ((int) $statement->agent_user_id !== (int) $user->id) {
            abort(403);
        }
    }
}
