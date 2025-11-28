
import paramiko
import sys
import getpass

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # poți lăsa gol pentru a fi întrebat

COMMANDS = [
    ("chkconfig exlibris", "chkconfig --list exlibris"),
    ("symlink-uri exlibris", "ls -lah /etc/rc.d/rc*.d/*exlibris"),
    ("conținut exlibris init", "cat /etc/rc.d/init.d/exlibris"),
    ("cron spool listing", "ls -lah /var/spool/cron"),
    ("cron root", "cat /var/spool/cron/root"),
    ("cron aleph", "cat /var/spool/cron/aleph"),
    ("căutare stop/kill în /exlibris", "grep -R \"stop\" /exlibris/aleph -n | head -n 200"),
    ("căutare kill în /exlibris", "grep -R \"kill\" /exlibris/aleph -n | head -n 200"),
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