<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Illuminate\View\View;

class PropertySearchController extends Controller
{
    /**
     * @return array{scope:string,needle:string}
     */
    private function normalizeQuery(string $raw): array
    {
        $q = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        [$scope, $term] = $this->parseScopeAndTerm($q);
        $needle = $term !== '' ? $term : $q;
        return ['scope' => $scope, 'needle' => $needle];
    }

    public function index(Request $request): View
    {
        $q = (string) $request->query('q', '');
        $parsed = $this->normalizeQuery($q);
        $scope = $parsed['scope'];
        $needle = $parsed['needle'];

        $tenants = collect();
        $landlords = collect();
        $properties = collect();
        $units = collect();
        $invoices = collect();
        $payments = collect();
        $searchError = null;

        if ($q !== '') {
            try {
                $wantAll = $scope === '';
                $isNumeric = ctype_digit($needle);

                if (($wantAll || $scope === 'landlord') && Schema::hasTable('users')) {
                    $landlords = User::query()
                        ->where('property_portal_role', 'landlord')
                        ->where(function ($b) use ($needle, $isNumeric) {
                            $b->where('name', 'like', '%'.$needle.'%');
                            if (Schema::hasColumn('users', 'email')) {
                                $b->orWhere('email', 'like', '%'.$needle.'%');
                            }
                            if ($isNumeric) {
                                $b->orWhere('id', (int) $needle);
                            }
                        })
                        ->withCount('landlordProperties')
                        ->orderBy('name')
                        ->limit(12)
                        ->get(['id', 'name', 'email']);
                }

                if (($wantAll || $scope === 'tenant') && Schema::hasTable('pm_tenants')) {
                    $tenants = PmTenant::query()
                        ->where(function ($b) use ($needle, $isNumeric) {
                            $b->where('name', 'like', '%'.$needle.'%');
                            if (Schema::hasColumn('pm_tenants', 'phone')) {
                                $b->orWhere('phone', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('pm_tenants', 'email')) {
                                $b->orWhere('email', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('pm_tenants', 'account_number')) {
                                $b->orWhere('account_number', 'like', '%'.$needle.'%');
                            }
                            if ($isNumeric) {
                                $b->orWhere('id', (int) $needle);
                            }
                        })
                        ->orderBy('name')
                        ->limit(12)
                        ->get(['id', 'name', 'phone', 'email']);
                }

                if (($wantAll || $scope === 'property') && Schema::hasTable('properties')) {
                    $properties = Property::query()
                        ->where(function ($b) use ($needle) {
                            $b->where('name', 'like', '%'.$needle.'%');
                            if (Schema::hasColumn('properties', 'code')) {
                                $b->orWhere('code', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('properties', 'city')) {
                                $b->orWhere('city', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('properties', 'address_line')) {
                                $b->orWhere('address_line', 'like', '%'.$needle.'%');
                            }
                        })
                        ->orderBy('name')
                        ->limit(10)
                        ->get(['id', 'name']);
                }

                if (($wantAll || $scope === 'unit') && Schema::hasTable('property_units')) {
                    $units = PropertyUnit::query()
                        ->with('property:id,name')
                        ->where(function ($b) use ($needle, $isNumeric) {
                            $b->where('label', 'like', '%'.$needle.'%');
                            if (Schema::hasColumn('property_units', 'unit_type')) {
                                $b->orWhere('unit_type', 'like', '%'.$needle.'%');
                            }
                            $b->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$needle.'%'));
                            if ($isNumeric) {
                                $b->orWhere('id', (int) $needle);
                            }
                        })
                        ->orderBy('property_id')
                        ->orderBy('label')
                        ->limit(12)
                        ->get(['id', 'property_id', 'label', 'unit_type', 'status', 'rent_amount']);
                }

                if (($wantAll || $scope === 'invoice') && Schema::hasTable('pm_invoices')) {
                    $invoices = PmInvoice::query()
                        ->with(['tenant:id,name', 'unit:id,label,property_id', 'unit.property:id,name'])
                        ->where(function ($b) use ($needle, $isNumeric) {
                            if (Schema::hasColumn('pm_invoices', 'invoice_no')) {
                                $b->where('invoice_no', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('pm_invoices', 'description')) {
                                $b->orWhere('description', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('pm_invoices', 'billing_period')) {
                                $b->orWhere('billing_period', 'like', '%'.$needle.'%');
                            }
                            $b->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$needle.'%')->orWhere('phone', 'like', '%'.$needle.'%'))
                                ->orWhereHas('unit', fn ($u) => $u->where('label', 'like', '%'.$needle.'%'));
                            if ($isNumeric) {
                                $b->orWhere('id', (int) $needle);
                            }
                        })
                        ->orderByDesc('issue_date')
                        ->limit(12)
                        ->get();
                }

                if (($wantAll || $scope === 'payment') && Schema::hasTable('pm_payments')) {
                    $payments = PmPayment::query()
                        ->with('tenant:id,name,phone')
                        ->where(function ($b) use ($needle, $isNumeric) {
                            if (Schema::hasColumn('pm_payments', 'external_ref')) {
                                $b->where('external_ref', 'like', '%'.$needle.'%');
                            }
                            if (Schema::hasColumn('pm_payments', 'channel')) {
                                $b->orWhere('channel', 'like', '%'.$needle.'%');
                            }
                            $b->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$needle.'%')->orWhere('phone', 'like', '%'.$needle.'%'));
                            if ($isNumeric) {
                                $b->orWhere('id', (int) $needle);
                            }
                        })
                        ->orderByDesc('paid_at')
                        ->orderByDesc('id')
                        ->limit(12)
                        ->get(['id', 'pm_tenant_id', 'channel', 'amount', 'external_ref', 'paid_at', 'status', 'meta']);
                }
            } catch (Throwable $e) {
                // Keep search UX alive even when schema/data mismatch exists.
                report($e);
                $searchError = 'Some search sources are unavailable right now. Try a simpler term or check setup.';
            }
        }

        return view('property.agent.search.index', [
            'q' => trim((string) preg_replace('/\s+/', ' ', $q)),
            'scope' => $scope,
            'landlords' => $landlords,
            'tenants' => $tenants,
            'properties' => $properties,
            'units' => $units,
            'invoices' => $invoices,
            'payments' => $payments,
            'searchError' => $searchError,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $parsed = $this->normalizeQuery($q);
        $scope = $parsed['scope'];
        $needle = $parsed['needle'];

        $empty = [
            'query' => trim((string) preg_replace('/\s+/', ' ', $q)),
            'scope' => $scope,
            'groups' => [
                'pages' => [],
                'landlords' => [],
                'tenants' => [],
                'properties' => [],
                'units' => [],
                'invoices' => [],
                'payments' => [],
            ],
            'has_results' => false,
            'error' => null,
        ];
        if ($needle === '') {
            return response()->json($empty);
        }

        $isNumeric = ctype_digit($needle);
        $wantAll = $scope === '';
        $groups = $empty['groups'];
        $error = null;

        try {
            if ($wantAll || $scope === 'page') {
                $groups['pages'] = $this->searchablePages($needle);
            }

            if (($wantAll || $scope === 'landlord') && Schema::hasTable('users')) {
                $landlords = User::query()
                    ->where('property_portal_role', 'landlord')
                    ->where(function ($b) use ($needle, $isNumeric) {
                        $b->where('name', 'like', '%'.$needle.'%');
                        if (Schema::hasColumn('users', 'email')) {
                            $b->orWhere('email', 'like', '%'.$needle.'%');
                        }
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->withCount('landlordProperties')
                    ->orderBy('name')
                    ->limit(5)
                    ->get(['id', 'name', 'email']);

                $groups['landlords'] = $landlords->map(fn (User $u) => [
                    'id' => (int) $u->id,
                    'title' => (string) $u->name,
                    'subtitle' => trim(((string) ($u->email ?? '—')).' • '.((int) ($u->landlord_properties_count ?? 0)).' properties'),
                    'url' => route('property.landlords.show', $u->id),
                ])->all();
            }

            if (($wantAll || $scope === 'tenant') && Schema::hasTable('pm_tenants')) {
                $tenants = PmTenant::query()
                    ->where(function ($b) use ($needle, $isNumeric) {
                        $b->where('name', 'like', '%'.$needle.'%');
                        if (Schema::hasColumn('pm_tenants', 'phone')) {
                            $b->orWhere('phone', 'like', '%'.$needle.'%');
                        }
                        if (Schema::hasColumn('pm_tenants', 'email')) {
                            $b->orWhere('email', 'like', '%'.$needle.'%');
                        }
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->orderBy('name')
                    ->limit(5)
                    ->get(['id', 'name', 'phone', 'email']);

                $groups['tenants'] = $tenants->map(fn (PmTenant $t) => [
                    'id' => (int) $t->id,
                    'title' => (string) $t->name,
                    'subtitle' => trim(((string) ($t->phone ?? '—')).' • '.((string) ($t->email ?? '—'))),
                    'url' => route('property.tenants.show', $t->id),
                ])->all();
            }

            if (($wantAll || $scope === 'property') && Schema::hasTable('properties')) {
                $properties = Property::query()
                    ->where(function ($b) use ($needle, $isNumeric) {
                        $b->where('name', 'like', '%'.$needle.'%');
                        if (Schema::hasColumn('properties', 'code')) {
                            $b->orWhere('code', 'like', '%'.$needle.'%');
                        }
                        if (Schema::hasColumn('properties', 'city')) {
                            $b->orWhere('city', 'like', '%'.$needle.'%');
                        }
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->orderBy('name')
                    ->limit(5)
                    ->get(['id', 'name']);

                $groups['properties'] = $properties->map(fn (Property $p) => [
                    'id' => (int) $p->id,
                    'title' => (string) $p->name,
                    'subtitle' => 'Property #'.$p->id,
                    'url' => route('property.properties.show', $p->id),
                ])->all();
            }

            if (($wantAll || $scope === 'unit') && Schema::hasTable('property_units')) {
                $units = PropertyUnit::query()
                    ->with('property:id,name')
                    ->where(function ($b) use ($needle, $isNumeric) {
                        $b->where('label', 'like', '%'.$needle.'%');
                        if (Schema::hasColumn('property_units', 'unit_type')) {
                            $b->orWhere('unit_type', 'like', '%'.$needle.'%');
                        }
                        $b->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$needle.'%'));
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->orderBy('property_id')
                    ->orderBy('label')
                    ->limit(5)
                    ->get(['id', 'property_id', 'label', 'unit_type', 'status']);

                $groups['units'] = $units->map(fn (PropertyUnit $u) => [
                    'id' => (int) $u->id,
                    'title' => (string) $u->label,
                    'subtitle' => trim(((string) ($u->property?->name ?? 'Property')).' • '.((string) $u->unitTypeLabel())),
                    'url' => route('property.properties.units', ['q' => $u->label]),
                ])->all();
            }

            if (($wantAll || $scope === 'invoice') && Schema::hasTable('pm_invoices')) {
                $invoices = PmInvoice::query()
                    ->with(['tenant:id,name', 'unit:id,label,property_id', 'unit.property:id,name'])
                    ->where(function ($b) use ($needle, $isNumeric) {
                        if (Schema::hasColumn('pm_invoices', 'invoice_no')) {
                            $b->where('invoice_no', 'like', '%'.$needle.'%');
                        }
                        if (Schema::hasColumn('pm_invoices', 'description')) {
                            $b->orWhere('description', 'like', '%'.$needle.'%');
                        }
                        $b->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$needle.'%')->orWhere('phone', 'like', '%'.$needle.'%'))
                            ->orWhereHas('unit', fn ($u) => $u->where('label', 'like', '%'.$needle.'%'));
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->orderByDesc('issue_date')
                    ->limit(5)
                    ->get(['id', 'invoice_no', 'pm_tenant_id', 'property_unit_id', 'status']);

                $groups['invoices'] = $invoices->map(fn (PmInvoice $i) => [
                    'id' => (int) $i->id,
                    'title' => (string) ($i->invoice_no ?? ('INV-'.$i->id)),
                    'subtitle' => trim(((string) ($i->tenant?->name ?? '—')).' • '.((string) ucfirst((string) $i->status))),
                    'url' => route('property.revenue.invoices', ['q' => $i->invoice_no ?: $i->id]),
                ])->all();
            }

            if (($wantAll || $scope === 'payment') && Schema::hasTable('pm_payments')) {
                $payments = PmPayment::query()
                    ->with('tenant:id,name,phone')
                    ->where(function ($b) use ($needle, $isNumeric) {
                        if (Schema::hasColumn('pm_payments', 'external_ref')) {
                            $b->where('external_ref', 'like', '%'.$needle.'%');
                        }
                        if (Schema::hasColumn('pm_payments', 'channel')) {
                            $b->orWhere('channel', 'like', '%'.$needle.'%');
                        }
                        $b->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$needle.'%')->orWhere('phone', 'like', '%'.$needle.'%'));
                        if ($isNumeric) {
                            $b->orWhere('id', (int) $needle);
                        }
                    })
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(['id', 'pm_tenant_id', 'channel', 'amount', 'external_ref', 'status']);

                $groups['payments'] = $payments->map(fn (PmPayment $p) => [
                    'id' => (int) $p->id,
                    'title' => 'PAY-'.(int) $p->id,
                    'subtitle' => trim(((string) ($p->tenant?->name ?? '—')).' • '.((string) ($p->external_ref ?: $p->channel))),
                    'url' => route('property.revenue.payments', ['q' => $p->external_ref ?: ('PAY-'.$p->id)]),
                ])->all();
            }
        } catch (Throwable $e) {
            report($e);
            $error = 'Search sources are temporarily unavailable.';
        }

        $hasResults = collect($groups)->flatten(1)->isNotEmpty();

        return response()->json([
            'query' => $empty['query'],
            'scope' => $scope,
            'groups' => $groups,
            'has_results' => $hasResults,
            'error' => $error,
        ]);
    }

    /**
     * Supports scoped search syntax:
     * landlord:jane, tenant:john, unit:A1, invoice:INV-001, payment:123, property:Westlands
     *
     * @return array{0:string,1:string}
     */
    private function parseScopeAndTerm(string $q): array
    {
        if (! str_contains($q, ':')) {
            return ['', $q];
        }
        [$rawScope, $rawTerm] = array_pad(explode(':', $q, 2), 2, '');
        $scope = strtolower(trim($rawScope));
        $term = trim($rawTerm);
        $aliases = [
            'page' => 'page',
            'pages' => 'page',
            'landlord' => 'landlord',
            'landlords' => 'landlord',
            'tenant' => 'tenant',
            'tenants' => 'tenant',
            'unit' => 'unit',
            'units' => 'unit',
            'invoice' => 'invoice',
            'invoices' => 'invoice',
            'payment' => 'payment',
            'payments' => 'payment',
            'property' => 'property',
            'properties' => 'property',
        ];
        if (! isset($aliases[$scope])) {
            return ['', $q];
        }
        return [$aliases[$scope], $term !== '' ? $term : $q];
    }

    /**
     * @return list<array{id:int,title:string,subtitle:string,url:string}>
     */
    private function searchablePages(string $needle): array
    {
        $catalog = [
            ['title' => 'Dashboard', 'subtitle' => 'Portfolio snapshot and quick actions', 'route' => 'property.dashboard', 'keywords' => 'home summary overview'],
            ['title' => 'Revenue', 'subtitle' => 'Rent roll, arrears, invoices and payments', 'route' => 'property.revenue.index', 'keywords' => 'revenue collections billing receipts income rent'],
            ['title' => 'Properties', 'subtitle' => 'Manage properties list', 'route' => 'property.properties.list', 'keywords' => 'buildings real estate assets'],
            ['title' => 'Units', 'subtitle' => 'Manage property units', 'route' => 'property.properties.units', 'keywords' => 'rooms apartments occupancy'],
            ['title' => 'Tenants', 'subtitle' => 'Tenant directory and profiles', 'route' => 'property.tenants.directory', 'keywords' => 'clients renters occupants'],
            ['title' => 'Leases', 'subtitle' => 'Allocate units and manage leases', 'route' => 'property.tenants.leases', 'keywords' => 'contracts tenancy'],
            ['title' => 'Invoices', 'subtitle' => 'Create and track invoices', 'route' => 'property.revenue.invoices', 'keywords' => 'billing rent bills'],
            ['title' => 'Payments', 'subtitle' => 'Record and track payments', 'route' => 'property.revenue.payments', 'keywords' => 'receipts collection mpesa bank cash'],
            ['title' => 'Arrears', 'subtitle' => 'Overdue invoices and reminders', 'route' => 'property.revenue.arrears', 'keywords' => 'overdue aging late'],
            ['title' => 'Rent Roll', 'subtitle' => 'Unit-by-unit billing status', 'route' => 'property.revenue.rent_roll', 'keywords' => 'roll schedule'],
            ['title' => 'Receipts', 'subtitle' => 'Fiscal receipts and eTIMS records', 'route' => 'property.revenue.receipts', 'keywords' => 'receipts etims tax invoices'],
            ['title' => 'Utilities Charges', 'subtitle' => 'Water and utility recoveries', 'route' => 'property.revenue.utilities', 'keywords' => 'utilities water charges billing'],
            ['title' => 'Maintenance', 'subtitle' => 'Requests, jobs, and costs', 'route' => 'property.maintenance.requests', 'keywords' => 'repairs tickets jobs'],
            ['title' => 'Vendors', 'subtitle' => 'Vendor directory and work records', 'route' => 'property.vendors.directory', 'keywords' => 'contractors suppliers quotes'],
            ['title' => 'Listings', 'subtitle' => 'Vacant units, leads, applications', 'route' => 'property.listings.index', 'keywords' => 'ads public vacant'],
            ['title' => 'Financials', 'subtitle' => 'Income/expense and owner balances', 'route' => 'property.financials.index', 'keywords' => 'cashflow commission'],
            ['title' => 'Accounting', 'subtitle' => 'Entries, reports, trial balance', 'route' => 'property.accounting.index', 'keywords' => 'ledger journal books trial'],
            ['title' => 'Trial Balance', 'subtitle' => 'Accounting report', 'route' => 'property.accounting.reports.trial_balance', 'keywords' => 'debit credit balance'],
            ['title' => 'Income Statement', 'subtitle' => 'Accounting report', 'route' => 'property.accounting.reports.income_statement', 'keywords' => 'profit loss noi'],
            ['title' => 'Cash Book', 'subtitle' => 'Accounting report', 'route' => 'property.accounting.reports.cash_book', 'keywords' => 'cash bank running balance'],
            ['title' => 'Landlords', 'subtitle' => 'Manage landlord profiles and ownership', 'route' => 'property.landlords.index', 'keywords' => 'owners portfolio'],
            ['title' => 'Communications', 'subtitle' => 'Messages, templates, bulk sends', 'route' => 'property.communications.index', 'keywords' => 'sms email logs'],
            ['title' => 'Settings', 'subtitle' => 'System setup and permissions', 'route' => 'property.settings.index', 'keywords' => 'roles access config'],
            ['title' => 'Performance', 'subtitle' => 'Collection rate, vacancy, arrears trends', 'route' => 'property.performance.index', 'keywords' => 'kpi analytics trends'],
            ['title' => 'Reports', 'subtitle' => 'Tenant, landlord, financial reports', 'route' => 'property.reports.center', 'keywords' => 'analytics exports reporting'],
        ];

        $n = mb_strtolower(trim($needle));
        if ($n === '') {
            return [];
        }

        $hits = [];
        foreach ($catalog as $i => $row) {
            $hay = mb_strtolower($row['title'].' '.$row['subtitle'].' '.$row['keywords']);
            if (! str_contains($hay, $n)) {
                continue;
            }
            try {
                $hits[] = [
                    'id' => $i + 1,
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'],
                    'url' => route($row['route']),
                ];
            } catch (Throwable) {
                // Ignore invalid routes in this environment.
            }
            if (count($hits) >= 5) {
                break;
            }
        }
        return $hits;
    }
}

