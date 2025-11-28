<?php
// config.php - Configurare conexiune bază de date și email

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// ==========================================
// CONFIGURARE BAZĂ DE DATE
// ==========================================
$host = 'localhost';
$dbname = 'biblioteca';
$username = 'root';
$password = '';

// ==========================================
// CONFIGURARE EMAIL SMTP
// ==========================================
// Opțiunea 1: Pentru Gmail (necesită "App Password" - https://myaccount.google.com/apppasswords)
// Opțiunea 2: Pentru alt server SMTP (Outlook, Yahoo, etc.)
// Opțiunea 3: Pentru server SMTP local (hMailServer, MailHog, etc.)

define('SMTP_HOST', 'smtp.gmail.com');      // sau 'smtp.office365.com', 'smtp-mail.outlook.com'
define('SMTP_PORT', 587);                    // 587 pentru TLS, 465 pentru SSL
define('SMTP_USER', 'YOUR-EMAIL@gmail.com');      // Adresa ta de email
define('SMTP_PASS', 'GOOGLE SECRET PASSWORD');   // Parola aplicației Gmail
define('SMTP_FROM_EMAIL', 'YOUR-EMAIL@gmail.com');
define('SMTP_FROM_NAME', 'Biblioteca Municipală');
define('SMTP_SECURE', 'tls');                // 'tls' sau 'ssl'

// Pentru Windows fără SMTP extern, folosește MailHog sau similar pentru testare locală
// Download: https://github.com/mailhog/MailHog/releases
// Pornește MailHog și setează: SMTP_HOST='localhost', SMTP_PORT=1025

try {
    // 🔥 IMPORTANT: Setează charset=utf8mb4 în DSN
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    // Forțează encoding UTF-8 pentru toată sesiunea MySQL
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    $pdo->exec("SET character_set_results=utf8mb4");
    $pdo->exec("SET collation_connection=utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    die("Eroare conexiune: " . $e->getMessage());
}
