<?php

namespace App\View\Components\Loan;

use App\Support\LoanWorkspaceTabs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class WorkspaceTabs extends Component
{
    public string $workspaceKey;

    /** @var list<array<string, mixed>> */
    public array $tabs;

    public string $workspaceLabel;

    public string $mode;

    public ?string $activeKey;

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $tabs
     */
    public function __construct(
        ?string $workspace = null,
        string $mode = 'route',
        array $tabs = [],
        ?string $activeKey = null,
    ) {
        $routeName = Route::currentRouteName();
        $this->workspaceKey = trim((string) ($workspace ?? LoanWorkspaceTabs::resolveWorkspaceKey($routeName) ?? ''));
        $this->mode = $mode;
        $this->activeKey = $activeKey;

        if ($mode === 'panel') {
            $this->tabs = $tabs['all'] ?? $tabs;
        } else {
            $this->tabs = $this->workspaceKey !== ''
                ? LoanWorkspaceTabs::navigableTabsFor($this->workspaceKey)
                : [];
        }

        $this->workspaceLabel = $this->workspaceKey !== ''
            ? LoanWorkspaceTabs::workspaceLabel($this->workspaceKey)
            : '';
    }

    public function tabIsActive(array $tab): bool
    {
        if ($this->mode === 'panel') {
            return ($tab['key'] ?? '') === $this->activeKey;
        }

        return LoanWorkspaceTabs::tabIsActive($tab);
    }

    public function render(): View
    {
        return view('components.loan.workspace-tabs');
    }
}
