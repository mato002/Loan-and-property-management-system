<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PmMaintenanceRequest extends Model
{
    protected $table = 'pm_maintenance_requests';

    protected $fillable = [
        'property_id',
        'property_unit_id',
        'pm_tenant_id',
        'reported_by_user_id',
        'assigned_user_id',
        'category',
        'description',
        'urgency',
        'status',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PmMaintenanceRequest $request) {
            if (! Schema::hasTable('pm_maintenance_request_files')) {
                return;
            }

            $request->load('files');
            foreach ($request->files as $file) {
                Storage::disk($file->disk ?: 'local')->delete($file->path);
            }
        });

        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $ownerIds = AgentWorkspaceScope::workspaceOwnerIds();
            if ($ownerIds === []) {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->where(function (Builder $visible) use ($ownerIds) {
                $visible->whereIn('property_unit_id', function ($sub) use ($ownerIds) {
                    $sub->select('pu.id')
                        ->from('property_units as pu')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->where(function ($owned) use ($ownerIds) {
                            $owned->whereIn('p.agent_user_id', $ownerIds)
                                ->orWhereNull('p.agent_user_id');
                        });
                });

                if (Schema::hasColumn('pm_maintenance_requests', 'property_id')) {
                    $visible->orWhereIn('property_id', function ($sub) use ($ownerIds) {
                        $sub->select('id')
                            ->from('properties')
                            ->where(function ($owned) use ($ownerIds) {
                                $owned->whereIn('agent_user_id', $ownerIds)
                                    ->orWhereNull('agent_user_id');
                            });
                    });
                }
            });
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function locationLabel(): string
    {
        $propertyName = null;
        if (Schema::hasColumn($this->getTable(), 'property_id')) {
            $propertyName = $this->property?->name;
        }
        $propertyName = $propertyName ?: ($this->unit?->property?->name ?? 'Property');
        $unit = $this->unit?->label;

        return $unit ? $propertyName.'/'.$unit : $propertyName.' / Whole property';
    }

    public function pmTenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_maintenance_request_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PmMaintenanceRequestFile::class, 'pm_maintenance_request_id');
    }

    public static function openAlertCount(): int
    {
        return once(function (): int {
            if (! Schema::hasTable('pm_maintenance_requests')) {
                return 0;
            }

            return (int) static::query()->whereIn('status', ['open', 'in_progress'])->count();
        });
    }
}
