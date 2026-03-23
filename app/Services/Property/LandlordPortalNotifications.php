<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Carbon;

final class LandlordPortalNotifications
{
    /**
     * @return list<array{at: \Illuminate\Support\Carbon, title: string, body: string, tone: string}>
     */
    public static function recent(User $user, int $limit = 25): array
    {
        $propIds = $user->landlordProperties()->pluck('properties.id');
        if ($propIds->isEmpty()) {
            return [];
        }

        $unitIds = PropertyUnit::query()->whereIn('property_id', $propIds)->pluck('id');
        $items = [];

        $maint = PmMaintenanceRequest::query()
            ->with(['unit.property'])
            ->whereIn('property_unit_id', $unitIds)
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();

        foreach ($maint as $r) {
            $items[] = [
                'at' => Carbon::parse($r->updated_at),
                'title' => 'Maintenance — '.$r->unit->property->name.'/'.$r->unit->label,
                'body' => ucfirst(str_replace('_', ' ', $r->status)).' · '.$r->category,
                'tone' => 'slate',
            ];
        }

        $leaseUnitIds = $unitIds->all();
        if ($leaseUnitIds !== []) {
            $leases = PmLease::query()
                ->where('status', PmLease::STATUS_ACTIVE)
                ->where('end_date', '<=', now()->addDays(60))
                ->where('end_date', '>=', now()->subDay())
                ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $leaseUnitIds))
                ->with(['units.property'])
                ->orderBy('end_date')
                ->limit(15)
                ->get();

            foreach ($leases as $lease) {
                $label = $lease->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ');
                $items[] = [
                    'at' => now(),
                    'title' => 'Lease ending soon',
                    'body' => ($label !== '' ? $label.' — ' : '').'Ends '.$lease->end_date->format('Y-m-d'),
                    'tone' => 'amber',
                ];
            }
        }

        $payments = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('paid_at', '>=', now()->subDays(21))
            ->whereHas('allocations.invoice', fn ($q) => $q->whereIn('property_unit_id', $unitIds))
            ->with(['tenant', 'allocations.invoice.unit.property'])
            ->orderByDesc('paid_at')
            ->limit(12)
            ->get();

        foreach ($payments as $p) {
            $inv = $p->allocations->first()?->invoice;
            $place = $inv?->unit
                ? $inv->unit->property->name.'/'.$inv->unit->label
                : 'Portfolio';
            $items[] = [
                'at' => Carbon::parse($p->paid_at ?? $p->updated_at),
                'title' => 'Rent collected',
                'body' => PropertyMoney::kes((float) $p->amount).' · '.$place.($p->tenant ? ' · '.$p->tenant->name : ''),
                'tone' => 'emerald',
            ];
        }

        $overdue = PmInvoice::query()
            ->whereIn('property_unit_id', $unitIds)
            ->where('status', PmInvoice::STATUS_OVERDUE)
            ->with(['unit.property', 'tenant'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        foreach ($overdue as $inv) {
            $items[] = [
                'at' => Carbon::parse($inv->updated_at),
                'title' => 'Overdue invoice',
                'body' => $inv->invoice_no.' · '.$inv->unit->property->name.'/'.$inv->unit->label.' · balance '.PropertyMoney::kes((float) $inv->amount - (float) $inv->amount_paid),
                'tone' => 'rose',
            ];
        }

        usort($items, static fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($items, 0, $limit);
    }
}
