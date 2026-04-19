<?php
/**
 * modules/dashboard/widget_calendari.php
 *
 * Widget de calendari reduït per al dashboard principal.
 * Mostra els esdeveniments propers en format compact.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$esdeveniments = [];
$error_db = null;

try {
    $pdo = connectDB();
    
    // Obtenir esdeveniments dels propers 30 dies
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+30 days'));
    
    // Tractaments programats
    $sql = "
        SELECT 'tractament' AS tipus, tp.data_prevista AS data, 
               CONCAT(UPPER(SUBSTRING(tp.tipus, 1, 1)), LOWER(SUBSTRING(tp.tipus, 2))) AS descripcio,
               s.nom AS sector, tp.id_programat AS id
        FROM tractament_programat tp
        LEFT JOIN sector s ON tp.id_sector = s.id_sector
        WHERE tp.estat = 'pendent' AND tp.data_prevista BETWEEN :start AND :end
        ORDER BY tp.data_prevista ASC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $esdeveniments = array_merge($esdeveniments, $stmt->fetchAll());
    
    // Collites
    $sql = "
        SELECT 'collita' AS tipus, c.data_inici AS data, 
               CONCAT('Collita: ', v.nom_varietat) AS descripcio,
               s.nom AS sector, c.id_collita AS id
        FROM collita c
        JOIN plantacio p ON c.id_plantacio = p.id_plantacio
        JOIN varietat v ON p.id_varietat = v.id_varietat
        JOIN sector s ON p.id_sector = s.id_sector
        WHERE DATE(c.data_inici) BETWEEN :start AND :end
        ORDER BY c.data_inici ASC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $esdeveniments = array_merge($esdeveniments, $stmt->fetchAll());
    
    // Tasques de personal
    $sql = "
        SELECT 'tasca' AS tipus, t.data_inici_prevista AS data, 
               t.tipus AS descripcio,
               s.nom AS sector, t.id_tasca AS id
        FROM tasca t
        LEFT JOIN sector s ON t.id_sector = s.id_sector
        WHERE t.estat IN ('pendent', 'en_proces') 
          AND t.data_inici_prevista BETWEEN :start AND :end
        ORDER BY t.data_inici_prevista ASC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $esdeveniments = array_merge($esdeveniments, $stmt->fetchAll());
    
    // Ordenar per data
    usort($esdeveniments, function($a, $b) {
        return strtotime($a['data']) - strtotime($b['data']);
    });
    
    // Limitar a 8 esdeveniments totals
    $esdeveniments = array_slice($esdeveniments, 0, 8);
    
} catch (Exception $e) {
    error_log('[CultiuConnect] widget_calendari.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els esdeveniments.';
}

function getIconaEsdeveniment($tipus) {
    $icones = [
        'tractament' => 'fa-spray-can',
        'collita' => 'fa-apple-alt',
        'tasca' => 'fa-tasks',
        'fenologic' => 'fa-seedling',
        'monitoratge' => 'fa-bug',
        'analisis_sol' => 'fa-vial'
    ];
    return $icones[$tipus] ?? 'fa-calendar';
}

function getColorEsdeveniment($tipus) {
    $colors = [
        'tractament' => '#e74c3c',
        'collita' => '#8e44ad',
        'tasca' => '#27ae60',
        'fenologic' => '#16a085',
        'monitoratge' => '#d35400',
        'analisis_sol' => '#8e44ad'
    ];
    return $colors[$tipus] ?? '#95a5a6';
}

function getDiesRestants($data) {
    $avui = new DateTime('today');
    $data_event = new DateTime($data);
    $diferencia = $avui->diff($data_event);
    
    if ($diferencia->days == 0) return 'Avui';
    if ($diferencia->days == 1) return 'Demà';
    if ($diferencia->days < 7) return 'En ' . $diferencia->days . ' dies';
    
    return 'En ' . $diferencia->days . ' dies';
}
?>

<div class="widget-calendari">
    <div class="widget-header">
        <h3 class="widget-title">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            Calendari
        </h3>
        <div class="widget-actions">
            <a href="<?= BASE_URL ?>modules/calendari/calendari.php" class="boto-widget">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                <span class="sr-only">Veure calendari complet</span>
            </a>
        </div>
    </div>
    
    <div class="widget-content">
        <?php if ($error_db): ?>
            <div class="widget-error">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <?= e($error_db) ?>
            </div>
        <?php elseif (empty($esdeveniments)): ?>
            <div class="widget-buit">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                <p>No hi ha esdeveniments propers</p>
            </div>
        <?php else: ?>
            <div class="esdeveniments-lista">
                <?php foreach ($esdeveniments as $esdeveniment): ?>
                    <div class="esdeveniment-item">
                        <div class="esdeveniment-icona" 
                             data-color="<?= e(getColorEsdeveniment($esdeveniment['tipus'])) ?>">
                            <i class="fas <?= getIconaEsdeveniment($esdeveniment['tipus']) ?>" 
                               aria-hidden="true"></i>
                        </div>
                        <div class="esdeveniment-info">
                            <div class="esdeveniment-data">
                                <?= format_data($esdeveniment['data'], curta: true) ?>
                                <span class="esdeveniment-dies">
                                    <?= getDiesRestants($esdeveniment['data']) ?>
                                </span>
                            </div>
                            <div class="esdeveniment-titol">
                                <?= e($esdeveniment['descripcio']) ?>
                            </div>
                            <?php if ($esdeveniment['sector']): ?>
                                <div class="esdeveniment-sector">
                                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                    <?= e($esdeveniment['sector']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.esdeveniment-icona[data-color]').forEach(el => {
    el.style.backgroundColor = el.dataset.color;
});
</script>
