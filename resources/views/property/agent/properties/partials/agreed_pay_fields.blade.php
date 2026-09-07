@props([
    'agreedPayDay' => null,
    'agreedPayNotes' => null,
    'compact' => false,
])

<div @class(['space-y-3', 'sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0' => ! $compact])>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agreed pay day</label>
        <select name="agreed_pay_day" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="">Not set</option>
            @for ($day = 1; $day <= 28; $day++)
                <option value="{{ $day }}" @selected((int) old('agreed_pay_day', $agreedPayDay ?? 0) === $day)>
                    {{ $day }}{{ in_array($day % 10, [1, 2, 3], true) && ! in_array($day, [11, 12, 13], true) ? match ($day % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd' } : 'th' }} of month
                </option>
            @endfor
        </select>
        @error('agreed_pay_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    @unless ($compact)
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Pay schedule notes</label>
            <input
                type="text"
                name="agreed_pay_notes"
                value="{{ old('agreed_pay_notes', $agreedPayNotes) }}"
                placeholder="e.g. Pay after rent collected"
                class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            />
            @error('agreed_pay_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    @endunless
</div>
