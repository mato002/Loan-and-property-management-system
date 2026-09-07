<?php

namespace App\Modules\Reporting\Tenant;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPenaltyRule;
use App\Models\PmUnitMovement;
use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportScope;

class TenantReportService
{
	use ReportFilters;

	/**
	 * @return array<string,mixed>
	 */
	public function buildPenaltyRulesReport(): array
	{
		$rules = PmPenaltyRule::query()
			->where('is_active', true)
			->where('scope', 'rent')
			->orderBy('id')
			->get();

		$invoiceQuery = PmInvoice::query()
			->with(['tenant', 'unit.property'])
			->where('invoice_type', PmInvoice::TYPE_RENT)
			->whereColumn('amount_paid', '<', 'amount');
		$this->applyDateRange($invoiceQuery, 'due_date');
		ReportScope::applyToInvoice($invoiceQuery, ReportScope::fromRequest());
		$invoices = $invoiceQuery->orderBy('due_date')->limit(2000)->get();

		$today = now()->startOfDay();
		$rows = collect();
		$totalPenalty = 0.0;

		foreach ($invoices as $invoice) {
			$dueDate = $invoice->due_date;
			if ($dueDate === null) {
				continue;
			}

			$daysLate = $today->diffInDays($dueDate, false) * -1;
			if ($daysLate <= 0) {
				continue;
			}

			$base = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
			if ($base <= 0) {
				continue;
			}

			$penalty = 0.0;
			foreach ($rules as $rule) {
				$grace = (int) ($rule->grace_days ?? 0);
				if ($daysLate <= $grace) {
					continue;
				}

				$value = 0.0;
				if ($rule->formula === 'flat') {
					$value = (float) ($rule->amount ?? 0);
				} elseif ($rule->formula === 'percent' || $rule->formula === 'percent_plus_flat') {
					$value = $base * (((float) ($rule->percent ?? 0)) / 100);
					if ($rule->formula === 'percent_plus_flat') {
						$value += (float) ($rule->amount ?? 0);
					}
				}

				if ($rule->cap !== null) {
					$value = min($value, (float) $rule->cap);
				}
				$penalty += max(0.0, $value);
			}

			if ($penalty <= 0) {
				continue;
			}

			$unitLabel = trim(($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—'));
			$rows->push([
				$this->date((string) $dueDate),
				(string) ($invoice->invoice_no ?? ('INV-'.$invoice->id)),
				(string) ($invoice->tenant?->name ?? '—'),
				$unitLabel,
				$this->money($penalty),
			]);
			$totalPenalty += $penalty;
		}

		return [
			'stats' => [
				['label' => 'Penalty rows', 'value' => (string) $rows->count(), 'hint' => 'Computed'],
				['label' => 'Active rent rules', 'value' => (string) $rules->count(), 'hint' => 'Applied'],
				['label' => 'Total penalty', 'value' => $this->money($totalPenalty), 'hint' => 'From report rows'],
			],
			'columns' => ['Date', 'Transaction ID', 'Tenant Name', 'Unit', 'Penalty'],
			'tableRows' => $rows->all(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildDeAllocationReport(): array
	{
		$query = PmUnitMovement::query()
			->with([
				'unit.property',
				'unit.leases' => fn ($leaseQuery) => $leaseQuery->with('pmTenant')->orderByDesc('start_date'),
			])
			->where('movement_type', 'move_out');
		$this->applyDateRange($query, 'completed_on');
		ReportScope::applyToUnitModel($query, ReportScope::fromRequest());
		$movements = $query->latest('completed_on')->latest('id')->limit(2000)->get();

		return [
			'stats' => [
				['label' => 'De-allocations', 'value' => (string) $movements->count(), 'hint' => 'Recent'],
				['label' => 'Completed', 'value' => (string) $movements->whereNotNull('completed_on')->count(), 'hint' => 'Closed'],
			],
			'columns' => ['Date of De-allocation', 'Transaction ID', 'Allocation ID', 'Tenant Name', 'Property Name', 'Unit Name'],
			'tableRows' => $movements->map(function (PmUnitMovement $movement) {
				$lease = $movement->unit?->leases?->first();

				return [
					$this->date((string) ($movement->completed_on ?? $movement->scheduled_on)),
					'TXN-'.$movement->id,
					$lease ? 'ALLOC-'.$lease->id : '—',
					(string) ($lease?->pmTenant?->name ?? '—'),
					(string) ($movement->unit?->property?->name ?? '—'),
					(string) ($movement->unit?->label ?? '—'),
				];
			})->all(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildAllocationReport(): array
	{
		$query = PmUnitMovement::query()
			->with([
				'unit.property',
				'unit.leases' => fn ($leaseQuery) => $leaseQuery
					->withSum('invoices as invoices_paid_total', 'amount_paid')
					->orderByDesc('start_date'),
			])
			->where('movement_type', 'move_in');
		$this->applyDateRange($query, 'completed_on');
		ReportScope::applyToUnitModel($query, ReportScope::fromRequest());
		$movements = $query->latest('completed_on')->latest('id')->limit(2000)->get();

		return [
			'stats' => [
				['label' => 'Allocations', 'value' => (string) $movements->count(), 'hint' => 'Recent'],
				['label' => 'Completed', 'value' => (string) $movements->whereNotNull('completed_on')->count(), 'hint' => 'Closed'],
			],
			'columns' => ['Unit Name', 'Tenant Name', 'Amount Paid (when entering)', 'Unit Type', 'Transaction ID'],
			'tableRows' => $movements->map(function (PmUnitMovement $movement) {
				$lease = $movement->unit?->leases?->first();
				$amountPaid = (float) ($lease?->invoices_paid_total ?? 0);

				return [
					(string) ($movement->unit?->label ?? '—'),
					(string) ($lease?->pmTenant?->name ?? '—'),
					$this->money($amountPaid),
					(string) ($movement->unit?->unitTypeLabel() ?? '—'),
					'TXN-'.$movement->id,
				];
			})->all(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildLeaseDepositReport(): array
	{
		$scope = ReportScope::fromRequest();
		$filters = array_merge($scope, $this->depositReportExtraFilters());

		$query = PmLease::query()->with(['pmTenant', 'units.property']);
		$this->applyDateRange($query, 'start_date');
		ReportScope::applyToLease($query, $filters);

		if ($filters['status'] !== '') {
			$query->where('status', $filters['status']);
		}

		$leases = $query->get();

		if ($filters['q'] !== '') {
			$needle = mb_strtolower($filters['q']);
			$leases = $leases->filter(function (PmLease $lease) use ($needle): bool {
				$haystack = mb_strtolower(trim(implode(' ', [
					(string) ($lease->pmTenant?->name ?? ''),
					(string) ($lease->pmTenant?->account_number ?? ''),
					(string) ($lease->pmTenant?->phone ?? ''),
					$lease->units->map(fn ($unit) => $unit->property?->name)->filter()->implode(' '),
					$lease->units->map(fn ($unit) => $unit->label)->filter()->implode(' '),
				])));

				return str_contains($haystack, $needle);
			});
		}

		$rows = $leases->map(function (PmLease $lease) {
			$units = $lease->units;
			$propertyNames = $units->map(fn ($u) => $u->property?->name)->filter()->unique()->implode(', ');
			$unitNames = $units->map(fn ($u) => $u->label)->filter()->implode(', ');
			$depositPaid = $this->leaseDepositHeld($lease);
			$refundedAmount = 0.0;
			$balance = max(0.0, $depositPaid - $refundedAmount);

			return [
				'tenant' => (string) ($lease->pmTenant?->name ?? '—'),
				'property' => $propertyNames !== '' ? $propertyNames : '—',
				'unit' => $unitNames !== '' ? $unitNames : '—',
				'deposit' => $depositPaid,
				'refunded' => $refundedAmount,
				'balance' => $balance,
			];
		});

		if ($filters['deposit'] === 'held') {
			$rows = $rows->filter(fn (array $row) => $row['balance'] > 0.009);
		} elseif ($filters['deposit'] === 'zero') {
			$rows = $rows->filter(fn (array $row) => $row['balance'] <= 0.009);
		}

		$rows = $rows->sort(function (array $a, array $b) use ($filters): int {
			$dir = $filters['dir'] === 'asc' ? 1 : -1;

			return match ($filters['sort']) {
				'property' => $dir * strcasecmp($a['property'], $b['property']),
				'unit' => $dir * strcasecmp($a['unit'], $b['unit']),
				'deposit' => $dir * ($a['deposit'] <=> $b['deposit']),
				'balance' => $dir * ($a['balance'] <=> $b['balance']),
				default => $dir * strcasecmp($a['tenant'], $b['tenant']),
			};
		})->values();

		$totalDepositPaid = (float) $rows->sum('deposit');
		$totalRefunded = (float) $rows->sum('refunded');
		$totalBalance = (float) $rows->sum('balance');
		$heldCount = $rows->filter(fn (array $row) => $row['balance'] > 0.009)->count();

		return [
			'stats' => [
				['label' => 'Tenants', 'value' => (string) $rows->count(), 'hint' => 'Matching leases'],
				['label' => 'With deposit held', 'value' => (string) $heldCount, 'hint' => 'Balance > 0'],
				['label' => 'Deposit paid', 'value' => $this->money($totalDepositPaid), 'hint' => 'Rent + additional'],
				['label' => 'Refunded amount', 'value' => $this->money($totalRefunded), 'hint' => 'Recorded'],
				['label' => 'Balance', 'value' => $this->money($totalBalance), 'hint' => 'Still held'],
			],
			'columns' => ['Tenant', 'Property', 'Unit', 'Deposit Paid', 'Refunded Amount', 'Balance'],
			'tableRows' => $rows->map(fn (array $row) => [
				$row['tenant'],
				$row['property'],
				$row['unit'],
				$this->money($row['deposit']),
				$this->money($row['refunded']),
				$this->money($row['balance']),
			])->all(),
			'filters' => $filters,
			'reportFilterPreset' => 'tenant',
			'reportFilterExtrasView' => 'property.agent.partials.filter_toolbars.tenant_deposits_extras',
		];
	}

	/**
	 * @return array{status: string, deposit: string, sort: string, dir: string}
	 */
	private function depositReportExtraFilters(): array
	{
		$sort = (string) request()->query('sort', 'tenant');
		if (! in_array($sort, ['tenant', 'property', 'unit', 'deposit', 'balance'], true)) {
			$sort = 'tenant';
		}

		$dir = strtolower((string) request()->query('dir', 'asc'));
		if (! in_array($dir, ['asc', 'desc'], true)) {
			$dir = 'asc';
		}

		$status = (string) request()->query('status', '');
		if (! in_array($status, ['', 'active', 'expired', 'terminated', 'draft'], true)) {
			$status = '';
		}

		$deposit = (string) request()->query('deposit', '');
		if (! in_array($deposit, ['', 'held', 'zero'], true)) {
			$deposit = '';
		}

		return [
			'status' => $status,
			'deposit' => $deposit,
			'sort' => $sort,
			'dir' => $dir,
		];
	}

	private function leaseDepositHeld(PmLease $lease): float
	{
		$rent = (float) ($lease->deposit_amount ?? 0);
		$extra = collect($lease->additional_deposits ?? [])
			->filter(fn ($row) => is_array($row))
			->sum(fn (array $row) => (float) ($row['amount'] ?? 0));

		return round($rent + $extra, 2);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildStatementsByAllocationReport(): array
	{
		$query = PmLease::query()
			->with(['pmTenant', 'units.property'])
			->withSum('invoices as invoices_amount_sum', 'amount')
			->withSum('invoices as invoices_paid_sum', 'amount_paid');
		$this->applyDateRange($query, 'start_date');
		ReportScope::applyToLease($query, ReportScope::fromRequest());
		$leases = $query->latest('start_date')->limit(2000)->get();

		return [
			'stats' => [
				['label' => 'Lease allocations', 'value' => (string) $leases->count(), 'hint' => 'Recent'],
			],
			'columns' => ['Tenant', 'Allocation', 'Start', 'End', 'Invoiced', 'Paid', 'Outstanding'],
			'tableRows' => $leases->map(function (PmLease $lease) {
				$units = $lease->units->map(fn ($unit) => ($unit->property->name ?? '—').' / '.$unit->label)->implode(', ');
				$invoiced = (float) ($lease->invoices_amount_sum ?? 0);
				$paid = (float) ($lease->invoices_paid_sum ?? 0);

				return [
					(string) ($lease->pmTenant?->name ?? '—'),
					$units !== '' ? $units : '—',
					$this->date((string) $lease->start_date),
					$this->date((string) $lease->end_date),
					$this->money($invoiced),
					$this->money($paid),
					$this->money(max(0.0, $invoiced - $paid)),
				];
			})->all(),
		];
	}
}

