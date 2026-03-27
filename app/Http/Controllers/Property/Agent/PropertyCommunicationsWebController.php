<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMessageLog;
use App\Models\PmMessageRead;
use App\Models\PmMessageTemplate;
use App\Support\CsvExport;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyCommunicationsWebController extends Controller
{
    public function notifications(Request $request): View
    {
        $logs = PmMessageLog::query()
            ->with('user')
            ->where('channel', 'system')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        if (Schema::hasTable('pm_message_reads') && $logs->isNotEmpty()) {
            $now = now();
            $uid = (int) $request->user()->id;
            $rows = $logs->map(fn (PmMessageLog $log) => [
                'pm_message_log_id' => (int) $log->id,
                'user_id' => $uid,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            PmMessageRead::query()->upsert(
                $rows,
                ['pm_message_log_id', 'user_id'],
                ['read_at', 'updated_at']
            );
        }

        $stats = [
            ['label' => 'Total alerts', 'value' => (string) $logs->count(), 'hint' => 'Latest 300'],
            ['label' => 'New', 'value' => (string) $logs->where('delivery_status', 'new')->count(), 'hint' => 'Unread/new alerts'],
            ['label' => 'Today', 'value' => (string) $logs->filter(fn (PmMessageLog $l) => $l->created_at?->isToday())->count(), 'hint' => ''],
            ['label' => 'This week', 'value' => (string) $logs->filter(fn (PmMessageLog $l) => $l->created_at?->greaterThanOrEqualTo(now()->startOfWeek()))->count(), 'hint' => ''],
        ];

        $rows = $logs->map(fn (PmMessageLog $l) => [
            $l->created_at?->format('Y-m-d H:i') ?? '—',
            strtoupper((string) $l->channel),
            strtoupper((string) ($l->delivery_status ?? 'unknown')),
            $l->subject ?? '—',
            Str::limit(strip_tags((string) ($l->body ?? '')), 120),
            $l->user?->name ?? 'System',
        ])->all();

        return view('property.agent.communications.notifications', [
            'stats' => $stats,
            'columns' => ['When', 'Channel', 'Status', 'Subject', 'Message', 'By'],
            'tableRows' => $rows,
        ]);
    }

    public function messages(Request $request): View
    {
        $filters = $request->only(['q', 'channel', 'status', 'from', 'to', 'sort', 'dir']);
        $logs = $this->messageLogsQuery($filters)->limit(300)->get();

        // Opening the notifications/messages page marks currently loaded messages as read for this user.
        if (Schema::hasTable('pm_message_reads') && $logs->isNotEmpty()) {
            $now = now();
            $uid = (int) $request->user()->id;
            $rows = $logs->map(fn (PmMessageLog $log) => [
                'pm_message_log_id' => (int) $log->id,
                'user_id' => $uid,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            PmMessageRead::query()->upsert(
                $rows,
                ['pm_message_log_id', 'user_id'],
                ['read_at', 'updated_at']
            );
        }

        $stats = [
            ['label' => 'Logged sends', 'value' => (string) $logs->count(), 'hint' => 'This list'],
            ['label' => 'Email', 'value' => (string) $logs->where('channel', 'email')->count(), 'hint' => ''],
            ['label' => 'SMS', 'value' => (string) $logs->where('channel', 'sms')->count(), 'hint' => ''],
            ['label' => 'System', 'value' => (string) $logs->where('channel', 'system')->count(), 'hint' => ''],
        ];

        $mapped = $logs->map(function (PmMessageLog $l) {
            $viewHref = route('property.communications.messages.show', $l, absolute: false);
            $actions = new HtmlString(
                '<a href="'.$viewHref.'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">View</a>'
            );

            return [
                'filter' => mb_strtolower($l->channel.' '.($l->delivery_status ?? '').' '.$l->to_address.' '.strip_tags($l->body)),
                'cells' => [
                    $l->created_at->format('Y-m-d H:i'),
                    strtoupper($l->channel),
                    strtoupper((string) ($l->delivery_status ?? 'unknown')),
                    $l->to_address,
                    $l->subject ?? '—',
                    ($l->delivery_error ? Str::limit($l->delivery_error, 48) : Str::limit(strip_tags($l->body), 48)),
                    $l->user->name ?? '—',
                    $actions,
                ],
            ];
        });

        return view('property.agent.communications.messages', [
            'stats' => $stats,
            'columns' => ['When', 'Channel', 'Status', 'To', 'Subject', 'Preview / Error', 'By', 'Actions'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
            'filters' => $filters,
        ]);
    }

    public function messagesExport(Request $request)
    {
        $filters = $request->only(['q', 'channel', 'status', 'from', 'to', 'sort', 'dir']);
        $rows = $this->messageLogsQuery($filters)->get();

        return CsvExport::stream(
            'communications_messages_'.now()->format('Ymd_His').'.csv',
            ['ID', 'When', 'Channel', 'Status', 'To', 'Subject', 'Body', 'Delivery Error', 'By'],
            function () use ($rows) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        $l->channel,
                        $l->delivery_status,
                        $l->to_address,
                        $l->subject,
                        strip_tags((string) $l->body),
                        $l->delivery_error,
                        $l->user?->name,
                    ];
                }
            }
        );
    }

    public function showMessage(PmMessageLog $log): View
    {
        $log->loadMissing('user');
        if (Schema::hasTable('pm_message_reads')) {
            PmMessageRead::query()->updateOrCreate(
                ['pm_message_log_id' => $log->id, 'user_id' => auth()->id()],
                ['read_at' => now()]
            );
        }

        return view('property.agent.communications.message_show', ['log' => $log]);
    }

    public function logMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'to_address' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        if ($data['channel'] === 'sms') {
            /** @var BulkSmsService $bulkSms */
            $bulkSms = app(BulkSmsService::class);
            $phones = $bulkSms->normalizeRecipientList($data['to_address']);
            if ($phones === []) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    ...$data,
                    'delivery_status' => 'failed',
                    'delivery_error' => 'Invalid recipient phone format.',
                    'sent_at' => null,
                ]);
                return back()->withInput()->withErrors([
                    'to_address' => 'Enter a valid phone number in international format (e.g. 2547XXXXXXXX) or local (e.g. 0712...).',
                ]);
            }

            $result = $bulkSms->sendNow($data['body'], $phones, $request->user()->id, null);
            if (! ($result['ok'] ?? false)) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    ...$data,
                    'delivery_status' => 'failed',
                    'delivery_error' => (string) ($result['error'] ?? 'Could not send SMS.'),
                    'sent_at' => null,
                ]);
                return back()->withInput()->withErrors([
                    'body' => $result['error'] ?? 'Could not send SMS.',
                ]);
            }
        } else {
            try {
                $subject = (string) ($data['subject'] ?? '(No subject)');
                Mail::raw((string) $data['body'], function ($m) use ($data, $subject) {
                    $m->to((string) $data['to_address'])->subject($subject);
                });
            } catch (\Throwable $e) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    ...$data,
                    'delivery_status' => 'failed',
                    'delivery_error' => 'Email failed: '.$e->getMessage(),
                    'sent_at' => null,
                ]);
                return back()->withInput()->withErrors([
                    'body' => 'Email failed to send (check MAIL_* in .env): '.$e->getMessage(),
                ]);
            }
        }

        PmMessageLog::query()->create([
            'user_id' => $request->user()->id,
            ...$data,
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return back()->with('success', $data['channel'] === 'sms'
            ? __('SMS sent and logged.')
            : __('Email sent and logged.')
        );
    }

    public function templates(): View
    {
        $templates = PmMessageTemplate::query()->orderBy('name')->get();

        $stats = [
            ['label' => 'Templates', 'value' => (string) $templates->count(), 'hint' => ''],
            ['label' => 'SMS', 'value' => (string) $templates->where('channel', 'sms')->count(), 'hint' => ''],
            ['label' => 'Email', 'value' => (string) $templates->where('channel', 'email')->count(), 'hint' => ''],
        ];

        $rows = $templates->map(fn (PmMessageTemplate $t) => [
            $t->name,
            strtoupper($t->channel),
            $t->subject ?? '—',
            Str::limit($t->body, 60),
            $t->updated_at->format('Y-m-d'),
        ])->all();

        return view('property.agent.communications.templates', [
            'stats' => $stats,
            'columns' => ['Name', 'Channel', 'Subject', 'Body preview', 'Updated'],
            'tableRows' => $rows,
            'messageTemplates' => $templates,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'channel' => ['required', 'in:sms,email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        PmMessageTemplate::query()->create($data);

        return back()->with('success', __('Template saved.'));
    }

    public function destroyTemplate(PmMessageTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('Template deleted.'));
    }

    public function bulk(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'from', 'to', 'sort', 'dir']);
        $logs = $this->bulkLogsQuery($filters)->limit(200)->get();

        $stats = [
            ['label' => 'Bulk jobs logged', 'value' => (string) $logs->count(), 'hint' => ''],
        ];

        $mapped = $logs->map(function (PmMessageLog $l) {
            $viewHref = route('property.communications.messages.show', $l, absolute: false);
            $actions = new HtmlString(
                '<a href="'.$viewHref.'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">View</a>'
            );

            return [
                'filter' => mb_strtolower($l->to_address.' '.$l->body.' '.($l->delivery_status ?? '')),
                'cells' => [
                    $l->created_at->format('Y-m-d H:i'),
                    strtoupper((string) ($l->delivery_status ?? 'unknown')),
                    $l->to_address,
                    Str::limit($l->body, 80),
                    $actions,
                ],
            ];
        });

        return view('property.agent.communications.bulk', [
            'stats' => $stats,
            'columns' => ['When', 'Status', 'Segment / label', 'Notes', 'Actions'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
            'walletBalance' => app(BulkSmsService::class)->walletBalance(),
            'currency' => app(BulkSmsService::class)->currency(),
            'costPerSms' => app(BulkSmsService::class)->costPerSms(),
            'filters' => $filters,
        ]);
    }

    public function bulkExport(Request $request)
    {
        $filters = $request->only(['q', 'status', 'from', 'to', 'sort', 'dir']);
        $rows = $this->bulkLogsQuery($filters)->get();

        return CsvExport::stream(
            'communications_bulk_'.now()->format('Ymd_His').'.csv',
            ['ID', 'When', 'Status', 'Segment Label', 'Subject', 'Notes', 'By'],
            function () use ($rows) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        $l->delivery_status,
                        $l->to_address,
                        $l->subject,
                        strip_tags((string) $l->body),
                        $l->user?->name,
                    ];
                }
            }
        );
    }

    public function logBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'segment_label' => ['required', 'string', 'max:255'],
            'recipients' => ['required', 'string'],
            'message' => ['required', 'string', 'max:1000'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
        ]);

        $bulkSms = app(BulkSmsService::class);
        $phones = $bulkSms->normalizeRecipientList($data['recipients']);
        if ($phones === []) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => 'No valid phone numbers found. Use digits only, separated by comma, semicolon, or new lines.']);
        }

        if (! empty($data['schedule_at'])) {
            $when = Carbon::parse($data['schedule_at']);
            $bulkSms->createSchedule($data['message'], $phones, $when, null, $request->user()->id);

            PmMessageLog::query()->create([
                'user_id' => $request->user()->id,
                'channel' => 'sms',
                'to_address' => $data['segment_label'],
                'subject' => '[BULK] '.$data['segment_label'],
                'body' => 'Scheduled '.$when->format('Y-m-d H:i').' · Recipients: '.count($phones),
                'delivery_status' => 'queued',
                'delivery_error' => null,
                'sent_at' => null,
            ]);

            return back()->with('success', __('Bulk SMS scheduled for '.$when->format('Y-m-d H:i').'.'));
        }

        $result = $bulkSms->sendNow($data['message'], $phones, $request->user()->id, null);
        if (! ($result['ok'] ?? false)) {
            return back()->withInput()->withErrors(['message' => $result['error'] ?? 'Could not send messages.']);
        }

        PmMessageLog::query()->create([
            'user_id' => $request->user()->id,
            'channel' => 'sms',
            'to_address' => $data['segment_label'],
            'subject' => '[BULK] '.$data['segment_label'],
            'body' => 'Sent '.count($phones).' SMS(s).',
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return back()->with('success', __('Bulk SMS sent and logged.'));
    }

    private function messageLogsQuery(array $filters): Builder
    {
        $q = PmMessageLog::query()->with('user');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('to_address', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhere('delivery_error', 'like', '%'.$search.'%');
            });
        }

        $channel = trim((string) ($filters['channel'] ?? ''));
        if ($channel !== '') {
            $q->where('channel', $channel);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $q->where('delivery_status', $status);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'delivery_status', 'channel'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    private function bulkLogsQuery(array $filters): Builder
    {
        $q = PmMessageLog::query()
            ->with('user')
            ->where('subject', 'like', '[BULK]%');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('to_address', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $q->where('delivery_status', $status);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'delivery_status'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }
}
