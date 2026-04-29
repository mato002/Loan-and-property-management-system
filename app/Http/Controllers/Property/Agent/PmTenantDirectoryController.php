<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\UserModuleAccess;
use App\Mail\TenantPortalCredentialsMail;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\PropertyPortalSetting;
use App\Models\User;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\Property\PropertyMoney;

class PmTenantDirectoryController extends Controller
{
    public function directory(): View
    {
        return view('property.agent.tenants.directory', $this->tenantListPayload(
            pageTitle: 'Tenant list',
            pageSubtitle: 'Operational directory — add tenants here, then leases and billing.',
            showTenantForm: true,
        ));
    }

    public function profiles(): RedirectResponse
    {
        return redirect()->route('property.tenants.directory');
    }

    public function exportDirectoryCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenants = $this->buildTenantDirectoryQuery($request)
            ->orderBy('name')
            ->get();

        $filename = 'tenant_directory_'.now()->format('Ymd_His').'.csv';
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8'];

        return response()->streamDownload(function () use ($tenants): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['name', 'phone', 'email', 'national_id', 'risk_level', 'portal_login', 'leases_count', 'lease_end']);

            foreach ($tenants as $tenant) {
                $leaseEnd = $tenant->leases_max_end_date
                    ? (string) Carbon::parse((string) $tenant->leases_max_end_date)->format('Y-m-d')
                    : '';

                fputcsv($out, [
                    (string) $tenant->name,
                    (string) ($tenant->phone ?? ''),
                    (string) ($tenant->email ?? ''),
                    (string) ($tenant->national_id ?? ''),
                    (string) ($tenant->risk_level ?? 'normal'),
                    $tenant->user_id ? 'yes' : 'no',
                    (string) ($tenant->leases_count ?? 0),
                    $leaseEnd,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
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
                        'agent_user_id' => (int) auth()->id(),
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
        $tenantQuery = $this->buildTenantDirectoryQuery(request());
        $statsSource = (clone $tenantQuery)->get();
        $perPage = $this->directoryPerPage(request());
        $tenants = $tenantQuery
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            ['label' => 'Tenants', 'value' => (string) $statsSource->count(), 'hint' => 'Filtered records'],
            ['label' => 'With portal login', 'value' => (string) $statsSource->whereNotNull('user_id')->count(), 'hint' => 'Linked user'],
            ['label' => 'High risk flagged', 'value' => (string) $statsSource->where('risk_level', 'high')->count(), 'hint' => 'Manual'],
            ['label' => 'Total leases', 'value' => (string) $statsSource->sum('leases_count'), 'hint' => 'Linked'],
        ];

        $rows = $tenants->getCollection()->map(function (PmTenant $t) {
            $leaseEnd = $t->leases_max_end_date
                ? (string) \Illuminate\Support\Carbon::parse((string) $t->leases_max_end_date)->format('Y-m-d')
                : '—';
            $deleteConfirm = e("Delete {$t->name} and all related records? This cannot be undone.");

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
                $t->phone ?? '—',
                $t->email ?? '—',
                $t->national_id ?? '—',
                (string) $t->leases_count,
                $leaseEnd,
                ucfirst($t->risk_level),
                $actions,
            ];
        })->all();

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
            'filters' => [
                'q' => (string) request()->string('q'),
                'risk' => (string) request()->string('risk'),
                'portal' => (string) request()->string('portal'),
                'per_page' => $perPage,
            ],
            'tenantPager' => $tenants,
            'columns' => ['Tenant', 'Phone', 'Email', 'ID / ref', 'Leases', 'Lease end', 'Risk', 'Actions'],
            'tableRows' => $rows,
        ];
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
        $value = (int) $request->integer('per_page', 20);

        return in_array($value, [10, 20, 50, 100], true) ? $value : 20;
    }

    private function buildTenantDirectoryQuery(Request $request): Builder
    {
        $query = PmTenant::query()
            ->withCount(['leases', 'invoices'])
            ->withMax('leases', 'end_date');

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
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

        return $query;
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
                Rule::unique('pm_tenants', 'phone')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
            ],
            'email' => $createPortal
                ? ['required', 'email', 'max:255', Rule::unique(User::class, 'email')]
                : [
                    Rule::requiredIf($this->isFieldRequired($cfg, 'email')),
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('pm_tenants', 'email')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
                ],
            'national_id' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'national_id')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
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
        ]);
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
            'agent_user_id' => (int) $request->user()->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $createPortal ? Str::lower($data['email']) : ($data['email'] ?? null),
            'national_id' => $data['national_id'] ?? null,
            'risk_level' => $data['risk_level'],
            ...$openingArrearsPayload,
            'notes' => $data['notes'] ?? null,
        ]);

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
            try {
                Mail::to($user->email)->send(new TenantPortalCredentialsMail(
                    tenantName: $data['name'],
                    email: $user->email,
                    plainPassword: $plainPassword,
                    loginUrl: url(route('property.tenant.login', [], false)),
                    tenantHomeUrl: url(route('property.tenant.home', [], false)),
                ));
            } catch (\Throwable $e) {
                Log::error('tenant_portal_welcome_mail_failed', [
                    'message' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

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
                Rule::unique('pm_tenants', 'phone')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
            ],
            'email' => [
                ...($createPortal
                    ? ['required']
                    : [Rule::requiredIf($this->isFieldRequired($cfg, 'email'))]),
                'nullable',
                'email',
                'max:255',
                Rule::unique('pm_tenants', 'email')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
                ...($createPortal ? [Rule::unique(User::class, 'email')] : []),
            ],
            'national_id' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'national_id')->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id())),
            ],
            'risk_level' => ['nullable', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_portal_login' => ['nullable'],
        ]);

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
            'agent_user_id' => (int) $request->user()->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? Str::lower((string) $data['email']) : null,
            'national_id' => $data['national_id'] ?? null,
            'risk_level' => $data['risk_level'] ?? 'normal',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $tenant->id,
                'label' => $tenant->name.($tenant->phone ? ' ('.$tenant->phone.')' : ''),
            ],
            'message' => 'Tenant created.',
        ]);
    }

    public function show(PmTenant $tenant): View
    {
        $tenant->load([
            'leases' => fn ($q) => $q->with(['units.property'])->orderByDesc('start_date'),
            'invoices' => fn ($q) => $q->latest('issue_date')->limit(10),
        ])->loadCount(['leases', 'invoices']);

        $leaseRows = $tenant->leases->map(function ($lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');

            return [
                'id' => $lease->id,
                'status' => (string) $lease->status,
                'start' => $lease->start_date?->format('Y-m-d') ?? '—',
                'end' => $lease->end_date?->format('Y-m-d') ?? '—',
                'rent' => (float) $lease->monthly_rent,
                'units' => $units !== '' ? $units : '—',
            ];
        });

        $invoiceTotal = (float) $tenant->invoices->sum('amount');
        $invoicePaid = (float) $tenant->invoices->sum('amount_paid');
        $openingArrears = (float) ($tenant->opening_arrears_amount ?? 0);
        $completedPayments = (float) $tenant->payments()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->sum('amount');
        $invoiceDue = max(0.0, ($openingArrears + $invoiceTotal) - max($invoicePaid, $completedPayments));

        return view('property.agent.tenants.show', [
            'tenant' => $tenant,
            'leaseRows' => $leaseRows,
            'invoiceTotals' => [
                'total' => $invoiceTotal,
                'paid' => $invoicePaid,
                'due' => $invoiceDue,
            ],
        ]);
    }

    public function statement(Request $request, PmTenant $tenant): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $embed = $request->boolean('embed');

        $from = isset($validated['from']) ? trim((string) $validated['from']) : '';
        $to = isset($validated['to']) ? trim((string) $validated['to']) : '';

        $fromDate = $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        $invoiceQuery = PmInvoice::query()
            ->with(['unit.property'])
            ->where('pm_tenant_id', $tenant->id)
            ->when($fromDate, fn ($q) => $q->whereDate('issue_date', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('issue_date', '<=', $toDate->toDateString()));

        $paymentQuery = PmPayment::query()
            ->with(['allocations.invoice'])
            ->where('pm_tenant_id', $tenant->id)
            ->when($fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('paid_at', '<=', $toDate->toDateString()));

        $invoices = $invoiceQuery->orderBy('issue_date')->orderBy('id')->get();
        $payments = $paymentQuery->orderBy('paid_at')->orderBy('id')->get();

        $openingInvoices = 0.0;
        $openingPayments = 0.0;
        $openingArrears = (float) ($tenant->opening_arrears_amount ?? 0);
        $openingArrearsAsOf = $tenant->opening_arrears_as_of
            ? Carbon::parse((string) $tenant->opening_arrears_as_of)->startOfDay()
            : null;
        if ($fromDate) {
            $openingInvoices = (float) PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereDate('issue_date', '<', $fromDate->toDateString())
                ->sum('amount');

            $openingPayments = (float) PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereDate('paid_at', '<', $fromDate->toDateString())
                ->sum('amount');

            if ($openingArrears > 0 && ($openingArrearsAsOf === null || $openingArrearsAsOf->lt($fromDate))) {
                $openingInvoices += $openingArrears;
            }
        }

        $openingBalance = $openingInvoices - $openingPayments;

        $entries = collect();

        foreach ($invoices as $invoice) {
            $label = $invoice->invoice_no ?: 'INV-'.$invoice->id;
            $unitLabel = trim(($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—'));

            $entries->push([
                'date' => $invoice->issue_date?->toDateString(),
                'timestamp' => $invoice->issue_date?->startOfDay()?->timestamp ?? 0,
                'type' => 'Invoice',
                'ref' => $label,
                'description' => ($invoice->invoice_type ? strtoupper((string) $invoice->invoice_type) : 'CHARGE').($unitLabel !== '— / —' ? ' · '.$unitLabel : ''),
                'debit' => (float) $invoice->amount,
                'credit' => 0.0,
                'payment_id' => null,
            ]);
        }

        if ($openingArrears > 0) {
            $entryDate = $openingArrearsAsOf?->toDateString() ?? $tenant->created_at?->toDateString() ?? now()->toDateString();
            $entryTs = $openingArrearsAsOf?->timestamp ?? ($tenant->created_at?->timestamp ?? now()->timestamp);
            $inRange = (! $fromDate || $entryTs >= $fromDate->timestamp) && (! $toDate || $entryTs <= $toDate->timestamp);
            if ($inRange) {
                $items = collect((array) ($tenant->opening_arrears_items ?? []))
                    ->filter(fn ($item): bool => is_array($item) && (float) ($item['amount'] ?? 0) > 0)
                    ->map(function (array $item): string {
                        $customLabel = trim((string) ($item['label'] ?? ''));
                        $label = $customLabel !== ''
                            ? $customLabel
                            : ($this->openingArrearsTypeOptions()[(string) ($item['type'] ?? '')] ?? ucfirst(str_replace('_', ' ', (string) ($item['type'] ?? 'Other'))));
                        $period = (string) ($item['period'] ?? '');
                        $ref = trim((string) ($item['reference'] ?? ''));
                        $bits = [$label, $period !== '' ? "({$period})" : null, PropertyMoney::kes((float) ($item['amount'] ?? 0))];
                        if ($ref !== '') {
                            $bits[] = '['.$ref.']';
                        }

                        return implode(' ', array_values(array_filter($bits, fn ($v): bool => (string) $v !== '')));
                    });
                $partsText = $items->isEmpty() ? '' : ' Breakdown: '.$items->implode(' · ');
                $entries->push([
                    'date' => $entryDate,
                    'timestamp' => $entryTs,
                    'type' => 'Opening arrears',
                    'ref' => 'B/F-'.$tenant->id,
                    'description' => trim((string) (($tenant->opening_arrears_notes ?: 'Brought-forward debt captured at tenant onboarding.').$partsText)),
                    'debit' => $openingArrears,
                    'credit' => 0.0,
                    'payment_id' => null,
                ]);
            }
        }

        foreach ($payments as $payment) {
            $label = $payment->external_ref ?: 'PAY-'.$payment->id;
            $allocTo = $payment->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
            $desc = strtoupper((string) $payment->channel);
            if ($allocTo !== '') {
                $desc .= ' · Alloc: '.$allocTo;
            }
            $desc .= ' · '.ucfirst((string) $payment->status);

            $isCompleted = $payment->status === PmPayment::STATUS_COMPLETED;

            $entries->push([
                'date' => $payment->paid_at?->toDateString(),
                'timestamp' => $payment->paid_at?->timestamp ?? 0,
                'type' => 'Payment',
                'ref' => $label,
                'description' => $desc,
                'debit' => 0.0,
                'credit' => $isCompleted ? (float) $payment->amount : 0.0,
                'payment_id' => $isCompleted ? $payment->id : null,
                'status' => ucfirst((string) $payment->status),
            ]);
        }

        $entries = $entries
            ->sortBy([
                ['timestamp', 'asc'],
                ['type', 'asc'],
            ])
            ->values();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = [];
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
        }

        $stats = [
            ['label' => 'Tenant', 'value' => $tenant->name, 'hint' => 'Statement owner'],
            ['label' => 'Transactions', 'value' => (string) count($rows), 'hint' => 'Invoices + payments'],
            ['label' => 'Total debit', 'value' => PropertyMoney::kes($totalDebit), 'hint' => 'Charges'],
            ['label' => 'Total credit', 'value' => PropertyMoney::kes($totalCredit), 'hint' => 'Payments'],
            ['label' => 'Closing balance', 'value' => PropertyMoney::kes($running), 'hint' => 'Debit - credit (+ opening)'],
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
            'outstanding' => max(0.0, $running),
            'openCount' => $invoices->filter(fn (PmInvoice $i) => (float) $i->amount_paid < (float) $i->amount)->count(),
        ];

        $paymentSummary = [
            'count' => $payments->count(),
            'completedCount' => $payments->where('status', PmPayment::STATUS_COMPLETED)->count(),
            'pendingCount' => $payments->where('status', PmPayment::STATUS_PENDING)->count(),
            'failedCount' => $payments->where('status', PmPayment::STATUS_FAILED)->count(),
            'completedAmount' => (float) $payments->where('status', PmPayment::STATUS_COMPLETED)->sum('amount'),
            'pendingAmount' => (float) $payments->where('status', PmPayment::STATUS_PENDING)->sum('amount'),
        ];

        return view($embed ? 'property.agent.tenants.statement_embed' : 'property.agent.tenants.statement', [
            'tenant' => $tenant,
            'stats' => $stats,
            'columns' => ['Date', 'Type', 'Ref', 'Description', 'Debit', 'Credit', 'Balance', 'Status', 'Receipt'],
            'tableRows' => $rows,
            'filters' => ['from' => $from !== '' ? $from : null, 'to' => $to !== '' ? $to : null],
            'leaseSummary' => $leaseSummary,
            'invoiceSummary' => $invoiceSummary,
            'paymentSummary' => $paymentSummary,
            'embed' => $embed,
        ]);
    }

    public function edit(PmTenant $tenant): View
    {
        $tenant->loadCount('leases');

        return view('property.agent.tenants.edit', [
            'tenant' => $tenant,
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
        ]);
    }

    public function update(Request $request, PmTenant $tenant): RedirectResponse
    {
        $cfg = $this->tenantFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($cfg, 'name')), 'nullable', 'string', 'max:255'],
            'phone' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'phone')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'phone')
                    ->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id()))
                    ->ignore($tenant->id),
            ],
            'email' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'email')),
                'nullable',
                'email',
                'max:255',
                Rule::unique('pm_tenants', 'email')
                    ->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id()))
                    ->ignore($tenant->id),
            ],
            'national_id' => [
                Rule::requiredIf($this->isFieldRequired($cfg, 'id_number')),
                'nullable',
                'string',
                'max:64',
                Rule::unique('pm_tenants', 'national_id')
                    ->where(fn ($q) => $q->where('agent_user_id', (int) auth()->id()))
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
        ]);
        $openingArrearsPayload = $this->buildOpeningArrearsPayload($data, $tenant);

        $tenant->update([
            ...$data,
            ...$openingArrearsPayload,
        ]);

        return back()->with('success', 'Tenant updated.');
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
            'penalty' => 'Penalty',
            'other' => 'Other charge',
            'custom_charge' => 'Custom charge',
        ];
    }
}
