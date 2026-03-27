<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmPermission;
use App\Models\PmRole;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PropertySettingsStoreWebController extends Controller
{
    public function systemSetup(): View
    {
        return view('property.agent.settings.system_setup.index', [
            'formsCount' => (int) PropertyPortalSetting::getValue('system_setup_forms_count', '0'),
            'workflowsCount' => (int) PropertyPortalSetting::getValue('system_setup_workflows_count', '0'),
            'templatesCount' => (int) PropertyPortalSetting::getValue('system_setup_templates_count', '0'),
            'accessCount' => Schema::hasTable('pm_roles') ? (int) PmRole::query()->count() : 0,
        ]);
    }

    public function systemSetupForms(): View
    {
        return view('property.agent.settings.system_setup.forms', [
            'tenantMoveInEnabled' => PropertyPortalSetting::getValue('form_tenant_move_in_enabled', '1') === '1',
            'maintenanceEnabled' => PropertyPortalSetting::getValue('form_maintenance_enabled', '1') === '1',
            'customFields' => PropertyPortalSetting::getValue('form_custom_fields_json', ''),
        ]);
    }

    public function storeSystemSetupForms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_move_in_enabled' => ['nullable', 'in:0,1'],
            'maintenance_enabled' => ['nullable', 'in:0,1'],
            'form_custom_fields_json' => ['nullable', 'string', 'max:10000'],
        ]);

        PropertyPortalSetting::setValue('form_tenant_move_in_enabled', (string) ($data['tenant_move_in_enabled'] ?? '0'));
        PropertyPortalSetting::setValue('form_maintenance_enabled', (string) ($data['maintenance_enabled'] ?? '0'));
        PropertyPortalSetting::setValue('form_custom_fields_json', $data['form_custom_fields_json'] ?? '');
        PropertyPortalSetting::setValue('system_setup_forms_count', '2');

        return back()->with('success', __('Form setup saved.'));
    }

    public function systemSetupWorkflows(): View
    {
        return view('property.agent.settings.system_setup.workflows', [
            'autoAssignTickets' => PropertyPortalSetting::getValue('workflow_auto_assign_tickets', '0') === '1',
            'autoReminders' => PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1',
            'reminderLeadDays' => PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'),
            'notes' => PropertyPortalSetting::getValue('workflow_notes', ''),
        ]);
    }

    public function storeSystemSetupWorkflows(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workflow_auto_assign_tickets' => ['nullable', 'in:0,1'],
            'workflow_auto_reminders' => ['nullable', 'in:0,1'],
            'workflow_reminder_lead_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'workflow_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        PropertyPortalSetting::setValue('workflow_auto_assign_tickets', (string) ($data['workflow_auto_assign_tickets'] ?? '0'));
        PropertyPortalSetting::setValue('workflow_auto_reminders', (string) ($data['workflow_auto_reminders'] ?? '0'));
        PropertyPortalSetting::setValue('workflow_reminder_lead_days', (string) ($data['workflow_reminder_lead_days'] ?? 3));
        PropertyPortalSetting::setValue('workflow_notes', $data['workflow_notes'] ?? '');
        PropertyPortalSetting::setValue('system_setup_workflows_count', '3');

        return back()->with('success', __('Workflow setup saved.'));
    }

    public function systemSetupTemplates(): View
    {
        return view('property.agent.settings.system_setup.templates', [
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
            'noticeTemplate' => PropertyPortalSetting::getValue('template_notice_text', ''),
        ]);
    }

    public function storeSystemSetupTemplates(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_lease_text' => ['nullable', 'string', 'max:10000'],
            'template_notice_text' => ['nullable', 'string', 'max:10000'],
        ]);

        PropertyPortalSetting::setValue('template_lease_text', $data['template_lease_text'] ?? '');
        PropertyPortalSetting::setValue('template_notice_text', $data['template_notice_text'] ?? '');
        PropertyPortalSetting::setValue('system_setup_templates_count', '2');

        return back()->with('success', __('Template setup saved.'));
    }

    public function systemSetupAccess(): View
    {
        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_permissions')) {
            return view('property.agent.settings.system_setup.access', [
                'roles' => collect(),
                'permissionsByGroup' => collect(),
                'portalUsers' => collect(),
                'tablesReady' => false,
            ]);
        }

        $this->ensureAccessControlDefaults();

        $roles = PmRole::query()->with('permissions:id')->orderBy('portal_scope')->orderBy('name')->get();
        $permissionsByGroup = PmPermission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group');
        $portalUsers = User::query()
            ->whereNotNull('property_portal_role')
            ->with('pmRoles:id,name', 'pmPermissions:id,name')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email', 'property_portal_role']);

        return view('property.agent.settings.system_setup.access', [
            'roles' => $roles,
            'permissionsByGroup' => $permissionsByGroup,
            'portalUsers' => $portalUsers,
            'tablesReady' => true,
        ]);
    }

    public function storeSystemSetupRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:pm_roles,slug'],
            'portal_scope' => ['required', 'in:agent,landlord,tenant,any'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        PmRole::query()->create($data);
        PropertyPortalSetting::setValue('system_setup_access_count', (string) PmRole::query()->count());

        return back()->with('success', __('Role created.'));
    }

    public function storeSystemSetupRoleClone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_role_id' => ['required', 'integer', 'exists:pm_roles,id'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:pm_roles,slug'],
            'portal_scope' => ['required', 'in:agent,landlord,tenant,any'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $sourceRole = PmRole::query()->with('permissions:id')->findOrFail((int) $data['source_role_id']);
        $newRole = PmRole::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'portal_scope' => $data['portal_scope'],
            'description' => $data['description'] ?? '',
        ]);
        $newRole->permissions()->sync($sourceRole->permissions->pluck('id')->all());

        PropertyPortalSetting::setValue('system_setup_access_count', (string) PmRole::query()->count());

        return back()->with('success', __('Role cloned with permissions.'));
    }

    public function storeSystemSetupPermission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9._-]+$/', 'unique:pm_permissions,key'],
            'group' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        PmPermission::query()->create([
            ...$data,
            'group' => $data['group'] !== '' ? $data['group'] : 'general',
        ]);

        return back()->with('success', __('Permission created.'));
    }

    public function updateSystemSetupPermission(Request $request, PmPermission $pmPermission): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9._-]+$/', 'unique:pm_permissions,key,'.$pmPermission->id],
            'group' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $pmPermission->update([
            ...$data,
            'group' => $data['group'] !== '' ? $data['group'] : 'general',
        ]);

        return back()->with('success', __('Permission updated.'));
    }

    public function destroySystemSetupPermission(PmPermission $pmPermission): RedirectResponse
    {
        $pmPermission->delete();

        return back()->with('success', __('Permission deleted.'));
    }

    public function storeSystemSetupRolePermissions(Request $request, PmRole $pmRole): RedirectResponse
    {
        $data = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:pm_permissions,id'],
        ]);

        $pmRole->permissions()->sync($data['permission_ids'] ?? []);

        return back()->with('success', __('Role permissions updated.'));
    }

    public function storeSystemSetupUserRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:pm_roles,id'],
        ]);

        $user->pmRoles()->sync($data['role_ids'] ?? []);

        return back()->with('success', __('User role assignments updated.'));
    }

    public function storeSystemSetupUserPermissions(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:pm_permissions,id'],
        ]);

        $user->pmPermissions()->sync($data['permission_ids'] ?? []);

        return back()->with('success', __('User direct permissions updated.'));
    }

    private function ensureAccessControlDefaults(): void
    {
        $defaultPermissions = [
            ['name' => 'Manage properties', 'key' => 'properties.manage', 'group' => 'properties'],
            ['name' => 'Manage tenants', 'key' => 'tenants.manage', 'group' => 'tenants'],
            ['name' => 'Manage leases', 'key' => 'leases.manage', 'group' => 'tenants'],
            ['name' => 'Manage maintenance', 'key' => 'maintenance.manage', 'group' => 'maintenance'],
            ['name' => 'Manage vendors', 'key' => 'vendors.manage', 'group' => 'vendors'],
            ['name' => 'Record payments', 'key' => 'payments.record', 'group' => 'payments'],
            ['name' => 'Settle payments', 'key' => 'payments.settle', 'group' => 'payments'],
            ['name' => 'Manage penalties', 'key' => 'revenue.penalties.manage', 'group' => 'revenue'],
            ['name' => 'Manage utilities', 'key' => 'revenue.utilities.manage', 'group' => 'revenue'],
            ['name' => 'Manage accounting entries', 'key' => 'accounting.entries.manage', 'group' => 'accounting'],
            ['name' => 'Manage payroll', 'key' => 'accounting.payroll.manage', 'group' => 'accounting'],
            ['name' => 'Manage communications', 'key' => 'communications.manage', 'group' => 'communications'],
            ['name' => 'Manage listings', 'key' => 'listings.manage', 'group' => 'listings'],
            ['name' => 'Manage settings', 'key' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'Manage access control', 'key' => 'settings.access.manage', 'group' => 'settings'],
        ];

        foreach ($defaultPermissions as $perm) {
            PmPermission::query()->firstOrCreate(
                ['key' => $perm['key']],
                [
                    'name' => $perm['name'],
                    'group' => $perm['group'],
                    'description' => 'Auto-created default permission.',
                ]
            );
        }

        $rolesWithPermissions = [
            'property_manager' => [
                'name' => 'Property Manager',
                'portal_scope' => 'agent',
                'description' => 'Full operational access across property modules.',
                'permissions' => [
                    'properties.manage', 'tenants.manage', 'leases.manage', 'maintenance.manage', 'vendors.manage',
                    'payments.record', 'payments.settle', 'revenue.penalties.manage', 'revenue.utilities.manage',
                    'accounting.entries.manage', 'accounting.payroll.manage', 'communications.manage',
                    'listings.manage', 'settings.manage', 'settings.access.manage',
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'portal_scope' => 'agent',
                'description' => 'Finance and accounting operations.',
                'permissions' => [
                    'payments.record', 'payments.settle', 'revenue.penalties.manage', 'revenue.utilities.manage',
                    'accounting.entries.manage', 'accounting.payroll.manage',
                ],
            ],
            'leasing_officer' => [
                'name' => 'Leasing Officer',
                'portal_scope' => 'agent',
                'description' => 'Tenant onboarding, leases, and listings.',
                'permissions' => [
                    'tenants.manage', 'leases.manage', 'listings.manage', 'communications.manage',
                ],
            ],
            'maintenance_officer' => [
                'name' => 'Maintenance Officer',
                'portal_scope' => 'agent',
                'description' => 'Maintenance requests, jobs, and vendors.',
                'permissions' => [
                    'maintenance.manage', 'vendors.manage', 'communications.manage',
                ],
            ],
            'finance_clerk' => [
                'name' => 'Finance Clerk',
                'portal_scope' => 'agent',
                'description' => 'Record collections without settlement/admin scope.',
                'permissions' => [
                    'payments.record',
                ],
            ],
            'settings_admin' => [
                'name' => 'Settings Admin',
                'portal_scope' => 'agent',
                'description' => 'Configuration and access-control management.',
                'permissions' => [
                    'settings.manage', 'settings.access.manage',
                ],
            ],
            'landlord_portal_user' => [
                'name' => 'Landlord Portal User',
                'portal_scope' => 'landlord',
                'description' => 'Base landlord portal role mapping.',
                'permissions' => [],
            ],
            'tenant_portal_user' => [
                'name' => 'Tenant Portal User',
                'portal_scope' => 'tenant',
                'description' => 'Base tenant portal role mapping.',
                'permissions' => [],
            ],
        ];

        foreach ($rolesWithPermissions as $slug => $roleDef) {
            $role = PmRole::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleDef['name'],
                    'portal_scope' => $roleDef['portal_scope'],
                    'description' => $roleDef['description'],
                ]
            );

            if ($role->permissions()->count() === 0 && ! empty($roleDef['permissions'])) {
                $permIds = PmPermission::query()->whereIn('key', $roleDef['permissions'])->pluck('id')->all();
                $role->permissions()->sync($permIds);
            }
        }
    }

    public function commission(): View
    {
        $rawOverrides = PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = [];
        if (is_string($rawOverrides) && $rawOverrides !== '') {
            $decoded = json_decode($rawOverrides, true);
            if (is_array($decoded)) {
                foreach ($decoded as $propertyId => $percent) {
                    $overrides[(string) $propertyId] = is_scalar($percent) ? (string) $percent : '';
                }
            }
        }

        return view('property.agent.settings.commission', [
            'defaultPercent' => PropertyPortalSetting::getValue('commission_default_percent', ''),
            'notes' => PropertyPortalSetting::getValue('commission_notes', ''),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'propertyCommissionOverrides' => $overrides,
        ]);
    }

    public function storeCommission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_default_percent' => ['nullable', 'string', 'max:32'],
            'commission_notes' => ['nullable', 'string', 'max:2000'],
            'property_commission_overrides' => ['nullable', 'array'],
            'property_commission_overrides.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        PropertyPortalSetting::setValue('commission_default_percent', $data['commission_default_percent'] ?? '');
        PropertyPortalSetting::setValue('commission_notes', $data['commission_notes'] ?? '');

        $overrides = [];
        foreach (($data['property_commission_overrides'] ?? []) as $propertyId => $percent) {
            $pid = (int) $propertyId;
            if ($pid <= 0) {
                continue;
            }
            $value = is_numeric($percent) ? trim((string) $percent) : '';
            if ($value === '') {
                continue;
            }
            $overrides[(string) $pid] = (float) $value;
        }
        PropertyPortalSetting::setValue(
            'commission_property_overrides_json',
            json_encode($overrides, JSON_UNESCAPED_UNICODE)
        );

        return back()->with('success', __('Commission settings saved.'));
    }

    public function payments(): View
    {
        $rawCustomMethods = PropertyPortalSetting::getValue('custom_payment_methods_json', '[]');
        $customPaymentMethods = [];
        if (is_string($rawCustomMethods) && $rawCustomMethods !== '') {
            $decoded = json_decode($rawCustomMethods, true);
            if (is_array($decoded)) {
                $customPaymentMethods = array_values(array_filter($decoded, fn ($row) => is_array($row)));
            }
        }

        return view('property.agent.settings.payments', [
            'shortcode' => PropertyPortalSetting::getValue('mpesa_shortcode', ''),
            'consumerKey' => PropertyPortalSetting::getValue('mpesa_consumer_key', ''),
            'callbackUrl' => PropertyPortalSetting::getValue('mpesa_callback_url', ''),
            'notes' => PropertyPortalSetting::getValue('payments_notes', ''),
            'trustAccountLabel' => PropertyPortalSetting::getValue('trust_account_label', ''),
            'trustAccountNumber' => PropertyPortalSetting::getValue('trust_account_number', ''),
            'trustBankName' => PropertyPortalSetting::getValue('trust_bank_name', ''),
            'hasConsumerSecret' => (bool) strlen((string) PropertyPortalSetting::getValue('mpesa_consumer_secret', '')),
            'hasPasskey' => (bool) strlen((string) PropertyPortalSetting::getValue('mpesa_passkey', '')),
            'customPaymentMethods' => $customPaymentMethods,
        ]);
    }

    public function storePayments(Request $request): RedirectResponse
    {
        if ($request->boolean('save_trust_account')) {
            $data = $request->validate([
                'trust_account_label' => ['nullable', 'string', 'max:128'],
                'trust_account_number' => ['nullable', 'string', 'max:64'],
                'trust_bank_name' => ['nullable', 'string', 'max:128'],
            ]);

            PropertyPortalSetting::setValue('trust_account_label', $data['trust_account_label'] ?? '');
            PropertyPortalSetting::setValue('trust_account_number', $data['trust_account_number'] ?? '');
            PropertyPortalSetting::setValue('trust_bank_name', $data['trust_bank_name'] ?? '');

            return back()->with('success', __('Trust account details saved.'));
        }

        if ($request->boolean('save_custom_methods')) {
            $data = $request->validate([
                'custom_methods' => ['nullable', 'array', 'max:10'],
                'custom_methods.*.name' => ['nullable', 'string', 'max:80'],
                'custom_methods.*.provider' => ['nullable', 'string', 'max:120'],
                'custom_methods.*.provider_other' => ['nullable', 'string', 'max:120'],
                'custom_methods.*.account' => ['nullable', 'string', 'max:120'],
                'custom_methods.*.instructions' => ['nullable', 'string', 'max:400'],
            ]);

            $methods = collect($data['custom_methods'] ?? [])
                ->map(function ($row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    $provider = trim((string) ($row['provider'] ?? ''));
                    $providerOther = trim((string) ($row['provider_other'] ?? ''));
                    $account = trim((string) ($row['account'] ?? ''));
                    $instructions = trim((string) ($row['instructions'] ?? ''));

                    if (strcasecmp($provider, 'Other') === 0 && $providerOther !== '') {
                        $provider = $providerOther;
                    }

                    if ($name === '' && $provider === '' && $account === '' && $instructions === '') {
                        return null;
                    }

                    return [
                        'name' => $name,
                        'provider' => $provider,
                        'account' => $account,
                        'instructions' => $instructions,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            PropertyPortalSetting::setValue('custom_payment_methods_json', json_encode($methods, JSON_UNESCAPED_UNICODE));

            return back()->with('success', __('Custom payment methods saved.'));
        }

        $data = $request->validate([
            'mpesa_shortcode' => ['nullable', 'string', 'max:64'],
            'mpesa_consumer_key' => ['nullable', 'string', 'max:255'],
            'mpesa_consumer_secret' => ['nullable', 'string', 'max:255'],
            'mpesa_passkey' => ['nullable', 'string', 'max:255'],
            'mpesa_callback_url' => ['nullable', 'string', 'max:500'],
            'payments_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $secretKeys = ['mpesa_consumer_secret', 'mpesa_passkey'];

        foreach ($data as $key => $value) {
            if (in_array($key, $secretKeys, true)) {
                if ($request->filled($key)) {
                    PropertyPortalSetting::setValue($key, (string) $value);
                }

                continue;
            }

            PropertyPortalSetting::setValue($key, $value ?? '');
        }

        return back()->with('success', __('Payment settings saved (store secrets carefully — encryption not enabled in this build).'));
    }

    public function rules(): View
    {
        return view('property.agent.settings.rules', [
            'graceDays' => PropertyPortalSetting::getValue('rules_grace_days', '3'),
            'lateFeePercent' => PropertyPortalSetting::getValue('rules_late_fee_percent', '0'),
            'notes' => PropertyPortalSetting::getValue('rules_notes', ''),
        ]);
    }

    public function storeRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules_grace_days' => ['nullable', 'string', 'max:16'],
            'rules_late_fee_percent' => ['nullable', 'string', 'max:32'],
            'rules_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PropertyPortalSetting::setValue('rules_grace_days', $data['rules_grace_days'] ?? '');
        PropertyPortalSetting::setValue('rules_late_fee_percent', $data['rules_late_fee_percent'] ?? '');
        PropertyPortalSetting::setValue('rules_notes', $data['rules_notes'] ?? '');

        return back()->with('success', __('Rules saved — wire these values into invoice generation when you automate penalties.'));
    }

    public function branding(): View
    {
        return view('property.agent.settings.branding', [
            'companyName' => PropertyPortalSetting::getValue('company_name', ''),
            'companyLogoUrl' => PropertyPortalSetting::getValue('company_logo_url', ''),
            'siteFaviconUrl' => PropertyPortalSetting::getValue('site_favicon_url', ''),
            'contactEmailPrimary' => PropertyPortalSetting::getValue('contact_email_primary', ''),
            'contactEmailSupport' => PropertyPortalSetting::getValue('contact_email_support', ''),
            'contactPhone' => PropertyPortalSetting::getValue('contact_phone', ''),
            'contactWhatsapp' => PropertyPortalSetting::getValue('contact_whatsapp', ''),
            'contactAddress' => PropertyPortalSetting::getValue('contact_address', ''),
            'contactRegNo' => PropertyPortalSetting::getValue('contact_reg_no', ''),
            'contactMapEmbedUrl' => PropertyPortalSetting::getValue('contact_map_embed_url', ''),
        ]);
    }

    public function storeBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_logo_url' => ['nullable', 'string', 'max:2048'],
            'company_logo' => ['nullable', 'image', 'max:4096'],
            'site_favicon_url' => ['nullable', 'string', 'max:2048'],
            'site_favicon' => ['nullable', 'image', 'max:2048'],
            'contact_email_primary' => ['nullable', 'email', 'max:255'],
            'contact_email_support' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_whatsapp' => ['nullable', 'string', 'max:64'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_reg_no' => ['nullable', 'string', 'max:128'],
            'contact_map_embed_url' => ['nullable', 'url', 'max:2048'],
            'remove_logo' => ['nullable', 'in:0,1'],
            'remove_favicon' => ['nullable', 'in:0,1'],
        ]);

        PropertyPortalSetting::setValue('company_name', $data['company_name'] ?? '');
        PropertyPortalSetting::setValue('contact_email_primary', $data['contact_email_primary'] ?? '');
        PropertyPortalSetting::setValue('contact_email_support', $data['contact_email_support'] ?? '');
        PropertyPortalSetting::setValue('contact_phone', $data['contact_phone'] ?? '');
        PropertyPortalSetting::setValue('contact_whatsapp', $data['contact_whatsapp'] ?? '');
        PropertyPortalSetting::setValue('contact_address', $data['contact_address'] ?? '');
        PropertyPortalSetting::setValue('contact_reg_no', $data['contact_reg_no'] ?? '');
        PropertyPortalSetting::setValue('contact_map_embed_url', $data['contact_map_embed_url'] ?? '');

        if (($data['remove_logo'] ?? '0') === '1') {
            PropertyPortalSetting::setValue('company_logo_url', '');

            return back()->with('success', __('Branding settings saved.'));
        }

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('property/branding', 'public');
            PropertyPortalSetting::setValue('company_logo_url', Storage::url($path));
        } elseif (array_key_exists('company_logo_url', $data)) {
            PropertyPortalSetting::setValue('company_logo_url', $data['company_logo_url'] ?? '');
        }

        if (($data['remove_favicon'] ?? '0') === '1') {
            PropertyPortalSetting::setValue('site_favicon_url', '');
        } elseif ($request->hasFile('site_favicon')) {
            $path = $request->file('site_favicon')->store('property/branding', 'public');
            PropertyPortalSetting::setValue('site_favicon_url', Storage::url($path));
        } elseif (array_key_exists('site_favicon_url', $data)) {
            PropertyPortalSetting::setValue('site_favicon_url', $data['site_favicon_url'] ?? '');
        }

        return back()->with('success', __('Branding settings saved.'));
    }
}
