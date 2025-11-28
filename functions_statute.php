<?php
/**
 * Funcții pentru gestionarea statutelor cititorilor
 * 
 * Acest fișier conține funcții pentru:
 * - Extragerea statutului din codul de bare
 * - Obținerea limitelor de împrumut pentru fiecare statut
 * - Verificarea dacă un cititor poate împrumuta încă o carte
 */

/**
 * Extrage statutul din codul de bare (suport pentru multiple formate)
 * 
 * Suportă:
 * - Coduri Aleph (12 cifre): extrage primele 2 cifre
 * - Coduri numerice simple: extrage primele 2 cifre
 * - Coduri USER (ex: USER011): statut implicit '14'
 * 
 * @param string $cod_bare Codul de bare al cititorului
 * @param PDO|null $pdo Conexiunea la baza de date (opțional, pentru validare)
 * @return string Codul statutului sau '14' ca fallback
 */
function extrageStatutDinCodBare($cod_bare, $pdo = null) {
    if (empty($cod_bare) || strlen($cod_bare) < 2) {
        return '14'; // Fallback: statut implicit
    }
    
    // Verifică dacă este cod USER (ex: USER011, USER001)
    if (preg_match('/^USER/i', $cod_bare)) {
        // Coduri USER → statut implicit '14' (Nespecifici cu domiciliu)
        return '14';
    }
    
    // Extrage primele 2 cifre ca potențial statut
    $statut = substr($cod_bare, 0, 2);
    
    // Verifică dacă este cod Aleph (12 cifre numerice) sau cod numeric
    if ((strlen($cod_bare) === 12 && ctype_digit($cod_bare)) || 
        (ctype_digit($cod_bare) && strlen($cod_bare) >= 2)) {
        
        // Dacă avem conexiune la baza de date, verifică dacă statutul există
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("SELECT cod_statut FROM statute_cititori WHERE cod_statut = ?");
                $stmt->execute([$statut]);
                if ($stmt->fetch()) {
                    return $statut; // Statut valid găsit în baza de date
                }
            } catch (PDOException $e) {
                error_log("Eroare verificare statut $statut: " . $e->getMessage());
            }
        }
        
        // Fallback: verifică dacă statutul e între 11-99 (interval rezonabil)
        // Această validare va fi înlocuită cu verificarea din baza de date
        if (is_numeric($statut) && $statut >= 11 && $statut <= 99) {
            return $statut;
        }
    }
    
    // Fallback: statut implicit = 14 (Nespecifici cu domiciliu)
    return '14';
}

/**
 * Obține limita de împrumut pentru un statut
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $statut Codul statutului (11-17)
 * @return int Limita de împrumut pentru acest statut
 */
function getLimitaImprumut($pdo, $statut) {
    try {
        $stmt = $pdo->prepare("SELECT limita_totala FROM statute_cititori WHERE cod_statut = ?");
        $stmt->execute([$statut]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['limita_totala'])) {
            return (int)$result['limita_totala'];
        }
        
        // Fallback: limită implicită = 6
        error_log("Statut necunoscut: $statut - folosind limită implicită 6");
        return 6;
        
    } catch (PDOException $e) {
        error_log("Eroare getLimitaImprumut pentru statut $statut: " . $e->getMessage());
        return 6; // Fallback
    }
}

/**
 * Obține informații complete despre statut
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $statut Codul statutului (11-17)
 * @return array|null Array cu informații despre statut sau null
 */
function getInfoStatut($pdo, $statut) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM statute_cititori WHERE cod_statut = ?");
        $stmt->execute([$statut]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result : null;
        
    } catch (PDOException $e) {
        error_log("Eroare getInfoStatut pentru statut $statut: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifică dacă cititorul poate împrumuta încă o carte
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $cod_cititor Codul de bare al cititorului
 * @param int $numar_carti_imprumutate Numărul de cărți deja împrumutate
 * @return array Array cu informații despre capacitatea de împrumut:
 *               - 'poate' (bool): Dacă poate împrumuta
 *               - 'limita' (int): Limita pentru acest statut
 *               - 'statut' (string): Codul statutului
 *               - 'nume_statut' (string): Numele statutului
 *               - 'ramase' (int): Numărul de cărți rămase
 */
function poateImprumuta($pdo, $cod_cititor, $numar_carti_imprumutate) {
    // Extrage statutul din codul de bare (cu validare din baza de date)
    $statut = extrageStatutDinCodBare($cod_cititor, $pdo);
    
    // Obține limita pentru acest statut
    $limita = getLimitaImprumut($pdo, $statut);
    
    // Obține numele statutului
    $info_statut = getInfoStatut($pdo, $statut);
    $nume_statut = $info_statut ? $info_statut['nume_statut'] : 'Nespecificat';
    
    return [
        'poate' => $numar_carti_imprumutate < $limita,
        'limita' => $limita,
        'statut' => $statut,
        'nume_statut' => $nume_statut,
        'ramase' => max(0, $limita - $numar_carti_imprumutate)
    ];
}

/**
 * Actualizează statutul unui cititor în baza de date
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $cod_cititor Codul de bare al cititorului
 * @return bool True dacă actualizarea a reușit
 */
function actualizeazaStatutCititor($pdo, $cod_cititor) {
    try {
        $statut = extrageStatutDinCodBare($cod_cititor, $pdo);
        
        $stmt = $pdo->prepare("UPDATE cititori SET statut = ? WHERE cod_bare = ?");
        $stmt->execute([$statut, $cod_cititor]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Eroare actualizare statut pentru cititor $cod_cititor: " . $e->getMessage());
        return false;
    }
}

