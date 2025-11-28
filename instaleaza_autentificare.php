<?php
/**
 * Script pentru instalarea sistemului de autentificare
 * Rulează acest script o singură dată pentru a configura sistemul
 */

require_once 'config.php';

echo "🔧 Instalare sistem autentificare...\n\n";

try {
    // Activează buffering-ul pentru PDO
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // Creează tabelul utilizatori
    echo "📋 Creare tabel utilizatori...\n";
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
    echo "✅ Tabelul utilizatori creat!\n";
    
    // Inserează utilizatorii
    echo "📝 Inserare utilizatori...\n";
    
    // Utilizator 1: larisa2025 / admin2024
    $password1 = password_hash('admin2024', PASSWORD_DEFAULT);
    $stmt1 = $pdo->prepare("
        INSERT INTO utilizatori (username, password_hash, nume, activ) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            nume = VALUES(nume)
    ");
    $stmt1->execute(['larisa2025', $password1, 'Larisa', true]);
    echo "✅ Utilizator 'larisa2025' creat/actualizat!\n";
    
    // Utilizator 2: bunica20 / iubire32
    $password2 = password_hash('iubire32', PASSWORD_DEFAULT);
    $stmt2 = $pdo->prepare("
        INSERT INTO utilizatori (username, password_hash, nume, activ) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            nume = VALUES(nume)
    ");
    $stmt2->execute(['bunica20', $password2, 'Bunica', true]);
    echo "✅ Utilizator 'bunica20' creat/actualizat!\n";
    
    $stmt1 = null;
    $stmt2 = null;
    
    // Verifică utilizatorii
    echo "\n📊 Utilizatori configurați:\n";
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
    
    echo "✅ Instalare completă!\n";
    echo "\n📝 Credențiale:\n";
    echo "  - larisa2025 / admin2024\n";
    echo "  - bunica20 / iubire32\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

