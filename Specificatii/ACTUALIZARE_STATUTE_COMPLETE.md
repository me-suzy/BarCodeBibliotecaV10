# 🔄 Actualizare Sistem Statute - Toate Tipurile

## ⚠️ Situația Actuală

**Am implementat doar 7 statute (11-17), dar ar trebui să fie ~20!**

### Statute Implementate Acum:
- 11 - Personal Științific Academie (10 cărți)
- 12 - Bibliotecari BARI (15 cărți)
- 13 - Angajați ARFI (8 cărți)
- 14 - Nespecifici cu domiciliu în Iași (4 cărți)
- 15 - Nespecifici fără domiciliu în Iași (2 cărți)
- 16 - Personal departamente (6 cărți)
- 17 - ILL - Împrumut interbibliotecar (20 cărți)

---

## 📋 Ce Trebuie Actualizat

### 1. Funcția `extrageStatutDinCodBare()`
**Problema:** Verifică doar pentru statuturi 11-17
```php
if ($statut >= 11 && $statut <= 17) {
    return $statut;
}
```

**Soluție:** Trebuie să accepte toate statuturile valide (probabil 11-30 sau mai multe)

### 2. Tabelul `statute_cititori`
**Problema:** Conține doar 7 înregistrări

**Soluție:** Trebuie adăugate toate statutele cu limitele lor

### 3. Scriptul SQL `update_database_statute.sql`
**Problema:** Inserează doar 7 statute

**Soluție:** Trebuie actualizat cu toate statutele

---

## 🎯 Pași pentru Actualizare

### Pasul 1: Obține Lista Completă de Statute

**Ai nevoie de:**
- Lista completă cu toate codurile de statut (11, 12, 13, ..., 30+)
- Numele fiecărui statut
- Limita de cărți pentru fiecare statut

**Unde să găsești:**
- Tabelul 31 din modulul ALEPHADM (Circulație)
- Documentația Aleph
- PDF-ul "Statutul cititorului barcod-uri.pdf"

### Pasul 2: Actualizează Funcția PHP

**Fișier:** `functions_statute.php`

**Schimbare necesară:**
```php
// ÎNAINTE (doar 11-17):
if ($statut >= 11 && $statut <= 17) {
    return $statut;
}

// DUPĂ (toate statuturile valide):
// Verifică dacă statutul există în tabelul statute_cititori
$stmt = $pdo->prepare("SELECT cod_statut FROM statute_cititori WHERE cod_statut = ?");
$stmt->execute([$statut]);
if ($stmt->fetch()) {
    return $statut;
}
```

### Pasul 3: Actualizează Scriptul SQL

**Fișier:** `update_database_statute.sql`

**Adaugă toate statutele:**
```sql
INSERT INTO statute_cititori (cod_statut, nume_statut, limita_totala, descriere) VALUES
('11', 'Personal Științific Academie', 10, '...'),
('12', 'Bibliotecari BARI', 15, '...'),
('13', 'Angajați ARFI', 8, '...'),
-- ... adaugă aici toate celelalte statute
('30', 'Nume Statut 30', X, '...')
ON DUPLICATE KEY UPDATE ...;
```

---

## 📝 Template pentru Adăugare Statute

**Trimite-mi lista în acest format:**

```
Cod Statut | Nume Statut | Limita Cărți | Descriere
-----------|-------------|--------------|----------
11         | Personal Științific Academie | 10 | ...
12         | Bibliotecari BARI | 15 | ...
13         | Angajați ARFI | 8 | ...
...        | ... | ... | ...
```

---

## ✅ Ce Voi Face După Ce Primești Lista

1. ✅ Actualizez `update_database_statute.sql` cu toate statutele
2. ✅ Actualizez `functions_statute.php` pentru a accepta toate statuturile
3. ✅ Actualizez validarea în `extrageStatutDinCodBare()`
4. ✅ Creez script de actualizare pentru baza de date existentă
5. ✅ Testez cu toate statuturile

---

## 🔍 Verificare Rapidă

**Dacă ai acces la Aleph sau documentație, verifică:**
- Câte statuturi sunt în tabelul 31?
- Care sunt codurile exacte (11, 12, 13, ..., 30+)?
- Care sunt limitele pentru fiecare?

**Dacă ai PDF-ul "Statutul cititorului barcod-uri.pdf", poți să:**
- Îl deschizi și să extragi toate statutele
- Sau să-mi spui și eu actualizez sistemul

---

## 🚀 Rezumat

**Problema:** Sistemul acceptă doar 7 statute (11-17), dar ar trebui ~20

**Soluție:** Actualizez sistemul pentru a accepta toate statutele după ce primesc lista completă

**Ce ai nevoie să faci:** Trimite-mi lista completă cu toate statutele și limitele lor

