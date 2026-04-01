<?php

namespace App\Modules\Reporting\Landlord;

use App\Models\PmAccountingEntry;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Modules\Reporting\Support\ReportFilters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LandlordReportService
{
	use ReportFilters;

	/**
	 * @return array<string,mixed>
	 */
	public function buildLandlordLedgerReport(): array
	{
		$leaseQuery = PmLease::query()
			->with(['pmTenant', 'units.property'])
			->withSum('invoices as invoices_total', 'amount')
			->withSum('invoices as invoices_paid_total', 'amount_paid');
		$this->applyDateRange($leaseQuery, 'start_date');
		$leases = $leaseQuery->latest('start_date')->limit(300)->get();

		$leaseIds = $leases->pluck('id')->all();
		$arrearsByLease = [];
		$carryForwardCutoff = now()->startOfMonth()->toDateString();

		if (count($leaseIds) > 0) {
			$arrearsByLease = PmInvoice::query()
				->select('pm_lease_id', DB::raw('SUM(GREATEST(amount - amount_paid, 0)) as arrears_total'))
				->whereIn('pm_lease_id', $leaseIds)
				->whereDate('due_date', '<', $carryForwardCutoff)
				->groupBy('pm_lease_id')
				->pluck('arrears_total', 'pm_lease_id')
				->toArray();
		}

		return [
			'stats' => [
				['label' => 'Lease rows', 'value' => (string) $leases->count(), 'hint' => 'Landlord statement lines'],
				['label' => 'Monthly rent', 'value' => $this->money((float) $leases->sum('monthly_rent')), 'hint' => 'Current contract rent'],
				['label' => 'Amount paid', 'value' => $this->money((float) $leases->sum('invoices_paid_total')), 'hint' => 'Invoice settlements'],
				['label' => 'Total balance', 'value' => $this->money((float) $leases->sum(function (PmLease $lease) use ($arrearsByLease) {
					$monthlyRent = (float) ($lease->monthly_rent ?? 0);
					$arrears = (float) ($arrearsByLease[$lease->id] ?? 0);
					$total = $monthlyRent + $arrears;
					$paid = (float) ($lease->invoices_paid_total ?? 0);

					return max(0.0, $total - $paid);
				})), 'hint' => 'Outstanding'],
			],
			'columns' => ['Unit', 'Tenant Name', 'Monthly Rent', 'Arrears', 'Total', 'Amount Paid', 'Total Balance'],
			'tableRows' => $leases->map(function (PmLease $lease) use ($arrearsByLease) {
				$units = $lease->units->map(fn ($unit) => ($unit->property->name ?? '—').' / '.($unit->label ?? '—'))->implode(', ');
				$monthlyRent = (float) ($lease->monthly_rent ?? 0);
				$arrears = (float) ($arrearsByLease[$lease->id] ?? 0);
				$total = $monthlyRent + $arrears;
				$paid = (float) ($lease->invoices_paid_total ?? 0);
				$balance = max(0.0, $total - $paid);

				return [
					$units !== '' ? $units : '—',
					(string) ($lease->pmTenant?->name ?? '—'),
					$this->money($monthlyRent),
					$this->money($arrears),
					$this->money($total),
					$this->money($paid),
					$this->money($balance),
				];
			})->all(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildLandlordDetailedStatementReport(): array
	{
		$landlords = User::query()
			->where('property_portal_role', 'landlord')
			->orderBy('name')
			->get(['id', 'name']);

		$landlordId = $this->filterLandlordId();
		if ($landlordId === null) {
			return [
				'landlords' => $landlords,
				'selectedLandlordId' => null,
				'stats' => [
					['label' => 'Landlord', 'value' => '—', 'hint' => 'Select a landlord to view statement'],
				],
				'columns' => ['Date', 'Transaction Type', 'Transaction ID', 'Invoice No', 'Payments', 'Balance'],
				'tableRows' => [],
				'emptyTitle' => 'Select a landlord',
				'emptyHint' => 'Use the landlord filter to load the detailed statement.',
			];
		}

		$selected = $landlords->firstWhere('id', $landlordId);

		$query = PmLandlordLedgerEntry::query()
			->where('user_id', $landlordId)
			->orderBy('occurred_at')
			->orderBy('id');
		$this->applyDateRange($query, 'occurred_at');
		$entries = $query->limit(2000)->get();

		$running = 0.0;
		$rows = $entries->map(function (PmLandlordLedgerEntry $e) use (&$running) {
			$amount = (float) $e->amount;
			$isCredit = $e->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT;

			if ($e->balance_after !== null) {
				$running = (float) $e->balance_after;
			} else {
				$running += $isCredit ? $amount : (-1 * $amount);
			}

			$txnType = $e->reference_type ?: ($isCredit ? 'Credit' : 'Debit');
			$txnId = ($e->reference_type && $e->reference_id)
				? strtoupper((string) $e->reference_type).'-'.$e->reference_id
				: 'LED-'.$e->id;

			$invoiceNo = '—';
			if ($e->reference_type === 'invoice' && $e->reference_id) {
				$invoiceNo = (string) (PmInvoice::query()->where('id', $e->reference_id)->value('invoice_no') ?? '—');
			}

			$payments = $isCredit ? $this->money($amount) : $this->money(0);

			return [
				$this->dateTime((string) $e->occurred_at),
				(string) $txnType,
				(string) $txnId,
				(string) $invoiceNo,
				$payments,
				$this->money((float) $running),
			];
		})->all();

		return [
			'landlords' => $landlords,
			'selectedLandlordId' => $landlordId,
			'stats' => [
				['label' => 'Landlord', 'value' => (string) ($selected?->name ?? '—'), 'hint' => 'Detailed statement'],
				['label' => 'Entries', 'value' => (string) count($rows), 'hint' => 'Ledger rows'],
			],
			'columns' => ['Date', 'Transaction Type', 'Transaction ID', 'Invoice No', 'Payments', 'Balance'],
			'tableRows' => $rows,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildLandlordBalanceSummaryReport(): array
	{
		$landlords = User::query()
			->where('property_portal_role', 'landlord')
			->orderBy('name')
			->get(['id', 'name']);
		$selectedLandlordId = $this->filterLandlordId();
		$search = trim((string) request()->query('q', ''));
		$perPage = (int) request()->query('per_page', 30);
		if (! in_array($perPage, [10, 30, 50, 100, 200], true)) {
			$perPage = 30;
		}

		$baseQuery = PmLandlordLedgerEntry::query()
			->join('users', 'users.id', '=', 'pm_landlord_ledger_entries.user_id')
			->selectRaw('pm_landlord_ledger_entries.user_id as user_id')
			->selectRaw("COALESCE(SUM(CASE WHEN pm_landlord_ledger_entries.direction='credit' THEN pm_landlord_ledger_entries.amount ELSE 0 END),0) as total_credits")
			->selectRaw("COALESCE(SUM(CASE WHEN pm_landlord_ledger_entries.direction='debit' THEN pm_landlord_ledger_entries.amount ELSE 0 END),0) as total_debits")
			->selectRaw('COALESCE(MAX(pm_landlord_ledger_entries.occurred_at), NULL) as last_entry_at')
			->where('users.property_portal_role', 'landlord')
			->groupBy('pm_landlord_ledger_entries.user_id', 'users.name');

		if ($selectedLandlordId !== null) {
			$baseQuery->where('pm_landlord_ledger_entries.user_id', $selectedLandlordId);
		}
		if ($search !== '') {
			$baseQuery->where('users.name', 'like', '%'.$search.'%');
		}
		$this->applyDateRange($baseQuery, 'pm_landlord_ledger_entries.occurred_at');

		$statsRows = (clone $baseQuery)->get();
		$totalCredits = (float) $statsRows->sum(static fn ($row) => (float) ($row->total_credits ?? 0));
		$totalDebits = (float) $statsRows->sum(static fn ($row) => (float) ($row->total_debits ?? 0));
		$totalNetBalance = $totalCredits - $totalDebits;

		$paginator = $baseQuery
			->orderByDesc('last_entry_at')
			->paginate($perPage)
			->withQueryString();

		$users = User::query()
			->whereIn('id', $paginator->getCollection()->pluck('user_id')->filter()->all())
			->get(['id', 'name'])
			->keyBy('id');

		$tableRows = $paginator->getCollection()->map(function ($row) use ($users) {
			$credits = (float) ($row->total_credits ?? 0);
			$debits = (float) ($row->total_debits ?? 0);
			$net = $credits - $debits;

			return [
				(string) ($users->get($row->user_id)?->name ?? '—'),
				$this->money($credits),
				$this->money($debits),
				$this->money($net),
				$this->dateTime((string) ($row->last_entry_at ?? '')),
			];
		})->all();

		$exportRows = $statsRows->map(function ($row) use ($landlords) {
			$credits = (float) ($row->total_credits ?? 0);
			$debits = (float) ($row->total_debits ?? 0);
			$net = $credits - $debits;
			$name = (string) ($landlords->firstWhere('id', (int) $row->user_id)?->name ?? '—');

			return [
				$name,
				number_format($credits, 2, '.', ''),
				number_format($debits, 2, '.', ''),
				number_format($net, 2, '.', ''),
				(string) ($row->last_entry_at ?? ''),
			];
		})->values()->all();

		return [
			'landlords' => $landlords,
			'selectedLandlordId' => $selectedLandlordId,
			'perPage' => $perPage,
			'paginator' => $paginator,
			'showLandlordFilter' => true,
			'stats' => [
				['label' => 'Landlords', 'value' => (string) $statsRows->count(), 'hint' => 'Within current filters'],
				['label' => 'Total credits', 'value' => $this->money($totalCredits), 'hint' => 'Amounts paid to landlords'],
				['label' => 'Total debits', 'value' => $this->money($totalDebits), 'hint' => 'Charges/adjustments'],
				['label' => 'Net balance', 'value' => $this->money($totalNetBalance), 'hint' => 'Credits - debits'],
			],
			'columns' => ['Landlord', 'Total credits', 'Total debits', 'Net balance', 'Last entry'],
			'tableRows' => $tableRows,
			'exportColumns' => ['Landlord', 'TotalCredits', 'TotalDebits', 'NetBalance', 'LastEntryAt'],
			'exportRows' => $exportRows,
			'filters' => [
				'from' => $this->filterDateFrom(),
				'to' => $this->filterDateTo(),
				'landlord_id' => $selectedLandlordId ? (string) $selectedLandlordId : '',
				'q' => $search,
			],
			'emptyTitle' => 'No landlord balances found',
			'emptyHint' => 'Try widening the date range or removing filters.',
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildLandlordCommissionsReport(): array
	{
		$commissionDefaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
		$commissionDefaultPct = is_numeric($commissionDefaultRaw) ? (float) $commissionDefaultRaw : 10.0;
		if ($commissionDefaultPct < 0) {
			$commissionDefaultPct = 0.0;
		}

		$commissionOverridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
		$commissionOverrides = [];
		$decodedOverrides = json_decode($commissionOverridesRaw, true);
		if (is_array($decodedOverrides)) {
			foreach ($decodedOverrides as $propertyId => $pct) {
				$pid = (int) $propertyId;
				if ($pid <= 0 || ! is_numeric($pct)) {
					continue;
				}
				$commissionOverrides[$pid] = max(0.0, (float) $pct);
			}
		}

		$collectedByPropertyQ = DB::table('pm_payment_allocations as a')
			->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
			->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
			->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
			->where('pay.status', PmPayment::STATUS_COMPLETED)
			->groupBy('pu.property_id')
			->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total');
		$this->applyDateRange($collectedByPropertyQ, 'pay.paid_at');
		$collectedByProperty = $collectedByPropertyQ->pluck('total', 'property_id');

		$landlords = User::query()
			->where('property_portal_role', 'landlord')
			->orderBy('name')
			->get(['id', 'name']);
		$selectedLandlordId = $this->filterLandlordId();
		$propertyQ = $this->filterPropertySearch();
		$q = trim((string) request()->query('q', ''));
		$perPage = (int) request()->query('per_page', 30);
		if (! in_array($perPage, [10, 30, 50, 100, 200], true)) {
			$perPage = 30;
		}

		$links = DB::table('property_landlord as pl')
			->join('users as u', 'u.id', '=', 'pl.user_id')
			->join('properties as p', 'p.id', '=', 'pl.property_id')
			->select([
				'pl.user_id',
				'pl.property_id',
				'pl.ownership_percent',
				'u.name as landlord_name',
				'p.name as property_name',
			])
			->orderBy('p.name')
			->orderBy('u.name');
		if ($selectedLandlordId !== null) {
			$links->where('pl.user_id', $selectedLandlordId);
		}
		if ($propertyQ !== null) {
			$links->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$links = $links->get();

		$rawRows = $links->map(function ($link) use ($collectedByProperty, $commissionDefaultPct, $commissionOverrides) {
			$pid = (int) $link->property_id;
			$ownership = (float) ($link->ownership_percent ?? 0);
			$income = ((float) ($collectedByProperty[$pid] ?? 0)) * ($ownership / 100);
			$rate = $commissionOverrides[$pid] ?? $commissionDefaultPct;
			$commission = $income * ($rate / 100);

			return [
				'landlord_id' => (int) ($link->user_id ?? 0),
				'property' => (string) ($link->property_name ?? '—'),
				'landlord' => (string) ($link->landlord_name ?? '—'),
				'income' => $income,
				'commission' => $commission,
			];
		})->filter(fn (array $r) => $r['income'] > 0);
		if ($q !== '') {
			$qNorm = mb_strtolower($q);
			$rawRows = $rawRows->filter(static function (array $r) use ($qNorm): bool {
				$haystack = mb_strtolower(($r['property'] ?? '').' '.($r['landlord'] ?? ''));

				return str_contains($haystack, $qNorm);
			});
		}
		$rawRows = $rawRows->values();

		$totalIncome = (float) $rawRows->sum('income');
		$totalCommission = (float) $rawRows->sum('commission');
		$paginator = $this->paginateCollection($rawRows, $perPage);
		$pageRows = $paginator->getCollection();

		return [
			'stats' => [
				['label' => 'Default commission rate', 'value' => number_format($commissionDefaultPct, 2).'%', 'hint' => 'Per-property overrides apply'],
				['label' => 'Income (collected)', 'value' => $this->money($totalIncome), 'hint' => 'Landlord share'],
				['label' => 'Agent earns', 'value' => $this->money($totalCommission), 'hint' => 'Commission total'],
			],
			'columns' => ['Property', 'Landlord', 'Income (total rent collected)', 'Commissions (agent earns)'],
			'tableRows' => $pageRows->map(fn (array $r) => [
				$r['property'],
				$r['landlord'],
				$this->money((float) $r['income']),
				$this->money((float) $r['commission']),
			])->all(),
			'exportColumns' => ['Property', 'Landlord', 'IncomeCollected', 'Commission'],
			'exportRows' => $rawRows->map(fn (array $r) => [
				$r['property'],
				$r['landlord'],
				number_format((float) $r['income'], 2, '.', ''),
				number_format((float) $r['commission'], 2, '.', ''),
			])->all(),
			'landlords' => $landlords,
			'selectedLandlordId' => $selectedLandlordId,
			'showLandlordFilter' => true,
			'showPropertyFilter' => true,
			'perPage' => $perPage,
			'paginator' => $paginator,
			'filters' => [
				'from' => $this->filterDateFrom(),
				'to' => $this->filterDateTo(),
				'property' => $propertyQ,
				'landlord_id' => $selectedLandlordId ? (string) $selectedLandlordId : '',
				'q' => $q,
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildRentCollectionReport(): array
	{
		$landlords = User::query()
			->where('property_portal_role', 'landlord')
			->orderBy('name')
			->get(['id', 'name']);
		$landlordId = $this->filterLandlordId();
		$q = trim((string) request()->query('q', ''));
		$perPage = min(200, max(10, (int) request()->integer('per_page', 30)));

		$query = PmPayment::query()->with(['tenant', 'invoices.unit.property']);
		if ($landlordId !== null) {
			$query->whereExists(function ($sub) use ($landlordId) {
				$sub->selectRaw('1')
					->from('pm_payment_allocations as a')
					->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
					->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
					->join('property_landlord as pl', 'pl.property_id', '=', 'u.property_id')
					->whereColumn('a.pm_payment_id', 'pm_payments.id')
					->where('pl.user_id', $landlordId);
			});
		}
		if ($q !== '') {
			$query->where(function ($sub) use ($q) {
				$sub->where('external_ref', 'like', '%'.$q.'%')
					->orWhere('channel', 'like', '%'.$q.'%')
					->orWhereHas('tenant', function ($tenantQ) use ($q) {
						$tenantQ->where('name', 'like', '%'.$q.'%')
							->orWhere('email', 'like', '%'.$q.'%')
							->orWhere('phone', 'like', '%'.$q.'%')
							->orWhere('account_number', 'like', '%'.$q.'%');
					})
					->orWhereHas('invoices.unit', function ($unitQ) use ($q) {
						$unitQ->where('label', 'like', '%'.$q.'%')
							->orWhereHas('property', function ($propertyQ) use ($q) {
								$propertyQ->where('name', 'like', '%'.$q.'%');
							});
					});
			});
		}
		$this->applyDateRange($query, 'paid_at');
		$paginator = $query
			->latest('paid_at')
			->latest('id')
			->paginate($perPage)
			->withQueryString();
		$payments = $paginator->getCollection();

		$channelSummary = $payments
			->where('status', PmPayment::STATUS_COMPLETED)
			->groupBy(fn (PmPayment $p) => (string) ($p->channel ?: 'unknown'))
			->map(fn ($group, $channel) => [
				'channel' => ucfirst((string) $channel),
				'count' => $group->count(),
				'amount' => (float) $group->sum('amount'),
			])
			->sortByDesc('amount')
			->values()
			->all();

		$propertySummary = collect();
		foreach ($payments as $payment) {
			if ($payment->status !== PmPayment::STATUS_COMPLETED) {
				continue;
			}
			foreach ($payment->invoices as $invoice) {
				$key = (string) ($invoice->unit?->property?->name ?? '—');
				$alloc = (float) ($invoice->pivot?->amount ?? 0);
				if ($alloc <= 0) {
					continue;
				}
				if (! $propertySummary->has($key)) {
					$propertySummary->put($key, 0.0);
				}
				$propertySummary->put($key, (float) $propertySummary->get($key) + $alloc);
			}
		}
		$propertySummary = $propertySummary
			->map(fn ($amount, $property) => ['property' => (string) $property, 'amount' => (float) $amount])
			->sortByDesc('amount')
			->values()
			->all();

		return [
			'stats' => [
				['label' => 'Landlord', 'value' => (string) ($landlords->firstWhere('id', $landlordId)?->name ?? 'All'), 'hint' => 'Filter'],
				['label' => 'Payments', 'value' => (string) $payments->count(), 'hint' => 'Recent'],
				['label' => 'Completed', 'value' => (string) $payments->where('status', PmPayment::STATUS_COMPLETED)->count(), 'hint' => 'Settled'],
				['label' => 'Collected', 'value' => $this->money((float) $payments->where('status', PmPayment::STATUS_COMPLETED)->sum('amount')), 'hint' => 'Completed only'],
				['label' => 'Pending', 'value' => (string) $payments->where('status', PmPayment::STATUS_PENDING)->count(), 'hint' => 'Awaiting settlement'],
				['label' => 'Failed', 'value' => (string) $payments->where('status', PmPayment::STATUS_FAILED)->count(), 'hint' => 'Failed attempts'],
			],
			'columns' => ['Date', 'Tenant', 'Property / Unit', 'Channel', 'Amount', 'Reference', 'Status'],
			'tableRows' => $payments->map(function (PmPayment $payment) {
				$allocations = $payment->invoices
					->map(fn ($invoice) => (($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—')))
					->filter()
					->unique()
					->values()
					->implode(', ');

				return [
					$this->dateTime((string) $payment->paid_at),
					(string) ($payment->tenant?->name ?? '—'),
					$allocations !== '' ? $allocations : '—',
					ucfirst((string) ($payment->channel ?? '—')),
					$this->money((float) $payment->amount),
					(string) ($payment->external_ref ?? '—'),
					ucfirst((string) $payment->status),
				];
			})->all(),
			'exportColumns' => ['Date', 'Tenant', 'Property / Unit', 'Channel', 'Amount', 'Reference', 'Status'],
			'exportRows' => $payments->map(function (PmPayment $payment) {
				$allocations = $payment->invoices
					->map(fn ($invoice) => (($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—')))
					->filter()
					->unique()
					->values()
					->implode(', ');

				return [
					$this->dateTime((string) $payment->paid_at),
					(string) ($payment->tenant?->name ?? '—'),
					$allocations !== '' ? $allocations : '—',
					ucfirst((string) ($payment->channel ?? '—')),
					number_format((float) $payment->amount, 2, '.', ''),
					(string) ($payment->external_ref ?? '—'),
					ucfirst((string) $payment->status),
				];
			})->all(),
			'showLandlordFilter' => true,
			'landlords' => $landlords,
			'selectedLandlordId' => $landlordId,
			'perPage' => $perPage,
			'channelSummary' => $channelSummary,
			'propertySummary' => $propertySummary,
			'paginator' => $paginator,
			'showPrintAction' => true,
			'filters' => [
				'from' => $this->filterDateFrom(),
				'to' => $this->filterDateTo(),
				'landlord_id' => $landlordId ? (string) $landlordId : '',
				'q' => $q,
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildPropertyStatementReport(): array
	{
		$from = $this->filterDateFrom();
		$to = $this->filterDateTo();
		$periodStart = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
		$periodEnd = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

		$propertyQ = $this->filterPropertySearch();
		$q = trim((string) request()->query('q', ''));

		$openingQ = DB::table('pm_invoices as i')
			->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
			->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
			->join('properties as p', 'p.id', '=', 'u.property_id')
			->whereExists(function ($sub) {
				$sub->selectRaw('1')
					->from('pm_accounting_entries as ae')
					->where('ae.source_key', 'invoice_issued')
					->whereColumn('ae.reference', 'i.invoice_no');
			})
			->whereDate('i.issue_date', '<', $periodStart->toDateString())
			->whereColumn('i.amount_paid', '<', 'i.amount')
			->selectRaw("
                i.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as opening_balance
            ")
			->groupBy('i.pm_tenant_id', 'i.property_unit_id');
		if ($propertyQ !== null) {
			$openingQ->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$openingRows = $openingQ->get();

		$periodInvQ = DB::table('pm_invoices as i')
			->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
			->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
			->join('properties as p', 'p.id', '=', 'u.property_id')
			->whereExists(function ($sub) {
				$sub->selectRaw('1')
					->from('pm_accounting_entries as ae')
					->where('ae.source_key', 'invoice_issued')
					->whereColumn('ae.reference', 'i.invoice_no');
			})
			->whereBetween('i.issue_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
			->selectRaw("
                i.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(CASE WHEN i.invoice_type = 'rent' THEN i.amount ELSE 0 END),0) as rent_invoices,
                COALESCE(SUM(CASE
                    WHEN (i.invoice_type = 'mixed' AND LOWER(COALESCE(i.description, '')) LIKE '%penalty%')
                      OR (i.invoice_type NOT IN ('rent', 'water', 'mixed'))
                    THEN i.amount ELSE 0 END),0) as penalties_invoices,
                COALESCE(SUM(CASE
                    WHEN i.invoice_type = 'water'
                      OR (i.invoice_type = 'mixed' AND LOWER(COALESCE(i.description, '')) NOT LIKE '%penalty%')
                    THEN i.amount ELSE 0 END),0) as bills_invoices,
                COALESCE(SUM(i.amount),0) as invoices_total
            ")
			->groupBy('i.pm_tenant_id', 'i.property_unit_id');
		if ($propertyQ !== null) {
			$periodInvQ->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$periodInvRows = $periodInvQ->get();

		$payQ = DB::table('pm_payment_allocations as a')
			->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
			->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
			->join('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
			->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
			->join('properties as p', 'p.id', '=', 'u.property_id')
			->where('pay.status', PmPayment::STATUS_COMPLETED)
			->whereExists(function ($sub) {
				$sub->selectRaw('1')
					->from('pm_accounting_entries as ae')
					->where('ae.source_key', 'payment_received')
					->whereRaw("ae.reference = COALESCE(NULLIF(pay.external_ref, ''), CONCAT('PAY-', pay.id))");
			})
			->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
			->selectRaw("
                pay.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(a.amount),0) as payments_received
            ")
			->groupBy('pay.pm_tenant_id', 'i.property_unit_id');
		if ($propertyQ !== null) {
			$payQ->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$paymentRows = $payQ->get();

		$index = [];
		$put = static function ($tenantId, $unitId, array $payload) use (&$index) {
			$k = (string) $tenantId.'|'.(string) $unitId;
			$index[$k] = array_merge($index[$k] ?? [
				'tenant_name' => '—',
				'unit_label' => '—',
				'property_name' => '—',
				'opening_balance' => 0.0,
				'rent_invoices' => 0.0,
				'penalties_invoices' => 0.0,
				'bills_invoices' => 0.0,
				'invoices_total' => 0.0,
				'payments_received' => 0.0,
			], $payload);
		};

		foreach ($openingRows as $r) {
			$put($r->tenant_id, $r->unit_id, [
				'tenant_name' => (string) ($r->tenant_name ?? '—'),
				'unit_label' => (string) ($r->unit_label ?? '—'),
				'property_name' => (string) ($r->property_name ?? '—'),
				'opening_balance' => (float) ($r->opening_balance ?? 0),
			]);
		}
		foreach ($periodInvRows as $r) {
			$put($r->tenant_id, $r->unit_id, [
				'tenant_name' => (string) ($r->tenant_name ?? '—'),
				'unit_label' => (string) ($r->unit_label ?? '—'),
				'property_name' => (string) ($r->property_name ?? '—'),
				'rent_invoices' => (float) ($r->rent_invoices ?? 0),
				'penalties_invoices' => (float) ($r->penalties_invoices ?? 0),
				'bills_invoices' => (float) ($r->bills_invoices ?? 0),
				'invoices_total' => (float) ($r->invoices_total ?? 0),
			]);
		}
		foreach ($paymentRows as $r) {
			$put($r->tenant_id, $r->unit_id, [
				'tenant_name' => (string) ($r->tenant_name ?? '—'),
				'unit_label' => (string) ($r->unit_label ?? '—'),
				'property_name' => (string) ($r->property_name ?? '—'),
				'payments_received' => (float) ($r->payments_received ?? 0),
			]);
		}

		$rowsRaw = collect(array_values($index))
			->map(function (array $r) {
				$opening = (float) ($r['opening_balance'] ?? 0);
				$rent = (float) ($r['rent_invoices'] ?? 0);
				$pen = (float) ($r['penalties_invoices'] ?? 0);
				$bills = (float) ($r['bills_invoices'] ?? 0);
				$invTotal = $rent + $pen + $bills;
				$paid = (float) ($r['payments_received'] ?? 0);
				$closing = max(0.0, $opening + $invTotal - $paid);

				return [
					'property_name' => (string) ($r['property_name'] ?? '—'),
					'tenant_name' => (string) ($r['tenant_name'] ?? '—'),
					'unit_label' => (string) ($r['unit_label'] ?? '—'),
					'closing' => $closing,
					'rent' => $rent,
					'penalty' => $pen,
					'bills' => $bills,
					'paid' => $paid,
					'opening' => $opening,
				];
			})
			->sortBy([fn ($a) => $a['property_name'], fn ($a) => $a['tenant_name'], fn ($a) => $a['unit_label']])
			->values();
		if ($q !== '') {
			$qNorm = mb_strtolower($q);
			$rowsRaw = $rowsRaw->filter(static function (array $r) use ($qNorm): bool {
				$haystack = mb_strtolower(
					($r['property_name'] ?? '').' '.($r['tenant_name'] ?? '').' '.($r['unit_label'] ?? '')
				);

				return str_contains($haystack, $qNorm);
			})->values();
		}
		$rowsRaw = $rowsRaw->all();

		$perPage = (int) request()->query('per_page', 30);
		if (! in_array($perPage, [10, 30, 50, 100, 200], true)) {
			$perPage = 30;
		}
		$paginator = $this->paginateCollection(collect($rowsRaw), $perPage);
		$pageRowsRaw = $paginator->getCollection();

		$rows = $pageRowsRaw->map(fn (array $r) => [
			$r['property_name'],
			$r['tenant_name'],
			$r['unit_label'],
			$this->money((float) $r['closing']),
			$this->money((float) $r['rent']),
			$this->money((float) $r['penalty']),
			$this->money((float) $r['bills']),
			$this->money((float) $r['paid']),
			$this->money((float) $r['opening']),
			$this->money((float) $r['closing']),
		])->all();

		$totalClosing = (float) collect($rowsRaw)->sum('closing');

		$postedInvoicesQ = DB::table('pm_accounting_entries as ae')
			->leftJoin('properties as p', 'p.id', '=', 'ae.property_id')
			->where('ae.source_key', 'invoice_issued')
			->where('ae.category', PmAccountingEntry::CATEGORY_INCOME)
			->where('ae.entry_type', PmAccountingEntry::TYPE_CREDIT)
			->whereBetween('ae.entry_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
		if ($propertyQ !== null) {
			$postedInvoicesQ->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$postedInvoices = (float) $postedInvoicesQ->sum('ae.amount');

		$postedPaymentsQ = DB::table('pm_accounting_entries as ae')
			->leftJoin('properties as p', 'p.id', '=', 'ae.property_id')
			->where('ae.source_key', 'payment_received')
			->where('ae.category', PmAccountingEntry::CATEGORY_ASSET)
			->where('ae.entry_type', PmAccountingEntry::TYPE_DEBIT)
			->whereBetween('ae.entry_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
		if ($propertyQ !== null) {
			$postedPaymentsQ->where('p.name', 'like', '%'.$propertyQ.'%');
		}
		$postedPayments = (float) $postedPaymentsQ->sum('ae.amount');

		return [
			'stats' => [
				['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Tenant-unit lines'],
				['label' => 'Total outstanding', 'value' => $this->money((float) $totalClosing), 'hint' => 'Balances'],
				['label' => 'Invoices (posted)', 'value' => $this->money($postedInvoices), 'hint' => 'Accounting module'],
				['label' => 'Payments (posted)', 'value' => $this->money($postedPayments), 'hint' => 'Accounting module'],
				['label' => 'Period', 'value' => $periodStart->format('Y-m-d').' -> '.$periodEnd->format('Y-m-d'), 'hint' => 'Filter'],
			],
			'columns' => [
				'Property',
				'Tenant name',
				'Unit number',
				'Balance (owes us)',
				'Invoices: Rent',
				'Invoices: Penalties',
				'Invoices: Bills',
				'Payments received',
				'Opening balance',
				'Total balance',
			],
			'tableRows' => $rows,
			'exportColumns' => [
				'Property',
				'TenantName',
				'UnitNumber',
				'BalanceOwesUs',
				'InvoicesRent',
				'InvoicesPenalties',
				'InvoicesBills',
				'PaymentsReceived',
				'OpeningBalance',
				'TotalBalance',
			],
			'exportRows' => collect($rowsRaw)->map(fn (array $r) => [
				$r['property_name'],
				$r['tenant_name'],
				$r['unit_label'],
				number_format((float) $r['closing'], 2, '.', ''),
				number_format((float) $r['rent'], 2, '.', ''),
				number_format((float) $r['penalty'], 2, '.', ''),
				number_format((float) $r['bills'], 2, '.', ''),
				number_format((float) $r['paid'], 2, '.', ''),
				number_format((float) $r['opening'], 2, '.', ''),
				number_format((float) $r['closing'], 2, '.', ''),
			])->all(),
			'showPropertyFilter' => true,
			'perPage' => $perPage,
			'paginator' => $paginator,
			'filters' => [
				'from' => $from,
				'to' => $to,
				'property' => $propertyQ,
				'q' => $q,
			],
		];
	}

	/**
	 * @param Collection<int,mixed> $rows
	 */
	private function paginateCollection(Collection $rows, int $perPage): LengthAwarePaginator
	{
		$page = max(1, (int) request()->query('page', 1));
		$total = $rows->count();
		$items = $rows->forPage($page, $perPage)->values();

		return (new LengthAwarePaginator(
			$items,
			$total,
			$perPage,
			$page,
			['path' => request()->url(), 'query' => request()->query()]
		))->withQueryString();
	}

	private function filterLandlordId(): ?int
	{
		$id = request()->query('landlord_id');
		if ($id === null || $id === '') {
			return null;
		}

		if (is_numeric($id)) {
			$int = (int) $id;

			return $int > 0 ? $int : null;
		}

		return null;
	}
}

