<?php

use App\Models\PmMessageTemplate;
use App\Services\Property\PropertyCommunicationTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_message_templates')) {
            return;
        }

        $definitions = collect(app(PropertyCommunicationTemplateService::class)->defaultTemplateDefinitions());

        foreach (['sms', 'email'] as $channel) {
            $definition = $definitions->first(
                fn (array $row) => ($row['category'] ?? '') === 'rent_reminder' && ($row['channel'] ?? '') === $channel
            );

            if (! is_array($definition)) {
                continue;
            }

            PmMessageTemplate::query()
                ->where('category', 'rent_reminder')
                ->where('channel', $channel)
                ->update([
                    'body' => (string) ($definition['body'] ?? ''),
                    'subject' => $channel === 'email' ? ($definition['subject'] ?? null) : null,
                    'supported_variables' => $definition['supported_variables'] ?? null,
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive content update; no rollback body.
    }
};
