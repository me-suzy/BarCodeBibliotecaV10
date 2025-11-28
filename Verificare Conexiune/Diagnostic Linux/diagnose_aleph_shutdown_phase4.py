#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase4.py

Investighează scripturile de startup din /exlibris/startup, în special
fişierul `start_stop`, fără a modifica nimic pe server. Rulează scriptul
după fazele anterioare pentru a înţelege ce comenzi sunt apelate când se
porneşte/opreşte serviciul Aleph.
"""

import paramiko
import sys
import getpass

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă-l gol dacă vrei să fie cerut la rulare

COMMANDS = [
    ("listare /exlibris/startup", "ls -lah /exlibris/startup"),
    ("permisiuni start_stop", "ls -lah /exlibris/startup/start_stop"),
    ("primele 200 linii start_stop", "sed -n '1,200p' /exlibris/startup/start_stop"),
    ("restul start_stop (dacă lung)", "sed -n '200,400p' /exlibris/startup/start_stop"),
    ("căutare stop|kill în startup", "grep -En 'stop|kill|shutdown' /exlibris/startup/start_stop"),
    ("scripturi auxiliare", "ls -lah /exlibris/startup/*.sh"),
    ("conţinut start_lib_batch (dacă există)", "test -f /exlibris/startup/start_lib_batch && sed -n '1,200p' /exlibris/startup/start_lib_batch"),
    ("conţinut stop_lib_batch (dacă există)", "test -f /exlibris/startup/stop_lib_batch && sed -n '1,200p' /exlibris/startup/stop_lib_batch"),
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

