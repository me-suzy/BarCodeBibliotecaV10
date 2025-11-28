# 📦 Instalare Sistem Backup Automat Complet

Acest sistem creează backup-uri automate complete în fiecare dimineață la ora **05:30**:
- ✅ **Baza de date MySQL** (fișier SQL)
- ✅ **Toate fișierele din proiect** (arhivă ZIP cu data zilei)

## 📋 Pași de Instalare

### 1. Verificare Fișiere

Asigură-te că există următoarele fișiere:
- ✅ `backup_database.php` - Scriptul principal de backup
- ✅ `run_backup.bat` - Script batch pentru Task Scheduler
- ✅ Folder `BackUp\` - Folderul pentru backup-uri

### 2. Configurare Task Scheduler Windows

#### Pasul 1: Deschide Task Scheduler
1. Apasă `Win + R`
2. Tastează `taskschd.msc` și apasă Enter
3. Sau caută "Task Scheduler" în meniul Start

#### Pasul 2: Creează Task Nou
1. Click pe **"Create Basic Task..."** în panoul din dreapta
2. Nume: `Backup Biblioteca Zilnic`
3. Descriere: `Backup automat baza de date biblioteca la 05:30 zilnic`
4. Click **Next**

#### Pasul 3: Configurează Trigger-ul
1. Selectează **"Daily"** (Zilnic)
2. Click **Next**
3. Setează ora: **05:30**
4. Repetare: **Every 1 days**
5. Click **Next**

#### Pasul 4: Configurează Acțiunea
1. Selectează **"Start a program"**
2. Click **Next**
3. **Program/script**: Click **Browse** și selectează:
   ```
   C:\xampp\php\php.exe
   ```
   (sau calea către PHP dacă este instalat altundeva)
4. **Add arguments (optional)**: Introdu:
   ```
   "e:\Carte\BB\17 - Site Leadership\alte\Ionel Balauta\Aryeht\Task 1 - Traduce tot site-ul\Doar Google Web\Andreea\Meditatii\2023\BarCode Biblioteca\backup_database.php"
   ```
   (cu ghilimele pentru că calea conține spații)
5. **Start in (optional)**: Lasă gol sau setează la:
   ```
   e:\Carte\BB\17 - Site Leadership\alte\Ionel Balauta\Aryeht\Task 1 - Traduce tot site-ul\Doar Google Web\Andreea\Meditatii\2023\BarCode Biblioteca
   ```
6. Click **Next**

#### Pasul 5: Finalizează
1. Bifează **"Open the Properties dialog for this task when I click Finish"**
2. Click **Finish**

#### Pasul 6: Configurează Proprietățile Avansate
1. În tab-ul **General**:
   - ✅ Bifează **"Run whether user is logged on or not"**
   - ✅ Bifează **"Run with highest privileges"**
   - **Configure for**: Selectează versiunea ta de Windows

2. În tab-ul **Conditions**:
   - ✅ Debifează **"Start the task only if the computer is on AC power"** (dacă vrei să ruleze și pe baterie)
   - ✅ Bifează **"Wake the computer to run this task"** (opțional)

3. În tab-ul **Settings**:
   - ✅ Bifează **"Allow task to be run on demand"**
   - ✅ Bifează **"Run task as soon as possible after a scheduled start is missed"**
   - ✅ Bifează **"If the task fails, restart every"** - setează la **10 minutes**
   - **Stop the task if it runs longer than**: Lasă gol sau setează la **1 hour**

4. Click **OK**
5. Introduce parola utilizatorului dacă este necesar

### 3. Testare Manuală

#### Test 1: Rulează backup-ul manual
1. Deschide **Task Scheduler**
2. Găsește task-ul **"Backup Biblioteca Zilnic"**
3. Click dreapta → **Run**
4. Verifică folderul `BackUp\` - ar trebui să apară un fișier `.sql` sau `.sql.gz`

#### Test 2: Verifică log-ul
1. Deschide `BackUp\backup_log.txt`
2. Ar trebui să vezi mesaje de succes

#### Test 3: Rulează direct scriptul
1. Deschide CMD
2. Navighează la folderul proiectului
3. Rulează: `php backup_database.php`
4. Verifică dacă apare eroare

### 4. Verificare Cale mysqldump

Dacă backup-ul eșuează, verifică calea către `mysqldump.exe`:

1. Deschide `backup_database.php`
2. Verifică array-ul `$mysqldump_paths`
3. Dacă XAMPP este instalat altundeva, adaugă calea corectă

Căi comune:
- `C:\xampp\mysql\bin\mysqldump.exe`
- `C:\Program Files\xampp\mysql\bin\mysqldump.exe`

## 📊 Structura Backup-urilor

### Fișiere Create:
- `backup_biblioteca_YYYY-MM-DD_HH-MM-SS.sql` - Backup baza de date (necomprimat)
- `backup_complet_YYYY-MM-DD.zip` - **Arhivă ZIP cu TOATE fișierele** (inclusiv backup-ul SQL în folderul `database/`)
- `backup_log.txt` - Log cu toate backup-urile

**Format nume ZIP:** `backup_complet_2024-01-15.zip` (cu data zilei)

### Retenție:
- **Backup-urile vechi** (mai vechi de 30 zile) sunt șterse automat
- Poți modifica numărul de zile în funcția `cleanOldBackups()` din `backup_database.php`

## 🔧 Troubleshooting

### Problema: Backup-ul nu rulează
**Soluție:**
1. Verifică dacă Task Scheduler rulează (serviciul Windows)
2. Verifică log-urile Task Scheduler: Task Scheduler → Task Scheduler Library → Backup Biblioteca Zilnic → History
3. Verifică `BackUp\backup_log.txt` pentru erori

### Problema: "mysqldump nu a fost găsit"
**Soluție:**
1. Verifică calea către XAMPP în `backup_database.php`
2. Adaugă calea corectă în array-ul `$mysqldump_paths`

### Problema: "Access denied" la MySQL
**Soluție:**
1. Verifică `config.php` - username și parola MySQL
2. Asigură-te că utilizatorul `root` are permisiuni de backup

### Problema: Backup-ul este gol
**Soluție:**
1. Verifică dacă baza de date `biblioteca` există
2. Verifică dacă există date în baza de date
3. Verifică log-ul pentru erori

## 📝 Notițe

- **Backup baza de date:**
  - Encoding **UTF-8**
  - Include toate tabelele, procedurile stocate și trigger-ele
  
- **Backup fișiere (ZIP):**
  - Include **TOATE fișierele** din folderul proiectului
  - Exclude automat: `BackUp`, `node_modules`, `.git`, `__pycache__`, fișiere `.log`, `.tmp`, `.cache`
  - Backup-ul SQL este inclus în arhivă în folderul `database/`
  - Numele arhivei: `backup_complet_YYYY-MM-DD.zip` (cu data zilei)
  
- **Retenție:**
  - Backup-urile vechi (mai vechi de 30 zile) sunt șterse automat
  - Log-ul păstrează istoricul tuturor backup-urilor

## ✅ Verificare Finală

După instalare, verifică:
1. ✅ Task-ul apare în Task Scheduler
2. ✅ Poți rula task-ul manual (Run)
3. ✅ Apare fișier de backup în folderul `BackUp\`
4. ✅ Log-ul conține mesaje de succes

---

**Dezvoltat pentru:** Biblioteca Academiei Române - Iași  
**Dezvoltare web:** Neculai Ioan Fantanaru

