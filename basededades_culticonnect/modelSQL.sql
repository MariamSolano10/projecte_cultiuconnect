-- ******************************************************
-- 1: Parcel·les
DROP TABLE IF EXISTS Parcela;

CREATE TABLE
    Parcela (
        id_parcela INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL COMMENT 'Identificador unic i nom descriptiu',
        coordenades_geo GEOMETRY NOT NULL COMMENT 'Coordenades GPS que delimiten el perimetre',
        superficie_ha DECIMAL(10, 2) NOT NULL COMMENT 'Superficie total calculada automaticament',
        pendent VARCHAR(20) COMMENT 'Factor clau per a la gestio de l''aigua',
        orientacio VARCHAR(20) COMMENT 'Factor clau per a l''exposicio solar'
    );

-- 2: Caracteristiques del sol
DROP TABLE IF EXISTS Caracteristiques_Sol;

CREATE TABLE
    Caracteristiques_Sol (
        id_parcela INT NOT NULL COMMENT 'Clau Forana a la parcela analitzada',
        data_analisis DATE NOT NULL COMMENT 'Data en que es va realitzar l''analisi',
        textura VARCHAR(50),
        pH DECIMAL(4, 2),
        materia_organica DECIMAL(5, 2),
        PRIMARY KEY (id_parcela, data_analisis),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE
    );

-- 3: Infraestructures
DROP TABLE IF EXISTS Infraestructura;

CREATE TABLE
    Infraestructura (
        id_infra INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        tipus ENUM ('reg', 'camin', 'tanca', 'edificacio', 'altres') NOT NULL COMMENT 'Sistemes de reg, camins d''acces, tanques'
    );

-- 4: Parcela_Infraestructura
DROP TABLE IF EXISTS Parcela_Infraestructura;

CREATE TABLE
    Parcela_Infraestructura (
        id_parcela INT NOT NULL,
        id_infra INT NOT NULL,
        PRIMARY KEY (id_parcela, id_infra),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_infra) REFERENCES Infraestructura (id_infra) ON DELETE CASCADE
    );

-- 5: Especies
DROP TABLE IF EXISTS Especie;

CREATE TABLE
    Especie (
        id_especie INT AUTO_INCREMENT PRIMARY KEY,
        nom_comu VARCHAR(100) NOT NULL COMMENT 'Nom comu',
        nom_cientific VARCHAR(100) COMMENT 'Nom cientific'
    );

-- 6: Varietats
DROP TABLE IF EXISTS Varietat;

CREATE TABLE
    Varietat (
        id_varietat INT AUTO_INCREMENT PRIMARY KEY,
        id_especie INT NOT NULL,
        nom_varietat VARCHAR(100) NOT NULL,
        caracteristiques_agronomiques TEXT COMMENT 'Necessitats hidriques, hores de fred, resistencia a malalties',
        cicle_vegetatiu TEXT COMMENT 'Floracio, quallat, maduracio, recol·leccio',
        requisits_pollinitzacio TEXT COMMENT 'Requisits de pol·linitzacio i varietats compatibles',
        productivitat_mitjana_esperada DECIMAL(10, 2) COMMENT 'Esperada per hectarea',
        qualitats_comercials TEXT COMMENT 'Qualitats organoleptiques i comercials del fruit',
        FOREIGN KEY (id_especie) REFERENCES Especie (id_especie) ON DELETE CASCADE
    );

-- 7: Sectors
DROP TABLE IF EXISTS Sector;

CREATE TABLE
    Sector (
        id_sector INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE COMMENT 'Sector de cultiu (area amb una mateixa varietat i maneig)',
        descripcio TEXT,
        coordenades_geo GEOMETRY COMMENT 'Geometria que delimita el perimetre real del sector agronomic'
    );

-- 8: Parcela_Sector
DROP TABLE IF EXISTS Parcela_Sector;

CREATE TABLE
    Parcela_Sector (
        id_parcela INT NOT NULL,
        id_sector INT NOT NULL,
        superficie_m2 DECIMAL(10, 2) NOT NULL,
        PRIMARY KEY (id_parcela, id_sector),
        FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- 9: Fila_Arbre
DROP TABLE IF EXISTS Fila_Arbre;

CREATE TABLE
    Fila_Arbre (
        id_fila INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        numero INT NOT NULL,
        coordenades_geo GEOMETRY COMMENT 'Enregistrat per on passen les diferents files d''arbres',
        UNIQUE (id_sector, numero),
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- 10: Plantacio
DROP TABLE IF EXISTS Plantacio;

CREATE TABLE
    Plantacio (
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
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
        FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
    );

-- 11: Seguiment_Anual
DROP TABLE IF EXISTS Seguiment_Anual;

CREATE TABLE
    Seguiment_Anual (
        id_plantacio INT NOT NULL COMMENT 'Clau forana a la plantacio',
        any YEAR NOT NULL COMMENT 'Any de la campanya del seguiment',
        estat_fenologic VARCHAR(100) COMMENT 'Estat fenologic actual',
        creixement_vegetatiu VARCHAR(100) COMMENT 'Creixement vegetatiu',
        rendiment_kg_ha DECIMAL(10, 2) COMMENT 'Rendiments obtinguts en cada campanya',
        PRIMARY KEY (id_plantacio, any),
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
    );

-- 12: Producte_Quimic
DROP TABLE IF EXISTS Producte_Quimic;

CREATE TABLE
    Producte_Quimic (
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
DROP TABLE IF EXISTS Materia_Activa;

CREATE TABLE
    Materia_Activa (
        id_materia_activa INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        espectre_accio TEXT COMMENT 'Plagues/malalties que controla',
        modes_accio TEXT COMMENT 'Per evitar resistencies (Requisit Herbicides)',
        requeriments_normatius TEXT COMMENT 'Restriccions d''us legals'
    );

-- 14: Producte_MA
DROP TABLE IF EXISTS Producte_MA;

CREATE TABLE
    Producte_MA (
        id_producte INT NOT NULL,
        id_materia_activa INT NOT NULL,
        concentracio DECIMAL(5, 2) COMMENT 'Percentatge o unitat de concentracio',
        PRIMARY KEY (id_producte, id_materia_activa),
        FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE CASCADE,
        FOREIGN KEY (id_materia_activa) REFERENCES Materia_Activa (id_materia_activa) ON DELETE CASCADE
    );

-- 15: Inventari_Estoc
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

-- 24: Treballador
DROP TABLE IF EXISTS Treballador;

CREATE TABLE
    Treballador (
        id_treballador INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        cognoms VARCHAR(100),
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
        estat ENUM ('actiu', 'inactiu', 'vacances', 'baix') DEFAULT 'actiu'
    );

-- 16: Aplicacio
DROP TABLE IF EXISTS Aplicacio;

CREATE TABLE
    Aplicacio (
        id_aplicacio INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NOT NULL,
        data_event DATE NOT NULL,
        tipus_event VARCHAR(100) COMMENT 'Poda, aclarida, tractaments, fertilitzacions, etc.',
        descripcio TEXT,
        volum_caldo DECIMAL(10, 2) COMMENT 'Volum de caldo utilitzat/preparat (si aplica)',
        maquinaria_utilitzada VARCHAR(100) COMMENT 'Equip o maquinaria utilitzada en l''aplicacio',
        id_treballador INT COMMENT 'Operari responsable de l''aplicacio',
        condicions_ambientals TEXT COMMENT 'Temperatura, vent, etc. durant l''aplicacio',
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
        FOREIGN KEY (id_treballador) REFERENCES Treballador (id_treballador) ON DELETE SET NULL
    );

-- 17: Detall_Aplicacio_Producte
DROP TABLE IF EXISTS Detall_Aplicacio_Producte;

CREATE TABLE
    Detall_Aplicacio_Producte (
        id_aplicacio INT NOT NULL,
        id_estoc INT NOT NULL COMMENT 'Lot especific utilitzat',
        dosi_aplicada DECIMAL(10, 2) COMMENT 'Dosi real aplicada per unitat',
        quantitat_consumida_total DECIMAL(10, 2) COMMENT 'Quantitat total d''estoc consumida',
        PRIMARY KEY (id_aplicacio, id_estoc),
        FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE,
        FOREIGN KEY (id_estoc) REFERENCES Inventari_Estoc (id_estoc) ON DELETE RESTRICT
    );

-- 18: Fila_Aplicacio (SINTAXI I RESTRICCIÓ CORREGIDA)
DROP TABLE IF EXISTS Fila_Aplicacio;

CREATE TABLE
    Fila_Aplicacio (
        id_fila_aplicada INT NOT NULL COMMENT 'Identificador de la fila on s’ha aplicat el tractament',
        id_aplicacio INT NOT NULL COMMENT 'Aplicacio o tractament concret realitzat',
        data_inici DATETIME NOT NULL COMMENT 'Hora d’inici de l’aplicacio a la fila',
        data_fi DATETIME NULL COMMENT 'Hora de finalitzacio de l’aplicacio a la fila',
        percentatge_complet DECIMAL(5, 2) DEFAULT 100 COMMENT 'Percentatge de la fila tractada (0–100%)',
        longitud_tratada_m DECIMAL(10, 2) NULL COMMENT 'Longitud efectiva tractada en metres',
        coordenada_final POINT NOT NULL COMMENT 'Coordenada GPS on es va aturar l’aplicacio',
        estat ENUM ('pendent', 'en proces', 'completada', 'aturada') DEFAULT 'completada' COMMENT 'Estat de l’aplicacio a la fila',
        observacions TEXT COMMENT 'Comentaris o incidencies durant l’aplicacio',
        PRIMARY KEY (id_fila_aplicada, id_aplicacio),
        FOREIGN KEY (id_fila_aplicada) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
        FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE
    );

CREATE SPATIAL INDEX idx_fila_aplicacio_geo ON Fila_Aplicacio (coordenada_final);

-- 19: Inversio
DROP TABLE IF EXISTS Inversio;

CREATE TABLE
    Inversio (
        id_inversio INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        data_inversio DATE NOT NULL,
        concepte VARCHAR(255) NOT NULL,
        import DECIMAL(10, 2) NOT NULL,
        vida_util_anys INT COMMENT 'Periodos d''amortitzacio',
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
    );

-- 20: Monitoratge_Plaga
DROP TABLE IF EXISTS Monitoratge_Plaga;

CREATE TABLE
    Monitoratge_Plaga (
        id_monitoratge INT AUTO_INCREMENT PRIMARY KEY,
        id_sector INT NULL,
        data_observacio DATETIME NOT NULL,
        tipus_problema ENUM ('Plaga', 'Malaltia', 'Deficiencia', 'Mala Herba') NOT NULL,
        descripcio_breu VARCHAR(255),
        nivell_poblacio DECIMAL(5, 2) COMMENT 'Nivell de poblacio o index de dany',
        llindar_intervencio_assolit BOOLEAN COMMENT 'Indica si s''ha de realitzar un tractament',
        coordenades_geo GEOMETRY COMMENT 'Geolocalitzacio de l''observacio',
        FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
    );

-- ******************************************************
-- 21: Incidencia
DROP TABLE IF EXISTS Incidencia;

CREATE TABLE
    Incidencia (
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
        FOREIGN KEY (id_monitoratge) REFERENCES Monitoratge_Plaga (id_monitoratge) ON DELETE CASCADE
    );

-- 22: Protocol_Tratament
DROP TABLE IF EXISTS Protocol_Tratament;

CREATE TABLE
    Protocol_Tratament (
        id_protocol INT AUTO_INCREMENT PRIMARY KEY,
        nom_protocol VARCHAR(100) NOT NULL,
        descripcio TEXT,
        productes_json JSON COMMENT 'Llista de productes i dosis en format JSON',
        condicions_ambientals TEXT COMMENT 'Condicions ideals per aplicar el protocol'
    );

-- 23: Incidencia_Protocol
DROP TABLE IF EXISTS Incidencia_Protocol;

CREATE TABLE
    Incidencia_Protocol (
        id_incidencia INT NOT NULL,
        id_protocol INT NOT NULL,
        PRIMARY KEY (id_incidencia, id_protocol),
        FOREIGN KEY (id_incidencia) REFERENCES Incidencia (id_incidencia) ON DELETE CASCADE,
        FOREIGN KEY (id_protocol) REFERENCES Protocol_Tratament (id_protocol) ON DELETE RESTRICT
    );

-- 25: Maquinaria
DROP TABLE IF EXISTS Maquinaria;

CREATE TABLE
    Maquinaria (
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
DROP TABLE IF EXISTS Maquinaria_Applicacio;

CREATE TABLE
    Maquinaria_Applicacio (
        id_maquinaria INT NOT NULL,
        id_aplicacio INT NOT NULL,
        hores_utilitzades DECIMAL(5, 2) COMMENT 'Durada de l’ús per aplicació',
        PRIMARY KEY (id_maquinaria, id_aplicacio),
        FOREIGN KEY (id_maquinaria) REFERENCES Maquinaria (id_maquinaria) ON DELETE RESTRICT,
        FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE
    );

-- 27: Collita
DROP TABLE IF EXISTS Collita;

CREATE TABLE
    Collita (
        id_collita INT AUTO_INCREMENT PRIMARY KEY,
        id_plantacio INT NOT NULL,
        data_collita DATE NOT NULL,
        quantitat_kg DECIMAL(10, 2) NOT NULL,
        qualitat ENUM ('Extra', 'Primera', 'Segona', 'Industrial') DEFAULT 'Primera',
        observacions TEXT,
        FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
    );

-- 28: Lot_Produccio
DROP TABLE IF EXISTS Lot_Produccio;

CREATE TABLE
    Lot_Produccio (
        id_lot INT AUTO_INCREMENT PRIMARY KEY,
        id_collita INT NOT NULL,
        identificador VARCHAR(100) NOT NULL UNIQUE,
        data_processat DATE,
        pes_kg DECIMAL(10, 2),
        qualitat ENUM ('Extra', 'Primera', 'Segona', 'Industrial') DEFAULT 'Primera',
        desti VARCHAR(255) COMMENT 'Mercat, transformació, exportació',
        FOREIGN KEY (id_collita) REFERENCES Collita (id_collita) ON DELETE CASCADE
    );

-- 29: Control_Qualitat
DROP TABLE IF EXISTS Control_Qualitat;

CREATE TABLE
    Control_Qualitat (
        id_control INT AUTO_INCREMENT PRIMARY KEY,
        id_lot INT NOT NULL,
        data_control DATE NOT NULL,
        resultat ENUM ('Acceptat', 'Rebutjat', 'Condicional') DEFAULT 'Acceptat',
        comentaris TEXT,
        FOREIGN KEY (id_lot) REFERENCES Lot_Produccio (id_lot) ON DELETE CASCADE
    );

-- 30: Proveidor
DROP TABLE IF EXISTS Proveidor;

CREATE TABLE
    Proveidor (
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
DROP TABLE IF EXISTS Compra_Producte;

CREATE TABLE
    Compra_Producte (
        id_compra INT AUTO_INCREMENT PRIMARY KEY,
        id_proveidor INT NOT NULL,
        id_producte INT NOT NULL,
        data_compra DATE NOT NULL,
        quantitat DECIMAL(10, 2),
        preu_unitari DECIMAL(10, 2),
        num_lot VARCHAR(100),
        FOREIGN KEY (id_proveidor) REFERENCES Proveidor (id_proveidor) ON DELETE RESTRICT,
        FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE RESTRICT
    );

-- 32: Log_Accions
DROP TABLE IF EXISTS Log_Accions;

CREATE TABLE
    Log_Accions (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_treballador INT NOT NULL,
        data_hora DATETIME NOT NULL,
        accio VARCHAR(255) NOT NULL,
        taula_afectada VARCHAR(100),
        id_registre_afectat INT,
        comentaris TEXT,
        FOREIGN KEY (id_treballador) REFERENCES Treballador (id_treballador) ON DELETE RESTRICT
    );

INSERT INTO
    Sector (nom, descripcio, coordenades_geo)
VALUES
    -- Sector 1: Olivars del Sol
    (
        'Olivars del Sol',
        'Olivars típics de la zona de Les Borges Blanques',
        ST_GeomFromText (
            'POLYGON((0.8580080806726755 41.51480210318985, 0.8584683293428554 41.51429287610483, 0.8554595395291926 41.51259027969266, 0.8549924214758278 41.51300178746081, 0.8579874725231207 41.51481753425119, 0.8580080806726755 41.51480210318985))'
        )
    ),
    -- Sector 2: Ametllers del Racó
    (
        'Ametllers del Racó',
        'Plantació d’ametllers de qualitat',
        ST_GeomFromText (
            'POLYGON((0.8580492969718136 41.51484839636208, 0.8585232844083066 41.514313451014345, 0.8597254264571745 41.515038712391316, 0.8593063940861043 41.515465635501016, 0.8580699051213685 41.514832965308074, 0.8580492969718136 41.51484839636208))'
        )
    ),
    -- Sector 3: Poma del Camp
    (
        'Poma del Camp',
        'Sector dedicat a pomeres',
        ST_GeomFromText (
            'POLYGON((0.8555213639768056 41.512554272638226, 0.8569364569033269 41.513382429817824, 0.8576646115155881 41.51240510034293, 0.85628386550502 41.51159750643248, 0.855514494593649 41.512564560370095, 0.8555213639768056 41.512554272638226))'
        )
    ),
    -- Sector 4: Perers de la Serra
    (
        'Perers de la Serra',
        'Plantació de perers i fruiters variats',
        ST_GeomFromText (
            'POLYGON((0.8569776732024081 41.51340300501673, 0.8597666427562558 41.51498213200841, 0.8604054953871412 41.5141539952954, 0.857705827814641 41.5124462513555, 0.8569708038192232 41.513408148815444, 0.8569776732024081 41.51340300501673))'
        )
    );