<?php
/**
 * Script pentru verificarea utilizatorilor din baza de date
 */

require_once 'config.php';

echo "🔍 Verificare utilizatori în baza de date...\n\n";

try {
    // Verifică dacă tabelul există
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilizatori'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabelul 'utilizatori' NU există!\n";
        echo "📝 Rulează: php instaleaza_autentificare.php\n";
        exit(1);
    }
    
    echo "✅ Tabelul 'utilizatori' există!\n\n";
    
    // Verifică utilizatorii
    $stmt = $pdo->query("SELECT id, username, password_hash, nume, activ FROM utilizatori ORDER BY id");
    $utilizatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($utilizatori)) {
        echo "❌ Nu există utilizatori în baza de date!\n";
        echo "📝 Rulează: php instaleaza_autentificare.php\n";
        exit(1);
    }
    
    echo "📊 Utilizatori găsiți: " . count($utilizatori) . "\n\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s | %-20s | %-15s | %-10s | %s\n", "ID", "USERNAME", "NUME", "ACTIV", "HASH (primele 20)");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($utilizatori as $user) {
        printf(
            "%-5s | %-20s | %-15s | %-10s | %s\n",
            $user['id'],
            $user['username'],
            $user['nume'] ?? '-',
            $user['activ'] ? 'DA' : 'NU',
            substr($user['password_hash'], 0, 20) . '...'
        );
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Testează autentificarea pentru fiecare utilizator
    echo "🧪 Testare autentificare:\n\n";
    
    $teste = [
        ['larisa2025', 'admin2024'],
        ['bunica20', 'iubire32']
    ];
    
    require_once 'functions_autentificare.php';
    
    foreach ($teste as $test) {
        $username = $test[0];
        $password = $test[1];
        
        echo "Test: $username / $password\n";
        
        // Verifică dacă utilizatorul există
        $stmt = $pdo->prepare("SELECT * FROM utilizatori WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "  ❌ Utilizatorul '$username' NU există în baza de date!\n\n";
            continue;
        }
        
        echo "  ✅ Utilizator găsit: {$user['nume']}\n";
        echo "  📝 Hash stocat: " . substr($user['password_hash'], 0, 30) . "...\n";
        
        // Testează parola
        $verificare = password_verify($password, $user['password_hash']);
        
        if ($verificare) {
            echo "  ✅ Parola corectă!\n";
        } else {
            echo "  ❌ Parola INCORECTĂ!\n";
            echo "  🔧 Generând hash nou pentru parola '$password'...\n";
            
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "  📝 Hash nou: " . substr($new_hash, 0, 30) . "...\n";
            
            // Actualizează hash-ul
            $stmt_update = $pdo->prepare("UPDATE utilizatori SET password_hash = ? WHERE username = ?");
            $stmt_update->execute([$new_hash, $username]);
            
            echo "  ✅ Hash actualizat în baza de date!\n";
            
            // Testează din nou
            $verificare2 = password_verify($password, $new_hash);
            if ($verificare2) {
                echo "  ✅ Verificare după actualizare: SUCCES!\n";
            } else {
                echo "  ❌ Verificare după actualizare: EȘEC!\n";
            }
        }
        
        echo "\n";
    }
    
    echo "✅ Verificare completă!\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

