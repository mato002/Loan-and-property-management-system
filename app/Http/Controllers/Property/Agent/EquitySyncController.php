<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\EquitySyncRun;
use App\Models\Payment;
use App\Models\PmTenant;
use App\Models\PmSmsIngest;
use App\Models\UnassignedPayment;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquitySyncController extends Controller
{
    public function syncStatus(): View
    {
        if (! Schema::hasTable('equity_sync_runs')) {
            return $this->notReadyView('Equity sync runs table is missing. Run migrations first.');
        }

        $runs = EquitySyncRun::query()->latest('id')->paginate(20);

        return view('property.agent.equity.sync_status', [
            'runs' => $runs,
            'latest' => $runs->first(),
        ]);
    }

    public function triggerSync(): RedirectResponse
    {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('equity_sync_runs')) {
            return back()->withErrors(['equity' => 'Equity module is not ready. Run migrations and retry.']);
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

        return view('property.agent.equity.unmatched_payments', [
            'items' => $query->paginate($perPage)->withQueryString(),
            'hasPaymentMethod' => $hasPaymentMethod,
            'filters' => [
                'q' => (string) $request->query('q', ''),
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
        $format = strtolower((string) $request->query('format', 'csv'));
        $isXls = $format === 'xls';
        $sep = $isXls ? "\t" : ",";
        $stamp = now()->format('Ymd_His');
        $filename = 'unmatched-payments-'.$stamp.($isXls ? '.xls' : '.csv');
        $contentType = $isXls
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($query, $sep, $hasPaymentMethod) {
            $out = fopen('php://output', 'w');
            if (! $out) {
                return;
            }
            if ($sep === ',') {
                fputcsv($out, ['Date', 'Transaction', 'Amount', 'Account', 'Phone', 'Source', 'Reason']);
            } else {
                fwrite($out, implode($sep, ['Date', 'Transaction', 'Amount', 'Account', 'Phone', 'Source', 'Reason'])."\n");
            }

            foreach ($query->cursor() as $item) {
                $row = [
                    optional($item->created_at)->format('Y-m-d H:i:s'),
                    (string) $item->transaction_id,
                    number_format((float) $item->amount, 2, '.', ''),
                    (string) ($item->account_number ?? ''),
                    (string) ($item->phone ?? ''),
                    $hasPaymentMethod && (string) ($item->payment_method ?? '') === 'sms_forwarder' ? 'SMS Forwarder' : 'Equity',
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

        return response()->view('property.agent.equity.unmatched_payments_print', [
            'items' => $items,
            'hasPaymentMethod' => $hasPaymentMethod,
        ]);
    }

    public function showUnmatchedPayment(UnassignedPayment $unassignedPayment): View
    {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            return $this->notReadyView('Payments module is not ready. Run migrations first.');
        }

        return view('property.agent.equity.unmatched_assign', [
            'item' => $unassignedPayment,
            'tenants' => PmTenant::query()->orderBy('name')->get(['id', 'name', 'phone', 'account_number']),
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

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:pm_tenants,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

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

    public function allPayments(Request $request): View
    {
        if (! Schema::hasTable('payments')) {
            return $this->notReadyView('Payments table is missing. Run migrations first.');
        }

        $sourceStatsBase = Payment::query();
        if ($request->filled('from')) {
            $sourceStatsBase->whereDate('transaction_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $sourceStatsBase->whereDate('transaction_date', '<=', (string) $request->query('to'));
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

        $query = Payment::query()->with('tenant')->latest('transaction_date')->latest('id');

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

        return view('property.agent.equity.all_payments', [
            'items' => $query->paginate(30)->withQueryString(),
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
            ],
        ]);
    }

    private function notReadyView(string $reason): View
    {
        return view('property.agent.equity.not_ready', [
            'reason' => $reason,
        ]);
    }

    private function buildUnmatchedQuery(Request $request, bool $hasPaymentMethod): Builder
    {
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
        if ($hasPaymentMethod && $request->filled('source')) {
            $source = strtolower((string) $request->query('source'));
            if (in_array($source, ['equity', 'sms_forwarder'], true)) {
                $query->where('payment_method', $source);
            }
        }

        return $query;
    }
}

