<?php
/**
 * modules/personal/nou_permis.php — Sol·licitud de permís/absència.
 *
 * Crea un registre a `permis_absencia` amb aprovat = 0 (pendent).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();
$usuari = usuari_actiu();

function usuari_treballador_id(PDO $pdo, int $usuari_id): ?int
{
    $stmt = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
    $stmt->execute([':id' => $usuari_id]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int)$id : null;
}

function es_gestor(?array $usuari): bool
{
    $rol = strtolower((string)($usuari['rol'] ?? 'operari'));
    return in_array($rol, ['admin', 'tecnic', 'responsable'], true);
}

$titol_pagina  = 'Sol·licitar permís';
$pagina_activa = 'permisos';

$error_db = null;
$treballadors = [];
$id_treballador_fix = null;

try {
    $pdo = connectDB();

    // Si l'usuari està vinculat a un treballador, només pot sol·licitar per ell mateix
    $id_treballador_fix = $usuari ? usuari_treballador_id($pdo, (int)$usuari['id']) : null;

    if (!$id_treballador_fix && es_gestor($usuari)) {
        $treballadors = $pdo->query("
            SELECT id_treballador, CONCAT(nom, ' ', COALESCE(cognoms, '')) AS nom_complet
            FROM treballador
            WHERE estat = 'actiu'
            ORDER BY cognoms ASC, nom ASC
        ")->fetchAll();
    } elseif (!$id_treballador_fix) {
        $error_db = 'El teu usuari no està vinculat a cap treballador. Contacta amb un responsable.';
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_permis.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-plane-departure" aria-hidden="true"></i>
            Sol·licitar permís / absència
        </h1>
        <p class="descripcio-seccio">
            Envia una sol·licitud de vacances, permís o baixa perquè sigui revisada per un responsable.
        </p>
        <div class="botons-accions">
            <a href="<?= BASE_URL ?>modules/personal/permisos.php" class="boto-secundari">
                <i class="fas fa-list" aria-hidden="true"></i> Veure sol·licituds
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/personal/processar_permis.php"
          class="formulari-card"
          enctype="multipart/form-data"
          novalidate>

        <input type="hidden" name="accio" value="crear">

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-user" aria-hidden="true"></i> Persona i tipus
            </legend>

            <?php if ($id_treballador_fix): ?>
                <input type="hidden" name="id_treballador" value="<?= (int)$id_treballador_fix ?>">
                <div class="flash flash--info" role="note">
                    <i class="fas fa-circle-info"></i>
                    Sol·licitud associada al teu perfil.
                </div>
            <?php else: ?>
                <div class="form-grup">
                    <label for="id_treballador" class="form-label form-label--requerit">
                        Treballador/a
                    </label>
                    <select id="id_treballador" name="id_treballador"
                            class="form-select camp-requerit" required>
                        <option value="0">— Selecciona —</option>
                        <?php foreach ($treballadors as $t): ?>
                            <option value="<?= (int)$t['id_treballador'] ?>"
                                <?= ((int)($_POST['id_treballador'] ?? 0) === (int)$t['id_treballador']) ? 'selected' : '' ?>>
                                <?= e($t['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus" class="form-label form-label--requerit">Tipus</label>
                    <select id="tipus" name="tipus"
                            class="form-select camp-requerit" required>
                        <?php
                        $opcions = [
                            'vacances'       => 'Vacances',
                            'permis'         => 'Permís',
                            'baixa_malaltia' => 'Baixa per malaltia',
                            'baixa_accident' => 'Baixa per accident',
                            'curs'           => 'Curs / Formació',
                            'altres'         => 'Altres',
                        ];
                        $sel = $_POST['tipus'] ?? 'vacances';
                        foreach ($opcions as $v => $lbl):
                        ?>
                            <option value="<?= $v ?>" <?= $sel === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="motiu" class="form-label">Motiu (opcional)</label>
                    <input type="text" id="motiu" name="motiu"
                           class="form-input"
                           maxlength="255"
                           value="<?= e($_POST['motiu'] ?? '') ?>"
                           placeholder="Ex: visita mèdica, gestió personal...">
                </div>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-calendar-days" aria-hidden="true"></i> Dates
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_inici" class="form-label form-label--requerit">Data inici</label>
                    <input type="date" id="data_inici" name="data_inici"
                           class="form-input camp-requerit"
                           value="<?= e($_POST['data_inici'] ?? date('Y-m-d')) ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="data_fi" class="form-label">Data fi</label>
                    <input type="date" id="data_fi" name="data_fi"
                           class="form-input"
                           value="<?= e($_POST['data_fi'] ?? '') ?>">
                    <div class="text-suau text-suau--mt-xs">
                        Si és una baixa oberta, pots deixar-la en blanc.
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-file-arrow-up" aria-hidden="true"></i> Justificant (opcional)
            </legend>
            <div class="form-grup">
                <label for="document_pdf" class="form-label">Fitxer PDF</label>
                <input type="file" id="document_pdf" name="document_pdf"
                       class="form-input"
                       accept="application/pdf">
                    <div class="text-suau text-suau--mt-xs">
                    No s'enviarà al calendari públic; queda associat a la sol·licitud interna.
                </div>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-paper-plane" aria-hidden="true"></i> Enviar sol·licitud
            </button>
            <a href="<?= BASE_URL ?>modules/personal/permisos.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
