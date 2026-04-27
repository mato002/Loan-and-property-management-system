<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookLoan;
use App\Models\LoanBranch;
use App\Models\LoanBranchRegionChange;
use App\Models\LoanRegion;
use App\Models\LoanSystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanOrganizationController extends Controller
{
    /* ---------- Regions ---------- */

    public function regionsIndex(): View
    {
        $regions = LoanRegion::query()
            ->withCount('branches')
            ->orderBy('name')
            ->paginate(20);

        return view('loan.organization.regions.index', [
            'title' => 'Regions',
            'subtitle' => 'Geographic or administrative groupings for branches.',
            'regions' => $regions,
        ]);
    }

    public function regionsCreate(): View
    {
        return view('loan.organization.regions.create', [
            'title' => 'Create region',
            'subtitle' => 'Add a new region before attaching branches.',
        ]);
    }

    public function regionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:loan_regions,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        LoanRegion::query()->create($validated);

        return redirect()
            ->route('loan.regions.index')
            ->with('status', __('Region saved.'));
    }

    public function regionsEdit(LoanRegion $loan_region): View
    {
        return view('loan.organization.regions.edit', [
            'title' => 'Edit region',
            'subtitle' => $loan_region->name,
            'region' => $loan_region,
        ]);
    }

    public function regionsUpdate(Request $request, LoanRegion $loan_region): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:loan_regions,code,'.$loan_region->id],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $loan_region->update($validated);

        return redirect()
            ->route('loan.regions.index')
            ->with('status', __('Region updated.'));
    }

    public function regionsDestroy(LoanRegion $loan_region): RedirectResponse
    {
        if ($loan_region->branches()->exists()) {
            return redirect()
                ->route('loan.regions.index')
                ->with('error', __('Remove or reassign branches before deleting this region.'));
        }

        $loan_region->delete();

        return redirect()
            ->route('loan.regions.index')
            ->with('status', __('Region removed.'));
    }

    /* ---------- Branches ---------- */

    public function branchesIndex(): View
    {
        $branches = LoanBranch::query()
            ->with('region')
            ->withCount('loans')
            ->orderBy('name')
            ->paginate(20);

        return view('loan.organization.branches.index', [
            'title' => 'Branches',
            'subtitle' => 'Outlets linked to regions and LoanBook accounts.',
            'branches' => $branches,
            'showStructureHistory' => $this->shouldTrackStructureHistory(),
        ]);
    }

    public function branchesCreate(): View
    {
        $regions = LoanRegion::query()->orderBy('name')->get();

        return view('loan.organization.branches.create', [
            'title' => 'Add branch',
            'subtitle' => 'Register an outlet under a region.',
            'regions' => $regions,
        ]);
    }

    public function branchesStore(Request $request): RedirectResponse
    {
        $validated = $this->validatedBranch($request);
        $branch = LoanBranch::query()->create($validated);

        if ($this->shouldTrackStructureHistory()) {
            LoanBranchRegionChange::query()->create([
                'loan_branch_id' => $branch->id,
                'from_loan_region_id' => null,
                'to_loan_region_id' => $branch->loan_region_id,
                'requested_by_user_id' => $request->user()?->id,
                'approved_by_user_id' => $request->user()?->id,
                'status' => LoanBranchRegionChange::STATUS_APPROVED,
                'effective_at' => now(),
                'approved_at' => now(),
                'reason' => 'Initial branch assignment.',
                'meta' => ['source' => 'branch_create'],
            ]);
        }

        return redirect()
            ->route('loan.branches.index')
            ->with('status', __('Branch saved.'));
    }

    public function branchesEdit(LoanBranch $loan_branch): View
    {
        $regions = LoanRegion::query()->orderBy('name')->get();
        $requiresApproval = LoanSystemSetting::getValue('org_structure_change_requires_approval', '0') === '1';

        return view('loan.organization.branches.edit', [
            'title' => 'Edit branch',
            'subtitle' => $loan_branch->name,
            'branch' => $loan_branch,
            'regions' => $regions,
            'requiresApproval' => $requiresApproval,
        ]);
    }

    public function branchesUpdate(Request $request, LoanBranch $loan_branch): RedirectResponse
    {
        $validated = $this->validatedBranch($request, $loan_branch->id);
        $currentRegionId = (int) $loan_branch->loan_region_id;
        $targetRegionId = (int) $validated['loan_region_id'];
        $regionChanged = $currentRegionId !== $targetRegionId;
        $requiresApproval = LoanSystemSetting::getValue('org_structure_change_requires_approval', '0') === '1';

        if ($regionChanged && $requiresApproval) {
            $requestData = $request->validate([
                'change_effective_at' => ['nullable', 'date'],
                'change_reason' => ['nullable', 'string', 'max:1000'],
            ]);

            // Keep non-structural updates immediate, but queue region reassignment.
            $nonStructural = $validated;
            $nonStructural['loan_region_id'] = $loan_branch->loan_region_id;
            $loan_branch->update($nonStructural);

            LoanBranchRegionChange::query()->create([
                'loan_branch_id' => $loan_branch->id,
                'from_loan_region_id' => $currentRegionId,
                'to_loan_region_id' => $targetRegionId,
                'requested_by_user_id' => $request->user()?->id,
                'status' => LoanBranchRegionChange::STATUS_PENDING,
                'effective_at' => filled($requestData['change_effective_at'] ?? null) ? $requestData['change_effective_at'] : now(),
                'reason' => filled($requestData['change_reason'] ?? null) ? trim((string) $requestData['change_reason']) : null,
                'meta' => ['source' => 'branch_edit'],
            ]);

            return redirect()
                ->route('loan.branches.index')
                ->with('status', __('Branch details saved. Region reassignment queued for approval.'));
        }

        $loan_branch->update($validated);
        if ($regionChanged && $this->shouldTrackStructureHistory()) {
            LoanBranchRegionChange::query()->create([
                'loan_branch_id' => $loan_branch->id,
                'from_loan_region_id' => $currentRegionId,
                'to_loan_region_id' => $targetRegionId,
                'requested_by_user_id' => $request->user()?->id,
                'approved_by_user_id' => $request->user()?->id,
                'status' => LoanBranchRegionChange::STATUS_APPROVED,
                'effective_at' => now(),
                'approved_at' => now(),
                'reason' => 'Immediate reassignment (approval not required).',
                'meta' => ['source' => 'branch_edit'],
            ]);
        }

        return redirect()
            ->route('loan.branches.index')
            ->with('status', __('Branch updated.'));
    }

    public function branchesDestroy(LoanBranch $loan_branch): RedirectResponse
    {
        if ($loan_branch->loans()->exists()) {
            return redirect()
                ->route('loan.branches.index')
                ->with('error', __('Reassign loans before deleting this branch.'));
        }

        $loan_branch->delete();

        return redirect()
            ->route('loan.branches.index')
            ->with('status', __('Branch removed.'));
    }

    public function branchLoanSummary(): View
    {
        $unassigned = 'Unassigned';
        $activeStatus = LoanBookLoan::STATUS_ACTIVE;

        // Identical SQL fragment for SELECT and GROUP BY (required by MySQL only_full_group_by).
        // Use PDO-quoted literals: ? bindings on groupByRaw are not always merged reliably, which
        // produced `Unassigned` / `active` as bare identifiers in the executed SQL.
        $pdo = DB::connection()->getPdo();
        $unassignedSql = $pdo->quote($unassigned);
        $activeSql = $pdo->quote($activeStatus);
        $branchKeySql = 'COALESCE(b.name, NULLIF(TRIM(l.branch), \'\'), '.$unassignedSql.')';

        $rows = DB::table('loan_book_loans as l')
            ->leftJoin('loan_branches as b', 'l.loan_branch_id', '=', 'b.id')
            ->leftJoin('loan_regions as r', 'b.loan_region_id', '=', 'r.id')
            ->selectRaw(
                $branchKeySql.' as branch_label, '.
                'MAX(r.name) as region_name, '.
                'COUNT(*) as loan_count, '.
                'COALESCE(SUM(l.principal), 0) as total_principal, '.
                'COALESCE(SUM(l.balance), 0) as total_balance, '.
                'SUM(CASE WHEN l.status = '.$activeSql.' THEN 1 ELSE 0 END) as active_count'
            )
            // MySQL ONLY_FULL_GROUP_BY can still reject grouping by a computed alias expression
            // when the expression references non-aggregated columns. Group by the underlying
            // columns instead to keep it portable across MySQL modes/versions.
            ->groupBy('b.name', 'l.branch')
            ->get();

        $totals = [
            'loans' => (int) $rows->sum('loan_count'),
            'principal' => (float) $rows->sum('total_principal'),
            'balance' => (float) $rows->sum('total_balance'),
        ];

        return view('loan.organization.branches.loan_summary', [
            'title' => 'Loan summary by branch',
            'subtitle' => 'Portfolio totals grouped by directory branch or legacy branch label.',
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function branchChangesIndex(): View
    {
        abort_unless($this->shouldTrackStructureHistory(), 404);

        $rows = LoanBranchRegionChange::query()
            ->with(['branch', 'fromRegion', 'toRegion'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('loan.organization.branches.changes', [
            'title' => 'Structure change requests',
            'subtitle' => 'Pending and historical branch-region reassignments.',
            'rows' => $rows,
        ]);
    }

    public function branchChangesApprove(Request $request, LoanBranchRegionChange $change): RedirectResponse
    {
        abort_unless($this->shouldTrackStructureHistory(), 404);

        if ($change->status !== LoanBranchRegionChange::STATUS_PENDING) {
            return back()->with('error', __('Only pending requests can be approved.'));
        }

        DB::transaction(function () use ($change, $request): void {
            $branch = LoanBranch::query()->findOrFail($change->loan_branch_id);
            $branch->update(['loan_region_id' => $change->to_loan_region_id]);

            $change->update([
                'status' => LoanBranchRegionChange::STATUS_APPROVED,
                'approved_by_user_id' => $request->user()?->id,
                'approved_at' => now(),
            ]);
        });

        return back()->with('status', __('Structure change approved.'));
    }

    public function branchChangesReject(Request $request, LoanBranchRegionChange $change): RedirectResponse
    {
        abort_unless($this->shouldTrackStructureHistory(), 404);

        if ($change->status !== LoanBranchRegionChange::STATUS_PENDING) {
            return back()->with('error', __('Only pending requests can be rejected.'));
        }

        $validated = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $change->update([
            'status' => LoanBranchRegionChange::STATUS_REJECTED,
            'rejected_by_user_id' => $request->user()?->id,
            'rejected_at' => now(),
            'reason' => filled($validated['reject_reason'] ?? null) ? trim((string) $validated['reject_reason']) : $change->reason,
        ]);

        return back()->with('status', __('Structure change rejected.'));
    }

    private function validatedBranch(Request $request, ?int $ignoreId = null): array
    {
        $codeRules = ['nullable', 'string', 'max:40', Rule::unique('loan_branches', 'code')];
        if ($ignoreId !== null) {
            $codeRules[3] = Rule::unique('loan_branches', 'code')->ignore($ignoreId);
        }

        $validated = $request->validate([
            'loan_region_id' => ['required', 'exists:loan_regions,id'],
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function shouldTrackStructureHistory(): bool
    {
        return LoanSystemSetting::getValue('org_structure_effective_dated_history', '1') === '1';
    }
}
