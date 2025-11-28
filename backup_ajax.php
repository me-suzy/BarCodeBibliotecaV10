<?php
/**
 * Endpoint AJAX pentru backup
 * Rulează backup-ul direct și returnează rezultatul în format JSON
 */
// Dezactivează afișarea erorilor pentru a preveni output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Setează timeout mai mare pentru backup (10 minute)
set_time_limit(600);
ini_set('max_execution_time', 600);

// Previne output-ul prematur - TREBUIE să fie primul lucru
ob_start();

// Dezactivează afișarea erorilor pentru a preveni output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Include fișierele necesare
try {
    require_once 'config.php';
    require_once 'auth_check.php';
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Eroare la încărcarea configurației: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Setează header-ul JSON - TREBUIE să fie după ob_start()
header('Content-Type: application/json; charset=utf-8');

// Verifică dacă este cerere POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obține datele JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || $input['action'] !== 'backup') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Acțiune invalidă'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Setează timezone
date_default_timezone_set('Europe/Bucharest');

// Calea către folderul de backup
$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'BackUp';

// Creează folderul dacă nu există
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Data pentru backup (format: YYYY-MM-DD)
$date = date('Y-m-d');
$date_time = date('Y-m-d_H-i-s');

// Numele fișierului de backup SQL
$backup_sql_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_biblioteca_' . $date_time . '.sql';

// Numele arhivei ZIP (cu data zilei)
$backup_zip_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_complet_' . $date . '.zip';

// Log file pentru tracking
$log_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_log.txt';

/**
 * Funcție pentru scriere în log
 */
function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Funcție pentru crearea arhivei ZIP cu toate fișierele
 */
function createZipArchive($source_dir, $zip_file, $log_file) {
    if (!class_exists('ZipArchive')) {
        throw new Exception("Extensia ZipArchive nu este disponibilă în PHP!");
    }
    
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Nu s-a putut crea arhiva ZIP: $zip_file");
    }
    
    writeLog("Creând arhivă ZIP cu toate fișierele...", $log_file);
    
    // Listează toate fișierele și directoarele de exclus
    $exclude_patterns = [
        'BackUp',
        'node_modules',
        '.git',
        '.svn',
        '__pycache__',
        '*.pyc',
        '*.log',
        '*.tmp',
        '*.cache',
    ];
    
    // Funcție recursivă pentru adăugarea fișierelor
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $files_added = 0;
    $base_path = realpath($source_dir);
    
    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR, '', $file_path);
        
        // Verifică dacă fișierul trebuie exclus
        $should_exclude = false;
        foreach ($exclude_patterns as $pattern) {
            // Verifică dacă pattern-ul este în cale
            if (strpos($relative_path, $pattern) !== false) {
                $should_exclude = true;
                break;
            }
            
            // Verifică dacă pattern-ul se potrivește cu numele fișierului (wildcard)
            if (function_exists('fnmatch')) {
                if (fnmatch($pattern, basename($relative_path))) {
                    $should_exclude = true;
                    break;
                }
            } else {
                // Fallback pentru Windows (fnmatch nu este disponibil)
                $pattern_regex = str_replace(['*', '?'], ['.*', '.'], $pattern);
                if (preg_match('/^' . $pattern_regex . '$/i', basename($relative_path))) {
                    $should_exclude = true;
                    break;
                }
            }
        }
        
        if ($should_exclude) {
            continue;
        }
        
        // Adaugă fișierul în arhivă
        if ($file->isFile()) {
            $zip->addFile($file_path, $relative_path);
            $files_added++;
        } elseif ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        }
    }
    
    $zip->close();
    
    if (!file_exists($zip_file)) {
        throw new Exception("Arhiva ZIP nu a fost creată!");
    }
    
    $zip_size_mb = round(filesize($zip_file) / 1024 / 1024, 2);
    writeLog("✓ Arhivă ZIP creată: " . basename($zip_file) . " ($files_added fișiere, $zip_size_mb MB)", $log_file);
    
    return $files_added;
}

/**
 * Funcție pentru ștergerea backup-urilor vechi (păstrează ultimele 30 zile)
 */
function cleanOldBackups($backup_dir, $log_file) {
    // Șterge backup-uri SQL vechi
    $sql_files = glob($backup_dir . DIRECTORY_SEPARATOR . 'backup_biblioteca_*.sql');
    $zip_files = glob($backup_dir . DIRECTORY_SEPARATOR . 'backup_complet_*.zip');
    $keep_days = 30;
    $cutoff_time = time() - ($keep_days * 24 * 60 * 60);
    
    $deleted = 0;
    
    // Șterge SQL-uri vechi
    foreach ($sql_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $deleted++;
                writeLog("Backup SQL vechi șters: " . basename($file), $log_file);
            }
        }
    }
    
    // Șterge ZIP-uri vechi
    foreach ($zip_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $deleted++;
                writeLog("Backup ZIP vechi șters: " . basename($file), $log_file);
            }
        }
    }
    
    if ($deleted > 0) {
        writeLog("Total backup-uri vechi șterse: $deleted", $log_file);
    }
}

// Începe backup-ul
try {
    writeLog("=== ÎNCEPUT BACKUP (MANUAL) ===", $log_file);
    
    // ============================================
    // PARTEA 1: BACKUP BAZA DE DATE (folosind PHP pur, fără mysqldump)
    // ============================================
    writeLog("--- BACKUP BAZA DE DATE ---", $log_file);
    
    writeLog("Conectare la baza de date...", $log_file);
    
    // Conectare la MySQL folosind PDO
    // Folosește variabilele din config.php
    $host = 'localhost';
    $dbname = 'biblioteca';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        throw new Exception("Eroare conectare MySQL: " . $e->getMessage());
    }
    
    writeLog("Obținere listă tabele...", $log_file);
    
    // Obține lista de tabele
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        throw new Exception("Nu s-au găsit tabele în baza de date!");
    }
    
    writeLog("Tabele găsite: " . count($tables), $log_file);
    
    // Deschide fișierul pentru scriere
    $handle = fopen($backup_sql_file, 'w');
    if (!$handle) {
        throw new Exception("Nu s-a putut crea fișierul de backup!");
    }
    
    // Header SQL
    $output = "-- MySQL Backup" . PHP_EOL;
    $output .= "-- Database: " . $dbname . PHP_EOL;
    $output .= "-- Date: " . date('Y-m-d H:i:s') . PHP_EOL;
    $output .= "-- --------------------------------------------------------" . PHP_EOL . PHP_EOL;
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";" . PHP_EOL;
    $output .= "SET AUTOCOMMIT = 0;" . PHP_EOL;
    $output .= "START TRANSACTION;" . PHP_EOL;
    $output .= "SET time_zone = \"+00:00\";" . PHP_EOL . PHP_EOL;
    
    fwrite($handle, $output);
    
    // Backup fiecare tabelă
    foreach ($tables as $table) {
        writeLog("Backup tabelă: $table", $log_file);
        
        // Obține structura tabelei
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        
        if (!$row) {
            writeLog("Eroare la obținerea structurii tabelei $table", $log_file);
            continue;
        }
        
        $output = PHP_EOL . "-- --------------------------------------------------------" . PHP_EOL;
        $output .= "-- Structură tabelă: `$table`" . PHP_EOL;
        $output .= "-- --------------------------------------------------------" . PHP_EOL . PHP_EOL;
        $output .= "DROP TABLE IF EXISTS `$table`;" . PHP_EOL;
        $output .= $row[1] . ";" . PHP_EOL . PHP_EOL;
        
        fwrite($handle, $output);
        
        // Obține datele din tabelă
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $num_rows = $stmt->rowCount();
        
        if ($num_rows > 0) {
            $output = "-- Date pentru tabelă: `$table`" . PHP_EOL . PHP_EOL;
            fwrite($handle, $output);
            
            // Procesare date în batch-uri de 100 rânduri
            $batch = [];
            $batch_size = 100;
            $row_count = 0;
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        // Escapare pentru SQL
                        $value = str_replace(['\\', "'", "\n", "\r"], ['\\\\', "\\'", "\\n", "\\r"], $value);
                        $values[] = "'" . $value . "'";
                    }
                }
                
                $batch[] = "(" . implode(", ", $values) . ")";
                $row_count++;
                
                // Scrie batch-ul când ajunge la 100 rânduri sau la ultimul rând
                if (count($batch) >= $batch_size || $row_count >= $num_rows) {
                    $columns = array_keys($row);
                    $columns_str = "`" . implode("`, `", $columns) . "`";
                    
                    $output = "INSERT INTO `$table` ($columns_str) VALUES " . PHP_EOL;
                    $output .= implode("," . PHP_EOL, $batch) . ";" . PHP_EOL . PHP_EOL;
                    
                    fwrite($handle, $output);
                    $batch = [];
                }
            }
            
            writeLog("Tabelă $table: $num_rows rânduri", $log_file);
        } else {
            writeLog("Tabelă $table: 0 rânduri", $log_file);
        }
    }
    
    // Footer SQL
    $output = "COMMIT;" . PHP_EOL;
    fwrite($handle, $output);
    
    fclose($handle);
    
    // Verifică dimensiunea fișierului
    if (!file_exists($backup_sql_file)) {
        throw new Exception("Fișierul de backup nu a fost creat!");
    }
    
    $sql_file_size = filesize($backup_sql_file);
    
    if ($sql_file_size < 500) {
        throw new Exception("Fișierul de backup pare să fie gol! Dimensiune: $sql_file_size bytes");
    }
    
    $sql_file_size_mb = round($sql_file_size / 1024 / 1024, 2);
    writeLog("✓ Backup SQL creat cu succes: " . basename($backup_sql_file) . " ($sql_file_size_mb MB)", $log_file);
    
    // ============================================
    // PARTEA 2: BACKUP TOATE FIȘIERELE (ZIP)
    // ============================================
    writeLog("--- BACKUP TOATE FIȘIERELE (ZIP) ---", $log_file);
    
    // Creează arhiva ZIP cu toate fișierele
    $files_count = createZipArchive(__DIR__, $backup_zip_file, $log_file);
    
    // Adaugă și backup-ul SQL în arhivă
    if (file_exists($backup_sql_file)) {
        $zip = new ZipArchive();
        if ($zip->open($backup_zip_file, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backup_sql_file, 'database/' . basename($backup_sql_file));
            $zip->close();
            writeLog("✓ Backup SQL adăugat în arhivă ZIP", $log_file);
        }
    }
    
    // Verifică dimensiunea finală a arhivei ZIP
    $zip_size_mb = round(filesize($backup_zip_file) / 1024 / 1024, 2);
    writeLog("✓ Backup complet finalizat: " . basename($backup_zip_file) . " ($zip_size_mb MB)", $log_file);
    
    // Curăță backup-urile vechi
    cleanOldBackups($backup_dir, $log_file);
    
    writeLog("=== BACKUP FINALIZAT CU SUCCES ===", $log_file);
    writeLog("", $log_file);
    
    // Găsește fișierele create
    $files = [];
    if (file_exists($backup_sql_file)) {
        $size = filesize($backup_sql_file);
        $size_mb = round($size / 1024 / 1024, 2);
        $files[] = [
            'name' => basename($backup_sql_file),
            'size' => $size_mb . ' MB',
            'type' => 'SQL'
        ];
    }
    
    if (file_exists($backup_zip_file)) {
        $size = filesize($backup_zip_file);
        $size_mb = round($size / 1024 / 1024, 2);
        $files[] = [
            'name' => basename($backup_zip_file),
            'size' => $size_mb . ' MB',
            'type' => 'ZIP'
        ];
    }
    
    // Curăță orice output înainte de a trimite JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup realizat cu succes!',
        'files' => $files
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Curăță orice output înainte de a trimite JSON
    ob_end_clean();
    
    $error_message = "❌ EROARE BACKUP: " . $e->getMessage();
    writeLog($error_message, $log_file);
    writeLog("=== BACKUP EȘUAT ===", $log_file);
    writeLog("", $log_file);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'files' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>
