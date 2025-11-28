<?php
/**
 * Funcții helper pentru coduri de bare Aleph
 * Suport pentru format Aleph: 12 caractere (2 cifre statut + 9 cifre număr + 1 padding/check)
 * Format: SS + NNNNNNNNN + X = 12 caractere total
 */

/**
 * Validează dacă un cod este în format Aleph
 * Format Aleph: 12 caractere numerice, primele 2 cifre = statut
 * 
 * @param string $cod Codul de verificat
 * @return bool|array False dacă nu este valid, array cu ['statut' => XX, 'numar' => NNNNNNNNN] dacă este valid
 */
function valideazaCodAleph($cod) {
    // Elimină spații și convertește la string
    $cod = trim((string)$cod);
    
    // Verifică dacă are exact 12 caractere și sunt toate cifre
    if (strlen($cod) !== 12 || !ctype_digit($cod)) {
        return false;
    }
    
    // Extrage statutul (primele 2 cifre)
    $statut = substr($cod, 0, 2);
    
    // Extrage numărul (următoarele 9 cifre)
    $numar = substr($cod, 2, 9);
    
    // Ultima cifră (poate fi check digit sau padding)
    $ultima_cifra = substr($cod, 11, 1);
    
    return [
        'cod_complet' => $cod,
        'statut' => $statut,
        'numar' => $numar,
        'ultima_cifra' => $ultima_cifra,
        'format' => 'aleph'
    ];
}

/**
 * Validează dacă un cod este în format USER (pentru testare)
 * Format USER: USER + număr (ex: USER001, USER002)
 * 
 * @param string $cod Codul de verificat
 * @return bool|array False dacă nu este valid, array cu informații dacă este valid
 */
function valideazaCodUser($cod) {
    $cod = trim((string)$cod);
    
    // Verifică format USER + număr
    if (preg_match('/^USER(\d+)$/i', $cod, $matches)) {
        return [
            'cod_complet' => strtoupper($cod),
            'numar' => $matches[1],
            'format' => 'user'
        ];
    }
    
    return false;
}

/**
 * Detectează tipul de cod (Aleph, USER sau Biblioteca Academiei)
 * 
 * @param string $cod Codul de verificat
 * @return string|false 'aleph', 'user', 'biblioteca_academiei', sau false dacă nu este recunoscut
 */
function detecteazaTipCod($cod) {
    $cod = trim((string)$cod);
    
    // Verifică mai întâi format Aleph (12 cifre)
    if (valideazaCodAleph($cod)) {
        return 'aleph';
    }
    
    // Verifică format USER
    if (valideazaCodUser($cod)) {
        return 'user';
    }
    
    // Verifică format Biblioteca Academiei Române - Iași (14016xxx - 8 cifre, începe cu 14016)
    if (preg_match('/^14016\d{3}$/', $cod)) {
        return 'biblioteca_academiei';
    }
    
    return false;
}

/**
 * Generează un cod Aleph nou
 * Format: SS + NNNNNNNNN + X
 * 
 * @param int $statut Statutul cititorului (11, 12, 13, etc.)
 * @param int $numar_start Numărul de început (default: 1)
 * @param PDO $pdo Conexiune la baza de date pentru verificare duplicat
 * @return string|false Codul generat sau false dacă nu se poate genera
 */
function genereazaCodAleph($statut, $numar_start = 1, $pdo = null) {
    // Validează statutul (trebuie să fie între 11 și 99)
    $statut = (int)$statut;
    if ($statut < 11 || $statut > 99) {
        return false;
    }
    
    // Formatează statutul cu 2 cifre
    $statut_str = str_pad($statut, 2, '0', STR_PAD_LEFT);
    
    // Formatează numărul cu 9 cifre
    $numar_str = str_pad($numar_start, 9, '0', STR_PAD_LEFT);
    
    // Generează codul (12 caractere: 2 statut + 9 număr + 1 padding)
    // Pentru moment, ultima cifră este 0 (poate fi modificat pentru check digit)
    $cod = $statut_str . $numar_str . '0';
    
    // Verifică duplicat dacă avem conexiune la baza de date
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cititori WHERE cod_bare = ?");
        $stmt->execute([$cod]);
        if ($stmt->fetchColumn() > 0) {
            // Dacă există, încearcă următorul număr
            return genereazaCodAleph($statut, $numar_start + 1, $pdo);
        }
    }
    
    return $cod;
}

/**
 * Extrage statutul dintr-un cod Aleph
 * 
 * @param string $cod Codul Aleph
 * @return int|false Statutul sau false dacă codul nu este valid
 */
function extrageStatutDinCod($cod) {
    $validare = valideazaCodAleph($cod);
    if ($validare) {
        return (int)$validare['statut'];
    }
    return false;
}

/**
 * Formatează un cod Aleph pentru afișare
 * 
 * @param string $cod Codul Aleph
 * @return string Codul formatat (ex: "12-000000001-0")
 */
function formateazaCodAleph($cod) {
    $validare = valideazaCodAleph($cod);
    if ($validare) {
        return $validare['statut'] . '-' . $validare['numar'] . '-' . $validare['ultima_cifra'];
    }
    return $cod;
}

/**
 * Verifică dacă un cod de cititor există în baza de date
 * Funcționează cu ambele formate (USER și Aleph)
 * 
 * @param PDO $pdo Conexiune la baza de date
 * @param string $cod Codul de verificat
 * @return array|false Array cu datele cititorului sau false dacă nu există
 */
function gasesteCititorDupaCod($pdo, $cod) {
    $cod = trim((string)$cod);
    
    // Caută direct în baza de date (funcționează pentru ambele formate)
    $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cititor) {
        // Adaugă informații despre format
        $tip_cod = detecteazaTipCod($cod);
        $cititor['tip_cod'] = $tip_cod;
        
        if ($tip_cod === 'aleph') {
            $validare = valideazaCodAleph($cod);
            $cititor['statut_cod'] = $validare['statut'];
        }
        
        return $cititor;
    }
    
    return false;
}

/**
 * Obține următorul număr disponibil pentru un statut dat
 * 
 * @param PDO $pdo Conexiune la baza de date
 * @param int $statut Statutul cititorului
 * @return int Următorul număr disponibil
 */
function obtineUrmatorulNumarAleph($pdo, $statut) {
    $statut_str = str_pad($statut, 2, '0', STR_PAD_LEFT);
    
    // Caută toate codurile care încep cu acest statut
    $stmt = $pdo->prepare("SELECT cod_bare FROM cititori WHERE cod_bare LIKE ? ORDER BY cod_bare DESC LIMIT 1");
    $stmt->execute([$statut_str . '%']);
    $ultimul_cod = $stmt->fetchColumn();
    
    if ($ultimul_cod) {
        $validare = valideazaCodAleph($ultimul_cod);
        if ($validare && $validare['statut'] == $statut_str) {
            // Extrage numărul și incrementează
            return (int)$validare['numar'] + 1;
        }
    }
    
    // Dacă nu există coduri pentru acest statut, începe de la 1
    return 1;
}

/**
 * Listează statuturile disponibile (din tabelul 31 Aleph)
 * Acestea sunt exemple - trebuie actualizate cu valorile reale din Aleph
 * 
 * @return array Array cu statuturi disponibile
 */
function obtineStatuturiDisponibile() {
    return [
        '11' => 'Statut 11 (exemplu)',
        '12' => 'Statut 12 (exemplu)',
        '13' => 'Statut 13 (exemplu)',
        // Adaugă aici statuturile reale din tabelul 31 Aleph
    ];
}

