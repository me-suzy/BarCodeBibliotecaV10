<?php
/**
 * Script pentru adăugarea verificării de autentificare pe toate paginile PHP
 * Rulează acest script o singură dată
 */

$director = __DIR__;

// Lista de fișiere care NU trebuie protejate
$exclude_files = [
    'login.php',
    'config.php',
    'auth_check.php',
    'functions_autentificare.php',
    'instaleaza_autentificare.php',
    'instaleaza_statute.php',
    'instaleaza_statute_carti.php',
    'instaleaza_statute_carti_simplu.php',
    'verifica_instalare_statute.php',
    'verifica_instalare_xampp.php',
    'diagnosticare_mysql.php',
    'diagnosticare_avansata_mysql.php',
    'analiza_crash_mysql.php',
    'citeste_log_mysql.php',
    'test_encoding.php',
    'test_encoding_db.php',
    'test_modele_email.php',
    'fix_modele_email_encoding.php',
    'verifica_encoding.php',
    'repara_date_corupte.php',
    'cron_notificari.php',
    'cron_notificari_intarzieri.php',
    'trimite_rapoarte_zilnice.php',
    'aleph_api.php',
    'aleph_api (fara ISBN).php',
    'test_aleph.php',
    'test_aleph_wlb.php',
    'test_aleph_wlb_2.php',
    'test_aleph_direct.php',
    'debug_isbn.php',
    'debug_details.php',
    'debug_links.php',
    'view_debug.php',
    'import_carte_aleph.php',
    'verifica_server.php',
    'verifica_status.php',
    'verifica_detaliata.php',
    'verifica_final.php',
    'verifica_imprumuturi.php',
    'curatare_finala.php',
    'curatare_completa.php',
    'executa_curatare.php',
    'curatare_finala_script.php',
    'curatare_imprumuturi.php',
    'curatare_imprumuturi_script.php',
    'executa_curatare_script.php',
    'sterge_dubluri_final.php',
    'sterge_dubluri_script.php',
    'sterge_dubluri_imprumuturi.php',
    'update_database_script.php',
    'update_database.php',
    'setup.php',
    'setup_modele_email.php',
    'send_email.php',
    'notificare_imprumut.php',
    'trimite_notificare.php',
    'sistem_notificari.php',
    'afiseaza_limite_statute.php',
    'protejeaza_toate_paginile.php' // Exclude acest script
];

// Lista de directoare care NU trebuie procesate
$exclude_dirs = [
    'Securitate',
    'scripts_saved',
    'coduri_utilizatori',
    'coduri_carti',
    'pdf_print'
];

// Funcție pentru a verifica dacă un fișier este deja protejat
function esteProtejat($content) {
    return (
        strpos($content, 'require_once \'auth_check.php\'') !== false ||
        strpos($content, 'verificaAutentificare') !== false ||
        strpos($content, 'functions_autentificare.php') !== false
    );
}

// Funcție pentru a adăuga protecția
function adaugaProtectie($content) {
    // Verifică dacă are deja session_start
    $are_session_start = strpos($content, 'session_start()') !== false;
    
    // Găsește primul require_once 'config.php'
    $pattern = "/(require_once\s+['\"]config\.php['\"];?)/";
    
    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        $pos = $matches[0][1];
        $before = substr($content, 0, $pos);
        $after = substr($content, $pos);
        
        // Adaugă session_start dacă nu există
        if (!$are_session_start) {
            $before .= "\nsession_start();\n";
        }
        
        // Adaugă auth_check după config.php
        $new_content = $before . $after;
        $new_content = preg_replace(
            $pattern,
            "$1\nrequire_once 'auth_check.php';",
            $new_content,
            1
        );
        
        return $new_content;
    }
    
    return $content;
}

// Procesează toate fișierele PHP
$fisiere_procesate = 0;
$fisiere_protejate = 0;
$fisiere_skip = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($director),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filename = $file->getFilename();
        $relative_path = str_replace($director . DIRECTORY_SEPARATOR, '', $file->getPathname());
        
        // Verifică dacă este în lista de exclude
        if (in_array($filename, $exclude_files)) {
            $fisiere_skip++;
            continue;
        }
        
        // Verifică dacă este într-un director exclus
        $skip = false;
        foreach ($exclude_dirs as $exclude_dir) {
            if (strpos($relative_path, $exclude_dir) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            $fisiere_skip++;
            continue;
        }
        
        // Citește conținutul
        $content = file_get_contents($file->getPathname());
        
        // Verifică dacă este deja protejat
        if (esteProtejat($content)) {
            $fisiere_skip++;
            continue;
        }
        
        // Verifică dacă este un fișier de funcții (nu pagină web)
        if (strpos($content, '<?php') === 0 && 
            (strpos($content, 'header(') === false && 
             strpos($content, '<!DOCTYPE') === false &&
             strpos($content, '<html') === false)) {
            // Probabil este un fișier de funcții, nu pagină web
            $fisiere_skip++;
            continue;
        }
        
        // Adaugă protecția
        $new_content = adaugaProtectie($content);
        
        if ($new_content !== $content) {
            // Creează backup
            $backup_path = $file->getPathname() . '.backup';
            file_put_contents($backup_path, $content);
            
            // Scrie conținutul nou
            file_put_contents($file->getPathname(), $new_content);
            
            echo "✅ Protejat: $relative_path\n";
            $fisiere_protejate++;
        }
        
        $fisiere_procesate++;
    }
}

echo "\n📊 Rezumat:\n";
echo "  - Fișiere procesate: $fisiere_procesate\n";
echo "  - Fișiere protejate: $fisiere_protejate\n";
echo "  - Fișiere skip (exclude/deja protejate): $fisiere_skip\n";
echo "\n✅ Gata! Toate paginile sunt acum protejate.\n";

