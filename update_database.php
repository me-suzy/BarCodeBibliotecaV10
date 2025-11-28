<?php
/**
 * Script pentru actualizarea bazei de date cu câmpurile necesare pentru sistemul de sesiuni
 * Rulează acest fișier în browser: http://localhost/update_database.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizare Baza de Date - Sesiuni</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        .sql-code {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 10px 0;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Actualizare Baza de Date - Sistem Sesiuni</h1>

        <?php
        $erori = [];
        $succese = [];
        $info = [];

        try {
            // 1. Adaugă câmpul blocat în tabelul cititori
            $info[] = "1. Verificare și adăugare câmpuri în tabelul cititori...";
            
            // Verifică dacă câmpul blocat există deja
            $stmt = $pdo->query("SHOW COLUMNS FROM cititori LIKE 'blocat'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `cititori` 
                    ADD COLUMN `blocat` TINYINT(1) DEFAULT 0 COMMENT '0=activ, 1=blocat (din cauza întârzierilor sau alte motive)' AFTER `email`,
                    ADD COLUMN `motiv_blocare` VARCHAR(255) DEFAULT NULL COMMENT 'Motivul blocării' AFTER `blocat`");
                $succese[] = "✅ Câmpurile 'blocat' și 'motiv_blocare' au fost adăugate în tabelul cititori";
            } else {
                $info[] = "ℹ️ Câmpul 'blocat' există deja în tabelul cititori";
            }

            // 2. Adaugă câmpul data_scadenta în tabelul imprumuturi
            $info[] = "2. Verificare și adăugare câmp data_scadenta în tabelul imprumuturi...";
            
            $stmt = $pdo->query("SHOW COLUMNS FROM imprumuturi LIKE 'data_scadenta'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `imprumuturi` 
                    ADD COLUMN `data_scadenta` DATE DEFAULT NULL COMMENT 'Data scadenței împrumutului (14 zile de la data_imprumut)' AFTER `data_imprumut`");
                $succese[] = "✅ Câmpul 'data_scadenta' a fost adăugat în tabelul imprumuturi";
                
                // Actualizează data_scadenta pentru împrumuturile existente
                $pdo->exec("UPDATE `imprumuturi` 
                    SET `data_scadenta` = DATE_ADD(DATE(`data_imprumut`), INTERVAL 14 DAY)
                    WHERE `data_scadenta` IS NULL AND `data_returnare` IS NULL");
                $succese[] = "✅ Data scadenței a fost calculată pentru împrumuturile existente";
            } else {
                $info[] = "ℹ️ Câmpul 'data_scadenta' există deja în tabelul imprumuturi";
            }

            // 3. Creează tabelul sesiuni_utilizatori
            $info[] = "3. Verificare și creare tabel sesiuni_utilizatori...";
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'sesiuni_utilizatori'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `sesiuni_utilizatori` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `cod_cititor` VARCHAR(50) NOT NULL,
                  `timestamp_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Momentul când utilizatorul a fost scanat',
                  `timestamp_ultima_actiune` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Momentul ultimei acțiuni (scanare carte)',
                  `status` ENUM('activ', 'expirat', 'inchis') DEFAULT 'activ' COMMENT 'Statusul sesiunii',
                  `numar_carti_scanate` INT DEFAULT 0 COMMENT 'Numărul de cărți scanate în această sesiune',
                  INDEX `idx_cititor` (`cod_cititor`),
                  INDEX `idx_status` (`status`),
                  INDEX `idx_timestamp` (`timestamp_ultima_actiune`),
                  FOREIGN KEY (`cod_cititor`) REFERENCES `cititori`(`cod_bare`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci");
                $succese[] = "✅ Tabelul 'sesiuni_utilizatori' a fost creat cu succes";
            } else {
                $info[] = "ℹ️ Tabelul 'sesiuni_utilizatori' există deja";
            }

            // 4. Creează tabelul tracking_sesiuni
            $info[] = "4. Verificare și creare tabel tracking_sesiuni...";
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'tracking_sesiuni'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tracking_sesiuni` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `cod_cititor` VARCHAR(50) NOT NULL,
                  `tip_actiune` ENUM('scanare_permis', 'scanare_carte_imprumut', 'scanare_carte_returnare', 'sesiune_expirata', 'sesiune_inchisa') NOT NULL,
                  `cod_carte` VARCHAR(50) DEFAULT NULL COMMENT 'NULL pentru scanare_permis, codul cărții pentru scanare/returnare',
                  `data_ora` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data și ora exactă a acțiunii',
                  `data` DATE GENERATED ALWAYS AS (DATE(data_ora)) STORED COMMENT 'Data pentru rapoarte zilnice',
                  `ora` TIME GENERATED ALWAYS AS (TIME(data_ora)) STORED COMMENT 'Ora pentru rapoarte',
                  `sesiune_id` INT DEFAULT NULL COMMENT 'ID-ul sesiunii din sesiuni_utilizatori',
                  `detalii` TEXT DEFAULT NULL COMMENT 'Detalii suplimentare (JSON sau text)',
                  INDEX `idx_cititor` (`cod_cititor`),
                  INDEX `idx_data_ora` (`data_ora`),
                  INDEX `idx_data` (`data`),
                  INDEX `idx_tip_actiune` (`tip_actiune`),
                  INDEX `idx_sesiune` (`sesiune_id`),
                  FOREIGN KEY (`cod_cititor`) REFERENCES `cititori`(`cod_bare`) ON DELETE CASCADE,
                  FOREIGN KEY (`sesiune_id`) REFERENCES `sesiuni_utilizatori`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci");
                $succese[] = "✅ Tabelul 'tracking_sesiuni' a fost creat cu succes";
            } else {
                $info[] = "ℹ️ Tabelul 'tracking_sesiuni' există deja";
            }

            // 5. Verifică dacă toate câmpurile necesare există
            $info[] = "5. Verificare finală...";
            
            // Verifică structura tabelului cititori
            $stmt = $pdo->query("SHOW COLUMNS FROM cititori");
            $colums_cititori = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $necesare_cititori = ['blocat', 'motiv_blocare'];
            $lipsesc_cititori = array_diff($necesare_cititori, $colums_cititori);
            
            if (empty($lipsesc_cititori)) {
                $succese[] = "✅ Toate câmpurile necesare există în tabelul cititori";
            } else {
                $erori[] = "❌ Lipsesc câmpurile: " . implode(', ', $lipsesc_cititori) . " din tabelul cititori";
            }
            
            // Verifică structura tabelului imprumuturi
            $stmt = $pdo->query("SHOW COLUMNS FROM imprumuturi");
            $colums_imprumuturi = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $necesare_imprumuturi = ['data_scadenta'];
            $lipsesc_imprumuturi = array_diff($necesare_imprumuturi, $colums_imprumuturi);
            
            if (empty($lipsesc_imprumuturi)) {
                $succese[] = "✅ Toate câmpurile necesare există în tabelul imprumuturi";
            } else {
                $erori[] = "❌ Lipsesc câmpurile: " . implode(', ', $lipsesc_imprumuturi) . " din tabelul imprumuturi";
            }
            
            // Verifică dacă tabelul sesiuni_utilizatori există
            $stmt = $pdo->query("SHOW TABLES LIKE 'sesiuni_utilizatori'");
            if ($stmt->rowCount() > 0) {
                $succese[] = "✅ Tabelul sesiuni_utilizatori există";
            } else {
                $erori[] = "❌ Tabelul sesiuni_utilizatori nu există";
            }
            
            // Verifică dacă tabelul tracking_sesiuni există
            $stmt = $pdo->query("SHOW TABLES LIKE 'tracking_sesiuni'");
            if ($stmt->rowCount() > 0) {
                $succese[] = "✅ Tabelul tracking_sesiuni există";
            } else {
                $erori[] = "❌ Tabelul tracking_sesiuni nu există";
            }

        } catch (PDOException $e) {
            $erori[] = "❌ Eroare PDO: " . $e->getMessage();
        } catch (Exception $e) {
            $erori[] = "❌ Eroare: " . $e->getMessage();
        }

        // Afișează rezultatele
        if (!empty($info)) {
            foreach ($info as $msg) {
                echo "<div class='info'>$msg</div>";
            }
        }

        if (!empty($succese)) {
            foreach ($succese as $msg) {
                echo "<div class='success'>$msg</div>";
            }
        }

        if (!empty($erori)) {
            foreach ($erori as $msg) {
                echo "<div class='error'>$msg</div>";
            }
        }

        if (empty($erori) && !empty($succese)) {
            echo "<div class='success'><strong>🎉 Actualizarea bazei de date a fost finalizată cu succes!</strong></div>";
            echo "<p>Baza de date este acum pregătită pentru sistemul de sesiuni.</p>";
        } elseif (!empty($erori)) {
            echo "<div class='error'><strong>⚠️ Au apărut erori în timpul actualizării.</strong></div>";
            echo "<p>Te rugăm să verifici erorile de mai sus și să încerci din nou.</p>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
            <h3>📋 Structura Actualizată:</h3>
            <div class="sql-code">
                <strong>Tabel cititori:</strong><br>
                - blocat (TINYINT) - Status blocare utilizator<br>
                - motiv_blocare (VARCHAR) - Motivul blocării<br><br>
                
                <strong>Tabel imprumuturi:</strong><br>
                - data_scadenta (DATE) - Data scadenței (14 zile de la împrumut)<br><br>
                
                <strong>Tabel nou: sesiuni_utilizatori</strong><br>
                - id (INT) - ID sesiune<br>
                - cod_cititor (VARCHAR) - Codul cititorului<br>
                - timestamp_start (TIMESTAMP) - Momentul scanării utilizatorului<br>
                - timestamp_ultima_actiune (TIMESTAMP) - Ultima acțiune<br>
                - status (ENUM) - Status sesiune (activ/expirat/inchis)<br>
                - numar_carti_scanate (INT) - Număr cărți scanate în sesiune<br><br>
                
                <strong>Tabel nou: tracking_sesiuni</strong><br>
                - id (INT) - ID tracking<br>
                - cod_cititor (VARCHAR) - Codul cititorului<br>
                - tip_actiune (ENUM) - Tip acțiune (scanare_permis, scanare_carte_imprumut, scanare_carte_returnare, etc.)<br>
                - cod_carte (VARCHAR) - Codul cărții (NULL pentru scanare permis)<br>
                - data_ora (TIMESTAMP) - Data și ora exactă<br>
                - data (DATE) - Data generată automat<br>
                - ora (TIME) - Ora generată automat<br>
                - sesiune_id (INT) - ID sesiune<br>
                - detalii (TEXT) - Detalii suplimentare (JSON)
            </div>
        </div>

        <div style="margin-top: 20px;">
            <a href="index.php" style="text-decoration: none;">
                <button>➡️ Mergi la Pagina Principală</button>
            </a>
            <a href="index.php" style="text-decoration: none; margin-left: 10px;">
                <button style="background: #6c757d;">🏠 Acasă</button>
            </a>
        </div>
    </div>
</body>
</html>

