<?php

namespace App\Services\Property;

use DOMDocument;
use RuntimeException;

/** Reads the XML-based .xls reports exported by the legacy EZEN system. */
final class EzenSpreadsheetMlReader
{
    private const NS = 'urn:schemas-microsoft-com:office:spreadsheet';

    /** @return list<list<string>> */
    public function readRows(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $xml = file_get_contents($path);
        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('Spreadsheet file is empty.');
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('Spreadsheet XML must not contain a document type or entity declaration.');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded || $document->documentElement?->localName !== 'Workbook') {
            throw new RuntimeException('Expected an EZEN Excel XML (.xls) workbook.');
        }

        $worksheets = $document->getElementsByTagNameNS(self::NS, 'Worksheet');
        if ($worksheets->length === 0) {
            throw new RuntimeException('No worksheets were found in the Excel XML file.');
        }

        $rows = [];
        foreach ($worksheets->item(0)->getElementsByTagNameNS(self::NS, 'Row') as $rowNode) {
            $row = [];
            $nextColumn = 1;
            foreach ($rowNode->getElementsByTagNameNS(self::NS, 'Cell') as $cell) {
                $index = (int) $cell->getAttributeNS(self::NS, 'Index');
                if ($index > 0) {
                    $nextColumn = $index;
                }
                while (count($row) < $nextColumn - 1) {
                    $row[] = '';
                }
                $data = $cell->getElementsByTagNameNS(self::NS, 'Data')->item(0);
                $row[] = $data?->textContent ?? '';
                $nextColumn++;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
