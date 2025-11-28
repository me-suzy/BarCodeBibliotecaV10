<?php
// editare_cititor.php - Editare date cititor
require_once 'config.php';

$mesaj = '';
$tip_mesaj = '';

// Verifică dacă avem ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID cititor lipsă');
}

$id = (int)$_GET['id'];

// Obține datele cititorului
$stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
$stmt->execute([$id]);
$cititor = $stmt->fetch();

if (!$cititor) {
    die('Cititorul nu a fost găsit');
}

// Procesare ștergere
if (isset($_POST['delete']) && $_POST['delete'] === 'true') {
    try {
        // Verifică dacă cititorul are împrumuturi în istoric
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ?");
        $check_stmt->execute([$cititor['cod_bare']]);
        $imprumuturi_count = $check_stmt->fetchColumn();

        $sterge_istoric = isset($_POST['sterge_istoric']) && $_POST['sterge_istoric'] === 'true';

        if ($imprumuturi_count > 0 && !$sterge_istoric) {
            // Nu are permisiune să șteargă istoric, arată warning
            $mesaj = "❌ Nu poți șterge acest cititor!\n\n" .
                     "Cititorul are {$imprumuturi_count} împrumuturi în istoric.\n\n" .
                     "Dacă vrei să ștergi cititorul împreună cu tot istoricul, " .
                     "apasă butonul 'Șterge cu tot istoricul' de jos.";
            $tip_mesaj = "danger";
        } else {
            // Are permisiune SAU nu are împrumuturi
            if ($imprumuturi_count > 0) {
                // Șterge mai întâi împrumuturile
                $del_stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE cod_cititor = ?");
                $del_stmt->execute([$cititor['cod_bare']]);
            }
            
            // Acum șterge cititorul
            $stmt = $pdo->prepare("DELETE FROM cititori WHERE id = ?");
            $stmt->execute([$id]);
            
            echo "<script>alert('✅ Cititorul și {$imprumuturi_count} împrumuturi au fost șterse!'); window.location.href='cititori.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        $mesaj = "❌ Eroare la ștergere: " . $e->getMessage();
        $tip_mesaj = "danger";
    }
}

// Procesare formular
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $cod_bare = trim($_POST['cod_bare'] ?? '');
    $nume = trim($_POST['nume'] ?? '');
    $prenume = trim($_POST['prenume'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE cititori
            SET cod_bare = ?, nume = ?, prenume = ?, telefon = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$cod_bare, $nume, $prenume, $telefon, $email, $id]);

        $mesaj = "✅ Cititorul a fost actualizat cu succes!";
        $tip_mesaj = "success";

        // Reîncarcă datele
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
        $stmt->execute([$id]);
        $cititor = $stmt->fetch();

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
    <title>Editare Cititor - Sistem Bibliotecă</title>
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

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus {
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

        .delete-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            margin-top: 15px;
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #c82333 0%, #a02622 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,0,0,0.2);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-buttons a, .action-buttons button {
            flex: 1;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Editare Cititor</h1>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="cititorForm">
            <div class="form-group">
                <label>Cod de bare carnet <span class="required">*</span></label>
                <input type="text" name="cod_bare" value="<?php echo htmlspecialchars($cititor['cod_bare']); ?>" required>
            </div>

            <div class="form-group">
                <label>Nume <span class="required">*</span></label>
                <input type="text" name="nume" value="<?php echo htmlspecialchars($cititor['nume']); ?>" required>
            </div>

            <div class="form-group">
                <label>Prenume <span class="required">*</span></label>
                <input type="text" name="prenume" value="<?php echo htmlspecialchars($cititor['prenume']); ?>" required>
            </div>

            <div class="form-group">
                <label>Telefon</label>
                <input type="tel" name="telefon" value="<?php echo htmlspecialchars($cititor['telefon'] ?: ''); ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($cititor['email'] ?: ''); ?>">
            </div>

            <button type="submit">💾 Salvează modificările</button>
        </form>

        <!-- Previzualizare card -->
        <div class="preview-card" id="previewCard">
            <h4>👤 Previzualizare card cititor</h4>
            <div class="card-grid">
                <div class="card-item">
                    <span class="card-label">Cod:</span>
                    <span class="card-value" id="previewCod"><?php echo htmlspecialchars($cititor['cod_bare']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Nume:</span>
                    <span class="card-value" id="previewNume"><?php echo htmlspecialchars($cititor['nume']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Prenume:</span>
                    <span class="card-value" id="previewPrenume"><?php echo htmlspecialchars($cititor['prenume']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Telefon:</span>
                    <span class="card-value" id="previewTelefon"><?php echo htmlspecialchars($cititor['telefon'] ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="index.php" class="home-link">🏠 Acasă</a>
            <a href="cititori.php" class="back-link">← Înapoi la lista cititori</a>
        </div>

		
		<form method="POST" style="margin-top: 20px;" onsubmit="return confirm('⚠️ Ești sigur că vrei să ștergi acest cititor?\n\nAceastă acțiune NU poate fi anulată!')">
			<input type="hidden" name="delete" value="true">
			<button type="submit" class="delete-btn">🗑️ Șterge cititorul (doar dacă nu are istoric)</button>
		</form>

		<form method="POST" style="margin-top: 10px;" onsubmit="return confirm('🚨 ATENȚIE!\n\nVei șterge cititorul ȘI TOATE împrumuturile lui din istoric!\n\nAceastă acțiune NU poate fi anulată!\n\nEști absolut sigur?')">
			<input type="hidden" name="delete" value="true">
			<input type="hidden" name="sterge_istoric" value="true">
			<button type="submit" class="delete-btn" style="background: linear-gradient(135deg, #ff0000 0%, #990000 100%);">
				💥 Șterge cititorul CU TOT ISTORICUL
			</button>
		</form>

    </div>

    <script>
        // Actualizare previzualizare în timp real
        function updatePreview() {
            const cod = document.querySelector('input[name="cod_bare"]').value.trim();
            const nume = document.querySelector('input[name="nume"]').value.trim();
            const prenume = document.querySelector('input[name="prenume"]').value.trim();
            const telefon = document.querySelector('input[name="telefon"]').value.trim();

            document.getElementById('previewCod').textContent = cod || '-';
            document.getElementById('previewNume').textContent = nume || '-';
            document.getElementById('previewPrenume').textContent = prenume || '-';
            document.getElementById('previewTelefon').textContent = telefon || '-';
        }

        // Adaugă event listeners pentru actualizare în timp real
        document.querySelector('input[name="cod_bare"]').addEventListener('input', updatePreview);
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

        // Resetare formular după succes
        <?php if (isset($mesaj) && $tip_mesaj === 'success'): ?>
            setTimeout(() => {
                // Reîncarcă previzualizarea cu datele noi
                updatePreview();
            }, 500);
        <?php endif; ?>
    </script>
</body>
</html>
