<?php
/**
 * Script pentru repararea automată a datelor corupte din baza de date
 * Scanează toate cărțile și le repară cu datele corecte din Aleph
 */

// START SESIUNE ÎNAINTE DE ORICE OUTPUT
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Activează output buffering pentru progres în timp real
if (ob_get_level() == 0) {
    ob_start();
}

require_once 'config.php';
require_once 'aleph_api.php';

// FUNCȚIE PENTRU LOGGING
function logMessage($message, $type = 'info') {
    $colors = [
        'info' => '#17a2b8',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'error' => '#dc3545',
        'debug' => '#6c757d'
    ];
    
    $color = $colors[$type] ?? '#6c757d';
    $timestamp = date('H:i:s');
    
    echo "<div style='background: rgba(0,0,0,0.05); padding: 8px; margin: 5px 0; border-left: 4px solid {$color}; font-family: monospace; font-size: 0.9em;'>";
    echo "<strong style='color: {$color};'>[{$timestamp}]</strong> {$message}";
    echo "</div>";
    ob_flush();
    flush();
}

// FUNCȚIE PENTRU ȘTERGERE CACHE-URI/DEBUG - AGRESIVĂ
function stergeToateCache() {
    $fisiere_cache = [
        __DIR__ . '/debug_aleph_converted.html',
        __DIR__ . '/debug_aleph_raw.html',
        __DIR__ . '/debug_encoding.txt',
        __DIR__ . '/debug_session_raw.html',
        __DIR__ . '/debug_scanare.log',
        __DIR__ . '/debug_aleph_response.html'
    ];
    
    $sterse = 0;
    foreach ($fisiere_cache as $fisier) {
        if (file_exists($fisier)) {
            if (@unlink($fisier)) {
                $sterse++;
            }
        }
    }
    
    // Caută și șterge orice alt fișier debug_*.* din directorul curent
    $toate_fisierele = glob(__DIR__ . '/debug_*.*');
    if ($toate_fisierele) {
        foreach ($toate_fisierele as $fisier) {
            if (is_file($fisier)) {
                @unlink($fisier);
                $sterse++;
            }
        }
    }
    
    return $sterse;
}

// FUNCȚIE NOUĂ: Forțează o sesiune nouă în Aleph
function forteazaSesiuneNouaAleph() {
    global $ALEPH_SERVER, $ALEPH_PORT;
    
    // Adaugă timestamp random pentru a forța o sesiune nouă
    $timestamp = time() . rand(1000, 9999);
    $init_url = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}/F?func=file&file_name=find-b&nocache={$timestamp}";
    
    try {
        // Apelează URL-ul pentru a inițializa o sesiune nouă
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $init_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        
        // Așteaptă puțin pentru ca sesiunea să fie complet inițializată
        usleep(500000); // 0.5 secunde
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Reparare Date Corupte</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        h1 { 
            color: #667eea; 
            margin: 0;
        }
        .btn-home {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            font-weight: 600;
            display: inline-block;
        }
        .btn-home:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        .success { color: #28a745; font-weight: bold; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; font-weight: bold; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; border-radius: 5px; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f5f5f5; }
        .btn-sincronizeaza {
            padding: 15px 30px;
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn-sincronizeaza:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }
        .btn-verifica {
            padding: 15px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn-verifica:hover {
            background: #545b62;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        .diferente-tabel td {
            vertical-align: top;
        }
        .vechi {
            color: #dc3545;
            text-decoration: line-through;
            font-size: 0.9em;
        }
        .nou {
            color: #28a745;
            font-weight: bold;
        }
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
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
        .loader-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loader-overlay.active {
            display: flex;
        }
        .loader-spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #667eea;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-text {
            color: white;
            font-size: 1.5em;
            font-weight: 600;
            text-align: center;
        }
        .loader-progress {
            color: white;
            font-size: 1.1em;
            margin-top: 10px;
            opacity: 0.9;
            min-height: 25px;
        }
    </style>
</head>
<body>
    <div id='loaderOverlay' class='loader-overlay'>
        <div class='loader-spinner'></div>
        <div class='loader-text' id='loaderText'>Procesare...</div>
        <div class='loader-progress' id='loaderProgress'></div>
    </div>
    
    <div class='container'>
        <div class='header'>
            <h1>🔧 Reparare Date Corupte din Baza de Date</h1>
            <a href='index.php' class='btn-home'>🏠 Acasă</a>
        </div>";

// Verifică dacă s-a făcut click pe butonul de sincronizare
if (isset($_POST['sincronizeaza'])) {
    // Șterge cache-urile înainte de sincronizare
    stergeToateCache();
    
    // Afișează loader-ul IMEDIAT
    echo "<script>
        document.getElementById('loaderOverlay').classList.add('active');
        document.getElementById('loaderText').textContent = 'Sincronizare cu Aleph în curs...';
        document.getElementById('loaderProgress').textContent = 'Inițializare...';
    </script>";
    ob_flush();
    flush();
    
    echo "<div class='info'><strong>🔄 Procesare sincronizare cu Aleph...</strong></div>";
    
    // Preia lista de cărți cu diferențe din sesiune
    $carti_de_sincronizat = $_SESSION['diferente'] ?? [];
    
    if (empty($carti_de_sincronizat)) {
        echo "<div class='warning'><strong>⚠️ Nu există cărți de sincronizat!</strong><br>";
        echo "Te rog să rulezi mai întâi verificarea diferențelor.</div>";
        
        // Ascunde loader-ul
        echo "<script>
            document.getElementById('loaderOverlay').classList.remove('active');
        </script>";
        ob_flush();
        flush();
    } else {
        $total = count($carti_de_sincronizat);
        $actualizate = 0;
        $eroare = 0;
        
        echo "<table>";
        echo "<tr><th>#</th><th>Cod Bare</th><th>Titlu (ÎNAINTE)</th><th>Titlu (DUPĂ)</th><th>Autor (ÎNAINTE)</th><th>Autor (DUPĂ)</th><th>Status</th></tr>";
        ob_flush();
        flush();
        
        foreach ($carti_de_sincronizat as $index => $diferenta) {
            $numar = $index + 1;
            $carte = $diferenta['carte'];
            $procent = round((($index + 1) / $total) * 100);
            
            // Actualizează progresul în loader
            echo "<script>
                document.getElementById('loaderText').textContent = 'Sincronizare cu Aleph în curs...';
                document.getElementById('loaderProgress').textContent = 'Procesare carte $numar din $total ($procent%)...';
            </script>";
            ob_flush();
            flush();
            
            $titlu_vechi = htmlspecialchars($diferenta['titlu_local'], ENT_QUOTES, 'UTF-8');
            $autor_vechi = htmlspecialchars($diferenta['autor_local'], ENT_QUOTES, 'UTF-8');
            $titlu_nou = htmlspecialchars($diferenta['titlu_aleph'], ENT_QUOTES, 'UTF-8');
            $autor_nou = htmlspecialchars($diferenta['autor_aleph'], ENT_QUOTES, 'UTF-8');
            $cod_bare = htmlspecialchars($carte['cod_bare'], ENT_QUOTES, 'UTF-8');
            
            try {
                // Actualizează în baza de date cu datele din Aleph
                $stmt_update = $pdo->prepare("
                    UPDATE carti 
                    SET titlu = ?, autor = ?
                    WHERE cod_bare = ?
                ");
                $stmt_update->execute([
                    $diferenta['titlu_aleph'],
                    $diferenta['autor_aleph'],
                    $carte['cod_bare']
                ]);
                
                if ($stmt_update->rowCount() > 0) {
                    echo "<tr><td>$numar</td><td>$cod_bare</td><td>$titlu_vechi</td><td>$titlu_nou</td><td>$autor_vechi</td><td>$autor_nou</td><td class='success'>✅ Sincronizată</td></tr>";
                    $actualizate++;
                } else {
                    echo "<tr><td>$numar</td><td>$cod_bare</td><td>$titlu_vechi</td><td>$titlu_nou</td><td>$autor_vechi</td><td>$autor_nou</td><td class='warning'>⚠️ Nu a fost modificată</td></tr>";
                }
                
                ob_flush();
                flush();
                usleep(100000);
            } catch (Exception $e) {
                echo "<tr><td>$numar</td><td>$cod_bare</td><td>$titlu_vechi</td><td>-</td><td>$autor_vechi</td><td>-</td><td class='error'>❌ Eroare: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</td></tr>";
                $eroare++;
                ob_flush();
                flush();
            }
        }
        
        echo "</table>";
        
        // Rezumat
        echo "<h2>📊 Rezumat</h2>";
        echo "<div class='info'>";
        echo "<p><strong>Total cărți procesate:</strong> $total</p>";
        echo "<p class='success'><strong>✅ Actualizate cu succes:</strong> $actualizate</p>";
        
        if ($eroare > 0) {
            echo "<p class='error'><strong>❌ Erori:</strong> $eroare</p>";
        }
        echo "</div>";
        
        if ($actualizate > 0) {
            echo "<div class='success'><strong>🎉 Sincronizare completă! $actualizate " . 
                 ($actualizate == 1 ? 'carte a fost actualizată' : 'cărți au fost actualizate') . 
                 " cu datele din Aleph.</strong></div>";
        }
        
        // Șterge lista din sesiune după sincronizare
        unset($_SESSION['diferente']);
        
        // Șterge cache-urile la final
        stergeToateCache();
        
        // Ascunde loader-ul DOAR LA FINAL
        echo "<script>
            document.getElementById('loaderOverlay').classList.remove('active');
        </script>";
        ob_flush();
        flush();
    }
    
} else {
    // Verifică dacă trebuie să facă check automat în Aleph
    $verifica_automat = isset($_GET['verifica']) && $_GET['verifica'] == '1';
    
    // Afișează informații despre script
    echo "<div class='info'><strong>ℹ️ Acest script poate:</strong><br>";
    echo "<strong>🔍 Mod Verificare:</strong><br>";
    echo "1. Scanează TOATE cărțile din baza de date<br>";
    echo "2. Caută fiecare carte în Aleph după codul de bare<br>";
    echo "3. Compară titlul și autorul local cu cele din Aleph<br>";
    echo "4. Afișează diferențele găsite<br><br>";
    echo "<strong>🔄 Mod Sincronizare:</strong><br>";
    echo "1. Actualizează DOAR cărțile cu diferențe găsite la verificare<br>";
    echo "2. Restaurează titlul și autorul cu datele originale din Aleph<br>";
    echo "<strong>⚠️ Atenție:</strong> Mai întâi rulează verificarea, apoi sincronizarea.</div>";
    
    // Găsește toate cărțile
    $stmt = $pdo->query("SELECT * FROM carti ORDER BY id");
    $toate_carti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($toate_carti);
    $diferente = [];
    
    // Dacă trebuie să verifice automat, verifică toate cărțile în Aleph
    if ($verifica_automat) {
        // Afișează loader-ul IMEDIAT
        echo "<script>
            document.getElementById('loaderOverlay').classList.add('active');
            document.getElementById('loaderText').textContent = 'Verificare diferențe cu Aleph în curs...';
            document.getElementById('loaderProgress').textContent = 'Inițializare...';
        </script>";
        ob_flush();
        flush();
        
        echo "<h2>📋 Log Verificare</h2>";
        echo "<div class='log-container'>";
        
        logMessage("START VERIFICARE - Total cărți: {$total}", 'info');
        
        // **ȘTERGE TOATE CACHE-URILE ÎNAINTE DE VERIFICARE**
        $cache_sterse = stergeToateCache();
        logMessage("Cache-uri șterse: {$cache_sterse} fișiere", 'success');
        
        // **FORȚEAZĂ O SESIUNE NOUĂ ÎN ALEPH**
        logMessage("Inițializare sesiune nouă Aleph...", 'info');
        forteazaSesiuneNouaAleph();
        logMessage("Sesiune nouă Aleph inițializată cu succes", 'success');
        
        usleep(1000000); // 1 secundă
        
        foreach ($toate_carti as $index => $carte) {
            $numar = $index + 1;
            $procent = round((($index + 1) / $total) * 100);
            
            // Actualizează progresul în loader
            echo "<script>
                document.getElementById('loaderProgress').textContent = 'Verificare carte {$numar} din {$total} ({$procent}%)...';
            </script>";
            ob_flush();
            flush();
            
            $cod_cautare = !empty($carte['cod_bare']) ? $carte['cod_bare'] : $carte['cota'];
            
            if (empty($cod_cautare)) {
                logMessage("Carte #{$numar} - SKIP: Fără cod de căutare", 'warning');
                continue;
            }
            
            logMessage("Carte #{$numar} - Cod: {$cod_cautare} | Titlu local: " . substr($carte['titlu'], 0, 50) . "...", 'info');
            
            // **ȘTERGE CACHE-URILE ÎNAINTE DE FIECARE CĂUTARE**
            $cache_sterse_carte = stergeToateCache();
            logMessage("  → Cache-uri șterse: {$cache_sterse_carte} fișiere", 'debug');
            
            try {
                $rezultat_aleph = cautaCarteInAleph($cod_cautare, 'AUTO');
                
                if ($rezultat_aleph['success']) {
                    $date_carte = $rezultat_aleph['data'];
                    $titlu_aleph = trim($date_carte['titlu'] ?? '');
                    $autor_aleph = trim($date_carte['autor'] ?? '');
                    $barcode_aleph = trim($date_carte['barcode'] ?? '');
                    
                    logMessage("  → Răspuns Aleph SUCCESS: Titlu='{$titlu_aleph}' | Autor='{$autor_aleph}' | Barcode='{$barcode_aleph}'", 'success');
                    
                    $titlu_generic = (
                        stripos($titlu_aleph, 'Căutări anterioare') !== false ||
                        stripos($titlu_aleph, 'Previous searches') !== false ||
                        empty($titlu_aleph) ||
                        strlen($titlu_aleph) < 3
                    );
                    
                    if ($titlu_generic) {
                        logMessage("  → Titlu GENERIC detectat - cartea nu există în Aleph", 'warning');
                        continue;
                    }
                    
                    $are_barcode = !empty($barcode_aleph);
                    $are_autor = !empty($autor_aleph);
                    $are_cota = !empty(trim($date_carte['cota'] ?? ''));
                    $are_titlu_valid = !empty($titlu_aleph) && !$titlu_generic && strlen($titlu_aleph) >= 3;
                    
                    $carte_gasita_in_aleph = !$titlu_generic && ($are_barcode || ($are_titlu_valid && ($are_autor || $are_cota)));
                    
                    logMessage("  → Validare: Barcode={$are_barcode}, Autor={$are_autor}, Cota={$are_cota}, TitluValid={$are_titlu_valid}, GăsităÎnAleph={$carte_gasita_in_aleph}", 'debug');
                    
                    if ($carte_gasita_in_aleph) {
                        $titlu = !empty($date_carte['titlu']) ? 
                            (mb_check_encoding($date_carte['titlu'], 'UTF-8') ? 
                                $date_carte['titlu'] : 
                                mb_convert_encoding($date_carte['titlu'], 'UTF-8', 'ISO-8859-2')) : '';
                        
                        $autor = !empty($date_carte['autor']) ? 
                            (mb_check_encoding($date_carte['autor'], 'UTF-8') ? 
                                $date_carte['autor'] : 
                                mb_convert_encoding($date_carte['autor'], 'UTF-8', 'ISO-8859-2')) : '';
                        
                        $titlu_local = trim($carte['titlu'] ?? '');
                        $autor_local = trim($carte['autor'] ?? '');
                        $titlu_aleph_trim = trim($titlu);
                        $autor_aleph_trim = trim($autor);
                        
                        $titlu_diferit = ($titlu_local !== $titlu_aleph_trim);
                        $autor_diferit = ($autor_local !== $autor_aleph_trim);
                        
                        logMessage("  → Comparație: TitluDiferit={$titlu_diferit}, AutorDiferit={$autor_diferit}", 'debug');
                        
                        if ($titlu_diferit) {
                            logMessage("  → DIFERENȚĂ TITLU: Local='{$titlu_local}' vs Aleph='{$titlu_aleph_trim}'", 'warning');
                        }
                        if ($autor_diferit) {
                            logMessage("  → DIFERENȚĂ AUTOR: Local='{$autor_local}' vs Aleph='{$autor_aleph_trim}'", 'warning');
                        }
                        
                        if ($titlu_diferit || $autor_diferit) {
                            $diferente[] = [
                                'carte' => $carte,
                                'titlu_aleph' => $titlu,
                                'autor_aleph' => $autor,
                                'titlu_local' => $titlu_local,
                                'autor_local' => $autor_local
                            ];
                            logMessage("  → ✓ DIFERENȚĂ ADĂUGATĂ la listă (Total diferențe: " . count($diferente) . ")", 'success');
                        } else {
                            logMessage("  → OK - Identic cu Aleph", 'success');
                        }
                    } else {
                        logMessage("  → Carte nu găsită în Aleph (validare eșuată)", 'warning');
                    }
                } else {
                    logMessage("  → Răspuns Aleph FAILED: " . ($rezultat_aleph['mesaj'] ?? 'Eroare necunoscută'), 'error');
                }
            } catch (Exception $e) {
                logMessage("  → EXCEPȚIE: " . $e->getMessage(), 'error');
            }
            
            // **ȘTERGE CACHE-URILE DUPĂ FIECARE CĂUTARE**
            stergeToateCache();
            
            // **DELAY între căutări**
            usleep(800000); // 0.8 secunde
        }
        
        // SALVEAZĂ DIFERENȚELE ÎN SESIUNE
        $_SESSION['diferente'] = $diferente;
        logMessage("Salvat în sesiune: " . count($diferente) . " diferențe", 'success');
        
        // Verificare sesiune
        $diferente_in_sesiune = count($_SESSION['diferente'] ?? []);
        logMessage("Verificare sesiune după salvare: {$diferente_in_sesiune} diferențe", 'info');
        
        // Șterge cache-urile la final
        stergeToateCache();
        logMessage("FINAL - Cache-uri curățate", 'success');
        
        echo "</div>"; // închide log-container
        
        // Ascunde loader-ul DOAR LA FINAL
        echo "<script>
            document.getElementById('loaderOverlay').classList.remove('active');
        </script>";
        ob_flush();
        flush();
        
        // Afișează diferențele găsite
        $numar_diferente = count($diferente);
        
        echo "<h2>📋 Statistici</h2>";
        echo "<div class='info'>";
        echo "<p><strong>Total cărți în baza de date:</strong> $total</p>";
        
        if ($numar_diferente > 0) {
            echo "<p class='warning'><strong>⚠️ Cărți cu diferențe față de Aleph:</strong> $numar_diferente</p>";
            echo "</div>";
            
            // Tabel cu diferențele
            echo "<h2>📊 Diferențe găsite</h2>";
            echo "<table class='diferente-tabel'>";
            echo "<tr><th>#</th><th>Cod Bare</th><th>Titlu</th><th>Autor</th></tr>";
            
            foreach ($diferente as $idx => $dif) {
                $numar = $idx + 1;
                $titlu_local = htmlspecialchars($dif['titlu_local'], ENT_QUOTES, 'UTF-8');
                $titlu_aleph = htmlspecialchars($dif['titlu_aleph'], ENT_QUOTES, 'UTF-8');
                $autor_local = htmlspecialchars($dif['autor_local'], ENT_QUOTES, 'UTF-8');
                $autor_aleph = htmlspecialchars($dif['autor_aleph'], ENT_QUOTES, 'UTF-8');
                $cod_bare = htmlspecialchars($dif['carte']['cod_bare'], ENT_QUOTES, 'UTF-8');
                
                $titlu_diferit = ($dif['titlu_local'] !== $dif['titlu_aleph']);
                $autor_diferit = ($dif['autor_local'] !== $dif['autor_aleph']);
                
                echo "<tr>";
                echo "<td>$numar</td>";
                echo "<td>$cod_bare</td>";
                echo "<td>";
                if ($titlu_diferit) {
                    echo "<span class='vechi'>$titlu_local</span><br>↓<br><span class='nou'>$titlu_aleph</span>";
                } else {
                    echo "<span>$titlu_local</span>";
                }
                echo "</td>";
                echo "<td>";
                if ($autor_diferit) {
                    echo "<span class='vechi'>$autor_local</span><br>↓<br><span class='nou'>$autor_aleph</span>";
                } else {
                    echo "<span>$autor_local</span>";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Buton de sincronizare
            echo "<div style='text-align: center; margin-top: 30px;'>";
            echo "<form method='POST' id='formSincronizeaza'>";
            echo "<button type='submit' name='sincronizeaza' class='btn-sincronizeaza'>";
            echo "🔄 Sincronizează " . ($numar_diferente == 1 ? 'cartea găsită' : "cele $numar_diferente cărți găsite") . " cu Aleph";
            echo "</button>";
            echo "</form>";
            echo "</div>";
        } else {
            echo "<p class='success'><strong>✅ Nu s-au găsit diferențe!</strong> Toate cărțile sunt sincronizate cu Aleph.</p>";
            echo "</div>";
        }
    } else {
        echo "<h2>📋 Statistici</h2>";
        echo "<div class='info'>";
        echo "<p><strong>Total cărți în baza de date:</strong> $total</p>";
        echo "</div>";
        
        echo "<div style='display: flex; gap: 20px; margin-top: 30px; justify-content: center;'>";
        echo "<a href='?verifica=1' class='btn-verifica'>";
        echo "🔍 Verifică diferențe cu Aleph ($total cărți)";
        echo "</a>";
        echo "</div>";
    }
}

echo "    </div>
    <div class=\"app-footer\">
        <p>Dezvoltare web: Neculai Ioan Fantanaru</p>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method=\"POST\"]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    // Loader-ul se afișează în PHP
                });
            });
        });
    </script>
</body>
</html>";
?>