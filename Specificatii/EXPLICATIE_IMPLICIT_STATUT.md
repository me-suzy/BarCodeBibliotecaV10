# 📖 Explicație: Ce Înseamnă "Implicit" pentru Statut

## 🎯 Ce Înseamnă "Implicit"?

**"Implicit"** înseamnă **"automat, fără să fie specificat explicit"**.

### Pentru Coduri USER:

Când scanezi un cod care începe cu `USER` (ex: `USER011`):

1. **Sistemul detectează:** "Acesta este un cod USER"
2. **Sistemul atribuie automat:** Statutul `14` (fără să fie specificat în cod)
3. **Rezultat:** 
   - Statut: `14` (Nespecifici cu domiciliu în Iași)
   - Limită: `4 cărți`

**De ce statutul 14?**
- Codurile USER nu au statut în codul de bare
- Sistemul trebuie să atribuie un statut implicit (default)
- Statutul `14` este cel mai comun pentru cititori generali
- Este un "fallback" - dacă nu știe ce statut să folosească, folosește `14`

---

## 🔍 Comparație: USER vs Aleph

### Cod USER: `USER011`

**Procesare:**
1. Detectează: `USER` → cod USER
2. Statut: `14` (implicit/automat)
3. Limită: `4 cărți`

**În codul de bare:** ❌ NU există informație despre statut

---

### Cod Aleph: `1200000010`

**Procesare:**
1. Detectează: 12 cifre → cod Aleph
2. Extrage: Primele 2 cifre = `12`
3. Statut: `12` (Bibliotecari BARI) - **din cod!**
4. Limită: `15 cărți`

**În codul de bare:** ✅ Statutul este în cod (primele 2 cifre)

---

## 📊 Tabel Comparativ

| Tip Cod | Exemplu | Statut | De Unde Vine Statutul? |
|---------|---------|--------|------------------------|
| USER | `USER011` | `14` | **Implicit** (atribuit automat) |
| Aleph | `1200000010` | `12` | **Din cod** (primele 2 cifre) |
| Aleph | `1500000010` | `15` | **Din cod** (primele 2 cifre) |

---

## ⚙️ Cum Funcționează în Cod

### Funcția `extrageStatutDinCodBare()`:

```php
function extrageStatutDinCodBare($cod_bare) {
    // 1. Verifică dacă este cod USER
    if (preg_match('/^USER/i', $cod_bare)) {
        return '14'; // ← IMPLICIT (atribuit automat)
    }
    
    // 2. Verifică dacă este cod Aleph (12 cifre)
    if (strlen($cod_bare) === 12 && ctype_digit($cod_bare)) {
        $statut = substr($cod_bare, 0, 2); // ← DIN COD
        return $statut;
    }
    
    // 3. Fallback: statut implicit
    return '14'; // ← IMPLICIT (dacă nu se poate determina)
}
```

---

## 🎯 De Ce Statutul 14 ca Implicit?

Statutul `14` (Nespecifici cu domiciliu în Iași) este folosit ca implicit pentru că:

1. **Este cel mai comun statut** pentru cititori generali
2. **Are o limită moderată** (4 cărți) - nu prea mult, nu prea puțin
3. **Este sigur** - dacă nu știi statutul exact, e mai bine să fie restrictiv decât permisiv
4. **Poate fi schimbat manual** dacă e nevoie

---

## 🔧 Dacă Vrei Să Schimbi Statutul Implicit

### Pentru un anumit cititor USER:

```sql
UPDATE cititori 
SET statut = '16' 
WHERE cod_bare = 'USER011';
```

**Rezultat:**
- Statut: `16` (Personal departamente)
- Limită: `6 cărți` (în loc de 4)

---

### Pentru toți cititorii USER (opțional):

```sql
UPDATE cititori 
SET statut = '16' 
WHERE cod_bare LIKE 'USER%';
```

**Rezultat:**
- Toți cititorii USER vor avea statut `16`
- Limită: `6 cărți` pentru toți

---

## ✅ Rezumat

**"Implicit"** = **"Automat, fără să fie specificat"**

- **Coduri USER** → Statut `14` (implicit) → 4 cărți
- **Coduri Aleph** → Statut din cod (primele 2 cifre) → Limită variabilă
- **Poți schimba** statutul implicit manual dacă e nevoie

**Totul funcționează automat!** 🚀

