<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PmPermission extends Model
{
    protected $table = 'pm_permissions';

    protected $fillable = [
        'name',
        'key',
        'group',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(PmRole::class, 'pm_role_permission', 'pm_permission_id', 'pm_role_id')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pm_user_permission', 'pm_permission_id', 'user_id')
            ->withTimestamps();
    }
}

