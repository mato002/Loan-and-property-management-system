<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Mail\TenantPortalCredentialsMail;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    public function profiles(): View
    {
        return view('property.agent.tenants.directory', $this->tenantListPayload(
            pageTitle: 'Tenant profiles',
            pageSubtitle: 'Same roster — future: per-tenant profile, documents, and timeline.',
            showTenantForm: false,
        ));
    }

    public function importForm(): View
    {
        return view('property.agent.tenants.import', [
            'expectedColumns' => ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'],
            'lastImportStats' => session('tenant_import_stats'),
            'lastImportErrors' => session('tenant_import_errors', []),
        ]);
    }

    public function importTemplate(): Response
    {
        $csv = implode(',', ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'])."\n"
            ."John Doe,+254700000000,john@example.com,ID123,normal,Notes here\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tenant_import_template.csv"',
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
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

        $normalize = static fn ($v) => mb_strtolower(trim((string) $v));
        $header = array_map($normalize, $header);

        $expected = ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'];
        $missing = array_values(array_diff($expected, $header));
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
        $rowNum = 1; // header row

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
                'phone' => ($v = trim((string) ($row[$colIndex['phone']] ?? ''))) !== '' ? $v : null,
                'email' => $email,
                'national_id' => ($v = trim((string) ($row[$colIndex['national_id']] ?? ''))) !== '' ? $v : null,
                'risk_level' => $risk,
                'notes' => ($v = trim((string) ($row[$colIndex['notes']] ?? ''))) !== '' ? $v : null,
            ];

            try {
                $tenant = null;
                if ($email !== null) {
                    $tenant = PmTenant::query()->where('email', $email)->first();
                }

                if ($tenant) {
                    $tenant->update($payload);
                    $updated++;
                } else {
                    PmTenant::query()->create($payload);
                    $created++;
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
        ];

        return redirect()
            ->route('property.tenants.import')
            ->with('success', "Import finished. Created {$created}, updated {$updated}.")
            ->with('tenant_import_stats', $stats)
            ->with('tenant_import_errors', array_slice($errors, 0, 200));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantListPayload(string $pageTitle, string $pageSubtitle, bool $showTenantForm): array
    {
        $tenants = PmTenant::query()
            ->withCount(['leases', 'invoices'])
            ->withMax('leases', 'end_date')
            ->orderBy('name')
            ->get();

        $stats = [
            ['label' => 'Tenants', 'value' => (string) $tenants->count(), 'hint' => 'Records'],
            ['label' => 'With portal login', 'value' => (string) $tenants->whereNotNull('user_id')->count(), 'hint' => 'Linked user'],
            ['label' => 'High risk flagged', 'value' => (string) $tenants->where('risk_level', 'high')->count(), 'hint' => 'Manual'],
            ['label' => 'Total leases', 'value' => (string) $tenants->sum('leases_count'), 'hint' => 'Linked'],
        ];

        $rows = $tenants->map(function (PmTenant $t) {
            $leaseEnd = $t->leases_max_end_date
                ? (string) \Illuminate\Support\Carbon::parse((string) $t->leases_max_end_date)->format('Y-m-d')
                : '—';

            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<a href="'.route('property.tenants.edit', $t).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.tenants.leases').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Leases</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.tenants.notices').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Notices</a>'.
                '</div>'
            );

            return [
                $t->name,
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
            'stats' => $stats,
            'columns' => ['Tenant', 'Phone', 'Email', 'ID / ref', 'Leases', 'Lease end', 'Risk', 'Actions'],
            'tableRows' => $rows,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $createPortal = $request->boolean('create_portal_login');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => $createPortal
                ? ['required', 'email', 'max:255', Rule::unique(User::class, 'email')]
                : ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'risk_level' => ['required', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_portal_login' => ['sometimes', 'boolean'],
        ]);

        $plainPassword = null;
        $user = null;

        if ($createPortal) {
            $plainPassword = Str::password(14, symbols: false);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $plainPassword,
                'property_portal_role' => 'tenant',
                'email_verified_at' => now(),
            ]);
        }

        $tenant = PmTenant::query()->create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $createPortal ? Str::lower($data['email']) : ($data['email'] ?? null),
            'national_id' => $data['national_id'] ?? null,
            'risk_level' => $data['risk_level'],
            'notes' => $data['notes'] ?? null,
        ]);

        $nextSteps = [
            'title' => 'Tenant saved',
            'message' => 'Next, allocate a vacant unit by creating a lease (Active leases mark units as occupied).',
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

    public function edit(PmTenant $tenant): View
    {
        $tenant->loadCount('leases');

        return view('property.agent.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'risk_level' => ['required', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Tenant updated.');
    }
}
