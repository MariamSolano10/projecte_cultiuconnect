<?php
/**
 * includes/header.php — Capçalera amb sidebar esquerra fixa.
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: text/html; charset=UTF-8');

cc_session_start();

try {
    $pdo = connectDB();
} catch (Exception $e) {
    $pdo = null;
}

if (!isset($titol_pagina))
    $titol_pagina = 'CultiuConnect';
if (!isset($pagina_activa))
    $pagina_activa = '';

$usuari = usuari_actiu();
$flash = get_flash();

if ($pdo !== null) {
    $alertes_estoc_minim = obtenirAlertesEstoc($pdo);
    $alertes_caducitat   = obtenirAlertesCaducitatEstoc($pdo, 30);
    $alertes_tractaments = obtenirAlertesTractaments($pdo);
    $alertes_certificats = obtenirAlertesCertificacions($pdo, 30);
    $alertes_contractes  = obtenirAlertesContractes($pdo, 30);
} else {
    $alertes_estoc_minim = $alertes_caducitat = $alertes_tractaments = $alertes_certificats = $alertes_contractes = [];
}
$total_alertes = count($alertes_estoc_minim) + count($alertes_caducitat)
               + count($alertes_tractaments) + count($alertes_certificats)
               + count($alertes_contractes);

function nav_item(string $clau, string $pagina_activa): string
{
    return ($pagina_activa === $clau) ? 'actiu' : '';
}

function nav_aria(string $clau, string $pagina_activa): string
{
    return ($pagina_activa === $clau) ? 'aria-current="page"' : '';
}

function inicial_avatar(string $nom): string
{
    $nom = trim($nom);
    if ($nom === '') {
        return '?';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($nom, 0, 1));
    }

    return strtoupper(substr($nom, 0, 1));
}

// Grups de navegació per a la sidebar
$nav_grups = [
    'Explotació' => [
        ['clau' => 'panell', 'url' => 'index.php', 'icon' => 'fa-gauge-high', 'label' => 'Panell'],
        ['clau' => 'tasques', 'url' => 'modules/tasques/tasques.php', 'icon' => 'fa-list-check', 'label' => 'Tasques'],
        ['clau' => 'parceles', 'url' => 'modules/parceles/parceles.php', 'icon' => 'fa-map-marked-alt', 'label' => 'Parcel·les'],
        ['clau' => 'sectors', 'url' => 'modules/sectors/sectors.php', 'icon' => 'fa-seedling', 'label' => 'Sectors'],
        ['clau' => 'mapa', 'url' => 'modules/mapa/mapa_gis.php', 'icon' => 'fa-map', 'label' => 'Mapa GIS'],
    ],
    'Producció' => [
        ['clau' => 'tractaments', 'url' => 'modules/operacions/operacio_nova.php', 'icon' => 'fa-spray-can-sparkles', 'label' => 'Tractaments'],
        ['clau' => 'tractaments_programats', 'url' => 'modules/tractaments/tractaments_programats.php', 'icon' => 'fa-calendar-check', 'label' => 'Alertes Tractaments'],
        ['clau' => 'monitoratge', 'url' => 'modules/monitoratge/monitoratge.php', 'icon' => 'fa-bug', 'label' => 'Monitoratge'],
        ['clau' => 'protocols', 'url' => 'modules/protocols/protocols.php', 'icon' => 'fa-file-medical', 'label' => 'Protocols'],
        ['clau' => 'fila_aplicacio', 'url' => 'modules/fila_aplicacio/fila_aplicacio.php', 'icon' => 'fa-table-list', 'label' => 'Files Tractades'],
        ['clau' => 'analisis', 'url' => 'modules/analisis/analisis_lab.php', 'icon' => 'fa-flask', 'label' => 'Anàlisis'],
        ['clau' => 'collita', 'url' => 'modules/collita/collita.php', 'icon' => 'fa-apple-whole', 'label' => 'Collita'],
        ['clau' => 'qualitat', 'url' => 'modules/qualitat/qualitat_lots.php', 'icon' => 'fa-medal', 'label' => 'Qualitat'],
        ['clau' => 'previsio', 'url' => 'modules/previsio/previsio.php', 'icon' => 'fa-chart-line', 'label' => 'Previsió Collita'],
        ['clau' => 'calendari', 'url' => 'modules/calendari/calendari.php', 'icon' => 'fa-calendar-alt', 'label' => 'Calendari'],
    ],
    'Gestió' => [
        ['clau' => 'inventari', 'url' => 'modules/estoc/estoc.php', 'icon' => 'fa-boxes-stacked', 'label' => 'Inventari'],
        ['clau' => 'proveidors', 'url' => 'modules/proveidors/proveidors.php', 'icon' => 'fa-truck-field', 'label' => 'Proveïdors'],
        ['clau' => 'personal', 'url' => 'modules/personal/personal.php', 'icon' => 'fa-users-gear', 'label' => 'Personal'],
        ['clau' => 'permisos', 'url' => 'modules/personal/permisos.php', 'icon' => 'fa-calendar-xmark', 'label' => 'Permisos'],
        ['clau' => 'jornades', 'url' => 'modules/jornades/jornades.php', 'icon' => 'fa-clock', 'label' => 'Jornades'],
        ['clau' => 'planificacio_personal', 'url' => 'modules/planificacio_personal/planificacio_personal.php', 'icon' => 'fa-calendar-days', 'label' => 'Planificació RR.HH.'],
        ['clau' => 'maquinaria', 'url' => 'modules/maquinaria/maquinaria.php', 'icon' => 'fa-tractor', 'label' => 'Maquinària'],
        ['clau' => 'finances', 'url' => 'modules/finances/inversions.php', 'icon' => 'fa-coins', 'label' => 'Finances'],
        ['clau' => 'varietats', 'url' => 'modules/varietats/varietats.php', 'icon' => 'fa-leaf', 'label' => 'Varietats'],
    ],
    'Informes' => [
        ['clau' => 'alertes', 'url' => 'alertes.php', 'icon' => 'fa-bell', 'label' => 'Alertes globals'],
        ['clau' => 'quadern', 'url' => 'modules/quadern/quadern.php', 'icon' => 'fa-book-open', 'label' => 'Quadern'],
        ['clau' => 'seguiment', 'url' => 'modules/seguiment/seguiment_anual.php', 'icon' => 'fa-chart-line', 'label' => 'Seguiment'],
    ],
];

// ── Filtre de menú per rol (UX). La seguretat real es valida a helpers.php.
$rol_usuari = strtolower((string)($usuari['rol'] ?? 'operari'));
$es_gestor  = in_array($rol_usuari, ['admin', 'tecnic', 'responsable'], true);

if (!$es_gestor) {
    // Elements que un operari NO hauria de veure al menú
    $claus_bloquejades = [
        'alertes',
        'inventari',
        'personal',
        'planificacio_personal',
        'finances',
        'varietats',
        'parceles',
        'sectors',
        'mapa',
    ];

    foreach ($nav_grups as $nom_grup => $items) {
        $nav_grups[$nom_grup] = array_values(array_filter($items, function ($it) use ($claus_bloquejades) {
            return !in_array($it['clau'] ?? '', $claus_bloquejades, true);
        }));
        if (empty($nav_grups[$nom_grup])) {
            unset($nav_grups[$nom_grup]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titol_pagina) ?> — CultiuConnect</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_v2.css">

    <?php if (!empty($css_addicional)): ?>
        <?php foreach ((array) $css_addicional as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body>

    <a href="#main-content" class="skip-link">Salta al contingut principal</a>

    <aside class="sidebar" id="sidebar" aria-label="Navegació principal">
        <div class="sidebar__logo">
            <a href="<?= BASE_URL ?>index.php">
                <img src="<?= BASE_URL ?>assets/img/LogoAppRetallatSenseNom.png" alt="CultiuConnect"
                    class="sidebar__logo-img" onerror="this.style.display='none'">
                <span class="sidebar__logo-nom">CultiuConnect</span>
            </a>
        </div>

        <nav class="sidebar__nav" aria-label="Menú principal">
            <?php foreach ($nav_grups as $grup => $items): ?>
                <div class="sidebar__grup">
                    <span class="sidebar__grup-titol"><?= $grup ?></span>
                    <ul>
                        <?php foreach ($items as $item): ?>
                            <li>
                                <a href="<?= BASE_URL ?><?= $item['url'] ?>"
                                    class="sidebar__link <?= nav_item($item['clau'], $pagina_activa) ?>"
                                    <?= nav_aria($item['clau'], $pagina_activa) ?> title="<?= e($item['label']) ?>">
                                    <i class="fas <?= $item['icon'] ?>" aria-hidden="true"></i>
                                    <span><?= e($item['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__usuari">
            <?php if ($usuari): ?>
                <div class="sidebar__usuari-info">
                    <div class="sidebar__avatar" aria-hidden="true">
                        <?= e(inicial_avatar((string)$usuari['nom'])) ?>
                    </div>
                    <div class="sidebar__usuari-dades">
                        <span class="sidebar__usuari-nom"><?= e($usuari['nom']) ?></span>
                        <span class="sidebar__usuari-rol"><?= e(ucfirst($usuari['rol'])) ?></span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>auth/logout.php" class="sidebar__logout" title="Tancar sessió"
                    aria-label="Tancar sessió">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>auth/login.php" class="sidebar__login">
                    <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Entrar</span>
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <button class="sidebar__toggle" id="sidebar-toggle" aria-label="Obrir menú" aria-expanded="false"
        aria-controls="sidebar">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <div class="sidebar__overlay" id="sidebar-overlay"></div>

    <div class="app-shell">
        <header class="top-bar" role="banner">
            <div class="top-bar__esquerra">
                <button class="sidebar__toggle top-bar__hamburguer" id="sidebar-toggle-top" aria-label="Obrir menú"
                    aria-expanded="false" aria-controls="sidebar">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>
                <h1 class="top-bar__titol"><?= e($titol_pagina) ?></h1>
            </div>
            <div class="top-bar__dreta">
                <div class="alertes-wrapper" style="position: relative;">
                    <button class="top-bar__btn-alerta"
                        onclick="document.getElementById('dropdown-alertes').classList.toggle('actiu')"
                        aria-label="Notificacions">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($total_alertes > 0): ?>
                            <span class="alerta-badge"><?= $total_alertes ?></span>
                        <?php endif; ?>
                    </button>

                    <div id="dropdown-alertes" class="alertes-dropdown">
                        <div class="alertes-dropdown__header">
                            <strong>Centre de Notificacions</strong>
                        </div>
                        <div class="alertes-dropdown__body">
                            <?php if ($total_alertes > 0): ?>

                                <?php foreach ($alertes_estoc_minim as $alerta): ?>
                                    <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="alerta-item alerta-item--warning">
                                        <i class="fa-solid fa-box-open"></i>
                                        <span>L'estoc de <strong><?= e($alerta['nom_comercial']) ?></strong> és baix
                                            (<?= e($alerta['estoc_actual']) ?>         <?= e($alerta['unitat_mesura']) ?>).</span>
                                    </a>
                                <?php endforeach; ?>

                                <?php foreach ($alertes_caducitat as $alerta): ?>
                                    <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="alerta-item alerta-item--danger">
                                        <i class="fa-solid fa-hourglass-end"></i>
                                        <span>Lot <?= e($alerta['num_lot']) ?> de
                                            <strong><?= e($alerta['nom_comercial']) ?></strong> caduca aviat.</span>
                                    </a>
                                <?php endforeach; ?>

                                <?php foreach ($alertes_tractaments as $alerta): ?>
                                    <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php"
                                        class="alerta-item alerta-item--info">
                                        <i class="fa-solid fa-tractor"></i>
                                        <span>Tractament a <strong><?= e($alerta['nom_sector']) ?></strong> previst pel
                                            <?= date('d/m/Y', strtotime($alerta['data_prevista'])) ?>.</span>
                                    </a>
                                <?php endforeach; ?>

                                <?php foreach ($alertes_certificats as $alerta): ?>
                                    <a href="<?= BASE_URL ?>modules/personal/personal.php" class="alerta-item alerta-item--primary">
                                        <i class="fa-solid fa-id-card"></i>
                                        <span>Certificació <strong><?= e($alerta['tipus_certificacio']) ?></strong> de
                                            <strong><?= e($alerta['nom']) ?> <?= e($alerta['cognoms']) ?></strong>
                                            caduca el <?= date('d/m/Y', strtotime($alerta['data_caducitat'])) ?>.</span>
                                    </a>
                                <?php endforeach; ?>

                                <?php foreach ($alertes_contractes as $alerta): ?>
                                    <a href="<?= BASE_URL ?>modules/personal/personal.php?estat=actiu" class="alerta-item alerta-item--danger">
                                        <i class="fa-solid fa-file-signature"></i>
                                        <span>Contracte temporal de <strong><?= e($alerta['nom']) ?> <?= e($alerta['cognoms']) ?></strong>
                                            finalitza el <?= date('d/m/Y', strtotime($alerta['data_baixa'])) ?>.</span>
                                    </a>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <div class="alerta-buit">
                                    <i class="fa-regular fa-circle-check"></i>
                                    <p>No hi ha alertes pendents</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="alertes-dropdown__footer" style="border-top:1px solid #eee; padding:10px 12px;">
                            <a href="<?= BASE_URL ?>alertes.php"
                               style="display:flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; font-weight:700; color: var(--verd-900);">
                                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                                Veure totes les alertes
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($usuari): ?>
                    <span class="top-bar__usuari">
                        <span class="top-bar__avatar" aria-hidden="true">
                            <?= e(inicial_avatar((string)$usuari['nom'])) ?>
                        </span>
                        <?= e($usuari['nom']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($flash): ?>
            <?php
            $icones = [
                'success' => 'circle-check',
                'error' => 'circle-xmark',
                'warning' => 'triangle-exclamation',
                'info' => 'circle-info',
            ];
            $icona = $icones[$flash['tipus']] ?? 'circle-info';
            ?>
            <div class="flash flash--<?= e($flash['tipus']) ?>" role="alert" data-flash>
                <i class="fas fa-<?= $icona ?>" aria-hidden="true"></i>
                <span><?= e($flash['missatge']) ?></span>
                <button class="flash__tancar" onclick="this.parentElement.remove()" aria-label="Tancar missatge">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>

        <main class="contingut-principal" id="main-content">