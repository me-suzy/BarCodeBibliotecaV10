<?php
// export_excel.php - Export date în format Excel (CSV)
session_start();
require_once 'config.php';
require_once 'auth_check.php';

$tip = $_GET['tip'] ?? 'active';

// Setează headers pentru download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="biblioteca_' . $tip . '_' . date('Y-m-d') . '.csv"');

// Output stream
$output = fopen('php://output', 'w');

// BOM pentru UTF-8 (pentru Excel să recunoască diacriticele)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($tip === 'active') {
    // Export împrumuturi active
    fputcsv($output, ['Cod Cititor', 'Nume', 'Prenume', 'Telefon', 'Email', 'Cod Carte', 'Titlu', 'Autor', 'Data Împrumut', 'Zile Împrumut', 'Status']);
    
    $stmt = $pdo->query("
        SELECT 
            i.cod_cititor,
            cit.nume,
            cit.prenume,
            cit.telefon,
            cit.email,
            i.cod_carte,
            c.titlu,
            c.autor,
            i.data_imprumut,
            DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut,
            CASE 
                WHEN DATEDIFF(NOW(), i.data_imprumut) > 30 THEN 'Întârziere mare'
                WHEN DATEDIFF(NOW(), i.data_imprumut) > 14 THEN 'Întârziere'
                ELSE 'OK'
            END as status
        FROM imprumuturi i
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        JOIN carti c ON i.cod_carte = c.cod_bare
        WHERE i.status = 'activ'
        ORDER BY i.data_imprumut DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
} elseif ($tip === 'istoric') {
    // Export istoric complet
    fputcsv($output, ['Cod Cititor', 'Nume', 'Prenume', 'Cod Carte', 'Titlu', 'Autor', 'Data Împrumut', 'Data Returnare', 'Zile Total', 'Status']);
    
    $stmt = $pdo->query("
        SELECT 
            i.cod_cititor,
            cit.nume,
            cit.prenume,
            i.cod_carte,
            c.titlu,
            c.autor,
            i.data_imprumut,
            i.data_returnare,
            DATEDIFF(COALESCE(i.data_returnare, NOW()), i.data_imprumut) as zile_total,
            i.status
        FROM imprumuturi i
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        JOIN carti c ON i.cod_carte = c.cod_bare
        ORDER BY i.data_imprumut DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
} elseif ($tip === 'carti') {
    // Export lista cărți
    fputcsv($output, ['Cod Bare', 'Titlu', 'Autor', 'ISBN', 'Cotă', 'Secțiune', 'Locație', 'Data Adăugare']);
    
    $stmt = $pdo->query("
        SELECT 
            cod_bare,
            titlu,
            autor,
            isbn,
            cota,
            sectiune,
            locatie_completa,
            data_adaugare
        FROM carti
        ORDER BY titlu ASC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
} elseif ($tip === 'cititori') {
    // Export lista cititori
    fputcsv($output, ['Cod Bare', 'Nume', 'Prenume', 'Telefon', 'Email', 'Data Înregistrare']);
    
    $stmt = $pdo->query("
        SELECT 
            cod_bare,
            nume,
            prenume,
            telefon,
            email,
            data_inregistrare
        FROM cititori
        ORDER BY nume, prenume ASC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
?>