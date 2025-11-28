<?php
// verifica_encoding.php - Verifică și repară encoding-ul bazei de date
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require_once 'config.php';

echo "<h1>Verificare Encoding Bază de Date</h1>";
echo "<style>body { font-family: Arial; margin: 20px; } table { border-collapse: collapse; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #4CAF50; color: white; } .error { color: red; } .success { color: green; }</style>";

// Verifică encoding-ul conexiunii
echo "<h2>1. Encoding Conexiune MySQL</h2>";
$stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
echo "<table>";
echo "<tr><th>Variable</th><th>Value</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr><td>{$row['Variable_name']}</td><td>{$row['Value']}</td></tr>";
}
echo "</table>";

// Verifică encoding-ul bazei de date
echo "<h2>2. Encoding Bază de Date</h2>";
$stmt = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
                     FROM information_schema.SCHEMATA 
                     WHERE SCHEMA_NAME = 'biblioteca'");
$db_info = $stmt->fetch();
echo "<table>";
echo "<tr><th>Character Set</th><th>Collation</th></tr>";
echo "<tr><td>{$db_info['DEFAULT_CHARACTER_SET_NAME']}</td><td>{$db_info['DEFAULT_COLLATION_NAME']}</td></tr>";
echo "</table>";

// Verifică encoding-ul tabelelor
echo "<h2>3. Encoding Tabele</h2>";
$stmt = $pdo->query("SELECT TABLE_NAME, TABLE_COLLATION 
                     FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = 'biblioteca' 
                     ORDER BY TABLE_NAME");
echo "<table>";
echo "<tr><th>Tabelă</th><th>Collation</th></tr>";
while ($row = $stmt->fetch()) {
    $collation = $row['TABLE_COLLATION'];
    $is_utf8 = (strpos($collation, 'utf8mb4') !== false || strpos($collation, 'utf8') !== false);
    $class = $is_utf8 ? 'success' : 'error';
    echo "<tr class='$class'><td>{$row['TABLE_NAME']}</td><td>$collation</td></tr>";
}
echo "</table>";

// Verifică date exemple din carti
echo "<h2>4. Verificare Date Exemple (Carti)</h2>";
$stmt = $pdo->query("SELECT cod_bare, titlu, autor FROM carti LIMIT 5");
echo "<table>";
echo "<tr><th>Cod Bare</th><th>Titlu (Raw)</th><th>Titlu (Hex)</th><th>Autor (Raw)</th><th>Autor (Hex)</th></tr>";
while ($row = $stmt->fetch()) {
    $titlu_hex = bin2hex($row['titlu']);
    $autor_hex = bin2hex($row['autor'] ?? '');
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['cod_bare'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['titlu'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td><small>$titlu_hex</small></td>";
    echo "<td>" . htmlspecialchars($row['autor'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td><small>$autor_hex</small></td>";
    echo "</tr>";
}
echo "</table>";

// Script pentru conversie (dacă e necesar)
echo "<h2>5. Comenzi SQL pentru Reparare</h2>";
echo "<pre style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>";

// Conversie baza de date
echo "-- 1. Convertește baza de date la utf8mb4\n";
echo "ALTER DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n\n";

// Conversie tabele
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'biblioteca'");
while ($row = $stmt->fetch()) {
    $table = $row['TABLE_NAME'];
    echo "-- 2. Convertește tabelul $table\n";
    echo "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n\n";
}

// Conversie coloane text
echo "-- 3. Convertește coloanele text individual\n";
$stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE 
                     FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = 'biblioteca' 
                     AND DATA_TYPE IN ('varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext')
                     ORDER BY TABLE_NAME, COLUMN_NAME");
while ($row = $stmt->fetch()) {
    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    echo "ALTER TABLE `$table` MODIFY `$column` " . 
         ($row['DATA_TYPE'] == 'varchar' ? 'VARCHAR(255)' : strtoupper($row['DATA_TYPE'])) . 
         " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
}

echo "</pre>";

echo "<h2>6. Acțiune Rapidă</h2>";
echo "<p><a href='?executa_reparare=1' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Execută Repararea Automată</a></p>";

// Execută repararea dacă e solicitată
if (isset($_GET['executa_reparare'])) {
    echo "<h2>Reparare în curs...</h2>";
    
    try {
        // Convertește baza de date
        $pdo->exec("ALTER DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p class='success'>✅ Baza de date convertită la utf8mb4</p>";
        
        // Convertește toate tabelele
        $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'biblioteca'");
        while ($row = $stmt->fetch()) {
            $table = $row['TABLE_NAME'];
            try {
                $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<p class='success'>✅ Tabelul $table convertit</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Eroare la tabelul $table: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<h3>✅ Reparare finalizată! Reîncarcă pagina pentru a verifica.</h3>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Eroare: " . $e->getMessage() . "</p>";
    }
}

echo "<p><a href='index.php'>← Înapoi la pagina principală</a></p>";
?>

