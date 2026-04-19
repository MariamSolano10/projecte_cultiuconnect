<?php
/**
 * modules/monitoratge/monitoratge.php — Formulari per registrar una nova observació.
 *
 * Camps que coincideixen amb monitoratge_plaga:
 *   id_sector, data_observacio, tipus_problema,
 *   descripcio_breu, nivell_poblacio, llindar_intervencio_assolit
 *
 * NOTA: element_observat NO existeix a BD — eliminat.
 *       El nom de la plaga/element s'inclou dins descripcio_breu.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$sectors = [];
$error_db = null;
$sector_pre = sanitize_int($_GET['sector_id'] ?? null);

try {
    $pdo = connectDB();

    $sectors = $pdo->query("
        SELECT
            s.id_sector,
            s.nom,
            COALESCE(v.nom_varietat, '—') AS varietat,
            COALESCE(
                (SELECT SUM(p.superficie_ha)
                 FROM parcela p
                 JOIN parcela_sector ps ON p.id_parcela = ps.id_parcela
                 WHERE ps.id_sector = s.id_sector),
                0
            ) AS superficie_ha
        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector = s.id_sector
                               AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat = pl.id_varietat
        ORDER BY s.nom ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] monitoratge.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els sectors.';
}

$titol_pagina = 'Nou Monitoratge';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-bug" aria-hidden="true"></i>
            Registre de Monitoratge i Plagues
        </h1>
        <p class="descripcio-seccio">
            Registra una nova observació de plaga, malaltia, deficiència o mala herba.
            Si el llindar d'intervenció s'ha assolit, s'activarà una alerta al panell.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="processar_monitoratge.php" class="formulari-card" novalidate>

        <!-- ================================================
             BLOC 1: On i quan
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-pin" aria-hidden="true"></i>
                Ubicació i data
            </legend>

            <div class="form-grup">
                <label for="id_sector" class="form-label form-label--requerit">
                    Sector d'observació
                </label>
                <select id="id_sector" name="id_sector" class="form-select camp-requerit" data-etiqueta="El sector"
                    required>
                    <option value="0">— Selecciona un sector —</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int) $s['id_sector'] ?>" <?= ($sector_pre === (int) $s['id_sector']) ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                            <?php if ($s['varietat'] !== '—'): ?>
                                — <?= e($s['varietat']) ?>
                            <?php endif; ?>
                            (<?= format_ha((float) $s['superficie_ha']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_observacio" class="form-label form-label--requerit">
                        Data de l'observació
                    </label>
                    <input type="date" id="data_observacio" name="data_observacio" class="form-input camp-requerit"
                        data-etiqueta="La data d'observació" data-no-futur value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-grup">
                    <label for="hora_observacio" class="form-label">
                        Hora (opcional)
                    </label>
                    <input type="time" id="hora_observacio" name="hora_observacio" class="form-input"
                        value="<?= date('H:i') ?>">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Problema detectat
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                Problema detectat
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus_problema" class="form-label form-label--requerit">
                        Tipus de problema
                    </label>
                    <select id="tipus_problema" name="tipus_problema" class="form-select camp-requerit"
                        data-etiqueta="El tipus de problema" required>
                        <option value="0">— Selecciona el tipus —</option>
                        <option value="Plaga">Plaga (insecte, àcar, nematode)</option>
                        <option value="Malaltia">Malaltia (fong, virus, bactèria)</option>
                        <option value="Deficiencia">Deficiència nutricional / estrès</option>
                        <option value="Mala Herba">Mala herba</option>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="nivell_poblacio" class="form-label">
                        Nivell de població / % danys
                    </label>
                    <input type="number" id="nivell_poblacio" name="nivell_poblacio" class="form-input"
                        data-tipus="decimal" step="0.01" min="0" max="100" placeholder="Ex: 15.50">
                    <span class="form-ajuda">Deixa en blanc si no es pot quantificar.</span>
                </div>
            </div>

            <div class="form-grup">
                <label for="descripcio_breu" class="form-label form-label--requerit">
                    Descripció de l'observació
                </label>
                <textarea id="descripcio_breu" name="descripcio_breu" class="form-textarea camp-requerit"
                    data-etiqueta="La descripció" rows="3" maxlength="255"
                    placeholder="Ex: Mosca blanca a fulles baixes. 3 trips/trampa. Afecta ~5% de la parcel·la."
                    required></textarea>
                <span class="form-ajuda form-ajuda--contador" aria-live="polite">
                    <span id="comptador-desc">0</span> / 255 caràcters
                </span>
            </div>

            <div class="form-grup">
                <div class="checkbox-card">
                    <input type="checkbox" id="llindar_intervencio_assolit" name="llindar_intervencio_assolit" value="1"
                        class="checkbox-input">
                    <label for="llindar_intervencio_assolit" class="checkbox-label">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        Llindar d'intervenció assolit
                        <span class="checkbox-sublabel">
                            Marca si cal un tractament immediat.
                            Generarà una alerta al panell de control.
                        </span>
                    </label>
                </div>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Registrar Observació
            </button>
            <a href="<?= BASE_URL ?>modules/monitoratge/historial_monitoratge.php" class="boto-secundari">
                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                Veure Historial
            </a>
        </div>

    </form>

</div>

<script>
    (function () {
        'use strict';
        const textarea = document.getElementById('descripcio_breu');
        const comptador = document.getElementById('comptador-desc');
        if (!textarea || !comptador) return;
        const actualitzar = () => { comptador.textContent = textarea.value.length; };
        textarea.addEventListener('input', actualitzar);
        actualitzar();
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>