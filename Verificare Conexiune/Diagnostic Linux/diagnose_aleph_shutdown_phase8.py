#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_FOCUSED.py
Căutare țintită după cauza shutdown-ului la 17:00
"""

import getpass
import io
import paramiko
import sys
import time

SERVER_IP = "YOUR-IP-or-http://WEBSITE"  # IP corect (nu 423)
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"


def log(msg):
    print(msg, flush=True)


def run(client, title, command, timeout=30):
    log("=" * 70)
    log(f"# {title}")
    log(f"$ {command}")

    start = time.time()
    try:
        stdin, stdout, stderr = client.exec_command(command, timeout=timeout)
        out = stdout.read().decode(errors="replace")
        err = stderr.read().decode(errors="replace")
        elapsed = time.time() - start

        log(f"[⏱️ {elapsed:.2f}s]")

        if out.strip():
            log(out if out.endswith("\n") else out + "\n")
        else:
            log("[FĂRĂ OUTPUT]\n")

        if err.strip():
            log(f"[STDERR] {err}\n")

    except Exception as e:
        log(f"[❌ EROARE] {e}\n")


def main():
    if hasattr(sys.stdout, "buffer"):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")

    log(f"\n🔍 INVESTIGAȚIE SHUTDOWN ALEPH - {time.strftime('%Y-%m-%d %H:%M:%S')}\n")

    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        log(f"📡 Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
        client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)
        log("✅ Conectat!\n")
    except Exception as e:
        log(f"❌ Eroare conexiune: {e}")
        sys.exit(1)

    try:
        # === 1. INVESTIGARE K10exlibris ===
        log("\n" + "="*70)
        log("📁 SECȚIUNEA 1: Fișierul K10exlibris găsit")
        log("="*70 + "\n")

        run(client, "Info K10exlibris",
            "ls -lah /etc/rc.d/rc0.d/K10exlibris", timeout=5)

        run(client, "Conținut K10exlibris",
            "cat /etc/rc.d/rc0.d/K10exlibris", timeout=5)

        # === 2. GĂSEȘTE START_STOP ===
        log("\n" + "="*70)
        log("🔎 SECȚIUNEA 2: Localizare script start_stop")
        log("="*70 + "\n")

        run(client, "Unde e start_stop?",
            "find /exlibris -maxdepth 4 -name 'start_stop' -type f 2>/dev/null", timeout=20)

        run(client, "Verifică PATH-uri comune",
            "ls -la /exlibris/aleph/a*/a*_admin/start_stop 2>/dev/null || echo 'Nu există'", timeout=10)

        # === 3. JOB-URI PROGRAMATE ALEPH ===
        log("\n" + "="*70)
        log("⏰ SECȚIUNEA 3: Job-uri Aleph (tab files)")
        log("="*70 + "\n")

        run(client, "Găsește fișiere .tab",
            "find /exlibris/aleph -path '*/tab/*.tab' 2>/dev/null | head -20", timeout=20)

        run(client, "Caută 17:00 în tab files",
            "find /exlibris/aleph -path '*/tab/*.tab' -exec grep -l '17:' {} \\; 2>/dev/null", timeout=30)

        run(client, "Conținut tab files cu 17:",
            "find /exlibris/aleph -path '*/tab/*.tab' -exec grep -H '17:' {} \\; 2>/dev/null | head -30", timeout=30)

        # === 4. SYSTEMD TIMERS ===
        log("\n" + "="*70)
        log("⏲️ SECȚIUNEA 4: Systemd timers și servicii")
        log("="*70 + "\n")

        run(client, "Lista timers active",
            "systemctl list-timers --all 2>/dev/null | grep -i aleph || echo 'Nu există'", timeout=10)

        run(client, "Servicii Aleph/ExLibris",
            "systemctl list-units | grep -i 'aleph\\|exlibris' || echo 'Nu există'", timeout=10)

        # === 5. AT JOBS ===
        log("\n" + "="*70)
        log("📅 SECȚIUNEA 5: At jobs programate")
        log("="*70 + "\n")

        run(client, "Lista at jobs",
            "atq 2>/dev/null || echo 'Serviciu at nu e activ'", timeout=10)

        # === 6. LOGS RECENTE ===
        log("\n" + "="*70)
        log("📜 SECȚIUNEA 6: Log-uri recente de shutdown")
        log("="*70 + "\n")

        run(client, "Ultima oprire Aleph în logs",
            "grep -i 'shutdown\\|stop' /exlibris/aleph/a*/a*_admin/log/* 2>/dev/null | grep '17:0' | tail -20 || echo 'Nu există'", timeout=30)

        run(client, "Messages sistem la 17:00",
            "grep '17:0' /var/log/messages 2>/dev/null | tail -30 || echo 'Nu există'", timeout=15)

        # === 7. PROCESE ACTIVE ===
        log("\n" + "="*70)
        log("🔄 SECȚIUNEA 7: Procese active acum")
        log("="*70 + "\n")

        run(client, "Procese Aleph",
            "ps aux | grep -i aleph | grep -v grep || echo 'Nimic'", timeout=10)

        # === 8. BACKUP SCRIPTS ===
        log("\n" + "="*70)
        log("💾 SECȚIUNEA 8: Verificare scripturi backup")
        log("="*70 + "\n")

        run(client, "Conținut exec_backup_main",
            "cat /exlibris/backup/scripts/exec_backup_main 2>/dev/null | head -100 || echo 'Nu există'", timeout=10)

        run(client, "Scripturi în /exlibris/backup",
            "ls -lah /exlibris/backup/scripts/ 2>/dev/null || echo 'Nu există'", timeout=10)

    finally:
        client.close()
        log("\n" + "="*70)
        log(f"✅ INVESTIGAȚIE COMPLETĂ - {time.strftime('%H:%M:%S')}")
        log("="*70 + "\n")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log("\n⚠️  Întrerupt manual.")
        sys.exit(1)
    except Exception as exc:
        log(f"\n❌ EROARE: {exc}")
        import traceback
        traceback.print_exc()
        sys.exit(1)