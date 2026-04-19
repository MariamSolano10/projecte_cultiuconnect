<?php
/**
 * api/restaurar_geometries_auto.php — Endpoint AJAX per restaurar geometries
 * automàticament des de fitxers JSON quan la BD està buida.
 * 
 * Aquest endpoint es crida des de mapa_gis.php al carregar si no hi ha geometries.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

$backupDir = __DIR__ . '/../data/geometries';

if (!is_dir($backupDir)) {
    echo json_encode(['restaurats' => 0, 'missatge' => 'No hi ha directori de backup']);
    exit;
}

$files = glob($backupDir . '/*.json');
if (empty($files)) {
    echo json_encode(['restaurats' => 0, 'missatge' => 'No hi ha fitxers de backup']);
    exit;
}

try {
    $pdo = connectDB();
    $restaurats = 0;

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['coordenades_geo'])) {
            continue;
        }

        $tipus = $data['tipus'] ?? '';
        $geojson = json_encode($data['coordenades_geo'], JSON_UNESCAPED_UNICODE);

        try {
            switch ($tipus) {
                case 'parcela':
                    $id = (int)($data['id_parcela'] ?? 0);
                    if ($id <= 0) break;
                    // Només si existeix la parcel·la però no té geometria
                    $check = $pdo->prepare("SELECT id_parcela FROM parcela WHERE id_parcela = ? AND coordenades_geo IS NULL");
                    $check->execute([$id]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE parcela SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_parcela = ?");
                        $stmt->execute([$geojson, $id]);
                        $restaurats++;
                    }
                    break;

                case 'sector':
                    $id = (int)($data['id_sector'] ?? 0);
                    if ($id <= 0) break;
                    $check = $pdo->prepare("SELECT id_sector FROM sector WHERE id_sector = ? AND coordenades_geo IS NULL");
                    $check->execute([$id]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE sector SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_sector = ?");
                        $stmt->execute([$geojson, $id]);
                        $restaurats++;
                    }
                    break;

                case 'infraestructura':
                    $id = (int)($data['id_infra'] ?? 0);
                    if ($id <= 0) break;
                    $check = $pdo->prepare("SELECT id_infra FROM infraestructura WHERE id_infra = ? AND coordenades_geo IS NULL");
                    $check->execute([$id]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE infraestructura SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_infra = ?");
                        $stmt->execute([$geojson, $id]);
                        $restaurats++;
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log('[CultiuConnect] Error restaurant: ' . $e->getMessage());
        }
    }

    echo json_encode(['restaurats' => $restaurats, 'missatge' => "Restaurades $restaurats geometries"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['restaurats' => 0, 'error' => $e->getMessage()]);
}
