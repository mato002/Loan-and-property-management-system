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
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertySettingsStoreWebController extends Controller
{
    public function systemSetup(): View
    {
        $fieldModules = $this->systemSetupFieldModules();
        $configuredModuleKeys = collect($fieldModules)
            ->pluck('key')
            ->filter(fn (string $module): bool => $this->isSystemSetupModuleConfigured($module))
            ->values()
            ->all();

        return view('property.agent.settings.system_setup.index', [
            'formsCount' => (int) PropertyPortalSetting::getValue('system_setup_forms_count', '0'),
            'propertyOnboardingFieldsCount' => count($this->configuredFields('property_onboarding')),
            'unitFieldsCount' => count($this->configuredFields('unit')),
            'amenityFieldsCount' => count($this->configuredFields('amenity')),
            'landlordFieldsCount' => count($this->configuredFields('landlord')),
            'leadFieldsCount' => count($this->configuredFields('lead')),
            'rentalApplicationFieldsCount' => count($this->configuredFields('rental_application')),
            'tenantFieldsCount' => count($this->configuredFields('tenant')),
            'leaseFieldsCount' => count($this->configuredFields('lease')),
            'maintenanceFieldsCount' => count($this->configuredFields('maintenance')),
            'vendorFieldsCount' => count($this->configuredFields('vendor')),
            'invoiceFieldsCount' => count($this->configuredFields('invoice')),
            'tenantNoticeFieldsCount' => count($this->configuredFields('tenant_notice')),
            'movementFieldsCount' => count($this->configuredFields('movement')),
            'workflowsCount' => (int) PropertyPortalSetting::getValue('system_setup_workflows_count', '0'),
            'templatesCount' => (int) PropertyPortalSetting::getValue('system_setup_templates_count', '0'),
            'accessCount' => Schema::hasTable('pm_roles') ? (int) PmRole::query()->count() : 0,
            'fieldModuleCompletion' => [
                'total' => count($fieldModules),
                'configured' => count($configuredModuleKeys),
                'pending' => max(0, count($fieldModules) - count($configuredModuleKeys)),
                'configured_keys' => $configuredModuleKeys,
                'items' => $fieldModules,
            ],
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

    public function systemSetupPropertyOnboardingFields(): View
    {
        return view('property.agent.settings.system_setup.property_onboarding_fields', [
            'fields' => $this->configuredFields('property_onboarding'),
        ]);
    }

    public function storeSystemSetupPropertyOnboardingFields(Request $request): RedirectResponse
    {
        $fields = $this->validateDynamicFields($request);

        PropertyPortalSetting::setValue(
            'system_setup_property_onboarding_fields_json',
            json_encode($fields, JSON_UNESCAPED_UNICODE)
        );
        PropertyPortalSetting::setValue('system_setup_forms_count', (string) max(2, count($fields)));

        return back()->with('success', __('Property onboarding fields updated.'));
    }

    public function systemSetupUnitFields(): View
    {
        return view('property.agent.settings.system_setup.unit_fields', [
            'fields' => $this->configuredFields('unit'),
        ]);
    }

    public function storeSystemSetupUnitFields(Request $request): RedirectResponse
    {
        $fields = $this->validateDynamicFields($request);

        PropertyPortalSetting::setValue(
            'system_setup_unit_fields_json',
            json_encode($fields, JSON_UNESCAPED_UNICODE)
        );
        PropertyPortalSetting::setValue('system_setup_forms_count', (string) max(2, count($fields)));

        return back()->with('success', __('Unit fields updated.'));
    }

    public function systemSetupAmenityFields(): View
    {
        return $this->renderModuleFieldSetup('amenity', 'Amenity fields', 'Configure amenity form fields used when creating/updating amenities.');
    }

    public function storeSystemSetupAmenityFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'amenity', 'Amenity fields updated.');
    }

    public function systemSetupLandlordFields(): View
    {
        return $this->renderModuleFieldSetup('landlord', 'Landlord fields', 'Configure landlord onboarding/profile form fields.');
    }

    public function storeSystemSetupLandlordFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'landlord', 'Landlord fields updated.');
    }

    public function systemSetupLeadFields(): View
    {
        return $this->renderModuleFieldSetup('lead', 'Lead fields', 'Configure listing lead capture and qualification fields.');
    }

    public function storeSystemSetupLeadFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'lead', 'Lead fields updated.');
    }

    public function systemSetupRentalApplicationFields(): View
    {
        return $this->renderModuleFieldSetup('rental_application', 'Rental application fields', 'Configure rental application form fields and checks.');
    }

    public function storeSystemSetupRentalApplicationFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'rental_application', 'Rental application fields updated.');
    }

    public function systemSetupTenantFields(): View
    {
        return $this->renderModuleFieldSetup('tenant', 'Tenant fields', 'Configure tenant onboarding/profile form fields.');
    }

    public function storeSystemSetupTenantFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'tenant', 'Tenant fields updated.');
    }

    public function systemSetupLeaseFields(): View
    {
        return $this->renderModuleFieldSetup('lease', 'Lease fields', 'Configure lease creation and update form fields.');
    }

    public function storeSystemSetupLeaseFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'lease', 'Lease fields updated.');
    }

    public function systemSetupMaintenanceFields(): View
    {
        return $this->renderModuleFieldSetup('maintenance', 'Maintenance fields', 'Configure maintenance request and work-order form fields.');
    }

    public function storeSystemSetupMaintenanceFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'maintenance', 'Maintenance fields updated.');
    }

    public function systemSetupVendorFields(): View
    {
        return $this->renderModuleFieldSetup('vendor', 'Vendor fields', 'Configure vendor onboarding and profile fields.');
    }

    public function storeSystemSetupVendorFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'vendor', 'Vendor fields updated.');
    }

    public function systemSetupInvoiceFields(): View
    {
        return $this->renderModuleFieldSetup('invoice', 'Invoice & payment fields', 'Configure invoice issue, payment capture, and settlement form fields.');
    }

    public function storeSystemSetupInvoiceFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'invoice', 'Invoice & payment fields updated.');
    }

    public function systemSetupTenantNoticeFields(): View
    {
        return $this->renderModuleFieldSetup('tenant_notice', 'Tenant notice fields', 'Configure tenant notice/vacate form fields.');
    }

    public function storeSystemSetupTenantNoticeFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'tenant_notice', 'Tenant notice fields updated.');
    }

    public function systemSetupMovementFields(): View
    {
        return $this->renderModuleFieldSetup('movement', 'Move-in / move-out fields', 'Configure move scheduling and movement tracking form fields.');
    }

    public function storeSystemSetupMovementFields(Request $request): RedirectResponse
    {
        return $this->storeModuleFieldSetup($request, 'movement', 'Move-in / move-out fields updated.');
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

    /**
     * @return list<array{
     *   key:string,
     *   label:string,
     *   type:string,
     *   required:bool,
     *   enabled:bool,
     *   help_text:string,
     *   options:string
     * }>
     */
    private function configuredFields(string $module): array
    {
        $settingKey = match ($module) {
            'unit' => 'system_setup_unit_fields_json',
            'amenity' => 'system_setup_amenity_fields_json',
            'landlord' => 'system_setup_landlord_fields_json',
            'lead' => 'system_setup_lead_fields_json',
            'rental_application' => 'system_setup_rental_application_fields_json',
            'tenant' => 'system_setup_tenant_fields_json',
            'lease' => 'system_setup_lease_fields_json',
            'maintenance' => 'system_setup_maintenance_fields_json',
            'vendor' => 'system_setup_vendor_fields_json',
            'invoice' => 'system_setup_invoice_fields_json',
            'tenant_notice' => 'system_setup_tenant_notice_fields_json',
            'movement' => 'system_setup_movement_fields_json',
            default => 'system_setup_property_onboarding_fields_json',
        };

        $raw = PropertyPortalSetting::getValue($settingKey, '');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $normalized = collect($decoded)
                    ->filter(fn ($row) => is_array($row))
                    ->map(function (array $row) {
                        return [
                            'key' => trim((string) ($row['key'] ?? '')),
                            'label' => trim((string) ($row['label'] ?? '')),
                            'type' => trim((string) ($row['type'] ?? 'text')),
                            'required' => (bool) ($row['required'] ?? false),
                            'enabled' => ! array_key_exists('enabled', $row) || (bool) $row['enabled'],
                            'help_text' => trim((string) ($row['help_text'] ?? '')),
                            'options' => trim((string) ($row['options'] ?? '')),
                        ];
                    })
                    ->filter(fn (array $row) => $row['key'] !== '' && $row['label'] !== '')
                    ->values()
                    ->all();

                if (! empty($normalized)) {
                    return $normalized;
                }
            }
        }

        return match ($module) {
            'unit' => $this->defaultUnitFields(),
            'amenity' => $this->defaultAmenityFields(),
            'landlord' => $this->defaultLandlordFields(),
            'lead' => $this->defaultLeadFields(),
            'rental_application' => $this->defaultRentalApplicationFields(),
            'tenant' => $this->defaultTenantFields(),
            'lease' => $this->defaultLeaseFields(),
            'maintenance' => $this->defaultMaintenanceFields(),
            'vendor' => $this->defaultVendorFields(),
            'invoice' => $this->defaultInvoiceFields(),
            'tenant_notice' => $this->defaultTenantNoticeFields(),
            'movement' => $this->defaultMovementFields(),
            default => $this->defaultPropertyOnboardingFields(),
        };
    }

    /**
     * @return list<array{key:string,label:string}>
     */
    private function systemSetupFieldModules(): array
    {
        return [
            ['key' => 'property_onboarding', 'label' => 'Property onboarding fields'],
            ['key' => 'unit', 'label' => 'Unit fields'],
            ['key' => 'amenity', 'label' => 'Amenity fields'],
            ['key' => 'landlord', 'label' => 'Landlord fields'],
            ['key' => 'lead', 'label' => 'Lead fields'],
            ['key' => 'rental_application', 'label' => 'Rental application fields'],
            ['key' => 'tenant', 'label' => 'Tenant fields'],
            ['key' => 'lease', 'label' => 'Lease fields'],
            ['key' => 'maintenance', 'label' => 'Maintenance fields'],
            ['key' => 'vendor', 'label' => 'Vendor fields'],
            ['key' => 'invoice', 'label' => 'Invoice & payment fields'],
            ['key' => 'tenant_notice', 'label' => 'Tenant notice fields'],
            ['key' => 'movement', 'label' => 'Move-in / move-out fields'],
        ];
    }

    private function isSystemSetupModuleConfigured(string $module): bool
    {
        $settingKey = $this->systemSetupModuleSettingKey($module);
        if ($settingKey === '') {
            return false;
        }

        $raw = PropertyPortalSetting::getValue($settingKey, '');
        if (! is_string($raw) || trim($raw) === '') {
            return false;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return false;
        }

        return collect($decoded)
            ->filter(fn ($row) => is_array($row))
            ->contains(function (array $row): bool {
                return trim((string) ($row['key'] ?? '')) !== ''
                    && trim((string) ($row['label'] ?? '')) !== '';
            });
    }

    private function systemSetupModuleSettingKey(string $module): string
    {
        return match ($module) {
            'unit' => 'system_setup_unit_fields_json',
            'amenity' => 'system_setup_amenity_fields_json',
            'landlord' => 'system_setup_landlord_fields_json',
            'lead' => 'system_setup_lead_fields_json',
            'rental_application' => 'system_setup_rental_application_fields_json',
            'tenant' => 'system_setup_tenant_fields_json',
            'lease' => 'system_setup_lease_fields_json',
            'maintenance' => 'system_setup_maintenance_fields_json',
            'vendor' => 'system_setup_vendor_fields_json',
            'invoice' => 'system_setup_invoice_fields_json',
            'tenant_notice' => 'system_setup_tenant_notice_fields_json',
            'movement' => 'system_setup_movement_fields_json',
            default => 'system_setup_property_onboarding_fields_json',
        };
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function validateDynamicFields(Request $request): array
    {
        $data = $request->validate([
            'fields' => ['nullable', 'array', 'max:60'],
            'fields.*.key' => ['nullable', 'string', 'max:100'],
            'fields.*.label' => ['nullable', 'string', 'max:120'],
            'fields.*.type' => ['nullable', 'in:text,textarea,number,select,date,checkbox'],
            'fields.*.required' => ['nullable', 'in:0,1'],
            'fields.*.enabled' => ['nullable', 'in:0,1'],
            'fields.*.help_text' => ['nullable', 'string', 'max:300'],
            'fields.*.options' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = [];
        foreach (($data['fields'] ?? []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $keyInput = trim((string) ($row['key'] ?? ''));
            $key = $keyInput !== '' ? Str::snake($keyInput) : Str::snake($label);
            $key = preg_replace('/[^a-z0-9_]/', '', (string) $key) ?? '';
            $type = trim((string) ($row['type'] ?? 'text'));

            if ($label === '' || $key === '') {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'type' => in_array($type, ['text', 'textarea', 'number', 'select', 'date', 'checkbox'], true) ? $type : 'text',
                'required' => (string) ($row['required'] ?? '0') === '1',
                'enabled' => (string) ($row['enabled'] ?? '1') === '1',
                'help_text' => trim((string) ($row['help_text'] ?? '')),
                'options' => trim((string) ($row['options'] ?? '')),
            ];
        }

        $uniqueByKey = [];
        foreach ($rows as $row) {
            $uniqueByKey[$row['key']] = $row;
        }

        return array_values($uniqueByKey);
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultPropertyOnboardingFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Property name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Official property name.', 'options' => ''],
            ['key' => 'code', 'label' => 'Property code', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Auto-generated when blank.', 'options' => ''],
            ['key' => 'city', 'label' => 'City', 'type' => 'select', 'required' => false, 'enabled' => true, 'help_text' => 'Town/city where property is located.', 'options' => 'Nairobi, Nakuru, Mombasa, Kisumu'],
            ['key' => 'address_line', 'label' => 'Address line', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Street/building description.', 'options' => ''],
            ['key' => 'commission_percent', 'label' => 'Commission %', 'type' => 'number', 'required' => false, 'enabled' => true, 'help_text' => 'Override commission for this property.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultUnitFields(): array
    {
        return [
            ['key' => 'property_id', 'label' => 'Property', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Parent property/building.', 'options' => ''],
            ['key' => 'label', 'label' => 'Unit label', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Door/unit identifier.', 'options' => ''],
            ['key' => 'unit_type', 'label' => 'Unit type', 'type' => 'select', 'required' => false, 'enabled' => true, 'help_text' => 'Bedsitter, 1BR, shop, office, etc.', 'options' => 'Bedsitter, 1BR, 2BR, 3BR, Shop, Office'],
            ['key' => 'bedrooms', 'label' => 'Bedrooms', 'type' => 'number', 'required' => false, 'enabled' => true, 'help_text' => 'No. of bedrooms where applicable.', 'options' => ''],
            ['key' => 'rent_amount', 'label' => 'Rent amount', 'type' => 'number', 'required' => true, 'enabled' => true, 'help_text' => 'Monthly rent amount.', 'options' => ''],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Current occupancy status.', 'options' => 'vacant, occupied, notice'],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultAmenityFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Amenity name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Display name of the amenity.', 'options' => ''],
            ['key' => 'property_id', 'label' => 'Property', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Property this amenity belongs to.', 'options' => ''],
            ['key' => 'is_shared', 'label' => 'Shared amenity', 'type' => 'checkbox', 'required' => false, 'enabled' => true, 'help_text' => 'Whether all units can use it.', 'options' => ''],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Usage restrictions or details.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultLandlordFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Landlord legal/display name.', 'options' => ''],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Primary email address.', 'options' => ''],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Primary contact number.', 'options' => ''],
            ['key' => 'id_number', 'label' => 'ID / registration', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'National ID or company registration.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultLeadFields(): array
    {
        return [
            ['key' => 'full_name', 'label' => 'Lead name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Prospect full name.', 'options' => ''],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Reachable phone number.', 'options' => ''],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Prospect email address.', 'options' => ''],
            ['key' => 'preferred_unit_type', 'label' => 'Preferred unit type', 'type' => 'select', 'required' => false, 'enabled' => true, 'help_text' => 'Preferred unit style.', 'options' => 'Bedsitter, 1BR, 2BR, 3BR, Shop, Office'],
            ['key' => 'budget', 'label' => 'Budget', 'type' => 'number', 'required' => false, 'enabled' => true, 'help_text' => 'Lead budget range.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultRentalApplicationFields(): array
    {
        return [
            ['key' => 'applicant_name', 'label' => 'Applicant name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Main applicant full name.', 'options' => ''],
            ['key' => 'unit_id', 'label' => 'Applied unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Unit being applied for.', 'options' => ''],
            ['key' => 'monthly_income', 'label' => 'Monthly income', 'type' => 'number', 'required' => false, 'enabled' => true, 'help_text' => 'Stated applicant income.', 'options' => ''],
            ['key' => 'employment_status', 'label' => 'Employment status', 'type' => 'select', 'required' => false, 'enabled' => true, 'help_text' => 'Current employment state.', 'options' => 'Employed, Self-employed, Student, Unemployed'],
            ['key' => 'references', 'label' => 'References', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Referees and contacts.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultTenantFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tenant name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Tenant full legal name.', 'options' => ''],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Primary email address.', 'options' => ''],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Primary mobile number.', 'options' => ''],
            ['key' => 'id_number', 'label' => 'ID number', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'National ID / passport.', 'options' => ''],
            ['key' => 'emergency_contact', 'label' => 'Emergency contact', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Next of kin details.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultLeaseFields(): array
    {
        return [
            ['key' => 'tenant_id', 'label' => 'Tenant', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Tenant to be leased.', 'options' => ''],
            ['key' => 'property_unit_id', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Unit under lease.', 'options' => ''],
            ['key' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true, 'enabled' => true, 'help_text' => 'Lease commencement date.', 'options' => ''],
            ['key' => 'end_date', 'label' => 'End date', 'type' => 'date', 'required' => false, 'enabled' => true, 'help_text' => 'Lease end or renewal date.', 'options' => ''],
            ['key' => 'rent_amount', 'label' => 'Rent amount', 'type' => 'number', 'required' => true, 'enabled' => true, 'help_text' => 'Contract rent amount.', 'options' => ''],
            ['key' => 'deposit_amount', 'label' => 'Deposit amount', 'type' => 'number', 'required' => false, 'enabled' => true, 'help_text' => 'Security deposit collected.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultMaintenanceFields(): array
    {
        return [
            ['key' => 'property_unit_id', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Unit with issue/request.', 'options' => ''],
            ['key' => 'category', 'label' => 'Issue category', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Plumbing, electrical, cleaning, etc.', 'options' => 'Plumbing, Electrical, Cleaning, Structural, Other'],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Operational urgency level.', 'options' => 'low, medium, high, critical'],
            ['key' => 'description', 'label' => 'Issue description', 'type' => 'textarea', 'required' => true, 'enabled' => true, 'help_text' => 'Detailed maintenance notes.', 'options' => ''],
            ['key' => 'target_date', 'label' => 'Target completion date', 'type' => 'date', 'required' => false, 'enabled' => true, 'help_text' => 'Expected close date.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultVendorFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Vendor name', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Supplier/company name.', 'options' => ''],
            ['key' => 'service_category', 'label' => 'Service category', 'type' => 'select', 'required' => false, 'enabled' => true, 'help_text' => 'Work domain offered by vendor.', 'options' => 'Plumbing, Electrical, Security, Cleaning, General repairs'],
            ['key' => 'contact_phone', 'label' => 'Phone', 'type' => 'text', 'required' => true, 'enabled' => true, 'help_text' => 'Primary phone contact.', 'options' => ''],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false, 'enabled' => true, 'help_text' => 'Primary email address.', 'options' => ''],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Rates, SLA, or compliance notes.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultInvoiceFields(): array
    {
        return [
            ['key' => 'pm_tenant_id', 'label' => 'Tenant', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Tenant being billed.', 'options' => ''],
            ['key' => 'property_unit_id', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Billed unit.', 'options' => ''],
            ['key' => 'invoice_type', 'label' => 'Invoice type', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Charge source/classification.', 'options' => 'rent, water, service, penalty, other'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'enabled' => true, 'help_text' => 'Invoice amount.', 'options' => ''],
            ['key' => 'due_date', 'label' => 'Due date', 'type' => 'date', 'required' => true, 'enabled' => true, 'help_text' => 'Payment due date.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultTenantNoticeFields(): array
    {
        return [
            ['key' => 'pm_tenant_id', 'label' => 'Tenant', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Notice recipient.', 'options' => ''],
            ['key' => 'property_unit_id', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Unit reference for notice.', 'options' => ''],
            ['key' => 'notice_type', 'label' => 'Notice type', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Reason/category of notice.', 'options' => 'vacate, renewal, warning, other'],
            ['key' => 'notice_date', 'label' => 'Notice date', 'type' => 'date', 'required' => true, 'enabled' => true, 'help_text' => 'Date served.', 'options' => ''],
            ['key' => 'details', 'label' => 'Notice details', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Context and instructions.', 'options' => ''],
        ];
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,enabled:bool,help_text:string,options:string}>
     */
    private function defaultMovementFields(): array
    {
        return [
            ['key' => 'property_unit_id', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Unit for move event.', 'options' => ''],
            ['key' => 'movement_type', 'label' => 'Movement type', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Move-in or move-out.', 'options' => 'move_in, move_out'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'enabled' => true, 'help_text' => 'Planning/execution state.', 'options' => 'planned, in_progress, done, cancelled'],
            ['key' => 'scheduled_on', 'label' => 'Scheduled date', 'type' => 'date', 'required' => true, 'enabled' => true, 'help_text' => 'Expected move date.', 'options' => ''],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'enabled' => true, 'help_text' => 'Operational notes for field teams.', 'options' => ''],
        ];
    }

    private function renderModuleFieldSetup(string $module, string $title, string $subtitle): View
    {
        return view('property.agent.settings.system_setup.module_fields', [
            'module' => $module,
            'title' => $title,
            'subtitle' => $subtitle,
            'fields' => $this->configuredFields($module),
        ]);
    }

    private function storeModuleFieldSetup(Request $request, string $module, string $successMessage): RedirectResponse
    {
        $fields = $this->validateDynamicFields($request);
        $settingKey = 'system_setup_'.$module.'_fields_json';
        PropertyPortalSetting::setValue(
            $settingKey,
            json_encode($fields, JSON_UNESCAPED_UNICODE)
        );
        PropertyPortalSetting::setValue('system_setup_forms_count', (string) max((int) PropertyPortalSetting::getValue('system_setup_forms_count', '2'), count($fields)));

        return back()->with('success', __($successMessage));
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
