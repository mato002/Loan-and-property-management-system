@php($identityCtx = $contextClient ?? null)

@if (session('lead_convert_duplicate') && $identityCtx && $identityCtx->kind === \App\Models\LoanClient::KIND_LEAD)
    @php($dupBlock = session('lead_convert_duplicate'))
    <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm space-y-3" role="alert">
        <p class="font-semibold">Possible duplicate client</p>
        <p class="text-amber-900">Another onboarded client already uses the same phone or ID number as this lead. You can open that record or convert this lead anyway (you will keep two separate client files).</p>
        <ul class="list-disc pl-5 space-y-1 text-amber-900">
            @foreach (($dupBlock['matches'] ?? []) as $row)
                <li>
                    <a href="{{ route('loan.clients.show', $row['id']) }}" class="font-medium text-amber-950 underline hover:text-amber-800">{{ $row['name'] }} ({{ $row['number'] }})</a>
                </li>
            @endforeach
        </ul>
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <form method="post" action="{{ route('loan.clients.leads.convert', $identityCtx) }}" class="inline">
                @csrf
                <input type="hidden" name="lead_convert_confirmed" value="1">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-amber-800 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-900 transition-colors">
                    Convert this lead anyway
                </button>
            </form>
            @if (! empty($dupBlock['matches'][0]['id']))
                <a href="{{ route('loan.clients.show', $dupBlock['matches'][0]['id']) }}" class="text-sm font-semibold text-amber-950 underline hover:text-amber-800">Open first matching client</a>
            @endif
        </div>
    </div>
@endif

@if (session('duplicate_client_warnings') && is_array(session('duplicate_client_warnings')) && count(session('duplicate_client_warnings')) > 0)
    <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm space-y-1" role="status">
        <p class="font-semibold text-amber-900">Duplicate check (informational)</p>
        @foreach (session('duplicate_client_warnings') as $line)
            <p class="text-amber-900">{{ $line }}</p>
        @endforeach
    </div>
@endif

@if (session('warning'))
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 shadow-sm" role="status">
        {{ session('warning') }}
    </div>
@endif
