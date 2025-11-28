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
    output, _, _ = ssh.execute_command("mysql --version 2>&1 || mariadb --version 2>&1 || echo 'MySQL/MariaDB nu este în PATH'")
    print(output)
    
    # Status
    print("\n📌 Status serviciu:")
    output, _, _ = ssh.execute_command("systemctl status mysql 2>&1 | head -10 || systemctl status mariadb 2>&1 | head -10 || service mysql status 2>&1 | head -10 || echo 'Nu s-a putut verifica statusul'")
    print(output)
    
    # Procese
    print("\n📌 Procese MySQL:")
    output, _, _ = ssh.execute_command("ps aux | grep -i mysql | grep -v grep || echo 'Nu s-au găsit procese MySQL'")
    print(output)

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
    output, _, _ = ssh.execute_command(f"test -d {APP_PATH} && echo '✅ Director există' || echo '❌ Director NU există'")
    print(output)
    
    # Listă fișiere
    print(f"\n📋 Fișiere în {APP_PATH}:")
    output, _, _ = ssh.execute_command(f"ls -lah {APP_PATH} 2>&1 | head -20")
    print(output)
    
    # Verifică fișiere importante
    print("\n🔍 Verificare fișiere importante:")
    files = ["index.php", "config.php", "scanare_rapida.php", "imprumuturi.php"]
    for file in files:
        output, _, _ = ssh.execute_command(f"test -f {APP_PATH}/{file} && echo '✅ {file}' || echo '❌ {file} LIPSĂ'")
        print(output.strip())

def verificare_web_server(ssh: SSHClient):
    """Verifică configurația web server"""
    print("\n🌐 VERIFICARE WEB SERVER")
    print("-" * 70)
    
    # Verifică Apache
    print("📌 Apache:")
    output, _, _ = ssh.execute_command("systemctl status apache2 2>&1 | head -5 || systemctl status httpd 2>&1 | head -5 || echo 'Apache nu rulează sau nu este instalat'")
    print(output)
    
    # Verifică Nginx
    print("\n📌 Nginx:")
    output, _, _ = ssh.execute_command("systemctl status nginx 2>&1 | head -5 || echo 'Nginx nu rulează sau nu este instalat'")
    print(output)
    
    # Verifică PHP
    print("\n📌 PHP:")
    output, _, _ = ssh.execute_command("php -v 2>&1 | head -3")
    print(output)
    
    # Verifică extensii PHP
    print("\n📌 Extensii PHP importante:")
    extensions = ["pdo_mysql", "mbstring", "dom", "xml"]
    for ext in extensions:
        output, _, _ = ssh.execute_command(f"php -m 2>&1 | grep -i {ext} && echo '✅ {ext}' || echo '❌ {ext} LIPSĂ'")
        print(output.strip())

def verificare_conexiune_db(ssh: SSHClient):
    """Verifică conexiunea la baza de date"""
    print("\n🔌 VERIFICARE CONEXIUNE BAZĂ DE DATE")
    print("-" * 70)
    
    # Test conexiune MySQL
    print("📌 Test conexiune MySQL:")
    output, error, _ = ssh.execute_command(
        f"mysql -u root -p'{SSH_PASS}' -e 'SELECT 1;' 2>&1"
    )
    if "ERROR" in output or "ERROR" in error:
        print(f"❌ Eroare conexiune: {output}{error}")
    else:
        print("✅ Conexiune MySQL funcționează!")
    
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
        f"echo '{test_php}' | php 2>&1"
    )
    print(output)

def verificare_loguri(ssh: SSHClient):
    """Verifică log-urile"""
    print("\n📝 VERIFICARE LOG-URI")
    print("-" * 70)
    
    # Log Apache
    print("📌 Ultimele 10 linii log Apache:")
    output, _, _ = ssh.execute_command(
        "tail -10 /var/log/apache2/error.log 2>&1 || tail -10 /var/log/httpd/error_log 2>&1 || echo 'Nu s-a găsit log Apache'"
    )
    print(output)
    
    # Log PHP
    print("\n📌 Ultimele 10 linii log PHP:")
    output, _, _ = ssh.execute_command(
        "tail -10 /var/log/php*.log 2>&1 | head -10 || echo 'Nu s-a găsit log PHP'"
    )
    print(output)
    
    # Log MySQL
    print("\n📌 Ultimele 10 linii log MySQL:")
    output, _, _ = ssh.execute_command(
        "tail -10 /var/log/mysql/error.log 2>&1 || tail -10 /var/log/mysqld.log 2>&1 || echo 'Nu s-a găsit log MySQL'"
    )
    print(output)

def verificare_servicii(ssh: SSHClient):
    """Verifică serviciile"""
    print("\n⚙️  VERIFICARE SERVIcii")
    print("-" * 70)
    
    services = ["apache2", "httpd", "nginx", "mysql", "mariadb", "php-fpm", "php8.1-fpm", "php8.2-fpm"]
    
    for service in services:
        output, _, _ = ssh.execute_command(f"systemctl is-active {service} 2>&1")
        if "active" in output.lower():
            print(f"✅ {service}: ACTIV")
        elif "inactive" in output.lower():
            print(f"⚠️  {service}: INACTIV")
        # Ignoră erorile pentru servicii care nu există

def verificare_permisiuni(ssh: SSHClient):
    """Verifică permisiunile fișierelor"""
    print("\n🔐 VERIFICARE PERMISIUNI")
    print("-" * 70)
    
    output, _, _ = ssh.execute_command(f"ls -lah {APP_PATH} 2>&1 | head -15")
    print(output)
    
    # Verifică owner
    print("\n📌 Owner și grup:")
    output, _, _ = ssh.execute_command(f"stat -c '%U:%G' {APP_PATH} 2>&1 || ls -ld {APP_PATH} | awk '{{print $3\":\"$4}}'")
    print(output)

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
    print("Introdu 'exit' pentru a ieși din shell")
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
            if error:
                print(f"⚠️ Erori: {error}")
            if code != 0:
                print(f"⚠️ Exit code: {code}")
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

