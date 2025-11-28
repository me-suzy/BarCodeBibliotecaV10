<?php
/**
 * Analiză Crash MySQL - Detectează probleme de crash instant
 * 
 * Acest script analizează log-urile pentru a identifica
 * de ce MySQL pornește dar se oprește imediat
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analiză Crash MySQL</title>
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
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .solution {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
        }
        .solution h3 {
            margin-top: 0;
            color: #2196F3;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
            line-height: 1.5;
            max-height: 400px;
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
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: 600;
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
        .step {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-left: 3px solid #667eea;
        }
        .step h4 {
            margin-top: 0;
            color: #667eea;
        }
        .timestamp {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Analiză Crash MySQL - Detectare Problemă</h1>
        <p style="color: #666;">Analiză pentru crash instant (MySQL pornește dar se oprește imediat)</p>
        
        <?php
        $error_log = 'C:\\xampp\\mysql\\data\\mysql_error.log';
        $mysql_bin = 'C:\\xampp\\mysql\\bin';
        $my_ini = $mysql_bin . '\\my.ini';
        
        // ============================================
        // DETECTARE: Crash Instant
        // ============================================
        echo '<div class="alert alert-warning">';
        echo '<h3>⚠️ Problemă Detectată: Crash Instant</h3>';
        echo '<p>MySQL pornește dar se oprește după câteva secunde. Aceasta indică o problemă critică care împiedică inițializarea completă.</p>';
        echo '</div>';
        
        // ============================================
        // ANALIZĂ LOG: Caută pattern-uri de crash
        // ============================================
        echo '<div class="solution">';
        echo '<h3>📊 Analiză Log-uri pentru Pattern-uri de Crash</h3>';
        
        if (file_exists($error_log)) {
            $log_content = file_get_contents($error_log);
            $log_lines = explode("\n", $log_content);
            
            // Ultimele 200 linii pentru analiză detaliată
            $recent_lines = array_slice($log_lines, -200);
            
            // Pattern-uri comune de crash
            $patterns = [
                'innodb' => ['InnoDB', 'innodb', 'ibdata', 'ib_logfile'],
                'plugin' => ['Plugin', 'plugin initialization', 'init function'],
                'port' => ['port', 'bind', 'address already in use'],
                'permission' => ['permission', 'access denied', 'cannot open', 'cannot create'],
                'corrupt' => ['corrupt', 'corrupted', 'damaged'],
                'memory' => ['memory', 'out of memory', 'malloc'],
                'socket' => ['socket', 'mysql.sock'],
            ];
            
            $found_patterns = [];
            $error_lines = [];
            
            foreach ($recent_lines as $line_num => $line) {
                $line_lower = strtolower($line);
                
                foreach ($patterns as $pattern_name => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (stripos($line, $keyword) !== false) {
                            if (!isset($found_patterns[$pattern_name])) {
                                $found_patterns[$pattern_name] = [];
                            }
                            $found_patterns[$pattern_name][] = $line;
                            $error_lines[] = $line;
                            break;
                        }
                    }
                }
            }
            
            if (!empty($found_patterns)) {
                echo '<h4>🔍 Pattern-uri Identificate:</h4>';
                
                foreach ($found_patterns as $pattern_name => $lines) {
                    echo '<div class="step">';
                    echo '<h4>' . ucfirst($pattern_name) . ' (' . count($lines) . ' apariții)</h4>';
                    echo '<pre>';
                    foreach (array_slice($lines, -5) as $line) {
                        echo htmlspecialchars($line) . "\n";
                    }
                    echo '</pre>';
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-info">';
                echo '<p>Nu s-au găsit pattern-uri specifice în log. Eroarea poate fi prea gravă sau log-ul nu se scrie corect.</p>';
                echo '</div>';
            }
            
            // Afișează ultimele linii relevante
            echo '<h4>📋 Ultimele Linii Relevante din Log:</h4>';
            echo '<pre>';
            $relevant_lines = array_filter($recent_lines, function($line) {
                return stripos($line, 'error') !== false || 
                       stripos($line, 'fatal') !== false ||
                       stripos($line, 'warning') !== false ||
                       stripos($line, 'shutdown') !== false ||
                       stripos($line, 'started') !== false;
            });
            foreach (array_slice($relevant_lines, -30) as $line) {
                echo htmlspecialchars($line) . "\n";
            }
            echo '</pre>';
            
        } else {
            echo '<div class="alert alert-danger">';
            echo '<p>❌ Fișierul de log nu există: <code>' . htmlspecialchars($error_log) . '</code></p>';
            echo '<p>MySQL nu a încercat să pornească sau log-ul nu a fost creat.</p>';
            echo '</div>';
        }
        echo '</div>';
        
        // ============================================
        // SOLUȚII SPECIFICE
        // ============================================
        echo '<div class="solution">';
        echo '<h3>🔧 Soluții Recomandate (În Ordine de Prioritate)</h3>';
        
        // Soluția 1: Test Manual
        echo '<div class="step">';
        echo '<h4>1️⃣ PRIORITATE MARE: Test Manual MySQL</h4>';
        echo '<p>Rulează MySQL manual pentru a vedea eroarea exactă în timp real:</p>';
        echo '<pre>cd C:\\xampp\\mysql\\bin
mysqld.exe --console</pre>';
        echo '<p><strong>SAU</strong> rulează scriptul automat:</p>';
        echo '<p><a href="testeaza_mysql_manual.bat" class="btn">▶️ Rulează testeaza_mysql_manual.bat</a></p>';
        echo '<p class="timestamp">⏱️ Lasă-l să ruleze 15 secunde și copiază TOT ce apare!</p>';
        echo '</div>';
        
        // Soluția 2: Reset InnoDB
        echo '<div class="step">';
        echo '<h4>2️⃣ Reset InnoDB (Dacă eroarea e InnoDB)</h4>';
        echo '<p><strong>⚠️ Fă BACKUP mai întâi!</strong></p>';
        echo '<pre>xcopy C:\\xampp\\mysql\\data C:\\backup_mysql_urgent\\ /E /I /Y</pre>';
        echo '<ol>';
        echo '<li>Oprește XAMPP complet (Quit)</li>';
        echo '<li>Navighează la: <code>C:\\xampp\\mysql\\data\\</code></li>';
        echo '<li>Șterge DOAR: <code>ibdata1</code>, <code>ib_logfile0</code>, <code>ib_logfile1</code>, <code>aria_log_control</code></li>';
        echo '<li><strong>NU șterge folder-ele!</strong> (biblioteca, mysql, performance_schema)</li>';
        echo '<li>Pornește XAMPP → Start MySQL</li>';
        echo '</ol>';
        echo '</div>';
        
        // Soluția 3: Verificare my.ini
        echo '<div class="step">';
        echo '<h4>3️⃣ Verificare Configurație my.ini</h4>';
        echo '<p>Editează: <code>C:\\xampp\\mysql\\bin\\my.ini</code></p>';
        echo '<p><strong>Caută și comentează</strong> (pune <code>#</code> în față):</p>';
        echo '<pre># innodb_force_recovery=1
# skip-grant-tables
# innodb_fast_shutdown=0</pre>';
        echo '<p><strong>Asigură-te că există:</strong></p>';
        echo '<pre>[mysqld]
port=3306
socket="C:/xampp/mysql/mysql.sock"
basedir="C:/xampp/mysql"
tmpdir="C:/xampp/tmp"
datadir="C:/xampp/mysql/data"</pre>';
        echo '</div>';
        
        // Soluția 4: Permisiuni
        echo '<div class="step">';
        echo '<h4>4️⃣ Verificare Permisiuni</h4>';
        echo '<ol>';
        echo '<li>Right-click pe <code>C:\\xampp\\mysql\\data</code></li>';
        echo '<li>Properties → Security → Edit</li>';
        echo '<li>Adaugă "Everyone" cu "Full Control"</li>';
        echo '<li>Apply → OK</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '</div>';
        
        // ============================================
        // INSTRUCȚIUNI FINALE
        // ============================================
        echo '<div class="alert alert-info">';
        echo '<h3>📋 Ce să Trimiti pentru Analiză Completă</h3>';
        echo '<ol>';
        echo '<li><strong>Output complet</strong> de la <code>mysqld.exe --console</code> (15 secunde)</li>';
        echo '<li><strong>Secțiunea [mysqld]</strong> din <code>my.ini</code> (primele 50 linii)</li>';
        echo '<li><strong>Ultimele 100 linii</strong> din <code>mysql_error.log</code></li>';
        echo '</ol>';
        echo '<p>Cu aceste informații, voi identifica EXACT ce blochează MySQL!</p>';
        echo '</div>';
        
        // ============================================
        // BUTOANE UTILE
        // ============================================
        echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">';
        echo '<h3>🔗 Acțiuni Rapide</h3>';
        echo '<a href="testeaza_mysql_manual.bat" class="btn">▶️ Test MySQL Manual</a>';
        echo '<a href="backup_mysql_rapid.bat" class="btn">💾 Backup Rapid</a>';
        echo '<a href="diagnosticare_avansata_mysql.php" class="btn">🔍 Diagnosticare Avansată</a>';
        echo '<a href="citeste_log_mysql.php" class="btn">📋 Citește Log Direct</a>';
        echo '<a href="index.php" class="btn">🏠 Pagina Principală</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

