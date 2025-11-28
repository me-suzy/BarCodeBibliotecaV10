#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase7.py

Scanează serverul pentru a identifica scripturile cron, fişierele şi
comenzile care ar putea opri Aleph la ora 17:00.
"""

import getpass
import io
import paramiko
import sys
import time

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"

SEARCH_PATTERNS = [
    "aleph_shutdown",
    "aleph_startup",
    "start_stop stop",
    "start_stop start",
    "server_monitor -ks",
    "util_e_01_stop",
    "util_e_03_stop",
    "util_e_06_stop",
    "util_e_08_stop",
    "util_e_11_stop",
    "util_e_13_stop",
    "util_e_17_stop",
    "util_e_19_stop",
    "util_e_21_stop",
    "util_e_23_stop",
    "jobd $ALEPH_VERSION",
    "jboss_shutdown",
]


def log(msg):
    """Print cu flush imediat"""
    print(msg, flush=True)


def run(client, title, command, timeout=30):
    log("=" * 70)
    log(f"# {title}")
    log(f"$ {command}")
    log(f"[DEBUG] Execut comanda... (timeout={timeout}s)")

    start = time.time()
    try:
        stdin, stdout, stderr = client.exec_command(command, timeout=timeout)

        log(f"[DEBUG] Aștept rezultat...")
        out = stdout.read().decode(errors="replace")
        err = stderr.read().decode(errors="replace")
        elapsed = time.time() - start

        log(f"[DEBUG] Răspuns primit în {elapsed:.2f}s")

        if out.strip():
            log(out if out.endswith("\n") else out + "\n")
        else:
            log("[FĂRĂ OUTPUT]")

        if err.strip():
            log(f"[STDERR] {err}")

    except Exception as e:
        log(f"[EROARE] Comanda a eșuat: {e}")


def main():
    if hasattr(sys.stdout, "buffer"):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")

    log(f"[DEBUG] Start script la {time.strftime('%H:%M:%S')}")

    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    log(f"[DEBUG] Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
    try:
        client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)
        log("[DEBUG] ✓ Conectat cu succes!")
    except Exception as e:
        log(f"[EROARE] Nu m-am putut conecta: {e}")
        sys.exit(1)

    try:
        log("\n[DEBUG] === VERIFICARE CRONTAB ===\n")
        run(client, "conţinut crontab root", "cat /var/spool/cron/root 2>/dev/null || echo 'Nu există'", timeout=10)

        log("\n[DEBUG] === VERIFICARE /etc/cron.d ===\n")
        run(client, "listare /etc/cron.d", "ls -lah /etc/cron.d 2>/dev/null || echo 'Nu există'", timeout=10)

        log("\n[DEBUG] === CĂUTARE PATTERN-URI ÎN CRON ===\n")
        run(client, "grep pattern cron root",
            "grep -Ein 'aleph|start_stop|server_monitor|shutdown' /var/spool/cron/root 2>/dev/null || echo 'Nimic găsit'",
            timeout=10)

        log("\n[DEBUG] === CĂUTARE ÎN /etc/rc.d ===\n")
        run(client, "grep aleph în /etc/rc.d",
            "grep -R 'aleph\\|start_stop\\|shutdown' /etc/rc.d -n 2>/dev/null | head -20 || echo 'Nimic găsit'",
            timeout=15)

        log("\n[DEBUG] === CĂUTARE PATTERN-URI ÎN /exlibris (poate dura!) ===\n")

        # Căutări mai rapide - limitate
        patterns_quick = ["aleph_shutdown", "start_stop stop", "util_e_17_stop"]
        for i, pattern in enumerate(patterns_quick, 1):
            log(f"\n[DEBUG] Pattern {i}/{len(patterns_quick)}: {pattern}")
            cmd = f'grep -R "{pattern}" /exlibris 2>/dev/null | head -n 20 || echo "Nimic găsit"'
            run(client, f'grep "{pattern}"', cmd, timeout=45)

        log("\n[DEBUG] === CĂUTARE FIȘIERE SPECIFICE ===\n")
        run(client, "căutare util_e_*_stop",
            "find /exlibris -name 'util_e_*_stop' -type f 2>/dev/null | head -20 || echo 'Nimic găsit'",
            timeout=30)

        run(client, "căutare *shutdown*",
            "find /exlibris -name '*shutdown*' -type f 2>/dev/null | head -20 || echo 'Nimic găsit'",
            timeout=30)

        log("\n[DEBUG] === CĂUTARE BACKUP/17:00 ===\n")
        run(client, "căutare 17:00",
            "grep -R '17:00\\|16:5.*stop' /exlibris/aleph 2>/dev/null | head -n 30 || echo 'Nimic găsit'",
            timeout=45)

    finally:
        client.close()
        log("=" * 70)
        log(f"[DEBUG] Gata la {time.strftime('%H:%M:%S')}. Conexiune închisă.")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log("\n[DEBUG] Întrerupt manual.")
        sys.exit(1)
    except Exception as exc:
        log(f"[EROARE FATALĂ] {exc}")
        import traceback
        traceback.print_exc()
        sys.exit(1)