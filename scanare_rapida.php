<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';
require_once 'functions_vizare.php';
require_once 'functions_sesiuni.php';
require_once 'functions_coduri_aleph.php';
require_once 'send_email.php';
require_once 'functions_email_templates.php';
// sistem_notificari.php nu trebuie inclus aici - este un script standalone cu HTML complet
// Configurare email pentru trimitere notificări
$config_email = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'YOUR-EMAIL@gmail.com',
    'smtp_pass' => 'GOOGLE SECRET PASSWORD',
    'from_email' => 'YOUR-EMAIL@gmail.com',
    'from_name' => 'Biblioteca Academiei Române - Iași'
];

// Verifică și închide sesiunile expirate
verificaSesiuniExpirate($pdo);

$mesaj = '';
$tip_mesaj = '';
$status_vizare = null;
$cod_cititor_curent = null;
$cititor_necunoscut = null;

// CLEAR error când se face refresh sau cancel
if (isset($_GET['clear_error'])) {
    unset($_SESSION['error_cititor_necunoscut']);
    header('Location: scanare_rapida.php');
    exit;
}

// Resetează cititor
if (isset($_GET['reset'])) {
    unset($_SESSION['cititor_activ']);
    header('Location: scanare_rapida.php');
    exit;
}

// Verificare automată cod cititor dacă vine din adauga_cititor.php (după adăugare)
if (isset($_GET['cod_cititor']) && !empty($_GET['cod_cititor'])) {
    $cod_verificare = trim($_GET['cod_cititor']);
    unset($_SESSION['error_cititor_necunoscut']); // Șterge eroarea dacă există
    
    // Verifică dacă cititorul există acum în baza de date
    $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod_verificare]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cititor) {
        // Procesează cititorul ca și cum ar fi fost scanat
        $tip_cod = detecteazaTipCod($cod_verificare);
        
        if ($tip_cod === 'user' || $tip_cod === 'aleph') {
            // Creează sau reînnoiește sesiunea utilizatorului
            $sesiune_id = creazaSesiuneUtilizator($pdo, $cod_verificare);
            
            // TRACKING: Înregistrează scanarea permisului
            inregistreazaActiuneTracking($pdo, $cod_verificare, 'scanare_permis', null, $sesiune_id, [
                'nume' => $cititor['nume'],
                'prenume' => $cititor['prenume']
            ]);
            
            $_SESSION['cititor_activ'] = $cititor;
            $_SESSION['sesiune_id'] = $sesiune_id;
            
            $rezultat_auto_vizare = vizeazaPermisAutomat($pdo, $cod_verificare);
            $status_vizare = verificaVizarePermis($pdo, $cod_verificare);
            $cod_cititor_curent = $cod_verificare;
            
            $stmt = $pdo->prepare("INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare) VALUES (?, CURDATE(), CURTIME(), NOW())");
            $stmt->execute([$cod_verificare]);
            
            $are_intarzieri = areIntarzieri($pdo, $cod_verificare);
            $numar_carti = numarCartiImprumutate($pdo, $cod_verificare);
            
            // Construiește mesajul
            if (isset($_GET['nou']) && $_GET['nou'] == '1') {
                $mesaj = "✅ <strong>Cititor nou adăugat și înregistrat cu succes!</strong><br>";
                $mesaj .= "👤 Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>!";
            } else {
                if ($rezultat_auto_vizare['vizat'] && strpos($rezultat_auto_vizare['mesaj'], 'AUTOMAT') !== false) {
                    $mesaj = $rezultat_auto_vizare['mesaj'] . "<br>✅ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>!";
                } elseif ($status_vizare['vizat']) {
                    $mesaj = "✅ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>! Permis VIZAT.";
                } else {
                    $mesaj = "⚠️ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>! ATENȚIE: " . $status_vizare['mesaj'];
                }
            }
            
            if ($are_intarzieri) {
                $mesaj .= "<br>⚠️ <strong>ATENȚIE:</strong> Aveți cărți cu întârzieri! Vă rugăm să le returnați.";
            }
            $mesaj .= "<br>📚 <strong>Cărți împrumutate:</strong> $numar_carti/6";
            $tip_mesaj = "success";
            
            // Salvează mesajul în sesiune pentru a-l afișa după redirect
            $_SESSION['success_message'] = $mesaj;
            $_SESSION['success_type'] = $tip_mesaj;
            
            // Redirect fără parametri pentru a curăța URL-ul
            header('Location: scanare_rapida.php');
            exit;
        }
    }
}

// Afișează mesajul de succes din sesiune (după adăugare cititor nou)
if (isset($_SESSION['success_message'])) {
    $mesaj = $_SESSION['success_message'];
    $tip_mesaj = $_SESSION['success_type'];
    unset($_SESSION['success_message']);
    unset($_SESSION['success_type']);
}

// Restaurează eroarea din sesiune dacă există
if (isset($_SESSION['error_cititor_necunoscut'])) {
    $cititor_necunoscut = $_SESSION['error_cititor_necunoscut'];
    $mesaj = "❌ Cititor necunoscut: " . htmlspecialchars($cititor_necunoscut);
    $tip_mesaj = "danger";
}

// Procesare vizare permis
if (isset($_POST['vizeaza_permis'])) {
    $cod_cititor = trim($_POST['cod_cititor_vizare']);
    $rezultat = vizeazaPermis($pdo, $cod_cititor);
    
    if ($rezultat['success']) {
        $mesaj = $rezultat['mesaj'];
        $tip_mesaj = 'success';
        $status_vizare = verificaVizarePermis($pdo, $cod_cititor);
        $cod_cititor_curent = $cod_cititor;
        
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
        $stmt->execute([$cod_cititor]);
        $_SESSION['cititor_activ'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $mesaj = $rezultat['mesaj'];
        $tip_mesaj = 'danger';
    }
}

// Procesare scanare cod de bare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['vizeaza_permis'])) {
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');

    // CLEAR error anterior
    unset($_SESSION['error_cititor_necunoscut']);

    if (empty($cod_scanat)) {
        $mesaj = "⚠️ Cod invalid!";
        $tip_mesaj = "warning";
    } else {
        try {
            // Detectează tipul de cod (USER*** sau Aleph)
            $tip_cod = detecteazaTipCod($cod_scanat);
            
            // Verifică dacă este cod de cititor (USER sau Aleph)
            if ($tip_cod === 'user' || $tip_cod === 'aleph') {
                // Folosește funcția helper pentru a găsi cititorul
                $cititor = gasesteCititorDupaCod($pdo, $cod_scanat);
                
                // Fallback la metoda veche dacă funcția helper nu găsește
                if (!$cititor) {
                    $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
                    $stmt->execute([$cod_scanat]);
                    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($cititor) {
                    // Verifică dacă utilizatorul este blocat
                    $status_blocare = esteUtilizatorBlocat($pdo, $cod_scanat);
                    if ($status_blocare['blocat']) {
                        $mesaj = "🔴 <strong>UTILIZATOR BLOCAT!</strong><br>Motiv: " . htmlspecialchars($status_blocare['motiv']);
                        $tip_mesaj = "danger";
                    } else {
                        // Creează sau reînnoiește sesiunea utilizatorului
                        $sesiune_id = creazaSesiuneUtilizator($pdo, $cod_scanat);
                        
                        // 🔥 TRACKING: Înregistrează scanarea permisului
                        inregistreazaActiuneTracking($pdo, $cod_scanat, 'scanare_permis', null, $sesiune_id, [
                            'nume' => $cititor['nume'],
                            'prenume' => $cititor['prenume']
                        ]);
                        
                        $_SESSION['cititor_activ'] = $cititor;
                        $_SESSION['sesiune_id'] = $sesiune_id;
                        
                        $rezultat_auto_vizare = vizeazaPermisAutomat($pdo, $cod_scanat);
                        $status_vizare = verificaVizarePermis($pdo, $cod_scanat);
                        $cod_cititor_curent = $cod_scanat;
                        
                        $stmt = $pdo->prepare("INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare) VALUES (?, CURDATE(), CURTIME(), NOW())");
                        $stmt->execute([$cod_scanat]);
                        
                        // Verifică dacă are întârzieri
                        $are_intarzieri = areIntarzieri($pdo, $cod_scanat);
                        $numar_carti = numarCartiImprumutate($pdo, $cod_scanat);
                        
                        if ($rezultat_auto_vizare['vizat'] && strpos($rezultat_auto_vizare['mesaj'], 'AUTOMAT') !== false) {
                            $mesaj = $rezultat_auto_vizare['mesaj'] . "<br>✅ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>!";
                            if ($are_intarzieri) {
                                $mesaj .= "<br>⚠️ <strong>ATENȚIE:</strong> Aveți cărți cu întârzieri! Vă rugăm să le returnați.";
                            }
                            $mesaj .= "<br>📚 <strong>Cărți împrumutate:</strong> $numar_carti/6";
                            $tip_mesaj = "success";
                        } elseif ($status_vizare['vizat']) {
                            $mesaj = "✅ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>! Permis VIZAT.";
                            if ($are_intarzieri) {
                                $mesaj .= "<br>⚠️ <strong>ATENȚIE:</strong> Aveți cărți cu întârzieri! Vă rugăm să le returnați.";
                            }
                            $mesaj .= "<br>📚 <strong>Cărți împrumutate:</strong> $numar_carti/6";
                            $tip_mesaj = "success";
                        } else {
                            $mesaj = "⚠️ Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>! ATENȚIE: " . $status_vizare['mesaj'];
                            $tip_mesaj = "warning";
                        }
                    }
                } else {
                    // SALVEAZĂ eroarea în sesiune
                    $_SESSION['error_cititor_necunoscut'] = $cod_scanat;
                    $cititor_necunoscut = $cod_scanat;
                    $mesaj = "❌ Cititor necunoscut: $cod_scanat";
                    $tip_mesaj = "danger";
                }

            // Detectează CARTE - dacă nu este detectat ca user sau aleph, este carte
            } else {
                // 🔥 CAZ 1: Fără utilizator scanat - doar afișează dacă există sau nu
                if (!isset($_SESSION['cititor_activ'])) {
                    // Caută cartea în baza locală
                    $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ? OR cota = ?");
                    $stmt->execute([$cod_scanat, $cod_scanat]);
                    $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Dacă nu există în baza locală, caută în Aleph
                    if (!$carte) {
                        require_once 'aleph_api.php';
                        $rezultat_aleph = cautaCarteInAleph($cod_scanat, 'AUTO');
                        
                        if ($rezultat_aleph['success']) {
                            $date_carte = $rezultat_aleph['data'];
                            $mesaj = "ℹ️ <strong>Cartea există în Aleph:</strong><br>";
                            $mesaj .= "📚 <strong>Titlu:</strong> " . htmlspecialchars($date_carte['titlu']) . "<br>";
                            if (!empty($date_carte['autor'])) {
                                $mesaj .= "👤 <strong>Autor:</strong> " . htmlspecialchars($date_carte['autor']) . "<br>";
                            }
                            if (!empty($date_carte['cota'])) {
                                $mesaj .= "📍 <strong>Cota:</strong> " . htmlspecialchars($date_carte['cota']) . "<br>";
                            }
                            if (!empty($date_carte['barcode'])) {
                                $mesaj .= "🏷️ <strong>Barcode:</strong> " . htmlspecialchars($date_carte['barcode']) . "<br>";
                            }
                            $mesaj .= "<br>💡 <em>Scanați carnetul cititorului pentru a împrumuta sau returna cartea.</em>";
                            $tip_mesaj = "info";
                        } else {
                            $mesaj = "❌ <strong>Cartea nu există</strong><br>Cod de bare/cotă necunoscut: <strong>" . htmlspecialchars($cod_scanat) . "</strong><br>Nu există în baza locală și nici în Aleph!";
                            $tip_mesaj = "danger";
                        }
                    } else {
                        $mesaj = "ℹ️ <strong>Cartea există în baza locală:</strong><br>";
                        $mesaj .= "📚 <strong>Titlu:</strong> " . htmlspecialchars($carte['titlu']) . "<br>";
                        if (!empty($carte['autor'])) {
                            $mesaj .= "👤 <strong>Autor:</strong> " . htmlspecialchars($carte['autor']) . "<br>";
                        }
                        if (!empty($carte['cota'])) {
                            $mesaj .= "📍 <strong>Cota:</strong> " . htmlspecialchars($carte['cota']) . "<br>";
                        }
                        $mesaj .= "<br>💡 <em>Scanați carnetul cititorului pentru a împrumuta sau returna cartea.</em>";
                        $tip_mesaj = "info";
                    }
                } else {
                    // 🔥 CAZ 2: Cu utilizator scanat - procesează împrumut/returnare
                    $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
                    
                    // Verifică dacă utilizatorul este blocat
                    $status_blocare = esteUtilizatorBlocat($pdo, $cod_cititor);
                    if ($status_blocare['blocat']) {
                        $mesaj = "🔴 <strong>UTILIZATOR BLOCAT!</strong><br>Motiv: " . htmlspecialchars($status_blocare['motiv']);
                        $tip_mesaj = "danger";
                        $status_vizare = verificaVizarePermis($pdo, $cod_cititor);
                        $cod_cititor_curent = $cod_cititor;
                    } else {
                        // Verifică vizarea permisului
                        $status_vizare_temp = verificaVizarePermis($pdo, $cod_cititor);
                        
                        if (!$status_vizare_temp['vizat']) {
                            $mesaj = "🔴 ÎMPRUMUT BLOCAT! Permisul nu este vizat pentru anul curent!";
                            $tip_mesaj = "danger";
                            $status_vizare = $status_vizare_temp;
                            $cod_cititor_curent = $cod_cititor;
                        } else {
                            // Verifică dacă sesiunea este activă (30 secunde de la ultima acțiune)
                            if (!esteSesiuneActiva($pdo, $cod_cititor)) {
                                // Sesiunea a expirat - închide sesiunea
                                $sesiune_id_expirata = $_SESSION['sesiune_id'] ?? null;
                                inchideSesiuneUtilizator($pdo, $cod_cititor);
                                
                                // 🔥 TRACKING: Înregistrează expirarea sesiunii
                                inregistreazaActiuneTracking($pdo, $cod_cititor, 'sesiune_expirata', null, $sesiune_id_expirata, [
                                    'motiv' => 'Timeout 30 secunde sau 5 minute'
                                ]);
                                
                                unset($_SESSION['cititor_activ']);
                                unset($_SESSION['sesiune_id']);
                                $mesaj = "⏱️ <strong>Sesiunea a expirat!</strong><br>Nu ați scanat nicio carte timp de 30 secunde sau a trecut timpul de 5 minute.<br>Scanați din nou carnetul pentru a continua.";
                                $tip_mesaj = "warning";
                            } else {
                                $sesiune_id = $_SESSION['sesiune_id'] ?? null;
                                // Caută cartea în baza locală - mai întâi după cod_bare, apoi după cota
                                $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                                $stmt->execute([$cod_scanat]);
                                $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Dacă nu găsește după cod_bare, caută după cota
                                if (!$carte) {
                                    $stmt = $pdo->prepare("SELECT * FROM carti WHERE cota = ?");
                                    $stmt->execute([$cod_scanat]);
                                    $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                                }

                                // Dacă nu există în baza locală, caută în Aleph
                                if (!$carte) {
                                    require_once 'aleph_api.php';
                                    
                                    // 🔥 CĂUTARE AUTOMATĂ cu fallback (BAR → LOC → WRD)
                                    // Pentru cota, strategia AUTO va încerca LOC (location/call number)
                                    $rezultat_aleph = cautaCarteInAleph($cod_scanat, 'AUTO');
                                    
                                    if ($rezultat_aleph['success']) {
                                        $date_carte = $rezultat_aleph['data'];
                                        
                                        // Folosește barcode-ul din Aleph dacă există, altfel codul scanat
                                        $barcode_final = !empty($date_carte['barcode']) ? $date_carte['barcode'] : $cod_scanat;
                                        $cota_final = !empty($date_carte['cota']) ? $date_carte['cota'] : '';
                                        
                                        // Verifică dacă cartea există deja (evită duplicate) - după barcode sau cota
                                        $carte_existenta = null;
                                        
                                        // Verifică mai întâi după barcode
                                        if (!empty($barcode_final)) {
                                            $stmt_check = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                                            $stmt_check->execute([$barcode_final]);
                                            $carte_existenta = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                        }
                                        
                                        // Dacă nu găsește după barcode, verifică după cota
                                        if (!$carte_existenta && !empty($cota_final)) {
                                            $stmt_check = $pdo->prepare("SELECT * FROM carti WHERE cota = ?");
                                            $stmt_check->execute([$cota_final]);
                                            $carte_existenta = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                        }
                                        
                                        if ($carte_existenta) {
                                            // Carte deja importată
                                            $carte = $carte_existenta;
                                            $mesaj = "ℹ️ Carte găsită în baza locală: <strong>{$carte['titlu']}</strong><br>";
                                            $tip_mesaj = "info";
                                        } else {
                                            // Import nou
                                            $stmt_import = $pdo->prepare("
                                                INSERT INTO carti (
                                                    cod_bare, titlu, autor, isbn, cota, sectiune, data_adaugare
                                                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                                            ");
                                            $stmt_import->execute([
                                                $barcode_final,
                                                $date_carte['titlu'],
                                                $date_carte['autor'] ?? '',
                                                $date_carte['isbn'] ?? '',
                                                $date_carte['cota'] ?? '',
                                                $date_carte['sectiune'] ?? ''
                                            ]);
                                            
                                            // Re-încarcă cartea din DB
                                            $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                                            $stmt->execute([$barcode_final]);
                                            $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            $mesaj = "✅ Carte importată automat din Aleph: <strong>{$date_carte['titlu']}</strong><br>";
                                            $tip_mesaj = "info";
                                        }
                                    } else {
                                        $mesaj = "❌ Cod de bare/cotă necunoscut: $cod_scanat<br>Nu există în baza locală și nici în Aleph!";
                                        $tip_mesaj = "danger";
                                        $carte = null;
                                    }
                                }
                                
                                // Procesează împrumutul/returnarea
                                if ($carte) {
                                    // 🔥 FOLOSEȘTE BARCODE-UL REAL din baza de date
                                    $cod_carte_db = $carte['cod_bare'];
                                    
                                    // Verifică dacă cartea e deja împrumutată de acest utilizator
                                    $stmt = $pdo->prepare("
                                        SELECT * FROM imprumuturi 
                                        WHERE cod_carte = ? 
                                        AND cod_cititor = ?
                                        AND data_returnare IS NULL
                                    ");
                                    $stmt->execute([$cod_carte_db, $cod_cititor]);
                                    $imprumut_existent = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($imprumut_existent) {
                                        // RETURNARE AUTOMATĂ - cartea este deja împrumutată de acest utilizator
                                        $stmt = $pdo->prepare("
                                            UPDATE imprumuturi 
                                            SET data_returnare = NOW(), status = 'returnat' 
                                            WHERE id = ?
                                        ");
                                        $stmt->execute([$imprumut_existent['id']]);
                                        
                                        // 🔥 TRACKING: Înregistrează returnarea cărții
                                        inregistreazaActiuneTracking($pdo, $cod_cititor, 'scanare_carte_returnare', $cod_carte_db, $sesiune_id, [
                                            'titlu' => $carte['titlu'],
                                            'imprumut_id' => $imprumut_existent['id']
                                        ]);
                                        
                                        $mesaj .= "📥 <strong>Carte returnată:</strong> {$carte['titlu']}";
                                        $tip_mesaj = "info";
                                    } else {
                                        // 🔥 VERIFICARE DUBLURI: Verifică dacă cartea este deja împrumutată de același utilizator
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM imprumuturi 
                                            WHERE cod_cititor = ? 
                                            AND cod_carte = ?
                                            AND data_returnare IS NULL
                                        ");
                                        $stmt->execute([$cod_cititor, $cod_carte_db]);
                                        $imprumut_duplicat = $stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($imprumut_duplicat) {
                                            // Cartea este deja împrumutată de același utilizator - DUBLU!
                                            $mesaj .= "⚠️ <strong>Cartea este deja împrumutată de dvs!</strong><br>Cartea <strong>{$carte['titlu']}</strong> este deja în lista dvs. de împrumuturi.<br>Nu se poate împrumuta din nou până nu este returnată.";
                                            $tip_mesaj = "warning";
                                        } else {
                                            // Verifică dacă cartea este împrumutată de alt utilizator
                                            $stmt = $pdo->prepare("
                                                SELECT i.*, c.nume, c.prenume 
                                                FROM imprumuturi i
                                                JOIN cititori c ON i.cod_cititor = c.cod_bare
                                                WHERE i.cod_carte = ? 
                                                AND i.data_returnare IS NULL
                                            ");
                                            $stmt->execute([$cod_carte_db]);
                                            $imprumut_alt_utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($imprumut_alt_utilizator) {
                                                $mesaj .= "⚠️ <strong>Cartea este deja împrumutată</strong> de: {$imprumut_alt_utilizator['nume']} {$imprumut_alt_utilizator['prenume']}!";
                                                $tip_mesaj = "warning";
                                            } else {
                                                // Verifică limita de 6 cărți
                                                $numar_carti_imprumutate = numarCartiImprumutate($pdo, $cod_cititor);
                                                
                                                if ($numar_carti_imprumutate >= 6) {
                                                    $mesaj .= "⚠️ <strong>LIMITĂ DEPĂȘITĂ!</strong><br>Aveți deja 6 cărți împrumutate. Nu puteți împrumuta mai multe cărți până nu returnați cel puțin una.";
                                                    $tip_mesaj = "warning";
                                                } else {
                                                    // ÎMPRUMUT NOU - calculează data scadenței (14 zile de la data împrumutului)
                                                    $data_scadenta = date('Y-m-d', strtotime('+14 days'));
                                                    $stmt = $pdo->prepare("
                                                        INSERT INTO imprumuturi (cod_cititor, cod_carte, data_imprumut, data_scadenta, status) 
                                                        VALUES (?, ?, NOW(), ?, 'activ')
                                                    ");
                                                    $stmt->execute([
                                                        $cod_cititor,
                                                        $cod_carte_db,
                                                        $data_scadenta
                                                    ]);
                                                    
                                                    $imprumut_id = $pdo->lastInsertId();
                                                    
                                                    // 🔥 TRACKING: Înregistrează împrumutul cărții
                                                    inregistreazaActiuneTracking($pdo, $cod_cititor, 'scanare_carte_imprumut', $cod_carte_db, $sesiune_id, [
                                                        'titlu' => $carte['titlu'],
                                                        'imprumut_id' => $imprumut_id,
                                                        'data_scadenta' => $data_scadenta
                                                    ]);
                                                    
                                                    // Trimite email de confirmare (dacă este configurat)
                                                    try {
                                                        // Obține datele complete ale cititorului
                                                        $stmt_cititor = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
                                                        $stmt_cititor->execute([$cod_cititor]);
                                                        $cititor_complet = $stmt_cititor->fetch(PDO::FETCH_ASSOC);
                                                        
                                                        // Trimite email doar dacă cititorul are email
                                                        if ($cititor_complet && !empty($cititor_complet['email'])) {
                                                            $carti_imprumutate = [[
                                                                'titlu' => $carte['titlu'],
                                                                'autor' => $carte['autor'] ?? '',
                                                                'cod_bare' => $carte['cod_bare'],
                                                                'data_imprumut' => date('Y-m-d'),
                                                                'locatie_completa' => $carte['locatie_completa'] ?? ''
                                                            ]];
                                                            
                                                            trimiteEmailPersonalizat(
                                                                $cititor_complet['email'],
                                                                'imprumut',
                                                                $cititor_complet,
                                                                $carti_imprumutate,
                                                                $config_email
                                                            );
                                                        }
                                                    } catch (Exception $e) {
                                                        // Ignoră erorile de email - împrumutul este înregistrat oricum
                                                        error_log("Eroare trimitere email împrumut: " . $e->getMessage());
                                                    }
                                                    
                                                    $numar_ramase = 6 - ($numar_carti_imprumutate + 1);
                                                    $mesaj .= "✅ <strong>Carte împrumutată:</strong> {$carte['titlu']}<br>";
                                                    $mesaj .= "📅 <strong>Scadență:</strong> $data_scadenta<br>";
                                                    $mesaj .= "📚 <strong>Cărți împrumutate:</strong> " . ($numar_carti_imprumutate + 1) . "/6";
                                                    if ($numar_ramase > 0) {
                                                        $mesaj .= " (Mai puteți împrumuta $numar_ramase cărți)";
                                                    }
                                                    $tip_mesaj = "success";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

        } catch (PDOException $e) {
            $mesaj = "❌ Eroare: " . $e->getMessage();
            $tip_mesaj = "danger";
        }
    }
}

$cititor_activ = $_SESSION['cititor_activ'] ?? null;

if ($cititor_activ && !$status_vizare) {
    $status_vizare = verificaVizarePermis($pdo, $cititor_activ['cod_bare']);
    $cod_cititor_curent = $cititor_activ['cod_bare'];
}

// STATISTICI ZILNICE
$stats_azi = [
    'imprumuturi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_imprumut) = CURDATE()")->fetchColumn(),
    'returnari' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE DATE(data_returnare) = CURDATE()")->fetchColumn(),
    'vizitatori' => $pdo->query("SELECT COUNT(DISTINCT cod_cititor) FROM imprumuturi WHERE DATE(data_imprumut) = CURDATE()")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanare Rapidă - Bibliotecă</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .scan-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        .scan-input {
            font-size: 2rem;
            text-align: center;
            border: 3px solid #667eea;
            border-radius: 15px;
            padding: 20px;
        }
        .scan-input:focus {
            border-color: #764ba2;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }
        
        /* Status vizare - în colț */
        .status-vizare-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            z-index: 10;
        }
        
        .status-vizat {
            background: #28a745;
            color: white;
        }
        
        .status-nevizat {
            background: #dc3545;
            color: white;
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .btn-vizeaza-mini {
            display: block;
            margin-top: 8px;
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 15px;
        }
        
        .btn-add-cititor {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-add-cititor:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        /* Statistici zilnice */
        .stats-daily {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.imprumuturi {
            border-left: 5px solid #28a745;
        }
        
        .stat-card.returnari {
            border-left: 5px solid #17a2b8;
        }
        
        .stat-card.vizitatori {
            border-left: 5px solid #ffc107;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="scan-container">
        <!-- Status vizare în colț -->
        <?php if ($status_vizare && $cod_cititor_curent): ?>
        <div class="status-vizare-badge <?= $status_vizare['vizat'] ? 'status-vizat' : 'status-nevizat' ?>">
            <?= $status_vizare['icon'] ?> <?= $status_vizare['vizat'] ? 'VIZAT 2025' : 'NEVIZAT!' ?>
            
            <?php if (!$status_vizare['vizat']): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="cod_cititor_vizare" value="<?= htmlspecialchars($cod_cititor_curent) ?>">
                <button type="submit" name="vizeaza_permis" class="btn btn-light btn-vizeaza-mini">
                    Vizează acum
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <h1 class="text-center mb-4" style="color: #667eea;">📚 Scanare Rapidă</h1>

        <?php if ($mesaj): ?>
        <div class="alert alert-<?= $tip_mesaj ?> alert-dismissible fade show" role="alert">
            <?= $mesaj ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="window.location.href='?clear_error=1'"></button>
        </div>
        
        <!-- BUTON pentru cititor necunoscut -->
        <?php if ($cititor_necunoscut): ?>
        <div class="text-center my-4">
            <a href="adauga_cititor.php?cod=<?= urlencode($cititor_necunoscut) ?>" class="btn-add-cititor">
                👤 Adaugă cititor nou: <?= htmlspecialchars($cititor_necunoscut) ?>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- STATISTICI ZILNICE -->
        <div class="stats-daily">
            <div class="stat-card imprumuturi">
                <div class="stat-icon">📤</div>
                <div class="stat-number"><?= $stats_azi['imprumuturi'] ?></div>
                <div class="stat-label">Împrumuturi azi</div>
            </div>
            <div class="stat-card returnari">
                <div class="stat-icon">📥</div>
                <div class="stat-number"><?= $stats_azi['returnari'] ?></div>
                <div class="stat-label">Returnări azi</div>
            </div>
            <div class="stat-card vizitatori">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= $stats_azi['vizitatori'] ?></div>
                <div class="stat-label">Vizitatori</div>
            </div>
        </div>

        <form method="POST" id="formScanare">
            <div class="mb-3">
                <input 
                    type="text" 
                    name="cod_scanat" 
                    id="cod_scanat" 
                    class="form-control scan-input" 
                    placeholder="Scanați codul de bare..." 
                    autofocus
                    autocomplete="off">
            </div>
        </form>

        <div class="d-grid gap-2">
            <a href="imprumuturi.php" class="btn btn-primary btn-lg">📋 Vezi Împrumuturi</a>
            <a href="raport_prezenta.php" class="btn btn-info btn-lg">📊 Raport Prezență</a>
            <a href="lista_nevizati.php" class="btn btn-warning btn-lg">⚠️ Lista Permise Nevizate</a>
            <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='?clear_error=1&reset=1'">🔄 Resetează Cititor</button>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <a href="index.php" class="btn btn-success btn-lg w-50">🏠 Acasă</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const inputScanare = document.getElementById('cod_scanat');
        const formScanare = document.getElementById('formScanare');
        let timeoutScanare = null;

        inputScanare.focus();
        
        document.addEventListener('click', () => {
            inputScanare.focus();
        });

        inputScanare.addEventListener('input', function(e) {
            if (timeoutScanare) {
                clearTimeout(timeoutScanare);
            }

            this.style.borderColor = '#28a745';
            this.style.backgroundColor = '#d4edda';

            // Pentru scanner barcode - așteaptă puțin pentru a se termina scanarea
            // Scannerul trimite codul rapid + Enter, dar inputul se populează caracter cu caracter
            timeoutScanare = setTimeout(() => {
                const valoare = this.value.trim();
                
                // Doar dacă are cel puțin 4 caractere (pentru a evita submit-ul prea devreme)
                if (valoare.length >= 4) {
                    // Verifică dacă scannerul a terminat (nu mai primește input)
                    // Scannerul trimite Enter la sfârșit, deci dacă nu e Enter, așteaptă
                    formScanare.submit();
                }
            }, 500);
        });

        inputScanare.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const valoare = this.value.trim();
                
                if (valoare.length > 0) {
                    formScanare.submit();
                } else {
                    this.style.borderColor = '#667eea';
                    this.style.backgroundColor = 'white';
                }
            }
        });

        inputScanare.addEventListener('blur', function() {
            setTimeout(() => {
                this.focus();
            }, 10);
        });

        inputScanare.addEventListener('keydown', function(e) {
            console.log('Tastă:', e.key, 'Cod:', e.keyCode, 'Valoare:', this.value);
        });
    </script>
</body>
</html>