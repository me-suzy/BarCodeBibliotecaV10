<?php
// raport_intarzieri.php - Raport cititori cu întârzieri
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Cititori cu întârzieri (peste 14 zile) - include și statut
$stmt = $pdo->query("
    SELECT 
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        c.titlu,
        c.autor,
        cit.id as cititor_id,
        cit.nume,
        cit.prenume,
        cit.telefon,
        cit.email,
        cit.statut as statut_cititor,
        COALESCE(sc.nume_statut, 'Nespecificat') as nume_statut,
        DATEDIFF(NOW(), i.data_imprumut) as zile_intarziere
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare COLLATE utf8mb4_unicode_ci
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare COLLATE utf8mb4_unicode_ci
    LEFT JOIN statute_cititori sc ON cit.statut COLLATE utf8mb4_unicode_ci = sc.cod_statut COLLATE utf8mb4_unicode_ci
    WHERE i.status = 'activ' AND DATEDIFF(NOW(), i.data_imprumut) > 14
    ORDER BY zile_intarziere DESC
");
$intarzieri = $stmt->fetchAll();

// Grupare pe cititori
$cititori_intarziere = [];
foreach ($intarzieri as $int) {
    $cod = $int['cod_cititor'];
    if (!isset($cititori_intarziere[$cod])) {
        $cititori_intarziere[$cod] = [
            'id' => $int['cititor_id'],
            'cod_bare' => $cod,
            'nume' => $int['nume'],
            'prenume' => $int['prenume'],
            'telefon' => $int['telefon'],
            'email' => $int['email'],
            'statut' => $int['statut_cititor'],
            'nume_statut' => $int['nume_statut'],
            'carti' => [],
            'max_zile' => 0
        ];
    }
    $cititori_intarziere[$cod]['carti'][] = $int;
    if ($int['zile_intarziere'] > $cititori_intarziere[$cod]['max_zile']) {
        $cititori_intarziere[$cod]['max_zile'] = $int['zile_intarziere'];
    }
}

// Sortare după max zile întârziere
uasort($cititori_intarziere, function($a, $b) {
    return $b['max_zile'] - $a['max_zile'];
});

// Statistici
$total_intarzieri = count($intarzieri);
$total_cititori_intarziere = count($cititori_intarziere);
$max_intarziere = $intarzieri[0]['zile_intarziere'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Întârzieri</title>
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
            color: #dc3545;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2.5em;
            color: #dc3545;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
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
            border-bottom: 3px solid #dc3545;
        }

        .cititor-card {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .cititor-card:hover {
            border-color: #dc3545;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
        }

        .cititor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .cititor-info h3 {
            color: #333;
            font-size: 1.3em;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
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
        }

        .cititor-contact {
            font-size: 0.9em;
            color: #666;
        }

        .cititor-contact a {
            color: #667eea;
            text-decoration: none;
            margin-right: 15px;
        }

        .cititor-contact a:hover {
            text-decoration: underline;
        }

        .max-intarziere {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.2em;
        }

        .carti-list {
            list-style: none;
        }

        .carte-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .carte-info {
            flex: 1;
        }

        .carte-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .carte-author {
            color: #666;
            font-size: 0.9em;
        }

        .carte-date {
            font-size: 0.85em;
            color: #999;
        }

        .badge-intarziere {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .btn-email {
            background: #17a2b8;
            color: white;
        }

        .btn-email:hover {
            background: #138496;
        }

        .btn-sms {
            background: #28a745;
            color: white;
        }

        .btn-sms:hover {
            background: #218838;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #28a745;
        }

        .no-data h2 {
            font-size: 2em;
            margin-bottom: 10px;
            border: none;
        }

        .no-data p {
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
            <h1>⏰ Raport Întârzieri</h1>
            <div class="header-buttons">
                <a href="export.php?tip=intarzieri&format=excel" class="btn" style="background: #17a2b8; color: white;">📥 Export Excel</a>
                <a href="index.php" class="btn btn-home">🏠 Acasă</a>
                <a href="rapoarte.php" class="btn btn-back">← Înapoi</a>
            </div>
        </div>

        <!-- Statistici -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_intarzieri; ?></h3>
                <p>Total cărți întârziate</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_cititori_intarziere; ?></h3>
                <p>Cititori cu întârzieri</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $max_intarziere; ?></h3>
                <p>Întârziere maximă (zile)</p>
            </div>
        </div>

        <!-- Lista cititori cu întârzieri -->
        <div class="section">
            <h2>👥 Cititori cu Cărți Întârziate</h2>
            
            <?php if (count($cititori_intarziere) > 0): ?>
                <?php foreach ($cititori_intarziere as $cod_cititor => $cititor): ?>
                    <div class="cititor-card">
                        <div class="cititor-header">
                            <div class="cititor-info">
                                <h3>
                                    👤 <?php echo htmlspecialchars($cititor['nume'] . ' ' . $cititor['prenume']); ?>
                                    <?php if (isset($cititor['id'])): ?>
                                        <a href="editare_cititor.php?id=<?php echo $cititor['id']; ?>" 
                                           class="btn-view-profile" 
                                           title="Vizualizează profilul cititorului">
                                            👁️ Vezi profil
                                        </a>
                                    <?php endif; ?>
                                </h3>
                                <div class="cititor-details" style="font-size: 0.9em; color: #555; margin: 5px 0;">
                                    <span style="background: #e9ecef; padding: 2px 8px; border-radius: 4px; margin-right: 10px;">
                                        🔖 Cod: <strong><?php echo htmlspecialchars($cititor['cod_bare']); ?></strong>
                                    </span>
                                    <span style="background: #6c5ce7; color: white; padding: 2px 8px; border-radius: 4px;">
                                        🏷️ Statut <?php echo htmlspecialchars($cititor['statut']); ?>: <?php echo htmlspecialchars($cititor['nume_statut']); ?>
                                    </span>
                                </div>
                                <div class="cititor-contact">
                                    <?php if ($cititor['telefon']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($cititor['telefon']); ?>">
                                            📞 <?php echo htmlspecialchars($cititor['telefon']); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($cititor['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($cititor['email']); ?>">
                                            ✉️ <?php echo htmlspecialchars($cititor['email']); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="max-intarziere">
                                🚨 Max: <?php echo $cititor['max_zile']; ?> zile
                            </div>
                        </div>

                        <ul class="carti-list">
                            <?php foreach ($cititor['carti'] as $carte): ?>
                                <li class="carte-item">
                                    <div class="carte-info">
                                        <div class="carte-title">
                                            📕 <?php echo htmlspecialchars($carte['titlu']); ?>
                                        </div>
                                        <div class="carte-author">
                                            <?php echo htmlspecialchars($carte['autor'] ?? 'Autor necunoscut'); ?>
                                        </div>
                                        <div class="carte-date">
                                            Împrumutată: <?php echo date('d.m.Y', strtotime($carte['data_imprumut'])); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($carte['zile_intarziere'] > 30): ?>
                                            <span class="badge-intarziere badge-danger">
                                                🔴 <?php echo $carte['zile_intarziere']; ?> zile întârziere
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-intarziere badge-warning">
                                                🟡 <?php echo $carte['zile_intarziere']; ?> zile întârziere
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="action-buttons">
                            <a href="trimite_notificare.php?cod_cititor=<?php echo urlencode($cod_cititor); ?>&tip=email" 
                               class="action-btn btn-email">
                                📧 Trimite Email Reminder
                            </a>
                            <a href="trimite_notificare.php?cod_cititor=<?php echo urlencode($cod_cititor); ?>&tip=sms" 
                               class="action-btn btn-sms">
                                📱 Trimite SMS
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <h2>✅ Nicio întârziere!</h2>
                    <p>Toți cititorii au returnat cărțile la timp. Excelent! 🎉</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>
</body>
</html>