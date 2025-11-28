<?php
// Set encoding to UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeLibrary {

    

    /**

     * Generează cod de bare Code128 ca imagine PNG

     */

    public function generateBarcodePNG($code, $outputPath = null) {

        $generator = new BarcodeGeneratorPNG();

        

        // Parametri pentru Code128 compatibil cu scanere

        $barcode = $generator->getBarcode(

            $code,                          // Codul (ex: 014016038)

            $generator::TYPE_CODE_128,      // Tipul: Code128

            3,                              // Lățimea unei bare (în pixeli) - minim 3 pentru scanere

            80,                             // Înălțimea codului (pixeli)

            array(0, 0, 0)                  // Culoare negru

        );

        

        if ($outputPath) {

            file_put_contents($outputPath, $barcode);

            return $outputPath;

        }

        

        return $barcode; // Return binary data

    }

    

    /**

     * Generează cod de bare Code128 ca SVG (pentru FastReport)

     */

    public function generateBarcodeSVG($code) {

        $generator = new BarcodeGeneratorSVG();

        

        return $generator->getBarcode(

            $code,

            $generator::TYPE_CODE_128,

            3,    // Lățime modul

            80    // Înălțime

        );

    }

    

    /**

     * Generează serie de coduri cu counter

     */

    public function generateBarcodeRange($startCode, $count) {

        $barcodes = [];

        

        for ($i = 0; $i < $count; $i++) {

            $currentCode = $startCode + $i;

            $barcodes[] = [

                'code' => $currentCode,

                'barcode_png' => $this->generateBarcodePNG($currentCode),

                'barcode_svg' => $this->generateBarcodeSVG($currentCode)

            ];

        }

        

        return $barcodes;

    }

    

    /**

     * Salvează coduri ca imagini separate

     */

    public function saveBarcodeImages($startCode, $count, $outputDir = 'barcodes/') {

        if (!is_dir($outputDir)) {

            mkdir($outputDir, 0755, true);

        }

        

        $files = [];

        

        for ($i = 0; $i < $count; $i++) {

            $currentCode = $startCode + $i;

            $filename = $outputDir . 'barcode_' . $currentCode . '.png';

            $this->generateBarcodePNG($currentCode, $filename);

            $files[] = $filename;

        }

        

        return $files;

    }

}

