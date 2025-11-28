<?php
/**
 * Script pentru repararea și verificarea sistemului de autentificare
 * Rulează acest script pentru a verifica și repara problemele
 */

require_once 'config.php';

echo "🔧 Reparare și Verificare Autentificare\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Activează buffering-ul pentru PDO
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // PASUL 1: Verifică dacă tabelul există
    echo "📋 PASUL 1: Verificare tabel utilizatori...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilizatori'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Tabelul NU există! Se creează...\n";
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS utilizatori (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                nume VARCHAR(100),
                email VARCHAR(100),
                activ BOOLEAN DEFAULT TRUE,
                data_creare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ultima_autentificare TIMESTAMP NULL,
                INDEX idx_username (username),
                INDEX idx_activ (activ)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabelul creat!\n";
    } else {
        echo "✅ Tabelul există!\n";
    }
    
    echo "\n";
    
    // PASUL 2: Verifică și actualizează utilizatorii
    echo "📝 PASUL 2: Verificare și actualizare utilizatori...\n\n";
    
    $utilizatori_config = [
        ['larisa2025', 'admin2024', 'Larisa'],
        ['bunica20', 'iubire32', 'Bunica']
    ];
    
    foreach ($utilizatori_config as $user_config) {
        $username = $user_config[0];
        $password = $user_config[1];
        $nume = $user_config[2];
        
        echo "🔍 Verificare: $username...\n";
        
        // Verifică dacă utilizatorul există
        $stmt = $pdo->prepare("SELECT * FROM utilizatori WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Creează utilizatorul
            echo "  ➕ Utilizatorul nu există. Se creează...\n";
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt_insert = $pdo->prepare("
                INSERT INTO utilizatori (username, password_hash, nume, activ) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_insert->execute([$username, $password_hash, $nume, true]);
            
            echo "  ✅ Utilizator creat!\n";
        } else {
            // Verifică dacă parola funcționează
            echo "  ✅ Utilizator există.\n";
            echo "  🔐 Testare parolă...\n";
            
            $verificare = password_verify($password, $user['password_hash']);
            
            if ($verificare) {
                echo "  ✅ Parola funcționează corect!\n";
            } else {
                echo "  ⚠️ Parola NU funcționează! Se actualizează hash-ul...\n";
                
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt_update = $pdo->prepare("
                    UPDATE utilizatori 
                    SET password_hash = ?, nume = ?, activ = TRUE 
                    WHERE username = ?
                ");
                $stmt_update->execute([$new_hash, $nume, $username]);
                
                echo "  ✅ Hash actualizat!\n";
                
                // Verifică din nou
                $verificare2 = password_verify($password, $new_hash);
                if ($verificare2) {
                    echo "  ✅ Verificare după actualizare: SUCCES!\n";
                } else {
                    echo "  ❌ Verificare după actualizare: EȘEC!\n";
                }
            }
        }
        
        echo "\n";
    }
    
    // PASUL 3: Afișează toți utilizatorii
    echo "📊 PASUL 3: Lista utilizatorilor:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-5s | %-20s | %-15s | %s\n", "ID", "USERNAME", "NUME", "ACTIV");
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->query("SELECT id, username, nume, activ FROM utilizatori ORDER BY id");
    $utilizatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null;
    
    foreach ($utilizatori as $user) {
        printf(
            "%-5s | %-20s | %-15s | %s\n",
            $user['id'],
            $user['username'],
            $user['nume'] ?? '-',
            $user['activ'] ? 'DA' : 'NU'
        );
    }
    
    echo str_repeat("-", 60) . "\n\n";
    
    // PASUL 4: Testare finală
    echo "🧪 PASUL 4: Testare finală autentificare...\n\n";
    
    require_once 'functions_autentificare.php';
    
    foreach ($utilizatori_config as $user_config) {
        $username = $user_config[0];
        $password = $user_config[1];
        
        echo "Test: $username / $password\n";
        
        // Simulează sesiune nouă
        $_SESSION = [];
        
        $rezultat = autentificaUtilizator($pdo, $username, $password);
        
        if ($rezultat['success']) {
            echo "  ✅ Autentificare REUȘITĂ!\n";
            echo "  👤 Utilizator: {$rezultat['utilizator']['nume']}\n";
        } else {
            echo "  ❌ Autentificare EȘUATĂ!\n";
            echo "  📝 Mesaj: {$rezultat['mesaj']}\n";
        }
        
        echo "\n";
    }
    
    echo "✅ Reparare completă!\n";
    echo "\n📝 Credențiale:\n";
    echo "  - larisa2025 / admin2024\n";
    echo "  - bunica20 / iubire32\n";
    echo "\n💡 Acum poți încerca să te autentifici din nou!\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

