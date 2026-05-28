<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\UtilityBillingPeriod;
use App\Models\UtilityPeriodOverrideRequest;
use App\Services\Property\UtilityPeriodClosingService;
use App\Services\Property\UtilityPeriodGuardService;
use App\Services\Property\UtilityPeriodOverrideService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class UtilityPeriodController extends Controller
{
    public function __construct(
        private readonly UtilityPeriodClosingService $closing,
        private readonly UtilityPeriodOverrideService $overrides,
        private readonly UtilityPeriodGuardService $guard,
    ) {}

    public function index(): View
    {
        $agentId = (int) auth()->id();
        $periods = $this->closing->recentPeriods(18, $agentId);

        return property_view('property.agent.revenue.utility_periods.index', [
            'periods' => $periods,
            'stats' => [
                ['label' => 'Open periods', 'value' => (string) $periods->where('status', UtilityBillingPeriod::STATUS_OPEN)->count(), 'hint' => 'Editable'],
                ['label' => 'Closed periods', 'value' => (string) $periods->where('status', UtilityBillingPeriod::STATUS_CLOSED)->count(), 'hint' => 'Locked'],
                ['label' => 'Pending overrides', 'value' => (string) UtilityPeriodOverrideRequest::query()
                    ->where('status', UtilityPeriodOverrideRequest::STATUS_PENDING)
                    ->whereIn('utility_billing_period_id', $periods->pluck('id'))
                    ->count(), 'hint' => 'Awaiting approval'],
            ],
        ]);
    }

    public function show(string $billingMonth): View
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            abort(404);
        }

        $period = $this->guard->ensurePeriod($billingMonth);
        $period->load(['closedBy', 'overrideRequests.requester', 'overrideRequests.approver']);

        $checklist = $period->isClosed()
            ? ($period->reconciliation_snapshot ?? [])
            : $this->closing->reconciliationChecklist($billingMonth);

        $canClose = $period->isOpen() && $this->closing->canClose($billingMonth, false);

        return property_view('property.agent.revenue.utility_periods.show', [
            'period' => $period,
            'billingMonth' => $billingMonth,
            'checklist' => $checklist,
            'canClose' => $canClose,
            'closeReport' => $period->close_report ?? $this->closing->buildCloseReport($billingMonth),
            'pendingOverrides' => $period->overrideRequests->where('status', UtilityPeriodOverrideRequest::STATUS_PENDING),
            'actionTypes' => $this->overrideActionTypes(),
        ]);
    }

    public function close(Request $request, string $billingMonth): RedirectResponse
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            abort(404);
        }

        $data = $request->validate([
            'close_notes' => ['nullable', 'string', 'max:2000'],
            'acknowledge_suspense' => ['nullable', 'boolean'],
        ]);

        try {
            $this->closing->closePeriod(
                $billingMonth,
                $request->user(),
                $data['close_notes'] ?? null,
                (bool) ($data['acknowledge_suspense'] ?? false),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return redirect()
            ->route('property.revenue.utilities.periods.show', ['billingMonth' => $billingMonth], false)
            ->with('success', 'Utility billing period '.$billingMonth.' is now closed and locked.');
    }

    public function closeReport(Request $request, string $billingMonth): View|Response
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            abort(404);
        }

        $period = UtilityBillingPeriod::query()
            ->with('closedBy')
            ->where('billing_month', $billingMonth)
            ->where('agent_user_id', (int) auth()->id())
            ->firstOrFail();

        $report = $period->close_report ?? $this->closing->buildCloseReport($billingMonth);

        $viewData = [
            'period' => $period,
            'report' => $report,
            'billingMonth' => $billingMonth,
        ];

        if ($request->query('export') === 'pdf') {
            $html = view('property.agent.revenue.utility_periods.close_report_print', $viewData)->render();
            try {
                $options = new Options;
                $options->set('isRemoteEnabled', false);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="utility-close-'.$billingMonth.'.pdf"',
                ]);
            } catch (\Throwable) {
                return response($html);
            }
        }

        return property_view('property.agent.revenue.utility_periods.close_report_print', $viewData);
    }

    public function requestOverride(Request $request, string $billingMonth): RedirectResponse
    {
        $data = $request->validate([
            'action_type' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:2000'],
            'entity_type' => ['nullable', 'string', 'max:64'],
            'entity_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->overrides->request(
                $billingMonth,
                (string) $data['action_type'],
                (string) $data['reason'],
                $request->user(),
                $data['entity_type'] ?? null,
                isset($data['entity_id']) ? (int) $data['entity_id'] : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }

        return back()->with('success', 'Override request submitted for supervisor approval.');
    }

    public function approveOverride(Request $request, UtilityPeriodOverrideRequest $override): RedirectResponse
    {
        $data = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->overrides->approve($override, $request->user(), $data['approval_notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }

        return back()->with('success', 'Override approved. Requester may now execute the action within 48 hours using override #'.$override->id.'.');
    }

    public function rejectOverride(Request $request, UtilityPeriodOverrideRequest $override): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->overrides->reject($override, $request->user(), (string) $data['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }

        return back()->with('success', 'Override request rejected.');
    }

    /**
     * @return array<string, string>
     */
    private function overrideActionTypes(): array
    {
        return [
            UtilityPeriodGuardService::ACTION_EDIT_READING => 'Edit / add water reading',
            UtilityPeriodGuardService::ACTION_DELETE_READING => 'Delete water reading',
            UtilityPeriodGuardService::ACTION_GENERATE_INVOICE => 'Generate utility invoices',
            UtilityPeriodGuardService::ACTION_REVERSE_INVOICE => 'Cancel / reverse utility invoice',
            UtilityPeriodGuardService::ACTION_EDIT_INVOICE => 'Edit utility invoice',
            UtilityPeriodGuardService::ACTION_REVERSE_PENALTY => 'Reverse water penalty',
            UtilityPeriodGuardService::ACTION_APPLY_PENALTY => 'Apply water penalty',
            UtilityPeriodGuardService::ACTION_REVERSE_ALLOCATION => 'Reverse payment allocation',
        ];
    }
}
