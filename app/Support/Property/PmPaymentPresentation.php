<?php

namespace App\Support\Property;

use App\Models\PmPayment;
use Illuminate\Support\HtmlString;

final class PmPaymentPresentation
{
    public static function payerPhone(PmPayment $payment, string $empty = '—'): string
    {
        $tenant = self::resolveTenant($payment);

        $phone = trim((string) (
            data_get($payment->meta, 'payer_phone')
            ?? data_get($payment->meta, 'phone')
            ?? $tenant?->phone
            ?? $tenant?->user?->phone
            ?? ''
        ));

        return $phone !== '' ? $phone : $empty;
    }

    public static function transactionRef(PmPayment $payment, string $empty = '—'): string
    {
        $transactionRef = trim((string) (
            data_get($payment->meta, 'mpesa_ref')
            ?? data_get($payment->meta, 'ezen_ref_no')
            ?? ''
        ));

        $externalRef = trim((string) ($payment->external_ref ?? ''));
        if ($transactionRef === '' && $externalRef !== '' && ! self::isInternalEzenReference($externalRef)) {
            $transactionRef = $externalRef;
        }

        return $transactionRef !== '' ? $transactionRef : $empty;
    }

    public static function paymentMethod(PmPayment $payment, string $empty = '—'): string
    {
        $receiptedTo = trim((string) data_get($payment->meta, 'receipted_to', ''));
        $mpesaRef = trim((string) data_get($payment->meta, 'mpesa_ref', ''));

        if ($mpesaRef !== '' && $mpesaRef !== 'CASH' && preg_match('/^U[A-Z0-9]{9,}$/', strtoupper($mpesaRef)) === 1) {
            return 'M-Pesa';
        }

        if ($receiptedTo !== '') {
            $upper = strtoupper($receiptedTo);
            if (str_contains($upper, 'CASH')) {
                return 'Cash';
            }
            if (str_contains($upper, 'M-PESA') || str_contains($upper, 'MPESA')) {
                return 'M-Pesa';
            }
            if ($upper === 'MR.' || $upper === 'MR') {
                $receiptNo = trim((string) data_get($payment->meta, 'ezen_receipt_no', ''));
                if ($receiptNo !== '' && trim((string) self::transactionRef($payment, '')) === '') {
                    return 'Cash';
                }
            }
            if (str_contains($upper, 'BANK') || str_contains($upper, 'CO-OPERATIVE') || str_contains($upper, 'EQUITY') || str_contains($upper, 'KCB')) {
                return $mpesaRef !== '' ? 'M-Pesa' : 'Bank transfer';
            }

            return $receiptedTo;
        }

        $channel = strtolower(trim((string) ($payment->channel ?? '')));

        return match ($channel) {
            'mpesa', 'mpesa_sms_ingest', 'mpesa_stk', 'equity_paybill' => 'M-Pesa',
            'bank', 'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            'card' => 'Card',
            'cheque' => 'Cheque',
            'ezen_import', 'ezen_receipt' => $empty,
            default => $channel !== '' ? ucfirst(str_replace('_', ' ', $channel)) : $empty,
        };
    }

    public static function propertyUnit(PmPayment $payment, string $empty = '—'): string|HtmlString
    {
        if ($payment->relationLoaded('allocations')) {
            foreach ($payment->allocations as $allocation) {
                $invoice = $allocation->invoice;
                if ($invoice?->relationLoaded('unit') && $invoice->unit) {
                    $label = self::formatPropertyUnitLabel($invoice->unit->property, $invoice->unit);
                    if ($label !== '') {
                        return $label;
                    }
                }
            }
        }

        $propertyCode = trim((string) data_get($payment->meta, 'property_code', ''));
        $unitLabel = trim((string) data_get($payment->meta, 'unit_label', ''));
        if ($propertyCode !== '' || $unitLabel !== '') {
            return self::formatPropertyUnitParts($propertyCode, $unitLabel);
        }

        return $empty;
    }

    public static function payerPhoneAndRef(PmPayment $payment, string $empty = '—'): string|HtmlString
    {
        $phone = self::payerPhone($payment, '');
        $transactionRef = self::transactionRef($payment, '');

        if ($phone !== '' && $transactionRef !== '') {
            return new HtmlString(
                '<div class="min-w-[8rem]"><p class="font-medium text-slate-800">'.e($phone).'</p>'
                .'<p class="mt-0.5 text-[11px] text-slate-500">'.e($transactionRef).'</p></div>'
            );
        }

        if ($phone !== '') {
            return $phone;
        }

        if ($transactionRef !== '') {
            return $transactionRef;
        }

        $externalRef = trim((string) ($payment->external_ref ?? ''));
        if ($externalRef !== '') {
            return $externalRef;
        }

        return $empty;
    }

    public static function isInternalEzenReference(string $reference): bool
    {
        $reference = strtoupper(trim($reference));

        return str_starts_with($reference, 'EZEN-INV')
            || str_starts_with($reference, 'EZEN-RC');
    }

    private static function resolveTenant(PmPayment $payment): ?\App\Models\PmTenant
    {
        $tenant = $payment->tenant;
        if (! $tenant && $payment->relationLoaded('allocations')) {
            $tenant = $payment->allocations->first()?->invoice?->tenant;
        }

        return $tenant;
    }

    private static function formatPropertyUnitLabel(?\App\Models\Property $property, ?\App\Models\PropertyUnit $unit): string|HtmlString
    {
        if ($unit === null) {
            return '';
        }

        $propertyCode = trim((string) ($property?->code ?? ''));
        $propertyName = trim((string) ($property?->name ?? ''));
        $propertyLabel = $propertyCode !== '' ? $propertyCode : $propertyName;
        $unitLabel = trim((string) ($unit->label ?? ''));

        return self::formatPropertyUnitParts($propertyLabel, $unitLabel);
    }

    private static function formatPropertyUnitParts(string $propertyLabel, string $unitLabel): string|HtmlString
    {
        if ($propertyLabel !== '' && $unitLabel !== '') {
            return new HtmlString(
                '<div class="min-w-[7rem]"><p class="font-medium text-slate-800">'.e($propertyLabel).'</p>'
                .'<p class="mt-0.5 text-[11px] text-slate-500">'.e($unitLabel).'</p></div>'
            );
        }

        if ($propertyLabel !== '') {
            return $propertyLabel;
        }

        return $unitLabel;
    }
}
