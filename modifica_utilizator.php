<?php
/**
 * Script pentru modificarea utilizatorilor și parolelor
 * 
 * Acest script permite:
 * - Modificarea parolei unui utilizator
 * - Modificarea username-ului
 * - Adăugarea de utilizatori noi
 * - Dezactivarea/activarea conturilor
 */

session_start();
require_once 'config.php';
require_once 'auth_check.php';

$mesaj = '';
$tip_mesaj = '';

// Procesare formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actiune = $_POST['actiune'] ?? '';
    
    if ($actiune === 'modifica_parola') {
        $username = trim($_POST['username'] ?? '');
        $parola_noua = $_POST['parola_noua'] ?? '';
        $parola_noua_confirmare = $_POST['parola_noua_confirmare'] ?? '';
        
        if (empty($username) || empty($parola_noua)) {
            $mesaj = '⚠️ Completează toate câmpurile!';
            $tip_mesaj = 'warning';
        } elseif ($parola_noua !== $parola_noua_confirmare) {
            $mesaj = '❌ Parolele nu coincid!';
            $tip_mesaj = 'danger';
        } elseif (strlen($parola_noua) < 4) {
            $mesaj = '⚠️ Parola trebuie să aibă minim 4 caractere!';
            $tip_mesaj = 'warning';
        } else {
            try {
                // Generează hash nou pentru parolă
                $password_hash = password_hash($parola_noua, PASSWORD_DEFAULT);
                
                // Actualizează parola în baza de date
                $stmt = $pdo->prepare("UPDATE utilizatori SET password_hash = ? WHERE username = ?");
                $stmt->execute([$password_hash, $username]);
                
                if ($stmt->rowCount() > 0) {
                    $mesaj = "✅ Parola pentru utilizatorul '$username' a fost modificată cu succes!";
                    $tip_mesaj = 'success';
                } else {
                    $mesaj = "❌ Utilizatorul '$username' nu a fost găsit!";
                    $tip_mesaj = 'danger';
                }
            } catch (PDOException $e) {
                $mesaj = "❌ Eroare: " . $e->getMessage();
                $tip_mesaj = 'danger';
            }
        }
    }
    
    elseif ($actiune === 'modifica_username') {
        $username_vechi = trim($_POST['username_vechi'] ?? '');
        $username_nou = trim($_POST['username_nou'] ?? '');
        
        if (empty($username_vechi) || empty($username_nou)) {
            $mesaj = '⚠️ Completează toate câmpurile!';
            $tip_mesaj = 'warning';
        } elseif ($username_vechi === $username_nou) {
            $mesaj = '⚠️ Username-ul nou trebuie să fie diferit de cel vechi!';
            $tip_mesaj = 'warning';
        } else {
            try {
                // Verifică dacă username-ul nou există deja
                $stmt_check = $pdo->prepare("SELECT id FROM utilizatori WHERE username = ?");
                $stmt_check->execute([$username_nou]);
                if ($stmt_check->fetch()) {
                    $mesaj = "❌ Username-ul '$username_nou' există deja!";
                    $tip_mesaj = 'danger';
                } else {
                    // Actualizează username-ul
                    $stmt = $pdo->prepare("UPDATE utilizatori SET username = ? WHERE username = ?");
                    $stmt->execute([$username_nou, $username_vechi]);
                    
                    if ($stmt->rowCount() > 0) {
                        $mesaj = "✅ Username-ul a fost modificat de la '$username_vechi' la '$username_nou'!";
                        $tip_mesaj = 'success';
                    } else {
                        $mesaj = "❌ Utilizatorul '$username_vechi' nu a fost găsit!";
                        $tip_mesaj = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $mesaj = "❌ Eroare: " . $e->getMessage();
                $tip_mesaj = 'danger';
            }
        }
    }
    
    elseif ($actiune === 'adauga_utilizator') {
        $username = trim($_POST['username_nou'] ?? '');
        $parola = $_POST['parola_noua'] ?? '';
        $nume = trim($_POST['nume'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($parola)) {
            $mesaj = '⚠️ Username și parola sunt obligatorii!';
            $tip_mesaj = 'warning';
        } elseif (strlen($parola) < 4) {
            $mesaj = '⚠️ Parola trebuie să aibă minim 4 caractere!';
            $tip_mesaj = 'warning';
        } else {
            try {
                // Generează hash pentru parolă
                $password_hash = password_hash($parola, PASSWORD_DEFAULT);
                
                // Inserează utilizatorul nou
                $stmt = $pdo->prepare("
                    INSERT INTO utilizatori (username, password_hash, nume, email, activ) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$username, $password_hash, $nume, $email]);
                
                $mesaj = "✅ Utilizatorul '$username' a fost adăugat cu succes!";
                $tip_mesaj = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mesaj = "❌ Username-ul '$username' există deja!";
                } else {
                    $mesaj = "❌ Eroare: " . $e->getMessage();
                }
                $tip_mesaj = 'danger';
            }
        }
    }
    
    elseif ($actiune === 'activeaza_dezactiveaza') {
        $username = trim($_POST['username'] ?? '');
        $activ = isset($_POST['activ']) ? (int)$_POST['activ'] : 0;
        
        if (empty($username)) {
            $mesaj = '⚠️ Selectează un utilizator!';
            $tip_mesaj = 'warning';
        } else {
            try {
                // Verifică dacă utilizatorul există
                $stmt_check = $pdo->prepare("SELECT id FROM utilizatori WHERE username = ?");
                $stmt_check->execute([$username]);
                $user_exists = $stmt_check->fetch();
                
                if (!$user_exists) {
                    $mesaj = "❌ Utilizatorul '$username' nu există!";
                    $tip_mesaj = 'danger';
                } else {
                    // Actualizează statusul
                    $stmt = $pdo->prepare("UPDATE utilizatori SET activ = ? WHERE username = ?");
                    $stmt->execute([$activ, $username]);
                    
                    if ($stmt->rowCount() > 0) {
                        $status = $activ ? 'activ' : 'dezactivat';
                        $mesaj = "✅ Utilizatorul '$username' a fost $status!";
                        $tip_mesaj = 'success';
                        
                        // Dacă utilizatorul curent s-a dezactivat pe sine, deconectează-l
                        if (!$activ && isset($_SESSION['utilizator_username']) && $_SESSION['utilizator_username'] === $username) {
                            require_once 'functions_autentificare.php';
                            distrugeSesiune();
                            $mesaj .= " Ai fost deconectat automat.";
                            // Redirecționează la login după 2 secunde
                            header("Refresh: 2; url=login.php");
                        }
                    } else {
                        $mesaj = "⚠️ Nu s-a putut actualiza statusul utilizatorului '$username'!";
                        $tip_mesaj = 'warning';
                    }
                }
            } catch (PDOException $e) {
                $mesaj = "❌ Eroare: " . $e->getMessage();
                $tip_mesaj = 'danger';
            }
        }
    }
}

// Obține lista utilizatorilor
$stmt = $pdo->query("SELECT id, username, nume, email, activ, data_creare, ultima_autentificare FROM utilizatori ORDER BY id");
$utilizatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionare Utilizatori - Biblioteca</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px 20px;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 12px 20px;
            background: #f8f9fa;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #0c5460;
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
        <h1>👤 Gestionare Utilizatori</h1>
        
        <div class="info-box">
            <p><strong>💡 Informații:</strong></p>
            <p>• Parolele sunt hash-uite cu BCRYPT (securitate maximă)</p>
            <p>• La modificarea parolei, se generează automat un hash nou</p>
            <p>• Username-ul trebuie să fie unic în sistem</p>
        </div>
        
        <?php if ($mesaj): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('modifica_parola')">🔑 Modifică Parolă</button>
            <button class="tab" onclick="showTab('modifica_username')">✏️ Modifică Username</button>
            <button class="tab" onclick="showTab('adauga_utilizator')">➕ Adaugă Utilizator</button>
            <button class="tab" onclick="showTab('lista_utilizatori')">📋 Lista Utilizatori</button>
        </div>
        
        <!-- Tab: Modifică Parolă -->
        <div id="modifica_parola" class="tab-content active">
            <h2>🔑 Modifică Parolă</h2>
            <form method="POST">
                <input type="hidden" name="actiune" value="modifica_parola">
                
                <div class="form-group">
                    <label for="username_parola">Username:</label>
                    <input type="text" id="username_parola" name="username" 
                           placeholder="larisa2025" required>
                </div>
                
                <div class="form-group">
                    <label for="parola_noua">Parolă Nouă:</label>
                    <input type="password" id="parola_noua" name="parola_noua" 
                           placeholder="Introdu parola nouă" required minlength="4">
                </div>
                
                <div class="form-group">
                    <label for="parola_noua_confirmare">Confirmă Parola:</label>
                    <input type="text" id="parola_noua_confirmare" name="parola_noua_confirmare" 
                           placeholder="Confirmă parola nouă" required minlength="4" autocomplete="off">
                </div>
                
                <button type="submit" class="btn btn-primary">💾 Modifică Parola</button>
            </form>
        </div>
        
        <!-- Tab: Modifică Username -->
        <div id="modifica_username" class="tab-content">
            <h2>✏️ Modifică Username</h2>
            <form method="POST">
                <input type="hidden" name="actiune" value="modifica_username">
                
                <div class="form-group">
                    <label for="username_vechi">Username Vechi:</label>
                    <input type="text" id="username_vechi" name="username_vechi" 
                           placeholder="larisa2025" required>
                </div>
                
                <div class="form-group">
                    <label for="username_nou_username">Username Nou:</label>
                    <input type="text" id="username_nou_username" name="username_nou" 
                           placeholder="larisa2026" required>
                </div>
                
                <button type="submit" class="btn btn-primary">💾 Modifică Username</button>
            </form>
        </div>
        
        <!-- Tab: Adaugă Utilizator -->
        <div id="adauga_utilizator" class="tab-content">
            <h2>➕ Adaugă Utilizator Nou</h2>
            <form method="POST">
                <input type="hidden" name="actiune" value="adauga_utilizator">
                
                <div class="form-group">
                    <label for="username_nou">Username: <span style="color: red;">*</span></label>
                    <input type="text" id="username_nou" name="username_nou" 
                           placeholder="nou_utilizator" required>
                </div>
                
                <div class="form-group">
                    <label for="parola_noua_user">Parolă: <span style="color: red;">*</span></label>
                    <input type="password" id="parola_noua_user" name="parola_noua" 
                           placeholder="Introdu parola" required minlength="4">
                </div>
                
                <div class="form-group">
                    <label for="nume">Nume:</label>
                    <input type="text" id="nume" name="nume" 
                           placeholder="Nume complet (opțional)">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" 
                           placeholder="email@example.com (opțional)">
                </div>
                
                <button type="submit" class="btn btn-primary">➕ Adaugă Utilizator</button>
            </form>
        </div>
        
        <!-- Tab: Lista Utilizatori -->
        <div id="lista_utilizatori" class="tab-content">
            <h2>📋 Lista Utilizatori</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nume</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Data Creare</th>
                        <th>Ultima Autentificare</th>
                        <th>Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilizatori as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['nume'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $user['activ'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $user['activ'] ? 'ACTIV' : 'DEZACTIVAT'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['data_creare'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['ultima_autentificare'] ?? 'Niciodată'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Ești sigur că vrei să <?php echo $user['activ'] ? 'dezactivezi' : 'activezi'; ?> utilizatorul <?php echo htmlspecialchars($user['username']); ?>?');">
                                    <input type="hidden" name="actiune" value="activeaza_dezactiveaza">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                    <input type="hidden" name="activ" value="<?php echo $user['activ'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $user['activ'] ? 'btn-secondary' : 'btn-primary'; ?>" style="padding: 6px 12px; font-size: 12px;">
                                        <?php echo $user['activ'] ? '🚫 Dezactivează' : '✅ Activează'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="afiseaza_utilizatori.php" class="btn btn-primary" style="margin-right: 10px;">👁️ Afișează Utilizatori</a>
            <a href="index.php" class="btn btn-secondary">← Înapoi la Index</a>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Ascunde toate tab-urile
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Elimină active de la toate butoanele
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Arată tab-ul selectat
            document.getElementById(tabName).classList.add('active');
            
            // Activează butonul
            event.target.classList.add('active');
        }
    </script>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>

