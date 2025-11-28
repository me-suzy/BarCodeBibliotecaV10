<?php
/**
 * Funcții pentru gestionarea statuturilor cărților
 * 
 * Acest fișier conține funcții pentru:
 * - Verificarea dacă o carte poate fi împrumutată
 * - Obținerea duratei de împrumut pentru fiecare statut
 * - Verificarea restricțiilor de împrumut (acasă vs. sală)
 */

/**
 * Obține informații despre statutul unei cărți
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $statut Codul statutului (01-90)
 * @return array|null Array cu informații despre statut sau null
 */
function getInfoStatutCarte($pdo, $statut) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM statute_carti WHERE cod_statut = ?");
        $stmt->execute([$statut]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result : null;
        
    } catch (PDOException $e) {
        error_log("Eroare getInfoStatutCarte pentru statut $statut: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifică dacă o carte poate fi împrumutată
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $cod_carte Codul de bare al cărții
 * @param string $tip_imprumut Tipul de împrumut: 'acasa' sau 'sala'
 * @return array Array cu informații despre posibilitatea de împrumut:
 *               - 'poate' (bool): Dacă poate fi împrumutată
 *               - 'statut' (string): Codul statutului
 *               - 'nume_statut' (string): Numele statutului
 *               - 'durata_zile' (int): Durata împrumutului în zile
 *               - 'mesaj' (string): Mesaj explicativ
 */
function poateImprumutaCarte($pdo, $cod_carte, $tip_imprumut = 'acasa') {
    try {
        // Obține informații despre carte
        $stmt = $pdo->prepare("SELECT statut, titlu FROM carti WHERE cod_bare = ?");
        $stmt->execute([$cod_carte]);
        $carte = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$carte) {
            return [
                'poate' => false,
                'statut' => null,
                'nume_statut' => 'Necunoscut',
                'durata_zile' => 0,
                'mesaj' => 'Cartea nu există în baza de date!'
            ];
        }
        
        $statut = $carte['statut'] ?? '01';
        $info_statut = getInfoStatutCarte($pdo, $statut);
        
        if (!$info_statut) {
            // Fallback: statut implicit '01'
            $info_statut = getInfoStatutCarte($pdo, '01');
            if (!$info_statut) {
                return [
                    'poate' => false,
                    'statut' => $statut,
                    'nume_statut' => 'Necunoscut',
                    'durata_zile' => 14,
                    'mesaj' => 'Statut necunoscut pentru carte!'
                ];
            }
        }
        
        // Verifică dacă poate fi împrumutată
        $poate_imprumuta = false;
        $mesaj = '';
        
        if ($tip_imprumut === 'acasa') {
            $poate_imprumuta = (bool)$info_statut['poate_imprumuta_acasa'];
            if (!$poate_imprumuta) {
                $mesaj = "Cartea '{$carte['titlu']}' nu poate fi împrumutată acasă. Statut: {$info_statut['nume_statut']}";
            }
        } else if ($tip_imprumut === 'sala') {
            $poate_imprumuta = (bool)$info_statut['poate_imprumuta_sala'];
            if (!$poate_imprumuta) {
                $mesaj = "Cartea '{$carte['titlu']}' nu poate fi împrumutată la sală. Statut: {$info_statut['nume_statut']}";
            }
        }
        
        // Verificări speciale
        if ($statut === '04') {
            $poate_imprumuta = false;
            $mesaj = "Cartea '{$carte['titlu']}' nu există fizic - nu se poate împrumuta!";
        } else if ($statut === '08') {
            $poate_imprumuta = false;
            $mesaj = "Cartea '{$carte['titlu']}' nu se împrumută (ne circulată)!";
        } else if ($statut === '90') {
            $poate_imprumuta = false;
            $mesaj = "Cartea '{$carte['titlu']}' este încă în depozit, nu a ajuns la raft!";
        }
        
        return [
            'poate' => $poate_imprumuta,
            'statut' => $statut,
            'nume_statut' => $info_statut['nume_statut'],
            'durata_zile' => (int)$info_statut['durata_imprumut_zile'],
            'mesaj' => $mesaj ?: "Cartea poate fi împrumutată. Durată: {$info_statut['durata_imprumut_zile']} zile"
        ];
        
    } catch (PDOException $e) {
        error_log("Eroare poateImprumutaCarte pentru carte $cod_carte: " . $e->getMessage());
        return [
            'poate' => false,
            'statut' => null,
            'nume_statut' => 'Eroare',
            'durata_zile' => 0,
            'mesaj' => 'Eroare la verificarea statutului cărții!'
        ];
    }
}

/**
 * Obține durata de împrumut pentru o carte
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $cod_carte Codul de bare al cărții
 * @return int Durata în zile (default: 14)
 */
function getDurataImprumutCarte($pdo, $cod_carte) {
    try {
        $stmt = $pdo->prepare("SELECT statut FROM carti WHERE cod_bare = ?");
        $stmt->execute([$cod_carte]);
        $statut = $stmt->fetchColumn();
        
        if ($statut) {
            $info_statut = getInfoStatutCarte($pdo, $statut);
            if ($info_statut) {
                return (int)$info_statut['durata_imprumut_zile'];
            }
        }
        
        return 14; // Default
        
    } catch (PDOException $e) {
        error_log("Eroare getDurataImprumutCarte pentru carte $cod_carte: " . $e->getMessage());
        return 14;
    }
}

/**
 * Actualizează statutul unei cărți
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $cod_carte Codul de bare al cărții
 * @param string $statut_nou Codul noului statut
 * @return bool True dacă actualizarea a reușit
 */
function actualizeazaStatutCarte($pdo, $cod_carte, $statut_nou) {
    try {
        // Verifică dacă statutul există
        $info_statut = getInfoStatutCarte($pdo, $statut_nou);
        if (!$info_statut) {
            error_log("Statut invalid pentru carte: $statut_nou");
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE carti SET statut = ? WHERE cod_bare = ?");
        $stmt->execute([$statut_nou, $cod_carte]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Eroare actualizare statut pentru carte $cod_carte: " . $e->getMessage());
        return false;
    }
}

/**
 * Obține mesajul de statut pentru email
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $statut_carte Codul statutului cărții
 * @return string Mesajul formatat pentru email
 */
function getMesajStatutCarteEmail($pdo, $statut_carte) {
    $info_statut = getInfoStatutCarte($pdo, $statut_carte);
    
    if (!$info_statut) {
        return '';
    }
    
    $mesaje = [
        '01' => 'Se poate împrumuta acasă',
        '02' => 'Se împr. numai la sală',
        '03' => 'Colecții speciale - sală 1 zi',
        '04' => 'Nu există fizic',
        '05' => 'Împrumut scurt 5 zile',
        '06' => 'Regim special 6 luni - 1 an',
        '08' => 'Ne circulat',
        '90' => 'În achiziție - depozit'
    ];
    
    return $mesaje[$statut_carte] ?? $info_statut['nume_statut'];
}

/**
 * Convertește statusul din Aleph în codul de statut
 * 
 * @param string $status_aleph Statusul din Aleph (ex: "Pentru împrumut", "Se împr. numai la sală")
 * @return string Codul statutului (01-90) sau '01' ca fallback
 */
function convertesteStatusAlephInCod($status_aleph) {
    if (empty($status_aleph)) {
        return '01'; // Default: pentru împrumut acasă
    }
    
    $status_lower = mb_strtolower(trim($status_aleph), 'UTF-8');
    
    // Mapare status Aleph → cod statut
    if (stripos($status_lower, 'pentru împrumut') !== false || 
        stripos($status_lower, 'pe raft') !== false) {
        return '01'; // Pentru împrumut acasă
    }
    
    if (stripos($status_lower, 'se împr. numai la sală') !== false ||
        stripos($status_lower, 'se imprumuta numai la sala') !== false ||
        stripos($status_lower, 'numai la sală') !== false) {
        return '02'; // Se împr. numai la sală
    }
    
    if (stripos($status_lower, 'doar pentru sl') !== false ||
        stripos($status_lower, 'colectii speciale') !== false ||
        stripos($status_lower, 'colecții speciale') !== false) {
        return '03'; // Colecții speciale - sală 1 zi
    }
    
    if (stripos($status_lower, 'casat') !== false ||
        stripos($status_lower, 'nu există') !== false ||
        stripos($status_lower, 'nu exista') !== false) {
        return '04'; // Nu există fizic
    }
    
    if (stripos($status_lower, 'împrumut scurt') !== false ||
        stripos($status_lower, 'imprumut scurt') !== false) {
        return '05'; // Împrumut scurt 5 zile
    }
    
    if (stripos($status_lower, 'regim special') !== false ||
        stripos($status_lower, '6 luni') !== false ||
        stripos($status_lower, '1 an') !== false) {
        return '06'; // Regim special 6 luni - 1 an
    }
    
    if (stripos($status_lower, 'ne circulat') !== false ||
        stripos($status_lower, 'nu se imprumuta') !== false) {
        return '08'; // Ne circulat
    }
    
    if (stripos($status_lower, 'în achiziție') !== false ||
        stripos($status_lower, 'in achizitie') !== false ||
        stripos($status_lower, 'depozit') !== false) {
        return '90'; // În achiziție - depozit
    }
    
    // Fallback: default pentru împrumut acasă
    return '01';
}

