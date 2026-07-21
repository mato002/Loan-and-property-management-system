<?php

namespace Tests\Feature\Loan;

use App\Models\LoanRole;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Support\LoanNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAccessRolesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_granular_loan_permissions_imply_loanbook_sidebar_access(): void
    {
        $user = $this->createLoanUserWithRolePermissions([
            'loans.view',
            'loan_applications.view',
        ]);

        $this->assertTrue($user->hasLoanPermission('loanbook.view'));

        $menu = LoanNavigation::filterSidebarMenu($user, [
            'Dashboard' => [],
            'LoanBook' => [],
            'Clients' => [],
        ]);

        $this->assertArrayHasKey('LoanBook', $menu);
    }

    public function test_user_cannot_update_permissions_on_own_assigned_role(): void
    {
        $role = LoanRole::query()->create([
            'name' => 'Loan Administrators',
            'slug' => 'loan-administrators',
            'base_role' => 'admin',
            'permissions' => ['dashboard.view', 'system.help.view', 'access_roles.configure'],
            'is_active' => true,
        ]);

        $user = $this->createLoanUserWithRolePermissions(['dashboard.view', 'system.help.view', 'access_roles.configure'], $role);

        $response = $this->actingAs($user)->patch(route('loan.system.setup.access_roles.update', $role), [
            'base_role' => 'admin',
            'is_active' => '1',
            'permissions' => ['dashboard.view', 'access_roles.configure', 'loans.view'],
        ]);

        $response->assertRedirect(route('loan.system.setup.access_roles'));
        $response->assertSessionHas('error');
        $role->refresh();
        $this->assertNotContains('loans.view', $role->permissions);
    }

    public function test_user_cannot_grant_permissions_they_do_not_hold(): void
    {
        $editorRole = LoanRole::query()->create([
            'name' => 'Access Editor',
            'slug' => 'access-editor',
            'base_role' => 'manager',
            'permissions' => ['dashboard.view', 'system.help.view', 'access_roles.configure'],
            'is_active' => true,
        ]);

        $targetRole = LoanRole::query()->create([
            'name' => 'Officers',
            'slug' => 'officers',
            'base_role' => 'officer',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);

        $user = $this->createLoanUserWithRolePermissions(['dashboard.view', 'system.help.view', 'access_roles.configure'], $editorRole);

        $response = $this->actingAs($user)->patch(route('loan.system.setup.access_roles.update', $targetRole), [
            'base_role' => 'officer',
            'is_active' => '1',
            'permissions' => ['dashboard.view', 'loans.view'],
        ]);

        $response->assertRedirect(route('loan.system.setup.access_roles'));
        $response->assertSessionHas('error');
        $targetRole->refresh();
        $this->assertNotContains('loans.view', $targetRole->permissions);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createLoanUserWithRolePermissions(array $permissions, ?LoanRole $role = null): User
    {
        $role ??= LoanRole::query()->create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.uniqid(),
            'base_role' => 'manager',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'loan_role' => 'manager',
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        $user->loanAccessRoles()->sync([$role->id]);

        return $user->fresh();
    }
}
