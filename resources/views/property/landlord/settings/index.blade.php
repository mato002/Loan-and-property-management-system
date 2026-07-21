<x-property-layout>
    <x-slot name="header">Account</x-slot>

    <x-property.page title="Account">
        <div class="flex flex-wrap gap-2 mb-4 print-hide">
            <a href="{{ route('property.landlord.audit_trail') }}" data-turbo-frame="property-main" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Audit trail</a>
            <a href="{{ route('property.landlord.loans') }}" data-turbo-frame="property-main" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Loans</a>
            <a href="{{ route('property.landlord.notifications') }}" data-turbo-frame="property-main" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Notification inbox</a>
        </div>

        <form id="profile" method="post" action="{{ route('property.landlord.settings.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-6 space-y-4 mb-6 scroll-mt-4">
            @csrf
            <h3 class="text-sm font-semibold">Contact &amp; tax</h3>
            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                <div><label class="block text-xs text-slate-500 mb-1">KRA PIN</label><input name="kra_pin" value="{{ old('kra_pin', $profile->kra_pin) }}" class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
                <div><label class="block text-xs text-slate-500 mb-1">M-Pesa phone</label><input name="mpesa_phone" value="{{ old('mpesa_phone', $profile->mpesa_phone) }}" class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
                <div><label class="block text-xs text-slate-500 mb-1">Bank name</label><input name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}" class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
                <div><label class="block text-xs text-slate-500 mb-1">Bank account</label><input name="bank_account" value="{{ old('bank_account', $profile->bank_account) }}" class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_email" value="1" @checked(old('notify_email', $profile->notify_email)) /> Email delivery</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_sms" value="1" @checked(old('notify_sms', $profile->notify_sms)) /> SMS delivery</label>
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save profile</button>
        </form>

        <form id="payout" method="post" action="{{ route('property.landlord.earnings.settings.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-6 space-y-4 mb-6 scroll-mt-4" data-swal-confirm="Save payout preferences?">
            @csrf
            <h3 class="text-sm font-semibold">Payout preferences</h3>
            <p class="text-xs text-slate-500">Used when you submit remittance instructions. Payments are processed manually by your agency.</p>
            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Default destination</label>
                    <select name="default_destination" class="w-full rounded-lg border px-3 py-2 text-sm">
                        <option value="bank" @selected(old('default_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'bank')>Bank transfer</option>
                        <option value="mpesa" @selected(old('default_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'mpesa')>M-Pesa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Destination details</label>
                    <input type="text" name="destination_detail" value="{{ old('destination_detail', $payoutPrefs['destination_detail'] ?? '') }}" placeholder="Account or phone" class="w-full rounded-lg border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Preferred remittance day</label>
                    <input type="number" min="1" max="28" name="auto_withdraw_day" value="{{ old('auto_withdraw_day', $payoutPrefs['auto_withdraw_day'] ?? '') }}" class="w-full rounded-lg border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Minimum amount (KES)</label>
                    <input type="number" min="0" step="0.01" name="minimum_payout_amount" value="{{ old('minimum_payout_amount', $payoutPrefs['minimum_payout_amount'] ?? '') }}" class="w-full rounded-lg border px-3 py-2 text-sm" />
                </div>
            </div>
            <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="auto_withdraw_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('auto_withdraw_enabled', $payoutPrefs['auto_withdraw_enabled'] ?? false)) />
                <span>Enable monthly remittance reminder (instruction only — no auto payment)</span>
            </label>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save payout preferences</button>
        </form>

        <form id="alerts" method="post" action="{{ route('property.landlord.notifications.preferences.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-6 space-y-4 mb-6 scroll-mt-4" data-swal-confirm="Save alert preferences?">
            @csrf
            <h3 class="text-sm font-semibold">Alert types</h3>
            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_rent_collected" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_rent_collected'] ?? true)> Rent collected</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_overdue" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_overdue'] ?? true)> Overdue invoices</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_maintenance" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_maintenance'] ?? true)> Maintenance updates</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="notify_lease_expiry" value="1" class="rounded border-slate-300" @checked($notificationPrefs['notify_lease_expiry'] ?? true)> Lease expiry</label>
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save alert preferences</button>
        </form>

        <form id="password" method="post" action="{{ route('property.landlord.settings.password') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-6 space-y-4 mb-6 scroll-mt-4">
            @csrf
            <h3 class="text-sm font-semibold">Change password</h3>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 max-w-xl">
                <div class="col-span-2"><label class="block text-xs text-slate-500 mb-1">Current password</label><input type="password" name="current_password" required class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
                <div><label class="block text-xs text-slate-500 mb-1">New password</label><input type="password" name="password" required class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
                <div><label class="block text-xs text-slate-500 mb-1">Confirm</label><input type="password" name="password_confirmation" required class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
            </div>
            <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">Update password</button>
        </form>

        <form id="contact" method="post" action="{{ route('property.landlord.contact_agency') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-6 space-y-4 scroll-mt-4">
            @csrf
            <h3 class="text-sm font-semibold">Contact agency</h3>
            <div><label class="block text-xs text-slate-500 mb-1">Subject</label><input name="subject" required class="w-full rounded-lg border px-3 py-2 text-sm" /></div>
            <div><label class="block text-xs text-slate-500 mb-1">Message</label><textarea name="message" rows="4" required class="w-full rounded-lg border px-3 py-2 text-sm"></textarea></div>
            <button type="submit" class="rounded-xl bg-slate-800 text-white px-4 py-2 text-sm">Send message</button>
        </form>

        @if (request('section'))
            <script>
                document.getElementById(@json(request('section')))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            </script>
        @endif
    </x-property.page>
</x-property-layout>
