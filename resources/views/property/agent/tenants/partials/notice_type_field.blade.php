@php
    $selectedNoticeType = \App\Support\Property\TenantNoticeTypes::normalize((string) old('notice_type', $selected ?? 'vacate'));
    $noticeTypeOptions = \App\Support\Property\TenantNoticeTypes::options($selectedNoticeType);
@endphp

<div x-data="{ adding: false, custom: '' }">
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
    <div class="mt-1 flex gap-2">
        <select
            name="notice_type"
            x-ref="typeSelect"
            required
            class="min-w-0 flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
        >
            @foreach ($noticeTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedNoticeType === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button
            type="button"
            @click="adding = !adding; $nextTick(() => { if (adding) $refs.customType?.focus() })"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            title="Add a notice type"
            aria-label="Add a notice type"
        >+</button>
    </div>
    <div x-show="adding" x-cloak class="mt-2 flex gap-2">
        <input
            type="text"
            x-ref="customType"
            x-model="custom"
            maxlength="64"
            placeholder="New type, e.g. Rent increase"
            class="min-w-0 flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            @keydown.enter.prevent="
                const raw = custom.trim();
                if (!raw) return;
                const value = raw.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                if (!value) return;
                const label = value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                let opt = Array.from($refs.typeSelect.options).find((o) => o.value === value);
                if (!opt) {
                    opt = new Option(label, value, true, true);
                    $refs.typeSelect.add(opt);
                }
                $refs.typeSelect.value = value;
                custom = '';
                adding = false;
            "
        />
        <button
            type="button"
            class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900"
            @click="
                const raw = custom.trim();
                if (!raw) return;
                const value = raw.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                if (!value) return;
                const label = value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                let opt = Array.from($refs.typeSelect.options).find((o) => o.value === value);
                if (!opt) {
                    opt = new Option(label, value, true, true);
                    $refs.typeSelect.add(opt);
                }
                $refs.typeSelect.value = value;
                custom = '';
                adding = false;
            "
        >Add</button>
    </div>
    <p class="mt-1 text-[11px] text-slate-500">Choose a saved type, or use + to add one. New types stay in the list after you save a notice.</p>
    @error('notice_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
