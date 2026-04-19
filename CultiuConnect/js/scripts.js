/**
 * js/scripts.js — Lògica client de CultiuConnect
 *
 * Índex de seccions:
 *   1. Inicialització general
 *   2. Missatges flash (auto-tancament animat)
 *   3. Validació de formularis (genèrica, inline, accessible)
 *   4. Filtre en temps real de taules (amb aria-live)
 *   5. Modal de confirmació accessible (substitueix confirm())
 *   6. Indicador de càrrega en submits
 *   7. Dates màximes automàtiques (data-no-futur)
 *   8. Utilitats generals
 */

'use strict';

// ============================================================
// 1. INICIALITZACIÓ GENERAL
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    inicialitzarFlash();
    inicialitzarValidacioFormularis();
    inicialitzarFiltresTaules();
    inicialitzarConfirmacions();
    inicialitzarLoadingSubmit();
    inicialitzarDatesMaximes();
});


// ============================================================
// 2. MISSATGES FLASH (auto-tancament amb animació d'altura)
// ============================================================

function inicialitzarFlash() {
    document.querySelectorAll('.flash').forEach(flash => {
        const timer = setTimeout(() => tancarFlash(flash), 6000);

        flash.querySelector('.flash__tancar')?.addEventListener('click', () => {
            clearTimeout(timer);
            tancarFlash(flash);
        });
    });
}

function tancarFlash(element) {
    // Anima opacity I altura per evitar salt brusc del layout
    const altura = element.offsetHeight;
    element.style.overflow   = 'hidden';
    element.style.maxHeight  = altura + 'px';
    element.style.transition =
        'opacity .3s ease, max-height .35s ease .1s, margin .35s ease .1s, padding .35s ease .1s';

    requestAnimationFrame(() => {
        element.style.opacity  = '0';
        element.style.maxHeight = '0';
        element.style.margin   = '0';
        element.style.padding  = '0';
    });

    setTimeout(() => element.remove(), 500);
}


// ============================================================
// 3. VALIDACIÓ DE FORMULARIS
// ============================================================
//
// Activació: afegir `novalidate` i classe `camp-requerit` als camps.
// La validació s'activa automàticament en qualsevol formulari
// que contingui camps amb la classe `camp-requerit`.
//
// Atributs de camp:
//   data-etiqueta="El nom del camp"   → text del missatge d'error
//   data-tipus="positiu|decimal|dni|email"  → validació de format

function inicialitzarValidacioFormularis() {
    // Genèric: qualsevol formulari amb camps .camp-requerit
    document.querySelectorAll('form').forEach(form => {
        if (!form.querySelector('.camp-requerit')) return;
        form.addEventListener('submit', e => {
            if (!validarFormulari(form)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    });

    // Neteja errors en temps real mentre l'usuari corregeix
    document.querySelectorAll('.camp-requerit').forEach(camp => {
        camp.addEventListener('input',  () => netejaError(camp));
        camp.addEventListener('change', () => netejaError(camp));
    });
}

function validarFormulari(formulari) {
    const camps = formulari.querySelectorAll('.camp-requerit');
    let valid       = true;
    let primerError = null;

    camps.forEach(camp => {
        netejaError(camp);
        const error = obtenirErrorCamp(camp);
        if (error) {
            mostrarError(camp, error);
            valid = false;
            if (!primerError) primerError = camp;
        }
    });

    if (primerError) {
        primerError.focus();
        primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return valid;
}

function obtenirErrorCamp(camp) {
    const valor    = camp.value.trim();
    const etiqueta = camp.dataset.etiqueta || camp.name || 'Aquest camp';
    const tipus    = camp.dataset.tipus;

    // Camp buit (inclou selects sense selecció: valor "" o "0")
    if (valor === '' || valor === '0') {
        return `${etiqueta} és obligatori.`;
    }

    // Número estrictament positiu (> 0)
    if (tipus === 'positiu') {
        const num = parseFloat(valor.replace(',', '.'));
        if (isNaN(num) || num <= 0) {
            return `${etiqueta} ha de ser un nombre positiu.`;
        }
    }

    // Decimal >= 0
    if (tipus === 'decimal') {
        const num = parseFloat(valor.replace(',', '.'));
        if (isNaN(num) || num < 0) {
            return `${etiqueta} ha de ser un nombre vàlid (0 o superior).`;
        }
    }

    // DNI espanyol bàsic
    if (tipus === 'dni') {
        if (!/^\d{7,8}[A-Za-z]$/.test(valor)) {
            return `${etiqueta} ha de tenir el format correcte (ex: 12345678A).`;
        }
    }

    // Email
    if (tipus === 'email') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
            return `${etiqueta} no té un format de correu vàlid.`;
        }
    }

    // Data de fi >= data d'inici
    // Ús al camp de fi: data-data-fi="id-del-camp-inici"
    if (camp.dataset.dataFi) {
        const campInici = document.getElementById(camp.dataset.dataFi);
        if (campInici?.value && camp.value) {
            if (new Date(camp.value) < new Date(campInici.value)) {
                return `${etiqueta} no pot ser anterior a la data d'inici.`;
            }
        }
    }

    return null;
}

function mostrarError(camp, missatge) {
    const idError = 'err-' + (camp.id || camp.name || Math.random().toString(36).slice(2));

    camp.classList.add('camp--error');
    camp.setAttribute('aria-invalid', 'true');
    camp.setAttribute('aria-describedby', idError);

    // Eliminar error anterior si existís
    camp.nextElementSibling?.classList.contains('error-validacio')
        && camp.nextElementSibling.remove();

    const span = document.createElement('span');
    span.className   = 'error-validacio';
    span.id          = idError;
    span.setAttribute('role', 'alert');
    span.textContent = missatge;

    camp.insertAdjacentElement('afterend', span);
}

function netejaError(camp) {
    camp.classList.remove('camp--error');
    camp.removeAttribute('aria-invalid');
    camp.removeAttribute('aria-describedby');

    const seguent = camp.nextElementSibling;
    if (seguent?.classList.contains('error-validacio')) {
        seguent.remove();
    }
}


// ============================================================
// 4. FILTRE EN TEMPS REAL DE TAULES (amb aria-live)
// ============================================================
//
// Ús:
//   <input type="search" data-filtre-taula="id-de-la-taula" placeholder="Cerca...">
//   <table id="id-de-la-taula">
//     <tbody>
//       <tr>
//         <td data-cerca>Valor filtrable</td>
//         <td>Valor no filtrable</td>
//       </tr>
//     </tbody>
//   </table>

function inicialitzarFiltresTaules() {
    document.querySelectorAll('[data-filtre-taula]').forEach(input => {
        const taula = document.getElementById(input.dataset.filtreTaula);
        if (!taula) return;

        // Injectar regió aria-live per anunciar resultats als lectors de pantalla
        let regio = document.getElementById('filtre-live-' + taula.id);
        if (!regio) {
            regio = document.createElement('div');
            regio.id = 'filtre-live-' + taula.id;
            regio.setAttribute('aria-live', 'polite');
            regio.setAttribute('aria-atomic', 'true');
            regio.className = 'sr-only';
            taula.parentNode.insertBefore(regio, taula);
        }

        // Debounce per no filtrar a cada tecla
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => filtrarTaula(input.value, taula, regio), 180);
        });
    });
}

function filtrarTaula(cerca, taula, regioAria) {
    const text  = cerca.toLowerCase().trim();
    const files = taula.querySelectorAll('tbody tr:not(.fila-sense-resultats)');
    let visibles = 0;

    files.forEach(fila => {
        const cel·les   = fila.querySelectorAll('td[data-cerca]');
        const contingut = Array.from(cel·les)
            .map(td => td.textContent.toLowerCase())
            .join(' ');

        const mostra = text === '' || contingut.includes(text);
        fila.hidden  = !mostra;
        if (mostra) visibles++;
    });

    // Fila "cap resultat"
    const tbody = taula.querySelector('tbody');
    let fila0   = tbody.querySelector('.fila-sense-resultats');

    if (visibles === 0) {
        if (!fila0) {
            const cols = taula.querySelector('thead tr')?.children.length ?? 1;
            fila0 = document.createElement('tr');
            fila0.className = 'fila-sense-resultats';
            fila0.innerHTML = `<td colspan="${cols}" class="sense-dades">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                Cap resultat per a la cerca.
            </td>`;
            tbody.appendChild(fila0);
        }
    } else {
        fila0?.remove();
    }

    // Anunci per a lectors de pantalla
    if (regioAria) {
        const total = files.length;
        regioAria.textContent = text === ''
            ? `Mostrant tots els ${total} registres.`
            : `${visibles} de ${total} resultats per "${cerca}".`;
    }
}


// ============================================================
// 5. MODAL DE CONFIRMACIÓ ACCESSIBLE
// ============================================================
//
// Substitueix el confirm() natiu del navegador:
//   - Segueix el disseny de CultiuConnect
//   - Accessible: focus trap, ESC, aria-modal, aria-labelledby
//   - No bloqueja el thread del navegador
//
// Ús a HTML:
//   <a href="eliminar.php?id=5"
//      data-confirma="Segur que vols eliminar aquesta parcel·la?"
//      data-confirma-title="Eliminar parcel·la"
//      data-confirma-ok="Sí, eliminar">
//     Eliminar
//   </a>

let _modalCallback = null;
let _focusPreModal = null;

function inicialitzarConfirmacions() {
    if (!document.getElementById('modal-confirma')) {
        document.body.insertAdjacentHTML('beforeend', _templateModal());

        document.getElementById('btn-confirma-cancel')
            .addEventListener('click', () => tancarModal(false));
        document.getElementById('btn-confirma-ok')
            .addEventListener('click', () => tancarModal(true));
        document.getElementById('modal-confirma')
            .addEventListener('keydown', e => {
                if (e.key === 'Escape') tancarModal(false);
                _focusTrapModal(e);
            });
    }

    document.querySelectorAll('[data-confirma]').forEach(element => {
        element.addEventListener('click', e => {
            e.preventDefault();
            obrirModal(
                element.dataset.confirmaTitle || 'Confirmar acció',
                element.dataset.confirma      || 'Segur que vols continuar?',
                element.dataset.confirmaOk    || 'Confirmar',
                () => _executarAccio(element)
            );
        });
    });
}

function _templateModal() {
    return `
    <div id="modal-confirma"
         class="modal-confirma"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-titol"
         aria-describedby="modal-missatge"
         hidden>
        <div class="modal-confirma__caixa">
            <div class="modal-confirma__icona" aria-hidden="true">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h2 class="modal-confirma__titol" id="modal-titol"></h2>
            <p  class="modal-confirma__missatge" id="modal-missatge"></p>
            <div class="modal-confirma__botons">
                <button id="btn-confirma-cancel" class="boto-secundari">
                    Cancel·lar
                </button>
                <button id="btn-confirma-ok" class="boto-confirma-destructiu">
                    Confirmar
                </button>
            </div>
        </div>
    </div>`;
}

function obrirModal(titol, missatge, labelOk, callback) {
    document.getElementById('modal-titol').textContent   = titol;
    document.getElementById('modal-missatge').textContent = missatge;
    document.getElementById('btn-confirma-ok').textContent = labelOk;

    _modalCallback = callback;
    _focusPreModal = document.activeElement;

    const modal = document.getElementById('modal-confirma');
    modal.hidden = false;
    document.body.classList.add('modal-obert');

    // Focus al botó cancel·lar (acció per defecte segura)
    requestAnimationFrame(() => {
        document.getElementById('btn-confirma-cancel').focus();
    });
}

function tancarModal(confirmat) {
    const modal = document.getElementById('modal-confirma');
    modal.hidden = true;
    document.body.classList.remove('modal-obert');
    _focusPreModal?.focus();

    if (confirmat && _modalCallback) _modalCallback();
    _modalCallback = null;
}

function _focusTrapModal(e) {
    if (e.key !== 'Tab') return;
    const modal      = document.getElementById('modal-confirma');
    const elements   = Array.from(modal.querySelectorAll('button:not([disabled])'));
    const primer     = elements[0];
    const darrer     = elements[elements.length - 1];

    if (e.shiftKey && document.activeElement === primer) {
        e.preventDefault(); darrer?.focus();
    } else if (!e.shiftKey && document.activeElement === darrer) {
        e.preventDefault(); primer?.focus();
    }
}

function _executarAccio(element) {
    if (element.tagName === 'A') {
        window.location.href = element.href;
    } else if (element.tagName === 'BUTTON' && element.form) {
        // Evitar que el listener torni a interceptar
        const clon = element.cloneNode(true);
        clon.removeAttribute('data-confirma');
        element.replaceWith(clon);
        clon.click();
    } else if (element.form) {
        element.form.submit();
    }
}


// ============================================================
// 6. INDICADOR DE CÀRREGA EN SUBMITS
// ============================================================
//
// Quan l'usuari envia un formulari vàlid, el botó submit es
// desactiva i mostra un spinner. Evita dobles enviaments.

function inicialitzarLoadingSubmit() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            // No activar si la validació ha deixat errors visibles
            if (form.querySelector('.camp--error')) return;

            const btn = form.querySelector('[type="submit"]:not([disabled])');
            if (!btn) return;

            const textOriginal = btn.innerHTML;
            const labelText    = btn.textContent.trim() || 'Desant...';

            btn.disabled  = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                             <span>${labelText}</span>`;
            btn.setAttribute('aria-busy', 'true');

            // Salvaguarda: restaurar si la pàgina no navega en 10 s
            setTimeout(() => {
                if (btn.disabled) {
                    btn.disabled  = false;
                    btn.innerHTML = textOriginal;
                    btn.removeAttribute('aria-busy');
                }
            }, 10000);
        });
    });
}


// ============================================================
// 7. DATES MÀXIMES AUTOMÀTIQUES (data-no-futur)
// ============================================================

function inicialitzarDatesMaximes() {
    const avui = avuiISO();
    document.querySelectorAll('input[type="date"][data-no-futur]').forEach(input => {
        if (!input.getAttribute('max')) input.setAttribute('max', avui);
    });
}


// ============================================================
// 8. UTILITATS GENERALS
// ============================================================

/**
 * Formata un número amb separadors europeus.
 * Exemple: formatNumero(12500.5, 2) → "12.500,50"
 */
function formatNumero(valor, decimals = 2) {
    return Number(valor).toLocaleString('ca-ES', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

/** Retorna la data d'avui en format ISO (YYYY-MM-DD). */
function avuiISO() {
    return new Date().toISOString().split('T')[0];
}

document.addEventListener('click', function(event) {
    const wrapper = document.querySelector('.alertes-wrapper');
    const dropdown = document.getElementById('dropdown-alertes');
    if (wrapper && dropdown && !wrapper.contains(event.target)) {
        dropdown.classList.remove('actiu');
    }
});