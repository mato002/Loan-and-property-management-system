<x-loan-layout>
    <div class="min-h-[calc(100vh-5rem)] bg-[#f2ede4] py-8 sm:py-10 px-4">
        <div class="max-w-5xl mx-auto bg-white rounded-lg shadow-md border border-slate-200/90 overflow-hidden">
            <div class="px-6 sm:px-10 pt-8 pb-5 border-b border-slate-200">
                <div class="flex items-center gap-4">
                    <a
                        href="{{ route('loan.dashboard') }}"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-300 text-[#1a2f42] hover:bg-slate-50 transition-colors"
                        title="Back"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <h1 class="font-serif text-2xl sm:text-[1.65rem] font-semibold text-[#1a2f42] tracking-tight">System Setup</h1>
                </div>
            </div>

            <div class="p-6 sm:p-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($cards as $card)
                        <a
                            href="{{ $card['href'] }}"
                            class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                        >
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                                @include('loan.system.setup.icon', ['name' => $card['icon']])
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">{{ $card['title'] }}</h2>
                                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">{{ $card['desc'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-loan-layout>
