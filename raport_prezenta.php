<?php
// raport_prezenta.php - Raport prezență cititori la bibliotecă
header('Content-Type: text/html; charset=UTF-8');

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Filtrare pe dată
$data_inceput = $_GET['data_inceput'] ?? date('Y-m-01'); // Prima zi a lunii curente
$data_sfarsit = $_GET['data_sfarsit'] ?? date('Y-m-d'); // Azi
$filter_type = $_GET['filter'] ?? ''; // Tip de filtru: total_vizite, cititori_unici, vizite_azi, medie

// Statistici generale
$total_vizite = $pdo->query("SELECT COUNT(*) FROM sesiuni_biblioteca")->fetchColumn();
$total_cititori_unici = $pdo->query("SELECT COUNT(DISTINCT cod_cititor) FROM sesiuni_biblioteca")->fetchColumn();
$vizite_azi = $pdo->query("SELECT COUNT(*) FROM sesiuni_biblioteca WHERE data = CURDATE()")->fetchColumn();
$medie_vizite = $total_cititori_unici > 0 ? ($total_vizite / $total_cititori_unici) : 0;

// Construiește query-ul pentru raport detaliat în funcție de filtru
$where_conditions = ["s.data BETWEEN ? AND ?"];
$params = [$data_inceput, $data_sfarsit];

switch ($filter_type) {
    case 'total_vizite':
        // Toate vizitele (deja filtrate pe perioadă)
        break;
    case 'cititori_unici':
        // Doar cititorii unici (nu se schimbă query-ul, doar ordinea)
        break;
    case 'vizite_azi':
        // Doar vizitele de astăzi
        $where_conditions = ["s.data = CURDATE()"];
        $params = [];
        break;
    case 'medie':
        // Cititorii cu număr de vizite peste medie
        $where_conditions = ["s.data BETWEEN ? AND ?"];
        $params = [$data_inceput, $data_sfarsit];
        break;
    default:
        // Fără filtru special
        break;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Raport detaliat pe cititori
$query = "
    SELECT 
        s.cod_cititor,
        c.id as cititor_id,
        c.nume,
        c.prenume,
        COUNT(*) as numar_vizite,
        MIN(s.data) as prima_vizita,
        MAX(s.data) as ultima_vizita,
        MIN(s.ora_intrare) as prima_ora,
        MAX(s.ora_intrare) as ultima_ora
    FROM sesiuni_biblioteca s
    JOIN cititori c ON s.cod_cititor = c.cod_bare
    $where_clause
    GROUP BY s.cod_cititor, c.id, c.nume, c.prenume
";

// Aplică filtrare suplimentară pentru medie
if ($filter_type === 'medie') {
    $query .= " HAVING COUNT(*) >= ?";
    $params[] = floor($medie_vizite);
}

$query .= " ORDER BY numar_vizite DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$raport = $stmt->fetchAll();

// Vizite pe zile (pentru grafic)
$stmt_zile = $pdo->prepare("
    SELECT 
        data,
        COUNT(*) as numar_vizite
    FROM sesiuni_biblioteca
    WHERE data BETWEEN ? AND ?
    GROUP BY data
    ORDER BY data ASC
");
$stmt_zile->execute([$data_inceput, $data_sfarsit]);
$vizite_pe_zile = $stmt_zile->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Prezență - Sistem Bibliotecă</title>
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

        .home-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            font-weight: 600;
        }

        .home-btn:hover {
            background: #218838;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }

        .stat-card.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .stat-card h3 {
            font-size: 2.5em;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            font-size: 1.1em;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .filter-section h2 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-form input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }

        .filter-form button {
            padding: 10px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-form button:hover {
            background: #764ba2;
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
            margin-top: 20px;
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
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .badge-gold {
            background: #ffd700;
            color: #856404;
        }

        .badge-silver {
            background: #c0c0c0;
            color: #495057;
        }

        .badge-bronze {
            background: #cd7f32;
            color: white;
        }

        .badge-normal {
            background: #e9ecef;
            color: #495057;
        }

        .cititor-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .cititor-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.2em;
        }

        .chart-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .chart-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .chart-label {
            width: 100px;
            font-size: 0.9em;
            color: #666;
        }

        .chart-bar-fill {
            height: 25px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 5px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            color: white;
            font-weight: 600;
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
            <h1>📊 Raport Prezență Cititori</h1>
            <div class="header-buttons">
                <a href="index.php" class="home-btn">🏠 Acasă</a>
            </div>
        </div>

        <!-- Statistici generale -->
        <div class="stats-grid">
            <div class="stat-card <?php echo $filter_type === 'total_vizite' ? 'active' : ''; ?>" 
                 onclick="filterTable('total_vizite')" 
                 title="Click pentru a vedea toate vizitele">
                <h3><?php echo number_format($total_vizite); ?></h3>
                <p>Total vizite</p>
            </div>
            <div class="stat-card <?php echo $filter_type === 'cititori_unici' ? 'active' : ''; ?>" 
                 onclick="filterTable('cititori_unici')" 
                 title="Click pentru a vedea cititorii unici">
                <h3><?php echo number_format($total_cititori_unici); ?></h3>
                <p>Cititori unici</p>
            </div>
            <div class="stat-card <?php echo $filter_type === 'vizite_azi' ? 'active' : ''; ?>" 
                 onclick="filterTable('vizite_azi')" 
                 title="Click pentru a vedea vizitele de astăzi">
                <h3><?php echo number_format($vizite_azi); ?></h3>
                <p>Vizite astăzi</p>
            </div>
            <div class="stat-card <?php echo $filter_type === 'medie' ? 'active' : ''; ?>" 
                 onclick="filterTable('medie')" 
                 title="Click pentru a vedea cititorii cu vizite peste medie">
                <h3><?php echo number_format($medie_vizite, 1); ?></h3>
                <p>Medie vizite/cititor</p>
            </div>
        </div>

        <!-- Filtrare -->
        <div class="filter-section">
            <h2>🔍 Filtrare perioadă</h2>
            <form method="GET" class="filter-form">
                <div>
                    <label>Data început:</label>
                    <input type="date" name="data_inceput" value="<?php echo $data_inceput; ?>" required>
                </div>
                <div>
                    <label>Data sfârșit:</label>
                    <input type="date" name="data_sfarsit" value="<?php echo $data_sfarsit; ?>" required>
                </div>
                <button type="submit">📊 Actualizează</button>
            </form>
        </div>

        <!-- Tabel detaliat (mutat înainte de grafic pentru a fi vizibil imediat) -->
        <div class="content" id="detalii-cititori-section">
            <h2>👥 Detalii cititori 
                <?php 
                if ($filter_type === 'vizite_azi') {
                    echo "(Astăzi - " . date('d.m.Y') . ")";
                } elseif ($filter_type === 'medie') {
                    echo "(Cititori cu vizite ≥ " . floor($medie_vizite) . " - " . date('d.m.Y', strtotime($data_inceput)) . " - " . date('d.m.Y', strtotime($data_sfarsit)) . ")";
                } else {
                    echo "(" . date('d.m.Y', strtotime($data_inceput)) . " - " . date('d.m.Y', strtotime($data_sfarsit)) . ")";
                }
                ?>
            </h2>

            <?php if (count($raport) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Loc</th>
                            <th>Cititor</th>
                            <th>Cod</th>
                            <th>Nr. vizite</th>
                            <th>Prima vizită</th>
                            <th>Ultima vizită</th>
                            <th>Interval orar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($raport as $index => $rand): 
                            $loc = $index + 1;
                            if ($loc == 1) $badge = 'badge-gold';
                            elseif ($loc == 2) $badge = 'badge-silver';
                            elseif ($loc == 3) $badge = 'badge-bronze';
                            else $badge = 'badge-normal';
                        ?>
                            <tr>
                                <td><span class="badge <?php echo $badge; ?>">#<?php echo $loc; ?></span></td>
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars($rand['nume'] . ' ' . $rand['prenume'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <a href="editare_cititor.php?id=<?php echo (int)$rand['cititor_id']; ?>" 
                                       class="cititor-link"
                                       title="Click pentru a edita cititorul">
                                        <?php echo htmlspecialchars($rand['cod_cititor'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong style="color: #28a745; font-size: 1.1em;">
                                        <?php echo $rand['numar_vizite']; ?>
                                    </strong>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($rand['prima_vizita'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($rand['ultima_vizita'])); ?></td>
                                <td>
                                    <?php echo substr($rand['prima_ora'], 0, 5); ?> - <?php echo substr($rand['ultima_ora'], 0, 5); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">🔭 Nu există vizite în această perioadă</div>
            <?php endif; ?>
        </div>

        <!-- Grafic vizite pe zile (mutat după tabel pentru a nu fi în prim plan) -->
        <?php if (count($vizite_pe_zile) > 0): ?>
        <div class="content">
            <h2>📈 Vizite pe zile</h2>
            <div class="chart-container">
                <?php 
                $max_vizite = max(array_column($vizite_pe_zile, 'numar_vizite'));
                foreach ($vizite_pe_zile as $zi): 
                    $width_percent = ($zi['numar_vizite'] / $max_vizite) * 100;
                ?>
                    <div class="chart-bar">
                        <div class="chart-label"><?php echo date('d.m.Y', strtotime($zi['data'])); ?></div>
                        <div class="chart-bar-fill" style="width: <?php echo $width_percent; ?>%;">
                            <?php echo $zi['numar_vizite']; ?> vizite
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="app-footer">
            <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
        </div>
    </div>

    <script>
        function filterTable(filterType) {
            // Construiește URL-ul cu parametrii
            const url = new URL(window.location.href);
            url.searchParams.set('filter', filterType);
            
            // Păstrează parametrii de dată dacă există
            const dataInceput = url.searchParams.get('data_inceput') || '<?php echo date('Y-m-01'); ?>';
            const dataSfarsit = url.searchParams.get('data_sfarsit') || '<?php echo date('Y-m-d'); ?>';
            
            url.searchParams.set('data_inceput', dataInceput);
            url.searchParams.set('data_sfarsit', dataSfarsit);
            
            // Salvează în sessionStorage pentru a face scroll după redirect
            sessionStorage.setItem('scrollToDetalii', 'true');
            
            // Redirecționează
            window.location.href = url.toString();
        }
        
        // Dacă există un filtru activ la încărcarea paginii, face scroll la secțiunea "Detalii cititori"
        window.addEventListener('load', function() {
            // Verifică dacă trebuie să facă scroll (din sessionStorage sau dacă există filtru activ)
            const shouldScroll = sessionStorage.getItem('scrollToDetalii') === 'true' || <?php echo !empty($filter_type) ? 'true' : 'false'; ?>;
            
            if (shouldScroll) {
                // Șterge flag-ul din sessionStorage
                sessionStorage.removeItem('scrollToDetalii');
                
                // Așteaptă puțin pentru ca pagina să se încarce complet
                setTimeout(function() {
                    const detaliiSection = document.getElementById('detalii-cititori-section');
                    if (detaliiSection) {
                        // Calculează poziția cu offset pentru a lăsa vizibile cardurile de statistici
                        const elementPosition = detaliiSection.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - 100; // 100px offset pentru a vedea cardurile
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }, 300);
            }
        });
    </script>
</body>
</html>