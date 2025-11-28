# ANALIZĂ STORAGE - Adăugare HDD pentru Hosting Web

**Data:** 2025-11-20  
**Server:** IBM System x 49Y6512  
**Scop:** Adăugare HDD pentru server de hosting web

## 📊 CONFIGURAȚIE ACTUALĂ

### Discuri Instalate

1. **/dev/sda - 146 GB (Sistem)**
   - Partiții:
     - /dev/sda1: 500 MB (boot)
     - /dev/sda2: 4 GB (swap)
     - /dev/sda3: 130 GB (root - sistem)
   - Utilizare: 6.9 GB (6%)
   - Disponibil: 117 GB

2. **/dev/sdb - 897 GB (Aleph)**
   - Partiție: /dev/sdb1
   - Mount point: /exlibris
   - Utilizare: 314 GB (41%)
   - Disponibil: 468 GB
   - **Aleph ocupă ~314 GB (confirmat ~40%)**

### Controller-e Storage

1. **LSI MegaSAS 9260** (RAID Controller)
   - Tip: RAID bus controller
   - Folosit pentru: /dev/sda și /dev/sdb (probabil în RAID)

2. **LSI SAS1068E** (SAS Controller)
   - Tip: SCSI storage controller
   - Porturi: 1 port

3. **Intel ICH10 SATA Controllers**
   - 4 port SATA IDE Controller
   - 2 port SATA IDE Controller
   - **Total: 6 porturi SATA disponibile**

## ✅ RĂSPUNS: DA, POȚI ADĂUGA HDD NOU!

### Compatibilitate

**Serverul suportă:**
- ✅ **SAS HDD-uri** (prin LSI SAS1068E)
- ✅ **SATA HDD-uri** (prin Intel ICH10 - 6 porturi disponibile)
- ✅ **Capacitate:** 1TB sau 2TB (sau mai mare)

### Opțiuni

#### Opțiunea 1: SATA HDD (RECOMANDAT - Mai Ieftin)
- **Tip:** SATA 3.5" sau 2.5"
- **Capacitate:** 1TB sau 2TB
- **Avantaje:**
  - ✅ Prețuri mai mici
  - ✅ Disponibil ușor
  - ✅ 6 porturi SATA disponibile
- **Dezavantaje:**
  - ⚠️ Performanță mai mică decât SAS
  - ⚠️ Nu este în RAID (dacă nu configurezi)

#### Opțiunea 2: SAS HDD (Performanță Mai Bună)
- **Tip:** SAS 3.5" sau 2.5"
- **Capacitate:** 1TB sau 2TB
- **Avantaje:**
  - ✅ Performanță mai bună
  - ✅ Compatibil cu controller-ul SAS
- **Dezavantaje:**
  - ⚠️ Prețuri mai mari
  - ⚠️ Mai greu de găsit

## 🔧 CONFIGURARE PENTRU HOSTING WEB

### Pași pentru Adăugare HDD

1. **Instalează HDD-ul fizic** în server
   - Deschide carcasei serverului
   - Găsește slot liber pentru HDD
   - Instalează HDD-ul (SATA sau SAS)

2. **Detectează HDD-ul nou**
   ```bash
   # După instalare, verifică:
   fdisk -l
   dmesg | tail -50
   ```

3. **Creează partiție**
   ```bash
   fdisk /dev/sdc  # sau /dev/sdd, etc.
   # Creează partiție nouă (n -> p -> 1 -> Enter -> Enter -> w)
   ```

4. **Formatează partiția**
   ```bash
   mkfs.ext3 /dev/sdc1  # sau ext4
   ```

5. **Montează partiția**
   ```bash
   mkdir /hosting
   mount /dev/sdc1 /hosting
   ```

6. **Adaugă în /etc/fstab pentru permanent**
   ```bash
   echo '/dev/sdc1 /hosting ext3 defaults 0 2' >> /etc/fstab
   ```

### Configurare Hosting Web

1. **Instalează server web** (Apache sau Nginx)
   ```bash
   yum install httpd  # sau nginx
   ```

2. **Configurează DocumentRoot** în `/hosting/www` sau `/hosting/html`

3. **Configurează virtual hosts** pentru multiple site-uri

4. **Configurează firewall** pentru portul 80/443

## ⚠️ CONSIDERAȚII IMPORTANTE

### 1. RAID vs Non-RAID

**Situația actuală:**
- Discurile existente (sda, sdb) sunt probabil în RAID
- HDD-ul nou poate fi adăugat:
  - **Ca disc standalone** (mai simplu, mai rapid)
  - **În RAID** (mai sigur, dar necesită configurare RAID)

**Recomandare:**
- Pentru hosting web: **Standalone este OK** (mai simplu)
- Pentru date critice: **RAID 1** (mirror) pentru siguranță

### 2. Performanță

**SATA HDD:**
- Performanță: ~100-150 MB/s
- Suficient pentru hosting web cu trafic moderat

**SAS HDD:**
- Performanță: ~150-200 MB/s
- Mai bun pentru trafic mare

**SSD (dacă este compatibil):**
- Performanță: ~500+ MB/s
- Ideal pentru hosting web performant

### 3. Capacitate

**1TB HDD:**
- Suficient pentru multe site-uri web
- Preț: ~200-400 lei

**2TB HDD:**
- Mai mult spațiu pentru creștere
- Preț: ~300-600 lei

### 4. Compatibilitate cu Aleph

**Nu va afecta Aleph:**
- ✅ Aleph rulează pe /exlibris (sdb)
- ✅ Hosting web va rula pe HDD nou (sdc/sdd)
- ✅ Separare completă - fără interferențe

## 📋 RECOMANDĂRI

### Pentru Hosting Web

1. **HDD Recomandat:**
   - **SATA 2TB** (cel mai bun raport preț/capacitate)
   - **Sau SSD SATA 1TB** (dacă vrei performanță)

2. **Configurare:**
   - HDD standalone (nu în RAID) - mai simplu
   - Partiție ext4 (mai modern decât ext3)
   - Mount point: `/hosting` sau `/var/www`

3. **Server Web:**
   - Apache sau Nginx
   - PHP (dacă este necesar)
   - MySQL/MariaDB (dacă este necesar)

4. **Securitate:**
   - Firewall pentru portul 80/443
   - SSL/TLS pentru HTTPS
   - Backup-uri regulate

## 🎯 CONCLUZIE

**DA, poți adăuga HDD nou de 1TB sau 2TB pentru hosting web!**

**Recomandare:**
- **SATA 2TB HDD** - cel mai bun raport preț/capacitate
- **Instalare standalone** - mai simplu și rapid
- **Separare completă** de Aleph - fără interferențe

**Serverul are:**
- ✅ 6 porturi SATA disponibile
- ✅ Controller SAS disponibil
- ✅ Suport pentru HDD-uri de 1TB/2TB sau mai mari

---

**Notă:** După instalarea HDD-ului, verifică compatibilitatea și configurarea înainte de a începe hosting-ul web!

