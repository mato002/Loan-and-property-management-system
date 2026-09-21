<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PassionLegacyRegisterPdfTextExtractor
{
    public function extract(string $path): string
    {
        $candidates = $this->extractCandidates($path);
        foreach ($candidates as $text) {
            if ($this->isUsableExtractedText($text)) {
                return $text;
            }
        }

        throw new \RuntimeException(
            'Could not read text from this PDF. Export the Co-op statement as TXT and upload that file, '
            .'or install pdftotext (Poppler) / Python pypdf on the server.'
        );
    }

    /**
     * All non-empty extracts from available backends, longest first.
     *
     * @return list<string>
     */
    public function extractCandidates(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['txt', 'text', 'log'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) && trim($contents) !== '' ? [$contents] : [];
        }

        if ($extension !== 'pdf') {
            throw new \InvalidArgumentException('Expected a .pdf or .txt file.');
        }

        $candidates = [];
        $seen = [];
        foreach ($this->extractors($path) as $extractor) {
            try {
                $text = $extractor();
            } catch (\Throwable) {
                $text = null;
            }
            if (! is_string($text)) {
                continue;
            }
            $text = trim($text);
            if (strlen($text) < 80) {
                continue;
            }
            $hash = sha1($text);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $candidates[] = $text;
        }

        usort($candidates, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidates;
    }

    /**
     * Extract text without requiring Passion-register markers.
     */
    public function extractAny(string $path, int $minLength = 80): string
    {
        foreach ($this->extractCandidates($path) as $text) {
            if (strlen(trim($text)) >= $minLength) {
                return $text;
            }
        }

        throw new \RuntimeException(
            'Could not read text from this PDF. Export the statement as TXT and upload that file, '
            .'or install pdftotext (Poppler) / Python pypdf on the server.'
        );
    }

    /**
     * @return list<callable(): ?string>
     */
    private function extractors(string $path): array
    {
        return [
            fn () => $this->viaPdftotext($path),
            fn () => $this->viaInflatedTj($path),
            fn () => $this->viaPythonPypdf($path),
            fn () => $this->viaRawPdfRegex($path),
        ];
    }

    private function viaPdftotext(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        try {
            $process->setTimeout(20);
            $process->mustRun();
        } catch (ProcessFailedException) {
            return null;
        }

        return $process->getOutput();
    }

    private function viaPythonPypdf(string $path): ?string
    {
        $script = <<<'PY'
import sys
path = sys.argv[1]
try:
    from pypdf import PdfReader
except ImportError:
    from PyPDF2 import PdfReader
reader = PdfReader(path)
print("".join((page.extract_text() or "") for page in reader.pages))
PY;

        foreach (['python3', 'python', 'py'] as $binary) {
            $process = new Process([$binary, '-c', $script, $path]);
            try {
                $process->setTimeout(20);
                $process->mustRun();
            } catch (ProcessFailedException) {
                continue;
            }

            $output = $process->getOutput();
            if (is_string($output) && trim($output) !== '') {
                return $output;
            }
        }

        return null;
    }

    /**
     * Co-op statement PDFs store text in FlateDecode streams as `(...) Tj`.
     * Inflating in PHP avoids depending on pdftotext / Python on production.
     */
    private function viaInflatedTj(string $path): ?string
    {
        $content = file_get_contents($path);
        if (! is_string($content) || $content === '') {
            return null;
        }

        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $matches) === false) {
            return null;
        }

        $parts = [];
        foreach ($matches[1] as $stream) {
            $decoded = $this->inflatePdfStream((string) $stream);
            if ($decoded === null) {
                continue;
            }

            if (preg_match_all('/\(([^\\\\()]*(?:\\\\.[^\\\\()]*)*)\)\s*Tj/', $decoded, $tjs) === false) {
                continue;
            }

            foreach ($tjs[1] as $part) {
                $parts[] = $this->decodePdfLiteral((string) $part);
            }
        }

        $text = trim(implode("\n", $parts));

        return $text !== '' ? $text : null;
    }

    private function decodePdfLiteral(string $part): string
    {
        $part = stripcslashes($part);
        if (str_contains($part, "\x00")) {
            $stripped = str_replace("\x00", '', $part);
            if ($stripped !== '') {
                return $stripped;
            }
        }

        return $part;
    }

    private function inflatePdfStream(string $stream): ?string
    {
        $stream = ltrim($stream, "\r\n");
        $candidates = [$stream, rtrim($stream, "\r\n")];

        foreach ($candidates as $candidate) {
            foreach (['gzuncompress', 'gzinflate', 'gzdecode'] as $fn) {
                if (! function_exists($fn)) {
                    continue;
                }
                $out = @$fn($candidate);
                if (is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }

        return null;
    }

    private function isUsableExtractedText(?string $text): bool
    {
        if (! is_string($text)) {
            return false;
        }

        $text = trim($text);
        if ($text === '' || strlen($text) < 80) {
            return false;
        }

        $markers = [
            '/^[A-Z]\d{5}[A-Z]/m',
            '/Property Register/i',
            '/PROPERTY UNITS LISTING/i',
            '/ACTIVE TENANT & LEASES/i',
            '/\bTNT\d{4,}\b/',
            '/UNIT NO PROPERTY TENANT/i',
            '/BILLS LISTING/i',
            '/PAYMENT VOUCHER LISTING/i',
            '/STATEMENT OF ACCOUNT/i',
            '/MPESAC2B_/i',
        ];

        foreach ($markers as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function viaRawPdfRegex(string $path): ?string
    {
        $content = file_get_contents($path);
        if (! is_string($content) || $content === '') {
            return null;
        }

        preg_match_all('/\(([^\\\\)]*(?:\\\\.[^\\\\)]*)*)\)\s*T[jJ]/', $content, $matches);
        if (($matches[1] ?? []) === []) {
            return null;
        }

        $parts = array_map(static function (string $part): string {
            return stripcslashes($part);
        }, $matches[1]);

        return implode("\n", $parts);
    }
}
