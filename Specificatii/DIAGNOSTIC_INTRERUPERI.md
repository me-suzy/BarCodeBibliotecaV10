# DIAGNOSTIC INTRERUPERI ALEPH - Pattern Identificat

## Pattern Observat

**Problema:** Aleph se oprește des în timpul zilei (înainte de 17:30), la fiecare 3-4 minute.

**Comportament:**
- ✅ **După 17:30** - Funcționează bine
- ❌ **În timpul zilei** - Se oprește la fiecare 3-4 minute
- ⚠️ **Frecvență** - Cam o dată la câteva zile

## Cauze Posibile

### 1. **Probleme de Memorie (OOM - Out of Memory)**
**Cel mai probabil cauză!**

**Semnale:**
- Serverul rămâne fără memorie în timpul zilei (mai mulți utilizatori)
- OOM killer oprește Aleph când memoria se epuizează
- După 17:30, când sunt mai puțini utilizatori, memoria este suficientă

**Verificare:**
```bash
# Verifică memorie
free -h

# Verifică dacă există OOM kills
dmesg | grep -i oom
journalctl | grep -i "out of memory"
```

### 2. **Procese care Consumă Resurse în Timpul Zilei**
**Posibil cauză**

**Semnale:**
- Alte procese consumă CPU/memorie în timpul zilei
- Cron jobs care rulează în timpul zilei
- Backup-uri sau task-uri programate

**Verificare:**
```bash
# Top procese după CPU
ps aux --sort=-%cpu | head -10

# Top procese după memorie
ps aux --sort=-%mem | head -10

# Cron jobs
crontab -l
```

### 3. **Load Average Ridicat**
**Posibil cauză**

**Semnale:**
- Serverul este suprasolicitat în timpul zilei
- Load average > 2.0 în timpul zilei
- După 17:30, load average scade

**Verificare:**
```bash
uptime
top
```

### 4. **Probleme de Licență Aleph**
**Posibil cauză**

**Semnale:**
- Aleph are limitări de licență bazate pe număr de utilizatori/timp
- În timpul zilei sunt prea mulți utilizatori simultani
- După 17:30, mai puțini utilizatori

**Verificare:**
- Verifică logurile Aleph pentru mesaje de licență
- Verifică configurația Aleph

### 5. **Probleme de Rețea sau Firewall**
**Mai puțin probabil**

**Semnale:**
- Prea multe conexiuni simultane în timpul zilei
- Firewall blochează conexiuni după un anumit număr

**Verificare:**
```bash
# Conexiuni active
netstat -tn | wc -l
ss -tn | wc -l
```

## Scripturi de Diagnosticare

### 1. `monitor_aleph.py`
**Monitorizare continuă** - Verifică statusul Aleph la fiecare 30 secunde și înregistrează când se oprește.

**Utilizare:**
```bash
python monitor_aleph.py
```

**Output:**
- Log în `monitor_aleph.log`
- Alerte când Aleph se oprește
- Timestamp pentru fiecare eveniment

### 2. `analiza_pattern.py`
**Analiză resurse** - Verifică resursele serverului și identifică diferențele între zi și seară.

**Utilizare:**
```bash
python analiza_pattern.py
```

**Verifică:**
- Uptime și load average
- Memorie disponibilă
- Procese Aleph
- Top procese după CPU/memorie
- Cron jobs
- Erori în loguri

## Plan de Acțiune

### Pasul 1: Monitorizare Continuă
Rulează `monitor_aleph.py` în timpul zilei pentru a confirma pattern-ul și a înregistra exact când se oprește.

### Pasul 2: Analiză Resurse
Rulează `analiza_pattern.py` în timpul zilei (când se oprește) și seara (când funcționează) pentru a compara resursele.

### Pasul 3: Verificare Loguri
Când Aleph se oprește, verifică:
```bash
# Loguri sistem
journalctl -p err -n 50

# Loguri kernel
dmesg | tail -50

# Loguri Aleph (dacă există)
find /var/log -name "*aleph*" -type f
```

### Pasul 4: Soluții Posibile

#### Dacă problema este memorie:
1. **Crește memoria swap**
2. **Reduce numărul de procese Aleph**
3. **Optimizează configurația Aleph**
4. **Adaugă mai multă memorie RAM**

#### Dacă problema este CPU:
1. **Optimizează procesele care consumă CPU**
2. **Oprește cron jobs inutile**
3. **Upgrade CPU**

#### Dacă problema este licență:
1. **Verifică limitările licenței Aleph**
2. **Contactează suportul Aleph**

## Următorii Pași

1. ✅ **Rulează `monitor_aleph.py`** în timpul zilei pentru a confirma pattern-ul
2. ✅ **Rulează `analiza_pattern.py`** când se oprește vs când funcționează
3. ✅ **Verifică logurile** pentru erori OOM sau alte probleme
4. ✅ **Compară resursele** între zi și seară

## Concluzie

Pattern-ul (se oprește în timpul zilei, funcționează seara) sugerează puternic o **problemă de resurse** (memorie sau CPU) cauzată de:
- Mai mulți utilizatori în timpul zilei
- Procese care consumă resurse în timpul zilei
- Cron jobs sau task-uri programate

**Cel mai probabil:** Problema de memorie (OOM) - serverul rămâne fără memorie în timpul zilei și OOM killer oprește Aleph.

