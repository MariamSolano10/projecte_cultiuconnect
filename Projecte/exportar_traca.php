<?php
// exportar_traca.php - Simula l'exportació de la Traçabilitat a Excel

$lot = $_GET['lot'] ?? 'general';

// 1. Capçaleres per forçar la descàrrega del fitxer (Excel)
header('Content-Type: application/vnd.ms-excel'); // Tipus de contingut: Excel
header('Content-Disposition: attachment; filename="Traçabilitat_' . $lot . '_' . date('Ymd') . '.xls"');
header('Cache-Control: must-revalidate'); 
header('Pragma: public');
header('Content-Length: 100'); // Mida simulada

// 2. Contingut del Fitxer (CSV bàsic simulant una taula Excel)
echo "Nom,Data Operació,Producte\n";
echo "Poma Gala,2025-05-10,Fungicida\n";
echo "Poma Gala,2025-06-01,Fertilitzant\n";
echo "Aquesta és la traçabilitat simulada del lot: " . $lot . ".";

// Normalment aquí hi hauria la lògica complexa de la llibreria Excel que construeix la taula.

exit;
?>