<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_vizare.php';

$an_curent = date('Y');

// Obține TOȚI cititorii cu status vizare
$sql = "SELECT id, cod_bare, nume, prenume, email, telefon, ultima_vizare 
        FROM cititori 
        ORDER BY 
            CASE WHEN ultima_vizare IS NULL THEN 1 
                 WHEN YEAR(ultima_vizare) < ? THEN 1 
                 ELSE 0 
            END,
            ultima_vizare DESC,
            nume, prenume";

$stmt = $pdo->prepare($sql);
$stmt->execute([$an_curent]);
$toti_cititorii = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculează statistici
$total_cititori = count($toti_cititorii);
$total_vizati = 0;
$total_nevizati = 0;

foreach ($toti_cititorii as $cititor) {
    if (empty($cititor['ultima_vizare']) || date('Y', strtotime($cititor['ultima_vizare'])) < $an_curent) {
        $total_nevizati++;
    } else {
        $total_vizati++;
    }
}

$procent_vizare = $total_cititori > 0 ? round(($total_vizati / $total_cititori) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Vizări - <?= $an_curent ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .badge-vizat {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .badge-nevizat {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .row-vizat {
            background-color: #d4edda;
        }
        .row-nevizat {
            background-color: #f8d7da;
        }
        .table tbody tr {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-vizati {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        .stats-nevizati {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
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
        <h1 class="mb-4">📊 Status Vizări Permise - <?= $an_curent ?></h1>

        <!-- Statistici -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card bg-primary text-white">
                    <h2><?= $total_cititori ?></h2>
                    <p class="mb-0">Total Cititori</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card stats-vizati">
                    <h2><?= $total_vizati ?></h2>
                    <p class="mb-0">✅ Vizați <?= $an_curent ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card stats-nevizati">
                    <h2><?= $total_nevizati ?></h2>
                    <p class="mb-0">❌ Nevizați</p>
                </div>
            </div>
        </div>

        <!-- Bară progres -->
        <div class="mb-4">
            <h5>Progres Vizare: <?= $procent_vizare ?>%</h5>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar <?= $procent_vizare >= 80 ? 'bg-success' : 'bg-warning' ?>" 
                     style="width: <?= $procent_vizare ?>%">
                    <?= $procent_vizare ?>%
                </div>
            </div>
        </div>

        <!-- Tabel toți cititorii -->
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Cod Bare</th>
                    <th>Nume</th>
                    <th>Prenume</th>
                    <th>Email</th>
                    <th>Telefon</th>
                    <th>Ultima Vizare</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($toti_cititorii as $cititor): 
                    $este_vizat = !empty($cititor['ultima_vizare']) && 
                                  date('Y', strtotime($cititor['ultima_vizare'])) >= $an_curent;
                    $row_class = $este_vizat ? 'row-vizat' : 'row-nevizat';
                    $badge_class = $este_vizat ? 'badge-vizat' : 'badge-nevizat';
                    $status_text = $este_vizat ? '✅ Vizat' : '❌ Nevizat';
                ?>
                <tr class="<?= $row_class ?>" 
                    onclick="window.location.href='editare_cititor.php?id=<?= $cititor['id'] ?>'" 
                    data-cititor-id="<?= $cititor['id'] ?>"
                    title="Click pentru a vedea detalii complete despre cititor">
                    <td><strong><?= htmlspecialchars($cititor['cod_bare']) ?></strong></td>
                    <td><?= htmlspecialchars($cititor['nume']) ?></td>
                    <td><?= htmlspecialchars($cititor['prenume']) ?></td>
                    <td><?= htmlspecialchars($cititor['email']) ?></td>
                    <td><?= htmlspecialchars($cititor['telefon']) ?></td>
                    <td>
                        <?php if ($cititor['ultima_vizare']): ?>
                            <?= date('d.m.Y', strtotime($cititor['ultima_vizare'])) ?>
                        <?php else: ?>
                            <span class="text-muted">Niciodată</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?= $badge_class ?>">
                            <?= $status_text ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

<!-- Butoane acțiuni -->
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-primary">← Înapoi la Scanare</a>
            <a href="lista_nevizati.php" class="btn btn-warning">⚠️ Doar Nevizați</a>
            <a href="raport_vizari.php" class="btn btn-info">📊 Raport Detaliat</a>
        </div>
    </div>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>