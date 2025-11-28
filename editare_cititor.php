<?php
// editare_cititor.php - Editare date cititor
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_statute.php';

$mesaj = '';
$tip_mesaj = '';

// Obține lista statutelor disponibile
$stmt_statute = $pdo->query("SELECT cod_statut, nume_statut, limita_totala FROM statute_cititori ORDER BY cod_statut");
$statute_disponibile = $stmt_statute->fetchAll(PDO::FETCH_ASSOC);

// Funcție pentru verificarea parolei admin din baza de date
function verificaParolaAdmin($pdo, $parola_introdusa) {
    try {
        // Obține parola hash-uită pentru utilizatorul cu ID 1
        $stmt = $pdo->prepare("SELECT password_hash FROM utilizatori WHERE id = 1 AND activ = TRUE");
        $stmt->execute();
        $utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilizator) {
            return false;
        }
        
        // Verifică parola folosind password_verify()
        return password_verify($parola_introdusa, $utilizator['password_hash']);
    } catch (PDOException $e) {
        error_log("Eroare verificare parolă admin: " . $e->getMessage());
        return false;
    }
}

// Verifică dacă avem ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID cititor lipsă');
}

$id = (int)$_GET['id'];

// Obține datele cititorului
$stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
$stmt->execute([$id]);
$cititor = $stmt->fetch();

if (!$cititor) {
    die('Cititorul nu a fost găsit');
}

// Verifică și actualizează statutul dacă este NULL sau gol
$statut_cititor = $cititor['statut'] ?? null;
if (empty($statut_cititor) || $statut_cititor === null) {
    // Extrage statutul din codul de bare
    require_once 'functions_statute.php';
    $statut_din_cod = extrageStatutDinCodBare($cititor['cod_bare'], $pdo);
    
    // Actualizează statutul în baza de date
    if ($statut_din_cod) {
        $stmt_update_statut = $pdo->prepare("UPDATE cititori SET statut = ? WHERE id = ?");
        $stmt_update_statut->execute([$statut_din_cod, $id]);
        $statut_cititor = $statut_din_cod;
        
        // Reîncarcă datele cititorului cu statutul actualizat
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
        $stmt->execute([$id]);
        $cititor = $stmt->fetch();
    } else {
        // Fallback la 14 dacă nu se poate extrage statutul
        $statut_cititor = '14';
    }
}

// Obține informații despre statutul cititorului
$info_statut_cititor = getInfoStatut($pdo, $statut_cititor);
$nume_statut_cititor = $info_statut_cititor ? $info_statut_cititor['nume_statut'] : 'Nespecificat';
$limita_cititor = $info_statut_cititor ? $info_statut_cititor['limita_totala'] : 4;

// Obține lista cărților împrumutate de cititor (cu informații despre întârziere)
$stmt_carti = $pdo->prepare("
    SELECT 
        i.id as imprumut_id,
        c.titlu,
        c.autor,
        c.cod_bare as cod_carte,
        i.data_imprumut,
        i.data_scadenta,
        DATEDIFF(CURDATE(), i.data_scadenta) as zile_intarziere
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    WHERE i.cod_cititor = ? 
    AND i.data_returnare IS NULL
    ORDER BY i.data_scadenta ASC
");
$stmt_carti->execute([$cititor['cod_bare']]);
$carti_imprumutate = $stmt_carti->fetchAll(PDO::FETCH_ASSOC);
$numar_carti_imprumutate = count($carti_imprumutate);

// Numără cărțile întârziate
$numar_intarziate = 0;
foreach ($carti_imprumutate as $c) {
    if ($c['zile_intarziere'] > 0) $numar_intarziate++;
}

// Procesare ștergere - CU VERIFICARE PAROLĂ
if (isset($_POST['delete']) && $_POST['delete'] === 'true') {
    // Verifică parola admin
    $parola_introdusa = $_POST['admin_password'] ?? '';
    
    if (!verificaParolaAdmin($pdo, $parola_introdusa)) {
        $mesaj = "🚫 Parolă incorectă! Nu ai permisiuni de administrator.";
        $tip_mesaj = "danger";
    } else {
        try {
            // Verifică dacă cititorul are împrumuturi în istoric
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ?");
            $check_stmt->execute([$cititor['cod_bare']]);
            $imprumuturi_count = $check_stmt->fetchColumn();

            // Verifică dacă cititorul are sesiuni în biblioteca
            $sesiuni_count = 0;
            try {
                $check_sesiuni = $pdo->prepare("SELECT COUNT(*) FROM sesiuni_biblioteca WHERE cod_cititor = ?");
                $check_sesiuni->execute([$cititor['cod_bare']]);
                $sesiuni_count = $check_sesiuni->fetchColumn();
            } catch (PDOException $e) {
                // Ignoră dacă tabelul nu există
            }

            // Verifică dacă cititorul are notificări
            $notificari_count = 0;
            try {
                $check_notificari = $pdo->prepare("SELECT COUNT(*) FROM notificari WHERE cod_cititor = ?");
                $check_notificari->execute([$cititor['cod_bare']]);
                $notificari_count = $check_notificari->fetchColumn();
            } catch (PDOException $e) {
                // Ignoră dacă tabelul nu există
            }

            $sterge_istoric = isset($_POST['sterge_istoric']) && $_POST['sterge_istoric'] === 'true';

            if ($imprumuturi_count > 0 && !$sterge_istoric) {
                $mesaj = "⚠️ Nu poți șterge acest cititor!\n\n" .
                         "Cititorul are {$imprumuturi_count} împrumuturi în istoric.\n\n" .
                         "Dacă vrei să ștergi cititorul împreună cu tot istoricul, " .
                         "apasă butonul 'Șterge cu tot istoricul' de jos.";
                $tip_mesaj = "danger";
            } else {
                // Șterge împrumuturile dacă există
                if ($imprumuturi_count > 0) {
                    $del_stmt = $pdo->prepare("DELETE FROM imprumuturi WHERE cod_cititor = ?");
                    $del_stmt->execute([$cititor['cod_bare']]);
                }
                
                // Șterge sesiunile din biblioteca (foreign key fără ON DELETE CASCADE)
                if ($sesiuni_count > 0) {
                    $del_sesiuni = $pdo->prepare("DELETE FROM sesiuni_biblioteca WHERE cod_cititor = ?");
                    $del_sesiuni->execute([$cititor['cod_bare']]);
                }
                
                // Șterge notificările (dacă există)
                if ($notificari_count > 0) {
                    $del_notificari = $pdo->prepare("DELETE FROM notificari WHERE cod_cititor = ?");
                    $del_notificari->execute([$cititor['cod_bare']]);
                }
                
                // Șterge cititorul
                $stmt = $pdo->prepare("DELETE FROM cititori WHERE id = ?");
                $stmt->execute([$id]);
                
                $msg_detalii = [];
                if ($imprumuturi_count > 0) $msg_detalii[] = "{$imprumuturi_count} împrumuturi";
                if ($sesiuni_count > 0) $msg_detalii[] = "{$sesiuni_count} sesiuni bibliotecă";
                if ($notificari_count > 0) $msg_detalii[] = "{$notificari_count} notificări";
                
                $msg_extra = !empty($msg_detalii) ? " și " . implode(", ", $msg_detalii) : "";
                echo "<script>alert('✅ Cititorul{$msg_extra} au fost șterse!'); window.location.href='cititori.php';</script>";
                exit;
            }
        } catch (PDOException $e) {
            $mesaj = "❌ Eroare la ștergere: " . $e->getMessage();
            $tip_mesaj = "danger";
        }
    }
}

// Procesare formular editare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $cod_bare = trim($_POST['cod_bare'] ?? '');
    $nume = trim($_POST['nume'] ?? '');
    $prenume = trim($_POST['prenume'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $statut_nou = trim($_POST['statut'] ?? '');
    
    // Verifică dacă codul de bare a fost schimbat
    $cod_bare_vechi = $cititor['cod_bare'];
    $cod_schimbat = ($cod_bare !== $cod_bare_vechi);
    
    // Dacă codul s-a schimbat, verifică dacă noul cod nu există deja pentru alt cititor
    $cod_duplicat = false;
    if ($cod_schimbat) {
        $stmt_check = $pdo->prepare("SELECT id, nume, prenume FROM cititori WHERE cod_bare = ? AND id != ?");
        $stmt_check->execute([$cod_bare, $id]);
        $cititor_existent = $stmt_check->fetch();
        
        if ($cititor_existent) {
            // Setează variabile pentru modal JavaScript
            $cod_duplicat_mesaj = "Codul de bare '{$cod_bare}' este deja folosit!";
            $cititor_duplicat_nume = $cititor_existent['nume'] . ' ' . $cititor_existent['prenume'];
            $cod_duplicat = true;
            
            // Reîncarcă datele pentru a afișa valorile originale
            $stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
            $stmt->execute([$id]);
            $cititor = $stmt->fetch();
        } else {
            // Codul este disponibil, continuă procesarea
            // Extrage statutul din noul cod de bare
            $statut_din_cod_nou = extrageStatutDinCodBare($cod_bare, $pdo);
            
            // Folosește întotdeauna statutul extras din cod
            $statut_nou = $statut_din_cod_nou ? $statut_din_cod_nou : '14';
            if ($statut_nou !== $statut_cititor) {
                $mesaj .= "⚠️ Statutul a fost ajustat automat la '{$statut_nou}' conform noului cod de bare.\n";
            }
        }
    } else {
        // Codul nu s-a schimbat - folosește statutul existent din baza de date
        // Dacă statutul este NULL sau gol, extrage-l din cod
        if (empty($cititor['statut']) || $cititor['statut'] === null) {
            $statut_din_cod = extrageStatutDinCodBare($cititor['cod_bare'], $pdo);
            $statut_nou = $statut_din_cod ? $statut_din_cod : '14';
        } else {
            $statut_nou = $cititor['statut'];
        }
    }

    // Procesare upload imagine dacă există
    $imagine_path = $cititor['imagine'] ?? null; // Păstrează imaginea existentă
    if (isset($_FILES['imagine']) && $_FILES['imagine']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imagine'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nume_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $nume);
            $prenume_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $prenume);
            $cod_curat = preg_replace('/[^a-zA-Z0-9]/', '_', $cod_bare);
            $filename = $cod_curat . '_' . $nume_curat . '_' . $prenume_curat . '.' . $ext;
            $upload_path = 'imagini/' . $filename;
            
            if (!is_dir('imagini')) {
                mkdir('imagini', 0755, true);
            }
            
            // Șterge imaginea veche dacă există
            if (!empty($cititor['imagine']) && file_exists($cititor['imagine'])) {
                @unlink($cititor['imagine']);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $imagine_path = $upload_path;
            } else {
                $mesaj .= "⚠️ Imaginea nu a putut fi încărcată!\n";
            }
        } else {
            $mesaj .= "⚠️ Imaginea nu este validă! (Max 5MB, JPEG/PNG/GIF)\n";
        }
    }

    // Continuă procesarea doar dacă nu există eroare de cod duplicat
    if (!$cod_duplicat) {
    try {
        // Dacă codul de bare s-a schimbat, trebuie să dezactivăm temporar foreign key checks
        if ($cod_schimbat) {
            $pdo->beginTransaction();
            
            try {
                // Dezactivează verificarea foreign key-urilor temporar
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Actualizează codul în cititori PRIMUL (înainte de tabelele dependente)
                $stmt_img = $pdo->query("SHOW COLUMNS FROM cititori LIKE 'imagine'");
                $are_imagine = $stmt_img->rowCount() > 0;
                
                if ($are_imagine) {
                    $stmt_cititor = $pdo->prepare("
                        UPDATE cititori
                        SET cod_bare = ?, nume = ?, prenume = ?, telefon = ?, email = ?, statut = ?, imagine = ?
                        WHERE id = ?
                    ");
                    $stmt_cititor->execute([$cod_bare, $nume, $prenume, $telefon, $email, $statut_nou, $imagine_path, $id]);
                } else {
                    $stmt_cititor = $pdo->prepare("
                        UPDATE cititori
                        SET cod_bare = ?, nume = ?, prenume = ?, telefon = ?, email = ?, statut = ?
                        WHERE id = ?
                    ");
                    $stmt_cititor->execute([$cod_bare, $nume, $prenume, $telefon, $email, $statut_nou, $id]);
                }
                
                // Acum actualizează tabelele dependente (codul nou există deja în cititori)
                // Actualizează imprumuturi
                $stmt_imprumuturi = $pdo->prepare("UPDATE imprumuturi SET cod_cititor = ? WHERE cod_cititor = ?");
                $stmt_imprumuturi->execute([$cod_bare, $cod_bare_vechi]);
                
                // Actualizează sesiuni_biblioteca
                try {
                    $stmt_sesiuni = $pdo->prepare("UPDATE sesiuni_biblioteca SET cod_cititor = ? WHERE cod_cititor = ?");
                    $stmt_sesiuni->execute([$cod_bare, $cod_bare_vechi]);
                } catch (PDOException $e) {
                    // Ignoră dacă tabelul nu există
                }
                
                // Actualizează sesiuni_utilizatori
                try {
                    $stmt_sesiuni_util = $pdo->prepare("UPDATE sesiuni_utilizatori SET cod_cititor = ? WHERE cod_cititor = ?");
                    $stmt_sesiuni_util->execute([$cod_bare, $cod_bare_vechi]);
                } catch (PDOException $e) {
                    // Ignoră dacă tabelul nu există
                }
                
                // Actualizează notificari
                try {
                    $stmt_notificari = $pdo->prepare("UPDATE notificari SET cod_cititor = ? WHERE cod_cititor = ?");
                    $stmt_notificari->execute([$cod_bare, $cod_bare_vechi]);
                } catch (PDOException $e) {
                    // Ignoră dacă tabelul nu există
                }
                
                // Actualizează tracking_sesiuni
                try {
                    $stmt_tracking = $pdo->prepare("UPDATE tracking_sesiuni SET cod_cititor = ? WHERE cod_cititor = ?");
                    $stmt_tracking->execute([$cod_bare, $cod_bare_vechi]);
                } catch (PDOException $e) {
                    // Ignoră dacă tabelul nu există
                }
                
                // Reactivează verificarea foreign key-urilor
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $pdo->commit();
            } catch (PDOException $e) {
                // Reactivează verificarea foreign key-urilor în caz de eroare
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // Codul nu s-a schimbat, doar actualizează cititorul normal
            $stmt_img = $pdo->query("SHOW COLUMNS FROM cititori LIKE 'imagine'");
            $are_imagine = $stmt_img->rowCount() > 0;
            
            if ($are_imagine) {
                $stmt = $pdo->prepare("
                    UPDATE cititori
                    SET nume = ?, prenume = ?, telefon = ?, email = ?, statut = ?, imagine = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nume, $prenume, $telefon, $email, $statut_nou, $imagine_path, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE cititori
                    SET nume = ?, prenume = ?, telefon = ?, email = ?, statut = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nume, $prenume, $telefon, $email, $statut_nou, $id]);
            }
        }

        $mesaj = "✅ Cititorul a fost actualizat cu succes!" . ($mesaj ? "\n" . $mesaj : "");
        $tip_mesaj = "success";

        // Reîncarcă datele
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
        $stmt->execute([$id]);
        $cititor = $stmt->fetch();
        
        // Actualizează variabilele pentru afișare - folosește statutul salvat
        $statut_cititor = $statut_nou; // Folosește statutul care tocmai a fost salvat
        $info_statut_cititor = getInfoStatut($pdo, $statut_cititor);
        $nume_statut_cititor = $info_statut_cititor ? $info_statut_cititor['nume_statut'] : 'Nespecificat';
        $limita_cititor = $info_statut_cititor ? $info_statut_cititor['limita_totala'] : 4;

    } catch (PDOException $e) {
        $mesaj = "❌ Eroare: " . $e->getMessage();
        $tip_mesaj = "danger";
        
        // Reîncarcă datele în caz de eroare
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE id = ?");
        $stmt->execute([$id]);
        $cititor = $stmt->fetch();
    }
    } // End if pentru verificare cod duplicat
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editare Cititor - Sistem Bibliotecă</title>
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
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        h1 {
            color: #667eea;
            margin-bottom: 30px;
            font-size: 2.2em;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1em;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .delete-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            margin-top: 15px;
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #c82333 0%, #a02622 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,0,0,0.2);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-buttons a {
            flex: 1;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-line;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link, .home-link {
            display: inline-block;
            margin-top: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
            text-align: center;
        }

        .home-link {
            background: #28a745;
            margin-right: 10px;
        }

        .home-link:hover {
            background: #218838;
        }

        .back-link {
            background: #667eea;
            color: white;
        }

        .back-link:hover {
            background: #764ba2;
        }

        .preview-card {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }

        .preview-card h4 {
            margin-bottom: 8px;
            color: #28a745;
            font-size: 1em;
        }

        .card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9em;
        }

        .card-item {
            display: flex;
            justify-content: space-between;
        }

        .card-label {
            font-weight: 600;
            color: #666;
        }

        .card-value {
            color: #333;
        }

        /* Modal pentru parolă */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            min-width: 350px;
            z-index: 1001;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #667eea;
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .modal-header p {
            color: #666;
            font-size: 0.9em;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            margin-bottom: 20px;
        }

        .modal-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-confirm:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #545b62;
        }

        .warning-icon {
            font-size: 3em;
            text-align: center;
            margin-bottom: 15px;
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

        /* Upload imagine */
        .user-icon-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px dashed #667eea;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .user-icon-container:hover {
            border-color: #764ba2;
            background: #e9ecef;
            transform: scale(1.05);
        }

        .user-icon-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-icon-svg {
            width: 80px;
            height: 80px;
            fill: #667eea;
        }

        .upload-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            animation: fadeIn 0.3s;
        }

        .upload-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            min-width: 400px;
            max-width: 600px;
            z-index: 2001;
            animation: slideIn 0.3s;
        }

        .drop-zone {
            border: 3px dashed #667eea;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .drop-zone:hover {
            border-color: #764ba2;
            background: #e9ecef;
        }

        .drop-zone.dragover {
            border-color: #28a745;
            background: #d4edda;
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .upload-buttons {
            display: none;
            gap: 10px;
        }

        .upload-buttons button {
            flex: 1;
        }

        /* Modal pentru eroare cod duplicat */
        .error-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 3000;
            animation: fadeIn 0.3s;
        }

        .error-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 60px rgba(220, 53, 69, 0.4);
            min-width: 450px;
            max-width: 600px;
            z-index: 3001;
            animation: slideIn 0.3s;
            border-top: 5px solid #dc3545;
        }

        .error-icon {
            font-size: 4em;
            text-align: center;
            margin-bottom: 20px;
            color: #dc3545;
        }

        .error-modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .error-modal-header h3 {
            color: #dc3545;
            font-size: 1.8em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .error-modal-header p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .error-details {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .error-details strong {
            color: #856404;
            display: block;
            margin-bottom: 8px;
            font-size: 1.1em;
        }

        .error-details .cod-duplicat {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            color: #dc3545;
            font-weight: bold;
            background: #f8d7da;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            margin: 5px 0;
        }

        .error-details .cititor-existent {
            color: #856404;
            font-size: 1em;
            margin-top: 8px;
        }

        .error-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .error-modal-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .error-modal-btn-ok {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            flex: 1;
        }

        .error-modal-btn-ok:hover {
            background: linear-gradient(135deg, #c82333 0%, #a02622 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 <?php echo htmlspecialchars($cititor['nume'] . ' ' . $cititor['prenume']); ?></h1>

        <?php if ($mesaj && (!isset($cod_duplicat) || !$cod_duplicat)): ?>
            <div class="alert alert-<?php echo $tip_mesaj; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <!-- Secțiune Statut și Cărți Împrumutate -->
        <div class="status-section" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); border-radius: 12px; padding: 20px; margin-bottom: 25px; color: white;">
            <h3 style="margin: 0 0 15px 0; font-size: 1.3em;">
                🏷️ Statut: <span style="background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 6px;"><?php echo htmlspecialchars($statut_cititor); ?></span> 
                - <?php echo htmlspecialchars($nume_statut_cititor); ?>
            </h3>
            <p style="margin: 0 0 10px 0; opacity: 0.95;">
                📚 <strong><?php echo $numar_carti_imprumutate; ?> / <?php echo $limita_cititor; ?></strong> cărți împrumutate
                <?php if ($numar_carti_imprumutate >= $limita_cititor): ?>
                    <span style="background: #e74c3c; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; margin-left: 8px;">⚠️ Limita atinsă!</span>
                <?php endif; ?>
            </p>
            <?php if ($numar_intarziate > 0): ?>
                <p style="margin: 0; background: rgba(255,0,0,0.3); padding: 8px 12px; border-radius: 6px;">
                    🚨 <strong><?php echo $numar_intarziate; ?> <?php echo $numar_intarziate == 1 ? 'carte întârziată' : 'cărți întârziate'; ?>!</strong>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($carti_imprumutate)): ?>
        <!-- Lista Cărți Împrumutate -->
        <div class="books-section" style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #667eea;">
            <h4 style="margin: 0 0 15px 0; color: #667eea;">📚 Cărți împrumutate curent:</h4>
            <ol style="margin: 0; padding-left: 25px;">
                <?php foreach ($carti_imprumutate as $carte): 
                    $este_intarziata = $carte['zile_intarziere'] > 0;
                    $li_style = $este_intarziata ? 
                        'margin-bottom: 12px; padding: 10px; background: #ffe6e6; border-radius: 6px; border-left: 3px solid #dc3545;' : 
                        'margin-bottom: 10px; padding: 8px; background: white; border-radius: 6px;';
                ?>
                    <li style="<?php echo $li_style; ?>">
                        <a href="editare_imprumut.php?id=<?php echo $carte['imprumut_id']; ?>" 
                           style="color: <?php echo $este_intarziata ? '#dc3545' : '#667eea'; ?>; font-weight: 600; text-decoration: none;">
                            <?php echo htmlspecialchars($carte['titlu']); ?>
                        </a>
                        <?php if ($carte['autor']): ?>
                            <span style="color: #666; font-size: 0.9em;"> / <?php echo htmlspecialchars($carte['autor']); ?></span>
                        <?php endif; ?>
                        <?php if ($este_intarziata): ?>
                            <span style="display: block; color: #dc3545; font-size: 0.85em; margin-top: 4px; font-weight: bold;">
                                🚨 Întârziere: <?php echo $carte['zile_intarziere']; ?> <?php echo $carte['zile_intarziere'] == 1 ? 'zi' : 'zile'; ?>
                            </span>
                        <?php else: ?>
                            <span style="display: block; color: #28a745; font-size: 0.85em; margin-top: 4px;">
                                ✅ Scadență: <?php echo date('d.m.Y', strtotime($carte['data_scadenta'])); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <form method="POST" id="cititorForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>Cod de bare carnet <span class="required">*</span></label>
                <input type="text" name="cod_bare" id="cod_bare" value="<?php echo htmlspecialchars($cititor['cod_bare']); ?>" required>
                <small style="color: #666; display: block; margin-top: 5px;">⚠️ Schimbarea codului de bare va actualiza automat statutul</small>
            </div>

            <div class="form-group">
                <label>Statut cititor</label>
                <select name="statut" id="statut_select" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1em; background: #f5f5f5; cursor: not-allowed;" disabled>
                    <?php foreach ($statute_disponibile as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['cod_statut']); ?>" 
                                <?php echo ($s['cod_statut'] === $statut_cititor) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['cod_statut'] . ' - ' . $s['nume_statut'] . ' (' . $s['limita_totala'] . ' cărți)'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="statut_info" style="color: #666; display: block; margin-top: 5px;">
                    🔒 Statutul se poate schimba doar prin modificarea codului de bare
                </small>
            </div>

            <div class="form-group">
                <label>Nume <span class="required">*</span></label>
                <input type="text" name="nume" value="<?php echo htmlspecialchars($cititor['nume']); ?>" required>
            </div>

            <div class="form-group">
                <label>Prenume <span class="required">*</span></label>
                <input type="text" name="prenume" value="<?php echo htmlspecialchars($cititor['prenume']); ?>" required>
            </div>

            <div class="form-group">
                <label>Telefon</label>
                <input type="tel" name="telefon" value="<?php echo htmlspecialchars($cititor['telefon'] ?: ''); ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($cititor['email'] ?: ''); ?>">
            </div>

            <!-- Secțiune imagine utilizator -->
            <div class="form-group" style="text-align: center; margin-bottom: 30px;">
                <label style="margin-bottom: 15px;">Imagine cititor (opțional)</label>
                <div class="user-icon-container" onclick="deschideUploadModal()">
                    <?php if (!empty($cititor['imagine']) && file_exists($cititor['imagine'])): ?>
                        <img src="<?php echo htmlspecialchars($cititor['imagine']); ?>" 
                             alt="Imagine cititor" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <svg class="user-icon-svg" style="display: none;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            <circle cx="20" cy="8" r="6" fill="#28a745"/>
                            <path d="M17 8h6M20 5v6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    <?php else: ?>
                        <svg class="user-icon-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            <circle cx="20" cy="8" r="6" fill="#28a745"/>
                            <path d="M17 8h6M20 5v6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <small style="color: #666; display: block; margin-top: 10px;">Click pentru a încărca imagine</small>
            </div>

            <input type="file" name="imagine" id="fileInput" accept="image/*" style="display: none;">

            <button type="submit">💾 Salvează modificările</button>
        </form>

        <!-- Previzualizare card -->
        <div class="preview-card" id="previewCard">
            <h4>👤 Previzualizare card cititor</h4>
            <div class="card-grid">
                <div class="card-item">
                    <span class="card-label">Cod:</span>
                    <span class="card-value" id="previewCod"><?php echo htmlspecialchars($cititor['cod_bare']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Nume:</span>
                    <span class="card-value" id="previewNume"><?php echo htmlspecialchars($cititor['nume']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Prenume:</span>
                    <span class="card-value" id="previewPrenume"><?php echo htmlspecialchars($cititor['prenume']); ?></span>
                </div>
                <div class="card-item">
                    <span class="card-label">Telefon:</span>
                    <span class="card-value" id="previewTelefon"><?php echo htmlspecialchars($cititor['telefon'] ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="index.php" class="home-link">🏠 Acasă</a>
            <a href="cititori.php" class="back-link">← Înapoi la lista cititori</a>
        </div>

        <!-- Butoane ștergere -->
        <button type="button" class="delete-btn" onclick="solicitaParola(false)">
            🗑️ Șterge cititorul (doar dacă nu are istoric)
        </button>

        <button type="button" class="delete-btn" onclick="solicitaParola(true)" 
                style="background: linear-gradient(135deg, #ff0000 0%, #990000 100%);">
            💥 Șterge cititorul CU TOT ISTORICUL
        </button>
    </div>

    <!-- Modal pentru parolă admin -->
    <div id="modalOverlay" class="modal-overlay" onclick="inchideModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="warning-icon">🔐</div>
            <div class="modal-header">
                <h3>Autentificare Administrator</h3>
                <p>Introdu parola pentru a continua</p>
            </div>
            <input type="password" id="parolaAdmin" class="modal-input" 
                   placeholder="Parolă administrator" 
                   onkeypress="if(event.key === 'Enter') confirmaParola()">
            <div class="modal-buttons">
                <button class="modal-btn btn-confirm" onclick="confirmaParola()">
                    ✓ Confirmă
                </button>
                <button class="modal-btn btn-cancel" onclick="inchideModal()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pentru eroare cod duplicat -->
    <div id="errorModalOverlay" class="error-modal-overlay" onclick="inchideErrorModal()">
        <div class="error-modal-content" onclick="event.stopPropagation()">
            <div class="error-icon">⚠️</div>
            <div class="error-modal-header">
                <h3>Cod de bare duplicat</h3>
                <p>Codul introdus este deja folosit de alt cititor</p>
            </div>
            <div class="error-details">
                <strong>Cod duplicat:</strong>
                <span class="cod-duplicat" id="errorCodDuplicat"></span>
                <div class="cititor-existent">
                    <strong>Folosit de:</strong> <span id="errorCititorExistent"></span>
                </div>
            </div>
            <div class="error-modal-buttons">
                <button class="error-modal-btn error-modal-btn-ok" onclick="inchideErrorModal()">
                    ✓ Am înțeles
                </button>
            </div>
        </div>
    </div>

    <!-- Modal pentru upload imagine -->
    <div id="uploadModalOverlay" class="upload-modal-overlay" onclick="inchideUploadModal()">
        <div class="upload-modal-content" onclick="event.stopPropagation()">
            <div style="text-align: center;">
                <h3 style="color: #667eea; font-size: 1.5em; margin-bottom: 5px;">📷 Selectează imagine cititor</h3>
                <p style="color: #666; font-size: 0.9em;">Selectează sau trage o imagine aici</p>
            </div>
            <div class="drop-zone" id="dropZone">
                <p style="font-size: 1.2em; color: #667eea; margin-bottom: 10px;">📁 Pick image to upload</p>
                <p style="color: #666; font-size: 0.9em;">Sau trage imaginea aici</p>
            </div>
            <img id="imagePreview" class="image-preview" alt="Preview">
            <div class="upload-buttons" id="uploadButtons" style="display: flex;">
                <button type="button" style="background: #28a745; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; margin-right: 10px;" onclick="inchideUploadModal()">
                    ✓ Confirmă
                </button>
                <button type="button" style="background: #6c757d; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1;" onclick="anuleazaUpload()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>

    <!-- Form ascuns pentru ștergere -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="delete" value="true">
        <input type="hidden" name="admin_password" id="parolaHidden">
        <input type="hidden" name="sterge_istoric" id="stergeIstoric" value="false">
    </form>

    <script>
        let stergeIstoricFlag = false;

        function solicitaParola(cuIstoric) {
            stergeIstoricFlag = cuIstoric;
            
            // Mesaj de confirmare înainte de a cere parola
            let mesajConfirmare = cuIstoric 
                ? '🚨 ATENȚIE!\n\nVei șterge cititorul ȘI TOATE împrumuturile lui din istoric!\n\nAceastă acțiune NU poate fi anulată!\n\nEști absolut sigur?'
                : '⚠️ Ești sigur că vrei să ștergi acest cititor?\n\nAceastă acțiune NU poate fi anulată!';
            
            if (confirm(mesajConfirmare)) {
                document.getElementById('modalOverlay').style.display = 'block';
                setTimeout(() => {
                    document.getElementById('parolaAdmin').focus();
                }, 100);
            }
        }

        function confirmaParola() {
            const parola = document.getElementById('parolaAdmin').value;
            
            if (!parola) {
                alert('❌ Te rog să introduci parola!');
                return;
            }
            
            // Setează valorile în form
            document.getElementById('parolaHidden').value = parola;
            document.getElementById('stergeIstoric').value = stergeIstoricFlag ? 'true' : 'false';
            
            // Trimite formularul
            document.getElementById('deleteForm').submit();
        }

        function inchideModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('parolaAdmin').value = '';
        }

        // Închide modal cu ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                inchideModal();
                inchideErrorModal();
            }
        });

        // Funcții pentru modal eroare cod duplicat
        function deschideErrorModal(cod, cititor) {
            document.getElementById('errorCodDuplicat').textContent = cod;
            document.getElementById('errorCititorExistent').textContent = cititor;
            document.getElementById('errorModalOverlay').style.display = 'block';
        }

        function inchideErrorModal() {
            document.getElementById('errorModalOverlay').style.display = 'none';
        }

        // Verifică dacă există eroare de cod duplicat și afișează modalul
        <?php if (isset($cod_duplicat) && $cod_duplicat && isset($cod_duplicat_mesaj)): ?>
        window.addEventListener('load', function() {
            setTimeout(function() {
                deschideErrorModal('<?php echo htmlspecialchars($cod_bare, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($cititor_duplicat_nume, ENT_QUOTES, 'UTF-8'); ?>');
            }, 300);
        });
        <?php endif; ?>

        // Cod bare original pentru comparare
        const codBareOriginal = '<?php echo addslashes($cititor['cod_bare']); ?>';
        
        // Funcție pentru extragerea statutului din cod de bare
        function extrageStatutDinCod(cod) {
            if (!cod || cod.length < 2) return '14';
            
            // Cod USER → statut 14
            if (cod.toUpperCase().startsWith('USER')) return '14';
            
            // Cod 8 cifre care începe cu 14016 → statut 14
            if (/^\d{8}$/.test(cod) && cod.startsWith('14016')) return '14';
            
            // Cod 12 cifre → extrage primele 2
            if (/^\d{12}$/.test(cod)) {
                const statut = cod.substring(0, 2);
                if (parseInt(statut) >= 11 && parseInt(statut) <= 17) {
                    return statut;
                }
            }
            
            // Fallback
            return '14';
        }
        
        // Handler pentru schimbarea codului de bare
        document.getElementById('cod_bare').addEventListener('input', function() {
            const codNou = this.value.trim();
            const selectStatut = document.getElementById('statut_select');
            const statutInfo = document.getElementById('statut_info');
            
            if (codNou !== codBareOriginal && codNou.length >= 2) {
                // Codul a fost schimbat - activează dropdown și setează noul statut
                const statutNou = extrageStatutDinCod(codNou);
                selectStatut.disabled = false;
                selectStatut.style.background = '#fff';
                selectStatut.style.cursor = 'pointer';
                selectStatut.value = statutNou;
                statutInfo.innerHTML = '🔓 Statut actualizat automat la <strong>' + statutNou + '</strong> conform noului cod de bare';
                statutInfo.style.color = '#28a745';
            } else {
                // Codul este cel original - blochează dropdown
                selectStatut.disabled = true;
                selectStatut.style.background = '#f5f5f5';
                selectStatut.style.cursor = 'not-allowed';
                selectStatut.value = '<?php echo addslashes($statut_cititor); ?>';
                statutInfo.innerHTML = '🔒 Statutul se poate schimba doar prin modificarea codului de bare';
                statutInfo.style.color = '#666';
            }
            
            updatePreview();
        });
        
        // Actualizare previzualizare în timp real
        function updatePreview() {
            const cod = document.querySelector('input[name="cod_bare"]').value.trim();
            const nume = document.querySelector('input[name="nume"]').value.trim();
            const prenume = document.querySelector('input[name="prenume"]').value.trim();
            const telefon = document.querySelector('input[name="telefon"]').value.trim();

            document.getElementById('previewCod').textContent = cod || '-';
            document.getElementById('previewNume').textContent = nume || '-';
            document.getElementById('previewPrenume').textContent = prenume || '-';
            document.getElementById('previewTelefon').textContent = telefon || '-';
        }

        // Adaugă event listeners pentru actualizare în timp real
        document.querySelector('input[name="nume"]').addEventListener('input', updatePreview);
        document.querySelector('input[name="prenume"]').addEventListener('input', updatePreview);
        document.querySelector('input[name="telefon"]').addEventListener('input', updatePreview);

        // Validare email simplă
        document.querySelector('input[name="email"]').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#ddd';
            }
        });

        // Upload imagine
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        const imagePreview = document.getElementById('imagePreview');
        const uploadButtons = document.getElementById('uploadButtons');
        const userIconContainer = document.querySelector('.user-icon-container');

        function deschideUploadModal() {
            document.getElementById('uploadModalOverlay').style.display = 'block';
        }

        function inchideUploadModal() {
            document.getElementById('uploadModalOverlay').style.display = 'none';
        }

        function anuleazaUpload() {
            fileInput.value = '';
            imagePreview.style.display = 'none';
            imagePreview.src = '';
            uploadButtons.style.display = 'none';
            inchideUploadModal();
        }

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (!file) {
                alert('❌ Te rog să selectezi o imagine!');
                return;
            }

            if (!file.type.startsWith('image/')) {
                alert('❌ Te rog să selectezi o imagine validă!');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('❌ Imaginea este prea mare! Maxim 5MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                uploadButtons.style.display = 'flex';
                
                // Actualizează preview-ul în container
                const img = userIconContainer.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                } else {
                    const svg = userIconContainer.querySelector('svg');
                    if (svg) {
                        svg.style.display = 'none';
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.style.width = '100%';
                        newImg.style.height = '100%';
                        newImg.style.objectFit = 'cover';
                        newImg.style.borderRadius = '50%';
                        userIconContainer.appendChild(newImg);
                    }
                }
            };
            reader.readAsDataURL(file);
        }

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect({ target: fileInput });
            }
        });

        fileInput.addEventListener('change', handleFileSelect);
    </script>

    <!-- Footer -->
    <div class="app-footer">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
</body>
</html>