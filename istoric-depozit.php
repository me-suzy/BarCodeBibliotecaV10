<?php
/**
 * Pagină pentru istoricul cărților preluate din depozit
 * Afișează toate cărțile care au fost preluate sau livrate din depozit
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

// Obține parametrii de căutare
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$data_start = isset($_GET['data_start']) ? trim($_GET['data_start']) : '';
$data_end = isset($_GET['data_end']) ? trim($_GET['data_end']) : '';

// Construiește query-ul cu filtre
$query = "
    SELECT 
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        i.data_scadenta,
        i.status_depozit,
        i.data_returnare,
        c.titlu,
        c.autor,
        c.cod_bare,
        c.cota,
        cit.nume,
        cit.prenume,
        cit.telefon,
        cit.email,
        TIMESTAMPDIFF(DAY, i.data_imprumut, COALESCE(i.data_returnare, NOW())) as zile_total
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.status_depozit IN ('preluata', 'livrata')
";

// Adaugă filtre pentru căutare
$params = [];
if (!empty($search_term)) {
    $query .= " AND (
        c.titlu LIKE ? OR 
        c.autor LIKE ? OR 
        c.cod_bare LIKE ? OR 
        c.cota LIKE ? OR 
        cit.nume LIKE ? OR 
        cit.prenume LIKE ? OR 
        cit.telefon LIKE ? OR
        CONCAT(cit.nume, ' ', cit.prenume) LIKE ?
    )";
    $search_pattern = '%' . $search_term . '%';
    $params = array_merge($params, [$search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
}

// Adaugă filtre pentru interval de date (după data_imprumut - data preluare)
if (!empty($data_start)) {
    $query .= " AND DATE(i.data_imprumut) >= ?";
    $params[] = $data_start;
}
if (!empty($data_end)) {
    $query .= " AND DATE(i.data_imprumut) <= ?";
    $params[] = $data_end;
}

$query .= " ORDER BY 
    CASE i.status_depozit 
        WHEN 'livrata' THEN 1 
        WHEN 'preluata' THEN 2 
        ELSE 3 
    END,
    i.data_imprumut DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$istoric = $stmt->fetchAll(PDO::FETCH_ASSOC);

$numar_preluate = count(array_filter($istoric, function($i) { return $i['status_depozit'] === 'preluata'; }));
$numar_livrate = count(array_filter($istoric, function($i) { return $i['status_depozit'] === 'livrata'; }));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Istoric Cărți Depozit</title>
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
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 3em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
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
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 1.1em;
        }

        .istoric-list {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .istoric-item {
            background: #f8f9fa;
            border-left: 6px solid #17a2b8;
            padding: 20px;
            padding-left: 50px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
        }

        .istoric-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .istoric-item.livrata {
            border-left-color: #28a745;
            background: #d4edda;
        }

        .istoric-item.preluata {
            border-left-color: #17a2b8;
            background: #d1ecf1;
        }

        .istoric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .istoric-header h3 {
            color: #333;
            font-size: 1.5em;
        }

        .istoric-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .status-livrata {
            background: #28a745;
            color: white;
        }

        .status-preluata {
            background: #17a2b8;
            color: white;
        }

        .istoric-info {
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
            color: #667eea;
            display: block;
            margin-bottom: 5px;
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
            line-height: 1.5;
            box-sizing: border-box;
            vertical-align: top;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .home-btn {
            background: #28a745;
            color: white;
        }

        .home-btn:hover {
            background: #218838;
        }

        .btn-istoric {
            background: #17a2b8;
            color: white;
        }

        .btn-istoric:hover {
            background: #138496;
        }

        /* Uniformizare butoane sus (Acasă, Înapoi, Istoric) */
        .btn.home-btn,
        .btn.btn-back,
        .btn.btn-istoric {
            padding: 14px 28px !important;   /* mărime egală */
            line-height: 1.5 !important;
            font-weight: 600;
            font-size: 1em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        /* Ajustare hover consistentă */
        .btn.btn-istoric:hover {
            background: #0a58ca; /* puțin mai închis */
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h2 {
            font-size: 2em;
            margin-bottom: 10px;
            color: #667eea;
        }

        .data-preluare {
            font-weight: bold;
            color: #17a2b8;
        }

        .data-livrare {
            font-weight: bold;
            color: #28a745;
        }

        .search-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 8px;
            font-size: 0.95em;
        }

        .form-group input {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-search {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-search:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }

        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Istoric Cărți Depozit</h1>
            
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo count($istoric); ?></h3>
                    <p>Total Înregistrări</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $numar_preluate; ?></h3>
                    <p>Preluate</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $numar_livrate; ?></h3>
                    <p>Livrate</p>
                </div>
            </div>
        </div>

        <div class="search-container">
            <form method="GET" action="istoric-depozit.php" class="search-form">
                <div class="form-group">
                    <label for="search">🔍 Căutare (titlu, autor, cotă, cod, cititor):</label>
                    <input type="text" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Caută după orice câmp...">
                </div>
                <div class="form-group">
                    <label for="data_start">📅 De la data:</label>
                    <input type="date" id="data_start" name="data_start" 
                           value="<?php echo htmlspecialchars($data_start, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="data_end">📅 Până la data:</label>
                    <input type="date" id="data_end" name="data_end" 
                           value="<?php echo htmlspecialchars($data_end, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-search">🔍 Caută</button>
                    <a href="istoric-depozit.php" class="btn-reset">🔄 Reset</a>
                </div>
            </form>
        </div>

        <div class="istoric-list">
            <?php if (empty($istoric)): ?>
            <div class="empty-state">
                <h2>📭 Nu există înregistrări</h2>
                <p>Nu au fost preluate cărți din depozit încă.</p>
            </div>
            <?php else: ?>
            <form id="exportForm" method="POST" action="">
                <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center;">
                    <button type="button" onclick="selectAll()" class="btn" style="background: #17a2b8; color: white;">☑ Selectează toate</button>
                    <button type="button" onclick="deselectAll()" class="btn" style="background: #6c757d; color: white;">☐ Deselectează toate</button>
                    <button type="submit" formaction="export_excel_istoric.php" class="btn" style="background: #28a745; color: white;">📊 Export Excel</button>
                    <button type="submit" formaction="export_pdf_istoric.php" class="btn" style="background: #dc3545; color: white;">📄 Export PDF</button>
                </div>
            <?php foreach ($istoric as $item): ?>
            <div class="istoric-item <?php echo $item['status_depozit']; ?>">
                <input type="checkbox" name="selected_ids[]" value="<?php echo $item['id']; ?>" 
                       style="position: absolute; top: 20px; left: 20px; width: 20px; height: 20px; cursor: pointer; z-index: 10; accent-color: #667eea;">
                <div class="istoric-header">
                    <h3>📚 <?php echo htmlspecialchars($item['titlu']); ?></h3>
                    <span class="istoric-status status-<?php echo $item['status_depozit']; ?>">
                        <?php 
                        echo $item['status_depozit'] === 'livrata' ? '✅ LIVRATĂ' : '📦 PRELUATĂ';
                        ?>
                    </span>
                </div>
                
                <div class="istoric-info">
                    <div class="info-item">
                        <strong>👤 Cititor:</strong>
                        <?php echo htmlspecialchars($item['nume'] . ' ' . $item['prenume']); ?>
                    </div>
                    <div class="info-item">
                        <strong>📖 Autor:</strong>
                        <?php echo htmlspecialchars($item['autor'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <strong>🔖 Cotă:</strong>
                        <?php echo htmlspecialchars($item['cota'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <strong>📍 Cod:</strong>
                        <?php echo htmlspecialchars($item['cod_bare']); ?>
                    </div>
                    <?php if (!empty($item['telefon'])): ?>
                    <div class="info-item">
                        <strong>📞 Telefon:</strong>
                        <a href="tel:<?php echo htmlspecialchars($item['telefon']); ?>">
                            <?php echo htmlspecialchars($item['telefon']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <strong>📅 Data împrumut:</strong>
                        <?php echo date('d.m.Y H:i', strtotime($item['data_imprumut'])); ?>
                    </div>
                    <?php if ($item['status_depozit'] === 'preluata'): ?>
                    <div class="info-item">
                        <strong class="data-preluare">📦 Data preluare:</strong>
                        <span class="data-preluare">
                            <?php 
                            // Data preluare = data_imprumut (când a fost marcată ca preluată)
                            // Sau putem folosi o coloană separată dacă există
                            echo date('d.m.Y H:i', strtotime($item['data_imprumut'])); 
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['status_depozit'] === 'livrata' && !empty($item['data_returnare'])): ?>
                    <div class="info-item">
                        <strong class="data-livrare">✅ Data livrare:</strong>
                        <span class="data-livrare">
                            <?php echo date('d.m.Y H:i', strtotime($item['data_returnare'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($item['data_scadenta'])): ?>
                    <div class="info-item">
                        <strong>⏰ Scadență:</strong>
                        <?php echo date('d.m.Y', strtotime($item['data_scadenta'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </form>
            <?php endif; ?>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="index.php" class="btn home-btn">🏠 Acasă</a>
                <a href="cerere-carti-depozit.php" class="btn btn-back">← Înapoi la Cereri</a>
            </div>
        </div>
    </div>

    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(cb => cb.checked = true);
        }

        function deselectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(cb => cb.checked = false);
        }

        // Trimite și parametrii de căutare la export
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            const form = e.target;
            const searchParams = new URLSearchParams(window.location.search);
            
            // Adaugă parametrii de căutare ca hidden inputs
            if (searchParams.get('search')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'search';
                input.value = searchParams.get('search');
                form.appendChild(input);
            }
            if (searchParams.get('data_start')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'data_start';
                input.value = searchParams.get('data_start');
                form.appendChild(input);
            }
            if (searchParams.get('data_end')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'data_end';
                input.value = searchParams.get('data_end');
                form.appendChild(input);
            }
        });
    </script>
</body>
</html>

