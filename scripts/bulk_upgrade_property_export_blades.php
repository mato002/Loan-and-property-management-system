<?php

$root = dirname(__DIR__);
$base = $root.'/resources/views/property';

$replacements = [
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.maintenance\\.requests\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.maintenance.requests.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.maintenance\\.jobs\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.maintenance.jobs.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.listings\\.leads\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.listings.leads.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.listings\\.applications\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.listings.applications.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.tenants\\.notices\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.tenants.notices.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.communications\\.bulk\\.export'[^\\}]+\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.communications.bulk.export', 'query' => (array) (\$filters ?? [])])",
    "/<a[^>]*href=\"\\{\\{\\s*route\\('property\\.landlords\\.index', array_merge\\(request\\(\\)->query\\(\\), \\['export' => 'csv'\\]\\)[^\\}]*\\}\\}\"[^>]*>\\s*Export CSV\\s*<\\/a>/"
        => "@include('property.agent.partials.table_export_dropdown', ['route' => 'property.landlords.index', 'query' => request()->query()])",
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$updated = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content) ?? $content;
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        $updated++;
        echo str_replace($root.'/', '', $path).PHP_EOL;
    }
}

echo "Updated {$updated} property blade files".PHP_EOL;
