#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru testarea stabilității serverului Linux și investigarea cauzelor întreruperilor.
Nu modifică nimic, doar verifică statusul sistemului.
Salvează rezultatele în fișiere pentru analiză ulterioară.
"""

import subprocess
import time
from datetime import datetime
import os

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"
CATALOG_URL = f"http://{SERVER_IP}:8991/F"

# Folder pentru rezultate
RESULTS_DIR = os.path.dirname(os.path.abspath(__file__))
TIMESTAMP = datetime.now().strftime("%Y%m%d_%H%M%S")

def execute_ssh_command(command):
    """Execută o comandă SSH folosind plink sau ssh direct"""
    try:
        # Încearcă cu plink (PuTTY) dacă este disponibil
        cmd = [
            "plink",
            "-ssh",
            "-P", str(SSH_PORT),
            "-l", SSH_USER,
            "-pw", SSH_PASS,
            "-batch",
            SERVER_IP,
            command
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        return result.stdout, result.stderr, result.returncode
    except FileNotFoundError:
        # Dacă plink nu există, încearcă cu ssh direct (dacă este configurat)
        try:
            # Folosim sshpass dacă este disponibil
            cmd = [
                "sshpass",
                "-p", SSH_PASS,
                "ssh",
                "-o", "StrictHostKeyChecking=no",
                "-o", "KexAlgorithms=+diffie-hellman-group-exchange-sha1,diffie-hellman-group14-sha1,diffie-hellman-group1-sha1",
                "-o", "HostKeyAlgorithms=+ssh-rsa,ssh-dss",
                "-o", "MACs=+hmac-md5,hmac-sha1",
                "-p", str(SSH_PORT),
                f"{SSH_USER}@{SERVER_IP}",
                command
            ]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            return result.stdout, result.stderr, result.returncode
        except FileNotFoundError:
            return "", "SSH client (plink sau sshpass) nu este disponibil", 1

def save_result(filename, content):
    """Salvează rezultatele într-un fișier"""
    filepath = os.path.join(RESULTS_DIR, f"{TIMESTAMP}_{filename}")
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    return filepath

def test_http_connection():
    """Testează conexiunea HTTP la catalog"""
    import urllib.request
    import urllib.error
    
    try:
        req = urllib.request.Request(CATALOG_URL)
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=10) as response:
            return True, f"HTTP {response.getcode()}", response.read()[:200].decode('utf-8', errors='ignore')
    except urllib.error.URLError as e:
        return False, f"Eroare HTTP: {e}", ""
    except Exception as e:
        return False, f"Eroare: {e}", ""

def main():
    import sys
    import io
    # Fix encoding pentru Windows
    if sys.platform == 'win32':
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    
    print(f"[{datetime.now()}] Inceput verificare server {SERVER_IP}")
    print("=" * 80)
    
    all_results = []
    all_results.append(f"RAPORT VERIFICARE SERVER - {datetime.now()}\n")
    all_results.append(f"Server IP: {SERVER_IP}\n")
    all_results.append(f"Catalog URL: {CATALOG_URL}\n")
    all_results.append("=" * 80 + "\n\n")
    
    # Test HTTP
    print("\n[TEST HTTP] Verificare accesibilitate catalog...")
    http_ok, http_status, http_content = test_http_connection()
    http_result = f"HTTP Test: {'SUCCES' if http_ok else 'ESEC'}\nStatus: {http_status}\n"
    if http_content:
        http_result += f"Conținut (primele 200 caractere): {http_content}\n"
    print(http_result)
    all_results.append(http_result + "\n")
    
    # Test SSH
    print("\n[TEST SSH] Conectare la server...")
    test_cmd = "echo 'SSH Connection Test OK'"
    stdout, stderr, returncode = execute_ssh_command(test_cmd)
    
    if returncode == 0:
        print("✓ Conexiune SSH reușită!")
        all_results.append("SSH Connection: SUCCES\n\n")
        
        # 1. Verificare uptime și resurse de bază
        print("\n[1] STATUS GENERAL SISTEM")
        print("-" * 80)
        section = "[1] STATUS GENERAL SISTEM\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("uptime")
        section += f"Uptime:\n{stdout}\n"
        print(stdout)
        
        stdout, stderr, _ = execute_ssh_command("free -h")
        section += f"\nMemorie:\n{stdout}\n"
        print("Memorie:")
        print(stdout)
        
        stdout, stderr, _ = execute_ssh_command("df -h")
        section += f"\nSpațiu disk:\n{stdout}\n"
        print("Spațiu disk:")
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 2. Verificare procese Aleph
        print("\n[2] PROCESE ALEPH")
        print("-" * 80)
        section = "[2] PROCESE ALEPH\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep")
        if stdout.strip():
            section += stdout + "\n"
            print(stdout)
        else:
            section += "Nu s-au gasit procese Aleph active!\n"
            print("Nu s-au găsit procese Aleph active!")
        
        all_results.append(section + "\n")
        
        # 3. Verificare port 8991
        print("\n[3] VERIFICARE PORT 8991")
        print("-" * 80)
        section = "[3] VERIFICARE PORT 8991\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep 8991 || ss -tuln | grep 8991")
        if stdout.strip():
            section += stdout + "\n"
            print(stdout)
        else:
            section += "Portul 8991 nu este in ascultare!\n"
            print("Portul 8991 nu este în ascultare!")
        
        all_results.append(section + "\n")
        
        # 4. Verificare servicii systemd
        print("\n[4] SERVICII SYSTEMD")
        print("-" * 80)
        section = "[4] SERVICII SYSTEMD\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("systemctl list-units --type=service --state=running 2>/dev/null | grep -i aleph || echo 'Nu s-au găsit servicii Aleph'")
        section += stdout + "\n"
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 5. Verificare loguri sistem recente
        print("\n[5] LOGURI SISTEM RECENTE")
        print("-" * 80)
        section = "[5] LOGURI SISTEM RECENTE (ultimele 50 linii)\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("journalctl -n 50 --no-pager 2>/dev/null || tail -50 /var/log/messages 2>/dev/null || tail -50 /var/log/syslog 2>/dev/null || echo 'Nu s-au găsit loguri'")
        section += stdout + "\n"
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
        
        all_results.append(section + "\n")
        
        # 6. Verificare loguri kernel
        print("\n[6] LOGURI KERNEL (dmesg)")
        print("-" * 80)
        section = "[6] LOGURI KERNEL (dmesg - ultimele 30 linii)\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("dmesg | tail -30")
        section += stdout + "\n"
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
        
        all_results.append(section + "\n")
        
        # 7. Verificare erori în loguri
        print("\n[7] CĂUTARE ERORI ÎN LOGURI")
        print("-" * 80)
        section = "[7] CĂUTARE ERORI ÎN LOGURI\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("journalctl -p err -n 20 --no-pager 2>/dev/null || grep -i error /var/log/messages 2>/dev/null | tail -20 || echo 'Nu s-au găsit erori'")
        section += stdout + "\n"
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
        
        all_results.append(section + "\n")
        
        # 8. Verificare load average și CPU
        print("\n[8] LOAD AVERAGE ȘI CPU")
        print("-" * 80)
        section = "[8] LOAD AVERAGE ȘI CPU\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("top -bn1 | head -20")
        section += stdout + "\n"
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 9. Verificare procese care consumă resurse
        print("\n[9] PROCESE CU CONSUM RIDICAT")
        print("-" * 80)
        section = "[9] PROCESE CU CONSUM RIDICAT\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%mem | head -10")
        section += "Top 10 procese după memorie:\n" + stdout + "\n"
        print("Top 10 procese după memorie:")
        print(stdout)
        
        stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -10")
        section += "\nTop 10 procese după CPU:\n" + stdout + "\n"
        print("Top 10 procese după CPU:")
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 10. Verificare cron jobs
        print("\n[10] TASK-URI PROGRAMATE (cron)")
        print("-" * 80)
        section = "[10] TASK-URI PROGRAMATE (cron)\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("crontab -l 2>/dev/null || echo 'Nu există cron jobs pentru root'")
        section += stdout + "\n"
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 11. Verificare rețea
        print("\n[11] CONEXIUNI REȚEA ACTIVE")
        print("-" * 80)
        section = "[11] CONEXIUNI REȚEA ACTIVE\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("netstat -tn | head -20 || ss -tn | head -20")
        section += stdout + "\n"
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
        
        all_results.append(section + "\n")
        
        # 12. Verificare fișiere log Aleph
        print("\n[12] CĂUTARE FIȘIERE LOG ALEPH")
        print("-" * 80)
        section = "[12] CĂUTARE FIȘIERE LOG ALEPH\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("find /var/log /opt /usr/local /home -name '*aleph*' -type f 2>/dev/null | head -20")
        if stdout.strip():
            section += "Fișiere găsite:\n" + stdout + "\n"
            print("Fișiere găsite:")
            print(stdout)
            # Verificare ultimele linii din logurile Aleph
            for line in stdout.strip().split('\n')[:3]:
                if line.strip():
                    log_stdout, _, _ = execute_ssh_command(f"tail -20 '{line}' 2>/dev/null")
                    section += f"\nUltimele 20 linii din {line}:\n{log_stdout}\n"
        else:
            section += "Nu s-au gasit fisiere log Aleph\n"
            print("Nu s-au găsit fișiere log Aleph")
        
        all_results.append(section + "\n")
        
        # 13. Verificare reboot-uri
        print("\n[13] ISTORIC REBOOT-URI")
        print("-" * 80)
        section = "[13] ISTORIC REBOOT-URI\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("last reboot | head -10")
        section += stdout + "\n"
        print(stdout)
        
        stdout, stderr, _ = execute_ssh_command("who -b")
        section += "\nUltimul boot:\n" + stdout + "\n"
        print("Ultimul boot:")
        print(stdout)
        
        all_results.append(section + "\n")
        
        # 14. Verificare swap
        print("\n[14] STATUS SWAP")
        print("-" * 80)
        section = "[14] STATUS SWAP\n" + "-" * 80 + "\n"
        
        stdout, stderr, _ = execute_ssh_command("swapon --show")
        section += stdout + "\n"
        print(stdout)
        
        stdout, stderr, _ = execute_ssh_command("cat /proc/swaps")
        section += stdout + "\n"
        print(stdout)
        
        all_results.append(section + "\n")
        
    else:
        error_msg = f"SSH Connection: ESEC\nEroare: {stderr}\nReturn code: {returncode}\n"
        print(f"✗ Conexiune SSH eșuată: {stderr}")
        all_results.append(error_msg + "\n")
    
    # Salvare rezultate
    full_report = "".join(all_results)
    report_file = save_result("raport_verificare.txt", full_report)
    print(f"\n{'=' * 80}")
    print(f"Raport salvat în: {report_file}")
    print(f"[{datetime.now()}] Verificare completă!")

if __name__ == "__main__":
    main()

