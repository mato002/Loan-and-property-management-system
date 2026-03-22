<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">System setup</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-3xl p-6">
            <form method="post" action="{{ route('loan.system.setup.preferences.update') }}" class="space-y-5">
                @csrf
                @include('loan.system.setup._fields', ['settings' => $settings])
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save general settings</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
