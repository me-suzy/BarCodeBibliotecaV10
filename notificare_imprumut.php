<?php
// notificare_imprumut.php - Funcție pentru trimitere email automat la împrumut
// Folosește modelele din baza de date

function trimite_email_imprumut($pdo, $cod_cititor, $cod_carte) {
    try {
        require_once 'send_email.php';
        require_once 'functions_email_templates.php';
        require_once 'sistem_notificari.php'; // Pentru configurația email
        
        // Obține date cititor
        $stmt = $pdo->prepare("SELECT * FROM cititori WHERE cod_bare = ?");
        $stmt->execute([$cod_cititor]);
        $cititor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cititor || empty($cititor['email'])) {
            return false; // Nu are email
        }
        
        // Obține date carte
        $stmt = $pdo->prepare("SELECT * FROM carti WHERE cod_bare = ?");
        $stmt->execute([$cod_carte]);
        $carte = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$carte) {
            return false;
        }
        
        // ✅ NOU - Obține informații despre statutul cărții
        require_once 'functions_statute_carti.php';
        $info_statut_carte = getInfoStatutCarte($pdo, $carte['statut'] ?? '01');
        $nume_statut_carte = $info_statut_carte ? $info_statut_carte['nume_statut'] : '';
        
        // Obține durata de împrumut
        $durata_zile = getDurataImprumutCarte($pdo, $cod_carte);
        
        // Pregătește datele pentru email
        $carti_imprumutate = [[
            'titlu' => $carte['titlu'],
            'autor' => $carte['autor'] ?? '',
            'cod_bare' => $carte['cod_bare'],
            'data_imprumut' => date('Y-m-d'),
            'locatie_completa' => $carte['locatie_completa'] ?? '',
            'statut_carte' => $carte['statut'] ?? '01',
            'nume_statut_carte' => $nume_statut_carte,
            'durata_zile' => $durata_zile
        ]];
        
        // Trimite email folosind funcția personalizată
        $rezultat = trimiteEmailPersonalizat(
            $cititor['email'],
            'imprumut',
            $cititor,
            $carti_imprumutate,
            $config_email
        );
        
        if ($rezultat['success']) {
            // Salvează în log notificări (dacă tabelul există)
            try {
                $check = $pdo->query("SHOW TABLES LIKE 'notificari'")->fetch();
                if ($check) {
                    $pdo->prepare("INSERT INTO notificari (cod_cititor, tip_notificare, canal, destinatar, subiect, mesaj, status) VALUES (?, 'imprumut', 'email', ?, ?, ?, 'trimis')")
                        ->execute([
                            $cod_cititor,
                            $cititor['email'],
                            'Confirmare Împrumut',
                            'Email confirmare împrumut trimis cu succes'
                        ]);
                }
            } catch (PDOException $e) {
                // Ignoră eroarea de log
            }
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Eroare trimitere email împrumut: " . $e->getMessage());
        return false;
    }
}
?>