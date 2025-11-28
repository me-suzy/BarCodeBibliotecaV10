<?php
require_once 'config.php';

echo "=== VERIFICARE STATUS ÎMPRUMUTURI ===\n\n";

// Verifică înregistrări cu status 'activ' dar cu data_returnare setată
echo "1. Înregistrări cu status 'activ' dar returnate:\n";
$stmt = $pdo->query("
    SELECT id, cod_cititor, cod_carte, status, data_returnare
    FROM imprumuturi
    WHERE status = 'activ' AND data_returnare IS NOT NULL
    LIMIT 20
");
$probleme = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($probleme) > 0) {
    echo "Găsite " . count($probleme) . " înregistrări cu status 'activ' dar returnate!\n";
    foreach ($probleme as $p) {
        echo "  ID: {$p['id']}, Cititor: {$p['cod_cititor']}, Carte: {$p['cod_carte']}, Returnat: {$p['data_returnare']}\n";
    }
} else {
    echo "Nu există probleme.\n";
}

// Verifică toate înregistrările active (după ambele criterii)
echo "\n2. Înregistrări cu status = 'activ':\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM imprumuturi WHERE status = 'activ'");
$total_status = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total: {$total_status['total']}\n";

echo "\n3. Înregistrări cu data_returnare IS NULL:\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM imprumuturi WHERE data_returnare IS NULL");
$total_null = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total: {$total_null['total']}\n";

// Verifică dublurile după status = 'activ'
echo "\n4. Dubluri (status = 'activ'):\n";
$dubluri = $pdo->query("
    SELECT cod_cititor, cod_carte, COUNT(*) as numar
    FROM imprumuturi
    WHERE status = 'activ'
    GROUP BY cod_cititor, cod_carte
    HAVING COUNT(*) > 1
")->fetchAll(PDO::FETCH_ASSOC);
if (count($dubluri) > 0) {
    echo "Găsite " . count($dubluri) . " dubluri!\n";
    foreach ($dubluri as $d) {
        echo "  Cititor: {$d['cod_cititor']}, Carte: {$d['cod_carte']}, Număr: {$d['numar']}\n";
    }
} else {
    echo "Nu există dubluri.\n";
}

// Verifică utilizatorii cu peste 6 cărți (status = 'activ')
echo "\n5. Utilizatori cu peste 6 cărți (status = 'activ'):\n";
$utilizatori = $pdo->query("
    SELECT cod_cititor, COUNT(*) as numar
    FROM imprumuturi
    WHERE status = 'activ'
    GROUP BY cod_cititor
    HAVING COUNT(*) > 6
")->fetchAll(PDO::FETCH_ASSOC);
if (count($utilizatori) > 0) {
    echo "Găsiți " . count($utilizatori) . " utilizatori!\n";
    foreach ($utilizatori as $u) {
        echo "  Cititor: {$u['cod_cititor']}, Cărți: {$u['numar']}\n";
    }
} else {
    echo "Toți utilizatorii au maxim 6 cărți.\n";
}
?>

