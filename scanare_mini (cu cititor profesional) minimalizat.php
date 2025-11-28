<?php
// scanare_mini.php - Versiune compactă pentru fereastră mică
require_once 'config.php';
session_start();

$TIMEOUT_SECUNDE = 120;

// Verifică timeout
if (isset($_SESSION['cititor_activ']) && isset($_SESSION['ultima_activitate'])) {
    if (time() - $_SESSION['ultima_activitate'] > $TIMEOUT_SECUNDE) {
        unset($_SESSION['cititor_activ']);
    }
}

if (isset($_GET['reset'])) {
    unset($_SESSION['cititor_activ']);
    header('Location: scanare_mini.php');
    exit;
}

$mesaj = '';
$tip_mesaj = '';
$sound = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');
    $_SESSION['ultima_activitate'] = time();

    if (!empty($cod_scanat)) {
        try {
            // CITITOR
            if (preg_match('/^USER\d+$/i', $cod_scanat)) {
                $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
                $stmt->execute([strtoupper($cod_scanat)]);
                $cititor = $stmt->fetch();

                if ($cititor) {
                    $stmt_prezenta = $pdo->prepare("
                        INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare)
                        VALUES (?, CURDATE(), CURTIME())
                    ");
                    $stmt_prezenta->execute([strtoupper($cod_scanat)]);

                    $_SESSION['cititor_activ'] = [
                        'cod_bare' => $cititor['cod_bare'],
                        'nume' => $cititor['nume'],
                        'prenume' => $cititor['prenume']
                    ];

                    $mesaj = "✅ " . $cititor['nume'] . " " . $cititor['prenume'];
                    $tip_mesaj = "success";
                    $sound = "success";
                } else {
                    $mesaj = "❌ Cititor necunoscut";
                    $tip_mesaj = "danger";
                    $sound = "error";
                }
            }
            // CARTE
            elseif (preg_match('/^BOOK\d+$/i', $cod_scanat)) {
                if (!isset($_SESSION['cititor_activ'])) {
                    $mesaj = "⚠️ Scanează carnetul!";
                    $tip_mesaj = "warning";
                    $sound = "error";
                } else {
                    $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
                    $cod_carte = strtoupper($cod_scanat);

                    $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                    $stmt->execute([$cod_carte]);
                    $carte = $stmt->fetch();

                    if (!$carte) {
                        $mesaj = "❌ Carte necunoscută";
                        $tip_mesaj = "danger";
                        $sound = "error";
                    } else {
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
                                $stmt = $pdo->prepare("
                                    UPDATE imprumuturi
                                    SET status = 'returnat', data_returnare = NOW()
                                    WHERE cod_carte = ? AND cod_cititor = ? AND status = 'activ'
                                ");
                                $stmt->execute([$cod_carte, $cod_cititor]);
                                $mesaj = "📥 RETURNAT";
                                $tip_mesaj = "info";
                                $sound = "return";
                            } else {
                                $mesaj = "❌ Împrumutată de altcineva";
                                $tip_mesaj = "danger";
                                $sound = "error";
                            }
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO imprumuturi (cod_cititor, cod_carte, status) 
                                VALUES (?, ?, 'activ')
                            ");
                            $stmt->execute([$cod_cititor, $cod_carte]);
                            $mesaj = "📤 ÎMPRUMUTAT";
                            $tip_mesaj = "success";
                            $sound = "success";
                        }
                    }
                }
            } else {
                $mesaj = "❌ Cod invalid";
                $tip_mesaj = "danger";
                $sound = "error";
            }
        } catch (PDOException $e) {
            $mesaj = "❌ Eroare BD";
            $tip_mesaj = "danger";
            $sound = "error";
        }
    }
}

if (isset($_SESSION['cititor_activ']) && !isset($_SESSION['ultima_activitate'])) {
    $_SESSION['ultima_activitate'] = time();
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px;
            overflow: hidden;
        }

        .container {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .header h1 {
            color: #667eea;
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .cititor-box {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
            font-size: 0.9em;
        }

        .cititor-box strong {
            font-size: 1.1em;
            display: block;
        }

        .reset-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ff6b6b;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75em;
            font-weight: bold;
        }

        .timer {
            font-size: 0.8em;
            opacity: 0.9;
            margin-top: 5px;
        }

        .scan-input {
            width: 100%;
            padding: 12px;
            border: 3px solid #667eea;
            border-radius: 6px;
            font-size: 1.2em;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            background: #f8f9fa;
        }

        .scan-input:focus {
            outline: none;
            border-color: #38ef7d;
            background: white;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.95em;
            font-weight: 600;
            text-align: center;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .links {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }

        .links a {
            flex: 1;
            padding: 6px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.75em;
            text-align: center;
            font-weight: 600;
        }

        .links a:hover {
            background: #764ba2;
        }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #38ef7d;
            margin-right: 5px;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="status-dot"></span>Scanner Bibliotecă</h1>
        </div>

        <?php if (isset($_SESSION['cititor_activ'])): ?>
            <div class="cititor-box">
                <a href="?reset=1" class="reset-btn">RESET</a>
                <strong>👤 <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume']); ?></strong>
                <div style="font-size: 0.85em; opacity: 0.9;">
                    <?php echo htmlspecialchars($_SESSION['cititor_activ']['cod_bare']); ?>
                </div>
                <div class="timer" id="timerDisplay">--:--</div>
            </div>
        <?php endif; ?>

        <?php if (isset($mesaj) && !empty($mesaj)): ?>
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

        <div class="links">
            <a href="index.php" target="_blank">🏠</a>
            <a href="imprumuturi.php" target="_blank">📖</a>
            <a href="raport_prezenta.php" target="_blank">📊</a>
        </div>
    </div>

    <audio id="soundSuccess" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZSA0PVqzn77BdGAg+mN3yvW0gBSuByPDaizsIHGjD8OSYTAwPUqzn7bJfGQlAmt3yvmwgBSqAyfDajzwIG2vG8OWhUQ0PUqvm7bJgGQlAm93xv24hBSh+yPDakz0IHGrD7+WUTw8PU63m77JhGgo/mt3xv24hBSh+yPDakz0HHGrD7+WUTw8PU63m77JhGgo/mt3xwG4hBSh9x/Dakz0HHGrD7+WUTw8PU63m77JhGgo/mt3xwG4hBSh9x/DWnUAME1io5++2YhoJP5ve8cFtIQUpcMjw1p1ADxNYqOfvtmIaCT+b3vHAbSEFKXDI8NadQA8TWKjn77ZiGgk/m97xwG0hBSl2yO7UoUQRElSp5e+2YhoJQJre8L9tIgUpbsfv0aVJEg9TqeTwuGIaCkCZ3O6/bSIFKWjB7s6pTxIORanm8LZhGQpBmtzuv20iBSpmv+3MrVITDUWn5O+1YRsKQprd7r5tIgUqY73szK1SFA1Fp+TvtWEbCkKa3e6+bSIFKmK87MutUhMNRqbk8LVhGwpCmt3uvm0iBSphvO3KsVQTDUal5O+1YRsKQprd7r5tIgUqYrztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKmG87cqvUhQNRabk8LVhGwpCmt3uvm0iBSphvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqYbztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKmC87cqxVBMNRqfk77VhGwpCmt3uvm0iBSphvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqYbztyrFUEw1Gp+TvtWEbCkKa3e6+bSIFKmG87cqvUhQNRabk8LVhGwpCmt3uvm0iBSphvO3KsVQTDUWm5O+1YRsKQprd7r5tIgUqYbztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKmG87cqxVBMNRabk77VhGwpCmt3uvm0iBSphvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqYbztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKmG87cqwVBMNRqbk8LVhGwpCmt3uvm0iBSphvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqYbztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKl+87cqxVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKl+87cqvUhQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKl+87cqvUhQNRabk8LVhGwpCmt3uvm0iBSpfvO3KrlQUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKl+87cqvUhQNRabk8LVhGwpCmt3uvm0iBSpfvO3KrlQUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq9SFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq5UFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq5UFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq5UFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq5UFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uvm0iBSpfvO3Kr1IUDUWm5PC1YRsKQprd7r5tIgUqX7ztyq5UFA1FpuTwtWEbCkKa3e6+bSIFKl+87cquVBQNRabk8LVhGwpCmt3uv" type="audio/wav">
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
        let secundeRamase = TIMEOUT_SECUNDE - (Math.floor(Date.now() / 1000) - ultimaActivitate);

        const timerInterval = setInterval(function() {
            secundeRamase--;
            if (secundeRamase <= 0) {
                clearInterval(timerInterval);
                window.location.reload();
                return;
            }
            const minute = Math.floor(secundeRamase / 60);
            const secunde = secundeRamase % 60;
            document.getElementById('timerDisplay').textContent = 
                '⏱️ ' + minute + ':' + (secunde < 10 ? '0' : '') + secunde;
        }, 1000);
        <?php endif; ?>

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (input.value.trim().length > 0) {
                this.submit();
            }
        });

        <?php if (isset($sound)): ?>
            <?php if ($sound === 'success' || $sound === 'return'): ?>
                document.getElementById('soundSuccess').play();
            <?php elseif ($sound === 'error'): ?>
                document.getElementById('soundError').play();
            <?php endif; ?>
        <?php endif; ?>

        window.addEventListener('load', function() {
            setTimeout(function() {
                input.value = '';
                input.focus();
            }, 2000);
        });

        setInterval(function() {
            if (document.activeElement !== input) {
                input.focus();
            }
        }, 1000);
    </script>
</body>
</html>