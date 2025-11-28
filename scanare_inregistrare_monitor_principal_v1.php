<?php
// scanare_monitor_principal.php - Scanare rapidă pentru înregistrare cititori/cărți noi
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$mesaj = '';
$tip_mesaj = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');

    if (!empty($cod_scanat)) {
        // Detectează tipul și redirectează
        if (preg_match('/^USER\d+$/i', $cod_scanat)) {
            // Redirect la formular cititor cu cod pre-completat
            header("Location: adauga_cititor.php?cod=" . urlencode(strtoupper($cod_scanat)));
            exit;
        } elseif (preg_match('/^BOOK\d+$/i', $cod_scanat)) {
            // Redirect la formular carte cu cod pre-completat
            header("Location: adauga_carte.php?cod=" . urlencode(strtoupper($cod_scanat)));
            exit;
        } else {
            $mesaj = "❌ Cod invalid! Folosește USER*** pentru cititori sau BOOK*** pentru cărți.";
            $tip_mesaj = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Înregistrare Nouă - Scanner</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .scanner-icon {
            font-size: 5em;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .scan-area {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        .scan-input {
            width: 100%;
            padding: 25px;
            border: 4px solid #667eea;
            border-radius: 12px;
            font-size: 2.5em;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 5px;
            background: #f8f9fa;
            margin-bottom: 30px;
        }

        .scan-input:focus {
            outline: none;
            border-color: #38ef7d;
            background: white;
            box-shadow: 0 0 30px rgba(56, 239, 125, 0.5);
        }

        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 1.2em;
            font-weight: 600;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 3px solid #f5c6cb;
        }

        .instructions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
        }

        .instructions h3 {
            font-size: 1.5em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .instructions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .instruction-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .instruction-card h4 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #38ef7d;
        }

        .instruction-card ol {
            margin-left: 20px;
            line-height: 1.8;
            font-size: 1.05em;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        .nav-links a {
            padding: 12px 25px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .example-codes {
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .example-codes h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .code-examples {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .code-example {
            background: #f8f9fa;
            padding: 15px 30px;
            border-radius: 8px;
            border: 3px solid #667eea;
            font-family: 'Courier New', monospace;
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="scanner-icon">🏷️</div>
            <h1>Înregistrare Nouă</h1>
            <p>Scanează codul de bare pentru cititor nou sau carte nouă</p>
        </div>

        <div class="scan-area">
            <?php if (isset($mesaj)): ?>
                <div class="alert alert-<?php echo $tip_mesaj; ?>">
                    <?php echo $mesaj; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="scanForm">
                <input type="text" 
                       name="cod_scanat" 
                       id="scanInput"
                       class="scan-input" 
                       placeholder="SCANEAZĂ..."
                       autocomplete="off"
                       autofocus>
            </form>

            <div class="example-codes">
                <h4>📋 Exemple de coduri valide:</h4>
                <div class="code-examples">
                    <div class="code-example">USER009</div>
                    <div class="code-example">BOOK017</div>
                </div>
            </div>

            <div class="nav-links">
                <a href="index.php">🏠 Acasă</a>
                <a href="adauga_cititor.php">👤 Adaugă cititor manual</a>
                <a href="adauga_carte.php">📕 Adaugă carte manual</a>
            </div>
        </div>

        <div class="instructions">
            <h3>📚 Instrucțiuni de înregistrare</h3>
            <div class="instructions-grid">
                <div class="instruction-card">
                    <h4>👤 Pentru cititor nou:</h4>
                    <ol>
                        <li>Lipește codul USER*** pe carnet</li>
                        <li>Scanează codul cu scanner-ul</li>
                        <li>Formularul se deschide automat</li>
                        <li>Completează: Nume, Prenume, Telefon, Email</li>
                        <li>Salvează</li>
                    </ol>
                </div>
                <div class="instruction-card">
                    <h4>📕 Pentru carte nouă:</h4>
                    <ol>
                        <li>Lipește codul BOOK*** pe carte</li>
                        <li>Scanează codul cu scanner-ul</li>
                        <li>Formularul se deschide automat</li>
                        <li>Completează: Titlu, Autor, ISBN, Locație</li>
                        <li>Salvează</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('scanForm');
        const input = document.getElementById('scanInput');

        // Auto-submit când scanner-ul introduce codul
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const cod = input.value.trim();
            if (cod.length > 0) {
                this.submit();
            }
        });

        // Auto-focus constant
        setInterval(function() {
            if (document.activeElement !== input) {
                input.focus();
            }
        }, 1000);

        // Clear după eroare
        <?php if (isset($mesaj)): ?>
        setTimeout(function() {
            input.value = '';
            input.focus();
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>