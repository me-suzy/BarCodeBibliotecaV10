<?php
/**
 * test_ipv6_mysql.php
 * Test conexiune MySQL pe IPv6 (::1)
 */

echo "<h2>🔍 Test Conexiune MySQL IPv6</h2>";
echo "<style>body { font-family: Arial; padding: 20px; } .ok { color: green; } .error { color: red; }</style>";

// Setează timeout scurt
ini_set('default_socket_timeout', 2);

// Test 1: IPv6 (::1)
echo "<h3>1. Test conexiune pe IPv6 (::1)</h3>";
try {
    $pdo = new PDO(
        "mysql:host=::1;port=3306;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    echo "<span class='ok'>✅ Conexiune IPv6 reușită!</span><br>";
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<span class='ok'>✅ Versiune MySQL: " . htmlspecialchars($result['version']) . "</span><br>";
    
    // Test bază de date
    $stmt = $pdo->query("SHOW DATABASES LIKE 'biblioteca'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='ok'>✅ Baza de date 'biblioteca' există</span><br>";
    } else {
        echo "<span class='error'>⚠️ Baza de date 'biblioteca' NU există</span><br>";
    }
    
    echo "<hr>";
    echo "<h3>✅ MySQL funcționează pe IPv6!</h3>";
    echo "<p><strong>💡 Soluție:</strong> Schimbă DB_HOST în config.php la <code>::1</code> sau <code>localhost</code></p>";
    echo "<p><a href='index.php'>← Înapoi la index</a></p>";
    
} catch (PDOException $e) {
    echo "<span class='error'>❌ Eroare conexiune IPv6: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    
    // Test 2: IPv4 (127.0.0.1)
    echo "<h3>2. Test conexiune pe IPv4 (127.0.0.1)</h3>";
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
        echo "<span class='ok'>✅ Conexiune IPv4 reușită!</span><br>";
        echo "<p><strong>💡 Soluție:</strong> Păstrează DB_HOST = <code>127.0.0.1</code> în config.php</p>";
    } catch (PDOException $e2) {
        echo "<span class='error'>❌ Eroare conexiune IPv4: " . htmlspecialchars($e2->getMessage()) . "</span><br>";
        echo "<hr>";
        echo "<h3>❌ Niciuna dintre conexiuni nu funcționează</h3>";
        echo "<p><strong>💡 Verifică:</strong></p>";
        echo "<ol>";
        echo "<li>Dacă MySQL este pornit în XAMPP Control Panel</li>";
        echo "<li>Dacă apare 'ready for connections' în log-urile MySQL</li>";
        echo "<li>Dacă ai repornit MySQL după modificarea my.ini</li>";
        echo "</ol>";
    }
    
    echo "<p><a href='index.php'>← Înapoi la index</a></p>";
}
?>

