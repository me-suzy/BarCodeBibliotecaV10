<?php
/**
 * Export date în format Excel (CSV) sau PDF
 * Suportă export pentru: cititori, carti, imprumuturi, intarzieri
 */
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$tip_export = isset($_GET['tip']) ? $_GET['tip'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

$valid_types = ['cititori', 'carti', 'imprumuturi', 'intarzieri'];
$valid_formats = ['csv', 'excel'];

if (!in_array($tip_export, $valid_types)) {
    die('❌ Tip de export invalid');
}

if (!in_array($format, $valid_formats)) {
    $format = 'csv';
}

// Funcție pentru formatarea datelor pentru CSV
function formatCSV($value, $forceText = false) {
    $value = str_replace('"', '""', $value);
    // Forțează format text pentru numere lungi (coduri de bare) pentru a preveni notația științifică
    if ($forceText && is_numeric($value) && strlen($value) > 10) {
        return '="' . $value . '"';
    }
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        $value = '"' . $value . '"';
    }
    return $value;
}

// Funcție specială pentru coduri de bare - previne notația științifică în Excel
function formatBarcode($value) {
    return '="' . $value . '"';
}

// Setează header-ele pentru descărcare
$filename = $tip_export . '_' . date('Y-m-d_H-i-s');
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    // BOM pentru UTF-8 în Excel
    echo "\xEF\xBB\xBF";
} else {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    // BOM pentru UTF-8
    echo "\xEF\xBB\xBF";
}

// Export CITITORI
if ($tip_export === 'cititori') {
    $stmt = $pdo->query("
        SELECT 
            c.cod_bare,
            c.nume,
            c.prenume,
            c.telefon,
            c.email,
            c.data_inregistrare,
            (SELECT COUNT(*) FROM imprumuturi i WHERE i.cod_cititor = c.cod_bare AND i.data_returnare IS NULL) as imprumuturi_active,
            (SELECT COUNT(*) FROM imprumuturi i WHERE i.cod_cititor = c.cod_bare) as total_imprumuturi
        FROM cititori c
        ORDER BY c.nume, c.prenume
    ");
    $data = $stmt->fetchAll();
    
    // Header
    echo "Cod Bare,Nume,Prenume,Telefon,Email,Data Înregistrare,Împrumuturi Active,Total Împrumuturi\n";
    
    // Rows
    foreach ($data as $row) {
        echo formatBarcode($row['cod_bare']) . ',';
        echo formatCSV($row['nume']) . ',';
        echo formatCSV($row['prenume']) . ',';
        echo formatCSV($row['telefon'] ?: '-') . ',';
        echo formatCSV($row['email'] ?: '-') . ',';
        echo formatCSV(date('d.m.Y', strtotime($row['data_inregistrare']))) . ',';
        echo $row['imprumuturi_active'] . ',';
        echo $row['total_imprumuturi'] . "\n";
    }
}

// Export CĂRȚI
elseif ($tip_export === 'carti') {
    $stmt = $pdo->query("
        SELECT 
            c.cod_bare,
            c.titlu,
            c.autor,
            c.isbn,
            c.cota,
            c.raft,
            c.nivel,
            c.pozitie,
            c.locatie_completa,
            c.sectiune,
            c.data_adaugare,
            CASE 
                WHEN EXISTS (SELECT 1 FROM imprumuturi i WHERE i.cod_carte = c.cod_bare AND i.data_returnare IS NULL) 
                THEN 'Împrumutată'
                ELSE 'Disponibilă'
            END as status,
            (SELECT COUNT(*) FROM imprumuturi i WHERE i.cod_carte = c.cod_bare) as total_imprumuturi
        FROM carti c
        ORDER BY c.titlu
    ");
    $data = $stmt->fetchAll();
    
    // Header
    echo "Cod Bare,Titlu,Autor,ISBN,Cotă,Raft,Nivel,Poziție,Locație,Secțiune,Data Adăugare,Status,Total Împrumuturi\n";
    
    // Rows
    foreach ($data as $row) {
        echo formatBarcode($row['cod_bare']) . ',';
        echo formatCSV($row['titlu']) . ',';
        echo formatCSV($row['autor'] ?: '-') . ',';
        echo formatBarcode($row['isbn'] ?: '-') . ',';
        echo formatCSV($row['cota'] ?: '-') . ',';
        echo formatCSV($row['raft'] ?: '-') . ',';
        echo formatCSV($row['nivel'] ?: '-') . ',';
        echo formatCSV($row['pozitie'] ?: '-') . ',';
        echo formatCSV($row['locatie_completa'] ?: '-') . ',';
        echo formatCSV($row['sectiune'] ?: '-') . ',';
        echo formatCSV(date('d.m.Y', strtotime($row['data_adaugare']))) . ',';
        echo formatCSV($row['status']) . ',';
        echo $row['total_imprumuturi'] . "\n";
    }
}

// Export ÎMPRUMUTURI ACTIVE
elseif ($tip_export === 'imprumuturi') {
    $stmt = $pdo->query("
        SELECT 
            i.data_imprumut,
            cit.cod_bare as cod_cititor,
            cit.nume,
            cit.prenume,
            cit.telefon,
            cit.email,
            c.cod_bare as cod_carte,
            c.titlu,
            c.autor,
            c.cota,
            c.locatie_completa,
            DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
        FROM imprumuturi i
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        JOIN carti c ON i.cod_carte = c.cod_bare
        WHERE i.data_returnare IS NULL
        ORDER BY i.data_imprumut ASC
    ");
    $data = $stmt->fetchAll();
    
    // Header
    echo "Data Împrumut,Cod Cititor,Nume Cititor,Prenume,Telefon,Email,Cod Carte,Titlu,Autor,Cotă,Locație,Zile de la Împrumut\n";
    
    // Rows
    foreach ($data as $row) {
        echo formatCSV(date('d.m.Y', strtotime($row['data_imprumut']))) . ',';
        echo formatBarcode($row['cod_cititor']) . ',';
        echo formatCSV($row['nume']) . ',';
        echo formatCSV($row['prenume']) . ',';
        echo formatCSV($row['telefon'] ?: '-') . ',';
        echo formatCSV($row['email'] ?: '-') . ',';
        echo formatBarcode($row['cod_carte']) . ',';
        echo formatCSV($row['titlu']) . ',';
        echo formatCSV($row['autor'] ?: '-') . ',';
        echo formatCSV($row['cota'] ?: '-') . ',';
        echo formatCSV($row['locatie_completa'] ?: '-') . ',';
        echo $row['zile_imprumut'] . "\n";
    }
}

// Export ÎNTÂRZIERI
elseif ($tip_export === 'intarzieri') {
    $zile_limita = 14;
    $stmt = $pdo->prepare("
        SELECT 
            i.data_imprumut,
            cit.cod_bare as cod_cititor,
            cit.nume,
            cit.prenume,
            cit.telefon,
            cit.email,
            c.cod_bare as cod_carte,
            c.titlu,
            c.autor,
            c.cota,
            DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut,
            DATEDIFF(NOW(), i.data_imprumut) - ? as zile_intarziere
        FROM imprumuturi i
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        JOIN carti c ON i.cod_carte = c.cod_bare
        WHERE i.data_returnare IS NULL
        AND DATEDIFF(NOW(), i.data_imprumut) > ?
        ORDER BY zile_imprumut DESC
    ");
    $stmt->execute([$zile_limita, $zile_limita]);
    $data = $stmt->fetchAll();
    
    // Header
    echo "Data Împrumut,Cod Cititor,Nume Cititor,Prenume,Telefon,Email,Cod Carte,Titlu,Autor,Cotă,Zile de la Împrumut,Zile Întârziere\n";
    
    // Rows
    foreach ($data as $row) {
        echo formatCSV(date('d.m.Y', strtotime($row['data_imprumut']))) . ',';
        echo formatBarcode($row['cod_cititor']) . ',';
        echo formatCSV($row['nume']) . ',';
        echo formatCSV($row['prenume']) . ',';
        echo formatCSV($row['telefon'] ?: '-') . ',';
        echo formatCSV($row['email'] ?: '-') . ',';
        echo formatBarcode($row['cod_carte']) . ',';
        echo formatCSV($row['titlu']) . ',';
        echo formatCSV($row['autor'] ?: '-') . ',';
        echo formatCSV($row['cota'] ?: '-') . ',';
        echo $row['zile_imprumut'] . ',';
        echo $row['zile_intarziere'] . "\n";
    }
}

