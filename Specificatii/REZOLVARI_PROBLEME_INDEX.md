# Rezolvări Probleme - index.php (Fereastra Cititor Necunoscut)

**Data:** 27 Noiembrie 2025  
**Fișier afectat:** `index.php`

---

## 📋 Sumar Probleme Rezolvate

### 1. Eroare de sintaxă PHP - `unexpected token "endif"`

**Simptome:**
```
Parse error: syntax error, unexpected token "endif", expecting end of file in index.php on line 1595
```

**Cauza:**
- Exista un `<?php endif; ?>` și un `</div>` în plus în structura HTML/PHP, fără un `if` corespunzător.

**Rezolvare:**
- Am eliminat `endif`-ul și `</div>`-ul redundante din jurul secțiunii `cititor_necunoscut`.

---

### 2. Afișare simultană a două ferestre (cititor activ + cititor necunoscut)

**Simptome:**
- Când se scana un utilizator existent, apoi unul inexistent, apăreau ambele ferestre simultan.

**Cauza:**
- Variabilele de sesiune nu erau șterse corect înainte de a seta altele noi.

**Rezolvare:**
- Am adăugat `unset()` pentru toate variabilele de sesiune relevante înainte de a seta noi valori:

```php
// Când se găsește un cititor activ, se șterge cititor_necunoscut
unset($_SESSION['cititor_necunoscut']);
unset($_SESSION['cititor_necunoscut_statut']);
unset($_SESSION['cititor_necunoscut_nume_statut']);
unset($_SESSION['cititor_necunoscut_limita']);

// Când se setează cititor_necunoscut, se șterge cititor_activ
unset($_SESSION['cititor_activ']);
unset($_SESSION['carte_scanata']);
unset($_SESSION['carte_scanata_pentru_anulare']);
```

---

### 3. ⭐ PROBLEMA PRINCIPALĂ: Fereastra pentru cititor necunoscut dispărea instant

**Simptome:**
- La scanarea unui cod de utilizator inexistent, fereastra apărea pentru o fracțiune de secundă, apoi dispărea imediat.
- Problema apărea pe toate browserele (Chrome, Firefox, Edge, etc.).
- La CTRL+SHIFT+R (hard refresh), fereastra apărea scurt înainte să dispară.

**Cauza:**
- Funcția JavaScript `verificaContainerGol()` verifica doar 3 elemente:
  - `alert-message`
  - `cititor-activ-box`
  - `carte-scanata-box`
  
- **NU verifica** `cititor-necunoscut-box`!

- Această funcție era apelată la `window.addEventListener('load', ...)`, deci imediat ce pagina se încărca, verifica dacă containerul era "gol". Pentru că nu găsea `cititor-necunoscut-box` în lista de elemente verificate, considera că containerul era gol și îl ascundea cu `container.style.display = 'none'`.

**Codul problematic:**
```javascript
function verificaContainerGol() {
    const container = document.getElementById('info-container');
    const alertMsg = document.getElementById('alert-message');
    const cititorBox = document.getElementById('cititor-activ-box');
    const carteBox = document.getElementById('carte-scanata-box');
    // ❌ LIPSEA: cititor-necunoscut-box
    
    if (container && !alertMsg && !cititorBox && !carteBox) {
        container.style.display = 'none'; // Ascundea containerul!
    }
}
```

**Rezolvare:**
```javascript
function verificaContainerGol() {
    const container = document.getElementById('info-container');
    const alertMsg = document.getElementById('alert-message');
    const cititorBox = document.getElementById('cititor-activ-box');
    const carteBox = document.getElementById('carte-scanata-box');
    const cititorNecunoscutBox = document.getElementById('cititor-necunoscut-box'); // ✅ ADĂUGAT
    
    // Ascunde containerul DOAR dacă nu există NICIUNUL dintre elementele posibile
    if (container && !alertMsg && !cititorBox && !carteBox && !cititorNecunoscutBox) {
        container.style.display = 'none';
    }
}
```

---

## 📍 Locația modificărilor în `index.php`

| Linie (aprox.) | Modificare |
|----------------|------------|
| ~308-325 | Adăugat `unset()` pentru `cititor_necunoscut_*` când se găsește cititor activ |
| ~326-360 | Adăugat `unset()` pentru `cititor_activ` și `cititor_necunoscut_*` când se setează cititor necunoscut |
| ~815-845 | Adăugat `unset()` în fallback pentru coduri neprocesate |
| ~1927-1940 | Corectat funcția `verificaContainerGol()` pentru a include `cititor-necunoscut-box` |

---

## 🧪 Cum se testează

1. Accesează `http://localhost/index.php`
2. Resetează sesiunea: `http://localhost/index.php?actiune=reseteaza_cititor`
3. Scanează un cod de utilizator inexistent (ex: `USER0120`, `USER9999`, `160000000099`)
4. **Rezultat așteptat:** Fereastra portocalie "⚠️ Cititor necunoscut" apare și rămâne vizibilă cu:
   - Codul scanat
   - Statutul detectat
   - Limita de cărți
   - Butonul "Adaugă cititor nou"

---

## 📚 Lecții învățate

1. **Verifică toate elementele posibile** - Când adaugi un nou tip de element în interfață, asigură-te că toate funcțiile JavaScript care verifică existența elementelor sunt actualizate.

2. **Debugging JavaScript** - Când o fereastră dispare instant, verifică funcțiile care rulează la `load` sau `DOMContentLoaded`.

3. **Gestionarea sesiunilor** - Când ai mai multe stări posibile (cititor activ, cititor necunoscut, carte scanată), asigură-te că ștergi stările anterioare înainte de a seta altele noi.

---

*Document creat pentru referință viitoare.*

