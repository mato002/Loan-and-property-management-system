<x-guest-layout :title="__('Choose module').' — '.config('app.name')">
    <div class="mt-2 mb-6">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Choose module') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
            {{ __('Select the portal you want to enter. If a button is disabled, your account is not approved for that module.') }}
        </p>
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('choose_module.activate', ['module' => 'property']) }}">
            @csrf
            <button
                type="submit"
                class="w-full rounded-2xl border px-5 py-4 text-left font-bold transition
                    {{ $propertyApproved ? 'border-indigo-200 bg-indigo-50 text-indigo-800 hover:bg-indigo-100' : 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed' }}"
                @disabled(! $propertyApproved)
            >
                <span class="block text-sm uppercase tracking-[0.14em] opacity-80">Property module</span>
                <span class="block text-xl mt-1">Login for Property Management</span>
            </button>
        </form>

        <form method="POST" action="{{ route('choose_module.activate', ['module' => 'loan']) }}">
            @csrf
            <button
                type="submit"
                class="w-full rounded-2xl border px-5 py-4 text-left font-bold transition
                    {{ $loanApproved ? 'border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100' : 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed' }}"
                @disabled(! $loanApproved)
            >
                <span class="block text-sm uppercase tracking-[0.14em] opacity-80">Loan module</span>
                <span class="block text-xl mt-1">Login for Loan Management</span>
            </button>
        </form>
    </div>

    @if ($errors->any())
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('module') }}
        </div>
    @endif
</x-guest-layout>

