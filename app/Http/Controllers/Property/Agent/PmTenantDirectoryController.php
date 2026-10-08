<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\UserModuleAccess;
use App\Mail\TenantPortalCredentialsMail;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\PmTenantDeposit;
use App\Models\PmTenantDepositRefund;
use App\Models\PmTenantNotice;
use App\Models\PmWaterReading;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Support\Property\PhoneLink;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinancialReportingFormulaService;
use App\Services\Property\InvoiceStateIntegrityService;
use App\Services\Property\PropertyMoney;
use App\Services\Property\PropertyPaymentAllocationRepairService;
use App\Services\Property\TenantCreditService;
use App\Services\Property\TenantStatementLedgerService;
use App\Support\Property\PropertyEntityHub;
use App\Support\Property\LeaseStandingCharges;
use App\Support\Property\PropertyFilterCascadeCatalog;
use App\Support\Property\TenantCompliancePresentation;
use App\Support\Property\TenantDirectoryBalanceFilter;
use App\Support\Property\PropertyFormModal;
use App\Support\Property\TenantProfileStatus;
use App\Support\Property\WorkspaceRowAlert;
use App\Http\Controllers\Property\Concerns\RespondsWithPropertyFormModal;
use Illuminate\Validation\ValidationException;

class PmTenantDirectoryController extends Controller
{
    use RespondsWithPropertyFormModal;

    public function directory(): View
    {
        return property_view('property.agent.tenants.directory', $this->tenantListPayload(
            pageTitle: 'Tenant list',
            pageSubtitle: 'Operational directory — add tenants here, then leases and billing.',
            showTenantForm: true,
        ));
    }

    public function profiles(): View
    {
        return property_view('property.agent.tenants.profiles', $this->tenantCompliancePayload());
    }

    public function exportDirectoryCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenants = $this->buildTenantDirectoryQuery($request)
            ->with(['leases' => function ($query): void {
                $query->where('status', PmLease::STATUS_ACTIVE)
                    ->with(['units.property'])
                    ->orderByDesc('start_date');
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $format = TabularExport::requestedFormat($request->query('export'), $request->query('format'));
        $statementLedger = app(TenantStatementLedgerService::class);
        $stats = $this->tenantDirectoryStatsFromQuery($request);

        return TabularExport::stream(
            'tenant_directory_'.now()->format('Ymd_His'),
            ['Tenant', 'Ac/No', 'Phone', 'Email', 'Unit', 'A/c balance', 'Rent', 'Charges', 'Lease start', 'Lease end', 'Leases', 'Status', 'Risk'],
            function () use ($tenants, $statementLedger): \Generator {
                foreach ($tenants as $tenant) {
                    $activeLease = $tenant->leases->first();
                    $activeUnit = $activeLease?->units->first();
                    $unitLabel = $activeUnit
                        ? trim((string) (($activeUnit->property->name ?? '').' / '.$activeUnit->label), ' /')
                        : '';
                    $balance = $statementLedger->closingBalance($tenant);
                    $balanceText = abs($balance) <= 0.009
                        ? '0.00'
                        : ($balance < 0 ? 'CR '.number_format(abs($balance), 2) : number_format($balance, 2));
                    $leaseEnd = $tenant->leases_max_end_date
                        ? (string) Carbon::parse((string) $tenant->leases_max_end_date)->format('Y-m-d')
                        : '';
                    $status = TenantProfileStatus::forTenant($tenant);

                    yield [
                        (string) $tenant->name,
                        (string) ($tenant->account_number ?? ''),
                        (string) ($tenant->phone ?? ''),
                        (string) ($tenant->email ?? ''),
                        $unitLabel,
                        $balanceText,
                        $activeLease?->monthly_rent !== null ? number_format((float) $activeLease->monthly_rent, 2) : '',
                        LeaseStandingCharges::exportText($activeLease),
                        $activeLease?->start_date?->format('Y-m-d') ?? '',
                        $leaseEnd,
                        (string) ($tenant->leases_count ?? 0),
                        (string) ($status['label'] ?? ''),
                        ucfirst((string) ($tenant->risk_level ?? 'normal')),
                    ];
                }
            },
            $format,
            [
                'title' => 'Tenant directory',
                'subtitle' => 'Same columns as the tenant list'.($this->directoryExportFilterNote($request) !== '' ? ' · '.$this->directoryExportFilterNote($request) : ''),
                'summary' => [
                    'Tenants' => (string) ($stats[0]['value'] ?? $tenants->count()),
                    'Active' => (string) ($stats[1]['value'] ?? ''),
                    'Not active' => (string) ($stats[2]['value'] ?? ''),
                ],
            ],
        );
    }

    private function directoryExportFilterNote(Request $request): string
    {
        $parts = [];
        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $parts[] = 'Search: '.$search;
        }
        $status = trim((string) $request->string('status'));
        if ($status !== '') {
            $parts[] = 'Status: '.str_replace('_', ' ', $status);
        }
        $balance = trim((string) $request->string('balance'));
        if ($balance !== '') {
            $parts[] = 'Balance: '.str_replace('_', ' ', $balance);
        }
        $risk = trim((string) $request->string('risk'));
        if ($risk !== '') {
            $parts[] = 'Risk: '.$risk;
        }
        if ((int) $request->integer('property_id') > 0) {
            $parts[] = 'Property filter on';
        }
        if ((int) $request->integer('unit_id') > 0) {
            $parts[] = 'Unit filter on';
        }

        return implode(' · ', $parts);
    }

    public function importForm(): View
    {
        return $this->directory();
    }

    public function importTemplate(): Response
    {
        $csv = implode(',', $this->tenantImportColumns())."\n"
            ."John Doe,+254700000000,john@example.com,ID123,normal,Notes here,no\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tenant_import_template.csv"',
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $cfg = $this->tenantFieldConfig();
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);

        $path = $data['file']->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->with('error', 'Upload failed. Please try again.');
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return back()->with('error', 'Could not read uploaded file.');
        }

        $header = fgetcsv($fh);
        if (! is_array($header) || count($header) === 0) {
            fclose($fh);
            return back()->with('error', 'CSV is empty or header row is missing.');
        }

        $normalize = static fn ($v) => str_replace([' ', '-'], '_', mb_strtolower(trim((string) $v)));
        $aliases = [
            'id_number' => 'national_id',
            'id_ref' => 'national_id',
            'portal_login' => 'create_portal_login',
        ];

        $header = array_map(function ($col) use ($normalize, $aliases) {
            $key = $normalize($col);

            return $aliases[$key] ?? $key;
        }, $header);

        $required = $this->tenantImportRequiredColumns($cfg);
        $missing = array_values(array_diff($required, $header));
        if (count($missing) > 0) {
            fclose($fh);
            return back()->with('error', 'Missing required columns: '.implode(', ', $missing));
        }

        $colIndex = [];
        foreach ($header as $i => $col) {
            $colIndex[$col] = $i;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $portalLoginsCreated = 0;
        $rowNum = 1; // header row
        $booleanish = static function (?string $value): bool {
            $v = trim(strtolower((string) $value));

            return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
        };

        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;

            // Skip blank lines
            if (! is_array($row) || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                $skipped++;
                continue;
            }

            $name = trim((string) ($row[$colIndex['name']] ?? ''));
            if ($name === '') {
                $errors[] = "Row {$rowNum}: name is required.";
                continue;
            }

            $emailRaw = trim((string) ($row[$colIndex['email']] ?? ''));
            $email = $emailRaw !== '' ? Str::lower($emailRaw) : null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: invalid email '{$emailRaw}'.";
                continue;
            }

            $phone = ($v = trim((string) ($row[$colIndex['phone']] ?? ''))) !== '' ? $v : null;
            $nationalId = ($v = trim((string) ($row[$colIndex['national_id']] ?? ''))) !== '' ? $v : null;
            $notes = ($v = trim((string) ($row[$colIndex['notes']] ?? ''))) !== '' ? $v : null;
            $createPortal = isset($colIndex['create_portal_login'])
                ? $booleanish((string) ($row[$colIndex['create_portal_login']] ?? ''))
                : false;

            if ($this->isFieldRequired($cfg, 'phone') && $phone === null) {
                $errors[] = "Row {$rowNum}: phone is required by tenant settings.";
                continue;
            }
            if ($this->isFieldRequired($cfg, 'email') && $email === null) {
                $errors[] = "Row {$rowNum}: email is required by tenant settings.";
                continue;
            }
            if ($this->isFieldRequired($cfg, 'id_number') && $nationalId === null) {
                $errors[] = "Row {$rowNum}: national_id is required by tenant settings.";
                continue;
            }
            if ($createPortal && $email === null) {
                $errors[] = "Row {$rowNum}: create_portal_login requires an email.";
                continue;
            }

            $risk = $normalize($row[$colIndex['risk_level']] ?? 'normal');
            if ($risk === '') {
                $risk = 'normal';
            }
            if (! in_array($risk, ['normal', 'medium', 'high'], true)) {
                $errors[] = "Row {$rowNum}: risk_level must be normal|medium|high.";
                continue;
            }

            $payload = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'national_id' => $nationalId,
                'risk_level' => $risk,
                'notes' => $notes,
            ];

            try {
                $tenant = null;
                if ($email !== null) {
                    $tenant = PmTenant::query()->where('email', $email)->first();
                }
                if (! $tenant && $phone !== null) {
                    $tenant = PmTenant::query()->where('phone', $phone)->first();
                }
                if (! $tenant && $nationalId !== null) {
                    $tenant = PmTenant::query()->where('national_id', $nationalId)->first();
                }

                $user = null;
                if ($createPortal && $email !== null) {
                    $user = User::query()->where('email', $email)->first();
                    if (! $user) {
                        $user = User::query()->create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make(Str::password(14, symbols: false)),
                            'property_portal_role' => 'tenant',
                            'email_verified_at' => now(),
                        ]);
                        $portalLoginsCreated++;
                    }
                }

                if ($tenant) {
                    $tenant->update([
                        ...$payload,
                        'user_id' => $createPortal ? ($user?->id ?? $tenant->user_id) : $tenant->user_id,
                    ]);
                    $updated++;
                } else {
                    PmTenant::query()->create([
                        ...$payload,
                        'user_id' => $createPortal ? $user?->id : null,
                        'agent_user_id' => $this->tenantWorkspaceOwnerId(),
                    ]);
                    $created++;
                }

                if ($createPortal && $user && Schema::hasTable('user_module_accesses')) {
                    UserModuleAccess::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'module' => 'property',
                        ],
                        [
                            'status' => UserModuleAccess::STATUS_APPROVED,
                            'approved_at' => now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);

        $stats = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => count($errors),
            'portal_logins_created' => $portalLoginsCreated,
        ];

        return redirect()
            ->route('property.tenants.directory', ['tenant_import' => '1'])
            ->with('success', "Import finished. Created {$created}, updated {$updated}.")
            ->with('tenant_import_stats', $stats)
            ->with('tenant_import_errors', array_slice($errors, 0, 200));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantListPayload(string $pageTitle, string $pageSubtitle, bool $showTenantForm): array
    {
        $request = request();
        $tenantQuery = $this->buildTenantDirectoryQuery($request);
        $stats = $this->tenantDirectoryStatsFromQuery($request);
        $perPage = $this->directoryPerPage($request);
        $tenants = $tenantQuery
            ->with(['leases' => function ($query): void {
                $query->where('status', PmLease::STATUS_ACTIVE)
                    ->with(['units.property'])
                    ->orderByDesc('start_date');
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $statementLedger = app(TenantStatementLedgerService::class);
        $rows = $tenants->getCollection()->map(function (PmTenant $t) use ($statementLedger) {
            $leaseEnd = $t->leases_max_end_date
                ? (string) \Illuminate\Support\Carbon::parse((string) $t->leases_max_end_date)->format('Y-m-d')
                : '—';
            $activeLease = $t->leases->first();
            $activeUnit = $activeLease?->units->first();
            $unitLabel = $activeUnit
                ? trim(($activeUnit->property->name ?? '').' / '.$activeUnit->label, ' /')
                : '—';
            $deleteConfirm = e("Delete {$t->name} and all related records? This cannot be undone.");
            // Statement closing already includes every receipt. Do not subtract
            // the leftover-credit wallet again — that turns a settled deposit
            // payment into a fake CR extra on the directory.
            $accountBalance = $statementLedger->closingBalance($t);

            $actions = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                '<a href="'.route('property.tenants.show', $t).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">View</a>'.
                '<a href="'.route('property.tenants.edit', $t).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">Edit</a>'.
                '<a href="'.route('property.tenants.leases').'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Leases</a>'.
                '<a href="'.route('property.tenants.notices').'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Notices</a>'.
                '<form method="POST" action="'.route('property.tenants.destroy', $t).'" onsubmit="return confirm(\''.$deleteConfirm.'\')">'.
                csrf_field().
                method_field('DELETE').
                '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>'.
                '</form>'.
                '</div>'.
                '</details>'.
                '</div>'
            );

            return [
                new HtmlString('<a href="'.route('property.tenants.show', $t).'" class="font-medium text-slate-800 hover:text-indigo-700 hover:underline">'.$t->name.'</a>'),
                $t->account_number ?? '—',
                PhoneLink::html($t->phone),
                $t->email ?? '—',
                $unitLabel,
                WorkspaceRowAlert::accountBalance($accountBalance),
                $activeLease?->monthly_rent !== null
                    ? number_format((float) $activeLease->monthly_rent, 2)
                    : '—',
                LeaseStandingCharges::directoryCell($activeLease),
                $activeLease?->start_date?->format('Y-m-d') ?? '—',
                $leaseEnd,
                (string) $t->leases_count,
                TenantProfileStatus::badge($t),
                ucfirst($t->risk_level),
                $actions,
            ];
        })->all();

        $cascade = app(PropertyFilterCascadeCatalog::class);
        $propertyId = (int) $request->integer('property_id');
        $unitId = (int) $request->integer('unit_id');

        return [
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'showTenantForm' => $showTenantForm,
            'expectedColumns' => $this->tenantImportColumns(),
            'lastImportStats' => session('tenant_import_stats'),
            'lastImportErrors' => session('tenant_import_errors', []),
            'openImportModal' => request()->boolean('tenant_import'),
            'tenantFields' => $this->tenantFieldConfig(),
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
            'stats' => $stats,
            'duplicateGroups' => $this->tenantDuplicateGroups(),
            'filters' => [
                'q' => (string) request()->string('q'),
                'property_id' => $propertyId > 0 ? (string) $propertyId : '0',
                'unit_id' => $unitId > 0 ? (string) $unitId : '0',
                'risk' => (string) request()->string('risk'),
                'status' => (string) request()->string('status'),
                'balance' => (string) request()->string('balance'),
                'portal' => (string) request()->string('portal'),
                'per_page' => $perPage,
            ],
            'properties' => $cascade->properties(),
            'units' => $cascade->unitsForProperty($propertyId),
            'filterCascadeCatalog' => $cascade->fromLeases(),
            'tenantPager' => $tenants,
            'columns' => ['Tenant', 'Ac/No', 'Phone', 'Email', 'Unit', 'A/c balance', 'Rent', 'Charges', 'Lease start', 'Lease end', 'Leases', 'Status', 'Risk', 'Actions'],
            'tableRows' => $rows,
        ];
    }

    /**
     * Groups of tenants that look like true duplicates (not multi-unit same person).
     *
     * Same name on different units is normal in Ezen (one person booked as two residents)
     * and is excluded from merge warnings.
     *
     * @return list<array{type: string, label: string, key: string, count: int, severity: string, note: string|null, tenants: list<array<string, mixed>>}>
     */
    private function tenantDuplicateGroups(): array
    {
        $groups = [];
        $placeholderNames = [
            'OCCP', 'OCCUPIED', 'VACANT', 'OWNER', 'OWNER (LLD)', 'LLD', 'N/A', 'NA', 'NONE', '—', '-',
        ];

        $nameKeys = PmTenant::query()
            ->select('name', DB::raw('COUNT(*) as c'))
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->groupBy('name')
            ->having('c', '>', 1)
            ->orderByDesc('c')
            ->limit(40)
            ->get();

        foreach ($nameKeys as $row) {
            $key = trim((string) $row->name);
            if ($key === '' || in_array(mb_strtoupper($key), $placeholderNames, true)) {
                continue;
            }

            $tenants = PmTenant::query()
                ->where('name', $key)
                ->with(['leases' => function ($query): void {
                    $query->where('status', PmLease::STATUS_ACTIVE)
                        ->with(['units.property'])
                        ->orderByDesc('start_date');
                }])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            if ($tenants->count() < 2) {
                continue;
            }

            $unitKeys = $tenants
                ->map(fn (PmTenant $t) => $this->activeUnitKey($t))
                ->filter()
                ->values();
            $uniqueUnits = $unitKeys->unique()->count();
            $hasDistinctUnits = $unitKeys->count() >= 2 && $uniqueUnits === $unitKeys->count();

            // One person booked on two units → two TNT rows in Ezen; not a merge candidate.
            if ($hasDistinctUnits) {
                continue;
            }

            $groups[] = [
                'type' => 'name',
                'label' => 'Same name',
                'key' => $key,
                'count' => (int) $row->c,
                'severity' => 'warning',
                'note' => 'Same name without distinct units — compare before merging.',
                'tenants' => $tenants->map(fn (PmTenant $t) => $this->duplicateTenantCard($t))->all(),
            ];
        }

        if (Schema::hasColumn('pm_tenants', 'phone')) {
            $phoneKeys = PmTenant::query()
                ->select('phone', DB::raw('COUNT(*) as c'))
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->groupBy('phone')
                ->having('c', '>', 1)
                ->orderByDesc('c')
                ->limit(40)
                ->get();

            foreach ($phoneKeys as $row) {
                $key = trim((string) $row->phone);
                if ($key === '') {
                    continue;
                }

                $tenants = PmTenant::query()
                    ->where('phone', $key)
                    ->with(['leases' => function ($query): void {
                        $query->where('status', PmLease::STATUS_ACTIVE)
                            ->with(['units.property'])
                            ->orderByDesc('start_date');
                    }])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();

                if ($tenants->count() < 2) {
                    continue;
                }

                $groups[] = [
                    'type' => 'phone',
                    'label' => 'Same phone',
                    'key' => $key,
                    'count' => (int) $row->c,
                    'severity' => 'warning',
                    'note' => 'Shared phone across tenant profiles.',
                    'tenants' => $tenants->map(fn (PmTenant $t) => $this->duplicateTenantCard($t))->all(),
                ];
            }
        }

        if (Schema::hasColumn('pm_tenants', 'account_number')) {
            $accountKeys = PmTenant::query()
                ->select('account_number', DB::raw('COUNT(*) as c'))
                ->whereNotNull('account_number')
                ->where('account_number', '!=', '')
                ->groupBy('account_number')
                ->having('c', '>', 1)
                ->orderByDesc('c')
                ->limit(20)
                ->get();

            foreach ($accountKeys as $row) {
                $key = strtoupper(trim((string) $row->account_number));
                if ($key === '') {
                    continue;
                }

                $tenants = PmTenant::query()
                    ->where('account_number', $key)
                    ->with(['leases' => function ($query): void {
                        $query->where('status', PmLease::STATUS_ACTIVE)
                            ->with(['units.property'])
                            ->orderByDesc('start_date');
                    }])
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();

                if ($tenants->count() < 2) {
                    continue;
                }

                $groups[] = [
                    'type' => 'account_number',
                    'label' => 'Same account number',
                    'key' => $key,
                    'count' => (int) $row->c,
                    'severity' => 'danger',
                    'note' => 'Identical Ac/No — almost certainly a true duplicate.',
                    'tenants' => $tenants->map(fn (PmTenant $t) => $this->duplicateTenantCard($t))->all(),
                ];
            }
        }

        // Placeholder names from legacy registers inflate the tenant count vs Ezen.
        $placeholders = PmTenant::query()
            ->where(function ($query) use ($placeholderNames): void {
                foreach ($placeholderNames as $placeholder) {
                    $query->orWhereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($placeholder)]);
                }
            })
            ->with(['leases' => function ($query): void {
                $query->where('status', PmLease::STATUS_ACTIVE)
                    ->with(['units.property'])
                    ->orderByDesc('start_date');
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(40)
            ->get();

        if ($placeholders->isNotEmpty()) {
            $groups[] = [
                'type' => 'placeholder',
                'label' => 'Placeholder names (legacy)',
                'key' => 'OCCP / OCCUPIED / VACANT…',
                'count' => $placeholders->count(),
                'severity' => 'info',
                'note' => 'Imported when the old register had no real resident name. These often explain a higher tenant count than Ezen. Rename to the real tenant or remove if the unit should be vacant.',
                'tenants' => $placeholders->map(fn (PmTenant $t) => $this->duplicateTenantCard($t))->all(),
            ];
        }

        return $groups;
    }

    private function activeUnitKey(PmTenant $tenant): ?string
    {
        $unit = $tenant->leases->first()?->units->first();
        if (! $unit) {
            return null;
        }

        return (int) $unit->property_id.'|'.mb_strtoupper(trim((string) $unit->label));
    }

    /**
     * @return array{id: int, name: string, account_number: string, phone: string, email: string, unit: string, created_at: string, show_url: string}
     */
    private function duplicateTenantCard(PmTenant $tenant): array
    {
        $unit = $tenant->relationLoaded('leases')
            ? $tenant->leases->first()?->units->first()
            : null;
        $unitLabel = $unit
            ? trim(($unit->property->name ?? '').' / '.$unit->label, ' /')
            : '—';

        return [
            'id' => (int) $tenant->id,
            'name' => (string) $tenant->name,
            'account_number' => (string) ($tenant->account_number ?: '—'),
            'phone' => (string) ($tenant->phone ?: '—'),
            'email' => (string) ($tenant->email ?: '—'),
            'unit' => $unitLabel !== '' ? $unitLabel : '—',
            'created_at' => $tenant->created_at?->format('Y-m-d H:i') ?? '—',
            'show_url' => route('property.tenants.show', $tenant, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantCompliancePayload(): array
    {
        $request = request();
        $tenantQuery = $this->buildTenantComplianceQuery($request);
        $stats = $this->tenantComplianceStatsFromQuery($request);
        $perPage = $this->directoryPerPage($request);
        $tenants = $tenantQuery
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $rows = $tenants->getCollection()->map(function (PmTenant $t) {
            $actions = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                '<a href="'.route('property.tenants.show', $t).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">View</a>'.
                '<a href="'.route('property.tenants.edit', $t).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">Edit profile</a>'.
                '</div>'.
                '</details>'.
                '</div>'
            );

            return [
                new HtmlString('<a href="'.route('property.tenants.show', $t).'" class="font-medium text-slate-800 hover:text-indigo-700 hover:underline">'.$t->name.'</a>'),
                $t->account_number ?? '—',
                $t->national_id ?: '—',
                PhoneLink::html($t->phone),
                $t->email ?? '—',
                PhoneLink::html($t->emergency_contact),
                TenantCompliancePresentation::riskCell($t),
                TenantCompliancePresentation::portalCell($t),
                TenantProfileStatus::badge($t),
                TenantCompliancePresentation::gapsCell($t),
                $actions,
            ];
        })->all();

        return [
            'pageTitle' => 'Tenant compliance',
            'pageSubtitle' => 'Profile completeness — ID, contacts, risk flags, and portal access.',
            'stats' => $stats,
            'filters' => [
                'q' => (string) request()->string('q'),
                'risk' => (string) request()->string('risk'),
                'status' => (string) request()->string('status'),
                'portal' => (string) request()->string('portal'),
                'compliance' => (string) request()->string('compliance'),
                'per_page' => $perPage,
            ],
            'tenantPager' => $tenants,
            'columns' => ['Tenant', 'Ac/No', 'National ID', 'Phone', 'Email', 'Emergency contact', 'Risk', 'Portal', 'Status', 'Gaps', 'Actions'],
            'tableRows' => $rows,
        ];
    }

    private function buildTenantComplianceQuery(Request $request): Builder
    {
        $query = PmTenant::query();
        TenantProfileStatus::addCounts($query);
        $this->applyTenantDirectoryFilters($query, $request);
        $this->applyTenantComplianceFilters($query, $request);

        return $query;
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    private function tenantComplianceStatsFromQuery(Request $request): array
    {
        $filteredTenants = PmTenant::query();
        $this->applyTenantDirectoryFilters($filteredTenants, $request);
        $this->applyTenantComplianceFilters($filteredTenants, $request);

        $aggregates = (clone $filteredTenants)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN TRIM(COALESCE(national_id, '')) = '' THEN 1 ELSE 0 END), 0) as missing_id_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN TRIM(COALESCE(phone, '')) = '' THEN 1 ELSE 0 END), 0) as missing_phone_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN pm_tenants.risk_level = 'high' THEN 1 ELSE 0 END), 0) as high_risk_count")
            ->selectRaw('COALESCE(SUM(CASE WHEN pm_tenants.user_id IS NOT NULL THEN 1 ELSE 0 END), 0) as portal_count')
            ->first();

        $totalTenants = (int) ($aggregates->total_count ?? 0);
        $missingId = (int) ($aggregates->missing_id_count ?? 0);
        $missingPhone = (int) ($aggregates->missing_phone_count ?? 0);
        $highRisk = (int) ($aggregates->high_risk_count ?? 0);
        $withPortal = (int) ($aggregates->portal_count ?? 0);

        return [
            ['label' => 'Profiles', 'value' => (string) $totalTenants, 'hint' => 'Matching filters'],
            ['label' => 'Missing ID', 'value' => (string) $missingId, 'hint' => 'Needs KYC update'],
            ['label' => 'Missing phone', 'value' => (string) $missingPhone, 'hint' => 'Contact gap'],
            ['label' => 'High risk', 'value' => (string) $highRisk, 'hint' => 'Manual flag'],
            ['label' => 'Portal login', 'value' => (string) $withPortal, 'hint' => 'With access'],
        ];
    }

    private function applyTenantComplianceFilters(Builder $query, Request $request): void
    {
        $compliance = trim((string) $request->string('compliance'));
        if ($compliance === '') {
            return;
        }

        match ($compliance) {
            'missing_id' => $query->where(function (Builder $builder): void {
                $builder->whereNull('national_id')->orWhere('national_id', '');
            }),
            'missing_phone' => $query->where(function (Builder $builder): void {
                $builder->whereNull('phone')->orWhere('phone', '');
            }),
            'missing_email' => $query->where(function (Builder $builder): void {
                $builder->whereNull('email')->orWhere('email', '');
            }),
            'missing_emergency' => $query->where(function (Builder $builder): void {
                $builder->whereNull('emergency_contact')->orWhere('emergency_contact', '');
            }),
            'high_risk' => $query->where('risk_level', 'high'),
            'no_portal' => $query->whereNull('user_id'),
            'complete' => $query
                ->whereNotNull('national_id')
                ->where('national_id', '!=', '')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('emergency_contact')
                ->where('emergency_contact', '!=', '')
                ->where('risk_level', '!=', 'high'),
            'any' => $query->where(function (Builder $builder): void {
                $builder
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('national_id')->orWhere('national_id', '');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNull('phone')->orWhere('phone', '');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNull('email')->orWhere('email', '');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNull('emergency_contact')->orWhere('emergency_contact', '');
                    })
                    ->orWhere('risk_level', 'high')
                    ->orWhereNull('user_id');
            }),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function tenantImportColumns(): array
    {
        return ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes', 'create_portal_login'];
    }

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $cfg
     * @return list<string>
     */
    private function tenantImportRequiredColumns(array $cfg): array
    {
        $required = ['name', 'risk_level'];
        if ($this->isFieldRequired($cfg, 'phone')) {
            $required[] = 'phone';
        }
        if ($this->isFieldRequired($cfg, 'email')) {
            $required[] = 'email';
        }
        if ($this->isFieldRequired($cfg, 'id_number')) {
            $required[] = 'national_id';
        }

        return $required;
    }

    private function directoryPerPage(Request $request): int
    {
        return \App\Support\ListPageSize::resolve($request->input('per_page'), 100);
    }

    private function buildTenantDirectoryQuery(Request $request): Builder
    {
        $query = PmTenant::query()
            ->withCount(['leases', 'invoices'])
            ->withMax('leases', 'end_date');
        TenantProfileStatus::addCounts($query);

        $this->applyTenantDirectoryFilters($query, $request);

        return $query;
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    private function tenantDirectoryStatsFromQuery(Request $request): array
    {
        $filteredTenants = PmTenant::query();
        $this->applyTenantDirectoryFilters($filteredTenants, $request);

        $aggregates = (clone $filteredTenants)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN pm_tenants.user_id IS NOT NULL THEN 1 ELSE 0 END), 0) as portal_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN pm_tenants.risk_level = 'high' THEN 1 ELSE 0 END), 0) as high_risk_count")
            ->first();

        $activeTenants = (clone $filteredTenants)
            ->whereHas('leases', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE))
            ->count();
        $totalTenants = (int) ($aggregates->total_count ?? 0);

        return [
            ['label' => 'Tenants', 'value' => (string) $totalTenants, 'hint' => 'Filtered records'],
            ['label' => 'Active', 'value' => (string) $activeTenants, 'hint' => 'Occupying a unit'],
            ['label' => 'Not active', 'value' => (string) max(0, $totalTenants - $activeTenants), 'hint' => 'Expired, former, draft, or no lease'],
            ['label' => 'High risk flagged', 'value' => (string) (int) ($aggregates->high_risk_count ?? 0), 'hint' => 'Manual'],
        ];
    }

    private function applyTenantDirectoryFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");

                if (Schema::hasColumn('pm_tenants', 'account_number')) {
                    $builder->orWhere('account_number', 'like', "%{$search}%");
                }
            });
        }

        $risk = trim((string) $request->string('risk'));
        if (in_array($risk, ['normal', 'medium', 'high'], true)) {
            $query->where('risk_level', $risk);
        }

        $portal = trim((string) $request->string('portal'));
        if ($portal === 'with') {
            $query->whereNotNull('user_id');
        } elseif ($portal === 'without') {
            $query->whereNull('user_id');
        }

        TenantProfileStatus::applyFilter($query, trim((string) $request->string('status')));
        TenantDirectoryBalanceFilter::apply($query, trim((string) $request->string('balance')));

        $propertyId = (int) $request->integer('property_id');
        $unitId = (int) $request->integer('unit_id');
        if ($unitId > 0) {
            $query->whereHas('leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_units.id', $unitId));
        } elseif ($propertyId > 0) {
            $query->whereHas('leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_units.property_id', $propertyId));
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $cfg = $this->tenantFieldConfig();
        $createPortal = $request->boolean('create_portal_login');

        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($cfg, 'name')), 'nullable', 'string', 'max:255'],
            'phone' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'phone')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'phone')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
            'email' => $createPortal
                ? ['required', 'email', 'max:255', Rule::unique(User::class, 'email')]
                : [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'email')),
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('pm_tenants', 'email')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
                ],
            'national_id' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'national_id')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
            'risk_level' => ['required', 'in:normal,medium,high'],
            'opening_arrears_items' => ['nullable', 'array'],
            'opening_arrears_items.*.type' => ['required_with:opening_arrears_items', Rule::in(array_keys($this->openingArrearsTypeOptions()))],
            'opening_arrears_items.*.period' => ['required_with:opening_arrears_items', 'date_format:Y-m'],
            'opening_arrears_items.*.amount' => ['required_with:opening_arrears_items', 'numeric', 'min:0.01'],
            'opening_arrears_items.*.label' => ['nullable', 'string', 'max:120'],
            'opening_arrears_items.*.reference' => ['nullable', 'string', 'max:120'],
            'opening_arrears_rent' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_utilities' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_penalties' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_other' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_as_of' => ['nullable', 'date'],
            'opening_arrears_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_portal_login' => ['sometimes', 'boolean'],
            'emergency_contact' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'emergency_contact')),
                'nullable',
                'string',
                'max:255',
            ],
            'account_number' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'account_number')),
                'nullable',
                'string',
                'max:32',
                Rule::unique('pm_tenants', 'account_number')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
        ] + $this->tenantRecordFieldRules());
        $openingArrearsPayload = $this->buildOpeningArrearsPayload($data);

        $plainPassword = null;
        $user = null;

        if ($createPortal) {
            $plainPassword = Str::password(14, symbols: false);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'tenant',
                'email_verified_at' => now(),
            ]);

            // Auto-approve tenant accounts for the Property module so their portal login works immediately.
            if (Schema::hasTable('user_module_accesses')) {
                UserModuleAccess::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'module' => 'property',
                    ],
                    [
                        'status' => UserModuleAccess::STATUS_APPROVED,
                        'approved_at' => now(),
                    ]
                );
            }
        }

        $tenant = PmTenant::query()->create([
            'user_id' => $user?->id,
            'agent_user_id' => $this->tenantWorkspaceOwnerId(),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $createPortal ? Str::lower($data['email']) : ($data['email'] ?? null),
            'national_id' => $data['national_id'] ?? null,
            'emergency_contact' => $this->normalizeTenantEmergencyContact($data['emergency_contact'] ?? null),
            'account_number' => $this->normalizeTenantAccountNumber($data['account_number'] ?? null),
            'risk_level' => $data['risk_level'],
            ...$openingArrearsPayload,
            'notes' => $data['notes'] ?? null,
            ...$this->extractTenantRecordAttributes($request, $data),
        ]);
        $this->persistTenantPhoto($request, $tenant);

        $nextSteps = [
            'title' => 'Tenant saved',
            'message' => 'Step 1 of 4 complete. Next, allocate a vacant unit, then raise the first rent bill and record the opening payment.',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'national_id' => $tenant->national_id,
                'opening_arrears_amount' => (float) ($tenant->opening_arrears_amount ?? 0),
                'opening_arrears_rent' => (float) ($tenant->opening_arrears_rent ?? 0),
                'opening_arrears_utilities' => (float) ($tenant->opening_arrears_utilities ?? 0),
                'opening_arrears_penalties' => (float) ($tenant->opening_arrears_penalties ?? 0),
                'opening_arrears_other' => (float) ($tenant->opening_arrears_other ?? 0),
                'opening_arrears_items_count' => count((array) ($tenant->opening_arrears_items ?? [])),
            ],
            'actions' => [
                [
                    'label' => 'Allocate vacant unit (create lease)',
                    'href' => route('property.tenants.leases', ['pm_tenant_id' => $tenant->id], absolute: false),
                    'kind' => 'primary',
                    'icon' => 'fa-solid fa-key',
                    'turbo_frame' => 'property-main',
                ],
                [
                    'label' => 'Back to tenant list',
                    'href' => route('property.tenants.directory', absolute: false),
                    'kind' => 'secondary',
                    'icon' => 'fa-solid fa-users',
                    'turbo_frame' => 'property-main',
                ],
            ],
        ];

        if ($user !== null && $plainPassword !== null) {
            $emailSubject = __('Your tenant portal login');
            $emailLogBody = __('Tenant portal credentials emailed to :name. Temporary password omitted from this log.', [
                'name' => $data['name'],
            ]);
            try {
                Mail::to($user->email)->send(new TenantPortalCredentialsMail(
                    tenantName: $data['name'],
                    email: $user->email,
                    plainPassword: $plainPassword,
                    loginUrl: url(route('property.tenant.login', [], false)),
                    tenantHomeUrl: url(route('property.tenant.home', [], false)),
                ));

                if (Schema::hasTable('pm_message_logs')) {
                    PmMessageLog::query()->create([
                        'user_id' => $request->user()?->id,
                        'channel' => 'email',
                        'to_address' => (string) $user->email,
                        'subject' => $emailSubject,
                        'body' => $emailLogBody,
                        'delivery_status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('tenant_portal_welcome_mail_failed', [
                    'message' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                if (Schema::hasTable('pm_message_logs')) {
                    try {
                        PmMessageLog::query()->create([
                            'user_id' => $request->user()?->id,
                            'channel' => 'email',
                            'to_address' => (string) $user->email,
                            'subject' => $emailSubject,
                            'body' => $emailLogBody,
                            'delivery_status' => 'failed',
                            'delivery_error' => $e->getMessage(),
                        ]);
                    } catch (\Throwable) {
                        // ignore log failures
                    }
                }

                return back()
                    ->with('success', 'Tenant saved with portal login.')
                    ->with('next_steps', $nextSteps)
                    ->with('error', 'Email could not be sent — share the login link and a password reset manually, or check your mail configuration (MAIL_* in .env).');
            }

            return back()
                ->with('success', 'Tenant saved. Portal login details were emailed.')
                ->with('next_steps', $nextSteps);
        }

        return back()
            ->with('success', 'Tenant saved.')
            ->with('next_steps', $nextSteps);
    }

    public function storeJson(Request $request)
    {
        $cfg = $this->tenantFieldConfig();
        $createPortal = in_array(mb_strtolower(trim((string) $request->input('create_portal_login', '0'))), ['1', 'true', 'yes', 'on'], true);
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($cfg, 'name')), 'nullable', 'string', 'max:255'],
            'phone' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'phone')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'phone')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
            'email' => [
                ...($createPortal
                    ? ['required']
                    : [Rule::requiredIf($this->isFieldRequired($cfg, 'email'))]),
                'nullable',
                'email',
                'max:255',
                Rule::unique('pm_tenants', 'email')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
                ...($createPortal ? [Rule::unique(User::class, 'email')] : []),
            ],
            'national_id' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'national_id')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
            'risk_level' => ['nullable', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_portal_login' => ['nullable'],
            'emergency_contact' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'emergency_contact')),
                'nullable',
                'string',
                'max:255',
            ],
            'account_number' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'account_number')),
                'nullable',
                'string',
                'max:32',
                Rule::unique('pm_tenants', 'account_number')->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId())),
            ],
        ] + $this->tenantRecordFieldRules());

        $user = null;
        if ($createPortal) {
            $plainPassword = Str::password(14, symbols: false);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower((string) $data['email']),
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'tenant',
                'email_verified_at' => now(),
            ]);
            if (Schema::hasTable('user_module_accesses')) {
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'property'],
                    ['status' => UserModuleAccess::STATUS_APPROVED, 'approved_at' => now()]
                );
            }
        }

        $tenant = PmTenant::query()->create([
            'user_id' => $user?->id,
            'agent_user_id' => $this->tenantWorkspaceOwnerId(),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? Str::lower((string) $data['email']) : null,
            'national_id' => $data['national_id'] ?? null,
            'emergency_contact' => $this->normalizeTenantEmergencyContact($data['emergency_contact'] ?? null),
            'account_number' => $this->normalizeTenantAccountNumber($data['account_number'] ?? null),
            'risk_level' => $data['risk_level'] ?? 'normal',
            'notes' => $data['notes'] ?? null,
            ...$this->extractTenantRecordAttributes($request, $data),
        ]);
        $this->persistTenantPhoto($request, $tenant);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $tenant->id,
                'label' => \App\Support\Property\PmTenantSelectOptions::label($tenant),
                'search' => \App\Support\Property\PmTenantSelectOptions::searchText($tenant),
            ],
            'message' => 'Tenant created.',
        ]);
    }

    public function show(Request $request, PmTenant $tenant): View
    {
        $activeTab = PropertyEntityHub::activeTabFromRequest($request, 'tenant');

        $leaseRelations = ['units.property'];
        if (Schema::hasTable('lease_deposit_lines')) {
            $leaseRelations[] = 'depositLines';
        }

        $tenant->load([
            'leases' => fn ($q) => $q->with($leaseRelations)->orderByDesc('start_date'),
        ])->loadCount(['leases', 'invoices']);

        app(InvoiceStateIntegrityService::class)->repairAllocationDriftForTenant((int) $tenant->id);

        $formulas = app(FinancialReportingFormulaService::class);
        $billing = $formulas->tenantBillingSnapshot($tenant);
        $profileStatus = TenantProfileStatus::forTenant($tenant);

        $leaseRows = $tenant->leases->map(function ($lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
            $extras = $this->leaseStandingChargeLines($lease);

            return [
                'id' => $lease->id,
                'status' => (string) $lease->status,
                'start' => $lease->start_date?->format('Y-m-d') ?? '—',
                'end' => $lease->end_date?->format('Y-m-d') ?? '—',
                'rent' => (float) $lease->monthly_rent,
                'rent_due_day' => $lease->rent_due_day,
                'units' => $units !== '' ? $units : '—',
                'deposit' => (float) ($lease->deposit_amount ?? 0),
                'standing_total' => (float) collect($extras)->sum('amount'),
                'standing_lines' => $extras,
            ];
        });

        $activeLeases = $tenant->leases->filter(fn ($lease) => $lease->status === PmLease::STATUS_ACTIVE);
        $occupancyUnits = $activeLeases
            ->flatMap(fn ($lease) => $lease->units)
            ->unique('id')
            ->values();
        $occupancyLabel = $occupancyUnits->isEmpty()
            ? '—'
            : $occupancyUnits->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');

        $creditService = app(TenantCreditService::class);
        $creditBalance = $creditService->balanceForTenant((int) $tenant->id);
        $creditTransactions = $creditService->isEnabled()
            ? $creditService->ledgerForTenant((int) $tenant->id, 20)
            : collect();

        $hubOpenInvoices = $tenant->invoices()
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereNotIn('status', [PmInvoice::STATUS_CANCELLED, PmInvoice::STATUS_DRAFT])
            ->orderBy('due_date')
            ->limit(40)
            ->get();

        $hubLeases = $tenant->leases;
        $hubUnits = $tenant->leases
            ->flatMap(fn ($lease) => $lease->units)
            ->unique('id')
            ->values();
        $unitIds = $hubUnits->pluck('id')->filter()->values();

        $maintenanceRequests = collect();
        if (Schema::hasTable('pm_maintenance_requests') && $unitIds->isNotEmpty()) {
            $maintenanceRequests = \App\Models\PmMaintenanceRequest::query()
                ->with('unit.property')
                ->where(function ($q) use ($tenant, $unitIds) {
                    $q->whereIn('property_unit_id', $unitIds);
                    if (Schema::hasColumn('pm_maintenance_requests', 'pm_tenant_id')) {
                        $q->orWhere('pm_tenant_id', $tenant->id);
                    }
                })
                ->orderByDesc('id')
                ->limit(25)
                ->get();
        }

        $statementLedger = app(TenantStatementLedgerService::class);
        $recentLedger = $statementLedger->build($tenant, null, null);
        $statementApplications = $statementLedger->applicationsByPayment($recentLedger['entries']);
        $recentInvoices = $recentLedger['invoices']
            ->sortByDesc(fn ($invoice) => $invoice->issue_date?->timestamp ?? 0)
            ->take(25)
            ->values();
        $recentPayments = $recentLedger['payments']
            ->sortByDesc(fn ($payment) => $payment->paid_at?->timestamp ?? 0)
            ->take(25)
            ->values();
        $recentRegisterReceipts = $recentLedger['registerReceipts']
            ->sortByDesc(fn ($receipt) => optional($receipt->txn_date ?? $receipt->banking_date)->timestamp ?? 0)
            ->take(25)
            ->values();
        $lastPayment = $recentPayments->first(
            fn ($payment) => (string) $payment->status === PmPayment::STATUS_COMPLETED
        );
        if (! $lastPayment) {
            $lastPayment = $tenant->payments()
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->with('allocations')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->first();
        }
        $lastPaymentAmount = $lastPayment
            ? (float) $lastPayment->amount
            : (float) ($recentRegisterReceipts->first()?->amount ?? 0);
        $recentNotices = PmTenantNotice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $utilityReadings = $unitIds->isEmpty() || ! Schema::hasTable('pm_water_readings')
            ? collect()
            : PmWaterReading::query()
                ->whereIn('property_unit_id', $unitIds)
                ->orderByDesc('billing_month')
                ->orderByDesc('id')
                ->limit(25)
                ->get();

        $standingExtras = $this->tenantStandingExtras($tenant);
        $depositSnapshot = $this->tenantDepositSnapshot($tenant);
        $activityFeed = $this->tenantActivityFeed($tenant, $lastPayment, $lastPaymentAmount, $recentInvoices, $recentNotices);
        $alerts = $this->tenantHubAlerts($tenant, $billing['total_due'] ?? [], $profileStatus);
        $quickActions = [
            ['label' => 'Create invoice', 'modal' => 'showHubInvoiceForm', 'icon' => 'fa-file-invoice', 'tone' => 'primary'],
            ['label' => 'Record payment', 'modal' => 'showHubPaymentForm', 'icon' => 'fa-money-bill'],
            ['label' => 'Record advance', 'modal' => 'showHubAdvanceForm', 'icon' => 'fa-piggy-bank'],
            ['label' => 'New lease', 'modal' => 'showLeaseCreateForm', 'icon' => 'fa-file-signature'],
            ['label' => 'Create notice', 'modal' => 'showHubNoticeForm', 'icon' => 'fa-file-circle-plus'],
            ['label' => 'Maintenance', 'modal' => 'showHubMaintenanceForm', 'icon' => 'fa-wrench'],
            ['label' => 'Edit tenant', 'route' => 'property.tenants.edit', 'params' => ['tenant' => $tenant->id], 'icon' => 'fa-pen-to-square', 'tone' => 'muted'],
            ['label' => 'Full statement', 'route' => 'property.tenants.statement', 'params' => ['tenant' => $tenant->id], 'icon' => 'fa-file-lines'],
        ];

        return property_view('property.agent.tenants.show', [
            'tenant' => $tenant,
            'activeTab' => $activeTab,
            'profileStatus' => $profileStatus,
            'occupancyLabel' => $occupancyLabel,
            'activeLeaseCount' => $activeLeases->count(),
            'monthlyRentTotal' => (float) $activeLeases->sum('monthly_rent'),
            'leaseRows' => $leaseRows,
            'invoiceTotals' => $billing['invoice_totals'],
            'leaseCarryForward' => $billing['lease_carry_forward'],
            'totalDue' => $billing['total_due'],
            'creditBalance' => $creditBalance,
            'creditTransactions' => $creditTransactions,
            'advanceCreditsEnabled' => $creditService->isEnabled(),
            'hubOpenInvoices' => $hubOpenInvoices,
            'hubLeases' => $hubLeases,
            'hubUnits' => $hubUnits,
            'maintenanceRequests' => $maintenanceRequests,
            'noticeTemplate' => (string) PropertyPortalSetting::getValue('template_notice_text', ''),
            'lastPayment' => $lastPayment,
            'lastPaymentAmount' => $lastPaymentAmount,
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
            'statementApplications' => $statementApplications,
            'recentRegisterReceipts' => $recentRegisterReceipts,
            'recentNotices' => $recentNotices,
            'utilityReadings' => $utilityReadings,
            'standingExtras' => $standingExtras,
            'depositSnapshot' => $depositSnapshot,
            'depositRefunds' => $this->tenantDepositRefunds($tenant),
            'activityFeed' => $activityFeed,
            'alerts' => $alerts,
            'quickActions' => $quickActions,
        ]);
    }

    public function statement(Request $request, PmTenant $tenant): View|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        app(InvoiceStateIntegrityService::class)->repairAllocationDriftForTenant((int) $tenant->id);

        $formulas = app(FinancialReportingFormulaService::class);
        $validated = $request->validate([
            'from' => ['nullable', 'string', 'max:32'],
            'to' => ['nullable', 'string', 'max:32'],
            'fy' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'string', 'max:7'],
            'report' => ['nullable', 'in:summary,detail'],
            'export' => ['nullable', 'string'],
            'export_scope' => ['nullable', 'string'],
            'print' => ['nullable'],
        ]);
        $embed = $request->boolean('embed');

        $from = isset($validated['from']) ? trim((string) $validated['from']) : '';
        $to = isset($validated['to']) ? trim((string) $validated['to']) : '';
        $month = isset($validated['month']) ? trim((string) $validated['month']) : '';
        $fy = (int) ($validated['fy'] ?? 0);
        $reportMode = strtolower((string) ($validated['report'] ?? 'detail'));
        $exportScope = strtolower((string) ($validated['export_scope'] ?? ''));
        if (in_array($exportScope, ['summary', 'detail'], true) && ! isset($validated['report'])) {
            $reportMode = $exportScope;
        }

        // Period shortcuts: FY / month / date-range.
        if ($from === '' && $to === '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
            $to = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        } elseif ($from === '' && $to === '' && $fy >= 2000) {
            $from = Carbon::create($fy, 1, 1)->toDateString();
            $to = Carbon::create($fy, 12, 31)->toDateString();
        } elseif ($from !== '' && preg_match('/^\d{4}-\d{2}$/', $from) === 1 && strlen($from) === 7) {
            $from = Carbon::createFromFormat('Y-m', $from)->startOfMonth()->toDateString();
            if ($to !== '' && preg_match('/^\d{4}-\d{2}$/', $to) === 1 && strlen($to) === 7) {
                $to = Carbon::createFromFormat('Y-m', $to)->endOfMonth()->toDateString();
            }
        }

        $fromDate = $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        $ledger = app(TenantStatementLedgerService::class)->build($tenant, $fromDate, $toDate);
        $invoices = $ledger['invoices'];
        $payments = $ledger['payments'];
        $openingArrears = $ledger['openingArrears'];
        $openingBalance = $ledger['openingBalance'];
        $entries = $ledger['entries'];
        $unpostedReceiptTotal = $ledger['unpostedReceiptTotal'];

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = [];
        $exportRows = [];
        if ($fromDate) {
            $rows[] = [
                $fromDate->toDateString(),
                'Opening balance',
                '—',
                'B/F',
                '—',
                '—',
                PropertyMoney::kes($openingBalance),
                '—',
                '—',
            ];
            $exportRows[] = [
                $fromDate->toDateString(),
                'Opening balance',
                '',
                'B/F',
                '',
                number_format($openingBalance, 2, '.', ''),
                number_format($openingBalance, 2, '.', ''),
                '',
            ];
        }

        foreach ($entries as $e) {
            $debit = (float) $e['debit'];
            $credit = (float) $e['credit'];
            $totalDebit += $debit;
            $totalCredit += $credit;
            $running += $debit - $credit;

            $actions = '—';
            if ($e['payment_id']) {
                $actions = new HtmlString(
                    '<a href="'.route('property.payments.receipt.show', ['payment' => $e['payment_id']], false).'" data-turbo="false" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700 font-medium">Receipt</a> '.
                    '<span class="text-slate-300">|</span> '.
                    '<a href="'.route('property.payments.receipt.download', ['payment' => $e['payment_id']], false).'" data-turbo="false" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700 font-medium">Download</a>'
                );
            }

            $rows[] = [
                $e['date'] ?: '—',
                (string) $e['type'],
                (string) $e['ref'],
                (string) $e['description'],
                $debit > 0 ? PropertyMoney::kes($debit) : '—',
                $credit > 0 ? PropertyMoney::kes($credit) : '—',
                PropertyMoney::kes($running),
                (string) ($e['status'] ?? ($e['type'] === 'Invoice' ? 'Issued' : '—')),
                $actions,
            ];
            $exportRows[] = [
                (string) ($e['date'] ?? ''),
                (string) $e['type'],
                (string) $e['ref'],
                (string) $e['description'],
                $debit > 0 ? number_format($debit, 2, '.', '') : '',
                $credit > 0 ? number_format($credit, 2, '.', '') : '',
                number_format($running, 2, '.', ''),
                (string) ($e['status'] ?? ''),
            ];
        }

        $billingSnapshot = $formulas->tenantBillingSnapshot($tenant);
        $canonicalOutstanding = $formulas->tenantTotalDue($tenant);
        $ledgerRunningBalance = $running;
        $closingBalance = round($ledgerRunningBalance, 2);

        $export = strtolower(trim((string) ($validated['export'] ?? $request->query('export', ''))));
        if (in_array($export, ['csv', 'xls', 'xlsx', 'pdf', 'word'], true) || $request->boolean('print')) {
            $periodLabel = collect([
                $fromDate?->toDateString(),
                $toDate?->toDateString(),
            ])->filter()->implode(' to ') ?: 'all-time';
            $slug = 'tenant-'.$tenant->id.'-statement-'.\Illuminate\Support\Str::slug($periodLabel);

            if ($reportMode === 'summary') {
                return TabularExport::stream(
                    $slug.'-summary',
                    ['Metric', 'Value'],
                    function () use ($tenant, $totalDebit, $totalCredit, $closingBalance, $canonicalOutstanding, $unpostedReceiptTotal, $entries, $periodLabel) {
                        yield ['Tenant', (string) $tenant->name];
                        yield ['Period', $periodLabel];
                        yield ['Transactions', (string) count($entries)];
                        yield ['Total debit', number_format($totalDebit, 2, '.', '')];
                        yield ['Total credit', number_format($totalCredit, 2, '.', '')];
                        yield ['Closing balance', number_format($closingBalance, 2, '.', '')];
                        yield ['Amount due', number_format(round($canonicalOutstanding - $unpostedReceiptTotal, 2), 2, '.', '')];
                    },
                    $export !== '' ? $export : 'csv',
                );
            }

            return TabularExport::stream(
                $slug,
                ['Date', 'Type', 'Ref', 'Description', 'Debit', 'Credit', 'Balance', 'Status'],
                function () use ($exportRows) {
                    foreach ($exportRows as $row) {
                        yield $row;
                    }
                },
                $export !== '' ? $export : 'csv',
            );
        }

        $stats = [
            ['label' => 'Tenant', 'value' => $tenant->name, 'hint' => 'Statement owner'],
            ['label' => 'Transactions', 'value' => (string) count($rows), 'hint' => 'Invoices, payments, and imported receipts'],
            ['label' => 'Total debit', 'value' => PropertyMoney::kes($totalDebit), 'hint' => 'Charges and opening arrears'],
            ['label' => 'Total credit', 'value' => PropertyMoney::kes($totalCredit), 'hint' => 'Payments and imported receipts'],
            ['label' => 'Closing balance', 'value' => PropertyMoney::kes($closingBalance), 'hint' => 'Running balance on this statement (matches ledger)'],
            ['label' => 'Amount due', 'value' => PropertyMoney::kes(round($canonicalOutstanding - $unpostedReceiptTotal, 2)), 'hint' => 'Canonical AR + opening arrears − credits'],
        ];

        $tenant->loadMissing([
            'leases' => fn ($q) => $q->with(['units.property'])->orderByDesc('start_date'),
        ]);

        $leaseSummary = $tenant->leases->map(function ($lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
            return [
                'start' => $lease->start_date?->format('Y-m-d') ?? '—',
                'end' => $lease->end_date?->format('Y-m-d') ?? '—',
                'rent' => PropertyMoney::kes((float) ($lease->monthly_rent ?? 0)),
                'units' => $units !== '' ? $units : '—',
                'status' => (string) ($lease->status ?? '—'),
            ];
        })->all();

        $invoiceSummary = [
            'count' => $invoices->count(),
            'total' => (float) $invoices->sum('amount'),
            'paid' => (float) $invoices->sum('amount_paid'),
            'opening_arrears' => $openingArrears,
            'opening_arrears_rent' => (float) ($tenant->opening_arrears_rent ?? 0),
            'opening_arrears_utilities' => (float) ($tenant->opening_arrears_utilities ?? 0),
            'opening_arrears_penalties' => (float) ($tenant->opening_arrears_penalties ?? 0),
            'opening_arrears_other' => (float) ($tenant->opening_arrears_other ?? 0),
            'opening_arrears_items' => collect((array) ($tenant->opening_arrears_items ?? []))
                ->filter(fn ($item): bool => is_array($item) && (float) ($item['amount'] ?? 0) > 0)
                ->values()
                ->all(),
            'outstanding' => $canonicalOutstanding,
            'openCount' => (int) ($billingSnapshot['invoice_totals']['open_count'] ?? 0),
        ];

        $paymentSummary = [
            'count' => $payments->count() + $ledger['registerReceipts']->count(),
            'completedCount' => $payments->where('status', PmPayment::STATUS_COMPLETED)->count() + $ledger['registerReceipts']->count(),
            'pendingCount' => $payments->where('status', PmPayment::STATUS_PENDING)->count(),
            'failedCount' => $payments->where('status', PmPayment::STATUS_FAILED)->count(),
            'completedAmount' => round(
                (float) $payments->where('status', PmPayment::STATUS_COMPLETED)->sum('amount') + $unpostedReceiptTotal,
                2
            ),
            'pendingAmount' => (float) $payments->where('status', PmPayment::STATUS_PENDING)->sum('amount'),
        ];

        return view($embed ? 'property.agent.tenants.statement_embed' : 'property.agent.tenants.statement', [
            'tenant' => $tenant,
            'stats' => $stats,
            'columns' => ['Date', 'Type', 'Ref', 'Description', 'Debit', 'Credit', 'Balance', 'Status', 'Receipt'],
            'tableRows' => $rows,
            'filters' => [
                'from' => $from !== '' ? $from : null,
                'to' => $to !== '' ? $to : null,
                'fy' => $fy > 0 ? $fy : null,
                'month' => $month !== '' ? $month : null,
            ],
            'leaseSummary' => $leaseSummary,
            'invoiceSummary' => $invoiceSummary,
            'paymentSummary' => $paymentSummary,
            'totalDue' => $billingSnapshot['total_due'],
            'embed' => $embed,
            'canRepairAllocations' => (bool) auth()->user()?->hasPmPermission('payments.settle'),
        ]);
    }

    public function repairAllocations(
        Request $request,
        PmTenant $tenant,
        PropertyPaymentAllocationRepairService $repair,
    ): RedirectResponse {
        $result = $repair->repairTenant((int) $tenant->id);

        $message = match (true) {
            $result['allocations_moved'] > 0 => sprintf(
                'Payment allocations rebuilt (oldest invoice first). %d invoice balance(s) corrected across %d payment(s).',
                $result['invoices_synced'],
                $result['allocations_moved'],
            ),
            $result['invoices_synced'] > 0 => sprintf(
                'Invoice balances updated from payment allocations (%d invoice(s)).',
                $result['invoices_synced'],
            ),
            default => 'Allocations already correct — no changes made.',
        };

        $query = array_filter([
            'from' => $request->input('from', $request->query('from')),
            'to' => $request->input('to', $request->query('to')),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('property.tenants.statement', ['tenant' => $tenant->id] + $query)
            ->with('success', $message);
    }

    public function edit(Request $request, PmTenant $tenant): View
    {
        $tenant->loadCount([
            'leases',
            'leases as active_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE),
            'leases as expired_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_EXPIRED),
            'leases as terminated_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_TERMINATED),
            'leases as draft_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_DRAFT),
        ]);

        return view('property.agent.tenants.edit', array_merge([
            'tenant' => $tenant,
            'profileStatus' => TenantProfileStatus::forTenant($tenant),
            'tenantFields' => $this->tenantFieldConfig(),
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
        ], $this->propertyFormModalViewData($request)));
    }

    public function update(Request $request, PmTenant $tenant): RedirectResponse|Response
    {
        $cfg = $this->tenantFieldConfig();

        try {
            $data = $request->validate([
                'name' => [Rule::requiredIf($this->isFieldRequired($cfg, 'name')), 'nullable', 'string', 'max:255'],
                'phone' => [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'phone')),
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('pm_tenants', 'phone')
                        ->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId()))
                        ->ignore($tenant->id),
                ],
                'email' => [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'email')),
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('pm_tenants', 'email')
                        ->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId()))
                        ->ignore($tenant->id),
                ],
                'national_id' => [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('pm_tenants', 'national_id')
                        ->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId()))
                        ->ignore($tenant->id),
                ],
                'risk_level' => ['required', 'in:normal,medium,high'],
                'opening_arrears_items' => ['nullable', 'array'],
                'opening_arrears_items.*.type' => ['required_with:opening_arrears_items', Rule::in(array_keys($this->openingArrearsTypeOptions()))],
                'opening_arrears_items.*.period' => ['required_with:opening_arrears_items', 'date_format:Y-m'],
                'opening_arrears_items.*.amount' => ['required_with:opening_arrears_items', 'numeric', 'min:0.01'],
                'opening_arrears_items.*.label' => ['nullable', 'string', 'max:120'],
                'opening_arrears_items.*.reference' => ['nullable', 'string', 'max:120'],
                'opening_arrears_rent' => ['nullable', 'numeric', 'min:0'],
                'opening_arrears_utilities' => ['nullable', 'numeric', 'min:0'],
                'opening_arrears_penalties' => ['nullable', 'numeric', 'min:0'],
                'opening_arrears_other' => ['nullable', 'numeric', 'min:0'],
                'opening_arrears_amount' => ['nullable', 'numeric', 'min:0'],
                'opening_arrears_as_of' => ['nullable', 'date'],
                'opening_arrears_notes' => ['nullable', 'string', 'max:500'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'emergency_contact' => [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'emergency_contact')),
                    'nullable',
                    'string',
                    'max:255',
                ],
                'account_number' => [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'account_number')),
                    'nullable',
                    'string',
                    'max:32',
                    Rule::unique('pm_tenants', 'account_number')
                        ->where(fn ($q) => $q->where('agent_user_id', $this->tenantWorkspaceOwnerId()))
                        ->ignore($tenant->id),
                ],
            ] + $this->tenantRecordFieldRules());
        } catch (ValidationException $e) {
            if (! PropertyFormModal::fromModal($request)) {
                throw $e;
            }

            $request->flash();
            $tenant->loadCount([
                'leases',
                'leases as active_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE),
                'leases as expired_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_EXPIRED),
                'leases as terminated_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_TERMINATED),
                'leases as draft_leases_count' => fn ($q) => $q->where('status', PmLease::STATUS_DRAFT),
            ]);

            return response(
                view('property.agent.tenants.edit', [
                    'tenant' => $tenant,
                    'profileStatus' => TenantProfileStatus::forTenant($tenant),
                    'tenantFields' => $this->tenantFieldConfig(),
                    'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
                    'inPropertyFormModal' => true,
                ])->withErrors($e->validator),
                422
            );
        }

        $openingArrearsPayload = $this->buildOpeningArrearsPayload($data, $tenant);

        $tenant->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'emergency_contact' => $this->normalizeTenantEmergencyContact($data['emergency_contact'] ?? null),
            'account_number' => $this->normalizeTenantAccountNumber($data['account_number'] ?? null),
            'risk_level' => $data['risk_level'],
            'notes' => $data['notes'] ?? null,
            ...$openingArrearsPayload,
            ...$this->extractTenantRecordAttributes($request, $data),
        ]);
        $this->persistTenantPhoto($request, $tenant);

        return $this->redirectOrPropertyFormModalSuccess(
            $request,
            back()->with('success', 'Tenant updated.'),
            'Tenant updated.',
        );
    }

    public function storeDepositRefund(Request $request, PmTenant $tenant): RedirectResponse
    {
        if (! Schema::hasTable('pm_tenant_deposit_refunds')) {
            return back()->with('error', 'Deposit refunds are not available until the latest migration is applied.');
        }

        $data = $request->validate([
            'refunded_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'property_id' => ['nullable', 'integer'],
            'property_unit_id' => ['nullable', 'integer'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_branch' => ['nullable', 'string', 'max:80'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $occupancy = $this->tenantRefundOccupancy($tenant);
        $propertyId = (int) ($data['property_id'] ?? 0) ?: (int) ($occupancy['property_id'] ?? 0);
        $unitId = (int) ($data['property_unit_id'] ?? 0) ?: (int) ($occupancy['property_unit_id'] ?? 0);
        $bank = [
            'bank_name' => trim((string) ($data['bank_name'] ?? '')) ?: null,
            'bank_branch' => trim((string) ($data['bank_branch'] ?? '')) ?: null,
            'bank_account_name' => trim((string) ($data['bank_account_name'] ?? '')) ?: null,
            'bank_account_number' => trim((string) ($data['bank_account_number'] ?? '')) ?: null,
        ];

        $refund = null;
        DB::transaction(function () use ($request, $tenant, $data, $propertyId, $unitId, $bank, &$refund): void {
            $heldDeposit = null;
            if (Schema::hasTable('pm_tenant_deposits')) {
                $heldDeposit = PmTenantDeposit::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'held')
                    ->orderByDesc('id')
                    ->first();
            }

            $refund = PmTenantDepositRefund::query()->create([
                'tenant_id' => $tenant->id,
                'property_id' => $propertyId > 0 ? $propertyId : null,
                'property_unit_id' => $unitId > 0 ? $unitId : null,
                'pm_tenant_deposit_id' => $heldDeposit?->id,
                'amount' => round((float) $data['amount'], 2),
                'refunded_at' => $data['refunded_at'],
                ...$bank,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => $request->user()?->id,
                'agent_user_id' => $tenant->agent_user_id ?: $request->user()?->id,
            ]);

            $tenantBank = [];
            foreach ($bank as $key => $value) {
                if ($value !== null && trim((string) ($tenant->{$key} ?? '')) === '' && Schema::hasColumn('pm_tenants', $key)) {
                    $tenantBank[$key] = $value;
                }
            }
            if ($tenantBank !== []) {
                $tenant->update($tenantBank);
            }

            if ($heldDeposit) {
                $heldDeposit->update(['status' => 'refunded']);
            }

            if (Schema::hasTable('lease_deposit_lines')) {
                $leaseIds = $tenant->leases()->pluck('id');
                if ($leaseIds->isNotEmpty()) {
                    \App\Models\LeaseDepositLine::query()
                        ->whereIn('pm_lease_id', $leaseIds)
                        ->where('is_refundable', true)
                        ->where('refund_status', '!=', 'refunded')
                        ->update(['refund_status' => 'refunded']);
                }
            }
        });

        if ($refund?->pm_tenant_deposit_id) {
            $deposit = PmTenantDeposit::query()->find($refund->pm_tenant_deposit_id);
            if ($deposit) {
                try {
                    app(\App\Services\Property\PropertyTrustAccountingService::class)
                        ->postTenantDepositRefund($deposit, $request->user()?->id);
                } catch (\Throwable $e) {
                    Log::warning('tenant_deposit_refund_gl_skipped', [
                        'refund_id' => $refund->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()
            ->route('property.tenants.show', ['tenant' => $tenant->id, 'tab' => 'deposits'])
            ->with('success', 'Deposit refund recorded.');
    }

    public function destroy(PmTenant $tenant): RedirectResponse
    {
        $tenantName = $tenant->name;
        $portalUserId = $tenant->user_id;

        DB::transaction(function () use ($tenant, $portalUserId): void {
            if ($portalUserId) {
                $isSharedPortalUser = PmTenant::query()
                    ->withoutGlobalScopes()
                    ->where('user_id', $portalUserId)
                    ->where('id', '!=', $tenant->id)
                    ->exists();

                if (! $isSharedPortalUser) {
                    User::query()
                        ->whereKey($portalUserId)
                        ->where('property_portal_role', 'tenant')
                        ->delete();
                }
            }

            // Tenant relations are removed by FK cascade/null-on-delete rules.
            $tenant->delete();
        });

        return back()->with('success', "Tenant {$tenantName} deleted with all related records.");
    }

    private function tenantWorkspaceOwnerId(): int
    {
        return AgentWorkspaceScope::ownerIdForNewRecord();
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function tenantFieldConfig(): array
    {
        $defaults = [
            'name' => ['enabled' => true, 'required' => true],
            'phone' => ['enabled' => true, 'required' => true],
            'email' => ['enabled' => true, 'required' => false],
            'id_number' => ['enabled' => true, 'required' => false],
            'emergency_contact' => ['enabled' => true, 'required' => false],
            'account_number' => ['enabled' => true, 'required' => false],
        ];
        $raw = PropertyPortalSetting::getValue('system_setup_tenant_fields_json', '');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $defaults)) {
                continue;
            }
            $defaults[$key]['enabled'] = ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
            $defaults[$key]['required'] = (bool) ($row['required'] ?? false);
        }

        return $defaults;
    }

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $config
     */
    private function isFieldRequired(array $config, string $field): bool
    {
        return (bool) (($config[$field]['enabled'] ?? false) && ($config[$field]['required'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantRecordFieldRules(): array
    {
        return [
            'tenant_type' => ['nullable', 'string', Rule::in(array_keys(PmTenant::TYPES))],
            'other_names' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(array_keys(PmTenant::GENDERS))],
            'kra_pin' => ['nullable', 'string', 'max:32'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'town' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'max:64'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_branch' => ['nullable', 'string', 'max:80'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'emergency_contacts' => ['nullable', 'array', 'max:2'],
            'emergency_contacts.*.name' => ['nullable', 'string', 'max:120'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:80'],
            'emergency_contacts.*.phone' => ['nullable', 'string', 'max:64'],
            'emergency_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractTenantRecordAttributes(Request $request, array $data): array
    {
        $attrs = [];
        foreach ([
            'tenant_type', 'other_names', 'gender', 'kra_pin', 'postal_address', 'postal_code', 'town', 'country',
            'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number',
        ] as $key) {
            if (! array_key_exists($key, $data) && ! $request->exists($key)) {
                continue;
            }
            $value = trim((string) ($data[$key] ?? $request->input($key) ?? ''));
            $attrs[$key] = $value !== '' ? $value : null;
        }

        if (array_key_exists('emergency_contacts', $data) || $request->has('emergency_contacts')) {
            $contacts = $this->packEmergencyContacts($data['emergency_contacts'] ?? $request->input('emergency_contacts'));
            $attrs['emergency_contacts'] = $contacts;
            $first = $contacts[0] ?? null;
            if (is_array($first)) {
                $line = trim(implode(' / ', array_filter([
                    trim((string) ($first['name'] ?? '')),
                    trim((string) ($first['phone'] ?? '')),
                ], static fn (string $part): bool => $part !== '')));
                if ($line !== '') {
                    $attrs['emergency_contact'] = $line;
                }
            }
        }

        foreach (array_keys($attrs) as $key) {
            if (! Schema::hasColumn('pm_tenants', $key)) {
                unset($attrs[$key]);
            }
        }

        return $attrs;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{name: string, relationship: string, phone: string, email: string}>
     */
    private function packEmergencyContacts(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : [];
        $packed = [];
        foreach (array_slice(array_values($rows), 0, 2) as $row) {
            $row = is_array($row) ? $row : [];
            $contact = [
                'name' => trim((string) ($row['name'] ?? '')),
                'relationship' => trim((string) ($row['relationship'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
            ];
            if (implode('', $contact) === '') {
                continue;
            }
            $packed[] = $contact;
        }

        return $packed;
    }

    private function persistTenantPhoto(Request $request, PmTenant $tenant): void
    {
        if (! Schema::hasColumn('pm_tenants', 'photo_path') || ! $request->hasFile('photo')) {
            return;
        }

        $file = $request->file('photo');
        if ($file === null) {
            return;
        }

        $path = $file->store('tenant-photos/'.$tenant->id, 'public');
        $previous = trim((string) ($tenant->photo_path ?? ''));
        if ($previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $tenant->updateQuietly(['photo_path' => $path]);
    }

    private function normalizeTenantEmergencyContact(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function normalizeTenantAccountNumber(mixed $value): ?string
    {
        $account = strtoupper(str_replace(' ', '', trim((string) ($value ?? ''))));

        return $account !== '' ? $account : null;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildOpeningArrearsPayload(array $data, ?PmTenant $tenant = null): array
    {
        $items = collect((array) ($data['opening_arrears_items'] ?? []))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'type' => (string) ($item['type'] ?? ''),
                    'period' => (string) ($item['period'] ?? ''),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                    'label' => trim((string) ($item['label'] ?? '')),
                    'reference' => trim((string) ($item['reference'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['type'] !== '' && $item['period'] !== '' && $item['amount'] > 0)
            ->values();

        $categories = [
            'opening_arrears_rent' => 0.0,
            'opening_arrears_utilities' => 0.0,
            'opening_arrears_penalties' => 0.0,
            'opening_arrears_other' => 0.0,
        ];
        $utilityTypes = ['water', 'electricity', 'service_charge', 'garbage', 'internet', 'parking', 'utility_other'];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            $amount = (float) $item['amount'];
            if ($type === 'rent') {
                $categories['opening_arrears_rent'] += $amount;
            } elseif ($type === 'penalty') {
                $categories['opening_arrears_penalties'] += $amount;
            } elseif (in_array($type, $utilityTypes, true)) {
                $categories['opening_arrears_utilities'] += $amount;
            } else {
                $categories['opening_arrears_other'] += $amount;
            }
        }

        // Backward compatibility for older form submissions without item rows.
        if ($items->isEmpty()) {
            $categories['opening_arrears_rent'] = (float) ($data['opening_arrears_rent'] ?? 0);
            $categories['opening_arrears_utilities'] = (float) ($data['opening_arrears_utilities'] ?? 0);
            $categories['opening_arrears_penalties'] = (float) ($data['opening_arrears_penalties'] ?? 0);
            $categories['opening_arrears_other'] = (float) ($data['opening_arrears_other'] ?? 0);
        }

        $computedTotal = array_sum($categories);
        $manualTotal = (float) ($data['opening_arrears_amount'] ?? 0);
        $total = $computedTotal > 0 ? $computedTotal : $manualTotal;
        $asOf = $total > 0
            ? ($data['opening_arrears_as_of'] ?? ($tenant?->opening_arrears_as_of?->toDateString() ?? now()->toDateString()))
            : null;

        return [
            'opening_arrears_rent' => (float) $categories['opening_arrears_rent'],
            'opening_arrears_utilities' => (float) $categories['opening_arrears_utilities'],
            'opening_arrears_penalties' => (float) $categories['opening_arrears_penalties'],
            'opening_arrears_other' => (float) $categories['opening_arrears_other'],
            'opening_arrears_amount' => $total,
            'opening_arrears_as_of' => $asOf,
            'opening_arrears_notes' => $data['opening_arrears_notes'] ?? null,
            'opening_arrears_items' => $items->all(),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function openingArrearsTypeOptions(): array
    {
        return [
            'rent' => 'Rent',
            'water' => 'Water',
            'electricity' => 'Electricity',
            'service_charge' => 'Service charge',
            'garbage' => 'Garbage',
            'internet' => 'Internet',
            'parking' => 'Parking',
            'utility_other' => 'Other utility',
            'standing_charge' => 'Other standing charge',
            'on_account' => 'On account',
            'penalty' => 'Penalty',
            'other' => 'Other charge',
            'custom_charge' => 'Custom charge',
        ];
    }

    /**
     * @return list<array{lease_id: int, type: string, type_label: string, amount: float, property_name: string, unit_label: string}>
     */
    private function tenantStandingExtras(PmTenant $tenant): array
    {
        $rows = [];
        foreach ($tenant->leases as $lease) {
            if ((string) $lease->status !== PmLease::STATUS_ACTIVE) {
                continue;
            }
            $unit = $lease->units->first();
            foreach ($this->leaseStandingChargeLines($lease) as $line) {
                $rows[] = [
                    'lease_id' => (int) $lease->id,
                    'type' => $line['type'],
                    'type_label' => ucwords(str_replace('_', ' ', $line['type'])),
                    'amount' => $line['amount'],
                    'property_name' => (string) ($unit?->property?->name ?? '—'),
                    'unit_label' => (string) ($unit?->label ?? '—'),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{type: string, amount: float}>
     */
    private function leaseStandingChargeLines(PmLease $lease): array
    {
        return array_map(
            static fn (array $line): array => [
                'type' => $line['type'],
                'amount' => $line['amount'],
            ],
            LeaseStandingCharges::lines($lease),
        );
    }

    /**
     * One row per deposit charge: amount to pay, amount paid, amount still due.
     *
     * @return array{held: float, expected: float, to_pay: float, paid: float, due: float, lines: list<array<string, mixed>>}
     */
    private function tenantDepositSnapshot(PmTenant $tenant): array
    {
        $invoices = $this->depositChargeInvoices($tenant);
        $usedInvoiceIds = [];
        $lines = [];
        $expected = 0.0;

        foreach ($tenant->leases as $lease) {
            foreach ($this->leaseDepositObligations($lease) as $obligation) {
                $expected += $obligation['amount'];
                $invoice = $this->takeMatchingDepositInvoice($invoices, $usedInvoiceIds, $obligation);
                $lines[] = $this->depositChargeRow($obligation, $invoice);
            }
        }

        foreach ($invoices as $invoice) {
            if (in_array($invoice['invoice_id'], $usedInvoiceIds, true)) {
                continue;
            }
            $lines[] = $this->depositChargeRow([
                'item' => $invoice['memo'],
                'subtitle' => null,
                'source' => 'Invoice',
                'amount' => (float) $invoice['invoiced'],
                'kind' => 'other',
            ], $invoice);
            $expected += $invoice['invoiced'];
        }

        $held = 0.0;
        if (Schema::hasTable('pm_tenant_deposits')) {
            $held = (float) PmTenantDeposit::query()
                ->where('tenant_id', $tenant->id)
                ->sum('amount');
        }

        $invoicePaid = 0.0;
        foreach ($lines as $line) {
            if (($line['invoice_id'] ?? null) !== null) {
                $invoicePaid += (float) $line['paid'];
            }
        }
        $pool = max(0.0, round($held - $invoicePaid, 2));
        foreach ($lines as $index => $line) {
            if (($line['invoice_id'] ?? null) !== null || $pool <= 0.009) {
                continue;
            }
            $take = min((float) $line['due'], $pool);
            if ($take <= 0.009) {
                continue;
            }
            $paid = round((float) $line['paid'] + $take, 2);
            $due = round(max(0.0, (float) $line['to_pay'] - $paid), 2);
            $lines[$index]['paid'] = $paid;
            $lines[$index]['due'] = $due;
            $lines[$index]['status'] = $due <= 0.009 ? 'paid' : 'partial';
            $lines[$index]['status_label'] = $due <= 0.009 ? 'Paid' : 'Partially paid';
            $pool = round($pool - $take, 2);
        }

        $toPay = 0.0;
        $paid = 0.0;
        $due = 0.0;
        foreach ($lines as $line) {
            $toPay += (float) $line['to_pay'];
            $paid += (float) $line['paid'];
            $due += (float) $line['due'];
        }

        return [
            'held' => $held,
            'expected' => $expected,
            'to_pay' => round($toPay, 2),
            'paid' => round($paid, 2),
            'due' => round($due, 2),
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array{item: string, subtitle: string, source: string, amount: float, kind: string}>
     */
    private function leaseDepositObligations(PmLease $lease): array
    {
        $rows = [];
        $rentDeposit = (float) ($lease->deposit_amount ?? 0);
        if ($rentDeposit > 0) {
            $rows[] = [
                'item' => 'Rent deposit',
                'subtitle' => 'Lease #'.$lease->id,
                'source' => 'Lease agreement',
                'amount' => $rentDeposit,
                'kind' => 'rent',
            ];
        }
        foreach (is_array($lease->additional_deposits) ? $lease->additional_deposits : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $rows[] = [
                'item' => $label !== '' ? $label : 'Additional deposit',
                'subtitle' => 'Lease #'.$lease->id,
                'source' => 'Lease agreement',
                'amount' => $amount,
                'kind' => 'other',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     * @param  list<int>  $usedInvoiceIds
     * @param  array{item: string, subtitle: ?string, source: string, amount: float, kind: string}  $obligation
     * @return array<string, mixed>|null
     */
    private function takeMatchingDepositInvoice(array $invoices, array &$usedInvoiceIds, array $obligation): ?array
    {
        $fallback = null;
        foreach ($invoices as $invoice) {
            $invoiceId = (int) $invoice['invoice_id'];
            if (in_array($invoiceId, $usedInvoiceIds, true)) {
                continue;
            }
            $sameAmount = abs((float) $invoice['invoiced'] - (float) $obligation['amount']) <= 0.009;
            $memo = strtolower((string) ($invoice['memo'] ?? ''));
            $isRent = $obligation['kind'] === 'rent' && str_contains($memo, 'rent deposit');
            if ($sameAmount && ($obligation['kind'] === 'rent' || $isRent || $fallback === null)) {
                $usedInvoiceIds[] = $invoiceId;

                return $invoice;
            }
            if ($fallback === null && ($isRent || ($obligation['kind'] === 'rent' && str_contains($memo, 'deposit')))) {
                $fallback = $invoice;
            }
        }
        if ($fallback !== null) {
            $usedInvoiceIds[] = (int) $fallback['invoice_id'];
        }

        return $fallback;
    }

    /**
     * @param  array{item: string, subtitle: ?string, source: string, amount: float, kind: string}  $obligation
     * @param  array<string, mixed>|null  $invoice
     * @return array<string, mixed>
     */
    private function depositChargeRow(array $obligation, ?array $invoice): array
    {
        $toPay = (float) $obligation['amount'];
        $paid = $invoice !== null ? (float) $invoice['paid'] : 0.0;
        if ($invoice !== null && $toPay <= 0.009) {
            $toPay = (float) $invoice['invoiced'];
        }
        $due = round(max(0.0, $toPay - $paid), 2);
        $status = $due <= 0.009 ? 'paid' : ($paid > 0.009 ? 'partial' : 'unpaid');

        return [
            'item' => (string) $obligation['item'],
            'subtitle' => $obligation['subtitle'] ?? null,
            'source' => (string) ($obligation['source'] ?? 'Lease agreement'),
            'label' => (string) $obligation['item'],
            'invoice_id' => $invoice['invoice_id'] ?? null,
            'invoice_no' => $invoice['invoice_no'] ?? null,
            'url' => $invoice['url'] ?? null,
            'date' => $invoice['date'] ?? null,
            'to_pay' => round($toPay, 2),
            'paid' => round($paid, 2),
            'due' => $due,
            'status' => $status,
            'status_label' => match ($status) {
                'paid' => 'Paid',
                'partial' => 'Partially paid',
                default => 'Unpaid',
            },
        ];
    }

    /**
     * Deposit invoices, paid or still open, in the old Amount / Total Paid / Amt Due shape.
     *
     * @return list<array{invoice_id: int, invoice_no: string, url: string, date: string, memo: string, invoiced: float, paid: float}>
     */
    private function depositChargeInvoices(PmTenant $tenant): array
    {
        if (! Schema::hasTable('pm_invoices')) {
            return [];
        }

        return PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->where('amount', '>', 0)
            ->where(function ($query): void {
                $query->where('description', 'like', '%DEPOSIT%')
                    ->orWhere('invoice_type', 'like', '%deposit%');
            })
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->map(function (PmInvoice $invoice): array {
                $memo = trim((string) ($invoice->description ?? ''));
                $memo = preg_replace('/^\[[^\]]+\]\s*/', '', $memo) ?? $memo;

                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) ($invoice->invoice_no ?: '#'.$invoice->id),
                    'url' => route('property.revenue.invoices.show', $invoice, false),
                    'date' => $invoice->issue_date?->format('Y-m-d') ?? '—',
                    'memo' => $memo !== '' ? $memo : 'Deposit',
                    'invoiced' => (float) $invoice->amount,
                    'paid' => (float) $invoice->amount_paid,
                ];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmTenantDepositRefund>
     */
    private function tenantDepositRefunds(PmTenant $tenant)
    {
        if (! Schema::hasTable('pm_tenant_deposit_refunds')) {
            return collect();
        }

        return PmTenantDepositRefund::query()
            ->where('tenant_id', $tenant->id)
            ->with(['createdBy:id,name', 'property:id,name', 'unit:id,label'])
            ->orderByDesc('refunded_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{property_id: int, property_unit_id: int}
     */
    private function tenantRefundOccupancy(PmTenant $tenant): array
    {
        if (! $tenant->relationLoaded('leases')) {
            $tenant->load(['leases' => fn ($q) => $q->with('units')->orderByDesc('id')]);
        }

        $lease = $tenant->leases
            ->first(fn ($row) => (string) $row->status === PmLease::STATUS_ACTIVE)
            ?? $tenant->leases->first();
        $unit = $lease?->units?->first();

        return [
            'property_id' => (int) ($unit?->property_id ?? 0),
            'property_unit_id' => (int) ($unit?->id ?? 0),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PmInvoice>  $recentInvoices
     * @param  \Illuminate\Support\Collection<int, PmTenantNotice>  $recentNotices
     * @return list<array{title: string, subtitle: string, at: string, href?: string, tone?: string}>
     */
    private function tenantActivityFeed(
        PmTenant $tenant,
        ?PmPayment $lastPayment,
        float $lastPaymentAmount,
        $recentInvoices,
        $recentNotices,
    ): array {
        $items = [];

        if ($lastPayment) {
            $items[] = [
                'title' => 'Payment received',
                'subtitle' => PropertyMoney::kes($lastPaymentAmount).' · '.strtoupper((string) ($lastPayment->channel ?? 'cash')),
                'at' => $lastPayment->paid_at?->format('Y-m-d') ?? '',
                'href' => route('property.payments.receipt.show', $lastPayment, false),
                'tone' => 'emerald',
            ];
        }

        $latestInvoice = $recentInvoices->first();
        if ($latestInvoice) {
            $items[] = [
                'title' => 'Invoice '.($latestInvoice->invoice_no ?: '#'.$latestInvoice->id),
                'subtitle' => PropertyMoney::kes((float) $latestInvoice->amount).' · '.str_replace('_', ' ', (string) ($latestInvoice->invoice_type ?? 'charge')),
                'at' => $latestInvoice->issue_date?->format('Y-m-d') ?? '',
                'href' => route('property.revenue.invoices.show', $latestInvoice, false),
                'tone' => 'cyan',
            ];
        }

        $latestNotice = $recentNotices->first();
        if ($latestNotice) {
            $items[] = [
                'title' => 'Notice: '.str_replace('_', ' ', (string) ($latestNotice->notice_type ?? 'notice')),
                'subtitle' => ucfirst((string) ($latestNotice->status ?? 'open')),
                'at' => $latestNotice->created_at?->format('Y-m-d') ?? '',
                'href' => route('property.tenants.notices', ['tenant_id' => $tenant->id], false),
                'tone' => 'amber',
            ];
        }

        $latestLease = $tenant->leases->first();
        if ($latestLease) {
            $items[] = [
                'title' => 'Lease #'.$latestLease->id.' · '.ucfirst((string) $latestLease->status),
                'subtitle' => ($latestLease->start_date?->format('Y-m-d') ?? '—').' → '.($latestLease->end_date?->format('Y-m-d') ?? 'open'),
                'at' => $latestLease->start_date?->format('Y-m-d') ?? '',
                'href' => route('property.leases.show', ['lease' => $latestLease->id], false),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $totalDue
     * @param  array{label?: string, key?: string}  $profileStatus
     * @return list<array{label: string, tone: string, href?: string}>
     */
    private function tenantHubAlerts(PmTenant $tenant, array $totalDue, array $profileStatus): array
    {
        $alerts = [];
        $due = (float) ($totalDue['total_due'] ?? 0);
        if ($due > 0.009) {
            $alerts[] = [
                'label' => 'Total due '.PropertyMoney::kes($due),
                'tone' => 'rose',
                'href' => route('property.tenants.show', ['tenant' => $tenant->id, 'tab' => 'invoices'], false),
            ];
        }
        $cf = (float) ($totalDue['uninvoiced_cf'] ?? 0);
        if ($cf > 0.009) {
            $alerts[] = [
                'label' => 'Uninvoiced carry-forward '.PropertyMoney::kes($cf),
                'tone' => 'amber',
            ];
        }
        if (($profileStatus['key'] ?? '') === TenantProfileStatus::EXPIRED) {
            $alerts[] = ['label' => 'Lease expired', 'tone' => 'amber'];
        }
        if (in_array($profileStatus['key'] ?? '', [TenantProfileStatus::FORMER, TenantProfileStatus::INACTIVE], true)) {
            $alerts[] = [
                'label' => ($profileStatus['label'] ?? 'Inactive').' tenant',
                'tone' => 'slate',
            ];
        }
        if (! $tenant->user_id) {
            $alerts[] = ['label' => 'No portal login', 'tone' => 'slate'];
        }

        return $alerts;
    }
}
