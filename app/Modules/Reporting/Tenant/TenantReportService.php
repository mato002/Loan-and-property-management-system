<?php

namespace App\Modules\Reporting\Tenant;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPenaltyRule;
use App\Models\PmUnitMovement;
use App\Modules\Reporting\Support\ReportFilters;

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
		$invoices = $invoiceQuery->orderBy('due_date')->limit(300)->get();

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
		$movements = $query->latest('completed_on')->latest('id')->limit(250)->get();

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
		$movements = $query->latest('completed_on')->latest('id')->limit(250)->get();

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
		$query = PmLease::query()->with(['pmTenant', 'units.property']);
		$this->applyDateRange($query, 'start_date');
		$leases = $query->latest('start_date')->limit(250)->get();
		$totalDepositPaid = (float) $leases->sum(fn (PmLease $lease) => (float) ($lease->deposit_amount ?? 0));
		$totalRefunded = 0.0;
		$totalBalance = max(0.0, $totalDepositPaid - $totalRefunded);

		return [
			'stats' => [
				['label' => 'Tenants', 'value' => (string) $leases->pluck('pm_tenant_id')->filter()->unique()->count(), 'hint' => 'With leases'],
				['label' => 'Deposit paid', 'value' => $this->money($totalDepositPaid), 'hint' => 'Recorded'],
				['label' => 'Refunded amount', 'value' => $this->money($totalRefunded), 'hint' => 'Recorded'],
				['label' => 'Balance', 'value' => $this->money($totalBalance), 'hint' => 'Deposit - refunded'],
			],
			'columns' => ['Tenant', 'Property', 'Unit', 'Deposit Paid', 'Refunded Amount', 'Balance'],
			'tableRows' => $leases->map(function (PmLease $lease) {
				$units = $lease->units;
				$propertyNames = $units->map(fn ($u) => $u->property?->name)->filter()->unique()->implode(', ');
				$unitNames = $units->map(fn ($u) => $u->label)->filter()->implode(', ');
				$depositPaid = (float) ($lease->deposit_amount ?? 0);
				$refundedAmount = 0.0;
				$balance = max(0.0, $depositPaid - $refundedAmount);

				return [
					(string) ($lease->pmTenant?->name ?? '—'),
					$propertyNames !== '' ? $propertyNames : '—',
					$unitNames !== '' ? $unitNames : '—',
					$this->money($depositPaid),
					$this->money($refundedAmount),
					$this->money($balance),
				];
			})->all(),
		];
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
		$leases = $query->latest('start_date')->limit(250)->get();

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

