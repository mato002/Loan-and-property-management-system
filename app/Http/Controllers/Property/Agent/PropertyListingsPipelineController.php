<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmListingApplication;
use App\Models\PmListingLead;
use App\Models\PmMessageLog;
use App\Models\PmMessageTemplate;
use App\Models\PropertyUnit;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use App\Services\BulkSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyListingsPipelineController extends Controller
{
    public function leads(): View
    {
        $filters = request()->only(['q', 'stage', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $leads = $this->leadsQuery($filters)->limit(500)->get();

        $stats = [
            ['label' => 'Leads', 'value' => (string) $leads->count(), 'hint' => ''],
            ['label' => 'New', 'value' => (string) $leads->where('stage', 'new')->count(), 'hint' => ''],
            ['label' => 'Won', 'value' => (string) $leads->where('stage', 'won')->count(), 'hint' => ''],
        ];

        $rows = $leads->map(function (PmListingLead $l) {
            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<form method="POST" action="'.route('property.listings.leads.update', $l).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="stage" value="contacted" />'.
                '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Contacted</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.leads.update', $l).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="stage" value="won" />'.
                '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Mark won</button>'.
                '</form>'.
                '</div>'
            );

            return [
                $l->name,
                $l->phone ?? '—',
                $l->email ?? '—',
                $l->source ?? '—',
                ucfirst($l->stage),
                $l->unit ? $l->unit->property->name.'/'.$l->unit->label : '—',
                $l->updated_at->format('Y-m-d'),
                $actions,
            ];
        })->all();

        return view('property.agent.listings.leads', [
            'stats' => $stats,
            'columns' => ['Name', 'Phone', 'Email', 'Source', 'Stage', 'Unit', 'Updated', 'Actions'],
            'tableRows' => $rows,
            'leads' => $leads,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'filters' => $filters,
        ]);
    }

    public function leadsExport(Request $request)
    {
        $filters = $request->only(['q', 'stage', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->leadsQuery($filters)->get();

        return CsvExport::stream(
            'property_leads_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Name', 'Phone', 'Email', 'Source', 'Stage', 'Unit', 'Updated At', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $l) {
                    yield [
                        $l->id,
                        $l->name,
                        $l->phone,
                        $l->email,
                        $l->source,
                        $l->stage,
                        $l->unit ? ($l->unit->property->name.'/'.$l->unit->label) : null,
                        optional($l->updated_at)->format('Y-m-d H:i:s'),
                        $l->notes,
                    ];
                }
            }
        );
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'stage' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
        ]);

        PmListingLead::query()->create([
            ...$data,
            'stage' => $data['stage'] ?? 'new',
        ]);

        return back()->with('success', __('Lead saved.'));
    }

    public function updateLeadStage(Request $request, PmListingLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'max:32'],
        ]);

        $lead->update($data);

        return back()->with('success', __('Stage updated.'));
    }

    private function leadsQuery(array $filters): Builder
    {
        $q = PmListingLead::query()->with('unit.property');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('source', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        $stage = trim((string) ($filters['stage'] ?? ''));
        if ($stage !== '') {
            $q->where('stage', $stage);
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0) {
            $q->where('property_unit_id', $unitId);
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
        $allowedSort = ['id', 'created_at', 'updated_at', 'stage', 'name'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    public function applications(): View
    {
        $filters = request()->only(['q', 'status', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $apps = $this->applicationsQuery($filters)
            ->limit(500)
            ->get();

        $stats = [
            ['label' => 'Applications', 'value' => (string) $apps->count(), 'hint' => ''],
            ['label' => 'In review', 'value' => (string) $apps->where('status', 'review')->count(), 'hint' => ''],
            ['label' => 'Approved', 'value' => (string) $apps->where('status', 'approved')->count(), 'hint' => ''],
        ];

        $rows = $apps->map(function (PmListingApplication $a) {
            $phone = (string) ($a->applicant_phone ?? '');
            $email = (string) ($a->applicant_email ?? '');
            $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
            $waPhone = $phoneDigits !== '' ? (Str::startsWith($phoneDigits, '0') ? '254'.ltrim($phoneDigits, '0') : $phoneDigits) : '';
            $waText = rawurlencode('Hello '.$a->applicant_name.', we received your rental application (ID #'.$a->id.').');

            $viewHref = route('property.listings.applications.show', $a, absolute: false);
            $msgHref = $viewHref.'#message';
            $mailtoHref = $email !== '' ? 'mailto:'.rawurlencode($email).'?subject='.rawurlencode('Rental application #'.$a->id) : '';
            $telHref = $phoneDigits !== '' ? 'tel:'.$phoneDigits : '';
            $waHref = $waPhone !== '' ? 'https://wa.me/'.$waPhone.'?text='.$waText : '';

            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<a href="'.$viewHref.'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">View</a>'.
                '<a href="'.$msgHref.'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Message</a>'.
                ($mailtoHref !== '' ? '<a href="'.$mailtoHref.'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Email</a>' : '').
                ($waHref !== '' ? '<a href="'.$waHref.'" target="_blank" rel="noopener" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">WhatsApp</a>' : '').
                ($telHref !== '' ? '<a href="'.$telHref.'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Call</a>' : '').
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="review" />'.
                '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Mark review</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="approved" />'.
                '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Approve</button>'.
                '</form>'.
                '<form method="POST" action="'.route('property.listings.applications.update', $a).'" class="inline-flex">'.csrf_field().method_field('PATCH').
                '<input type="hidden" name="status" value="declined" />'.
                '<button type="submit" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Decline</button>'.
                '</form>'.
                '</div>'
            );

            return [
                '#'.$a->id,
                $a->applicant_name,
                $a->applicant_phone ?? '—',
                $a->applicant_email ?? '—',
                $a->unit ? $a->unit->property->name.'/'.$a->unit->label : '—',
                ucfirst($a->status),
                $a->created_at->format('Y-m-d'),
                $actions,
            ];
        })->all();

        return view('property.agent.listings.applications', [
            'stats' => $stats,
            'columns' => ['#', 'Applicant', 'Phone', 'Email', 'Unit', 'Status', 'Submitted', 'Actions'],
            'tableRows' => $rows,
            'applications' => $apps,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'filters' => $filters,
        ]);
    }

    public function applicationsExport(Request $request)
    {
        $filters = $request->only(['q', 'status', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->applicationsQuery($filters)->get();

        return CsvExport::stream(
            'property_applications_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Applicant', 'Phone', 'Email', 'Unit', 'Status', 'Submitted At', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $a) {
                    yield [
                        $a->id,
                        $a->applicant_name,
                        $a->applicant_phone,
                        $a->applicant_email,
                        $a->unit ? ($a->unit->property->name.'/'.$a->unit->label) : null,
                        $a->status,
                        optional($a->created_at)->format('Y-m-d H:i:s'),
                        $a->notes,
                    ];
                }
            }
        );
    }

    private function applicationsQuery(array $filters): Builder
    {
        $q = PmListingApplication::query()
            ->with('unit.property');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('applicant_name', 'like', '%'.$search.'%')
                    ->orWhere('applicant_phone', 'like', '%'.$search.'%')
                    ->orWhere('applicant_email', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $q->where('status', $status);
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0) {
            $q->where('property_unit_id', $unitId);
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
        $allowedSort = ['id', 'created_at', 'status', 'applicant_name'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    public function showApplication(PmListingApplication $application): View
    {
        $application->loadMissing('unit.property');

        return view('property.agent.listings.application_show', [
            'application' => $application,
            'emailTemplates' => PmMessageTemplate::query()
                ->where('channel', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function sendApplicationMessage(Request $request, PmListingApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $to = null;

        if ($data['channel'] === 'sms') {
            $to = (string) ($application->applicant_phone ?? '');
            /** @var BulkSmsService $bulkSms */
            $bulkSms = app(BulkSmsService::class);
            $phones = $bulkSms->normalizeRecipientList($to);
            if ($phones === []) {
                return back()->withInput()->withErrors([
                    'channel' => 'Applicant has no valid phone number for SMS.',
                ]);
            }

            $result = $bulkSms->sendNow($data['body'], $phones, $request->user()->id, null);
            if (! ($result['ok'] ?? false)) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    'channel' => 'sms',
                    'to_address' => (string) $to,
                    'subject' => null,
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
            $to = (string) ($application->applicant_email ?? '');
            if (trim($to) === '') {
                return back()->withInput()->withErrors([
                    'channel' => 'Applicant has no email address.',
                ]);
            }

            try {
                $subject = (string) ($data['subject'] ?? 'Rental application #'.$application->id);
                $body = (string) $data['body'];
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            } catch (\Throwable $e) {
                PmMessageLog::query()->create([
                    'user_id' => $request->user()->id,
                    'channel' => 'email',
                    'to_address' => (string) $to,
                    'subject' => (string) ($data['subject'] ?? null),
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
            'to_address' => (string) $to,
            'subject' => $data['channel'] === 'email' ? (string) ($data['subject'] ?? null) : null,
            'body' => $data['body'],
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return back()->with('success', $data['channel'] === 'sms' ? 'SMS sent.' : 'Email sent.');
    }

    public function storeApplication(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_phone' => ['nullable', 'string', 'max:64'],
            'applicant_email' => ['nullable', 'email', 'max:255'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PmListingApplication::query()->create([
            ...$data,
            'status' => $data['status'] ?? 'received',
        ]);

        return back()->with('success', __('Application recorded.'));
    }

    public function updateApplicationStatus(Request $request, PmListingApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
        ]);

        $application->update($data);

        return back()->with('success', __('Status updated.'));
    }
}
