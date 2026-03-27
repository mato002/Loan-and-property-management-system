<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     */
    public static function stream(string $filename, array $headers, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM so Excel opens UTF-8 nicely
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);
            foreach ($rows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

