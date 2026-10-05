<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Models\PmTenant;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\Property\CoopBankAccountStatementImportService;
use App\Services\Property\PropertyStatementAutoAssignService;
use App\Services\Property\PropertyStatementMissingPaymentRecoveryService;
use App\Services\Property\PropertyStatementUploadService;
use App\Support\ListPageSize;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
                PropertyStatementUploadService::PROVIDER_SAFARICOM_C2B => 'Safaricom C2B CSV',
            ],
        ]);
    }

    public function store(Request $request, PropertyStatementUploadService $uploader): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'in:auto,coop_bank,safaricom_c2b'],
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
                ' Auto-posted %d receipts where the phone and name matched one tenant and the M-Pesa code was not already a receipt. Linked %d lines that already had a receipt.',
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
            'assignTenants' => PmTenant::query()
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'account_number'])
                ->map(fn (PmTenant $tenant) => [
                    'id' => (int) $tenant->id,
                    'label' => trim($tenant->name.($tenant->account_number ? ' · '.$tenant->account_number : '').($tenant->phone ? ' · '.$tenant->phone : '')),
                ])
                ->values()
                ->all(),
            'filters' => ['q' => $q, 'status' => $status, 'per_page' => $perPageValue],
            'perPageOptions' => ListPageSize::options($lineTotal, $perPage),
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
    ): RedirectResponse {
        $this->authorizeStatement($request, $statement);
        if ((int) $line->pm_bank_statement_id !== (int) $statement->id) {
            abort(404);
        }

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:pm_tenants,id'],
        ]);

        $tenant = PmTenant::query()->find($data['tenant_id']);
        if (! $tenant) {
            return back()->withErrors(['tenant_id' => 'That tenant is not in this workspace.'])->withInput();
        }

        if ($line->isAllocatedToTenant()) {
            return back()->with('status', 'This line is already on a tenant.');
        }

        $unassignedId = (int) ($line->unassigned_payment_id ?? 0);
        if ($unassignedId <= 0) {
            $prepared = $recovery->recoverLine($line, (int) $request->user()->id);
            $unassignedId = (int) ($prepared['unassigned_payment_id'] ?? 0);
            if ($unassignedId <= 0) {
                $message = $prepared['errors'][0] ?? 'This line could not be prepared for assignment.';

                return back()->withErrors(['tenant_id' => $message])->withInput();
            }
            $line->refresh();
        }

        $unassigned = UnassignedPayment::query()->find($unassignedId);
        if (! $unassigned) {
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

        return back()->with('status', 'Assigned to '.$tenant->name.' and posted.');
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
            'paid_to_kind' => ['required', 'in:landlord,bank_charge,other'],
            'landlord_id' => ['nullable', 'integer'],
            'paid_to_name' => ['nullable', 'string', 'max:191'],
            'paid_to_note' => ['nullable', 'string', 'max:255'],
        ]);

        $landlordId = null;
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
            'Auto-posted %d receipts where the phone and name matched one tenant and the M-Pesa code was not already a receipt. Linked %d lines that already had a receipt. %d lines stayed unmatched.',
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
