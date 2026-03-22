<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\ClientInteraction;
use App\Models\ClientTransfer;
use App\Models\DefaultClientGroup;
use App\Models\Employee;
use App\Models\LoanClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanClientsController extends Controller
{
    public function index(Request $request): View
    {
        $q = LoanClient::query()
            ->clients()
            ->with('assignedEmployee')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($query) use ($search) {
                $query
                    ->where('client_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $clients = $q->paginate(15)->withQueryString();

        return view('loan.clients.index', compact('clients'));
    }

    public function create(): View
    {
        $employees = $this->employeesForSelect();

        return view('loan.clients.create', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateClientPayload($request, null, LoanClient::KIND_CLIENT);
        $validated['kind'] = LoanClient::KIND_CLIENT;
        $validated['lead_status'] = null;
        if (empty($validated['client_status'])) {
            $validated['client_status'] = 'active';
        }

        LoanClient::create($validated);

        return redirect()
            ->route('loan.clients.index')
            ->with('status', 'Client saved successfully.');
    }

    public function show(LoanClient $loan_client): View
    {
        $loan_client->load([
            'assignedEmployee',
            'defaultGroups',
            'interactions' => fn ($q) => $q->with('user')->orderByDesc('interacted_at')->limit(8),
        ]);

        return view('loan.clients.show', compact('loan_client'));
    }

    public function edit(LoanClient $loan_client): View
    {
        $employees = $this->employeesForSelect();

        return view('loan.clients.edit', compact('loan_client', 'employees'));
    }

    public function update(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $validated = $this->validateClientPayload($request, $loan_client, $loan_client->kind);
        $loan_client->update($validated);

        $route = $loan_client->kind === LoanClient::KIND_LEAD
            ? 'loan.clients.leads'
            : 'loan.clients.index';

        return redirect()
            ->route($route)
            ->with('status', 'Record updated.');
    }

    public function destroy(LoanClient $loan_client): RedirectResponse
    {
        $wasLead = $loan_client->kind === LoanClient::KIND_LEAD;
        $loan_client->delete();

        return redirect()
            ->route($wasLead ? 'loan.clients.leads' : 'loan.clients.index')
            ->with('status', 'Record removed.');
    }

    public function leads(Request $request): View
    {
        $q = LoanClient::query()
            ->leads()
            ->with('assignedEmployee')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($query) use ($search) {
                $query
                    ->where('client_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $leads = $q->paginate(15)->withQueryString();

        return view('loan.clients.leads', compact('leads'));
    }

    public function leadsCreate(): View
    {
        $employees = $this->employeesForSelect();

        return view('loan.clients.leads-create', compact('employees'));
    }

    public function leadsStore(Request $request): RedirectResponse
    {
        $validated = $this->validateClientPayload($request, null, LoanClient::KIND_LEAD);
        $validated['kind'] = LoanClient::KIND_LEAD;
        $validated['client_status'] = 'n/a';
        if (empty($validated['lead_status'])) {
            $validated['lead_status'] = 'new';
        }

        LoanClient::create($validated);

        return redirect()
            ->route('loan.clients.leads')
            ->with('status', 'Lead captured.');
    }

    public function leadsConvert(LoanClient $loan_client): RedirectResponse
    {
        if ($loan_client->kind !== LoanClient::KIND_LEAD) {
            return back()->with('status', 'Only leads can be converted.');
        }

        $loan_client->update([
            'kind' => LoanClient::KIND_CLIENT,
            'lead_status' => null,
            'client_status' => 'active',
            'converted_at' => now(),
        ]);

        return redirect()
            ->route('loan.clients.show', $loan_client)
            ->with('status', 'Lead converted to client.');
    }

    public function transfer(): View
    {
        $clients = LoanClient::query()
            ->clients()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $employees = $this->employeesForSelect();

        $recentTransfers = ClientTransfer::query()
            ->with(['loanClient', 'fromEmployee', 'toEmployee', 'transferredByUser'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('loan.clients.transfer', compact('clients', 'employees', 'recentTransfers'));
    }

    public function transferStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'to_branch' => ['nullable', 'string', 'max:120'],
            'to_employee_id' => ['nullable', 'exists:employees,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = LoanClient::query()->findOrFail($validated['loan_client_id']);
        if ($client->kind !== LoanClient::KIND_CLIENT) {
            return back()->withErrors(['loan_client_id' => 'Transfers apply to clients only.'])->withInput();
        }

        ClientTransfer::create([
            'loan_client_id' => $client->id,
            'from_branch' => $client->branch,
            'to_branch' => $validated['to_branch'] ?? null,
            'from_employee_id' => $client->assigned_employee_id,
            'to_employee_id' => $validated['to_employee_id'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'transferred_by' => $request->user()->id,
        ]);

        $client->update([
            'branch' => $validated['to_branch'] ?? $client->branch,
            'assigned_employee_id' => $validated['to_employee_id'] ?? $client->assigned_employee_id,
        ]);

        return redirect()
            ->route('loan.clients.transfer')
            ->with('status', 'Client transfer recorded and portfolio updated.');
    }

    public function defaultGroups(): View
    {
        $groups = DefaultClientGroup::query()
            ->withCount('loanClients')
            ->orderBy('name')
            ->get();

        return view('loan.clients.default-groups', compact('groups'));
    }

    public function defaultGroupsCreate(): View
    {
        return view('loan.clients.default-groups-create');
    }

    public function defaultGroupsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = DefaultClientGroup::create($validated);

        return redirect()
            ->route('loan.clients.default_groups.show', $group)
            ->with('status', 'Group created. Add members below.');
    }

    public function defaultGroupsShow(DefaultClientGroup $default_client_group): View
    {
        $default_client_group->load([
            'loanClients' => fn ($q) => $q->clients()->orderBy('last_name')->orderBy('first_name'),
        ]);

        $availableClients = LoanClient::query()
            ->clients()
            ->whereNotIn('id', $default_client_group->loanClients->pluck('id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.clients.default-groups-show', compact('default_client_group', 'availableClients'));
    }

    public function defaultGroupsEdit(DefaultClientGroup $default_client_group): View
    {
        return view('loan.clients.default-groups-edit', compact('default_client_group'));
    }

    public function defaultGroupsUpdate(Request $request, DefaultClientGroup $default_client_group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $default_client_group->update($validated);

        return redirect()
            ->route('loan.clients.default_groups.show', $default_client_group)
            ->with('status', 'Group updated.');
    }

    public function defaultGroupsDestroy(DefaultClientGroup $default_client_group): RedirectResponse
    {
        $default_client_group->delete();

        return redirect()
            ->route('loan.clients.default_groups')
            ->with('status', 'Group removed.');
    }

    public function defaultGroupsMemberStore(Request $request, DefaultClientGroup $default_client_group): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
        ]);

        $client = LoanClient::query()->findOrFail($validated['loan_client_id']);
        if ($client->kind !== LoanClient::KIND_CLIENT) {
            return back()->with('status', 'Only clients can join default groups.');
        }

        $default_client_group->loanClients()->syncWithoutDetaching([$client->id]);

        return back()->with('status', 'Member added to group.');
    }

    public function defaultGroupsMemberDestroy(DefaultClientGroup $default_client_group, LoanClient $loan_client): RedirectResponse
    {
        $default_client_group->loanClients()->detach($loan_client->id);

        return back()->with('status', 'Member removed from group.');
    }

    public function interactions(Request $request): View
    {
        $q = ClientInteraction::query()
            ->with(['loanClient', 'user'])
            ->orderByDesc('interacted_at');

        if ($type = trim((string) $request->get('type'))) {
            $q->where('interaction_type', $type);
        }

        $interactions = $q->paginate(25)->withQueryString();

        return view('loan.clients.interactions', compact('interactions'));
    }

    public function interactionsCreate(): View
    {
        $people = LoanClient::query()
            ->orderBy('kind')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.clients.interactions-create', compact('people'));
    }

    public function interactionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'interaction_type' => ['required', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'interacted_at' => ['required', 'date'],
        ]);

        ClientInteraction::create([
            'loan_client_id' => $validated['loan_client_id'],
            'user_id' => $request->user()->id,
            'interaction_type' => $validated['interaction_type'],
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'interacted_at' => $validated['interacted_at'],
        ]);

        return redirect()
            ->route('loan.clients.interactions')
            ->with('status', 'Interaction logged.');
    }

    public function interactionCreateForClient(LoanClient $loan_client): View
    {
        return view('loan.clients.interaction-for-client', compact('loan_client'));
    }

    public function interactionStoreForClient(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $validated = $request->validate([
            'interaction_type' => ['required', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'interacted_at' => ['required', 'date'],
        ]);

        ClientInteraction::create([
            'loan_client_id' => $loan_client->id,
            'user_id' => $request->user()->id,
            'interaction_type' => $validated['interaction_type'],
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'interacted_at' => $validated['interacted_at'],
        ]);

        return redirect()
            ->route('loan.clients.show', $loan_client)
            ->with('status', 'Interaction logged.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClientPayload(Request $request, ?LoanClient $existing, string $kind): array
    {
        $id = $existing?->id;

        $rules = [
            'client_number' => [
                'required',
                'string',
                'max:50',
                'unique:loan_clients,client_number'.($id ? ','.$id.',id' : ''),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:2000'],
            'branch' => ['nullable', 'string', 'max:120'],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($kind === LoanClient::KIND_LEAD) {
            $rules['lead_status'] = ['nullable', 'string', 'max:40'];
            $rules['client_status'] = ['nullable', 'string', 'max:40'];
        } else {
            $rules['client_status'] = ['nullable', 'string', 'max:40'];
            $rules['lead_status'] = ['nullable', 'string', 'max:40'];
        }

        return $request->validate($rules);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesForSelect()
    {
        return Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
