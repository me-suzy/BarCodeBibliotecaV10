<?php
/**
 * Script pentru instalarea sistemului de statute cititori
 * 
 * Acest script:
 * 1. Creează tabela statute_cititori
 * 2. Inserează statutele predefinite
 * 3. Adaugă coloana statut în tabela cititori
 * 4. Actualizează statutul pentru cititorii existenți
 * 
 * Rulează acest script o singură dată pentru a configura sistemul de statute.
 */

require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalare Sistem Statute Cititori</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
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
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        .step h3 {
            margin-top: 0;
            color: #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Instalare Sistem Statute Cititori</h1>
        
        <?php
        $erori = [];
        $succese = [];
        
        try {
            // ============================================
            // PASUL 1: Creează tabelă pentru statute cititori
            // ============================================
            echo '<div class="step">';
            echo '<h3>Pasul 1: Creare tabelă statute_cititori</h3>';
            
            $query_tabel = "
            CREATE TABLE IF NOT EXISTS statute_cititori (
                cod_statut VARCHAR(2) PRIMARY KEY,
                nume_statut VARCHAR(100) NOT NULL,
                limita_depozit_carte INT DEFAULT 0,
                limita_depozit_periodice INT DEFAULT 0,
                limita_sala_lectura INT DEFAULT 0,
                limita_colectii_speciale INT DEFAULT 0,
                limita_totala INT DEFAULT 6,
                descriere TEXT,
                INDEX idx_cod_statut (cod_statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            $pdo->exec($query_tabel);
            $succese[] = "Tabela 'statute_cititori' a fost creată cu succes!";
            echo '<div class="success">✅ Tabela creată cu succes!</div>';
            echo '</div>';
            
            // ============================================
            // PASUL 2: Inserează statutele
            // ============================================
            echo '<div class="step">';
            echo '<h3>Pasul 2: Inserare statute predefinite</h3>';
            
            $statute = [
                ['11', 'Personal Științific Academie', 10, 'Personal științific al Academiei Române'],
                ['12', 'Bibliotecari BARI', 15, 'Bibliotecari din rețeaua BARI'],
                ['13', 'Angajați ARFI', 8, 'Angajați ARFI'],
                ['14', 'Nespecifici cu domiciliu în Iași', 4, 'Cititori nespecificați cu domiciliu în Iași'],
                ['15', 'Nespecifici fără domiciliu în Iași', 2, 'Cititori nespecificați fără domiciliu în Iași'],
                ['16', 'Personal departamente', 6, 'Personal din departamente'],
                ['17', 'ILL - Împrumut interbibliotecar', 20, 'Împrumut interbibliotecar']
            ];
            
            $stmt_insert = $pdo->prepare("
                INSERT INTO statute_cititori (cod_statut, nume_statut, limita_totala, descriere) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    nume_statut = VALUES(nume_statut),
                    limita_totala = VALUES(limita_totala),
                    descriere = VALUES(descriere)
            ");
            
            $inserate = 0;
            foreach ($statute as $statut) {
                $stmt_insert->execute($statut);
                $inserate++;
            }
            
            $succese[] = "Au fost inserate/actualizate $inserate statute!";
            echo '<div class="success">✅ ' . $inserate . ' statute inserate/actualizate!</div>';
            echo '</div>';
            
            // ============================================
            // PASUL 3: Adaugă coloană statut în cititori
            // ============================================
            echo '<div class="step">';
            echo '<h3>Pasul 3: Adăugare coloană statut în tabela cititori</h3>';
            
            // Verifică dacă coloana există deja
            $stmt_check = $pdo->query("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'cititori' 
                AND COLUMN_NAME = 'statut'
            ");
            $col_exists = $stmt_check->fetchColumn();
            
            if ($col_exists == 0) {
                $pdo->exec("
                    ALTER TABLE cititori 
                    ADD COLUMN statut VARCHAR(2) DEFAULT '14' AFTER cod_bare,
                    ADD INDEX idx_statut (statut)
                ");
                $succese[] = "Coloana 'statut' a fost adăugată în tabela 'cititori'!";
                echo '<div class="success">✅ Coloana adăugată cu succes!</div>';
            } else {
                echo '<div class="info">ℹ️ Coloana "statut" există deja în tabela "cititori".</div>';
            }
            echo '</div>';
            
            // ============================================
            // PASUL 4: Actualizează statutul pentru cititorii existenți
            // ============================================
            echo '<div class="step">';
            echo '<h3>Pasul 4: Actualizare statut pentru cititorii existenți</h3>';
            
            require_once 'functions_statute.php';
            
            // Obține toți cititorii
            $stmt_cititori = $pdo->query("SELECT cod_bare, statut FROM cititori");
            $cititori = $stmt_cititori->fetchAll(PDO::FETCH_ASSOC);
            
            $actualizati = 0;
            foreach ($cititori as $cititor) {
                $statut_nou = extrageStatutDinCodBare($cititor['cod_bare']);
                
                // Actualizează doar dacă statutul este diferit sau NULL
                if ($cititor['statut'] != $statut_nou || $cititor['statut'] === null) {
                    $stmt_update = $pdo->prepare("UPDATE cititori SET statut = ? WHERE cod_bare = ?");
                    $stmt_update->execute([$statut_nou, $cititor['cod_bare']]);
                    $actualizati++;
                }
            }
            
            $succese[] = "Au fost actualizați $actualizati cititori!";
            echo '<div class="success">✅ ' . $actualizati . ' cititori actualizați!</div>';
            echo '</div>';
            
            // ============================================
            // VERIFICARE FINALĂ
            // ============================================
            echo '<div class="step">';
            echo '<h3>Verificare finală</h3>';
            
            // Afișează statutele
            $stmt_statute = $pdo->query("SELECT * FROM statute_cititori ORDER BY cod_statut");
            $statute_db = $stmt_statute->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<h4>Statute configurate:</h4>';
            echo '<table>';
            echo '<tr><th>Cod</th><th>Nume Statut</th><th>Limită Totală</th><th>Descriere</th></tr>';
            foreach ($statute_db as $statut) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($statut['cod_statut']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($statut['nume_statut']) . '</td>';
                echo '<td>' . htmlspecialchars($statut['limita_totala']) . '</td>';
                echo '<td>' . htmlspecialchars($statut['descriere'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Distribuție cititori pe statut
            $stmt_dist = $pdo->query("
                SELECT statut, COUNT(*) as numar_cititori 
                FROM cititori 
                GROUP BY statut 
                ORDER BY statut
            ");
            $distributie = $stmt_dist->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<h4>Distribuție cititori pe statut:</h4>';
            echo '<table>';
            echo '<tr><th>Statut</th><th>Număr Cititori</th></tr>';
            foreach ($distributie as $dist) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($dist['statut']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($dist['numar_cititori']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            echo '</div>';
            
            // ============================================
            // REZUMAT
            // ============================================
            echo '<div class="success">';
            echo '<h3>✅ Instalare completă cu succes!</h3>';
            echo '<p>Sistemul de statute a fost instalat și configurat corect.</p>';
            echo '<ul>';
            foreach ($succese as $succes) {
                echo '<li>' . htmlspecialchars($succes) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="index.php" class="btn">← Înapoi la pagina principală</a></p>';
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<h3>❌ Eroare la instalare!</h3>';
            echo '<p><strong>Eroare:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>Fișier:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Linia:</strong> ' . htmlspecialchars($e->getLine()) . '</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

