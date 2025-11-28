# Ghid Encoding UTF-8 pentru Coduri de Bare

## Problema

Caracterele românești (ș, ț, ă, î, â) pot să nu se afișeze corect dacă encoding-ul nu este setat corect pe UTF-8.

## Soluții implementate

### PHP

Toate fișierele PHP includ la început:
```php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
```

### Python

1. **Imagini PIL/Pillow**: 
   - Se caută automat fonturi TTF care suportă caractere românești (Arial, Arial Unicode MS, DejaVu Sans, Liberation Sans)
   - Căile standard: `C:/Windows/Fonts/arial.ttf` pentru Windows

2. **PDF ReportLab**:
   - Se înregistrează automat fonturi TTF pentru PDF
   - Fonturile TTF permit afișarea corectă a caracterelor UTF-8

3. **Output console**:
   - Pe Windows, se configurează automat encoding UTF-8 pentru stdout/stderr

### FastReport (.fr3)

- Toate template-urile au encoding explicit în XML: `<?xml version="1.0" encoding="utf-8"?>`
- Fonturile recomandate: Arial, Times New Roman (suportă UTF-8)

## Testare

Pentru a testa dacă encoding-ul este corect:

### PHP
```php
<?php
header('Content-Type: text/html; charset=UTF-8');
echo "Biblioteca Academiei Române - Iași";
?>
```

Dacă vezi corect "Iași" (cu ș), encoding-ul este OK.

### Python
```python
# -*- coding: utf-8 -*-
text = "Biblioteca Academiei Române - Iași"
print(text)
```

Dacă vezi corect "Iași" (cu ș) în console, encoding-ul este OK.

## Rezolvare probleme

### Problemă: Caracterele apar ca pătrățele negre

**Cauză**: Fontul nu suportă caracterele românești

**Soluție**:
1. **Python**: Verifică că fonturile TTF există în căile specificate
2. **FastReport**: Schimbă fontul în Arial sau Times New Roman
3. **PDF**: Asigură-te că fontul TTF este înregistrat corect

### Problemă: Eroare "UnicodeDecodeError" în Python

**Cauză**: Encoding-ul fișierului nu este UTF-8

**Soluție**: Adaugă la începutul fișierului:
```python
# -*- coding: utf-8 -*-
```

### Problemă: În PHP, caracterele nu se afișează corect

**Cauză**: Encoding-ul nu este setat corect

**Soluție**: Adaugă la început:
```php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
```

## Fonturi recomandate

### Windows
- `C:/Windows/Fonts/arial.ttf` - Arial (suport bun UTF-8)
- `C:/Windows/Fonts/arialuni.ttf` - Arial Unicode MS (suport complet UTF-8)
- `C:/Windows/Fonts/times.ttf` - Times New Roman (suport UTF-8)

### Linux
- `/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf` - DejaVu Sans (suport complet UTF-8)
- `/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf` - Liberation Sans

### macOS
- `/System/Library/Fonts/Helvetica.ttc` - Helvetica (suport UTF-8)

## Verificare finală

După generare, verifică că în imagine/PDF apare corect:
- **Biblioteca Academiei Române - Iași** (nu "Iai" sau "Ia?i")

Dacă vezi pătrățele sau semne de întrebare în loc de "ș", problema este la font sau encoding.

