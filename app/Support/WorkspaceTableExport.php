<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fallback exporter for workspace tables that already have columns + rows on the page.
 */
final class WorkspaceTableExport
{
    public static function fromRequest(string $title, array $columns, array $rows): ?StreamedResponse
    {
        $format = strtolower(trim((string) request()->query('export', '')));
        if (! in_array($format, TabularExport::REVENUE_FORMATS, true)) {
            return null;
        }
        if (trim((string) request()->query('export_scope', '')) !== '') {
            return null;
        }
        if ($columns === [] || $rows === []) {
            return null;
        }

        $headers = [];
        $keepIndexes = [];
        foreach (array_values($columns) as $index => $column) {
            $label = trim(self::cellText($column));
            if ($label === '' || strcasecmp($label, 'Actions') === 0) {
                continue;
            }
            $headers[] = $label;
            $keepIndexes[] = $index;
        }
        if ($headers === []) {
            return null;
        }

        $filename = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'register';

        return TabularExport::stream(
            $filename.'-'.now()->format('Ymd_His'),
            $headers,
            function () use ($rows, $keepIndexes) {
                foreach ($rows as $row) {
                    $cells = array_values((array) $row);
                    $out = [];
                    foreach ($keepIndexes as $index) {
                        $out[] = self::cellText($cells[$index] ?? '');
                    }
                    yield $out;
                }
            },
            $format,
            ['title' => $title],
        );
    }

    public static function cellText(mixed $cell): string
    {
        $html = $cell instanceof HtmlString ? $cell->toHtml() : (string) $cell;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
