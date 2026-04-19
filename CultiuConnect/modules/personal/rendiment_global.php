<?php
/**
 * modules/personal/rendiment_global.php — Tauler Comparatiu General de Rendiment (Rankings).
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$titol_pagina  = "Rendiment Global d'Equip";
$pagina_activa = 'personal';
$error_db = null;

$ranking_recollectors = [];
$labels = [];
$data_efi = [];

try {
    $pdo = connectDB();

    // Rànquing del Top 10 Treballadors per Eficiència en Kg/h (Només Collita)
    $stmt = $pdo->prepare("
        SELECT t.id_treballador, t.nom, t.cognoms, t.estat,
               COALESCE(SUM(ct.kg_recollectats) / NULLIF(SUM(ct.hores_treballades),0), 0) as eficiencia,
               COALESCE(SUM(ct.hores_treballades), 0) as hr,
               COALESCE(SUM(ct.kg_recollectats), 0) as totals
        FROM treballador t
        JOIN collita_treballador ct ON ct.id_treballador = t.id_treballador
        JOIN collita c ON c.id_collita = ct.id_collita
        WHERE YEAR(c.data_inici) = YEAR(CURDATE())
        GROUP BY t.id_treballador
        ORDER BY eficiencia DESC
        LIMIT 10
    ");
    $stmt->execute();
    $ranking_recollectors = $stmt->fetchAll();

    foreach ($ranking_recollectors as $r) {
        $labels[] = e($r['nom'] . ' ' . substr($r['cognoms'], 0, 1) . '.');
        $data_efi[] = round((float)$r['eficiencia'], 1);
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] rendiment_global.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut compilar el rànquing general de rendiment operari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-personal">
    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                Panell General de Productivitat Laboral
            </h1>
            <p class="descripcio-seccio">
                Comparativa avançada d'eficiència i ranking de l'equip operatiu durant la campanya actual.
            </p>
        </div>
        <div class="capcalera-seccio__accions">
            <a href="personal.php" class="boto-secundari">
                <i class="fas fa-arrow-left"></i> Tornar al Directori
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert"><?= e($error_db) ?></div>
    <?php endif; ?>

    <div class="layout-dues-columnes layout-dues-columnes--sidebar">
        
        <!-- COLUMNA ESQUERRA: RÀNQUING GRÀFIC -->
        <div class="card chart-card">
            <h2 class="chart-card__title">
                <i class="fas fa-trophy"></i> Lideratge en Eficiència Recol·lectora (kg/h)
            </h2>
            <div class="chart-card__canvas chart-card__canvas--gran">
                <canvas id="grafic-ranking"></canvas>
            </div>
            <p class="chart-card__note">Aquest gràfic valora exclusivament les hores aplicades durant feines associades a esdeveniments de recollida de cultius de l'any present, donant justícia al pes vs velocitat.</p>
        </div>

        <!-- COLUMNA DRETA: DATA GRID -->
        <div class="card chart-card">
            <h3 class="chart-card__title"><i class="fas fa-medal text-suau"></i> Top 10 Actual</h3>
            
            <?php if (empty($ranking_recollectors)): ?>
                <div class="sense-dades">
                    <i class="fas fa-info-circle text-suau"></i><br>
                    Encara no hi ha operaris amb dades de collita quantificables.
                </div>
            <?php else: ?>
                <table class="ranking-lista">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Empleat</th>
                            <th class="text-dreta">Total Kg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ranking_recollectors as $i => $rec): ?>
                        <tr>
                            <td><strong><?= $i+1 ?></strong></td>
                            <td>
                                <a href="detall_treballador.php?id=<?= $rec['id_treballador'] ?>" class="ranking-lista__link">
                                    <?= e($rec['nom'].' '.$rec['cognoms']) ?>
                                </a>
                                <?php if($i===0): ?><br><span class="badge badge--destacat"><i class="fas fa-star"></i> Empleat del Mes</span><?php endif; ?>
                            </td>
                            <td class="text-dreta">
                                <?= number_format($rec['totals'], 0, ',', '.') ?> kg
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('grafic-ranking')?.getContext('2d');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Eficiència (kg per hora)',
                data: <?= json_encode($data_efi) ?>,
                backgroundColor: 'hsla(142, 60%, 45%, 0.7)',
                borderColor: 'hsl(142, 60%, 45%)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Kg / Hora' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
