# Script PowerShell pentru testarea stabilității serverului Linux
# Nu modifică nimic, doar verifică statusul sistemului

$SERVER_IP = "YOUR-IP-or-http://WEBSITE"
$SSH_PORT = 22
$SSH_USER = "root"
$SSH_PASS = "YOUR-SERVER-PASSWORD"
$CATALOG_URL = "http://${SERVER_IP}:8991/F"

$ResultsDir = $PSScriptRoot
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

function Save-Result {
    param(
        [string]$Filename,
        [string]$Content
    )
    $FilePath = Join-Path $ResultsDir "${Timestamp}_${Filename}"
    $Content | Out-File -FilePath $FilePath -Encoding UTF8
    return $FilePath
}

function Test-HttpConnection {
    try {
        $response = Invoke-WebRequest -Uri $CATALOG_URL -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
        return $true, "HTTP $($response.StatusCode)", $response.Content.Substring(0, [Math]::Min(200, $response.Content.Length))
    }
    catch {
        return $false, "Eroare HTTP: $_", ""
    }
}

function Invoke-SSHCommand {
    param([string]$Command)
    
    # Verifică dacă plink este disponibil
    $plinkPath = Get-Command plink -ErrorAction SilentlyContinue
    if ($plinkPath) {
        $plinkCmd = "plink.exe -ssh -P $SSH_PORT -l $SSH_USER -pw `"$SSH_PASS`" -batch $SERVER_IP `"$Command`""
        $output = cmd /c $plinkCmd 2>&1
        return $output
    }
    else {
        Write-Warning "plink.exe nu este disponibil. Instalează PuTTY pentru a folosi acest script."
        return "plink.exe nu este disponibil"
    }
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RAPORT VERIFICARE SERVER" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Server IP: $SERVER_IP"
Write-Host "Catalog URL: $CATALOG_URL"
Write-Host "Timestamp: $(Get-Date)"
Write-Host ""

$allResults = @()
$allResults += "RAPORT VERIFICARE SERVER - $(Get-Date)"
$allResults += "Server IP: $SERVER_IP"
$allResults += "Catalog URL: $CATALOG_URL"
$allResults += "========================================"
$allResults += ""

# Test HTTP
Write-Host "[TEST HTTP] Verificare accesibilitate catalog..." -ForegroundColor Yellow
$httpOk, $httpStatus, $httpContent = Test-HttpConnection
$httpResult = "HTTP Test: $(if ($httpOk) { 'SUCCES' } else { 'EȘEC' })`nStatus: $httpStatus"
if ($httpContent) {
    $httpResult += "`nConținut (primele 200 caractere): $httpContent"
}
Write-Host $httpResult
$allResults += $httpResult
$allResults += ""

# Test SSH
Write-Host "`n[TEST SSH] Conectare la server..." -ForegroundColor Yellow
$testOutput = Invoke-SSHCommand "echo 'SSH Connection Test OK'"

if ($testOutput -match "SSH Connection Test OK") {
    Write-Host "✓ Conexiune SSH reușită!" -ForegroundColor Green
    $allResults += "SSH Connection: SUCCES"
    $allResults += ""
    
    # 1. Status general
    Write-Host "`n[1] STATUS GENERAL SISTEM" -ForegroundColor Cyan
    $section = "[1] STATUS GENERAL SISTEM`n"
    
    $output = Invoke-SSHCommand "uptime"
    $section += "Uptime:`n$output`n"
    Write-Host $output
    
    $output = Invoke-SSHCommand "free -h"
    $section += "`nMemorie:`n$output`n"
    Write-Host "Memorie:"
    Write-Host $output
    
    $output = Invoke-SSHCommand "df -h"
    $section += "`nSpațiu disk:`n$output`n"
    Write-Host "Spațiu disk:"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 2. Procese Aleph
    Write-Host "`n[2] PROCESE ALEPH" -ForegroundColor Cyan
    $section = "[2] PROCESE ALEPH`n"
    
    $output = Invoke-SSHCommand "ps aux | grep -i aleph | grep -v grep"
    if ($output -and $output -notmatch "plink") {
        $section += "$output`n"
        Write-Host $output
    }
    else {
        $section += "Nu s-au găsit procese Aleph active!`n"
        Write-Host "Nu s-au găsit procese Aleph active!"
    }
    
    $allResults += $section
    $allResults += ""
    
    # 3. Port 8991
    Write-Host "`n[3] VERIFICARE PORT 8991" -ForegroundColor Cyan
    $section = "[3] VERIFICARE PORT 8991`n"
    
    $output = Invoke-SSHCommand "netstat -tuln | grep 8991 || ss -tuln | grep 8991"
    if ($output -and $output -notmatch "plink") {
        $section += "$output`n"
        Write-Host $output
    }
    else {
        $section += "Portul 8991 nu este în ascultare!`n"
        Write-Host "Portul 8991 nu este în ascultare!"
    }
    
    $allResults += $section
    $allResults += ""
    
    # 4. Servicii systemd
    Write-Host "`n[4] SERVICII SYSTEMD" -ForegroundColor Cyan
    $section = "[4] SERVICII SYSTEMD`n"
    
    $output = Invoke-SSHCommand "systemctl list-units --type=service --state=running 2>/dev/null | grep -i aleph || echo 'Nu s-au găsit servicii Aleph'"
    $section += "$output`n"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 5. Loguri sistem
    Write-Host "`n[5] LOGURI SISTEM RECENTE" -ForegroundColor Cyan
    $section = "[5] LOGURI SISTEM RECENTE (ultimele 50 linii)`n"
    
    $output = Invoke-SSHCommand "journalctl -n 50 --no-pager 2>/dev/null || tail -50 /var/log/messages 2>/dev/null || tail -50 /var/log/syslog 2>/dev/null || echo 'Nu s-au găsit loguri'"
    $section += "$output`n"
    Write-Host ($output.Substring(0, [Math]::Min(500, $output.Length)) + "...")
    
    $allResults += $section
    $allResults += ""
    
    # 6. Loguri kernel
    Write-Host "`n[6] LOGURI KERNEL (dmesg)" -ForegroundColor Cyan
    $section = "[6] LOGURI KERNEL (dmesg - ultimele 30 linii)`n"
    
    $output = Invoke-SSHCommand "dmesg | tail -30"
    $section += "$output`n"
    Write-Host ($output.Substring(0, [Math]::Min(500, $output.Length)) + "...")
    
    $allResults += $section
    $allResults += ""
    
    # 7. Erori în loguri
    Write-Host "`n[7] CĂUTARE ERORI ÎN LOGURI" -ForegroundColor Cyan
    $section = "[7] CĂUTARE ERORI ÎN LOGURI`n"
    
    $output = Invoke-SSHCommand "journalctl -p err -n 20 --no-pager 2>/dev/null || grep -i error /var/log/messages 2>/dev/null | tail -20 || echo 'Nu s-au găsit erori'"
    $section += "$output`n"
    Write-Host ($output.Substring(0, [Math]::Min(500, $output.Length)) + "...")
    
    $allResults += $section
    $allResults += ""
    
    # 8. Load average și CPU
    Write-Host "`n[8] LOAD AVERAGE ȘI CPU" -ForegroundColor Cyan
    $section = "[8] LOAD AVERAGE ȘI CPU`n"
    
    $output = Invoke-SSHCommand "top -bn1 | head -20"
    $section += "$output`n"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 9. Procese cu consum ridicat
    Write-Host "`n[9] PROCESE CU CONSUM RIDICAT" -ForegroundColor Cyan
    $section = "[9] PROCESE CU CONSUM RIDICAT`n"
    
    $output = Invoke-SSHCommand "ps aux --sort=-%mem | head -10"
    $section += "Top 10 procese după memorie:`n$output`n"
    Write-Host "Top 10 procese după memorie:"
    Write-Host $output
    
    $output = Invoke-SSHCommand "ps aux --sort=-%cpu | head -10"
    $section += "`nTop 10 procese după CPU:`n$output`n"
    Write-Host "Top 10 procese după CPU:"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 10. Cron jobs
    Write-Host "`n[10] TASK-URI PROGRAMATE (cron)" -ForegroundColor Cyan
    $section = "[10] TASK-URI PROGRAMATE (cron)`n"
    
    $output = Invoke-SSHCommand "crontab -l 2>/dev/null || echo 'Nu există cron jobs pentru root'"
    $section += "$output`n"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 11. Conexiuni rețea
    Write-Host "`n[11] CONEXIUNI REȚEA ACTIVE" -ForegroundColor Cyan
    $section = "[11] CONEXIUNI REȚEA ACTIVE`n"
    
    $output = Invoke-SSHCommand "netstat -tn | head -20 || ss -tn | head -20"
    $section += "$output`n"
    Write-Host ($output.Substring(0, [Math]::Min(500, $output.Length)) + "...")
    
    $allResults += $section
    $allResults += ""
    
    # 12. Fișiere log Aleph
    Write-Host "`n[12] CĂUTARE FIȘIERE LOG ALEPH" -ForegroundColor Cyan
    $section = "[12] CĂUTARE FIȘIERE LOG ALEPH`n"
    
    $output = Invoke-SSHCommand "find /var/log /opt /usr/local /home -name '*aleph*' -type f 2>/dev/null | head -20"
    if ($output -and $output -notmatch "plink") {
        $section += "Fișiere găsite:`n$output`n"
        Write-Host "Fișiere găsite:"
        Write-Host $output
    }
    else {
        $section += "Nu s-au găsit fișiere log Aleph`n"
        Write-Host "Nu s-au găsit fișiere log Aleph"
    }
    
    $allResults += $section
    $allResults += ""
    
    # 13. Istoric reboot-uri
    Write-Host "`n[13] ISTORIC REBOOT-URI" -ForegroundColor Cyan
    $section = "[13] ISTORIC REBOOT-URI`n"
    
    $output = Invoke-SSHCommand "last reboot | head -10"
    $section += "$output`n"
    Write-Host $output
    
    $output = Invoke-SSHCommand "who -b"
    $section += "`nUltimul boot:`n$output`n"
    Write-Host "Ultimul boot:"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
    
    # 14. Status swap
    Write-Host "`n[14] STATUS SWAP" -ForegroundColor Cyan
    $section = "[14] STATUS SWAP`n"
    
    $output = Invoke-SSHCommand "swapon --show"
    $section += "$output`n"
    Write-Host $output
    
    $output = Invoke-SSHCommand "cat /proc/swaps"
    $section += "$output`n"
    Write-Host $output
    
    $allResults += $section
    $allResults += ""
}
else {
    $errorMsg = "SSH Connection: EȘEC`nEroare: $testOutput`n"
    Write-Host "✗ Conexiune SSH eșuată: $testOutput" -ForegroundColor Red
    $allResults += $errorMsg
    $allResults += ""
}

# Salvare rezultate
$fullReport = $allResults -join "`n"
$reportFile = Save-Result "raport_verificare.txt" $fullReport

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Raport salvat în: $reportFile" -ForegroundColor Green
Write-Host "Verificare completă!" -ForegroundColor Green

