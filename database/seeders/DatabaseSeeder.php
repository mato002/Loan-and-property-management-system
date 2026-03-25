<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@system.com'],
            [
                'name' => 'Super Administrator',
                'password' => $password,
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ]
        );

        // Loan-only demo user (approved for Loan module).
        $loanUser = User::query()->firstOrCreate(
            ['email' => 'loan.user@system.com'],
            [
                'name' => 'Loan Demo User',
                'password' => $password,
                'email_verified_at' => now(),
                // Keep this empty so legacy inference doesn't incorrectly mark them as a Property user.
                'property_portal_role' => null,
            ]
        );

        UserModuleAccess::query()->updateOrCreate(
            ['user_id' => $loanUser->id, 'module' => 'loan'],
            [
                'status' => UserModuleAccess::STATUS_APPROVED,
                'approved_by' => $superAdmin->id,
                'approved_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'admin@loan.com'],
            [
                'name' => 'Loan Administrator',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'officer@loan.com'],
            [
                'name' => 'Loan Officer',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'manager@loan.com'],
            [
                'name' => 'Loan Manager',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'applicant@loan.com'],
            [
                'name' => 'Standard Applicant',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $this->call(EmployeeModuleSeeder::class);
        $this->call(LoanPortfolioDemoSeeder::class);
        $this->call(PropertyModuleDemoSeeder::class);
    }
}
