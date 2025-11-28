#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Test direct HTTP pentru a verifica de ce portul 80 nu functioneaza.
"""

import socket
import urllib.request
import urllib.error
from datetime import datetime

SERVER_IP = "YOUR-IP-or-http://WEBSITE"

def test_port(host, port, timeout=5):
    """Testeaza daca un port este deschis"""
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((host, port))
        sock.close()
        return result == 0
    except Exception as e:
        return False

def test_http(url, timeout=10):
    """Testeaza conexiunea HTTP"""
    try:
        req = urllib.request.Request(url)
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=timeout) as response:
            return True, f"HTTP {response.getcode()}", response.read()[:200].decode('utf-8', errors='ignore')
    except urllib.error.URLError as e:
        return False, f"URLError: {e}", ""
    except Exception as e:
        return False, f"Eroare: {e}", ""

def main():
    print(f"[{datetime.now()}] Test direct porturi si HTTP")
    print("=" * 80)
    
    # Test porturi
    print("\n[TEST PORTURI] Verificare porturi deschise...")
    ports = [22, 80, 443, 8991, 8080, 8000]
    for port in ports:
        is_open = test_port(SERVER_IP, port)
        status = "DESCHIS" if is_open else "INCHIS"
        print(f"Port {port}: {status}")
    
    # Test HTTP port 80 (implicit)
    print("\n[TEST HTTP PORT 80] http://" + SERVER_IP + "/")
    http_ok, status, content = test_http(f"http://{SERVER_IP}/")
    print(f"Rezultat: {'SUCCES' if http_ok else 'ESEC'}")
    print(f"Status: {status}")
    
    # Test HTTP port 8991 (Aleph)
    print("\n[TEST HTTP PORT 8991] http://" + SERVER_IP + ":8991/F/")
    http_ok, status, content = test_http(f"http://{SERVER_IP}:8991/F/")
    print(f"Rezultat: {'SUCCES' if http_ok else 'ESEC'}")
    print(f"Status: {status}")
    if content:
        print(f"Continut (primele 200 caractere): {content[:200]}")
    
    print("\n" + "=" * 80)
    print("DIAGNOSTIC:")
    print("=" * 80)
    
    port_80_open = test_port(SERVER_IP, 80)
    port_8991_open = test_port(SERVER_IP, 8991)
    
    if not port_80_open:
        print("[OK] Portul 80 este INCHIS - de aceea YOUR-IP-or-http://WEBSITE/ nu functioneaza")
        print("  Cauza: Nu exista niciun serviciu web (Apache/Nginx) care sa asculte pe portul 80")
        print("  Solutie: Nu este necesar sa functioneze portul 80 daca folosesti doar Aleph pe 8991")
    else:
        print("[EROARE] Portul 80 este DESCHIS dar conexiunea esueaza - problema de configurare serviciu web")
    
    if port_8991_open:
        print("[OK] Portul 8991 este DESCHIS - Aleph functioneaza")
    else:
        print("[EROARE] Portul 8991 este INCHIS - Aleph nu functioneaza acum")

if __name__ == "__main__":
    main()

