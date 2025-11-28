<?php
// setup.php - Script de inițializare bază de date
echo "<h1>🚀 Inițializare Sistem Bibliotecă</h1>";

// Funcție pentru execuția query-urilor
function executeQuery($pdo, $query, $description) {
    try {
        $pdo->exec($query);
        echo "<p style='color: green;'>✅ $description</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Eroare la $description: " . $e->getMessage() . "</p>";
        return false;
    }
}

try {
    // Conectare fără bază de date specifică pentru creare
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Creează baza de date
    executeQuery($pdo, "CREATE DATABASE IF NOT EXISTS biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_romanian_ci;", "crearea bazei de date");

    // Selectează baza de date
    $pdo->exec("USE biblioteca");

    // Creează tabelul carti
    $query_carti = "
    CREATE TABLE IF NOT EXISTS carti (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cod_bare VARCHAR(50) UNIQUE NOT NULL,
        titlu VARCHAR(255) NOT NULL,
        autor VARCHAR(255),
        isbn VARCHAR(20),
        cota VARCHAR(50),
        raft VARCHAR(10),
        nivel VARCHAR(10),
        pozitie VARCHAR(10),
        locatie_completa VARCHAR(100) GENERATED ALWAYS AS (CONCAT('Raft ', raft, ' - Nivel ', nivel, ' - Poziția ', pozitie)) STORED,
        sectiune VARCHAR(50),
        observatii_locatie TEXT,
        data_adaugare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cod_bare (cod_bare),
        INDEX idx_locatie (raft, nivel, pozitie),
        INDEX idx_cota (cota)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
    ";
    executeQuery($pdo, $query_carti, "crearea tabelului carti");

    // Creează tabelul cititori
    $query_cititori = "
    CREATE TABLE IF NOT EXISTS cititori (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cod_bare VARCHAR(50) UNIQUE NOT NULL,
        nume VARCHAR(100) NOT NULL,
        prenume VARCHAR(100) NOT NULL,
        telefon VARCHAR(20),
        email VARCHAR(100),
        data_inregistrare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cod_bare (cod_bare)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
    ";
    executeQuery($pdo, $query_cititori, "crearea tabelului cititori");

    // Creează tabelul imprumuturi
    $query_imprumuturi = "
    CREATE TABLE IF NOT EXISTS imprumuturi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cod_cititor VARCHAR(50) NOT NULL,
        cod_carte VARCHAR(50) NOT NULL,
        data_imprumut TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_returnare TIMESTAMP NULL,
        status ENUM('activ', 'returnat') DEFAULT 'activ',
        FOREIGN KEY (cod_cititor) REFERENCES cititori(cod_bare),
        FOREIGN KEY (cod_carte) REFERENCES carti(cod_bare),
        INDEX idx_status (status),
        INDEX idx_cititor (cod_cititor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
    ";
    executeQuery($pdo, $query_imprumuturi, "crearea tabelului imprumuturi");

    // Creează tabelul istoric_locatii
    $query_istoric = "
    CREATE TABLE IF NOT EXISTS istoric_locatii (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cod_carte VARCHAR(50) NOT NULL,
        raft_vechi VARCHAR(10),
        nivel_vechi VARCHAR(10),
        pozitie_veche VARCHAR(10),
        raft_nou VARCHAR(10),
        nivel_nou VARCHAR(10),
        pozitie_noua VARCHAR(10),
        data_mutare TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        utilizator VARCHAR(100),
        motiv VARCHAR(255),
        FOREIGN KEY (cod_carte) REFERENCES carti(cod_bare)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;
    ";
    executeQuery($pdo, $query_istoric, "crearea tabelului istoric_locatii");

    // Inserează date de test pentru cărți
    $carti_data = [
        ['BOOK001', 'Amintiri din copilărie', 'Ion Creangă', '9789734640539', '821.135.1 CRE a', 'A', '1', '01', 'Literatură română'],
        ['BOOK002', 'Maitreyi', 'Mircea Eliade', '9789734640546', '821.135.1 ELI m', 'A', '1', '02', 'Literatură română'],
        ['BOOK003', 'Pădurea spânzuraților', 'Liviu Rebreanu', '9789734640553', '821.135.1 REB p', 'A', '1', '03', 'Literatură română'],
        ['BOOK004', 'Enigma Otiliei', 'George Călinescu', '9789734640560', '821.135.1 CAL e', 'A', '1', '04', 'Literatură română'],
        ['BOOK005', 'Moromeții', 'Marin Preda', '9789734640577', '821.135.1 PRE m', 'A', '1', '05', 'Literatură română']
    ];

    foreach ($carti_data as $carte) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO carti (cod_bare, titlu, autor, isbn, cota, raft, nivel, pozitie, sectiune) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($carte);
    }
    echo "<p style='color: green;'>✅ Date de test pentru cărți inserate</p>";

    // Inserează date de test pentru cititori
    $cititori_data = [
        ['USER001', 'Popescu', 'Ion', '0721123456', 'ion.popescu@email.ro'],
        ['USER002', 'Ionescu', 'Maria', '0722234567', 'maria.ionescu@email.ro'],
        ['USER003', 'Dumitrescu', 'Andrei', '0723345678', 'andrei.dumitrescu@email.ro']
    ];

    foreach ($cititori_data as $cititor) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO cititori (cod_bare, nume, prenume, telefon, email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($cititor);
    }
    echo "<p style='color: green;'>✅ Date de test pentru cititori inserate</p>";

    echo "<h2 style='color: green;'>🎉 Inițializare completă!</h2>";
    echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📚 Deschide aplicația</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Eroare generală: " . $e->getMessage() . "</p>";
}
?>
