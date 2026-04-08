<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Support\Facades\DB;

class PropertyPaymentSettlementService
{
    public function fail(PmPayment $payment, ?string $externalRef, ?string $message, string $source): PmPayment
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['callback'] = [
            'source' => $source,
            'status' => 'failed',
            'message' => $message,
            'received_at' => now()->toIso8601String(),
        ];

        $payment->update([
            'status' => PmPayment::STATUS_FAILED,
            'external_ref' => $externalRef ?: $payment->external_ref,
            'meta' => $meta,
        ]);

        return $payment->fresh();
    }

    public function complete(
        PmPayment $payment,
        ?string $externalRef,
        mixed $paidAt,
        ?string $message,
        string $source,
        ?float $paidAmount = null,
    ): PmPayment
    {
        // If the provider callback includes a definitive amount, prefer it.
        // This avoids cases where the initiated amount differs from the confirmed amount.
        if ($paidAmount !== null && $paidAmount > 0) {
            $payment->amount = $paidAmount;
        }

        $payment->update([
            'status' => PmPayment::STATUS_COMPLETED,
            'paid_at' => $paidAt ?: now(),
            'external_ref' => $externalRef ?: $payment->external_ref,
            'meta' => array_merge(is_array($payment->meta) ? $payment->meta : [], [
                'callback' => [
                    'source' => $source,
                    'status' => 'success',
                    'message' => $message,
                    'amount' => $paidAmount,
                    'received_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $scope = (string) data_get($payment->meta, 'bill_scope', 'all');
        $invoiceType = match (strtolower(trim($scope))) {
            'rent' => PmInvoice::TYPE_RENT,
            'water' => PmInvoice::TYPE_WATER,
            default => null,
        };

        $remaining = (float) $payment->amount;
        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $payment->pm_tenant_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->when($invoiceType !== null, fn ($q) => $q->where('invoice_type', $invoiceType))
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($invoiceRemaining <= 0) {
                continue;
            }

            $allocation = min($remaining, $invoiceRemaining);
            PmPaymentAllocation::query()->create([
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => $invoice->id,
                'amount' => $allocation,
            ]);

            $invoice->amount_paid = (float) $invoice->amount_paid + $allocation;
            $invoice->save();
            $invoice->refreshComputedStatus();
            $remaining -= $allocation;
        }

        $payment->load('allocations.invoice.unit');
        PropertyAccountingPostingService::postPaymentReceived($payment, null);
        $this->postLandlordLedgerCredits($payment);

        return $payment->fresh();
    }

    public function settlePending(
        int $paymentId,
        string $status,
        ?string $externalRef,
        mixed $paidAt,
        ?string $message,
        string $source,
        ?float $paidAmount = null,
    ): PmPayment {
        return DB::transaction(function () use ($paymentId, $status, $externalRef, $paidAt, $message, $source, $paidAmount) {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->lockForUpdate()->findOrFail($paymentId);

            // Idempotency
            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return $payment;
            }

            if ($status === 'failed') {
                return $this->fail($payment, $externalRef, $message, $source);
            }

            return $this->complete($payment, $externalRef, $paidAt, $message, $source, $paidAmount);
        });
    }

    private function postLandlordLedgerCredits(PmPayment $payment): void
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED) {
            return;
        }

        // Idempotency: don't post landlord credits twice for the same payment.
        $already = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', $payment->id)
            ->exists();
        if ($already) {
            return;
        }

        $allocs = $payment->allocations ?? collect();
        if ($allocs->isEmpty()) {
            return;
        }

        // Group allocated amounts by property_id
        $byProperty = [];
        foreach ($allocs as $a) {
            $propertyId = (int) optional(optional($a->invoice)->unit)->property_id;
            if ($propertyId <= 0) {
                continue;
            }
            $byProperty[$propertyId] = ($byProperty[$propertyId] ?? 0.0) + (float) $a->amount;
        }
        if ($byProperty === []) {
            return;
        }

        // Commission settings
        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $defaultPct = is_numeric($defaultRaw) ? (float) $defaultRaw : 10.0;
        $defaultPct = max(0.0, $defaultPct);
        $overrideRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($overrideRaw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        $paymentRef = $payment->external_ref ?: ('PAY-'.$payment->id);
        $occurredAt = $payment->paid_at ?? $payment->created_at ?? now();

        foreach ($byProperty as $propertyId => $grossCollected) {
            $property = Property::query()->find($propertyId);
            $commissionPct = is_numeric($overrides[(string) $propertyId] ?? null)
                ? max(0.0, (float) $overrides[(string) $propertyId])
                : $defaultPct;

            $commission = $grossCollected * ($commissionPct / 100);
            $netToOwners = max(0.0, $grossCollected - $commission);
            if ($netToOwners <= 0) {
                continue;
            }

            // Split net-to-owners by ownership percent
            $links = DB::table('property_landlord')
                ->where('property_id', $propertyId)
                ->get(['user_id', 'ownership_percent']);

            foreach ($links as $link) {
                $uid = (int) ($link->user_id ?? 0);
                $ownershipPct = (float) ($link->ownership_percent ?? 0);
                if ($uid <= 0 || $ownershipPct <= 0) {
                    continue;
                }
                $share = $netToOwners * ($ownershipPct / 100);
                if ($share <= 0) {
                    continue;
                }

                $user = User::query()->find($uid);
                if (! $user) {
                    continue;
                }

                LandlordLedger::post(
                    $user,
                    PmLandlordLedgerEntry::DIRECTION_CREDIT,
                    (float) $share,
                    'Rent collected '.$paymentRef.' (net after '.$commissionPct.'% commission)',
                    $property,
                    'pm_payment',
                    (int) $payment->id,
                    $occurredAt
                );
            }
        }
    }
}

