# 🔒 Ghid de Securitate - Biblioteca Aplicație

Acest document descrie măsurile de securitate implementate și recomandările pentru protejarea aplicației Biblioteca.

## 📋 Cuprins

1. [Fișiere de Securitate](#fișiere-de-securitate)
2. [Protecție Bază de Date](#protecție-bază-de-date)
3. [Protecție PHP](#protecție-php)
4. [Protecție Apache (.htaccess)](#protecție-apache-htaccess)
5. [Best Practices](#best-practices)
6. [Checklist Deploy](#checklist-deploy)

---

## 📁 Fișiere de Securitate

### 1. `.htaccess`
Protejează aplicația la nivel de server Apache:
- Blochează accesul direct la fișiere sensibile (`.sql`, `.log`, `.ini`, etc.)
- Blochează accesul la fișiere de configurare (`config.php`, `config_security.php`)
- Blochează accesul la scripturi de setup/test/debug
- Setează headers de securitate (XSS Protection, Clickjacking, etc.)
- Previne atacuri SQL Injection și Path Traversal în URL
- Protejează împotriva atacurilor de tip Directory Traversal

**Locație:** Root director aplicație

### 2. `config_security.php`
Setări centralizate de securitate:
- Configurare mod aplicație (development/production)
- Securitate sesiuni (HttpOnly, Secure, SameSite)
- Funcții de sanitizare input (XSS protection)
- Protecție CSRF (Cross-Site Request Forgery)
- Rate limiting (protecție DDoS)
- Logging evenimente de securitate
- Validare input (barcode, email, integer)

**Locație:** Root director aplicație

### 3. `verificare_securitate.php`
Script de audit securitate:
- Verifică existența fișierelor de securitate
- Verifică configurația PHP
- Verifică setările PDO
- Verifică permisiunile fișierelor
- Verifică headers de securitate
- Detectează vulnerabilități comune

**IMPORTANT:** Acest fișier este blocat în `.htaccess`. Folosește-l doar pentru audit periodic.

**Acces:** `http://localhost/verificare_securitate.php?token=SECURITY_AUDIT_YYYYMMDD`

---

## 🗄️ Protecție Bază de Date

### SQL Injection Prevention

Aplicația folosește **PDO cu prepared statements** pentru toate interogările SQL:

```php
// ✅ CORECT - Folosește prepared statements
$stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
$stmt->execute([$cod_scanat]);

// ❌ GREȘIT - NU folosi concatenare directă
$query = "SELECT * FROM cititori WHERE cod_bare = '$cod_scanat'";
```

### Setări PDO Securizate

În `config.php`:
- `PDO::ATTR_EMULATE_PREPARES => false` - Previne SQL injection
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` - Gestionare erori
- `PDO::ATTR_TIMEOUT => 5` - Timeout conexiune

### Credențiale Bază de Date

**Local (Development):**
- User: `root`
- Password: (gol)
- Host: `localhost`

**Server (Production):**
- Creează un utilizator MySQL dedicat cu permisiuni limitate
- Folosește parolă puternică
- Limitează accesul la IP-uri specifice (dacă este posibil)

**Exemplu creare utilizator MySQL:**
```sql
CREATE USER 'biblioteca_user'@'localhost' IDENTIFIED BY 'parola_puternica_aici';
GRANT SELECT, INSERT, UPDATE, DELETE ON biblioteca.* TO 'biblioteca_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 🛡️ Protecție PHP

### 1. Sanitizare Input

Folosește funcțiile din `config_security.php`:

```php
require_once 'config_security.php';

// Sanitizează input
$cod_scanat = sanitize_input($_POST['cod_scanat']);

// Validează cod de bare
if (!validate_barcode($cod_scanat)) {
    die("Cod invalid!");
}

// Validează email
if (!validate_email($email)) {
    die("Email invalid!");
}
```

### 2. Protecție XSS

Toate datele afișate în HTML trebuie escăpate:

```php
// ✅ CORECT
echo htmlspecialchars($nume, ENT_QUOTES, 'UTF-8');

// ❌ GREȘIT
echo $nume;
```

### 3. Protecție CSRF

Pentru formulare importante, folosește token CSRF:

```php
// În formular
<?php echo csrf_field(); ?>

// La procesare
if (!verify_csrf_token($_POST['csrf_token'])) {
    die("Token CSRF invalid!");
}
```

### 4. Rate Limiting

Protejează împotriva atacurilor brute force:

```php
if (!check_rate_limit('scan', 10, 60)) {
    die("Prea multe încercări. Așteaptă 1 minut.");
}
```

### 5. Securitate Sesiuni

Sesiunile sunt configurate automat prin `config_security.php`:
- HttpOnly cookies (previne acces JavaScript)
- Secure cookies (pentru HTTPS)
- SameSite Strict (protecție CSRF)
- Regenerare ID sesiune periodică

---

## 🌐 Protecție Apache (.htaccess)

### Fișiere Blocate

Următoarele tipuri de fișiere sunt blocate:
- `.sql`, `.log`, `.ini`, `.conf`, `.bak`, `.backup`, `.old`, `.tmp`, `.env`
- `config.php`, `config_security.php`, `.htaccess`, `.git`, `.env`
- Scripturi: `setup*.php`, `update_database.php`, `test_*.php`, `debug_*.php`, etc.

### Directoare Blocate

- `.git/`
- `backup/`
- `build/`, `dist/`
- `scripts_saved/`
- `__pycache__/`

### Headers de Securitate

Setate automat prin `.htaccess`:
- `X-Frame-Options: SAMEORIGIN` - Previne clickjacking
- `X-Content-Type-Options: nosniff` - Previne MIME sniffing
- `X-XSS-Protection: 1; mode=block` - Protecție XSS
- `Content-Security-Policy` - Politică de securitate conținut
- `Referrer-Policy: strict-origin-when-cross-origin`

---

## ✅ Best Practices

### 1. Parole și Credențiale

- ❌ **NU** hardcode parola în cod
- ✅ Folosește variabile de mediu sau fișiere de configurare protejate
- ✅ Folosește parolă puternică pentru utilizatorul MySQL
- ✅ Schimbă parola periodic

### 2. Erori și Logging

**Development:**
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

**Production:**
```php
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
```

### 3. Actualizări

- ✅ Actualizează regulat PHP
- ✅ Actualizează MySQL/MariaDB
- ✅ Actualizează biblioteci PHP (dacă folosești Composer)
- ✅ Monitorizează vulnerabilități cunoscute

### 4. Backup-uri

- ✅ Fă backup-uri regulate ale bazei de date
- ✅ Stochează backup-urile într-un loc sigur
- ✅ Testează restaurarea din backup periodic
- ✅ **NU** păstra backup-uri în directorul public web

### 5. HTTPS

- ✅ Folosește HTTPS pe serverul de producție
- ✅ Activează `session.cookie_secure = 1` când folosești HTTPS
- ✅ Configurează certificat SSL valid

### 6. Firewall

- ✅ Blochează porturi inutile
- ✅ Permite doar porturile necesare (80, 443, 22 pentru SSH)
- ✅ Limitează accesul SSH la IP-uri specifice (dacă este posibil)

---

## 📋 Checklist Deploy pe Server

### Înainte de Deploy

- [ ] Schimbă `APP_MODE` la `'production'` în `config_security.php`
- [ ] Creează utilizator MySQL dedicat cu permisiuni limitate
- [ ] Actualizează credențiale în `config.php` (NU hardcode parola!)
- [ ] Verifică că `.htaccess` este activ
- [ ] Șterge sau protejează fișierele de test/debug
- [ ] Verifică permisiunile fișierelor (config: 0600 sau 0644)
- [ ] Configurează HTTPS
- [ ] Activează `session.cookie_secure = 1` pentru HTTPS

### După Deploy

- [ ] Rulează `verificare_securitate.php` pentru audit
- [ ] Verifică că fișierele sensibile nu sunt accesibile public
- [ ] Testează funcționalitatea aplicației
- [ ] Configurează backup-uri automate
- [ ] Configurează monitorizare (logs, erori)
- [ ] Documentează credențialele într-un loc sigur (NU în cod!)

---

## 🔍 Verificare Periodică

### Lunar

- [ ] Verifică log-urile de securitate (`logs/security.log`)
- [ ] Verifică actualizări disponibile pentru PHP/MySQL
- [ ] Revizuiește accesurile la bază de date
- [ ] Verifică backup-urile

### Trimestrial

- [ ] Rulează audit complet de securitate
- [ ] Revizuiește permisiunile fișierelor
- [ ] Actualizează parola utilizatorului MySQL
- [ ] Testează procedura de restaurare din backup

---

## 🚨 În Caz de Incident

### Dacă detectezi o breșă de securitate:

1. **Imediat:**
   - Schimbă toate parolele (MySQL, SSH, etc.)
   - Blochează IP-urile suspecte
   - Verifică log-urile pentru activitate suspectă

2. **Scurt termen:**
   - Identifică vulnerabilitatea
   - Aplică patch/update
   - Verifică integritatea datelor

3. **După remediere:**
   - Rulează audit complet
   - Documentează incidentul
   - Implementează măsuri preventive

---

## 📞 Contact

Pentru întrebări despre securitate sau pentru a raporta vulnerabilități, contactează administratorul sistemului.

---

**Ultima actualizare:** <?php echo date('Y-m-d'); ?>

**Versiune:** 1.0


