<?php
/**
 * Script pentru curățarea bazei de date: șterge dublurile și limitează la maxim 6 cărți per utilizator
 * Rulează acest fișier în browser: http://localhost/curatare_imprumuturi.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curățare Împrumuturi - Ștergere Dubluri și Limitare 6 Cărți</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
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
            margin: 10px 5px;
        }
        button:hover {
            background: #c82333;
        }
        .btn-primary {
            background: #667eea;
        }
        .btn-primary:hover {
            background: #5568d3;
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
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Curățare Împrumuturi - Ștergere Dubluri și Limitare 6 Cărți</h1>

        <?php
        $erori = [];
        $succese = [];
        $info = [];
        $dubluri_gasite = [];
        $utilizatori_peste_limita = [];

        try {
            // ============================================
            // PARTEA 1: Găsește dublurile
            // ============================================
            $info[] = "🔍 PASUL 1: Căutare dubluri în tabelul imprumuturi...";
            
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
                $info[] = "⚠️ Găsite " . count($dubluri) . " seturi de dubluri.";
                
                foreach ($dubluri as $dublu) {
                    $ids = explode(',', $dublu['ids']);
                    $id_de_pastrat = $ids[0];
                    $ids_de_sters = array_slice($ids, 1);
                    
                    // Obține informații
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
                        'cititor_nume' => ($info_carte['nume'] ?? '') . ' ' . ($info_carte['prenume'] ?? ''),
                        'carte' => $dublu['cod_carte'],
                        'carte_titlu' => $info_carte['titlu'] ?? 'N/A',
                        'numar_dubluri' => count($ids),
                        'id_pastrat' => $id_de_pastrat,
                        'ids_sters' => $ids_de_sters
                    ];
                }
            } else {
                $succese[] = "✅ Nu există dubluri în baza de date!";
            }

            // ============================================
            // PARTEA 2: Găsește utilizatorii cu peste 6 cărți
            // ============================================
            $info[] = "🔍 PASUL 2: Căutare utilizatori cu peste 6 cărți...";
            
            $stmt = $pdo->query("
                SELECT 
                    cod_cititor,
                    COUNT(*) as numar_carti
                FROM imprumuturi
                WHERE data_returnare IS NULL
                GROUP BY cod_cititor
                HAVING COUNT(*) > 6
                ORDER BY numar_carti DESC
            ");
            
            $utilizatori_peste_6 = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($utilizatori_peste_6) > 0) {
                $info[] = "⚠️ Găsiți " . count($utilizatori_peste_6) . " utilizatori cu peste 6 cărți.";
                
                foreach ($utilizatori_peste_6 as $utilizator) {
                    // Obține primele 6 cărți (cele mai vechi) și restul
                    $stmt_carti = $pdo->prepare("
                        SELECT id, cod_carte, data_imprumut
                        FROM imprumuturi
                        WHERE cod_cititor = ? AND data_returnare IS NULL
                        ORDER BY data_imprumut ASC
                    ");
                    $stmt_carti->execute([$utilizator['cod_cititor']]);
                    $toate_cartile = $stmt_carti->fetchAll(PDO::FETCH_ASSOC);
                    
                    $carti_de_pastrat = array_slice($toate_cartile, 0, 6);
                    $carti_de_sters = array_slice($toate_cartile, 6);
                    
                    // Obține numele cititorului
                    $stmt_nume = $pdo->prepare("SELECT nume, prenume FROM cititori WHERE cod_bare = ?");
                    $stmt_nume->execute([$utilizator['cod_cititor']]);
                    $nume_cititor = $stmt_nume->fetch(PDO::FETCH_ASSOC);
                    
                    $utilizatori_peste_limita[] = [
                        'cititor' => $utilizator['cod_cititor'],
                        'cititor_nume' => ($nume_cititor['nume'] ?? '') . ' ' . ($nume_cititor['prenume'] ?? ''),
                        'numar_total' => $utilizator['numar_carti'],
                        'carti_pastrat' => $carti_de_pastrat,
                        'carti_sters' => $carti_de_sters
                    ];
                }
            } else {
                $succese[] = "✅ Toți utilizatorii au maxim 6 cărți!";
            }

            // ============================================
            // PARTEA 3: Execută ștergerea dacă s-a făcut cererea
            // ============================================
            if (isset($_POST['executa_curatare']) && $_POST['executa_curatare'] === 'da') {
                $total_dubluri_sterse = 0;
                $total_carti_peste_limita_sterse = 0;
                
                // Șterge dublurile
                foreach ($dubluri_gasite as $dublu) {
                    foreach ($dublu['ids_sters'] as $id_sters) {
                        $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                        $stmt->execute([$id_sters]);
                        $total_dubluri_sterse++;
                    }
                }
                
                // Șterge cărțile peste limita de 6
                foreach ($utilizatori_peste_limita as $utilizator) {
                    foreach ($utilizator['carti_sters'] as $carte_sters) {
                        $stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE id = ?");
                        $stmt->execute([$carte_sters['id']]);
                        $total_carti_peste_limita_sterse++;
                    }
                }
                
                $succese[] = "✅ Curățare finalizată cu succes!";
                $succese[] = "📊 Dubluri șterse: $total_dubluri_sterse";
                $succese[] = "📊 Cărți peste limită șterse: $total_carti_peste_limita_sterse";
                $succese[] = "📊 Total înregistrări șterse: " . ($total_dubluri_sterse + $total_carti_peste_limita_sterse);
                
                // Re-încarcă datele după ștergere
                $dubluri_gasite = [];
                $utilizatori_peste_limita = [];
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

        // Afișează dublurile găsite
        if (count($dubluri_gasite) > 0 && !isset($_POST['executa_curatare'])) {
            echo "<div class='section'>";
            echo "<h2>📋 Dubluri găsite (" . count($dubluri_gasite) . " seturi):</h2>";
            echo "<table>";
            echo "<thead><tr><th>Cititor</th><th>Carte</th><th>Număr Dubluri</th><th>Acțiune</th></tr></thead>";
            echo "<tbody>";
            
            foreach ($dubluri_gasite as $dublu) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($dublu['cititor_nume']) . "</strong><br><small>" . htmlspecialchars($dublu['cititor']) . "</small></td>";
                echo "<td><strong>" . htmlspecialchars($dublu['carte_titlu']) . "</strong><br><small>" . htmlspecialchars($dublu['carte']) . "</small></td>";
                echo "<td>" . $dublu['numar_dubluri'] . " înregistrări<br><small>Păstrat: ID " . $dublu['id_pastrat'] . "<br>Șters: " . count($dublu['ids_sters']) . " înregistrări</small></td>";
                echo "<td>✅ Prima înregistrare va fi păstrată</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
            echo "</div>";
        }

        // Afișează utilizatorii peste limită
        if (count($utilizatori_peste_limita) > 0 && !isset($_POST['executa_curatare'])) {
            echo "<div class='section'>";
            echo "<h2>⚠️ Utilizatori cu peste 6 cărți (" . count($utilizatori_peste_limita) . " utilizatori):</h2>";
            echo "<table>";
            echo "<thead><tr><th>Cititor</th><th>Număr Total</th><th>Cărți de păstrat (primele 6)</th><th>Cărți de șters</th></tr></thead>";
            echo "<tbody>";
            
            foreach ($utilizatori_peste_limita as $utilizator) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($utilizator['cititor_nume']) . "</strong><br><small>" . htmlspecialchars($utilizator['cititor']) . "</small></td>";
                echo "<td><strong style='color: #dc3545;'>" . $utilizator['numar_total'] . " cărți</strong></td>";
                echo "<td><small>" . count($utilizator['carti_pastrat']) . " cărți (primele 6)</small></td>";
                echo "<td><strong style='color: #dc3545;'>" . count($utilizator['carti_sters']) . " cărți vor fi șterse</strong></td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
            echo "</div>";
        }

        // Buton pentru executare
        if ((count($dubluri_gasite) > 0 || count($utilizatori_peste_limita) > 0) && !isset($_POST['executa_curatare'])) {
            echo "<div class='warning'>";
            echo "<h3>⚠️ ATENȚIE!</h3>";
            echo "<p>Urmează să fie șterse:</p>";
            echo "<ul>";
            if (count($dubluri_gasite) > 0) {
                $total_dubluri = 0;
                foreach ($dubluri_gasite as $d) {
                    $total_dubluri += count($d['ids_sters']);
                }
                echo "<li><strong>" . $total_dubluri . " dubluri</strong> (păstrând prima înregistrare pentru fiecare)</li>";
            }
            if (count($utilizatori_peste_limita) > 0) {
                $total_peste_limita = 0;
                foreach ($utilizatori_peste_limita as $u) {
                    $total_peste_limita += count($u['carti_sters']);
                }
                echo "<li><strong>" . $total_peste_limita . " cărți peste limita de 6</strong> (păstrând primele 6 cărți pentru fiecare utilizator)</li>";
            }
            echo "</ul>";
            echo "<p><strong>Această acțiune nu poate fi anulată!</strong></p>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='executa_curatare' value='da'>";
            echo "<button type='submit' onclick='return confirm(\"Ești SIGUR că vrei să ștergi aceste înregistrări? Această acțiune nu poate fi anulată!\\n\\nDubluri de șters: " . (count($dubluri_gasite) > 0 ? array_sum(array_map(function($d) { return count($d['ids_sters']); }, $dubluri_gasite)) : 0) . "\\nCărți peste limită de șters: " . (count($utilizatori_peste_limita) > 0 ? array_sum(array_map(function($u) { return count($u['carti_sters']); }, $utilizatori_peste_limita)) : 0) . "\")'>🗑️ Execută Curățarea</button>";
            echo "</form>";
            echo "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
            <a href="imprumuturi.php" style="text-decoration: none;">
                <button class="btn-primary">➡️ Mergi la Împrumuturi</button>
            </a>
            <a href="index.php" style="text-decoration: none;">
                <button style="background: #6c757d;">🏠 Acasă</button>
            </a>
        </div>
    </div>
</body>
</html>

