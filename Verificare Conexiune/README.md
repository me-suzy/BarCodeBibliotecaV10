# Scripturi de Verificare Stabilitate Server

Acest folder conține scripturi pentru testarea și monitorizarea stabilității serverului Linux și a sistemului Aleph.

## Configurare

- **Server IP**: YOUR-IP-or-http://WEBSITE
- **SSH Port**: 22
- **SSH User**: root
- **SSH Password**: YOUR-SERVER-PASSWORD
- **Catalog URL**: YOUR-IP-or-http://WEBSITE:8991/F

## Scripturi Disponibile

### 1. `test_server_stability.py`
Script Python pentru verificare completă a serverului.

**Cerințe:**
- Python 3.x
- Paramiko (pentru SSH) sau plink.exe (PuTTY)

**Utilizare:**
```bash
python test_server_stability.py
```

**Funcționalități:**
- Test conexiune HTTP la catalog
- Test conexiune SSH
- Verificare status sistem (uptime, memorie, disk)
- Verificare procese Aleph
- Verificare port 8991
- Verificare servicii systemd
- Analiză loguri sistem și kernel
- Căutare erori în loguri
- Verificare load average și CPU
- Identificare procese cu consum ridicat
- Verificare cron jobs
- Verificare conexiuni rețea
- Căutare fișiere log Aleph
- Istoric reboot-uri
- Status swap

**Rezultate:**
- Salvează raport complet în `YYYYMMDD_HHMMSS_raport_verificare.txt`

### 2. `test_server_powershell.ps1`
Script PowerShell pentru verificare server.

**Cerințe:**
- PowerShell 5.0+
- plink.exe (PuTTY) pentru conexiuni SSH

**Utilizare:**
```powershell
.\test_server_powershell.ps1
```

**Funcționalități:**
- Aceleași verificări ca scriptul Python
- Interfață colorată pentru output
- Salvează raport în același format

### 3. `test_server_cmd.bat`
Script CMD/BAT pentru verificare server.

**Cerințe:**
- Windows CMD
- plink.exe (PuTTY) pentru conexiuni SSH
- curl.exe (opțional, pentru test HTTP)

**Utilizare:**
```cmd
test_server_cmd.bat
```

**Funcționalități:**
- Aceleași verificări ca celelalte scripturi
- Compatibil cu orice sistem Windows
- Salvează raport în același format

## Rezultate

Toate scripturile salvează rapoarte în același folder cu următorul format:
- `YYYYMMDD_HHMMSS_raport_verificare.txt`

Rapoartele conțin:
- Timestamp verificare
- Status conexiuni (HTTP și SSH)
- Status sistem (uptime, resurse)
- Status procese și servicii
- Loguri și erori
- Recomandări pentru diagnosticare

## Notă Importantă

**Aceste scripturi NU modifică nimic pe server!** Ele doar citesc informații și verifică statusul sistemului pentru diagnosticare.

## Diagnosticare Probleme

### Serverul se întrerupe frecvent

Verifică în raport:
1. **Uptime** - dacă este scurt, serverul se repornește des
2. **Loguri kernel (dmesg)** - căută erori hardware sau OOM (Out of Memory)
3. **Istoric reboot-uri** - când și cât de des se repornește
4. **Memorie și swap** - dacă se epuizează memoria
5. **Procese cu consum ridicat** - procese care consumă resurse excesive
6. **Erori în loguri** - erori sistem care pot cauza crash-uri

### Aleph nu răspunde

Verifică în raport:
1. **Procese Aleph** - dacă procesele Aleph rulează
2. **Port 8991** - dacă portul este în ascultare
3. **Servicii systemd** - dacă serviciul Aleph este activ
4. **Loguri Aleph** - erori specifice în logurile Aleph

### Diferențiere între probleme server vs Aleph

- **Dacă SSH nu funcționează** → Problemă server Linux
- **Dacă SSH funcționează dar port 8991 nu** → Problemă Aleph
- **Dacă procesele Aleph nu există** → Aleph s-a oprit
- **Dacă serverul are uptime scurt** → Serverul se repornește (problemă hardware/sistem)

## Utilizare Periodică

Pentru monitorizare continuă, rulează scripturile periodic:
- Zilnic pentru verificare generală
- La fiecare întrerupere pentru diagnosticare
- Înainte de deploy aplicație nouă pentru verificare stabilitate

## Suport

Pentru probleme sau întrebări, consultă rapoartele generate sau contactează administratorul sistemului.

