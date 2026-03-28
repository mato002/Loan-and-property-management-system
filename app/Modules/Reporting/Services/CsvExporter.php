<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
	/**
	 * Stream a CSV download response.
	 *
	 * @param array<int,string> $columns
	 * @param array<int,array<int,mixed>> $rows
	 */
	public function stream(array $columns, array $rows, string $filename): StreamedResponse
	{
		return response()->streamDownload(function () use ($columns, $rows) {
			$out = fopen('php://output', 'wb');
			if ($out === false) {
				return;
			}

			if (is_array($columns) && count($columns) > 0) {
				fputcsv($out, $columns);
			}
			if (is_array($rows)) {
				foreach ($rows as $row) {
					if (! is_array($row)) {
						continue;
					}

					$clean = array_map(static function ($cell) {
						if ($cell instanceof HtmlString) {
							return strip_tags((string) $cell);
						}

						return (string) $cell;
					}, $row);
					fputcsv($out, $clean);
				}
			}

			fclose($out);
		}, $filename, [
			'Content-Type' => 'text/csv; charset=UTF-8',
		]);
	}
}

