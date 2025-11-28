# 🔧 Soluții pentru MySQL care se oprește în XAMPP

## Probleme comune și soluții

### 1. Portul 3306 este ocupat

**Verificare:**
```cmd
netstat -ano | findstr :3306
```

**Soluție:**
- Dacă apare un PID, închide procesul:
  ```cmd
  taskkill /PID [PID_NUMBER] /F
  ```
- Sau schimbă portul în `C:\xampp\mysql\bin\my.ini`:
  ```
  port = 3307
  ```

### 2. Fișiere MySQL corupte

**Soluție:**
1. Oprește MySQL din XAMPP Control Panel
2. Șterge sau redenumește `C:\xampp\mysql\data\mysql_error.log`
3. Pornește din nou MySQL

### 3. Permisiuni insuficiente

**Soluție:**
- Rulează XAMPP Control Panel ca Administrator:
  - Click dreapta pe XAMPP Control Panel
  - Selectează "Run as administrator"

### 4. Configurație greșită în my.ini

**Verificare:**
- Deschide `C:\xampp\mysql\bin\my.ini`
- Verifică că `datadir` indică către un director existent:
  ```
  datadir="C:/xampp/mysql/data"
  ```

### 5. Serviciu Windows MySQL conflict

**Verificare:**
```cmd
sc query MySQL
```

**Soluție:**
- Dacă există un serviciu MySQL Windows, dezactivează-l:
  ```cmd
  sc stop MySQL
  sc config MySQL start= disabled
  ```

### 6. Reinstalare MySQL (ultimă soluție)

1. **Backup baza de date:**
   - Exportă toate bazele de date din phpMyAdmin
   - Sau copiază `C:\xampp\mysql\data\`

2. **Dezinstalează MySQL:**
   - Oprește MySQL din XAMPP
   - Șterge `C:\xampp\mysql\` (sau doar `data\`)

3. **Reinstalează:**
   - Reinstalează XAMPP complet
   - Sau doar MySQL din XAMPP

## Verificare rapidă

Rulează scriptul: `http://localhost/verifica_mysql_xampp.php`

Acesta va afișa:
- Status port 3306
- Procese MySQL active
- Log-uri cu erori
- Configurație MySQL

## Contact

Dacă niciuna dintre soluții nu funcționează, verifică log-urile MySQL:
- `C:\xampp\mysql\data\*.err`
- XAMPP Control Panel → MySQL → Logs

