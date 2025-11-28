@echo off
REM Script CMD/BAT pentru testarea stabilității serverului Linux
REM Nu modifică nimic, doar verifică statusul sistemului

setlocal enabledelayedexpansion

set SERVER_IP=YOUR-IP-or-http://WEBSITE
set SSH_PORT=22
set SSH_USER=root
set SSH_PASS=YOUR-SERVER-PASSWORD
set CATALOG_URL=http://%SERVER_IP%:8991/F

set RESULTS_DIR=%~dp0
for /f "tokens=2-4 delims=/ " %%a in ('date /t') do set mydate=%%c%%a%%b
for /f "tokens=1-2 delims=/:" %%a in ('time /t') do set mytime=%%a%%b
set TIMESTAMP=%mydate%_%mytime%
set TIMESTAMP=%TIMESTAMP: =0%

echo ========================================
echo RAPORT VERIFICARE SERVER
echo ========================================
echo Server IP: %SERVER_IP%
echo Catalog URL: %CATALOG_URL%
echo Timestamp: %date% %time%
echo.

set REPORT_FILE=%RESULTS_DIR%%TIMESTAMP%_raport_verificare.txt
(
    echo RAPORT VERIFICARE SERVER - %date% %time%
    echo Server IP: %SERVER_IP%
    echo Catalog URL: %CATALOG_URL%
    echo ========================================
    echo.
) > "%REPORT_FILE%"

REM Test HTTP
echo [TEST HTTP] Verificare accesibilitate catalog...
(
    echo [TEST HTTP] Verificare accesibilitate catalog...
    echo.
) >> "%REPORT_FILE%"

curl -s -o nul -w "HTTP Status: %%{http_code}\n" "%CATALOG_URL%" 2>nul
if errorlevel 1 (
    echo HTTP Test: EȘEC - Nu s-a putut conecta
    (
        echo HTTP Test: EȘEC - Nu s-a putut conecta
        echo.
    ) >> "%REPORT_FILE%"
) else (
    echo HTTP Test: SUCCES
    (
        echo HTTP Test: SUCCES
        echo.
    ) >> "%REPORT_FILE%"
)

REM Verifică dacă plink este disponibil
where plink >nul 2>&1
if errorlevel 1 (
    echo.
    echo ATENȚIE: plink.exe nu este disponibil!
    echo Instalează PuTTY pentru a folosi verificările SSH.
    echo.
    (
        echo ATENȚIE: plink.exe nu este disponibil!
        echo Instalează PuTTY pentru a folosi verificările SSH.
        echo.
    ) >> "%REPORT_FILE%"
    goto :end
)

REM Test SSH
echo.
echo [TEST SSH] Conectare la server...
(
    echo [TEST SSH] Conectare la server...
    echo.
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "echo 'SSH Connection Test OK'" >nul 2>&1
if errorlevel 1 (
    echo ✗ Conexiune SSH eșuată
    (
        echo SSH Connection: EȘEC
        echo.
    ) >> "%REPORT_FILE%"
    goto :end
)

echo ✓ Conexione SSH reușită!
(
    echo SSH Connection: SUCCES
    echo.
) >> "%REPORT_FILE%"

REM 1. Status general
echo.
echo [1] STATUS GENERAL SISTEM
(
    echo.
    echo [1] STATUS GENERAL SISTEM
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "uptime" >> "%REPORT_FILE%" 2>&1
echo.
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "uptime"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "free -h" >> "%REPORT_FILE%" 2>&1
echo Memorie:
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "free -h"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "df -h" >> "%REPORT_FILE%" 2>&1
echo Spațiu disk:
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "df -h"

REM 2. Procese Aleph
echo.
echo [2] PROCESE ALEPH
(
    echo.
    echo [2] PROCESE ALEPH
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux | grep -i aleph | grep -v grep" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux | grep -i aleph | grep -v grep"

REM 3. Port 8991
echo.
echo [3] VERIFICARE PORT 8991
(
    echo.
    echo [3] VERIFICARE PORT 8991
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "netstat -tuln | grep 8991 || ss -tuln | grep 8991" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "netstat -tuln | grep 8991 || ss -tuln | grep 8991"

REM 4. Servicii systemd
echo.
echo [4] SERVICII SYSTEMD
(
    echo.
    echo [4] SERVICII SYSTEMD
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "systemctl list-units --type=service --state=running 2>/dev/null | grep -i aleph || echo 'Nu s-au găsit servicii Aleph'" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "systemctl list-units --type=service --state=running 2>/dev/null | grep -i aleph || echo 'Nu s-au găsit servicii Aleph'"

REM 5. Loguri sistem
echo.
echo [5] LOGURI SISTEM RECENTE
(
    echo.
    echo [5] LOGURI SISTEM RECENTE
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "journalctl -n 50 --no-pager 2>/dev/null || tail -50 /var/log/messages 2>/dev/null || tail -50 /var/log/syslog 2>/dev/null || echo 'Nu s-au găsit loguri'" >> "%REPORT_FILE%" 2>&1

REM 6. Loguri kernel
echo.
echo [6] LOGURI KERNEL (dmesg)
(
    echo.
    echo [6] LOGURI KERNEL (dmesg - ultimele 30 linii)
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "dmesg | tail -30" >> "%REPORT_FILE%" 2>&1

REM 7. Erori în loguri
echo.
echo [7] CĂUTARE ERORI ÎN LOGURI
(
    echo.
    echo [7] CĂUTARE ERORI ÎN LOGURI
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "journalctl -p err -n 20 --no-pager 2>/dev/null || grep -i error /var/log/messages 2>/dev/null | tail -20 || echo 'Nu s-au găsit erori'" >> "%REPORT_FILE%" 2>&1

REM 8. Load average și CPU
echo.
echo [8] LOAD AVERAGE ȘI CPU
(
    echo.
    echo [8] LOAD AVERAGE ȘI CPU
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "top -bn1 | head -20" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "top -bn1 | head -20"

REM 9. Procese cu consum ridicat
echo.
echo [9] PROCESE CU CONSUM RIDICAT
(
    echo.
    echo [9] PROCESE CU CONSUM RIDICAT
    echo ----------------------------------------
) >> "%REPORT_FILE%"

echo Top 10 procese după memorie:
(
    echo Top 10 procese după memorie:
) >> "%REPORT_FILE%"
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux --sort=-%mem | head -10" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux --sort=-%mem | head -10"

echo Top 10 procese după CPU:
(
    echo.
    echo Top 10 procese după CPU:
) >> "%REPORT_FILE%"
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux --sort=-%cpu | head -10" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "ps aux --sort=-%cpu | head -10"

REM 10. Cron jobs
echo.
echo [10] TASK-URI PROGRAMATE (cron)
(
    echo.
    echo [10] TASK-URI PROGRAMATE (cron)
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "crontab -l 2>/dev/null || echo 'Nu există cron jobs pentru root'" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "crontab -l 2>/dev/null || echo 'Nu există cron jobs pentru root'"

REM 11. Conexiuni rețea
echo.
echo [11] CONEXIUNI REȚEA ACTIVE
(
    echo.
    echo [11] CONEXIUNI REȚEA ACTIVE
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "netstat -tn | head -20 || ss -tn | head -20" >> "%REPORT_FILE%" 2>&1

REM 12. Fișiere log Aleph
echo.
echo [12] CĂUTARE FIȘIERE LOG ALEPH
(
    echo.
    echo [12] CĂUTARE FIȘIERE LOG ALEPH
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "find /var/log /opt /usr/local /home -name '*aleph*' -type f 2>/dev/null | head -20" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "find /var/log /opt /usr/local /home -name '*aleph*' -type f 2>/dev/null | head -20"

REM 13. Istoric reboot-uri
echo.
echo [13] ISTORIC REBOOT-URI
(
    echo.
    echo [13] ISTORIC REBOOT-URI
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "last reboot | head -10" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "last reboot | head -10"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "who -b" >> "%REPORT_FILE%" 2>&1
echo Ultimul boot:
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "who -b"

REM 14. Status swap
echo.
echo [14] STATUS SWAP
(
    echo.
    echo [14] STATUS SWAP
    echo ----------------------------------------
) >> "%REPORT_FILE%"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "swapon --show" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "swapon --show"

plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "cat /proc/swaps" >> "%REPORT_FILE%" 2>&1
plink -ssh -P %SSH_PORT% -l %SSH_USER% -pw "%SSH_PASS%" -batch %SERVER_IP% "cat /proc/swaps"

:end
echo.
echo ========================================
echo Raport salvat în: %REPORT_FILE%
echo Verificare completă!
echo.

pause

