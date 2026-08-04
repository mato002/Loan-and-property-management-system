<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MpesaPlatformTransaction;
use App\Models\PmMessageLog;
use App\Models\PmMessageRead;
use App\Models\PmMessageTemplate;
use App\Models\PmCommunicationExport;
use App\Models\PmConversation;
use App\Models\PmConversationMessage;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\User;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyCommunicationService;
use App\Services\Property\PropertyCommunicationTemplateService;
use App\Services\Property\RentReminderEligibilityService;
use App\Services\Property\SmsHealthService;
use App\Services\Property\PropertySmsResendService;
use App\Services\Property\SmsDeliveryErrorPresenter;
use App\Services\Property\TenantCommunicationStageService;
use App\Support\CsvExport;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $query = $this->notificationLogsQuery($filters);
        $aggregates = $this->notificationLogAggregates($query);
        $page = max(1, (int) $request->query('page', 1));
        $items = (clone $query)->with('user')->forPage($page, $perPage)->get();
        $logs = new LengthAwarePaginator(
            $items,
            (int) ($aggregates->stat_total ?? 0),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );
        $uid = (int) $request->user()->id;
        $readIds = collect();
        if (Schema::hasTable('pm_message_reads') && $logs->isNotEmpty()) {
            $readIds = PmMessageRead::query()
                ->where('user_id', $uid)
                ->whereIn('pm_message_log_id', $logs->getCollection()->pluck('id')->all())
                ->pluck('pm_message_log_id');
        }
        $readLookup = $readIds->flip();

        $stats = [
            ['label' => 'Total alerts', 'value' => (string) ((int) ($aggregates->stat_total ?? 0)), 'hint' => 'Filtered set'],
            ['label' => 'Unread', 'value' => (string) ((int) ($aggregates->stat_unread ?? 0)), 'hint' => 'Matching filters'],
            ['label' => 'Today', 'value' => (string) ((int) ($aggregates->stat_today ?? 0)), 'hint' => ''],
            ['label' => 'This week', 'value' => (string) ((int) ($aggregates->stat_week ?? 0)), 'hint' => ''],
        ];

        return property_view('property.agent.communications.notifications', [
            'stats' => $stats,
            'logs' => $logs,
            'readLookup' => $readLookup,
            'filters' => $filters,
            'perPage' => $perPage,
            'canManageCommunications' => $this->canManageCommunications($request),
            'canExportCommunications' => $this->canExportCommunications($request),
        ]);
    }

    public function notificationsExport(Request $request)
    {
        if (! ($request->user()->hasPmPermission('communications.export') || $request->user()->hasPmPermission('communications.manage'))) {
            abort(403, 'You do not have permission to export communications.');
        }
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
        $canViewBody = $this->canViewMessageBody($request);
        $this->logExportAudit($request, 'notifications', $format, (int) $rows->count(), $filters);

        return TabularExport::stream(
            'property-notifications',
            ['ID', 'When', 'Channel', 'Status', 'Read', 'To', 'Subject', 'Message', 'By'],
            function () use ($rows, $readLookup, $canViewBody) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        strtoupper((string) $l->channel),
                        strtoupper((string) ($l->delivery_status ?? 'unknown')),
                        $readLookup->has((int) $l->id) ? 'READ' : 'UNREAD',
                        $this->maskAddress((string) ($l->to_address ?? '')),
                        (string) ($l->subject ?? ''),
                        $canViewBody ? strip_tags((string) ($l->body ?? '')) : '[MASKED]',
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
            ->where('channel', 'system')
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

    public function notificationsMarkAllRead(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('pm_message_reads')) {
            return back()->withErrors(['bulk_action' => 'Read tracking table not available on this instance.']);
        }

        $filters = $request->only(['q', 'status', 'read', 'from', 'to']);
        $uid = (int) $request->user()->id;
        $ids = $this->notificationLogsQuery($filters)->pluck('id');
        if ($ids->isEmpty()) {
            return back()->with('warning', 'No notifications matched the current filters.');
        }

        $alreadyRead = PmMessageRead::query()
            ->where('user_id', $uid)
            ->whereIn('pm_message_log_id', $ids->all())
            ->pluck('pm_message_log_id');
        $toMark = $ids->diff($alreadyRead);
        if ($toMark->isEmpty()) {
            return back()->with('success', 'All matching notifications are already marked as read.');
        }

        $now = now();
        $rows = $toMark->map(fn ($id) => [
            'pm_message_log_id' => (int) $id,
            'user_id' => $uid,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        PmMessageRead::query()->upsert($rows, ['pm_message_log_id', 'user_id'], ['read_at', 'updated_at']);

        return back()->with('success', 'Marked '.$toMark->count().' notification(s) as read.');
    }

    public function showNotification(PmMessageLog $log): View
    {
        if ($log->channel !== 'system') {
            abort(404);
        }

        return $this->renderMessageLogDetail($log, 'property.notifications', 'Back to notifications');
    }

    public function messages(Request $request): View
    {
        $rawFilters = $request->only([
            'q', 'channel', 'status', 'from', 'to', 'sort', 'dir', 'sender', 'has_error', 'period', 'per_page', 'duplicates',
        ]);
        if (! $this->messageFiltersAreScoped($rawFilters)) {
            $rawFilters['period'] = 'month';
        }

        $filters = $this->normalizeMessageFilters($rawFilters);
        $perPage = (int) ($filters['per_page'] ?? 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $query = $this->messageLogsQuery($filters);
        $aggregates = $this->messageLogAggregates($query);
        $page = max(1, (int) $request->query('page', 1));
        $items = (clone $query)->with('user')->forPage($page, $perPage)->get();
        $logs = new LengthAwarePaginator(
            $items,
            (int) ($aggregates->stat_total ?? 0),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );
        $stats = $this->messageStatsFromAggregates($aggregates, $filters);
        $canViewBody = $this->canViewMessageBody($request);
        $senderOptions = $this->cachedMessageSenderOptions($filters);
        $resendActions = $this->resendActionsForMessageLogs($request, $items);
        $logPresentations = $this->messageLogPresentations($items);
        $inlineCompose = $this->shouldInlineComposeContext($request);

        return property_view('property.agent.communications.messages', [
            'stats' => $stats,
            'logs' => $logs,
            'resendActions' => $resendActions,
            'logPresentations' => $logPresentations,
            'filters' => $filters,
            'perPage' => $perPage,
            'canViewBody' => $canViewBody,
            'senderOptions' => $senderOptions,
            'recipientContacts' => $inlineCompose ? $this->recipientContactsForCompose() : [],
            'columns' => [],
            'tableRows' => [],
            'tableRowFilters' => [],
            'canManageCommunications' => $this->canManageCommunications($request),
            'canExportCommunications' => $this->canExportCommunications($request),
            'smsWallet' => $inlineCompose ? $this->smsWalletStatus() : $this->emptySmsWalletStatus(),
            'composeTemplates' => $inlineCompose ? $this->composeTemplates() : [],
            'composeContextUrl' => route('property.communications.messages.compose_context', absolute: false),
        ]);
    }

    public function messagesComposeContext(Request $request): JsonResponse
    {
        if (! $this->canManageCommunications($request)) {
            abort(403, 'You do not have permission to compose messages.');
        }

        return response()->json([
            'ok' => true,
            'recipientContacts' => $this->recipientContactsForCompose(),
            'composeTemplates' => $this->composeTemplates(),
            'smsWallet' => $this->smsWalletStatus(),
        ]);
    }

    public function smsWalletTopup(Request $request): RedirectResponse
    {
        if (! $this->canManageCommunications($request)) {
            abort(403, 'You do not have permission to top up SMS balance.');
        }

        /** @var BulkSmsService $bulk */
        $bulk = app(BulkSmsService::class);
        $min = $bulk->minTopupAmount();
        $max = $bulk->maxTopupAmount();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$min, 'max:'.$max],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $result = $bulk->initiateProviderTopup((float) $data['amount'], (string) $data['phone']);
        if (! ($result['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors(['sms_topup' => (string) ($result['error'] ?? 'Could not initiate M-Pesa top-up.')]);
        }

        if (Schema::hasTable('mpesa_platform_transactions')) {
            MpesaPlatformTransaction::query()->create([
                'reference' => 'PRADYSMS-'.strtoupper(substr(sha1(uniqid((string) mt_rand(), true)), 0, 8)),
                'amount' => (float) ($result['amount'] ?? $data['amount']),
                'channel' => 'stk_push',
                'status' => 'pending',
                'notes' => 'Pradytec SMS wallet top-up initiated from property communications.',
                'transaction_id' => (string) ($result['transaction_id'] ?? ''),
                'meta' => [
                    'purpose' => 'provider_sms_topup',
                    'module' => 'property',
                    'requested_by_user_id' => (int) $request->user()->id,
                    'requested_phone' => (string) ($result['phone_number'] ?? ''),
                    'provider' => [
                        'transaction_id' => (string) ($result['transaction_id'] ?? ''),
                        'checkout_request_id' => (string) ($result['checkout_request_id'] ?? ''),
                        'status' => (string) ($result['status'] ?? 'pending'),
                        'message' => (string) ($result['message'] ?? ''),
                    ],
                ],
            ]);
        }

        return back()
            ->with(
                'success',
                (string) ($result['message'] ?? 'M-Pesa prompt sent. Complete payment on your phone; SMS balance updates at Pradytec after confirmation.')
            )
            ->with('sms_topup_pending_id', (string) ($result['transaction_id'] ?? ''));
    }

    public function smsBalanceJson(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'wallet' => $this->smsWalletStatus(),
        ]);
    }

    public function smsTopupStatusJson(Request $request): JsonResponse
    {
        if (! $this->canManageCommunications($request)) {
            abort(403, 'You do not have permission to check SMS top-up status.');
        }

        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:128'],
        ]);

        /** @var BulkSmsService $bulk */
        $bulk = app(BulkSmsService::class);
        $result = $bulk->providerTopupStatus((string) $data['transaction_id']);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => (string) ($result['error'] ?? 'Could not load top-up status.'),
            ], 422);
        }

        $transaction = (array) ($result['transaction'] ?? []);
        $status = strtolower((string) ($transaction['status'] ?? 'unknown'));

        return response()->json([
            'ok' => true,
            'status' => $status,
            'transaction' => $transaction,
            'wallet' => $this->smsWalletStatus(),
        ]);
    }

    public function smsProvider(Request $request): View
    {
        /** @var BulkSmsService $bulk */
        $bulk = app(BulkSmsService::class);
        $filters = $request->only(['status', 'page', 'per_page']);
        $perPage = (int) ($filters['per_page'] ?? 20);
        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }
        $filters['per_page'] = $perPage;

        $history = $bulk->providerSmsHistory($filters);
        $statistics = $bulk->providerSmsStatistics();
        $stats = $this->providerSmsStatsCards($statistics);

        return property_view('property.agent.communications.sms_provider', [
            'stats' => $stats,
            'history' => $history,
            'statistics' => $statistics,
            'filters' => $filters,
            'smsWallet' => $this->smsWalletStatus(),
            'smsTopup' => $this->smsTopupContext($request),
            'canManageCommunications' => $this->canManageCommunications($request),
            'webhookUrl' => url('/webhooks/property/communications/pradytec'),
        ]);
    }

    public function messagesBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', 'in:resend_failed,mark_read'],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer'],
        ]);

        $ids = collect((array) $data['selected_ids'])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk_action' => 'Select at least one message.']);
        }

        $logs = PmMessageLog::query()->whereIn('id', $ids->all())->get();
        if ($logs->isEmpty()) {
            return back()->withErrors(['bulk_action' => 'No valid messages selected.']);
        }

        if ($data['bulk_action'] === 'mark_read') {
            if (! Schema::hasTable('pm_message_reads')) {
                return back()->withErrors(['bulk_action' => 'Read tracking table not available on this instance.']);
            }

            $uid = (int) $request->user()->id;
            $now = now();
            $rows = $logs->map(fn (PmMessageLog $log) => [
                'pm_message_log_id' => (int) $log->id,
                'user_id' => $uid,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            PmMessageRead::query()->upsert($rows, ['pm_message_log_id', 'user_id'], ['read_at', 'updated_at']);

            return back()->with('success', 'Selected messages marked as read.');
        }

        /** @var BulkSmsService $sms */
        $sms = app(BulkSmsService::class);
        $resent = 0;
        $skipped = 0;
        $bulkFailures = [];
        $errorPresenter = app(SmsDeliveryErrorPresenter::class);
        $delayMs = max(0, (int) config('bulksms.bulk_resend_delay_ms', 1500));
        $smsLogs = $logs->filter(static fn (PmMessageLog $log): bool => $log->channel === 'sms');
        $eligibility = app(RentReminderEligibilityService::class);
        $resendActions = $eligibility->resendActionsForLogs($smsLogs);
        $eligibleSmsLogs = $smsLogs->filter(
            static fn (PmMessageLog $log): bool => (bool) (($resendActions[(int) $log->id]['can_bulk_select'] ?? false))
        );
        $ineligibleSelectionSkipped = $smsLogs->count() - $eligibleSmsLogs->count();
        $deduped = app(PropertySmsResendService::class)->dedupeLogsForResend($eligibleSmsLogs);
        $sendable = 0;

        foreach ($deduped as $log) {
            if ($sms->normalizeRecipientList((string) $log->to_address) !== []) {
                $sendable++;
            }
        }

        if ($sendable > 0) {
            $afford = $sms->canAffordRecipients($sendable);
            if (! ($afford['ok'] ?? false)) {
                return back()->withErrors([
                    'bulk_action' => $errorPresenter->forAgent((string) ($afford['error'] ?? 'Insufficient SMS balance for bulk resend.')),
                ]);
            }
        }
        $duplicateSelectionSkipped = $eligibleSmsLogs->count() - $deduped->count();

        $attemptIndex = 0;
        $skippedDelivered = 0;
        foreach ($deduped as $log) {
            $phones = $sms->normalizeRecipientList((string) $log->to_address);
            if ($phones === []) {
                $skipped++;
                continue;
            }

            if ($attemptIndex > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
            $attemptIndex++;

            $result = app(PropertySmsResendService::class)->resendLog($log, $phones, (int) $request->user()->id);

            if (($result['ok'] ?? false) === true) {
                $resent++;
            } elseif (($result['skipped'] ?? false) === true) {
                $skippedDelivered++;
            } else {
                $bulkFailures[] = [
                    'id' => (int) $log->id,
                    'error' => (string) ($result['error'] ?? 'Send failed'),
                ];
            }
        }

        if ($resent === 0 && $bulkFailures === [] && $skippedDelivered === 0 && $ineligibleSelectionSkipped === 0) {
            return back()->withErrors(['bulk_action' => 'No SMS messages could be resent from the selection.']);
        }

        $message = "Resent {$resent} SMS message(s).";
        if ($ineligibleSelectionSkipped > 0) {
            $message .= " Ignored {$ineligibleSelectionSkipped} row(s) already sent, delivered, or resolved.";
        }
        if ($duplicateSelectionSkipped > 0) {
            $message .= " Collapsed {$duplicateSelectionSkipped} duplicate row(s) in your selection (same invoice + phone).";
        }
        if ($skippedDelivered > 0) {
            $message .= " Skipped {$skippedDelivered} already-delivered reminder(s).";
        }
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} non-SMS or invalid recipient row(s).";
        }
        if ($bulkFailures !== []) {
            return back()->with('warning', $message.' '.$errorPresenter->summarizeBulkFailures($bulkFailures));
        }

        return back()->with('success', $message);
    }

    public function resendMessage(Request $request, PmMessageLog $log): RedirectResponse
    {
        if ($log->channel !== 'sms') {
            return back()->withErrors(['channel' => 'Only SMS messages can be resent from this action.']);
        }

        /** @var BulkSmsService $sms */
        $sms = app(BulkSmsService::class);
        $phones = $sms->normalizeRecipientList((string) $log->to_address);
        if ($phones === []) {
            return back()->withErrors(['to_address' => 'Could not normalize the stored phone number.']);
        }

        $result = app(PropertySmsResendService::class)->resendLog($log, $phones, (int) $request->user()->id);

        if (($result['ok'] ?? false) !== true) {
            return back()->withErrors([
                'body' => app(SmsDeliveryErrorPresenter::class)->forAgent((string) ($result['error'] ?? 'SMS resend failed.')),
            ]);
        }

        return back()->with('success', 'SMS resent to '.implode(', ', $phones).'.');
    }

    public function messagesExport(Request $request)
    {
        if (! ($request->user()->hasPmPermission('communications.export') || $request->user()->hasPmPermission('communications.manage'))) {
            abort(403, 'You do not have permission to export communications.');
        }
        $filters = $this->normalizeMessageFilters($request->only([
            'q', 'channel', 'status', 'from', 'to', 'sort', 'dir', 'sender', 'has_error', 'period', 'duplicates',
        ]));
        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xls', 'pdf'], true)) {
            $format = 'csv';
        }
        $rows = $this->messageLogsQuery($filters)->with('user')->get();
        $canViewBody = $this->canViewMessageBody($request);
        $this->logExportAudit($request, 'messages', $format, (int) $rows->count(), $filters);

        return TabularExport::stream(
            'communications-messages',
            ['ID', 'When', 'Channel', 'Internal Stage', 'Display Label', 'Status', 'To', 'Subject', 'Body', 'Delivery Error', 'Sent At', 'By'],
            function () use ($rows, $canViewBody) {
                foreach ($rows as $l) {
                    $parsed = app(TenantCommunicationStageService::class)->parseStaffSubject($l->subject);
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        strtoupper((string) $l->channel),
                        (string) ($l->internal_stage ?: ($parsed['internal_stage'] ?? '')),
                        (string) ($l->display_stage ?: ($parsed['display_label'] ?? '')),
                        strtoupper((string) ($l->delivery_status ?? 'unknown')),
                        $this->maskAddress((string) $l->to_address),
                        (string) ($l->subject ?? ''),
                        $canViewBody ? strip_tags((string) $l->body) : '[MASKED]',
                        (string) ($l->delivery_error ?? ''),
                        optional($l->sent_at)->format('Y-m-d H:i:s') ?? '',
                        (string) ($l->user?->name ?? 'System'),
                    ];
                }
            },
            $format
        );
    }

    public function showMessage(PmMessageLog $log): View
    {
        if (! in_array($log->channel, ['email', 'sms'], true)) {
            abort(404);
        }

        return $this->renderMessageLogDetail($log, 'property.communications.messages', 'Back to SMS / email log');
    }

    private function renderMessageLogDetail(PmMessageLog $log, string $backRoute, string $backLabel): View
    {
        $log->loadMissing('user');
        if (Schema::hasTable('pm_message_reads')) {
            PmMessageRead::query()->updateOrCreate(
                ['pm_message_log_id' => $log->id, 'user_id' => auth()->id()],
                ['read_at' => now()]
            );
        }

        $resendAction = app(RentReminderEligibilityService::class)->smsResendActionForLog(
            $log,
            app(RentReminderEligibilityService::class)->deliveredInvoiceKeysForInvoiceNumbers([
                app(RentReminderEligibilityService::class)->extractInvoiceNoFromLogText((string) $log->subject, (string) $log->body),
            ])
        );

        return property_view('property.agent.communications.message_show', [
            'log' => $log,
            'backRoute' => $backRoute,
            'backLabel' => $backLabel,
            'canManageCommunications' => $this->canManageCommunications(request()),
            'resendAction' => $resendAction,
        ]);
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
            'message_template_id' => ['nullable', 'integer', 'exists:pm_message_templates,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
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

        $recipients = $data['channel'] === 'sms'
            ? app(BulkSmsService::class)->normalizeRecipientList($rawRecipients->implode(','))
            : $rawRecipients->filter(static fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL))->values()->all();
        if ($recipients === []) {
            return back()->withInput()->withErrors([
                'to_address' => $data['channel'] === 'sms'
                    ? 'Enter a valid phone number in international format (e.g. 2547XXXXXXXX) or local (e.g. 0712...).'
                    : 'No valid email addresses found. Enter valid emails or pick contacts with email.',
            ]);
        }

        if ($data['channel'] === 'sms') {
            $afford = app(BulkSmsService::class)->canAffordRecipients(count($recipients));
            if (! ($afford['ok'] ?? false)) {
                return back()->withInput()->withErrors([
                    'to_address' => (string) ($afford['error'] ?? 'Insufficient SMS balance for this send.'),
                ]);
            }
        }

        $message = app(PropertyCommunicationService::class)->sendNow([
            'created_by_user_id' => (int) $request->user()->id,
            'channel' => $data['channel'],
            'category' => 'general_notice',
            'purpose' => 'manual_message',
            'subject' => $data['subject'] ?? null,
            'body' => (string) $data['body'],
            'template_id' => $data['message_template_id'] ?? null,
            'priority' => 'normal',
            'severity' => 'info',
            'attachments' => $request->file('attachments', []),
        ], $recipients);
        $message->load('recipients');

        if ($message->status === 'scheduled') {
            return back()->with('success', __('Message scheduled for :time.', [
                'time' => optional($message->scheduled_at)->format('Y-m-d H:i') ?? 'later',
            ]));
        }

        $failed = $message->recipients->first(static fn ($recipient) => $recipient->status === 'failed');
        if ($failed) {
            return back()->withInput()->withErrors([
                'body' => (string) ($failed->last_error ?: 'Message delivery failed.'),
            ]);
        }

        $cancelled = $message->recipients->first(static fn ($recipient) => $recipient->status === 'cancelled');
        if ($cancelled) {
            return back()->withInput()->withErrors([
                'to_address' => (string) ($cancelled->opt_out_reason ?: 'Recipient cannot receive this message.'),
            ]);
        }

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

        return (string) $items->first().' (+'.max(0, $items->count() - 1).' more)';
    }

    /**
     * @return list<array{id: int, name: string, channel: string, subject: string, body: string}>
     */
    private function composeTemplates(): array
    {
        return PmMessageTemplate::query()
            ->where('is_active', true)
            ->whereIn('channel', ['sms', 'email'])
            ->orderBy('name')
            ->get(['id', 'name', 'channel', 'subject', 'body'])
            ->map(static fn (PmMessageTemplate $template) => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'channel' => (string) $template->channel,
                'subject' => (string) ($template->subject ?? ''),
                'body' => (string) $template->body,
            ])
            ->values()
            ->all();
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

        return property_view('property.agent.communications.templates', [
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
            'channel' => ['required', 'in:sms,email,whatsapp,system'],
            'category' => ['nullable', 'string', 'max:64'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        PmMessageTemplate::query()->create([
            ...$data,
            'category' => $data['category'] ?? 'general_notice',
            'template_version' => 1,
            'is_active' => true,
        ]);

        return back()->with('success', __('Template saved.'));
    }

    public function destroyTemplate(PmMessageTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('Template deleted.'));
    }

    public function rentTemplates(Request $request): View
    {
        $stageService = app(TenantCommunicationStageService::class);
        $templateService = app(PropertyCommunicationTemplateService::class);
        $bulk = app(BulkSmsService::class);

        $stageKeys = $stageService->editableStageKeys();
        $stageMessages = $stageService->editableStageMessagesForForm();
        $stageLabels = [];
        foreach ($stageKeys as $key) {
            $def = $stageService->stageDefinition($key);
            $stageLabels[$key] = (string) ($def['display_label'] ?? $key);
        }

        $defaultStage = 'D+7';
        $preview = $templateService->previewRentReminder($defaultStage, 'sms');

        return property_view('property.agent.communications.rent_templates', [
            'stats' => [
                ['label' => 'Reminder stages', 'value' => (string) count($stageKeys), 'hint' => 'Editable wording'],
                ['label' => 'SMS cost / segment', 'value' => number_format($bulk->costPerSms(), 2).' '.$bulk->currency(), 'hint' => 'Provider rate'],
            ],
            'columns' => [],
            'tableRows' => [],
            'stageKeys' => $stageKeys,
            'stageMessages' => $stageMessages,
            'stageLabels' => $stageLabels,
            'preview' => $preview,
            'costPerSms' => $bulk->costPerSms(),
            'currency' => $bulk->currency(),
            'canManageCommunications' => $this->canManageCommunications($request),
        ]);
    }

    public function saveRentTemplateMessages(Request $request): RedirectResponse
    {
        $stageService = app(TenantCommunicationStageService::class);
        $rules = ['messages' => ['required', 'array']];
        foreach ($stageService->editableStageKeys() as $stageKey) {
            $rules['messages.'.$stageKey] = ['nullable', 'string', 'max:2000'];
        }

        $data = $request->validate($rules);
        $stageService->saveEditableStageMessages((array) ($data['messages'] ?? []));

        return back()->with('success', __('Rent reminder wording saved.'));
    }

    public function previewRentTemplatesJson(Request $request): JsonResponse
    {
        $stageService = app(TenantCommunicationStageService::class);
        $data = $request->validate([
            'stage_key' => ['required', 'string', 'in:'.implode(',', $stageService->editableStageKeys())],
            'channel' => ['nullable', 'in:sms,email,whatsapp,portal'],
            'stage_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $overrides = [];
        if (array_key_exists('stage_message', $data) && trim((string) $data['stage_message']) !== '') {
            $overrides['stage_message'] = (string) $data['stage_message'];
        }

        $preview = app(PropertyCommunicationTemplateService::class)->previewRentReminder(
            (string) $data['stage_key'],
            (string) ($data['channel'] ?? 'sms'),
            $overrides
        );

        return response()->json($preview);
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

        $canViewBody = $this->canViewMessageBody($request);
        $mapped = $logs->map(function (PmMessageLog $l) use ($canViewBody) {
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
                    $this->maskAddress((string) $l->to_address),
                    $canViewBody ? Str::limit($l->body, 80) : '[MASKED]',
                    $actions,
                ],
            ];
        });

        return property_view('property.agent.communications.bulk', [
            'stats' => $stats,
            'columns' => ['When', 'Channel', 'Status', 'Segment / label', 'Notes', 'Actions'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
            'walletBalance' => app(BulkSmsService::class)->walletBalance(),
            'currency' => app(BulkSmsService::class)->currency(),
            'costPerSms' => app(BulkSmsService::class)->costPerSms(),
            'smsWallet' => $this->smsWalletStatus(),
            'smsTopup' => $this->smsTopupContext($request),
            'filters' => $filters,
            'propertyOptions' => Property::query()->orderBy('name')->get(['id', 'name']),
            'composeTemplates' => $this->composeTemplates(),
        ]);
    }

    public function bulkExport(Request $request)
    {
        if (! ($request->user()->hasPmPermission('communications.export') || $request->user()->hasPmPermission('communications.manage'))) {
            abort(403, 'You do not have permission to export communications.');
        }
        $filters = $request->only(['q', 'channel', 'status', 'from', 'to', 'sort', 'dir']);
        $rows = $this->bulkLogsQuery($filters)->get();
        $canViewBody = $this->canViewMessageBody($request);
        $this->logExportAudit($request, 'bulk', 'csv', (int) $rows->count(), $filters);

        return CsvExport::stream(
            'communications_bulk_'.now()->format('Ymd_His').'.csv',
            ['ID', 'When', 'Channel', 'Status', 'Segment Label', 'Subject', 'Notes', 'By'],
            function () use ($rows, $canViewBody) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        optional($l->created_at)->format('Y-m-d H:i:s'),
                        $l->channel,
                        $l->delivery_status,
                        $this->maskAddress((string) $l->to_address),
                        $l->subject,
                        $canViewBody ? strip_tags((string) $l->body) : '[MASKED]',
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
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $recipients = $data['channel'] === 'sms'
            ? app(BulkSmsService::class)->normalizeRecipientList($data['recipients'])
            : collect(preg_split('/[\s,;]+/', (string) $data['recipients']) ?: [])
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->filter(static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => $data['channel'] === 'sms'
                    ? 'No valid phone numbers found. Use digits only, separated by comma, semicolon, or new lines.'
                    : 'No valid email addresses found. Use comma, semicolon, space, or new lines.',
                ]);
        }

        if ($data['channel'] === 'sms') {
            $afford = app(BulkSmsService::class)->canAffordRecipients(count($recipients));
            if (! ($afford['ok'] ?? false)) {
                return back()->withInput()->withErrors([
                    'recipients' => (string) ($afford['error'] ?? 'Insufficient SMS balance for this bulk send.'),
                ]);
            }
        }

        $payload = [
            'created_by_user_id' => (int) $request->user()->id,
            'channel' => $data['channel'],
            'category' => 'general_notice',
            'purpose' => 'bulk_message',
            'subject' => $data['channel'] === 'email'
                ? (trim((string) ($data['subject'] ?? '')) ?: '[Bulk] '.$data['segment_label'])
                : '[BULK][SMS] '.$data['segment_label'],
            'body' => (string) $data['message'],
            'priority' => 'normal',
            'severity' => 'info',
            'is_bulk' => true,
            'batch_name' => $data['segment_label'],
            'attachments' => $request->file('attachments', []),
        ];

        if (! empty($data['schedule_at'])) {
            $when = Carbon::parse($data['schedule_at']);
            $payload['scheduled_at'] = $when;
            app(PropertyCommunicationService::class)->schedule($payload, $recipients);

            return back()->with('success', __('Bulk message scheduled for '.$when->format('Y-m-d H:i').'.'));
        }

        app(PropertyCommunicationService::class)->sendNow($payload, $recipients);

        return back()->with('success', __('Bulk message queued for delivery.'));
    }

    public function conversationsPage(Request $request): View
    {
        if (! $request->user()->hasPmPermission('communications.manage')) {
            abort(403, 'You do not have permission to access conversations.');
        }

        $rows = PmConversation::query()
            ->with([
                'tenant:id,name',
                'messages' => fn ($q) => $q->orderByDesc('id')->limit(20),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->values();

        return property_view('property.agent.communications.conversations', [
            'rows' => $rows,
        ]);
    }

    public function conversations(Request $request): Response
    {
        if (! $request->user()->hasPmPermission('communications.manage')) {
            abort(403, 'You do not have permission to access conversations.');
        }

        $rows = PmConversation::query()
            ->with(['tenant:id,name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (PmConversation $conversation) {
                return [
                    'id' => $conversation->id,
                    'topic' => $conversation->topic,
                    'category' => $conversation->category,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,
                    'tenant' => $conversation->tenant?->name,
                    'last_message_at' => optional($conversation->last_message_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response(['ok' => true, 'conversations' => $rows]);
    }

    public function showConversation(Request $request, PmConversation $conversation): Response
    {
        if (! $request->user()->hasPmPermission('communications.manage')) {
            abort(403, 'You do not have permission to access conversations.');
        }

        $messages = PmConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (PmConversationMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'channel' => $message->channel,
                'to_address' => $this->maskAddress((string) ($message->to_address ?? '')),
                'body' => $message->body,
                'sent_at' => optional($message->sent_at)->toDateTimeString(),
            ])
            ->values();

        return response([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'topic' => $conversation->topic,
                'category' => $conversation->category,
                'status' => $conversation->status,
            ],
            'messages' => $messages,
        ]);
    }

    public function replyConversation(Request $request, PmConversation $conversation): Response
    {
        if (! $request->user()->hasPmPermission('communications.manage')) {
            abort(403, 'You do not have permission to reply to conversations.');
        }

        $data = $request->validate([
            'channel' => ['required', 'in:sms,email'],
            'to_address' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $toAddress = trim((string) ($data['to_address'] ?? ''));
        if ($toAddress === '') {
            $lastInbound = PmConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', 'inbound')
                ->latest('id')
                ->first();
            $toAddress = trim((string) ($lastInbound?->to_address ?? ''));
        }
        if ($toAddress === '') {
            return response(['ok' => false, 'error' => 'No conversation recipient address found.'], 422);
        }

        app(PropertyCommunicationService::class)->sendNow([
            'created_by_user_id' => (int) $request->user()->id,
            'channel' => $data['channel'],
            'category' => $conversation->category ?: 'general_notice',
            'purpose' => 'conversation_reply',
            'subject' => $data['subject'] ?? $conversation->topic,
            'body' => (string) $data['body'],
            'priority' => $conversation->priority ?: 'normal',
            'severity' => 'info',
            'recipient_type' => 'tenant',
            'recipient_id' => (int) ($conversation->pm_tenant_id ?? 0),
            'attachments' => $request->file('attachments', []),
        ], [$toAddress]);

        $conversation->update(['last_message_at' => now()]);

        return response(['ok' => true]);
    }

    private function messageLogsQuery(array $filters, bool $applyDuplicates = true): Builder
    {
        $filters = $this->normalizeMessageFilters($filters);
        $table = (new PmMessageLog)->getTable();
        $q = PmMessageLog::query()
            ->whereIn($table.'.channel', ['email', 'sms']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search, $table) {
                $b->where($table.'.to_address', 'like', '%'.$search.'%')
                    ->orWhere($table.'.subject', 'like', '%'.$search.'%')
                    ->orWhere($table.'.body', 'like', '%'.$search.'%')
                    ->orWhere($table.'.delivery_error', 'like', '%'.$search.'%');
            });
        }

        $channel = trim((string) ($filters['channel'] ?? ''));
        if ($channel !== '' && in_array($channel, ['email', 'sms'], true)) {
            $q->where($table.'.channel', $channel);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'failed') {
            $q->where(function (Builder $b) use ($table) {
                $b->where(function (Builder $nonSms) use ($table) {
                    $nonSms->where($table.'.channel', '!=', 'sms')
                        ->where($table.'.delivery_status', 'failed');
                })->orWhere(function (Builder $smsFailed) use ($table) {
                    app(SmsHealthService::class)->applyUnresolvedFailedSmsScope($smsFailed, $table);
                });
            });
        } elseif ($status === 'failed_all') {
            $q->where($table.'.delivery_status', 'failed');
        } elseif ($status !== '') {
            $q->where($table.'.delivery_status', $status);
        }

        $sender = trim((string) ($filters['sender'] ?? ''));
        if ($sender !== '' && ctype_digit($sender)) {
            $q->where($table.'.user_id', (int) $sender);
        }

        $hasError = trim((string) ($filters['has_error'] ?? ''));
        if ($hasError === 'yes') {
            $q->where(function (Builder $b) use ($table) {
                $b->where(function (Builder $smsFailed) use ($table) {
                    app(SmsHealthService::class)->applyUnresolvedFailedSmsScope($smsFailed, $table);
                })->orWhere(function (Builder $nonSms) use ($table) {
                    $nonSms->where($table.'.channel', '!=', 'sms')
                        ->where(function (Builder $inner) use ($table) {
                            $inner->where($table.'.delivery_status', 'failed')
                                ->orWhere(function (Builder $err) use ($table) {
                                    $err->whereNotNull($table.'.delivery_error')
                                        ->where($table.'.delivery_error', '!=', '');
                                });
                        });
                });
            });
        } elseif ($hasError === 'no') {
            $q->where(function (Builder $b) use ($table) {
                $b->where(function (Builder $inner) use ($table) {
                    $inner->whereNull($table.'.delivery_status')
                        ->orWhere($table.'.delivery_status', '!=', 'failed');
                })->where(function (Builder $inner) use ($table) {
                    $inner->whereNull($table.'.delivery_error')
                        ->orWhere($table.'.delivery_error', '');
                });
            });
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate($table.'.created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate($table.'.created_at', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'delivery_status', 'channel'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        if ($applyDuplicates) {
            $this->applyDuplicateSendFilter($q, $filters);
        }

        return $q->orderBy($table.'.'.$sort, $dir)->orderByDesc($table.'.id');
    }

    /**
     * Rows where the same recipient received the same subject more than once on the same day.
     */
    private function applyDuplicateSendFilter(Builder $q, array $filters): void
    {
        if (trim((string) ($filters['duplicates'] ?? '')) !== 'yes') {
            return;
        }

        $table = (new PmMessageLog)->getTable();
        $baseFilters = $filters;
        unset($baseFilters['duplicates']);

        $groupSub = $this->messageLogsQuery($baseFilters, applyDuplicates: false)
            ->select(
                "{$table}.to_address",
                "{$table}.subject",
                DB::raw("DATE({$table}.created_at) as duplicate_day"),
                "{$table}.channel",
                DB::raw('COUNT(*) as duplicate_group_count')
            )
            ->groupBy("{$table}.to_address", "{$table}.subject", DB::raw("DATE({$table}.created_at)"), "{$table}.channel")
            ->havingRaw('COUNT(*) > 1');

        $q->joinSub($groupSub, 'dup_groups', function ($join) use ($table) {
            $join->on("{$table}.to_address", '=', 'dup_groups.to_address')
                ->on("{$table}.subject", '=', 'dup_groups.subject')
                ->on("{$table}.channel", '=', 'dup_groups.channel')
                ->whereRaw("DATE({$table}.created_at) = dup_groups.duplicate_day");
        })->addSelect("{$table}.*", 'dup_groups.duplicate_group_count');
    }

    private function messageLogAggregates(Builder $query): object
    {
        $table = (new PmMessageLog)->getTable();

        return (clone $query)
            ->reorder()
            ->toBase()
            ->selectRaw("COUNT(*) as stat_total")
            ->selectRaw("SUM(CASE WHEN {$table}.channel = 'sms' THEN 1 ELSE 0 END) as stat_sms")
            ->selectRaw("SUM(CASE WHEN {$table}.channel = 'email' THEN 1 ELSE 0 END) as stat_email")
            ->selectRaw("SUM(CASE WHEN {$table}.delivery_status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as stat_delivered")
            ->selectRaw("SUM(CASE WHEN {$table}.delivery_status = 'failed' THEN 1 ELSE 0 END) as stat_failed")
            ->first();
    }

    private function notificationLogAggregates(Builder $query): object
    {
        $table = (new PmMessageLog)->getTable();
        $today = now()->toDateString();
        $weekStart = now()->copy()->startOfWeek()->toDateString();
        $aggregateQuery = (clone $query)
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) as stat_total')
            ->selectRaw("SUM(CASE WHEN DATE({$table}.created_at) = ? THEN 1 ELSE 0 END) as stat_today", [$today])
            ->selectRaw("SUM(CASE WHEN DATE({$table}.created_at) >= ? THEN 1 ELSE 0 END) as stat_week", [$weekStart]);

        if (Schema::hasTable('pm_message_reads')) {
            $uid = (int) auth()->id();
            $aggregateQuery->selectRaw(
                "SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM pm_message_reads pmr
                    WHERE pmr.pm_message_log_id = {$table}.id
                      AND pmr.user_id = ?
                ) THEN 1 ELSE 0 END) as stat_unread",
                [$uid]
            );
        } else {
            $aggregateQuery->selectRaw('COUNT(*) as stat_unread');
        }

        return $aggregateQuery->first();
    }

    /**
     * @return list<array{label:string,value:string,hint?:string}>
     */
    private function messageStatsFromAggregates(object $aggregates, array $filters): array
    {
        $total = (int) ($aggregates->stat_total ?? 0);
        $sms = (int) ($aggregates->stat_sms ?? 0);
        $email = (int) ($aggregates->stat_email ?? 0);
        $delivered = (int) ($aggregates->stat_delivered ?? 0);
        $failed = (int) ($aggregates->stat_failed ?? 0);
        $successRate = $total > 0 ? (int) round(($delivered / $total) * 100) : 0;
        $statusFilter = trim((string) ($filters['status'] ?? ''));
        $failedLabel = $statusFilter === 'failed' ? 'Failed (needs action)' : 'Failed';
        $failedHint = $statusFilter === 'failed'
            ? ($failed > 0 ? 'Unresolved SMS failures only' : 'All clear')
            : 'Use the Failed (needs action) quick filter for unresolved SMS only';

        $stats = [
            ['label' => 'Matching sends', 'value' => (string) $total, 'hint' => 'Current filters'],
            ['label' => 'Delivered', 'value' => (string) $delivered, 'hint' => $successRate.'% success rate'],
            ['label' => $failedLabel, 'value' => (string) $failed, 'hint' => $failedHint],
            ['label' => 'SMS / Email', 'value' => $sms.' / '.$email, 'hint' => 'Channel split'],
        ];

        if (trim((string) ($filters['duplicates'] ?? '')) === 'yes') {
            $stats[] = [
                'label' => 'Duplicate rows',
                'value' => (string) $total,
                'hint' => 'Same phone + subject + day (count > 1)',
            ];
        }

        return $stats;
    }

    /**
     * @return array<int, array{internal_stage:string,display_stage:string}>
     */
    private function messageLogPresentations(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $stageService = app(TenantCommunicationStageService::class);
        $presentations = [];

        foreach ($logs as $log) {
            if (! $log instanceof PmMessageLog) {
                continue;
            }

            $parsed = $stageService->parseStaffSubject($log->subject);
            $presentations[(int) $log->id] = [
                'internal_stage' => (string) ($log->internal_stage ?: ($parsed['internal_stage'] ?? '—')),
                'display_stage' => (string) ($log->display_stage ?: ($parsed['display_label'] ?? '—')),
            ];
        }

        return $presentations;
    }

    /**
     * @return array<int, array{can_resend: bool, can_bulk_select: bool, label: string, hint: string}>
     */
    private function resendActionsForMessageLogs(Request $request, Collection $logs): array
    {
        if (! $this->canManageCommunications($request) || $logs->isEmpty()) {
            return [];
        }

        $failedSms = $logs->filter(static function (PmMessageLog $log): bool {
            return $log->channel === 'sms'
                && strtolower((string) ($log->delivery_status ?? '')) === 'failed';
        });

        if ($failedSms->isEmpty()) {
            return [];
        }

        return app(RentReminderEligibilityService::class)->resendActionsForLogs($failedSms);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id:int,name:string}>
     */
    private function cachedMessageSenderOptions(array $filters): array
    {
        return Cache::remember(
            'property.messages.senders.'.md5(json_encode($filters)),
            now()->addMinutes(5),
            fn () => $this->messageSenderOptions($filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function messageFiltersAreScoped(array $filters): bool
    {
        foreach (['q', 'channel', 'status', 'from', 'to', 'period', 'sender', 'has_error', 'duplicates'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function shouldInlineComposeContext(Request $request): bool
    {
        return old('channel') !== null
            || old('body') !== null
            || old('to_address') !== null
            || old('subject') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySmsWalletStatus(): array
    {
        return [
            'balance' => 0.0,
            'cost_per_sms' => 0.0,
            'currency' => app(BulkSmsService::class)->currency(),
            'billing_mode' => 'local',
            'balance_source' => 'local',
            'max_recipients' => 0,
            'can_send_one' => false,
            'status' => 'unknown',
            'headline' => 'SMS balance loads when you open Send message',
            'detail' => null,
            'provider_ok' => false,
            'provider_error' => null,
        ];
    }

    /**
     * Unresolved failed rows for the current filter set (excludes superseded and SMS already delivered).
     */
    private function unresolvedFailedLogCount(array $filters): int
    {
        return app(SmsHealthService::class)->unresolvedFailedCountForCommunicationsFilters(
            $filters,
            fn (array $f) => $this->messageLogsQuery($this->normalizeMessageFilters($f)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id:int,name:string}>
     */
    private function messageSenderOptions(array $filters = []): array
    {
        $userIds = $this->messageLogsQuery($filters, applyDuplicates: false)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeMessageFilters(array $filters): array
    {
        return app(SmsHealthService::class)->normalizeCommunicationsPeriodFilters($filters);
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
            ->where('channel', 'system');

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
        if ($channel !== '' && $channel === 'system') {
            $q->where('channel', 'system');
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

    private function canManageCommunications(Request $request): bool
    {
        return $request->user()->hasPmPermission('communications.manage');
    }

    private function canExportCommunications(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPmPermission('communications.export')
            || $user->hasPmPermission('communications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    private function smsWalletStatus(): array
    {
        return app(BulkSmsService::class)->walletStatusForUi();
    }

    /**
     * @return array<string, mixed>
     */
    private function smsTopupContext(Request $request): array
    {
        /** @var BulkSmsService $bulk */
        $bulk = app(BulkSmsService::class);
        $config = $bulk->topupUiConfig();

        return [
            'config' => $config,
            'can_topup' => ($config['enabled'] ?? false) && $this->canManageCommunications($request),
            'recent' => $bulk->recentProviderTopups(6),
            'default_phone' => $this->defaultTopupPhone($request),
        ];
    }

    private function defaultTopupPhone(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            return '';
        }

        foreach ([$user->phone ?? null, $user->mobile ?? null] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  array{ok?:bool,statistics?:array<string,mixed>}  $statisticsResult
     * @return list<array{label:string,value:string,hint:string}>
     */
    private function providerSmsStatsCards(array $statisticsResult): array
    {
        if (! ($statisticsResult['ok'] ?? false)) {
            return [
                ['label' => 'Provider SMS', 'value' => '—', 'hint' => (string) ($statisticsResult['error'] ?? 'Unavailable')],
            ];
        }

        $stats = (array) ($statisticsResult['statistics'] ?? []);
        $today = (array) ($stats['today'] ?? []);
        $month = (array) ($stats['this_month'] ?? []);
        $currency = app(BulkSmsService::class)->currency();

        return [
            ['label' => 'Sent (today)', 'value' => (string) ((int) ($today['sent'] ?? 0)), 'hint' => 'Provider account'],
            ['label' => 'Delivered (today)', 'value' => (string) ((int) ($today['delivered'] ?? 0)), 'hint' => ''],
            ['label' => 'Failed (today)', 'value' => (string) ((int) ($today['failed'] ?? 0)), 'hint' => ''],
            ['label' => 'Cost (today)', 'value' => number_format((float) ($today['cost'] ?? 0), 2).' '.$currency, 'hint' => ''],
            ['label' => 'Sent (month)', 'value' => (string) ((int) ($month['sent'] ?? 0)), 'hint' => ''],
            ['label' => 'Delivered (month)', 'value' => (string) ((int) ($month['delivered'] ?? 0)), 'hint' => ''],
            ['label' => 'Total sent (all time)', 'value' => (string) ((int) ($stats['total_sent'] ?? 0)), 'hint' => ''],
            ['label' => 'Total cost (all time)', 'value' => number_format((float) ($stats['total_cost'] ?? 0), 2).' '.$currency, 'hint' => ''],
        ];
    }

    private function canViewMessageBody(Request $request): bool
    {
        $user = $request->user();
        return $user->hasPmPermission('communications.view_message_body')
            || $user->hasPmPermission('communications.manage');
    }

    private function maskAddress(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '@')) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');
            if ($local === '') {
                return $value;
            }
            $prefix = substr($local, 0, min(2, strlen($local)));
            return $prefix.str_repeat('*', max(0, strlen($local) - strlen($prefix))).'@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '' || strlen($digits) < 4) {
            return '****';
        }

        return substr($digits, 0, 4).str_repeat('*', max(0, strlen($digits) - 6)).substr($digits, -2);
    }

    private function logExportAudit(Request $request, string $reportType, string $format, int $rowCount, array $filters): void
    {
        PmCommunicationExport::query()->create([
            'exported_by_user_id' => $request->user()->id,
            'report_type' => $reportType,
            'format' => $format,
            'export_reason' => trim((string) $request->query('reason', '')),
            'row_count' => $rowCount,
            'filters' => $filters,
            'exported_at' => now(),
        ]);
    }
}
