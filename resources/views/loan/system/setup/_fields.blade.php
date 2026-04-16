@foreach ($settings as $row)
    @continue(in_array($row->key, ['logo_url', 'favicon_url'], true))
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

@php
    $logo = optional($settings->firstWhere('key', 'logo_url'))->value ?? '';
    $favicon = optional($settings->firstWhere('key', 'favicon_url'))->value ?? '';
@endphp

<div class="border-t border-slate-200 pt-5 space-y-4">
    <h3 class="text-sm font-semibold text-slate-700">Brand assets upload</h3>

    <div>
        <label for="logo_file" class="block text-xs font-semibold text-slate-600 mb-1">Upload logo</label>
        <input id="logo_file" type="file" name="logo_file" accept="image/*" class="w-full rounded-lg border-slate-200 text-sm" />
        <p class="text-[11px] text-slate-500 mt-1">PNG/JPG/SVG supported, max 3MB.</p>
        @error('logo_file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @if ($logo)
            <div class="mt-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-2">
                <img src="{{ $logo }}" alt="Current logo" class="h-10 w-auto rounded bg-white p-1 border border-slate-200">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                    Remove current logo
                </label>
            </div>
        @endif
    </div>

    <div>
        <label for="favicon_file" class="block text-xs font-semibold text-slate-600 mb-1">Upload favicon</label>
        <input id="favicon_file" type="file" name="favicon_file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml" class="w-full rounded-lg border-slate-200 text-sm" />
        <p class="text-[11px] text-slate-500 mt-1">PNG/ICO/SVG supported, max 2MB.</p>
        @error('favicon_file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @if ($favicon)
            <div class="mt-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-2">
                <img src="{{ $favicon }}" alt="Current favicon" class="h-8 w-8 rounded bg-white p-1 border border-slate-200">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remove_favicon" value="1" class="rounded border-slate-300">
                    Remove current favicon
                </label>
            </div>
        @endif
    </div>
</div>
