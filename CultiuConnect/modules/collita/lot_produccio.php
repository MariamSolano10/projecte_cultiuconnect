<?php
/**
 * modules/collita/lot_produccio.php — Llistat de lots de producció per a envasat i traçabilitat.
 *
 * Filtre opcional per collita: ?collita_id=N
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$lots     = [];
$error_db = null;
$filtre_collita = sanitize_int($_GET['collita_id'] ?? null);

try {
    $pdo = connectDB();

    $on     = $filtre_collita ? 'WHERE lp.id_collita = :id_collita' : '';
    $params = $filtre_collita ? [':id_collita' => $filtre_collita] : [];

    $stmt = $pdo->prepare("
        SELECT
            lp.id_lot,
            lp.identificador,
            lp.codi_qr,
            lp.data_processat,
            lp.pes_kg,
            lp.qualitat,
            lp.desti,
            c.id_collita,
            s.nom        AS nom_sector,
            v.nom_varietat
        FROM lot_produccio lp
        JOIN collita    c  ON lp.id_collita  = c.id_collita
        JOIN plantacio pl  ON c.id_plantacio = pl.id_plantacio
        JOIN sector     s  ON pl.id_sector   = s.id_sector
        LEFT JOIN varietat v ON pl.id_varietat = v.id_varietat
        {$on}
        ORDER BY lp.data_processat DESC, lp.id_lot DESC
    ");
    $stmt->execute($params);
    $lots = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] lot_produccio.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els lots de producció.';
}

$titol_pagina  = 'Lots de Producció';
$pagina_activa = 'collita';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-lots">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-boxes-packing" aria-hidden="true"></i>
            Lots de Producció
            <?php if ($filtre_collita): ?>
                <span class="subtitol-filtre">— Collita #<?= $filtre_collita ?></span>
            <?php endif; ?>
        </h1>
        <p class="descripcio-seccio">
            Traçabilitat completa de cada lot d'envasat: codi, pes, destí i estat.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/collita/nou_lot_produccio.php<?= $filtre_collita ? ('?collita_id=' . (int)$filtre_collita) : '' ?>" class="boto-principal">
            <i class="fas fa-plus"></i> Nou Lot
        </a>
        <a href="<?= BASE_URL ?>modules/collita/collita.php" class="boto-secundari">
            <i class="fas fa-arrow-left"></i> Tornar a Collites
        </a>
        <?php if ($filtre_collita): ?>
            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Netejar filtre
            </a>
        <?php endif; ?>
    </div>

    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-lots"
               placeholder="Cerca per codi lot, sector o destí..."
               class="input-cerca"
               aria-label="Cercar lots">
    </div>

    <table class="taula-simple" id="taula-lots">
        <thead>
            <tr>
                <th>Lot</th>
                <th>Data Processat</th>
                <th>Sector / Varietat</th>
                <th>Pes Net</th>
                <th>Destí</th>
                <th>Qualitat</th>
                <th>Traçabilitat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lots)): ?>
                <tr>
                    <td colspan="7" class="sense-dades">
                        <i class="fas fa-info-circle"></i>
                        No hi ha lots registrats.
                        <a href="<?= BASE_URL ?>modules/collita/nou_lot_produccio.php<?= $filtre_collita ? ('?collita_id=' . (int)$filtre_collita) : '' ?>">Crea'n un.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lots as $l): ?>
                    <tr>
                        <td data-cerca>
                            <strong><?= e($l['identificador']) ?></strong>
                            <div class="text-suau">#<?= (int)$l['id_lot'] ?></div>
                        </td>
                        <td><?= format_data($l['data_processat'], curta: true) ?></td>
                        <td data-cerca>
                            <?= e($l['nom_sector']) ?>
                            <?= $l['nom_varietat'] ? '<br><span class="text-suau">' . e($l['nom_varietat']) . '</span>' : '' ?>
                        </td>
                        <td>
                            <?= $l['pes_kg'] !== null ? format_kg((float)$l['pes_kg']) : '—' ?>
                        </td>
                        <td data-cerca><?= e($l['desti'] ?? '—') ?></td>
                        <td data-cerca>
                            <span class="badge badge--blau"><?= e($l['qualitat'] ?? '—') ?></span>
                        </td>
                        <td class="cel-accions">
                            <a class="btn-accio btn-accio--veure"
                               href="<?= BASE_URL ?>modules/collita/tracabilitat_lot.php?id_lot=<?= (int)$l['id_lot'] ?>"
                               title="Veure / generar QR">
                                <i class="fas fa-qrcode" aria-hidden="true"></i>
                            </a>
                            <?php if (!empty($l['codi_qr'])): ?>
                                <a class="btn-accio btn-accio--veure"
                                   href="<?= BASE_URL ?>tracabilitat.php?c=<?= urlencode($l['codi_qr']) ?>"
                                   target="_blank" rel="noopener"
                                   title="Obrir pàgina pública">
                                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-suau" title="Encara no s'ha generat cap QR">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
