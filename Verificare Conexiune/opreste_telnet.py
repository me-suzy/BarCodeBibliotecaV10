#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script Python pentru a opri procesul telnet zombie pe serverul Linux
"""

import subprocess
import sys

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
        print("EROARE: plink.exe nu este disponibil!")
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

def main():
    print("=" * 80)
    print("OPRIRE PROCES TELNET ZOMBIE (PID 1682)")
    print("Server: " + SERVER_IP)
    print("=" * 80)
    print()
    
    # Verifica daca procesul exista
    print("[1] Verifica procesul telnet (PID 1682)...")
    stdout, stderr, _ = execute_ssh_command("ps aux | grep 1682 | grep -v grep")
    if stdout.strip() and "1682" in stdout:
        print("PROCES GASIT:")
        print(stdout)
        print()
        
        # Opreste procesul
        print("[2] Opreste procesul...")
        stdout, stderr, _ = execute_ssh_command("kill -9 1682")
        if stderr and "kill" not in stderr.lower():
            print(f"Eroare: {stderr}")
        else:
            print("Comanda kill executata.")
        print()
        
        # Verifica daca s-a oprit
        print("[3] Verifica daca s-a oprit...")
        time.sleep(2)
        stdout, stderr, _ = execute_ssh_command("ps aux | grep 1682 | grep -v grep")
        if stdout.strip() and "1682" in stdout:
            print("ATENTIE: Procesul inca ruleaza!")
            print(stdout)
        else:
            print("SUCCES: Procesul a fost oprit!")
        print()
    else:
        print("Procesul nu exista (poate a fost deja oprit)")
        print()
    
    # Verifica toate procesele telnet
    print("[4] Verifica toate procesele telnet...")
    stdout, stderr, _ = execute_ssh_command("ps aux | grep telnet | grep -v grep")
    if stdout.strip():
        print(stdout)
    else:
        print("Nu exista procese telnet")
    print()
    
    # Top procese dupa CPU
    print("[5] Top procese dupa CPU (dupa oprirea telnet):")
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -11")
    if stdout:
        print(stdout)
    print()
    
    print("=" * 80)
    print("GATA!")
    print("=" * 80)

if __name__ == "__main__":
    import time
    main()

