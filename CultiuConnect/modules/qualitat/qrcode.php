<?php
/**
 * modules/qualitat/qrcode.php — Generador de codis QR (PNG) via phpqrcode.
 *
 * Ús:
 *   /modules/qualitat/qrcode.php?data=<urlencoded>&size=6&margin=2&level=M
 *   /modules/qualitat/qrcode.php?c=<token>&mode=payload
 *
 * Nota: aquest endpoint està pensat per ús intern (panells de gestió).
 */
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/db.php';
cc_session_start();
cc_enforce_auth_i_rols();
require_once __DIR__ . '/../../libs/phpqrcode/qrlib.php';

header('Content-Type: image/svg+xml');
header('Cache-Control: no-store, max-age=0');

function cc_compact(?string $s, int $max = 80): ?string
{
    if ($s === null) return null;
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if ($s === '') return null;
    if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max) . '…';
    } elseif (strlen($s) > $max) {
        $s = substr($s, 0, $max) . '…';
    }
    return $s;
}

$mode = strtolower(trim((string)($_GET['mode'] ?? 'data'))); // data|url|payload

// Prioritat: data explícita > token
$data = trim((string)($_GET['data'] ?? ''));

if ($data === '') {
    $token = sanitize($_GET['c'] ?? '');
    if ($token === '' || strlen($token) < 8) {
        http_response_code(400);
        exit;
    }

    // mode=url: codifica l'enllaç públic; mode=payload: codifica dades públiques
    if ($mode === 'url') {
        $data = BASE_URL . 'tracabilitat.php?c=' . urlencode($token);
    } else {
        try {
            $pdo = connectDB();

            $stmt = $pdo->prepare("
                SELECT
                    lp.id_lot,
                    lp.identificador,
                    lp.data_processat,
                    lp.pes_kg,
                    lp.qualitat AS lot_qualitat,
                    lp.desti,
                    c.data_inici AS collita_inici,
                    c.data_fi    AS collita_fi,
                    s.nom        AS nom_sector,
                    v.nom_varietat
                FROM lot_produccio lp
                JOIN collita c        ON c.id_collita = lp.id_collita
                JOIN plantacio pl     ON pl.id_plantacio = c.id_plantacio
                JOIN sector s         ON s.id_sector = pl.id_sector
                LEFT JOIN varietat v  ON v.id_varietat = pl.id_varietat
                WHERE lp.codi_qr = :t
                LIMIT 1
            ");
            $stmt->execute([':t' => $token]);
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lot) {
                http_response_code(404);
                exit;
            }

            $stmt_q = $pdo->prepare("
                SELECT data_control, calibre_mm, fermesa_kg_cm2, color, sabor, resultat
                FROM control_qualitat
                WHERE id_lot = :id
                ORDER BY data_control DESC, id_control DESC
                LIMIT 1
            ");
            $stmt_q->execute([':id' => (int)$lot['id_lot']]);
            $qc = $stmt_q->fetch(PDO::FETCH_ASSOC) ?: null;

            $payload = [
                'app' => 'CultiuConnect',
                'v'   => 1,
                'lot' => [
                    'id'           => (int)$lot['id_lot'],
                    'codi'         => (string)$lot['identificador'],
                    'sector'       => cc_compact($lot['nom_sector'] ?? null, 60),
                    'varietat'     => cc_compact($lot['nom_varietat'] ?? null, 60),
                    'collita_inici'=> !empty($lot['collita_inici']) ? substr((string)$lot['collita_inici'], 0, 10) : null,
                    'collita_fi'   => !empty($lot['collita_fi']) ? substr((string)$lot['collita_fi'], 0, 10) : null,
                    'processat'    => !empty($lot['data_processat']) ? (string)$lot['data_processat'] : null,
                    'pes_kg'       => ($lot['pes_kg'] !== null) ? (float)$lot['pes_kg'] : null,
                    'qualitat'     => (string)($lot['lot_qualitat'] ?? ''),
                    'desti'        => cc_compact($lot['desti'] ?? null, 80),
                ],
                'qc' => $qc ? [
                    'data'    => !empty($qc['data_control']) ? (string)$qc['data_control'] : null,
                    'resultat'=> (string)($qc['resultat'] ?? ''),
                    'calibre' => ($qc['calibre_mm'] !== null) ? (float)$qc['calibre_mm'] : null,
                    'fermesa' => ($qc['fermesa_kg_cm2'] !== null) ? (float)$qc['fermesa_kg_cm2'] : null,
                    'color'   => cc_compact($qc['color'] ?? null, 40),
                    'sabor'   => cc_compact($qc['sabor'] ?? null, 40),
                ] : null,
                'url' => BASE_URL . 'tracabilitat.php?c=' . urlencode($token),
            ];

            $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($data) || $data === '') {
                http_response_code(500);
                exit;
            }

        } catch (Exception $e) {
            error_log('[CultiuConnect] qrcode.php: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
    }
}

// Limita mida per evitar abusos i errors de memòria
if (strlen($data) > 2000) {
    http_response_code(413);
    exit;
}

$size = isset($_GET['size']) ? (int)$_GET['size'] : 6;      // píxels per punt
$size = max(2, min(12, $size));

$margin = isset($_GET['margin']) ? (int)$_GET['margin'] : 2; // "quiet zone"
$margin = max(0, min(10, $margin));

$level = strtoupper((string)($_GET['level'] ?? 'M'));
$levels = [
    'L' => QR_ECLEVEL_L,
    'M' => QR_ECLEVEL_M,
    'Q' => QR_ECLEVEL_Q,
    'H' => QR_ECLEVEL_H,
];
$ec = $levels[$level] ?? QR_ECLEVEL_M;

// Output directament a stdout (sense fitxer)
QRcode::svg($data, false, $ec, $size, $margin);

