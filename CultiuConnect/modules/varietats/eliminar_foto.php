<?php
/**
 * modules/varietats/eliminar_foto.php
 *
 * Processador per eliminar una foto de varietat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_foto = sanitize_int($_GET['id'] ?? null);
$id_varietat = sanitize_int($_GET['id_varietat'] ?? null);

if (!$id_foto || !$id_varietat) {
    set_flash('error', 'Paràmetres invàlids.');
    header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
    exit;
}

try {
    $pdo = connectDB();

    // Verificar que la foto pertany a la varietat
    $stmt = $pdo->prepare("
        SELECT fv.*, v.nom_varietat
        FROM foto_varietat fv
        JOIN varietat v ON fv.id_varietat = v.id_varietat
        WHERE fv.id_foto = ? AND fv.id_varietat = ?
    ");
    $stmt->execute([$id_foto, $id_varietat]);
    $foto = $stmt->fetch();

    if (!$foto) {
        set_flash('error', 'La foto no existeix o no pertany a aquesta varietat.');
        header('Location: ' . BASE_URL . 'modules/varietats/upload_fotos.php?id=' . $id_varietat);
        exit;
    }

    // Eliminar el fitxer físic
    $ruta_fitxer = __DIR__ . '/../../uploads/varietats/' . basename($foto['url_foto']);
    if (file_exists($ruta_fitxer)) {
        if (!unlink($ruta_fitxer)) {
            error_log('[CultiuConnect] No s\'ha pogut eliminar el fitxer: ' . $ruta_fitxer);
        }
    }

    // Eliminar el registre de la BD
    $stmt = $pdo->prepare("
        DELETE FROM foto_varietat 
        WHERE id_foto = ? AND id_varietat = ?
    ");
    $stmt->execute([$id_foto, $id_varietat]);

    // Registrar l'acció
    registrar_accio(
        'ELIMINAR_FOTO_VARIETAT',
        "Eliminada foto de la varietat {$foto['nom_varietat']}",
        'foto_varietat',
        $id_foto
    );

    set_flash('success', 'Foto eliminada correctament.');
    header('Location: ' . BASE_URL . 'modules/varietats/upload_fotos.php?id=' . $id_varietat);
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] eliminar_foto.php: ' . $e->getMessage());
    set_flash('error', 'No s\'ha pogut eliminar la foto.');
    header('Location: ' . BASE_URL . 'modules/varietats/upload_fotos.php?id=' . $id_varietat);
    exit;
}
