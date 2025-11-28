<?php
// actualizeaza_statuturi_cititori.php - Actualizează statuturile pentru toți cititorii care au statut NULL
require_once 'config.php';
require_once 'functions_statute.php';

echo "🔄 Actualizare statuturi cititori...\n\n";

// Obține toți cititorii care au statut NULL sau gol
$stmt = $pdo->query("SELECT id, cod_bare, nume, prenume, statut FROM cititori WHERE statut IS NULL OR statut = ''");
$cititori_fara_statut = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Găsiți " . count($cititori_fara_statut) . " cititori fără statut.\n\n";

$actualizati = 0;
$eroare = 0;

foreach ($cititori_fara_statut as $cititor) {
    // Extrage statutul din codul de bare
    $statut_din_cod = extrageStatutDinCodBare($cititor['cod_bare'], $pdo);
    
    if ($statut_din_cod) {
        try {
            $stmt_update = $pdo->prepare("UPDATE cititori SET statut = ? WHERE id = ?");
            $stmt_update->execute([$statut_din_cod, $cititor['id']]);
            echo "✅ ID {$cititor['id']} ({$cititor['cod_bare']} - {$cititor['nume']} {$cititor['prenume']}): Statut actualizat la '{$statut_din_cod}'\n";
            $actualizati++;
        } catch (PDOException $e) {
            echo "❌ ID {$cititor['id']}: Eroare - " . $e->getMessage() . "\n";
            $eroare++;
        }
    } else {
        // Dacă nu se poate extrage statutul, setează la 14
        try {
            $stmt_update = $pdo->prepare("UPDATE cititori SET statut = '14' WHERE id = ?");
            $stmt_update->execute([$cititor['id']]);
            echo "⚠️ ID {$cititor['id']} ({$cititor['cod_bare']} - {$cititor['nume']} {$cititor['prenume']}): Statut setat la '14' (nu s-a putut extrage din cod)\n";
            $actualizati++;
        } catch (PDOException $e) {
            echo "❌ ID {$cititor['id']}: Eroare - " . $e->getMessage() . "\n";
            $eroare++;
        }
    }
}

echo "\n📊 Rezumat:\n";
echo "✅ Actualizați: {$actualizati}\n";
echo "❌ Erori: {$eroare}\n";
echo "\n✅ Actualizare completă!\n";
?>


