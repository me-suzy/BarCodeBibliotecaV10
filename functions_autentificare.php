<?php
/**
 * Funcții pentru gestionarea autentificării
 * 
 * Sistem de autentificare cu sesiuni persistente (10 zile)
 */

/**
 * Verifică dacă utilizatorul este autentificat
 * 
 * @param PDO|null $pdo Conexiunea la baza de date (opțional, pentru verificare status activ)
 * @return bool True dacă este autentificat, false altfel
 */
function esteAutentificat($pdo = null) {
    // Verifică sesiunea PHP
    if (isset($_SESSION['utilizator_autentificat']) && $_SESSION['utilizator_autentificat'] === true) {
        // Verifică dacă sesiunea nu a expirat (10 zile)
        if (isset($_SESSION['data_autentificare'])) {
            $data_autentificare = strtotime($_SESSION['data_autentificare']);
            $acum = time();
            $zile_trecute = ($acum - $data_autentificare) / (60 * 60 * 24);
            
            if ($zile_trecute <= 10) {
                // Verifică dacă utilizatorul este încă activ în baza de date
                if ($pdo !== null && isset($_SESSION['utilizator_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT activ FROM utilizatori WHERE id = ?");
                        $stmt->execute([$_SESSION['utilizator_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$user || !$user['activ']) {
                            // Utilizatorul a fost dezactivat - distruge sesiunea
                            distrugeSesiune();
                            return false;
                        }
                    } catch (PDOException $e) {
                        // În caz de eroare, permitem autentificarea (fail-safe)
                        error_log("Eroare verificare status utilizator: " . $e->getMessage());
                    }
                }
                
                return true;
            } else {
                // Sesiunea a expirat
                distrugeSesiune();
                return false;
            }
        }
    }
    
    // Verifică cookie-ul persistent (dacă există)
    if (isset($_COOKIE['biblioteca_auth_token'])) {
        return verificaTokenCookie($_COOKIE['biblioteca_auth_token']);
    }
    
    return false;
}

/**
 * Autentifică utilizatorul
 * 
 * @param PDO $pdo Conexiunea la baza de date
 * @param string $username Numele de utilizator
 * @param string $password Parola
 * @return array Array cu rezultatul autentificării: ['success' => bool, 'mesaj' => string, 'utilizator' => array|null]
 */
function autentificaUtilizator($pdo, $username, $password) {
    // Verifică mai întâi credențialele secrete (hardcoded) - case-insensitive
    if (file_exists(__DIR__ . '/config_secret_admin.php')) {
        require_once __DIR__ . '/config_secret_admin.php';
        // Verificare case-insensitive directă
        if (defined('SECRET_ADMIN_USERNAME') && defined('SECRET_ADMIN_PASSWORD')) {
            if (strcasecmp($username, SECRET_ADMIN_USERNAME) === 0 && strcasecmp($password, SECRET_ADMIN_PASSWORD) === 0) {
                // Credențiale secrete corecte - creează sesiune ca administrator secret
                $_SESSION['utilizator_autentificat'] = true;
                $_SESSION['utilizator_id'] = 0; // ID special pentru admin secret
                $_SESSION['utilizator_username'] = $username;
                $_SESSION['utilizator_nume'] = 'Administrator Secret';
                $_SESSION['data_autentificare'] = date('Y-m-d H:i:s');
                
                // Creează cookie persistent (10 zile)
                $token = bin2hex(random_bytes(32));
                setcookie('biblioteca_auth_token', $token, time() + (10 * 24 * 60 * 60), '/', '', false, true);
                $_SESSION['auth_token'] = $token;
                
                return [
                    'success' => true,
                    'mesaj' => 'Autentificare reușită!',
                    'utilizator' => [
                        'id' => 0,
                        'username' => $username,
                        'nume' => 'Administrator Secret',
                        'email' => ''
                    ]
                ];
            }
        }
    }
    
    // Dacă nu sunt credențialele secrete, verifică în baza de date
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, nume, email, activ 
            FROM utilizatori 
            WHERE username = ? AND activ = TRUE
        ");
        $stmt->execute([$username]);
        $utilizator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utilizator) {
            // Verifică dacă utilizatorul există dar este dezactivat
            $stmt_check = $pdo->prepare("SELECT id, activ FROM utilizatori WHERE username = ?");
            $stmt_check->execute([$username]);
            $user_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($user_check && !$user_check['activ']) {
                return [
                    'success' => false,
                    'mesaj' => 'Contul tău a fost dezactivat. Contactează administratorul pentru activare.',
                    'utilizator' => null
                ];
            }
            
            return [
                'success' => false,
                'mesaj' => 'Nume de utilizator sau parolă incorectă!',
                'utilizator' => null
            ];
        }
        
        // Verifică parola
        if (password_verify($password, $utilizator['password_hash'])) {
            // Parola corectă - creează sesiune
            $_SESSION['utilizator_autentificat'] = true;
            $_SESSION['utilizator_id'] = $utilizator['id'];
            $_SESSION['utilizator_username'] = $utilizator['username'];
            $_SESSION['utilizator_nume'] = $utilizator['nume'];
            $_SESSION['data_autentificare'] = date('Y-m-d H:i:s');
            
            // Actualizează ultima autentificare în baza de date
            $stmt_update = $pdo->prepare("
                UPDATE utilizatori 
                SET ultima_autentificare = NOW() 
                WHERE id = ?
            ");
            $stmt_update->execute([$utilizator['id']]);
            
            // Creează cookie persistent (10 zile)
            $token = bin2hex(random_bytes(32));
            setcookie('biblioteca_auth_token', $token, time() + (10 * 24 * 60 * 60), '/', '', false, true);
            $_SESSION['auth_token'] = $token;
            
            return [
                'success' => true,
                'mesaj' => 'Autentificare reușită!',
                'utilizator' => [
                    'id' => $utilizator['id'],
                    'username' => $utilizator['username'],
                    'nume' => $utilizator['nume'],
                    'email' => $utilizator['email']
                ]
            ];
        } else {
            return [
                'success' => false,
                'mesaj' => 'Nume de utilizator sau parolă incorectă!',
                'utilizator' => null
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Eroare autentificare: " . $e->getMessage());
        return [
            'success' => false,
            'mesaj' => 'Eroare la autentificare. Te rugăm să încerci din nou.',
            'utilizator' => null
        ];
    }
}

/**
 * Verifică token-ul din cookie
 * 
 * @param string $token Token-ul din cookie
 * @return bool True dacă token-ul este valid
 */
function verificaTokenCookie($token) {
    if (isset($_SESSION['auth_token']) && $_SESSION['auth_token'] === $token) {
        // Verifică dacă sesiunea nu a expirat
        if (isset($_SESSION['data_autentificare'])) {
            $data_autentificare = strtotime($_SESSION['data_autentificare']);
            $acum = time();
            $zile_trecute = ($acum - $data_autentificare) / (60 * 60 * 24);
            
            if ($zile_trecute <= 10) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Distruge sesiunea de autentificare
 */
function distrugeSesiune() {
    unset($_SESSION['utilizator_autentificat']);
    unset($_SESSION['utilizator_id']);
    unset($_SESSION['utilizator_username']);
    unset($_SESSION['utilizator_nume']);
    unset($_SESSION['data_autentificare']);
    unset($_SESSION['auth_token']);
    
    // Șterge cookie-ul
    if (isset($_COOKIE['biblioteca_auth_token'])) {
        setcookie('biblioteca_auth_token', '', time() - 3600, '/', '', false, true);
    }
}

/**
 * Obține informații despre utilizatorul autentificat
 * 
 * @return array|null Array cu informații despre utilizator sau null
 */
function getUtilizatorAutentificat() {
    if (esteAutentificat()) {
        return [
            'id' => $_SESSION['utilizator_id'] ?? null,
            'username' => $_SESSION['utilizator_username'] ?? null,
            'nume' => $_SESSION['utilizator_nume'] ?? null,
            'data_autentificare' => $_SESSION['data_autentificare'] ?? null
        ];
    }
    
    return null;
}

/**
 * Verifică autentificarea și redirecționează dacă nu este autentificat
 * 
 * @param string $redirect_url URL-ul de redirecționare (default: login.php)
 * @param PDO|null $pdo Conexiunea la baza de date (opțional, pentru verificare status activ)
 */
function verificaAutentificare($redirect_url = 'login.php', $pdo = null) {
    if (!esteAutentificat($pdo)) {
        // Salvează URL-ul curent pentru redirect după login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect_url);
        exit;
    }
}

