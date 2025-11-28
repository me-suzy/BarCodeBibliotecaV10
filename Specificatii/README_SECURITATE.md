# 🔒 Securitate - Ghid Rapid

## Fișiere Create

1. **`.htaccess`** - Protecție Apache (blochează accesul la fișiere sensibile)
2. **`config_security.php`** - Setări centralizate de securitate
3. **`verificare_securitate.php`** - Script de audit securitate
4. **`SECURITATE.md`** - Documentație completă

## Verificare Rapidă

### 1. Testează că .htaccess funcționează

Încearcă să accesezi direct:
- ❌ `http://localhost/config.php` - Ar trebui să fie blocat
- ❌ `http://localhost/config_security.php` - Ar trebui să fie blocat
- ❌ `http://localhost/setup.php` - Ar trebui să fie blocat

### 2. Rulează Audit de Securitate

Accesează:
```
http://localhost/verificare_securitate.php?token=SECURITY_AUDIT_YYYYMMDD
```

Înlocuiește `YYYYMMDD` cu data de azi (ex: `SECURITY_AUDIT_20250115`)

### 3. Verifică Headers de Securitate

Deschide Developer Tools (F12) → Network → Verifică că headers-urile sunt setate:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`

## Ce Protejează

✅ **SQL Injection** - PDO cu prepared statements  
✅ **XSS (Cross-Site Scripting)** - Sanitizare input + htmlspecialchars  
✅ **CSRF** - Token-uri CSRF pentru formulare  
✅ **Clickjacking** - X-Frame-Options header  
✅ **Path Traversal** - Validare și blocare în .htaccess  
✅ **Directory Listing** - Options -Indexes  
✅ **Acces la Fișiere Sensibile** - Blocare prin .htaccess  
✅ **Rate Limiting** - Protecție DDoS/brute force  

## Pentru Server (Production)

1. Schimbă în `config_security.php`:
   ```php
   define('APP_MODE', 'production');
   ```

2. Creează utilizator MySQL dedicat (vezi `SECURITATE.md`)

3. Activează HTTPS și setează:
   ```php
   ini_set('session.cookie_secure', '1');
   ```

4. Șterge sau protejează `verificare_securitate.php`

## Documentație Completă

Vezi `SECURITATE.md` pentru detalii complete.

