<?php
/**
 * test_mysql_simple.php
 * Test simplu și rapid pentru MySQL
 */

// Setează timeout-uri scurte
ini_set('max_execution_time', 5);
ini_set('default_socket_timeout', 2);

echo "<h2>🔍 Test Rapid MySQL</h2>";
echo "<style>body { font-family: Arial; padding: 20px; } .ok { color: green; } .error { color: red; }</style>";

// Test 1: Port
echo "<h3>1. Test port 3306</h3>";
$start = microtime(true);
$port = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 1);
$time = round((microtime(true) - $start) * 1000, 2);

if ($port) {
    echo "<span class='ok'>✅ Port 3306 deschis ({$time}ms)</span><br>";
    fclose($port);
} else {
    echo "<span class='error'>❌ Port 3306 închis: $errstr ($errno)</span><br>";
    echo "<strong>MySQL nu rulează sau nu acceptă conexiuni!</strong><br>";
    exit;
}

// Test 2: Conexiune MySQL
echo "<h3>2. Test conexiune MySQL</h3>";
$start = microtime(true);
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    $time = round((microtime(true) - $start) * 1000, 2);
    echo "<span class='ok'>✅ Conexiune reușită ({$time}ms)</span><br>";
    
    // Test query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<span class='ok'>✅ Versiune MySQL: " . htmlspecialchars($result['version']) . "</span><br>";
    
    // Test bază de date
    $stmt = $pdo->query("SHOW DATABASES LIKE 'biblioteca'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='ok'>✅ Baza de date 'biblioteca' există</span><br>";
    } else {
        echo "<span class='error'>⚠️ Baza de date 'biblioteca' NU există</span><br>";
        echo "<strong>💡 Rulează setup.php pentru a o crea</strong><br>";
    }
    
    echo "<hr>";
    echo "<h3>✅ MySQL funcționează corect!</h3>";
    echo "<p><a href='index.php'>← Înapoi la index</a></p>";
    
} catch (PDOException $e) {
    $time = round((microtime(true) - $start) * 1000, 2);
    echo "<span class='error'>❌ Eroare conexiune ({$time}ms): " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<hr>";
    echo "<h3>📋 Soluții:</h3>";
    echo "<ol>";
    echo "<li>Verifică în XAMPP Control Panel că MySQL este 'Running' (verde)</li>";
    echo "<li>Oprește și pornește MySQL din XAMPP Control Panel</li>";
    echo "<li>Verifică log-urile MySQL: XAMPP Control Panel → MySQL → Logs</li>";
    echo "<li>Rulează XAMPP Control Panel ca Administrator</li>";
    echo "</ol>";
    echo "<p><a href='verifica_mysql_running.php'>← Verificare detaliată</a> | <a href='index.php'>← Index</a></p>";
}
?>

