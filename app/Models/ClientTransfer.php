<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTransfer extends Model
{
    protected $fillable = [
        'loan_client_id',
        'from_branch',
        'to_branch',
        'from_employee_id',
        'to_employee_id',
        'reason',
        'transferred_by',
    ];

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
