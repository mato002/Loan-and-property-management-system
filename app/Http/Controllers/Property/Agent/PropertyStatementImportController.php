<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Services\Property\PropertyStatementMissingPaymentRecoveryService;
use App\Services\Property\PropertyStatementUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PropertyStatementImportController extends Controller
{
    public function index(Request $request): View
    {
        $statements = Schema::hasTable('pm_bank_statements')
            ? PmBankStatement::query()->orderByDesc('id')->limit(30)->get()
            : collect();

        return property_view('property.agent.revenue.statement_upload', [
            'statements' => $statements,
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

    public function show(Request $request, PmBankStatement $statement): View
    {
        $this->authorizeStatement($request, $statement);

        $status = trim((string) $request->query('status', ''));
        $lines = PmBankStatementLine::query()
            ->where('pm_bank_statement_id', $statement->id)
            ->when($status !== '', fn ($q) => $q->where('match_status', $status))
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

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
