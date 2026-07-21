<?php

namespace App\Services\Property;

use App\Http\Controllers\Property\Landlord\LandlordPortalController;
use App\Http\Controllers\Property\Landlord\LandlordPortalWorkspaceController;
use Illuminate\Http\Request;

final class LandlordReportsHubService
{
    public const DEFAULT_PANEL = 'owner_statement';

    /**
     * @return array<string, array{title: string, description?: string|null}>
     */
    public static function panels(): array
    {
        return [
            'owner_statement' => ['title' => 'Owner statement'],
            'leases' => ['title' => 'Lease register'],
            'rent_roll' => ['title' => 'Rent roll'],
            'arrears' => ['title' => 'Arrears aging'],
            'income' => ['title' => 'Income'],
            'expenses' => ['title' => 'Expenses'],
            'cash_flow' => ['title' => 'Cash flow'],
            'statement' => ['title' => 'Ledger detail'],
            'documents' => ['title' => 'Documents'],
        ];
    }

    /**
     * @return array<string, array{label: string, default: string, panels: list<string>}>
     */
    public static function panelGroups(): array
    {
        return [
            'financial' => [
                'label' => 'Financial',
                'default' => 'owner_statement',
                'panels' => ['owner_statement', 'income', 'expenses', 'cash_flow', 'statement'],
            ],
            'tenancy' => [
                'label' => 'Tenancy',
                'default' => 'leases',
                'panels' => ['leases', 'rent_roll', 'arrears'],
            ],
            'documents' => [
                'label' => 'Documents',
                'default' => 'documents',
                'panels' => ['documents'],
            ],
        ];
    }

    public static function groupForPanel(string $panel): string
    {
        foreach (self::panelGroups() as $groupKey => $group) {
            if (in_array($panel, $group['panels'], true)) {
                return $groupKey;
            }
        }

        return 'financial';
    }

    /**
     * @return list<string>
     */
    public static function panelsInGroup(string $groupKey): array
    {
        return self::panelGroups()[$groupKey]['panels'] ?? [];
    }

    public static function resolvePanel(?string $panel): string
    {
        $panel = trim((string) $panel);

        return array_key_exists($panel, self::panels()) ? $panel : self::DEFAULT_PANEL;
    }

    /**
     * @return array<string, mixed>
     */
    public function dataFor(string $panel, Request $request): array
    {
        $view = match ($panel) {
            'owner_statement' => app(LandlordPortalWorkspaceController::class)->ownerStatement($request, true),
            'leases' => app(LandlordPortalWorkspaceController::class)->leases($request, true),
            'rent_roll' => app(LandlordPortalWorkspaceController::class)->rentRoll($request, true),
            'arrears' => app(LandlordPortalWorkspaceController::class)->arrears($request, true),
            'income' => app(LandlordPortalController::class)->reportIncome($request, true),
            'expenses' => app(LandlordPortalController::class)->reportExpenses($request, true),
            'cash_flow' => app(LandlordPortalController::class)->reportCashFlow($request, true),
            'statement' => app(LandlordPortalController::class)->statement($request, true),
            'documents' => app(LandlordPortalController::class)->documents($request, true),
            default => app(LandlordPortalWorkspaceController::class)->ownerStatement($request, true),
        };

        return $view->getData();
    }
}
