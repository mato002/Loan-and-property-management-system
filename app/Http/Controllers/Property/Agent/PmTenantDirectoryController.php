<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\UserModuleAccess;
use App\Mail\TenantPortalCredentialsMail;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\Property\PropertyMoney;

class PmTenantDirectoryController extends Controller
{
    public function directory(): View
    {
        return view('property.agent.tenants.directory', $this->tenantListPayload(
            pageTitle: 'Tenant list',
            pageSubtitle: 'Operational directory — add tenants here, then leases and billing.',
            showTenantForm: true,
        ));
    }

    public function profiles(): View
    {
        return view('property.agent.tenants.directory', $this->tenantListPayload(
            pageTitle: 'Tenant profiles',
            pageSubtitle: 'Same roster — future: per-tenant profile, documents, and timeline.',
            showTenantForm: false,
        ));
    }

    public function importForm(): View
    {
        return view('property.agent.tenants.import', [
            'expectedColumns' => ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'],
            'lastImportStats' => session('tenant_import_stats'),
            'lastImportErrors' => session('tenant_import_errors', []),
        ]);
    }

    public function importTemplate(): Response
    {
        $csv = implode(',', ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'])."\n"
            ."John Doe,+254700000000,john@example.com,ID123,normal,Notes here\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tenant_import_template.csv"',
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);

        $path = $data['file']->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->with('error', 'Upload failed. Please try again.');
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return back()->with('error', 'Could not read uploaded file.');
        }

        $header = fgetcsv($fh);
        if (! is_array($header) || count($header) === 0) {
            fclose($fh);
            return back()->with('error', 'CSV is empty or header row is missing.');
        }

        $normalize = static fn ($v) => mb_strtolower(trim((string) $v));
        $header = array_map($normalize, $header);

        $expected = ['name', 'phone', 'email', 'national_id', 'risk_level', 'notes'];
        $missing = array_values(array_diff($expected, $header));
        if (count($missing) > 0) {
            fclose($fh);
            return back()->with('error', 'Missing required columns: '.implode(', ', $missing));
        }

        $colIndex = [];
        foreach ($header as $i => $col) {
            $colIndex[$col] = $i;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1; // header row

        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;

            // Skip blank lines
            if (! is_array($row) || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                $skipped++;
                continue;
            }

            $name = trim((string) ($row[$colIndex['name']] ?? ''));
            if ($name === '') {
                $errors[] = "Row {$rowNum}: name is required.";
                continue;
            }

            $emailRaw = trim((string) ($row[$colIndex['email']] ?? ''));
            $email = $emailRaw !== '' ? Str::lower($emailRaw) : null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: invalid email '{$emailRaw}'.";
                continue;
            }

            $risk = $normalize($row[$colIndex['risk_level']] ?? 'normal');
            if ($risk === '') {
                $risk = 'normal';
            }
            if (! in_array($risk, ['normal', 'medium', 'high'], true)) {
                $errors[] = "Row {$rowNum}: risk_level must be normal|medium|high.";
                continue;
            }

            $payload = [
                'name' => $name,
                'phone' => ($v = trim((string) ($row[$colIndex['phone']] ?? ''))) !== '' ? $v : null,
                'email' => $email,
                'national_id' => ($v = trim((string) ($row[$colIndex['national_id']] ?? ''))) !== '' ? $v : null,
                'risk_level' => $risk,
                'notes' => ($v = trim((string) ($row[$colIndex['notes']] ?? ''))) !== '' ? $v : null,
            ];

            try {
                $tenant = null;
                if ($email !== null) {
                    $tenant = PmTenant::query()->where('email', $email)->first();
                }

                if ($tenant) {
                    $tenant->update($payload);
                    $updated++;
                } else {
                    PmTenant::query()->create($payload);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);

        $stats = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => count($errors),
        ];

        return redirect()
            ->route('property.tenants.import')
            ->with('success', "Import finished. Created {$created}, updated {$updated}.")
            ->with('tenant_import_stats', $stats)
            ->with('tenant_import_errors', array_slice($errors, 0, 200));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantListPayload(string $pageTitle, string $pageSubtitle, bool $showTenantForm): array
    {
        $tenants = PmTenant::query()
            ->withCount(['leases', 'invoices'])
            ->withMax('leases', 'end_date')
            ->orderBy('name')
            ->get();

        $stats = [
            ['label' => 'Tenants', 'value' => (string) $tenants->count(), 'hint' => 'Records'],
            ['label' => 'With portal login', 'value' => (string) $tenants->whereNotNull('user_id')->count(), 'hint' => 'Linked user'],
            ['label' => 'High risk flagged', 'value' => (string) $tenants->where('risk_level', 'high')->count(), 'hint' => 'Manual'],
            ['label' => 'Total leases', 'value' => (string) $tenants->sum('leases_count'), 'hint' => 'Linked'],
        ];

        $rows = $tenants->map(function (PmTenant $t) {
            $leaseEnd = $t->leases_max_end_date
                ? (string) \Illuminate\Support\Carbon::parse((string) $t->leases_max_end_date)->format('Y-m-d')
                : '—';

            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<a href="'.route('property.tenants.show', $t).'" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.tenants.edit', $t).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.tenants.leases').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Leases</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.tenants.notices').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Notices</a>'.
                '</div>'
            );

            return [
                $t->name,
                $t->phone ?? '—',
                $t->email ?? '—',
                $t->national_id ?? '—',
                (string) $t->leases_count,
                $leaseEnd,
                ucfirst($t->risk_level),
                $actions,
            ];
        })->all();

        return [
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'showTenantForm' => $showTenantForm,
            'stats' => $stats,
            'columns' => ['Tenant', 'Phone', 'Email', 'ID / ref', 'Leases', 'Lease end', 'Risk', 'Actions'],
            'tableRows' => $rows,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $createPortal = $request->boolean('create_portal_login');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => $createPortal
                ? ['required', 'email', 'max:255', Rule::unique(User::class, 'email')]
                : ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'risk_level' => ['required', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_portal_login' => ['sometimes', 'boolean'],
        ]);

        $plainPassword = null;
        $user = null;

        if ($createPortal) {
            $plainPassword = Str::password(14, symbols: false);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'tenant',
                'email_verified_at' => now(),
            ]);

            // Auto-approve tenant accounts for the Property module so their portal login works immediately.
            if (Schema::hasTable('user_module_accesses')) {
                UserModuleAccess::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'module' => 'property',
                    ],
                    [
                        'status' => UserModuleAccess::STATUS_APPROVED,
                        'approved_at' => now(),
                    ]
                );
            }
        }

        $tenant = PmTenant::query()->create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $createPortal ? Str::lower($data['email']) : ($data['email'] ?? null),
            'national_id' => $data['national_id'] ?? null,
            'risk_level' => $data['risk_level'],
            'notes' => $data['notes'] ?? null,
        ]);

        $nextSteps = [
            'title' => 'Tenant saved',
            'message' => 'Step 1 of 4 complete. Next, allocate a vacant unit, then raise the first rent bill and record the opening payment.',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'national_id' => $tenant->national_id,
            ],
            'actions' => [
                [
                    'label' => 'Allocate vacant unit (create lease)',
                    'href' => route('property.tenants.leases', ['pm_tenant_id' => $tenant->id], absolute: false),
                    'kind' => 'primary',
                    'icon' => 'fa-solid fa-key',
                    'turbo_frame' => 'property-main',
                ],
                [
                    'label' => 'Back to tenant list',
                    'href' => route('property.tenants.directory', absolute: false),
                    'kind' => 'secondary',
                    'icon' => 'fa-solid fa-users',
                    'turbo_frame' => 'property-main',
                ],
            ],
        ];

        if ($user !== null && $plainPassword !== null) {
            try {
                Mail::to($user->email)->send(new TenantPortalCredentialsMail(
                    tenantName: $data['name'],
                    email: $user->email,
                    plainPassword: $plainPassword,
                    loginUrl: url(route('property.tenant.login', [], false)),
                    tenantHomeUrl: url(route('property.tenant.home', [], false)),
                ));
            } catch (\Throwable $e) {
                Log::error('tenant_portal_welcome_mail_failed', [
                    'message' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                return back()
                    ->with('success', 'Tenant saved with portal login.')
                    ->with('next_steps', $nextSteps)
                    ->with('error', 'Email could not be sent — share the login link and a password reset manually, or check your mail configuration (MAIL_* in .env).');
            }

            return back()
                ->with('success', 'Tenant saved. Portal login details were emailed.')
                ->with('next_steps', $nextSteps);
        }

        return back()
            ->with('success', 'Tenant saved.')
            ->with('next_steps', $nextSteps);
    }

    public function storeJson(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $tenant = PmTenant::query()->create([
            'user_id' => null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? Str::lower($data['email']) : null,
            'national_id' => null,
            'risk_level' => 'normal',
            'notes' => null,
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $tenant->id,
                'label' => $tenant->name.($tenant->phone ? ' ('.$tenant->phone.')' : ''),
            ],
            'message' => 'Tenant created.',
        ]);
    }

    public function show(PmTenant $tenant): View
    {
        $tenant->load([
            'leases' => fn ($q) => $q->with(['units.property'])->orderByDesc('start_date'),
            'invoices' => fn ($q) => $q->latest('issue_date')->limit(10),
        ])->loadCount(['leases', 'invoices']);

        $leaseRows = $tenant->leases->map(function ($lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');

            return [
                'id' => $lease->id,
                'status' => (string) $lease->status,
                'start' => $lease->start_date?->format('Y-m-d') ?? '—',
                'end' => $lease->end_date?->format('Y-m-d') ?? '—',
                'rent' => (float) $lease->monthly_rent,
                'units' => $units !== '' ? $units : '—',
            ];
        });

        $invoiceTotal = (float) $tenant->invoices->sum('amount');
        $invoicePaid = (float) $tenant->invoices->sum('amount_paid');
        $invoiceDue = max(0.0, $invoiceTotal - $invoicePaid);

        return view('property.agent.tenants.show', [
            'tenant' => $tenant,
            'leaseRows' => $leaseRows,
            'invoiceTotals' => [
                'total' => $invoiceTotal,
                'paid' => $invoicePaid,
                'due' => $invoiceDue,
            ],
        ]);
    }

    public function statement(Request $request, PmTenant $tenant): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $embed = $request->boolean('embed');

        $from = isset($validated['from']) ? trim((string) $validated['from']) : '';
        $to = isset($validated['to']) ? trim((string) $validated['to']) : '';

        $fromDate = $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        $invoiceQuery = PmInvoice::query()
            ->with(['unit.property'])
            ->where('pm_tenant_id', $tenant->id)
            ->when($fromDate, fn ($q) => $q->whereDate('issue_date', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('issue_date', '<=', $toDate->toDateString()));

        $paymentQuery = PmPayment::query()
            ->with(['allocations.invoice'])
            ->where('pm_tenant_id', $tenant->id)
            ->when($fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('paid_at', '<=', $toDate->toDateString()));

        $invoices = $invoiceQuery->orderBy('issue_date')->orderBy('id')->get();
        $payments = $paymentQuery->orderBy('paid_at')->orderBy('id')->get();

        $openingInvoices = 0.0;
        $openingPayments = 0.0;
        if ($fromDate) {
            $openingInvoices = (float) PmInvoice::query()
                ->where('pm_tenant_id', $tenant->id)
                ->whereDate('issue_date', '<', $fromDate->toDateString())
                ->sum('amount');

            $openingPayments = (float) PmPayment::query()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereDate('paid_at', '<', $fromDate->toDateString())
                ->sum('amount');
        }

        $openingBalance = $openingInvoices - $openingPayments;

        $entries = collect();

        foreach ($invoices as $invoice) {
            $label = $invoice->invoice_no ?: 'INV-'.$invoice->id;
            $unitLabel = trim(($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—'));

            $entries->push([
                'date' => $invoice->issue_date?->toDateString(),
                'timestamp' => $invoice->issue_date?->startOfDay()?->timestamp ?? 0,
                'type' => 'Invoice',
                'ref' => $label,
                'description' => ($invoice->invoice_type ? strtoupper((string) $invoice->invoice_type) : 'CHARGE').($unitLabel !== '— / —' ? ' · '.$unitLabel : ''),
                'debit' => (float) $invoice->amount,
                'credit' => 0.0,
                'payment_id' => null,
            ]);
        }

        foreach ($payments as $payment) {
            $label = $payment->external_ref ?: 'PAY-'.$payment->id;
            $allocTo = $payment->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
            $desc = strtoupper((string) $payment->channel);
            if ($allocTo !== '') {
                $desc .= ' · Alloc: '.$allocTo;
            }
            $desc .= ' · '.ucfirst((string) $payment->status);

            $isCompleted = $payment->status === PmPayment::STATUS_COMPLETED;

            $entries->push([
                'date' => $payment->paid_at?->toDateString(),
                'timestamp' => $payment->paid_at?->timestamp ?? 0,
                'type' => 'Payment',
                'ref' => $label,
                'description' => $desc,
                'debit' => 0.0,
                'credit' => $isCompleted ? (float) $payment->amount : 0.0,
                'payment_id' => $isCompleted ? $payment->id : null,
                'status' => ucfirst((string) $payment->status),
            ]);
        }

        $entries = $entries
            ->sortBy([
                ['timestamp', 'asc'],
                ['type', 'asc'],
            ])
            ->values();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = [];
        if ($fromDate) {
            $rows[] = [
                $fromDate->toDateString(),
                'Opening balance',
                '—',
                'B/F',
                '—',
                '—',
                PropertyMoney::kes($openingBalance),
                '—',
                '—',
            ];
        }

        foreach ($entries as $e) {
            $debit = (float) $e['debit'];
            $credit = (float) $e['credit'];
            $totalDebit += $debit;
            $totalCredit += $credit;
            $running += $debit - $credit;

            $actions = '—';
            if ($e['payment_id']) {
                $actions = new HtmlString(
                    '<a href="'.route('property.payments.receipt.show', ['payment' => $e['payment_id']], false).'" data-turbo="false" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700 font-medium">Receipt</a> '.
                    '<span class="text-slate-300">|</span> '.
                    '<a href="'.route('property.payments.receipt.download', ['payment' => $e['payment_id']], false).'" data-turbo="false" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700 font-medium">Download</a>'
                );
            }

            $rows[] = [
                $e['date'] ?: '—',
                (string) $e['type'],
                (string) $e['ref'],
                (string) $e['description'],
                $debit > 0 ? PropertyMoney::kes($debit) : '—',
                $credit > 0 ? PropertyMoney::kes($credit) : '—',
                PropertyMoney::kes($running),
                (string) ($e['status'] ?? ($e['type'] === 'Invoice' ? 'Issued' : '—')),
                $actions,
            ];
        }

        $stats = [
            ['label' => 'Tenant', 'value' => $tenant->name, 'hint' => 'Statement owner'],
            ['label' => 'Transactions', 'value' => (string) count($rows), 'hint' => 'Invoices + payments'],
            ['label' => 'Total debit', 'value' => PropertyMoney::kes($totalDebit), 'hint' => 'Charges'],
            ['label' => 'Total credit', 'value' => PropertyMoney::kes($totalCredit), 'hint' => 'Payments'],
            ['label' => 'Closing balance', 'value' => PropertyMoney::kes($running), 'hint' => 'Debit - credit (+ opening)'],
        ];

        $tenant->loadMissing([
            'leases' => fn ($q) => $q->with(['units.property'])->orderByDesc('start_date'),
        ]);

        $leaseSummary = $tenant->leases->map(function ($lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
            return [
                'start' => $lease->start_date?->format('Y-m-d') ?? '—',
                'end' => $lease->end_date?->format('Y-m-d') ?? '—',
                'rent' => PropertyMoney::kes((float) ($lease->monthly_rent ?? 0)),
                'units' => $units !== '' ? $units : '—',
                'status' => (string) ($lease->status ?? '—'),
            ];
        })->all();

        $invoiceSummary = [
            'count' => $invoices->count(),
            'total' => (float) $invoices->sum('amount'),
            'paid' => (float) $invoices->sum('amount_paid'),
            'outstanding' => (float) $invoices->sum(fn (PmInvoice $i) => max(0.0, (float) $i->amount - (float) $i->amount_paid)),
            'openCount' => $invoices->filter(fn (PmInvoice $i) => (float) $i->amount_paid < (float) $i->amount)->count(),
        ];

        $paymentSummary = [
            'count' => $payments->count(),
            'completedCount' => $payments->where('status', PmPayment::STATUS_COMPLETED)->count(),
            'pendingCount' => $payments->where('status', PmPayment::STATUS_PENDING)->count(),
            'failedCount' => $payments->where('status', PmPayment::STATUS_FAILED)->count(),
            'completedAmount' => (float) $payments->where('status', PmPayment::STATUS_COMPLETED)->sum('amount'),
            'pendingAmount' => (float) $payments->where('status', PmPayment::STATUS_PENDING)->sum('amount'),
        ];

        return view($embed ? 'property.agent.tenants.statement_embed' : 'property.agent.tenants.statement', [
            'tenant' => $tenant,
            'stats' => $stats,
            'columns' => ['Date', 'Type', 'Ref', 'Description', 'Debit', 'Credit', 'Balance', 'Status', 'Receipt'],
            'tableRows' => $rows,
            'filters' => ['from' => $from !== '' ? $from : null, 'to' => $to !== '' ? $to : null],
            'leaseSummary' => $leaseSummary,
            'invoiceSummary' => $invoiceSummary,
            'paymentSummary' => $paymentSummary,
            'embed' => $embed,
        ]);
    }

    public function edit(PmTenant $tenant): View
    {
        $tenant->loadCount('leases');

        return view('property.agent.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'risk_level' => ['required', 'in:normal,medium,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Tenant updated.');
    }
}
