# Ghid: Activare Configuration Storage phpMyAdmin

## Problema
Mesajul "The phpMyAdmin configuration storage has been deactivated" apare când tabelele de configurare phpMyAdmin nu sunt create.

## Soluție

### Pasul 1: Rulează scriptul SQL

1. Deschide phpMyAdmin: `http://localhost/phpmyadmin`
2. Click pe tab-ul **"SQL"** (din meniul de sus)
3. Click pe butonul **"Choose File"** sau **"Import"**
4. Selectează fișierul: `creaza_tabele_phpmyadmin.sql`
5. Click pe **"Go"** sau **"Import"**

**SAU**

1. Deschide phpMyAdmin
2. Click pe tab-ul **"SQL"**
3. Copiază conținutul din `creaza_tabele_phpmyadmin.sql`
4. Lipește în câmpul SQL
5. Click pe **"Go"**

### Pasul 2: Verificare

După rularea scriptului:
1. Reîncarcă pagina phpMyAdmin (F5)
2. Mesajul roșu ar trebui să dispară
3. Funcțiile avansate vor fi disponibile:
   - Bookmarks (salvare interogări SQL)
   - Recent tables (tabele recente)
   - Favorite tables (tabele favorite)
   - Export templates (template-uri export)
   - Designer (designer de baze de date)

## Funcții activate

După crearea tabelelor, vei putea folosi:
- ✅ **Bookmarks**: Salvează interogări SQL frecvente
- ✅ **Recent**: Vezi tabelele accesate recent
- ✅ **Favorites**: Marchează tabele favorite
- ✅ **Export Templates**: Salvează template-uri pentru export
- ✅ **Designer**: Creează diagrame ER pentru baza de date
- ✅ **Tracking**: Urmărește modificările în tabele
- ✅ **User Preferences**: Salvează preferințele tale

## Notă

Aceste tabele sunt opționale. Dacă nu le creezi, phpMyAdmin funcționează normal, dar fără funcțiile avansate menționate mai sus.

## Verificare tabele create

După rularea scriptului, verifică că tabelele au fost create:
1. În phpMyAdmin, selectează baza de date `phpmyadmin` din sidebar
2. Ar trebui să vezi toate tabelele `pma__*` listate

