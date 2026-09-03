<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyActivityLogQueryService;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyActivityLogController extends Controller
{
    public function index(Request $request, PropertyActivityLogQueryService $queryService): View|StreamedResponse
    {
        $user = $request->user();
        if (! $user
            || (! $user->is_super_admin
                && ! $user->hasPmPermission('settings.manage')
                && ! $user->hasPmPermission('settings.access.manage'))) {
            abort(403);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'source' => strtolower(trim((string) $request->query('source', ''))),
            'user_id' => (int) $request->query('user_id', 0),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => min(100, max(10, (int) $request->query('per_page', 30))),
        ];

        if ($filters['from'] === '' || $filters['to'] === '') {
            $filters['from'] = now()->subDays(30)->toDateString();
            $filters['to'] = now()->toDateString();
        }

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf', 'word'], true)) {
            $items = $queryService->collectForExport($filters);

            return TabularExport::stream(
                'activity-log-'.now()->format('Ymd_His'),
                ['When', 'Source', 'User', 'Action', 'Summary', 'Details', 'Entity'],
                function () use ($items) {
                    foreach ($items as $row) {
                        yield [
                            (string) ($row['occurred_at_label'] ?? ''),
                            (string) ($row['source_label'] ?? ''),
                            (string) ($row['actor_name'] ?? ''),
                            (string) ($row['action'] ?? ''),
                            (string) ($row['summary'] ?? ''),
                            (string) ($row['detail_preview'] ?? ''),
                            trim((string) (($row['entity_type'] ?? '').($row['entity_id'] ? ' #'.$row['entity_id'] : ''))),
                        ];
                    }
                },
                $export
            );
        }

        $paginator = $queryService->paginate($filters);
        $items = $paginator->getCollection();

        $rows = $items->map(function (array $row) {
            $sourceBadge = '<span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-700">'
                .e((string) ($row['source_label'] ?? 'Activity')).'</span>';

            $summaryCell = e((string) ($row['summary'] ?? ''));
            if (! empty($row['detail_preview'])) {
                $summaryCell = '<div><p class="text-slate-800">'.e((string) $row['summary']).'</p>'
                    .'<p class="mt-0.5 text-[11px] text-slate-500">'.e((string) $row['detail_preview']).'</p></div>';
            }

            $entityCell = '—';
            if (! empty($row['url'])) {
                $entityCell = '<a href="'.e((string) $row['url']).'" class="text-blue-700 hover:underline">Open record</a>';
            } elseif (! empty($row['entity_type']) && ! empty($row['entity_id'])) {
                $entityCell = e((string) $row['entity_type']).' #'.(int) $row['entity_id'];
            }

            return [
                (string) ($row['occurred_at_label'] ?? '—'),
                new HtmlString($sourceBadge),
                e((string) ($row['actor_name'] ?? 'System')),
                e(str_replace('_', ' ', (string) ($row['action'] ?? ''))),
                new HtmlString($summaryCell),
                new HtmlString($entityCell),
            ];
        })->all();

        $rangeLabel = Carbon::parse($filters['from'])->format('Y-m-d').' → '.Carbon::parse($filters['to'])->format('Y-m-d');

        return property_view('property.agent.settings.activity_log', [
            'stats' => [
                ['label' => 'Events in view', 'value' => (string) $paginator->total(), 'hint' => $rangeLabel],
                ['label' => 'Sources merged', 'value' => (string) count(PropertyActivityLogQueryService::SOURCE_LABELS), 'hint' => 'Portal, finance, invoices, login, etc.'],
                ['label' => 'Date range', 'value' => $rangeLabel, 'hint' => 'Adjust filters below'],
            ],
            'columns' => ['When', 'Source', 'User', 'Action', 'Summary', 'Record'],
            'tableRows' => $rows,
            'paginator' => $paginator,
            'filters' => $filters,
            'sourceOptions' => PropertyActivityLogQueryService::SOURCE_LABELS,
            'actorOptions' => $queryService->actorOptions(),
        ]);
    }
}
