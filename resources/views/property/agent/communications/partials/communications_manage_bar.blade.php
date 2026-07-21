@php
    $canManage = (bool) ($canManageCommunications ?? false);
    $context = (string) ($manageContext ?? 'messages');
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                @if ($context === 'notifications')
                    Manage notifications
                @elseif ($context === 'provider')
                    Provider SMS insights
                @else
                    Manage SMS / email
                @endif
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                @if ($canManage)
                    @if ($context === 'notifications')
                        Open alerts, mark them read, and export history.
                    @elseif ($context === 'provider')
                        Review Pradytec delivery stats, provider history, and webhook setup.
                    @else
                        Send messages, resend failed SMS, and manage templates or bulk campaigns.
                    @endif
                @else
                    You can view logs and exports. Sending, resending, and template edits require the <span class="font-medium">Manage communications</span> permission.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($context === 'notifications')
                <form method="post" action="{{ route('property.notifications.mark_all_read', absolute: false) }}" class="inline">
                    @csrf
                    @foreach ((array) ($filters ?? []) as $key => $value)
                        @if (is_scalar($value) && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                        @endif
                    @endforeach
                    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-swal-confirm="Mark all matching notifications as read?">
                        Mark all as read
                    </button>
                </form>
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Open SMS / email log</a>
            @elseif ($context === 'provider')
                <a href="{{ route('property.communications.messages', absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">SMS / email log</a>
                @if ($canManage)
                    <a href="#sms-topup-card" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">Top up SMS</a>
                @endif
            @else
                @if ($canManage)
                    <a href="{{ route('property.communications.sms_provider', absolute: false) }}" class="rounded-lg border border-teal-300 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">SMS wallet</a>
                    <a href="#send-message-form" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Send SMS / email</a>
                    <a href="{{ route('property.communications.bulk', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Bulk messaging</a>
                    <a href="{{ route('property.communications.templates', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Templates</a>
                @endif
                <a href="{{ route('property.notifications', absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">System notifications</a>
            @endif
        </div>
    </div>
</div>
