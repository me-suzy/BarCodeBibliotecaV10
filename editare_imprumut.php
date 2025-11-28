<?php
// editare_imprumut.php - Editare împrumut
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$mesaj = '';
$tip_mesaj = '';

// Verifică dacă avem ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $base_url = 'http://' . $_SERVER['HTTP_HOST'];
    ?>
    <!DOCTYPE html>
    <html lang="ro">
    <head>
        <meta charset="UTF-8">
        <title>Eroare</title>
        <style>
            body { 
                font-family: 'Segoe UI', Arial; 
                text-align: center; 
                padding: 80px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 15px;
                max-width: 500px;
                margin: 0 auto;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            h1 { color: #dc3545; margin-bottom: 20px; }
            p { color: #666; margin-bottom: 25px; }
            a { 
                display: inline-block;
                padding: 12px 30px; 
                background: #667eea; 
                color: white; 
                text-decoration: none; 
                border-radius: 8px;
                font-weight: 600;
            }
            a:hover { background: #764ba2; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ ID împrumut lipsă</h1>
            <p>Nu ai selectat niciun împrumut pentru editare.</p>
            <a href="<?php echo $base_url; ?>/imprumuturi.php">📖 Înapoi la listă</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$id = (int)$_GET['id'];

// Obține datele împrumutului cu calcul întârziere
$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.titlu as carte_titlu,
        c.autor as carte_autor,
        c.cod_bare as carte_cod_bare,
        c.cota as carte_cota,
        c.data_adaugare as carte_data_adaugare,
        cit.nume as cititor_nume,
        cit.prenume as cititor_prenume,
        DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut,
        CASE 
            WHEN i.data_scadenta IS NOT NULL THEN GREATEST(0, DATEDIFF(CURDATE(), i.data_scadenta))
            ELSE GREATEST(0, DATEDIFF(NOW(), i.data_imprumut) - 14)
        END as zile_intarziere
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.id = ?
");
$stmt->execute([$id]);
$imprumut = $stmt->fetch();

// Verifică dacă cartea este întârziată (NERETURNATĂ)
$zile_imprumut = (int)($imprumut['zile_imprumut'] ?? 0);
$zile_intarziere = (int)($imprumut['zile_intarziere'] ?? 0);
$este_intarziata = ($zile_intarziere > 0) || ($zile_imprumut > 14);
if ($este_intarziata && $zile_intarziere <= 0) {
    $zile_intarziere = max(0, $zile_imprumut - 14);
}

// Verifică dacă codul de bare și cota par să vină din Aleph
// Logica: dacă codul de bare începe cu "000" sau are format Aleph (ex: 000030207-10)
// sau dacă cota are format Aleph (ex: DAB II-3878, II-3878), atunci probabil vine din Aleph
$cod_bare_din_aleph = false;
$cota_din_aleph = false;

if (!empty($imprumut['carte_cod_bare'])) {
    // Coduri Aleph de obicei încep cu "000" sau au format specific (ex: 000030207-10, 000029152-10)
    // Coduri manuale de obicei încep cu "BOOK" sau sunt mai scurte
    $cod_bare = $imprumut['carte_cod_bare'];
    if (preg_match('/^000\d+-?\d*$/', $cod_bare) || // Format: 000030207-10
        preg_match('/^\d{9,}-\d+$/', $cod_bare) || // Format: 9+ cifre - cifre
        (preg_match('/^\d{6,}/', $cod_bare) && strlen($cod_bare) >= 10)) { // 6+ cifre și lungime >= 10
        $cod_bare_din_aleph = true;
    }
    // Coduri care încep cu "BOOK" sau "C" sunt probabil manuale
    if (preg_match('/^(BOOK|C)\d+/i', $cod_bare)) {
        $cod_bare_din_aleph = false;
    }
}

if (!empty($imprumut['carte_cota'])) {
    // Cote Aleph de obicei au format specific:
    // - Format: DAB II-3878, II-3878, II-48419
    // Cote manuale au format:
    // - Format: 821.135.1 PET u (cu puncte și litere la sfârșit - 2-3 litere)
    $cota = $imprumut['carte_cota'];
    
    // Verifică dacă se termină cu 2-3 litere (ex: "PET u", "CRE a", "ELI m") → MANUALĂ, editabilă
    if (preg_match('/\s+[A-Z]{2,3}\s*[a-z]?\s*$/i', $cota)) {
        // Se termină cu spațiu + 2-3 litere → e manuală, NU e din Aleph
        $cota_din_aleph = false;
    } elseif (preg_match('/^[A-Z]{2,}\s+[A-Z]+\s*-\s*\d+/', $cota) || // Format: DAB II-3878
              preg_match('/^[A-Z]{2,}\s*-\s*\d+/', $cota) || // Format: II-3878, II-48419
              preg_match('/^[A-Z]{2,}\s+[IVX]+/', $cota)) { // Format: DAB II
        // Format Aleph → read-only
        $cota_din_aleph = true;
    } else {
        // Pentru alte formate, verifică dacă are format numeric cu puncte dar FĂRĂ litere la sfârșit
        // Dacă are doar format numeric (ex: "821.135.1" fără litere la sfârșit), e probabil din Aleph
        if (preg_match('/^\d+\.\d+\.\d+$/', $cota)) {
            $cota_din_aleph = true;
        } else {
            $cota_din_aleph = false;
        }
    }
}

if (!$imprumut) {
    die('Împrumutul nu a fost găsit');
}

// Procesare formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CITITORUL ȘI CARTEA NU SE POT SCHIMBA - folosim valorile originale
    $cod_cititor = $imprumut['cod_cititor'];
    $cod_carte = $imprumut['cod_carte'];
    
    // Doar acestea se pot edita
    $data_imprumut = trim($_POST['data_imprumut']);
    $data_returnare = trim($_POST['data_returnare']);
    $status = trim($_POST['status']);
    $titlu_nou = trim($_POST['titlu_carte'] ?? '');
    $autor_nou = trim($_POST['autor_carte'] ?? '');
    $cod_bare_nou = trim($_POST['cod_bare_carte'] ?? '');
    $cota_noua = trim($_POST['cota_carte'] ?? '');

    try {
        // Logică automată: Dacă status = "returnat" și nu ai dată, pune data curentă
        if ($status === 'returnat' && empty($data_returnare)) {
            $data_returnare = date('Y-m-d H:i:s');
        }
        
        // Logică automată: Dacă ai dată returnare, statusul devine automat "returnat"
        if (!empty($data_returnare)) {
            $status = 'returnat';
        }

        // Actualizează împrumutul
        $stmt = $pdo->prepare("
            UPDATE imprumuturi
            SET data_imprumut = ?, data_returnare = ?, status = ?
            WHERE id = ?
        ");

        // Convertește datele goale în NULL
        $data_returnare_null = empty($data_returnare) ? null : $data_returnare;

        $stmt->execute([$data_imprumut, $data_returnare_null, $status, $id]);

        // Verifică din nou dacă codul de bare și cota vin din Aleph (pentru a preveni modificarea)
        $cod_bare_din_aleph_post = false;
        $cota_din_aleph_post = false;
        
        if (!empty($imprumut['carte_cod_bare'])) {
            $cod_bare_check = $imprumut['carte_cod_bare'];
            if (preg_match('/^000\d+-?\d*$/', $cod_bare_check) ||
                preg_match('/^\d{9,}-\d+$/', $cod_bare_check) ||
                (preg_match('/^\d{6,}/', $cod_bare_check) && strlen($cod_bare_check) >= 10)) {
                $cod_bare_din_aleph_post = true;
            }
            if (preg_match('/^(BOOK|C)\d+/i', $cod_bare_check)) {
                $cod_bare_din_aleph_post = false;
            }
        }
        
        if (!empty($imprumut['carte_cota'])) {
            $cota_check = $imprumut['carte_cota'];
            
            // Verifică dacă se termină cu 2-3 litere (ex: "PET u", "CRE a") → MANUALĂ, editabilă
            if (preg_match('/\s+[A-Z]{2,3}\s*[a-z]?\s*$/i', $cota_check)) {
                $cota_din_aleph_post = false;
            } elseif (preg_match('/^[A-Z]{2,}\s+[A-Z]+\s*-\s*\d+/', $cota_check) ||
                      preg_match('/^[A-Z]{2,}\s*-\s*\d+/', $cota_check) ||
                      preg_match('/^[A-Z]{2,}\s+[IVX]+/', $cota_check)) {
                // Format Aleph → read-only
                $cota_din_aleph_post = true;
            } else {
                // Pentru alte formate, verifică dacă are format numeric cu puncte dar FĂRĂ litere la sfârșit
                if (preg_match('/^\d+\.\d+\.\d+$/', $cota_check)) {
                    $cota_din_aleph_post = true;
                } else {
                    $cota_din_aleph_post = false;
                }
            }
        }
        
        // Actualizează titlul, autorul, codul de bare și cota cărții în baza de date locală (NU în Aleph)
        // IMPORTANT: Nu actualizăm cod_bare sau cota dacă provin din Aleph
        if (!empty($titlu_nou) || !empty($autor_nou) || (!empty($cod_bare_nou) && !$cod_bare_din_aleph_post) || (!empty($cota_noua) && !$cota_din_aleph_post)) {
            $update_carte_fields = [];
            $update_carte_values = [];
            
            if (!empty($titlu_nou)) {
                $update_carte_fields[] = "titlu = ?";
                $update_carte_values[] = $titlu_nou;
            }
            
            if (!empty($autor_nou)) {
                $update_carte_fields[] = "autor = ?";
                $update_carte_values[] = $autor_nou;
            }
            
            // Actualizează cod_bare DOAR dacă NU provine din Aleph
            if (!empty($cod_bare_nou) && !$cod_bare_din_aleph_post) {
                $update_carte_fields[] = "cod_bare = ?";
                $update_carte_values[] = $cod_bare_nou;
            }
            
            // Actualizează cota DOAR dacă NU provine din Aleph
            if (!empty($cota_noua) && !$cota_din_aleph_post) {
                $update_carte_fields[] = "cota = ?";
                $update_carte_values[] = $cota_noua;
            }
            
            if (!empty($update_carte_fields)) {
                $update_carte_values[] = $cod_carte; // Pentru WHERE
                $stmt_carte = $pdo->prepare("
                    UPDATE carti
                    SET " . implode(", ", $update_carte_fields) . "
                    WHERE cod_bare = ?
                ");
                $stmt_carte->execute($update_carte_values);
                
                // Dacă s-a schimbat codul de bare (și nu provine din Aleph), actualizează și în tabelul imprumuturi
                if (!empty($cod_bare_nou) && $cod_bare_nou !== $cod_carte && !$cod_bare_din_aleph_post) {
                    $stmt_update_imprumut = $pdo->prepare("
                        UPDATE imprumuturi
                        SET cod_carte = ?
                        WHERE cod_carte = ?
                    ");
                    $stmt_update_imprumut->execute([$cod_bare_nou, $cod_carte]);
                }
            }
        }
        
        // Mesaj de avertizare dacă s-a încercat să se modifice câmpuri din Aleph
        if (($cod_bare_din_aleph_post && !empty($cod_bare_nou) && $cod_bare_nou !== $imprumut['carte_cod_bare']) ||
            ($cota_din_aleph_post && !empty($cota_noua) && $cota_noua !== $imprumut['carte_cota'])) {
            $mesaj .= "<br>⚠️ <strong>Notă:</strong> Codul de bare sau cota provin din Aleph și nu pot fi modificate.";
        }

        $mesaj = "✅ Împrumutul a fost actualizat cu succes!";
        if (!empty($titlu_nou) || !empty($autor_nou) || !empty($cod_bare_nou) || !empty($cota_noua)) {
            $mesaj .= "<br>📚 Informațiile cărții au fost actualizate local (nu în Aleph).";
        }
        $tip_mesaj = "success";

        // Reîncarcă datele cu recalculare întârziere
        $stmt = $pdo->prepare("
            SELECT
                i.*,
                c.titlu as carte_titlu,
                c.autor as carte_autor,
                c.cod_bare as carte_cod_bare,
                c.cota as carte_cota,
                c.data_adaugare as carte_data_adaugare,
                cit.nume as cititor_nume,
                cit.prenume as cititor_prenume,
                DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut,
                CASE 
                    WHEN i.data_scadenta IS NOT NULL THEN GREATEST(0, DATEDIFF(CURDATE(), i.data_scadenta))
                    ELSE GREATEST(0, DATEDIFF(NOW(), i.data_imprumut) - 14)
                END as zile_intarziere
            FROM imprumuturi i
            JOIN carti c ON i.cod_carte = c.cod_bare
            JOIN cititori cit ON i.cod_cititor = cit.cod_bare
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $imprumut = $stmt->fetch();
        
        // Recalculează flag-ul pentru întârziere după actualizare
        $zile_imprumut = (int)($imprumut['zile_imprumut'] ?? 0);
        $zile_intarziere = (int)($imprumut['zile_intarziere'] ?? 0);
        $este_intarziata = ($zile_intarziere > 0) || ($zile_imprumut > 14);
        if ($este_intarziata && $zile_intarziere <= 0) {
            $zile_intarziere = max(0, $zile_imprumut - 14);
        }
        
        // Recalculează flag-urile pentru Aleph după actualizare
        $cod_bare_din_aleph = false;
        $cota_din_aleph = false;
        
        if (!empty($imprumut['carte_cod_bare'])) {
            $cod_bare = $imprumut['carte_cod_bare'];
            if (preg_match('/^000\d+-?\d*$/', $cod_bare) ||
                preg_match('/^\d{9,}-\d+$/', $cod_bare) ||
                (preg_match('/^\d{6,}/', $cod_bare) && strlen($cod_bare) >= 10)) {
                $cod_bare_din_aleph = true;
            }
            if (preg_match('/^(BOOK|C)\d+/i', $cod_bare)) {
                $cod_bare_din_aleph = false;
            }
        }
        
        if (!empty($imprumut['carte_cota'])) {
            $cota = $imprumut['carte_cota'];
            
            // Verifică dacă se termină cu 2-3 litere (ex: "PET u", "CRE a", "ELI m") → MANUALĂ, editabilă
            if (preg_match('/\s+[A-Z]{2,3}\s*[a-z]?\s*$/i', $cota)) {
                // Se termină cu spațiu + 2-3 litere → e manuală, NU e din Aleph
                $cota_din_aleph = false;
            } elseif (preg_match('/^[A-Z]{2,}\s+[A-Z]+\s*-\s*\d+/', $cota) ||
                      preg_match('/^[A-Z]{2,}\s*-\s*\d+/', $cota) ||
                      preg_match('/^[A-Z]{2,}\s+[IVX]+/', $cota)) {
                // Format Aleph → read-only
                $cota_din_aleph = true;
            } else {
                // Pentru alte formate, verifică dacă are format numeric cu puncte dar FĂRĂ litere la sfârșit
                if (preg_match('/^\d+\.\d+\.\d+$/', $cota)) {
                    $cota_din_aleph = true;
                } else {
                    $cota_din_aleph = false;
                }
            }
        }

    } catch (PDOException $e) {
        $mesaj = "❌ Eroare: " . $e->getMessage();
        $tip_mesaj = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editare Împrumut - Sistem Bibliotecă</title>
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
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        h1 {
            color: #667eea;
            margin-bottom: 30px;
            font-size: 2.2em;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1em;
        }

        select, input[type="datetime-local"], input[type="date"], input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 15px;
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

        .back-link, .home-link {
            display: inline-block;
            margin-top: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .home-link {
            background: #28a745;
            margin-right: 10px;
        }

        .home-link:hover {
            background: #218838;
        }

        .back-link {
            background: #667eea;
            color: white;
        }

        .back-link:hover {
            background: #764ba2;
        }

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .info-box ul {
            margin-left: 20px;
            color: #856404;
        }

        .fixed-info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid #667eea;
        }

        .fixed-info-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .fixed-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .fixed-info-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #28a745;
        }

        .fixed-info-item .label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .fixed-info-item .value {
            font-size: 1.05em;
            font-weight: 600;
            color: #333;
        }

        .editable-section {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #28a745;
        }

        .editable-section h3 {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📖 Editare Împrumut</h1>

        <div class="info-box">
            <h3>ℹ️ Informații importante</h3>
            <ul>
                <li><strong>Cititorul NU poate fi modificat</strong> - este fix pentru acest împrumut</li>
                <li>Poți edita: data împrumutului, data returnării, statusul</li>
                <li>Poți modifica informațiile cărții: titlu, autor, cod de bare, cotă (doar local, nu în Aleph)</li>
                <li><strong>Modificările la carte</strong> se aplică doar local în baza de date, nu în Aleph</li>
                <li>Statusul se actualizează automat când adaugi dată returnare</li>
            </ul>
        </div>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <?php if ($este_intarziata && empty($imprumut['data_returnare'])): ?>
            <div class="alert alert-danger" style="background: #dc3545; color: white; border: 2px solid #a71d2a; font-weight: bold; font-size: 1.1em; padding: 20px; text-align: center;">
                <div style="font-size: 1.3em; margin-bottom: 10px;">⚠️ CARTE NERETURNATĂ</div>
                <div style="font-size: 1em;">
                    Cartea este întârziată cu <strong><?php echo $zile_intarziere; ?> <?php echo $zile_intarziere == 1 ? 'zi' : 'zile'; ?></strong>
                    <?php if ($zile_imprumut > 0): ?>
                        <br><span style="font-size: 0.9em; opacity: 0.9;">(împrumutată acum <?php echo $zile_imprumut; ?> <?php echo $zile_imprumut == 1 ? 'zi' : 'zile'; ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Secțiune FIXĂ - cititor și carte -->
        <div class="fixed-info-section">
            <h3>🔒 Informații fixe (nu pot fi modificate)</h3>
            <div class="fixed-info-grid">
                <div class="fixed-info-item">
                    <div class="label">👤 Cititor</div>
                    <div class="value">
                        <?php echo htmlspecialchars($imprumut['cititor_nume'] . ' ' . $imprumut['cititor_prenume']); ?>
                        <br>
                        <span style="font-size: 0.9em; color: #667eea;">
                            <?php echo htmlspecialchars($imprumut['cod_cititor']); ?>
                        </span>
                    </div>
                </div>
                <div class="fixed-info-item">
                    <div class="label">📕 Carte</div>
                    <div class="value">
                        <?php echo htmlspecialchars($imprumut['carte_titlu']); ?>
                        <br>
                        <span style="font-size: 0.85em; color: #666;">
                            <?php echo htmlspecialchars($imprumut['carte_autor'] ?: 'Autor necunoscut'); ?>
                        </span>
                        <br>
                        <span style="font-size: 0.9em; color: #667eea;">
                            Cod bare: <?php echo htmlspecialchars($imprumut['cod_carte']); ?>
                        </span>
                        <?php if (!empty($imprumut['carte_cota'])): ?>
                            <br>
                            <span style="font-size: 0.9em; color: #28a745;">
                                Cotă: <?php echo htmlspecialchars($imprumut['carte_cota']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secțiune EDITABILĂ - date și status -->
        <div class="editable-section">
            <h3>✏️ Câmpuri editabile</h3>
            
            <form method="POST" id="imprumutForm">
                <div class="form-group">
                    <label>Data împrumut <span class="required">*</span></label>
                    <input type="datetime-local"
                           name="data_imprumut"
                           value="<?php echo date('Y-m-d\TH:i', strtotime($imprumut['data_imprumut'])); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Data returnare (opțional)</label>
                    <input type="datetime-local"
                           name="data_returnare"
                           value="<?php echo $imprumut['data_returnare'] ? date('Y-m-d\TH:i', strtotime($imprumut['data_returnare'])) : ''; ?>">
                    <small style="color: #6c757d; font-size: 0.9em;">💡 Lasă gol pentru împrumuturi active</small>
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="activ" <?php echo $imprumut['status'] == 'activ' ? 'selected' : ''; ?>>
                            📤 Activ (împrumutată)
                        </option>
                        <option value="returnat" <?php echo $imprumut['status'] == 'returnat' ? 'selected' : ''; ?>>
                            📥 Returnată
                        </option>
                    </select>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 15px; font-size: 1.1em;">📚 Modifică informații carte (doar local)</h4>
                    <p style="font-size: 0.9em; color: #6c757d; margin-bottom: 15px;">
                        Modificările se aplică doar în baza de date locală, nu în Aleph. Util pentru cazurile când datele lipsesc din Aleph.
                    </p>
                    
                    <div class="form-group">
                        <label>Modifică Titlu</label>
                        <input type="text"
                               name="titlu_carte"
                               value="<?php echo htmlspecialchars($imprumut['carte_titlu'], ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Titlul cărții">
                    </div>

                    <div class="form-group">
                        <label>Modifică Autor</label>
                        <input type="text"
                               name="autor_carte"
                               value="<?php echo htmlspecialchars($imprumut['carte_autor'] ?: '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Autorul cărții">
                    </div>

                    <div class="form-group">
                        <label>Modifică Cod de bare
                            <?php if ($cod_bare_din_aleph): ?>
                                <span style="color: #6c757d; font-size: 0.85em; font-weight: normal;">(din Aleph - read-only)</span>
                            <?php endif; ?>
                        </label>
                        <input type="text"
                               name="cod_bare_carte"
                               value="<?php echo htmlspecialchars($imprumut['carte_cod_bare'] ?: $imprumut['cod_carte'], ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Codul de bare al cărții"
                               <?php echo $cod_bare_din_aleph ? 'readonly style="background: #e9ecef; cursor: not-allowed;"' : ''; ?>>
                        <?php if ($cod_bare_din_aleph): ?>
                            <small style="color: #856404; font-size: 0.85em;">⚠️ Codul de bare provine din Aleph și nu poate fi modificat</small>
                        <?php else: ?>
                            <small style="color: #6c757d; font-size: 0.85em;">💡 Util când codul de bare lipsește sau este greșit</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Modifică Cotă
                            <?php if ($cota_din_aleph): ?>
                                <span style="color: #6c757d; font-size: 0.85em; font-weight: normal;">(din Aleph - read-only)</span>
                            <?php endif; ?>
                        </label>
                        <input type="text"
                               name="cota_carte"
                               value="<?php echo htmlspecialchars($imprumut['carte_cota'] ?: '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Cota cărții"
                               <?php echo $cota_din_aleph ? 'readonly style="background: #e9ecef; cursor: not-allowed;"' : ''; ?>>
                        <?php if ($cota_din_aleph): ?>
                            <small style="color: #856404; font-size: 0.85em;">⚠️ Cota provine din Aleph și nu poate fi modificată</small>
                        <?php else: ?>
                            <small style="color: #6c757d; font-size: 0.85em;">💡 Util când cota lipsește sau este greșită</small>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit">💾 Salvează modificările</button>
            </form>
        </div>

        <a href="index.php" class="home-link">🏠 Acasă</a>
        <a href="imprumuturi.php" class="back-link">← Înapoi la lista împrumuturi</a>
    </div>

    <script>
        // LOGICĂ: Când schimbi statusul manual
        document.querySelector('select[name="status"]').addEventListener('change', function() {
            const dataReturnareInput = document.querySelector('input[name="data_returnare"]');
            
            // Dacă selectezi "returnat" și nu ai dată, completează automat cu data curentă
            if (this.value === 'returnat' && !dataReturnareInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                dataReturnareInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                
                alert('ℹ️ Data returnării a fost completată automat cu data curentă.\nPoți să o modifici dacă vrei.');
            }
            
            // Dacă selectezi "activ", șterge data returnării
            if (this.value === 'activ') {
                dataReturnareInput.value = '';
            }
        });

        // Când modifici data returnării manual
        document.querySelector('input[name="data_returnare"]').addEventListener('input', function() {
            const statusSelect = document.querySelector('select[name="status"]');
            
            // Doar sugerează, nu forța!
            if (this.value && statusSelect.value === 'activ') {
                if (confirm('💡 Ai completat data returnării.\n\nVrei să schimbi automat statusul în "Returnat"?')) {
                    statusSelect.value = 'returnat';
                }
            }
        });
    </script>
</body>
</html>