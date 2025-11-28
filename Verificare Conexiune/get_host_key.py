#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru a obtine si accepta host key-ul real de la server
"""

import subprocess
import winreg
import base64
import hashlib

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22

def get_host_key_from_server():
    """Obtine host key-ul real de la server folosind ssh-keyscan sau plink"""
    # Metoda 1: Folosim fingerprint-ul SHA256 cunoscut pentru a genera o cheie temporara
    # In productie, ar trebui sa folosim ssh-keyscan sau sa acceptam interactiv
    
    # Pentru testare, folosim o cheie dummy care va fi inlocuita la prima conexiune interactiva
    print("Pentru a accepta host key-ul real, conecteaza-te interactiv o data:")
    print(f'  plink -ssh -P {SSH_PORT} -l root -pw "YOUR-SERVER-PASSWORD" {SERVER_IP}')
    print("Apasa Y cand te intreaba daca vrei sa continui")
    print("\nSAU ruleaza:")
    print(f'  "C:\\Program Files\\PuTTY\\plink.exe" -ssh -P {SSH_PORT} -l root -pw "YOUR-SERVER-PASSWORD" {SERVER_IP} "echo test"')
    print("Si accepta host key-ul cand apare prompt-ul")

if __name__ == "__main__":
    get_host_key_from_server()

