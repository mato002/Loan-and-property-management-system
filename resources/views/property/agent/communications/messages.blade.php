@php
    $canManage = (bool) ($canManageCommunications ?? false);
    $showMessageFormByDefault = $errors->hasAny(['channel', 'to_address', 'subject', 'body']);
@endphp
<x-property.workspace
    :title="$pageTitle ?? 'SMS / email'"
    :subtitle="$pageSubtitle ?? 'Outbound SMS and email delivery log (tenant and staff sends). System alerts such as logins are on Notifications.'"
    back-route="property.communications.index"
    :stats="$stats"
    :columns="[]"
    :show-search="false"
    empty-title="No messages logged"
    empty-hint="Send a test SMS/email below to confirm provider and SMTP setup."
>
    @if ($canManage)
        <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from([
            'showSmsForm' => $showMessageFormByDefault && old('channel', $defaultComposeChannel ?? 'email') === 'sms',
            'showEmailForm' => $showMessageFormByDefault && old('channel', $defaultComposeChannel ?? 'email') === 'email',
        ]) !!}"></x-slot>
    @endif

    @if ($canManage)
        <x-slot name="actions">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                data-property-modal-open="showSmsForm" @click="showSmsForm = true"
            >
                <i class="fa-solid fa-comment-sms" aria-hidden="true"></i>
                <span>Send SMS</span>
            </button>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                data-property-modal-open="showEmailForm" @click="showEmailForm = true"
            >
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <span>Send email</span>
            </button>
        </x-slot>

        <x-slot name="modals">
            <!-- SMS Modal -->
            <x-property.modal
                show="showSmsForm"
                close="showSmsForm = false"
                name="send-sms"
                title="Send SMS"
                max-width="2xl"
            >
            <form method="post" action="{{ route('property.communications.messages.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="sms" />
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                    Sends immediately via the Bulk SMS provider. Use local numbers (0712…) or international format (254712…).
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone number(s)</label>
                    <input type="text" name="to_address" value="{{ old('to_address') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="0712345678 or 254712345678 (comma or newline separated)" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">SMS message</label>
                    <textarea name="body" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Type your SMS text here…">{{ old('body') }}</textarea>
                </div>
                @error('to_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-medium text-white hover:opacity-90 bg-emerald-600 hover:bg-emerald-700">Send SMS</button>
            </form>
            </x-property.modal>

            <!-- Email Modal -->
            <x-property.modal
                show="showEmailForm"
                close="showEmailForm = false"
                name="send-email"
                title="Send email"
                max-width="2xl"
            >
            <form method="post" action="{{ route('property.communications.messages.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="channel" value="email" />
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                    Sends via configured SMTP. Add a subject line and email body below.
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email address(es)</label>
                    <input type="text" name="to_address" value="{{ old('to_address') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="name@example.com (comma or newline separated)" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Email subject line" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email body</label>
                    <textarea name="body" rows="4" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Type your email message here…">{{ old('body') }}</textarea>
                </div>
                @error('to_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('subject')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-medium text-white hover:opacity-90 bg-blue-600 hover:bg-blue-700">Send email</button>
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
