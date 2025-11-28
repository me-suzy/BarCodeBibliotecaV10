#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de monitorizare automata cu verificare resurse cand Aleph se opreste
Monitorizeaza Aleph si cand se opreste, verifica automat memoria, procese, loguri
"""

import time
import urllib.request
import urllib.error
from datetime import datetime
import os
import subprocess

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
CATALOG_URL = f"http://{SERVER_IP}:8991/F"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"
CHECK_INTERVAL = 30  # secunde

RESULTS_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_FILE = "monitor_aleph.log"
DIAGNOSTIC_DIR = os.path.join(RESULTS_DIR, "diagnostice")

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

def check_aleph():
    """Verifica daca Aleph este accesibil"""
    try:
        req = urllib.request.Request(CATALOG_URL)
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=10) as response:
            return True, response.getcode(), ""
    except urllib.error.URLError as e:
        return False, 0, str(e)
    except Exception as e:
        return False, 0, str(e)

def log_event(message, log_file=LOG_FILE):
    """Inregistreaza un eveniment in log"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_entry = f"[{timestamp}] {message}\n"
    print(log_entry.strip())
    
    log_path = os.path.join(RESULTS_DIR, log_file)
    try:
        with open(log_path, 'a', encoding='utf-8') as f:
            f.write(log_entry)
    except:
        pass

def verifica_resurse_cand_se_opreste():
    """Verifica resursele serverului cand Aleph se opreste"""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    diagnostic_file = os.path.join(DIAGNOSTIC_DIR, f"diagnostic_{timestamp}.txt")
    
    # Creeaza directorul pentru diagnostice
    os.makedirs(DIAGNOSTIC_DIR, exist_ok=True)
    
    log_event(f"ALEPH S-A OPRIT! Incepe verificare automata resurse...")
    
    diagnostic_lines = []
    diagnostic_lines.append("=" * 80)
    diagnostic_lines.append(f"DIAGNOSTIC AUTOMAT - Aleph s-a oprit")
    diagnostic_lines.append(f"Data/Ora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    diagnostic_lines.append("=" * 80)
    diagnostic_lines.append("")
    
    # 1. Uptime si load average
    print("\n[1] Verificare uptime si load average...")
    diagnostic_lines.append("[1] UPTIME SI LOAD AVERAGE")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("uptime")
    if stdout:
        diagnostic_lines.append(stdout)
        print(stdout)
    else:
        diagnostic_lines.append(f"Eroare: {stderr}")
    diagnostic_lines.append("")
    
    # 2. Memorie
    print("[2] Verificare memorie...")
    diagnostic_lines.append("[2] MEMORIE")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("free -m")
    if stdout:
        diagnostic_lines.append(stdout)
        print(stdout)
        # Analiza memorie
        lines = stdout.split('\n')
        if len(lines) >= 2:
            mem_line = lines[1].split()
            if len(mem_line) >= 4:
                total = mem_line[1]
                used = mem_line[2]
                available = mem_line[6] if len(mem_line) > 6 else mem_line[3]
                diagnostic_lines.append(f"\nAnaliza: Total={total}MB, Folosita={used}MB, Disponibila={available}MB")
                if int(available) < 100:
                    diagnostic_lines.append("ATENTIE: Memorie disponibila foarte putina!")
    else:
        diagnostic_lines.append(f"Eroare: {stderr}")
    diagnostic_lines.append("")
    
    # 3. Procese Aleph
    print("[3] Verificare procese Aleph...")
    diagnostic_lines.append("[3] PROCESE ALEPH")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep | wc -l")
    num_procese = stdout.strip()
    diagnostic_lines.append(f"Numar procese Aleph: {num_procese}")
    print(f"Numar procese Aleph: {num_procese}")
    
    stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep | head -10")
    if stdout.strip():
        diagnostic_lines.append("\nPrimele 10 procese Aleph:")
        diagnostic_lines.append(stdout)
    diagnostic_lines.append("")
    
    # 4. Port 8991
    print("[4] Verificare port 8991...")
    diagnostic_lines.append("[4] PORT 8991")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep 8991 || ss -tuln | grep 8991")
    if stdout.strip():
        diagnostic_lines.append(stdout)
    else:
        diagnostic_lines.append("PORTUL 8991 NU ESTE IN ASCULTARE!")
    diagnostic_lines.append("")
    
    # 5. Top procese CPU
    print("[5] Verificare top procese CPU...")
    diagnostic_lines.append("[5] TOP 15 PROCESE DUPA CPU")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -16")
    if stdout:
        diagnostic_lines.append(stdout)
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
    diagnostic_lines.append("")
    
    # 6. Top procese memorie
    print("[6] Verificare top procese memorie...")
    diagnostic_lines.append("[6] TOP 15 PROCESE DUPA MEMORIE")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%mem | head -16")
    if stdout:
        diagnostic_lines.append(stdout)
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
    diagnostic_lines.append("")
    
    # 7. Procese telnet
    print("[7] Verificare procese telnet...")
    diagnostic_lines.append("[7] PROCESE TELNET")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ps aux | grep telnet | grep -v grep")
    if stdout.strip():
        diagnostic_lines.append("ATENTIE: Exista procese telnet!")
        diagnostic_lines.append(stdout)
    else:
        diagnostic_lines.append("Nu exista procese telnet")
    diagnostic_lines.append("")
    
    # 8. OOM kills
    print("[8] Verificare OOM kills...")
    diagnostic_lines.append("[8] OOM KILLS (Out of Memory)")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmesg | grep -i oom | tail -10 || journalctl | grep -i oom | tail -10 || echo 'Nu s-au gasit OOM kills'")
    if stdout:
        diagnostic_lines.append(stdout)
        if "oom" in stdout.lower() or "out of memory" in stdout.lower():
            diagnostic_lines.append("\nATENTIE: S-au gasit OOM kills! Aceasta este probabil cauza!")
            print("ATENTIE: S-au gasit OOM kills!")
    diagnostic_lines.append("")
    
    # 9. Erori recente in loguri
    print("[9] Verificare erori recente...")
    diagnostic_lines.append("[9] ERORI RECENTE IN LOGURI (ultimele 20)")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("journalctl -p err -n 20 --no-pager 2>/dev/null || dmesg | tail -30 | grep -i 'error\\|fail\\|oom\\|kill' || echo 'Nu s-au gasit erori recente'")
    if stdout:
        diagnostic_lines.append(stdout[:2000] + "..." if len(stdout) > 2000 else stdout)
    diagnostic_lines.append("")
    
    # 10. Servicii systemd Aleph
    print("[10] Verificare servicii systemd...")
    diagnostic_lines.append("[10] SERVICII SYSTEMD ALEPH")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("systemctl list-units | grep -i aleph || systemctl status aleph 2>/dev/null | head -20 || echo 'Nu s-au gasit servicii Aleph'")
    if stdout:
        diagnostic_lines.append(stdout)
    diagnostic_lines.append("")
    
    # 11. Cron jobs
    print("[11] Verificare cron jobs...")
    diagnostic_lines.append("[11] CRON JOBS")
    diagnostic_lines.append("-" * 80)
    stdout, stderr, _ = execute_ssh_command("crontab -l 2>/dev/null || echo 'Nu exista cron jobs pentru root'")
    if stdout:
        diagnostic_lines.append(stdout)
    diagnostic_lines.append("")
    
    # Salvare diagnostic
    diagnostic_content = "\n".join(diagnostic_lines)
    try:
        with open(diagnostic_file, 'w', encoding='utf-8') as f:
            f.write(diagnostic_content)
        log_event(f"Diagnostic salvat in: {diagnostic_file}")
        print(f"\nDiagnostic salvat in: {diagnostic_file}")
    except Exception as e:
        log_event(f"Eroare la salvare diagnostic: {e}")
    
    return diagnostic_file

def main():
    print("=" * 80)
    print("MONITORIZARE AUTOMATA ALEPH CU VERIFICARE RESURSE")
    print(f"Server: {CATALOG_URL}")
    print(f"Interval verificare: {CHECK_INTERVAL} secunde")
    print("Cand Aleph se opreste, se verifica automat resursele serverului")
    print("Apasa Ctrl+C pentru a opri")
    print("=" * 80)
    print()
    
    log_event("Monitorizare automata pornita")
    
    consecutive_failures = 0
    last_status = None
    verificare_in_curs = False
    
    try:
        while True:
            is_up, status_code, error = check_aleph()
            current_time = datetime.now()
            hour = current_time.hour
            
            if is_up:
                if last_status == False:
                    # Aleph s-a repornit
                    log_event(f"ALEPH S-A REPORNIT! (dupa {consecutive_failures} esecuri consecutive)")
                    verificare_in_curs = False
                consecutive_failures = 0
                last_status = True
                status_msg = f"OK - HTTP {status_code} (ora {hour}:{current_time.minute:02d})"
            else:
                consecutive_failures += 1
                if last_status == True and not verificare_in_curs:
                    # Aleph tocmai s-a oprit - incepe verificare automata
                    verificare_in_curs = True
                    diagnostic_file = verifica_resurse_cand_se_opreste()
                    log_event(f"ALEPH S-A OPRIT! Eroare: {error}")
                last_status = False
                status_msg = f"ESEC - {error} (ora {hour}:{current_time.minute:02d}) - {consecutive_failures} esecuri consecutive"
            
            print(f"[{current_time.strftime('%H:%M:%S')}] {status_msg}")
            
            if consecutive_failures >= 3:
                log_event(f"ATENTIE: {consecutive_failures} esecuri consecutive - Aleph pare sa fie oprit")
            
            time.sleep(CHECK_INTERVAL)
            
    except KeyboardInterrupt:
        log_event("Monitorizare oprita de utilizator")
        print("\nMonitorizare oprita.")

if __name__ == "__main__":
    main()

