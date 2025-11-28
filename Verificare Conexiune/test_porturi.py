#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pentru verificarea porturilor deschise pe server.
Verifica de ce portul 80 nu functioneaza dar 8991 da.
"""

import subprocess
import socket
import os
from datetime import datetime

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"

RESULTS_DIR = os.path.dirname(os.path.abspath(__file__))
TIMESTAMP = datetime.now().strftime("%Y%m%d_%H%M%S")

def execute_ssh_command(command):
    """Executa o comanda SSH folosind plink"""
    try:
        cmd = [
            "plink",
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
    except FileNotFoundError:
        return "", "plink.exe nu este disponibil", 1
    except Exception as e:
        return "", str(e), 1

def test_port(host, port, timeout=5):
    """Testeaza daca un port este deschis"""
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((host, port))
        sock.close()
        return result == 0
    except Exception as e:
        return False, str(e)

def main():
    print(f"[{datetime.now()}] Verificare porturi server {SERVER_IP}")
    print("=" * 80)
    
    report_lines = []
    report_lines.append(f"RAPORT VERIFICARE PORTURI - {datetime.now()}")
    report_lines.append(f"Server IP: {SERVER_IP}")
    report_lines.append("=" * 80)
    report_lines.append("")
    
    # Test porturi local (din Windows)
    print("\n[TEST LOCAL] Verificare porturi din Windows...")
    report_lines.append("[TEST LOCAL] Verificare porturi din Windows")
    report_lines.append("-" * 80)
    
    ports_to_test = [22, 80, 443, 8991, 8080, 8000]
    for port in ports_to_test:
        is_open, error = test_port(SERVER_IP, port)
        status = "DESCHIS" if is_open else "INCHIS"
        result = f"Port {port}: {status}"
        if error and not is_open:
            result += f" ({error})"
        print(result)
        report_lines.append(result)
    
    report_lines.append("")
    
    # Test SSH
    print("\n[TEST SSH] Conectare la server...")
    stdout, stderr, returncode = execute_ssh_command("echo 'SSH OK'")
    
    if returncode == 0:
        print("OK - Conexiune SSH reusita!")
        report_lines.append("SSH Connection: SUCCES")
        report_lines.append("")
        
        # Verificare porturi pe server
        print("\n[VERIFICARE SERVER] Porturi in ascultare pe server...")
        report_lines.append("[VERIFICARE SERVER] Porturi in ascultare pe server")
        report_lines.append("-" * 80)
        
        # netstat sau ss
        stdout, stderr, _ = execute_ssh_command("netstat -tuln 2>/dev/null || ss -tuln")
        if stdout:
            print("Porturi in ascultare:")
            print(stdout)
            report_lines.append(stdout)
        
        report_lines.append("")
        
        # Verificare specifica port 80
        print("\n[VERIFICARE PORT 80] De ce nu functioneaza portul 80?")
        report_lines.append("[VERIFICARE PORT 80] De ce nu functioneaza portul 80?")
        report_lines.append("-" * 80)
        
        # Verifica daca exista serviciu web
        stdout, stderr, _ = execute_ssh_command("systemctl status apache2 2>/dev/null || systemctl status httpd 2>/dev/null || systemctl status nginx 2>/dev/null || echo 'Nu s-a gasit serviciu web'")
        report_lines.append("Status servicii web:")
        report_lines.append(stdout)
        print(stdout)
        
        # Verifica procese care asculta pe port 80
        stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep ':80 ' || ss -tuln | grep ':80 ' || lsof -i :80 2>/dev/null || echo 'Nimic nu asculta pe port 80'")
        report_lines.append("\nProcese pe port 80:")
        report_lines.append(stdout)
        print(stdout)
        
        # Verificare firewall
        print("\n[VERIFICARE FIREWALL] Reguli firewall...")
        report_lines.append("\n[VERIFICARE FIREWALL] Reguli firewall")
        report_lines.append("-" * 80)
        
        stdout, stderr, _ = execute_ssh_command("iptables -L -n 2>/dev/null | head -30 || firewall-cmd --list-all 2>/dev/null || echo 'Firewall nu este configurat sau nu este accesibil'")
        report_lines.append(stdout)
        print(stdout)
        
        # Verificare port 8991 (Aleph)
        print("\n[VERIFICARE PORT 8991] Status Aleph...")
        report_lines.append("\n[VERIFICARE PORT 8991] Status Aleph")
        report_lines.append("-" * 80)
        
        stdout, stderr, _ = execute_ssh_command("netstat -tuln | grep 8991 || ss -tuln | grep 8991")
        report_lines.append("Port 8991:")
        report_lines.append(stdout if stdout else "Portul 8991 nu este in ascultare!")
        print(stdout if stdout else "Portul 8991 nu este in ascultare!")
        
        # Procese Aleph
        stdout, stderr, _ = execute_ssh_command("ps aux | grep -i aleph | grep -v grep")
        report_lines.append("\nProcese Aleph:")
        report_lines.append(stdout if stdout else "Nu s-au gasit procese Aleph!")
        print("\nProcese Aleph:")
        print(stdout if stdout else "Nu s-au gasit procese Aleph!")
        
        # Verificare de ce Aleph se intrerupe
        print("\n[DIAGNOSTIC INTRERUPERI] Cauze posibile pentru intreruperi Aleph...")
        report_lines.append("\n[DIAGNOSTIC INTRERUPERI] Cauze posibile")
        report_lines.append("-" * 80)
        
        # Memorie
        stdout, stderr, _ = execute_ssh_command("free -h")
        report_lines.append("Memorie:")
        report_lines.append(stdout)
        print("Memorie:")
        print(stdout)
        
        # Load average
        stdout, stderr, _ = execute_ssh_command("uptime")
        report_lines.append("\nUptime si load:")
        report_lines.append(stdout)
        print("\nUptime si load:")
        print(stdout)
        
        # Erori recente
        stdout, stderr, _ = execute_ssh_command("dmesg | tail -20 | grep -i 'error\|fail\|oom\|kill' || echo 'Nu s-au gasit erori recente in dmesg'")
        report_lines.append("\nErori recente kernel:")
        report_lines.append(stdout)
        print("\nErori recente kernel:")
        print(stdout)
        
        # Loguri sistem cu erori
        stdout, stderr, _ = execute_ssh_command("journalctl -p err -n 10 --no-pager 2>/dev/null || tail -20 /var/log/messages 2>/dev/null | grep -i error || echo 'Nu s-au gasit erori in loguri'")
        report_lines.append("\nErori in loguri sistem:")
        report_lines.append(stdout)
        print("\nErori in loguri sistem:")
        print(stdout[:500] + "..." if len(stdout) > 500 else stdout)
        
    else:
        error_msg = f"SSH Connection: ESEC\nEroare: {stderr}"
        print(f"ESEC - Conexiune SSH: {stderr}")
        report_lines.append(error_msg)
    
    # Salvare raport
    report_content = "\n".join(report_lines)
    report_file = os.path.join(RESULTS_DIR, f"{TIMESTAMP}_raport_porturi.txt")
    try:
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(report_content)
        print(f"\n{'=' * 80}")
        print(f"Raport salvat in: {report_file}")
    except Exception as e:
        print(f"Eroare la salvare raport: {e}")

if __name__ == "__main__":
    main()

