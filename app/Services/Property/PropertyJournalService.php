<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PropertyJournalService
{
    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    public function postBatch(array $payload, array $lines): AccountingJournalBatch
    {
        return DB::transaction(function () use ($payload, $lines) {
            $this->assertCurrentPeriodOpen($payload['date'], (int) ($payload['agent_user_id'] ?? 0));
            $this->assertBalanced($lines);

            $batch = AccountingJournalBatch::query()->firstOrCreate(
                [
                    'source_type' => $payload['source_type'],
                    'source_id' => $payload['source_id'],
                    'event_type' => $payload['event_type'],
                ],
                [
                    'date' => $payload['date'],
                    'description' => $payload['description'] ?? null,
                    'source_key' => $payload['source_key'],
                    'status' => AccountingJournalBatch::STATUS_POSTED,
                    'agent_user_id' => $payload['agent_user_id'] ?? null,
                    'created_by' => $payload['created_by'] ?? null,
                    'posted_by' => $payload['posted_by'] ?? null,
                    'posted_at' => now(),
                ]
            );

            if ($batch->wasRecentlyCreated) {
                foreach ($lines as $line) {
                    AccountingJournalLine::query()->create([
                        'batch_id' => $batch->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'reference' => $line['reference'] ?? null,
                        'property_id' => $line['property_id'] ?? null,
                        'tenant_id' => $line['tenant_id'] ?? null,
                        'landlord_id' => $line['landlord_id'] ?? null,
                        'unit_id' => $line['unit_id'] ?? null,
                        'agent_user_id' => $line['agent_user_id'] ?? null,
                    ]);
                }
            }

            return $batch;
        });
    }

    public function accountIdByCode(string $code): int
    {
        $id = AccountingChartAccount::query()->where('code', $code)->value('id');
        if (! $id) {
            throw new RuntimeException('Missing chart account code: '.$code);
        }

        return (int) $id;
    }

    public function reverseBatch(AccountingJournalBatch $batch, ?int $reversedBy = null, ?string $reason = null): AccountingJournalBatch
    {
        return DB::transaction(function () use ($batch, $reversedBy, $reason) {
            if ($batch->status === AccountingJournalBatch::STATUS_REVERSED) {
                $existing = AccountingJournalBatch::query()
                    ->where('reversed_from_batch_id', $batch->id)
                    ->first();
                if ($existing) {
                    return $existing;
                }
                throw new RuntimeException('Batch already reversed.');
            }

            $this->assertReversalPeriodOpen((int) ($batch->agent_user_id ?? 0));

            $lines = AccountingJournalLine::query()
                ->where('batch_id', $batch->id)
                ->get();
            if ($lines->isEmpty()) {
                throw new RuntimeException('Cannot reverse batch without journal lines.');
            }

            $reversal = AccountingJournalBatch::query()->firstOrCreate(
                [
                    'source_type' => $batch->source_type,
                    'source_id' => $batch->source_id,
                    'event_type' => $batch->event_type.'_reversal',
                ],
                [
                    'date' => now()->toDateString(),
                    'description' => trim('Reversal: '.($reason ?: (string) $batch->description)),
                    'source_key' => $batch->source_key.':reversal',
                    'status' => AccountingJournalBatch::STATUS_POSTED,
                    'agent_user_id' => $batch->agent_user_id,
                    'created_by' => $reversedBy,
                    'posted_by' => $reversedBy,
                    'reversed_from_batch_id' => $batch->id,
                    'posted_at' => now(),
                    'reversed_at' => now(),
                ]
            );

            if ($reversal->wasRecentlyCreated) {
                foreach ($lines as $line) {
                    AccountingJournalLine::query()->create([
                        'batch_id' => $reversal->id,
                        'account_id' => $line->account_id,
                        'debit' => (float) $line->credit,
                        'credit' => (float) $line->debit,
                        'reference' => $line->reference,
                        'property_id' => $line->property_id,
                        'tenant_id' => $line->tenant_id,
                        'landlord_id' => $line->landlord_id,
                        'unit_id' => $line->unit_id,
                        'agent_user_id' => $line->agent_user_id,
                    ]);
                }
            }

            $batch->status = AccountingJournalBatch::STATUS_REVERSED;
            $batch->reversed_by = $reversedBy;
            $batch->reversed_at = now();
            $batch->save();

            return $reversal;
        });
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $line) {
            $dr += (float) ($line['debit'] ?? 0);
            $cr += (float) ($line['credit'] ?? 0);
        }

        if (round($dr, 2) !== round($cr, 2)) {
            throw new RuntimeException('Journal batch is not balanced.');
        }
    }

    private function assertCurrentPeriodOpen(string $date, int $agentUserId): void
    {
        $period = AccountingPeriod::query()
            ->when($agentUserId > 0, fn ($q) => $q->where('agent_user_id', $agentUserId))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();

        if ($period && $period->status === AccountingPeriod::STATUS_LOCKED) {
            throw new RuntimeException('Cannot post into a locked accounting period.');
        }
    }

    private function assertReversalPeriodOpen(int $agentUserId): void
    {
        $current = AccountingPeriod::query()
            ->when($agentUserId > 0, fn ($q) => $q->where('agent_user_id', $agentUserId))
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        if (! $current || $current->status !== AccountingPeriod::STATUS_OPEN) {
            throw new RuntimeException('Reversal is only allowed in current open period.');
        }
    }
}

