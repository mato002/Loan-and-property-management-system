<x-loan-layout>
    <x-loan.page
        title="Send or schedule SMS"
        subtitle="Recipients are charged from the SMS wallet when messages are sent (immediate or when a schedule runs)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.wallet') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Wallet: {{ number_format((float) $walletBalance, 2) }} {{ $currency }}
            </a>
            <a href="{{ route('loan.bulksms.templates.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Manage templates
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <form method="post" action="{{ route('loan.bulksms.compose.store') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="recipients" class="block text-sm font-medium text-slate-700">Recipients</label>
                            <p class="text-xs text-slate-500 mt-0.5">One number per line, or comma / semicolon separated. Digits only (9+ per number).</p>
                            <textarea id="recipients" name="recipients" rows="6" required
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('recipients') }}</textarea>
                            @error('recipients')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-slate-700">Message</label>
                            <textarea id="message" name="message" rows="5" maxlength="1000" required
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('message', $prefillBody) }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="sms_template_id" class="block text-sm font-medium text-slate-700">Link template (optional)</label>
                                <select id="sms_template_id" name="sms_template_id"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">
                                    <option value="">— None —</option>
                                    @foreach ($templates as $t)
                                        <option value="{{ $t->id }}" @selected((string) old('sms_template_id', $prefillTemplateId) === (string) $t->id)>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-slate-500 mt-1">For reporting only; paste or edit the message above.</p>
                            </div>
                            <div>
                                <label for="schedule_at" class="block text-sm font-medium text-slate-700">Schedule send (optional)</label>
                                <input type="datetime-local" id="schedule_at" name="schedule_at" value="{{ old('schedule_at') }}"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                                @error('schedule_at')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-slate-500 mt-1">Leave empty to send now (wallet charged immediately).</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                                Submit
                            </button>
                            <a href="{{ route('loan.bulksms.logs') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                View logs
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-slate-800">Pricing</h2>
                    <p class="text-sm text-slate-600 mt-2">
                        <span class="tabular-nums font-medium text-slate-900">{{ number_format($costPerSms, 2) }} {{ $currency }}</span>
                        per SMS segment (configurable via <code class="text-xs bg-slate-100 px-1 rounded">BULKSMS_COST_PER_SMS</code>).
                    </p>
                    <p class="text-xs text-slate-500 mt-3">Delivery is recorded in-app. Connect a real SMS provider in code when you are ready.</p>
                </div>
                <div class="bg-[#2f4f4f]/5 border border-[#2f4f4f]/20 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-[#264040]">Start from template</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($templates->take(8) as $t)
                            <li>
                                <a href="{{ route('loan.bulksms.compose', ['template' => $t->id]) }}" class="text-indigo-600 hover:underline">{{ $t->name }}</a>
                            </li>
                        @empty
                            <li class="text-slate-500">No templates yet. <a href="{{ route('loan.bulksms.templates.create') }}" class="text-indigo-600 font-medium hover:underline">Create one</a>.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
