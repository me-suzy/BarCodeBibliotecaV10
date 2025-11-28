#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Verificare proces telnet pe serverul Linux
"""

import subprocess
import os

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

def main():
    print("=" * 80)
    print("VERIFICARE PROCES TELNET PE SERVERUL LINUX")
    print(f"Server: {SERVER_IP}")
    print("=" * 80)
    print()
    
    print("[1] Cauta procesul telnet (PID 1682)...")
    stdout, stderr, _ = execute_ssh_command("ps aux | grep 1682 | grep -v grep")
    if stdout.strip():
        print("PROCES GASIT PE SERVERUL LINUX:")
        print(stdout)
    else:
        print("Procesul nu exista (poate a fost deja oprit)")
    print()
    
    print("[2] Toate procesele telnet pe serverul Linux:")
    stdout, stderr, _ = execute_ssh_command("ps aux | grep telnet | grep -v grep")
    if stdout.strip():
        print(stdout)
    else:
        print("Nu exista procese telnet pe server")
    print()
    
    print("[3] Detalii proces PID 1682 (daca exista):")
    stdout, stderr, _ = execute_ssh_command("ps -fp 1682 2>/dev/null || echo 'Procesul nu exista'")
    print(stdout)
    print()
    
    print("[4] Verifica cine a pornit procesul:")
    stdout, stderr, _ = execute_ssh_command("ps aux | grep 1682 | grep -v grep | awk '{print $1, $2, $9, $10, $11}'")
    if stdout.strip():
        print("USER PID START_TIME CPU% COMMAND")
        print(stdout)
    print()
    
    print("[5] Top procese dupa CPU:")
    stdout, stderr, _ = execute_ssh_command("ps aux --sort=-%cpu | head -6")
    print(stdout)

if __name__ == "__main__":
    main()

