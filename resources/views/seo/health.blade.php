<x-public-layout :page-title="'SEO Health Check'">
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-10">
        <div class="max-w-4xl mx-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-900">SEO Health Check</h1>
            <p class="mt-2 text-sm text-slate-600">Quick verification page for sitemap, robots and public route readiness.</p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Sitemap Route</dt>
                    <dd class="mt-1 text-sm font-medium {{ $health['sitemap_route_exists'] ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $health['sitemap_route_exists'] ? 'OK' : 'Missing' }}
                    </dd>
                    <a class="mt-2 inline-block text-sm text-indigo-700 hover:underline" href="{{ $health['sitemap_url'] }}" target="_blank" rel="noopener">Open sitemap.xml</a>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Robots Route</dt>
                    <dd class="mt-1 text-sm font-medium {{ $health['robots_route_exists'] ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $health['robots_route_exists'] ? 'OK' : 'Missing' }}
                    </dd>
                    <a class="mt-2 inline-block text-sm text-indigo-700 hover:underline" href="{{ $health['robots_url'] }}" target="_blank" rel="noopener">Open robots.txt</a>
                </div>
            </dl>

            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <h2 class="text-sm font-semibold text-slate-900">Indexed Public Routes</h2>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($health['public_routes'] as $name => $url)
                        <li><span class="font-medium text-slate-700">{{ strtoupper($name) }}:</span> <a class="text-indigo-700 hover:underline" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-public-layout>
