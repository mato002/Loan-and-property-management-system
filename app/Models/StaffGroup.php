<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StaffGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'staff_group_employee')->withTimestamps();
    }
}
