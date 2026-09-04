<?php

namespace App\Services\Property;

use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class LandlordAdvanceService
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_WRITTEN_OFF = 'written_off';

    public function __construct(
        private readonly LandlordSettlementService $settlements,
    ) {}

    /**
     * @param  array{property_id?: int, landlord_id?: int, status?: string, search?: string}  $filters
     * @return array{
     *     schedules: list<array<string, mixed>>,
     *     advances: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, hint?: string}>
     * }
     */
    public function buildIndex(array $filters): array
    {
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));

        $schedules = $this->loadSchedules($propertyId, $landlordId, $search);
        $advances = $this->loadAdvances($propertyId, $landlordId, $statusFilter, $search);

        $openTotal = collect($advances)->where('advance_status', self::STATUS_OPEN)->sum('amount');
        $openCount = collect($advances)->where('advance_status', self::STATUS_OPEN)->count();
        $withSchedule = collect($schedules)->where('agreed_pay_day', '!=', null)->count();

        return [
            'schedules' => $schedules,
            'advances' => $advances,
            'stats' => [
                ['label' => 'Agreed schedules', 'value' => (string) $withSchedule, 'hint' => 'Property × landlord with pay day set'],
                ['label' => 'Advance records', 'value' => (string) count($advances), 'hint' => 'All advance payments logged'],
                ['label' => 'Open advances', 'value' => (string) $openCount, 'hint' => 'Awaiting recovery from collections'],
                ['label' => 'Open amount', 'value' => PropertyMoney::kes((float) $openTotal), 'hint' => 'Outstanding advance balance'],
            ],
        ];
    }

    public function recordAdvance(
        int $propertyId,
        int $landlordId,
        float $amount,
        User $actor,
        ?Carbon $agreedPayDate = null,
        ?string $paymentReference = null,
        ?string $notes = null,
        bool $markPaidImmediately = true,
    ): PmLandlordPayout {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $link = DB::table('property_landlord')
            ->join('properties as p', 'p.id', '=', 'property_landlord.property_id')
            ->where('property_landlord.property_id', $propertyId)
            ->where('property_landlord.user_id', $landlordId)
            ->select(['p.name as property_name', 'property_landlord.agreed_pay_day'])
            ->first();

        if (! $link) {
            throw new InvalidArgumentException('Landlord is not linked to this property.');
        }

        $propertyName = (string) ($link->property_name ?? 'Property');
        $effectiveAgreedDate = $agreedPayDate ?? $this->nextAgreedPayDate(
            $link->agreed_pay_day !== null ? (int) $link->agreed_pay_day : null,
        );

        return DB::transaction(function () use (
            $propertyId,
            $landlordId,
            $amount,
            $actor,
            $effectiveAgreedDate,
            $paymentReference,
            $notes,
            $propertyName,
            $markPaidImmediately,
        ) {
            $payout = PmLandlordPayout::query()->create([
                'agent_user_id' => (int) $actor->id,
                'total_amount' => $amount,
                'status' => 'draft',
                'created_by' => (int) $actor->id,
            ]);

            $description = 'Advance payment — '.$propertyName;
            if ($notes !== null && trim($notes) !== '') {
                $description .= ' ('.trim($notes).')';
            }

            PmLandlordPayoutItem::query()->create([
                'payout_id' => (int) $payout->id,
                'landlord_id' => $landlordId,
                'property_id' => $propertyId,
                'amount' => $amount,
                'line_type' => LandlordSettlementService::LINE_ADVANCE,
                'description' => $description,
                'period_month' => now()->format('Y-m'),
                'agreed_pay_date' => $effectiveAgreedDate?->toDateString(),
                'advance_status' => self::STATUS_OPEN,
                'payment_reference' => $paymentReference !== null && trim($paymentReference) !== '' ? trim($paymentReference) : null,
            ]);

            if ($markPaidImmediately) {
                $this->settlements->approvePayout($payout, $actor);
                $this->settlements->markPayoutPaid($payout->fresh(['items']), $actor);
            }

            return $payout->fresh(['items']);
        });
    }

    public function updateAgreedPaySchedule(
        int $propertyId,
        int $landlordId,
        ?int $agreedPayDay,
        ?string $notes = null,
    ): void {
        if ($agreedPayDay !== null && ($agreedPayDay < 1 || $agreedPayDay > 28)) {
            throw new InvalidArgumentException('Agreed pay day must be between 1 and 28.');
        }

        $updated = DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->where('user_id', $landlordId)
            ->update([
                'agreed_pay_day' => $agreedPayDay,
                'agreed_pay_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new InvalidArgumentException('Landlord is not linked to this property.');
        }
    }

    public function markAdvanceRecovered(PmLandlordPayoutItem $item): void
    {
        if ((string) $item->line_type !== LandlordSettlementService::LINE_ADVANCE) {
            throw new RuntimeException('Only advance payout items can be marked recovered.');
        }

        $item->update(['advance_status' => self::STATUS_RECOVERED]);
    }

    public function markAdvanceWrittenOff(PmLandlordPayoutItem $item, User $actor): void
    {
        if ((string) $item->line_type !== LandlordSettlementService::LINE_ADVANCE) {
            throw new RuntimeException('Only advance payout items can be written off.');
        }

        if ((string) $item->advance_status === self::STATUS_RECOVERED) {
            throw new RuntimeException('Recovered advances cannot be written off.');
        }

        $item->update(['advance_status' => self::STATUS_WRITTEN_OFF]);
    }

    public function nextAgreedPayDate(?int $agreedPayDay, ?Carbon $from = null): ?Carbon
    {
        if ($agreedPayDay === null || $agreedPayDay < 1 || $agreedPayDay > 28) {
            return null;
        }

        $from = ($from ?? now())->copy()->startOfDay();
        $candidate = $from->copy()->day(min($agreedPayDay, $from->daysInMonth));

        if ($candidate->lt($from)) {
            $next = $from->copy()->addMonthNoOverflow()->startOfMonth();
            $candidate = $next->copy()->day(min($agreedPayDay, $next->daysInMonth));
        }

        return $candidate;
    }

    /**
     * @return array<string, float>
     */
    public function openAdvanceTotalsByKey(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        return PmLandlordPayoutItem::query()
            ->whereIn('property_id', $propertyIds)
            ->where('line_type', LandlordSettlementService::LINE_ADVANCE)
            ->where('advance_status', self::STATUS_OPEN)
            ->selectRaw('property_id, landlord_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('property_id', 'landlord_id')
            ->get()
            ->mapWithKeys(fn ($row) => [((int) $row->property_id).'|'.((int) $row->landlord_id) => round((float) $row->total, 2)])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSchedules(int $propertyId, int $landlordId, string $search): array
    {
        $query = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->select([
                'pl.property_id',
                'pl.user_id as landlord_id',
                'pl.agreed_pay_day',
                'pl.agreed_pay_notes',
                'pl.ownership_percent',
                'u.name as landlord_name',
                'p.name as property_name',
                'p.code as property_code',
            ])
            ->orderBy('p.name')
            ->orderBy('u.name');

        if (AgentWorkspaceScope::shouldApply()) {
            $query->where('p.agent_user_id', (int) auth()->id());
        }
        if ($propertyId > 0) {
            $query->where('pl.property_id', $propertyId);
        }
        if ($landlordId > 0) {
            $query->where('pl.user_id', $landlordId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', '%'.$search.'%')
                    ->orWhere('u.name', 'like', '%'.$search.'%')
                    ->orWhere('p.code', 'like', '%'.$search.'%');
            });
        }

        return $query->get()->map(function ($row) {
            $agreedPayDay = $row->agreed_pay_day !== null ? (int) $row->agreed_pay_day : null;
            $nextDate = $this->nextAgreedPayDate($agreedPayDay);

            return [
                'property_id' => (int) $row->property_id,
                'landlord_id' => (int) $row->landlord_id,
                'property_name' => (string) $row->property_name,
                'property_code' => trim((string) ($row->property_code ?? '')),
                'landlord_name' => (string) $row->landlord_name,
                'ownership_percent' => (float) $row->ownership_percent,
                'agreed_pay_day' => $agreedPayDay,
                'agreed_pay_notes' => (string) ($row->agreed_pay_notes ?? ''),
                'next_agreed_pay_date' => $nextDate?->format('Y-m-d'),
                'next_agreed_pay_label' => $nextDate?->format('d M Y'),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadAdvances(int $propertyId, int $landlordId, string $statusFilter, string $search): array
    {
        $query = PmLandlordPayoutItem::query()
            ->with(['payout', 'property', 'landlord'])
            ->where('line_type', LandlordSettlementService::LINE_ADVANCE)
            ->orderByDesc('id');

        if ($propertyId > 0) {
            $query->where('property_id', $propertyId);
        }
        if ($landlordId > 0) {
            $query->where('landlord_id', $landlordId);
        }
        if (in_array($statusFilter, [self::STATUS_OPEN, self::STATUS_RECOVERED, self::STATUS_WRITTEN_OFF], true)) {
            $query->where('advance_status', $statusFilter);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhere('payment_reference', 'like', '%'.$search.'%')
                    ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('landlord', fn ($lq) => $lq->where('name', 'like', '%'.$search.'%'));
            });
        }

        if (AgentWorkspaceScope::shouldApply()) {
            $agentId = (int) auth()->id();
            $query->where(function ($q) use ($agentId) {
                $q->whereHas('property', fn ($pq) => $pq->where('agent_user_id', $agentId))
                    ->orWhereHas('payout', fn ($ppq) => $ppq->where('agent_user_id', $agentId));
            });
        }

        return $query->limit(500)->get()->map(function (PmLandlordPayoutItem $item) {
            $payout = $item->payout;

            return [
                'id' => (int) $item->id,
                'payout_id' => (int) $item->payout_id,
                'property_id' => (int) $item->property_id,
                'landlord_id' => (int) $item->landlord_id,
                'property_name' => (string) ($item->property?->name ?? '—'),
                'landlord_name' => (string) ($item->landlord?->name ?? '—'),
                'amount' => (float) $item->amount,
                'description' => (string) ($item->description ?? ''),
                'agreed_pay_date' => $item->agreed_pay_date?->format('Y-m-d'),
                'agreed_pay_label' => $item->agreed_pay_date?->format('d M Y') ?? '—',
                'advance_status' => (string) ($item->advance_status ?? self::STATUS_OPEN),
                'payment_reference' => (string) ($item->payment_reference ?? ''),
                'period_month' => (string) ($item->period_month ?? ''),
                'payout_status' => (string) ($payout?->status ?? 'draft'),
                'paid_at' => $payout?->paid_at?->format('d M Y H:i'),
                'recorded_at' => $payout?->created_at?->format('d M Y'),
            ];
        })->values()->all();
    }
}
