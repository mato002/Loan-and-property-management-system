<?php

$root = dirname(__DIR__);
$bladeRoot = $root.'/resources/views/loan';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bladeRoot));
$updated = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    if (! str_contains($content, 'export') || str_contains($content, 'partials.export_dropdown') || str_contains($content, 'partials/export_dropdown')) {
        continue;
    }

    // Replace standalone CSV export anchors with the shared dropdown when siblings are CSV/XLS/PDF only.
    if (preg_match_all('/<a[^>]*href="\{\{\s*(route\([^\)]+\)|request\(\)->fullUrlWithQuery\([^\)]+\))[^"]*\}\}"[^>]*>\s*CSV\s*<\/a>/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        // If page already has PDF export link nearby, convert the group to one dropdown.
        if (str_contains($content, "['export' => 'pdf']") || str_contains($content, "['export' => \"pdf\"]")) {
            $content = preg_replace(
                '/(<a[^>]*href="\{\{[^"]*export[^"]*csv[^"]*\}\}"[^>]*>\s*CSV\s*<\/a>\s*)+/',
                "@include('partials.export_dropdown')\n                    ",
                $content,
                1
            ) ?? $content;

            $content = preg_replace(
                '/<a[^>]*href="\{\{[^"]*export[^"]*xls[^"]*\}\}"[^>]*>\s*XLS\s*<\/a>\s*/',
                '',
                $content
            ) ?? $content;

            $content = preg_replace(
                '/<a[^>]*href="\{\{[^"]*export[^"]*pdf[^"]*\}\}"[^>]*>\s*PDF\s*<\/a>\s*/',
                '',
                $content
            ) ?? $content;
        } elseif (preg_match('/<a[^>]*href="\{\{[^"]*export[^"]*csv[^"]*\}\}"[^>]*>\s*CSV\s*<\/a>/', $content)) {
            $content = preg_replace(
                '/<a[^>]*href="\{\{[^"]*export[^"]*csv[^"]*\}\}"[^>]*>\s*CSV\s*<\/a>/',
                "@include('partials.export_dropdown')",
                $content,
                1
            ) ?? $content;
        }
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        $updated++;
        echo str_replace($bladeRoot.'/', 'loan/', $path).PHP_EOL;
    }
}

echo "Updated {$updated} loan blade files".PHP_EOL;
