<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmPenaltyRule;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use App\Services\Property\RentRollQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function rentRoll(): View
    {
        $rows = RentRollQuery::tableRows();

        $stats = [
            ['label' => 'Billed (MTD)', 'value' => PropertyMoney::kes((float) PmInvoice::query()->whereMonth('issue_date', now()->month)->sum('amount')), 'hint' => 'Issued'],
            ['label' => 'Collected (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::mtdCollected()), 'hint' => 'Payments'],
            ['label' => 'Outstanding', 'value' => PropertyMoney::kes(PropertyDashboardStats::outstandingBalance()), 'hint' => 'Open'],
            ['label' => 'Units on roll', 'value' => (string) count($rows), 'hint' => 'Listed'],
        ];

        return view('property.agent.revenue.rent_roll', [
            'stats' => $stats,
            'columns' => ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function arrears(): View
    {
        $invoices = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(300)
            ->get();

        $stats = [
            ['label' => '7 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)), 'hint' => 'Early'],
            ['label' => '14 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)), 'hint' => ''],
            ['label' => '30+ days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)), 'hint' => ''],
            ['label' => 'Accounts', 'value' => (string) $invoices->unique('pm_tenant_id')->count(), 'hint' => 'Distinct tenants'],
        ];

        $rows = $invoices->map(function (PmInvoice $i) {
            $bal = max(0, (float) $i->amount - (float) $i->amount_paid);
            $days = (int) $i->due_date->startOfDay()->diffInDays(now()->startOfDay(), true);
            $workflow = $days >= 30 ? 'Escalated' : ($days >= 14 ? 'Follow-up' : 'Reminder');
            $owner = new HtmlString(
                '<a href="'.route('property.tenants.notices', ['tenant_id' => $i->pm_tenant_id, 'view' => 1], absolute: false).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open notices</a>'
            );
            $lastContact = $i->updated_at?->format('Y-m-d') ?? '—';

            return [
                $i->tenant->name,
                $i->unit->property->name.'/'.$i->unit->label,
                $i->due_date->format('Y-m-d'),
                (string) $days,
                PropertyMoney::kes($bal),
                $lastContact,
                $workflow,
                $owner,
            ];
        })->all();

        return view('property.agent.revenue.arrears', [
            'stats' => $stats,
            'columns' => ['Tenant', 'Unit', 'Oldest due', 'Days late', 'Balance', 'Last contact', 'Workflow', 'Owner'],
            'tableRows' => $rows,
        ]);
    }

    public function sendArrearsReminders(Request $request, BulkSmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email,both'],
            'template_key' => ['required', 'in:friendly,firm,final'],
        ]);

        $templates = [
            'friendly' => "Dear {tenant}, this is a reminder that your rent invoice {invoice_no} for {property_unit} is overdue by {days_overdue} day(s). Amount due: KES {balance_due}. Please make payment as soon as possible. If already paid, kindly share your receipt.",
            'firm' => "Dear {tenant}, your rent invoice {invoice_no} for {property_unit} is now {days_overdue} day(s) overdue. Outstanding amount: KES {balance_due}. Please clear this balance immediately to avoid penalties or restrictions.",
            'final' => "FINAL NOTICE: {tenant}, invoice {invoice_no} for {property_unit} remains unpaid ({days_overdue} day(s) overdue). Amount due: KES {balance_due}. Kindly settle urgently or contact management today.",
        ];
        $template = $templates[$data['template_key']] ?? $templates['friendly'];

        $invoices = PmInvoice::query()
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name'])
            ->where('status', '!=', PmInvoice::STATUS_DRAFT)
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<=', now()->toDateString())
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sentSms = 0;
        $sentEmail = 0;
        $failed = 0;
        $today = now()->toDateString();

        foreach ($invoices as $inv) {
            $tenant = $inv->tenant;
            if (! $tenant) {
                continue;
            }

            $balance = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
            if ($balance <= 0) {
                continue;
            }

            $dueDate = $inv->due_date?->toDateString();
            if (! $dueDate) {
                continue;
            }

            $daysOverdue = max(0, now()->diffInDays($inv->due_date, false) * -1);
            $propertyUnit = trim((string) (($inv->unit?->property?->name ?? '—').'/'.($inv->unit?->label ?? '—')), '/');
            $subject = '[ARREARS] '.$inv->invoice_no.' D+'.(string) $daysOverdue;
            $message = strtr($template, [
                '{tenant}' => (string) $tenant->name,
                '{invoice_no}' => (string) $inv->invoice_no,
                '{property_unit}' => $propertyUnit,
                '{due_date}' => $dueDate,
                '{days_overdue}' => (string) $daysOverdue,
                '{balance_due}' => number_format($balance, 2),
            ]);

            if (in_array($data['channel'], ['email', 'both'], true) && ! empty($tenant->email)) {
                $alreadyEmailed = PmMessageLog::query()
                    ->where('channel', 'email')
                    ->where('subject', $subject)
                    ->where('to_address', (string) $tenant->email)
                    ->whereDate('created_at', $today)
                    ->exists();

                if (! $alreadyEmailed) {
                    try {
                        Mail::raw($message, function ($m) use ($tenant, $subject) {
                            $m->to((string) $tenant->email)->subject($subject);
                        });
                        PmMessageLog::query()->create([
                            'user_id' => $request->user()?->id,
                            'channel' => 'email',
                            'to_address' => (string) $tenant->email,
                            'subject' => $subject,
                            'body' => $message,
                            'delivery_status' => 'sent',
                            'sent_at' => now(),
                        ]);
                        $sentEmail++;
                    } catch (\Throwable $e) {
                        $failed++;
                    }
                }
            }

            if (in_array($data['channel'], ['sms', 'both'], true) && ! empty($tenant->phone)) {
                $phones = $sms->normalizeRecipientList((string) $tenant->phone);
                if ($phones !== []) {
                    $alreadySms = PmMessageLog::query()
                        ->where('channel', 'sms')
                        ->where('subject', $subject)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (! $alreadySms) {
                        $result = $sms->sendNow($message, $phones, $request->user()?->id, null);
                        if (($result['ok'] ?? false) === true) {
                            PmMessageLog::query()->create([
                                'user_id' => $request->user()?->id,
                                'channel' => 'sms',
                                'to_address' => implode(',', $phones),
                                'subject' => $subject,
                                'body' => $message,
                                'delivery_status' => 'sent',
                                'sent_at' => now(),
                            ]);
                            $sentSms++;
                        } else {
                            $failed++;
                        }
                    }
                }
            }
        }

        return back()->with('success', "Arrears reminders sent. SMS: {$sentSms}, Email: {$sentEmail}, Failed: {$failed}.");
    }

    public function penalties(): View
    {
        $rules = PmPenaltyRule::query()->orderByDesc('is_active')->orderBy('name')->get();
        $active = $rules->where('is_active', true);

        $rows = $rules->map(function (PmPenaltyRule $r) {
            $parts = [$r->formula];
            if ($r->percent !== null) {
                $parts[] = (string) $r->percent.'%';
            }
            if ($r->amount !== null) {
                $parts[] = PropertyMoney::kes((float) $r->amount);
            }

            return [
                $r->name,
                $r->scope,
                $r->trigger_event.' (grace '.$r->grace_days.'d)',
                implode(' · ', array_filter($parts)),
                $r->cap !== null ? PropertyMoney::kes((float) $r->cap) : '—',
                $r->effective_from?->format('Y-m-d') ?? '—',
                $r->is_active ? 'Active' : 'Off',
            ];
        })->all();

        return view('property.agent.revenue.penalties', [
            'stats' => [
                ['label' => 'Rules', 'value' => (string) $rules->count(), 'hint' => 'Defined'],
                ['label' => 'Active', 'value' => (string) $active->count(), 'hint' => ''],
                ['label' => 'Applied (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => 'Posting not automated'],
                ['label' => 'Waived (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => ''],
            ],
            'columns' => ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
            'tableRows' => $rows,
            'penaltyRules' => $rules,
        ]);
    }

    public function storePenaltyRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'scope' => ['required', 'string', 'max:64'],
            'trigger_event' => ['required', 'string', 'max:64'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'formula' => ['required', 'string', 'max:64'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cap' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        PmPenaltyRule::query()->create([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', __('Penalty rule saved.'));
    }

    public function destroyPenaltyRule(PmPenaltyRule $penalty_rule): RedirectResponse
    {
        $penalty_rule->delete();

        return back()->with('success', __('Rule removed.'));
    }

    public function receipts(): View
    {
        $invoices = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->where('status', PmInvoice::STATUS_PAID)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Paid invoices', 'value' => (string) $invoices->count(), 'hint' => 'Receipt stubs'],
            ['label' => 'eTIMS linked', 'value' => '0', 'hint' => 'Integration pending'],
            ['label' => 'Failed', 'value' => '0', 'hint' => ''],
        ];

        $rows = $invoices->map(fn (PmInvoice $i) => [
            'RCP-'.$i->id,
            $i->invoice_no,
            $i->tenant->name,
            PropertyMoney::kes((float) $i->amount),
            'KES 0.00',
            $i->updated_at->format('Y-m-d'),
            'Stub',
            new HtmlString('<a href="'.route('property.revenue.receipts').'" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'),
        ])->all();

        return view('property.agent.revenue.receipts', [
            'stats' => $stats,
            'columns' => ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status', 'Actions'],
            'tableRows' => $rows,
        ]);
    }
}
