# 📋 Documentație Deploy pe Server Linux

## ✅ Răspunsuri la Întrebări

### 1. Se pot pune fișierele PHP pe server Linux?
**DA, ABSOLUT!** 
- PHP funcționează perfect pe Linux (de fapt, majoritatea serverelor PHP sunt Linux)
- Toate fișierele PHP create funcționează identic pe Linux și Windows
- Nu sunt dependențe specifice Windows

### 2. Se poate implementa o bază de date nouă, diferită de Aleph?
**DA, DEJA ESTE IMPLEMENTAT!**

Sistemul folosește **o bază de date MySQL/MariaDB complet separată** de Aleph:

#### Baza de date locală (`biblioteca`):
- ✅ `carti` - Cărțile din bibliotecă
- ✅ `cititori` - Utilizatorii/cititorii
- ✅ `imprumuturi` - Împrumuturile (doar în baza locală)
- ✅ `sesiuni_utilizatori` - Sesiunile utilizatorilor
- ✅ `tracking_sesiuni` - Tracking-ul complet al acțiunilor
- ✅ `sesiuni_biblioteca` - Statistici prezență

#### Aleph este folosit DOAR pentru CITIRE:
- ✅ `aleph_api.php` **NU modifică nimic** în Aleph
- ✅ Doar citește datele (titlu, autor, ISBN, cota, etc.)
- ✅ Folosește `file_get_contents()` pentru a accesa URL-urile Aleph
- ✅ Parsează HTML-ul returnat de Aleph
- ✅ **ZERO operații de scriere în Aleph**

### 3. Nu ne atingem deloc de Aleph?
**CORECT - NU NE ATINGEM DELOC DE ALEPH!**

#### Ce face sistemul cu Aleph:
1. **Citește** informații despre cărți (când cartea nu există în baza locală)
2. **Importă** datele în baza de date locală (tabelul `carti`)
3. **NU scrie** nimic în Aleph
4. **NU modifică** nimic în Aleph
5. **NU șterge** nimic din Aleph

#### Toate operațiunile sunt în baza locală:
- ✅ Împrumuturi → `imprumuturi` (baza locală)
- ✅ Returnări → `imprumuturi` (baza locală)
- ✅ Sesiuni → `sesiuni_utilizatori` (baza locală)
- ✅ Tracking → `tracking_sesiuni` (baza locală)
- ✅ Utilizatori → `cititori` (baza locală)

## 🚀 Deploy pe Server Linux

### Cerințe:
- PHP 7.4+ sau PHP 8.x
- MySQL 5.7+ sau MariaDB 10.3+
- Apache sau Nginx
- Extensii PHP: `pdo_mysql`, `mbstring`, `dom`, `xml`

### Pași de instalare:

1. **Copiază fișierele PHP pe server**
   ```bash
   scp -r * user@server:/var/www/html/biblioteca/
   ```

2. **Creează baza de date**
   ```bash
   mysql -u root -p
   CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_romanian_ci;
   ```

3. **Importă structura bazei de date**
   ```bash
   mysql -u root -p biblioteca < biblioteca.sql
   ```

4. **Actualizează configurația**
   Editează `config.php`:
   ```php
   define('DB_HOST', 'localhost'); // sau IP-ul serverului MySQL
   define('DB_USER', 'biblioteca_user');
   define('DB_PASS', 'parola_securizata');
   define('DB_NAME', 'biblioteca');
   ```

5. **Actualizează baza de date cu tabelele noi**
   Accesează: `http://server/biblioteca/update_database.php`

6. **Configurează permisiunile**
   ```bash
   chmod 755 *.php
   chown www-data:www-data *.php
   ```

### Configurare Aleph (opțional):
Dacă vrei să folosești Aleph pentru citire, editează `aleph_api.php`:
```php
define('ALEPH_SERVER', 'YOUR-IP-or-http://WEBSITE'); // IP-ul serverului Aleph
define('ALEPH_PORT', '8991');
```

**IMPORTANT:** Aleph rămâne **read-only** - nu se modifică nimic!

## 📊 Structura Bazei de Date

### Tabele principale:
- `carti` - Cărțile (importate din Aleph sau adăugate manual)
- `cititori` - Utilizatorii bibliotecii
- `imprumuturi` - Împrumuturile (doar în baza locală)
- `sesiuni_utilizatori` - Sesiunile utilizatorilor
- `tracking_sesiuni` - Tracking complet al acțiunilor
- `sesiuni_biblioteca` - Statistici prezență

### Separare completă de Aleph:
- ✅ Toate datele sunt în MySQL local
- ✅ Aleph este folosit doar pentru citire
- ✅ Nu există sincronizare bidirecțională
- ✅ Nu există risc de modificare accidentală în Aleph

## 🔒 Securitate

### Recomandări:
1. **Creează utilizator MySQL dedicat:**
   ```sql
   CREATE USER 'biblioteca_user'@'localhost' IDENTIFIED BY 'parola_puternica';
   GRANT ALL PRIVILEGES ON biblioteca.* TO 'biblioteca_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Protejează `config.php`:**
   ```bash
   chmod 600 config.php
   ```

3. **Configurează firewall:**
   - Permite doar conexiuni necesare
   - Blochează accesul direct la MySQL din exterior

## ✅ Verificare

După deploy, verifică:
1. `http://server/biblioteca/update_database.php` - Actualizează baza de date
2. `http://server/biblioteca/scanare_rapida.php` - Testează scanarea
3. `http://server/biblioteca/imprumuturi.php` - Verifică împrumuturile

## 📝 Notă Importantă

**Aleph rămâne complet neafectat!**
- Sistemul citește doar datele din Aleph
- Toate modificările sunt în baza de date locală
- Nu există risc de corupere sau modificare accidentală în Aleph

## 🔍 Verificare Server Linux (ÎNAINTE DE DEPLOY)

### Informații Server:
- **IP:** YOUR-IP-or-http://WEBSITE
- **Port SSH:** 22
- **User:** root
- **Parolă:** (vezi `Date Login SERVER.txt`)

### Verificări Necesare:

#### 1. Verificare Spațiu Liber pe Disc

**IMPORTANT:** Verifică spațiul disponibil înainte de deploy!

```bash
# Conectare SSH (folosește Git Bash sau PuTTY)
ssh -o StrictHostKeyChecking=no \
    -o KexAlgorithms=+diffie-hellman-group-exchange-sha1 \
    -o HostKeyAlgorithms=+ssh-rsa \
    -o MACs=+hmac-sha1 \
    root@YOUR-IP-or-http://WEBSITE

# Apoi rulează:
df -h
```

**Ce să verifici:**
- Spațiu liber pe partiția unde este MySQL (`/var/lib/mysql` sau `/usr/local/mysql/data`)
- Recomandare minimă: **10 GB liber** pentru baza de date + backup-uri
- Baza de date actuală este foarte mică (<1 MB), dar peste 1 an cu backup-uri va crește semnificativ

#### 2. Verificare Dacă Se Poate Crea Bază de Date Nouă

**RĂSPUNS: DA, ABSOLUT!** 

MySQL permite crearea de multiple baze de date independente. Crearea unei baze de date noi **NU afectează** și **NU interferează** cu Aleph sau cu alte baze de date existente.

**Verificare baze de date existente:**
```bash
mysql -u root -e "SHOW DATABASES;"
```

**Verificare dimensiuni baze de date:**
```bash
mysql -u root -e "SELECT table_schema AS 'Database', 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' 
    FROM information_schema.tables 
    GROUP BY table_schema 
    ORDER BY table_schema;"
```

**Verificare locație MySQL (datadir):**
```bash
mysql -u root -e "SHOW VARIABLES LIKE 'datadir';"
```

#### 3. Scripturi de Verificare

Am creat scripturi pentru verificare automată:

**Opțiunea 1: Script Bash (Git Bash sau Linux)**
```bash
bash check_server.sh
```

**Opțiunea 2: Verificare Manuală**
Vezi `check_server_manual.txt` pentru instrucțiuni detaliate.

**Opțiunea 3: PuTTY (Windows)**
1. Deschide PuTTY
2. Host: YOUR-IP-or-http://WEBSITE, Port: 22
3. Login: root
4. Rulează comenzile de verificare

### Dimensiune Baza de Date

**Situația actuală:**
- Baza de date locală este foarte mică: **<1 MB**
- Conține doar structura tabelelor și date de test

**Proiecție peste 1 an:**
- Date operaționale: ~50-100 MB (în funcție de numărul de împrumuturi)
- Backup-uri zilnice (30 zile): ~3 GB
- Backup-uri lunare (12 luni): ~1.2 GB
- **Total estimat: ~5-10 GB** (cu backup-uri)

**Recomandare:**
- Asigură-te că ai minim **20 GB liber** pentru baza de date și backup-uri
- Configurează backup-uri automate (vezi secțiunea Backup)

### Separare Completă de Aleph

**GARANTIE: Crearea bazei de date `biblioteca` NU afectează Aleph!**

**De ce:**
1. **Baze de date separate:** Fiecare bază de date MySQL este complet independentă
2. **Nume unic:** Baza de date `biblioteca` nu interferează cu baza Aleph (care are alt nume)
3. **Fără conexiuni:** Sistemul nostru NU se conectează la baza de date Aleph
4. **Doar citire:** Folosim doar API-ul web al Aleph (HTTP), nu acces direct la baza de date

**Verificare:**
```bash
# Listează toate bazele de date
mysql -u root -e "SHOW DATABASES;"

# Verifică că baza de date Aleph (dacă există) este separată
# Baza noastră se va numi 'biblioteca'
```

### Creare Bază de Date Nouă (Pas cu Pas)

**Pasul 1: Conectare MySQL**
```bash
mysql -u root -p
```

**Pasul 2: Creare bază de date**
```sql
CREATE DATABASE biblioteca 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_romanian_ci;
```

**Pasul 3: Verificare**
```sql
SHOW DATABASES;
USE biblioteca;
SHOW TABLES;
```

**Pasul 4: Import structură**
```bash
mysql -u root -p biblioteca < biblioteca.sql
```

**Pasul 5: Actualizare tabele noi**
Accesează: `YOUR-IP-or-http://WEBSITE/biblioteca/update_database.php`

### Backup și Restaurare

**Backup manual:**
```bash
# Backup complet
mysqldump -u root -p biblioteca > backup_biblioteca_$(date +%Y%m%d).sql

# Backup doar structură
mysqldump -u root -p --no-data biblioteca > backup_structure.sql

# Backup doar date
mysqldump -u root -p --no-create-info biblioteca > backup_data.sql
```

**Restaurare:**
```bash
mysql -u root -p biblioteca < backup_biblioteca_YYYYMMDD.sql
```

**Backup automat (cron):**
```bash
# Adaugă în crontab (crontab -e)
0 2 * * * mysqldump -u root -pPAROLA biblioteca > /backup/biblioteca_$(date +\%Y\%m\%d).sql
```

## 📋 Checklist Pre-Deploy

Înainte de deploy, verifică:

- [ ] Spațiu liber pe disc: minim 20 GB
- [ ] MySQL/MariaDB instalat și funcțional
- [ ] PHP 7.4+ instalat cu extensiile necesare
- [ ] Apache/Nginx configurat
- [ ] Acces SSH funcțional
- [ ] Baze de date existente identificate (pentru a evita conflicte de nume)
- [ ] Backup-uri configurate
- [ ] Firewall configurat (port 80/443 deschis, MySQL blocat din exterior)


