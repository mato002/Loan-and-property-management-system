<?php

namespace Tests\Feature\Loan;

use App\Models\ClientLead;
use App\Models\ClientWallet;
use App\Models\Employee;
use App\Models\LoanClient;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCaptureVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_capture_verification_flow(): void
    {
        [$user, $employee] = $this->loanUserWithEmployee('admin');

        $this->actingAs($user);

        $page = $this->get(route('loan.clients.leads.create'));
        $page->assertOk();
        $page->assertSee('Lead identity', false);
        $page->assertSee('Lead source &amp; ownership', false);
        $page->assertSee('Client activity / occupation', false);
        $page->assertSee('Lead status &amp; follow-up', false);
        $page->assertSee('Notes', false);
        $page->assertSee('Lead capture tips', false);
        $page->assertSee('Why phone matters', false);
        $page->assertSee('Analytics', false);

        $this->post(route('loan.clients.leads.store'), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'assigned_employee_id' => $employee->id,
            'lead_source' => 'walk_in',
            'sector' => 'student',
        ])->assertSessionHasErrors(['phone']);

        $phone = '2547'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $store = $this->post(route('loan.clients.leads.store'), [
            'first_name' => 'Lead',
            'last_name' => 'Alpha',
            'phone' => $phone,
            'email' => '',
            'assigned_employee_id' => $employee->id,
            'lead_source' => 'walk_in',
            'sector' => 'student',
            'occupation' => 'Kiosk',
            'lead_status' => 'qualified',
            'follow_up_date' => '2026-06-15',
            'follow_up_notes' => 'Callback PM',
            'notes' => 'Interested in top-up',
            'branch' => '',
        ]);
        $store->assertRedirect(route('loan.clients.leads'));
        $store->assertSessionDoesntHaveErrors();

        $lead = LoanClient::query()->where('kind', LoanClient::KIND_LEAD)->where('last_name', 'Alpha')->first();
        $this->assertNotNull($lead);
        $this->assertTrue(ClientLead::query()->where('loan_client_id', $lead->id)->exists());
        $this->assertSame(LoanClient::KIND_LEAD, $lead->kind);
        $this->assertStringStartsWith('LD-', (string) $lead->client_number);
        $this->assertSame(LoanClient::SOURCE_LEAD, $lead->source_channel);
        $meta = (array) ($lead->biodata_meta ?? []);
        $this->assertArrayHasKey('lc_lead_source', $meta);
        $this->assertArrayHasKey('lc_sector', $meta);
        $this->assertSame('walk_in', $meta['lc_lead_source']);
        $this->assertFalse(ClientWallet::query()->where('loan_client_id', $lead->id)->exists());

        $dupPhone = $this->post(route('loan.clients.leads.store'), [
            'first_name' => 'Second',
            'last_name' => 'Lead',
            'phone' => $phone,
            'assigned_employee_id' => $employee->id,
            'lead_source' => 'referral',
            'sector' => 'services',
            'lead_status' => 'new',
        ]);
        $dupPhone->assertSessionHasErrors('phone');

        $beforeKeys = array_keys((array) ($lead->biodata_meta ?? []));
        $this->assertNotEmpty($beforeKeys);

        $patchPayload = $lead->only([
            'client_number', 'first_name', 'last_name', 'phone', 'email', 'id_number', 'gender',
            'next_of_kin_name', 'next_of_kin_contact', 'branch', 'address', 'assigned_employee_id',
            'lead_status', 'client_status', 'notes',
            'guarantor_1_full_name', 'guarantor_1_phone', 'guarantor_1_id_number', 'guarantor_1_relationship', 'guarantor_1_address',
            'guarantor_2_full_name', 'guarantor_2_phone', 'guarantor_2_id_number', 'guarantor_2_relationship', 'guarantor_2_address',
        ]);
        $patchPayload['first_name'] = 'RenamedLead';

        $patch = $this->patch(route('loan.clients.update', $lead), $patchPayload);
        $patch->assertRedirect(route('loan.clients.leads'));

        $lead->refresh();
        $afterMeta = (array) ($lead->biodata_meta ?? []);
        foreach (['lc_lead_source', 'lc_sector', 'lc_occupation', 'lc_follow_up_date', 'lc_follow_up_notes'] as $k) {
            if (array_key_exists($k, $meta)) {
                $this->assertArrayHasKey($k, $afterMeta, 'lc_* biodata should survive lead edit');
                $this->assertSame($meta[$k], $afterMeta[$k]);
            }
        }
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function loanUserWithEmployee(string $loanRole): array
    {
        $email = 'lead-verify-'.uniqid('', true).'@example.test';
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
            'employee_number' => 'EMP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Test',
            'last_name' => (string) $user->id,
            'email' => $email,
        ]);

        return [$user, $employee];
    }
}
