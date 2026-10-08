<?php

namespace App\Services\Property;

use RuntimeException;

final class PassionTerminatedLeasesSpreadsheetParser
{
    /**
     * @return list<array{
     *     property_code: string,
     *     property_name: string,
     *     unit_label: string,
     *     account_number: string,
     *     tenant_name: string,
     *     phone: ?string,
     *     email: ?string,
     *     account_balance: ?float,
     *     monthly_rent: ?float,
     *     lease_start: ?string,
     *     lease_end: ?string
     * }>
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = in_array($extension, ['csv', 'txt'], true)
            ? $this->rowsFromCsv($path)
            : $this->rowsFromSpreadsheetMl($path);

        if ($rows === []) {
            return [];
        }

        $headerIndex = null;
        $headers = [];
        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($cell) => $this->headerKey((string) $cell), $row);
            if (in_array('account_number', $normalized, true) && in_array('tenant_name', $normalized, true)) {
                $headerIndex = $index;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Could not find the header row (Property, Unit No, A/c No, Name).');
        }

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $record = $this->recordFromRow($headers, $row);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, mixed>|null
     */
    private function recordFromRow(array $headers, array $row): ?array
    {
        $values = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $values[$header] = trim((string) ($row[$index] ?? ''));
        }

        $account = strtoupper(preg_replace('/\s+/', '', (string) ($values['account_number'] ?? '')) ?? '');
        if (! preg_match('/^TNT\d+$/', $account)) {
            return null;
        }

        $propertyCell = (string) ($values['property'] ?? '');
        $propertyCode = '';
        $propertyName = $propertyCell;
        if (preg_match('/\[([A-Z]\d{5}[A-Z]?)\]\s*(.*)$/i', $propertyCell, $match)) {
            $propertyCode = strtoupper($match[1]);
            $propertyName = trim($match[2]);
        }

        $tenantName = PassionLegacyTextNormalizer::cleanTenantName($values['tenant_name'] ?? '');
        if ($tenantName === '') {
            return null;
        }

        $phone = trim((string) ($values['phone'] ?? ''));
        $email = strtolower(trim((string) ($values['email'] ?? '')));

        return [
            'property_code' => $propertyCode,
            'property_name' => $propertyName,
            'unit_label' => trim((string) ($values['unit_label'] ?? '')),
            'account_number' => $account,
            'tenant_name' => $tenantName,
            'phone' => $phone !== '' ? $phone : null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'account_balance' => $this->money($values['account_balance'] ?? null),
            'monthly_rent' => $this->money($values['monthly_rent'] ?? null),
            'lease_start' => $this->date($values['lease_start'] ?? null),
            'lease_end' => $this->date($values['lease_end'] ?? null),
        ];
    }

    private function headerKey(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace(['a/c', 'acc'], 'account', $header);

        return match (true) {
            str_contains($header, 'property') => 'property',
            str_contains($header, 'unit') => 'unit_label',
            str_contains($header, 'account') && str_contains($header, 'no') => 'account_number',
            $header === 'name' || str_contains($header, 'tenant') => 'tenant_name',
            str_contains($header, 'bal') => 'account_balance',
            str_contains($header, 'phone') => 'phone',
            str_contains($header, 'email') => 'email',
            str_contains($header, 'rent') => 'monthly_rent',
            str_contains($header, 'from') || str_contains($header, 'start') => 'lease_start',
            str_contains($header, 'to') || str_contains($header, 'end') => 'lease_end',
            default => '',
        };
    }

    /**
     * @return list<list<string>>
     */
    private function rowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not read {$path}");
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($cell) => trim((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Excel 2003 XML (.xls) export used by the legacy terminated-leases report.
     *
     * @return list<list<string>>
     */
    private function rowsFromSpreadsheetMl(string $path): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('This file is not an Excel spreadsheet. Export the terminated list as .xls or .csv.');
        }

        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $sheetRows = $xml->xpath('//ss:Worksheet[1]/ss:Table/ss:Row') ?: $xml->xpath('//ss:Row') ?: [];
        $rows = [];

        foreach ($sheetRows as $sheetRow) {
            $cells = [];
            foreach ($sheetRow->children('urn:schemas-microsoft-com:office:spreadsheet') as $cell) {
                if ($cell->getName() !== 'Cell') {
                    continue;
                }
                $index = (string) ($cell->attributes('urn:schemas-microsoft-com:office:spreadsheet')['Index'] ?? '');
                if ($index !== '') {
                    while (count($cells) < ((int) $index) - 1) {
                        $cells[] = '';
                    }
                }
                $data = $cell->children('urn:schemas-microsoft-com:office:spreadsheet')->Data;
                $cells[] = trim((string) $data);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private function money(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace([',', ' '], '', $value);
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match) === 1) {
            return $match[1].'-'.$match[2].'-'.$match[3];
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            $unix = ((int) floor((float) $value) - 25569) * 86400;

            return gmdate('Y-m-d', $unix);
        }

        return null;
    }
}
