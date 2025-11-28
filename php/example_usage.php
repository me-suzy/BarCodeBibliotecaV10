<?php
// Set encoding to UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require 'BarcodeLibrary.php';

// ==============================================================

// EXEMPLU DE FOLOSIRE

// ==============================================================

$barcodeLib = new BarcodeLibrary();

// 1. Generează UN singur cod

$code = '014016038';

$barcodeLib->generateBarcodePNG($code, 'barcode_014016038.png');

echo "Cod generat: barcode_014016038.png\n";

// 2. Generează serie de coduri (counter automat)

$startCode = 14016038;  // fără zero în față pentru calcul numeric

$count = 10;

$barcodes = $barcodeLib->generateBarcodeRange($startCode, $count);

foreach ($barcodes as $item) {

    echo "Cod: " . $item['code'] . " - generat\n";

}

// 3. Salvează toate codurile ca imagini PNG

$files = $barcodeLib->saveBarcodeImages($startCode, 10);

echo "Generate " . count($files) . " coduri de bare în folderul barcodes/\n";

?>

