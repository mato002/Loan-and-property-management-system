<?php

namespace App\Jobs;

use App\Services\Property\UtilityIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshUtilityIntelligenceCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $agentUserId,
        public ?int $propertyId = null,
        public int $months = 12,
    ) {}

    public function handle(UtilityIntelligenceService $intelligence): void
    {
        $intelligence->forgetCache($this->agentUserId);
        $intelligence->dashboard([
            'property_id' => $this->propertyId,
            'months' => $this->months,
            'agent_user_id' => $this->agentUserId,
        ]);
    }
}
