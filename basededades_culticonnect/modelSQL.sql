-- ******************************************************
-- Taula 1: Parcel·les (Cadastre Digital)
DROP TABLE IF EXISTS Parcela;

CREATE TABLE
    Parcela (
        id_parcela INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL COMMENT 'Identificador únic i nom descriptiu',
        coordenades_geo GEOMETRY NOT NULL COMMENT 'Coordenades GPS que delimiten el perímetre',
        -- Superfície ha és ara NOT NULL ja que és un atribut essencial
        superficie_ha DECIMAL(10, 2) NOT NULL COMMENT 'Superfície total calculada automàticament',
        pendent VARCHAR(20) COMMENT 'Factor clau per a la gestió de l''aigua',
        orientacio VARCHAR(20) COMMENT 'Factor clau per a l''exposició solar'
    );

-- Taula 2: Característiques del sòl (Informació edafològica amb historial)
DROP TABLE IF EXISTS Caracteristiques_Sol;

CREATE TABLE
    Caracteristiques_Sol (
        id_parcela INT NOT NULL COMMENT 'Clau Forana a la parcel·la analitzada',
        data_analisis DATE NOT NULL COMMENT 'Data en què es va realitzar l''anàlisi',
        textura VARCHAR(50),
        pH DECIMAL(4, 2),
        materia_organica DECIMAL(5, 2),
        -- Garanteix que només hi hagi una anàlisi per parcel·la per dia
        PRIMARY KEY (id_parcela, data_analisis),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE
    );

-- Taula 3: Infraestructures
DROP TABLE IF EXISTS Infraestructura;

CREATE TABLE
    Infraestructura (
        id_infra INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        tipus ENUM ('reg', 'camin', 'tanca', 'edificacio', 'altres') NOT NULL COMMENT 'Sistemes de reg, camins d''accés, tanques'
    );

-- Taula 4: Taula N:M Parcel·la-Infraestructura
DROP TABLE IF EXISTS Parcela_Infraestructura;

CREATE TABLE
    Parcela_Infraestructura (
        id_parcela INT NOT NULL,
        id_infra INT NOT NULL,
        PRIMARY KEY (id_parcela, id_infra),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_infra) REFERENCES Infraestructura (id_infra) ON DELETE CASCADE
    );

-- Taula 5: Espècies (Catàleg)
DROP TABLE IF EXISTS Especie;

CREATE TABLE
    Especie (
        id_especie INT AUTO_INCREMENT PRIMARY KEY,
        nom_comu VARCHAR(100) NOT NULL COMMENT 'Nom comú',
        nom_cientific VARCHAR(100) COMMENT 'Nom científic'
    );

-- Taula 6: Varietats (Catàleg)
DROP TABLE IF EXISTS Varietat;

CREATE TABLE
    Varietat (
        id_varietat INT AUTO_INCREMENT PRIMARY KEY,
        id_especie INT NOT NULL,
        nom_varietat VARCHAR(100) NOT NULL,
        caracteristiques_agronomiques TEXT COMMENT 'Necessitats hídriques, hores de fred, resistència a malalties',
        cicle_vegetatiu TEXT COMMENT 'Floració, quallat, maduració, recol·lecció',
        requisits_pollinitzacio TEXT COMMENT 'Requisits de pol·linització i varietats compatibles',
        productivitat_mitjana_esperada DECIMAL(10, 2) COMMENT 'Esperada per hectàrea',
        qualitats_comercials TEXT COMMENT 'Qualitats organolèptiques i comercials del fruit',
        FOREIGN KEY (id_especie) REFERENCES Especie (id_especie) ON DELETE CASCADE
    );

-- Taula 7: Sectors (Unitat Agronòmica)
DROP TABLE IF EXISTS Sector;

CREATE TABLE
    Sector (
        id_sector INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE COMMENT 'Sector de cultiu (àrea amb una mateixa varietat i maneig)',
        descripcio TEXT,
        -- CAMP AFEEGIT PER AL REQUISIT DEL MAPA:
        coordenades_geo GEOMETRY COMMENT 'Geometria que delimita el perímetre real del sector agronòmic'
    );

-- Taula 8: Relació N:M Parcel·la-Sector (Gestió de Complexitat Geoespacial)
DROP TABLE IF EXISTS Parcela_Sector;

CREATE TABLE
    Parcela_Sector (
        id_parcela INT NOT NULL,
        id_sector INT NOT NULL,
        -- Superfície d'intersecció (útil per al càlcul de la superfície efectiva de cultiu)
        superficie_m2 DECIMAL(10, 2) NOT NULL,
        PRIMARY KEY (id_parcela, id_sector),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- Taula 9: Files dins dels sectors (Per control precís d'operacions)
DROP TABLE IF EXISTS Fila_Arbre;

CREATE TABLE
    Fila_Arbre (
        id_fila INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        numero INT NOT NULL,
        coordenades_geo GEOMETRY COMMENT 'Enregistrat per on passen les diferents files d''arbres',
        -- S'afegeix un índex únic per garantir que el número de fila sigui únic per sector
        UNIQUE (id_sector, numero),
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- Taula 10: Plantacions (Assignació de Cultiu i Configuració)
DROP TABLE IF EXISTS Plantacio;

CREATE TABLE
    Plantacio (
        id_plantacio INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        id_varietat INT NOT NULL,
        data_plantacio DATE NOT NULL,
        -- Marc de plantació dividit en dos camps numèrics 
        marc_fila DECIMAL(5, 2) NOT NULL COMMENT 'Distància entre files (m)',
        marc_arbre DECIMAL(5, 2) NOT NULL COMMENT 'Distància entre arbres (m)',
        num_arbres_plantats INT COMMENT 'Nombre total d''arbres plantats',
        origen_material TEXT COMMENT 'Viver, certificacions',
        sistema_formacio VARCHAR(100) COMMENT 'Vas, palmeta, eix central ',
        previsio_entrada_produccio DATE COMMENT 'Previsió d''entrada en producció',
        data_arrencada DATE NULL COMMENT 'Data d''arrencada del cultiu ',
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
        FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
    );

-- Taula 11: Seguiment Anual (Rendiments, Fenologia i Historial)
DROP TABLE IF EXISTS Seguiment_Anual;

CREATE TABLE
    Seguiment_Anual (
        id_plantacio INT NOT NULL COMMENT 'Clau forana a la plantació',
        any YEAR NOT NULL COMMENT 'Any de la campanya del seguiment',
        estat_fenologic VARCHAR(100) COMMENT 'Estat fenològic actual',
        creixement_vegetatiu VARCHAR(100) COMMENT 'Creixement vegetatiu',
        rendiment_kg_ha DECIMAL(10, 2) COMMENT 'Rendiments obtinguts en cada campanya',
        -- CLAU PRIMÀRIA COMPOSTA
        PRIMARY KEY (id_plantacio, any),
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
    );

-- Taula 16: Catàleg Centralitzat de Productes Químics (Fitosanitaris, Fertilitzants, Herbicides)
DROP TABLE IF EXISTS Producte_Quimic;

CREATE TABLE
    Producte_Quimic (
        id_producte INT AUTO_INCREMENT PRIMARY KEY,
        nom_comercial VARCHAR(255) NOT NULL,
        tipus ENUM ('Fitosanitari', 'Fertilitzant', 'Herbicida') NOT NULL,
        num_registre VARCHAR(50) COMMENT 'Número de registre oficial',
        fabricant VARCHAR(100),
        termini_seguretat_dies INT COMMENT 'Dies de seguretat abans de collita',
        classificacio_tox VARCHAR(50) COMMENT 'Toxicològica i ecotoxicològica',
        compatibilitat_eco BOOLEAN COMMENT 'Compatible amb producció ecològica',
        dosi_max_ha DECIMAL(10, 2) COMMENT 'Dosi màxima legal per hectàrea',
        fitxa_seguretat_link TEXT COMMENT 'Enllaç al documentació del producte'
    );

-- Taula 17: Catàleg de Matèries Actives
DROP TABLE IF EXISTS Materia_Activa;

CREATE TABLE
    Materia_Activa (
        id_materia_activa INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        espectre_accio TEXT COMMENT 'Plagues/malalties que controla',
        modes_accio TEXT COMMENT 'Per evitar resistències (Requisit Herbicides)',
        requeriments_normatius TEXT COMMENT 'Restriccions d''ús legals'
    );

-- Taula 18: Relació N:M Producte-Matèria Activa
DROP TABLE IF EXISTS Producte_MA;

CREATE TABLE
    Producte_MA (
        id_producte INT NOT NULL,
        id_materia_activa INT NOT NULL,
        concentracio DECIMAL(5, 2) COMMENT 'Percentatge o unitat de concentració',
        PRIMARY KEY (id_producte, id_materia_activa),
        FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE CASCADE,
        FOREIGN KEY (id_materia_activa) REFERENCES Materia_Activa (id_materia_activa) ON DELETE CASCADE
    );

-- Taula 19: Gestió d'Estocs per Lot (permet la traçabilitat de producte)
DROP TABLE IF EXISTS Inventari_Estoc;

CREATE TABLE
    Inventari_Estoc (
        id_estoc INT AUTO_INCREMENT PRIMARY KEY,
        id_producte INT NOT NULL,
        num_lot VARCHAR(100) NOT NULL,
        quantitat_disponible DECIMAL(10, 2) NOT NULL,
        unitat_mesura VARCHAR(20) NOT NULL,
        data_caducitat DATE,
        ubicacio_magatzem VARCHAR(100),
        data_compra DATE,
        proveidor VARCHAR(100),
        preu_adquisicio DECIMAL(10, 2) COMMENT 'Per al control de costos',
        FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE RESTRICT
    );

-- Taula 12: Registre d'Aplicacions de Camp (Operacions i Tractaments)
DROP TABLE IF EXISTS Aplicacio;

CREATE TABLE
    Aplicacio (
        id_aplicacio INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        -- *** CAMPOS RELACIONATS AMB PRODUCTE ELIMINATS ***
        -- data_event, tipus_event, descripcio es mantenen
        data_event DATE NOT NULL,
        tipus_event VARCHAR(100) COMMENT 'Poda, aclarida, tractaments, fertilitzacions, etc.',
        descripcio TEXT,
        -- Només es mantenen els camps globals de l'aplicació
        volum_caldo DECIMAL(10, 2) COMMENT 'Volum de caldo utilitzat/preparat (si aplica)',
        -- Dades d'execució
        maquinaria_utilitzada VARCHAR(100) COMMENT 'Equip o maquinària utilitzada en l''aplicació',
        operari_carnet VARCHAR(50) COMMENT 'Nom i/o número de carnet de l''aplicador responsable',
        condicions_ambientals TEXT COMMENT 'Temperatura, vent, etc. durant l''aplicació',
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
        -- La FK a Inventari_Estoc (id_estoc) s'ha mogut a la taula d'intersecció (Detall_Aplicacio_Producte)
    );

-- Taula 12.1: Detall de Consum de Producte per Aplicació (PLA_APLICAT)
-- Implementa la relació N:M entre Aplicacio i Inventari_Estoc
DROP TABLE IF EXISTS Detall_Aplicacio_Producte;

CREATE TABLE
    Detall_Aplicacio_Producte (
        id_aplicacio INT NOT NULL,
        id_estoc INT NOT NULL COMMENT 'Lot específic utilitzat (FK a Inventari_Estoc)',
        -- Detall de l'ús del producte (Quantitat, dosi) - Camps moguts de la taula Aplicacio
        dosi_aplicada DECIMAL(10, 2) COMMENT 'Dosi real aplicada per unitat (ha o volum)',
        quantitat_consumida_total DECIMAL(10, 2) COMMENT 'Quantitat total d''estoc consumida',
        PRIMARY KEY (id_aplicacio, id_estoc),
        FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE,
        FOREIGN KEY (id_estoc) REFERENCES Inventari_Estoc (id_estoc) ON DELETE RESTRICT
    );

-- Taula 13: Inversions (Control de costos)
DROP TABLE IF EXISTS Inversio;

CREATE TABLE
    Inversio (
        id_inversio INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        data_inversio DATE NOT NULL,
        concepte VARCHAR(255) NOT NULL,
        import DECIMAL(10, 2) NOT NULL,
        vida_util_anys INT COMMENT 'Períodes d''amortització',
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
    );

-- Taula 20: Registre d'Observacions de Camp i Lectures de Trampes
DROP TABLE IF EXISTS Monitoratge_Plaga;

CREATE TABLE
    Monitoratge_Plaga (
        id_monitoratge INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NULL,
        data_observacio DATETIME NOT NULL,
        tipus_problema ENUM ('Plaga', 'Malaltia', 'Deficiencia', 'Mala Herba') NOT NULL,
        descripcio_breu VARCHAR(255),
        nivell_poblacio DECIMAL(5, 2) COMMENT 'Nivell de població o índex de dany',
        llindar_intervencio_assolit BOOLEAN COMMENT 'Indica si s''ha de realitzar un tractament',
        coordenades_geo GEOMETRY COMMENT 'Geolocalització de l''observació',
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- Taula 14: Incidències (Plagues, Gelades, etc.)
DROP TABLE IF EXISTS Incidencia;

CREATE TABLE
    Incidencia (
        id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
        id_parcela INT NULL,
        id_sector INT NULL,
        id_fila INT NULL,
        data_incidencia DATE NOT NULL,
        tipus_incidencia VARCHAR(100) COMMENT 'Problemes fitosanitaris',
        descripcio TEXT,
        id_monitoratge INT NULL COMMENT 'Vincula la incidència amb l''observació (Trampa/Observació de camp)',
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
        -- CORRECCIÓ: Referència a Fila_Arbre en lloc de Fila
        FOREIGN KEY (id_fila) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
        FOREIGN KEY (id_monitoratge) REFERENCES Monitoratge_Plaga (id_monitoratge) ON DELETE SET NULL
    );

-- S'elimina l'ALTER TABLE, ja que el camp id_monitoratge ja està inclòs a la definició de la taula Incidencia
-- Taula 15: Documentació i fotos (Centralitzada)
DROP TABLE IF EXISTS Documentacio;

CREATE TABLE
    Documentacio (
        id_document INT AUTO_INCREMENT PRIMARY KEY,
        id_parcela INT NULL COMMENT 'Escriptures, certificacions, permisos',
        id_plantacio INT NULL,
        id_varietat INT NULL COMMENT 'Fotografies identificatives de l''arbre, flor i fruit ',
        data_doc DATE,
        tipus_doc ENUM (
            'escriptura',
            'certificacio',
            'permis',
            'foto',
            'altres'
        ) NOT NULL,
        enllac_arxiu TEXT NOT NULL,
        descripcio TEXT,
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE,
        FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
        -- NOTA: La restricció per assegurar que ALMENYS un FK sigui NOT NULL s'hauria de fer a nivell d'aplicació o amb un TRIGGER
    );

-- Taula 21: Emmagatzematge de Resultats d'Anàlisis (Sòl, Aigua, Foliars)
DROP TABLE IF EXISTS Analisi_Laboratori;

CREATE TABLE
    Analisi_Laboratori (
        id_analisi INT AUTO_INCREMENT PRIMARY KEY,
        id_parcela INT NOT NULL,
        data_mostreig DATE NOT NULL,
        tipus_analisi ENUM ('Sol', 'Aigua', 'Foliar') NOT NULL,
        resultats_json JSON COMMENT 'Emmagatzema els paràmetres (pH, N, P, K, etc.)',
        objectiu TEXT COMMENT 'Motiu de l''anàlisi',
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE
    );

-- Taula 22: Detall de l'Aplicació per Fila (Agricultura de Precisió)
DROP TABLE IF EXISTS Fila_Aplicacio;

CREATE TABLE
    Fila_Aplicacio (
        id_fila_aplicada INT NOT NULL COMMENT 'Identificador de la fila on s’ha aplicat el tractament (FK a Fila_Arbre)',
        id_aplicacio INT NOT NULL COMMENT 'Aplicació o tractament concret realitzat (FK a Aplicacio)',
        id_sector INT NOT NULL COMMENT 'Sector al qual pertany la fila (FK a Sector)',
        -- Clau primària composta
        PRIMARY KEY (id_fila_aplicada, id_aplicacio),
        -- Claus externes
        FOREIGN KEY (id_fila_aplicada) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
        FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE,
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
        -- Dades de l’execució
        data_inici DATETIME NOT NULL COMMENT 'Hora d’inici de l’aplicació a la fila',
        data_fi DATETIME NULL COMMENT 'Hora de finalització de l’aplicació a la fila',
        percentatge_complet DECIMAL(5, 2) DEFAULT 100 COMMENT 'Percentatge de la fila tractada (0–100%)',
        longitud_tratada_m DECIMAL(10, 2) NULL COMMENT 'Longitud efectiva tractada en metres',
        -- S'especifica el tipus POINT correctament per a MySQL
        coordenada_final POINT SRID 4326 NULL COMMENT 'Coordenada GPS on es va aturar l’aplicació',
        estat ENUM ('pendent', 'en procés', 'completada', 'aturada') DEFAULT 'completada' COMMENT 'Estat de l’aplicació a la fila',
        observacions TEXT COMMENT 'Comentaris o incidències durant l’aplicació'
    );

-- Índex espacial per millorar consultes de geolocalització
CREATE SPATIAL INDEX idx_fila_aplicacio_geo ON Fila_Aplicacio (coordenada_final);

Tractaments collita,
sanitat ho controla,
han de poder veure quins tractaments es fan
-- ******************************************************
-- A1. Mòdul: Catàleg i Operació de Collita
-- ******************************************************
-- Taula 23: Catàleg d'Equips o Colles de Recol·lecció
DROP TABLE IF EXISTS Equip_Recollector;

CREATE TABLE
    Equip_Recollector (
        id_equip INT AUTO_INCREMENT PRIMARY KEY,
        nom_equip VARCHAR(100) NOT NULL,
        tipus ENUM ('Intern', 'Extern', 'Maquinaria') NOT NULL
    );

-- Taula 24: Registre d'Operacions de Collita (L'esdeveniment físic)
DROP TABLE IF EXISTS Collita_Operacio;

CREATE TABLE
    Collita_Operacio (
        id_collita INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        id_equip INT NULL, -- L'equip que va fer l'operació
        data_inici DATETIME NOT NULL,
        data_fi DATETIME NULL,
        condicions_ambientals TEXT COMMENT 'Temperatura, humitat durant la collita',
        estat_maduresa VARCHAR(100) COMMENT 'Grau de maduresa del fruit en el moment de la collita',
        observacions TEXT COMMENT 'Incidències i observacions',
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE,
        FOREIGN KEY (id_equip) REFERENCES Equip_Recollector (id_equip) ON DELETE SET NULL
    );

-- Taula 25: Lot de Producció (Traçabilitat i Quantitat)
DROP TABLE IF EXISTS Lot_Produccio;

CREATE TABLE
    Lot_Produccio (
        id_lot INT AUTO_INCREMENT PRIMARY KEY,
        id_collita INT NOT NULL,
        codi_qr_lot VARCHAR(100) NOT NULL UNIQUE COMMENT 'Identificador de traçabilitat (p. ex., Codi QR o Barres)',
        unitat_mesura VARCHAR(20) NOT NULL,
        quantitat_recollida DECIMAL(10, 2) NOT NULL COMMENT 'Pes brut o nombre d''unitats recollides',
        desti_inicial VARCHAR(100) COMMENT 'Magatzem o punt de recepció',
        FOREIGN KEY (id_collita) REFERENCES Collita_Operacio (id_collita) ON DELETE CASCADE
    );

-- ******************************************************
-- B2. Mòdul: Control de Qualitat
-- ******************************************************
-- Taula 26: Definició dels Protocols de Qualitat (Què s'ha de mesurar per a cada varietat)
DROP TABLE IF EXISTS Protocol_Qualitat;

CREATE TABLE
    Protocol_Qualitat (
        id_protocol INT AUTO_INCREMENT PRIMARY KEY,
        id_varietat INT NOT NULL,
        nom_protocol VARCHAR(100) NOT NULL,
        paràmetres_esperats JSON COMMENT 'Ex: {calibre_min: "75mm", fermesa_min: "5kg"}',
        FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
    );

-- Taula 27: Registre dels Controls de Qualitat (Resultats de la inspecció d'un Lot)
DROP TABLE IF EXISTS Control_Qualitat;

CREATE TABLE
    Control_Qualitat (
        id_control INT AUTO_INCREMENT PRIMARY KEY,
        id_lot INT NOT NULL,
        id_protocol INT NOT NULL,
        data_analisi DATETIME NOT NULL,
        resultats_analisi JSON COMMENT 'Resultats mesurats del calibre, fermesa, etc.',
        percentatge_rebuig DECIMAL(5, 2) COMMENT 'Percentatge de fruita que va a rebuig',
        motiu_rebuig TEXT COMMENT 'Motius del rebuig',
        analista VARCHAR(100),
        FOREIGN KEY (id_lot) REFERENCES Lot_Produccio (id_lot) ON DELETE CASCADE,
        FOREIGN KEY (id_protocol) REFERENCES Protocol_Qualitat (id_protocol) ON DELETE RESTRICT
    );