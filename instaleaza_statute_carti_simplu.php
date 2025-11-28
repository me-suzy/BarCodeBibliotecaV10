<?php
/**
 * Script simplificat pentru instalarea sistemului de statute cărți
 * Rulează acest script o singură dată pentru a configura sistemul
 */

require_once 'config.php';

echo "🔧 Instalare sistem statute cărți...\n\n";

try {
    // Activează buffering-ul pentru PDO
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    // Creează tabelul statute_carti
    echo "📋 Creare tabel statute_carti...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS statute_carti (
            cod_statut VARCHAR(2) PRIMARY KEY,
            nume_statut VARCHAR(100) NOT NULL,
            poate_imprumuta_acasa BOOLEAN DEFAULT FALSE,
            poate_imprumuta_sala BOOLEAN DEFAULT FALSE,
            durata_imprumut_zile INT DEFAULT 14,
            descriere TEXT,
            INDEX idx_cod_statut (cod_statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabelul statute_carti creat!\n";
    
    // Inserează statutele
    echo "📝 Inserare statute...\n";
    $statute = [
        ['01', 'Pentru împrumut acasă', 1, 0, 14, 'Se poate împrumuta acasă - durată standard 14 zile'],
        ['02', 'Se împr. numai la sală', 0, 1, 0, 'Se imprumuta doar la sala de lectură - nu se poate lua acasă'],
        ['03', 'Colecții speciale - sală 1 zi', 0, 1, 1, 'Colecții speciale - se imprumuta doar sala pentru 1 zi'],
        ['04', 'Nu există fizic', 0, 0, 0, 'Nu exista fizic cartea - deci nu se poate împrumuta'],
        ['05', 'Împrumut scurt 5 zile', 1, 0, 5, 'Se imprumuta doar 5 zile - împrumut scurt'],
        ['06', 'Regim special 6 luni - 1 an', 1, 0, 180, 'Regim special pentru cărți - se pot împrumuta pe o perioadă mare de timp 6 luni, maxim 1 an'],
        ['08', 'Ne circulat', 0, 0, 0, 'Nu se imprumuta - carte ne circulată'],
        ['90', 'În achiziție - depozit', 0, 0, 0, 'Cartea a fost primita, dar e inca in depozit, nu a ajuns la raft']
    ];
    
    $stmt_insert = $pdo->prepare("
        INSERT INTO statute_carti (cod_statut, nume_statut, poate_imprumuta_acasa, poate_imprumuta_sala, durata_imprumut_zile, descriere) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            nume_statut = VALUES(nume_statut),
            poate_imprumuta_acasa = VALUES(poate_imprumuta_acasa),
            poate_imprumuta_sala = VALUES(poate_imprumuta_sala),
            durata_imprumut_zile = VALUES(durata_imprumut_zile),
            descriere = VALUES(descriere)
    ");
    
    foreach ($statute as $statut) {
        $stmt_insert->execute($statut);
    }
    $stmt_insert = null;
    echo "✅ Statute inserate!\n";
    
    // Adaugă coloana statut în tabelul carti
    echo "🔧 Adăugare coloană statut în tabelul carti...\n";
    try {
        $pdo->exec("ALTER TABLE carti ADD COLUMN statut VARCHAR(2) DEFAULT '01' AFTER cod_bare");
        echo "✅ Coloana statut adăugată!\n";
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Duplicate column') !== false || 
            stripos($e->getMessage(), 'există deja') !== false) {
            echo "ℹ️ Coloana statut există deja.\n";
        } else {
            throw $e;
        }
    }
    
    // Adaugă index
    try {
        $pdo->exec("ALTER TABLE carti ADD INDEX idx_statut_carte (statut)");
    } catch (PDOException $e) {
        // Ignoră dacă index-ul există deja
    }
    
    // Actualizează cărțile existente
    echo "🔄 Actualizare cărți existente...\n";
    $pdo->exec("UPDATE carti SET statut = '01' WHERE statut IS NULL OR statut = ''");
    echo "✅ Cărți actualizate!\n\n";
    
    // Verifică statutele
    echo "📊 Statute configurate:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s | %-40s | %-15s | %s\n", "COD", "NUME STATUT", "DURATA (zile)", "DESCRIERE");
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $pdo->query("SELECT * FROM statute_carti ORDER BY cod_statut");
    $statute_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null;
    
    foreach ($statute_db as $statut) {
        printf(
            "%-5s | %-40s | %-15s | %s\n",
            $statut['cod_statut'],
            $statut['nume_statut'],
            $statut['durata_imprumut_zile'],
            substr($statut['descriere'] ?? '', 0, 30)
        );
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Verifică câte cărți au fiecare statut
    $stmt = $pdo->query("
        SELECT statut, COUNT(*) as numar 
        FROM carti 
        GROUP BY statut 
        ORDER BY statut
    ");
    $distributie = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null;
    
    echo "📚 Distribuție cărți pe statut:\n";
    foreach ($distributie as $row) {
        echo "  - Statut {$row['statut']}: {$row['numar']} cărți\n";
    }
    
    echo "\n✅ Instalare completă!\n";
    
} catch (Exception $e) {
    echo "❌ Eroare: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

