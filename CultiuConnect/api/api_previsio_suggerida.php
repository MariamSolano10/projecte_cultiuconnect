<?php
/**
 * api/api_previsio_suggerida.php — Suggeriment de previsió basat en històric.
 *
 * GET:
 *  - id_plantacio (int) [obligatori]
 *  - temporada    (int) [obligatori]
 *
 * Retorna JSON amb:
 *  - suggerit_total_kg, suggerit_kg_arbre, rang_min_kg, rang_max_kg
 *  - metode, avisos, historial (any, kg)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

header('Content-Type: application/json; charset=UTF-8');

$id_plantacio = sanitize_int($_GET['id_plantacio'] ?? null);
$temporada    = sanitize_int($_GET['temporada'] ?? null);

if (!$id_plantacio || !$temporada) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paràmetres obligatoris: id_plantacio, temporada']);
    exit;
}

function weighted_average(array $values, array $weights): ?float
{
    $sumW = 0.0;
    $sum  = 0.0;
    foreach ($values as $i => $v) {
        $w = (float)($weights[$i] ?? 0);
        if (!is_finite($v) || $w <= 0) continue;
        $sumW += $w;
        $sum  += $v * $w;
    }
    return $sumW > 0 ? $sum / $sumW : null;
}

function clamp(float $x, float $min, float $max): float
{
    return max($min, min($max, $x));
}

try {
    $pdo = connectDB();

    // 1) Dades base de la plantació (arbres efectius + sector/varietat per fallback)
    $stmt = $pdo->prepare("
        SELECT
            pl.id_plantacio,
            pl.num_arbres_plantats,
            pl.num_falles,
            pl.id_sector,
            pl.id_varietat
        FROM plantacio pl
        WHERE pl.id_plantacio = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id_plantacio]);
    $pl = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pl) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Plantació no trobada']);
        exit;
    }

    $arbres = max(
        0,
        (int)($pl['num_arbres_plantats'] ?? 0) - (int)($pl['num_falles'] ?? 0)
    );

    $avisos = [];
    if ($arbres <= 0) {
        $avisos[] = 'La plantació no té arbres efectius configurats; no es pot calcular kg/arbre.';
    }

    // 2) Històric de collita per la mateixa plantació i anys anteriors (només unitat kg i collites finalitzades)
    $stmtH = $pdo->prepare("
        SELECT
            YEAR(c.data_inici) AS any,
            SUM(CASE WHEN LOWER(c.unitat_mesura) = 'kg' THEN c.quantitat ELSE 0 END) AS kg
        FROM collita c
        WHERE c.id_plantacio = :id
          AND c.data_fi IS NOT NULL
          AND YEAR(c.data_inici) < :temporada
        GROUP BY YEAR(c.data_inici)
        HAVING kg > 0
        ORDER BY any DESC
        LIMIT 5
    ");
    $stmtH->execute([':id' => $id_plantacio, ':temporada' => $temporada]);
    $hist = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    $metode = 'plantacio';

    // 3) Fallback: si no hi ha històric per plantació, usem mateix sector + varietat (agregat)
    if (empty($hist)) {
        $metode = 'sector_varietat';
        $avisos[] = 'Sense històric de collites per aquesta plantació. S\'usa agregat per sector+varietat.';

        $stmtHF = $pdo->prepare("
            SELECT
                YEAR(c.data_inici) AS any,
                SUM(CASE WHEN LOWER(c.unitat_mesura) = 'kg' THEN c.quantitat ELSE 0 END) AS kg
            FROM collita c
            JOIN plantacio pl2 ON pl2.id_plantacio = c.id_plantacio
            WHERE pl2.id_sector = :id_sector
              AND pl2.id_varietat = :id_varietat
              AND c.data_fi IS NOT NULL
              AND YEAR(c.data_inici) < :temporada
            GROUP BY YEAR(c.data_inici)
            HAVING kg > 0
            ORDER BY any DESC
            LIMIT 5
        ");
        $stmtHF->execute([
            ':id_sector'   => (int)$pl['id_sector'],
            ':id_varietat' => (int)$pl['id_varietat'],
            ':temporada'   => $temporada,
        ]);
        $hist = $stmtHF->fetchAll(PDO::FETCH_ASSOC);
    }

    // Normalitzar ordre (asc) per calcular tendència
    $histAsc = array_reverse($hist);
    $years = [];
    $kgs   = [];
    foreach ($histAsc as $r) {
        $y = (int)$r['any'];
        $k = (float)$r['kg'];
        if ($y <= 0 || $k <= 0) continue;
        $years[] = $y;
        $kgs[]   = $k;
    }

    if (count($kgs) === 0) {
        echo json_encode([
            'ok' => true,
            'metode' => $metode,
            'avisos' => array_merge($avisos, ['No hi ha dades de collita (kg) suficients per suggerir una previsió.']),
            'historial' => [],
        ]);
        exit;
    }

    // 4) Model simple i explicable:
    // - Mitjana ponderada dels últims anys (més pes als recents)
    // - Ajust de tendència lineal suau (si hi ha >= 3 punts)
    $n = count($kgs);
    $weights = [];
    for ($i = 0; $i < $n; $i++) {
        // Pes creixent cap als anys recents
        $weights[] = $i + 1;
    }
    $wa = weighted_average($kgs, $weights);
    $pred = $wa ?? $kgs[$n - 1];

    // Tendència: slope per any via regressió simple
    if ($n >= 3) {
        $xMean = array_sum($years) / $n;
        $yMean = array_sum($kgs) / $n;
        $num = 0.0; $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $years[$i] - $xMean;
            $num += $dx * ($kgs[$i] - $yMean);
            $den += $dx * $dx;
        }
        if ($den > 0) {
            $slope = $num / $den; // kg / any
            // Ajust suau: només un 35% de la tendència per evitar sobreajust
            $pred += 0.35 * $slope;
        }
    }

    // Rang orientatiu segons variabilitat (std dev) o percentatge si hi ha pocs punts
    $min = null; $max = null;
    if ($n >= 3) {
        $mean = array_sum($kgs) / $n;
        $var = 0.0;
        foreach ($kgs as $k) $var += ($k - $mean) ** 2;
        $std = sqrt($var / ($n - 1));
        // Rang: pred ± 1 std (clamp a 0)
        $min = max(0.0, $pred - $std);
        $max = max($pred, $pred + $std);
    } else {
        $min = max(0.0, $pred * 0.85);
        $max = $pred * 1.15;
    }

    // Evitar rangs absurds
    $max = max($max, $min + 1);

    $kg_arbre = ($arbres > 0) ? ($pred / $arbres) : null;

    echo json_encode([
        'ok' => true,
        'metode' => $metode,
        'avisos' => $avisos,
        'arbres_efectius' => $arbres,
        'suggerit_total_kg' => round($pred, 0),
        'suggerit_kg_arbre' => $kg_arbre !== null ? round($kg_arbre, 2) : null,
        'rang_min_kg' => round($min, 0),
        'rang_max_kg' => round($max, 0),
        'historial' => array_map(fn($i) => ['any' => (int)$years[$i], 'kg' => round((float)$kgs[$i], 0)], array_keys($years)),
    ]);

} catch (Exception $e) {
    error_log('[CultiuConnect] api_previsio_suggerida.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error intern']);
    exit;
}

