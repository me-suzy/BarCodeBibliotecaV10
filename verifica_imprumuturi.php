<?php
require_once 'config.php';

echo "Verificare împrumuturi...\n\n";

$result = $pdo->query("
    SELECT 
        cod_cititor,
        COUNT(*) as numar
    FROM imprumuturi
    WHERE data_returnare IS NULL
    GROUP BY cod_cititor
    HAVING COUNT(*) > 6
    ORDER BY numar DESC
")->fetchAll();

if (count($result) > 0) {
    echo "Utilizatori cu peste 6 cărți:\n";
    foreach ($result as $r) {
        echo $r['cod_cititor'] . ': ' . $r['numar'] . ' cărți' . PHP_EOL;
    }
} else {
    echo "✅ Toți utilizatorii au maxim 6 cărți!\n";
}

$dubluri = $pdo->query("
    SELECT 
        cod_cititor,
        cod_carte,
        COUNT(*) as numar
    FROM imprumuturi
    WHERE data_returnare IS NULL
    GROUP BY cod_cititor, cod_carte
    HAVING COUNT(*) > 1
")->fetchAll();

if (count($dubluri) > 0) {
    echo "\nDubluri găsite: " . count($dubluri) . "\n";
} else {
    echo "\n✅ Nu există dubluri!\n";
}
?>
