#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase5.py

Afişează detalii din init.dat şi din scripturile stop corespunzătoare
pentru a identifica ce fişiere sunt chemaţi atunci când serviciul Aleph
primeşte comanda de oprire. Nu modifică nimic pe server.
"""

import paramiko
import sys
import getpass

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă-l gol dacă preferi să fie cerut la rulare

COMMANDS = [
    ("primele 150 linii init.dat", "sed -n '1,150p' /exlibris/startup/init.dat"),
    ("liniile 150-300 init.dat", "sed -n '150,300p' /exlibris/startup/init.dat"),
    (
        "rezumat init.dat (modul, owner, start/stop)",
        r"""awk -F: '!/^#/ && NF>=6 {printf "%-3s modul=%-8s owner=%-8s dev=%s start=%s stop=%s\n", NR, $2, $3, $4, $5, $6}' /exlibris/startup/init.dat""",
    ),
    (
        "scripturi stop unice (cale estimată)",
        r"""awk -F: '!/^#/ && $6 {print $4"/"$6}' /exlibris/startup/init.dat | sort -u""",
    ),
    (
        "există fişiere stop? (ls -lah)",
        r"""awk -F: '!/^#/ && $6 {print $4"/"$6}' /exlibris/startup/init.dat | sort -u | while read f ; do if [ -f "$f" ]; then ls -lah "$f" ; else echo "[missing]" "$f" ; fi ; done""",
    ),
    (
        "loguri stop asociate (ls -lah)",
        r"""awk -F: '!/^#/ && $6 {print $4"/"$6".log"}' /exlibris/startup/init.dat | sort -u | while read f ; do if [ -f "$f" ]; then ls -lah "$f" ; fi ; done""",
    ),
    (
        "ultimele linii din loguri stop (dacă există)",
        r"""awk -F: '!/^#/ && $6 {print $4"/"$6".log"}' /exlibris/startup/init.dat | sort -u | while read f ; do if [ -f "$f" ]; then echo "----- $f"; tail -n 40 "$f"; fi ; done""",
    ),
]


def run(client, title, cmd):
    print("=" * 70)
    print(f"# {title}\n$ {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out.strip():
        print(out, end="" if out.endswith("\n") else "\n")
    if err.strip():
        print("[STDERR]", err, end="" if err.endswith("\n") else "\n")


def main():
    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print(f"Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
    client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)

    try:
        for title, cmd in COMMANDS:
            run(client, title, cmd)
    finally:
        client.close()
        print("=" * 70)
        print("Gata, conexiune închisă.")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nInterupt manual.")
        sys.exit(1)
    except Exception as exc:
        print(f"Eroare: {exc}", file=sys.stderr)
        sys.exit(1)

