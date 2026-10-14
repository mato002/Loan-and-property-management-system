<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdmin\OpsLandlordScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminOpsConsoleController extends Controller
{
    public function index(Request $request, OpsLandlordScopeService $landlordScope): View
    {
        $agentId = (int) $request->query('agent_id', 0);
        $inspectLandlordId = (int) $request->query('inspect_landlord_id', 0);
        $tab = (string) $request->query('tab', 'landlord-scope');
        if (! in_array($tab, ['landlord-scope', 'commands', 'maintenance'], true)) {
            $tab = 'landlord-scope';
        }

        $agents = $landlordScope->listAgents();
        $agentLandlords = $agentId > 0 ? $landlordScope->listLandlordsForAgent($agentId) : collect();
        $orphans = $landlordScope->listOrphanLandlords();

        $inspectLandlord = null;
        $inspectReasons = [];
        if ($inspectLandlordId > 0) {
            $inspectLandlord = User::query()
                ->where('id', $inspectLandlordId)
                ->where('property_portal_role', 'landlord')
                ->first();
            if ($inspectLandlord) {
                $inspectReasons = $landlordScope->inspectLandlord($inspectLandlordId);
            }
        }

        $commandReferenceHtml = $tab === 'commands' ? $this->loadCommandReferenceHtml() : '';
        $maintenanceCommandGroups = $tab === 'maintenance' ? $this->loadMaintenanceCommandGroups() : [];

        return view('superadmin.ops.index', [
            'tab' => $tab,
            'agents' => $agents,
            'selectedAgentId' => $agentId,
            'selectedAgent' => $agentId > 0 ? $agents->firstWhere('id', $agentId) : null,
            'agentLandlords' => $agentLandlords,
            'orphans' => $orphans,
            'inspectLandlord' => $inspectLandlord,
            'inspectReasons' => $inspectReasons,
            'commandReferenceHtml' => $commandReferenceHtml,
            'maintenanceCommandGroups' => $maintenanceCommandGroups,
            'landlordScope' => $landlordScope,
        ]);
    }

    public function assignLandlord(Request $request, OpsLandlordScopeService $landlordScope): RedirectResponse
    {
        $data = $request->validate([
            'landlord_id' => ['required', 'integer', 'exists:users,id'],
            'agent_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $landlord = $landlordScope->assignLandlordToAgent((int) $data['landlord_id'], (int) $data['agent_id']);
        $agent = User::query()->findOrFail((int) $data['agent_id']);

        return redirect()
            ->route('superadmin.ops.index', [
                'tab' => 'landlord-scope',
                'agent_id' => (int) $data['agent_id'],
                'inspect_landlord_id' => (int) $landlord->id,
            ])
            ->with('success', 'Assigned landlord #'.$landlord->id.' ('.$landlord->name.') to agent #'.$agent->id.' ('.$agent->name.').');
    }

    public function releaseLandlord(Request $request, OpsLandlordScopeService $landlordScope): RedirectResponse
    {
        $data = $request->validate([
            'landlord_id' => ['required', 'integer', 'exists:users,id'],
            'from_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'keep_property_links' => ['nullable', 'boolean'],
        ]);

        $fromAgentId = isset($data['from_agent_id']) ? (int) $data['from_agent_id'] : null;
        if ($fromAgentId !== null && $fromAgentId <= 0) {
            $fromAgentId = null;
        }

        $landlord = $landlordScope->releaseLandlord(
            (int) $data['landlord_id'],
            $fromAgentId,
            ! (bool) ($data['keep_property_links'] ?? false),
        );

        $message = $fromAgentId
            ? 'Removed agent #'.$fromAgentId.' visibility for landlord #'.$landlord->id.' ('.$landlord->name.').'
            : 'Landlord #'.$landlord->id.' ('.$landlord->name.') is now super-admin only.';

        return redirect()
            ->route('superadmin.ops.index', [
                'tab' => 'landlord-scope',
                'inspect_landlord_id' => (int) $landlord->id,
            ])
            ->with('success', $message);
    }

    private function loadCommandReferenceHtml(): string
    {
        $path = base_path('docs/OPS-COMMAND-REFERENCE.md');
        if (! is_file($path)) {
            return '<p class="text-sm text-slate-600">Command reference file not found on server.</p>';
        }

        $markdown = (string) file_get_contents($path);

        return $this->markdownToBasicHtml($markdown);
    }

    /** @return list<array{name: string, commands: list<array{command: string, purpose: string}>}> */
    private function loadMaintenanceCommandGroups(): array
    {
        $path = base_path('docs/OPS-COMMAND-REFERENCE.md');
        if (! is_file($path)) {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) file_get_contents($path)) ?: [];
        $allowedSections = [
            'Property billing & utilities',
            'Finance integrity & reconciliation',
            'Tenants, leases, payments (property)',
            'SMS & communications',
            'Loan module',
            'Infrastructure & ops',
        ];
        $groups = [];
        $section = '';
        $quickIndex = false;
        $insideQuickIndexCode = false;
        $seen = [];
        $extraPurposes = [
            'property:backfill-landlord-agent-scope' => 'Repair landlord visibility links between agents and properties.',
            'mpesa:oauth-test' => 'Check M-Pesa API credentials and OAuth access.',
            'mpesa:stk-test' => 'Send a test M-Pesa STK push.',
        ];

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/', trim($line), $match) === 1) {
                $section = trim($match[1]);
                $quickIndex = $section === 'Quick index (all custom commands)';
                $insideQuickIndexCode = false;

                continue;
            }

            if ($quickIndex && str_starts_with(trim($line), '```')) {
                $insideQuickIndexCode = ! $insideQuickIndexCode;

                continue;
            }

            if ($quickIndex && $insideQuickIndexCode) {
                $name = trim($line);
                if ($name !== '' && preg_match('/^[a-z][a-z0-9:-]+$/i', $name) === 1 && ! isset($seen[$name])) {
                    $groups['Other system commands']['commands'][] = [
                        'command' => 'php artisan '.$name,
                        'purpose' => $extraPurposes[$name] ?? 'Additional maintenance command.',
                    ];
                    $seen[$name] = true;
                }

                continue;
            }

            if (! in_array($section, $allowedSections, true) || ! str_starts_with(trim($line), '|') || str_contains($line, '---')) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim(trim($line), '|')));
            if (count($cells) < 2 || stripos($cells[0], 'php artisan') === false) {
                continue;
            }

            $command = trim(str_replace('`', '', $cells[0]));
            $purpose = trim(str_replace('`', '', $cells[1]));
            if (preg_match('/^php artisan\s+([a-z0-9:-]+)/i', $command, $match) !== 1) {
                continue;
            }

            $name = strtolower($match[1]);
            if ($name === 'payments:repair-allocations') {
                $command = 'php artisan payments:repair-allocations --all';
                $purpose = 'Rebuild completed payment allocations for every tenant oldest-due-first; may move existing allocations between invoices.';
            } elseif ($name === 'finance:detect-allocation-drift') {
                $command = 'php artisan finance:detect-allocation-drift';
                $purpose = 'Read-only check for invoices whose paid totals differ from active allocations.';
            }
            $groups[$section]['commands'][] = ['command' => $command, 'purpose' => $purpose];
            if ($name === 'finance:detect-allocation-drift') {
                $groups[$section]['commands'][] = [
                    'command' => 'php artisan finance:detect-allocation-drift --fix',
                    'purpose' => 'Sync invoice paid totals and status from active allocations; does not move receipts.',
                ];
            }
            $seen[$name] = true;
        }

        // Useful framework operations that are not project-specific commands.
        $groups['Laravel operations']['commands'] = [
            ['command' => 'php artisan about', 'purpose' => 'Show application, PHP, environment, and framework configuration summary.'],
            ['command' => 'php artisan migrate:status', 'purpose' => 'Check which database migrations have run.'],
            ['command' => 'php artisan route:list', 'purpose' => 'Inspect registered routes when a page or endpoint is unavailable.'],
            ['command' => 'php artisan queue:failed', 'purpose' => 'List failed queued jobs.'],
            ['command' => 'php artisan schedule:list', 'purpose' => 'List scheduled tasks.'],
            ['command' => 'php artisan optimize:clear', 'purpose' => 'Clear cached config, routes, views, and application data.'],
        ];

        return collect($groups)
            ->map(fn (array $group, string $name) => ['name' => $name, 'commands' => $group['commands'] ?? []])
            ->filter(fn (array $group) => $group['commands'] !== [])
            ->values()
            ->all();
    }

    private function markdownToBasicHtml(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $html = [];
        $inCode = false;
        $inTable = false;
        $listOpen = false;

        $closeList = static function () use (&$html, &$listOpen): void {
            if ($listOpen) {
                $html[] = '</ul>';
                $listOpen = false;
            }
        };

        foreach ($lines as $line) {
            if (str_starts_with($line, '```')) {
                $closeList();
                if ($inTable) {
                    $html[] = '</tbody></table></div>';
                    $inTable = false;
                }
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;
                } else {
                    $html[] = '<pre class="my-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-900 p-4 text-xs text-emerald-100"><code>';
                    $inCode = true;
                }

                continue;
            }

            if ($inCode) {
                $html[] = e($line);

                continue;
            }

            if (preg_match('/^\|(.+)\|$/', $line) === 1) {
                $closeList();
                if (str_contains($line, '---')) {
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($line, '|')));
                if (! $inTable) {
                    $html[] = '<div class="overflow-x-auto my-3"><table class="min-w-full text-sm border border-slate-200 rounded-xl overflow-hidden"><thead class="bg-slate-100"><tr>';
                    foreach ($cells as $cell) {
                        $html[] = '<th class="px-3 py-2 text-left font-bold text-slate-700 border-b border-slate-200">'.e($cell).'</th>';
                    }
                    $html[] = '</tr></thead><tbody>';
                    $inTable = true;
                } else {
                    $html[] = '<tr class="border-b border-slate-100">';
                    foreach ($cells as $cell) {
                        $html[] = '<td class="px-3 py-2 text-slate-700 align-top">'.e($cell).'</td>';
                    }
                    $html[] = '</tr>';
                }

                continue;
            }

            if ($inTable) {
                $html[] = '</tbody></table></div>';
                $inTable = false;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m) === 1) {
                $closeList();
                $level = strlen($m[1]);
                $class = match ($level) {
                    1 => 'text-2xl font-black text-slate-900 mt-8 mb-3',
                    2 => 'text-lg font-black text-slate-900 mt-6 mb-2',
                    default => 'text-base font-bold text-slate-800 mt-4 mb-2',
                };
                $html[] = '<h'.$level.' class="'.$class.'">'.e($m[2]).'</h'.$level.'>';

                continue;
            }

            if (preg_match('/^-\s+(.+)$/', $line, $m) === 1) {
                if (! $listOpen) {
                    $html[] = '<ul class="list-disc list-inside space-y-1 text-sm text-slate-700 my-2">';
                    $listOpen = true;
                }
                $html[] = '<li>'.e($m[1]).'</li>';

                continue;
            }

            if (trim($line) === '---') {
                $closeList();
                $html[] = '<hr class="my-6 border-slate-200">';

                continue;
            }

            if (trim($line) === '') {
                $closeList();

                continue;
            }

            $closeList();
            $html[] = '<p class="text-sm text-slate-700 my-2">'.e($line).'</p>';
        }

        if ($inCode) {
            $html[] = '</code></pre>';
        }
        if ($inTable) {
            $html[] = '</tbody></table></div>';
        }
        $closeList();

        return implode("\n", $html);
    }
}
