<?php

namespace App\Services\Property;

use App\Models\PmEzenBill;
use App\Models\PmVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenBillsImportService
{
    public function __construct(
        private readonly EzenBillsListingParser $parser,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     register_upserted:int,
     *     skipped_zero:int,
     *     skipped_filtered:int,
     *     vendors:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        bool $dryRun = false,
        ?string $vendor = null,
        ?string $status = null,
        ?int $limit = null,
    ): array {
        $rows = $this->parser->parsePath($path);
        $summary = [
            'parsed' => count($rows),
            'register_upserted' => 0,
            'skipped_zero' => 0,
            'skipped_filtered' => 0,
            'vendors' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $vendorFilter = $vendor !== null && $vendor !== '' ? strtoupper(trim($vendor)) : null;
        $statusFilter = $status !== null && $status !== '' ? strtolower(trim($status)) : null;
        $vendorNames = [];

        $process = function () use (
            $rows,
            $agentUserId,
            $dryRun,
            $vendorFilter,
            $statusFilter,
            $limit,
            &$summary,
            &$vendorNames
        ): void {
            $processed = 0;
            foreach ($rows as $row) {
                if ($limit !== null && $processed >= $limit) {
                    break;
                }
                $processed++;

                $total = (float) ($row['total_amount'] ?? 0);
                if ($total <= 0) {
                    $summary['skipped_zero']++;
                    continue;
                }

                if ($vendorFilter !== null && ! str_contains(strtoupper((string) $row['vendor_name']), $vendorFilter)) {
                    $summary['skipped_filtered']++;
                    continue;
                }
                if ($statusFilter !== null && strtolower((string) $row['payment_status']) !== $statusFilter) {
                    $summary['skipped_filtered']++;
                    continue;
                }

                $vendorNames[strtoupper((string) $row['vendor_name'])] = true;

                if ($dryRun) {
                    $summary['register_upserted']++;
                    continue;
                }

                $vendorRecord = $this->upsertVendor($agentUserId, $row);
                $this->upsertBill($agentUserId, $row, $vendorRecord?->id);
                $summary['register_upserted']++;
            }
        };

        if ($dryRun) {
            $process();
        } else {
            if (! Schema::hasTable('pm_ezen_bills')) {
                throw new RuntimeException('Run migrations first (pm_ezen_bills is missing).');
            }
            DB::transaction($process);
        }

        $summary['vendors'] = count($vendorNames);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertVendor(int $agentUserId, array $row): ?PmVendor
    {
        if (! Schema::hasTable('pm_vendors')) {
            return null;
        }

        $name = trim((string) ($row['vendor_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $query = PmVendor::query()->withoutGlobalScopes();
        if (Schema::hasColumn('pm_vendors', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }
        $vendor = $query->whereRaw('UPPER(name) = ?', [strtoupper($name)])->first();

        $category = $this->vendorCategory((string) ($row['memo'] ?? ''));
        $payload = [
            'name' => $name,
            'category' => $category,
            'status' => 'active',
        ];
        if (Schema::hasColumn('pm_vendors', 'agent_user_id')) {
            $payload['agent_user_id'] = $agentUserId;
        }

        if ($vendor) {
            if (trim((string) ($vendor->category ?? '')) === '') {
                $vendor->category = $category;
            }
            $vendor->status = 'active';
            if (Schema::hasColumn('pm_vendors', 'agent_user_id') && ! $vendor->agent_user_id) {
                $vendor->agent_user_id = $agentUserId;
            }
            $vendor->save();

            return $vendor;
        }

        return PmVendor::query()->create($payload);
    }

    private function vendorCategory(string $memo): string
    {
        $text = strtoupper($memo);
        if (str_contains($text, 'GARBAGE') || str_contains($text, 'WASTE')) {
            return 'Waste / garbage';
        }
        if (str_contains($text, 'CLEAN')) {
            return 'Cleaning';
        }
        if (str_contains($text, 'WATER')) {
            return 'Water';
        }

        return 'Supplier';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertBill(int $agentUserId, array $row, ?int $vendorId = null): PmEzenBill
    {
        $payload = [
            'agent_user_id' => $agentUserId,
            'source_key' => (string) $row['source_key'],
            'ezen_bill_no' => (string) $row['ezen_bill_no'],
            'vendor_invoice_no' => (string) ($row['vendor_invoice_no'] ?? ''),
            'bill_date' => $row['bill_date'] ?? null,
            'due_date' => $row['due_date'] ?? null,
            'vendor_name' => (string) $row['vendor_name'],
            'memo' => (string) ($row['memo'] ?? ''),
            'total_amount' => (float) $row['total_amount'],
            'total_paid' => (float) $row['total_paid'],
            'amount_due' => (float) $row['amount_due'],
            'listing_status' => (string) $row['listing_status'],
            'payment_status' => (string) $row['payment_status'],
            'pm_supplier_id' => $vendorId,
        ];

        $existing = PmEzenBill::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('source_key', $payload['source_key'])
            ->first();

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return PmEzenBill::query()->create($payload);
    }
}
