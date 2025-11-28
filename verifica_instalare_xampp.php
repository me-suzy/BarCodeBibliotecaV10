<?php
/**
 * Verificare Instalare XAMPP și MySQL
 * 
 * Acest script verifică dacă XAMPP și MySQL sunt instalate corect
 * și găsește calea corectă
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificare Instalare XAMPP</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificare Instalare XAMPP și MySQL</h1>
        <p style="color: #666;">Verificare automată pentru găsirea căii corecte</p>
        
        <?php
        // Căi posibile pentru XAMPP
        $possible_paths = [
            'C:\\xampp\\mysql\\bin',
            'D:\\xampp\\mysql\\bin',
            'E:\\xampp\\mysql\\bin',
            'C:\\Program Files\\xampp\\mysql\\bin',
            'C:\\Program Files (x86)\\xampp\\mysql\\bin',
        ];
        
        $found_path = null;
        $found_files = [];
        
        // ============================================
        // VERIFICARE: Găsește MySQL
        // ============================================
        echo '<div class="check">';
        echo '<h3>1️⃣ Căutare MySQL în Căi Standard</h3>';
        
        foreach ($possible_paths as $path) {
            $mysqld = $path . '\\mysqld.exe';
            if (file_exists($mysqld)) {
                $found_path = $path;
                echo '<div class="success">✅ MySQL găsit: <code>' . htmlspecialchars($path) . '</code></div>';
                break;
            } else {
                echo '<div style="color: #999; font-size: 0.9em;">❌ Nu există: <code>' . htmlspecialchars($path) . '</code></div>';
            }
        }
        
        if (!$found_path) {
            echo '<div class="error">❌ MySQL nu a fost găsit în căile standard!</div>';
            echo '<div class="warning">';
            echo '<h4>⚠️ Posibile Cauze:</h4>';
            echo '<ul>';
            echo '<li>XAMPP nu este instalat în locația standard</li>';
            echo '<li>MySQL nu este instalat în XAMPP</li>';
            echo '<li>XAMPP este instalat într-o altă locație</li>';
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE: Fișiere Importante
        // ============================================
        if ($found_path) {
            echo '<div class="check">';
            echo '<h3>2️⃣ Verificare Fișiere Importante</h3>';
            
            $important_files = [
                'mysqld.exe' => 'Server MySQL',
                'mysql.exe' => 'Client MySQL',
                'my.ini' => 'Configurație MySQL',
            ];
            
            echo '<table>';
            echo '<tr><th>Fișier</th><th>Descriere</th><th>Status</th><th>Cale</th></tr>';
            
            foreach ($important_files as $file => $desc) {
                $full_path = $found_path . '\\' . $file;
                $exists = file_exists($full_path);
                $status = $exists ? '✅ Există' : '❌ Lipsă';
                $size = $exists ? filesize($full_path) : 0;
                $size_display = $size > 0 ? number_format($size / 1024, 2) . ' KB' : '-';
                
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($file) . '</strong></td>';
                echo '<td>' . htmlspecialchars($desc) . '</td>';
                echo '<td>' . $status . '</td>';
                echo '<td><code style="font-size: 0.85em;">' . htmlspecialchars($full_path) . '</code></td>';
                echo '</tr>';
                
                if ($exists) {
                    $found_files[$file] = $full_path;
                }
            }
            
            echo '</table>';
            echo '</div>';
            
            // ============================================
            // VERIFICARE: Configurație my.ini
            // ============================================
            if (isset($found_files['my.ini'])) {
                echo '<div class="check">';
                echo '<h3>3️⃣ Verificare Configurație my.ini</h3>';
                
                $ini_content = file_get_contents($found_files['my.ini']);
                $ini_lines = explode("\n", $ini_content);
                
                // Caută secțiunea [mysqld]
                $mysqld_section = false;
                $mysqld_lines = [];
                $in_mysqld = false;
                
                foreach ($ini_lines as $line) {
                    if (preg_match('/^\s*\[mysqld\]/i', $line)) {
                        $in_mysqld = true;
                        $mysqld_section = true;
                        $mysqld_lines[] = $line;
                    } elseif ($in_mysqld) {
                        if (preg_match('/^\s*\[/', $line)) {
                            break;
                        }
                        $mysqld_lines[] = $line;
                    }
                }
                
                if ($mysqld_section) {
                    echo '<div class="success">✅ Secțiunea [mysqld] găsită!</div>';
                    echo '<h4>Configurație [mysqld]:</h4>';
                    echo '<pre>' . htmlspecialchars(implode("\n", array_slice($mysqld_lines, 0, 30))) . '</pre>';
                } else {
                    echo '<div class="error">❌ Secțiunea [mysqld] nu a fost găsită!</div>';
                }
                echo '</div>';
            }
            
            // ============================================
            // INSTRUCȚIUNI PENTRU TEST MANUAL
            // ============================================
            echo '<div class="check">';
            echo '<h3>4️⃣ Test Manual MySQL</h3>';
            
            if (isset($found_files['mysqld.exe'])) {
                echo '<div class="info">';
                echo '<h4>📋 Pași pentru Test Manual:</h4>';
                echo '<ol>';
                echo '<li>Deschide <strong>Command Prompt ca Administrator</strong></li>';
                echo '<li>Rulează:</li>';
                echo '</ol>';
                echo '<pre>cd ' . htmlspecialchars($found_path) . '
mysqld.exe --console</pre>';
                echo '<p><strong>SAU</strong> rulează scriptul automat:</p>';
                echo '<p><a href="gaseste_mysql_xampp.bat" class="btn">▶️ Rulează gaseste_mysql_xampp.bat</a></p>';
                echo '<p class="warning">⏱️ Lasă fereastra deschisă 20 secunde și copiază TOT ce apare!</p>';
                echo '</div>';
            } else {
                echo '<div class="error">❌ mysqld.exe nu există! Nu se poate rula testul manual.</div>';
            }
            echo '</div>';
        } else {
            // ============================================
            // SOLUȚIE: Cale Manuală
            // ============================================
            echo '<div class="check">';
            echo '<h3>🔧 Soluție: Specifică Calea Manuală</h3>';
            echo '<div class="warning">';
            echo '<h4>MySQL nu a fost găsit automat. Te rog să specifici calea manuală:</h4>';
            echo '<ol>';
            echo '<li>Deschide <strong>XAMPP Control Panel</strong></li>';
            echo '<li>Vezi în josul ferestrei: <strong>"XAMPP Installation Directory"</strong></li>';
            echo '<li>Copiază calea (ex: <code>C:\\xampp</code> sau <code>D:\\xampp</code>)</li>';
            echo '<li>MySQL ar trebui să fie în: <code>[cale]\\mysql\\bin\\mysqld.exe</code></li>';
            echo '</ol>';
            echo '<p><strong>Verifică manual:</strong></p>';
            echo '<pre>1. Deschide File Explorer
2. Navighează la: [cale_xampp]\\mysql\\bin\\
3. Verifică dacă există mysqld.exe</pre>';
            echo '</div>';
            echo '</div>';
        }
        
        // ============================================
        // REZUMAT FINAL
        // ============================================
        echo '<div class="check">';
        echo '<h3>📊 Rezumat</h3>';
        
        if ($found_path) {
            echo '<div class="success">';
            echo '<h4>✅ MySQL Găsit!</h4>';
            echo '<p><strong>Cale:</strong> <code>' . htmlspecialchars($found_path) . '</code></p>';
            echo '<p><strong>Fișiere găsite:</strong> ' . count($found_files) . ' / ' . count($important_files) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h4>❌ MySQL Nu a Fost Găsit</h4>';
            echo '<p>Te rog să specifici manual calea XAMPP sau să verifici instalarea.</p>';
            echo '</div>';
        }
        echo '</div>';
        
        // ============================================
        // BUTOANE UTILE
        // ============================================
        echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">';
        echo '<h3>🔗 Acțiuni Rapide</h3>';
        if ($found_path) {
            echo '<a href="gaseste_mysql_xampp.bat" class="btn">▶️ Test MySQL Automat</a>';
        }
        echo '<a href="diagnosticare_mysql.php" class="btn">🔍 Diagnosticare Simplă</a>';
        echo '<a href="analiza_crash_mysql.php" class="btn">🔧 Analiză Crash</a>';
        echo '<a href="index.php" class="btn">🏠 Pagina Principală</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

