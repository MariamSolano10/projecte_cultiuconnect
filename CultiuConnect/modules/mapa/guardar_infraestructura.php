<?php
/**
 * modules/mapa/guardar_infraestructura.php - Endpoint AJAX per actualitzar
 * la posicio geografica d'una infraestructura des del mapa GIS.
 *
 * Metode: POST (JSON)
 * Body:   { "id": <int>, "geojson": <GeoJSON geometry object> }
 * Retorn: { "success": true|false, "error"?: "..." }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

header('Content-Type: application/json; charset=UTF-8');

function respond_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_backup_dir(string $backupDir): void
{
    if (is_dir($backupDir)) {
        return;
    }

    if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        throw new RuntimeException('No s\'ha pogut crear el directori de backup.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond_json(405, ['success' => false, 'error' => 'Metode no permes.']);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    respond_json(400, ['success' => false, 'error' => 'Cos de la peticio invalid.']);
}

$id = (int)($body['id'] ?? 0);
$geojson = $body['geojson'] ?? null;

if ($id <= 0 || !is_array($geojson) || empty($geojson['type']) || !isset($geojson['coordinates'])) {
    respond_json(400, ['success' => false, 'error' => 'Parametres incorrectes.']);
}

$tipusPermesos = ['Point', 'LineString', 'Polygon', 'MultiPolygon'];
if (!in_array((string)$geojson['type'], $tipusPermesos, true)) {
    respond_json(400, ['success' => false, 'error' => 'Tipus de geometria no valid.']);
}

try {
    $pdo = connectDB();
    $backupDir = __DIR__ . '/../../data/geometries';
    ensure_backup_dir($backupDir);

    $geojsonString = json_encode($geojson, JSON_UNESCAPED_UNICODE);
    if ($geojsonString === false) {
        throw new RuntimeException('No s\'ha pogut serialitzar el GeoJSON.');
    }

    $check = $pdo->prepare('SELECT id_infra FROM infraestructura WHERE id_infra = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        respond_json(404, ['success' => false, 'error' => 'Infraestructura no trobada.']);
    }

    $stmt = $pdo->prepare('
        UPDATE infraestructura
        SET coordenades_geo = ST_GeomFromGeoJSON(?)
        WHERE id_infra = ?
    ');
    $stmt->execute([$geojsonString, $id]);

    $backupFile = $backupDir . '/infra_' . $id . '.json';
    $backupData = [
        'id_infra' => $id,
        'coordenades_geo' => $geojson,
        'data_guardat' => date('Y-m-d H:i:s'),
        'tipus' => 'infraestructura',
    ];
    $backupJson = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($backupJson === false || file_put_contents($backupFile, $backupJson) === false) {
        throw new RuntimeException('No s\'ha pogut guardar el backup de la geometria.');
    }

    respond_json(200, ['success' => true]);
} catch (Exception $e) {
    error_log('[CultiuConnect] guardar_infraestructura.php: ' . $e->getMessage());
    respond_json(500, ['success' => false, 'error' => 'Error intern del servidor.']);
}
