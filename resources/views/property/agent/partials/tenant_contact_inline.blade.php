@props([
    'tenant',
    'phoneE164' => '',
])

<div {{ $attributes->merge(['class' => 'mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400']) }}>
    @if ($phoneE164 !== '')
        <a href="tel:{{ $phoneE164 }}" class="text-indigo-600 hover:text-indigo-700">
            <span class="font-medium text-slate-500">Phone:</span> {{ $tenant->phone }}
        </a>
    @else
        <span><span class="font-medium text-slate-500">Phone:</span> —</span>
    @endif
    @if (! empty($tenant->email))
        <a href="mailto:{{ $tenant->email }}" class="text-indigo-600 hover:text-indigo-700">
            <span class="font-medium text-slate-500">Email:</span> {{ $tenant->email }}
        </a>
    @endif
    @if (! empty($tenant->account_number))
        <span><span class="font-medium text-slate-500">Account:</span> {{ $tenant->account_number }}</span>
    @endif
</div>
