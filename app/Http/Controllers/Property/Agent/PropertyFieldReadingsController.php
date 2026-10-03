<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\FieldMeterCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyFieldReadingsController extends Controller
{
    public function __construct(
        private readonly FieldMeterCaptureService $capture,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeCapture($request);

        return property_view('property.agent.field.readings', [
            'packUrl' => route('property.field.readings.pack', absolute: false),
            'syncUrl' => route('property.field.readings.sync', absolute: false),
        ]);
    }

    public function pack(Request $request): JsonResponse
    {
        $this->authorizeCapture($request);
        $month = $request->query('billing_month');

        $pack = $this->capture->pack($request->user(), is_string($month) ? $month : null);
        $pack['csrf'] = csrf_token();

        return response()->json($pack);
    }

    public function sync(Request $request): JsonResponse
    {
        $this->authorizeCapture($request);
        $data = $request->validate([
            'readings' => ['required', 'array', 'max:200'],
            'readings.*.client_id' => ['required', 'string', 'max:64'],
            'readings.*.property_unit_id' => ['required', 'integer'],
            'readings.*.meter' => ['required', 'in:water,electricity,other'],
            'readings.*.billing_month' => ['required', 'date_format:Y-m'],
            'readings.*.previous_reading' => ['nullable', 'numeric', 'min:0'],
            'readings.*.current_reading' => ['required', 'numeric', 'min:0'],
            'readings.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'readings.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'readings.*.is_meter_reset' => ['nullable', 'boolean'],
            'readings.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json([
            'results' => $this->capture->sync($request->user(), $data['readings']),
        ]);
    }

    private function authorizeCapture(Request $request): void
    {
        if (! $this->capture->canCapture($request->user())) {
            abort(403, 'Field meter capture is for field officers and staff who record utility readings.');
        }
    }
}
