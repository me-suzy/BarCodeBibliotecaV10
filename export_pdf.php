<?php
/**
 * Export PDF - Rapoarte Bibliotecă
 */
// Previne output-ul prematur pentru PDF
ob_start();

session_start();
require_once 'config.php';
require_once 'auth_check.php';

// Bibliotecă simplă pentru PDF - folosim FPDF
require_once 'fpdf/fpdf.php';

$tip = $_GET['tip'] ?? 'active';

/**
 * Funcție helper pentru conversie sigură UTF-8 la windows-1252
 * Înlocuiește caracterele care nu pot fi convertite
 */
function convertToWindows1252($text) {
    if (empty($text)) {
        return '';
    }
    
    // Încearcă conversia normală
    $converted = @iconv('UTF-8', 'windows-1252//IGNORE', $text);
    
    // Dacă conversia eșuează, folosește mb_convert_encoding ca fallback
    if ($converted === false) {
        $converted = @mb_convert_encoding($text, 'windows-1252', 'UTF-8');
    }
    
    // Dacă tot eșuează, înlocuiește caracterele problematice
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

// Extinde clasa FPDF pentru header și footer personalizat
class PDF extends FPDF
{
    function Header()
    {
        // Folosește fonturile core (Helvetica, Times, Courier) care sunt încorporate în FPDF
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 10, convertToWindows1252('Bibliotecă - Raport Împrumuturi'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'I', 10);
        $this->Cell(0, 6, 'Generat: ' . date('d.m.Y H:i'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Creează PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 10);

if ($tip === 'active') {
    // Export împrumuturi active
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 10, convertToWindows1252('Împrumuturi Active'), 0, 1);
    $pdf->Ln(3);
    
    // Query pentru împrumuturi active
    $stmt = $pdo->query("
        SELECT
            i.cod_cititor,
            i.cod_carte,
            i.data_imprumut,
            c.titlu,
            c.autor,
            cit.nume,
            cit.prenume,
            cit.telefon,
            DATEDIFF(NOW(), i.data_imprumut) as zile_imprumut
        FROM imprumuturi i
        JOIN carti c ON i.cod_carte = c.cod_bare
        JOIN cititori cit ON i.cod_cititor = cit.cod_bare
        WHERE i.status = 'activ'
        ORDER BY i.data_imprumut DESC
    ");
    $imprumuturi = $stmt->fetchAll();
    
    // Header tabel
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(50, 8, 'Cititor', 1, 0, 'C', true);
    $pdf->Cell(70, 8, 'Carte', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Data', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Zile', 1, 1, 'C', true);
    
    // Date tabel
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    foreach ($imprumuturi as $imp) {
        $cititor = convertToWindows1252($imp['nume'] . ' ' . $imp['prenume']);
        $carte = convertToWindows1252(substr($imp['titlu'], 0, 40));
        $data = date('d.m.Y', strtotime($imp['data_imprumut']));
        $zile = $imp['zile_imprumut'];
        
        // Culoare background în funcție de zile
        if ($zile > 30) {
            $pdf->SetFillColor(248, 215, 218); // roșu deschis
        } elseif ($zile > 14) {
            $pdf->SetFillColor(255, 243, 205); // galben deschis
        } else {
            $pdf->SetFillColor(212, 237, 218); // verde deschis
        }
        
        $pdf->Cell(50, 7, $cititor, 1, 0, 'L', true);
        $pdf->Cell(70, 7, $carte, 1, 0, 'L', true);
        $pdf->Cell(30, 7, $data, 1, 0, 'C', true);
        $pdf->Cell(20, 7, $zile . ' zile', 1, 1, 'C', true);
    }
    
} elseif ($tip === 'statistica') {
    // Export raport statistic
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 10, convertToWindows1252('Raport Statistic'), 0, 1);
    $pdf->Ln(3);
    
    // Statistici generale
    $stats = [
        'total_carti' => $pdo->query("SELECT COUNT(*) FROM carti")->fetchColumn(),
        'total_cititori' => $pdo->query("SELECT COUNT(*) FROM cititori")->fetchColumn(),
        'imprumuturi_active' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'activ'")->fetchColumn(),
        'total_imprumuturi' => $pdo->query("SELECT COUNT(*) FROM imprumuturi")->fetchColumn(),
        'carti_returnate' => $pdo->query("SELECT COUNT(*) FROM imprumuturi WHERE status = 'returnat'")->fetchColumn()
    ];
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(90, 8, convertToWindows1252('Total cărți în bibliotecă:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $stats['total_carti'], 0, 1);
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(90, 8, convertToWindows1252('Cititori înregistrați:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $stats['total_cititori'], 0, 1);
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(90, 8, convertToWindows1252('Împrumuturi active:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $stats['imprumuturi_active'], 0, 1);
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(90, 8, convertToWindows1252('Total împrumuturi:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $stats['total_imprumuturi'], 0, 1);
    
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(90, 8, convertToWindows1252('Cărți returnate:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $stats['carti_returnate'], 0, 1);
    
    $pdf->Ln(10);
    
    // Top 10 cărți împrumutate
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 10, convertToWindows1252('Top 10 Cărți Împrumutate'), 0, 1);
    
    $stmt = $pdo->query("
        SELECT c.titlu, c.autor, COUNT(*) as numar_imprumuturi
        FROM imprumuturi i
        JOIN carti c ON i.cod_carte = c.cod_bare
        GROUP BY i.cod_carte
        ORDER BY numar_imprumuturi DESC
        LIMIT 10
    ");
    $top_carti = $stmt->fetchAll();
    
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(10, 8, 'Nr', 1, 0, 'C', true);
    $pdf->Cell(100, 8, 'Carte', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Autor', 1, 0, 'C', true);
    $pdf->Cell(30, 8, convertToWindows1252('Împrumuturi'), 1, 1, 'C', true);
    
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $nr = 1;
    foreach ($top_carti as $carte) {
        $titlu = convertToWindows1252(substr($carte['titlu'], 0, 50));
        $autor = convertToWindows1252(substr($carte['autor'], 0, 25));
        
        $pdf->Cell(10, 7, $nr++, 1, 0, 'C');
        $pdf->Cell(100, 7, $titlu, 1, 0, 'L');
        $pdf->Cell(50, 7, $autor, 1, 0, 'L');
        $pdf->Cell(30, 7, $carte['numar_imprumuturi'], 1, 1, 'C');
    }
}

// Curăță orice output înainte de a trimite PDF-ul
ob_end_clean();

// Output PDF
$filename = 'Raport_' . $tip . '_' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $filename);
?>