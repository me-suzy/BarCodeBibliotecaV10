<?php
/**
 * Script pentru verificarea că TOATE paginile PHP sunt protejate
 * Rulează acest script pentru a verifica dacă există pagini neprotejate
 */

$director = __DIR__;

// Lista de fișiere care NU trebuie protejate (excepții)
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
    'protejeaza_toate_paginile.php',
    'verifica_protectie_completa.php'
];

// Lista de directoare care NU trebuie procesate
$exclude_dirs = [
    'Securitate',
    'scripts_saved',
    'coduri_utilizatori',
    'coduri_carti',
    'pdf_print'
];

// Funcție pentru a verifica dacă un fișier este protejat
function esteProtejat($content) {
    return (
        strpos($content, 'require_once \'auth_check.php\'') !== false ||
        strpos($content, 'require_once "auth_check.php"') !== false ||
        strpos($content, 'verificaAutentificare') !== false ||
        strpos($content, 'functions_autentificare.php') !== false
    );
}

// Funcție pentru a verifica dacă este fișier de funcții (nu pagină web)
function esteFisierFunctii($content) {
    // Dacă nu are header() și nu are HTML, probabil este fișier de funcții
    return (
        strpos($content, 'header(') === false && 
        strpos($content, '<!DOCTYPE') === false &&
        strpos($content, '<html') === false &&
        strpos($content, 'function ') !== false
    );
}

$fisiere_neprotejate = [];
$fisiere_protejate = 0;
$fisiere_excluse = 0;
$fisiere_functii = 0;

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
            $fisiere_excluse++;
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
            $fisiere_excluse++;
            continue;
        }
        
        // Citește conținutul
        $content = file_get_contents($file->getPathname());
        
        // Verifică dacă este fișier de funcții
        if (esteFisierFunctii($content)) {
            $fisiere_functii++;
            continue;
        }
        
        // Verifică dacă este protejat
        if (!esteProtejat($content)) {
            $fisiere_neprotejate[] = $relative_path;
        } else {
            $fisiere_protejate++;
        }
    }
}

// Afișează rezultate
echo "🔒 Verificare Protecție Completă\n";
echo str_repeat("=", 60) . "\n\n";

if (empty($fisiere_neprotejate)) {
    echo "✅ TOATE paginile sunt protejate!\n\n";
    echo "📊 Statistici:\n";
    echo "  - Pagini protejate: $fisiere_protejate\n";
    echo "  - Fișiere excluse: $fisiere_excluse\n";
    echo "  - Fișiere funcții: $fisiere_functii\n";
} else {
    echo "⚠️ GĂSITE " . count($fisiere_neprotejate) . " PAGINI NEPROTEJATE:\n\n";
    foreach ($fisiere_neprotejate as $fisier) {
        echo "  ❌ $fisier\n";
    }
    echo "\n📊 Statistici:\n";
    echo "  - Pagini protejate: $fisiere_protejate\n";
    echo "  - Pagini neprotejate: " . count($fisiere_neprotejate) . "\n";
    echo "  - Fișiere excluse: $fisiere_excluse\n";
    echo "  - Fișiere funcții: $fisiere_functii\n";
    echo "\n⚠️ ATENȚIE: Trebuie să adaugi protecție la paginile de mai sus!\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

