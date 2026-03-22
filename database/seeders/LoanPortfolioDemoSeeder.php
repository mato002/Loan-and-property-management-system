<?php

namespace Database\Seeders;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\AnalyticsPerformanceRecord;
use App\Models\DefaultClientGroup;
use App\Models\Employee;
use App\Models\LoanBookApplication;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanFormFieldDefinition;
use App\Models\LoanSupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rich demo data for LoanBook, clients, payments, dashboard charts, accounting & support.
 * Safe to run multiple times — skips if marker loan LB-DEMO-001 already exists.
 */
class LoanPortfolioDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('loan_book_loans')) {
            $this->command?->warn('loan_book_loans table missing — run migrations first.');

            return;
        }

        if (LoanBookLoan::query()->where('loan_number', 'LB-DEMO-001')->exists()) {
            $this->command?->info('Loan portfolio demo already seeded (LB-DEMO-001). Skipping.');

            return;
        }

        if (! Schema::hasTable('loan_clients')) {
            $this->command?->warn('loan_clients missing — skipping portfolio demo.');

            return;
        }

        $user = User::query()->first();
        $employee = Employee::query()->first();

        if ($user === null) {
            $this->command?->warn('No users found — run DatabaseSeeder users first.');

            return;
        }

        $this->seedFormDefinitions();

        DB::transaction(function () use ($user, $employee): void {
            $clients = $this->seedClients($employee?->id);
            $applications = $this->seedApplications($clients, $user);
            $loans = $this->seedLoans($clients, $applications);
            $this->seedDisbursements($loans);
            $this->seedPayments($loans, $user);
            $this->seedCollectionEntries($loans, $employee?->id);
            $this->seedSupportTickets($user);
            $this->seedSalaryAdvances($employee);
            $this->seedRequisitions($user);
            $this->seedAnalyticsPerformance();
            $this->seedDefaultGroup($clients);
            $this->seedJournalIfPossible($user);
        });

        $this->command?->info('Loan portfolio demo seeded: clients, applications, loans, payments, charts data.');
    }

    private function seedFormDefinitions(): void
    {
        if (! Schema::hasTable('loan_form_field_definitions')) {
            return;
        }

        foreach ([
            LoanFormFieldDefinition::KIND_CLIENT_LOAN,
            LoanFormFieldDefinition::KIND_STAFF_LOAN,
            LoanFormFieldDefinition::KIND_SALARY_ADVANCE,
            LoanFormFieldDefinition::KIND_SYSTEM_ACCESS,
            LoanFormFieldDefinition::KIND_LOAN_PRODUCTS,
            LoanFormFieldDefinition::KIND_LEAVE_WORKFLOW,
            LoanFormFieldDefinition::KIND_CLIENT_BIODATA,
            LoanFormFieldDefinition::KIND_GROUP_LENDING,
            LoanFormFieldDefinition::KIND_ACCOUNTING_FORMS,
            LoanFormFieldDefinition::KIND_STAFF_LEAVE_APPLICATION,
            LoanFormFieldDefinition::KIND_STAFF_STRUCTURE,
            LoanFormFieldDefinition::KIND_STAFF_PERFORMANCE,
            LoanFormFieldDefinition::KIND_LOAN_POLICY,
        ] as $kind) {
            LoanFormFieldDefinition::ensureDefaults($kind);
        }
    }

    /**
     * @return list<LoanClient>
     */
    private function seedClients(?int $employeeId): array
    {
        $rows = [
            ['DEMO-CLI-001', LoanClient::KIND_CLIENT, 'Grace', 'Wambui', 'Nairobi HQ'],
            ['DEMO-CLI-002', LoanClient::KIND_CLIENT, 'Peter', 'Kamau', 'Westlands'],
            ['DEMO-CLI-003', LoanClient::KIND_CLIENT, 'Lucy', 'Achieng', 'Nairobi HQ'],
            ['DEMO-CLI-004', LoanClient::KIND_CLIENT, 'David', 'Omondi', 'Mombasa'],
            ['DEMO-CLI-005', LoanClient::KIND_CLIENT, 'Ann', 'Muthoni', 'Nairobi HQ'],
            ['DEMO-CLI-006', LoanClient::KIND_CLIENT, 'Samuel', 'Njoroge', 'Westlands'],
            ['DEMO-CLI-007', LoanClient::KIND_CLIENT, 'Faith', 'Nyambura', 'Nairobi HQ'],
            ['DEMO-CLI-008', LoanClient::KIND_CLIENT, 'Brian', 'Mutua', 'Mombasa'],
            ['DEMO-CLI-009', LoanClient::KIND_CLIENT, 'Janet', 'Chebet', 'Nairobi HQ'],
            ['DEMO-CLI-010', LoanClient::KIND_CLIENT, 'Kevin', 'Odhiambo', 'Kisumu'],
            ['DEMO-CLI-L01', LoanClient::KIND_LEAD, 'Rose', 'Wanjiru', 'Nairobi HQ'],
            ['DEMO-CLI-L02', LoanClient::KIND_LEAD, 'Eric', 'Otieno', 'Kisumu'],
        ];

        $out = [];
        foreach ($rows as $i => [$num, $kind, $fn, $ln, $branch]) {
            $out[] = LoanClient::query()->create([
                'client_number' => $num,
                'kind' => $kind,
                'first_name' => $fn,
                'last_name' => $ln,
                'phone' => '+2547'.str_pad((string) (100000000 + $i), 8, '0', STR_PAD_LEFT),
                'email' => strtolower($fn.'.'.$ln).'@demo-loan.local',
                'id_number' => 'ID-DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'address' => $branch.' demo address',
                'branch' => $branch,
                'assigned_employee_id' => $employeeId,
                'client_status' => $kind === LoanClient::KIND_CLIENT ? 'active' : 'lead',
                'lead_status' => $kind === LoanClient::KIND_LEAD ? 'new' : null,
                'notes' => 'Seeded by LoanPortfolioDemoSeeder',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<LoanClient>  $clients
     * @return array<int, LoanBookApplication|null>
     */
    private function seedApplications(array $clients, User $user): array
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return [];
        }

        $defs = [
            ['APP-DEMO-001', 0, LoanBookApplication::STAGE_CREDIT_REVIEW, 95000, 18, 'Working capital'],
            ['APP-DEMO-002', 1, LoanBookApplication::STAGE_APPROVED, 220000, 24, 'Asset purchase'],
            ['APP-DEMO-003', 2, LoanBookApplication::STAGE_SUBMITTED, 45000, 12, 'School fees'],
            ['APP-DEMO-004', 3, LoanBookApplication::STAGE_DECLINED, 500000, 36, 'Expansion'],
            ['APP-DEMO-005', 4, LoanBookApplication::STAGE_DISBURSED, 95000, 18, 'Stock finance'],
            ['APP-DEMO-006', 5, LoanBookApplication::STAGE_DISBURSED, 220000, 24, 'Medical'],
            ['APP-DEMO-007', 6, LoanBookApplication::STAGE_DISBURSED, 88000, 12, 'Agri input'],
            ['APP-DEMO-008', 7, LoanBookApplication::STAGE_DISBURSED, 200000, 24, 'Equipment batch'],
            ['APP-DEMO-009', 8, LoanBookApplication::STAGE_DISBURSED, 155000, 18, 'Retail stock'],
            ['APP-DEMO-010', 9, LoanBookApplication::STAGE_APPROVED, 150000, 24, 'Pending drawdown'],
        ];

        $map = [];
        foreach ($defs as [$ref, $ci, $stage, $amt, $term, $purpose]) {
            $map[] = LoanBookApplication::query()->create([
                'reference' => $ref,
                'loan_client_id' => $clients[$ci]->id,
                'product_name' => match ($stage) {
                    LoanBookApplication::STAGE_DECLINED => 'Commercial term (declined sample)',
                    default => 'Standard working capital',
                },
                'amount_requested' => $amt,
                'term_months' => $term,
                'purpose' => $purpose,
                'stage' => $stage,
                'branch' => $clients[$ci]->branch,
                'notes' => 'Demo application',
                'submitted_at' => now()->subDays(40 - (int) $ci),
            ]);
        }

        return $map;
    }

    /**
     * @param  list<LoanClient>  $clients
     * @param  array<int, LoanBookApplication>  $applications
     * @return list<LoanBookLoan>
     */
    private function seedLoans(array $clients, array $applications): array
    {
        $byRef = [];
        foreach ($applications as $app) {
            $byRef[$app->reference] = $app;
        }

        $defs = [
            ['LB-DEMO-001', 4, LoanBookLoan::STATUS_ACTIVE, 0, 52000, 95000, 0.1425, $byRef['APP-DEMO-005'] ?? null, now()->subMonths(5)],
            ['LB-DEMO-002', 5, LoanBookLoan::STATUS_ACTIVE, 8, 142000, 220000, 0.1350, $byRef['APP-DEMO-006'] ?? null, now()->subMonths(4)],
            ['LB-DEMO-003', 6, LoanBookLoan::STATUS_ACTIVE, 22, 31000, 88000, 0.1500, $byRef['APP-DEMO-007'] ?? null, now()->subMonths(6)],
            ['LB-DEMO-004', 7, LoanBookLoan::STATUS_ACTIVE, 45, 78000, 200000, 0.1600, $byRef['APP-DEMO-008'] ?? null, now()->subMonths(8)],
            ['LB-DEMO-005', 4, LoanBookLoan::STATUS_CLOSED, 0, 0, 120000, 0.1300, null, now()->subMonths(14)],
            ['LB-DEMO-006', 5, LoanBookLoan::STATUS_RESTRUCTURED, 0, 41000, 95000, 0.1200, null, now()->subMonths(10)],
            ['LB-DEMO-007', 9, LoanBookLoan::STATUS_PENDING_DISBURSEMENT, 0, 150000, 150000, 0.1400, $byRef['APP-DEMO-010'] ?? null, null],
            ['LB-DEMO-008', 8, LoanBookLoan::STATUS_ACTIVE, 2, 98000, 155000, 0.1380, $byRef['APP-DEMO-009'] ?? null, now()->subMonths(3)],
        ];

        $loans = [];
        foreach ($defs as [$num, $ci, $status, $dpd, $balance, $principal, $rate, $app, $disbursed]) {
            $loans[] = LoanBookLoan::query()->create([
                'loan_number' => $num,
                'loan_book_application_id' => $app?->id,
                'loan_client_id' => $clients[$ci]->id,
                'product_name' => 'Demo term loan',
                'principal' => $principal,
                'balance' => $balance,
                'interest_rate' => $rate,
                'status' => $status,
                'dpd' => $dpd,
                'disbursed_at' => $disbursed,
                'maturity_date' => $disbursed ? $disbursed->copy()->addMonths(18)->toDateString() : null,
                'branch' => $clients[$ci]->branch,
                'notes' => 'Portfolio demo seed',
            ]);
        }

        return $loans;
    }

    /**
     * @param  list<LoanBookLoan>  $loans
     */
    private function seedDisbursements(array $loans): void
    {
        if (! Schema::hasTable('loan_book_disbursements')) {
            return;
        }

        foreach ($loans as $loan) {
            if ($loan->disbursed_at === null || (float) $loan->principal <= 0) {
                continue;
            }
            LoanBookDisbursement::query()->create([
                'loan_book_loan_id' => $loan->id,
                'amount' => $loan->principal,
                'reference' => 'DISB-'.$loan->loan_number,
                'method' => 'bank_transfer',
                'disbursed_at' => $loan->disbursed_at->toDateString(),
                'notes' => 'Demo disbursement',
            ]);
        }
    }

    /**
     * @param  list<LoanBookLoan>  $loans
     */
    private function seedPayments(array $loans, User $user): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return;
        }

        $seq = 0;
        foreach ($loans as $loan) {
            if ($loan->status !== LoanBookLoan::STATUS_ACTIVE && $loan->status !== LoanBookLoan::STATUS_RESTRUCTURED) {
                continue;
            }
            if ((float) $loan->balance <= 0) {
                continue;
            }

            for ($m = 5; $m >= 0; $m--) {
                $monthStart = now()->subMonths($m)->startOfMonth();
                $daysInMonth = (int) $monthStart->daysInMonth;
                foreach ([10, 22] as $dayOffset) {
                    $day = min($dayOffset, $daysInMonth);
                    $when = $monthStart->copy()->day($day)->setTime(10, 30);
                    $amount = 3500 + ($loan->id * 127 % 8000) + ($m * 400);
                    $seq++;
                    LoanBookPayment::query()->create([
                        'reference' => 'DEMO-PAY-'.$loan->id.'-'.$seq,
                        'loan_book_loan_id' => $loan->id,
                        'amount' => $amount,
                        'currency' => 'KES',
                        'channel' => $seq % 3 === 0 ? 'mpesa' : 'bank',
                        'status' => LoanBookPayment::STATUS_PROCESSED,
                        'payment_kind' => LoanBookPayment::KIND_NORMAL,
                        'merged_into_payment_id' => null,
                        'mpesa_receipt_number' => $seq % 3 === 0 ? 'R'.str_pad((string) $seq, 8, '0', STR_PAD_LEFT) : null,
                        'payer_msisdn' => '+2547'.str_pad((string) (20000000 + $seq), 8, '0', STR_PAD_LEFT),
                        'transaction_at' => $when,
                        'posted_at' => $when->copy()->addHour(),
                        'posted_by' => $user->id,
                        'notes' => 'Demo collection',
                        'created_by' => $user->id,
                    ]);
                }
            }
        }

        LoanBookPayment::query()->create([
            'reference' => 'DEMO-PAY-UNPOSTED-1',
            'loan_book_loan_id' => $loans[0]->id,
            'amount' => 12000,
            'currency' => 'KES',
            'channel' => 'mpesa',
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now()->subDay(),
            'notes' => 'Awaiting validation (demo)',
            'created_by' => $user->id,
        ]);

        LoanBookPayment::query()->create([
            'reference' => 'DEMO-PAY-UNPOSTED-2',
            'loan_book_loan_id' => $loans[1]->id,
            'amount' => 8500,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now()->subHours(6),
            'created_by' => $user->id,
        ]);
    }

    /**
     * @param  list<LoanBookLoan>  $loans
     */
    private function seedCollectionEntries(array $loans, ?int $employeeId): void
    {
        if (! Schema::hasTable('loan_book_collection_entries')) {
            return;
        }

        $eligible = array_values(array_filter($loans, fn (LoanBookLoan $l) => in_array($l->status, [
            LoanBookLoan::STATUS_ACTIVE,
            LoanBookLoan::STATUS_RESTRUCTURED,
        ], true)));

        if ($eligible === []) {
            return;
        }

        for ($i = 0; $i < 24; $i++) {
            $loan = $eligible[$i % count($eligible)];
            $on = now()->subMonths($i % 6)->startOfMonth()->addDays(5 + ($i % 12));
            LoanBookCollectionEntry::query()->create([
                'loan_book_loan_id' => $loan->id,
                'collected_on' => $on->toDateString(),
                'amount' => 2000 + ($i * 750),
                'channel' => $i % 2 === 0 ? 'field_cash' : 'mpesa',
                'collected_by_employee_id' => $employeeId,
                'notes' => 'Demo field collection',
            ]);
        }
    }

    private function seedSupportTickets(User $user): void
    {
        if (! Schema::hasTable('loan_support_tickets')) {
            return;
        }

        $items = [
            ['Printer not working in Westlands', 'Need IT to check label printer.', LoanSupportTicket::STATUS_OPEN, LoanSupportTicket::PRIORITY_NORMAL],
            ['Mpesa settlement delay', 'Yesterday batch still showing unposted.', LoanSupportTicket::STATUS_IN_PROGRESS, LoanSupportTicket::PRIORITY_HIGH],
            ['Access to arrears report', 'New officer needs export permission.', LoanSupportTicket::STATUS_OPEN, LoanSupportTicket::PRIORITY_LOW],
        ];

        foreach ($items as $i => [$subj, $body, $status, $pri]) {
            LoanSupportTicket::query()->create([
                'ticket_number' => 'DEMO-TKT-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'subject' => $subj,
                'body' => $body,
                'category' => LoanSupportTicket::CATEGORY_GENERAL,
                'priority' => $pri,
                'status' => $status,
            ]);
        }
    }

    private function seedSalaryAdvances(?Employee $employee): void
    {
        if ($employee === null || ! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        AccountingSalaryAdvance::query()->create([
            'employee_id' => $employee->id,
            'amount' => 25000,
            'currency' => 'KES',
            'status' => AccountingSalaryAdvance::STATUS_PENDING,
            'requested_on' => now()->subDays(2)->toDateString(),
            'notes' => 'Demo pending advance',
        ]);

        $e2 = Employee::query()->where('id', '!=', $employee->id)->first();
        if ($e2) {
            AccountingSalaryAdvance::query()->create([
                'employee_id' => $e2->id,
                'amount' => 15000,
                'currency' => 'KES',
                'status' => AccountingSalaryAdvance::STATUS_APPROVED,
                'requested_on' => now()->subWeek()->toDateString(),
                'approved_by' => User::query()->first()?->id,
                'notes' => 'Demo approved advance',
            ]);
        }
    }

    private function seedRequisitions(User $user): void
    {
        if (! Schema::hasTable('accounting_requisitions')) {
            return;
        }

        AccountingRequisition::query()->create([
            'reference' => 'REQ-DEMO-001',
            'title' => 'Stationery restock',
            'purpose' => 'Paper, toner, files for credit desk',
            'amount' => 18500,
            'status' => AccountingRequisition::STATUS_PENDING,
            'requested_by' => $user->id,
        ]);

        AccountingRequisition::query()->create([
            'reference' => 'REQ-DEMO-002',
            'title' => 'Field visit fuel',
            'purpose' => 'Mombasa collections run',
            'amount' => 42000,
            'status' => AccountingRequisition::STATUS_APPROVED,
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now()->subDays(3),
        ]);
    }

    private function seedAnalyticsPerformance(): void
    {
        if (! Schema::hasTable('analytics_performance_records')) {
            return;
        }

        for ($m = 5; $m >= 0; $m--) {
            $d = now()->subMonths($m)->startOfMonth()->addDays(14);
            AnalyticsPerformanceRecord::query()->create([
                'record_date' => $d->toDateString(),
                'branch' => 'Nairobi HQ',
                'total_outstanding' => 1_200_000 + ($m * 85_000),
                'disbursements_period' => 180_000 + ($m * 22_000),
                'collections_period' => 210_000 + ($m * 18_000),
                'npl_rate' => 4.2 + ($m * 0.15),
                'active_borrowers_count' => 120 + ($m * 3),
                'notes' => 'Demo performance snapshot',
            ]);
        }
    }

    /**
     * @param  list<LoanClient>  $clients
     */
    private function seedDefaultGroup(array $clients): void
    {
        if (! Schema::hasTable('default_client_groups')) {
            return;
        }

        $group = DefaultClientGroup::query()->firstOrCreate(
            ['name' => 'DEMO Chama Plus'],
            ['description' => 'Seeded group for UI demos']
        );

        $ids = array_slice(array_map(fn (LoanClient $c) => $c->id, $clients), 0, 4);
        $group->loanClients()->syncWithoutDetaching($ids);
    }

    private function seedJournalIfPossible(User $user): void
    {
        if (! Schema::hasTable('accounting_journal_entries') || ! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('id')->limit(3)->get();
        if ($accounts->count() < 2) {
            return;
        }

        $a = $accounts[0];
        $b = $accounts[1];
        $amount = 50000;

        $entry = AccountingJournalEntry::query()->create([
            'entry_date' => now()->subDays(5)->toDateString(),
            'reference' => 'JE-DEMO-001',
            'description' => 'Demo journal — portfolio seed',
            'created_by' => $user->id,
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $a->id,
            'debit' => $amount,
            'credit' => 0,
            'memo' => 'Debit side',
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $b->id,
            'debit' => 0,
            'credit' => $amount,
            'memo' => 'Credit side',
        ]);
    }
}
