<?php
// raport_top_carti.php - Top cărți cele mai împrumutate
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Perioada de filtrare
$perioada = $_GET['perioada'] ?? 'tot';
// Secțiunea de afișat
$sectiune = $_GET['sectiune'] ?? 'toate';

$where_clause = '';
switch ($perioada) {
    case 'luna':
        $where_clause = "WHERE i.data_imprumut >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case 'an':
        $where_clause = "WHERE i.data_imprumut >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'tot':
    default:
        $where_clause = '';
        break;
}

// Top cărți împrumutate
$stmt = $pdo->query("
    SELECT 
        c.cod_bare,
        c.titlu,
        c.autor,
        c.isbn,
        c.sectiune,
        c.locatie_completa,
        COUNT(i.id) as nr_imprumuturi,
        COUNT(CASE WHEN i.status = 'activ' THEN 1 END) as imprumuturi_active,
        MAX(i.data_imprumut) as ultima_imprumutare,
        AVG(DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut)) as medie_zile
    FROM carti c
    LEFT JOIN imprumuturi i ON c.cod_bare = i.cod_carte
    $where_clause
    GROUP BY c.cod_bare, c.titlu, c.autor, c.isbn, c.sectiune, c.locatie_completa
    HAVING nr_imprumuturi > 0
    ORDER BY nr_imprumuturi DESC
    LIMIT 50
");
$top_carti = $stmt->fetchAll();

// Cărți NICIODATĂ împrumutate
$stmt = $pdo->query("
    SELECT 
        c.cod_bare,
        c.titlu,
        c.autor,
        c.sectiune,
        c.data_adaugare
    FROM carti c
    LEFT JOIN imprumuturi i ON c.cod_bare = i.cod_carte
    WHERE i.id IS NULL
    ORDER BY c.data_adaugare DESC
");
$carti_neimprumutate = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Cărți - Raport</title>
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

        .btn {
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            font-weight: 600;
        }

        .btn-home {
            background: #28a745;
        }

        .btn-home:hover {
            background: #218838;
        }

        .btn-back {
            background: #667eea;
        }

        .btn-back:hover {
            background: #764ba2;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #667eea;
            color: white;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        tr:hover {
            background: #f8f9fa;
        }

        .rank {
            font-size: 1.5em;
            font-weight: bold;
        }

        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-popular {
            background: #d4edda;
            color: #155724;
        }

        .badge-trending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-new {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.2em;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-mini {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-mini h3 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .stat-mini p {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .stat-mini-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-mini-link:hover .stat-mini {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .stat-mini {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .stat-mini.active {
            box-shadow: 0 0 0 4px white, 0 0 0 6px #667eea;
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
            <h1>🏆 Top Cărți - Cele Mai Împrumutate</h1>
            <div class="header-buttons">
                <a href="index.php" class="btn btn-home">🏠 Acasă</a>
                <a href="rapoarte.php" class="btn btn-back">← Înapoi</a>
            </div>
        </div>

        <!-- Filtrare perioadă -->
        <div class="filter-section">
            <h3 style="margin-bottom: 15px; color: #667eea;">📅 Filtrare perioadă:</h3>
            <div class="filter-buttons">
                <a href="?perioada=luna" class="filter-btn <?php echo $perioada === 'luna' ? 'active' : ''; ?>">
                    Ultima lună
                </a>
                <a href="?perioada=an" class="filter-btn <?php echo $perioada === 'an' ? 'active' : ''; ?>">
                    Ultimul an
                </a>
                <a href="?perioada=tot" class="filter-btn <?php echo $perioada === 'tot' ? 'active' : ''; ?>">
                    Tot timpul
                </a>
            </div>
        </div>

        <!-- Statistici rapide (clickabile) -->
        <div class="stats-mini">
            <a href="?perioada=<?php echo $perioada; ?>&sectiune=imprumutate" class="stat-mini-link">
                <div class="stat-mini <?php echo $sectiune === 'imprumutate' ? 'active' : ''; ?>">
                    <h3><?php echo count($top_carti); ?></h3>
                    <p>📚 Cărți împrumutate</p>
                </div>
            </a>
            <a href="?perioada=<?php echo $perioada; ?>&sectiune=neimprumutate" class="stat-mini-link">
                <div class="stat-mini <?php echo $sectiune === 'neimprumutate' ? 'active' : ''; ?>">
                    <h3><?php echo count($carti_neimprumutate); ?></h3>
                    <p>😴 Cărți neîmprumutate</p>
                </div>
            </a>
            <a href="?perioada=<?php echo $perioada; ?>&sectiune=record" class="stat-mini-link">
                <div class="stat-mini <?php echo $sectiune === 'record' ? 'active' : ''; ?>">
                    <h3><?php echo $top_carti[0]['nr_imprumuturi'] ?? 0; ?></h3>
                    <p>🏆 Record împrumuturi</p>
                </div>
            </a>
        </div>
        
        <?php if ($sectiune !== 'toate'): ?>
        <div style="margin-bottom: 15px;">
            <a href="?perioada=<?php echo $perioada; ?>&sectiune=toate" class="btn btn-back" style="display: inline-block;">
                ← Arată toate secțiunile
            </a>
        </div>
        <?php endif; ?>

        <!-- Top cărți -->
        <?php if ($sectiune === 'toate' || $sectiune === 'imprumutate'): ?>
        <div class="section">
            <h2>🏆 Top 50 Cărți Cele Mai Împrumutate</h2>
            <?php if (count($top_carti) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Loc</th>
                            <th>Carte</th>
                            <th>Autor</th>
                            <th>Secțiune</th>
                            <th>Nr. Împrumuturi</th>
                            <th>Active acum</th>
                            <th>Ultima împrumutare</th>
                            <th>Medie zile</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_carti as $index => $carte): 
                            $loc = $index + 1;
                            $rank_class = '';
                            if ($loc === 1) $rank_class = 'rank-1';
                            elseif ($loc === 2) $rank_class = 'rank-2';
                            elseif ($loc === 3) $rank_class = 'rank-3';
                        ?>
                            <tr>
                                <td>
                                    <span class="rank <?php echo $rank_class; ?>">
                                        <?php 
                                        if ($loc === 1) echo '🥇';
                                        elseif ($loc === 2) echo '🥈';
                                        elseif ($loc === 3) echo '🥉';
                                        else echo '#' . $loc;
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($carte['titlu']); ?></strong></td>
                                <td><?php echo htmlspecialchars($carte['autor'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($carte['sectiune'] ?? '-'); ?></td>
                                <td>
                                    <strong style="color: #667eea; font-size: 1.2em;">
                                        <?php echo $carte['nr_imprumuturi']; ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($carte['imprumuturi_active'] > 0): ?>
                                        <span class="badge badge-trending">
                                            <?php echo $carte['imprumuturi_active']; ?> active
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($carte['ultima_imprumutare'])); ?></td>
                                <td><?php echo round($carte['medie_zile']); ?> zile</td>
                                <td>
                                    <?php if ($carte['nr_imprumuturi'] >= 10): ?>
                                        <span class="badge badge-popular">⭐ Popular</span>
                                    <?php elseif ($carte['imprumuturi_active'] > 0): ?>
                                        <span class="badge badge-trending">🔥 Trending</span>
                                    <?php else: ?>
                                        <span class="badge badge-new">📖 Normal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">📚 Nu există date pentru perioada selectată</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Cărți NICIODATĂ împrumutate -->
        <?php if (($sectiune === 'toate' || $sectiune === 'neimprumutate') && count($carti_neimprumutate) > 0): ?>
        <div class="section">
            <h2>😴 Cărți Niciodată Împrumutate (<?php echo count($carti_neimprumutate); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cod</th>
                        <th>Titlu</th>
                        <th>Autor</th>
                        <th>Secțiune</th>
                        <th>Data adăugare</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carti_neimprumutate as $carte): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($carte['cod_bare']); ?></td>
                            <td><strong><?php echo htmlspecialchars($carte['titlu']); ?></strong></td>
                            <td><?php echo htmlspecialchars($carte['autor'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($carte['sectiune'] ?? '-'); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($carte['data_adaugare'])); ?></td>
                            <td><span class="badge badge-danger">❌ Neîmprumutată</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Record împrumuturi (cartea cu cele mai multe împrumuturi) -->
        <?php if (($sectiune === 'toate' || $sectiune === 'record') && count($top_carti) > 0): 
            $record_carte = $top_carti[0];
        ?>
        <div class="section" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
            <h2>🏆 Record: Cartea cu Cele Mai Multe Împrumuturi</h2>
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 4em; margin-bottom: 20px;">🥇</div>
                <h3 style="font-size: 2em; color: #333; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($record_carte['titlu']); ?>
                </h3>
                <p style="font-size: 1.3em; color: #666; margin-bottom: 20px;">
                    de <?php echo htmlspecialchars($record_carte['autor'] ?? 'Autor necunoscut'); ?>
                </p>
                <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
                    <div style="background: white; padding: 20px 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3em; font-weight: bold; color: #667eea;"><?php echo $record_carte['nr_imprumuturi']; ?></div>
                        <div style="color: #666;">Împrumuturi totale</div>
                    </div>
                    <div style="background: white; padding: 20px 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3em; font-weight: bold; color: #28a745;"><?php echo $record_carte['imprumuturi_active']; ?></div>
                        <div style="color: #666;">Active acum</div>
                    </div>
                    <div style="background: white; padding: 20px 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <div style="font-size: 3em; font-weight: bold; color: #ffc107;"><?php echo round($record_carte['medie_zile']); ?></div>
                        <div style="color: #666;">Zile medie împrumut</div>
                    </div>
                </div>
                <p style="margin-top: 20px; color: #666;">
                    📍 Secțiune: <?php echo htmlspecialchars($record_carte['sectiune'] ?? '-'); ?> |
                    📅 Ultima împrumutare: <?php echo date('d.m.Y', strtotime($record_carte['ultima_imprumutare'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>
</body>
</html>