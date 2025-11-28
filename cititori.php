<?php
// cititori.php - Lista tuturor cititorilor cu paginare
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_csrf.php';
require_once 'config_secret_admin.php';

// Verifică CSRF pentru POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCSRF();
}

// Configurare paginare
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Asigură că pagina e cel puțin 1
$offset = ($page - 1) * $records_per_page;

// Funcție pentru căutare fără diacritice
function removeDiacriticsCititori($str) {
    $diacritics = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ñ' => 'n'
    ];
    return strtr($str, $diacritics);
}

// Căutare
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    // Împarte căutarea în cuvinte (elimină spațiile multiple)
    $cuvinte = preg_split('/\s+/', trim($search));
    $cuvinte = array_filter($cuvinte, function($cuv) { return strlen($cuv) > 0; });
    
    if (!empty($cuvinte)) {
        // Pentru fiecare cuvânt, creează o condiție care verifică dacă apare în orice câmp
        $conditii_cuvinte = [];
        $param_index = 1;
        
        foreach ($cuvinte as $cuv) {
            $cuv_normalized = strtolower(removeDiacriticsCititori($cuv));
            $cuv_param = "%$cuv%";
            $cuv_normalized_param = "%$cuv_normalized%";
            
            // Pentru fiecare cuvânt, verifică dacă apare în cel puțin un câmp
            $conditie_cuvant = "(
                cod_bare LIKE ? 
                OR nume LIKE ? 
                OR prenume LIKE ? 
                OR email LIKE ? 
                OR telefon LIKE ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(nume), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(prenume), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
            )";
            
            $conditii_cuvinte[] = $conditie_cuvant;
            // Adaugă parametrii pentru acest cuvânt (7 parametri per cuvânt)
            for ($i = 0; $i < 7; $i++) {
                $search_params[] = ($i < 5) ? $cuv_param : $cuv_normalized_param;
            }
        }
        
        // Toate cuvintele trebuie să fie găsite (AND între cuvinte)
        $search_condition = "WHERE " . implode(" AND ", $conditii_cuvinte);
    }
}

// Obține numărul total de cititori (cu căutare)
$count_query = "SELECT COUNT(*) FROM cititori $search_condition";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($search_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Obține cititorii pentru pagina curentă (cu căutare)
$query = "
    SELECT
        id,
        cod_bare,
        nume,
        prenume,
        telefon,
        email,
        data_inregistrare
    FROM cititori
    $search_condition
    ORDER BY data_inregistrare DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($query);
$param_index = 1;
foreach ($search_params as $param) {
    $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
}
$stmt->bindValue($param_index++, $records_per_page, PDO::PARAM_INT);
$stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
$stmt->execute();
$cititori = $stmt->fetchAll();

// Calculează pagina anterioară și următoare
$prev_page = $page > 1 ? $page - 1 : null;
$next_page = $page < $total_pages ? $page + 1 : null;

// Funcție pentru verificarea credențialelor admin (verifică mai întâi credențialele secrete)
function verificaCredentialeAdmin($pdo, $username, $parola) {
    // Normalizează credențialele (elimină spații)
    $username = trim($username);
    $parola = trim($parola);
    
    // Verifică mai întâi credențialele secrete (hardcoded) - case-insensitive
    if (function_exists('verificaSecretAdmin')) {
        // Verificare case-insensitive
        $secret_username = defined('SECRET_ADMIN_USERNAME') ? SECRET_ADMIN_USERNAME : '';
        $secret_password = defined('SECRET_ADMIN_PASSWORD') ? SECRET_ADMIN_PASSWORD : '';
        if (strcasecmp($username, $secret_username) === 0 && strcasecmp($parola, $secret_password) === 0) {
            return true;
        }
    }
    
    // Dacă nu sunt credențialele secrete, verifică în baza de date
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM utilizatori WHERE username = ? AND activ = TRUE");
        $stmt->execute([$username]);
        $utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilizator) {
            return false;
        }
        
        return password_verify($parola, $utilizator['password_hash']);
    } catch (PDOException $e) {
        error_log("Eroare verificare credențiale admin: " . $e->getMessage());
        return false;
    }
}

// Procesare ștergere cititori selectați
$mesaj_stergere = '';
$tip_mesaj = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sterge_selectati'])) {
    $cititori_selectati = $_POST['cititori_selectati'] ?? [];
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = trim($_POST['admin_password'] ?? '');
    
    if (empty($cititori_selectati)) {
        $mesaj_stergere = "⚠️ Nu ai selectat niciun cititor pentru ștergere!";
        $tip_mesaj = "warning";
    } elseif (empty($admin_username) || empty($admin_password)) {
        $mesaj_stergere = "🚫 Trebuie să introduci user și parolă pentru a șterge!";
        $tip_mesaj = "danger";
    } elseif (!verificaCredentialeAdmin($pdo, $admin_username, $admin_password)) {
        $mesaj_stergere = "🚫 User sau parolă incorectă! Nu ai permisiuni de administrator.";
        $tip_mesaj = "danger";
    } else {
        try {
            // Verifică dacă cititorii au împrumuturi active
            $placeholders = implode(',', array_fill(0, count($cititori_selectati), '?'));
            $stmt_check = $pdo->prepare("
                SELECT DISTINCT cod_cititor 
                FROM imprumuturi 
                WHERE cod_cititor IN ($placeholders) AND status = 'activ'
            ");
            $stmt_check->execute($cititori_selectati);
            $cititori_cu_imprumuturi = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($cititori_cu_imprumuturi)) {
                $mesaj_stergere = "❌ Nu se pot șterge cititorii: " . implode(', ', $cititori_cu_imprumuturi) . " - au împrumuturi active!";
                $tip_mesaj = "danger";
            } else {
                // Șterge cititorii selectați
                $stmt_delete = $pdo->prepare("DELETE FROM cititori WHERE cod_bare IN ($placeholders)");
                $stmt_delete->execute($cititori_selectati);
                $numar_stersi = $stmt_delete->rowCount();
                
                $mesaj_stergere = "✅ Au fost șterși $numar_stersi cititori cu succes!";
                $tip_mesaj = "success";
                
                // Actualizează numărul total și reîncarcă lista
                $total_records = $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn();
                $total_pages = ceil($total_records / $records_per_page);
                
                // Reîncarcă cititorii
                $stmt->execute();
                $cititori = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $mesaj_stergere = "❌ Eroare la ștergere: " . $e->getMessage();
            $tip_mesaj = "danger";
        }
    }
}

// Funcție pentru generarea link-urilor de paginare
function generatePaginationLink($page_num, $current_page, $search = '') {
    $active_class = ($page_num == $current_page) ? 'active' : '';
    $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
    return "<a href=\"?page=$page_num$search_param\" class=\"$active_class\">$page_num</a>";
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T toți cititorii - Sistem Bibliotecă</title>
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
            max-width: 1400px;
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
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .home-btn, .back-btn {
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            font-weight: 600;
        }

        .home-btn {
            background: #28a745;
        }

        .home-btn:hover {
            background: #218838;
        }

        .back-btn {
            background: #667eea;
        }

        .back-btn:hover {
            background: #764ba2;
        }

        .stats {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        .stats h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }

        .action-btn {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            transition: background 0.3s;
        }

        .action-btn:hover {
            background: #218838;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.2em;
        }

        .reader-code {
            font-weight: bold;
            color: #667eea;
        }

        .reader-name {
            font-weight: 600;
            color: #333;
        }

        .contact-info {
            color: #666;
        }

        .contact-info a {
            color: #667eea;
            text-decoration: none;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        /* Stiluri pentru checkbox și ștergere */
        .checkbox-col {
            width: 40px;
            text-align: center;
        }

        .checkbox-col input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }

        /* Căutare rapidă */
        .search-box {
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 16px;
            font-size: 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .clear-search {
            padding: 12px 16px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .clear-search:hover {
            background: #c0392b;
        }

        .delete-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .delete-bar .selected-count {
            font-weight: 600;
            color: #667eea;
        }

        .btn-delete-selected {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete-selected:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .btn-delete-selected:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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
            border: 1px solid #ffeeba;
        }

        tr.selected {
            background: #e8f4fd !important;
        }

        /* Modal pentru autentificare admin */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            min-width: 380px;
            z-index: 1001;
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

        .modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .modal-header .warning-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .modal-header h3 {
            color: #667eea;
            font-size: 1.5em;
            margin-bottom: 8px;
        }

        .modal-header p {
            color: #666;
            font-size: 0.9em;
        }

        .modal-form-group {
            margin-bottom: 15px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
            font-size: 0.9em;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .modal-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-confirm:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #545b62;
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71d2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }

        .modal-confirm-content {
            border-top: 4px solid #dc3545;
        }

        .warning-delete {
            font-size: 4em !important;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .confirm-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .warning-badge {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            margin-bottom: 8px;
        }

        .confirm-warning p {
            margin: 0;
            color: #856404;
            font-size: 0.95em;
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
        <div class="header">
            <h1>👥 Toți cititorii înregistrați</h1>
		<div class="header-buttons">
			<a href="export.php?tip=cititori&format=excel" class="home-btn" style="background: #17a2b8;">📥 Export Excel</a>
			<a href="raport_prezenta.php" class="home-btn" style="background: #ffc107;">📈 Prezență</a>
			<a href="index.php" class="home-btn">🏠 Acasă</a>
			<a href="index.php" class="back-btn">← Înapoi la scanare</a>
		</div>
        </div>

        <div class="stats">
            <h2>Total: <?php echo number_format($total_records); ?> cititori<?php echo !empty($search) ? ' găsiți' : ''; ?></h2>
            <p>Afișate <?php echo $records_per_page; ?> înregistrări pe pagină</p>
        </div>

        <!-- Căutare rapidă -->
        <div class="search-box">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="🔍 Caută după cod, nume, prenume, email sau telefon..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="search-input" id="searchInput">
                <button type="submit" class="search-btn">🔍 Caută</button>
                <?php if (!empty($search)): ?>
                    <a href="cititori.php" class="clear-search">✕ Șterge filtru</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="content">
            <?php if (!empty($mesaj_stergere)): ?>
                <div class="alert alert-<?php echo $tip_mesaj; ?>">
                    <?php echo $mesaj_stergere; ?>
                </div>
            <?php endif; ?>

            <?php if (count($cititori) > 0): ?>
                <form method="POST" id="formStergere">
                    <?php echo campCSRF(); ?>
                    <!-- Bara de ștergere -->
                    <div class="delete-bar">
                        <div>
                            <span class="selected-count" id="selectedCount">0 cititori selectați</span>
                        </div>
                        <button type="button" class="btn-delete-selected" id="btnDelete" disabled onclick="solicitaAutentificare()">
                            🗑️ Șterge selectați
                        </button>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-col">
                                    <input type="checkbox" id="selectAll" title="Selectează tot">
                                </th>
                                <th>Cod</th>
                                <th>Nume</th>
                                <th>Prenume</th>
                                <th>Telefon</th>
                                <th>Email</th>
                                <th>Dată înregistrare</th>
                                <th>Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cititori as $cititor): ?>
                                <tr>
                                    <td class="checkbox-col">
                                        <input type="checkbox" name="cititori_selectati[]" value="<?php echo htmlspecialchars($cititor['cod_bare']); ?>" class="cititor-checkbox">
                                    </td>
                                    <td><span class="reader-code"><?php echo htmlspecialchars($cititor['cod_bare']); ?></span></td>
                                    <td><span class="reader-name"><?php echo htmlspecialchars($cititor['nume']); ?></span></td>
                                    <td><?php echo htmlspecialchars($cititor['prenume']); ?></td>
                                    <td class="contact-info">
                                        <?php if ($cititor['telefon']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($cititor['telefon']); ?>">
                                                <?php echo htmlspecialchars($cititor['telefon']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="contact-info">
                                        <?php if ($cititor['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($cititor['email']); ?>">
                                                <?php echo htmlspecialchars($cititor['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($cititor['data_inregistrare'])); ?></td>
                                    <td><a href="editare_cititor.php?id=<?php echo $cititor['id']; ?>" class="action-btn">✏️ Modifica</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <!-- Paginare -->
                <div class="pagination">
                    <?php 
                    $search_url_param = !empty($search) ? '&search=' . urlencode($search) : '';
                    if ($prev_page): ?>
                        <a href="?page=<?php echo $prev_page . $search_url_param; ?>">&laquo; Anterior</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Anterior</span>
                    <?php endif; ?>

                    <?php
                    // Afișează maxim 5 pagini în jurul paginii curente
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo generatePaginationLink(1, $page, $search);
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo generatePaginationLink($i, $page, $search);
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo generatePaginationLink($total_pages, $page, $search);
                    }
                    ?>

                    <?php if ($next_page): ?>
                        <a href="?page=<?php echo $next_page . $search_url_param; ?>">Următor &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Următor &raquo;</span>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="no-data">📭 Nu există cititori înregistrați</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pentru confirmare ștergere -->
    <div id="modalConfirm" class="modal-overlay" onclick="inchideModalConfirm()">
        <div class="modal-content modal-confirm-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="warning-icon warning-delete">⚠️</div>
                <h3>Confirmare Ștergere</h3>
                <p id="confirmMessage">Ești sigur că vrei să ștergi cititorii selectați?</p>
            </div>
            
            <div class="confirm-warning">
                <span class="warning-badge">🚨 Atenție!</span>
                <p>Această acțiune este <strong>ireversibilă</strong>!</p>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="modal-btn btn-delete" onclick="procedeazaLaAutentificare()">
                    🗑️ Da, șterge
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="inchideModalConfirm()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pentru autentificare admin -->
    <div id="modalOverlay" class="modal-overlay" onclick="inchideModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="warning-icon">🔐</div>
                <h3>Autentificare Administrator</h3>
                <p>Introduceți credențialele pentru a șterge cititorii selectați</p>
            </div>
            
            <div class="modal-form-group">
                <label for="adminUsername">Nume utilizator</label>
                <input type="text" id="adminUsername" class="modal-input" 
                       placeholder="Introdu numele de utilizator" autocomplete="username">
            </div>
            
            <div class="modal-form-group">
                <label for="adminPassword">Parolă administrator</label>
                <input type="password" id="adminPassword" class="modal-input" 
                       placeholder="Introdu parola" autocomplete="current-password"
                       onkeypress="if(event.key === 'Enter') confirmaStergere()">
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="modal-btn btn-confirm" onclick="confirmaStergere()">
                    ✓ Confirmă
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="inchideModal()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>

    <script>
        // Selectează toate elementele
        const selectAllCheckbox = document.getElementById('selectAll');
        const cititorCheckboxes = document.querySelectorAll('.cititor-checkbox');
        const selectedCountSpan = document.getElementById('selectedCount');
        const btnDelete = document.getElementById('btnDelete');

        // Funcție pentru actualizarea numărului de selectați
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.cititor-checkbox:checked');
            const count = checked.length;
            
            selectedCountSpan.textContent = count + ' cititor' + (count !== 1 ? 'i' : '') + ' selectat' + (count !== 1 ? 'i' : '');
            btnDelete.disabled = count === 0;
            
            // Actualizează starea "select all"
            if (cititorCheckboxes.length > 0) {
                selectAllCheckbox.checked = count === cititorCheckboxes.length;
                selectAllCheckbox.indeterminate = count > 0 && count < cititorCheckboxes.length;
            }

            // Evidențiază rândurile selectate
            cititorCheckboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (cb.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        // Event listener pentru "Select All"
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                cititorCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateSelectedCount();
            });
        }

        // Event listener pentru fiecare checkbox
        cititorCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        // Funcție pentru a deschide modal-ul de confirmare
        function solicitaAutentificare() {
            const checked = document.querySelectorAll('.cititor-checkbox:checked');
            const count = checked.length;
            
            if (count === 0) {
                afiseazaAlerta('❌ Nu ai selectat niciun cititor!');
                return false;
            }
            
            // Actualizează mesajul în modal
            const message = count === 1 
                ? 'Ești sigur că vrei să ștergi acest cititor?'
                : `Ești sigur că vrei să ștergi ${count} cititori?`;
            
            document.getElementById('confirmMessage').textContent = message;
            
            // Deschide modal-ul de confirmare
            document.getElementById('modalConfirm').style.display = 'block';
            
            return false; // Previne submit-ul direct
        }

        // Funcție pentru a afișa alertă stilizată (opțional)
        function afiseazaAlerta(mesaj) {
            alert(mesaj); // Poți înlocui cu un modal personalizat
        }

        // Funcție pentru a proceda la autentificare după confirmare
        function procedeazaLaAutentificare() {
            inchideModalConfirm();
            // Deschide modal-ul pentru autentificare
            document.getElementById('modalOverlay').style.display = 'block';
            setTimeout(() => {
                document.getElementById('adminUsername').focus();
            }, 100);
        }

        // Funcție pentru închiderea modal-ului de confirmare
        function inchideModalConfirm() {
            document.getElementById('modalConfirm').style.display = 'none';
        }

        // Funcție pentru confirmare ștergere după autentificare
        function confirmaStergere() {
            const username = document.getElementById('adminUsername').value.trim();
            const password = document.getElementById('adminPassword').value;
            
            if (!username) {
                alert('❌ Te rog să introduci numele de utilizator!');
                document.getElementById('adminUsername').focus();
                return;
            }
            
            if (!password) {
                alert('❌ Te rog să introduci parola!');
                document.getElementById('adminPassword').focus();
                return;
            }
            
            // Adaugă câmpurile ascunse la formular
            const form = document.getElementById('formStergere');
            
            // Creează sau actualizează input-urile ascunse
            let usernameInput = form.querySelector('input[name="admin_username"]');
            if (!usernameInput) {
                usernameInput = document.createElement('input');
                usernameInput.type = 'hidden';
                usernameInput.name = 'admin_username';
                form.appendChild(usernameInput);
            }
            usernameInput.value = username;
            
            let passwordInput = form.querySelector('input[name="admin_password"]');
            if (!passwordInput) {
                passwordInput = document.createElement('input');
                passwordInput.type = 'hidden';
                passwordInput.name = 'admin_password';
                form.appendChild(passwordInput);
            }
            passwordInput.value = password;
            
            // Adaugă câmpul pentru a activa ștergerea
            let stergeInput = form.querySelector('input[name="sterge_selectati"]');
            if (!stergeInput) {
                stergeInput = document.createElement('input');
                stergeInput.type = 'hidden';
                stergeInput.name = 'sterge_selectati';
                stergeInput.value = '1';
                form.appendChild(stergeInput);
            }
            
            // Închide modal-ul și trimite formularul
            inchideModal();
            form.submit();
        }

        // Funcție pentru închiderea modal-ului
        function inchideModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('adminUsername').value = '';
            document.getElementById('adminPassword').value = '';
        }

        // Închide modal cu ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                inchideModal();
                inchideModalConfirm();
            }
        });

        // Inițializare
        updateSelectedCount();
    </script>
</body>
</html>
