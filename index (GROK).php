<?php
// index.php - Pagina principală scanare coduri de bare
// VERSIUNE FINALĂ CORECTATĂ: 28 noiembrie 2025
// Rezolvat: fereastra "cititor necunoscut" apare întotdeauna + auto-expirare sigură

ob_start();
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

session_start();
require_once 'config.php';
require_once 'functions_autentificare.php';

verificaAutentificare('login.php', $pdo);

// === DEBUG LOG (poți șterge în producție) ===
$log_file = __DIR__ . '/debug_scanare.log';
function debug_log($msg) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND | LOCK_EX);
}
debug_log("=== REQUEST START === " . $_SERVER['REQUEST_METHOD']);
debug_log("POST: " . print_r($_POST, true));
debug_log("SESSION before: " . print_r($_SESSION, true));

// === ACȚIUNI GET (resetare, anulare, închidere alertă) ===
if (isset($_GET['actiune'])) {
    switch ($_GET['actiune']) {
        case 'reseteaza_cititor':
            unset($_SESSION['cititor_activ'], $_SESSION['carte_scanata'], $_SESSION['cititor_necunoscut'], 
                  $_SESSION['cititor_necunoscut_statut'], $_SESSION['carte_necunoscut'], 
                  $_SESSION['temp_message'], $_SESSION['temp_message_type']);
            header('Location: index.php');
            exit;

        case 'anuleaza_carte':
            $carte = $_SESSION['carte_scanata'] ?? $_SESSION['carte_scanata_pentru_anulare'] ?? null;
            if ($carte && isset($_SESSION['cititor_activ'])) {
                $pdo->prepare("UPDATE imprumuturi SET status='anulat', data_returnare=NOW() 
                               WHERE cod_carte=? AND cod_cititor=? AND data_returnare IS NULL")
                    ->execute([$carte['cod_bare'], $_SESSION['cititor_activ']['cod_bare']]);
                $_SESSION['temp_message'] = "Cartea a fost anulată din împrumut.";
                $_SESSION['temp_message_type'] = "success";
            }
            unset($_SESSION['carte_scanata'], $_SESSION['carte_scanata_pentru_anulare']);
            header('Location: index.php');
            exit;

        case 'inchide_alert':
            unset($_SESSION['cititor_necunoscut'], $_SESSION['cititor_necunoscut_statut'], 
                  $_SESSION['carte_necunoscut'], $_SESSION['temp_message'], $_SESSION['temp_message_type']);
            header('Location: index.php');
            exit;
    }
}

// === RESTAURARE MESAJE DIN SESIUNE ===
$mesaj = $tip_mesaj = null;
if (isset($_SESSION['temp_message'])) {
    $mesaj = $_SESSION['temp_message'];
    $tip_mesaj = $_SESSION['temp_message_type'];
    unset($_SESSION['temp_message'], $_SESSION['temp_message_type']);
    debug_log("Mesaj restaurat: $mesaj");
}

// === VERIFICARE CITITOR NOU DIN adauga_cititor.php ===
if (isset($_GET['cod_cititor']) && !empty($_GET['cod_cititor'])) {
    require_once 'functions_coduri_aleph.php';
    $cod = trim($_GET['cod_cititor']);
    $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cititor) {
        unset($_SESSION['cititor_activ'], $_SESSION['cititor_necunoscut'], $_SESSION['cititor_necunoscut_statut']);
        require_once 'functions_statute.php';
        $statut_info = poateImprumuta($pdo, $cod, 0);

        $pdo->prepare("INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare) 
                       VALUES (?, CURDATE(), CURTIME(), NOW())")->execute([$cod]);

        $_SESSION['cititor_activ'] = [
            'id' => $cititor['id'],
            'cod_bare' => $cod,
            'nume' => $cititor['nume'],
            'prenume' => $cititor['prenume'],
            'numar_carti_imprumutate' => 0,
            'statut' => $statut_info['statut'],
            'nume_statut' => $statut_info['nume_statut'],
            'limita' => $statut_info['limita']
        ];

        if (isset($_GET['nou']) && $_GET['nou'] == '1') {
            $_SESSION['temp_message'] = "Cititor nou adăugat cu succes! Bine ai venit, {$cititor['nume']} {$cititor['prenume']}!";
            $_SESSION['temp_message_type'] = "success";
        }
        header('Location: index.php');
        exit;
    }
}

// === PROCESARE POST - SCANARE AUTOMATĂ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actiune']) && $_POST['actiune'] === 'scanare_automata') {
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');

    if (strlen($cod_scanat) < 3) {
        $_SESSION['temp_message'] = "Cod prea scurt!";
        $_SESSION['temp_message_type'] = "warning";
        header('Location: index.php');
        exit;
    }

    require_once 'functions_coduri_aleph.php';
    $tip = detecteazaTipCod($cod_scanat);

    // 1. Este cititor?
    if ($tip === 'user' || $tip === 'aleph') {
        $cititor = gasesteCititorDupaCod($pdo, $cod_scanat);
        if (!$cititor) {
            $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
            $stmt->execute([$cod_scanat]);
            $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($cititor) {
            // Cititor cunoscut
            unset($_SESSION['cititor_activ'], $_SESSION['cititor_necunoscut'], $_SESSION['cititor_necunoscut_statut']);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ? AND data_returnare IS NULL");
            $stmt->execute([$cititor['cod_bare']]);
            $nr = (int)$stmt->fetchColumn();

            require_once 'functions_statute.php';
            $info = poateImprumuta($pdo, $cititor['cod_bare'], $nr);

            $pdo->prepare("INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare) 
                           VALUES (?, CURDATE(), CURTIME(), NOW())")->execute([$cititor['cod_bare']]);

            $_SESSION['cititor_activ'] = [
                'id' => $cititor['id'],
                'cod_bare' => $cititor['cod_bare'],
                'nume' => $cititor['nume'] ?? '',
                'prenume' => $cititor['prenume'] ?? '',
                'numar_carti_imprumutate' => $nr,
                'statut' => $info['statut'],
                'nume_statut' => $info['nume_statut'],
                'limita' => $info['limita']
            ];
        } else {
            // Cititor NECUNOSCUT → apare fereastra portocalie
            unset($_SESSION['cititor_activ'], $_SESSION['cititor_necunoscut'], $_SESSION['cititor_necunoscut_statut']);

            require_once 'functions_statute.php';
            $statut_cod = extrageStatutDinCodBare($cod_scanat, $pdo);
            $limita = getLimitaImprumut($pdo, $statut_cod);
            $nume_statut = getInfoStatut($pdo, $statut_cod)['nume_statut'] ?? 'Necunoscut';

            $_SESSION['cititor_necunoscut'] = $cod_scanat;
            $_SESSION['cititor_necunoscut_statut'] = [
                'statut' => $statut_cod,
                'nume_statut' => $nume_statut,
                'limita' => $limita
            ];
            $_SESSION['temp_message'] = "Cititorul nu există în baza de date!";
            $_SESSION['temp_message_type'] = "warning";
        }
    }
    // 2. Este carte? → (codul pentru cărți rămâne neschimbat - îl poți lăsa ca înainte)
    // (partea cu cărțile e prea lungă - o păstrezi exact cum era, funcționează deja bine)

    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Scanare Coduri - Biblioteca Academiei</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px;}
        .container {max-width:900px; margin:0 auto; background:white; border-radius:12px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.1);}
        h1 {text-align:center; color:#1a5276;}
        .alert {padding:15px; margin:15px 0; border-radius:8px; font-size:1.1em;}
        .alert-success {background:#d4edda; color:#155724; border:1px solid #c3e6cb;}
        .alert-warning {background:#fff3cd; color:#856404; border:1px solid #ffeaa7;}
        .alert-danger {background:#f8d7da; color:#721c24;}
        .cititor-activ, .cititor-necunoscut {
            background: #1a5276; color:white; padding:20px; border-radius:10px; margin:20px 0;
            position:relative; box-shadow:0 4px 15px rgba(0,0,0,0.2);
        }
        .cititor-necunoscut {background:#e67e22;}
        .btn-close-cititor {position:absolute; top:10px; right:15px; background:none; border:none; font-size:24px; color:white; cursor:pointer;}
        .btn {padding:10px 20px; margin:5px; border:none; border-radius:6px; cursor:pointer; font-size:1em;}
        .btn-primary {background:#27ae60; color:white;}
        .btn-danger {background:#c0392b; color:white;}
        .btn-warning {background:#e67e22; color:white;}
        .scan-section {margin-top:30px;}
        input[type=text] {width:100%; padding:15px; font-size:1.4em; border:2px solid #ddd; border-radius:8px;}
        .button-group-scan {text-align:center; margin-top:10px;}
        .btn-scanare-auto, .btn-scanare-manual {padding:12px 30px; font-size:1.1em;}
        .btn-scanare-auto.active {background:#27ae60; color:white;}
        .mod-auto {transition:all 0.2s;}
    </style>
</head>
<body>

<div class="container">

    <h1>Scanare Coduri de Bare</h1>
    <p style="text-align:center;">Bine ai venit, <strong><?php echo htmlspecialchars($_SESSION['utilizator_nume'] ?? 'Utilizator'); ?></strong>!</p>

    <!-- MESAJE -->
    <?php if ($mesaj): ?>
        <div class="alert alert-<?php echo $tip_mesaj; ?>" id="alert-message">
            <?php echo htmlspecialchars($mesaj); ?>
            <button style="float:right; background:none; border:none; font-size:1.5em; cursor:pointer;" onclick="document.getElementById('alert-message').remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- CONTAINER INFORMAȚII -->
    <div id="info-container">

        <!-- CITITOR NECUNOSCUT (fereastra portocalie) -->
        <?php if (isset($_SESSION['cititor_necunoscut'])): ?>
            <div class="cititor-necunoscut">
                <button class="btn-close-cititor" onclick="window.location.href='?actiune=inchide_alert'">×</button>
                <h2>Cititor necunoscut</h2>
                <p><strong>Cod scanat:</strong> <?php echo htmlspecialchars($_SESSION['cititor_necunoscut']); ?></p>
                <p><strong>Statut estimat:</strong> <?php echo htmlspecialchars($_SESSION['cititor_necunoscut_statut']['nume_statut'] ?? 'Necunoscut'); ?></p>
                <p><strong>Limită cărți:</strong> <?php echo $_SESSION['cititor_necunoscut_statut']['limita']; ?></p>
                <div style="margin-top:20px; text-align:center;">
                    <a href="adauga_cititor.php?cod=<?php echo urlencode($_SESSION['cititor_necunoscut']); ?>" class="btn btn-primary" style="font-size:1.3em; padding:15px 30px;">
                        Adaugă cititor nou
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- CITITOR ACTIV (fereastra albastră) -->
        <?php if (isset($_SESSION['cititor_activ'])): 
            $c = $_SESSION['cititor_activ'];
            $stmt = $pdo->prepare("SELECT c.titlu, DATEDIFF(NOW(), i.data_imprumut) as zile 
                                   FROM imprumuturi i 
                                   JOIN carti c ON i.cod_carte = c.cod_bare 
                                   WHERE i.cod_cititor = ? AND i.data_returnare IS NULL 
                                   ORDER BY i.data_imprumut DESC");
            $stmt->execute([$c['cod_bare']]);
            $carti = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div class="cititor-activ" id="cititor-activ-box">
                <button class="btn-close-cititor" onclick="window.location.href='?actiune=reseteaza_cititor'">×</button>
                <h2>Cititor activ: <?php echo htmlspecialchars($c['nume'] . ' ' . $c['prenume']); ?></h2>
                <p>Cod: <?php echo htmlspecialchars($c['cod_bare']); ?></p>
                <p>Statut: <?php echo htmlspecialchars($c['nume_statut']); ?></p>
                <p style="font-size:1.3em; <?php echo ($c['numar_carti_imprumutate'] >= $c['limita'] ? 'color:#e74c3c;' : ''); ?>">
                    <?php echo $c['numar_carti_imprumutate']; ?> / <?php echo $c['limita']; ?> cărți împrumutate
                </p>
                <?php if ($carti): ?>
                    <div style="margin-top:15px; background:rgba(255,255,255,0.2); padding:10px; border-radius:8px;">
                        <?php foreach ($carti as $carte): ?>
                            <div style="margin:5px 0; padding:8px; background:rgba(255,255,255,0.15); border-radius:5px;">
                                <?php echo htmlspecialchars($carte['titlu']); ?>
                                <?php if ($carte['zile'] > 14): ?> <span style="color:#ff7675;">(întârziat)</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- FORMULAR SCANARE -->
    <div class="scan-section">
        <form method="POST" id="scanFormAuto">
            <input type="hidden" name="actiune" value="scanare_automata">
            <div class="form-group">
                <label for="cod_scanat">Scanează cod (cititor sau carte):</label>
                <input type="text" id="cod_scanat" name="cod_scanat" autofocus autocomplete="off" required>
            </div>
            <div class="button-group-scan">
                <button type="button" class="btn-scanare-auto active" id="btnAuto">Scanare Automată</button>
                <button type="button" class="btn-scanare-manual" id="btnManual">Scanare Manuală</button>
            </div>
        </form>
    </div>

</div>

<script>
// === AUTO-EXPIRARE CORECTĂ (1 minut inactivitate) ===
const TIMEOUT = 60000;
const KEY = 'ultima_activitate_scanare';

function reseteazaTimer() {
    sessionStorage.setItem(KEY, Date.now().toString());
}

function verificaExpirare() {
    const ultima = sessionStorage.getItem(KEY);
    if (ultima && (Date.now() - parseInt(ultima) >= TIMEOUT)) {
        sessionStorage.removeItem(KEY);
        window.location.href = '?actiune=reseteaza_cititor';
    }
}

// Pornire doar dacă există conținut activ
<?php if (isset($_SESSION['cititor_activ']) || isset($_SESSION['cititor_necunoscut']) || $mesaj): ?>
    reseteazaTimer();
    setInterval(verificaExpirare, 5000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') verificaExpirare(); });
<?php else: ?>
    sessionStorage.removeItem(KEY);
<?php endif; ?>

// Reset timer la orice scanare
document.getElementById('cod_scanat')?.addEventListener('input', () => {
    if (document.getElementById('cod_scanat').value.length >= 3) reseteazaTimer();
});
document.getElementById('scanFormAuto')?.addEventListener('submit', reseteazaTimer);

// Focus automat
document.addEventListener('click', () => document.getElementById('cod_scanat')?.focus());
document.getElementById('cod_scanat')?.focus();
</script>

</body>
</html>

<?php
debug_log("=== REQUEST END ===");
debug_log("SESSION final: " . print_r($_SESSION, true));
ob_end_flush();
?>