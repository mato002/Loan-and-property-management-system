<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PmInvoiceItem extends Model
{
    protected $table = 'pm_invoice_items';

    protected $fillable = [
        'pm_invoice_id',
        'line_no',
        'description',
        'quantity',
        'unit_price',
        'line_subtotal',
        'discount_pct',
        'discount_amount',
        'tax_pct',
        'tax_amount',
        'line_total',
        'source_type',
        'source_id',
        'type',
        'amount',
    ];

    protected static function booted(): void
    {
        static::creating(function (PmInvoiceItem $item): void {
            if ($item->type === null || $item->type === '') {
                if (Schema::hasColumn('pm_invoice_items', 'type')) {
                    $invoiceType = $item->invoice?->invoice_type;
                    $item->type = $invoiceType !== null && $invoiceType !== ''
                        ? (string) $invoiceType
                        : PmInvoice::TYPE_RENT;
                }
            }

            if (
                Schema::hasColumn('pm_invoice_items', 'amount')
                && ($item->amount === null || $item->amount === '')
            ) {
                $lineTotal = (float) ($item->line_total ?? 0);
                if ($lineTotal <= 0) {
                    $qty = (float) ($item->quantity ?? 1);
                    $unit = (float) ($item->unit_price ?? 0);
                    $lineTotal = round($qty * $unit, 2);
                }

                $item->amount = $lineTotal;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'discount_pct' => 'decimal:3',
            'discount_amount' => 'decimal:2',
            'tax_pct' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    /**
     * Recompute and store line_subtotal, discount_amount, tax_amount, line_total
     * from quantity, unit_price, discount_pct, tax_pct. Caller is responsible
     * for saving the model.
     */
    public function recomputeTotals(): void
    {
        $qty = (float) ($this->quantity ?? 0);
        $unit = (float) ($this->unit_price ?? 0);
        $subtotal = round($qty * $unit, 2);

        $discountPct = (float) ($this->discount_pct ?? 0);
        $discount = $discountPct > 0
            ? round($subtotal * $discountPct / 100, 2)
            : (float) ($this->discount_amount ?? 0);

        $afterDiscount = max(0, $subtotal - $discount);
        $taxPct = (float) ($this->tax_pct ?? 0);
        $tax = $taxPct > 0
            ? round($afterDiscount * $taxPct / 100, 2)
            : (float) ($this->tax_amount ?? 0);

        $this->line_subtotal = $subtotal;
        $this->discount_amount = $discount;
        $this->tax_amount = $tax;
        $this->line_total = round($afterDiscount + $tax, 2);
    }
}
