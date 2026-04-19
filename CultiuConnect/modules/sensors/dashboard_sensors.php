<?php
/**
 * modules/sensors/dashboard_sensors.php
 *
 * Dashboard de sensors per visualitzar lectures en temps real i històriques.
 * Mostra gràfiques d'evolució amb Chart.js i estat actual dels sensors.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Dashboard de Sensors';
$pagina_activa = 'sensors';

$sensors      = [];
$lectures_recents = [];
$estadistiques = [];
$error_db     = null;

try {
    $pdo = connectDB();

    // Carregar sensors actius amb la seva última lectura
    $sensors = $pdo->query("
        SELECT 
            s.id_sensor,
            s.tipus,
            s.estat,
            s.protocol_comunicacio,
            s.id_sector,
            sec.nom AS nom_sector,
            ls.valor AS ultima_lectura,
            ls.unitat,
            ls.data_hora AS ultima_lectura_data
        FROM sensor s
        LEFT JOIN sector sec ON sec.id_sector = s.id_sector
        LEFT JOIN lectura_sensor ls ON ls.id_lectura = (
            SELECT id_lectura 
            FROM lectura_sensor 
            WHERE id_sensor = s.id_sensor 
            ORDER BY data_hora DESC 
            LIMIT 1
        )
        WHERE s.estat = 'actiu'
        ORDER BY s.tipus ASC, sec.nom ASC
    ")->fetchAll();

    // Estadístiques generals
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_sensors,
            SUM(CASE WHEN estat = 'actiu' THEN 1 ELSE 0 END) AS sensors_actius,
            SUM(CASE WHEN estat = 'inactiu' THEN 1 ELSE 0 END) AS sensors_inactius,
            SUM(CASE WHEN estat = 'avaria' THEN 1 ELSE 0 END) AS sensors_averia
        FROM sensor
    ");
    $estadistiques = $stmt->fetch();

    // Lectures recents per a gràfiques (últimes 24h)
    $stmt = $pdo->query("
        SELECT 
            s.id_sensor,
            s.tipus,
            s.id_sector,
            sec.nom AS nom_sector,
            ls.valor,
            ls.unitat,
            ls.data_hora
        FROM lectura_sensor ls
        JOIN sensor s ON s.id_sensor = ls.id_sensor
        LEFT JOIN sector sec ON sec.id_sector = s.id_sector
        WHERE ls.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY ls.data_hora DESC
        LIMIT 1000
    ");
    $lectures_recents = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] dashboard_sensors.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades dels sensors.';
}

function classeTipusSensor($tipus): array
{
    return match($tipus) {
        'humitat_sol' => ['badge--blau', 'fa-droplet', 'Humitat Sòl'],
        'conductivitat' => ['badge--taronja', 'fa-bolt', 'Conductivitat'],
        'temperatura_sol' => ['badge--vermell', 'fa-thermometer-half', 'Temp. Sòl'],
        'temperatura_ambient' => ['badge--groc', 'fa-temperature-high', 'Temp. Ambient'],
        'pluja' => ['badge--cyan', 'fa-cloud-rain', 'Pluja'],
        'trampa_plaga' => ['badge--verd', 'fa-bug', 'Trampa Plaga'],
        default => ['badge--gris', 'fa-microchip', 'Desconegut']
    };
}

function classeEstatSensor($estat): array
{
    return match($estat) {
        'actiu' => ['badge--verd', 'Actiu'],
        'inactiu' => ['badge--groc', 'Inactiu'],
        'avaria' => ['badge--vermell', 'Avària'],
        default => ['badge--gris', 'Desconegut']
    };
}

// Agrupar lectures per sensor per a gràfiques
$lectures_per_sensor = [];
foreach ($lectures_recents as $lectura) {
    $lectures_per_sensor[$lectura['id_sensor']][] = $lectura;
}

$css_addicional = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-dashboard-sensors">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-satellite-dish" aria-hidden="true"></i>
            Dashboard de Sensors
        </h1>
        <p class="descripcio-seccio">
            Monitoratge en temps real de sensors agrícoles. Visualitza lectures,
            tendències i estat dels dispositius desplegats a l'explotació.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Estadístiques generals -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-microchip"></i>
            </div>
            <span class="kpi-card__valor"><?= (int)$estadistiques['total_sensors'] ?></span>
            <span class="kpi-card__etiqueta">Total Sensors</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-check-circle"></i>
            </div>
            <span class="kpi-card__valor"><?= (int)$estadistiques['sensors_actius'] ?></span>
            <span class="kpi-card__etiqueta">Actius</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-pause-circle"></i>
            </div>
            <span class="kpi-card__valor"><?= (int)$estadistiques['sensors_inactius'] ?></span>
            <span class="kpi-card__etiqueta">Inactius</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <span class="kpi-card__valor"><?= (int)$estadistiques['sensors_averia'] ?></span>
            <span class="kpi-card__etiqueta">En Avària</span>
        </div>
    </div>

    <!-- Grid de sensors actius -->
    <div class="sensors-grid">
        <?php foreach ($sensors as $sensor): 
            [$classe_tipus, $icona_tipus, $nom_tipus] = classeTipusSensor($sensor['tipus']);
            [$classe_estat, $text_estat] = classeEstatSensor($sensor['estat']);
        ?>
            <div class="sensor-card" data-sensor-id="<?= (int)$sensor['id_sensor'] ?>">
                <div class="sensor-card__cap">
                    <div class="sensor-info">
                        <div class="sensor-tipus">
                            <span class="badge <?= $classe_tipus ?>">
                                <i class="fas <?= $icona_tipus ?>"></i>
                                <?= $nom_tipus ?>
                            </span>
                        </div>
                        <div class="sensor-sector">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= e($sensor['nom_sector'] ?? 'Sense sector') ?>
                        </div>
                    </div>
                    <div class="sensor-estat">
                        <span class="badge <?= $classe_estat ?>">
                            <?= $text_estat ?>
                        </span>
                    </div>
                </div>

                <div class="sensor-lectura">
                    <?php if ($sensor['ultima_lectura']): ?>
                        <div class="valor-actual">
                            <span class="valor">
                                <?= number_format((float)$sensor['ultima_lectura'], 2, ',', '.') ?>
                            </span>
                            <span class="unitat"><?= e($sensor['unitat']) ?></span>
                        </div>
                        <div class="data-lectura">
                            <i class="fas fa-clock"></i>
                            <?= format_data($sensor['ultima_lectura_data'], curta: true) ?>
                            <br>
                            <small><?= date('H:i', strtotime($sensor['ultima_lectura_data'])) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="sense-dades">
                            <i class="fas fa-exclamation-circle"></i>
                            Sense lectures
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sensor-grafica">
                    <canvas id="grafica-<?= (int)$sensor['id_sensor'] ?>" height="80"></canvas>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($sensors)): ?>
        <div class="info-box">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <strong>No hi ha sensors actius</strong>
            <p>No s'han trobat sensors actius al sistema. Configura'n alguns per veure les lectures aquí.</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dades de sensors per a gràfiques
    const sensorsData = <?= json_encode($lectures_per_sensor) ?>;
    
    // Configuració global per a totes les gràfiques
    Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#666';
    
    // Crear gràfica per cada sensor
    Object.keys(sensorsData).forEach(sensorId => {
        const lectures = sensorsData[sensorId];
        if (lectures.length < 2) return;
        
        const canvas = document.getElementById('grafica-' + sensorId);
        if (!canvas) return;
        
        // Preparar dades per a la gràfica
        const labels = [];
        const values = [];
        
        lectures.reverse().forEach(lectura => {
            const data = new Date(lectura.data_hora);
            labels.push(data.toLocaleTimeString('ca-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            }));
            values.push(parseFloat(lectura.valor));
        });
        
        // Determinar color segons tipus de sensor
        const tipus = lectures[0].tipus;
        let borderColor = '#666';
        let backgroundColor = 'rgba(102, 102, 102, 0.1)';
        
        switch(tipus) {
            case 'humitat_sol':
                borderColor = '#3b82f6';
                backgroundColor = 'rgba(59, 130, 246, 0.1)';
                break;
            case 'temperatura_sol':
            case 'temperatura_ambient':
                borderColor = '#ef4444';
                backgroundColor = 'rgba(239, 68, 68, 0.1)';
                break;
            case 'pluja':
                borderColor = '#06b6d4';
                backgroundColor = 'rgba(6, 182, 212, 0.1)';
                break;
            case 'conductivitat':
                borderColor = '#f97316';
                backgroundColor = 'rgba(249, 115, 22, 0.1)';
                break;
            case 'trampa_plaga':
                borderColor = '#10b981';
                backgroundColor = 'rgba(16, 185, 129, 0.1)';
                break;
        }
        
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    borderColor: borderColor,
                    backgroundColor: backgroundColor,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                    pointHoverRadius: 4
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
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const unitat = lectures[0].unitat || '';
                                return 'Valor: ' + context.parsed.y.toFixed(2) + ' ' + unitat;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        display: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    });
    
    // Auto-recarregar cada 5 minuts
    setInterval(function() {
        location.reload();
    }, 300000);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
