<?php

namespace App\Console\Commands;

use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillAgentBrandingCommand extends Command
{
    protected $signature = 'property:backfill-agent-branding
                            {--agent= : Agent user id to receive branding}
                            {--move-from-global : Copy global branding rows to the agent, then remove global branding keys}';

    protected $description = 'Assign legacy global property branding to a specific agent workspace.';

    public function handle(): int
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->error('Run migrations first (agent_user_id on property_portal_settings).');

            return self::FAILURE;
        }

        $agentId = (int) $this->option('agent');
        if ($agentId <= 0) {
            $this->error('Pass --agent=<user_id> for the property agent who owns the branding.');

            return self::FAILURE;
        }

        $agent = User::query()->find($agentId);
        if (! $agent) {
            $this->error("User #{$agentId} not found.");

            return self::FAILURE;
        }

        $copied = 0;
        foreach (PropertyWorkspaceBranding::KEYS as $key) {
            $globalValue = PropertyPortalSetting::query()
                ->whereNull('agent_user_id')
                ->where('key', $key)
                ->value('value');

            if ($globalValue === null || $globalValue === '') {
                continue;
            }

            PropertyPortalSetting::query()->updateOrCreate(
                [
                    'agent_user_id' => $agentId,
                    'key' => $key,
                ],
                ['value' => $globalValue]
            );
            $copied++;

            if ($this->option('move-from-global')) {
                PropertyPortalSetting::query()
                    ->whereNull('agent_user_id')
                    ->where('key', $key)
                    ->delete();
            }
        }

        $this->info("Branding keys copied for agent #{$agentId} ({$agent->email}): {$copied}.");

        if ($this->option('move-from-global')) {
            $this->info('Global branding keys removed — super admin will now see APP_NAME only.');
        }

        return self::SUCCESS;
    }
}
