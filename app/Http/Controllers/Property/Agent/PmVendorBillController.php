<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmEzenBill;
use App\Models\PmEzenPaymentVoucher;
use App\Models\PmVendor;
use App\Models\Property;
use App\Support\Property\PropertyFormModal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PmVendorBillController extends Controller
{
    public function linkVoucherForm(Request $request, PmEzenBill $bill): View
    {
        $vouchers = PmEzenPaymentVoucher::query()
            ->where('category', PmEzenPaymentVoucher::CATEGORY_EXPENSE)
            ->whereNull('pm_ezen_bill_id')
            ->where('amount', '>', 0)
            ->where('amount', '<=', (float) $bill->amount_due)
            ->whereRaw("LOWER(TRIM(COALESCE(NULLIF(paid_to, ''), payee_name, ''))) = ?", [mb_strtolower(trim((string) $bill->vendor_name))])
            ->where(function ($query) use ($bill): void {
                $query->whereNull('property_id');
                if ($bill->property_id) {
                    $query->orWhere('property_id', (int) $bill->property_id);
                }
            })
            ->with(['property', 'lines.property'])
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return property_view('property.agent.accounting.payables_link_voucher', [
            ...PropertyFormModal::viewContext($request),
            'bill' => $bill,
            'vouchers' => $vouchers,
        ]);
    }

    public function linkVoucher(Request $request, PmEzenBill $bill): RedirectResponse|\Illuminate\Http\Response
    {
        $data = $request->validate([
            'voucher_id' => ['required', 'integer', 'exists:pm_ezen_payment_vouchers,id'],
        ]);

        $message = DB::transaction(function () use ($request, $bill, $data): string {
            $lockedBill = PmEzenBill::query()->lockForUpdate()->findOrFail($bill->id);
            $voucher = PmEzenPaymentVoucher::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail((int) $data['voucher_id']);

            if ($voucher->category !== PmEzenPaymentVoucher::CATEGORY_EXPENSE || $voucher->pm_ezen_bill_id) {
                throw ValidationException::withMessages(['voucher_id' => 'Choose an unlinked expense voucher.']);
            }
            $voucherPayee = mb_strtolower(trim((string) ($voucher->paid_to ?: $voucher->payee_name)));
            if ($voucherPayee !== mb_strtolower(trim((string) $lockedBill->vendor_name))) {
                throw ValidationException::withMessages(['voucher_id' => 'The voucher payee does not match this bill’s vendor.']);
            }
            if ($lockedBill->property_id && $voucher->property_id && (int) $lockedBill->property_id !== (int) $voucher->property_id) {
                throw ValidationException::withMessages(['voucher_id' => 'The voucher belongs to a different property.']);
            }
            if ($lockedBill->property_id && $voucher->lines->contains(fn ($line) => $line->property_id && (int) $line->property_id !== (int) $lockedBill->property_id)) {
                throw ValidationException::withMessages(['voucher_id' => 'The voucher includes lines for a different property.']);
            }

            $amount = round((float) $voucher->amount, 2);
            $due = round((float) $lockedBill->amount_due, 2);
            if ($amount <= 0 || $amount - $due > 0.009) {
                throw ValidationException::withMessages(['voucher_id' => 'This voucher exceeds the bill’s remaining balance.']);
            }

            $voucher->update(['pm_ezen_bill_id' => (int) $lockedBill->id]);
            $paid = round((float) $lockedBill->total_paid + $amount, 2);
            $remaining = round(max(0, (float) $lockedBill->total_amount - $paid), 2);
            $lockedBill->update([
                'total_paid' => $paid,
                'amount_due' => $remaining,
                'payment_status' => $remaining <= 0.009 ? PmEzenBill::STATUS_PAID : PmEzenBill::STATUS_PARTIAL,
                'listing_status' => $remaining <= 0.009 ? 'closed' : 'open',
            ]);

            return 'Voucher '.$voucher->ezen_voucher_no.' linked to bill '.$lockedBill->ezen_bill_no.'; the existing expense was not posted again.';
        });

        if (PropertyFormModal::fromModal($request)) {
            return response()->view('property.agent.partials.form_modal_success', ['message' => $message]);
        }

        return redirect()->route('property.accounting.payables.accounts_payable')->with('success', $message);
    }

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
