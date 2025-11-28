<?php
// dashboard.php - Dashboard interactiv cu statistici și grafice
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Statistici generale
$stats = [
    'total_carti' => $pdo->query("SELECT COUNT(*) FROM carti")->fetchColumn(),
    'total_cititori' => $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn(),
    'imprumuturi_active' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NULL")->fetchColumn(),
    'total_imprumuturi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi")->fetchColumn(),
    'total_returnari' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NOT NULL")->fetchColumn(),
    'intarzieri' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NULL AND DATEDIFF(NOW(), data_imprumut) > 14")->fetchColumn(),
    'cititori_noi_luna' => $pdo->query("SELECT COUNT(*) FROM cititori WHERE data_inregistrare >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn(),
    'carti_noi_luna' => $pdo->query("SELECT COUNT(*) FROM carti WHERE data_adaugare >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn(),
];

// Calculează rata de returnare la timp
$returnari_la_timp = $pdo->query("
    SELECT COUNT(*) FROM imprumuturi 
    WHERE data_returnare IS NOT NULL 
    AND DATEDIFF(data_returnare, data_imprumut) <= 14
")->fetchColumn();
$stats['rata_returnare_timp'] = $stats['total_returnari'] > 0 
    ? round(($returnari_la_timp / $stats['total_returnari']) * 100, 1) 
    : 0;

// Media zile împrumut
$media_zile = $pdo->query("
    SELECT AVG(DATEDIFF(COALESCE(data_returnare, NOW()), data_imprumut)) 
    FROM imprumuturi
")->fetchColumn();
$stats['media_zile_imprumut'] = round($media_zile ?? 0, 1);

// Împrumuturi pe lună (ultimele 12 luni)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(data_imprumut, '%Y-%m') as luna,
        COUNT(*) as nr_imprumuturi
    FROM imprumuturi
    WHERE data_imprumut >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(data_imprumut, '%Y-%m')
    ORDER BY luna ASC
");
$imprumuturi_pe_luna = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Completează lunile lipsă cu 0
$luni_labels = [];
$luni_data = [];
for ($i = 11; $i >= 0; $i--) {
    $luna = date('Y-m', strtotime("-$i months"));
    $luni_labels[] = date('M Y', strtotime("-$i months"));
    $luni_data[] = $imprumuturi_pe_luna[$luna] ?? 0;
}

// Top 5 cărți
$stmt = $pdo->query("
    SELECT c.titlu, COUNT(i.id) as nr
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    GROUP BY c.titlu
    ORDER BY nr DESC
    LIMIT 5
");
$top_carti = $stmt->fetchAll();

// Top 5 cititori
$stmt = $pdo->query("
    SELECT CONCAT(cit.nume, ' ', cit.prenume) as nume_complet, COUNT(i.id) as nr
    FROM imprumuturi i
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    GROUP BY cit.cod_bare, cit.nume, cit.prenume
    ORDER BY nr DESC
    LIMIT 5
");
$top_cititori = $stmt->fetchAll();

// Împrumuturi pe secțiune
$stmt = $pdo->query("
    SELECT 
        COALESCE(c.sectiune, 'Fără secțiune') as sectiune,
        COUNT(i.id) as nr
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    GROUP BY c.sectiune
    ORDER BY nr DESC
");
$imprumuturi_sectiune = $stmt->fetchAll();

// Activitate săptămânală (ultimele 7 zile)
$activitate_saptamana = [];
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days"));
    
    $imprumuturi = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_imprumut) = ?");
    $imprumuturi->execute([$data]);
    
    $returnari = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_returnare) = ?");
    $returnari->execute([$data]);
    
    $activitate_saptamana[] = [
        'label' => $label,
        'imprumuturi' => $imprumuturi->fetchColumn(),
        'returnari' => $returnari->fetchColumn()
    ];
}

// Top 5 cărți întârziate frecvent
$stmt = $pdo->query("
    SELECT 
        c.titlu, 
        c.autor,
        COUNT(*) as nr_intarzieri
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    WHERE DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut) > 14
    GROUP BY c.cod_bare, c.titlu, c.autor
    ORDER BY nr_intarzieri DESC
    LIMIT 5
");
$carti_intarziate_frecvent = $stmt->fetchAll();

// Cititori cu cele mai multe întârzieri
$stmt = $pdo->query("
    SELECT 
        CONCAT(cit.nume, ' ', cit.prenume) as nume_complet,
        cit.telefon,
        COUNT(*) as nr_intarzieri
    FROM imprumuturi i
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut) > 14
    GROUP BY cit.cod_bare, cit.nume, cit.prenume, cit.telefon
    ORDER BY nr_intarzieri DESC
    LIMIT 5
");
$cititori_intarzieri_frecvente = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bibliotecă</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3em;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-info h3 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 0.95em;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .chart-card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .chart-container {
            position: relative;
            height: 350px;
        }

        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .stats-table th,
        .stats-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .stats-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }

        .stats-table tr:hover {
            background: #f8f9fa;
        }

        .stats-table tr:last-child td {
            border-bottom: none;
        }

        .top-lists {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .top-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .top-card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .top-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .top-item:hover {
            background: #f8f9fa;
        }

        .top-item:last-child {
            border-bottom: none;
        }

        .top-rank {
            font-size: 1.5em;
            font-weight: bold;
            min-width: 40px;
        }

        .top-rank-1 { color: #ffd700; }
        .top-rank-2 { color: #c0c0c0; }
        .top-rank-3 { color: #cd7f32; }

        .top-name {
            flex: 1;
            margin: 0 15px;
        }

        .top-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .top-lists {
                grid-template-columns: 1fr;
            }
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
            <h1>📊 Dashboard Bibliotecă</h1>
            <div class="header-buttons">
                <a href="index.php" class="btn btn-home">🏠 Acasă</a>
                <a href="rapoarte.php" class="btn btn-back">← Înapoi</a>
            </div>
        </div>

        <!-- Statistici principale -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_carti']; ?></h3>
                    <p>Total Cărți</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_cititori']; ?></h3>
                    <p>Cititori Activi</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div class="stat-info">
                    <h3><?php echo $stats['imprumuturi_active']; ?></h3>
                    <p>Împrumuturi Active</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_imprumuturi']; ?></h3>
                    <p>Total Împrumuturi</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-info">
                    <h3 style="color: <?php echo $stats['intarzieri'] > 0 ? '#dc3545' : '#28a745'; ?>">
                        <?php echo $stats['intarzieri']; ?>
                    </h3>
                    <p>Întârzieri</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3 style="color: #28a745;"><?php echo $stats['rata_returnare_timp']; ?>%</h3>
                    <p>Returnări la timp</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <h3><?php echo $stats['media_zile_imprumut']; ?> zile</h3>
                    <p>Durată medie împrumut</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-info">
                    <h3><?php echo $stats['cititori_noi_luna']; ?></h3>
                    <p>Cititori noi (luna)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📖</div>
                <div class="stat-info">
                    <h3><?php echo $stats['carti_noi_luna']; ?></h3>
                    <p>Cărți noi (luna)</p>
                </div>
            </div>
        </div>

        <!-- Grafice -->
        <div class="charts-grid">
            <div class="chart-card">
                <h2>📈 Împrumuturi pe Lună (Ultimele 12 luni)</h2>
                <div class="chart-container">
                    <canvas id="chartLuni"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2>🎯 Împrumuturi pe Secțiune</h2>
                <div class="chart-container">
                    <canvas id="chartSectiuni"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2>📊 Activitate Săptămânală</h2>
                <div class="chart-container">
                    <canvas id="chartSaptamana"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabele cu statistici detaliate -->
        <div class="charts-grid" style="margin-top: 20px;">
            <div class="chart-card">
                <h2>⚠️ Cărți Întârziate Frecvent</h2>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Titlu</th>
                            <th>Autor</th>
                            <th>Nr. Întârzieri</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carti_intarziate_frecvent as $carte): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(mb_substr($carte['titlu'], 0, 30)) . (mb_strlen($carte['titlu']) > 30 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($carte['autor'] ?? '-'); ?></td>
                            <td style="text-align: center; color: #dc3545; font-weight: bold;"><?php echo $carte['nr_intarzieri']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($carti_intarziate_frecvent)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #28a745;">✅ Nu există cărți cu întârzieri frecvente</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="chart-card">
                <h2>👤 Cititori cu Întârzieri</h2>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Nume</th>
                            <th>Telefon</th>
                            <th>Nr. Întârzieri</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cititori_intarzieri_frecvente as $cititor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cititor['nume_complet']); ?></td>
                            <td><?php echo htmlspecialchars($cititor['telefon'] ?? '-'); ?></td>
                            <td style="text-align: center; color: #dc3545; font-weight: bold;"><?php echo $cititor['nr_intarzieri']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cititori_intarzieri_frecvente)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #28a745;">✅ Nu există cititori cu întârzieri frecvente</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Lists -->
        <div class="top-lists">
            <div class="top-card">
                <h2>🏆 Top 5 Cărți Cele Mai Împrumutate</h2>
                <?php foreach ($top_carti as $index => $carte): ?>
                    <div class="top-item">
                        <div class="top-rank top-rank-<?php echo $index + 1; ?>">
                            <?php 
                            if ($index === 0) echo '🥇';
                            elseif ($index === 1) echo '🥈';
                            elseif ($index === 2) echo '🥉';
                            else echo '#' . ($index + 1);
                            ?>
                        </div>
                        <div class="top-name"><?php echo htmlspecialchars($carte['titlu']); ?></div>
                        <div class="top-count"><?php echo $carte['nr']; ?> împrumuturi</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="top-card">
                <h2>👥 Top 5 Cititori Activi</h2>
                <?php foreach ($top_cititori as $index => $cititor): ?>
                    <div class="top-item">
                        <div class="top-rank top-rank-<?php echo $index + 1; ?>">
                            <?php 
                            if ($index === 0) echo '🥇';
                            elseif ($index === 1) echo '🥈';
                            elseif ($index === 2) echo '🥉';
                            else echo '#' . ($index + 1);
                            ?>
                        </div>
                        <div class="top-name"><?php echo htmlspecialchars($cititor['nume_complet']); ?></div>
                        <div class="top-count"><?php echo $cititor['nr']; ?> împrumuturi</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Grafic Împrumuturi pe Lună
        const ctxLuni = document.getElementById('chartLuni').getContext('2d');
        new Chart(ctxLuni, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($luni_labels); ?>,
                datasets: [{
                    label: 'Număr Împrumuturi',
                    data: <?php echo json_encode($luni_data); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Grafic Împrumuturi pe Secțiune
        const ctxSectiuni = document.getElementById('chartSectiuni').getContext('2d');
        new Chart(ctxSectiuni, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($imprumuturi_sectiune, 'sectiune')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($imprumuturi_sectiune, 'nr')); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b',
                        '#fa709a',
                        '#fee140',
                        '#30cfd0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Grafic Activitate Săptămânală
        const ctxSaptamana = document.getElementById('chartSaptamana').getContext('2d');
        new Chart(ctxSaptamana, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($activitate_saptamana, 'label')); ?>,
                datasets: [{
                    label: 'Împrumuturi',
                    data: <?php echo json_encode(array_column($activitate_saptamana, 'imprumuturi')); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Returnări',
                    data: <?php echo json_encode(array_column($activitate_saptamana, 'returnari')); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Ultimele 7 zile'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>