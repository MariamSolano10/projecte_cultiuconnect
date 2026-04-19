<?php
/**
 * modules/fila_aplicacio/fila_aplicacio.php
 *
 * GestiÃ³ del registre de files tractades per aplicaciÃ³.
 * Permet seleccionar una aplicaciÃ³ i marcar/desmarcar quines files
 * han rebut el tractament, amb estat (completada, en_proces, aturada, pendent).
 *
 * CORRECCIONS v2:
 *  - Recupera i mostra percentatge_complet i longitud_tractada_m
 *  - Endpoint AJAX integrat (?ajax=1) per actualitzar estat d'una fila sense
 *    recarregar la pÃ gina (usat des del mapa GIS i des del tractor en camp)
 *  - DistinciÃ³ visual de tots 4 estats (completada / en_proces / aturada / pendent)
 *  - coordenada_final s'actualitza des de l'AJAX quan el tractor envia GPS real
 *  - Evita duplicats a la cÃ rrega: GROUP BY per si una fila tÃ© mÃºltiples treballadors
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if (isset($_GET['ajax']) && !defined('CULTIUCONNECT_AJAX_FILA_APLICACIO_V2')) {
    define('CULTIUCONNECT_AJAX_FILA_APLICACIO_V2', true);
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'MÃ¨tode no permÃ¨s.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Cos JSON invÃ lid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id_fila = isset($input['id_fila']) ? (int)$input['id_fila'] : 0;
    $id_aplicacio = isset($input['id_aplicacio']) ? (int)$input['id_aplicacio'] : 0;
    $id_treballador = isset($input['id_treballador']) ? (int)$input['id_treballador'] : 0;
    $estat = $input['estat'] ?? null;
    $lat = isset($input['lat']) ? (float)$input['lat'] : null;
    $lng = isset($input['lng']) ? (float)$input['lng'] : null;
    $percentatge = isset($input['percentatge']) ? (float)$input['percentatge'] : null;
    $longitud_m = isset($input['longitud_m']) ? (float)$input['longitud_m'] : null;
    $data_fi = $input['data_fi'] ?? null;

    $estats_valids = ['pendent', 'en_proces', 'completada', 'aturada'];
    if (!$id_fila || !$id_aplicacio || !in_array($estat, $estats_valids, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ParÃ metres invÃ lids.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($lat !== null && !is_finite($lat)) || ($lng !== null && !is_finite($lng))) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Coordenades invÃ lides.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($lat !== null && ($lat < -90 || $lat > 90)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Latitud fora de rang.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($lng !== null && ($lng < -180 || $lng > 180)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Longitud fora de rang.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo = connectDB();

        if ($id_treballador <= 0) {
            $stmtT = $pdo->prepare("SELECT id_treballador FROM aplicacio WHERE id_aplicacio = :id LIMIT 1");
            $stmtT->execute([':id' => $id_aplicacio]);
            $id_treballador = (int)($stmtT->fetchColumn() ?: 0);
        }
        if ($id_treballador <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Treballador no determinat per a aquesta aplicaciÃ³.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtCheck = $pdo->prepare("
            SELECT fa.id_fila
            FROM fila_arbre fa
            JOIN aplicacio a ON a.id_sector = fa.id_sector
            WHERE fa.id_fila = :fila AND a.id_aplicacio = :aplic
            LIMIT 1
        ");
        $stmtCheck->execute([':fila' => $id_fila, ':aplic' => $id_aplicacio]);
        if (!$stmtCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Fila no pertany a aquesta aplicaciÃ³.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $coordWkt = ($lat !== null && $lng !== null)
            ? sprintf('POINT(%F %F)', $lng, $lat)
            : 'POINT(0 0)';

        $stmtEx = $pdo->prepare("
            SELECT data_inici FROM fila_aplicacio
            WHERE id_fila_aplicada = :fila
              AND id_aplicacio     = :aplic
              AND id_treballador   = :treb
        ");
        $stmtEx->execute([':fila' => $id_fila, ':aplic' => $id_aplicacio, ':treb' => $id_treballador]);
        $existent = $stmtEx->fetch();

        if ($existent) {
            $setCols = ['estat = :estat'];
            $params = [
                ':estat' => $estat,
                ':fila' => $id_fila,
                ':aplic' => $id_aplicacio,
                ':treb' => $id_treballador,
            ];

            if ($data_fi !== null) {
                $setCols[] = 'data_fi = :data_fi';
                $params[':data_fi'] = $data_fi ?: null;
            }
            if ($percentatge !== null) {
                $setCols[] = 'percentatge_complet = :pct';
                $params[':pct'] = max(0, min(100, $percentatge));
            }
            if ($longitud_m !== null) {
                $setCols[] = 'longitud_tractada_m = :long_m';
                $params[':long_m'] = $longitud_m;
            }
            if ($lat !== null && $lng !== null) {
                $setCols[] = 'coordenada_final = ST_GeomFromText(:coord_wkt)';
                $params[':coord_wkt'] = $coordWkt;
            }

            $sql = "UPDATE fila_aplicacio SET " . implode(', ', $setCols) . "
                    WHERE id_fila_aplicada = :fila
                      AND id_aplicacio     = :aplic
                      AND id_treballador   = :treb";
            $pdo->prepare($sql)->execute($params);
        } else {
            $pdo->prepare("
                INSERT INTO fila_aplicacio
                    (id_fila_aplicada, id_aplicacio, id_treballador,
                     data_inici, data_fi, estat,
                     percentatge_complet, longitud_tractada_m, coordenada_final)
                VALUES
                    (:fila, :aplic, :treb,
                     NOW(), :data_fi, :estat,
                     :pct, :long_m, ST_GeomFromText(:coord_wkt))
            ")->execute([
                ':fila' => $id_fila,
                ':aplic' => $id_aplicacio,
                ':treb' => $id_treballador,
                ':data_fi' => $data_fi ?: null,
                ':estat' => $estat,
                ':pct' => ($percentatge !== null) ? max(0, min(100, $percentatge)) : ($estat === 'completada' ? 100 : 0),
                ':long_m' => $longitud_m,
                ':coord_wkt' => $coordWkt,
            ]);
        }

        echo json_encode(['ok' => true, 'estat' => $estat], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        error_log('[CultiuConnect] fila_aplicacio.php AJAX V2: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error intern del servidor.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
// ENDPOINT AJAX â€” ?ajax=1  (usat des de mapa_gis.php i des de dispositius mÃ²bils)
// Accepta POST JSON: { id_fila, id_aplicacio, id_treballador, estat,
//                      lat?, lng?, percentatge?, longitud_m?, data_fi? }
// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
if (false && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $input = json_decode(file_get_contents('php://input'), true);

    $id_fila       = isset($input['id_fila'])         ? (int)$input['id_fila']         : 0;
    $id_aplicacio  = isset($input['id_aplicacio'])    ? (int)$input['id_aplicacio']    : 0;
    $id_treballador = isset($input['id_treballador']) ? (int)$input['id_treballador']  : 0;
    $estat         = $input['estat']                  ?? null;
    $lat           = isset($input['lat'])             ? (float)$input['lat']           : null;
    $lng           = isset($input['lng'])             ? (float)$input['lng']           : null;
    $percentatge   = isset($input['percentatge'])     ? (float)$input['percentatge']   : null;
    $longitud_m    = isset($input['longitud_m'])      ? (float)$input['longitud_m']    : null;
    $data_fi       = $input['data_fi']                ?? null;

    $estats_valids = ['pendent', 'en_proces', 'completada', 'aturada'];

    if (!$id_fila || !$id_aplicacio || !in_array($estat, $estats_valids, true)) {
        echo json_encode(['ok' => false, 'error' => 'ParÃ metres invÃ lids.']);
        exit;
    }

    try {
        $pdo = connectDB();

        // id_treballador=0 â†’ usem el treballador associat a l'aplicaciÃ³
        if ($id_treballador <= 0) {
            $stmtT = $pdo->prepare("SELECT id_treballador FROM aplicacio WHERE id_aplicacio = :id LIMIT 1");
            $stmtT->execute([':id' => $id_aplicacio]);
            $id_treballador = (int)($stmtT->fetchColumn() ?: 0);
        }
        if ($id_treballador <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Treballador no determinat per a aquesta aplicaciÃ³.']);
            exit;
        }

        // Comprova que la fila pertany al sector de l'aplicaciÃ³ (seguretat)
        $stmtCheck = $pdo->prepare("
            SELECT fa.id_fila
            FROM fila_arbre fa
            JOIN aplicacio a ON a.id_sector = fa.id_sector
            WHERE fa.id_fila = :fila AND a.id_aplicacio = :aplic
            LIMIT 1
        ");
        $stmtCheck->execute([':fila' => $id_fila, ':aplic' => $id_aplicacio]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Fila no pertany a aquesta aplicaciÃ³.']);
            exit;
        }

        // ConstruÃ¯m la coordenada final: usem el GPS rebut si existeix, altrament mantenim POINT(0 0)
        $coordSql = ($lat !== null && $lng !== null)
            ? "ST_GeomFromText('POINT($lng $lat)')"
            : "ST_GeomFromText('POINT(0 0)')";

        // Comprova si ja existeix el registre
        $stmtEx = $pdo->prepare("
            SELECT data_inici FROM fila_aplicacio
            WHERE id_fila_aplicada = :fila
              AND id_aplicacio     = :aplic
              AND id_treballador   = :treb
        ");
        $stmtEx->execute([':fila' => $id_fila, ':aplic' => $id_aplicacio, ':treb' => $id_treballador]);
        $existent = $stmtEx->fetch();

        if ($existent) {
            // UPDATE: actualitzem l'estat i, opcionalment, la coordenada GPS i el progrÃ©s
            $setCols = ['estat = :estat'];
            $params  = [
                ':estat' => $estat,
                ':fila'  => $id_fila,
                ':aplic' => $id_aplicacio,
                ':treb'  => $id_treballador,
            ];

            if ($data_fi !== null) {
                $setCols[] = 'data_fi = :data_fi';
                $params[':data_fi'] = $data_fi ?: null;
            }
            if ($percentatge !== null) {
                $setCols[] = 'percentatge_complet = :pct';
                $params[':pct'] = max(0, min(100, $percentatge));
            }
            if ($longitud_m !== null) {
                $setCols[] = 'longitud_tractada_m = :long_m';
                $params[':long_m'] = $longitud_m;
            }
            if ($lat !== null && $lng !== null) {
                $setCols[] = "coordenada_final = $coordSql";
            }

            $sql = "UPDATE fila_aplicacio SET " . implode(', ', $setCols) . "
                    WHERE id_fila_aplicada = :fila
                      AND id_aplicacio     = :aplic
                      AND id_treballador   = :treb";
            $pdo->prepare($sql)->execute($params);

        } else {
            // INSERT nou registre
            $pdo->prepare("
                INSERT INTO fila_aplicacio
                    (id_fila_aplicada, id_aplicacio, id_treballador,
                     data_inici, data_fi, estat,
                     percentatge_complet, longitud_tractada_m, coordenada_final)
                VALUES
                    (:fila, :aplic, :treb,
                     NOW(), :data_fi, :estat,
                     :pct, :long_m, $coordSql)
            ")->execute([
                ':fila'   => $id_fila,
                ':aplic'  => $id_aplicacio,
                ':treb'   => $id_treballador,
                ':data_fi' => $data_fi ?: null,
                ':estat'  => $estat,
                ':pct'    => ($percentatge !== null) ? max(0, min(100, $percentatge)) : ($estat === 'completada' ? 100 : 0),
                ':long_m' => $longitud_m,
            ]);
        }

        echo json_encode(['ok' => true, 'estat' => $estat]);

    } catch (Exception $e) {
        error_log('[CultiuConnect] fila_aplicacio.php AJAX: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Error intern del servidor.']);
    }
    exit;
}

// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
// POST FORMULARI â€” desar tot el registre de files d'un cop
// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
$titol_pagina  = 'Registre de Files Tractades';
$pagina_activa = 'fila_aplicacio';

$aplicacions    = [];
$files_sector   = [];
$files_tractades = [];
$aplicacio_sel  = null;
$error_db       = null;
$treballadors   = [];

$id_aplicacio = is_numeric($_GET['id_aplicacio'] ?? '') ? (int)$_GET['id_aplicacio'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio         = $_POST['accio']        ?? '';
    $id_aplic_post = (int)($_POST['id_aplicacio'] ?? 0);

    try {
        $pdo = connectDB();

        if ($accio === 'desar_files' && $id_aplic_post) {

            $files_marcades     = is_array($_POST['files_marcades'] ?? null) ? $_POST['files_marcades'] : [];
            $estats_files       = is_array($_POST['estat_fila'] ?? null) ? $_POST['estat_fila'] : [];
            $treballadors_fila  = is_array($_POST['treballador_fila'] ?? null) ? $_POST['treballador_fila'] : [];
            $inici_fila         = is_array($_POST['inici_fila'] ?? null) ? $_POST['inici_fila'] : [];
            $fi_fila            = is_array($_POST['fi_fila'] ?? null) ? $_POST['fi_fila'] : [];
            $pct_fila           = is_array($_POST['pct_fila'] ?? null) ? $_POST['pct_fila'] : [];
            $long_fila          = is_array($_POST['long_fila'] ?? null) ? $_POST['long_fila'] : [];

            // Totes les files del sector d'aquesta aplicaciÃ³
            $stmtFa = $pdo->prepare("
                SELECT fa.id_fila FROM fila_arbre fa
                JOIN aplicacio a ON a.id_sector = fa.id_sector
                WHERE a.id_aplicacio = :id
            ");
            $stmtFa->execute([':id' => $id_aplic_post]);
            $totes_files = $stmtFa->fetchAll(PDO::FETCH_COLUMN);

            // Treballador per defecte de l'aplicaciÃ³
            $stmtTreb = $pdo->prepare("SELECT id_treballador FROM aplicacio WHERE id_aplicacio = :id");
            $stmtTreb->execute([':id' => $id_aplic_post]);
            $treb_defecte = (int)($stmtTreb->fetchColumn() ?: 0);

            foreach ($totes_files as $id_fila) {
                $marcada    = in_array((string)$id_fila, array_map('strval', $files_marcades), true);
                $estat      = $estats_files[$id_fila]       ?? 'completada';
                $id_treb    = !empty($treballadors_fila[$id_fila])
                              ? (int)$treballadors_fila[$id_fila]
                              : $treb_defecte;
                $data_inici = !empty($inici_fila[$id_fila])   ? $inici_fila[$id_fila]  : null;
                $data_fi    = !empty($fi_fila[$id_fila])      ? $fi_fila[$id_fila]      : null;
                $pct        = isset($pct_fila[$id_fila])      ? (float)$pct_fila[$id_fila] : null;
                $long_m     = isset($long_fila[$id_fila])     ? (float)$long_fila[$id_fila] : null;

                // Comprova si ja existeix (considerant la clau composta)
                $stmtEx = $pdo->prepare("
                    SELECT 1 FROM fila_aplicacio
                    WHERE id_fila_aplicada = :fila
                      AND id_aplicacio     = :aplic
                      AND id_treballador   = :treb
                ");
                $stmtEx->execute([':fila' => $id_fila, ':aplic' => $id_aplic_post, ':treb' => $id_treb]);
                $existeix = (bool)$stmtEx->fetchColumn();

                if ($marcada && !$existeix && $id_treb) {
                    $pdo->prepare("
                        INSERT INTO fila_aplicacio
                            (id_fila_aplicada, id_aplicacio, id_treballador,
                             data_inici, data_fi, estat,
                             percentatge_complet, longitud_tractada_m, coordenada_final)
                        VALUES
                            (:fila, :aplic, :treb,
                             :inici, :fi, :estat,
                             :pct, :long_m, ST_GeomFromText('POINT(0 0)'))
                    ")->execute([
                        ':fila'   => $id_fila,
                        ':aplic'  => $id_aplic_post,
                        ':treb'   => $id_treb,
                        ':inici'  => $data_inici,
                        ':fi'     => $data_fi,
                        ':estat'  => $estat,
                        ':pct'    => $pct ?? ($estat === 'completada' ? 100 : 0),
                        ':long_m' => $long_m ?: null,
                    ]);

                } elseif ($marcada && $existeix) {
                    $pdo->prepare("
                        UPDATE fila_aplicacio
                        SET estat               = :estat,
                            data_inici          = :inici,
                            data_fi             = :fi,
                            percentatge_complet = :pct,
                            longitud_tractada_m = :long_m
                        WHERE id_fila_aplicada = :fila
                          AND id_aplicacio     = :aplic
                          AND id_treballador   = :treb
                    ")->execute([
                        ':estat'  => $estat,
                        ':inici'  => $data_inici,
                        ':fi'     => $data_fi,
                        ':pct'    => $pct ?? ($estat === 'completada' ? 100 : 0),
                        ':long_m' => $long_m ?: null,
                        ':fila'   => $id_fila,
                        ':aplic'  => $id_aplic_post,
                        ':treb'   => $id_treb,
                    ]);

                } elseif (!$marcada && $existeix) {
                    $pdo->prepare("
                        DELETE FROM fila_aplicacio
                        WHERE id_fila_aplicada = :fila
                          AND id_aplicacio     = :aplic
                          AND id_treballador   = :treb
                    ")->execute([':fila' => $id_fila, ':aplic' => $id_aplic_post, ':treb' => $id_treb]);
                }
            }

            set_flash('success', 'Registre de files actualitzat correctament.');
        }

    } catch (Exception $e) {
        error_log('[CultiuConnect] fila_aplicacio.php POST: ' . $e->getMessage());
        set_flash('error', 'Error intern en desar el registre.');
    }

    header('Location: ' . BASE_URL . 'modules/fila_aplicacio/fila_aplicacio.php?id_aplicacio=' . $id_aplic_post);
    exit;
}

// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
// GET â€” cÃ rrega de dades
// â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.â.
try {
    $pdo = connectDB();

    $aplicacions = $pdo->query("
        SELECT
            a.id_aplicacio,
            a.data_event,
            a.tipus_event,
            s.nom AS nom_sector
        FROM aplicacio a
        LEFT JOIN sector s ON s.id_sector = a.id_sector
        ORDER BY a.data_event DESC
        LIMIT 100
    ")->fetchAll();

    $treballadors = $pdo->query("
        SELECT id_treballador, nom, cognoms
        FROM treballador
        WHERE estat = 'actiu'
        ORDER BY cognoms, nom
    ")->fetchAll();

    if ($id_aplicacio > 0) {
        $stmt = $pdo->prepare("
            SELECT a.*, s.nom AS nom_sector,
                   t.nom AS nom_treb, t.cognoms AS cog_treb
            FROM aplicacio a
            LEFT JOIN sector     s ON s.id_sector     = a.id_sector
            LEFT JOIN treballador t ON t.id_treballador = a.id_treballador
            WHERE a.id_aplicacio = :id
        ");
        $stmt->execute([':id' => $id_aplicacio]);
        $aplicacio_sel = $stmt->fetch();

        if ($aplicacio_sel) {
            $idSectorAplicacio = (int)($aplicacio_sel['id_sector'] ?? 0);

            // Files del sector, ordenades per nÃºmero
            if ($idSectorAplicacio > 0) {
                $stmtF = $pdo->prepare("
                    SELECT
                        fa.id_fila,
                        fa.numero,
                        fa.descripcio,
                        fa.num_arbres
                    FROM fila_arbre fa
                    WHERE fa.id_sector = :id_sector
                    ORDER BY fa.numero
                ");
                $stmtF->execute([':id_sector' => $idSectorAplicacio]);
                $files_sector = $stmtF->fetchAll();
            } else {
                $files_sector = [];
            }

            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // CORRECCIÃ“: La clau primÃ ria de fila_aplicacio Ã©s composta
            // (id_fila_aplicada, id_aplicacio, id_treballador).
            // Per mostrar l'estat per fila usem el registre amb percentatge
            // mÃ©s alt (o el primer, si tots sÃ³n iguals).
            // S'eviten duplicats per fila amb GROUP BY + subconsulta.
            // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $stmtT = $pdo->prepare("
                SELECT
                    fa2.id_fila_aplicada,
                    fa2.id_treballador,
                    fa2.data_inici,
                    fa2.data_fi,
                    fa2.estat,
                    fa2.percentatge_complet,
                    fa2.longitud_tractada_m,
                    fa2.observacions
                FROM fila_aplicacio fa2
                INNER JOIN (
                    SELECT   id_fila_aplicada,
                             MAX(percentatge_complet) AS max_pct
                    FROM     fila_aplicacio
                    WHERE    id_aplicacio = :id
                    GROUP BY id_fila_aplicada
                ) sub ON sub.id_fila_aplicada = fa2.id_fila_aplicada
                      AND sub.max_pct        = fa2.percentatge_complet
                WHERE fa2.id_aplicacio = :id2
            ");
            $stmtT->execute([':id' => $id_aplicacio, ':id2' => $id_aplicacio]);
            $raw = $stmtT->fetchAll();

            foreach ($raw as $r) {
                $files_tractades[(int)$r['id_fila_aplicada']] = $r;
            }
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] fila_aplicacio.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades.';
}

// â”€â”€ Helpers de presentaciÃ³ â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function classeEstatFila(string $estat): string
{
    return match ($estat) {
        'completada' => 'badge--verd',
        'en_proces'  => 'badge--blau',
        'aturada'    => 'badge--groc',
        'pendent'    => 'badge--gris',
        default      => 'badge--gris',
    };
}

function iconaEstatFila(string $estat): string
{
    return match ($estat) {
        'completada' => 'fa-circle-check',
        'en_proces'  => 'fa-circle-half-stroke',
        'aturada'    => 'fa-circle-pause',
        'pendent'    => 'fa-circle',
        default      => 'fa-circle',
    };
}

function format_datetime_local(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-fila-aplicacio">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
            Registre de Files Tractades
        </h1>
        <p class="descripcio-seccio">
            Marca quines files d'arbres han rebut el tractament en cada aplicaciÃ³.
            La visualitzaciÃ³ en mapa Ã©s accessible des del
            <a href="<?= BASE_URL ?>modules/mapa/mapa_gis.php">Mapa GIS</a>.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- SELECTOR D'APLICACIÃ“ -->
    <div class="card card--spaced">
        <div class="card__header">
            <i class="fas fa-filter"></i> Selecciona una aplicaciÃ³
        </div>
        <div class="card__body">
            <form method="GET" class="form-fila form-fila--compact-end">
                <div class="form-camp form-camp--grow">
                    <label for="id_aplicacio">AplicaciÃ³</label>
                    <select name="id_aplicacio" id="id_aplicacio">
                        <option value="">â€” Selecciona una aplicaciÃ³ â€”</option>
                        <?php foreach ($aplicacions as $ap): ?>
                            <option value="<?= (int)$ap['id_aplicacio'] ?>"
                                <?= $id_aplicacio === (int)$ap['id_aplicacio'] ? 'selected' : '' ?>>
                                #<?= (int)$ap['id_aplicacio'] ?> â€”
                                <?= format_data($ap['data_event'], curta: true) ?> â€”
                                <?= e($ap['nom_sector'] ?? 'â€”') ?>
                                <?= $ap['tipus_event'] ? '(' . e($ap['tipus_event']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-camp">
                    <button type="submit" class="boto-principal">
                        <i class="fas fa-arrow-right"></i> Carregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($aplicacio_sel): ?>

    <!-- INFO DE L'APLICACIÃ“ -->
    <div class="card card--spaced">
        <div class="card__header">
            <i class="fas fa-info-circle"></i>
            AplicaciÃ³ #<?= $id_aplicacio ?> â€” <?= e($aplicacio_sel['nom_sector'] ?? 'â€”') ?>
        </div>
        <div class="card__body">
            <dl class="llista-detalls llista-detalls--auto">
                <dt>Data</dt>
                <dd><?= format_data($aplicacio_sel['data_event'], curta: true) ?></dd>
                <dt>Tipus</dt>
                <dd><?= $aplicacio_sel['tipus_event'] ? e($aplicacio_sel['tipus_event']) : 'â€”' ?></dd>
                <dt>Responsable</dt>
                <dd>
                    <?= $aplicacio_sel['nom_treb']
                        ? e($aplicacio_sel['nom_treb'] . ' ' . $aplicacio_sel['cog_treb'])
                        : 'â€”' ?>
                </dd>
                <dt>MÃ¨tode</dt>
                <dd><?= $aplicacio_sel['metode_aplicacio'] ? e($aplicacio_sel['metode_aplicacio']) : 'â€”' ?></dd>
            </dl>

            <!-- Resum de progrÃ©s -->
            <?php
            $total_files   = count($files_sector);
            $num_completades = count(array_filter($files_tractades, fn($f) => $f['estat'] === 'completada'));
            $num_en_proces   = count(array_filter($files_tractades, fn($f) => $f['estat'] === 'en_proces'));
            $num_aturades    = count(array_filter($files_tractades, fn($f) => $f['estat'] === 'aturada'));
            $num_tractades   = count($files_tractades); // totes les que tenen registre
            $pct = $total_files > 0 ? round($num_completades / $total_files * 100) : 0;
            ?>
            <div class="progressio-bloc">
                <div class="progressio-cap">
                    <span>ProgrÃ©s del tractament</span>
                    <strong>
                        <?= $num_completades ?> completades Â· 
                        <?= $num_en_proces ?> en procÃ©s Â· 
                        <?= $num_aturades ?> aturades Â· 
                        <?= $total_files - $num_tractades ?> pendents
                        (<?= $pct ?>% completat)
                    </strong>
                </div>
                <!-- Barra de progrÃ©s segmentada per estat -->
                <div class="progressio-barra">
                    <?php if ($total_files > 0): ?>
                        <div class="progressio-segment progressio-segment--completada progressio-segment-fill" data-width="<?= round($num_completades/$total_files*100) ?>" title="Completades"></div>
                        <div class="progressio-segment progressio-segment--proces progressio-segment-fill" data-width="<?= round($num_en_proces/$total_files*100) ?>" title="En procÃ©s"></div>
                        <div class="progressio-segment progressio-segment--aturada progressio-segment-fill" data-width="<?= round($num_aturades/$total_files*100) ?>" title="Aturades"></div>
                    <?php endif; ?>
                </div>
                <div class="progressio-llegenda">
                    <span><span class="progressio-llegenda__dot--completada">â- </span> Completada</span>
                    <span><span class="progressio-llegenda__dot--proces">â- </span> En procÃ©s</span>
                    <span><span class="progressio-llegenda__dot--aturada">â- </span> Aturada</span>
                    <span><span class="progressio-llegenda__dot--pendent">â- </span> Pendent</span>
                </div>
            </div>
        </div>
    </div>

    <!-- REGISTRE DE FILES -->
    <?php if (empty($files_sector)): ?>
        <div class="flash flash--info">
            <i class="fas fa-circle-info"></i>
            Aquest sector no tÃ© files d'arbres registrades.
            <a href="<?= BASE_URL ?>modules/sectors/detall_sector.php?id=<?= (int)$aplicacio_sel['id_sector'] ?>">
                Afegir files al sector
            </a>.
        </div>
    <?php else: ?>

    <form method="POST" class="card" id="form-files">
        <input type="hidden" name="accio"        value="desar_files">
        <input type="hidden" name="id_aplicacio" value="<?= $id_aplicacio ?>">

        <div class="card__header">
            <i class="fas fa-list-check"></i>
            Files del sector
            <div class="accions-dreta-inline">
                <!-- Indicador d'autoguardat AJAX -->
                <span id="estat-ajax" class="estat-ajax">
                    <i class="fas fa-spinner fa-spin"></i> Desantâ?¦
                </span>
                <button type="button" id="btn-marcar-tot" class="boto-secundari boto-text-petit">
                    <i class="fas fa-check-double"></i> Marcar totes
                </button>
                <button type="button" id="btn-desmarcar-tot" class="boto-secundari boto-text-petit">
                    <i class="fas fa-xmark"></i> Desmarcar totes
                </button>
            </div>
        </div>

        <div class="card__body card__body--scroll">
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th class="w-40">
                            <input type="checkbox" id="check-tot" title="Seleccionar totes">
                        </th>
                        <th>Fila</th>
                        <th>DescripciÃ³</th>
                        <th>Arbres</th>
                        <th>Estat</th>
                        <th>% Complet</th>
                        <th>Longitud (m)</th>
                        <th>Treballador</th>
                        <th>Inici real</th>
                        <th>Fi real</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files_sector as $fila):
                        $tractada  = isset($files_tractades[(int)$fila['id_fila']]);
                        $dades     = $files_tractades[(int)$fila['id_fila']] ?? [];
                        $estat_act = $dades['estat'] ?? 'pendent';
                        $pct_act   = $dades['percentatge_complet'] ?? ($estat_act === 'completada' ? 100 : 0);
                        $long_act  = $dades['longitud_tractada_m'] ?? '';
                    ?>
                    <tr class="fila-registre <?= $tractada ? 'fila--tractada' : '' ?>"
                        data-id-fila="<?= (int)$fila['id_fila'] ?>"
                        data-id-aplicacio="<?= $id_aplicacio ?>">
                        <td class="text-center">
                            <input type="checkbox"
                                   name="files_marcades[]"
                                   value="<?= (int)$fila['id_fila'] ?>"
                                   class="check-fila"
                                   <?= $tractada ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <strong>Fila <?= (int)$fila['numero'] ?></strong>
                            <?php if ($tractada): ?>
                                <span class="badge <?= classeEstatFila($estat_act) ?> badge--offset">
                                    <i class="fas <?= iconaEstatFila($estat_act) ?>"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $fila['descripcio'] ? e($fila['descripcio']) : 'â€”' ?></td>
                        <td><?= $fila['num_arbres'] ? (int)$fila['num_arbres'] : 'â€”' ?></td>
                        <td>
                            <select name="estat_fila[<?= (int)$fila['id_fila'] ?>]"
                                    class="select-estat-fila"
                                    <?= !$tractada ? 'disabled' : '' ?>>
                                <?php foreach ([
                                    'completada' => 'Completada',
                                    'en_proces'  => 'En procÃ©s',
                                    'aturada'    => 'Aturada',
                                    'pendent'    => 'Pendent',
                                ] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $estat_act === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number"
                                   name="pct_fila[<?= (int)$fila['id_fila'] ?>]"
                                   class="input-pct w-65"
                                   min="0" max="100" step="1"
                                   value="<?= (int)$pct_act ?>"
                                   <?= !$tractada ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <input type="number"
                                   name="long_fila[<?= (int)$fila['id_fila'] ?>]"
                                   class="input-long w-80"
                                   min="0" step="0.1"
                                   value="<?= $long_act !== '' ? e((string)$long_act) : '' ?>"
                                   placeholder="m"
                                   <?= !$tractada ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <select name="treballador_fila[<?= (int)$fila['id_fila'] ?>]"
                                    class="select-treb"
                                    <?= !$tractada ? 'disabled' : '' ?>>
                                <option value="">â€” Responsable â€”</option>
                                <?php foreach ($treballadors as $t): ?>
                                    <option value="<?= (int)$t['id_treballador'] ?>"
                                        <?= (int)($dades['id_treballador'] ?? 0) === (int)$t['id_treballador'] ? 'selected' : '' ?>>
                                        <?= e($t['nom'] . ' ' . ($t['cognoms'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="datetime-local"
                                   name="inici_fila[<?= (int)$fila['id_fila'] ?>]"
                                   value="<?= format_datetime_local($dades['data_inici'] ?? null) ?>"
                                   <?= !$tractada ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <input type="datetime-local"
                                   name="fi_fila[<?= (int)$fila['id_fila'] ?>]"
                                   value="<?= format_datetime_local($dades['data_fi'] ?? null) ?>"
                                   <?= !$tractada ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-accions form-accions--mt-l">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-save"></i> Desar registre de files
                </button>
                <a href="<?= BASE_URL ?>modules/mapa/mapa_gis.php?id_aplicacio=<?= $id_aplicacio ?>"
                   class="boto-secundari">
                    <i class="fas fa-map"></i> Veure al mapa
                </a>
            </div>
        </div>
    </form>

    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
(function () {
    'use strict';

    // â”€â”€ Checkbox mestre â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const checkTot  = document.getElementById('check-tot');
    const checksFila = document.querySelectorAll('.check-fila');
    const estatAjax  = document.getElementById('estat-ajax');
    const AJAX_URL   = '<?= BASE_URL ?>modules/fila_aplicacio/fila_aplicacio.php?ajax=1';

    function fetchJSON(url, options = {}) {
        return fetch(url, options).then(async (response) => {
            const text = await response.text();
            let data = {};

            try {
                data = text ? JSON.parse(text) : {};
            } catch (e) {
                throw new Error('Resposta invÃ lida del servidor.');
            }

            if (!response.ok) {
                throw new Error(data?.error || `Error HTTP ${response.status}`);
            }

            return data;
        });
    }

    function activarControls(filaTR, actiu) {
        filaTR.querySelectorAll('select, input[type="datetime-local"], input[type="number"]')
              .forEach(el => { el.disabled = !actiu; });
    }

    // Sincronitza estat visual del checkbox mestre
    function syncCheckTot() {
        const tots = [...checksFila];
        if (!checkTot) return;
        checkTot.checked       = tots.every(c => c.checked);
        checkTot.indeterminate = !checkTot.checked && tots.some(c => c.checked);
    }

    checksFila.forEach(ch => {
        activarControls(ch.closest('tr'), ch.checked);
        ch.addEventListener('change', function () {
            activarControls(this.closest('tr'), this.checked);
            syncCheckTot();
        });
    });

    checkTot?.addEventListener('change', function () {
        checksFila.forEach(ch => {
            ch.checked = this.checked;
            activarControls(ch.closest('tr'), this.checked);
        });
    });
    syncCheckTot();

    // Botons rÃ pids
    document.getElementById('btn-marcar-tot')?.addEventListener('click', () => {
        checksFila.forEach(ch => { ch.checked = true; activarControls(ch.closest('tr'), true); });
        if (checkTot) { checkTot.checked = true; checkTot.indeterminate = false; }
    });
    document.getElementById('btn-desmarcar-tot')?.addEventListener('click', () => {
        checksFila.forEach(ch => { ch.checked = false; activarControls(ch.closest('tr'), false); });
        if (checkTot) { checkTot.checked = false; checkTot.indeterminate = false; }
    });

    // â”€â”€ Autoguardat AJAX per canvi d'estat â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Quan l'operari canvia l'estat d'una fila, s'envia immediatament sense
    // necessitat de prÃ©mer "Desar". Ãstil des d'un mÃ²bil al camp.
    let _timerAjax = null;

    function desarFilaAjax(tr) {
        const idFila     = parseInt(tr.dataset.idFila);
        const idAplic    = parseInt(tr.dataset.idAplicacio);
        const estat      = tr.querySelector('.select-estat-fila')?.value || 'completada';
        const trebEl     = tr.querySelector('.select-treb');
        const idTreb     = trebEl ? parseInt(trebEl.value) || 0 : 0;
        const pct        = parseFloat(tr.querySelector('.input-pct')?.value) || 0;
        const longM      = parseFloat(tr.querySelector('.input-long')?.value) || null;
        const fiEl       = tr.querySelector('input[type="datetime-local"][name^="fi_fila"]');
        const dataFi     = fiEl?.value || null;

        if (!idFila || !idAplic || !idTreb) return;

        if (estatAjax) {
            estatAjax.style.display = 'inline-flex';
            estatAjax.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Desantâ?¦';
        }

        fetchJSON(AJAX_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_fila:        idFila,
                id_aplicacio:   idAplic,
                id_treballador: idTreb,
                estat,
                percentatge:    pct,
                longitud_m:     longM,
                data_fi:        dataFi,
            }),
        }).then(data => {
            if (estatAjax) {
                estatAjax.innerHTML = data.ok
                    ? '<i class="fas fa-circle-check icona-estat--ok"></i> Desat'
                    : '<i class="fas fa-circle-xmark icona-estat--error"></i> Error';
                setTimeout(() => { estatAjax.style.display = 'none'; }, 2500);
            }
        })
        .catch(() => {
            if (estatAjax) {
                estatAjax.innerHTML = '<i class="fas fa-wifi icona-estat--error"></i> Sense connexiÃ³';
                setTimeout(() => { estatAjax.style.display = 'none'; }, 3000);
            }
        });
    }

    // Escolta els canvis en els selects d'estat per activar autoguardat (debounced 600ms)
    document.querySelectorAll('.select-estat-fila').forEach(sel => {
        sel.addEventListener('change', function () {
            clearTimeout(_timerAjax);
            _timerAjax = setTimeout(() => desarFilaAjax(this.closest('tr')), 600);
        });
    });

    document.querySelectorAll('.progressio-segment-fill[data-width]').forEach(seg => {
        seg.style.width = `${seg.dataset.width}%`;
    });

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


