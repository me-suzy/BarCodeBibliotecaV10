# 📋 Tipuri de Coduri de Bare pentru Cititori

## 🔍 Două Serii de Coduri

Sistemul suportă **două tipuri de coduri de bare** pentru cititori:

---

## 1️⃣ Coduri USER (Pentru Testare)

### Format:
- **Prefix:** `USER`
- **Sufix:** Număr (ex: `001`, `011`, `123`)
- **Exemple:** `USER001`, `USER011`, `USER123`

### Caracteristici:
- ✅ Folosite pentru **testare și dezvoltare**
- ✅ **Nu au statut asociat** în codul de bare
- ✅ **Statut implicit:** `14` (Nespecifici cu domiciliu în Iași)
- ✅ **Limită implicită:** 4 cărți (conform statutului 14)

### Utilizare:
```sql
-- Exemplu cititor cu cod USER
INSERT INTO cititori (cod_bare, statut, nume, prenume) 
VALUES ('USER011', '14', 'Popescu', 'Ion');
```

**Rezultat:**
- Statut: `14` (automat)
- Limită: `4 cărți`

---

## 2️⃣ Coduri Aleph (Producție)

### Format:
- **12 cifre numerice**
- **Structură:** `SS + NNNNNNNNN + X`
  - `SS` = 2 cifre (statut cititor: 11-17)
  - `NNNNNNNNN` = 9 cifre (număr secvențial)
  - `X` = 1 cifră (padding sau check digit)

### Exemple:
- `1100000010` → Statut `11` (Personal Științific) → Limită **10 cărți**
- `1200000010` → Statut `12` (Bibliotecari BARI) → Limită **15 cărți**
- `1500000010` → Statut `15` (Nespecifici fără domiciliu) → Limită **2 cărți**

### Caracteristici:
- ✅ Folosite în **producție**
- ✅ **Statutul este în cod** (primele 2 cifre)
- ✅ **Limită diferită** pentru fiecare statut

### Utilizare:
```sql
-- Exemplu cititor cu cod Aleph
INSERT INTO cititori (cod_bare, statut, nume, prenume) 
VALUES ('1200000010', '12', 'Ionescu', 'Maria');
```

**Rezultat:**
- Statut: `12` (extras automat din cod)
- Limită: `15 cărți`

---

## 🔧 Cum Funcționează Detecția

### Funcția `extrageStatutDinCodBare()`

Această funcție detectează automat tipul de cod și extrage statutul:

```php
function extrageStatutDinCodBare($cod_bare) {
    // 1. Verifică dacă este cod USER
    if (preg_match('/^USER/i', $cod_bare)) {
        return '14'; // Statut implicit pentru USER
    }
    
    // 2. Verifică dacă este cod Aleph (12 cifre)
    if (strlen($cod_bare) === 12 && ctype_digit($cod_bare)) {
        $statut = substr($cod_bare, 0, 2); // Primele 2 cifre
        if ($statut >= 11 && $statut <= 17) {
            return $statut;
        }
    }
    
    // 3. Pentru coduri numerice simple (ex: 11000001)
    $statut = substr($cod_bare, 0, 2);
    if (is_numeric($statut) && $statut >= 11 && $statut <= 17) {
        return $statut;
    }
    
    // 4. Fallback: statut implicit
    return '14';
}
```

---

## 📊 Tabel Statuturi și Limite

| Cod Statut | Nume Statut | Limită | Exemple Coduri |
|------------|-------------|--------|----------------|
| 11 | Personal Științific Academie | 10 cărți | `1100000010`, `1100000020` |
| 12 | Bibliotecari BARI | 15 cărți | `1200000010`, `1200000020` |
| 13 | Angajați ARFI | 8 cărți | `1300000010`, `1300000020` |
| 14 | Nespecifici cu domiciliu în Iași | 4 cărți | `USER001`, `USER011`, `1400000010` |
| 15 | Nespecifici fără domiciliu în Iași | 2 cărți | `1500000010`, `1500000020` |
| 16 | Personal departamente | 6 cărți | `1600000010`, `1600000020` |
| 17 | ILL - Împrumut interbibliotecar | 20 cărți | `1700000010`, `1700000020` |

---

## 🎯 Exemple Practice

### Exemplu 1: Cititor USER

**Cod:** `USER011`

**Procesare:**
1. Sistemul detectează: `USER` → cod USER
2. Statut setat: `14` (automat)
3. Limită: `4 cărți`

**În aplicație:**
- Scanezi `USER011`
- Se afișează: "Statut: Nespecifici cu domiciliu în Iași"
- Se afișează: "0/4 cărți împrumutate"
- Poți împrumuta maxim 4 cărți

---

### Exemplu 2: Cititor Aleph

**Cod:** `1200000010`

**Procesare:**
1. Sistemul detectează: 12 cifre → cod Aleph
2. Statut extras: `12` (primele 2 cifre)
3. Limită: `15 cărți`

**În aplicație:**
- Scanezi `1200000010`
- Se afișează: "Statut: Bibliotecari BARI"
- Se afișează: "0/15 cărți împrumutate"
- Poți împrumuta maxim 15 cărți

---

### Exemplu 3: Cititor cu Statut Restrictiv

**Cod:** `1500000010`

**Procesare:**
1. Sistemul detectează: 12 cifre → cod Aleph
2. Statut extras: `15` (primele 2 cifre)
3. Limită: `2 cărți` (cel mai restrictiv!)

**În aplicație:**
- Scanezi `1500000010`
- Se afișează: "Statut: Nespecifici fără domiciliu în Iași"
- Se afișează: "0/2 cărți împrumutate"
- Poți împrumuta maxim **2 cărți**
- La a 3-a carte: **BLOCARE** cu mesaj clar

---

## ✅ Verificare în Baza de Date

### Verifică tipurile de coduri:

```sql
USE biblioteca;

-- Coduri USER
SELECT cod_bare, statut, nume, prenume 
FROM cititori 
WHERE cod_bare LIKE 'USER%';

-- Coduri Aleph (12 cifre)
SELECT cod_bare, statut, nume, prenume 
FROM cititori 
WHERE LENGTH(cod_bare) = 12 
AND cod_bare REGEXP '^[0-9]{12}$';

-- Distribuție pe statut
SELECT statut, COUNT(*) as numar 
FROM cititori 
GROUP BY statut 
ORDER BY statut;
```

---

## 🔧 Actualizare Statut pentru Coduri USER

Dacă vrei să schimbi statutul pentru coduri USER:

```sql
-- Setează toate codurile USER la statut 14 (implicit)
UPDATE cititori 
SET statut = '14' 
WHERE cod_bare LIKE 'USER%';

-- SAU setează manual pentru un anumit cititor
UPDATE cititori 
SET statut = '16' 
WHERE cod_bare = 'USER011';
```

---

## 📝 Note Importante

1. **Coduri USER** → Statut implicit `14` (4 cărți)
2. **Coduri Aleph** → Statut din primele 2 cifre (limită variabilă)
3. **Ambele tipuri** funcționează în același sistem
4. **Detecția este automată** - nu trebuie să specifici tipul manual
5. **Limitele diferite** se aplică automat în funcție de statut

---

## 🎉 Rezumat

- ✅ **Coduri USER** (`USER001`, `USER011`) → Statut `14` → 4 cărți
- ✅ **Coduri Aleph** (`1200000010`, `1500000010`) → Statut din cod → Limită variabilă
- ✅ **Sistemul detectează automat** tipul de cod
- ✅ **Limitele se aplică corect** pentru fiecare statut

**Totul funcționează automat!** 🚀

