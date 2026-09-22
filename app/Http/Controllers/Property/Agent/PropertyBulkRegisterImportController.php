<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyBulkRegisterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyBulkRegisterImportController extends Controller
{
    public function index(): View
    {
        $appName = (string) config('app.name', 'Property ERP');

        return property_view('property.agent.settings.register_imports', [
            'appName' => $appName,
            'catalog' => PropertyBulkRegisterImportService::catalog(),
            'selectedType' => old('import_type', PropertyBulkRegisterImportService::TYPE_TENANTS_LEASES),
        ]);
    }

    public function store(Request $request, PropertyBulkRegisterImportService $importer): RedirectResponse
    {
        $types = array_keys(PropertyBulkRegisterImportService::catalog());

        $data = $request->validate([
            'import_type' => ['required', 'string', Rule::in($types)],
            'register_file' => ['required', 'file', 'mimes:csv,txt,pdf,xls,xlsx', 'max:15360'],
            'secondary_file' => ['nullable', 'file', 'mimes:csv,txt,xls,xlsx', 'max:15360'],
            'dry_run' => ['nullable', 'boolean'],
            'property' => ['nullable', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'register_only' => ['nullable', 'boolean'],
            'include_already_paid' => ['nullable', 'boolean'],
            'enrich_only' => ['nullable', 'boolean'],
            'remittances_only' => ['nullable', 'boolean'],
            'expenses_only' => ['nullable', 'boolean'],
            'post_gl' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'in:remittance,commission,tax,expense'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:paid,partial,unpaid'],
            'include_deposits' => ['nullable', 'boolean'],
            'no_update' => ['nullable', 'boolean'],
            'sync_invoices' => ['nullable', 'boolean'],
        ]);

        $type = (string) $data['import_type'];
        $catalog = PropertyBulkRegisterImportService::catalog()[$type];
        if (! empty($catalog['secondary']) && ! $request->hasFile('secondary_file')) {
            // secondary is optional for statement balances
        }

        $options = [
            'dry_run' => $request->boolean('dry_run'),
            'property' => $data['property'] ?? null,
            'limit' => $data['limit'] ?? null,
            'register_only' => $request->boolean('register_only'),
            'include_already_paid' => $request->boolean('include_already_paid'),
            'enrich_only' => $request->boolean('enrich_only'),
            'remittances_only' => $request->boolean('remittances_only'),
            'expenses_only' => $request->boolean('expenses_only'),
            'post_gl' => $request->boolean('post_gl'),
            'category' => $data['category'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'status' => $data['status'] ?? null,
            'include_deposits' => $request->boolean('include_deposits'),
            'no_update' => $request->boolean('no_update'),
            'sync_invoices' => $request->boolean('sync_invoices'),
        ];

        try {
            $result = $importer->importUploaded(
                $type,
                $request->file('register_file'),
                $request->user(),
                $options,
                $request->file('secondary_file'),
            );
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['register_file' => $e->getMessage()])
                ->withInput();
        }

        $summary = $result['summary'];
        $errors = is_array($summary['errors'] ?? null) ? $summary['errors'] : [];
        $message = PropertyBulkRegisterImportService::formatSummaryMessage(
            $type,
            $summary,
            (bool) $options['dry_run'],
        );

        if ($errors !== []) {
            return redirect()
                ->route('property.settings.register_imports')
                ->with('status', $message)
                ->withErrors(['import' => implode('; ', array_slice($errors, 0, 5))])
                ->withInput(['import_type' => $type]);
        }

        return redirect()
            ->route('property.settings.register_imports')
            ->with('status', $message)
            ->with('import_summary', $summary);
    }
}
