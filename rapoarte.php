<?php
// rapoarte.php - Vizualizare împrumuturi și rapoarte
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Obține împrumuturi active
$stmt = $pdo->query("
    SELECT
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        c.titlu,
        c.autor,
        c.locatie_completa,
        cit.nume,
        cit.prenume,
        cit.telefon,
        DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.status = 'activ'
    ORDER BY i.data_imprumut DESC
");
$imprumuturi_active = $stmt->fetchAll();

// Obține istoric returnări (ultimele 50)
$stmt = $pdo->query("
    SELECT
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        i.data_returnare,
        c.titlu,
        c.autor,
        cit.nume,
        cit.prenume,
        DATEDIFF(i.data_returnare, i.data_imprumut) as zile_total
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.status = 'returnat'
    ORDER BY i.data_returnare DESC
    LIMIT 50
");
$istoric = $stmt->fetchAll();

// Obține statistici generale
$statistici = [
    'total_carti' => $pdo->query("SELECT COUNT(*) FROM carti")->fetchColumn(),
    'total_cititori' => $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn(),
    'imprumuturi_active' => count($imprumuturi_active),
    'total_imprumuturi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi")->fetchColumn(),
    'carti_returnate' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'returnat'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapoarte Bibliotecă</title>
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
            margin: 0;
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

        /* SECȚIUNE NOUĂ - Meniu rapoarte avansate */
        .advanced-menu {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .advanced-menu h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .menu-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .menu-icon {
            font-size: 2.5em;
        }

        .menu-text h3 {
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .menu-text p {
            font-size: 0.85em;
            opacity: 0.9;
        }

        .export-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .export-section h2 {
            color: #667eea;
            margin-bottom: 20px;
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .export-btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .export-excel {
            background: #28a745;
            color: white;
        }

        .export-excel:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .export-pdf {
            background: #dc3545;
            color: white;
        }

        .export-pdf:hover {
            background: #c82333;
            transform: translateY(-2px);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .stat-card p {
            opacity: 0.9;
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

        .badge {
            padding: 5px 10px;
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

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.2em;
        }

        .location-info {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
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
            <h1>📊 Rapoarte Bibliotecă</h1>
            <div class="header-buttons">
                <a href="index.php" class="home-btn">🏠 Acasă</a>
                <a href="index.php" class="back-btn">← Înapoi la scanare</a>
            </div>
        </div>

        <!-- MENIU RAPOARTE AVANSATE -->
        <div class="advanced-menu">
            <h2>📈 Rapoarte Avansate & Analiză</h2>
            <div class="menu-grid">
                <a href="dashboard.php" class="menu-card">
                    <div class="menu-icon">📊</div>
                    <div class="menu-text">
                        <h3>Dashboard Interactiv</h3>
                        <p>Statistici vizuale & grafice</p>
                    </div>
                </a>

                <a href="raport_top_carti.php" class="menu-card">
                    <div class="menu-icon">🏆</div>
                    <div class="menu-text">
                        <h3>Top Cărți</h3>
                        <p>Cele mai împrumutate cărți</p>
                    </div>
                </a>

                <a href="raport_intarzieri.php" class="menu-card">
                    <div class="menu-icon">⏰</div>
                    <div class="menu-text">
                        <h3>Întârzieri</h3>
                        <p>Cititori cu cărți nereturnate</p>
                    </div>
                </a>

                <a href="sistem_notificari.php" class="menu-card">
                    <div class="menu-icon">📧</div>
                    <div class="menu-text">
                        <h3>Notificări</h3>
                        <p>Email & SMS automate</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- EXPORT DATE -->
        <div class="export-section">
            <h2>📥 Export Date</h2>
            <div class="export-buttons">
                <a href="export_excel.php?tip=active" class="export-btn export-excel">
                    📗 Export Excel - Împrumuturi Active
                </a>
                <a href="export_excel.php?tip=istoric" class="export-btn export-excel">
                    📗 Export Excel - Istoric Complet
                </a>
                <a href="export_pdf.php?tip=active" class="export-btn export-pdf">
                    📕 Export PDF - Împrumuturi Active
                </a>
                <a href="export_pdf.php?tip=statistica" class="export-btn export-pdf">
                    📕 Export PDF - Raport Statistic
                </a>
            </div>
        </div>

        <!-- Statistici generale -->
        <div class="section">
            <h2>📈 Statistici Generale</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $statistici['total_carti']; ?></h3>
                    <p>Total cărți în bibliotecă</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $statistici['total_cititori']; ?></h3>
                    <p>Cititori înregistrați</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $statistici['imprumuturi_active']; ?></h3>
                    <p>Împrumuturi active</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $statistici['total_imprumuturi']; ?></h3>
                    <p>Total împrumuturi</p>
                </div>
            </div>
        </div>

        <!-- Împrumuturi active -->
        <div class="section">
            <h2>📤 Împrumuturi active (<?php echo count($imprumuturi_active); ?>)</h2>
            <?php if (count($imprumuturi_active) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cititor</th>
                            <th>Telefon</th>
                            <th>Carte</th>
                            <th>Autor</th>
                            <th>Locație</th>
                            <th>Data împrumut</th>
                            <th>Zile împrumut</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imprumuturi_active as $imp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($imp['nume'] . ' ' . $imp['prenume']); ?></strong></td>
                                <td><?php echo htmlspecialchars($imp['telefon']); ?></td>
                                <td><?php echo htmlspecialchars($imp['titlu']); ?></td>
                                <td><?php echo htmlspecialchars($imp['autor']); ?></td>
                                <td><span class="location-info"><?php echo htmlspecialchars($imp['locatie_completa']); ?></span></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($imp['data_imprumut'])); ?></td>
                                <td><?php echo $imp['zile_imprumut']; ?> zile</td>
                                <td>
                                    <?php
                                    if ($imp['zile_imprumut'] > 30) {
                                        echo '<span class="badge badge-danger">Întârziere!</span>';
                                    } elseif ($imp['zile_imprumut'] > 14) {
                                        echo '<span class="badge badge-warning">Atenție</span>';
                                    } else {
                                        echo '<span class="badge badge-success">OK</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">🔭 Nu există împrumuturi active</div>
            <?php endif; ?>
        </div>

        <!-- Istoric returnări -->
        <div class="section">
            <h2>📥 Istoric returnări (ultimele 50)</h2>
            <?php if (count($istoric) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cititor</th>
                            <th>Carte</th>
                            <th>Autor</th>
                            <th>Data împrumut</th>
                            <th>Data returnare</th>
                            <th>Zile împrumut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($istoric as $ist): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ist['nume'] . ' ' . $ist['prenume']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ist['titlu']); ?></td>
                                <td><?php echo htmlspecialchars($ist['autor']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($ist['data_imprumut'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($ist['data_returnare'])); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $ist['zile_total']; ?> zile</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">🔭 Nu există istoric de returnări</div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>
</body>
</html>