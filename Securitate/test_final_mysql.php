<?php
/**
 * test_final_mysql.php
 * Test final conexiune MySQL după repornire
 */

echo "<h2>🔍 Test Final MySQL</h2>";
echo "<style>body { font-family: Arial; padding: 20px; } .ok { color: green; } .error { color: red; } .warning { color: orange; }</style>";

// Setează timeout
ini_set('default_socket_timeout', 3);

// Test 1: Port
echo "<h3>1. Test port 3306</h3>";
$start = microtime(true);
$port = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 2);
$time = round((microtime(true) - $start) * 1000, 2);

if ($port) {
    echo "<span class='ok'>✅ Port 3306 deschis ({$time}ms)</span><br>";
    fclose($port);
} else {
    echo "<span class='error'>❌ Port 3306 închis: $errstr ($errno)</span><br>";
    echo "<p><strong>💡 MySQL nu rulează sau nu acceptă conexiuni!</strong></p>";
    echo "<p><a href='verifica_ready_connections.php'>← Verifică status MySQL</a> | <a href='index.php'>← Index</a></p>";
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
            PDO::ATTR_TIMEOUT => 3,
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
    echo "<h3>3. Test bază de date 'biblioteca'</h3>";
    $stmt = $pdo->query("SHOW DATABASES LIKE 'biblioteca'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='ok'>✅ Baza de date 'biblioteca' există</span><br>";
        
        // Test conexiune cu baza de date
        try {
            $pdo_db = new PDO(
                "mysql:host=127.0.0.1;port=3306;dbname=biblioteca;charset=utf8mb4",
                "root",
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
            echo "<span class='ok'>✅ Conexiune la baza de date 'biblioteca' reușită!</span><br>";
            
            // Test tabel
            $stmt = $pdo_db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<span class='ok'>✅ Tabele găsite: " . count($tables) . "</span><br>";
            
            echo "<hr>";
            echo "<h3>✅ MySQL funcționează perfect!</h3>";
            echo "<p><strong>💡 Aplicația ar trebui să funcționeze acum!</strong></p>";
            echo "<p><a href='index.php'>← Testează aplicația</a> | <a href='scanare_rapida.php'>← Scanare rapidă</a></p>";
            
        } catch (PDOException $e) {
            echo "<span class='error'>❌ Eroare conexiune la baza de date: " . htmlspecialchars($e->getMessage()) . "</span><br>";
            echo "<p><strong>💡 Rulează setup.php pentru a crea baza de date</strong></p>";
            echo "<p><a href='setup.php'>← Setup baza de date</a></p>";
        }
    } else {
        echo "<span class='warning'>⚠️ Baza de date 'biblioteca' NU există</span><br>";
        echo "<p><strong>💡 Rulează setup.php pentru a crea baza de date</strong></p>";
        echo "<p><a href='setup.php'>← Setup baza de date</a></p>";
    }
    
} catch (PDOException $e) {
    $time = round((microtime(true) - $start) * 1000, 2);
    echo "<span class='error'>❌ Eroare conexiune ({$time}ms): " . htmlspecialchars($e->getMessage()) . "</span><br>";
    
    echo "<hr>";
    echo "<h3>📋 Soluții:</h3>";
    echo "<ol>";
    echo "<li>Verifică în XAMPP Control Panel că MySQL este 'Running' (verde)</li>";
    echo "<li>Așteaptă 10-15 secunde după pornirea MySQL</li>";
    echo "<li>Verifică log-urile MySQL: XAMPP Control Panel → MySQL → Logs</li>";
    echo "<li>Caută mesajul 'ready for connections' în log-uri</li>";
    echo "<li>Dacă nu apare, oprește și pornește MySQL din nou</li>";
    echo "</ol>";
    echo "<p><a href='verifica_ready_connections.php'>← Verifică status MySQL</a> | <a href='index.php'>← Index</a></p>";
}
?>

