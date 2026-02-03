-- ******************************************************
-- 1: Parcel·les
DROP TABLE IF EXISTS parcela;

CREATE TABLE
    parcela (
        id_parcela INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL COMMENT 'Identificador unic i nom descriptiu',
        coordenades_geo GEOMETRY NOT NULL COMMENT 'Coordenades GPS que delimiten el perimetre',
        superficie_ha DECIMAL(10, 2) NOT NULL COMMENT 'Superficie total calculada automaticament',
        pendent VARCHAR(20) COMMENT 'Factor clau per a la gestio de l''aigua',
        orientacio VARCHAR(20) COMMENT 'Factor clau per a l''exposicio solar'
    );

-- 2: Caracteristiques del sol
DROP TABLE IF EXISTS caracteristiques_sol;

CREATE TABLE
    caracteristiques_sol (
        id_sector INT NOT NULL COMMENT 'Clau forana al sector analitzat',
        data_analisi DATE NOT NULL COMMENT 'Data en què es va realitzar l''anàlisi',
        textura VARCHAR(50) COMMENT 'Tipus de textura del sòl',
        pH DECIMAL(4, 2) COMMENT 'pH del sòl (0-14)',
        materia_organica DECIMAL(5, 2) COMMENT 'Percentatge de matèria orgànica',
        N DECIMAL(5, 2) COMMENT 'Nitrogen disponible (ppm)',
        P DECIMAL(5, 2) COMMENT 'Fòsfor disponible (ppm)',
        K DECIMAL(5, 2) COMMENT 'Potassi disponible (ppm)',
        PRIMARY KEY (id_sector, data_analisi),
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE
    );

-- 3: Infraestructures
DROP TABLE IF EXISTS infraestructura;

CREATE TABLE
    infraestructura (
        id_infra INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        tipus ENUM ('reg', 'camin', 'tanca', 'edificacio', 'altres') NOT NULL COMMENT 'Sistemes de reg, camins d''acces, tanques'
    );

-- 4: Parcela_Infraestructura
DROP TABLE IF EXISTS parcela_infraestructura;

CREATE TABLE
    parcela_infraestructura (
        id_parcela INT NOT NULL,
        id_infra INT NOT NULL,
        PRIMARY KEY (id_parcela, id_infra),
        FOREIGN KEY (id_parcela) REFERENCES parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_infra) REFERENCES infraestructura (id_infra) ON DELETE CASCADE
    );

-- 5: Especies
DROP TABLE IF EXISTS especie;

CREATE TABLE
    especie (
        id_especie INT AUTO_INCREMENT PRIMARY KEY,
        nom_comu VARCHAR(100) NOT NULL COMMENT 'Nom comu',
        nom_cientific VARCHAR(100) COMMENT 'Nom cientific'
    );

-- 6: Varietats
DROP TABLE IF EXISTS varietat;

CREATE TABLE
    varietat (
        id_varietat INT AUTO_INCREMENT PRIMARY KEY,
        id_especie INT NOT NULL,
        nom_varietat VARCHAR(100) NOT NULL,
        caracteristiques_agronomiques TEXT COMMENT 'Necessitats hidriques, hores de fred, resistencia a malalties',
        cicle_vegetatiu TEXT COMMENT 'Floracio, quallat, maduracio, recol·leccio',
        requisits_pollinitzacio TEXT COMMENT 'Requisits de pol·linitzacio i varietats compatibles',
        productivitat_mitjana_esperada DECIMAL(10, 2) COMMENT 'Esperada per hectarea',
        qualitats_comercials TEXT COMMENT 'Qualitats organoleptiques i comercials del fruit',
        FOREIGN KEY (id_especie) REFERENCES especie (id_especie) ON DELETE CASCADE
    );

-- 7: Sectors
DROP TABLE IF EXISTS sector;

CREATE TABLE
    sector (
        id_sector INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE COMMENT 'Sector de cultiu (area amb una mateixa varietat i maneig)',
        descripcio TEXT,
        coordenades_geo GEOMETRY COMMENT 'Geometria que delimita el perimetre real del sector agronomic'
    );

-- 8: Parcela_Sector
DROP TABLE IF EXISTS parcela_sector;

CREATE TABLE
    parcela_sector (
        id_parcela INT NOT NULL,
        id_sector INT NOT NULL,
        superficie_m2 DECIMAL(10, 2) NOT NULL,
        PRIMARY KEY (id_parcela, id_sector),
        FOREIGN KEY (id_parcela) REFERENCES parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE
    );

-- 9: Fila_Arbre
DROP TABLE IF EXISTS fila_arbre;

CREATE TABLE
    fila_arbre (
        id_fila INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        numero INT NOT NULL,
        coordenades_geo GEOMETRY COMMENT 'Enregistrat per on passen les diferents files d''arbres',
        UNIQUE (id_sector, numero),
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE
    );

-- 10: Plantacio
DROP TABLE IF EXISTS plantacio;

CREATE TABLE
    plantacio (
        id_plantacio INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        id_varietat INT NOT NULL,
        data_plantacio DATE NOT NULL,
        marc_fila DECIMAL(5, 2) NOT NULL COMMENT 'Distancia entre files (m)',
        marc_arbre DECIMAL(5, 2) NOT NULL COMMENT 'Distancia entre arbres (m)',
        num_arbres_plantats INT COMMENT 'Nombre total d''arbres plantats',
        origen_material TEXT COMMENT 'Viver, certificacions',
        sistema_formacio VARCHAR(100) COMMENT 'Vas, palmeta, eix central',
        previsio_entrada_produccio DATE COMMENT 'Previsio d''entrada en produccio',
        data_arrencada DATE NULL COMMENT 'Data d''arrencada del cultiu',
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE,
        FOREIGN KEY (id_varietat) REFERENCES varietat (id_varietat) ON DELETE CASCADE
    );

-- 11: Seguiment_Anual
DROP TABLE IF EXISTS seguiment_anual;

CREATE TABLE
    seguiment_anual (
        id_plantacio INT NOT NULL COMMENT 'Clau forana a la plantacio',
        any YEAR NOT NULL COMMENT 'Any de la campanya del seguiment',
        estat_fenologic VARCHAR(100) COMMENT 'Estat fenologic actual',
        creixement_vegetatiu VARCHAR(100) COMMENT 'Creixement vegetatiu',
        rendiment_kg_ha DECIMAL(10, 2) COMMENT 'Rendiments obtinguts en cada campanya',
        PRIMARY KEY (id_plantacio, any),
        FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE CASCADE
    );

-- 12: Producte_Quimic
DROP TABLE IF EXISTS producte_quimic;

CREATE TABLE
    producte_quimic (
        id_producte INT AUTO_INCREMENT PRIMARY KEY,
        nom_comercial VARCHAR(255) NOT NULL,
        tipus ENUM ('Fitosanitari', 'Fertilitzant', 'Herbicida') NOT NULL,
        num_registre VARCHAR(50) COMMENT 'Numero de registre oficial',
        fabricant VARCHAR(100),
        termini_seguretat_dies INT COMMENT 'Dies de seguretat abans de collita',
        classificacio_tox VARCHAR(50) COMMENT 'Toxicologica i ecotoxicologica',
        compatibilitat_eco BOOLEAN COMMENT 'Compatible amb produccio ecologica',
        dosi_max_ha DECIMAL(10, 2) COMMENT 'Dosi maxima legal per hectarea',
        fitxa_seguretat_link TEXT COMMENT 'Enllac al documentacio del producte'
    );

-- 13: Materia_Activa
DROP TABLE IF EXISTS materia_activa;

CREATE TABLE
    materia_activa (
        id_materia_activa INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        espectre_accio TEXT COMMENT 'Plagues/malalties que controla',
        modes_accio TEXT COMMENT 'Per evitar resistencies (Requisit Herbicides)',
        requeriments_normatius TEXT COMMENT 'Restriccions d''us legals'
    );

-- 14: Producte_MA
DROP TABLE IF EXISTS producte_ma;

CREATE TABLE
    producte_ma (
        id_producte INT NOT NULL,
        id_materia_activa INT NOT NULL,
        concentracio DECIMAL(5, 2) COMMENT 'Percentatge o unitat de concentracio',
        PRIMARY KEY (id_producte, id_materia_activa),
        FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE CASCADE,
        FOREIGN KEY (id_materia_activa) REFERENCES materia_activa (id_materia_activa) ON DELETE CASCADE
    );

-- 15: Inventari_Estoc
DROP TABLE IF EXISTS inventari_estoc;

CREATE TABLE
    inventari_estoc (
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
        FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE RESTRICT
    );


-- 16: Aplicacio
DROP TABLE IF EXISTS aplicacio;

CREATE TABLE
    aplicacio (
        id_aplicacio INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        data_event DATE NOT NULL,
        hora_inici_planificada DATETIME COMMENT 'Hora planificada d’inici de la tasca',
        hora_fi_planificada DATETIME COMMENT 'Hora planificada de finalització de la tasca',
        tipus_event VARCHAR(100) COMMENT 'Poda, aclarida, tractaments, fertilitzacions, etc.',
        descripcio TEXT,
        volum_caldo DECIMAL(10, 2) COMMENT 'Volum de caldo utilitzat/preparat (si aplica)',
        maquinaria_utilitzada VARCHAR(100) COMMENT 'Equip o maquinaria utilitzada en l''aplicacio',
        id_treballador INT COMMENT 'Operari responsable de l''aplicacio',
        condicions_ambientals TEXT COMMENT 'Temperatura, vent, etc. durant l''aplicacio',
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE,
        FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE SET NULL
    );

-- 17: Detall_Aplicacio_Producte
DROP TABLE IF EXISTS detall_aplicacio_producte;

CREATE TABLE
    detall_aplicacio_producte (
        id_aplicacio INT NOT NULL,
        id_estoc INT NOT NULL COMMENT 'Lot especific utilitzat',
        dosi_aplicada DECIMAL(10, 2) COMMENT 'Dosi real aplicada per unitat',
        quantitat_consumida_total DECIMAL(10, 2) COMMENT 'Quantitat total d''estoc consumida',
        PRIMARY KEY (id_aplicacio, id_estoc),
        FOREIGN KEY (id_aplicacio) REFERENCES aplicacio (id_aplicacio) ON DELETE CASCADE,
        FOREIGN KEY (id_estoc) REFERENCES inventari_estoc (id_estoc) ON DELETE RESTRICT
    );

-- 18: Fila_Aplicacio (SINTAXI I RESTRICCIÓ CORREGIDA)
DROP TABLE IF EXISTS fila_aplicacio;

CREATE TABLE
    fila_aplicacio (
        id_fila_aplicada INT NOT NULL COMMENT 'Identificador de la fila on s’ha aplicat el tractament',
        id_aplicacio INT NOT NULL COMMENT 'Aplicacio o tractament concret realitzat',
        id_treballador INT COMMENT 'Treballador responsable de l’aplicacio a la fila',
        data_inici DATETIME NOT NULL COMMENT 'Hora d’inici de l’aplicacio a la fila',
        data_fi DATETIME NULL COMMENT 'Hora de finalitzacio de l’aplicacio a la fila',
        hores_treballades DECIMAL(5, 2) COMMENT 'Temps real treballat per aquest treballador a la fila',
        percentatge_complet DECIMAL(5, 2) DEFAULT 100 COMMENT 'Percentatge de la fila tractada (0–100%)',
        longitud_tratada_m DECIMAL(10, 2) NULL COMMENT 'Longitud efectiva tractada en metres',
        coordenada_final POINT NOT NULL COMMENT 'Coordenada GPS on es va aturar l’aplicacio',
        estat ENUM ('pendent', 'en proces', 'completada', 'aturada') DEFAULT 'completada' COMMENT 'Estat de l’aplicacio a la fila',
        observacions TEXT COMMENT 'Comentaris o incidencies durant l’aplicacio',
        PRIMARY KEY (id_fila_aplicada, id_aplicacio, id_treballador),
        FOREIGN KEY (id_fila_aplicada) REFERENCES fila_arbre (id_fila) ON DELETE CASCADE,
        FOREIGN KEY (id_aplicacio) REFERENCES aplicacio (id_aplicacio) ON DELETE CASCADE,
        FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE SET NULL
    );

CREATE SPATIAL INDEX idx_fila_aplicacio_geo ON Fila_Aplicacio (coordenada_final);

-- 19: Inversio
DROP TABLE IF EXISTS inversio;

CREATE TABLE
    inversio (
        id_inversio INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        data_inversio DATE NOT NULL,
        concepte VARCHAR(255) NOT NULL,
        import DECIMAL(10, 2) NOT NULL,
        vida_util_anys INT COMMENT 'Periodos d''amortitzacio',
        FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE CASCADE
    );

-- 20: Monitoratge_Plaga
DROP TABLE IF EXISTS monitoratge_plaga;

CREATE TABLE
    monitoratge_plaga (
        id_monitoratge INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NULL,
        data_observacio DATETIME NOT NULL,
        tipus_problema ENUM ('Plaga', 'Malaltia', 'Deficiencia', 'Mala Herba') NOT NULL,
        descripcio_breu VARCHAR(255),
        nivell_poblacio DECIMAL(5, 2) COMMENT 'Nivell de poblacio o index de dany',
        llindar_intervencio_assolit BOOLEAN COMMENT 'Indica si s''ha de realitzar un tractament',
        coordenades_geo GEOMETRY COMMENT 'Geolocalitzacio de l''observacio',
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE
    );

-- 21: Incidencia
DROP TABLE IF EXISTS incidencia;

CREATE TABLE
    incidencia (
        id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
        id_monitoratge INT NOT NULL,
        data_registre DATETIME NOT NULL,
        tipus ENUM (
            'Plaga',
            'Malaltia',
            'Deficiencia',
            'Mala Herba',
            'Altres'
        ) NOT NULL,
        descripcio TEXT,
        gravetat ENUM ('Baixa', 'Mitjana', 'Alta', 'Critica') DEFAULT 'Mitjana',
        accio_correctiva TEXT,
        estat ENUM ('pendent', 'en_proces', 'resolt') DEFAULT 'pendent',
        FOREIGN KEY (id_monitoratge) REFERENCES monitoratge_plaga (id_monitoratge) ON DELETE CASCADE
    );

-- 22: Protocol_Tractament
DROP TABLE IF EXISTS protocol_tractament;

CREATE TABLE
    protocol_tractament (
        id_protocol INT AUTO_INCREMENT PRIMARY KEY,
        nom_protocol VARCHAR(100) NOT NULL,
        descripcio TEXT,
        productes_json JSON COMMENT 'Llista de productes i dosis en format JSON',
        condicions_ambientals TEXT COMMENT 'Condicions ideals per aplicar el protocol'
    );

-- 23: Incidencia_Protocol
DROP TABLE IF EXISTS incidencia_protocol;

CREATE TABLE
    incidencia_protocol (
        id_incidencia INT NOT NULL,
        id_protocol INT NOT NULL,
        PRIMARY KEY (id_incidencia, id_protocol),
        FOREIGN KEY (id_incidencia) REFERENCES incidencia (id_incidencia) ON DELETE CASCADE,
        FOREIGN KEY (id_protocol) REFERENCES protocol_tractament (id_protocol) ON DELETE RESTRICT
    );

-- 24: Treballador
DROP TABLE IF EXISTS treballador;

CREATE TABLE
    treballador (
        id_treballador INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        cognoms VARCHAR(100),
        dni VARCHAR(20) NOT NULL,
        data_naixement DATE NOT NULL,
        rol ENUM (
            'Operari',
            'Supervisor',
            'Responsable',
            'Tècnic',
            'Altres'
        ) DEFAULT 'Operari',
        telefon VARCHAR(20),
        email VARCHAR(100),
        data_alta DATE,
        tipus_contracte ENUM ('Indefinit', 'Temporal', 'Practicum', 'Altres') NOT NULL,
        estat ENUM ('actiu', 'inactiu', 'vacances', 'baix') DEFAULT 'actiu',
        contracte_pdf TEXT COMMENT 'Enllaç o ruta al document digital del contracte',
        certificat_pdf TEXT COMMENT 'Enllaços a certificacions professionals',
        permis_pdf TEXT COMMENT 'Enllaços a permisos de treball o autoritzacions'
    );

-- 25: Maquinaria
DROP TABLE IF EXISTS maquinaria;

CREATE TABLE
    maquinaria (
        id_maquinaria INT AUTO_INCREMENT PRIMARY KEY,
        nom_maquina VARCHAR(100) NOT NULL,
        tipus ENUM (
            'Tractor',
            'Pulveritzador',
            'Poda',
            'Cistella',
            'Altres'
        ) NOT NULL,
        any_fabricacio YEAR,
        manteniment_json JSON COMMENT 'Registre de manteniment i revisions'
    );

-- 26: Maquinaria_Applicacio
DROP TABLE IF EXISTS maquinaria_aplicacio;

CREATE TABLE
    maquinaria_aplicacio (
        id_maquinaria INT NOT NULL,
        id_aplicacio INT NOT NULL,
        hores_utilitzades DECIMAL(5, 2) COMMENT 'Durada de l’ús per aplicació',
        PRIMARY KEY (id_maquinaria, id_aplicacio),
        FOREIGN KEY (id_maquinaria) REFERENCES maquinaria (id_maquinaria) ON DELETE RESTRICT,
        FOREIGN KEY (id_aplicacio) REFERENCES aplicacio (id_aplicacio) ON DELETE CASCADE
    );

-- 27: Collita
DROP TABLE IF EXISTS collita;

CREATE TABLE
    collita (
        id_collita INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        data_collita DATE NOT NULL,
        quantitat_kg DECIMAL(10, 2) NOT NULL,
        qualitat ENUM ('Extra', 'Primera', 'Segona', 'Industrial') DEFAULT 'Primera',
        observacions TEXT,
        id_treballador_responsable INT COMMENT 'Treballador responsable de la recol·lecció',
        FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE CASCADE,
        FOREIGN KEY (id_treballador_responsable) REFERENCES treballador (id_treballador) ON DELETE SET NULL
    );

-- 28: Lot_Produccio
DROP TABLE IF EXISTS lot_produccio;

CREATE TABLE
    lot_produccio (
        id_lot INT AUTO_INCREMENT PRIMARY KEY,
        id_collita INT NOT NULL,
        identificador VARCHAR(100) NOT NULL UNIQUE,
        data_processat DATE,
        pes_kg DECIMAL(10, 2),
        qualitat ENUM ('Extra', 'Primera', 'Segona', 'Industrial') DEFAULT 'Primera',
        desti VARCHAR(255) COMMENT 'Mercat, transformació, exportació o client final',
        id_parcela INT COMMENT 'Parcel·la d’origen del lot',
        id_sector INT COMMENT 'Sector d’origen del lot',
        id_fila INT COMMENT 'Fila d’origen si escau',
        FOREIGN KEY (id_collita) REFERENCES collita (id_collita) ON DELETE CASCADE,
        FOREIGN KEY (id_parcela) REFERENCES parcela (id_parcela) ON DELETE SET NULL,
        FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE SET NULL,
        FOREIGN KEY (id_fila) REFERENCES fila_arbre (id_fila) ON DELETE SET NULL
    );

-- 29: Control_Qualitat
DROP TABLE IF EXISTS control_qualitat;

CREATE TABLE
    control_qualitat (
        id_control INT AUTO_INCREMENT PRIMARY KEY,
        id_lot INT NOT NULL,
        data_control DATE NOT NULL,
        resultat ENUM ('Acceptat', 'Rebutjat', 'Condicional') DEFAULT 'Acceptat',
        comentaris TEXT,
        FOREIGN KEY (id_lot) REFERENCES lot_produccio (id_lot) ON DELETE CASCADE
    );

-- 30: Proveidor
DROP TABLE IF EXISTS proveidor;

CREATE TABLE
    proveidor (
        id_proveidor INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        telefon VARCHAR(20),
        email VARCHAR(100),
        adreca TEXT,
        tipus ENUM (
            'Fitosanitari',
            'Fertilitzant',
            'Semilla',
            'Maquinaria',
            'Altres'
        ) DEFAULT 'Altres'
    );

-- 31: Compra_Producte
DROP TABLE IF EXISTS compra_producte;

CREATE TABLE
    compra_producte (
        id_compra INT AUTO_INCREMENT PRIMARY KEY,
        id_proveidor INT NOT NULL,
        id_producte INT NOT NULL,
        data_compra DATE NOT NULL,
        quantitat DECIMAL(10, 2),
        preu_unitari DECIMAL(10, 2),
        num_lot VARCHAR(100),
        FOREIGN KEY (id_proveidor) REFERENCES proveidor (id_proveidor) ON DELETE RESTRICT,
        FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE RESTRICT
    );

-- 32: Log_Accions
DROP TABLE IF EXISTS log_accions;

CREATE TABLE
    log_accions (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_treballador INT NOT NULL,
        data_hora DATETIME NOT NULL,
        accio VARCHAR(255) NOT NULL,
        taula_afectada VARCHAR(100),
        id_registre_afectat INT,
        comentaris TEXT,
        FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE RESTRICT
    );