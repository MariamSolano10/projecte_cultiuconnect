<?php
/**
 * alertes.php — Centre d'alertes globals (encarregat).
 *
 * Centralitza:
 * - Contractes a punt de vèncer
 * - Estoc mínim (fito/fert)
 * - Tractaments programats propers
 * - (ja disponibles també al header: caducitats estoc, certificacions)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();                              
requereix_rol(['admin', 'tecnic', 'responsable']);

$titol_pagina  = 'Centre d’Alertes';
$pagina_activa = 'alertes';

$error_db = null;
$alertes_estoc_minim = $alertes_caducitat = $alertes_tractaments = $alertes_certificats = $alertes_contractes = [];

try {
    $pdo = connectDB();
    $alertes_estoc_minim = obtenirAlertesEstoc($pdo);
    $alertes_caducitat   = obtenirAlertesCaducitatEstoc($pdo, 30);
    $alertes_tractaments = obtenirAlertesTractaments($pdo);
    $alertes_certificats = obtenirAlertesCertificacions($pdo, 30);
    $alertes_contractes  = obtenirAlertesContractes($pdo, 30);
} catch (Exception $e) {
    error_log('[CultiuConnect] alertes.php: ' . $e->getMessage());
    $error_db = 'No s’han pogut carregar les alertes ara mateix.';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="contingut-llista">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-bell" aria-hidden="true"></i>
            Centre d’Alertes
        </h1>
        <a class="boto-secundari" href="<?= BASE_URL ?>index.php">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar al panell
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card <?= count($alertes_contractes) > 0 ? 'stat-card--avis' : '' ?>">
            <i class="fas fa-file-signature"></i>
            <div class="stat-val"><?= count($alertes_contractes) ?></div>
            <div class="stat-label">Contractes (30 dies)</div>
        </div>
        <div class="stat-card <?= count($alertes_estoc_minim) > 0 ? 'stat-card--error' : '' ?>">
            <i class="fas fa-box-open"></i>
            <div class="stat-val"><?= count($alertes_estoc_minim) ?></div>
            <div class="stat-label">Estoc mínim</div>
        </div>
        <div class="stat-card <?= count($alertes_caducitat) > 0 ? 'stat-card--error' : '' ?>">
            <i class="fas fa-hourglass-end"></i>
            <div class="stat-val"><?= count($alertes_caducitat) ?></div>
            <div class="stat-label">Lots caduquen (30 dies)</div>
        </div>
        <div class="stat-card <?= count($alertes_tractaments) > 0 ? 'stat-card--avis' : '' ?>">
            <i class="fas fa-calendar-check"></i>
            <div class="stat-val"><?= count($alertes_tractaments) ?></div>
            <div class="stat-label">Tractaments propers</div>
        </div>
        <div class="stat-card <?= count($alertes_certificats) > 0 ? 'stat-card--avis' : '' ?>">
            <i class="fas fa-id-card"></i>
            <div class="stat-val"><?= count($alertes_certificats) ?></div>
            <div class="stat-label">Certificacions (30 dies)</div>
        </div>
    </div>

    <div class="panells-inferiors">

        <section class="panell-taula" aria-labelledby="t-contractes">
            <h2 id="t-contractes"><i class="fas fa-file-signature" aria-hidden="true"></i> Contractes a punt de vèncer</h2>
            <?php if (empty($alertes_contractes)): ?>
                <p class="sense-dades"><i class="fas fa-circle-check" aria-hidden="true"></i> Cap venciment proper.</p>
            <?php else: ?>
                <table class="taula-simple">
                    <thead><tr><th>Treballador</th><th>Fi</th><th>Acció</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertes_contractes as $a): ?>
                        <tr>
                            <td><?= e(($a['cognoms'] ?? '') . ', ' . ($a['nom'] ?? '')) ?></td>
                            <td><?= !empty($a['data_baixa']) ? e(format_data((string)$a['data_baixa'], curta: true)) : '—' ?></td>
                            <td><a class="boto-taula" href="<?= BASE_URL ?>modules/personal/personal.php?estat=actiu">Obrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="panell-taula" aria-labelledby="t-estoc">
            <h2 id="t-estoc"><i class="fas fa-box-open" aria-hidden="true"></i> Estoc baix (magatzem)</h2>
            <?php if (empty($alertes_estoc_minim)): ?>
                <p class="sense-dades"><i class="fas fa-circle-check" aria-hidden="true"></i> Sense productes per sota del mínim.</p>
            <?php else: ?>
                <table class="taula-simple">
                    <thead><tr><th>Producte</th><th>Actual</th><th>Acció</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertes_estoc_minim as $a): ?>
                        <tr>
                            <td><?= e($a['nom_comercial'] ?? '—') ?></td>
                            <td><?= e($a['estoc_actual'] ?? '—') ?> <?= e($a['unitat_mesura'] ?? '') ?></td>
                            <td><a class="boto-taula" href="<?= BASE_URL ?>modules/estoc/estoc.php">Obrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="panell-taula" aria-labelledby="t-tract">
            <h2 id="t-tract"><i class="fas fa-calendar-check" aria-hidden="true"></i> Tractaments programats propers</h2>
            <?php if (empty($alertes_tractaments)): ?>
                <p class="sense-dades"><i class="fas fa-circle-check" aria-hidden="true"></i> Cap tractament pendent dins del marge d’avís.</p>
            <?php else: ?>
                <table class="taula-simple">
                    <thead><tr><th>Sector</th><th>Data prevista</th><th>Acció</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertes_tractaments as $a): ?>
                        <tr>
                            <td><?= e($a['nom_sector'] ?? '—') ?></td>
                            <td><?= !empty($a['data_prevista']) ? e(format_data((string)$a['data_prevista'], curta: true)) : '—' ?></td>
                            <td><a class="boto-taula" href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php">Obrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

