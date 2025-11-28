# FastReport Templates - UTF-8 Support

## Encoding UTF-8

Toate template-urile `.fr3` sunt salvate cu encoding **UTF-8** pentru a suporta caracterele românești (ș, ț, ă, î, â).

## Template-uri disponibile

### 1. `barcode_template.fr3`
- Folosește imagini PNG generate din PHP
- Câmpul `ImagePath` trebuie să conțină calea către imaginea PNG
- Encoding: UTF-8

### 2. `barcode_native.fr3`
- Folosește coduri de bare native FastReport
- Câmpul `BarcodeNumber` trebuie să conțină codul de bare (ex: "014016038")
- Encoding: UTF-8

## Configurare fonturi în FastReport

Pentru a afișa corect caracterele românești (ș, ț, ă, î, â), asigură-te că:

1. Fontul folosit în FastReport suportă UTF-8 (ex: Arial, Times New Roman)
2. Encoding-ul proiectului este setat la UTF-8
3. Encoding-ul bazei de date este UTF-8

## Utilizare

Vezi exemplul din `fastreport_example.php` pentru integrare completă.

