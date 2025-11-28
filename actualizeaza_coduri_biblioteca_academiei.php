<?php
/**
 * Script pentru actualizare coduri Biblioteca Academiei
 * Convertește codurile de la format 014016xxx la 14016xxx
 * 
 * IMPORTANT: Rulează acest script DOAR O DATĂ pentru a actualiza codurile existente!
 */

// Start sesiune înainte de orice
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering pentru a evita probleme cu redirect-uri
ob_start();

require_once 'config.php';
require_once 'functions_autentificare.php';

// Verifică dacă este autentificat
if (!esteAutentificat($pdo)) {
    // Salvează URL-ul curent pentru redirect după login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    ob_end_clean();
    header('Location: login.php');
    exit;
}

// Verifică dacă utilizatorul este autentificat (permite orice utilizator autentificat pentru moment)
$utilizator = getUtilizatorAutentificat();
if (!$utilizator) {
    ob_end_clean();
    die("❌ Acces restricționat! Trebuie să fii autentificat pentru a accesa acest script.");
}

// Opțional: Verifică dacă utilizatorul este admin (comentează dacă vrei să permiți oricui)
// if (($utilizator['username'] ?? '') !== 'admin') {
//     ob_end_clean();
//     die("❌ Acces restricționat! Doar administratorii pot rula acest script.");
// }

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Actualizare Coduri Biblioteca Academiei</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .success {
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #5568d3;
        }
        button.danger {
            background: #dc3545;
        }
        button.danger:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Actualizare Coduri Biblioteca Academiei</h1>
        
        <?php
        $action = $_GET['action'] ?? 'preview';
        
        if ($action === 'preview') {
            // PREVIEW: Arată ce coduri vor fi actualizate
            ?>
            <div class="info">
                <strong>ℹ️ Informații:</strong><br>
                Acest script va converti codurile de la format <code>014016xxx</code> la <code>14016xxx</code>.<br>
                <strong>Exemplu:</strong> <code>014016038</code> → <code>14016838</code>
            </div>
            
            <div class="warning">
                <strong>⚠️ ATENȚIE:</strong><br>
                - Acest script va modifica codurile din tabelul <code>cititori</code><br>
                - Va actualiza și codurile din tabelul <code>imprumuturi</code><br>
                - <strong>Fă backup la baza de date înainte de a continua!</strong>
            </div>
            
            <?php
            // Găsește toate codurile care încep cu 014016
            $stmt = $pdo->prepare("SELECT cod_bare, nume, prenume FROM cititori WHERE cod_bare LIKE '014016%' ORDER BY cod_bare");
            $stmt->execute();
            $coduri_vechi = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($coduri_vechi)) {
                ?>
                <div class="success">
                    ✅ Nu există coduri de actualizat! Toate codurile sunt deja în formatul nou.
                </div>
                <?php
            } else {
                ?>
                <h2>📋 Coduri care vor fi actualizate (<?php echo count($coduri_vechi); ?>)</h2>
                <table>
                    <tr>
                        <th>Cod Vechi</th>
                        <th>Cod Nou</th>
                        <th>Cititor</th>
                    </tr>
                    <?php
                    foreach ($coduri_vechi as $row) {
                        $cod_vechi = $row['cod_bare'];
                        // Convertește: 014016038 → 14016838
                        // Elimină primul 0 (poziția 0) și al 4-lea 0 (poziția 3)
                        if (strlen($cod_vechi) === 9 && substr($cod_vechi, 0, 1) === '0' && substr($cod_vechi, 3, 1) === '0') {
                            // Elimină primul 0 și al 4-lea 0
                            $cod_nou = substr($cod_vechi, 1, 2) . substr($cod_vechi, 4); // "14" + "16838" = "14016838"
                        } else {
                            // Fallback: doar elimină primul 0
                            $cod_nou = substr($cod_vechi, 1);
                        }
                        echo "<tr>";
                        echo "<td><code>{$cod_vechi}</code></td>";
                        echo "<td><code>{$cod_nou}</code></td>";
                        echo "<td>{$row['nume']} {$row['prenume']}</td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
                
                <div style="margin-top: 30px;">
                    <a href="?action=execute"><button>✅ Execută Actualizarea</button></a>
                    <a href="index.php"><button class="danger">❌ Anulează</button></a>
                </div>
                <?php
            }
        } elseif ($action === 'execute') {
            // EXECUTE: Actualizează codurile
            ?>
            <div class="warning">
                <strong>🔄 Actualizare în curs...</strong>
            </div>
            <?php
            
            try {
                $pdo->beginTransaction();
                
                // 1. Găsește toate codurile vechi
                $stmt = $pdo->prepare("SELECT cod_bare FROM cititori WHERE cod_bare LIKE '014016%'");
                $stmt->execute();
                $coduri_vechi = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $actualizate = 0;
                $eroare = [];
                
                foreach ($coduri_vechi as $cod_vechi) {
                    // Convertește: 014016038 → 14016838
                    // Elimină primul 0 (poziția 0) și al 4-lea 0 (poziția 3)
                    if (strlen($cod_vechi) === 9 && substr($cod_vechi, 0, 1) === '0' && substr($cod_vechi, 3, 1) === '0') {
                        // Elimină primul 0 și al 4-lea 0
                        $cod_nou = substr($cod_vechi, 1, 2) . substr($cod_vechi, 4); // "14" + "16838" = "14016838"
                    } else {
                        // Fallback: doar elimină primul 0
                        $cod_nou = substr($cod_vechi, 1);
                    }
                    
                    // Verifică dacă codul nou există deja
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM cititori WHERE cod_bare = ?");
                    $stmt_check->execute([$cod_nou]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $eroare[] = "Codul {$cod_nou} există deja! Nu se poate actualiza {$cod_vechi}";
                        continue;
                    }
                    
                    // Actualizează în tabelul cititori
                    $stmt_update = $pdo->prepare("UPDATE cititori SET cod_bare = ? WHERE cod_bare = ?");
                    $stmt_update->execute([$cod_nou, $cod_vechi]);
                    
                    // Actualizează în tabelul imprumuturi
                    $stmt_update_impr = $pdo->prepare("UPDATE imprumuturi SET cod_cititor = ? WHERE cod_cititor = ?");
                    $stmt_update_impr->execute([$cod_nou, $cod_vechi]);
                    
                    $actualizate++;
                }
                
                $pdo->commit();
                
                ?>
                <div class="success">
                    <strong>✅ Actualizare completă!</strong><br>
                    - Coduri actualizate: <strong><?php echo $actualizate; ?></strong><br>
                    - Erori: <strong><?php echo count($eroare); ?></strong>
                </div>
                
                <?php if (!empty($eroare)): ?>
                    <div class="error">
                        <strong>⚠️ Erori:</strong><br>
                        <ul>
                            <?php foreach ($eroare as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 30px;">
                    <a href="index.php"><button>🏠 Înapoi la Pagina Principală</button></a>
                </div>
                <?php
                
            } catch (Exception $e) {
                $pdo->rollBack();
                ?>
                <div class="error">
                    <strong>❌ Eroare la actualizare:</strong><br>
                    <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
                <div style="margin-top: 30px;">
                    <a href="?action=preview"><button>🔙 Înapoi la Preview</button></a>
                </div>
                <?php
            }
        }
        ?>
    </div>
</body>
</html>
<?php
// End output buffering și trimite output-ul
ob_end_flush();
?>

