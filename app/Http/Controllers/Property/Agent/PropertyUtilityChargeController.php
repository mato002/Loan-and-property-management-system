<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\ExpenseDefinition;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPenaltyRule;
use App\Models\PropertyPortalSetting;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use App\Models\PropertyUnit;
use App\Support\TabularExport;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'wr_q' => trim((string) $request->query('wr_q', '')),
            'wr_month' => trim((string) $request->query('wr_month', '')),
            'wr_status' => strtolower(trim((string) $request->query('wr_status', ''))),
            'wr_property_id' => (int) $request->query('wr_property_id', 0),
            'rr_month' => trim((string) $request->query('rr_month', '')),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));
        $wrPerPage = min(200, max(10, (int) $request->query('wr_per_page', 20)));

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
        if ($filters['charge_type'] !== '') {
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
                ['Label', 'Unit', 'Type', 'Billing month', 'Usage (units/rate/fixed)', 'Added', 'Amount', 'Notes'],
                function () use ($rows) {
                    foreach ($rows as $c) {
                        $usage = (($c->units_consumed ?? null) !== null || ($c->rate_per_unit ?? null) !== null || ($c->fixed_charge ?? null) !== null)
                            ? 'U: '.number_format((float) ($c->units_consumed ?? 0), 3).' | R: '.number_format((float) ($c->rate_per_unit ?? 0), 2).' | F: '.number_format((float) ($c->fixed_charge ?? 0), 2)
                            : '';
                        yield [
                            (string) $c->label,
                            (string) (($c->unit->property->name ?? '').' / '.($c->unit->label ?? '')),
                            (string) ($c->charge_type ?? ''),
                            (string) ($c->billing_month ?? ''),
                            $usage,
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
        $waterReadingsQuery = PmWaterReading::query()
            ->with(['unit.property', 'invoice'])
            ->when($filters['wr_q'] !== '', function ($q) use ($filters): void {
                $term = $filters['wr_q'];
                $q->where(function ($inner) use ($term): void {
                    $inner->whereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$term.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$term.'%')))
                        ->orWhere('notes', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['wr_month'] !== '' && preg_match('/^\d{4}\-\d{2}$/', $filters['wr_month']) === 1, fn ($q) => $q->where('billing_month', $filters['wr_month']))
            ->when(in_array($filters['wr_status'], ['recorded', 'invoiced'], true), fn ($q) => $q->where('status', $filters['wr_status']))
            ->when($filters['wr_property_id'] > 0, fn ($q) => $q->whereHas('unit', fn ($uq) => $uq->where('property_id', $filters['wr_property_id'])))
            ->orderByDesc('billing_month')
            ->orderByDesc('id');
        $waterReadings = $waterReadingsQuery->paginate($wrPerPage, ['*'], 'wr_page')->withQueryString();
        $waterReadingUnitIdsByMonth = PmWaterReading::query()
            ->select(['billing_month', 'property_unit_id'])
            ->get()
            ->groupBy('billing_month')
            ->map(fn ($rows) => $rows->pluck('property_unit_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();

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
        $propertyChargeTemplates = $this->propertyChargeTemplates();
        $waterTemplateByUnit = [];
        $utilityTemplateByUnit = [];
        $waterTemplatePropertyIds = [];
        foreach (PropertyUnit::query()->select(['id', 'property_id'])->get() as $unit) {
            $templates = (array) ($propertyChargeTemplates[(string) $unit->property_id] ?? []);
            $effectiveByType = [];
            foreach ($templates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;
                if ($scopeUnitId !== null && $scopeUnitId !== (int) $unit->id) {
                    continue;
                }
                $type = strtolower(trim((string) ($row['charge_type'] ?? '')));
                if ($type === '') {
                    continue;
                }
                $effectiveByType[$type] = [
                    'rate_per_unit' => is_numeric($row['rate_per_unit'] ?? null) ? (float) $row['rate_per_unit'] : 0.0,
                    'fixed_charge' => is_numeric($row['fixed_charge'] ?? null) ? (float) $row['fixed_charge'] : 0.0,
                    'label' => trim((string) ($row['label'] ?? '')),
                ];
            }
            if ($effectiveByType !== []) {
                $utilityTemplateByUnit[(string) $unit->id] = $effectiveByType;
            }
            $water = $effectiveByType['water'] ?? null;
            if (! is_array($water)) {
                continue;
            }
            $waterTemplateByUnit[(string) $unit->id] = [
                'rate_per_unit' => is_numeric($water['rate_per_unit'] ?? null) ? (float) $water['rate_per_unit'] : null,
                'fixed_charge' => is_numeric($water['fixed_charge'] ?? null) ? (float) $water['fixed_charge'] : null,
                'label' => trim((string) ($water['label'] ?? '')),
            ];
            $waterTemplatePropertyIds[] = (int) $unit->property_id;
        }
        $waterChargePropertyIds = collect($waterChargePropertyIds)
            ->merge($waterTemplatePropertyIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $readinessMonth = preg_match('/^\d{4}\-\d{2}$/', $filters['rr_month']) === 1
            ? $filters['rr_month']
            : now()->format('Y-m');
        $waterEnabledUnitIds = collect(array_keys($waterTemplateByUnit))
            ->map(fn ($id) => (int) $id)
            ->merge(
                PmUnitUtilityCharge::query()
                    ->where('charge_type', 'water')
                    ->distinct()
                    ->pluck('property_unit_id')
                    ->map(fn ($id) => (int) $id)
            )
            ->merge(
                PmWaterReading::query()
                    ->distinct()
                    ->pluck('property_unit_id')
                    ->map(fn ($id) => (int) $id)
            )
            ->filter()
            ->unique()
            ->values();

        $monthReadings = PmWaterReading::query()
            ->with('unit.property')
            ->where('billing_month', $readinessMonth)
            ->whereIn('property_unit_id', $waterEnabledUnitIds)
            ->get()
            ->keyBy('property_unit_id');

        $missingWaterReadings = PropertyUnit::query()
            ->with('property')
            ->whereIn('id', $waterEnabledUnitIds)
            ->whereNotIn('id', $monthReadings->keys()->map(fn ($id) => (int) $id)->values())
            ->orderBy('property_id')
            ->orderBy('label')
            ->get()
            ->map(fn ($unit) => [
                'unit_id' => (int) $unit->id,
                'property_name' => (string) ($unit->property->name ?? '—'),
                'unit_label' => (string) ($unit->label ?? '—'),
            ])
            ->values();

        $usageAnomalies = collect();
        foreach ($monthReadings as $reading) {
            $unitsUsed = (float) ($reading->units_used ?? 0);
            $historyAvg = (float) (PmWaterReading::query()
                ->where('property_unit_id', (int) $reading->property_unit_id)
                ->where('billing_month', '<', $readinessMonth)
                ->orderByDesc('billing_month')
                ->limit(3)
                ->avg('units_used') ?? 0);

            $reason = null;
            if ($unitsUsed <= 0) {
                $reason = 'Zero usage recorded';
            } elseif ($historyAvg > 0 && $unitsUsed >= ($historyAvg * 2) && ($unitsUsed - $historyAvg) >= 5) {
                $reason = 'Usage spike vs recent average';
            }

            if ($reason === null) {
                continue;
            }

            $usageAnomalies->push([
                'unit_id' => (int) $reading->property_unit_id,
                'property_name' => (string) ($reading->unit->property->name ?? '—'),
                'unit_label' => (string) ($reading->unit->label ?? '—'),
                'units_used' => $unitsUsed,
                'avg_units_used' => $historyAvg,
                'reason' => $reason,
            ]);
        }

        return view('property.agent.revenue.utilities', [
            'stats' => $stats,
            'charges' => $charges,
            'waterReadings' => $waterReadings,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
                'wr_per_page' => (string) $wrPerPage,
            ],
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
            'wrProperties' => PropertyUnit::query()->with('property:id,name')->select(['id', 'property_id'])->get()
                ->pluck('property')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'waterChargePropertyIds' => $waterChargePropertyIds,
            'waterTemplateByUnit' => $waterTemplateByUnit,
            'utilityTemplateByUnit' => $utilityTemplateByUnit,
            'waterReadingUnitIdsByMonth' => $waterReadingUnitIdsByMonth,
            'billingReadiness' => [
                'month' => $readinessMonth,
                'missing' => $missingWaterReadings,
                'anomalies' => $usageAnomalies->values(),
                'water_enabled_units' => $waterEnabledUnitIds->count(),
                'recorded_units' => $monthReadings->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'charge_type' => ['nullable', 'string', 'max:50'],
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'label' => ['required', 'string', 'max:128'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'units_consumed' => ['nullable', 'numeric', 'min:0'],
            'rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['charge_type'] = $this->normalizeChargeType((string) ($data['charge_type'] ?? 'other'));

        $allowedTypes = $this->allowedUtilityTypesForUnit((int) $data['property_unit_id']);
        if ($allowedTypes !== [] && ! in_array($data['charge_type'], $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'charge_type' => 'Only configured utility types are allowed for this unit: '.implode(', ', $allowedTypes),
            ]);
        }

        $unitsConsumed = is_numeric($data['units_consumed'] ?? null) ? max(0, (float) $data['units_consumed']) : 0.0;
        $ratePerUnit = is_numeric($data['rate_per_unit'] ?? null) ? max(0, (float) $data['rate_per_unit']) : 0.0;
        $fixedCharge = is_numeric($data['fixed_charge'] ?? null) ? max(0, (float) $data['fixed_charge']) : 0.0;
        $providedAmount = is_numeric($data['amount'] ?? null) ? max(0, (float) $data['amount']) : 0.0;
        $calculatedAmount = ($unitsConsumed * $ratePerUnit) + $fixedCharge;
        $finalAmount = $calculatedAmount > 0 ? $calculatedAmount : $providedAmount;
        if ($finalAmount <= 0) {
            return back()->withErrors(['amount' => 'Enter amount, or provide usage/rate/fixed values that result in a positive amount.'])->withInput();
        }
        $data['units_consumed'] = $unitsConsumed > 0 ? $unitsConsumed : null;
        $data['rate_per_unit'] = $ratePerUnit > 0 ? $ratePerUnit : null;
        $data['fixed_charge'] = ($fixedCharge > 0 || $calculatedAmount > 0) ? $fixedCharge : null;
        $data['amount'] = round($finalAmount, 2);
        PmUnitUtilityCharge::query()->create($data);

        return back()->with('success', __('Utility charge saved.'));
    }

    public function generateUtilityInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
        ]);

        $billingMonth = (string) $data['billing_month'];
        $dueDate = (string) $data['due_date'];

        $charges = PmUnitUtilityCharge::query()
            ->with('unit')
            ->where('billing_month', $billingMonth)
            ->where('is_invoiced', false)
            ->whereNull('pm_invoice_id')
            ->get();

        if ($charges->isEmpty()) {
            return back()->withErrors(['billing_month' => 'No uninvoiced utility charges for '.$billingMonth.'.']);
        }

        $created = 0;
        $skippedNoLease = 0;

        DB::transaction(function () use ($charges, $billingMonth, $dueDate, &$created, &$skippedNoLease): void {
            foreach ($charges as $charge) {
                $lease = PmLease::query()
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->whereHas('units', fn ($q) => $q->where('property_units.id', $charge->property_unit_id))
                    ->first();

                if (! $lease) {
                    $skippedNoLease++;
                    continue;
                }

                $amount = (float) $charge->amount;
                if ($amount <= 0) {
                    continue;
                }

                $invoiceType = $charge->charge_type === PmInvoice::TYPE_WATER
                    ? PmInvoice::TYPE_WATER
                    : PmInvoice::TYPE_MIXED;

                $usageMeta = (($charge->units_consumed ?? null) !== null || ($charge->rate_per_unit ?? null) !== null || ($charge->fixed_charge ?? null) !== null)
                    ? ' | U: '.number_format((float) ($charge->units_consumed ?? 0), 3)
                        .' R: '.number_format((float) ($charge->rate_per_unit ?? 0), 2)
                        .' F: '.number_format((float) ($charge->fixed_charge ?? 0), 2)
                    : '';

                $invoice = PmInvoice::query()->create([
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => $charge->property_unit_id,
                    'pm_tenant_id' => $lease->pm_tenant_id,
                    'invoice_no' => PmInvoice::nextInvoiceNumber(),
                    'issue_date' => now()->toDateString(),
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'status' => PmInvoice::STATUS_SENT,
                    'invoice_type' => $invoiceType,
                    'billing_period' => $billingMonth,
                    'description' => trim((string) $charge->label).$usageMeta,
                ]);
                $invoice->refreshComputedStatus();

                $charge->update([
                    'is_invoiced' => true,
                    'pm_invoice_id' => $invoice->id,
                ]);
                $created++;
            }
        });

        $msg = $created.' utility invoice(s) generated for '.$billingMonth.'.';
        if ($skippedNoLease > 0) {
            $msg .= ' '.$skippedNoLease.' charge line(s) skipped (no active lease on unit).';
        }

        return back()->with('success', $msg);
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
        $alreadyExists = PmWaterReading::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', $month)
            ->exists();
        if ($alreadyExists) {
            return back()->withErrors([
                'billing_month' => 'A water reading already exists for this unit and month. Remove/update the existing record instead of recording a duplicate.',
            ])->withInput();
        }

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

                $alreadyExists = PmWaterReading::query()
                    ->where('property_unit_id', (int) $unitId)
                    ->where('billing_month', $month)
                    ->exists();
                if ($alreadyExists) {
                    $errors['current_readings.'.$unitId] = ($unitMap[(int) $unitId] ?? ('Unit '.$unitId)).': reading already exists for '.$month.'.';
                    continue;
                }

                PmWaterReading::query()->create([
                    'property_unit_id' => (int) $unitId,
                    'billing_month' => $month,
                    'previous_reading' => $prev,
                    'current_reading' => $current,
                    'units_used' => $unitsUsed,
                    'rate_per_unit' => $rate,
                    'fixed_charge' => $fixed,
                    'amount' => $amount,
                    'status' => 'recorded',
                    'notes' => $notes,
                ]);
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
        $skippedNoLease = 0;
        $skippedNoTenant = 0;
        DB::transaction(function () use ($readings, $billingMonth, $dueDate, &$created, &$skippedNoLease, &$skippedNoTenant): void {
            foreach ($readings as $reading) {
                $lease = PmLease::query()
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->whereHas('units', fn ($q) => $q->where('property_units.id', $reading->property_unit_id))
                    ->with('pmTenant')
                    ->first();

                if (! $lease) {
                    $skippedNoLease++;
                    continue;
                }
                if (! $lease->pmTenant) {
                    $skippedNoTenant++;
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

        if ($created === 0) {
            return back()->withErrors([
                'billing_month' => 'No water invoices generated for '.$billingMonth
                    .'. Skipped: '.$skippedNoLease.' unit(s) without active lease'
                    .', '.$skippedNoTenant.' lease(s) without tenant.',
            ]);
        }

        $msg = $created.' water invoice(s) generated for '.$billingMonth.'.';
        if ($skippedNoLease > 0 || $skippedNoTenant > 0) {
            $msg .= ' Skipped: '.$skippedNoLease.' unit(s) without active lease, '
                .$skippedNoTenant.' lease(s) without tenant.';
        }

        return back()->with('success', $msg);
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

    public function waterReadingsBulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete'],
            'reading_ids' => ['nullable', 'array'],
            'reading_ids.*' => ['integer', 'exists:pm_water_readings,id'],
        ]);

        $ids = collect($data['reading_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['reading_ids' => 'Select at least one water reading first.']);
        }

        if ($data['action'] === 'delete') {
            $deleted = PmWaterReading::query()
                ->whereIn('id', $ids)
                ->whereNull('pm_invoice_id')
                ->delete();

            return back()->with('success', $deleted.' water reading(s) deleted (invoiced readings were skipped).');
        }

        return back();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function propertyChargeTemplates(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeChargeType(string $raw): string
    {
        $value = (string) Str::of($raw)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return $value !== '' ? $value : 'other';
    }

    /**
     * Same source as lease utility validation: merged templates + active expense definitions.
     *
     * @return array<int, string>
     */
    private function allowedUtilityTypesForUnit(int $unitId): array
    {
        if ($unitId <= 0) {
            return [];
        }

        $unit = PropertyUnit::query()->select(['id', 'property_id'])->find($unitId);
        if (! $unit) {
            return [];
        }

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];
        $rows = $all[(string) $unit->property_id] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $types = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }

            $type = $this->normalizeUtilityTypeForRules((string) ($row['charge_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        foreach (ExpenseDefinition::query()
            ->where('is_active', true)
            ->where('property_id', $unit->property_id)
            ->orderBy('sort_order')
            ->orderBy('charge_key')
            ->get() as $def) {
            $scopeUnitId = $def->property_unit_id ? (int) $def->property_unit_id : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }
            $type = $this->normalizeUtilityTypeForRules((string) $def->charge_key);
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        return array_values($types);
    }

    private function normalizeUtilityTypeForRules(string $type): string
    {
        return (string) Str::of($type)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
