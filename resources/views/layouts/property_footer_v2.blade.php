<footer class="shrink-0 mt-auto border-t border-slate-200/90 bg-[#f0f3f7] py-2 px-3 sm:py-3 sm:px-6 lg:px-8 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2 text-[11px] sm:text-xs lg:text-sm text-slate-500">
        <p class="text-center sm:text-left">
            <a href="https://mathiasodhiambo.netlify.app/" target="_blank" rel="noopener noreferrer" data-turbo="false" class="text-emerald-700 hover:text-emerald-800 hover:underline font-medium">
                &copy; {{ date('Y') }} {{ \App\Models\PropertyPortalSetting::getValue('company_name', config('app.name', 'Property Management')) }}. All rights reserved.
            </a>
        </p>
        <p class="text-center sm:text-right tabular-nums text-slate-400">
            Version {{ config('app.version', '2.0') }}
        </p>
    </div>
</footer>
