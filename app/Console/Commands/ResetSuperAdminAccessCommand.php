<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetSuperAdminAccessCommand extends Command
{
    protected $signature = 'system:super-admin-access
                            {--email=superadmin@system.com : Staff email for the super administrator account}
                            {--password= : If set, use this password (min 12 characters); otherwise a random password is generated}
                            {--force : Required when APP_ENV is production}';

    protected $description = 'Create or update the super administrator staff user and set a new password (local recovery / bootstrap).';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force. If you truly need this, run again with --force after understanding the risk.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->option('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email value is required.');

            return self::FAILURE;
        }

        if ($this->input->isInteractive() && ! $this->confirm("Create or reset super admin access for {$email}?", true)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $plain = (string) $this->option('password');
        if ($plain !== '') {
            if (strlen($plain) < 12) {
                $this->error('When using --password, it must be at least 12 characters.');

                return self::FAILURE;
            }
        } else {
            $plain = Str::password(length: 24, symbols: true);
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $wasExisting = $user->exists;

        if (! $wasExisting) {
            $user->name = 'Super Administrator';
            $user->email_verified_at = now();
        }

        $user->password = Hash::make($plain);
        $user->is_super_admin = true;
        $user->save();

        $this->info($wasExisting ? 'Super admin password updated.' : 'Super admin user created.');
        $this->line('Email: '.$email);
        $this->newLine();
        $this->warn('Copy the password below now; it will not be shown again.');
        $this->line($plain);
        $this->newLine();
        $this->comment('Sign in at the staff portal, then change the password from your profile if you prefer.');

        return self::SUCCESS;
    }
}
