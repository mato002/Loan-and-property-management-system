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
        if (! in_array($tab, ['landlord-scope', 'commands'], true)) {
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

        $commandReferenceHtml = $this->loadCommandReferenceHtml();

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
