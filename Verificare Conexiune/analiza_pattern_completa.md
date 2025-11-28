# ANALIZA COMPLETĂ - Pattern Intreruperi Aleph

## 📊 Observații din Monitorizare

### Pattern Identificat:
- ✅ **După 17:00-17:30** - Funcționează relativ bine (ocazional se oprește)
- ❌ **În timpul zilei (până la 17:00)** - Se oprește la fiecare 3-4 minute
- ⚠️ **Frecvență:** Cam o dată la câteva zile (nu zilnic)

### Comportament Observat:
1. Aleph se oprește brusc ("Remote end closed connection")
2. Se repornește automat după 1-2 verificări (30-60 secunde)
3. Funcționează bine între opriți
4. Pattern-ul se repetă periodic

## 🔍 Cauze Posibile (după oprirea telnet)

### 1. **Probleme de Memorie (OOM) - CEL MAI PROBABIL**
**De ce:**
- În timpul zilei sunt mai mulți utilizatori → mai multă memorie folosită
- Când memoria se epuizează, OOM killer oprește procese (inclusiv Aleph)
- Aleph se repornește automat (probabil prin systemd sau cron)

**Verificare:**
```bash
# Când Aleph se oprește, verifică:
free -m
dmesg | grep -i oom
```

### 2. **Procese Aleph care Consumă Resurse**
**De ce:**
- 1327 procese Aleph active (foarte multe!)
- Unele procese pot consuma resurse excesive
- Când se acumulează, sistemul oprește Aleph

**Verificare:**
```bash
ps aux | grep aleph | wc -l
ps aux --sort=-%mem | grep aleph | head -10
```

### 3. **Probleme de Configurare Aleph**
**De ce:**
- Aleph se repornește automat → probabil există un serviciu systemd sau cron
- Configurația poate avea limitări de memorie/procese
- Timeout-uri sau limitări de conexiuni

**Verificare:**
```bash
systemctl status aleph
systemctl list-units | grep aleph
crontab -l | grep aleph
```

### 4. **Probleme de Rețea sau Firewall**
**De ce:**
- "Remote end closed connection" sugerează o problemă de conexiune
- Poate există limitări de conexiuni simultane
- Firewall sau iptables pot bloca conexiuni după un anumit număr

**Verificare:**
```bash
netstat -tn | wc -l
ss -tn | wc -l
iptables -L -n
```

### 5. **Procese Oracle care Consumă Resurse**
**De ce:**
- Aleph folosește Oracle ca bază de date
- Oracle poate consuma resurse excesive
- Când Oracle este suprasolicitat, Aleph se oprește

**Verificare:**
```bash
ps aux | grep oracle
ps aux --sort=-%mem | grep oracle | head -10
```

## 🔧 Plan de Acțiune

### Pasul 1: Verificare Imediată Când Se Oprește
Rulează `verifica_cand_se_opreste.py` imediat după ce Aleph se oprește pentru a vedea exact ce se întâmplă.

### Pasul 2: Verificare Memorie
Când Aleph se oprește, verifică:
- Memorie disponibilă
- Swap folosit
- OOM kills în loguri

### Pasul 3: Verificare Procese Aleph
- Număr de procese Aleph
- Procese care consumă resurse
- Procese zombie sau blocate

### Pasul 4: Verificare Configurație
- Servicii systemd pentru Aleph
- Cron jobs care repornește Aleph
- Limitări de configurare

## 📋 Soluții Posibile

### Dacă problema este memorie:
1. **Crește memoria swap**
2. **Reduce numărul de procese Aleph** (1327 pare mult)
3. **Optimizează configurația Aleph**
4. **Adaugă mai multă RAM**

### Dacă problema este procese:
1. **Oprește procese Aleph zombie**
2. **Optimizează numărul de procese Aleph**
3. **Configurare limitări pentru procese Aleph**

### Dacă problema este configurație:
1. **Verifică serviciile systemd**
2. **Verifică cron jobs**
3. **Optimizează timeout-uri și limitări**

## 🎯 Următorii Pași

1. ✅ **Rulează `verifica_cand_se_opreste.py`** când Aleph se oprește
2. ✅ **Verifică memorie** când se oprește
3. ✅ **Verifică procese Aleph** (1327 procese pare mult)
4. ✅ **Verifică logurile** pentru OOM kills sau alte erori

---

**Notă:** După oprirea procesului telnet, problema persistă, deci există o altă cauză. Cel mai probabil este o problemă de memorie sau de configurare Aleph.

