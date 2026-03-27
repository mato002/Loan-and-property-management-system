<x-property.workspace
    title="Application #{{ $application->id }}"
    subtitle="Review applicant details, message them, and update status."
    back-route="property.listings.applications"
    :stats="[
        ['label' => 'Status', 'value' => ucfirst($application->status), 'hint' => $application->created_at?->format('Y-m-d') ?? ''],
        ['label' => 'Applicant', 'value' => $application->applicant_name, 'hint' => $application->applicant_phone ?? '—'],
        ['label' => 'Unit', 'value' => $application->unit ? ($application->unit->property->name.' / '.$application->unit->label) : '—', 'hint' => ''],
    ]"
    :columns="[]"
>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Applicant</h3>
            <div class="grid gap-3 sm:grid-cols-2 text-sm">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Name</p>
                    <p class="font-medium text-slate-900 dark:text-white">{{ $application->applicant_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Status</p>
                    <p class="font-medium text-slate-900 dark:text-white">{{ ucfirst($application->status) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Phone</p>
                    <p class="font-medium text-slate-900 dark:text-white">{{ $application->applicant_phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Email</p>
                    <p class="font-medium text-slate-900 dark:text-white">{{ $application->applicant_email ?? '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Unit</p>
                    <p class="font-medium text-slate-900 dark:text-white">
                        {{ $application->unit ? ($application->unit->property->name.' / '.$application->unit->label) : '—' }}
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Notes</p>
                    <p class="text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $application->notes ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Actions</h3>

            @php
                $phone = (string) ($application->applicant_phone ?? '');
                $email = (string) ($application->applicant_email ?? '');
                $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
                $waPhone = $phoneDigits !== '' ? (\Illuminate\Support\Str::startsWith($phoneDigits, '0') ? '254'.ltrim($phoneDigits, '0') : $phoneDigits) : '';
                $waText = rawurlencode('Hello '.$application->applicant_name.', we received your rental application (ID #'.$application->id.').');
                $mailtoHref = $email !== '' ? 'mailto:'.rawurlencode($email).'?subject='.rawurlencode('Rental application #'.$application->id) : '';
                $telHref = $phoneDigits !== '' ? 'tel:'.$phoneDigits : '';
                $waHref = $waPhone !== '' ? 'https://wa.me/'.$waPhone.'?text='.$waText : '';
            @endphp

            <div class="flex flex-wrap gap-2">
                @if ($mailtoHref !== '')
                    <a href="{{ $mailtoHref }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Email applicant</a>
                @endif
                @if ($waHref !== '')
                    <a href="{{ $waHref }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-300 dark:border-emerald-700 px-3 py-2 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20">WhatsApp</a>
                @endif
                @if ($telHref !== '')
                    <a href="{{ $telHref }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Call</a>
                @endif
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Update status</p>
                <form method="post" action="{{ route('property.listings.applications.update', $application) }}" class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['received', 'review', 'approved', 'declined', 'withdrawn'] as $st)
                            <option value="{{ $st }}" @selected($application->status === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save status</button>
                </form>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-700" id="message">
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Send message (SMS / Email)</p>

                @if (session('success'))
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                @endif
                @if ($errors->any())
                    <p class="text-xs text-rose-700 dark:text-rose-300">Could not send. Please fix the highlighted fields.</p>
                @endif


                <p class="text-xs text-slate-500 dark:text-slate-400">
                    If SMS fails with “Insufficient wallet balance”, top up here:
                    <a href="{{ route('loan.bulksms.wallet') }}" target="_blank" rel="noopener" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">SMS wallet</a>.
                </p>

                <form method="post" action="{{ route('property.listings.applications.message', $application) }}" class="space-y-2">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                        <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="sms" @selected(old('channel', 'sms') === 'sms')>SMS</option>
                            <option value="email" @selected(old('channel') === 'email')>Email</option>
                        </select>
                        @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    @if (isset($emailTemplates) && $emailTemplates->isNotEmpty())
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Template (email)</label>
                            <select
                                id="app-email-template"
                                class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                onchange="(() => {
                                    try {
                                        const sel = document.getElementById('app-email-template');
                                        const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
                                        if (!opt) return;
                                        const subj = opt.getAttribute('data-subject') || '';
                                        const body = opt.getAttribute('data-body') || '';
                                        const subjEl = document.querySelector('input[name=subject]');
                                        const bodyEl = document.querySelector('textarea[name=body]');
                                        if (subjEl && subj) subjEl.value = subj;
                                        if (bodyEl && body) bodyEl.value = body;
                                    } catch (e) {}
                                })()"
                            >
                                <option value="">— Select template —</option>
                                @foreach ($emailTemplates as $t)
                                    <option value="{{ $t->id }}" data-subject="{{ $t->subject ?? '' }}" data-body="{{ $t->body }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Selecting a template fills subject + body.</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Subject (email)</label>
                        <input type="text" name="subject" value="{{ old('subject', 'Rental application #'.$application->id) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('subject')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Message</label>
                        <textarea name="body" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" required>{{ old('body', 'Hello '.$application->applicant_name.', we received your rental application (ID #'.$application->id.').') }}</textarea>
                        @error('body')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Send</button>
                </form>

                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Sent messages are logged under <span class="font-semibold">Communications → SMS / email</span>.
                </p>
            </div>
        </div>
    </div>
</x-property.workspace>

