# Generator Coduri de Bare - Varianta Python

## Instalare

```bash
cd python
pip install -r requirements.txt
```

## Utilizare

### Exemplu simplu

```python
from BarcodeLibraryGenerator import BarcodeLibraryGenerator

generator = BarcodeLibraryGenerator()

# Generează un singur cod
single_code = generator.generate_barcode_with_header('014016038')
print(f"Cod generat: {single_code}")
```

### Generare serie de coduri

```python
from BarcodeLibraryGenerator import BarcodeLibraryGenerator

generator = BarcodeLibraryGenerator()

start = 14016038
count = 10

# Generează serie de coduri
files = generator.generate_range(start, count)
print(f"Generate {len(files)} coduri de bare")
```

### Generare PDF cu etichete

```python
from BarcodeLibraryGenerator import BarcodeLibraryGenerator

generator = BarcodeLibraryGenerator()

# Generează PDF cu etichete A4 (27 = 3×9 etichete/pagină)
pdf_file = generator.generate_pdf_labels(14016038, 27, 'etichete_biblioteca.pdf')
```

## Structura fișierelor

```
python/
├── BarcodeLibraryGenerator.py   # Clasa principală
├── requirements.txt              # Dependențe Python
├── example_usage.py              # Exemple de utilizare
└── README.md                     # Acest fișier
```

## Funcții disponibile

- `generate_barcode_image(code, show_text=True)` - Generează cod de bare simplu PNG
- `generate_barcode_with_header(code, header_text)` - Generează cod cu header personalizat
- `generate_range(start_code, count)` - Generează serie de coduri cu counter automat
- `generate_pdf_labels(start_code, count, output_pdf)` - Generează PDF cu etichete A4

## Format output

- **Imagini PNG**: Salvate în folderul `barcodes_python/`
- **PDF**: Etichete formatate pentru imprimare A4 (3 coloane × 9 rânduri per pagină)

