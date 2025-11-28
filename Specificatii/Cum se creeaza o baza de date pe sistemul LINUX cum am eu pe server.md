# 📚 Cum se Creează o Bază de Date pe Sistemul Linux (Server)

## 📖 Ce este o Bază de Date?

O **bază de date** este ca un **fișier Excel foarte puternic** care stochează date organizate în **tabele**. 

**Exemplu simplu:**
- În Excel ai coloane: Nume, Prenume, Telefon
- În baza de date ai **tabele** cu **coloane** (câmpuri) și **rânduri** (înregistrări)

**Diferențe față de Excel:**
- ✅ Poate stoca milioane de înregistrări
- ✅ Poate face căutări foarte rapide
- ✅ Poate face legături între tabele (relații)
- ✅ Poate fi accesată simultan de mai multe aplicații
- ✅ Are securitate (utilizatori, parole, permisiuni)

## 🎯 Ce Vrem să Facem?

Vrem să creăm o bază de date numită **`biblioteca`** pe serverul Linux care va stoca:
- **Cărți** (titlu, autor, ISBN, cod de bare, locație)
- **Cititori** (nume, prenume, telefon, email, cod de bare)
- **Împrumuturi** (cine a împrumutat ce carte, când, status)

## ✅ Ce Este Necesar (Prerequisituri)

### 1. **Server Linux Accesibil**
- ✅ Serverul tău: `YOUR-IP-or-http://WEBSITE`
- ✅ Acces SSH (user: `root`, parolă: `YOUR-SERVER-PASSWORD`)
- ✅ Port SSH: `22`

### 2. **MySQL sau MariaDB Instalat**
MySQL/MariaDB este **programul** care gestionează bazele de date.

**Verificare:**
```bash
mysql --version
# SAU
mariadb --version
```

**Dacă NU este instalat, instalează:**
```bash
# Ubuntu/Debian
apt-get update
apt-get install mysql-server
# SAU
apt-get install mariadb-server

# CentOS/RHEL
yum install mysql-server
# SAU
yum install mariadb-server
```

### 3. **Acces la MySQL cu User Root**
Trebuie să poți accesa MySQL cu user `root` și parola.

**Verificare:**
```bash
mysql -u root -p
# Introdu parola când este cerută
```

**Dacă nu ai parolă pentru root:**
```bash
mysql -u root
```

### 4. **Spațiu pe Disc**
Verifică că ai spațiu suficient:
```bash
df -h
```

**Recomandare:** Minim 1-2 GB liber pentru baza de date (pentru început).

### 5. **Cunoaștere de Bază SQL (Opțional)**
Nu este obligatoriu, dar ajută să înțelegi comenzile SQL.

## 📋 Pașii Detaliați de Creare

### **PASUL 1: Conectare la Server**

Conectează-te la serverul Linux prin SSH:

```bash
ssh root@YOUR-IP-or-http://WEBSITE
```

**Sau cu opțiuni pentru compatibilitate:**
```bash
ssh -o StrictHostKeyChecking=no \
    -o KexAlgorithms=+diffie-hellman-group-exchange-sha1 \
    -o HostKeyAlgorithms=+ssh-rsa \
    -o MACs=+hmac-sha1 \
    root@YOUR-IP-or-http://WEBSITE
```

**Introdu parola:** `YOUR-SERVER-PASSWORD`

**Rezultat așteptat:**
```
Welcome to Ubuntu...
root@server:~#
```

---

### **PASUL 2: Verificare MySQL/MariaDB**

Verifică dacă MySQL este instalat și rulează:

```bash
# Verifică versiunea
mysql --version
# SAU
mariadb --version

# Verifică dacă serviciul rulează
systemctl status mysql
# SAU
systemctl status mariadb
```

**Dacă serviciul NU rulează:**
```bash
# Pornește serviciul
systemctl start mysql
# SAU
systemctl start mariadb

# Activează la pornirea sistemului
systemctl enable mysql
# SAU
systemctl enable mariadb
```

---

### **PASUL 3: Conectare la MySQL**

Conectează-te la MySQL ca user `root`:

```bash
mysql -u root -p
```

**Introdu parola MySQL** (poate fi diferită de parola SSH).

**Dacă nu ai parolă:**
```bash
mysql -u root
```

**Rezultat așteptat:**
```
Welcome to the MySQL monitor...
mysql>
```

Acum ești în **consola MySQL** (prompt-ul este `mysql>`).

---

### **PASUL 4: Verificare Baze de Date Existente**

Înainte de a crea o bază de date nouă, verifică ce baze de date există deja:

```sql
SHOW DATABASES;
```

**Rezultat așteptat:**
```
+--------------------+
| Database           |
+--------------------+
| information_schema |
| mysql              |
| performance_schema |
| sys                |
+--------------------+
```

**Explicație:**
- `information_schema` - Informații despre structura bazei de date (NU modifica!)
- `mysql` - Baza de date sistem MySQL (NU modifica!)
- `performance_schema` - Performanță MySQL (NU modifica!)
- `sys` - Baza de date sistem (NU modifica!)

**IMPORTANT:** Aceste baze de date sunt **sistem** și NU trebuie modificate!

---

### **PASUL 5: Creare Bază de Date Nouă**

Acum creează baza de date `biblioteca`:

```sql
CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_romanian_ci;
```

**Explicație:**
- `CREATE DATABASE biblioteca` - Creează baza de date numită "biblioteca"
- `CHARACTER SET utf8mb4` - Setează codarea pentru caractere speciale (diacritice românești: ă, â, î, ș, ț)
- `COLLATE utf8mb4_romanian_ci` - Setează sortarea în limba română (ci = case insensitive)

**Rezultat așteptat:**
```
Query OK, 1 row affected (0.01 sec)
```

**Verificare:**
```sql
SHOW DATABASES;
```

Acum ar trebui să vezi `biblioteca` în listă:
```
+--------------------+
| Database           |
+--------------------+
| information_schema |
| biblioteca         |  ← NOUA BAZĂ DE DATE
| mysql              |
| performance_schema |
| sys                |
+--------------------+
```

---

### **PASUL 6: Selectare Bază de Date**

Înainte de a crea tabele, trebuie să "intri" în baza de date:

```sql
USE biblioteca;
```

**Rezultat așteptat:**
```
Database changed
```

**Verificare:**
```sql
SELECT DATABASE();
```

**Rezultat așteptat:**
```
+------------+
| DATABASE() |
+------------+
| biblioteca |
+------------+
```

---

### **PASUL 7: Creare Tabele**

Acum creează **tabelele** în care vor fi stocate datele.

#### **7.1. Tabelul `carti`**

```sql
CREATE TABLE carti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cod_bare VARCHAR(50) UNIQUE NOT NULL,
    titlu VARCHAR(255) NOT NULL,
    autor VARCHAR(255),
    isbn VARCHAR(20),
    cota VARCHAR(50),
    raft VARCHAR(10),
    nivel VARCHAR(10),
    pozitie VARCHAR(10),
    sectiune VARCHAR(50),
    observatii_locatie TEXT,
    data_adaugare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cod_bare (cod_bare),
    INDEX idx_locatie (raft, nivel, pozitie),
    INDEX idx_cota (cota)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
```

**Explicație:**
- `id` - Număr unic pentru fiecare carte (se generează automat)
- `cod_bare` - Codul de bare al cărții (UNIQUE = nu poate fi duplicat)
- `titlu` - Titlul cărții (NOT NULL = obligatoriu)
- `autor` - Autorul cărții
- `isbn` - ISBN-ul cărții
- `cota` - Cota bibliotecii
- `raft`, `nivel`, `pozitie` - Locația fizică în bibliotecă
- `sectiune` - Secțiunea bibliotecii
- `data_adaugare` - Data când a fost adăugată cartea (automat)
- `INDEX` - Indexuri pentru căutări rapide

#### **7.2. Tabelul `cititori`**

```sql
CREATE TABLE cititori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cod_bare VARCHAR(50) UNIQUE NOT NULL,
    nume VARCHAR(100) NOT NULL,
    prenume VARCHAR(100) NOT NULL,
    telefon VARCHAR(20),
    email VARCHAR(100),
    data_inregistrare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cod_bare (cod_bare)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
```

#### **7.3. Tabelul `imprumuturi`**

```sql
CREATE TABLE imprumuturi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cod_cititor VARCHAR(50) NOT NULL,
    cod_carte VARCHAR(50) NOT NULL,
    data_imprumut TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_returnare TIMESTAMP NULL,
    status ENUM('activ', 'returnat') DEFAULT 'activ',
    FOREIGN KEY (cod_cititor) REFERENCES cititori(cod_bare),
    FOREIGN KEY (cod_carte) REFERENCES carti(cod_bare),
    INDEX idx_status (status),
    INDEX idx_cititor (cod_cititor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
```

**Explicație:**
- `FOREIGN KEY` - Legătură cu alte tabele (relație)
  - `cod_cititor` se referă la `cititori.cod_bare`
  - `cod_carte` se referă la `carti.cod_bare`
- `status` - Poate fi doar 'activ' sau 'returnat'

**Verificare tabele create:**
```sql
SHOW TABLES;
```

**Rezultat așteptat:**
```
+---------------------+
| Tables_in_biblioteca |
+---------------------+
| carti               |
| cititori            |
| imprumuturi         |
+---------------------+
```

---

### **PASUL 8: Verificare Structură Tabele**

Verifică structura fiecărui tabel:

```sql
DESCRIBE carti;
DESCRIBE cititori;
DESCRIBE imprumuturi;
```

**Sau:**
```sql
SHOW CREATE TABLE carti;
SHOW CREATE TABLE cititori;
SHOW CREATE TABLE imprumuturi;
```

---

### **PASUL 9: Inserare Date de Test (Opțional)**

Poți insera date de test pentru a verifica că totul funcționează:

#### **9.1. Inserează cărți:**
```sql
INSERT INTO carti (cod_bare, titlu, autor, isbn, cota, raft, nivel, pozitie, sectiune) VALUES
('BOOK001', 'Amintiri din copilărie', 'Ion Creangă', '9789734640539', '821.135.1 CRE a', 'A', '1', '01', 'Literatură română'),
('BOOK002', 'Maitreyi', 'Mircea Eliade', '9789734640546', '821.135.1 ELI m', 'A', '1', '02', 'Literatură română'),
('BOOK003', 'Pădurea spânzuraților', 'Liviu Rebreanu', '9789734640553', '821.135.1 REB p', 'A', '1', '03', 'Literatură română');
```

#### **9.2. Inserează cititori:**
```sql
INSERT INTO cititori (cod_bare, nume, prenume, telefon, email) VALUES
('USER001', 'Popescu', 'Ion', '0721123456', 'ion.popescu@email.ro'),
('USER002', 'Ionescu', 'Maria', '0722234567', 'maria.ionescu@email.ro');
```

#### **9.3. Inserează împrumuturi:**
```sql
INSERT INTO imprumuturi (cod_cititor, cod_carte, status) VALUES
('USER001', 'BOOK001', 'activ'),
('USER002', 'BOOK002', 'activ');
```

#### **9.4. Verificare date:**
```sql
SELECT * FROM carti;
SELECT * FROM cititori;
SELECT * FROM imprumuturi;
```

---

### **PASUL 10: Ieșire din MySQL**

Când ai terminat, ieși din consola MySQL:

```sql
EXIT;
```

**Sau:**
```sql
QUIT;
```

**Sau apasă:** `Ctrl + D`

---

## 🔄 Metodă Alternativă: Folosire Script SQL

În loc să introduci manual toate comenzile, poți folosi un **script SQL**:

### **PASUL 1: Creează fișierul SQL**

Pe computerul tău local, creează fișierul `setup_database.sql` cu toate comenzile.

### **PASUL 2: Transferă fișierul pe server**

```bash
# Din computerul tău local
scp setup_database.sql root@YOUR-IP-or-http://WEBSITE:/tmp/
```

### **PASUL 3: Rulează scriptul**

```bash
# Pe server
mysql -u root -p < /tmp/setup_database.sql
```

**Sau dacă ești deja în MySQL:**
```sql
SOURCE /tmp/setup_database.sql;
```

---

## 🔐 Creare Utilizator Dedicat (Recomandat pentru Securitate)

În loc să folosești `root` pentru aplicație, creează un utilizator dedicat:

### **PASUL 1: Conectare MySQL ca root**
```bash
mysql -u root -p
```

### **PASUL 2: Creare utilizator**
```sql
CREATE USER 'biblioteca_user'@'localhost' IDENTIFIED BY 'parola_puternica_aici';
```

### **PASUL 3: Acordare permisiuni**
```sql
GRANT ALL PRIVILEGES ON biblioteca.* TO 'biblioteca_user'@'localhost';
FLUSH PRIVILEGES;
```

### **PASUL 4: Verificare**
```sql
SHOW GRANTS FOR 'biblioteca_user'@'localhost';
```

### **PASUL 5: Testare conexiune**
```bash
mysql -u biblioteca_user -p biblioteca
```

### **PASUL 6: Actualizare config.php**
În fișierul `config.php` al aplicației:
```php
define('DB_USER', 'biblioteca_user');
define('DB_PASS', 'parola_puternica_aici');
```

---

## ✅ Verificare Finală

### **1. Verificare baza de date există:**
```bash
mysql -u root -p -e "SHOW DATABASES;"
```

### **2. Verificare tabele:**
```bash
mysql -u root -p -e "USE biblioteca; SHOW TABLES;"
```

### **3. Verificare număr înregistrări:**
```bash
mysql -u root -p -e "USE biblioteca; SELECT COUNT(*) FROM carti; SELECT COUNT(*) FROM cititori;"
```

### **4. Verificare dimensiune baza de date:**
```bash
mysql -u root -p -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'biblioteca';"
```

---

## 🔧 Configurare Aplicație PHP

După ce ai creat baza de date, configurează aplicația PHP:

### **1. Editează `config.php`:**

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // SAU 'biblioteca_user' dacă ai creat utilizator dedicat
define('DB_PASS', 'parola_aici');
define('DB_NAME', 'biblioteca');
```

### **2. Testare conexiune:**

Creează un fișier `test_connection.php`:
```php
<?php
require_once 'config.php';
echo "✅ Conexiune reușită!";
?>
```

Accesează: `YOUR-IP-or-http://WEBSITE/biblioteca/test_connection.php`

---

## 📊 Structura Finală

După toți pașii, vei avea:

```
MySQL Server
└── biblioteca (bază de date)
    ├── carti (tabel)
    │   ├── id
    │   ├── cod_bare
    │   ├── titlu
    │   ├── autor
    │   └── ...
    ├── cititori (tabel)
    │   ├── id
    │   ├── cod_bare
    │   ├── nume
    │   ├── prenume
    │   └── ...
    └── imprumuturi (tabel)
        ├── id
        ├── cod_cititor (legătură cu cititori)
        ├── cod_carte (legătură cu carti)
        └── ...
```

---

## 🎯 Rezumat - Comenzi Rapide

```bash
# 1. Conectare server
ssh root@YOUR-IP-or-http://WEBSITE

# 2. Conectare MySQL
mysql -u root -p

# 3. Creare bază de date
CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_romanian_ci;

# 4. Selectare bază de date
USE biblioteca;

# 5. Creare tabele (copiază comenzile CREATE TABLE)

# 6. Verificare
SHOW TABLES;

# 7. Ieșire
EXIT;
```

---

## ❓ Întrebări Frecvente

### **1. Ce se întâmplă dacă baza de date există deja?**
```sql
CREATE DATABASE IF NOT EXISTS biblioteca ...;
```
Comanda `IF NOT EXISTS` previne eroarea dacă baza de date există deja.

### **2. Cum șterg o bază de date?**
```sql
DROP DATABASE biblioteca;
```
⚠️ **ATENȚIE:** Aceasta șterge TOATE datele!

### **3. Cum fac backup?**
```bash
mysqldump -u root -p biblioteca > backup_biblioteca.sql
```

### **4. Cum restaurez din backup?**
```bash
mysql -u root -p biblioteca < backup_biblioteca.sql
```

### **5. Cum văd toate bazele de date?**
```sql
SHOW DATABASES;
```

### **6. Cum văd toate tabelele dintr-o bază de date?**
```sql
USE biblioteca;
SHOW TABLES;
```

### **7. Cum văd structura unui tabel?**
```sql
DESCRIBE carti;
```

### **8. Cum văd datele dintr-un tabel?**
```sql
SELECT * FROM carti;
```

---

## 🔒 Securitate

### **Recomandări:**
1. ✅ **NU folosi `root` pentru aplicație** - Creează utilizator dedicat
2. ✅ **Folosește parole puternice** - Minim 12 caractere, mixte
3. ✅ **NU expune MySQL pe internet** - Doar localhost
4. ✅ **Configurează firewall** - Blochează portul 3306 din exterior
5. ✅ **Fă backup-uri regulate** - Zilnic sau săptămânal

---

## 📝 Notă Importantă

**Baza de date `biblioteca` este COMPLET SEPARATĂ de Aleph!**
- ✅ Nu interferează cu Aleph
- ✅ Nu modifică datele din Aleph
- ✅ Este independentă și sigură

---

## ✅ Checklist Final

- [ ] MySQL/MariaDB instalat și funcțional
- [ ] Conectare SSH la server funcțională
- [ ] Acces MySQL cu user root
- [ ] Bază de date `biblioteca` creată
- [ ] Tabele create (`carti`, `cititori`, `imprumuturi`)
- [ ] Date de test inserate (opțional)
- [ ] Configurație `config.php` actualizată
- [ ] Test conexiune din PHP reușit
- [ ] Backup configurat (opțional dar recomandat)

---

**🎉 Felicitări! Ai creat cu succes baza de date `biblioteca` pe serverul Linux!**

