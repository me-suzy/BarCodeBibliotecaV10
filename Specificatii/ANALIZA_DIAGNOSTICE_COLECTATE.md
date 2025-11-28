# ANALIZĂ DIAGNOSTICE COLECTATE - Pattern Confirmat

**Data:** 2025-11-20  
**Perioada monitorizare:** 19:39 - 20:24  
**Număr opriți detectate:** 3

## 📊 PATTERN CONFIRMAT

### Opriți Detectate

#### Oprirea 1: 19:42:09
- **Memorie disponibilă:** 139 MB
- **Swap folosit:** 4000 MB / 8096 MB (50%)
- **Procese Aleph:** 1325
- **Load average:** 0.27, 1.09, 0.79
- **Timp până la repornire:** ~88 secunde (2 verificări)

#### Oprirea 2: 19:58:12
- **Memorie disponibilă:** 112 MB
- **Swap folosit:** 4000 MB / 8096 MB (50%)
- **Procese Aleph:** 1326
- **Load average:** 0.04, 0.05, 0.27
- **Timp până la repornire:** ~64 secunde (1 verificare)

#### Oprirea 3: 20:14:22
- **Memorie disponibilă:** 55 MB (CRITIC!)
- **Swap folosit:** 4000 MB / 8096 MB (50%)
- **Procese Aleph:** 1325
- **Load average:** 0.02, 0.11, 0.16
- **Timp până la repornire:** ~49 secunde (1 verificare)

## 🔍 OBSERVAȚII IMPORTANTE

### 1. Memorie Disponibilă Scade Progresiv
- **19:42:** 139 MB
- **19:58:** 112 MB (-27 MB)
- **20:14:** 55 MB (-57 MB)

**Concluzie:** Memoria disponibilă scade continuu până când Aleph se oprește!

### 2. Swap-ul Rămâne la 4000 MB
- **Swap folosit:** Constant la 4000 MB (50% din 8 GB)
- **Swap nou (4 GB):** NU este folosit încă!
- **Concluzie:** Swap-ul vechi (4 GB) este complet folosit, swap-ul nou nu este activat automat

### 3. Threshold de Oprire
- Aleph se oprește când memoria disponibilă scade sub **~100-150 MB**
- Pattern consistent: 139 MB → 112 MB → 55 MB → OPRIT

### 4. Repornire Automată
- Aleph se repornește automat după **30-90 secunde**
- Probabil există un serviciu systemd sau cron care repornește Aleph

### 5. CPU Nu Este Problema
- Load average: 0.02-1.09 (foarte scăzut)
- CPU disponibil: 99%+
- **Concluzie:** CPU nu este problema

## ⚠️ PROBLEMĂ IDENTIFICATĂ

### Swap-ul Nou NU Este Folosit!

**Situație:**
- Swap total: 8 GB (4 GB vechi + 4 GB nou)
- Swap folosit: 4000 MB (doar swap-ul vechi!)
- Swap nou: 4095 MB disponibil, dar NU este folosit

**De ce:**
- Linux folosește swap-urile în ordinea priorității
- Swap-ul vechi are prioritate mai mare
- Swap-ul nou nu este folosit până când vechiul este complet epuizat

**Soluție:**
- Trebuie să schimbi prioritatea swap-urilor
- SAU să oprești swap-ul vechi și să folosești doar cel nou
- SAU să configurezi swap-ul nou cu prioritate mai mare

## 🎯 CONCLUZIE

### Cauza Întreruperilor Confirmată:

1. **Memorie RAM epuizată:**
   - Memorie disponibilă scade progresiv: 139 MB → 112 MB → 55 MB
   - Când scade sub ~100 MB, Aleph se oprește

2. **Swap-ul vechi este complet folosit:**
   - 4000 MB / 4000 MB (100%)
   - Swap-ul nou (4 GB) nu este folosit

3. **1325+ procese Aleph:**
   - Consumă multă memorie
   - Cauză principală a epuizării memoriei

### Rezolvare:

1. **URGENT:** Configurează swap-ul nou cu prioritate mai mare
2. **URGENT:** Upgrade RAM la 16-24 GB
3. **Optimizează:** Reduce numărul de procese Aleph

---

**Notă:** Swap-ul nou de 4 GB este disponibil, dar nu este folosit! Trebuie configurat prioritatea sau oprit swap-ul vechi!

