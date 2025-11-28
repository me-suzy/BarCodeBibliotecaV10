<?php
// aleph_api.php - API pentru integrare Aleph cu fallback automat

// ✅ NOU - Verifică dacă sesiunea este deja activă înainte de a o porni
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'auth_check.php';

$ALEPH_SERVER = "YOUR-IP-or-http://WEBSITE";
$ALEPH_PORT = "8991";
$ALEPH_BASE_URL = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}/F";

/**
 * Helper function pentru fetch URL cu timeout (fără conversie automată)
 * ACTUALIZAT: Folosește curl cu USERAGENT ca în test.php
 */
function fetch_url($url, $timeout = 60) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($result === false || $http_code !== 200) {
            throw new Exception("Nu se poate accesa: " . $url . ($error ? " - " . $error : ""));
        }
        
        return $result; // Returnează RAW - fără conversie
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'follow_location' => 1,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new Exception("Nu se poate accesa: " . $url);
        }
        
        return $result; // Returnează RAW - fără conversie
    }
}

/**
 * Convertește encoding de la Aleph la UTF-8 (versiune îmbunătățită)
 * IMPORTANT: Verifică ÎNTÂI dacă textul e deja UTF-8 - dacă DA, nu face conversie!
 */
function convertAlephEncoding($text) {
    if (empty($text)) return $text;
    
    // 🔥 IMPORTANT: Verifică dacă e deja UTF-8 valid
    if (mb_check_encoding($text, 'UTF-8')) {
        // Deja UTF-8 valid - NU converti nimic!
        return $text;
    }
    
    // Dacă nu e UTF-8, încearcă conversie din ISO-8859-2
    $converted = @iconv('ISO-8859-2', 'UTF-8//TRANSLIT//IGNORE', $text);
    
    if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
        return $converted;
    }
    
    // Fallback: returnează originalul
    return $text;
}

/**
 * Funcție principală de căutare în Aleph - ABORDARE NOUĂ FĂRĂ PATTERN-URI REGEX
 * 
 * Această funcție folosește formularul de căutare Aleph direct, fără a încerca
 * să determine tipul de căutare prin pattern-uri regex. Încearcă ambele tipuri
 * de căutare (WLB pentru cote, BAR pentru barcode) și extrage datele direct
 * din structura HTML a paginii item-global.
 */
function cautaCarteInAleph($search_term, $search_type = 'AUTO') {
    global $ALEPH_BASE_URL, $ALEPH_SERVER, $ALEPH_PORT;
    
    $debug_info = [];
    
    try {
        // ====================================================================
        // PASUL 1: Inițializare sesiune Aleph
        // ====================================================================
        $init_url = "{$ALEPH_BASE_URL}?func=file&file_name=find-b";
        $debug_info['init_url'] = $init_url;
        
        $session_response = fetch_url($init_url);
        @file_put_contents('debug_session_raw.html', $session_response);
        
        // Extrage session ID
        preg_match('/\/F\/([A-Z0-9\-]+)\?/', $session_response, $matches);
        $session_id = $matches[1] ?? '';
        
        if (empty($session_id)) {
            preg_match('/\/F\/([A-Z0-9\-]+)/', $session_response, $matches);
            $session_id = $matches[1] ?? '';
        }
        
        if (empty($session_id)) {
            throw new Exception("Nu s-a putut extrage session ID.");
        }
        
        $debug_info['session_id'] = $session_id;
        
        // ====================================================================
        // PASUL 2: Căutare în Aleph - încearcă WLB (cota) și BAR (barcode)
        // ====================================================================
        // Nu mai folosim pattern-uri regex pentru a determina tipul. Încearcă
        // ambele tipuri de căutare și folosește primul care returnează rezultate.
        $search_strategies = [];
        
        if ($search_type === 'AUTO') {
            // Detectare inteligentă a tipului de cod
            // COTA (WLB): conține spații, litere și cifre romane (ex: "SL DI II-0994", "III-32073")
            // BARCODE (BAR): doar cifre sau litere+cifre fără spații (ex: "C182834", "000003310-10")
            
            $is_likely_cota = (
                // Conține spații
                strpos($search_term, ' ') !== false ||
                // Conține cifre romane (I, V, X) urmate de liniuță și cifre
                preg_match('/[IVX]+[-\/]\d+/i', $search_term) ||
                // Pattern de cota: litere, cifre, liniuțe (ex: SL DI II-0994)
                preg_match('/^[A-Z]{1,3}\s+[A-Z]+\s+/i', $search_term) ||
                // Conține "/" (ex: SL Irimia/0681)
                strpos($search_term, '/') !== false
            );
            
            $is_likely_barcode = (
                // Doar cifre
                preg_match('/^\d+$/', $search_term) ||
                // Doar cifre cu liniuță (ex: 000003310-10)
                preg_match('/^\d+[-]\d+$/', $search_term) ||
                // Literă + cifre fără spații (ex: C182834)
                preg_match('/^[A-Z]\d+$/i', $search_term)
            );
            
            $debug_info['is_likely_cota'] = $is_likely_cota;
            $debug_info['is_likely_barcode'] = $is_likely_barcode;
            
            if ($is_likely_cota) {
                // Pentru cotă, încearcă WLB primul
                $search_strategies = ['WLB', 'BAR'];
            } else {
                // Pentru barcode sau nedeterminat, încearcă BAR primul
                $search_strategies = ['BAR', 'WLB'];
            }
        } elseif ($search_type === 'BAR') {
            $search_strategies = ['BAR'];
        } elseif ($search_type === 'LOC' || $search_type === 'WLB') {
            $search_strategies = ['WLB'];
        } else {
            $search_strategies = [$search_type];
        }
        
        $search_response = null;
        $used_strategy = null;
        
        foreach ($search_strategies as $strategy) {
            $search_url = "{$ALEPH_BASE_URL}/{$session_id}?func=find-b&request=" . urlencode($search_term) . "&find_code={$strategy}&adjacent=N&local_base=RAI01";
            $debug_info["search_url_{$strategy}"] = $search_url;
            
            $temp_response = fetch_url($search_url);
            $debug_info["search_response_{$strategy}_length"] = strlen($temp_response);
            
            // Verifică dacă sunt rezultate
            // IMPORTANT: Verifică mai multe variante de mesaje "no results"
            $no_results = (
                stripos($temp_response, 'Your search found no results') !== false ||
                stripos($temp_response, 'Căutarea nu a avut rezultate') !== false ||
                stripos($temp_response, 'nu a avut rezultate') !== false ||
                stripos($temp_response, 'No results') !== false ||
                stripos($temp_response, 'Niciun rezultat') !== false ||
                stripos($temp_response, 'niciun rezultat') !== false ||
                (stripos($temp_response, 'Format') !== false && stripos($temp_response, 'Autor') !== false && stripos($temp_response, 'Titlu') !== false && stripos($temp_response, 'Filială/Exemplare') === false) // Pagină de rezultate goală
            );
            
            // Verifică dacă există rezultate REALE (tabel cu Format, Autor, Titlu, Filială/Exemplare)
            // Nu acceptă doar liste de indexuri (scan-list)
            $has_real_results = (
                // Verifică dacă există tabelul de rezultate cu coloanele standard
                (stripos($temp_response, 'Format') !== false && 
                 stripos($temp_response, 'Autor') !== false && 
                 stripos($temp_response, 'Titlu') !== false && 
                 stripos($temp_response, 'Filială/Exemplare') !== false) ||
                // SAU linkuri către item-global sau "Biblioteca Academiei Iaşi"
                stripos($temp_response, 'func=item-global') !== false ||
                stripos($temp_response, 'Biblioteca Academiei Iaşi') !== false ||
                stripos($temp_response, 'Biblioteca Academiei Iasi') !== false ||
                stripos($temp_response, 'sub_library=ACAD') !== false ||
                preg_match('/doc_number=\d+/i', $temp_response) ||
                preg_match('/set_entry=\d+/i', $temp_response)
            );
            
            // Verifică dacă este doar o listă de indexuri (scan-list)
            // IMPORTANT: Pentru cote (WLB), scan-list-urile sunt acceptabile dacă conțin linkuri către item-global
            $is_scan_list = (
                stripos($temp_response, 'Nr. de înregistrări') !== false &&
                stripos($temp_response, 'Intrare') !== false &&
                stripos($temp_response, 'func=find-word') !== false &&
                stripos($temp_response, 'scan_code=') !== false
            );
            
            // Pentru WLB (cote), acceptă și scan-list-uri dacă conțin linkuri către item-global sau Biblioteca Academiei Iași
            $has_useful_links = (
                stripos($temp_response, 'func=item-global') !== false ||
                stripos($temp_response, 'Biblioteca Academiei Iaşi') !== false ||
                stripos($temp_response, 'Biblioteca Academiei Iasi') !== false ||
                stripos($temp_response, 'sub_library=ACAD') !== false ||
                preg_match('/doc_number=\d+/i', $temp_response) ||
                preg_match('/set_entry=\d+/i', $temp_response) ||
                preg_match('/set_number=\d+/i', $temp_response) ||
                preg_match('/rec_number=\d+/i', $temp_response) // Pentru scan-list-uri
            );
            
            // IMPORTANT: Pentru cote (WLB), dacă nu găsește rezultate directe, acceptă orice răspuns care nu este "no results"
            // deoarece scan-list-urile pot conține linkuri către item-global care vor fi procesate mai târziu
            $accept_for_wlb = ($strategy === 'WLB' && !$no_results);
            
            // Acceptă dacă:
            // 1. Are rezultate reale (tabel cu Format, Autor, Titlu) ȘI nu este scan-list
            // SAU
            // 2. Este scan-list dar conține linkuri utile (pentru cote)
            // SAU
            // 3. Este WLB și nu este "no results" (acceptă orice răspuns pentru procesare ulterioară)
            if (($has_real_results && !$is_scan_list) || 
                ($is_scan_list && $has_useful_links && $strategy === 'WLB') ||
                ($accept_for_wlb && !$is_scan_list)) {
                $search_response = $temp_response;
                $used_strategy = $strategy;
                $debug_info['used_strategy'] = $strategy;
                $debug_info['is_scan_list'] = $is_scan_list;
                $debug_info['has_useful_links'] = $has_useful_links;
                $debug_info['accept_for_wlb'] = $accept_for_wlb;
                @file_put_contents('debug_aleph_response.html', $search_response);
                break;
            }
        }
        
        if ($search_response === null) {
            return [
                'success' => false,
                'mesaj' => "Nu s-au găsit rezultate pentru: {$search_term} (încercat: " . implode(', ', $search_strategies) . ")",
                'debug' => $debug_info
            ];
        }

        // ====================================================================
        // PASUL 3: Găsire link către "Biblioteca Academiei Iași" (sub_library=ACAD)
        // ====================================================================
        // Caută în răspunsul de căutare link-ul către "Biblioteca Academiei Iaşi"
        // care conține sub_library=ACAD. Acesta este linkul către pagina item-global
        // cu toate detaliile cărții.
        $item_url = '';
        $items_page_html = null; // HTML din pagina intermediară cu exemplare ACAD

        // Decodifică entitățile HTML pentru a găsi link-urile corect (ex: &amp; devine &)
        $search_response_decoded = html_entity_decode($search_response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // ====================================================================
        // Metoda 1: Link direct către func=item-global cu sub_library=ACAD
        // ====================================================================
        // Caută link DIRECT care conține AMBELE: func=item-global ȘI sub_library=ACAD
        // Acest pattern trebuie să găsească AMBELE în același href
        // EXACT același pattern ca în test.php (linia 74)

        if (preg_match('/<a[^>]+href=["\']([^"\']+func=item-global[^"\']+sub_library=ACAD[^"\']*)["\'][^>]*>/i', $search_response_decoded, $match)) {
            $href = str_replace('&amp;', '&', $match[1]);

            // Construiește URL complet
            if (strpos($href, 'http') === 0) {
                $item_url = $href;
            } elseif (strpos($href, '/F/') === 0) {
                $item_url = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}" . $href;
            } else {
                $item_url = "{$ALEPH_BASE_URL}/{$session_id}?" . ltrim($href, '?');
            }

            $debug_info['found_via'] = 'method_1_direct_acad_link';
            $debug_info['item_url_method_1'] = $item_url;
        }
        
        // ====================================================================
        // Metoda 2: Link către "Biblioteca Academiei Iași(X/Y)" - LINK INTERMEDIAR
        // ====================================================================
        // Această metodă caută linkul către "Biblioteca Academiei Iași(X/Y)" care
        // duce la o pagină intermediară cu exemplarele ACAD. Apoi accesează acea
        // pagină intermediară și folosește HTML-ul rezultat pentru extragere date.
        // Se execută DOAR dacă Metoda 1 nu a găsit link direct cu ACAD
        if (empty($item_url)) {
            $debug_info['method_2_started'] = true;

            // Salvează un sample din search_response pentru debugging
            @file_put_contents('debug_search_response_sample.html', substr($search_response_decoded, 0, 5000));

            // Pattern specific pentru "Biblioteca Academiei Iași(X/Y)" cu spații multiple
            // IMPORTANT: Aleph pune multe spații în paranteze: "(     2/     0)"
            // Pattern îmbunătățit care acceptă orice caractere speciale în Iași/Iaşi
            $biblioteca_match = null;
            
            // Pattern 2a: Exact "Biblioteca Academiei Iași/Iaşi(X/Y)" cu spații variabile
            if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>\s*Biblioteca\s+Academiei\s+Ia[șşs]i\s*\([^\)]+\)/isu', $search_response_decoded, $biblioteca_match)) {
                $debug_info['method_2a_matched'] = true;
            }
            // Pattern 2b: Orice link care conține "Biblioteca Academiei" și "func=item-global"
            elseif (preg_match('/<a[^>]+href=["\']([^"\']*func=item-global[^"\']*)["\'][^>]*>[^<]*Biblioteca[^<]*Academiei[^<]*/isu', $search_response_decoded, $biblioteca_match)) {
                $debug_info['method_2b_matched'] = true;
            }
            // Pattern 2c: Orice link cu "sub_library=ACAD" (biblioteca academiei)
            elseif (preg_match('/<a[^>]+href=["\']([^"\']*sub_library=ACAD[^"\']*)["\'][^>]*>/isu', $search_response_decoded, $biblioteca_match)) {
                $debug_info['method_2c_matched'] = true;
            }
            
            if ($biblioteca_match) {
                $href = str_replace('&amp;', '&', $biblioteca_match[1]);

                // Construiește URL-ul complet către pagina intermediară
                if (strpos($href, 'http') === 0) {
                    $intermediate_url = $href;
                } elseif (strpos($href, '/F/') === 0) {
                    $intermediate_url = "http://{$ALEPH_SERVER}:{$ALEPH_PORT}" . $href;
                } else {
                    $intermediate_url = "{$ALEPH_BASE_URL}/{$session_id}?" . ltrim($href, '?');
                }

                $debug_info['intermediate_url'] = $intermediate_url;
                $debug_info['found_via'] = 'biblioteca_academiei_link';

                // Accesează pagina intermediară cu exemplare ACAD
                try {
                    $items_page_html = fetch_url($intermediate_url, 60);
                    $debug_info['items_page_html_length'] = strlen($items_page_html);
                    @file_put_contents('debug_items_page_aleph_api.html', $items_page_html);

                    if (!empty($items_page_html)) {
                        // Convertește encoding-ul
                        $items_page_html = convertAlephEncoding($items_page_html);

                        // Această pagină devine sursa pentru extragere date
                        // O vom folosi în loc de search_response
                        $item_url = $intermediate_url; // Salvăm URL-ul pentru usage mai târziu

                        $debug_info['method_2_success'] = true;
                    } else {
                        $debug_info['method_2_empty_response'] = true;
                    }
                } catch (Exception $e) {
                    $debug_info['method_2_error'] = $e->getMessage();
                }
            } else {
                // Dacă Metoda 2 nu a găsit pattern-ul pentru Biblioteca Academiei,
                // încearcă să găsească doc_number direct din pagina de rezultate
                $debug_info['method_2_no_match'] = true;
                
                // Încearcă să extragă doc_number din linkurile din pagina de rezultate
                // Acest lucru funcționează când căutăm după cotă și avem o listă de rezultate
                if (preg_match('/doc_number=(\d+)/i', $search_response_decoded, $doc_match)) {
                    $doc_num = $doc_match[1];
                    // Construiește URL-ul direct către item-global cu ACAD
                    $item_url = "{$ALEPH_BASE_URL}/{$session_id}?func=item-global&doc_library=RAI01&doc_number={$doc_num}&year=&volume=&sub_library=ACAD";
                    $debug_info['method_2_fallback_doc_number'] = $doc_num;
                    $debug_info['found_via'] = 'method_2_fallback_doc_number';
                }
            }
        }

        // ====================================================================
        // Metoda 3: doc_number cu ACAD (fallback)
        // ====================================================================
        // Dacă Metoda 1 și 2 nu au găsit link, caută doc_number în HTML
        // Exact ca în test.php (liniile 145-160)
        if (empty($item_url)) {
            $debug_info['method_3_started'] = true;

            // Caută în $items_page_html dacă există (din Metoda 2), altfel în $search_response
            $search_html = !empty($items_page_html) ? $items_page_html : $search_response;

            preg_match_all('/doc_number=(\d+)/i', $search_html, $doc_matches);
            $unique_docs = array_unique($doc_matches[1]);

            $debug_info['doc_numbers_found'] = count($unique_docs);

            if (count($unique_docs) > 0) {
                // Folosește primul doc_number găsit
                $doc_num = $unique_docs[0];
                $item_url = "{$ALEPH_BASE_URL}/{$session_id}?func=item-global&doc_library=RAI01&doc_number={$doc_num}&sub_library=ACAD";
                $debug_info['found_via'] = 'method_3_doc_number';
                $debug_info['doc_number'] = $doc_num;
            }
        }

        // Verificare finală - dacă nu s-a găsit niciun URL
        $debug_info['final_item_url'] = $item_url ?? 'EMPTY';
        
        if (empty($item_url)) {
            return [
                'success' => false,
                'mesaj' => "Nu s-a găsit link către Biblioteca Academiei Iași în rezultatele căutării",
                'debug' => $debug_info,
                'search_response_sample' => substr($search_response, 0, 2000)
            ];
        }
        
        // ====================================================================
        // Inițializare date
        // ====================================================================
        $data = [
            'titlu' => '',
            'autor' => '',
            'autor_complet' => '',
            'isbn' => '',
            'anul' => '',
            'editura' => '',
            'localitate' => '',
            'cota' => '',
            'locatie' => '',
            'colectie' => '',
            'biblioteca' => '',
            'status' => '',
            'barcode' => '',
            'sectiune' => ''
        ];
        
        // ====================================================================
        // PASUL 4: Extragere date din pagina item-global
        // ====================================================================
        // Accesează pagina item-global și extrage datele direct din structura
        // HTML standard (TD-uri cu clasa td1), fără pattern-uri regex pentru
        // cote și barcode-uri. Această abordare funcționează pentru orice format.

        // Dacă avem HTML din pagina intermediară (Metoda 2), folosește-l
        // Altfel, face fetch la item_url
        if (!empty($items_page_html)) {
            $item_html = $items_page_html;
            $debug_info['used_items_page_html'] = true;
        } else {
            $item_html = fetch_url($item_url);
            $debug_info['fetched_item_url'] = true;
        }

        // DEBUGGING
        @file_put_contents('debug_aleph_raw.html', $item_html);
        $item_html = convertAlephEncoding($item_html);
        @file_put_contents('debug_aleph_converted.html', $item_html);
            
        // ====================================================================
        // Extragere date din structura HTML standard (TD-uri cu clasa td1)
        // ====================================================================
        // Această metodă extrage datele direct din structura HTML a paginii
        // item-global, folosind EXACT aceeași logică ca în test.php (liniile 199-227)
        
        // METODA 1: Extrage autor și titlu (EXACT ca în test.php)
        // Pattern din test.php: /<td[^>]*class=["\']?td1["\']?[^>]*>\s*Author\s+(.+?)<br>/is
        if (preg_match('/<td[^>]*class=["\']?td1["\']?[^>]*>\s*Author\s+(.+?)<br>/is', $item_html, $m)) {
            $autor_titlu = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
            
            if (preg_match('/^(.+?)\s*\/\s*(.+)$/s', $autor_titlu, $parts)) {
                $partea1 = trim($parts[1]);
                $partea2 = trim($parts[2]);
                
                if (preg_match('/^(.+?)\.\s+([A-Z].+)$/s', $partea1, $split)) {
                    $data['autor'] = trim($split[1]);
                    $data['titlu'] = trim($split[2]);
                } else {
                    $data['autor'] = $partea2;
                    $data['titlu'] = $partea1;
                }
            } else {
                $data['titlu'] = $autor_titlu;
            }
            
            if (strpos($data['titlu'], ';') !== false) {
                $data['titlu'] = trim(explode(';', $data['titlu'])[0]);
            }
            
            $debug_info['extracted_via_test_php_method'] = true;
            $debug_info['autor_extracted'] = substr($data['autor'], 0, 50);
            $debug_info['titlu_extracted'] = substr($data['titlu'], 0, 70);
        }
        
        // Fallback: Extrage titlu/autor din primul TD cu clasa td1 (format standard Aleph)
        if (empty($data['titlu'])) {
                // Pattern 1: Author. Title : ... / ... (format standard Aleph din item-global)
                // Exemplu: "Author Uritescu, Dorin N.. Fascinaţia numelui : Studiu al creaţiei lexico-semantice şi stilistice, în relaţiile: -nume propriu-nume comun şi nume comun-nume propriu- / Dorin N. Uritescu"
                if (preg_match('/Author\s+([^\.]+)\.\.?\s+([^:]+):\s*([^\/]+)\s*\/\s*(.+?)(?:<br>|<\/|$)/is', $item_html_clean, $matches)) {
                    $autor = trim(strip_tags($matches[1]));
                    $titlu_part1 = trim(strip_tags($matches[2]));
                    $titlu_part2 = trim(strip_tags($matches[3]));
                    $titlu = $titlu_part1 . ' : ' . $titlu_part2;
                    
                    // Verifică că nu este text de navigare sau text generic
                    if (!stripos($titlu, 'Catalog general') && !stripos($titlu, 'Colecţii') && 
                        !stripos($titlu, 'exemplare') && !stripos($titlu, 'Selectaţi') &&
                        !stripos($titlu, 'BARI') && !stripos($titlu, 'catalog') &&
                        !stripos($titlu, 'Permis de bibliotecă') && !stripos($titlu, 'Permis de biblioteca') &&
                        !stripos($titlu, 'Înregistrările selectate') && !stripos($titlu, 'Inregistrarile selectate') &&
                        strlen($titlu) > 10) {
                        if (empty($data['autor'])) {
                            $data['autor'] = $autor;
                        }
                        $data['titlu'] = $titlu;
                        $data['autor_complet'] = trim(strip_tags($matches[4]));
                    }
                }
                // Pattern 1b: Author. Title / ... (format standard Aleph fără două puncte)
                elseif (preg_match('/Author\s+([^.]+)\.\s+([^\/]+)\s*\/\s*(.+?)(?:<br>|<\/|$)/is', $item_html_clean, $matches)) {
                    $autor = trim(strip_tags($matches[1]));
                    $titlu = trim(strip_tags($matches[2]));
                    // Verifică că nu este text de navigare sau text generic
                    if (!stripos($titlu, 'Catalog general') && !stripos($titlu, 'Colecţii') && 
                        !stripos($titlu, 'exemplare') && !stripos($titlu, 'Selectaţi') &&
                        !stripos($titlu, 'BARI') && !stripos($titlu, 'catalog') &&
                        !stripos($titlu, 'Permis de bibliotecă') && !stripos($titlu, 'Permis de biblioteca') &&
                        !stripos($titlu, 'Înregistrările selectate') && !stripos($titlu, 'Inregistrarile selectate') &&
                        strlen($titlu) > 10) {
                        if (empty($data['autor'])) {
                            $data['autor'] = $autor;
                        }
                        $data['titlu'] = $titlu;
                        $data['autor_complet'] = trim(strip_tags($matches[3]));
                    }
                }
                // Pattern 2: Format alternativ - Title / Author (doar dacă nu este text generic)
                elseif (preg_match('/([^\/]{20,})\s*\/\s*([^<]{5,})/i', $item_html_clean, $matches)) {
                    $potential_title = trim(strip_tags($matches[1]));
                    $potential_author = trim(strip_tags($matches[2]));
                    // Verifică că nu este text de navigare sau text generic (BARI, catalog, etc.)
                    if (strlen($potential_title) > 10 && !stripos($potential_title, 'Catalog') && 
                        !stripos($potential_title, 'Colecţii') && !stripos($potential_title, 'Selectaţi') &&
                        !stripos($potential_title, 'BARI') && !stripos($potential_title, 'catalog general') &&
                        !stripos($potential_title, 'Permis de bibliotecă') && !stripos($potential_title, 'Permis de biblioteca') &&
                        !stripos($potential_title, 'Înregistrările selectate') && !stripos($potential_title, 'Inregistrarile selectate')) {
                        $data['titlu'] = $potential_title;
                        if (strlen($potential_author) > 2 && !stripos($potential_author, 'Selectaţi')) {
                            $data['autor'] = $potential_author;
                        }
                    }
                }
                // Pattern 3: În tag-uri <pre> sau <div> cu text lung (format Aleph)
                elseif (preg_match('/<(?:pre|div)[^>]*>([^<]{30,500})<\/(?:pre|div)>/is', $item_html_clean, $matches)) {
                    $text_block = trim(strip_tags($matches[1]));
                    // Caută pattern Author. Title
                    if (preg_match('/([^\.]+)\.\s+([^\/]+)\s*\/\s*(.+)/', $text_block, $text_matches)) {
                        $autor = trim($text_matches[1]);
                        $titlu = trim($text_matches[2]);
                        // Verifică că nu este text de navigare sau text generic
                        if (strlen($titlu) > 10 && !stripos($titlu, 'Catalog') && !stripos($titlu, 'Selectaţi') &&
                            !stripos($titlu, 'BARI') && !stripos($titlu, 'catalog general') &&
                            !stripos($titlu, 'Permis de bibliotecă') && !stripos($titlu, 'Permis de biblioteca') &&
                            !stripos($titlu, 'Înregistrările selectate') && !stripos($titlu, 'Inregistrarile selectate')) {
                            $data['autor'] = $autor;
                            $data['titlu'] = $titlu;
                            $data['autor_complet'] = trim($text_matches[3]);
                        }
                    }
                }
            }
            
            // 🔥 METODA 2: Parsing DOM îmbunătățit
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($item_html, 'HTML-ENTITIES', 'UTF-8'));
            $tds = $dom->getElementsByTagName('td');
            
            // Parsing perechi label-value (verifică și fără class td1)
            for ($i = 0; $i < $tds->length - 1; $i++) {
                $current_td = $tds->item($i);
                $next_td = $tds->item($i + 1);
                $label = trim($current_td->textContent);
                $value = trim($next_td->textContent);
                
                // Extrage date din perechi label-value
                if (stripos($label, 'Title') !== false || stripos($label, 'Titlu') !== false) {
                    if (empty($data['titlu']) && !empty($value)) {
                        // Verifică că nu este text de navigare
                        $clean_value = trim($value);
                        // Verifică că nu este text de navigare sau text generic
                        if (!stripos($clean_value, 'Catalog general') && !stripos($clean_value, 'Colecţii') && 
                            !stripos($clean_value, 'exemplare') && !stripos($clean_value, 'Selectaţi') &&
                            !stripos($clean_value, 'BARI') && !stripos($clean_value, 'catalog general') &&
                            !stripos($clean_value, 'Permis de bibliotecă') && !stripos($clean_value, 'Permis de biblioteca') &&
                            !stripos($clean_value, 'Înregistrările selectate') && !stripos($clean_value, 'Inregistrarile selectate') &&
                            !stripos($clean_value, '>') && strlen($clean_value) > 10) {
                            $data['titlu'] = $clean_value;
                        }
                    }
                }
                if (stripos($label, 'Author') !== false || stripos($label, 'Autor') !== false) {
                    if (empty($data['autor']) && !empty($value)) {
                        $clean_value = trim($value);
                        if (!stripos($clean_value, 'Selectaţi') && strlen($clean_value) > 2) {
                            $data['autor'] = $clean_value;
                        }
                    }
                }
            }
            
            // Parsing alternativ: caută în toate TD-urile pentru text lung (posibil titlu)
            if (empty($data['titlu'])) {
                for ($i = 0; $i < $tds->length; $i++) {
                    $td = $tds->item($i);
                    $text = trim($td->textContent);
                    
                    // ✅ NOU - Exclude explicit textul din navigare
                    $text_exclus = [
                        'Sfârşitul sesiunii', 'Sfârșitul sesiunii', 'End of session',
                        'Selectați anul', 'Selectaţi anul', 'Select the year',
                        'Conectaţi-vă', 'Log in', 'Sesiune expirată',
                        'Permis de bibliotecă', 'Permis de biblioteca', 'Library permit',
                        'Biblioteca Academiei Iaşi', 'Biblioteca Academiei Iasi',
                        'Înregistrările selectate', 'Inregistrarile selectate', 'Selected records'
                    ];
                    
                    $is_text_navigare = false;
                    foreach ($text_exclus as $exclus) {
                        if (stripos($text, $exclus) !== false) {
                            $is_text_navigare = true;
                            break;
                        }
                    }
                    
                    if ($is_text_navigare) {
                        continue;
                    }
                    
                    // Caută text lung care ar putea fi titlu (20-500 caractere, fără text de navigare)
                    if (strlen($text) >= 20 && strlen($text) <= 500 && 
                        !stripos($text, 'Catalog') && !stripos($text, 'Colecţii') && 
                        !stripos($text, 'Selectaţi') && !stripos($text, 'exemplare') &&
                        !stripos($text, 'BARI') && !stripos($text, 'catalog general') &&
                        !stripos($text, 'Permis de bibliotecă') && !stripos($text, 'Permis de biblioteca') &&
                        !stripos($text, 'Înregistrările selectate') && !stripos($text, 'Inregistrarile selectate') &&
                        !preg_match('/^[A-Z]{1,3}[\s\-]?\d+([\s\-]\d+)?$/i', $text) && // nu este cota (I-14156)
                        !preg_match('/^[A-Z]{2,3}\s+[A-Za-z]+\/\d+$/i', $text) && // nu este cota (SL Irimia/1146)
                        !preg_match('/^([A-Z]\d{5,}|[A-Z]{2,3}\d{4,}|\d{5,})(-\d{1,2})?$/i', $text)) { // nu este barcode
                        // Verifică dacă conține pattern de titlu (mai multe cuvinte)
                        if (preg_match('/\b\w+\b.*\b\w+\b.*\b\w+\b/', $text)) {
                            $data['titlu'] = $text;
                            break;
                        }
                    }
                }
            }
            
            // ✅ NOU - Caută titlul în tabelul de rezultate (format Aleph standard)
            if (empty($data['titlu'])) {
                // Caută pattern: "Author. Title / ..." în formatul tabelului Aleph
                if (preg_match('/<td[^>]*>([^<]+(?:ed\.|trad\.|,)[^<]*)\.\s+([^<]+(?:"[^"]+"|Corespondenţa|Corespondenta)[^<]*)<\/td>/is', $item_html, $matches)) {
                    $autor_potential = trim(strip_tags($matches[1]));
                    $titlu_potential = trim(strip_tags($matches[2]));
                    
                    // Verifică că nu este text de navigare
                    if (strlen($titlu_potential) > 15 && 
                        !stripos($titlu_potential, 'Sfârşitul') && 
                        !stripos($titlu_potential, 'Selectați') &&
                        !stripos($titlu_potential, 'Catalog')) {
                        $data['titlu'] = $titlu_potential;
                        if (strlen($autor_potential) > 2) {
                            $data['autor'] = $autor_potential;
                        }
                    }
                }
            }

        // ====================================================================
        // METODA 2A: Extragere cota și barcode din TOATE rândurile cu td1
        // ====================================================================
        // Această metodă parcurge TOATE rândurile cu class=td1 din pagina
        // intermediară (ca în test.php) și extrage date din fiecare celulă
        // folosind pattern-uri regex. Această abordare găsește mai multe
        // exemplare decât metoda DOM parsing.

        // Extragem toate rândurile <tr>
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $item_html, $all_rows);
        $debug_info['total_rows_found'] = count($all_rows[1]);

        // Filtrăm doar rândurile care conțin td1
        $rows_with_td1 = [];
        foreach ($all_rows[1] as $row) {
            if (stripos($row, 'class=td1') !== false || stripos($row, 'class="td1"') !== false) {
                $rows_with_td1[] = $row;
            }
        }
        $debug_info['rows_with_td1'] = count($rows_with_td1);

        // Parcurgem fiecare rând cu td1
        foreach ($rows_with_td1 as $row_html) {
            // Extragem toate celulele td cu clasa td1
            preg_match_all('/<td[^>]*class=["\']?td1["\']?[^>]*>(.*?)<\/td>/is', $row_html, $cells);

            // Dacă rândul are mai puțin de 3 celule, sărim peste (nu e rând de date)
            if (empty($cells[1]) || count($cells[1]) < 3) {
                continue;
            }

            // Parcurgem fiecare celulă și detectăm tipul de date
            foreach ($cells[1] as $cell) {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags($cell)));

                if (empty($text) || strlen($text) < 2) continue;

                // Detectare Status (Se împr., Pe raft, etc.)
                if (empty($data['status']) && preg_match('/(Se împr\.|Pe raft|Pentru împrumut|Împrumutat|Doar pentru)/i', $text)) {
                    $data['status'] = $text;
                }
                // Detectare Cotă (ex: SBC/00004, I-14156, etc.)
                elseif (empty($data['cota']) && preg_match('/[A-Z]+.*?[\/\-]\d+/i', $text) && !preg_match('/^\d{4}\/\d{2}$/', $text) && strlen($text) < 50) {
                    $data['cota'] = $text;
                    $data['locatie'] = $text; // Salvăm și în locatie
                }
                // Detectare Barcode (ex: A123456, 12345-1, etc.)
                elseif (empty($data['barcode']) && (preg_match('/^[A-Z]{0,3}\d{4,}$/i', $text) || preg_match('/^\d+-\d+$/i', $text))) {
                    $data['barcode'] = $text;
                }
                // Detectare Bibliotecă
                elseif (empty($data['biblioteca']) && stripos($text, 'Biblioteca') !== false && stripos($text, 'Academiei') !== false) {
                    $data['biblioteca'] = $text;
                }
                // Detectare Colecție (Cărți, sala, depozit, etc.)
                elseif (empty($data['colectie']) && preg_match('/(Cărţi|Carte|sala|depozit|periodice)/i', $text) && strlen($text) > 5 && strlen($text) < 80) {
                    $data['colectie'] = $text;
                }
                // Detectare Localizare2 (format YYYY/MM)
                elseif (empty($data['sectiune']) && preg_match('/^\d{4}\/\d{2}$/', $text)) {
                    $data['sectiune'] = $text;
                }
            }

            // Dacă am găsit cotă SAU barcode, oprim căutarea
            if (!empty($data['cota']) || !empty($data['barcode'])) {
                $debug_info['found_via_method_2a'] = true;
                $debug_info['method_2a_cota'] = $data['cota'] ?? 'N/A';
                $debug_info['method_2a_barcode'] = $data['barcode'] ?? 'N/A';
                break;
            }
        }

        // ====================================================================
        // METODA 2B: Extragere cota și barcode din tabelul de exemplare (DOM parsing)
        // ====================================================================
        // Această metodă extrage cota și barcode-ul direct din tabelul de
        // exemplare, folosind DOM parsing pentru a găsi coloanele "COTĂ" și
        // "Barcod". Funcționează pentru orice format, fără pattern-uri regex.
        // Această metodă este un FALLBACK pentru cazurile în care Metoda 2A nu găsește date.

        if (empty($data['cota']) && empty($data['barcode'])) {
            $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($item_html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Caută tabelul de exemplare
        $tables = $xpath->query("//table[.//th[contains(., 'COTĂ')] or .//th[contains(., 'Barcod')]]");
        
        if ($tables->length > 0) {
            $table = $tables->item(0);
            $rows = $xpath->query(".//tr", $table);
            
            // Găsește header-ul pentru a identifica coloanele
            $header_row = null;
            $cota_col_index = -1;
            $barcode_col_index = -1;
            
            foreach ($rows as $row) {
                $th_cells = $xpath->query(".//th", $row);
                if ($th_cells->length > 0) {
                    $header_row = $row;
                    $col_index = 0;
                    foreach ($th_cells as $th) {
                        $th_text = trim($th->textContent);
                        if (stripos($th_text, 'COTĂ') !== false || stripos($th_text, 'Localizare') !== false) {
                            $cota_col_index = $col_index;
                        }
                        if (stripos($th_text, 'Barcod') !== false) {
                            $barcode_col_index = $col_index;
                        }
                        $col_index++;
                    }
                    break;
                }
            }
            
            // Extrage datele din primul rând de date (după header)
            if ($header_row && ($cota_col_index >= 0 || $barcode_col_index >= 0)) {
                foreach ($rows as $row) {
                    if ($row === $header_row) continue;
                    
                    $td_cells = $xpath->query(".//td[@class='td1']", $row);
                    if ($td_cells->length > 0) {
                        // Extrage cota
                        if ($cota_col_index >= 0 && $cota_col_index < $td_cells->length && empty($data['cota'])) {
                            $cota_td = $td_cells->item($cota_col_index);
                            $cota_val = trim($cota_td->textContent);
                            if (!empty($cota_val) && $cota_val !== 'Pe raft' && strlen($cota_val) > 0) {
                                $data['cota'] = $cota_val;
                                $data['locatie'] = $cota_val;
                            }
                        }
                        
                        // Extrage barcode
                        if ($barcode_col_index >= 0 && $barcode_col_index < $td_cells->length && empty($data['barcode'])) {
                            $barcode_td = $td_cells->item($barcode_col_index);
                            $barcode_val = trim($barcode_td->textContent);
                            if (!empty($barcode_val) && strlen($barcode_val) > 0) {
                                $data['barcode'] = $barcode_val;
                            }
                        }
                        
                        // Dacă am găsit ambele, oprim căutarea
                        if (!empty($data['cota']) && !empty($data['barcode'])) {
                            break;
                        }
                    }
                }
            }
        }
        } // Închide if (empty($data['cota']) && empty($data['barcode'])) pentru Metoda 2B

        // Fallback: Caută direct în HTML folosind comentariile <!--Localizare--> și <!--Barcod-->
        if (empty($data['cota']) || empty($data['barcode'])) {
            if (preg_match_all('/<!--Localizare-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i', $item_html, $cota_matches)) {
                foreach ($cota_matches[1] as $cota_match) {
                    $cota_val = trim(strip_tags($cota_match));
                    if (!empty($cota_val) && empty($data['cota'])) {
                        $data['cota'] = $cota_val;
                        $data['locatie'] = $cota_val;
                        break;
                    }
                }
            }
            
            if (preg_match_all('/<!--Barcod-->\s*<td[^>]*class=["\']?td1["\']?[^>]*>([^<]+)<\/td>/i', $item_html, $barcode_matches)) {
                foreach ($barcode_matches[1] as $barcode_match) {
                    $barcode_val = trim(strip_tags($barcode_match));
                    if (!empty($barcode_val) && empty($data['barcode'])) {
                        $data['barcode'] = $barcode_val;
                        break;
                    }
                }
            }
        }
        
        // Curăță și normalizează toate câmpurile text
        foreach ($data as $key => $value) {
            if (is_string($value) && !empty($value)) {
                // Fix encoding dacă mai sunt probleme
                $data[$key] = convertAlephEncoding($value);
                // Curăță spații multiple și caractere invizibile
                $data[$key] = preg_replace('/\s+/', ' ', trim($data[$key]));
            }
        }
        
        // Verifică dacă titlul este un mesaj de eroare/sesiune expirată sau titlu generic
        // IMPORTANT: Verificăm doar titluri GENERICE specifice care indică că nu s-a găsit nicio carte
        // NU verificăm "Căutare de bază" pentru că apare și în titlul paginii de căutare, nu doar când nu există carte
        $titlu = trim($data['titlu'] ?? '');
        $mesaje_eroare = [
            'Sfârşitul sesiunii',
            'Sfârșitul sesiunii',
            'End of session',
            'Session ended',
            'Sesiune expirată',
            'Session expired',
            'Căutări anterioare',  // Titlu generic când nu se găsește nicio carte
            'Previous searches'    // Titlu generic când nu se găsește nicio carte
        ];
        
        foreach ($mesaje_eroare as $mesaj_eroare) {
            if (stripos($titlu, $mesaj_eroare) !== false) {
                return [
                    'success' => false,
                    'mesaj' => "Nu există această carte în baza de date Aleph",
                    'debug' => $debug_info,
                    'data_partiala' => $data
                ];
            }
        }
        
        // Verifică date minime
        // IMPORTANT: Dacă titlul este gol sau prea scurt, cartea nu există
        $titlu_check = trim($data['titlu'] ?? '');
        if (empty($titlu_check) || strlen($titlu_check) < 3) {
            return [
                'success' => false,
                'mesaj' => "Nu există această carte în baza de date Aleph",
                'debug' => $debug_info,
                'data_partiala' => $data
            ];
        }
        
        return [
            'success' => true,
            'data' => $data,
            'debug' => $debug_info
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'mesaj' => $e->getMessage(),
            'debug' => $debug_info
        ];
    }
}

// ==========================================
// FUNCȚII WRAPPER - folosesc strategia AUTO
// ==========================================
function cautaCarteInAlephDupaBarcode($barcode) {
    return cautaCarteInAleph($barcode, 'AUTO');
}

function cautaCarteInAlephDupaCota($cota) {
    return cautaCarteInAleph($cota, 'AUTO');
}

// ==========================================
// API ENDPOINT (când se apelează direct)
// ==========================================
if (isset($_GET['cota']) || isset($_GET['barcode'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (isset($_GET['barcode'])) {
        $result = cautaCarteInAlephDupaBarcode($_GET['barcode']);
    } else {
        $result = cautaCarteInAlephDupaCota($_GET['cota']);
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>