# DIAGNOSTIC COMPLET - Server Linux și Aleph

**Data verificare:** 2025-11-20  
**Server IP:** YOUR-IP-or-http://WEBSITE

## 1. PROBLEMA IDENTIFICATĂ: Portul 80 nu funcționează

### Status Porturi (verificat din Windows):
- ✅ **Port 22 (SSH):** DESCHIS - SSH funcționează
- ❌ **Port 80 (HTTP):** INCHIS - **DE ACEEA YOUR-IP-or-http://WEBSITE/ NU FUNCȚIONEAZĂ**
- ❌ **Port 443 (HTTPS):** INCHIS
- ✅ **Port 8991 (Aleph):** DESCHIS - **ALEPH FUNCȚIONEAZĂ**
- ❌ **Port 8080:** INCHIS
- ❌ **Port 8000:** INCHIS

### Explicație:
**Portul 80 este închis pentru că nu există niciun serviciu web (Apache/Nginx/IIS) care să asculte pe el.**

Când accesezi `YOUR-IP-or-http://WEBSITE/` (fără port), browserul încearcă să se conecteze la portul 80 (portul HTTP implicit). Dacă portul 80 este închis, primești eroarea:
```
ERR_CONNECTION_REFUSED
This site can't be reached
YOUR-IP-or-http://WEBSITE refused to connect.
```

**Aceasta este o comportare NORMALĂ** dacă:
- Nu ai instalat un server web (Apache/Nginx)
- Nu ai nevoie de portul 80
- Folosești doar Aleph pe portul 8991

### Soluție:
**NU TREBUIE să funcționeze portul 80** dacă folosești doar Aleph pe 8991. Eroarea este normală și nu indică o problemă.

---

## 2. PROBLEMA: Aleph se întrerupe des

### Status Actual:
- ✅ Portul 8991 este DESCHIS
- ✅ HTTP la `YOUR-IP-or-http://WEBSITE:8991/F/` funcționează (HTTP 200)
- ⚠️ **Aleph se întrerupe des** - necesită investigare

### Cauze Posibile pentru Întreruperi:

#### A. Problemă Server Linux (întregul server se repornește):
**Semnale:**
- SSH nu funcționează când Aleph nu funcționează
- Uptime scurt (serverul se repornește des)
- Erori în loguri kernel (dmesg)

**Cauze posibile:**
1. **Out of Memory (OOM)** - serverul rămâne fără memorie și se repornește
2. **Probleme hardware** - overheating, alimentare, RAM defect
3. **Kernel panic** - erori critice în sistem
4. **Configurare greșită** - servicii care consumă resurse excesive

#### B. Problemă Doar Aleph (serverul funcționează, dar Aleph se oprește):
**Semnale:**
- SSH funcționează când Aleph nu funcționează
- Portul 8991 nu este în ascultare
- Procesele Aleph nu rulează

**Cauze posibile:**
1. **Aleph crash** - erori în aplicația Aleph
2. **Memorie insuficientă** - Aleph este oprit de sistem (OOM killer)
3. **Probleme de configurare** - Aleph nu pornește corect
4. **Dependențe lipsă** - biblioteci sau servicii necesare nu sunt disponibile
5. **Probleme de licență** - Aleph se oprește din cauza licenței

---

## 3. VERIFICĂRI NECESARE (cu SSH)

Pentru a identifica exact cauza întreruperilor, rulează scripturile de verificare:

### Scripturi disponibile:
1. **test_simple.py** - Verificare completă server
2. **test_porturi.py** - Verificare porturi și servicii
3. **test_http_direct.py** - Test direct HTTP și porturi

### Ce să verifici:

#### Dacă ai acces SSH (cu plink/PuTTY):

```bash
# 1. Verifică uptime (dacă este scurt, serverul se repornește)
uptime

# 2. Verifică memorie (dacă este plină, poate cauza OOM)
free -h

# 3. Verifică procese Aleph
ps aux | grep -i aleph | grep -v grep

# 4. Verifică portul 8991
netstat -tuln | grep 8991

# 5. Verifică erori recente în kernel
dmesg | tail -30 | grep -i 'error\|fail\|oom\|kill'

# 6. Verifică loguri sistem cu erori
journalctl -p err -n 20 --no-pager

# 7. Verifică istoric reboot-uri
last reboot | head -10

# 8. Verifică procese care consumă resurse
ps aux --sort=-%mem | head -10
ps aux --sort=-%cpu | head -10
```

---

## 4. RECOMANDĂRI

### Pentru a determina dacă problema este serverul sau doar Aleph:

1. **Când Aleph se întrerupe, verifică:**
   - Poți accesa SSH? → Dacă DA, problema este doar Aleph
   - Poți accesa SSH? → Dacă NU, problema este serverul Linux

2. **Verifică uptime:**
   - Dacă uptime este scurt (< 1 zi), serverul se repornește des
   - Dacă uptime este lung (> 7 zile), problema este doar Aleph

3. **Verifică memorie:**
   - Dacă memoria este aproape plină, poate cauza OOM
   - Dacă swap este folosit intens, problema este memorie

### Pentru a preveni întreruperile:

1. **Monitorizare continuă:**
   - Rulează scripturile de verificare zilnic
   - Verifică logurile pentru erori

2. **Optimizare resurse:**
   - Asigură-te că serverul are suficientă memorie
   - Verifică procesele care consumă resurse excesive

3. **Configurare Aleph:**
   - Verifică dacă Aleph are suficiente resurse alocate
   - Verifică dacă există limitări de memorie pentru Aleph

---

## 5. CONCLUZIE

### Portul 80:
✅ **NORMAL** - Nu trebuie să funcționeze dacă nu ai server web instalat.  
✅ **NU este o problemă** - Eroarea `ERR_CONNECTION_REFUSED` este așteptată.

### Aleph se întrerupe:
⚠️ **NECESITĂ INVESTIGARE** - Trebuie să verifici:
- Dacă serverul se repornește (uptime scurt)
- Dacă există probleme de memorie (OOM)
- Dacă există erori în loguri
- Dacă procesele Aleph se opresc sau serverul se repornește

### Următorii pași:
1. Instalează PuTTY (pentru plink.exe) sau configurează SSH
2. Rulează scripturile de verificare pentru diagnostic complet
3. Analizează rapoartele generate pentru a identifica cauza exactă

---

**Notă:** Toate scripturile sunt salvate în folderul `Verificare Conexiune` și pot fi rulate oricând pentru verificare periodică.

