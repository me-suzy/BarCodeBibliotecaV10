<?php
/**
 * Script pentru scanarea aplicației și identificarea fișierelor esențiale
 * Generează un XML cu toate fișierele folosite de aplicație
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = __DIR__;
$fisiere_esențiale = [];
$fisiere_scanate = [];
$foldere_excluse = ['BackUp', 'Securitate', 'Verificare Conexiune', 'Configuratie Server Linux', 'assets', 'imagini', 'logs', 'emails_log', 'scripts_saved', 'Specificatii', '__pycache__', 'php', 'python', 'fpdf', 'phpmailer'];

/**
 * Extrage toate referințele dintr-un fișier PHP
 */
function extrageReferinte($file_path, $root_dir) {
    $referinte = [
        'require' => [],
        'include' => [],
        'href' => [],
        'action' => [],
        'src' => [],
        'css' => [],
        'js' => []
    ];
    
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return $referinte;
    }
    
    $content = file_get_contents($file_path);
    
    // Extrage require/include
    preg_match_all('/(?:require|include)(?:_once)?\s*[\'"]\s*([^\'"]+\.php)[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['require'] = array_unique($matches[1]);
    }
    
    // Extrage href (link-uri)
    preg_match_all('/href\s*=\s*[\'"]([^\'"]+\.php[^\'"]*)[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['href'] = array_unique($matches[1]);
    }
    
    // Extrage action (formulare)
    preg_match_all('/action\s*=\s*[\'"]([^\'"]+\.php[^\'"]*)[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['action'] = array_unique($matches[1]);
    }
    
    // Extrage src (JavaScript, imagini)
    preg_match_all('/src\s*=\s*[\'"]([^\'"]+\.(?:js|css|png|jpg|jpeg|gif|svg))[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['src'] = array_unique($matches[1]);
    }
    
    // Extrage link-uri CSS
    preg_match_all('/<link[^>]+href\s*=\s*[\'"]([^\'"]+\.css)[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['css'] = array_unique($matches[1]);
    }
    
    // Extrage script-uri JS
    preg_match_all('/<script[^>]+src\s*=\s*[\'"]([^\'"]+\.js)[\'"]/', $content, $matches);
    if (!empty($matches[1])) {
        $referinte['js'] = array_unique($matches[1]);
    }
    
    return $referinte;
}

/**
 * Normalizează calea unui fișier
 */
function normalizeazaCale($file, $current_dir) {
    // Elimină query string-uri
    $file = preg_replace('/\?.*$/', '', $file);
    
    // Dacă începe cu /, este relativ la root
    if (strpos($file, '/') === 0) {
        return ltrim($file, '/');
    }
    
    // Dacă conține .., rezolvă calea
    if (strpos($file, '..') !== false) {
        $parts = explode('/', $file);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }
        return implode('/', $resolved);
    }
    
    // Cale relativă
    return $file;
}

/**
 * Verifică dacă un fișier este în root
 */
function esteInRoot($file, $root_dir) {
    $full_path = realpath($root_dir . '/' . $file);
    $root_path = realpath($root_dir);
    return $full_path && strpos($full_path, $root_path) === 0;
}

/**
 * Scanează recursiv un director
 */
function scaneazaDirector($dir, $root_dir, &$fisiere_esențiale, &$fisiere_scanate, $foldere_excluse, $depth = 0) {
    if ($depth > 10) return; // Previne recursiune infinită
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $full_path = $dir . '/' . $item;
        $relative_path = str_replace($root_dir . '/', '', $full_path);
        
        // Verifică dacă este în folder exclus
        $exclus = false;
        foreach ($foldere_excluse as $folder_exclus) {
            if (strpos($relative_path, $folder_exclus) === 0) {
                $exclus = true;
                break;
            }
        }
        if ($exclus) continue;
        
        if (is_dir($full_path)) {
            scaneazaDirector($full_path, $root_dir, $fisiere_esențiale, $fisiere_scanate, $foldere_excluse, $depth + 1);
        } elseif (is_file($full_path) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            // Adaugă fișierul la lista de scanat
            if (!in_array($relative_path, $fisiere_scanate)) {
                $fisiere_scanate[] = $relative_path;
                
                // Extrage referințe
                $referinte = extrageReferinte($full_path, $root_dir);
                
                // Adaugă fișierul curent dacă este în root
                if (esteInRoot($relative_path, $root_dir)) {
                    $fisiere_esențiale[$relative_path] = [
                        'tip' => 'pagina',
                        'referinte' => $referinte
                    ];
                }
                
                // Adaugă referințele
                foreach ($referinte as $tip => $files) {
                    foreach ($files as $file) {
                        $file_normalizat = normalizeazaCale($file, dirname($relative_path));
                        
                        if (esteInRoot($file_normalizat, $root_dir)) {
                            if (!isset($fisiere_esențiale[$file_normalizat])) {
                                $fisiere_esențiale[$file_normalizat] = [
                                    'tip' => $tip === 'require' || $tip === 'include' ? 'include' : 
                                            ($tip === 'css' ? 'css' : ($tip === 'js' ? 'js' : 'referinta')),
                                    'referinte' => []
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}

// Scanează directorul root
echo "🔍 Scanare aplicație în curs...\n";
scaneazaDirector($root_dir, $root_dir, $fisiere_esențiale, $fisiere_scanate, $foldere_excluse);

// Adaugă și fișierele CSS/JS din root
$extensii_importante = ['css', 'js', 'json', 'txt'];
foreach ($extensii_importante as $ext) {
    $files = glob($root_dir . '/*.' . $ext);
    foreach ($files as $file) {
        $relative = basename($file);
        if (!isset($fisiere_esențiale[$relative])) {
            $fisiere_esențiale[$relative] = [
                'tip' => $ext,
                'referinte' => []
            ];
        }
    }
}

// Sortează fișierele
ksort($fisiere_esențiale);

// Generează XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$root = $xml->createElement('aplicatie');
$root->setAttribute('nume', 'BarCode Biblioteca');
$root->setAttribute('data_scanare', date('Y-m-d H:i:s'));
$xml->appendChild($root);

$fisiere_node = $xml->createElement('fisiere_esentiale');
$root->appendChild($fisiere_node);

foreach ($fisiere_esențiale as $file => $info) {
    $file_node = $xml->createElement('fisier');
    $file_node->setAttribute('nume', $file);
    $file_node->setAttribute('tip', $info['tip']);
    $file_node->setAttribute('exista', file_exists($root_dir . '/' . $file) ? 'da' : 'nu');
    
    if (!empty($info['referinte'])) {
        $referinte_node = $xml->createElement('referinte');
        foreach ($info['referinte'] as $tip => $files) {
            if (!empty($files)) {
                $tip_node = $xml->createElement('tip', $tip);
                foreach ($files as $ref_file) {
                    $ref_node = $xml->createElement('referinta', $ref_file);
                    $tip_node->appendChild($ref_node);
                }
                $referinte_node->appendChild($tip_node);
            }
        }
        $file_node->appendChild($referinte_node);
    }
    
    $fisiere_node->appendChild($file_node);
}

// Salvează XML
$xml_file = $root_dir . '/fisiere_esentiale.xml';
$xml->save($xml_file);

echo "✅ Scanare completă!\n";
echo "📄 Fișiere esențiale găsite: " . count($fisiere_esențiale) . "\n";
echo "💾 XML salvat în: $xml_file\n\n";

// Listează fișierele esențiale
echo "📋 Lista fișiere esențiale:\n";
echo str_repeat("=", 80) . "\n";
foreach ($fisiere_esențiale as $file => $info) {
    $exists = file_exists($root_dir . '/' . $file) ? '✅' : '❌';
    echo sprintf("%s %-50s [%s]\n", $exists, $file, $info['tip']);
}

echo "\n📊 Rezumat:\n";
$tipuri = [];
foreach ($fisiere_esențiale as $file => $info) {
    $tip = $info['tip'];
    if (!isset($tipuri[$tip])) {
        $tipuri[$tip] = 0;
    }
    $tipuri[$tip]++;
}

foreach ($tipuri as $tip => $count) {
    echo "  - $tip: $count fișiere\n";
}

