<?php

namespace App\Http\Controllers\Property\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLandlordPortalProfile;
use App\Models\PmLandlordRemittanceRequest;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPortalAction;
use App\Models\PropertyUnit;
use App\Services\Property\LandlordPortalAccess;
use App\Services\Property\LandlordPortalInvoicePdfService;
use App\Services\Property\LandlordPortalSnapshotService;
use App\Services\Property\LandlordReportsHubService;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordPortalWorkspaceController extends Controller
{
    public function __construct(
        private LandlordPortalSnapshotService $snapshot,
        private LandlordPortalInvoicePdfService $invoicePdf,
    ) {}

    public function reportsIndex(Request $request, LandlordReportsHubService $hub): View
    {
        $activePanel = LandlordReportsHubService::resolvePanel($request->query('panel'));

        return property_view('property.landlord.reports.index', array_merge(
            $hub->dataFor($activePanel, $request),
            [
                'activePanel' => $activePanel,
                'activeGroup' => LandlordReportsHubService::groupForPanel($activePanel),
                'panels' => LandlordReportsHubService::panels(),
                'panelGroups' => LandlordReportsHubService::panelGroups(),
            ]
        ));
    }

    public function leases(Request $request, bool $forHub = false): View|RedirectResponse
    {
        if (! $forHub) {
            return $this->redirectToReportsHub('leases', $request);
        }

        $data = $this->snapshot->leasesIndex($request->user());

        return property_view('property.landlord.reports.leases', $data);
    }

    public function rentRoll(Request $request, bool $forHub = false): View|RedirectResponse
    {
        if (! $forHub) {
            return $this->redirectToReportsHub('rent_roll', $request);
        }

        $data = $this->snapshot->rentRoll($request->user());

        return property_view('property.landlord.reports.rent_roll', array_merge($data, [
            'title' => 'Rent roll',
        ]));
    }

    public function arrears(Request $request, bool $forHub = false): View|RedirectResponse
    {
        if (! $forHub) {
            return $this->redirectToReportsHub('arrears', $request);
        }

        $data = $this->snapshot->arrearsAging($request->user());

        return property_view('property.landlord.reports.arrears', $data);
    }

    public function ownerStatement(Request $request, bool $forHub = false): View|RedirectResponse
    {
        if (! $forHub) {
            return $this->redirectToReportsHub('owner_statement', $request);
        }

        $user = $request->user();
        $month = (string) $request->query('month', now()->format('Y-m'));
        $fy = (int) $request->query('fy', now()->year);
        $snap = $this->snapshot->buildSnapshot($user, $month, $fy);
        $profile = PmLandlordPortalProfile::forUser($user);

        return property_view('property.landlord.reports.owner_statement', [
            'snapshot' => $snap,
            'month' => $month,
            'fy' => $fy,
            'profile' => $profile,
            'ledgerBalance' => \App\Services\Property\PropertyMoney::kes(\App\Services\Property\LandlordLedger::balance($user)),
        ]);
    }

    public function exportOwnerStatementPdf(Request $request): StreamedResponse
    {
        $user = $request->user();
        $month = (string) $request->query('month', now()->format('Y-m'));
        $fy = (int) $request->query('fy', now()->year);
        $snap = $this->snapshot->buildSnapshot($user, $month, $fy);

        $rows = $snap['propertyBreakdown']->map(fn (array $row) => [
            (string) $row['property_name'],
            number_format((float) $row['ownership_percent'], 2).'%',
            number_format((float) $row['gross_collected'], 2, '.', ''),
            number_format((float) $row['management_fee'], 2, '.', ''),
            number_format((float) $row['net_to_owner'], 2, '.', ''),
            number_format((float) $row['pending_share'], 2, '.', ''),
        ])->all();

        return TabularExport::stream(
            'owner-statement-'.$user->id.'-'.($month !== '' ? $month : 'fy'.$fy),
            ['Property', 'Ownership %', 'Collected', 'Mgmt fee', 'Net to owner', 'Pending AR'],
            fn () => $rows,
            TabularExport::FORMAT_PDF,
            [
                'title' => 'Owner statement — '.$snap['periodLabel'],
                'subtitle' => (string) $user->name,
            ]
        );
    }

    public function acknowledgeStatement(Request $request): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $profile = PmLandlordPortalProfile::forUser($request->user());
        $profile->update(['last_acknowledged_statement_month' => $data['month']]);

        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'landlord',
            'action_key' => 'landlord_statement_acknowledged',
            'notes' => 'Acknowledged statement '.$data['month'],
            'context' => ['month' => $data['month']],
        ]);

        return redirect()
            ->route('property.landlord.reports.index', ['panel' => 'owner_statement', 'month' => $data['month']])
            ->with('success', 'Statement acknowledged for '.$data['month'].'.');
    }

    private function redirectToReportsHub(string $panel, Request $request): RedirectResponse
    {
        return redirect()->route('property.landlord.reports.index', array_merge(
            ['panel' => $panel],
            $request->query()
        ));
    }

    public function vacancies(Request $request): RedirectResponse
    {
        return redirect()->route('property.landlord.properties', array_merge(
            ['view' => 'vacancies'],
            $request->query()
        ));
    }

    public function settings(Request $request): View
    {
        $user = $request->user();

        return property_view('property.landlord.settings.index', [
            'profile' => PmLandlordPortalProfile::forUser($user),
            'payoutPrefs' => LandlordPortalAccess::latestPreferenceContext($user, 'landlord_payout_preferences'),
            'notificationPrefs' => LandlordPortalAccess::latestPreferenceContext($user, 'landlord_notification_preferences'),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kra_pin' => ['nullable', 'string', 'max:32'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'mpesa_phone' => ['nullable', 'string', 'max:32'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
        ]);

        $profile = PmLandlordPortalProfile::forUser($request->user());
        $profile->update([
            'kra_pin' => $data['kra_pin'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'mpesa_phone' => $data['mpesa_phone'] ?? null,
            'notify_email' => $request->boolean('notify_email'),
            'notify_sms' => $request->boolean('notify_sms'),
        ]);

        return back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated.');
    }

    public function contactAgency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'landlord',
            'action_key' => 'landlord_agency_message',
            'notes' => $data['subject'],
            'context' => ['message' => $data['message']],
        ]);

        return back()->with('success', 'Message sent to your property manager.');
    }

    public function maintenanceShow(Request $request, PmMaintenanceJob $job): View
    {
        $job->load(['request.unit.property', 'vendor']);
        LandlordPortalAccess::authorizeMaintenanceJob($request->user(), $job);

        return property_view('property.landlord.maintenance.show', [
            'job' => $job,
            'approvalThreshold' => (float) ($this->latestActionContext($request->user(), 'landlord_maintenance_threshold')['approval_threshold'] ?? 0),
        ]);
    }

    public function maintenanceStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'integer'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:2000'],
            'urgency' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        if (! LandlordPortalAccess::ownsUnit($request->user(), (int) $data['property_unit_id'])) {
            abort(403);
        }

        $maint = PmMaintenanceRequest::query()->create([
            'property_unit_id' => (int) $data['property_unit_id'],
            'reported_by_user_id' => $request->user()->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'urgency' => $data['urgency'] ?? 'normal',
            'status' => 'open',
        ]);

        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'landlord',
            'action_key' => 'landlord_maintenance_request',
            'notes' => 'Maintenance request #'.$maint->id,
            'context' => ['request_id' => $maint->id],
        ]);

        return redirect()->route('property.landlord.maintenance')->with('success', 'Maintenance request submitted.');
    }

    public function invoicePdf(Request $request, PmInvoice $invoice): StreamedResponse|\Illuminate\Http\Response
    {
        LandlordPortalAccess::authorizeInvoice($request->user(), $invoice);

        return $this->invoicePdf->stream($invoice);
    }

    public function remittances(Request $request): View
    {
        $user = $request->user();
        $remittanceService = app(\App\Services\Property\LandlordPortalRemittanceService::class);

        return property_view('property.landlord.earnings.remittances', [
            'requests' => $remittanceService->recentRequests($user),
            'ledgerBalance' => \App\Services\Property\PropertyMoney::kes($remittanceService->ledgerBalance($user)),
            'pendingTotal' => \App\Services\Property\PropertyMoney::kes($remittanceService->pendingRemittanceTotal($user)),
            'available' => \App\Services\Property\PropertyMoney::kes($remittanceService->availableForRequest($user)),
        ]);
    }

    /** @return array<string, mixed> */
    private function latestActionContext(\App\Models\User $user, string $actionKey): array
    {
        return (array) (PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->where('action_key', $actionKey)
            ->latest('id')
            ->value('context') ?? []);
    }
}
