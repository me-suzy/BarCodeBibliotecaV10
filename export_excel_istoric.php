<?php
// export_excel_istoric.php - Export istoric depozit în format Excel (CSV)
session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Obține ID-urile selectate sau parametrii de căutare
$selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
$search_term = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$data_start = isset($_POST['data_start']) ? trim($_POST['data_start']) : (isset($_GET['data_start']) ? trim($_GET['data_start']) : '');
$data_end = isset($_POST['data_end']) ? trim($_POST['data_end']) : (isset($_GET['data_end']) ? trim($_GET['data_end']) : '');

// Construiește query-ul
$query = "
    SELECT 
        i.id,
        i.cod_cititor,
        i.cod_carte,
        i.data_imprumut,
        i.data_scadenta,
        i.status_depozit,
        i.data_returnare,
        c.titlu,
        c.autor,
        c.cod_bare,
        c.cota,
        cit.nume,
        cit.prenume,
        cit.telefon,
        cit.email,
        TIMESTAMPDIFF(DAY, i.data_imprumut, COALESCE(i.data_returnare, NOW())) as zile_total
    FROM imprumuturi i
    JOIN carti c ON i.cod_carte = c.cod_bare
    JOIN cititori cit ON i.cod_cititor = cit.cod_bare
    WHERE i.status_depozit IN ('preluata', 'livrata')
";

$params = [];

// Dacă sunt selectate checkbox-uri, folosește doar acele ID-uri
if (!empty($selected_ids) && is_array($selected_ids)) {
    $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
    $query .= " AND i.id IN ($placeholders)";
    $params = array_merge($params, $selected_ids);
} else {
    // Altfel, folosește filtrele de căutare
    if (!empty($search_term)) {
        $query .= " AND (
            c.titlu LIKE ? OR 
            c.autor LIKE ? OR 
            c.cod_bare LIKE ? OR 
            c.cota LIKE ? OR 
            cit.nume LIKE ? OR 
            cit.prenume LIKE ? OR 
            cit.telefon LIKE ? OR
            CONCAT(cit.nume, ' ', cit.prenume) LIKE ?
        )";
        $search_pattern = '%' . $search_term . '%';
        $params = array_merge($params, [$search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
    }

    if (!empty($data_start)) {
        $query .= " AND DATE(i.data_imprumut) >= ?";
        $params[] = $data_start;
    }
    if (!empty($data_end)) {
        $query .= " AND DATE(i.data_imprumut) <= ?";
        $params[] = $data_end;
    }
}

$query .= " ORDER BY 
    CASE i.status_depozit 
        WHEN 'livrata' THEN 1 
        WHEN 'preluata' THEN 2 
        ELSE 3 
    END,
    i.data_imprumut DESC";

// Setează headers pentru download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="istoric_depozit_' . date('Y-m-d') . '.csv"');

// Output stream
$output = fopen('php://output', 'w');

// BOM pentru UTF-8 (pentru Excel să recunoască diacriticele)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header-uri
fputcsv($output, [
    'ID',
    'Titlu',
    'Autor',
    'Cotă',
    'Cod Bare',
    'Cititor',
    'Prenume',
    'Telefon',
    'Email',
    'Data Împrumut',
    'Data Scadență',
    'Status Depozit',
    'Data Returnare',
    'Zile Total'
]);

// Execută query-ul
$stmt = $pdo->prepare($query);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['titlu'],
        $row['autor'] ?? 'N/A',
        $row['cota'] ?? 'N/A',
        $row['cod_bare'],
        $row['nume'],
        $row['prenume'],
        $row['telefon'] ?? '',
        $row['email'] ?? '',
        date('d.m.Y H:i', strtotime($row['data_imprumut'])),
        $row['data_scadenta'] ? date('d.m.Y', strtotime($row['data_scadenta'])) : 'N/A',
        $row['status_depozit'] === 'livrata' ? 'Livrată' : 'Preluată',
        $row['data_returnare'] ? date('d.m.Y H:i', strtotime($row['data_returnare'])) : 'N/A',
        $row['zile_total']
    ]);
}

fclose($output);
exit;


