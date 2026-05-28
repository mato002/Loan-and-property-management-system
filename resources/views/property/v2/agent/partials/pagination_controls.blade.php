@if (isset($paginator) && $paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Page {{ $paginator->currentPage() }}
            @if (method_exists($paginator, 'lastPage'))
                of {{ $paginator->lastPage() }}
            @endif
            @if (method_exists($paginator, 'total'))
                <span class="text-slate-500">({{ $paginator->total() }} records)</span>
            @endif
        </p>
        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Prev</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Next</a>
            @else
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400">Next</span>
            @endif
        </div>
    </div>
@endif
