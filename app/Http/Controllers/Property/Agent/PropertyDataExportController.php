<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\PropertyUnit;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyDataExportController extends Controller
{
    public function maintenanceCosts(): StreamedResponse
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            (string) $j->id,
            $j->request->unit->property->name ?? '—',
            $j->request->unit->label ?? '—',
            $j->request->category ?? '—',
            $j->vendor?->name ?? '—',
            $j->quote_amount !== null ? (string) $j->quote_amount : '',
            $j->status,
            $j->completed_at?->format('Y-m-d') ?? '',
        ]);

        return $this->csvResponse('maintenance-costs.csv', [
            'Job ID', 'Property', 'Unit', 'Category', 'Vendor', 'Quote (KES)', 'Status', 'Completed',
        ], $rows->all());
    }

    public function performanceSnapshot(): StreamedResponse
    {
        $properties = Property::query()->withCount(['units as vacant' => fn ($q) => $q->where('status', PropertyUnit::STATUS_VACANT)])
            ->withCount(['units as occupied' => fn ($q) => $q->where('status', PropertyUnit::STATUS_OCCUPIED)])
            ->withCount('units')
            ->orderBy('name')
            ->get();

        $rows = $properties->map(fn (Property $p) => [
            $p->name,
            (string) $p->units_count,
            (string) $p->vacant,
            (string) $p->occupied,
        ]);

        return $this->csvResponse('portfolio-performance.csv', [
            'Property', 'Units', 'Vacant', 'Occupied',
        ], $rows->all());
    }

    public function incomeExpensesSummary(): StreamedResponse
    {
        $income = (float) PmInvoice::query()->sum('amount');
        $maint = (float) PmMaintenanceJob::query()->whereNotNull('quote_amount')->sum('quote_amount');
        $paymentsIn = (float) PmPayment::query()->where('status', PmPayment::STATUS_COMPLETED)->sum('amount');

        $rows = [
            ['Billed (invoices, all time)', 'Income', number_format($income, 2, '.', '')],
            ['Maintenance job quotes (all time)', 'Expense', number_format($maint, 2, '.', '')],
            ['Completed tenant payments (all time)', 'Cash in', number_format($paymentsIn, 2, '.', '')],
            ['NOI proxy (billed − maint. quotes)', 'Derived', number_format(max(0, $income - $maint), 2, '.', '')],
        ];

        return $this->csvResponse('income-expenses-summary.csv', ['Line', 'Class', 'Amount KES'], $rows);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function csvResponse(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
