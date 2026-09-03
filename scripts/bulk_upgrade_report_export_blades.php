<?php

$root = dirname(__DIR__);
$paths = [
    $root.'/resources/views/property',
    $root.'/resources/views/superadmin',
];

$singleCsvPattern = '/<a[^>]*href="\{\{\s*url\(\)->current\(\)\.\'\?\'\.http_build_query\(array_filter\(array_merge\(request\(\)->query\(\), \[\s*\'export\'\s*=>\s*\'csv\'\s*\]\)\)\)\s*\}\}"[^>]*>\s*Export CSV\s*<\/a>/';

$routeCsvPattern = '/<a[^>]*href="\{\{\s*route\([^\)]+\[\'export\'\s*=>\s*\'csv\'\][^\)]*\)[^"]*\}\}"[^>]*>\s*Export CSV\s*<\/a>/';

$dropdown = "@include('property.agent.partials.table_export_dropdown', ['current' => true, 'formats' => \\App\\Support\\TableExportLinks::STANDARD_FORMATS])";

$updated = 0;
foreach ($paths as $base) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $content = file_get_contents($path);
        $orig = $content;

        $content = preg_replace($singleCsvPattern, $dropdown, $content) ?? $content;
        $content = preg_replace($routeCsvPattern, $dropdown, $content) ?? $content;

        if ($content !== $orig) {
            file_put_contents($path, $content);
            $updated++;
            echo str_replace($root.'/', '', $path).PHP_EOL;
        }
    }
}

echo "Updated {$updated} report/action blade files".PHP_EOL;
