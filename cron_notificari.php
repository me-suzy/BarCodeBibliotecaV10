<?php
/**
 * cron_notificari.php - Script automat pentru trimitere notificări
 * 
 * Rulează zilnic prin CRON sau Task Scheduler
 * Exemplu CRON: 0 8 * * * php /path/to/cron_notificari.php
 * 
 * Configurare în config.php:
 * - NOTIFICARI_ZILE_REMINDER: zile înainte de termen pentru reminder (default: 12-13)
 * - NOTIFICARI_ZILE_INTARZIERE: zile după care se trimite alertă (default: 14)
 * - NOTIFICARI_INTERVAL_ALERTA: interval între alertele repetate (default: 7 zile)
 * - ADMIN_EMAIL: email pentru raportul zilnic (opțional)
 */

// Previne timeout pentru scripturi lungi
set_time_limit(300);

require_once 'config.php';

// Include fișierele de email doar dacă există
$send_email_exists = file_exists(__DIR__ . '/send_email.php');
$templates_exists = file_exists(__DIR__ . '/functions_email_templates.php');
$sistem_exists = file_exists(__DIR__ . '/sistem_notificari.php');

if ($send_email_exists) require_once 'send_email.php';
if ($templates_exists) require_once 'functions_email_templates.php';
if ($sistem_exists) require_once 'sistem_notificari.php';

// Configurații default
$CONFIG_NOTIFICARI = [
    'zile_reminder' => defined('NOTIFICARI_ZILE_REMINDER') ? NOTIFICARI_ZILE_REMINDER : 12,
    'zile_reminder_max' => defined('NOTIFICARI_ZILE_REMINDER_MAX') ? NOTIFICARI_ZILE_REMINDER_MAX : 13,
    'zile_intarziere' => defined('NOTIFICARI_ZILE_INTARZIERE') ? NOTIFICARI_ZILE_INTARZIERE : 14,
    'interval_alerta' => defined('NOTIFICARI_INTERVAL_ALERTA') ? NOTIFICARI_INTERVAL_ALERTA : 7,
    'admin_email' => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : null,
    'log_file' => __DIR__ . '/logs/cron_notificari_' . date('Y-m-d') . '.log'
];

// Asigură că directorul logs există
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Funcție pentru logging
function cron_log($message, $type = 'INFO') {
    global $CONFIG_NOTIFICARI;
    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] [$type] $message\n";
    
    // Output în consolă
    echo $log_line;
    
    // Salvează și în fișier
    file_put_contents($CONFIG_NOTIFICARI['log_file'], $log_line, FILE_APPEND);
}

// Variabile pentru statistici
$stats = [
    'reminder_trimise' => 0,
    'reminder_erori' => 0,
    'alerte_trimise' => 0,
    'alerte_erori' => 0,
    'start_time' => microtime(true)
];

cron_log("=== CRON Notificări START ===");
cron_log("Configurație: Reminder la {$CONFIG_NOTIFICARI['zile_reminder']}-{$CONFIG_NOTIFICARI['zile_reminder_max']} zile, Alertă la >{$CONFIG_NOTIFICARI['zile_intarziere']} zile");

// 1. REMINDER RETURNARE (zile configurabile de la împrumut)
cron_log("--- Procesare REMINDER-e ---");

$stmt = $pdo->prepare("
    SELECT 
        i.id as imprumut_id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        c.titlu,
        c.autor,
        c.locatie_completa,
        cit.nume,
        cit.prenume,
        cit.email,
        cit.telefon,
        DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.data_returnare IS NULL
    AND DATEDIFF(NOW(), i.data_imprumut) BETWEEN ? AND ?
    AND NOT EXISTS (
        SELECT 1 FROM notificari 
        WHERE cod_cititor = i.cod_cititor 
        AND tip_notificare = 'reminder'
        AND DATE(data_trimitere) = CURDATE()
    )
");
$stmt->execute([$CONFIG_NOTIFICARI['zile_reminder'], $CONFIG_NOTIFICARI['zile_reminder_max']]);

// Grupează împrumuturile pe cititor
$imprumuturi_grupate = [];
while ($imp = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cod_cititor = $imp['cod_cititor'];
    if (!isset($imprumuturi_grupate[$cod_cititor])) {
        $imprumuturi_grupate[$cod_cititor] = [
            'cititor' => [
                'nume' => $imp['nume'],
                'prenume' => $imp['prenume'],
                'email' => $imp['email']
            ],
            'carti' => []
        ];
    }
    $imprumuturi_grupate[$cod_cititor]['carti'][] = [
        'titlu' => $imp['titlu'],
        'autor' => $imp['autor'],
        'cod_bare' => $imp['cod_carte'],
        'data_imprumut' => $imp['data_imprumut'],
        'locatie_completa' => $imp['locatie_completa']
    ];
}

foreach ($imprumuturi_grupate as $cod_cititor => $data) {
    if (!empty($data['cititor']['email'])) {
        // Calculează data returnare (14 zile de la prima carte)
        $data_returnare = date('Y-m-d', strtotime($data['carti'][0]['data_imprumut'] . ' +14 days'));
        
        // Verifică dacă funcția de email există
        $rezultat = ['success' => false, 'message' => 'Funcție email nedisponibilă'];
        
        if (function_exists('trimiteEmailPersonalizat') && isset($config_email)) {
            $rezultat = trimiteEmailPersonalizat(
                $data['cititor']['email'],
                'reminder',
                $data['cititor'],
                $data['carti'],
                $config_email,
                $data_returnare
            );
        } elseif (function_exists('trimiteEmailNotificare')) {
            // Fallback la funcția simplă de email
            $mesaj = "Dragă {$data['cititor']['prenume']},\n\n";
            $mesaj .= "Îți reamintim că termenul de returnare pentru cărțile împrumutate se apropie.\n";
            $mesaj .= "Te rugăm să le returnezi până la data de " . date('d.m.Y', strtotime($data_returnare)) . ".\n\n";
            $mesaj .= "Cărți de returnat:\n";
            foreach ($data['carti'] as $carte) {
                $mesaj .= "- {$carte['titlu']} ({$carte['autor']})\n";
            }
            $mesaj .= "\nMulțumim!\nBiblioteca";
            
            $rezultat = trimiteEmailNotificare($data['cititor']['email'], 'Reminder: Returnare cărți bibliotecă', $mesaj);
        }
        
        if ($rezultat['success']) {
            // Salvează în log
            try {
                $pdo->prepare("INSERT INTO notificari (cod_cititor, tip_notificare, canal, destinatar, subiect, mesaj, status) VALUES (?, 'reminder', 'email', ?, ?, ?, 'trimis')")
                    ->execute([
                        $cod_cititor,
                        $data['cititor']['email'],
                        'Reminder Returnare',
                        'Email reminder trimis cu succes'
                    ]);
            } catch (Exception $e) {
                cron_log("Nu s-a putut salva în log notificări: " . $e->getMessage(), 'WARN');
            }
            
            cron_log("✅ Reminder trimis: {$data['cititor']['nume']} {$data['cititor']['prenume']} - {$data['cititor']['email']} (" . count($data['carti']) . " cărți)");
            $stats['reminder_trimise']++;
        } else {
            cron_log("❌ EROARE trimitere reminder: {$data['cititor']['email']} - {$rezultat['message']}", 'ERROR');
            $stats['reminder_erori']++;
        }
    }
}
cron_log("Total reminder-e trimise: {$stats['reminder_trimise']}, erori: {$stats['reminder_erori']}");

// 2. ALERTE ÎNTÂRZIERE (configurabil zile)
cron_log("--- Procesare ALERTE ÎNTÂRZIERE ---");

$stmt = $pdo->prepare("
    SELECT 
        i.id as imprumut_id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        c.titlu,
        c.autor,
        cit.nume,
        cit.prenume,
        cit.email,
        cit.telefon,
        DATEDIFF(NOW(), i.data_imprumut) as zile_intarziere
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.data_returnare IS NULL 
    AND DATEDIFF(NOW(), i.data_imprumut) > ?
    AND (
        NOT EXISTS (
            SELECT 1 FROM notificari 
            WHERE cod_cititor = i.cod_cititor 
            AND tip_notificare = 'intarziere'
            AND DATE(data_trimitere) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        )
    )
");
$stmt->execute([$CONFIG_NOTIFICARI['zile_intarziere'], $CONFIG_NOTIFICARI['interval_alerta']]);

// Grupează împrumuturile pe cititor
$imprumuturi_intarziate_grupate = [];
while ($imp = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cod_cititor = $imp['cod_cititor'];
    if (!isset($imprumuturi_intarziate_grupate[$cod_cititor])) {
        $imprumuturi_intarziate_grupate[$cod_cititor] = [
            'cititor' => [
                'nume' => $imp['nume'],
                'prenume' => $imp['prenume'],
                'email' => $imp['email']
            ],
            'carti' => []
        ];
    }
    $imprumuturi_intarziate_grupate[$cod_cititor]['carti'][] = [
        'titlu' => $imp['titlu'],
        'autor' => $imp['autor'],
        'cod_bare' => $imp['cod_carte'],
        'data_imprumut' => $imp['data_imprumut'],
        'zile_intarziere' => $imp['zile_intarziere']
    ];
}

foreach ($imprumuturi_intarziate_grupate as $cod_cititor => $data) {
    if (!empty($data['cititor']['email'])) {
        // Calculează data returnare
        $data_returnare = date('Y-m-d', strtotime($data['carti'][0]['data_imprumut'] . ' +' . $CONFIG_NOTIFICARI['zile_intarziere'] . ' days'));
        $zile_intarziere = $data['carti'][0]['zile_intarziere'] ?? 0;
        
        // Verifică dacă funcția de email există
        $rezultat = ['success' => false, 'message' => 'Funcție email nedisponibilă'];
        
        if (function_exists('trimiteEmailPersonalizat') && isset($config_email)) {
            $rezultat = trimiteEmailPersonalizat(
                $data['cititor']['email'],
                'intarziere',
                $data['cititor'],
                $data['carti'],
                $config_email,
                $data_returnare
            );
        } elseif (function_exists('trimiteEmailNotificare')) {
            // Fallback la funcția simplă de email
            $mesaj = "Dragă {$data['cititor']['prenume']},\n\n";
            $mesaj .= "⚠️ ATENȚIE: Cărțile împrumutate au depășit termenul de returnare!\n\n";
            $mesaj .= "Ai întârziere de aproximativ $zile_intarziere zile.\n";
            $mesaj .= "Te rugăm să returnezi urgent următoarele cărți:\n\n";
            foreach ($data['carti'] as $carte) {
                $mesaj .= "- {$carte['titlu']} ({$carte['autor']}) - întârziere: {$carte['zile_intarziere']} zile\n";
            }
            $mesaj .= "\nAlți cititori așteaptă aceste cărți! Te rugăm să le returnezi cât mai curând.\n\n";
            $mesaj .= "Mulțumim pentru înțelegere!\nBiblioteca";
            
            $rezultat = trimiteEmailNotificare($data['cititor']['email'], '⚠️ URGENT: Cărți întârziate la bibliotecă', $mesaj);
        }
        
        if ($rezultat['success']) {
            // Salvează în log
            try {
                $pdo->prepare("INSERT INTO notificari (cod_cititor, tip_notificare, canal, destinatar, subiect, mesaj, status) VALUES (?, 'intarziere', 'email', ?, ?, ?, 'trimis')")
                    ->execute([
                        $cod_cititor,
                        $data['cititor']['email'],
                        'Alertă Întârziere',
                        'Email alertă întârziere trimis cu succes'
                    ]);
            } catch (Exception $e) {
                cron_log("Nu s-a putut salva în log notificări: " . $e->getMessage(), 'WARN');
            }
            
            cron_log("🚨 Alertă trimisă: {$data['cititor']['nume']} {$data['cititor']['prenume']} - {$data['cititor']['email']} (" . count($data['carti']) . " cărți, {$zile_intarziere} zile întârziere)");
            $stats['alerte_trimise']++;
        } else {
            cron_log("❌ EROARE trimitere alertă: {$data['cititor']['email']} - {$rezultat['message']}", 'ERROR');
            $stats['alerte_erori']++;
        }
    }
}
cron_log("Total alerte trimise: {$stats['alerte_trimise']}, erori: {$stats['alerte_erori']}");

// REZUMAT FINAL
$execution_time = round(microtime(true) - $stats['start_time'], 2);
$total_trimise = $stats['reminder_trimise'] + $stats['alerte_trimise'];
$total_erori = $stats['reminder_erori'] + $stats['alerte_erori'];

cron_log("=== CRON Notificări END ===");
cron_log("REZUMAT: {$stats['reminder_trimise']} reminder-e + {$stats['alerte_trimise']} alerte = $total_trimise notificări trimise");
if ($total_erori > 0) {
    cron_log("ERORI: $total_erori notificări eșuate", 'WARN');
}
cron_log("Timp execuție: {$execution_time}s");

// Trimite raport admin dacă e configurat
if (!empty($CONFIG_NOTIFICARI['admin_email']) && function_exists('trimiteEmailNotificare')) {
    $raport = "Raport zilnic notificări bibliotecă - " . date('d.m.Y H:i') . "\n\n";
    $raport .= "Reminder-e trimise: {$stats['reminder_trimise']}\n";
    $raport .= "Reminder-e eșuate: {$stats['reminder_erori']}\n";
    $raport .= "Alerte întârziere trimise: {$stats['alerte_trimise']}\n";
    $raport .= "Alerte eșuate: {$stats['alerte_erori']}\n";
    $raport .= "\nTOTAL: $total_trimise notificări trimise\n";
    $raport .= "Timp execuție: {$execution_time}s\n";
    
    if ($total_trimise > 0 || $total_erori > 0) {
        trimiteEmailNotificare(
            $CONFIG_NOTIFICARI['admin_email'],
            "📊 Raport notificări bibliotecă - " . date('d.m.Y'),
            $raport
        );
        cron_log("Raport trimis la admin: {$CONFIG_NOTIFICARI['admin_email']}");
    }
}
?>