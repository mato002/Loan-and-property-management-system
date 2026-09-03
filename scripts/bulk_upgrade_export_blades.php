<?php

$root = dirname(__DIR__);
$bladeRoot = $root.'/resources/views';

$routeExportReplacements = [
    "route('property.maintenance.requests.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.maintenance.requests.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.maintenance.jobs.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.maintenance.jobs.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.vendors.directory.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.vendors.directory.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.listings.leads.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.listings.leads.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.listings.applications.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.listings.applications.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.tenants.notices.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.tenants.notices.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.tenants.movements.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.tenants.movements.export', 'query' => (array) (\$filters ?? [])])",
    "route('property.tenants.directory.export', request()->query())" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.tenants.directory.export', 'query' => request()->query()])",
    "route('property.communications.bulk.export', (array) (\$filters ?? []))" => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.communications.bulk.export', 'query' => (array) (\$filters ?? [])])",
];

$reportsTableActions = <<<'BLADE'
    <x-slot name="actions">
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
        @include('property.agent.partials.table_export_dropdown', ['current' => true])
    </x-slot>
BLADE;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bladeRoot));
$updated = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    foreach ($routeExportReplacements as $search => $replace) {
        if (! str_contains($content, $search) || ! str_contains($content, 'Export CSV')) {
            continue;
        }

        $pattern = '/<a[^>]*href="\{\{\s*'.preg_quote($search, '/').'\s*\}\}"[^>]*>\s*Export CSV\s*<\/a>/s';
        $replacement = '@include(\'property.agent.partials.table_export_dropdown\', '.substr($replace, strpos($replace, '[')).')';
        $content = preg_replace($pattern, $replacement, $content) ?? $content;
    }

    if (str_ends_with(str_replace('\\', '/', $path), 'reports/table.blade.php') && str_contains($content, 'Export CSV')) {
        $content = preg_replace(
            '/<x-slot name="actions">.*?<\/x-slot>/s',
            $reportsTableActions,
            $content,
            1
        ) ?? $content;
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        $updated++;
        echo str_replace($bladeRoot.'/', '', $path).PHP_EOL;
    }
}

echo "Updated {$updated} blade files".PHP_EOL;
