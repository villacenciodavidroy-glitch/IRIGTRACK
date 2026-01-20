<?php
/**
 * Quick script to check if DOMPDF is installed
 * Run: php check_dompdf.php
 */

require __DIR__ . '/vendor/autoload.php';

echo "Checking DOMPDF installation...\n\n";

$dompdfExists = class_exists('Dompdf\Dompdf');
$optionsExists = class_exists('Dompdf\Options');

echo "Dompdf\Dompdf class: " . ($dompdfExists ? "✓ FOUND" : "✗ NOT FOUND") . "\n";
echo "Dompdf\Options class: " . ($optionsExists ? "✓ FOUND" : "✗ NOT FOUND") . "\n\n";

if ($dompdfExists && $optionsExists) {
    echo "SUCCESS: DOMPDF is installed and ready to use!\n";
    exit(0);
} else {
    echo "ERROR: DOMPDF is not installed.\n\n";
    echo "To install, run:\n";
    echo "  composer require dompdf/dompdf\n";
    echo "  composer dump-autoload\n";
    echo "  php artisan config:clear\n";
    exit(1);
}

