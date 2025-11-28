#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase10.py

Continuă investigația asupra opririlor automate ale catalogului:
  1. Caută fișiere job_list în toate directoarele a??/a??_admin/tab.
  2. Afișează conținut și pattern-uri relevante (17:, stop, shutdown, util_e).
  3. Verifică job_list ale bibliotecilor RAI, USR, VIR etc. dacă există.
  4. Inspectează tab_job_type pentru legături către scripturi stop/shutdown.
  5. Enumeră scripturile util_* din proc/ care conțin aleph_shutdown/start_stop.

Numai diagnostic, fără modificări pe server.
"""

import getpass
import sys
import time
import datetime
import paramiko

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă gol pentru prompt


def run_command(client, title, cmd, timeout=20):
    print("=" * 70)
    print(f"# {title}\n$ {cmd}")
    start = time.time()
    try:
        stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
        out = stdout.read().decode(errors="replace")
        err = stderr.read().decode(errors="replace")
        elapsed = time.time() - start
        print(f"[⏱️ {elapsed:.2f}s]")
        if out.strip():
            print(out, end="" if out.endswith("\n") else "\n")
        if err.strip():
            print("[STDERR]", err, end="" if err.endswith("\n") else "\n")
        return out, err
    except paramiko.SSHException as exc:
        print(f"[EROARE] Comanda a eșuat: {exc}", file=sys.stderr)
        return "", str(exc)
    except Exception as exc:  # pylint: disable=broad-except
        print(f"[EROARE] Eroare neașteptată: {exc}", file=sys.stderr)
        return "", str(exc)


def main():
    print(f"🔎 PHASE 10 - job_list extins @ {datetime.datetime.now():%Y-%m-%d %H:%M:%S}")
    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        print(f"📡 Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
        client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)
        print("✅ Conectat!\n")

        run_command(
            client,
            "Găsește job_list în toate a??/a??_admin/tab",
            "find /exlibris/aleph -maxdepth 4 -path '*_admin/tab/job_list' -type f 2>/dev/null"
        )

        run_command(
            client,
            "Pattern 17: în toate job_list găsite",
            r"""find /exlibris/aleph -maxdepth 4 -path '*_admin/tab/job_list' -type f -exec \
grep -Hn '17:' {} \; 2>/dev/null || echo 'Nimic cu 17:'"""
        )

        run_command(
            client,
            "Pattern stop/shutdown/util_e în job_list",
            r"""find /exlibris/aleph -maxdepth 4 -path '*_admin/tab/job_list' -type f -exec \
grep -Hn 'stop\|STOP\|shutdown\|SHUTDOWN\|util_e' {} \; 2>/dev/null || echo 'Nimic relevant'"""
        )

        run_command(
            client,
            "Exemplu job_list RAI? (dacă există)",
            "test -f /exlibris/aleph/u20_2/rai50/tab/job_list && sed -n '1,200p' /exlibris/aleph/u20_2/rai50/tab/job_list || echo 'Nu există job_list RAI50'"
        )

        run_command(
            client,
            "Exemplu job_list USR?",
            "test -f /exlibris/aleph/u20_2/usr00/tab/job_list && sed -n '1,200p' /exlibris/aleph/u20_2/usr00/tab/job_list || echo 'Nu există job_list USR00'"
        )

        run_command(
            client,
            "Exemplu job_list VIR?",
            "test -f /exlibris/aleph/u20_2/vir01/tab/job_list && sed -n '1,200p' /exlibris/aleph/u20_2/vir01/tab/job_list || echo 'Nu există job_list VIR01'"
        )

        run_command(
            client,
            "tab_job_type global (căutare stop/shutdown)",
            r"grep -En 'stop|shutdown|start_stop|aleph_shutdown' /exlibris/aleph/a20_2/aleph/tab/tab_job_type || echo 'Nimic special în tab_job_type'"
        )

        run_command(
            client,
            "Scripturi util_* ce conțin aleph_shutdown",
            r"grep -Rl 'aleph_shutdown' /exlibris/aleph/a20_2/aleph/proc/util_* 2>/dev/null | head -40 || echo 'Nimic găsit'"
        )

        run_command(
            client,
            "Scripturi util_* ce conțin start_stop",
            r"grep -Rl 'start_stop' /exlibris/aleph/a20_2/aleph/proc/util_* 2>/dev/null | head -40 || echo 'Nimic găsit'"
        )

        run_command(
            client,
            "Caută job_list.log în proc/",
            "find /exlibris/aleph/a20_2/aleph/proc -maxdepth 1 -name 'job_list*.log' -type f 2>/dev/null || echo 'Nu există loguri job_list'"
        )

    finally:
        client.close()
        print("\n======================================================================")
        print(f"✅ PHASE 10 COMPLETĂ - {datetime.datetime.now():%H:%M:%S}")
        print("======================================================================")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nInterupt manual.")
        sys.exit(1)
    except Exception as exc:  # pylint: disable=broad-except
        print(f"Eroare: {exc}", file=sys.stderr)
        sys.exit(1)

