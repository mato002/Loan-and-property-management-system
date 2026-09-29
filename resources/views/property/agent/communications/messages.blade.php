@php
    $canManage = (bool) ($canManageCommunications ?? false);
    $showMessageFormByDefault = $errors->hasAny(['channel', 'to_address', 'subject', 'body']);
@endphp
<x-property.workspace
    title="SMS / email"
    subtitle="Outbound SMS and email delivery log (tenant and staff sends). System alerts such as logins are on Notifications."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="[]"
    :show-search="false"
    empty-title="No messages logged"
    empty-hint="Send a test SMS/email below to confirm provider and SMTP setup."
>
    @if ($canManage)
        <x-slot name="pageModalsAttributes" x-data="{
            showMessageForm: @js($showMessageFormByDefault),
            composeLoading: false,
            composeLoaded: @js(! empty($recipientContacts) || ! empty($composeTemplates)),
            async openMessageCompose() {
                if (! this.composeLoaded) {
                    this.composeLoading = true;
                    try {
                        if (typeof window.__propertyMessagesEnsureCompose === 'function') {
                            await window.__propertyMessagesEnsureCompose();
                        }
                        this.composeLoaded = true;
                    } catch {
                        return;
                    } finally {
                        this.composeLoading = false;
                    }
                }
                this.showMessageForm = true;
            }
        }"></x-slot>
    @endif

    @if ($canManage)
        <x-slot name="actions">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                :disabled="composeLoading"
                @click="openMessageCompose()"
            >
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                <span x-text="composeLoading ? 'Loading…' : 'Send message'">Send message</span>
            </button>
        </x-slot>

        <x-slot name="modals">
            <x-property.modal
                show="showMessageForm"
                close="showMessageForm = false"
                name="send-message"
                title="Send message"
                max-width="4xl"
            >
            <form method="post" action="{{ route('property.communications.messages.store') }}" class="space-y-3" x-data="{
                channel: '{{ old('channel', 'email') }}',
                bodyText: @js(old('body', '')),
                subjectText: @js(old('subject', '')),
                templateId: @js(old('message_template_id', '')),
                templates: @js($composeTemplates ?? []),
                manualTo: @js(old('to_address', '')),
                smsWallet: @js($smsWallet ?? []),
                composeLoading: false,
                composeLoaded: @js(! empty($recipientContacts) || ! empty($composeTemplates)),
                composeContextUrl: @js($composeContextUrl ?? route('property.communications.messages.compose_context', absolute: false)),
                search: '',
                groupFilter: '',
                pickerOpen: false,
                selected: [],
                async ensureComposeContext() {
                    if (this.composeLoaded || this.composeLoading) {
                        return;
                    }
                    this.composeLoading = true;
                    try {
                        const response = await fetch(this.composeContextUrl, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });
                        if (! response.ok) {
                            throw new Error('Could not load compose data.');
                        }
                        const payload = await response.json();
                        this.templates = Array.isArray(payload.composeTemplates) ? payload.composeTemplates : [];
                        this.contacts = Array.isArray(payload.recipientContacts) ? payload.recipientContacts : [];
                        this.smsWallet = payload.smsWallet && typeof payload.smsWallet === 'object' ? payload.smsWallet : {};
                        this.composeLoaded = true;
                    } catch (error) {
                        if (window.Swal?.fire) {
                            window.Swal.fire({
                                icon: 'error',
                                title: 'Could not open composer',
                                text: error instanceof Error ? error.message : 'Try again in a moment.',
                            });
                        }
                        throw error;
                    } finally {
                        this.composeLoading = false;
                    }
                },
                templatesForChannel() {
                    return this.templates.filter(t => t.channel === this.channel);
                },
                applyTemplate() {
                    const t = this.templates.find(x => String(x.id) === String(this.templateId));
                    if (!t) return;
                    this.bodyText = t.body || '';
                    if (t.subject) this.subjectText = t.subject;
                },
                contacts: @js($recipientContacts ?? []),
                normalize(v) { return (v || '').toString().trim(); },
                manualRecipientCount() {
                    const raw = (this.manualTo || '').split(/[\s,;]+/).map(v => this.normalize(v)).filter(Boolean);
                    return raw.length;
                },
                recipientCount() {
                    const manual = this.manualRecipientCount();
                    const picked = this.selected.length;
                    return Math.max(manual + picked, manual > 0 ? manual : picked);
                },
                smsBlocked() {
                    if (this.channel !== 'sms') return false;
                    const max = Number(this.smsWallet?.max_recipients ?? 0);
                    if ((this.smsWallet?.status ?? '') === 'empty') return true;
                    if ((this.smsWallet?.status ?? '') === 'unknown' && max <= 0) return true;
                    return this.recipientCount() > max;
                },
                smsBlockMessage() {
                    if (this.channel !== 'sms' || !this.smsBlocked()) return '';
                    const max = Number(this.smsWallet?.max_recipients ?? 0);
                    const count = this.recipientCount();
                    if ((this.smsWallet?.status ?? '') === 'empty') {
                        return this.smsWallet?.detail || 'Insufficient SMS balance. Top up on Provider SMS before sending.';
                    }
                    if (count > max) {
                        return `Selected ${count} recipient(s) but only about ${max} SMS can be sent with the current balance.`;
                    }
                    return this.smsWallet?.detail || 'SMS balance is too low for this send.';
                },
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
                },
                onChannelChange() {
                    this.selected = [];
                    this.pickerOpen = false;
                    this.templateId = '';
                },
                init() {
                    window.__propertyMessagesEnsureCompose = () => this.ensureComposeContext();
                }
            }" x-init="init()">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="channel === 'sms' ? 'Send SMS' : 'Send email'">Send / log a message</h3>
                <div x-show="channel === 'sms'" x-cloak class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                    Sends immediately via the Bulk SMS provider. Use local numbers (0712…) or international format (254712…).
                </div>
                <div x-show="channel === 'email'" x-cloak class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                    Sends via configured SMTP. Add a subject line and email body below.
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                        <select name="channel" x-model="channel" @change="onChannelChange()" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="email" @selected(old('channel') === 'email')>Email</option>
                            <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" x-text="channel === 'sms' ? 'Phone number(s)' : 'Email address(es)'">To</label>
                        <input type="text" name="to_address" x-model="manualTo" value="{{ old('to_address') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" :placeholder="channel === 'sms' ? '0712345678 or 254712345678 (comma or newline separated)' : 'name@example.com (comma or newline separated)'" />
                    </div>
                </div>
                <x-communications.send-template-picker />
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Select contacts (optional)</label>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="channel === 'sms' ? 'Only contacts with a phone number are shown. Manual input above is also allowed.' : 'Only contacts with an email address are shown. Manual input above is also allowed.'">Choose from Tenants, Landlords, and Other users. Manual input above is also allowed.</p>
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
                        <input type="search" x-model="search" :placeholder="channel === 'sms' ? 'Search name or phone…' : 'Search name or email…'" class="mt-2 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
                                <button type="button" @click="removeRecipient(value)" class="text-blue-700 hover:text-blue-900">&times;</button>
                                <input type="hidden" name="selected_recipients[]" :value="value">
                            </span>
                        </template>
                    </div>
                </div>
                <div x-show="channel === 'email'" x-cloak>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject</label>
                    <input type="text" name="subject" x-model="subjectText" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Email subject line" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" x-text="channel === 'sms' ? 'SMS message' : 'Email body'">Body</label>
                    <textarea name="body" x-model="bodyText" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" :placeholder="channel === 'sms' ? 'Type your SMS text here…' : 'Type your email message here…'">{{ old('body') }}</textarea>
                    <p x-show="channel === 'sms'" x-cloak class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <span x-text="(bodyText || '').length"></span> characters · standard SMS is 160 characters per segment
                    </p>
                </div>
                <div x-show="channel === 'sms'" x-cloak class="rounded-lg border px-3 py-2 text-xs" :class="smsBlocked() ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'">
                    <span x-show="!smsBlocked()">Estimated recipients: <span x-text="recipientCount()"></span> · SMS balance allows about <span x-text="smsWallet.max_recipients ?? 0"></span> send(s).</span>
                    <span x-show="smsBlocked()" x-text="smsBlockMessage()"></span>
                </div>
                @error('channel')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('to_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50" :disabled="channel === 'sms' && smsBlocked()" :class="channel === 'sms' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700'" x-text="channel === 'sms' ? 'Send SMS' : 'Send email'">Submit</button>
            </form>
            </x-property.modal>
        </x-slot>
    @endif

    <x-slot name="tabs">
        @include('property.agent.communications.partials.communications_manage_bar', ['manageContext' => 'messages'])
    </x-slot>

    @if (! $canManage)
        <x-slot name="secondary">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                Sending SMS or email requires the <strong>Manage communications</strong> permission. You can still review delivery logs, filter, and export below.
            </div>
        </x-slot>
    @endif

    <x-slot name="toolbar">
        @include('property.agent.communications.partials.messages_toolbar')
    </x-slot>

    @include('property.agent.communications.partials.messages_log_table')

    <x-slot name="footer">
        @isset($logs)
            <div class="flex flex-wrap items-center justify-between gap-3 px-1">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} message(s)
                </p>
                <div>{{ $logs->links() }}</div>
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
