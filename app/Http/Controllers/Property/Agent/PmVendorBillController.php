<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmEzenBill;
use App\Models\PmVendor;
use App\Models\Property;
use App\Support\Property\PropertyFormModal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PmVendorBillController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('pm_ezen_bills'), 503, 'Vendor bills are unavailable until migrations are run.');

        return property_view('property.agent.accounting.payables_bill_create', [
            ...PropertyFormModal::viewContext($request),
            'vendors' => PmVendor::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\Response
    {
        $data = $request->validate([
            'pm_supplier_id' => ['required', 'integer', 'exists:pm_vendors,id'],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'vendor_invoice_no' => ['nullable', 'string', 'max:191'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'memo' => ['required', 'string', 'max:500'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $vendor = PmVendor::query()->findOrFail((int) $data['pm_supplier_id']);
        $property = Property::query()->findOrFail((int) $data['property_id']);
        if (AgentWorkspaceScope::shouldApply()) {
            $workspaceIds = array_map('intval', AgentWorkspaceScope::workspaceOwnerIds());
            abort_unless(in_array((int) $property->agent_user_id, $workspaceIds, true), 403);
        }

        $amount = round((float) $data['total_amount'], 2);
        $landlords = $property->landlords;
        $bill = PmEzenBill::query()->create([
            'agent_user_id' => AgentWorkspaceScope::ownerIdForNewRecord() ?: (int) $request->user()->id,
            'source_key' => 'manual-'.Str::uuid(),
            'ezen_bill_no' => 'MAN-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
            'vendor_invoice_no' => $data['vendor_invoice_no'] ?? null,
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'vendor_name' => (string) $vendor->name,
            'memo' => $data['memo'],
            'total_amount' => $amount,
            'total_paid' => 0,
            'amount_due' => $amount,
            'listing_status' => 'open',
            'payment_status' => PmEzenBill::STATUS_UNPAID,
            'pm_supplier_id' => (int) $vendor->id,
            'property_id' => (int) $property->id,
            'landlord_id' => $landlords->count() === 1 ? (int) $landlords->first()->id : null,
        ]);

        $message = 'Bill '.$bill->ezen_bill_no.' created and added to Accounts Payable.';
        if (PropertyFormModal::fromModal($request)) {
            return response()->view('property.agent.partials.form_modal_success', ['message' => $message]);
        }

        return redirect()->route('property.accounting.payables.accounts_payable')->with('success', $message);
    }
}
