<?php
// Set encoding to UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require '../BarcodeLibrary.php';

// Generează coduri

$barcodeLib = new BarcodeLibrary();

$startCode = 14016038;

$count = 10;

// Salvează imagini

$files = $barcodeLib->saveBarcodeImages($startCode, $count, '../barcodes/');

// Pregătește date pentru FastReport

$reportData = [];

for ($i = 0; $i < $count; $i++) {

    $currentCode = str_pad($startCode + $i, 9, '0', STR_PAD_LEFT); // 014016038

    

    $reportData[] = [

        'ImagePath' => 'barcodes/barcode_' . ($startCode + $i) . '.png',

        'BarcodeNumber' => $currentCode,

        'LibraryName' => 'Biblioteca Academiei Române - Iași'

    ];

}

// Trimite către FastReport

// (cod specific pentru integrarea ta cu FastReport)

// $frReport = new FastReport();

// $frReport->loadTemplate('barcode_template.fr3');

// $frReport->setData($reportData);

// $frReport->prepare();

// $frReport->export('pdf', 'etichete_coduri_bare.pdf');

echo "PDF generat: etichete_coduri_bare.pdf\n";

// ==============================================================

// Varianta B: Folosești cod de bare nativ în FastReport

// ==============================================================

$data = [];

for ($i = 0; $i < $count; $i++) {

    $currentCode = str_pad($startCode + $i, 9, '0', STR_PAD_LEFT);

    

    $data[] = [

        'BarcodeNumber' => $currentCode,

        'LibraryName' => 'Biblioteca Academiei Române - Iași'

    ];

}

// Export cu FastReport

// $frReport = new FastReport();

// $frReport->loadTemplate('barcode_native.fr3');

// $frReport->setData($data);

// $frReport->exportToPDF('etichete_finale.pdf');

?>

