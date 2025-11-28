<?php
/**
 * Script pentru executarea curățării bazei de date
 * Rulează: php executa_curatare.php
 */

require_once 'config.php';

echo "🧹 Început curățare baza de date...\n\n";

try {
    // Dezactivează verificările de siguranță temporar
    $pdo->exec('SET SQL_SAFE_UPDATES = 0');
    
    // ============================================
    // PARTEA 1: Șterge dublurile
    // ============================================
    echo "📋 PASUL 1: Căutare și ștergere dubluri...\n";
    
    // Găsește toate dublurile
    $dubluri = $pdo->query("
        SELECT 
            cod_cititor,
            cod_carte,
            COUNT(*) as numar,
            GROUP_CONCAT(id ORDER BY data_imprumut ASC SEPARATOR ',') as ids
        FROM imprumuturi
        WHERE data_returnare IS NULL
        GROUP BY cod_cititor, cod_carte
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Găsite " . count($dubluri) . " seturi de dubluri.\n";
    
    $total_dubluri_sterse = 0;
    
    foreach ($dubluri as $dublu) {
        $ids = explode(',', $dublu['ids']);
        echo "Cititor: {$dublu['cod_cititor']}, Carte: {$dublu['cod_carte']}, Total: " . count($ids) . " înregistrări\n";
        
        // Păstrează prima înregistrare (cea mai veche), șterge restul
        $ids_de_sters = array_slice($ids, 1);
        
        foreach ($ids_de_sters as $id_sters) {
            $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
            $stmt->execute([$id_sters]);
            $total_dubluri_sterse++;
            echo "  Șters ID: $id_sters\n";
        }
    }
    
    echo "✅ Dubluri șterse: $total_dubluri_sterse\n\n";
    
    // ============================================
    // PARTEA 2: Limitează la maxim 6 cărți per utilizator
    // ============================================
    echo "📋 PASUL 2: Limitare la maxim 6 cărți per utilizator...\n";
    
    $utilizatori_peste_6 = $pdo->query("
        SELECT 
            cod_cititor,
            COUNT(*) as numar_carti
        FROM imprumuturi
        WHERE data_returnare IS NULL
        GROUP BY cod_cititor
        HAVING COUNT(*) > 6
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Găsiți " . count($utilizatori_peste_6) . " utilizatori cu peste 6 cărți.\n";
    
    $total_carti_peste_limita_sterse = 0;
    
    foreach ($utilizatori_peste_6 as $utilizator) {
        echo "Utilizator: {$utilizator['cod_cititor']}, Total cărți: {$utilizator['numar_carti']}\n";
        
        // Obține toate cărțile ordonate după data împrumutului
        $stmt_carti = $pdo->prepare("
            SELECT id, cod_carte, data_imprumut
            FROM imprumuturi
            WHERE cod_cititor = ? AND data_returnare IS NULL
            ORDER BY data_imprumut ASC
        ");
        $stmt_carti->execute([$utilizator['cod_cititor']]);
        $toate_cartile = $stmt_carti->fetchAll(PDO::FETCH_ASSOC);
        
        // Păstrează primele 6, șterge restul
        $carti_de_pastrat = array_slice($toate_cartile, 0, 6);
        $carti_de_sters = array_slice($toate_cartile, 6);
        
        echo "  Păstrate: " . count($carti_de_pastrat) . " cărți\n";
        echo "  De șters: " . count($carti_de_sters) . " cărți\n";
        
        foreach ($carti_de_sters as $carte_sters) {
            $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
            $stmt->execute([$carte_sters['id']]);
            $total_carti_peste_limita_sterse++;
            echo "  Șters ID: {$carte_sters['id']}, Carte: {$carte_sters['cod_carte']}\n";
        }
    }
    
    echo "✅ Cărți peste limită șterse: $total_carti_peste_limita_sterse\n\n";
    
    // Reactivează verificările de siguranță
    $pdo->exec('SET SQL_SAFE_UPDATES = 1');
    
    // ============================================
    // REZUMAT
    // ============================================
    echo "═══════════════════════════════════════\n";
    echo "✅ CURĂȚARE FINALIZATĂ!\n";
    echo "═══════════════════════════════════════\n";
    echo "📊 Dubluri șterse: $total_dubluri_sterse\n";
    echo "📊 Cărți peste limită șterse: $total_carti_peste_limita_sterse\n";
    echo "📊 Total înregistrări șterse: " . ($total_dubluri_sterse + $total_carti_peste_limita_sterse) . "\n";
    echo "═══════════════════════════════════════\n";
    
} catch (PDOException $e) {
    echo "❌ Eroare PDO: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    exit(1);
}
?>

