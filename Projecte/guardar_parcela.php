<?php
// guardar_parcela.php - Versió compatible amb MariaDB / MySQL

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    $pdo = connectDB();

    $input = json_decode(file_get_contents('php://input'), true);

    $nom = $input['nom_sector'] ?? '';
    $geometry = $input['geojson'] ?? null;

    if (empty($nom) || !$geometry) {
        echo json_encode(['success' => false, 'error' => 'Dades incompletes']);
        exit;
    }

    $geojson_str = json_encode($geometry);

    // INSERT sense RETURNING (MariaDB no ho suporta)
    $sql = "INSERT INTO Sector (nom, coordenades_geo) 
            VALUES (:nom, ST_GeomFromGeoJSON(:geojson))";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':geojson', $geojson_str);
    $stmt->execute();

    // Obtenim l'ID del registre acabat d'inserir
    $id = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => $id]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>