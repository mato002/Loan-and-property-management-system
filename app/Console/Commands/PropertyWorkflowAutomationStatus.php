<?php

namespace App\Console\Commands;

use App\Models\PropertyPortalSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PropertyWorkflowAutomationStatus extends Command
{
    protected $signature = 'property:workflow-automation-status';

    protected $description = 'Show whether property scheduled automation (rent invoices, water, reminders, penalties) is enabled.';

    public function handle(): int
    {
        $envRaw = config('property.workflow_automation_enabled');
        $envActive = $envRaw !== null && $envRaw !== ''
            ? filter_var($envRaw, FILTER_VALIDATE_BOOLEAN)
            : null;

        $this->line('Property workflow automation (scheduled jobs)');
        if ($envActive === null) {
            $this->line('  PROPERTY_WORKFLOW_AUTOMATION_ENABLED: (not set — database / granular keys control automation)');
        } else {
            $this->line('  PROPERTY_WORKFLOW_AUTOMATION_ENABLED: '.($envActive ? 'true' : 'false').' (overrides all flags below)');
        }

        if (! Schema::hasTable('property_portal_settings')) {
            $this->warn('  property_portal_settings table missing.');

            return self::FAILURE;
        }

        $legacy = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        $this->line('  Legacy workflow_auto_reminders (default when granular unset): '.($legacy ? 'on' : 'off'));

        $this->line('  Rent invoices (rent:generate-invoices): '.(PropertyPortalSetting::isRentInvoiceAutomationEnabled() ? 'ON' : 'OFF'));
        $this->line('  Water invoices (water:generate-invoices): '.(PropertyPortalSetting::isWaterInvoiceAutomationEnabled() ? 'ON' : 'OFF'));
        $this->line('  Rent reminders (rent:send-reminders): '.(PropertyPortalSetting::isRentReminderAutomationEnabled() ? 'ON' : 'OFF'));
        $this->line('  Invoice delivery (invoices:deliver-pending): '.(PropertyPortalSetting::isInvoiceDeliveryAutomationEnabled() ? 'ON' : 'OFF'));
        $this->line('  Water penalties (water:apply-penalties): '.(PropertyPortalSetting::isWaterPenaltyAutomationEnabled() ? 'ON' : 'OFF'));
        $this->line('  Any scheduled automation ON: '.(PropertyPortalSetting::isAnyScheduledPropertyAutomationOn() ? 'yes' : 'no'));

        if (! PropertyPortalSetting::isAnyScheduledPropertyAutomationOn()) {
            $this->newLine();
            $this->warn('Scheduled property jobs will skip until at least one flag is on.');
            $this->line('  Enable: Property → System setup → Workflow adjustments, or set PROPERTY_WORKFLOW_AUTOMATION_ENABLED=true in .env');
        } else {
            $this->newLine();
            $this->info('Ensure the OS runs: php artisan schedule:run every minute (see deploy/laravel-scheduler.cron.example).');
        }

        return self::SUCCESS;
    }
}
