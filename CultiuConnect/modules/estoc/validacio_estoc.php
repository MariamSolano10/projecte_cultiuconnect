<?php
/**
 * modules/estoc/validacio_estoc.php
 *
 * Eina de diagnòstic i validació del sistema de gestió d'estoc.
 * Permet verificar el funcionament de la validació d'estoc i notificacions.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina = 'Diagnòstic d\'Estoc';
$pagina_activa = 'estoc';

$productes = [];
$resultats = null;
$error_db = null;

// Obtenir productes per al formulari
try {
    $pdo = connectDB();
    $productes = $pdo->query("
        SELECT id_producte, nom_comercial, unitat_mesura, estoc_actual, estoc_minim
        FROM producte_quimic
        ORDER BY nom_comercial ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] validacio_estoc.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els productes.';
}

// Processar validació
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producte = sanitize_int($_POST['id_producte'] ?? null);
    $quantitat = sanitize_decimal($_POST['quantitat'] ?? null);
    
    if ($id_producte && $quantitat > 0) {
        try {
            $pdo = connectDB();
            
            // Carregar funcions de validació
            require_once __DIR__ . '/../tractaments/processar_tractament_programat.php';
            
            $validacio = validarEstocSuficient($pdo, $id_producte, $quantitat);
            $resultats = $validacio;
            
            // Simular prova de notificació si l'estoc és suficient
            if ($validacio['suficient']) {
                $resultats['notificacio_disponible'] = true;
            }
            
        } catch (Exception $e) {
            error_log('[CultiuConnect] validacio_estoc.php: ' . $e->getMessage());
            $error_db = 'Error en la validació d\'estoc.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-validacio">
    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-stethoscope" aria-hidden="true"></i>
            Diagnòstic d'Estoc
        </h1>
        <p class="descripcio-seccio">
            Eina de diagnòstic per verificar el funcionament del sistema de gestió d'estoc.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="validacio-grid">
        <div class="validacio-form">
            <h2>Verificar Validació d'Estoc</h2>
            <form method="POST" class="formulari-card">
                <div class="form-grup">
                    <label for="id_producte" class="form-label form-label--requerit">
                        Producte
                    </label>
                    <select id="id_producte" name="id_producte" class="form-select" required>
                        <option value="">Selecciona un producte</option>
                        <?php foreach ($productes as $p): ?>
                            <option value="<?= (int)$p['id_producte'] ?>">
                                <?= e($p['nom_comercial']) ?> 
                                (Estoc: <?= formatEstoc((float)$p['estoc_actual'], $p['unitat_mesura']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="quantitat" class="form-label form-label--requerit">
                        Quantitat requerida
                    </label>
                    <input type="number" 
                           id="quantitat" 
                           name="quantitat" 
                           class="form-input" 
                           step="0.01" 
                           min="0.01" 
                           required>
                </div>

                <div class="form-botons">
                    <button type="submit" class="boto-principal">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        Verificar Disponibilitat
                    </button>
                </div>
            </form>
        </div>

        <?php if ($resultats): ?>
            <div class="validacio-resultats">
                <h2>Resultats del Diagnòstic</h2>
                <div class="resultats-card">
                    <div class="resultat-item">
                        <strong>Producte:</strong> <?= e($resultats['nom_producte']) ?>
                    </div>
                    <div class="resultat-item">
                        <strong>Estoc actual:</strong> 
                        <?= formatEstoc($resultats['estoc_actual'], $resultats['unitat_mesura']) ?>
                    </div>
                    <div class="resultat-item">
                        <strong>Estoc mínim:</strong> 
                        <?= formatEstoc($resultats['estoc_minim'], $resultats['unitat_mesura']) ?>
                    </div>
                    <div class="resultat-item">
                        <strong>Quantitat requerida:</strong> 
                        <?= formatEstoc($quantitat, $resultats['unitat_mesura']) ?>
                    </div>
                    <div class="resultat-item">
                        <strong>Estat:</strong> 
                        <span class="badge <?= $resultats['suficient'] ? 'badge--verd' : 'badge--vermell' ?>">
                            <?= $resultats['suficient'] ? 'Estoc suficient' : 'Estoc insuficient' ?>
                        </span>
                    </div>
                    
                    <?php if (isset($resultats['notificacio_disponible'])): ?>
                        <div class="resultat-item">
                            <strong>Sistema de notificacions:</strong> 
                            <span class="badge badge--blau">Operatiu</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
