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
}
