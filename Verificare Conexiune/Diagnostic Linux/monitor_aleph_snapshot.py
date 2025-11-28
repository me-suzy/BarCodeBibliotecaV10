#!/usr/bin/env python3
"""
monitor_aleph_snapshot.py

Colectează instantanee despre starea serverului ALEPH via SSH.
Rulează aceleași comenzi folosite în investigațiile anterioare și le repetă
periodic (opțional) pentru monitorizare continuă.

Utilizare de bază:
    python monitor_aleph_snapshot.py --host YOUR-IP-or-http://WEBSITE --user root

Argumente utile:
    --interval 120    -> repetă la fiecare 120s (implicit 300s când --loop)
    --loop            -> rulează în buclă până la Ctrl+C
    --port 22         -> port SSH (implicit 22)
    --password XXX    -> poți pasa parola direct (altfel e cerută interactiv)

IMPORTANT: Scriptul doar citește informații, nu modifică nimic pe server.
"""

from __future__ import annotations

import argparse
import datetime as _dt
import getpass
import sys
import time
from typing import Iterable, Tuple

import paramiko


DEFAULT_COMMANDS: Tuple[Tuple[str, str], ...] = (
    ("timestamp (server)", "date"),
    ("uptime", "uptime"),
    (
        "proc util/start_stop",
        "ps -eo pid,lstart,user,cmd | egrep 'util_|start_stop|aleph_shutdown|pc_server|www_server' | grep -v grep",
    ),
    (
        "stop flags recente",
        "find /exlibris/aleph/u20_2 -maxdepth 2 -name 'util_e_*_stop' -printf '%TY-%Tm-%Td %TH:%TM %p\\n' 2>/dev/null | sort",
    ),
    (
        "jobd ultime linii",
        "tail -n 20 /exlibris/aleph/a20_2/aleph/proc/jobd.log 2>/dev/null",
    ),
    (
        "pc_server ultime linii",
        "tail -n 20 /exlibris/aleph/a20_2/log/pc_server.log 2>/dev/null",
    ),
    (
        "www_server ultime linii",
        "tail -n 10 /exlibris/aleph/a20_2/log/www_server.log 2>/dev/null",
    ),
)


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Monitorizare snapshot pentru procese/loguri ALEPH."
    )
    parser.add_argument("--host", required=True, help="Adresa IP/host a serverului.")
    parser.add_argument("--user", default="root", help="Utilizator SSH (implicit root).")
    parser.add_argument("--port", type=int, default=22, help="Port SSH (implicit 22).")
    parser.add_argument(
        "--password",
        help="Parola SSH (dacă lipsește, este cerută interactiv).",
    )
    parser.add_argument(
        "--interval",
        type=int,
        default=300,
        help="Secunde între execuții când rulează în buclă (implicit 300).",
    )
    parser.add_argument(
        "--loop",
        action="store_true",
        help="Menține scriptul în execuție, repetând comenzile la interval.",
    )
    parser.add_argument(
        "--commands-file",
        help="Fișier text cu perechi titlu|comandă (se adaugă la comenzile implicite).",
    )
    parser.add_argument(
        "--no-defaults",
        action="store_true",
        help="Nu executa setul implicit, doar comenzile din --commands-file.",
    )
    return parser


def _load_extra_commands(path: str) -> Tuple[Tuple[str, str], ...]:
    commands = []
    with open(path, "r", encoding="utf-8") as handle:
        for raw in handle:
            raw = raw.strip()
            if not raw or raw.startswith("#"):
                continue
            if "|" not in raw:
                raise ValueError(
                    f"Linia din {path!r} trebuie să conțină titlu|comandă: {raw}"
                )
            title, cmd = raw.split("|", 1)
            commands.append((title.strip(), cmd.strip()))
    return tuple(commands)


def _iter_commands(args: argparse.Namespace) -> Iterable[Tuple[str, str]]:
    commands: Tuple[Tuple[str, str], ...] = ()
    if not args.no_defaults:
        commands += DEFAULT_COMMANDS
    if args.commands_file:
        commands += _load_extra_commands(args.commands_file)
    if not commands:
        raise ValueError("Nu există comenzi de rulat. Verifică opțiunile.")
    return commands


def _run_command(client: paramiko.SSHClient, title: str, command: str) -> None:
    print("=" * 70)
    print(f"# {title}\n$ {command}")
    start = time.time()
    stdin, stdout, stderr = client.exec_command(command, timeout=90)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    elapsed = time.time() - start
    print(f"[⏱️ {elapsed:.2f}s]")
    if out.strip():
        print(out.rstrip())
    if err.strip():
        print("[STDERR]", err.rstrip())


def main() -> int:
    parser = _build_parser()
    args = parser.parse_args()

    commands = tuple(_iter_commands(args))

    password = args.password or getpass.getpass(
        f"Parola pentru {args.user}@{args.host}: "
    )

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        print(
            f"[INFO] Conectare la {args.user}@{args.host}:{args.port} "
            f"la {_dt.datetime.now():%Y-%m-%d %H:%M:%S} ..."
        )
        client.connect(
            args.host,
            args.port,
            args.user,
            password,
            timeout=15,
        )
        print("[INFO] Conectat. Pentru oprire folosește Ctrl+C.\n")

        iteration = 1
        while True:
            stamp = _dt.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            print(f"\n######################################################################")
            print(f"# SNAPSHOT #{iteration} @ {stamp}")
            print(f"######################################################################\n")

            for title, command in commands:
                _run_command(client, title, command)

            if not args.loop:
                break

            iteration += 1
            print(f"\n[INFO] Pauză {args.interval}s ... (Ctrl+C pentru oprire)\n")
            time.sleep(max(args.interval, 1))

    except KeyboardInterrupt:
        print("\n[INFO] Monitorizare întreruptă manual.")
    except Exception as exc:  # pylint: disable=broad-except
        print(f"[EROARE] {type(exc).__name__}: {exc}", file=sys.stderr)
        return 1
    finally:
        client.close()
        print("[INFO] Conexiune SSH închisă.")

    return 0


if __name__ == "__main__":
    sys.exit(main())

