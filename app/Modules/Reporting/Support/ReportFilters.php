<?php

namespace App\Modules\Reporting\Support;

use Illuminate\Support\Carbon;

trait ReportFilters
{
	/**
	 * Format amount as money string.
	 */
	private function money(float $amount): string
	{
		return 'KES '.number_format($amount, 2);
	}

	/**
	 * Format a date (Y-m-d) value or return em dash when empty.
	 */
	private function date(?string $value): string
	{
		if ($value === null || $value === '') {
			return '—';
		}

		return (string) Carbon::parse($value)->format('Y-m-d');
	}

	/**
	 * Format a datetime (Y-m-d H:i) value or return em dash when empty.
	 */
	private function dateTime(?string $value): string
	{
		if ($value === null || $value === '') {
			return '—';
		}

		return (string) Carbon::parse($value)->format('Y-m-d H:i');
	}

	/**
	 * Parse "from" date query (YYYY-MM-DD) or null.
	 */
	private function filterDateFrom(): ?string
	{
		$from = request()->query('from');
		if (! is_string($from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
			return null;
		}

		return $from;
	}

	/**
	 * Parse "to" date query (YYYY-MM-DD) or null.
	 */
	private function filterDateTo(): ?string
	{
		$to = request()->query('to');
		if (! is_string($to) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			return null;
		}

		return $to;
	}

	/**
	 * Parse property search query string or null.
	 */
	private function filterPropertySearch(): ?string
	{
		$q = request()->query('property');
		if (! is_string($q)) {
			return null;
		}
		$q = trim($q);
		if ($q === '') {
			return null;
		}

		return mb_substr($q, 0, 80);
	}

	/**
	 * Apply date range to an Eloquent/Query Builder.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
	 */
	private function applyDateRange($query, string $dateColumn): void
	{
		$from = $this->filterDateFrom();
		$to = $this->filterDateTo();

		if ($from !== null) {
			$query->whereDate($dateColumn, '>=', $from);
		}
		if ($to !== null) {
			$query->whereDate($dateColumn, '<=', $to);
		}
	}
}

