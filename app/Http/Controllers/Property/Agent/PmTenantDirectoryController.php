<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Mail\TenantPortalCredentialsMail;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    /**
     * @return array<string, mixed>
     */
    private function tenantListPayload(string $pageTitle, string $pageSubtitle, bool $showTenantForm): array
    {
        $tenants = PmTenant::query()->withCount(['leases', 'invoices'])->orderBy('name')->get();

        $stats = [
            ['label' => 'Tenants', 'value' => (string) $tenants->count(), 'hint' => 'Records'],
            ['label' => 'With portal login', 'value' => (string) $tenants->whereNotNull('user_id')->count(), 'hint' => 'Linked user'],
            ['label' => 'High risk flagged', 'value' => (string) $tenants->where('risk_level', 'high')->count(), 'hint' => 'Manual'],
            ['label' => 'Total leases', 'value' => (string) $tenants->sum('leases_count'), 'hint' => 'Linked'],
        ];

        $rows = $tenants->map(fn (PmTenant $t) => [
            $t->name,
            $t->phone ?? '—',
            $t->email ?? '—',
            $t->national_id ?? '—',
            (string) $t->leases_count,
            '—',
            ucfirst($t->risk_level),
            '—',
        ])->all();

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

        PmTenant::query()->create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $createPortal ? Str::lower($data['email']) : ($data['email'] ?? null),
            'national_id' => $data['national_id'] ?? null,
            'risk_level' => $data['risk_level'],
            'notes' => $data['notes'] ?? null,
        ]);

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
                    ->with('error', 'Email could not be sent — share the login link and a password reset manually, or check your mail configuration (MAIL_* in .env).');
            }

            return back()->with('success', 'Tenant saved. Portal login details were emailed.');
        }

        return back()->with('success', 'Tenant saved.');
    }
}
