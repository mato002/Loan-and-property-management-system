<?php

namespace Database\Seeders;

use App\Models\PmMessageTemplate;
use App\Services\Property\PropertyCommunicationTemplateService;
use Illuminate\Database\Seeder;

class PropertyCommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PropertyCommunicationTemplateService::class);

        foreach ($service->defaultTemplateDefinitions() as $definition) {
            $exists = PmMessageTemplate::query()
                ->where('name', $definition['name'])
                ->where('channel', $definition['channel'])
                ->exists();

            if ($exists) {
                continue;
            }

            PmMessageTemplate::query()->create([
                'name' => $definition['name'],
                'channel' => $definition['channel'],
                'category' => $definition['category'],
                'subject' => $definition['subject'],
                'body' => $definition['body'],
                'template_version' => 1,
                'is_active' => true,
                'supported_variables' => $definition['supported_variables'],
            ]);
        }
    }
}
