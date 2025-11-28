<?php
/**
 * Script pentru instalarea sistemului de statute cărți
 * Rulează acest script o singură dată pentru a configura sistemul
 */

require_once 'config.php';

echo "🔧 Instalare sistem statute cărți...\n\n";

try {
    // Activează buffering-ul pentru PDO
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // Rulează scriptul SQL
    $sql = file_get_contents('update_database_statute_carti.sql');
    
    // Împarte în comenzi separate
    $comenzi = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($comenzi as $comanda) {
        if (empty($comanda) || stripos($comanda, 'USE ') === 0) {
            continue;
        }
        
        // Sare peste comentarii și comenzi goale
        $comanda_clean = trim($comanda);
        if (empty($comanda_clean) || strpos($comanda_clean, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($comanda_clean);
        } catch (PDOException $e) {
            // Ignoră erorile de "already exists" și "Duplicate"
            if (stripos($e->getMessage(), 'already exists') === false && 
                stripos($e->getMessage(), 'Duplicate') === false &&
                stripos($e->getMessage(), 'există deja') === false) {
                echo "⚠️ Eroare: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "✅ Tabelul statute_carti creat cu succes!\n";
    echo "✅ Coloana statut adăugată în tabelul carti!\n\n";
    
    // Verifică statutele - folosește fetchAll() pentru a finaliza query-ul
    $stmt = $pdo->query("SELECT * FROM statute_carti ORDER BY cod_statut");
    $statute = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null; // Închide statement-ul
    
    echo "📊 Statute configurate:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s | %-40s | %-15s | %s\n", "COD", "NUME STATUT", "DURATA (zile)", "DESCRIERE");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($statute as $statut) {
        printf(
            "%-5s | %-40s | %-15s | %s\n",
            $statut['cod_statut'],
            $statut['nume_statut'],
            $statut['durata_imprumut_zile'],
            substr($statut['descriere'] ?? '', 0, 30)
        );
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Verifică câte cărți au fiecare statut - folosește fetchAll() pentru a finaliza query-ul
    $stmt = $pdo->query("
        SELECT statut, COUNT(*) as numar 
        FROM carti 
        GROUP BY statut 
        ORDER BY statut
    ");
    $distributie = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null; // Închide statement-ul
    
    echo "📚 Distribuție cărți pe statut:\n";
    foreach ($distributie as $row) {
        echo "  - Statut {$row['statut']}: {$row['numar']} cărți\n";
    }
    
    echo "\n✅ Instalare completă!\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    exit(1);
}

