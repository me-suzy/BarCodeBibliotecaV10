<?php
/**
 * Script de diagnosticare MySQL pentru XAMPP
 * 
 * Acest script verifică:
 * - Dacă MySQL rulează
 * - Dacă port-ul 3306 este ocupat
 * - Dacă există fișiere .lock
 * - Dacă există erori în log-uri
 * - Dacă există servicii MySQL concurente
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosticare MySQL - XAMPP</title>
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
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 3px solid #667eea;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnosticare MySQL - XAMPP</h1>
        
        <?php
        $xampp_path = 'C:\\xampp';
        $mysql_path = $xampp_path . '\\mysql';
        $data_path = $mysql_path . '\\data';
        $error_log = $data_path . '\\mysql_error.log';
        
        $probleme = [];
        $solutii = [];
        
        // ============================================
        // VERIFICARE 1: MySQL Rulează?
        // ============================================
        echo '<div class="check">';
        echo '<h3>1️⃣ Verificare: MySQL Rulează?</h3>';
        
        try {
            $pdo = new PDO("mysql:host=localhost;port=3306", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<div class="success">✅ MySQL rulează și răspunde la conexiuni!</div>';
        } catch (PDOException $e) {
            echo '<div class="error">❌ MySQL NU rulează sau nu răspunde!</div>';
            echo '<div class="info">Eroare: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $probleme[] = "MySQL nu rulează";
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE 2: Port 3306 Ocupat?
        // ============================================
        echo '<div class="check">';
        echo '<h3>2️⃣ Verificare: Port 3306 Ocupat?</h3>';
        
        $port_check = @shell_exec('netstat -ano | findstr :3306 2>nul');
        
        if ($port_check) {
            $lines = explode("\n", trim($port_check));
            $processes = [];
            
            foreach ($lines as $line) {
                if (preg_match('/LISTENING\s+(\d+)/', $line, $matches)) {
                    $pid = $matches[1];
                    $processes[] = $pid;
                }
            }
            
            if (!empty($processes)) {
                echo '<div class="warning">⚠️ Port 3306 este ocupat de procese:</div>';
                echo '<pre>' . htmlspecialchars($port_check) . '</pre>';
                
                echo '<div class="solution">';
                echo '<h4>🔧 Soluție:</h4>';
                echo '<p>Oprește procesele care ocupă port-ul:</p>';
                foreach (array_unique($processes) as $pid) {
                    $process_name = @shell_exec("tasklist /FI \"PID eq $pid\" /FO CSV /NH 2>nul");
                    echo '<p><strong>PID ' . htmlspecialchars($pid) . ':</strong></p>';
                    echo '<pre>taskkill /PID ' . htmlspecialchars($pid) . ' /F</pre>';
                    if ($process_name) {
                        echo '<p style="font-size: 0.9em; color: #666;">' . htmlspecialchars(trim($process_name)) . '</p>';
                    }
                }
                echo '</div>';
                
                $probleme[] = "Port 3306 ocupat";
            } else {
                echo '<div class="success">✅ Port 3306 este liber!</div>';
            }
        } else {
            echo '<div class="success">✅ Port 3306 este liber!</div>';
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE 3: Fișiere .lock
        // ============================================
        echo '<div class="check">';
        echo '<h3>3️⃣ Verificare: Fișiere .lock</h3>';
        
        if (is_dir($data_path)) {
            $lock_files = [];
            $files = scandir($data_path);
            
            foreach ($files as $file) {
                if (strpos($file, '.lock') !== false) {
                    $lock_files[] = $file;
                }
            }
            
            if (!empty($lock_files)) {
                echo '<div class="error">❌ Găsite ' . count($lock_files) . ' fișiere .lock:</div>';
                echo '<ul>';
                foreach ($lock_files as $lock_file) {
                    $full_path = $data_path . '\\' . $lock_file;
                    $size = file_exists($full_path) ? filesize($full_path) : 0;
                    echo '<li><code>' . htmlspecialchars($lock_file) . '</code> (' . $size . ' bytes)</li>';
                }
                echo '</ul>';
                
                echo '<div class="solution">';
                echo '<h4>🔧 Soluție:</h4>';
                echo '<p>Șterge aceste fișiere manual:</p>';
                echo '<pre>';
                foreach ($lock_files as $lock_file) {
                    echo 'del "' . htmlspecialchars($data_path . '\\' . $lock_file) . '"' . "\n";
                }
                echo '</pre>';
                echo '<p><strong>SAU</strong> folosește PowerShell (ca Administrator):</p>';
                echo '<pre>';
                foreach ($lock_files as $lock_file) {
                    echo 'Remove-Item "' . htmlspecialchars($data_path . '\\' . $lock_file) . '" -Force' . "\n";
                }
                echo '</pre>';
                echo '</div>';
                
                $probleme[] = "Fișiere .lock găsite";
            } else {
                echo '<div class="success">✅ Nu există fișiere .lock!</div>';
            }
        } else {
            echo '<div class="error">❌ Folder-ul data nu există: <code>' . htmlspecialchars($data_path) . '</code></div>';
            $probleme[] = "Folder data inexistent";
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE 4: Fișier ibdata1
        // ============================================
        echo '<div class="check">';
        echo '<h3>4️⃣ Verificare: Fișier ibdata1</h3>';
        
        $ibdata1 = $data_path . '\\ibdata1';
        if (file_exists($ibdata1)) {
            $size = filesize($ibdata1);
            $size_mb = round($size / 1024 / 1024, 2);
            
            if ($size == 0) {
                echo '<div class="error">❌ Fișierul ibdata1 are 0 bytes - CORUPT!</div>';
                echo '<div class="solution">';
                echo '<h4>🔧 Soluție:</h4>';
                echo '<p>Fișierul este corupt. Trebuie recreat:</p>';
                echo '<ol>';
                echo '<li>Oprește XAMPP complet</li>';
                echo '<li>Fă backup la <code>' . htmlspecialchars($data_path) . '</code></li>';
                echo '<li>Șterge: <code>ibdata1</code>, <code>ib_logfile0</code>, <code>ib_logfile1</code></li>';
                echo '<li>Repornește XAMPP - MySQL va recrea automat aceste fișiere</li>';
                echo '</ol>';
                echo '</div>';
                $probleme[] = "ibdata1 corupt (0 bytes)";
            } else {
                echo '<div class="success">✅ Fișierul ibdata1 există și are ' . $size_mb . ' MB</div>';
            }
        } else {
            echo '<div class="warning">⚠️ Fișierul ibdata1 nu există (va fi creat la primul start)</div>';
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE 5: Log-uri de eroare
        // ============================================
        echo '<div class="check">';
        echo '<h3>5️⃣ Verificare: Log-uri de Eroare</h3>';
        
        if (file_exists($error_log)) {
            $log_content = file_get_contents($error_log);
            $log_lines = explode("\n", $log_content);
            $recent_errors = array_slice($log_lines, -30); // Ultimele 30 linii
            
            $has_errors = false;
            foreach ($recent_errors as $line) {
                if (stripos($line, 'ERROR') !== false || stripos($line, 'FATAL') !== false) {
                    $has_errors = true;
                    break;
                }
            }
            
            if ($has_errors) {
                echo '<div class="error">❌ Găsite erori în log!</div>';
                echo '<div class="info">Ultimele erori din <code>' . htmlspecialchars($error_log) . '</code>:</div>';
                echo '<pre style="max-height: 300px; overflow-y: auto;">';
                echo htmlspecialchars(implode("\n", $recent_errors));
                echo '</pre>';
                $probleme[] = "Erori în log-uri";
            } else {
                echo '<div class="success">✅ Nu s-au găsit erori recente în log!</div>';
            }
        } else {
            echo '<div class="info">ℹ️ Fișierul de log nu există încă: <code>' . htmlspecialchars($error_log) . '</code></div>';
        }
        echo '</div>';
        
        // ============================================
        // VERIFICARE 6: Servicii Windows MySQL
        // ============================================
        echo '<div class="check">';
        echo '<h3>6️⃣ Verificare: Servicii Windows MySQL</h3>';
        
        $services = @shell_exec('sc query | findstr /I "mysql" 2>nul');
        
        if ($services) {
            echo '<div class="warning">⚠️ Găsite servicii MySQL în Windows:</div>';
            echo '<pre>' . htmlspecialchars($services) . '</pre>';
            
            echo '<div class="solution">';
            echo '<h4>🔧 Soluție:</h4>';
            echo '<p>Oprește serviciile MySQL concurente:</p>';
            echo '<ol>';
            echo '<li>Win + R → <code>services.msc</code> → Enter</li>';
            echo '<li>Caută "MySQL" sau "MySQL80"</li>';
            echo '<li>Click dreapta → <strong>Stop</strong></li>';
            echo '<li>Click dreapta → <strong>Properties</strong> → <strong>Startup type: Disabled</strong> → OK</li>';
            echo '</ol>';
            echo '</div>';
            $probleme[] = "Servicii MySQL concurente";
        } else {
            echo '<div class="success">✅ Nu există servicii MySQL concurente!</div>';
        }
        echo '</div>';
        
        // ============================================
        // REZUMAT FINAL
        // ============================================
        echo '<div class="check">';
        echo '<h3>📊 Rezumat Diagnosticare</h3>';
        
        if (empty($probleme)) {
            echo '<div class="success">';
            echo '<h4>✅ Toate verificările au trecut!</h4>';
            echo '<p>MySQL ar trebui să funcționeze corect. Dacă tot nu pornește:</p>';
            echo '<ul>';
            echo '<li>Verifică log-urile din XAMPP Control Panel → MySQL → Logs</li>';
            echo '<li>Repornește computerul</li>';
            echo '<li>Reinstalează XAMPP dacă problema persistă</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h4>❌ Probleme identificate: ' . count($probleme) . '</h4>';
            echo '<ul>';
            foreach ($probleme as $problema) {
                echo '<li>' . htmlspecialchars($problema) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="solution">';
            echo '<h4>🚀 Pași Recomandați (în ordine):</h4>';
            echo '<ol>';
            echo '<li><strong>Oprește toate procesele MySQL</strong> (port 3306 și servicii Windows)</li>';
            echo '<li><strong>Șterge fișierele .lock</strong> din <code>' . htmlspecialchars($data_path) . '</code></li>';
            echo '<li><strong>Verifică ibdata1</strong> - dacă e 0 bytes, șterge-l și lasă MySQL să-l recreeze</li>';
            echo '<li><strong>Repornește XAMPP complet</strong> (Quit → Start din nou)</li>';
            echo '<li><strong>Încearcă să pornești MySQL</strong> din XAMPP Control Panel</li>';
            echo '</ol>';
            echo '</div>';
        }
        echo '</div>';
        
        // ============================================
        // BUTOANE UTILE
        // ============================================
        echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">';
        echo '<h3>🔗 Acțiuni Rapide</h3>';
        echo '<a href="index.php" class="btn">← Înapoi la aplicație</a>';
        echo '<a href="instaleaza_statute.php" class="btn">📊 Instalează Statute</a>';
        echo '<a href="diagnosticare_mysql.php" class="btn">🔄 Reîncarcă Diagnosticare</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

