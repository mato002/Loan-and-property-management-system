<?php

namespace App\Support;

use Closure;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExport
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_WORD = 'word';

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     */
    public static function stream(string $filenameBase, array $headers, Closure $rows, string $format): StreamedResponse
    {
        $format = strtolower(trim($format));

        return match ($format) {
            self::FORMAT_PDF => self::streamPdf($filenameBase.'.pdf', $headers, $rows),
            self::FORMAT_WORD => self::streamWordHtml($filenameBase.'.doc', $headers, $rows),
            default => CsvExport::stream($filenameBase.'.csv', $headers, $rows),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     */
    private static function streamPdf(string $filename, array $headers, Closure $rows): StreamedResponse
    {
        $html = self::htmlTable($headers, $rows);

        return response()->streamDownload(function () use ($html) {
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Simple Word export via HTML (.doc). Opens in Word and LibreOffice.
     *
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     */
    private static function streamWordHtml(string $filename, array $headers, Closure $rows): StreamedResponse
    {
        $html = self::htmlTable($headers, $rows);

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'application/msword; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     */
    private static function htmlTable(array $headers, Closure $rows): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $th = implode('', array_map(fn ($h) => '<th>'.$esc($h).'</th>', $headers));

        $trs = '';
        foreach ($rows() as $row) {
            $tds = '';
            foreach ($row as $cell) {
                $tds .= '<td>'.$esc($cell).'</td>';
            }
            $trs .= '<tr>'.$tds.'</tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans, Arial, sans-serif;font-size:12px;color:#111;}
            table{width:100%;border-collapse:collapse;}
            th,td{border:1px solid #ddd;padding:6px;vertical-align:top;}
            th{background:#f3f4f6;text-align:left;font-weight:700;}
        </style></head><body><table><thead><tr>'.$th.'</tr></thead><tbody>'.$trs.'</tbody></table></body></html>';
    }
}

