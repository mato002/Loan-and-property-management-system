<?php

namespace App\Support;

use App\Models\PropertyPortalSetting;
use Closure;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExport
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_WORD = 'word';

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     * @param  array<string,mixed>  $options
     */
    public static function stream(string $filenameBase, array $headers, Closure $rows, string $format, array $options = []): StreamedResponse
    {
        $format = strtolower(trim($format));

        return match ($format) {
            self::FORMAT_PDF => self::streamPdf($filenameBase.'.pdf', $headers, $rows, $options),
            self::FORMAT_WORD => self::streamWordHtml($filenameBase.'.doc', $headers, $rows),
            default => CsvExport::stream($filenameBase.'.csv', $headers, $rows),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  Closure(): iterable<array<int, scalar|null>>  $rows
     * @param  array<string,mixed>  $options
     */
    private static function streamPdf(string $filename, array $headers, Closure $rows, array $options = []): StreamedResponse
    {
        $html = self::htmlTable($headers, $rows, $options);

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
     * @param  array<string,mixed>  $options
     */
    private static function htmlTable(array $headers, Closure $rows, array $options = []): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $brandName = trim((string) PropertyPortalSetting::getValue('company_name', ''));
        if ($brandName === '') {
            $brandName = (string) config('app.name', 'Property Management System');
        }
        $brandTagline = trim((string) PropertyPortalSetting::getValue('company_tagline', ''));
        $brandLogo = trim((string) PropertyPortalSetting::getValue('company_logo_url', ''));
        $logoSrc = self::resolveLogoSrc($brandLogo);
        $contactParts = array_values(array_filter([
            trim((string) PropertyPortalSetting::getValue('contact_phone', '')),
            trim((string) PropertyPortalSetting::getValue('contact_email_primary', '')),
            trim((string) PropertyPortalSetting::getValue('contact_address', '')),
        ], static fn ($v) => $v !== ''));
        $generatedAt = now()->format('d M Y, h:i A');
        $copyright = 'Copyright © '.now()->format('Y').' '.$brandName.'. All rights reserved.';
        $reportTitle = trim((string) ($options['title'] ?? Str::headline(str_replace('-', ' ', pathinfo((string) ($options['filename_base'] ?? ''), PATHINFO_FILENAME)))));
        if ($reportTitle === '') {
            $reportTitle = 'Report';
        }
        $reportSubtitle = trim((string) ($options['subtitle'] ?? ''));
        $summary = is_array($options['summary'] ?? null) ? $options['summary'] : [];
        $th = implode('', array_map(fn ($h) => '<th>'.$esc($h).'</th>', $headers));

        $trs = '';
        foreach ($rows() as $row) {
            $tds = '';
            foreach ($row as $cell) {
                $tds .= '<td>'.$esc($cell).'</td>';
            }
            $trs .= '<tr>'.$tds.'</tr>';
        }

        $summaryHtml = '';
        if ($summary !== []) {
            $summaryRows = '';
            foreach ($summary as $label => $value) {
                $summaryRows .= '<tr><th>'.$esc($label).'</th><td>'.$esc($value).'</td></tr>';
            }
            $summaryHtml = '<div class="summary-wrap"><table class="summary-table">'.$summaryRows.'</table></div>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans, Arial, sans-serif;font-size:12px;color:#111;margin:18px 22px;}
            .letterhead{border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:12px;}
            .brand{display:table;width:100%;}
            .brand-logo,.brand-copy{display:table-cell;vertical-align:top;}
            .brand-logo{width:98px;}
            .brand-logo img{max-width:86px;max-height:86px;display:block;}
            .brand-name{font-size:16px;font-weight:700;line-height:1.25;}
            .brand-tagline{font-size:11px;color:#333;margin-top:2px;}
            .brand-contact{font-size:10px;color:#333;margin-top:4px;line-height:1.5;}
            .report-title{font-size:15px;font-weight:700;margin:8px 0 2px;}
            .report-subtitle{font-size:11px;color:#444;margin-bottom:8px;}
            .meta{font-size:10px;color:#555;margin:6px 0 10px;}
            table{width:100%;border-collapse:collapse;}
            th,td{border:1px solid #ddd;padding:6px;vertical-align:top;word-wrap:break-word;}
            th{background:#f3f4f6;text-align:left;font-weight:700;font-size:10px;text-transform:uppercase;}
            .summary-wrap{margin-top:12px;display:flex;justify-content:flex-end;}
            .summary-table{width:48%;border-collapse:collapse;}
            .summary-table th,.summary-table td{border:1px solid #ddd;padding:6px;font-size:11px;}
            .summary-table th{background:#fafafa;width:55%;}
            .footer{margin-top:12px;padding-top:6px;border-top:1px solid #ddd;font-size:10px;color:#555;text-align:center;}
        </style></head><body>'.
            '<div class="letterhead"><div class="brand">'.
                '<div class="brand-logo">'.($logoSrc !== '' ? '<img src="'.$esc($logoSrc).'" alt="" />' : '').'</div>'.
                '<div class="brand-copy">'.
                    '<div class="brand-name">'.$esc($brandName).'</div>'.
                    ($brandTagline !== '' ? '<div class="brand-tagline">'.$esc($brandTagline).'</div>' : '').
                    ($contactParts !== [] ? '<div class="brand-contact">'.implode('<br>', array_map($esc, $contactParts)).'</div>' : '').
                '</div>'.
            '</div></div>'.
            '<div class="report-title">'.$esc($reportTitle).'</div>'.
            ($reportSubtitle !== '' ? '<div class="report-subtitle">'.$esc($reportSubtitle).'</div>' : '').
            '<div class="meta">Generated on '.$esc($generatedAt).'</div>'.
            '<table><thead><tr>'.$th.'</tr></thead><tbody>'.$trs.'</tbody></table>'.
            $summaryHtml.
            '<div class="footer">'.$esc($copyright).'</div>'.
            '</body></html>';
    }

    private static function resolveLogoSrc(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }

        if (str_starts_with($logo, 'data:image/')) {
            return $logo;
        }

        if (preg_match('/^https?:\/\//i', $logo) === 1) {
            return $logo;
        }

        $candidate = $logo;
        if (str_starts_with($candidate, '/')) {
            $candidate = ltrim($candidate, '/');
        }
        $publicPath = public_path($candidate);
        if (is_file($publicPath)) {
            $mime = mime_content_type($publicPath) ?: 'image/png';
            $content = @file_get_contents($publicPath);
            if ($content !== false) {
                return 'data:'.$mime.';base64,'.base64_encode($content);
            }
        }

        return $logo;
    }
}

