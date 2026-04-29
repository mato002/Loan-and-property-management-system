<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanAccessLog;
use App\Models\LoanAuditConcern;
use App\Models\LoanAuditConcernMessage;
use App\Models\LoanBookLoan;
use App\Models\LoanDepartment;
use App\Models\LoanJobTitle;
use App\Models\LoanProduct;
use App\Models\LoanRole;
use App\Models\LoanTemporaryAccessRequest;
use App\Models\LoanSupportTicket;
use App\Models\LoanSupportTicketReply;
use App\Models\LoanSystemSetting;
use App\Support\TabularExport;
use App\Models\User;
use App\Services\LoanSecurityPolicyService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanSystemHelpController extends Controller
{
    public function __construct(private readonly LoanBookLoanUpdateService $loanMath)
    {
    }

    /** @var list<string> */
    private const COMPANY_SETTING_KEYS = [
        'app_display_name',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'logo_url',
        'favicon_url',
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
        'org_structure_change_requires_approval',
        'org_structure_effective_dated_history',
        'loan_repayment_allocation_order',
        'loan_accounting_event_mappings_json',
    ];

    /** @var list<string> */
    private const CLIENT_SETTINGS_KEYS = [
        'client_onboarding_required_documents',
        'client_onboarding_kyc_mode',
        'client_onboarding_auto_activate',
        'client_onboarding_requires_guarantor',
        'client_onboarding_minimum_age',
        'client_onboarding_maximum_age',
        'client_onboarding_default_client_status',
        'client_onboarding_blacklist_screening',
        'client_onboarding_notes',
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
        $modules = [
            [
                'key' => 'foundation',
                'title' => 'Foundation',
                'cards' => [
                    [
                        'title' => 'Company Settings',
                        'desc' => 'Set company name, logo, address, contacts, and public profile.',
                        'href' => route('loan.system.setup.company'),
                        'icon' => 'building',
                        'status' => 'completed',
                        'priority' => 'required',
                    ],
                    [
                        'title' => 'General Settings',
                        'desc' => 'Configure automation defaults, approvals, and policy notes.',
                        'href' => route('loan.system.setup.preferences'),
                        'icon' => 'wrench',
                        'status' => 'needs_review',
                        'priority' => 'required',
                    ],
                ],
            ],
            [
                'key' => 'lending',
                'title' => 'Lending',
                'cards' => [
                    [
                        'title' => 'Loans Products',
                        'desc' => 'Setup loan products, interest rates, duty fees, and prepayments.',
                        'href' => route('loan.system.setup.loan_products'),
                        'icon' => 'briefcase',
                        'status' => 'needs_review',
                        'priority' => 'required',
                    ],
                    [
                        'title' => 'Loan Settings',
                        'desc' => 'Configure checkoffs, reschedules, and policy capture fields.',
                        'href' => $formSetup('loan-settings'),
                        'icon' => 'bookmark',
                        'status' => 'not_configured',
                        'priority' => 'required',
                    ],
                    [
                        'title' => 'Salary Advances',
                        'desc' => 'Manage salary advance requests and approvals.',
                        'href' => route('loan.accounting.advances.index'),
                        'icon' => 'banknote',
                        'status' => 'needs_review',
                        'priority' => 'recommended',
                    ],
                    [
                        'title' => 'Loan Form Setup',
                        'desc' => 'Design the fields captured on client and staff loan forms.',
                        'href' => route('loan.system.form_setup.client'),
                        'icon' => 'document',
                        'status' => 'not_configured',
                        'priority' => 'required',
                        'badge' => 'Recommended first',
                    ],
                    [
                        'title' => 'Client Settings',
                        'desc' => 'Configure client onboarding defaults, KYC controls, and eligibility rules.',
                        'href' => route('loan.system.setup.client_settings'),
                        'icon' => 'idcard',
                        'status' => 'needs_review',
                        'priority' => 'optional',
                        'badge' => 'Onboarding controls',
                    ],
                ],
            ],
            [
                'key' => 'people-hr',
                'title' => 'People & HR',
                'cards' => [
                    [
                        'title' => 'Staff Structure',
                        'desc' => 'Define hierarchy and staff profile structure.',
                        'href' => $formSetup('staff-structure'),
                        'icon' => 'org',
                        'status' => 'not_configured',
                        'priority' => 'required',
                    ],
                    [
                        'title' => 'System Access & User roles',
                        'desc' => 'Manage login OTP settings, roles, and access permissions.',
                        'href' => route('loan.system.setup.access_roles'),
                        'icon' => 'access',
                        'status' => 'needs_review',
                        'priority' => 'required',
                        'badge' => 'Permission required',
                    ],
                    [
                        'title' => 'Staff Performance',
                        'desc' => 'Define KPI and performance analysis setup fields.',
                        'href' => $formSetup('staff-performance'),
                        'icon' => 'trending',
                        'status' => 'not_configured',
                        'priority' => 'recommended',
                    ],
                    [
                        'title' => 'Departments',
                        'desc' => 'Add, activate, and manage organizational departments.',
                        'href' => route('loan.system.setup.departments'),
                        'icon' => 'org',
                        'status' => 'completed',
                        'priority' => 'required',
                    ],
                ],
            ],
            [
                'key' => 'operations',
                'title' => 'Operations',
                'cards' => [
                    [
                        'title' => 'Accounting',
                        'desc' => 'Configure accounting requisition and related forms.',
                        'href' => $formSetup('accounting-forms'),
                        'icon' => 'chart-bar',
                        'status' => 'needs_review',
                        'priority' => 'required',
                    ],
                    [
                        'title' => 'SMS Configurations',
                        'desc' => 'Prepare SMS provider configuration and notification templates.',
                        'href' => null,
                        'icon' => 'document',
                        'status' => 'not_configured',
                        'priority' => 'optional',
                        'coming_soon' => true,
                        'badge' => 'Coming soon',
                    ],
                    [
                        'title' => 'Wallets Configurations',
                        'desc' => 'Wallet and M-Pesa-equivalent configuration placeholders.',
                        'href' => null,
                        'icon' => 'banknote',
                        'status' => 'not_configured',
                        'priority' => 'optional',
                        'coming_soon' => true,
                        'badge' => 'Coming soon',
                    ],
                ],
            ],
        ];

        $priorityRank = ['required' => 0, 'recommended' => 1, 'optional' => 2];

        foreach ($modules as &$module) {
            usort($module['cards'], function (array $a, array $b) use ($priorityRank): int {
                $aRank = $priorityRank[$a['priority'] ?? 'optional'] ?? 9;
                $bRank = $priorityRank[$b['priority'] ?? 'optional'] ?? 9;

                if ($aRank === $bRank) {
                    return strcmp($a['title'], $b['title']);
                }

                return $aRank <=> $bRank;
            });

            $total = count($module['cards']);
            $completed = collect($module['cards'])->where('status', 'completed')->count();
            $critical = collect($module['cards'])->where('status', 'critical')->count();
            $attention = collect($module['cards'])->whereIn('status', ['not_configured', 'needs_review', 'critical'])->count();
            $ready = $attention === 0;
            $module['progress_percent'] = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
            $module['summary'] = $ready ? "{$completed}/{$total} completed" : "{$attention} pending";
            $module['status_label'] = $ready ? 'Ready' : ($critical > 0 ? 'Incomplete' : 'Needs attention');
            $module['status_tone'] = $ready ? 'green' : ($critical > 0 ? 'red' : 'orange');
        }
        unset($module);

        $allCards = collect($modules)->pluck('cards')->flatten(1)->values();
        $trackedCards = $allCards->filter(fn (array $card): bool => ! ($card['coming_soon'] ?? false))->values();
        $trackedTotal = $trackedCards->count();
        $trackedCompleted = $trackedCards->where('status', 'completed')->count();
        $readinessPercent = $trackedTotal > 0 ? (int) round(($trackedCompleted / $trackedTotal) * 100) : 0;

        $missingRequiredCount = $trackedCards
            ->where('priority', 'required')
            ->where('status', '!=', 'completed')
            ->count();
        $criticalCount = $trackedCards->where('status', 'critical')->count();
        $needsAttentionCount = $trackedCards->whereIn('status', ['not_configured', 'needs_review', 'critical'])->count();
        $resumeCard = $trackedCards->first(fn (array $card): bool => ($card['status'] ?? null) !== 'completed' && ! empty($card['href']));

        $insights = [
            [
                'label' => "{$missingRequiredCount} required item(s) pending",
                'module_key' => 'lending',
            ],
            [
                'label' => 'Loan Products need review',
                'module_key' => 'lending',
            ],
            [
                'label' => 'Wallets configuration is pending',
                'module_key' => 'operations',
            ],
        ];

        $quickActions = [
            [
                'label' => 'Complete Required Setup',
                'href' => '#module-foundation',
            ],
            [
                'label' => 'Resume Last Task',
                'href' => $resumeCard['href'] ?? '#module-lending',
            ],
            [
                'label' => 'View Missing Configurations',
                'href' => '#module-operations',
            ],
        ];

        return view('loan.system.setup.hub', [
            'modules' => $modules,
            'readinessPercent' => $readinessPercent,
            'moduleBreakdown' => $modules,
            'quickActions' => $quickActions,
            'insights' => $insights,
            'needsAttentionCount' => $needsAttentionCount,
            'criticalCount' => $criticalCount,
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

    public function setupClientSettings(): View
    {
        $this->ensureDefaultSettings();

        return view('loan.system.setup.client_settings', [
            'title' => 'Client settings',
            'subtitle' => 'Onboarding defaults, KYC controls, and client eligibility policies.',
            'settings' => $this->settingsRowsOrdered(self::CLIENT_SETTINGS_KEYS),
        ]);
    }

    public function setupClientSettingsUpdate(Request $request): RedirectResponse
    {
        return $this->persistSettingsSubset($request, self::CLIENT_SETTINGS_KEYS, 'loan.system.setup.client_settings');
    }

    public function setupLoanProducts(): View
    {
        abort_unless(Schema::hasTable('loan_products'), 404, 'Loan products table not found. Run migrations.');

        $activeLoanCounts = LoanBookLoan::query()
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->selectRaw('product_name, COUNT(*) as c')
            ->groupBy('product_name')
            ->pluck('c', 'product_name')
            ->map(fn ($count) => (int) $count)
            ->all();

        $hasProductCharges = Schema::hasTable('loan_product_charges');
        $productsQuery = LoanProduct::query()
            ->orderByDesc('is_active')
            ->orderBy('name');
        if ($hasProductCharges) {
            $productsQuery->with(['charges' => fn ($q) => $q->where('is_active', true)->orderBy('id')]);
        }

        return view('loan.system.setup.loan_products', [
            'title' => 'Loan products',
            'subtitle' => 'Set default interest rates and repayment periods per product.',
            'products' => $productsQuery->get(),
            'activeLoanCounts' => $activeLoanCounts,
            'hasProductCharges' => $hasProductCharges,
        ]);
    }

    public function setupLoanProductsCreate(): View
    {
        abort_unless(Schema::hasTable('loan_products'), 404, 'Loan products table not found. Run migrations.');

        return view('loan.system.setup.loan_products_create', [
            'title' => 'Add loan product',
            'subtitle' => 'Create a new product with default term and interest settings.',
        ]);
    }

    public function setupLoanProductsStore(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_products'), 404, 'Loan products table not found. Run migrations.');
        $hasDefaultTermUnit = Schema::hasColumn('loan_products', 'default_term_unit');
        $hasDefaultRatePeriod = Schema::hasColumn('loan_products', 'default_interest_rate_period');
        $hasDefaultRateType = Schema::hasColumn('loan_products', 'default_interest_rate_type');
        $hasPenaltyAmountType = Schema::hasColumn('loan_products', 'penalty_amount_type');
        $hasRolloverFeesType = Schema::hasColumn('loan_products', 'rollover_fees_type');
        $hasLoanOffsetFeesType = Schema::hasColumn('loan_products', 'loan_offset_fees_type');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'default_interest_rate_type' => ['nullable', 'in:fixed,percent'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'in:daily,weekly,monthly'],
            'default_interest_rate_period' => ['nullable', 'in:daily,weekly,monthly,annual'],
            'payment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'total_interest_amount' => ['nullable', 'string', 'max:30'],
            'interest_duration_value' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_type' => ['nullable', 'in:flat_rate,reducing_balance,amortized,simple_interest'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'arrears_penalty_scope' => ['nullable', 'in:whole_loan,per_installment,none'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'penalty_amount_type' => ['nullable', 'in:fixed,percent'],
            'rollover_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'rollover_fees_type' => ['nullable', 'in:fixed,percent'],
            'loan_offset_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'loan_offset_fees_type' => ['nullable', 'in:fixed,percent'],
            'repay_waiver_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'client_application_scope' => ['nullable', 'in:any_client,no_running_loans,new_clients_only,existing_clients_only'],
            'installment_display_mode' => ['nullable', 'in:all_installments,due_only,summary'],
            'exempt_from_checkoffs' => ['nullable', 'in:0,1'],
            'cluster_name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['nullable', 'in:0,1'],
            'charges' => ['nullable', 'array'],
            'charges.*.name' => ['nullable', 'string', 'max:160'],
            'charges.*.type' => ['nullable', 'in:fixed,percent'],
            'charges.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'charges.*.applies_to_stage' => ['nullable', 'in:application,installment,repeat_application,certain_installments,loan_deduction,added_to_loan'],
            'charges.*.applies_to_client_scope' => ['nullable', 'in:all,new_clients,existing_clients,checkoff_only,non_checkoff'],
        ]);

        $name = trim((string) $validated['name']);
        $payload = [
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'default_interest_rate' => isset($validated['default_interest_rate']) && $validated['default_interest_rate'] !== ''
                ? (float) $validated['default_interest_rate']
                : null,
            'default_term_months' => isset($validated['default_term_months']) && $validated['default_term_months'] !== ''
                ? (int) $validated['default_term_months']
                : null,
            'payment_interval_days' => isset($validated['payment_interval_days']) && $validated['payment_interval_days'] !== ''
                ? (int) $validated['payment_interval_days']
                : null,
            'total_interest_amount' => $this->parseTotalInterestAmount($validated['total_interest_amount'] ?? null),
            'interest_duration_value' => isset($validated['interest_duration_value']) && $validated['interest_duration_value'] !== ''
                ? (int) $validated['interest_duration_value']
                : null,
            'interest_type' => filled($validated['interest_type'] ?? null) ? (string) $validated['interest_type'] : null,
            'min_loan_amount' => isset($validated['min_loan_amount']) && $validated['min_loan_amount'] !== ''
                ? (float) $validated['min_loan_amount']
                : null,
            'max_loan_amount' => isset($validated['max_loan_amount']) && $validated['max_loan_amount'] !== ''
                ? (float) $validated['max_loan_amount']
                : null,
            'arrears_penalty_scope' => filled($validated['arrears_penalty_scope'] ?? null) ? (string) $validated['arrears_penalty_scope'] : null,
            'penalty_amount' => isset($validated['penalty_amount']) && $validated['penalty_amount'] !== ''
                ? (float) $validated['penalty_amount']
                : null,
            'rollover_fees' => isset($validated['rollover_fees']) && $validated['rollover_fees'] !== ''
                ? (float) $validated['rollover_fees']
                : null,
            'loan_offset_fees' => isset($validated['loan_offset_fees']) && $validated['loan_offset_fees'] !== ''
                ? (float) $validated['loan_offset_fees']
                : null,
            'repay_waiver_days' => isset($validated['repay_waiver_days']) && $validated['repay_waiver_days'] !== ''
                ? (int) $validated['repay_waiver_days']
                : null,
            'client_application_scope' => filled($validated['client_application_scope'] ?? null) ? (string) $validated['client_application_scope'] : null,
            'installment_display_mode' => filled($validated['installment_display_mode'] ?? null) ? (string) $validated['installment_display_mode'] : null,
            'exempt_from_checkoffs' => ($validated['exempt_from_checkoffs'] ?? '0') === '1',
            'cluster_name' => filled($validated['cluster_name'] ?? null) ? trim((string) $validated['cluster_name']) : null,
            'is_active' => ($validated['is_active'] ?? '1') === '1',
        ];
        if ($hasDefaultTermUnit) {
            $payload['default_term_unit'] = (string) ($validated['default_term_unit'] ?? 'monthly');
        }
        if ($hasDefaultRatePeriod) {
            $payload['default_interest_rate_period'] = (string) ($validated['default_interest_rate_period'] ?? 'annual');
        }
        if ($hasDefaultRateType) {
            $payload['default_interest_rate_type'] = (string) ($validated['default_interest_rate_type'] ?? 'percent');
        }
        if ($hasPenaltyAmountType) {
            $payload['penalty_amount_type'] = (string) ($validated['penalty_amount_type'] ?? 'fixed');
        }
        if ($hasRolloverFeesType) {
            $payload['rollover_fees_type'] = (string) ($validated['rollover_fees_type'] ?? 'fixed');
        }
        if ($hasLoanOffsetFeesType) {
            $payload['loan_offset_fees_type'] = (string) ($validated['loan_offset_fees_type'] ?? 'fixed');
        }

        $product = LoanProduct::query()->updateOrCreate(
            ['name' => $name],
            $payload
        );

        if (Schema::hasTable('loan_product_charges')) {
            $charges = collect($validated['charges'] ?? [])
                ->map(function (array $charge): array {
                    return [
                        'charge_name' => trim((string) ($charge['name'] ?? '')),
                        'amount_type' => (string) ($charge['type'] ?? 'fixed'),
                        'amount' => (float) ($charge['amount'] ?? 0),
                        'applies_to_stage' => (string) ($charge['applies_to_stage'] ?? 'installment'),
                        'applies_to_client_scope' => (string) ($charge['applies_to_client_scope'] ?? 'all'),
                    ];
                })
                ->filter(fn (array $charge): bool => $charge['charge_name'] !== '' && $charge['amount'] > 0)
                ->values();

            if ($charges->isNotEmpty()) {
                $product->charges()->createMany(
                    $charges->map(fn (array $charge): array => array_merge($charge, ['is_active' => true]))->all()
                );
            }
        }

        return redirect()->route('loan.system.setup.loan_products')->with('status', 'Loan product saved.');
    }

    public function setupLoanProductsUpdate(Request $request, LoanProduct $loan_product): RedirectResponse
    {
        $hasDefaultTermUnit = Schema::hasColumn('loan_products', 'default_term_unit');
        $hasDefaultRatePeriod = Schema::hasColumn('loan_products', 'default_interest_rate_period');
        $hasDefaultRateType = Schema::hasColumn('loan_products', 'default_interest_rate_type');
        $hasPenaltyAmountType = Schema::hasColumn('loan_products', 'penalty_amount_type');
        $hasRolloverFeesType = Schema::hasColumn('loan_products', 'rollover_fees_type');
        $hasLoanOffsetFeesType = Schema::hasColumn('loan_products', 'loan_offset_fees_type');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('loan_products', 'name')->ignore($loan_product->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'default_interest_rate_type' => ['nullable', 'in:fixed,percent'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'in:daily,weekly,monthly'],
            'default_interest_rate_period' => ['nullable', 'in:daily,weekly,monthly,annual'],
            'payment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'total_interest_amount' => ['nullable', 'string', 'max:30'],
            'interest_duration_value' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_type' => ['nullable', 'in:flat_rate,reducing_balance,amortized,simple_interest'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'arrears_penalty_scope' => ['nullable', 'in:whole_loan,per_installment,none'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'penalty_amount_type' => ['nullable', 'in:fixed,percent'],
            'rollover_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'rollover_fees_type' => ['nullable', 'in:fixed,percent'],
            'loan_offset_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'loan_offset_fees_type' => ['nullable', 'in:fixed,percent'],
            'repay_waiver_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'client_application_scope' => ['nullable', 'in:any_client,no_running_loans,new_clients_only,existing_clients_only'],
            'installment_display_mode' => ['nullable', 'in:all_installments,due_only,summary'],
            'exempt_from_checkoffs' => ['nullable', 'in:0,1'],
            'cluster_name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'in:0,1'],
            'apply_to_existing_active_loans' => ['nullable', 'in:0,1'],
            'repricing_effective_date' => ['nullable', 'date'],
            'repricing_note' => ['nullable', 'string', 'max:500'],
            'charges' => ['nullable', 'array'],
            'charges.*.name' => ['nullable', 'string', 'max:160'],
            'charges.*.type' => ['nullable', 'in:fixed,percent'],
            'charges.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'charges.*.applies_to_stage' => ['nullable', 'in:application,installment,repeat_application,certain_installments,loan_deduction,added_to_loan'],
            'charges.*.applies_to_client_scope' => ['nullable', 'in:all,new_clients,existing_clients,checkoff_only,non_checkoff'],
        ]);

        $newDefaultInterestRate = isset($validated['default_interest_rate']) && $validated['default_interest_rate'] !== ''
            ? (float) $validated['default_interest_rate']
            : null;

        $oldName = (string) $loan_product->name;
        $newName = trim((string) $validated['name']);

        $updatePayload = [
            'name' => $newName,
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'default_interest_rate' => $newDefaultInterestRate,
            'default_term_months' => isset($validated['default_term_months']) && $validated['default_term_months'] !== ''
                ? (int) $validated['default_term_months']
                : null,
            'payment_interval_days' => isset($validated['payment_interval_days']) && $validated['payment_interval_days'] !== ''
                ? (int) $validated['payment_interval_days']
                : null,
            'total_interest_amount' => $this->parseTotalInterestAmount($validated['total_interest_amount'] ?? null),
            'interest_duration_value' => isset($validated['interest_duration_value']) && $validated['interest_duration_value'] !== ''
                ? (int) $validated['interest_duration_value']
                : null,
            'interest_type' => filled($validated['interest_type'] ?? null) ? (string) $validated['interest_type'] : null,
            'min_loan_amount' => isset($validated['min_loan_amount']) && $validated['min_loan_amount'] !== ''
                ? (float) $validated['min_loan_amount']
                : null,
            'max_loan_amount' => isset($validated['max_loan_amount']) && $validated['max_loan_amount'] !== ''
                ? (float) $validated['max_loan_amount']
                : null,
            'arrears_penalty_scope' => filled($validated['arrears_penalty_scope'] ?? null) ? (string) $validated['arrears_penalty_scope'] : null,
            'penalty_amount' => isset($validated['penalty_amount']) && $validated['penalty_amount'] !== ''
                ? (float) $validated['penalty_amount']
                : null,
            'rollover_fees' => isset($validated['rollover_fees']) && $validated['rollover_fees'] !== ''
                ? (float) $validated['rollover_fees']
                : null,
            'loan_offset_fees' => isset($validated['loan_offset_fees']) && $validated['loan_offset_fees'] !== ''
                ? (float) $validated['loan_offset_fees']
                : null,
            'repay_waiver_days' => isset($validated['repay_waiver_days']) && $validated['repay_waiver_days'] !== ''
                ? (int) $validated['repay_waiver_days']
                : null,
            'client_application_scope' => filled($validated['client_application_scope'] ?? null) ? (string) $validated['client_application_scope'] : null,
            'installment_display_mode' => filled($validated['installment_display_mode'] ?? null) ? (string) $validated['installment_display_mode'] : null,
            'exempt_from_checkoffs' => ($validated['exempt_from_checkoffs'] ?? '0') === '1',
            'cluster_name' => filled($validated['cluster_name'] ?? null) ? trim((string) $validated['cluster_name']) : null,
            'is_active' => $validated['is_active'] === '1',
        ];
        if ($hasDefaultTermUnit) {
            $updatePayload['default_term_unit'] = (string) ($validated['default_term_unit'] ?? 'monthly');
        }
        if ($hasDefaultRatePeriod) {
            $updatePayload['default_interest_rate_period'] = (string) ($validated['default_interest_rate_period'] ?? 'annual');
        }
        if ($hasDefaultRateType) {
            $updatePayload['default_interest_rate_type'] = (string) ($validated['default_interest_rate_type'] ?? 'percent');
        }
        if ($hasPenaltyAmountType) {
            $updatePayload['penalty_amount_type'] = (string) ($validated['penalty_amount_type'] ?? 'fixed');
        }
        if ($hasRolloverFeesType) {
            $updatePayload['rollover_fees_type'] = (string) ($validated['rollover_fees_type'] ?? 'fixed');
        }
        if ($hasLoanOffsetFeesType) {
            $updatePayload['loan_offset_fees_type'] = (string) ($validated['loan_offset_fees_type'] ?? 'fixed');
        }
        $loan_product->update($updatePayload);
        if (Schema::hasTable('loan_product_charges')) {
            $charges = collect($validated['charges'] ?? [])
                ->map(function (array $charge): array {
                    return [
                        'charge_name' => trim((string) ($charge['name'] ?? '')),
                        'amount_type' => (string) ($charge['type'] ?? 'fixed'),
                        'amount' => (float) ($charge['amount'] ?? 0),
                        'applies_to_stage' => (string) ($charge['applies_to_stage'] ?? 'installment'),
                        'applies_to_client_scope' => (string) ($charge['applies_to_client_scope'] ?? 'all'),
                    ];
                })
                ->filter(fn (array $charge): bool => $charge['charge_name'] !== '' && $charge['amount'] > 0)
                ->values();

            $loan_product->charges()->delete();
            if ($charges->isNotEmpty()) {
                $loan_product->charges()->createMany(
                    $charges->map(fn (array $charge): array => array_merge($charge, ['is_active' => true]))->all()
                );
            }
        }

        if ($newName !== $oldName) {
            $renameAudit = '[Product rename '.now()->format('Y-m-d H:i').'] Product name changed from "'.$oldName.'" to "'.$newName.'" by '
                .trim((string) ($request->user()?->name ?? 'System')).'.';

            LoanBookLoan::query()
                ->where('product_name', $oldName)
                ->orderBy('id')
                ->chunkById(200, function ($loans) use ($newName, $renameAudit): void {
                    foreach ($loans as $loan) {
                        $existingNotes = trim((string) ($loan->notes ?? ''));
                        $loan->update([
                            'product_name' => $newName,
                            'notes' => $existingNotes !== '' ? $existingNotes."\n".$renameAudit : $renameAudit,
                        ]);
                    }
                });

            \App\Models\LoanBookApplication::query()
                ->where('product_name', $oldName)
                ->update(['product_name' => $newName]);
        }

        $applyToExisting = ($validated['apply_to_existing_active_loans'] ?? '0') === '1';
        if (! $applyToExisting || $newDefaultInterestRate === null) {
            return redirect()->route('loan.system.setup.loan_products')->with('status', 'Loan product updated.');
        }

        $effectiveDate = filled($validated['repricing_effective_date'] ?? null)
            ? (string) $validated['repricing_effective_date']
            : now()->toDateString();
        $extraNote = trim((string) ($validated['repricing_note'] ?? ''));
        $actor = trim((string) ($request->user()?->name ?? 'System'));
        $auditNote = '[Rate update '.now()->format('Y-m-d H:i').'] Product "'.$loan_product->name.'" default rate changed to '
            .number_format($newDefaultInterestRate, 4).'% (effective '.$effectiveDate.') by '.$actor
            .($extraNote !== '' ? '. Note: '.$extraNote : '.');
        $targetProductName = (string) $loan_product->name;

        $affected = 0;

        DB::transaction(function () use ($targetProductName, $newDefaultInterestRate, $auditNote, &$affected): void {
            LoanBookLoan::query()
                ->where('product_name', $targetProductName)
                ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
                ->orderBy('id')
                ->chunkById(200, function ($loans) use ($newDefaultInterestRate, $auditNote, &$affected): void {
                    foreach ($loans as $loan) {
                        $principalOutstanding = max(0.0, (float) $loan->principal_outstanding);
                        if ($principalOutstanding <= 0.0) {
                            $principalOutstanding = max(0.0, (float) $loan->balance);
                        }
                        $recomputedInterest = $this->loanMath->estimateInterestForLoan(
                            $loan,
                            $principalOutstanding,
                            $newDefaultInterestRate
                        );
                        $existingNotes = trim((string) ($loan->notes ?? ''));
                        $loan->update([
                            'interest_rate' => $newDefaultInterestRate,
                            'principal_outstanding' => $principalOutstanding,
                            'interest_outstanding' => $recomputedInterest,
                            'balance' => round($principalOutstanding + $recomputedInterest + max(0.0, (float) $loan->fees_outstanding), 2),
                            'notes' => $existingNotes !== '' ? $existingNotes."\n".$auditNote : $auditNote,
                        ]);
                        $affected++;
                    }
                });
        });

        return redirect()
            ->route('loan.system.setup.loan_products')
            ->with('status', 'Loan product updated. Repriced '.$affected.' active loan(s).');
    }

    public function setupLoanProductsDestroy(LoanProduct $loan_product): RedirectResponse
    {
        $inUseOnLoans = \App\Models\LoanBookLoan::query()->where('product_name', $loan_product->name)->exists();
        $inUseOnApplications = \App\Models\LoanBookApplication::query()->where('product_name', $loan_product->name)->exists();
        if ($inUseOnLoans || $inUseOnApplications) {
            $loan_product->update(['is_active' => false]);

            return redirect()->route('loan.system.setup.loan_products')
                ->with('status', 'Product is in use; it was deactivated instead of deleted.');
        }

        $loan_product->delete();

        return redirect()->route('loan.system.setup.loan_products')->with('status', 'Loan product removed.');
    }

    public function setupDepartments(): View
    {
        abort_unless(Schema::hasTable('loan_departments'), 404, 'Loan departments table not found. Run migrations.');

        return view('loan.system.setup.departments', [
            'title' => 'Departments',
            'subtitle' => 'Master list used on employee forms and HR records.',
            'departments' => LoanDepartment::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function setupDepartmentsStore(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_departments'), 404, 'Loan departments table not found. Run migrations.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $name = trim((string) $validated['name']);
        $code = $this->resolveDepartmentCode($validated['code'] ?? null, $name);

        LoanDepartment::query()->updateOrCreate(
            ['name' => $name],
            [
                'code' => $code,
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'is_active' => ($validated['is_active'] ?? '1') === '1',
            ]
        );

        return redirect()->route('loan.system.setup.departments')->with('status', 'Department saved.');
    }

    public function setupDepartmentsUpdate(Request $request, LoanDepartment $loan_department): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_departments'), 404, 'Loan departments table not found. Run migrations.');

        $validated = $request->validate([
            'is_active' => ['required', 'in:0,1'],
        ]);

        $loan_department->update(['is_active' => $validated['is_active'] === '1']);

        return redirect()->route('loan.system.setup.departments')->with('status', 'Department status updated.');
    }

    public function setupDepartmentsDestroy(LoanDepartment $loan_department): RedirectResponse
    {
        $inUse = \App\Models\Employee::query()->where('department', $loan_department->name)->exists();
        if ($inUse) {
            $loan_department->update(['is_active' => false]);

            return redirect()->route('loan.system.setup.departments')
                ->with('status', 'Department is in use; it was deactivated instead of deleted.');
        }

        $loan_department->delete();

        return redirect()->route('loan.system.setup.departments')->with('status', 'Department removed.');
    }

    public function setupDepartmentsSync(): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_departments'), 404, 'Loan departments table not found. Run migrations.');

        $names = \App\Models\Employee::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        $added = 0;
        foreach ($names as $departmentName) {
            $name = trim((string) $departmentName);
            if ($name === '') {
                continue;
            }

            $model = LoanDepartment::query()->firstOrCreate(
                ['name' => $name],
                [
                    'code' => $this->resolveDepartmentCode(null, $name),
                    'is_active' => true,
                ]
            );

            if ($model->wasRecentlyCreated) {
                $added++;
                continue;
            }

            $updates = [];
            if (! $model->is_active) {
                $updates['is_active'] = true;
            }
            if (! filled($model->code)) {
                $updates['code'] = $this->resolveDepartmentCode(null, $name);
            }
            if ($updates !== []) {
                $model->update($updates);
            }
        }

        return redirect()
            ->route('loan.system.setup.departments')
            ->with('status', "Sync complete. {$added} department(s) added from employee records.");
    }

    private function resolveDepartmentCode(?string $inputCode, string $name): string
    {
        $candidate = strtoupper(trim((string) $inputCode));
        $candidate = preg_replace('/[^A-Z0-9]+/', '_', $candidate ?? '') ?? '';
        $candidate = trim($candidate, '_');

        if ($candidate === '') {
            $words = preg_split('/\s+/', strtoupper($name)) ?: [];
            $letters = '';
            foreach ($words as $word) {
                $ch = substr(preg_replace('/[^A-Z0-9]/', '', $word) ?? '', 0, 1);
                if ($ch !== '') {
                    $letters .= $ch;
                }
            }
            if ($letters === '') {
                $letters = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?? 'DEPT', 0, 4);
            }
            $candidate = substr($letters, 0, 12);
        }

        if ($candidate === '') {
            $candidate = 'DEPT';
        }

        $base = substr($candidate, 0, 32);
        $final = $base;
        $i = 1;
        while (LoanDepartment::query()->where('code', $final)->exists()) {
            $suffix = '_'.$i;
            $final = substr($base, 0, max(1, 40 - strlen($suffix))).$suffix;
            $i++;
        }

        return $final;
    }

    public function setupJobTitles(): View
    {
        abort_unless(Schema::hasTable('loan_job_titles'), 404, 'Loan job titles table not found. Run migrations.');

        return view('loan.system.setup.job_titles', [
            'title' => 'Job Titles',
            'subtitle' => 'Master list used on employee forms and HR records.',
            'jobTitles' => LoanJobTitle::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function setupJobTitlesStore(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_job_titles'), 404, 'Loan job titles table not found. Run migrations.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        LoanJobTitle::query()->updateOrCreate(
            ['name' => trim((string) $validated['name'])],
            [
                'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'is_active' => ($validated['is_active'] ?? '1') === '1',
            ]
        );

        return redirect()->route('loan.system.setup.job_titles')->with('status', 'Job title saved.');
    }

    public function setupJobTitlesUpdate(Request $request, LoanJobTitle $loan_job_title): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_job_titles'), 404, 'Loan job titles table not found. Run migrations.');

        $validated = $request->validate([
            'is_active' => ['required', 'in:0,1'],
        ]);

        $loan_job_title->update(['is_active' => $validated['is_active'] === '1']);

        return redirect()->route('loan.system.setup.job_titles')->with('status', 'Job title status updated.');
    }

    public function setupJobTitlesDestroy(LoanJobTitle $loan_job_title): RedirectResponse
    {
        $inUse = \App\Models\Employee::query()->where('job_title', $loan_job_title->name)->exists();
        if ($inUse) {
            $loan_job_title->update(['is_active' => false]);

            return redirect()->route('loan.system.setup.job_titles')
                ->with('status', 'Job title is in use; it was deactivated instead of deleted.');
        }

        $loan_job_title->delete();

        return redirect()->route('loan.system.setup.job_titles')->with('status', 'Job title removed.');
    }

    public function setupJobTitlesSync(): RedirectResponse
    {
        abort_unless(Schema::hasTable('loan_job_titles'), 404, 'Loan job titles table not found. Run migrations.');

        $titles = \App\Models\Employee::query()
            ->whereNotNull('job_title')
            ->where('job_title', '!=', '')
            ->distinct()
            ->orderBy('job_title')
            ->pluck('job_title');

        $added = 0;
        foreach ($titles as $title) {
            $name = trim((string) $title);
            if ($name === '') {
                continue;
            }

            $model = LoanJobTitle::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );

            if ($model->wasRecentlyCreated) {
                $added++;
            } elseif (! $model->is_active) {
                $model->update(['is_active' => true]);
            }
        }

        return redirect()
            ->route('loan.system.setup.job_titles')
            ->with('status', "Sync complete. {$added} job title(s) added from employee records.");
    }

    public function setupAccessRoles(): View
    {
        $rbacReady = Schema::hasTable('loan_roles') && Schema::hasTable('loan_user_role');
        app(LoanSecurityPolicyService::class)->ensureDefaults();

        return view('loan.system.setup.access_roles', [
            'title' => 'Loan roles & permissions',
            'subtitle' => 'Create custom access roles, choose permissions, and assign to users.',
            'rbacReady' => $rbacReady,
            'roles' => $rbacReady ? LoanRole::query()->orderByDesc('is_active')->orderBy('name')->get() : collect(),
            'users' => $rbacReady ? User::query()->orderBy('name')->get(['id', 'name', 'email']) : collect(),
            'permissionCatalog' => $this->loanPermissionCatalog(),
            'defaultPermissionsByBaseRole' => $this->defaultPermissionsByBaseRole(),
            'temporaryAccessRequests' => Schema::hasTable('loan_temporary_access_requests')
                ? LoanTemporaryAccessRequest::query()
                    ->with(['requester:id,name,email', 'approver:id,name,email'])
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                : collect(),
            'securityPolicySettings' => [
                'device_governance_enabled' => LoanSystemSetting::getValue('loan_security_device_governance_enabled', '0'),
                'role_login_windows_enabled' => LoanSystemSetting::getValue('loan_security_role_login_windows_enabled', '0'),
                'ip_restrictions_enabled' => LoanSystemSetting::getValue('loan_security_ip_restrictions_enabled', '0'),
                'ip_allowlist_json' => LoanSystemSetting::getValue('loan_security_ip_allowlist_json', '[]'),
                'role_ip_overrides_json' => LoanSystemSetting::getValue('loan_security_role_ip_overrides_json', '{}'),
                'role_login_windows_json' => LoanSystemSetting::getValue('loan_security_role_login_windows_json', '{}'),
            ],
        ]);
    }

    public function setupAccessSecurityPoliciesUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_governance_enabled' => ['nullable', 'in:0,1'],
            'role_login_windows_enabled' => ['nullable', 'in:0,1'],
            'ip_restrictions_enabled' => ['nullable', 'in:0,1'],
            'ip_allowlist_json' => ['nullable', 'string', 'max:10000'],
            'role_ip_overrides_json' => ['nullable', 'string', 'max:10000'],
            'role_login_windows_json' => ['nullable', 'string', 'max:20000'],
        ]);

        $service = app(LoanSecurityPolicyService::class);
        $service->ensureDefaults();

        LoanSystemSetting::setValue('loan_security_device_governance_enabled', (string) ($data['device_governance_enabled'] ?? '0'), 'Device governance toggle', 'security');
        LoanSystemSetting::setValue('loan_security_role_login_windows_enabled', (string) ($data['role_login_windows_enabled'] ?? '0'), 'Role login window toggle', 'security');
        LoanSystemSetting::setValue('loan_security_ip_restrictions_enabled', (string) ($data['ip_restrictions_enabled'] ?? '0'), 'IP restriction toggle', 'security');

        foreach (['ip_allowlist_json', 'role_ip_overrides_json', 'role_login_windows_json'] as $jsonField) {
            if (! isset($data[$jsonField])) {
                continue;
            }
            $raw = trim((string) $data[$jsonField]);
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    return redirect()->route('loan.system.setup.access_roles')
                        ->with('error', strtoupper(str_replace('_', ' ', $jsonField)).' must be valid JSON.');
                }
            }
            $key = 'loan_security_'.$jsonField;
            LoanSystemSetting::setValue($key, $raw === '' ? ($jsonField === 'ip_allowlist_json' ? '[]' : '{}') : $raw, $key, 'security');
        }

        $this->logSecurityAudit($request, 'Updated security policy toggles', [
            'device_governance_enabled' => (string) ($data['device_governance_enabled'] ?? '0'),
            'role_login_windows_enabled' => (string) ($data['role_login_windows_enabled'] ?? '0'),
            'ip_restrictions_enabled' => (string) ($data['ip_restrictions_enabled'] ?? '0'),
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Security policies updated.');
    }

    public function setupTemporaryAccessRequestStore(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('loan_temporary_access_requests')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Temporary access table is missing. Run migrations first.');
        }

        $validated = $request->validate([
            'permission_key' => ['required', 'string', 'in:'.implode(',', array_keys($this->loanPermissionCatalog()))],
            'scope' => ['nullable', 'string', 'max:500'],
            'amount_limit' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        LoanTemporaryAccessRequest::query()->create([
            'requester_user_id' => (int) $request->user()->id,
            'permission_key' => $validated['permission_key'],
            'scope' => filled($validated['scope'] ?? null) ? trim((string) $validated['scope']) : null,
            'amount_limit' => isset($validated['amount_limit']) ? (float) $validated['amount_limit'] : null,
            'reason' => filled($validated['reason'] ?? null) ? trim((string) $validated['reason']) : null,
            'status' => LoanTemporaryAccessRequest::STATUS_PENDING,
            'expires_at' => isset($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : now()->addHours(8),
        ]);

        $this->logSecurityAudit($request, 'Submitted temporary access request', [
            'requester_user_id' => (int) $request->user()->id,
            'amount_limit' => isset($validated['amount_limit']) ? (float) $validated['amount_limit'] : null,
            'permission_key' => $validated['permission_key'],
            'scope' => filled($validated['scope'] ?? null) ? trim((string) $validated['scope']) : null,
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Temporary access request submitted.');
    }

    public function setupTemporaryAccessRequestDecision(Request $request, LoanTemporaryAccessRequest $loan_temporary_access_request): RedirectResponse
    {
        if (! Schema::hasTable('loan_temporary_access_requests')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Temporary access table is missing. Run migrations first.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $policy = app(LoanSecurityPolicyService::class);
        if (! $policy->canApproveTemporaryAccess($request->user(), $loan_temporary_access_request)) {
            return redirect()->route('loan.system.setup.access_roles')->with('error', 'Your role cannot approve this request threshold.');
        }

        $loan_temporary_access_request->update([
            'status' => $validated['decision'] === 'approve'
                ? LoanTemporaryAccessRequest::STATUS_APPROVED
                : LoanTemporaryAccessRequest::STATUS_REJECTED,
            'approver_user_id' => (int) $request->user()->id,
            'approved_at' => $validated['decision'] === 'approve' ? now() : null,
            'decision_note' => filled($validated['decision_note'] ?? null) ? trim((string) $validated['decision_note']) : null,
            'expires_at' => isset($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : $loan_temporary_access_request->expires_at,
        ]);

        $this->logSecurityAudit($request, 'Temporary access request '.$loan_temporary_access_request->status, [
            'request_id' => $loan_temporary_access_request->id,
            'requester_user_id' => $loan_temporary_access_request->requester_user_id,
            'permission_key' => $loan_temporary_access_request->permission_key,
            'decision_note' => $validated['decision_note'] ?? null,
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Temporary access request '.$loan_temporary_access_request->status.'.');
    }

    public function setupDeviceUnbind(Request $request, User $user): RedirectResponse
    {
        $count = app(LoanSecurityPolicyService::class)->unbindUserDevices($user);

        $this->logSecurityAudit($request, 'Device unbind executed', [
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'removed_devices' => $count,
        ]);

        return redirect()->route('loan.system.setup.access_roles')
            ->with('status', "Device unbind completed. Removed {$count} trusted device record(s) for {$user->name}.");
    }

    /**
     * @param  array<string, mixed>  $newValue
     */
    private function logSecurityAudit(Request $request, string $activity, array $newValue = []): void
    {
        if (! Schema::hasTable('loan_access_logs')) {
            return;
        }

        try {
            LoanAccessLog::query()->create([
                'user_id' => $request->user()?->id,
                'session_id' => $request->session()->getId(),
                'device_fingerprint' => substr(hash('sha256', (string) $request->userAgent()), 0, 40),
                'route_name' => (string) optional($request->route())->getName(),
                'method' => (string) $request->method(),
                'event_category' => 'security',
                'action_type' => 'update',
                'path' => '/'.ltrim((string) $request->path(), '/'),
                'activity' => $activity,
                'result' => 'success',
                'risk_score' => 60,
                'risk_level' => 'medium',
                'risk_reason' => 'RBAC governance action',
                'requires_reason' => false,
                'reason_text' => null,
                'old_value' => null,
                'new_value' => $newValue,
                'audit_token' => strtoupper('AUDSEC-'.dechex((int) now()->timestamp).'-'.Str::upper(Str::random(4))),
                'checksum' => hash('sha256', $activity.'|'.json_encode($newValue).'|'.now()->toIso8601String()),
                'previous_hash' => null,
                'mfa_verified' => null,
                'ip_address' => (string) $request->ip(),
                'country_code' => null,
                'geo_label' => null,
                'is_foreign_ip' => null,
                'is_privileged' => true,
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never fail security workflow due to audit logging issues.
        }
    }

    public function setupAccessRolesStore(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('loan_roles')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Loan roles tables are missing. Run migrations first.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_role' => ['required', 'in:admin,manager,accountant,officer,applicant,user'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys($this->loanPermissionCatalog()))],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $name = trim((string) $validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'role-'.now()->format('YmdHis');
        }
        $baseSlug = $slug;
        $i = 1;
        while (LoanRole::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($baseSlug, 100, '').'-'.$i;
            $i++;
        }

        $permissions = $request->has('permissions')
            ? array_values(array_unique($validated['permissions'] ?? []))
            : ($this->defaultPermissionsByBaseRole()[$validated['base_role']] ?? []);

        LoanRole::query()->create([
            'name' => $name,
            'slug' => $slug,
            'base_role' => $validated['base_role'],
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'permissions' => $permissions,
            'is_active' => ($validated['is_active'] ?? '1') === '1',
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Loan access role created.');
    }

    public function setupAccessRolesClone(Request $request, LoanRole $loan_role): RedirectResponse
    {
        if (! Schema::hasTable('loan_roles')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Loan roles tables are missing. Run migrations first.');
        }

        $baseName = trim((string) $loan_role->name).' (Copy)';
        $name = $baseName;
        $suffix = 2;
        while (LoanRole::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            $name = $baseName.' '.$suffix;
            $suffix++;
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'role-copy-'.now()->format('YmdHis');
        }
        $baseSlug = $slug;
        $i = 1;
        while (LoanRole::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($baseSlug, 100, '').'-'.$i;
            $i++;
        }

        LoanRole::query()->create([
            'name' => $name,
            'slug' => $slug,
            'base_role' => (string) $loan_role->base_role,
            'description' => filled($loan_role->description ?? null)
                ? trim((string) $loan_role->description).' (Cloned)'
                : 'Cloned from '.$loan_role->name,
            'permissions' => is_array($loan_role->permissions) ? $loan_role->permissions : [],
            'is_active' => (bool) $loan_role->is_active,
        ]);

        $this->logSecurityAudit($request, 'Cloned loan role', [
            'source_role_id' => $loan_role->id,
            'source_role_name' => $loan_role->name,
            'cloned_name' => $name,
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Role cloned successfully.');
    }

    public function setupAccessRolesUpdate(Request $request, LoanRole $loan_role): RedirectResponse
    {
        if (! Schema::hasTable('loan_roles')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Loan roles table is missing. Run migrations first.');
        }

        $catalog = array_keys($this->loanPermissionCatalog());
        $validated = $request->validate([
            'base_role' => ['required', 'in:admin,manager,accountant,officer,applicant,user'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', $catalog)],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $permissions = $request->has('permissions')
            ? array_values(array_unique($validated['permissions'] ?? []))
            : ($this->defaultPermissionsByBaseRole()[$validated['base_role']] ?? []);

        $loan_role->update([
            'base_role' => $validated['base_role'],
            'permissions' => $permissions,
            'is_active' => ($validated['is_active'] ?? '1') === '1',
        ]);

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Role permissions updated.');
    }

    public function setupAccessRolesAssign(Request $request, LoanRole $loan_role): RedirectResponse
    {
        if (! Schema::hasTable('loan_user_role')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Loan role assignment table is missing. Run migrations first.');
        }

        $validated = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));

        \Illuminate\Support\Facades\DB::table('loan_user_role')
            ->where('loan_role_id', $loan_role->id)
            ->whereNotIn('user_id', $ids)
            ->delete();

        foreach ($ids as $userId) {
            \Illuminate\Support\Facades\DB::table('loan_user_role')
                ->where('user_id', $userId)
                ->delete();

            \Illuminate\Support\Facades\DB::table('loan_user_role')->insert([
                'loan_role_id' => $loan_role->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Role assignments updated.');
    }

    public function setupAccessRolesDestroy(LoanRole $loan_role): RedirectResponse
    {
        $loan_role->users()->detach();
        $loan_role->delete();

        return redirect()->route('loan.system.setup.access_roles')->with('status', 'Loan role removed.');
    }

    public function setupAccessRolesSync(): RedirectResponse
    {
        if (! Schema::hasTable('loan_roles') || ! Schema::hasTable('loan_user_role')) {
            return redirect()->route('loan.system.setup.access_roles')
                ->with('error', 'Loan roles tables are missing. Run migrations first.');
        }

        $legacyRoles = User::query()
            ->whereNotNull('loan_role')
            ->where('loan_role', '!=', '')
            ->distinct()
            ->pluck('loan_role')
            ->map(fn ($r) => strtolower(trim((string) $r)))
            ->filter(fn ($r) => in_array($r, ['admin', 'manager', 'accountant', 'officer', 'applicant', 'user'], true))
            ->values();

        $created = 0;
        $assigned = 0;
        foreach ($legacyRoles as $baseRole) {
            $name = match ($baseRole) {
                'admin' => 'Loan Administrators',
                'manager' => 'Loan Managers',
                'accountant' => 'Loan Accountants',
                'officer' => 'Loan Officers',
                'applicant' => 'Loan Applicants',
                default => 'Loan Users',
            };
            $slug = 'legacy-'.$baseRole;

            $role = LoanRole::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'base_role' => $baseRole,
                    'description' => 'Imported from existing users.loan_role values.',
                    'permissions' => $this->defaultPermissionsByBaseRole()[$baseRole] ?? [],
                    'is_active' => true,
                ]
            );

            if ($role->wasRecentlyCreated) {
                $created++;
            }

            $userIds = User::query()
                ->whereRaw('LOWER(loan_role) = ?', [$baseRole])
                ->pluck('id')
                ->all();

            foreach ($userIds as $userId) {
                \Illuminate\Support\Facades\DB::table('loan_user_role')->where('user_id', $userId)->delete();
                \Illuminate\Support\Facades\DB::table('loan_user_role')->insert([
                    'loan_role_id' => $role->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $assigned++;
            }
        }

        return redirect()->route('loan.system.setup.access_roles')
            ->with('status', "Sync complete. {$created} role(s) prepared, {$assigned} user assignment(s) imported.");
    }

    /**
     * @return array<string, string>
     */
    private function loanPermissionCatalog(): array
    {
        return [
            'dashboard.view' => 'Dashboard · View',
            'employees.view' => 'Employees · View',
            'branches.view' => 'Branches & Regions · View',
            'analytics.view' => 'Business Analytics · View',
            'bulksms.view' => 'Bulk SMS · View',
            'my_account.view' => 'My Account · View',
            'system.help.view' => 'System & Help · View',

            'clients.view' => 'Clients · View',
            'clients.create' => 'Clients · Create',
            'clients.update' => 'Clients · Update',
            'clients.delete' => 'Clients · Delete',
            'clients.approve' => 'Clients · Approve',
            'clients.export' => 'Clients · Export',
            'clients.reverse' => 'Clients · Reverse',
            'clients.configure' => 'Clients · Configure',

            'loan_applications.view' => 'Loan Applications · View',
            'loan_applications.create' => 'Loan Applications · Create',
            'loan_applications.update' => 'Loan Applications · Update',
            'loan_applications.delete' => 'Loan Applications · Delete',
            'loan_applications.approve' => 'Loan Applications · Approve',
            'loan_applications.export' => 'Loan Applications · Export',
            'loan_applications.reverse' => 'Loan Applications · Reverse',
            'loan_applications.configure' => 'Loan Applications · Configure',

            'loans.view' => 'Loans · View',
            'loans.create' => 'Loans · Create',
            'loans.update' => 'Loans · Update',
            'loans.delete' => 'Loans · Delete',
            'loans.approve' => 'Loans · Approve',
            'loans.export' => 'Loans · Export',
            'loans.reverse' => 'Loans · Reverse',
            'loans.configure' => 'Loans · Configure',

            'disbursements.view' => 'Disbursements · View',
            'disbursements.create' => 'Disbursements · Create',
            'disbursements.update' => 'Disbursements · Update',
            'disbursements.delete' => 'Disbursements · Delete',
            'disbursements.approve' => 'Disbursements · Approve',
            'disbursements.export' => 'Disbursements · Export',
            'disbursements.reverse' => 'Disbursements · Reverse',
            'disbursements.configure' => 'Disbursements · Configure',

            'collections.view' => 'Collections · View',
            'collections.create' => 'Collections · Create',
            'collections.update' => 'Collections · Update',
            'collections.delete' => 'Collections · Delete',
            'collections.approve' => 'Collections · Approve',
            'collections.export' => 'Collections · Export',
            'collections.reverse' => 'Collections · Reverse',
            'collections.configure' => 'Collections · Configure',

            'payments.view' => 'Payments · View',
            'payments.create' => 'Payments · Create',
            'payments.update' => 'Payments · Update',
            'payments.delete' => 'Payments · Delete',
            'payments.approve' => 'Payments · Approve',
            'payments.export' => 'Payments · Export',
            'payments.reverse' => 'Payments · Reverse',
            'payments.configure' => 'Payments · Configure',

            'wallets.view' => 'Wallets · View',
            'wallets.create' => 'Wallets · Create',
            'wallets.update' => 'Wallets · Update',
            'wallets.delete' => 'Wallets · Delete',
            'wallets.approve' => 'Wallets · Approve',
            'wallets.export' => 'Wallets · Export',
            'wallets.reverse' => 'Wallets · Reverse',
            'wallets.configure' => 'Wallets · Configure',

            'accounting.view' => 'Accounting · View',
            'accounting.create' => 'Accounting · Create',
            'accounting.update' => 'Accounting · Update',
            'accounting.delete' => 'Accounting · Delete',
            'accounting.approve' => 'Accounting · Approve',
            'accounting.export' => 'Accounting · Export',
            'accounting.reverse' => 'Accounting · Reverse',
            'accounting.configure' => 'Accounting · Configure',

            'journals.view' => 'Journals · View',
            'journals.create' => 'Journals · Create',
            'journals.update' => 'Journals · Update',
            'journals.delete' => 'Journals · Delete',
            'journals.approve' => 'Journals · Approve',
            'journals.export' => 'Journals · Export',
            'journals.reverse' => 'Journals · Reverse',
            'journals.configure' => 'Journals · Configure',

            'chart_of_accounts.view' => 'Chart of Accounts · View',
            'chart_of_accounts.create' => 'Chart of Accounts · Create',
            'chart_of_accounts.update' => 'Chart of Accounts · Update',
            'chart_of_accounts.delete' => 'Chart of Accounts · Delete',
            'chart_of_accounts.approve' => 'Chart of Accounts · Approve',
            'chart_of_accounts.export' => 'Chart of Accounts · Export',
            'chart_of_accounts.reverse' => 'Chart of Accounts · Reverse',
            'chart_of_accounts.configure' => 'Chart of Accounts · Configure',

            'automated_cash_mappings.view' => 'Automated Cash Mappings · View',
            'automated_cash_mappings.create' => 'Automated Cash Mappings · Create',
            'automated_cash_mappings.update' => 'Automated Cash Mappings · Update',
            'automated_cash_mappings.delete' => 'Automated Cash Mappings · Delete',
            'automated_cash_mappings.approve' => 'Automated Cash Mappings · Approve',
            'automated_cash_mappings.export' => 'Automated Cash Mappings · Export',
            'automated_cash_mappings.reverse' => 'Automated Cash Mappings · Reverse',
            'automated_cash_mappings.configure' => 'Automated Cash Mappings · Configure',

            'reports.view' => 'Reports · View',
            'reports.create' => 'Reports · Create',
            'reports.update' => 'Reports · Update',
            'reports.delete' => 'Reports · Delete',
            'reports.approve' => 'Reports · Approve',
            'reports.export' => 'Reports · Export',
            'reports.reverse' => 'Reports · Reverse',
            'reports.configure' => 'Reports · Configure',

            'system_setup.view' => 'System Setup · View',
            'system_setup.create' => 'System Setup · Create',
            'system_setup.update' => 'System Setup · Update',
            'system_setup.delete' => 'System Setup · Delete',
            'system_setup.approve' => 'System Setup · Approve',
            'system_setup.export' => 'System Setup · Export',
            'system_setup.reverse' => 'System Setup · Reverse',
            'system_setup.configure' => 'System Setup · Configure',

            'audit_logs.view' => 'Audit Logs · View',
            'audit_logs.create' => 'Audit Logs · Create',
            'audit_logs.update' => 'Audit Logs · Update',
            'audit_logs.delete' => 'Audit Logs · Delete',
            'audit_logs.approve' => 'Audit Logs · Approve',
            'audit_logs.export' => 'Audit Logs · Export',
            'audit_logs.reverse' => 'Audit Logs · Reverse',
            'audit_logs.configure' => 'Audit Logs · Configure',
            'access_roles.view' => 'User Roles & Access Control · View',
            'access_roles.configure' => 'User Roles & Access Control · Configure',
            'access_roles.request' => 'User Roles & Access Control · Request Temporary Access',
            'access_roles.approve' => 'User Roles & Access Control · Approve Temporary Access',
            'device_governance.unbind' => 'Device Governance · Unbind User Device',
            'device_governance.master_key' => 'Device Governance · Master Key Override',

            // Legacy coarse keys retained for backward compatibility.
            'loanbook.view' => 'LoanBook',
            'financial.view' => 'Financial · View (Legacy)',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultPermissionsByBaseRole(): array
    {
        return [
            'admin' => array_keys($this->loanPermissionCatalog()),
            'manager' => array_keys($this->loanPermissionCatalog()),
            'accountant' => [
                'dashboard.view', 'clients.view', 'payments.view', 'accounting.view', 'financial.view', 'reports.view',
                'journals.view', 'journals.create', 'journals.update', 'journals.approve',
                'chart_of_accounts.view', 'audit_logs.view', 'access_roles.request', 'my_account.view', 'system.help.view',
            ],
            'officer' => [
                'dashboard.view', 'employees.view', 'clients.view', 'clients.create', 'clients.update',
                'loanbook.view', 'loan_applications.view', 'loan_applications.create', 'loan_applications.update',
                'loans.view', 'loans.create', 'loans.update',
                'disbursements.view', 'collections.view', 'collections.create', 'collections.update',
                'payments.view', 'bulksms.view', 'branches.view', 'reports.view', 'access_roles.request', 'my_account.view', 'system.help.view',
            ],
            'applicant' => ['dashboard.view', 'my_account.view', 'system.help.view'],
            'user' => [
                'dashboard.view', 'clients.view', 'loanbook.view',
                'loan_applications.view', 'loan_applications.create', 'loans.view',
                'payments.view', 'bulksms.view', 'my_account.view', 'system.help.view',
            ],
        ];
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
        if ($keys === self::COMPANY_SETTING_KEYS) {
            $rules['logo_file'] = ['nullable', 'image', 'max:3072'];
            $rules['favicon_file'] = ['nullable', 'file', 'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml', 'max:2048'];
            $rules['remove_logo'] = ['nullable', 'in:0,1'];
            $rules['remove_favicon'] = ['nullable', 'in:0,1'];
        }
        $validated = $request->validate($rules);

        foreach ($keys as $key) {
            $val = $validated['settings'][$key] ?? null;
            LoanSystemSetting::query()->where('key', $key)->update(['value' => $val]);
        }

        if ($keys === self::COMPANY_SETTING_KEYS) {
            if (($validated['remove_logo'] ?? '0') === '1') {
                LoanSystemSetting::setValue('logo_url', '', 'Logo URL (or path)', 'company');
            } elseif ($request->hasFile('logo_file')) {
                $path = $request->file('logo_file')->store('loan/branding', 'public');
                LoanSystemSetting::setValue('logo_url', Storage::url($path), 'Logo URL (or path)', 'company');
            }

            if (($validated['remove_favicon'] ?? '0') === '1') {
                LoanSystemSetting::setValue('favicon_url', '', 'Favicon URL (or path)', 'company');
            } elseif ($request->hasFile('favicon_file')) {
                $path = $request->file('favicon_file')->store('loan/branding', 'public');
                LoanSystemSetting::setValue('favicon_url', Storage::url($path), 'Favicon URL (or path)', 'company');
            }
        }

        return redirect()->route($redirectRouteName)->with('status', 'Settings saved.');
    }

    public function accessLogsIndex(Request $request)
    {
        $logsQuery = LoanAccessLog::query()->with(['user', 'concerns.messages.user'])->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $logsQuery->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $logsQuery->where('method', strtoupper($request->string('method')));
        }
        if ($request->filled('result')) {
            $logsQuery->where('result', $request->string('result'));
        }
        if ($request->filled('risk_level')) {
            $logsQuery->where('risk_level', $request->string('risk_level'));
        }
        if ($request->filled('route_name')) {
            $logsQuery->where('route_name', $request->string('route_name'));
        }
        if ($request->filled('ip_address')) {
            $logsQuery->where('ip_address', $request->string('ip_address'));
        }
        if ($request->filled('from_date')) {
            $logsQuery->whereDate('created_at', '>=', (string) $request->string('from_date'));
        }
        if ($request->filled('to_date')) {
            $logsQuery->whereDate('created_at', '<=', (string) $request->string('to_date'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $booleanMode = $request->boolean('boolean_search');
            $advancedBoolean = $request->boolean('advanced_boolean');
            $logsQuery->where(function ($w) use ($term, $booleanMode, $advancedBoolean, $request) {
                $w->where('path', 'like', $term)
                    ->orWhere('route_name', 'like', $term)
                    ->orWhere('activity', 'like', $term)
                    ->orWhere('risk_reason', 'like', $term)
                    ->orWhere('audit_token', 'like', $term)
                    ->orWhere('checksum', 'like', $term);

                if ($booleanMode) {
                    $w->orWhere('method', strtoupper((string) $request->string('q')));
                }
                if ($advancedBoolean) {
                    $w->orWhere('event_category', 'like', $term)
                        ->orWhere('action_type', 'like', $term);
                }
            });
        }
        if ($request->filled('activity_type')) {
            $activityType = strtolower(trim((string) $request->string('activity_type')));
            $logsQuery->where(function ($w) use ($activityType) {
                if ($activityType === 'failed') {
                    $w->where('activity', 'like', '%failed%');
                    return;
                }

                $w->where('activity', 'like', ucfirst($activityType).'%');
            });
        }

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $logsQuery)->limit(10000)->get();

            return TabularExport::stream(
                'loan-access-logs-'.now()->format('Ymd_His'),
                ['When', 'User', 'Method', 'Activity', 'Path', 'Route', 'IP', 'Risk', 'Result', 'Audit token'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            optional($row->created_at)->format('Y-m-d H:i:s') ?? '',
                            (string) ($row->user?->name ?? ''),
                            (string) ($row->method ?? ''),
                            (string) ($row->activity ?? ''),
                            (string) ($row->path ?? ''),
                            (string) ($row->route_name ?? ''),
                            (string) ($row->ip_address ?? ''),
                            (string) (($row->risk_level ? strtoupper((string) $row->risk_level).' ' : '').($row->risk_score ?? '')),
                            (string) ($row->result ?? ''),
                            (string) ($row->audit_token ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $perPage = min(200, max(20, (int) $request->query('per_page', 40)));
        $logs = $logsQuery->paginate($perPage)->withQueryString();
        /** @var LengthAwarePaginator $logs */
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $routes = LoanAccessLog::query()
            ->whereNotNull('route_name')
            ->where('route_name', '!=', '')
            ->distinct()
            ->orderBy('route_name')
            ->pluck('route_name');
        $ips = LoanAccessLog::query()
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->distinct()
            ->orderBy('ip_address')
            ->pluck('ip_address');

        $today = Carbon::today();
        $todayQuery = LoanAccessLog::query()->whereDate('created_at', $today);
        $last7DaysStart = now()->subDays(6)->startOfDay();
        $last7DaysQuery = LoanAccessLog::query()->where('created_at', '>=', $last7DaysStart);
        $recent = (clone $todayQuery)
            ->select([
                'id',
                'user_id',
                'activity',
                'path',
                'route_name',
                'ip_address',
                'created_at',
                'risk_score',
                'risk_level',
                'result',
                'is_foreign_ip',
                'is_privileged',
                'action_type',
                'checksum',
            ])
            ->get();

        $riskDistribution = [
            'critical' => $recent->where('risk_score', '>=', 90)->count(),
            'high' => $recent->filter(fn ($r) => (int) $r->risk_score >= 70 && (int) $r->risk_score < 90)->count(),
            'medium' => $recent->filter(fn ($r) => (int) $r->risk_score >= 40 && (int) $r->risk_score < 70)->count(),
            'low' => $recent->filter(fn ($r) => (int) $r->risk_score < 40)->count(),
        ];
        $riskDistribution['total'] = array_sum($riskDistribution);

        $todayCount = $recent->count();
        $eventsPerMinute = (clone $todayQuery)->where('created_at', '>=', now()->subMinute())->count();
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
            ->count();
        $activeUsersOnline = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
        $anomaliesDetected = $recent->whereIn('risk_level', ['high', 'critical'])->count();
        $interceptedAttempts = $recent->whereIn('result', ['blocked', 'failed'])->count();
        $foreignIpBlocked = $recent->where('is_foreign_ip', true)->whereIn('result', ['blocked', 'failed'])->count();
        $gatedCriticalEvents = $recent->where('is_privileged', true)->where('risk_score', '>=', 70)->count();
        $coaDnaModificationCount = (clone $todayQuery)->where('path', 'like', '%chart-of-accounts%')->where('method', '!=', 'GET')->count();
        $floorOverrideCount = (clone $todayQuery)->where(function ($w) {
            $w->where('activity', 'like', '%floor override%')
                ->orWhere('route_name', 'like', '%floor%');
        })->count();
        $manualReversalCount = (clone $todayQuery)->where(function ($w) {
            $w->where('activity', 'like', '%reversal%')
                ->orWhere('route_name', 'like', '%reverse%');
        })->count();
        $importCount = (clone $todayQuery)->where('action_type', 'import')->count();
        $exportCount = (clone $todayQuery)->where('action_type', 'export')->count();
        $downloadCount = (clone $todayQuery)->where('action_type', 'download')->count();
        $failedDistinctPages = (clone $todayQuery)
            ->whereIn('result', ['blocked', 'failed'])
            ->whereNotNull('path')
            ->distinct('path')
            ->count('path');
        $failedDistinctPages7d = (clone $last7DaysQuery)
            ->whereIn('result', ['blocked', 'failed'])
            ->whereNotNull('path')
            ->distinct('path')
            ->count('path');

        $importCount7d = (clone $last7DaysQuery)->where('action_type', 'import')->count();
        $exportCount7d = (clone $last7DaysQuery)->where('action_type', 'export')->count();
        $downloadCount7d = (clone $last7DaysQuery)->where('action_type', 'download')->count();

        $topVisitedPaths = (clone $last7DaysQuery)
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->selectRaw('path, COUNT(*) AS hits')
            ->groupBy('path')
            ->orderByDesc('hits')
            ->limit(5)
            ->get();

        $mostActiveHour = (clone $last7DaysQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%H:00') AS hour_slot, COUNT(*) AS hits")
            ->groupBy('hour_slot')
            ->orderByDesc('hits')
            ->first();
        $checkerRequired = DB::table('accounting_journal_approval_queues')->where('status', 'pending')->count() > 0;

        $integrityScore = $todayCount > 0
            ? (int) round(($recent->whereNotNull('checksum')->count() / $todayCount) * 100)
            : 100;
        $checksumVerified = $recent->whereNotNull('checksum')->count();
        $shadowLedgerActive = LoanSystemSetting::getValue('loan_accounting_event_mappings_json') !== null;

        $topRiskyUsers = LoanAccessLog::query()
            ->with('user:id,name')
            ->whereDate('created_at', $today)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, SUM(COALESCE(risk_score, 0)) AS risk_points')
            ->groupBy('user_id')
            ->orderByDesc('risk_points')
            ->limit(4)
            ->get();

        return view('loan.system.access_logs.index', [
            'title' => 'Access logs',
            'subtitle' => 'Loan portal page views and actions (from sign-in onward).',
            'logs' => $logs,
            'users' => $users,
            'routes' => $routes,
            'ips' => $ips,
            'perPage' => $perPage,
            'activityTypes' => ['viewed', 'submitted', 'updated', 'deleted', 'failed'],
            'kpis' => [
                'eventsPerMinute' => $eventsPerMinute,
                'activeSessions' => $activeSessions,
                'activeUsersOnline' => $activeUsersOnline,
                'anomaliesDetected' => $anomaliesDetected,
                'interceptedAttempts' => $interceptedAttempts,
                'foreignIpBlocked' => $foreignIpBlocked,
                'infoHarvesting' => (clone $todayQuery)
                    ->where('path', 'like', '%report%')
                    ->where('risk_score', '>=', 70)
                    ->count(),
                'gatedCriticalEvents' => $gatedCriticalEvents,
                'coaDnaModificationCount' => $coaDnaModificationCount,
                'floorOverrideCount' => $floorOverrideCount,
                'manualReversalCount' => $manualReversalCount,
                'checkerRequired' => $checkerRequired,
                'integrityScore' => $integrityScore,
                'checksumVerified' => $checksumVerified,
                'shadowLedgerActive' => $shadowLedgerActive,
                'criticalEvents' => $riskDistribution['critical'],
                'highRiskActions' => $riskDistribution['high'],
                'blockedAttempts' => $interceptedAttempts,
                'successfulLogins' => $recent->where('result', 'success')->count(),
                'importCount' => $importCount,
                'exportCount' => $exportCount,
                'downloadCount' => $downloadCount,
                'importCount7d' => $importCount7d,
                'exportCount7d' => $exportCount7d,
                'downloadCount7d' => $downloadCount7d,
                'failedDistinctPages' => $failedDistinctPages,
                'failedDistinctPages7d' => $failedDistinctPages7d,
                'topVisitedPaths' => $topVisitedPaths,
                'mostActiveHour' => $mostActiveHour?->hour_slot ?? 'N/A',
                'mostActiveHourHits' => (int) ($mostActiveHour?->hits ?? 0),
                'digestLastSentAt' => now()->setTime(8, 0)->format('Y-m-d H:i'),
            ],
            'riskDistribution' => $riskDistribution,
            'topRiskyUsers' => $topRiskyUsers,
        ]);
    }

    public function accessLogsConcernStore(Request $request, LoanAccessLog $loan_access_log): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['normal', 'high', 'critical'])],
        ]);

        $concern = LoanAuditConcern::query()->create([
            'loan_access_log_id' => $loan_access_log->id,
            'opened_by_user_id' => (int) $request->user()->id,
            'owner_user_id' => null,
            'status' => 'open',
            'priority' => $data['priority'] ?? 'high',
            'title' => $data['title'],
            'reason' => $data['reason'],
        ]);

        LoanAuditConcernMessage::query()->create([
            'loan_audit_concern_id' => $concern->id,
            'user_id' => (int) $request->user()->id,
            'message' => $data['reason'],
        ]);

        return redirect()->route('loan.system.access_logs.index', ['q' => $loan_access_log->audit_token])->with('status', 'Concern opened for review.');
    }

    private function parseTotalInterestAmount(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^\d+(?:\.\d+)?%?$/', $raw)) {
            throw ValidationException::withMessages([
                'total_interest_amount' => 'Enter total interest as a number (e.g. 15) or percentage (e.g. 15%).',
            ]);
        }

        $normalized = rtrim($raw, '%');
        $amount = (float) $normalized;
        if ($amount < 0 || $amount > 1000000000) {
            throw ValidationException::withMessages([
                'total_interest_amount' => 'Total interest must be between 0 and 1000000000.',
            ]);
        }

        return $amount;
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
            ['key' => 'favicon_url', 'label' => 'Favicon URL (or path)', 'group' => 'company', 'value' => ''],
            ['key' => 'about_us', 'label' => 'About us (public / reports)', 'group' => 'company', 'value' => ''],
            ['key' => 'support_contact_email', 'label' => 'Support contact email', 'group' => 'company', 'value' => ''],
            ['key' => 'default_timezone', 'label' => 'Default timezone', 'group' => 'preferences', 'value' => config('app.timezone', 'UTC')],
            ['key' => 'date_display_format', 'label' => 'Date display format (PHP)', 'group' => 'preferences', 'value' => 'Y-m-d'],
            ['key' => 'records_per_page', 'label' => 'Default table page size', 'group' => 'preferences', 'value' => '20'],
            ['key' => 'maintenance_notice', 'label' => 'Maintenance / banner notice (optional)', 'group' => 'preferences', 'value' => ''],
            ['key' => 'payment_automation', 'label' => 'Payment automation (notes / rules)', 'group' => 'preferences', 'value' => ''],
            ['key' => 'approval_levels', 'label' => 'Approval levels & thresholds', 'group' => 'preferences', 'value' => ''],
            ['key' => 'client_loyalty_points', 'label' => 'Client loyalty / royalty points rules', 'group' => 'preferences', 'value' => ''],
            ['key' => 'org_structure_change_requires_approval', 'label' => 'Require maker-checker approval for structure changes (0/1)', 'group' => 'preferences', 'value' => '0'],
            ['key' => 'org_structure_effective_dated_history', 'label' => 'Track effective-dated structure history (0/1)', 'group' => 'preferences', 'value' => '1'],
            ['key' => 'loan_repayment_allocation_order', 'label' => 'Loan repayment allocation order (csv: principal,interest,fees,penalty,overpayment)', 'group' => 'preferences', 'value' => 'principal,interest,fees,penalty,overpayment'],
            ['key' => 'loan_account_code_collection', 'label' => 'Collection account code (cash/bank receiving account)', 'group' => 'preferences', 'value' => '1004'],
            ['key' => 'loan_account_code_principal', 'label' => 'Loan principal receivable account code', 'group' => 'preferences', 'value' => '1200'],
            ['key' => 'loan_account_code_interest_income', 'label' => 'Loan interest income account code', 'group' => 'preferences', 'value' => '4002'],
            ['key' => 'loan_account_code_fee_income', 'label' => 'Loan fee income account code', 'group' => 'preferences', 'value' => '4007'],
            ['key' => 'loan_account_code_processing_fee_income', 'label' => 'Loan processing fee income account code', 'group' => 'preferences', 'value' => '4005'],
            ['key' => 'loan_account_code_penalty_income', 'label' => 'Loan penalty income account code', 'group' => 'preferences', 'value' => '4003'],
            ['key' => 'loan_account_code_overpayment_liability', 'label' => 'Loan overpayment liability account code', 'group' => 'preferences', 'value' => '2003'],
            ['key' => 'loan_accounting_event_mappings_json', 'label' => 'Loan accounting event mappings JSON', 'group' => 'preferences', 'value' => '{"loan_disbursed":"loan_ledger","loan_repayment":"split_component_posting","loan_overpayment":"loan_overpayments","loan_c2b_reversal":"loan_ledger","penalty_raised":"loan_penalty_income"}'],
            ['key' => 'client_onboarding_required_documents', 'label' => 'Required onboarding documents (csv)', 'group' => 'client_settings', 'value' => 'national_id,passport_photo,proof_of_address'],
            ['key' => 'client_onboarding_kyc_mode', 'label' => 'KYC mode (basic|enhanced)', 'group' => 'client_settings', 'value' => 'basic'],
            ['key' => 'client_onboarding_auto_activate', 'label' => 'Auto-activate client after review (yes|no)', 'group' => 'client_settings', 'value' => 'no'],
            ['key' => 'client_onboarding_requires_guarantor', 'label' => 'Require guarantor on onboarding (yes|no)', 'group' => 'client_settings', 'value' => 'no'],
            ['key' => 'client_onboarding_minimum_age', 'label' => 'Minimum onboarding age', 'group' => 'client_settings', 'value' => '18'],
            ['key' => 'client_onboarding_maximum_age', 'label' => 'Maximum onboarding age', 'group' => 'client_settings', 'value' => '75'],
            ['key' => 'client_onboarding_default_client_status', 'label' => 'Default client status after onboarding', 'group' => 'client_settings', 'value' => 'pending_review'],
            ['key' => 'client_onboarding_blacklist_screening', 'label' => 'Enable blacklist screening (yes|no)', 'group' => 'client_settings', 'value' => 'yes'],
            ['key' => 'client_onboarding_notes', 'label' => 'Onboarding policy notes', 'group' => 'client_settings', 'value' => ''],
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
