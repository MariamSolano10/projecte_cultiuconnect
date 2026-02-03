<?php
// Registre_Tractament.php
include 'db_connect.php';

/**
 * Registra una aplicació de camp (tractament, fertilització, poda, etc.)
 * i actualitza l'estoc del producte utilitzat.
 * 
 * @param array $aplicacio_data Dades de la taula Aplicacio
 * @return bool True si es registra correctament, False si hi ha error.
 */
function registrarAplicacio(array $aplicacio_data): bool {
    $pdo = connectDB();
    $pdo->beginTransaction();

    try {
        // --- 1. INSERT a la taula Aplicacio ---
        $sql_aplicacio = "INSERT INTO Aplicacio (
                id_sector,
                id_fila,
                id_estoc,
                data_event,
                tipus_event,
                descripcio,
                dosi_aplicada,
                quantitat_consumida_total,
                volum_caldo,
                maquinaria_utilitzada,
                operari_carnet,
                condicions_ambientals
            ) VALUES (
                :id_sector,
                :id_fila,
                :id_estoc,
                :data_event,
                :tipus_event,
                :descripcio,
                :dosi_aplicada,
                :quantitat_consumida_total,
                :volum_caldo,
                :maquinaria_utilitzada,
                :operari_carnet,
                :condicions_ambientals
            )";

        $stmt = $pdo->prepare($sql_aplicacio);
        $stmt->execute($aplicacio_data);

        // --- 2. UPDATE a Inventari_Estoc ---
        $sql_update = "UPDATE Inventari_Estoc
                       SET quantitat_disponible = quantitat_disponible - :quantitat_consumida_total
                       WHERE id_estoc = :id_estoc";
        
        $stmt = $pdo->prepare($sql_update);
        $stmt->bindValue(':quantitat_consumida_total', $aplicacio_data['quantitat_consumida_total']);
        $stmt->bindValue(':id_estoc', $aplicacio_data['id_estoc']);
        $stmt->execute();

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Error en el registre de l'aplicació: " . $e->getMessage();
        return false;
    }
}

// --- EXEMPLE D'ÚS ---
$dades_aplicacio = [
    'id_sector' => 5,
    'id_fila' => null, // Es pot posar NULL si és tot el sector
    'id_estoc' => 101,
    'data_event' => '2025-10-06',
    'tipus_event' => 'Tractament Fitosanitari',
    'descripcio' => 'Aplicació preventiva contra el pugó',
    'dosi_aplicada' => 0.5,
    'quantitat_consumida_total' => 15.0,
    'volum_caldo' => 300.0,
    'maquinaria_utilitzada' => 'Atomitzador model X200',
    'operari_carnet' => 'JOAN123',
    'condicions_ambientals' => 'Temperatura 22°C, Vent suau (5 km/h)'
];

if (registrarAplicacio($dades_aplicacio)) {
    echo "✅ Aplicació i descompte d'inventari registrats correctament!";
} else {
    echo "❌ Error: l'operació s'ha revertit (Rollback).";
}
?>
