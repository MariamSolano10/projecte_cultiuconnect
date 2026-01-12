<?php
// processar_monitoratge.php

// 1. COMPROVACIÓ DEL MÈTODE D'ENVIAMENT
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $missatge = "❌ Accés no vàlid. El formulari s'ha d'enviar mitjançant POST.";
    header("Location: operacio_nova.php?estat=error&missatge=" . urlencode($missatge));
    exit;
}

// Incloure la funció de connexió a la base de dades
include 'db_connect.php'; 

$estat = 'error';
$missatge = '';

try {
    // 2. CONNEXIÓ A LA BASE DE DADES
    $pdo = connectDB();

    // 3. RECOLLIDA I SANITITZACIÓ DE DADES
    $id_sector = $_POST['id_sector'] ?? '';
    $data_observacio = $_POST['data_observacio'] ?? '';
    $hora_observacio = $_POST['hora_observacio'] ?? '00:00:00';
    $tipus_problema = $_POST['tipus_problema'] ?? '';
    $element_observat = $_POST['element_observat'] ?? '';
    $descripcio = $_POST['descripcio'] ?? '';
    
    // Camps opcionals
    $nivell_danys = $_POST['nivell_danys'] ?? NULL;
    // La casella de verificació només s'envia si està marcada. Si no s'envia, és 0 (fals).
    $llindar_assolit = isset($_POST['llindar_assolit']) ? 1 : 0; 

    // Concatenem data i hora per al format DATETIME de la BBDD
    $datahora_observacio = $data_observacio . ' ' . $hora_observacio;

    // Convertim cadenes buides a NULL per als camps opcionals
    $nivell_danys = empty($nivell_danys) ? NULL : $nivell_danys;

    // 4. VALIDACIÓ DE CAMPS OBLIGATORIS
    if (empty($id_sector) || empty($data_observacio) || empty($tipus_problema) || empty($element_observat) || empty($descripcio)) {
        $missatge = "⚠️ Falten camps obligatoris. Si us plau, omple tots els camps marcats amb *.";
        throw new Exception($missatge);
    }

    // 5. INSERCIÓ DE DADES (Sentències Preparades)
    $sql = "INSERT INTO Monitoratge_Camp (
                id_sector, datahora_observacio, tipus_problema, element_observat,
                descripcio, nivell_danys, llindar_assolit
            ) VALUES (
                :id_sector, :datahora_observacio, :tipus_problema, :element_observat,
                :descripcio, :nivell_danys, :llindar_assolit
            )";
            
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':id_sector', $id_sector);
    $stmt->bindParam(':datahora_observacio', $datahora_observacio);
    $stmt->bindParam(':tipus_problema', $tipus_problema);
    $stmt->bindParam(':element_observat', $element_observat);
    $stmt->bindParam(':descripcio', $descripcio);
    $stmt->bindParam(':nivell_danys', $nivell_danys);
    $stmt->bindParam(':llindar_assolit', $llindar_assolit);
    
    $stmt->execute();
    
    // Si l'execució és reeixida
    $estat = 'exit';
    $missatge = "✅ Observació de Monitoratge registrada correctament! Id: " . $pdo->lastInsertId();

} catch (Exception $e) {
    // 6. GESTIÓ D'ERRORS
    $estat = 'error';
    if (!isset($missatge) || $missatge === '') {
        $missatge = "❌ Error al processar el monitoratge: " . htmlspecialchars($e->getMessage());
    }
}

// 7. REDIRECCIÓ FINAL
header("Location: operacio_nova.php?estat=" . $estat . "&missatge=" . urlencode($missatge));
exit;
?>