<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_vizare.php';

$cititori_nevizati = getCitoriNevizati($pdo);
$an_curent = date('Y');
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista Permise Nevizate - <?= $an_curent ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }

        .header h1 {
            color: #667eea;
            font-size: 2.2em;
            margin: 0;
            font-weight: 700;
        }

        .stats-card {
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .stats-nevizati {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }

        .stats-card h2 {
            font-size: 3em;
            margin: 0;
            font-weight: 700;
        }

        .stats-card p {
            font-size: 1.2em;
            margin-top: 10px;
            opacity: 0.95;
        }

        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .table thead {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .table tbody tr:hover {
            background-color: #fff3cd;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .badge-nevizat {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .badge-vechi {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
            color: white;
        }

        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 1.1em;
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
            <h1>⚠️ Cititori cu Permise NEVIZATE pentru <?= htmlspecialchars($an_curent, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>

        <!-- Statistici -->
        <div class="stats-card stats-nevizati">
            <h2><?= count($cititori_nevizati) ?></h2>
            <p>❌ Total Cititori Nevizați</p>
        </div>

        <?php if (count($cititori_nevizati) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cod Bare</th>
                        <th>Nume</th>
                        <th>Prenume</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Ultima Vizare</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cititori_nevizati as $cititor): ?>
                    <tr onclick="window.location.href='editare_cititor.php?id=<?= $cititor['id'] ?>'" 
                        data-cititor-id="<?= $cititor['id'] ?>"
                        title="Click pentru a vedea detalii complete despre cititor">
                        <td><strong><?= htmlspecialchars($cititor['cod_bare'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($cititor['nume'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($cititor['prenume'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($cititor['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($cititor['telefon'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($cititor['ultima_vizare']): ?>
                                <span class="badge-vechi"><?= date('d.m.Y', strtotime($cititor['ultima_vizare'])) ?></span>
                            <?php else: ?>
                                <span class="badge-nevizat">Niciodată</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <h3>🎉 Excelent!</h3>
                <p>Toți cititorii au permisele vizate pentru anul <?= htmlspecialchars($an_curent, ENT_QUOTES, 'UTF-8') ?>!</p>
            </div>
        <?php endif; ?>

		<!-- Butoane acțiuni -->
        <div class="actions">
            <a href="index.php" class="btn btn-primary">← Înapoi la Pagina Principală</a>
            <a href="status_vizari.php" class="btn btn-info">📊 Status Vizări Complet</a>
            <a href="raport_vizari.php" class="btn btn-warning">📋 Raport Detaliat</a>
        </div>
    </div>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>