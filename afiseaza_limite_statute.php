<?php
/**
 * Script pentru afișarea limitelor de împrumut pe statut
 */

require_once 'config.php';

try {
    $stmt = $pdo->query("
        SELECT 
            cod_statut,
            nume_statut,
            limita_totala,
            descriere
        FROM statute_cititori
        ORDER BY limita_totala DESC
    ");
    
    $statute = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n";
    echo "📊 LIMITE ÎMPRUMUT PE STATUT\n";
    echo str_repeat("=", 90) . "\n";
    printf("%-5s | %-40s | %-15s | %s\n", "COD", "NUME STATUT", "LIMITA CĂRȚI", "DESCRIERE");
    echo str_repeat("-", 90) . "\n";
    
    foreach ($statute as $statut) {
        printf(
            "%-5s | %-40s | %-15s | %s\n",
            $statut['cod_statut'],
            $statut['nume_statut'],
            $statut['limita_totala'] . ' cărți',
            $statut['descriere'] ?? '-'
        );
    }
    
    echo str_repeat("=", 90) . "\n\n";
    
    // Distribuție cititori
    echo "📈 DISTRIBUȚIE CITITORI PE STATUT:\n";
    echo str_repeat("=", 60) . "\n";
    
    $stmt2 = $pdo->query("
        SELECT 
            c.statut,
            s.nume_statut,
            s.limita_totala,
            COUNT(c.id) as numar_cititori
        FROM cititori c
        LEFT JOIN statute_cititori s ON c.statut = s.cod_statut
        GROUP BY c.statut, s.nume_statut, s.limita_totala
        ORDER BY s.limita_totala DESC
    ");
    
    $distributie = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-5s | %-40s | %-15s | %s\n", "COD", "NUME STATUT", "LIMITA", "NR. CITITORI");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($distributie as $row) {
        printf(
            "%-5s | %-40s | %-15s | %d\n",
            $row['statut'] ?? 'NULL',
            $row['nume_statut'] ?? 'Nespecificat',
            ($row['limita_totala'] ?? 0) . ' cărți',
            $row['numar_cititori']
        );
    }
    
    echo str_repeat("=", 60) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
}

