<?php

namespace Tests\Feature\Loan;

use App\Models\Employee;
use App\Models\LoanClient;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadWorkspaceRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_client_show_redirects_lead_to_lead_workspace(): void
    {
        [$user, $employee] = $this->loanUserWithEmployee('admin');

        $phone = '2547'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $lead = LoanClient::query()->create([
            'client_number' => 'LD-WS-'.strtoupper(substr(sha1((string) microtime(true)), 0, 8)),
            'kind' => LoanClient::KIND_LEAD,
            'first_name' => 'Lead',
            'last_name' => 'Workspace',
            'phone' => $phone,
            'email' => null,
            'assigned_employee_id' => $employee->id,
            'lead_status' => 'new',
            'client_status' => 'n/a',
            'source_channel' => LoanClient::SOURCE_LEAD,
        ]);

        $this->actingAs($user);

        $this->get(route('loan.clients.show', $lead))
            ->assertRedirect(route('loan.clients.leads.show', $lead));

        $this->get(route('loan.clients.leads.show', $lead))
            ->assertOk()
            ->assertSee('Lead workspace', false)
            ->assertSee('Not a full client yet', false);
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function loanUserWithEmployee(string $loanRole): array
    {
        $email = 'lead-ws-'.uniqid('', true).'@example.test';
        $user = User::factory()->create([
            'email' => $email,
            'loan_role' => $loanRole,
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-WS-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Test',
            'last_name' => (string) $user->id,
            'email' => $email,
        ]);

        return [$user, $employee];
    }
}
