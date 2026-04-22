<x-loan-layout>
    <x-loan.page
        title="Clients"
        subtitle="Active loan clients, assignments, and contact details."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add client
            </a>
        </x-slot>

        <div
            x-data="{
                columnMenuOpen: false,
                storageKey: 'loan.clients.index.columns.v1',
                defaultCols: {
                    number: true,
                    name: true,
                    mPoints: true,
                    idNo: true,
                    branch: true,
                    cycles: true,
                    gender: true,
                    kinContact: true,
                    nextOfKin: true,
                    assigned: true,
                    status: true,
                    contact: true,
                    actions: true
                },
                cols: {},
                init() {
                    this.cols = { ...this.defaultCols };
                    try {
                        const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
                        if (saved && typeof saved === 'object') {
                            Object.keys(this.defaultCols).forEach((k) => {
                                if (Object.prototype.hasOwnProperty.call(saved, k)) {
                                    this.cols[k] = !!saved[k];
                                }
                            });
                        }
                    } catch (e) {}

                    this.$watch('cols', (value) => {
                        localStorage.setItem(this.storageKey, JSON.stringify(value));
                    }, { deep: true });
                },
                visibleExportCols() {
                    return Object.keys(this.defaultCols).filter((k) => k !== 'actions' && !!this.cols[k]);
                },
                visibleCount() {
                    return Object.keys(this.defaultCols).filter((k) => !!this.cols[k]).length || 1;
                },
                exportUrl(format) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('export', format);
                    url.searchParams.set('cols', this.visibleExportCols().join(','));
                    return `${url.pathname}?${url.searchParams.toString()}`;
                },
                openClientProfile(url, event) {
                    const target = event?.target;
                    if (target && target.closest('a, button, input, select, textarea, form, label')) {
                        return;
                    }

                    window.location.href = url;
                }
            }"
        >
        <form method="get" action="{{ route('loan.clients.index') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="search" id="q" name="q" value="{{ $search ?? '' }}" placeholder="Name, number, phone, email..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branchOptions ?? []) as $branchOption)
                            <option value="{{ $branchOption }}" @selected(($branch ?? '') === $branchOption)>{{ $branchOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (['active' => 'Active', 'dormant' => 'Dormant', 'watchlist' => 'Watchlist'] as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(($status ?? '') === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Assigned</label>
                    <select name="employee_id" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($employees ?? []) as $employee)
                            <option value="{{ $employee->id }}" @selected((int) ($employeeId ?? 0) === (int) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 15, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.clients.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a :href="exportUrl('csv')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a :href="exportUrl('xls')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a :href="exportUrl('pdf')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">All clients</h2>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.number" class="rounded border-slate-300">Number</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.name" class="rounded border-slate-300">Name</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.mPoints" class="rounded border-slate-300">M-Points</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.idNo" class="rounded border-slate-300">ID No</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.branch" class="rounded border-slate-300">Branch</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.cycles" class="rounded border-slate-300">Cycles</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.gender" class="rounded border-slate-300">Gender</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.status" class="rounded border-slate-300">Status</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.contact" class="rounded border-slate-300">Contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.kinContact" class="rounded border-slate-300">Kin contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.nextOfKin" class="rounded border-slate-300">Next of kin</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.assigned" class="rounded border-slate-300">Loans officer</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.actions" class="rounded border-slate-300">Actions</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">{{ $clients->total() }} record(s)</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.number" class="px-5 py-3">Number</th>
                            <th x-show="cols.name" class="px-5 py-3">Name</th>
                            <th x-show="cols.mPoints" class="px-5 py-3">M-Points</th>
                            <th x-show="cols.idNo" class="px-5 py-3">ID No</th>
                            <th x-show="cols.branch" class="px-5 py-3">Branch</th>
                            <th x-show="cols.cycles" class="px-5 py-3">Cycles</th>
                            <th x-show="cols.gender" class="px-5 py-3">Gender</th>
                            <th x-show="cols.contact" class="px-5 py-3">Contact</th>
                            <th x-show="cols.kinContact" class="px-5 py-3">Kin contact</th>
                            <th x-show="cols.nextOfKin" class="px-5 py-3">Next of kin</th>
                            <th x-show="cols.assigned" class="px-5 py-3">Loans officer</th>
                            <th x-show="cols.status" class="px-5 py-3">Status</th>
                            <th x-show="cols.actions" class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($clients as $client)
                            <tr
                                class="cursor-pointer hover:bg-slate-50/80"
                                role="link"
                                tabindex="0"
                                @click="openClientProfile('{{ route('loan.clients.show', $client) }}', $event)"
                                @keydown.enter.prevent="openClientProfile('{{ route('loan.clients.show', $client) }}', $event)"
                                @keydown.space.prevent="openClientProfile('{{ route('loan.clients.show', $client) }}', $event)"
                            >
                                <td x-show="cols.number" class="px-5 py-3 font-mono text-xs text-slate-600">{{ $client->client_number }}</td>
                                <td x-show="cols.name" class="px-5 py-3 font-medium text-slate-900">{{ $client->full_name }}</td>
                                <td x-show="cols.mPoints" class="px-5 py-3 text-slate-600 tabular-nums">0</td>
                                <td x-show="cols.idNo" class="px-5 py-3 text-slate-600">{{ $client->id_number ?: '—' }}</td>
                                <td x-show="cols.branch" class="px-5 py-3 text-slate-600">{{ $client->branch ?? '—' }}</td>
                                <td x-show="cols.cycles" class="px-5 py-3 text-slate-600 tabular-nums">{{ (int) ($client->loan_book_loans_count ?? 0) }}</td>
                                <td x-show="cols.gender" class="px-5 py-3 text-slate-600">{{ $client->gender ? ucfirst($client->gender) : '—' }}</td>
                                <td x-show="cols.contact" class="px-5 py-3 text-slate-600">
                                    <div class="flex flex-col">
                                        @if ($client->phone)<span><x-phone-link :value="$client->phone" /></span>@endif
                                        @if ($client->email)<span class="text-xs">{{ $client->email }}</span>@endif
                                        @if (! $client->phone && ! $client->email)<span>—</span>@endif
                                    </div>
                                </td>
                                <td x-show="cols.kinContact" class="px-5 py-3 text-slate-600"><x-phone-link :value="$client->next_of_kin_contact" /></td>
                                <td x-show="cols.nextOfKin" class="px-5 py-3 text-slate-600">{{ $client->next_of_kin_name ?: '—' }}</td>
                                <td x-show="cols.assigned" class="px-5 py-3 text-slate-600">{{ $client->assignedEmployee?->full_name ?? '—' }}</td>
                                <td x-show="cols.status" class="px-5 py-3 text-slate-600">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold {{ $client->client_status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($client->client_status === 'watchlist' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-700') }}">
                                        {{ $client->client_status }}
                                    </span>
                                </td>
                                <td x-show="cols.actions" class="px-5 py-3 text-right whitespace-nowrap">
                                    <div
                                        x-data="{ open: false }"
                                        class="relative inline-block text-left"
                                        @click.stop
                                        @keydown.stop
                                        @click.outside="open = false"
                                    >
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                        >
                                            Actions
                                            <svg class="ml-1 h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.514a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            class="absolute right-0 z-20 mt-2 w-36 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                                        >
                                            <a href="{{ route('loan.clients.show', $client) }}" class="block px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                            <a href="{{ route('loan.clients.edit', $client) }}" class="block px-3 py-2 text-left text-xs font-medium text-indigo-700 hover:bg-indigo-50">Edit</a>
                                            <form method="post" action="{{ route('loan.clients.destroy', $client) }}" data-swal-confirm="Remove this client?">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="visibleCount()" class="px-5 py-12 text-center text-slate-500">
                                    No clients yet. Use <span class="font-medium text-slate-700">Add Client</span> or convert a lead.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($clients->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $clients->withQueryString()->links() }}
                </div>
            @endif
        </div>
        </div>
    </x-loan.page>
</x-loan-layout>
