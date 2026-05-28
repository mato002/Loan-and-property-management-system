<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\EquitySyncRun;
use App\Models\Payment;
use App\Models\PmSmsIngest;
use App\Models\PmTenant;
use App\Models\UnassignedPayment;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\EquityBankService;
use App\Services\PaymentMatchingService;
use App\Support\MpesaSmsForwarderParser;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquitySyncController extends Controller
{
    public function syncStatus(Request $request): View|StreamedResponse
    {
        if (! Schema::hasTable('equity_sync_runs')) {
            return $this->notReadyView('Equity sync runs table is missing. Run migrations first.');
        }

        $q = trim((string) $request->query('q', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $trigger = strtolower(trim((string) $request->query('trigger', '')));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $sort = strtolower(trim((string) $request->query('sort', 'started_at')));
        $dir = strtolower(trim((string) $request->query('dir', 'desc')));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $query = EquitySyncRun::query();
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($trigger !== '') {
            $query->where('trigger', $trigger);
        }
        if ($from !== '') {
            $query->whereDate('started_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('started_at', '<=', $to);
        }
        if ($q !== '') {
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('message', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhere('trigger', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }
        $sortMap = [
            'started_at' => 'started_at',
            'finished_at' => 'finished_at',
            'status' => 'status',
            'fetched_count' => 'fetched_count',
            'matched_count' => 'matched_count',
            'unmatched_count' => 'unmatched_count',
            'error_count' => 'error_count',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$sort] ?? 'started_at';
        $sortDir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'equity-sync-runs-'.now()->format('Ymd_His'),
                ['Started', 'Finished', 'Trigger', 'Status', 'Fetched', 'Matched', 'Unmatched', 'Duplicates', 'Errors', 'Message'],
                function () use ($rows) {
                    foreach ($rows as $run) {
                        yield [
                            optional($run->started_at)->format('Y-m-d H:i:s'),
                            optional($run->finished_at)->format('Y-m-d H:i:s'),
                            ucfirst((string) $run->trigger),
                            ucfirst((string) $run->status),
                            (string) ($run->fetched_count ?? 0),
                            (string) ($run->matched_count ?? 0),
                            (string) ($run->unmatched_count ?? 0),
                            (string) ($run->duplicate_count ?? 0),
                            (string) ($run->error_count ?? 0),
                            (string) ($run->message ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $runs = (clone $query)->paginate($perPage)->withQueryString();
        $latest = EquitySyncRun::query()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
        $latestSuccess = EquitySyncRun::query()
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
        $liveStats = [
            'fetched' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'duplicates' => 0,
        ];
        if (Schema::hasTable('payments')) {
            $liveStats['fetched'] = (int) Payment::query()->count();
            $liveStats['matched'] = (int) Payment::query()->whereIn('status', ['matched', 'completed'])->count();
            $liveStats['duplicates'] = (int) Payment::query()->where('status', 'duplicate')->count();
        }
        if (Schema::hasTable('unassigned_payments')) {
            $liveStats['unmatched'] = (int) UnassignedPayment::query()->count();
        } elseif (Schema::hasTable('payments')) {
            $liveStats['unmatched'] = (int) Payment::query()->where('status', 'unmatched')->count();
        }

        return property_view('property.agent.equity.sync_status', [
            'runs' => $runs,
            'latest' => $latest,
            'latestSuccess' => $latestSuccess,
            'liveStats' => $liveStats,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'trigger' => $trigger,
                'from' => $from,
                'to' => $to,
                'sort' => $sortBy,
                'dir' => $sortDir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function triggerSync(EquityBankService $equityBankService): RedirectResponse
    {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('equity_sync_runs')) {
            return back()->withErrors(['equity' => 'Equity module is not ready. Run migrations and retry.']);
        }

        if (! $equityBankService->isConfigured()) {
            return back()->withErrors([
                'equity' => 'Equity API is not configured. Set EQUITY_API_BASE_URL, EQUITY_API_USERNAME, EQUITY_API_PASSWORD, EQUITY_API_KEY and EQUITY_API_SECRET in your .env, then run `php artisan config:clear`.',
            ]);
        }

        Artisan::call('fetch:equity-transactions', ['--manual' => true]);

        return back()->with('success', 'Equity sync executed.');
    }

    public function unmatchedPayments(Request $request): View
    {
        if (! Schema::hasTable('unassigned_payments')) {
            return $this->notReadyView('Unassigned payments table is missing. Run migrations first.');
        }

        $hasPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $query = $this->buildUnmatchedQuery($request, $hasPaymentMethod);
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));
        $items = $query->paginate($perPage)->withQueryString();

        $this->enrichUnmatchedItems($items->getCollection(), $hasPaymentMethod);

        return property_view('property.agent.equity.unmatched_payments', [
            'items' => $items,
            'hasPaymentMethod' => $hasPaymentMethod,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'name' => (string) $request->query('name', ''),
                'source' => (string) $request->query('source', ''),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function unmatchedPaymentsExport(Request $request): StreamedResponse
    {
        if (! Schema::hasTable('unassigned_payments')) {
            abort(404, 'Unassigned payments table is missing.');
        }

        $hasPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $query = $this->buildUnmatchedQuery($request, $hasPaymentMethod);
        $messageMaps = $this->loadUnmatchedMessageMaps((clone $query)->pluck('transaction_id')->all());
        $format = strtolower((string) $request->query('format', 'csv'));
        if ($format === 'pdf') {
            return TabularExport::stream(
                'unmatched-payments-'.now()->format('Ymd_His'),
                ['Date', 'Transaction', 'Payer name', 'Amount', 'Account', 'Phone', 'Source', 'Message', 'Reason'],
                function () use ($query, $hasPaymentMethod, $messageMaps) {
                    foreach ($query->cursor() as $item) {
                        $this->enrichUnmatchedItem(
                            $item,
                            $hasPaymentMethod,
                            $messageMaps['ingests'],
                            $messageMaps['payment_messages']
                        );
                        yield [
                            optional($item->created_at)->format('Y-m-d H:i:s'),
                            (string) $item->transaction_id,
                            (string) ($item->payer_name ?? ''),
                            number_format((float) $item->amount, 2, '.', ''),
                            (string) ($item->account_number ?? ''),
                            (string) ($item->phone ?? ''),
                            (string) ($item->source_label ?? 'Equity'),
                            (string) ($item->display_message ?? ''),
                            (string) ($item->reason ?? ''),
                        ];
                    }
                },
                'pdf'
            );
        }

        $isXls = $format === 'xls';
        $sep = $isXls ? "\t" : ",";
        $stamp = now()->format('Ymd_His');
        $filename = 'unmatched-payments-'.$stamp.($isXls ? '.xls' : '.csv');
        $contentType = $isXls
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($query, $sep, $hasPaymentMethod, $messageMaps) {
            $out = fopen('php://output', 'w');
            if (! $out) {
                return;
            }
            $headers = ['Date', 'Transaction', 'Payer name', 'Amount', 'Account', 'Phone', 'Source', 'Message', 'Reason'];
            if ($sep === ',') {
                fputcsv($out, $headers);
            } else {
                fwrite($out, implode($sep, $headers)."\n");
            }

            foreach ($query->cursor() as $item) {
                $this->enrichUnmatchedItem(
                    $item,
                    $hasPaymentMethod,
                    $messageMaps['ingests'],
                    $messageMaps['payment_messages']
                );
                $row = [
                    optional($item->created_at)->format('Y-m-d H:i:s'),
                    (string) $item->transaction_id,
                    (string) ($item->payer_name ?? ''),
                    number_format((float) $item->amount, 2, '.', ''),
                    (string) ($item->account_number ?? ''),
                    (string) ($item->phone ?? ''),
                    (string) ($item->source_label ?? 'Equity'),
                    (string) ($item->display_message ?? ''),
                    (string) ($item->reason ?? ''),
                ];

                if ($sep === ',') {
                    fputcsv($out, $row);
                } else {
                    fwrite($out, implode($sep, array_map(
                        static fn ($v) => str_replace(["\t", "\r", "\n"], ' ', (string) $v),
                        $row
                    ))."\n");
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    public function unmatchedPaymentsPrint(Request $request): Response
    {
        if (! Schema::hasTable('unassigned_payments')) {
            abort(404, 'Unassigned payments table is missing.');
        }

        $hasPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $items = $this->buildUnmatchedQuery($request, $hasPaymentMethod)
            ->limit(2000)
            ->get();
        $this->enrichUnmatchedItems($items, $hasPaymentMethod);

        return response()->view('property.agent.equity.unmatched_payments_print', [
            'items' => $items,
            'hasPaymentMethod' => $hasPaymentMethod,
        ]);
    }

    public function showUnmatchedPayment(Request $request, UnassignedPayment $unassignedPayment): View
    {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            return $this->notReadyView('Payments module is not ready. Run migrations first.');
        }

        $this->authorizeUnassignedPaymentAccess($request, $unassignedPayment);

        $tenantQuery = PmTenant::query()->orderBy('name');
        $user = $request->user();
        if (
            $user
            && ($user->is_super_admin ?? false) !== true
            && (string) ($user->property_portal_role ?? '') === 'agent'
            && Schema::hasColumn('pm_tenants', 'agent_user_id')
        ) {
            $tenantQuery->where('agent_user_id', $user->id);
        }

        return property_view('property.agent.equity.unmatched_assign', [
            'item' => $unassignedPayment,
            'tenants' => $tenantQuery->get(['id', 'name', 'phone', 'account_number']),
        ]);
    }

    public function assignUnmatchedPayment(
        Request $request,
        UnassignedPayment $unassignedPayment,
        EquityPaymentRepository $payments,
        PaymentAuditLogRepository $auditLogs
    ): RedirectResponse {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            return back()->withErrors(['payments' => 'Payments module is not ready. Run migrations first.']);
        }

        $this->authorizeUnassignedPaymentAccess($request, $unassignedPayment);

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:pm_tenants,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // The selected tenant must belong to the agent's workspace.
        $this->authorizeTenantAccess($request, (int) $data['tenant_id']);

        $method = (string) ($unassignedPayment->payment_method ?: 'equity');
        $isSms = $method === 'sms_forwarder';

        $tx = [
            'transaction_id' => (string) $unassignedPayment->transaction_id,
            'amount' => (float) $unassignedPayment->amount,
            'account_number' => (string) ($unassignedPayment->account_number ?? ''),
            'reference' => '',
            'phone' => (string) ($unassignedPayment->phone ?? ''),
            'transaction_date' => $unassignedPayment->created_at ?? now(),
            'raw_payload' => [
                'manual_assigned' => true,
                'unassigned_payment_id' => (int) $unassignedPayment->id,
                'reason' => (string) ($unassignedPayment->reason ?? ''),
                'note' => (string) ($data['note'] ?? ''),
            ],
        ];

        $options = [
            'payment_method' => $method,
            'channel' => $isSms ? 'mpesa_sms_ingest' : 'equity_paybill',
            'source' => $isSms ? 'sms_ingest' : 'equity_api',
            'provider' => $isSms ? 'mpesa' : 'equity',
            'message' => 'Manually assigned by agent from unposted payments queue.',
        ];

        // A matching unmatched ledger row may already exist with the same transaction_id.
        // Remove only unmatched entries so we can post the matched payment without unique-key collision.
        Payment::query()
            ->where('transaction_id', (string) $unassignedPayment->transaction_id)
            ->where('status', 'unmatched')
            ->delete();

        $payment = $payments->storeMatched($tx, (int) $data['tenant_id'], 'manual', $options);

        if ($isSms) {
            PmSmsIngest::query()
                ->where('provider_txn_code', (string) $unassignedPayment->transaction_id)
                ->whereNull('pm_payment_id')
                ->update([
                    'matched_tenant_id' => (int) $data['tenant_id'],
                    'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0) ?: null,
                    'match_status' => 'matched',
                    'match_note' => 'Manually matched by agent from unmatched payments queue.',
                ]);
        }

        $auditLogs->decision('success', [
            'stage' => 'manual_assign',
            'decision' => 'assigned',
            'unassigned_payment_id' => (int) $unassignedPayment->id,
            'transaction_id' => (string) $unassignedPayment->transaction_id,
            'tenant_id' => (int) $data['tenant_id'],
            'payment_id' => (int) $payment->id,
            'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0),
            'note' => (string) ($data['note'] ?? ''),
        ], 'manual_assign_decision');

        $unassignedPayment->delete();

        return redirect()
            ->route('property.equity.unmatched')
            ->with('success', 'Payment assigned and posted successfully.');
    }

    public function rematchUnmatchedPayment(
        Request $request,
        UnassignedPayment $unassignedPayment,
        EquityPaymentRepository $payments,
        PaymentMatchingService $matcher,
        PaymentAuditLogRepository $auditLogs
    ): RedirectResponse {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            return back()->withErrors(['payments' => 'Payments module is not ready. Run migrations first.']);
        }

        $this->authorizeUnassignedPaymentAccess($request, $unassignedPayment);

        $tx = [
            'transaction_id' => (string) $unassignedPayment->transaction_id,
            'amount' => (float) $unassignedPayment->amount,
            'account_number' => (string) ($unassignedPayment->account_number ?? ''),
            'reference' => '',
            'phone' => (string) ($unassignedPayment->phone ?? ''),
            'transaction_date' => $unassignedPayment->created_at ?? now(),
            'raw_payload' => [
                'auto_rematch' => true,
                'unassigned_payment_id' => (int) $unassignedPayment->id,
                'reason' => (string) ($unassignedPayment->reason ?? ''),
            ],
        ];

        $match = $matcher->match($tx, $this->paymentMatchAgentUserId());
        $tenantId = (int) ($match['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return back()->withErrors([
                'payments' => 'Auto re-match did not find a tenant yet. Use Assign and choose tenant manually.',
            ]);
        }

        $method = (string) ($unassignedPayment->payment_method ?: 'equity');
        $isSms = $method === 'sms_forwarder';
        $options = [
            'payment_method' => $method,
            'channel' => $isSms ? 'mpesa_sms_ingest' : 'equity_paybill',
            'source' => $isSms ? 'sms_ingest' : 'equity_api',
            'provider' => $isSms ? 'mpesa' : 'equity',
            'message' => 'Automatically re-matched from unmatched queue.',
        ];

        Payment::query()
            ->where('transaction_id', (string) $unassignedPayment->transaction_id)
            ->where('status', 'unmatched')
            ->delete();

        $payment = $payments->storeMatched($tx, $tenantId, (string) ($match['matched_by'] ?? 'auto_rematch'), $options);

        if ($isSms) {
            PmSmsIngest::query()
                ->where('provider_txn_code', (string) $unassignedPayment->transaction_id)
                ->whereNull('pm_payment_id')
                ->update([
                    'matched_tenant_id' => $tenantId,
                    'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0) ?: null,
                    'match_status' => 'matched',
                    'match_note' => 'Auto re-matched by updated matching logic.',
                ]);
        }

        $auditLogs->decision('success', [
            'stage' => 'auto_rematch',
            'decision' => 'matched',
            'unassigned_payment_id' => (int) $unassignedPayment->id,
            'transaction_id' => (string) $unassignedPayment->transaction_id,
            'tenant_id' => $tenantId,
            'matched_by' => (string) ($match['matched_by'] ?? 'auto_rematch'),
            'payment_id' => (int) $payment->id,
            'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0),
        ], 'auto_rematch_decision');

        $unassignedPayment->delete();

        return redirect()
            ->route('property.equity.matched')
            ->with('success', 'Payment re-matched and posted successfully.');
    }

    public function rematchAllUnmatchedPayments(
        Request $request,
        EquityPaymentRepository $payments,
        PaymentMatchingService $matcher,
        PaymentAuditLogRepository $auditLogs
    ): RedirectResponse {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            return back()->withErrors(['payments' => 'Payments module is not ready. Run migrations first.']);
        }

        $hasPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $rows = $this->buildUnmatchedQuery($request, $hasPaymentMethod)
            ->orderBy('id')
            ->limit(1000)
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors(['payments' => 'No unmatched payments found for the current filters.']);
        }

        $matched = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $unassignedPayment) {
            try {
                $tx = [
                    'transaction_id' => (string) $unassignedPayment->transaction_id,
                    'amount' => (float) $unassignedPayment->amount,
                    'account_number' => (string) ($unassignedPayment->account_number ?? ''),
                    'reference' => '',
                    'phone' => (string) ($unassignedPayment->phone ?? ''),
                    'transaction_date' => $unassignedPayment->created_at ?? now(),
                    'raw_payload' => [
                        'auto_rematch' => true,
                        'bulk_rematch' => true,
                        'unassigned_payment_id' => (int) $unassignedPayment->id,
                        'reason' => (string) ($unassignedPayment->reason ?? ''),
                    ],
                ];

                $match = $matcher->match($tx, $this->paymentMatchAgentUserId());
                $tenantId = (int) ($match['tenant_id'] ?? 0);
                if ($tenantId <= 0) {
                    $skipped++;
                    continue;
                }

                $method = (string) ($unassignedPayment->payment_method ?: 'equity');
                $isSms = $method === 'sms_forwarder';
                $options = [
                    'payment_method' => $method,
                    'channel' => $isSms ? 'mpesa_sms_ingest' : 'equity_paybill',
                    'source' => $isSms ? 'sms_ingest' : 'equity_api',
                    'provider' => $isSms ? 'mpesa' : 'equity',
                    'message' => 'Automatically re-matched from unmatched queue (bulk).',
                ];

                Payment::query()
                    ->where('transaction_id', (string) $unassignedPayment->transaction_id)
                    ->where('status', 'unmatched')
                    ->delete();

                $payment = $payments->storeMatched($tx, $tenantId, (string) ($match['matched_by'] ?? 'auto_rematch'), $options);

                if ($isSms) {
                    PmSmsIngest::query()
                        ->where('provider_txn_code', (string) $unassignedPayment->transaction_id)
                        ->whereNull('pm_payment_id')
                        ->update([
                            'matched_tenant_id' => $tenantId,
                            'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0) ?: null,
                            'match_status' => 'matched',
                            'match_note' => 'Auto re-matched by bulk matching action.',
                        ]);
                }

                $auditLogs->decision('success', [
                    'stage' => 'auto_rematch_bulk',
                    'decision' => 'matched',
                    'unassigned_payment_id' => (int) $unassignedPayment->id,
                    'transaction_id' => (string) $unassignedPayment->transaction_id,
                    'tenant_id' => $tenantId,
                    'matched_by' => (string) ($match['matched_by'] ?? 'auto_rematch'),
                    'payment_id' => (int) $payment->id,
                    'pm_payment_id' => (int) ($payment->pm_payment_id ?? 0),
                ], 'auto_rematch_bulk_decision');

                $unassignedPayment->delete();
                $matched++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $summary = "Matched: {$matched}, Skipped: {$skipped}, Failed: {$failed}.";

        if ($matched === 0 && $skipped === 0 && $failed === 0) {
            return back()->with('info', 'No payments were processed.');
        }

        $flash = [
            'title' => 'Bulk auto re-match complete',
            'text' => $summary,
            'confirmButtonColor' => '#2563eb',
        ];

        if ($failed > 0) {
            $flash['icon'] = 'warning';
            $flash['title'] = 'Bulk auto re-match finished with errors';
        } elseif ($matched > 0) {
            $flash['icon'] = 'success';
        } else {
            $flash['icon'] = 'info';
            $flash['title'] = 'No payments could be auto-matched';
        }

        return back()->with('swal_flash', $flash);
    }

    public function allPayments(Request $request): View|StreamedResponse
    {
        if (! Schema::hasTable('payments')) {
            return $this->notReadyView('Payments table is missing. Run migrations first.');
        }
        $qText = trim((string) $request->query('q', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));
        $sort = strtolower(trim((string) $request->query('sort', 'transaction_date')));
        $dir = strtolower(trim((string) $request->query('dir', 'desc')));

        $sourceStatsBase = Payment::query();
        if ($request->filled('from')) {
            $sourceStatsBase->whereDate('transaction_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $sourceStatsBase->whereDate('transaction_date', '<=', (string) $request->query('to'));
        }
        if ($qText !== '') {
            $sourceStatsBase->where(function (Builder $inner) use ($qText) {
                $inner->where('transaction_id', 'like', '%'.$qText.'%')
                    ->orWhere('account_number', 'like', '%'.$qText.'%')
                    ->orWhere('phone', 'like', '%'.$qText.'%')
                    ->orWhere('reference', 'like', '%'.$qText.'%');
            });
        }

        $sourceStats = (clone $sourceStatsBase)
            ->selectRaw('payment_method, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $equityCount = (int) data_get($sourceStats, 'equity.c', 0);
        $equityAmount = (float) data_get($sourceStats, 'equity.total', 0);
        $smsCount = (int) data_get($sourceStats, 'sms_forwarder.c', 0);
        $smsAmount = (float) data_get($sourceStats, 'sms_forwarder.total', 0);
        $manualCount = (int) data_get($sourceStats, 'manual.c', 0);
        $manualAmount = (float) data_get($sourceStats, 'manual.total', 0);
        $allCount = $equityCount + $smsCount + $manualCount;
        $allAmount = $equityAmount + $smsAmount + $manualAmount;

        $percent = static fn (float $amount) => $allAmount > 0 ? round(($amount / $allAmount) * 100, 1) : 0.0;

        // Agent scoping is enforced by the global scope on the Payment model
        // (App\Models\Payment::booted), which combines:
        //   * payments.agent_user_id = me  (new column, written by the
        //     forwarder token + EquityPaymentRepository), AND/OR
        //   * payments whose tenant belongs to me (legacy fallback for rows
        //     created before the agent_user_id column existed).
        // Super admins skip the global scope and see everything.
        $query = Payment::query()->with(['tenant', 'pmPayment.tenant']);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('source')) {
            $query->where('payment_method', (string) $request->query('source'));
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (int) $request->query('tenant_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('transaction_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('transaction_date', '<=', (string) $request->query('to'));
        }
        if ($qText !== '') {
            $query->where(function (Builder $inner) use ($qText) {
                $inner->where('transaction_id', 'like', '%'.$qText.'%')
                    ->orWhere('account_number', 'like', '%'.$qText.'%')
                    ->orWhere('phone', 'like', '%'.$qText.'%')
                    ->orWhere('reference', 'like', '%'.$qText.'%')
                    ->orWhereHas('tenant', fn (Builder $tq) => $tq->where('name', 'like', '%'.$qText.'%'));
            });
        }
        $sortMap = [
            'transaction_date' => 'transaction_date',
            'amount' => 'amount',
            'status' => 'status',
            'transaction_id' => 'transaction_id',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$sort] ?? 'transaction_date';
        $sortDir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->limit(10000)->get();

            return TabularExport::stream(
                'equity-payments-'.now()->format('Ymd_His'),
                ['Date', 'Transaction', 'Source', 'Tenant', 'Amount', 'Account', 'Phone', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $item) {
                        $source = match ((string) $item->payment_method) {
                            'equity' => 'Equity API',
                            'sms_forwarder' => 'SMS Ingest',
                            default => 'Manual / Legacy',
                        };
                        yield [
                            optional($item->transaction_date)->format('Y-m-d H:i:s'),
                            (string) $item->transaction_id,
                            $source,
                            (string) ($item->tenant?->name ?? ''),
                            number_format((float) $item->amount, 2, '.', ''),
                            (string) ($item->account_number ?? ''),
                            (string) ($item->phone ?? ''),
                            ucfirst((string) $item->status),
                        ];
                    }
                },
                $export
            );
        }

        $trendFrom = now()->startOfDay()->subDays(6);
        $trendRaw = Payment::query()
            ->selectRaw('DATE(transaction_date) as d, payment_method, COALESCE(SUM(amount),0) as total')
            ->whereDate('transaction_date', '>=', $trendFrom->toDateString())
            ->groupByRaw('DATE(transaction_date), payment_method')
            ->orderByRaw('DATE(transaction_date) asc')
            ->get();

        $trendByDay = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $trendFrom->copy()->addDays($i)->toDateString();
            $trendByDay[$day] = [
                'date' => $day,
                'equity' => 0.0,
                'sms_forwarder' => 0.0,
                'manual' => 0.0,
                'total' => 0.0,
            ];
        }

        foreach ($trendRaw as $row) {
            $day = (string) ($row->d ?? '');
            $method = (string) ($row->payment_method ?? '');
            if (! isset($trendByDay[$day])) {
                continue;
            }
            $amount = (float) ($row->total ?? 0);
            if (in_array($method, ['equity', 'sms_forwarder', 'manual'], true)) {
                $trendByDay[$day][$method] += $amount;
            } else {
                $trendByDay[$day]['manual'] += $amount;
            }
            $trendByDay[$day]['total'] += $amount;
        }

        return property_view('property.agent.equity.all_payments', [
            'items' => tap($query->paginate($perPage)->withQueryString(), function ($paginator) {
                $paginator->setCollection(
                    $paginator->getCollection()->map(function (Payment $item) {
                        $resolvedTenant = $item->tenant ?? $item->pmPayment?->tenant;
                        $resolvedTenantRow = null;

                        $tenantWorkspaceFilter = static function ($q) {
                            if (\App\Models\Concerns\AgentWorkspaceScope::shouldApply()) {
                                $q->where('agent_user_id', (int) auth()->id());
                            }
                        };

                        if (! $resolvedTenant && (int) ($item->tenant_id ?? 0) > 0) {
                            $resolvedTenantRow = DB::table('pm_tenants')
                                ->where('id', (int) $item->tenant_id)
                                ->tap($tenantWorkspaceFilter)
                                ->select(['id', 'name', 'account_number'])
                                ->first();
                        }

                        if (! $resolvedTenant && ! $resolvedTenantRow && strtolower((string) $item->status) === 'matched') {
                            $phone = preg_replace('/\D+/', '', (string) ($item->phone ?? '')) ?? '';
                            $account = strtoupper(trim((string) ($item->account_number ?? '')));

                            $fallback = DB::table('pm_tenants')
                                ->when($phone !== '', function ($q) use ($phone) {
                                    $q->whereRaw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ?', [$phone]);
                                    if (str_starts_with($phone, '254') && strlen($phone) >= 12) {
                                        $q->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ?', ['0'.substr($phone, 3)]);
                                    } elseif (str_starts_with($phone, '0') && strlen($phone) >= 10) {
                                        $q->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ?', ['254'.substr($phone, 1)]);
                                    }
                                })
                                ->when($account !== '', fn ($q) => $q->orWhereRaw('UPPER(REPLACE(account_number, " ", "")) = ?', [str_replace(' ', '', $account)]))
                                ->tap($tenantWorkspaceFilter)
                                ->orderBy('id')
                                ->select(['id', 'name', 'account_number'])
                                ->first();

                            if ($fallback) {
                                $resolvedTenantRow = $fallback;
                            }
                        }

                        $item->setAttribute('resolved_tenant_name', $resolvedTenantRow->name ?? $resolvedTenant?->name);
                        $item->setAttribute('resolved_tenant_account', $resolvedTenantRow->account_number ?? $resolvedTenant?->account_number);

                        return $item;
                    })
                );
            }),
            'tenants' => PmTenant::query()->orderBy('name')->get(['id', 'name']),
            'sourceStats' => [
                'equity' => [
                    'count' => $equityCount,
                    'amount' => $equityAmount,
                    'percent' => $percent($equityAmount),
                ],
                'sms_forwarder' => [
                    'count' => $smsCount,
                    'amount' => $smsAmount,
                    'percent' => $percent($smsAmount),
                ],
                'manual' => [
                    'count' => $manualCount,
                    'amount' => $manualAmount,
                    'percent' => $percent($manualAmount),
                ],
                'all' => [
                    'count' => $allCount,
                    'amount' => $allAmount,
                ],
            ],
            'sourceTrend' => array_values($trendByDay),
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'source' => (string) $request->query('source', ''),
                'tenant_id' => (string) $request->query('tenant_id', ''),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'q' => $qText,
                'per_page' => (string) $perPage,
                'sort' => $sortBy,
                'dir' => $sortDir,
            ],
        ]);
    }

    public function matchedPayments(Request $request): View
    {
        // Dedicated page: defaults to `status=matched` while still allowing further filtering.
        $request->query->set('status', $request->query->get('status', 'matched') ?: 'matched');

        return $this->allPayments($request);
    }

    private function notReadyView(string $reason): View
    {
        return property_view('property.agent.equity.not_ready', [
            'reason' => $reason,
        ]);
    }

    private function buildUnmatchedQuery(Request $request, bool $hasPaymentMethod): Builder
    {
        // Agent scoping is enforced by App\Models\UnassignedPayment::booted
        // (preferring agent_user_id; falling back to phone/account match for
        // legacy rows). Super admins see everything.
        $query = UnassignedPayment::query()->latest('created_at');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('transaction_id', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('account_number', 'like', '%'.$q.'%')
                    ->orWhere('reason', 'like', '%'.$q.'%');
            });
        }
        if ($request->filled('name')) {
            $like = '%'.trim((string) $request->query('name')).'%';
            $hasSms = Schema::hasTable('pm_sms_ingests');
            $hasPayments = Schema::hasTable('payments');
            if ($hasSms || $hasPayments) {
                $query->where(function (Builder $outer) use ($like, $hasSms, $hasPayments) {
                    if ($hasSms) {
                        $outer->whereExists(function ($sub) use ($like) {
                            $sub->selectRaw('1')
                                ->from('pm_sms_ingests as sms')
                                ->whereColumn('sms.provider_txn_code', 'unassigned_payments.transaction_id')
                                ->where('sms.raw_message', 'like', $like);
                        });
                    }
                    if ($hasPayments) {
                        $exists = $hasSms ? 'orWhereExists' : 'whereExists';
                        $outer->{$exists}(function ($sub) use ($like) {
                            $sub->selectRaw('1')
                                ->from('payments as p')
                                ->whereColumn('p.transaction_id', 'unassigned_payments.transaction_id')
                                ->where('p.raw_payload', 'like', $like);
                        });
                    }
                });
            }
        }
        if ($hasPaymentMethod && $request->filled('source')) {
            $source = strtolower((string) $request->query('source'));
            if (in_array($source, ['equity', 'sms_forwarder'], true)) {
                $query->where('payment_method', $source);
            }
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UnassignedPayment>|\Illuminate\Database\Eloquent\Collection<int, UnassignedPayment>  $items
     */
    private function enrichUnmatchedItems($items, bool $hasPaymentMethod): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $messageMaps = $this->loadUnmatchedMessageMaps($items->pluck('transaction_id')->all());
        $items->each(function (UnassignedPayment $item) use ($hasPaymentMethod, $messageMaps) {
            $this->enrichUnmatchedItem(
                $item,
                $hasPaymentMethod,
                $messageMaps['ingests'],
                $messageMaps['payment_messages']
            );
        });
    }

    /**
     * @param  array<int, mixed>  $transactionIds
     * @return array{ingests: array<string, PmSmsIngest>, payment_messages: array<string, string>}
     */
    private function loadUnmatchedMessageMaps(array $transactionIds): array
    {
        $txnIds = collect($transactionIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'ingests' => $this->loadSmsIngestMap($txnIds),
            'payment_messages' => $this->loadPaymentRawMessageMap($txnIds),
        ];
    }

    /**
     * @param  array<string, PmSmsIngest>  $ingestMap
     * @param  array<string, string>  $paymentMessageMap
     */
    private function enrichUnmatchedItem(
        UnassignedPayment $item,
        bool $hasPaymentMethod,
        ?array $ingestMap = null,
        ?array $paymentMessageMap = null
    ): void {
        $txn = (string) ($item->transaction_id ?? '');
        if ($ingestMap === null || $paymentMessageMap === null) {
            $ingestMap = $this->loadSmsIngestMap([$txn]);
            $paymentMessageMap = $this->loadPaymentRawMessageMap([$txn]);
        }

        $ingest = $ingestMap[$txn] ?? null;
        $message = (string) ($ingest?->raw_message ?? '');
        if ($message === '') {
            $message = (string) ($paymentMessageMap[$txn] ?? '');
        }

        $item->display_message = $message;
        $item->payer_name = $this->resolvePayerName($message, $ingest?->payload);
        $item->source_label = $this->resolveSourceLabel(
            $txn,
            $hasPaymentMethod ? (string) ($item->payment_method ?? '') : '',
            $ingest
        );
    }

    /**
     * @param  array<int, string>  $transactionIds
     * @return array<string, PmSmsIngest>
     */
    private function loadSmsIngestMap(array $transactionIds): array
    {
        if ($transactionIds === [] || ! Schema::hasTable('pm_sms_ingests')) {
            return [];
        }

        return PmSmsIngest::query()
            ->whereIn('provider_txn_code', $transactionIds)
            ->get(['provider_txn_code', 'provider', 'raw_message', 'payload'])
            ->keyBy(fn (PmSmsIngest $ingest) => (string) $ingest->provider_txn_code)
            ->all();
    }

    /**
     * @param  array<int, string>  $transactionIds
     * @return array<string, string>
     */
    private function loadPaymentRawMessageMap(array $transactionIds): array
    {
        if ($transactionIds === [] || ! Schema::hasTable('payments')) {
            return [];
        }

        $map = [];
        Payment::query()
            ->whereIn('transaction_id', $transactionIds)
            ->get(['transaction_id', 'raw_payload'])
            ->each(function (Payment $payment) use (&$map) {
                $txn = (string) ($payment->transaction_id ?? '');
                if ($txn === '' || isset($map[$txn])) {
                    return;
                }
                $payload = is_array($payment->raw_payload) ? $payment->raw_payload : [];
                $message = (string) ($payload['raw_message'] ?? '');
                if ($message === '' && isset($payload['message'])) {
                    $message = (string) $payload['message'];
                }
                if ($message !== '') {
                    $map[$txn] = $message;
                }
            });

        return $map;
    }

    private function resolveSourceLabel(string $transactionId, string $paymentMethod, ?PmSmsIngest $ingest): string
    {
        if ($ingest !== null) {
            $provider = strtolower((string) ($ingest->provider ?? ''));
            if ($provider !== '') {
                return 'SMS Ingest ('.strtoupper($provider).')';
            }

            return 'SMS Ingest';
        }
        if ($paymentMethod === 'sms_forwarder') {
            return 'SMS Ingest';
        }

        return 'Equity';
    }

    private function resolvePayerName(string $message, mixed $payload): string
    {
        $parsed = MpesaSmsForwarderParser::extractMpesaFields($message);
        $name = trim((string) ($parsed['sender_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $normalized = MpesaSmsForwarderParser::normalizePayload($payload);
        if (is_array($normalized)) {
            foreach (['sender_name', 'payer_name', 'name', 'customer_name'] as $key) {
                $candidate = trim((string) ($normalized[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function paymentMatchAgentUserId(): ?int
    {
        $u = auth()->user();
        if (! $u) {
            return null;
        }
        if (($u->property_portal_role ?? null) === 'agent') {
            return (int) $u->id;
        }

        return null;
    }

    /**
     * Aborts with 404 when the active agent user does not own (cannot claim)
     * this unassigned payment. Super admins are unaffected. Used as a defence
     * against URL tampering on row-bound endpoints.
     */
    private function authorizeUnassignedPaymentAccess(Request $request, UnassignedPayment $unassignedPayment): void
    {
        $user = $request->user();
        if (! $user || ($user->is_super_admin ?? false) === true) {
            return;
        }
        if ((string) ($user->property_portal_role ?? '') !== 'agent') {
            return;
        }

        // Must mirror App\Models\UnassignedPayment global scope (agent_user_id
        // attribution OR legacy phone/account tenant match).
        if (Schema::hasColumn('unassigned_payments', 'agent_user_id')) {
            $attributedAgentId = (int) ($unassignedPayment->agent_user_id ?? 0);
            if ($attributedAgentId > 0 && $attributedAgentId === (int) $user->id) {
                return;
            }
        }

        if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            abort(404);
        }

        $phone = (string) ($unassignedPayment->phone ?? '');
        $account = (string) ($unassignedPayment->account_number ?? '');
        if ($phone === '' && $account === '') {
            abort(404);
        }

        $owns = PmTenant::query()
            ->where('agent_user_id', $user->id)
            ->where(function ($q) use ($phone, $account) {
                if ($phone !== '') {
                    $q->where('phone', $phone);
                }
                if ($account !== '') {
                    $q->orWhere('account_number', $account);
                }
            })
            ->exists();

        if (! $owns) {
            abort(404);
        }
    }

    /**
     * Ensures a tenant id submitted via form input belongs to the active
     * agent's workspace. Super admins are unaffected.
     */
    private function authorizeTenantAccess(Request $request, int $tenantId): void
    {
        $user = $request->user();
        if (! $user || ($user->is_super_admin ?? false) === true) {
            return;
        }
        if ((string) ($user->property_portal_role ?? '') !== 'agent') {
            return;
        }
        if ($tenantId <= 0) {
            abort(422, 'Invalid tenant.');
        }

        $exists = PmTenant::query()->where('id', $tenantId)->where('agent_user_id', $user->id)->exists();
        if (! $exists) {
            abort(403, 'Tenant does not belong to your workspace.');
        }
    }
}

