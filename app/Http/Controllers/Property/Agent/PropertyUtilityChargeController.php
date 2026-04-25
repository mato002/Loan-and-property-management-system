<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPenaltyRule;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use App\Models\PropertyUnit;
use App\Support\TabularExport;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyUtilityChargeController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'charge_type' => strtolower(trim((string) $request->query('charge_type', ''))),
            'month' => trim((string) $request->query('month', '')),
            'sort' => strtolower(trim((string) $request->query('sort', 'id'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmUnitUtilityCharge::query()
            ->with(['unit.property'])
            ->whereNotNull('id');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('label', 'like', '%'.$q.'%')
                    ->orWhere('notes', 'like', '%'.$q.'%')
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }
        if ($filters['charge_type'] !== '' && in_array($filters['charge_type'], ['water', 'service', 'garbage', 'other'], true)) {
            $query->where('charge_type', $filters['charge_type']);
        }
        if ($filters['month'] !== '' && preg_match('/^\d{4}\-\d{2}$/', $filters['month']) === 1) {
            $query->where('billing_month', $filters['month']);
        }
        $sortMap = ['id' => 'id', 'amount' => 'amount', 'created_at' => 'created_at', 'label' => 'label', 'billing_month' => 'billing_month'];
        $sortBy = $sortMap[$filters['sort']] ?? 'id';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'desc';
        $query->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'utility-charges-'.now()->format('Ymd_His'),
                ['Label', 'Unit', 'Type', 'Billing month', 'Added', 'Amount', 'Notes'],
                function () use ($rows) {
                    foreach ($rows as $c) {
                        yield [
                            (string) $c->label,
                            (string) (($c->unit->property->name ?? '').' / '.($c->unit->label ?? '')),
                            (string) ($c->charge_type ?? ''),
                            (string) ($c->billing_month ?? ''),
                            $c->created_at?->format('Y-m-d') ?? '',
                            (string) PropertyMoney::kes((float) $c->amount),
                            (string) ($c->notes ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $charges = (clone $query)->paginate($perPage)->withQueryString();
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
            ['label' => 'Charge lines', 'value' => (string) $charges->total(), 'hint' => 'Filtered total'],
            ['label' => 'New (MTD)', 'value' => PropertyMoney::kes((float) $mtd), 'hint' => 'Sum of amounts'],
            ['label' => 'Distinct units', 'value' => (string) $charges->getCollection()->unique('property_unit_id')->count(), 'hint' => 'Current page'],
            ['label' => 'Water readings', 'value' => (string) $waterReadings->count(), 'hint' => 'Recent'],
        ];
        $waterChargePropertyIds = DB::table('pm_unit_utility_charges as charges')
            ->join('property_units as units', 'units.id', '=', 'charges.property_unit_id')
            ->where('charges.charge_type', 'water')
            ->distinct()
            ->pluck('units.property_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return view('property.agent.revenue.utilities', [
            'stats' => $stats,
            'charges' => $charges,
            'waterReadings' => $waterReadings,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
            'waterChargePropertyIds' => $waterChargePropertyIds,
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

    public function storeBulkWaterReadings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'current_readings' => ['required', 'array'],
            'current_readings.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $propertyId = (int) $data['property_id'];
        $month = (string) $data['billing_month'];
        $rate = (float) $data['rate_per_unit'];
        $fixed = (float) ($data['fixed_charge'] ?? 0);
        $notes = $data['notes'] ?? null;

        $unitMap = PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->pluck('label', 'id')
            ->mapWithKeys(fn ($label, $id) => [(int) $id => (string) $label]);

        if ($unitMap->isEmpty()) {
            return back()->withErrors(['property_id' => 'No units found for the selected property.'])->withInput();
        }

        $submittedReadings = collect($data['current_readings'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->mapWithKeys(fn ($value, $unitId) => [(int) $unitId => (float) $value]);

        if ($submittedReadings->isEmpty()) {
            return back()->withErrors(['current_readings' => 'Enter at least one current reading to save in bulk.'])->withInput();
        }

        $invalidUnitIds = $submittedReadings
            ->keys()
            ->filter(fn ($unitId) => ! $unitMap->has((int) $unitId))
            ->values();
        if ($invalidUnitIds->isNotEmpty()) {
            return back()->withErrors(['current_readings' => 'Some submitted units do not belong to the selected property.'])->withInput();
        }

        $errors = [];
        $saved = 0;

        DB::transaction(function () use ($submittedReadings, $month, $rate, $fixed, $notes, $unitMap, &$errors, &$saved) {
            foreach ($submittedReadings as $unitId => $current) {
                $prev = (float) (PmWaterReading::query()
                    ->where('property_unit_id', (int) $unitId)
                    ->where('billing_month', '<', $month)
                    ->orderByDesc('billing_month')
                    ->value('current_reading') ?? 0);

                if ($current < $prev) {
                    $errors['current_readings.'.$unitId] = ($unitMap[(int) $unitId] ?? ('Unit '.$unitId)).': current reading cannot be less than previous reading ('.$prev.').';
                    continue;
                }

                $unitsUsed = $current - $prev;
                $amount = ($unitsUsed * $rate) + $fixed;

                PmWaterReading::query()->updateOrCreate(
                    ['property_unit_id' => (int) $unitId, 'billing_month' => $month],
                    [
                        'previous_reading' => $prev,
                        'current_reading' => $current,
                        'units_used' => $unitsUsed,
                        'rate_per_unit' => $rate,
                        'fixed_charge' => $fixed,
                        'amount' => $amount,
                        'status' => 'recorded',
                        'notes' => $notes,
                    ]
                );
                $saved++;
            }
        });

        if (! empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        return back()->with('success', $saved.' water meter reading(s) saved in bulk.');
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

                $invoiceNo = PmInvoice::nextInvoiceNumber();
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
