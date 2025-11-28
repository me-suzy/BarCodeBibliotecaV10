<?php
/**
 * Script final pentru curățarea completă a bazei de date
 * 1. Actualizează statusul pentru cărțile returnate
 * 2. Șterge dublurile
 * 3. Limitează la maxim 6 cărți per utilizator
 */

require_once 'config.php';

echo "🧹 CURĂȚARE FINALĂ BAZA DE DATE\n";
echo "═══════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();
    
    // ============================================
    // PARTEA 1: Actualizează statusul pentru cărțile returnate
    // ============================================
    echo "📋 PASUL 1: Actualizare status pentru cărțile returnate...\n";
    
    $stmt = $pdo->exec("
        UPDATE imprumuturi 
        SET status = 'returnat' 
        WHERE data_returnare IS NOT NULL AND status = 'activ'
    ");
    
    echo "✅ Actualizate $stmt înregistrări.\n\n";
    
    // ============================================
    // PARTEA 2: Șterge dublurile (status = 'activ')
    // ============================================
    echo "📋 PASUL 2: Ștergere dubluri (status = 'activ')...\n";
    
    $dubluri = $pdo->query("
        SELECT 
            cod_cititor,
            cod_carte,
            GROUP_CONCAT(id ORDER BY data_imprumut ASC SEPARATOR ',') as ids
        FROM imprumuturi
        WHERE status = 'activ' AND data_returnare IS NULL
        GROUP BY cod_cititor, cod_carte
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_dubluri_sterse = 0;
    
    if (count($dubluri) > 0) {
        echo "Găsite " . count($dubluri) . " seturi de dubluri.\n";
        
        foreach ($dubluri as $dublu) {
            $ids = explode(',', $dublu['ids']);
            echo "  Cititor: {$dublu['cod_cititor']}, Carte: {$dublu['cod_carte']}, Total: " . count($ids) . " înregistrări\n";
            
            // Păstrează prima înregistrare (cea mai veche), șterge restul
            $ids_de_sters = array_slice($ids, 1);
            
            foreach ($ids_de_sters as $id_sters) {
                $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                $stmt->execute([$id_sters]);
                $total_dubluri_sterse++;
            }
        }
    } else {
        echo "Nu există dubluri.\n";
    }
    
    echo "✅ Dubluri șterse: $total_dubluri_sterse\n\n";
    
    // ============================================
    // PARTEA 3: Limitează la maxim 6 cărți per utilizator
    // ============================================
    echo "📋 PASUL 3: Limitare la maxim 6 cărți per utilizator...\n";
    
    $utilizatori_peste_6 = $pdo->query("
        SELECT 
            cod_cititor,
            COUNT(*) as numar_carti
        FROM imprumuturi
        WHERE status = 'activ' AND data_returnare IS NULL
        GROUP BY cod_cititor
        HAVING COUNT(*) > 6
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_carti_peste_limita_sterse = 0;
    
    if (count($utilizatori_peste_6) > 0) {
        echo "Găsiți " . count($utilizatori_peste_6) . " utilizatori cu peste 6 cărți.\n";
        
        foreach ($utilizatori_peste_6 as $utilizator) {
            echo "  Utilizator: {$utilizator['cod_cititor']}, Total cărți: {$utilizator['numar_carti']}\n";
            
            // Obține toate cărțile ordonate după data împrumutului
            $stmt_carti = $pdo->prepare("
                SELECT id, cod_carte
                FROM imprumuturi
                WHERE cod_cititor = ? AND status = 'activ' AND data_returnare IS NULL
                ORDER BY data_imprumut ASC
            ");
            $stmt_carti->execute([$utilizator['cod_cititor']]);
            $toate_cartile = $stmt_carti->fetchAll(PDO::FETCH_ASSOC);
            
            // Păstrează primele 6, șterge restul
            $carti_de_pastrat = array_slice($toate_cartile, 0, 6);
            $carti_de_sters = array_slice($toate_cartile, 6);
            
            echo "    Păstrate: " . count($carti_de_pastrat) . " cărți\n";
            echo "    De șters: " . count($carti_de_sters) . " cărți\n";
            
            foreach ($carti_de_sters as $carte_sters) {
                $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                $stmt->execute([$carte_sters['id']]);
                $total_carti_peste_limita_sterse++;
            }
        }
    } else {
        echo "Toți utilizatorii au maxim 6 cărți.\n";
    }
    
    echo "✅ Cărți peste limită șterse: $total_carti_peste_limita_sterse\n\n";
    
    // Confirmă tranzacția
    $pdo->commit();
    
    // ============================================
    // REZUMAT
    // ============================================
    echo "═══════════════════════════════════════\n";
    echo "✅ CURĂȚARE FINALIZATĂ!\n";
    echo "═══════════════════════════════════════\n";
    echo "📊 Status actualizat pentru cărți returnate: $stmt\n";
    echo "📊 Dubluri șterse: $total_dubluri_sterse\n";
    echo "📊 Cărți peste limită șterse: $total_carti_peste_limita_sterse\n";
    echo "📊 Total înregistrări șterse: " . ($total_dubluri_sterse + $total_carti_peste_limita_sterse) . "\n";
    echo "═══════════════════════════════════════\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "❌ Eroare PDO: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    exit(1);
}
?>

