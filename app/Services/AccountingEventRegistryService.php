<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingWalletSlotSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingEventRegistryService
{
    public function allEvents(): array
    {
        $events = config('accounting_events.events', []);

        return is_array($events) ? $events : [];
    }

    public function allSlots(): array
    {
        $slots = config('accounting_slots.slots', []);

        return is_array($slots) ? $slots : [];
    }

    public function getEvent(string $eventKey): ?array
    {
        $event = $this->allEvents()[$eventKey] ?? null;

        return is_array($event) ? $event : null;
    }

    public function activeEvents(): array
    {
        return array_values(array_filter($this->allEvents(), static fn (array $event): bool => (bool) ($event['active'] ?? true)));
    }

    public function ensureRequiredSlotsExist(): void
    {
        foreach (array_keys($this->allSlots()) as $slotKey) {
            AccountingWalletSlotSetting::query()->firstOrCreate(
                ['slot_key' => $slotKey],
                [
                    'accounting_chart_account_id' => null,
                    'approval_status' => 'needs_setup',
                    'history_json' => [],
                ]
            );
        }
    }

    public function resolveEventAccountIdsOrFail(string $eventKey): array
    {
        $event = $this->getEvent($eventKey);
        if (! $event) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$eventKey.']. Configure mapping before posting.');
        }
        if (! (bool) ($event['active'] ?? true)) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        $debitSlot = (string) ($event['debit_slot'] ?? '');
        $creditSlot = (string) ($event['credit_slot'] ?? '');

        $debitMap = AccountingWalletSlotSetting::query()->where('slot_key', $debitSlot)->first();
        $creditMap = AccountingWalletSlotSetting::query()->where('slot_key', $creditSlot)->first();

        $debitId = (int) ($debitMap?->accounting_chart_account_id ?? 0);
        $creditId = (int) ($creditMap?->accounting_chart_account_id ?? 0);

        if ($debitId <= 0 || $creditId <= 0) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        if ((string) ($debitMap?->approval_status ?? 'needs_setup') !== 'active'
            || (string) ($creditMap?->approval_status ?? 'needs_setup') !== 'active') {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        $accounts = AccountingChartAccount::query()
            ->whereIn('id', [$debitId, $creditId])
            ->get()
            ->keyBy('id');
        $debitAccount = $accounts->get($debitId);
        $creditAccount = $accounts->get($creditId);

        if (! $debitAccount || ! $creditAccount || ! $debitAccount->is_active || ! $creditAccount->is_active) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        if ($debitAccount->isHeader() || $creditAccount->isHeader()) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        if ((int) $debitId === (int) $creditId && ! (bool) ($event['allow_same_account'] ?? false)) {
            throw new \RuntimeException('Accounting event mapping incomplete for ['.$event['event_name'].']. Configure mapping before posting.');
        }

        return [
            'event' => $event,
            'debit_account_id' => $debitId,
            'credit_account_id' => $creditId,
        ];
    }

    public function eventRowsForChartRules(): Collection
    {
        $this->ensureRequiredSlotsExist();
        $events = collect($this->allEvents())->values();
        $slotRows = AccountingWalletSlotSetting::query()
            ->whereIn('slot_key', array_keys($this->allSlots()))
            ->get()
            ->keyBy('slot_key');
        $accountIds = $slotRows->pluck('accounting_chart_account_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $accounts = AccountingChartAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        return $events->map(function (array $event) use ($slotRows, $accounts) {
            $debitSlot = (string) ($event['debit_slot'] ?? '');
            $creditSlot = (string) ($event['credit_slot'] ?? '');
            $debitMap = $slotRows->get($debitSlot);
            $creditMap = $slotRows->get($creditSlot);
            $debitAccount = $accounts->get((int) ($debitMap?->accounting_chart_account_id ?? 0));
            $creditAccount = $accounts->get((int) ($creditMap?->accounting_chart_account_id ?? 0));
            $active = (bool) ($event['active'] ?? true);
            $hasCompleteMapping = $debitAccount && $creditAccount;
            $approved = (string) ($debitMap?->approval_status ?? 'needs_setup') === 'active'
                && (string) ($creditMap?->approval_status ?? 'needs_setup') === 'active';

            $status = 'Needs Setup';
            if (! $active) {
                $status = 'Disabled';
            } elseif ($hasCompleteMapping && ! $approved) {
                $status = 'Awaiting Approval';
            } elseif ($hasCompleteMapping && $approved) {
                $status = 'Active';
            }

            $history = collect(array_merge(
                is_array($debitMap?->history_json) ? $debitMap->history_json : [],
                is_array($creditMap?->history_json) ? $creditMap->history_json : []
            ))
                ->sortByDesc(fn ($row) => (string) ($row['at'] ?? ''))
                ->values()
                ->all();

            return [
                'event_key' => (string) ($event['event_key'] ?? ''),
                'event_name' => (string) ($event['event_name'] ?? ''),
                'description' => (string) ($event['description'] ?? ''),
                'debit_slot' => $debitSlot,
                'credit_slot' => $creditSlot,
                'debit_account' => $debitAccount,
                'credit_account' => $creditAccount,
                'status' => $status,
                'active' => $active,
                'history' => $history,
            ];
        });
    }

    public function saveEventMapping(string $eventKey, int $debitAccountId, int $creditAccountId, int $actorUserId, bool $approvalRequired): void
    {
        $event = $this->getEvent($eventKey);
        if (! $event) {
            throw ValidationException::withMessages(['event_key' => 'Unknown accounting event.']);
        }

        $allowSame = (bool) ($event['allow_same_account'] ?? false);
        if ($debitAccountId === $creditAccountId && ! $allowSame) {
            throw ValidationException::withMessages([
                'credit_account_id' => 'Debit and credit accounts cannot be the same for this event.',
            ]);
        }

        $accounts = AccountingChartAccount::query()
            ->whereIn('id', [$debitAccountId, $creditAccountId])
            ->get()
            ->keyBy('id');
        $debit = $accounts->get($debitAccountId);
        $credit = $accounts->get($creditAccountId);
        if (! $debit || ! $credit) {
            throw ValidationException::withMessages(['debit_account_id' => 'Selected account does not exist.']);
        }
        if (! $debit->is_active || ! $credit->is_active) {
            throw ValidationException::withMessages(['debit_account_id' => 'Mapped accounts must be active.']);
        }
        if ($debit->isHeader() || $credit->isHeader()) {
            throw ValidationException::withMessages(['debit_account_id' => 'Header accounts cannot be used for posting mappings.']);
        }

        $this->validateSlotAccountType((string) $event['debit_slot'], $debit->account_type, 'debit_account_id');
        $this->validateSlotAccountType((string) $event['credit_slot'], $credit->account_type, 'credit_account_id');

        $status = $approvalRequired ? 'awaiting_approval' : 'active';
        $this->ensureRequiredSlotsExist();

        DB::transaction(function () use ($event, $debitAccountId, $creditAccountId, $status, $actorUserId): void {
            $this->updateSlotMapping((string) $event['debit_slot'], $debitAccountId, $status, $actorUserId, (string) $event['event_name'], 'debit');
            $this->updateSlotMapping((string) $event['credit_slot'], $creditAccountId, $status, $actorUserId, (string) $event['event_name'], 'credit');
        });
    }

    private function updateSlotMapping(
        string $slotKey,
        int $accountId,
        string $status,
        int $actorUserId,
        string $eventName,
        string $side
    ): void {
        $slot = AccountingWalletSlotSetting::query()->firstOrCreate(
            ['slot_key' => $slotKey],
            [
                'accounting_chart_account_id' => null,
                'approval_status' => 'needs_setup',
                'history_json' => [],
            ]
        );

        $history = is_array($slot->history_json) ? $slot->history_json : [];
        $history[] = [
            'at' => now()->toDateTimeString(),
            'user_id' => $actorUserId,
            'event_name' => $eventName,
            'slot_key' => $slotKey,
            'side' => $side,
            'old_account_id' => $slot->accounting_chart_account_id,
            'new_account_id' => $accountId,
            'status' => $status,
        ];

        $slot->update([
            'accounting_chart_account_id' => $accountId,
            'approval_status' => $status,
            'last_updated_by' => $actorUserId,
            'approved_by' => $status === 'active' ? $actorUserId : null,
            'approved_at' => $status === 'active' ? now() : null,
            'history_json' => $history,
        ]);
    }

    private function validateSlotAccountType(string $slotKey, string $actualType, string $field): void
    {
        $slot = $this->allSlots()[$slotKey] ?? null;
        if (! is_array($slot)) {
            return;
        }
        $expected = collect($slot['expected_account_types'] ?? [])->filter()->values()->all();
        if ($expected === []) {
            return;
        }
        if (! in_array($actualType, $expected, true)) {
            $label = is_array($slot) ? (string) ($slot['label'] ?? $slotKey) : $slotKey;
            throw ValidationException::withMessages([
                $field => $label.' ('.$slotKey.') requires account type: '.implode(', ', $expected).'. Selected: '.$actualType.'.',
            ]);
        }
    }
}
