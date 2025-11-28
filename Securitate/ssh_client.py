#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SSH Client pentru Server Linux - Înlocuitor PuTTY
Folosește paramiko pentru conexiune SSH interactivă
"""

import paramiko
import sys
import os
import getpass
from typing import Optional

# Configurație server
SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # Poate fi modificat sau citit din variabilă de mediu

# Configurație aplicație
APP_PATH = "/var/www/html/biblioteca"
DB_NAME = "biblioteca"
WEB_URL = f"http://{SERVER_IP}/biblioteca/"

# Variabilă globală pentru calea PHP
PHP_CMD = "php"

class SSHClient:
    def __init__(self, hostname: str, port: int, username: str, password: str):
        self.hostname = hostname
        self.port = port
        self.username = username
        self.password = password
        self.client: Optional[paramiko.SSHClient] = None
        
    def connect(self) -> bool:
        """Conectează la server"""
        try:
            self.client = paramiko.SSHClient()
            self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            
            print(f"🔌 Conectare la {self.hostname}:{self.port}...")
            self.client.connect(
                hostname=self.hostname,
                port=self.port,
                username=self.username,
                password=self.password,
                timeout=10,
                look_for_keys=False,
                allow_agent=False
            )
            print("✅ Conectat cu succes!\n")
            return True
        except paramiko.AuthenticationException:
            print("❌ Eroare: Autentificare eșuată!")
            return False
        except paramiko.SSHException as e:
            print(f"❌ Eroare SSH: {e}")
            return False
        except Exception as e:
            print(f"❌ Eroare conexiune: {e}")
            return False
    
    def execute_command(self, command: str) -> tuple[str, str, int]:
        """Execută o comandă și returnează output, error și exit code"""
        if not self.client:
            return "", "Nu este conectat", 1
        
        try:
            stdin, stdout, stderr = self.client.exec_command(command)
            exit_code = stdout.channel.recv_exit_status()
            output = stdout.read().decode('utf-8', errors='ignore')
            error = stderr.read().decode('utf-8', errors='ignore')
            return output, error, exit_code
        except Exception as e:
            return "", f"Eroare execuție: {e}", 1
    
    def close(self):
        """Închide conexiunea"""
        if self.client:
            self.client.close()
            print("\n👋 Conexiune închisă.")

def print_header():
    """Afișează header-ul"""
    print("=" * 70)
    print("🔧 SSH CLIENT - Verificare Server Linux Biblioteca")
    print("=" * 70)
    print(f"📍 Server: {SERVER_IP}")
    print(f"🌐 URL Aplicație: {WEB_URL}")
    print(f"💾 Baza de date: {DB_NAME} (localhost)")
    print(f"📁 Path aplicație: {APP_PATH}")
    print("=" * 70)
    print()

def print_menu():
    """Afișează meniul principal"""
    print("\n" + "=" * 70)
    print("📋 MENIU PRINCIPAL")
    print("=" * 70)
    print("1.  📊 Verificare spațiu disc")
    print("2.  🗄️  Verificare MySQL/MariaDB (versiune, status)")
    print("3.  📚 Verificare baze de date existente")
    print("4.  🔍 Verificare baza de date 'biblioteca' (tabele, dimensiuni)")
    print("5.  📁 Verificare fișiere aplicație (existență, permisiuni)")
    print("6.  🌐 Verificare configurație web server (Apache/Nginx)")
    print("7.  🔌 Verificare conexiune bază de date (test PHP)")
    print("8.  📝 Verificare log-uri (Apache, PHP, MySQL)")
    print("9.  ⚙️  Verificare servicii (Apache, MySQL, PHP-FPM)")
    print("10. 🔐 Verificare permisiuni fișiere")
    print("11. 📈 Statistici baza de date (număr înregistrări)")
    print("12. 🧪 Test acces web (curl)")
    print("13. 🔄 Verificare completă (toate verificările)")
    print("14. 💻 Shell interactiv")
    print("15. 📋 Informații despre server")
    print("0.  🚪 Ieșire")
    print("=" * 70)

def verificare_spatiu_disc(ssh: SSHClient):
    """Verifică spațiul disponibil pe disc"""
    print("\n📊 VERIFICARE SPATIU DISC")
    print("-" * 70)
    output, error, code = ssh.execute_command("df -h")
    print(output)
    if error:
        print(f"⚠️ Erori: {error}")

def verificare_mysql(ssh: SSHClient):
    """Verifică MySQL/MariaDB"""
    print("\n🗄️  VERIFICARE MYSQL/MARIADB")
    print("-" * 70)
    
    # Versiune
    print("📌 Versiune:")
    output, _, _ = ssh.execute_command("mysql --version 2>&1 || mariadb --version 2>&1 || /usr/bin/mysql --version 2>&1 || echo 'MySQL/MariaDB nu este în PATH'")
    print(output)
    
    # Detectează tipul de sistem (systemd sau init.d)
    output, _, code = ssh.execute_command("which systemctl 2>&1")
    has_systemctl = code == 0 and "systemctl" in output and "not found" not in output.lower()
    
    # Status
    print("\n📌 Status serviciu:")
    if has_systemctl:
        output, _, _ = ssh.execute_command("systemctl status mysql 2>&1 | head -10 || systemctl status mariadb 2>&1 | head -10 || echo 'Serviciul nu rulează sau nu este instalat'")
    else:
        # Sistem vechi - folosește service
        output, _, _ = ssh.execute_command("service mysqld status 2>&1 | head -10 || service mysql status 2>&1 | head -10 || /etc/init.d/mysqld status 2>&1 | head -10 || echo 'Serviciul nu rulează sau nu este instalat'")
    print(output)
    
    # Procese
    print("\n📌 Procese MySQL:")
    output, _, _ = ssh.execute_command("ps aux | grep -iE 'mysql|mariadb' | grep -v grep || echo 'Nu s-au găsit procese MySQL'")
    if output.strip() and "Nu s-au găsit" not in output:
        print(output)
    else:
        print("Nu s-au găsit procese MySQL")

def verificare_baze_date(ssh: SSHClient):
    """Verifică bazele de date existente"""
    print("\n📚 BAZE DE DATE EXISTENTE")
    print("-" * 70)
    
    # Listă baze de date
    output, error, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e 'SHOW DATABASES;' 2>&1 | grep -v '^Database$' | grep -v '^information_schema$' | grep -v '^performance_schema$' | grep -v '^mysql$' | grep -v '^sys$'".format(SSH_PASS)
    )
    print("Baze de date:")
    print(output)
    
    # Dimensiuni baze de date
    print("\n📊 Dimensiuni baze de date (MB):")
    output, error, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e \"SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables GROUP BY table_schema ORDER BY table_schema;\" 2>&1".format(SSH_PASS)
    )
    print(output)

def verificare_baza_biblioteca(ssh: SSHClient):
    """Verifică baza de date 'biblioteca'"""
    print("\n🔍 VERIFICARE BAZA DE DATE 'biblioteca'")
    print("-" * 70)
    
    # Verifică dacă există
    output, error, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e 'SHOW DATABASES LIKE \"biblioteca\";' 2>&1".format(SSH_PASS)
    )
    if "biblioteca" not in output:
        print("⚠️ Baza de date 'biblioteca' NU există!")
        return
    
    print("✅ Baza de date 'biblioteca' există!\n")
    
    # Tabele
    print("📋 Tabele:")
    output, _, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e 'USE biblioteca; SHOW TABLES;' 2>&1".format(SSH_PASS)
    )
    print(output)
    
    # Dimensiune
    print("\n📊 Dimensiune baza de date:")
    output, _, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e \"SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'biblioteca';\" 2>&1".format(SSH_PASS)
    )
    print(output)
    
    # Număr înregistrări per tabel
    print("\n📈 Număr înregistrări per tabel:")
    output, _, _ = ssh.execute_command(
        "mysql -u root -p'{}' -e \"SELECT table_name AS 'Tabel', table_rows AS 'Randuri' FROM information_schema.tables WHERE table_schema = 'biblioteca' ORDER BY table_name;\" 2>&1".format(SSH_PASS)
    )
    print(output)

def verificare_fisiere_aplicatie(ssh: SSHClient):
    """Verifică fișierele aplicației"""
    print("\n📁 VERIFICARE FIȘIERE APLICAȚIE")
    print("-" * 70)
    
    # Verifică dacă directorul există
    output, _, _ = ssh.execute_command(f"test -d {APP_PATH} && echo '✅ Director există' || echo '❌ Director NU există: {APP_PATH}'")
    print(output)
    
    if "NU există" in output:
        print(f"\n💡 Directorul aplicației nu există încă.")
        print(f"💡 Creează-l cu: mkdir -p {APP_PATH}")
        return
    
    # Listă fișiere
    print(f"\n📋 Fișiere în {APP_PATH}:")
    output, _, _ = ssh.execute_command(f"ls -lah {APP_PATH} 2>&1 | head -20")
    if output.strip() and "No such file" not in output:
        print(output)
    else:
        print("⚠️ Directorul este gol sau nu poate fi citit")
    
    # Verifică fișiere importante
    print("\n🔍 Verificare fișiere importante:")
    files = ["index.php", "config.php", "scanare_rapida.php", "imprumuturi.php"]
    for file in files:
        output, _, _ = ssh.execute_command(f"test -f {APP_PATH}/{file} && echo '✅ {file}' || echo '❌ {file} LIPSĂ'")
        result = output.strip()
        if result:
            print(result)

def verificare_web_server(ssh: SSHClient):
    """Verifică configurația web server"""
    print("\n🌐 VERIFICARE WEB SERVER")
    print("-" * 70)
    
    # Detectează tipul de sistem
    output, _, code = ssh.execute_command("which systemctl 2>&1")
    has_systemctl = code == 0 and "systemctl" in output and "not found" not in output.lower()
    
    # Verifică Apache
    print("📌 Apache:")
    if has_systemctl:
        output, _, _ = ssh.execute_command("systemctl status apache2 2>&1 | head -5 || systemctl status httpd 2>&1 | head -5 || echo 'Apache nu rulează sau nu este instalat'")
    else:
        output, _, _ = ssh.execute_command("service httpd status 2>&1 | head -5 || service apache2 status 2>&1 | head -5 || /etc/init.d/httpd status 2>&1 | head -5 || echo 'Apache nu rulează sau nu este instalat'")
    print(output)
    
    # Verifică dacă Apache rulează prin procese
    output, _, _ = ssh.execute_command("ps aux | grep -iE 'httpd|apache' | grep -v grep | head -2")
    if output.strip():
        print(f"✅ Procese Apache găsite: {len(output.strip().split(chr(10)))}")
    else:
        print("⚠️ Nu s-au găsit procese Apache")
    
    # Verifică Nginx
    print("\n📌 Nginx:")
    if has_systemctl:
        output, _, _ = ssh.execute_command("systemctl status nginx 2>&1 | head -5 || echo 'Nginx nu rulează sau nu este instalat'")
    else:
        output, _, _ = ssh.execute_command("service nginx status 2>&1 | head -5 || echo 'Nginx nu rulează sau nu este instalat'")
    print(output)
    
    # Verifică PHP - caută în mai multe locații
    print("\n📌 PHP:")
    php_paths = ["php", "/usr/bin/php", "/usr/local/bin/php", "/opt/php/bin/php", "/usr/bin/php-cli"]
    php_found = False
    global PHP_CMD
    for php_path in php_paths:
        output, error, code = ssh.execute_command(f"{php_path} -v 2>&1")
        # Verifică dacă comanda a reușit (exit code 0) și dacă output-ul conține versiunea PHP
        if code == 0 and ("PHP" in output or ("php" in output.lower() and "command not found" not in error.lower() and "command not found" not in output.lower())):
            print(f"✅ PHP găsit la: {php_path}")
            print(output.split('\n')[0] if output else "Versiune PHP găsită")
            php_found = True
            # Salvează calea pentru extensii
            PHP_CMD = php_path
            break
    
    if not php_found:
        print("❌ PHP nu este găsit în PATH sau nu este instalat")
        print("💡 Verifică dacă PHP este instalat: which php || find /usr -name php 2>/dev/null | head -3")
        PHP_CMD = "php"  # Fallback
    
    # Verifică extensii PHP
    print("\n📌 Extensii PHP importante:")
    extensions = ["pdo_mysql", "mbstring", "dom", "xml"]
    for ext in extensions:
        output, _, _ = ssh.execute_command(f"{PHP_CMD} -m 2>&1 | grep -i {ext} && echo '✅ {ext}' || echo '❌ {ext} LIPSĂ'")
        result = output.strip()
        if result:
            print(result)
        else:
            print(f"❌ {ext} LIPSĂ")

def verificare_conexiune_db(ssh: SSHClient):
    """Verifică conexiunea la baza de date"""
    print("\n🔌 VERIFICARE CONEXIUNE BAZĂ DE DATE")
    print("-" * 70)
    
    # Test conexiune MySQL - caută mysql în mai multe locații
    print("📌 Test conexiune MySQL:")
    mysql_paths = ["mysql", "/usr/bin/mysql", "/usr/local/bin/mysql"]
    mysql_found = False
    for mysql_path in mysql_paths:
        output, error, _ = ssh.execute_command(
            f"{mysql_path} -u root -p'{SSH_PASS}' -e 'SELECT 1;' 2>&1"
        )
        if "ERROR" not in output and "ERROR" not in error and ("1" in output or "mysql>" not in output.lower()):
            print(f"✅ Conexiune MySQL funcționează! (folosind {mysql_path})")
            mysql_found = True
            break
    
    if not mysql_found:
        print("❌ Nu s-a putut conecta la MySQL")
        print("💡 Verifică dacă MySQL rulează: ps aux | grep mysql")
        print("💡 Verifică parola root MySQL")
    
    # Test conexiune din PHP
    print("\n📌 Test conexiune din PHP:")
    test_php = f"""<?php
try {{
    $pdo = new PDO('mysql:host=localhost;dbname={DB_NAME}', 'root', '{SSH_PASS}');
    echo '✅ Conexiune PHP funcționează!';
}} catch (Exception $e) {{
    echo '❌ Eroare: ' . $e->getMessage();
}}
?>"""
    
    output, _, _ = ssh.execute_command(
        f"echo '{test_php}' | {PHP_CMD} 2>&1"
    )
    if output.strip():
        print(output)
    else:
        print("⚠️ PHP nu a returnat output (posibil nu este instalat sau nu funcționează)")

def verificare_loguri(ssh: SSHClient):
    """Verifică log-urile"""
    print("\n📝 VERIFICARE LOG-URI")
    print("-" * 70)
    
    # Log Apache - verifică mai multe locații
    print("📌 Ultimele 10 linii log Apache:")
    log_paths_apache = [
        "/var/log/apache2/error.log",
        "/var/log/httpd/error_log",
        "/var/log/httpd/error.log",
        "/var/log/apache/error.log",
        "/var/log/messages"  # Red Hat vechi
    ]
    found = False
    for log_path in log_paths_apache:
        output, _, _ = ssh.execute_command(f"test -f {log_path} && tail -10 {log_path} 2>&1")
        if output.strip() and "No such file" not in output and "cannot open" not in output:
            print(f"✅ Log găsit: {log_path}")
            print(output)
            found = True
            break
    if not found:
        print("⚠️ Nu s-a găsit log Apache în locațiile standard")
        print("💡 Caută manual: find /var/log -name '*apache*' -o -name '*httpd*' 2>/dev/null | head -5")
    
    # Log PHP
    print("\n📌 Ultimele 10 linii log PHP:")
    log_paths_php = [
        "/var/log/php_errors.log",
        "/var/log/php-fpm/error.log",
        "/var/log/php5-fpm.log",
        "/var/log/php.log"
    ]
    found = False
    for log_path in log_paths_php:
        output, _, _ = ssh.execute_command(f"test -f {log_path} && tail -10 {log_path} 2>&1")
        if output.strip() and "No such file" not in output:
            print(f"✅ Log găsit: {log_path}")
            print(output)
            found = True
            break
    if not found:
        # Caută în directorul aplicației
        output, _, _ = ssh.execute_command(f"test -d {APP_PATH}/logs && tail -10 {APP_PATH}/logs/php_errors.log 2>&1 || echo 'Nu s-a găsit log PHP'")
        if "Nu s-a găsit" not in output:
            print(f"✅ Log găsit în aplicație: {APP_PATH}/logs/php_errors.log")
            print(output)
        else:
            print("⚠️ Nu s-a găsit log PHP")
    
    # Log MySQL
    print("\n📌 Ultimele 10 linii log MySQL:")
    log_paths_mysql = [
        "/var/log/mysql/error.log",
        "/var/log/mysqld.log",
        "/var/log/mysql.log",
        "/var/lib/mysql/*.err"
    ]
    found = False
    for log_path in log_paths_mysql:
        if "*" in log_path:
            # Pentru wildcard, folosește find
            output, _, _ = ssh.execute_command(f"find /var/lib/mysql -name '*.err' 2>/dev/null | head -1 | xargs tail -10 2>&1")
        else:
            output, _, _ = ssh.execute_command(f"test -f {log_path} && tail -10 {log_path} 2>&1")
        if output.strip() and "No such file" not in output and "cannot open" not in output:
            print(f"✅ Log găsit: {log_path}")
            print(output)
            found = True
            break
    if not found:
        print("⚠️ Nu s-a găsit log MySQL în locațiile standard")
        print("💡 Caută manual: find /var/log /var/lib/mysql -name '*mysql*' -o -name '*.err' 2>/dev/null | head -5")

def verificare_servicii(ssh: SSHClient):
    """Verifică serviciile"""
    print("\n⚙️  VERIFICARE SERVIcii")
    print("-" * 70)
    
    # Detectează tipul de sistem
    output, _, code = ssh.execute_command("which systemctl 2>&1")
    has_systemctl = code == 0 and "systemctl" in output and "not found" not in output.lower()
    
    services = ["apache2", "httpd", "nginx", "mysql", "mariadb", "mysqld", "php-fpm", "php8.1-fpm", "php8.2-fpm"]
    
    for service in services:
        if has_systemctl:
            output, _, _ = ssh.execute_command(f"systemctl is-active {service} 2>&1")
        else:
            # Sistem vechi - folosește service
            output, _, _ = ssh.execute_command(f"service {service} status 2>&1 | head -1")
        
        if "active" in output.lower() or "running" in output.lower():
            print(f"✅ {service}: ACTIV")
        elif "inactive" in output.lower() or "stopped" in output.lower():
            print(f"⚠️  {service}: INACTIV")
        # Verifică dacă există procesul chiar dacă serviciul nu este găsit
        else:
            output, _, _ = ssh.execute_command(f"ps aux | grep -i {service} | grep -v grep | head -1")
            if output.strip():
                print(f"ℹ️  {service}: Proces găsit (dar serviciul nu este înregistrat)")

def verificare_permisiuni(ssh: SSHClient):
    """Verifică permisiunile fișierelor"""
    print("\n🔐 VERIFICARE PERMISIUNI")
    print("-" * 70)
    
    # Verifică dacă directorul există
    output, _, _ = ssh.execute_command(f"test -d {APP_PATH} && echo '✅ Director există' || echo '❌ Director NU există: {APP_PATH}'")
    print(output)
    
    if "NU există" in output:
        print(f"\n💡 Directorul aplicației nu există încă.")
        print(f"💡 Creează-l cu: mkdir -p {APP_PATH}")
        return
    
    output, _, _ = ssh.execute_command(f"ls -lah {APP_PATH} 2>&1 | head -15")
    if output.strip():
        print(f"\n📋 Fișiere în {APP_PATH}:")
        print(output)
    else:
        print(f"\n⚠️ Directorul este gol sau nu poate fi citit")
    
    # Verifică owner
    print("\n📌 Owner și grup:")
    output, _, _ = ssh.execute_command(f"stat -c '%U:%G' {APP_PATH} 2>&1 || ls -ld {APP_PATH} 2>&1 | awk '{{print $3\":\"$4}}'")
    if output.strip() and "cannot stat" not in output:
        print(output)
    else:
        print("⚠️ Nu s-a putut determina owner-ul")

def statistici_baza_date(ssh: SSHClient):
    """Afișează statistici baza de date"""
    print("\n📈 STATISTICI BAZĂ DE DATE")
    print("-" * 70)
    
    queries = {
        "Total cărți": "SELECT COUNT(*) FROM carti",
        "Total cititori": "SELECT COUNT(*) FROM cititori",
        "Împrumuturi active": "SELECT COUNT(*) FROM imprumuturi WHERE status='activ'",
        "Împrumuturi returnate": "SELECT COUNT(*) FROM imprumuturi WHERE status='returnat'",
    }
    
    for name, query in queries.items():
        output, error, _ = ssh.execute_command(
            f"mysql -u root -p'{SSH_PASS}' -e 'USE {DB_NAME}; {query};' 2>&1 | tail -1"
        )
        if "ERROR" not in output and "ERROR" not in error:
            print(f"{name}: {output.strip()}")
        else:
            print(f"{name}: Eroare - {error}")

def test_acces_web(ssh: SSHClient):
    """Testează accesul web"""
    print("\n🧪 TEST ACCES WEB")
    print("-" * 70)
    
    print(f"📌 Test acces: {WEB_URL}")
    output, error, _ = ssh.execute_command(f"curl -I {WEB_URL} 2>&1 | head -10")
    print(output)
    
    if "200" in output or "301" in output or "302" in output:
        print("✅ Aplicația este accesibilă!")
    else:
        print("⚠️ Aplicația nu este accesibilă sau returnează eroare")

def verificare_completa(ssh: SSHClient):
    """Rulează toate verificările"""
    print("\n🔄 VERIFICARE COMPLETĂ")
    print("=" * 70)
    
    verificari = [
        ("Spațiu disc", verificare_spatiu_disc),
        ("MySQL/MariaDB", verificare_mysql),
        ("Baze de date", verificare_baze_date),
        ("Baza biblioteca", verificare_baza_biblioteca),
        ("Fișiere aplicație", verificare_fisiere_aplicatie),
        ("Web server", verificare_web_server),
        ("Conexiune DB", verificare_conexiune_db),
        ("Servicii", verificare_servicii),
        ("Permisiuni", verificare_permisiuni),
        ("Statistici DB", statistici_baza_date),
        ("Acces web", test_acces_web),
    ]
    
    for nume, func in verificari:
        print(f"\n{'='*70}")
        print(f"🔍 {nume.upper()}")
        print('='*70)
        try:
            func(ssh)
        except Exception as e:
            print(f"❌ Eroare la verificare {nume}: {e}")
    
    print("\n✅ Verificare completă terminată!")

def shell_interactiv(ssh: SSHClient):
    """Shell interactiv"""
    print("\n💻 SHELL INTERACTIV")
    print("-" * 70)
    print("💡 Introdu comenzi shell Linux (ex: ls, pwd, cat /etc/passwd)")
    print("💡 Introdu 'exit', 'quit' sau 'q' pentru a ieși din shell")
    print("-" * 70)
    
    while True:
        try:
            comanda = input(f"\n{SSH_USER}@{SERVER_IP}:$ ").strip()
            
            if not comanda:
                continue
            
            if comanda.lower() in ['exit', 'quit', 'q']:
                break
            
            output, error, code = ssh.execute_command(comanda)
            if output:
                print(output)
            if error and error.strip():
                print(f"⚠️ Erori: {error}")
            if code != 0 and not output and not error:
                # Doar dacă nu există output sau error, afișează exit code
                pass
        except KeyboardInterrupt:
            print("\n\n👋 Ieșire din shell...")
            break
        except Exception as e:
            print(f"❌ Eroare: {e}")

def info_server(ssh: SSHClient):
    """Afișează informații despre server"""
    print("\n📋 INFORMAȚII SERVER")
    print("-" * 70)
    
    # OS
    print("📌 Sistem de operare:")
    output, _, _ = ssh.execute_command("cat /etc/os-release 2>&1 | grep -E '^NAME|^VERSION' | head -2")
    print(output)
    
    # Kernel
    print("\n📌 Kernel:")
    output, _, _ = ssh.execute_command("uname -a")
    print(output)
    
    # Uptime
    print("\n📌 Uptime:")
    output, _, _ = ssh.execute_command("uptime")
    print(output)
    
    # Memorie
    print("\n📌 Memorie:")
    output, _, _ = ssh.execute_command("free -h")
    print(output)
    
    # IP
    print("\n📌 IP-uri:")
    output, _, _ = ssh.execute_command("hostname -I")
    print(output)

def main():
    """Funcția principală"""
    print_header()
    
    # Conectare
    ssh = SSHClient(SERVER_IP, SSH_PORT, SSH_USER, SSH_PASS)
    
    if not ssh.connect():
        print("\n❌ Nu s-a putut conecta la server!")
        sys.exit(1)
    
    # Meniu principal
    while True:
        try:
            print_menu()
            alegere = input("\n👉 Alege opțiunea: ").strip()
            
            if alegere == "0":
                break
            elif alegere == "1":
                verificare_spatiu_disc(ssh)
            elif alegere == "2":
                verificare_mysql(ssh)
            elif alegere == "3":
                verificare_baze_date(ssh)
            elif alegere == "4":
                verificare_baza_biblioteca(ssh)
            elif alegere == "5":
                verificare_fisiere_aplicatie(ssh)
            elif alegere == "6":
                verificare_web_server(ssh)
            elif alegere == "7":
                verificare_conexiune_db(ssh)
            elif alegere == "8":
                verificare_loguri(ssh)
            elif alegere == "9":
                verificare_servicii(ssh)
            elif alegere == "10":
                verificare_permisiuni(ssh)
            elif alegere == "11":
                statistici_baza_date(ssh)
            elif alegere == "12":
                test_acces_web(ssh)
            elif alegere == "13":
                verificare_completa(ssh)
            elif alegere == "14":
                shell_interactiv(ssh)
            elif alegere == "15":
                info_server(ssh)
            else:
                print("❌ Opțiune invalidă!")
            
            input("\n📌 Apasă Enter pentru a continua...")
            
        except KeyboardInterrupt:
            print("\n\n👋 Ieșire...")
            break
        except Exception as e:
            print(f"\n❌ Eroare: {e}")
            input("\n📌 Apasă Enter pentru a continua...")
    
    ssh.close()
    print("\n👋 La revedere!")

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n👋 Ieșire...")
        sys.exit(0)

