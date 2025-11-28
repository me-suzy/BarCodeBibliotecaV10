<?php
// scanare_mini_monitor_alternativ.php - Versiune compactă pentru monitor secundar
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_vizare.php'; // ← NOU

$TIMEOUT_SECUNDE = 120;

// Verifică timeout
if (isset($_SESSION['cititor_activ']) && isset($_SESSION['ultima_activitate'])) {
    if (time() - $_SESSION['ultima_activitate'] > $TIMEOUT_SECUNDE) {
        unset($_SESSION['cititor_activ']);
    }
}

if (isset($_GET['reset'])) {
    unset($_SESSION['cititor_activ']);
    header('Location: scanare_mini_monitor_alternativ.php');
    exit;
}

$mesaj = '';
$tip_mesaj = '';
$sound = '';
$status_vizare = null; // ← NOU
$cod_cititor_curent = null; // ← NOU

// ← NOU: Procesare vizare permis
if (isset($_POST['vizeaza_permis'])) {
    $cod_cititor = trim($_POST['cod_cititor_vizare']);
    $rezultat = vizeazaPermis($pdo, $cod_cititor);
    
    if ($rezultat['success']) {
        $mesaj = $rezultat['mesaj'];
        $tip_mesaj = 'success';
        $sound = 'success';
        $status_vizare = verificaVizarePermis($pdo, $cod_cititor);
        $cod_cititor_curent = $cod_cititor;
        
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
        $stmt->execute([$cod_cititor]);
        $cititor_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['cititor_activ'] = [
            'cod_bare' => $cititor_data['cod_bare'],
            'nume' => $cititor_data['nume'],
            'prenume' => $cititor_data['prenume']
        ];
        $_SESSION['ultima_activitate'] = time();
    } else {
        $mesaj = $rezultat['mesaj'];
        $tip_mesaj = 'danger';
        $sound = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['vizeaza_permis'])) {
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
			// ← NOU: VIZARE AUTOMATĂ
			$rezultat_auto_vizare = vizeazaPermisAutomat($pdo, strtoupper($cod_scanat));
			
			// Verifică status vizare DUPĂ vizare automată
			$status_vizare = verificaVizarePermis($pdo, strtoupper($cod_scanat));
			$cod_cititor_curent = strtoupper($cod_scanat);
			
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

			if ($rezultat_auto_vizare['vizat'] && strpos($rezultat_auto_vizare['mesaj'], 'AUTOMAT') !== false) {
				$mesaj = "🎉 VIZAT AUTO " . date('Y');
				$tip_mesaj = "success";
				$sound = "success";
			} elseif ($status_vizare['vizat']) {
				$mesaj = "✅ " . $cititor['nume'] . " " . $cititor['prenume'];
				$tip_mesaj = "success";
				$sound = "success";
			} else {
				$mesaj = "⚠️ " . $cititor['nume'] . " - NEVIZAT!";
				$tip_mesaj = "warning";
				$sound = "warning";
			}
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
                    // ← NOU: Verifică vizare ÎNAINTE de împrumut
                    $status_vizare_temp = verificaVizarePermis($pdo, $_SESSION['cititor_activ']['cod_bare']);
                    
                    if (!$status_vizare_temp['vizat']) {
                        $mesaj = "🔴 BLOCAT - Permis nevizat!";
                        $tip_mesaj = "danger";
                        $sound = "error";
                        $status_vizare = $status_vizare_temp;
                        $cod_cititor_curent = $_SESSION['cititor_activ']['cod_bare'];
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
                                // RETURNARE
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
                                // ÎMPRUMUT NOU
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

// ← NOU: Verifică status vizare pentru cititor activ
if (isset($_SESSION['cititor_activ']) && !$status_vizare) {
    $status_vizare = verificaVizarePermis($pdo, $_SESSION['cititor_activ']['cod_bare']);
    $cod_cititor_curent = $_SESSION['cititor_activ']['cod_bare'];
}

// Statistici rapide pentru ziua curentă
$stats_azi = [
    'imprumuturi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_imprumut) = CURDATE() AND status = 'activ'")->fetchColumn(),
    'returnari' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_returnare) = CURDATE()")->fetchColumn(),
    'vizitatori' => $pdo->query("SELECT COUNT(DISTINCT cod_cititor) FROM sesiuni_biblioteca WHERE data = CURDATE()")->fetchColumn()
];

// Ultimele 5 scanări
$stmt = $pdo->query("
    SELECT 
        i.data_imprumut,
        c.titlu,
        i.status,
        CASE 
            WHEN i.status = 'activ' THEN i.data_imprumut
            ELSE i.data_returnare
        END as data_actiune
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    ORDER BY data_actiune DESC
    LIMIT 5
");
$recent = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Mini</title>
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
            max-height: 98vh;
            overflow-y: auto;
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

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5px;
            margin-bottom: 10px;
        }

        .stat-box {
            padding: 8px;
            border-radius: 5px;
            text-align: center;
            font-size: 0.75em;
        }

        .stat-box-success {
            background: #d4edda;
            color: #155724;
        }

        .stat-box-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .stat-box-warning {
            background: #fff3cd;
            color: #856404;
        }

        .stat-box .number {
            font-size: 1.4em;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
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

        /* ← NOU: Styles pentru status vizare - versiune compactă */
        @keyframes pulse-red-mini {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            50% { 
                transform: scale(1.03); 
                box-shadow: 0 0 0 5px rgba(220, 53, 69, 0);
            }
        }
        
        .status-vizare-mini {
            margin: 8px 0;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.85em;
        }
        
        .status-vizare-mini.nevizat {
            background: #ffe6e6;
            border: 2px solid #dc3545;
            color: #721c24;
            animation: pulse-red-mini 2s infinite;
        }
        
        .status-vizare-mini.vizat {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .btn-vizeaza-mini {
            font-size: 0.8rem;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 5px;
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
            animation: pulse-red-mini 2s infinite;
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
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .scan-input:focus {
            outline: none;
            border-color: #38ef7d;
            background: white;
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(56, 239, 125, 0.3);
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.95em;
            font-weight: 600;
            text-align: center;
            animation: slideInBounce 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes slideInBounce {
            0% { transform: translateY(-20px) scale(0.8); opacity: 0; }
            50% { transform: translateY(5px) scale(1.05); }
            100% { transform: translateY(0) scale(1); opacity: 1; }
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

        .recent-section {
            margin-top: 15px;
            font-size: 0.8em;
        }

        .recent-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .recent-item {
            padding: 5px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 3px solid #ddd;
        }

        .recent-item.activ {
            border-left-color: #28a745;
        }

        .recent-item.returnat {
            border-left-color: #6c757d;
            opacity: 0.7;
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
            transition: all 0.3s;
        }

        .links a:hover {
            background: #764ba2;
            transform: translateY(-2px);
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

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="status-dot"></span>Scanner Bibliotecă</h1>
        </div>

        <!-- STATISTICI RAPIDE -->
        <div class="stats-mini">
            <div class="stat-box stat-box-success">
                <span class="number"><?php echo $stats_azi['imprumuturi']; ?></span>
                📤 Împr. azi
            </div>
            <div class="stat-box stat-box-info">
                <span class="number"><?php echo $stats_azi['returnari']; ?></span>
                📥 Ret. azi
            </div>
            <div class="stat-box stat-box-warning">
                <span class="number"><?php echo $stats_azi['vizitatori']; ?></span>
                👥 Vizitatori
            </div>
        </div>

        <!-- CITITOR ACTIV -->
        <?php if (isset($_SESSION['cititor_activ'])): ?>
            <div class="cititor-box">
                <a href="?reset=1" class="reset-btn">RESET</a>
                <strong>👤 <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume']); ?></strong>
                <div style="font-size: 0.85em; opacity: 0.9;">
                    <?php echo htmlspecialchars($_SESSION['cititor_activ']['cod_bare']); ?>
                </div>
                <div class="timer" id="timerDisplay">--:--</div>
            </div>
            
            <!-- ← NOU: STATUS VIZARE COMPACT -->
            <?php if ($status_vizare && $cod_cititor_curent): ?>
            <div class="status-vizare-mini <?= $status_vizare['vizat'] ? 'vizat' : 'nevizat' ?>">
                <strong><?= $status_vizare['icon'] ?> <?= $status_vizare['vizat'] ? 'VIZAT' : 'NEVIZAT' ?></strong>
                <?php if ($status_vizare['data_vizare']): ?>
                <div style="font-size: 0.8em; margin-top: 3px;">
                    <?= date('d.m.Y', strtotime($status_vizare['data_vizare'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$status_vizare['vizat']): ?>
                <form method="POST" style="margin-top: 5px;">
                    <input type="hidden" name="cod_cititor_vizare" value="<?= htmlspecialchars($cod_cititor_curent) ?>">
                    <button type="submit" name="vizeaza_permis" class="btn-vizeaza-mini">
                        ⚠️ VIZEAZĂ
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- MESAJ FEEDBACK -->
        <?php if (isset($mesaj) && !empty($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <!-- INPUT SCANARE -->
        <form method="POST" id="scanForm">
            <input type="text" 
                   name="cod_scanat" 
                   id="scanInput"
                   class="scan-input" 
                   placeholder="SCANEAZĂ..."
                   autocomplete="off"
                   autofocus>
        </form>

        <!-- ISTORIC RECENT -->
        <?php if (count($recent) > 0): ?>
        <div class="recent-section">
            <div class="recent-title">
                📋 Ultimele scanări:
            </div>
            <?php foreach ($recent as $r): ?>
                <div class="recent-item <?php echo $r['status']; ?>">
                    <span><?php echo htmlspecialchars(mb_substr($r['titlu'], 0, 30)); ?><?php echo mb_strlen($r['titlu']) > 30 ? '...' : ''; ?></span>
                    <span style="color: <?php echo $r['status'] === 'activ' ? '#28a745' : '#6c757d'; ?>">
                        <?php echo $r['status'] === 'activ' ? '📤' : '📥'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- LINK-URI RAPIDE -->
        <div class="links">
            <a href="index.php" target="_blank">🏠</a>
            <a href="imprumuturi.php" target="_blank">📖</a>
            <a href="status_vizari.php" target="_blank">✅</a>
        </div>
    </div>

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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                input.value = '';
                input.focus();
            }
        });
    </script>
</body>
</html>