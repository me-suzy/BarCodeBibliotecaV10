@echo off
REM Script pentru configurare prioritate swap - swap-ul nou sa fie folosit primul

echo ==========================================
echo Configurare Prioritate Swap
echo ==========================================
echo.

echo [1] Status swap actual:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon -s"

echo.
echo [2] Opreste swap-ul nou temporar...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapoff /swapfile2 2>&1"

echo.
echo [3] Activeaza swap-ul nou cu prioritate mai mare (10)...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon -p 10 /swapfile2 2>&1"

echo.
echo [4] Opreste swap-ul vechi temporar...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapoff /dev/sda2 2>&1"

echo.
echo [5] Reactiveaza swap-ul vechi cu prioritate mai mica (5)...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon -p 5 /dev/sda2 2>&1"

echo.
echo [6] Verifica swap-urile dupa configurare:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "swapon -s"

echo.
echo [7] Verifica memorie:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "free -m"

echo.
echo [8] Actualizeaza /etc/fstab pentru prioritati...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "cp /etc/fstab /etc/fstab.backup_swap && sed -i 's|^/swapfile2.*|/swapfile2 none swap sw,pri=10 0 0|' /etc/fstab && sed -i 's|^LABEL=SWAP-sda2.*|LABEL=SWAP-sda2 swap swap defaults,pri=5 0 0|' /etc/fstab && echo 'Actualizat /etc/fstab' && cat /etc/fstab | grep swap"

echo.
echo ==========================================
echo GATA!
echo ==========================================
echo.
echo Swap-ul nou (4 GB) va fi folosit PRIMUL (prioritate 10)
echo Swap-ul vechi (4 GB) va fi folosit DOAR daca cel nou se epuizeaza (prioritate 5)
echo.
pause

