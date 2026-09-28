@php
    $propertyUrl = (string) ($propertyUrl ?? '#');
    $propertyName = (string) ($propertyName ?? '');
@endphp
<div class="flex items-center gap-2 min-w-0" data-row-ignore-click>
    <button
        type="button"
        @click.stop="open = !open"
        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-base font-bold leading-none text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200"
        :aria-expanded="open.toString()"
        title="Monthly share breakdown"
    >
        <span x-text="open ? '−' : '+'"></span>
    </button>
    <a href="{{ $propertyUrl }}" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 break-words min-w-0">{{ $propertyName }}</a>
</div>
