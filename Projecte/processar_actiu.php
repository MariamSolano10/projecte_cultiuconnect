<?php
// processar_actiu.php - Lògica de processament del formulari Nou Actiu/Inversió

// Inclusió de la connexió. És VITAL que aquest fitxer INCLOGUI session_start()
include 'db_connect.php'; 

// 1. COMPROVACIÓ DEL MÈTODE D'ENVIAMENT
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $missatge = "Accés no vàlid. El formulari s'ha d'enviar mitjançant POST.";
    // Redirecció a la pàgina del formulari amb el missatge d'error
    header("Location: nou_actiu.php?estat=error&missatge=" . urlencode($missatge));
    exit;
}

// 2. INICIALITZACIÓ DE LA SESSIÓ (Actius)
// Ens assegurem que la llista d'actius a la sessió existeixi
if (!isset($_SESSION['actius_inversions'])) {
    // Si no hi ha dades, inicialitzem amb un array buit
    $_SESSION['actius_inversions'] = []; 
}

// 3. RECOLLIDA, SANITITZACIÓ I VALIDACIÓ DE DADES
$nom_actiu = filter_input(INPUT_POST, 'nom_actiu', FILTER_SANITIZE_STRING);
$data_compra = filter_input(INPUT_POST, 'data_compra', FILTER_SANITIZE_STRING);
$valor_inicial = filter_input(INPUT_POST, 'valor_inicial', FILTER_VALIDATE_FLOAT);
$vida_util_anys = filter_input(INPUT_POST, 'vida_util_anys', FILTER_VALIDATE_INT);
// Si no s'introdueix valor, assumeix 0.
$manteniment_anual = filter_input(INPUT_POST, 'manteniment_anual', FILTER_VALIDATE_FLOAT) ?: 0;

// Validació
if (empty($nom_actiu) || !$valor_inicial || !$vida_util_anys || $valor_inicial <= 0 || $vida_util_anys <= 0) {
    $missatge = "Error: Tots els camps obligatoris (Nom, Valor, Vida Útil) han de ser vàlids i positius.";
    header("Location: nou_actiu.php?estat=error&missatge=" . urlencode($missatge));
    exit;
}


// 4. CÀLCULS I CREACIÓ DEL NOU REGISTRE
$amortitzacio_anual = $valor_inicial / $vida_util_anys;

$nou_actiu = [
    // L'ID és l'últim ID + 1 (simulat)
    'id' => count($_SESSION['actius_inversions']) + 1, 
    'nom_actiu' => $nom_actiu,
    'data_compra' => $data_compra,
    'valor_inicial' => $valor_inicial,
    'vida_util_anys' => $vida_util_anys,
    'amortitzacio_anual' => round($amortitzacio_anual, 2), 
    'valor_comptable' => $valor_inicial, 
    'manteniment_anual' => $manteniment_anual
];

// 5. GUARDAT A LA SESSIÓ (Simulació d'Inserció)
$_SESSION['actius_inversions'][] = $nou_actiu;


// 6. REDIRECCIÓ AMB MISSATGE DE SUCCÉS
$missatge_succes = "✅ Actiu **" . htmlspecialchars($nom_actiu) . "** registrat correctament. Amortització Anual: **" . number_format($amortitzacio_anual, 2) . " €**.";

header("Location: nou_actiu.php?estat=success&missatge=" . urlencode($missatge_succes));
exit;
?>