<?php
/**
 * modules/clients/clients.php
 *
 * Gestió de clients de l'explotació agrícola.
 * Permet llistar, crear, editar i eliminar clients.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Gestió de Clients';
$pagina_activa = 'clients';

$clients = [];
$error_db   = null;
$missatge    = null;

// Processament d'accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = sanitize($_POST['accio'] ?? '');
    
    if ($accio === 'eliminar') {
        $id_client = sanitize_int($_POST['id_client'] ?? null);
        
        if ($id_client) {
            try {
                $pdo = connectDB();
                
                // Verificar si té comandes associades
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM comanda WHERE id_client = ?");
                $stmt->execute([$id_client]);
                $te_comandes = (int)$stmt->fetchColumn() > 0;
                
                if ($te_comandes) {
                    $missatge = 'No es pot eliminar el client perquè té comandes associades.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM client WHERE id_client = ?");
                    $stmt->execute([$id_client]);
                    
                    if ($stmt->rowCount() > 0) {
                        set_flash('success', 'Client eliminat correctament.');
                    } else {
                        $missatge = 'El client no existeix.';
                    }
                }
                
                header('Location: ' . BASE_URL . 'modules/clients/clients.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] clients.php (eliminar): ' . $e->getMessage());
                $missatge = 'Error eliminant el client.';
            }
        }
    }
}

try {
    $pdo = connectDB();
    
    // Carregar tots els clients
    $clients = $pdo->query("
        SELECT id_client, nom_client, nif_cif, adreca, poblacio, codi_postal, 
               telefon, email, tipus_client, observacions, data_creacio, estat
        FROM client
        ORDER BY nom_client ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] clients.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els clients.';
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

function classeEstatClient($estat): array
{
    return match($estat) {
        'actiu' => ['badge--verd', 'Actiu'],
        'inactiu' => ['badge--vermell', 'Inactiu'],
        default => ['badge--gris', 'Desconegut']
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-clients">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-users" aria-hidden="true"></i>
            Gestió de Clients
        </h1>
        <p class="descripcio-seccio">
            Administra el teu catàleg de clients per a la venda de productes agrícoles.
            Gestiona les seves dades de contacte i historial de comandes.
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/clients/nou_client.php" class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i> Nou Client
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($missatge): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <?= e($missatge) ?>
        </div>
    <?php endif; ?>

    <!-- Estadístiques ràpides -->
    <div class="kpi-grid kpi-grid--petit">
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-users"></i>
            </div>
            <span class="kpi-card__valor"><?= count($clients) ?></span>
            <span class="kpi-card__etiqueta">Total clients</span>
        </div>

        <?php 
        $tipus_counts = [];
        foreach ($clients as $c) {
            $tipus_counts[$c['tipus_client']] = ($tipus_counts[$c['tipus_client']] ?? 0) + 1;
        }
        ?>

        <?php if (!empty($tipus_counts['particular'])): ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-user"></i>
            </div>
            <span class="kpi-card__valor"><?= $tipus_counts['particular'] ?></span>
            <span class="kpi-card__etiqueta">Particulars</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($tipus_counts['empresa'])): ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-building"></i>
            </div>
            <span class="kpi-card__valor"><?= $tipus_counts['empresa'] ?></span>
            <span class="kpi-card__etiqueta">Empreses</span>
        </div>
        <?php endif; ?>

        <?php 
        $actius = count(array_filter($clients, fn($c) => $c['estat'] === 'actiu'));
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-check-circle"></i>
            </div>
            <span class="kpi-card__valor"><?= $actius ?></span>
            <span class="kpi-card__etiqueta">Actius</span>
        </div>
    </div>

    <!-- Cerca -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-clients"
               placeholder="Cerca per nom, NIF/CIF, població, telèfon, email..."
               class="input-cerca"
               aria-label="Cercar clients">
    </div>

    <!-- Taula de clients -->
    <div class="taula-container">
        <table class="taula-simple" id="taula-clients">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>NIF/CIF</th>
                    <th>Contacte</th>
                    <th>Ubicació</th>
                    <th>Tipus</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="7" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha clients registrats.
                            <a href="<?= BASE_URL ?>modules/clients/nou_client.php">Afegeix-ne un.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): 
                        [$classe_tipus, $icona_tipus] = classeTipusClient($client['tipus_client']);
                        [$classe_estat, $text_estat] = classeEstatClient($client['estat']);
                    ?>
                        <tr>
                            <td data-cerca>
                                <strong><?= e($client['nom_client']) ?></strong>
                                <?php if ($client['observacions']): ?>
                                    <br><small class="text-suau"><?= e(substr($client['observacions'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td data-cerca>
                                <?= e($client['nif_cif'] ?: '---') ?>
                            </td>
                            <td data-cerca>
                                <?php if ($client['telefon']): ?>
                                    <div class="contacte-info">
                                        <i class="fas fa-phone" aria-hidden="true"></i>
                                        <?= e($client['telefon']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($client['email']): ?>
                                    <div class="contacte-info">
                                        <i class="fas fa-envelope" aria-hidden="true"></i>
                                        <a href="mailto:<?= e($client['email']) ?>" class="enllac-email">
                                            <?= e($client['email']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$client['telefon'] && !$client['email']): ?>
                                    <span class="text-suau">---</span>
                                <?php endif; ?>
                            </td>
                            <td data-cerca class="cel-text-llarg">
                                <?= e($client['poblacio'] ?: '---') ?>
                                <?php if ($client['codi_postal']): ?>
                                    <br><small class="text-suau">(<?= e($client['codi_postal']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td data-cerca>
                                <span class="badge <?= $classe_tipus ?>">
                                    <i class="fas <?= $icona_tipus ?>" aria-hidden="true"></i>
                                    <?= ucfirst($client['tipus_client']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $classe_estat ?>">
                                    <?= $text_estat ?>
                                </span>
                            </td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/clients/veure_client.php?id=<?= (int)$client['id_client'] ?>"
                                   title="Veure detall"
                                   aria-label="Veure detall de <?= e($client['nom_client']) ?>"
                                   class="btn-accio btn-accio--veure">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                    <span class="sr-only">Veure</span>
                                </a>
                                <a href="<?= BASE_URL ?>modules/clients/nou_client.php?editar=<?= (int)$client['id_client'] ?>"
                                   title="Editar client"
                                   aria-label="Editar <?= e($client['nom_client']) ?>"
                                   class="btn-accio btn-accio--editar">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    <span class="sr-only">Editar</span>
                                </a>
                                <form method="POST" class="form-inline" 
                                      onsubmit="return confirm('Estàs segur que vols eliminar aquest client? Aquesta acció no es pot desfer.');">
                                    <input type="hidden" name="accio" value="eliminar">
                                    <input type="hidden" name="id_client" value="<?= (int)$client['id_client'] ?>">
                                    <button type="submit" 
                                            title="Eliminar client"
                                            aria-label="Eliminar <?= e($client['nom_client']) ?>"
                                            class="btn-accio btn-accio--eliminar">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="sr-only">Eliminar</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

