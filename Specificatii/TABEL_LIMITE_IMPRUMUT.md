# 📊 Tabel Limite Împrumut pe Statut

## 🎯 Limite Cărți per Tip de Utilizator

| Cod Statut | Nume Statut | Limita Cărți | Descriere |
|------------|-------------|--------------|-----------|
| **11** | Personal Științific Academie | **10 cărți** | Personal științific al Academiei Române |
| **12** | Bibliotecari BARI | **15 cărți** | Bibliotecari din rețeaua BARI |
| **13** | Angajați ARFI | **8 cărți** | Angajați ARFI |
| **14** | Nespecifici cu domiciliu în Iași | **4 cărți** | Cititori nespecificați cu domiciliu în Iași |
| **15** | Nespecifici fără domiciliu în Iași | **2 cărți** | Cititori nespecificați fără domiciliu în Iași |
| **16** | Personal departamente | **6 cărți** | Personal din departamente |
| **17** | ILL - Împrumut interbibliotecar | **20 cărți** | Împrumut interbibliotecar |

---

## 📝 Detalii Coduri

### Coduri Aleph (12 cifre)
**Format:** `SSNNNNNNNNNX`
- **SS** = Statut (11-17)
- **NNNNNNNNN** = Număr cititor
- **X** = Check digit

**Exemple:**
- `120000001060` → Statut **12** → **15 cărți**
- `150000001000` → Statut **15** → **2 cărți**
- `110000001000` → Statut **11** → **10 cărți**

### Coduri USER (Alfanumerice)
**Format:** `USER###` (ex: `USER011`, `USER001`)

**Comportament:**
- Toate codurile USER primesc automat statut **14** (implicit)
- Limită: **4 cărți**

---

## 🔍 Verificare în Baza de Date

**Vezi toate limitele configurate:**

```sql
USE biblioteca;

SELECT 
    cod_statut,
    nume_statut,
    limita_totala as 'Limita Cărți',
    descriere
FROM statute_cititori
ORDER BY limita_totala DESC;
```

**Vezi distribuția cititorilor pe statut:**

```sql
SELECT 
    c.statut,
    s.nume_statut,
    s.limita_totala as 'Limita Cărți',
    COUNT(c.id) as 'Numar Cititori'
FROM cititori c
LEFT JOIN statute_cititori s ON c.statut = s.cod_statut
GROUP BY c.statut, s.nume_statut, s.limita_totala
ORDER BY s.limita_totala DESC;
```

---

## ✅ Exemple de Utilizare

### Exemplu 1: Bibliotecar BARI
- **Cod:** `120000001060`
- **Statut:** 12 (Bibliotecari BARI)
- **Limită:** 15 cărți
- **Poate împrumuta:** 0/15 → 1/15 → ... → 15/15 ✅
- **Nu poate împrumuta:** 16/15 ❌

### Exemplu 2: Cititor cu Domiciliu
- **Cod:** `USER011` sau `140000001000`
- **Statut:** 14 (Nespecifici cu domiciliu în Iași)
- **Limită:** 4 cărți
- **Poate împrumuta:** 0/4 → 1/4 → ... → 4/4 ✅
- **Nu poate împrumuta:** 5/4 ❌

### Exemplu 3: Cititor fără Domiciliu
- **Cod:** `150000001000`
- **Statut:** 15 (Nespecifici fără domiciliu în Iași)
- **Limită:** 2 cărți
- **Poate împrumuta:** 0/2 → 1/2 → 2/2 ✅
- **Nu poate împrumuta:** 3/2 ❌

### Exemplu 4: Cercetător Academie
- **Cod:** `110000001000`
- **Statut:** 11 (Personal Științific Academie)
- **Limită:** 10 cărți
- **Poate împrumuta:** 0/10 → 1/10 → ... → 10/10 ✅
- **Nu poate împrumuta:** 11/10 ❌

---

## 🎯 Rezumat Rapid

| Statut | Limita | Cine sunt |
|--------|--------|-----------|
| **17** | 20 | ILL - Interbibliotecar |
| **12** | 15 | Bibliotecari BARI |
| **11** | 10 | Personal Științific |
| **13** | 8 | Angajați ARFI |
| **16** | 6 | Personal Departamente |
| **14** | 4 | Nespecifici cu domiciliu (implicit pentru USER) |
| **15** | 2 | Nespecifici fără domiciliu |

---

## 📌 Notă Importantă

**Codurile USER** (ex: `USER011`, `USER001`) primesc **automat statut 14** și limită de **4 cărți**, indiferent de ce alt cod ar fi în baza de date.

**Codurile Aleph** (12 cifre) extrag statutul din primele 2 cifre și aplică limita corespunzătoare.

