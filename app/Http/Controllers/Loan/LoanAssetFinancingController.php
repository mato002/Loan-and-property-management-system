<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanAssetCategory;
use App\Models\LoanAssetMeasurementUnit;
use App\Models\LoanAssetStockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanAssetFinancingController extends Controller
{
    public function unitsIndex(): View
    {
        $units = LoanAssetMeasurementUnit::query()->orderBy('name')->paginate(20);

        return view('loan.assets.units.index', [
            'title' => 'Measurement units',
            'subtitle' => 'Define units used for asset and stock quantities.',
            'units' => $units,
        ]);
    }

    public function unitsCreate(): View
    {
        return view('loan.assets.units.create', [
            'title' => 'Add measurement unit',
            'subtitle' => 'Name and abbreviation must be unique.',
        ]);
    }

    public function unitsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abbreviation' => ['required', 'string', 'max:20', 'unique:loan_asset_measurement_units,abbreviation'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        LoanAssetMeasurementUnit::query()->create($data);

        return redirect()->route('loan.assets.units.index')->with('status', 'Measurement unit saved.');
    }

    public function unitsEdit(LoanAssetMeasurementUnit $loan_asset_measurement_unit): View
    {
        return view('loan.assets.units.edit', [
            'title' => 'Edit measurement unit',
            'subtitle' => $loan_asset_measurement_unit->name,
            'unit' => $loan_asset_measurement_unit,
        ]);
    }

    public function unitsUpdate(Request $request, LoanAssetMeasurementUnit $loan_asset_measurement_unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abbreviation' => ['required', 'string', 'max:20', 'unique:loan_asset_measurement_units,abbreviation,'.$loan_asset_measurement_unit->id],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $loan_asset_measurement_unit->update($data);

        return redirect()->route('loan.assets.units.index')->with('status', 'Measurement unit updated.');
    }

    public function unitsDestroy(LoanAssetMeasurementUnit $loan_asset_measurement_unit): RedirectResponse
    {
        if ($loan_asset_measurement_unit->stockItems()->exists()) {
            return redirect()->route('loan.assets.units.index')->with('error', 'Cannot delete a unit that is used by stock items.');
        }

        $loan_asset_measurement_unit->delete();

        return redirect()->route('loan.assets.units.index')->with('status', 'Measurement unit removed.');
    }

    public function categoriesIndex(): View
    {
        $categories = LoanAssetCategory::query()->orderBy('name')->paginate(20);

        return view('loan.assets.categories.index', [
            'title' => 'Asset categories',
            'subtitle' => 'Group assets for reporting and filtering.',
            'categories' => $categories,
        ]);
    }

    public function categoriesCreate(): View
    {
        return view('loan.assets.categories.create', [
            'title' => 'Add asset category',
            'subtitle' => 'Create a category for assets and stock.',
        ]);
    }

    public function categoriesStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        LoanAssetCategory::query()->create($data);

        return redirect()->route('loan.assets.categories.index')->with('status', 'Category saved.');
    }

    public function categoriesEdit(LoanAssetCategory $loan_asset_category): View
    {
        return view('loan.assets.categories.edit', [
            'title' => 'Edit asset category',
            'subtitle' => $loan_asset_category->name,
            'category' => $loan_asset_category,
        ]);
    }

    public function categoriesUpdate(Request $request, LoanAssetCategory $loan_asset_category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $loan_asset_category->update($data);

        return redirect()->route('loan.assets.categories.index')->with('status', 'Category updated.');
    }

    public function categoriesDestroy(LoanAssetCategory $loan_asset_category): RedirectResponse
    {
        if ($loan_asset_category->stockItems()->exists()) {
            return redirect()->route('loan.assets.categories.index')->with('error', 'Cannot delete a category that has stock items.');
        }

        $loan_asset_category->delete();

        return redirect()->route('loan.assets.categories.index')->with('status', 'Category removed.');
    }

    public function itemsIndex(): View
    {
        $items = LoanAssetStockItem::query()
            ->with(['category', 'measurementUnit'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('loan.assets.items.index', [
            'title' => 'Asset list / stock',
            'subtitle' => 'Inventory and asset register.',
            'items' => $items,
        ]);
    }

    public function itemsCreate(): View
    {
        return view('loan.assets.items.create', [
            'title' => 'Add asset / stock',
            'subtitle' => 'Record a new line in the register.',
            'categories' => LoanAssetCategory::query()->orderBy('name')->get(),
            'units' => LoanAssetMeasurementUnit::query()->orderBy('name')->get(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function itemsStore(Request $request): RedirectResponse
    {
        $data = $this->validatedItem($request);

        LoanAssetStockItem::query()->create($data);

        return redirect()->route('loan.assets.items.index')->with('status', 'Asset / stock saved.');
    }

    public function itemsEdit(LoanAssetStockItem $loan_asset_stock_item): View
    {
        return view('loan.assets.items.edit', [
            'title' => 'Edit asset / stock',
            'subtitle' => $loan_asset_stock_item->asset_code,
            'item' => $loan_asset_stock_item,
            'categories' => LoanAssetCategory::query()->orderBy('name')->get(),
            'units' => LoanAssetMeasurementUnit::query()->orderBy('name')->get(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function itemsUpdate(Request $request, LoanAssetStockItem $loan_asset_stock_item): RedirectResponse
    {
        $data = $this->validatedItem($request, $loan_asset_stock_item->id);

        $loan_asset_stock_item->update($data);

        return redirect()->route('loan.assets.items.index')->with('status', 'Asset / stock updated.');
    }

    public function itemsDestroy(LoanAssetStockItem $loan_asset_stock_item): RedirectResponse
    {
        $loan_asset_stock_item->delete();

        return redirect()->route('loan.assets.items.index')->with('status', 'Asset / stock removed.');
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            LoanAssetStockItem::STATUS_IN_STOCK => 'In stock',
            LoanAssetStockItem::STATUS_ASSIGNED => 'Assigned',
            LoanAssetStockItem::STATUS_DISPOSED => 'Disposed',
        ];
    }

    private function validatedItem(Request $request, ?int $ignoreItemId = null): array
    {
        $assetCodeRule = 'required|string|max:60|unique:loan_asset_stock_items,asset_code';
        if ($ignoreItemId !== null) {
            $assetCodeRule .= ','.$ignoreItemId;
        }

        return $request->validate([
            'loan_asset_category_id' => ['required', 'exists:loan_asset_categories,id'],
            'loan_asset_measurement_unit_id' => ['required', 'exists:loan_asset_measurement_units,id'],
            'asset_code' => $assetCodeRule,
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:160'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:'.implode(',', [
                LoanAssetStockItem::STATUS_IN_STOCK,
                LoanAssetStockItem::STATUS_ASSIGNED,
                LoanAssetStockItem::STATUS_DISPOSED,
            ])],
            'acquisition_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
