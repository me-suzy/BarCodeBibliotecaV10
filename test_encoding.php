<?php
/**
 * Script de test pentru verificarea encoding-ului din Aleph
 */

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'aleph_api.php';

echo "<!DOCTYPE html>
<html lang='ro'>
<head>
    <meta charset='UTF-8'>
    <title>Test Encoding Aleph</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .hex { font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Test Encoding Aleph</h1>";

// Șterge fișierele de debug anterioare
@unlink('debug_encoding.txt');
@unlink('debug_aleph_raw.html');
@unlink('debug_aleph_converted.html');

// Test cu un cod de carte (înlocuiește cu un cod real din sistemul tău)
$test_code = isset($_GET['cod']) ? $_GET['cod'] : 'CI95082';

echo "<div class='info'><strong>📚 Testare cu cod:</strong> " . htmlspecialchars($test_code, ENT_QUOTES, 'UTF-8') . "</div>";

$result = cautaCarteInAleph($test_code, 'AUTO');

// Verificare suplimentară bazată pe conținutul HTML brut de la Aleph
$verificare_html = null;
$html_to_check = '';
$pattern_gasit_inexistent = '';
$pattern_gasit_existent = '';
$gasit_inexistent = false;
$gasit_existent = false;

// Colectează HTML-ul din toate sursele disponibile
if (file_exists('debug_aleph_raw.html')) {
    $html_to_check .= file_get_contents('debug_aleph_raw.html') . "\n";
}
if (file_exists('debug_session_raw.html')) {
    $html_to_check .= file_get_contents('debug_session_raw.html') . "\n";
}
// Verifică și în debug info dacă există răspunsuri de căutare
if (isset($result['debug'])) {
    foreach ($result['debug'] as $key => $value) {
        if (is_string($value) && (stripos($key, 'response') !== false || stripos($key, 'preview') !== false)) {
            $html_to_check .= $value . "\n";
        }
    }
}

if (!empty($html_to_check)) {
    // Pattern-uri care indică că cartea NU există
    $pattern_inexistent = [
        'Trucuri în căutare',
        'Trucuri in cautare',
        'Înregistrarea cerută nu este în baza de date',
        'Inregistrarea ceruta nu este in baza de date',
        'nu este în baza de date',
        'nu este in baza de date',
        'Cu cât introduceţi mai multe cuvinte',
        'Cu cat introduceti mai multe cuvinte',
        'Operatorul boolean ŞI',
        'Operatorul boolean SI',
        'Utilizarea caracterului ? permite',
        'Your search found no results',
        'Căutarea nu a avut rezultate',
        'nu a avut rezultate',
        'No results found'
    ];
    
    // Pattern-uri care indică că cartea EXISTĂ
    $pattern_existent = [
        'Rezultate pentru Barcode=',
        'Rezultate pentru Barcode =',
        'Rezultate pentru',
        'Rezultatele pentru',
        'Înregistrări 1 - 1 din 1',
        'Inregistrari 1 - 1 din 1',
        'Înregistrări 1 -',
        'Inregistrari 1 -',
        'Sortat după:',
        'Sortat dupa:',
        'Format	Autor	Titlu',
        'Format Autor Titlu',
        'Opţiuni de sortare:',
        'Optiuni de sortare:'
    ];
    
    foreach ($pattern_inexistent as $pattern) {
        if (stripos($html_to_check, $pattern) !== false) {
            $gasit_inexistent = true;
            $pattern_gasit_inexistent = $pattern;
            break;
        }
    }
    
    foreach ($pattern_existent as $pattern) {
        if (stripos($html_to_check, $pattern) !== false) {
            $gasit_existent = true;
            $pattern_gasit_existent = $pattern;
            break;
        }
    }
    
    if ($gasit_inexistent && !$gasit_existent) {
        $verificare_html = 'inexistent';
    } elseif ($gasit_existent && !$gasit_inexistent) {
        $verificare_html = 'existent';
    } else {
        $verificare_html = 'neclar';
    }
}

// Afișează rezultatul verificării HTML
if ($verificare_html !== null) {
    echo "<h2>🔍 Verificare Suplimentară (Analiză HTML Aleph):</h2>";
    if ($verificare_html === 'inexistent') {
        echo "<div class='error'><strong>❌ Cartea NU există în Aleph</strong><br>";
        echo "Răspunsul HTML conține pattern-uri care indică că cartea nu a fost găsită:<br>";
        if (!empty($pattern_gasit_inexistent)) {
            echo "• Pattern găsit: <code>" . htmlspecialchars($pattern_gasit_inexistent, ENT_QUOTES, 'UTF-8') . "</code><br>";
        }
        echo "• Secțiunea 'Trucuri în căutare' sau mesaj de eroare este prezentă<br>";
        echo "• Nu există rezultate în tabelul de căutare</div>";
    } elseif ($verificare_html === 'existent') {
        echo "<div class='success'><strong>✅ Cartea EXISTĂ în Aleph</strong><br>";
        echo "Răspunsul HTML conține pattern-uri care indică că cartea a fost găsită:<br>";
        if (!empty($pattern_gasit_existent)) {
            echo "• Pattern găsit: <code>" . htmlspecialchars($pattern_gasit_existent, ENT_QUOTES, 'UTF-8') . "</code><br>";
        }
        echo "• Apare 'Rezultate pentru Barcode=' sau tabel cu rezultate<br>";
        echo "• Există rezultate în tabelul de căutare</div>";
    } else {
        echo "<div class='info'><strong>⚠️ Status neclar</strong><br>";
        echo "Nu s-au putut identifica clar pattern-urile în răspunsul HTML.<br>";
        echo "HTML-ul verificat: " . number_format(strlen($html_to_check)) . " caractere<br>";
        if ($gasit_inexistent && $gasit_existent) {
            echo "⚠️ S-au găsit AMBELE tipuri de pattern-uri (conflict).</div>";
        } else {
            echo "ℹ️ Nu s-au găsit pattern-uri clare de existență sau inexistență.</div>";
        }
    }
    echo "<hr>";
}

if ($result['success']) {
    echo "<div class='success'>✅ SUCCES! Cartea a fost găsită.</div>";
    
    $data = $result['data'];
    
    echo "<h2>📖 Date extrase:</h2>";
    echo "<p><strong>Titlu:</strong> " . htmlspecialchars($data['titlu'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p><strong>Autor:</strong> " . htmlspecialchars($data['autor'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p><strong>Cota:</strong> " . htmlspecialchars($data['cota'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p><strong>Barcode:</strong> " . htmlspecialchars($data['barcode'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</p>";
    
    // Analiză encoding pentru titlu
    if (!empty($data['titlu'])) {
        $titlu = $data['titlu'];
        $detected = mb_detect_encoding($titlu, ['UTF-8', 'ISO-8859-2', 'ISO-8859-1'], true);
        
        echo "<h2>🔬 Analiză Encoding - Titlu:</h2>";
        echo "<p><strong>Encoding detectat:</strong> " . ($detected ?: 'UNKNOWN') . "</p>";
        echo "<p><strong>Este UTF-8 valid?</strong> " . (mb_check_encoding($titlu, 'UTF-8') ? '✅ DA' : '❌ NU') . "</p>";
        
        // Hex dump pentru primele 50 de caractere
        echo "<h3>Hex Dump (primele 50 caractere):</h3>";
        echo "<pre class='hex'>";
        $hex = '';
        $len = min(50, strlen($titlu));
        for ($i = 0; $i < $len; $i++) {
            $hex .= bin2hex($titlu[$i]) . " ";
            if (($i + 1) % 16 == 0) $hex .= "\n";
        }
        echo $hex;
        echo "</pre>";
        
        // Verifică diacritice
        echo "<h3>🔍 Verificare Diacritice:</h3>";
        $diacritice = ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'];
        $gasite = [];
        foreach ($diacritice as $diacritic) {
            if (strpos($titlu, $diacritic) !== false) {
                $gasite[] = $diacritic;
            }
        }
        if (!empty($gasite)) {
            echo "<p class='success'>✅ Diacritice găsite: " . implode(', ', $gasite) . "</p>";
        } else {
            echo "<p class='error'>❌ Nu s-au găsit diacritice în titlu!</p>";
        }
    }
    
    // Verifică fișierele de debug
    echo "<h2>📝 Fișiere Debug:</h2>";
    
    if (file_exists('debug_encoding.txt')) {
        echo "<div class='info'><strong>debug_encoding.txt:</strong><pre>" . htmlspecialchars(file_get_contents('debug_encoding.txt'), ENT_QUOTES, 'UTF-8') . "</pre></div>";
    }
    
    if (file_exists('debug_aleph_raw.html')) {
        $raw_size = filesize('debug_aleph_raw.html');
        echo "<div class='info'><strong>debug_aleph_raw.html:</strong> " . number_format($raw_size) . " bytes <a href='debug_aleph_raw.html' target='_blank'>Deschide</a></div>";
    }
    
    if (file_exists('debug_aleph_converted.html')) {
        $conv_size = filesize('debug_aleph_converted.html');
        echo "<div class='info'><strong>debug_aleph_converted.html:</strong> " . number_format($conv_size) . " bytes <a href='debug_aleph_converted.html' target='_blank'>Deschide</a></div>";
    }
    
} else {
    echo "<div class='error'>❌ EROARE: " . htmlspecialchars($result['mesaj'] ?? 'Eroare necunoscută', ENT_QUOTES, 'UTF-8') . "</div>";
    
    if (isset($result['debug'])) {
        echo "<h2>🐛 Debug Info:</h2>";
        echo "<pre>" . htmlspecialchars(print_r($result['debug'], true), ENT_QUOTES, 'UTF-8') . "</pre>";
        
        // Afișează preview-ul răspunsului session
        if (isset($result['debug']['session_response_preview'])) {
            echo "<h3>📄 Session Response Preview (primele 500 caractere):</h3>";
            echo "<pre style='max-height: 300px; overflow-y: auto;'>" . htmlspecialchars($result['debug']['session_response_preview'], ENT_QUOTES, 'UTF-8') . "</pre>";
        }
        
        // Afișează toate match-urile găsite
        if (isset($result['debug']['all_matches']) && !empty($result['debug']['all_matches'])) {
            echo "<h3>🔍 Pattern-uri găsite în răspuns:</h3>";
            echo "<ul>";
            foreach ($result['debug']['all_matches'] as $match) {
                echo "<li>" . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . "</li>";
            }
            echo "</ul>";
        }
    }
    
    // Verifică fișierele de debug
    echo "<h2>📝 Fișiere Debug Session:</h2>";
    
    if (file_exists('debug_session_raw.html')) {
        $raw_size = filesize('debug_session_raw.html');
        echo "<div class='info'><strong>debug_session_raw.html:</strong> " . number_format($raw_size) . " bytes <a href='debug_session_raw.html' target='_blank'>Deschide</a></div>";
    } else {
        echo "<div class='error'>❌ debug_session_raw.html nu există</div>";
    }
    
    if (file_exists('debug_session_converted.html')) {
        $conv_size = filesize('debug_session_converted.html');
        echo "<div class='info'><strong>debug_session_converted.html:</strong> " . number_format($conv_size) . " bytes <a href='debug_session_converted.html' target='_blank'>Deschide</a></div>";
    }
}

echo "<hr>";
echo "<h2>🧪 Testează alt cod:</h2>";
echo "<form method='GET' style='margin-top: 20px;'>
    <input type='text' name='cod' placeholder='Introdu cod carte (ex: CI95082)' value='" . htmlspecialchars($test_code, ENT_QUOTES, 'UTF-8') . "' style='padding: 10px; width: 300px; border: 1px solid #ddd; border-radius: 5px;'>
    <button type='submit' style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Testează</button>
</form>";

echo "    </div>
</body>
</html>";
?>

