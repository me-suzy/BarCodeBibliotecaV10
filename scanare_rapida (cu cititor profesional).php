<?php
// scanare_rapida.php - Interfață optimizată pentru scanner hardware
require_once 'config.php';
session_start();

// ===== CONFIGURARE SECURITATE =====
$TIMEOUT_SECUNDE = 120; // 2 minute inactivitate = reset automat

// Verifică timeout sesiune
if (isset($_SESSION['cititor_activ']) && isset($_SESSION['ultima_activitate'])) {
    $timp_inactiv = time() - $_SESSION['ultima_activitate'];
    if ($timp_inactiv > $TIMEOUT_SECUNDE) {
        unset($_SESSION['cititor_activ']);
        $mesaj_timeout = "⏱️ Sesiunea anterioară a expirat după {$TIMEOUT_SECUNDE} secunde de inactivitate.";
    }
}

// Reset manual
if (isset($_GET['reset'])) {
    unset($_SESSION['cititor_activ']);
    header('Location: scanare_rapida.php');
    exit;
}

$mesaj = $mesaj_timeout ?? '';
$tip_mesaj = '';
$sound = '';

// ===== PROCESARE SCANARE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');
    
    // Actualizează timpul ultimei activități
    $_SESSION['ultima_activitate'] = time();

    if (empty($cod_scanat)) {
        $mesaj = "⚠️ Cod invalid!";
        $tip_mesaj = "warning";
        $sound = "error";
    } else {
        try {
            // ===== SCANARE CITITOR (USER***) =====
            if (preg_match('/^USER\d+$/i', $cod_scanat)) {
                $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
                $stmt->execute([strtoupper($cod_scanat)]);
                $cititor = $stmt->fetch();

                if ($cititor) {
                    // Salvează prezența
                    $stmt_prezenta = $pdo->prepare("
                        INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare)
                        VALUES (?, CURDATE(), CURTIME())
                    ");
                    $stmt_prezenta->execute([strtoupper($cod_scanat)]);

                    // Actualizează sesiunea
                    $_SESSION['cititor_activ'] = [
                        'cod_bare' => $cititor['cod_bare'],
                        'nume' => $cititor['nume'],
                        'prenume' => $cititor['prenume'],
                        'timestamp' => time()
                    ];

                    // Număr cărți împrumutate
                    $stmt_imprumuturi = $pdo->prepare("
                        SELECT COUNT(*) FROM imprumuturi 
                        WHERE cod_cititor = ? AND status = 'activ'
                    ");
                    $stmt_imprumuturi->execute([$cititor['cod_bare']]);
                    $nr_carti = $stmt_imprumuturi->fetchColumn();

                    $mesaj = "✅ Bine ai venit: <strong>{$cititor['nume']} {$cititor['prenume']}</strong><br>" .
                            "📚 {$nr_carti} " . ($nr_carti == 1 ? 'carte împrumutată' : 'cărți împrumutate');
                    $tip_mesaj = "success";
                    $sound = "success";
                } else {
                    $mesaj = "❌ Cititor necunoscut: {$cod_scanat}";
                    $tip_mesaj = "danger";
                    $sound = "error";
                }
            }
            // ===== SCANARE CARTE (BOOK***) =====
            elseif (preg_match('/^BOOK\d+$/i', $cod_scanat)) {
                if (!isset($_SESSION['cititor_activ'])) {
                    $mesaj = "⚠️ <strong>Scanează ÎNTÂI carnetul cititorului!</strong>";
                    $tip_mesaj = "warning";
                    $sound = "error";
                } else {
                    $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
                    $cod_carte = strtoupper($cod_scanat);

                    // Verifică dacă cartea există
                    $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                    $stmt->execute([$cod_carte]);
                    $carte = $stmt->fetch();

                    if (!$carte) {
                        $mesaj = "❌ Carte necunoscută: <strong>{$cod_scanat}</strong>";
                        $tip_mesaj = "danger";
                        $sound = "error";
                    } else {
                        // Verifică împrumut existent
                        $stmt = $pdo->prepare("
                            SELECT i.*, c.nume, c.prenume 
                            FROM imprumuturi i
                            JOIN cititori c ON i.cod_cititor = c.cod_bare
                            WHERE i.cod_carte = ? AND i.status = 'activ'
                        ");
                        $stmt->execute([$cod_carte]);
                        $imprumut = $stmt->fetch();

                        if ($imprumut) {
                            if ($imprumut['cod_cititor'] === $cod_cititor) {
                                // RETURNARE
                                $stmt = $pdo->prepare("
                                    UPDATE imprumuturi
                                    SET status = 'returnat', data_returnare = NOW()
                                    WHERE cod_carte = ? AND cod_cititor = ? AND status = 'activ'
                                ");
                                $stmt->execute([$cod_carte, $cod_cititor]);

                                $mesaj = "📥 <strong>Carte RETURNATĂ!</strong><br>📕 {$carte['titlu']}";
                                $tip_mesaj = "info";
                                $sound = "return";
                            } else {
                                $mesaj = "❌ Cartea este deja împrumutată de:<br><strong>{$imprumut['nume']} {$imprumut['prenume']}</strong>";
                                $tip_mesaj = "danger";
                                $sound = "error";
                            }
                        } else {
                            // ÎMPRUMUT NOU
                            $stmt = $pdo->prepare("
                                INSERT INTO imprumuturi (cod_cititor, cod_carte, status) 
                                VALUES (?, ?, 'activ')
                            ");
                            $stmt->execute([$cod_cititor, $cod_carte]);

                            $mesaj = "📤 <strong>Carte ÎMPRUMUTATĂ!</strong><br>📕 {$carte['titlu']}";
                            $tip_mesaj = "success";
                            $sound = "success";
                        }
                    }
                }
            } else {
                $mesaj = "❌ Cod invalid: <strong>{$cod_scanat}</strong><br>Folosește USER*** sau BOOK***";
                $tip_mesaj = "danger";
                $sound = "error";
            }
        } catch (PDOException $e) {
            $mesaj = "❌ Eroare bază de date: " . $e->getMessage();
            $tip_mesaj = "danger";
            $sound = "error";
        }
    }
}

// Actualizează ultima activitate dacă există cititor activ
if (isset($_SESSION['cititor_activ']) && !isset($_SESSION['ultima_activitate'])) {
    $_SESSION['ultima_activitate'] = time();
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanare Hardware - Bibliotecă</title>
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
            max-width: 900px;
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

        .scanner-icon {
            font-size: 4em;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .cititor-activ {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }

        .cititor-activ h2 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .cititor-activ p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .timer-container {
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 10px;
        }

        .timer-bar {
            background: rgba(255,255,255,0.3);
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .timer-fill {
            background: linear-gradient(90deg, #fff, #38ef7d);
            height: 100%;
            transition: width 1s linear;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #11998e;
        }

        .reset-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ff6b6b;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            box-shadow: 0 5px 15px rgba(255,107,107,0.4);
            transition: all 0.3s;
        }

        .reset-btn:hover {
            background: #ee5a52;
            transform: translateY(-2px);
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
            padding: 20px;
            border: 4px solid #667eea;
            border-radius: 12px;
            font-size: 2em;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
            background: #f8f9fa;
        }

        .scan-input:focus {
            outline: none;
            border-color: #38ef7d;
            background: white;
            box-shadow: 0 0 20px rgba(56, 239, 125, 0.3);
        }

        .alert {
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 1.3em;
            font-weight: 600;
            animation: slideIn 0.3s;
            text-align: center;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 3px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 3px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 3px solid #ffeaa7;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 3px solid #f5c6cb;
        }

        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }

        .instructions h3 {
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions ol {
            margin-left: 25px;
            font-size: 1.1em;
            line-height: 1.8;
            text-align: left;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
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

        .timeout-warning {
            background: #ff9800;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: bold;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="scanner-icon">📡</div>
            <h1>Scanare Hardware</h1>
            <p style="color: #666; font-size: 1.1em;">Scanează codurile de bare direct cu cititorul</p>
        </div>

        <?php if (isset($_SESSION['cititor_activ'])): ?>
            <div class="cititor-activ">
                <a href="?reset=1" class="reset-btn">🔄 UTILIZATOR NOU</a>
                <h2>👤 <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume']); ?></h2>
                <p>Cod: <?php echo htmlspecialchars($_SESSION['cititor_activ']['cod_bare']); ?></p>
                
                <div class="timer-container">
                    <div class="timer-bar">
                        <div class="timer-fill" id="timerFill">
                            <span id="timerText">--:--</span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="timeoutWarning" style="display: none;" class="timeout-warning">
                ⚠️ Sesiunea va expira în 30 de secunde! Scanează o carte sau resetează.
            </div>
        <?php endif; ?>

        <?php if (isset($mesaj) && !empty($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <div class="scan-area">
            <form method="POST" id="scanForm">
                <input type="text" 
                       name="cod_scanat" 
                       id="scanInput"
                       class="scan-input" 
                       placeholder="SCANEAZĂ CODUL..."
                       autocomplete="off"
                       autofocus>
            </form>

            <div class="instructions">
                <h3>📋 Instrucțiuni:</h3>
                <ol>
                    <li><strong>Scanează carnetul cititorului</strong> (USER***)</li>
                    <li><strong>Scanează fiecare carte</strong> (BOOK***) pentru împrumut/returnare</li>
                    <li>Sistemul detectează automat dacă împrumută sau returnează</li>
                    <li>⏱️ Sesiunea expiră automat după <strong><?php echo $TIMEOUT_SECUNDE; ?> secunde</strong> de inactivitate</li>
                </ol>
            </div>

            <div class="nav-links">
                <a href="index.php">🏠 Acasă</a>
                <a href="imprumuturi.php">📖 Împrumuturi</a>
                <a href="raport_prezenta.php">📊 Raport prezență</a>
            </div>
        </div>
    </div>

    <!-- Sunete feedback -->
    <audio id="soundSuccess" preload="auto">
        <source src="data:audio/wav;base64,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" type="audio/wav">
    </audio>
    <audio id="soundError" preload="auto">
        <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQsMAAB/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/" type="audio/wav">
    </audio>

    <script>
        const TIMEOUT_SECUNDE = <?php echo $TIMEOUT_SECUNDE; ?>;
        const form = document.getElementById('scanForm');
        const input = document.getElementById('scanInput');
        
        <?php if (isset($_SESSION['cititor_activ'])): ?>
        const ultimaActivitate = <?php echo $_SESSION['ultima_activitate'] ?? time(); ?>;
        const acum = Math.floor(Date.now() / 1000);
        let secundeRamase = TIMEOUT_SECUNDE - (acum - ultimaActivitate);

        // Update timer
        const timerInterval = setInterval(function() {
            secundeRamase--;
            
            if (secundeRamase <= 0) {
                clearInterval(timerInterval);
                window.location.reload();
                return;
            }

            const minute = Math.floor(secundeRamase / 60);
            const secunde = secundeRamase % 60;
            const display = minute + ':' + (secunde < 10 ? '0' : '') + secunde;
            
            document.getElementById('timerText').textContent = display;
            
            const procentaj = (secundeRamase / TIMEOUT_SECUNDE) * 100;
            document.getElementById('timerFill').style.width = procentaj + '%';

            // Alertă la 30 secunde
            if (secundeRamase === 30) {
                document.getElementById('timeoutWarning').style.display = 'block';
                document.getElementById('soundError').play();
            }
        }, 1000);
        <?php endif; ?>

        // Auto-submit când scanner-ul introduce codul
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const cod = input.value.trim();
            if (cod.length > 0) {
                this.submit();
            }
        });

        // Play sound feedback
        <?php if (isset($sound)): ?>
            <?php if ($sound === 'success' || $sound === 'return'): ?>
                document.getElementById('soundSuccess').play();
            <?php elseif ($sound === 'error'): ?>
                document.getElementById('soundError').play();
            <?php endif; ?>
        <?php endif; ?>

        // Auto-clear și re-focus după submit
        window.addEventListener('load', function() {
            setTimeout(function() {
                input.value = '';
                input.focus();
            }, 2000);
        });

        // Asigură-te că inputul e mereu în focus
        setInterval(function() {
            if (document.activeElement !== input) {
                input.focus();
            }
        }, 1000);
    </script>
</body>
</html>