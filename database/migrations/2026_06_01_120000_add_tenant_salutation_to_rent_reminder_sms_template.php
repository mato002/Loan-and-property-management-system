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

        $definitions = collect(app(PropertyCommunicationTemplateService::class)->defaultTemplateDefinitions())
            ->first(fn (array $row) => ($row['category'] ?? '') === 'rent_reminder' && ($row['channel'] ?? '') === 'sms');

        if (! is_array($definitions)) {
            return;
        }

        PmMessageTemplate::query()
            ->where('category', 'rent_reminder')
            ->where('channel', 'sms')
            ->update([
                'body' => (string) ($definitions['body'] ?? ''),
                'supported_variables' => $definitions['supported_variables'] ?? null,
            ]);
    }

    public function down(): void
    {
        // Non-destructive content update; no rollback body.
    }
};
