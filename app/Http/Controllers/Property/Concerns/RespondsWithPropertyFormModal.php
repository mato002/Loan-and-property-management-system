<?php

namespace App\Http\Controllers\Property\Concerns;

use App\Support\Property\PropertyFormModal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

trait RespondsWithPropertyFormModal
{
    protected function propertyFormModalViewData(Request $request): array
    {
        return PropertyFormModal::viewContext($request);
    }

    protected function propertyFormModalSuccess(string $message): Response
    {
        return response()->view('property.agent.partials.form_modal_success', [
            'message' => $message,
        ]);
    }

    protected function redirectOrPropertyFormModalSuccess(
        Request $request,
        RedirectResponse $redirect,
        string $message,
    ): RedirectResponse|Response {
        if (PropertyFormModal::fromModal($request)) {
            return $this->propertyFormModalSuccess($message);
        }

        return $redirect;
    }

    /**
     * @param  callable(): RedirectResponse  $onSuccess
     * @param  callable(?\Illuminate\Contracts\Validation\Validator): View  $renderForm
     */
    /**
     * @param  callable(): RedirectResponse  $onSuccess
     * @param  callable(?\Illuminate\Contracts\Validation\Validator): View  $renderForm
     */
    protected function handlePropertyFormModalUpdate(
        Request $request,
        callable $onSuccess,
        callable $renderForm,
        string $successMessage = 'Saved.',
    ): RedirectResponse|Response {
        try {
            $redirect = $onSuccess();
            if (PropertyFormModal::fromModal($request)) {
                return $this->propertyFormModalSuccess($successMessage);
            }

            return $redirect;
        } catch (ValidationException $e) {
            if (PropertyFormModal::fromModal($request)) {
                return response($renderForm($e->validator), 422);
            }

            throw $e;
        }
    }

    protected function backWithPropertySwalError(
        Request $request,
        string $message,
        string $title = 'Cannot save',
    ): RedirectResponse {
        return back()
            ->withInput()
            ->with('swal_flash', [
                'icon' => 'error',
                'title' => $title,
                'text' => $message,
                'confirmButtonColor' => '#dc2626',
            ]);
    }

    protected function propertyAccountingErrorTitle(string $message): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'open period') || str_contains($normalized, 'locked accounting period') || str_contains($normalized, 'accounting period for')) {
            return 'Accounting period required';
        }

        if (str_contains($normalized, 'chart account')) {
            return 'Chart of accounts setup';
        }

        return 'Cannot save invoice';
    }
}
