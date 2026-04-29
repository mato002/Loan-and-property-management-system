<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Mail\LandlordPortalCredentialsMail;
use App\Models\DepositDefinition;
use App\Models\ExpenseDefinition;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\TabularExport;
use App\Services\Property\PropertyMoney;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PropertyPortfolioController extends Controller
{
    private const IMPERSONATOR_SESSION_KEY = 'pm_impersonator_id';

    public function impersonateLandlord(Request $request, User $landlord): RedirectResponse
    {
        $actor = Auth::user();
        if (! $actor || (! $actor->hasPmPermission('users.impersonate'))) {
            abort(403);
        }
        $this->ensureLandlordVisibleForActor($actor, $landlord);

        // If not already impersonating, store who initiated it.
        if (! $request->session()->has(self::IMPERSONATOR_SESSION_KEY)) {
            $request->session()->put(self::IMPERSONATOR_SESSION_KEY, (int) $actor->id);
        }

        Auth::login($landlord);

        Log::info('property_impersonation_started', [
            'impersonator_id' => (int) ($request->session()->get(self::IMPERSONATOR_SESSION_KEY) ?? 0),
            'impersonated_user_id' => (int) $landlord->id,
        ]);

        return redirect()
            ->route('property.landlord.portfolio')
            ->with('success', 'Now viewing the portal as '.$landlord->name.'.');
    }

    public function stopImpersonation(Request $request): RedirectResponse
    {
        $impersonatorId = (int) $request->session()->get(self::IMPERSONATOR_SESSION_KEY, 0);
        if ($impersonatorId <= 0) {
            return back();
        }

        $impersonator = User::query()->find($impersonatorId);
        $request->session()->forget(self::IMPERSONATOR_SESSION_KEY);

        if (! $impersonator) {
            Auth::logout();
            return redirect()->route('login')->with('success', 'Impersonation ended. Please log in again.');
        }

        Auth::login($impersonator);

        Log::info('property_impersonation_stopped', [
            'impersonator_id' => (int) $impersonator->id,
        ]);

        return redirect()
            ->route('property.landlords.index')
            ->with('success', 'Impersonation ended.');
    }

    private function generateUniquePropertyCode(string $name): string
    {
        $base = strtoupper(Str::of($name)->slug('')->substr(0, 6)->toString());
        $base = $base !== '' ? $base : 'PROP';

        // Try a few times; code is unique in DB, so we must avoid collisions.
        for ($i = 0; $i < 25; $i++) {
            $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $base.'-'.$suffix; // e.g. "GREENH-0421"
            if (!Property::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Extremely unlikely fallback.
        return 'PROP-'.strtoupper(Str::random(8));
    }

    public function propertyList(Request $request): View
    {
        $filters = $request->only(['q', 'city', 'landlord', 'sort', 'dir']);
        $q = Property::query()
            ->with(['landlords' => fn ($q) => $q->orderBy('name')])
            ->withCount('units')
            ->withCount([
                'units as occupied_units_count' => fn ($uq) => $uq->where('status', PropertyUnit::STATUS_OCCUPIED),
                'units as vacant_units_count' => fn ($uq) => $uq->where('status', PropertyUnit::STATUS_VACANT),
            ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function ($b) use ($search) {
                $b->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('address_line', 'like', '%'.$search.'%');
            });
        }

        $city = trim((string) ($filters['city'] ?? ''));
        if ($city !== '') {
            $q->where('city', $city);
        }

        $landlord = trim((string) ($filters['landlord'] ?? ''));
        if ($landlord === 'linked') {
            $q->has('landlords');
        } elseif ($landlord === 'unlinked') {
            $q->doesntHave('landlords');
        }

        $sort = (string) ($filters['sort'] ?? 'name');
        $dir = strtolower((string) ($filters['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['name', 'code', 'city', 'units_count', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'name';
        }
        $q->orderBy($sort, $dir)->orderBy('name');

        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));
        $portfolio = $q->paginate($perPage)->withQueryString();

        $stats = [
            ['label' => 'Properties', 'value' => (string) $portfolio->total(), 'hint' => 'In portfolio'],
            ['label' => 'Total units', 'value' => (string) PropertyUnit::query()->count(), 'hint' => 'Across all'],
            ['label' => 'Occupied', 'value' => (string) PropertyUnit::query()->where('status', PropertyUnit::STATUS_OCCUPIED)->count(), 'hint' => 'Units'],
            ['label' => 'Vacant', 'value' => (string) PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count(), 'hint' => 'Units'],
        ];

        $propertyChargeTemplatesByPropertyId = $this->allPropertyChargeTemplates();

        $rows = $portfolio->getCollection()->map(function (Property $p) use ($propertyChargeTemplatesByPropertyId) {
            $landlordNames = $p->landlords->pluck('name')->filter()->values();
            $landlordCell = $landlordNames->isEmpty()
                ? '—'
                : new HtmlString(
                    '<span class="text-xs font-medium text-slate-700" title="'.e($landlordNames->implode(', ')).'">'.
                    e((string) $landlordNames->count().' linked').
                    '</span>'
                );
            $status = $p->units_count === 0
                ? 'No units'
                : ($p->vacant_units_count > 0 ? 'Has vacancy' : 'Fully occupied');
            $chargeTemplates = (array) ($propertyChargeTemplatesByPropertyId[(string) $p->id] ?? []);
            $chargeBreakdownCell = count($chargeTemplates) === 0
                ? '—'
                : new HtmlString(
                    '<div class="space-y-1">'.
                    collect($chargeTemplates)->map(function (array $template): string {
                        return '<div class="text-xs text-slate-700 leading-5">'.e($this->formatChargeTemplateSummary($template)).'</div>';
                    })->implode('').
                    '</div>'
                );
            $action = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details class="group">'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">'.
                'Actions <span class="text-slate-400">▼</span>'.
                '</summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                '<a href="'.route('property.properties.show', $p).'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">View</a>'.
                '<a href="'.route('property.properties.edit', $p).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">Edit</a>'.
                '<a href="'.route('property.properties.units', ['property_id' => $p->id], absolute: false).'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Units</a>'.
                '<a href="'.route('property.properties.list', ['property_id' => $p->id], absolute: false).'#link-landlord-form" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Link landlord</a>'.
                '<form method="POST" action="'.route('property.properties.destroy', $p).'" data-swal-title="Delete property?" data-swal-confirm="This will permanently delete this property if it has no units." data-swal-confirm-text="Yes, delete">'.
                csrf_field().method_field('DELETE').
                '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>'.
                '</form>'.
                '</div>'.
                '</details>'.
                '</div>'
            );

            $nameCodeCell = new HtmlString(
                '<div class="space-y-0.5">'.
                '<div class="font-medium text-slate-900">'.e((string) $p->name).'</div>'.
                '<div class="text-xs text-slate-500">'.e((string) ($p->code ?? '—')).'</div>'.
                '</div>'
            );
            $addressCityCell = new HtmlString(
                '<div class="space-y-0.5">'.
                '<div class="text-slate-700">'.e((string) ($p->address_line ?? '—')).'</div>'.
                '<div class="text-xs text-slate-500">'.e((string) ($p->city ?? '—')).'</div>'.
                '</div>'
            );

            return [
                $nameCodeCell,
                $addressCityCell,
                (string) $p->units_count,
                $chargeBreakdownCell,
                $landlordCell,
                $status,
                $action,
            ];
        })->all();

        $actor = $request->user();

        $landlordLinks = DB::table('property_landlord')
            ->join('properties', 'properties.id', '=', 'property_landlord.property_id')
            ->join('users', 'users.id', '=', 'property_landlord.user_id')
            ->select([
                'property_landlord.id',
                'properties.id as property_id',
                'properties.name as property_name',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'property_landlord.ownership_percent',
            ]);
        if ($actor && $this->isAgentActor($actor)) {
            $landlordLinks->where('properties.agent_user_id', (int) $actor->id);
        }
        $landlordLinks = $landlordLinks
            ->orderBy('properties.name')
            ->orderBy('users.name')
            ->get();

        $linkableProperties = Property::query()
            ->doesntHave('landlords')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('property.agent.properties.list', [
            'stats' => $stats,
            'columns' => ['Name / Code', 'Address / City', 'Units', 'Utility charges', 'Landlord(s)', 'Status', 'Actions'],
            'tableRows' => $rows,
            'propertyOnboardingFields' => $this->propertyOnboardingFieldConfig(),
            'landlordUsers' => $this->landlordUsersQueryForActor($request->user())->orderBy('name')->get(),
            'properties' => $portfolio,
            'linkableProperties' => $linkableProperties,
            'landlordLinks' => $landlordLinks,
            'filters' => array_merge($filters, ['per_page' => (string) $perPage]),
            'perPage' => $perPage,
            'cities' => Property::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function propertyListExport(Request $request)
    {
        $filters = $request->only(['q', 'city', 'landlord', 'sort', 'dir']);
        $q = Property::query()
            ->with(['landlords' => fn ($lq) => $lq->orderBy('name')])
            ->withCount('units')
            ->withCount([
                'units as occupied_units_count' => fn ($uq) => $uq->where('status', PropertyUnit::STATUS_OCCUPIED),
                'units as vacant_units_count' => fn ($uq) => $uq->where('status', PropertyUnit::STATUS_VACANT),
            ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function ($b) use ($search) {
                $b->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('address_line', 'like', '%'.$search.'%');
            });
        }
        $city = trim((string) ($filters['city'] ?? ''));
        if ($city !== '') {
            $q->where('city', $city);
        }
        $landlord = trim((string) ($filters['landlord'] ?? ''));
        if ($landlord === 'linked') {
            $q->has('landlords');
        } elseif ($landlord === 'unlinked') {
            $q->doesntHave('landlords');
        }

        $rows = $q->orderBy('name')->get();
        $propertyChargeTemplatesByPropertyId = $this->allPropertyChargeTemplates();

        return CsvExport::stream(
            'properties_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Name', 'Code', 'Address', 'City', 'Total Units', 'Occupied Units', 'Vacant Units', 'Utility charges', 'Landlords', 'Status'],
            function () use ($rows, $propertyChargeTemplatesByPropertyId) {
                foreach ($rows as $p) {
                    $status = $p->units_count === 0
                        ? 'No units'
                        : ($p->vacant_units_count > 0 ? 'Has vacancy' : 'Fully occupied');
                    $chargeTemplates = (array) ($propertyChargeTemplatesByPropertyId[(string) $p->id] ?? []);
                    $chargeSummary = collect($chargeTemplates)
                        ->map(fn (array $template): string => $this->formatChargeTemplateSummary($template))
                        ->implode('; ');

                    yield [
                        $p->id,
                        $p->name,
                        $p->code,
                        $p->address_line,
                        $p->city,
                        $p->units_count,
                        $p->occupied_units_count,
                        $p->vacant_units_count,
                        $chargeSummary,
                        $p->landlords->pluck('name')->join(', '),
                        $status,
                    ];
                }
            }
        );
    }

    public function showProperty(Request $request, Property $property)
    {
        $month = (string) $request->query('month', '');
        $fy = (int) $request->query('fy', now()->year);
        $unitStatus = (string) $request->query('unit_status', '');
        if (! in_array($unitStatus, [
            PropertyUnit::STATUS_VACANT,
            PropertyUnit::STATUS_OCCUPIED,
            PropertyUnit::STATUS_NOTICE,
        ], true)) {
            $unitStatus = '';
        }
        $collectionChannel = trim((string) $request->query('collection_channel', ''));
        $collectionSearch = trim((string) $request->query('collection_q', ''));
        $exportReport = trim((string) $request->query('export_report', 'full'));
        if (! in_array($exportReport, ['full', 'units', 'collections', 'channels'], true)) {
            $exportReport = 'full';
        }
        if ($fy < 2000 || $fy > 2100) {
            $fy = (int) now()->year;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $periodLabel = $periodStart->format('M Y');
        } else {
            $periodStart = Carbon::create($fy, 1, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfYear();
            $periodLabel = 'FY '.$fy;
        }

        $property->load(['landlords' => fn ($q) => $q->orderBy('name')]);

        $units = PropertyUnit::query()
            ->where('property_id', $property->id)
            ->orderBy('label')
            ->get();
        $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();

        $totalUnits = (int) $units->count();
        $occupiedUnits = (int) $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $vacantUnits = (int) $units->where('status', PropertyUnit::STATUS_VACANT)->count();
        $noticeUnits = (int) $units->where('status', PropertyUnit::STATUS_NOTICE)->count();
        $rentRoll = (float) $units->sum(fn (PropertyUnit $u) => (float) $u->rent_amount);

        $invoiced = 0.0;
        $collected = 0.0;
        $arrears = 0.0;
        $activeLeasesCount = 0;
        $activeLeaseRent = 0.0;
        $recentCollections = collect();
        $unitSnapshots = collect();
        $collectionByChannel = collect();
        $availableCollectionChannels = [];

        if ($unitIds !== []) {
            $invoiced = (float) DB::table('pm_invoices')
                ->whereIn('property_unit_id', $unitIds)
                ->whereBetween('issue_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->sum('amount');

            $collected = (float) DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->whereIn('i.property_unit_id', $unitIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->sum('a.amount');

            $arrears = (float) DB::table('pm_invoices')
                ->whereIn('property_unit_id', $unitIds)
                ->whereDate('issue_date', '<=', $periodEnd->toDateString())
                ->sum(DB::raw('GREATEST(amount - amount_paid, 0)'));

            $activeLeaseRows = DB::table('pm_lease_unit as lu')
                ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                ->whereIn('lu.property_unit_id', $unitIds)
                ->where('l.status', PmLease::STATUS_ACTIVE)
                ->select(['lu.property_unit_id', 'l.monthly_rent'])
                ->get();

            $activeLeasesCount = (int) $activeLeaseRows->pluck('property_unit_id')->unique()->count();
            $activeLeaseRent = (float) $activeLeaseRows->sum('monthly_rent');

            $recentCollectionsQuery = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->leftJoin('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
                ->whereIn('i.property_unit_id', $unitIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->when($collectionChannel !== '', fn ($q) => $q->where('pay.channel', $collectionChannel))
                ->when($collectionSearch !== '', function ($q) use ($collectionSearch) {
                    $q->where(function ($qq) use ($collectionSearch) {
                        $qq->where('t.name', 'like', '%'.$collectionSearch.'%')
                            ->orWhere('pay.external_ref', 'like', '%'.$collectionSearch.'%');
                    });
                });

            $recentCollections = (clone $recentCollectionsQuery)
                ->orderByDesc('pay.paid_at')
                ->limit(25)
                ->select(['pay.paid_at', 'pay.external_ref', 'pay.channel', 'a.amount', 't.name as tenant_name'])
                ->get();

            $collectionByChannel = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->whereIn('i.property_unit_id', $unitIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->groupBy('pay.channel')
                ->orderByDesc(DB::raw('SUM(a.amount)'))
                ->selectRaw('COALESCE(pay.channel, "") as channel, COUNT(*) as tx_count, SUM(a.amount) as total_amount')
                ->get();

            $availableCollectionChannels = DB::table('pm_payments as pay')
                ->join('pm_payment_allocations as a', 'a.pm_payment_id', '=', 'pay.id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->whereIn('i.property_unit_id', $unitIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->whereNotNull('pay.channel')
                ->where('pay.channel', '!=', '')
                ->distinct()
                ->orderBy('pay.channel')
                ->pluck('pay.channel')
                ->all();

            $unitSnapshots = DB::table('property_units as u')
                ->leftJoin('pm_invoices as i', 'i.property_unit_id', '=', 'u.id')
                ->selectRaw('u.id, u.label, u.status, u.rent_amount, COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as arrears')
                ->where('u.property_id', $property->id)
                ->when($unitStatus !== '', fn ($q) => $q->where('u.status', $unitStatus))
                ->groupBy('u.id', 'u.label', 'u.status', 'u.rent_amount')
                ->orderBy('u.label')
                ->get();
        }

        $commissionDefaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $commissionDefaultPct = is_numeric($commissionDefaultRaw) ? (float) $commissionDefaultRaw : 10.0;
        if ($commissionDefaultPct < 0) {
            $commissionDefaultPct = 0.0;
        }
        $commissionOverridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $commissionOverrides = json_decode($commissionOverridesRaw, true);
        $propertyCommissionPct = $commissionDefaultPct;
        if (is_array($commissionOverrides)) {
            $propertyCommissionPct = is_numeric($commissionOverrides[(string) $property->id] ?? null)
                ? max(0.0, (float) $commissionOverrides[(string) $property->id])
                : $commissionDefaultPct;
        }
        $agentEarning = $collected * ($propertyCommissionPct / 100);

        $ownerRows = $property->landlords->map(function (User $u) use ($collected, $arrears, $agentEarning) {
            $pct = (float) ($u->pivot->ownership_percent ?? 0);
            $ratio = $pct / 100;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'ownership_percent' => $pct,
                'share_collected' => $collected * $ratio,
                'share_arrears' => $arrears * $ratio,
                'agent_earning_portion' => $agentEarning * $ratio,
            ];
        });

        $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0.0;
        $collectionRate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0.0;
        $avgArrearsPerUnit = $totalUnits > 0 ? ($arrears / $totalUnits) : 0.0;
        $export = strtolower(trim((string) $request->query('export', '')));
        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            if ($exportReport === 'units') {
                return TabularExport::stream(
                    'property-'.$property->id.'-units',
                    ['Property', 'Period', 'Unit', 'Status', 'Listed Rent', 'Arrears'],
                    function () use ($property, $periodLabel, $unitSnapshots) {
                        return $unitSnapshots->map(function ($u) use ($property, $periodLabel) {
                            return [
                                (string) $property->name,
                                (string) $periodLabel,
                                (string) ($u->label ?? ''),
                                (string) ucfirst((string) ($u->status ?? '')),
                                number_format((float) ($u->rent_amount ?? 0), 2, '.', ''),
                                number_format((float) ($u->arrears ?? 0), 2, '.', ''),
                            ];
                        });
                    },
                    $export
                );
            }

            if ($exportReport === 'collections') {
                return TabularExport::stream(
                    'property-'.$property->id.'-collections',
                    ['Property', 'Period', 'Date', 'Tenant', 'Channel', 'Reference', 'Amount'],
                    function () use ($property, $periodLabel, $recentCollections) {
                        return $recentCollections->map(function ($c) use ($property, $periodLabel) {
                            return [
                                (string) $property->name,
                                (string) $periodLabel,
                                ! empty($c->paid_at) ? Carbon::parse((string) $c->paid_at)->format('Y-m-d H:i') : '',
                                (string) ($c->tenant_name ?? '—'),
                                strtoupper((string) ($c->channel ?? '')),
                                (string) ($c->external_ref ?? ''),
                                number_format((float) ($c->amount ?? 0), 2, '.', ''),
                            ];
                        });
                    },
                    $export
                );
            }

            if ($exportReport === 'channels') {
                return TabularExport::stream(
                    'property-'.$property->id.'-collection-channels',
                    ['Property', 'Period', 'Channel', 'Transactions', 'Total Collected'],
                    function () use ($property, $periodLabel, $collectionByChannel) {
                        return $collectionByChannel->map(function ($row) use ($property, $periodLabel) {
                            return [
                                (string) $property->name,
                                (string) $periodLabel,
                                (string) (($row->channel ?? '') !== '' ? strtoupper((string) $row->channel) : 'UNSPECIFIED'),
                                (string) ((int) ($row->tx_count ?? 0)),
                                number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
                            ];
                        });
                    },
                    $export
                );
            }

            return TabularExport::stream(
                'property-'.$property->id.'-intelligence',
                ['Section', 'Property', 'Period', 'Label', 'Status / Channel', 'Reference', 'Amount', 'Arrears', 'Notes'],
                function () use ($property, $periodLabel, $unitSnapshots, $recentCollections, $collectionByChannel, $occupancyRate, $collectionRate) {
                    $rows = [];
                    $rows[] = [
                        'Summary',
                        (string) $property->name,
                        (string) $periodLabel,
                        'Occupancy rate',
                        number_format((float) $occupancyRate, 1).'%',
                        '',
                        '',
                        '',
                        'Collection rate '.number_format((float) $collectionRate, 1).'%',
                    ];

                    foreach ($unitSnapshots as $u) {
                        $rows[] = [
                            'Unit',
                            (string) $property->name,
                            (string) $periodLabel,
                            (string) ($u->label ?? ''),
                            (string) ucfirst((string) ($u->status ?? '')),
                            '',
                            number_format((float) ($u->rent_amount ?? 0), 2, '.', ''),
                            number_format((float) ($u->arrears ?? 0), 2, '.', ''),
                            '',
                        ];
                    }

                    foreach ($recentCollections as $c) {
                        $rows[] = [
                            'Collection',
                            (string) $property->name,
                            (string) $periodLabel,
                            (string) ($c->tenant_name ?? '—'),
                            strtoupper((string) ($c->channel ?? '')),
                            (string) ($c->external_ref ?? ''),
                            number_format((float) ($c->amount ?? 0), 2, '.', ''),
                            '',
                            ! empty($c->paid_at) ? Carbon::parse((string) $c->paid_at)->format('Y-m-d H:i') : '',
                        ];
                    }

                    foreach ($collectionByChannel as $row) {
                        $rows[] = [
                            'Channel report',
                            (string) $property->name,
                            (string) $periodLabel,
                            (string) (($row->channel ?? '') !== '' ? strtoupper((string) $row->channel) : 'UNSPECIFIED'),
                            (string) ((int) ($row->tx_count ?? 0)).' tx',
                            '',
                            number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
                            '',
                            '',
                        ];
                    }

                    return $rows;
                },
                $export
            );
        }

        return view('property.agent.properties.show', [
            'property' => $property,
            'units' => $units,
            'unitSnapshots' => $unitSnapshots,
            'periodLabel' => $periodLabel,
            'monthValue' => $month,
            'fyValue' => $fy,
            'stats' => [
                ['label' => 'Units', 'value' => (string) $totalUnits, 'hint' => 'Total doors'],
                ['label' => 'Occupied', 'value' => (string) $occupiedUnits, 'hint' => 'Current'],
                ['label' => 'Vacant / Notice', 'value' => $vacantUnits.' / '.$noticeUnits, 'hint' => 'Current'],
                ['label' => 'Rent roll', 'value' => PropertyMoney::kes($rentRoll), 'hint' => 'Listed unit rents'],
                ['label' => 'Invoiced ('.$periodLabel.')', 'value' => PropertyMoney::kes($invoiced), 'hint' => 'Issued invoices'],
                ['label' => 'Collected ('.$periodLabel.')', 'value' => PropertyMoney::kes($collected), 'hint' => 'Completed payments'],
                ['label' => 'Arrears', 'value' => PropertyMoney::kes($arrears), 'hint' => 'Outstanding invoices'],
                ['label' => 'Your earnings', 'value' => PropertyMoney::kes($agentEarning), 'hint' => 'At '.number_format($propertyCommissionPct, 2).'%'],
            ],
            'activeLeasesCount' => $activeLeasesCount,
            'activeLeaseRent' => $activeLeaseRent,
            'recentCollections' => $recentCollections,
            'collectionByChannel' => $collectionByChannel,
            'availableCollectionChannels' => $availableCollectionChannels,
            'ownerRows' => $ownerRows,
            'commissionPct' => $propertyCommissionPct,
            'filters' => [
                'unit_status' => $unitStatus,
                'collection_channel' => $collectionChannel,
                'collection_q' => $collectionSearch,
                'export_report' => $exportReport,
            ],
            'reporting' => [
                'occupancy_rate' => $occupancyRate,
                'collection_rate' => $collectionRate,
                'avg_arrears_per_unit' => $avgArrearsPerUnit,
            ],
            'propertyChargeTemplates' => $this->propertyChargeTemplates((int) $property->id),
            'propertyExpenseDefinitions' => $this->propertyExpenseDefinitions((int) $property->id),
            'propertyDepositDefinitions' => $this->propertyDepositDefinitions((int) $property->id),
            'unitFields' => $this->unitFieldConfig(),
        ]);
    }

    public function editProperty(Request $request, Property $property): View
    {
        $property->load(['landlords' => fn ($q) => $q->orderBy('name')]);

        return view('property.agent.properties.edit', [
            'property' => $property,
            'propertyOnboardingFields' => $this->propertyOnboardingFieldConfig(),
            'propertyCommissionPercent' => $this->propertyCommissionPercent((int) $property->id),
            'landlordUsers' => $this->landlordUsersQueryForActor($request->user())->orderBy('name')->get(),
            'propertyChargeTemplates' => $this->propertyChargeTemplates((int) $property->id),
        ]);
    }

    public function updateProperty(Request $request, Property $property): RedirectResponse
    {
        $propertyFields = $this->propertyOnboardingFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($propertyFields, 'name')), 'nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'unique:properties,code,'.$property->id],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'charge_templates' => ['nullable', 'array', 'max:50'],
            'charge_templates.*.property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            'charge_templates.*.charge_type' => ['nullable', 'string', 'max:64'],
            'charge_templates.*.label' => ['nullable', 'string', 'max:128'],
            'charge_templates.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.notes' => ['nullable', 'string', 'max:500'],
            'expense_definitions' => ['nullable', 'array', 'max:50'],
            'expense_definitions.*.property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            'expense_definitions.*.charge_key' => ['nullable', 'string', 'max:64'],
            'expense_definitions.*.label' => ['nullable', 'string', 'max:120'],
            'expense_definitions.*.is_required' => ['nullable', 'in:0,1'],
            'expense_definitions.*.amount_mode' => ['nullable', 'in:fixed,rate_per_unit'],
            'expense_definitions.*.amount_value' => ['nullable', 'numeric', 'min:0'],
            'expense_definitions.*.ledger_account' => ['nullable', 'string', 'max:120'],
            'expense_definitions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'expense_definitions.*.is_active' => ['nullable', 'in:0,1'],
            'deposit_definitions' => ['nullable', 'array', 'max:50'],
            'deposit_definitions.*.property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            'deposit_definitions.*.deposit_key' => ['nullable', 'string', 'max:64'],
            'deposit_definitions.*.label' => ['nullable', 'string', 'max:120'],
            'deposit_definitions.*.is_required' => ['nullable', 'in:0,1'],
            'deposit_definitions.*.amount_mode' => ['nullable', 'in:fixed,percent_rent'],
            'deposit_definitions.*.amount_value' => ['nullable', 'numeric', 'min:0'],
            'deposit_definitions.*.is_refundable' => ['nullable', 'in:0,1'],
            'deposit_definitions.*.ledger_account' => ['nullable', 'string', 'max:120'],
            'deposit_definitions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'deposit_definitions.*.is_active' => ['nullable', 'in:0,1'],
        ]);
        $commissionPercent = isset($data['commission_percent']) ? (float) $data['commission_percent'] : null;
        $hasChargeTemplates = $request->has('charge_templates');
        $hasExpenseDefinitions = $request->has('expense_definitions');
        $hasDepositDefinitions = $request->has('deposit_definitions');
        $chargeTemplates = $this->normalizePropertyChargeTemplates((array) ($data['charge_templates'] ?? []));
        $expenseDefinitions = $this->normalizePropertyExpenseDefinitions((int) $property->id, (array) ($data['expense_definitions'] ?? []));
        $depositDefinitions = $this->normalizePropertyDepositDefinitions((int) $property->id, (array) ($data['deposit_definitions'] ?? []));
        unset($data['commission_percent']);
        unset($data['charge_templates']);
        unset($data['expense_definitions']);
        unset($data['deposit_definitions']);

        $property->update($data);
        $this->setPropertyCommissionOverride((int) $property->id, $commissionPercent);
        if ($hasChargeTemplates) {
            $this->setPropertyChargeTemplates((int) $property->id, $chargeTemplates);
            $this->syncExpenseRulesFromUtilityTemplates((int) $property->id, $chargeTemplates);
        }
        if ($hasExpenseDefinitions) {
            $this->setPropertyExpenseDefinitions((int) $property->id, $expenseDefinitions);
        }
        if ($hasDepositDefinitions) {
            $this->setPropertyDepositDefinitions((int) $property->id, $depositDefinitions);
        }

        return back()->with('success', 'Property updated.');
    }

    public function destroyProperty(Property $property): RedirectResponse
    {
        if ($property->units()->exists()) {
            return back()->with('error', 'Cannot delete a property that already has units. Remove units first.');
        }

        $property->landlords()->detach();
        $property->delete();

        return back()->with('success', 'Property deleted.');
    }

    public function detachLandlord(Request $request): RedirectResponse
    {
        Log::warning('attachLandlord_debug: detachLandlord called', [
            'property_id' => $request->input('property_id'),
            'user_id' => $request->input('user_id'),
        ]);
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        $property->landlords()->detach($data['user_id']);

        return redirect()
            ->route('property.properties.edit', $property->id)
            ->with('success', __('Landlord unlinked from property.'));
    }

    public function updateLandlordOwnership(Request $request): RedirectResponse
    {
        Log::warning('attachLandlord_debug: updateLandlordOwnership called', [
            'property_id' => $request->input('property_id'),
            'user_id' => $request->input('user_id'),
            'ownership_percent' => $request->input('ownership_percent'),
        ]);
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
            'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        if (! $property->landlords()->whereKey($data['user_id'])->exists()) {
            return redirect()
                ->route('property.properties.edit', $property->id)
                ->withErrors(['user_id' => __('That landlord is not linked to this property.')])
                ->withInput();
        }

        $newPct = (float) $data['ownership_percent'];
        $others = (float) $property->landlords()
            ->where('users.id', '!=', $data['user_id'])
            ->sum('property_landlord.ownership_percent');

        if ($others + $newPct > 100.0001) {
            return redirect()
                ->route('property.properties.edit', $property->id)
                ->withErrors(['ownership_percent' => __('Total ownership for this property cannot exceed 100%.')])
                ->withInput();
        }

        $property->landlords()->updateExistingPivot($data['user_id'], [
            'ownership_percent' => $newPct,
        ]);

        return redirect()
            ->route('property.properties.edit', $property->id)
            ->with('success', __('Ownership % updated.'));
    }

    public function propertyPerformance(Request $request)
    {
        $preset = trim((string) $request->query('preset', ''));
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, [PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_NOTICE], true)) {
            $status = '';
        }
        $trendFilter = trim((string) $request->query('trend', ''));
        if (! in_array($trendFilter, ['below_ask', 'at_or_above_ask', 'vacant'], true)) {
            $trendFilter = '';
        }
        $propertyId = (int) $request->query('property_id', 0);
        $search = trim((string) $request->query('q', ''));
        $export = strtolower(trim((string) $request->query('export', '')));

        if ($preset === 'vacant') {
            $status = PropertyUnit::STATUS_VACANT;
            $trendFilter = 'vacant';
        } elseif ($preset === 'below_ask') {
            $trendFilter = 'below_ask';
        }

        $baseQuery = PropertyUnit::query()
            ->with([
                'property',
                'leases' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE)->with('pmTenant'),
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($propertyId > 0, fn ($q) => $q->where('property_id', $propertyId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('label', 'like', '%'.$search.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('property_id')
            ->orderBy('label');

        $units = (clone $baseQuery)->get();

        $lossToLease = 0.0;
        $computed = $units->map(function (PropertyUnit $u) use (&$lossToLease) {
            $lease = $u->leases->first();
            $asking = (float) $u->rent_amount;
            $effective = $lease ? (float) $lease->monthly_rent : null;
            $delta = $effective !== null ? $effective - $asking : null;
            if ($delta !== null && $delta < 0) {
                $lossToLease += abs($delta);
            }

            $vacancyDays = ($u->status === PropertyUnit::STATUS_VACANT && $u->vacant_since)
                ? (int) $u->vacant_since->diffInDays(now())
                : 0;

            $trend = $u->status === PropertyUnit::STATUS_VACANT ? 'Vacant' : ($delta !== null && $delta < 0 ? 'Below ask' : 'At/above ask');

            return [
                'unit' => $u,
                'lease' => $lease,
                'asking' => $asking,
                'delta' => $delta,
                'vacancy_days' => $vacancyDays,
                'trend' => $trend,
            ];
        });

        $computed = $computed->when($trendFilter !== '', function ($collection) use ($trendFilter) {
            return $collection->filter(function ($row) use ($trendFilter) {
                if ($trendFilter === 'vacant') {
                    return ($row['unit']->status ?? null) === PropertyUnit::STATUS_VACANT;
                }
                if ($trendFilter === 'below_ask') {
                    return ($row['delta'] ?? null) !== null && (float) $row['delta'] < 0;
                }

                return (($row['unit']->status ?? null) !== PropertyUnit::STATUS_VACANT)
                    && ((float) ($row['delta'] ?? 0) >= 0);
            });
        })->values();

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream(
                'unit-performance',
                ['Unit', 'Property', 'Status', 'Active Tenant', 'Days Vacant', 'Asking Rent', 'Lease Rent', 'Variance', 'Trend'],
                function () use ($computed) {
                    return $computed->map(function ($row) {
                        /** @var PropertyUnit $u */
                        $u = $row['unit'];
                        $lease = $row['lease'];
                        $tenant = $lease?->pmTenant;
                        $leaseRent = $lease ? (float) $lease->monthly_rent : null;

                        return [
                            (string) $u->label,
                            (string) ($u->property->name ?? ''),
                            (string) ucfirst((string) $u->status),
                            (string) ($tenant?->name ?? '—'),
                            (string) ($row['vacancy_days'] ?? 0),
                            number_format((float) ($row['asking'] ?? 0), 2, '.', ''),
                            $leaseRent !== null ? number_format($leaseRent, 2, '.', '') : '',
                            ($row['delta'] ?? null) !== null ? number_format((float) $row['delta'], 2, '.', '') : '',
                            (string) ($row['trend'] ?? ''),
                        ];
                    });
                },
                $export
            );
        }

        $unitsPage = $computed->forPage((int) $request->query('page', 1), 50)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $unitsPage,
            $computed->count(),
            50,
            (int) $request->query('page', 1),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $rows = $unitsPage->map(function (array $row) {
            /** @var PropertyUnit $u */
            $u = $row['unit'];
            $lease = $row['lease'];
            $tenant = $lease?->pmTenant;
            $variance = $row['delta'] === null ? '—' : PropertyMoney::kes((float) $row['delta']);

            $actions = [
                '<a href="'.route('property.properties.show', $u->property_id, absolute: false).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">View property</a>',
            ];
            if ($u->status === PropertyUnit::STATUS_VACANT) {
                $actions[] = '<a href="'.route('property.tenants.leases', ['property_id' => $u->property_id, 'unit_id' => $u->id], absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Assign tenant</a>';
                $actions[] = '<a href="'.route('property.listings.create', ['selected_unit' => $u->id], absolute: false).'#listing-publish" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">Publish listing</a>';
            } elseif ($lease) {
                $actions[] = '<a href="'.route('property.leases.edit', $lease, absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Open lease</a>';
                if ($tenant?->name) {
                    $actions[] = '<a href="'.route('property.tenants.profiles', ['q' => $tenant->name], absolute: false).'" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">View tenant</a>';
                }
            }
            $actionHtml = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                implode('', $actions).
                '</div>'.
                '</details>'.
                '</div>'
            );

            return [
                $u->label,
                $u->property->name,
                ucfirst((string) $u->status),
                $tenant?->name ?? '—',
                (string) ($row['vacancy_days'] ?? 0),
                PropertyMoney::kes((float) ($row['asking'] ?? 0)),
                $lease ? PropertyMoney::kes((float) $lease->monthly_rent) : '—',
                $variance,
                (string) ($row['trend'] ?? ''),
                $actionHtml,
            ];
        })->all();

        $worst = $computed->filter(fn ($r) => (($r['unit']->status ?? null) === PropertyUnit::STATUS_VACANT))->count();
        $withLease = $computed->filter(fn ($r) => $r['lease'] !== null)->count();
        $avgVacantDays = $worst > 0
            ? round($computed->where(fn ($r) => (($r['unit']->status ?? null) === PropertyUnit::STATUS_VACANT))->avg('vacancy_days'), 1)
            : 0.0;
        $trendBreakdown = [
            'below_ask' => $computed->where('trend', 'Below ask')->count(),
            'at_or_above_ask' => $computed->where('trend', 'At/above ask')->count(),
            'vacant' => $computed->where('trend', 'Vacant')->count(),
        ];

        return view('property.agent.properties.performance', [
            'stats' => [
                ['label' => 'Loss to lease (est.)', 'value' => PropertyMoney::kes($lossToLease), 'hint' => 'Active leases below asking'],
                ['label' => 'Vacant units', 'value' => (string) $worst, 'hint' => 'Current'],
                ['label' => 'Total units', 'value' => (string) $computed->count(), 'hint' => 'Filtered'],
                ['label' => 'With active lease', 'value' => (string) $withLease, 'hint' => ''],
            ],
            'columns' => ['Unit', 'Property', 'Status', 'Active tenant', 'Days vacant', 'Asking', 'Lease rent', 'Variance', 'Trend', 'Actions'],
            'tableRows' => $rows,
            'filters' => [
                'preset' => $preset,
                'status' => $status,
                'trend' => $trendFilter,
                'property_id' => $propertyId > 0 ? (string) $propertyId : '',
                'q' => $search,
            ],
            'propertyOptions' => Property::query()
                ->whereIn('id', PropertyUnit::query()->select('property_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
            'trendBreakdown' => $trendBreakdown,
            'avgVacantDays' => $avgVacantDays,
            'unitsPage' => $paginator,
        ]);
    }

    public function landlordsIndex(Request $request): View|StreamedResponse
    {
        $month = (string) $request->query('month', '');
        $fy = (int) $request->query('fy', now()->year);
        if ($fy < 2000 || $fy > 2100) {
            $fy = (int) now()->year;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $periodLabel = $periodStart->format('M Y');
        } else {
            $periodStart = Carbon::create($fy, 1, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfYear();
            $periodLabel = 'FY '.$fy;
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'linked' => (string) $request->query('linked', 'all'),
            'property_id' => (int) $request->query('property_id', 0),
            'share_level' => (string) $request->query('share_level', 'all'),
        ];

        $landlords = $this->landlordUsersQueryForActor($request->user())
            ->with(['landlordProperties' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $links = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->select([
                'pl.user_id',
                'pl.property_id',
                'pl.ownership_percent',
                'u.name as owner_name',
                'u.email as owner_email',
                'p.name as property_name',
            ])
            ->get();

        $collectedByProperty = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
            ->pluck('total', 'property_id');

        $pendingByProperty = DB::table('pm_invoices as i')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->whereDate('i.issue_date', '<=', $periodEnd->toDateString())
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as total')
            ->pluck('total', 'property_id');

        $lastPaidByProperty = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, MAX(pay.paid_at) as last_paid_at')
            ->pluck('last_paid_at', 'property_id');

        $commissionDefaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $commissionDefaultPct = is_numeric($commissionDefaultRaw) ? (float) $commissionDefaultRaw : 10.0;
        if ($commissionDefaultPct < 0) {
            $commissionDefaultPct = 0.0;
        }
        $commissionOverridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $commissionOverrides = [];
        $decodedOverrides = json_decode($commissionOverridesRaw, true);
        if (is_array($decodedOverrides)) {
            foreach ($decodedOverrides as $propertyId => $pct) {
                $pid = (int) $propertyId;
                if ($pid <= 0 || ! is_numeric($pct)) {
                    continue;
                }
                $commissionOverrides[$pid] = max(0.0, (float) $pct);
            }
        }

        $statsByLandlord = [];
        foreach ($links as $link) {
            $uid = (int) $link->user_id;
            $pid = (int) $link->property_id;
            $pct = ((float) $link->ownership_percent) / 100;
            $baseCollected = ((float) ($collectedByProperty[$pid] ?? 0)) * $pct;
            $basePending = ((float) ($pendingByProperty[$pid] ?? 0)) * $pct;
            $commissionPct = $commissionOverrides[$pid] ?? $commissionDefaultPct;
            $commission = $baseCollected * ($commissionPct / 100);

            if (! isset($statsByLandlord[$uid])) {
                $statsByLandlord[$uid] = [
                    'linked_count' => 0,
                    'ownership_sum' => 0.0,
                    'available_share' => 0.0,
                    'pending_share' => 0.0,
                    'agent_earning' => 0.0,
                    'last_paid_at' => null,
                ];
            }
            $statsByLandlord[$uid]['linked_count']++;
            $statsByLandlord[$uid]['ownership_sum'] += (float) $link->ownership_percent;
            $statsByLandlord[$uid]['available_share'] += $baseCollected;
            $statsByLandlord[$uid]['pending_share'] += $basePending;
            $statsByLandlord[$uid]['agent_earning'] += $commission;

            $lp = $lastPaidByProperty[$pid] ?? null;
            if ($lp && (! $statsByLandlord[$uid]['last_paid_at'] || (string) $lp > (string) $statsByLandlord[$uid]['last_paid_at'])) {
                $statsByLandlord[$uid]['last_paid_at'] = (string) $lp;
            }
        }

        $landlords = $landlords->map(function (User $u) use ($statsByLandlord) {
            $s = $statsByLandlord[$u->id] ?? null;
            $u->setAttribute('linked_count', (int) ($s['linked_count'] ?? 0));
            $u->setAttribute('ownership_sum', (float) ($s['ownership_sum'] ?? 0));
            $u->setAttribute('available_share', (float) ($s['available_share'] ?? 0));
            $u->setAttribute('pending_share', (float) ($s['pending_share'] ?? 0));
            $u->setAttribute('agent_earning', (float) ($s['agent_earning'] ?? 0));
            $u->setAttribute('last_paid_at', $s['last_paid_at'] ?? null);

            return $u;
        });

        if ($filters['q'] !== '') {
            $q = mb_strtolower($filters['q']);
            $landlords = $landlords->filter(function (User $u) use ($q) {
                $props = $u->landlordProperties->pluck('name')->join(' ');
                $hay = mb_strtolower(trim($u->name.' '.$u->email.' '.$props));

                return str_contains($hay, $q);
            });
        }

        if ($filters['linked'] === 'linked') {
            $landlords = $landlords->filter(fn (User $u) => (int) $u->linked_count > 0);
        } elseif ($filters['linked'] === 'unlinked') {
            $landlords = $landlords->filter(fn (User $u) => (int) $u->linked_count === 0);
        }

        if ($filters['property_id'] > 0) {
            $pid = $filters['property_id'];
            $landlords = $landlords->filter(fn (User $u) => $u->landlordProperties->contains('id', $pid));
        }

        if ($filters['share_level'] === 'high') {
            $landlords = $landlords->filter(fn (User $u) => (float) $u->ownership_sum >= 100);
        } elseif ($filters['share_level'] === 'medium') {
            $landlords = $landlords->filter(fn (User $u) => (float) $u->ownership_sum >= 30 && (float) $u->ownership_sum < 100);
        } elseif ($filters['share_level'] === 'low') {
            $landlords = $landlords->filter(fn (User $u) => (float) $u->ownership_sum > 0 && (float) $u->ownership_sum < 30);
        }

        $landlords = $landlords->values();
        $linked = $landlords->filter(fn (User $u) => (int) $u->linked_count > 0);
        $agentEarningTotal = (float) $landlords->sum(fn (User $u) => (float) $u->agent_earning);
        $ownerShareTotal = (float) $landlords->sum(fn (User $u) => (float) $u->available_share);

        $stats = [
            ['label' => 'Landlord accounts', 'value' => (string) $landlords->count(), 'hint' => 'Filtered result'],
            ['label' => 'Linked to properties', 'value' => (string) $linked->count(), 'hint' => 'At least one building'],
            ['label' => 'Not linked yet', 'value' => (string) ($landlords->count() - $linked->count()), 'hint' => 'Use link form on property list'],
            ['label' => 'Owner share ('.$periodLabel.')', 'value' => PropertyMoney::kes($ownerShareTotal), 'hint' => 'Allocated collections'],
            ['label' => 'Your earnings ('.$periodLabel.')', 'value' => PropertyMoney::kes($agentEarningTotal), 'hint' => 'Commission estimate'],
        ];

        if ((string) $request->query('export', '') === 'csv') {
            return CsvExport::stream(
                'landlords_'.now()->format('Ymd_His').'.csv',
                ['ID', 'Name', 'Email', 'Properties Linked', 'Ownership %', 'Owner Share', 'Pending Share', 'Agent Earning', 'Last Collection', 'Buildings'],
                function () use ($landlords) {
                    foreach ($landlords as $u) {
                        yield [
                            $u->id,
                            $u->name,
                            $u->email,
                            (int) ($u->linked_count ?? 0),
                            number_format((float) ($u->ownership_sum ?? 0), 2),
                            (float) ($u->available_share ?? 0),
                            (float) ($u->pending_share ?? 0),
                            (float) ($u->agent_earning ?? 0),
                            ! empty($u->last_paid_at) ? Carbon::parse((string) $u->last_paid_at)->format('Y-m-d') : null,
                            $u->landlordProperties->pluck('name')->join(', '),
                        ];
                    }
                }
            );
        }

        return view('property.agent.landlords.index', [
            'stats' => $stats,
            'landlords' => $landlords,
            'landlordFields' => $this->landlordFieldConfig(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'periodLabel' => $periodLabel,
            'monthValue' => $month,
            'fyValue' => $fy,
            'filters' => $filters,
            'commissionPct' => $commissionDefaultPct,
        ]);
    }

    /**
     * @return array{
     *   periodLabel:string,
     *   monthValue:string,
     *   fyValue:int,
     *   commissionPct:float,
     *   totals:array<string,mixed>,
     *   propertyBreakdown:\Illuminate\Support\Collection<int,array<string,mixed>>,
     *   recentCollections:\Illuminate\Support\Collection<int,object>
     * }
     */
    private function buildLandlordSnapshot(User $landlord, string $month, int $fy): array
    {
        if ($fy < 2000 || $fy > 2100) {
            $fy = (int) now()->year;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $periodLabel = $periodStart->format('M Y');
        } else {
            $periodStart = Carbon::create($fy, 1, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfYear();
            $periodLabel = 'FY '.$fy;
        }

        $landlord->load(['landlordProperties' => fn ($q) => $q->orderBy('name')]);

        $propertyLinks = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->where('pl.user_id', $landlord->id)
            ->select([
                'pl.property_id',
                'pl.ownership_percent',
                'p.name as property_name',
            ])
            ->orderBy('p.name')
            ->get();

        $propertyIds = $propertyLinks->pluck('property_id')->map(fn ($id) => (int) $id)->all();

        $collectedByProperty = collect();
        $pendingByProperty = collect();
        $lastPaidByProperty = collect();
        if ($propertyIds !== []) {
            $collectedByProperty = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
                ->pluck('total', 'property_id');

            $pendingByProperty = DB::table('pm_invoices as i')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->whereDate('i.issue_date', '<=', $periodEnd->toDateString())
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as total')
                ->pluck('total', 'property_id');

            $lastPaidByProperty = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, MAX(pay.paid_at) as last_paid_at')
                ->pluck('last_paid_at', 'property_id');
        }

        $commissionDefaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $commissionDefaultPct = is_numeric($commissionDefaultRaw) ? (float) $commissionDefaultRaw : 10.0;
        if ($commissionDefaultPct < 0) {
            $commissionDefaultPct = 0.0;
        }
        $commissionOverridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $commissionOverrides = [];
        $decodedOverrides = json_decode($commissionOverridesRaw, true);
        if (is_array($decodedOverrides)) {
            foreach ($decodedOverrides as $propertyId => $pct) {
                $pid = (int) $propertyId;
                if ($pid <= 0 || ! is_numeric($pct)) {
                    continue;
                }
                $commissionOverrides[$pid] = max(0.0, (float) $pct);
            }
        }

        $propertyBreakdown = $propertyLinks->map(function ($link) use ($collectedByProperty, $pendingByProperty, $lastPaidByProperty, $commissionDefaultPct, $commissionOverrides) {
            $pid = (int) $link->property_id;
            $pct = ((float) $link->ownership_percent) / 100;
            $grossCollected = (float) ($collectedByProperty[$pid] ?? 0);
            $grossPending = (float) ($pendingByProperty[$pid] ?? 0);
            $ownerShare = $grossCollected * $pct;
            $pendingShare = $grossPending * $pct;
            $commissionPct = $commissionOverrides[$pid] ?? $commissionDefaultPct;
            $agentEarning = $ownerShare * ($commissionPct / 100);

            return [
                'property_id' => $pid,
                'property_name' => (string) $link->property_name,
                'ownership_percent' => (float) $link->ownership_percent,
                'owner_share' => $ownerShare,
                'pending_share' => $pendingShare,
                'agent_earning' => $agentEarning,
                'last_paid_at' => $lastPaidByProperty[$pid] ?? null,
            ];
        })->values();

        $recentCollections = collect();
        if ($propertyIds !== []) {
            $recentCollections = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->join('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->orderByDesc('pay.paid_at')
                ->limit(20)
                ->select([
                    'pay.paid_at',
                    'pay.external_ref',
                    'pay.channel',
                    'a.amount',
                    'pu.property_id',
                    't.name as tenant_name',
                ])
                ->get();
        }

        $totals = [
            'properties' => (int) $propertyBreakdown->count(),
            'ownership_sum' => (float) $propertyBreakdown->sum('ownership_percent'),
            'owner_share' => (float) $propertyBreakdown->sum('owner_share'),
            'pending_share' => (float) $propertyBreakdown->sum('pending_share'),
            'agent_earning' => (float) $propertyBreakdown->sum('agent_earning'),
        ];

        return [
            'periodLabel' => $periodLabel,
            'monthValue' => $month,
            'fyValue' => $fy,
            'commissionPct' => $commissionDefaultPct,
            'totals' => $totals,
            'propertyBreakdown' => $propertyBreakdown,
            'recentCollections' => $recentCollections,
        ];
    }

    public function landlordsShow(Request $request, User $landlord): View|StreamedResponse
    {
        $this->ensureLandlordVisibleForActor($request->user(), $landlord);

        $month = (string) $request->query('month', '');
        $fy = (int) $request->query('fy', now()->year);
        $snapshot = $this->buildLandlordSnapshot($landlord, $month, $fy);

        $export = $request->string('export')->toString();
        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream(
                'landlord-'.$landlord->id.'-snapshot',
                [
                    'Landlord Name', 'Landlord Email', 'Period', 'Property', 'Ownership %', 'Owner Share', 'Pending Share', 'Agent Earning', 'Last Collection',
                ],
                function () use ($landlord, $snapshot) {
                    return $snapshot['propertyBreakdown']->map(function (array $row) use ($landlord, $snapshot) {
                        return [
                            (string) $landlord->name,
                            (string) $landlord->email,
                            (string) $snapshot['periodLabel'],
                            (string) ($row['property_name'] ?? ''),
                            (string) number_format((float) ($row['ownership_percent'] ?? 0), 2),
                            (string) number_format((float) ($row['owner_share'] ?? 0), 2, '.', ''),
                            (string) number_format((float) ($row['pending_share'] ?? 0), 2, '.', ''),
                            (string) number_format((float) ($row['agent_earning'] ?? 0), 2, '.', ''),
                            ! empty($row['last_paid_at']) ? Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '',
                        ];
                    });
                },
                $export
            );
        }

        return view('property.agent.landlords.show', [
            'landlord' => $landlord,
            ...$snapshot,
        ]);
    }

    public function landlordsStatement(Request $request, User $landlord): View
    {
        $this->ensureLandlordVisibleForActor($request->user(), $landlord);

        $month = (string) $request->query('month', '');
        $fy = (int) $request->query('fy', now()->year);
        $snapshot = $this->buildLandlordSnapshot($landlord, $month, $fy);

        return view('property.agent.landlords.statement', [
            'landlord' => $landlord,
            ...$snapshot,
        ]);
    }

    public function onboardLandlord(Request $request): RedirectResponse
    {
        $landlordFields = $this->landlordFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($landlordFields, 'name')), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf($this->isFieldRequired($landlordFields, 'email')), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'property_id' => ['nullable', 'exists:properties,id'],
            'ownership_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $plainPassword = $data['password'];
        $landlord = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'property_portal_role' => 'landlord',
        ]);
        if ($this->isAgentActor($request->user()) && Schema::hasColumn('users', 'agent_user_id')) {
            $landlord->forceFill(['agent_user_id' => (int) $request->user()->id])->save();
        }

        if (! empty($data['property_id'])) {
            $property = Property::query()->findOrFail((int) $data['property_id']);
            $pct = (float) ($data['ownership_percent'] ?? 100);

            $currentSum = (float) $property->landlords()->sum('property_landlord.ownership_percent');
            if ($currentSum + $pct > 100.0001) {
                $landlord->delete();

                return back()->withErrors([
                    'ownership_percent' => __('Total ownership for this property would exceed 100%.'),
                ])->withInput();
            }

            $property->landlords()->attach($landlord->id, ['ownership_percent' => $pct]);
        }

        $mailWarning = null;
        try {
            Mail::to($landlord->email)->send(new LandlordPortalCredentialsMail(
                landlordName: $landlord->name,
                email: $landlord->email,
                plainPassword: $plainPassword,
                loginUrl: route('login'),
                landlordHomeUrl: route('property.landlord.portfolio'),
            ));
        } catch (Throwable) {
            $mailWarning = ' Landlord account created, but credential email was not sent (check mail settings).';
        }

        $message = $mailWarning === null
            ? 'Landlord onboarded successfully. Credentials email sent.'
            : 'Landlord onboarded successfully.'.$mailWarning;

        $nextSteps = [
            'title' => 'Landlord onboarded',
            'message' => 'Next, link this landlord to properties and review their owner balance and commission.',
            'landlord' => [
                'id' => $landlord->id,
                'name' => $landlord->name,
                'email' => $landlord->email,
            ],
            'actions' => [
                [
                    'label' => 'Link to property now',
                    'href' => route('property.properties.list', absolute: false).'#link-landlord-form',
                    'kind' => 'primary',
                    'icon' => 'fa-solid fa-link',
                    'turbo_frame' => 'property-main',
                ],
                [
                    'label' => 'View landlord profile',
                    'href' => route('property.landlords.index', absolute: false),
                    'kind' => 'secondary',
                    'icon' => 'fa-solid fa-user-tie',
                    'turbo_frame' => 'property-main',
                ],
            ],
        ];

        return back()
            ->with('success', $message)
            ->with('next_steps', $nextSteps);
    }

    public function onboardLandlordJson(Request $request)
    {
        $landlordFields = $this->landlordFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($landlordFields, 'name')), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf($this->isFieldRequired($landlordFields, 'email')), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $plainPassword = (string) $data['password'];
        $landlord = User::query()->create([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'password' => Hash::make($plainPassword),
            'property_portal_role' => 'landlord',
        ]);
        if ($this->isAgentActor($request->user()) && Schema::hasColumn('users', 'agent_user_id')) {
            $landlord->forceFill(['agent_user_id' => (int) $request->user()->id])->save();
        }

        // Email sending is best-effort; UI will still proceed even if mail isn't configured.
        $mailOk = true;
        try {
            Mail::to($landlord->email)->send(new LandlordPortalCredentialsMail(
                landlordName: $landlord->name,
                email: $landlord->email,
                plainPassword: $plainPassword,
                loginUrl: route('login'),
                landlordHomeUrl: route('property.landlord.portfolio'),
            ));
        } catch (Throwable) {
            $mailOk = false;
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $landlord->id,
                'name' => $landlord->name,
                'email' => $landlord->email,
            ],
            'message' => $mailOk
                ? 'Landlord created. Credentials email sent.'
                : 'Landlord created. Email not sent (check mail settings).',
        ]);
    }

    public function storeProperty(Request $request): RedirectResponse
    {
        $propertyFields = $this->propertyOnboardingFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($propertyFields, 'name')), 'nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'unique:properties,code'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'charge_templates' => ['nullable', 'array', 'max:50'],
            'charge_templates.*.property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            'charge_templates.*.charge_type' => ['nullable', 'string', 'max:64'],
            'charge_templates.*.label' => ['nullable', 'string', 'max:128'],
            'charge_templates.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $commissionPercent = isset($data['commission_percent']) ? (float) $data['commission_percent'] : null;
        $chargeTemplates = $this->normalizePropertyChargeTemplates((array) ($data['charge_templates'] ?? []));
        unset($data['commission_percent']);
        unset($data['charge_templates']);

        if (!isset($data['code']) || trim((string) $data['code']) === '') {
            $data['code'] = $this->generateUniquePropertyCode($data['name']);
        }
        $data['agent_user_id'] = (int) $request->user()->id;

        $property = Property::query()->create($data);
        $this->setPropertyCommissionOverride((int) $property->id, $commissionPercent);
        $this->setPropertyChargeTemplates((int) $property->id, $chargeTemplates);
        $this->syncExpenseRulesFromUtilityTemplates((int) $property->id, $chargeTemplates);

        return back()
            ->with('success', 'Property saved.')
            ->with('next_steps', [
                'title' => 'Property saved',
                'message' => 'Next, add units (doors), then link the landlord user, then publish vacant units under Listings.',
                'actions' => [
                    [
                        'label' => 'Add units',
                        'href' => route('property.properties.units', ['property_id' => $property->id], absolute: false),
                        'kind' => 'primary',
                        'icon' => 'fa-solid fa-building',
                        'turbo_frame' => 'property-main',
                    ],
                    [
                        'label' => 'Link landlord user',
                        'href' => route('property.properties.list', ['property_id' => $property->id], absolute: false).'#link-landlord-form',
                        'kind' => 'secondary',
                        'icon' => 'fa-solid fa-user-tie',
                        'turbo_frame' => 'property-main',
                    ],
                    [
                        'label' => 'Go to Listings',
                        'href' => route('property.listings.index', absolute: false),
                        'kind' => 'ghost',
                        'icon' => 'fa-solid fa-bullhorn',
                        'turbo_frame' => 'property-main',
                    ],
                ],
            ]);
    }

    public function storePropertyJson(Request $request)
    {
        $propertyFields = $this->propertyOnboardingFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($propertyFields, 'name')), 'nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'unique:properties,code'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'charge_templates' => ['nullable', 'array', 'max:50'],
            'charge_templates.*.property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            'charge_templates.*.charge_type' => ['nullable', 'string', 'max:64'],
            'charge_templates.*.label' => ['nullable', 'string', 'max:128'],
            'charge_templates.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'charge_templates.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $commissionPercent = isset($data['commission_percent']) ? (float) $data['commission_percent'] : null;
        $chargeTemplates = $this->normalizePropertyChargeTemplates((array) ($data['charge_templates'] ?? []));
        unset($data['commission_percent']);
        unset($data['charge_templates']);

        if (!isset($data['code']) || trim((string) $data['code']) === '') {
            $data['code'] = $this->generateUniquePropertyCode($data['name']);
        }
        $data['agent_user_id'] = (int) $request->user()->id;

        $property = Property::query()->create($data);
        $this->setPropertyCommissionOverride((int) $property->id, $commissionPercent);
        $this->setPropertyChargeTemplates((int) $property->id, $chargeTemplates);
        $this->syncExpenseRulesFromUtilityTemplates((int) $property->id, $chargeTemplates);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $property->id,
                'label' => $property->name,
            ],
            'message' => 'Property created.',
        ]);
    }

    public function unitList(Request $request): View
    {
        $filters = $request->only([
            'q', 'property_id', 'status', 'unit_type', 'beds_min', 'beds_max', 'rent_min', 'rent_max', 'sort', 'dir',
        ]);
        $query = PropertyUnit::query()->with([
            'property',
            'leases' => function ($q) {
                $q->where('pm_leases.status', \App\Models\PmLease::STATUS_ACTIVE)
                    ->with('pmTenant:id,name')
                    ->orderBy('pm_leases.start_date')
                    ->orderBy('pm_leases.id');
            },
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', '%'.$search.'%')
                    ->orWhere('unit_type', 'like', '%'.$search.'%')
                    ->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$search.'%'));
            });
        }

        $propertyId = (int) ($filters['property_id'] ?? 0);
        if ($propertyId > 0) {
            $query->where('property_id', $propertyId);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_NOTICE], true)) {
            $query->where('status', $status);
        }

        $unitType = trim((string) ($filters['unit_type'] ?? ''));
        if ($unitType !== '') {
            $query->where('unit_type', $unitType);
        }

        $bedsMin = is_numeric($filters['beds_min'] ?? null) ? (int) $filters['beds_min'] : null;
        $bedsMax = is_numeric($filters['beds_max'] ?? null) ? (int) $filters['beds_max'] : null;
        if ($bedsMin !== null) {
            $query->where('bedrooms', '>=', $bedsMin);
        }
        if ($bedsMax !== null) {
            $query->where('bedrooms', '<=', $bedsMax);
        }

        $rentMin = is_numeric($filters['rent_min'] ?? null) ? (float) $filters['rent_min'] : null;
        $rentMax = is_numeric($filters['rent_max'] ?? null) ? (float) $filters['rent_max'] : null;
        if ($rentMin !== null) {
            $query->where('rent_amount', '>=', $rentMin);
        }
        if ($rentMax !== null) {
            $query->where('rent_amount', '<=', $rentMax);
        }

        $sort = (string) ($filters['sort'] ?? 'property_id');
        $dir = strtolower((string) ($filters['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['property_id', 'label', 'rent_amount', 'status', 'bedrooms', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'property_id';
        }
        $query->orderBy($sort, $dir)->orderBy('label');

        $perPage = min(200, max(10, (int) $request->integer('per_page', 30)));
        $units = $query->paginate($perPage)->withQueryString();
        $unitCollection = $units->getCollection();

        $stats = [
            ['label' => 'Units', 'value' => (string) $units->total(), 'hint' => 'Total'],
            ['label' => 'Occupied', 'value' => (string) $unitCollection->where('status', PropertyUnit::STATUS_OCCUPIED)->count(), 'hint' => 'This page'],
            ['label' => 'Vacant', 'value' => (string) $unitCollection->where('status', PropertyUnit::STATUS_VACANT)->count(), 'hint' => 'This page'],
            ['label' => 'Listed rent (avg)', 'value' => $unitCollection->count() ? PropertyMoney::kes($unitCollection->avg('rent_amount')) : PropertyMoney::kes(0), 'hint' => 'This page'],
        ];

        $rows = $unitCollection->map(function (PropertyUnit $u) {
            $activeLease = $u->leases->first();
            $activeTenantName = (string) ($activeLease?->pmTenant?->name ?? '');
            $actions = [
                '<a href="'.route('property.properties.show', $u->property_id, absolute: false).'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">View property</a>',
                '<a href="'.route('property.units.edit', $u, absolute: false).'" data-turbo="false" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">Edit unit</a>',
                // Quick path to onboarding or takeover: create a lease (often future-dated) for this unit
                '<a href="'.route('property.tenants.leases', ['property_id' => $u->property_id], absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Add lease</a>',
            ];

            if ($u->status === PropertyUnit::STATUS_VACANT) {
                $actions[] = '<a href="'.route('property.listings.create', ['selected_unit' => $u->id], absolute: false).'#listing-publish" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">Edit listing</a>';
            }

            foreach ([PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_NOTICE] as $targetStatus) {
                if ($targetStatus === $u->status) {
                    continue;
                }
                $actions[] = '<form method="POST" action="'.route('property.units.status', $u, absolute: false).'">'
                    .csrf_field()
                    .'<input type="hidden" name="status" value="'.$targetStatus.'" />'
                    .'<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50">Mark '.ucfirst($targetStatus).'</button>'
                    .'</form>';
            }

            $actions[] = '<form method="POST" action="'.route('property.units.destroy', $u, absolute: false).'" data-swal-title="Delete unit?" data-swal-confirm="Delete '.$u->label.' from '.$u->property->name.'? This cannot be undone." data-swal-confirm-text="Yes, delete">'
                .csrf_field()
                .method_field('DELETE')
                .'<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>'
                .'</form>';

            $action = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                implode('', $actions).
                '</div>'.
                '</details>'.
                '</div>'
            );

            return [
                $u->label,
                $u->property->name,
                $u->unitTypeLabel(),
                $u->bedroomsLabel(),
                PropertyMoney::kes((float) $u->rent_amount),
                ucfirst($u->status),
                $activeTenantName !== ''
                    ? $activeTenantName
                    : ($u->status === PropertyUnit::STATUS_OCCUPIED ? 'No active lease' : '—'),
                $u->vacant_since?->format('Y-m-d') ?? '—',
                $action,
            ];
        })->all();

        return view('property.agent.properties.units', [
            'stats' => $stats,
            'columns' => ['Unit', 'Property', 'Type', 'Beds', 'Rent', 'Status', 'Tenant', 'Vacant since', 'Actions'],
            'tableRows' => $rows,
            'unitFields' => $this->unitFieldConfig(),
            'paginator' => $units,
            'perPage' => $perPage,
            'properties' => Property::query()
                ->whereDoesntHave('units')
                ->orderBy('name')
                ->get(),
            'allProperties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'unitTypes' => $this->propertyUnitTypeOptions(),
            'bedroomOptionsByType' => $this->propertyBedroomOptionsByType(),
            'filters' => $filters,
        ]);
    }

    private function propertyCommissionPercent(int $propertyId): float
    {
        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $defaultPct = is_numeric($defaultRaw) ? max(0.0, (float) $defaultRaw) : 10.0;

        $overridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($overridesRaw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        $value = $overrides[(string) $propertyId] ?? null;
        if (! is_numeric($value)) {
            return $defaultPct;
        }

        return max(0.0, (float) $value);
    }

    private function setPropertyCommissionOverride(int $propertyId, ?float $percent): void
    {
        $raw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($raw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        if ($percent === null) {
            unset($overrides[(string) $propertyId]);
        } else {
            $overrides[(string) $propertyId] = max(0.0, round($percent, 2));
        }

        PropertyPortalSetting::setValue(
            'commission_property_overrides_json',
            json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array{property_unit_id:int|null,charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>
     */
    private function normalizePropertyChargeTemplates(array $templates): array
    {
        $normalized = [];
        foreach ($templates as $row) {
            $propertyUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;
            $rawChargeType = strtolower(trim((string) ($row['charge_type'] ?? '')));
            $chargeType = (string) Str::of($rawChargeType)
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_');
            if ($chargeType === '') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $rate = is_numeric($row['rate_per_unit'] ?? null) ? max(0.0, (float) $row['rate_per_unit']) : 0.0;
            $fixed = is_numeric($row['fixed_charge'] ?? null) ? max(0.0, (float) $row['fixed_charge']) : 0.0;
            $notes = trim((string) ($row['notes'] ?? ''));
            if ($label === '' && $rate <= 0.0 && $fixed <= 0.0 && $notes === '') {
                continue;
            }
            $normalized[] = [
                'property_unit_id' => $propertyUnitId,
                'charge_type' => $chargeType,
                'label' => $label !== '' ? $label : ucfirst($chargeType),
                'rate_per_unit' => round($rate, 2),
                'fixed_charge' => round($fixed, 2),
                'notes' => Str::limit($notes, 500, ''),
            ];
        }

        return array_slice($normalized, 0, 50);
    }

    /**
     * @param  array<int, array{property_unit_id:int|null,charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>  $templates
     */
    private function setPropertyChargeTemplates(int $propertyId, array $templates): void
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];

        if ($templates === []) {
            unset($all[(string) $propertyId]);
        } else {
            $all[(string) $propertyId] = array_values($templates);
        }

        PropertyPortalSetting::setValue(
            'utility_property_charge_templates_json',
            json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Mirror utility templates into normalized expense rules.
     *
     * @param  array<int, array{property_unit_id:int|null,charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>  $templates
     */
    private function syncExpenseRulesFromUtilityTemplates(int $propertyId, array $templates): void
    {
        if (! Schema::hasTable('expense_definitions')) {
            return;
        }

        ExpenseDefinition::query()->where('property_id', $propertyId)->delete();

        foreach (array_values($templates) as $index => $template) {
            $chargeKey = (string) Str::of((string) ($template['charge_type'] ?? ''))
                ->lower()
                ->replaceMatches('/[^a-z0-9_]+/i', '_')
                ->trim('_');
            if ($chargeKey === '') {
                continue;
            }

            $label = trim((string) ($template['label'] ?? ''));
            $rate = is_numeric($template['rate_per_unit'] ?? null) ? max(0.0, (float) $template['rate_per_unit']) : 0.0;
            $fixed = is_numeric($template['fixed_charge'] ?? null) ? max(0.0, (float) $template['fixed_charge']) : 0.0;
            $isRatePerUnit = $rate > 0.0 && $fixed <= 0.0;
            $defaultAmount = $isRatePerUnit ? $rate : $fixed;

            ExpenseDefinition::query()->create([
                'property_id' => $propertyId,
                'property_unit_id' => isset($template['property_unit_id']) && $template['property_unit_id'] !== '' ? (int) $template['property_unit_id'] : null,
                'charge_key' => $chargeKey,
                'label' => $label !== '' ? Str::limit($label, 120, '') : Str::of($chargeKey)->replace('_', ' ')->title()->toString(),
                'is_required' => false,
                'amount_mode' => $isRatePerUnit ? ExpenseDefinition::MODE_RATE_PER_UNIT : ExpenseDefinition::MODE_FLAT_CHARGE,
                'amount_value' => round($defaultAmount, 2),
                'ledger_account' => null,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<int, array{property_unit_id:int|null,charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>
     */
    private function propertyChargeTemplates(int $propertyId): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];
        $rows = $all[(string) $propertyId] ?? [];

        return $this->normalizePropertyChargeTemplates(is_array($rows) ? $rows : []);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizePropertyDepositDefinitions(int $propertyId, array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $depositKey = (string) Str::of((string) ($row['deposit_key'] ?? ''))
                ->lower()
                ->replaceMatches('/[^a-z0-9_]+/i', '_')
                ->trim('_');
            $label = trim((string) ($row['label'] ?? ''));
            $amountMode = (string) ($row['amount_mode'] ?? 'fixed');
            $amountMode = in_array($amountMode, ['fixed', 'percent_rent'], true) ? $amountMode : 'fixed';
            $amountValue = is_numeric($row['amount_value'] ?? null) ? max(0, (float) $row['amount_value']) : 0.0;
            $propertyUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;

            if ($depositKey === '' || $label === '') {
                continue;
            }

            $normalized[] = [
                'property_id' => $propertyId,
                'property_unit_id' => $propertyUnitId,
                'deposit_key' => $depositKey,
                'label' => Str::limit($label, 120, ''),
                'is_required' => (string) ($row['is_required'] ?? '0') === '1',
                'amount_mode' => $amountMode,
                'amount_value' => round($amountValue, 2),
                'is_refundable' => (string) ($row['is_refundable'] ?? '1') === '1',
                'ledger_account' => ($tmp = trim((string) ($row['ledger_account'] ?? ''))) !== '' ? Str::limit($tmp, 120, '') : null,
                'sort_order' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : (int) $index,
                'is_active' => (string) ($row['is_active'] ?? '1') === '1',
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizePropertyExpenseDefinitions(int $propertyId, array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $chargeKey = (string) Str::of((string) ($row['charge_key'] ?? ''))
                ->lower()
                ->replaceMatches('/[^a-z0-9_]+/i', '_')
                ->trim('_');
            $label = trim((string) ($row['label'] ?? ''));
            $amountMode = (string) ($row['amount_mode'] ?? ExpenseDefinition::MODE_FLAT_CHARGE);
            $amountMode = in_array($amountMode, [ExpenseDefinition::MODE_FLAT_CHARGE, ExpenseDefinition::MODE_RATE_PER_UNIT], true)
                ? $amountMode
                : ExpenseDefinition::MODE_FLAT_CHARGE;
            $amountValue = is_numeric($row['amount_value'] ?? null) ? max(0, (float) $row['amount_value']) : 0.0;
            $propertyUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;

            if ($chargeKey === '' || $label === '') {
                continue;
            }

            $normalized[] = [
                'property_id' => $propertyId,
                'property_unit_id' => $propertyUnitId,
                'charge_key' => $chargeKey,
                'label' => Str::limit($label, 120, ''),
                'is_required' => (string) ($row['is_required'] ?? '0') === '1',
                'amount_mode' => $amountMode,
                'amount_value' => round($amountValue, 2),
                'ledger_account' => ($tmp = trim((string) ($row['ledger_account'] ?? ''))) !== '' ? Str::limit($tmp, 120, '') : null,
                'sort_order' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : (int) $index,
                'is_active' => (string) ($row['is_active'] ?? '1') === '1',
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int,array<string,mixed>>  $definitions
     */
    private function setPropertyDepositDefinitions(int $propertyId, array $definitions): void
    {
        if (! Schema::hasTable('deposit_definitions')) {
            return;
        }

        DepositDefinition::query()->where('property_id', $propertyId)->delete();
        foreach ($definitions as $row) {
            DepositDefinition::query()->create($row);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $definitions
     */
    private function setPropertyExpenseDefinitions(int $propertyId, array $definitions): void
    {
        if (! Schema::hasTable('expense_definitions')) {
            return;
        }

        ExpenseDefinition::query()->where('property_id', $propertyId)->delete();
        foreach ($definitions as $row) {
            ExpenseDefinition::query()->create($row);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function propertyDepositDefinitions(int $propertyId): array
    {
        if (! Schema::hasTable('deposit_definitions')) {
            return [];
        }

        return DepositDefinition::query()
            ->where('property_id', $propertyId)
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DepositDefinition $definition): array => [
                'property_unit_id' => $definition->property_unit_id,
                'deposit_key' => $definition->deposit_key,
                'label' => $definition->label,
                'is_required' => (bool) $definition->is_required,
                'amount_mode' => $definition->amount_mode,
                'amount_value' => (float) $definition->amount_value,
                'is_refundable' => (bool) $definition->is_refundable,
                'ledger_account' => $definition->ledger_account,
                'sort_order' => (int) $definition->sort_order,
                'is_active' => (bool) $definition->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function propertyExpenseDefinitions(int $propertyId): array
    {
        if (! Schema::hasTable('expense_definitions')) {
            return [];
        }

        return ExpenseDefinition::query()
            ->where('property_id', $propertyId)
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseDefinition $definition): array => [
                'property_unit_id' => $definition->property_unit_id,
                'charge_key' => $definition->charge_key,
                'label' => $definition->label,
                'is_required' => (bool) $definition->is_required,
                'amount_mode' => $definition->amount_mode,
                'amount_value' => (float) $definition->amount_value,
                'ledger_account' => $definition->ledger_account,
                'sort_order' => (int) $definition->sort_order,
                'is_active' => (bool) $definition->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array{charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>>
     */
    private function allPropertyChargeTemplates(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];

        return collect($all)
            ->mapWithKeys(function ($rows, $propertyId): array {
                $normalized = $this->normalizePropertyChargeTemplates(is_array($rows) ? $rows : []);
                return [(string) $propertyId => $normalized];
            })
            ->all();
    }

    /**
     * @param array{charge_type?:string,label?:string,rate_per_unit?:float|int|string|null,fixed_charge?:float|int|string|null} $template
     */
    private function formatChargeTemplateSummary(array $template): string
    {
        $chargeType = ucfirst(str_replace('_', ' ', (string) ($template['charge_type'] ?? 'other')));
        $label = trim((string) ($template['label'] ?? ''));
        $rate = is_numeric($template['rate_per_unit'] ?? null) ? (float) $template['rate_per_unit'] : 0.0;
        $fixed = is_numeric($template['fixed_charge'] ?? null) ? (float) $template['fixed_charge'] : 0.0;

        $parts = [];
        if ($rate > 0) {
            $parts[] = 'r '.number_format($rate, 2);
        }
        if ($fixed > 0) {
            $parts[] = 'f '.number_format($fixed, 2);
        }

        $prefix = $label !== '' ? $label : $chargeType;

        return $parts === []
            ? $prefix
            : $prefix.' ('.implode(' | ', $parts).')';
    }

    private function isAgentActor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ! (bool) ($user->is_super_admin ?? false)
            && (string) ($user->property_portal_role ?? '') === 'agent';
    }

    private function landlordUsersQueryForActor(?User $actor)
    {
        $query = User::query()->where('property_portal_role', 'landlord');
        if (! $this->isAgentActor($actor)) {
            return $query;
        }

        $agentId = (int) $actor->id;
        if (Schema::hasColumn('users', 'agent_user_id')) {
            return $query->where('agent_user_id', $agentId);
        }

        return $query->whereExists(function ($sub) use ($agentId) {
            $sub->selectRaw('1')
                ->from('property_landlord as pl')
                ->join('properties as p', 'p.id', '=', 'pl.property_id')
                ->whereColumn('pl.user_id', 'users.id')
                ->where('p.agent_user_id', $agentId);
        });
    }

    private function ensureLandlordVisibleForActor(?User $actor, User $landlord): void
    {
        if ((string) $landlord->property_portal_role !== 'landlord') {
            abort(404);
        }
        if (! $this->isAgentActor($actor)) {
            return;
        }

        $agentId = (int) $actor->id;
        if (Schema::hasColumn('users', 'agent_user_id')) {
            abort_unless((int) ($landlord->agent_user_id ?? 0) === $agentId, 404);
            return;
        }

        $visible = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->where('pl.user_id', $landlord->id)
            ->where('p.agent_user_id', $agentId)
            ->exists();
        abort_unless($visible, 404);
    }

    public function unitListExport(Request $request)
    {
        $filters = $request->only([
            'q', 'property_id', 'status', 'unit_type', 'beds_min', 'beds_max', 'rent_min', 'rent_max',
        ]);

        $query = PropertyUnit::query()->with([
            'property',
            'leases' => function ($q) {
                $q->where('pm_leases.status', \App\Models\PmLease::STATUS_ACTIVE)
                    ->with('pmTenant:id,name')
                    ->orderBy('pm_leases.start_date')
                    ->orderBy('pm_leases.id');
            },
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', '%'.$search.'%')
                    ->orWhere('unit_type', 'like', '%'.$search.'%')
                    ->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$search.'%'));
            });
        }
        $propertyId = (int) ($filters['property_id'] ?? 0);
        if ($propertyId > 0) {
            $query->where('property_id', $propertyId);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_NOTICE], true)) {
            $query->where('status', $status);
        }
        $unitType = trim((string) ($filters['unit_type'] ?? ''));
        if ($unitType !== '') {
            $query->where('unit_type', $unitType);
        }
        if (is_numeric($filters['beds_min'] ?? null)) {
            $query->where('bedrooms', '>=', (int) $filters['beds_min']);
        }
        if (is_numeric($filters['beds_max'] ?? null)) {
            $query->where('bedrooms', '<=', (int) $filters['beds_max']);
        }
        if (is_numeric($filters['rent_min'] ?? null)) {
            $query->where('rent_amount', '>=', (float) $filters['rent_min']);
        }
        if (is_numeric($filters['rent_max'] ?? null)) {
            $query->where('rent_amount', '<=', (float) $filters['rent_max']);
        }

        $rows = $query->orderBy('property_id')->orderBy('label')->get();

        return CsvExport::stream(
            'property_units_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Property', 'Unit', 'Type', 'Bedrooms', 'Rent', 'Status', 'Tenant', 'Vacant Since'],
            function () use ($rows) {
                foreach ($rows as $u) {
                    $activeLease = $u->leases->first();
                    $activeTenantName = (string) ($activeLease?->pmTenant?->name ?? '');
                    yield [
                        $u->id,
                        $u->property->name,
                        $u->label,
                        $u->unitTypeLabel(),
                        $u->bedroomsLabel(),
                        (float) $u->rent_amount,
                        $u->status,
                        $activeTenantName !== '' ? $activeTenantName : ($u->status === PropertyUnit::STATUS_OCCUPIED ? 'No active lease' : ''),
                        optional($u->vacant_since)->format('Y-m-d'),
                    ];
                }
            }
        );
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $unitFields = $this->unitFieldConfig();
        if ($request->boolean('mixed_units_mode')) {
            return $this->storeMixedUnits($request);
        }

        $data = $request->validate([
            'property_id' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'property_id')), 'nullable', 'exists:properties,id'],
            'label' => ['nullable', 'string', 'max:64'],
            'unit_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'label_prefix' => ['nullable', 'string', 'max:32'],
            'label_start' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'vacant_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'occupied_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'notice_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'status_mode' => ['nullable', 'in:single,split'],
            'unit_type' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'unit_type')), 'nullable', 'string', 'max:64'],
            // Bedrooms is conditional: some unit types have no separate bedroom and the UI disables the field.
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'rent_amount' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'rent_amount')), 'nullable', 'numeric', 'min:0'],
            'status' => [
                Rule::requiredIf(fn () => $this->isFieldRequired($unitFields, 'status') && (string) $request->input('status_mode', 'single') !== 'split'),
                'in:vacant,occupied,notice',
            ],
            'public_listing_description' => ['nullable', 'string', 'max:20000'],
        ]);

        $data['unit_type'] = $this->normalizeUnitTypeValue((string) ($data['unit_type'] ?? PropertyUnit::TYPE_APARTMENT));

        $desc = isset($data['public_listing_description']) && trim((string) $data['public_listing_description']) !== ''
            ? $data['public_listing_description']
            : null;
        $noBedroomTypes = [PropertyUnit::TYPE_SINGLE_ROOM, PropertyUnit::TYPE_BEDSITTER, PropertyUnit::TYPE_STUDIO];
        $requiresNoBedroom = in_array($data['unit_type'], $noBedroomTypes, true);
        if ($requiresNoBedroom) {
            $data['bedrooms'] = 0;
        } elseif ($this->isFieldRequired($unitFields, 'bedrooms') && !isset($data['bedrooms'])) {
            return back()
                ->withErrors(['bedrooms' => __('The bedrooms field is required.')])
                ->withInput();
        } elseif (! isset($data['bedrooms'])) {
            $data['bedrooms'] = 1;
        }

        $unitCount = (int) ($data['unit_count'] ?? 1);
        $labelStart = (int) ($data['label_start'] ?? 1);
        $labelPrefix = trim((string) ($data['label_prefix'] ?? ''));
        $baseLabel = trim((string) ($data['label'] ?? ''));
        $vacantCount = (int) ($data['vacant_count'] ?? 0);
        $occupiedCount = (int) ($data['occupied_count'] ?? 0);
        $noticeCount = (int) ($data['notice_count'] ?? 0);
        $statusMode = (string) ($data['status_mode'] ?? 'single');
        $hasStatusSplit = $statusMode === 'split';
        $labels = [];
        if ($unitCount <= 1) {
            if ($baseLabel === '') {
                return back()
                    ->withErrors(['label' => __('Label is required when saving a single unit.')])
                    ->withInput();
            }
            $labels[] = $baseLabel;
        } else {
            $numericOnlyLabels = false;
            // If user typed A1 in prefix and left label blank, auto-split to prefix A + start 1.
            if ($baseLabel === '' && $labelPrefix !== '' && preg_match('/^(.*?)(\d+)$/', $labelPrefix, $m) === 1) {
                $labelPrefix = trim((string) $m[1]);
                $labelStart = (int) $m[2];
                $numericOnlyLabels = $labelPrefix === '';
            }

            $baseLooksNumeric = preg_match('/\d/', $baseLabel) === 1;
            if ($labelPrefix === '' && ! $baseLooksNumeric) {
                $labelPrefix = $baseLabel;
            }
            // Support numeric-only labels for buildings that use doors like 1,2,3...
            if ($labelPrefix === '' && preg_match('/^\d+$/', $baseLabel) === 1) {
                $labelStart = (int) $baseLabel;
                $numericOnlyLabels = true;
            }

            if ($labelPrefix === '' && ! $numericOnlyLabels) {
                return back()
                    ->withErrors(['label_prefix' => __('Set a label prefix for bulk creation (e.g. A, B, BLOCK-1-).')])
                    ->withInput();
            }
            for ($i = 0; $i < $unitCount; $i++) {
                $labels[] = $numericOnlyLabels
                    ? (string) ($labelStart + $i)
                    : $labelPrefix.($labelStart + $i);
            }
        }

        if ($hasStatusSplit) {
            if ($unitCount <= 1) {
                return back()
                    ->withErrors(['unit_count' => __('Status split is for bulk only. Set units greater than 1.')])
                    ->withInput();
            }
            if (($vacantCount + $occupiedCount + $noticeCount) !== $unitCount) {
                return back()
                    ->withErrors(['unit_count' => __('Vacant + Occupied + Notice counts must equal total units.')])
                    ->withInput();
            }
        }

        $existing = PropertyUnit::query()
            ->where('property_id', $data['property_id'])
            ->whereIn('label', $labels)
            ->pluck('label')
            ->all();
        if ($existing !== []) {
            return back()
                ->withErrors(['label' => __('Some labels already exist for this property: :labels', ['labels' => implode(', ', array_slice($existing, 0, 10))])])
                ->withInput();
        }

        if (! $hasStatusSplit) {
            $vacantCount = 0;
            $occupiedCount = 0;
            $noticeCount = 0;
        }

        $statuses = [];
        if ($hasStatusSplit) {
            for ($i = 0; $i < $vacantCount; $i++) {
                $statuses[] = PropertyUnit::STATUS_VACANT;
            }
            for ($i = 0; $i < $occupiedCount; $i++) {
                $statuses[] = PropertyUnit::STATUS_OCCUPIED;
            }
            for ($i = 0; $i < $noticeCount; $i++) {
                $statuses[] = PropertyUnit::STATUS_NOTICE;
            }
        }

        $created = [];
        foreach ($labels as $idx => $label) {
            $rowStatus = $hasStatusSplit
                ? (string) ($statuses[$idx] ?? $data['status'])
                : (string) $data['status'];
            $created[] = PropertyUnit::query()->create([
                'property_id' => $data['property_id'],
                'label' => $label,
                'unit_type' => $data['unit_type'],
                'bedrooms' => (int) $data['bedrooms'],
                'rent_amount' => $data['rent_amount'],
                'status' => $rowStatus,
                'public_listing_description' => $desc,
                'vacant_since' => $rowStatus === PropertyUnit::STATUS_VACANT ? now()->toDateString() : null,
            ]);
        }
        $unit = $created[0];

        $actions = [
            [
                'label' => 'Add another unit',
                'href' => route('property.properties.units', ['property_id' => $unit->property_id], absolute: false),
                'kind' => 'primary',
                'icon' => 'fa-solid fa-plus',
                'turbo_frame' => 'property-main',
            ],
            [
                'label' => 'Link landlord user',
                'href' => route('property.properties.list', ['property_id' => $unit->property_id], absolute: false).'#link-landlord-form',
                'kind' => 'secondary',
                'icon' => 'fa-solid fa-user-tie',
                'turbo_frame' => 'property-main',
            ],
            [
                'label' => 'Go to Listings',
                'href' => route('property.listings.index', absolute: false),
                'kind' => 'ghost',
                'icon' => 'fa-solid fa-bullhorn',
                'turbo_frame' => 'property-main',
            ],
        ];

        if ($unit->status === PropertyUnit::STATUS_VACANT) {
            array_unshift($actions, [
                'label' => 'Edit listing (vacant unit)',
                'href' => route('property.listings.create', ['selected_unit' => $unit->id], absolute: false).'#listing-publish',
                'kind' => 'primary',
                'icon' => 'fa-solid fa-pen-to-square',
                'turbo_frame' => 'property-main',
            ]);
        }

        $savedMessage = $unitCount > 1
            ? 'Units saved: '.$unitCount.'.'
            : 'Unit saved.';

        return back()
            ->with('success', $savedMessage)
            ->with('next_steps', [
                'title' => 'Unit saved',
                'message' => $unit->status === PropertyUnit::STATUS_VACANT
                    ? ($unitCount > 1
                        ? 'These units are vacant. You can now add photos and publish selected ones under Listings.'
                        : 'This unit is vacant. You can now add photos and publish it under Listings.')
                    : 'Next, add more units, link the landlord, or manage listings for vacant units.',
                'actions' => $actions,
            ]);
    }

    public function updateUnitStatus(Request $request, PropertyUnit $unit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:vacant,occupied,notice'],
        ]);

        $status = (string) ($data['status'] ?? PropertyUnit::STATUS_VACANT);
        $hasActiveLease = $unit->leases()->where('pm_leases.status', PmLease::STATUS_ACTIVE)->exists();
        if ($status === PropertyUnit::STATUS_OCCUPIED && ! $hasActiveLease) {
            return back()->withErrors([
                'status' => 'Cannot mark unit occupied without an active lease. Create/activate a lease first.',
            ]);
        }
        if ($status === PropertyUnit::STATUS_VACANT && $hasActiveLease) {
            return back()->withErrors([
                'status' => 'Cannot mark unit vacant while it still has an active lease.',
            ]);
        }

        $unit->update([
            'status' => $status,
            'vacant_since' => $status === PropertyUnit::STATUS_VACANT ? ($unit->vacant_since?->toDateString() ?? now()->toDateString()) : null,
        ]);

        if ($status !== PropertyUnit::STATUS_VACANT) {
            $unit->update(['public_listing_published' => false]);
        }

        return back()->with('success', 'Unit status updated.');
    }

    public function editUnit(PropertyUnit $unit): View
    {
        $unit->loadMissing('property');

        return view('property.agent.properties.edit_unit', [
            'unit' => $unit,
            'unitFields' => $this->unitFieldConfig(),
            'unitTypes' => $this->propertyUnitTypeOptions((string) $unit->unit_type),
            'bedroomOptionsByType' => $this->propertyBedroomOptionsByType((string) $unit->unit_type, (int) $unit->bedrooms),
        ]);
    }

    public function updateUnit(Request $request, PropertyUnit $unit): RedirectResponse
    {
        $unitFields = $this->unitFieldConfig();
        $data = $request->validate([
            'label' => [
                Rule::requiredIf($this->isFieldRequired($unitFields, 'label')),
                'string',
                'max:64',
                Rule::unique('property_units', 'label')
                    ->where(fn ($q) => $q->where('property_id', $unit->property_id))
                    ->ignore($unit->id),
            ],
            'unit_type' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'unit_type')), 'nullable', 'string', 'max:64'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'rent_amount' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'rent_amount')), 'nullable', 'numeric', 'min:0'],
            'status' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'status')), 'nullable', 'in:vacant,occupied,notice'],
            'public_listing_description' => ['nullable', 'string', 'max:20000'],
        ]);

        $data['label'] = (string) ($data['label'] ?? $unit->label);
        $data['unit_type'] = $this->normalizeUnitTypeValue((string) ($data['unit_type'] ?? $unit->unit_type));
        $noBedroomTypes = [PropertyUnit::TYPE_SINGLE_ROOM, PropertyUnit::TYPE_BEDSITTER, PropertyUnit::TYPE_STUDIO];
        $requiresNoBedroom = in_array($data['unit_type'], $noBedroomTypes, true);
        if ($requiresNoBedroom) {
            $data['bedrooms'] = 0;
        } elseif ($this->isFieldRequired($unitFields, 'bedrooms') && ! isset($data['bedrooms'])) {
            return back()
                ->withErrors(['bedrooms' => __('The bedrooms field is required.')])
                ->withInput();
        } elseif (! isset($data['bedrooms'])) {
            $data['bedrooms'] = (int) $unit->bedrooms;
        }

        $status = (string) ($data['status'] ?? $unit->status);
        $hasActiveLease = $unit->leases()->where('pm_leases.status', PmLease::STATUS_ACTIVE)->exists();
        if ($status === PropertyUnit::STATUS_OCCUPIED && ! $hasActiveLease) {
            return back()->withErrors([
                'status' => 'Cannot mark unit occupied without an active lease. Create/activate a lease first.',
            ])->withInput();
        }
        if ($status === PropertyUnit::STATUS_VACANT && $hasActiveLease) {
            return back()->withErrors([
                'status' => 'Cannot mark unit vacant while it still has an active lease.',
            ])->withInput();
        }

        $data['vacant_since'] = $status === PropertyUnit::STATUS_VACANT
            ? ($unit->vacant_since?->toDateString() ?? now()->toDateString())
            : null;

        $unit->update($data);

        if ($status !== PropertyUnit::STATUS_VACANT && $unit->public_listing_published) {
            $unit->update(['public_listing_published' => false]);
        }

        return redirect()
            ->route('property.properties.units', ['property_id' => $unit->property_id], absolute: false)
            ->with('success', 'Unit updated.');
    }

    public function storeUnitJson(Request $request)
    {
        $unitFields = $this->unitFieldConfig();
        $data = $request->validate([
            'property_id' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'property_id')), 'nullable', 'integer', 'exists:properties,id'],
            'label' => [Rule::requiredIf($this->isFieldRequired($unitFields, 'label')), 'nullable', 'string', 'max:64'],
            'unit_type' => ['nullable', 'string', 'max:64'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:vacant,occupied,notice'],
        ]);

        $propertyId = (int) ($data['property_id'] ?? 0);
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = 'UNIT-'.strtoupper(Str::random(6));
        }

        $exists = PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->where('label', $label)
            ->exists();
        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'A unit with that label already exists for the selected property.',
            ], 422);
        }

        $unitType = $this->normalizeUnitTypeValue((string) ($data['unit_type'] ?? PropertyUnit::TYPE_APARTMENT));
        $noBedroomTypes = [PropertyUnit::TYPE_SINGLE_ROOM, PropertyUnit::TYPE_BEDSITTER, PropertyUnit::TYPE_STUDIO];
        $bedrooms = in_array($unitType, $noBedroomTypes, true)
            ? 0
            : (int) ($data['bedrooms'] ?? 1);

        $status = (string) ($data['status'] ?? PropertyUnit::STATUS_VACANT);
        $rentAmount = (float) ($data['rent_amount'] ?? 0);

        $unit = PropertyUnit::query()->create([
            'property_id' => $propertyId,
            'label' => $label,
            'unit_type' => $unitType,
            'bedrooms' => $bedrooms,
            'rent_amount' => $rentAmount,
            'status' => $status,
            'vacant_since' => $status === PropertyUnit::STATUS_VACANT ? now()->toDateString() : null,
        ]);

        $unit->loadMissing('property');

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $unit->id,
                'label' => ($unit->property?->name ?? 'Property '.$propertyId).' / '.$unit->label,
            ],
            'message' => 'Unit created.',
        ]);
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function propertyOnboardingFieldConfig(): array
    {
        $defaults = [
            'name' => ['enabled' => true, 'required' => true],
            'code' => ['enabled' => true, 'required' => false],
            'city' => ['enabled' => true, 'required' => false],
            'address_line' => ['enabled' => true, 'required' => false],
            'commission_percent' => ['enabled' => true, 'required' => false],
        ];

        return $this->configuredFieldMap('system_setup_property_onboarding_fields_json', $defaults, ['name']);
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function landlordFieldConfig(): array
    {
        $defaults = [
            'name' => ['enabled' => true, 'required' => true],
            'email' => ['enabled' => true, 'required' => true],
            'phone' => ['enabled' => true, 'required' => false],
            'id_number' => ['enabled' => true, 'required' => false],
        ];

        return $this->configuredFieldMap('system_setup_landlord_fields_json', $defaults, ['name', 'email']);
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function unitFieldConfig(): array
    {
        $defaults = [
            'property_id' => ['enabled' => true, 'required' => true],
            'label' => ['enabled' => true, 'required' => true],
            'unit_type' => ['enabled' => true, 'required' => true],
            'bedrooms' => ['enabled' => true, 'required' => false],
            'rent_amount' => ['enabled' => true, 'required' => true],
            'status' => ['enabled' => true, 'required' => true],
        ];

        return $this->configuredFieldMap('system_setup_unit_fields_json', $defaults, ['property_id', 'label']);
    }

    /**
     * @param array<string,array{enabled:bool,required:bool}> $defaults
     * @param array<int,string> $alwaysOn
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function configuredFieldMap(string $settingKey, array $defaults, array $alwaysOn = []): array
    {
        $map = $defaults;
        $raw = PropertyPortalSetting::getValue($settingKey, '');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $key = trim((string) ($row['key'] ?? ''));
                    if ($key === '' || ! array_key_exists($key, $map)) {
                        continue;
                    }
                    $map[$key]['enabled'] = ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
                    $map[$key]['required'] = (bool) ($row['required'] ?? false);
                }
            }
        }

        foreach ($alwaysOn as $fieldKey) {
            if (! array_key_exists($fieldKey, $map)) {
                continue;
            }
            $map[$fieldKey]['enabled'] = true;
            $map[$fieldKey]['required'] = true;
        }

        return $map;
    }

    /**
     * @param array<string,array{enabled:bool,required:bool}> $config
     */
    private function isFieldRequired(array $config, string $field): bool
    {
        return (bool) (($config[$field]['enabled'] ?? false) && ($config[$field]['required'] ?? false));
    }

    public function destroyUnit(PropertyUnit $unit): RedirectResponse
    {
        if ($unit->leases()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete unit with lease history.']);
        }
        if ($unit->invoices()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete unit with invoices.']);
        }
        if ($unit->utilityCharges()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete unit with utility charges.']);
        }
        if ($unit->maintenanceRequests()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete unit with maintenance records.']);
        }

        foreach ($unit->publicImages as $img) {
            Storage::disk('public')->delete($img->path);
            $img->delete();
        }

        $unit->delete();

        return back()->with('success', 'Unit deleted.');
    }

    private function storeMixedUnits(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'unit_groups' => ['required', 'array', 'min:1', 'max:200'],
            'unit_groups.*.unit_count' => ['required', 'integer', 'min:1', 'max:5000'],
            'unit_groups.*.label_prefix' => ['required', 'string', 'max:32'],
            'unit_groups.*.label_start' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_groups.*.unit_type' => ['required', 'string', 'max:64'],
            'unit_groups.*.bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'unit_groups.*.rent_amount' => ['required', 'numeric', 'min:0'],
            'unit_groups.*.status' => ['required', 'in:vacant,occupied,notice'],
            'unit_groups.*.public_listing_description' => ['nullable', 'string', 'max:20000'],
        ]);

        $propertyId = (int) $data['property_id'];
        $groups = (array) $data['unit_groups'];
        $noBedroomTypes = [PropertyUnit::TYPE_SINGLE_ROOM, PropertyUnit::TYPE_BEDSITTER, PropertyUnit::TYPE_STUDIO];

        $allLabels = [];
        $toCreate = [];

        foreach ($groups as $index => $group) {
            $groupNumber = $index + 1;
            $count = (int) ($group['unit_count'] ?? 0);
            $prefix = trim((string) ($group['label_prefix'] ?? ''));
            $start = (int) ($group['label_start'] ?? 1);
            $numericOnlyLabels = false;
            $unitType = $this->normalizeUnitTypeValue((string) ($group['unit_type'] ?? ''));
            $status = (string) ($group['status'] ?? '');
            $rentAmount = (float) ($group['rent_amount'] ?? 0);
            $desc = isset($group['public_listing_description']) && trim((string) $group['public_listing_description']) !== ''
                ? (string) $group['public_listing_description']
                : null;

            if ($prefix !== '' && preg_match('/^(.*?)(\d+)$/', $prefix, $m) === 1) {
                $prefix = trim((string) $m[1]);
                $start = (int) $m[2];
                $numericOnlyLabels = $prefix === '';
            }

            if ($prefix === '' && ! $numericOnlyLabels) {
                return back()
                    ->withErrors(['unit_groups' => __('Group :n: label prefix is required.', ['n' => $groupNumber])])
                    ->withInput();
            }

            $requiresNoBedroom = in_array($unitType, $noBedroomTypes, true);
            $bedrooms = $requiresNoBedroom ? 0 : ($group['bedrooms'] ?? null);
            if (! $requiresNoBedroom && $bedrooms === null) {
                return back()
                    ->withErrors(['unit_groups' => __('Group :n: bedrooms is required for this unit type.', ['n' => $groupNumber])])
                    ->withInput();
            }

            for ($i = 0; $i < $count; $i++) {
                $label = $numericOnlyLabels
                    ? (string) ($start + $i)
                    : $prefix.($start + $i);
                $allLabels[] = $label;
                $toCreate[] = [
                    'property_id' => $propertyId,
                    'label' => $label,
                    'unit_type' => $unitType,
                    'bedrooms' => (int) $bedrooms,
                    'rent_amount' => $rentAmount,
                    'status' => $status,
                    'public_listing_description' => $desc,
                    'vacant_since' => $status === PropertyUnit::STATUS_VACANT ? now()->toDateString() : null,
                ];
            }
        }

        $counts = array_count_values($allLabels);
        $dupesInPayload = array_keys(array_filter($counts, static fn ($c) => $c > 1));
        if ($dupesInPayload !== []) {
            return back()
                ->withErrors([
                    'unit_groups' => __('Duplicate labels in your batch: :labels. Adjust label prefix/start so each group has a unique range (example: R1-R4, then R5-R8).', [
                        'labels' => implode(', ', array_slice($dupesInPayload, 0, 10)),
                    ]),
                ])
                ->withInput();
        }

        $existing = PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->whereIn('label', $allLabels)
            ->pluck('label')
            ->all();
        if ($existing !== []) {
            return back()
                ->withErrors(['unit_groups' => __('Some labels already exist for this property: :labels', ['labels' => implode(', ', array_slice($existing, 0, 10))])])
                ->withInput();
        }

        DB::transaction(function () use ($toCreate): void {
            foreach ($toCreate as $payload) {
                PropertyUnit::query()->create($payload);
            }
        });

        return back()->with('success', 'Units saved: '.count($toCreate).'.');
    }

    /**
     * @return array<string, string>
     */
    private function propertyUnitTypeOptions(?string $forceInclude = null): array
    {
        $options = [];

        $customTypes = PropertyUnit::query()
            ->select('unit_type')
            ->distinct()
            ->pluck('unit_type')
            ->map(fn ($value) => $this->normalizeUnitTypeValue((string) $value))
            ->filter()
            ->unique()
            ->values();

        foreach ($customTypes as $type) {
            $options[$type] = (string) Str::of($type)->replace(['_', '-'], ' ')->title();
        }

        $forced = $this->normalizeUnitTypeValue((string) $forceInclude);
        if ($forced !== '' && ! isset($options[$forced])) {
            $options[$forced] = (string) Str::of($forced)->replace(['_', '-'], ' ')->title();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function propertyBedroomOptionsByType(?string $forceType = null, ?int $forceBedroom = null): array
    {
        $map = [];
        $rows = PropertyUnit::query()
            ->select(['unit_type', 'bedrooms'])
            ->whereNotNull('unit_type')
            ->whereNotNull('bedrooms')
            ->distinct()
            ->get()
            ->all();

        foreach ($rows as $row) {
            $type = $this->normalizeUnitTypeValue((string) ($row->unit_type ?? ''));
            $count = (int) ($row->bedrooms ?? -1);
            if ($type === '' || $count < 0 || $count > 20) {
                continue;
            }
            $map[$type] ??= [];
            $map[$type][$count] = $count === 0 ? 'No separate bedroom' : $count.' '.Str::plural('bedroom', $count);
        }

        $forcedTypeValue = $this->normalizeUnitTypeValue((string) $forceType);
        if ($forcedTypeValue !== '' && $forceBedroom !== null && $forceBedroom >= 0 && $forceBedroom <= 20) {
            $map[$forcedTypeValue] ??= [];
            $map[$forcedTypeValue][$forceBedroom] = $forceBedroom === 0
                ? 'No separate bedroom'
                : $forceBedroom.' '.Str::plural('bedroom', $forceBedroom);
        }

        foreach ($map as $type => $options) {
            ksort($options);
            $map[$type] = $options;
        }

        return $map;
    }

    private function normalizeUnitTypeValue(string $value): string
    {
        $normalized = (string) Str::of($value)->trim()->lower()->replaceMatches('/\s+/', '_');

        return trim($normalized, '_');
    }

    public function attachLandlord(Request $request): RedirectResponse
    {
        Log::warning('attachLandlord_debug: attachLandlord called', [
            'property_id' => $request->input('property_id'),
            'user_id' => $request->input('user_id'),
            'ownership_percent' => $request->input('ownership_percent'),
        ]);
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
            'ownership_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        if ($property->landlords()->exists()) {
            return redirect()
                ->route('property.properties.list')
                ->withErrors(['property_id' => __('This property is already linked to a landlord.')])
                ->withInput();
        }
        $landlordAllowed = $this->landlordUsersQueryForActor($request->user())
            ->whereKey((int) $data['user_id'])
            ->exists();
        if (! $landlordAllowed) {
            return redirect()
                ->route('property.properties.edit', $property->id)
                ->withErrors(['user_id' => __('You can only link landlord accounts in your workspace.')])
                ->withInput();
        }
        $pct = (float) ($data['ownership_percent'] ?? 100);

        $currentSum = (float) $property->landlords()->sum('property_landlord.ownership_percent');
        if ($currentSum + $pct > 100.0001) {
            return redirect()
                ->route('property.properties.edit', $property->id)
                ->withErrors(['ownership_percent' => __('Total ownership for this property would exceed 100%.')])
                ->withInput();
        }

        $property->landlords()->syncWithoutDetaching([
            $data['user_id'] => ['ownership_percent' => $pct],
        ]);

        return redirect()
            ->route('property.properties.edit', $property->id)
            ->with('success', 'Landlord linked to property.')
            ->with('next_steps', [
                'title' => 'Landlord linked',
                'message' => 'Next, add units for this property, then publish vacant units under Listings.',
                'actions' => [
                    [
                        'label' => 'Add units',
                        'href' => route('property.properties.units', ['property_id' => $property->id], absolute: false),
                        'kind' => 'primary',
                        'icon' => 'fa-solid fa-building',
                        'turbo_frame' => 'property-main',
                    ],
                    [
                        'label' => 'View properties list',
                        'href' => route('property.properties.list', ['property_id' => $property->id], absolute: false),
                        'kind' => 'secondary',
                        'icon' => 'fa-solid fa-list',
                        'turbo_frame' => 'property-main',
                    ],
                    [
                        'label' => 'Go to Listings',
                        'href' => route('property.listings.index', absolute: false),
                        'kind' => 'ghost',
                        'icon' => 'fa-solid fa-bullhorn',
                        'turbo_frame' => 'property-main',
                    ],
                ],
            ]);
    }

    public function occupancy(Request $request)
    {
        $preset = trim((string) $request->query('preset', ''));
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, [PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_NOTICE], true)) {
            $status = '';
        }
        $ageBucket = trim((string) $request->query('age_bucket', ''));
        if (! in_array($ageBucket, ['0_30', '31_60', '61_90', '90_plus'], true)) {
            $ageBucket = '';
        }
        $propertyId = (int) $request->query('property_id', 0);
        $search = trim((string) $request->query('q', ''));
        $export = strtolower(trim((string) $request->query('export', '')));

        if ($preset === 'vacant') {
            $status = PropertyUnit::STATUS_VACANT;
        } elseif ($preset === 'notice') {
            $status = PropertyUnit::STATUS_NOTICE;
        } elseif ($preset === 'long_vacant') {
            $status = PropertyUnit::STATUS_VACANT;
            if ($ageBucket === '') {
                $ageBucket = '90_plus';
            }
        }

        $today = Carbon::today();
        $d30 = $today->copy()->subDays(30)->toDateString();
        $d60 = $today->copy()->subDays(60)->toDateString();
        $d90 = $today->copy()->subDays(90)->toDateString();

        $baseQuery = PropertyUnit::query()
            ->with([
                'property',
                'leases' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE)->with('pmTenant'),
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($propertyId > 0, fn ($q) => $q->where('property_id', $propertyId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('label', 'like', '%'.$search.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($ageBucket !== '', function ($q) use ($ageBucket, $d30, $d60, $d90) {
                $q->whereNotNull('vacant_since');
                if ($ageBucket === '0_30') {
                    $q->whereDate('vacant_since', '>=', $d30);
                } elseif ($ageBucket === '31_60') {
                    $q->whereBetween('vacant_since', [$d60, Carbon::parse($d30)->subDay()->toDateString()]);
                } elseif ($ageBucket === '61_90') {
                    $q->whereBetween('vacant_since', [$d90, Carbon::parse($d60)->subDay()->toDateString()]);
                } elseif ($ageBucket === '90_plus') {
                    $q->whereDate('vacant_since', '<', $d90);
                }
            })
            ->orderBy('property_id')
            ->orderBy('label');

        $units = (clone $baseQuery)->get();
        $unitsPage = (clone $baseQuery)->paginate(50)->withQueryString();

        $total = $units->count();
        $occ = $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $vac = $units->where('status', PropertyUnit::STATUS_VACANT)->count();
        $notice = $units->where('status', PropertyUnit::STATUS_NOTICE)->count();
        $rate = $total > 0 ? round(100 * $occ / $total, 1) : null;
        $vacantRentExposure = (float) $units
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->sum(fn (PropertyUnit $u) => (float) $u->rent_amount);

        $vacancyAging = [
            '0_30' => ['label' => '0-30 days', 'count' => 0, 'rent' => 0.0],
            '31_60' => ['label' => '31-60 days', 'count' => 0, 'rent' => 0.0],
            '61_90' => ['label' => '61-90 days', 'count' => 0, 'rent' => 0.0],
            '90_plus' => ['label' => '90+ days', 'count' => 0, 'rent' => 0.0],
        ];
        foreach ($units->where('status', PropertyUnit::STATUS_VACANT) as $vu) {
            $days = $vu->vacant_since ? $vu->vacant_since->diffInDays($today) : 0;
            $bucket = $days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus'));
            $vacancyAging[$bucket]['count']++;
            $vacancyAging[$bucket]['rent'] += (float) $vu->rent_amount;
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream(
                'occupancy-view',
                ['Unit', 'Property', 'Status', 'Active Tenant', 'List Rent', 'Vacant Since'],
                function () use ($units) {
                    return $units->map(function (PropertyUnit $u) {
                        $lease = $u->leases->first();
                        $tenant = $lease?->pmTenant;

                        return [
                            (string) $u->label,
                            (string) ($u->property->name ?? ''),
                            (string) ucfirst($u->status),
                            (string) ($tenant?->name ?? '—'),
                            (string) number_format((float) $u->rent_amount, 2, '.', ''),
                            (string) ($u->vacant_since?->format('Y-m-d') ?? '—'),
                        ];
                    });
                },
                $export
            );
        }

        $stats = [
            ['label' => 'Occupancy rate', 'value' => $rate !== null ? $rate.'%' : '—', 'hint' => 'Occupied / all units'],
            ['label' => 'Occupied', 'value' => (string) $occ, 'hint' => 'Units'],
            ['label' => 'Vacant', 'value' => (string) $vac, 'hint' => 'Units'],
            ['label' => 'Notice', 'value' => (string) $notice, 'hint' => 'Move-out pipeline'],
        ];

        $rows = $unitsPage->getCollection()->map(function (PropertyUnit $u) {
            $lease = $u->leases->first();
            $tenant = $lease?->pmTenant;
            $actions = [
                '<a href="'.route('property.properties.show', $u->property_id, absolute: false).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">View property</a>',
            ];

            if ($u->status === PropertyUnit::STATUS_VACANT) {
                $actions[] = '<a href="'.route('property.tenants.leases', ['property_id' => $u->property_id, 'unit_id' => $u->id], absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Assign tenant</a>';
                $actions[] = '<a href="'.route('property.listings.create', ['selected_unit' => $u->id], absolute: false).'#listing-publish" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">Publish listing</a>';
            } elseif ($u->status === PropertyUnit::STATUS_OCCUPIED) {
                if ($lease) {
                    $actions[] = '<a href="'.route('property.leases.edit', $lease, absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Open lease</a>';
                }
                if ($tenant?->name) {
                    $actions[] = '<a href="'.route('property.tenants.profiles', ['q' => $tenant->name], absolute: false).'" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">View tenant</a>';
                }
            } elseif ($u->status === PropertyUnit::STATUS_NOTICE) {
                $actions[] = '<a href="'.route('property.tenants.notices', ['q' => $u->label], absolute: false).'" class="block px-3 py-2 text-xs text-amber-700 hover:bg-amber-50">Prepare move-out</a>';
                $actions[] = '<a href="'.route('property.listings.vacant', ['q' => $u->property->name], absolute: false).'" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">Market unit</a>';
            }

            $actionHtml = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                implode('', $actions).
                '</div>'.
                '</details>'.
                '</div>'
            );
            $select = new HtmlString('<input form="occupancy-bulk-form" type="checkbox" name="unit_ids[]" value="'.$u->id.'" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />');

            return [
                $select,
                $u->label,
                $u->property->name,
                ucfirst($u->status),
                $tenant?->name ?? '—',
                PropertyMoney::kes((float) $u->rent_amount),
                $u->vacant_since?->format('Y-m-d') ?? '—',
                $actionHtml,
            ];
        })->all();

        $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
        $activityTrend = collect();
        if ($unitIds !== []) {
            $startMonth = Carbon::now()->startOfMonth()->subMonths(5);
            $activityRows = DB::table('pm_unit_movements')
                ->selectRaw('DATE_FORMAT(COALESCE(completed_on, scheduled_on), "%Y-%m") as ym, movement_type, COUNT(*) as c')
                ->whereIn('property_unit_id', $unitIds)
                ->whereIn('movement_type', ['move_in', 'move_out'])
                ->whereDate(DB::raw('COALESCE(completed_on, scheduled_on)'), '>=', $startMonth->toDateString())
                ->groupBy('ym', 'movement_type')
                ->get();

            $activityTrend = collect(range(0, 5))->map(function ($i) use ($startMonth, $activityRows) {
                $ym = $startMonth->copy()->addMonths($i)->format('Y-m');
                $monthRows = $activityRows->where('ym', $ym);

                return [
                    'label' => Carbon::createFromFormat('Y-m', $ym)->format('M Y'),
                    'move_in' => (int) ($monthRows->firstWhere('movement_type', 'move_in')->c ?? 0),
                    'move_out' => (int) ($monthRows->firstWhere('movement_type', 'move_out')->c ?? 0),
                ];
            });
        }

        return view('property.agent.properties.occupancy', [
            'stats' => $stats,
            'columns' => ['Select', 'Unit', 'Property', 'Status', 'Active tenant', 'List rent', 'Vacant since', 'Actions'],
            'tableRows' => $rows,
            'filters' => [
                'preset' => $preset,
                'status' => $status,
                'age_bucket' => $ageBucket,
                'property_id' => $propertyId > 0 ? (string) $propertyId : '',
                'q' => $search,
            ],
            'propertyOptions' => Property::query()
                ->whereIn('id', PropertyUnit::query()->select('property_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
            'vacancyAging' => $vacancyAging,
            'vacantRentExposure' => $vacantRentExposure,
            'activityTrend' => $activityTrend,
            'unitsPage' => $unitsPage,
        ]);
    }

    public function occupancyBulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', Rule::in([
                'mark_vacant',
                'mark_occupied',
                'mark_notice',
                'open_assign',
                'open_publish',
                'open_property',
            ])],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', 'exists:property_units,id'],
        ]);

        $units = PropertyUnit::query()->whereIn('id', $data['unit_ids'])->get();
        if ($units->isEmpty()) {
            return back()->with('error', 'Select at least one unit.');
        }

        $action = (string) $data['bulk_action'];
        if ($action === 'mark_vacant') {
            $activeLeaseCount = DB::table('pm_lease_unit as lu')
                ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                ->whereIn('lu.property_unit_id', $units->pluck('id'))
                ->where('l.status', PmLease::STATUS_ACTIVE)
                ->count();
            if ($activeLeaseCount > 0) {
                return back()->with('error', 'Some selected units still have active leases. End those leases before marking vacant.');
            }

            PropertyUnit::query()->whereIn('id', $units->pluck('id'))->update([
                'status' => PropertyUnit::STATUS_VACANT,
                'vacant_since' => now()->toDateString(),
            ]);

            return back()->with('success', 'Selected units marked vacant.');
        }
        if ($action === 'mark_occupied') {
            $activeLeaseUnitIds = DB::table('pm_lease_unit as lu')
                ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                ->whereIn('lu.property_unit_id', $units->pluck('id'))
                ->where('l.status', PmLease::STATUS_ACTIVE)
                ->distinct()
                ->pluck('lu.property_unit_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $selectedIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
            $missing = array_values(array_diff($selectedIds, $activeLeaseUnitIds));
            if ($missing !== []) {
                return back()->with('error', 'Some selected units have no active lease. Only units with active leases can be marked occupied.');
            }

            PropertyUnit::query()->whereIn('id', $units->pluck('id'))->update([
                'status' => PropertyUnit::STATUS_OCCUPIED,
                'vacant_since' => null,
            ]);

            return back()->with('success', 'Selected units marked occupied.');
        }
        if ($action === 'mark_notice') {
            PropertyUnit::query()->whereIn('id', $units->pluck('id'))->update([
                'status' => PropertyUnit::STATUS_NOTICE,
            ]);

            return back()->with('success', 'Selected units marked notice.');
        }
        if ($action === 'open_assign') {
            $target = $units->firstWhere('status', PropertyUnit::STATUS_VACANT) ?? $units->first();

            return redirect()->route('property.tenants.leases', [
                'property_id' => $target->property_id,
                'unit_id' => $target->id,
            ]);
        }
        if ($action === 'open_publish') {
            $target = $units->firstWhere('status', PropertyUnit::STATUS_VACANT);
            if (! $target) {
                return back()->with('error', 'Choose at least one vacant unit to publish.');
            }

            return redirect()->route('property.listings.create', ['selected_unit' => $target->id])->withFragment('listing-publish');
        }

        $target = $units->first();

        return redirect()->route('property.properties.show', ['property' => $target->property_id]);
    }
}
