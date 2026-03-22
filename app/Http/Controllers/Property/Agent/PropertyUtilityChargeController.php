<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmUnitUtilityCharge;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyUtilityChargeController extends Controller
{
    public function index(): View
    {
        $charges = PmUnitUtilityCharge::query()
            ->with(['unit.property'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $mtd = PmUnitUtilityCharge::query()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $stats = [
            ['label' => 'Charge lines', 'value' => (string) $charges->count(), 'hint' => 'Listed'],
            ['label' => 'New (MTD)', 'value' => PropertyMoney::kes((float) $mtd), 'hint' => 'Sum of amounts'],
            ['label' => 'Distinct units', 'value' => (string) $charges->unique('property_unit_id')->count(), 'hint' => ''],
        ];

        return view('property.agent.revenue.utilities', [
            'stats' => $stats,
            'charges' => $charges,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'label' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        PmUnitUtilityCharge::query()->create($data);

        return back()->with('success', __('Utility charge saved.'));
    }

    public function destroy(PmUnitUtilityCharge $charge): RedirectResponse
    {
        $charge->delete();

        return back()->with('success', __('Charge removed.'));
    }
}
