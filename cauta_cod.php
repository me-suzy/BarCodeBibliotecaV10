<?php
/**
 * Pagină pentru căutare după cod (cotă sau barcode) în Aleph
 */
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$mesaj = '';
$tip_mesaj = '';
$date_carte = null;
$cod_cautat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cod'])) {
    $cod_cautat = trim($_POST['cod']);
    
    if (empty($cod_cautat)) {
        $mesaj = '⚠️ Te rugăm să introduci un cod (cotă sau barcode)!';
        $tip_mesaj = 'warning';
    } else {
        // ✅ NOU - Include direct funcția din aleph_api.php în loc de request HTTP
        require_once 'aleph_api.php';
        
        // Determină tipul de cod pentru căutare
        $search_type = 'AUTO'; // AUTO detectează automat dacă e cota sau barcode
        
        try {
            // Apelează direct funcția de căutare
            $result = cautaCarteInAleph($cod_cautat, $search_type);
            
            if ($result && isset($result['success'])) {
                if ($result['success']) {
                    $date_carte = $result['data'] ?? null;
                    $mesaj = '✅ Cartea a fost găsită în Aleph!';
                    $tip_mesaj = 'success';
                } else {
                    $mesaj = '❌ ' . ($result['mesaj'] ?? 'Cartea nu a fost găsită în Aleph');
                    $tip_mesaj = 'danger';
                    // Dacă există date parțiale, le afișăm
                    if (isset($result['data_partiala']) && !empty($result['data_partiala'])) {
                        $date_carte = $result['data_partiala'];
                        $mesaj .= ' (date parțiale disponibile)';
                    }
                }
            } else {
                $mesaj = '❌ Răspuns invalid de la API-ul Aleph';
                $tip_mesaj = 'danger';
            }
        } catch (Exception $e) {
            $mesaj = '❌ Eroare la căutare: ' . $e->getMessage();
            $tip_mesaj = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Căutare Cod - Biblioteca</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .date-carte {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .date-carte h2 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .date-carte .field {
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 5px;
        }
        
        .date-carte .field strong {
            color: #333;
            display: inline-block;
            min-width: 120px;
        }
        
        .date-carte .field span {
            color: #666;
        }
        
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #0c5460;
        }

        .app-footer {
            text-align: right;
            padding: 30px 40px;
            margin-top: 40px;
            background: transparent;
        }

        .app-footer p {
            display: inline-block;
            margin: 0;
            padding: 13px 26px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(13px);
            border-radius: 22px;
            color: white;
            font-weight: 400;
            font-size: 0.9em;
            box-shadow: 0 0 18px rgba(196, 181, 253, 0.15),
                        0 4px 16px rgba(0, 0, 0, 0.1),
                        inset 0 1px 1px rgba(255, 255, 255, 0.2);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            transition: all 0.45s ease;
            position: relative;
        }

        .app-footer p::before {
            content: '💡';
            margin-right: 10px;
            font-size: 1.15em;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.6));
        }

        .app-footer p:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.08));
            box-shadow: 0 0 35px rgba(196, 181, 253, 0.3),
                        0 8px 24px rgba(0, 0, 0, 0.15),
                        inset 0 1px 1px rgba(255, 255, 255, 0.3);
            transform: translateY(-3px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Căutare Cod în Aleph</h1>
        
        <div class="info-box">
            <p><strong>💡 Instrucțiuni:</strong></p>
            <p>• Introdu cotă (ex: <code>III-32073</code>, <code>IV-4659</code>)</p>
            <p>• Sau introdu barcode (ex: <code>C196541</code>, <code>C013121</code>)</p>
            <p>• Sistemul va căuta automat în Aleph</p>
        </div>
        
        <?php if ($mesaj): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="cod">Cod (Cotă sau Barcode):</label>
                <input type="text" 
                       id="cod" 
                       name="cod" 
                       placeholder="Ex: III-32073 sau C196541"
                       value="<?php echo htmlspecialchars($cod_cautat); ?>"
                       required
                       autofocus>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-primary">🔍 Caută</button>
                <a href="index.php" class="btn btn-secondary">← Înapoi</a>
            </div>
        </form>
        
        <?php if ($date_carte): ?>
            <div class="date-carte">
                <h2>📚 Date Carte</h2>
                
                <?php if (!empty($date_carte['titlu'])): ?>
                    <div class="field">
                        <strong>Titlu:</strong>
                        <span><?php echo htmlspecialchars($date_carte['titlu']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['autor'])): ?>
                    <div class="field">
                        <strong>Autor:</strong>
                        <span><?php echo htmlspecialchars($date_carte['autor']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['isbn'])): ?>
                    <div class="field">
                        <strong>ISBN:</strong>
                        <span><?php echo htmlspecialchars($date_carte['isbn']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['cota'])): ?>
                    <div class="field">
                        <strong>Cotă:</strong>
                        <span><?php echo htmlspecialchars($date_carte['cota']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['barcode'])): ?>
                    <div class="field">
                        <strong>Barcode:</strong>
                        <span><?php echo htmlspecialchars($date_carte['barcode']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['biblioteca'])): ?>
                    <div class="field">
                        <strong>Bibliotecă:</strong>
                        <span><?php echo htmlspecialchars($date_carte['biblioteca']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['status'])): ?>
                    <div class="field">
                        <strong>Status:</strong>
                        <span><?php echo htmlspecialchars($date_carte['status']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['colectie'])): ?>
                    <div class="field">
                        <strong>Colecție:</strong>
                        <span><?php echo htmlspecialchars($date_carte['colectie']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date_carte['sectiune'])): ?>
                    <div class="field">
                        <strong>Secțiune:</strong>
                        <span><?php echo htmlspecialchars($date_carte['sectiune']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>

