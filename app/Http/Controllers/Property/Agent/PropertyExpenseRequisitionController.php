<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmEzenBill;
use App\Models\PmPropertyExpenseRequisition;
use App\Models\PmVendor;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyExpenseRequisitionController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $requisitions = PmPropertyExpenseRequisition::query()
            ->with(['vendor', 'property', 'requester', 'reviewer', 'bill'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('requested_date')->orderByDesc('id')->limit(500)->get();

        return property_view('property.agent.accounting.expense_requisitions', [
            'requisitions' => $requisitions,
            'statusFilter' => $status,
            'vendors' => PmVendor::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_vendor_id' => ['required', 'integer', 'exists:pm_vendors,id'],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:4000'],
            'vendor_invoice_no' => ['nullable', 'string', 'max:191'],
            'requested_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:requested_date'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $vendor = PmVendor::query()->findOrFail((int) $data['pm_vendor_id']);
        $property = Property::query()->findOrFail((int) $data['property_id']);
        $this->assertPropertyInWorkspace($property);

        PmPropertyExpenseRequisition::query()->create([
            ...$data,
            'agent_user_id' => AgentWorkspaceScope::ownerIdForNewRecord() ?: (int) $request->user()->id,
            'requested_by' => (int) $request->user()->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Expense requisition submitted for approval. It will appear as a payable bill after approval.');
    }

    public function approve(Request $request, PmPropertyExpenseRequisition $requisition): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        $bill = DB::transaction(function () use ($request, $requisition, $data): PmEzenBill {
            $locked = PmPropertyExpenseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            abort_unless($locked->status === 'pending' && ! $locked->pm_ezen_bill_id, 422, 'Only pending requisitions can be approved.');

            $property = Property::query()->findOrFail((int) $locked->property_id);
            $this->assertPropertyInWorkspace($property);
            $vendor = PmVendor::query()->findOrFail((int) $locked->pm_vendor_id);
            $landlords = $property->landlords;
            $landlordId = $landlords->count() === 1 ? (int) $landlords->first()->id : null;
            $sourceKey = 'expense-requisition-'.$locked->id;
            $bill = PmEzenBill::query()->firstOrCreate(
                ['source_key' => $sourceKey],
                [
                    'agent_user_id' => (int) $locked->agent_user_id,
                    'ezen_bill_no' => 'ER-REQ-'.$locked->id,
                    'vendor_invoice_no' => $locked->vendor_invoice_no,
                    'bill_date' => $locked->requested_date?->toDateString(),
                    'due_date' => $locked->due_date?->toDateString(),
                    'vendor_name' => (string) $vendor->name,
                    'memo' => trim($locked->title.($locked->description ? ' — '.$locked->description : '')),
                    'total_amount' => (float) $locked->amount,
                    'total_paid' => 0,
                    'amount_due' => (float) $locked->amount,
                    'listing_status' => 'open',
                    'payment_status' => PmEzenBill::STATUS_UNPAID,
                    'property_id' => (int) $property->id,
                    'landlord_id' => $landlordId,
                ],
            );
            $locked->update([
                'status' => 'approved',
                'reviewed_by' => (int) $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
                'pm_ezen_bill_id' => (int) $bill->id,
            ]);

            return $bill;
        });

        return back()->with('success', 'Requisition approved. Payable '.$bill->ezen_bill_no.' is ready for payment.');
    }

    public function reject(Request $request, PmPropertyExpenseRequisition $requisition): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        abort_unless($requisition->status === 'pending', 422, 'Only pending requisitions can be rejected.');
        $requisition->update([
            'status' => 'rejected',
            'reviewed_by' => (int) $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        return back()->with('success', 'Expense requisition rejected.');
    }

    private function assertPropertyInWorkspace(Property $property): void
    {
        if (! AgentWorkspaceScope::shouldApply()) {
            return;
        }
        $allowedIds = array_map('intval', AgentWorkspaceScope::workspaceOwnerIds());
        abort_unless(in_array((int) $property->agent_user_id, $allowedIds, true), 403);
    }
}
