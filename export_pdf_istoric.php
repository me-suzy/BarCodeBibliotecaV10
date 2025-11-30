<?php
/**
 * Export PDF - Istoric Depozit
 */
ob_start();

session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Bibliotecă simplă pentru PDF - folosim FPDF
require_once 'fpdf/fpdf.php';

// Obține ID-urile selectate sau parametrii de căutare
$selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
$search_term = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$data_start = isset($_POST['data_start']) ? trim($_POST['data_start']) : (isset($_GET['data_start']) ? trim($_GET['data_start']) : '');
$data_end = isset($_POST['data_end']) ? trim($_POST['data_end']) : (isset($_GET['data_end']) ? trim($_GET['data_end']) : '');

/**
 * Funcție helper pentru conversie sigură UTF-8 la windows-1252
 */
function convertToWindows1252($text) {
    if (empty($text)) {
        return '';
    }
    
    $converted = @iconv('UTF-8', 'windows-1252//IGNORE', $text);
    
    if ($converted === false) {
        $converted = @mb_convert_encoding($text, 'windows-1252', 'UTF-8');
    }
    
    if ($converted === false || $converted === '') {
        $replacements = [
            'ă' => 'a', 'Ă' => 'A',
            'â' => 'a', 'Â' => 'A',
            'î' => 'i', 'Î' => 'I',
            'ș' => 's', 'Ș' => 'S',
            'ț' => 't', 'Ț' => 'T',
            '„' => '"', '"' => '"',
            '' => "'", '' => "'",
            '–' => '-', '—' => '-',
        ];
        $converted = strtr($text, $replacements);
        $converted = @iconv('UTF-8', 'windows-1252//IGNORE', $converted);
    }
    
    return $converted !== false ? $converted : $text;
}

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

// Execută query-ul
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$istoric = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Curăță output buffer
ob_end_clean();

// Creează PDF
$pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, convertToWindows1252('Istoric Cărți Depozit'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, convertToWindows1252('Generat la: ' . date('d.m.Y H:i')), 0, 1, 'C');
$pdf->Cell(0, 5, convertToWindows1252('Total înregistrări: ' . count($istoric)), 0, 1, 'C');
$pdf->Ln(5);

// Header tabel
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(15, 8, convertToWindows1252('ID'), 1, 0, 'C', true);
$pdf->Cell(60, 8, convertToWindows1252('Titlu'), 1, 0, 'L', true);
$pdf->Cell(40, 8, convertToWindows1252('Autor'), 1, 0, 'L', true);
$pdf->Cell(35, 8, convertToWindows1252('Cititor'), 1, 0, 'L', true);
$pdf->Cell(25, 8, convertToWindows1252('Data Împrumut'), 1, 0, 'C', true);
$pdf->Cell(25, 8, convertToWindows1252('Status'), 1, 0, 'C', true);
$pdf->Cell(25, 8, convertToWindows1252('Data Returnare'), 1, 0, 'C', true);
$pdf->Cell(20, 8, convertToWindows1252('Zile'), 1, 1, 'C', true);

// Date
$pdf->SetFont('Arial', '', 7);
foreach ($istoric as $item) {
    $pdf->Cell(15, 6, $item['id'], 1, 0, 'C');
    $pdf->Cell(60, 6, convertToWindows1252(mb_substr($item['titlu'], 0, 40)), 1, 0, 'L');
    $pdf->Cell(40, 6, convertToWindows1252(mb_substr($item['autor'] ?? 'N/A', 0, 30)), 1, 0, 'L');
    
    // Limitează cititorul la 26 caractere
    $cititor = $item['nume'] . ' ' . $item['prenume'];
    $cititor_limited = mb_substr($cititor, 0, 26);
    $pdf->Cell(35, 6, convertToWindows1252($cititor_limited), 1, 0, 'L');
    
    $pdf->Cell(25, 6, date('d.m.Y', strtotime($item['data_imprumut'])), 1, 0, 'C');
    $pdf->Cell(25, 6, convertToWindows1252($item['status_depozit'] === 'livrata' ? 'Livrată' : 'Preluată'), 1, 0, 'C');
    
    // Data returnare - nu afișa "N/A", lasă gol dacă nu există
    $data_returnare = $item['data_returnare'] ? date('d.m.Y', strtotime($item['data_returnare'])) : '';
    $pdf->Cell(25, 6, $data_returnare, 1, 0, 'C');
    
    $pdf->Cell(20, 6, $item['zile_total'], 1, 1, 'C');
}

// Output PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="istoric_depozit_' . date('Y-m-d') . '.pdf"');
$pdf->Output('D');
exit;

