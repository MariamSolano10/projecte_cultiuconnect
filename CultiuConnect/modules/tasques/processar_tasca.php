<?php
/**
 * modules/tasques/processar_tasca.php — Processa el formulari de nova_tasca.php.
 *
 * Accepta POST. Crea o actualitza una tasca a la BD i redirigeix.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/tasques/tasques.php');
    exit;
}

$id_tasca  = !empty($_POST['id_tasca']) && is_numeric($_POST['id_tasca'])
    ? (int)$_POST['id_tasca']
    : null;

$es_edicio = $id_tasca !== null;

// --- Recollida i neteja de dades ---
$tipus                       = in_array($_POST['tipus'] ?? '', ['poda','aclarida','tractament','collita','fertilitzacio','reg','manteniment','altres'])
                                ? $_POST['tipus']
                                : null;
$id_sector                   = !empty($_POST['id_sector']) ? (int)$_POST['id_sector'] : null;
$descripcio                  = trim($_POST['descripcio'] ?? '') ?: null;
$data_inici_prevista         = trim($_POST['data_inici_prevista'] ?? '');
$data_fi_prevista            = trim($_POST['data_fi_prevista'] ?? '') ?: null;
$duracio_estimada_h          = is_numeric($_POST['duracio_estimada_h'] ?? '') ? (float)$_POST['duracio_estimada_h'] : null;
$num_treballadors_necessaris = is_numeric($_POST['num_treballadors_necessaris'] ?? '') ? (int)$_POST['num_treballadors_necessaris'] : null;
$qualificacions_necessaries  = trim($_POST['qualificacions_necessaries'] ?? '') ?: null;
$equipament_necessari        = trim($_POST['equipament_necessari'] ?? '') ?: null;
$instruccions                = trim($_POST['instruccions'] ?? '') ?: null;
$tasca_precedent             = !empty($_POST['tasca_precedent']) ? (int)$_POST['tasca_precedent'] : null;
$estat                       = in_array($_POST['estat'] ?? '', ['pendent','en_proces','completada','cancel·lada'])
                                ? $_POST['estat']
                                : 'pendent';

// --- Validació bàsica ---
if (!$tipus || !$data_inici_prevista) {
    set_flash('error', 'El tipus i la data d\'inici prevista són obligatoris.');
    $url_back = $es_edicio
        ? BASE_URL . 'modules/tasques/nova_tasca.php?editar=' . $id_tasca
        : BASE_URL . 'modules/tasques/nova_tasca.php';
    header('Location: ' . $url_back);
    exit;
}

if ($data_fi_prevista && $data_fi_prevista < $data_inici_prevista) {
    set_flash('error', 'La data de fi no pot ser anterior a la data d\'inici.');
    $url_back = $es_edicio
        ? BASE_URL . 'modules/tasques/nova_tasca.php?editar=' . $id_tasca
        : BASE_URL . 'modules/tasques/nova_tasca.php';
    header('Location: ' . $url_back);
    exit;
}

try {
    $pdo = connectDB();

    if ($es_edicio) {
        $sql = "
            UPDATE tasca SET
                id_sector                   = :id_sector,
                tipus                       = :tipus,
                descripcio                  = :descripcio,
                data_inici_prevista         = :data_inici_prevista,
                data_fi_prevista            = :data_fi_prevista,
                duracio_estimada_h          = :duracio_estimada_h,
                num_treballadors_necessaris = :num_treballadors_necessaris,
                qualificacions_necessaries  = :qualificacions_necessaries,
                equipament_necessari        = :equipament_necessari,
                instruccions                = :instruccions,
                tasca_precedent             = :tasca_precedent,
                estat                       = :estat
            WHERE id_tasca = :id_tasca
        ";
        $params = [
            ':id_tasca'                    => $id_tasca,
            ':id_sector'                   => $id_sector,
            ':tipus'                       => $tipus,
            ':descripcio'                  => $descripcio,
            ':data_inici_prevista'         => $data_inici_prevista,
            ':data_fi_prevista'            => $data_fi_prevista,
            ':duracio_estimada_h'          => $duracio_estimada_h,
            ':num_treballadors_necessaris' => $num_treballadors_necessaris,
            ':qualificacions_necessaries'  => $qualificacions_necessaries,
            ':equipament_necessari'        => $equipament_necessari,
            ':instruccions'                => $instruccions,
            ':tasca_precedent'             => $tasca_precedent,
            ':estat'                       => $estat,
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        set_flash('success', 'Tasca actualitzada correctament.');
        header('Location: ' . BASE_URL . 'modules/tasques/detall_tasca.php?id=' . $id_tasca);

    } else {
        $sql = "
            INSERT INTO tasca (
                id_sector, tipus, descripcio,
                data_inici_prevista, data_fi_prevista,
                duracio_estimada_h, num_treballadors_necessaris,
                qualificacions_necessaries, equipament_necessari,
                instruccions, tasca_precedent, estat
            ) VALUES (
                :id_sector, :tipus, :descripcio,
                :data_inici_prevista, :data_fi_prevista,
                :duracio_estimada_h, :num_treballadors_necessaris,
                :qualificacions_necessaries, :equipament_necessari,
                :instruccions, :tasca_precedent, 'pendent'
            )
        ";
        $params = [
            ':id_sector'                   => $id_sector,
            ':tipus'                       => $tipus,
            ':descripcio'                  => $descripcio,
            ':data_inici_prevista'         => $data_inici_prevista,
            ':data_fi_prevista'            => $data_fi_prevista,
            ':duracio_estimada_h'          => $duracio_estimada_h,
            ':num_treballadors_necessaris' => $num_treballadors_necessaris,
            ':qualificacions_necessaries'  => $qualificacions_necessaries,
            ':equipament_necessari'        => $equipament_necessari,
            ':instruccions'                => $instruccions,
            ':tasca_precedent'             => $tasca_precedent,
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $nou_id = (int)$pdo->lastInsertId();

        set_flash('success', 'Tasca creada correctament.');
        header('Location: ' . BASE_URL . 'modules/tasques/detall_tasca.php?id=' . $nou_id);
    }
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_tasca.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en desar la tasca.');
    $url_back = $es_edicio
        ? BASE_URL . 'modules/tasques/nova_tasca.php?editar=' . $id_tasca
        : BASE_URL . 'modules/tasques/nova_tasca.php';
    header('Location: ' . $url_back);
    exit;
}
