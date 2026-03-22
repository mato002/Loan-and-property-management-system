<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsSchedule;
use App\Models\SmsTemplate;
use App\Models\SmsWalletTopup;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanBulkSmsController extends Controller
{
    public function compose(Request $request, BulkSmsService $bulkSms): View
    {
        $templates = SmsTemplate::query()->orderBy('name')->get();
        $prefillBody = null;
        $prefillTemplateId = null;
        if ($request->filled('template')) {
            $t = SmsTemplate::query()->find($request->integer('template'));
            if ($t) {
                $prefillBody = $t->body;
                $prefillTemplateId = $t->id;
            }
        }

        return view('loan.bulksms.compose', [
            'templates' => $templates,
            'walletBalance' => $bulkSms->walletBalance(),
            'currency' => $bulkSms->currency(),
            'costPerSms' => $bulkSms->costPerSms(),
            'prefillBody' => $prefillBody,
            'prefillTemplateId' => $prefillTemplateId,
        ]);
    }

    public function composeStore(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'recipients' => ['required', 'string'],
            'message' => ['required', 'string', 'max:1000'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
            'sms_template_id' => ['nullable', 'exists:sms_templates,id'],
        ]);

        $phones = $bulkSms->normalizeRecipientList($validated['recipients']);
        $userId = $request->user()?->id;

        if ($phones === []) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => 'No valid phone numbers found. Use digits only (at least 9 per number), separated by comma, semicolon, or new lines.']);
        }

        if (! empty($validated['schedule_at'])) {
            $when = Carbon::parse($validated['schedule_at']);
            $bulkSms->createSchedule(
                $validated['message'],
                $phones,
                $when,
                $validated['sms_template_id'] ?? null,
                $userId
            );

            return redirect()
                ->route('loan.bulksms.schedules')
                ->with('status', 'SMS queued for '.$when->format('Y-m-d H:i').'.');
        }

        $result = $bulkSms->sendNow($validated['message'], $phones, $userId, null);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['message' => $result['error'] ?? 'Could not send messages.']);
        }

        return redirect()
            ->route('loan.bulksms.logs')
            ->with('status', sprintf('Sent %d message(s). Charged %s %s.', $result['sent'], number_format($result['charged'], 2), $bulkSms->currency()));
    }

    public function templatesIndex(): View
    {
        $templates = SmsTemplate::query()
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('loan.bulksms.templates.index', compact('templates'));
    }

    public function templatesCreate(): View
    {
        return view('loan.bulksms.templates.create');
    }

    public function templatesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $validated['user_id'] = $request->user()?->id;
        SmsTemplate::create($validated);

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template saved.');
    }

    public function templatesEdit(SmsTemplate $sms_template): View
    {
        return view('loan.bulksms.templates.edit', ['template' => $sms_template]);
    }

    public function templatesUpdate(Request $request, SmsTemplate $sms_template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $sms_template->update($validated);

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template updated.');
    }

    public function templatesDestroy(SmsTemplate $sms_template): RedirectResponse
    {
        $sms_template->delete();

        return redirect()
            ->route('loan.bulksms.templates.index')
            ->with('status', 'Template removed.');
    }

    public function logs(): View
    {
        $logs = SmsLog::query()
            ->with(['user', 'schedule'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('loan.bulksms.logs', compact('logs'));
    }

    public function wallet(BulkSmsService $bulkSms): View
    {
        $topups = SmsWalletTopup::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loan.bulksms.wallet', [
            'balance' => $bulkSms->walletBalance(),
            'currency' => $bulkSms->currency(),
            'costPerSms' => $bulkSms->costPerSms(),
            'topups' => $topups,
        ]);
    }

    public function walletTopup(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $bulkSms->topup((float) $validated['amount'], $validated['reference'] ?? null, $validated['notes'] ?? null);

        return redirect()
            ->route('loan.bulksms.wallet')
            ->with('status', 'Wallet topped up.');
    }

    public function schedules(): View
    {
        $schedules = SmsSchedule::query()
            ->with(['user', 'template'])
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return view('loan.bulksms.schedules', compact('schedules'));
    }

    public function schedulesCancel(SmsSchedule $sms_schedule): RedirectResponse
    {
        if ($sms_schedule->status !== 'pending') {
            return redirect()
                ->route('loan.bulksms.schedules')
                ->withErrors(['status' => 'Only pending schedules can be cancelled.']);
        }

        $sms_schedule->update([
            'status' => 'cancelled',
            'processed_at' => now(),
            'failure_reason' => 'Cancelled by user.',
        ]);

        return redirect()
            ->route('loan.bulksms.schedules')
            ->with('status', 'Schedule cancelled.');
    }
}
