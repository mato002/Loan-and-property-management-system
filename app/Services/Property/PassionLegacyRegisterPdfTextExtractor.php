<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PassionLegacyRegisterPdfTextExtractor
{
    public function extract(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['txt', 'text', 'log'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        }

        if ($extension !== 'pdf') {
            throw new \InvalidArgumentException('Passion register import expects a .pdf or .txt file.');
        }

        foreach ($this->extractors($path) as $extractor) {
            $text = $extractor();
            if ($this->isUsableExtractedText($text)) {
                return $text;
            }
        }

        throw new \RuntimeException(
            'Could not extract text from the PDF. Save the register as plain text (.txt) and pass that file instead, '
            .'or install pdftotext (Poppler) on the server.'
        );
    }

    /**
     * @return list<callable(): ?string>
     */
    private function extractors(string $path): array
    {
        return [
            fn () => $this->viaPdftotext($path),
            fn () => $this->viaPythonPypdf($path),
            fn () => $this->viaRawPdfRegex($path),
        ];
    }

    private function viaPdftotext(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        try {
            $process->setTimeout(120);
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

        $process = new Process(['python', '-c', $script, $path]);
        try {
            $process->setTimeout(120);
            $process->mustRun();
        } catch (ProcessFailedException) {
            return null;
        }

        return $process->getOutput();
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
