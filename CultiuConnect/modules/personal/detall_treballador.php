<?php
/**
 * modules/personal/detall_treballador.php — Fitxa individual i panell de rendiment d'un treballador.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$id_treballador = sanitize_int($_GET['id'] ?? null);

if (!$id_treballador) {
    set_flash('error', 'ID de treballador invàlid.');
    header('Location: ' . BASE_URL . 'modules/personal/personal.php');
    exit;
}

$error_db = null;
$t = [];
$kpis = [];
$hores_evol = [];

try {
    $pdo = connectDB();

    // 1. Dades personals bàsiques
    $stmt = $pdo->prepare("SELECT * FROM treballador WHERE id_treballador = ?");
    $stmt->execute([$id_treballador]);
    $t = $stmt->fetch();

    if (!$t) {
        set_flash('error', 'Treballador no trobat.');
        header('Location: ' . BASE_URL . 'modules/personal/personal.php');
        exit;
    }

    $titol_pagina  = "Fitxa: " . e($t['nom'] . ' ' . $t['cognoms']);
    $pagina_activa = 'personal';

    // 2. Extracció de KPIs generals Anuals
    // Hores totals de Jornada normal
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, data_hora_inici, data_hora_fi))/60, 0)
        FROM jornada
        WHERE id_treballador = ? AND data_hora_fi IS NOT NULL
          AND YEAR(data_hora_inici) = YEAR(CURDATE())
    ");
    $stmt->execute([$id_treballador]);
    $hores_jornada = (float) $stmt->fetchColumn();

    // Hores i Kg de Collita
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ct.kg_recollectats), 0) as total_kg,
               COALESCE(SUM(ct.hores_treballades), 0) as hores_collita
        FROM collita_treballador ct
        JOIN collita c ON c.id_collita = ct.id_collita
        WHERE ct.id_treballador = ? AND YEAR(c.data_inici) = YEAR(CURDATE())
    ");
    $stmt->execute([$id_treballador]);
    $collitaData = $stmt->fetch();
    
    $kg_recull = (float)$collitaData['total_kg'];
    $hores_collita = (float)$collitaData['hores_collita'];
    
    // Total hores generals (agregades)
    $hores_totals = $hores_jornada + $hores_collita;
    $eficiencia_kg_hr = $hores_collita > 0 ? ($kg_recull / $hores_collita) : 0;

    // Tasques assignades
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assignacio_tasca 
        WHERE id_treballador = ? AND estat IN ('completat', 'en_proces')
    ");
    $stmt->execute([$id_treballador]);
    $tasques_fetes = (int) $stmt->fetchColumn();

    // 3. Gràfic línia d'Hores Treballades per mes (Evolució Anual)
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(data_hora_inici) as mes,
            SUM(TIMESTAMPDIFF(MINUTE, data_hora_inici, data_hora_fi))/60 as hr
        FROM jornada
        WHERE id_treballador = ? AND data_hora_fi IS NOT NULL AND YEAR(data_hora_inici) = YEAR(CURDATE())
        GROUP BY MONTH(data_hora_inici)
        ORDER BY mes ASC
    ");
    $stmt->execute([$id_treballador]);
    $dades_mes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    for ($i = 1; $i <= 12; $i++) {
        $hores_evol[$i] = isset($dades_mes[$i]) ? round((float)$dades_mes[$i], 2) : 0;
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_treballador.php: ' . $e->getMessage());
    $error_db = 'Impossible extreure el rendiment del treballador.';
}

function badgeClass($r): string {
    return match(strtolower($r)) {
         'actiu' => 'badge--verd',
         'inactiu','baixa','baix' => 'badge--vermell',
         'vacances' => 'badge--blau',
         default => 'badge--gris',
    };
}
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-personal">
    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-id-badge" aria-hidden="true"></i>
                Fitxa Personal — <?= e($t['nom']) ?>
            </h1>
            <p class="descripcio-seccio">
                Dades de contacte i anàlisi de rendiment associat.
            </p>
        </div>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/personal/nou_treballador.php?editar=<?= $id_treballador ?>" class="boto-principal">
                <i class="fas fa-pen"></i> Editar Treballador
            </a>
            <a href="personal.php" class="boto-secundari">
                <i class="fas fa-arrow-left"></i> Tornar a Directori
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert"><?= e($error_db) ?></div>
    <?php endif; ?>

    <div class="layout-dues-columnes layout-dues-columnes--sidebar">
        
        <!-- COL 1: DADES PERSONALS I CONTACTE -->
        <div class="card perfil-treballador">
            <div class="perfil-treballador__cap">
                <div class="perfil-treballador__avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h2 class="perfil-treballador__nom"><?= e($t['nom'].' '.$t['cognoms']) ?></h2>
                <span class="badge <?= badgeClass($t['estat']) ?> perfil-treballador__estat"><?= e(ucfirst($t['estat'])) ?></span>
            </div>

            <hr class="perfil-treballador__separador">
            
            <ul class="llista-fitxa">
                <li><i class="fas fa-id-card text-suau icona-fixa-25"></i> <strong>DNI/NIE:</strong> <?= e($t['dni']) ?></li>
                <li><i class="fas fa-phone text-suau icona-fixa-25"></i> <strong>Telèfon:</strong> <?= e($t['telefon'] ?: 'No indicat') ?></li>
                <li><i class="fas fa-envelope text-suau icona-fixa-25"></i> <strong>Correu:</strong> <?= e($t['email'] ?: 'No indicat') ?></li>
                <li><i class="fas fa-briefcase text-suau icona-fixa-25"></i> <strong>Rol:</strong> <?= e($t['rol']) ?></li>
                <li><i class="fas fa-file-contract text-suau icona-fixa-25"></i> <strong>Contracte:</strong> <?= e($t['tipus_contracte']) ?></li>
                <li><i class="fas fa-calendar-check text-suau icona-fixa-25"></i> <strong>Data Alta:</strong> <?= format_data($t['data_alta']) ?></li>
            </ul>
        </div>

        <!-- COL 2: RENDIMENT I KPIS -->
        <div class="stack-vertical">
            
            <section class="kpi-grid">
                <div class="kpi-highlight">
                    <div class="kpi-highlight__icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <span class="kpi-highlight__value"><?= number_format($hores_totals, 1, ',', '.') ?> h</span>
                        <span class="kpi-highlight__label">Hores globals reportades (Any)</span>
                    </div>
                </div>
                <div class="kpi-highlight">
                    <div class="kpi-highlight__icon"><i class="fas fa-apple-whole"></i></div>
                    <div>
                        <span class="kpi-highlight__value"><?= number_format($eficiencia_kg_hr, 1, ',', '.') ?> kg/h</span>
                        <span class="kpi-highlight__label">Eficiència Mitjana Collita</span>
                    </div>
                </div>
                <div class="kpi-highlight">
                    <div class="kpi-highlight__icon"><i class="fas fa-list-check"></i></div>
                    <div>
                        <span class="kpi-highlight__value"><?= $tasques_fetes ?></span>
                        <span class="kpi-highlight__label">Tasques processades</span>
                    </div>
                </div>
            </section>

            <div class="card chart-card">
                <h3 class="chart-card__title"><i class="fas fa-chart-line text-suau"></i> Evolució de la participació laboral (Assistència vs Dies treballats)</h3>
                <div class="chart-card__canvas">
                    <canvas id="grafic-hores"></canvas>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('grafic-hores')?.getContext('2d');
    if (!ctx) return;

    const dataOriginal = <?= json_encode(array_values($hores_evol)) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Gen', 'Feb', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Des'],
            datasets: [{
                label: 'Hores Realitzades',
                data: dataOriginal,
                borderColor: 'hsl(142, 60%, 45%)',
                backgroundColor: 'hsla(142, 60%, 45%, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: 'hsl(142, 60%, 45%)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Quantitat d\'Hores' }
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
