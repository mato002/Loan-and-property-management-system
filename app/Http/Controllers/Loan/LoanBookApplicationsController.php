<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookApplication;
use App\Models\LoanClient;
use App\Models\PmInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanBookApplicationsController extends Controller
{
    public function index(): View
    {
        $applications = LoanBookApplication::query()
            ->with('loanClient')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('loan.book.applications.index', [
            'title' => 'Loan applications',
            'subtitle' => 'Customer LoanBook pipeline — from submission to disbursement.',
            'applications' => $applications,
        ]);
    }

    public function report(): View
    {
        $applications = LoanBookApplication::query()
            ->with('loanClient')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('loan.book.applications.report', [
            'title' => 'Application loans report',
            'subtitle' => 'Export-style listing for committee and MIS.',
            'applications' => $applications,
        ]);
    }

    public function create(Request $request): View
    {
        if ($this->tenantHasPropertyArrears($request)) {
            abort(403, 'Clear property arrears before creating a loan application.');
        }

        $selectedClientId = null;
        if ((string) $request->query('prefill') === 'portal') {
            $selectedClientId = $this->resolvePortalClientId($request);
        }
        $portalRole = strtolower(trim((string) $request->query('portal_role', '')));
        $defaultProductName = match ($portalRole) {
            'tenant' => 'Tenant personal loan',
            'landlord' => 'Landlord property improvement loan',
            default => '',
        };
        $defaultPurpose = match ($portalRole) {
            'tenant' => 'Personal or household financing request via tenant portal.',
            'landlord' => 'Property-related financing request via landlord portal.',
            default => '',
        };

        return view('loan.book.applications.create', [
            'title' => 'Create application',
            'subtitle' => 'Start a new LoanBook file for an onboarded client.',
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'stages' => $this->stageOptions(),
            'selectedClientId' => $selectedClientId,
            'defaultProductName' => $defaultProductName,
            'defaultPurpose' => $defaultPurpose,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->tenantHasPropertyArrears($request)) {
            return redirect()
                ->route('property.tenant.loans')
                ->withErrors([
                    'loan' => 'Clear your rent arrears first before applying for a loan.',
                ]);
        }

        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validated['submission_source'] = 'manual_internal';

        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }

        $next = (LoanBookApplication::query()->max('id') ?? 0) + 1;
        $validated['reference'] = 'APP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $validated['submitted_at'] = now();

        LoanBookApplication::query()->create($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application saved.'));
    }

    public function edit(LoanBookApplication $loan_book_application): View
    {
        return view('loan.book.applications.edit', [
            'title' => 'Edit application',
            'subtitle' => $loan_book_application->reference,
            'application' => $loan_book_application,
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'stages' => $this->stageOptions(),
        ]);
    }

    public function update(Request $request, LoanBookApplication $loan_book_application): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (empty($loan_book_application->submission_source)) {
            $validated['submission_source'] = 'manual_internal';
        }

        LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $loan_book_application->update($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application updated.'));
    }

    public function destroy(LoanBookApplication $loan_book_application): RedirectResponse
    {
        if ($loan_book_application->loan()->exists()) {
            return redirect()
                ->route('loan.book.applications.index')
                ->with('error', __('Cannot delete an application that already has a loan record.'));
        }

        $loan_book_application->delete();

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application removed.'));
    }

    /**
     * @return array<string, string>
     */
    private function stageOptions(): array
    {
        return [
            LoanBookApplication::STAGE_SUBMITTED => 'Submitted',
            LoanBookApplication::STAGE_CREDIT_REVIEW => 'Credit review',
            LoanBookApplication::STAGE_APPROVED => 'Approved',
            LoanBookApplication::STAGE_DECLINED => 'Declined',
            LoanBookApplication::STAGE_DISBURSED => 'Disbursed',
        ];
    }

    private function resolvePortalClientId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $role = (string) ($user->property_portal_role ?? '');
        if (! in_array($role, ['tenant', 'landlord'], true)) {
            return null;
        }

        $email = trim((string) ($user->email ?? ''));
        $phone = '';
        if (Schema::hasColumn('users', 'phone')) {
            $phone = trim((string) ($user->phone ?? ''));
        }

        $existing = null;
        if ($email !== '') {
            $existing = LoanClient::query()->clients()->where('email', $email)->first();
        }
        if (! $existing && $phone !== '') {
            $existing = LoanClient::query()->clients()->where('phone', $phone)->first();
        }
        if ($existing) {
            return (int) $existing->id;
        }

        $name = trim((string) ($user->name ?? ''));
        $parts = preg_split('/\s+/', $name) ?: [];
        $firstName = trim((string) ($parts[0] ?? 'Portal'));
        $lastName = trim((string) (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : ucfirst($role)));

        $seed = $email !== '' ? Str::lower($email) : ('u'.$user->id);
        $clientNumber = 'PORTAL-'.strtoupper(substr(md5($role.'|'.$seed), 0, 8));
        while (LoanClient::query()->where('client_number', $clientNumber)->exists()) {
            $clientNumber = 'PORTAL-'.strtoupper(substr(md5($role.'|'.$seed.'|'.Str::random(6)), 0, 8));
        }

        $client = LoanClient::query()->create([
            'client_number' => $clientNumber,
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => $firstName !== '' ? $firstName : 'Portal',
            'last_name' => $lastName !== '' ? $lastName : ucfirst($role),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'client_status' => 'active',
            'notes' => 'Auto-created from '.$role.' portal handoff.',
        ]);

        return (int) $client->id;
    }

    private function tenantHasPropertyArrears(Request $request): bool
    {
        $user = $request->user();
        if (! $user || (string) ($user->property_portal_role ?? '') !== 'tenant') {
            return false;
        }

        $tenant = $user->pmTenantProfile;
        if (! $tenant) {
            return false;
        }

        $arrears = (float) (PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as arrears')
            ->value('arrears') ?? 0.0);

        return $arrears > 0;
    }
}
