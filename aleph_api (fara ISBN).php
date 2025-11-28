<?php
session_start();
require_once 'config.php';
require_once 'auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$ALEPH_SERVER = "YOUR-IP-or-http://WEBSITE";
$ALEPH_PORT = "8991";
$ALEPH_BASE_URL = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}/F";

// Primește cota din request
$cota = $_GET['cota'] ?? '';

if (empty($cota)) {
    echo json_encode([
        'success' => false,
        'mesaj' => 'Lipsește parametrul cota'
    ]);
    exit;
}

try {
    // 1. Inițializare sesiune
    $init_url = "{$ALEPH_BASE_URL}?func=file&file_name=find-b";
    $session_response = @file_get_contents($init_url);
    
    if ($session_response === false) {
        throw new Exception("Nu se poate conecta la serverul Aleph");
    }
    
    preg_match('/\/F\/([A-Z0-9\-]+)\?/', $session_response, $matches);
    $session_id = $matches[1] ?? '';
    
    if (empty($session_id)) {
        throw new Exception("Nu s-a putut inițializa sesiunea");
    }
    
    // 2. Căutare după cotă
    $search_url = "{$ALEPH_BASE_URL}/{$session_id}?func=find-b&request=" . urlencode($cota) . "&find_code=LOC&adjacent=N&local_base=RAI01";
    $search_response = @file_get_contents($search_url);
    
    if ($search_response === false) {
        throw new Exception("Eroare la căutare");
    }
    
    // Verifică dacă a fost găsită cartea
    if (stripos($search_response, 'Your search found no results') !== false ||
        stripos($search_response, 'Căutarea nu a avut rezultate') !== false) {
        echo json_encode([
            'success' => false,
            'mesaj' => 'Cartea nu a fost găsită în sistem'
        ]);
        exit;
    }
    
    // 3. Extrage link către detalii
    preg_match_all('/<A\s+HREF=([^>]*func=item-global[^>]*)>/i', $search_response, $all_links);
    
    $detail_url = '';
    if (!empty($all_links[1])) {
        // Preferă link-ul pentru ACAD
        foreach ($all_links[1] as $link) {
            if (strpos($link, 'sub_library=ACAD') !== false) {
                $detail_url = trim($link);
                break;
            }
        }
        
        if (empty($detail_url)) {
            $detail_url = trim($all_links[1][0]);
        }
    }
    
    if (empty($detail_url)) {
        throw new Exception("Nu s-a găsit link către detalii");
    }
    
    // 4. Fetch pagina de detalii
    $detail_html = @file_get_contents($detail_url);
    
    if ($detail_html === false) {
        throw new Exception("Nu s-au putut încărca detaliile cărții");
    }
    
    // 5. EXTRAGERE DATE din formatul specific Aleph
    $data = [
        'titlu' => '',
        'autor' => '',
        'autor_complet' => '',
        'isbn' => '',
        'anul' => '',
        'cota' => $cota,
        'colectie' => '',
        'biblioteca' => '',
        'status' => '',
        'barcode' => ''
    ];
    
    // Extrage: "Author Nume, Prenume. Titlu / Autor Complet"
    if (preg_match('/Author\s+([^.]+)\.\s+([^\/]+)\s*\/\s*(.+?)(?:<br>|$)/is', $detail_html, $matches)) {
        $data['autor'] = trim($matches[1]);
        $data['titlu'] = trim($matches[2]);
        $data['autor_complet'] = trim(strip_tags($matches[3]));
    }
    
    // ISBN
    if (preg_match('/ISBN[:\s]*([0-9\-Xx]+)/i', $detail_html, $matches)) {
        $data['isbn'] = trim($matches[1]);
    }
    
    // An publicare
    if (preg_match('/\b(19|20)\d{2}\b/', $detail_html, $matches)) {
        $data['anul'] = $matches[0];
    }
    
    // Parse tabel pentru date suplimentare
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($detail_html, 'HTML-ENTITIES', 'UTF-8'));
    $tds = $dom->getElementsByTagName('td');
    
    for ($i = 0; $i < $tds->length; $i++) {
        $td = $tds->item($i);
        $text = trim($td->textContent);
        
        // Colecție
        if (empty($data['colectie']) && 
            (stripos($text, 'depozit') !== false || stripos($text, 'Cărți') !== false)) {
            if (!stripos($text, 'Bibliotec') && strlen($text) < 50) {
                $data['colectie'] = $text;
            }
        }
        
        // Bibliotecă (evită duplicatele cu "Toate")
        if (empty($data['biblioteca']) && 
            stripos($text, 'Biblioteca Academiei') !== false &&
            stripos($text, 'Toate') === false) {
            $data['biblioteca'] = $text;
        }
        
        // Status (Pe raft / Pentru împrumut / etc.)
        if (stripos($text, 'Pe raft') !== false || 
            stripos($text, 'Pentru împrumut') !== false ||
            stripos($text, 'Împrumutat') !== false) {
            $data['status'] = $text;
        }
        
        // Barcode (format: XXXXX-XX)
        if (preg_match('/^\d{5}-\d{2}$/', $text)) {
            $data['barcode'] = $text;
        }
    }
    
    // Verifică dacă s-au extras date minime
    if (empty($data['titlu'])) {
        throw new Exception("Nu s-au putut extrage datele cărții");
    }
    
    // Returnează SUCCESS
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mesaj' => $e->getMessage()
    ]);
}
?>
