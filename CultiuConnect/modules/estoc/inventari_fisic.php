<?php
/**
 * modules/estoc/inventari_fisic.php
 *
 * Interfície per realitzar inventari físic del magatzem.
 * Permet introduir quantitats reals comptades a mà, comparar-les amb l'estoc teòric
 * i generar regularitzacions automàtiques per ajustar les discrepàncies.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Inventari Físic';
$pagina_activa = 'inventari';

$productes     = [];
$discrepancies = [];
$error_db      = null;
$inventari_real = [];

// Processament POST per guardar inventari
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accio']) && $_POST['accio'] === 'guardar_inventari') {
    try {
        $pdo = connectDB();
        $pdo->beginTransaction();

        $data_inventari = sanitize($_POST['data_inventari'] ?? date('Y-m-d'));
        $observacions   = sanitize($_POST['observacions'] ?? '');

        // Recollir quantitats reals per cada producte
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'quantitat_real_')) {
                $id_producte = (int)substr($key, 16); // Eliminar 'quantitat_real_'
                $quantitat_real = sanitize_decimal($value);

                if ($quantitat_real !== null && $quantitat_real >= 0) {
                    // Obtenir estoc actual teòric
                    $stmt = $pdo->prepare("SELECT estoc_actual FROM producte_quimic WHERE id_producte = ?");
                    $stmt->execute([$id_producte]);
                    $producte = $stmt->fetch();

                    if ($producte) {
                        $estoc_teoric = (float)$producte['estoc_actual'];
                        $diferencia = $quantitat_real - $estoc_teoric;

                        // Si hi ha discrepància, crear regularització automàtica
                        if (abs($diferencia) > 0.01) { // Marge de 0.01 per evitar decimals petits
                            // Inserir moviment d'estoc de regularització
                            $stmt_mov = $pdo->prepare("
                                INSERT INTO moviment_estoc
                                    (id_producte, tipus_moviment, quantitat, data_moviment, motiu)
                                VALUES
                                    (:id_producte, 'Regularitzacio', :quantitat, :data_moviment, :motiu)
                            ");
                            $stmt_mov->execute([
                                ':id_producte' => $id_producte,
                                ':quantitat'   => abs($diferencia),
                                ':data_moviment' => $data_inventari,
                                ':motiu' => sprintf(
                                    'Regularització inventari físic %s. Teòric: %.2f, Real: %.2f, Diferència: %+.2f',
                                    $data_inventari,
                                    $estoc_teoric,
                                    $quantitat_real,
                                    $diferencia
                                )
                            ]);

                            // Actualitzar estoc actual
                            $stmt_upd = $pdo->prepare("
                                UPDATE producte_quimic 
                                SET estoc_actual = :nou_estoc 
                                WHERE id_producte = :id_producte
                            ");
                            $stmt_upd->execute([
                                ':nou_estoc' => $quantitat_real,
                                ':id_producte' => $id_producte
                            ]);

                            // Guardar registre d'inventari per històric
                            $stmt_inv = $pdo->prepare("
                                INSERT INTO inventari_fisic_registre
                                    (id_producte, data_inventari, estoc_teoric, estoc_real, diferencia, observacions)
                                VALUES
                                    (:id_producte, :data_inventari, :estoc_teoric, :estoc_real, :diferencia, :observacions)
                            ");
                            $stmt_inv->execute([
                                ':id_producte' => $id_producte,
                                ':data_inventari' => $data_inventari,
                                ':estoc_teoric' => $estoc_teoric,
                                ':estoc_real' => $quantitat_real,
                                ':diferencia' => $diferencia,
                                ':observacions' => $observacions
                            ]);
                        }
                    }
                }
            }
        }

        $pdo->commit();

        set_flash('success', 'Inventari físic guardat correctament. Les discrepàncies s\'han regularitzat automàticament.');
        header('Location: ' . BASE_URL . 'modules/estoc/inventari_fisic.php');
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[CultiuConnect] inventari_fisic.php (guardar): ' . $e->getMessage());
        $error_db = 'Error guardant l\'inventari físic. No s\'ha realitzat cap canvi.';
    }
}

try {
    $pdo = connectDB();

    // Carregar productes amb estoc actual
    $productes = $pdo->query("
        SELECT 
            id_producte, 
            nom_comercial, 
            tipus,
            estoc_actual, 
            estoc_minim, 
            unitat_mesura,
            (SELECT MIN(ie.data_caducitat)
             FROM inventari_estoc ie
             WHERE ie.id_producte = p.id_producte
               AND ie.quantitat_disponible > 0) AS proxima_caducitat
        FROM producte_quimic p
        WHERE estoc_actual > 0 OR estoc_minim > 0
        ORDER BY tipus ASC, nom_comercial ASC
    ")->fetchAll();

    // Carregar últims inventaris realitzats
    $stmt_hist = $pdo->query("
        SELECT 
            data_inventari,
            COUNT(*) AS num_productes,
            SUM(CASE WHEN diferencia != 0 THEN 1 ELSE 0 END) AS discreancies,
            SUM(ABS(diferencia)) AS total_diferencia
        FROM inventari_fisic_registre
        GROUP BY data_inventari
        ORDER BY data_inventari DESC
        LIMIT 5
    ");
    $historial_inventaris = $stmt_hist->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] inventari_fisic.php (càrrega): ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades de l\'inventari.';
}

function classeDiscrepancia(float $diferencia): array
{
    $abs_diff = abs($diferencia);
    
    if ($abs_diff <= 0.01) {
        return ['badge--verd', 'Correcte'];
    } elseif ($abs_diff <= 0.1) {
        return ['badge--groc', 'Petita discrepància'];
    } elseif ($abs_diff <= 1.0) {
        return ['badge--taronja', 'Discrepància moderada'];
    } else {
        return ['badge--vermell', 'Discrepància important'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-estoc">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-clipboard-check" aria-hidden="true"></i>
            Inventari Físic
        </h1>
        <p class="descripcio-seccio">
            Compta físicament els productes del magatzem i introdueix les quantitats reals.
            El sistema detectarà discrepàncies amb l'estoc teòric i generarà regularitzacions automàtiques.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Formulari d'inventari físic -->
    <form method="POST" class="formulari-card" id="formulari-inventari">
        <input type="hidden" name="accio" value="guardar_inventari">

        <!-- Capçalera del formulari -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-calendar-day"></i> Dades de l'inventari
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_inventari" class="form-label form-label--requerit">
                        Data de l'inventari
                    </label>
                    <input type="date"
                           id="data_inventari"
                           name="data_inventari"
                           class="form-input camp-requerit"
                           value="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="form-grup">
                    <label for="observacions" class="form-label">
                        Observacions (opcional)
                    </label>
                    <input type="text"
                           id="observacions"
                           name="observacions"
                           class="form-input"
                           placeholder="Ex: Inventari trimestral, canvi de responsable, etc."
                           maxlength="255">
                </div>
            </div>
        </fieldset>

        <!-- Taula de productes per comptar -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-boxes"></i> Comptatge de productes
            </legend>

            <div class="taula-container taula-container--max">
                <table class="taula-simple taula-simple--compacta">
                    <thead>
                        <tr>
                            <th>Producte</th>
                            <th>Tipus</th>
                            <th class="text-dreta">Estoc teòric</th>
                            <th class="text-dreta">Quantitat real</th>
                            <th class="text-dreta">Diferència</th>
                            <th>Estat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productes)): ?>
                            <tr>
                                <td colspan="6" class="sense-dades">
                                    No hi ha productes a l'inventari.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productes as $p): 
                                $unitat = $p['unitat_mesura'] ?? '';
                                $estoc_teoric = (float)$p['estoc_actual'];
                            ?>
                                <tr data-producte-id="<?= (int)$p['id_producte'] ?>">
                                    <td>
                                        <strong><?= e($p['nom_comercial']) ?></strong>
                                        <?php if ($p['proxima_caducitat']): ?>
                                            <br><small class="text-suau">Caduca: <?= format_data($p['proxima_caducitat'], curta: true) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= match($p['tipus']) {
                                            'Fitosanitari' => 'badge--vermell',
                                            'Fertilitzant' => 'badge--verd',
                                            'Herbicida'    => 'badge--taronja',
                                            default        => 'badge--gris',
                                        } ?>">
                                            <?= e($p['tipus']) ?>
                                        </span>
                                    </td>
                                    <td class="text-dreta">
                                        <strong><?= number_format($estoc_teoric, 2, ',', '.') ?></strong>
                                        <br><small><?= e($unitat) ?></small>
                                    </td>
                                    <td class="text-dreta">
                                        <input type="number"
                                               name="quantitat_real_<?= (int)$p['id_producte'] ?>"
                                               class="form-input form-input--petit quantitat-real"
                                               data-teoric="<?= $estoc_teoric ?>"
                                               step="0.01"
                                               min="0"
                                               placeholder="0.00"
                                               class="input--xs">
                                        <br><small><?= e($unitat) ?></small>
                                    </td>
                                    <td class="text-dreta diferencia-cell">
                                        <span class="diferencia-valor">---</span>
                                        <br><small class="diferencia-unitat"><?= e($unitat) ?></small>
                                    </td>
                                    <td class="estat-cell">
                                        <span class="badge badge--gris">Pendent</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Resum de discrepàncies -->
            <div class="resum-discrepancies">
                <h4 class="resum-discrepancies__titol">Resum de discrepàncies</h4>
                <div class="kpi-grid kpi-grid--petit">
                    <div class="kpi-card">
                        <div class="kpi-card__icona">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <span class="kpi-card__valor" id="correctes">0</span>
                        <span class="kpi-card__etiqueta">Correctes</span>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icona">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <span class="kpi-card__valor" id="discreancies">0</span>
                        <span class="kpi-card__etiqueta">Discrepàncies</span>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icona">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <span class="kpi-card__valor" id="diferencia-total">0.00</span>
                        <span class="kpi-card__etiqueta">Diferència total</span>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                Guardar Inventari i Regularitzar
            </button>
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>
    </form>

    <!-- Historial d'inventaris -->
    <?php if (!empty($historial_inventaris)): ?>
        <div class="detall-bloc detall-bloc--mt-l">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-history" aria-hidden="true"></i>
                Últims inventaris realitzats
            </h2>

            <table class="taula-simple taula-simple--compacta">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-dreta">Productes</th>
                        <th class="text-dreta">Discrepàncies</th>
                        <th class="text-dreta">Diferència total</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial_inventaris as $hist): ?>
                        <tr>
                            <td><?= format_data($hist['data_inventari']) ?></td>
                            <td class="text-dreta"><?= (int)$hist['num_productes'] ?></td>
                            <td class="text-dreta">
                                <span class="badge <?= (int)$hist['discreancies'] > 0 ? 'badge--taronja' : 'badge--verd' ?>">
                                    <?= (int)$hist['discreancies'] ?>
                                </span>
                            </td>
                            <td class="text-dreta"><?= number_format((float)$hist['total_diferencia'], 2, ',', '.') ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>modules/estoc/detall_inventari.php?data=<?= e($hist['data_inventari']) ?>"
                                   class="btn-accio btn-accio--veure"
                                   title="Veure detall d'aquest inventari">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- JavaScript per càlcul automàtic de diferències -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formulari = document.getElementById('formulari-inventari');
    const inputsQuantitat = document.querySelectorAll('.quantitat-real');
    const resumDiscrepancies = document.querySelector('.resum-discrepancies');
    
    function calcularDiferencia(input) {
        const fila = input.closest('tr');
        const teoric = parseFloat(input.dataset.teoric);
        const real = parseFloat(input.value) || 0;
        const diferencia = real - teoric;
        
        const diferenciaCell = fila.querySelector('.diferencia-valor');
        const estatCell = fila.querySelector('.estat-cell .badge');
        const unitatCell = fila.querySelector('.diferencia-unitat');
        
        // Mostrar diferència
        diferenciaCell.textContent = (diferencia >= 0 ? '+' : '') + diferencia.toFixed(2);
        unitatCell.textContent = input.closest('tr').querySelector('td:nth-child(4) small').textContent;
        
        // Determinar estat
        const absDiff = Math.abs(diferencia);
        let classe, text;
        if (absDiff <= 0.01) {
            classe = 'badge--verd';
            text = 'Correcte';
        } else if (absDiff <= 0.1) {
            classe = 'badge--groc';
            text = 'Petita discrepància';
        } else if (absDiff <= 1.0) {
            classe = 'badge--taronja';
            text = 'Discrepància moderada';
        } else {
            classe = 'badge--vermell';
            text = 'Discrepància important';
        }
        
        estatCell.className = 'badge ' + classe;
        estatCell.textContent = text;
        
        // Actualitzar resum
        actualizarResum();
    }
    
    function actualizarResum() {
        const files = document.querySelectorAll('tbody tr[data-producte-id]');
        let correctes = 0, discreancies = 0, diferenciaTotal = 0;
        
        files.forEach(fila => {
            const diferenciaCell = fila.querySelector('.diferencia-valor');
            const diferencia = parseFloat(diferenciaCell.textContent) || 0;
            
            if (Math.abs(diferencia) <= 0.01) {
                correctes++;
            } else {
                discreancies++;
                diferenciaTotal += Math.abs(diferencia);
            }
        });
        
        document.getElementById('correctes').textContent = correctes;
        document.getElementById('discreancies').textContent = discreancies;
        document.getElementById('diferencia-total').textContent = diferenciaTotal.toFixed(2);
        
        // Mostrar resum si hi ha dades
        resumDiscrepancies.style.display = 'block';
    }
    
    // Afegir events als inputs
    inputsQuantitat.forEach(input => {
        input.addEventListener('input', function() {
            calcularDiferencia(this);
        });
    });
    
    // Prevenir enviament si no hi ha valors
    formulari.addEventListener('submit', function(e) {
        const inputsAmbValor = document.querySelectorAll('.quantitat-real[value]:not([value=""])');
        if (inputsAmbValor.length === 0) {
            e.preventDefault();
            alert('Has d\'introduir com a mínim una quantitat real per poder guardar l\'inventari.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
