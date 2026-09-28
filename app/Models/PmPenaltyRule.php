<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmPenaltyRule extends Model
{
    protected $table = 'pm_penalty_rules';

    public const COMPOUNDING_SIMPLE = 'simple';

    public const COMPOUNDING_DAILY = 'daily_compound';

    public const COMPOUNDING_ONE_SHOT = 'one_shot';

    public const SCOPE_GLOBAL = 'global';

    public const TRIGGER_DAYS_AFTER_DUE = 'days_after_due';

    public const FORMULA_PERCENT = 'percent_of_rent';

    public const FORMULA_FLAT = 'flat';

    public const FORMULA_PERCENT_PLUS_FLAT = 'percent_plus_flat';

    /**
     * @return array<string, string>
     */
    public static function formulaOptions(): array
    {
        return [
            self::FORMULA_PERCENT => 'A percent of the unpaid rent',
            self::FORMULA_FLAT => 'A fixed amount',
            self::FORMULA_PERCENT_PLUS_FLAT => 'A percent plus a fixed amount',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function compoundingOptions(): array
    {
        return [
            self::COMPOUNDING_SIMPLE => 'Once for that overdue bill',
            self::COMPOUNDING_ONE_SHOT => 'Only once on that bill',
            self::COMPOUNDING_DAILY => 'Again for every extra day late',
        ];
    }

    public static function formulaLabel(?string $formula): string
    {
        return self::formulaOptions()[$formula] ?? 'A percent of the unpaid rent';
    }

    public static function compoundingLabel(?string $mode): string
    {
        return self::compoundingOptions()[$mode] ?? 'Once for that overdue bill';
    }

    protected $fillable = [
        'name',
        'scope',
        'trigger_event',
        'grace_days',
        'formula',
        'compounding_mode',
        'amount',
        'percent',
        'cap',
        'cumulative_cap',
        'effective_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percent' => 'decimal:4',
            'cap' => 'decimal:2',
            'cumulative_cap' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
            'grace_days' => 'integer',
        ];
    }
}
