# 📚 Logica Completă de Căutare în Aleph

## 🔍 Prezentare Generală

Sistemul folosește funcția `cautaCarteInAleph($search_term, $search_type = 'AUTO')` care detectează automat tipul de cod (cota sau barcode) și aplică strategiile corespunzătoare de căutare în catalogul Aleph.

---

## 🎯 ETAPA 1: Inițializare Sesiune

### 1.1. Obținere Session ID
```php
$init_url = "YOUR-IP-or-http://WEBSITE:8991/F?func=file&file_name=find-b"
$session_response = fetch_url($init_url)
```

**Proces:**
- Se accesează pagina de căutare Aleph
- Se extrage `session_id` din răspunsul HTML folosind regex: `/\/F\/([A-Z0-9\-]+)\?/`
- Dacă nu găsește, încearcă pattern alternativ: `/\/F\/([A-Z0-9\-]+)/`

**Rezultat:** Session ID necesar pentru toate cererile ulterioare

---

## 🔎 ETAPA 2: Detectare Automată Tip Cod

### 2.1. Detectare Barcode
**Pattern:** `/^([A-Z]\d{5,}|[A-Z]{2,3}\d{4,}|\d{5,})(-\d{1,2})?$/i`

**Exemple:**
- `RV00108` ✅ (2 litere + cifre)
- `C013121` ✅ (1 literă + cifre)
- `000029152-10` ✅ (doar cifre cu sufix)
- `000017317-10` ✅

**Strategie:** `['BAR', 'LOC', 'WRD']` - încearcă BAR primul

### 2.2. Detectare Cota
**Pattern 1:** `/^[A-Z]{1,3}[\s\-]\d+([\s\-]\d+)?$/i`
- `I-14156` ✅
- `I 14156` ✅
- `II-01270` ✅
- `III-32073` ✅

**Pattern 2:** `/^[A-Z]{2,3}\s+[A-Z]\s*-\s*\d+([\s\-]\d+)?$/i`
- `RV I-94` ✅

**Strategie:** `['LOC', 'BAR', 'WRD']` - încearcă LOC primul

### 2.3. Tip Necunoscut
**Strategie:** `['BAR', 'LOC', 'WRD']` - încearcă toate în ordine

---

## 🔄 ETAPA 3: Căutare cu Fallback Automat

### 3.1. Proces de Căutare
Pentru fiecare strategie din listă (BAR, LOC, sau WRD):

```php
$search_url = "{$ALEPH_BASE_URL}/{$session_id}?func=find-b&request=" . 
              urlencode($search_term) . 
              "&find_code={$strategy}&adjacent=N&local_base=RAI01"
```

### 3.2. Verificare Rezultate
Se verifică dacă răspunsul conține mesaje de "no results":
- `"Your search found no results"`
- `"Căutarea nu a avut rezultate"`
- `"nu a avut rezultate"`
- `"No results"`

**Dacă NU conține mesaje de eroare:**
- ✅ **GĂSIT!** Se salvează `$search_response` și se oprește căutarea
- Se marchează `$used_strategy` pentru debug

**Dacă TOATE strategiile returnează "no results":**
- ❌ **NU EXISTĂ** - returnează `success: false`

---

## 🔗 ETAPA 4: Extragere Link către Item-Global

### 4.1. METODA 1: Căutare Directă în Search Response

**Pattern 1:** Linkuri cu `func=item-global|direct|full-set`
```regex
/<A\s+[^>]*HREF\s*=\s*["\']?([^"\'>\s]*func=(?:item-global|direct|full-set)[^"\'>\s]*)["\']?/i
```

**Pattern 2:** Pattern simplificat (case-insensitive)
```regex
/href\s*=\s*["\']?([^"\'>]*func=(?:item-global|direct|full-set)[^"\'>]*)["\']?/i
```

**Pattern 3:** Linkuri cu `doc_number`
```regex
/href\s*=\s*["\']?([^"\'>]*doc_number[^"\'>]*)["\']?/i
```

**Pattern 4:** Orice link către `/F/`
```regex
/href\s*=\s*["\']?(\/F\/[^"\'>\s]+)["\']?/i
```

**Pattern 5:** Fallback - orice link către `/F/`
```regex
/<A\s+[^>]*HREF\s*=\s*["\']?([^"\'>\s]*\/F\/[^"\'>\s]*)["\']?/i
```

### 4.2. Priorizare Linkuri
1. **Preferă linkuri cu `sub_library=ACAD`** (Biblioteca Academiei Iași)
2. **Preferă linkuri cu `func=item-global`**
3. **Preferă linkuri cu `doc_number`**

### 4.3. Normalizare URL
- Dacă linkul începe cu `http` → folosește direct
- Dacă linkul începe cu `/F/` sau `F/` → construiește URL complet
- Dacă linkul începe cu `?` → adaugă session_id
- Dacă linkul conține `func=` sau `doc_number` → construiește cu session_id
- **IMPORTANT:** Adaugă `sub_library=ACAD` dacă nu există deja

### 4.4. METODA 2: Căutare după set_number și set_entry
**Când se folosește:** Când METODA 1 nu găsește linkuri directe

**Proces:**
1. Extrage `set_number` și `set_entry` din search_response
2. Construiește URL: `func=direct&doc_number={set_entry}&local_base=RAI01`
3. Accesează pagina rezultatului
4. Caută linkuri către `item-global` în pagina rezultatului

### 4.5. METODA 3: Fallback - Construcție Manuală
**Când se folosește:** Când niciuna dintre metodele anterioare nu găsește linkuri

**Proces:**
1. Caută `doc_number` direct în search_response
2. Construiește manual: `func=item-global&doc_library=RAI01&doc_number={doc_number}&sub_library=ACAD`

### 4.6. METODA 4: Căutare în Format Diferit
**Când se folosește:** Pentru cazuri speciale

**Proces:**
- Caută linkuri care conțin `session_id` și `func=item-global|direct`

---

## 📄 ETAPA 5: Extragere Date din Item-Global

### 5.1. Fetch Pagina Item-Global
```php
$item_html = fetch_url($item_url)
$item_html = convertAlephEncoding($item_html) // ISO-8859-2 → UTF-8
```

### 5.2. Curățare HTML
Se elimină:
- Link-uri de navigare (`func=BOR-INFO`, `func=file`, `func=logout`)
- Header-ul paginii (`middlebar`)
- Text generic (`Permis de bibliotecă`, `Înregistrările selectate`)

### 5.3. Extragere Titlu și Autor

**METODA 1: Pattern Regex în HTML**
- Pattern: `Author\s+([^\.]+)\.\.?\s+([^:]+):\s*([^\/]+)\s*\/\s*(.+?)`
- Format: `Author. Title : Subtitle / Author Full`

**METODA 2: Parsing DOM (label-value pairs)**
- Caută `<td>` cu label "Title"/"Titlu" → valoarea din `<td>` următor
- Caută `<td>` cu label "Author"/"Autor" → valoarea din `<td>` următor
- Exclude text de navigare

**METODA 3: Căutare Text Lung**
- Caută în toate `<td>`-urile text între 20-500 caractere
- Exclude text de navigare și pattern-uri de cota/barcode
- Verifică că conține cel puțin 3 cuvinte

**METODA 4: Pattern în Tabel Aleph**
- Pattern: `Author. Title / ...` în formatul tabelului Aleph

### 5.4. Extragere Cota și Barcode

**🔥 METODA PRIORITARĂ: Comentarii HTML**
```regex
/<!--Localizare-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i
/<!--Barcod-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i
```

**Format Cota acceptat:**
- `I-14156`, `I 14156`, `I14156` → Pattern: `/^[A-Z]{1,3}[\s\-]?\d+([\s\-]\d+)?$/i`
- `RV I-94` → Pattern: `/^[A-Z]{2,3}\s+[A-Z]\s*-\s*\d+([\s\-]\d+)?$/i`
- `III-32073` → Pattern: `/^[A-Z]{1,3}\s*-\s*\d+([\s\-]\d+)?$/i`

**Format Barcode acceptat:**
- `RV00108`, `C013121`, `000029152-10` → Pattern: `/^([A-Z]{1,3})?\d{5,10}(-\d{1,2})?$/i`

**METODA FALLBACK: Parsing TD-uri**
- Caută în toate `<td>`-urile pattern-uri de cota/barcode
- Acceptă multiple formate (cu spații, cratime, etc.)

### 5.5. Extragere Alte Câmpuri
- **Colecție:** Caută text cu "depozit", "Cărți", "sala de lectură"
- **Bibliotecă:** Caută "Biblioteca Academiei" (exclude "Toate")
- **Status:** Caută "Pe raft", "Pentru împrumut", "Împrumutat", "Doar pentru SL"

---

## ✅ ETAPA 6: Verificare Finală - Cartea Există?

### 6.1. Verificare Titlu Generic/Eroare
**Mesaje de eroare care indică că cartea NU există:**
- `"Sfârşitul sesiunii"` / `"Sfârșitul sesiunii"`
- `"End of session"` / `"Session ended"`
- `"Sesiune expirată"` / `"Session expired"`
- `"Căutări anterioare"` / `"Previous searches"` ⚠️ **IMPORTANT**

**Dacă titlul conține unul dintre acestea:**
- ❌ Returnează `success: false` cu mesaj: `"Nu există această carte în baza de date Aleph"`

### 6.2. Verificare Titlu Gol/Prea Scurt
**Condiție:** `empty($data['titlu']) || strlen($titlu) < 3`

**Dacă DA:**
- ❌ Returnează `success: false` cu mesaj: `"Nu există această carte în baza de date Aleph"`

### 6.3. Succes
**Dacă titlul este valid (nu este generic, nu este gol, lungime >= 3):**
- ✅ Returnează `success: true` cu toate datele extrase

---

## 📊 SCENARII DE CAUTARE

### ✅ SCENARIUL 1: Barcode Existent (ex: `RV00108`)

1. **Detectare:** Pattern barcode → strategie `['BAR', 'LOC', 'WRD']`
2. **Căutare BAR:** Găsește rezultate → oprește căutarea
3. **Extragere Link:** Găsește link către `item-global` cu `sub_library=ACAD`
4. **Extragere Date:**
   - Titlu: ✅ Extras din HTML
   - Autor: ✅ Extras din HTML
   - Cota: ✅ Extras din comentarii HTML (`<!--Localizare-->`) sau TD-uri
   - Barcode: ✅ Extras din comentarii HTML (`<!--Barcod-->`) sau TD-uri
5. **Verificare:** Titlu valid → `success: true`

**Rezultat:** `{"success": true, "data": {"titlu": "...", "autor": "...", "cota": "RV I-94", "barcode": "RV00108", ...}}`

---

### ✅ SCENARIUL 2: Cota Existentă (ex: `RV I-94`)

1. **Detectare:** Pattern cota → strategie `['LOC', 'BAR', 'WRD']`
2. **Căutare LOC:** Găsește rezultate → oprește căutarea
3. **Extragere Link:** Găsește link către `item-global` cu `sub_library=ACAD`
4. **Extragere Date:**
   - Titlu: ✅ Extras din HTML
   - Autor: ✅ Extras din HTML
   - Cota: ✅ Extras din comentarii HTML (`<!--Localizare-->`) sau TD-uri
   - Barcode: ✅ Extras din comentarii HTML (`<!--Barcod-->`) sau TD-uri
5. **Verificare:** Titlu valid → `success: true`

**Rezultat:** `{"success": true, "data": {"titlu": "...", "autor": "...", "cota": "RV I-94", "barcode": "RV00108", ...}}`

---

### ❌ SCENARIUL 3: Barcode Inexistent (ex: `000017317-105uuu`)

1. **Detectare:** Pattern barcode → strategie `['BAR', 'LOC', 'WRD']`
2. **Căutare BAR:** Returnează "Your search found no results"
3. **Căutare LOC:** Returnează "Your search found no results"
4. **Căutare WRD:** Returnează "Your search found no results"
5. **Rezultat:** `success: false` cu mesaj: `"Nu s-au găsit rezultate pentru: 000017317-105uuu"`

**SAU** (dacă găsește link dar nu extrage date valide):

6. **Extragere Link:** Găsește link către `item-global` (dar pagina nu conține date reale)
7. **Extragere Date:**
   - Titlu: `"Căutări anterioare"` (titlu generic)
   - Autor: gol
   - Cota: gol
   - Barcode: gol
8. **Verificare:** Titlu este generic → `success: false`

**Rezultat:** `{"success": false, "mesaj": "Nu există această carte în baza de date Aleph", "data_partiala": {...}}`

---

### ❌ SCENARIUL 4: Cota Inexistentă (ex: `ABC-99999`)

1. **Detectare:** Pattern cota → strategie `['LOC', 'BAR', 'WRD']`
2. **Căutare LOC:** Returnează "Your search found no results"
3. **Căutare BAR:** Returnează "Your search found no results"
4. **Căutare WRD:** Returnează "Your search found no results"
5. **Rezultat:** `success: false` cu mesaj: `"Nu s-au găsit rezultate pentru: ABC-99999"`

**SAU** (dacă găsește link dar nu extrage date valide):

6. **Extragere Link:** Găsește link către `item-global` (dar pagina nu conține date reale)
7. **Extragere Date:**
   - Titlu: `"Căutări anterioare"` sau gol
   - Autor: gol
   - Cota: gol
   - Barcode: gol
8. **Verificare:** Titlu este generic sau gol → `success: false`

**Rezultat:** `{"success": false, "mesaj": "Nu există această carte în baza de date Aleph", "data_partiala": {...}}`

---

## 🔑 PUNCTE CHEIE

### 1. **Detectare Automată**
- Sistemul detectează automat dacă este cota sau barcode
- Aplică strategia corespunzătoare (BAR pentru barcode, LOC pentru cota)

### 2. **Fallback Automat**
- Dacă prima strategie nu găsește, încearcă următoarea
- Ordinea: BAR → LOC → WRD (pentru barcode) sau LOC → BAR → WRD (pentru cota)

### 3. **Extragere Robustă**
- Multiple metode de extragere link (5 metode diferite)
- Multiple metode de extragere date (4 metode pentru titlu/autor, 2 metode pentru cota/barcode)

### 4. **Verificare Strictă**
- Verifică dacă titlul este generic (`"Căutări anterioare"`)
- Verifică dacă titlul este gol sau prea scurt (< 3 caractere)
- **NU** verifică dacă barcode/autor/cota sunt goale separat - doar titlul contează

### 5. **Prioritizare Biblioteca Academiei**
- Adaugă automat `sub_library=ACAD` la toate link-urile
- Preferă link-uri care conțin deja `sub_library=ACAD`

---

## 🎯 CONCLUZIE

Logica este **simplă și eficientă**:
1. Detectează tipul de cod
2. Caută cu strategia corespunzătoare (cu fallback)
3. Extrage link către item-global
4. Extrage date din HTML (cu multiple metode)
5. Verifică dacă titlul este valid (nu generic, nu gol, lungime >= 3)

**Succes = Titlu valid** (nu contează dacă barcode/autor/cota sunt goale)

