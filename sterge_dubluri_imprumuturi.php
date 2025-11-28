<?php
/**
 * Script pentru ștergerea dublurilor din tabelul imprumuturi
 * Rulează acest fișier în browser: http://localhost/sterge_dubluri_imprumuturi.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ștergere Dubluri Împrumuturi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        button {
            background: #dc3545;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Ștergere Dubluri Împrumuturi</h1>

        <?php
        $erori = [];
        $succese = [];
        $info = [];
        $dubluri_gasite = [];

        try {
            // Găsește dublurile (cărți împrumutate de același utilizator, fără returnare)
            $info[] = "Căutare dubluri în tabelul imprumuturi...";
            
            $stmt = $pdo->query("
                SELECT 
                    cod_cititor,
                    cod_carte,
                    COUNT(*) as numar_dubluri,
                    GROUP_CONCAT(id ORDER BY data_imprumut ASC) as ids
                FROM imprumuturi
                WHERE data_returnare IS NULL
                GROUP BY cod_cititor, cod_carte
                HAVING COUNT(*) > 1
                ORDER BY cod_cititor, cod_carte
            ");
            
            $dubluri = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($dubluri) > 0) {
                $info[] = "Găsite " . count($dubluri) . " seturi de dubluri.";
                
                foreach ($dubluri as $dublu) {
                    $ids = explode(',', $dublu['ids']);
                    $numar_dubluri = count($ids);
                    
                    // Păstrează primul împrumut (cel mai vechi), șterge restul
                    $id_de_pastrat = $ids[0];
                    $ids_de_sters = array_slice($ids, 1);
                    
                    // Obține informații despre carte și cititor
                    $stmt_info = $pdo->prepare("
                        SELECT 
                            c.titlu,
                            cit.nume,
                            cit.prenume
                        FROM imprumuturi i
                        JOIN carti c ON i.cod_carte = c.cod_bare
                        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
                        WHERE i.id = ?
                    ");
                    $stmt_info->execute([$id_de_pastrat]);
                    $info_carte = $stmt_info->fetch(PDO::FETCH_ASSOC);
                    
                    $dubluri_gasite[] = [
                        'cititor' => $dublu['cod_cititor'],
                        'cititor_nume' => $info_carte['nume'] . ' ' . $info_carte['prenume'],
                        'carte' => $dublu['cod_carte'],
                        'carte_titlu' => $info_carte['titlu'],
                        'numar_dubluri' => $numar_dubluri,
                        'id_pastrat' => $id_de_pastrat,
                        'ids_sters' => $ids_de_sters
                    ];
                }
                
                // Dacă s-a făcut cerere de ștergere
                if (isset($_POST['sterge_dubluri']) && $_POST['sterge_dubluri'] === 'da') {
                    $total_sterse = 0;
                    
                    foreach ($dubluri_gasite as $dublu) {
                        foreach ($dublu['ids_sters'] as $id_sters) {
                            $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                            $stmt->execute([$id_sters]);
                            $total_sterse++;
                        }
                    }
                    
                    $succese[] = "✅ Șterse $total_sterse dubluri cu succes!";
                    $succese[] = "✅ Păstrate " . count($dubluri_gasite) . " înregistrări originale.";
                    
                    // Re-încarcă lista după ștergere
                    $dubluri_gasite = [];
                }
            } else {
                $succese[] = "✅ Nu există dubluri în baza de date!";
            }

        } catch (PDOException $e) {
            $erori[] = "❌ Eroare PDO: " . $e->getMessage();
        } catch (Exception $e) {
            $erori[] = "❌ Eroare: " . $e->getMessage();
        }

        // Afișează rezultatele
        if (!empty($info)) {
            foreach ($info as $msg) {
                echo "<div class='info'>$msg</div>";
            }
        }

        if (!empty($succese)) {
            foreach ($succese as $msg) {
                echo "<div class='success'>$msg</div>";
            }
        }

        if (!empty($erori)) {
            foreach ($erori as $msg) {
                echo "<div class='error'>$msg</div>";
            }
        }

        if (count($dubluri_gasite) > 0 && !isset($_POST['sterge_dubluri'])) {
            echo "<div class='warning'><strong>⚠️ ATENȚIE!</strong> Au fost găsite dubluri. Verifică lista de mai jos înainte de a șterge.</div>";
            
            echo "<h3>Dubluri găsite:</h3>";
            echo "<table>";
            echo "<thead><tr><th>Cititor</th><th>Carte</th><th>Număr Dubluri</th><th>ID-uri de șters</th></tr></thead>";
            echo "<tbody>";
            
            foreach ($dubluri_gasite as $dublu) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($dublu['cititor_nume']) . "</strong><br><small>" . htmlspecialchars($dublu['cititor']) . "</small></td>";
                echo "<td><strong>" . htmlspecialchars($dublu['carte_titlu']) . "</strong><br><small>" . htmlspecialchars($dublu['carte']) . "</small></td>";
                echo "<td>" . $dublu['numar_dubluri'] . " înregistrări</td>";
                echo "<td>Păstrat: ID " . $dublu['id_pastrat'] . "<br>Șters: " . implode(', ', $dublu['ids_sters']) . "</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='sterge_dubluri' value='da'>";
            echo "<button type='submit' onclick='return confirm(\"Ești sigur că vrei să ștergi dublurile? Această acțiune nu poate fi anulată!\")'>🗑️ Șterge Dublurile</button>";
            echo "</form>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
            <a href="imprumuturi.php" style="text-decoration: none;">
                <button style="background: #667eea;">➡️ Mergi la Împrumuturi</button>
            </a>
            <a href="index.php" style="text-decoration: none; margin-left: 10px;">
                <button style="background: #6c757d;">🏠 Acasă</button>
            </a>
        </div>
    </div>
</body>
</html>

