<?php

namespace App\Http\Controllers\Property\Concerns;

use App\Models\PmPortalAction;
use Illuminate\Http\Request;

trait RecordsPmPortalDraft
{
    protected function savePmPortalDraft(
        Request $request,
        string $portalRole,
        string $actionKey,
        ?string $notes,
        array $context,
    ): void {
        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => $portalRole,
            'action_key' => $actionKey,
            'notes' => $notes,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
