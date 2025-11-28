<?php
require_once 'config.php';

echo "=== VERIFICARE FINALĂ ===\n\n";

$result = $pdo->query("
    SELECT cod_cititor, COUNT(*) as numar 
    FROM imprumuturi 
    WHERE data_returnare IS NULL 
    GROUP BY cod_cititor 
    ORDER BY numar DESC
")->fetchAll();

echo "Utilizatori cu cărți active:\n";
foreach ($result as $r) {
    echo "  {$r['cod_cititor']}: {$r['numar']} cărți\n";
}

$dubluri = $pdo->query("
    SELECT cod_cititor, cod_carte, COUNT(*) as numar
    FROM imprumuturi
    WHERE data_returnare IS NULL
    GROUP BY cod_cititor, cod_carte
    HAVING COUNT(*) > 1
")->fetchAll();

if (count($dubluri) > 0) {
    echo "\n⚠️ Dubluri găsite: " . count($dubluri) . "\n";
} else {
    echo "\n✅ Nu există dubluri!\n";
}
?>

