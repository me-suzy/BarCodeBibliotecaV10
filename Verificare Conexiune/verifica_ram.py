#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru verificare detaliată RAM și compatibilitate upgrade
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
    print("VERIFICARE RAM SI COMPATIBILITATE UPGRADE")
    print("=" * 80)
    print()
    
    # 1. Memorie actuală
    print("[1] MEMORIE ACTUALĂ")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("free -m")
    print(stdout)
    print()
    
    # 2. Informații memorie (dmidecode)
    print("[2] DETALII MEMORIE (dmidecode)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmidecode -t 17 2>/dev/null | grep -E 'Size|Speed|Type|Locator|Manufacturer|Part Number|Serial Number' | head -40")
    if stdout.strip():
        print(stdout)
    else:
        print("dmidecode nu este disponibil sau nu are permisiuni")
    print()
    
    # 3. Sloturi memorie
    print("[3] SLOTURI MEMORIE")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmidecode -t 16 2>/dev/null | grep -E 'Number Of Devices|Maximum Capacity|Error Information Handle'")
    if stdout.strip():
        print(stdout)
    else:
        print("Informații sloturi nu disponibile")
    print()
    
    # 4. Motherboard
    print("[4] MOTHERBOARD")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmidecode -t 2 2>/dev/null | grep -E 'Manufacturer|Product Name|Version'")
    if stdout.strip():
        print(stdout)
    else:
        print("Informații motherboard nu disponibile")
    print()
    
    # 5. Procesor
    print("[5] PROCESOR")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("cat /proc/cpuinfo | grep -E 'model name|processor' | head -3")
    print(stdout)
    print()
    
    # 6. Sistem
    print("[6] SISTEM")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("uname -a")
    print(stdout)
    print()
    
    # 7. Memorie instalată (alternativ)
    print("[7] MEMORIE INSTALATĂ (alternativ)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("cat /proc/meminfo | grep -i 'memtotal\|memfree\|memavailable'")
    print(stdout)
    print()
    
    # 8. Sloturi ocupate/libere
    print("[8] SLOTURI MEMORIE (detaliat)")
    print("-" * 80)
    stdout, stderr, _ = execute_ssh_command("dmidecode -t 17 2>/dev/null")
    if stdout.strip():
        # Analizează output-ul
        lines = stdout.split('\n')
        slot_count = 0
        installed_count = 0
        for line in lines:
            if 'Size:' in line:
                slot_count += 1
                if 'No Module Installed' not in line and '0' not in line.split(':')[1].strip().split()[0]:
                    installed_count += 1
        print(f"Sloturi detectate: {slot_count}")
        print(f"Sloturi ocupate: {installed_count}")
        print(f"Sloturi libere: {slot_count - installed_count}")
        print("\nDetalii complete:")
        print(stdout[:2000] + "..." if len(stdout) > 2000 else stdout)
    else:
        print("Informații detaliate nu disponibile")
    print()
    
    print("=" * 80)
    print("VERIFICARE COMPLETĂ!")
    print("=" * 80)
    print()
    print("Analizează output-ul de mai sus pentru a determina:")
    print("1. Tipul de memorie (DDR2, DDR3, DDR4)")
    print("2. Numărul de sloturi disponibile")
    print("3. Capacitatea maximă suportată")
    print("4. Compatibilitatea cu plăci de 16 GB sau mai mari")

if __name__ == "__main__":
    main()

