# 🧪 Ghid Testare Locală - Sistem Bibliotecă

## ✅ Status Actual

**IMPORTANT:** 
- ✅ **NU am modificat nimic pe server**
- ✅ **NU am atins Aleph**
- ✅ **Serverul rămâne intact**
- ✅ **Testăm totul LOCAL înainte de deploy**

## 📋 Ce Avem Local

### Fișiere PHP:
- ✅ `config.php` - Configurare conexiune bază de date locală
- ✅ `index.php` - Pagina principală
- ✅ `setup.php` - Script inițializare baza de date
- ✅ `setup_database.sql` - Script SQL pentru creare structură
- ✅ Alte fișiere PHP pentru funcționalități

### Configurație Locală:
```php
// config.php
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = '' (fără parolă, tipic pentru XAMPP/WAMP local)
DB_NAME = 'biblioteca'
```

## 🚀 Pașii pentru Testare Locală

### **PASUL 1: Verificare Cerințe**

#### 1.1. PHP Instalat
```bash
php -v
```
**Rezultat așteptat:** PHP 7.4+ sau 8.x

#### 1.2. MySQL/MariaDB Instalat
```bash
mysql --version
# SAU
mariadb --version
```
**Sau verifică în XAMPP/WAMP dacă MySQL este pornit**

#### 1.3. Server Web (Apache/Nginx sau XAMPP/WAMP)
- **XAMPP:** Verifică că Apache și MySQL sunt pornite
- **WAMP:** Verifică că serviciile sunt pornite
- **PHP Built-in server:** `php -S localhost:8000`

### **PASUL 2: Creare Bază de Date Locală**

#### Opțiunea A: Folosind setup.php (Recomandat)

1. **Deschide în browser:**
   ```
   http://localhost/biblioteca/setup.php
   ```
   **SAU dacă folosești PHP built-in server:**
   ```
   http://localhost:8000/setup.php
   ```

2. **Scriptul va:**
   - ✅ Crea baza de date `biblioteca`
   - ✅ Crea toate tabelele necesare
   - ✅ Insera date de test

#### Opțiunea B: Folosind setup_database.sql

1. **Deschide phpMyAdmin:**
   ```
   http://localhost/phpmyadmin
   ```

2. **Importă fișierul:**
   - Click pe "Import"
   - Selectează `setup_database.sql`
   - Click "Go"

#### Opțiunea C: Manual (linia de comandă)

```bash
# Conectare MySQL
mysql -u root

# Rulează scriptul
SOURCE setup_database.sql;
# SAU
mysql -u root < setup_database.sql
```

### **PASUL 3: Verificare Configurație**

#### 3.1. Verifică config.php
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // SAU parola ta locală
define('DB_NAME', 'biblioteca');
```

#### 3.2. Testează conexiunea
Creează `test_connection.php`:
```php
<?php
require_once 'config.php';
echo "✅ Conexiune reușită!";
echo "<br>Baza de date: " . DB_NAME;
?>
```

Accesează: `http://localhost/biblioteca/test_connection.php`

### **PASUL 4: Testare Funcționalități**

#### 4.1. Pagina Principală
```
http://localhost/biblioteca/index.php
```

**Verifică:**
- ✅ Se încarcă fără erori
- ✅ Afișează statistici (cărți, cititori, împrumuturi)
- ✅ Formulare funcționează

#### 4.2. Scanare Rapidă
```
http://localhost/biblioteca/scanare_rapida.php
```

**Verifică:**
- ✅ Poți scana/ introduce coduri
- ✅ Căutarea funcționează
- ✅ Rezultatele se afișează corect

#### 4.3. Împrumuturi
```
http://localhost/biblioteca/imprumuturi.php
```

**Verifică:**
- ✅ Lista împrumuturilor se afișează
- ✅ Poți adăuga/edita/șterge împrumuturi

#### 4.4. Cărți
```
http://localhost/biblioteca/carti.php
```

**Verifică:**
- ✅ Lista cărților se afișează
- ✅ Poți adăuga/edita cărți

#### 4.5. Cititori
```
http://localhost/biblioteca/cititori.php
```

**Verifică:**
- ✅ Lista cititorilor se afișează
- ✅ Poți adăuga/edita cititori

### **PASUL 5: Testare Funcționalități Avansate**

#### 5.1. Rapoarte
```
http://localhost/biblioteca/rapoarte.php
```

#### 5.2. Raport Prezență
```
http://localhost/biblioteca/raport_prezenta.php
```

#### 5.3. Dashboard
```
http://localhost/biblioteca/dashboard.php
```

### **PASUL 6: Testare Integrare Aleph (Opțional)**

**IMPORTANT:** Aceasta este doar pentru TESTARE LOCALĂ. Nu modifică nimic în Aleph!

#### 6.1. Verifică aleph_api.php
```php
// Verifică că funcțiile de citire din Aleph funcționează
// (doar citire, NU scriere!)
```

#### 6.2. Test Aleph
```
http://localhost/biblioteca/test_aleph.php
```

**Verifică:**
- ✅ Poate citi date din Aleph (dacă este configurat)
- ✅ NU scrie nimic în Aleph
- ✅ Importă date în baza locală

### **PASUL 7: Verificare Baza de Date**

#### 7.1. Verificare Tabele
```sql
USE biblioteca;
SHOW TABLES;
```

**Tabele așteptate:**
- `carti`
- `cititori`
- `imprumuturi`
- `sesiuni_utilizatori` (dacă există)
- `tracking_sesiuni` (dacă există)
- Alte tabele suplimentare

#### 7.2. Verificare Date
```sql
SELECT COUNT(*) FROM carti;
SELECT COUNT(*) FROM cititori;
SELECT COUNT(*) FROM imprumuturi;
```

#### 7.3. Verificare Structură
```sql
DESCRIBE carti;
DESCRIBE cititori;
DESCRIBE imprumuturi;
```

## 🐛 Depanare Probleme Locale

### Problema 1: "Eroare conexiune bază de date"
**Soluție:**
1. Verifică că MySQL rulează
2. Verifică `config.php` (user, parolă)
3. Verifică că baza de date `biblioteca` există

### Problema 2: "Baza de date nu există"
**Soluție:**
```bash
mysql -u root -e "CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_romanian_ci;"
```

### Problema 3: "Tabelele nu există"
**Soluție:**
- Rulează `setup.php` în browser
- SAU importă `setup_database.sql` în phpMyAdmin

### Problema 4: "Eroare PHP"
**Soluție:**
1. Verifică versiunea PHP: `php -v`
2. Verifică extensiile PHP necesare:
   - `pdo_mysql`
   - `mbstring`
   - `dom`
   - `xml`

**Verificare extensii:**
```bash
php -m | grep pdo_mysql
php -m | grep mbstring
```

### Problema 5: "Caractere speciale nu se afișează corect"
**Soluție:**
1. Verifică că baza de date folosește `utf8mb4`
2. Verifică că fișierele PHP sunt salvate cu encoding UTF-8
3. Verifică header-ul HTML: `<meta charset="UTF-8">`

## ✅ Checklist Testare Locală

### Configurare:
- [ ] PHP instalat și funcțional
- [ ] MySQL/MariaDB instalat și funcțional
- [ ] Server web (Apache/Nginx/XAMPP/WAMP) pornit
- [ ] Baza de date `biblioteca` creată
- [ ] Tabelele create
- [ ] `config.php` configurat corect

### Funcționalități de Bază:
- [ ] Pagina principală (`index.php`) funcționează
- [ ] Scanare rapidă funcționează
- [ ] Adăugare cărți funcționează
- [ ] Adăugare cititori funcționează
- [ ] Împrumuturi funcționează
- [ ] Returnări funcționează

### Funcționalități Avansate:
- [ ] Rapoarte funcționează
- [ ] Dashboard funcționează
- [ ] Export Excel funcționează (dacă există)
- [ ] Notificări funcționează (dacă există)

### Integrare Aleph (Opțional):
- [ ] Citire din Aleph funcționează (dacă este configurat)
- [ ] Import cărți din Aleph funcționează
- [ ] NU se scrie nimic în Aleph (verificat!)

### Date:
- [ ] Date de test inserate
- [ ] Căutări funcționează
- [ ] Filtre funcționează
- [ ] Sortări funcționează

## 🎯 Următorii Pași

**După ce totul funcționează local:**

1. ✅ **Documentează orice probleme găsite**
2. ✅ **Testează toate scenariile posibile**
3. ✅ **Verifică că nu există erori în consolă**
4. ✅ **Verifică că datele se salvează corect**
5. ✅ **Pregătește pentru deploy pe server** (când ești gata)

## 📝 Notă Importantă

**NU facem deploy pe server până când:**
- ✅ Totul funcționează perfect local
- ✅ Toate testele trec
- ✅ Nu există erori
- ✅ Ești sigur că totul este pregătit

**Când ești gata pentru deploy:**
- Folosește documentația: `Cum se creeaza o baza de date pe sistemul LINUX cum am eu pe server.md`
- Folosește scriptul SSH: `ssh_client.py`
- Urmează pașii din: `DOCUMENTATIE_DEPLOY_LINUX.md`

---

**🎉 Testează totul local și asigură-te că funcționează perfect înainte de deploy!**

