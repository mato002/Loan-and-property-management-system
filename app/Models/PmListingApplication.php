<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmListingApplication extends Model
{
    protected $table = 'pm_listing_applications';

    protected $fillable = [
        'property_unit_id',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->whereIn('property_unit_id', function ($sub) use ($user) {
                $sub->select('pu.id')
                    ->from('property_units as pu')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->where('p.agent_user_id', $user->id);
            });
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
