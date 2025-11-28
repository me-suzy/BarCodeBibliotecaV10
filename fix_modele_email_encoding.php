<?php
/**
 * fix_modele_email_encoding.php
 * Script pentru fixare encoding UTF-8 în modelele de email din baza de date
 * Reînlocuiește template-urile corupte cu versiuni corecte UTF-8
 */

header('Content-Type: text/html; charset=UTF-8');

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Fix Encoding Modele Email</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; background: white; 
                    border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        h1 { color: #667eea; margin-top: 0; }
        .success { background: #d4edda; color: #155724; padding: 15px; 
                  border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; 
                border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 8px; 
               margin: 15px 0; border-left: 4px solid #2196F3; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 30px; background: #667eea; 
              color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Fix Encoding Modele Email</h1>";

try {
    // Setează encoding-ul conexiunii MySQL la UTF-8
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    
    echo "<div class='info'><strong>📝 Reînlocuiesc template-urile cu versiuni corecte UTF-8...</strong></div>";
    
    // Subiecte corecte cu diacritice
    $subiecte_corecte = [
        'imprumut' => '📚 Confirmare Împrumut - Biblioteca Academiei Române - Iași',
        'reminder' => '⏰ Reminder: Termen Returnare Aproape - Biblioteca Academiei Române - Iași',
        'intarziere' => '🚨 URGENT: Cărți Întârziate - Acțiune Necesară - Biblioteca Academiei Române - Iași'
    ];
    
    // Update subiecte pentru fiecare tip
    foreach ($subiecte_corecte as $tip => $subiect_corect) {
        $stmt = $pdo->prepare("
            UPDATE modele_email 
            SET subiect = ? 
            WHERE tip_notificare = ? 
            AND activ = TRUE
        ");
        $stmt->execute([$subiect_corect, $tip]);
        
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✅ <strong>$tip:</strong> Subiect actualizat:<br><code>" . htmlspecialchars($subiect_corect, ENT_QUOTES, 'UTF-8') . "</code></div>";
        } else {
            echo "<div class='error'>❌ <strong>$tip:</strong> Nu s-a găsit model activ pentru actualizare!</div>";
        }
    }
    
    // Verifică rezultatele
    echo "<div class='info'><strong>📋 Verificare rezultate:</strong></div>";
    
    $stmt = $pdo->query("
        SELECT tip_notificare, subiect 
        FROM modele_email 
        WHERE activ = TRUE 
        ORDER BY tip_notificare
    ");
    $modele = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($modele) > 0) {
        echo "<div class='success'>";
        echo "<strong>✅ Modele actualizate:</strong><br><br>";
        foreach ($modele as $model) {
            echo "<strong>" . ucfirst($model['tip_notificare']) . ":</strong><br>";
            echo htmlspecialchars($model['subiect'], ENT_QUOTES, 'UTF-8') . "<br><br>";
        }
        echo "</div>";
    } else {
        echo "<div class='error'><strong>❌ Nu s-au găsit modele active!</strong><br>Rulează mai întâi <code>setup_modele_email.php</code> pentru a crea modelele.</div>";
    }
    
    echo "<div class='success' style='margin-top: 20px;'><strong>🎉 FINALIZAT!</strong><br>Encoding-ul a fost actualizat cu succes!</div>";
    echo "<a href='test_modele_email.php' class='btn'>📧 Mergi la Test Modele Email</a>";
    echo "<a href='index.php' class='btn' style='margin-left: 10px; background: #28a745;'>← Înapoi la Pagina Principală</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ EROARE:</strong><br>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ EROARE GENERALĂ:</strong><br>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
}

echo "    </div>
</body>
</html>";
?>

