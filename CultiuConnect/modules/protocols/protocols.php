<?php
/**
 * modules/protocols/protocols.php — Gestió de protocols de tractament fitosanitari.
 *
 * Llista, crea, edita i elimina protocols reutilitzables
 * que s'associen als tractaments programats i aplicacions.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Protocols de Tractament';
$pagina_activa = 'protocols';

$protocols = [];
$error_db  = null;

// ── Accions inline (eliminar) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accio'] ?? '') === 'eliminar') {
    $id = !empty($_POST['id_protocol']) ? (int)$_POST['id_protocol'] : 0;
    try {
        $pdo = connectDB();
        // Comprovem si té tractaments programats associats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tractament_programat WHERE id_protocol = :id");
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            set_flash('error', 'No es pot eliminar: el protocol té tractaments programats associats.');
        } else {
            $pdo->prepare("DELETE FROM protocol_tractament WHERE id_protocol = :id")
                ->execute([':id' => $id]);
            set_flash('success', 'Protocol eliminat correctament.');
        }
    } catch (Exception $e) {
        error_log('[CultiuConnect] protocols.php eliminar: ' . $e->getMessage());
        set_flash('error', 'Error en eliminar el protocol.');
    }
    header('Location: ' . BASE_URL . 'modules/protocols/protocols.php');
    exit;
}

try {
    $pdo = connectDB();

    $protocols = $pdo->query("
        SELECT
            pt.id_protocol,
            pt.nom_protocol,
            pt.descripcio,
            pt.condicions_ambientals,
            pt.productes_json,
            COUNT(tp.id_programat) AS num_programats
        FROM protocol_tractament pt
        LEFT JOIN tractament_programat tp ON tp.id_protocol = pt.id_protocol
        GROUP BY pt.id_protocol
        ORDER BY pt.nom_protocol
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] protocols.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els protocols.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-protocols">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-file-medical" aria-hidden="true"></i>
            Protocols de Tractament
        </h1>
        <p class="descripcio-seccio">
            Plantilles reutilitzables de tractaments fitosanitaris, fertilitzacions i correccions.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/protocols/nou_protocol.php" class="boto-principal">
            <i class="fas fa-plus"></i> Nou Protocol
        </a>
    </div>

    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-protocols"
               placeholder="Cerca per nom o descripció..."
               class="input-cerca"
               aria-label="Cercar protocols">
    </div>

    <table class="taula-simple" id="taula-protocols">
        <thead>
            <tr>
                <th>Nom del protocol</th>
                <th>Descripció</th>
                <th>Condicions ambientals</th>
                <th>Productes</th>
                <th>Tractaments associats</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($protocols)): ?>
                <tr>
                    <td colspan="6" class="sense-dades">
                        <i class="fas fa-info-circle"></i>
                        Encara no hi ha cap protocol creat.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($protocols as $p):
                    $productes = [];
                    if ($p['productes_json']) {
                        $decoded = json_decode($p['productes_json'], true);
                        if (is_array($decoded)) $productes = $decoded;
                    }
                ?>
                <tr>
                    <td data-cerca>
                        <strong><?= e($p['nom_protocol']) ?></strong>
                    </td>
                    <td data-cerca>
                        <?= $p['descripcio']
                            ? e(mb_substr($p['descripcio'], 0, 80)) . (mb_strlen($p['descripcio']) > 80 ? '…' : '')
                            : '—' ?>
                    </td>
                    <td>
                        <?= $p['condicions_ambientals']
                            ? e(mb_substr($p['condicions_ambientals'], 0, 60)) . (mb_strlen($p['condicions_ambientals']) > 60 ? '…' : '')
                            : '—' ?>
                    </td>
                    <td>
                        <?php if (!empty($productes)): ?>
                            <?php foreach ($productes as $prod): ?>
                                <span class="badge badge--blau badge--bottom-xs">
                                    <?= e($prod['nom'] ?? $prod) ?>
                                    <?= isset($prod['dosi']) ? '(' . e($prod['dosi']) . ')' : '' ?>
                                </span><br>
                            <?php endforeach; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= (int)$p['num_programats'] > 0 ? 'badge--verd' : 'badge--gris' ?>">
                            <?= (int)$p['num_programats'] ?>
                        </span>
                    </td>
                    <td class="cel-accions">
                        <a href="<?= BASE_URL ?>modules/protocols/nou_protocol.php?editar=<?= (int)$p['id_protocol'] ?>"
                           title="Editar protocol"
                           class="btn-accio btn-accio--editar">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" class="form-inline-display"
                              onsubmit="return confirm('Eliminar el protocol «<?= e(addslashes($p['nom_protocol'])) ?>»?')">
                            <input type="hidden" name="accio"       value="eliminar">
                            <input type="hidden" name="id_protocol" value="<?= (int)$p['id_protocol'] ?>">
                            <button type="submit"
                                    class="btn-accio btn-accio--eliminar"
                                    title="Eliminar protocol">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
