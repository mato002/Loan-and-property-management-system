<x-property.workspace
    title="SMS / email"
    subtitle="Send SMS (via provider) and email (via SMTP). SMS uses the Bulk SMS provider configuration."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    empty-title="No messages logged"
    empty-hint="Send a test SMS/email below to confirm provider and SMTP setup."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All messages</a>
                <a href="{{ route('property.communications.messages', array_merge((array) ($filters ?? []), ['channel' => 'email']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Email only</a>
                <a href="{{ route('property.communications.messages', array_merge((array) ($filters ?? []), ['channel' => 'sms']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">SMS only</a>
                <a href="{{ route('property.communications.messages', array_merge((array) ($filters ?? []), ['status' => 'failed']), absolute: false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Failed only</a>
                <a href="{{ route('property.communications.messages.export', (array) ($filters ?? []), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route('property.communications.messages') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="To, subject, body, error..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                    <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['email', 'sms', 'system'] as $ch)
                            <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ strtoupper($ch) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['sent', 'failed', 'queued', 'unknown'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ strtoupper($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>

        <form method="post" action="{{ route('property.communications.messages.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl" x-data="{
            channel: '{{ old('channel', 'email') }}',
            search: '',
            groupFilter: '',
            pickerOpen: false,
            selected: [],
            contacts: @js($recipientContacts ?? []),
            normalize(v) { return (v || '').toString().trim(); },
            selectable(c) { return this.channel === 'sms' ? this.normalize(c.phone) !== '' : this.normalize(c.email) !== ''; },
            recipientValue(c) { return this.channel === 'sms' ? this.normalize(c.phone) : this.normalize(c.email); },
            filteredContacts() {
                const q = this.search.toLowerCase().trim();
                return this.contacts.filter(c => {
                    if (!this.selectable(c)) return false;
                    if (this.groupFilter && c.group !== this.groupFilter) return false;
                    if (!q) return true;
                    return [c.name, c.group, c.email, c.phone].join(' ').toLowerCase().includes(q);
                });
            },
            addRecipient(c) {
                const val = this.recipientValue(c);
                if (!val || this.selected.includes(val)) return;
                this.selected.push(val);
            },
            addAllFiltered() {
                this.filteredContacts().forEach(c => this.addRecipient(c));
            },
            clearAllRecipients() {
                this.selected = [];
            },
            removeRecipient(val) {
                this.selected = this.selected.filter(v => v !== val);
            }
        }">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Send / log a message</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                    <select name="channel" x-model="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="email" @selected(old('channel') === 'email')>Email</option>
                        <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="text" name="to_address" value="{{ old('to_address') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Type email/phone manually (comma or newline separated)" />
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Select contacts (optional)</label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose from Tenants, Landlords, and Other users. Manual input above is also allowed.</p>
                    </div>
                    <button type="button" @click="pickerOpen = !pickerOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <span x-text="pickerOpen ? 'Hide contact list' : 'Open contact list'"></span>
                    </button>
                </div>
                <div x-show="pickerOpen" x-cloak class="mt-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="groupFilter = ''" :class="groupFilter === '' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-medium">All groups</button>
                        <button type="button" @click="groupFilter = 'Tenant'" :class="groupFilter === 'Tenant' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-medium">Tenants</button>
                        <button type="button" @click="groupFilter = 'Landlord'" :class="groupFilter === 'Landlord' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-medium">Landlords</button>
                        <button type="button" @click="groupFilter = 'Other user'" :class="groupFilter === 'Other user' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-medium">Other users</button>
                    </div>
                    <input type="search" x-model="search" placeholder="Search name, email or phone..." class="mt-2 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" @click="addAllFiltered()" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Select all filtered</button>
                        <button type="button" @click="clearAllRecipients()" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Clear selected</button>
                        <span class="text-xs text-slate-500" x-text="selected.length + ' selected'"></span>
                    </div>
                    <div class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
                        <template x-for="contact in filteredContacts()" :key="contact.id + ':' + recipientValue(contact)">
                            <button type="button" @click="addRecipient(contact)" class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100" x-text="contact.name"></span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400"><span x-text="contact.group"></span> • <span x-text="recipientValue(contact)"></span></span>
                                </span>
                                <span class="text-xs text-blue-600 dark:text-blue-400">Add</span>
                            </button>
                        </template>
                        <div x-show="filteredContacts().length === 0" class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400">No contacts match this channel/search.</div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="value in selected" :key="value">
                        <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                            <span x-text="value"></span>
                            <button type="button" @click="removeRecipient(value)" class="text-blue-700 hover:text-blue-900">×</button>
                            <input type="hidden" name="selected_recipients[]" :value="value">
                        </span>
                    </template>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject (email)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Body</label>
                <textarea name="body" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('body') }}</textarea>
            </div>
            @error('channel')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('to_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Channel: All</option>
            <option value="sms">SMS</option>
            <option value="email">Email</option>
        </select>
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search phone or email…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
