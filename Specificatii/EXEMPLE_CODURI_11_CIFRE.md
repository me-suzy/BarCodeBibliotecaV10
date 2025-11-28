# 📋 Exemple Coduri Utilizator de 11 Cifre

## 🔍 Problema Identificată

Codurile Aleph **trebuie să aibă exact 12 cifre**, dar unele coduri pot avea **11 cifre** din diverse motive:
- Eroare la scanare
- Format vechi
- Coduri incomplete

---

## 📊 Structura Cod Aleph Corect

### Format Standard (12 cifre):
```
SS + NNNNNNNNN + X = 12 caractere

SS = 2 cifre (statut: 11-17)
NNNNNNNNN = 9 cifre (număr secvențial)
X = 1 cifră (padding/check digit)
```

### Exemple Corecte (12 cifre):
- ✅ `1100000010` → Statut `11`, Număr `000000001`, Check `0`
- ✅ `1200000010` → Statut `12`, Număr `000000001`, Check `0`
- ✅ `1500000010` → Statut `15`, Număr `000000001`, Check `0`

---

## ❌ Exemple Coduri de 11 Cifre (Incorecte)

### Problema:
Codurile de 11 cifre **NU sunt recunoscute** ca coduri Aleph și sunt tratate ca **cărți**.

### Exemple Coduri de 11 Cifre:

| Cod (11 cifre) | Problema | Cod Corect (12 cifre) |
|----------------|----------|----------------------|
| `12000000106` | Lipsă o cifră la final | `120000001060` |
| `1100000010` | Aici e OK (12 cifre) | `1100000010` ✅ |
| `150000001` | Lipsă 3 cifre | `150000001000` |
| `1200000106` | Lipsă o cifră în mijloc | `120000001060` |
| `120000001` | Lipsă 5 cifre | `120000001000` |

---

## 🔧 Cum Să Corectezi Codurile de 11 Cifre

### Opțiunea 1: Adaugă Cifră la Final

**Dacă codul are 11 cifre și începe cu 11-17:**

```sql
-- Exemplu: 12000000106 (11 cifre) → 120000001060 (12 cifre)
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '0')
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';
```

**Rezultat:**
- `12000000106` → `120000001060` ✅

---

### Opțiunea 2: Adaugă Cifră la Început (Dacă lipsește statutul)

**Dacă codul are 11 cifre dar nu începe cu 11-17:**

```sql
-- Exemplu: 0000000106 (11 cifre) → 120000001060 (12 cifre)
-- Aici trebuie să știi statutul corect!
UPDATE cititori 
SET cod_bare = CONCAT('12', cod_bare)  -- Adaugă statutul 12
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND cod_bare NOT REGEXP '^(11|12|13|14|15|16|17)';
```

---

### Opțiunea 3: Normalizare Completă

**Script pentru normalizare automată:**

```sql
USE biblioteca;

-- Găsește toate codurile de 11 cifre
SELECT cod_bare, nume, prenume 
FROM cititori 
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- Normalizează: adaugă '0' la final
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '0')
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';
```

---

## 📋 Exemple Concrete

### Exemplu 1: Cod `12000000106` (11 cifre)

**Analiză:**
- Lungime: 11 cifre ❌
- Statut: `12` (primele 2 cifre) ✅
- Număr: `000000010` (următoarele 9 cifre) ✅
- Lipsă: Ultima cifră (check digit)

**Corecție:**
```sql
UPDATE cititori 
SET cod_bare = '120000001060'  -- Adaugă '0' la final
WHERE cod_bare = '12000000106';
```

**Rezultat:**
- Cod vechi: `12000000106` (11 cifre) ❌
- Cod nou: `120000001060` (12 cifre) ✅
- Recunoscut ca: Cod Aleph cu statut `12`

---

### Exemplu 2: Cod `150000001` (9 cifre)

**Analiză:**
- Lungime: 9 cifre ❌
- Statut: `15` (primele 2 cifre) ✅
- Număr: `0000001` (următoarele 7 cifre) ✅
- Lipsă: 3 cifre (2 pentru număr + 1 check digit)

**Corecție:**
```sql
UPDATE cititori 
SET cod_bare = '150000001000'  -- Adaugă '000' la final
WHERE cod_bare = '150000001';
```

**Rezultat:**
- Cod vechi: `150000001` (9 cifre) ❌
- Cod nou: `150000001000` (12 cifre) ✅
- Recunoscut ca: Cod Aleph cu statut `15`

---

### Exemplu 3: Cod `11000001` (8 cifre)

**Analiză:**
- Lungime: 8 cifre ❌
- Statut: `11` (primele 2 cifre) ✅
- Număr: `000001` (următoarele 6 cifre) ✅
- Lipsă: 4 cifre (3 pentru număr + 1 check digit)

**Corecție:**
```sql
UPDATE cititori 
SET cod_bare = '110000001000'  -- Adaugă '0000' la final
WHERE cod_bare = '11000001';
```

**Rezultat:**
- Cod vechi: `11000001` (8 cifre) ❌
- Cod nou: `110000001000` (12 cifre) ✅
- Recunoscut ca: Cod Aleph cu statut `11`

---

## 🔍 Script de Verificare

### Găsește Toate Codurile Problemă:

```sql
USE biblioteca;

-- Coduri de 11 cifre (trebuie normalizate)
SELECT 
    cod_bare,
    LENGTH(cod_bare) as lungime,
    SUBSTRING(cod_bare, 1, 2) as statut_detectat,
    nume,
    prenume
FROM cititori 
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- Coduri de 10 cifre
SELECT 
    cod_bare,
    LENGTH(cod_bare) as lungime,
    SUBSTRING(cod_bare, 1, 2) as statut_detectat,
    nume,
    prenume
FROM cititori 
WHERE LENGTH(cod_bare) = 10 
AND cod_bare REGEXP '^[0-9]{10}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- Coduri de 9 cifre
SELECT 
    cod_bare,
    LENGTH(cod_bare) as lungime,
    SUBSTRING(cod_bare, 1, 2) as statut_detectat,
    nume,
    prenume
FROM cititori 
WHERE LENGTH(cod_bare) = 9 
AND cod_bare REGEXP '^[0-9]{9}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';
```

---

## 🛠️ Script de Normalizare Completă

```sql
USE biblioteca;

-- ============================================
-- NORMALIZARE CODURI CITITORI
-- ============================================

-- 1. Coduri de 11 cifre → Adaugă '0' la final
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '0')
WHERE LENGTH(cod_bare) = 11 
AND cod_bare REGEXP '^[0-9]{11}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- 2. Coduri de 10 cifre → Adaugă '00' la final
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '00')
WHERE LENGTH(cod_bare) = 10 
AND cod_bare REGEXP '^[0-9]{10}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- 3. Coduri de 9 cifre → Adaugă '000' la final
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '000')
WHERE LENGTH(cod_bare) = 9 
AND cod_bare REGEXP '^[0-9]{9}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- 4. Coduri de 8 cifre → Adaugă '0000' la final
UPDATE cititori 
SET cod_bare = CONCAT(cod_bare, '0000')
WHERE LENGTH(cod_bare) = 8 
AND cod_bare REGEXP '^[0-9]{8}$'
AND SUBSTRING(cod_bare, 1, 2) BETWEEN '11' AND '17';

-- Verificare finală
SELECT 
    LENGTH(cod_bare) as lungime,
    COUNT(*) as numar_cititori
FROM cititori 
WHERE cod_bare REGEXP '^[0-9]+$'
GROUP BY LENGTH(cod_bare)
ORDER BY lungime;
```

---

## 📊 Tabel Exemple

| Cod Original | Lungime | Statut | Corecție | Cod Corect |
|--------------|---------|--------|----------|------------|
| `12000000106` | 11 | `12` | Adaugă `0` | `120000001060` |
| `150000001` | 9 | `15` | Adaugă `000` | `150000001000` |
| `11000001` | 8 | `11` | Adaugă `0000` | `110000001000` |
| `1200000010` | 10 | `12` | Adaugă `00` | `120000001000` |
| `1200000010` | 10 | `12` | Adaugă `00` | `120000001000` |

---

## ⚠️ Atenție!

**Înainte de normalizare:**

1. **Fă backup:**
   ```sql
   CREATE TABLE cititori_backup AS SELECT * FROM cititori;
   ```

2. **Verifică codurile:**
   ```sql
   SELECT cod_bare, nume, prenume 
   FROM cititori 
   WHERE LENGTH(cod_bare) BETWEEN 8 AND 11
   AND cod_bare REGEXP '^[0-9]+$';
   ```

3. **Testează pe un singur cod:**
   ```sql
   -- Test pe un singur cititor
   UPDATE cititori 
   SET cod_bare = CONCAT(cod_bare, '0')
   WHERE cod_bare = '12000000106';
   ```

4. **Verifică rezultatul:**
   ```sql
   SELECT cod_bare FROM cititori WHERE cod_bare = '120000001060';
   ```

5. **Dacă e OK, aplică la toți**

---

## ✅ Rezumat

- **Coduri Aleph** trebuie să aibă **exact 12 cifre**
- **Coduri de 11 cifre** nu sunt recunoscute ca Aleph
- **Soluție:** Adaugă cifre la final pentru a ajunge la 12
- **Exemplu:** `12000000106` (11) → `120000001060` (12) ✅

