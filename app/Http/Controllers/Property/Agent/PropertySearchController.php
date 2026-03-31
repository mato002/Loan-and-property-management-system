<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PropertySearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $q = preg_replace('/\s+/', ' ', $q ?? '') ?? '';

        $tenants = collect();
        $properties = collect();
        $units = collect();
        $invoices = collect();
        $payments = collect();

        if ($q !== '') {
            if (Schema::hasTable('pm_tenants')) {
                $tenants = PmTenant::query()
                    ->where(function ($b) use ($q) {
                        $b->where('name', 'like', '%'.$q.'%')
                            ->orWhere('phone', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%')
                            ->orWhere('account_number', 'like', '%'.$q.'%');
                    })
                    ->orderBy('name')
                    ->limit(12)
                    ->get(['id', 'name', 'phone', 'email', 'account_number']);
            }

            if (Schema::hasTable('properties')) {
                $properties = Property::query()
                    ->where(function ($b) use ($q) {
                        $b->where('name', 'like', '%'.$q.'%')
                            ->orWhere('code', 'like', '%'.$q.'%')
                            ->orWhere('city', 'like', '%'.$q.'%')
                            ->orWhere('address_line', 'like', '%'.$q.'%');
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'code', 'city', 'address_line']);
            }

            if (Schema::hasTable('property_units')) {
                $units = PropertyUnit::query()
                    ->with('property:id,name')
                    ->where(function ($b) use ($q) {
                        $b->where('label', 'like', '%'.$q.'%')
                            ->orWhere('unit_type', 'like', '%'.$q.'%')
                            ->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%'.$q.'%'));
                    })
                    ->orderBy('property_id')
                    ->orderBy('label')
                    ->limit(12)
                    ->get(['id', 'property_id', 'label', 'unit_type', 'status', 'rent_amount']);
            }

            if (Schema::hasTable('pm_invoices')) {
                $invoices = PmInvoice::query()
                    ->with(['tenant:id,name', 'unit:id,label,property_id', 'unit.property:id,name'])
                    ->where(function ($b) use ($q) {
                        $b->where('invoice_no', 'like', '%'.$q.'%')
                            ->orWhere('description', 'like', '%'.$q.'%')
                            ->orWhere('billing_period', 'like', '%'.$q.'%')
                            ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))
                            ->orWhereHas('unit', fn ($u) => $u->where('label', 'like', '%'.$q.'%'));
                    })
                    ->orderByDesc('issue_date')
                    ->limit(12)
                    ->get();
            }

            if (Schema::hasTable('pm_payments')) {
                $payments = PmPayment::query()
                    ->with('tenant:id,name,phone')
                    ->where(function ($b) use ($q) {
                        $b->where('external_ref', 'like', '%'.$q.'%')
                            ->orWhere('channel', 'like', '%'.$q.'%')
                            ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'));
                    })
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get(['id', 'pm_tenant_id', 'channel', 'amount', 'external_ref', 'paid_at', 'status', 'meta']);
            }
        }

        return view('property.agent.search.index', [
            'q' => $q,
            'tenants' => $tenants,
            'properties' => $properties,
            'units' => $units,
            'invoices' => $invoices,
            'payments' => $payments,
        ]);
    }
}

