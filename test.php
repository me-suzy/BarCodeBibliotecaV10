<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = [];
$error = null;
$debug = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['search'])) {
    $searchTerm = trim($_POST['search']);
    $searchType = $_POST['search_type'] ?? 'WLB';
    
    $debug[] = "=== CĂUTARE: " . $searchTerm . " ===";
    $debug[] = "Tip: " . ($searchType === 'BAR' ? 'Barcode' : 'Cotă') . "\n";
    
    try {
        $baseUrl = 'YOUR-IP-or-http://WEBSITE:8991/F/';
        
        $ch = curl_init($baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $html = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        preg_match('/\/F\/([A-Z0-9\-]+)/', $finalUrl, $match);
        $sessionId = $match[1] ?? '';
        
        if (empty($sessionId)) {
            preg_match('/href="[^"]*\/F\/([A-Z0-9\-]+)\?func=/i', $html, $match);
            $sessionId = $match[1] ?? '';
        }
        
        if (empty($sessionId)) {
            throw new Exception('Nu s-a putut obține session ID');
        }
        
        $debug[] = "Session ID: " . substr($sessionId, 0, 20) . "...";
        
        $searchUrl = "YOUR-IP-or-http://WEBSITE:8991/F/{$sessionId}?func=find-b" .
                     "&request=" . urlencode($searchTerm) .
                     "&find_code={$searchType}" .
                     "&adjacent=N" .
                     "&local_base=RAI01";
        
        $debug[] = "\nPasul 1: Căutare inițială...";
        
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $searchHtml = curl_exec($ch);
        curl_close($ch);
        
        if (empty($searchHtml)) {
            throw new Exception('Eroare la căutare');
        }
        
        $debug[] = "Răspuns: " . strlen($searchHtml) . " bytes";
        
        file_put_contents('debug_search.html', $searchHtml);
        $debug[] = "HTML salvat în debug_search.html";
        
        if (stripos($searchHtml, 'Nici o înregistrare') !== false) {
            throw new Exception('Nu s-au găsit rezultate pentru "' . $searchTerm . '"');
        }
        
        $itemUrls = [];
        
        // Metodă 1: Link direct către func=item-global cu ACAD
        if (preg_match('/<a[^>]+href=["\']([^"\']+func=item-global[^"\']+sub_library=ACAD[^"\']*)["\'][^>]*>/i', $searchHtml, $match)) {
            $href = str_replace('&amp;', '&', $match[1]);
            
            if (strpos($href, 'http') === 0) {
                $itemUrls[] = $href;
            } elseif (strpos($href, '/F/') === 0) {
                $itemUrls[] = 'YOUR-IP-or-http://WEBSITE:8991' . $href;
            } else {
                $itemUrls[] = 'YOUR-IP-or-http://WEBSITE:8991/F/' . $sessionId . '?' . ltrim($href, '?');
            }
            
            $debug[] = "\n✓ METODĂ 1: Link direct către ACAD";
        } 
        // Metodă 2: Link către "Biblioteca Academiei Iași(X/Y)"
        else {
            $debug[] = "\nMETODĂ 2: Căutare link 'Biblioteca Academiei Iași'...";
            
            // Pattern specific pentru Biblioteca Academiei Iași
            if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>\s*Biblioteca\s+Academiei\s+Ia[șs]i\s*\(\s*\d+\s*\/\s*\d+\s*\)/is', $searchHtml, $match)) {
                $href = str_replace('&amp;', '&', $match[1]);
                
                // Construim URL-ul complet
                if (strpos($href, 'http') === 0) {
                    $intermediateUrl = $href;
                } elseif (strpos($href, '/F/') === 0) {
                    $intermediateUrl = 'YOUR-IP-or-http://WEBSITE:8991' . $href;
                } else {
                    $intermediateUrl = 'YOUR-IP-or-http://WEBSITE:8991/F/' . $sessionId . '?' . ltrim($href, '?');
                }
                
                $debug[] = "✓ Găsit link intermediar";
                $debug[] = "  URL: " . substr($intermediateUrl, 0, 120) . "...";
                
                // Accesăm link-ul intermediar
                $debug[] = "\nPasul 2: Accesare pagină exemplare ACAD...";
                
                $ch = curl_init($intermediateUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                
                $itemsPageHtml = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                $debug[] = "HTTP Code: " . $httpCode;
                
                if (!empty($curlError)) {
                    $debug[] = "cURL Error: " . $curlError;
                }
                
                if (empty($itemsPageHtml) || $httpCode != 200) {
                    throw new Exception('Nu s-a putut accesa pagina cu exemplare');
                }
                
                $debug[] = "Răspuns: " . strlen($itemsPageHtml) . " bytes";
                file_put_contents('debug_items_page.html', $itemsPageHtml);
                $debug[] = "Salvat în debug_items_page.html";
                
                // Această pagină devine sursa pentru extragere
                $searchHtml = $itemsPageHtml;
                $itemUrls[] = $intermediateUrl;
            } else {
                $debug[] = "❌ Nu s-a găsit link către Biblioteca Academiei Iași";
            }
        }
        
        // Metodă 3: doc_number cu ACAD
        if (empty($itemUrls)) {
            $debug[] = "\nMETODĂ 3: Căutare doc_number...";
            
            preg_match_all('/doc_number=(\d+)/i', $searchHtml, $docMatches);
            $uniqueDocs = array_unique($docMatches[1]);
            
            $debug[] = "doc_number găsite: " . count($uniqueDocs);
            
            if (count($uniqueDocs) > 0) {
                foreach ($uniqueDocs as $docNum) {
                    $url = "YOUR-IP-or-http://WEBSITE:8991/F/{$sessionId}?func=item-global&doc_library=RAI01&doc_number={$docNum}&sub_library=ACAD";
                    $itemUrls[] = $url;
                    $debug[] = "  ✓ URL: doc=" . $docNum;
                }
            }
        }
        
        if (empty($itemUrls)) {
            throw new Exception('Nu s-au găsit exemplare în Biblioteca Academiei Iași');
        }
        
        $debug[] = "\n✓ Total URL-uri: " . count($itemUrls);
        
        // Parcurgem URL-urile
        foreach ($itemUrls as $idx => $itemUrl) {
            $debug[] = "\nPasul 3: Extragere date...";
            
            // Dacă avem HTML din pasul 2, îl folosim
            $detailsHtml = isset($itemsPageHtml) ? $itemsPageHtml : null;
            
            // Altfel, facem request
            if (empty($detailsHtml)) {
                $ch = curl_init($itemUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                
                $detailsHtml = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if (empty($detailsHtml) || $httpCode != 200) {
                    $debug[] = "  ❌ Eroare la obținere date";
                    continue;
                }
            }
            
            $debug[] = "  HTML: " . strlen($detailsHtml) . " bytes";
            
            file_put_contents("debug_details_{$idx}.html", $detailsHtml);
            $debug[] = "  Salvat: debug_details_{$idx}.html";
            
            // Extragem autor și titlu
            $autorComun = '';
            $titluComun = '';
            
            if (preg_match('/<td[^>]*class=["\']?td1["\']?[^>]*>\s*Author\s+(.+?)<br>/is', $detailsHtml, $m)) {
                $autorTitlu = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
                
                if (preg_match('/^(.+?)\s*\/\s*(.+)$/s', $autorTitlu, $parts)) {
                    $partea1 = trim($parts[1]);
                    $partea2 = trim($parts[2]);
                    
                    if (preg_match('/^(.+?)\.\s+([A-Z].+)$/s', $partea1, $split)) {
                        $autorComun = trim($split[1]);
                        $titluComun = trim($split[2]);
                    } else {
                        $autorComun = $partea2;
                        $titluComun = $partea1;
                    }
                } else {
                    $titluComun = $autorTitlu;
                }
                
                if (strpos($titluComun, ';') !== false) {
                    $titluComun = trim(explode(';', $titluComun)[0]);
                }
                
                $debug[] = "  ✓ Autor: " . substr($autorComun, 0, 50);
                $debug[] = "  ✓ Titlu: " . substr($titluComun, 0, 70);
            }
            
            // Extragem rândurile
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $detailsHtml, $allRows);
            
            $debug[] = "  Total rânduri: " . count($allRows[1]);
            
            $rowsWithData = [];
            foreach ($allRows[1] as $row) {
                if (stripos($row, 'class=td1') !== false || stripos($row, 'class="td1"') !== false) {
                    $rowsWithData[] = $row;
                }
            }
            
            $debug[] = "  Rânduri cu td1: " . count($rowsWithData);
            
            foreach ($rowsWithData as $rowHtml) {
                preg_match_all('/<td[^>]*class=["\']?td1["\']?[^>]*>(.*?)<\/td>/is', $rowHtml, $cells);
                
                if (empty($cells[1]) || count($cells[1]) < 3) {
                    continue;
                }
                
                $result = [
                    'autor' => $autorComun,
                    'titlu' => $titluComun,
                    'status' => '',
                    'cota' => '',
                    'barcode' => '',
                    'biblioteca' => '',
                    'colectie' => '',
                    'localizare2' => ''
                ];
                
                foreach ($cells[1] as $cell) {
                    $text = trim(preg_replace('/\s+/', ' ', strip_tags($cell)));
                    
                    if (empty($text) || strlen($text) < 2) continue;
                    
                    // Status
                    if (preg_match('/(Se împr\.|Pe raft|Pentru împrumut|Împrumutat|Doar pentru)/i', $text)) {
                        $result['status'] = $text;
                    }
                    // Cotă
                    elseif (preg_match('/[A-Z]+.*?[\/\-]\d+/i', $text) && !preg_match('/^\d{4}\/\d{2}$/', $text) && strlen($text) < 50) {
                        $result['cota'] = $text;
                    }
                    // Barcode
                    elseif (preg_match('/^[A-Z]{0,3}\d{4,}$/i', $text) || preg_match('/^\d+-\d+$/i', $text)) {
                        $result['barcode'] = $text;
                    }
                    // Bibliotecă
                    elseif (stripos($text, 'Biblioteca') !== false && stripos($text, 'Academiei') !== false) {
                        $result['biblioteca'] = $text;
                    }
                    // Colecție
                    elseif (preg_match('/(Cărţi|Carte|sala|depozit|periodice)/i', $text) && strlen($text) > 5 && strlen($text) < 80) {
                        $result['colectie'] = $text;
                    }
                    // Localizare2
                    elseif (preg_match('/^\d{4}\/\d{2}$/', $text)) {
                        $result['localizare2'] = $text;
                    }
                }
                
                if (!empty($result['cota']) || !empty($result['barcode'])) {
                    $results[] = $result;
                    $debug[] = "  ✓ Extras: Cotă=[" . ($result['cota'] ?: 'N/A') . "], Barcode=[" . ($result['barcode'] ?: 'N/A') . "]";
                }
            }
        }
        
        $debug[] = "\n✓ Total exemplare: " . count($results);
        
        if (empty($results)) {
            throw new Exception('Nu s-au putut extrage date din pagină');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        $debug[] = "\n❌ " . $error;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Căutare Aleph BARI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 1100px;
            margin: 0 auto;
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2em;
        }
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            margin-bottom: 30px;
        }
        input[type="text"] {
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        select {
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .results-count {
            background: #e6f2ff;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #0066cc;
            font-weight: bold;
            text-align: center;
        }
        .result-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .result-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .result-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin: -25px -25px 20px -25px;
            font-weight: bold;
        }
        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        .result-item {
            padding: 12px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .result-item.highlight {
            background: #f0f4ff;
            border-left: 4px solid #4c51bf;
        }
        .result-label {
            font-weight: bold;
            color: #667eea;
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .result-value {
            color: #333;
            font-size: 15px;
        }
        .error {
            background: #fee;
            border-left: 4px solid #f44;
            padding: 15px;
            border-radius: 8px;
            color: #c33;
            margin-bottom: 20px;
        }
        .debug {
            background: #1a202c;
            color: #68d391;
            border-radius: 8px;
            padding: 20px;
            font-size: 12px;
            font-family: 'Consolas', monospace;
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        .debug-title {
            color: #63b3ed;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Căutare Catalog Aleph BARI</h1>
        
        <form method="POST" class="search-form">
            <input type="text" name="search" placeholder="Introdu cotă sau barcode" 
                   value="<?php echo htmlspecialchars($_POST['search'] ?? ''); ?>" required>
            
            <select name="search_type">
                <option value="WLB" <?php echo (($_POST['search_type'] ?? 'WLB') === 'WLB') ? 'selected' : ''; ?>>Cotă</option>
                <option value="BAR" <?php echo (($_POST['search_type'] ?? '') === 'BAR') ? 'selected' : ''; ?>>Barcode</option>
            </select>
            
            <button type="submit">Caută</button>
        </form>
        
        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($results) && empty($error)): ?>
            <div class="results-count">
                📚 S-au găsit <?php echo count($results); ?> exemplar<?php echo count($results) > 1 ? 'e' : ''; ?>
            </div>
            
            <?php foreach ($results as $i => $result): ?>
                <div class="result-card">
                    <div class="result-header">
                        Exemplarul <?php echo $i + 1; ?> din <?php echo count($results); ?>
                    </div>
                    
                    <div class="result-grid">
                        <?php if (!empty($result['titlu'])): ?>
                        <div class="result-item highlight" style="grid-column: 1 / -1;">
                            <span class="result-label">📖 Titlu</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['titlu']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['autor'])): ?>
                        <div class="result-item highlight" style="grid-column: 1 / -1;">
                            <span class="result-label">✍️ Autor</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['autor']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['cota'])): ?>
                        <div class="result-item">
                            <span class="result-label">🏷️ Cotă</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['cota']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['barcode'])): ?>
                        <div class="result-item">
                            <span class="result-label">🔢 Barcode</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['barcode']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['status'])): ?>
                        <div class="result-item">
                            <span class="result-label">📊 Status</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['status']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['biblioteca'])): ?>
                        <div class="result-item">
                            <span class="result-label">🏛️ Bibliotecă</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['biblioteca']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['colectie'])): ?>
                        <div class="result-item">
                            <span class="result-label">📚 Colecție</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['colectie']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['localizare2'])): ?>
                        <div class="result-item">
                            <span class="result-label">📍 Localizare 2</span>
                            <span class="result-value"><?php echo htmlspecialchars($result['localizare2']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($debug)): ?>
            <div class="debug">
                <div class="debug-title">📋 Debug</div><?php echo htmlspecialchars(implode("\n", $debug)); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>