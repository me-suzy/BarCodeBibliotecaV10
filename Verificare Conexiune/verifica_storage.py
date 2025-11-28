#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Verificare storage si posibilitate adaugare HDD pentru hosting web
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
    print("VERIFICARE STORAGE SI POSIBILITATE ADAUGARE HDD")
    print("=" * 80)
    print()
    
    # 1. Partitii si spatiu
    print("[1] PARTITII SI SPATIU DISPONIBIL")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("df -h")
    print(stdout)
    print()
    
    # 2. Discuri detectate
    print("[2] DISCURI DETECTATE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("fdisk -l 2>/dev/null | grep -E '^Disk /dev'")
    print(stdout)
    print()
    
    # 3. Partitii
    print("[3] PARTITII")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("cat /proc/partitions")
    print(stdout)
    print()
    
    # 4. Block devices
    print("[4] BLOCK DEVICES")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("lsblk 2>/dev/null || echo 'lsblk nu este disponibil'")
    print(stdout)
    print()
    
    # 5. Controller storage
    print("[5] CONTROLLER STORAGE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("lspci | grep -i 'sata\|ide\|raid\|storage\|scsi'")
    print(stdout if stdout.strip() else "Nu s-au gasit controller-e specifice")
    print()
    
    # 6. Discuri in sistem
    print("[6] DISCURI IN SISTEM (dmesg)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmesg | grep -iE 'sda|sdb|sdc|hdd|disk|sata|sas' | tail -30")
    print(stdout[:1500] + "..." if len(stdout) > 1500 else stdout)
    print()
    
    # 7. Verificare sloturi disponibile (daca este posibil)
    print("[7] VERIFICARE SLOTURI HDD")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("ls -la /dev/sd* 2>/dev/null | head -10")
    print(stdout)
    print()
    
    # 8. Utilizare Aleph
    print("[8] UTILIZARE ALEPH")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("du -sh /exlibris 2>/dev/null || echo 'Nu s-a gasit /exlibris'")
    print(stdout)
    stdout, stderr, _ = execute_ssh_command("df -h /exlibris 2>/dev/null")
    print(stdout)
    print()
    
    print("=" * 80)
    print("VERIFICARE COMPLETA!")
    print("=" * 80)
    print()
    print("Analizeaza output-ul pentru a determina:")
    print("1. Tipul de discuri (SATA/SAS)")
    print("2. Sloturi disponibile")
    print("3. Capacitatea maxima")
    print("4. Compatibilitatea cu HDD-uri noi")

if __name__ == "__main__":
    main()

