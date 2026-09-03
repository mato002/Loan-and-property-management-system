<?php

namespace App\Support;

class TableExportLinks
{
    /** @var list<string> */
    public const STANDARD_FORMATS = [
        TabularExport::FORMAT_CSV,
        'xls',
        TabularExport::FORMAT_PDF,
        TabularExport::FORMAT_WORD,
    ];

    /** @var list<string> */
    public const BASIC_FORMATS = [
        TabularExport::FORMAT_CSV,
        TabularExport::FORMAT_PDF,
        TabularExport::FORMAT_WORD,
    ];

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $formats
     * @param  array<string, mixed>  $routeParams
     * @return array{csvUrl?: string, xlsUrl?: string, pdfUrl?: string, wordUrl?: string}
     */
    public static function forRoute(string $route, array $query = [], array $formats = self::STANDARD_FORMATS, array $routeParams = []): array
    {
        return self::buildUrls(
            fn (string $format) => route($route, array_merge($routeParams, $query, ['export' => $format]), false),
            $formats
        );
    }

    /**
     * @param  array<string, mixed>  $extraQuery
     * @param  list<string>  $formats
     * @return array{csvUrl?: string, xlsUrl?: string, pdfUrl?: string, wordUrl?: string}
     */
    public static function forCurrentUrl(array $extraQuery = [], array $formats = self::BASIC_FORMATS): array
    {
        $baseQuery = array_merge(request()->query(), $extraQuery);

        return self::buildUrls(
            fn (string $format) => url()->current().'?'.http_build_query(array_filter(array_merge($baseQuery, ['export' => $format]))),
            $formats
        );
    }

    /**
     * @param  Closure(string): string  $urlBuilder
     * @param  list<string>  $formats
     * @return array{csvUrl?: string, xlsUrl?: string, pdfUrl?: string, wordUrl?: string}
     */
    private static function buildUrls(callable $urlBuilder, array $formats): array
    {
        $urls = [];

        foreach ($formats as $format) {
            $key = match ($format) {
                'xls' => 'xlsUrl',
                TabularExport::FORMAT_CSV => 'csvUrl',
                TabularExport::FORMAT_PDF => 'pdfUrl',
                TabularExport::FORMAT_WORD => 'wordUrl',
                default => null,
            };

            if ($key !== null) {
                $urls[$key] = $urlBuilder($format);
            }
        }

        return $urls;
    }
}
