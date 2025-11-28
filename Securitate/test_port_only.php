<?php
/**
 * test_port_only.php
 * Test doar portul 3306, fără conexiune MySQL
 */

// Setează timeout foarte scurt
set_time_limit(3);
ini_set('max_execution_time', 3);

echo "<h2>🔍 Test Port 3306 (fără conexiune MySQL)</h2>";
echo "<style>body { font-family: Arial; padding: 20px; } .ok { color: green; } .error { color: red; }</style>";

echo "<p>Testare port 3306...</p>";
flush();
ob_flush();

// Test port cu timeout foarte scurt
$start = microtime(true);
$socket = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 1);
$time = round((microtime(true) - $start) * 1000, 2);

if ($socket) {
    echo "<p><span class='ok'>✅ Port 3306 este DESCHIS ({$time}ms)</span></p>";
    fclose($socket);
    
    echo "<hr>";
    echo "<h3>💡 Portul este deschis, dar conexiunea MySQL se blochează</h3>";
    echo "<p><strong>Posibile cauze:</strong></p>";
    echo "<ul>";
    echo "<li>MySQL nu acceptă conexiuni pe IPv4 (doar IPv6)</li>";
    echo "<li>Firewall blochează conexiunea</li>";
    echo "<li>MySQL rulează dar nu este gata să accepte conexiuni</li>";
    echo "</ul>";
    
    echo "<p><strong>Soluții:</strong></p>";
    echo "<ol>";
    echo "<li>Verifică în XAMPP Control Panel → MySQL → Logs dacă apare 'ready for connections'</li>";
    echo "<li>Oprește și pornește MySQL din XAMPP Control Panel</li>";
    echo "<li>Rulează XAMPP Control Panel ca Administrator</li>";
    echo "<li>Verifică dacă există servicii Windows MySQL care interferă</li>";
    echo "</ol>";
    
} else {
    echo "<p><span class='error'>❌ Port 3306 este ÎNCHIS ({$time}ms)</span></p>";
    echo "<p>Eroare: $errstr ($errno)</p>";
    echo "<p><strong>💡 MySQL NU rulează sau nu ascultă pe portul 3306!</strong></p>";
    echo "<p>Verifică în XAMPP Control Panel că MySQL este 'Running' (verde)</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Înapoi la index</a></p>";
?>

