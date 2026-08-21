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
use App\Support\Property\PropertyFilterCascadeCatalog;
use App\Support\Property\UtilityWorkspaceViewData;
use App\Exceptions\Property\UtilityPeriodClosedException;
use App\Jobs\RefreshUtilityIntelligenceCacheJob;
use App\Services\Property\AttachedUtilityChargeService;
use App\Services\Property\PropertyMoney;
use App\Services\Property\UtilityIntelligenceService;
use App\Services\Property\UtilityPeriodGuardService;
use App\Services\Property\WaterBillingService;
use App\Services\Property\WaterPenaltyService;
use Illuminate\Http\JsonResponse;
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
            'property_id' => max(0, (int) $request->query('property_id', 0)),
            'unit_id' => max(0, (int) $request->query('unit_id', 0)),
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
        $query = app(PropertyFilterCascadeCatalog::class)->applyToUtilityChargeQuery($query, $filters);
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
            ->with(['unit.property', 'invoice.allocations'])
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
        $readingAnomalies = app(UtilityIntelligenceService::class)->anomalyMapForReadingIds(
            $waterReadings->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
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

        $waterChargePropertyIdsQuery = DB::table('pm_unit_utility_charges as charges')
            ->join('property_units as units', 'units.id', '=', 'charges.property_unit_id')
            ->where('charges.charge_type', 'water')
            ->distinct();
        if (\App\Models\Concerns\AgentWorkspaceScope::shouldApply()) {
            $waterChargePropertyIdsQuery
                ->join('properties as wp', 'wp.id', '=', 'units.property_id')
                ->where('wp.agent_user_id', (int) auth()->id());
        }
        $waterChargePropertyIds = $waterChargePropertyIdsQuery
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

        $billingReadiness = [
            'month' => $readinessMonth,
            'missing' => $missingWaterReadings,
            'anomalies' => $usageAnomalies->values(),
            'water_enabled_units' => $waterEnabledUnitIds->count(),
            'recorded_units' => $monthReadings->count(),
        ];

        $waterRateAdjustments = app(WaterBillingService::class)->waterRateAdjustmentRows(
            $readinessMonth,
            $waterTemplateByUnit,
        );

        $openWaterAr = (float) PmInvoice::query()
            ->liveBalances()
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->whereColumn('amount_paid', '<', 'amount')
            ->sum(DB::raw('amount - amount_paid'));

        $uninvoicedReadings = (int) PmWaterReading::query()
            ->where('billing_month', $readinessMonth)
            ->whereNull('pm_invoice_id')
            ->count();

        $stats = [
            ['label' => 'Readings (page)', 'value' => (string) $waterReadings->count(), 'hint' => 'Current filter'],
            ['label' => 'Month progress', 'value' => ((int) $billingReadiness['recorded_units']).'/'.((int) $billingReadiness['water_enabled_units']), 'hint' => $readinessMonth.' captured'],
            ['label' => 'Missing meters', 'value' => (string) collect($billingReadiness['missing'])->count(), 'hint' => 'Need readings'],
            ['label' => 'Charge lines', 'value' => (string) $charges->total(), 'hint' => 'Filtered ledger'],
        ];

        $opsKpis = [
            ['label' => 'Open utility AR', 'value' => PropertyMoney::kes($openWaterAr), 'hint' => 'Water & mixed', 'tone' => $openWaterAr > 0 ? 'warning' : 'success'],
            ['label' => 'Readings captured', 'value' => ((int) $billingReadiness['recorded_units']).'/'.((int) $billingReadiness['water_enabled_units']), 'hint' => $readinessMonth, 'tone' => 'info'],
            ['label' => 'Uninvoiced', 'value' => (string) $uninvoicedReadings, 'hint' => 'Readings pending invoice', 'tone' => $uninvoicedReadings > 0 ? 'warning' : 'success'],
            ['label' => 'Usage alerts', 'value' => (string) collect($billingReadiness['anomalies'])->count(), 'hint' => 'Review before billing', 'tone' => collect($billingReadiness['anomalies'])->count() > 0 ? 'danger' : 'success'],
            ['label' => 'Rate corrections', 'value' => (string) count($waterRateAdjustments), 'hint' => 'Bill water supplement', 'tone' => count($waterRateAdjustments) > 0 ? 'warning' : 'success'],
        ];

        $units = PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get();
        $cascade = app(PropertyFilterCascadeCatalog::class);
        $propertyId = (int) $filters['property_id'];
        $viewFilters = [
            ...$filters,
            'sort' => $sortBy,
            'dir' => $dir,
            'per_page' => (string) $perPage,
            'wr_per_page' => (string) $wrPerPage,
        ];

        return property_view('property.agent.revenue.utilities', [
            'stats' => $stats,
            'opsKpis' => $opsKpis,
            'charges' => $charges,
            'waterReadings' => $waterReadings,
            'readingAnomalies' => $readingAnomalies,
            'filters' => $viewFilters,
            'units' => $cascade->unitsForProperty($propertyId),
            'properties' => $cascade->properties(),
            'filterCascadeCatalog' => $cascade->fromUtilityCharges(),
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
            'billingReadiness' => $billingReadiness,
            'waterRateAdjustments' => $waterRateAdjustments,
            ...UtilityWorkspaceViewData::compose($request, $viewFilters, $units, $waterChargePropertyIds->all()),
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

        $property = app(\App\Services\Property\PropertyManagementGuardService::class)
            ->propertyForUnitId((int) $data['property_unit_id']);
        if ($property) {
            app(\App\Services\Property\PropertyManagementGuardService::class)->assertCanSetupUtility($property);
        }

        PmUnitUtilityCharge::query()->create($data);

        return back()->with('success', __('Utility charge saved.'));
    }

    public function generateUtilityInvoices(Request $request, AttachedUtilityChargeService $attachedCharges): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
        ]);

        $billingMonth = (string) $data['billing_month'];
        $dueDate = (string) $data['due_date'];
        $overrideId = (int) $request->input('utility_override_request_id', 0) ?: null;

        try {
            app(UtilityPeriodGuardService::class)->assertMutable(
                $billingMonth,
                UtilityPeriodGuardService::ACTION_GENERATE_INVOICE,
                $request->user(),
                $overrideId,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

        try {
            $materialized = $attachedCharges->materializeForMonth(
                $billingMonth,
                $request->user(),
                null,
                $overrideId,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

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

                $chargeType = strtolower(trim((string) ($charge->charge_type ?? '')));
                $label = trim((string) ($charge->label ?? ''));
                $knownOptions = PmInvoice::createTypeOptions();
                if ($chargeType !== '' && isset($knownOptions[$chargeType])) {
                    $invoiceType = $chargeType;
                } elseif ($chargeType !== '' && in_array($chargeType, PmInvoice::reservedTypeKeys(), true)
                    && $chargeType !== PmInvoice::TYPE_OTHER && $chargeType !== PmInvoice::TYPE_MIXED) {
                    $invoiceType = $chargeType;
                } else {
                    $invoiceType = PmInvoice::resolveOrCreateTypeFromLabel(
                        $label !== '' ? $label : ($chargeType !== '' ? $chargeType : 'Service'),
                        PmInvoice::TYPE_SERVICE
                    );
                }

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
        if (($materialized['created'] ?? 0) > 0) {
            $msg .= ' '.$materialized['created'].' charge line(s) auto-created from property expense rules.';
        }
        if ($skippedNoLease > 0) {
            $msg .= ' '.$skippedNoLease.' charge line(s) skipped (no active lease on unit).';
        }

        return back()->with('success', $msg);
    }

    public function materializeAttachedCharges(Request $request, AttachedUtilityChargeService $attachedCharges): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
        ]);

        $billingMonth = (string) $data['billing_month'];
        $overrideId = (int) $request->input('utility_override_request_id', 0) ?: null;

        try {
            $stats = $attachedCharges->materializeForMonth(
                $billingMonth,
                $request->user(),
                null,
                $overrideId,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

        $msg = sprintf(
            '%d charge line(s) created for %s from property expense rules.',
            (int) $stats['created'],
            $billingMonth,
        );
        if ($stats['skipped_duplicate'] > 0) {
            $msg .= ' '.$stats['skipped_duplicate'].' already existed.';
        }
        if ($stats['skipped_no_lease'] > 0) {
            $msg .= ' '.$stats['skipped_no_lease'].' skipped (no active lease).';
        }
        if ($stats['skipped_rate_only'] > 0) {
            $msg .= ' '.$stats['skipped_rate_only'].' rate-only rule(s) need manual usage entry.';
        }

        return back()->with('success', $msg);
    }

    public function storeWaterReading(Request $request, WaterBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'is_estimated' => ['nullable', 'boolean'],
            'is_meter_reset' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $request->filled('previous_reading')) {
            $data['previous_reading'] = $billing->defaultPreviousReading(
                (int) $data['property_unit_id'],
                (string) $data['billing_month']
            );
        }

        $data['utility_override_request_id'] = (int) $request->input('utility_override_request_id', 0) ?: null;

        try {
            $billing->recordReading($data, $request->user());
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->refreshUtilityIntelligence((int) $request->user()?->id);

        return back()->with('success', 'Water meter reading saved.');
    }

    public function updateWaterReading(Request $request, PmWaterReading $reading, WaterBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'is_estimated' => ['nullable', 'boolean'],
            'is_meter_reset' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['utility_override_request_id'] = (int) $request->input('utility_override_request_id', 0) ?: null;

        try {
            $billing->updateReading(
                $reading,
                $data,
                $request->user(),
                $data['utility_override_request_id'],
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->refreshUtilityIntelligence((int) $request->user()?->id);

        return back()->with('success', 'Water reading updated.');
    }

    /**
     * Default previous reading per unit (last current reading before billing_month), for meter forms.
     */
    public function waterDefaultPreviousReadings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
        ]);

        $propertyId = (int) $data['property_id'];
        $month = (string) $data['billing_month'];

        $previousByUnit = [];
        foreach (PropertyUnit::query()->where('property_id', $propertyId)->orderBy('label')->pluck('id') as $unitId) {
            $unitId = (int) $unitId;
            $previousByUnit[(string) $unitId] = round(
                $this->defaultWaterPreviousReading($unitId, $month),
                3,
                PHP_ROUND_HALF_UP
            );
        }

        return response()->json(['previous_by_unit' => $previousByUnit]);
    }

    public function storeBulkWaterReadings(Request $request, WaterBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'current_readings' => ['required', 'array'],
            'current_readings.*' => ['nullable', 'numeric', 'min:0'],
            'previous_readings' => ['nullable', 'array'],
            'previous_readings.*' => ['nullable', 'numeric', 'min:0'],
            'is_estimated' => ['nullable', 'array'],
            'is_estimated.*' => ['nullable', 'boolean'],
            'is_meter_reset' => ['nullable', 'array'],
            'is_meter_reset.*' => ['nullable', 'boolean'],
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

        $previousReadings = $data['previous_readings'] ?? [];
        $estimatedFlags = $data['is_estimated'] ?? [];
        $resetFlags = $data['is_meter_reset'] ?? [];

        try {
            $result = $billing->recordBulkReadings(
                $submittedReadings->all(),
                $month,
                $rate,
                $fixed,
                $notes,
                $previousReadings,
                $estimatedFlags,
                $resetFlags,
                $unitMap->all(),
                $request->user(),
                (int) $request->input('utility_override_request_id', 0) ?: null,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

        if ($result['errors'] !== []) {
            return back()->withErrors($result['errors'])->withInput();
        }

        $this->refreshUtilityIntelligence((int) $request->user()?->id);

        return back()->with('success', $result['saved'].' water meter reading(s) saved in bulk.');
    }

    public function generateWaterInvoices(Request $request, WaterBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
        ]);

        try {
            $stats = $billing->generateInvoicesForMonth(
                billingMonth: (string) $data['billing_month'],
                dueDate: (string) $data['due_date'],
                actor: $request->user(),
                source: 'utilities.ui',
                postToGl: true,
                autoApplyCredit: true,
                utilityOverrideRequestId: (int) $request->input('utility_override_request_id', 0) ?: null,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

        if ($stats['created'] === 0) {
            return back()->withErrors([
                'billing_month' => 'No water invoices generated for '.$data['billing_month']
                    .'. Skipped: '.$stats['skipped_no_lease'].' (no lease), '
                    .$stats['skipped_duplicate'].' (duplicate).',
            ]);
        }

        $msg = $stats['created'].' water invoice(s) generated for '.$data['billing_month'].'.';
        if ($stats['skipped_no_lease'] > 0 || $stats['skipped_duplicate'] > 0) {
            $msg .= ' Skipped: '.$stats['skipped_no_lease'].' no lease, '.$stats['skipped_duplicate'].' duplicate.';
        }
        if ($stats['credit_applied'] > 0) {
            $msg .= ' Tenant credit auto-applied on '.$stats['credit_applied'].' invoice(s).';
        }

        return back()->with('success', $msg);
    }

    public function generateWaterSupplements(Request $request, WaterBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['nullable', 'date'],
            'reading_ids' => ['nullable', 'array'],
            'reading_ids.*' => ['integer', 'exists:pm_water_readings,id'],
            'generate_all' => ['nullable', 'boolean'],
        ]);

        $month = (string) $data['billing_month'];
        $generateAll = $request->boolean('generate_all');
        $readingIds = $generateAll
            ? null
            : collect($data['reading_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();

        if (! $generateAll && $readingIds === []) {
            return back()->withErrors(['reading_ids' => 'Select at least one row, or use Bill all rate corrections.'])->withInput();
        }

        $templates = $billing->waterTemplateByUnitFromSettings();

        try {
            $result = $billing->generateWaterSupplements(
                $month,
                $templates,
                $readingIds,
                $request->user(),
                isset($data['due_date']) ? (string) $data['due_date'] : null,
                (int) $request->input('utility_override_request_id', 0) ?: null,
            );
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['billing_month' => $e->getMessage()])->withInput();
        }

        $message = "Issued {$result['created']} water supplement invoice(s) for {$month}.";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }
        if ($result['errors'] !== []) {
            return back()
                ->with('warning', $message)
                ->with('bulk_invoice_errors', array_slice($result['errors'], 0, 8));
        }

        if ($result['created'] === 0) {
            return back()->withErrors([
                'billing_month' => 'No water supplements were created. Update property water rates first, or amounts may already match current rates.',
            ]);
        }

        return back()->with('success', $message);
    }

    public function applyWaterPenalties(Request $request, WaterPenaltyService $penalties): RedirectResponse
    {
        if ($request->boolean('preview')) {
            $rows = $penalties->preview(now()->toDateString());
            if ($rows->isEmpty()) {
                return back()->with('success', 'No water penalties would be applied today.');
            }

            return back()->with('success', $rows->count().' water penalty(ies) would be applied. Review overdue water invoices.');
        }

        $stats = $penalties->apply(now()->toDateString(), $request->user(), 'utilities.ui');

        return back()->with('success', 'Applied water penalties to '.$stats['applied'].' invoice(s).');
    }

    public function previewWaterPenalties(WaterPenaltyService $penalties): JsonResponse
    {
        $simulation = $penalties->simulate(now()->toDateString());
        $rows = $simulation['rows']->map(fn (array $row) => [
            ...$row,
            'base_display' => PropertyMoney::kes((float) ($row['base'] ?? 0)),
            'penalty_display' => PropertyMoney::kes((float) ($row['penalty'] ?? 0)),
            'compounding_label' => str_replace('_', ' ', (string) ($row['compounding_mode'] ?? 'simple')),
        ])->values();

        return response()->json([
            'rows' => $rows,
            'warnings' => $simulation['warnings'],
            'total_penalty' => $simulation['total_penalty'],
            'total_penalty_display' => PropertyMoney::kes((float) $simulation['total_penalty']),
        ]);
    }

    public function reverseWaterPenalty(Request $request, WaterPenaltyService $penalties): RedirectResponse
    {
        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:pm_invoice_penalty_applications,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'utility_override_request_id' => ['nullable', 'integer'],
        ]);

        $application = \App\Models\PmInvoicePenaltyApplication::query()->findOrFail($data['application_id']);
        try {
            if (! $penalties->reverseApplication(
                $application,
                $request->user(),
                $data['reason'] ?? null,
                (int) ($data['utility_override_request_id'] ?? 0) ?: null,
            )) {
                return back()->withErrors(['application_id' => 'Penalty could not be reversed (already reversed or invalid).']);
            }
        } catch (UtilityPeriodClosedException $e) {
            return back()->withErrors(['application_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Water penalty reversed.');
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
            'utility_override_request_id' => ['nullable', 'integer'],
        ]);

        $ids = collect($data['reading_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['reading_ids' => 'Select at least one water reading first.']);
        }

        if ($data['action'] === 'delete') {
            $overrideId = (int) ($data['utility_override_request_id'] ?? 0) ?: null;
            $guard = app(UtilityPeriodGuardService::class);
            $readings = PmWaterReading::query()->whereIn('id', $ids)->get();

            try {
                foreach ($readings->pluck('billing_month')->unique() as $month) {
                    $guard->assertMutable(
                        (string) $month,
                        UtilityPeriodGuardService::ACTION_DELETE_READING,
                        $request->user(),
                        $overrideId,
                        'pm_water_reading',
                        null,
                    );
                }
            } catch (UtilityPeriodClosedException $e) {
                return back()->withErrors(['reading_ids' => $e->getMessage()]);
            }

            $deleted = PmWaterReading::query()
                ->whereIn('id', $ids)
                ->whereNull('pm_invoice_id')
                ->delete();

            return back()->with('success', $deleted.' water reading(s) deleted (invoiced readings were skipped).');
        }

        return back();
    }

    /** Latest prior-month current reading for this unit, or 0 if none (e.g. new meter). */
    private function defaultWaterPreviousReading(int $unitId, string $billingMonth): float
    {
        return (float) (PmWaterReading::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', '<', $billingMonth)
            ->orderByDesc('billing_month')
            ->value('current_reading') ?? 0);
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

    private function refreshUtilityIntelligence(?int $agentUserId): void
    {
        if (! $agentUserId) {
            return;
        }

        app(UtilityIntelligenceService::class)->forgetCache($agentUserId);
        RefreshUtilityIntelligenceCacheJob::dispatch($agentUserId);
    }
}
