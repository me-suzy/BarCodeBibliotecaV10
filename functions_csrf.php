<?php
/**
 * Funcții pentru protecție CSRF (Cross-Site Request Forgery)
 * Include acest fișier în paginile cu formulare
 */

/**
 * Generează un token CSRF unic pentru sesiunea curentă
 * @return string Token-ul CSRF
 */
function genereazaTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerează token-ul CSRF (folosește după operații sensibile)
 * @return string Noul token CSRF
 */
function regenereazaTokenCSRF() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Verifică dacă token-ul CSRF trimis este valid
 * @param string $token Token-ul trimis din formular
 * @return bool True dacă token-ul este valid
 */
function verificaTokenCSRF($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generează un câmp hidden pentru formulare cu token-ul CSRF
 * @return string HTML-ul pentru câmpul hidden
 */
function campCSRF() {
    $token = genereazaTokenCSRF();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verifică automat CSRF pentru request-uri POST
 * Apelează această funcție la începutul paginilor cu formulare
 * @param bool $die Dacă să oprească execuția în caz de eroare
 * @return bool|void True dacă valid, die() sau false dacă invalid
 */
function verificaCSRF($die = true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verificaTokenCSRF($token)) {
            if ($die) {
                http_response_code(403);
                die('❌ Eroare de securitate: Token CSRF invalid. Reîncarcă pagina și încearcă din nou.');
            }
            return false;
        }
    }
    return true;
}

/**
 * Adaugă token CSRF la o URL (pentru link-uri GET sensibile)
 * @param string $url URL-ul original
 * @return string URL-ul cu token CSRF
 */
function adaugaCSRFlaURL($url) {
    $token = genereazaTokenCSRF();
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $separator . 'csrf_token=' . urlencode($token);
}

/**
 * Verifică CSRF pentru request-uri GET sensibile
 * @return bool True dacă valid
 */
function verificaCSRFget() {
    $token = $_GET['csrf_token'] ?? '';
    return verificaTokenCSRF($token);
}


