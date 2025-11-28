<?php
/**
 * Script de test pentru autentificare
 * Testează direct autentificarea cu credențialele specificate
 */

session_start();
require_once 'config.php';
require_once 'functions_autentificare.php';

echo "🧪 Test Autentificare\n";
echo str_repeat("=", 60) . "\n\n";

// Testează utilizatorii
$teste = [
    ['larisa2025', 'admin2024'],
    ['bunica20', 'iubire32']
];

foreach ($teste as $test) {
    $username = $test[0];
    $password = $test[1];
    
    echo "Test: $username / $password\n";
    echo str_repeat("-", 60) . "\n";
    
    // Verifică dacă utilizatorul există
    $stmt = $pdo->prepare("SELECT * FROM utilizatori WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Utilizatorul '$username' NU există în baza de date!\n";
        echo "📝 Rulează: php instaleaza_autentificare.php\n\n";
        continue;
    }
    
    echo "✅ Utilizator găsit:\n";
    echo "   - ID: {$user['id']}\n";
    echo "   - Username: {$user['username']}\n";
    echo "   - Nume: {$user['nume']}\n";
    echo "   - Activ: " . ($user['activ'] ? 'DA' : 'NU') . "\n";
    echo "   - Hash: " . substr($user['password_hash'], 0, 30) . "...\n\n";
    
    // Testează parola direct
    echo "🔐 Testare parolă:\n";
    $verificare_directa = password_verify($password, $user['password_hash']);
    
    if ($verificare_directa) {
        echo "   ✅ password_verify() = TRUE (parola corectă)\n";
    } else {
        echo "   ❌ password_verify() = FALSE (parola INCORECTĂ)\n";
        echo "   🔧 Generând hash nou...\n";
        
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        echo "   📝 Hash nou generat: " . substr($new_hash, 0, 30) . "...\n";
        
        // Actualizează în baza de date
        $stmt_update = $pdo->prepare("UPDATE utilizatori SET password_hash = ? WHERE username = ?");
        $stmt_update->execute([$new_hash, $username]);
        
        echo "   ✅ Hash actualizat în baza de date!\n";
        
        // Testează din nou
        $verificare_noua = password_verify($password, $new_hash);
        if ($verificare_noua) {
            echo "   ✅ Verificare după actualizare: SUCCES!\n";
        }
    }
    
    echo "\n";
    
    // Testează funcția de autentificare
    echo "🔐 Testare funcție autentificaUtilizator():\n";
    $rezultat = autentificaUtilizator($pdo, $username, $password);
    
    if ($rezultat['success']) {
        echo "   ✅ Autentificare REUȘITĂ!\n";
        echo "   📝 Mesaj: {$rezultat['mesaj']}\n";
        echo "   👤 Utilizator: {$rezultat['utilizator']['nume']}\n";
        
        // Verifică sesiunea
        if (isset($_SESSION['utilizator_autentificat']) && $_SESSION['utilizator_autentificat'] === true) {
            echo "   ✅ Sesiune creată corect!\n";
            echo "   📝 Utilizator ID: {$_SESSION['utilizator_id']}\n";
            echo "   📝 Username: {$_SESSION['utilizator_username']}\n";
        } else {
            echo "   ⚠️ Sesiunea NU a fost creată!\n";
        }
    } else {
        echo "   ❌ Autentificare EȘUATĂ!\n";
        echo "   📝 Mesaj: {$rezultat['mesaj']}\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "✅ Test complet!\n";

