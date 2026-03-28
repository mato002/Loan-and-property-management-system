<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\EquitySyncRun;
use App\Models\Payment;
use App\Models\PmTenant;
use App\Models\UnassignedPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

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

        $query = UnassignedPayment::query()->latest('created_at');
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to'));
        }

        return view('property.agent.equity.unmatched_payments', [
            'items' => $query->paginate(30)->withQueryString(),
        ]);
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
}

