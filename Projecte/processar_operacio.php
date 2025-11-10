<?php
// processar_operacio.php - Lògica d'inserció de Tractament a Quadern_Explotacio

include 'db_connect.php'; // Inclusió de la funció connectDB()

$missatge = "";
$estat = "error";

// 1. Comprovar la sol·licitud POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = connectDB();

        // 2. Neteja i Validació de les dades del formulari
        $id_sector = filter_input(INPUT_POST, 'parcela_aplicacio', FILTER_VALIDATE_INT);
        $id_producte = filter_input(INPUT_POST, 'producte_quimic', FILTER_VALIDATE_INT);
        $data_hora_aplicacio = filter_input(INPUT_POST, 'data_aplicacio', FILTER_SANITIZE_STRING);
        $dosi_per_ha = filter_input(INPUT_POST, 'dosi', FILTER_VALIDATE_FLOAT);
        $quantitat_total_text = filter_input(INPUT_POST, 'quantitat_total', FILTER_SANITIZE_STRING); // Per exemple: "7.80 L"
        $comentaris = filter_input(INPUT_POST, 'comentaris', FILTER_SANITIZE_STRING);
        $operari = filter_input(INPUT_POST, 'operari', FILTER_SANITIZE_STRING);

        // Extreure la quantitat total numèrica i la unitat
        $parts_quantitat = explode(' ', trim($quantitat_total_text));
        $quantitat_total = floatval($parts_quantitat[0]);
        $unitat_usada = $id_producte > 0 ? (isset($parts_quantitat[1]) ? $parts_quantitat[1] : 'N/A') : 'N/A';

        // 3. Comprovació bàsica de validació
        if (!$id_sector || $dosi_per_ha === false || $quantitat_total === false || empty($data_hora_aplicacio)) {
            throw new Exception("Dades del formulari incompletes o invàlides.");
        }

        // 4. Preparar la consulta d'inserció
        $sql = "INSERT INTO Quadern_Explotacio (
            id_sector, 
            id_producte, 
            data_hora_aplicacio, 
            tipus_operacio, 
            dosi_per_ha, 
            quantitat_total_aplicada, 
            unitat_aplicada, 
            operari, 
            observacions
        ) VALUES (
            :id_sector, 
            :id_producte, 
            :data_hora, 
            :tipus_op, 
            :dosi_ha, 
            :quantitat_total, 
            :unitat, 
            :operari, 
            :comentaris
        )";

        $stmt = $pdo->prepare($sql);

        // Determinar el Tipus d'Operació (simplificat)
        $tipus_operacio = ($id_producte > 0) ? 'Tractament Fitosanitari/Adob' : 'Altres Operacions';

        // 5. Executar la inserció
        $stmt->execute([
            ':id_sector' => $id_sector,
            ':id_producte' => ($id_producte == 0 ? null : $id_producte), // NULL si és 'Sense Producte'
            ':data_hora' => str_replace('T', ' ', $data_hora_aplicacio),
            ':tipus_op' => $tipus_operacio,
            ':dosi_ha' => $dosi_per_ha,
            ':quantitat_total' => $quantitat_total,
            ':unitat' => $unitat_usada,
            ':operari' => $operari,
            ':comentaris' => $comentaris
        ]);

        $missatge = "✅ Registre del tractament realitzat amb èxit! (ID: " . $pdo->lastInsertId() . ")";
        $estat = "exit";

    } catch (PDOException $e) {
        // Error de la BD
        $missatge = "❌ Error en registrar el tractament (Base de Dades): " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        // Altres errors
        $missatge = "❌ Error en el procés: " . htmlspecialchars($e->getMessage());
    }
} else {
    $missatge = "❌ Accés no vàlid al processador.";
}

// 6. Redirecció o Mostra de Missatge
// Pots redirigir a una pàgina de confirmació o mostrar el missatge directament
// Aquí mostrem un missatge senzill per simplicitat.

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <title>Processament d'Operació</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .exit {
            color: #1E4620;
            border: 2px solid #4CAF50;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
        }

        .error {
            color: #c0392b;
            border: 2px solid #e74c3c;
            background: #fbecec;
            padding: 15px;
            border-radius: 5px;
        }

        .boto {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Resultat del Registre</h1>
        <div class="<?= $estat; ?>">
            <?= $missatge; ?>
        </div>
        <a href="operacio_nova.php" class="boto"><i class="fas fa-undo"></i> Tornar al Formulari</a>
        <a href="quadern.php" class="boto" style="background-color: #1E4620;"><i class="fas fa-file-invoice"></i> Veure
            Quadern</a>
    </div>
</body>

</html>