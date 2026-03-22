<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsLoanSize;
use App\Models\AnalyticsPerformanceRecord;
use App\Models\AnalyticsPeriodTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanBusinessAnalyticsController extends Controller
{
    /* ---------- Loan sizes ---------- */

    public function loanSizesIndex(): View
    {
        $sizes = AnalyticsLoanSize::query()
            ->orderBy('sort_order')
            ->orderBy('min_principal')
            ->paginate(20);

        return view('loan.analytics.loan-sizes.index', compact('sizes'));
    }

    public function loanSizesCreate(): View
    {
        return view('loan.analytics.loan-sizes.create');
    }

    public function loanSizesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'min_principal' => ['required', 'numeric', 'min:0'],
            'max_principal' => ['nullable', 'numeric', 'min:0', 'gt:min_principal'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        AnalyticsLoanSize::create([
            'label' => $validated['label'],
            'min_principal' => $validated['min_principal'],
            'max_principal' => isset($validated['max_principal']) && $validated['max_principal'] !== '' ? $validated['max_principal'] : null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('loan.analytics.loan_sizes')
            ->with('status', 'Loan size band saved.');
    }

    public function loanSizesEdit(AnalyticsLoanSize $analytics_loan_size): View
    {
        return view('loan.analytics.loan-sizes.edit', ['size' => $analytics_loan_size]);
    }

    public function loanSizesUpdate(Request $request, AnalyticsLoanSize $analytics_loan_size): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'min_principal' => ['required', 'numeric', 'min:0'],
            'max_principal' => ['nullable', 'numeric', 'min:0', 'gt:min_principal'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $analytics_loan_size->update([
            'label' => $validated['label'],
            'min_principal' => $validated['min_principal'],
            'max_principal' => isset($validated['max_principal']) && $validated['max_principal'] !== '' ? $validated['max_principal'] : null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('loan.analytics.loan_sizes')
            ->with('status', 'Loan size band updated.');
    }

    public function loanSizesDestroy(AnalyticsLoanSize $analytics_loan_size): RedirectResponse
    {
        $analytics_loan_size->delete();

        return redirect()
            ->route('loan.analytics.loan_sizes')
            ->with('status', 'Loan size band removed.');
    }

    /* ---------- Targets & accruals ---------- */

    public function targetsIndex(): View
    {
        $targets = AnalyticsPeriodTarget::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderBy('branch')
            ->paginate(20);

        return view('loan.analytics.targets.index', compact('targets'));
    }

    public function targetsCreate(): View
    {
        return view('loan.analytics.targets.create');
    }

    public function targetsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:120'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('analytics_period_targets')->where(function ($query) use ($request) {
                    return $query->where('branch', $request->input('branch'))
                        ->where('period_year', (int) $request->input('period_year'));
                }),
            ],
            'disbursement_target' => ['nullable', 'numeric', 'min:0'],
            'collection_target' => ['nullable', 'numeric', 'min:0'],
            'accrual_target' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AnalyticsPeriodTarget::create([
            'branch' => $validated['branch'],
            'period_year' => $validated['period_year'],
            'period_month' => $validated['period_month'],
            'disbursement_target' => $validated['disbursement_target'] ?? 0,
            'collection_target' => $validated['collection_target'] ?? 0,
            'accrual_target' => $validated['accrual_target'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('loan.analytics.targets')
            ->with('status', 'Period target saved.');
    }

    public function targetsEdit(AnalyticsPeriodTarget $analytics_period_target): View
    {
        return view('loan.analytics.targets.edit', ['target' => $analytics_period_target]);
    }

    public function targetsUpdate(Request $request, AnalyticsPeriodTarget $analytics_period_target): RedirectResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:120'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('analytics_period_targets')
                    ->where(function ($query) use ($request) {
                        return $query->where('branch', $request->input('branch'))
                            ->where('period_year', (int) $request->input('period_year'));
                    })
                    ->ignore($analytics_period_target->id),
            ],
            'disbursement_target' => ['nullable', 'numeric', 'min:0'],
            'collection_target' => ['nullable', 'numeric', 'min:0'],
            'accrual_target' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $analytics_period_target->update([
            'branch' => $validated['branch'],
            'period_year' => $validated['period_year'],
            'period_month' => $validated['period_month'],
            'disbursement_target' => $validated['disbursement_target'] ?? 0,
            'collection_target' => $validated['collection_target'] ?? 0,
            'accrual_target' => $validated['accrual_target'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('loan.analytics.targets')
            ->with('status', 'Period target updated.');
    }

    public function targetsDestroy(AnalyticsPeriodTarget $analytics_period_target): RedirectResponse
    {
        $analytics_period_target->delete();

        return redirect()
            ->route('loan.analytics.targets')
            ->with('status', 'Period target removed.');
    }

    /* ---------- Business performance ---------- */

    public function performanceIndex(): View
    {
        $records = AnalyticsPerformanceRecord::query()
            ->orderByDesc('record_date')
            ->orderBy('branch')
            ->paginate(20);

        return view('loan.analytics.performance.index', compact('records'));
    }

    public function performanceCreate(): View
    {
        return view('loan.analytics.performance.create');
    }

    public function performanceStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'record_date' => ['required', 'date'],
            'branch' => ['nullable', 'string', 'max:120'],
            'total_outstanding' => ['nullable', 'numeric', 'min:0'],
            'disbursements_period' => ['nullable', 'numeric', 'min:0'],
            'collections_period' => ['nullable', 'numeric', 'min:0'],
            'npl_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active_borrowers_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AnalyticsPerformanceRecord::create([
            'record_date' => $validated['record_date'],
            'branch' => isset($validated['branch']) && $validated['branch'] !== '' ? $validated['branch'] : null,
            'total_outstanding' => $validated['total_outstanding'] ?? null,
            'disbursements_period' => $validated['disbursements_period'] ?? null,
            'collections_period' => $validated['collections_period'] ?? null,
            'npl_rate' => $validated['npl_rate'] ?? null,
            'active_borrowers_count' => $validated['active_borrowers_count'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('loan.analytics.performance')
            ->with('status', 'Performance snapshot saved.');
    }

    public function performanceEdit(AnalyticsPerformanceRecord $analytics_performance_record): View
    {
        return view('loan.analytics.performance.edit', ['record' => $analytics_performance_record]);
    }

    public function performanceUpdate(Request $request, AnalyticsPerformanceRecord $analytics_performance_record): RedirectResponse
    {
        $validated = $request->validate([
            'record_date' => ['required', 'date'],
            'branch' => ['nullable', 'string', 'max:120'],
            'total_outstanding' => ['nullable', 'numeric', 'min:0'],
            'disbursements_period' => ['nullable', 'numeric', 'min:0'],
            'collections_period' => ['nullable', 'numeric', 'min:0'],
            'npl_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active_borrowers_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $analytics_performance_record->update([
            'record_date' => $validated['record_date'],
            'branch' => isset($validated['branch']) && $validated['branch'] !== '' ? $validated['branch'] : null,
            'total_outstanding' => $validated['total_outstanding'] ?? null,
            'disbursements_period' => $validated['disbursements_period'] ?? null,
            'collections_period' => $validated['collections_period'] ?? null,
            'npl_rate' => $validated['npl_rate'] ?? null,
            'active_borrowers_count' => $validated['active_borrowers_count'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('loan.analytics.performance')
            ->with('status', 'Performance snapshot updated.');
    }

    public function performanceDestroy(AnalyticsPerformanceRecord $analytics_performance_record): RedirectResponse
    {
        $analytics_performance_record->delete();

        return redirect()
            ->route('loan.analytics.performance')
            ->with('status', 'Performance snapshot removed.');
    }
}
