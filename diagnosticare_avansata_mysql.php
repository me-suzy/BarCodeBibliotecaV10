<?php
/**
 * Diagnosticare Avansată MySQL - Identificare Erori Exacte
 * 
 * Acest script:
 * - Citește log-urile MySQL
 * - Verifică configurația my.ini
 * - Identifică erorile exacte care împiedică pornirea MySQL
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosticare Avansată MySQL</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
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
        .section {
            margin: 30px 0;
            padding: 20px;
            border-left: 4px solid #667eea;
            background: #f8f9fa;
        }
        .section h3 {
            margin-top: 0;
            color: #667eea;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
            line-height: 1.5;
            max-height: 500px;
            overflow-y: auto;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .solution {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
        }
        .solution h4 {
            margin-top: 0;
            color: #2196F3;
        }
        .highlight {
            background: #ffeb3b;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
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
        .error-line {
            background: #ffebee;
            border-left: 3px solid #f44336;
            padding: 5px 10px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnosticare Avansată MySQL</h1>
        <p style="color: #666;">Analiză detaliată pentru identificarea problemei exacte</p>
        
        <?php
        $xampp_path = 'C:\\xampp';
        $mysql_path = $xampp_path . '\\mysql';
        $mysql_bin = $mysql_path . '\\bin';
        $mysql_data = $mysql_path . '\\data';
        $error_log = $mysql_data . '\\mysql_error.log';
        $my_ini = $mysql_bin . '\\my.ini';
        
        // ============================================
        // SECȚIUNEA 1: Log-uri MySQL
        // ============================================
        echo '<div class="section">';
        echo '<h3>1️⃣ Analiză Log-uri MySQL</h3>';
        
        if (file_exists($error_log)) {
            $log_content = file_get_contents($error_log);
            $log_lines = explode("\n", $log_content);
            
            // Ultimele 100 linii
            $recent_lines = array_slice($log_lines, -100);
            $recent_content = implode("\n", $recent_lines);
            
            // Caută erori
            $errors = [];
            $warnings = [];
            $fatal = [];
            
            foreach ($recent_lines as $line) {
                if (stripos($line, 'FATAL') !== false || stripos($line, 'fatal') !== false) {
                    $fatal[] = $line;
                } elseif (stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false) {
                    $errors[] = $line;
                } elseif (stripos($line, 'WARNING') !== false || stripos($line, 'warning') !== false) {
                    $warnings[] = $line;
                }
            }
            
            echo '<div class="info">';
            echo '<strong>📁 Fișier:</strong> <code>' . htmlspecialchars($error_log) . '</code><br>';
            echo '<strong>📊 Total linii:</strong> ' . count($log_lines) . '<br>';
            echo '<strong>🔍 Analizat:</strong> Ultimele 100 linii';
            echo '</div>';
            
            if (!empty($fatal)) {
                echo '<div class="error">';
                echo '<h4>❌ Erori FATAL găsite: ' . count($fatal) . '</h4>';
                foreach (array_slice($fatal, -10) as $fatal_line) {
                    echo '<div class="error-line">' . htmlspecialchars($fatal_line) . '</div>';
                }
                echo '</div>';
            }
            
            if (!empty($errors)) {
                echo '<div class="error">';
                echo '<h4>❌ Erori găsite: ' . count($errors) . '</h4>';
                foreach (array_slice($errors, -15) as $error_line) {
                    echo '<div class="error-line">' . htmlspecialchars($error_line) . '</div>';
                }
                echo '</div>';
            }
            
            if (!empty($warnings)) {
                echo '<div class="warning">';
                echo '<h4>⚠️ Avertismente: ' . count($warnings) . '</h4>';
                foreach (array_slice($warnings, -10) as $warning_line) {
                    echo '<div>' . htmlspecialchars($warning_line) . '</div>';
                }
                echo '</div>';
            }
            
            if (empty($fatal) && empty($errors) && empty($warnings)) {
                echo '<div class="success">✅ Nu s-au găsit erori în ultimele 100 linii!</div>';
            }
            
            // Afișează ultimele linii complete
            echo '<h4>📋 Ultimele 50 linii din log:</h4>';
            echo '<pre>' . htmlspecialchars(implode("\n", array_slice($recent_lines, -50))) . '</pre>';
            
        } else {
            echo '<div class="warning">⚠️ Fișierul de log nu există: <code>' . htmlspecialchars($error_log) . '</code></div>';
            echo '<div class="info">ℹ️ MySQL nu a încercat să pornească sau log-ul nu a fost creat încă.</div>';
        }
        echo '</div>';
        
        // ============================================
        // SECȚIUNEA 2: Configurație my.ini
        // ============================================
        echo '<div class="section">';
        echo '<h3>2️⃣ Verificare Configurație my.ini</h3>';
        
        if (file_exists($my_ini)) {
            $ini_content = file_get_contents($my_ini);
            $ini_lines = explode("\n", $ini_content);
            
            echo '<div class="info">';
            echo '<strong>📁 Fișier:</strong> <code>' . htmlspecialchars($my_ini) . '</code><br>';
            echo '<strong>📏 Mărime:</strong> ' . filesize($my_ini) . ' bytes';
            echo '</div>';
            
            // Caută secțiunea [mysqld]
            $mysqld_section = false;
            $mysqld_lines = [];
            $in_mysqld = false;
            
            foreach ($ini_lines as $line_num => $line) {
                if (preg_match('/^\s*\[mysqld\]/i', $line)) {
                    $in_mysqld = true;
                    $mysqld_section = true;
                    $mysqld_lines[] = $line;
                } elseif ($in_mysqld) {
                    if (preg_match('/^\s*\[/', $line)) {
                        // Nouă secțiune
                        break;
                    }
                    $mysqld_lines[] = $line;
                }
            }
            
            if ($mysqld_section) {
                echo '<h4>📋 Secțiunea [mysqld]:</h4>';
                echo '<pre>' . htmlspecialchars(implode("\n", $mysqld_lines)) . '</pre>';
                
                // Verifică setări importante
                $checks = [
                    'port' => ['expected' => '3306', 'found' => false],
                    'datadir' => ['expected' => 'C:/xampp/mysql/data', 'found' => false],
                    'basedir' => ['expected' => 'C:/xampp/mysql', 'found' => false],
                ];
                
                foreach ($mysqld_lines as $line) {
                    foreach ($checks as $key => &$check) {
                        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/i', $line)) {
                            $check['found'] = true;
                            $check['value'] = trim($line);
                        }
                    }
                }
                
                echo '<h4>✅ Verificări Configurație:</h4>';
                echo '<table>';
                echo '<tr><th>Setare</th><th>Status</th><th>Valoare</th></tr>';
                foreach ($checks as $key => $check) {
                    $status = $check['found'] ? '✅ Găsit' : '❌ Lipsă';
                    $value = isset($check['value']) ? htmlspecialchars($check['value']) : '-';
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
                    echo '<td>' . $status . '</td>';
                    echo '<td><code>' . $value . '</code></td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Caută setări problematice
                $problematic = [];
                foreach ($mysqld_lines as $line) {
                    if (preg_match('/^\s*innodb_force_recovery\s*=\s*[1-6]/i', $line)) {
                        $problematic[] = "⚠️ " . htmlspecialchars(trim($line)) . " - Poate cauza probleme!";
                    }
                    if (preg_match('/^\s*skip-grant-tables/i', $line)) {
                        $problematic[] = "⚠️ " . htmlspecialchars(trim($line)) . " - Modul de recuperare activ!";
                    }
                }
                
                if (!empty($problematic)) {
                    echo '<div class="warning">';
                    echo '<h4>⚠️ Setări Potențial Problemice:</h4>';
                    foreach ($problematic as $prob) {
                        echo '<div>' . $prob . '</div>';
                    }
                    echo '</div>';
                }
                
            } else {
                echo '<div class="error">❌ Secțiunea [mysqld] nu a fost găsită în my.ini!</div>';
            }
            
        } else {
            echo '<div class="error">❌ Fișierul my.ini nu există: <code>' . htmlspecialchars($my_ini) . '</code></div>';
        }
        echo '</div>';
        
        // ============================================
        // SECȚIUNEA 3: Verificare Fișiere Critice
        // ============================================
        echo '<div class="section">';
        echo '<h3>3️⃣ Verificare Fișiere Critice</h3>';
        
        $critical_files = [
            'mysqld.exe' => $mysql_bin . '\\mysqld.exe',
            'mysql.exe' => $mysql_bin . '\\mysql.exe',
            'ibdata1' => $mysql_data . '\\ibdata1',
            'mysql_error.log' => $mysql_data . '\\mysql_error.log',
        ];
        
        echo '<table>';
        echo '<tr><th>Fișier</th><th>Cale</th><th>Status</th><th>Mărime</th></tr>';
        
        foreach ($critical_files as $name => $path) {
            $exists = file_exists($path);
            $status = $exists ? '✅ Există' : '❌ Lipsă';
            $size = $exists ? filesize($path) : 0;
            $size_display = $size > 0 ? number_format($size / 1024 / 1024, 2) . ' MB' : '0 bytes';
            
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($name) . '</strong></td>';
            echo '<td><code style="font-size: 0.85em;">' . htmlspecialchars($path) . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $size_display . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // ============================================
        // SECȚIUNEA 4: Soluții Recomandate
        // ============================================
        echo '<div class="section">';
        echo '<h3>4️⃣ Soluții Recomandate</h3>';
        
        // Analizează erorile găsite și oferă soluții specifice
        $has_innodb_error = false;
        $has_port_error = false;
        $has_permission_error = false;
        
        if (file_exists($error_log)) {
            $log_content_lower = strtolower(file_get_contents($error_log));
            
            if (strpos($log_content_lower, 'innodb') !== false && 
                (strpos($log_content_lower, 'error') !== false || strpos($log_content_lower, 'corrupt') !== false)) {
                $has_innodb_error = true;
            }
            
            if (strpos($log_content_lower, 'port') !== false && strpos($log_content_lower, 'bind') !== false) {
                $has_port_error = true;
            }
            
            if (strpos($log_content_lower, 'permission') !== false || strpos($log_content_lower, 'access denied') !== false) {
                $has_permission_error = true;
            }
        }
        
        if ($has_innodb_error) {
            echo '<div class="solution">';
            echo '<h4>🔧 Soluție: Problemă InnoDB</h4>';
            echo '<p>MySQL nu poate porni din cauza unei probleme cu InnoDB. Pași:</p>';
            echo '<ol>';
            echo '<li><strong>Fă backup:</strong> <code>xcopy C:\\xampp\\mysql\\data C:\\backup_mysql\\ /E /I /Y</code></li>';
            echo '<li><strong>Oprește XAMPP complet</strong></li>';
            echo '<li><strong>Șterge:</strong> <code>ibdata1</code>, <code>ib_logfile0</code>, <code>ib_logfile1</code></li>';
            echo '<li><strong>NU șterge folder-ele</strong> (biblioteca, mysql, etc.)</li>';
            echo '<li><strong>Repornește XAMPP</strong> → MySQL va recrea fișierele</li>';
            echo '</ol>';
            echo '</div>';
        }
        
        if ($has_port_error) {
            echo '<div class="solution">';
            echo '<h4>🔧 Soluție: Problemă Port</h4>';
            echo '<p>Port-ul 3306 este ocupat sau nu poate fi folosit.</p>';
            echo '<ol>';
            echo '<li>Rulează: <code>netstat -ano | findstr :3306</code></li>';
            echo '<li>Oprește procesele găsite: <code>taskkill /PID [PID] /F</code></li>';
            echo '<li>Sau schimbă port-ul în my.ini: <code>port=3307</code></li>';
            echo '</ol>';
            echo '</div>';
        }
        
        if ($has_permission_error) {
            echo '<div class="solution">';
            echo '<h4>🔧 Soluție: Problemă Permisiuni</h4>';
            echo '<p>MySQL nu are permisiuni să acceseze fișierele.</p>';
            echo '<ol>';
            echo '<li>Right-click pe <code>C:\\xampp\\mysql\\data</code></li>';
            echo '<li>Properties → Security → Edit</li>';
            echo '<li>Adaugă "Everyone" cu permisiuni "Full Control"</li>';
            echo '<li>Apply → OK</li>';
            echo '</ol>';
            echo '</div>';
        }
        
        // Soluție generică
        if (!$has_innodb_error && !$has_port_error && !$has_permission_error) {
            echo '<div class="solution">';
            echo '<h4>🔧 Soluție Generică</h4>';
            echo '<p>Încearcă aceste pași în ordine:</p>';
            echo '<ol>';
            echo '<li><strong>Rulează fix_mysql_xampp.ps1</strong> ca Administrator</li>';
            echo '<li><strong>Verifică my.ini</strong> - asigură-te că toate căile sunt corecte</li>';
            echo '<li><strong>Pornește MySQL manual:</strong> <code>cd C:\\xampp\\mysql\\bin && mysqld.exe --console</code></li>';
            echo '<li><strong>Copiază eroarea exactă</strong> și trimite-o pentru analiză</li>';
            echo '</ol>';
            echo '</div>';
        }
        echo '</div>';
        
        // ============================================
        // BUTOANE UTILE
        // ============================================
        echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">';
        echo '<h3>🔗 Acțiuni Rapide</h3>';
        echo '<a href="diagnosticare_mysql.php" class="btn">← Diagnosticare Simplă</a>';
        echo '<a href="diagnosticare_avansata_mysql.php" class="btn">🔄 Reîncarcă Analiză</a>';
        echo '<a href="index.php" class="btn">🏠 Pagina Principală</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

