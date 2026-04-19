<?php
/**
 * modules/proveidors/veure_proveidor.php
 *
 * Vista detallada d'un proveïdor amb la seva informació completa
 * i opcionalment les compres realitzades.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_proveidor = sanitize_int($_GET['id'] ?? null);

if (!$id_proveidor) {
    set_flash('error', 'ID de proveïdor invàlid.');
    header('Location: ' . BASE_URL . 'modules/proveidors/proveidors.php');
    exit;
}

$titol_pagina  = 'Detall del Proveïdor';
$pagina_activa = 'proveidors';

$proveidor = null;
$compres    = [];
$error_db   = null;

try {
    $pdo = connectDB();

    // Dades del proveïdor
    $stmt = $pdo->prepare("SELECT * FROM proveidor WHERE id_proveidor = ?");
    $stmt->execute([$id_proveidor]);
    $proveidor = $stmt->fetch();

    if (!$proveidor) {
        set_flash('error', 'El proveïdor no existeix.');
        header('Location: ' . BASE_URL . 'modules/proveidors/proveidors.php');
        exit;
    }

    // Compres realitzades a aquest proveïdor
    $stmt = $pdo->prepare("
        SELECT 
            cp.id_compra,
            cp.data_compra,
            cp.quantitat,
            cp.preu_unitari,
            cp.num_lot,
            p.nom_comercial,
            p.unitat_mesura,
            (cp.quantitat * cp.preu_unitari) AS import_total
        FROM compra_producte cp
        JOIN producte_quimic p ON p.id_producte = cp.id_producte
        WHERE cp.id_proveidor = ?
        ORDER BY cp.data_compra DESC
        LIMIT 20
    ");
    $stmt->execute([$id_proveidor]);
    $compres = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] veure_proveidor.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del proveïdor.';
}

function classeTipus($tipus): array
{
    return match($tipus) {
        'Fitosanitari' => ['badge--vermell', 'fa-spray-can'],
        'Fertilitzant' => ['badge--verd', 'fa-seedling'],
        'Llavor'       => ['badge--groc', 'fa-wheat-awn'],
        'Maquinaria'   => ['badge--blau', 'fa-tractor'],
        'Altres'       => ['badge--gris', 'fa-box'],
        default        => ['badge--gris', 'fa-box'],
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-detall">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-truck-field" aria-hidden="true"></i>
            Detall del Proveïdor
        </h1>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/proveidors/proveidors.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/proveidors/nou_proveidor.php?editar=<?= (int)$id_proveidor ?>" 
               class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($proveidor): ?>
        <!-- Dades principals -->
        <div class="detall-grid">
            <div class="detall-bloc">
                <h2 class="detall-bloc__titol">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Informació General
                </h2>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Nom</label>
                    <div class="detall-valor">
                        <strong><?= e($proveidor['nom']) ?></strong>
                    </div>
                </div>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Tipus</label>
                    <div class="detall-valor">
                        <?php [$classe_badge, $icona] = classeTipus($proveidor['tipus']); ?>
                        <span class="badge <?= $classe_badge ?>">
                            <i class="fas <?= $icona ?>" aria-hidden="true"></i>
                            <?= e($proveidor['tipus']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detall-bloc">
                <h2 class="detall-bloc__titol">
                    <i class="fas fa-address-book" aria-hidden="true"></i>
                    Contacte
                </h2>

                <?php if ($proveidor['telefon']): ?>
                    <div class="detall-camp">
                        <label class="detall-etiqueta">Telèfon</label>
                        <div class="detall-valor">
                            <a href="tel:<?= e($proveidor['telefon']) ?>" class="enllac-contacte">
                                <i class="fas fa-phone" aria-hidden="true"></i>
                                <?= e($proveidor['telefon']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($proveidor['email']): ?>
                    <div class="detall-camp">
                        <label class="detall-etiqueta">Email</label>
                        <div class="detall-valor">
                            <a href="mailto:<?= e($proveidor['email']) ?>" class="enllac-contacte">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                                <?= e($proveidor['email']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($proveidor['adreca']): ?>
                    <div class="detall-camp">
                        <label class="detall-etiqueta">Adreça</label>
                        <div class="detall-valor">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <?= nl2br(e($proveidor['adreca'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$proveidor['telefon'] && !$proveidor['email'] && !$proveidor['adreca']): ?>
                    <p class="text-suau">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha informació de contacte registrada.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Compres realitzades -->
        <div class="detall-bloc detall-bloc--ample">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                Compres Realitzades
                <span class="badge badge--gris"><?= count($compres) ?></span>
            </h2>

            <?php if (empty($compres)): ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Aquest proveïdor no té compres registrades.
                </p>
            <?php else: ?>
                <div class="taula-container">
                    <table class="taula-simple">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Producte</th>
                                <th class="text-dreta">Quantitat</th>
                                <th class="text-dreta">Preu Unitari</th>
                                <th class="text-dreta">Import Total</th>
                                <th>Núm. Lot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_import = 0;
                            foreach ($compres as $compra): 
                                $total_import += (float)$compra['import_total'];
                            ?>
                                <tr>
                                    <td><?= format_data($compra['data_compra'], curta: true) ?></td>
                                    <td>
                                        <strong><?= e($compra['nom_comercial']) ?></strong>
                                        <br><small class="text-suau"><?= e($compra['unitat_mesura']) ?></small>
                                    </td>
                                    <td class="text-dreta">
                                        <?= number_format((float)$compra['quantitat'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-dreta">
                                        <?= number_format((float)$compra['preu_unitari'], 2, ',', '.') ?> EUR
                                    </td>
                                    <td class="text-dreta">
                                        <strong><?= number_format((float)$compra['import_total'], 2, ',', '.') ?> EUR</strong>
                                    </td>
                                    <td>
                                        <?= e($compra['num_lot'] ?: '---') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-dreta">
                                    <strong>Total:</strong>
                                </td>
                                <td class="text-dreta">
                                    <strong class="estat-ok">
                                        <?= number_format($total_import, 2, ',', '.') ?> EUR
                                    </strong>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="accio-peu">
                    <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php" class="boto-secundari">
                        <i class="fas fa-plus" aria-hidden="true"></i> Nova Compra
                    </a>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

