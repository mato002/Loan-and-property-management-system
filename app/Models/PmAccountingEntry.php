<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmAccountingEntry extends Model
{
    protected $table = 'pm_accounting_entries';

    public const CATEGORY_INCOME = 'income';
    public const CATEGORY_EXPENSE = 'expense';
    public const CATEGORY_ASSET = 'asset';
    public const CATEGORY_LIABILITY = 'liability';
    public const CATEGORY_EQUITY = 'equity';

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';

    protected $fillable = [
        'property_id',
        'recorded_by_user_id',
        'entry_date',
        'account_name',
        'category',
        'entry_type',
        'amount',
        'reference',
        'description',
        'reversal_of_id',
        'source_key',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Agent isolation: an entry belongs to an agent if its `property_id`
     * resolves to a property in their workspace, or — for property-less
     * entries (e.g. payroll runs, agency-wide bank moves) — if the agent
     * recorded it. Super admins see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $userId = (int) \Illuminate\Support\Facades\Auth::id();

            $query->where(function (Builder $scope) use ($userId) {
                $scope->whereIn('pm_accounting_entries.property_id', function ($sub) use ($userId) {
                    $sub->select('id')->from('properties')->where('agent_user_id', $userId);
                })->orWhere(function (Builder $owned) use ($userId) {
                    $owned->whereNull('pm_accounting_entries.property_id')
                        ->where('pm_accounting_entries.recorded_by_user_id', $userId);
                });
            });
        });
    }

    /**
     * @return array<string,string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_INCOME => 'Income',
            self::CATEGORY_EXPENSE => 'Expense',
            self::CATEGORY_ASSET => 'Asset',
            self::CATEGORY_LIABILITY => 'Liability',
            self::CATEGORY_EQUITY => 'Equity',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_DEBIT => 'Debit',
            self::TYPE_CREDIT => 'Credit',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}

