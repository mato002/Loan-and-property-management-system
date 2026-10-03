<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmUnitUtilityCharge extends Model
{
    protected $table = 'pm_unit_utility_charges';

    protected $fillable = [
        'property_unit_id',
        'charge_type',
        'billing_month',
        'previous_reading',
        'current_reading',
        'units_consumed',
        'rate_per_unit',
        'fixed_charge',
        'label',
        'amount',
        'notes',
        'is_invoiced',
        'pm_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'units_consumed' => 'decimal:3',
            'rate_per_unit' => 'decimal:2',
            'fixed_charge' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_invoiced' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByPropertyUnit($query, 'pm_unit_utility_charges');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    /**
     * Plain-language billing breakdown for the posted-charges table.
     */
    public function billingExplanation(): string
    {
        $units = round((float) ($this->units_consumed ?? 0), 3);
        $rate = round((float) ($this->rate_per_unit ?? 0), 2);
        $fixed = round((float) ($this->fixed_charge ?? 0), 2);

        $hasUnits = $this->units_consumed !== null && $units > 0.0005;
        $hasRate = $this->rate_per_unit !== null && $rate > 0.004;
        $hasFixed = $this->fixed_charge !== null && $fixed > 0.004;

        if (! $hasUnits && ! $hasRate && ! $hasFixed) {
            return '—';
        }

        $parts = [];
        if ($hasUnits && $hasRate) {
            $parts[] = $this->formatUnits($units).' units × '.\App\Services\Property\PropertyMoney::kes($rate);
        } elseif ($hasUnits) {
            $parts[] = $this->formatUnits($units).' units';
        } elseif ($hasRate) {
            $parts[] = \App\Services\Property\PropertyMoney::kes($rate).' per unit';
        }

        if ($hasFixed) {
            $fixedLabel = \App\Services\Property\PropertyMoney::kes($fixed);
            $parts[] = $parts === [] ? 'Fixed charge of '.$fixedLabel : '+ fixed '.$fixedLabel;
        }

        return implode(' ', $parts);
    }

    private function formatUnits(float $units): string
    {
        if (abs($units - round($units)) < 0.0005) {
            return number_format($units, 0);
        }

        return rtrim(rtrim(number_format($units, 3, '.', ''), '0'), '.');
    }
}
