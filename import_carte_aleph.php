<?php
// import_carte_aleph.php - Import automat carte din Aleph în baza locală
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'aleph_api.php';
header('Content-Type: application/json; charset=utf-8');

$cod_bare = isset($_GET['cod_bare']) ? trim($_GET['cod_bare']) : '';

if (empty($cod_bare)) {
    echo json_encode([
        'success' => false,
        'mesaj' => 'Cod de bare lipsă'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verifică dacă cartea există deja în baza locală
$stmt = $pdo->prepare("SELECT id FROM carti WHERE cod_bare = ?");
$stmt->execute([$cod_bare]);
$carte_existenta = $stmt->fetch();

if ($carte_existenta) {
    echo json_encode([
        'success' => true,
        'exista_local' => true,
        'mesaj' => 'Cartea există deja în baza locală'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Căutare în Aleph
$rezultat_aleph = cautaCarteInAlephDupaBarcode($cod_bare);

if (!$rezultat_aleph['success']) {
    echo json_encode([
        'success' => false,
        'mesaj' => $rezultat_aleph['mesaj']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Import date în baza locală
$date_carte = $rezultat_aleph['data'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO carti (
            cod_bare, 
            titlu, 
            autor, 
            isbn, 
            cota, 
            sectiune,
            data_adaugare
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $cod_bare,
        $date_carte['titlu'],
        $date_carte['autor'] ?? '',
        $date_carte['isbn'] ?? '',
        $date_carte['locatie'] ?? '', // Locația devine cotă
        $date_carte['sectiune'] ?? ''
    ]);
    
    echo json_encode([
        'success' => true,
        'mesaj' => 'Carte importată cu succes din Aleph!',
        'data' => [
            'titlu' => $date_carte['titlu'],
            'autor' => $date_carte['autor'] ?? '',
            'isbn' => $date_carte['isbn'] ?? ''
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mesaj' => 'Eroare la salvare: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>