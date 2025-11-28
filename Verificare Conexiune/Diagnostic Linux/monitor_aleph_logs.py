#!/usr/bin/env python3
"""
monitor_aleph_logs.py

Deschide un stream tip `tail -F` pentru logurile principale ALEPH prin SSH.
Poți ține scriptul pornit într-o consolă pentru a vedea în timp real mesaje
de tip shutdown/stop/util.

Utilizare:
    python monitor_aleph_logs.py --host YOUR-IP-or-http://WEBSITE --user root

Argumente:
    --files           Listează fișierele monitorizate (implicit o selecție utilă)
    --grep PATTERN    Afișează doar liniile care conțin PATTERN (regex basic)
    --raw             Nu colorează și nu prefixează output-ul (doar text brut)
    --port, --password ca în scriptul snapshot

CTRL+C pentru oprire. Nu modifică nimic pe server.
"""

from __future__ import annotations

import argparse
import getpass
import re
import sys
from typing import Iterable, Optional

import paramiko

DEFAULT_FILES = [
    "/exlibris/aleph/a20_2/aleph/proc/jobd.log",
    "/exlibris/aleph/a20_2/log/pc_server.log",
    "/exlibris/aleph/a20_2/log/www_server.log",
    "/exlibris/aleph/u20_2/alephe/aleph_shutdown.log",
]


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Monitorizare live a logurilor ALEPH via SSH."
    )
    parser.add_argument("--host", required=True, help="Adresa IP/hostname.")
    parser.add_argument("--user", default="root", help="Utilizator SSH (implicit root).")
    parser.add_argument("--port", type=int, default=22, help="Port SSH (implicit 22).")
    parser.add_argument(
        "--password",
        help="Parola SSH (dacă lipsește, este cerută interactiv).",
    )
    parser.add_argument(
        "--files",
        nargs="+",
        default=None,
        help="Fișiere de monitorizat (folosește căi absolute pe server).",
    )
    parser.add_argument(
        "--grep",
        help="Afișează doar liniile care conțin expresia (regex simplu).",
    )
    parser.add_argument(
        "--raw",
        action="store_true",
        help="Nu adaugă prefix [filename] înaintea fiecărei linii.",
    )
    return parser


def _format_line(
    line: str, filename: str, raw: bool = False, match: Optional[re.Pattern] = None
) -> Optional[str]:
    if match and not match.search(line):
        return None

    if raw:
        return line

    clean_line = line.rstrip("\n")
    return f"[{filename}] {clean_line}"


def _iter_files(files: Optional[Iterable[str]]) -> Iterable[str]:
    if files:
        return files
    return DEFAULT_FILES


def main() -> int:
    parser = _build_parser()
    args = parser.parse_args()

    password = args.password or getpass.getpass(
        f"Parola pentru {args.user}@{args.host}: "
    )

    files = list(_iter_files(args.files))
    if not files:
        print("[EROARE] Nu există fișiere de monitorizat.", file=sys.stderr)
        return 1

    grep_pattern = re.compile(args.grep) if args.grep else None

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    tail_cmd = "tail -F " + " ".join(files)

    try:
        print(f"[INFO] Conectare la {args.user}@{args.host}:{args.port} ...")
        client.connect(
            args.host,
            args.port,
            args.user,
            password,
            timeout=15,
        )
        print("[INFO] Conectat. CTRL+C pentru a opri monitorizarea.\n")
        print(f"[INFO] tail remote: {tail_cmd}\n")

        transport = client.get_transport()
        if transport is None:
            raise RuntimeError("Transport SSH indisponibil.")

        channel = transport.open_session()
        channel.get_pty()
        channel.exec_command(tail_cmd)

        try:
            while True:
                line = channel.recv(4096).decode(errors="replace")
                if not line:
                    break
                for chunk in line.splitlines():
                    formatted = _format_line(
                        chunk, filename="<tail>", raw=args.raw, match=grep_pattern
                    )
                    if formatted is not None:
                        print(formatted)
        except KeyboardInterrupt:
            print("\n[INFO] Monitorizare oprită de utilizator.")
        finally:
            channel.close()

    except KeyboardInterrupt:
        print("\n[INFO] Monitorizare oprită.")
    except Exception as exc:  # pylint: disable=broad-except
        print(f"[EROARE] {type(exc).__name__}: {exc}", file=sys.stderr)
        return 1
    finally:
        client.close()
        print("[INFO] Conexiune SSH închisă.")

    return 0


if __name__ == "__main__":
    sys.exit(main())

