<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanHrController extends Controller
{
    public function dashboard(): RedirectResponse
    {
        return redirect()->route('loan.hr.section', ['section' => 'communication']);
    }

    public function section(string $section): View|RedirectResponse
    {
        $view = 'loan.hr.'.$section;
        if (! view()->exists($view)) {
            return redirect()
                ->route('loan.employees.index')
                ->with('error', 'That HR workspace section is not available yet.');
        }

        return view($view, $this->sectionViewData($section));
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        return back()->with('error', 'HR document uploads are not configured yet.');
    }

    public function destroyDocument(int $employeeDocument): RedirectResponse
    {
        return back()->with('error', 'HR document management is not configured yet.');
    }

    public function storeTraining(Request $request): RedirectResponse
    {
        return back()->with('error', 'HR training records are not configured yet.');
    }

    public function destroyTraining(int $staffTrainingRecord): RedirectResponse
    {
        return back()->with('error', 'HR training records are not configured yet.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionViewData(string $section): array
    {
        return [
            'sectionKey' => $section,
            'sectionMeta' => [
                'title' => ucfirst(str_replace('-', ' ', $section)),
                'description' => 'Human resources workspace',
            ],
            'hrSections' => [
                ['key' => 'communication', 'label' => 'Communication'],
            ],
            'workspaceTabs' => [],
            'searchCommands' => [],
            'focusModes' => [],
            'recentLeaves' => collect(),
            'activityLogs' => collect(),
        ];
    }
}
