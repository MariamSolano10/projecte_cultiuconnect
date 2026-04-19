<?php
/**
 * modules/comandes/nova_comanda.php
 *
 * Formulari per crear noves comandes de clients.
 * Gestió de productes, càlcul de totals i IVA.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nova Comanda';
$pagina_activa = 'comandes';

$mode_editar = false;
$comanda    = null;
$productes  = [];
$clients    = [];
$errors     = [];
$missatge    = null;

// Comprovar si és mode edició
$id_editar = sanitize_int($_GET['editar'] ?? null);
if ($id_editar) {
    $mode_editar = true;
    $titol_pagina = 'Editar Comanda';
    
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM comanda WHERE id_comanda = ?");
        $stmt->execute([$id_editar]);
        $comanda = $stmt->fetch();
        
        if (!$comanda) {
            set_flash('error', 'La comanda no existeix.');
            header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
            exit;
        }
        
    } catch (Exception $e) {
        error_log('[CultiuConnect] nova_comanda.php (càrrega): ' . $e->getMessage());
        $errors[] = 'Error carregant la comanda.';
    }
}

// Client preseleccionat via GET
$id_client_get = sanitize_int($_GET['id_client'] ?? null);

// Dades per al formulari
$dades = [
    'id_client'             => $comanda['id_client'] ?? $id_client_get ?? '',
    'data_comanda'          => $comanda['data_comanda'] ?? date('Y-m-d'),
    'data_entrega_prevista' => $comanda['data_entrega_prevista'] ?? '',
    'estat_comanda'        => $comanda['estat_comanda'] ?? 'pendent',
    'forma_pagament'       => $comanda['forma_pagament'] ?? 'transferencia',
    'observacions'         => $comanda['observacions'] ?? '',
    'iva_percentatge'      => $comanda['iva_percentatge'] ?? 21.00
];

// Processament POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dades['id_client']             = sanitize_int($_POST['id_client'] ?? null);
    $dades['data_comanda']          = sanitize($_POST['data_comanda'] ?? '');
    $dades['data_entrega_prevista'] = sanitize($_POST['data_entrega_prevista'] ?? '');
    $dades['estat_comanda']        = sanitize($_POST['estat_comanda'] ?? 'pendent');
    $dades['forma_pagament']       = sanitize($_POST['forma_pagament'] ?? 'transferencia');
    $dades['observacions']         = sanitize($_POST['observacions'] ?? '');
    $dades['iva_percentatge']      = sanitize_decimal($_POST['iva_percentatge'] ?? 21.00);
    
    // Productes de la comanda
    $productes_comanda = [];
    if (isset($_POST['productes']) && is_array($_POST['productes'])) {
        foreach ($_POST['productes'] as $index => $producte) {
            $id_producte = sanitize_int($producte['id_producte'] ?? null);
            $quantitat = sanitize_decimal($producte['quantitat'] ?? null);
            $preu_unitari = sanitize_decimal($producte['preu_unitari'] ?? null);
            $descompte_percent = sanitize_decimal($producte['descompte_percent'] ?? 0);
            
            if ($id_producte && $quantitat > 0 && $preu_unitari > 0) {
                $productes_comanda[] = [
                    'id_producte' => $id_producte,
                    'quantitat' => $quantitat,
                    'preu_unitari' => $preu_unitari,
                    'descompte_percent' => $descompte_percent
                ];
            }
        }
    }
    
    // Validació
    if (!$dades['id_client']) {
        $errors[] = 'El client és obligatori.';
    }
    
    if (empty($dades['data_comanda']) || !strtotime($dades['data_comanda'])) {
        $errors[] = 'La data de comanda és obligatòria i ha de ser vàlida.';
    }
    
    if (!empty($dades['data_entrega_prevista']) && !strtotime($dades['data_entrega_prevista'])) {
        $errors[] = 'La data de entrega prevista no té un format vàlid.';
    }
    
    if (empty($productes_comanda)) {
        $errors[] = 'Has d\'afegir com a mínim un producte a la comanda.';
    }
    
    $estats_valids = ['pendent', 'preparacio', 'enviat', 'entregat', 'cancelat'];
    if (!in_array($dades['estat_comanda'], $estats_valids)) {
        $errors[] = 'L\'estat de la comanda no és vàlid.';
    }
    
    $pagaments_valids = ['transferencia', 'targeta', 'efectiu', 'poder', 'altres'];
    if (!in_array($dades['forma_pagament'], $pagaments_valids)) {
        $errors[] = 'La forma de pagament no és vàlida.';
    }
    
    if ($dades['iva_percentatge'] < 0 || $dades['iva_percentatge'] > 100) {
        $errors[] = 'El percentatge d\'IVA no és vàlid.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = connectDB();
            
            // Calcular totals
            $subtotal = 0;
            foreach ($productes_comanda as $prod) {
                $descompte_import = ($prod['preu_unitari'] * $prod['quantitat']) * ($prod['descompte_percent'] / 100);
                $subtotal_linia = ($prod['preu_unitari'] * $prod['quantitat']) - $descompte_import;
                $subtotal += $subtotal_linia;
            }
            
            $iva_import = $subtotal * ($dades['iva_percentatge'] / 100);
            $total = $subtotal + $iva_import;
            
            if ($mode_editar) {
                // Actualitzar comanda existent
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE comanda 
                    SET id_client = ?, data_comanda = ?, data_entrega_prevista = ?, 
                        estat_comanda = ?, forma_pagament = ?, observacions = ?, 
                        iva_percentatge = ?, subtotal = ?, iva_import = ?, total = ?
                    WHERE id_comanda = ?
                ");
                $stmt->execute([
                    $dades['id_client'],
                    $dades['data_comanda'],
                    $dades['data_entrega_prevista'] ?: null,
                    $dades['estat_comanda'],
                    $dades['forma_pagament'],
                    $dades['observacions'] ?: null,
                    $dades['iva_percentatge'],
                    $subtotal,
                    $iva_import,
                    $total,
                    $id_editar
                ]);
                
                // Eliminar detalls existents
                $stmt = $pdo->prepare("DELETE FROM detall_comanda WHERE id_comanda = ?");
                $stmt->execute([$id_editar]);
                
                // Insertar nous detalls
                foreach ($productes_comanda as $prod) {
                    $descompte_import = ($prod['preu_unitari'] * $prod['quantitat']) * ($prod['descompte_percent'] / 100);
                    $subtotal_linia = ($prod['preu_unitari'] * $prod['quantitat']) - $descompte_import;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO detall_comanda
                            (id_comanda, id_producte, quantitat, preu_unitari, 
                             descompte_percent, descompte_import, subtotal_linia)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_editar,
                        $prod['id_producte'],
                        $prod['quantitat'],
                        $prod['preu_unitari'],
                        $prod['descompte_percent'],
                        $descompte_import,
                        $subtotal_linia
                    ]);
                }
                
                $pdo->commit();
                set_flash('success', 'Comanda actualitzada correctament.');
                
            } else {
                // Crear nova comanda
                $pdo->beginTransaction();
                
                // Generar número de comanda
                $stmt = $pdo->query("SELECT generar_num_comanda() AS num_comanda");
                $num_comanda = $stmt->fetch()['num_comanda'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO comanda
                        (id_client, num_comanda, data_comanda, data_entrega_prevista, 
                         estat_comanda, forma_pagament, observacions, iva_percentatge, 
                         subtotal, iva_import, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $dades['id_client'],
                    $num_comanda,
                    $dades['data_comanda'],
                    $dades['data_entrega_prevista'] ?: null,
                    $dades['estat_comanda'],
                    $dades['forma_pagament'],
                    $dades['observacions'] ?: null,
                    $dades['iva_percentatge'],
                    $subtotal,
                    $iva_import,
                    $total
                ]);
                
                $id_comanda = (int)$pdo->lastInsertId();
                
                // Insertar detalls
                foreach ($productes_comanda as $prod) {
                    $descompte_import = ($prod['preu_unitari'] * $prod['quantitat']) * ($prod['descompte_percent'] / 100);
                    $subtotal_linia = ($prod['preu_unitari'] * $prod['quantitat']) - $descompte_import;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO detall_comanda
                            (id_comanda, id_producte, quantitat, preu_unitari, 
                             descompte_percent, descompte_import, subtotal_linia)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_comanda,
                        $prod['id_producte'],
                        $prod['quantitat'],
                        $prod['preu_unitari'],
                        $prod['descompte_percent'],
                        $descompte_import,
                        $subtotal_linia
                    ]);
                }
                
                $pdo->commit();
                set_flash('success', 'Comanda creada correctament.');
            }
            
            if (empty($errors)) {
                header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
                exit;
            }
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CultiuConnect] nova_comanda.php (desar): ' . $e->getMessage());
            $errors[] = 'Error desant la comanda.';
        }
    }
}

try {
    $pdo = connectDB();
    
    // Carregar clients
    $clients = $pdo->query("
        SELECT id_client, nom_client, tipus_client
        FROM client
        WHERE estat = 'actiu'
        ORDER BY nom_client ASC
    ")->fetchAll();
    
    // Carregar productes
    $productes = $pdo->query("
        SELECT id_producte, nom_comercial, unitat_mesura, estoc_actual, preu_venta
        FROM producte_quimic
        ORDER BY nom_comercial ASC
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log('[CultiuConnect] nova_comanda.php (dades): ' . $e->getMessage());
    $errors[] = 'Error carregant les dades.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <?= $mode_editar ? 'Editar Comanda' : 'Nova Comanda' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $mode_editar 
                ? 'Modifica les dades de la comanda existent.' 
                : 'Registra una nova comanda de productes per a un client.' ?>
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/comandes/comandes.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <ul class="llista-errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>modules/comandes/nova_comanda.php<?= $mode_editar ? '?editar=' . $id_editar : '' ?>" 
          method="POST" 
          class="formulari-card"
          id="formComanda"
          novalidate>

        <!-- Dades principals -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-info-circle"></i> Dades de la Comanda
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_client" class="form-label form-label--requerit">
                        Client *
                    </label>
                    <select id="id_client"
                            name="id_client"
                            class="form-select camp-requerit"
                            data-etiqueta="El client"
                            required>
                        <option value="">Selecciona un client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id_client'] ?>"
                                    <?= $dades['id_client'] == $c['id_client'] ? 'selected' : '' ?>>
                                <?= e($c['nom_client']) ?> (<?= e($c['tipus_client']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="data_comanda" class="form-label form-label--requerit">
                        Data comanda *
                    </label>
                    <input type="date"
                           id="data_comanda"
                           name="data_comanda"
                           class="form-input camp-requerit"
                           data-etiqueta="La data de comanda"
                           value="<?= e($dades['data_comanda']) ?>"
                           required>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_entrega_prevista" class="form-label">
                        Data entrega prevista
                    </label>
                    <input type="date"
                           id="data_entrega_prevista"
                           name="data_entrega_prevista"
                           class="form-input"
                           value="<?= e($dades['data_entrega_prevista']) ?>">
                </div>

                <div class="form-grup">
                    <label for="estat_comanda" class="form-label form-label--requerit">
                        Estat *
                    </label>
                    <select id="estat_comanda"
                            name="estat_comanda"
                            class="form-select camp-requerit"
                            data-etiqueta="L\'estat de la comanda"
                            required>
                        <option value="pendent" <?= $dades['estat_comanda'] === 'pendent' ? 'selected' : '' ?>>
                            Pendent
                        </option>
                        <option value="preparacio" <?= $dades['estat_comanda'] === 'preparacio' ? 'selected' : '' ?>>
                            Preparació
                        </option>
                        <option value="enviat" <?= $dades['estat_comanda'] === 'enviat' ? 'selected' : '' ?>>
                            Enviat
                        </option>
                        <option value="entregat" <?= $dades['estat_comanda'] === 'entregat' ? 'selected' : '' ?>>
                            Entregat
                        </option>
                        <option value="cancelat" <?= $dades['estat_comanda'] === 'cancelat' ? 'selected' : '' ?>>
                            Cancel·lat
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="forma_pagament" class="form-label form-label--requerit">
                        Forma de pagament *
                    </label>
                    <select id="forma_pagament"
                            name="forma_pagament"
                            class="form-select camp-requerit"
                            data-etiqueta="La forma de pagament"
                            required>
                        <option value="transferencia" <?= $dades['forma_pagament'] === 'transferencia' ? 'selected' : '' ?>>
                            Transferència bancària
                        </option>
                        <option value="targeta" <?= $dades['forma_pagament'] === 'targeta' ? 'selected' : '' ?>>
                            Targeta de crèdit/dèbit
                        </option>
                        <option value="efectiu" <?= $dades['forma_pagament'] === 'efectiu' ? 'selected' : '' ?>>
                            Efectiu
                        </option>
                        <option value="poder" <?= $dades['forma_pagament'] === 'poder' ? 'selected' : '' ?>>
                            Poder de pagament
                        </option>
                        <option value="altres" <?= $dades['forma_pagament'] === 'altres' ? 'selected' : '' ?>>
                            Altres
                        </option>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="iva_percentatge" class="form-label form-label--requerit">
                        IVA (%)
                    </label>
                    <input type="number"
                           id="iva_percentatge"
                           name="iva_percentatge"
                           class="form-input"
                           value="<?= e($dades['iva_percentatge']) ?>"
                           step="0.01"
                           min="0"
                           max="100">
                </div>
            </div>
        </fieldset>

        <!-- Productes -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-boxes-stacked"></i> Productes
            </legend>

            <div id="productes-container">
                <!-- Productes s'afegiran aquí dinàmicament -->
            </div>

            <button type="button" id="afegir-producte" class="boto-secundari">
                <i class="fas fa-plus" aria-hidden="true"></i> Afegir Producte
            </button>
        </fieldset>

        <!-- Resum -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-calculator"></i> Resum
            </legend>

            <div class="resum-comanda">
                <div class="resum-fila">
                    <span class="resum-etiqueta">Subtotal:</span>
                    <span class="resum-valor" id="subtotal">0.00 EUR</span>
                </div>
                <div class="resum-fila">
                    <span class="resum-etiqueta">IVA (<?= e($dades['iva_percentatge']) ?>%):</span>
                    <span class="resum-valor" id="iva">0.00 EUR</span>
                </div>
                <div class="resum-fila resum-total">
                    <span class="resum-etiqueta">Total:</span>
                    <span class="resum-valor" id="total">0.00 EUR</span>
                </div>
            </div>
        </fieldset>

        <!-- Observacions -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-sticky-note"></i> Observacions
            </legend>

            <div class="form-grup">
                <label for="observacions" class="form-label">
                    Observacions
                </label>
                <textarea id="observacions"
                          name="observacions"
                          class="form-textarea"
                          rows="3"
                          placeholder="Notes addicionals sobre la comanda..."><?= e($dades['observacions']) ?></textarea>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $mode_editar ? 'Actualitzar Comanda' : 'Crear Comanda' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/comandes/comandes.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>

</div>

<!-- Template per producte (amagat) -->
<template id="producte-template">
    <div class="producte-fila" data-index="">
        <div class="form-fila-4">
            <div class="form-grup">
                <label class="form-label">Producte *</label>
                <select name="productes[INDEX][id_producte]" class="form-select producte-select" required>
                    <option value="">Selecciona un producte</option>
                    <?php foreach ($productes as $p): ?>
                        <option value="<?= (int)$p['id_producte'] ?>" 
                                data-preu="<?= e($p['preu_venta']) ?>"
                                data-unitat="<?= e($p['unitat_mesura']) ?>"
                                data-estoc="<?= e($p['estoc_actual']) ?>">
                            <?= e($p['nom_comercial']) ?> (<?= e($p['unitat_mesura']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label class="form-label">Quantitat *</label>
                <input type="number" 
                       name="productes[INDEX][quantitat]" 
                       class="form-input quantitat-input" 
                       min="0.01" 
                       step="0.01" 
                       required>
            </div>
            <div class="form-grup">
                <label class="form-label">Preu unitari *</label>
                <input type="number" 
                       name="productes[INDEX][preu_unitari]" 
                       class="form-input preu-input" 
                       min="0" 
                       step="0.01" 
                       required>
            </div>
            <div class="form-grup">
                <label class="form-label">Descompte (%)</label>
                <input type="number" 
                       name="productes[INDEX][descompte_percent]" 
                       class="form-input descompte-input" 
                       min="0" 
                       max="100" 
                       step="0.01" 
                       value="0">
            </div>
        </div>
        <div class="producte-info">
            <span class="producte-estoc"></span>
            <span class="producte-unitat"></span>
            <span class="producte-subtotal"></span>
            <button type="button" class="boto-eliminar-producte">
                <i class="fas fa-trash" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>

<script>
let producteIndex = 0;

function afegirProducte() {
    const template = document.getElementById('producte-template');
    const clone = template.content.cloneNode(true);
    
    // Actualitzar l'índex
    clone.querySelector('.producte-fila').dataset.index = producteIndex;
    
    // Actualitzar els noms dels camps
    const inputs = clone.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.name = input.name.replace('INDEX', producteIndex);
    });
    
    // Afegir event listeners
    const select = clone.querySelector('.producte-select');
    const quantitatInput = clone.querySelector('.quantitat-input');
    const preuInput = clone.querySelector('.preu-input');
    const descompteInput = clone.querySelector('.descompte-input');
    const botoEliminar = clone.querySelector('.boto-eliminar-producte');
    
    select.addEventListener('change', () => {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            preuInput.value = option.dataset.preu;
            clone.querySelector('.producte-unitat').textContent = 'Unitat: ' + option.dataset.unitat;
            clone.querySelector('.producte-estoc').textContent = 'Estoc: ' + option.dataset.estoc + ' ' + option.dataset.unitat;
        } else {
            clone.querySelector('.producte-unitat').textContent = '';
            clone.querySelector('.producte-estoc').textContent = '';
        }
        calcularSubtotal();
    });
    
    [quantitatInput, preuInput, descompteInput].forEach(input => {
        input.addEventListener('input', calcularSubtotal);
    });
    
    botoEliminar.addEventListener('click', () => {
        clone.querySelector('.producte-fila').remove();
        calcularSubtotal();
    });
    
    document.getElementById('productes-container').appendChild(clone);
    producteIndex++;
}

function calcularSubtotal() {
    let subtotal = 0;
    
    document.querySelectorAll('.producte-fila').forEach(fila => {
        const quantitat = parseFloat(fila.querySelector('.quantitat-input').value) || 0;
        const preu = parseFloat(fila.querySelector('.preu-input').value) || 0;
        const descompte = parseFloat(fila.querySelector('.descompte-input').value) || 0;
        
        const subtotalLinia = (quantitat * preu) * (1 - descompte / 100);
        subtotal += subtotalLinia;
        
        // Mostrar subtotal de la línia
        const subtotalSpan = fila.querySelector('.producte-subtotal');
        if (subtotalLinia > 0) {
            subtotalSpan.textContent = 'Subtotal: ' + subtotalLinia.toFixed(2) + ' EUR';
        } else {
            subtotalSpan.textContent = '';
        }
    });
    
    const ivaPercentatge = parseFloat(document.getElementById('iva_percentatge').value) || 21;
    const iva = subtotal * (ivaPercentatge / 100);
    const total = subtotal + iva;
    
    document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' EUR';
    document.getElementById('iva').textContent = iva.toFixed(2) + ' EUR';
    document.getElementById('total').textContent = total.toFixed(2) + ' EUR';
}

document.addEventListener('DOMContentLoaded', function() {
    // Afegir primer producte
    afegirProducte();
    
    // Event listener per afegir productes
    document.getElementById('afegir-producte').addEventListener('click', afegirProducte);
    
    // Event listener per canvi d'IVA
    document.getElementById('iva_percentatge').addEventListener('input', calcularSubtotal);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
