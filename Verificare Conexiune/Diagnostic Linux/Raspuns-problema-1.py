import paramiko
import sys
import getpass

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
SSH_PORT = 22
SSH_USER = "root"
SSH_PASS = "YOUR-SERVER-PASSWORD"

COMMANDS = [
    ("Timp curent + uptime", "date && uptime"),
    ("Utilizatori conectați", "who"),
    ("Ultimele conectări", "last -n 5"),
    ("Versiune OS", "cat /etc/os-release"),
    ("Procese ALEPH", "ps -eo pid,ppid,stat,lstart,cmd | grep -i '[a]leph'"),
    ("Servicii care conțin aleph", "systemctl list-units --type=service | grep -i aleph || true"),
    ("Procese cron active", "ps -ef | grep -i '[c]ron'"),
    ("Crontab root", "crontab -l"),
    ("Fișiere în /etc/cron.d", "ls -lah /etc/cron.d"),
    ("Fișiere în /etc/cron.daily", "ls -lah /etc/cron.daily"),
    ("Fișiere în /etc/cron.hourly", "ls -lah /etc/cron.hourly"),
    ("Joburi atd", "atq || true"),
    ("Log cron ultimele 200 linii", "tail -n 200 /var/log/cron || tail -n 200 /var/log/syslog"),
    ("Jurnal systemd 17:00 ±10 min", "journalctl --since 'today 16:50' --until 'today 17:10'"),
    ("Loguri ALEPH (dacă există)", "ls -lah /var/log | grep -i aleph || true"),
    ("grep aleph în syslog", "grep -i aleph /var/log/syslog | tail -n 50 || true"),
    ("grep stop/shutdown în syslog", "grep -i 'stop\\|shutdown' /var/log/syslog | tail -n 50 || true"),
]

def run_command(client, title, command):
    print("=" * 80)
    print(f"# {title}\n$ {command}")
    stdin, stdout, stderr = client.exec_command(command)
    stdout_data = stdout.read().decode(errors="replace")
    stderr_data = stderr.read().decode(errors="replace")
    if stdout_data.strip():
        print(stdout_data, end="" if stdout_data.endswith("\n") else "\n")
    if stderr_data.strip():
        print("[STDERR]", stderr_data, end="" if stderr_data.endswith("\n") else "\n")

def main():
    password = SSH_PASS or getpass.getpass(f"Parola pentru {SSH_USER}@{SERVER_IP}: ")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print(f"Conectare la {SSH_USER}@{SERVER_IP}:{SSH_PORT} ...")
    client.connect(SERVER_IP, port=SSH_PORT, username=SSH_USER, password=password, timeout=15)

    try:
        for title, command in COMMANDS:
            run_command(client, title, command)
    finally:
        client.close()
        print("=" * 80)
        print("Gata: conexiunea SSH a fost închisă.")

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nInterupt manual.")
        sys.exit(1)
    except Exception as exc:
        print(f"Eroare: {exc}", file=sys.stderr)
        sys.exit(1)