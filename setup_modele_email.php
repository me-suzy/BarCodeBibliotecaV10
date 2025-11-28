<?php
/**
 * Script pentru creare automată a tabelului modele_email și inserare modele
 * Rulează acest script o singură dată pentru a inițializa modelele de email
 */
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Setup Modele Email</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        h1 { color: #667eea; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #e7f3ff; color: #004085; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #2196F3; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Setup Modele Email</h1>";

try {
    // Verifică dacă tabelul există deja
    $stmt = $pdo->query("SHOW TABLES LIKE 'modele_email'");
    $tabel_exista = $stmt->rowCount() > 0;
    
    if ($tabel_exista) {
        echo "<div class='info'><strong>ℹ️ Tabelul 'modele_email' există deja.</strong><br>Verific dacă modelele sunt completate...</div>";
        
        // Verifică dacă există modele
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM modele_email");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count >= 3) {
            echo "<div class='success'><strong>✅ Modelele de email sunt deja configurate!</strong><br>Există $count modele în baza de date.</div>";
            echo "<a href='test_modele_email.php' class='btn'>📧 Mergi la Test Modele Email</a>";
        } else {
            echo "<div class='info'><strong>⚠️ Tabelul există dar are doar $count modele.</strong><br>Voi adăuga modelele...</div>";
            $tabel_exista = false; // Forțează recrearea
        }
    }
    
    if (!$tabel_exista || $count < 3) {
        echo "<div class='info'><strong>📝 Creez tabelul și modelele...</strong></div>";
        
        // Creează tabelul
        $query_create = "
        CREATE TABLE IF NOT EXISTS modele_email (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tip_notificare ENUM('imprumut', 'reminder', 'intarziere') NOT NULL,
            subiect VARCHAR(255) NOT NULL,
            template_html TEXT NOT NULL,
            template_text TEXT,
            variabile_utilizate TEXT COMMENT 'Listă variabile disponibile (JSON)',
            activ BOOLEAN DEFAULT TRUE,
            data_creare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_actualizare TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tip (tip_notificare),
            INDEX idx_activ (activ)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
        ";
        
        $pdo->exec($query_create);
        echo "<div class='success'>✅ Tabelul 'modele_email' a fost creat cu succes!</div>";
        
        // Șterge modelele existente (dacă există) pentru a le reînlocui
        $pdo->exec("DELETE FROM modele_email");
        
        // Model 1: Email la Împrumut
        $template_imprumut_html = '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }
        .book-details { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }
        .book-title { font-weight: bold; color: #667eea; font-size: 16px; }
        .book-info { color: #666; font-size: 14px; margin-top: 5px; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .info-box strong { color: #1976D2; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>📚 Confirmare Împrumut</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="content">
            <div class="greeting">
                Bună ziua <strong>{{NUME_COMPLET}}</strong>,
            </div>
            
            <p>Vă mulțumim că sunteți cititor fidel al bibliotecii noastre și vă confirmăm că ați împrumutat cu succes {{FRAZA_CARTE}}:</p>
            
            <div class="book-details">
                {{LISTA_CARTI}}
            </div>
            
            <div class="info-box">
                <p><strong>📅 Data returnare recomandată:</strong> {{DATA_RETURNARE}}</p>
                <p><strong>📍 Locație bibliotecă:</strong> Biblioteca Academiei Române - Iași</p>
                <p><strong>⏰ Program:</strong> Luni - Vineri: 09:00 - 17:00</p>
            </div>
            
            <p>Vă rugăm să respectați termenul de returnare pentru a permite și altor cititori să beneficieze de {{FRAZA_BENEFICIEZE}}.</p>
            
            <p>Pentru întrebări sau prelungire termen, vă rugăm să ne contactați.</p>
            
            <p style="margin-top: 30px;">Cu respect,<br>
            <strong>Echipa Bibliotecii</strong><br>
            Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="footer">
            <p>Acest email a fost generat automat de sistemul de notificări al bibliotecii.</p>
            <p>Pentru întrebări: bib.acadiasi@gmail.com</p>
        </div>
    </div>
</body>
</html>';
        
        $template_imprumut_text = 'Bună ziua {{NUME_COMPLET}},

Vă mulțumim că sunteți cititor fidel al bibliotecii noastre și vă confirmăm că ați împrumutat cu succes {{FRAZA_CARTE}}:

{{LISTA_CARTI_TEXT}}

Data returnare recomandată: {{DATA_RETURNARE}}
Locație bibliotecă: Biblioteca Academiei Române - Iași
Program: Luni - Vineri: 09:00 - 17:00

Vă rugăm să respectați termenul de returnare pentru a permite și altor cititori să beneficieze de {{FRAZA_BENEFICIEZE}}.

Cu respect,
Echipa Bibliotecii
Biblioteca Academiei Române - Iași';
        
        $stmt = $pdo->prepare("INSERT INTO modele_email (tip_notificare, subiect, template_html, template_text, variabile_utilizate) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            'imprumut',
            '📚 Confirmare Împrumut - Biblioteca Academiei Române - Iași',
            $template_imprumut_html,
            $template_imprumut_text,
            '["NUME_COMPLET", "LISTA_CARTI", "LISTA_CARTI_TEXT", "DATA_RETURNARE", "FRAZA_CARTE", "FRAZA_BENEFICIEZE"]'
        ]);
        echo "<div class='success'>✅ Model 'Împrumut' adăugat!</div>";
        
        // Model 2: Reminder Returnare
        $template_reminder_html = '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }
        .book-details { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }
        .book-title { font-weight: bold; color: #856404; font-size: 16px; }
        .book-info { color: #666; font-size: 14px; margin-top: 5px; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .warning-box strong { color: #856404; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .info-box strong { color: #1976D2; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>⏰ Reminder Returnare</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="content">
            <div class="greeting">
                Bună ziua <strong>{{NUME_COMPLET}}</strong>,
            </div>
            
            <p>Vă mulțumim că sunteți cititor fidel al bibliotecii noastre.</p>
            
            <p>Vă aducem la cunoștință că termenul de păstrare pentru {{FRAZA_CARTE}} se apropie de scadență:</p>
            
            <div class="book-details">
                {{LISTA_CARTI}}
            </div>
            
            <div class="warning-box">
                <p><strong>📅 Termen returnare:</strong> {{DATA_RETURNARE}}</p>
                <p><strong>⏳ Zile rămase:</strong> {{ZILE_RAMASE}} zile</p>
            </div>
            
            <p>Vă rugăm să returnați {{FRAZA_RETURNARE}} înainte de data scadenței pentru a permite și altor cititori să {{FRAZA_IMPRUMUTE}} împrumute pentru studiu personal.</p>
            
            <div class="info-box">
                <p><strong>📍 Locație bibliotecă:</strong> Biblioteca Academiei Române - Iași</p>
                <p><strong>⏰ Program:</strong> Luni - Vineri: 09:00 - 17:00</p>
                <p><strong>📞 Contact:</strong> Pentru prelungire termen sau întrebări, vă rugăm să ne contactați.</p>
            </div>
            
            <p style="margin-top: 30px;">Cu respect,<br>
            <strong>Echipa Bibliotecii</strong><br>
            Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="footer">
            <p>Acest email a fost generat automat de sistemul de notificări al bibliotecii.</p>
            <p>Pentru întrebări: bib.acadiasi@gmail.com</p>
        </div>
    </div>
</body>
</html>';
        
        $template_reminder_text = 'Bună ziua {{NUME_COMPLET}},

Vă mulțumim că sunteți cititor fidel al bibliotecii noastre.

Vă aducem la cunoștință că termenul de păstrare pentru {{FRAZA_CARTE}} se apropie de scadență:

{{LISTA_CARTI_TEXT}}

Termen returnare: {{DATA_RETURNARE}}
Zile rămase: {{ZILE_RAMASE}} zile

Vă rugăm să returnați {{FRAZA_RETURNARE}} înainte de data scadenței pentru a permite și altor cititori să {{FRAZA_IMPRUMUTE}} împrumute pentru studiu personal.

Locație bibliotecă: Biblioteca Academiei Române - Iași
Program: Luni - Vineri: 09:00 - 17:00

Cu respect,
Echipa Bibliotecii
Biblioteca Academiei Române - Iași';
        
        $stmt->execute([
            'reminder',
            '⏰ Reminder: Termen Returnare Aproape - Biblioteca Academiei Române - Iași',
            $template_reminder_html,
            $template_reminder_text,
            '["NUME_COMPLET", "LISTA_CARTI", "LISTA_CARTI_TEXT", "DATA_RETURNARE", "ZILE_RAMASE", "FRAZA_CARTE", "FRAZA_RETURNARE", "FRAZA_IMPRUMUTE"]'
        ]);
        echo "<div class='success'>✅ Model 'Reminder' adăugat!</div>";
        
        // Model 3: Alertă Întârziere
        $template_intarziere_html = '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }
        .book-details { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }
        .book-title { font-weight: bold; color: #721c24; font-size: 16px; }
        .book-info { color: #666; font-size: 14px; margin-top: 5px; }
        .urgent-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .urgent-box strong { color: #721c24; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .info-box strong { color: #1976D2; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🚨 Alertă Întârziere</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="content">
            <div class="greeting">
                Bună ziua <strong>{{NUME_COMPLET}}</strong>,
            </div>
            
            <p>Vă mulțumim că sunteți cititor fidel al bibliotecii noastre.</p>
            
            <p><strong>Vă aducem la cunoștință că a expirat termenul de păstrare</strong> pentru {{FRAZA_CARTE}}:</p>
            
            <div class="book-details">
                {{LISTA_CARTI}}
            </div>
            
            <div class="urgent-box">
                <p><strong>⚠️ Data returnare recomandată:</strong> {{DATA_RETURNARE}}</p>
                <p><strong>📅 Data expirare:</strong> {{DATA_EXPIRARE}}</p>
                <p><strong>⏰ Zile întârziere:</strong> {{ZILE_INTARZIERE}} zile</p>
            </div>
            
            <p><strong>Vă rugăm urgent să returnați {{FRAZA_RETURNARE}}</strong> pentru a permite și altor cititori să {{FRAZA_IMPRUMUTE}} împrumute pentru studiu personal.</p>
            
            <p>Înțelegem că pot apărea situații neprevăzute, dar vă rugăm să ne contactați cât mai curând pentru a discuta soluții.</p>
            
            <div class="info-box">
                <p><strong>📍 Locație bibliotecă:</strong> Biblioteca Academiei Române - Iași</p>
                <p><strong>⏰ Program:</strong> Luni - Vineri: 09:00 - 17:00</p>
                <p><strong>📞 Contact:</strong> Pentru întrebări sau prelungire termen, vă rugăm să ne contactați urgent.</p>
            </div>
            
            <p style="margin-top: 30px;">Cu respect,<br>
            <strong>Echipa Bibliotecii</strong><br>
            Biblioteca Academiei Române - Iași</p>
        </div>
        <div class="footer">
            <p>Acest email a fost generat automat de sistemul de notificări al bibliotecii.</p>
            <p>Pentru întrebări: bib.acadiasi@gmail.com</p>
        </div>
    </div>
</body>
</html>';
        
        $template_intarziere_text = 'Bună ziua {{NUME_COMPLET}},

Vă mulțumim că sunteți cititor fidel al bibliotecii noastre.

Vă aducem la cunoștință că a expirat termenul de păstrare pentru {{FRAZA_CARTE}}:

{{LISTA_CARTI_TEXT}}

Data returnare recomandată: {{DATA_RETURNARE}}
Data expirare: {{DATA_EXPIRARE}}
Zile întârziere: {{ZILE_INTARZIERE}} zile

Vă rugăm urgent să returnați {{FRAZA_RETURNARE}} pentru a permite și altor cititori să {{FRAZA_IMPRUMUTE}} împrumute pentru studiu personal.

Înțelegem că pot apărea situații neprevăzute, dar vă rugăm să ne contactați cât mai curând pentru a discuta soluții.

Locație bibliotecă: Biblioteca Academiei Române - Iași
Program: Luni - Vineri: 09:00 - 17:00

Cu respect,
Echipa Bibliotecii
Biblioteca Academiei Române - Iași';
        
        $stmt->execute([
            'intarziere',
            '🚨 URGENT: Cărți Întârziate - Acțiune Necesară - Biblioteca Academiei Române - Iași',
            $template_intarziere_html,
            $template_intarziere_text,
            '["NUME_COMPLET", "LISTA_CARTI", "LISTA_CARTI_TEXT", "DATA_RETURNARE", "DATA_EXPIRARE", "ZILE_INTARZIERE", "FRAZA_CARTE", "FRAZA_RETURNARE", "FRAZA_IMPRUMUTE"]'
        ]);
        echo "<div class='success'>✅ Model 'Întârziere' adăugat!</div>";
        
        echo "<div class='success' style='margin-top: 20px;'><strong>🎉 SUCCES!</strong><br>Toate modelele de email au fost create cu succes!</div>";
        echo "<a href='test_modele_email.php' class='btn'>📧 Mergi la Test Modele Email</a>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ EROARE:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "    </div>
</body>
</html>";
?>

