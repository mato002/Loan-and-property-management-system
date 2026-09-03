<?php

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/app/Http/Controllers')
);

$replacements = [
    "in_array(\$export, ['csv', 'xls', 'pdf'], true)" => "in_array(\$export, ['csv', 'xls', 'pdf', 'word'], true)",
    "in_array(\$export, ['csv', 'pdf'], true)" => "in_array(\$export, ['csv', 'pdf', 'word'], true)",
    "in_array(\$format, ['csv', 'pdf'], true)" => "in_array(\$format, ['csv', 'pdf', 'word'], true)",
];

$count = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $orig = $content;

    foreach ($replacements as $search => $replace) {
        if (str_contains($content, $search) && ! str_contains($content, str_replace("'word'", "'word', 'word'", $replace))) {
            $content = str_replace($search, $replace, $content);
        }
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        $count++;
        echo basename($path).PHP_EOL;
    }
}

echo "Updated {$count} controller files".PHP_EOL;
