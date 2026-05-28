@props([
    /** @var list<array{label: string, value: string, hint?: string, tone?: string}> $items */
    'items' => [],
])

@php
    $stats = collect($items)->map(static function (array $item): array {
        $tone = (string) ($item['tone'] ?? 'default');

        return [
            'label' => (string) ($item['label'] ?? ''),
            'value' => (string) ($item['value'] ?? ''),
            'hint' => $item['hint'] ?? null,
            'emphasis' => $tone !== 'default',
            'tone' => match ($tone) {
                'danger' => 'rose',
                'warning' => 'amber',
                default => 'emerald',
            },
        ];
    })->all();
@endphp

<x-property.responsive.stat-card-grid :stats="$stats" dense />
