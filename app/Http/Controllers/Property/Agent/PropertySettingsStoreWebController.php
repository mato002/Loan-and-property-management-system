<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PropertyPortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function commission(): View
    {
        return view('property.agent.settings.commission', [
            'defaultPercent' => PropertyPortalSetting::getValue('commission_default_percent', ''),
            'notes' => PropertyPortalSetting::getValue('commission_notes', ''),
        ]);
    }

    public function storeCommission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_default_percent' => ['nullable', 'string', 'max:32'],
            'commission_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        PropertyPortalSetting::setValue('commission_default_percent', $data['commission_default_percent'] ?? '');
        PropertyPortalSetting::setValue('commission_notes', $data['commission_notes'] ?? '');

        return back()->with('success', __('Commission settings saved.'));
    }

    public function payments(): View
    {
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
            'company_logo_url' => ['nullable', 'url', 'max:2048'],
            'company_logo' => ['nullable', 'image', 'max:4096'],
            'site_favicon_url' => ['nullable', 'url', 'max:2048'],
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
        }

        if (array_key_exists('company_logo_url', $data)) {
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
