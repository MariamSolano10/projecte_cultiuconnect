<?php
/**
 * modules/collita/processar_nou_lot.php — Processador del formulari nou_lot.php.
 *
 * NOTA: La lògica d'inserció s'ha integrat directament a nou_lot.php (GET/POST unificat).
 * Aquest fitxer es manté per compatibilitat amb possibles crides externes,
 * però redirigeix al formulari unificat.
 *
 * Si reps POST aquí directament, processa i redirigeix (PRG complet).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
    exit;
}

// Deleguem al nou_lot.php unificat renviant el POST
// En producció seria millor unificar tot a nou_lot.php
// però si el formulari apunta aquí, tractem el POST aquí

$id_producte = sanitize_int($_POST['id_producte']           ?? null);
$num_lot     = sanitize($_POST['num_lot']                   ?? '');
$quantitat   = sanitize_decimal($_POST['quantitat_disponible'] ?? null);
$unitat      = sanitize($_POST['unitat_mesura']             ?? '');
$caducitat   = sanitize($_POST['data_caducitat']            ?? '');
$ubicacio    = sanitize($_POST['ubicacio_magatzem']         ?? '');
$data_compra = sanitize($_POST['data_compra']               ?? '');
$proveidor   = sanitize($_POST['proveidor']                 ?? '');
$preu        = sanitize_decimal($_POST['preu_adquisicio']   ?? null);

$errors = [];
if (!$id_producte)                          $errors[] = 'Cal seleccionar un producte.';
if (empty($num_lot))                        $errors[] = 'El codi de lot és obligatori.';
if ($quantitat === null || $quantitat <= 0) $errors[] = 'La quantitat ha de ser positiva.';
if (!in_array($unitat, ['Kg', 'L', 'Unitat'])) $errors[] = 'La unitat de mesura no és vàlida.';
if (empty($data_compra))                    $errors[] = 'La data de compra és obligatòria.';

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
    exit;
}

try {
    $pdo = connectDB();

    // Verificar que el producte existeix
    $check = $pdo->prepare("SELECT nom_comercial FROM producte_quimic WHERE id_producte = ?");
    $check->execute([$id_producte]);
    $producte = $check->fetch();

    if (!$producte) {
        throw new RuntimeException('El producte seleccionat no existeix.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO inventari_estoc
            (id_producte, num_lot, quantitat_disponible, unitat_mesura,
             data_caducitat, ubicacio_magatzem, data_compra, proveidor, preu_adquisicio)
        VALUES
            (:id_producte, :num_lot, :quantitat, :unitat,
             :caducitat, :ubicacio, :data_compra, :proveidor, :preu)
    ");
    $stmt->execute([
        ':id_producte' => $id_producte,
        ':num_lot'     => $num_lot,
        ':quantitat'   => $quantitat,
        ':unitat'      => $unitat,
        ':caducitat'   => !empty($caducitat) ? $caducitat : null,
        ':ubicacio'    => !empty($ubicacio)  ? $ubicacio  : null,
        ':data_compra' => $data_compra,
        ':proveidor'   => !empty($proveidor) ? $proveidor : null,
        ':preu'        => $preu,
    ]);

    set_flash('success', 'Lot del producte «' . e($producte['nom_comercial']) . '» registrat correctament.');
    header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
    exit;

} catch (RuntimeException $e) {
    error_log('[CultiuConnect] processar_nou_lot.php runtime: ' . $e->getMessage());
    set_flash('error', 'No s\'ha pogut registrar el lot.');
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
    exit;
} catch (Exception $e) {
    if ($e->getCode() === '23000') {
        set_flash('error', 'Ja existeix un lot amb el codi "' . e($num_lot) . '" per a aquest producte.');
    } else {
        error_log('[CultiuConnect] processar_nou_lot.php: ' . $e->getMessage());
        set_flash('error', 'Error intern en registrar el lot.');
    }
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
    exit;
}
