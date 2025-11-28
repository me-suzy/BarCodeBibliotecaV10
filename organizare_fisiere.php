<?php
/**
 * Script pentru organizarea fișierelor aplicației
 * Identifică fișierele esențiale și dublurile
 */

$root_dir = __DIR__;
$foldere_excluse = ['BackUp', 'Securitate', 'Verificare Conexiune', 'Configuratie Server Linux', 'assets', 'imagini', 'logs', 'emails_log', 'scripts_saved', 'Specificatii', '__pycache__', 'php', 'python', 'fpdf', 'phpmailer'];

// Pagini principale ale aplicației (identificate manual)
$pagini_principale = [
    'index.php',
    'login.php',
    'rapoarte.php',
    'cititori.php',
    'carti.php',
    'imprumuturi.php',
    'editare_cititor.php',
    'editare_carte.php',
    'editare_imprumut.php',
    'adauga_cititor.php',
    'adauga_carte.php',
    'raport_prezenta.php',
    'raport_intarzieri.php',
    'raport_top_carti.php',
    'raport_vizari.php',
    'status_vizari.php',
    'lista_nevizati.php',
    'istoric_imprumuturi.php',
    'cauta_cod.php',
    'repara_date_corupte.php',
    'modifica_utilizator.php',
    'verifica_parola_admin.php',
    'dashboard.php',
    'sistem_notificari.php',
    'export.php',
    'export_excel.php',
    'export_pdf.php',
    'backup.php',
    'backup_ajax.php',
    'backup_database.php',
    'scanare_rapida.php',
    'scanare_monitor_principal.php',
    'scanare_mini_monitor_alternativ.php',
    'scanare_inregistrare.php',
    'scanare_inregistrare_monitor_principal_v1.php',
    'scan_barcode.php',
    'scan_barcode v.1.php',
    'scanare_mini (cu cititor profesional) minimalizat.php',
    'scanare_rapida (cu cititor profesional).php',
    'trimite_notificare.php',
    'trimite_rapoarte_zilnice.php',
    'cron_notificari.php',
    'cron_notificari_intarzieri.php',
    'notificare_imprumut.php',
    'send_email.php',
    'import_carte_aleph.php',
    'curatare_imprumuturi.php',
    'curatare_completa.php',
    'curatare_finala.php',
    'executa_curatare.php',
    'sterge_dubluri_imprumuturi.php',
    'sterge_dubluri_final.php',
    'adauga_imprumuturi_mai_multe.php',
    'check_vizare_an_nou.php',
    'afiseaza_utilizatori.php',
    'afiseaza_limite_statute.php',
    'actualizeaza_statuturi_cititori.php',
    'actualizeaza_coduri_biblioteca_academiei.php',
    'update_database.php',
    'update_database_script.php',
    'instaleaza_statute.php',
    'instaleaza_statute_carti.php',
    'instaleaza_statute_carti_simplu.php',
    'instaleaza_autentificare.php',
    'repara_autentificare.php',
    'setup.php',
    'setup_modele_email.php',
    'protejeaza_toate_paginile.php',
    'verificare_securitate.php',
    'verifica_protectie_completa.php',
    'verifica_utilizatori_bd.php',
    'verifica_instalare_statute.php',
    'verifica_instalare_xampp.php',
    'verifica_imprumuturi.php',
    'verifica_encoding.php',
    'verifica_encoding_db.php',
    'verifica_detaliata.php',
    'verifica_final.php',
    'verifica_status.php',
    'verifica_server.php',
    'verifica_mysql_running.php',
    'verifica_mysql_xampp.php',
    'verifica_my_ini.php',
    'verifica_bind_address.php',
    'verifica_ready_connections.php',
    'fix_mysql_xampp.php',
    'fix_bind_address.php',
    'fix_modele_email_encoding.php',
    'diagnosticare_mysql.php',
    'diagnosticare_avansata_mysql.php',
    'analiza_crash_mysql.php',
    'citeste_log_mysql.php',
    'view_debug.php',
];

// Fișiere de configurare și funcții
$fisiere_config = [
    'config.php',
    'config_secret_admin.php',
    'config_security.php',
    'auth_check.php',
    'functions_autentificare.php',
    'functions_coduri_aleph.php',
    'functions_csrf.php',
    'functions_email_templates.php',
    'functions_sesiuni.php',
    'functions_statute.php',
    'functions_statute_carti.php',
    'functions_vizare.php',
    'aleph_api.php',
    'aleph_api (fara ISBN).php',
];

// Fișiere CSS/JS
$fisiere_frontend = [
    'ux-improvements.css',
    'ux-improvements.js',
];

// Fișiere biblioteci externe (să rămână în root sau în foldere)
$fisiere_biblioteci = [
    'PHPMailer.php',
    'SMTP.php',
    'Exception.php',
    'fpdf.php',
    'BarcodeLibrary.php',
    'helvetica.php',
    'helveticab.php',
    'helveticabi.php',
    'helveticai.php',
    'times.php',
    'timesb.php',
    'timesbi.php',
    'timesi.php',
    'courier.php',
    'courierb.php',
    'courierbi.php',
    'courieri.php',
];

// Fișiere test (să fie mutate în folder BackUp sau șterse)
$fisiere_test = [
    'test.php',
    'test_aleph.php',
    'test_aleph_direct.php',
    'test_aleph_search.php',
    'test_aleph_wlb.php',
    'test_aleph_wlb_2.php',
    'test_autentificare.php',
    'test_direct_aleph.php',
    'test_encoding.php',
    'test_encoding_db.php',
    'test_final_mysql.php',
    'test_ipv6_mysql.php',
    'test_modele_email.php',
    'test_mysql.php',
    'test_mysql_simple.php',
    'test_parola_secreta.php',
    'test_port_only.php',
    'test BUN cu titlu FULL 8 (cauta in toate bibliotecile).php',
];

// Fișiere duplicate (care există și în alte foldere)
$fisiere_duplicate = [];

// Verifică dublurile
function verificaDubluri($root_dir) {
    $dubluri = [];
    
    // Verifică dacă există fișiere în Securitate/
    if (is_dir($root_dir . '/Securitate')) {
        $securitate_files = glob($root_dir . '/Securitate/*.php');
        foreach ($securitate_files as $file) {
            $basename = basename($file);
            if (file_exists($root_dir . '/' . $basename)) {
                $dubluri[] = [
                    'fisier' => $basename,
                    'locatie_root' => $root_dir . '/' . $basename,
                    'locatie_duplicat' => $file,
                    'folder_duplicat' => 'Securitate'
                ];
            }
        }
    }
    
    // Verifică dacă există fișiere în BackUp/
    if (is_dir($root_dir . '/BackUp')) {
        $backup_files = glob($root_dir . '/BackUp/*.php');
        foreach ($backup_files as $file) {
            $basename = basename($file);
            if (file_exists($root_dir . '/' . $basename)) {
                $dubluri[] = [
                    'fisier' => $basename,
                    'locatie_root' => $root_dir . '/' . $basename,
                    'locatie_duplicat' => $file,
                    'folder_duplicat' => 'BackUp'
                ];
            }
        }
    }
    
    // Verifică dacă există fișiere în Verificare Conexiune/
    if (is_dir($root_dir . '/Verificare Conexiune')) {
        $verif_files = glob($root_dir . '/Verificare Conexiune/**/*.php', GLOB_BRACE);
        foreach ($verif_files as $file) {
            $basename = basename($file);
            if (file_exists($root_dir . '/' . $basename)) {
                $dubluri[] = [
                    'fisier' => $basename,
                    'locatie_root' => $root_dir . '/' . $basename,
                    'locatie_duplicat' => $file,
                    'folder_duplicat' => 'Verificare Conexiune'
                ];
            }
        }
    }
    
    return $dubluri;
}

$fisiere_duplicate = verificaDubluri($root_dir);

// Generează XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$root = $xml->createElement('aplicatie');
$root->setAttribute('nume', 'BarCode Biblioteca');
$root->setAttribute('data_scanare', date('Y-m-d H:i:s'));
$xml->appendChild($root);

// Pagini principale
$pagini_node = $xml->createElement('pagini_principale');
foreach ($pagini_principale as $pagina) {
    $pagina_node = $xml->createElement('pagina', $pagina);
    $pagina_node->setAttribute('exista', file_exists($root_dir . '/' . $pagina) ? 'da' : 'nu');
    $pagini_node->appendChild($pagina_node);
}
$root->appendChild($pagini_node);

// Fișiere config
$config_node = $xml->createElement('fisiere_config');
foreach ($fisiere_config as $fisier) {
    $fisier_node = $xml->createElement('fisier', $fisier);
    $fisier_node->setAttribute('exista', file_exists($root_dir . '/' . $fisier) ? 'da' : 'nu');
    $config_node->appendChild($fisier_node);
}
$root->appendChild($config_node);

// Fișiere frontend
$frontend_node = $xml->createElement('fisiere_frontend');
foreach ($fisiere_frontend as $fisier) {
    $fisier_node = $xml->createElement('fisier', $fisier);
    $fisier_node->setAttribute('exista', file_exists($root_dir . '/' . $fisier) ? 'da' : 'nu');
    $frontend_node->appendChild($fisier_node);
}
$root->appendChild($frontend_node);

// Fișiere biblioteci
$biblioteci_node = $xml->createElement('fisiere_biblioteci');
foreach ($fisiere_biblioteci as $fisier) {
    $fisier_node = $xml->createElement('fisier', $fisier);
    $fisier_node->setAttribute('exista', file_exists($root_dir . '/' . $fisier) ? 'da' : 'nu');
    $biblioteci_node->appendChild($fisier_node);
}
$root->appendChild($biblioteci_node);

// Fișiere test
$test_node = $xml->createElement('fisiere_test');
foreach ($fisiere_test as $fisier) {
    $fisier_node = $xml->createElement('fisier', $fisier);
    $fisier_node->setAttribute('exista', file_exists($root_dir . '/' . $fisier) ? 'da' : 'nu');
    $test_node->appendChild($fisier_node);
}
$root->appendChild($test_node);

// Dubluri
$dubluri_node = $xml->createElement('fisiere_duplicate');
foreach ($fisiere_duplicate as $dublu) {
    $dublu_node = $xml->createElement('duplicat');
    $dublu_node->setAttribute('fisier', $dublu['fisier']);
    $dublu_node->setAttribute('folder_duplicat', $dublu['folder_duplicat']);
    $dublu_node->setAttribute('locatie_root', $dublu['locatie_root']);
    $dublu_node->setAttribute('locatie_duplicat', $dublu['locatie_duplicat']);
    $dubluri_node->appendChild($dublu_node);
}
$root->appendChild($dubluri_node);

// Salvează XML
$xml_file = $root_dir . '/fisiere_esentiale.xml';
$xml->save($xml_file);

// Afișează rezultate
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizare Fișiere - BarCode Biblioteca</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #764ba2;
            margin-top: 30px;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .file-list {
            list-style: none;
            padding: 0;
        }
        .file-list li {
            padding: 8px;
            margin: 5px 0;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .file-list li.exists {
            border-left-color: #28a745;
        }
        .file-list li.missing {
            border-left-color: #dc3545;
        }
        .duplicate {
            background: #fff3cd;
            border-left-color: #ffc107;
            padding: 15px;
            margin: 10px 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2em;
            margin: 0;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <h1>📁 Organizare Fișiere - BarCode Biblioteca</h1>
    
    <div class="stats">
        <div class="stat-card">
            <h3><?php echo count($pagini_principale); ?></h3>
            <p>Pagini Principale</p>
        </div>
        <div class="stat-card">
            <h3><?php echo count($fisiere_config); ?></h3>
            <p>Fișiere Config</p>
        </div>
        <div class="stat-card">
            <h3><?php echo count($fisiere_duplicate); ?></h3>
            <p>Dubluri Identificate</p>
        </div>
        <div class="stat-card">
            <h3><?php echo count($fisiere_test); ?></h3>
            <p>Fișiere Test</p>
        </div>
    </div>
    
    <div class="section">
        <h2>✅ XML Generat</h2>
        <p>Fișierul XML a fost generat cu succes: <strong><?php echo basename($xml_file); ?></strong></p>
        <a href="<?php echo basename($xml_file); ?>" class="btn" download>📥 Descarcă XML</a>
    </div>
    
    <?php if (!empty($fisiere_duplicate)): ?>
    <div class="section">
        <h2>⚠️ Fișiere Duplicate Identificate</h2>
        <p>Următoarele fișiere există atât în root cât și în alte foldere:</p>
        <?php foreach ($fisiere_duplicate as $dublu): ?>
        <div class="duplicate">
            <strong>📄 <?php echo htmlspecialchars($dublu['fisier']); ?></strong><br>
            <small>
                Root: <?php echo htmlspecialchars($dublu['locatie_root']); ?><br>
                Duplicat în: <?php echo htmlspecialchars($dublu['folder_duplicat']); ?> → <?php echo htmlspecialchars($dublu['locatie_duplicat']); ?>
            </small>
        </div>
        <?php endforeach; ?>
        <p><strong>Recomandare:</strong> Păstrează versiunea din root și șterge dublurile din folderele respective.</p>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>📋 Pagini Principale (<?php echo count($pagini_principale); ?>)</h2>
        <ul class="file-list">
            <?php foreach ($pagini_principale as $pagina): ?>
            <li class="<?php echo file_exists($root_dir . '/' . $pagina) ? 'exists' : 'missing'; ?>">
                <?php echo file_exists($root_dir . '/' . $pagina) ? '✅' : '❌'; ?>
                <?php echo htmlspecialchars($pagina); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>⚙️ Fișiere Config și Funcții (<?php echo count($fisiere_config); ?>)</h2>
        <ul class="file-list">
            <?php foreach ($fisiere_config as $fisier): ?>
            <li class="<?php echo file_exists($root_dir . '/' . $fisier) ? 'exists' : 'missing'; ?>">
                <?php echo file_exists($root_dir . '/' . $fisier) ? '✅' : '❌'; ?>
                <?php echo htmlspecialchars($fisier); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>🎨 Fișiere Frontend (<?php echo count($fisiere_frontend); ?>)</h2>
        <ul class="file-list">
            <?php foreach ($fisiere_frontend as $fisier): ?>
            <li class="<?php echo file_exists($root_dir . '/' . $fisier) ? 'exists' : 'missing'; ?>">
                <?php echo file_exists($root_dir . '/' . $fisier) ? '✅' : '❌'; ?>
                <?php echo htmlspecialchars($fisier); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>📚 Fișiere Biblioteci (<?php echo count($fisiere_biblioteci); ?>)</h2>
        <p>Aceste fișiere pot rămâne în root sau pot fi mutate în foldere dedicate.</p>
        <ul class="file-list">
            <?php foreach ($fisiere_biblioteci as $fisier): ?>
            <li class="<?php echo file_exists($root_dir . '/' . $fisier) ? 'exists' : 'missing'; ?>">
                <?php echo file_exists($root_dir . '/' . $fisier) ? '✅' : '❌'; ?>
                <?php echo htmlspecialchars($fisier); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>🧪 Fișiere Test (<?php echo count($fisiere_test); ?>)</h2>
        <p><strong>Recomandare:</strong> Mută aceste fișiere în folderul <code>BackUp</code> sau șterge-le dacă nu mai sunt necesare.</p>
        <ul class="file-list">
            <?php foreach ($fisiere_test as $fisier): ?>
            <li class="<?php echo file_exists($root_dir . '/' . $fisier) ? 'exists' : 'missing'; ?>">
                <?php echo file_exists($root_dir . '/' . $fisier) ? '✅' : '❌'; ?>
                <?php echo htmlspecialchars($fisier); ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>📝 Acțiuni Recomandate</h2>
        <ol>
            <li>Verifică fișierele duplicate și șterge dublurile din folderele respective</li>
            <li>Mută fișierele de test în folderul <code>BackUp</code></li>
            <li>Verifică că toate fișierele esențiale există în root</li>
            <li>Descarcă XML-ul generat pentru referință</li>
        </ol>
    </div>
</body>
</html>

