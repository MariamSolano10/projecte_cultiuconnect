<?php
/**
 * modules/clients/veure_client.php
 *
 * Vista detallada d'un client amb el seu historial de comandes.
 * Mostra informació completa i estadístiques.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_client = sanitize_int($_GET['id'] ?? null);

if (!$id_client) {
    set_flash('error', 'ID de client invàlid.');
    header('Location: ' . BASE_URL . 'modules/clients/clients.php');
    exit;
}

$titol_pagina  = 'Detall del Client';
$pagina_activa = 'clients';

$client      = null;
$comandes     = [];
$estadistiques = [];
$error_db     = null;

try {
    $pdo = connectDB();

    // Dades del client
    $stmt = $pdo->prepare("SELECT * FROM client WHERE id_client = ?");
    $stmt->execute([$id_client]);
    $client = $stmt->fetch();

    if (!$client) {
        set_flash('error', 'El client no existeix.');
        header('Location: ' . BASE_URL . 'modules/clients/clients.php');
        exit;
    }

    // Comandes del client
    $stmt = $pdo->prepare("
        SELECT 
            c.id_comanda,
            c.num_comanda,
            c.data_comanda,
            c.data_entrega_prevista,
            c.estat_comanda,
            c.forma_pagament,
            c.subtotal,
            c.iva_import,
            c.total,
            COUNT(dc.id_detall) AS num_productes,
            SUM(dc.quantitat) AS total_quantitat
        FROM comanda c
        LEFT JOIN detall_comanda dc ON dc.id_comanda = c.id_comanda
        WHERE c.id_client = ?
        GROUP BY c.id_comanda
        ORDER BY c.data_comanda DESC
        LIMIT 20
    ");
    $stmt->execute([$id_client]);
    $comandes = $stmt->fetchAll();

    // Estadístiques
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_comandes,
            SUM(CASE WHEN c.estat_comanda = 'entregat' THEN 1 ELSE 0 END) AS comandes_entregades,
            SUM(CASE WHEN c.estat_comanda = 'pendent' THEN 1 ELSE 0 END) AS comandes_pendents,
            SUM(c.total) AS import_total
        FROM comanda c
        WHERE c.id_client = ?
    ");
    $stmt->execute([$id_client]);
    $estadistiques = $stmt->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] veure_client.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del client.';
}

function classeTipusClient($tipus): array
{
    return match($tipus) {
        'particular' => ['badge--blau', 'fa-user'],
        'empresa' => ['badge--verd', 'fa-building'],
        'cooperativa' => ['badge--groc', 'fa-users'],
        'altres' => ['badge--gris', 'fa-tag'],
        default => ['badge--gris', 'fa-user']
    };
}

function classeEstatComanda($estat): array
{
    return match($estat) {
        'pendent' => ['badge--groc', 'Pendent'],
        'preparacio' => ['badge--blau', 'Preparació'],
        'enviat' => ['badge--cyan', 'Enviat'],
        'entregat' => ['badge--verd', 'Entregat'],
        'cancelat' => ['badge--vermell', 'Cancel·lat'],
        default => ['badge--gris', 'Desconegut']
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-detall">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-users" aria-hidden="true"></i>
            Detall del Client
        </h1>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/clients/clients.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/clients/nou_client.php?editar=<?= (int)$id_client ?>" 
               class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <!-- Dades principals -->
        <div class="detall-grid">
            <div class="detall-bloc">
                <h2 class="detall-bloc__titol">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Informació General
                </h2>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Nom</label>
                    <div class="detall-valor">
                        <strong><?= e($client['nom_client']) ?></strong>
                    </div>
                </div>

                <div class="detall-camp">
                    <label class="detall-etiqueta">NIF/CIF</label>
                    <div class="detall-valor">
                        <?= e($client['nif_cif'] ?: '---') ?>
                    </div>
                </div>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Tipus</label>
                    <div class="detall-valor">
                        <?php [$classe_tipus, $icona_tipus] = classeTipusClient($client['tipus_client']); ?>
                        <span class="badge <?= $classe_tipus ?>">
                            <i class="fas <?= $icona_tipus ?>" aria-hidden="true"></i>
                            <?= ucfirst($client['tipus_client']) ?>
                        </span>
                    </div>
                </div>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Estat</label>
                    <div class="detall-valor">
                        <span class="badge badge--<?= $client['estat'] === 'actiu' ? 'verd' : 'vermell' ?>">
                            <?= ucfirst($client['estat']) ?>
                        </span>
                    </div>
                </div>

                <div class="detall-camp">
                    <label class="detall-etiqueta">Data de creació</label>
                    <div class="detall-valor">
                        <?= format_data($client['data_creacio'], curta: true) ?>
                    </div>
                </div>
            </div>

            <div class="detall-bloc">
                <h2 class="detall-bloc__titol">
                    <i class="fas fa-address-book" aria-hidden="true"></i>
                    Contacte
                </h2>

                <?php if ($client['telefon']): ?>
                    <div class="detall-camp">
                        <label class="detall-etiqueta">Telèfon</label>
                        <div class="detall-valor">
                            <a href="tel:<?= e($client['telefon']) ?>" class="enllac-contacte">
                                <i class="fas fa-phone" aria-hidden="true"></i>
                                <?= e($client['telefon']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($client['email']): ?>
                    <div class="detall-camp">
                        <label class="detall-etiqueta">Email</label>
                        <div class="detall-valor">
                            <a href="mailto:<?= e($client['email']) ?>" class="enllac-contacte">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                                <?= e($client['email']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$client['telefon'] && !$client['email']): ?>
                    <p class="text-suau">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha informació de contacte.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ubicació -->
        <div class="detall-bloc">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                Ubicació
            </h2>
            <div class="detall-grid">
                <div class="detall-camp">
                    <label class="detall-etiqueta">Adreça</label>
                    <div class="detall-valor">
                        <?= e($client['adreca'] ?: '---') ?>
                    </div>
                </div>
                <div class="detall-camp">
                    <label class="detall-etiqueta">Població</label>
                    <div class="detall-valor">
                        <?= e($client['poblacio'] ?: '---') ?>
                    </div>
                </div>
                <div class="detall-camp">
                    <label class="detall-etiqueta">Codi Postal</label>
                    <div class="detall-valor">
                        <?= e($client['codi_postal'] ?: '---') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadístiques -->
        <div class="detall-bloc">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-chart-bar" aria-hidden="true"></i>
                Estadístiques
            </h2>
            <div class="kpi-grid kpi-grid--petit">
                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <span class="kpi-card__valor"><?= (int)$estadistiques['total_comandes'] ?></span>
                    <span class="kpi-card__etiqueta">Total comandes</span>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="kpi-card__valor"><?= (int)$estadistiques['comandes_entregades'] ?></span>
                    <span class="kpi-card__etiqueta">Entregades</span>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-clock"></i>
                    </div>
                    <span class="kpi-card__valor"><?= (int)$estadistiques['comandes_pendents'] ?></span>
                    <span class="kpi-card__etiqueta">Pendents</span>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-coins"></i>
                    </div>
                    <span class="kpi-card__valor"><?= number_format((float)($estadistiques['import_total'] ?? 0), 2, ',', '.') ?> EUR</span>
                    <span class="kpi-card__etiqueta">Import total</span>
                </div>
            </div>
        </div>

        <!-- Comandes recentes -->
        <div class="detall-bloc detall-bloc--ample">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                Comandes Recents
                <span class="badge badge--gris"><?= count($comandes) ?></span>
            </h2>

            <?php if (empty($comandes)): ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Aquest client no té comandes registrades.
                </p>
            <?php else: ?>
                <div class="taula-container">
                    <table class="taula-simple">
                        <thead>
                            <tr>
                                <th>Núm. Comanda</th>
                                <th>Data</th>
                                <th>Estat</th>
                                <th>Productes</th>
                                <th>Total</th>
                                <th>Forma Pagament</th>
                                <th>Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comandes as $comanda): 
                                [$classe_estat, $text_estat] = classeEstatComanda($comanda['estat_comanda']);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= e($comanda['num_comanda']) ?></strong>
                                    </td>
                                    <td>
                                        <?= format_data($comanda['data_comanda'], curta: true) ?>
                                        <?php if ($comanda['data_entrega_prevista']): ?>
                                            <br><small class="text-suau">Entrega: <?= format_data($comanda['data_entrega_prevista'], curta: true) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $classe_estat ?>">
                                            <?= $text_estat ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= (int)$comanda['num_productes'] ?> productes
                                        <br><small class="text-suau"><?= number_format((float)$comanda['total_quantitat'], 2, ',', '.') ?> unitats</small>
                                    </td>
                                    <td class="text-dreta">
                                        <strong><?= number_format((float)$comanda['total'], 2, ',', '.') ?> EUR</strong>
                                    </td>
                                    <td>
                                        <?= ucfirst($comanda['forma_pagament']) ?>
                                    </td>
                                    <td class="cel-accions">
                                        <a href="<?= BASE_URL ?>modules/comandes/veure_comanda.php?id=<?= (int)$comanda['id_comanda'] ?>" 
                                           class="btn-accio btn-accio--veure"
                                           title="Veure detall">
                                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                            <span class="sr-only">Veure</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="accio-peu">
                    <a href="<?= BASE_URL ?>modules/comandes/nova_comanda.php?id_client=<?= (int)$id_client ?>" class="boto-principal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Nova Comanda
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Observacions -->
        <?php if ($client['observacions']): ?>
            <div class="detall-bloc">
                <h2 class="detall-bloc__titol">
                    <i class="fas fa-sticky-note" aria-hidden="true"></i>
                    Observacions
                </h2>
                <div class="detall-contingut">
                    <p><?= nl2br(e($client['observacions'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

