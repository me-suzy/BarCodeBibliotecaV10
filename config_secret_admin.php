<?php
/**
 * Configurație User Secret Administrator
 * 
 * ⚠️ SECRET - Acest fișier conține credențiale secrete!
 * Nu ar trebui să fie accesibil public și nu ar trebui să apară în modifica_utilizator.php
 * 
 * User-ul secret poate fi folosit pentru autentificare administrator
 * dar nu apare în lista de utilizatori din modifica_utilizator.php
 */

// User și parolă secretă (hardcoded pentru securitate maximă)
define('SECRET_ADMIN_USERNAME', 'bebe2025');
define('SECRET_ADMIN_PASSWORD', 'bebe');

/**
 * Verifică dacă username-ul și parola corespund user-ului secret
 * 
 * @param string $username Username-ul introdus
 * @param string $password Parola introdusă
 * @return bool True dacă credențialele sunt corecte pentru user-ul secret
 */
function verificaSecretAdmin($username, $password) {
    return ($username === SECRET_ADMIN_USERNAME && $password === SECRET_ADMIN_PASSWORD);
}

/**
 * Verifică dacă un username este user-ul secret (pentru filtrare din liste)
 * 
 * @param string $username Username-ul de verificat
 * @return bool True dacă este user-ul secret
 */
function esteSecretAdmin($username) {
    return ($username === SECRET_ADMIN_USERNAME);
}





