<?php

namespace App\Modules\Reporting\Tenant;

use App\Models\PmInvoice;
use App\Modules\Reporting\Support\ReportFilters;

class AgingBalanceReportBuilder
{
	use ReportFilters;

	/**
	 * Build tenant aging balance summary payload.
	 *
	 * @return array<string,mixed>
	 */
	public function build(): array
	{
		$query = PmInvoice::query()->with(['tenant', 'unit.property']);
		$this->applyDateRange($query, 'due_date');
		$invoices = $query
			->whereColumn('amount_paid', '<', 'amount')
			->latest('due_date')
			->limit(500)
			->get();

		$today = now()->startOfDay();

		$groups = [];
		foreach ($invoices as $invoice) {
			$balance = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
			if ($balance <= 0) {
				continue;
			}

			$tenantName = (string) ($invoice->tenant?->name ?? '—');
			$propertyName = (string) ($invoice->unit?->property?->name ?? '—');
			$unitNo = (string) ($invoice->unit?->label ?? '—');
			$key = $tenantName.'|'.$propertyName.'|'.$unitNo;

			if (! isset($groups[$key])) {
				$groups[$key] = [
					'tenant' => $tenantName,
					'property' => $propertyName,
					'unit' => $unitNo,
					'total_balance' => 0.0,
					'has_overdue' => false,
				];
			}

			$groups[$key]['total_balance'] += $balance;
			if ($invoice->due_date && $invoice->due_date->lt($today)) {
				$groups[$key]['has_overdue'] = true;
			}
		}

		$rows = collect(array_values($groups))
			->sortByDesc('total_balance')
			->map(fn (array $row) => [
				$row['tenant'],
				$row['property'],
				$row['unit'],
				$row['has_overdue'] ? 'Overdue' : 'Open',
				$this->money((float) $row['total_balance']),
			])
			->all();

		return [
			'stats' => [
				['label' => 'Tenants with balance', 'value' => (string) count($rows), 'hint' => 'Outstanding'],
				['label' => 'Overdue', 'value' => (string) collect($rows)->where(2, 'Overdue')->count(), 'hint' => 'Past due'],
				['label' => 'Total balance', 'value' => $this->money((float) collect(array_values($groups))->sum('total_balance')), 'hint' => 'Amount owed'],
			],
			'columns' => ['Tenant Name', 'Property', 'Unit No', 'Status', 'Total Balance'],
			'tableRows' => $rows,
		];
	}
}

