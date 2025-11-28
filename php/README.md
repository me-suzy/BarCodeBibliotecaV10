# Generator Coduri de Bare - Varianta PHP

## Instalare

```bash
cd php
composer install
```

## Utilizare

### Exemplu simplu

```php
<?php
require 'BarcodeLibrary.php';

$barcodeLib = new BarcodeLibrary();
$code = '014016038';
$barcodeLib->generateBarcodePNG($code, 'barcode_014016038.png');
```

### Generare serie de coduri

```php
<?php
require 'BarcodeLibrary.php';

$barcodeLib = new BarcodeLibrary();
$startCode = 14016038;
$count = 10;

// Salvează toate codurile ca imagini PNG
$files = $barcodeLib->saveBarcodeImages($startCode, $count, 'barcodes/');
```

## Integrare cu FastReport

Vezi fișierele din folderul `fastreport/`:
- `barcode_template.fr3` - Template cu imagini PNG
- `barcode_native.fr3` - Template cu coduri de bare native FastReport
- `fastreport_example.php` - Exemplu de integrare

## Structura fișierelor

```
php/
├── BarcodeLibrary.php          # Clasa principală
├── composer.json                # Dependențe Composer
├── example_usage.php            # Exemple de utilizare
├── fastreport/
│   ├── barcode_template.fr3     # Template FastReport cu imagini
│   ├── barcode_native.fr3       # Template FastReport nativ
│   └── fastreport_example.php   # Exemplu integrare FastReport
└── README.md                    # Acest fișier
```

