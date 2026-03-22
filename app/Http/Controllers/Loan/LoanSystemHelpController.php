<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanAccessLog;
use App\Models\LoanSupportTicket;
use App\Models\LoanSupportTicketReply;
use App\Models\LoanSystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LoanSystemHelpController extends Controller
{
    /** @var list<string> */
    private const COMPANY_SETTING_KEYS = [
        'app_display_name',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'logo_url',
        'about_us',
        'support_contact_email',
    ];

    /** @var list<string> */
    private const PREFERENCES_SETTING_KEYS = [
        'default_timezone',
        'date_display_format',
        'records_per_page',
        'maintenance_notice',
        'payment_automation',
        'approval_levels',
        'client_loyalty_points',
    ];

    /**
     * @return array<string, string>
     */
    private function ticketCategories(): array
    {
        return [
            LoanSupportTicket::CATEGORY_GENERAL => 'General',
            LoanSupportTicket::CATEGORY_TECHNICAL => 'Technical',
            LoanSupportTicket::CATEGORY_BILLING => 'Billing',
            LoanSupportTicket::CATEGORY_ACCESS => 'Access / security',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ticketPriorities(): array
    {
        return [
            LoanSupportTicket::PRIORITY_LOW => 'Low',
            LoanSupportTicket::PRIORITY_NORMAL => 'Normal',
            LoanSupportTicket::PRIORITY_HIGH => 'High',
            LoanSupportTicket::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ticketStatuses(): array
    {
        return [
            LoanSupportTicket::STATUS_OPEN => 'Open',
            LoanSupportTicket::STATUS_IN_PROGRESS => 'In progress',
            LoanSupportTicket::STATUS_RESOLVED => 'Resolved',
            LoanSupportTicket::STATUS_CLOSED => 'Closed',
        ];
    }

    public function ticketsCreate(): View
    {
        return view('loan.system.tickets.create', [
            'title' => 'Create a ticket',
            'subtitle' => 'Describe the issue; your team can reply here.',
            'categories' => $this->ticketCategories(),
            'priorities' => $this->ticketPriorities(),
        ]);
    }

    public function ticketsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys($this->ticketCategories()))],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys($this->ticketPriorities()))],
        ]);

        $ticket = LoanSupportTicket::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => LoanSupportTicket::STATUS_OPEN,
        ]);

        return redirect()
            ->route('loan.system.tickets.show', $ticket)
            ->with('status', 'Ticket '.$ticket->fresh()->ticket_number.' created.');
    }

    public function ticketsIndex(Request $request): View
    {
        $q = LoanSupportTicket::query()->with(['user', 'assignedTo'])->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('mine') && $request->boolean('mine')) {
            $q->where('user_id', $request->user()->id);
        }

        $tickets = $q->paginate(20)->withQueryString();

        return view('loan.system.tickets.index', [
            'title' => 'Raised tickets',
            'subtitle' => 'Support and IT requests from staff.',
            'tickets' => $tickets,
            'statuses' => $this->ticketStatuses(),
        ]);
    }

    public function ticketsShow(LoanSupportTicket $loan_support_ticket): View
    {
        $loan_support_ticket->load(['user', 'assignedTo', 'replies.user']);

        return view('loan.system.tickets.show', [
            'ticket' => $loan_support_ticket,
            'statuses' => $this->ticketStatuses(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function ticketsEdit(LoanSupportTicket $loan_support_ticket): View
    {
        abort_unless((int) $loan_support_ticket->user_id === (int) auth()->id(), 403);

        return view('loan.system.tickets.edit', [
            'ticket' => $loan_support_ticket,
            'categories' => $this->ticketCategories(),
            'priorities' => $this->ticketPriorities(),
        ]);
    }

    public function ticketsUpdate(Request $request, LoanSupportTicket $loan_support_ticket): RedirectResponse
    {
        abort_unless((int) $loan_support_ticket->user_id === (int) $request->user()->id, 403);
        abort_unless($loan_support_ticket->status === LoanSupportTicket::STATUS_OPEN, 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys($this->ticketCategories()))],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys($this->ticketPriorities()))],
        ]);

        $loan_support_ticket->update($data);

        return redirect()
            ->route('loan.system.tickets.show', $loan_support_ticket)
            ->with('status', 'Ticket updated.');
    }

    public function ticketsDestroy(Request $request, LoanSupportTicket $loan_support_ticket): RedirectResponse
    {
        abort_unless($loan_support_ticket->canBeDeletedBy($request->user()), 403);

        $loan_support_ticket->delete();

        return redirect()->route('loan.system.tickets.index')->with('status', 'Ticket deleted.');
    }

    public function ticketsReplyStore(Request $request, LoanSupportTicket $loan_support_ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $internal = (int) $request->user()->id !== (int) $loan_support_ticket->user_id
            && $request->boolean('is_internal');

        LoanSupportTicketReply::query()->create([
            'loan_support_ticket_id' => $loan_support_ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => $internal,
        ]);

        $loan_support_ticket->touch();

        return redirect()
            ->route('loan.system.tickets.show', $loan_support_ticket)
            ->with('status', 'Reply added.');
    }

    public function ticketsStatusUpdate(Request $request, LoanSupportTicket $loan_support_ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys($this->ticketStatuses()))],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $loan_support_ticket->update([
            'status' => $data['status'],
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'resolution_notes' => isset($data['resolution_notes']) && $data['resolution_notes'] !== ''
                ? $data['resolution_notes']
                : null,
        ]);

        return redirect()
            ->route('loan.system.tickets.show', $loan_support_ticket)
            ->with('status', 'Ticket status updated.');
    }

    public function setupHub(): View
    {
        $this->ensureDefaultSettings();

        $formSetup = fn (string $page) => route('loan.system.form_setup.page', ['page' => $page]);

        $cards = [
            [
                'title' => 'Company Settings',
                'desc' => 'Set company name, logo, address, contacts & about us',
                'href' => route('loan.system.setup.company'),
                'icon' => 'building',
            ],
            [
                'title' => 'System Access & User roles',
                'desc' => 'Set login OTP & user access permissions in the system',
                'href' => $formSetup('access'),
                'icon' => 'access',
            ],
            [
                'title' => 'Loan form Setup',
                'desc' => 'Design the structure and details to be captured in loan form',
                'href' => route('loan.system.form_setup.client'),
                'icon' => 'document',
            ],
            [
                'title' => 'Loans Products',
                'desc' => 'Setup loan products, interest rates, duty fees & prepayments',
                'href' => $formSetup('loan-products'),
                'icon' => 'briefcase',
            ],
            [
                'title' => 'Salary Advances',
                'desc' => 'View and approve salary advance requests',
                'href' => route('loan.accounting.advances.index'),
                'icon' => 'banknote',
            ],
            [
                'title' => 'Salary advance form',
                'desc' => 'Design fields captured on the salary advance application',
                'href' => route('loan.system.form_setup.salary_advance'),
                'icon' => 'document',
            ],
            [
                'title' => 'Leave Settings',
                'desc' => 'Setup staff leaves approval workflow',
                'href' => $formSetup('leave-settings'),
                'icon' => 'tools',
            ],
            [
                'title' => 'Staff Structure',
                'desc' => 'Set company staff hierarchy & personal details form',
                'href' => $formSetup('staff-structure'),
                'icon' => 'org',
            ],
            [
                'title' => 'Client Bio-data Setup',
                'desc' => 'Design client registration form details & structure',
                'href' => $formSetup('client-biodata'),
                'icon' => 'idcard',
            ],
            [
                'title' => 'Group Lending',
                'desc' => 'Design client groups form & related group settings',
                'href' => $formSetup('group-lending'),
                'icon' => 'users',
            ],
            [
                'title' => 'Accounting',
                'desc' => 'Design requisition forms & petty cashbook settings',
                'href' => $formSetup('accounting-forms'),
                'icon' => 'chart-bar',
            ],
            [
                'title' => 'Staff Leaves',
                'desc' => 'Design leave application form, conditions & yearly leave days',
                'href' => $formSetup('staff-leaves'),
                'icon' => 'bed',
            ],
            [
                'title' => 'Staff Performance',
                'desc' => 'Setup performance indicators for staff & BI analysis',
                'href' => $formSetup('staff-performance'),
                'icon' => 'trending',
            ],
            [
                'title' => 'Loan Settings',
                'desc' => 'Configure loan settings e.g. checkoffs & reschedule',
                'href' => $formSetup('loan-settings'),
                'icon' => 'bookmark',
            ],
            [
                'title' => 'General Settings',
                'desc' => 'Payment automation, approval levels & client loyalty points',
                'href' => route('loan.system.setup.preferences'),
                'icon' => 'wrench',
            ],
        ];

        return view('loan.system.setup.hub', [
            'cards' => $cards,
        ]);
    }

    public function setupCompany(): View
    {
        $this->ensureDefaultSettings();

        return view('loan.system.setup.company', [
            'title' => 'Company settings',
            'subtitle' => 'Branding, contacts, and public-facing copy.',
            'settings' => $this->settingsRowsOrdered(self::COMPANY_SETTING_KEYS),
        ]);
    }

    public function setupCompanyUpdate(Request $request): RedirectResponse
    {
        return $this->persistSettingsSubset($request, self::COMPANY_SETTING_KEYS, 'loan.system.setup.company');
    }

    public function setupPreferences(): View
    {
        $this->ensureDefaultSettings();

        return view('loan.system.setup.preferences', [
            'title' => 'General settings',
            'subtitle' => 'Portal defaults, automation notes, and policy text.',
            'settings' => $this->settingsRowsOrdered(self::PREFERENCES_SETTING_KEYS),
        ]);
    }

    public function setupPreferencesUpdate(Request $request): RedirectResponse
    {
        return $this->persistSettingsSubset($request, self::PREFERENCES_SETTING_KEYS, 'loan.system.setup.preferences');
    }

    /**
     * @param  list<string>  $keys
     */
    private function settingsRowsOrdered(array $keys): Collection
    {
        $rows = LoanSystemSetting::query()->whereIn('key', $keys)->get()->keyBy('key');

        return collect($keys)->map(fn (string $k) => $rows->get($k))->filter();
    }

    /**
     * @param  list<string>  $keys
     */
    private function persistSettingsSubset(Request $request, array $keys, string $redirectRouteName): RedirectResponse
    {
        $this->ensureDefaultSettings();

        $rules = [];
        foreach ($keys as $key) {
            $rules['settings.'.$key] = ['nullable', 'string', 'max:20000'];
        }
        $validated = $request->validate($rules);

        foreach ($keys as $key) {
            $val = $validated['settings'][$key] ?? null;
            LoanSystemSetting::query()->where('key', $key)->update(['value' => $val]);
        }

        return redirect()->route($redirectRouteName)->with('status', 'Settings saved.');
    }

    public function accessLogsIndex(Request $request): View
    {
        $q = LoanAccessLog::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $q->where('method', strtoupper($request->string('method')));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(function ($w) use ($term) {
                $w->where('path', 'like', $term)
                    ->orWhere('route_name', 'like', $term);
            });
        }

        $logs = $q->paginate(40)->withQueryString();
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return view('loan.system.access_logs.index', [
            'title' => 'Access logs',
            'subtitle' => 'Loan portal page views and actions (from sign-in onward).',
            'logs' => $logs,
            'users' => $users,
        ]);
    }

    private function ensureDefaultSettings(): void
    {
        $defaults = [
            ['key' => 'app_display_name', 'label' => 'Application / company display name', 'group' => 'company', 'value' => 'Loan Manager'],
            ['key' => 'company_name', 'label' => 'Registered company name', 'group' => 'company', 'value' => ''],
            ['key' => 'company_address', 'label' => 'Address (multiline)', 'group' => 'company', 'value' => ''],
            ['key' => 'company_phone', 'label' => 'Main phone', 'group' => 'company', 'value' => ''],
            ['key' => 'company_email', 'label' => 'Main email', 'group' => 'company', 'value' => ''],
            ['key' => 'company_website', 'label' => 'Website URL', 'group' => 'company', 'value' => ''],
            ['key' => 'logo_url', 'label' => 'Logo URL (or path)', 'group' => 'company', 'value' => ''],
            ['key' => 'about_us', 'label' => 'About us (public / reports)', 'group' => 'company', 'value' => ''],
            ['key' => 'support_contact_email', 'label' => 'Support contact email', 'group' => 'company', 'value' => ''],
            ['key' => 'default_timezone', 'label' => 'Default timezone', 'group' => 'preferences', 'value' => config('app.timezone', 'UTC')],
            ['key' => 'date_display_format', 'label' => 'Date display format (PHP)', 'group' => 'preferences', 'value' => 'Y-m-d'],
            ['key' => 'records_per_page', 'label' => 'Default table page size', 'group' => 'preferences', 'value' => '20'],
            ['key' => 'maintenance_notice', 'label' => 'Maintenance / banner notice (optional)', 'group' => 'preferences', 'value' => ''],
            ['key' => 'payment_automation', 'label' => 'Payment automation (notes / rules)', 'group' => 'preferences', 'value' => ''],
            ['key' => 'approval_levels', 'label' => 'Approval levels & thresholds', 'group' => 'preferences', 'value' => ''],
            ['key' => 'client_loyalty_points', 'label' => 'Client loyalty / royalty points rules', 'group' => 'preferences', 'value' => ''],
        ];

        foreach ($defaults as $row) {
            LoanSystemSetting::query()->firstOrCreate(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'group' => $row['group'],
                    'value' => $row['value'],
                ]
            );
        }
    }
}
