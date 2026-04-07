<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PmMessageLog;
use App\Models\PmMessageRead;
use App\Models\PmMessageTemplate;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\TabularExport;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyCommunicationsWebController extends Controller
{
    public function notifications(Request $request): View
    {
        $filters = $request->only(['q', 'channel', 'status', 'read', 'from', 'to', 'sort', 'dir', 'per_page']);
        $perPage = (int) ($filters['per_page'] ?? 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $logs = $this->notificationLogsQuery($filters)->paginate($perPage)->withQueryString();
        $uid = (int) $request->user()->id;
        $readIds = collect();
        if (Schema::hasTable('pm_message_reads') && $logs->isNotEmpty()) {
            $readIds = PmMessageRead::query()
                ->where('user_id', $uid)
                ->whereIn('pm_message_log_id', $logs->getCollection()->pluck('id')->all())
                ->pluck('pm_message_log_id');
        }
        $readLookup = $readIds->flip();

        $statsQuery = $this->notificationLogsQuery($filters);
        $statsRows = $statsQuery->limit(3000)->get();
        $stats = [
            ['label' => 'Total alerts', 'value' => (string) $statsRows->count(), 'hint' => 'Filtered set'],
            ['label' => 'Unread', 'value' => (string) $statsRows->filter(fn (PmMessageLog $l) => ! $readLookup->has((int) $l->id))->count(), 'hint' => 'Current page aware'],
            ['label' => 'Today', 'value' => (string) $statsRows->filter(fn (PmMessageLog $l) => $l->created_at?->isToday())->count(), 'hint' => ''],
            ['label' => 'This week', 'value' => (string) $statsRows->filter(fn (PmMessageLog $l) => $l->created_at?->greaterThanOrEqualTo(now()->startOfWeek()))->count(), 'hint' => ''],
        ];

        return view('property.agent.communications.notifications', [
            'stats' => $stats,
            'logs' => $logs,
            'readLookup' => $readLookup,
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }

    public function notificationsExport(Request $request)
    {
        $filters = $request->only(['q', 'channel', 'status', 'read', 'from', 'to', 'sort', 'dir']);
        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xls', 'pdf'], true)) {
            $format = 'csv';
        }
        $uid = (int) $request->user()->id;
        $rows = $this->notificationLogsQuery($filters)->get();
        $readIds = collect();
        if (Schema::hasTable('pm_message_reads') && $rows->isNotEmpty()) {
            $readIds = PmMessageRead::query()
                ->where('user_id', $uid)
                ->whereIn('pm_message_log_id', $rows->pluck('id')->all())
                ->pluck('pm_message_log_id');
        }
        $readLookup = $readIds->flip();

        return TabularExport::stream(
            'property-notifications',
            ['ID', 'When', 'Channel', 'Status', 'Read', 'To', 'Subject', 'Message', 'By'],
            function () use ($rows, $readLookup) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        strtoupper((string) $l->channel),
                        strtoupper((string) ($l->delivery_status ?? 'unknown')),
                        $readLookup->has((int) $l->id) ? 'READ' : 'UNREAD',
                        (string) ($l->to_address ?? ''),
                        (string) ($l->subject ?? ''),
                        strip_tags((string) ($l->body ?? '')),
                        (string) ($l->user?->name ?? 'System'),
                    ];
                }
            },
            $format
        );
    }

    public function notificationsBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', 'in:mark_read,mark_unread'],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer'],
        ]);

        if (! Schema::hasTable('pm_message_reads')) {
            return back()->withErrors(['bulk_action' => 'Read tracking table not available on this instance.']);
        }

        $ids = collect((array) $data['selected_ids'])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk_action' => 'Select at least one notification.']);
        }

        $validIds = PmMessageLog::query()
            ->whereIn('id', $ids->all())
            ->whereIn('channel', ['system', 'email', 'sms'])
            ->pluck('id');
        if ($validIds->isEmpty()) {
            return back()->withErrors(['bulk_action' => 'No valid notifications selected.']);
        }

        $uid = (int) $request->user()->id;
        if ($data['bulk_action'] === 'mark_read') {
            $now = now();
            $rows = $validIds->map(fn ($id) => [
                'pm_message_log_id' => (int) $id,
                'user_id' => $uid,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            PmMessageRead::query()->upsert($rows, ['pm_message_log_id', 'user_id'], ['read_at', 'updated_at']);

            return back()->with('success', 'Selected notifications marked as read.');
        }

        PmMessageRead::query()
            ->where('user_id', $uid)
            ->whereIn('pm_message_log_id', $validIds->all())
            ->delete();

        return back()->with('success', 'Selected notifications marked as unread.');
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
            'recipientContacts' => $this->recipientContactsForCompose(),
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

    /**
     * Returns a JSON list of phone numbers for a given group.
     * Supported: tenants, landlords, staff.
     */
    public function recipients(Request $request): Response
    {
        $type = strtolower((string) $request->query('type', ''));
        $channel = strtolower((string) $request->query('channel', 'sms'));
        $propertyId = $request->integer('property_id') ?: null;
        $detailed = $request->boolean('detailed');

        /** @var BulkSmsService $bulk */
        $bulk = app(BulkSmsService::class);
        $normalize = fn (?string $p) => $p ? $bulk->normalizeRecipientList($p)[0] ?? null : null;

        $recipients = [];

        if ($type === 'tenants') {
            $q = PmTenant::query();
            // Optional narrow by property via active leases tied to units of a property
            if ($propertyId) {
                $q->whereHas('leases.units', function (Builder $b) use ($propertyId) {
                    $b->where('property_units.property_id', $propertyId);
                });
            }
            $recipients = $channel === 'email'
                ? $q->whereNotNull('email')->pluck('email')->filter()->map(static fn ($e) => trim((string) $e))->filter()->unique()->values()->all()
                : $q->whereNotNull('phone')->pluck('phone')->filter()->map($normalize)->filter()->unique()->values()->all();

            if ($detailed) {
                $tenantRows = $q->with([
                    'leases' => fn ($lq) => $lq->where('status', 'active')->with(['units:id,property_id']),
                ])->get(['id', 'name', 'phone', 'email']);

                $tenantItems = $tenantRows
                    ->map(function (PmTenant $tenant) use ($channel, $normalize) {
                        $recipient = $channel === 'email'
                            ? trim((string) ($tenant->email ?? ''))
                            : ($normalize((string) ($tenant->phone ?? '')) ?? '');
                        if ($recipient === '') {
                            return null;
                        }
                        $propertyIds = $tenant->leases
                            ->flatMap(fn ($lease) => $lease->units->pluck('property_id'))
                            ->map(fn ($id) => (int) $id)
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        return [
                            'id' => (int) $tenant->id,
                            'name' => (string) $tenant->name,
                            'recipient' => $recipient,
                            'property_ids' => $propertyIds,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return response([
                    'ok' => true,
                    'type' => $type,
                    'channel' => $channel,
                    'count' => count($tenantItems),
                    'tenant_items' => $tenantItems,
                    'recipients' => array_values($recipients),
                    'phones' => $channel === 'sms' ? array_values($recipients) : [],
                ]);
            }
        } elseif ($type === 'staff') {
            $staffQuery = Employee::query();
            $recipients = $channel === 'email'
                ? $staffQuery->whereNotNull('email')->pluck('email')->filter()->map(static fn ($e) => trim((string) $e))->filter()->unique()->values()->all()
                : $staffQuery->whereNotNull('phone')->pluck('phone')->filter()->map($normalize)->filter()->unique()->values()->all();
        } elseif ($type === 'landlords') {
            $userIds = User::query()->where('property_portal_role', 'landlord')->pluck('id')->all();
            if ($userIds !== []) {
                if ($channel === 'email') {
                    $recipients = User::query()
                        ->whereIn('id', $userIds)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->filter()
                        ->map(static fn ($e) => trim((string) $e))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                } else {
                    // Landlord phone numbers are often unavailable on users; attempt tenant-linked profile phones.
                    $recipients = PmTenant::query()
                        ->whereIn('user_id', $userIds)
                        ->whereNotNull('phone')
                        ->pluck('phone')
                        ->filter()
                        ->map($normalize)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                }
            } else {
                $recipients = [];
            }
        } else {
            return response(['ok' => false, 'error' => 'Unknown type. Use tenants, landlords, or staff.'], 422);
        }

        return response([
            'ok' => true,
            'type' => $type,
            'channel' => $channel,
            'count' => count($recipients),
            'recipients' => array_values($recipients),
            'phones' => $channel === 'sms' ? array_values($recipients) : [],
        ]);
    }

    public function logMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'to_address' => ['nullable', 'string', 'max:5000'],
            'selected_recipients' => ['nullable', 'array'],
            'selected_recipients.*' => ['string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $manualRecipients = (string) ($data['to_address'] ?? '');
        $pickedRecipients = collect((array) ($data['selected_recipients'] ?? []))
            ->map(static fn ($v) => trim((string) $v))
            ->filter()
            ->values();
        $rawRecipients = collect(preg_split('/[\s,;]+/', $manualRecipients) ?: [])
            ->map(static fn ($v) => trim((string) $v))
            ->filter()
            ->merge($pickedRecipients)
            ->unique()
            ->values();

        if ($rawRecipients->isEmpty()) {
            return back()->withInput()->withErrors([
                'to_address' => 'Enter at least one recipient manually or select from contacts.',
            ]);
        }

        if ($data['channel'] === 'sms') {
            /** @var BulkSmsService $bulkSms */
            $bulkSms = app(BulkSmsService::class);
            $phones = $bulkSms->normalizeRecipientList($rawRecipients->implode(','));
            if ($phones === []) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    'channel' => 'sms',
                    'to_address' => $this->formatLoggedRecipientLabel($rawRecipients),
                    'subject' => $data['subject'] ?? null,
                    'body' => $data['body'],
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
                    'channel' => 'sms',
                    'to_address' => $this->formatLoggedRecipientLabel($rawRecipients),
                    'subject' => $data['subject'] ?? null,
                    'body' => $data['body'],
                    'delivery_status' => 'failed',
                    'delivery_error' => (string) ($result['error'] ?? 'Could not send SMS.'),
                    'sent_at' => null,
                ]);
                return back()->withInput()->withErrors([
                    'body' => $result['error'] ?? 'Could not send SMS.',
                ]);
            }
        } else {
            $emails = $rawRecipients
                ->filter(static fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL))
                ->values();
            if ($emails->isEmpty()) {
                return back()->withInput()->withErrors([
                    'to_address' => 'No valid email addresses found. Enter valid emails or pick contacts with email.',
                ]);
            }
            try {
                $subject = (string) ($data['subject'] ?? '(No subject)');
                Mail::raw((string) $data['body'], function ($m) use ($emails, $subject) {
                    $m->to($emails->first());
                    if ($emails->count() > 1) {
                        $m->bcc($emails->slice(1)->all());
                    }
                    $m->subject($subject);
                });
            } catch (\Throwable $e) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    'channel' => 'email',
                    'to_address' => $this->formatLoggedRecipientLabel($emails),
                    'subject' => $data['subject'] ?? null,
                    'body' => $data['body'],
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
            'channel' => $data['channel'],
            'to_address' => $this->formatLoggedRecipientLabel($rawRecipients),
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return back()->with('success', $data['channel'] === 'sms'
            ? __('SMS sent and logged.')
            : __('Email sent and logged.')
        );
    }

    /**
     * @return array<int,array{id:string,name:string,group:string,email:string,phone:string}>
     */
    private function recipientContactsForCompose(): array
    {
        $rows = collect();
        $userHasPhone = Schema::hasColumn('users', 'phone');
        $userSelect = ['id', 'name', 'email'];
        if ($userHasPhone) {
            $userSelect[] = 'phone';
        }

        $rows = $rows->merge(
            PmTenant::query()->orderBy('name')->get(['id', 'name', 'email', 'phone'])
                ->map(static fn (PmTenant $t) => [
                    'id' => 'tenant:'.$t->id,
                    'name' => (string) $t->name,
                    'group' => 'Tenant',
                    'email' => trim((string) ($t->email ?? '')),
                    'phone' => trim((string) ($t->phone ?? '')),
                ])
        );

        $rows = $rows->merge(
            User::query()
                ->where('property_portal_role', 'landlord')
                ->orderBy('name')
                ->get($userSelect)
                ->map(static fn (User $u) => [
                    'id' => 'landlord:'.$u->id,
                    'name' => (string) $u->name,
                    'group' => 'Landlord',
                    'email' => trim((string) ($u->email ?? '')),
                    'phone' => trim((string) ($u->phone ?? '')),
                ])
        );

        $rows = $rows->merge(
            User::query()
                ->whereNotIn('property_portal_role', ['landlord', 'tenant'])
                ->orderBy('name')
                ->get($userSelect)
                ->map(static fn (User $u) => [
                    'id' => 'user:'.$u->id,
                    'name' => (string) $u->name,
                    'group' => 'Other user',
                    'email' => trim((string) ($u->email ?? '')),
                    'phone' => trim((string) ($u->phone ?? '')),
                ])
        );

        return $rows
            ->filter(static fn (array $r) => $r['email'] !== '' || $r['phone'] !== '')
            ->unique(static fn (array $r) => Str::lower($r['group'].'|'.$r['name'].'|'.$r['email'].'|'.$r['phone']))
            ->values()
            ->all();
    }

    private function formatLoggedRecipientLabel(Collection $recipients): string
    {
        $items = $recipients
            ->map(static fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return '';
        }
        $joined = $items->implode(', ');
        if (Str::length($joined) <= 255) {
            return $joined;
        }

        return (string) $items->first().' (+' . max(0, $items->count() - 1) . ' more)';
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
        $filters = $request->only(['q', 'channel', 'status', 'from', 'to', 'sort', 'dir']);
        $logs = $this->bulkLogsQuery($filters)->limit(200)->get();

        $stats = [
            ['label' => 'Bulk jobs logged', 'value' => (string) $logs->count(), 'hint' => ''],
            ['label' => 'Bulk SMS', 'value' => (string) $logs->where('channel', 'sms')->count(), 'hint' => ''],
            ['label' => 'Bulk Email', 'value' => (string) $logs->where('channel', 'email')->count(), 'hint' => ''],
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
                    strtoupper((string) $l->channel),
                    strtoupper((string) ($l->delivery_status ?? 'unknown')),
                    $l->to_address,
                    Str::limit($l->body, 80),
                    $actions,
                ],
            ];
        });

        return view('property.agent.communications.bulk', [
            'stats' => $stats,
            'columns' => ['When', 'Channel', 'Status', 'Segment / label', 'Notes', 'Actions'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
            'walletBalance' => app(BulkSmsService::class)->walletBalance(),
            'currency' => app(BulkSmsService::class)->currency(),
            'costPerSms' => app(BulkSmsService::class)->costPerSms(),
            'filters' => $filters,
            'propertyOptions' => Property::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function bulkExport(Request $request)
    {
        $filters = $request->only(['q', 'channel', 'status', 'from', 'to', 'sort', 'dir']);
        $rows = $this->bulkLogsQuery($filters)->get();

        return CsvExport::stream(
            'communications_bulk_'.now()->format('Ymd_His').'.csv',
            ['ID', 'When', 'Channel', 'Status', 'Segment Label', 'Subject', 'Notes', 'By'],
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
                        $l->user?->name,
                    ];
                }
            }
        );
    }

    public function logBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email'],
            'segment_label' => ['required', 'string', 'max:255'],
            'recipients' => ['required', 'string'],
            'message' => ['required', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['channel'] === 'sms') {
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
                    'subject' => '[BULK][SMS] '.$data['segment_label'],
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
                'subject' => '[BULK][SMS] '.$data['segment_label'],
                'body' => 'Sent '.count($phones).' SMS(s).',
                'delivery_status' => 'sent',
                'delivery_error' => null,
                'sent_at' => now(),
            ]);

            return back()->with('success', __('Bulk SMS sent and logged.'));
        }

        if (! empty($data['schedule_at'])) {
            return back()
                ->withInput()
                ->withErrors(['schedule_at' => 'Scheduling is currently supported for SMS only. For email, send immediately.']);
        }

        $emails = collect(preg_split('/[\s,;]+/', (string) $data['recipients']) ?: [])
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->filter(static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => 'No valid email addresses found. Use comma, semicolon, space, or new lines.']);
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        $mailSubject = $subject !== '' ? $subject : '[Bulk] '.$data['segment_label'];

        $sent = 0;
        try {
            foreach ($emails as $email) {
                Mail::raw((string) $data['message'], function ($m) use ($email, $mailSubject) {
                    $m->to($email)->subject($mailSubject);
                });
                $sent++;
            }
        } catch (\Throwable $e) {
            PmMessageLog::query()->create([
                'user_id' => $request->user()->id,
                'channel' => 'email',
                'to_address' => $data['segment_label'],
                'subject' => '[BULK][EMAIL] '.$data['segment_label'],
                'body' => 'Failed after sending '.$sent.'/'.count($emails).' email(s).',
                'delivery_status' => 'failed',
                'delivery_error' => 'Email failed: '.$e->getMessage(),
                'sent_at' => null,
            ]);

            return back()->withInput()->withErrors([
                'message' => 'Bulk email failed: '.$e->getMessage(),
            ]);
        }

        PmMessageLog::query()->create([
            'user_id' => $request->user()->id,
            'channel' => 'email',
            'to_address' => $data['segment_label'],
            'subject' => '[BULK][EMAIL] '.$data['segment_label'],
            'body' => 'Sent '.$sent.' email(s).',
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return back()->with('success', __('Bulk email sent and logged.'));
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
        $channel = trim((string) ($filters['channel'] ?? ''));
        if ($channel !== '' && in_array($channel, ['sms', 'email'], true)) {
            $q->where('channel', $channel);
        }


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

    private function notificationLogsQuery(array $filters): Builder
    {
        $q = PmMessageLog::query()
            ->with('user')
            ->whereIn('channel', ['system', 'email', 'sms']);

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
        if ($channel !== '' && in_array($channel, ['system', 'email', 'sms'], true)) {
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

        if (Schema::hasTable('pm_message_reads')) {
            $uid = (int) auth()->id();
            $read = trim((string) ($filters['read'] ?? ''));
            if (in_array($read, ['read', 'unread'], true)) {
                $q->leftJoin('pm_message_reads as pmr', function ($join) use ($uid) {
                    $join->on('pm_message_logs.id', '=', 'pmr.pm_message_log_id')
                        ->where('pmr.user_id', '=', $uid);
                });
                if ($read === 'read') {
                    $q->whereNotNull('pmr.id');
                } else {
                    $q->whereNull('pmr.id');
                }
                $q->select('pm_message_logs.*');
            }
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'delivery_status', 'channel'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }
}
