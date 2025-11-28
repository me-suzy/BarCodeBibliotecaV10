<?php
// scanare_monitor_principal.php - Versiune completă pentru monitor principal
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
    header('Location: scanare_monitor_principal.php');
    exit;
}

$mesaj = '';
$tip_mesaj = '';
$sound = '';
$carte_scanata = null;
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
        $_SESSION['cititor_activ'] = $stmt->fetch(PDO::FETCH_ASSOC);
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
					'prenume' => $cititor['prenume'],
					'telefon' => $cititor['telefon'],
					'email' => $cititor['email']
				];

				if ($rezultat_auto_vizare['vizat'] && strpos($rezultat_auto_vizare['mesaj'], 'AUTOMAT') !== false) {
					$mesaj = $rezultat_auto_vizare['mesaj'] . "<br>Bine ai venit, " . $cititor['prenume'] . " " . $cititor['nume'] . "!";
					$tip_mesaj = "success";
					$sound = "success";
				} elseif ($status_vizare['vizat']) {
					$mesaj = "✅ Bine ai venit, " . $cititor['prenume'] . " " . $cititor['nume'] . "! Permis VIZAT.";
					$tip_mesaj = "success";
					$sound = "success";
				} else {
					$mesaj = "⚠️ Bine ai venit, " . $cititor['prenume'] . " " . $cititor['nume'] . "! ATENȚIE: " . $status_vizare['mesaj'];
					$tip_mesaj = "warning";
					$sound = "warning";
				}
			} else {
				$mesaj = "❌ Cititor necunoscut: " . htmlspecialchars($cod_scanat);
				$tip_mesaj = "danger";
				$sound = "error";
			}
		}
			
			
            // CARTE
            elseif (preg_match('/^BOOK\d+$/i', $cod_scanat)) {
                if (!isset($_SESSION['cititor_activ'])) {
                    $mesaj = "⚠️ Te rog scanează mai întâi carnetul de cititor!";
                    $tip_mesaj = "warning";
                    $sound = "error";
                } else {
                    // ← NOU: Verifică vizare ÎNAINTE de împrumut
                    $status_vizare_temp = verificaVizarePermis($pdo, $_SESSION['cititor_activ']['cod_bare']);
                    
                    if (!$status_vizare_temp['vizat']) {
                        $mesaj = "🔴 ÎMPRUMUT BLOCAT! Permisul nu este vizat pentru anul curent!";
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
                            $mesaj = "❌ Carte necunoscută: " . htmlspecialchars($cod_carte);
                            $tip_mesaj = "danger";
                            $sound = "error";
                        } else {
                            $carte_scanata = $carte;
                            
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
                                    $mesaj = "📥 Carte returnată cu succes!<br><strong>" . htmlspecialchars($carte['titlu']) . "</strong>";
                                    $tip_mesaj = "info";
                                    $sound = "return";
                                } else {
                                    $mesaj = "❌ Cartea este împrumutată de: <strong>" . htmlspecialchars($imprumut['nume'] . ' ' . $imprumut['prenume']) . "</strong>";
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
                                
                                $data_returnare = date('d.m.Y', strtotime('+14 days'));
                                $mesaj = "📤 Împrumut înregistrat cu succes!<br><strong>" . htmlspecialchars($carte['titlu']) . "</strong><br><small>Returnare recomandată: " . $data_returnare . "</small>";
                                $tip_mesaj = "success";
                                $sound = "success";
                            }
                        }
                    }
                }
            } else {
                $mesaj = "❌ Cod invalid: " . htmlspecialchars($cod_scanat) . "<br>Folosește USER*** pentru cititori sau BOOK*** pentru cărți.";
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

if (isset($_SESSION['cititor_activ']) && !isset($_SESSION['ultima_activitate'])) {
    $_SESSION['ultima_activitate'] = time();
}

// ← NOU: Verifică status vizare pentru cititor activ
if (isset($_SESSION['cititor_activ']) && !$status_vizare) {
    $status_vizare = verificaVizarePermis($pdo, $_SESSION['cititor_activ']['cod_bare']);
    $cod_cititor_curent = $_SESSION['cititor_activ']['cod_bare'];
}

// Statistici extinse
$stats = [
    'total_carti' => $pdo->query("SELECT COUNT(*) FROM carti")->fetchColumn(),
    'total_cititori' => $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn(),
    'imprumuturi_active' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'activ'")->fetchColumn(),
    'imprumuturi_azi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_imprumut) = CURDATE()")->fetchColumn(),
    'returnari_azi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_returnare) = CURDATE()")->fetchColumn(),
    'vizitatori_azi' => $pdo->query("SELECT COUNT(DISTINCT cod_cititor) FROM sesiuni_biblioteca WHERE data = CURDATE()")->fetchColumn(),
    'intarzieri' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'activ' AND DATEDIFF(NOW(), data_imprumut) > 14")->fetchColumn()
];

// Istoric cititor activ
$istoric_cititor = [];
if (isset($_SESSION['cititor_activ'])) {
    $stmt = $pdo->prepare("
        SELECT 
            c.titlu,
            c.autor,
            i.data_imprumut,
            i.data_returnare,
            i.status,
            DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut) as zile
        FROM imprumuturi i
        JOIN carti c ON i.cod_carte = c.cod_bare
        WHERE i.cod_cititor = ?
        ORDER BY i.data_imprumut DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['cititor_activ']['cod_bare']]);
    $istoric_cititor = $stmt->fetchAll();
}

// Ultimele 15 împrumuturi/returnări din sistem
$stmt = $pdo->query("
    SELECT 
        i.data_imprumut,
        i.data_returnare,
        i.status,
        c.titlu,
        c.autor,
        cit.nume,
        cit.prenume,
        CASE 
            WHEN i.status = 'activ' THEN i.data_imprumut
            ELSE i.data_returnare
        END as data_actiune
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    ORDER BY data_actiune DESC
    LIMIT 15
");
$activitate_recenta = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanare Împrumuturi - Bibliotecă</title>
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
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #38ef7d;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-home {
            background: #28a745;
            color: white;
        }

        .btn-home:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reports {
            background: #17a2b8;
            color: white;
        }

        .btn-reports:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 2.5em;
        }

        .stat-info h3 {
            font-size: 2em;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 0.9em;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .scan-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .scan-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .cititor-active-box {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
        }

        .cititor-active-box h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .cititor-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
            font-size: 0.95em;
        }

        .cititor-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reset-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff6b6b;
            color: white;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s;
        }

        .reset-btn:hover {
            background: #ee5a5a;
            transform: translateY(-2px);
        }

        .timer-display {
            margin-top: 10px;
            font-size: 1.1em;
            opacity: 0.9;
        }

        /* ← NOU: Styles pentru status vizare */
        @keyframes pulse-red {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            50% { 
                transform: scale(1.05); 
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
        }
        
        .status-vizare-container {
            margin: 20px 0;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .status-vizare-nevizat {
            background: #ffe6e6;
            border: 3px solid #dc3545;
            animation: pulse-red 2s infinite;
        }
        
        .status-vizare-vizat {
            background: #d4edda;
            border: 3px solid #28a745;
        }
        
        .btn-vizeaza {
            font-size: 1.1rem;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            margin-top: 10px;
            animation: pulse-red 2s infinite;
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .icon-large {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
        }

        .scan-input-wrapper {
            position: relative;
            margin-bottom: 20px;
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
            transition: all 0.3s ease;
        }

        .scan-input:focus {
            outline: none;
            border-color: #38ef7d;
            background: white;
            box-shadow: 0 0 30px rgba(56, 239, 125, 0.4);
            transform: scale(1.02);
        }

        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: 600;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
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

        .carte-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            margin-top: 20px;
        }

        .carte-preview h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .carte-detail {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .carte-detail:last-child {
            border-bottom: none;
        }

        .carte-label {
            font-weight: 600;
            color: #666;
        }

        .carte-value {
            color: #333;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .sidebar-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .istoric-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
            transition: all 0.3s;
        }

        .istoric-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .istoric-item.activ {
            border-left-color: #28a745;
            background: #d4edda;
        }

        .istoric-item.returnat {
            border-left-color: #6c757d;
        }

        .istoric-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .istoric-meta {
            font-size: 0.85em;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activitate-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }

        .activitate-item:hover {
            background: #f8f9fa;
        }

        .activitate-item:last-child {
            border-bottom: none;
        }

        .activitate-text {
            flex: 1;
        }

        .activitate-nume {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }

        .activitate-carte {
            color: #666;
            font-size: 0.85em;
        }

        .activitate-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .badge-imprumut {
            background: #d4edda;
            color: #155724;
        }

        .badge-returnare {
            background: #d1ecf1;
            color: #0c5460;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>
                <span class="status-indicator"></span>
                Scanare Împrumuturi
            </h1>
            <div class="header-buttons">
                <a href="index.php" class="btn btn-home">🏠 Acasă</a>
                <a href="rapoarte.php" class="btn btn-reports">📊 Rapoarte</a>
            </div>
        </div>

        <!-- STATISTICI -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_carti']; ?></h3>
                    <p>Total Cărți</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_cititori']; ?></h3>
                    <p>Cititori Înregistrați</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div class="stat-info">
                    <h3><?php echo $stats['imprumuturi_active']; ?></h3>
                    <p>Împrumuturi Active</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-info">
                    <h3><?php echo $stats['imprumuturi_azi']; ?></h3>
                    <p>Împrumuturi Azi</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📥</div>
                <div class="stat-info">
                    <h3><?php echo $stats['returnari_azi']; ?></h3>
                    <p>Returnări Azi</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚶</div>
                <div class="stat-info">
                    <h3><?php echo $stats['vizitatori_azi']; ?></h3>
                    <p>Vizitatori Azi</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-info">
                    <h3 style="color: <?php echo $stats['intarzieri'] > 0 ? '#dc3545' : '#28a745'; ?>">
                        <?php echo $stats['intarzieri']; ?>
                    </h3>
                    <p>Întârzieri</p>
                </div>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="main-grid">
            <!-- ZONA SCANARE -->
            <div class="scan-section">
                <h2>🔍 Scanare Cod de Bare</h2>

                <!-- CITITOR ACTIV -->
                <?php if (isset($_SESSION['cititor_activ'])): ?>
                    <div class="cititor-active-box">
                        <a href="?reset=1" class="reset-btn">❌ RESET</a>
                        <h3>👤 Cititor Activ</h3>
                        <div style="font-size: 1.2em; font-weight: 600;">
                            <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume']); ?>
                        </div>
                        <div class="cititor-info-grid">
                            <div class="cititor-info-item">
                                <span>🆔</span>
                                <span><?php echo htmlspecialchars($_SESSION['cititor_activ']['cod_bare']); ?></span>
                            </div>
                            <?php if (!empty($_SESSION['cititor_activ']['telefon'])): ?>
                            <div class="cititor-info-item">
                                <span>📞</span>
                                <span><?php echo htmlspecialchars($_SESSION['cititor_activ']['telefon']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['cititor_activ']['email'])): ?>
                            <div class="cititor-info-item">
                                <span>📧</span>
                                <span><?php echo htmlspecialchars($_SESSION['cititor_activ']['email']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="timer-display" id="timerDisplay">⏱️ --:--</div>
                    </div>
                    
                    <!-- ← NOU: STATUS VIZARE -->
                    <?php if ($status_vizare && $cod_cititor_curent): ?>
                    <div class="status-vizare-container <?= $status_vizare['vizat'] ? 'status-vizare-vizat' : 'status-vizare-nevizat' ?>">
                        <span class="icon-large"><?= $status_vizare['icon'] ?></span>
                        <h4 style="margin: 10px 0;"><?= $status_vizare['mesaj'] ?></h4>
                        
                        <?php if ($status_vizare['data_vizare']): ?>
                        <p style="margin: 5px 0;">Data vizare: <strong><?= date('d.m.Y', strtotime($status_vizare['data_vizare'])) ?></strong></p>
                        <?php endif; ?>
                        
                        <?php if (!$status_vizare['vizat']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="cod_cititor_vizare" value="<?= htmlspecialchars($cod_cititor_curent) ?>">
                            <button type="submit" name="vizeaza_permis" class="btn-vizeaza">
                                ⚠️ VIZEAZĂ PERMIS ACUM
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                        <strong>⚠️ Pas 1:</strong> Scanează mai întâi carnetul cititorului (USER***)
                    </div>
                <?php endif; ?>

                <!-- MESAJ FEEDBACK -->
                <?php if (!empty($mesaj)): ?>
                    <div class="alert alert-<?php echo $tip_mesaj; ?>">
                        <?php echo $mesaj; ?>
                    </div>
                <?php endif; ?>

                <!-- INPUT SCANARE -->
                <form method="POST" id="scanForm">
                    <div class="scan-input-wrapper">
                        <input type="text" 
                               name="cod_scanat" 
                               id="scanInput"
                               class="scan-input" 
                               placeholder="<?php echo isset($_SESSION['cititor_activ']) ? 'SCANEAZĂ CARTEA...' : 'SCANEAZĂ CARNETUL...'; ?>"
                               autocomplete="off"
                               autofocus>
                    </div>
                </form>

                <!-- PREVIEW CARTE -->
                <?php if ($carte_scanata): ?>
                    <div class="carte-preview">
                        <h4>📕 Detalii Carte Scanată</h4>
                        <div class="carte-detail">
                            <span class="carte-label">Titlu:</span>
                            <span class="carte-value"><strong><?php echo htmlspecialchars($carte_scanata['titlu']); ?></strong></span>
                        </div>
                        <div class="carte-detail">
                            <span class="carte-label">Autor:</span>
                            <span class="carte-value"><?php echo htmlspecialchars($carte_scanata['autor'] ?? '-'); ?></span>
                        </div>
                        <div class="carte-detail">
                            <span class="carte-label">ISBN:</span>
                            <span class="carte-value"><?php echo htmlspecialchars($carte_scanata['isbn'] ?? '-'); ?></span>
                        </div>
                        <div class="carte-detail">
                            <span class="carte-label">Locație:</span>
                            <span class="carte-value"><?php echo htmlspecialchars($carte_scanata['locatie_completa'] ?? '-'); ?></span>
                        </div>
                        <div class="carte-detail">
                            <span class="carte-label">Secțiune:</span>
                            <span class="carte-value"><?php echo htmlspecialchars($carte_scanata['sectiune'] ?? '-'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ISTORIC CITITOR -->
                <?php if (isset($_SESSION['cititor_activ']) && count($istoric_cititor) > 0): ?>
                    <div style="margin-top: 30px;">
                        <h3 style="color: #667eea; margin-bottom: 15px;">📚 Istoric Împrumuturi Cititor</h3>
                        <?php foreach ($istoric_cititor as $item): ?>
                            <div class="istoric-item <?php echo $item['status']; ?>">
                                <div class="istoric-title"><?php echo htmlspecialchars($item['titlu']); ?></div>
                                <div class="istoric-meta">
                                    <span><?php echo htmlspecialchars($item['autor'] ?? 'Autor necunoscut'); ?></span>
                                    <span>
                                        <?php if ($item['status'] === 'activ'): ?>
                                            <strong style="color: #28a745;">📤 Activ - <?php echo $item['zile']; ?> zile</strong>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">📥 Returnat - <?php echo $item['zile']; ?> zile</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SIDEBAR -->
            <div class="sidebar">
                <!-- ACTIVITATE RECENTĂ -->
                <div class="sidebar-card">
                    <h3>🕒 Activitate Recentă</h3>
                    <?php if (count($activitate_recenta) > 0): ?>
                        <?php foreach ($activitate_recenta as $act): ?>
                            <div class="activitate-item">
                                <div class="activitate-text">
                                    <div class="activitate-nume">
                                        <?php echo htmlspecialchars($act['nume'] . ' ' . $act['prenume']); ?>
                                    </div>
                                    <div class="activitate-carte">
                                        <?php echo htmlspecialchars(mb_substr($act['titlu'], 0, 35)); ?><?php echo mb_strlen($act['titlu']) > 35 ? '...' : ''; ?>
                                    </div>
                                </div>
                                <div class="activitate-badge <?php echo $act['status'] === 'activ' ? 'badge-imprumut' : 'badge-returnare'; ?>">
                                    <?php echo $act['status'] === 'activ' ? '📤 Împrumut' : '📥 Returnare'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">Nu există activitate recentă</div>
                    <?php endif; ?>
                </div>

                <!-- LINK-URI RAPIDE -->
                <div class="sidebar-card">
                    <h3>🔗 Link-uri Rapide</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="imprumuturi.php" class="btn btn-reports" style="text-align: center; display: block;">
                            📖 Vezi Toate Împrumuturile
                        </a>
                        <a href="status_vizari.php" class="btn" style="background: #28a745; color: white; text-align: center; display: block; text-decoration: none;">
                            ✅ Status Vizări Permise
                        </a>
                        <a href="lista_nevizati.php" class="btn" style="background: #ffc107; color: #333; text-align: center; display: block; text-decoration: none;">
                            ⚠️ Permise Nevizate
                        </a>
                    </div>
                </div>
            </div>
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