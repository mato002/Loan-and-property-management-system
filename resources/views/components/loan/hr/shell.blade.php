@props([
    'sectionMeta' => [],
    'sectionKey' => null,
    'hrSections' => [],
    'workspaceTabs' => [],
    'searchCommands' => [],
    'focusModes' => [],
])

@php
    $title = (string) ($sectionMeta['title'] ?? 'HR workspace');
    $subtitle = (string) ($sectionMeta['description'] ?? '');
@endphp

<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle !== '' ? $subtitle : null" :show-quick-links="false">
        @if (! empty($hrSections))
            <nav class="mb-4 flex flex-nowrap gap-1 overflow-x-auto custom-scrollbar rounded-lg border border-slate-200 bg-white px-1.5 py-1 shadow-sm">
                @foreach ($hrSections as $section)
                    @php
                        $key = (string) ($section['key'] ?? '');
                        $label = (string) ($section['label'] ?? $key);
                        $isActive = $key !== '' && $key === (string) $sectionKey;
                    @endphp
                    @if ($key !== '' && Route::has('loan.hr.section'))
                        <a
                            href="{{ route('loan.hr.section', ['section' => $key]) }}"
                            @class([
                                'shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold min-h-[32px] border border-transparent whitespace-nowrap',
                                'bg-[#0f766e] text-white' => $isActive,
                                'text-slate-700 hover:bg-slate-100' => ! $isActive,
                            ])
                        >
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </nav>
        @endif

        {{ $slot }}
    </x-loan.page>
</x-loan-layout>
