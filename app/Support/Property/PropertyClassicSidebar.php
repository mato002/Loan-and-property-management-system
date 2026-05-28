<?php

namespace App\Support\Property;

final class PropertyClassicSidebar
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        /** @var list<array<string, mixed>> $sections */
        $sections = config('property.classic_sidebar', []);

        return array_map(static fn (array $section): array => [
            ...$section,
            'items' => self::normalizeItems($section['items'] ?? []),
        ], $sections);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function normalizeItems(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'group') {
                $children = self::normalizeItems($item['items'] ?? []);
                if ($children === []) {
                    continue;
                }

                $out[] = [
                    'label' => (string) ($item['label'] ?? 'Group'),
                    'children' => $children,
                    'badge' => $item['badge'] ?? null,
                    'requires_superadmin' => $item['requires_superadmin'] ?? null,
                    'requires_pm_permission' => $item['requires_pm_permission'] ?? null,
                ];

                continue;
            }

            $out[] = $item;
        }

        return $out;
    }
}
