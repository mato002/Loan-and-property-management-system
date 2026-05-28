<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Services\Property\PropertyMoney;
use App\Services\Property\UtilityLedgerService;
use App\Services\Property\UtilityReconciliationService;
use App\Support\TabularExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UtilityLedgerController extends Controller
{
    public function __construct(
        private readonly UtilityLedgerService $ledgerService,
        private readonly UtilityReconciliationService $reconciliationService,
    ) {}

    public function reconciliation(Request $request): View|StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'property' => ['nullable', 'integer'],
            'export' => ['nullable', 'string'],
        ]);

        $from = trim((string) ($validated['from'] ?? $request->query('from', '')));
        $to = trim((string) ($validated['to'] ?? $request->query('to', '')));
        $propertyId = (int) ($validated['property'] ?? $request->query('property', 0)) ?: null;
        $agentUserId = (int) auth()->id() ?: null;

        $data = $this->reconciliationService->dashboard(
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $propertyId,
            $agentUserId,
        );

        $export = strtolower((string) ($validated['export'] ?? $request->query('export', '')));
        if (in_array($export, ['csv', 'pdf'], true)) {
            return $this->exportAging($data['aging_rows'] ?? [], $export);
        }

        $totals = $data['totals'];
        $kpis = $data['kpis'];

        return property_view('property.agent.revenue.utility_reconciliation', [
            'data' => $data,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'property' => $propertyId ? (string) $propertyId : '',
            ],
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                ['label' => 'Total billed', 'value' => PropertyMoney::kes($totals['total_billed']), 'hint' => 'Water & mixed invoices'],
                ['label' => 'Total collected', 'value' => PropertyMoney::kes($totals['total_collected']), 'hint' => 'Cash receipts'],
                ['label' => 'Open utility AR', 'value' => PropertyMoney::kes($totals['open_ar']), 'hint' => 'Outstanding'],
                ['label' => 'Recovery', 'value' => $kpis['recovery_pct'].'%', 'hint' => 'Settled / billed+penalties'],
            ],
            'kpiCards' => [
                ['label' => 'Recovery rate', 'value' => $kpis['recovery_pct'].'%', 'hint' => 'Collections + credit / billed'],
                ['label' => 'Unpaid rate', 'value' => $kpis['unpaid_pct'].'%', 'hint' => 'Open AR / billed'],
                ['label' => 'Penalty ratio', 'value' => $kpis['penalty_ratio'].'%', 'hint' => 'Penalties / base billed'],
                ['label' => 'Collection efficiency', 'value' => $kpis['collection_efficiency'].'%', 'hint' => 'Cash / billed+penalties'],
            ],
        ]);
    }

    public function index(Request $request): View|StreamedResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'property' => ['nullable', 'integer'],
            'export' => ['nullable', 'string'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $propertyId = (int) ($validated['property'] ?? $request->query('property', 0)) ?: null;

        $summaries = $this->ledgerService->tenantSummaries($q, $propertyId, 30);

        $export = strtolower((string) ($validated['export'] ?? $request->query('export', '')));
        if (in_array($export, ['csv', 'pdf'], true)) {
            return TabularExport::stream(
                'utility-ledger-tenants-'.now()->format('Ymd_His'),
                ['Tenant', 'Phone', 'Open utility balance', 'Open invoices'],
                function () use ($summaries) {
                    foreach ($summaries->items() as $row) {
                        yield [
                            $row['name'],
                            $row['phone'],
                            $row['utility_balance_display'],
                            (string) $row['open_invoices'],
                        ];
                    }
                },
                $export === 'pdf' ? TabularExport::FORMAT_PDF : TabularExport::FORMAT_CSV,
            );
        }

        $totalOpen = collect($summaries->items())->sum('utility_balance');

        return property_view('property.agent.revenue.utility_ledger_index', [
            'summaries' => $summaries,
            'filters' => ['q' => $q, 'property' => $propertyId ? (string) $propertyId : ''],
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                ['label' => 'Tenants', 'value' => (string) $summaries->total(), 'hint' => 'With utility history'],
                ['label' => 'Total open utility AR', 'value' => PropertyMoney::kes($totalOpen), 'hint' => 'This page'],
                ['label' => 'Reconciliation', 'value' => 'View', 'hint' => 'Portfolio totals'],
            ],
        ]);
    }

    public function tenantStatement(Request $request, PmTenant $tenant): View|StreamedResponse|Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'export' => ['nullable', 'string'],
            'embed' => ['nullable'],
        ]);

        $from = trim((string) ($validated['from'] ?? ''));
        $to = trim((string) ($validated['to'] ?? ''));
        $embed = $request->boolean('embed');

        $ledger = $this->ledgerService->buildTenantLedger((int) $tenant->id, $from !== '' ? $from : null, $to !== '' ? $to : null);
        $currentBalance = $this->ledgerService->currentBalanceForTenant((int) $tenant->id);

        $viewData = [
            'tenant' => $tenant,
            'ledger' => $ledger,
            'currentBalance' => $currentBalance,
            'filters' => ['from' => $from, 'to' => $to],
            'branding' => $this->branding(),
            'generatedAt' => now()->format('d M Y, H:i'),
            'embed' => $embed,
        ];

        $export = strtolower((string) ($validated['export'] ?? $request->query('export', '')));
        if ($export === 'csv') {
            return TabularExport::stream(
                'utility-statement-'.$tenant->id.'-'.now()->format('Ymd_His'),
                ['Date', 'Type', 'Reference', 'Description', 'Debit', 'Credit', 'Balance'],
                function () use ($ledger) {
                    foreach ($ledger['rows'] as $row) {
                        yield [
                            $row['date'],
                            $row['type_label'],
                            $row['reference'],
                            $row['description'],
                            $row['debit'] > 0 ? $row['debit'] : '',
                            $row['credit'] > 0 ? $row['credit'] : '',
                            $row['balance_after'],
                        ];
                    }
                },
                TabularExport::FORMAT_CSV,
            );
        }

        if ($export === 'pdf') {
            return $this->streamStatementPdf($viewData);
        }

        if ($embed) {
            return property_view('property.agent.tenants.utility_statement_embed', $viewData);
        }

        return property_view('property.agent.tenants.utility_statement', $viewData);
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function streamStatementPdf(array $viewData): StreamedResponse|Response
    {
        $html = view('property.agent.tenants.utility_statement_print', $viewData)->render();
        $tenant = $viewData['tenant'];
        $filename = 'utility-statement-'.$tenant->id.'-'.now()->format('Ymd').'.pdf';

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        } catch (\Throwable) {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.str_replace('.pdf', '.html', $filename).'"',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function exportAging(array $rows, string $format): StreamedResponse
    {
        return TabularExport::stream(
            'utility-aging-'.now()->format('Ymd_His'),
            ['Invoice', 'Tenant', 'Property / Unit', 'Period', 'Due', 'Days', 'Bucket', 'Balance'],
            function () use ($rows) {
                foreach ($rows as $row) {
                    yield [
                        $row['invoice_no'],
                        $row['tenant'],
                        trim($row['property'].' / '.$row['unit'], ' /'),
                        $row['period'],
                        $row['due_date'],
                        (string) $row['days_overdue'],
                        $row['bucket'],
                        $row['balance_display'],
                    ];
                }
            },
            $format === 'pdf' ? TabularExport::FORMAT_PDF : TabularExport::FORMAT_CSV,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function branding(): array
    {
        $b = PropertyPortalSetting::query()->where('key', 'branding')->value('value');
        $decoded = is_string($b) ? json_decode($b, true) : (is_array($b) ? $b : []);

        return array_merge([
            'company_name' => PropertyPortalSetting::getValue('company_name', 'Property Manager'),
            'address' => '',
            'phone' => '',
            'email' => '',
            'colour' => '#0f766e',
        ], is_array($decoded) ? $decoded : []);
    }
}
