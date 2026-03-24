<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PmRole extends Model
{
    protected $table = 'pm_roles';

    protected $fillable = [
        'name',
        'slug',
        'portal_scope',
        'description',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PmPermission::class, 'pm_role_permission', 'pm_role_id', 'pm_permission_id')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pm_user_role', 'pm_role_id', 'user_id')
            ->withTimestamps();
    }
}

