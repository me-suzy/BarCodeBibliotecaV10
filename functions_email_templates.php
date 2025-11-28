<?php
/**
 * Funcții pentru generare email-uri personalizate din modele
 * Folosește modelele din baza de date și le personalizează cu datele utilizatorului
 */

require_once 'config.php';

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

/**
 * Obține modelul de email din baza de date
 * 
 * @param string $tip_notificare 'imprumut', 'reminder', sau 'intarziere'
 * @return array|false Array cu modelul sau false dacă nu există
 */
function obtineModelEmail($tip_notificare) {
    global $pdo;
    
    // Setează encoding-ul conexiunii la UTF-8 pentru citire corectă a diacriticelor
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    
    $stmt = $pdo->prepare("
        SELECT * FROM modele_email 
        WHERE tip_notificare = ? AND activ = TRUE 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$tip_notificare]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generează variabile pentru singular/plural în funcție de numărul de cărți
 * 
 * @param int $numar_carti Număr de cărți
 * @return array Array cu variabile pentru singular/plural
 */
function genereazaVariabilePlural($numar_carti) {
    $este_plural = $numar_carti > 1;
    
    return [
        '{{CARTE_CARTI}}' => $este_plural ? 'cărți' : 'carte',
        '{{ACESTA_ACESTE}}' => $este_plural ? 'aceste' : 'această',
        '{{CARTEA_CARTILE}}' => $este_plural ? 'cărțile' : 'cartea',
        '{{CARTII_CARTI}}' => $este_plural ? 'cărți' : 'carte',
        '{{FRAZA_CARTE}}' => $este_plural ? 'aceste cărți' : 'această carte',
        '{{FRAZA_CARTEA}}' => $este_plural ? 'cărțile' : 'cartea',
        '{{FRAZA_CARTI}}' => $este_plural ? 'cărțile' : 'cartea',
        '{{FRAZA_IMPRUMUTATE}}' => $este_plural ? 'cărțile împrumutate' : 'cartea împrumutată',
        '{{FRAZA_RETURNARE}}' => $este_plural ? 'cărțile' : 'cartea',
        '{{FRAZA_BENEFICIEZE}}' => $este_plural ? 'aceste cărți' : 'această carte',
        '{{FRAZA_IMPRUMUTE}}' => $este_plural ? 'le' : 'o'
    ];
}

/**
 * Generează lista de cărți în format HTML pentru email
 * 
 * @param array $carti Array cu cărți (fiecare cu: titlu, autor, cod_bare, data_imprumut, data_returnare)
 * @return string HTML formatat
 */
function genereazaListaCartiHTML($carti) {
    if (empty($carti)) {
        return '<p>Nu există cărți.</p>';
    }
    
    $html = '';
    foreach ($carti as $carte) {
        $html .= '<div class="book-item">';
        $html .= '<div class="book-title">📖 ' . htmlspecialchars($carte['titlu'] ?? 'Necunoscut') . '</div>';
        
        if (!empty($carte['autor'])) {
            $html .= '<div class="book-info">👤 Autor: ' . htmlspecialchars($carte['autor']) . '</div>';
        }
        
        if (!empty($carte['cod_bare'])) {
            $html .= '<div class="book-info">🏷️ Cod: ' . htmlspecialchars($carte['cod_bare']) . '</div>';
        }
        
        if (!empty($carte['data_imprumut'])) {
            $data_imprumut = date('d.m.Y', strtotime($carte['data_imprumut']));
            $html .= '<div class="book-info">📅 Împrumutată: ' . $data_imprumut . '</div>';
        }
        
        if (!empty($carte['locatie_completa'])) {
            $html .= '<div class="book-info">📍 Locație: ' . htmlspecialchars($carte['locatie_completa']) . '</div>';
        }
        
        // ✅ NOU - Adaugă statutul cărții dacă există
        if (!empty($carte['statut_carte']) || !empty($carte['nume_statut_carte'])) {
            require_once 'functions_statute_carti.php';
            global $pdo;
            
            $statut_carte = $carte['statut_carte'] ?? '';
            $nume_statut = $carte['nume_statut_carte'] ?? '';
            
            // Dacă nu avem numele, îl obținem din baza de date
            if (empty($nume_statut) && !empty($statut_carte) && isset($pdo)) {
                $nume_statut = getMesajStatutCarteEmail($pdo, $statut_carte);
            }
            
            if (!empty($nume_statut)) {
                $html .= '<div class="book-info">🏷️ Statut: ' . htmlspecialchars($nume_statut) . '</div>';
            }
        }
        
        // ✅ NOU - Adaugă durata de împrumut dacă există
        if (!empty($carte['durata_zile'])) {
            $html .= '<div class="book-info">⏱️ Durată împrumut: ' . (int)$carte['durata_zile'] . ' zile</div>';
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Generează lista de cărți în format text simplu
 * 
 * @param array $carti Array cu cărți
 * @return string Text formatat
 */
function genereazaListaCartiText($carti) {
    if (empty($carti)) {
        return 'Nu există cărți.';
    }
    
    $text = '';
    $numar = 1;
    foreach ($carti as $carte) {
        $text .= "\n$numar. " . ($carte['titlu'] ?? 'Necunoscut');
        
        if (!empty($carte['autor'])) {
            $text .= ' - ' . $carte['autor'];
        }
        
        if (!empty($carte['cod_bare'])) {
            $text .= ' (Cod: ' . $carte['cod_bare'] . ')';
        }
        
        if (!empty($carte['data_imprumut'])) {
            $data_imprumut = date('d.m.Y', strtotime($carte['data_imprumut']));
            $text .= ' - Împrumutată: ' . $data_imprumut;
        }
        
        $numar++;
    }
    
    return $text;
}

/**
 * Calculează zilele rămase până la data returnare
 * 
 * @param string $data_returnare Data returnare (Y-m-d)
 * @return int Număr zile rămase (poate fi negativ dacă a expirat)
 */
function calculeazaZileRamase($data_returnare) {
    $data_returnare_ts = strtotime($data_returnare);
    $acum_ts = time();
    $diferenta = $data_returnare_ts - $acum_ts;
    return (int)floor($diferenta / (60 * 60 * 24));
}

/**
 * Calculează zilele de întârziere
 * 
 * @param string $data_returnare Data returnare (Y-m-d)
 * @return int Număr zile întârziere (0 dacă nu este întârziat)
 */
function calculeazaZileIntarziere($data_returnare) {
    $zile_ramase = calculeazaZileRamase($data_returnare);
    return $zile_ramase < 0 ? abs($zile_ramase) : 0;
}

/**
 * Generează email personalizat pentru împrumut
 * 
 * @param array $cititor Date cititor (nume, prenume, email)
 * @param array $carti Array cu cărți împrumutate
 * @return array ['subiect' => string, 'html' => string, 'text' => string]
 */
function genereazaEmailImprumut($cititor, $carti) {
    $model = obtineModelEmail('imprumut');
    
    if (!$model) {
        return false;
    }
    
    // Calculează data returnare (14 zile de la împrumut)
    $data_imprumut = !empty($carti[0]['data_imprumut']) ? $carti[0]['data_imprumut'] : date('Y-m-d');
    $data_returnare = date('d.m.Y', strtotime($data_imprumut . ' +14 days'));
    
    // Generează variabile pentru plural/singular
    $numar_carti = count($carti);
    $variabile_plural = genereazaVariabilePlural($numar_carti);
    
    // Variabile pentru înlocuire
    $variabile = array_merge([
        '{{NUME_COMPLET}}' => trim(($cititor['nume'] ?? '') . ' ' . ($cititor['prenume'] ?? '')),
        '{{LISTA_CARTI}}' => genereazaListaCartiHTML($carti),
        '{{LISTA_CARTI_TEXT}}' => genereazaListaCartiText($carti),
        '{{DATA_RETURNARE}}' => $data_returnare
    ], $variabile_plural);
    
    // Înlocuiește variabilele în template
    $html = str_replace(array_keys($variabile), array_values($variabile), $model['template_html']);
    $text = str_replace(array_keys($variabile), array_values($variabile), $model['template_text']);
    
    return [
        'subiect' => $model['subiect'],
        'html' => $html,
        'text' => $text
    ];
}

/**
 * Generează email personalizat pentru reminder returnare
 * 
 * @param array $cititor Date cititor
 * @param array $carti Array cu cărți
 * @param string $data_returnare Data returnare (Y-m-d)
 * @return array ['subiect' => string, 'html' => string, 'text' => string]
 */
function genereazaEmailReminder($cititor, $carti, $data_returnare) {
    $model = obtineModelEmail('reminder');
    
    if (!$model) {
        return false;
    }
    
    $data_returnare_format = date('d.m.Y', strtotime($data_returnare));
    $zile_ramase = calculeazaZileRamase($data_returnare);
    
    // Generează variabile pentru plural/singular
    $numar_carti = count($carti);
    $variabile_plural = genereazaVariabilePlural($numar_carti);
    
    // Variabile pentru înlocuire
    $variabile = array_merge([
        '{{NUME_COMPLET}}' => trim(($cititor['nume'] ?? '') . ' ' . ($cititor['prenume'] ?? '')),
        '{{LISTA_CARTI}}' => genereazaListaCartiHTML($carti),
        '{{LISTA_CARTI_TEXT}}' => genereazaListaCartiText($carti),
        '{{DATA_RETURNARE}}' => $data_returnare_format,
        '{{ZILE_RAMASE}}' => max(0, $zile_ramase)
    ], $variabile_plural);
    
    // Înlocuiește variabilele în template
    $html = str_replace(array_keys($variabile), array_values($variabile), $model['template_html']);
    $text = str_replace(array_keys($variabile), array_values($variabile), $model['template_text']);
    
    return [
        'subiect' => $model['subiect'],
        'html' => $html,
        'text' => $text
    ];
}

/**
 * Generează email personalizat pentru alertă întârziere
 * 
 * @param array $cititor Date cititor
 * @param array $carti Array cu cărți
 * @param string $data_returnare Data returnare (Y-m-d)
 * @return array ['subiect' => string, 'html' => string, 'text' => string]
 */
function genereazaEmailIntarziere($cititor, $carti, $data_returnare) {
    $model = obtineModelEmail('intarziere');
    
    if (!$model) {
        return false;
    }
    
    $data_returnare_format = date('d.m.Y', strtotime($data_returnare));
    $data_expirare_format = date('d.m.Y', strtotime($data_returnare));
    $zile_intarziere = calculeazaZileIntarziere($data_returnare);
    
    // Generează variabile pentru plural/singular
    $numar_carti = count($carti);
    $variabile_plural = genereazaVariabilePlural($numar_carti);
    
    // Variabile pentru înlocuire
    $variabile = array_merge([
        '{{NUME_COMPLET}}' => trim(($cititor['nume'] ?? '') . ' ' . ($cititor['prenume'] ?? '')),
        '{{LISTA_CARTI}}' => genereazaListaCartiHTML($carti),
        '{{LISTA_CARTI_TEXT}}' => genereazaListaCartiText($carti),
        '{{DATA_RETURNARE}}' => $data_returnare_format,
        '{{DATA_EXPIRARE}}' => $data_expirare_format,
        '{{ZILE_INTARZIERE}}' => $zile_intarziere
    ], $variabile_plural);
    
    // Înlocuiește variabilele în template
    $html = str_replace(array_keys($variabile), array_values($variabile), $model['template_html']);
    $text = str_replace(array_keys($variabile), array_values($variabile), $model['template_text']);
    
    return [
        'subiect' => $model['subiect'],
        'html' => $html,
        'text' => $text
    ];
}

/**
 * Trimite email personalizat către un cititor
 * 
 * @param string $email Email destinatar
 * @param string $tip_notificare 'imprumut', 'reminder', sau 'intarziere'
 * @param array $cititor Date cititor
 * @param array $carti Array cu cărți
 * @param array $config_email Configurație SMTP
 * @param string|null $data_returnare Data returnare (opțional, pentru reminder/intarziere)
 * @return array ['success' => bool, 'message' => string]
 */
function trimiteEmailPersonalizat($email, $tip_notificare, $cititor, $carti, $config_email, $data_returnare = null) {
    require_once 'send_email.php';
    
    // Generează email-ul în funcție de tip
    switch ($tip_notificare) {
        case 'imprumut':
            $email_content = genereazaEmailImprumut($cititor, $carti);
            break;
        case 'reminder':
            if (!$data_returnare) {
                // Calculează data returnare (14 zile de la împrumut)
                $data_imprumut = !empty($carti[0]['data_imprumut']) ? $carti[0]['data_imprumut'] : date('Y-m-d');
                $data_returnare = date('Y-m-d', strtotime($data_imprumut . ' +14 days'));
            }
            $email_content = genereazaEmailReminder($cititor, $carti, $data_returnare);
            break;
        case 'intarziere':
            if (!$data_returnare) {
                // Calculează data returnare (14 zile de la împrumut)
                $data_imprumut = !empty($carti[0]['data_imprumut']) ? $carti[0]['data_imprumut'] : date('Y-m-d');
                $data_returnare = date('Y-m-d', strtotime($data_imprumut . ' +14 days'));
            }
            $email_content = genereazaEmailIntarziere($cititor, $carti, $data_returnare);
            break;
        default:
            return [
                'success' => false,
                'message' => 'Tip notificare necunoscut: ' . $tip_notificare
            ];
    }
    
    if (!$email_content) {
        return [
            'success' => false,
            'message' => 'Nu s-a putut genera conținutul email-ului'
        ];
    }
    
    // Trimite email-ul
    $rezultat = trimiteEmailSMTP($email, $email_content['subiect'], $email_content['html'], $config_email);
    
    return $rezultat;
}

