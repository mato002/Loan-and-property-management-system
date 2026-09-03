<?php

$path = dirname(__DIR__).'/app/Http/Controllers/Property/Agent/PropertyAccountingController.php';
$content = file_get_contents($path);
$content = str_replace(
    "\$format = strtolower((string) \$request->query('format', 'csv'));",
    "\$format = TabularExport::requestedFormat(\$request->query('export'), \$request->query('format'));",
    $content
);
file_put_contents($path, $content);
echo "Updated PropertyAccountingController\n";
