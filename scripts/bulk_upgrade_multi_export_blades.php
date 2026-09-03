<?php

$root = dirname(__DIR__);
$paths = [
    $root.'/resources/views/property',
];

$multiExportBlock = <<<'REGEX'
/<a[^>]*href="\{\{[^"]*\['export'\s*=>\s*'csv'[^\}]*\}\}"[^>]*>\s*Export CSV\s*<\/a>\s*<a[^>]*href="\{\{[^"]*\['export'\s*=>\s*'xls'[^\}]*\}\}"[^>]*>\s*Export XLS\s*<\/a>\s*<a[^>]*href="\{\{[^"]*\['export'\s*=>\s*'pdf'[^\}]*\}\}"[^>]*>\s*Export PDF\s*<\/a>/s
REGEX;

$dropdown = "@include('property.agent.partials.table_export_dropdown', ['current' => true, 'formats' => \\App\\Support\\TableExportLinks::STANDARD_FORMATS])";

$formatBlock = <<<'REGEX'
/<a[^>]*href="\{\{[^"]*\['format'\s*=>\s*'csv'[^\}]*\}\}"[^>]*>\s*Export CSV\s*<\/a>\s*<a[^>]*href="\{\{[^"]*\['format'\s*=>\s*'xls'[^\}]*\}\}"[^>]*>\s*Export XLS\s*<\/a>\s*<a[^>]*href="\{\{[^"]*\['format'\s*=>\s*'pdf'[^\}]*\}\}"[^>]*>\s*Export PDF\s*<\/a>/s
REGEX;

$formatDropdown = <<<'BLADE'
@include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'csv'])),
                'xlsUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'xls'])),
                'pdfUrl' => route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'pdf'])),
            ])
BLADE;

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

        $content = preg_replace($multiExportBlock, $dropdown, $content) ?? $content;

        if (str_contains($path, 'unmatched_payments.blade.php')) {
            $content = preg_replace($formatBlock, $formatDropdown, $content) ?? $content;
        }

        if ($content !== $orig) {
            file_put_contents($path, $content);
            $updated++;
            echo str_replace($root.'/', '', $path).PHP_EOL;
        }
    }
}

echo "Updated {$updated} multi-export blade files".PHP_EOL;
