<?php
/**
 * modules/protocols/processar_protocol.php
 * Accions: crear, editar
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/protocols/protocols.php');
    exit;
}

$accio = $_POST['accio'] ?? '';

// Construïm el JSON de productes
$noms  = $_POST['producte_nom']  ?? [];
$dosis = $_POST['producte_dosi'] ?? [];
$productes_arr = [];
foreach ($noms as $i => $nom) {
    $nom = trim($nom);
    if ($nom !== '') {
        $productes_arr[] = [
            'nom'  => $nom,
            'dosi' => trim($dosis[$i] ?? ''),
        ];
    }
}
$productes_json = !empty($productes_arr) ? json_encode($productes_arr, JSON_UNESCAPED_UNICODE) : null;

$nom_protocol        = trim($_POST['nom_protocol']        ?? '');
$descripcio          = trim($_POST['descripcio']          ?? '') ?: null;
$condicions_ambientals = trim($_POST['condicions_ambientals'] ?? '') ?: null;

if (!$nom_protocol) {
    set_flash('error', 'El nom del protocol és obligatori.');
    $url_back = $accio === 'editar'
        ? BASE_URL . 'modules/protocols/nou_protocol.php?editar=' . (int)($_POST['id_protocol'] ?? 0)
        : BASE_URL . 'modules/protocols/nou_protocol.php';
    header('Location: ' . $url_back);
    exit;
}

try {
    $pdo = connectDB();

    if ($accio === 'crear') {
        $stmt = $pdo->prepare("
            INSERT INTO protocol_tractament (nom_protocol, descripcio, productes_json, condicions_ambientals)
            VALUES (:nom, :desc, :prod, :cond)
        ");
        $stmt->execute([
            ':nom'  => $nom_protocol,
            ':desc' => $descripcio,
            ':prod' => $productes_json,
            ':cond' => $condicions_ambientals,
        ]);
        set_flash('success', 'Protocol creat correctament.');

    } elseif ($accio === 'editar') {
        $id = (int)($_POST['id_protocol'] ?? 0);
        $stmt = $pdo->prepare("
            UPDATE protocol_tractament
            SET nom_protocol = :nom, descripcio = :desc,
                productes_json = :prod, condicions_ambientals = :cond
            WHERE id_protocol = :id
        ");
        $stmt->execute([
            ':nom'  => $nom_protocol,
            ':desc' => $descripcio,
            ':prod' => $productes_json,
            ':cond' => $condicions_ambientals,
            ':id'   => $id,
        ]);
        set_flash('success', 'Protocol actualitzat correctament.');
    }

    header('Location: ' . BASE_URL . 'modules/protocols/protocols.php');
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_protocol.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en desar el protocol.');
    header('Location: ' . BASE_URL . 'modules/protocols/protocols.php');
    exit;
}
