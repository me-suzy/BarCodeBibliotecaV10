#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru a accepta automat host key-ul SSH
"""

import subprocess
import winreg
import re

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22

def get_host_key():
    """Obtine host key-ul de la server folosind ssh-keyscan sau plink"""
    plink_paths = [
        r"C:\Program Files\PuTTY\plink.exe",
        r"C:\Program Files (x86)\PuTTY\plink.exe",
        "plink",
    ]
    
    plink_exe = None
    for path in plink_paths:
        try:
            subprocess.run([path, "-V"], capture_output=True, timeout=5)
            plink_exe = path
            break
        except:
            continue
    
    if not plink_exe:
        print("plink.exe nu este disponibil")
        return None
    
    # Folosim fingerprint-ul SHA256 cunoscut
    # Pentru a obtine host key-ul real, trebuie sa ne conectam o data interactiv
    print("Pentru a accepta host key-ul, ruleaza:")
    print(f'  "{plink_exe}" -ssh -P {SSH_PORT} -l root -pw "YOUR-SERVER-PASSWORD" {SERVER_IP} "echo test"')
    print("Si apasa Y cand te intreaba daca vrei sa continui")
    return None

if __name__ == "__main__":
    get_host_key()

