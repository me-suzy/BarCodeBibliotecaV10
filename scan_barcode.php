<?php
// scan_barcode.php - Endpoint pentru scanner Python
session_start();
require_once 'config.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodă invalidă']);
    exit;
}

$barcode = trim($_POST['barcode'] ?? '');

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Cod invalid']);
    exit;
}

try {
    require_once 'functions_coduri_aleph.php';
    
    // Detectează tipul de cod (USER, Aleph sau Biblioteca Academiei)
    $tip_cod = detecteazaTipCod($barcode);
    
    // Verifică dacă este cod CITITOR (USER, Aleph sau Biblioteca Academiei)
    if ($tip_cod === 'user' || $tip_cod === 'aleph' || $tip_cod === 'biblioteca_academiei') {
        // Folosește funcția helper pentru a găsi cititorul
        $cititor = gasesteCititorDupaCod($pdo, $barcode);
        
        // Fallback la metoda veche dacă funcția helper nu găsește
        if (!$cititor) {
            $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
            $stmt->execute([$barcode]);
            $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($cititor) {
            // 1. SALVEAZĂ PREZENȚA în istoric
            $stmt_prezenta = $pdo->prepare("
                INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare)
                VALUES (?, CURDATE(), CURTIME())
            ");
            $stmt_prezenta->execute([$cititor['cod_bare']]);

            // 2. ACTUALIZEAZĂ SESIUNEA ACTIVĂ (ultimul utilizator scanat)
            $_SESSION['cititor_activ'] = [
                'cod_bare' => $cititor['cod_bare'],
                'nume' => $cititor['nume'],
                'prenume' => $cititor['prenume']
            ];

            // 3. VERIFICĂ câte cărți are împrumutate
            $stmt_imprumuturi = $pdo->prepare("
                SELECT COUNT(*) as nr_carti 
                FROM imprumuturi 
                WHERE cod_cititor = ? AND status = 'activ'
            ");
            $stmt_imprumuturi->execute([$cititor['cod_bare']]);
            $nr_carti = $stmt_imprumuturi->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'tip' => 'cititor',
                'message' => "✅ Bine ai venit: {$cititor['nume']} {$cititor['prenume']}\n" .
                            "📚 Ai {$nr_carti} " . ($nr_carti == 1 ? 'carte împrumutată' : 'cărți împrumutate') . "\n" .
                            "Scanează cărțile pentru împrumut/returnare!"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Cititorul nu există în baza de date!"
            ]);
        }
        exit;
    }

    // Verifică dacă este cod CARTE
    if (preg_match('/^BOOK\d+$/i', $barcode)) {
        // Verifică dacă există cititor activ în sesiune
        if (!isset($_SESSION['cititor_activ'])) {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Scanează ÎNTÂI carnetul cititorului!"
            ]);
            exit;
        }

        $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
        $cod_carte = strtoupper($barcode);

        // Verifică dacă cartea există
        $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
        $stmt->execute([$cod_carte]);
        $carte = $stmt->fetch();

        if (!$carte) {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Cartea {$barcode} nu există în baza de date!"
            ]);
            exit;
        }

        // Verifică dacă cartea e împrumutată
        $stmt = $pdo->prepare("
            SELECT i.*, c.nume, c.prenume 
            FROM imprumuturi i
            JOIN cititori c ON i.cod_cititor = c.cod_bare
            WHERE i.cod_carte = ? AND i.status = 'activ'
        ");
        $stmt->execute([$cod_carte]);
        $imprumut_existent = $stmt->fetch();

        if ($imprumut_existent) {
            // CARTEA E ÎMPRUMUTATĂ - verificăm DE CINE
            
            if ($imprumut_existent['cod_cititor'] === $cod_cititor) {
                // ✅ E ÎMPRUMUTATĂ DE CITITORUL CURENT → RETURNARE
                $stmt = $pdo->prepare("
                    UPDATE imprumuturi
                    SET status = 'returnat', data_returnare = NOW()
                    WHERE cod_carte = ? AND cod_cititor = ? AND status = 'activ'
                ");
                $stmt->execute([$cod_carte, $cod_cititor]);

                echo json_encode([
                    'success' => true,
                    'tip' => 'returnare',
                    'message' => "📥 Carte RETURNATĂ!\n" .
                                "📕 {$carte['titlu']}\n" .
                                "👤 {$_SESSION['cititor_activ']['nume']} {$_SESSION['cititor_activ']['prenume']}"
                ]);
            } else {
                // ❌ E ÎMPRUMUTATĂ DE ALTCINEVA
                echo json_encode([
                    'success' => false,
                    'message' => "⚠️ Cartea '{$carte['titlu']}' este deja împrumutată de:\n" .
                                "{$imprumut_existent['nume']} {$imprumut_existent['prenume']} ({$imprumut_existent['cod_cititor']})"
                ]);
            }
        } else {
            // ✅ CARTEA NU E ÎMPRUMUTATĂ → ÎMPRUMUT NOU
            $stmt = $pdo->prepare("INSERT INTO imprumuturi (cod_cititor, cod_carte, status) VALUES (?, ?, 'activ')");
            $stmt->execute([$cod_cititor, $cod_carte]);

            echo json_encode([
                'success' => true,
                'tip' => 'imprumut',
                'message' => "📤 Carte ÎMPRUMUTATĂ!\n" .
                            "📕 {$carte['titlu']}\n" .
                            "👤 {$_SESSION['cititor_activ']['nume']} {$_SESSION['cititor_activ']['prenume']}"
            ]);
        }
        exit;
    }

    // Cod necunoscut
    echo json_encode([
        'success' => false,
        'message' => "⚠️ Cod necunoscut: {$barcode}\nFolosește USER*** sau BOOK***"
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Eroare BD: ' . $e->getMessage()]);
}
?>
