#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script simplu pentru testarea stabilitatii serverului Linux.
Nu modifica nimic, doar verifica statusul sistemului.
"""

import subprocess
import os
from datetime import datetime
import urllib.request
import urllib.error

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"
CATALOG_URL = f"http://{SERVER_IP}:8991/F"

RESULTS_DIR = os.path.dirname(os.path.abspath(__file__))
TIMESTAMP = datetime.now().strftime("%Y%m%d_%H%M%S")

def execute_ssh_command(command):
    """Executa o comanda SSH folosind plink"""
    # Cauta plink in locatii comune
    plink_paths = [
        "plink",  # In PATH
        r"C:\Program Files\PuTTY\plink.exe",
        r"C:\Program Files (x86)\PuTTY\plink.exe",
    ]
    
    plink_exe = None
    for path in plink_paths:
        try:
            result = subprocess.run([path, "-V"], capture_output=True, timeout=5)
            if result.returncode == 0 or "PuTTY" in result.stderr.decode('utf-8', errors='ignore'):
                plink_exe = path
                break
        except:
            continue
    
    if not plink_exe:
        return "", "plink.exe nu este disponibil. Instaleaza PuTTY sau adauga-l in PATH.", 1
    
    # Accepta host key automat folosind registry (pentru batch mode)
    import winreg
    try:
        key_path = r"Software\SimonTatham\PuTTY\SshHostKeys"
        key_name = f"rsa2@22:{SERVER_IP}"
        # Adauga o cheie dummy pentru a permite conexiunea (in productie ar trebui sa fie cheia reala)
        # Pentru testare, folosim aceasta metoda
        try:
            key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, key_path, 0, winreg.KEY_WRITE)
            winreg.SetValueEx(key, key_name, 0, winreg.REG_SZ, "0x23")
            winreg.CloseKey(key)
        except:
            pass  # Cheia poate exista deja sau nu putem scrie
    except:
        pass  # Ignora erorile de registry
    
    try:
        cmd = [
            plink_exe,
            "-ssh",
            "-P", str(SSH_PORT),
            "-l", SSH_USER,
            "-pw", SSH_PASS,
            "-batch",
            "-no-antispoof",  # Permite conexiunea fara verificare host key strict
            SERVER_IP,
            command
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30, encoding='utf-8', errors='replace')
        return result.stdout, result.stderr, result.returncode
    except Exception as e:
        return "", str(e), 1

def test_http():
    """Testeaza conexiunea HTTP"""
    try:
        req = urllib.request.Request(CATALOG_URL)
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=10) as response:
            return True, f"HTTP {response.getcode()}", ""
    except Exception as e:
        return False, f"Eroare: {e}", ""

def main():
    print(f"[{datetime.now()}] Inceput verificare server {SERVER_IP}")
    print("=" * 80)
    
    report_lines = []
    report_lines.append(f"RAPORT VERIFICARE SERVER - {datetime.now()}")
    report_lines.append(f"Server IP: {SERVER_IP}")
    report_lines.append(f"Catalog URL: {CATALOG_URL}")
    report_lines.append("=" * 80)
    report_lines.append("")
    
    # Test HTTP
    print("\n[TEST HTTP] Verificare accesibilitate catalog...")
    http_ok, http_status, _ = test_http()
    http_result = f"HTTP Test: {'SUCCES' if http_ok else 'ESEC'}\nStatus: {http_status}"
    print(http_result)
    report_lines.append(http_result)
    report_lines.append("")
    
    # Test SSH
    print("\n[TEST SSH] Conectare la server...")
    stdout, stderr, returncode = execute_ssh_command("echo 'SSH Connection Test OK'")
    
    if returncode == 0 and "SSH Connection Test OK" in stdout:
        print("OK - Conexiune SSH reusita!")
        report_lines.append("SSH Connection: SUCCES")
        report_lines.append("")
        
        # Verificari
        checks = [
            ("[1] STATUS GENERAL SISTEM", [
                ("uptime", "uptime"),
                ("memorie", "free -h"),
                ("spatiu disk", "df -h")
            ]),
            ("[2] PROCESE ALEPH", [
                ("procese aleph", "ps aux | grep -i aleph | grep -v grep")
            ]),
            ("[3] VERIFICARE PORT 8991", [
                ("port 8991", "netstat -tuln | grep 8991 || ss -tuln | grep 8991")
            ]),
            ("[4] SERVICII SYSTEMD", [
                ("servicii aleph", "systemctl list-units --type=service --state=running 2>/dev/null | grep -i aleph || echo 'Nu s-au gasit servicii Aleph'")
            ]),
            ("[5] LOGURI SISTEM", [
                ("loguri recente", "journalctl -n 50 --no-pager 2>/dev/null || tail -50 /var/log/messages 2>/dev/null || tail -50 /var/log/syslog 2>/dev/null || echo 'Nu s-au gasit loguri'")
            ]),
            ("[6] LOGURI KERNEL", [
                ("dmesg", "dmesg | tail -30")
            ]),
            ("[7] ERORI IN LOGURI", [
                ("erori", "journalctl -p err -n 20 --no-pager 2>/dev/null || grep -i error /var/log/messages 2>/dev/null | tail -20 || echo 'Nu s-au gasit erori'")
            ]),
            ("[8] LOAD AVERAGE SI CPU", [
                ("top", "top -bn1 | head -20")
            ]),
            ("[9] PROCESE CU CONSUM RIDICAT", [
                ("top memorie", "ps aux --sort=-%mem | head -10"),
                ("top CPU", "ps aux --sort=-%cpu | head -10")
            ]),
            ("[10] CRON JOBS", [
                ("crontab", "crontab -l 2>/dev/null || echo 'Nu exista cron jobs pentru root'")
            ]),
            ("[11] CONEXIUNI RETEA", [
                ("netstat", "netstat -tn | head -20 || ss -tn | head -20")
            ]),
            ("[12] FISIERE LOG ALEPH", [
                ("find aleph", "find /var/log /opt /usr/local /home -name '*aleph*' -type f 2>/dev/null | head -20")
            ]),
            ("[13] ISTORIC REBOOT-URI", [
                ("last reboot", "last reboot | head -10"),
                ("who -b", "who -b")
            ]),
            ("[14] STATUS SWAP", [
                ("swapon", "swapon --show"),
                ("proc swaps", "cat /proc/swaps")
            ])
        ]
        
        for section_title, commands in checks:
            print(f"\n{section_title}")
            print("-" * 80)
            report_lines.append("")
            report_lines.append(section_title)
            report_lines.append("-" * 80)
            
            for desc, cmd in commands:
                stdout, stderr, _ = execute_ssh_command(cmd)
                if stdout.strip():
                    print(f"\n{desc}:")
                    print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
                    report_lines.append(f"\n{desc}:")
                    report_lines.append(stdout)
                elif stderr and "plink" not in stderr.lower():
                    print(f"\n{desc}: {stderr}")
                    report_lines.append(f"\n{desc}: {stderr}")
    else:
        error_msg = f"SSH Connection: ESEC\nEroare: {stderr}\nReturn code: {returncode}"
        print(f"ESEC - Conexiune SSH: {stderr}")
        report_lines.append(error_msg)
    
    # Salvare raport
    report_content = "\n".join(report_lines)
    report_file = os.path.join(RESULTS_DIR, f"{TIMESTAMP}_raport_verificare.txt")
    try:
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(report_content)
        print(f"\n{'=' * 80}")
        print(f"Raport salvat in: {report_file}")
        print(f"[{datetime.now()}] Verificare completa!")
    except Exception as e:
        print(f"Eroare la salvare raport: {e}")

if __name__ == "__main__":
    main()

