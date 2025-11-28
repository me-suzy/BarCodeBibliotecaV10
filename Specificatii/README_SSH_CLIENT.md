# 🔧 SSH Client Python - Înlocuitor PuTTY

Script Python interactiv pentru conectare și administrare server Linux folosind `paramiko`.

## 📋 Cerințe

### Instalare Python:
- Python 3.6 sau mai nou
- pip (package manager Python)

### Instalare dependențe:
```bash
pip install -r requirements.txt
```

**Sau direct:**
```bash
pip install paramiko
```

## 🚀 Utilizare

### Rulare script:
```bash
python ssh_client.py
```

**Sau pe Linux/Mac:**
```bash
chmod +x ssh_client.py
./ssh_client.py
```

## 📋 Opțiuni Disponibile

Scriptul oferă un meniu interactiv cu următoarele opțiuni:

1. **📊 Verificare spațiu disc** - Afișează utilizarea discului
2. **🗄️ Verificare MySQL/MariaDB** - Versiune, status, procese
3. **📚 Verificare baze de date existente** - Listă toate bazele de date
4. **🔍 Verificare baza de date 'biblioteca'** - Tabele, dimensiuni, înregistrări
5. **📁 Verificare fișiere aplicație** - Existență, permisiuni
6. **🌐 Verificare configurație web server** - Apache/Nginx, PHP, extensii
7. **🔌 Verificare conexiune bază de date** - Test conexiune MySQL și PHP
8. **📝 Verificare log-uri** - Apache, PHP, MySQL
9. **⚙️ Verificare servicii** - Status servicii (Apache, MySQL, PHP-FPM)
10. **🔐 Verificare permisiuni fișiere** - Owner, grup, permisiuni
11. **📈 Statistici baza de date** - Număr înregistrări per tabel
12. **🧪 Test acces web** - Verifică dacă aplicația este accesibilă
13. **🔄 Verificare completă** - Rulează toate verificările
14. **💻 Shell interactiv** - Shell interactiv pentru comenzi personalizate
15. **📋 Informații despre server** - OS, kernel, uptime, memorie, IP

## ⚙️ Configurare

### Modificare credențiale:
Editează variabilele din `ssh_client.py`:
```python
SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"
```

### Modificare path aplicație:
```python
APP_PATH = "/var/www/html/biblioteca"
DB_NAME = "biblioteca"
```

## 🔒 Securitate

⚠️ **ATENȚIE:** Parola este hardcodată în script pentru simplitate.

Pentru securitate mai bună:
1. Folosește variabile de mediu:
   ```python
   import os
   SSH_PASS = os.getenv('SSH_PASSWORD', 'YOUR-SERVER-PASSWORD')
   ```

2. Sau folosește fișier de configurare (JSON/YAML) cu permisiuni restricționate

3. Sau folosește chei SSH în loc de parolă

## 📝 Exemple

### Verificare rapidă:
```bash
python ssh_client.py
# Alege opțiunea 13 (Verificare completă)
```

### Shell interactiv:
```bash
python ssh_client.py
# Alege opțiunea 14 (Shell interactiv)
# Apoi rulează comenzi Linux normale
```

### Verificare baza de date:
```bash
python ssh_client.py
# Alege opțiunea 4 (Verificare baza biblioteca)
```

## 🐛 Depanare

### Eroare: "ModuleNotFoundError: No module named 'paramiko'"
**Soluție:** Instalează paramiko:
```bash
pip install paramiko
```

### Eroare: "Authentication failed"
**Soluție:** Verifică credențialele în script sau pe server

### Eroare: "Connection timeout"
**Soluție:** 
- Verifică dacă serverul este accesibil: `ping YOUR-IP-or-http://WEBSITE`
- Verifică firewall-ul
- Verifică dacă portul SSH (22) este deschis

### Eroare: "Host key verification failed"
**Soluție:** Scriptul folosește `AutoAddPolicy()` care acceptă automat cheile. Dacă apare eroarea, verifică manual cheia serverului.

## 📚 Documentație Paramiko

Pentru mai multe informații despre paramiko:
- https://www.paramiko.org/
- https://github.com/paramiko/paramiko

## 🔄 Alternativă: Folosire directă SSH

Dacă preferi să folosești SSH direct în loc de script:
```bash
ssh root@YOUR-IP-or-http://WEBSITE
```

**Sau cu opțiuni:**
```bash
ssh -o StrictHostKeyChecking=no \
    -o KexAlgorithms=+diffie-hellman-group-exchange-sha1 \
    -o HostKeyAlgorithms=+ssh-rsa \
    -o MACs=+hmac-sha1 \
    root@YOUR-IP-or-http://WEBSITE
```

## ✅ Avantaje față de PuTTY

1. ✅ **Automatizare** - Poți rula verificări automate
2. ✅ **Scriptabil** - Poți integra în alte scripturi
3. ✅ **Cross-platform** - Funcționează pe Windows, Linux, Mac
4. ✅ **Interactiv** - Meniu simplu și intuitiv
5. ✅ **Verificări predefinite** - Verificări comune gata de folosit
6. ✅ **Shell interactiv** - Poți rula comenzi personalizate

