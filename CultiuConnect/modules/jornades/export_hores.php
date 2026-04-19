<?php
/**
 * modules/jornades/export_hores.php — Export d'hores treballades (CSV/PDF).
 *
 * GET:
 *  - mes=YYYY-MM (obligatori)
 *  - id_treballador=int (opcional)
 *  - validada=0|1|tots (opcional)
 *  - format=csv|pdf (obligatori)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols(['admin', 'tecnic', 'responsable']);

$mes = (!empty($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['mes']))
    ? (string)$_GET['mes']
    : date('Y-m');

$id_treballador = (!empty($_GET['id_treballador']) && is_numeric($_GET['id_treballador']))
    ? (int)$_GET['id_treballador']
    : 0;

$validada = (isset($_GET['validada']) && in_array((string)$_GET['validada'], ['0', '1', 'tots'], true))
    ? (string)$_GET['validada']
    : 'tots';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'pdf'], true)) {
    http_response_code(400);
    echo 'Format no vàlid.';
    exit;
}

try {
    $pdo = connectDB();

    $condicions = ["DATE_FORMAT(j.data_hora_inici, '%Y-%m') = :mes"];
    $params     = [':mes' => $mes];

    if ($id_treballador > 0) {
        $condicions[] = 'j.id_treballador = :id_treballador';
        $params[':id_treballador'] = $id_treballador;
    }
    if ($validada !== 'tots') {
        $condicions[] = 'j.validada = :validada';
        $params[':validada'] = (int)$validada;
    }
    $where = 'WHERE ' . implode(' AND ', $condicions);

    $sql = "
        SELECT
            j.id_jornada,
            j.data_hora_inici,
            j.data_hora_fi,
            j.pausa_minuts,
            j.ubicacio,
            j.incidencies,
            j.validada,
            t.id_treballador,
            CONCAT(t.nom, ' ', COALESCE(t.cognoms,'')) AS treballador,
            t.rol,
            ta.tipus AS tipus_tasca,
            TIMESTAMPDIFF(MINUTE, j.data_hora_inici, j.data_hora_fi) - COALESCE(j.pausa_minuts, 0) AS minuts_nets
        FROM jornada j
        JOIN treballador t ON t.id_treballador = j.id_treballador
        LEFT JOIN tasca ta ON ta.id_tasca = j.id_tasca
        $where
        ORDER BY treballador ASC, j.data_hora_inici ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hores_' . $mes . ($id_treballador ? ('_t' . $id_treballador) : '') . '.csv"');

        // UTF-8 BOM per Excel
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Mes', 'ID jornada', 'ID treballador', 'Treballador', 'Rol',
            'Inici', 'Fi', 'Pausa (min)', 'Minuts nets', 'Hores nets',
            'Validada', 'Tasca', 'Ubicació', 'Incidències',
        ], ';');
        foreach ($rows as $r) {
            $min = ($r['minuts_nets'] !== null) ? (int)$r['minuts_nets'] : null;
            $hores = $min !== null ? round($min / 60, 2) : null;
            fputcsv($out, [
                $mes,
                (int)$r['id_jornada'],
                (int)$r['id_treballador'],
                $r['treballador'],
                $r['rol'],
                $r['data_hora_inici'],
                $r['data_hora_fi'],
                $r['pausa_minuts'],
                $min,
                $hores,
                ((int)$r['validada'] === 1) ? 'Sí' : 'No',
                $r['tipus_tasca'] ?? '',
                $r['ubicacio'] ?? '',
                $r['incidencies'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    // PDF
    require_once __DIR__ . '/../../libs/fpdf/fpdf.php';

    class HoresPDF extends FPDF
    {
        public string $titol = '';

        function Header()
        {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $this->titol), 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Generat: ' . date('d/m/Y H:i')), 0, 1, 'L');
            $this->Ln(2);
        }

        function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 10, 'Pàgina ' . $this->PageNo(), 0, 0, 'R');
        }
    }

    $pdf = new HoresPDF('L', 'mm', 'A4');
    $pdf->titol = 'Hores treballades (mes ' . $mes . ')' . ($id_treballador ? ' — Treballador #' . $id_treballador : '');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 9);

    $cols = [
        ['Treballador', 55],
        ['Inici', 32],
        ['Fi', 32],
        ['Pausa', 14],
        ['Nets', 14],
        ['Valid.', 14],
        ['Tasca', 28],
        ['Ubicació', 35],
    ];
    foreach ($cols as [$lbl, $w]) {
        $pdf->Cell($w, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $lbl), 1, 0, 'L');
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    $totalMin = 0;
    foreach ($rows as $r) {
        $min = ($r['minuts_nets'] !== null) ? (int)$r['minuts_nets'] : 0;
        if (!empty($r['data_hora_fi'])) $totalMin += $min;

        $vals = [
            [$r['treballador'] ?? '', 55],
            [substr((string)$r['data_hora_inici'], 0, 16), 32],
            [substr((string)$r['data_hora_fi'], 0, 16), 32],
            [(string)($r['pausa_minuts'] ?? 0), 14],
            [(string)$min, 14],
            [((int)$r['validada'] === 1) ? 'Sí' : 'No', 14],
            [$r['tipus_tasca'] ?? '', 28],
            [$r['ubicacio'] ?? '', 35],
        ];
        foreach ($vals as [$txt, $w]) {
            $pdf->Cell($w, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$txt), 1, 0, 'L');
        }
        $pdf->Ln();
    }

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Total minuts nets: ' . $totalMin . '  (hores: ' . round($totalMin / 60, 2) . ')'), 0, 1, 'L');

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="hores_' . $mes . ($id_treballador ? ('_t' . $id_treballador) : '') . '.pdf"');
    $pdf->Output('I');
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] export_hores.php: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error intern en exportar.';
    exit;
}

