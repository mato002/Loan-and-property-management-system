<?php

namespace App\Http\Controllers\Property\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\Concerns\RecordsPmPortalDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantWorkspaceFormController extends Controller
{
    use RecordsPmPortalDraft;

    private const FORMS = [
        'tenant-email-receipts',
    ];

    public function show(string $form): View
    {
        if (! in_array($form, self::FORMS, true)) {
            abort(404);
        }

        return view('property.workspace.draft_form', [
            'formKey' => $form,
            'storeRoute' => 'property.tenant.workspace.form.store',
            'title' => 'Email all receipts',
            'subtitle' => 'Request copies to your inbox; delivery runs when email delivery is connected.',
            'backRoute' => 'property.tenant.payments.receipts',
            'backLabel' => '← Back to receipts',
            'fields' => [
                ['name' => 'email', 'label' => 'Send to (optional)', 'type' => 'email', 'placeholder' => 'Defaults to your account email if empty'],
                ['name' => 'period', 'label' => 'Period / filter', 'type' => 'text', 'placeholder' => 'e.g. Last 12 months, or date range'],
                ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'placeholder' => 'Any special instructions'],
            ],
            'submitLabel' => 'Submit email request',
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        if (! in_array($form, self::FORMS, true)) {
            abort(404);
        }

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'period' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = $data['notes'] ?? null;
        $ctx = array_filter([
            'email' => $data['email'] ?? null,
            'period' => $data['period'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $this->savePmPortalDraft($request, 'tenant', 'email_all_receipts', $notes, $ctx);

        return redirect()
            ->route('property.tenant.payments.receipts')
            ->with('success', 'Receipt email batch request recorded.');
    }
}
