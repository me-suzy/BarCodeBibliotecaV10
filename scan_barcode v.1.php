<?php
// scan_barcode.php - Endpoint pentru scanner Python
require_once 'config.php';
session_start();

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
    // Verifică dacă este cod CITITOR
    if (preg_match('/^USER\d+$/i', $barcode)) {
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
        $stmt->execute([strtoupper($barcode)]);
        $cititor = $stmt->fetch();

        if ($cititor) {
            // Salvează cititorul în sesiune
            $_SESSION['cititor_activ'] = [
                'cod_bare' => $cititor['cod_bare'],
                'nume' => $cititor['nume'],
                'prenume' => $cititor['prenume']
            ];
            
            echo json_encode([
                'success' => true,
                'tip' => 'cititor',
                'message' => "✅ Cititor selectat: {$cititor['nume']} {$cititor['prenume']}\n" .
                            "Scanează cărțile pentru împrumut!"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Cititorul {$barcode} nu există în baza de date!"
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

        $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
        $stmt->execute([strtoupper($barcode)]);
        $carte = $stmt->fetch();

        if (!$carte) {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Cartea {$barcode} nu există în baza de date!"
            ]);
            exit;
        }

        // Verifică dacă cartea este deja împrumutată
        $stmt = $pdo->prepare("SELECT * FROM imprumuturi WHERE cod_carte = ? AND status = 'activ'");
        $stmt->execute([strtoupper($barcode)]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => "⚠️ Cartea '{$carte['titlu']}' este deja împrumutată!"
            ]);
            exit;
        }

        // ÎNREGISTREAZĂ ÎMPRUMUTUL
        $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
        $stmt = $pdo->prepare("INSERT INTO imprumuturi (cod_cititor, cod_carte, status) VALUES (?, ?, 'activ')");
        $stmt->execute([$cod_cititor, strtoupper($barcode)]);

        echo json_encode([
            'success' => true,
            'tip' => 'carte',
            'message' => "✅ Carte împrumutată!\n" .
                        "📕 {$carte['titlu']}\n" .
                        "👤 {$_SESSION['cititor_activ']['nume']} {$_SESSION['cititor_activ']['prenume']}"
        ]);
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