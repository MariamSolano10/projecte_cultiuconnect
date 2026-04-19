<?php
/**
 * modules/jornades/processar_jornada.php — Processa totes les accions del mòdul de jornades.
 *
 * Accions POST:
 *  - crear     → INSERT nova jornada
 *  - editar    → UPDATE jornada existent
 *  - validar   → Marca una jornada com a validada
 *  - eliminar  → DELETE jornada
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/jornades/jornades.php');
    exit;
}

$usuari = usuari_actiu();

function es_gestor(?array $usuari): bool
{
    $rol = strtolower((string)($usuari['rol'] ?? 'operari'));
    return in_array($rol, ['admin', 'tecnic', 'responsable'], true);
}

function usuari_treballador_id(PDO $pdo, int $usuari_id): ?int
{
    $stmt = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
    $stmt->execute([':id' => $usuari_id]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int)$id : null;
}

$accio = $_POST['accio'] ?? '';

// Paràmetres per reconstruir la URL de retorn amb els filtres actius
$mes             = preg_match('/^\d{4}-\d{2}$/', $_POST['mes'] ?? '') ? $_POST['mes'] : date('Y-m');
$id_treballador  = is_numeric($_POST['id_treballador'] ?? '') ? (int)$_POST['id_treballador'] : 0;
$url_retorn      = BASE_URL . 'modules/jornades/jornades.php?mes=' . urlencode($mes)
                 . ($id_treballador ? '&id_treballador=' . $id_treballador : '');

try {
    $pdo = connectDB();
    $gestor = es_gestor($usuari);
    $id_self = (!$gestor && $usuari) ? usuari_treballador_id($pdo, (int)($usuari['id'] ?? 0)) : null;

    // ----------------------------------------------------------------
    // CREAR
    // ----------------------------------------------------------------
    if ($accio === 'crear') {

        $id_treballador_form = !empty($_POST['id_treballador']) && is_numeric($_POST['id_treballador'])
            ? (int)$_POST['id_treballador'] : null;
        $id_tasca       = !empty($_POST['id_tasca'])       ? (int)$_POST['id_tasca']       : null;
        $data_hora_inici = trim($_POST['data_hora_inici'] ?? '');
        $data_hora_fi    = trim($_POST['data_hora_fi']    ?? '') ?: null;
        $pausa_minuts    = is_numeric($_POST['pausa_minuts'] ?? '') ? (int)$_POST['pausa_minuts'] : 0;
        $ubicacio        = trim($_POST['ubicacio']    ?? '') ?: null;
        $incidencies     = trim($_POST['incidencies'] ?? '') ?: null;

        if (!$id_treballador_form || !$data_hora_inici) {
            set_flash('error', 'El treballador i l\'hora d\'inici són obligatoris.');
            header('Location: ' . BASE_URL . 'modules/jornades/nova_jornada.php');
            exit;
        }

        if (!$gestor) {
            if (!$id_self) {
                set_flash('error', 'El teu usuari no està vinculat a cap treballador.');
                header('Location: ' . BASE_URL . 'modules/jornades/jornades.php');
                exit;
            }
            $id_treballador_form = $id_self; // ignora manipulació de POST
        }

        if ($data_hora_fi && $data_hora_fi <= $data_hora_inici) {
            set_flash('error', 'L\'hora de fi no pot ser anterior o igual a l\'hora d\'inici.');
            header('Location: ' . BASE_URL . 'modules/jornades/nova_jornada.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO jornada
                (id_treballador, id_tasca, data_hora_inici, data_hora_fi, pausa_minuts, ubicacio, incidencies, validada)
            VALUES
                (:id_treballador, :id_tasca, :inici, :fi, :pausa, :ubicacio, :incidencies, 0)
        ");
        $stmt->execute([
            ':id_treballador' => $id_treballador_form,
            ':id_tasca'       => $id_tasca,
            ':inici'          => $data_hora_inici,
            ':fi'             => $data_hora_fi,
            ':pausa'          => $pausa_minuts,
            ':ubicacio'       => $ubicacio,
            ':incidencies'    => $incidencies,
        ]);

        set_flash('success', 'Jornada registrada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------------
    if ($accio === 'editar') {

        $id_jornada = !empty($_POST['id_jornada']) ? (int)$_POST['id_jornada'] : 0;
        if (!$id_jornada) {
            set_flash('error', 'Jornada no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $id_treballador_form = !empty($_POST['id_treballador']) ? (int)$_POST['id_treballador'] : null;
        $id_tasca            = !empty($_POST['id_tasca'])       ? (int)$_POST['id_tasca']       : null;
        $data_hora_inici     = trim($_POST['data_hora_inici'] ?? '');
        $data_hora_fi        = trim($_POST['data_hora_fi']    ?? '') ?: null;
        $pausa_minuts        = is_numeric($_POST['pausa_minuts'] ?? '') ? (int)$_POST['pausa_minuts'] : 0;
        $ubicacio            = trim($_POST['ubicacio']    ?? '') ?: null;
        $incidencies         = trim($_POST['incidencies'] ?? '') ?: null;
        $validada            = isset($_POST['validada']) ? 1 : 0;

        if (!$gestor) {
            if (!$id_self) {
                set_flash('error', 'El teu usuari no està vinculat a cap treballador.');
                header('Location: ' . BASE_URL . 'modules/jornades/jornades.php');
                exit;
            }
            // Operari: no pot canviar treballador ni validar jornades
            $id_treballador_form = $id_self;
            $validada = 0;
        }

        if (!$id_treballador_form || !$data_hora_inici) {
            set_flash('error', 'El treballador i l\'hora d\'inici són obligatoris.');
            header('Location: ' . BASE_URL . 'modules/jornades/nova_jornada.php?editar=' . $id_jornada);
            exit;
        }

        if ($data_hora_fi && $data_hora_fi <= $data_hora_inici) {
            set_flash('error', 'L\'hora de fi no pot ser anterior o igual a l\'hora d\'inici.');
            header('Location: ' . BASE_URL . 'modules/jornades/nova_jornada.php?editar=' . $id_jornada);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE jornada SET
                id_treballador = :id_treballador,
                id_tasca       = :id_tasca,
                data_hora_inici = :inici,
                data_hora_fi    = :fi,
                pausa_minuts    = :pausa,
                ubicacio        = :ubicacio,
                incidencies     = :incidencies,
                validada        = :validada
            WHERE id_jornada = :id_jornada
        ");
        $stmt->execute([
            ':id_treballador' => $id_treballador_form,
            ':id_tasca'       => $id_tasca,
            ':inici'          => $data_hora_inici,
            ':fi'             => $data_hora_fi,
            ':pausa'          => $pausa_minuts,
            ':ubicacio'       => $ubicacio,
            ':incidencies'    => $incidencies,
            ':validada'       => $validada,
            ':id_jornada'     => $id_jornada,
        ]);

        set_flash('success', 'Jornada actualitzada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // VALIDAR
    // ----------------------------------------------------------------
    if ($accio === 'validar') {
        if (!$gestor) {
            set_flash('error', 'No tens permisos per validar jornades.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $id_jornada = !empty($_POST['id_jornada']) ? (int)$_POST['id_jornada'] : 0;
        if (!$id_jornada) {
            set_flash('error', 'Jornada no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE jornada SET validada = 1 WHERE id_jornada = :id");
        $stmt->execute([':id' => $id_jornada]);

        set_flash('success', 'Jornada validada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // ELIMINAR
    // ----------------------------------------------------------------
    if ($accio === 'eliminar') {
        if (!$gestor) {
            set_flash('error', 'No tens permisos per eliminar jornades.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $id_jornada = !empty($_POST['id_jornada']) ? (int)$_POST['id_jornada'] : 0;
        if (!$id_jornada) {
            set_flash('error', 'Jornada no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        // Comprovem que la jornada no estigui validada (protecció extra)
        $stmt = $pdo->prepare("SELECT validada FROM jornada WHERE id_jornada = :id");
        $stmt->execute([':id' => $id_jornada]);
        $row = $stmt->fetch();

        if ($row && $row['validada']) {
            set_flash('error', 'No es pot eliminar una jornada ja validada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM jornada WHERE id_jornada = :id");
        $stmt->execute([':id' => $id_jornada]);

        set_flash('success', 'Jornada eliminada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // Acció desconeguda
    set_flash('error', 'Acció no reconeguda.');
    header('Location: ' . $url_retorn);
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_jornada.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en processar l\'acció.');
    header('Location: ' . $url_retorn);
    exit;
}
