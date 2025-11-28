#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Verificare resurse si procese CAND Aleph se opreste
Ruleaza acest script imediat dupa ce Aleph se opreste
"""

import subprocess
import time
from datetime import datetime

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"

def get_plink_path():
    paths = [
        r"C:\Program Files\PuTTY\plink.exe",
        r"C:\Program Files (x86)\PuTTY\plink.exe",
        "plink",
    ]
    for path in paths:
        try:
            subprocess.run([path, "-V"], capture_output=True, timeout=5)
            return path
        except:
            continue
    return None

def execute_ssh_command(command):
    plink_exe = get_plink_path()
    if not plink_exe:
        return "", "plink nu este disponibil", 1
    
    try:
        cmd = [
            plink_exe,
            "-ssh",
            "-P", str(SSH_PORT),
            "-l", SSH_USER,
            "-pw", SSH_PASS,
            "-batch",
            SERVER_IP,
            command
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30, encoding='utf-8', errors='replace')
        return result.stdout, result.stderr, result.returncode
    except Exception as e:
        return "", str(e), 1

def check_aleph():
    """Verifica daca Aleph este accesibil"""
    import urllib.request
    import urllib.error
    try:
        req = urllib.request.Request(f"http://{SERVER_IP}:8991/F")
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=10) as response:
            return True, response.getcode()
    except:
        return False, 0

def main():
    print("=" * 80)
    print("VERIFICARE RESURSE CAND ALEPH SE OPRESTE")
    print("=" * 80)
    print()
    print("Asteapta pana cand Aleph se opreste, apoi apasa Enter...")
    input()
    
    # Verifica daca Aleph este oprit
    is_up, status = check_aleph()
    if is_up:
        print("ATENTIE: Aleph pare sa fie activ acum!")
        print("Asteapta pana cand se opreste, apoi ruleaza din nou scriptul.")
        return
    
    print(f"[{datetime.now()}] Aleph este OPrit - verificare resurse...")
    print()
    
    # 1. Uptime si load
    print("[1] UPTIME SI LOAD AVERAGE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("uptime")
    print(stdout)
    print()
    
    # 2. Memorie
    print("[2] MEMORIE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("free -m")
    print(stdout)
    print()
    
    # 3. Procese Aleph
    print("[3] PROCESE ALEPH")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep | wc -l")
    num_procese = stdout.strip()
    print(f"Numar procese Aleph: {num_procese}")
    
    stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep | head -10")
    if stdout.strip():
        print("Primele 10 procese Aleph:")
        print(stdout)
    print()
    
    # 4. Port 8991
    print("[4] PORT 8991")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep 8991 || ss -tuln | grep 8991")
    if stdout.strip():
        print(stdout)
    else:
        print("PORTUL 8991 NU ESTE IN ASCULTARE!")
    print()
    
    # 5. Top procese CPU
    print("[5] TOP 15 PROCESE DUPA CPU")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -16")
    print(stdout)
    print()
    
    # 6. Top procese memorie
    print("[6] TOP 15 PROCESE DUPA MEMORIE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%mem | head -16")
    print(stdout)
    print()
    
    # 7. Procese telnet
    print("[7] PROCESE TELNET")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux | grep telnet | grep -v grep")
    if stdout.strip():
        print("ATENTIE: Exista procese telnet!")
        print(stdout)
    else:
        print("Nu exista procese telnet")
    print()
    
    # 8. Erori recente
    print("[8] ERORI RECENTE IN LOGURI (ultimele 20)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("journalctl -p err -n 20 --no-pager 2>/dev/null || dmesg | tail -30 | grep -i 'error\\|fail\\|oom\\|kill' || echo 'Nu s-au gasit erori recente'")
    print(stdout[:1500] + "..." if len(stdout) > 1500 else stdout)
    print()
    
    # 9. OOM kills
    print("[9] OOM KILLS (Out of Memory)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmesg | grep -i oom | tail -10 || journalctl | grep -i oom | tail -10 || echo 'Nu s-au gasit OOM kills'")
    print(stdout)
    print()
    
    # 10. Loguri Aleph (daca exista)
    print("[10] LOGURI ALEPH (daca exista)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("find /var/log /opt /usr/local /exlibris -name '*aleph*' -type f 2>/dev/null | head -5")
    if stdout.strip():
        print("Fisiere log Aleph gasite:")
        print(stdout)
        # Citeste ultimele linii din primul log
        first_log = stdout.strip().split('\n')[0]
        if first_log:
            stdout2, _, _ = execute_ssh_command(f"tail -30 '{first_log}' 2>/dev/null")
            print(f"\nUltimele 30 linii din {first_log}:")
            print(stdout2[:1000] + "..." if len(stdout2) > 1000 else stdout2)
    else:
        print("Nu s-au gasit fisiere log Aleph")
    print()
    
    print("=" * 80)
    print("VERIFICARE COMPLETA!")
    print("=" * 80)

if __name__ == "__main__":
    main()

