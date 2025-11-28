<?php
// istoric_imprumuturi.php - Istoric complet al împrumuturilor cu căutare și filtre
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Configurare paginare
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Parametri de căutare și filtrare
$cautare = isset($_GET['cautare']) ? trim($_GET['cautare']) : '';
$filtru_status = isset($_GET['status']) ? $_GET['status'] : 'toate';
$filtru_perioada = isset($_GET['perioada']) ? $_GET['perioada'] : 'toate';

$valid_statuses = ['toate', 'active', 'returnate', 'anulate'];
if (!in_array($filtru_status, $valid_statuses)) {
    $filtru_status = 'toate';
}

$valid_perioade = ['toate', 'azi', 'saptamana', 'luna', 'anul', 'custom'];
if (!in_array($filtru_perioada, $valid_perioade)) {
    $filtru_perioada = 'toate';
}

$data_inceput = isset($_GET['data_inceput']) ? $_GET['data_inceput'] : '';
$data_sfarsit = isset($_GET['data_sfarsit']) ? $_GET['data_sfarsit'] : '';

// Construiește WHERE clause
$where_conditions = [];
$params = [];

// Filtru status
if ($filtru_status === 'active') {
    $where_conditions[] = "i.data_returnare IS NULL";
} elseif ($filtru_status === 'returnate') {
    $where_conditions[] = "i.data_returnare IS NOT NULL";
} elseif ($filtru_status === 'anulate') {
    $where_conditions[] = "i.status = 'anulat'";
}

// Filtru perioadă
if ($filtru_perioada === 'azi') {
    $where_conditions[] = "DATE(i.data_imprumut) = CURDATE()";
} elseif ($filtru_perioada === 'saptamana') {
    $where_conditions[] = "i.data_imprumut >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filtru_perioada === 'luna') {
    $where_conditions[] = "i.data_imprumut >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($filtru_perioada === 'anul') {
    $where_conditions[] = "i.data_imprumut >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
} elseif ($filtru_perioada === 'custom' && $data_inceput && $data_sfarsit) {
    $where_conditions[] = "DATE(i.data_imprumut) BETWEEN ? AND ?";
    $params[] = $data_inceput;
    $params[] = $data_sfarsit;
}

// Căutare
if (!empty($cautare)) {
    $where_conditions[] = "(c.titlu LIKE ? OR c.autor LIKE ? OR c.cod_bare LIKE ? OR 
                          cit.nume LIKE ? OR cit.prenume LIKE ? OR cit.cod_bare LIKE ?)";
    $search_term = "%$cautare%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obține numărul total de împrumuturi
$count_query = "
    SELECT COUNT(*) 
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    $where_clause
";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Obține împrumuturile
$query = "
    SELECT 
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        i.data_returnare,
        i.status,
        c.titlu,
        c.autor,
        c.cod_bare as cod_carte_bare,
        cit.nume,
        cit.prenume,
        cit.cod_bare as cod_cititor_bare,
        DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut) as durata_zile
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    $where_clause
    ORDER BY i.data_imprumut DESC
    LIMIT ? OFFSET ?
";
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
foreach ($params as $index => $param) {
    if (is_int($param)) {
        $stmt->bindValue($index + 1, $param, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($index + 1, $param);
    }
}
$stmt->execute();
$imprumuturi = $stmt->fetchAll();

// Calculează statistici
$stats = [
    'toate' => $pdo->query("SELECT COUNT(*) FROM imprumuturi")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NULL")->fetchColumn(),
    'returnate' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NOT NULL")->fetchColumn(),
    'anulate' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'anulat'")->fetchColumn()
];

// Funcție pentru generarea link-urilor de paginare
function generatePaginationLink($page_num, $current_page, $params) {
    $active_class = ($page_num == $current_page) ? 'active' : '';
    $query_string = http_build_query($params);
    return "<a href=\"?page=$page_num&$query_string\" class=\"$active_class\">$page_num</a>";
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Istoric Împrumuturi - Sistem Bibliotecă</title>
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
            max-width: 1600px;
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

        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .filter-section {
            margin-bottom: 20px;
        }

        .filter-section:last-child {
            margin-bottom: 0;
        }

        .filter-section h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .search-box input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-box button:hover {
            background: #764ba2;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .filter-btn.active {
            background: #667eea;
            color: white;
        }

        .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-range input {
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }

        .content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow-x: auto;
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
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-activ {
            background: #f8d7da;
            color: #721c24;
        }

        .status-returnat {
            background: #d4edda;
            color: #155724;
        }

        .status-anulat {
            background: #fff3cd;
            color: #856404;
        }

        .link-cititor, .link-carte {
            color: #667eea;
            text-decoration: underline;
            transition: opacity 0.3s;
        }

        .link-cititor:hover, .link-carte:hover {
            opacity: 0.7;
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
            <h1>📋 Istoric Împrumuturi</h1>
            <div class="header-buttons">
                <a href="index.php" class="home-btn">🏠 Acasă</a>
                <a href="carti.php" class="back-btn">← Înapoi la cărți</a>
            </div>
        </div>

        <div class="stats">
            <h2>Total: <?php echo number_format($total_records); ?> împrumuturi</h2>
            <p>Afișate <?php echo $records_per_page; ?> înregistrări pe pagină</p>
        </div>

        <!-- Filtre și căutare -->
        <div class="filters">
            <!-- Căutare -->
            <div class="filter-section">
                <h3>🔍 Căutare</h3>
                <form method="GET" class="search-box">
                    <input type="text" 
                           name="cautare" 
                           placeholder="Caută după titlu carte, autor, cod bare, nume cititor..." 
                           value="<?php echo htmlspecialchars($cautare); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtru_status); ?>">
                    <input type="hidden" name="perioada" value="<?php echo htmlspecialchars($filtru_perioada); ?>">
                    <button type="submit">🔍 Caută</button>
                    <?php if ($cautare): ?>
                        <a href="?" style="padding: 12px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">✕ Șterge</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filtru status -->
            <div class="filter-section">
                <h3>📊 Filtrează după status:</h3>
                <div class="filter-buttons">
                    <a href="?status=toate&perioada=<?php echo $filtru_perioada; ?><?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_status === 'toate' ? 'active' : ''; ?>">
                        📚 Toate (<?php echo number_format($stats['toate']); ?>)
                    </a>
                    <a href="?status=active&perioada=<?php echo $filtru_perioada; ?><?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_status === 'active' ? 'active' : ''; ?>">
                        📖 Active (<?php echo number_format($stats['active']); ?>)
                    </a>
                    <a href="?status=returnate&perioada=<?php echo $filtru_perioada; ?><?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_status === 'returnate' ? 'active' : ''; ?>">
                        ✅ Returnate (<?php echo number_format($stats['returnate']); ?>)
                    </a>
                    <a href="?status=anulate&perioada=<?php echo $filtru_perioada; ?><?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_status === 'anulate' ? 'active' : ''; ?>">
                        🚫 Anulate (<?php echo number_format($stats['anulate']); ?>)
                    </a>
                </div>
            </div>

            <!-- Filtru perioadă -->
            <div class="filter-section">
                <h3>📅 Filtrează după perioadă:</h3>
                <div class="filter-buttons">
                    <a href="?status=<?php echo $filtru_status; ?>&perioada=toate<?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_perioada === 'toate' ? 'active' : ''; ?>">
                        📆 Toate
                    </a>
                    <a href="?status=<?php echo $filtru_status; ?>&perioada=azi<?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_perioada === 'azi' ? 'active' : ''; ?>">
                        📅 Astăzi
                    </a>
                    <a href="?status=<?php echo $filtru_status; ?>&perioada=saptamana<?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_perioada === 'saptamana' ? 'active' : ''; ?>">
                        📅 Săptămâna trecută
                    </a>
                    <a href="?status=<?php echo $filtru_status; ?>&perioada=luna<?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_perioada === 'luna' ? 'active' : ''; ?>">
                        📅 Luna trecută
                    </a>
                    <a href="?status=<?php echo $filtru_status; ?>&perioada=anul<?php echo $cautare ? '&cautare=' . urlencode($cautare) : ''; ?>" 
                       class="filter-btn <?php echo $filtru_perioada === 'anul' ? 'active' : ''; ?>">
                        📅 Anul trecut
                    </a>
                </div>
                <div style="margin-top: 15px;">
                    <form method="GET" class="date-range">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtru_status); ?>">
                        <input type="hidden" name="perioada" value="custom">
                        <?php if ($cautare): ?>
                            <input type="hidden" name="cautare" value="<?php echo htmlspecialchars($cautare); ?>">
                        <?php endif; ?>
                        <label>De la:</label>
                        <input type="date" name="data_inceput" value="<?php echo htmlspecialchars($data_inceput); ?>" required>
                        <label>Până la:</label>
                        <input type="date" name="data_sfarsit" value="<?php echo htmlspecialchars($data_sfarsit); ?>" required>
                        <button type="submit" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">Filtrează</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if (count($imprumuturi) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data împrumut</th>
                            <th>Cititor</th>
                            <th>Carte</th>
                            <th>Autor</th>
                            <th>Data returnare</th>
                            <th>Durată</th>
                            <th>Status</th>
                            <th>Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imprumuturi as $imprumut): 
                            $status = $imprumut['data_returnare'] ? 'returnat' : ($imprumut['status'] === 'anulat' ? 'anulat' : 'activ');
                            $status_text = $status === 'activ' ? 'Activ' : ($status === 'returnat' ? 'Returnat' : 'Anulat');
                            $status_class = "status-$status";
                            
                            // Obține ID-ul cititorului pentru link
                            $stmt_cititor_id = $pdo->prepare("SELECT id FROM cititori WHERE cod_bare = ?");
                            $stmt_cititor_id->execute([$imprumut['cod_cititor_bare']]);
                            $cititor_id = $stmt_cititor_id->fetchColumn();
                            
                            // Obține ID-ul împrumutului pentru link
                            $imprumut_id = $imprumut['id'];
                        ?>
                            <tr>
                                <td><?php echo date('d.m.Y H:i', strtotime($imprumut['data_imprumut'])); ?></td>
                                <td>
                                    <?php if ($cititor_id): ?>
                                        <a href="editare_cititor.php?id=<?php echo $cititor_id; ?>" class="link-cititor">
                                            <?php echo htmlspecialchars($imprumut['nume'] . ' ' . $imprumut['prenume']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($imprumut['nume'] . ' ' . $imprumut['prenume']); ?>
                                    <?php endif; ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($imprumut['cod_cititor_bare']); ?></small>
                                </td>
                                <td>
                                    <a href="editare_imprumut.php?id=<?php echo $imprumut_id; ?>" class="link-carte">
                                        <?php echo htmlspecialchars($imprumut['titlu']); ?>
                                    </a>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($imprumut['cod_carte_bare']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($imprumut['autor'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($imprumut['data_returnare']): ?>
                                        <?php echo date('d.m.Y H:i', strtotime($imprumut['data_returnare'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $durata = (int)$imprumut['durata_zile'];
                                    if ($durata == 0) {
                                        echo "Astăzi";
                                    } elseif ($durata == 1) {
                                        echo "1 zi";
                                    } else {
                                        echo "$durata zile";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="editare_imprumut.php?id=<?php echo $imprumut_id; ?>" 
                                       style="padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;">
                                        ✏️ Modifică
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginare -->
                <div class="pagination">
                    <?php 
                    $pagination_params = [
                        'status' => $filtru_status,
                        'perioada' => $filtru_perioada
                    ];
                    if ($cautare) {
                        $pagination_params['cautare'] = $cautare;
                    }
                    if ($filtru_perioada === 'custom' && $data_inceput && $data_sfarsit) {
                        $pagination_params['data_inceput'] = $data_inceput;
                        $pagination_params['data_sfarsit'] = $data_sfarsit;
                    }
                    
                    $prev_page = $page > 1 ? $page - 1 : null;
                    $next_page = $page < $total_pages ? $page + 1 : null;
                    ?>
                    <?php if ($prev_page): ?>
                        <a href="?page=<?php echo $prev_page; ?>&<?php echo http_build_query($pagination_params); ?>">&laquo; Anterior</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Anterior</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo generatePaginationLink(1, $page, $pagination_params);
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo generatePaginationLink($i, $page, $pagination_params);
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo generatePaginationLink($total_pages, $page, $pagination_params);
                    }
                    ?>

                    <?php if ($next_page): ?>
                        <a href="?page=<?php echo $next_page; ?>&<?php echo http_build_query($pagination_params); ?>">Următor &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Următor &raquo;</span>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="no-data">📭 Nu există împrumuturi care să corespundă criteriilor de căutare</div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>
</body>
</html>




