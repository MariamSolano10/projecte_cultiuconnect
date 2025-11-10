<?php
// processar_nou_monitoratge.php - Lògica d'inserció a la taula Monitoratge_Plaga

include 'db_connect.php'; 

$missatge = "";
$estat = "error";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = connectDB();

        // 1. RECOLLIDA I VALIDACIÓ DE LES DADES (Adaptat a la taula Monitoratge_Plaga)
        $id_sector = filter_input(INPUT_POST, 'sector_observat', FILTER_VALIDATE_INT);
        $data_observacio = filter_input(INPUT_POST, 'data_observacio', FILTER_SANITIZE_STRING); // Format DATETIME
        $tipus_problema = filter_input(INPUT_POST, 'tipus_problema', FILTER_SANITIZE_STRING); // ENUM: Plaga, Malaltia, Deficiencia, Mala Herba
        $descripcio_breu = filter_input(INPUT_POST, 'descripcio_breu', FILTER_SANITIZE_STRING);
        $nivell_poblacio = filter_input(INPUT_POST, 'nivell_poblacio', FILTER_VALIDATE_FLOAT); 
        $llindar_assolit = filter_input(INPUT_POST, 'llindar_assolit', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Comprovació bàsica
        if (!$id_sector || empty($data_observacio) || empty($tipus_problema) || empty($descripcio_breu) || is_null($llindar_assolit)) {
            throw new Exception("Dades del formulari incompletes o invàlides.");
        }

        // 2. PREPARAR LA CONSULTA D'INSERCIÓ
        $sql = "INSERT INTO Monitoratge_Plaga (
            id_sector, 
            data_observacio, 
            tipus_problema, 
            descripcio_breu, 
            nivell_poblacio, 
            llindar_intervencio_assolit
            -- (Ometem coordenades_geo per simplificar, si no es recullen al formulari)
        ) VALUES (
            :id_sector, 
            :data_obs, 
            :tipus, 
            :desc_breu, 
            :nivell, 
            :llindar
        )";

        $stmt = $pdo->prepare($sql);
        
        // 3. EXECUTAR LA INSERCIÓ
        $stmt->execute([
            ':id_sector' => $id_sector,
            ':data_obs' => str_replace('T', ' ', $data_observacio),
            ':tipus' => $tipus_problema,
            ':desc_breu' => $descripcio_breu,
            ':nivell' => $nivell_poblacio,
            ':llindar' => $llindar_assolit
        ]);

        $missatge = "✅ Registre de Monitoratge inserit amb èxit a la BD (ID: " . $pdo->lastInsertId() . ")";
        $estat = "exit";

    } catch (PDOException $e) {
        $missatge = "❌ Error de Base de Dades: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        $missatge = "❌ Error en el procés: " . htmlspecialchars($e->getMessage());
    }
} else {
    $missatge = "❌ Accés no vàlid al processador.";
}

// Pantalla de resultat (la pots reutilitzar del codi anterior)
// ... (HTML de resultat)
?>