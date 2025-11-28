<?php
// Script pentru adăugarea mai multor împrumuturi de test
session_start();
require_once 'config.php';
require_once 'auth_check.php';

echo "<h1>📖 Adăugare împrumuturi suplimentare de test</h1>";

// Împrumuturi noi pentru testare extinsă
$imprumuturi_noi = [
    // Împrumuturi foarte recente (ultimele ore)
    ['cod_cititor' => 'USER001', 'cod_carte' => 'BOOK006', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'status' => 'activ'],
    ['cod_cititor' => 'USER002', 'cod_carte' => 'BOOK007', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'status' => 'activ'],
    ['cod_cititor' => 'USER003', 'cod_carte' => 'BOOK008', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'status' => 'activ'],

    // Împrumuturi din ziua curentă
    ['cod_cititor' => 'USER004', 'cod_carte' => 'BOOK009', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-6 hours')), 'status' => 'activ'],
    ['cod_cititor' => 'USER005', 'cod_carte' => 'BOOK010', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-8 hours')), 'status' => 'activ'],
    ['cod_cititor' => 'USER006', 'cod_carte' => 'BOOK011', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-10 hours')), 'status' => 'activ'],

    // Împrumuturi din zilele trecute
    ['cod_cititor' => 'USER007', 'cod_carte' => 'BOOK012', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-1 day')), 'status' => 'activ'],
    ['cod_cititor' => 'USER008', 'cod_carte' => 'BOOK013', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-2 days')), 'status' => 'activ'],
    ['cod_cititor' => 'USER001', 'cod_carte' => 'BOOK014', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-3 days')), 'status' => 'activ'],
    ['cod_cititor' => 'USER002', 'cod_carte' => 'BOOK015', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-4 days')), 'status' => 'activ'],

    // Împrumuturi care vor fi returnate astăzi
    ['cod_cititor' => 'USER003', 'cod_carte' => 'BOOK006', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-5 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('+2 hours')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER004', 'cod_carte' => 'BOOK007', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-6 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('+4 hours')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER005', 'cod_carte' => 'BOOK008', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-7 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('+6 hours')), 'status' => 'returnat'],

    // Împrumuturi returnate recent
    ['cod_cititor' => 'USER006', 'cod_carte' => 'BOOK009', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-8 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-2 days')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER007', 'cod_carte' => 'BOOK010', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-9 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-3 days')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER008', 'cod_carte' => 'BOOK011', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-10 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-4 days')), 'status' => 'returnat'],

    // Împrumuturi mai vechi returnate
    ['cod_cititor' => 'USER001', 'cod_carte' => 'BOOK012', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-15 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-10 days')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER002', 'cod_carte' => 'BOOK013', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-20 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-15 days')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER003', 'cod_carte' => 'BOOK014', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-25 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-20 days')), 'status' => 'returnat'],
    ['cod_cititor' => 'USER004', 'cod_carte' => 'BOOK015', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-30 days')), 'data_returnare' => date('Y-m-d H:i:s', strtotime('-25 days')), 'status' => 'returnat'],

    // Împrumuturi care vor întârzia (peste 30 de zile)
    ['cod_cititor' => 'USER005', 'cod_carte' => 'BOOK001', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-35 days')), 'status' => 'activ'],
    ['cod_cititor' => 'USER006', 'cod_carte' => 'BOOK002', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-40 days')), 'status' => 'activ'],
    ['cod_cititor' => 'USER007', 'cod_carte' => 'BOOK003', 'data_imprumut' => date('Y-m-d H:i:s', strtotime('-45 days')), 'status' => 'activ'],
];

$adaugate = 0;
$erori = 0;

foreach ($imprumuturi_noi as $imprumut) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO imprumuturi (cod_cititor, cod_carte, data_imprumut, data_returnare, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $data_returnare = isset($imprumut['data_returnare']) ? $imprumut['data_returnare'] : null;

        $stmt->execute([
            $imprumut['cod_cititor'],
            $imprumut['cod_carte'],
            $imprumut['data_imprumut'],
            $data_returnare,
            $imprumut['status']
        ]);

        echo "<p style='color: green;'>✅ Adăugat împrumut: {$imprumut['cod_cititor']} → {$imprumut['cod_carte']} ({$imprumut['status']})</p>";
        $adaugate++;

    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠️ Eroare la adăugare {$imprumut['cod_cititor']} → {$imprumut['cod_carte']}: " . $e->getMessage() . "</p>";
        $erori++;
    }
}

echo "<hr>";
echo "<h2>📊 Rezumat împrumuturi noi:</h2>";
echo "<p>Împrumuturi adăugate: <strong>$adaugate</strong></p>";
echo "<p>Erori: <strong>$erori</strong></p>";

// Calculează statistici pentru verificare
$stmt = $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'activ'");
$active = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'returnat'");
$returned = $stmt->fetchColumn();

echo "<h3>📈 Statistici totale după adăugare:</h3>";
echo "<p>Împrumuturi active: <strong>$active</strong></p>";
echo "<p>Împrumuturi returnate: <strong>$returned</strong></p>";
echo "<p>Total împrumuturi: <strong>" . ($active + $returned) . "</strong></p>";

if ($adaugate > 0) {
    echo "<p style='color: green; font-size: 18px; margin-top: 20px;'>🎉 Împrumuturile noi au fost adăugate cu succes!</p>";
    echo "<p><a href='imprumuturi.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📖 Vezi împrumuturile active</a></p>";
    echo "<p><a href='rapoarte.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px; display: inline-block;'>📊 Vezi rapoartele complete</a></p>";
}

echo "<p><a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px; display: inline-block;'>🏠 Înapoi la bibliotecă</a></p>";
?>
