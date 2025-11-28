#!/usr/bin/env python3
"""
diagnose_aleph_shutdown_phase6.py

Analizează scripturile reale de start/stop şi logurile generate, folosind
căile exacte aşa cum le construieşte start_stop (adăugând /alephe/ etc.).
Scopul este să identificăm ce se execută efectiv la oprire/pornire.
"""

import getpass
import paramiko
import sys

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă gol pentru a fi întrebat
INIT_DAT_PATH = "/exlibris/startup/init.dat"


def run_simple(client, title, command):
    """Rulează o comandă simplă şi afişează rezultatul."""
    print("=" * 70)
    print(f"# {title}\n$ {command}")
    stdin, stdout, stderr = client.exec_command(command)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out.strip():
        print(out, end="" if out.endswith("\n") else "\n")
    if err.strip():
        print("[STDERR]", err, end="" if err.endswith("\n") else "\n")


def fetch_init_dat(client):
    """Returnează conţinutul init.dat şi lista de intrări parse-ate."""
    stdin, stdout, stderr = client.exec_command(f"cat {INIT_DAT_PATH}")
    text = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if err.strip():
        print("[STDERR]", err)
    entries = []
    for raw_line in text.splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        fields = line.split(":")
        entries.append({"raw": raw_line, "fields": fields})
    return text, entries


def map_root(module):
    mapping = {
        "metalib": "metalib_conf",
        "arc": "arce",
        "primo": "primoe",
        "dtl": "dtle",
        "dps": "profile",
        "verde": "home/system/bin",
    }
    return mapping.get(module, "alephe")


def compute_paths(entry):
    fields = entry["fields"]
    if len(fields) < 2:
        return None
    flag = fields[0]
    module = fields[1]
    owner = fields[2] if len(fields) > 2 else ""
    info = {
        "flag": flag,
        "module": module,
        "owner": owner,
        "start_path": None,
        "stop_path": None,
        "start_log": None,
        "stop_log": None,
        "extra": {},
    }

    if module == "oracle":
        if len(fields) >= 4:
            info["extra"]["oracle_home"] = fields[3]
        return info

    if module == "apache" and len(fields) >= 8:
        http_label = fields[3]
        alephe_dev = fields[4]
        # aleph_dev_rel = fields[5]  # nu e necesar pentru căile de script
        start_script = fields[6]
        stop_script = fields[7]
        http_root = f"{alephe_dev}/alephe/apache"
        info["start_path"] = f"{http_root}/bin/{start_script}"
        info["stop_path"] = f"{http_root}/bin/{stop_script}"
        info["start_log"] = f"{http_root}/logs/{start_script}.log"
        info["stop_log"] = f"{http_root}/logs/{stop_script}.log"
        info["extra"]["http_label"] = http_label
        info["extra"]["http_root"] = http_root
        return info

    if len(fields) >= 6:
        dev = fields[3]
        start_script = fields[4]
        stop_script = fields[5]
        root = map_root(module)
        if module == "verde":
            base_bin = f"{dev}/home/system/bin"
            base_log = f"{dev}/log"
            info["start_path"] = f"{base_bin}/{start_script}"
            info["stop_path"] = f"{base_bin}/{stop_script}"
            info["start_log"] = f"{base_log}/{start_script}.log"
            info["stop_log"] = f"{base_log}/{stop_script}.log"
        else:
            base = f"{dev}/{root}"
            info["start_path"] = f"{base}/{start_script}"
            info["stop_path"] = f"{base}/{stop_script}"
            info["start_log"] = f"{base}/{start_script}.log"
            info["stop_log"] = f"{base}/{stop_script}.log"
        info["extra"]["dev"] = dev
    return info


def remote_show_file(client, path, title):
    """Afişează primele linii din fişier dacă există."""
    if not path:
        return
    cmd = f"""if [ -f "{path}" ]; then
  echo "----- {title}: {path}"
  sed -n '1,200p' "{path}"
else
  echo "[missing] {path}"
fi"""
    run_simple(client, title, cmd)


def remote_tail_log(client, path, title):
    """Afişează ultimele linii din log dacă există."""
    if not path:
        return
    cmd = f"""if [ -f "{path}" ]; then
  echo "----- tail {path}"
  tail -n 40 "{path}"
fi"""
    run_simple(client, title, cmd)


def main():
    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print(f"Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
    client.connect(SERVER_IP, SSH_PORT, SSH_USER, password, timeout=15)

    try:
        run_simple(client, "primele 200 linii init.dat", f"sed -n '1,200p' {INIT_DAT_PATH}")
        text, entries = fetch_init_dat(client)

        print("=" * 70)
        print("# Rezumat init.dat (intrări cu flag 'Y')")
        relevant = []
        for entry in entries:
            fields = entry["fields"]
            if not fields or fields[0] != "Y":
                continue
            info = compute_paths(entry)
            relevant.append((entry, info))
            module = info.get("module", "?")
            owner = info.get("owner", "?")
            start_path = info.get("start_path")
            stop_path = info.get("stop_path")
            print(f"- modul={module:8s} owner={owner:8s} start={start_path} stop={stop_path}")

        for entry, info in relevant:
            module = info["module"]
            print("=" * 70)
            print(f"### Modul {module} (owner={info.get('owner','')})")
            extra = info.get("extra", {})
            if extra:
                for key, value in extra.items():
                    print(f"  {key}: {value}")
            remote_show_file(client, info.get("start_path"), f"conținut start {module}")
            remote_show_file(client, info.get("stop_path"), f"conținut stop {module}")
            remote_tail_log(client, info.get("start_log"), f"log start {module}")
            remote_tail_log(client, info.get("stop_log"), f"log stop {module}")

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

