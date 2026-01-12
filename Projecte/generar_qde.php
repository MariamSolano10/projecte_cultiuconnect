<?php
// generar_qde.php - Simula la generació i descàrrega del Quadern d'Explotació (QDE)

$any = $_GET['any'] ?? date('Y');

// 1. Capçaleres per forçar la descàrrega del fitxer
header('Content-Type: application/pdf'); // Tipus de contingut: PDF
header('Content-Disposition: attachment; filename="QDE_Oficial_' . $any . '.pdf"');
header('Cache-Control: must-revalidate'); 
header('Pragma: public');
header('Content-Length: 50'); // Mida simulada

// 2. Contingut del Fitxer (Molt simple, només per demostrar que funciona la descàrrega)
echo "Quadern d'Explotació generat per a l'any " . $any . ". (Dades simulades)";

// Normalment aquí hi hauria la lògica complexa de la llibreria PDF que construeix el document.

exit;
?>