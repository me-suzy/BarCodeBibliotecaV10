@echo off
REM Script pentru a creste swap-ul pe serverul Linux prin SSH

echo ==========================================
echo Creste Swap pe Server Linux
echo ==========================================
echo.
echo ATENTIE: Acest script va crea un swap file de 2 GB pe server
echo.
pause

"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon --show && free -m | grep Swap"

echo.
echo Verifica spatiu disk...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "df -h / | tail -1"

echo.
echo Creeaza swap file nou (2 GB)...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "if [ -f /swapfile2 ]; then swapoff /swapfile2 2>/dev/null; rm -f /swapfile2; fi; dd if=/dev/zero of=/swapfile2 bs=1M count=2048 && chmod 600 /swapfile2 && mkswap /swapfile2 && swapon /swapfile2"

echo.
echo Verifica swap nou:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon --show && free -m | grep Swap"

echo.
echo Adauga in /etc/fstab pentru permanent...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "if ! grep -q '/swapfile2' /etc/fstab; then echo '/swapfile2 none swap sw 0 0' >> /etc/fstab; echo 'Adaugat in /etc/fstab'; else echo 'Deja exista in /etc/fstab'; fi"

echo.
echo ==========================================
echo GATA!
echo ==========================================
pause

