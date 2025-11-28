<?php
/**
 * test_mysql.php
 * Script simplu pentru testarea conexiunii MySQL
 */

echo "<h2>🔍 Test Conexiune MySQL</h2>";

// Test 1: Verifică dacă extensia PDO MySQL este instalată
echo "<h3>1. Verificare extensie PDO MySQL</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "✅ Extensia PDO MySQL este instalată<br>";
} else {
    echo "❌ Extensia PDO MySQL NU este instalată!<br>";
    echo "💡 Instalează extensia: php -m | grep pdo_mysql<br>";
}

// Test 2: Verifică dacă MySQL rulează (port 3306)
echo "<h3>2. Verificare port MySQL (3306)</h3>";
$connection = @fsockopen('localhost', 3306, $errno, $errstr, 2);
if ($connection) {
    echo "✅ Port 3306 este deschis - MySQL pare să ruleze<br>";
    fclose($connection);
} else {
    echo "❌ Port 3306 este închis - MySQL NU rulează sau nu acceptă conexiuni<br>";
    echo "💡 Eroare: $errstr ($errno)<br>";
    echo "💡 Pornește MySQL din XAMPP Control Panel<br>";
}

// Test 3: Încearcă conexiunea la MySQL
echo "<h3>3. Test conexiune MySQL</h3>";
try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]
    );
    echo "✅ Conexiune MySQL reușită!<br>";
    
    // Verifică dacă baza de date există
    $stmt = $pdo->query("SHOW DATABASES LIKE 'biblioteca'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Baza de date 'biblioteca' există<br>";
    } else {
        echo "⚠️ Baza de date 'biblioteca' NU există<br>";
        echo "💡 Rulează setup.php pentru a o crea<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Eroare conexiune MySQL: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<br><strong>💡 Soluții:</strong><br>";
    echo "1. Verifică dacă MySQL este pornit în XAMPP Control Panel<br>";
    echo "2. Verifică dacă portul 3306 este liber<br>";
    echo "3. Verifică dacă parola root este corectă (în cazul tău ar trebui să fie goală)<br>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Înapoi la index</a></p>";
?>

