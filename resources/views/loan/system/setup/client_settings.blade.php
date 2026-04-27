<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">System setup</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-3xl p-6">
            <form method="post" action="{{ route('loan.system.setup.client_settings.update') }}" class="space-y-5">
                @csrf

                @foreach ($settings as $row)
                    <div>
                        <label for="setting_{{ $row->key }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $row->label ?? $row->key }}</label>

                        @if ($row->key === 'client_onboarding_kyc_mode')
                            <select id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" class="w-full rounded-lg border-slate-200 text-sm">
                                @php($current = (string) old('settings.'.$row->key, $row->value))
                                <option value="basic" @selected($current === 'basic')>Basic</option>
                                <option value="enhanced" @selected($current === 'enhanced')>Enhanced</option>
                            </select>
                        @elseif ($row->key === 'client_onboarding_default_client_status')
                            <select id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" class="w-full rounded-lg border-slate-200 text-sm">
                                @php($current = (string) old('settings.'.$row->key, $row->value))
                                <option value="pending_review" @selected($current === 'pending_review')>Pending review</option>
                                <option value="active" @selected($current === 'active')>Active</option>
                                <option value="inactive" @selected($current === 'inactive')>Inactive</option>
                            </select>
                        @elseif (in_array($row->key, ['client_onboarding_auto_activate', 'client_onboarding_requires_guarantor', 'client_onboarding_blacklist_screening'], true))
                            <select id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" class="w-full rounded-lg border-slate-200 text-sm">
                                @php($current = (string) old('settings.'.$row->key, $row->value))
                                <option value="yes" @selected($current === 'yes')>Yes</option>
                                <option value="no" @selected($current === 'no')>No</option>
                            </select>
                        @elseif (in_array($row->key, ['client_onboarding_notes', 'client_onboarding_required_documents'], true))
                            <textarea id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" rows="{{ $row->key === 'client_onboarding_notes' ? 4 : 3 }}" class="w-full rounded-lg border-slate-200 text-sm">{{ old('settings.'.$row->key, $row->value) }}</textarea>
                        @elseif (in_array($row->key, ['client_onboarding_minimum_age', 'client_onboarding_maximum_age'], true))
                            <input id="setting_{{ $row->key }}" type="number" min="0" max="120" name="settings[{{ $row->key }}]" value="{{ old('settings.'.$row->key, $row->value) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @else
                            <input id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" value="{{ old('settings.'.$row->key, $row->value) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @endif

                        @if ($row->key === 'client_onboarding_required_documents')
                            <p class="text-[11px] text-slate-500 mt-1">Comma-separated document keys. Example: national_id,passport_photo,proof_of_address.</p>
                        @elseif ($row->key === 'client_onboarding_notes')
                            <p class="text-[11px] text-slate-500 mt-1">Internal notes for onboarding officers and reviewers.</p>
                        @endif

                        <p class="text-[11px] text-slate-400 mt-1 font-mono">{{ $row->key }}</p>
                    </div>
                @endforeach

                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save client settings</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
