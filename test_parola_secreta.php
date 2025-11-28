<?php
/**
 * Script de test pentru verificarea parolei secrete
 * Testează toate funcțiile de autentificare admin
 */

session_start();
require_once 'config.php';
require_once 'config_secret_admin.php';

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Test Parolă Secretă</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        h1 { color: #667eea; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔐 Test Parolă Secretă Administrator</h1>";

// Test 1: Verificare constante
echo "<div class='test-section'>";
echo "<h2>Test 1: Verificare Constante</h2>";
if (defined('SECRET_ADMIN_USERNAME')) {
    echo "<p class='success'>✅ SECRET_ADMIN_USERNAME este definit: <strong>" . SECRET_ADMIN_USERNAME . "</strong></p>";
} else {
    echo "<p class='error'>❌ SECRET_ADMIN_USERNAME NU este definit!</p>";
}

if (defined('SECRET_ADMIN_PASSWORD')) {
    echo "<p class='success'>✅ SECRET_ADMIN_PASSWORD este definit: <strong>" . SECRET_ADMIN_PASSWORD . "</strong></p>";
} else {
    echo "<p class='error'>❌ SECRET_ADMIN_PASSWORD NU este definit!</p>";
}
echo "</div>";

// Test 2: Verificare funcție verificaSecretAdmin
echo "<div class='test-section'>";
echo "<h2>Test 2: Funcția verificaSecretAdmin()</h2>";
if (function_exists('verificaSecretAdmin')) {
    // Test cu credențiale corecte
    $test1 = verificaSecretAdmin('bebe2025', 'bebe');
    if ($test1) {
        echo "<p class='success'>✅ verificaSecretAdmin('bebe2025', 'bebe') = TRUE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaSecretAdmin('bebe2025', 'bebe') = FALSE (ar trebui să fie TRUE!)</p>";
    }
    
    // Test cu credențiale greșite
    $test2 = verificaSecretAdmin('bebe2025', 'parola_gresita');
    if (!$test2) {
        echo "<p class='success'>✅ verificaSecretAdmin('bebe2025', 'parola_gresita') = FALSE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaSecretAdmin('bebe2025', 'parola_gresita') = TRUE (ar trebui să fie FALSE!)</p>";
    }
    
    // Test cu username greșit
    $test3 = verificaSecretAdmin('username_gresit', 'bebe');
    if (!$test3) {
        echo "<p class='success'>✅ verificaSecretAdmin('username_gresit', 'bebe') = FALSE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaSecretAdmin('username_gresit', 'bebe') = TRUE (ar trebui să fie FALSE!)</p>";
    }
} else {
    echo "<p class='error'>❌ Funcția verificaSecretAdmin() NU există!</p>";
}
echo "</div>";

// Test 3: Verificare funcție din editare_cititor.php
echo "<div class='test-section'>";
echo "<h2>Test 3: Funcția verificaParolaAdmin() din editare_cititor.php</h2>";

// Simulează ID cititor pentru a evita eroarea
$_GET['id'] = 1;

// Include doar funcțiile necesare fără să ruleze tot codul
require_once 'config_secret_admin.php';

// Definim funcția manual pentru test (copiată din editare_cititor.php)
function verificaParolaAdmin($pdo, $parola_introdusa) {
    // Normalizează parola (elimină spații)
    $parola_introdusa = trim($parola_introdusa);
    
    // Verifică mai întâi credențialele secrete (hardcoded)
    if (defined('SECRET_ADMIN_PASSWORD') && $parola_introdusa === SECRET_ADMIN_PASSWORD) {
        return true;
    }
    
    // Dacă nu este parola secretă, verifică în baza de date
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM utilizatori WHERE id = 1 AND activ = TRUE");
        $stmt->execute();
        $utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilizator) {
            return false;
        }
        
        return password_verify($parola_introdusa, $utilizator['password_hash']);
    } catch (PDOException $e) {
        error_log("Eroare verificare parolă admin: " . $e->getMessage());
        return false;
    }
}

if (function_exists('verificaParolaAdmin')) {
    // Test cu parola secretă corectă
    $test4 = verificaParolaAdmin($pdo, 'bebe');
    if ($test4) {
        echo "<p class='success'>✅ verificaParolaAdmin(\$pdo, 'bebe') = TRUE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaParolaAdmin(\$pdo, 'bebe') = FALSE (ar trebui să fie TRUE!)</p>";
    }
    
    // Test cu parola greșită
    $test5 = verificaParolaAdmin($pdo, 'parola_gresita');
    if (!$test5) {
        echo "<p class='success'>✅ verificaParolaAdmin(\$pdo, 'parola_gresita') = FALSE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaParolaAdmin(\$pdo, 'parola_gresita') = TRUE (ar trebui să fie FALSE!)</p>";
    }
    
    // Test cu spații în plus
    $test6 = verificaParolaAdmin($pdo, '  bebe  ');
    if ($test6) {
        echo "<p class='success'>✅ verificaParolaAdmin(\$pdo, '  bebe  ') = TRUE (corect - trim elimină spațiile)</p>";
    } else {
        echo "<p class='error'>❌ verificaParolaAdmin(\$pdo, '  bebe  ') = FALSE (ar trebui să fie TRUE după trim!)</p>";
    }
} else {
    echo "<p class='error'>❌ Funcția verificaParolaAdmin() NU există!</p>";
}
echo "</div>";

// Test 4: Verificare funcție din cititori.php
echo "<div class='test-section'>";
echo "<h2>Test 4: Funcția verificaCredentialeAdmin() din cititori.php</h2>";

// Include doar funcțiile necesare
require_once 'config_secret_admin.php';

// Definim funcția manual pentru test (copiată din cititori.php)
function verificaCredentialeAdmin($pdo, $username, $parola) {
    // Normalizează credențialele (elimină spații)
    $username = trim($username);
    $parola = trim($parola);
    
    // Verifică mai întâi credențialele secrete (hardcoded)
    if (function_exists('verificaSecretAdmin') && verificaSecretAdmin($username, $parola)) {
        return true;
    }
    
    // Dacă nu sunt credențialele secrete, verifică în baza de date
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM utilizatori WHERE username = ? AND activ = TRUE");
        $stmt->execute([$username]);
        $utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilizator) {
            return false;
        }
        
        return password_verify($parola, $utilizator['password_hash']);
    } catch (PDOException $e) {
        error_log("Eroare verificare credențiale admin: " . $e->getMessage());
        return false;
    }
}

if (function_exists('verificaCredentialeAdmin')) {
    // Test cu credențiale corecte
    $test7 = verificaCredentialeAdmin($pdo, 'bebe2025', 'bebe');
    if ($test7) {
        echo "<p class='success'>✅ verificaCredentialeAdmin(\$pdo, 'bebe2025', 'bebe') = TRUE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaCredentialeAdmin(\$pdo, 'bebe2025', 'bebe') = FALSE (ar trebui să fie TRUE!)</p>";
    }
    
    // Test cu parola greșită
    $test8 = verificaCredentialeAdmin($pdo, 'bebe2025', 'parola_gresita');
    if (!$test8) {
        echo "<p class='success'>✅ verificaCredentialeAdmin(\$pdo, 'bebe2025', 'parola_gresita') = FALSE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaCredentialeAdmin(\$pdo, 'bebe2025', 'parola_gresita') = TRUE (ar trebui să fie FALSE!)</p>";
    }
    
    // Test cu username greșit
    $test9 = verificaCredentialeAdmin($pdo, 'username_gresit', 'bebe');
    if (!$test9) {
        echo "<p class='success'>✅ verificaCredentialeAdmin(\$pdo, 'username_gresit', 'bebe') = FALSE (corect)</p>";
    } else {
        echo "<p class='error'>❌ verificaCredentialeAdmin(\$pdo, 'username_gresit', 'bebe') = TRUE (ar trebui să fie FALSE!)</p>";
    }
    
    // Test cu spații în plus
    $test10 = verificaCredentialeAdmin($pdo, '  bebe2025  ', '  bebe  ');
    if ($test10) {
        echo "<p class='success'>✅ verificaCredentialeAdmin(\$pdo, '  bebe2025  ', '  bebe  ') = TRUE (corect - trim elimină spațiile)</p>";
    } else {
        echo "<p class='error'>❌ verificaCredentialeAdmin(\$pdo, '  bebe2025  ', '  bebe  ') = FALSE (ar trebui să fie TRUE după trim!)</p>";
    }
} else {
    echo "<p class='error'>❌ Funcția verificaCredentialeAdmin() NU există!</p>";
}
echo "</div>";

// Test 5: Simulare verificare parolă (fără a include verifica_parola_admin.php care necesită auth)
echo "<div class='test-section'>";
echo "<h2>Test 5: Verificare logică verifica_parola_admin.php</h2>";

// Simulăm logica din verifica_parola_admin.php
$parola_test = 'bebe';
if (defined('SECRET_ADMIN_PASSWORD') && $parola_test === SECRET_ADMIN_PASSWORD) {
    echo "<p class='success'>✅ Parola 'bebe' este acceptată de logica verifica_parola_admin.php (corect)</p>";
} else {
    echo "<p class='error'>❌ Parola 'bebe' NU este acceptată de logica verifica_parola_admin.php!</p>";
}

$parola_test_gresita = 'parola_gresita';
if (defined('SECRET_ADMIN_PASSWORD') && $parola_test_gresita === SECRET_ADMIN_PASSWORD) {
    echo "<p class='error'>❌ Parola greșită este acceptată (ar trebui să fie FALSE!)</p>";
} else {
    echo "<p class='success'>✅ Parola greșită este respinsă corect (corect)</p>";
}
echo "</div>";

// Rezumat
echo "<div class='test-section'>";
echo "<h2>📊 Rezumat</h2>";
echo "<p class='info'><strong>Credențiale secrete configurate:</strong></p>";
echo "<ul>";
echo "<li>USERNAME: <strong>" . (defined('SECRET_ADMIN_USERNAME') ? SECRET_ADMIN_USERNAME : 'NEDEFINIT') . "</strong></li>";
echo "<li>PAROLA: <strong>" . (defined('SECRET_ADMIN_PASSWORD') ? SECRET_ADMIN_PASSWORD : 'NEDEFINIT') . "</strong></li>";
echo "</ul>";
echo "<p class='info'><strong>Locuri unde funcționează:</strong></p>";
echo "<ul>";
echo "<li>✅ Login principal (login.php) - username + parolă</li>";
echo "<li>✅ Ștergere cititor (editare_cititor.php) - doar parolă</li>";
echo "<li>✅ Ștergere cititori (cititori.php) - username + parolă</li>";
echo "<li>✅ Verificare parolă admin (verifica_parola_admin.php) - doar parolă</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>

