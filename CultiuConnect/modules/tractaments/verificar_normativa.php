<?php
/**
 * modules/tractaments/verificar_normativa.php
 *
 * Endpoint AJAX (POST) de verificació normativa fitosanitària.
 * Retorna un JSON amb la llista d'alertes i advertències per al producte,
 * sector i data indicats. Cap dada s'escriu a la BD; és només consulta.
 *
 * Paràmetres POST esperats:
 *   id_producte   (int)    — producte_quimic.id_producte
 *   id_sector     (int)    — sector.id_sector
 *   data_prevista (string) — format YYYY-MM-DD
 *   dosi_ha       (float)  — dosi prevista en l/ha o kg/ha
 *
 * Resposta JSON:
 * {
 *   "ok": true,
 *   "bloqueig": false,          // true si cal impedir el formulari
 *   "alertes": [
 *     { "tipus": "error"|"avis"|"ok", "codi": "...", "text": "..." }
 *   ],
 *   "producte": {               // dades normatives del producte (per mostrar-les)
 *     "nom": "...",
 *     "termini_seguretat_dies": 14,
 *     "dosi_max_ha": 2.5,
 *     "num_aplicacions_max": 3,
 *     "classificacio_tox": "...",
 *     "fitxa_seguretat_link": "..."
 *   }
 * }
 */

declare(strict_types=1);

// Acceptem només POST + JSON
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'missatge' => 'Mètode no permès.']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// ── Lectura i sanejament d'entrada ────────────────────────────────────────────
$id_producte   = filter_input(INPUT_POST, 'id_producte',   FILTER_VALIDATE_INT);
$id_sector     = filter_input(INPUT_POST, 'id_sector',     FILTER_VALIDATE_INT);
$data_prevista = trim($_POST['data_prevista'] ?? '');
$dosi_ha       = filter_input(INPUT_POST, 'dosi_ha', FILTER_VALIDATE_FLOAT);

// Validació bàsica
if (!$id_producte || !$id_sector || !$data_prevista) {
    echo json_encode(['ok' => false, 'missatge' => 'Falten paràmetres obligatoris.']);
    exit;
}

$data_obj = DateTime::createFromFormat('Y-m-d', $data_prevista);
if (!$data_obj || $data_obj->format('Y-m-d') !== $data_prevista) {
    echo json_encode(['ok' => false, 'missatge' => 'Data no vàlida.']);
    exit;
}

// ── Connexió i consultes ──────────────────────────────────────────────────────
try {
    $pdo = connectDB();
    $alertes  = [];
    $bloqueig = false;

    // 1. Dades normatives del producte
    // ─────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT nom_comercial,
               termini_seguretat_dies,
               dosi_max_ha,
               num_aplicacions_max,
               classificacio_tox,
               fitxa_seguretat_link,
               tipus
        FROM producte_quimic
        WHERE id_producte = :id
    ");
    $stmt->execute([':id' => $id_producte]);
    $producte = $stmt->fetch();

    if (!$producte) {
        echo json_encode(['ok' => false, 'missatge' => 'Producte no trobat.']);
        exit;
    }

    // 2. CHECK: Termini de seguretat vs. previsió de collita del sector
    // ─────────────────────────────────────────────────────────────────
    // Busquem la data d'inici de collita estimada més propera per al sector
    // a partir de l'any en curs, unint plantacio → previsio_collita.
    if ($producte['termini_seguretat_dies'] !== null) {
        $stmt2 = $pdo->prepare("
            SELECT pc.data_inici_collita_estimada,
                   pc.data_fi_collita_estimada,
                   pc.temporada,
                   v.nom_varietat
            FROM previsio_collita  pc
            JOIN plantacio         pl ON pl.id_plantacio = pc.id_plantacio
            JOIN varietat          v  ON v.id_varietat   = pl.id_varietat
            WHERE pl.id_sector = :sector
              AND pc.data_inici_collita_estimada IS NOT NULL
              AND pc.data_inici_collita_estimada >= CURDATE()
            ORDER BY pc.data_inici_collita_estimada ASC
            LIMIT 1
        ");
        $stmt2->execute([':sector' => $id_sector]);
        $collita = $stmt2->fetch();

        if ($collita) {
            $termini        = (int)$producte['termini_seguretat_dies'];
            $data_limit_obj = clone $data_obj;
            $data_limit_obj->modify("+{$termini} days");

            $data_collita_obj = new DateTime($collita['data_inici_collita_estimada']);

            if ($data_limit_obj > $data_collita_obj) {
                // Calculem quants dies de marge falten
                $diff_dies = (int)$data_obj->diff($data_collita_obj)->days;
                $alertes[] = [
                    'tipus' => 'error',
                    'codi'  => 'TERMINI_SEGURETAT',
                    'text'  => sprintf(
                        '⛔ TERMINI DE SEGURETAT INCOMPLIT: %s requereix %d dies de seguretat abans de la collita. '
                        . 'La previsió de collita de "%s" és el %s (%d dies). '
                        . 'La data de tractament ha de ser anterior al %s.',
                        $producte['nom_comercial'],
                        $termini,
                        $collita['nom_varietat'],
                        (new DateTime($collita['data_inici_collita_estimada']))->format('d/m/Y'),
                        $diff_dies,
                        $data_collita_obj->modify("-{$termini} days")->format('d/m/Y')
                    ),
                ];
                $bloqueig = true;
            } else {
                // Tot bé, però avisem si el marge és ajustat (menys de 7 dies extra)
                $dies_marge = (int)$data_obj->diff($data_collita_obj)->days - $termini;
                if ($dies_marge < 7) {
                    $alertes[] = [
                        'tipus' => 'avis',
                        'codi'  => 'TERMINI_SEGURETAT_AJUSTAT',
                        'text'  => sprintf(
                            '⚠️ Marge ajustat: el termini de seguretat (%d dies) es compleix, però amb tan sols %d dies de marge. '
                            . 'Confirma que la data de collita no s\'avançarà.',
                            $termini,
                            $dies_marge
                        ),
                    ];
                } else {
                    $alertes[] = [
                        'tipus' => 'ok',
                        'codi'  => 'TERMINI_SEGURETAT_OK',
                        'text'  => sprintf(
                            '✅ Termini de seguretat: %d dies requerits, marge de %d dies fins a la collita prevista (%s).',
                            $termini,
                            $dies_marge,
                            (new DateTime($collita['data_inici_collita_estimada']))->format('d/m/Y')
                        ),
                    ];
                }
            }
        } else {
            // No hi ha previsió de collita — no bloquegem però avisem
            $alertes[] = [
                'tipus' => 'avis',
                'codi'  => 'SENSE_PREVISIO_COLLITA',
                'text'  => sprintf(
                    '⚠️ No s\'ha trobat cap previsió de collita per a aquest sector. '
                    . 'Tingues en compte que %s té un termini de seguretat de %d dies.',
                    $producte['nom_comercial'],
                    (int)$producte['termini_seguretat_dies']
                ),
            ];
        }
    }

    // 2b. CHECK: Termini (data + seguretat) vs. finestra de floració (observacions foliar)
    // ───────────────────────────────────────────────────────────────────────
    // No existeix una taula "previsio_floracio" a l'esquema actual. Fem servir
    // l'últim registre d'analítica foliar amb estat_fenologic='floracio' com a
    // proxy d'observació/calendari fenològic del sector.
    //
    // Regla:
    // - Definim finestra floració = data_floracio ± 7 dies
    // - Si l'interval [data_prevista, data_prevista + termini_seguretat] solapa la finestra,
    //   avisem i (si és Fitosanitari/Herbicida) bloquegem per risc d'afectació a floració/pol·linitzadors.
    if ($producte['termini_seguretat_dies'] !== null) {
        $stmtF = $pdo->prepare("
            SELECT af.data_analisi
            FROM analisi_foliar af
            WHERE af.id_sector = :sector
              AND af.estat_fenologic = 'floracio'
              AND YEAR(af.data_analisi) = YEAR(:data_prevista)
            ORDER BY af.data_analisi DESC
            LIMIT 1
        ");
        $stmtF->execute([
            ':sector' => $id_sector,
            ':data_prevista' => $data_prevista,
        ]);
        $fl = $stmtF->fetch();

        if ($fl && !empty($fl['data_analisi'])) {
            $termini = (int)$producte['termini_seguretat_dies'];
            $iniciTr = clone $data_obj;
            $fiTr    = (clone $data_obj)->modify("+{$termini} days");

            $dataFlor = new DateTime((string)$fl['data_analisi']);
            $iniFlor  = (clone $dataFlor)->modify('-7 days');
            $fiFlor   = (clone $dataFlor)->modify('+7 days');

            $solapa = ($iniciTr <= $fiFlor) && ($fiTr >= $iniFlor);
            if ($solapa) {
                $es_risc_alt = in_array(($producte['tipus'] ?? ''), ['Fitosanitari', 'Herbicida'], true);
                $alertes[] = [
                    'tipus' => $es_risc_alt ? 'error' : 'avis',
                    'codi'  => 'FINESTRA_FLORACIO',
                    'text'  => sprintf(
                        '%s FINESTRA DE FLORACIÓ: el tractament (%s) + termini de seguretat (%d dies) solapa la floració observada (%s ± 7 dies).',
                        $es_risc_alt ? '⛔' : '⚠️',
                        $data_prevista,
                        $termini,
                        $dataFlor->format('d/m/Y')
                    ),
                ];
                if ($es_risc_alt) {
                    $bloqueig = true;
                }
            } else {
                $alertes[] = [
                    'tipus' => 'ok',
                    'codi'  => 'FINESTRA_FLORACIO_OK',
                    'text'  => '✅ No hi ha solapament amb la finestra de floració (segons analítiques foliars).',
                ];
            }
        } else {
            $alertes[] = [
                'tipus' => 'info',
                'codi'  => 'SENSE_FLORACIO',
                'text'  => 'ℹ️ Sense dades de floració (analítica foliar) per aquest sector/any. No es pot verificar el solapament amb floració.',
            ];
        }
    }

    // 3. CHECK: Dosi màxima per hectàrea
    // ────────────────────────────────────
    if ($dosi_ha !== null && $dosi_ha !== false && $producte['dosi_max_ha'] !== null) {
        $dosi_max = (float)$producte['dosi_max_ha'];
        if ($dosi_ha > $dosi_max) {
            $alertes[] = [
                'tipus' => 'error',
                'codi'  => 'DOSI_EXCESSIVA',
                'text'  => sprintf(
                    '⛔ DOSI SUPERADA: Has introduït %.2f %s/ha però la dosi màxima legal de %s és %.2f %s/ha.',
                    $dosi_ha,
                    'l·kg',
                    $producte['nom_comercial'],
                    $dosi_max,
                    'l·kg'
                ),
            ];
            $bloqueig = true;
        } else {
            $alertes[] = [
                'tipus' => 'ok',
                'codi'  => 'DOSI_OK',
                'text'  => sprintf(
                    '✅ Dosi correcta: %.2f sobre un màxim de %.2f.',
                    $dosi_ha,
                    $dosi_max
                ),
            ];
        }
    } elseif ($producte['dosi_max_ha'] !== null) {
        // Camp de dosi buit, però el producte en té: avís no bloquejant
        $alertes[] = [
            'tipus' => 'avis',
            'codi'  => 'DOSI_NO_INTRODUIDA',
            'text'  => sprintf(
                '⚠️ Introdueix la dosi prevista per verificar-la. Màxim permès: %.2f.',
                (float)$producte['dosi_max_ha']
            ),
        ];
    }

    // 3b. Informació: matèries actives (desglossament per dosi prevista)
    // ─────────────────────────────────────────────────────────────────
    if ($dosi_ha !== null && $dosi_ha !== false) {
        $stmtMA = $pdo->prepare("
            SELECT ma.nom AS materia, pm.concentracio
            FROM producte_ma pm
            JOIN materia_activa ma ON ma.id_materia_activa = pm.id_materia_activa
            WHERE pm.id_producte = :id_producte
            ORDER BY ma.nom ASC
        ");
        $stmtMA->execute([':id_producte' => $id_producte]);
        $mas = $stmtMA->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($mas)) {
            foreach ($mas as $m) {
                $conc = (float)($m['concentracio'] ?? 0);
                if ($conc <= 0) continue;
                $actiu = $dosi_ha * ($conc / 100.0);
                $alertes[] = [
                    'tipus' => 'info',
                    'codi'  => 'MATERIA_ACTIVA',
                    'text'  => sprintf(
                        'ℹ️ Matèria activa: %s (%.2f%%). A %.2f/ha → %.3f actiu/ha.',
                        (string)$m['materia'],
                        $conc,
                        (float)$dosi_ha,
                        $actiu
                    ),
                ];
            }
        }
    }

    // 4. CHECK: Nombre màxim d'aplicacions per temporada i sector
    // ────────────────────────────────────────────────────────────
    if ($producte['num_aplicacions_max'] !== null) {
        $any_actual = (int)$data_obj->format('Y');
        $max_apl    = (int)$producte['num_aplicacions_max'];

        // Compta les aplicacions d'aquest producte al sector en la temporada actual
        // a través de aplicacio → detall_aplicacio_producte → inventari_estoc
        $stmt3 = $pdo->prepare("
            SELECT COUNT(DISTINCT a.id_aplicacio) AS total
            FROM aplicacio                 a
            JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
            JOIN inventari_estoc           ie  ON ie.id_estoc      = dap.id_estoc
            WHERE a.id_sector  = :sector
              AND ie.id_producte = :producte
              AND YEAR(a.data_event) = :any
        ");
        $stmt3->execute([
            ':sector'   => $id_sector,
            ':producte' => $id_producte,
            ':any'      => $any_actual,
        ]);
        $total_aplicacions = (int)$stmt3->fetchColumn();

        // Compta també les planificades pendents (tractament_programat)
        $stmt4 = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM tractament_programat
            WHERE id_sector   = :sector
              AND id_producte  = :producte
              AND estat        = 'pendent'
              AND YEAR(data_prevista) = :any
        ");
        $stmt4->execute([
            ':sector'   => $id_sector,
            ':producte' => $id_producte,
            ':any'      => $any_actual,
        ]);
        $total_planificades = (int)$stmt4->fetchColumn();
        $total_combinat     = $total_aplicacions + $total_planificades;

        if ($total_combinat >= $max_apl) {
            $alertes[] = [
                'tipus' => 'error',
                'codi'  => 'MAX_APLICACIONS_ASSOLIT',
                'text'  => sprintf(
                    '⛔ NOMBRE MÀXIM D\'APLICACIONS ASSOLIT: %s permet un màxim de %d aplicació/ns per temporada '
                    . 'i ja n\'hi ha %d de registrades o planificades en aquest sector per a %d.',
                    $producte['nom_comercial'],
                    $max_apl,
                    $total_combinat,
                    $any_actual
                ),
            ];
            $bloqueig = true;
        } elseif (($total_combinat + 1) === $max_apl) {
            $alertes[] = [
                'tipus' => 'avis',
                'codi'  => 'MAX_APLICACIONS_ULTIMA',
                'text'  => sprintf(
                    '⚠️ Atenció: aquesta serà l\'ÚLTIMA aplicació permesa de %s per a %d en aquest sector '
                    . '(%d/%d).',
                    $producte['nom_comercial'],
                    $any_actual,
                    $total_combinat + 1,
                    $max_apl
                ),
            ];
        } else {
            $alertes[] = [
                'tipus' => 'ok',
                'codi'  => 'APLICACIONS_OK',
                'text'  => sprintf(
                    '✅ Aplicacions: %d de %d màximes usades en temporada %d.',
                    $total_combinat,
                    $max_apl,
                    $any_actual
                ),
            ];
        }
    }

    // 5. Informació extra: toxicitat i fitxa de seguretat
    // ─────────────────────────────────────────────────────
    if (!empty($producte['classificacio_tox'])) {
        $alertes[] = [
            'tipus' => 'info',
            'codi'  => 'TOXICITAT',
            'text'  => 'ℹ️ Classificació toxicològica: ' . $producte['classificacio_tox'],
        ];
    }

    // ── Resposta ─────────────────────────────────────────────────────────────
    echo json_encode([
        'ok'       => true,
        'bloqueig' => $bloqueig,
        'alertes'  => $alertes,
        'producte' => [
            'nom'                    => $producte['nom_comercial'],
            'termini_seguretat_dies' => $producte['termini_seguretat_dies'],
            'dosi_max_ha'            => $producte['dosi_max_ha'],
            'num_aplicacions_max'    => $producte['num_aplicacions_max'],
            'classificacio_tox'      => $producte['classificacio_tox'],
            'fitxa_seguretat_link'   => $producte['fitxa_seguretat_link'],
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[CultiuConnect] verificar_normativa.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'missatge' => 'Error intern al servidor.']);
}