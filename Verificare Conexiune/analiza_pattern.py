#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru analiza pattern-ului de intreruperi Aleph
Verifica resursele si procesele in timpul zilei vs seara
"""

import subprocess
import os
from datetime import datetime

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"

def get_plink_path():
    """Gaseste calea catre plink"""
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
    """Executa o comanda SSH"""
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

def analyze_resources():
    """Analizeaza resursele serverului"""
    print("=" * 80)
    print("ANALIZA RESURSE SERVER - Pattern intreruperi Aleph")
    print("=" * 80)
    print()
    
    current_time = datetime.now()
    hour = current_time.hour
    is_daytime = hour < 17 or (hour == 17 and current_time.minute < 30)
    
    print(f"Ora curenta: {current_time.strftime('%H:%M:%S')}")
    print(f"Perioada: {'ZIUA (inainte de 17:30)' if is_daytime else 'SEARA (dupa 17:30)'}")
    print()
    
    # 1. Uptime si load average
    print("[1] UPTIME SI LOAD AVERAGE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("uptime")
    if stdout:
        print(stdout)
        # Analiza load average
        if "load average" in stdout.lower():
            parts = stdout.split("load average:")[-1].strip().split(",")
            if len(parts) >= 3:
                load_1min = float(parts[0].strip())
                load_5min = float(parts[1].strip())
                load_15min = float(parts[2].strip())
                print(f"  Load 1 min: {load_1min}")
                print(f"  Load 5 min: {load_5min}")
                print(f"  Load 15 min: {load_15min}")
                if load_1min > 2.0:
                    print("  ATENTIE: Load average ridicat!")
    else:
        print(f"Eroare: {stderr}")
    print()
    
    # 2. Memorie
    print("[2] MEMORIE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("free -m")
    if stdout:
        print(stdout)
        # Analiza memorie
        lines = stdout.split('\n')
        if len(lines) >= 2:
            mem_line = lines[1].split()
            if len(mem_line) >= 4:
                total = mem_line[1]
                used = mem_line[2]
                available = mem_line[6] if len(mem_line) > 6 else mem_line[3]
                print(f"  Total: {total}, Folosita: {used}, Disponibila: {available}")
    else:
        print(f"Eroare: {stderr}")
    print()
    
    # 3. Procese Aleph
    print("[3] PROCESE ALEPH")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep")
    if stdout and stdout.strip():
        print(stdout)
        # Analiza procese
        lines = stdout.strip().split('\n')
        print(f"  Număr procese Aleph: {len(lines)}")
        for line in lines:
            parts = line.split()
            if len(parts) >= 11:
                cpu = parts[2]
                mem = parts[3]
                print(f"    CPU: {cpu}%, Memorie: {mem}%")
    else:
        print("  NU SUNT PROCESE ALEPH ACTIVE!")
    print()
    
    # 4. Port 8991
    print("[4] PORT 8991")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep 8991 || ss -tuln | grep 8991")
    if stdout and stdout.strip():
        print(stdout)
    else:
        print("  PORTUL 8991 NU ESTE IN ASCULTARE!")
    print()
    
    # 5. Top procese dupa CPU
    print("[5] TOP 10 PROCESE DUPA CPU")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -11")
    if stdout:
        print(stdout)
    print()
    
    # 6. Top procese dupa memorie
    print("[6] TOP 10 PROCESE DUPA MEMORIE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%mem | head -11")
    if stdout:
        print(stdout)
    print()
    
    # 7. Cron jobs (task-uri programate)
    print("[7] CRON JOBS (Task-uri programate)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("crontab -l 2>/dev/null || echo 'Nu exista cron jobs pentru root'")
    if stdout:
        print(stdout)
        if "cron" in stdout.lower() or "*" in stdout:
            print("  ATENTIE: Exista cron jobs care pot rula in timpul zilei!")
    print()
    
    # 8. Erori recente in loguri
    print("[8] ERORI RECENTE IN LOGURI")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("journalctl -p err -n 10 --no-pager 2>/dev/null || dmesg | tail -20 | grep -i 'error\\|fail\\|oom\\|kill' || echo 'Nu s-au gasit erori recente'")
    if stdout:
        print(stdout[:1000] + "..." if len(stdout) > 1000 else stdout)
    print()
    
    # 9. Istoric reboot-uri
    print("[9] ISTORIC REBOOT-URI (ultimele 10)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("last reboot | head -10")
    if stdout:
        print(stdout)
    print()
    
    # 10. Recomandari
    print("=" * 80)
    print("RECOMANDARI:")
    print("=" * 80)
    print("1. Ruleaza monitor_aleph.py pentru a monitoriza continuu Aleph")
    print("2. Verifica logurile cand Aleph se opreste")
    print("3. Verifica daca exista cron jobs care ruleaza in timpul zilei")
    print("4. Verifica daca exista procese care consuma resurse excesive")
    print("5. Verifica daca exista probleme de memorie (OOM)")
    print()

if __name__ == "__main__":
    analyze_resources()

