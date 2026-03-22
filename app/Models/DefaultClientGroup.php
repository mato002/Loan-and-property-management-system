<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DefaultClientGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function loanClients(): BelongsToMany
    {
        return $this->belongsToMany(LoanClient::class, 'default_client_group_loan_client')
            ->withTimestamps();
    }
}
