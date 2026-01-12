<?php
// processar_producte.php - Processa les dades d'un nou producte

// 1. COMPROVACIÓ DEL MÈTODE D'ENVIAMENT
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Si s'intenta accedir directament per URL (no a través del formulari), redirigim amb error.
    $missatge = "❌ Accés no vàlid. El formulari s'ha d'enviar mitjançant POST.";
    header("Location: nou_producte.php?estat=error&missatge=" . urlencode($missatge));
    exit;
}

// Incloure la funció de connexió a la base de dades (ASSUMIM que 'db_connect.php' existeix i retorna un objecte PDO)
include 'db_connect.php'; 

$estat = 'error';
$missatge = '';

try {
    // 2. CONNEXIÓ A LA BASE DE DADES
    $pdo = connectDB();

    // 3. RECOLLIDA I SANITITZACIÓ DE DADES
    // Camps obligatoris
    $nom_comercial = $_POST['nom_comercial'] ?? '';
    $tipus_producte = $_POST['tipus_producte'] ?? '';
    $unitat_mesura = $_POST['unitat_mesura'] ?? 'L'; 
    
    // Camps opcionals
    $fabricant = $_POST['fabricant'] ?? NULL;
    $registre_oficial = $_POST['registre_oficial'] ?? NULL;
    $composicio = $_POST['composicio'] ?? NULL;
    $preu_compra = $_POST['preu_compra'] ?? NULL;
    $estoc_inicial = $_POST['estoc_inicial'] ?? 0.0;

    // NETEJA I CONVERSIÓ DE TIPUS
    
    // Convertim cadenes buides a NULL per als camps opcionals de text
    $fabricant = empty($fabricant) ? NULL : $fabricant;
    $registre_oficial = empty($registre_oficial) ? NULL : $registre_oficial;
    $composicio = empty($composicio) ? NULL : $composicio;
    
    // Assegurem que els números siguin floats o NULL (si és buit)
    $preu_compra = is_numeric($preu_compra) ? (float)$preu_compra : NULL;
    $estoc_inicial = is_numeric($estoc_inicial) ? (float)$estoc_inicial : 0.0;


    // 4. VALIDACIÓ DE CAMPS OBLIGATORIS
    if (empty($nom_comercial) || empty($tipus_producte) || empty($unitat_mesura)) {
        $missatge = "⚠️ Falten camps obligatoris (Nom Comercial, Tipus, o Unitat de Mesura).";
        throw new Exception($missatge);
    }
    
    /* NOTA IMPORTANT SOBRE LA BBDD:
    Assumim que la vostra taula Producte té un camp per a l'Estoc Actual (estoc_actual)
    i que en registrar un producte nou, l'Estoc Actual s'omple amb l'Estoc Inicial.
    */

    // 5. INSERCIÓ DE DADES (Utilitzant Sentències Preparades PDO per seguretat)
    $sql = "INSERT INTO Producte (
                nom_comercial, fabricant, registre_oficial, tipus_producte,
                composicio, unitat_mesura, preu_compra, estoc_actual
            ) VALUES (
                :nom_comercial, :fabricant, :registre_oficial, :tipus_producte,
                :composicio, :unitat_mesura, :preu_compra, :estoc_inicial
            )";
            
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':nom_comercial', $nom_comercial);
    $stmt->bindParam(':fabricant', $fabricant);
    $stmt->bindParam(':registre_oficial', $registre_oficial);
    $stmt->bindParam(':tipus_producte', $tipus_producte);
    $stmt->bindParam(':composicio', $composicio);
    $stmt->bindParam(':unitat_mesura', $unitat_mesura);
    $stmt->bindParam(':preu_compra', $preu_compra);
    $stmt->bindParam(':estoc_inicial', $estoc_inicial); // Usa estoc_inicial per omplir estoc_actual
    
    $stmt->execute();
    
    // Si l'execució és reeixida
    $estat = 'exit';
    $missatge = "✅ Producte '" . htmlspecialchars($nom_comercial) . "' registrat i afegit a inventari. Id: " . $pdo->lastInsertId();

} catch (Exception $e) {
    // 6. GESTIÓ D'ERRORS (Connexió, Validació o SQL)
    $estat = 'error';
    if (!isset($missatge) || $missatge === '') {
        $missatge = "❌ Error al registrar el producte: " . htmlspecialchars($e->getMessage());
    }
}

// 7. REDIRECCIÓ FINAL
// Retornem l'usuari al formulari amb l'estat i el missatge.
header("Location: nou_producte.php?estat=" . $estat . "&missatge=" . urlencode($missatge));
exit;
?>