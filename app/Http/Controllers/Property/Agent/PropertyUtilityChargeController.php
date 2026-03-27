<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPenaltyRule;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyUtilityChargeController extends Controller
{
    public function index(): View
    {
        $charges = PmUnitUtilityCharge::query()
            ->with(['unit.property'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $waterReadings = PmWaterReading::query()
            ->with(['unit.property', 'invoice'])
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        $mtd = PmUnitUtilityCharge::query()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $stats = [
            ['label' => 'Charge lines', 'value' => (string) $charges->count(), 'hint' => 'Listed'],
            ['label' => 'New (MTD)', 'value' => PropertyMoney::kes((float) $mtd), 'hint' => 'Sum of amounts'],
            ['label' => 'Distinct units', 'value' => (string) $charges->unique('property_unit_id')->count(), 'hint' => ''],
            ['label' => 'Water readings', 'value' => (string) $waterReadings->count(), 'hint' => 'Recent'],
        ];

        return view('property.agent.revenue.utilities', [
            'stats' => $stats,
            'charges' => $charges,
            'waterReadings' => $waterReadings,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'charge_type' => ['nullable', 'string', 'in:water,service,garbage,other'],
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'label' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['charge_type'] = $data['charge_type'] ?? 'other';
        PmUnitUtilityCharge::query()->create($data);

        return back()->with('success', __('Utility charge saved.'));
    }

    public function storeWaterReading(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $unitId = (int) $data['property_unit_id'];
        $month = (string) $data['billing_month'];
        $current = (float) $data['current_reading'];
        $rate = (float) $data['rate_per_unit'];
        $fixed = (float) ($data['fixed_charge'] ?? 0);

        $prev = (float) (PmWaterReading::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', '<', $month)
            ->orderByDesc('billing_month')
            ->value('current_reading') ?? 0);

        if ($current < $prev) {
            return back()->withErrors(['current_reading' => 'Current reading cannot be less than previous reading ('.$prev.').'])->withInput();
        }

        $unitsUsed = $current - $prev;
        $amount = ($unitsUsed * $rate) + $fixed;

        PmWaterReading::query()->updateOrCreate(
            ['property_unit_id' => $unitId, 'billing_month' => $month],
            [
                'previous_reading' => $prev,
                'current_reading' => $current,
                'units_used' => $unitsUsed,
                'rate_per_unit' => $rate,
                'fixed_charge' => $fixed,
                'amount' => $amount,
                'status' => 'recorded',
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Water meter reading saved.');
    }

    public function generateWaterInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
        ]);

        $billingMonth = (string) $data['billing_month'];
        $dueDate = (string) $data['due_date'];

        $readings = PmWaterReading::query()
            ->with('unit')
            ->where('billing_month', $billingMonth)
            ->whereNull('pm_invoice_id')
            ->get();

        if ($readings->isEmpty()) {
            return back()->withErrors(['billing_month' => 'No uninvoiced water readings for '.$billingMonth.'.']);
        }

        $created = 0;
        DB::transaction(function () use ($readings, $billingMonth, $dueDate, &$created) {
            foreach ($readings as $reading) {
                $lease = PmLease::query()
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->whereHas('units', fn ($q) => $q->where('property_units.id', $reading->property_unit_id))
                    ->with('pmTenant')
                    ->first();

                if (! $lease || ! $lease->pmTenant) {
                    continue;
                }

                $next = (int) (PmInvoice::query()->max('id') ?? 0) + 1 + $created;
                $invoiceNo = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
                $amount = (float) $reading->amount;

                $invoice = PmInvoice::query()->create([
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => $reading->property_unit_id,
                    'pm_tenant_id' => $lease->pm_tenant_id,
                    'invoice_no' => $invoiceNo,
                    'issue_date' => now()->toDateString(),
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'status' => PmInvoice::STATUS_SENT,
                    'invoice_type' => PmInvoice::TYPE_WATER,
                    'billing_period' => $billingMonth,
                    'description' => 'Water bill for '.$billingMonth.' (units '.number_format((float) $reading->units_used, 3).')',
                ]);
                $invoice->refreshComputedStatus();

                $reading->update([
                    'status' => 'invoiced',
                    'pm_invoice_id' => $invoice->id,
                ]);

                $created++;
            }
        });

        return back()->with('success', $created.' water invoice(s) generated for '.$billingMonth.'.');
    }

    public function applyWaterPenalties(): RedirectResponse
    {
        $rules = PmPenaltyRule::query()
            ->where('is_active', true)
            ->where('scope', 'water')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return back()->withErrors(['penalty' => 'No active water penalty rule found. Use scope=water.']);
        }

        $rule = $rules->first();
        $graceDays = (int) ($rule->grace_days ?? 0);
        $threshold = now()->subDays($graceDays)->toDateString();

        $invoices = PmInvoice::query()
            ->where('invoice_type', PmInvoice::TYPE_WATER)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereDate('due_date', '<', $threshold)
            ->get();

        $count = 0;
        foreach ($invoices as $invoice) {
            $base = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($base <= 0) {
                continue;
            }

            $penalty = 0.0;
            if ($rule->formula === 'flat' || $rule->formula === 'fixed') {
                $penalty = (float) ($rule->amount ?? 0);
            } else {
                $penalty = $base * (((float) ($rule->percent ?? 0)) / 100);
                if ((float) ($rule->amount ?? 0) > 0) {
                    $penalty += (float) $rule->amount;
                }
            }
            if ((float) ($rule->cap ?? 0) > 0) {
                $penalty = min($penalty, (float) $rule->cap);
            }
            if ($penalty <= 0) {
                continue;
            }

            $invoice->amount = (float) $invoice->amount + $penalty;
            $invoice->description = trim(((string) $invoice->description).' | Water penalty '.$rule->name.' '.now()->format('Y-m-d'));
            $invoice->save();
            $invoice->refreshComputedStatus();
            $count++;
        }

        return back()->with('success', 'Applied water penalties to '.$count.' invoice(s).');
    }

    public function destroy(PmUnitUtilityCharge $charge): RedirectResponse
    {
        $charge->delete();

        return back()->with('success', __('Charge removed.'));
    }
}
