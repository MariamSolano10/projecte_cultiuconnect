<?php
// processar_analisi.php

// 1. COMPROVACIÓ DEL MÈTODE D'ENVIAMENT
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Redirigir si l'accés no és vàlid (per exemple, accés directe per URL)
    $missatge = "❌ Accés no vàlid. El formulari s'ha d'enviar mitjançant POST.";
    header("Location: nou_analisi.php?estat=error&missatge=" . urlencode($missatge));
    exit;
}

// Incloure la funció de connexió a la base de dades (NECESSITA db_connect.php)
include 'db_connect.php'; 

// Inicialitzar variables de missatge
$estat = 'error';
$missatge = '';

try {
    // 2. CONNEXIÓ A LA BASE DE DADES
    $pdo = connectDB();

    // 3. RECOLLIDA I SANITITZACIÓ DE DADES
    // Utilitzem ?? '' per a valors opcionals per evitar errors si no s'envien
    $id_sector = $_POST['id_sector'] ?? '';
    $tipus_mostra = $_POST['tipus_mostra'] ?? '';
    $data_mostreig = $_POST['data_mostreig'] ?? '';
    $descripcio_nutricional = $_POST['descripcio_nutricional'] ?? '';
    
    $data_informe = $_POST['data_informe'] ?? NULL;
    $laboratori = $_POST['laboratori'] ?? NULL;
    $ph = $_POST['ph'] ?? NULL;
    $mo_percent = $_POST['mo_percent'] ?? NULL;
    $ce_ds_m = $_POST['ce_ds_m'] ?? NULL;
    $n_ppm = $_POST['n_ppm'] ?? NULL;
    $p_ppm = $_POST['p_ppm'] ?? NULL;
    $k_ppm = $_POST['k_ppm'] ?? NULL;
    $recomanacions = $_POST['recomanacions'] ?? NULL;

    // Convertim cadenes buides a NULL per als camps opcionals (per a la BBDD)
    $data_informe = empty($data_informe) ? NULL : $data_informe;
    $laboratori = empty($laboratori) ? NULL : $laboratori;
    $recomanacions = empty($recomanacions) ? NULL : $recomanacions;
    // La resta de camps numèrics ja estan tractats pel formulari HTML5, però assegurem NULL
    $ph = is_numeric($ph) ? (float)$ph : NULL;
    // ... repetir per a mo_percent, ce_ds_m, n_ppm, p_ppm, k_ppm si cal una validació estricta

    // 4. VALIDACIÓ DE CAMPS OBLIGATORIS (revisar que no estiguin buits)
    if (empty($id_sector) || empty($tipus_mostra) || empty($data_mostreig) || empty($descripcio_nutricional)) {
        $missatge = "⚠️ Falten camps obligatoris (Sector, Data de Mostreig, o Resum Nutricional). Si us plau, omple tots els camps marcats amb *.";
        throw new Exception($missatge); // Forcem l'error
    }

    // 5. INSERCIÓ DE DADES (Utilitzant Sentències Preparades per seguretat)
    $sql = "INSERT INTO Analisi_Lab (
                id_sector, tipus_mostra, data_mostreig, data_informe, laboratori,
                ph, mo_percent, ce_ds_m, n_ppm, p_ppm, k_ppm,
                descripcio_nutricional, recomanacions
            ) VALUES (
                :id_sector, :tipus_mostra, :data_mostreig, :data_informe, :laboratori,
                :ph, :mo_percent, :ce_ds_m, :n_ppm, :p_ppm, :k_ppm,
                :descripcio_nutricional, :recomanacions
            )";
            
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':id_sector', $id_sector);
    $stmt->bindParam(':tipus_mostra', $tipus_mostra);
    $stmt->bindParam(':data_mostreig', $data_mostreig);
    $stmt->bindParam(':data_informe', $data_informe);
    $stmt->bindParam(':laboratori', $laboratori);
    $stmt->bindParam(':ph', $ph);
    $stmt->bindParam(':mo_percent', $mo_percent);
    $stmt->bindParam(':ce_ds_m', $ce_ds_m);
    $stmt->bindParam(':n_ppm', $n_ppm);
    $stmt->bindParam(':p_ppm', $p_ppm);
    $stmt->bindParam(':k_ppm', $k_ppm);
    $stmt->bindParam(':descripcio_nutricional', $descripcio_nutricional);
    $stmt->bindParam(':recomanacions', $recomanacions);
    
    $stmt->execute();
    
    // Si l'execució és reeixida
    $estat = 'exit';
    $missatge = "✅ Analítica registrada correctament! Id: " . $pdo->lastInsertId();

} catch (Exception $e) {
    // 6. GESTIÓ D'ERRORS (Connexió, Validació o SQL)
    $estat = 'error';
    if (!isset($missatge) || $missatge === '') {
        $missatge = "❌ Error al processar l'analítica: " . htmlspecialchars($e->getMessage());
    }
}

// 7. REDIRECCIÓ FINAL
// Retornem l'usuari a la pàgina del formulari amb l'estat i el missatge
header("Location: nou_analisi.php?estat=" . $estat . "&missatge=" . urlencode($missatge));
exit;
?>