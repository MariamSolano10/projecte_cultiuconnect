<?php
/**
 * modules/quadern/exportar_quadern_normatiu_pdf.php
 *
 * Generació del quadern de camp en format PDF normatiu oficial.
 * Adaptat als requisits de la normativa agrícola amb camps específics
 * per a aplicacions fitosanitaries segons RD 1702/2011.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// Validar paràmetres
$any = sanitize_int($_GET['any'] ?? null);
$id_sector = sanitize_int($_GET['id_sector'] ?? null);

if (!$any) {
    set_flash('error', 'L\'any és obligatori.');
    header('Location: ' . BASE_URL . 'modules/quadern/quadern.php');
    exit;
}

try {
    $pdo = connectDB();
    
    // Obtenir tractaments/aplicacions amb camps normatius
    $sql = "
        SELECT 
            a.id_aplicacio,
            a.data_event,
            a.hora_inici_planificada,
            a.hora_fi_planificada,
            a.metode_aplicacio,
            a.volum_caldo,
            a.estat_fenologic,
            a.num_carnet_aplicador,
            a.condicions_ambientals,
            s.nom AS nom_sector,
            t.nom AS treballador_nom,
            t.cognoms AS treballador_cognoms,
            p.nom_comercial,
            p.num_registre,
            p.fabricant,
            dap.dosi_aplicada,
            dap.quantitat_consumida_total,
            dap.num_lot,
            i.unitat_mesura
        FROM aplicacio a
        LEFT JOIN sector s ON s.id_sector = a.id_sector
        LEFT JOIN treballador t ON t.id_treballador = a.id_treballador
        LEFT JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
        LEFT JOIN producte_quimic p ON p.id_producte = dap.id_producte
        LEFT JOIN inventari_estoc i ON i.id_estoc = dap.id_estoc
        WHERE YEAR(a.data_event) = :any
    ";
    
    $params = [':any' => $any];
    
    if ($id_sector) {
        $sql .= " AND a.id_sector = :id_sector";
        $params[':id_sector'] = $id_sector;
    }
    
    $sql .= " ORDER BY a.data_event ASC, s.nom ASC, a.id_aplicacio ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $aplicacions = $stmt->fetchAll();
    
    // Agrupar aplicacions per ID per mostrar múltiples productes
    $aplicacions_agrupades = [];
    foreach ($aplicacions as $ap) {
        $id = $ap['id_aplicacio'];
        if (!isset($aplicacions_agrupades[$id])) {
            $aplicacions_agrupades[$id] = [
                'id_aplicacio' => $ap['id_aplicacio'],
                'data_event' => $ap['data_event'],
                'hora_inici_planificada' => $ap['hora_inici_planificada'],
                'hora_fi_planificada' => $ap['hora_fi_planificada'],
                'metode_aplicacio' => $ap['metode_aplicacio'],
                'volum_caldo' => $ap['volum_caldo'],
                'estat_fenologic' => $ap['estat_fenologic'],
                'num_carnet_aplicador' => $ap['num_carnet_aplicador'],
                'condicions_ambientals' => $ap['condicions_ambientals'],
                'nom_sector' => $ap['nom_sector'],
                'treballador_nom' => $ap['treballador_nom'],
                'treballador_cognoms' => $ap['treballador_cognoms'],
                'productes' => []
            ];
        }
        $aplicacions_agrupades[$id]['productes'][] = [
            'nom_comercial' => $ap['nom_comercial'],
            'num_registre' => $ap['num_registre'],
            'fabricant' => $ap['fabricant'],
            'dosi_aplicada' => $ap['dosi_aplicada'],
            'quantitat_consumida_total' => $ap['quantitat_consumida_total'],
            'num_lot' => $ap['num_lot'],
            'unitat_mesura' => $ap['unitat_mesura']
        ];
    }
    
    // Obtenir altres dades (monitoratges, collites, anàlisis)
    $monitoratges = [];
    $collites = [];
    $analisis = [];
    
    if ($id_sector) {
        // Monitoratges
        $stmt = $pdo->prepare("
            SELECT mp.*, s.nom AS nom_sector
            FROM monitoratge_plaga mp
            JOIN sector s ON s.id_sector = mp.id_sector
            WHERE YEAR(mp.data_observacio) = :any AND mp.id_sector = :id_sector
            ORDER BY mp.data_observacio ASC
        ");
        $stmt->execute([':any' => $any, ':id_sector' => $id_sector]);
        $monitoratges = $stmt->fetchAll();
        
        // Collites
        $stmt = $pdo->prepare("
            SELECT c.*, v.nom AS nom_varietat, s.nom AS nom_sector
            FROM collita c
            JOIN plantacio p ON p.id_plantacio = c.id_plantacio
            JOIN varietat v ON v.id_varietat = p.id_varietat
            JOIN sector s ON s.id_sector = p.id_sector
            WHERE (YEAR(c.data_inici) = :any OR YEAR(c.data_fi) = :any) AND s.id_sector = :id_sector
            ORDER BY c.data_inici ASC
        ");
        $stmt->execute([':any' => $any, ':id_sector' => $id_sector]);
        $collites = $stmt->fetchAll();
        
        // Anàlisis de sòl
        $stmt = $pdo->prepare("
            SELECT cs.*, s.nom AS nom_sector
            FROM caracteristiques_sol cs
            JOIN sector s ON s.id_sector = cs.id_sector
            WHERE YEAR(cs.data_analisi) = :any AND s.id_sector = :id_sector
            ORDER BY cs.data_analisi ASC
        ");
        $stmt->execute([':any' => $any, ':id_sector' => $id_sector]);
        $analisis = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log('[CultiuConnect] exportar_quadern_normatiu_pdf.php: ' . $e->getMessage());
    set_flash('error', 'Error generant el PDF normatiu.');
    header('Location: ' . BASE_URL . 'modules/quadern/quadern.php');
    exit;
}

// Classe PDF personalitzada per format normatiu
class QuadernNormatiuPDF extends FPDF {
    private $titol;
    
    public function __construct($orientation = 'L', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 15);
    }
    
    public function Header() {
        // Capçalera oficial
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'QUADERN FITOSANITARI OFICIAL', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, 'Registre d\'aplicacions fitosanitaries - RD 1702/2011', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 6, 'Explotació: CultiuConnect - Any: ' . $this->titol, 0, 1, 'C');
        
        // Línia separadora
        $this->Ln(3);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 280, $this->GetY());
        $this->Ln(5);
    }
    
    public function Footer() {
        // Peu de pàgina
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pàgina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Generat: ' . date('d/m/Y H:i'), 0, 0, 'R');
    }
    
    public function SetTitol($titol) {
        $this->titol = $titol;
    }
    
    public function Seccio($titol) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, utf8_decode($titol), 0, 1, 'L');
        $this->Ln(3);
    }
    
    private function TaulaHeader($headers) {
        $this->SetFont('Arial', 'B', 8);
        foreach ($headers as $header) {
            $this->Cell($header['width'], 7, utf8_decode($header['text']), 1, 0, 'C');
        }
        $this->Ln();
    }
    
    private function TableRow($data) {
        $this->SetFont('Arial', '', 7);
        foreach ($data as $cell) {
            $this->Cell($cell['width'], 6, utf8_decode($cell['text']), 1, 0, $cell['align'] ?? 'L');
        }
        $this->Ln();
    }
    
    public function TaulaAplicacions($aplicacions) {
        if (empty($aplicacions)) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 6, 'No hi ha aplicacions fitosanitaries registrades.', 0, 1, 'L');
            $this->Ln(10);
            return;
        }
        
        // Capçalera de taula segons normativa
        $headers = [
            ['width' => 12, 'text' => 'Data'],
            ['width' => 25, 'text' => 'Sector'],
            ['width' => 30, 'text' => 'Producte'],
            ['width' => 20, 'text' => 'Núm. Registre'],
            ['width' => 15, 'text' => 'Dosi'],
            ['width' => 12, 'text' => 'Unitat'],
            ['width' => 15, 'text' => 'Núm. Lot'],
            ['width' => 20, 'text' => 'Mètode'],
            ['width' => 25, 'text' => 'Aplicador'],
            ['width' => 15, 'text' => 'Carnet'],
            ['width' => 20, 'text' => 'Estat Fen.'],
            ['width' => 15, 'text' => 'Volum'],
            ['width' => 25, 'text' => 'Condicions'],
        ];
        
        $this->TaulaHeader($headers);
        
        foreach ($aplicacions as $aplicacio) {
            $productes_text = '';
            foreach ($aplicacio['productes'] as $i => $prod) {
                if ($i > 0) $productes_text .= "\n";
                $productes_text .= $prod['nom_comercial'];
            }
            
            $registre_text = '';
            foreach ($aplicacio['productes'] as $i => $prod) {
                if ($i > 0) $registre_text .= "\n";
                $registre_text .= $prod['num_registre'] ?? '---';
            }
            
            $dosi_text = '';
            foreach ($aplicacio['productes'] as $i => $prod) {
                if ($i > 0) $dosi_text .= "\n";
                $dosi_text .= number_format((float)$prod['dosi_aplicada'], 2, ',', '.');
            }
            
            $unitat_text = '';
            foreach ($aplicacio['productes'] as $i => $prod) {
                if ($i > 0) $unitat_text .= "\n";
                $unitat_text .= $prod['unitat_mesura'] ?? '---';
            }
            
            $lot_text = '';
            foreach ($aplicacio['productes'] as $i => $prod) {
                if ($i > 0) $lot_text .= "\n";
                $lot_text .= $prod['num_lot'] ?? '---';
            }
            
            $data = [
                ['width' => 12, 'text' => format_data($aplicacio['data_event']), 'align' => 'C'],
                ['width' => 25, 'text' => $aplicacio['nom_sector'], 'align' => 'L'],
                ['width' => 30, 'text' => $productes_text, 'align' => 'L'],
                ['width' => 20, 'text' => $registre_text, 'align' => 'L'],
                ['width' => 15, 'text' => $dosi_text, 'align' => 'C'],
                ['width' => 12, 'text' => $unitat_text, 'align' => 'C'],
                ['width' => 15, 'text' => $lot_text, 'align' => 'L'],
                ['width' => 20, 'text' => ucfirst($aplicacio['metode_aplicacio'] ?? '---'), 'align' => 'L'],
                ['width' => 25, 'text' => trim(($aplicacio['treballador_nom'] ?? '') . ' ' . ($aplicacio['treballador_cognoms'] ?? '')), 'align' => 'L'],
                ['width' => 15, 'text' => $aplicacio['num_carnet_aplicador'] ?? '---', 'align' => 'L'],
                ['width' => 20, 'text' => $aplicacio['estat_fenologic'] ?? '---', 'align' => 'L'],
                ['width' => 15, 'text' => $aplicacio['volum_caldo'] ? number_format((float)$aplicacio['volum_caldo'], 1, ',', '.') . ' L' : '---', 'align' => 'C'],
                ['width' => 25, 'text' => substr($aplicacio['condicions_ambientals'] ?? '---', 0, 15), 'align' => 'L'],
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
            ['width' => 15, 'text' => 'Data'],
            ['width' => 20, 'text' => 'Sector'],
            ['width' => 25, 'text' => 'Tipus Problema'],
            ['width' => 30, 'text' => 'Descripció'],
            ['width' => 15, 'text' => 'Nivell'],
            ['width' => 15, 'text' => 'Intervenció'],
        ];
        
        $this->TaulaHeader($headers);
        
        foreach ($monitoratges as $monitoratge) {
            $data = [
                ['width' => 15, 'text' => format_data($monitoratge['data_observacio']), 'align' => 'C'],
                ['width' => 20, 'text' => $monitoratge['nom_sector'], 'align' => 'L'],
                ['width' => 25, 'text' => $monitoratge['tipus_problema'], 'align' => 'L'],
                ['width' => 30, 'text' => substr($monitoratge['descripcio_breu'] ?? '', 0, 25), 'align' => 'L'],
                ['width' => 15, 'text' => number_format((float)$monitoratge['nivell_poblacio'], 2, ',', '.'), 'align' => 'C'],
                ['width' => 15, 'text' => $monitoratge['llindar_intervencio_assolit'] ? 'Sí' : 'No', 'align' => 'C'],
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
            ['width' => 15, 'text' => 'Data'],
            ['width' => 20, 'text' => 'Sector'],
            ['width' => 20, 'text' => 'Varietat'],
            ['width' => 15, 'text' => 'Quantitat'],
            ['width' => 15, 'text' => 'Qualitat'],
            ['width' => 20, 'text' => 'Treballadors'],
        ];
        
        $this->TaulaHeader($headers);
        
        foreach ($collites as $collita) {
            $periode = $collita['data_fi'] && $collita['data_fi'] != $collita['data_inici'] 
                ? format_data($collita['data_inici']) . '-' . format_data($collita['data_fi'])
                : format_data($collita['data_inici']);
                
            $data = [
                ['width' => 15, 'text' => $periode, 'align' => 'C'],
                ['width' => 20, 'text' => $collita['nom_sector'], 'align' => 'L'],
                ['width' => 20, 'text' => $collita['nom_varietat'], 'align' => 'L'],
                ['width' => 15, 'text' => number_format((float)$collita['quantitat'], 2, ',', '.') . ' ' . $collita['unitat_mesura'], 'align' => 'C'],
                ['width' => 15, 'text' => $collita['qualitat'] ?? '---', 'align' => 'C'],
                ['width' => 20, 'text' => substr($collita['treballadors'] ?? '', 0, 15), 'align' => 'L'],
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
            ['width' => 15, 'text' => 'Data'],
            ['width' => 20, 'text' => 'Sector'],
            ['width' => 10, 'text' => 'pH'],
            ['width' => 12, 'text' => 'M.O.%'],
            ['width' => 10, 'text' => 'N'],
            ['width' => 10, 'text' => 'P'],
            ['width' => 10, 'text' => 'K'],
            ['width' => 15, 'text' => 'Cond.'],
        ];
        
        $this->TaulaHeader($headers);
        
        foreach ($analisis as $analisi) {
            $data = [
                ['width' => 15, 'text' => format_data($analisi['data_analisi']), 'align' => 'C'],
                ['width' => 20, 'text' => $analisi['nom_sector'], 'align' => 'L'],
                ['width' => 10, 'text' => number_format((float)$analisi['pH'], 1, ',', '.'), 'align' => 'C'],
                ['width' => 12, 'text' => number_format((float)$analisi['materia_organica'], 1, ',', '.'), 'align' => 'C'],
                ['width' => 10, 'text' => number_format((float)$analisi['N'], 1, ',', '.'), 'align' => 'C'],
                ['width' => 10, 'text' => number_format((float)$analisi['P'], 1, ',', '.'), 'align' => 'C'],
                ['width' => 10, 'text' => number_format((float)$analisi['K'], 1, ',', '.'), 'align' => 'C'],
                ['width' => 15, 'text' => number_format((float)$analisi['conductivitat_electrica'], 2, ',', '.'), 'align' => 'C'],
            ];
            $this->TableRow($data);
        }
        
        $this->Ln(10);
    }
}

// Generar PDF
$pdf = new QuadernNormatiuPDF('L', 'mm', 'A4');
$pdf->SetTitol($any . ($id_sector ? ' - Sector: ' . $aplicacions[0]['nom_sector'] ?? '' : ''));

$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Secció d'aplicacions fitosanitaries (principal)
$pdf->Seccio('1. APLICACIONS FITOSANITARIAS');
$pdf->TaulaAplicacions($aplicacions_agrupades);

// Altres seccions si hi ha dades
if (!empty($monitoratges)) {
    $pdf->AddPage();
    $pdf->Seccio('2. MONITATGE DE PLAGUES I MALALTIES');
    $pdf->TaulaMonitoratges($monitoratges);
}

if (!empty($collites)) {
    $pdf->AddPage();
    $pdf->Seccio('3. COLLITES');
    $pdf->TaulaCollites($collites);
}

if (!empty($analisis)) {
    $pdf->AddPage();
    $pdf->Seccio('4. ANÀLISIS DE SÒL');
    $pdf->TaulaAnalisis($analisis);
}

// Sortida PDF
$nom_fitxer = 'quadern_normatiu_' . $any . ($id_sector ? '_sector_' . $id_sector : '') . '.pdf';
$pdf->Output($nom_fitxer, 'D');
?>
