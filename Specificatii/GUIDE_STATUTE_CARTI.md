# 📚 Ghid Sistem Statute Cărți

## ✅ Ce Am Implementat

Sistemul de statuturi pentru cărți este acum complet funcțional! Acesta verifică dacă o carte poate fi împrumutată și aplică durata corectă de împrumut.

---

## 📋 Statuturi Configurate

| Cod | Nume Statut | Împrumut Acasă | Împrumut Sală | Durată (zile) | Descriere |
|-----|-------------|----------------|---------------|---------------|-----------|
| **01** | Pentru împrumut acasă | ✅ | ❌ | 14 | Se poate împrumuta acasă - durată standard |
| **02** | Se împr. numai la sală | ❌ | ✅ | 0 | Se imprumuta doar la sala de lectură |
| **03** | Colecții speciale - sală 1 zi | ❌ | ✅ | 1 | Colecții speciale - se imprumuta doar sala pentru 1 zi |
| **04** | Nu există fizic | ❌ | ❌ | 0 | Nu exista fizic cartea - nu se poate împrumuta |
| **05** | Împrumut scurt 5 zile | ✅ | ❌ | 5 | Se imprumuta doar 5 zile |
| **06** | Regim special 6 luni - 1 an | ✅ | ❌ | 180 | Regim special - se pot împrumuta 6 luni, maxim 1 an |
| **08** | Ne circulat | ❌ | ❌ | 0 | Nu se imprumuta - carte ne circulată |
| **90** | În achiziție - depozit | ❌ | ❌ | 0 | Cartea e încă în depozit, nu a ajuns la raft |

---

## 🔧 Instalare

### Pasul 1: Rulează Scriptul SQL

**Opțiunea 1: phpMyAdmin**
1. Deschide phpMyAdmin
2. Selectează baza `biblioteca`
3. Click pe tab-ul "SQL"
4. Copiază conținutul din `update_database_statute_carti.sql`
5. Click "Go"

**Opțiunea 2: Script PHP**
```bash
php instaleaza_statute_carti.php
```

**Opțiunea 3: MySQL Command Line**
```cmd
cd C:\xampp\mysql\bin
mysql.exe -u root biblioteca < update_database_statute_carti.sql
```

### Pasul 2: Verificare

După instalare, verifică că totul funcționează:

```sql
-- Verifică statutele
SELECT * FROM statute_carti ORDER BY cod_statut;

-- Verifică cărțile cu statut
SELECT statut, COUNT(*) as numar FROM carti GROUP BY statut;
```

---

## 🎯 Cum Funcționează

### 1. La Scanare Carte

Când un utilizator scanează o carte:

1. **Verificare statut carte:**
   - Sistemul verifică dacă cartea poate fi împrumutată acasă
   - Dacă statutul este `04`, `08`, sau `90` → **NU se poate împrumuta**
   - Dacă statutul este `02` sau `03` → **Doar la sală**

2. **Verificare limită utilizator:**
   - Sistemul verifică dacă utilizatorul nu a depășit limita de cărți
   - Limita depinde de statutul utilizatorului (ex: 4, 6, 10, 15, 20 cărți)

3. **Calculare durată împrumut:**
   - Durata se calculează din statutul cărții:
     - `01` → 14 zile
     - `05` → 5 zile
     - `06` → 180 zile (6 luni)
     - `03` → 1 zi (doar sală)

4. **Creare împrumut:**
   - Dacă toate verificările trec, se creează împrumutul cu durata corectă

### 2. În Email-uri

Email-urile de notificare includ acum:
- **Statutul cărții** (ex: "Se împr. numai la sală")
- **Durata de împrumut** (ex: "5 zile", "180 zile")

**Exemplu email:**
```
📖 Titlu Carte
👤 Autor: ...
🏷️ Statut: Se împr. numai la sală
⏱️ Durată împrumut: 1 zi
📅 Împrumutată: 15.01.2024
```

---

## 📝 Exemple de Utilizare

### Exemplu 1: Carte Normală (Statut 01)

**Scenariu:**
- Utilizator: `USER001` (statut 14, limită 4 cărți)
- Carte: `BOOK001` (statut 01)

**Rezultat:**
- ✅ Cartea poate fi împrumutată acasă
- ✅ Durată: 14 zile
- ✅ Se creează împrumutul

---

### Exemplu 2: Carte Doar Sală (Statut 02)

**Scenariu:**
- Utilizator: `USER001`
- Carte: `BOOK002` (statut 02)

**Rezultat:**
- ❌ Cartea nu poate fi împrumutată acasă
- ⚠️ Mesaj: "Cartea '...' nu poate fi împrumutată acasă. Statut: Se împr. numai la sală"
- ❌ Nu se creează împrumutul

---

### Exemplu 3: Carte Nu Există Fizic (Statut 04)

**Scenariu:**
- Utilizator: `USER001`
- Carte: `BOOK003` (statut 04)

**Rezultat:**
- ❌ Cartea nu poate fi împrumutată
- ⚠️ Mesaj: "Cartea '...' nu există fizic - nu se poate împrumuta!"
- ❌ Nu se creează împrumutul

---

### Exemplu 4: Împrumut Scurt (Statut 05)

**Scenariu:**
- Utilizator: `USER001`
- Carte: `BOOK004` (statut 05)

**Rezultat:**
- ✅ Cartea poate fi împrumutată acasă
- ✅ Durată: 5 zile (nu 14!)
- ✅ Se creează împrumutul cu scadența corectă

---

## 🔄 Actualizare Statut Carte

Pentru a actualiza statutul unei cărți:

```sql
-- Actualizează statutul unei cărți
UPDATE carti SET statut = '02' WHERE cod_bare = 'BOOK001';

-- Sau folosește funcția PHP
require_once 'functions_statute_carti.php';
actualizeazaStatutCarte($pdo, 'BOOK001', '02');
```

---

## 📊 Verificare în Aplicație

### La Scanare:

**Dacă cartea poate fi împrumutată:**
```
✅ Carte împrumutată: Titlu Carte
📅 Scadență: 2024-01-29 (14 zile)
🏷️ Statut carte: Pentru împrumut acasă
📚 Cărți împrumutate: 1/4
```

**Dacă cartea NU poate fi împrumutată:**
```
⚠️ NU SE POATE ÎMPRUMUTA!
Cartea 'Titlu Carte' nu poate fi împrumutată acasă. 
Statut: Se împr. numai la sală
```

---

## 🎉 Rezumat

✅ **Sistemul de statuturi cărți este complet funcțional!**

- ✅ Verifică dacă cartea poate fi împrumutată
- ✅ Aplică durata corectă de împrumut
- ✅ Blochează împrumuturile pentru cărți cu statut nepermis
- ✅ Include informații despre statut în email-uri
- ✅ Afișează mesaje clare pentru utilizatori

**Totul este pregătit pentru utilizare!** 🚀

