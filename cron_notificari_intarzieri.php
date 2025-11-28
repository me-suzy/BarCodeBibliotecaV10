<?php
// cron_notificari_intarzieri.php - Script pentru trimiterea notificărilor email pentru întârzieri
// Rulează zilnic (cron job)

require_once 'config.php';

// Configurare email
define('EMAIL_FROM', 'YOUR-EMAIL@gmail.com');
define('EMAIL_PASSWORD', '<{[8_42Nw)(L(');
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', 587);

/**
 * Trimite email de notificare pentru întârzieri
 */
function trimiteNotificareIntarziere($pdo, $cod_cititor, $carte_titlu, $zile_intarziere) {
    // Obține datele cititorului
    $stmt = $pdo->prepare("SELECT nume, prenume, email FROM cititori WHERE cod_bare = ?");
    $stmt->execute([$cod_cititor]);
    $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cititor || empty($cititor['email'])) {
        return false; // Nu are email sau nu există
    }
    
    $to = $cititor['email'];
    $subject = "Notificare întârziere returnare carte - Biblioteca Academiei Iași";
    $message = "
    Bună ziua {$cititor['nume']} {$cititor['prenume']},
    
    Vă informăm că aveți o carte cu întârziere la returnare:
    
    📚 Titlu: {$carte_titlu}
    ⏰ Zile întârziere: {$zile_intarziere}
    
    Vă rugăm să returnați cartea cât mai curând posibil.
    
    Dacă nu returnați cartea, contul dvs. va fi blocat.
    
    Cu respect,
    Biblioteca Academiei Române - Iași
    ";
    
    $headers = "From: " . EMAIL_FROM . "\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Folosește funcția PHP mail() - pentru SMTP mai avansat, folosește PHPMailer
    return @mail($to, $subject, $message, $headers);
}

/**
 * Blochează utilizatorii cu întârzieri peste 14 zile
 */
function blocheazaUtilizatoriCuIntarzieri($pdo) {
    // Găsește utilizatorii cu cărți peste 14 zile întârziere
    $stmt = $pdo->prepare("
        SELECT DISTINCT i.cod_cititor, c.nume, c.prenume, c.email
        FROM imprumuturi i
        JOIN cititori c ON i.cod_cititor = c.cod_bare
        WHERE i.data_returnare IS NULL
        AND i.data_scadenta < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND c.blocat = 0
    ");
    $stmt->execute();
    $utilizatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($utilizatori as $utilizator) {
        // Blochează utilizatorul
        $stmt = $pdo->prepare("
            UPDATE cititori 
            SET blocat = 1, 
                motiv_blocare = 'Cont blocat din cauza cărților ne returnate'
            WHERE cod_bare = ?
        ");
        $stmt->execute([$utilizator['cod_cititor']]);
    }
    
    return count($utilizatori);
}

// Procesează notificările
try {
    // Găsește toate împrumuturile cu întârzieri (peste data scadenței)
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.cod_cititor,
            i.cod_carte,
            i.data_scadenta,
            DATEDIFF(CURDATE(), i.data_scadenta) as zile_intarziere,
            c.titlu as carte_titlu,
            cit.email,
            cit.nume,
            cit.prenume
        FROM imprumuturi i
        JOIN carti c ON i.cod_carte = c.cod_bare
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        WHERE i.data_returnare IS NULL
        AND i.data_scadenta < CURDATE()
        AND cit.blocat = 0
        ORDER BY i.data_scadenta ASC
    ");
    $stmt->execute();
    $imprumuturi_intarziate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notificari_trimise = 0;
    $notificari_esuate = 0;
    
    foreach ($imprumuturi_intarziate as $imprumut) {
        if (!empty($imprumut['email'])) {
            $rezultat = trimiteNotificareIntarziere(
                $pdo,
                $imprumut['cod_cititor'],
                $imprumut['carte_titlu'],
                $imprumut['zile_intarziere']
            );
            
            if ($rezultat) {
                $notificari_trimise++;
            } else {
                $notificari_esuate++;
            }
        }
    }
    
    // Blochează utilizatorii cu întârzieri peste 14 zile
    $utilizatori_blocati = blocheazaUtilizatoriCuIntarzieri($pdo);
    
    echo "Notificări trimise: $notificari_trimise\n";
    echo "Notificări eșuate: $notificari_esuate\n";
    echo "Utilizatori blocați: $utilizatori_blocati\n";
    
} catch (Exception $e) {
    echo "Eroare: " . $e->getMessage() . "\n";
}
?>

