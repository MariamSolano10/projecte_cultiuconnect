<?php
/**
 * modules/analisis/evolucio_nutrients.php
 *
 * Vista d'evolució de nutrients NPK (Nitrogen, Fòsfor, Potassi) al llarg del temps.
 * Mostra gràfiques interactives per visualitzar les tendències de nutrients en el sòl.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Evolució de Nutrients';
$pagina_activa = 'analisis';

$sectors       = [];
$analisis      = [];
$sector_sel    = sanitize_int($_GET['id_sector'] ?? null);
$any_sel       = sanitize_int($_GET['any'] ?? date('Y'));
$error_db      = null;

try {
    $pdo = connectDB();

    // Carregar sectors
    $sectors = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom ASC")->fetchAll();

    // Carregar anàlisis de nutrients
    if ($sector_sel) {
        $sql = "
            SELECT 
                cs.id_sector,
                cs.data_analisi,
                cs.N,
                cs.P,
                cs.K,
                cs.pH,
                cs.materia_organica,
                cs.conductivitat_electrica,
                s.nom AS nom_sector
            FROM caracteristiques_sol cs
            JOIN sector s ON s.id_sector = cs.id_sector
            WHERE cs.id_sector = :id_sector
            AND YEAR(cs.data_analisi) = :any
            ORDER BY cs.data_analisi ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_sector' => $sector_sel, ':any' => $any_sel]);
        $analisis = $stmt->fetchAll();
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] evolucio_nutrients.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades d\'anàlisis de nutrients.';
}

function valorNutrient(float $valor, string $unitat = 'ppm'): string
{
    return number_format($valor, 2, ',', '.') . ' ' . $unitat;
}

function classePh(float $ph): array
{
    if ($ph < 5.5) return ['badge--vermell', 'Àcid'];
    if ($ph < 6.0) return ['badge--groc', 'Lleugerament àcid'];
    if ($ph < 6.5) return ['badge--verd', 'Òptim'];
    if ($ph < 7.0) return ['badge--verd', 'Bé'];
    if ($ph < 7.5) return ['badge--groc', 'Lleugerament alcalí'];
    return ['badge--vermell', 'Alcalí'];
}

function classeNutrient(float $valor, string $nutrient): array
{
    $limits = [
        'N' => ['baix' => 20, 'optim' => 40, 'alt' => 80],
        'P' => ['baix' => 15, 'optim' => 30, 'alt' => 60],
        'K' => ['baix' => 50, 'optim' => 120, 'alt' => 250]
    ];
    
    $limit = $limits[$nutrient] ?? ['baix' => 0, 'optim' => 50, 'alt' => 100];
    
    if ($valor < $limit['baix']) return ['badge--vermell', 'Baix'];
    if ($valor < $limit['optim']) return ['badge--groc', 'Mitjà'];
    if ($valor < $limit['alt']) return ['badge--verd', 'Òptim'];
    return ['badge--taronja', 'Alt'];
}

$css_addicional = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-evolucio-nutrients">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-flask" aria-hidden="true"></i>
            Evolució de Nutrients del Sòl
        </h1>
        <p class="descripcio-seccio">
            Visualitza l'evolució dels nutrients principals (N-P-K) i altres paràmetres
            del sòl al llarg del temps per prendre decisions informades sobre fertilització.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="id_sector" class="form-label form-label--requerit">
                    Sector
                </label>
                <select id="id_sector" name="id_sector" class="form-select camp-requerit" required>
                    <option value="">Selecciona un sector</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int)$s['id_sector'] ?>" 
                                <?= $sector_sel === (int)$s['id_sector'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="any" class="form-label">Any</label>
                <select id="any" name="any" class="form-select">
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $any_sel ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <?php if ($sector_sel && !empty($analisis)): ?>
        <!-- Estadístiques actuals -->
        <div class="kpi-grid kpi-grid--petit">
            <?php 
            $ultim_analisi = end($analisis);
            [$classe_ph, $text_ph] = classePh((float)$ultim_analisi['pH']);
            [$classe_n, $text_n] = classeNutrient((float)$ultim_analisi['N'], 'N');
            [$classe_p, $text_p] = classeNutrient((float)$ultim_analisi['P'], 'P');
            [$classe_k, $text_k] = classeNutrient((float)$ultim_analisi['K'], 'K');
            ?>
            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-vial"></i>
                </div>
                <span class="kpi-card__valor"><?= valorNutrient((float)$ultim_analisi['N']) ?></span>
                <span class="kpi-card__etiqueta">Nitrogen (N)</span>
                <span class="badge <?= $classe_n ?>"><?= $text_n ?></span>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-vial"></i>
                </div>
                <span class="kpi-card__valor"><?= valorNutrient((float)$ultim_analisi['P']) ?></span>
                <span class="kpi-card__etiqueta">Fòsfor (P)</span>
                <span class="badge <?= $classe_p ?>"><?= $text_p ?></span>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-vial"></i>
                </div>
                <span class="kpi-card__valor"><?= valorNutrient((float)$ultim_analisi['K']) ?></span>
                <span class="kpi-card__etiqueta">Potassi (K)</span>
                <span class="badge <?= $classe_k ?>"><?= $text_k ?></span>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-tint"></i>
                </div>
                <span class="kpi-card__valor"><?= number_format((float)$ultim_analisi['pH'], 1, ',', '.') ?></span>
                <span class="kpi-card__etiqueta">pH</span>
                <span class="badge <?= $classe_ph ?>"><?= $text_ph ?></span>
            </div>
        </div>

        <!-- Gràfica principal NPK -->
        <div class="grafica-bloc">
            <h2 class="grafica-bloc__titol">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                Evolució N-P-K
            </h2>
            <div class="grafica-container">
                <canvas id="grafica-npk" height="300"></canvas>
            </div>
        </div>

        <!-- Gràfiques secundàries -->
        <div class="grafiques-grid">
            <div class="grafica-bloc">
                <h3 class="grafica-bloc__titol">
                    <i class="fas fa-tint" aria-hidden="true"></i>
                    pH del Sòl
                </h3>
                <div class="grafica-container">
                    <canvas id="grafica-ph" height="200"></canvas>
                </div>
            </div>
            <div class="grafica-bloc">
                <h3 class="grafica-bloc__titol">
                    <i class="fas fa-leaf" aria-hidden="true"></i>
                    Matèria Orgànica
                </h3>
                <div class="grafica-container">
                    <canvas id="grafica-mo" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Taula detallada -->
        <div class="detall-bloc">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-table" aria-hidden="true"></i>
                Anàlisis Detallades
            </h2>
            <div class="taula-container">
                <table class="taula-simple">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>N (ppm)</th>
                            <th>P (ppm)</th>
                            <th>K (ppm)</th>
                            <th>pH</th>
                            <th>M.O. (%)</th>
                            <th>Cond. (dS/m)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analisis as $a): 
                            [$classe_n, $text_n] = classeNutrient((float)$a['N'], 'N');
                            [$classe_p, $text_p] = classeNutrient((float)$a['P'], 'P');
                            [$classe_k, $text_k] = classeNutrient((float)$a['K'], 'K');
                            [$classe_ph, $text_ph] = classePh((float)$a['pH']);
                        ?>
                            <tr>
                                <td><?= format_data($a['data_analisi']) ?></td>
                                <td>
                                    <?= valorNutrient((float)$a['N']) ?>
                                    <br><span class="badge <?= $classe_n ?>"><?= $text_n ?></span>
                                </td>
                                <td>
                                    <?= valorNutrient((float)$a['P']) ?>
                                    <br><span class="badge <?= $classe_p ?>"><?= $text_p ?></span>
                                </td>
                                <td>
                                    <?= valorNutrient((float)$a['K']) ?>
                                    <br><span class="badge <?= $classe_k ?>"><?= $text_k ?></span>
                                </td>
                                <td>
                                    <?= number_format((float)$a['pH'], 1, ',', '.') ?>
                                    <br><span class="badge <?= $classe_ph ?>"><?= $text_ph ?></span>
                                </td>
                                <td><?= number_format((float)$a['materia_organica'], 1, ',', '.') ?>%</td>
                                <td><?= number_format((float)$a['conductivitat_electrica'], 3, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($sector_sel): ?>
        <div class="info-box">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <strong>No hi ha anàlisis</strong>
            <p>No s'han trobat anàlisis de nutrients per aquest sector i any.</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const analisisData = <?= json_encode($analisis) ?>;
    
    if (analisisData.length === 0) return;
    
    // Preparar dades per a les gràfiques
    const labels = analisisData.map(d => {
        const data = new Date(d.data_analisi);
        return data.toLocaleDateString('ca-ES', { 
            day: '2-digit', 
            month: '2-digit' 
        });
    });
    
    const nValues = analisisData.map(d => parseFloat(d.N));
    const pValues = analisisData.map(d => parseFloat(d.P));
    const kValues = analisisData.map(d => parseFloat(d.K));
    const phValues = analisisData.map(d => parseFloat(d.pH));
    const moValues = analisisData.map(d => parseFloat(d.materia_organica));
    
    // Configuració global
    Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
    
    // Gràfica principal NPK
    new Chart(document.getElementById('grafica-npk'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Nitrogen (N)',
                    data: nValues,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'Fòsfor (P)',
                    data: pValues,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'Potassi (K)',
                    data: kValues,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' ppm';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ppm'
                    }
                }
            }
        }
    });
    
    // Gràfica pH
    new Chart(document.getElementById('grafica-ph'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'pH',
                data: phValues,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    min: 4,
                    max: 9,
                    title: {
                        display: true,
                        text: 'pH'
                    }
                }
            }
        }
    });
    
    // Gràfica Matèria Orgànica
    new Chart(document.getElementById('grafica-mo'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Matèria Orgànica',
                data: moValues,
                borderColor: '#84cc16',
                backgroundColor: 'rgba(132, 204, 22, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Matèria Orgànica: ' + context.parsed.y.toFixed(1) + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '%'
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
