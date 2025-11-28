<?php
/**
 * Pagină pentru testare modele email
 * Permite testarea modelelor de email cu date reale sau simulate
 */

header('Content-Type: text/html; charset=UTF-8');

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

require_once 'config.php';

// Setează encoding-ul conexiunii MySQL la UTF-8
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET CHARACTER SET utf8mb4");
$pdo->exec("SET character_set_connection=utf8mb4");

require_once 'send_email.php';
require_once 'functions_email_templates.php';
require_once 'sistem_notificari.php'; // Pentru configurația email

$mesaj = '';
$tip_mesaj = '';

// Procesare test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_model') {
        $tip_notificare = $_POST['tip_notificare'];
        $email_test = $_POST['email_test'];
        
        // Date simulate pentru test
        $cititor_test = [
            'nume' => $_POST['nume_test'] ?? 'Popescu',
            'prenume' => $_POST['prenume_test'] ?? 'Ion',
            'email' => $email_test
        ];
        
        $carti_test = [
            [
                'titlu' => $_POST['titlu_carte_1'] ?? 'Amintiri din copilărie',
                'autor' => $_POST['autor_carte_1'] ?? 'Ion Creangă',
                'cod_bare' => 'BOOK001',
                'data_imprumut' => date('Y-m-d', strtotime('-10 days')),
                'locatie_completa' => 'Raft A - Nivel 1 - Poziția 01'
            ]
        ];
        
        // Adaugă a doua carte dacă este completată
        if (!empty($_POST['titlu_carte_2'])) {
            $carti_test[] = [
                'titlu' => $_POST['titlu_carte_2'],
                'autor' => $_POST['autor_carte_2'] ?? '',
                'cod_bare' => 'BOOK002',
                'data_imprumut' => date('Y-m-d', strtotime('-12 days')),
                'locatie_completa' => 'Raft A - Nivel 1 - Poziția 02'
            ];
        }
        
        // Calculează data returnare
        $data_returnare = date('Y-m-d', strtotime($carti_test[0]['data_imprumut'] . ' +14 days'));
        
        // Trimite email de test
        $rezultat = trimiteEmailPersonalizat(
            $email_test,
            $tip_notificare,
            $cititor_test,
            $carti_test,
            $config_email,
            $data_returnare
        );
        
        if ($rezultat['success']) {
            $mesaj = "✅ Email de test trimis cu succes la: <strong>$email_test</strong><br><small>Tip: " . ucfirst($tip_notificare) . "</small>";
            $tip_mesaj = "success";
        } else {
            $mesaj = "❌ Eroare: <strong>" . htmlspecialchars($rezultat['message']) . "</strong>";
            $tip_mesaj = "danger";
        }
    }
}

// Verifică dacă tabelul există
$tabel_exista = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'modele_email'");
    $tabel_exista = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $tabel_exista = false;
}

// Obține modelele disponibile (doar dacă tabelul există)
$modele = [];
if ($tabel_exista) {
    // Asigură-te că encoding-ul conexiunii este UTF-8 înainte de a citi modelele
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    
    $modele = [
        'imprumut' => obtineModelEmail('imprumut'),
        'reminder' => obtineModelEmail('reminder'),
        'intarziere' => obtineModelEmail('intarziere')
    ];
    
    // Conversie UTF-8 pentru modelele obținute (dacă este necesar)
    foreach ($modele as $tip => $model) {
        if ($model) {
            if (isset($model['subiect'])) {
                $model['subiect'] = mb_convert_encoding($model['subiect'], 'UTF-8', 'UTF-8');
            }
            if (isset($model['variabile_utilizate'])) {
                $model['variabile_utilizate'] = mb_convert_encoding($model['variabile_utilizate'], 'UTF-8', 'UTF-8');
            }
            $modele[$tip] = $model;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Modele Email</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 15px; padding: 20px 30px; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .header h1 { color: #667eea; font-size: 2em; }
        .section { background: white; border-radius: 15px; padding: 30px; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input, select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1em; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .btn { padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1.1em; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .preview-box { background: #f8f9fa; border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin-top: 20px; max-height: 500px; overflow-y: auto; }
        .model-info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Test Modele Email</h1>
            <p style="color: #666; margin-top: 10px;">Testează modelele de email cu date simulate</p>
        </div>

        <?php if (!$tabel_exista): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Tabelul 'modele_email' nu există!</strong><br>
                Trebuie să rulezi mai întâi scriptul de setup pentru a crea tabelul și modelele.<br>
                <a href="setup_modele_email.php" class="btn" style="display: inline-block; margin-top: 10px; text-decoration: none;">
                    🔧 Rulează Setup Modele Email
                </a>
            </div>
        <?php endif; ?>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($tip_mesaj, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <!-- Verificare modele -->
        <div class="section">
            <h2>📋 Modele Disponibile</h2>
            <?php foreach ($modele as $tip => $model): ?>
                <?php if ($model): ?>
                    <div class="model-info">
                        <strong><?php echo ucfirst($tip); ?>:</strong> <?php echo htmlspecialchars($model['subiect'], ENT_QUOTES, 'UTF-8'); ?>
                        <br><small>Variabile: <?php echo htmlspecialchars($model['variabile_utilizate'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                <?php else: ?>
                    <div class="model-info" style="background: #fff3cd; border-color: #ffc107;">
                        <strong>⚠️ Model <?php echo ucfirst($tip); ?>:</strong> Nu există în baza de date! Rulează <code>update_database_modele_email.sql</code>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Formular test -->
        <div class="section">
            <h2>🧪 Test Email</h2>
            <form method="POST">
                <input type="hidden" name="action" value="test_model">
                
                <div class="form-group">
                    <label>Tip Notificare *</label>
                    <select name="tip_notificare" required>
                        <option value="imprumut">📚 Email la Împrumut</option>
                        <option value="reminder">⏰ Reminder Returnare</option>
                        <option value="intarziere">🚨 Alertă Întârziere</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Email Destinatar *</label>
                    <input type="email" name="email_test" value="ioan.fantanaru@gmail.com" required>
                </div>

                <div class="form-group">
                    <label>Nume Cititor *</label>
                    <input type="text" name="nume_test" value="Popescu" required>
                </div>

                <div class="form-group">
                    <label>Prenume Cititor *</label>
                    <input type="text" name="prenume_test" value="Ion" required>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px;">Carte 1 *</h3>
                <div class="form-group">
                    <label>Titlu Carte</label>
                    <input type="text" name="titlu_carte_1" value="Amintiri din copilărie" required>
                </div>

                <div class="form-group">
                    <label>Autor Carte</label>
                    <input type="text" name="autor_carte_1" value="Ion Creangă">
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px;">Carte 2 (Opțional)</h3>
                <div class="form-group">
                    <label>Titlu Carte 2</label>
                    <input type="text" name="titlu_carte_2" placeholder="Lasă gol pentru o singură carte">
                </div>

                <div class="form-group">
                    <label>Autor Carte 2</label>
                    <input type="text" name="autor_carte_2" placeholder="Lasă gol pentru o singură carte">
                </div>

                <button type="submit" class="btn">📧 Trimite Email de Test</button>
            </form>
        </div>

        <div class="section">
            <h2>ℹ️ Informații</h2>
            <p><strong>Variabile disponibile în modele:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><code>{{NUME_COMPLET}}</code> - Numele complet al cititorului</li>
                <li><code>{{LISTA_CARTI}}</code> - Lista cărților în format HTML</li>
                <li><code>{{LISTA_CARTI_TEXT}}</code> - Lista cărților în format text</li>
                <li><code>{{DATA_RETURNARE}}</code> - Data returnare recomandată</li>
                <li><code>{{ZILE_RAMASE}}</code> - Zile rămase până la returnare (pentru reminder)</li>
                <li><code>{{DATA_EXPIRARE}}</code> - Data expirare (pentru întârziere)</li>
                <li><code>{{ZILE_INTARZIERE}}</code> - Zile întârziere (pentru întârziere)</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="sistem_notificari.php" class="btn" style="text-decoration: none; display: inline-block;">← Înapoi la Notificări</a>
        </div>
    </div>
</body>
</html>

