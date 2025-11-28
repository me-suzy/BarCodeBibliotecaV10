<?php
/**
 * Script complet pentru curățarea bazei de date
 * Șterge dublurile și limitează la maxim 6 cărți per utilizator
 * Rulează: php curatare_completa.php
 */

require_once 'config.php';

echo "🧹 CURĂȚARE COMPLETĂ BAZA DE DATE\n";
echo "═══════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();
    
    // ============================================
    // PARTEA 1: Șterge dublurile (aceeași carte, același utilizator, nerețurnate)
    // ============================================
    echo "📋 PASUL 1: Ștergere dubluri...\n";
    
    // Găsește toate dublurile
    $dubluri = $pdo->query("
        SELECT 
            cod_cititor,
            cod_carte,
            GROUP_CONCAT(id ORDER BY data_imprumut ASC SEPARATOR ',') as ids
        FROM imprumuturi
        WHERE data_returnare IS NULL
        GROUP BY cod_cititor, cod_carte
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_dubluri_sterse = 0;
    
    if (count($dubluri) > 0) {
        echo "Găsite " . count($dubluri) . " seturi de dubluri.\n";
        
        foreach ($dubluri as $dublu) {
            $ids = explode(',', $dublu['ids']);
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
    
    $total_carti_peste_limita_sterse = 0;
    
    if (count($utilizatori_peste_6) > 0) {
        echo "Găsiți " . count($utilizatori_peste_6) . " utilizatori cu peste 6 cărți.\n";
        
        foreach ($utilizatori_peste_6 as $utilizator) {
            // Obține toate cărțile ordonate după data împrumutului
            $stmt_carti = $pdo->prepare("
                SELECT id
                FROM imprumuturi
                WHERE cod_cititor = ? AND data_returnare IS NULL
                ORDER BY data_imprumut ASC
            ");
            $stmt_carti->execute([$utilizator['cod_cititor']]);
            $toate_cartile = $stmt_carti->fetchAll(PDO::FETCH_COLUMN);
            
            // Păstrează primele 6, șterge restul
            $carti_de_sters = array_slice($toate_cartile, 6);
            
            foreach ($carti_de_sters as $id_sters) {
                $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                $stmt->execute([$id_sters]);
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

