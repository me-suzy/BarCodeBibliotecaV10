<?php
// Test direct pentru a vedea ce returnează Aleph
$barcode = 'C184507';
$ALEPH_SERVER = "YOUR-IP-or-http://WEBSITE";
$ALEPH_PORT = "8991";
$ALEPH_BASE_URL = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}/F";

echo "<h1>Test direct Aleph pentru: {$barcode}</h1>";

// PASUL 1: Inițializare sesiune
$init_url = "{$ALEPH_BASE_URL}?func=file&file_name=find-b";
$session_response = @file_get_contents($init_url);

preg_match('/\/F\/([A-Z0-9\-]+)\?/', $session_response, $matches);
$session_id = $matches[1] ?? '';

if (empty($session_id)) {
    preg_match('/\/F\/([A-Z0-9\-]+)/', $session_response, $matches);
    $session_id = $matches[1] ?? '';
}

echo "<p><strong>Session ID:</strong> {$session_id}</p>";

// PASUL 2: Căutare cu BAR
$search_url = "{$ALEPH_BASE_URL}/{$session_id}?func=find-b&request=" . urlencode($barcode) . "&find_code=BAR&adjacent=N&local_base=RAI01";
echo "<p><strong>Search URL:</strong> <a href='{$search_url}' target='_blank'>{$search_url}</a></p>";

$search_response = @file_get_contents($search_url);
file_put_contents('test_direct_response.html', $search_response);

echo "<p><strong>Response length:</strong> " . strlen($search_response) . " bytes</p>";

// Verifică ce conține
echo "<h2>Verificări:</h2>";
echo "<ul>";
echo "<li>Conține 'Format': " . (stripos($search_response, 'Format') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'Autor': " . (stripos($search_response, 'Autor') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'Titlu': " . (stripos($search_response, 'Titlu') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'Filială/Exemplare': " . (stripos($search_response, 'Filială/Exemplare') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'Biblioteca Academiei Iaşi': " . (stripos($search_response, 'Biblioteca Academiei Iaşi') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'func=item-global': " . (stripos($search_response, 'func=item-global') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'sub_library=ACAD': " . (stripos($search_response, 'sub_library=ACAD') !== false ? 'DA' : 'NU') . "</li>";
echo "<li>Conține 'doc_number': " . (preg_match('/doc_number=\d+/i', $search_response) ? 'DA' : 'NU') . "</li>";
echo "</ul>";

// Caută linkuri
echo "<h2>Linkuri găsite:</h2>";
preg_match_all('/<a[^>]*href=["\']?([^"\'>]*)["\'][^>]*>.*?Biblioteca Academiei Ia[şs]i/is', $search_response, $links);
if (!empty($links[1])) {
    echo "<ul>";
    foreach ($links[1] as $i => $link) {
        echo "<li>Link " . ($i+1) . ": " . htmlspecialchars($link) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Nu s-au găsit linkuri cu 'Biblioteca Academiei Iaşi'</p>";
}

// Caută func=item-global
preg_match_all('/<a[^>]*href=["\']?([^"\'>]*func=item-global[^"\'>]*)["\']?/i', $search_response, $item_links);
if (!empty($item_links[1])) {
    echo "<h3>Linkuri func=item-global:</h3><ul>";
    foreach ($item_links[1] as $i => $link) {
        echo "<li>Link " . ($i+1) . ": " . htmlspecialchars($link) . "</li>";
    }
    echo "</ul>";
}

echo "<p><strong>Răspuns salvat în:</strong> test_direct_response.html</p>";
?>




