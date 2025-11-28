<?php
/**
 * Script pentru verificarea serverului Linux
 * Rulează: php verifica_server.php
 */

// Credențiale server
$SERVER_IP = "YOUR-IP-or-http://WEBSITE";
$SSH_USER = "root";
$SSH_PASS = "YOUR-SERVER-PASSWORD";
$SSH_PORT = 22;

echo "🔍 Verificare Server Linux\n";
echo "═══════════════════════════════════════\n\n";
echo "Server: $SERVER_IP\n";
echo "User: $SSH_USER\n\n";

// Verifică dacă există extensia SSH2
if (!function_exists('ssh2_connect')) {
    echo "⚠️ Extensia PHP SSH2 nu este instalată.\n";
    echo "Pentru a verifica serverul, folosește:\n";
    echo "1. PuTTY sau WinSCP pentru conectare manuală\n";
    echo "2. Sau rulează direct pe server:\n\n";
    echo "Script pentru rulare pe server:\n";
    echo "─────────────────────────────────\n";
    echo "#!/bin/bash\n";
    echo "echo '=== SPATIU DISPONIBIL ==='\n";
    echo "df -h\n";
    echo "echo ''\n";
    echo "echo '=== MYSQL VERSIUNE ==='\n";
    echo "mysql --version 2>&1 || mariadb --version 2>&1\n";
    echo "echo ''\n";
    echo "echo '=== BAZE DE DATE EXISTENTE ==='\n";
    echo "mysql -u root -p -e 'SHOW DATABASES;' 2>&1 | grep -v '^Database$' | grep -v '^information_schema$' | grep -v '^performance_schema$' | grep -v '^mysql$'\n";
    echo "echo ''\n";
    echo "echo '=== SPATIU UTILIZAT DE MYSQL ==='\n";
    echo "du -sh /var/lib/mysql 2>/dev/null || du -sh /usr/local/mysql/data 2>/dev/null || echo 'Nu s-a găsit directorul MySQL'\n";
    echo "echo ''\n";
    echo "echo '=== PROCESE MYSQL ==='\n";
    echo "ps aux | grep -i mysql | grep -v grep\n";
    exit;
}

// Încearcă conexiunea SSH
try {
    $connection = @ssh2_connect($SERVER_IP, $SSH_PORT);
    
    if (!$connection) {
        throw new Exception("Nu se poate conecta la server");
    }
    
    if (!@ssh2_auth_password($connection, $SSH_USER, $SSH_PASS)) {
        throw new Exception("Autentificare eșuată");
    }
    
    echo "✅ Conectat la server!\n\n";
    
    // Verifică spațiul disponibil
    echo "📊 SPATIU DISPONIBIL:\n";
    $stream = ssh2_exec($connection, 'df -h');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output . "\n";
    
    // Verifică versiunea MySQL
    echo "📊 VERSIUNE MYSQL:\n";
    $stream = ssh2_exec($connection, 'mysql --version 2>&1 || mariadb --version 2>&1 || echo "MySQL/MariaDB nu este în PATH"');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output . "\n";
    
    // Verifică bazele de date existente
    echo "📊 BAZE DE DATE EXISTENTE:\n";
    $stream = ssh2_exec($connection, "mysql -u root -p'$SSH_PASS' -e 'SHOW DATABASES;' 2>&1 | grep -v '^Database$' | grep -v '^information_schema$' | grep -v '^performance_schema$' | grep -v '^mysql$' | grep -v '^sys$'");
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output . "\n";
    
    // Verifică spațiul utilizat de MySQL
    echo "📊 SPATIU UTILIZAT DE MYSQL:\n";
    $stream = ssh2_exec($connection, 'du -sh /var/lib/mysql 2>/dev/null || du -sh /usr/local/mysql/data 2>/dev/null || echo "Nu s-a găsit directorul MySQL"');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output . "\n";
    
    // Verifică dacă MySQL rulează
    echo "📊 STATUS MYSQL:\n";
    $stream = ssh2_exec($connection, 'systemctl status mysql 2>&1 | head -5 || systemctl status mariadb 2>&1 | head -5 || service mysql status 2>&1 | head -5 || echo "Nu s-a putut verifica statusul"');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    echo $output . "\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    echo "\n";
    echo "📝 INSTRUCȚIUNI MANUALE:\n";
    echo "─────────────────────────────────\n";
    echo "Conectează-te manual la server folosind PuTTY sau WinSCP:\n";
    echo "  Host: $SERVER_IP\n";
    echo "  Port: $SSH_PORT\n";
    echo "  User: $SSH_USER\n";
    echo "  Pass: $SSH_PASS\n";
    echo "\n";
    echo "Apoi rulează următoarele comenzi:\n";
    echo "  df -h                    # Verifică spațiul\n";
    echo "  mysql --version          # Verifică MySQL\n";
    echo "  mysql -u root -p -e 'SHOW DATABASES;'  # Vezi bazele de date\n";
}
?>


