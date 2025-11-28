<?php
// imprumuturi.php - Lista împrumuturilor active cu paginare și grupare pe cititori
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Configurare paginare
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Funcție pentru căutare fără diacritice
function removeDiacriticsImprumuturi($str) {
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
    $search_normalized = strtolower(removeDiacriticsImprumuturi($search));
    $search_param = "%$search%";
    $search_normalized_param = "%$search_normalized%";
    
    // Căutare care funcționează și cu și fără diacritice
    $search_condition = "AND (
        cit.nume LIKE ? 
        OR cit.prenume LIKE ? 
        OR cit.cod_bare LIKE ? 
        OR c.titlu LIKE ? 
        OR c.autor LIKE ? 
        OR c.cod_bare LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(cit.nume), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(cit.prenume), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(c.titlu), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(c.autor), 'ă', 'a'), 'â', 'a'), 'î', 'i'), 'ș', 's'), 'ş', 's'), 'ț', 't'), 'ţ', 't'), 'ö', 'o') LIKE ?
    )";
    $search_params = [
        $search_param, $search_param, $search_param, $search_param, $search_param, $search_param,
        $search_normalized_param, $search_normalized_param, $search_normalized_param, $search_normalized_param
    ];
}

// Obține numărul total de împrumuturi active (doar cele nerețurnate)
$count_query = "
    SELECT COUNT(*) FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.data_returnare IS NULL $search_condition
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($search_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Obține împrumuturile active GRUPATE pe cititori (doar cele nerețurnate)
$query = "
    SELECT
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        c.titlu,
        c.autor,
        c.cod_bare,
        c.cota,
        c.locatie_completa,
        cit.id as cititor_id,
        cit.nume,
        cit.prenume,
        cit.telefon,
        cit.email,
        DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.data_returnare IS NULL $search_condition
    ORDER BY cit.nume, cit.prenume, i.data_imprumut DESC
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
$imprumuturi = $stmt->fetchAll();

// Numără cererile noi de depozit (status_depozit = 'cerere')
try {
    $pdo->exec("ALTER TABLE imprumuturi ADD COLUMN IF NOT EXISTS status_depozit ENUM('nu', 'cerere', 'preluata', 'livrata') DEFAULT 'nu'");
} catch (PDOException $e) {
    // Câmpul există deja sau altă eroare - continuă
}

$cereri_depozit_query = "
    SELECT COUNT(*) as numar_cereri
    FROM imprumuturi i
    WHERE i.data_returnare IS NULL 
    AND i.status_depozit = 'cerere'
";
$cereri_depozit_stmt = $pdo->query($cereri_depozit_query);
$numar_cereri_depozit = (int)$cereri_depozit_stmt->fetchColumn();

// Grupează împrumuturile pe cititori
$imprumuturi_grupate = [];
foreach ($imprumuturi as $imp) {
    $cod_cititor = $imp['cod_cititor'];
    if (!isset($imprumuturi_grupate[$cod_cititor])) {
        $imprumuturi_grupate[$cod_cititor] = [
            'cititor' => [
                'id' => $imp['cititor_id'],
                'cod_bare' => $imp['cod_cititor'],
                'nume' => $imp['nume'],
                'prenume' => $imp['prenume'],
                'telefon' => $imp['telefon'],
                'email' => $imp['email']
            ],
            'carti' => []
        ];
    }
    $imprumuturi_grupate[$cod_cititor]['carti'][] = $imp;
}

$prev_page = $page > 1 ? $page - 1 : null;
$next_page = $page < $total_pages ? $page + 1 : null;

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
    <title>Împrumuturi active - Sistem Bibliotecă</title>
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

        /* Căutare rapidă */
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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

        /* Stiluri pentru gruparea cititorilor */
        .cititor-group {
            border-top: 3px solid #667eea;
        }

        .cititor-row {
            background: #f0f4ff;
            font-weight: 600;
        }

        .cititor-info {
            vertical-align: top;
            background: #f0f4ff;
            border-right: 2px solid #667eea;
        }

        .carte-row {
            background: white;
        }

        .carte-row:hover {
            background: #f8f9fa;
        }

        .empty-cell {
            background: #f0f4ff;
            border-right: 2px solid #667eea;
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

        .book-code {
            font-weight: bold;
            color: #667eea;
        }

        .book-title {
            font-weight: 600;
            color: #333;
        }

        .reader-name {
            font-weight: 600;
            color: #28a745;
            font-size: 1.1em;
        }

        .reader-code {
            color: #667eea;
            font-size: 0.9em;
            font-weight: 600;
        }

        .location-info {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }

        .contact-info {
            color: #666;
            font-size: 0.95em;
        }

        .contact-info a {
            color: #667eea;
            text-decoration: none;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        .btn-view-profile {
            display: inline-block;
            padding: 6px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-view-profile:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .loan-date {
            color: #666;
            font-size: 0.9em;
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
            <h1>📖 Împrumuturi active</h1>
            <div class="header-buttons">
                <a href="cerere-carti-depozit.php" class="home-btn" style="background: #ff6b35; position: relative;" id="btn-depozit">
                    📦 Depozit
                    <?php if ($numar_cereri_depozit > 0): ?>
                    <span id="depozit-badge" style="position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;"><?php echo $numar_cereri_depozit > 9 ? '9+' : $numar_cereri_depozit; ?></span>
                    <?php endif; ?>
                </a>
                <a href="export.php?tip=imprumuturi&format=excel" class="home-btn" style="background: #17a2b8;">📥 Export Excel</a>
                <a href="index.php" class="home-btn">🏠 Acasă</a>
                <a href="index.php" class="back-btn">← Înapoi la scanare</a>
            </div>
        </div>

        <div class="stats">
            <h2>Total: <?php echo number_format($total_records); ?> împrumuturi active<?php echo !empty($search) ? ' găsite' : ''; ?></h2>
            <p>Afișate <?php echo $records_per_page; ?> înregistrări pe pagină</p>
        </div>

        <!-- Căutare rapidă -->
        <div class="search-box">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="🔍 Caută după cititor, titlu carte sau cod..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="search-input" id="searchInput">
                <button type="submit" class="search-btn">🔍 Caută</button>
                <?php if (!empty($search)): ?>
                    <a href="imprumuturi.php" class="clear-search">✕ Șterge filtru</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="content">
            <?php if (count($imprumuturi_grupate) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cititor</th>
                            <th>Contact</th>
                            <th>Carte</th>
                            <th>Autor</th>
                            <th>Locație</th>
                            <th>Data împrumut</th>
                            <th>Zile</th>
                            <th>Status</th>
                            <th>Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imprumuturi_grupate as $cod_cititor => $data): ?>
                            <?php $cititor = $data['cititor']; ?>
                            <?php $carti = $data['carti']; ?>
                            <?php $nr_carti = count($carti); ?>
                            
                            <?php foreach ($carti as $index => $carte): ?>
                                <tr class="<?php echo $index === 0 ? 'cititor-group' : ''; ?>">
                                    <?php if ($index === 0): ?>
                                        <!-- Informații cititor - doar pe primul rând -->
                                        <td class="cititor-info" rowspan="<?php echo $nr_carti; ?>">
                                            <div class="reader-name">
                                                <?php echo htmlspecialchars($cititor['nume'] . ' ' . $cititor['prenume']); ?>
                                            </div>
                                            <div class="reader-code"><?php echo htmlspecialchars($cititor['cod_bare']); ?></div>
                                            <div style="margin-top: 8px; font-weight: 600; color: #667eea; font-size: 1.1em;">
                                                📚 <?php echo $nr_carti; ?> <?php echo $nr_carti == 1 ? 'Carte' : 'Cărți'; ?>
                                            </div>
                                            <?php if (isset($cititor['id'])): ?>
                                                <div style="margin-top: 10px;">
                                                    <a href="editare_cititor.php?id=<?php echo $cititor['id']; ?>" 
                                                       class="btn-view-profile" 
                                                       title="Vizualizează profilul cititorului">
                                                        👁️ Vezi profil
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cititor-info contact-info" rowspan="<?php echo $nr_carti; ?>">
                                            <?php if ($cititor['telefon']): ?>
                                                <a href="tel:<?php echo htmlspecialchars($cititor['telefon']); ?>">
                                                    📞 <?php echo htmlspecialchars($cititor['telefon']); ?>
                                                </a><br>
                                            <?php endif; ?>
                                            <?php if ($cititor['email']): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($cititor['email']); ?>">
                                                    ✉️ <?php echo htmlspecialchars($cititor['email']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <!-- Informații carte -->
                                    <td class="carte-row">
                                        <div class="book-title"><?php echo htmlspecialchars($carte['titlu']); ?></div>
                                        <div class="book-code"><?php echo htmlspecialchars($carte['cod_bare'] ?: $carte['cod_carte']); ?></div>
                                        <?php if (!empty($carte['cota'])): ?>
                                            <div style="font-size: 0.9em; color: #28a745; margin-top: 4px;">
                                                📖 Cotă: <?php echo htmlspecialchars($carte['cota']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="carte-row"><?php echo htmlspecialchars($carte['autor'] ?: '-'); ?></td>
                                    <td class="carte-row">
                                        <?php if ($carte['locatie_completa']): ?>
                                            <span class="location-info"><?php echo htmlspecialchars($carte['locatie_completa']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="carte-row">
                                        <div><?php echo date('d.m.Y', strtotime($carte['data_imprumut'])); ?></div>
                                        <div class="loan-date"><?php echo date('H:i', strtotime($carte['data_imprumut'])); ?></div>
                                    </td>
                                    <td class="carte-row"><?php echo $carte['zile_imprumut']; ?> zile</td>
                                    <td class="carte-row">
                                        <?php
                                        if ($carte['zile_imprumut'] > 30) {
                                            echo '<span class="badge badge-danger">Întârziere!</span>';
                                        } elseif ($carte['zile_imprumut'] > 14) {
                                            echo '<span class="badge badge-warning">Atenție</span>';
                                        } else {
                                            echo '<span class="badge badge-success">OK</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="carte-row">
                                        <a href="editare_imprumut.php?id=<?php echo $carte['id']; ?>" class="action-btn">✏️ Modifică</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

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
                <div class="no-data">🔭 Nu există împrumuturi active<?php echo !empty($search) ? ' pentru căutarea "' . htmlspecialchars($search) . '"' : ''; ?></div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>
</body>
</html>