<?php
/**
 * Script pentru ștergerea finală a dublurilor
 * Rulează: php sterge_dubluri_final.php
 */

require_once 'config.php';

echo "🗑️ ȘTERGERE FINALĂ DUBLURI\n";
echo "═══════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();
    
    // Găsește toate dublurile (status = 'activ' sau data_returnare IS NULL)
    echo "Căutare dubluri...\n";
    
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
    
    $total_sterse = 0;
    
    if (count($dubluri) > 0) {
        echo "Găsite " . count($dubluri) . " seturi de dubluri.\n\n";
        
        foreach ($dubluri as $dublu) {
            $ids = explode(',', $dublu['ids']);
            echo "Cititor: {$dublu['cod_cititor']}, Carte: {$dublu['cod_carte']}\n";
            echo "  Total înregistrări: " . count($ids) . "\n";
            echo "  Păstrat: ID {$ids[0]}\n";
            
            // Șterge toate în afară de prima
            $ids_de_sters = array_slice($ids, 1);
            echo "  De șters: " . count($ids_de_sters) . " înregistrări\n";
            
            foreach ($ids_de_sters as $id_sters) {
                $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                $stmt->execute([$id_sters]);
                $total_sterse++;
                echo "    ✓ Șters ID: $id_sters\n";
            }
            echo "\n";
        }
    } else {
        echo "Nu există dubluri.\n";
    }
    
    // Limitează la 6 cărți
    echo "\nLimitare la maxim 6 cărți per utilizator...\n";
    
    $utilizatori = $pdo->query("
        SELECT cod_cititor, COUNT(*) as numar
        FROM imprumuturi
        WHERE status = 'activ' AND data_returnare IS NULL
        GROUP BY cod_cititor
        HAVING COUNT(*) > 6
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_peste_limita = 0;
    
    if (count($utilizatori) > 0) {
        foreach ($utilizatori as $u) {
            echo "Utilizator: {$u['cod_cititor']}, Cărți: {$u['numar']}\n";
            
            $stmt_carti = $pdo->prepare("
                SELECT id FROM imprumuturi
                WHERE cod_cititor = ? AND status = 'activ' AND data_returnare IS NULL
                ORDER BY data_imprumut ASC
            ");
            $stmt_carti->execute([$u['cod_cititor']]);
            $carti = $stmt_carti->fetchAll(PDO::FETCH_COLUMN);
            
            $de_sters = array_slice($carti, 6);
            echo "  Păstrate: 6 cărți\n";
            echo "  De șters: " . count($de_sters) . " cărți\n";
            
            foreach ($de_sters as $id) {
                $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                $stmt->execute([$id]);
                $total_peste_limita++;
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n═══════════════════════════════════════\n";
    echo "✅ FINALIZAT!\n";
    echo "═══════════════════════════════════════\n";
    echo "Dubluri șterse: $total_sterse\n";
    echo "Cărți peste limită șterse: $total_peste_limita\n";
    echo "Total: " . ($total_sterse + $total_peste_limita) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Eroare: " . $e->getMessage() . "\n";
}
?>

