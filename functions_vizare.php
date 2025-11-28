<?php
// functions_vizare.php - Funcții pentru vizarea permiselor

function verificaVizarePermis($pdo, $cod_cititor) {
    $stmt = $pdo->prepare("SELECT ultima_vizare FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod_cititor]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cititor) {
        return [
            'vizat' => false,
            'data_vizare' => null,
            'mesaj' => 'Cititor inexistent',
            'culoare' => 'danger',
            'icon' => '❌',
            'auto_vizat' => false
        ];
    }
    
    $ultima_vizare = $cititor['ultima_vizare'];
    $an_curent = date('Y');
    
    // Detectare ianuarie (prima lună a anului)
    $este_ianuarie = (date('n') == 1);
    
    if (empty($ultima_vizare)) {
        $mesaj_baza = "PERMIS NEVIZAT - Niciodată vizat";
        $mesaj_extra = $este_ianuarie ? " 🎉 LA MULȚI ANI! Permis vizat AUTOMAT pentru $an_curent!" : "";
        
        return [
            'vizat' => false,
            'data_vizare' => null,
            'mesaj' => $mesaj_baza . $mesaj_extra,
            'culoare' => 'danger',
            'icon' => '🔴',
            'pulsate' => true,
            'auto_vizat' => false
        ];
    }
    
    $an_vizare = date('Y', strtotime($ultima_vizare));
    if ($an_vizare < $an_curent) {
        $mesaj_baza = "PERMIS NEVIZAT pentru $an_curent";
        $mesaj_extra = $este_ianuarie ? " 🎉 AN NOU!" : " (ultima vizare: $ultima_vizare)";
        
        return [
            'vizat' => false,
            'data_vizare' => $ultima_vizare,
            'mesaj' => $mesaj_baza . $mesaj_extra,
            'culoare' => 'danger',
            'icon' => '🔴',
            'pulsate' => true,
            'auto_vizat' => false
        ];
    }
    
    return [
        'vizat' => true,
        'data_vizare' => $ultima_vizare,
        'mesaj' => "✅ Permis VIZAT pentru $an_curent",
        'culoare' => 'success',
        'icon' => '✅',
        'pulsate' => false,
        'auto_vizat' => false
    ];
}

// ← NOU: Vizare AUTOMATĂ la prima scanare din an nou
function vizeazaPermisAutomat($pdo, $cod_cititor) {
    try {
        $data_vizare = date('Y-m-d');
        $an_curent = date('Y');
        
        // Verifică dacă permisul trebuie vizat
        $stmt = $pdo->prepare("SELECT ultima_vizare FROM cititori WHERE cod_bare = ?");
        $stmt->execute([$cod_cititor]);
        $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cititor) {
            return [
                'vizat' => false,
                'mesaj' => "Cititor inexistent"
            ];
        }
        
        $ultima_vizare = $cititor['ultima_vizare'];
        
        // Cazul 1: Niciodată vizat → Vizează automat
        if (empty($ultima_vizare)) {
            $stmt = $pdo->prepare("UPDATE cititori SET ultima_vizare = ? WHERE cod_bare = ?");
            $stmt->execute([$data_vizare, $cod_cititor]);
            
            return [
                'vizat' => true,
                'mesaj' => "🎉 Permis vizat AUTOMAT pentru $an_curent! Bun venit!"
            ];
        }
        
        // Cazul 2: Vizat în alt an → Vizează automat pentru anul curent
        $an_vizare = date('Y', strtotime($ultima_vizare));
        if ($an_vizare < $an_curent) {
            $stmt = $pdo->prepare("UPDATE cititori SET ultima_vizare = ? WHERE cod_bare = ?");
            $stmt->execute([$data_vizare, $cod_cititor]);
            
            return [
                'vizat' => true,
                'mesaj' => "🎉 Permis vizat AUTOMAT pentru $an_curent! (anterior vizat: " . date('Y', strtotime($ultima_vizare)) . ")"
            ];
        }
        
        // Cazul 3: Deja vizat pentru anul curent → Nu face nimic
        return [
            'vizat' => true,
            'mesaj' => "✅ Permis deja vizat pentru $an_curent"
        ];
        
    } catch (PDOException $e) {
        return [
            'vizat' => false,
            'mesaj' => "❌ Eroare vizare automată: " . $e->getMessage()
        ];
    }
}

function vizeazaPermis($pdo, $cod_cititor) {
    try {
        $data_vizare = date('Y-m-d');
        $an_curent = date('Y');
        
        $stmt = $pdo->prepare("UPDATE cititori SET ultima_vizare = ? WHERE cod_bare = ?");
        $stmt->execute([$data_vizare, $cod_cititor]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'mesaj' => "✅ Permis vizat cu succes pentru anul $an_curent!"
            ];
        } else {
            return [
                'success' => false,
                'mesaj' => "⚠️ Cititor inexistent sau deja vizat"
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'mesaj' => "❌ Eroare: " . $e->getMessage()
        ];
    }
}

function getCitoriNevizati($pdo) {
    $an_curent = date('Y');
    
    $sql = "SELECT id, cod_bare, nume, prenume, email, telefon, ultima_vizare 
            FROM cititori 
            WHERE YEAR(ultima_vizare) < ? OR ultima_vizare IS NULL
            ORDER BY nume, prenume";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$an_curent]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}