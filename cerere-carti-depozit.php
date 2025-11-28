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
    
    // Redirect pentru a evita re-submit
    header('Location: cerere-carti-depozit.php');
    exit;
}

// Obține cererile active de depozit (status_depozit = 'cerere' sau 'preluata', EXCLUZÂND 'livrata')
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
            animation: pulseBackground 3s infinite;
        }

        @keyframes pulseBackground {
            0%, 100% { background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); }
            50% { background: linear-gradient(135deg, #ff8c42 0%, #ffa64d 100%); }
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
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            color: #ff6b35;
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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
            animation: slideIn 0.5s ease-out;
            transition: all 0.3s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        .btn-preluata:hover {
            background: #138496;
        }

        .btn-livrata {
            background: #28a745;
            color: white;
        }

        .btn-livrata:hover {
            background: #218838;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            margin-top: 20px;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .home-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .home-btn:hover {
            background: #218838;
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

        .timer {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
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
            animation: pulse 1s infinite;
        }
    </style>
</head>
<body>
    <?php if ($numar_cereri_noi > 0): ?>
    <div class="sound-indicator" id="soundIndicator">
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
                        echo $cerere['status_depozit'] === 'cerere' ? '🆕 CERERE NOUĂ' : 
                             ($cerere['status_depozit'] === 'preluata' ? '📦 PRELUATĂ' : '✅ LIVRATĂ');
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
            </div>
        </div>
    </div>

    <script>
        // Salvează numărul de cereri noi în sessionStorage pentru comparație
        const numarCereriNoi = <?php echo $numar_cereri_noi; ?>;
        const numarCereriNoiAnterior = parseInt(sessionStorage.getItem('numarCereriNoi') || '0');
        const timpUltimaCerere = parseInt(sessionStorage.getItem('timpUltimaCerere') || '0');
        const timpCurent = Date.now();
        
        // Verifică dacă a apărut o cerere nouă (numărul a crescut)
        const cerereNouaApare = numarCereriNoi > numarCereriNoiAnterior;
        
        // Salvează starea actuală
        sessionStorage.setItem('numarCereriNoi', numarCereriNoi.toString());
        
        // Dacă apare o cerere nouă, salvează timpul
        if (cerereNouaApare) {
            sessionStorage.setItem('timpUltimaCerere', timpCurent.toString());
        }
        
        // Calculează cât timp a trecut de la ultima cerere nouă
        const timpDeLaUltimaCerere = timpCurent - timpUltimaCerere;
        const timpRamasPanaLaFocus = 15000 - timpDeLaUltimaCerere; // 15 secunde = 15000ms
        
        // Funcție pentru a aduce pagina în prim plan
        function aduceInPrimPlan() {
            // Forțează focus-ul pe fereastră
            window.focus();
            
            // Încearcă să deschidă în fullscreen (dacă este permis)
            const elem = document.documentElement;
            if (elem.requestFullscreen) {
                elem.requestFullscreen().catch(err => {
                    console.log('Fullscreen nu este permis:', err);
                });
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
            
            // NU mai redăm sunet aici - va fi redat la fiecare 20 secunde
            
            // Actualizează titlul pentru a atrage atenția
            document.title = '⚠️ ' + numarCereriNoi + ' CERERI NOI - Depozit';
        }
        
        // Variabilă globală pentru AudioContext (pentru a evita crearea multiplă)
        let audioContext = null;
        
        // Funcție pentru a obține sau crea AudioContext
        function getAudioContext() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            return audioContext;
        }
        
        // Funcție pentru sunet de alertă (mai lung și mai strident)
        function playAlertSound() {
            try {
                const ctx = getAudioContext();
                
                // Resuspendă contextul dacă este suspendat (pentru browser-uri care blochează audio)
                if (ctx.state === 'suspended') {
                    ctx.resume().then(() => {
                        redaSunetul(ctx);
                    }).catch(err => {
                        console.log('Eroare la resumarea audio context:', err);
                    });
                } else {
                    redaSunetul(ctx);
                }
            } catch (e) {
                console.log('Nu se poate reda sunet:', e);
            }
        }
        
        // Funcție helper pentru a reda efectiv sunetul
        function redaSunetul(ctx) {
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);
            
            // Frecvență mai înaltă pentru sunet mai strident
            oscillator.frequency.value = 1200;
            oscillator.type = 'sine';
            
            // Sunet mai lung (1.2 secunde) și mai puternic
            gainNode.gain.setValueAtTime(0.5, ctx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.6);
            gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);
            
            oscillator.start(ctx.currentTime);
            oscillator.stop(ctx.currentTime + 1.2);
            
            // După 2 secunde de la începutul sunetului, redă fișierul audio
            setTimeout(function() {
                playVoiceFile();
            }, 2000); // 2 secunde = 2000ms
        }
        
        // Funcție pentru a reda fișierul audio Voce_1.mp3
        function playVoiceFile() {
            try {
                const audio = new Audio('Voce_1.mp3');
                audio.volume = 1.0; // Volum maxim
                audio.play().catch(err => {
                    console.log('Eroare la redarea fișierului audio:', err);
                });
            } catch (e) {
                console.log('Nu se poate reda fișierul audio:', e);
            }
        }
        
        // Dacă a apărut o cerere nouă și au trecut deja 15 secunde, aduce imediat în prim plan
        if (cerereNouaApare && timpRamasPanaLaFocus <= 0) {
            aduceInPrimPlan();
        }
        // Dacă a apărut o cerere nouă dar nu au trecut 15 secunde, așteaptă
        else if (cerereNouaApare && timpRamasPanaLaFocus > 0) {
            setTimeout(function() {
                aduceInPrimPlan();
            }, timpRamasPanaLaFocus);
        }
        // Dacă există cereri noi dar nu e o cerere nouă (deja există), verifică dacă au trecut 15 secunde
        else if (numarCereriNoi > 0 && timpRamasPanaLaFocus <= 0 && timpDeLaUltimaCerere > 0) {
            // Nu face nimic - deja a fost procesat
        }
        
        // Auto-refresh la fiecare 5 secunde pentru actualizare rapidă  5 secunde = 5000 , 1 minut = 60000
        setTimeout(function() {
            location.reload();
        }, 60000);   
        
        // Actualizează titlul pentru a atrage atenția (doar dacă există cereri noi)
        if (numarCereriNoi > 0) {
            let titleBlink = true;
            setInterval(function() {
                document.title = titleBlink ? 
                    '⚠️ ' + numarCereriNoi + ' CERERI NOI - Depozit' : 
                    '📦 Cereri Depozit';
                titleBlink = !titleBlink;
            }, 1000);
        } else {
            document.title = '📦 Cereri Depozit';
        }
        
        // Forțează focus-ul la încărcare (doar dacă nu există cereri noi sau dacă au trecut 15 secunde)
        if (numarCereriNoi === 0 || timpRamasPanaLaFocus <= 0) {
            window.focus();
        }
        
        // Redă sunet de alertă (doar dacă există cereri noi)
        if (numarCereriNoi > 0) {
            // Redă sunetul imediat la încărcarea paginii
            playAlertSound();
            
            // Apoi redă sunetul la fiecare 1 minut
            setInterval(function() {
                playAlertSound();
            }, 60000); // 1 minut = 60000ms
        }
    </script>
</body>
</html>

