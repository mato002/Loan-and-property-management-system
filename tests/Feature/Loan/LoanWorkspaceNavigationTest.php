<?php

namespace Tests\Feature\Loan;

use App\Models\User;
use App\Models\UserModuleAccess;
use App\Support\LoanNavigation;
use App\Support\LoanWorkspaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanWorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_workspaces_are_main_domains_only(): void
    {
        $user = $this->loanAdminUser();

        $labels = array_column(LoanNavigation::agentWorkspaces($user), 'label');

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Clients', $labels);
        $this->assertContains('Loan Book', $labels);
        $this->assertContains('Collections', $labels);
        $this->assertContains('Payments', $labels);
        $this->assertContains('Settings', $labels);
        $this->assertNotContains('Add Client', $labels);
        $this->assertNotContains('Communications', $labels);
    }

    public function test_officer_sees_operational_workspaces_only(): void
    {
        $user = $this->loanUserWithRole('officer');

        $keys = array_column(LoanNavigation::agentWorkspaces($user), 'key');

        $this->assertContains(LoanWorkspaces::CLIENTS, $keys);
        $this->assertContains(LoanWorkspaces::PAYMENTS, $keys);
        $this->assertNotContains(LoanWorkspaces::ACCOUNTING, $keys);
        $this->assertNotContains(LoanWorkspaces::HR, $keys);
    }

    public function test_workspace_for_route_resolves_clients_workspace(): void
    {
        $workspace = LoanNavigation::workspaceForRoute('loan.clients.leads');

        $this->assertIsArray($workspace);
        $this->assertSame(LoanWorkspaces::CLIENTS, $workspace['key'] ?? null);
    }

    public function test_loan_dashboard_renders_workspace_sidebar(): void
    {
        $user = $this->loanAdminUser();

        $this->actingAs($user)
            ->get(route('loan.dashboard'))
            ->assertOk()
            ->assertSee('data-loan-nav-mode="workspace"', false)
            ->assertSee('Loan Book', false)
            ->assertSee('Collections', false)
            ->assertDontSee('Add Client', false);
    }

    public function test_clients_index_renders_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->actingAs($user)
            ->get(route('loan.clients.index'))
            ->assertOk()
            ->assertSee('data-loan-workspace="clients"', false)
            ->assertSee('Client directory', false)
            ->assertSee('Client leads', false);
    }

    public function test_client_show_shows_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->assertTrue(\App\Support\LoanWorkspaceTabs::shouldShow('loan.clients.show'));

        $client = \App\Models\LoanClient::query()->create([
            'client_number' => 'CL-WS-'.strtoupper(substr(sha1((string) microtime(true)), 0, 8)),
            'kind' => \App\Models\LoanClient::KIND_CLIENT,
            'first_name' => 'Tab',
            'last_name' => 'Show',
            'phone' => '2547'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'client_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('loan.clients.show', $client))
            ->assertOk()
            ->assertSee('data-loan-workspace="clients"', false)
            ->assertSee('Client directory', false);
    }

    public function test_client_edit_shows_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->assertTrue(\App\Support\LoanWorkspaceTabs::shouldShow('loan.clients.edit'));
    }

    public function test_client_create_shows_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->assertTrue(\App\Support\LoanWorkspaceTabs::shouldShow('loan.clients.create'));

        $this->actingAs($user)
            ->get(route('loan.clients.create'))
            ->assertOk()
            ->assertSee('data-loan-workspace="clients"', false)
            ->assertSee('Add client', false);
    }

    public function test_application_create_shows_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->assertTrue(\App\Support\LoanWorkspaceTabs::shouldShow('loan.book.applications.create'));

        $this->actingAs($user)
            ->get(route('loan.book.applications.create'))
            ->assertOk()
            ->assertSee('data-loan-workspace="loanbook"', false)
            ->assertSee('Create application', false);
    }

    public function test_payments_index_renders_workspace_tabs(): void
    {
        $user = $this->loanAdminUser();

        $this->actingAs($user)
            ->get(route('loan.payments.unposted'))
            ->assertOk()
            ->assertSee('data-loan-workspace="payments"', false)
            ->assertSee('Unposted', false)
            ->assertSee('Processed', false);
    }

    public function test_dashboard_does_not_duplicate_quick_links_in_body(): void
    {
        $user = $this->loanAdminUser();

        $content = $this->actingAs($user)
            ->get(route('loan.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertEquals(1, substr_count($content, 'aria-label="Quick navigation"'));
    }

    public function test_quick_links_include_full_operational_set_for_admin(): void
    {
        $user = $this->loanAdminUser();
        $labels = array_column(LoanNavigation::quickLinksForUser($user), 'label');

        $this->assertContains('Disbursements', $labels);
        $this->assertContains('Pay-in report', $labels);
        $this->assertContains('Unposted', $labels);
    }

    private function loanAdminUser(): User
    {
        return $this->loanUserWithRole('admin');
    }

    private function loanUserWithRole(string $loanRole): User
    {
        $user = User::factory()->create([
            'email' => 'loan-ws-'.uniqid('', true).'@example.test',
            'loan_role' => $loanRole,
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        return $user;
    }
}
