# SPECIFICAȚII COMPLETE SERVER - Pentru Comandă RAM

**Data:** 2025-11-20  
**Server IP:** YOUR-IP-or-http://WEBSITE  
**Scop:** Upgrade RAM pentru stabilitate Aleph și aplicație biblioteca

---

## 🖥️ INFORMATII MOTHERBOARD

- **Manufacturer:** IBM
- **Model:** 49Y6512
- **Tip:** IBM System x (Server rack)
- **An fabricație:** ~2009

---

## 💾 PROCESOR

- **Model:** Intel Xeon E5520 @ 2.27GHz
- **Număr procesoare:** 2 (Dual processor)
- **Arhitectură:** 64-bit (i686)
- **Max Speed:** 4000 MHz
- **Current Speed:** 2270 MHz

---

## 📊 MEMORIE ACTUALĂ

### Configurație Actuală:
- **RAM Total:** 8 GB (8095 MB)
- **RAM Folosit:** ~8 GB (99.7%)
- **RAM Disponibil:** 25-55 MB (CRITIC!)
- **Swap Total:** 8 GB (8096 MB)
- **Swap Folosit:** ~4 GB (50%)

### Plăci de Memorie Instalate:
- **Slot DIMM03:** 4 GB
  - **Manufacturer:** Samsung
  - **Part Number:** M393B5170EH1-CH9
  - **Speed:** 1067 MHz (0.9 ns)
  - **Type:** DDR3 ECC (Single-bit ECC)
  - **Standard:** PC3-8500 (DDR3-1066)

- **Slot (probabil DIMM11 sau alt slot):** 4 GB
  - **Detalii:** Similar cu DIMM03 (nu detectat complet în dmidecode)

### Sloturi Memorie:
- **Total sloturi:** 18 DIMM slots
- **Sloturi ocupate:** 2 (cu 4 GB fiecare)
- **Sloturi libere:** 16 sloturi disponibile

---

## 🎯 CAPACITATE MAXIMĂ RAM

### Specificații Memorie:
- **Capacitate maximă:** 192 GB
- **Număr sloturi:** 18 DIMM slots
- **Error Correction:** Single-bit ECC (Error Correcting Code)
- **Tip memorie:** DDR3 ECC Registered (RDIMM) - probabil

---

## 🔧 SPECIFICAȚII MEMORIE NECESARE

### Tip Memorie:
- **Tip:** DDR3 ECC Registered (RDIMM)
- **Speed actual:** 1067 MHz (PC3-8500)
- **Speed compatibil:** 1067 MHz, 1333 MHz (PC3-10600), sau 1600 MHz (PC3-12800)
- **Notă:** Memoria cu speed mai mare va face downclock automat la speed-ul existent

### Compatibilitate:
- ✅ **ECC Required:** DA (Error Correcting Code)
- ✅ **Registered (RDIMM):** DA (nu Unbuffered UDIMM)
- ✅ **DDR3:** DA (nu DDR2 sau DDR4)
- ✅ **Speed:** 1067 MHz sau compatibil (1333/1600 MHz)

---

## 💻 SISTEM OPERARE

- **OS:** Red Hat Enterprise Linux ES release 4 (Nahant Update 8)
- **Kernel:** Linux 2.6.9-89.ELsmp
- **Arhitectură:** i686 (32-bit kernel, 64-bit CPU)
- **An:** 2009

---

## 📋 APLICAȚII RULATE

### Aplicații Actuale:
- **Aleph (Exlibris):** Sistem de management bibliotecă
  - Număr procese: 1325+ procese
  - Consum memorie: ~7 GB
  - Port: 8991

- **Oracle Database:** Baza de date pentru Aleph
  - Consum memorie: ~1 GB

### Aplicații Planificate:
- **Apache Web Server:** Pentru aplicația biblioteca
- **MySQL/MariaDB:** Baza de date pentru aplicația biblioteca
- **PHP:** Pentru aplicația biblioteca

---

## 🎯 NECESITATE UPGRADE

### Problema Actuală:
- **Memorie disponibilă:** Doar 25-55 MB (CRITIC!)
- **Aleph se oprește des:** La fiecare 3-4 minute în timpul zilei
- **Swap folosit intens:** 4 GB din 8 GB (50%)
- **Risc aplicație biblioteca:** Nu va funcționa stabil cu memorie actuală

### Scop Upgrade:
- **Stabilitate Aleph:** Să nu se mai oprească din cauza memoriei
- **Aplicație biblioteca:** Să funcționeze stabil (Apache + MySQL + PHP)
- **Performanță:** Toate aplicațiile să funcționeze rapid

---

## 💰 BUGET ESTIMAT

- **Opțiunea 1:** 2x 8GB DDR3 ECC → ~122 lei (Total: 24 GB)
- **Opțiunea 2:** 2x 16GB DDR3 ECC → ~418 lei (Total: 40 GB)

---

## 📞 CONTACT

**Pentru întrebări suplimentare:**
- **Email:** (specificați email-ul dvs.)
- **Telefon:** (specificați telefonul dvs.)

---

## ✅ CERINȚE PENTRU EXPERTCOMPANY

### Vă rog să recomandați:
1. **2 modele de memorie RAM compatibile** cu configurația de mai sus
2. **Opțiunea 1:** 2x 8GB DDR3 ECC (pentru total 24 GB)
3. **Opțiunea 2:** 2x 16GB DDR3 ECC (pentru total 40 GB)
4. **Confirmare compatibilitate** cu IBM System x 49Y6512
5. **Prețuri și disponibilitate**

### Informații Suplimentare Necesare:
- **Part Number exact** pentru compatibilitate
- **Manufacturer** (Samsung, Hynix, Micron, etc.)
- **Garantie** și **politică retur**
- **Timp livrare**

---

**Notă:** Serverul este din 2009, deci este important să confirmați compatibilitatea exactă cu IBM System x 49Y6512 înainte de comandă.

