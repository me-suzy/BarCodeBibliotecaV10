<?php
// index.php - Pagina principală cu scanare coduri de bare

// Start output buffering pentru a evita output accidental înainte de redirect
ob_start();

// Setează encoding-ul înainte de orice output
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'functions_autentificare.php';

// Verifică autentificarea - redirecționează la login dacă nu este autentificat
verificaAutentificare('login.php', $pdo);

// ACTIVEAZĂ ERROR LOGGING PENTRU DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// LOG PENTRU DEBUG
$log_file = __DIR__ . '/debug_scanare.log';
function debug_log($message)
{
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== REQUEST START ===");
debug_log("Method: " . $_SERVER['REQUEST_METHOD']);
debug_log("POST: " . print_r($_POST, true));
debug_log("SESSION before: " . print_r($_SESSION, true));

// Configurare email pentru trimitere notificări
$config_email = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'YOUR-EMAIL@gmail.com',
    'smtp_pass' => 'GOOGLE SECRET PASSWORD',
    'from_email' => 'YOUR-EMAIL@gmail.com',
    'from_name' => 'Biblioteca Academiei Române - Iași'
];

// Acțiune pentru resetare cititor
// Gestionare acțiuni GET
if (isset($_GET['actiune'])) {
    if ($_GET['actiune'] === 'reseteaza_cititor') {
        unset($_SESSION['cititor_activ']);
        unset($_SESSION['carte_scanata']); // Șterge și cartea scanată când se resetează cititorul
        unset($_SESSION['cititor_necunoscut']); // Șterge și cititorul necunoscut
        unset($_SESSION['cititor_necunoscut_statut']); // Șterge și informațiile despre statut
        unset($_SESSION['carte_necunoscut']); // Șterge și cartea necunoscută
        unset($_SESSION['temp_message']);
        unset($_SESSION['temp_message_type']);
        unset($_SESSION['last_post_time']);
        ob_end_clean();
        header('Location: index.php');
        exit;
    } elseif ($_GET['actiune'] === 'anuleaza_carte') {
        // Anulează împrumutul pentru cartea scanată
        // Verifică atât în carte_scanata cât și în carte_scanata_pentru_anulare
        $carte_pentru_anulare = $_SESSION['carte_scanata'] ?? $_SESSION['carte_scanata_pentru_anulare'] ?? null;

        if ($carte_pentru_anulare && isset($_SESSION['cititor_activ'])) {
            try {
                $cod_carte = $carte_pentru_anulare['cod_bare'];
                $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];

                // Șterge împrumutul activ pentru această carte și cititor
                $stmt = $pdo->prepare("
                    UPDATE imprumuturi 
                    SET status = 'anulat', data_returnare = NOW() 
                    WHERE cod_carte = ? 
                    AND cod_cititor = ? 
                    AND data_returnare IS NULL
                    AND status = 'activ'
                ");
                $stmt->execute([$cod_carte, $cod_cititor]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['temp_message'] = "✅ Cartea '{$carte_pentru_anulare['titlu']}' a fost anulată din împrumut!";
                    $_SESSION['temp_message_type'] = "success";
                } else {
                    $_SESSION['temp_message'] = "ℹ️ Nu există împrumut activ pentru această carte.";
                    $_SESSION['temp_message_type'] = "info";
                }
            } catch (PDOException $e) {
                error_log("Eroare anulare carte: " . $e->getMessage());
                $_SESSION['temp_message'] = "❌ Eroare la anularea cărții: " . $e->getMessage();
                $_SESSION['temp_message_type'] = "danger";
            }
        }

        // Șterge ambele variabile din sesiune
        unset($_SESSION['carte_scanata']);
        unset($_SESSION['carte_scanata_pentru_anulare']);
        ob_end_clean();
        header('Location: index.php');
        exit;
    } elseif ($_GET['actiune'] === 'inchide_alert') {
        // Curăță toate variabilele de alertă
        unset($_SESSION['cititor_necunoscut']);
        unset($_SESSION['carte_necunoscut']);
        unset($_SESSION['temp_message']);
        unset($_SESSION['temp_message_type']);
        ob_end_clean();
        header('Location: index.php');
        exit;
    }
}

// Curăță date incomplete din sesiune la refresh (GET)
// Dacă nu vine din POST și nu există mesaj de succes, resetează datele incomplete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['cod_cititor']) && !isset($_GET['actiune'])) {
    // Șterge mesajele vechi dacă nu mai sunt relevante
    if (isset($_SESSION['temp_message']) && !isset($_SESSION['last_post_time'])) {
        unset($_SESSION['temp_message']);
        unset($_SESSION['temp_message_type']);
    }

    // Șterge timestamp-ul ultimului POST (nu mai e relevant după GET)
    unset($_SESSION['last_post_time']);

    // Verifică dacă există cititor activ fără mesaj - asta înseamnă starea e incompletă
    // (doar dacă nu există mesaj care să-l valideze)
    if (isset($_SESSION['cititor_activ']) && !isset($_SESSION['temp_message'])) {
        // Nu ștergem cititorul activ automat - lasă-l să rămână până la resetare manuală
    }
}

// Variabile pentru mesaje (se resetează la fiecare request)
$mesaj = null;
$tip_mesaj = null;

// Verificare automată cod cititor dacă vine din adauga_cititor.php (după adăugare)
if (isset($_GET['cod_cititor']) && !empty($_GET['cod_cititor'])) {
    require_once 'functions_coduri_aleph.php';

    $cod_verificare = trim($_GET['cod_cititor']);
    unset($_SESSION['error_cititor_necunoscut']); // Șterge eroarea dacă există
    unset($_SESSION['carte_necunoscut']); // Șterge codul de carte necunoscut dacă există
    unset($_SESSION['cititor_necunoscut']); // Șterge codul de cititor necunoscut dacă există

    // Verifică dacă cititorul există acum în baza de date
    $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod_verificare]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cititor) {
        // ✅ Șterge cititorul anterior când se scanează unul nou
        unset($_SESSION['cititor_activ']);
        unset($_SESSION['carte_scanata']);
        unset($_SESSION['cititor_necunoscut']); // Șterge și cititorul necunoscut dacă există
        unset($_SESSION['cititor_necunoscut_statut']);

        // Numără câte cărți are împrumutate activ (doar cele cu data_returnare IS NULL)
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) 
            FROM imprumuturi 
            WHERE cod_cititor = ? 
            AND data_returnare IS NULL
        ");
        $stmt_count->execute([$cititor['cod_bare']]);
        $numar_carti_active = (int) $stmt_count->fetchColumn();

        // ✅ NOU - Obține informații despre statut
        require_once 'functions_statute.php';

        // Actualizează statutul în baza de date dacă nu există
        if (empty($cititor['statut'])) {
            actualizeazaStatutCititor($pdo, $cititor['cod_bare']);
        }

        // Obține informații despre statut și limită
        $verificare_statut = poateImprumuta($pdo, $cititor['cod_bare'], $numar_carti_active);

        // Salvează scanarea în baza de date pentru raport prezență
        try {
            $stmt_prezenta = $pdo->prepare("
                INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare)
                VALUES (?, CURDATE(), CURTIME(), NOW())
            ");
            $stmt_prezenta->execute([$cititor['cod_bare']]);
            debug_log("Scanare salvata in sesiuni_biblioteca pentru: {$cititor['cod_bare']} (din adauga_cititor.php)");
        } catch (PDOException $e) {
            debug_log("Eroare la salvarea scanarii: " . $e->getMessage());
        }

        // Procesează cititorul ca și cum ar fi fost scanat
        $_SESSION['cititor_activ'] = [
            'id' => $cititor['id'],
            'cod_bare' => $cititor['cod_bare'],
            'nume' => $cititor['nume'],
            'prenume' => $cititor['prenume'],
            'numar_carti_imprumutate' => $numar_carti_active,
            'statut' => $verificare_statut['statut'],
            'nume_statut' => $verificare_statut['nume_statut'],
            'limita' => $verificare_statut['limita']
        ];

        // Construiește mesajul doar dacă este cititor nou
        if (isset($_GET['nou']) && $_GET['nou'] == '1') {
            $mesaj = "✅ <strong>Cititor nou adăugat și înregistrat cu succes!</strong><br>";
            $mesaj .= "👤 Bun venit, <strong>{$cititor['nume']} {$cititor['prenume']}</strong>!";
            $tip_mesaj = "success";

            // Salvează mesajul în sesiune pentru a-l afișa după redirect
            $_SESSION['temp_message'] = $mesaj;
            $_SESSION['temp_message_type'] = $tip_mesaj;
        }
        // Nu mai setăm mesaj pentru cititorul selectat - va apărea doar box-ul "Cititor activ"

        // Redirect fără parametri pentru a curăța URL-ul
        ob_end_clean();
        header('Location: index.php');
        exit;
    }
}

// Restaurează mesajele din sesiune (dacă există) și apoi șterge-le
if (isset($_SESSION['temp_message'])) {
    $mesaj = $_SESSION['temp_message'];
    $tip_mesaj = $_SESSION['temp_message_type'];
    debug_log("GET REQUEST - RESTAURAT DIN SESIUNE");
    debug_log("Mesaj restaurat: " . substr($mesaj, 0, 100));
    debug_log("Tip restaurat: $tip_mesaj");
    unset($_SESSION['temp_message']);
    unset($_SESSION['temp_message_type']);
} else {
    debug_log("GET REQUEST - NU EXISTA temp_message IN SESIUNE");
}

// Procesare formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST REQUEST DETECTAT");

    $actiune = $_POST['actiune'] ?? '';
    $cod_cititor = trim($_POST['cod_cititor'] ?? '');
    $cod_carte = trim($_POST['cod_carte'] ?? '');
    $cod_scanat = trim($_POST['cod_scanat'] ?? '');

    debug_log("Actiune: $actiune");
    debug_log("Cod scanat: $cod_scanat");

    // Logica de scanare automată (exact ca scanare_rapida.php)
    if ($actiune === 'scanare_automata' && !empty($cod_scanat)) {
        debug_log("PROCESARE SCANARE AUTOMATA");

        try {
            require_once 'functions_coduri_aleph.php';

            // Detectează tipul de cod (USER*** sau Aleph)
            $tip_cod = detecteazaTipCod($cod_scanat);
            debug_log("Tip cod detectat: $tip_cod");

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
                    debug_log("Cititor gasit: {$cititor['nume']} {$cititor['prenume']}");

                    // ✅ Șterge cititorul anterior când se scanează unul nou
                    unset($_SESSION['cititor_activ']);
                    unset($_SESSION['carte_scanata']);
                    unset($_SESSION['cititor_necunoscut']); // Șterge și cititorul necunoscut dacă există
                    unset($_SESSION['cititor_necunoscut_statut']);

                    // Numără câte cărți are împrumutate activ (doar cele cu data_returnare IS NULL)
                    $stmt_count = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM imprumuturi 
                        WHERE cod_cititor = ? 
                        AND data_returnare IS NULL
                    ");
                    $stmt_count->execute([$cititor['cod_bare']]);
                    $numar_carti_active = (int) $stmt_count->fetchColumn();

                    // ✅ NOU - Obține informații despre statut
                    require_once 'functions_statute.php';

                    // Actualizează statutul în baza de date dacă nu există
                    if (empty($cititor['statut'])) {
                        actualizeazaStatutCititor($pdo, $cititor['cod_bare']);
                    }

                    // Obține informații despre statut și limită
                    $verificare_statut = poateImprumuta($pdo, $cititor['cod_bare'], $numar_carti_active);

                    // Salvează scanarea în baza de date pentru raport prezență
                    try {
                        $stmt_prezenta = $pdo->prepare("
                            INSERT INTO sesiuni_biblioteca (cod_cititor, data, ora_intrare, timestamp_intrare)
                            VALUES (?, CURDATE(), CURTIME(), NOW())
                        ");
                        $stmt_prezenta->execute([$cititor['cod_bare']]);
                        debug_log("Scanare salvata in sesiuni_biblioteca pentru: {$cititor['cod_bare']}");
                    } catch (PDOException $e) {
                        debug_log("Eroare la salvarea scanarii: " . $e->getMessage());
                    }

                    $_SESSION['cititor_activ'] = [
                        'id' => $cititor['id'],
                        'cod_bare' => $cititor['cod_bare'],
                        'nume' => $cititor['nume'],
                        'prenume' => $cititor['prenume'],
                        'numar_carti_imprumutate' => $numar_carti_active,
                        'statut' => $verificare_statut['statut'],
                        'nume_statut' => $verificare_statut['nume_statut'],
                        'limita' => $verificare_statut['limita']
                    ];
                    // Nu mai setăm mesaj - va apărea doar box-ul "Cititor activ"
                    debug_log("Cititor activ setat in sesiune cu {$numar_carti_active} carti imprumutate, statut: {$verificare_statut['statut']}");
                } else {
                    debug_log("Cititor NU a fost gasit pentru cod: $cod_scanat");

                    // ✅ Șterge cititorul activ anterior când se scanează un cititor necunoscut
                    unset($_SESSION['cititor_activ']);
                    unset($_SESSION['carte_scanata']);
                    unset($_SESSION['cititor_necunoscut']); // Șterge și cititorul necunoscut anterior dacă există
                    unset($_SESSION['cititor_necunoscut_statut']);

                    $mesaj = "⚠️ Cititorul nu există în baza de date!";
                    $tip_mesaj = "warning";
                    // Salvează codul în sesiune pentru butonul de adăugare cititor
                    $_SESSION['cititor_necunoscut'] = $cod_scanat;

                    // ✅ Extrage statutul și limita din cod pentru afișare
                    require_once 'functions_statute.php';
                    $statut_din_cod = extrageStatutDinCodBare($cod_scanat, $pdo);
                    $limita_din_statut = getLimitaImprumut($pdo, $statut_din_cod);
                    $info_statut = getInfoStatut($pdo, $statut_din_cod);
                    $nume_statut = $info_statut ? $info_statut['nume_statut'] : 'Nespecificat';

                    // Salvează informațiile despre statut în sesiune pentru afișare
                    $_SESSION['cititor_necunoscut_statut'] = [
                        'statut' => $statut_din_cod,
                        'nume_statut' => $nume_statut,
                        'limita' => $limita_din_statut
                    ];
                }
            } else {
                // Este cod de carte - procesează cartea
                debug_log("Este cod de CARTE - procesare...");
                // Funcție helper pentru a verifica dacă datele sunt corupte (au semne de întrebare)
                $verificaDateCorupte = function ($carte) {
                    if (!$carte)
                        return false;
                    // Verifică dacă titlul sau autorul conțin semne de întrebare consecutive (indică encoding corupt)
                    $titlu = $carte['titlu'] ?? '';
                    $autor = $carte['autor'] ?? '';
                    // Verifică pattern-uri de encoding corupt: ?? sau ? în loc de diacritice
                    return (preg_match('/\?{2,}/', $titlu) || preg_match('/\?{2,}/', $autor) ||
                        (preg_match('/\?/', $titlu) && strlen($titlu) > 5) ||
                        (preg_match('/\?/', $autor) && strlen($autor) > 3));
                };

                // Caută cartea în baza locală - mai întâi după cod_bare, apoi după cota
                $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                $stmt->execute([$cod_scanat]);
                $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($carte) {
                    debug_log("Carte gasita in baza locala: " . substr($carte['titlu'] ?? 'N/A', 0, 50));
                    // Verifică dacă datele sunt corupte
                    if ($verificaDateCorupte($carte)) {
                        debug_log("Carte gasita are date corupte (semne de intrebare) - recautare in Aleph pentru actualizare");
                        $carte = null; // Forțează re-căutare în Aleph
                    }
                } else {
                    debug_log("Carte NU gasita in baza locala, cautare in Aleph...");
                }

                // Dacă nu găsește după cod_bare, caută după cota
                if (!$carte) {
                    $stmt = $pdo->prepare("SELECT * FROM carti WHERE cota = ?");
                    $stmt->execute([$cod_scanat]);
                    $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($carte && $verificaDateCorupte($carte)) {
                        debug_log("Carte gasita dupa cota are date corupte - recautare in Aleph");
                        $carte = null; // Forțează re-căutare în Aleph
                    }
                }

                // Dacă nu există în baza locală SAU are date corupte, caută în Aleph
                if (!$carte) {
                    require_once 'aleph_api.php';

                    // Căutare automată cu fallback (BAR → LOC → WRD)
                    $rezultat_aleph = cautaCarteInAleph($cod_scanat, 'AUTO');

                    if ($rezultat_aleph['success']) {
                        $date_carte = $rezultat_aleph['data'];
                        $barcode_final = !empty($date_carte['barcode']) ? $date_carte['barcode'] : $cod_scanat;
                        $cota_final = !empty($date_carte['cota']) ? $date_carte['cota'] : '';

                        // Verifică dacă cartea există deja (evită duplicate)
                        $carte_existenta = null;
                        if (!empty($barcode_final)) {
                            $stmt_check = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                            $stmt_check->execute([$barcode_final]);
                            $carte_existenta = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        }

                        if (!$carte_existenta && !empty($cota_final)) {
                            $stmt_check = $pdo->prepare("SELECT * FROM carti WHERE cota = ?");
                            $stmt_check->execute([$cota_final]);
                            $carte_existenta = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        }

                        // Pregătește datele pentru salvare/actualizare
                        $titlu = !empty($date_carte['titlu']) ?
                            (mb_check_encoding($date_carte['titlu'], 'UTF-8') ?
                                $date_carte['titlu'] :
                                mb_convert_encoding($date_carte['titlu'], 'UTF-8', 'ISO-8859-2')) : '';

                        $autor = !empty($date_carte['autor']) ?
                            (mb_check_encoding($date_carte['autor'], 'UTF-8') ?
                                $date_carte['autor'] :
                                mb_convert_encoding($date_carte['autor'], 'UTF-8', 'ISO-8859-2')) : '';

                        // Pentru câmpurile simple (isbn, cota, sectiune) folosim direct
                        $isbn = !empty($date_carte['isbn']) ? $date_carte['isbn'] : '';
                        $cota = !empty($date_carte['cota']) ? $date_carte['cota'] : '';
                        $sectiune = !empty($date_carte['sectiune']) ? $date_carte['sectiune'] : '';

                        if ($carte_existenta) {
                            // Cartea există - verifică dacă are date corupte
                            if ($verificaDateCorupte($carte_existenta)) {
                                // Actualizează datele corupte cu cele corecte din Aleph
                                debug_log("Actualizare date corupte pentru carte: " . $barcode_final);
                                $stmt_update = $pdo->prepare("
                                    UPDATE carti 
                                    SET titlu = ?, autor = ?, isbn = ?, cota = ?, sectiune = ?
                                    WHERE cod_bare = ?
                                ");
                                $stmt_update->execute([
                                    $titlu,
                                    $autor,
                                    $isbn,
                                    $cota,
                                    $sectiune,
                                    $barcode_final
                                ]);

                                // Re-încarcă cartea actualizată
                                $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                                $stmt->execute([$barcode_final]);
                                $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                            } else {
                                // Datele sunt OK, folosește cartea existentă
                                $carte = $carte_existenta;
                            }
                        } else {
                            // Import nou - DOAR dacă există cititor activ
                            // Dacă nu există cititor activ, NU salvăm cartea în baza de date
                            if (isset($_SESSION['cititor_activ'])) {
                                // Există cititor activ - salvează cartea în baza de date
                                debug_log("Cititor activ exista - salvare carte in baza de date");

                                // ✅ NOU - Extrage și convertește statutul din Aleph
                                require_once 'functions_statute_carti.php';
                                $status_aleph = $date_carte['status'] ?? '';
                                $statut_carte = convertesteStatusAlephInCod($status_aleph);

                                $stmt_import = $pdo->prepare("
                                    INSERT INTO carti (cod_bare, titlu, autor, isbn, cota, sectiune, statut, data_adaugare)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $stmt_import->execute([
                                    $barcode_final,
                                    $titlu,
                                    $autor,
                                    $isbn,
                                    $cota,
                                    $sectiune,
                                    $statut_carte
                                ]);

                                // Re-încarcă cartea din DB
                                $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
                                $stmt->execute([$barcode_final]);
                                $carte = $stmt->fetch(PDO::FETCH_ASSOC);
                            } else {
                                // NU există cititor activ - NU salvăm cartea în baza de date
                                // Construim un array temporar cu datele cărții pentru afișare
                                debug_log("NU exista cititor activ - NU salvam carte in baza de date, doar afisare");
                                $carte = [
                                    'cod_bare' => $barcode_final,
                                    'titlu' => $titlu,
                                    'autor' => $autor,
                                    'isbn' => $isbn,
                                    'cota' => $cota,
                                    'sectiune' => $sectiune
                                ];
                            }
                        }
                    } else {
                        // Aleph nu a găsit cartea - afișează mesaj clar
                        $mesaj_aleph = $rezultat_aleph['mesaj'] ?? "Nu există această carte în baza de date Aleph";
                        debug_log("Aleph nu a gasit cartea: " . $mesaj_aleph);

                        // Verifică dacă mesajul este despre inexistență în Aleph
                        if (
                            stripos($mesaj_aleph, 'Nu există') !== false ||
                            stripos($mesaj_aleph, 'nu există') !== false ||
                            stripos($mesaj_aleph, 'baza de date Aleph') !== false
                        ) {
                            $mesaj = "❌ <strong>" . htmlspecialchars($mesaj_aleph, ENT_QUOTES, 'UTF-8') . "</strong><br><br>";
                            $mesaj .= "📚 <strong>Cod scanat:</strong> " . htmlspecialchars($cod_scanat, ENT_QUOTES, 'UTF-8') . "<br><br>";
                            $mesaj .= "ℹ️ <em>Această carte nu poate fi adăugată la împrumuturi deoarece nu există în catalogul Aleph.</em>";
                            $tip_mesaj = "danger";

                            // NU salvează codul pentru adăugare - cartea nu există în Aleph
                            // Nu setează $_SESSION['carte_necunoscut'] pentru că nu vrem să permită adăugarea
                        } else {
                            // Alt tip de eroare
                            $mesaj = "⚠️ <strong>Eroare la căutare în Aleph:</strong><br>" . htmlspecialchars($mesaj_aleph, ENT_QUOTES, 'UTF-8');
                            $tip_mesaj = "warning";
                        }
                    }
                }

                if ($carte) {
                    debug_log("Carte procesata: " . substr($carte['titlu'] ?? 'N/A', 0, 50));
                    // Verifică dacă există cititor activ
                    if (isset($_SESSION['cititor_activ'])) {
                        debug_log("Cititor activ exista - procesare imprumut/returnare");
                        $cod_cititor = $_SESSION['cititor_activ']['cod_bare'];
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
                            // Returnează cartea
                            debug_log("Cartea este deja imprumutata - RETURNARE");
                            $update_stmt = $pdo->prepare("
                                UPDATE imprumuturi
                                SET status = 'returnat', data_returnare = NOW()
                                WHERE cod_carte = ? AND cod_cititor = ? AND data_returnare IS NULL
                            ");
                            $update_stmt->execute([$cod_carte_db, $cod_cititor]);

                            // Recalculează numărul de cărți împrumutate din baza de date (după UPDATE)
                            $stmt_count_after_return = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ? AND data_returnare IS NULL");
                            $stmt_count_after_return->execute([$cod_cititor]);
                            $numar_carti_dupa_returnare = (int) $stmt_count_after_return->fetchColumn();

                            // Recalculează verificarea limitelor
                            require_once 'functions_statute.php';
                            $verificare_after_return = poateImprumuta($pdo, $cod_cititor, $numar_carti_dupa_returnare);

                            // Actualizează numărul de cărți împrumutate în sesiune
                            if (isset($_SESSION['cititor_activ'])) {
                                $_SESSION['cititor_activ']['numar_carti_imprumutate'] = $numar_carti_dupa_returnare;
                                $_SESSION['cititor_activ']['limita'] = $verificare_after_return['limita'];
                            }

                            $mesaj = "✅ Cartea a fost returnată cu succes!\n" .
                                "📕 {$carte['titlu']}\n" .
                                "👤 {$_SESSION['cititor_activ']['nume']} {$_SESSION['cititor_activ']['prenume']}";
                            $tip_mesaj = "success";
                            debug_log("Mesaj returnare creat: " . substr($mesaj, 0, 50));

                            // Șterge cartea scanată din sesiune după returnare
                            unset($_SESSION['carte_scanata']);
                        } else {
                            // Verifică dacă cartea este împrumutată de altcineva (doar cele cu data_returnare IS NULL)
                            $stmt = $pdo->prepare("SELECT * FROM imprumuturi WHERE cod_carte = ? AND data_returnare IS NULL");
                            $stmt->execute([$cod_carte_db]);

                            if ($stmt->rowCount() > 0) {
                                $mesaj = "⚠️ Cartea '{$carte['titlu']}' este deja împrumutată de alt cititor!";
                                $tip_mesaj = "warning";
                            } else {
                                // ✅ NOU - Verificare statut carte
                                require_once 'functions_statute_carti.php';

                                // Verifică dacă cartea poate fi împrumutată (acasă)
                                $verificare_carte = poateImprumutaCarte($pdo, $cod_carte_db, 'acasa');

                                if (!$verificare_carte['poate']) {
                                    $mesaj = "⚠️ <strong>NU SE POATE ÎMPRUMUTA!</strong><br>";
                                    $mesaj .= htmlspecialchars($verificare_carte['mesaj'], ENT_QUOTES, 'UTF-8');
                                    $tip_mesaj = "warning";
                                } else {
                                    // Verifică numărul de cărți împrumutate (doar cele cu data_returnare IS NULL)
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ? AND data_returnare IS NULL");
                                    $stmt->execute([$cod_cititor]);
                                    $numar_carti_imprumutate = (int) $stmt->fetchColumn();

                                    // ✅ NOU - Verificare limită dinamică în funcție de statut
                                    require_once 'functions_statute.php';

                                    $verificare = poateImprumuta($pdo, $cod_cititor, $numar_carti_imprumutate);

                                    if (!$verificare['poate'] || $numar_carti_imprumutate >= $verificare['limita']) {
                                        $mesaj = "🚫 <strong>Utilizatorul a atins limita de cărți împrumutate!</strong><br>";
                                        $mesaj .= "Nu mai puteți împrumuta: aveți deja <strong>{$numar_carti_imprumutate} cărți</strong>, limita maximă.<br>";
                                        $mesaj .= "Statut: <strong>{$verificare['nume_statut']}</strong><br>";
                                        $mesaj .= "Trebuie să returnați cel puțin o carte pentru a împrumuta alta.";
                                        $tip_mesaj = "danger";
                                    } else {
                                        // ÎMPRUMUT NOU - calculează data scadenței din statutul cărții
                                        $durata_zile = $verificare_carte['durata_zile'];
                                        if ($durata_zile <= 0) {
                                            $durata_zile = 14; // Fallback
                                        }
                                        $data_scadenta = date('Y-m-d', strtotime("+{$durata_zile} days"));

                                        // Verifică dacă cartea este din depozit (cota începe cu I-, II-, III- sau IV-)
                                        $este_depozit = false;
                                        $cota_carte = $carte['cota'] ?? '';
                                        if (!empty($cota_carte)) {
                                            $cota_trimmed = trim($cota_carte);
                                            // Verifică dacă cota începe cu I-, II-, III- sau IV-
                                            if (preg_match('/^(I-|II-|III-|IV-)/i', $cota_trimmed)) {
                                                $este_depozit = true;
                                            }
                                        }

                                        $stmt = $pdo->prepare("
                                            INSERT INTO imprumuturi (cod_cititor, cod_carte, data_imprumut, data_scadenta, status, status_depozit) 
                                            VALUES (?, ?, NOW(), ?, 'activ', ?)
                                        ");
                                        $status_depozit = $este_depozit ? 'cerere' : 'nu';
                                        $stmt->execute([
                                            $cod_cititor,
                                            $cod_carte_db,
                                            $data_scadenta,
                                            $status_depozit
                                        ]);

                                        // Email-ul se trimite automat la ora 18:00 prin trimite_rapoarte_zilnice.php
                                        // Nu trimitem email instant pentru a avea o situație completă la final de zi

                                        // Recalculează numărul de cărți împrumutate din baza de date (după INSERT)
                                        $stmt_count_after = $pdo->prepare("SELECT COUNT(*) FROM imprumuturi WHERE cod_cititor = ? AND data_returnare IS NULL");
                                        $stmt_count_after->execute([$cod_cititor]);
                                        $numar_carti_dupa_imprumut = (int) $stmt_count_after->fetchColumn();

                                        // Recalculează verificarea limitelor
                                        $verificare_after = poateImprumuta($pdo, $cod_cititor, $numar_carti_dupa_imprumut);

                                        // Actualizează numărul de cărți împrumutate în sesiune
                                        if (isset($_SESSION['cititor_activ'])) {
                                            $_SESSION['cititor_activ']['numar_carti_imprumutate'] = $numar_carti_dupa_imprumut;
                                            $_SESSION['cititor_activ']['limita'] = $verificare_after['limita'];
                                        }

                                        $mesaj = "✅ <strong>Carte împrumutată:</strong> {$carte['titlu']}<br>";
                                        $mesaj .= "📅 <strong>Scadență:</strong> $data_scadenta ({$durata_zile} zile)<br>";
                                        $mesaj .= "🏷️ <strong>Statut carte:</strong> {$verificare_carte['nume_statut']}<br>";
                                        $mesaj .= "📚 <strong>Cărți împrumutate:</strong> {$numar_carti_dupa_imprumut}/{$verificare_after['limita']}";
                                        if ($verificare_after['ramase'] > 0) {
                                            $mesaj .= " (Mai puteți împrumuta {$verificare_after['ramase']} cărți)";
                                        }
                                        $tip_mesaj = "success";

                                        // Salvează informații despre statutul cărții pentru email
                                        $_SESSION['ultimul_imprumut'] = [
                                            'cod_carte' => $cod_carte_db,
                                            'titlu' => $carte['titlu'],
                                            'statut_carte' => $verificare_carte['statut'],
                                            'nume_statut_carte' => $verificare_carte['nume_statut'],
                                            'durata_zile' => $durata_zile,
                                            'data_scadenta' => $data_scadenta
                                        ];
                                        debug_log("Mesaj imprumut creat: " . substr($mesaj, 0, 50));

                                        // Nu mai salvăm cartea scanată când se împrumută cu succes
                                        // Informațiile sunt deja în mesajul de succes, nu mai trebuie secțiune separată
                                        // Salvează doar pentru anulare ulterioară (dacă e nevoie)

                                        // Șterge cartea anterioară când scanezi una nouă
                                        unset($_SESSION['carte_scanata']);
                                        unset($_SESSION['carte_scanata_pentru_anulare']);

                                        // APOI setează noua carte
                                        $_SESSION['carte_scanata_pentru_anulare'] = [
                                            'cod_bare' => $carte['cod_bare'],
                                            'titlu' => $carte['titlu'],
                                            'este_imprumutata_acum' => true // Flag pentru a ști dacă tocmai a fost împrumutată
                                        ];
                                    }
                                }
                            }
                        }
                    } else {
                        // Nu există cititor activ - verifică statusul cărții și afișează informații
                        debug_log("Nu exista cititor activ - afisare info carte");

                        // Verifică dacă cartea este împrumutată
                        $stmt_verif = $pdo->prepare("
                            SELECT COUNT(*) as total 
                            FROM imprumuturi 
                            WHERE cod_carte = ? 
                            AND data_returnare IS NULL
                        ");
                        $stmt_verif->execute([$carte['cod_bare']]);
                        $este_imprumutata = $stmt_verif->fetchColumn() > 0;

                        // Mesaj elegant cu toate informațiile
                        $mesaj = "ℹ️ <strong>Informații carte:</strong><br><br>";
                        $mesaj .= "📚 <strong>Titlu:</strong> " . htmlspecialchars($carte['titlu'], ENT_QUOTES, 'UTF-8') . "<br>";

                        if (!empty($carte['autor'])) {
                            $mesaj .= "👤 <strong>Autor:</strong> " . htmlspecialchars($carte['autor'], ENT_QUOTES, 'UTF-8') . "<br>";
                        }

                        $mesaj .= "📍 <strong>Cod de bare:</strong> " . htmlspecialchars($carte['cod_bare'], ENT_QUOTES, 'UTF-8') . "<br>";

                        if (!empty($carte['cota'])) {
                            $mesaj .= "🔖 <strong>Cotă:</strong> " . htmlspecialchars($carte['cota'], ENT_QUOTES, 'UTF-8') . "<br>";
                        }

                        // Status și instrucțiuni
                        if ($este_imprumutata) {
                            $mesaj .= "<br>🔴 <strong>Status:</strong> <span style='color: #dc3545;'>ÎMPRUMUTATĂ</span><br>";
                            $mesaj .= "<br>💡 <em>Scanați carnetul cititorului pentru a <strong>returna</strong> cartea.</em>";
                            debug_log("Cartea este imprumutata - mesaj de returnare");
                        } else {
                            $mesaj .= "<br>🟢 <strong>Status:</strong> <span style='color: #28a745;'>DISPONIBILĂ</span><br>";
                            $mesaj .= "<br>💡 <em>Scanați carnetul cititorului pentru a <strong>împrumuta</strong> cartea.</em>";
                            debug_log("Cartea NU este imprumutata - mesaj de imprumut");
                        }

                        $tip_mesaj = "info";
                        debug_log("Afisare info carte - Status: " . ($este_imprumutata ? "Imprumutata" : "Disponibila"));

                        // NU salvăm cartea în sesiune când nu există cititor
                        // Cartea scanată se va afișa doar în mesaj, nu ca box separat
                        unset($_SESSION['carte_scanata']);
                        unset($_SESSION['carte_scanata_pentru_anulare']);
                    }
                } else {
                    debug_log("Carte NU gasita nici in baza locala, nici in Aleph");
                    $mesaj = "❌ Cod de bare/cotă necunoscut: $cod_scanat<br>Nu există în baza locală și nici în Aleph!";
                    $tip_mesaj = "danger";
                    // Salvează codul în sesiune pentru butonul de adăugare carte
                    $_SESSION['carte_necunoscut'] = $cod_scanat;
                }
            }
        } catch (PDOException $e) {
            $mesaj = "❌ Eroare: " . $e->getMessage();
            $tip_mesaj = "danger";
        }

        // După procesare, salvează mesajele în sesiune și redirect pentru a evita re-submit
        // IMPORTANT: Redirect după ORICE POST, chiar dacă nu există mesaj, pentru a evita dialogul "Confirm Form Resubmission"
        if (isset($mesaj) && isset($tip_mesaj)) {
            $_SESSION['temp_message'] = $mesaj;
            $_SESSION['temp_message_type'] = $tip_mesaj;
        }

        // Marchează că s-a făcut un POST (pentru validare la următorul GET)
        $_SESSION['last_post_time'] = time();
    }

    // IMPORTANT: Redirect întotdeauna după POST (POST-REDIRECT-GET pattern)
    // Chiar dacă nu s-a procesat nimic, redirect pentru a evita dialogul "Confirm Form Resubmission"
    ob_end_clean(); // Curăță orice output accidental și oprește buffering
    header('Location: index.php');
    exit;

    // Toate acțiunile se fac prin scanare_automata - eliminat formularile vechi
}

// Obține statistici
$total_carti = $pdo->query("SELECT COUNT(*) FROM carti")->fetchColumn();
$total_cititori = $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn();
$carti_imprumutate = $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE data_returnare IS NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Bibliotecă - Scanare Coduri de Bare</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card.clickable {
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .stat-card.clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .stat-card p {
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .cititor-activ {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cititor-info h2 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .cititor-info p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .btn-reseteaza {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border: 2px solid white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-reseteaza:hover {
            background: white;
            color: #11998e;
        }

        .scan-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .scan-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1.1em;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1.1em;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
        }

        button {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-selecteaza {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-imprumuta {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-returneaza {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }

        .btn-add-carte {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-add-carte:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* ===== FEREASTRĂ COMPACTĂ CARTE NECUNOSCUTĂ ===== */
        .carte-necunoscut-box {
            background: linear-gradient(135deg, #ffe6e6 0%, #f8d7da 100%);
            border: 1px solid #dc3545;
            border-radius: 12px;
            padding: 5px 0px;
            margin-top: 50px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
        }

        .carte-necunoscut-box .btn-container {
            text-align: center;
        }

        .btn-add-cititor {
            display: inline-block;
            margin-top: 0;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.95);
            color: #667eea;
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            font-size: 1.05em;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
        }

        .btn-add-cititor:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
            background: #ffffff;
            color: #764ba2;
            border-color: #ffffff;
        }

        /* ===== FEREASTRĂ COMPACTĂ CITITOR NECUNOSCUT ===== */
        .cititor-necunoscut-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 2px solid #5a67d8;
            border-radius: 15px;
            padding: 20px 25px;
            margin-top: 15px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cititor-necunoscut-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .cititor-necunoscut-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }

        .cititor-necunoscut-box:hover::before {
            left: 100%;
        }

        .cititor-info-wrapper {
            display: flex;
            flex-direction: column;
            text-align: left;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .cititor-necunoscut-box .info-row {
            display: block;
            margin: 4px 0;
            font-size: 1em;
            color: #ffffff;
            line-height: 1.5;
            font-weight: 500;
        }

        .cititor-necunoscut-box .info-row strong {
            color: #ffffff;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .cititor-necunoscut-box .btn-container {
            margin-top: 0;
            text-align: right;
            position: relative;
            z-index: 1;
            margin-left: 20px;
        }

        .carte-scanata {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .carte-scanata .carte-info {
            color: white;
        }

        .carte-scanata .carte-info h3 {
            color: white;
            margin-bottom: 5px;
        }

        .carte-scanata .carte-info p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }

        .btn-close-carte,
        .btn-close-cititor {
            position: absolute;
            top: 15px;
            /* Mărit de la 10px */
            right: 15px;
            /* Mărit de la 10px */
            background: #dc3545;
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            width: 35px;
            /* Mărit de la 30px */
            height: 35px;
            /* Mărit de la 30px */
            font-size: 20px;
            /* Mărit de la 18px */
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        }

        .btn-close-carte:hover,
        .btn-close-cititor:hover {
            background: #c82333;
            transform: scale(1.15);
            /* Efect hover mai pronunțat */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .alert {
            padding: 15px 45px 15px 20px;
            /* Spațiu pentru butonul X */
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: 500;
            white-space: pre-line;
            position: relative;
            /* ← IMPORTANT pentru butonul X */
        }

        .btn-close-alert {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.2);
            color: inherit;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10;
        }

        .btn-close-alert:hover {
            background: rgba(0, 0, 0, 0.4);
            transform: scale(1.1);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-warning {
            font-size: 1.1em;
            font-weight: 500;
        }

        .alert-warning .mesaj-cititor-necunoscut {
            font-size: 1.5em !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            display: block;
            margin-bottom: 10px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .info-container {
            position: relative;
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .info-container .alert {
            margin-bottom: 15px;
        }

        .info-container .cititor-activ {
            margin-bottom: 0;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 10px;
            padding: 15px;
            position: relative;
            /* ← ADĂUGAT - esențial pentru butonul X */
        }

        .info-container .cititor-activ .cititor-info {
            color: white;
        }

        .info-container .cititor-activ .cititor-info h2 {
            color: white;
        }

        .info-container .cititor-activ .cititor-info p {
            color: rgba(255, 255, 255, 0.9);
        }

        .nav-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            /* ← Se ajustează automat: desktop = 4 pe linie, mobile = 2 pe linie */
            gap: 15px;
            margin-top: 20px;
        }

        .nav-links a {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            text-align: center;
        }

        .nav-links a:hover {
            background: #764ba2;
        }

        .nav-link-sync {
            background: #28a745 !important;
            font-weight: 600;
        }

        .nav-link-sync:hover {
            background: #218838 !important;
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        .button-group-scan {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-scanare-auto,
        .btn-scanare-manual {
            padding: 15px 30px;
            border: 3px solid transparent;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .btn-scanare-auto {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-scanare-manual {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-scanare-auto.active {
            border-color: #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            transform: scale(1.02);
        }

        .btn-scanare-manual.active {
            border-color: #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            transform: scale(1.02);
        }

        .btn-scanare-auto:hover,
        .btn-scanare-manual:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-scanare-auto.active::after,
        .btn-scanare-manual.active::after {
            content: "✓ ACTIV";
            position: absolute;
            top: -10px;
            right: -10px;
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.7em;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        #cod_scanat.mod-manual {
            border-color: #f5576c;
            border-width: 3px;
            background-color: #fff5f7;
        }

        #cod_scanat.mod-auto {
            border-color: #667eea;
            border-width: 2px;
        }

        /* Modal pentru parolă admin - Gestionare Utilizatori */
        .modal-overlay-admin {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content-admin {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            max-width: 500px;
            z-index: 10001;
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

        .warning-icon-admin {
            font-size: 4em;
            text-align: center;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .modal-header-admin {
            text-align: center;
            margin-bottom: 25px;
        }

        .modal-header-admin h3 {
            color: #667eea;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .modal-header-admin p {
            color: #666;
            font-size: 1em;
        }

        .modal-input-admin {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1.1em;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .modal-input-admin:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-buttons-admin {
            display: flex;
            gap: 10px;
        }

        .modal-btn-admin {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-confirm-admin {
            background: #28a745;
            color: white;
        }

        .btn-confirm-admin:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-cancel-admin {
            background: #6c757d;
            color: white;
        }

        .btn-cancel-admin:hover {
            background: #545b62;
        }

        .alert-admin-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📚 Sistem Bibliotecă</h1>
            <p style="color: #666; font-size: 1.1em;">Scanare coduri de bare pentru împrumuturi</p>

            <div class="stats">
                <a href="carti.php" class="stat-card clickable">
                    <h3><?php echo $total_carti; ?></h3>
                    <p>Total cărți</p>
                </a>
                <a href="cititori.php" class="stat-card clickable">
                    <h3><?php echo $total_cititori; ?></h3>
                    <p>Cititori înregistrați</p>
                </a>
                <a href="imprumuturi.php" class="stat-card clickable">
                    <h3><?php echo $carti_imprumutate; ?></h3>
                    <p>Cărți împrumutate</p>
                </a>
            </div>

            <div class="nav-links">
                <a href="rapoarte.php">📊 Rapoarte</a>
                <a href="imprumuturi.php">📋 Listă Împrumuturi</a>
                <a href="raport_prezenta.php">📈 Raport Prezență</a>
                <a href="status_vizari.php">✅ Status Vizări</a>
                <a href="lista_nevizati.php">⚠️ Doar Nevizați</a>
                <a href="adauga_carte.php">➕ Adaugă carte</a>
                <a href="adauga_cititor.php">👤 Adaugă cititor</a>
                <a href="repara_date_corupte.php" class="nav-link-sync">🔄 Sincronizare cu Aleph</a>
                <a href="cauta_cod.php" class="nav-link-sync">🔍 Caută COD</a>
                <a href="#" class="nav-link-sync"
                    onclick="event.preventDefault(); deschideModalAdmin(); return false;">👤 Gestionare Utilizatori</a>
            </div>

        </div>

        <!-- Container unificat pentru mesaje, carte scanată și cititor activ -->
        <div id="info-container" class="info-container"
            style="display: <?php echo (isset($mesaj) || isset($_SESSION['cititor_activ']) || isset($_SESSION['carte_scanata'])) ? 'block' : 'none'; ?>;">

            <?php if (isset($mesaj)): ?>
                <div class="alert alert-<?php echo $tip_mesaj; ?>" id="alert-message">
                    <button class="btn-close-alert" onclick="inchideAlert()" title="Închide mesajul">✕</button>
                    <?php 
                    // Verifică dacă este mesajul de cititor necunoscut pentru a-l face mai expresiv
                    if ($tip_mesaj === 'warning' && strpos($mesaj, 'Cititorul nu există') !== false) {
                        echo '<span class="mesaj-cititor-necunoscut">' . $mesaj . '</span>';
                    } else {
                        echo $mesaj;
                    }
                    ?>

                    <!-- BUTON pentru carte necunoscută -->
                    <?php if (isset($_SESSION['carte_necunoscut']) && $tip_mesaj === 'danger'): ?>
                        <div class="carte-necunoscut-box">
                            <div class="btn-container">
                                <a href="adauga_carte.php?cod=<?php echo urlencode($_SESSION['carte_necunoscut']); ?>"
                                    class="btn-add-carte">
                                    ➕ Adaugă carte nouă:
                                    <?php echo htmlspecialchars($_SESSION['carte_necunoscut'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                        </div>
                        <?php unset($_SESSION['carte_necunoscut']); // Șterge codul după afișare ?>
                    <?php endif; ?>

                    <!-- BUTON pentru cititor necunoscut -->
                    <?php if (isset($_SESSION['cititor_necunoscut']) && $tip_mesaj === 'warning'): ?>
                        <?php
                        // Afișează informații despre statut dacă există
                        $info_statut_necunoscut = $_SESSION['cititor_necunoscut_statut'] ?? null;
                        ?>
                        <div class="cititor-necunoscut-box">
                            <?php if ($info_statut_necunoscut): ?>
                                <div class="cititor-info-wrapper">
                                    <div class="info-row">
                                        🏷️ <strong>Statut detectat:</strong>
                                        <?php 
                                        $statut_nr = isset($info_statut_necunoscut['statut']) ? $info_statut_necunoscut['statut'] : '';
                                        $statut_nume = htmlspecialchars($info_statut_necunoscut['nume_statut'], ENT_QUOTES, 'UTF-8');
                                        if (!empty($statut_nr)) {
                                            echo "[" . htmlspecialchars($statut_nr, ENT_QUOTES, 'UTF-8') . "] " . $statut_nume;
                                        } else {
                                            echo $statut_nume;
                                        }
                                        ?>
                                    </div>
                                    <div class="info-row">
                                        📚 <strong>Limită cărți:</strong> <?php echo (int) $info_statut_necunoscut['limita']; ?>
                                        cărți
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="btn-container">
                                <a href="adauga_cititor.php?cod=<?php echo urlencode($_SESSION['cititor_necunoscut']); ?>"
                                    class="btn-add-cititor">
                                    👤 Adaugă cititor nou:
                                    <?php echo htmlspecialchars($_SESSION['cititor_necunoscut'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                        </div>
                        <?php
                        unset($_SESSION['cititor_necunoscut']); // Șterge codul după afișare
                        unset($_SESSION['cititor_necunoscut_statut']); // Șterge și informațiile despre statut
                        ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Carte scanată (DOAR când există cititor activ ȘI cartea a fost procesată) -->
            <?php if (isset($_SESSION['carte_scanata']) && isset($_SESSION['cititor_activ']) && (!isset($mesaj) || $tip_mesaj !== 'success')): ?>
                <div class="carte-scanata" id="carte-scanata-box">
                    <button class="btn-close-carte" onclick="anuleazaCarte()" title="Anulează operațiunea">✕</button>
                    <div class="carte-info">
                        <h3>📚 Cartea scanată:
                            <?php echo htmlspecialchars($_SESSION['carte_scanata']['titlu'], ENT_QUOTES, 'UTF-8'); ?>
                        </h3>
                        <?php if (!empty($_SESSION['carte_scanata']['autor'])): ?>
                            <p>👤 Autor:
                                <?php echo htmlspecialchars($_SESSION['carte_scanata']['autor'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <p>📍 Cod:
                            <?php echo htmlspecialchars($_SESSION['carte_scanata']['cod_bare'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cititor activ (cu buton X separat) -->
            <?php if (isset($_SESSION['cititor_activ'])):
                // Obține ID-ul cititorului pentru link
                $cititor_id = $_SESSION['cititor_activ']['id'] ?? null;
                if (!$cititor_id) {
                    // Dacă nu există ID în sesiune, îl obține din baza de date
                    $stmt_id = $pdo->prepare("SELECT id FROM cititori WHERE cod_bare = ?");
                    $stmt_id->execute([$_SESSION['cititor_activ']['cod_bare']]);
                    $cititor_id = $stmt_id->fetchColumn();
                }

                // Obține lista de cărți împrumutate pentru acest cititor
                $stmt_carti = $pdo->prepare("
                    SELECT 
                        i.id as imprumut_id,
                        i.data_imprumut,
                        i.data_scadenta,
                        c.titlu,
                        c.autor,
                        c.cod_bare as cod_carte,
                        DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
                    FROM imprumuturi i
                    JOIN carti c ON i.cod_carte = c.cod_bare
                    WHERE i.cod_cititor = ? 
                    AND i.data_returnare IS NULL
                    ORDER BY i.data_imprumut DESC
                ");
                $stmt_carti->execute([$_SESSION['cititor_activ']['cod_bare']]);
                $carti_imprumutate = $stmt_carti->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="cititor-activ" id="cititor-activ-box">
                    <button class="btn-close-cititor" onclick="reseteazaCititor()"
                        title="Închide fereastra (împrumuturile rămân salvate)">✕</button>
                    <div class="cititor-info">
                        <h2>👤 Cititor activ:
                            <?php if ($cititor_id): ?>
                                <a href="editare_cititor.php?id=<?php echo $cititor_id; ?>"
                                    style="color: white; text-decoration: underline; font-weight: bold;">
                                    <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($_SESSION['cititor_activ']['nume'] . ' ' . $_SESSION['cititor_activ']['prenume'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </h2>
                        <p>Cod: <?php echo htmlspecialchars($_SESSION['cititor_activ']['cod_bare'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php if (isset($_SESSION['cititor_activ']['nume_statut'])): ?>
                            <p style="font-size: 1em; margin-top: 5px; opacity: 0.95;">
                                🏷️ <strong>Statut:</strong>
                                <?php 
                                $statut_nr = isset($_SESSION['cititor_activ']['statut']) ? $_SESSION['cititor_activ']['statut'] : '';
                                $statut_nume = htmlspecialchars($_SESSION['cititor_activ']['nume_statut'], ENT_QUOTES, 'UTF-8');
                                if (!empty($statut_nr)) {
                                    echo "[" . htmlspecialchars($statut_nr, ENT_QUOTES, 'UTF-8') . "] " . $statut_nume;
                                } else {
                                    echo $statut_nume;
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['cititor_activ']['numar_carti_imprumutate'])):
                            $numar_carti = (int) $_SESSION['cititor_activ']['numar_carti_imprumutate'];
                            $limita = isset($_SESSION['cititor_activ']['limita']) ? (int) $_SESSION['cititor_activ']['limita'] : 6;
                            $e_la_maxim = $numar_carti >= $limita;
                            $culoare = $e_la_maxim ? 'color: #dc3545; font-weight: bold;' : '';
                            ?>
                            <p style="font-size: 1.1em; margin-top: 8px; <?php echo $culoare; ?>">
                                📚 <strong><?php echo $numar_carti; ?>
                                    <?php echo $numar_carti == 1 ? 'Carte împrumutată' : 'Cărți împrumutate'; ?></strong>
                                <?php if (isset($_SESSION['cititor_activ']['limita'])): ?>
                                    / <?php echo $limita; ?>
                                <?php endif; ?>
                            </p>

                            <!-- Lista cărți împrumutate -->
                            <?php if (!empty($carti_imprumutate)): ?>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
                                    <?php foreach ($carti_imprumutate as $carte_imp):
                                        $este_intarziata = (int) $carte_imp['zile_imprumut'] > 14;
                                        ?>
                                        <div
                                            style="margin: 8px 0; padding: 8px; background: rgba(255,255,255,0.15); border-radius: 5px; font-size: 0.9em;">
                                            <p style="margin: 3px 0;">
                                                📖 <?php echo htmlspecialchars($carte_imp['titlu'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($este_intarziata): ?>
                                                    <span style="color: #ff6b6b; font-weight: bold; margin-left: 10px;">⚠️
                                                        NERETURNATĂ</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="scan-section">
            <h2>🔍 Scanare Coduri</h2>

            <!-- Scanare automată/manuală - detectează automat tipul de cod -->
            <form method="POST" id="scanFormAuto">
                <input type="hidden" name="actiune" value="scanare_automata">
                <input type="hidden" id="mod_scanare" name="mod_scanare" value="auto">

                <div class="form-group">
                    <label for="cod_scanat">🔍 Scanează cod (cititor sau carte):</label>
                    <input type="text" id="cod_scanat" name="cod_scanat"
                        placeholder="Scanează codul cititorului (USER001) sau al cărții (BOOK001)" autofocus required>
                    <small id="info-mod" style="display: block; margin-top: 5px; color: #666;">
                        💡 <strong>MOD AUTOMAT:</strong> Scannerul trimite automat datele
                    </small>
                </div>

                <div class="button-group-scan">
                    <button type="submit" class="btn-scanare-auto active" id="btnAuto" onclick="setModAutomat(event)">
                        🔍 Scanare Automată
                    </button>
                    <button type="submit" class="btn-scanare-manual" id="btnManual" onclick="setModManual(event)">
                        ✍️ Scanare Manuală
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        // Variabile globale pentru control mod scanare
        let modScanare = 'auto'; // 'auto' sau 'manual'
        let timeoutScanare = null;
        let ultimulTimestamp = 0;
        let isScanning = false;

        // Constante
        const SCANNER_THRESHOLD = 100; // ms - detectare scanner
        const MANUAL_TIMEOUT = 2500; // ms - timeout pentru input manual în mod auto
        const SCANNER_TIMEOUT = 150; // ms - timeout pentru scanner

        // ===== FUNCȚII PENTRU COMUTARE MOD =====
        function setModAutomat(e) {
            if (e) e.preventDefault();
            modScanare = 'auto';

            // Update UI
            document.getElementById('btnAuto').classList.add('active');
            document.getElementById('btnManual').classList.remove('active');
            document.getElementById('cod_scanat').classList.remove('mod-manual');
            document.getElementById('cod_scanat').classList.add('mod-auto');
            document.getElementById('info-mod').innerHTML = '💡 <strong>MOD AUTOMAT:</strong> Scannerul trimite automat datele';
            document.getElementById('mod_scanare').value = 'auto';

            // Focus input
            document.getElementById('cod_scanat').focus();

            console.log('🔄 Mod AUTOMAT activat');
        }

        function setModManual(e) {
            if (e) e.preventDefault();
            modScanare = 'manual';

            // Anulează orice timeout activ
            if (timeoutScanare) {
                clearTimeout(timeoutScanare);
                timeoutScanare = null;
            }

            // Update UI
            document.getElementById('btnManual').classList.add('active');
            document.getElementById('btnAuto').classList.remove('active');
            document.getElementById('cod_scanat').classList.add('mod-manual');
            document.getElementById('cod_scanat').classList.remove('mod-auto');
            document.getElementById('info-mod').innerHTML = '✍️ <strong>MOD MANUAL:</strong> Introduceți codul și apăsați Enter sau click pe buton';
            document.getElementById('mod_scanare').value = 'manual';

            // Focus input
            document.getElementById('cod_scanat').focus();

            console.log('✍️ Mod MANUAL activat');
        }

        // ===== FUNCȚII PENTRU ÎNCHIDERE/RESETARE =====
        function inchideAlert() {
            // Redirect pentru a curăța sesiunea (variabilele de alertă)
            window.location.href = '?actiune=inchide_alert';
        }

        function reseteazaCititor() {
            // Doar închide fereastra - împrumuturile rămân salvate
            window.location.href = '?actiune=reseteaza_cititor';
        }

        function anuleazaCarte() {
            if (confirm('Sigur doriți să anulați cartea scanată și să o ștergeți din împrumut?')) {
                window.location.href = '?actiune=anuleaza_carte';
            }
        }

        function verificaContainerGol() {
            const container = document.getElementById('info-container');
            const alertMsg = document.getElementById('alert-message');
            const cititorBox = document.getElementById('cititor-activ-box');
            const carteBox = document.getElementById('carte-scanata-box');

            if (container && !alertMsg && !cititorBox && !carteBox) {
                container.style.display = 'none';
            }
        }

        // Ascunde automat containerul dacă nu există conținut
        window.addEventListener('load', function () {
            verificaContainerGol();
        });

        // ===== LOGICA PRINCIPALĂ DE SCANARE =====
        document.addEventListener('DOMContentLoaded', function () {
            const inputScan = document.getElementById('cod_scanat');
            const formScan = document.getElementById('scanFormAuto');

            if (!inputScan || !formScan) {
                console.error('Input sau formular negăsit!');
                return;
            }

            // Inițializează input-ul cu clasa pentru mod automat
            inputScan.classList.add('mod-auto');

            inputScan.focus();

            // Click pe pagină pentru focus automat
            document.addEventListener('click', function (e) {
                // Nu refocusează dacă s-a dat click pe butoane
                if (!e.target.closest('button')) {
                    inputScan.focus();
                }
            });

            // Funcție pentru trimitere formular
            function trimiteFormular() {
                const valoare = inputScan.value.trim();
                console.log('🚀 Trimitere formular cu:', valoare, `(MOD: ${modScanare})`);

                if (valoare.length >= 3) {
                    // Feedback vizual
                    inputScan.style.borderColor = '#28a745';
                    inputScan.style.backgroundColor = '#d4edda';

                    // Anulează timeout-ul dacă există
                    if (timeoutScanare) {
                        clearTimeout(timeoutScanare);
                        timeoutScanare = null;
                    }

                    // Reset flag
                    isScanning = false;

                    // Trimite formularul
                    formScan.submit();
                }
            }

            // EVENT LISTENER PENTRU INPUT
            inputScan.addEventListener('input', function (e) {
                const acum = Date.now();
                const delta = acum - ultimulTimestamp;
                ultimulTimestamp = acum;

                // Anulează timeout-ul anterior
                if (timeoutScanare) {
                    clearTimeout(timeoutScanare);
                    timeoutScanare = null;
                }

                const valoare = this.value.trim();
                const lungimeInainte = this.dataset.lastLength || 0;
                const lungimeAcum = valoare.length;

                // ===== MOD MANUAL - NU FACE NIMIC AUTOMAT =====
                if (modScanare === 'manual') {
                    // Doar feedback vizual
                    if (valoare.length >= 3) {
                        this.style.borderColor = '#f5576c';
                        this.style.backgroundColor = '#fff5f7';
                    } else {
                        this.style.borderColor = '#ddd';
                        this.style.backgroundColor = 'white';
                    }
                    this.dataset.lastLength = lungimeAcum;
                    return; // STOP aici - nu procesa automat
                }

                // ===== MOD AUTOMAT - DETECTARE SCANNER VS MANUAL =====
                // Detectare automată: scanner vs manual
                if (delta < SCANNER_THRESHOLD && lungimeAcum > lungimeInainte) {
                    isScanning = true;
                    console.log('📥 SCANNER detectat - input rapid:', delta + 'ms');
                } else if (delta >= SCANNER_THRESHOLD) {
                    isScanning = false;
                    console.log('📥 Input MANUAL detectat - întârziere:', delta + 'ms');
                }

                // Salvează lungimea actuală
                this.dataset.lastLength = lungimeAcum;

                // Feedback vizual
                this.style.borderColor = '#28a745';
                this.style.backgroundColor = '#d4edda';

                if (valoare.length >= 3) {
                    // Alege timeout-ul în funcție de tipul de input
                    const isScanningNow = isScanning;
                    const timeoutDuration = isScanningNow ? SCANNER_TIMEOUT : MANUAL_TIMEOUT;

                    console.log(`⏰ Setare timeout ${isScanningNow ? 'SCANNER' : 'MANUAL'}: ${timeoutDuration}ms`);

                    timeoutScanare = setTimeout(function () {
                        const valoareFinala = inputScan.value.trim();
                        if (valoareFinala.length >= 3) {
                            console.log(`⏰ Timeout expirat (${isScanningNow ? 'SCANNER' : 'MANUAL'}) - trimitere automată`);
                            trimiteFormular();
                        }
                    }, timeoutDuration);
                }
            });

            // EVENT LISTENER PENTRU ENTER
            inputScan.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();

                    const valoare = this.value.trim();
                    console.log('⏎ Enter detectat cu valoarea:', valoare, `(MOD: ${modScanare})`);

                    // Anulează timeout-ul automat
                    if (timeoutScanare) {
                        clearTimeout(timeoutScanare);
                        timeoutScanare = null;
                    }

                    if (valoare.length >= 3) {
                        trimiteFormular();
                    }

                    return false;
                }

                // Dacă apasă Delete sau Backspace, anulează timeout-ul
                if (e.key === 'Delete' || e.key === 'Backspace') {
                    if (timeoutScanare) {
                        clearTimeout(timeoutScanare);
                        timeoutScanare = null;
                    }
                    isScanning = false;
                }
            });

            // PREVINE SUBMIT MANUAL dacă valoarea e prea scurtă
            formScan.addEventListener('submit', function (e) {
                const valoare = inputScan.value.trim();
                if (valoare.length < 3) {
                    e.preventDefault();
                    console.log('❌ Submit blocat - valoare prea scurtă');
                    return false;
                }
                console.log('✅ Submit permis cu valoarea:', valoare, `(MOD: ${modScanare})`);
            });
        });

        // AUTO-RESET FORMULAR după succes
        <?php if (isset($mesaj) && $tip_mesaj === 'success'): ?>
            setTimeout(() => {
                const form = document.getElementById('scanFormAuto');
                const input = document.getElementById('cod_scanat');
                if (form && input) {
                    form.reset();
                    input.focus();
                    input.style.borderColor = '';
                    input.style.backgroundColor = '';
                }
            }, 2000);
        <?php endif; ?>

        // ===== AUTO-EXPIRARE FERESTRE DUPĂ 1 MINUT DE INACTIVITATE =====
        // Funcționează și când utilizatorul este în altă aplicație/tab
        const TIMEOUT_EXPIRARE_MS = 60000; // 1 minut = 60000ms
        const STORAGE_KEY = 'ultima_scanare_timestamp';
        let intervalVerificare = null;

        // Funcție pentru resetarea timer-ului de expirare
        function reseteazaTimerExpirare() {
            const acum = Date.now();
            sessionStorage.setItem(STORAGE_KEY, acum.toString());
            console.log('🔄 Timer resetat la:', new Date(acum).toLocaleTimeString());
        }

        // Funcție pentru verificarea expirării
        function verificaExpirare() {
            const ultimaScanare = parseInt(sessionStorage.getItem(STORAGE_KEY) || '0');
            const acum = Date.now();
            const diferenta = acum - ultimaScanare;

            if (ultimaScanare > 0 && diferenta >= TIMEOUT_EXPIRARE_MS) {
                console.log('⏱️ Expirare: 1 minut de inactivitate - ștergere ferestre');
                sessionStorage.removeItem(STORAGE_KEY);
                if (intervalVerificare) clearInterval(intervalVerificare);
                window.location.href = '?actiune=reseteaza_cititor';
            }
        }

        // Resetează timer-ul la orice scanare (Enter sau submit)
        document.addEventListener('DOMContentLoaded', function () {
            const inputScan = document.getElementById('cod_scanat');
            const formScan = document.getElementById('scanFormAuto');

            if (inputScan) {
                // Resetează timer-ul la Enter
                inputScan.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        reseteazaTimerExpirare();
                    }
                });

                // Resetează timer-ul la input (pentru scanări automate)
                inputScan.addEventListener('input', function () {
                    // Doar dacă se face o scanare completă (valoare >= 3 caractere)
                    if (this.value.trim().length >= 3) {
                        reseteazaTimerExpirare();
                    }
                });
            }

            if (formScan) {
                // Resetează timer-ul la submit
                formScan.addEventListener('submit', function () {
                    reseteazaTimerExpirare();
                });
            }

            // Inițializează timer-ul la încărcarea paginii (dacă există ferestre active)
            <?php if (isset($_SESSION['cititor_activ']) || isset($_SESSION['carte_scanata']) || isset($_SESSION['cititor_necunoscut']) || isset($_SESSION['carte_necunoscut']) || isset($mesaj)): ?>
                // Setează timestamp-ul inițial dacă nu există
                if (!sessionStorage.getItem(STORAGE_KEY)) {
                    reseteazaTimerExpirare();
                }

                // Verifică imediat la încărcare (în caz că a expirat între timp)
                verificaExpirare();

                // Verifică la fiecare 5 secunde (funcționează și în background)
                intervalVerificare = setInterval(verificaExpirare, 5000);

                // Verifică și când tab-ul devine vizibil din nou
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') {
                        console.log('👁️ Tab vizibil - verificare expirare');
                        verificaExpirare();
                    }
                });

                // Verifică și când fereastra primește focus
                window.addEventListener('focus', function () {
                    console.log('🎯 Fereastră focalizată - verificare expirare');
                    verificaExpirare();
                });
            <?php else: ?>
                // Curăță timestamp-ul dacă nu există ferestre active
                sessionStorage.removeItem(STORAGE_KEY);
            <?php endif; ?>
        });

        // Funcții pentru modal admin - Gestionare Utilizatori
        function deschideModalAdmin() {
            document.getElementById('modalOverlayAdmin').style.display = 'block';
            setTimeout(() => {
                document.getElementById('parolaAdminInput').focus();
            }, 100);
        }

        function inchideModalAdmin() {
            document.getElementById('modalOverlayAdmin').style.display = 'none';
            document.getElementById('parolaAdminInput').value = '';
            document.getElementById('adminErrorMsg').style.display = 'none';
        }

        function confirmaParolaAdmin() {
            const parola = document.getElementById('parolaAdminInput').value;
            const errorMsg = document.getElementById('adminErrorMsg');

            if (!parola) {
                errorMsg.textContent = '❌ Te rog să introduci parola!';
                errorMsg.style.display = 'block';
                return;
            }

            // Disable butonul pentru a preveni dublă trimitere
            const btnConfirm = document.querySelector('.btn-confirm-admin');
            btnConfirm.disabled = true;
            btnConfirm.textContent = '⏳ Verificare...';

            // Trimite request AJAX pentru verificare parolă din baza de date
            const formData = new FormData();
            formData.append('parola', parola);

            fetch('verifica_parola_admin.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Parola corectă - redirecționează
                        window.location.href = 'modifica_utilizator.php';
                    } else {
                        // Parolă greșită
                        errorMsg.textContent = '❌ ' + data.mesaj;
                        errorMsg.style.display = 'block';
                        document.getElementById('parolaAdminInput').value = '';
                        document.getElementById('parolaAdminInput').focus();

                        // Re-enable butonul
                        btnConfirm.disabled = false;
                        btnConfirm.textContent = '✓ Confirmă';
                    }
                })
                .catch(error => {
                    console.error('Eroare:', error);
                    errorMsg.textContent = '❌ Eroare la verificarea parolei. Te rugăm să încerci din nou.';
                    errorMsg.style.display = 'block';

                    // Re-enable butonul
                    btnConfirm.disabled = false;
                    btnConfirm.textContent = '✓ Confirmă';
                });
        }

        function anuleazaModalAdmin() {
            inchideModalAdmin();
        }

        // Închide modal cu ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('modalOverlayAdmin').style.display === 'block') {
                anuleazaModalAdmin();
            }
        });

        // Enter pentru confirmare
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && document.getElementById('modalOverlayAdmin').style.display === 'block') {
                const input = document.getElementById('parolaAdminInput');
                if (document.activeElement === input) {
                    e.preventDefault();
                    confirmaParolaAdmin();
                }
            }
        });
    </script>

    <!-- Modal pentru parolă admin - Gestionare Utilizatori -->
    <div id="modalOverlayAdmin" class="modal-overlay-admin" onclick="inchideModalAdmin()">
        <div class="modal-content-admin" onclick="event.stopPropagation()">
            <div class="warning-icon-admin">🔐</div>
            <div class="modal-header-admin">
                <h3>Autentificare Administrator</h3>
                <p>Introduceți parola pentru a accesa<br>secțiunea de gestionare utilizatori</p>
            </div>

            <div id="adminErrorMsg" class="alert-admin-error"></div>

            <input type="password" id="parolaAdminInput" class="modal-input-admin" placeholder="Parolă administrator"
                autocomplete="off">

            <div class="modal-buttons-admin">
                <button type="button" class="modal-btn-admin btn-confirm-admin" onclick="confirmaParolaAdmin()">
                    ✓ Confirmă
                </button>
                <button type="button" class="modal-btn-admin btn-cancel-admin" onclick="anuleazaModalAdmin()">
                    ✗ Anulează
                </button>
            </div>
        </div>
    </div>
</body>

</html>
<?php
// End output buffering și trimite output-ul
ob_end_flush();
?>