# 📋 Ghid - Coduri de Bare Aleph pentru Cititori

## 📖 Informații Generale

Sistemul suportă **două tipuri de coduri de bare** pentru cititori:

### 1. **Format USER (pentru testare locală)**
- Format: `USER001`, `USER002`, `USER003`, etc.
- Folosit pentru testare și dezvoltare
- Nu are statut asociat
- Păstrat pentru compatibilitate

### 2. **Format Aleph (producție)**
- Format: **12 caractere numerice**
- Standard: **GS1-128, Code 128-A**
- Structură: `SS + NNNNNNNNN + X`
  - `SS` = 2 cifre (statut cititor din tabelul 31 Aleph)
  - `NNNNNNNNN` = 9 cifre (număr secvențial)
  - `X` = 1 cifră (padding sau check digit)
- Exemplu: `1200000010` (statut 12, număr 000000001)

## 🔧 Implementare

### Fișiere Create/Modificate:

1. **`functions_coduri_aleph.php`** - Funcții helper pentru coduri Aleph
   - `valideazaCodAleph()` - Validează coduri Aleph
   - `valideazaCodUser()` - Validează coduri USER
   - `detecteazaTipCod()` - Detectează tipul de cod
   - `genereazaCodAleph()` - Generează coduri Aleph noi
   - `gasesteCititorDupaCod()` - Găsește cititor după cod (ambele formate)

2. **`scanare_rapida.php`** - Actualizat pentru recunoaștere ambele formate
   - Detectează automat tipul de cod
   - Funcționează cu ambele formate

3. **`adauga_cititor.php`** - Actualizat pentru suport ambele formate
   - Validare automată format
   - Extragere automată statut din coduri Aleph
   - Interfață îmbunătățită cu detectare în timp real

4. **`update_database_coduri_aleph.sql`** - Script SQL pentru actualizare structură
   - Adaugă câmpul `statut` în tabelul `cititori`
   - Adaugă câmpul `tip_cod` (user/aleph)
   - Actualizează codurile existente

## 🗄️ Structură Baza de Date

### Tabelul `cititori` - Câmpuri Noi:

```sql
ALTER TABLE cititori 
ADD COLUMN statut VARCHAR(2) NULL COMMENT 'Statut cititor (extras din cod Aleph)',
ADD COLUMN tip_cod ENUM('user', 'aleph') DEFAULT 'user' COMMENT 'Tip cod de bare';
```

### Exemple Date:

```sql
-- Cod USER (testare)
INSERT INTO cititori (cod_bare, nume, prenume, tip_cod) 
VALUES ('USER001', 'Popescu', 'Ion', 'user');

-- Cod Aleph (producție)
INSERT INTO cititori (cod_bare, nume, prenume, tip_cod, statut) 
VALUES ('1200000010', 'Ionescu', 'Maria', 'aleph', '12');
```

## 📝 Utilizare

### Adăugare Cititor cu Cod USER:

1. Accesează `adauga_cititor.php`
2. Introdu cod: `USER001`
3. Completează datele
4. Salvează

### Adăugare Cititor cu Cod Aleph:

1. Accesează `adauga_cititor.php`
2. Introdu cod: `1200000010` (12 cifre)
3. Sistemul detectează automat formatul Aleph
4. Statutul este extras automat (primele 2 cifre: `12`)
5. Completează datele
6. Salvează

### Scanare Coduri:

Sistemul recunoaște automat ambele formate:
- Scanezi `USER001` → Sistemul găsește cititorul
- Scanezi `1200000010` → Sistemul găsește cititorul

## 🔍 Validare Coduri

### Format Aleph Valid:
- ✅ Exact 12 caractere
- ✅ Toate caracterele sunt cifre (0-9)
- ✅ Primele 2 cifre reprezintă statutul (11-99)
- ✅ Următoarele 9 cifre reprezintă numărul
- ✅ Ultima cifră este padding/check digit

### Format USER Valid:
- ✅ Începe cu `USER` (case insensitive)
- ✅ Urmează un număr (ex: `USER001`, `USER123`)

## 🎯 Statuturi Cititori

Statuturile sunt preluate din **tabelul 31 din modulul ALEPHADM** (Circulație).

Exemple statuturi (trebuie actualizate cu valorile reale):
- `11` - Statut 11
- `12` - Statut 12
- `13` - Statut 13
- etc.

**IMPORTANT:** Actualizează funcția `obtineStatuturiDisponibile()` din `functions_coduri_aleph.php` cu statuturile reale din Aleph!

## 🚀 Pași pentru Deploy

### 1. Actualizare Baza de Date Locală:

```bash
# Rulează scriptul SQL
mysql -u root -p biblioteca < update_database_coduri_aleph.sql
```

Sau în phpMyAdmin:
- Importă `update_database_coduri_aleph.sql`

### 2. Testare Locală:

1. Testează adăugare cititor cu cod USER: `USER001`
2. Testează adăugare cititor cu cod Aleph: `1200000010`
3. Testează scanare cu ambele tipuri de coduri
4. Verifică că toate funcțiile funcționează corect

### 3. Verificare:

```sql
-- Verifică cititorii cu tipuri de coduri
SELECT cod_bare, nume, prenume, tip_cod, statut 
FROM cititori 
ORDER BY tip_cod, cod_bare;
```

## ⚠️ Notă Importantă

**Codurile USER (USER001, USER002, etc.) sunt păstrate pentru testare locală!**

Nu șterge sau modifica codurile USER existente - acestea sunt necesare pentru testare.

## 📚 Referințe

- Standard GS1-128: https://www.gs1.org/standards/barcodes
- Code 128-A: https://en.wikipedia.org/wiki/Code_128
- Documentație Aleph: Tabelul 31 (Modul Circulație, ALEPHADM)

---

**✅ Sistemul suportă acum ambele tipuri de coduri de bare pentru cititori!**

