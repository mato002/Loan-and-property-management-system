<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\Concerns\RecordsPmPortalDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentWorkspaceFormController extends Controller
{
    use RecordsPmPortalDraft;

    private const FORMS = [
        'settings-automation-rule',
        'settings-invite-user',
        'settings-commission-plan',
        'listings-import-leads',
        'listings-add-lead',
        'listings-publish-vacant',
        'communications-template',
        'communications-bulk-campaign',
        'financials-invoice-commission',
        'financials-remittance',
        'vendors-work-records-zip',
        'vendors-quote-matrix',
        'properties-amenity-library',
        'tenants-notice',
        'tenants-renewal-email',
        'revenue-penalty-rule',
        'tenants-schedule-move',
    ];

    public function show(string $form): View
    {
        if (! in_array($form, self::FORMS, true)) {
            abort(404);
        }

        $def = $this->definition($form);

        return view('property.workspace.draft_form', [
            'formKey' => $form,
            'storeRoute' => 'property.workspace.form.store',
            'title' => $def['title'],
            'subtitle' => $def['subtitle'],
            'backRoute' => $def['back_route'],
            'backLabel' => $def['back_label'],
            'fields' => $def['fields'],
            'submitLabel' => $def['submit_label'],
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        if (! in_array($form, self::FORMS, true)) {
            abort(404);
        }

        $def = $this->definition($form);
        $data = $request->validate($def['rules']);
        [$notes, $context] = $this->resolveNotesAndContext($request, $form, $data);

        $this->savePmPortalDraft($request, 'agent', $def['action_key'], $notes, $context);

        return redirect()
            ->route($def['back_route'])
            ->with('success', $def['success']);
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function resolveNotesAndContext(Request $request, string $form, array $data): array
    {
        $notes = null;
        $ctx = [];

        switch ($form) {
            case 'settings-automation-rule':
                $notes = $data['actions'] ?? null;
                $ctx = array_filter([
                    'rule_name' => $data['rule_name'] ?? null,
                    'trigger' => $data['trigger'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'settings-invite-user':
                $notes = $data['message'] ?? null;
                $ctx = array_filter([
                    'email' => $data['email'] ?? null,
                    'role' => $data['role'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'settings-commission-plan':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'plan_name' => $data['plan_name'] ?? null,
                    'default_rate' => $data['default_rate'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'listings-import-leads':
                $notes = $data['mapping_notes'] ?? null;
                if ($request->hasFile('csv_file')) {
                    $ctx['csv_file'] = $request->file('csv_file')->store('pm-workspace-uploads/'.now()->format('Y/m'), 'local');
                }

                break;

            case 'listings-add-lead':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'source' => $data['source'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'listings-publish-vacant':
                $notes = $data['selection_notes'] ?? null;
                $ctx = array_filter([
                    'channels' => $data['channels'] ?? null,
                    'go_live_date' => $data['go_live_date'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'communications-template':
                $notes = $data['body'] ?? null;
                $ctx = array_filter([
                    'template_name' => $data['template_name'] ?? null,
                    'subject' => $data['subject'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'communications-bulk-campaign':
                $notes = $data['message_outline'] ?? null;
                $ctx = array_filter([
                    'campaign_name' => $data['campaign_name'] ?? null,
                    'audience' => $data['audience'] ?? null,
                    'send_date' => $data['send_date'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'financials-invoice-commission':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'period' => $data['period'] ?? null,
                    'owners_scope' => $data['owners_scope'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'financials-remittance':
                $notes = $data['batch_notes'] ?? null;
                $ctx = array_filter([
                    'pay_date' => $data['pay_date'] ?? null,
                    'amount_hint' => $data['amount_hint'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'vendors-work-records-zip':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null,
                    'vendor_filter' => $data['vendor_filter'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'vendors-quote-matrix':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'rfq_reference' => $data['rfq_reference'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'properties-amenity-library':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'amenity_name' => $data['amenity_name'] ?? null,
                    'category' => $data['category'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'tenants-notice':
                $notes = $data['body'] ?? null;
                $ctx = array_filter([
                    'tenant_unit' => $data['tenant_unit'] ?? null,
                    'notice_type' => $data['notice_type'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'tenants-renewal-email':
                $notes = $data['message_notes'] ?? null;
                $ctx = array_filter([
                    'lease_filter' => $data['lease_filter'] ?? null,
                    'send_by' => $data['send_by'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'revenue-penalty-rule':
                $notes = $data['applies_when'] ?? null;
                $ctx = array_filter([
                    'rule_name' => $data['rule_name'] ?? null,
                    'amount_or_percent' => $data['amount_or_percent'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;

            case 'tenants-schedule-move':
                $notes = $data['notes'] ?? null;
                $ctx = array_filter([
                    'unit_reference' => $data['unit_reference'] ?? null,
                    'move_date' => $data['move_date'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');

                break;
        }

        return [$notes, $ctx];
    }

    /**
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     back_route: string,
     *     back_label: string,
     *     action_key: string,
     *     success: string,
     *     submit_label: string,
     *     rules: array<string, array<int, string>>,
     *     fields: list<array{name: string, label: string, type: string, required?: bool, placeholder?: string}>
     * }
     */
    private function definition(string $form): array
    {
        return match ($form) {
            'settings-automation-rule' => [
                'title' => 'New automation rule',
                'subtitle' => 'Capture trigger and actions as a draft until rules are stored in the rules engine.',
                'back_route' => 'property.settings.rules',
                'back_label' => '← Back to system rules',
                'action_key' => 'new_automation_rule',
                'success' => 'Automation rule draft saved.',
                'submit_label' => 'Save draft',
                'rules' => [
                    'rule_name' => ['required', 'string', 'max:255'],
                    'trigger' => ['nullable', 'string', 'max:2000'],
                    'actions' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'rule_name', 'label' => 'Rule name', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Rent reminder T-3'],
                    ['name' => 'trigger', 'label' => 'Trigger', 'type' => 'textarea', 'placeholder' => 'When should this run?'],
                    ['name' => 'actions', 'label' => 'Actions', 'type' => 'textarea', 'placeholder' => 'What should happen?'],
                ],
            ],
            'settings-invite-user' => [
                'title' => 'Invite team user',
                'subtitle' => 'Record an invite request; invitations send once your identity provider is connected.',
                'back_route' => 'property.settings.roles',
                'back_label' => '← Back to roles',
                'action_key' => 'invite_team_user',
                'success' => 'Invite request recorded.',
                'submit_label' => 'Record invite',
                'rules' => [
                    'email' => ['required', 'email', 'max:255'],
                    'role' => ['nullable', 'string', 'max:128'],
                    'message' => ['nullable', 'string', 'max:2000'],
                ],
                'fields' => [
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'role', 'label' => 'Role', 'type' => 'text', 'placeholder' => 'e.g. Property manager'],
                    ['name' => 'message', 'label' => 'Note to approver', 'type' => 'textarea'],
                ],
            ],
            'settings-commission-plan' => [
                'title' => 'New commission plan',
                'subtitle' => 'Draft plan details for finance review before activation.',
                'back_route' => 'property.settings.commission',
                'back_label' => '← Back to commission',
                'action_key' => 'new_commission_plan',
                'success' => 'Commission plan draft saved.',
                'submit_label' => 'Save draft',
                'rules' => [
                    'plan_name' => ['required', 'string', 'max:255'],
                    'default_rate' => ['nullable', 'string', 'max:64'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'plan_name', 'label' => 'Plan name', 'type' => 'text', 'required' => true],
                    ['name' => 'default_rate', 'label' => 'Default rate', 'type' => 'text', 'placeholder' => 'e.g. 8.5% or flat amount'],
                    ['name' => 'notes', 'label' => 'Terms & exceptions', 'type' => 'textarea'],
                ],
            ],
            'listings-import-leads' => [
                'title' => 'Import leads (CSV)',
                'subtitle' => 'Upload a CSV and describe column mapping; import runs when the pipeline is connected.',
                'back_route' => 'property.listings.leads',
                'back_label' => '← Back to leads',
                'action_key' => 'import_leads_csv',
                'success' => 'Lead import request recorded.',
                'submit_label' => 'Submit import request',
                'rules' => [
                    'csv_file' => ['nullable', 'file', 'max:12288', 'mimes:csv,txt'],
                    'mapping_notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'csv_file', 'label' => 'CSV file', 'type' => 'file'],
                    ['name' => 'mapping_notes', 'label' => 'Column mapping notes', 'type' => 'textarea'],
                ],
            ],
            'listings-add-lead' => [
                'title' => 'Add lead',
                'subtitle' => 'Capture a lead manually until CRM sync is enabled.',
                'back_route' => 'property.listings.leads',
                'back_label' => '← Back to leads',
                'action_key' => 'add_lead',
                'success' => 'Lead draft saved.',
                'submit_label' => 'Save lead',
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:64'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'source' => ['nullable', 'string', 'max:128'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'source', 'label' => 'Source', 'type' => 'text', 'placeholder' => 'e.g. Website form'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                ],
            ],
            'listings-publish-vacant' => [
                'title' => 'Publish vacant listings',
                'subtitle' => 'Describe which units and channels; publishing executes when listings are wired.',
                'back_route' => 'property.listings.vacant',
                'back_label' => '← Back to vacant listings',
                'action_key' => 'publish_vacant_listings',
                'success' => 'Publish request recorded.',
                'submit_label' => 'Submit publish request',
                'rules' => [
                    'selection_notes' => ['required', 'string', 'max:5000'],
                    'channels' => ['nullable', 'string', 'max:500'],
                    'go_live_date' => ['nullable', 'date'],
                ],
                'fields' => [
                    ['name' => 'selection_notes', 'label' => 'Units / listings to publish', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Property, unit IDs, or paste from your sheet'],
                    ['name' => 'channels', 'label' => 'Channels', 'type' => 'text', 'placeholder' => 'e.g. Website, partner portals'],
                    ['name' => 'go_live_date', 'label' => 'Target go-live', 'type' => 'date'],
                ],
            ],
            'communications-template' => [
                'title' => 'New message template',
                'subtitle' => 'Draft copy for approval before templates are versioned in the comms service.',
                'back_route' => 'property.communications.templates',
                'back_label' => '← Back to templates',
                'action_key' => 'new_message_template',
                'success' => 'Message template draft saved.',
                'submit_label' => 'Save template draft',
                'rules' => [
                    'template_name' => ['required', 'string', 'max:255'],
                    'subject' => ['nullable', 'string', 'max:255'],
                    'body' => ['nullable', 'string', 'max:10000'],
                ],
                'fields' => [
                    ['name' => 'template_name', 'label' => 'Template name', 'type' => 'text', 'required' => true],
                    ['name' => 'subject', 'label' => 'Subject line', 'type' => 'text'],
                    ['name' => 'body', 'label' => 'Body', 'type' => 'textarea'],
                ],
            ],
            'communications-bulk-campaign' => [
                'title' => 'New bulk campaign',
                'subtitle' => 'Plan an audience and schedule; sends run when the campaign runner is connected.',
                'back_route' => 'property.communications.bulk',
                'back_label' => '← Back to bulk comms',
                'action_key' => 'new_bulk_campaign',
                'success' => 'Bulk campaign draft saved.',
                'submit_label' => 'Save campaign draft',
                'rules' => [
                    'campaign_name' => ['required', 'string', 'max:255'],
                    'audience' => ['nullable', 'string', 'max:2000'],
                    'send_date' => ['nullable', 'date'],
                    'message_outline' => ['nullable', 'string', 'max:10000'],
                ],
                'fields' => [
                    ['name' => 'campaign_name', 'label' => 'Campaign name', 'type' => 'text', 'required' => true],
                    ['name' => 'audience', 'label' => 'Audience', 'type' => 'textarea', 'placeholder' => 'Segments, filters, or paste list criteria'],
                    ['name' => 'send_date', 'label' => 'Planned send date', 'type' => 'date'],
                    ['name' => 'message_outline', 'label' => 'Message outline', 'type' => 'textarea'],
                ],
            ],
            'financials-invoice-commission' => [
                'title' => 'Invoice owners (commission)',
                'subtitle' => 'Record a billing batch request for finance.',
                'back_route' => 'property.financials.commission',
                'back_label' => '← Back to commission',
                'action_key' => 'invoice_owners_commission',
                'success' => 'Commission invoice batch request recorded.',
                'submit_label' => 'Submit request',
                'rules' => [
                    'period' => ['required', 'string', 'max:255'],
                    'owners_scope' => ['nullable', 'string', 'max:2000'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'period', 'label' => 'Billing period', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. March 2026'],
                    ['name' => 'owners_scope', 'label' => 'Owners / properties scope', 'type' => 'textarea'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                ],
            ],
            'financials-remittance' => [
                'title' => 'Run owner remittance',
                'subtitle' => 'Capture payout instructions for the treasury workflow.',
                'back_route' => 'property.financials.owner_balances',
                'back_label' => '← Back to owner balances',
                'action_key' => 'run_owner_remittance',
                'success' => 'Remittance run request recorded.',
                'submit_label' => 'Submit remittance request',
                'rules' => [
                    'pay_date' => ['nullable', 'date'],
                    'amount_hint' => ['nullable', 'string', 'max:255'],
                    'batch_notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'pay_date', 'label' => 'Pay date', 'type' => 'date'],
                    ['name' => 'amount_hint', 'label' => 'Amount / batch reference', 'type' => 'text'],
                    ['name' => 'batch_notes', 'label' => 'Payout notes', 'type' => 'textarea'],
                ],
            ],
            'vendors-work-records-zip' => [
                'title' => 'Request work records (ZIP)',
                'subtitle' => 'Describe the window and vendors; the archive job runs when document storage is connected.',
                'back_route' => 'property.vendors.work_records',
                'back_label' => '← Back to work records',
                'action_key' => 'download_work_records_zip',
                'success' => 'ZIP bundle request recorded.',
                'submit_label' => 'Request bundle',
                'rules' => [
                    'date_from' => ['nullable', 'date'],
                    'date_to' => ['nullable', 'date'],
                    'vendor_filter' => ['nullable', 'string', 'max:500'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'date_from', 'label' => 'From', 'type' => 'date'],
                    ['name' => 'date_to', 'label' => 'To', 'type' => 'date'],
                    ['name' => 'vendor_filter', 'label' => 'Vendor filter', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Additional notes', 'type' => 'textarea'],
                ],
            ],
            'vendors-quote-matrix' => [
                'title' => 'Quote matrix view',
                'subtitle' => 'Open a side-by-side comparison once RFQs are linked; this records your request for now.',
                'back_route' => 'property.vendors.quotes',
                'back_label' => '← Back to quotes',
                'action_key' => 'open_quote_matrix',
                'success' => 'Matrix view request recorded.',
                'submit_label' => 'Record request',
                'rules' => [
                    'rfq_reference' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'rfq_reference', 'label' => 'RFQ reference', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                ],
            ],
            'properties-amenity-library' => [
                'title' => 'Manage amenity library',
                'subtitle' => 'Propose additions or edits until the amenity catalog is editable in-app.',
                'back_route' => 'property.properties.amenities',
                'back_label' => '← Back to amenities',
                'action_key' => 'manage_amenity_library',
                'success' => 'Amenity library change request recorded.',
                'submit_label' => 'Submit request',
                'rules' => [
                    'amenity_name' => ['required', 'string', 'max:255'],
                    'category' => ['nullable', 'string', 'max:128'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'amenity_name', 'label' => 'Amenity name', 'type' => 'text', 'required' => true],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Change details', 'type' => 'textarea'],
                ],
            ],
            'tenants-notice' => [
                'title' => 'New tenant notice',
                'subtitle' => 'Draft notice content for compliance review before delivery.',
                'back_route' => 'property.tenants.notices',
                'back_label' => '← Back to notices',
                'action_key' => 'new_tenant_notice',
                'success' => 'Tenant notice draft saved.',
                'submit_label' => 'Save notice draft',
                'rules' => [
                    'tenant_unit' => ['required', 'string', 'max:255'],
                    'notice_type' => ['required', 'string', 'max:128'],
                    'body' => ['nullable', 'string', 'max:10000'],
                ],
                'fields' => [
                    ['name' => 'tenant_unit', 'label' => 'Tenant / unit', 'type' => 'text', 'required' => true],
                    ['name' => 'notice_type', 'label' => 'Notice type', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Rent increase, entry notice'],
                    ['name' => 'body', 'label' => 'Notice body', 'type' => 'textarea'],
                ],
            ],
            'tenants-renewal-email' => [
                'title' => 'Email renewal batch',
                'subtitle' => 'Describe which leases to include; mail merge sends when templates are wired.',
                'back_route' => 'property.tenants.expiry',
                'back_label' => '← Back to lease expiry',
                'action_key' => 'email_renewal_batch',
                'success' => 'Renewal email batch request recorded.',
                'submit_label' => 'Submit batch request',
                'rules' => [
                    'lease_filter' => ['nullable', 'string', 'max:2000'],
                    'send_by' => ['nullable', 'date'],
                    'message_notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'lease_filter', 'label' => 'Lease selection', 'type' => 'textarea', 'placeholder' => 'Expiry window, properties, or IDs'],
                    ['name' => 'send_by', 'label' => 'Send by', 'type' => 'date'],
                    ['name' => 'message_notes', 'label' => 'Message notes', 'type' => 'textarea'],
                ],
            ],
            'revenue-penalty-rule' => [
                'title' => 'New penalty rule',
                'subtitle' => 'Draft a penalty policy for review before it affects billing.',
                'back_route' => 'property.revenue.penalties',
                'back_label' => '← Back to penalties',
                'action_key' => 'new_penalty_rule',
                'success' => 'Penalty rule draft saved.',
                'submit_label' => 'Save draft',
                'rules' => [
                    'rule_name' => ['required', 'string', 'max:255'],
                    'amount_or_percent' => ['nullable', 'string', 'max:64'],
                    'applies_when' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'rule_name', 'label' => 'Rule name', 'type' => 'text', 'required' => true],
                    ['name' => 'amount_or_percent', 'label' => 'Amount or %', 'type' => 'text'],
                    ['name' => 'applies_when', 'label' => 'When it applies', 'type' => 'textarea'],
                ],
            ],
            'tenants-schedule-move' => [
                'title' => 'Schedule move',
                'subtitle' => 'Record a move-in/out request for operations.',
                'back_route' => 'property.tenants.movements',
                'back_label' => '← Back to movements',
                'action_key' => 'schedule_move',
                'success' => 'Move schedule request recorded.',
                'submit_label' => 'Submit request',
                'rules' => [
                    'unit_reference' => ['required', 'string', 'max:255'],
                    'move_date' => ['nullable', 'date'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                ],
                'fields' => [
                    ['name' => 'unit_reference', 'label' => 'Unit / tenant', 'type' => 'text', 'required' => true],
                    ['name' => 'move_date', 'label' => 'Move date', 'type' => 'date'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                ],
            ],
        };
    }
}
