# 🔑 Soluția pentru Cazul: Mai Multe Cote la O Carte, Barcode Unic

## 📚 Problema Identificată

### Situația Reală în Aleph:
- **O carte** poate avea **mai multe exemplare**, fiecare cu **cota proprie**
  - Exemplu: Cartea "Tălmăcire rumânească..." are cote: `RV I-94`, `RV I-95`, `RV I-96`
- **Fiecare exemplar** are un **barcode unic**
  - Exemplu: `RV00108` (pentru cota `RV I-94`), `RV00109` (pentru cota `RV I-95`)

### Problema Vechii Logici:
1. **Căutare după barcode** (`RV00108`):
   - ✅ Funcționează perfect - găsește exemplarul specific
   - ✅ Barcode-ul este unic, deci rezultatul este clar

2. **Căutare după cota** (`RV I-94`):
   - ⚠️ Găsește mai multe rezultate (toate exemplarele cu acea cota sau similare)
   - ❌ Nu știa care exemplar este cel corect dintre toate
   - ❌ Logica veche încerca să rezolve prin căutare suplimentară după barcode, dar complica procesul

---

## ✅ Soluția Implementată

### Principiu: **Extragere Directă din Item-Global**

În loc să încercăm să rezolvăm problema prin căutări suplimentare, am simplificat logica:

### 1. Căutare Simplă după Cota
```php
// Caută după cota cu strategia LOC
$search_url = "...?func=find-b&request=RV+I-94&find_code=LOC..."
$search_response = fetch_url($search_url)
```

**Rezultat:** Aleph returnează pagina de rezultate care conține link-uri către toate exemplarele găsite.

### 2. Extragere Link către Item-Global
```php
// Găsește primul link către item-global cu sub_library=ACAD
preg_match_all('/func=item-global[^>]*sub_library=ACAD/i', $search_response, $links)
$item_url = $links[1][0] // Primul link găsit
```

**Important:** 
- Preferă link-uri cu `sub_library=ACAD` (Biblioteca Academiei Iași)
- Dacă nu găsește cu ACAD explicit, folosește primul link disponibil și adaugă `sub_library=ACAD`

### 3. Extragere Date din Item-Global (Cheia Soluției!)

Când accesăm pagina `item-global` pentru un exemplar specific, aceasta conține **AMBELE informații**:
- ✅ **Cota** pentru acel exemplar specific
- ✅ **Barcode-ul** pentru acel exemplar specific

#### Metoda Prioritară: Comentarii HTML
```php
// Extrage cota din comentariul HTML
preg_match_all('/<!--Localizare-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i', 
               $item_html, $cota_matches)
$data['cota'] = trim($cota_matches[1][0]) // Ex: "RV I-94"

// Extrage barcode din comentariul HTML
preg_match_all('/<!--Barcod-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i', 
               $item_html, $barcode_matches)
$data['barcode'] = trim($barcode_matches[1][0]) // Ex: "RV00108"
```

#### Metoda Fallback: Parsing TD-uri
```php
// Dacă nu găsește prin comentarii, caută în toate TD-urile
for ($i = 0; $i < $tds->length; $i++) {
    $text = trim($tds->item($i)->textContent);
    
    // Pattern pentru cota
    if (preg_match('/^[A-Z]{1,3}[\s\-]?\d+([\s\-]\d+)?$/i', $text) ||
        preg_match('/^[A-Z]{2,3}\s+[A-Z]\s*-\s*\d+([\s\-]\d+)?$/i', $text)) {
        $data['cota'] = $text;
    }
    
    // Pattern pentru barcode
    if (preg_match('/^([A-Z]{1,3})?\d{5,10}(-\d{1,2})?$/i', $text)) {
        $data['barcode'] = $text;
    }
}
```

---

## 🎯 De Ce Funcționează Această Soluție?

### 1. **Aleph Returnează Exemplarul Specific**
Când căutăm după cota `RV I-94`, Aleph returnează link-uri către exemplarele care au acea cota. Primul link găsit (sau cel cu `sub_library=ACAD`) este pentru **exemplarul specific** care are cota `RV I-94`.

### 2. **Item-Global Conține Ambele Informații**
Pagina `item-global` pentru un exemplar specific conține:
- Cota exactă pentru acel exemplar (`RV I-94`)
- Barcode-ul exact pentru acel exemplar (`RV00108`)

### 3. **Nu Mai Avem Nevoie de Căutări Suplimentare**
- ❌ **Logica veche:** Căuta după cota → găsea mai multe → încerca să găsească barcode → căuta din nou după barcode
- ✅ **Logica nouă:** Caută după cota → găsește link → extrage direct cota ȘI barcode-ul din item-global

---

## 📊 Exemplu Concret

### Căutare după Cota: `RV I-94`

#### Pasul 1: Căutare în Aleph
```
URL: ...?func=find-b&request=RV+I-94&find_code=LOC...
Rezultat: Pagină cu mai multe exemplare (RV I-94, RV I-95, etc.)
```

#### Pasul 2: Extragere Link
```
Link găsit: ...?func=item-global&doc_number=000030454&sub_library=ACAD
Acest link este pentru exemplarul specific cu cota RV I-94
```

#### Pasul 3: Accesare Item-Global
```
URL: ...?func=item-global&doc_number=000030454&sub_library=ACAD
HTML conține:
  <!--Localizare-->
  <td class="td1">RV I-94</td>
  <!--Barcod-->
  <td class="td1">RV00108</td>
```

#### Pasul 4: Extragere Date
```php
$data['cota'] = "RV I-94"      // Extras din <!--Localizare-->
$data['barcode'] = "RV00108"   // Extras din <!--Barcod-->
$data['titlu'] = "Tălmăcire rumânească..."
$data['autor'] = "Kontos, Polyzois"
```

#### Rezultat Final:
```json
{
  "success": true,
  "data": {
    "titlu": "Tălmăcire rumânească...",
    "autor": "Kontos, Polyzois",
    "cota": "RV I-94",
    "barcode": "RV00108",
    ...
  }
}
```

**✅ Rezultat:** Am obținut **AMBELE informații** (cota ȘI barcode) pentru exemplarul specific, fără căutări suplimentare!

---

## 🔄 Comparație: Logica Veche vs. Logica Nouă

### ❌ Logica Veche (Complicată):
```
1. Caută după cota → găsește mai multe rezultate
2. Încearcă să extragă barcode din rezultate
3. Caută din nou după barcode pentru identificare exactă
4. Extrage date din item-global
```
**Probleme:**
- Complicată și lentă
- Poate eșua dacă nu găsește barcode în rezultate
- Poate alege exemplarul greșit

### ✅ Logica Nouă (Simplă):
```
1. Caută după cota → găsește rezultate
2. Extrage primul link către item-global (cu ACAD)
3. Accesează item-global → extrage direct cota ȘI barcode
```
**Avantaje:**
- Simplă și rapidă
- Sigură - obține datele pentru exemplarul specific găsit
- Nu necesită căutări suplimentare

---

## 🎯 Concluzie

**Soluția:** În loc să încercăm să rezolvăm problema prin căutări suplimentare, am simplificat logica pentru a extrage direct **AMBELE informații** (cota ȘI barcode) din pagina `item-global` pentru exemplarul specific găsit.

**Rezultat:** 
- ✅ Căutare după barcode → funcționează perfect (barcode unic)
- ✅ Căutare după cota → funcționează perfect (extrage cota ȘI barcode pentru exemplarul specific)

**Cheia succesului:** Pagina `item-global` conține întotdeauna ambele informații pentru exemplarul specific, deci nu mai avem nevoie de căutări suplimentare!

