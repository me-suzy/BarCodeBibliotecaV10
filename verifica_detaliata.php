<?php
require_once 'config.php';

echo "=== VERIFICARE DETALIATĂ ===\n\n";

// Verifică toate împrumuturile pentru USER011
echo "1. Toate împrumuturile pentru USER011:\n";
$stmt = $pdo->prepare("
    SELECT id, cod_carte, data_imprumut, data_returnare, status
    FROM imprumuturi
    WHERE cod_cititor = 'USER011'
    ORDER BY data_imprumut DESC
");
$stmt->execute();
$imprumuturi = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($imprumuturi) . " înregistrări\n";
foreach ($imprumuturi as $imp) {
    $returnat = $imp['data_returnare'] ? 'RETURNAT' : 'ACTIV';
    echo "  ID: {$imp['id']}, Carte: {$imp['cod_carte']}, Data: {$imp['data_imprumut']}, Status: $returnat\n";
}

// Verifică dublurile pentru USER011
echo "\n2. Dubluri pentru USER011 (doar active):\n";
$stmt = $pdo->prepare("
    SELECT cod_carte, COUNT(*) as numar
    FROM imprumuturi
    WHERE cod_cititor = 'USER011' AND data_returnare IS NULL
    GROUP BY cod_carte
    HAVING COUNT(*) > 1
");
$stmt->execute();
$dubluri = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($dubluri) > 0) {
    foreach ($dubluri as $d) {
        echo "  Carte: {$d['cod_carte']}, Număr: {$d['numar']}\n";
        
        // Arată toate ID-urile pentru această carte
        $stmt_ids = $pdo->prepare("
            SELECT id, data_imprumut
            FROM imprumuturi
            WHERE cod_cititor = 'USER011' AND cod_carte = ? AND data_returnare IS NULL
            ORDER BY data_imprumut ASC
        ");
        $stmt_ids->execute([$d['cod_carte']]);
        $ids = $stmt_ids->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ids as $id_row) {
            echo "    ID: {$id_row['id']}, Data: {$id_row['data_imprumut']}\n";
        }
    }
} else {
    echo "  Nu există dubluri\n";
}

// Verifică numărul total de cărți active pentru USER011
echo "\n3. Număr total cărți active pentru USER011:\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM imprumuturi
    WHERE cod_cititor = 'USER011' AND data_returnare IS NULL
");
$stmt->execute();
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Total cărți active: {$total['total']}\n";
?>

