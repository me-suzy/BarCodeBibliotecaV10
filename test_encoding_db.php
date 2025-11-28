<?php
/**
 * Script de test pentru verificarea encoding-ului din baza de date
 */

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Test Encoding Baza de Date</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; }
        .success { color: #28a745; font-weight: bold; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; font-weight: bold; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Test Encoding Baza de Date</h1>";

try {
    // Test 1: Verifică encoding-ul conexiunii
    echo "<h2>1. Verificare Encoding Conexiune</h2>";
    
    $stmt = $pdo->query("SELECT @@character_set_connection, @@collation_connection, @@character_set_database, @@collation_database");
    $charset_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Setare</th><th>Valoare</th><th>Status</th></tr>";
    
    $checks = [
        'character_set_connection' => $charset_info['@@character_set_connection'],
        'collation_connection' => $charset_info['@@collation_connection'],
        'character_set_database' => $charset_info['@@character_set_database'],
        'collation_database' => $charset_info['@@collation_database']
    ];
    
    foreach ($checks as $key => $value) {
        $is_ok = (strpos($value, 'utf8mb4') !== false);
        $status = $is_ok ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ NU E UTF-8</span>";
        echo "<tr><td><strong>$key</strong></td><td>$value</td><td>$status</td></tr>";
    }
    
    echo "</table>";
    
    // Test 2: Citește date din baza de date
    echo "<h2>2. Test Citire Date</h2>";
    
    // Test cu cărți
    $stmt = $pdo->query("SELECT titlu, autor, cod_bare FROM carti WHERE (titlu LIKE '%ă%' OR titlu LIKE '%â%' OR titlu LIKE '%î%' OR titlu LIKE '%ș%' OR titlu LIKE '%ț%' OR autor LIKE '%ă%' OR autor LIKE '%â%' OR autor LIKE '%î%' OR autor LIKE '%ș%' OR autor LIKE '%ț%') LIMIT 5");
    $carti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($carti) > 0) {
        echo "<div class='success'>✅ Găsite " . count($carti) . " cărți cu diacritice</div>";
        echo "<table>";
        echo "<tr><th>Cod</th><th>Titlu</th><th>Autor</th><th>Status</th></tr>";
        
        foreach ($carti as $carte) {
            $titlu = $carte['titlu'];
            $autor = $carte['autor'] ?? '';
            
            // Verifică dacă conține semne de întrebare (indică encoding greșit)
            $has_question_marks = (strpos($titlu, '?') !== false || strpos($autor, '?') !== false);
            $status = $has_question_marks ? "<span class='error'>❌ Encoding greșit</span>" : "<span class='success'>✅ OK</span>";
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($carte['cod_bare'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($titlu, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($autor, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ Nu s-au găsit cărți cu diacritice în baza de date pentru testare.</div>";
    }
    
    // Test cu cititori
    $stmt = $pdo->query("SELECT nume, prenume, cod_bare FROM cititori WHERE (nume LIKE '%ă%' OR nume LIKE '%â%' OR nume LIKE '%î%' OR nume LIKE '%ș%' OR nume LIKE '%ț%' OR prenume LIKE '%ă%' OR prenume LIKE '%â%' OR prenume LIKE '%î%' OR prenume LIKE '%ș%' OR prenume LIKE '%ț%') LIMIT 5");
    $cititori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cititori) > 0) {
        echo "<h3>Cititori cu diacritice:</h3>";
        echo "<div class='success'>✅ Găsiți " . count($cititori) . " cititori cu diacritice</div>";
        echo "<table>";
        echo "<tr><th>Cod</th><th>Nume</th><th>Prenume</th><th>Status</th></tr>";
        
        foreach ($cititori as $cititor) {
            $nume = $cititor['nume'];
            $prenume = $cititor['prenume'] ?? '';
            
            $has_question_marks = (strpos($nume, '?') !== false || strpos($prenume, '?') !== false);
            $status = $has_question_marks ? "<span class='error'>❌ Encoding greșit</span>" : "<span class='success'>✅ OK</span>";
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($cititor['cod_bare'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($nume, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($prenume, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Test 3: Verifică encoding-ul tabelelor
    echo "<h2>3. Verificare Encoding Tabele</h2>";
    
    $tables = ['carti', 'cititori', 'imprumuturi'];
    echo "<table>";
    echo "<tr><th>Tabel</th><th>Encoding</th><th>Collation</th><th>Status</th></tr>";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $create_table = $stmt->fetchColumn(1);
        
        $has_utf8mb4 = (strpos($create_table, 'utf8mb4') !== false);
        $status = $has_utf8mb4 ? "<span class='success'>✅ UTF-8</span>" : "<span class='error'>❌ NU E UTF-8</span>";
        
        // Extrage encoding și collation
        preg_match("/CHARACTER SET\s+(\w+)/i", $create_table, $charset_match);
        preg_match("/COLLATE\s+(\w+)/i", $create_table, $collate_match);
        
        $charset = $charset_match[1] ?? 'N/A';
        $collation = $collate_match[1] ?? 'N/A';
        
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$charset</td>";
        echo "<td>$collation</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Rezumat final
    echo "<h2>📊 Rezumat</h2>";
    $all_ok = true;
    
    if (strpos($charset_info['@@character_set_connection'], 'utf8mb4') === false) {
        $all_ok = false;
    }
    if (count($carti) > 0) {
        foreach ($carti as $carte) {
            if (strpos($carte['titlu'], '?') !== false) {
                $all_ok = false;
                break;
            }
        }
    }
    
    if ($all_ok) {
        echo "<div class='success'>✅ Toate testele au trecut! Encoding-ul este configurat corect.</div>";
    } else {
        echo "<div class='error'>❌ Există probleme cu encoding-ul. Rulează fix_database_encoding.sql pentru a repara.</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Eroare: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
}

echo "    </div>
</body>
</html>";
?>

