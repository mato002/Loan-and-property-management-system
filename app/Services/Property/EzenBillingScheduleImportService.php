<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class EzenBillingScheduleImportService
{
    /**
     * @return array{
     *     parsed:int,
     *     with_utility:int,
     *     leases_updated:int,
     *     skipped_zero:int,
     *     skipped_unmatched:int,
     *     skipped_ambiguous:int,
     *     utility_applied:float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false, bool $updateExisting = true): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $text = $this->readText($path);
        $rows = $this->parseRows($text);

        $summary = [
            'parsed' => count($rows),
            'with_utility' => 0,
            'leases_updated' => 0,
            'skipped_zero' => 0,
            'skipped_unmatched' => 0,
            'skipped_ambiguous' => 0,
            'utility_applied' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        $leasesByName = $this->leasesIndexedByName();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            if ($row['utility'] <= 0.009) {
                $summary['skipped_zero']++;
                continue;
            }
            $summary['with_utility']++;

            try {
                $result = $this->applyRow($row, $leasesByName, $dryRun, $updateExisting, $rowNum);
                $summary['leases_updated'] += $result['updated'] ? 1 : 0;
                $summary['skipped_unmatched'] += $result['unmatched'] ? 1 : 0;
                $summary['skipped_ambiguous'] += $result['ambiguous'] ? 1 : 0;
                $summary['utility_applied'] += $result['amount'];
                $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
            } catch (RuntimeException $e) {
                $summary['errors'][] = 'Row '.$rowNum.' ('.$row['name'].'): '.$e->getMessage();
            }
        }

        $summary['utility_applied'] = round($summary['utility_applied'], 2);

        return $summary;
    }

    /**
     * @param  array{name:string, rent:float, utility:float, invoice:?string}  $row
     * @param  array<string, list<array{tenant:PmTenant, lease:PmLease}>>  $leasesByName
     * @return array{updated:bool, unmatched:bool, ambiguous:bool, amount:float, warnings:list<string>}
     */
    private function applyRow(array $row, array $leasesByName, bool $dryRun, bool $updateExisting, int $rowNum): array
    {
        $warnings = [];
        $candidates = $this->matchLeases($row, $leasesByName);

        if ($candidates === []) {
            $warnings[] = 'Row '.$rowNum.': no current tenant for "'.$row['name'].'" rent '.$row['rent'].' — skipped.';

            return ['updated' => false, 'unmatched' => true, 'ambiguous' => false, 'amount' => 0.0, 'warnings' => $warnings];
        }

        if (count($candidates) > 1) {
            $labels = collect($candidates)->map(fn (array $hit) => $hit['tenant']->account_number)->unique()->implode(', ');
            $warnings[] = 'Row '.$rowNum.': ambiguous "'.$row['name'].'" rent '.$row['rent'].' ('.$labels.') — skipped.';

            return ['updated' => false, 'unmatched' => false, 'ambiguous' => true, 'amount' => 0.0, 'warnings' => $warnings];
        }

        $lease = $candidates[0]['lease'];
        $existing = collect((array) ($lease->utility_expenses ?? []))
            ->sum(fn ($item) => is_array($item) ? (float) ($item['amount'] ?? 0) : 0);
        if (! $updateExisting && $existing > 0.009) {
            $warnings[] = 'Row '.$rowNum.': '.$candidates[0]['tenant']->account_number.' already has utilities — skipped.';

            return ['updated' => false, 'unmatched' => false, 'ambiguous' => false, 'amount' => 0.0, 'warnings' => $warnings];
        }

        if ($dryRun) {
            return ['updated' => true, 'unmatched' => false, 'ambiguous' => false, 'amount' => $row['utility'], 'warnings' => $warnings];
        }

        $type = $row['utility'] >= 1000 ? 'service_charge' : 'garbage';
        $payload = [
            'utility_expense_type' => $type,
            'utility_expense_amount' => $row['utility'],
        ];
        if (Schema::hasColumn('pm_leases', 'utility_expenses')) {
            $payload['utility_expenses'] = [[
                'type' => $type,
                'amount' => number_format($row['utility'], 2, '.', ''),
                'fixed_charge' => number_format($row['utility'], 2, '.', ''),
            ]];
        }
        $lease->fill(array_filter(
            $payload,
            static fn ($value, $key) => Schema::hasColumn('pm_leases', $key),
            ARRAY_FILTER_USE_BOTH
        ));
        $lease->save();

        return ['updated' => true, 'unmatched' => false, 'ambiguous' => false, 'amount' => $row['utility'], 'warnings' => $warnings];
    }

    /**
     * @param  array{name:string, rent:float, utility:float, invoice:?string}  $row
     * @param  array<string, list<array{tenant:PmTenant, lease:PmLease}>>  $leasesByName
     * @return list<array{tenant:PmTenant, lease:PmLease}>
     */
    private function matchLeases(array $row, array $leasesByName): array
    {
        $key = $this->nameKey($row['name']);
        $hits = $key !== '' ? ($leasesByName[$key] ?? []) : [];

        if ($hits === [] && $key !== '') {
            foreach ($leasesByName as $candidateKey => $group) {
                if ($this->keysLooselyMatch($key, $candidateKey)) {
                    $hits = array_merge($hits, $group);
                }
            }
        }

        if (count($hits) <= 1) {
            return $hits;
        }

        $rentHits = array_values(array_filter($hits, function (array $hit) use ($row): bool {
            $leaseRent = round((float) $hit['lease']->monthly_rent, 2);

            return abs($leaseRent - $row['rent']) < 0.51;
        }));

        return $rentHits !== [] ? $rentHits : $hits;
    }

    /**
     * @return array<string, list<array{tenant:PmTenant, lease:PmLease}>>
     */
    private function leasesIndexedByName(): array
    {
        $leases = PmLease::query()
            ->withoutGlobalScopes()
            ->with(['pmTenant' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('id')
            ->get();

        $index = [];
        $seenTenant = [];
        foreach ($leases as $lease) {
            $tenant = $lease->pmTenant;
            if (! $tenant) {
                continue;
            }
            $tid = (int) $tenant->id;
            if (isset($seenTenant[$tid])) {
                continue;
            }
            $seenTenant[$tid] = true;
            $key = $this->nameKey((string) $tenant->name);
            if ($key === '') {
                continue;
            }
            $index[$key][] = ['tenant' => $tenant, 'lease' => $lease];
        }

        return $index;
    }

    /**
     * @return list<array{name:string, rent:float, utility:float, invoice:?string}>
     */
    private function parseRows(string $text): array
    {
        $rows = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '' || ! preg_match('/Sep\/2026/i', $line)) {
                continue;
            }
            if (preg_match('/TENANT DESCRIPTION|INVOICE #/i', $line)) {
                continue;
            }

            if (preg_match('/^(.+?)\s+Sep\/2026\s+(.+)$/i', $line, $m) !== 1) {
                continue;
            }

            $name = trim($m[1]);
            $rest = str_replace(["\u{00A0}", "\u{202F}", "\u{201A}", "'"], [' ', ' ', ',', ''], $m[2]);
            preg_match_all('/KES\s+([0-9][0-9,\s]*\.\d{2})/i', $rest, $money);
            $amounts = array_map(fn (string $raw) => $this->money($raw), $money[1] ?? []);
            if ($amounts === []) {
                continue;
            }

            $parsed = $this->splitAmounts($amounts);
            $invoice = null;
            if (preg_match('/INV\d+/i', $rest, $inv) === 1) {
                $invoice = strtoupper($inv[0]);
            }

            $rows[] = [
                'name' => $name,
                'rent' => $parsed['rent'],
                'utility' => $parsed['utility'],
                'invoice' => $invoice,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<float>  $amounts
     * @return array{rent:float, utility:float}
     */
    private function splitAmounts(array $amounts): array
    {
        $count = count($amounts);
        if ($count >= 6) {
            return [
                'rent' => $amounts[0] > 0.009 ? $amounts[0] : $amounts[1],
                'utility' => $amounts[2],
            ];
        }
        if ($count === 5) {
            $rent = $amounts[0] > 0.009 ? $amounts[0] : $amounts[1];

            return [
                'rent' => $rent,
                'utility' => ($amounts[2] > 0.009 && $amounts[2] < $rent) ? $amounts[2] : 0.0,
            ];
        }

        $rent = $amounts[0] > 0.009 ? $amounts[0] : ($amounts[1] ?? 0.0);

        return ['rent' => $rent, 'utility' => 0.0];
    }

    private function readText(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['txt', 'text', 'log', 'csv'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        }

        foreach ([
            fn () => $this->viaPdftotext($path),
            fn () => $this->viaPython($path),
        ] as $extractor) {
            $text = $extractor();
            if (is_string($text) && str_contains($text, 'Sep/2026')) {
                return $text;
            }
        }

        throw new RuntimeException('Could not extract billing rows from '.$path);
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

    private function viaPython(string $path): ?string
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

    private function nameKey(string $name): string
    {
        $n = strtoupper($name);
        $n = str_replace(["'", '`'], '', $n);
        $n = preg_replace('/[^A-Z0-9]+/', ' ', $n) ?? '';
        $n = preg_replace('/\b(OCCP|OCCUPIED|OCC)\b/', ' ', $n) ?? '';
        $n = preg_replace('/\s+/', ' ', trim($n)) ?? '';
        $n = preg_replace('/\b0$/', '', $n) ?? $n;

        return trim($n);
    }

    private function keysLooselyMatch(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        $max = max(strlen($a), strlen($b));

        return $max > 8 && (similar_text($a, $b) / $max) >= 0.88;
    }

    private function money(string $value): float
    {
        $raw = str_replace([',', ' '], '', $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }
}
