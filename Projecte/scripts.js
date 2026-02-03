// scripts.js - Conté tota la lògica JS de l'aplicació

/**
 * 1. Funció per a la Validació del Formulari de Nou Tractament (operacio_nova.php)
 * Impedeix l'enviament si hi ha camps clau buits o valors invàlids.
 */
function validarTractament() {
    // Es requereix que els elements del formulari tinguin aquests 'id'
    const parcela = document.getElementById('parcela_aplicacio').value;
    const producte = document.getElementById('producte_quimic').value;
    const dosi = parseFloat(document.getElementById('dosi').value);
    
    if (parcela === "" || parcela === "0") {
        alert("⚠️ Si us plau, selecciona el Sector d'Aplicació.");
        document.getElementById('parcela_aplicacio').focus();
        return false;
    }
    
    if (producte === "" || producte === "0") {
        alert("⚠️ Si us plau, selecciona un Producte Fitosanitari.");
        document.getElementById('producte_quimic').focus();
        return false;
    }

    if (isNaN(dosi) || dosi <= 0) {
        alert("⚠️ La Dosi (L/Kg per hectàrea) ha de ser un nombre positiu i superior a zero.");
        document.getElementById('dosi').focus();
        return false;
    }
    
    return true; // Si retorna true, el formulari s'envia
}


/**
 * 2. Funció per al Filtre en Temps Real de la Taula (quadern.php)
 * Filtra la taula del quadern per Sector o Producte a mesura que l'usuari escriu.
 */
function filtrarQuadern() {
    const input = document.getElementById('filtreInput');
    const filtre = input.value.toUpperCase();
    const taula = document.getElementById('taula-quadern');
    
    if (!taula) return; 

    // Obtenim les files del body de la taula
    // S'assumeix que la taula té un <thead> i un <tbody>
    const files = taula.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < files.length; i++) { 
        // <td>[1] = Sector, <td>[3] = Producte
        let celdaSector = files[i].getElementsByTagName('td')[1]; 
        let celdaProducte = files[i].getElementsByTagName('td')[3];
        
        if (celdaSector && celdaProducte) {
            let textSector = celdaSector.textContent || celdaSector.innerText;
            let textProducte = celdaProducte.textContent || celdaProducte.innerText;
            
            // Mostra la fila si el filtre coincideix amb el sector O el producte
            if (textSector.toUpperCase().indexOf(filtre) > -1 || textProducte.toUpperCase().indexOf(filtre) > -1) {
                files[i].style.display = ""; 
            } else {
                files[i].style.display = "none"; 
            }
        }       
    }
}