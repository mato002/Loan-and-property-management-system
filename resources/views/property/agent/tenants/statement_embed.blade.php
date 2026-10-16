<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Statement - {{ $tenant->name }}</title>
    @vite(['resources/css/app.css'])
    @vite(['resources/js/statement-reversal-confirm.js'])
</head>
<body class="h-full overflow-hidden bg-slate-50 text-slate-900">
    <div class="flex h-full min-h-0 flex-col gap-2 p-2 md:p-3">
        @if (session('success'))
            <div class="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif
        <div class="shrink-0 rounded-xl border border-slate-200 bg-white px-3 py-2">
            <h1 class="text-base font-semibold">Tenant Statement - {{ $tenant->name }}</h1>
            <p class="mt-0.5 text-xs text-slate-600">
                Phone: <x-phone-link :value="$tenant->phone" /> | Email: {{ $tenant->email ?: '—' }}
            </p>
        </div>

        <div class="grid shrink-0 grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($stats as $s)
                <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5">
                    <div class="text-[10px] uppercase tracking-wide text-slate-500">{{ $s['label'] }}</div>
                    <div class="text-sm font-semibold text-slate-900">{{ $s['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            @foreach ($columns as $col)
                                <th class="px-3 py-2 whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tableRows as $row)
                            <tr class="border-b border-slate-100">
                                @foreach ($row as $cell)
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if ($cell instanceof \Illuminate\Support\HtmlString)
                                            {!! $cell !!}
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-3 py-6 text-center text-slate-500">No statement entries.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
