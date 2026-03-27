@foreach ($settings as $row)
    <div>
        <label for="setting_{{ $row->key }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $row->label ?? $row->key }}</label>
        @if ($row->key === 'loan_repayment_allocation_order')
            <select id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" class="w-full rounded-lg border-slate-200 text-sm">
                @php
                    $cur = (string) old('settings.'.$row->key, $row->value);
                    $opts = [
                        'fees,interest,principal' => 'Fees → Interest → Principal (recommended)',
                        'interest,principal,fees' => 'Interest → Principal → Fees',
                        'principal,interest,fees' => 'Principal → Interest → Fees',
                        'interest,fees,principal' => 'Interest → Fees → Principal',
                    ];
                @endphp
                @foreach ($opts as $val => $label)
                    <option value="{{ $val }}" @selected($cur === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-slate-500 mt-1">Controls how repayments reduce outstanding buckets on posting.</p>
        @elseif (in_array($row->key, ['about_us', 'company_address', 'maintenance_notice', 'payment_automation', 'approval_levels', 'client_loyalty_points'], true))
            <textarea id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" rows="{{ $row->key === 'about_us' ? 5 : 4 }}" class="w-full rounded-lg border-slate-200 text-sm">{{ old('settings.'.$row->key, $row->value) }}</textarea>
        @else
            <input id="setting_{{ $row->key }}" name="settings[{{ $row->key }}]" value="{{ old('settings.'.$row->key, $row->value) }}" class="w-full rounded-lg border-slate-200 text-sm" />
        @endif
        <p class="text-[11px] text-slate-400 mt-1 font-mono">{{ $row->key }}</p>
    </div>
@endforeach
