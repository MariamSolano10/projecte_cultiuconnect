<?php
include 'db_connect.php';

try {
    $pdo = connectDB();
    echo "Connexió a la BD realitzada amb èxit! ✅";
} catch (Exception $e) {
    echo "Error de prova: " . $e->getMessage();
}
?>