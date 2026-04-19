<?php
/**
 * tracabilitat.php — Pàgina pública de traçabilitat (client final).
 *
 * URL: /tracabilitat.php?c=<token>
 * El token és el contingut de `lot_produccio.codi_qr`.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/html; charset=UTF-8');

$token = sanitize($_GET['c'] ?? '');
$error = null;
$lot   = null;
$qc    = null;

if ($token === '' || strlen($token) < 8) {
    $error = 'Codi de traçabilitat no vàlid.';
} else {
    try {
        $pdo = connectDB();

        $stmt = $pdo->prepare("
            SELECT
                lp.id_lot,
                lp.identificador,
                lp.data_processat,
                lp.pes_kg,
                lp.qualitat   AS lot_qualitat,
                lp.desti,
                c.id_collita,
                c.data_inici  AS collita_inici,
                c.data_fi     AS collita_fi,
                s.nom         AS nom_sector,
                v.nom_varietat,
                (SELECT COUNT(DISTINCT ct.id_treballador) FROM collita_treballador ct WHERE ct.id_collita = c.id_collita) AS equip_recollectors,
                ST_Y(ST_Centroid(s.coordenades_geo)) AS lat,
                ST_X(ST_Centroid(s.coordenades_geo)) AS lng
            FROM lot_produccio lp
            JOIN collita c        ON c.id_collita = lp.id_collita
            JOIN plantacio pl     ON pl.id_plantacio = c.id_plantacio
            JOIN sector s         ON s.id_sector = pl.id_sector
            LEFT JOIN varietat v  ON v.id_varietat = pl.id_varietat
            WHERE lp.codi_qr = :t
            LIMIT 1
        ");
        $stmt->execute([':t' => $token]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lot) {
            $error = 'No s\'ha trobat cap lot associat a aquest codi.';
        } else {
            $stmt_q = $pdo->prepare("
                SELECT data_control, calibre_mm, fermesa_kg_cm2, color, sabor, resultat
                FROM control_qualitat
                WHERE id_lot = :id
                ORDER BY data_control DESC, id_control DESC
                LIMIT 1
            ");
            $stmt_q->execute([':id' => (int)$lot['id_lot']]);
            $qc = $stmt_q->fetch(PDO::FETCH_ASSOC) ?: null;
        }

    } catch (Exception $e) {
        error_log('[CultiuConnect] tracabilitat.php: ' . $e->getMessage());
        $error = 'No s\'ha pogut consultar la traçabilitat ara mateix.';
    }
}
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Traçabilitat — CultiuConnect</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_v2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body class="tracabilitat-app">
    <div class="app-shell tracabilitat-shell">
        
        <header class="top-bar tracabilitat-topbar" role="banner">
            <div class="tracabilitat-topbar__brand">
                <div class="tracabilitat-topbar__logo">C</div>
                <h1 class="top-bar__titol">CultiuConnect Traçabilitat</h1>
            </div>
        </header>

        <main class="contingut-principal tracabilitat-main">
            
            <div class="panell-control tracabilitat-panell">

                <?php if ($error): ?>
                    <div class="tracabilitat-capcalera">
                        <div class="flash flash--error" role="alert">
                            <i class="fas fa-circle-xmark" aria-hidden="true"></i> <?= e($error) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="capcalera-seccio tracabilitat-capcalera">
                        <h1 class="titol-pagina">
                            <i class="fas fa-seedling" aria-hidden="true"></i>
                            Informació del Producte
                        </h1>
                        <p class="descripcio-seccio">
                            Dades públiques associades al lot escanejat.
                        </p>
                    </div>
                    
                    <section class="panell-taula">
                        <div class="tracabilitat-hero">
                            <div>
                                <p class="tracabilitat-hero__eyebrow">Identificador de Lot</p>
                                <div class="tracabilitat-hero__id"><?= e($lot['identificador']) ?></div>
                                <div class="tracabilitat-hero__meta">
                                    Sector: <strong><?= e($lot['nom_sector']) ?></strong>
                                    <?= $lot['nom_varietat'] ? ' · Varietat: <strong>' . e($lot['nom_varietat']) . '</strong>' : '' ?>
                                    
                                    <?php if ($lot['lat'] && $lot['lng']): ?>
                                        · <a href="https://www.google.com/maps?q=<?= (float)$lot['lat'] ?>,<?= (float)$lot['lng'] ?>" target="_blank" rel="noopener noreferrer" class="tracabilitat-link">
                                            <i class="fas fa-map-location-dot"></i> Origen (Mapa)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="tracabilitat-hero__dates">
                                <?= $lot['data_processat'] ? 'Processat: <strong>' . e(format_data($lot['data_processat'], curta: true)) . '</strong><br>' : '' ?>
                                <?= $lot['collita_inici'] ? 'Collita: <strong>' . e(format_data(substr((string)$lot['collita_inici'],0,10), curta: true)) . '</strong>' : '' ?>
                                <?= $lot['collita_fi'] ? ' - <strong>' . e(format_data(substr((string)$lot['collita_fi'],0,10), curta: true)) . '</strong>' : '' ?>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-weight-hanging"></i></div>
                                <div class="stat-val"><?= $lot['pes_kg'] !== null ? e(number_format((float)$lot['pes_kg'], 2, ',', '.')) . ' kg' : '—' ?></div>
                                <div class="stat-label">Pes</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-tags"></i></div>
                                <div class="stat-val"><?= e($lot['lot_qualitat'] ?? '—') ?></div>
                                <div class="stat-label">Qualitat</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-earth-europe"></i></div>
                                <div class="stat-val"><?= e($lot['desti'] ?? '—') ?></div>
                                <div class="stat-label">Destí</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-users-gear"></i></div>
                                <div class="stat-val"><?= (int)$lot['equip_recollectors'] ?> person.</div>
                                <div class="stat-label">Equip Agrícola</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-hashtag"></i></div>
                                <div class="stat-val">#<?= (int)$lot['id_lot'] ?></div>
                                <div class="stat-label">ID Intern</div>
                            </div>
                        </div>

                        <h2 class="chart-card__title">
                            <i class="fas fa-clipboard-check" aria-hidden="true"></i> 
                            Últim control de qualitat
                        </h2>
                        
                        <?php if (!$qc): ?>
                            <div class="sense-dades">
                                <i class="fas fa-info-circle"></i>
                                No hi ha cap control de qualitat publicat per aquest lot.
                            </div>
                        <?php else: ?>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-card__icon"><i class="fas fa-calendar"></i></div>
                                    <div class="stat-val"><?= $qc['data_control'] ? e(format_data((string)$qc['data_control'], curta: true)) : '—' ?></div>
                                    <div class="stat-label">Data</div>
                                </div>
                                <div class="stat-card <?= ($qc['resultat'] === 'Acceptat') ? 'stat-card--correcte' : (($qc['resultat'] === 'Rebutjat') ? 'stat-card--error' : 'stat-card--avis') ?>">
                                    <div class="stat-card__icon"><i class="fas <?= ($qc['resultat'] === 'Acceptat') ? 'fa-check-circle' : (($qc['resultat'] === 'Rebutjat') ? 'fa-times-circle' : 'fa-exclamation-circle') ?>"></i></div>
                                    <div class="stat-val"><?= e($qc['resultat'] ?? '—') ?></div>
                                    <div class="stat-label">Resultat</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-card__icon"><i class="fas fa-ruler"></i></div>
                                    <div class="stat-val"><?= $qc['calibre_mm'] !== null ? e($qc['calibre_mm']) . ' mm' : '—' ?></div>
                                    <div class="stat-label">Calibre</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-card__icon"><i class="fas fa-dumbbell"></i></div>
                                    <div class="stat-val"><?= $qc['fermesa_kg_cm2'] !== null ? e($qc['fermesa_kg_cm2']) . ' kg' : '—' ?></div>
                                    <div class="stat-label">Fermesa (kg/cm²)</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="tracabilitat-note">
                            <i class="fas fa-shield-halved"></i> Aquesta pàgina mostra només dades públiques del lot. Si necessites informació addicional, contacta amb el productor.
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

