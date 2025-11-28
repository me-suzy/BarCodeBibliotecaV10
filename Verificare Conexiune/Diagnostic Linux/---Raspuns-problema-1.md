## Ce-am găsit

- **Crontab root**: doar backup-uri la 22:00 și 23:00 plus scriptul `backup_summary_ntfy.sh` la 03:10. Nicio sarcină la 17:00.  
- **Log cron (`/var/log/cron`)**: acoperă toată ziua; repetă doar `sa1`/`sa2`, `run-parts` și joburile de backup menționate. Nu apar comenzi care să oprească servicii în jurul orei 17:00.  
- **Servicii**: mașina nu folosește `systemd` (`systemctl` lipsește), deci e foarte probabil un CentOS 5/6 cu SysV init.  
- **Procese curente**: sute de procese Aleph și Oracle active; toate par pornite din septembrie/octombrie, nimic recent legat de opriri.  
- **Loguri sistem**: nu există `/var/log/syslog`; trebuie căutat în fișierele clasice SysV (`/var/log/messages`, poate `dmesg`).  
- **Cron.d / cron.daily / cron.hourly**: doar scripturi standard, fără nimic legat de Aleph.  
- **Nimic suspect direct** care să explice restarturile la 17:00.

## Pas următor

Probabil există un script SysV sau cron la nivel de aplicație Aleph. Ținte:

- `ls -lah /etc/rc.d/init.d`, `chkconfig --list | grep -i aleph`.  
- Caută cron-uri în conturile Aleph (`su - aleph` → `crontab -l`).  
- Verifică cron-urile în `/exlibris/*/aleph/proc` (uneori Aleph are cronuri proprii).  

Continuăm cu comenzile de mai sus; după ce le rulezi, trimite-mi rezultatele și continuăm analiza.