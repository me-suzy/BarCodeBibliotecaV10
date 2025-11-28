<?php
// check_vizare_an_nou.php - Verificare automată la începutul anului
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$an_curent = date('Y');

// Contorizează câți cititori au permise expirate
$sql = "SELECT COUNT(*) as total_nevizati 
        FROM cititori 
        WHERE YEAR(ultima_vizare) < ? OR ultima_vizare IS NULL";

$stmt = $pdo->prepare($sql);
$stmt->execute([$an_curent]);
$rezultat = $stmt->fetch(PDO::FETCH_ASSOC);

$total_nevizati = $rezultat['total_nevizati'];

// Salvează în log
$log_mesaj = date('Y-m-d H:i:s') . " - Cititori cu permise nevizate pentru {$an_curent}: {$total_nevizati}\n";
file_put_contents('logs/vizare_check.log', $log_mesaj, FILE_APPEND);

// Opțional: Trimite email către admin
if ($total_nevizati > 0) {
    $mesaj_email = "ATENȚIE: Există {$total_nevizati} cititori cu permise nevizate pentru {$an_curent}.";
    // mail('admin@biblioteca.ro', 'Permise Nevizate', $mesaj_email);
}

echo "Check complet. Total nevizați: {$total_nevizati}\n";
?>