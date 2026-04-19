<?php
/**
 * modules/protocols/nou_protocol.php — Formulari per crear o editar un protocol.
 * POST → processar_protocol.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nou Protocol';
$pagina_activa = 'protocols';

$protocol  = null;
$productes = []; // array de {nom, dosi}
$error_db  = null;
$es_edicio = false;

try {
    $pdo = connectDB();

    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio    = true;
        $titol_pagina = 'Editar Protocol';

        $stmt = $pdo->prepare("SELECT * FROM protocol_tractament WHERE id_protocol = :id");
        $stmt->execute([':id' => (int)$_GET['editar']]);
        $protocol = $stmt->fetch();

        if (!$protocol) {
            set_flash('error', 'Protocol no trobat.');
            header('Location: ' . BASE_URL . 'modules/protocols/protocols.php');
            exit;
        }

        if ($protocol['productes_json']) {
            $decoded = json_decode($protocol['productes_json'], true);
            if (is_array($decoded)) $productes = $decoded;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_protocol.php: ' . $e->getMessage());
    $error_db = 'Error en carregar el formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>"></i>
            <?= $es_edicio ? 'Editar Protocol' : 'Nou Protocol de Tractament' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/protocols/protocols.php" class="boto-secundari">
            <i class="fas fa-arrow-left"></i> Tornar
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error"><i class="fas fa-circle-xmark"></i> <?= e($error_db) ?></div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/protocols/processar_protocol.php"
          class="form-card" novalidate>

        <input type="hidden" name="accio" value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_protocol" value="<?= (int)$protocol['id_protocol'] ?>">
        <?php endif; ?>

        <!-- INFO GENERAL -->
        <fieldset class="form-grup">
            <legend>Informació general</legend>

            <div class="form-camp">
                <label for="nom_protocol">Nom del protocol <span class="obligatori">*</span></label>
                <input type="text" name="nom_protocol" id="nom_protocol" required
                       value="<?= e($protocol['nom_protocol'] ?? '') ?>"
                       placeholder="Ex: Tractament preventiu míldiu floració">
            </div>

            <div class="form-camp">
                <label for="descripcio">Descripció</label>
                <textarea name="descripcio" id="descripcio" rows="3"
                          placeholder="Quan aplicar-lo, objectiu, observacions generals..."><?= e($protocol['descripcio'] ?? '') ?></textarea>
            </div>

            <div class="form-camp">
                <label for="condicions_ambientals">Condicions ambientals d'aplicació</label>
                <input type="text" name="condicions_ambientals" id="condicions_ambientals"
                       value="<?= e($protocol['condicions_ambientals'] ?? '') ?>"
                       placeholder="Ex: Temperatura > 15°C, vent < 3 m/s, sense pluja prevista 24h">
            </div>
        </fieldset>

        <!-- PRODUCTES -->
        <fieldset class="form-grup">
            <legend>
                Productes del protocol
                <span class="text-suau inline-subtle">
                    (afegeix tants com necessitis)
                </span>
            </legend>

            <div id="llista-productes">
                <?php
                $productes_inicials = !empty($productes) ? $productes : [['nom' => '', 'dosi' => '']];
                foreach ($productes_inicials as $i => $prod): ?>
                <div class="producte-fila form-fila form-fila--end-gap form-card--spaced">
                    <div class="form-camp form-camp--grow-2">
                        <label>Nom del producte</label>
                        <input type="text" name="producte_nom[]"
                               value="<?= e($prod['nom'] ?? '') ?>"
                               placeholder="Ex: CobrePlus 50">
                    </div>
                    <div class="form-camp form-camp--grow">
                        <label>Dosi</label>
                        <input type="text" name="producte_dosi[]"
                               value="<?= e($prod['dosi'] ?? '') ?>"
                               placeholder="Ex: 2 kg/ha">
                    </div>
                    <div class="form-camp form-camp--bottom">
                        <button type="button" class="btn-accio btn-accio--eliminar btn-eliminar-producte"
                                title="Eliminar producte">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

                <button type="button" id="btn-afegir-producte" class="boto-secundari form-grup--accio">
                    <i class="fas fa-plus"></i> Afegir producte
                </button>
        </fieldset>

        <div class="form-accions">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Crear protocol' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/protocols/protocols.php" class="boto-secundari">
                Cancel·lar
            </a>
        </div>

    </form>
</div>

<script>
// Afegir fila de producte
document.getElementById('btn-afegir-producte').addEventListener('click', function () {
    const contenidor = document.getElementById('llista-productes');
    const div = document.createElement('div');
    div.className = 'producte-fila form-fila form-fila--end-gap form-card--spaced';
    div.innerHTML = `
        <div class="form-camp form-camp--grow-2">
            <label>Nom del producte</label>
            <input type="text" name="producte_nom[]" placeholder="Ex: HerbiMax 36">
        </div>
        <div class="form-camp form-camp--grow">
            <label>Dosi</label>
            <input type="text" name="producte_dosi[]" placeholder="Ex: 3 L/ha">
        </div>
        <div class="form-camp form-camp--bottom">
            <button type="button" class="btn-accio btn-accio--eliminar btn-eliminar-producte"
                    title="Eliminar producte">
                <i class="fas fa-xmark"></i>
            </button>
        </div>`;
    contenidor.appendChild(div);
    div.querySelector('input').focus();
});

// Eliminar fila de producte (delegació d'events)
document.getElementById('llista-productes').addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-eliminar-producte');
    if (!btn) return;
    const files = this.querySelectorAll('.producte-fila');
    if (files.length <= 1) {
        // Netejem els camps però no eliminem la fila
        btn.closest('.producte-fila').querySelectorAll('input').forEach(i => i.value = '');
        return;
    }
    btn.closest('.producte-fila').remove();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
