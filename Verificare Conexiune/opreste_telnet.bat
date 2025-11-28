@echo off
REM Script pentru a opri procesul telnet zombie care consuma 99.9% CPU
REM Ruleaza acest script prin SSH pe server

echo ==========================================
echo Oprire proces telnet zombie (PID 1682)
echo ==========================================
echo.

"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "ps aux | grep 1682 | grep -v grep"

echo.
echo Opreste procesul...
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "kill -9 1682"

echo.
echo Verifica daca s-a oprit:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "ps aux | grep 1682 | grep -v grep || echo 'Procesul a fost oprit!'"

echo.
echo Verifica toate procesele telnet:
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "ps aux | grep telnet | grep -v grep"

echo.
echo Top procese dupa CPU (dupa oprirea telnet):
"C:\Program Files\PuTTY\plink.exe" -ssh -P 22 -l root -pw "YOUR-SERVER-PASSWORD" -batch YOUR-IP-or-http://WEBSITE "ps aux --sort=-%cpu | head -11"

echo.
echo Gata!
pause

