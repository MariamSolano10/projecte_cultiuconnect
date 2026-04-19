<?php
/**
 * modules/collita/tracabilitat_lot.php — Gestió de traçabilitat i QR per un lot de producció.
 *
 * - Genera (si cal) un token públic i el desa a `lot_produccio.codi_qr`
 * - Mostra l'enllaç públic i un QR per imprimir/compartir
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_lot = sanitize_int($_GET['id_lot'] ?? null);
if (!$id_lot) {
    set_flash('error', 'Lot no vàlid.');
    header('Location: ' . BASE_URL . 'modules/collita/lot_produccio.php');
    exit;
}

function genera_token_public(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

$error_db = null;
$lot = null;
$url_publica = null;

try {
    $pdo = connectDB();

    // Carregar dades del lot
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
            c.data_inici AS collita_inici,
            c.data_fi    AS collita_fi,
            s.nom        AS nom_sector,
            v.nom_varietat
        FROM lot_produccio lp
        JOIN collita c      ON c.id_collita = lp.id_collita
        JOIN plantacio pl   ON pl.id_plantacio = c.id_plantacio
        JOIN sector s       ON s.id_sector = pl.id_sector
        LEFT JOIN varietat v ON v.id_varietat = pl.id_varietat
        WHERE lp.id_lot = :id_lot
        LIMIT 1
    ");
    $stmt->execute([':id_lot' => $id_lot]);
    $lot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lot) {
        throw new RuntimeException('Lot inexistent.');
    }

    // Generar token si no existeix
    if (empty($lot['codi_qr'])) {
        $token = genera_token_public(16);
        $upd = $pdo->prepare("UPDATE lot_produccio SET codi_qr = :t WHERE id_lot = :id");
        $upd->execute([':t' => $token, ':id' => $id_lot]);
        $lot['codi_qr'] = $token;
    }

    $url_publica = BASE_URL . 'tracabilitat.php?c=' . urlencode((string)$lot['codi_qr']);

} catch (Exception $e) {
    error_log('[CultiuConnect] tracabilitat_lot.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar la traçabilitat del lot.';
}

$titol_pagina  = 'Traçabilitat del Lot';
$pagina_activa = 'collita';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-qrcode" aria-hidden="true"></i>
            Traçabilitat i QR del Lot
        </h1>
        <p class="descripcio-seccio">
            Aquest QR porta a una pàgina pública perquè el client/consumidor pugui consultar l'origen del producte.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
        <div class="botons-accions">
            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php" class="boto-secundari">
                <i class="fas fa-arrow-left"></i> Tornar
            </a>
        </div>
    <?php else: ?>

        <div class="formulari-card stack-separat">

            <div class="fila-entre">
                <div>
                    <div class="text-suau">Lot</div>
                    <div class="targeta-compacta__titol">
                        <?= e($lot['identificador']) ?> <span class="text-suau">(#<?= (int)$lot['id_lot'] ?>)</span>
                    </div>
                    <div class="text-suau">
                        Sector: <strong><?= e($lot['nom_sector']) ?></strong>
                        <?= $lot['nom_varietat'] ? ' · Varietat: <strong>' . e($lot['nom_varietat']) . '</strong>' : '' ?>
                    </div>
                    <div class="text-suau">
                        Processat: <?= format_data($lot['data_processat'], curta: true) ?>
                        · Pes: <?= $lot['pes_kg'] !== null ? format_kg((float)$lot['pes_kg']) : '—' ?>
                        · Qualitat: <strong><?= e($lot['qualitat'] ?? '—') ?></strong>
                    </div>
                </div>

                <div class="botons-accions mt-0">
                    <a class="boto-secundari" href="<?= BASE_URL ?>modules/collita/lot_produccio.php">
                        <i class="fas fa-arrow-left"></i> Tornar a lots
                    </a>
                    <a class="boto-principal" href="<?= e($url_publica) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-arrow-up-right-from-square"></i> Obrir traça pública
                    </a>
                </div>
            </div>

            <div class="grid-aside">
                <div class="card targeta-compacta">
                    <div class="targeta-compacta__titol">QR del lot</div>
                    <img
                        alt="Codi QR de traçabilitat"
                        width="220" height="220"
                        class="qr-imatge"
                        src="<?= BASE_URL ?>modules/qualitat/qrcode.php?size=6&margin=2&level=M&mode=payload&c=<?= urlencode((string)$lot['codi_qr']) ?>">
                    <div class="text-suau">
                        Escaneja per veure la traça.
                    </div>
                    <div class="accions-inline">
                        <a class="boto-neutre"
                           href="<?= BASE_URL ?>modules/qualitat/qrcode.php?size=6&margin=2&level=M&mode=payload&c=<?= urlencode((string)$lot['codi_qr']) ?>"
                           target="_blank" rel="noopener">
                            QR dades (payload)
                        </a>
                        <a class="boto-neutre"
                           href="<?= BASE_URL ?>modules/qualitat/qrcode.php?size=6&margin=2&level=M&mode=url&c=<?= urlencode((string)$lot['codi_qr']) ?>"
                           target="_blank" rel="noopener">
                            QR enllaç (URL)
                        </a>
                    </div>
                    <button type="button" class="boto-neutre w-100"
                            onclick="window.print()">
                        <i class="fas fa-print" aria-hidden="true"></i> Imprimir
                    </button>
                </div>

                <div>
                    <label class="form-label">Enllaç públic</label>
                    <div class="fila-entre">
                        <input id="urlPublica" class="form-input input-flex" type="text" readonly value="<?= e($url_publica) ?>">
                        <button type="button" class="boto-secundari" onclick="copiarEnllac()">
                            <i class="fas fa-copy" aria-hidden="true"></i> Copiar
                        </button>
                    </div>

                    <div class="text-suau">
                        Aquest enllaç és públic. Si vols “revocar-lo”, hauríem de regenerar el token (opció a implementar).
                    </div>
                </div>
            </div>

        </div>

        <script>
        function copiarEnllac() {
            const el = document.getElementById('urlPublica');
            if (!el) return;
            el.select();
            el.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
            } catch (e) {}
        }
        </script>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
