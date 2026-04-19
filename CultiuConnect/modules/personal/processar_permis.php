<?php
/**
 * modules/personal/processar_permis.php — Accions PRG per permisos.
 *
 * POST accio:
 *  - crear
 *  - aprovar   (admin/tecnic)
 *  - eliminar  (admin/tecnic)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();
$usuari = usuari_actiu();

function es_gestor(?array $usuari): bool
{
    $rol = strtolower($usuari['rol'] ?? 'operari');
    return in_array($rol, ['admin', 'tecnic', 'responsable'], true);
}

function usuari_treballador_id(PDO $pdo, int $usuari_id): ?int
{
    $stmt = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
    $stmt->execute([':id' => $usuari_id]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int)$id : null;
}

function guardar_pdf(array $file): ?string
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error en pujar el fitxer.');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Fitxer no vàlid.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 6 * 1024 * 1024) {
        throw new RuntimeException('El PDF ha de ser menor de 6MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('El fitxer ha de ser un PDF.');
    }

    $dir = __DIR__ . '/../../uploads/permisos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $nom = 'permis_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $dest = $dir . '/' . $nom;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('No s\'ha pogut desar el PDF.');
    }

    // Ruta web relativa
    return 'uploads/permisos/' . $nom;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
    exit;
}

$accio = $_POST['accio'] ?? '';

try {
    $pdo = connectDB();

    if ($accio === 'crear') {
        $id_treballador = sanitize_int($_POST['id_treballador'] ?? null);
        $tipus          = sanitize($_POST['tipus'] ?? '');
        $data_inici     = sanitize($_POST['data_inici'] ?? '');
        $data_fi        = sanitize($_POST['data_fi'] ?? '');
        $motiu          = sanitize($_POST['motiu'] ?? '');

        // Operari: només pot crear permisos per al seu propi treballador vinculat.
        if (!es_gestor($usuari)) {
            $id_self = usuari_treballador_id($pdo, (int)($usuari['id'] ?? 0));
            if (!$id_self) {
                set_flash('error', 'El teu usuari no està vinculat a cap treballador.');
                header('Location: ' . BASE_URL . 'modules/personal/nou_permis.php');
                exit;
            }
            $id_treballador = $id_self;
        }

        $tipus_valids = ['vacances','permis','baixa_malaltia','baixa_accident','curs','altres'];
        $errors = [];
        if (!$id_treballador) $errors[] = 'Cal indicar el treballador.';
        if (!in_array($tipus, $tipus_valids, true)) $errors[] = 'El tipus no és vàlid.';
        if (empty($data_inici) || !strtotime($data_inici)) $errors[] = 'La data d\'inici no és vàlida.';
        if (!empty($data_fi) && !strtotime($data_fi)) $errors[] = 'La data de fi no és vàlida.';
        if (!empty($data_fi) && strtotime($data_fi) < strtotime($data_inici)) $errors[] = 'La data de fi no pot ser anterior a l\'inici.';

        if (!empty($errors)) {
            set_flash('error', implode(' ', $errors));
            header('Location: ' . BASE_URL . 'modules/personal/nou_permis.php');
            exit;
        }

        $pdf_path = null;
        if (!empty($_FILES['document_pdf'] ?? null)) {
            $pdf_path = guardar_pdf($_FILES['document_pdf']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO permis_absencia
                (id_treballador, tipus, data_inici, data_fi, motiu, aprovat, document_pdf)
            VALUES
                (:id_treballador, :tipus, :data_inici, :data_fi, :motiu, 0, :document_pdf)
        ");
        $stmt->execute([
            ':id_treballador' => $id_treballador,
            ':tipus'          => $tipus,
            ':data_inici'     => $data_inici,
            ':data_fi'        => !empty($data_fi) ? $data_fi : null,
            ':motiu'          => $motiu ?: null,
            ':document_pdf'   => $pdf_path,
        ]);

        set_flash('success', 'Sol·licitud enviada. Queda pendent d\'aprovació.');
        header('Location: ' . BASE_URL . 'modules/personal/permisos.php?mode=pendents');
        exit;
    }

    if ($accio === 'aprovar') {
        if (!es_gestor($usuari)) {
            set_flash('error', 'No tens permisos per aprovar sol·licituds.');
            header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
            exit;
        }
        $id_permis = sanitize_int($_POST['id_permis'] ?? null);
        if (!$id_permis) {
            set_flash('error', 'Sol·licitud no identificada.');
            header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
            exit;
        }
        $stmt = $pdo->prepare("UPDATE permis_absencia SET aprovat = 1 WHERE id_permis = :id");
        $stmt->execute([':id' => $id_permis]);
        set_flash('success', 'Sol·licitud aprovada. Ja apareix al calendari.');
        header('Location: ' . BASE_URL . 'modules/personal/permisos.php?mode=pendents');
        exit;
    }

    if ($accio === 'eliminar') {
        if (!es_gestor($usuari)) {
            set_flash('error', 'No tens permisos per eliminar sol·licituds.');
            header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
            exit;
        }
        $id_permis = sanitize_int($_POST['id_permis'] ?? null);
        if (!$id_permis) {
            set_flash('error', 'Sol·licitud no identificada.');
            header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM permis_absencia WHERE id_permis = :id");
        $stmt->execute([':id' => $id_permis]);
        set_flash('success', 'Sol·licitud eliminada.');
        header('Location: ' . BASE_URL . 'modules/personal/permisos.php?mode=tots');
        exit;
    }

    set_flash('error', 'Acció no reconeguda.');
    header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
    exit;

} catch (RuntimeException $e) {
    error_log('[CultiuConnect] processar_permis.php runtime: ' . $e->getMessage());
    set_flash('error', 'Sol·licitud no vàlida o document incorrecte.');
    header('Location: ' . BASE_URL . 'modules/personal/nou_permis.php');
    exit;
} catch (Exception $e) {
    error_log('[CultiuConnect] processar_permis.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en processar la sol·licitud.');
    header('Location: ' . BASE_URL . 'modules/personal/permisos.php');
    exit;
}

