<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMessageLog;
use App\Models\PmMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyCommunicationsWebController extends Controller
{
    public function messages(): View
    {
        $logs = PmMessageLog::query()->with('user')->orderByDesc('id')->limit(200)->get();

        $stats = [
            ['label' => 'Logged sends', 'value' => (string) $logs->count(), 'hint' => 'This list'],
            ['label' => 'Email', 'value' => (string) $logs->where('channel', 'email')->count(), 'hint' => ''],
            ['label' => 'SMS', 'value' => (string) $logs->where('channel', 'sms')->count(), 'hint' => ''],
        ];

        $mapped = $logs->map(fn (PmMessageLog $l) => [
            'filter' => mb_strtolower($l->channel.' '.$l->to_address.' '.strip_tags($l->body)),
            'cells' => [
                $l->created_at->format('Y-m-d H:i'),
                strtoupper($l->channel),
                $l->to_address,
                $l->subject ?? '—',
                Str::limit(strip_tags($l->body), 48),
                $l->user->name ?? '—',
            ],
        ]);

        return view('property.agent.communications.messages', [
            'stats' => $stats,
            'columns' => ['When', 'Channel', 'To', 'Subject', 'Preview', 'By'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
        ]);
    }

    public function logMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'to_address' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        PmMessageLog::query()->create([
            'user_id' => $request->user()->id,
            ...$data,
        ]);

        return back()->with('success', __('Message logged (provider integration not configured — nothing was sent externally).'));
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

    public function bulk(): View
    {
        $logs = PmMessageLog::query()->where('subject', 'like', '[BULK]%')->orderByDesc('id')->limit(100)->get();

        $stats = [
            ['label' => 'Bulk jobs logged', 'value' => (string) $logs->count(), 'hint' => ''],
        ];

        $mapped = $logs->map(fn (PmMessageLog $l) => [
            'filter' => mb_strtolower($l->to_address.' '.$l->body),
            'cells' => [
                $l->created_at->format('Y-m-d H:i'),
                $l->to_address,
                Str::limit($l->body, 80),
            ],
        ]);

        return view('property.agent.communications.bulk', [
            'stats' => $stats,
            'columns' => ['When', 'Segment / label', 'Notes'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'tableRowFilters' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
        ]);
    }

    public function logBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'segment_label' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:5000'],
        ]);

        PmMessageLog::query()->create([
            'user_id' => $request->user()->id,
            'channel' => 'email',
            'to_address' => $data['segment_label'],
            'subject' => '[BULK] '.$data['segment_label'],
            'body' => $data['notes'],
        ]);

        return back()->with('success', __('Bulk campaign recorded — connect SMS/email gateway to deliver.'));
    }
}
