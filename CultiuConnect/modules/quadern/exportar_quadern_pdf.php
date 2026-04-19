<?php
/**
 * modules/quadern/exportar_quadern_pdf.php
 *
 * Genera i exporta el quadern de camp en format PDF segons la normativa
 * agrícola (RD 1702/2011). Agrupa totes les operacions per any i opcionalment per sector.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();
require_once __DIR__ . '/../../libs/fpdf/fpdf.php';

// Paràmetres
$any_sel    = sanitize_int($_GET['any'] ?? date('Y'));
$sector_sel = sanitize_int($_GET['id_sector'] ?? null);

if (!$any_sel) {
    die('Paràmetre any invàlid.');
}

try {
    $pdo = connectDB();

    // Obtenir dades del sector (si aplica)
    $nom_sector = 'Tots els sectors';
    if ($sector_sel) {
        $stmt = $pdo->prepare("SELECT nom FROM sector WHERE id_sector = ?");
        $stmt->execute([$sector_sel]);
        $sector = $stmt->fetch();
        $nom_sector = $sector ? $sector['nom'] : 'Sector desconegut';
    }

    // --- 1. Tractaments / Aplicacions ---
    $param_any = [':any' => $any_sel];
    $param_sector = $sector_sel ? [':sector' => $sector_sel] : [];

    $sql_t = "
        SELECT
            a.id_aplicacio,
            a.data_event AS data_aplicacio,
            s.nom AS sector,
            a.tipus_event AS tipus_aplicacio,
            a.metode_aplicacio,
            a.descripcio AS observacions,
            GROUP_CONCAT(
                CONCAT(pq.nom_comercial, ' (', dap.quantitat_consumida_total, ' ', pq.unitat_mesura, ')')
                ORDER BY pq.nom_comercial SEPARATOR ' | '
            ) AS productes
        FROM aplicacio a
        JOIN sector s ON s.id_sector = a.id_sector
        LEFT JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
        LEFT JOIN inventari_estoc ie ON ie.id_estoc = dap.id_estoc
        LEFT JOIN producte_quimic pq ON pq.id_producte = ie.id_producte
        WHERE YEAR(a.data_event) = :any
        " . ($sector_sel ? 'AND a.id_sector = :sector' : '') . "
        GROUP BY a.id_aplicacio, a.data_event, s.nom, a.tipus_event, a.metode_aplicacio, a.descripcio
        ORDER BY a.data_event ASC
    ";
    $stmt = $pdo->prepare($sql_t);
    $stmt->execute(array_merge($param_any, $param_sector));
    $tractaments = $stmt->fetchAll();

    // --- 2. Monitoratges de plagues ---
    $sql_m = "
        SELECT
            mp.id_monitoratge,
            mp.data_observacio AS data_monitoratge,
            s.nom AS sector,
            mp.tipus_problema AS nom_plaga,
            mp.nivell_poblacio,
            mp.llindar_intervencio_assolit,
            mp.descripcio_breu
        FROM monitoratge_plaga mp
        JOIN sector s ON s.id_sector = mp.id_sector
        WHERE YEAR(mp.data_observacio) = :any
        " . ($sector_sel ? 'AND mp.id_sector = :sector' : '') . "
        ORDER BY mp.data_observacio ASC
    ";
    $stmt = $pdo->prepare($sql_m);
    $stmt->execute(array_merge($param_any, $param_sector));
    $monitoratges = $stmt->fetchAll();

    // --- 3. Collites ---
    $sql_c = "
        SELECT
            c.id_collita,
            c.data_inici,
            c.data_fi,
            s.nom AS sector,
            c.quantitat,
            c.unitat_mesura,
            c.qualitat,
            c.observacions,
            GROUP_CONCAT(
                CONCAT(t.nom, ' ', COALESCE(t.cognoms,''))
                ORDER BY t.nom SEPARATOR ', '
            ) AS treballadors
        FROM collita c
        JOIN plantacio p ON p.id_plantacio = c.id_plantacio
        JOIN sector s ON s.id_sector = p.id_sector
        LEFT JOIN collita_treballador ct ON ct.id_collita = c.id_collita
        LEFT JOIN treballador t ON t.id_treballador = ct.id_treballador
        WHERE YEAR(c.data_inici) = :any
        " . ($sector_sel ? 'AND s.id_sector = :sector' : '') . "
        GROUP BY c.id_collita, c.data_inici, c.data_fi, s.nom, c.quantitat, c.unitat_mesura, c.qualitat, c.observacions
        ORDER BY c.data_inici ASC
    ";
    $stmt = $pdo->prepare($sql_c);
    $stmt->execute(array_merge($param_any, $param_sector));
    $collites = $stmt->fetchAll();

    // --- 4. Anàlisis de sòl ---
    $sql_a = "
        SELECT
            cs.id_sector,
            cs.data_analisi,
            s.nom AS sector,
            cs.pH,
            cs.materia_organica,
            cs.N, cs.P, cs.K,
            cs.conductivitat_electrica
        FROM caracteristiques_sol cs
        JOIN sector s ON s.id_sector = cs.id_sector
        WHERE YEAR(cs.data_analisi) = :any
        " . ($sector_sel ? 'AND cs.id_sector = :sector' : '') . "
        ORDER BY cs.data_analisi ASC
    ";
    $stmt = $pdo->prepare($sql_a);
    $stmt->execute(array_merge($param_any, $param_sector));
    $analisis = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] exportar_quadern_pdf.php: ' . $e->getMessage());
    die('Error carregant les dades del quadern.');
}

// Classe PDF personalitzada
class QuadernPDF extends FPDF {
    private $titol;
    
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 20);
    }
    
    public function Header() {
        // Capçalera
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'QUADERN DE CAMP OFICIAL', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'Registre d\'activitats de l\'explotació', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, 'Segons RD 1702/2011 i normativa autonòmica', 0, 1, 'C');
        
        // Línia separadora
        $this->Ln(5);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(10);
    }
    
    public function Footer() {
        // Peu de pàgina
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pàgina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Generat: ' . date('d/m/Y H:i'), 0, 0, 'R');
    }
    
    public function Seccio($titol) {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode($titol), 0, 1, 'L');
        $this->Ln(5);
    }
    
    public function Subseccio($titol) {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, utf8_decode($titol), 0, 1, 'L');
        $this->Ln(2);
    }
    
    private function TableHeader($headers) {
        $this->SetFont('Arial', 'B', 9);
        foreach ($headers as $header) {
            $this->Cell($header['width'], 7, utf8_decode($header['text']), 1, 0, 'C');
        }
        $this->Ln();
    }
    
    private function TableRow($data, $is_header = false) {
        $this->SetFont($is_header ? 'Arial' : 'Arial', $is_header ? 'B' : '', 8);
        foreach ($data as $cell) {
            $this->Cell($cell['width'], 6, utf8_decode($cell['text']), 1, 0, $cell['align'] ?? 'L');
        }
        $this->Ln();
    }
    
    public function TaulaTractaments($tractaments) {
        if (empty($tractaments)) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 6, 'No hi ha tractaments registrats.', 0, 1, 'L');
            $this->Ln(10);
            return;
        }
        
        $headers = [
            ['width' => 25, 'text' => 'Data'],
            ['width' => 30, 'text' => 'Sector'],
            ['width' => 35, 'text' => 'Tipus'],
            ['width' => 25, 'text' => 'Mètode'],
            ['width' => 60, 'text' => 'Productes'],
            ['width' => 25, 'text' => 'Observacions']
        ];
        
        $this->TableHeader($headers);
        
        foreach ($tractaments as $tractament) {
            $data = [
                ['width' => 25, 'text' => format_data($tractament['data_aplicacio']), 'align' => 'C'],
                ['width' => 30, 'text' => $tractament['sector'], 'align' => 'L'],
                ['width' => 35, 'text' => $tractament['tipus_aplicacio'], 'align' => 'L'],
                ['width' => 25, 'text' => $tractament['metode_aplicacio'] ?? '-', 'align' => 'L'],
                ['width' => 60, 'text' => $tractament['productes'] ?? '-', 'align' => 'L'],
                ['width' => 25, 'text' => substr($tractament['observacions'] ?? '-', 0, 15), 'align' => 'L']
            ];
            $this->TableRow($data);
        }
        
        $this->Ln(10);
    }
    
    public function TaulaMonitoratges($monitoratges) {
        if (empty($monitoratges)) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 6, 'No hi ha monitoratges registrats.', 0, 1, 'L');
            $this->Ln(10);
            return;
        }
        
        $headers = [
            ['width' => 25, 'text' => 'Data'],
            ['width' => 30, 'text' => 'Sector'],
            ['width' => 40, 'text' => 'Plaga/Problema'],
            ['width' => 25, 'text' => 'Nivell'],
            ['width' => 30, 'text' => 'Intervenció'],
            ['width' => 40, 'text' => 'Descripció']
        ];
        
        $this->TableHeader($headers);
        
        foreach ($monitoratges as $monitoratge) {
            $data = [
                ['width' => 25, 'text' => format_data($monitoratge['data_monitoratge']), 'align' => 'C'],
                ['width' => 30, 'text' => $monitoratge['sector'], 'align' => 'L'],
                ['width' => 40, 'text' => $monitoratge['nom_plaga'], 'align' => 'L'],
                ['width' => 25, 'text' => $monitoratge['nivell_poblacio'] ?? '-', 'align' => 'C'],
                ['width' => 30, 'text' => $monitoratge['llindar_intervencio_assolit'] ? 'Sí' : 'No', 'align' => 'C'],
                ['width' => 40, 'text' => substr($monitoratge['descripcio_breu'] ?? '-', 0, 25), 'align' => 'L']
            ];
            $this->TableRow($data);
        }
        
        $this->Ln(10);
    }
    
    public function TaulaCollites($collites) {
        if (empty($collites)) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 6, 'No hi ha collites registrades.', 0, 1, 'L');
            $this->Ln(10);
            return;
        }
        
        $headers = [
            ['width' => 20, 'text' => 'Data'],
            ['width' => 25, 'text' => 'Sector'],
            ['width' => 25, 'text' => 'Quantitat'],
            ['width' => 20, 'text' => 'Qualitat'],
            ['width' => 40, 'text' => 'Treballadors'],
            ['width' => 30, 'text' => 'Observacions']
        ];
        
        $this->TableHeader($headers);
        
        foreach ($collites as $collita) {
            $periode = $collita['data_fi'] && $collita['data_fi'] != $collita['data_inici'] 
                ? format_data($collita['data_inici']) . '-' . format_data($collita['data_fi'])
                : format_data($collita['data_inici']);
                
            $data = [
                ['width' => 20, 'text' => $periode, 'align' => 'C'],
                ['width' => 25, 'text' => $collita['sector'], 'align' => 'L'],
                ['width' => 25, 'text' => $collita['quantitat'] . ' ' . $collita['unitat_mesura'], 'align' => 'C'],
                ['width' => 20, 'text' => $collita['qualitat'] ?? '-', 'align' => 'C'],
                ['width' => 40, 'text' => $collita['treballadors'] ?? '-', 'align' => 'L'],
                ['width' => 30, 'text' => substr($collita['observacions'] ?? '-', 0, 15), 'align' => 'L']
            ];
            $this->TableRow($data);
        }
        
        $this->Ln(10);
    }
    
    public function TaulaAnalisis($analisis) {
        if (empty($analisis)) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 6, 'No hi ha anàlisis de sòl registrades.', 0, 1, 'L');
            $this->Ln(10);
            return;
        }
        
        $headers = [
            ['width' => 25, 'text' => 'Data'],
            ['width' => 25, 'text' => 'Sector'],
            ['width' => 15, 'text' => 'pH'],
            ['width' => 20, 'text' => 'M.O. (%)'],
            ['width' => 15, 'text' => 'N'],
            ['width' => 15, 'text' => 'P'],
            ['width' => 15, 'text' => 'K'],
            ['width' => 20, 'text' => 'Cond. (µS/cm)']
        ];
        
        $this->TableHeader($headers);
        
        foreach ($analisis as $analisi) {
            $data = [
                ['width' => 25, 'text' => format_data($analisi['data_analisi']), 'align' => 'C'],
                ['width' => 25, 'text' => $analisi['sector'], 'align' => 'L'],
                ['width' => 15, 'text' => $analisi['pH'] ?? '-', 'align' => 'C'],
                ['width' => 20, 'text' => $analisi['materia_organica'] ?? '-', 'align' => 'C'],
                ['width' => 15, 'text' => $analisi['N'] ?? '-', 'align' => 'C'],
                ['width' => 15, 'text' => $analisi['P'] ?? '-', 'align' => 'C'],
                ['width' => 15, 'text' => $analisi['K'] ?? '-', 'align' => 'C'],
                ['width' => 20, 'text' => $analisi['conductivitat_electrica'] ?? '-', 'align' => 'C']
            ];
            $this->TableRow($data);
        }
        
        $this->Ln(10);
    }
}

// Crear PDF
$pdf = new QuadernPDF();
$pdf->AddPage();

// Capçalera del document
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'ANY: ' . $any_sel, 0, 1, 'L');
$pdf->Cell(0, 8, 'SECTOR: ' . utf8_decode($nom_sector), 0, 1, 'L');
$pdf->Ln(10);

// Seccions
$pdf->Seccio('1. TRACTAMENTS FITOSANITARIS I FERTILITZANTS');
$pdf->TaulaTractaments($tractaments);

$pdf->Seccio('2. MONITATGE DE PLAGUES I MALALTIES');
$pdf->TaulaMonitoratges($monitoratges);

$pdf->Seccio('3. COLLITES');
$pdf->TaulaCollites($collites);

$pdf->Seccio('4. ANÀLISIS DE SÒL');
$pdf->TaulaAnalisis($analisis);

// Resum final
$pdf->Seccio('RESUM DE L\'ANY');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode('Total tractaments/aplicacions: ') . count($tractaments), 0, 1, 'L');
$pdf->Cell(0, 6, utf8_decode('Total monitoratges: ') . count($monitoratges), 0, 1, 'L');
$pdf->Cell(0, 6, utf8_decode('Total collites: ') . count($collites), 0, 1, 'L');
$pdf->Cell(0, 6, utf8_decode('Total anàlisis de sòl: ') . count($analisis), 0, 1, 'L');

// Signatures
$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'SIGNATURES', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(80, 6, utf8_decode('_________________________'), 0, 0, 'C');
$pdf->Cell(40, 6, '', 0, 0, 'C');
$pdf->Cell(80, 6, utf8_decode('_________________________'), 0, 1, 'C');
$pdf->Cell(80, 6, utf8_decode('Tècnic/Agricultor'), 0, 0, 'C');
$pdf->Cell(40, 6, '', 0, 0, 'C');
$pdf->Cell(80, 6, utf8_decode('Data'), 0, 1, 'C');

// Nom del fitxer
$filename = 'quadern_camp_' . $any_sel;
if ($sector_sel) {
    $filename .= '_sector_' . $sector_sel;
}
$filename .= '_' . date('Ymd_His') . '.pdf';

// Sortida PDF
$pdf->Output('D', $filename);
exit;
