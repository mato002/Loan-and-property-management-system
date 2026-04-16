<footer class="mt-auto border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 flex-shrink-0">
    <div class="w-full px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-sm text-slate-500 dark:text-slate-400 text-center sm:text-left">
            <a href="https://mathiasodhiambo.netlify.app/" target="_blank" rel="noopener noreferrer" class="hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline transition-colors">
            &copy; {{ date('Y') }} Loan Management System. All rights reserved.
            </a>
        </p>
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-6 gap-y-2">
            @if (\Illuminate\Support\Facades\Route::has('loan.system.tickets.index'))
                <a href="{{ route('loan.system.tickets.index') }}" class="text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Support tickets</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('loan.system.setup'))
                <a href="{{ route('loan.system.setup') }}" class="text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">System setup</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('public.privacy'))
                <a href="{{ route('public.privacy') }}" class="text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Privacy</a>
            @endif
        </div>
    </div>
</footer>
