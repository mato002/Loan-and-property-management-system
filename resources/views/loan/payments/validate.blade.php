<x-loan-layout>
    <x-loan.page
        title="Validate payment"
        subtitle="Mark an already-processed payment as validated using internal reference or M-Pesa receipt."
    >
        @include('loan.payments.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-lg">
            <form method="post" action="{{ route('loan.payments.validate.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="lookup" class="block text-xs font-semibold text-slate-600 mb-1">Reference or M-Pesa receipt</label>
                    <input id="lookup" name="lookup" value="{{ old('lookup') }}" required autocomplete="off" class="w-full rounded-lg border-slate-200 text-sm font-mono" placeholder="PAY-000001 or QGH8…" />
                    @error('lookup')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-xs text-slate-500">Finds the first matching payment that is not a merged child, then sets validated time and your user as validator.</p>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Validate</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
