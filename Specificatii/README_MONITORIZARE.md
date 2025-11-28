# Monitorizare Automată Aleph cu Verificare Resurse

## Descriere

Scriptul `monitor_auto_verificare.py` monitorizează continuu Aleph și, când detectează că s-a oprit, verifică automat resursele serverului (memorie, procese, loguri, etc.).

## Utilizare

### Pornire Monitorizare

```bash
cd "Verificare Conexiune"
python monitor_auto_verificare.py
```

### Ce Face Scriptul

1. **Monitorizează Aleph** la fiecare 30 secunde
2. **Când Aleph se oprește:**
   - Detectează automat oprirea
   - Rulează verificări SSH pentru:
     - Uptime și load average
     - Memorie (total, folosită, disponibilă)
     - Procese Aleph (număr, top 10)
     - Port 8991 (dacă este în ascultare)
     - Top 15 procese după CPU
     - Top 15 procese după memorie
     - Procese telnet
     - OOM kills (Out of Memory)
     - Erori recente în loguri
     - Servicii systemd Aleph
     - Cron jobs
3. **Salvează diagnosticul** în folderul `diagnostice/` cu timestamp
4. **Înregistrează evenimentele** în `monitor_aleph.log`

## Fișiere Generate

### 1. `monitor_aleph.log`
Log continuu cu toate evenimentele:
- Când Aleph se oprește
- Când Aleph se repornește
- Când se rulează verificări automate

### 2. `diagnostice/diagnostic_YYYYMMDD_HHMMSS.txt`
Fișiere de diagnostic generate automat când Aleph se oprește:
- Conțin toate verificările de resurse
- Un fișier pentru fiecare oprire detectată
- Format: `diagnostic_20251120_183045.txt`

## Exemplu Output

```
================================================================================
MONITORIZARE AUTOMATA ALEPH CU VERIFICARE RESURSE
Server: YOUR-IP-or-http://WEBSITE:8991/F
Interval verificare: 30 secunde
Cand Aleph se opreste, se verifica automat resursele serverului
Apasa Ctrl+C pentru a opri
================================================================================

[2025-11-20 18:30:01] Monitorizare automata pornita
[18:30:01] OK - HTTP 200 (ora 18:30)
[18:30:31] OK - HTTP 200 (ora 18:30)
[18:31:01] ALEPH S-A OPRIT! Incepe verificare automata resurse...

[1] Verificare uptime si load average...
 18:31:25 up 107 days, 10:25,  0 users,  load average: 1.00, 1.01, 1.00

[2] Verificare memorie...
              total        used        free      shared  buff/cache   available
Mem:          2048        1800         100          50         148          48
Swap:         1024         800         224

Analiza: Total=2048MB, Folosita=1800MB, Disponibila=48MB
ATENTIE: Memorie disponibila foarte putina!

[3] Verificare procese Aleph...
Numar procese Aleph: 1327

[4] Verificare port 8991...
PORTUL 8991 NU ESTE IN ASCULTARE!

[5] Verificare top procese CPU...
...

Diagnostic salvat in: diagnostice/diagnostic_20251120_183101.txt
[18:31:01] ALEPH S-A OPRIT! Eroare: Remote end closed connection without response
```

## Analiză Diagnostic

După ce rulează scriptul, verifică fișierele din folderul `diagnostice/`:

1. **Caută OOM kills** - dacă există, problema este memorie
2. **Verifică memorie disponibilă** - dacă este < 100MB, problema este memorie
3. **Verifică numărul de procese Aleph** - dacă este > 1000, poate fi prea mult
4. **Verifică procese care consumă CPU/memorie** - identifică procese problematice

## Recomandări

### Pentru Monitorizare Continuă

Rulează scriptul în timpul zilei când știi că Aleph se oprește des:

```bash
# În timpul zilei (până la 17:00)
python monitor_auto_verificare.py
```

### Pentru Analiză

După ce rulează câteva ore, verifică:
1. **Log-ul** `monitor_aleph.log` pentru pattern-uri
2. **Fișierele de diagnostic** din `diagnostice/` pentru cauze
3. **Compară diagnosticele** între zi și seară

## Soluții Bazate pe Diagnostic

### Dacă Diagnosticul Arată OOM Kills:
- **Problema:** Memorie insuficientă
- **Soluție:** Crește memoria swap sau reduce procesele Aleph

### Dacă Diagnosticul Arată Memorie < 100MB:
- **Problema:** Memorie epuizată
- **Soluție:** Optimizează procesele sau adaugă RAM

### Dacă Diagnosticul Arată > 1000 Procese Aleph:
- **Problema:** Prea multe procese
- **Soluție:** Optimizează configurația Aleph

## Notă

Scriptul necesită:
- ✅ PuTTY instalat (pentru plink.exe)
- ✅ Host key SSH acceptat (rulează o dată manual pentru a accepta)
- ✅ Python 3.x

---

**Tip:** Rulează scriptul în timpul zilei când știi că Aleph se oprește des pentru a colecta diagnostice complete!

