<?php
/**
 * modules/parceles/guardar_parcela.php — Endpoint JSON per crear una nova parcel·la.
 *
 * Rep una petició POST amb JSON:
 * {
 *   "nom":          "Parcel·la Nord",
 *   "superficie_ha": 3.5,
 *   "pendent":      "Suau",
 *   "orientacio":   "Sud",
 *   "geojson":      { ...GeoJSON Polygon... }
 * }
 *
 * Retorna:
 *   { "success": true,  "id": 12 }
 *   { "success": false, "error": "Missatge d'error" }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// Només acceptem POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Mètode no permès.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON invàlid.']);
        exit;
    }

    // -----------------------------------------------------------
    // Validació dels camps obligatoris
    // -----------------------------------------------------------
    $nom          = sanitize($input['nom'] ?? '');
    $superficie   = sanitize_decimal($input['superficie_ha'] ?? null);
    $pendent      = sanitize($input['pendent']    ?? '');
    $orientacio   = sanitize($input['orientacio'] ?? '');
    $geometry     = $input['geojson']             ?? null;

    $errors = [];

    if (empty($nom)) {
        $errors[] = 'El nom de la parcel·la és obligatori.';
    }

    if ($superficie === null || $superficie <= 0) {
        $errors[] = 'La superfície ha de ser un valor positiu (ha).';
    }

    if (empty($geometry) || !is_array($geometry)) {
        $errors[] = 'La geometria de la parcel·la és obligatòria (dibuixa el polígon al mapa).';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
        exit;
    }

    // -----------------------------------------------------------
    // Inserció a la taula `parcela`
    // -----------------------------------------------------------
    $pdo = connectDB();

    $geojson_str = json_encode($geometry);

    $sql = "
        INSERT INTO parcela (nom, coordenades_geo, superficie_ha, pendent, orientacio)
        VALUES (
            :nom,
            ST_GeomFromGeoJSON(:geojson),
            :superficie_ha,
            :pendent,
            :orientacio
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nom'          => $nom,
        ':geojson'      => $geojson_str,
        ':superficie_ha' => $superficie,
        ':pendent'      => $pendent   ?: null,
        ':orientacio'   => $orientacio ?: null,
    ]);

    $id = (int)$pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => $id]);

} catch (Exception $e) {
    error_log('[CultiuConnect] guardar_parcela.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error intern en guardar la parcel·la.']);
}
