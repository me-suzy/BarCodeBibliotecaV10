import paramiko
import sys
import getpass

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"  # lasă gol ca să te întrebe la rulare dacă preferi

COMMANDS = [
    ("ls init.d", "ls -lah /etc/rc.d/init.d"),
    ("chkconfig", "chkconfig --list | grep -i aleph"),
    ("scripturi aleph cron", "ls -lah /exlibris/aleph/a20_2/aleph/cron"),
    ("crontab aleph (direct)", "su - aleph -c 'crontab -l'"),
    ("crontab usm01", "su - usm01 -c 'crontab -l'"),
    ("crontab usr00", "su - usr00 -c 'crontab -l'"),
    ("crontab vir01", "su - vir01 -c 'crontab -l'"),
    ("crontab rai01", "su - rai01 -c 'crontab -l'"),
    ("cron.tab global aleph", "grep -R '' /exlibris/aleph/a20_2/aleph/cron -n"),
    ("cron.log aleph", "tail -n 200 /exlibris/aleph/a20_2/aleph/proc/p_*.err 2>/dev/null"),
    ("/var/log/messages 16:30-17:30", "grep 'Nov  7 1[67]:' /var/log/messages"),
    ("|-> filtre aleph", "grep -i aleph /var/log/messages | tail -n 100"),
    ("|-> filtre shutdown/stop", "grep -Ei 'stop|shutdown|down' /var/log/messages | tail -n 100"),
    ("scripturi job daemon", "ps -ef | grep -i '[p]_manager\\|jobd\\|ue_01'"),
    ("cron aleph documentație", "grep -R '' /exlibris/aleph/a20_2/aleph/tab/job_list -n"),
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