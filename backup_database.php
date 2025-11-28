<?php
/**
 * Script de Backup Automat Complet - Versiune PHP pură
 * Fără dependență de mysqldump.exe
 */

// Seteaza timezone
date_default_timezone_set('Europe/Bucharest');

// Configuratie MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'biblioteca');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Calea catre folderul de backup
$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'BackUp';

// Creeaza folderul daca nu exista
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Data pentru backup
$date = date('Y-m-d');
$date_time = date('Y-m-d_H-i-s');

// Numele fisierului de backup SQL
$backup_sql_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_biblioteca_' . $date_time . '.sql';

// Numele arhivei ZIP
$backup_zip_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_complet_' . $date . '.zip';

// Log file
$log_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_log.txt';

/**
 * Functie pentru scriere in log
 */
function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Functie pentru backup baza de date folosind MySQLi (fara mysqldump)
 */
function backupDatabasePHP($host, $user, $pass, $dbname, $backup_file, $log_file) {
    writeLog("Conectare la baza de date...", $log_file);
    
    // Conectare la MySQL
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    
    if ($mysqli->connect_error) {
        throw new Exception("Eroare conectare MySQL: " . $mysqli->connect_error);
    }
    
    // Seteaza charset
    $mysqli->set_charset('utf8mb4');
    
    writeLog("Obtinere lista tabele...", $log_file);
    
    // Obtine lista de tabele
    $tables = array();
    $result = $mysqli->query("SHOW TABLES");
    
    if (!$result) {
        throw new Exception("Eroare la obtinerea tabelelor: " . $mysqli->error);
    }
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        throw new Exception("Nu s-au gasit tabele in baza de date!");
    }
    
    writeLog("Tabele gasite: " . count($tables), $log_file);
    
    // Deschide fisierul pentru scriere
    $handle = fopen($backup_file, 'w');
    
    if (!$handle) {
        throw new Exception("Nu s-a putut crea fisierul de backup!");
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
    
    // Backup fiecare tabela
    foreach ($tables as $table) {
        writeLog("Backup tabela: $table", $log_file);
        
        // Obtine structura tabelei
        $result = $mysqli->query("SHOW CREATE TABLE `$table`");
        
        if (!$result) {
            writeLog("Eroare la obtinerea structurii tabelei $table: " . $mysqli->error, $log_file);
            continue;
        }
        
        $row = $result->fetch_row();
        
        $output = PHP_EOL . "-- --------------------------------------------------------" . PHP_EOL;
        $output .= "-- Structura tabela: `$table`" . PHP_EOL;
        $output .= "-- --------------------------------------------------------" . PHP_EOL . PHP_EOL;
        $output .= "DROP TABLE IF EXISTS `$table`;" . PHP_EOL;
        $output .= $row[1] . ";" . PHP_EOL . PHP_EOL;
        
        fwrite($handle, $output);
        
        // Obtine datele din tabela
        $result = $mysqli->query("SELECT * FROM `$table`");
        
        if (!$result) {
            writeLog("Eroare la obtinerea datelor din tabela $table: " . $mysqli->error, $log_file);
            continue;
        }
        
        $num_rows = $result->num_rows;
        
        if ($num_rows > 0) {
            $output = "-- Date pentru tabela: `$table`" . PHP_EOL . PHP_EOL;
            fwrite($handle, $output);
            
            // Procesare date in batch-uri de 100 randuri
            $batch = array();
            $batch_size = 100;
            $row_count = 0;
            
            while ($row = $result->fetch_assoc()) {
                $values = array();
                
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                    }
                }
                
                $batch[] = "(" . implode(", ", $values) . ")";
                $row_count++;
                
                // Scrie batch-ul cand ajunge la 100 randuri sau la ultimul rand
                if (count($batch) >= $batch_size || $row_count >= $num_rows) {
                    $columns = array_keys($row);
                    $columns_str = "`" . implode("`, `", $columns) . "`";
                    
                    $output = "INSERT INTO `$table` ($columns_str) VALUES " . PHP_EOL;
                    $output .= implode("," . PHP_EOL, $batch) . ";" . PHP_EOL . PHP_EOL;
                    
                    fwrite($handle, $output);
                    $batch = array();
                }
            }
            
            writeLog("Tabela $table: $num_rows randuri", $log_file);
        } else {
            writeLog("Tabela $table: 0 randuri", $log_file);
        }
    }
    
    // Footer SQL
    $output = "COMMIT;" . PHP_EOL;
    fwrite($handle, $output);
    
    fclose($handle);
    $mysqli->close();
    
    // Verifica dimensiunea fisierului
    if (!file_exists($backup_file)) {
        throw new Exception("Fisierul de backup nu a fost creat!");
    }
    
    $size = filesize($backup_file);
    
    if ($size < 500) {
        throw new Exception("Fisierul de backup pare sa fie gol! Dimensiune: $size bytes");
    }
    
    $size_mb = round($size / 1024 / 1024, 2);
    writeLog("Backup SQL creat: " . basename($backup_file) . " ($size_mb MB)", $log_file);
    
    return true;
}

/**
 * Functie pentru crearea arhivei ZIP
 */
function createZipArchive($source_dir, $zip_file, $log_file) {
    if (!class_exists('ZipArchive')) {
        throw new Exception("Extensia ZipArchive nu este disponibila!");
    }
    
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Nu s-a putut crea arhiva ZIP!");
    }
    
    writeLog("Creand arhiva ZIP...", $log_file);
    
    $exclude_patterns = [
        'BackUp',
        'node_modules',
        '.git',
        '.svn',
        '__pycache__',
    ];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $files_added = 0;
    $base_path = realpath($source_dir);
    
    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR, '', $file_path);
        
        // Verifica excluderi
        $should_exclude = false;
        foreach ($exclude_patterns as $pattern) {
            if (strpos($relative_path, $pattern) !== false) {
                $should_exclude = true;
                break;
            }
        }
        
        if ($should_exclude) {
            continue;
        }
        
        if ($file->isFile()) {
            $zip->addFile($file_path, $relative_path);
            $files_added++;
        } elseif ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        }
    }
    
    $zip->close();
    
    if (!file_exists($zip_file)) {
        throw new Exception("Arhiva ZIP nu a fost creata!");
    }
    
    $zip_size_mb = round(filesize($zip_file) / 1024 / 1024, 2);
    writeLog("Arhiva ZIP creata: " . basename($zip_file) . " ($files_added fisiere, $zip_size_mb MB)", $log_file);
    
    return $files_added;
}

/**
 * Functie pentru stergerea backup-urilor vechi
 */
function cleanOldBackups($backup_dir, $log_file) {
    $sql_files = glob($backup_dir . DIRECTORY_SEPARATOR . 'backup_biblioteca_*.sql');
    $zip_files = glob($backup_dir . DIRECTORY_SEPARATOR . 'backup_complet_*.zip');
    $keep_days = 30;
    $cutoff_time = time() - ($keep_days * 24 * 60 * 60);
    
    $deleted = 0;
    
    if ($sql_files) {
        foreach ($sql_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
    }
    
    if ($zip_files) {
        foreach ($zip_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
    }
    
    if ($deleted > 0) {
        writeLog("Backup-uri vechi sterse: $deleted", $log_file);
    }
}

// ============================================
// MAIN EXECUTION
// ============================================
try {
    writeLog("=== INCEPUT BACKUP ===", $log_file);
    
    // PARTEA 1: BACKUP BAZA DE DATE (cu PHP)
    writeLog("--- BACKUP BAZA DE DATE ---", $log_file);
    backupDatabasePHP(DB_HOST, DB_USER, DB_PASS, DB_NAME, $backup_sql_file, $log_file);
    
    // PARTEA 2: BACKUP FISIERE (ZIP)
    writeLog("--- BACKUP FISIERE (ZIP) ---", $log_file);
    $files_count = createZipArchive(__DIR__, $backup_zip_file, $log_file);
    
    // Adauga SQL in arhiva
    if (file_exists($backup_sql_file)) {
        $zip = new ZipArchive();
        if ($zip->open($backup_zip_file) === TRUE) {
            $zip->addFile($backup_sql_file, 'database/' . basename($backup_sql_file));
            $zip->close();
            writeLog("Backup SQL adaugat in arhiva ZIP", $log_file);
        }
    }
    
    // Curata backup-urile vechi
    cleanOldBackups($backup_dir, $log_file);
    
    writeLog("=== BACKUP FINALIZAT CU SUCCES ===", $log_file);
    writeLog("", $log_file);
    
    exit(0);
    
} catch (Exception $e) {
    writeLog("EROARE BACKUP: " . $e->getMessage(), $log_file);
    writeLog("=== BACKUP ESUAT ===", $log_file);
    writeLog("", $log_file);
    exit(1);
}
?>