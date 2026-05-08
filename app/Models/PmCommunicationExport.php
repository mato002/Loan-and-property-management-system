<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmCommunicationExport extends Model
{
    protected $table = 'pm_communication_exports';

    protected $fillable = [
        'exported_by_user_id',
        'report_type',
        'format',
        'export_reason',
        'row_count',
        'filters',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_communication_exports', 'exported_by_user_id');
        });
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }
}
