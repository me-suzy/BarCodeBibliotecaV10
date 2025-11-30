<?php
// adauga_cititor.php - Adaugă cititori noi în sistem
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_coduri_aleph.php';
require_once 'functions_statute.php';

// Pre-completare cod dacă vine din scanare
$cod_prestabilit = isset($_GET['cod']) ? strtoupper(trim($_GET['cod'])) : '';

// Variabile pentru păstrarea datelor la eroare
$form_data = [
    'cod_bare' => $cod_prestabilit,
    'nume' => '',
    'prenume' => '',
    'telefon' => '',
    'email' => '',
    'statut' => '14' // Statut implicit
];

// Detectează automat statutul din cod dacă există
$statut_detectat = null;
if (!empty($cod_prestabilit)) {
    $statut_detectat = extrageStatutDinCodBare($cod_prestabilit, $pdo);
    $form_data['statut'] = $statut_detectat;
}

// Obține toate statuturile din baza de date pentru dropdown
$statute_disponibile = [];
try {
    $stmt_statute = $pdo->query("SELECT cod_statut, nume_statut, limita_totala FROM statute_cititori ORDER BY cod_statut");
    $statute_disponibile = $stmt_statute->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Eroare obținere statute: " . $e->getMessage());
}

$mesaj = '';
$tip_mesaj = '';
$cod_duplicat = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Salvează toate datele
    $form_data = [
        'cod_bare' => strtoupper(trim($_POST['cod_bare'])),
        'nume' => trim($_POST['nume']),
        'prenume' => trim($_POST['prenume']),
        'telefon' => trim($_POST['telefon']),
        'email' => trim($_POST['email']),
        'statut' => trim($_POST['statut'] ?? '14') // Statut selectat manual sau implicit
    ];

    try {
        // Detectează tipul de cod și extrage informații
        $tip_cod = detecteazaTipCod($form_data['cod_bare']);
        
        // Dacă statutul nu a fost selectat manual, încearcă să-l detecteze automat
        if (empty($form_data['statut']) || $form_data['statut'] === '14') {
            $statut_detectat_auto = extrageStatutDinCodBare($form_data['cod_bare'], $pdo);
            if ($statut_detectat_auto) {
                $form_data['statut'] = $statut_detectat_auto;
            }
        }
        
        // Procesare upload imagine dacă există
        $imagine_path = null;
        if (isset($_FILES['imagine']) && $_FILES['imagine']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['imagine'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $nume_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $form_data['nume']);
                $prenume_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $form_data['prenume']);
                $cod_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $form_data['cod_bare']);
                $filename = $cod_curat . '_' . $nume_curat . '_' . $prenume_curat . '.' . $ext;
                $upload_path = 'imagini/' . $filename;
                
                if (!is_dir('imagini')) {
                    mkdir('imagini', 0755, true);
                }
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $imagine_path = $upload_path;
                } else {
                    $mesaj = "⚠️ Cititorul a fost adăugat, dar imaginea nu a putut fi încărcată!";
                    $tip_mesaj = "warning";
                }
            } else {
                $mesaj = "⚠️ Cititorul a fost adăugat, dar imaginea nu este validă! (Max 5MB, JPEG/PNG/GIF)";
                $tip_mesaj = "warning";
            }
        }
        
        // Inserează cititorul cu statut
        $stmt = $pdo->query("SHOW COLUMNS FROM cititori LIKE 'statut'");
        $are_statut = $stmt->rowCount() > 0;
        
        // Verifică dacă există câmpul imagine
        $stmt_img = $pdo->query("SHOW COLUMNS FROM cititori LIKE 'imagine'");
        $are_imagine = $stmt_img->rowCount() > 0;
        
        if ($are_statut && $are_imagine) {
            $stmt = $pdo->prepare("INSERT INTO cititori (cod_bare, statut, nume, prenume, telefon, email, imagine) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $form_data['cod_bare'],
                $form_data['statut'],
                $form_data['nume'],
                $form_data['prenume'],
                !empty($form_data['telefon']) ? $form_data['telefon'] : null,
                !empty($form_data['email']) ? $form_data['email'] : null,
                $imagine_path
            ]);
        } elseif ($are_statut) {
            $stmt = $pdo->prepare("INSERT INTO cititori (cod_bare, statut, nume, prenume, telefon, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $form_data['cod_bare'],
                $form_data['statut'],
                $form_data['nume'],
                $form_data['prenume'],
                !empty($form_data['telefon']) ? $form_data['telefon'] : null,
                !empty($form_data['email']) ? $form_data['email'] : null
            ]);
        } else {
            // Fallback la metoda veche dacă câmpul statut nu există încă
            if ($are_imagine) {
                $stmt = $pdo->prepare("INSERT INTO cititori (cod_bare, nume, prenume, telefon, email, imagine) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $form_data['cod_bare'],
                    $form_data['nume'],
                    $form_data['prenume'],
                    !empty($form_data['telefon']) ? $form_data['telefon'] : null,
                    !empty($form_data['email']) ? $form_data['email'] : null,
                    $imagine_path
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cititori (cod_bare, nume, prenume, telefon, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $form_data['cod_bare'],
                    $form_data['nume'],
                    $form_data['prenume'],
                    !empty($form_data['telefon']) ? $form_data['telefon'] : null,
                    !empty($form_data['email']) ? $form_data['email'] : null
                ]);
            }
        }

        // SUCCES - Redirecționează către index.php cu codul cititorului pentru auto-verificare
        header('Location: index.php?cod_cititor=' . urlencode($form_data['cod_bare']) . '&nou=1');
        exit;
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $mesaj = "❌ Codul de bare <strong>{$form_data['cod_bare']}</strong> există deja în baza de date!";
            $tip_mesaj = "danger";
            $cod_duplicat = true;
        } else {
            $mesaj = "❌ Eroare la salvare: " . $e->getMessage();
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
    <title>Adaugă Cititor - Sistem Bibliotecă</title>
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
            max-width: 600px;
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

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }

        select {
            font-size: 1.1em;
            padding: 14px;
            background-color: #fff;
            cursor: pointer;
            min-height: 50px;
        }

        select option {
            padding: 12px;
            font-size: 1em;
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
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .preview-card {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }

        .preview-card h4 {
            margin-bottom: 8px;
            color: #28a745;
            font-size: 1em;
        }

        .card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9em;
        }

        .card-item {
            display: flex;
            justify-content: space-between;
        }

        .card-label {
            font-weight: 600;
            color: #666;
        }

        .card-value {
            color: #333;
        }

        .error-field {
            border-color: #dc3545 !important;
            background: #f8d7da !important;
        }

        .error-message {
            color: #dc3545;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .edit-button-wrapper {
            margin-top: 10px;
        }

        .edit-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: background 0.3s;
        }

        .edit-button:hover {
            background: #764ba2;
        }

        .edit-button.cancel {
            background: #dc3545;
        }

        .edit-button.cancel:hover {
            background: #c82333;
        }

        .success-indicator {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .check-link {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }

        .check-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.1em;
        }

        .check-link a:hover {
            text-decoration: underline;
        }

        .scanned-code-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.1em;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .scanned-code-badge .code {
            font-size: 1.5em;
            margin-top: 5px;
            letter-spacing: 2px;
        }

        /* Upload imagine */
        .user-icon-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px dashed #667eea;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .user-icon-container:hover {
            border-color: #764ba2;
            background: #e9ecef;
            transform: scale(1.05);
        }

        .user-icon-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-icon-svg {
            width: 80px;
            height: 80px;
            fill: #667eea;
        }

        .upload-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .upload-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            min-width: 400px;
            max-width: 600px;
            z-index: 2001;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .drop-zone {
            border: 3px dashed #667eea;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .drop-zone:hover {
            border-color: #764ba2;
            background: #e9ecef;
        }

        .drop-zone.dragover {
            border-color: #28a745;
            background: #d4edda;
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .upload-buttons {
            display: none;
            gap: 10px;
        }

        .upload-buttons button {
            flex: 1;
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
        <h1>👤 Adaugă cititor nou</h1>

        <?php if (!empty($cod_prestabilit) && !$cod_duplicat): ?>
        <div class="scanned-code-badge">
            📟 Cod scanat detectat
            <div class="code"><?php echo htmlspecialchars($cod_prestabilit); ?></div>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>💡 Informații utile</h3>
            <ul style="margin-left: 20px;">
                <li>Codul de bare trebuie să fie unic (ex: USER001, 14016xxx, 12 cifre Aleph)</li>
                <li>Va fi printat pe carnetul de membru al cititorului</li>
                <li>Contactele sunt importante pentru notificări</li>
            </ul>
        </div>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
            
            <?php if ($cod_duplicat): ?>
                <div class="check-link">
                    <a href="cititori.php" target="_blank">🔍 Vezi lista completă de cititori pentru a verifica codurile existente</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" id="cititorForm" enctype="multipart/form-data">
            <input type="file" name="imagine" id="fileInput" accept="image/*" style="display: none;">
            <div class="form-group">
                <label>Cod de bare carnet <span class="required">*</span></label>
                <input type="text" 
                       name="cod_bare" 
                       id="cod_bare_input"
                       placeholder="USER003 sau 1200000010" 
                       value="<?php echo htmlspecialchars($form_data['cod_bare']); ?>"
                       required
                       class="<?php echo $cod_duplicat ? 'error-field' : ''; ?>"
                       <?php echo (!empty($cod_prestabilit) && !$cod_duplicat) ? 'readonly style="background:#e9ecef;"' : ''; ?>>
                
                <?php if (!empty($cod_prestabilit) && !$cod_duplicat): ?>
                    <div class="edit-button-wrapper">
                        <button type="button" 
                                id="btn_edit_cod" 
                                class="edit-button"
                                title="Click pentru a edita codul">
                            ✏️ EDIT
                        </button>
                    </div>
                <?php endif; ?>
                
                <small id="cod_info" style="display: block; margin-top: 5px; color: #666; font-size: 0.9em;">
                    💡 Formate acceptate: USER001 (testare), 14016xxx (Biblioteca Academiei) sau 12 cifre (Aleph)
                </small>
                
                <?php if (!empty($cod_prestabilit) && !$cod_duplicat): ?>
                    <small class="success-indicator">
                        ✅ Cod scanat: <?php echo htmlspecialchars($cod_prestabilit); ?>
                    </small>
                <?php endif; ?>
                
                <?php if ($cod_duplicat): ?>
                    <small class="error-message">
                        ⚠️ Acest cod există deja! Verifică lista de cititori sau folosește alt cod.
                    </small>
                <?php endif; ?>
            </div>
            
            <div class="form-group" id="statut_group">
                <label>
                    Statut cititor 
                    <?php if ($statut_detectat): ?>
                        <span style="color: #667eea; font-size: 0.9em;">
                            (Detectat automat: <?php 
                                $info_statut = getInfoStatut($pdo, $statut_detectat);
                                echo htmlspecialchars($info_statut ? $info_statut['nume_statut'] : 'Statut ' . $statut_detectat);
                            ?>)
                        </span>
                    <?php endif; ?>
                </label>
                <select name="statut" id="statut_select" size="1" style="font-size: 1.1em; padding: 14px;">
                    <?php foreach ($statute_disponibile as $statut): ?>
                        <option value="<?php echo htmlspecialchars($statut['cod_statut']); ?>"
                                <?php echo ($form_data['statut'] == $statut['cod_statut']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($statut['cod_statut']); ?> - <?php echo htmlspecialchars($statut['nume_statut']); ?> (Limita: <?php echo $statut['limita_totala']; ?> cărți)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="display: block; margin-top: 8px; color: #666; font-size: 0.95em; font-weight: 500;">
                    <?php if ($statut_detectat): ?>
                        ✅ Statut detectat automat din cod: <?php 
                            $info_statut = getInfoStatut($pdo, $statut_detectat);
                            echo htmlspecialchars($info_statut ? $info_statut['nume_statut'] : 'Statut ' . $statut_detectat);
                        ?> (<?php echo htmlspecialchars($statut_detectat); ?>)
                    <?php else: ?>
                        💡 Poți selecta manual statutul sau va fi detectat automat din codul Aleph (primele 2 cifre)
                    <?php endif; ?>
                </small>
            </div>

            <div class="form-group">
                <label>Nume <span class="required">*</span></label>
                <input type="text" 
                       name="nume" 
                       placeholder="" 
                       value="<?php echo htmlspecialchars($form_data['nume']); ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Prenume <span class="required">*</span></label>
                <input type="text" 
                       name="prenume" 
                       placeholder="" 
                       value="<?php echo htmlspecialchars($form_data['prenume']); ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Telefon</label>
                <input type="tel" 
                       name="telefon" 
                       placeholder=""
                       value="<?php echo htmlspecialchars($form_data['telefon']); ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" 
                       name="email" 
                       placeholder="maria@email.ro"
                       value="<?php echo htmlspecialchars($form_data['email']); ?>">
            </div>

            <!-- Secțiune imagine utilizator -->
            <div class="form-group" style="text-align: center;">
                <label style="margin-bottom: 15px;">Imagine cititor (opțional)</label>
                <div class="user-icon-container" onclick="deschideUploadModal()">
                    <svg class="user-icon-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        <circle cx="20" cy="8" r="6" fill="#28a745"/>
                        <path d="M17 8h6M20 5v6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <small style="color: #666; display: block; margin-top: 10px;">Click pentru a selecta imagine</small>
            </div>

            <button type="submit">💾 Adaugă cititor</button>
        </form>

        <!-- Previzualizare card -->
        <div class="preview-card" id="previewCard" style="display: none;">
            <h4>👤 Previzualizare card cititor</h4>
            <div class="card-grid">
                <div class="card-item">
                    <span class="card-label">Cod:</span>
                    <span class="card-value" id="previewCod">-</span>
                </div>
                <div class="card-item">
                    <span class="card-label">Nume:</span>
                    <span class="card-value" id="previewNume">-</span>
                </div>
                <div class="card-item">
                    <span class="card-label">Prenume:</span>
                    <span class="card-value" id="previewPrenume">-</span>
                </div>
                <div class="card-item">
                    <span class="card-label">Telefon:</span>
                    <span class="card-value" id="previewTelefon">-</span>
                </div>
            </div>
        </div>

		<a href="index.php" class="home-link">🔙 Înapoi la scanare</a>
        <a href="cititori.php" class="back-link">👥 Vezi toți cititorii</a>
    </div>

    <!-- Modal pentru upload imagine -->
    <div id="uploadModalOverlay" class="upload-modal-overlay" onclick="inchideUploadModal()">
        <div class="upload-modal-content" onclick="event.stopPropagation()">
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="color: #667eea; font-size: 1.5em; margin-bottom: 5px;">📷 Selectează imagine cititor</h3>
                <p style="color: #666; font-size: 0.9em;">Selectează sau trage o imagine aici</p>
            </div>
            
            <div class="drop-zone" id="dropZone">
                <p style="font-size: 1.2em; color: #667eea; margin-bottom: 10px;">📁 Pick image to upload</p>
                <p style="color: #666; font-size: 0.9em;">Sau trage imaginea aici</p>
            </div>
            
            <img id="imagePreview" class="image-preview" alt="Preview">
            
            <div class="upload-buttons" id="uploadButtons" style="display: flex;">
                <button type="button" style="background: #28a745; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; margin-right: 10px;" onclick="inchideUploadModal()">
                    ✓ Confirmă
                </button>
                <button type="button" style="background: #6c757d; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1;" onclick="anuleazaUpload()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
    </div>

    <script>
        // Funcție pentru actualizare previzualizare
        function updatePreview() {
            const codInput = document.getElementById('cod_bare_input');
            const cod = codInput ? codInput.value.trim() : '';
            const nume = document.querySelector('input[name="nume"]').value.trim();
            const prenume = document.querySelector('input[name="prenume"]').value.trim();
            const telefon = document.querySelector('input[name="telefon"]').value.trim();

            const preview = document.getElementById('previewCard');

            if (cod || nume || prenume) {
                preview.style.display = 'block';
                document.getElementById('previewCod').textContent = cod || '-';
                document.getElementById('previewNume').textContent = nume || '-';
                document.getElementById('previewPrenume').textContent = prenume || '-';
                document.getElementById('previewTelefon').textContent = telefon || '-';
            } else {
                preview.style.display = 'none';
            }
        }

        // Adaugă event listeners pentru actualizare în timp real
        const codInputForPreview = document.getElementById('cod_bare_input');
        if (codInputForPreview) {
            codInputForPreview.addEventListener('input', updatePreview);
        }
        document.querySelector('input[name="nume"]').addEventListener('input', updatePreview);
        document.querySelector('input[name="prenume"]').addEventListener('input', updatePreview);
        document.querySelector('input[name="telefon"]').addEventListener('input', updatePreview);

        // Validare email simplă
        document.querySelector('input[name="email"]').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#ddd';
            }
        });

        // Actualizează previzualizarea la încărcare dacă sunt date
        window.addEventListener('load', updatePreview);
        
        // Upload imagine
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        const imagePreview = document.getElementById('imagePreview');
        const uploadButtons = document.getElementById('uploadButtons');
        const userIconContainer = document.querySelector('.user-icon-container');
        
        function deschideUploadModal() {
            document.getElementById('uploadModalOverlay').style.display = 'block';
        }
        
        function inchideUploadModal() {
            document.getElementById('uploadModalOverlay').style.display = 'none';
        }
        
        function anuleazaUpload() {
            fileInput.value = '';
            imagePreview.style.display = 'none';
            imagePreview.src = '';
            dropZone.style.display = 'block';
            uploadButtons.style.display = 'none';
            inchideUploadModal();
        }
        
        function handleFileSelect(e) {
            const file = e.target.files[0] || (e.dataTransfer && e.dataTransfer.files[0]);
            if (!file) return;
            
            if (!file.type.startsWith('image/')) {
                alert('❌ Te rog să selectezi o imagine!');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                dropZone.style.display = 'none';
                uploadButtons.style.display = 'flex';
                
                // Actualizează icon-ul din formular
                if (userIconContainer) {
                    userIconContainer.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
                }
            };
            reader.readAsDataURL(file);
        }
        
        // Click pe drop zone
        dropZone.addEventListener('click', () => fileInput.click());
        
        // Drag & drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect({ target: fileInput });
        });
        
        // Change event pentru file input
        fileInput.addEventListener('change', handleFileSelect);
        
        // Închide modal cu ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                inchideUploadModal();
            }
        });
        
        // Funcționalitate buton EDIT pentru codul de bare
        const btnEditCod = document.getElementById('btn_edit_cod');
        const codBareInput = document.getElementById('cod_bare_input');
        
        if (btnEditCod && codBareInput) {
            const codOriginal = codBareInput.value;
            let isEditing = false;

            btnEditCod.addEventListener('click', function() {
                if (!isEditing) {
                    // Activează editarea
                    codBareInput.removeAttribute('readonly');
                    codBareInput.style.background = '#fff';
                    codBareInput.style.borderColor = '#667eea';
                    codBareInput.focus();
                    btnEditCod.textContent = '✕ Anulează';
                    btnEditCod.classList.add('cancel');
                    isEditing = true;
                } else {
                    // Anulează editarea - revine la valoarea originală
                    codBareInput.value = codOriginal;
                    codBareInput.setAttribute('readonly', 'readonly');
                    codBareInput.style.background = '#e9ecef';
                    codBareInput.style.borderColor = '#ddd';
                    btnEditCod.textContent = '✏️ EDIT';
                    btnEditCod.classList.remove('cancel');
                    isEditing = false;
                }
            });
        }
        
        // Auto-focus pe primul câmp gol
        window.addEventListener('load', function() {
            const codInput = document.getElementById('cod_bare_input');
            const numeInput = document.querySelector('input[name="nume"]');
            
            if (codInput && codInput.value.trim() !== '') {
                numeInput.focus();
            } else if (codInput) {
                codInput.focus();
            }
        });
        
        // Detectare automată tip cod și afișare informații
        const codInput = document.getElementById('cod_bare_input');
        if (codInput) {
            const codInfo = document.getElementById('cod_info');
            const statutGroup = document.getElementById('statut_group');
            const statutSelect = document.getElementById('statut_select');
            
            // Validare input - previne caractere invalide și limitează lungimea
            codInput.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which || e.keyCode);
                const currentValue = this.value;
                
                // Permite control keys (backspace, delete, tab, etc.)
                if (e.which === 0 || e.which === 8 || e.which === 9 || e.which === 13 || 
                    e.which === 27 || (e.which >= 37 && e.which <= 40)) {
                    return true;
                }
                
                // Dacă codul începe cu USER (case insensitive)
                if (/^USER/i.test(currentValue + char)) {
                    // După USER, permite doar cifre (maxim 6 cifre pentru USER999999)
                    if (/^USER/i.test(currentValue)) {
                        const numarPart = currentValue.substring(4);
                        if (numarPart.length >= 6) {
                            return false; // Nu permite mai mult de 6 cifre după USER
                        }
                        return /^\d$/.test(char);
                    }
                    // În timpul scrierii USER, permite doar literele USER
                    return /^[USERuser]$/i.test(char);
                }
                
                // Pentru coduri numerice
                if (/^\d/.test(currentValue + char)) {
                    // Verifică dacă este cod Biblioteca Academiei (14016xxx - 8 cifre)
                    if (/^14016/.test(currentValue + char)) {
                        return currentValue.length < 8 && /^\d$/.test(char);
                    }
                    // Pentru coduri Aleph (12 cifre) sau alte coduri numerice
                    // Limitează la 12 cifre pentru coduri Aleph
                    if (currentValue.length >= 12) {
                        return false; // Nu permite mai mult de 12 cifre
                    }
                    return /^\d$/.test(char);
                }
                
                return false;
            });
            
            // Validare la paste
            codInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const currentValue = this.value;
                
                // Verifică dacă textul lipit este valid
                let validText = '';
                if (/^USER/i.test(pastedText)) {
                    // USER urmat de cifre
                    const match = pastedText.match(/^(USER\d*)/i);
                    if (match) {
                        validText = match[1].toUpperCase();
                    }
                } else {
                    // Doar cifre
                    validText = pastedText.replace(/\D/g, '');
                }
                
                this.value = currentValue + validText;
                // Trigger input event pentru a actualiza detecția
                this.dispatchEvent(new Event('input'));
            });
            
            function detecteazaSiSelecteazaStatut(cod) {
                // Verifică format Aleph (12 cifre) sau cod numeric (cel puțin 2 cifre)
                if (/^\d{12}$/.test(cod)) {
                    // Cod Aleph - extrage primele 2 cifre
                    const statut = cod.substring(0, 2);
                    codInfo.innerHTML = `✅ Format Aleph detectat | Statut: ${statut} | Număr: ${cod.substring(2, 11)}`;
                    codInfo.style.color = '#28a745';
                    
                    // Setează statutul în select (dacă există)
                    if (statutSelect && statutSelect.querySelector(`option[value="${statut}"]`)) {
                        statutSelect.value = statut;
                        // Afișează mesaj de succes
                        showStatutDetected(statut);
                    }
                    if (statutGroup) statutGroup.style.display = 'block';
                } else if (/^\d{2}/.test(cod)) {
                    // Cod numeric - extrage primele 2 cifre
                    const statut = cod.substring(0, 2);
                    codInfo.innerHTML = `✅ Format numeric detectat | Statut: ${statut}`;
                    codInfo.style.color = '#28a745';
                    
                    // Setează statutul în select (dacă există)
                    if (statutSelect && statutSelect.querySelector(`option[value="${statut}"]`)) {
                        statutSelect.value = statut;
                        showStatutDetected(statut);
                    }
                    if (statutGroup) statutGroup.style.display = 'block';
                } else if (/^USER/i.test(cod)) {
                    // Cod USER - statut implicit 14
                    codInfo.innerHTML = '✅ Format USER detectat (pentru testare) | Statut implicit: 14';
                    codInfo.style.color = '#28a745';
                    
                    if (statutSelect && statutSelect.querySelector('option[value="14"]')) {
                        statutSelect.value = '14';
                        showStatutDetected('14');
                    }
                    if (statutGroup) statutGroup.style.display = 'block';
                }
                // Verifică format Biblioteca Academiei (14016xxx - 8 cifre)
                else if (/^14016\d{3}$/.test(cod)) {
                    codInfo.innerHTML = '✅ Format Biblioteca Academiei detectat (14016xxx)';
                    codInfo.style.color = '#28a745';
                    if (statutGroup) statutGroup.style.display = 'block';
                }
                // Cod invalid sau incomplet
                else if (cod.length > 0) {
                    codInfo.innerHTML = '⚠️ Format necunoscut. Formate acceptate: USER001, 14016xxx (Biblioteca Academiei) sau 12 cifre (Aleph)';
                    codInfo.style.color = '#ffc107';
                    if (statutGroup) statutGroup.style.display = 'block';
                }
                // Câmp gol
                else {
                    codInfo.innerHTML = '💡 Formate acceptate: USER001 (testare), 14016xxx (Biblioteca Academiei) sau 12 cifre (Aleph)';
                    codInfo.style.color = '#666';
                    if (statutGroup) statutGroup.style.display = 'block';
                }
            }
            
            function showStatutDetected(codStatut) {
                // Creează sau actualizează mesajul de detecție
                let detectMsg = document.getElementById('statut_detected_msg');
                if (!detectMsg) {
                    detectMsg = document.createElement('small');
                    detectMsg.id = 'statut_detected_msg';
                    detectMsg.style.display = 'block';
                    detectMsg.style.marginTop = '8px';
                    detectMsg.style.color = '#28a745';
                    detectMsg.style.fontSize = '0.95em';
                    detectMsg.style.fontWeight = '500';
                    statutGroup.appendChild(detectMsg);
                }
                
                const option = statutSelect.querySelector(`option[value="${codStatut}"]`);
                const numeStatut = option ? option.textContent.split(' - ')[1]?.split(' (')[0] : 'Necunoscut';
                detectMsg.innerHTML = `✅ Statut detectat automat: ${numeStatut} (${codStatut})`;
            }
            
            codInput.addEventListener('input', function() {
                // Curăță caracterele invalide și limitează lungimea
                let value = this.value;
                
                // Dacă începe cu USER, păstrează USER + cifre (maxim 6 cifre)
                if (/^USER/i.test(value)) {
                    const numarPart = value.substring(4).replace(/\D/g, '');
                    // Limitează la 6 cifre după USER (USER999999)
                    value = 'USER' + numarPart.substring(0, 6);
                } else {
                    // Altfel, doar cifre
                    value = value.replace(/\D/g, '');
                    // Limitează la 12 cifre pentru coduri Aleph
                    if (value.length > 12) {
                        value = value.substring(0, 12);
                    }
                }
                
                if (this.value !== value) {
                    this.value = value;
                }
                
                const cod = this.value.trim();
                
                // Validare lungime și afișare mesaj
                valideazaLungimeCod(cod);
                
                detecteazaSiSelecteazaStatut(cod);
                updatePreview();
            });
            
            // Funcție pentru validarea lungimii codului
            function valideazaLungimeCod(cod) {
                let errorMsg = document.getElementById('error_lungime_message');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.id = 'error_lungime_message';
                    errorMsg.className = 'error-message-statut';
                    errorMsg.style.marginTop = '5px';
                    codInput.parentNode.insertBefore(errorMsg, codInput.nextSibling);
                }
                
                if (!cod) {
                    errorMsg.classList.remove('show');
                    codInput.classList.remove('error-statut');
                    return true;
                }
                
                let isValid = true;
                let message = '';
                
                if (/^USER/i.test(cod)) {
                    const numarPart = cod.substring(4);
                    if (numarPart.length === 0) {
                        isValid = false;
                        message = '⚠️ Codul USER trebuie să aibă cel puțin o cifră după USER (ex: USER001)';
                    } else if (numarPart.length > 6) {
                        isValid = false;
                        message = '⚠️ Codul USER nu poate avea mai mult de 6 cifre după USER (maxim USER999999)';
                    }
                } else if (/^\d/.test(cod)) {
                    if (/^14016/.test(cod)) {
                        // Cod Biblioteca Academiei - exact 8 cifre
                        if (cod.length !== 8) {
                            isValid = false;
                            message = '⚠️ Codul Biblioteca Academiei trebuie să aibă exact 8 cifre (14016xxx)';
                        }
                    } else {
                        // Cod Aleph - exact 12 cifre
                        if (cod.length !== 12 && cod.length > 0) {
                            isValid = false;
                            message = '⚠️ Codul Aleph trebuie să aibă exact 12 cifre (ex: 150000000010)';
                        }
                    }
                }
                
                if (!isValid) {
                    errorMsg.textContent = message;
                    errorMsg.classList.add('show');
                    codInput.classList.add('error-statut');
                } else {
                    errorMsg.classList.remove('show');
                    codInput.classList.remove('error-statut');
                }
                
                return isValid;
            }
            
            // Trigger la încărcare dacă există deja un cod
            if (codInput.value.trim() !== '') {
                detecteazaSiSelecteazaStatut(codInput.value.trim());
            }
        }
        
        // Validare la submit formular
        const cititorForm = document.getElementById('cititorForm');
        if (cititorForm) {
            cititorForm.addEventListener('submit', function(e) {
                const codInput = document.getElementById('cod_bare_input');
                if (codInput) {
                    const cod = codInput.value.trim();
                    
                    if (cod) {
                        let isValid = true;
                        let message = '';
                        
                        if (/^USER/i.test(cod)) {
                            const numarPart = cod.substring(4);
                            if (numarPart.length === 0) {
                                isValid = false;
                                message = '⚠️ Codul USER trebuie să aibă cel puțin o cifră după USER (ex: USER001)';
                            } else if (numarPart.length > 6) {
                                isValid = false;
                                message = '⚠️ Codul USER nu poate avea mai mult de 6 cifre după USER (maxim USER999999)';
                            }
                        } else if (/^\d/.test(cod)) {
                            if (/^14016/.test(cod)) {
                                if (cod.length !== 8) {
                                    isValid = false;
                                    message = '⚠️ Codul Biblioteca Academiei trebuie să aibă exact 8 cifre (14016xxx)';
                                }
                            } else {
                                if (cod.length !== 12) {
                                    isValid = false;
                                    message = '⚠️ Codul Aleph trebuie să aibă exact 12 cifre (ex: 150000000010)';
                                }
                            }
                        }
                        
                        if (!isValid) {
                            e.preventDefault();
                            alert(message);
                            codInput.focus();
                            return false;
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>