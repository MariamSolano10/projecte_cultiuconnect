<?php
// processar_nou_lot.php - Script per processar l'entrada de nous lots a Inventari_Estoc

include 'db_connect.php'; 

$missatge = "";
$estat = "error";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = connectDB();

        // 1. RECOLLIDA I VALIDACIÓ DE LES DADES (Adaptat a la taula Inventari_Estoc)
        $id_producte = filter_input(INPUT_POST, 'id_producte', FILTER_VALIDATE_INT);
        $num_lot = filter_input(INPUT_POST, 'num_lot', FILTER_SANITIZE_STRING);
        $quantitat_disponible = filter_input(INPUT_POST, 'quantitat_disponible', FILTER_VALIDATE_FLOAT);
        $data_caducitat = filter_input(INPUT_POST, 'data_caducitat', FILTER_SANITIZE_STRING) ?: NULL;
        $data_compra = filter_input(INPUT_POST, 'data_compra', FILTER_SANITIZE_STRING);
        $ubicacio_magatzem = filter_input(INPUT_POST, 'ubicacio_magatzem', FILTER_SANITIZE_STRING) ?: NULL;
        $proveidor = filter_input(INPUT_POST, 'proveidor', FILTER_SANITIZE_STRING) ?: NULL;
        $preu_adquisicio = filter_input(INPUT_POST, 'preu_adquisicio', FILTER_VALIDATE_FLOAT) ?: 0.00;

        // Recuperar la unitat de mesura del producte per la FK
        $stmt_unitat = $pdo->prepare("SELECT unitat_mesura FROM Producte_Quimic WHERE id_producte = :id_producte");
        $stmt_unitat->execute([':id_producte' => $id_producte]);
        $resultat_unitat = $stmt_unitat->fetch(PDO::FETCH_ASSOC);

        if (!$id_producte || !$num_lot || $quantitat_disponible === false || !$resultat_unitat) {
            throw new Exception("Dades bàsiques del lot incompletes o producte no trobat. Assegura't de seleccionar un producte i introduir la quantitat.");
        }
        
        $unitat_mesura = $resultat_unitat['unitat_mesura'];


        // 2. PREPARAR LA CONSULTA D'INSERCIÓ
        $sql = "INSERT INTO Inventari_Estoc (
            id_producte, 
            num_lot, 
            quantitat_disponible, 
            unitat_mesura, 
            data_caducitat, 
            ubicacio_magatzem, 
            data_compra, 
            proveidor, 
            preu_adquisicio
        ) VALUES (
            :id_prod, 
            :num_lot, 
            :quantitat, 
            :unitat, 
            :data_cad, 
            :ubicacio, 
            :data_compra, 
            :proveidor, 
            :preu
        )";

        $stmt = $pdo->prepare($sql);
        
        // 3. EXECUTAR LA INSERCIÓ
        $stmt->execute([
            ':id_prod' => $id_producte,
            ':num_lot' => $num_lot,
            ':quantitat' => $quantitat_disponible,
            ':unitat' => $unitat_mesura,
            ':data_cad' => $data_caducitat,
            ':ubicacio' => $ubicacio_magatzem,
            ':data_compra' => $data_compra,
            ':proveidor' => $proveidor,
            ':preu' => $preu_adquisicio
        ]);

        $missatge = "✅ Nou lot d'estoc del producte '" . $resultat_unitat['nom_comercial'] . "' registrat amb èxit a l'inventari (ID Lot: " . $pdo->lastInsertId() . ")";
        $estat = "exit";

    } catch (PDOException $e) {
        $missatge = "❌ Error de Base de Dades (Codi: {$e->getCode()}): No s'ha pogut inserir l'estoc. " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        $missatge = "❌ Error en el procés: " . htmlspecialchars($e->getMessage());
    }
} else {
    $missatge = "❌ Accés no vàlid. S'esperava una petició POST del formulari.";
}

// Redirecció o missatge final
if ($estat === "exit") {
    // Redirecció a la llista d'inventari per veure el resultat
    header("Location: estoc.php?status=success&msg=" . urlencode($missatge));
    exit();
} else {
    // Si hi ha error, es mostra el missatge (o es podria redirigir amb el missatge d'error)
    // Aquí es mostra un HTML de resultat simple per simplicitat
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Resultat</title>
    <link rel="stylesheet" href="estils.css">
</head>
<body>
    <div style="padding: 50px; text-align: center; border: 1px solid red; margin: 50px; background-color: #f8d7da; color: #721c24;">
        <h2><?= ($estat === 'exit' ? 'Èxit' : 'Error'); ?> en el Registre</h2>
        <p><?= $missatge; ?></p>
        <p><a href="nou_lot.php">Tornar al formulari</a> | <a href="estoc.php">Anar a l'Inventari</a></p>
    </div>
</body>
</html>
<?php
}