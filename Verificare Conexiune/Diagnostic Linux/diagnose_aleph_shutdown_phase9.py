#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase9.py

Scop: investighează dacă există job-uri Aleph programate (job_list) care pot
declanșa oprirea catalogului în jurul orei 17:00 sau care invocă scripturi de
tip stop/shutdown.

Comenzi executate:
  1. Listare director tab/ pentru context.
  2. Afișare secvențială a fișierului job_list (primele 200 linii, apoi restul).
  3. Căutare țintită după pattern-uri relevante (17:, stop, shutdown, util_e).
  4. Afișarea fișierului job_list.conf și a documentației job_list (dacă există).
  5. Verificare job-urilor active în proc/job_list* și loguri jobd.
  6. Căutare în proc/ după scripturi util_e_*_stop sau aleph_shutdown.

Nu modifică nimic pe server; doar rulează comenzi de diagnostic.
"""

import getpass
import sys
import paramiko
import datetime
import time

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă gol pentru prompt dacă preferi


def run_command(client, title, cmd, timeout=15):
    print("=" * 70)
    print(f"# {title}\n$ {cmd}")
    start_time = time.time()
    try:
        stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
        out = stdout.read().decode(errors="replace")
        err = stderr.read().decode(errors="replace")
        elapsed = time.time() - start_time
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
    print(f"🔍 PHASE 9 - job_list analysis @ {datetime.datetime.now():%Y-%m-%d %H:%M:%S}")
    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        print(f"📡 Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
        client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)
        print("✅ Conectat!\n")

        run_command(
            client,
            "tab directory listing",
            "ls -lah /exlibris/aleph/a20_2/aleph/tab"
        )

        run_command(
            client,
            "job_list (primele 200 linii)",
            "sed -n '1,200p' /exlibris/aleph/a20_2/aleph/tab/job_list"
        )

        run_command(
            client,
            "job_list (restul dacă e mai lung)",
            "sed -n '200,400p' /exlibris/aleph/a20_2/aleph/tab/job_list"
        )

        run_command(
            client,
            "Pattern-uri job_list (17:, stop, shutdown, util_e)",
            r"grep -En '17:|stop|STOP|shutdown|SHUTDOWN|util_e' /exlibris/aleph/a20_2/aleph/tab/job_list || echo 'Nimic găsit'"
        )

        run_command(
            client,
            "job_list.conf (dacă există)",
            "test -f /exlibris/aleph/a20_2/aleph/tab/job_list.conf && cat /exlibris/aleph/a20_2/aleph/tab/job_list.conf || echo 'Nu există job_list.conf'"
        )

        run_command(
            client,
            "job_list documentație (tab/README?)",
            "grep -Hi 'job_list' /exlibris/aleph/a20_2/aleph/tab/README* 2>/dev/null || echo 'Nu există documentație locală job_list'"
        )

        run_command(
            client,
            "proc/job_list* (procese sau queue)",
            "ls -lah /exlibris/aleph/a20_2/aleph/proc/job_list* 2>/dev/null || echo 'Nu există job_list* în proc/'"
        )

        run_command(
            client,
            "jobd log (ultimele 120 linii)",
            "tail -n 120 /exlibris/aleph/a20_2/aleph/proc/jobd.log 2>/dev/null || echo 'Nu există jobd.log'"
        )

        run_command(
            client,
            "Caută util_e_*_stop în proc/",
            r"find /exlibris/aleph/a20_2/aleph/proc -maxdepth 2 -name 'util_e_*_stop' -type f 2>/dev/null || echo 'Nimic găsit'"
        )

        run_command(
            client,
            "grep aleph_shutdown în proc/",
            "grep -R 'aleph_shutdown' /exlibris/aleph/a20_2/aleph/proc 2>/dev/null | head -n 40 || echo 'Nimic găsit'"
        )

    finally:
        client.close()
        print("\n======================================================================")
        print(f"✅ PHASE 9 COMPLETĂ - {datetime.datetime.now():%H:%M:%S}")
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

