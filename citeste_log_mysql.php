<?php
/**
 * Script Simplu - Citește Log-ul MySQL Direct
 * 
 * Dacă diagnosticarea avansată nu funcționează,
 * acest script citește direct log-ul și afișează erorile
 */

header('Content-Type: text/html; charset=UTF-8');

$error_log = 'C:\\xampp\\mysql\\data\\mysql_error.log';

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Log MySQL - Citire Directă</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            background: #252526;
            padding: 20px;
            border-radius: 5px;
        }
        h1 {
            color: #4ec9b0;
            margin-bottom: 20px;
        }
        .info {
            background: #264f78;
            color: #9cdcfe;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
            line-height: 1.6;
            border: 1px solid #3e3e42;
        }
        .error-line {
            color: #f48771;
            font-weight: bold;
        }
        .fatal-line {
            color: #f44336;
            font-weight: bold;
            background: #3d1f1f;
            padding: 2px 4px;
        }
        .warning-line {
            color: #dcdcaa;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0e639c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1177bb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Log MySQL - Citire Directă</h1>
        
        <?php
        if (file_exists($error_log)) {
            $log_content = file_get_contents($error_log);
            $log_lines = explode("\n", $log_content);
            
            echo '<div class="info">';
            echo '<strong>📁 Fișier:</strong> ' . htmlspecialchars($error_log) . '<br>';
            echo '<strong>📊 Total linii:</strong> ' . count($log_lines) . '<br>';
            echo '<strong>📏 Mărime:</strong> ' . number_format(filesize($error_log) / 1024, 2) . ' KB';
            echo '</div>';
            
            // Ultimele 100 linii
            $recent_lines = array_slice($log_lines, -100);
            
            echo '<h2 style="color: #4ec9b0;">Ultimele 100 linii:</h2>';
            echo '<pre>';
            
            foreach ($recent_lines as $line) {
                $line_escaped = htmlspecialchars($line);
                
                // Colorează după tip
                if (stripos($line, 'FATAL') !== false) {
                    echo '<span class="fatal-line">' . $line_escaped . '</span>' . "\n";
                } elseif (stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false) {
                    echo '<span class="error-line">' . $line_escaped . '</span>' . "\n";
                } elseif (stripos($line, 'WARNING') !== false || stripos($line, 'warning') !== false) {
                    echo '<span class="warning-line">' . $line_escaped . '</span>' . "\n";
                } else {
                    echo $line_escaped . "\n";
                }
            }
            
            echo '</pre>';
            
            // Extrage doar erorile
            $errors = [];
            $fatal = [];
            
            foreach ($recent_lines as $line) {
                if (stripos($line, 'FATAL') !== false) {
                    $fatal[] = $line;
                } elseif (stripos($line, 'ERROR') !== false && stripos($line, 'error') !== false) {
                    $errors[] = $line;
                }
            }
            
            if (!empty($fatal)) {
                echo '<h2 style="color: #f44336;">❌ Erori FATAL:</h2>';
                echo '<pre>';
                foreach ($fatal as $line) {
                    echo '<span class="fatal-line">' . htmlspecialchars($line) . '</span>' . "\n";
                }
                echo '</pre>';
            }
            
            if (!empty($errors)) {
                echo '<h2 style="color: #f48771;">❌ Erori:</h2>';
                echo '<pre>';
                foreach (array_slice($errors, -20) as $line) {
                    echo '<span class="error-line">' . htmlspecialchars($line) . '</span>' . "\n";
                }
                echo '</pre>';
            }
            
        } else {
            echo '<div class="info" style="background: #6a1b1b; color: #f44336;">';
            echo '<strong>❌ Fișierul de log nu există:</strong><br>';
            echo htmlspecialchars($error_log) . '<br><br>';
            echo 'MySQL nu a încercat să pornească sau log-ul nu a fost creat încă.';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px;">
            <a href="diagnosticare_avansata_mysql.php" class="btn">🔍 Diagnosticare Avansată</a>
            <a href="diagnosticare_mysql.php" class="btn">📊 Diagnosticare Simplă</a>
            <a href="index.php" class="btn">🏠 Pagina Principală</a>
        </div>
    </div>
</body>
</html>

