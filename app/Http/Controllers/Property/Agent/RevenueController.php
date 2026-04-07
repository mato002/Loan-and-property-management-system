<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmPenaltyRule;
use App\Support\TabularExport;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use App\Services\Property\RentRollQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    public function rentRoll(Request $request): View|StreamedResponse
    {
        $rows = RentRollQuery::tableRows();
        $q = trim((string) $request->query('q', ''));
        $sort = strtolower(trim((string) $request->query('sort', 'unit')));
        $dir = strtolower(trim((string) $request->query('dir', 'asc')));
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $text = mb_strtolower(implode(' ', array_map(static fn ($c) => (string) $c, $row)));

                return str_contains($text, $needle);
            }));
        }
        $sortMap = ['unit' => 0, 'tenant' => 1, 'period' => 2, 'due' => 3, 'paid' => 5, 'balance' => 6, 'status' => 7];
        $sortIndex = $sortMap[$sort] ?? 0;
        usort($rows, static function (array $a, array $b) use ($sortIndex, $dir): int {
            $va = (string) ($a[$sortIndex] ?? '');
            $vb = (string) ($b[$sortIndex] ?? '');
            if (in_array($sortIndex, [3, 5, 6], true)) {
                $na = (float) preg_replace('/[^0-9.\-]/', '', $va);
                $nb = (float) preg_replace('/[^0-9.\-]/', '', $vb);
                return $dir === 'desc' ? ($nb <=> $na) : ($na <=> $nb);
            }

            return $dir === 'desc' ? strcasecmp($vb, $va) : strcasecmp($va, $vb);
        });

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return TabularExport::stream(
                'rent-roll-'.now()->format('Ymd_His'),
                ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield $row;
                    }
                },
                $export
            );
        }
        $paginator = $this->paginateRows($rows, $perPage, $request);
        $pageRows = $paginator->getCollection()->all();

        $stats = [
            ['label' => 'Billed (MTD)', 'value' => PropertyMoney::kes((float) PmInvoice::query()->whereMonth('issue_date', now()->month)->sum('amount')), 'hint' => 'Issued'],
            ['label' => 'Collected (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::mtdCollected()), 'hint' => 'Payments'],
            ['label' => 'Outstanding', 'value' => PropertyMoney::kes(PropertyDashboardStats::outstandingBalance()), 'hint' => 'Open'],
            ['label' => 'Units on roll', 'value' => (string) count($rows), 'hint' => 'Filtered total'],
        ];

        return view('property.agent.revenue.rent_roll', [
            'stats' => $stats,
            'columns' => ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
            'tableRows' => $pageRows,
            'paginator' => $paginator,
            'filters' => [
                'q' => $q,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function invoicesBulk(Request $request): RedirectResponse
    {
        $action = strtolower((string) $request->input('action', ''));
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one invoice.']);
        }
        if ($action === 'cancel') {
            PmInvoice::query()
                ->whereIn('id', $ids)
                ->where('status', '!=', PmInvoice::STATUS_PAID)
                ->update(['status' => PmInvoice::STATUS_CANCELLED]);
            return back()->with('status', 'Selected invoices were cancelled (paid invoices skipped).');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function paymentsBulk(Request $request): RedirectResponse
    {
        $action = strtolower((string) $request->input('action', ''));
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one payment.']);
        }
        // For now only allow deleting pending/failed; completed records are ledgered
        if ($action === 'delete') {
            \App\Models\PmPayment::query()
                ->whereIn('id', $ids)
                ->whereIn('status', ['pending', 'failed'])
                ->delete();
            return back()->with('status', 'Selected pending/failed payments removed.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function arrears(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'workflow' => strtolower(trim((string) $request->query('workflow', ''))),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => strtolower(trim((string) $request->query('sort', 'due_date'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'asc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', PmInvoice::STATUS_DRAFT);
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', '%'.$q.'%')
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'))
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }
        if ($filters['from'] !== '') {
            $query->whereDate('due_date', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('due_date', '<=', $filters['to']);
        }
        if ($filters['workflow'] !== '' && in_array($filters['workflow'], ['reminder', 'follow-up', 'escalated'], true)) {
            $today = now()->startOfDay()->toDateString();
            if ($filters['workflow'] === 'escalated') {
                $query->whereRaw('DATEDIFF(?, due_date) >= 30', [$today]);
            } elseif ($filters['workflow'] === 'follow-up') {
                $query->whereRaw('DATEDIFF(?, due_date) >= 14', [$today])
                    ->whereRaw('DATEDIFF(?, due_date) < 30', [$today]);
            } else {
                $query->whereRaw('DATEDIFF(?, due_date) < 14', [$today]);
            }
        }
        $sortMap = [
            'due_date' => 'due_date',
            'balance' => 'amount',
            'updated_at' => 'updated_at',
            'invoice_no' => 'invoice_no',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$filters['sort']] ?? 'due_date';
        $sortDir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'asc';
        $query->orderBy($sortBy, $sortDir)->orderBy('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $invoices = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'arrears-'.now()->format('Ymd_His'),
                ['Tenant', 'Unit', 'Invoice', 'Oldest due', 'Days late', 'Balance', 'Last contact', 'Workflow'],
                function () use ($invoices) {
                    foreach ($invoices as $i) {
                        $bal = max(0, (float) $i->amount - (float) $i->amount_paid);
                        $days = (int) $i->due_date->startOfDay()->diffInDays(now()->startOfDay(), true);
                        $workflow = $days >= 30 ? 'Escalated' : ($days >= 14 ? 'Follow-up' : 'Reminder');
                        yield [
                            (string) ($i->tenant->name ?? ''),
                            (string) (($i->unit->property->name ?? '').'/'.($i->unit->label ?? '')),
                            (string) ($i->invoice_no ?? ''),
                            $i->due_date?->format('Y-m-d') ?? '',
                            (string) $days,
                            number_format($bal, 2, '.', ''),
                            $i->updated_at?->format('Y-m-d') ?? '',
                            $workflow,
                        ];
                    }
                },
                $export
            );
        }

        $invoices = (clone $query)->paginate($perPage)->withQueryString();

        $stats = [
            ['label' => '7 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)), 'hint' => 'Early'],
            ['label' => '14 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)), 'hint' => ''],
            ['label' => '30+ days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)), 'hint' => ''],
            ['label' => 'Accounts', 'value' => (string) $invoices->getCollection()->unique('pm_tenant_id')->count(), 'hint' => 'Current page'],
        ];

        $rows = $invoices->getCollection()->map(function (PmInvoice $i) {
            $bal = max(0, (float) $i->amount - (float) $i->amount_paid);
            $days = (int) $i->due_date->startOfDay()->diffInDays(now()->startOfDay(), true);
            $workflow = $days >= 30 ? 'Escalated' : ($days >= 14 ? 'Follow-up' : 'Reminder');
            $selector = new HtmlString(
                '<input type="checkbox" class="arrears-invoice-pick rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="'.(int) $i->id.'" aria-label="Select invoice '.e((string) $i->invoice_no).'" />'
            );
            $owner = new HtmlString(
                '<a href="'.route('property.tenants.notices', ['tenant_id' => $i->pm_tenant_id, 'view' => 1], absolute: false).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open notices</a>'
            );
            $lastContact = $i->updated_at?->format('Y-m-d') ?? '—';

            return [
                $selector,
                $i->tenant->name,
                $i->unit->property->name.'/'.$i->unit->label,
                $i->invoice_no,
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
            'columns' => ['Pick', 'Tenant', 'Unit', 'Invoice', 'Oldest due', 'Days late', 'Balance', 'Last contact', 'Workflow', 'Owner'],
            'tableRows' => $rows,
            'paginator' => $invoices,
            'perPage' => $perPage,
            'reminderTargets' => $invoices->getCollection()
                ->map(fn (PmInvoice $i) => [
                    'id' => (int) $i->id,
                    'label' => (string) ($i->invoice_no.' · '.($i->tenant->name ?? 'Tenant').' · '.$i->due_date?->format('Y-m-d')),
                ])
                ->values()
                ->all(),
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $sortDir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    public function sendArrearsReminders(Request $request, BulkSmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email,both'],
            'template_key' => ['required', 'in:friendly,firm,final'],
            'target_mode' => ['nullable', 'in:all,single,selected'],
            'single_invoice_id' => ['nullable', 'integer', 'exists:pm_invoices,id'],
            'selected_invoice_ids' => ['nullable', 'array'],
            'selected_invoice_ids.*' => ['integer', 'exists:pm_invoices,id'],
            'selected_invoice_ids_raw' => ['nullable', 'string'],
        ]);

        $templates = [
            'friendly' => "Dear {tenant}, this is a reminder that your rent invoice {invoice_no} for {property_unit} is overdue by {days_overdue} day(s). Amount due: KES {balance_due}. Please make payment as soon as possible. If already paid, kindly share your receipt.",
            'firm' => "Dear {tenant}, your rent invoice {invoice_no} for {property_unit} is now {days_overdue} day(s) overdue. Outstanding amount: KES {balance_due}. Please clear this balance immediately to avoid penalties or restrictions.",
            'final' => "FINAL NOTICE: {tenant}, invoice {invoice_no} for {property_unit} remains unpaid ({days_overdue} day(s) overdue). Amount due: KES {balance_due}. Kindly settle urgently or contact management today.",
        ];
        $template = $templates[$data['template_key']] ?? $templates['friendly'];

        $targetMode = (string) ($data['target_mode'] ?? 'all');
        $singleInvoiceId = (int) ($data['single_invoice_id'] ?? 0);
        $selectedFromArray = collect((array) ($data['selected_invoice_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0);
        $selectedFromRaw = collect(preg_split('/[\s,;]+/', (string) ($data['selected_invoice_ids_raw'] ?? '')) ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();
        $selectedInvoiceIds = $selectedFromArray
            ->merge($selectedFromRaw)
            ->unique()
            ->values()
            ->all();

        $invoicesQuery = PmInvoice::query()
            ->with(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name'])
            ->where('status', '!=', PmInvoice::STATUS_DRAFT)
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<=', now()->toDateString());

        if ($targetMode === 'single') {
            if ($singleInvoiceId <= 0) {
                return back()->withErrors([
                    'single_invoice_id' => 'Choose an invoice for single reminder.',
                ])->withInput();
            }
            $invoicesQuery->where('id', $singleInvoiceId);
        } elseif ($targetMode === 'selected') {
            if ($selectedInvoiceIds === []) {
                return back()->withErrors([
                    'selected_invoice_ids' => 'Select one or more arrears rows first.',
                ])->withInput();
            }
            $invoicesQuery->whereIn('id', $selectedInvoiceIds);
        }

        $invoices = $invoicesQuery
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sentSms = 0;
        $sentEmail = 0;
        $failed = 0;
        $today = now()->toDateString();
        $failedReasons = [];
        $skippedReasons = [];

        $addFailedReason = static function (string $reason) use (&$failedReasons): void {
            $failedReasons[$reason] = (int) ($failedReasons[$reason] ?? 0) + 1;
        };
        $addSkippedReason = static function (string $reason) use (&$skippedReasons): void {
            $skippedReasons[$reason] = (int) ($skippedReasons[$reason] ?? 0) + 1;
        };

        foreach ($invoices as $inv) {
            $tenant = $inv->tenant;
            if (! $tenant) {
                $addSkippedReason('missing tenant');
                continue;
            }

            $balance = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
            if ($balance <= 0) {
                continue;
            }

            $dueDate = $inv->due_date?->toDateString();
            if (! $dueDate) {
                $addSkippedReason('missing due date');
                continue;
            }

            $daysOverdue = max(0, now()->diffInDays($inv->due_date, false) * -1);
            $propertyUnit = trim((string) (($inv->unit?->property?->name ?? '—').'/'.($inv->unit?->label ?? '—')), '/');
            $subject = '[ARREARS] '.$inv->invoice_no.' D+'.(string) $daysOverdue;
            $tenantEmail = strtolower(trim((string) ($tenant->email ?? '')));
            $message = strtr($template, [
                '{tenant}' => (string) $tenant->name,
                '{invoice_no}' => (string) $inv->invoice_no,
                '{property_unit}' => $propertyUnit,
                '{due_date}' => $dueDate,
                '{days_overdue}' => (string) $daysOverdue,
                '{balance_due}' => number_format($balance, 2),
            ]);

            if (in_array($data['channel'], ['email', 'both'], true)) {
                if ($tenantEmail === '') {
                    $addSkippedReason('email missing');
                } elseif (! filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                    $addSkippedReason('invalid email format');
                } else {
                    $alreadyEmailed = PmMessageLog::query()
                        ->where('channel', 'email')
                        ->where('subject', $subject)
                        ->where('to_address', $tenantEmail)
                        ->whereDate('created_at', $today)
                        ->exists();

                    if (! $alreadyEmailed) {
                        try {
                            Mail::raw($message, function ($m) use ($tenantEmail, $subject) {
                                $m->to($tenantEmail)->subject($subject);
                            });
                            PmMessageLog::query()->create([
                                'user_id' => $request->user()?->id,
                                'channel' => 'email',
                                'to_address' => $tenantEmail,
                                'subject' => $subject,
                                'body' => $message,
                                'delivery_status' => 'sent',
                                'sent_at' => now(),
                            ]);
                            $sentEmail++;
                        } catch (\Throwable $e) {
                            $failed++;
                            $addFailedReason('email send error');
                            PmMessageLog::query()->create([
                                'user_id' => $request->user()?->id,
                                'channel' => 'email',
                                'to_address' => $tenantEmail,
                                'subject' => $subject,
                                'body' => $message,
                                'delivery_status' => 'failed',
                                'delivery_error' => 'Email failed: '.$e->getMessage(),
                                'sent_at' => null,
                            ]);
                            Log::warning('arrears_reminder_email_failed', [
                                'invoice_id' => $inv->id,
                                'invoice_no' => $inv->invoice_no,
                                'tenant_id' => $tenant->id,
                                'tenant_email' => $tenantEmail,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $addSkippedReason('email already sent today');
                    }
                }
            }

            if (in_array($data['channel'], ['sms', 'both'], true)) {
                if (empty($tenant->phone)) {
                    $addSkippedReason('phone missing');
                } else {
                    $phones = $sms->normalizeRecipientList((string) $tenant->phone);
                    if ($phones !== []) {
                        $smsTo = implode(',', $phones);
                        $alreadySms = PmMessageLog::query()
                            ->where('channel', 'sms')
                            ->where('subject', $subject)
                            ->where('to_address', $smsTo)
                            ->whereDate('created_at', $today)
                            ->exists();

                        if (! $alreadySms) {
                            $result = $sms->sendNow($message, $phones, $request->user()?->id, null);
                            if (($result['ok'] ?? false) === true) {
                                PmMessageLog::query()->create([
                                    'user_id' => $request->user()?->id,
                                    'channel' => 'sms',
                                    'to_address' => $smsTo,
                                    'subject' => $subject,
                                    'body' => $message,
                                    'delivery_status' => 'sent',
                                    'sent_at' => now(),
                                ]);
                                $sentSms++;
                            } else {
                                $failed++;
                                $addFailedReason('sms provider error');
                            }
                        } else {
                            $addSkippedReason('sms already sent today');
                        }
                    } else {
                        $addSkippedReason('invalid phone format');
                    }
                }
            }
        }

        $failedSummary = collect($failedReasons)
            ->map(fn (int $count, string $reason) => "{$reason}={$count}")
            ->implode(', ');
        $skippedSummary = collect($skippedReasons)
            ->map(fn (int $count, string $reason) => "{$reason}={$count}")
            ->implode(', ');

        $message = "Arrears reminders sent. SMS: {$sentSms}, Email: {$sentEmail}, Failed: {$failed}.";
        if ($failedSummary !== '') {
            $message .= " Failed reasons: {$failedSummary}.";
        }
        if ($skippedSummary !== '') {
            $message .= " Skipped: {$skippedSummary}.";
        }

        return back()->with('success', $message);
    }

    public function sendArrearsTestEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $to = trim((string) ($user?->email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors([
                'arrears_test_email' => 'Your account email is missing or invalid. Update your profile email and retry.',
            ]);
        }

        $subject = '[ARREARS TEST] Mail diagnostics';
        $body = "This is a test email from arrears reminders.\n".
            'Sent at: '.now()->format('Y-m-d H:i:s')."\n".
            'User ID: '.(string) ($user?->id ?? 'n/a')."\n";

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });

            PmMessageLog::query()->create([
                'user_id' => $user?->id,
                'channel' => 'email',
                'to_address' => $to,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            return back()->with('success', 'Test email sent to '.$to.'.');
        } catch (\Throwable $e) {
            Log::warning('arrears_test_email_failed', [
                'user_id' => $user?->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'arrears_test_email' => 'Test email failed: '.$e->getMessage(),
            ]);
        }
    }

    public function penalties(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'scope' => trim((string) $request->query('scope', '')),
            'sort' => strtolower(trim((string) $request->query('sort', 'name'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'asc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmPenaltyRule::query();
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', '%'.$q.'%')
                    ->orWhere('scope', 'like', '%'.$q.'%')
                    ->orWhere('trigger_event', 'like', '%'.$q.'%')
                    ->orWhere('formula', 'like', '%'.$q.'%');
            });
        }
        if ($filters['status'] !== '' && in_array($filters['status'], ['active', 'off'], true)) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($filters['scope'] !== '') {
            $query->where('scope', $filters['scope']);
        }
        $sortMap = ['name' => 'name', 'scope' => 'scope', 'trigger_event' => 'trigger_event', 'effective_from' => 'effective_from', 'id' => 'id'];
        $sortBy = $sortMap[$filters['sort']] ?? 'name';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'asc';
        $query->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $exportRows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'penalty-rules-'.now()->format('Ymd_His'),
                ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
                function () use ($exportRows) {
                    foreach ($exportRows as $r) {
                        $parts = [$r->formula];
                        if ($r->percent !== null) {
                            $parts[] = (string) $r->percent.'%';
                        }
                        if ($r->amount !== null) {
                            $parts[] = PropertyMoney::kes((float) $r->amount);
                        }

                        yield [
                            (string) $r->name,
                            (string) $r->scope,
                            (string) $r->trigger_event.' (grace '.$r->grace_days.'d)',
                            implode(' · ', array_filter($parts)),
                            $r->cap !== null ? PropertyMoney::kes((float) $r->cap) : '—',
                            $r->effective_from?->format('Y-m-d') ?? '—',
                            $r->is_active ? 'Active' : 'Off',
                        ];
                    }
                },
                $export
            );
        }

        $rules = (clone $query)->paginate($perPage)->withQueryString();
        $active = $rules->getCollection()->where('is_active', true);

        $rows = $rules->getCollection()->map(function (PmPenaltyRule $r) {
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
                ['label' => 'Rules', 'value' => (string) $rules->total(), 'hint' => 'Filtered total'],
                ['label' => 'Active', 'value' => (string) $active->count(), 'hint' => ''],
                ['label' => 'Applied (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => 'Posting not automated'],
                ['label' => 'Waived (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => ''],
            ],
            'columns' => ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
            'tableRows' => $rows,
            'penaltyRules' => $rules->getCollection(),
            'paginator' => $rules,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
            'scopes' => PmPenaltyRule::query()->select('scope')->distinct()->orderBy('scope')->pluck('scope')->values(),
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

    public function receipts(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => strtolower(trim((string) $request->query('sort', 'updated_at'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $query = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->where('status', PmInvoice::STATUS_PAID)
            ->whereNotNull('updated_at');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', '%'.$q.'%')
                    ->orWhere('id', $q)
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'))
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }
        if ($filters['from'] !== '') {
            $query->whereDate('updated_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('updated_at', '<=', $filters['to']);
        }
        $sortMap = ['updated_at' => 'updated_at', 'amount' => 'amount', 'invoice_no' => 'invoice_no', 'id' => 'id'];
        $sortBy = $sortMap[$filters['sort']] ?? 'updated_at';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'desc';
        $query->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $items = (clone $query)->limit(5000)->get();
            return TabularExport::stream(
                'receipts-'.now()->format('Ymd_His'),
                ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status'],
                function () use ($items) {
                    foreach ($items as $i) {
                        yield [
                            'RCP-'.$i->id,
                            (string) $i->invoice_no,
                            (string) ($i->tenant->name ?? ''),
                            PropertyMoney::kes((float) $i->amount),
                            'KES 0.00',
                            $i->updated_at?->format('Y-m-d') ?? '',
                            'Stub',
                        ];
                    }
                },
                $export
            );
        }

        $invoices = (clone $query)->paginate($perPage)->withQueryString();

        $stats = [
            ['label' => 'Paid invoices', 'value' => (string) $invoices->total(), 'hint' => 'Filtered total'],
            ['label' => 'eTIMS linked', 'value' => '0', 'hint' => 'Integration pending'],
            ['label' => 'Failed', 'value' => '0', 'hint' => ''],
        ];

        $rows = $invoices->getCollection()->map(fn (PmInvoice $i) => [
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
            'paginator' => $invoices,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    /**
     * @param array<int,mixed> $rows
     */
    private function paginateRows(array $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = count($rows);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return (new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        ))->withQueryString();
    }
}
