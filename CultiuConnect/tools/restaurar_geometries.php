<?php
/**
 * tools/restaurar_geometries.php — Restaura geometries des de fitxers JSON
 * quan la base de dades s'ha reinicialitzat (ex: després de docker compose down -v).
 * 
 * Ús manual:   php tools/restaurar_geometries.php
 * O via web:   http://localhost:8080/tools/restaurar_geometries.php
 */

require_once __DIR__ . '/../config/db.php';

$backupDir = __DIR__ . '/../data/geometries';

if (!is_dir($backupDir)) {
    echo "No hi ha cap directori de backup ($backupDir).\n";
    exit(0);
}

$files = glob($backupDir . '/*.json');
if (empty($files)) {
    echo "No s'han trobat fitxers de backup.\n";
    exit(0);
}

echo "Trobats " . count($files) . " fitxers de backup.\n\n";

try {
    $pdo = connectDB();
    $restaurats = ['parcela' => 0, 'sector' => 0, 'infra' => 0, 'errors' => 0];

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['coordenades_geo'])) {
            echo "⚠ Error llegint: $file\n";
            $restaurats['errors']++;
            continue;
        }

        $tipus = $data['tipus'] ?? '';
        $geojson = json_encode($data['coordenades_geo'], JSON_UNESCAPED_UNICODE);

        try {
            switch ($tipus) {
                case 'parcela':
                    $id = (int)($data['id_parcela'] ?? 0);
                    if ($id <= 0) break;
                    $stmt = $pdo->prepare("UPDATE parcela SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_parcela = ?");
                    $stmt->execute([$geojson, $id]);
                    $restaurats['parcela']++;
                    echo "✓ Parcel·la $id restaurada\n";
                    break;

                case 'sector':
                    $id = (int)($data['id_sector'] ?? 0);
                    if ($id <= 0) break;
                    $stmt = $pdo->prepare("UPDATE sector SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_sector = ?");
                    $stmt->execute([$geojson, $id]);
                    $restaurats['sector']++;
                    echo "✓ Sector $id restaurat\n";
                    break;

                case 'infraestructura':
                    $id = (int)($data['id_infra'] ?? 0);
                    if ($id <= 0) break;
                    $stmt = $pdo->prepare("UPDATE infraestructura SET coordenades_geo = ST_GeomFromGeoJSON(?) WHERE id_infra = ?");
                    $stmt->execute([$geojson, $id]);
                    $restaurats['infra']++;
                    echo "✓ Infraestructura $id restaurada\n";
                    break;

                default:
                    echo "? Tipus desconegut ($tipus): $file\n";
                    $restaurats['errors']++;
            }
        } catch (Exception $e) {
            echo "✗ Error SQL: " . $e->getMessage() . "\n";
            $restaurats['errors']++;
        }
    }

    echo "\n=== RESUM ===\n";
    echo "Parcel·les: {$restaurats['parcela']}\n";
    echo "Sectors:    {$restaurats['sector']}\n";
    echo "Infra.:     {$restaurats['infra']}\n";
    echo "Errors:     {$restaurats['errors']}\n";
    echo "\nRestauració completada!\n";

} catch (Exception $e) {
    echo "Error de connexió: " . $e->getMessage() . "\n";
    exit(1);
}
