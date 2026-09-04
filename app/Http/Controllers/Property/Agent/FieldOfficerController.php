<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmFieldOfficer;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class FieldOfficerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $officers = PmFieldOfficer::query()
            ->orderBy('name')
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            ->get();

        $tableRows = [];
        $tableRowFilters = [];
        $totalProperties = 0;
        $totalUnits = 0;
        $totalTenants = 0;
        $totalRent = 0.0;

        foreach ($officers as $officer) {
            $stats = $officer->portfolioStats();
            $totalProperties += (int) $stats['properties'];
            $totalUnits += (int) $stats['units'];
            $totalTenants += (int) $stats['tenants'];
            $totalRent += (float) $stats['rent_portfolio'];

            $tableRows[] = [
                $officer->name,
                $officer->phone ?: '—',
                (string) $stats['landlords'],
                (string) $stats['properties'],
                (string) $stats['units'],
                (string) $stats['tenants'],
                PropertyMoney::kes((float) $stats['rent_portfolio']),
                new HtmlString(
                    $officer->portal_access
                        ? '<span class="property-status-pill property-status-pill--occupied">Yes</span>'
                        : '<span class="property-status-pill property-status-pill--vacant">No</span>'
                ),
            ];
            $tableRowFilters[] = mb_strtolower($officer->name.' '.$officer->phone);
        }

        return property_view('property.agent.field_officers.index', [
            'filters' => ['q' => $search],
            'stats' => [
                ['label' => 'Field officers', 'value' => (string) $officers->count(), 'hint' => 'Matching filters'],
                ['label' => 'Properties', 'value' => (string) $totalProperties, 'hint' => 'Assigned portfolio'],
                ['label' => 'Units', 'value' => (string) $totalUnits, 'hint' => 'Assigned portfolio'],
                ['label' => 'Active tenants', 'value' => (string) $totalTenants, 'hint' => 'On active leases'],
                ['label' => 'Rent portfolio', 'value' => PropertyMoney::kes($totalRent), 'hint' => 'Active lease rent'],
            ],
            'columns' => [
                'Name',
                'Phone #',
                'Landlord portfolio',
                'Property portfolio',
                'Units portfolio',
                'Tenants portfolio',
                'Rent portfolio',
                'Portal access',
            ],
            'tableRows' => $tableRows,
            'tableRowFilters' => $tableRowFilters,
            'columnConfig' => [],
        ]);
    }
}
