<?php
/**
 * modules/comandes/generar_factura_pdf.php
 *
 * Generació de factures en PDF a partir de comandes.
 * Calcula IVA, totals i genera document fiscal.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_comanda = sanitize_int($_GET['id_comanda'] ?? null);

if (!$id_comanda) {
    set_flash('error', 'ID de comanda invàlid.');
    header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
    exit;
}

try {
    $pdo = connectDB();
    
    // Obtenir dades de la comanda
    $stmt = $pdo->prepare("
        SELECT 
            c.id_comanda,
            c.num_comanda,
            c.data_comanda,
            c.data_entrega_prevista,
            c.forma_pagament,
            c.subtotal,
            c.iva_percentatge,
            c.iva_import,
            c.total,
            c.observacions,
            cl.id_client,
            cl.nom_client,
            cl.nif_cif,
            cl.adreca,
            cl.poblacio,
            cl.codi_postal,
            cl.telefon,
            cl.email
        FROM comanda c
        JOIN client cl ON cl.id_client = c.id_client
        WHERE c.id_comanda = ?
    ");
    $stmt->execute([$id_comanda]);
    $comanda = $stmt->fetch();
    
    if (!$comanda) {
        set_flash('error', 'La comanda no existeix.');
        header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
        exit;
    }
    
    // Obtenir detalls de la comanda
    $stmt = $pdo->prepare("
        SELECT 
            dc.quantitat,
            dc.preu_unitari,
            dc.descompte_percent,
            dc.descompte_import,
            dc.subtotal_linia,
            pq.nom_comercial,
            pq.unitat_mesura
        FROM detall_comanda dc
        JOIN producte_quimic pq ON pq.id_producte = dc.id_producte
        WHERE dc.id_comanda = ?
        ORDER BY pq.nom_comercial ASC
    ");
    $stmt->execute([$id_comanda]);
    $detalls = $stmt->fetchAll();
    
    // Verificar si ja existeix factura
    $stmt = $pdo->prepare("SELECT id_factura, num_factura FROM factura WHERE id_comanda = ?");
    $stmt->execute([$id_comanda]);
    $factura_exist = $stmt->fetch();
    
    if ($factura_exist) {
        // Ja existeix factura, redirigir a la existent
        set_flash('info', 'Ja existeix una factura per aquesta comanda: ' . $factura_exist['num_factura']);
        header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
        exit;
    }
    
    // Generar número de factura
    $stmt = $pdo->query("SELECT generar_num_factura() AS num_factura");
    $num_factura = $stmt->fetch()['num_factura'];
    
    // Crear factura a la base de dades
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO factura
            (id_comanda, num_factura, data_factura, data_venciment, forma_pagament,
             base_imposable, iva_percentatge, iva_import, total_factura, observacions)
        VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $id_comanda,
        $num_factura,
        $comanda['data_comanda'],
        $comanda['forma_pagament'],
        $comanda['subtotal'],
        $comanda['iva_percentatge'],
        $comanda['iva_import'],
        $comanda['total'],
        $comanda['observacions'] ?: null
    ]);
    
    $id_factura = (int)$pdo->lastInsertId();
    $pdo->commit();
    
} catch (Exception $e) {
    error_log('[CultiuConnect] generar_factura_pdf.php: ' . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Error generant la factura.');
    header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
    exit;
}

// Classe PDF per a factures
class FacturaPDF extends FPDF {
    private $factura_data;
    private $detalls_data;
    
    public function __construct($factura_data, $detalls_data) {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(true, 20);
        $this->factura_data = $factura_data;
        $this->detalls_data = $detalls_data;
    }
    
    public function Header() {
        // Capçalera de factura
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'FACTURA', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'CultiuConnect - Explotació Agrícola', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 6, 'NIF: 12345678A - Carrer Principal, 123 - 08001 Barcelona', 0, 1, 'C');
        
        // Dades de la factura
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 6, 'Número: ' . $this->factura_data['num_factura'], 0, 0, 'L');
        $this->Cell(60, 6, 'Data: ' . format_data($this->factura_data['data_comanda']), 0, 0, 'C');
        $this->Cell(60, 6, 'Venciment: ' . format_data(date('Y-m-d', strtotime('+30 days'))), 0, 1, 'R');
        
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
    
    public function DadesClient() {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, 'Dades del Client', 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(100, 6, utf8_decode($this->factura_data['nom_client']), 0, 0, 'L');
        $this->Cell(0, 6, 'NIF/CIF: ' . utf8_decode($this->factura_data['nif_cif'] ?: '---'), 0, 1, 'R');
        
        if ($this->factura_data['adreca']) {
            $this->Cell(0, 6, utf8_decode($this->factura_data['adreca']), 0, 1, 'L');
        }
        
        $poblacio = trim(($this->factura_data['poblacio'] ?? '') . ' ' . ($this->factura_data['codi_postal'] ?? ''));
        if ($poblacio) {
            $this->Cell(0, 6, utf8_decode($poblacio), 0, 1, 'L');
        }
        
        if ($this->factura_data['telefon']) {
            $this->Cell(0, 6, 'Tel: ' . utf8_decode($this->factura_data['telefon']), 0, 1, 'L');
        }
        
        if ($this->factura_data['email']) {
            $this->Cell(0, 6, 'Email: ' . utf8_decode($this->factura_data['email']), 0, 1, 'L');
        }
        
        $this->Ln(10);
    }
    
    public function TaulaProductes() {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'Detall de Productes', 0, 1, 'L');
        $this->Ln(5);
        
        // Capçalera de taula
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(80, 7, 'Descripció', 1, 0, 'L');
        $this->Cell(25, 7, 'Quantitat', 1, 0, 'C');
        $this->Cell(25, 7, 'Preu Unit.', 1, 0, 'R');
        $this->Cell(20, 7, 'Dto. %', 1, 0, 'C');
        $this->Cell(30, 7, 'Import', 1, 1, 'R');
        
        // Línies de productes
        $this->SetFont('Arial', '', 9);
        foreach ($this->detalls_data as $detall) {
            $descripcio = utf8_decode($detall['nom_comercial']);
            $quantitat = number_format($detall['quantitat'], 2, ',', '.');
            $preu_unitari = number_format($detall['preu_unitari'], 2, ',', '.');
            $descompte = number_format($detall['descompte_percent'], 1, ',', '.');
            $import = number_format($detall['subtotal_linia'], 2, ',', '.');
            
            $this->Cell(80, 6, $descripcio, 1, 0, 'L');
            $this->Cell(25, 6, $quantitat . ' ' . utf8_decode($detall['unitat_mesura']), 1, 0, 'C');
            $this->Cell(25, 6, $preu_unitari . ' EUR', 1, 0, 'R');
            $this->Cell(20, 6, ($descompte > 0 ? $descompte . '%' : ''), 1, 0, 'C');
            $this->Cell(30, 6, $import . ' EUR', 1, 1, 'R');
        }
        
        $this->Ln(10);
    }
    
    public function Resum() {
        // Alineat a la dreta
        $this->SetX(120);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 6, 'Base Imposable:', 0, 0, 'L');
        $this->Cell(30, 6, number_format($this->factura_data['subtotal'], 2, ',', '.') . ' EUR', 0, 1, 'R');
        
        $this->SetX(120);
        $this->Cell(60, 6, 'IVA (' . number_format($this->factura_data['iva_percentatge'], 1, ',', '.') . '%):', 0, 0, 'L');
        $this->Cell(30, 6, number_format($this->factura_data['iva_import'], 2, ',', '.') . ' EUR', 0, 1, 'R');
        
        // Línia separadora
        $this->SetX(120);
        $this->SetLineWidth(0.5);
        $this->Line(120, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        
        // Total
        $this->SetX(120);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(60, 8, 'TOTAL:', 0, 0, 'L');
        $this->Cell(30, 8, number_format($this->factura_data['total'], 2, ',', '.') . ' EUR', 0, 1, 'R');
        
        $this->Ln(10);
    }
    
    public function FormaPagament() {
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Forma de pagament: ' . ucfirst($this->factura_data['forma_pagament']), 0, 1, 'L');
        
        if ($this->factura_data['observacions']) {
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, 'Observacions:', 0, 1, 'L');
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 5, utf8_decode($this->factura_data['observacions']));
        }
        
        $this->Ln(10);
    }
    
    public function Conditions() {
        $this->SetFont('Arial', 'I', 8);
        $this->MultiCell(0, 4, utf8_decode(
            "Condicions de pagament: Pagament en 30 dies.\n" .
            "Totes les vendes estan subjectes als nostres termes i condicions generals.\n" .
            "Els preus inclouen l'IVA corresponent.\n" .
            "En cas de impagament, s'aplicarà l'interès legal del retard."
        ));
    }
}

// Generar PDF
$pdf = new FacturaPDF($comanda, $detalls);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Afegir seccions
$pdf->DadesClient();
$pdf->TaulaProductes();
$pdf->Resum();
$pdf->FormaPagament();
$pdf->Conditions();

// Sortida PDF
$nom_fitxer = 'factura_' . $num_factura . '.pdf';
$pdf->Output($nom_fitxer, 'D');

// Actualitzar ruta del PDF a la base de dades (opcional)
try {
    $pdo = connectDB();
    $stmt = $pdo->prepare("
        UPDATE factura 
        SET ruta_pdf = ? 
        WHERE id_factura = ?
    ");
    $stmt->execute([$nom_fitxer, $id_factura]);
} catch (Exception $e) {
    error_log('[CultiuConnect] generar_factura_pdf.php (actualitzar ruta): ' . $e->getMessage());
}
?>
