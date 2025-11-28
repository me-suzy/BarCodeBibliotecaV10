<?php
/**
 * Verificare Instalare Sistem Statute
 * 
 * Acest script verifică dacă toate modificările pentru sistemul de statute
 * s-au salvat corect după repararea MySQL
 */

require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificare Instalare Statute</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
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
            margin-bottom: 20px;
        }
        .check {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f8f9fa;
        }
        .check h3 {
            margin-top: 0;
            color: #667eea;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
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
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #764ba2;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificare Instalare Sistem Statute</h1>
        <p style="color: #666;">Verificare completă pentru a confirma că toate modificările s-au salvat</p>
        
        <?php
        $erori = [];
        $succese = [];
        $avertizari = [];
        
        try {
            // ============================================
            // VERIFICARE 1: Tabelul statute_cititori
            // ============================================
            echo '<div class="check">';
            echo '<h3>1️⃣ Verificare: Tabelul statute_cititori</h3>';
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'statute_cititori'");
            $tabel_exista = $stmt->rowCount() > 0;
            
            if ($tabel_exista) {
                echo '<div class="success">✅ Tabelul <code>statute_cititori</code> există!</div>';
                $succese[] = "Tabelul statute_cititori există";
                
                // Verifică structura
                $stmt = $pdo->query("DESCRIBE statute_cititori");
                $structura = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h4>Structură tabel:</h4>';
                echo '<table>';
                echo '<tr><th>Câmp</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th></tr>';
                foreach ($structura as $camp) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($camp['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($camp['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($camp['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($camp['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($camp['Default'] ?? 'NULL') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Verifică datele
                $stmt = $pdo->query("SELECT COUNT(*) FROM statute_cititori");
                $numar_statute = $stmt->fetchColumn();
                
                if ($numar_statute > 0) {
                    echo '<div class="success">✅ Tabelul conține <strong>' . $numar_statute . '</strong> statute!</div>';
                    $succese[] = "Tabelul conține $numar_statute statute";
                    
                    // Afișează statutele
                    $stmt = $pdo->query("SELECT * FROM statute_cititori ORDER BY cod_statut");
                    $statute = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo '<h4>Statute configurate:</h4>';
                    echo '<table>';
                    echo '<tr><th>Cod</th><th>Nume Statut</th><th>Limită Totală</th><th>Descriere</th></tr>';
                    foreach ($statute as $statut) {
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($statut['cod_statut']) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($statut['nume_statut']) . '</td>';
                        echo '<td>' . htmlspecialchars($statut['limita_totala']) . ' cărți</td>';
                        echo '<td>' . htmlspecialchars($statut['descriere'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    
                    if ($numar_statute < 7) {
                        echo '<div class="warning">⚠️ Ar trebui să fie 7 statute (11-17). Lipsesc ' . (7 - $numar_statute) . ' statute!</div>';
                        $avertizari[] = "Lipsesc " . (7 - $numar_statute) . " statute";
                    }
                } else {
                    echo '<div class="error">❌ Tabelul există dar este GOL! Trebuie să inserezi statutele.</div>';
                    $erori[] = "Tabelul statute_cititori este gol";
                }
            } else {
                echo '<div class="error">❌ Tabelul <code>statute_cititori</code> NU există! Trebuie creat.</div>';
                $erori[] = "Tabelul statute_cititori nu există";
            }
            echo '</div>';
            
            // ============================================
            // VERIFICARE 2: Coloana statut în cititori
            // ============================================
            echo '<div class="check">';
            echo '<h3>2️⃣ Verificare: Coloana statut în tabelul cititori</h3>';
            
            $stmt = $pdo->query("DESCRIBE cititori");
            $coloane = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $coloana_statut = null;
            foreach ($coloane as $coloana) {
                if ($coloana['Field'] === 'statut') {
                    $coloana_statut = $coloana;
                    break;
                }
            }
            
            if ($coloana_statut) {
                echo '<div class="success">✅ Coloana <code>statut</code> există în tabelul <code>cititori</code>!</div>';
                $succese[] = "Coloana statut există";
                
                echo '<h4>Detalii coloană:</h4>';
                echo '<table>';
                echo '<tr><th>Proprietate</th><th>Valoare</th></tr>';
                echo '<tr><td><strong>Nume</strong></td><td>' . htmlspecialchars($coloana_statut['Field']) . '</td></tr>';
                echo '<tr><td><strong>Tip</strong></td><td>' . htmlspecialchars($coloana_statut['Type']) . '</td></tr>';
                echo '<tr><td><strong>Null</strong></td><td>' . htmlspecialchars($coloana_statut['Null']) . '</td></tr>';
                echo '<tr><td><strong>Default</strong></td><td>' . htmlspecialchars($coloana_statut['Default'] ?? 'NULL') . '</td></tr>';
                echo '</table>';
                
                // Verifică câți cititori au statut
                $stmt = $pdo->query("SELECT COUNT(*) FROM cititori WHERE statut IS NOT NULL AND statut != ''");
                $cititori_cu_statut = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM cititori");
                $total_cititori = $stmt->fetchColumn();
                
                if ($cititori_cu_statut > 0) {
                    echo '<div class="success">✅ <strong>' . $cititori_cu_statut . '</strong> din <strong>' . $total_cititori . '</strong> cititori au statut configurat!</div>';
                    $succese[] = "$cititori_cu_statut/$total_cititori cititori au statut";
                    
                    // Distribuție pe statut
                    $stmt = $pdo->query("SELECT statut, COUNT(*) as numar FROM cititori WHERE statut IS NOT NULL AND statut != '' GROUP BY statut ORDER BY statut");
                    $distributie = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($distributie)) {
                        echo '<h4>Distribuție cititori pe statut:</h4>';
                        echo '<table>';
                        echo '<tr><th>Statut</th><th>Număr Cititori</th></tr>';
                        foreach ($distributie as $dist) {
                            echo '<tr>';
                            echo '<td><strong>' . htmlspecialchars($dist['statut']) . '</strong></td>';
                            echo '<td>' . htmlspecialchars($dist['numar']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                } else {
                    echo '<div class="warning">⚠️ Coloana există dar niciun cititor nu are statut configurat! Trebuie să actualizezi cititorii existenți.</div>';
                    $avertizari[] = "Niciun cititor nu are statut configurat";
                }
            } else {
                echo '<div class="error">❌ Coloana <code>statut</code> NU există în tabelul <code>cititori</code>! Trebuie adăugată.</div>';
                $erori[] = "Coloana statut nu există";
            }
            echo '</div>';
            
            // ============================================
            // VERIFICARE 3: Funcțiile PHP
            // ============================================
            echo '<div class="check">';
            echo '<h3>3️⃣ Verificare: Fișiere PHP (functions_statute.php)</h3>';
            
            if (file_exists('functions_statute.php')) {
                echo '<div class="success">✅ Fișierul <code>functions_statute.php</code> există!</div>';
                $succese[] = "Fișierul functions_statute.php există";
                
                // Verifică funcțiile
                require_once 'functions_statute.php';
                
                $functii_required = ['extrageStatutDinCod', 'getLimitaImprumut', 'poateImprumuta', 'actualizeazaStatutCititor'];
                $functii_gasite = [];
                
                foreach ($functii_required as $functie) {
                    if (function_exists($functie)) {
                        $functii_gasite[] = $functie;
                    }
                }
                
                if (count($functii_gasite) === count($functii_required)) {
                    echo '<div class="success">✅ Toate funcțiile necesare există!</div>';
                    $succese[] = "Toate funcțiile PHP există";
                } else {
                    echo '<div class="warning">⚠️ Lipsesc funcții: ' . implode(', ', array_diff($functii_required, $functii_gasite)) . '</div>';
                    $avertizari[] = "Lipsesc funcții PHP";
                }
            } else {
                echo '<div class="error">❌ Fișierul <code>functions_statute.php</code> NU există!</div>';
                $erori[] = "Fișierul functions_statute.php nu există";
            }
            echo '</div>';
            
            // ============================================
            // VERIFICARE 4: Integrare în index.php
            // ============================================
            echo '<div class="check">';
            echo '<h3>4️⃣ Verificare: Integrare în index.php</h3>';
            
            if (file_exists('index.php')) {
                $index_content = file_get_contents('index.php');
                
                if (strpos($index_content, 'functions_statute.php') !== false) {
                    echo '<div class="success">✅ <code>index.php</code> include <code>functions_statute.php</code>!</div>';
                    $succese[] = "index.php include functions_statute.php";
                } else {
                    echo '<div class="error">❌ <code>index.php</code> NU include <code>functions_statute.php</code>!</div>';
                    $erori[] = "index.php nu include functions_statute.php";
                }
                
                if (strpos($index_content, 'poateImprumuta') !== false) {
                    echo '<div class="success">✅ <code>index.php</code> folosește funcția <code>poateImprumuta()</code>!</div>';
                    $succese[] = "index.php folosește poateImprumuta()";
                } else {
                    echo '<div class="error">❌ <code>index.php</code> NU folosește funcția <code>poateImprumuta()</code>!</div>';
                    $erori[] = "index.php nu folosește poateImprumuta()";
                }
            } else {
                echo '<div class="error">❌ Fișierul <code>index.php</code> NU există!</div>';
                $erori[] = "index.php nu există";
            }
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="error">❌ Eroare la verificare: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $erori[] = "Eroare PDO: " . $e->getMessage();
        }
        
        // ============================================
        // REZUMAT FINAL
        // ============================================
        echo '<div class="check">';
        echo '<h3>📊 Rezumat Verificare</h3>';
        
        if (empty($erori) && empty($avertizari)) {
            echo '<div class="success">';
            echo '<h4>✅ Instalare Completă și Funcțională!</h4>';
            echo '<p>Toate componentele sistemului de statute sunt instalate și configurate corect!</p>';
            echo '<ul>';
            foreach ($succese as $succes) {
                echo '<li>✅ ' . htmlspecialchars($succes) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            if (!empty($erori)) {
                echo '<div class="error">';
                echo '<h4>❌ Probleme Identificate: ' . count($erori) . '</h4>';
                echo '<ul>';
                foreach ($erori as $eroare) {
                    echo '<li>❌ ' . htmlspecialchars($eroare) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (!empty($avertizari)) {
                echo '<div class="warning">';
                echo '<h4>⚠️ Avertismente: ' . count($avertizari) . '</h4>';
                echo '<ul>';
                foreach ($avertizari as $avertizare) {
                    echo '<li>⚠️ ' . htmlspecialchars($avertizare) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (!empty($succese)) {
                echo '<div class="info">';
                echo '<h4>✅ Componente OK:</h4>';
                echo '<ul>';
                foreach ($succese as $succes) {
                    echo '<li>✅ ' . htmlspecialchars($succes) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
        echo '</div>';
        
        // ============================================
        // ACȚIUNI RECOMANDATE
        // ============================================
        if (!empty($erori)) {
            echo '<div class="check">';
            echo '<h3>🔧 Acțiuni Recomandate</h3>';
            
            if (in_array("Tabelul statute_cititori nu există", $erori) || in_array("Tabelul statute_cititori este gol", $erori)) {
                echo '<div class="info">';
                echo '<h4>📋 Trebuie să creezi/populezi tabelul statute_cititori:</h4>';
                echo '<p><a href="instaleaza_statute.php" class="btn btn-success">▶️ Rulează instaleaza_statute.php</a></p>';
                echo '</div>';
            }
            
            if (in_array("Coloana statut nu există", $erori)) {
                echo '<div class="info">';
                echo '<h4>📋 Trebuie să adaugi coloana statut:</h4>';
                echo '<p>Rulează în phpMyAdmin sau MySQL:</p>';
                echo '<pre>USE biblioteca;
ALTER TABLE cititori 
ADD COLUMN statut VARCHAR(2) DEFAULT \'14\' AFTER cod_bare;</pre>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // ============================================
        // BUTOANE UTILE
        // ============================================
        echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">';
        echo '<h3>🔗 Acțiuni Rapide</h3>';
        echo '<a href="instaleaza_statute.php" class="btn btn-success">📊 Instalează/Reinstalează Statute</a>';
        echo '<a href="verifica_instalare_statute.php" class="btn">🔄 Reîncarcă Verificare</a>';
        echo '<a href="index.php" class="btn">🏠 Pagina Principală</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

