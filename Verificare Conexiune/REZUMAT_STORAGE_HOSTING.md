# REZUMAT - Adăugare HDD pentru Hosting Web

## ✅ RĂSPUNS: DA, POȚI ADĂUGA HDD NOU!

### Configurație Actuală

**Discuri existente:**
- **/dev/sda (146 GB):** Sistem de operare
- **/dev/sdb (897 GB):** Aleph (/exlibris) - 314 GB folosit (41%)

**Controller-e disponibile:**
- ✅ **6 porturi SATA** (Intel ICH10)
- ✅ **Controller SAS** (LSI SAS1068E)
- ✅ **RAID Controller** (LSI MegaSAS 9260)

## 🎯 RECOMANDARE

### HDD Recomandat: SATA 2TB

**De ce:**
- ✅ Prețuri bune (~300-600 lei)
- ✅ Disponibil ușor
- ✅ 6 porturi SATA disponibile
- ✅ Suficient pentru multe site-uri web
- ✅ Compatibil cu serverul

**Alternativ:**
- **SATA 1TB** - dacă nu ai nevoie de atât spațiu
- **SAS 2TB** - dacă vrei performanță mai bună (mai scump)

## 📋 PAȘI PENTRU ADAUGARE

### 1. Instalare Fizică
- Deschide carcasei serverului
- Găsește slot liber pentru HDD
- Instalează HDD-ul SATA (sau SAS)
- Conectează la port SATA disponibil

### 2. Configurare Software
După instalarea fizică, rulează comenzile:

```bash
# Detectează HDD-ul nou
fdisk -l

# Creează partiție (exemplu pentru /dev/sdc)
fdisk /dev/sdc
# n -> p -> 1 -> Enter -> Enter -> w

# Formatează
mkfs.ext4 /dev/sdc1

# Creează director
mkdir /hosting

# Montează
mount /dev/sdc1 /hosting

# Adaugă în /etc/fstab pentru permanent
echo '/dev/sdc1 /hosting ext4 defaults 0 2' >> /etc/fstab
```

### 3. Configurare Hosting Web

```bash
# Instalează Apache
yum install httpd

# Creează directoare
mkdir -p /hosting/www

# Configurează Apache
# Editează /etc/httpd/conf/httpd.conf
# Schimbă DocumentRoot în /hosting/www

# Pornește Apache
service httpd start
chkconfig httpd on
```

## ⚠️ IMPORTANT

### Compatibilitate cu Aleph
- ✅ **Nu va afecta Aleph** - separare completă
- ✅ Aleph rulează pe /exlibris (sdb)
- ✅ Hosting web va rula pe HDD nou (sdc/sdd)

### Performanță
- **SATA HDD:** Suficient pentru hosting web cu trafic moderat
- **SAS HDD:** Mai bun pentru trafic mare (mai scump)
- **SSD:** Ideal pentru performanță maximă (dacă este compatibil)

### Securitate
- Configurează firewall pentru portul 80/443
- Instalează SSL/TLS pentru HTTPS
- Fă backup-uri regulate

## 🎯 CONCLUZIE

**Serverul poate adăuga HDD nou de 1TB sau 2TB pentru hosting web!**

**Recomandare finală:**
- **SATA 2TB HDD** - cel mai bun raport preț/capacitate
- **Instalare standalone** - mai simplu și rapid
- **Separare completă** de Aleph - fără interferențe

---

**Notă:** Am creat scriptul `ghid_adaugare_hdd.sh` pentru configurare automată după instalarea fizică a HDD-ului!

