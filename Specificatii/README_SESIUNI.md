# Sistem de Gestionare Împrumuturi cu Sesiuni

## Instalare și Configurare

### 1. Actualizare Baza de Date

Rulează scriptul SQL pentru a adăuga câmpurile necesare:

```sql
-- Rulează în phpMyAdmin sau MySQL Workbench
source update_database_sesiuni.sql;
```

Sau copiază și rulează manual conținutul din `update_database_sesiuni.sql`.

### 2. Configurare Cron Job pentru Notificări Email

Pentru a trimite notificări email zilnic pentru întârzieri, adaugă următorul cron job:

```bash
# Rulează zilnic la ora 9:00 AM
0 9 * * * /usr/bin/php /calea/catre/proiect/cron_notificari_intarzieri.php
```

Sau folosește un serviciu de cron online pentru a rula scriptul zilnic.

### 3. Funcționalități Implementate

#### Căutare fără utilizator scanat
- Dacă se scanează o carte fără să fie scanat utilizatorul, sistemul doar afișează dacă cartea există sau nu în baza de date
- Nu se procesează împrumuturi sau returnări

#### Sesiuni utilizatori
- Utilizatorul este scanat (cod USER***)
- Timp de 30 secunde după scanare poate scana cărți
- Între fiecare carte scanată, maxim 30 secunde
- Dacă trec 30 secunde fără să scaneze o carte, sesiunea se închide
- Sesiunea utilizatorului durează 5 minute total
- Dacă utilizatorul revine după 5 minute, se creează o sesiune nouă

#### Limitări împrumuturi
- Maxim 6 cărți per utilizator
- Fiecare carte se împrumută pentru 14 zile (2 săptămâni)
- Dacă depășește limita de 6 cărți, se afișează mesaj de atenționare și nu se adaugă în baza de date

#### Returnări
- Utilizatorul se scanează din nou
- Dacă scanează o carte deja împrumutată de el, se returnează automat
- Poate reveni de mai multe ori într-o zi să împrumute sau să returneze

#### Blocare utilizatori
- Utilizatorii cu cărți peste 14 zile întârziere sunt blocați automat
- Câmp `blocat` în tabelul `cititori` (0=activ, 1=blocat)
- Câmp `motiv_blocare` pentru motivul blocării

#### Notificări email
- Utilizatorii cu cărți întârziate primesc email de notificare
- Email trimis de la: YOUR-EMAIL@gmail.com
- Scriptul `cron_notificari_intarzieri.php` trebuie rulat zilnic

## Structura Bazei de Date

### Tabel `cititori` - Câmpuri adăugate:
- `blocat` TINYINT(1) DEFAULT 0 - Status blocare
- `motiv_blocare` VARCHAR(255) - Motivul blocării

### Tabel `imprumuturi` - Câmpuri adăugate:
- `data_scadenta` DATE - Data scadenței împrumutului (14 zile de la data_imprumut)

### Tabel `sesiuni_utilizatori` - Nou:
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `cod_cititor` VARCHAR(50) NOT NULL
- `timestamp_start` TIMESTAMP - Momentul când utilizatorul a fost scanat
- `timestamp_ultima_actiune` TIMESTAMP - Momentul ultimei acțiuni
- `status` ENUM('activ', 'expirat', 'inchis') - Statusul sesiunii
- `numar_carti_scanate` INT DEFAULT 0 - Numărul de cărți scanate

## Fișiere Modificate/Create

1. **scanare_rapida.php** - Logica principală de scanare și împrumuturi
2. **functions_sesiuni.php** - Funcții helper pentru gestionarea sesiunilor
3. **update_database_sesiuni.sql** - Script SQL pentru actualizarea bazei de date
4. **cron_notificari_intarzieri.php** - Script pentru notificări email

## Testare

1. Scanează o carte fără utilizator - ar trebui să afișeze doar informații despre carte
2. Scanează utilizatorul (USER001) - ar trebui să creeze sesiune
3. Scanează o carte în termen de 30 secunde - ar trebui să proceseze împrumutul
4. Așteaptă 30+ secunde și scanează altă carte - ar trebui să afișeze că sesiunea a expirat
5. Scanează utilizatorul din nou - ar trebui să creeze sesiune nouă
6. Încearcă să împrumute mai mult de 6 cărți - ar trebui să afișeze mesaj de limită depășită
7. Scanează o carte deja împrumutată de același utilizator - ar trebui să o returneze automat

