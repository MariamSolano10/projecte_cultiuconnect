<?php
/**
 * modules/mapa/mapa_gis.php — Mapa GIS complet de l'explotació.
 *
 * EXCEPCIÓ JUSTIFICADA: pàgina full-screen (100vh) que NO inclou header.php/footer.php.
 * Gestiona el seu propi <html>, <head> i <body>.
 *
 * Taules: sector, parcela, parcela_sector, plantacio, varietat,
 *         infraestructura, fila_arbre, aplicacio, monitoratge_plaga
 *
 * CORRECCIONS v2:
 *  - Query files tractades (secció 5b): elimina duplicats per fila causats per
 *    la clau composta (id_fila_aplicada, id_aplicacio, id_treballador).
 *    Ara usa GROUP BY + MAX(percentatge_complet) per obtenir un registre per fila.
 *  - Capa 6 (progrés per tractament): distingeix 4 estats amb colors:
 *      · completada → verd    (#27ae60)
 *      · en_proces  → blau    (#2980b9)
 *      · aturada    → taronja (#e67e22)
 *      · pendent    → vermell (#e74c3c, línia discontínua)
 *  - Afegit popup d'acció ràpida per canviar l'estat d'una fila directament
 *    des del mapa sense sortir a fila_aplicacio.php (crida AJAX).
 *  - Resum del panell "Progrés" mostra els 4 comptadors.
 *  - Paràmetre GET ?id_aplicacio=X per preseleccionar una aplicació en carregar.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// Error de BD → pàgina d'error mínima (mai die() amb missatge intern)
function error_mapa(string $msg): never
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="ca"><head><meta charset="UTF-8">
          <title>Error — CultiuConnect</title></head><body>
          <p style="font-family:sans-serif;padding:2rem;">
          <strong>No s\'ha pogut carregar el mapa.</strong><br>' . e($msg) . '
          <br><a href="javascript:history.back()">Tornar enrere</a></p></body></html>';
    exit;
}

try {
    $pdo = connectDB();
} catch (Exception $e) {
    error_log('[CultiuConnect] mapa_gis.php connectDB: ' . $e->getMessage());
    error_mapa('Error de connexió a la base de dades.');
}

// Funció per executar queries sense aturar el mapa si fallen
function query_safe(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        error_log('[CultiuConnect] mapa_gis.php query: ' . $e->getMessage());
        return [];
    }
}

function json_for_script(mixed $value, mixed $fallback = []): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $json = json_encode($value, $flags);
    if ($json !== false) {
        return $json;
    }

    error_log('[CultiuConnect] mapa_gis.php json_encode: ' . json_last_error_msg());
    return json_encode($fallback, $flags) ?: 'null';
}

function unique_rows_by_int_key(array $rows, string $key): array
{
    $unique = [];
    foreach ($rows as $row) {
        $id = (int)($row[$key] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if (!isset($unique[$id])) {
            $unique[$id] = $row;
        }
    }

    return array_values($unique);
}

// Preselecció d'aplicació des de paràmetre GET (per al vincle des de fila_aplicacio.php)
$id_aplicacio_presel = is_numeric($_GET['id_aplicacio'] ?? '') ? (int)$_GET['id_aplicacio'] : 0;

// ============================================================
// 1. SECTORS amb plantació activa
// ============================================================
$sectors = query_safe($pdo, "
    SELECT
        s.id_sector,
        s.nom                                   AS nom_sector,
        s.descripcio,
        v.nom_varietat,
        pl.data_plantacio,
        pl.marc_fila,
        pl.marc_arbre,
        COALESCE(
            (SELECT SUM(p2.superficie_ha)
             FROM parcela p2
             JOIN parcela_sector ps2 ON ps2.id_parcela = p2.id_parcela
             WHERE ps2.id_sector = s.id_sector),
            0
        )                                        AS superficie_ha,
        /* Edat de la plantació en anys */
        CASE WHEN pl.data_plantacio IS NOT NULL
             THEN TIMESTAMPDIFF(YEAR, pl.data_plantacio, CURDATE())
             ELSE NULL END                       AS edat_anys,
        /* Alertes sanitàries actives (llindar assolit) */
        COALESCE(
            (SELECT COUNT(*)
             FROM monitoratge_plaga mp
             WHERE mp.id_sector = s.id_sector
               AND mp.llindar_intervencio_assolit = 1),
            0
        )                                        AS alertes_actives,
        /* Kg collits per hectàrea (última temporada) */
        COALESCE(
            (SELECT SUM(ct.kg_recollectats)
             FROM collita c2
             JOIN collita_treballador ct ON ct.id_collita = c2.id_collita
             JOIN plantacio pl2 ON pl2.id_plantacio = c2.id_plantacio
             WHERE pl2.id_sector = s.id_sector),
            0
        )                                        AS kg_total_collits,
        ST_AsGeoJSON(s.coordenades_geo)          AS geojson
    FROM sector s
    LEFT JOIN plantacio pl ON pl.id_sector    = s.id_sector
                           AND pl.data_arrencada IS NULL
    LEFT JOIN varietat  v  ON v.id_varietat   = pl.id_varietat
    WHERE s.coordenades_geo IS NOT NULL
    ORDER BY s.nom ASC
");
$sectors = unique_rows_by_int_key($sectors, 'id_sector');

// ============================================================
// 2. PARCEL·LES cadastrals
// ============================================================
$parceles = query_safe($pdo, "
    SELECT
        id_parcela,
        nom,
        superficie_ha,
        pendent,
        ST_AsGeoJSON(coordenades_geo) AS geojson
    FROM parcela
    WHERE coordenades_geo IS NOT NULL
    ORDER BY nom ASC
");
$parceles = unique_rows_by_int_key($parceles, 'id_parcela');

// ============================================================
// 3. INFRAESTRUCTURES (reg, camins, tanques…)
// ============================================================
$infraestructures = query_safe($pdo, "
    SELECT
        id_infra,
        nom,
        tipus,
        ST_AsGeoJSON(coordenades_geo) AS geojson
    FROM infraestructura
    WHERE coordenades_geo IS NOT NULL
    ORDER BY tipus ASC
");
$infraestructures = unique_rows_by_int_key($infraestructures, 'id_infra');

// ============================================================
// 4. FILES D'ARBRES amb GPS
// ============================================================
$files_arbres = query_safe($pdo, "
    SELECT
        fa.id_fila,
        fa.numero,
        s.nom        AS nom_sector,
        v.nom_varietat,
        ST_AsGeoJSON(
            CASE
                WHEN s.coordenades_geo IS NOT NULL
                 AND ST_Intersects(fa.coordenades_geo, s.coordenades_geo)
                THEN ST_Intersection(fa.coordenades_geo, s.coordenades_geo)
                ELSE fa.coordenades_geo
            END
        ) AS geojson
    FROM fila_arbre fa
    LEFT JOIN sector    s  ON s.id_sector   = fa.id_sector
    LEFT JOIN plantacio pl ON pl.id_sector  = fa.id_sector
                           AND pl.data_arrencada IS NULL
    LEFT JOIN varietat  v  ON v.id_varietat = pl.id_varietat
    WHERE fa.coordenades_geo IS NOT NULL
");

// ============================================================
// 5. APLICACIONS (últimes 30) per al selector de progrés
// ============================================================
$aplicacions = query_safe($pdo, "
    SELECT
        a.id_aplicacio,
        a.data_event                 AS data_aplicacio,
        s.nom                        AS nom_sector,
        GROUP_CONCAT(pq.nom_comercial ORDER BY pq.nom_comercial SEPARATOR ', ') AS productes
    FROM aplicacio a
    LEFT JOIN sector s                       ON s.id_sector      = a.id_sector
    LEFT JOIN detall_aplicacio_producte dap  ON dap.id_aplicacio = a.id_aplicacio
    LEFT JOIN inventari_estoc ie             ON ie.id_estoc      = dap.id_estoc
    LEFT JOIN producte_quimic pq             ON pq.id_producte   = ie.id_producte
    GROUP BY a.id_aplicacio, a.data_event, s.nom
    ORDER BY a.data_event DESC
    LIMIT 30
");
$aplicacions = unique_rows_by_int_key($aplicacions, 'id_aplicacio');

// ============================================================
// 5b. FILES TRACTADES per aplicació
// CORRECCIÓ: la taula fila_aplicacio té clau composta
// (id_fila_aplicada, id_aplicacio, id_treballador).
// Una mateixa fila pot tenir múltiples treballadors i causava
// files duplicades en el JS. Ara fem GROUP BY + subquery per
// obtenir un únic registre per fila (el de percentatge més alt).
// També recuperem l'estat per pintar els 4 colors al mapa.
// ============================================================
$files_tractades_per_op = [];
$files_per_op_raw = query_safe($pdo, "
    SELECT
        fa2.id_aplicacio,
        fa2.id_fila_aplicada          AS id_fila,
        fa2.estat,
        fa2.percentatge_complet,
        ST_AsGeoJSON(
            CASE
                WHEN s.coordenades_geo IS NOT NULL
                 AND ST_Intersects(fa.coordenades_geo, s.coordenades_geo)
                THEN ST_Intersection(fa.coordenades_geo, s.coordenades_geo)
                ELSE fa.coordenades_geo
            END
        ) AS geojson
    FROM fila_aplicacio fa2
    INNER JOIN fila_arbre fa ON fa.id_fila = fa2.id_fila_aplicada
    LEFT JOIN sector s ON s.id_sector = fa.id_sector
    INNER JOIN (
        /* Per cada (aplicacio, fila) agafem el registre amb % més alt */
        SELECT   id_aplicacio,
                 id_fila_aplicada,
                 MAX(percentatge_complet) AS max_pct
        FROM     fila_aplicacio
        GROUP BY id_aplicacio, id_fila_aplicada
    ) sub ON  sub.id_aplicacio    = fa2.id_aplicacio
          AND sub.id_fila_aplicada = fa2.id_fila_aplicada
          AND sub.max_pct          = fa2.percentatge_complet
    WHERE fa.coordenades_geo IS NOT NULL
");
foreach ($files_per_op_raw as $f) {
    $idAplicacio = (int)($f['id_aplicacio'] ?? 0);
    $idFila = (int)($f['id_fila'] ?? 0);
    if ($idAplicacio <= 0 || $idFila <= 0) {
        continue;
    }

    // Dedupliquem també a PHP per cobrir empats de percentatge a SQL.
    $existing = $files_tractades_per_op[$idAplicacio][$idFila] ?? null;
    if ($existing === null) {
        $files_tractades_per_op[$idAplicacio][$idFila] = $f;
        continue;
    }

    $pctNou = (float)($f['percentatge_complet'] ?? 0);
    $pctAntic = (float)($existing['percentatge_complet'] ?? 0);
    if ($pctNou >= $pctAntic) {
        $files_tractades_per_op[$idAplicacio][$idFila] = $f;
    }
}

foreach ($files_tractades_per_op as $idAplicacio => $filesOp) {
    $files_tractades_per_op[$idAplicacio] = array_values($filesOp);
}

// ============================================================
// 6. MONITORATGE DE PLAGUES amb coordenades GPS
// ============================================================
$monitoratge = query_safe($pdo, "
    SELECT
        mp.id_monitoratge,
        s.nom                              AS nom_sector,
        mp.tipus_problema                  AS nom_plaga,
        mp.descripcio_breu,
        mp.nivell_poblacio,
        mp.llindar_intervencio_assolit,
        mp.data_observacio                 AS data_monitoratge,
        ST_AsGeoJSON(mp.coordenades_geo)   AS geojson
    FROM monitoratge_plaga mp
    LEFT JOIN sector s ON s.id_sector = mp.id_sector
    WHERE mp.coordenades_geo IS NOT NULL
    ORDER BY mp.data_observacio DESC
");

// ============================================================
// 7. FOTOS GEOLOCALITZADES (Capa Extra Requeriment 1.1a)
// ============================================================
$fotos_geo = query_safe($pdo, "
    SELECT 
        f.id_foto,
        f.url_foto,
        f.data_foto,
        f.descripcio,
        p.nom AS nom_parcela,
        ST_AsGeoJSON(COALESCE(f.coordenades_geo, ST_Centroid(p.coordenades_geo))) AS geojson
    FROM foto_parcela f
    JOIN parcela p ON p.id_parcela = f.id_parcela
    WHERE p.coordenades_geo IS NOT NULL
");

// Base URL per al fetch de guardar_parcela
$base_url = defined('BASE_URL') ? BASE_URL : '/CultiuConnect/';
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa GIS — CultiuConnect</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-cos, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif);
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ---- MAPA ---- */
        #map {
            flex: 1;
            width: 100%;
        }

        /* ---- PANELLS FLOTANTS ---- */
        .panell-flotant {
            position: absolute;
            z-index: 900;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            font-size: 0.87em;
            color: rgba(17,24,39,0.92);
        }

        /* Dock d'icones (obrir/tancar panells) */
        .dock-panells {
            position: absolute;
            z-index: 950;
            /* Posició base (JS la recalcula “just a sota” del toolbar) */
            top: 168px;
            left: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 8px;
            border-radius: 12px;
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(0,0,0,0.10);
            box-shadow: 0 10px 24px rgba(0,0,0,0.18);
            backdrop-filter: blur(6px);
        }

        /* En pantalles estretes, el JS també recol·loca si cal */
        .dock-panells__btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.12);
            background: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #005461;
            box-shadow: 0 8px 18px rgba(0,0,0,0.10);
        }
        .dock-panells__btn:hover { filter: brightness(0.98); }
        .dock-panells__btn.is-active {
            background: rgba(0,84,97,0.12);
            border-color: rgba(0,84,97,0.28);
        }
        .dock-panells__btn.is-mode{
            color: #111827;
        }

        /* Panell plegable */
        .panell-flotant.is-hidden { display: none !important; }
        .panell-flotant .panell-cap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .panell-actions{
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }
        .panell-flotant .panell-cap h3{
            margin: 0;
            border-bottom: 0;
            padding-bottom: 0;
        }
        .btn-pin-panell{
            border: 1px solid rgba(0,0,0,0.12);
            background: #fff;
            border-radius: 10px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #005461;
        }
        .btn-pin-panell.is-pinned{
            background: rgba(0,84,97,0.12);
            border-color: rgba(0,84,97,0.28);
        }
        .btn-tancar-panell{
            border: 1px solid rgba(0,0,0,0.12);
            background: #fff;
            border-radius: 10px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #374151;
        }

        .panell-flotant h3 {
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1E4620;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
        }

        /* Inputs del panell (look consistent amb l'app) */
        #panell-import input[type="file"],
        #panell-import input[type="text"],
        #panell-import select,
        #panell-import textarea{
            font-family: var(--font-cos, inherit);
            font-size: 0.92rem;
            color: rgba(17,24,39,0.92);
        }

        /* Header sticky del panell import (pin + tancar sempre visibles) */
        #panell-import .panell-cap{
            position: sticky;
            top: 0;
            z-index: 2;
            background: rgba(255,255,255,0.98);
            margin: -12px -16px 8px; /* compensa padding del panell */
            padding: 10px 16px 8px;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 8px 14px rgba(17,24,39,0.06);
            backdrop-filter: blur(6px);
        }

        #panell-import input[type="text"],
        #panell-import select,
        #panell-import textarea{
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(17,24,39,0.14);
            background: rgba(255,255,255,0.92);
            box-shadow: 0 10px 20px rgba(17,24,39,0.06);
            outline: none;
        }
        #panell-import textarea{
            line-height: 1.35;
        }
        #panell-import input[type="text"]:focus,
        #panell-import select:focus,
        #panell-import textarea:focus{
            border-color: rgba(40, 204, 158, 0.55);
            box-shadow: 0 0 0 3px rgba(40, 204, 158, 0.16), 0 10px 20px rgba(17,24,39,0.06);
        }

        #panell-import p{
            margin: 0 0 5px;
            color: rgba(55,65,81,0.78);
            line-height: 1.35;
        }

        /* Després del header sticky, dona una mica d’aire al primer text */
        #panell-import .panell-cap + p{
            margin-top: 20px;
        }

        #panell-import hr{
            border: 0;
            height: 1px;
            background: rgba(17,24,39,0.10);
            margin: 12px 0;
        }

        /* Botons import */
        #panell-import button{
            font-family: var(--font-cos, inherit);
            font-weight: 800;
            border-radius: 12px;
        }
        #panell-import #btn-importar{
            background: var(--verd-800, #005461) !important;
        }
        #panell-import #btn-desar-import{
            background: #16a34a !important;
        }
        #panell-import #btn-esborrar-import{
            background: rgba(255,255,255,0.90) !important;
        }

        /* Files d'arbres: mateix estil que l'app */
        #panell-files-arbres .text-ajuda{
            margin: 0 0 10px;
            color: rgba(55,65,81,0.78);
            line-height: 1.35;
            font-size: 0.88rem;
        }
        #panell-files-arbres select{
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(17,24,39,0.14);
            background: rgba(255,255,255,0.92);
            box-shadow: 0 10px 20px rgba(17,24,39,0.06);
            outline: none;
            font-family: var(--font-cos, inherit);
            color: rgba(17,24,39,0.92);
        }
        #panell-files-arbres select:focus{
            border-color: rgba(40, 204, 158, 0.55);
            box-shadow: 0 0 0 3px rgba(40, 204, 158, 0.16), 0 10px 20px rgba(17,24,39,0.06);
        }
        #panell-files-arbres .files-actions{
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        #panell-files-arbres .boto-login{
            flex: 1;
            text-decoration: none;
            text-align: center;
            padding: 10px 12px;
            font-size: 0.92rem;
            border-radius: 12px;
            border: 1px solid rgba(17,24,39,0.14);
            background: rgba(255,255,255,0.92);
            color: rgba(17,24,39,0.92);
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(17,24,39,0.06);
        }
        #panell-files-arbres .boto-login--verd{
            background: #16a34a;
            color: #fff;
            border-color: rgba(22,163,74,0.35);
        }
        /* “Nova” deshabilitat: que no quedi verd apagat, sinó gris suau */
        #panell-files-arbres .boto-login--verd.is-disabled{
            background: rgba(243,244,246,0.95);
            color: rgba(107,114,128,0.95);
            border-color: rgba(17,24,39,0.14);
        }
        #panell-files-arbres .btn-centrar{
            margin-top: 10px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(17,24,39,0.14);
            background: rgba(255,255,255,0.92);
            color: rgba(17,24,39,0.92);
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(17,24,39,0.06);
            font-family: var(--font-cos, inherit);
        }
        #panell-files-arbres .is-disabled{
            opacity: 0.55;
            pointer-events: none;
        }

        /* Selector tractament */
        #selector-tractament {
            top: 72px;
            left: 70px;
            width: 280px;
        }

        #selector-tractament p {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 8px;
        }

        #selector-tractament select {
            width: 100%;
            padding: 7px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.85em;
            font-family: inherit;
        }

        #resum-tractament {
            margin-top: 8px;
            font-size: 0.82em;
        }

        .resum-comptadors {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 10px;
            margin-top: 6px;
        }

        .resum-comptadors span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dot-estat {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Filtre de capes */
        #filtre-capes {
            top: 72px;
            right: 15px;
            min-width: 200px;
        }

        .filtre-fila {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 7px;
            cursor: pointer;
            user-select: none;
        }

        .filtre-fila input {
            cursor: pointer;
            width: 15px;
            height: 15px;
        }

        .filtre-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        /* Llegenda */
        #llegenda {
            bottom: 30px;
            left: 15px;
            max-width: 230px;
            width: fit-content;
            min-width: 210px;
        }

        .llegenda-seccio {
            margin-top: 8px;
        }

        .llegenda-seccio strong {
            display: block;
            font-size: 0.78em;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 4px;
        }

        .llegenda-fila {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
            font-size: 0.82em;
        }

        .llegenda-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        .llegenda-linia {
            width: 24px;
            height: 4px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* Notificació flotant (substitueix alert) */
        #notificacio-mapa {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1100;
            background: #1E4620;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            font-size: 0.9em;
            display: none;
            max-width: 400px;
            text-align: center;
        }

        .notif--error {
            background: #c0392b !important;
        }

        /* ---- PANELL METEOROLOGIC ---- */
        #panell-meteo {
            top: 70px;
            right: 220px;
            width: 230px;
        }

        #panell-meteo.visible { display: block; }

        .meteo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 10px;
            margin-top: 6px;
            font-size: 0.82em;
        }

        .meteo-kpi {
            display: flex;
            flex-direction: column;
            background: #f5f9f5;
            border-radius: 5px;
            padding: 5px 7px;
        }

        .meteo-kpi__val {
            font-size: 1.1em;
            font-weight: 700;
            color: #1E4620;
        }

        .meteo-kpi__label {
            font-size: 0.78em;
            color: #666;
            margin-top: 1px;
        }

        .meteo-loading {
            text-align: center;
            color: #888;
            font-size: 0.82em;
            padding: 8px 0;
        }

        /* Marcador pulsant per alertes */
        @keyframes pulse {
            0%, 100% { transform: scale(1);   opacity: 1;   }
            50%       { transform: scale(1.5); opacity: 0.6; }
        }

        .marker-alerta {
            animation: pulse 1.2s infinite;
        }

        /* Popup d'actualització ràpida d'estat */
        .popup-estat-fila select,
        .popup-estat-fila button {
            width: 100%;
            margin-top: 6px;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-family: inherit;
        }
        .popup-estat-fila button {
            background: #27ae60;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .popup-estat-fila button:hover { background: #1e8449; }
        .popup-estat-fila .msg-ok   { color: #27ae60; font-size:0.82em; margin-top:4px; }
        .popup-estat-fila .msg-err  { color: #c0392b; font-size:0.82em; margin-top:4px; }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body>

    <!-- Dock d'icones per panells -->
    <div class="dock-panells" id="dock-panells" role="navigation" aria-label="Panells del mapa">
        <button class="dock-panells__btn is-active" type="button" data-panell="selector-tractament" title="Progrés tractament">
            <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
        </button>
        <button class="dock-panells__btn is-active" type="button" data-panell="panell-import" title="Importar / Dibuixar">
            <i class="fas fa-upload" aria-hidden="true"></i>
        </button>
        <button class="dock-panells__btn is-active" type="button" data-panell="panell-files-arbres" title="Files d’arbres">
            <i class="fas fa-grip-lines" aria-hidden="true"></i>
        </button>
        <button class="dock-panells__btn is-active" type="button" data-panell="llegenda" title="Llegenda">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
        </button>
        <button class="dock-panells__btn is-active" type="button" data-panell="filtre-capes" title="Capes">
            <i class="fas fa-layer-group" aria-hidden="true"></i>
        </button>
        <button class="dock-panells__btn" type="button" data-panell="panell-meteo" title="Meteo">
            <i class="fas fa-cloud-sun" aria-hidden="true"></i>
        </button>
    </div>

    <header class="top-bar" role="banner" style="position: relative; z-index: 1000;">
        <div class="top-bar__esquerra">
            <h1 class="top-bar__titol" style="margin: 0; line-height: 1;">Mapa GIS de l'Explotació</h1>
        </div>

        <div class="top-bar__dreta" style="display: flex; align-items: center; gap: 20px;">
            <?php if (isset($usuari) && $usuari): ?>
                <span class="top-bar__usuari">
                    <span class="top-bar__avatar" aria-hidden="true">
                        <?= mb_strtoupper(mb_substr($usuari['nom'], 0, 1)) ?>
                    </span>
                    <span style="font-family: var(--font-cos); font-weight: 500;"><?= e($usuari['nom']) ?></span>
                </span>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>index.php" title="Tornar al panell"
                style="color: var(--verd-800); text-decoration: none; display: flex; align-items: center;
                       gap: 8px; background: rgba(0, 84, 97, 0.1); padding: 8px 15px; border-radius: 6px;
                       font-size: 0.9em; font-weight: 600; border: 1px solid rgba(0, 84, 97, 0.2);">
                <span class="text-boto">Sortir</span>
                <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </a>
        </div>
    </header>

    <!-- Filtre de capes -->
    <div class="panell-flotant" id="filtre-capes" role="group" aria-label="Control de capes">
        <div class="panell-cap">
            <h3><i class="fas fa-layer-group" aria-hidden="true"></i> Capes</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="filtre-capes" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="filtre-capes" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-sectors" checked>
            <span class="filtre-color" style="background:#3498db;border:2px solid #2980b9;"></span>
            Sectors productius
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-parceles" checked>
            <span class="filtre-color" style="background:#f39c12;border:2px dashed #e67e22;"></span>
            Parcel·les cadastrals
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-infra" checked>
            <span class="filtre-color" style="background:#95a5a6;"></span>
            Infraestructures
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-files" checked>
            <span class="filtre-color" style="background:#2ecc71;"></span>
            Files d'arbres
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-monitor" checked>
            <span class="filtre-color" style="background:#e74c3c;"></span>
            Monitoratge
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-fotos" title="Fotos geolocalitzades">
            <span class="filtre-color" style="background:#f1c40f; display:flex; align-items:center; justify-content:center; color:#111; font-size:10px;"><i class="fas fa-camera"></i></span>
            Fotos parcel·les
        </label>
        <hr style="margin:8px 0;border-color:#eee;">
        <div style="margin-bottom:6px;">
            <label style="font-size:0.8em; text-transform:uppercase; letter-spacing:0.5px; color:#555; font-weight:600; display:block; margin-bottom:4px;">
                <i class="fas fa-palette" aria-hidden="true"></i> Vista Temàtica
            </label>
            <select id="select-tematica" style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.85em; font-family:inherit;">
                <option value="normal">Normal (per defecte)</option>
                <option value="edat">Edat de plantació</option>
                <option value="sanitari">Estat sanitari</option>
                <option value="rendiment">Rendiment (kg/ha)</option>
            </select>
            <div id="llegenda-tematica" style="margin-top:6px; font-size:0.78em; display:none;"></div>
        </div>
        <hr style="margin:8px 0;border-color:#eee;">
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-meteo">
            <span class="filtre-color" style="background:#0288d1;border-radius:50%;"></span>
            Meteo actual
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-sol">
            <span class="filtre-color" style="background:#8d6e63;"></span>
            Mapa de sols (ICGC)
        </label>
        <label class="filtre-fila">
            <input type="checkbox" id="toggle-protegides">
            <span class="filtre-color" style="background:#43a047;border:2px solid #1b5e20;border-radius:50%;"></span>
            Zones protegides
        </label>
        <hr style="margin:8px 0;border-color:#eee;">
        <label class="filtre-fila" title="Activa edició/dibuix de geometries al mapa">
            <input type="checkbox" id="toggle-edicio">
            <span class="filtre-color" style="background:#111827;"></span>
            Mode edició (dibuix/editar)
        </label>
    </div>

    <!-- Importar / Dibuixar geometries -->
    <div class="panell-flotant" id="panell-import" style="top: 70px; left: 20px; width: 320px;"
         role="region" aria-label="Importar o dibuixar geometries">
        <div class="panell-cap">
            <h3><i class="fas fa-upload" aria-hidden="true"></i> Importar / Dibuixar</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="panell-import" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="panell-import" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <p style="font-size:0.8em;color:#666;margin-bottom:8px;">
            Importa un fitxer <b>GeoJSON</b> o <b>KML</b> (o enganxa el contingut) i desa'l com parcel·la o assigna'l a un sector existent.
        </p>

        <label style="display:block;font-size:0.82em;margin-bottom:6px;color:#444;font-weight:700;">
            Fitxer (GeoJSON / KML)
        </label>
        <input type="file" id="fitxer-geo" accept=".geojson,.json,.kml,application/geo+json,application/json,application/vnd.google-earth.kml+xml"
               style="width:100%; margin-bottom:8px;">

        <label style="display:block;font-size:0.82em;margin-bottom:6px;color:#444;font-weight:700;">
            O enganxa GeoJSON/KML
        </label>
        <textarea id="txt-geo" rows="5" placeholder="{ ... } o <kml>..."
                  style="width:100%; resize:vertical; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; padding: 8px; border-radius: 8px; border:1px solid #ddd;"></textarea>

        <div style="display:flex; gap:8px; margin-top:10px;">
            <button type="button" id="btn-importar"
                    style="flex:1;background:#005461;color:white;padding:8px 10px;border:none;border-radius:8px;cursor:pointer;font-weight:800;">
                Importar al mapa
            </button>
            <button type="button" id="btn-netejartxt"
                    style="background:#f5f5f5;border:1px solid #ddd;border-radius:8px;padding:8px 10px;cursor:pointer;font-weight:800;">
                Netejar
            </button>
        </div>

        <hr style="margin:10px 0;border-color:#eee;">

        <label style="display:block;font-size:0.82em;margin-bottom:6px;color:#444;font-weight:700;">Assignar a…</label>
        <div style="display:grid; grid-template-columns: 1fr; gap:8px;">
            <select id="assigna-tipus" style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd;">
                <option value="parcela_nova">Parcel·la nova (crear)</option>
                <option value="parcela_update">Parcel·la existent (actualitzar geometria)</option>
                <option value="sector_update">Sector existent (actualitzar geometria)</option>
            </select>
            <input id="nom-parcela-nova" type="text" placeholder="Nom nova parcel·la"
                   style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd;">
            <select id="select-parcela-update" style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd; display:none;">
                <option value="">— Selecciona parcel·la —</option>
                <?php foreach ($parceles as $p): ?>
                    <option value="<?= (int)$p['id_parcela'] ?>"><?= e($p['nom'] ?? ('Parcel·la #' . (int)$p['id_parcela'])) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="select-sector-update" style="width:100%; padding:8px; border-radius:8px; border:1px solid #ddd; display:none;">
                <option value="">— Selecciona sector —</option>
                <?php foreach ($sectors as $s): ?>
                    <option value="<?= (int)$s['id_sector'] ?>"><?= e($s['nom_sector'] ?? ('Sector #' . (int)$s['id_sector'])) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="btn-desar-import"
                    style="width:100%;background:#27ae60;color:white;padding:9px 10px;border:none;border-radius:8px;cursor:pointer;font-weight:900;">
                Desar geometria importada
            </button>
            <button type="button" id="btn-esborrar-import"
                    style="width:100%;background:#fff;border:1px solid #ddd;border-radius:8px;padding:9px 10px;cursor:pointer;font-weight:900;">
                Esborrar geometria temporal
            </button>
            <div id="msg-import" style="font-size:0.82em;color:#444;"></div>
        </div>
    </div>

    <!-- Accés directe a Files ("línies") d'arbres -->
    <div class="panell-flotant" id="panell-files-arbres" style="top: 70px; left: 360px; width: 280px;"
        role="region" aria-label="Gestió de files d'arbres">
        <div class="panell-cap">
            <h3><i class="fas fa-grip-lines" aria-hidden="true"></i> Files d'arbres</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="panell-files-arbres" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="panell-files-arbres" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <p class="text-ajuda">
            Tria un sector per veure o donar d'alta les files ("línies") d'arbres.
        </p>
        <select id="select-sector-files" aria-label="Seleccionar sector">
            <option value="">— Selecciona un sector —</option>
            <?php foreach ($sectors as $s): ?>
                <option value="<?= (int)$s['id_sector'] ?>"><?= e($s['nom_sector'] ?? ('Sector #' . (int)$s['id_sector'])) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="files-actions">
            <a id="btn-veure-files" class="boto-login is-disabled" href="#" aria-disabled="true">
                Veure
            </a>
            <a id="btn-nova-fila" class="boto-login boto-login--verd is-disabled" href="#" aria-disabled="true">
                Nova
            </a>
        </div>
        <button id="btn-centrar-sector" type="button"
            class="btn-centrar is-disabled" disabled>
            Centrar al sector
        </button>
    </div>

    <!-- Panell meteorologic flotant -->
    <div class="panell-flotant is-hidden" id="panell-meteo" role="region" aria-label="Dades meteorologiques actuals">
        <div class="panell-cap">
            <h3><i class="fas fa-cloud-sun" aria-hidden="true"></i> Meteo actual</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="panell-meteo" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="panell-meteo" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <div id="meteo-contingut">
            <p class="meteo-loading">Carregant dades...</p>
        </div>
    </div>

    <!-- Panell progrés de tractament -->
    <div class="panell-flotant" id="selector-tractament">
        <div class="panell-cap">
            <h3><i class="fas fa-spray-can-sparkles" aria-hidden="true"></i> Progrés de Tractament</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="selector-tractament" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="selector-tractament" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <p>Selecciona una aplicació per veure quines files s'han cobert.</p>
        <select id="select-operacio" aria-label="Seleccionar aplicació">
            <option value="">— Selecciona una aplicació —</option>
            <?php foreach ($aplicacions as $ap): ?>
                <option value="<?= (int)$ap['id_aplicacio'] ?>"
                    <?= $id_aplicacio_presel === (int)$ap['id_aplicacio'] ? 'selected' : '' ?>>
                    <?= e(
                        format_data($ap['data_aplicacio'], curta: true) . ' · ' .
                        ($ap['productes'] ?? 'Producte desconegut') . ' · ' .
                        ($ap['nom_sector'] ?? '—')
                    ) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="resum-tractament" hidden>
            <div class="resum-comptadors">
                <span>
                    <span class="dot-estat" style="background:#27ae60;"></span>
                    <span id="num-completades">0</span> completades
                </span>
                <span>
                    <span class="dot-estat" style="background:#2980b9;"></span>
                    <span id="num-en-proces">0</span> en procés
                </span>
                <span>
                    <span class="dot-estat" style="background:#e67e22;"></span>
                    <span id="num-aturades">0</span> aturades
                </span>
                <span>
                    <span class="dot-estat" style="background:#e74c3c;"></span>
                    <span id="num-pendents">0</span> pendents
                </span>
            </div>
            <a id="link-gestio-files" href="#" style="display:block;margin-top:8px;font-size:0.8em;color:#1E4620;">
                <i class="fas fa-table-list"></i> Gestionar files d'aquesta aplicació
            </a>
        </div>
    </div>

    <!-- Llegenda -->
    <div class="panell-flotant" id="llegenda" aria-label="Llegenda del mapa">
        <div class="panell-cap">
            <h3><i class="fas fa-circle-info" aria-hidden="true"></i> Llegenda</h3>
            <span class="panell-actions">
                <button class="btn-pin-panell" type="button" data-pin-panell="llegenda" aria-label="Fixar/Desfixar panell">
                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                </button>
                <button class="btn-tancar-panell" type="button" data-tancar-panell="llegenda" aria-label="Tancar panell">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </span>
        </div>

        <div class="llegenda-seccio">
            <strong>Sectors</strong>
            <div class="llegenda-fila">
                <span class="llegenda-linia" style="background:#3498db;"></span> Perímetre del sector
            </div>
        </div>

        <div class="llegenda-seccio">
            <strong>Parcel·les</strong>
            <div class="llegenda-fila">
                <span class="llegenda-linia" style="background:#f39c12;border-top:3px dashed #f39c12;height:0;"></span>
                Contorn cadastral
            </div>
        </div>

        <div class="llegenda-seccio">
            <strong>Infraestructures</strong>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#3498db;"></span> Reg</div>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#7f8c8d;"></span> Camí</div>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#95a5a6;"></span> Tanca</div>
        </div>

        <div class="llegenda-seccio">
            <strong>Estat del tractament</strong>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#27ae60;"></span> Completada</div>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#2980b9;"></span> En procés</div>
            <div class="llegenda-fila"><span class="llegenda-linia" style="background:#e67e22;"></span> Aturada</div>
            <div class="llegenda-fila">
                <span class="llegenda-linia" style="background:#e74c3c;border-top:3px dashed #e74c3c;height:0;"></span>
                Pendent
            </div>
        </div>

        <div class="llegenda-seccio">
            <strong>Monitoratge</strong>
            <div class="llegenda-fila">
                <span class="llegenda-dot" style="background:#e74c3c;"></span> Alerta activa
            </div>
            <div class="llegenda-fila">
                <span class="llegenda-dot" style="background:#f39c12;"></span> Observació
            </div>
        </div>
    </div>

    <!-- Mapa -->
    <div id="map" role="main" aria-label="Mapa GIS de l'explotació"></div>

    <!-- Notificació flotant -->
    <div id="notificacio-mapa" role="status" aria-live="polite"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>
    <script src="https://unpkg.com/@tmcw/togeojson@0.16.0/dist/togeojson.umd.js"></script>
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

    <script>
        (() => {
            'use strict';

            // ---- Notificació flotant (substitueix tots els alert()) ----
            function notificar(text, tipus) {
                const el = document.getElementById('notificacio-mapa');
                el.textContent = text;
                el.className = tipus === 'error' ? 'notif--error' : '';
                el.style.display = 'block';
                clearTimeout(el._timer);
                el._timer = setTimeout(() => { el.style.display = 'none'; }, 4000);
            }

            // ---- Dock: obrir/tancar panells ----
            const LS_KEY_UI = 'CultiuConnect.mapa_gis.ui.v1';
            const PANEL_IDS = ['selector-tractament','panell-import','panell-files-arbres','llegenda','filtre-capes','panell-meteo'];
            const pinned = new Set();

            function loadUI() {
                try {
                    const raw = localStorage.getItem(LS_KEY_UI);
                    if (!raw) return null;
                    const data = JSON.parse(raw);
                    return data && typeof data === 'object' ? data : null;
                } catch (e) {
                    return null;
                }
            }

            let _saveTimer = null;
            function saveUI(partial) {
                // Throttle (evita spam de localStorage)
                if (_saveTimer) clearTimeout(_saveTimer);
                _saveTimer = setTimeout(() => {
                    try {
                        const current = loadUI() || {};
                        const next = { ...current, ...(partial || {}) };
                        localStorage.setItem(LS_KEY_UI, JSON.stringify(next));
                    } catch (e) {}
                }, 150);
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function isPinned(id) { return pinned.has(id); }
            function setPinned(id, on) {
                if (!id) return;
                if (on) pinned.add(id); else pinned.delete(id);
                const btn = document.querySelector('.btn-pin-panell[data-pin-panell="' + CSS.escape(id) + '"]');
                if (btn) btn.classList.toggle('is-pinned', on);
                saveUI({ pinned: Array.from(pinned) });
                setTimeout(layoutDockedPanels, 0);
            }

            function togglePanell(id, forceOpen) {
                const panell = document.getElementById(id);
                if (!panell) return;
                const isHidden = panell.classList.contains('is-hidden') || getComputedStyle(panell).display === 'none';
                const open = typeof forceOpen === 'boolean' ? forceOpen : isHidden;
                if (open) {
                    panell.classList.remove('is-hidden');
                }
                else panell.classList.add('is-hidden');

                document.querySelectorAll('.dock-panells__btn[data-panell="' + CSS.escape(id) + '"]').forEach(btn => {
                    btn.classList.toggle('is-active', open);
                });
                if (id === 'panell-meteo') {
                    const chk = document.getElementById('toggle-meteo');
                    if (chk) chk.checked = open;
                }

                // Els panells oberts queden al costat del dock (evita dispersió)
                setTimeout(layoutDockedPanels, 0);

                // Recordar obert/tancat
                const openPanels = PANEL_IDS.filter(pid => {
                    const p = document.getElementById(pid);
                    return p && !p.classList.contains('is-hidden') && getComputedStyle(p).display !== 'none';
                });
                saveUI({ open: openPanels });
            }

            function layoutDockedPanels() {
                const dock = document.getElementById('dock-panells');
                const mapEl = document.getElementById('map');
                if (!dock || !mapEl) return;

                const rectMap  = mapEl.getBoundingClientRect();
                const rectDock = dock.getBoundingClientRect();

                // Panells oberts segons el dock (ordre vertical)
                const openIds = Array.from(document.querySelectorAll('.dock-panells__btn.is-active[data-panell]'))
                    .map(b => b.getAttribute('data-panell'))
                    .filter(Boolean);

                const xBase = Math.round(rectDock.right - rectMap.left + 12);
                let y = Math.round(rectDock.top - rectMap.top);

                const maxW = Math.max(260, Math.min(390, Math.round(rectMap.width - xBase - 14)));

                openIds.forEach(id => {
                    const p = document.getElementById(id);
                    if (!p) return;
                    if (p.classList.contains('is-hidden') || getComputedStyle(p).display === 'none') return;
                    if (isPinned(id)) return; // pinned: no el reubiquem

                    // Evitem l'antic mode .visible
                    p.classList.remove('visible');

                    p.style.left  = xBase + 'px';
                    p.style.right = 'auto';
                    p.style.top   = y + 'px';
                    p.style.bottom = 'auto';
                    p.style.height = 'auto';
                    p.style.maxWidth = maxW + 'px';

                    if (id === 'llegenda') {
                        p.style.maxHeight = 'none';
                        p.style.overflow = 'visible';
                    } else {
                        const maxH = Math.max(220, Math.round(rectMap.height - y - 18));
                        p.style.maxHeight = maxH + 'px';
                        p.style.overflow = 'auto';
                    }

                    const h = p.getBoundingClientRect().height;
                    y += Math.round(h + 10);
                });
            }

            document.querySelectorAll('.dock-panells__btn[data-panell]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-panell');
                    if (!id) return;
                    // meteo és especial: el codi existent feia servir .visible; ara usem is-hidden
                    if (id === 'panell-meteo') {
                        const chk = document.getElementById('toggle-meteo');
                        if (chk) {
                            chk.checked = !chk.checked;
                            chk.dispatchEvent(new Event('change'));
                            return;
                        }
                    }
                    togglePanell(id);
                });
            });

            document.querySelectorAll('[data-pin-panell]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-pin-panell');
                    if (!id) return;
                    setPinned(id, !isPinned(id));
                });
            });
            document.querySelectorAll('[data-tancar-panell]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-tancar-panell');
                    if (!id) return;
                    togglePanell(id, false);
                });
            });

            // Meteo: unifiquem el checkbox de capes amb el panell
            const chkMeteo = document.getElementById('toggle-meteo');
            chkMeteo?.addEventListener('change', function () {
                const p = document.getElementById('panell-meteo');
                if (!p) return;
                togglePanell('panell-meteo', !!this.checked);
                if (this.checked && !meteoCarregada) {
                    const centre = map.getCenter();
                    carregarMeteo(centre.lat.toFixed(4), centre.lng.toFixed(4));
                    meteoCarregada = true;
                }
                saveUI({ meteo: !!this.checked });
            });

            function parseGeoJSONSafe(txt, ctx) {
                if (!txt) return null;
                try {
                    return JSON.parse(txt);
                } catch (err) {
                    console.warn('[CultiuConnect] GeoJSON no vàlid a ' + ctx + ':', err);
                    return null;
                }
            }

            // ---- Dades PHP → JS (anti-XSS amb JSON_HEX_TAG|JSON_HEX_AMP) ----
            function lineStringsFromGeo(geo) {
                if (!geo) return [];
                if (geo.type === 'LineString' && Array.isArray(geo.coordinates)) return [geo.coordinates];
                if (geo.type === 'MultiLineString' && Array.isArray(geo.coordinates)) {
                    return geo.coordinates.filter(coords => Array.isArray(coords) && coords.length >= 2);
                }
                return [];
            }

            const sectors        = <?= json_for_script($sectors) ?>;
            const parceles       = <?= json_for_script($parceles) ?>;
            const infra          = <?= json_for_script($infraestructures) ?>;
            const filesArbres    = <?= json_for_script($files_arbres) ?>;
            const monitoratgeData= <?= json_for_script($monitoratge) ?>;
            const fotosGeo       = <?= json_for_script($fotos_geo) ?>;
            const filesTractPerOp= <?= json_for_script($files_tractades_per_op) ?>;
            const baseUrl        = <?= json_for_script($base_url, '') ?>;
            const idPresel       = <?= (int)$id_aplicacio_presel ?>;

            // Estat inicial de panells (recordatori)
            const ui0 = loadUI();

            if (Array.isArray(ui0?.pinned)) {
                ui0.pinned.forEach(id => setPinned(id, true));
            }

            // Aplicar obert/tancat
            const open0 = Array.isArray(ui0?.open) ? ui0.open : ['selector-tractament','panell-import','filtre-capes'];
            PANEL_IDS.forEach(id => togglePanell(id, open0.includes(id)));

            // Meteo toggle
            // Resta de toggles de capes (si existeixen)
            if (ui0?.capes && typeof ui0.capes === 'object') {
                Object.entries(ui0.capes).forEach(([id, val]) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (typeof val === 'boolean') el.checked = val;
                });
            }

            if (typeof ui0?.meteo === 'boolean') {
                const chk = document.getElementById('toggle-meteo');
                if (chk) chk.checked = ui0.meteo;
            }

            setTimeout(layoutDockedPanels, 0);

            // ---- MAPA BASE ----
            const map = L.map('map').setView([41.5145, 0.857], 16);

            // Col·loca el dock “just a sota” del menú Leaflet/Geoman (sense solapaments)
            function reposicionarDock() {
                const dock = document.getElementById('dock-panells');
                const mapEl = document.getElementById('map');
                if (!dock || !mapEl) return;

                // Preferim referenciar el container real dels controls de l’esquerra
                const leftCtrl = mapEl.querySelector('.leaflet-top.leaflet-left');
                const rectMap = mapEl.getBoundingClientRect();

                // Fallback si no trobem controls encara
                if (!leftCtrl) {
                    dock.style.left = '14px';
                    dock.style.top  = '168px';
                    return;
                }

                // Bottom real: últim control visible (zoom + geoman + etc.)
                let maxBottom = leftCtrl.getBoundingClientRect().bottom;
                leftCtrl.querySelectorAll('.leaflet-control').forEach(ctrl => {
                    const cs = getComputedStyle(ctrl);
                    if (cs.display === 'none' || cs.visibility === 'hidden') return;
                    const r = ctrl.getBoundingClientRect();
                    if (r.height > 0) maxBottom = Math.max(maxBottom, r.bottom);
                });

                const rectLeft = leftCtrl.getBoundingClientRect();
                const topPx = Math.max(72, Math.round(maxBottom - rectMap.top + 80)); // + marge extra (més avall)
                const leftPx = Math.round(rectLeft.left - rectMap.left);

                dock.style.left = `${Math.max(14, leftPx)}px`;
                dock.style.top  = `${topPx}px`;

                // En pantalles estretes, si el dock es menja el mapa, el passem a la dreta
                if (window.innerWidth < 980) {
                    dock.style.left = 'auto';
                    dock.style.right = '14px';
                } else {
                    dock.style.right = 'auto';
                }
            }

            // Recalcular quan Leaflet ja ha pintat controls
            setTimeout(reposicionarDock, 0);
            window.addEventListener('resize', () => setTimeout(() => { reposicionarDock(); layoutDockedPanels(); }, 0));

            const satellite = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                { attribution: '© Esri', maxZoom: 20 }
            ).addTo(map);

            const streets = L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                { attribution: '© OpenStreetMap' }
            );

            L.control.layers(
                { 'Satèl·lit': satellite, 'Mapa normal': streets },
                {},
                { position: 'topright' }
            ).addTo(map);

            // ---- GRUPS DE CAPES ----
            // Creem els grups buits i sense forçar l'opció addTo(map)
            const grupSectors  = L.layerGroup();
            const grupParceles = L.layerGroup();
            const grupInfra    = L.layerGroup();
            const grupFiles    = L.layerGroup();
            const grupMonitor  = L.layerGroup();
            const grupFotos    = L.layerGroup();

            const toggleCapes = {
                'toggle-sectors':  grupSectors,
                'toggle-parceles': grupParceles,
                'toggle-infra':    grupInfra,
                'toggle-files':    grupFiles,
                'toggle-monitor':  grupMonitor,
                'toggle-fotos':    grupFotos,
            };
            
            Object.entries(toggleCapes).forEach(([id, grup]) => {
                const el = document.getElementById(id);
                if (!el) return;
                
                // Mantenir la sincronització pels canvis de l'usuari
                el.addEventListener('change', function () {
                    this.checked ? map.addLayer(grup) : map.removeLayer(grup);
                    saveUI({ capes: { ...(loadUI()?.capes || {}), [id]: !!this.checked } });
                });
                
                // Sincronització inicial real: Només es mostra la capa si el checkbox està activat
                if (el.checked) {
                    map.addLayer(grup);
                }
            });

            const colorsSectors = ['#3498db','#9b59b6','#1abc9c','#e67e22','#34495e','#2980b9','#8e44ad'];
            let allPoints = [];
            const capaSectorById = new Map(); // id_sector -> L.GeoJSON
            const capaParcelaById = new Map(); // id_parcela -> L.GeoJSON

            function fetchJSON(url, options = {}) {
                return fetch(url, options).then(async (response) => {
                    const text = await response.text();
                    let data = {};

                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (e) {
                        throw new Error('Resposta invàlida del servidor.');
                    }

                    if (!response.ok) {
                        throw new Error(data?.error || `Error HTTP ${response.status}`);
                    }

                    return data;
                });
            }

            function postJSON(url, payload) {
                return fetchJSON(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            }

            function areaHa(geojsonFeature) {
                try {
                    const aM2 = turf.area(geojsonFeature);
                    if (!isFinite(aM2) || aM2 <= 0) return null;
                    return aM2 / 10000;
                } catch (e) {
                    return null;
                }
            }

            function guardarGeomParcela(idParcela, geometry) {
                return postJSON(baseUrl + 'modules/mapa/guardar_parcela_geo.php', {
                    id: idParcela,
                    geojson: geometry,
                });
            }

            function guardarGeomSector(idSector, geometry) {
                return postJSON(baseUrl + 'modules/mapa/guardar_sector_geo.php', {
                    id: idSector,
                    geojson: geometry,
                });
            }

            // ============================================================
            // CAPA 1: SECTORS
            // ============================================================
            sectors.forEach((sector, idx) => {
                if (!sector.geojson) return;
                const geo = parseGeoJSONSafe(sector.geojson, 'sector ' + (sector.id_sector || ''));
                if (!geo) return;
                const color   = colorsSectors[idx % colorsSectors.length];

                const poly = L.geoJSON(geo, {
                    style: { color, fillColor: color, weight: 3, fillOpacity: 0.25 }
                }).addTo(grupSectors);
                if (sector.id_sector != null) {
                    capaSectorById.set(parseInt(sector.id_sector), poly);
                    poly.on('click', () => {
                        if (!selSectorFiles) return;
                        selSectorFiles.value = String(parseInt(sector.id_sector));
                        selSectorFiles.dispatchEvent(new Event('change'));
                    });
                }

                // Edició (només si Mode edició està actiu)
                poly.eachLayer(l => {
                    if (!l?.pm) return;
                    l.pm.setOptions({
                        allowSelfIntersection: true,
                        snappable: false,
                        snapVertex: false,
                        snapSegment: false,
                        snapMiddle: false,
                        pinning: false,
                        allowPinning: false
                    });
                    l.on('pm:edit', function (e) {
                        const idSector = parseInt(sector.id_sector) || 0;
                        if (!idSector) return;
                        const geom = e.layer.toGeoJSON().geometry;
                        guardarGeomSector(idSector, geom)
                            .then(d => notificar(d.success ? '✔ Sector actualitzat.' : '✘ Error en guardar sector.', d.success ? 'ok' : 'error'))
                            .catch(() => notificar('✘ Error de connexió.', 'error'));
                    });
                    l.pm.disable();
                });

                const ha = parseFloat(sector.superficie_ha) || 0;
                poly.bindPopup(
                    `<b style="color:${color};font-size:14px;">${sector.nom_sector}</b>
                     <hr style="margin:5px 0">
                     ${sector.nom_varietat ? 'Varietat: <b>' + sector.nom_varietat + '</b><br>' : ''}
                     ${sector.data_plantacio ? 'Plantació: <b>' + sector.data_plantacio + '</b><br>' : ''}
                     ${ha > 0 ? 'Superfície: <b>' + ha.toFixed(2) + ' ha</b><br>' : ''}
                     ${sector.marc_fila && sector.marc_arbre
                         ? 'Marc: <b>' + sector.marc_fila + ' × ' + sector.marc_arbre + ' m</b>' : ''}`
                );

                poly.eachLayer(l => {
                    if (typeof l.getLatLngs !== 'function') return;
                    const ll = l.getLatLngs();
                    const flatten = (arr) => Array.isArray(arr) ? arr.flatMap(flatten) : [arr];
                    flatten(ll).forEach(p => {
                        if (p && typeof p.lat === 'number' && typeof p.lng === 'number') {
                            allPoints.push([p.lat, p.lng]);
                        }
                    });
                });
            });

            // ============================================================
            // MOTOR DE VISTES TEMÀTIQUES (Coloració dinàmica de sectors)
            // ============================================================
            const selectTematica = document.getElementById('select-tematica');
            const llegendaTematica = document.getElementById('llegenda-tematica');

            // Escales de color per cada mode temàtic
            function colorEdat(anys) {
                if (anys === null || anys === undefined) return { fill: '#bdc3c7', border: '#95a5a6', label: 'Sense dades' };
                if (anys <= 3)  return { fill: '#27ae60', border: '#1e8449', label: 'Jove (0-3 anys)' };
                if (anys <= 8)  return { fill: '#2ecc71', border: '#27ae60', label: 'Productiva (4-8 anys)' };
                if (anys <= 15) return { fill: '#f39c12', border: '#e67e22', label: 'Madura (9-15 anys)' };
                if (anys <= 25) return { fill: '#e67e22', border: '#d35400', label: 'Envellint (16-25 anys)' };
                return               { fill: '#e74c3c', border: '#c0392b', label: 'Caduca (>25 anys)' };
            }

            function colorSanitari(alertes) {
                alertes = parseInt(alertes) || 0;
                if (alertes === 0) return { fill: '#27ae60', border: '#1e8449', label: 'Sa (0 alertes)' };
                if (alertes === 1) return { fill: '#f39c12', border: '#e67e22', label: 'Vigilància (1 alerta)' };
                if (alertes === 2) return { fill: '#e67e22', border: '#d35400', label: 'Risc (2 alertes)' };
                return                    { fill: '#e74c3c', border: '#c0392b', label: 'Crític (≥3 alertes)' };
            }

            function colorRendiment(kg, ha) {
                ha = parseFloat(ha) || 0;
                kg = parseFloat(kg) || 0;
                if (ha <= 0) return { fill: '#bdc3c7', border: '#95a5a6', label: 'Sense dades' };
                const kgHa = kg / ha;
                if (kgHa === 0)    return { fill: '#95a5a6', border: '#7f8c8d', label: 'Sense collita' };
                if (kgHa < 5000)   return { fill: '#e74c3c', border: '#c0392b', label: 'Baix (<5t/ha)' };
                if (kgHa < 10000)  return { fill: '#f39c12', border: '#e67e22', label: 'Mitjà (5-10t/ha)' };
                if (kgHa < 20000)  return { fill: '#2ecc71', border: '#27ae60', label: 'Bo (10-20t/ha)' };
                return                    { fill: '#1abc9c', border: '#16a085', label: 'Excel·lent (>20t/ha)' };
            }

            function aplicarVistaTematica(mode) {
                const llegendes = {};

                sectors.forEach((sector, idx) => {
                    const poly = capaSectorById.get(parseInt(sector.id_sector));
                    if (!poly) return;

                    if (mode === 'normal') {
                        const color = colorsSectors[idx % colorsSectors.length];
                        poly.setStyle({ color, fillColor: color, weight: 3, fillOpacity: 0.25 });
                        return;
                    }

                    let info;
                    if (mode === 'edat') {
                        info = colorEdat(sector.edat_anys != null ? parseInt(sector.edat_anys) : null);
                    } else if (mode === 'sanitari') {
                        info = colorSanitari(sector.alertes_actives);
                    } else if (mode === 'rendiment') {
                        info = colorRendiment(sector.kg_total_collits, sector.superficie_ha);
                    }

                    if (!info) return;
                    poly.setStyle({
                        color: info.border,
                        fillColor: info.fill,
                        weight: 3,
                        fillOpacity: 0.55
                    });

                    llegendes[info.label] = info.fill;
                });

                // Actualitzar llegenda
                if (mode === 'normal') {
                    llegendaTematica.style.display = 'none';
                    llegendaTematica.innerHTML = '';
                } else {
                    const titols = { edat: 'Edat Plantació', sanitari: 'Estat Sanitari', rendiment: 'Rendiment kg/ha' };
                    let html = `<strong style="display:block;margin-bottom:4px;">${titols[mode] || ''}</strong>`;
                    Object.entries(llegendes).forEach(([label, color]) => {
                        html += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                                    <span style="width:12px;height:12px;border-radius:3px;background:${color};flex-shrink:0;"></span>
                                    ${label}
                                 </div>`;
                    });
                    llegendaTematica.innerHTML = html;
                    llegendaTematica.style.display = 'block';
                }
            }

            selectTematica?.addEventListener('change', function() {
                aplicarVistaTematica(this.value);
            });

            // ============================================================
            // CAPA 2: PARCEL·LES CADASTRALS
            // ============================================================
            parceles.forEach(parcela => {
                if (!parcela.geojson) return;
                const geo = parseGeoJSONSafe(parcela.geojson, 'parcela ' + (parcela.id_parcela || ''));
                if (!geo) return;

                const layer = L.geoJSON(geo, {
                    style: {
                        color: '#f39c12', weight: 2, dashArray: '8 6',
                        fillOpacity: 0.08, fillColor: '#f39c12',
                    }
                }).addTo(grupParceles);
                if (parcela.id_parcela != null) {
                    capaParcelaById.set(parseInt(parcela.id_parcela), layer);
                }

                layer.bindPopup(
                    `<b style="color:#e67e22;">${parcela.nom}</b>
                     <hr style="margin:5px 0">
                     ${parcela.superficie_ha ? 'Superfície: <b>' + parseFloat(parcela.superficie_ha).toFixed(2) + ' ha</b><br>' : ''}
                     ${parcela.pendent ? 'Pendent: <b>' + parcela.pendent + '</b>' : ''}`
                );

                // Edició (només si Mode edició està actiu)
                layer.eachLayer(l => {
                    if (!l?.pm) return;
                    l.pm.setOptions({
                        allowSelfIntersection: true,
                        snappable: false,
                        snapVertex: false,
                        snapSegment: false,
                        snapMiddle: false,
                        pinning: false,
                        allowPinning: false
                    });
                    l.on('pm:edit', function (e) {
                        const idParcela = parseInt(parcela.id_parcela) || 0;
                        if (!idParcela) return;
                        const geom = e.layer.toGeoJSON().geometry;
                        guardarGeomParcela(idParcela, geom)
                            .then(d => notificar(d.success ? '✔ Parcel·la actualitzada.' : '✘ Error en guardar parcel·la.', d.success ? 'ok' : 'error'))
                            .catch(() => notificar('✘ Error de connexió.', 'error'));
                    });
                    l.pm.disable();
                });

                layer.eachLayer(l => {
                    if (typeof l.getLatLngs !== 'function') return;
                    const ll = l.getLatLngs();
                    const flatten = (arr) => Array.isArray(arr) ? arr.flatMap(flatten) : [arr];
                    flatten(ll).forEach(p => {
                        if (p && typeof p.lat === 'number' && typeof p.lng === 'number') {
                            allPoints.push([p.lat, p.lng]);
                        }
                    });
                });
            });

            // ============================================================
            // CAPA 3: INFRAESTRUCTURES (amb edició arrossegable)
            // ============================================================
            const colorInfra = {
                'reg': '#3498db', 'camin': '#7f8c8d', 'tanca': '#95a5a6',
                'edificacio': '#e67e22', 'altres': '#bdc3c7'
            };

            function guardarPosicioInfra(idInfra, geojson) {
                fetchJSON(baseUrl + 'modules/mapa/guardar_infraestructura.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: idInfra, geojson }),
                }).then(d => notificar(
                    d.success ? '✔ Infraestructura guardada.' : '✘ Error en guardar.',
                    d.success ? 'ok' : 'error'
                ))
                .catch(() => notificar('✘ Error de connexió.', 'error'));
            }

            infra.forEach(inf => {
                if (!inf.geojson) return;
                const geo   = parseGeoJSONSafe(inf.geojson, 'infra ' + (inf.id_infra || ''));
                if (!geo) return;
                const color = colorInfra[inf.tipus] || '#bdc3c7';
                const popup = `<b>${inf.nom}</b><br><em style="color:#888;">${inf.tipus}</em>`;
                let capa;

                if (geo.type === 'LineString') {
                    const ll = geo.coordinates.map(c => [c[1], c[0]]);
                    capa = L.polyline(ll, {
                        color,
                        weight: inf.tipus === 'camin' ? 4 : 2,
                        opacity: inf.tipus === 'camin' ? 0.55 : 0.70,
                        dashArray: inf.tipus === 'tanca' ? '8 5' : null,
                        idInfra: inf.id_infra
                    });
                    capa.addTo(grupInfra).bindPopup(popup);
                    ll.forEach(p => allPoints.push(p));
                    capa.bringToBack?.();

                } else if (geo.type === 'Polygon') {
                    const ll = geo.coordinates[0].map(c => [c[1], c[0]]);
                    capa = L.polygon(ll, { color, weight: 2, fillOpacity: 0.15, idInfra: inf.id_infra });
                    capa.addTo(grupInfra).bindPopup(popup);
                    ll.forEach(p => allPoints.push(p));

                } else if (geo.type === 'Point') {
                    const p = [geo.coordinates[1], geo.coordinates[0]];
                    capa = L.circleMarker(p, { radius: 8, color, fillColor: color, fillOpacity: 0.8, idInfra: inf.id_infra });
                    capa.addTo(grupInfra).bindPopup(popup);
                    allPoints.push(p);
                }

                if (capa) {
                    capa.pm.enable({ allowSelfIntersection: false });
                    capa.on('pm:edit', function (e) {
                        const nouGeo = e.layer.toGeoJSON().geometry;
                        guardarPosicioInfra(inf.id_infra, nouGeo);
                    });
                }
            });

            // ============================================================
            // CAPA 4: FILES D'ARBRES (vista general sense selecció tractament)
            // ============================================================
            filesArbres.forEach(fila => {
                if (!fila.geojson) return;
                const geo = parseGeoJSONSafe(fila.geojson, 'fila ' + (fila.id_fila || ''));
                if (!geo) return;
                const lineStrings = lineStringsFromGeo(geo);
                if (lineStrings.length === 0) return;

                lineStrings.forEach(coords => {
                    const ll = coords.map(c => [c[1], c[0]]);
                    L.polyline(ll, { color: '#27ae60', weight: 2, opacity: 0.85 })
                        .addTo(grupFiles)
                        .bindPopup(
                            `<b>Fila #${fila.id_fila}</b>` +
                            (fila.numero ? ` (Núm. ${fila.numero})` : '') +
                            `<br>
                             ${fila.nom_sector   ? 'Sector: <b>'  + fila.nom_sector   + '</b><br>' : ''}
                             ${fila.nom_varietat ? 'Varietat: <b>' + fila.nom_varietat + '</b>'      : ''}`
                        );
                    ll.forEach(p => allPoints.push(p));
                });
            });

            // ============================================================
            // CAPA 5: MONITORATGE DE PLAGUES
            // ============================================================
            monitoratgeData.forEach(obs => {
                if (!obs.geojson) return;
                const geo = parseGeoJSONSafe(obs.geojson, 'monitoratge ' + (obs.id_monitoratge || ''));
                if (!geo) return;
                if (geo.type !== 'Point') return;

                const ll    = [geo.coordinates[1], geo.coordinates[0]];
                const alerta = obs.llindar_intervencio_assolit == 1;
                const color  = alerta ? '#e74c3c' : '#f39c12';

                const icon = L.divIcon({
                    className: '',
                    html: `<div class="${alerta ? 'marker-alerta' : ''}"
                           style="background:${color};width:16px;height:16px;
                                  border:3px solid white;border-radius:50%;
                                  box-shadow:0 0 8px ${color};"></div>`,
                    iconSize: [16, 16], iconAnchor: [8, 8],
                });

                L.marker(ll, { icon }).addTo(grupMonitor).bindPopup(
                    `<b style="color:${color};">${obs.nom_plaga || '—'}</b>
                     <hr style="margin:5px 0">
                     Sector: <b>${obs.nom_sector || '—'}</b><br>
                     ${obs.descripcio_breu ? obs.descripcio_breu + '<br>' : ''}
                     ${obs.nivell_poblacio ? 'Nivell: <b>' + obs.nivell_poblacio + '</b><br>' : ''}
                     ${obs.data_monitoratge ? 'Data: <b>' + obs.data_monitoratge + '</b><br>' : ''}
                     ${alerta ? '<b style="color:#e74c3c;">⚠ Llindar d\'intervenció assolit</b>' : ''}`
                );

                allPoints.push(ll);
            });

            // ============================================================
            // CAPA 7: FOTOS GEOLOCALITZADES
            // ============================================================
            fotosGeo.forEach(foto => {
                if (!foto.geojson) return;
                const geo = parseGeoJSONSafe(foto.geojson, 'foto ' + (foto.id_foto || ''));
                if (!geo) return;
                if (geo.type !== 'Point') return;

                const ll = [geo.coordinates[1], geo.coordinates[0]];

                const icon = L.divIcon({
                    className: '',
                    html: `<div style="background:#f1c40f;width:24px;height:24px;
                                  border:3px solid white;border-radius:50%;
                                  box-shadow:0 0 8px #d4ac0d; display:flex; 
                                  align-items:center; justify-content:center; color:#111; font-size:12px;">
                             <i class="fas fa-camera"></i>
                           </div>`,
                    iconSize: [24, 24], iconAnchor: [12, 12],
                });

                L.marker(ll, { icon }).addTo(grupFotos).bindPopup(
                    `<b style="color:#b7950b;">Foto Geolocalitzada</b>
                     <hr style="margin:5px 0">
                     Sector/Parcel·la: <b>${foto.nom_parcela || '—'}</b><br>
                     ${foto.data_foto ? 'Data: <b>' + foto.data_foto + '</b><br>' : ''}
                     <div style="margin-top:8px; text-align:center;">
                         <img src="${baseUrl}${foto.url_foto}" style="max-width:100%; border-radius:6px; margin-bottom:5px;" alt="Foto parcella">
                     </div>
                     ${foto.descripcio ? '<em style="font-size:0.95em;">' + foto.descripcio + '</em>' : ''}`,
                     { minWidth: 200 }
                );

                allPoints.push(ll);
            });

            // ============================================================
            // CAPA 6: FILES TRACTADES vs PENDENTS per aplicació
            // CORRECCIÓ: 4 estats amb colors diferenciats
            //   completada → verd    (#27ae60), línia sòlida, pes 3
            //   en_proces  → blau    (#2980b9), línia sòlida, pes 3
            //   aturada    → taronja (#e67e22), línia discontínua curta
            //   pendent    → vermell (#e74c3c), línia discontínua llarga, pes 2
            // ============================================================
            const grupFilesTract = L.layerGroup();
            const selectOp   = document.getElementById('select-operacio');
            const resumDiv   = document.getElementById('resum-tractament');
            const linkGestio = document.getElementById('link-gestio-files');

            // Colors i estils per estat
            const estilEstat = {
                completada: { color: '#27ae60', weight: 3, dashArray: null,  opacity: 1.0 },
                en_proces:  { color: '#2980b9', weight: 3, dashArray: '10 4', opacity: 1.0 },
                aturada:    { color: '#e67e22', weight: 3, dashArray: '5 5',  opacity: 0.9 },
                pendent:    { color: '#e74c3c', weight: 2, dashArray: '6 4',  opacity: 0.8 },
            };

            // Etiquetes per al popup
            const etiquetaEstat = {
                completada: '✔ Completada',
                en_proces:  '⟳ En procés',
                aturada:    '⏸ Aturada',
                pendent:    '✘ Pendent',
            };

            // URL de l'endpoint AJAX de fila_aplicacio.php
            const AJAX_URL = baseUrl + 'modules/fila_aplicacio/fila_aplicacio.php?ajax=1';

            /**
             * Construeix el popup d'una fila amb selector d'estat i botó de
             * canvi ràpid via AJAX. Útil perquè el supervisor pugui actualitzar
             * l'estat d'una fila directament des del mapa sense sortir a la taula.
             */
            function popupFila(fila, estatActual, idAplic) {
                const idFila    = fila.id_fila;
                const nomSector = escapeHtml(fila.nom_sector);
                const nomVarietat = escapeHtml(fila.nom_varietat);
                const nom       = nomSector ? 'Sector: <b>' + nomSector + '</b><br>' : '';
                const varietat  = nomVarietat ? 'Varietat: <b>' + nomVarietat + '</b><br>' : '';
                const numFila   = fila.numero ? ' (Núm. ' + escapeHtml(fila.numero) + ')' : '';
                const colorEstat = estilEstat[estatActual]?.color || '#888';
                const etiq       = escapeHtml(etiquetaEstat[estatActual] || estatActual);

                return `
                    <b>Fila #${idFila}${numFila}</b><br>
                    ${nom}${varietat}
                    <span style="color:${colorEstat};font-weight:600;">${etiq}</span>
                    <div class="popup-estat-fila" style="margin-top:8px;">
                        <label style="font-size:0.82em;color:#555;">Canviar estat:</label>
                        <select id="sel-estat-${idFila}">
                            <option value="completada" ${estatActual==='completada'?'selected':''}>Completada</option>
                            <option value="en_proces"  ${estatActual==='en_proces' ?'selected':''}>En procés</option>
                            <option value="aturada"    ${estatActual==='aturada'   ?'selected':''}>Aturada</option>
                            <option value="pendent"    ${estatActual==='pendent'   ?'selected':''}>Pendent</option>
                        </select>
                        <button onclick="canviarEstatFila(${idFila}, ${idAplic})">
                            Actualitzar
                        </button>
                        <div id="msg-estat-${idFila}"></div>
                    </div>`;
            }

            // Funció global per al botó del popup
            window.canviarEstatFila = function(idFila, idAplic) {
                const sel   = document.getElementById('sel-estat-' + idFila);
                const msgEl = document.getElementById('msg-estat-' + idFila);
                if (!sel) return;
                const nouEstat = sel.value;

                fetchJSON(AJAX_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_fila:        idFila,
                        id_aplicacio:   idAplic,
                        id_treballador: 0, // 0 → el servidor usarà el treballador de l'aplicació
                        estat:          nouEstat,
                    }),
                }).then(data => {
                    if (data.ok) {
                        if (msgEl) { msgEl.className = 'msg-ok'; msgEl.textContent = '✔ Estat actualitzat.'; }
                        // Refresca la capa de tractament
                        if (selectOp) {
                            setTimeout(() => selectOp.dispatchEvent(new Event('change')), 800);
                        }
                    } else {
                        if (msgEl) { msgEl.className = 'msg-err'; msgEl.textContent = '✘ ' + (data.error || 'Error.'); }
                    }
                })
                .catch(() => {
                    if (msgEl) { msgEl.className = 'msg-err'; msgEl.textContent = '✘ Error de connexió.'; }
                });
            };

            function actualitzarCapaTractament(idOp) {
                grupFilesTract.clearLayers();

                if (!idOp) {
                    if (resumDiv) resumDiv.hidden = true;
                    map.removeLayer(grupFilesTract);
                    return;
                }

                const tractades    = filesTractPerOp[idOp] || [];
                // Creem un mapa id_fila → { estat, geojson } per accés ràpid
                const mapaTractades = {};
                tractades.forEach(f => {
                    mapaTractades[f.id_fila] = f;
                });

                let cCompletades = 0, cEnProcés = 0, cAturades = 0, cPendents = 0;

                filesArbres.forEach(fila => {
                    if (!fila.geojson) return;
                    const geo = parseGeoJSONSafe(fila.geojson, 'fila ' + (fila.id_fila || ''));
                    if (!geo) return;
                    const lineStrings = lineStringsFromGeo(geo);
                    if (lineStrings.length === 0) return;

                    const reg        = mapaTractades[fila.id_fila];
                    const estat      = reg ? (reg.estat || 'completada') : 'pendent';
                    const estil      = estilEstat[estat] || estilEstat.pendent;

                    // Comptadors
                    if      (estat === 'completada') cCompletades++;
                    else if (estat === 'en_proces')  cEnProcés++;
                    else if (estat === 'aturada')    cAturades++;
                    else                             cPendents++;

                    lineStrings.forEach(coords => {
                        const ll = coords.map(c => [c[1], c[0]]);
                        const polyline = L.polyline(ll, {
                            color:     estil.color,
                            weight:    estil.weight,
                            opacity:   estil.opacity,
                            dashArray: estil.dashArray,
                        }).addTo(grupFilesTract);

                        polyline.bindPopup(popupFila(fila, estat, idOp));
                    });
                });

                // Actualitza comptadors
                const elNumCompletades = document.getElementById('num-completades');
                const elNumEnProces = document.getElementById('num-en-proces');
                const elNumAturades = document.getElementById('num-aturades');
                const elNumPendents = document.getElementById('num-pendents');
                if (elNumCompletades) elNumCompletades.textContent = String(cCompletades);
                if (elNumEnProces) elNumEnProces.textContent = String(cEnProcés);
                if (elNumAturades) elNumAturades.textContent = String(cAturades);
                if (elNumPendents) elNumPendents.textContent = String(cPendents);

                // Actualitza l'enllaç a la gestió de files
                if (linkGestio) {
                    linkGestio.href = baseUrl + 'modules/fila_aplicacio/fila_aplicacio.php?id_aplicacio=' + idOp;
                }

                if (resumDiv) resumDiv.hidden = false;
                map.removeLayer(grupFiles);
                const toggleFiles = document.getElementById('toggle-files');
                if (toggleFiles) toggleFiles.checked = false;
                map.addLayer(grupFilesTract);
            }

            selectOp?.addEventListener('change', function () {
                actualitzarCapaTractament(parseInt(this.value) || 0);
                saveUI({ id_aplicacio: parseInt(this.value) || 0 });
            });

            document.getElementById('toggle-files')?.addEventListener('change', function () {
                if (this.checked) {
                    grupFilesTract.clearLayers();
                    selectOp.value = '';
                    if (resumDiv) resumDiv.hidden = true;
                }
                this.checked ? map.addLayer(grupFiles) : map.removeLayer(grupFiles);
            });

            // Si hi ha aplicació preseleccionada, activar la capa automàticament
            if (idPresel && selectOp) {
                actualitzarCapaTractament(idPresel);
            }

            // Si hi ha selecció guardada a UI, respecta-la
            const uiSel = loadUI();
            if (selectOp && uiSel && typeof uiSel.id_aplicacio === 'number' && uiSel.id_aplicacio > 0) {
                selectOp.value = String(uiSel.id_aplicacio);
                selectOp.dispatchEvent(new Event('change'));
            }

            // ============================================================
            // UX: Accés ràpid a veure/crear Files d'arbres per sector
            // ============================================================
            const selSectorFiles = document.getElementById('select-sector-files');
            const btnVeureFiles  = document.getElementById('btn-veure-files');
            const btnNovaFila    = document.getElementById('btn-nova-fila');
            const btnCentrar     = document.getElementById('btn-centrar-sector');

            function activarBoto(a, enabled) {
                if (!a) return;
                a.classList.toggle('is-disabled', !enabled);
                a.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            }

            function activarBtnCentrar(enabled) {
                if (!btnCentrar) return;
                btnCentrar.disabled = !enabled;
                btnCentrar.classList.toggle('is-disabled', !enabled);
            }

            selSectorFiles?.addEventListener('change', function () {
                const idSector = parseInt(this.value) || 0;
                if (!idSector) {
                    btnVeureFiles.href = '#';
                    btnNovaFila.href   = '#';
                    activarBoto(btnVeureFiles, false);
                    activarBoto(btnNovaFila, false);
                    activarBtnCentrar(false);
                    return;
                }

                btnVeureFiles.href = baseUrl + 'modules/sectors/files_arbre.php?id_sector=' + idSector;
                btnNovaFila.href   = baseUrl + 'modules/sectors/nou_fila_arbre.php?id_sector=' + idSector;
                activarBoto(btnVeureFiles, true);
                activarBoto(btnNovaFila, true);
                activarBtnCentrar(true);
            });

            btnCentrar?.addEventListener('click', function () {
                const idSector = parseInt(selSectorFiles?.value) || 0;
                if (!idSector) return;
                const layer = capaSectorById.get(idSector);
                if (!layer) return;
                try {
                    map.fitBounds(layer.getBounds().pad(0.12));
                } catch (e) {}
            });

            // ============================================================
            // DIBUIX DE NOVES PARCEL·LES (Leaflet-Geoman)
            // ============================================================
            map.pm.addControls({
                position:         'topleft',
                drawMarker:       false,
                drawCircleMarker: false,
                drawPolyline:     false,
                drawRectangle:    false,
                drawCircle:       false,
                drawPolygon:      true,
                cutPolygon:       false,
                editMode:         false,
                dragMode:         false,
                removalMode:      false,
                rotateMode:       false,
            });

            map.pm.setGlobalOptions({
                pathOptions:  { color: '#e74c3c', weight: 4, fillOpacity: 0.2 },
                snappable:    true,
                snapDistance: 20,
            });

            // Control global del "Mode edició"
            const chkEdicio = document.getElementById('toggle-edicio');
            function setModeEdicio(on) {
                const toggleFilesEl = document.getElementById('toggle-files');
                // Leaflet-Geoman: algunes versions no tenen Toolbar.setVisible()
                function toolbarSetVisible(visible) {
                    try {
                        const el = document.querySelector('.leaflet-pm-toolbar');
                        if (!el) return;
                        el.style.display = visible ? '' : 'none';
                    } catch (e) {}
                }

                // Toolbar
                if (on) {
                    try {
                        map.pm.addControls({}); // assegura toolbar creada primer
                        map.pm.Toolbar?.setOptions({ position: 'topleft' });
                    } catch(e) {}
                    toolbarSetVisible(true);
                } else {
                    try { map.pm.disableDraw(); } catch(e) {}
                    toolbarSetVisible(false);
                }

                // Mentre editem, retirem les files perquè no interceptin clics.
                if (on) {
                    map.removeLayer(grupFiles);
                    map.removeLayer(grupFilesTract);
                    if (toggleFilesEl) toggleFilesEl.disabled = true;
                    if (selectOp) selectOp.disabled = true;
                } else {
                    if (toggleFilesEl) toggleFilesEl.disabled = false;
                    if (selectOp) selectOp.disabled = false;

                    const idOpActiu = parseInt(selectOp?.value) || 0;
                    if (idOpActiu) {
                        actualitzarCapaTractament(idOpActiu);
                    } else if (toggleFilesEl?.checked) {
                        map.addLayer(grupFiles);
                    }
                }

                // Habilitar/deshabilitar edició per capes existents
                capaSectorById.forEach(g => g.eachLayer(l => on
                    ? l.pm.enable({
                        allowSelfIntersection: true,
                        snappable: false,
                        snapVertex: false,
                        snapSegment: false,
                        snapMiddle: false,
                        pinning: false,
                        allowPinning: false
                    })
                    : l.pm.disable()));
                capaParcelaById.forEach(g => g.eachLayer(l => on
                    ? l.pm.enable({
                        allowSelfIntersection: true,
                        snappable: false,
                        snapVertex: false,
                        snapSegment: false,
                        snapMiddle: false,
                        pinning: false,
                        allowPinning: false
                    })
                    : l.pm.disable()));

                if (on) {
                    capaParcelaById.forEach(g => g.eachLayer(l => l.bringToBack?.()));
                    capaSectorById.forEach(g => g.eachLayer(l => l.bringToFront?.()));
                }

                // Reposiciona el dock perquè quedi sota del toolbar actual
                setTimeout(reposicionarDock, 0);
            }
            if (chkEdicio) {
                chkEdicio.addEventListener('change', () => {
                    setModeEdicio(chkEdicio.checked);
                    saveUI({ edicio: !!chkEdicio.checked });
                });
                const uiE = loadUI();
                const edicio0 = !!(uiE && uiE.edicio === true);
                chkEdicio.checked = edicio0;
                setModeEdicio(edicio0);
            }

            // Geometria temporal importada/dibuixada
            const grupTemp = L.layerGroup().addTo(map);
            let lastTempLayer = null;

            function setTempLayer(layer) {
                grupTemp.clearLayers();
                lastTempLayer = layer || null;
                if (layer) {
                    layer.addTo(grupTemp);
                    try { map.fitBounds(layer.getBounds().pad(0.12)); } catch(e) {}
                }
            }

            function importarTextGeo(txt) {
                const t = (txt || '').trim();
                if (!t) return null;
                // KML
                if (t.startsWith('<') && t.toLowerCase().includes('<kml')) {
                    const xml = (new DOMParser()).parseFromString(t, 'text/xml');
                    const gj = toGeoJSON.kml(xml);
                    return gj;
                }
                // GeoJSON
                return JSON.parse(t);
            }

            function layerFromGeoJSON(gj) {
                const layer = L.geoJSON(gj, {
                    style: { color: '#111827', weight: 3, fillOpacity: 0.12, fillColor: '#111827' }
                });
                layer.eachLayer(l => { if (l?.pm) { l.pm.enable({ allowSelfIntersection: false }); }});
                return layer;
            }

            // UI import
            const elFile = document.getElementById('fitxer-geo');
            const elTxt  = document.getElementById('txt-geo');
            const btnImp = document.getElementById('btn-importar');
            const btnClr = document.getElementById('btn-netejartxt');
            const selTip = document.getElementById('assigna-tipus');
            const inpNom = document.getElementById('nom-parcela-nova');
            const selPar = document.getElementById('select-parcela-update');
            const selSec = document.getElementById('select-sector-update');
            const btnDes = document.getElementById('btn-desar-import');
            const btnDel = document.getElementById('btn-esborrar-import');
            const msgImp = document.getElementById('msg-import');

            function setMsg(text, isErr) {
                if (!msgImp) return;
                msgImp.textContent = text || '';
                msgImp.style.color = isErr ? '#c0392b' : '#1f2937';
            }

            function syncAssignUI() {
                const v = selTip?.value || 'parcela_nova';
                if (inpNom) inpNom.style.display = v === 'parcela_nova' ? 'block' : 'none';
                if (selPar) selPar.style.display = v === 'parcela_update' ? 'block' : 'none';
                if (selSec) selSec.style.display = v === 'sector_update' ? 'block' : 'none';
            }
            selTip?.addEventListener('change', syncAssignUI);
            syncAssignUI();

            btnClr?.addEventListener('click', () => { if (elTxt) elTxt.value = ''; if (elFile) elFile.value = ''; setMsg('', false); });
            btnDel?.addEventListener('click', () => { setTempLayer(null); setMsg('Geometria temporal esborrada.', false); });

            btnImp?.addEventListener('click', async () => {
                setMsg('', false);
                try {
                    let gj = null;
                    if (elTxt && elTxt.value.trim()) {
                        gj = importarTextGeo(elTxt.value);
                    } else if (elFile && elFile.files && elFile.files[0]) {
                        const f = elFile.files[0];
                        const txt = await f.text();
                        if (elTxt) elTxt.value = txt;
                        gj = importarTextGeo(txt);
                    }
                    if (!gj) { setMsg('No hi ha cap GeoJSON/KML per importar.', true); return; }
                    const layer = layerFromGeoJSON(gj);
                    setTempLayer(layer);
                    setMsg('Importat. Pots editar la geometria (si Mode edició actiu) i després desar.', false);
                } catch (e) {
                    setMsg('No s’ha pogut importar: contingut invàlid.', true);
                }
            });

            function getTempGeometry() {
                if (!lastTempLayer) return null;
                // agafem la primera feature layer
                let geom = null;
                lastTempLayer.eachLayer(l => {
                    if (geom) return;
                    try { geom = l.toGeoJSON().geometry; } catch(e) {}
                });
                return geom;
            }

            btnDes?.addEventListener('click', () => {
                setMsg('', false);
                const geom = getTempGeometry();
                if (!geom) { setMsg('No hi ha geometria temporal per desar.', true); return; }

                const tipus = selTip?.value || 'parcela_nova';
                if (tipus === 'parcela_nova') {
                    const nom = (inpNom?.value || '').trim();
                    if (!nom) { setMsg('Introdueix el nom de la nova parcel·la.', true); return; }
                    const feat = { type: 'Feature', properties: {}, geometry: geom };
                    const ha = areaHa(feat);
                    if (!ha) { setMsg('No s’ha pogut calcular l’àrea. Revisa la geometria.', true); return; }

                    postJSON(baseUrl + 'modules/parceles/guardar_parcela.php', {
                        nom: nom,
                        superficie_ha: Math.max(0.01, Math.round(ha * 100) / 100),
                        pendent: '',
                        orientacio: '',
                        geojson: geom,
                    }).then(d => {
                        if (d.success) {
                            notificar('✔ Parcel·la creada.', 'ok');
                            setMsg('Parcel·la creada. Ja la veuràs també a la capa de parcel·les.', false);
                        } else {
                            setMsg('Error: ' + (d.error || 'Desconegut'), true);
                        }
                    }).catch(() => setMsg('Error de connexió.', true));
                } else if (tipus === 'parcela_update') {
                    const id = parseInt(selPar?.value) || 0;
                    if (!id) { setMsg('Selecciona una parcel·la existent.', true); return; }
                    guardarGeomParcela(id, geom).then(d => {
                        if (d.success) { notificar('✔ Parcel·la actualitzada.', 'ok'); setMsg('Parcel·la actualitzada.', false); }
                        else setMsg('Error: ' + (d.error || 'Desconegut'), true);
                    }).catch(() => setMsg('Error de connexió.', true));
                } else if (tipus === 'sector_update') {
                    const id = parseInt(selSec?.value) || 0;
                    if (!id) { setMsg('Selecciona un sector existent.', true); return; }
                    guardarGeomSector(id, geom).then(d => {
                        if (d.success) { notificar('✔ Sector actualitzat.', 'ok'); setMsg('Sector actualitzat.', false); }
                        else setMsg('Error: ' + (d.error || 'Desconegut'), true);
                    }).catch(() => setMsg('Error de connexió.', true));
                }
            });

            map.on('pm:create', function (e) {
                const layer   = e.layer;
                const geojson = layer.toGeoJSON();

                const popupHtml = `
                    <div style="min-width:200px;">
                        <b>Geometria dibuixada</b><br><br>
                        <label style="display:block;margin-bottom:4px;">
                            Nom nova parcel·la (si crees):
                            <input type="text" id="nom-nova-parcela"
                                   style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;"
                                   placeholder="Ex: Finca 4B">
                        </label>
                        <label style="display:block;margin:6px 0 4px;font-size:0.82em;color:#555;">
                            Desar com:
                            <select id="tipus-desar" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:4px;">
                                <option value="parcela_nova">Parcel·la nova (crear)</option>
                                <option value="parcela_update">Parcel·la existent (actualitzar)</option>
                                <option value="sector_update">Sector existent (actualitzar)</option>
                            </select>
                        </label>
                        <select id="sel-parcela-exist" style="display:none;width:100%;padding:6px;border:1px solid #ccc;border-radius:4px;">
                            <option value="">— Selecciona parcel·la —</option>
                            ${parceles.map(p => `<option value="${p.id_parcela}">${(p.nom||('Parcel·la #'+p.id_parcela))}</option>`).join('')}
                        </select>
                        <select id="sel-sector-exist" style="display:none;width:100%;padding:6px;border:1px solid #ccc;border-radius:4px;">
                            <option value="">— Selecciona sector —</option>
                            ${sectors.map(s => `<option value="${s.id_sector}">${(s.nom_sector||('Sector #'+s.id_sector))}</option>`).join('')}
                        </select>
                        <div id="error-nova-parcela"
                             style="color:#c0392b;font-size:0.82em;margin:4px 0;display:none;"></div>
                        <div style="display:flex;gap:8px;margin-top:10px;">
                            <button id="btn-guardar-parcela"
                                    style="flex:1;background:#27ae60;color:white;padding:7px;
                                           border:none;border-radius:4px;cursor:pointer;">
                                Guardar
                            </button>
                            <button id="btn-cancelar-parcela"
                                    style="flex:1;background:#f5f5f5;border:1px solid #ccc;
                                           border-radius:4px;padding:7px;cursor:pointer;">
                                Cancel·lar
                            </button>
                        </div>
                    </div>`;

                layer.bindPopup(popupHtml).openPopup();

                setTimeout(() => {
                    const btnGuardar  = document.getElementById('btn-guardar-parcela');
                    const btnCancelar = document.getElementById('btn-cancelar-parcela');
                    const inputNom    = document.getElementById('nom-nova-parcela');
                    const errorDiv    = document.getElementById('error-nova-parcela');
                    const selTipus    = document.getElementById('tipus-desar');
                    const selParcela  = document.getElementById('sel-parcela-exist');
                    const selSector   = document.getElementById('sel-sector-exist');

                    function syncSel() {
                        const v = selTipus?.value || 'parcela_nova';
                        if (selParcela) selParcela.style.display = v === 'parcela_update' ? 'block' : 'none';
                        if (selSector)  selSector.style.display  = v === 'sector_update' ? 'block' : 'none';
                    }
                    selTipus?.addEventListener('change', syncSel);
                    syncSel();

                    btnCancelar?.addEventListener('click', () => { map.removeLayer(layer); });

                    btnGuardar?.addEventListener('click', () => {
                        const tipus = selTipus?.value || 'parcela_nova';
                        const geom  = geojson.geometry;

                        if (tipus === 'parcela_nova') {
                        const nom = inputNom?.value.trim();
                        if (!nom) {
                            errorDiv.textContent    = 'Has d\'introduir un nom.';
                            errorDiv.style.display  = 'block';
                            inputNom.focus();
                            return;
                        }
                        errorDiv.style.display  = 'none';
                        btnGuardar.textContent  = 'Guardant…';
                        btnGuardar.disabled     = true;

                        const feat = { type:'Feature', properties:{}, geometry: geom };
                        const ha = areaHa(feat);
                        if (!ha) {
                            errorDiv.textContent = 'No s\'ha pogut calcular l\'àrea (geometria invàlida?).';
                            errorDiv.style.display = 'block';
                            btnGuardar.textContent = 'Guardar';
                            btnGuardar.disabled = false;
                            return;
                        }

                        postJSON(baseUrl + 'modules/parceles/guardar_parcela.php', {
                            nom: nom,
                            superficie_ha: Math.max(0.01, Math.round(ha * 100) / 100),
                            pendent: '',
                            orientacio: '',
                            geojson: geom,
                        }).then(data => {
                            if (data.success) {
                                notificar('✔ Parcel·la «' + nom + '» guardada.', 'ok');
                                layer.closePopup();
                                setTempLayer(null);
                            } else {
                                errorDiv.textContent   = 'Error: ' + (data.error || 'Desconegut');
                                errorDiv.style.display = 'block';
                                btnGuardar.textContent = 'Guardar';
                                btnGuardar.disabled    = false;
                            }
                        }).catch(() => {
                            errorDiv.textContent   = 'Error de connexió amb el servidor.';
                            errorDiv.style.display = 'block';
                            btnGuardar.textContent = 'Guardar';
                            btnGuardar.disabled    = false;
                        });
                        return;
                        }

                        if (tipus === 'parcela_update') {
                            const idP = parseInt(selParcela?.value) || 0;
                            if (!idP) {
                                errorDiv.textContent = 'Selecciona una parcel·la.';
                                errorDiv.style.display = 'block';
                                return;
                            }
                            btnGuardar.textContent  = 'Guardant…';
                            btnGuardar.disabled     = true;
                            guardarGeomParcela(idP, geom).then(d => {
                                if (d.success) {
                                    notificar('✔ Parcel·la actualitzada.', 'ok');
                                    layer.closePopup();
                                } else {
                                    errorDiv.textContent = 'Error: ' + (d.error || 'Desconegut');
                                    errorDiv.style.display = 'block';
                                    btnGuardar.textContent = 'Guardar';
                                    btnGuardar.disabled = false;
                                }
                            }).catch(() => {
                                errorDiv.textContent = 'Error de connexió.';
                                errorDiv.style.display = 'block';
                                btnGuardar.textContent = 'Guardar';
                                btnGuardar.disabled = false;
                            });
                            return;
                        }

                        if (tipus === 'sector_update') {
                            const idS = parseInt(selSector?.value) || 0;
                            if (!idS) {
                                errorDiv.textContent = 'Selecciona un sector.';
                                errorDiv.style.display = 'block';
                                return;
                            }
                            btnGuardar.textContent  = 'Guardant…';
                            btnGuardar.disabled     = true;
                            guardarGeomSector(idS, geom).then(d => {
                                if (d.success) {
                                    notificar('✔ Sector actualitzat.', 'ok');
                                    layer.closePopup();
                                } else {
                                    errorDiv.textContent = 'Error: ' + (d.error || 'Desconegut');
                                    errorDiv.style.display = 'block';
                                    btnGuardar.textContent = 'Guardar';
                                    btnGuardar.disabled = false;
                                }
                            }).catch(() => {
                                errorDiv.textContent = 'Error de connexió.';
                                errorDiv.style.display = 'block';
                                btnGuardar.textContent = 'Guardar';
                                btnGuardar.disabled = false;
                            });
                            return;
                        }
                    });
                }, 100);
            });

            // ============================================================
            // CAPA 7: MAPA DE SOLS — WMS ICGC (versió actualitzada)
            // ============================================================
            const capaSol = L.tileLayer.wms(
                'https://geoserveis.icgc.cat/servei/catalunya/edafologia/wms',
                {
                    layers: 'sols-25000',
                    format: 'image/png',
                    transparent: true,
                    opacity: 0.60,
                    version: '1.1.1',
                    attribution: '&copy; <a href="https://www.icgc.cat" target="_blank">ICGC</a> — Mapa de sols',
                }
            );
            let avisErrorSol = false;
            capaSol.on('tileerror', function () {
                if (avisErrorSol) return;
                avisErrorSol = true;
                notificar('No s\'ha pogut carregar el mapa de sols ara mateix.', 'error');
            });
            document.getElementById('toggle-sol')?.addEventListener('change', function () {
                if (this.checked) {
                    avisErrorSol = false;
                    map.addLayer(capaSol);
                    notificar('Mapa de sols activat. Si no es veu res, apropa una mica el zoom.', 'ok');
                } else {
                    map.removeLayer(capaSol);
                }
            });

            // ============================================================
            // CAPA 8: ZONES PROTEGIDES — WMS INSPIRE / Xarxa Natura 2000
            // ============================================================
            const capaProtegides = L.tileLayer.wms(
                'https://wms.mapama.gob.es/sig/Biodiversidad/RedNatura/wms.aspx',
                {
                    layers: 'PS.ProtectedSite',
                    format: 'image/png',
                    transparent: true,
                    opacity: 0.50,
                    version: '1.1.1',
                    attribution: '&copy; <a href="https://www.eea.europa.eu" target="_blank">EEA — Xarxa Natura 2000</a>',
                }
            );
            let avisErrorProtegides = false;
            capaProtegides.on('tileerror', function () {
                if (avisErrorProtegides) return;
                avisErrorProtegides = true;
                notificar('No s\'han pogut carregar les zones protegides ara mateix.', 'error');
            });
            document.getElementById('toggle-protegides')?.addEventListener('change', function () {
                if (this.checked) {
                    avisErrorProtegides = false;
                    map.addLayer(capaProtegides);
                    notificar('Capa de zones protegides (Xarxa Natura 2000) activada.', 'ok');
                } else {
                    map.removeLayer(capaProtegides);
                }
            });

            // ============================================================
            // CAPA 9: METEOROLOGIA — Open-Meteo (API gratuïta, sense clau)
            // ============================================================
            let meteoCarregada = false;

            const codiWMO = {
                0:'Cel clar', 1:'Majoritariament clar', 2:'Parcialment nuvolat', 3:'Nuvolat',
                45:'Boira', 48:'Boira amb glaç', 51:'Plugim lleuger', 53:'Plugim moderat',
                55:'Plugim dens', 61:'Pluja lleu', 63:'Pluja moderada', 65:'Pluja forta',
                71:'Neu lleu', 73:'Neu moderada', 75:'Neu forta', 80:'Ruixats lleus',
                81:'Ruixats moderats', 82:'Ruixats violents', 95:'Tempesta', 99:'Tempesta amb pedra',
            };

            function carregarMeteo(lat, lng) {
                const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}` +
                    `&current=temperature_2m,relative_humidity_2m,wind_speed_10m,wind_direction_10m,` +
                    `precipitation,weather_code,soil_temperature_0cm,soil_moisture_0_to_1cm` +
                    `&wind_speed_unit=kmh&timezone=Europe%2FMadrid`;

                fetchJSON(url)
                    .then(data => {
                        const c = data.current;
                        if (!c) throw new Error('Sense dades');
                        const meteoEl = document.getElementById('meteo-contingut');
                        if (!meteoEl) return;

                        const estat   = codiWMO[c.weather_code] ?? 'Desconegut';
                        const dirVent = ['N','NE','E','SE','S','SO','O','NO'][
                            Math.round(c.wind_direction_10m / 45) % 8
                        ];

                        meteoEl.innerHTML = `
                            <div style="font-size:0.78em;color:#888;margin-bottom:4px;">
                                ${estat} &bull; Actualitzat ara
                            </div>
                            <div class="meteo-grid">
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.temperature_2m} &deg;C</span>
                                    <span class="meteo-kpi__label">Temperatura</span>
                                </div>
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.relative_humidity_2m}%</span>
                                    <span class="meteo-kpi__label">Humitat relativa</span>
                                </div>
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.wind_speed_10m} km/h</span>
                                    <span class="meteo-kpi__label">Vent (${dirVent})</span>
                                </div>
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.precipitation} mm</span>
                                    <span class="meteo-kpi__label">Precipitació</span>
                                </div>
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.soil_temperature_0cm ?? '—'} &deg;C</span>
                                    <span class="meteo-kpi__label">Temp. sòl superf.</span>
                                </div>
                                <div class="meteo-kpi">
                                    <span class="meteo-kpi__val">${c.soil_moisture_0_to_1cm != null
                                        ? (c.soil_moisture_0_to_1cm * 100).toFixed(1) + '%'
                                        : '—'}</span>
                                    <span class="meteo-kpi__label">Humitat sòl</span>
                                </div>
                            </div>
                            <div style="font-size:0.72em;color:#aaa;margin-top:6px;text-align:right;">
                                Font: <a href="https://open-meteo.com" target="_blank"
                                style="color:#0288d1;">Open-Meteo</a>
                            </div>`;
                    })
                    .catch(() => {
                        const meteoEl = document.getElementById('meteo-contingut');
                        if (meteoEl) {
                            meteoEl.innerHTML =
                                '<p class="meteo-loading">No s\'han pogut carregar les dades meteorològiques.</p>';
                        }
                    });
            }

            if (document.getElementById('toggle-meteo')?.checked) {
                const centre = map.getCenter();
                carregarMeteo(centre.lat.toFixed(4), centre.lng.toFixed(4));
                meteoCarregada = true;
                togglePanell('panell-meteo', true);
            }

            // ============================================================
            // AJUSTAR VISTA AL CONTINGUT
            // ============================================================
            if (allPoints.length > 0) {
                map.fitBounds(L.latLngBounds(allPoints).pad(0.12));
            }

            setTimeout(() => map.invalidateSize(), 300);

            // ============================================================
            // RESTAURACIÓ AUTOMÀTICA DE GEOMETRIES (si la BD està buida)
            // ============================================================
            (function restauracioAuto() {
                // Comprovar si hi ha geometries al mapa
                const teSectors = capaSectorById.size > 0;
                const teParceles = capaParcelaById.size > 0;
                
                // Si ja hi ha geometries, no cal restaurar
                if (teSectors || teParceles) return;

                // Intentar restaurar des dels fitxers JSON de backup
                fetchJSON(baseUrl + 'api/restaurar_geometries_auto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                }).then(data => {
                    if (data.restaurats > 0) {
                        notificar(`✔ Restaurades ${data.restaurats} geometries des del backup. Refresca la pàgina per veure-les.`, 'ok');
                        // Ofereixen refrescar automàticament després de 3 segons
                        setTimeout(() => {
                            if (confirm('Geometries restaurades. Vols refrescar la pàgina per veure-les?')) {
                                location.reload();
                            }
                        }, 3000);
                    }
                })
                .catch(() => {
                    // Silenciar errors - pot ser que no hi hagi fitxers de backup
                    console.log('No s\'han trobat geometries de backup per restaurar');
                });
            })();

        })(); // fi IIFE
    </script>
</body>

</html>
