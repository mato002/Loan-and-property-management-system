<?php

namespace App\Services\Property;

use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Splits a combined landlord cheque into the property shares printed on each
 * EZEN Property Account Statement.
 */
final class EzenPropertyStatementRemittanceImporter
{
    /**
     * @param  list<string>  $paths
     * @return array{posted:int, skipped:int, held:list<string>, lines:list<string>}
     */
    public function importPaths(array $paths, bool $dryRun = false): array
    {
        $shares = [];
        foreach ($paths as $path) {
            foreach ($this->parseFile($path) as $share) {
                $shares[] = $share;
            }
        }

        $byVoucher = [];
        foreach ($shares as $share) {
            $byVoucher[$share['voucher_no']][] = $share;
        }

        $summary = ['posted' => 0, 'skipped' => 0, 'held' => [], 'lines' => []];

        foreach ($byVoucher as $voucherNo => $group) {
            $result = $this->importVoucher((string) $voucherNo, $group, $dryRun);
            $summary['posted'] += $result['posted'];
            $summary['skipped'] += $result['skipped'];
            if ($result['held'] !== null) {
                $summary['held'][] = $result['held'];
            }
            foreach ($result['lines'] as $line) {
                $summary['lines'][] = $line;
            }
        }

        return $summary;
    }

    /**
     * @return list<array{property_id:int, property_code:string, landlord_id:int, agent_user_id:int, voucher_no:string, txn_date:string, particulars:string, period_month:string, amount:float}>
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found: '.$path);
        }

        $xml = file_get_contents($path);
        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Empty statement file: '.$path);
        }

        preg_match_all('/<Row\b[^>]*>.*?<\/Row>/s', $xml, $rowMatches);
        $property = null;
        $shares = [];

        foreach ($rowMatches[0] as $rowXml) {
            $cells = $this->cells($rowXml);
            if ($property === null) {
                $property = $this->propertyFromCells($cells);
            }

            $share = $this->remittanceFromCells($cells);
            if ($share === null) {
                continue;
            }
            if ($property === null) {
                throw new RuntimeException('Remittance found before a property code in '.$path);
            }
            $shares[] = $share + [
                'property_id' => (int) $property->id,
                'property_code' => (string) $property->code,
                'landlord_id' => $this->landlordId($property),
                'agent_user_id' => (int) $property->agent_user_id,
            ];
        }

        if ($property === null) {
            throw new RuntimeException('No matching property on the statement: '.$path);
        }

        return $shares;
    }

    /**
     * @param  list<array<string, mixed>>  $group
     * @return array{posted:int, skipped:int, held:?string, lines:list<string>}
     */
    private function importVoucher(string $voucherNo, array $group, bool $dryRun): array
    {
        $marker = '[EZEN '.$voucherNo.']';
        $voucher = DB::table('pm_ezen_payment_vouchers')
            ->where('ezen_voucher_no', $voucherNo)
            ->first(['id', 'amount', 'txn_date', 'particulars']);
        $voucherAmount = $voucher ? round((float) $voucher->amount, 2) : null;

        $statementByProperty = [];
        foreach ($group as $share) {
            $pid = (int) $share['property_id'];
            if (isset($statementByProperty[$pid]) && abs((float) $statementByProperty[$pid]['amount'] - (float) $share['amount']) > 0.5) {
                return [
                    'posted' => 0,
                    'skipped' => 0,
                    'held' => $voucherNo.': two different amounts for the same property. Not posted.',
                    'lines' => [],
                ];
            }
            $statementByProperty[$pid] = $share;
        }
        $statementTotal = round(array_sum(array_map(fn (array $share): float => (float) $share['amount'], $statementByProperty)), 2);
        if ($voucherAmount !== null && abs($statementTotal - $voucherAmount) > 1.0) {
            return [
                'posted' => 0,
                'skipped' => 0,
                'held' => $voucherNo.': statement shares '.number_format($statementTotal, 2).' do not equal cheque '.number_format($voucherAmount, 2).'. Not posted.',
                'lines' => [],
            ];
        }

        $existing = DB::table('pm_landlord_payout_items')
            ->where('line_type', LandlordSettlementService::LINE_REMITTANCE)
            ->where('description', 'like', $marker.'%')
            ->get(['id', 'payout_id', 'property_id', 'amount']);

        $already = [];
        $unsplitIds = [];
        foreach ($existing as $row) {
            $amount = round((float) $row->amount, 2);
            if ($voucherAmount !== null && abs($amount - $voucherAmount) <= 1.0) {
                $unsplitIds[] = (int) $row->id;

                continue;
            }
            $already[(int) $row->property_id] = ($already[(int) $row->property_id] ?? 0.0) + $amount;
        }

        $pending = [];
        $skipped = 0;
        foreach ($statementByProperty as $pid => $share) {
            if (abs(($already[$pid] ?? 0.0) - (float) $share['amount']) <= 0.5) {
                $skipped++;

                continue;
            }
            if (($already[$pid] ?? 0.0) > 0.5) {
                return [
                    'posted' => 0,
                    'skipped' => $skipped,
                    'held' => $voucherNo.': '.$share['property_code'].' already has a different amount recorded. Not posted.',
                    'lines' => [],
                ];
            }
            $pending[] = $share;
        }

        $lines = [];
        if ($pending === [] && $unsplitIds === []) {
            return ['posted' => 0, 'skipped' => $skipped, 'held' => null, 'lines' => $lines];
        }

        $post = function () use ($pending, $unsplitIds, $voucherNo, $marker, &$lines): int {
            $this->removeUnsplitCheques($unsplitIds);
            if ($pending === []) {
                return 0;
            }
            $first = $pending[0];
            $total = round(array_sum(array_map(fn (array $share): float => (float) $share['amount'], $pending)), 2);
            $paidAt = Carbon::createFromFormat('Y-m-d', (string) $first['txn_date'])->setTime(12, 0);
            $payout = PmLandlordPayout::query()->create([
                'agent_user_id' => (int) $first['agent_user_id'],
                'total_amount' => $total,
                'status' => 'paid',
                'created_by' => (int) $first['agent_user_id'],
                'approved_by' => (int) $first['agent_user_id'],
                'paid_at' => $paidAt,
            ]);

            foreach ($pending as $share) {
                $description = trim($marker.' '.$share['particulars'].' ['.$share['property_code'].']');
                PmLandlordPayoutItem::query()->create([
                    'payout_id' => (int) $payout->id,
                    'landlord_id' => (int) $share['landlord_id'],
                    'property_id' => (int) $share['property_id'],
                    'amount' => (float) $share['amount'],
                    'line_type' => LandlordSettlementService::LINE_REMITTANCE,
                    'description' => $description,
                    'period_month' => (string) $share['period_month'],
                    'payment_reference' => $voucherNo,
                ]);
                $lines[] = $share['property_code'].' '.$share['period_month'].' '.$voucherNo.' '.number_format((float) $share['amount'], 2);
            }

            return count($pending);
        };

        $posted = $dryRun ? count($pending) : DB::transaction($post);
        if ($dryRun) {
            foreach ($pending as $share) {
                $lines[] = $share['property_code'].' '.$share['period_month'].' '.$voucherNo.' '.number_format((float) $share['amount'], 2);
            }
        }

        return ['posted' => $posted, 'skipped' => $skipped, 'held' => null, 'lines' => $lines];
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function removeUnsplitCheques(array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        $payoutIds = DB::table('pm_landlord_payout_items')
            ->whereIn('id', $itemIds)
            ->pluck('payout_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();
        DB::table('pm_landlord_payout_items')->whereIn('id', $itemIds)->delete();
        foreach ($payoutIds as $payoutId) {
            $remaining = (float) DB::table('pm_landlord_payout_items')->where('payout_id', $payoutId)->sum('amount');
            if ($remaining <= 0.009) {
                DB::table('pm_ezen_payment_vouchers')->where('pm_landlord_payout_id', $payoutId)->update(['pm_landlord_payout_id' => null]);
                DB::table('pm_landlord_payouts')->where('id', $payoutId)->delete();

                continue;
            }
            DB::table('pm_landlord_payouts')->where('id', $payoutId)->update(['total_amount' => round($remaining, 2)]);
        }
    }

    /**
     * @return list<string>
     */
    private function cells(string $rowXml): array
    {
        preg_match_all('/<Data\b[^>]*>(.*?)<\/Data>/s', $rowXml, $matches);
        $cells = [];
        foreach ($matches[1] as $value) {
            $text = html_entity_decode(trim(strip_tags($value)), ENT_QUOTES | ENT_XML1);
            if ($text !== '') {
                $cells[] = $text;
            }
        }

        return $cells;
    }

    /**
     * @param  list<string>  $cells
     */
    private function propertyFromCells(array $cells): ?Property
    {
        $codes = [];
        foreach ($cells as $cell) {
            if (preg_match('/^\[([A-Z][A-Z0-9]+)\]/i', $cell, $match) === 1) {
                $codes[] = strtoupper($match[1]);
            }
        }
        if ($codes === []) {
            return null;
        }

        $found = Property::query()->whereIn('code', $codes)->get();
        if ($found->isEmpty()) {
            return null;
        }

        return $found->sortByDesc(fn (Property $property): int => strlen((string) $property->code))->first();
    }

    /**
     * @param  list<string>  $cells
     * @return array{voucher_no:string, txn_date:string, particulars:string, period_month:string, amount:float}|null
     */
    private function remittanceFromCells(array $cells): ?array
    {
        $particulars = '';
        $voucherNo = '';
        $date = '';
        $amount = null;
        foreach ($cells as $cell) {
            if ($date === '' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $cell, $match) === 1) {
                $date = $match[3].'-'.$match[2].'-'.$match[1];
            }
            if ($voucherNo === '' && preg_match('/^(PM\d+)\s+(.+)$/i', $cell, $match) === 1 && str_contains(strtoupper($match[2]), 'REMIT')) {
                $voucherNo = strtoupper($match[1]);
                $particulars = trim($match[2]);
            }
            if (is_numeric($cell)) {
                $amount = abs((float) $cell);
            }
        }
        if ($voucherNo === '' || $date === '' || $amount === null || $amount <= 0.009) {
            return null;
        }

        return [
            'voucher_no' => $voucherNo,
            'txn_date' => $date,
            'particulars' => $particulars,
            'period_month' => $this->periodMonth($particulars, $date),
            'amount' => round($amount, 2),
        ];
    }

    private function periodMonth(string $particulars, string $txnDate): string
    {
        $months = [
            'JANUARY' => '01', 'FEBRUARY' => '02', 'MARCH' => '03', 'APRIL' => '04',
            'MAY' => '05', 'JUNE' => '06', 'JULY' => '07', 'AUGUST' => '08',
            'SEPTEMBER' => '09', 'OCTOBER' => '10', 'NOVEMBER' => '11', 'DECEMBER' => '12',
            'SEPT' => '09', 'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'JUN' => '06', 'JUL' => '07', 'AUG' => '08', 'SEP' => '09', 'OCT' => '10',
            'NOV' => '11', 'DEC' => '12',
        ];
        $text = strtoupper($particulars);
        if (preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER|SEPT|JAN|FEB|MAR|APR|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\b[^\d]{0,6}(\d{4})/', $text, $match) === 1) {
            return $match[2].'-'.$months[$match[1]];
        }

        return substr($txnDate, 0, 7);
    }

    private function landlordId(Property $property): int
    {
        $ids = DB::table('property_landlord')->where('property_id', $property->id)->pluck('user_id');
        $id = (int) ($ids->first() ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No landlord is linked to '.$property->code);
        }

        return $id;
    }
}
