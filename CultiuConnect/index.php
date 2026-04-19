<?php
/**
 * index.php — Panell de Control Principal de CultiuConnect.
 *
 * KPIs dinàmics:
 * - Treballadors actius
 * - Kg collits aquest mes
 * - Alertes de plagues actives (monitoratge_plaga.llindar_intervencio_assolit)
 * - Tractaments programats pendents
 * - Certificacions que caduquen en 30 dies
 * - Últimes 5 aplicacions registrades
 * - Propers 7 dies de tractaments programats
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// -----------------------------------------------------------
// Inicialitzem variables de la vista ABANS del try/catch
// per evitar "undefined variable" si l'excepció es llança a meitat
// -----------------------------------------------------------
$kpis = [
    'treballadors_actius'     => 0,
    'kg_mes'                  => 0.0,
    'alertes_actives'         => 0,
    'tractaments_pendents'    => 0,
    'certificacions_caduquen' => 0,
];
$darreres_operacions = [];
$propers_tractaments = [];
$alertes_frontals     = [
    'contractes' => [],
    'estoc'      => [],
    'tractaments'=> [],
];
$error               = null;

try {
    $pdo = connectDB();

    // 1. Treballadors actius
    $kpis['treballadors_actius'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM treballador WHERE estat = 'actiu'")
        ->fetchColumn();

    // 2. Kg collits aquest mes
    $kpis['kg_mes'] = (float)$pdo
        ->query("
            SELECT COALESCE(SUM(quantitat), 0)
            FROM collita
            WHERE unitat_mesura IN ('kg', 'Kg', 'KG')
              AND MONTH(data_inici) = MONTH(CURDATE())
              AND YEAR(data_inici)  = YEAR(CURDATE())
        ")
        ->fetchColumn();

    // 3. Alertes de plagues actives
    // CORRECCIÓ: La columna és data_observacio, no data_monitoratge
    $kpis['alertes_actives'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM monitoratge_plaga
            WHERE llindar_intervencio_assolit = 1
              AND data_observacio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")
        ->fetchColumn();

    // 4. Tractaments programats pendents
    $kpis['tractaments_pendents'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM tractament_programat
            WHERE estat = 'pendent'
              AND data_prevista >= CURDATE()
        ")
        ->fetchColumn();

    // 5. Certificacions de treballadors que caduquen en 30 dies
    $kpis['certificacions_caduquen'] = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM certificacio_treballador
            WHERE data_caducitat IS NOT NULL
              AND data_caducitat BETWEEN CURDATE()
                                     AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ")
        ->fetchColumn();

    // 6. Últimes 5 aplicacions registrades
    $darreres_operacions = $pdo
        ->query("
            SELECT a.tipus_event, a.data_event, s.nom AS nom_sector
            FROM aplicacio a
            JOIN sector s ON s.id_sector = a.id_sector
            ORDER BY a.data_event DESC, a.id_aplicacio DESC
            LIMIT 5
        ")
        ->fetchAll();

    // 7. Propers tractaments programats (7 dies)
    $propers_tractaments = $pdo
        ->query("
            SELECT tp.data_prevista, tp.tipus, tp.motiu, s.nom AS nom_sector
            FROM tractament_programat tp
            JOIN sector s ON s.id_sector = tp.id_sector
            WHERE tp.estat = 'pendent'
              AND tp.data_prevista BETWEEN CURDATE()
                                       AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY tp.data_prevista ASC
            LIMIT 5
        ")
        ->fetchAll();

    // 8. Alertes frontals globals (encarregat)
    // Reusem les funcions del header (mateixa font d'alertes)
    $alertes_frontals['contractes']  = obtenirAlertesContractes($pdo, 30);
    $alertes_frontals['estoc']       = obtenirAlertesEstoc($pdo);
    $alertes_frontals['tractaments'] = obtenirAlertesTractaments($pdo);

} catch (Exception $e) {
    error_log('[CultiuConnect] index.php: ' . $e->getMessage());
    $error = 'No s\'ha pogut carregar el panell ara mateix.';
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Panell de Control';
$pagina_activa = 'panell';
require_once __DIR__ . '/includes/header.php';
?>

<div class="panell-control">

    <div class="panell-capcalera">
        <div>
            <h1 class="titol-pagina titol-pagina--compacte">
                <i class="fas fa-gauge-high" aria-hidden="true"></i>
                Panell de Control
            </h1>
            <p class="descripcio-seccio descripcio-seccio--zero">Benvingut de nou, aquí tens la visió global de l'explotació.</p>
        </div>
        <a href="<?= BASE_URL ?>modules/operacions/operacio_nova.php" class="boto-principal">
            <i class="fas fa-plus"></i> Nova Operació
        </a>
    </div>

    <?php if ($error): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error) ?>
        </div>
    <?php else: ?>

    <section class="kpi-grid" aria-label="Indicadors clau">
        <div class="kpi-card kpi-card--blau">
            <div class="kpi-card__icona">
                <i class="fas fa-users" aria-hidden="true"></i>
            </div>
            <div class="kpi-card__contingut">
                <span class="kpi-card__valor"><?= $kpis['treballadors_actius'] ?></span>
                <span class="kpi-card__etiqueta">Treballadors actius</span>
            </div>
        </div>

        <div class="kpi-card kpi-card--verd">
            <div class="kpi-card__icona">
                <i class="fas fa-apple-whole" aria-hidden="true"></i>
            </div>
            <div class="kpi-card__contingut">
                <span class="kpi-card__valor"><?= format_kg($kpis['kg_mes'], 0) ?></span>
                <span class="kpi-card__etiqueta">Producció aquest mes</span>
            </div>
        </div>

        <div class="kpi-card <?= $kpis['tractaments_pendents'] > 0 ? 'kpi-card--taronja' : 'kpi-card--gris' ?>">
            <div class="kpi-card__icona">
                <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
            </div>
            <div class="kpi-card__contingut">
                <span class="kpi-card__valor"><?= $kpis['tractaments_pendents'] ?></span>
                <span class="kpi-card__etiqueta">Tractaments pendents</span>
            </div>
        </div>

        <div class="kpi-card <?= $kpis['alertes_actives'] > 0 ? 'kpi-card--vermell' : 'kpi-card--gris' ?>">
            <div class="kpi-card__icona">
                <i class="fas fa-bug" aria-hidden="true"></i>
            </div>
            <div class="kpi-card__contingut">
                <span class="kpi-card__valor"><?= $kpis['alertes_actives'] ?></span>
                <span class="kpi-card__etiqueta">Plagues detectades</span>
            </div>
        </div>
    </section>

    <!-- Layout Asimètric en dues columnes -->
    <div class="dashboard-layout">
        
        <!-- COLUMNA PRINCIPAL (Esquerra) -->
        <div class="dashboard-column dashboard-column--main">
            <!-- Calendari dinàmic -->
            <div class="widget-container-v2">
                <?php require_once __DIR__ . '/modules/dashboard/widget_calendari.php'; ?>
            </div>

            <!-- Últimes operacions estilitzades -->
            <section class="feed-widget" aria-labelledby="titol-darreres-ops">
                <header class="feed-header">
                    <h3 id="titol-darreres-ops"><i class="fas fa-clock-rotate-left"></i> Registre d'Auditoria (5 últimes operacions)</h3>
                    <a href="<?= BASE_URL ?>modules/quadern/quadern.php" class="boto-secundari boto-secundari--petit">Quadern</a>
                </header>
                <?php if (empty($darreres_operacions)): ?>
                    <div class="feed-empty">Sense operacions registrades recents.</div>
                <?php else: ?>
                    <ul class="feed-list">
                        <?php foreach ($darreres_operacions as $op): ?>
                            <li class="feed-item">
                                <div class="feed-item__icon feed-item__icon--neutre">
                                    <i class="fas fa-seedling"></i>
                                </div>
                                <div class="feed-item__content">
                                    <div class="feed-item__title"><?= e($op['tipus_event'] ?? 'Operació general') ?></div>
                                    <div class="feed-item__desc">Sector: <strong><?= e($op['nom_sector']) ?></strong></div>
                                    <div class="feed-item__meta"><?= format_data($op['data_event'], curta: true) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <!-- COLUMNA LATERAL (Dreta) -->
        <div class="dashboard-column dashboard-column--side">
            
            <!-- Dreceres Ràpides -->
            <section class="quick-actions-grid" aria-label="Accessos ràpids">
                <a href="<?= BASE_URL ?>modules/mapa/mapa_gis.php" class="quick-action-btn">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Mapa GIS</span>
                </a>
                <a href="<?= BASE_URL ?>modules/sectors/sectors.php" class="quick-action-btn">
                    <i class="fas fa-layer-group"></i>
                    <span>Sectors</span>
                </a>
                <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php" class="quick-action-btn">
                    <i class="fas fa-spray-can-sparkles"></i>
                    <span>Tractaments</span>
                </a>
                <a href="<?= BASE_URL ?>modules/monitoratge/monitoratge.php" class="quick-action-btn">
                    <i class="fas fa-bug"></i>
                    <span>Monitoratge</span>
                </a>
                <a href="<?= BASE_URL ?>modules/collita/collita.php" class="quick-action-btn">
                    <i class="fas fa-apple-whole"></i>
                    <span>Collita</span>
                </a>
                <a href="<?= BASE_URL ?>modules/finances/inversions.php" class="quick-action-btn">
                    <i class="fas fa-sack-dollar"></i>
                    <span>Finances</span>
                </a>
            </section>

            <!-- Centre d'Avisos Global -->
            <section class="feed-widget feed-widget--full-alt">
                <header class="feed-header feed-header--verd">
                    <h3><i class="fas fa-bell text-alert icon-verd-fosc"></i> Centre d'Acció</h3>
                    <a href="<?= BASE_URL ?>alertes.php" class="veure-mes veure-mes--compacte">Obrir panell</a>
                </header>
                
                <ul class="feed-list feed-list--grow">
                    <?php 
                    $hi_ha_alertes = false;
                    
                    // 1. Alertes de certificacions
                    if ($kpis['certificacions_caduquen'] > 0) {
                        $hi_ha_alertes = true;
                        echo '<li class="feed-item feed-item--warning">
                            <div class="feed-item__icon"><i class="fas fa-id-card"></i></div>
                            <div class="feed-item__content">
                                <div class="feed-item__title">Certificacions a renovar</div>
                                <div class="feed-item__desc">Hi ha '.$kpis['certificacions_caduquen'].' treballador(s) amb accreditacions que caduquen aviat.</div>
                            </div>
                            <div class="feed-item__action"><a href="'.BASE_URL.'modules/personal/personal.php" class="btn-accio" title="Revisar"><i class="fas fa-arrow-right"></i></a></div>
                        </li>';
                    }

                    // 2. Alertes d'estoc
                    foreach (array_slice($alertes_frontals['estoc'], 0, 3) as $a) {
                        $hi_ha_alertes = true;
                        echo '<li class="feed-item feed-item--danger">
                            <div class="feed-item__icon"><i class="fas fa-box-open"></i></div>
                            <div class="feed-item__content">
                                <div class="feed-item__title">Estoc crític: '.e($a['nom_comercial'] ?? 'N/D').'</div>
                                <div class="feed-item__desc">Només ens queden '.e($a['estoc_actual']).' '.e($a['unitat_mesura']).' als magatzems.</div>
                            </div>
                            <div class="feed-item__action"><a href="'.BASE_URL.'modules/estoc/estoc.php" class="btn-accio"><i class="fas fa-arrow-right"></i></a></div>
                        </li>';
                    }

                    // 3. Propers tractaments (Fusiona les alertes i la taula de propers 7 dies)
                    foreach (array_slice($propers_tractaments, 0, 4) as $tr) {
                        $hi_ha_alertes = true;
                        echo '<li class="feed-item feed-item--info">
                            <div class="feed-item__icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="feed-item__content">
                                <div class="feed-item__title">Tractament Programat</div>
                                <div class="feed-item__desc">Sector '.e($tr['nom_sector']).' - '.e($tr['motiu'] ?? 'Prevenció').'</div>
                                <div class="feed-item__meta">Data Prevista: '.format_data($tr['data_prevista'], curta: true).'</div>
                            </div>
                            <div class="feed-item__action"><a href="'.BASE_URL.'modules/tractaments/tractaments_programats.php" class="btn-accio"><i class="fas fa-arrow-right"></i></a></div>
                        </li>';
                    }

                    if (!$hi_ha_alertes) {
                        echo '<div class="estat-buit">
                            <i class="fas fa-circle-check estat-buit__icona"></i>
                            <div class="estat-buit__titol">Tot al dia!</div>
                            <div class="estat-buit__text">No tens pendents crítics ni programats recents.</div>
                        </div>';
                    }
                    ?>
                </ul>
            </section>
            
        </div>
    </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

