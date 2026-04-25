<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanAccessLog;
use App\Models\LoanBookLoan;
use App\Models\LoanDepartment;
use App\Models\LoanJobTitle;
use App\Models\LoanProduct;
use App\Models\LoanRole;
use App\Models\LoanSupportTicket;
use App\Models\LoanSupportTicketReply;
use App\Models\LoanSystemSetting;
use App\Support\TabularExport;
use App\Models\User;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        'loan_repayment_allocation_order',
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
                        'desc' => 'Client settings module is reserved for upcoming onboarding controls.',
                        'href' => null,
                        'icon' => 'idcard',
                        'status' => 'not_configured',
                        'priority' => 'optional',
                        'coming_soon' => true,
                        'badge' => 'Coming soon',
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'in:daily,weekly,monthly'],
            'default_interest_rate_period' => ['nullable', 'in:daily,weekly,monthly,annual'],
            'payment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'total_interest_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'interest_duration_value' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_type' => ['nullable', 'in:flat_rate,reducing_balance,amortized,simple_interest'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'arrears_penalty_scope' => ['nullable', 'in:whole_loan,per_installment,none'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'rollover_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'loan_offset_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
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
            'charges.*.applies_to_stage' => ['nullable', 'in:application,loan,disbursement,installment'],
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
            'total_interest_amount' => isset($validated['total_interest_amount']) && $validated['total_interest_amount'] !== ''
                ? (float) $validated['total_interest_amount']
                : null,
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
                        'applies_to_stage' => (string) ($charge['applies_to_stage'] ?? 'loan'),
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('loan_products', 'name')->ignore($loan_product->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_term_unit' => ['nullable', 'in:daily,weekly,monthly'],
            'default_interest_rate_period' => ['nullable', 'in:daily,weekly,monthly,annual'],
            'payment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'total_interest_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'interest_duration_value' => ['nullable', 'integer', 'min:1', 'max:600'],
            'interest_type' => ['nullable', 'in:flat_rate,reducing_balance,amortized,simple_interest'],
            'min_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'max_loan_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'arrears_penalty_scope' => ['nullable', 'in:whole_loan,per_installment,none'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'rollover_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'loan_offset_fees' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'repay_waiver_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'client_application_scope' => ['nullable', 'in:any_client,no_running_loans,new_clients_only,existing_clients_only'],
            'installment_display_mode' => ['nullable', 'in:all_installments,due_only,summary'],
            'exempt_from_checkoffs' => ['nullable', 'in:0,1'],
            'cluster_name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'in:0,1'],
            'apply_to_existing_active_loans' => ['nullable', 'in:0,1'],
            'repricing_effective_date' => ['nullable', 'date'],
            'repricing_note' => ['nullable', 'string', 'max:500'],
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
            'total_interest_amount' => isset($validated['total_interest_amount']) && $validated['total_interest_amount'] !== ''
                ? (float) $validated['total_interest_amount']
                : null,
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
        $loan_product->update($updatePayload);

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

        return view('loan.system.setup.access_roles', [
            'title' => 'Loan roles & permissions',
            'subtitle' => 'Create custom access roles, choose permissions, and assign to users.',
            'rbacReady' => $rbacReady,
            'roles' => $rbacReady ? LoanRole::query()->orderByDesc('is_active')->orderBy('name')->get() : collect(),
            'users' => $rbacReady ? User::query()->orderBy('name')->get(['id', 'name', 'email']) : collect(),
            'permissionCatalog' => $this->loanPermissionCatalog(),
            'defaultPermissionsByBaseRole' => $this->defaultPermissionsByBaseRole(),
        ]);
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
            'dashboard.view' => 'Dashboard',
            'employees.view' => 'Employees',
            'clients.view' => 'Clients',
            'loanbook.view' => 'LoanBook',
            'payments.view' => 'Payments',
            'accounting.view' => 'Accounting',
            'financial.view' => 'Financial',
            'branches.view' => 'Branches & Regions',
            'analytics.view' => 'Business Analytics',
            'bulksms.view' => 'Bulk SMS',
            'my_account.view' => 'My Account',
            'system.help.view' => 'System & Help',
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
            'accountant' => ['dashboard.view', 'accounting.view', 'financial.view', 'payments.view', 'clients.view', 'my_account.view', 'system.help.view'],
            'officer' => ['dashboard.view', 'employees.view', 'clients.view', 'loanbook.view', 'payments.view', 'bulksms.view', 'branches.view', 'my_account.view', 'system.help.view'],
            'applicant' => ['dashboard.view', 'my_account.view', 'system.help.view'],
            'user' => ['dashboard.view', 'clients.view', 'loanbook.view', 'payments.view', 'bulksms.view', 'my_account.view', 'system.help.view'],
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
        $logsQuery = LoanAccessLog::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $logsQuery->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $logsQuery->where('method', strtoupper($request->string('method')));
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
            $logsQuery->where(function ($w) use ($term) {
                $w->where('path', 'like', $term)
                    ->orWhere('route_name', 'like', $term)
                    ->orWhere('activity', 'like', $term);
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
                ['When', 'User', 'Method', 'Activity', 'Path', 'Route', 'IP'],
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
                        ];
                    }
                },
                $export
            );
        }

        $perPage = min(200, max(20, (int) $request->query('per_page', 40)));
        $logs = $logsQuery->paginate($perPage)->withQueryString();
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

        return view('loan.system.access_logs.index', [
            'title' => 'Access logs',
            'subtitle' => 'Loan portal page views and actions (from sign-in onward).',
            'logs' => $logs,
            'users' => $users,
            'routes' => $routes,
            'ips' => $ips,
            'perPage' => $perPage,
            'activityTypes' => ['viewed', 'submitted', 'updated', 'deleted', 'failed'],
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
            ['key' => 'loan_repayment_allocation_order', 'label' => 'Loan repayment allocation order (csv: fees,interest,principal)', 'group' => 'preferences', 'value' => 'fees,interest,principal'],
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
