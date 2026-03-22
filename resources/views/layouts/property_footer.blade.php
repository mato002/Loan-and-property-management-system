<footer class="shrink-0 mt-auto border-t border-slate-200/90 bg-[#f0f3f7] py-3 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs sm:text-sm text-slate-500">
        <p class="text-center sm:text-left">
            &copy; {{ date('Y') }}
            <a href="{{ url('/') }}" data-turbo="false" class="text-emerald-700 hover:text-emerald-800 hover:underline font-medium">{{ config('app.name', 'Property Management') }}</a>.
            All rights reserved.
        </p>
        <p class="text-center sm:text-right tabular-nums text-slate-400">
            Version {{ config('app.version', '2.0') }}
        </p>
    </div>
</footer>
