<?php
/**
 * Pagină pentru cereri de cărți din depozit
 * Afișează cererile active și permite marcarea ca preluate/livrate
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Verifică dacă câmpul status_depozit există, dacă nu, îl adaugă
try {
    $pdo->exec("ALTER TABLE imprumuturi ADD COLUMN IF NOT EXISTS status_depozit ENUM('nu', 'cerere', 'preluata', 'livrata') DEFAULT 'nu'");
} catch (PDOException $e) {
    // Câmpul există deja sau altă eroare - continuă
}

// Procesează acțiuni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actiune'])) {
    $actiune = $_POST['actiune'];
    $imprumut_id = isset($_POST['imprumut_id']) ? (int)$_POST['imprumut_id'] : 0;
    
    if ($imprumut_id > 0) {
        if ($actiune === 'preluata') {
            $stmt = $pdo->prepare("UPDATE imprumuturi SET status_depozit = 'preluata' WHERE id = ?");
            $stmt->execute([$imprumut_id]);
        } elseif ($actiune === 'livrata') {
            $stmt = $pdo->prepare("UPDATE imprumuturi SET status_depozit = 'livrata' WHERE id = ?");
            $stmt->execute([$imprumut_id]);
        }
    }
    
    header('Location: cerere-carti-depozit.php');
    exit;
}

$query = "
    SELECT 
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        i.status_depozit,
        c.titlu,
        c.autor,
        c.cod_bare,
        c.cota,
        cit.nume,
        cit.prenume,
        cit.telefon,
        cit.email,
        TIMESTAMPDIFF(MINUTE, i.data_imprumut, NOW()) as minute_asteptare
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.data_returnare IS NULL 
    AND i.status_depozit IN ('cerere', 'preluata')
    AND i.status_depozit != 'livrata'
    ORDER BY 
        CASE i.status_depozit 
            WHEN 'cerere' THEN 1 
            WHEN 'preluata' THEN 2 
            ELSE 3 
        END,
        i.data_imprumut ASC
";

$stmt = $pdo->query($query);
$cereri = $stmt->fetchAll(PDO::FETCH_ASSOC);

$numar_cereri_noi = count(array_filter($cereri, function($c) { return $c['status_depozit'] === 'cerere'; }));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Cereri Cărți Depozit</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
        }

        .header h1 {
            color: #ff6b35;
            font-size: 3em;
            margin-bottom: 10px;
        }

        .alert-badge {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.2em;
            font-weight: bold;
            margin: 10px 0;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .stat-card h3 {
            font-size: 2.5em;
            color: #ff6b35;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 1.1em;
        }

        .cereri-list {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .cerere-item {
            background: #fff3cd;
            border-left: 6px solid #ff6b35;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .cerere-item.noua {
            background: #ffe6e6;
            border-left-color: #dc3545;
            animation: flash 2s infinite;
        }

        @keyframes flash {
            0%, 100% { box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.5); }
        }

        .cerere-item.preluata {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }

        .cerere-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .cerere-header h3 {
            color: #333;
            font-size: 1.5em;
        }

        .cerere-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .status-cerere {
            background: #dc3545;
            color: white;
        }

        .status-preluata {
            background: #17a2b8;
            color: white;
        }

        .cerere-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .info-item {
            padding: 10px;
            background: rgba(255,255,255,0.7);
            border-radius: 5px;
        }

        .info-item strong {
            color: #ff6b35;
            display: block;
            margin-bottom: 5px;
        }

        .cerere-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-preluata {
            background: #17a2b8;
            color: white;
        }

        .btn-livrata {
            background: #28a745;
            color: white;
        }

        .home-btn {
            background: #28a745;
            color: white;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-istoric {
            background: #17a2b8;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h2 {
            font-size: 2em;
            margin-bottom: 10px;
            color: #ff6b35;
        }

        .sound-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: bold;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <?php if ($numar_cereri_noi > 0): ?>
    <div class="sound-indicator">
        🔊 ALERTĂ: <?php echo $numar_cereri_noi; ?> cereri noi!
    </div>
    <?php endif; ?>

    <div class="container">
        <div class="header">
            <h1>📦 Cereri Cărți Depozit</h1>
            <?php if ($numar_cereri_noi > 0): ?>
            <div class="alert-badge">
                ⚠️ <?php echo $numar_cereri_noi; ?> CERERI NOI!
            </div>
            <?php endif; ?>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3><?php echo count($cereri); ?></h3>
                <p>Total Cereri</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $numar_cereri_noi; ?></h3>
                <p>Cereri Noi</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($cereri, function($c) { return $c['status_depozit'] === 'preluata'; })); ?></h3>
                <p>Preluate</p>
            </div>
        </div>

        <div class="cereri-list">
            <?php if (empty($cereri)): ?>
            <div class="empty-state">
                <h2>✅ Nu există cereri active</h2>
                <p>Toate cererile au fost procesate.</p>
            </div>
            <?php else: ?>
            <?php foreach ($cereri as $cerere): ?>
            <div class="cerere-item <?php echo $cerere['status_depozit'] === 'cerere' ? 'noua' : 'preluata'; ?>">
                <div class="cerere-header">
                    <h3>📚 <?php echo htmlspecialchars($cerere['titlu']); ?></h3>
                    <span class="cerere-status <?php echo $cerere['status_depozit'] === 'cerere' ? 'status-cerere' : 'status-preluata'; ?>">
                        <?php 
                        echo $cerere['status_depozit'] === 'cerere' ? '🆕 CERERE NOUĂ' : '📦 PRELUATĂ';
                        ?>
                    </span>
                </div>
                
                <div class="cerere-info">
                    <div class="info-item">
                        <strong>👤 Cititor:</strong>
                        <?php echo htmlspecialchars($cerere['nume'] . ' ' . $cerere['prenume']); ?>
                    </div>
                    <div class="info-item">
                        <strong>📖 Autor:</strong>
                        <?php echo htmlspecialchars($cerere['autor'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <strong>🔖 Cotă:</strong>
                        <?php echo htmlspecialchars($cerere['cota'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <strong>📍 Cod:</strong>
                        <?php echo htmlspecialchars($cerere['cod_bare']); ?>
                    </div>
                    <?php if (!empty($cerere['telefon'])): ?>
                    <div class="info-item">
                        <strong>📞 Telefon:</strong>
                        <a href="tel:<?php echo htmlspecialchars($cerere['telefon']); ?>">
                            <?php echo htmlspecialchars($cerere['telefon']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <strong>⏰ Așteaptă de:</strong>
                        <?php 
                        $minute = (int)$cerere['minute_asteptare'];
                        if ($minute < 60) {
                            echo $minute . ' minute';
                        } else {
                            $ore = floor($minute / 60);
                            $min_ramase = $minute % 60;
                            echo $ore . 'h ' . $min_ramase . 'm';
                        }
                        ?>
                    </div>
                </div>

                <div class="cerere-actions">
                    <?php if ($cerere['status_depozit'] === 'cerere'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="imprumut_id" value="<?php echo $cerere['id']; ?>">
                        <input type="hidden" name="actiune" value="preluata">
                        <button type="submit" class="btn btn-preluata">✅ Marchează ca Preluată</button>
                    </form>
                    <?php elseif ($cerere['status_depozit'] === 'preluata'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="imprumut_id" value="<?php echo $cerere['id']; ?>">
                        <input type="hidden" name="actiune" value="livrata">
                        <button type="submit" class="btn btn-livrata">✅ Marchează ca Livrată</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="index.php" class="btn home-btn">🏠 Acasă</a>
                <a href="imprumuturi.php" class="btn btn-back">← Înapoi la Împrumuturi</a>
                <a href="istoric-depozit.php" class="btn btn-istoric">📋 Istoric</a>
            </div>
        </div>
    </div>

    <script>
        const numarCereriNoi = <?php echo $numar_cereri_noi; ?>;
        let audioContext = null;
        let sunetRedat = false;

        function playBeepSound() {
            return new Promise((resolve) => {
                try {
                    if (!audioContext) {
                        audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    }
                    
                    if (audioContext.state === 'suspended') {
                        audioContext.resume();
                    }
                    
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.5, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                    
                    setTimeout(resolve, 500);
                } catch (e) {
                    console.log('Eroare beep:', e);
                    resolve();
                }
            });
        }

        function playVoiceFile() {
            return new Promise((resolve) => {
                const audio = new Audio('Voce_1.mp3');
                audio.volume = 1.0;
                audio.onended = resolve;
                audio.onerror = resolve;
                audio.play().catch(() => resolve());
            });
        }

        async function cicluAlerta() {
            if (numarCereriNoi === 0) return;
            
            // Beep
            await playBeepSound();
            
            // Pauză 2 secunde
            await new Promise(r => setTimeout(r, 2000));
            
            // Voce
            await playVoiceFile();
            
            // Repetă după 1 minut
            setTimeout(cicluAlerta, 60000);
        }

        // Pornește automat la încărcare
        if (numarCereriNoi > 0) {
            cicluAlerta();
        }

        // Auto-refresh la 30 secunde
        setTimeout(() => location.reload(), 30000);

        // Titlu alternant
        if (numarCereriNoi > 0) {
            let blink = true;
            setInterval(() => {
                document.title = blink ? '⚠️ ' + numarCereriNoi + ' CERERI NOI!' : '📦 Cereri Depozit';
                blink = !blink;
            }, 1000);
        }
    </script>
</body>
</html>