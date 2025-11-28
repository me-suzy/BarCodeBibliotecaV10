<?php
/**
 * Fișier helper pentru verificare autentificare
 * Include acest fișier la începutul fiecărei pagini care necesită autentificare
 * 
 * EXCEPTIE: Nu include în login.php!
 */

if (!isset($skip_auth_check) || !$skip_auth_check) {
    require_once 'config.php';
    require_once 'functions_autentificare.php';
    
    // Verifică autentificarea cu verificare status activ
    if (!esteAutentificat($pdo)) {
        // Salvează URL-ul curent pentru redirect după login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

