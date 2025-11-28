<?php
// functions_sesiuni.php - Funcții pentru gestionarea sesiunilor utilizatorilor

/**
 * Verifică dacă utilizatorul este blocat
 */
function esteUtilizatorBlocat($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("SELECT blocat, motiv_blocare FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod_cititor]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cititor && $cititor['blocat'] == 1) {
        return [
            'blocat' => true,
            'motiv' => $cititor['motiv_blocare'] ?? 'Utilizator blocat'
        ];
    }
    
    return ['blocat' => false];
}

/**
 * Verifică dacă utilizatorul are întârzieri (cărți peste 14 zile)
 */
function areIntarzieri($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as numar_intarzieri 
        FROM imprumuturi 
        WHERE cod_cititor = ? 
        AND data_returnare IS NULL 
        AND data_scadenta < CURDATE()
    ");
    $stmt->execute([$cod_cititor]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['numar_intarzieri'] > 0;
}

/**
 * Obține numărul de cărți împrumutate activ de către un utilizator
 */
function numarCartiImprumutate($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as numar 
        FROM imprumuturi 
        WHERE cod_cititor = ? 
        AND data_returnare IS NULL
    ");
    $stmt->execute([$cod_cititor]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$result['numar'];
}

/**
 * Creează sau reînnoiește sesiunea utilizatorului
 * Dacă utilizatorul revine după 5 minute, se creează o sesiune nouă
 */
function creazaSesiuneUtilizator($pdo, $cod_cititor) {
    // Șterge sesiunile expirate (mai vechi de 5 minute)
    $pdo->exec("
        UPDATE sesiuni_utilizatori 
        SET status = 'expirat' 
        WHERE status = 'activ' 
        AND TIMESTAMPDIFF(SECOND, timestamp_start, NOW()) > 300
    ");
    
    // Verifică dacă există sesiune activă pentru acest utilizator (în termen de 5 minute)
    $stmt = $pdo->prepare("
        SELECT * FROM sesiuni_utilizatori 
        WHERE cod_cititor = ? 
        AND status = 'activ' 
        AND TIMESTAMPDIFF(SECOND, timestamp_start, NOW()) <= 300
        ORDER BY timestamp_start DESC
        LIMIT 1
    ");
    $stmt->execute([$cod_cititor]);
    $sesiune = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sesiune) {
        // Reînnoiește timestamp-ul ultimei acțiuni și resetăm numărul de cărți scanate
        $stmt = $pdo->prepare("
            UPDATE sesiuni_utilizatori 
            SET timestamp_ultima_actiune = NOW(),
                numar_carti_scanate = 0
            WHERE id = ?
        ");
        $stmt->execute([$sesiune['id']]);
        return $sesiune['id'];
    } else {
        // Creează sesiune nouă (utilizatorul revine după 5 minute sau prima dată)
        $stmt = $pdo->prepare("
            INSERT INTO sesiuni_utilizatori (cod_cititor, timestamp_start, timestamp_ultima_actiune, numar_carti_scanate) 
            VALUES (?, NOW(), NOW(), 0)
        ");
        $stmt->execute([$cod_cititor]);
        return $pdo->lastInsertId();
    }
}

/**
 * Verifică dacă sesiunea utilizatorului este activă (în termen de 30 secunde de la ultima acțiune)
 */
function esteSesiuneActiva($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("
        SELECT * FROM sesiuni_utilizatori 
        WHERE cod_cititor = ? 
        AND status = 'activ' 
        AND TIMESTAMPDIFF(SECOND, timestamp_start, NOW()) <= 300
        AND TIMESTAMPDIFF(SECOND, timestamp_ultima_actiune, NOW()) <= 30
        ORDER BY timestamp_ultima_actiune DESC 
        LIMIT 1
    ");
    $stmt->execute([$cod_cititor]);
    $sesiune = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sesiune) {
        // Actualizează timestamp-ul ultimei acțiuni
        $stmt = $pdo->prepare("
            UPDATE sesiuni_utilizatori 
            SET timestamp_ultima_actiune = NOW(),
                numar_carti_scanate = numar_carti_scanate + 1
            WHERE id = ?
        ");
        $stmt->execute([$sesiune['id']]);
        return true;
    }
    
    return false;
}

/**
 * Închide sesiunea utilizatorului
 */
function inchideSesiuneUtilizator($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("
        UPDATE sesiuni_utilizatori 
        SET status = 'inchis' 
        WHERE cod_cititor = ? 
        AND status = 'activ'
    ");
    $stmt->execute([$cod_cititor]);
}

/**
 * Verifică și închide sesiunile expirate (30 secunde fără acțiune sau 5 minute total)
 */
function verificaSesiuniExpirate($pdo) {
    // Închide sesiunile care au trecut 30 secunde de la ultima acțiune
    $pdo->exec("
        UPDATE sesiuni_utilizatori 
        SET status = 'expirat' 
        WHERE status = 'activ' 
        AND TIMESTAMPDIFF(SECOND, timestamp_ultima_actiune, NOW()) > 30
    ");
    
    // Închide sesiunile care au trecut 5 minute de la start
    $pdo->exec("
        UPDATE sesiuni_utilizatori 
        SET status = 'expirat' 
        WHERE status = 'activ' 
        AND TIMESTAMPDIFF(SECOND, timestamp_start, NOW()) > 300
    ");
}

/**
 * Înregistrează o acțiune în tracking_sesiuni
 */
function inregistreazaActiuneTracking($pdo, $cod_cititor, $tip_actiune, $cod_carte = null, $sesiune_id = null, $detalii = null) {
    try {
        // Verifică dacă tabelul există
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tracking_sesiuni` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `cod_cititor` VARCHAR(50) NOT NULL,
              `tip_actiune` ENUM('scanare_permis', 'scanare_carte_imprumut', 'scanare_carte_returnare', 'sesiune_expirata', 'sesiune_inchisa') NOT NULL,
              `cod_carte` VARCHAR(50) DEFAULT NULL,
              `data_ora` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `data` DATE GENERATED ALWAYS AS (DATE(data_ora)) STORED,
              `ora` TIME GENERATED ALWAYS AS (TIME(data_ora)) STORED,
              `sesiune_id` INT DEFAULT NULL,
              `detalii` TEXT DEFAULT NULL,
              INDEX `idx_cititor` (`cod_cititor`),
              INDEX `idx_data_ora` (`data_ora`),
              INDEX `idx_data` (`data`),
              INDEX `idx_tip_actiune` (`tip_actiune`),
              INDEX `idx_sesiune` (`sesiune_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci
        ");
    } catch (PDOException $e) {
        // Tabelul există deja sau eroare - continuă
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO tracking_sesiuni (cod_cititor, tip_actiune, cod_carte, sesiune_id, detalii) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detalii_json = $detalii ? json_encode($detalii) : null;
    $stmt->execute([$cod_cititor, $tip_actiune, $cod_carte, $sesiune_id, $detalii_json]);
    
    return $pdo->lastInsertId();
}

