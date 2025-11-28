# 📊 SUMAR STATUSURI CĂRȚI ȘI UTILIZATORI

## 📚 STATUSURI CĂRȚI

### Structura Tabelului `carti`
```sql
CREATE TABLE `carti` (
  `id` INT PRIMARY KEY,
  `cod_bare` VARCHAR(50) UNIQUE NOT NULL,
  `statut` VARCHAR(2) DEFAULT '01',  -- Cod statut Aleph (nu pentru disponibilitate)
  `titlu` VARCHAR(255) NOT NULL,
  `autor` VARCHAR(255),
  `isbn` VARCHAR(20),
  `cota` VARCHAR(50),
  `raft`, `nivel`, `pozitie` VARCHAR(10),  -- Localizare fizică
  `locatie_completa` VARCHAR(100) GENERATED,  -- "Raft X - Nivel Y - Poziția Z"
  `sectiune` VARCHAR(50),
  `observatii_locatie` TEXT,
  `data_adaugare` TIMESTAMP
)
```

### Statusuri Disponibile pentru Cărți

#### 1. **DISPONIBILĂ** ✅
- **Condiție**: Nu există împrumut activ pentru această carte
- **Verificare SQL**:
  ```sql
  SELECT COUNT(*) FROM imprumuturi 
  WHERE cod_carte = ? 
  AND status = 'activ' 
  AND data_returnare IS NULL
  ```
  - Dacă rezultatul = 0 → Cartea este **DISPONIBILĂ**
- **Afișare în aplicație**: 
  - 🟢 **Status: DISPONIBILĂ** (verde)
  - Poate fi împrumutată

#### 2. **ÎMPRUMUTATĂ** 📖
- **Condiție**: Există împrumut activ (status = 'activ' și data_returnare IS NULL)
- **Verificare SQL**:
  ```sql
  SELECT i.*, c.nume, c.prenume 
  FROM imprumuturi i
  JOIN cititori c ON i.cod_cititor = c.cod_bare
  WHERE i.cod_carte = ? 
  AND i.status = 'activ'
  AND i.data_returnare IS NULL
  ```
- **Afișare în aplicație**:
  - 🔴 **Status: ÎMPRUMUTATĂ** (roșu)
  - Se afișează de cine este împrumutată
  - Poate fi returnată doar de cititorul care a împrumutat-o

#### 3. **RETURNATĂ** 📥
- **Condiție**: Împrumutul are status = 'returnat' sau data_returnare IS NOT NULL
- **Notă**: După returnare, cartea devine automat **DISPONIBILĂ** pentru un nou împrumut

### Logică de Tranziție Statusuri Cărți

```
[DISPONIBILĂ] ──(împrumut)──> [ÎMPRUMUTATĂ]
     ↑                              │
     │                              │
     └────────(returnare)───────────┘
```

**Flux complet:**
1. **Scanare carte DISPONIBILĂ** + **Cititor activ** → **ÎMPRUMUT**
   - Se creează înregistrare în `imprumuturi` cu `status = 'activ'`
   - Cartea devine **ÎMPRUMUTATĂ**

2. **Scanare carte ÎMPRUMUTATĂ** de **același cititor** → **RETURNARE**
   - Se actualizează `imprumuturi` cu `status = 'returnat'` și `data_returnare = NOW()`
   - Cartea devine **DISPONIBILĂ**

3. **Scanare carte ÎMPRUMUTATĂ** de **alt cititor** → **EROARE**
   - Se afișează mesaj: "Cartea este împrumutată de: [Nume Cititor]"
   - Nu se permite împrumutul

---

## 👥 STATUSURI UTILIZATORI (CITITORI)

### Structura Tabelului `cititori`
```sql
CREATE TABLE `cititori` (
  `id` INT PRIMARY KEY,
  `cod_bare` VARCHAR(50) UNIQUE NOT NULL,  -- USER001 sau cod Aleph (12 cifre) sau 14016xxx
  `statut` VARCHAR(2) DEFAULT '14',  -- Cod statut Aleph (extras din cod sau setat manual)
  `nume` VARCHAR(100) NOT NULL,
  `prenume` VARCHAR(100) NOT NULL,
  `telefon` VARCHAR(20),
  `email` VARCHAR(100),
  `blocat` TINYINT(1) DEFAULT 0,  -- 0=activ, 1=blocat
  `motiv_blocare` VARCHAR(255),  -- Motivul blocării
  `data_inregistrare` TIMESTAMP,
  `ultima_vizare` DATE,  -- Data ultimei vizări anuale a permisului
  `nota_vizare` TEXT  -- Observații despre vizare
)
```

### Statusuri Disponibile pentru Cititori

#### 1. **ACTIV** ✅
- **Condiție**: `blocat = 0`
- **Permisiuni**:
  - ✅ Poate împrumuta cărți
  - ✅ Poate returna cărți
  - ✅ Acces complet la sistem
- **Afișare în aplicație**: Badge verde "ACTIV"

#### 2. **BLOCAT** 🚫
- **Condiție**: `blocat = 1`
- **Motiv**: 
  - Întârzieri la returnare
  - Alte motive (specificate în `motiv_blocare`)
- **Restricții**:
  - ❌ Nu poate împrumuta cărți noi
  - ✅ Poate returna cărțile existente
- **Afișare în aplicație**: Badge roșu "BLOCAT"

#### 3. **VIZARE PERMIS** 📋
- **Câmp**: `ultima_vizare` (DATE)
- **Scop**: Verificare anuală a permisului de bibliotecă
- **Statusuri**:
  - **VIZAT** ✅: `ultima_vizare` este setat și în termen
  - **NEVIZAT** ❌: `ultima_vizare` este NULL sau expirat
- **Afișare**: Pagina `status_vizari.php` cu lista completă

### Tipuri de Coduri de Bare pentru Cititori

#### 📌 DOUĂ Tipuri de Coduri pentru Cititori Normal

##### 1. **Coduri USER** (ex: `USER030`, `USER001`, `USER011`)
- **Format**: `USER` + 3 cifre (alfanumeric)
- **Exemple**: `USER030`, `USER001`, `USER011`, `USER021`
- **Tip**: Cod generat de sistem (alfanumeric)
- **Statut**: **'14' (implicit automat)** - Nu se extrage din cod, este întotdeauna statut 14
- **Limită**: **4 cărți** (corespunzătoare statutului 14)
- **Detectare**: Sistemul recunoaște automat codurile care încep cu "USER"
- **Notă**: **Toate codurile USER primesc automat statut 14 și limită de 4 cărți**, indiferent de numerele de după "USER"

##### 2. **Coduri Biblioteca Academiei** (ex: `14016838`, `14016038`)
- **Format**: 8 cifre numerice, începe cu `14016` + 3 cifre
- **Exemple**: `14016838`, `14016038`, `14016001`
- **Tip**: Cod specific Biblioteca Academiei Române - Iași
- **Statut**: **'14' (implicit automat)** - Nu se extrage din cod, este întotdeauna statut 14
- **Limită**: **4 cărți** (corespunzătoare statutului 14)
- **Detectare**: Sistemul recunoaște automat codurile de 8 cifre care încep cu "14016"
- **Notă**: **Toate codurile Biblioteca Academiei (14016xxx) primesc automat statut 14 și limită de 4 cărți**

**✅ Ambele tipuri de coduri (USER și Biblioteca Academiei) sunt pentru cititori normal și primesc aceeași limită: Statut 14 → 4 cărți**

---

#### 3. **Coduri Aleph** (12 cifre numerice) - Pentru Statuturi Speciale

- **Format**: 12 cifre numerice (ex: `120000001060`, `150000001000`, `110000001000`)
- **Tip**: Cod din sistemul Aleph
- **Statut**: **Se extrage automat din primele 2 cifre** ale codului (11-17)
- **Limită**: Depinde de statutul extras (vezi tabelul de limite)
- **Detectare**: Sistemul recunoaște automat codurile de exact 12 cifre numerice
- **Exemple**: 
  - `120000001060` → Primele 2 cifre = `12` → Statut **12** → Limită **15 cărți**
  - `150000001000` → Primele 2 cifre = `15` → Statut **15** → Limită **2 cărți**
  - `110000001000` → Primele 2 cifre = `11` → Statut **11** → Limită **10 cărți**

### Logică de Verificare Permisiuni Împrumut

Funcția `poateImprumuta($pdo, $cod_cititor, $numar_carti_imprumutate)` verifică:

1. **Status cititor** (`blocat`):
   - Dacă `blocat = 1` → ❌ Nu poate împrumuta

2. **Număr cărți împrumutate**:
   - Verifică câte cărți active are cititorul
   - Compară cu limita maximă (în funcție de statut)

3. **Limite împrumut** (în funcție de `statut`):
   - Fiecare statut are o limită specifică (vezi tabelul de mai jos)

### 📊 Tabel Limite Împrumut pe Statut

| Cod | Nume Statut | Limita Cărți | Descriere |
|-----|-------------|--------------|-----------|
| **17** | ILL - Împrumut interbibliotecar | **20 cărți** | Împrumut interbibliotecar |
| **12** | Bibliotecari BARI | **15 cărți** | Bibliotecari din rețeaua BARI |
| **11** | Personal Științific Academie | **10 cărți** | Personal științific al Academiei Române |
| **13** | Angajați ARFI | **8 cărți** | Angajați ARFI |
| **16** | Personal departamente | **6 cărți** | Personal din departamente |
| **14** | Nespecifici cu domiciliu în Iași | **4 cărți** | Cititori nespecificați cu domiciliu în Iași **(implicit pentru USER și Biblioteca Academiei)** |
| **15** | Nespecifici fără domiciliu în Iași | **2 cărți** | Cititori nespecificați fără domiciliu în Iași |

**⚠️ NOTĂ IMPORTANTĂ - Două Tipuri de Coduri pentru Cititori Normal:**

1. **Coduri USER** (ex: `USER030`, `USER001`, `USER011`)
   - **Tip cod:** USER (alfanumeric)
   - **Statut:** 14 (implicit automat)
   - **Limită:** 4 cărți

2. **Coduri Biblioteca Academiei** (ex: `14016838`, `14016038`)
   - **Tip cod:** Biblioteca Academiei (8 cifre numerice, începe cu 14016)
   - **Statut:** 14 (implicit automat)
   - **Limită:** 4 cărți

**✅ Ambele tipuri primesc aceeași limită: Statut 14 → 4 cărți**

3. **Codurile Aleph** (ex: `120000001060`, `150000001000`)
   - **Tip cod:** Aleph (12 cifre numerice)
   - **Statut:** Se extrage din primele 2 cifre (11-17)
   - **Limită:** Depinde de statut (vezi tabelul)

### 🔍 Exemple Practice de Limite

#### Exemplu 1: Bibliotecar BARI
- **Cod:** `120000001060`
- **Statut:** 12 (Bibliotecari BARI)
- **Limită:** 15 cărți
- **Poate împrumuta:** 0/15 → 1/15 → ... → 15/15 ✅
- **Nu poate împrumuta:** 16/15 ❌

#### Exemplu 2a: Cod USER (ex: `USER030`, `USER001`, `USER011`)
- **Tip cod:** USER (alfanumeric, generat de sistem)
- **Exemple:** `USER030`, `USER001`, `USER011`, `USER021`
- **Statut:** **14 (implicit automat)** - Toate codurile USER primesc automat statut 14
- **Limită:** **4 cărți** (corespunzătoare statutului 14)
- **Poate împrumuta:** 0/4 → 1/4 → ... → 4/4 ✅
- **Nu poate împrumuta:** 5/4 ❌
- **Notă importantă:** Orice cod care începe cu "USER" (ex: USER030, USER001, USER011) primește automat statut 14 și limită de 4 cărți

#### Exemplu 2b: Cod Biblioteca Academiei (ex: `14016838`, `14016038`)
- **Tip cod:** Biblioteca Academiei (8 cifre numerice)
- **Exemple:** `14016838`, `14016038`, `14016001`
- **Statut:** **14 (implicit automat)** - Toate codurile Biblioteca Academiei primesc automat statut 14
- **Limită:** **4 cărți** (corespunzătoare statutului 14)
- **Poate împrumuta:** 0/4 → 1/4 → ... → 4/4 ✅
- **Nu poate împrumuta:** 5/4 ❌
- **Notă importantă:** Orice cod de 8 cifre care începe cu "14016" (ex: 14016838, 14016038) primește automat statut 14 și limită de 4 cărți

**✅ ATENȚIE: Ambele tipuri (USER și Biblioteca Academiei) sunt pentru cititori normal și primesc aceeași limită: Statut 14 → 4 cărți**

#### Exemplu 3: Cititor fără domiciliu
- **Cod:** `150000001000`
- **Statut:** 15
- **Limită:** 2 cărți
- **Poate împrumuta:** 0/2 → 1/2 → 2/2 ✅
- **Nu poate împrumuta:** 3/2 ❌

#### Exemplu 4: Cercetător (cod `110000001000`)
- **Statut:** 11
- **Limită:** 10 cărți
- **Poate împrumuta:** 0/10 → 1/10 → ... → 10/10 ✅
- **Nu poate împrumuta:** 11/10 ❌

### ⚙️ Funcționare Limite în Aplicație

**Verificare automată la scanare carte:**

1. **Sistemul detectează tipul de cod și statutul:**
   - **Coduri USER** (`USER030`, `USER001`, `USER011`, etc.) → Tip: USER → **Statut:** 14 (implicit automat) → **Limită:** 4 cărți
   - **Coduri Biblioteca Academiei** (`14016838`, `14016038`, etc.) → Tip: Biblioteca Academiei → **Statut:** 14 (implicit automat) → **Limită:** 4 cărți
   - **Coduri Aleph** (`120000001060`, `150000001000`, etc.) → Tip: Aleph → **Statut:** Se extrage din primele 2 cifre ale codului (11-17) → **Limită:** Depinde de statut

2. **Aplică limita corespunzătoare:**
   - Se caută în tabelul `statute_cititori` câmpul `limita_totala`
   - Se compară cu numărul de cărți împrumutate active

3. **Afișare în interfață:**
   - **"X/Y cărți împrumutate"** (unde Y = limita pentru statut)
   - **Badge de status:**
     - 🟢 OK dacă X < Y
     - 🟡 Atenție dacă X = Y-1
     - 🔴 Blocat dacă X >= Y

4. **Blocare împrumut:**
   - Dacă limita este depășită → ❌ Nu se permite împrumutul
   - Mesaj: "🚫 Utilizatorul a atins limita de cărți împrumutate! Nu mai puteți împrumuta: aveți deja X cărți, limita maximă este Y."

### 📊 Structura Tabelului `statute_cititori`

```sql
CREATE TABLE `statute_cititori` (
  `cod_statut` VARCHAR(2) PRIMARY KEY,
  `nume_statut` VARCHAR(100) NOT NULL,
  `limita_totala` INT DEFAULT 6,
  `descriere` TEXT,
  `limita_depozit_carte` INT DEFAULT 0,
  `limita_depozit_periodice` INT DEFAULT 0,
  `limita_sala_lectura` INT DEFAULT 0,
  `limita_colectii_speciale` INT DEFAULT 0
)
```

**Verificare limite în baza de date:**
```sql
SELECT 
    cod_statut,
    nume_statut,
    limita_totala as 'Limita Cărți',
    descriere
FROM statute_cititori
ORDER BY limita_totala DESC;
```

**Distribuția cititorilor pe statut:**
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

## 📖 STATUSURI ÎMPRUMUTURI

### Structura Tabelului `imprumuturi`
```sql
CREATE TABLE `imprumuturi` (
  `id` INT PRIMARY KEY,
  `cod_cititor` VARCHAR(50) NOT NULL,
  `cod_carte` VARCHAR(50) NOT NULL,
  `data_imprumut` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `data_scadenta` DATE,  -- Data scadenței (calculată automat)
  `data_returnare` TIMESTAMP NULL,  -- NULL = ne returnat
  `status` ENUM('activ', 'returnat') DEFAULT 'activ'
)
```

### Statusuri Disponibile pentru Împrumuturi

#### 1. **ACTIV** 📖
- **Condiție**: 
  - `status = 'activ'`
  - `data_returnare IS NULL`
- **Semnificație**: Cartea este încă împrumutată
- **Afișare**: 
  - În lista împrumuturi active
  - Cu badge de status (OK / Atenție / Întârziere)

#### 2. **RETURNAT** ✅
- **Condiție**: 
  - `status = 'returnat'` SAU
  - `data_returnare IS NOT NULL`
- **Semnificație**: Cartea a fost returnată
- **Notă**: După returnare, cartea devine disponibilă pentru un nou împrumut

### Logică Automată de Status

**La creare împrumut:**
```sql
INSERT INTO imprumuturi (cod_cititor, cod_carte, data_imprumut, data_scadenta, status)
VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'activ')
```

**La returnare:**
```sql
UPDATE imprumuturi
SET status = 'returnat', data_returnare = NOW()
WHERE cod_carte = ? AND cod_cititor = ? AND status = 'activ'
```

**Logică automată în `editare_imprumut.php`:**
- Dacă `status = 'returnat'` și `data_returnare` este gol → setează `data_returnare = NOW()`
- Dacă `data_returnare` este setat → `status` devine automat `'returnat'`

### Statusuri Vizuale în Aplicație

#### Badge-uri pentru Împrumuturi Active:

1. **OK** 🟢 (badge-success)
   - `zile_imprumut <= 14` zile
   - Totul în regulă

2. **Atenție** 🟡 (badge-warning)
   - `14 < zile_imprumut <= 30` zile
   - Se apropie de termen

3. **Întârziere** 🔴 (badge-danger)
   - `zile_imprumut > 30` zile
   - Depășit termenul de returnare

---

## 🔄 FLUX COMPLET: ÎMPRUMUT → RETURNARE

### Scenariu 1: Împrumut Normal

```
1. Scanare CITITOR → Cititor activ setat în sesiune
2. Scanare CARTE DISPONIBILĂ → 
   ├─ Verificare: Cititor poate împrumuta? (blocat = 0, limita OK)
   ├─ Creare împrumut: status = 'activ', data_returnare = NULL
   └─ Cartea devine ÎMPRUMUTATĂ

3. Scanare CARTE ÎMPRUMUTATĂ (de același cititor) →
   ├─ Verificare: Este împrumutată de cititorul activ?
   ├─ Actualizare: status = 'returnat', data_returnare = NOW()
   └─ Cartea devine DISPONIBILĂ
```

### Scenariu 2: Încercare Împrumut Carte deja Împrumutată

```
1. Scanare CITITOR → Cititor activ
2. Scanare CARTE ÎMPRUMUTATĂ (de alt cititor) →
   └─ EROARE: "Cartea este împrumutată de: [Nume Alt Cititor]"
   └─ Nu se permite împrumutul
```

### Scenariu 3: Cititor Blocat

```
1. Scanare CITITOR BLOCAT → 
   ├─ Cititor activ setat (cu restricții)
   └─ Mesaj: "Cititor blocat - nu poate împrumuta cărți noi"

2. Scanare CARTE →
   └─ EROARE: "Nu puteți împrumuta: cititor blocat"
   └─ Poate doar returna cărțile existente
```

---

## 📊 RAPOARTE ȘI STATISTICI

### Rapoarte Disponibile

1. **Raport Împrumuturi Active** (`imprumuturi.php`)
   - Listă toate împrumuturile cu `status = 'activ'`
   - Grupat pe cititori
   - Cu badge-uri de status (OK / Atenție / Întârziere)

2. **Raport Întârzieri** (`raport_intarzieri.php`)
   - Împrumuturi active cu `zile_imprumut > 14`
   - Pentru urmărire și notificări

3. **Raport Prezență** (`raport_prezenta.php`)
   - Statistici despre utilizarea bibliotecii
   - Câți cititori au venit în perioada selectată

4. **Status Vizări** (`status_vizari.php`)
   - Listă toți cititorii cu status vizare
   - ✅ Vizat / ❌ Nevizat

5. **Top Cărți** (`raport_top_carti.php`)
   - Cărțile cele mai împrumutate
   - Cărțile niciodată împrumutate

---

## 🎯 REGULI DE BUSINESS

### 1. Disponibilitate Carte
- O carte este **DISPONIBILĂ** doar dacă nu există împrumut activ
- Nu există conceptul de "rezervare" în sistemul actual

### 2. Limite Împrumut pe Statut
- **Limita depinde de `statut`-ul cititorului:**
  - Statut **17** (ILL): **20 cărți**
  - Statut **12** (Bibliotecari BARI): **15 cărți**
  - Statut **11** (Personal Științific): **10 cărți**
  - Statut **13** (Angajați ARFI): **8 cărți**
  - Statut **16** (Personal departamente): **6 cărți**
  - Statut **14** (Nespecifici cu domiciliu): **4 cărți** ⭐ **IMPLICIT pentru USER**
  - Statut **15** (Nespecifici fără domiciliu): **2 cărți**
- **Se verifică la fiecare împrumut nou:**
  - Sistemul calculează automat numărul de cărți împrumutate active
  - Compară cu limita pentru statutul cititorului
  - Blochează împrumutul dacă limita este depășită
- **Cititorul blocat** (`blocat = 1`) nu poate împrumuta cărți noi, indiferent de limită
- **Afișare în interfață:** "X/Y cărți împrumutate" (unde Y = limita pentru statut)

### 3. Returnare
- Doar cititorul care a împrumutat cartea o poate returna
- La returnare, statusul devine automat `'returnat'`
- Cartea devine imediat disponibilă pentru un nou împrumut

### 4. Vizare Permis
- `ultima_vizare` este opțional
- Nu blochează împrumuturile dacă nu este setat
- Folosit pentru raportare și statistici

### 5. Blocare Cititor
- `blocat = 1` → Nu poate împrumuta cărți noi
- Poate returna cărțile existente
- Motivul blocării se stochează în `motiv_blocare`

---

## 🔍 VERIFICĂRI AUTOMATE

### La Scanare Carte

1. **Cartea există în baza de date?**
   - Dacă NU → Căutare în Aleph → Import automat (dacă există cititor activ)

2. **Cartea este împrumutată?**
   - Dacă DA → De cine? → Returnare sau Eroare
   - Dacă NU → Disponibilă pentru împrumut

3. **Cititor activ poate împrumuta?**
   - Verificare `blocat = 0`
   - Extragere statut din cod (coduri Aleph) sau statut implicit '14' (coduri USER/14016xxx)
   - Obținere limită pentru statut din tabelul `statute_cititori`
   - Verificare număr cărți împrumutate active < limită
   - Dacă limita este depășită → ❌ Blocare împrumut cu mesaj explicativ

### La Scanare Cititor

1. **Cititorul există în baza de date?**
   - Dacă NU → Căutare în Aleph → Import automat

2. **Cititorul este blocat?**
   - Dacă DA → Setare cititor activ cu restricții
   - Dacă NU → Setare cititor activ normal

3. **Număr cărți împrumutate**
   - Calculare automată
   - Afișare în box-ul "Cititor activ"

---

## 📝 NOTIȚE IMPORTANTE

1. **Statusul cărții NU este stocat direct în tabelul `carti`**
   - Se calculează dinamic din tabelul `imprumuturi`
   - O carte poate avea mai multe înregistrări în `imprumuturi` (istoric)
   - Doar împrumuturile cu `status = 'activ'` și `data_returnare IS NULL` contează

2. **Câmpul `statut` din `carti` și `cititori`**
   - NU este pentru disponibilitate/blocare
   - Este cod Aleph (2 cifre) pentru categorisire
   - Default: '01' pentru cărți, '14' pentru cititori
   - **Pentru cititori:** Statutul determină limita de împrumut din tabelul `statute_cititori`
   - **Pentru coduri USER** (ex: `USER030`, `USER001`, `USER011`): Tip cod: USER → Statutul este întotdeauna '14' (implicit automat) → Limită: 4 cărți
   - **Pentru coduri Biblioteca Academiei** (ex: `14016838`, `14016038`): Tip cod: Biblioteca Academiei → Statutul este întotdeauna '14' (implicit automat) → Limită: 4 cărți
   - **Pentru coduri Aleph** (ex: `120000001060`, `150000001000`): Tip cod: Aleph → Statutul este extras din primele 2 cifre ale codului (11-17) → Limită: Depinde de statut (vezi tabelul)

3. **Statusul împrumutului este sincronizat automat**
   - `status = 'returnat'` → `data_returnare` este setat
   - `data_returnare IS NOT NULL` → `status` devine 'returnat'

4. **Sesiunea păstrează starea**
   - `$_SESSION['cititor_activ']` → Cititorul selectat
   - `$_SESSION['carte_scanata']` → Cartea scanată (opțional)
   - Resetare manuală prin buton "X" sau link "Resetează cititor"

---

## 🎯 REZUMAT RAPID - Limite Împrumut pe Statut

### Tabel Limite (Sortat Descrescător)

| Statut | Limita | Nume Statut | Cine sunt |
|--------|--------|-------------|-----------|
| **17** | **20 cărți** | ILL - Împrumut interbibliotecar | Împrumut interbibliotecar |
| **12** | **15 cărți** | Bibliotecari BARI | Bibliotecari din rețeaua BARI |
| **11** | **10 cărți** | Personal Științific Academie | Personal științific al Academiei Române |
| **13** | **8 cărți** | Angajați ARFI | Angajați ARFI |
| **16** | **6 cărți** | Personal departamente | Personal din departamente |
| **14** | **4 cărți** ⭐ | Nespecifici cu domiciliu în Iași | Cititori nespecificați cu domiciliu în Iași **(implicit pentru USER)** |
| **15** | **2 cărți** | Nespecifici fără domiciliu în Iași | Cititori nespecificați fără domiciliu în Iași |

### Puncte Cheie

✅ **Cel mai mult:** Statut **17** → **20 cărți** (ILL - Împrumut interbibliotecar)  
✅ **Cel mai puțin:** Statut **15** → **2 cărți** (Nespecifici fără domiciliu)  
✅ **Două tipuri de coduri pentru cititori normal (ambele cu statut 14 și 4 cărți):** 
   - Coduri **USER** (ex: `USER030`, `USER001`, `USER011`) → Statut **14** → **4 cărți**
   - Coduri **Biblioteca Academiei** (ex: `14016838`, `14016038`) → Statut **14** → **4 cărți**

### Verificare în Aplicație

Când scanezi un cod:
1. ✅ Sistemul detectează **tipul de cod**:
   - **Cod USER** (ex: `USER030`, `USER001`, `USER011`) → Statut **14** (implicit) → **4 cărți**
   - **Cod Biblioteca Academiei** (ex: `14016838`, `14016038`) → Statut **14** (implicit) → **4 cărți**
   - **Cod Aleph** (ex: `120000001060`, `150000001000`) → Statut extras din primele 2 cifre (11-17) → **Limită** depinde de statut
2. ✅ Aplică limita corespunzătoare din tabelul `statute_cititori` pentru statutul detectat
3. ✅ Afișează: **"X/Y cărți împrumutate"** (unde Y = limita pentru statut)
4. ✅ Blochează împrumutul dacă limita este depășită (X >= Y)

### Funcția de Verificare

**`poateImprumuta($pdo, $cod_cititor, $numar_carti_imprumutate)`**
- Extrage statutul din codul de bare
- Obține limita pentru statut din `statute_cititori`
- Compară `numar_carti_imprumutate` cu `limita`
- Returnează: `['poate' => bool, 'limita' => int, 'statut' => string, 'nume_statut' => string, 'ramase' => int]`

---

**Dezvoltat pentru:** Biblioteca Academiei Române - Iași  
**Dezvoltare web:** Neculai Ioan Fantanaru  
**Data:** 2025-11-22

