<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Statement - {{ $tenant->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="p-4 md:p-6 space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <h1 class="text-lg font-semibold">Tenant Statement - {{ $tenant->name }}</h1>
            <p class="text-sm text-slate-600 mt-1">
                Phone: {{ $tenant->phone ?: '—' }} | Email: {{ $tenant->email ?: '—' }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ($stats as $s)
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">{{ $s['label'] }}</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $s['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
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
