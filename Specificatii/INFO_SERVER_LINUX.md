# 📋 Informații Server Linux - Biblioteca

## 🌐 Acces Web

### URL Aplicație
```
YOUR-IP-or-http://WEBSITE/biblioteca/
```

**Sau:**
```
YOUR-IP-or-http://WEBSITE/biblioteca/index.php
```

### Pagini principale:
- **Index/Pagina principală:** `YOUR-IP-or-http://WEBSITE/biblioteca/`
- **Scanare rapidă:** `YOUR-IP-or-http://WEBSITE/biblioteca/scanare_rapida.php`
- **Împrumuturi:** `YOUR-IP-or-http://WEBSITE/biblioteca/imprumuturi.php`
- **Rapoarte:** `YOUR-IP-or-http://WEBSITE/biblioteca/rapoarte.php`
- **Cărți:** `YOUR-IP-or-http://WEBSITE/biblioteca/carti.php`
- **Cititori:** `YOUR-IP-or-http://WEBSITE/biblioteca/cititori.php`

## 💾 Baza de Date

### Configurație conexiune:
- **Host:** `localhost` (sau `127.0.0.1`)
- **Port:** `3306` (default MySQL)
- **Baza de date:** `biblioteca`
- **User:** `root` (sau utilizator dedicat)
- **Parolă:** (vezi configurația serverului)

### Conexiune din aplicație:
Fișierul `config.php` conține:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'parola_aici');
define('DB_NAME', 'biblioteca');
```

### Conexiune din linia de comandă (pe server):
```bash
mysql -u root -p biblioteca
```

### Conexiune din aplicații externe:
**NU este recomandat** să expui MySQL direct pe internet pentru securitate!

Dacă este necesar (doar pentru administrare):
- **Host:** `YOUR-IP-or-http://WEBSITE`
- **Port:** `3306` (trebuie deschis în firewall)
- **User:** `root` sau utilizator dedicat
- **Parolă:** (vezi configurația)

⚠️ **ATENȚIE:** Deschiderea MySQL pe internet este un risc de securitate! Folosește doar pentru administrare și protejează cu firewall!

## 🔌 SSH Acces

### Conectare SSH:
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

### Credențiale:
- **User:** `root`
- **Parolă:** `YOUR-SERVER-PASSWORD`
- **Port:** `22`

## 📁 Structură Fișiere

### Path aplicație:
```
/var/www/html/biblioteca/
```

### Fișiere importante:
- `index.php` - Pagina principală
- `config.php` - Configurație bază de date
- `scanare_rapida.php` - Scanare coduri de bare
- `imprumuturi.php` - Listă împrumuturi
- `rapoarte.php` - Rapoarte și statistici

## 🗄️ Baza de Date - Structură

### Tabele principale:
1. **`carti`** - Cărțile din bibliotecă
2. **`cititori`** - Utilizatorii/cititorii
3. **`imprumuturi`** - Împrumuturile (doar în baza locală)
4. **`sesiuni_utilizatori`** - Sesiunile utilizatorilor
5. **`tracking_sesiuni`** - Tracking complet al acțiunilor
6. **`sesiuni_biblioteca`** - Statistici prezență

### Separare de Aleph:
✅ **Baza de date `biblioteca` este complet separată de Aleph!**
- Aleph este folosit DOAR pentru citire (API web)
- Toate modificările sunt în baza de date locală
- Nu există risc de modificare accidentală în Aleph

## 🔧 Verificare Server

### Folosind scriptul Python:
```bash
python ssh_client.py
```

### Verificări disponibile:
1. Spațiu disc
2. MySQL/MariaDB (versiune, status)
3. Baze de date existente
4. Baza de date 'biblioteca' (tabele, dimensiuni)
5. Fișiere aplicație
6. Configurație web server
7. Conexiune bază de date
8. Log-uri
9. Servicii
10. Permisiuni
11. Statistici baza de date
12. Test acces web
13. Verificare completă
14. Shell interactiv

## 📊 Statistici

### Verificare statistici din aplicație:
Accesează: `YOUR-IP-or-http://WEBSITE/biblioteca/rapoarte.php`

### Verificare din linia de comandă:
```bash
mysql -u root -p biblioteca -e "
SELECT 
    (SELECT COUNT(*) FROM carti) AS 'Total cărți',
    (SELECT COUNT(*) FROM cititori) AS 'Total cititori',
    (SELECT COUNT(*) FROM imprumuturi WHERE status='activ') AS 'Împrumuturi active',
    (SELECT COUNT(*) FROM imprumuturi WHERE status='returnat') AS 'Împrumuturi returnate';
"
```

## 🔒 Securitate

### Recomandări:
1. **Nu expune MySQL pe internet** (doar localhost)
2. **Folosește utilizator MySQL dedicat** (nu root)
3. **Protejează `config.php`** (chmod 600)
4. **Configurează firewall** (blochează MySQL din exterior)
5. **Folosește HTTPS** (dacă este posibil)

## 📝 Notă Importantă

**Când te conectezi la baza de date:**
- **Local (pe server):** `localhost` sau `127.0.0.1`
- **Din aplicație PHP:** `localhost` (din `config.php`)
- **Din aplicații externe:** `YOUR-IP-or-http://WEBSITE` (NU recomandat pentru securitate!)

**Când accesezi aplicația:**
- **Din browser:** `YOUR-IP-or-http://WEBSITE/biblioteca/`
- **IP-ul serverului:** `YOUR-IP-or-http://WEBSITE`
- **Path-ul aplicației:** `/biblioteca/` (subdirector în `/var/www/html/`)

