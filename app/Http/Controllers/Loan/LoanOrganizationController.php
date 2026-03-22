<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookLoan;
use App\Models\LoanBranch;
use App\Models\LoanRegion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        LoanBranch::query()->create($validated);

        return redirect()
            ->route('loan.branches.index')
            ->with('status', __('Branch saved.'));
    }

    public function branchesEdit(LoanBranch $loan_branch): View
    {
        $regions = LoanRegion::query()->orderBy('name')->get();

        return view('loan.organization.branches.edit', [
            'title' => 'Edit branch',
            'subtitle' => $loan_branch->name,
            'branch' => $loan_branch,
            'regions' => $regions,
        ]);
    }

    public function branchesUpdate(Request $request, LoanBranch $loan_branch): RedirectResponse
    {
        $validated = $this->validatedBranch($request, $loan_branch->id);
        $loan_branch->update($validated);

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

        $rows = DB::table('loan_book_loans as l')
            ->leftJoin('loan_branches as b', 'l.loan_branch_id', '=', 'b.id')
            ->leftJoin('loan_regions as r', 'b.loan_region_id', '=', 'r.id')
            ->selectRaw(
                'COALESCE(b.name, NULLIF(TRIM(l.branch), \'\'), ?) as branch_label, '.
                'r.name as region_name, '.
                'COUNT(*) as loan_count, '.
                'COALESCE(SUM(l.principal), 0) as total_principal, '.
                'COALESCE(SUM(l.balance), 0) as total_balance, '.
                'SUM(CASE WHEN l.status = ? THEN 1 ELSE 0 END) as active_count',
                [$unassigned, $activeStatus]
            )
            ->groupByRaw('COALESCE(b.name, NULLIF(TRIM(l.branch), \'\'), ?), r.name', [$unassigned])
            ->orderBy('branch_label')
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

    private function validatedBranch(Request $request, ?int $ignoreId = null): array
    {
        $uniqueCode = 'nullable|string|max:40|unique:loan_branches,code';
        if ($ignoreId !== null) {
            $uniqueCode .= ','.$ignoreId;
        }

        $validated = $request->validate([
            'loan_region_id' => ['required', 'exists:loan_regions,id'],
            'code' => [$uniqueCode],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
