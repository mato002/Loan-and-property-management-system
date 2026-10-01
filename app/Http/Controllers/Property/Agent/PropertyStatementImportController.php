<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Services\Property\PropertyStatementMissingPaymentRecoveryService;
use App\Services\Property\PropertyStatementUploadService;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            ->when($status !== '', fn (Builder $query) => $query->where('match_status', $status))
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
                ['Date', 'Reference', 'Phone', 'Payer', 'Tenant account', 'Tenant', 'Unit', 'Direction', 'Amount', 'Status', 'Match reason', 'Narration'],
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

        $lines = $linesQuery->paginate(50)->withQueryString();

        $counts = [
            'matched' => PmBankStatementLine::query()->where('pm_bank_statement_id', $statement->id)->where('match_status', 'matched')->count(),
            'unmatched' => PmBankStatementLine::query()->where('pm_bank_statement_id', $statement->id)->where('match_status', 'unmatched')->count(),
            'bank_only' => PmBankStatementLine::query()->where('pm_bank_statement_id', $statement->id)->where('match_status', 'bank_only')->count(),
        ];

        return property_view('property.agent.revenue.statement_show', [
            'statement' => $statement,
            'lines' => $lines,
            'counts' => $counts,
            'status' => $status,
            'filters' => ['q' => $q, 'status' => $status],
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
