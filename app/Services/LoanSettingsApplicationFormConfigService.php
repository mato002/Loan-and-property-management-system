<?php

namespace App\Services;

use App\Models\LoanFormFieldDefinition;
use App\Models\LoanProduct;
use App\Models\LoanProductFormFieldOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanSettingsApplicationFormConfigService
{
    public const FORM_KIND = LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION;

    /**
     * @return list<array<string, mixed>>
     */
    public function buildEditorPayload(?int $editorProductId): array
    {
        LoanFormFieldDefinition::ensureDefaults(self::FORM_KIND);

        $masters = LoanFormFieldDefinition::query()
            ->where('form_kind', self::FORM_KIND)
            ->whereNull('product_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $overrides = collect();
        if ($editorProductId !== null) {
            $overrides = LoanProductFormFieldOverride::query()
                ->where('product_id', $editorProductId)
                ->where('form_kind', self::FORM_KIND)
                ->get()
                ->keyBy('field_key');
        }

        $rows = [];
        foreach ($masters as $m) {
            $o = $overrides->get($m->field_key);
            $isGlobal = $editorProductId === null;

            if ($isGlobal) {
                $included = (bool) $m->is_core || ($m->field_status ?? 'active') === 'active';
                $fieldStatus = (bool) $m->is_core ? 'active' : (string) ($m->field_status ?? 'draft');
                if ((bool) $m->is_core) {
                    $fieldStatus = 'active';
                }
                $isRequired = (bool) ($m->is_required ?? false);
                $prefill = (bool) $m->prefill_from_previous;
                $visibleTo = (string) ($m->visible_to ?? '');
                $sortOrder = (int) $m->sort_order;
            } else {
                $included = (bool) $m->is_core || (bool) ($o?->is_included ?? false);
                $fieldStatus = ! $included ? 'draft' : (($o?->display_status ?? 'active') === 'requires_approval' ? 'requires_approval' : 'active');
                $isRequired = (bool) ($o?->is_required ?? ($m->is_required ?? false));
                $prefill = (bool) ($o?->prefill_from_previous ?? $m->prefill_from_previous);
                $visibleTo = (string) ($o?->visible_to ?? ($m->visible_to ?? ''));
                $sortOrder = $o?->sort_order !== null ? (int) $o->sort_order : (int) $m->sort_order;
            }

            $detailStatus = $fieldStatus === 'requires_approval' ? 'requires_approval' : 'active';

            $rows[] = [
                'master_id' => (int) $m->id,
                'override_id' => $o?->id !== null ? (int) $o->id : null,
                'field_key' => (string) $m->field_key,
                'label' => (string) $m->label,
                'data_type' => (string) $m->data_type,
                'select_options' => (string) ($m->select_options ?? ''),
                'is_core' => (bool) $m->is_core,
                'included' => $included,
                'field_status' => $fieldStatus,
                'detail_status' => $detailStatus,
                'is_required' => $isRequired,
                'prefill_from_previous' => $prefill,
                'visible_to' => $visibleTo,
                'sort_order' => $sortOrder,
                'editor_scope' => $isGlobal ? 'global' : 'product',
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']) ?: ($a['master_id'] <=> $b['master_id']));

        return $rows;
    }

    /**
     * Effective field rows for LoanBook application forms (consumer, not editor).
     * If the product has no rows in {@see LoanProductFormFieldOverride}, uses global
     * defaults so behaviour matches installations that never configured per-product rules.
     *
     * @return list<array<string, mixed>> Same shape as {@see self::buildEditorPayload()}.
     */
    public function effectiveApplicationFieldsForConsumer(?int $productId): array
    {
        if ($productId === null || ! $this->productHasApplicationFieldOverrides($productId)) {
            return $this->buildEditorPayload(null);
        }

        return $this->buildEditorPayload($productId);
    }

    /**
     * @return list<array{field_key:string,label:string,data_type:string,is_included:bool,is_required:bool,is_core:bool}>
     */
    public function simplifiedConsumerFieldRows(?int $productId): array
    {
        return array_values(array_map(
            static fn (array $row): array => [
                'field_key' => (string) $row['field_key'],
                'label' => (string) $row['label'],
                'data_type' => (string) $row['data_type'],
                'is_included' => (bool) ($row['included'] ?? false),
                'is_required' => (bool) ($row['is_required'] ?? false),
                'is_core' => (bool) ($row['is_core'] ?? false),
            ],
            $this->effectiveApplicationFieldsForConsumer($productId)
        ));
    }

    private function productHasApplicationFieldOverrides(int $productId): bool
    {
        if (! Schema::hasTable('loan_product_form_field_overrides')) {
            return false;
        }

        return LoanProductFormFieldOverride::query()
            ->where('form_kind', self::FORM_KIND)
            ->where('product_id', $productId)
            ->exists();
    }

    public function saveLoanFormSetup(Request $request): RedirectResponse
    {
        $scope = (string) $request->input('loan_form_setup_editor', 'global');
        if ($scope === 'product') {
            $productId = (int) $request->input('form_product', 0);
            if ($productId <= 0 || ! LoanProduct::query()->whereKey($productId)->exists()) {
                throw ValidationException::withMessages([
                    'form_product' => 'Select a valid loan product to save this configuration.',
                ]);
            }

            return $this->saveProductScoped($request, $productId);
        }

        return $this->saveGlobalMasters($request);
    }

    private function saveGlobalMasters(Request $request): RedirectResponse
    {
        $types = array_keys(LoanFormFieldDefinition::dataTypeLabels());

        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.master_id' => ['nullable', 'integer', 'min:0'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.data_type' => ['required', 'string', Rule::in($types)],
            'fields.*.is_required' => ['nullable', 'in:0,1'],
            'fields.*.select_options' => ['nullable', 'string', 'max:10000'],
            'fields.*.prefill_from_previous' => ['nullable', 'in:0,1'],
            'fields.*.visible_to' => ['nullable', 'string', 'max:255'],
            'fields.*.field_status' => ['nullable', Rule::in(['active', 'draft', 'requires_approval'])],
            'fields.*.included' => ['nullable', 'in:0,1'],
            'complete_loan_form_setup_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
        ]);

        foreach ($validated['fields'] as $i => $row) {
            if ($row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
                && trim((string) ($row['select_options'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "fields.$i.select_options" => 'Add comma-separated options for a dropdown field.',
                ]);
            }
        }

        $this->assertPendingLoanFormProductIfRequested($request);

        DB::transaction(function () use ($validated): void {
            $rows = $validated['fields'];
            $submittedMasterIds = collect($rows)
                ->pluck('master_id')
                ->filter(fn ($v) => (int) $v > 0)
                ->map(fn ($v) => (int) $v)
                ->all();

            LoanFormFieldDefinition::query()
                ->where('form_kind', self::FORM_KIND)
                ->whereNull('product_id')
                ->where('is_core', false)
                ->whereNotIn('id', $submittedMasterIds)
                ->delete();

            foreach ($rows as $index => $row) {
                $selectOptions = $row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
                    ? ($row['select_options'] ?? null)
                    : null;

                $prefill = isset($row['prefill_from_previous']) && (string) $row['prefill_from_previous'] === '1';
                $required = isset($row['is_required']) && (string) $row['is_required'] === '1';
                $masterId = (int) ($row['master_id'] ?? 0);

                $included = isset($row['included']) && (string) $row['included'] === '1';
                $fieldStatus = trim((string) ($row['field_status'] ?? 'active'));

                if ($masterId > 0) {
                    $field = LoanFormFieldDefinition::query()
                        ->where('form_kind', self::FORM_KIND)
                        ->whereNull('product_id')
                        ->whereKey($masterId)
                        ->firstOrFail();

                    if ($field->is_core) {
                        $fieldStatus = 'active';
                        $included = true;
                    } elseif (! $included) {
                        $fieldStatus = 'draft';
                    } elseif ($fieldStatus === 'draft') {
                        $fieldStatus = 'active';
                    }

                    $field->update([
                        'label' => $row['label'],
                        'data_type' => $row['data_type'],
                        'is_required' => $required,
                        'select_options' => $selectOptions,
                        'prefill_from_previous' => $field->is_core ? false : $prefill,
                        'visible_to' => trim((string) ($row['visible_to'] ?? '')),
                        'field_status' => $fieldStatus,
                        'sort_order' => $index,
                    ]);

                    continue;
                }

                if (! $included) {
                    $fieldStatus = 'draft';
                } elseif ($fieldStatus === 'draft') {
                    $fieldStatus = 'active';
                }

                LoanFormFieldDefinition::query()->create([
                    'form_kind' => self::FORM_KIND,
                    'product_id' => null,
                    'field_key' => LoanFormFieldDefinition::generateFieldKey(self::FORM_KIND, $row['label']),
                    'label' => $row['label'],
                    'data_type' => $row['data_type'],
                    'is_required' => $required,
                    'select_options' => $selectOptions,
                    'prefill_from_previous' => $prefill,
                    'visible_to' => trim((string) ($row['visible_to'] ?? '')),
                    'is_core' => false,
                    'field_status' => $fieldStatus,
                    'sort_order' => $index,
                ]);
            }
        });

        $this->finalizeLoanFormSetupForProductIfRequested($request);

        return redirect()
            ->route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => 'product-rules'])
            ->with('status', 'Loan form setup (global library) saved.');
    }

    private function saveProductScoped(Request $request, int $productId): RedirectResponse
    {
        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.master_id' => ['required', 'integer'],
            'fields.*.included' => ['nullable', 'in:0,1'],
            'fields.*.is_required' => ['nullable', 'in:0,1'],
            'fields.*.prefill_from_previous' => ['nullable', 'in:0,1'],
            'fields.*.visible_to' => ['nullable', 'string', 'max:255'],
            'fields.*.field_status' => ['nullable', Rule::in(['active', 'draft', 'requires_approval'])],
            'complete_loan_form_setup_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
        ]);

        $this->assertPendingLoanFormProductIfRequested($request);

        DB::transaction(function () use ($validated, $productId): void {
            foreach ($validated['fields'] as $index => $row) {
                $masterId = (int) $row['master_id'];
                $master = LoanFormFieldDefinition::query()
                    ->where('form_kind', self::FORM_KIND)
                    ->whereNull('product_id')
                    ->whereKey($masterId)
                    ->firstOrFail();

                $included = isset($row['included']) && (string) $row['included'] === '1';
                if ($master->is_core) {
                    $included = true;
                }

                $required = isset($row['is_required']) && (string) $row['is_required'] === '1';
                $prefill = isset($row['prefill_from_previous']) && (string) $row['prefill_from_previous'] === '1';
                $visibleTo = trim((string) ($row['visible_to'] ?? ''));
                $rawStatus = trim((string) ($row['field_status'] ?? 'active'));
                $displayStatus = $rawStatus === 'requires_approval' ? 'requires_approval' : 'active';

                if ($master->is_core) {
                    LoanProductFormFieldOverride::query()
                        ->where('product_id', $productId)
                        ->where('form_kind', self::FORM_KIND)
                        ->where('field_key', $master->field_key)
                        ->delete();

                    continue;
                }

                if (! $included) {
                    LoanProductFormFieldOverride::query()
                        ->where('product_id', $productId)
                        ->where('form_kind', self::FORM_KIND)
                        ->where('field_key', $master->field_key)
                        ->delete();

                    continue;
                }

                LoanProductFormFieldOverride::query()->updateOrCreate(
                    [
                        'product_id' => $productId,
                        'form_kind' => self::FORM_KIND,
                        'field_key' => $master->field_key,
                    ],
                    [
                        'is_included' => $included,
                        'is_required' => $required,
                        'prefill_from_previous' => $prefill,
                        'visible_to' => $visibleTo !== '' ? $visibleTo : null,
                        'display_status' => $displayStatus,
                        'sort_order' => $index,
                    ]
                );
            }
        });

        $this->finalizeLoanFormSetupForProductIfRequested($request);

        return redirect()
            ->route('loan.system.form_setup.page', [
                'page' => 'loan-settings',
                'tab' => 'product-rules',
                'form_product' => $productId,
            ])
            ->with('status', 'Product-specific loan form setup saved.');
    }

    private function assertPendingLoanFormProductIfRequested(Request $request): void
    {
        if (! $request->filled('complete_loan_form_setup_product_id')) {
            return;
        }
        if (! Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
            return;
        }

        $id = (int) $request->input('complete_loan_form_setup_product_id');
        $product = LoanProduct::query()->find($id);
        if (! $product) {
            throw ValidationException::withMessages([
                'complete_loan_form_setup_product_id' => 'Invalid loan product.',
            ]);
        }
        if ($product->loan_form_setup_completed_at !== null) {
            throw ValidationException::withMessages([
                'complete_loan_form_setup_product_id' => 'This product is already set up. Use the product selector to adjust fields.',
            ]);
        }
    }

    private function finalizeLoanFormSetupForProductIfRequested(Request $request): void
    {
        if (! $request->filled('complete_loan_form_setup_product_id')) {
            return;
        }
        if (! Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
            return;
        }

        $id = (int) $request->input('complete_loan_form_setup_product_id');
        LoanProduct::query()
            ->whereKey($id)
            ->whereNull('loan_form_setup_completed_at')
            ->update(['loan_form_setup_completed_at' => now()]);
    }
}
