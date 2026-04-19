<?php
/**
 * api/api_esdeveniments.php - Endpoint JSON per al calendari FullCalendar.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$start = $_GET['start'] ?? null;
$end   = $_GET['end'] ?? null;

if (!$start || !strtotime($start)) {
    $start = date('Y-m-01');
}
if (!$end || !strtotime($end)) {
    $end = date('Y-m-t');
}

$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

try {
    $pdo = connectDB();
    $esdeveniments = [];

    $afegir = static function (callable $loader) use (&$esdeveniments): void {
        try {
            $nous = $loader();
            if (is_array($nous) && $nous !== []) {
                $esdeveniments = array_merge($esdeveniments, $nous);
            }
        } catch (Throwable $e) {
            error_log('[CultiuConnect] api_esdeveniments bloc: ' . $e->getMessage());
        }
    };

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                tp.id_programat,
                tp.data_prevista,
                tp.tipus,
                tp.motiu,
                tp.estat,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM tractament_programat tp
            LEFT JOIN sector s ON tp.id_sector = s.id_sector
            WHERE tp.estat = 'pendent'
              AND tp.data_prevista BETWEEN :start AND :end
            ORDER BY tp.data_prevista ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'id' => 'tractament-' . $row['id_programat'],
                'title' => ucfirst((string)$row['tipus']) . ' - ' . $row['nom_sector'],
                'start' => $row['data_prevista'],
                'allDay' => true,
                'description' => $row['motiu'] ?? '',
                'color' => '#e74c3c',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'tractament',
                    'estat' => $row['estat'],
                    'sector' => $row['nom_sector'],
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                t.id_tasca,
                t.tipus,
                t.descripcio,
                t.data_inici_prevista,
                t.data_fi_prevista,
                t.estat,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM tasca t
            LEFT JOIN sector s ON t.id_sector = s.id_sector
            WHERE t.estat IN ('pendent', 'en_proces')
              AND t.data_inici_prevista <= :end
              AND (t.data_fi_prevista >= :start OR t.data_fi_prevista IS NULL)
            ORDER BY t.data_inici_prevista ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $dataFi = $row['data_fi_prevista'] ?? $row['data_inici_prevista'];
            $events[] = [
                'id' => 'tasca-' . $row['id_tasca'],
                'title' => ucfirst((string)$row['tipus']) . ' - ' . $row['nom_sector'],
                'start' => $row['data_inici_prevista'],
                'end' => date('Y-m-d', strtotime($dataFi . ' +1 day')),
                'allDay' => true,
                'description' => $row['descripcio'] ?? '',
                'color' => $row['estat'] === 'en_proces' ? '#2980b9' : '#27ae60',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'tasca',
                    'estat' => $row['estat'],
                    'sector' => $row['nom_sector'],
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                pc.id_previsio,
                pc.data_inici_collita_estimada,
                pc.data_fi_collita_estimada,
                pc.produccio_estimada_kg,
                pc.qualitat_prevista,
                v.nom_varietat,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM previsio_collita pc
            JOIN plantacio p ON pc.id_plantacio = p.id_plantacio
            JOIN varietat v ON p.id_varietat = v.id_varietat
            JOIN sector s ON p.id_sector = s.id_sector
            WHERE pc.data_inici_collita_estimada IS NOT NULL
              AND pc.data_inici_collita_estimada <= :end
              AND (pc.data_fi_collita_estimada >= :start OR pc.data_fi_collita_estimada IS NULL)
            ORDER BY pc.data_inici_collita_estimada ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $dataFi = $row['data_fi_collita_estimada'] ?? $row['data_inici_collita_estimada'];
            $kg = $row['produccio_estimada_kg']
                ? ' (~' . number_format((float)$row['produccio_estimada_kg'], 0, ',', '.') . ' kg)'
                : '';
            $events[] = [
                'id' => 'previsio-' . $row['id_previsio'],
                'title' => 'Collita prev.: ' . $row['nom_varietat'] . $kg,
                'start' => $row['data_inici_collita_estimada'],
                'end' => date('Y-m-d', strtotime($dataFi . ' +1 day')),
                'allDay' => true,
                'description' => $row['nom_sector'],
                'color' => '#f39c12',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'previsio_collita',
                    'sector' => $row['nom_sector'],
                    'qualitat' => $row['qualitat_prevista'] ?? '',
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                c.id_collita,
                c.data_inici,
                c.data_fi,
                c.quantitat,
                c.unitat_mesura,
                c.qualitat,
                v.nom_varietat,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM collita c
            JOIN plantacio p ON c.id_plantacio = p.id_plantacio
            JOIN varietat v ON p.id_varietat = v.id_varietat
            JOIN sector s ON p.id_sector = s.id_sector
            WHERE DATE(c.data_inici) <= :end
              AND (DATE(c.data_fi) >= :start OR c.data_fi IS NULL)
            ORDER BY c.data_inici ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $dataFi = $row['data_fi']
                ? date('Y-m-d', strtotime(substr((string)$row['data_fi'], 0, 10) . ' +1 day'))
                : date('Y-m-d', strtotime(substr((string)$row['data_inici'], 0, 10) . ' +1 day'));
            $quantitat = $row['quantitat']
                ? ' - ' . number_format((float)$row['quantitat'], 0, ',', '.') . ' ' . $row['unitat_mesura']
                : '';
            $events[] = [
                'id' => 'collita-' . $row['id_collita'],
                'title' => 'Collita: ' . $row['nom_varietat'] . $quantitat,
                'start' => substr((string)$row['data_inici'], 0, 10),
                'end' => $dataFi,
                'allDay' => true,
                'description' => $row['nom_sector'],
                'color' => '#8e44ad',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'collita',
                    'sector' => $row['nom_sector'],
                    'qualitat' => $row['qualitat'] ?? '',
                ],
            ];
        }
        return $events;
    });

    $usuari = usuari_actiu();
    $rol = strtolower((string)($usuari['rol'] ?? 'operari'));
    $esGestor = in_array($rol, ['admin', 'tecnic', 'responsable'], true);
    $idTreballadorFiltre = null;
    if (!$esGestor) {
        $stmtU = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
        $stmtU->execute([':id' => (int)($usuari['id'] ?? 0)]);
        $idTreballadorFiltre = (int)($stmtU->fetchColumn() ?: -1);
    }

    $afegir(function () use ($pdo, $start, $end, $esGestor, $idTreballadorFiltre) {
        $sql = "
            SELECT
                pa.id_permis,
                pa.tipus,
                pa.data_inici,
                pa.data_fi,
                t.nom,
                t.cognoms
            FROM permis_absencia pa
            JOIN treballador t ON pa.id_treballador = t.id_treballador
            WHERE pa.data_inici <= :end
              AND (pa.data_fi >= :start OR pa.data_fi IS NULL)
              AND pa.aprovat = 1
              " . (!$esGestor ? "AND pa.id_treballador = :id_treballador" : "") . "
            ORDER BY pa.data_inici ASC
        ";
        $stmt = $pdo->prepare($sql);
        $params = [':start' => $start, ':end' => $end];
        if (!$esGestor) {
            $params[':id_treballador'] = $idTreballadorFiltre;
        }
        $stmt->execute($params);

        $etiquetes = [
            'vacances' => 'Vacances',
            'permis' => 'Permis',
            'baixa_malaltia' => 'Baixa malaltia',
            'baixa_accident' => 'Baixa accident',
            'curs' => 'Curs / Formacio',
            'altres' => 'Absencia',
        ];

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $dataFi = $row['data_fi']
                ? date('Y-m-d', strtotime($row['data_fi'] . ' +1 day'))
                : date('Y-m-d', strtotime($row['data_inici'] . ' +1 day'));
            $etiqueta = $etiquetes[$row['tipus']] ?? ucfirst((string)$row['tipus']);
            $nomComplet = trim((string)$row['nom'] . ' ' . (string)($row['cognoms'] ?? ''));
            $events[] = [
                'id' => 'permis-' . $row['id_permis'],
                'title' => $etiqueta . ': ' . $nomComplet,
                'start' => $row['data_inici'],
                'end' => $dataFi,
                'allDay' => true,
                'description' => $etiqueta,
                'color' => '#95a5a6',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'permis',
                    'treballador' => $nomComplet,
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                af.id_analisi_foliar,
                af.data_analisi,
                af.estat_fenologic,
                af.observacions,
                v.nom_varietat,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM analisi_foliar af
            LEFT JOIN plantacio p ON p.id_plantacio = af.id_plantacio
            LEFT JOIN varietat v ON v.id_varietat = p.id_varietat
            LEFT JOIN sector s ON s.id_sector = af.id_sector
            WHERE af.data_analisi BETWEEN :start AND :end
            ORDER BY af.data_analisi ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $etapa = ucwords(str_replace('_', ' ', (string)$row['estat_fenologic']));
            $varietat = trim((string)($row['nom_varietat'] ?? ''));
            $title = $varietat !== '' ? $etapa . ' - ' . $varietat : $etapa;
            $description = (string)$row['nom_sector'];
            if (!empty($row['observacions'])) {
                $description .= ' - ' . $row['observacions'];
            }
            $events[] = [
                'id' => 'fenologic-' . $row['id_analisi_foliar'],
                'title' => $title,
                'start' => $row['data_analisi'],
                'allDay' => true,
                'description' => $description,
                'color' => '#16a085',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'fenologic',
                    'etapa' => $row['estat_fenologic'],
                    'varietat' => $row['nom_varietat'],
                    'sector' => $row['nom_sector'],
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                mp.id_monitoratge,
                mp.data_observacio,
                mp.tipus_problema,
                mp.descripcio_breu,
                mp.nivell_poblacio,
                mp.llindar_intervencio_assolit,
                COALESCE(s.nom, 'Sense sector') AS nom_sector
            FROM monitoratge_plaga mp
            LEFT JOIN sector s ON s.id_sector = mp.id_sector
            WHERE DATE(mp.data_observacio) BETWEEN :start AND :end
            ORDER BY mp.data_observacio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $nivell = $row['nivell_poblacio']
                ? ' Nivell: ' . number_format((float)$row['nivell_poblacio'], 1, ',', '.')
                : '';
            $events[] = [
                'id' => 'monitoratge-' . $row['id_monitoratge'],
                'title' => 'Monitoratge: ' . $row['tipus_problema'],
                'start' => substr((string)$row['data_observacio'], 0, 10),
                'allDay' => true,
                'description' => trim((string)$row['nom_sector'] . (($row['descripcio_breu'] ?? '') ? ' - ' . $row['descripcio_breu'] : '') . $nivell),
                'color' => '#d35400',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'monitoratge',
                    'sector' => $row['nom_sector'],
                    'intervencio' => $row['llindar_intervencio_assolit'],
                ],
            ];
        }
        return $events;
    });

    $afegir(function () use ($pdo, $start, $end) {
        $sql = "
            SELECT
                cs.id_sector,
                cs.data_analisi,
                cs.pH,
                cs.N,
                cs.P,
                cs.K,
                s.nom AS nom_sector
            FROM caracteristiques_sol cs
            JOIN sector s ON cs.id_sector = s.id_sector
            WHERE cs.data_analisi BETWEEN :start AND :end
            ORDER BY cs.data_analisi ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $parts = [];
            if ($row['pH'] !== null) $parts[] = 'pH:' . number_format((float)$row['pH'], 1, ',', '.');
            if ($row['N'] !== null) $parts[] = 'N:' . number_format((float)$row['N'], 1, ',', '.');
            if ($row['P'] !== null) $parts[] = 'P:' . number_format((float)$row['P'], 1, ',', '.');
            if ($row['K'] !== null) $parts[] = 'K:' . number_format((float)$row['K'], 1, ',', '.');
            $suffix = $parts !== [] ? ' (' . implode(', ', $parts) . ')' : '';
            $events[] = [
                'id' => 'analisis-sol-' . $row['id_sector'] . '-' . $row['data_analisi'],
                'title' => 'Analisi sol' . $suffix,
                'start' => $row['data_analisi'],
                'allDay' => true,
                'description' => $row['nom_sector'],
                'color' => '#8e44ad',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'tipus' => 'analisis_sol',
                    'sector' => $row['nom_sector'],
                ],
            ];
        }
        return $events;
    });

    echo json_encode($esdeveniments, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[CultiuConnect] Error api_esdeveniments: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No s\'han pogut carregar els esdeveniments.'], JSON_UNESCAPED_UNICODE);
}
