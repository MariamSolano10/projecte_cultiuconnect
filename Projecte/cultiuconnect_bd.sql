-- ===============================================================
-- BASE DE DADES AGRÍCOLA COMPLETA (VERSIÓ CORREGIDA + FILA_ARBRE)
-- ===============================================================

-- ======================
-- TAULA 1: PARCELA
-- ======================
DROP TABLE IF EXISTS Parcela;

CREATE TABLE Parcela (
    id_parcela INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL COMMENT 'Identificador únic i nom descriptiu',
    coordenades_geo GEOMETRY NOT NULL COMMENT 'Coordenades GPS que delimiten el perímetre',
    superficie_ha DECIMAL(10, 2) COMMENT 'Superfície total calculada automàticament',
    pendent VARCHAR(20),
    orientacio VARCHAR(20)
);

-- ======================
-- TAULA 2: CARACTERÍSTIQUES DEL SÒL
-- ======================
DROP TABLE IF EXISTS Caracteristiques_Sol;

CREATE TABLE Caracteristiques_Sol (
    id_caracteristica INT AUTO_INCREMENT PRIMARY KEY,
    id_parcela INT NOT NULL,
    textura VARCHAR(50),
    pH DECIMAL(4, 2),
    materia_organica DECIMAL(5, 2),
    FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE
);

-- ======================
-- TAULA 3: INFRAESTRUCTURA
-- ======================
DROP TABLE IF EXISTS Infraestructura;

CREATE TABLE Infraestructura (
    id_infra INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    tipus ENUM(
        'reg',
        'camin',
        'tanca',
        'edificacio',
        'altres'
    ) NOT NULL
);

-- ======================
-- TAULA 4: PARCELA-INFRAESTRUCTURA
-- ======================
DROP TABLE IF EXISTS Parcela_Infraestructura;

CREATE TABLE Parcela_Infraestructura (
    id_parcela INT NOT NULL,
    id_infra INT NOT NULL,
    PRIMARY KEY (id_parcela, id_infra),
    FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
    FOREIGN KEY (id_infra) REFERENCES Infraestructura (id_infra) ON DELETE CASCADE
);

-- ======================
-- TAULA 5: ESPÈCIE
-- ======================
DROP TABLE IF EXISTS Especie;

CREATE TABLE Especie (
    id_especie INT AUTO_INCREMENT PRIMARY KEY,
    nom_comu VARCHAR(100) NOT NULL,
    nom_cientific VARCHAR(100)
);

-- ======================
-- TAULA 6: VARIETAT
-- ======================
DROP TABLE IF EXISTS Varietat;

CREATE TABLE Varietat (
    id_varietat INT AUTO_INCREMENT PRIMARY KEY,
    id_especie INT NOT NULL,
    nom_varietat VARCHAR(100) NOT NULL,
    caracteristiques_agronomiques TEXT,
    cicle_vegetatiu TEXT,
    requisits_pollinitzacio TEXT,
    productivitat_mitjana_esperada DECIMAL(10, 2),
    qualitats_comercials TEXT,
    FOREIGN KEY (id_especie) REFERENCES Especie (id_especie) ON DELETE CASCADE
);

-- ======================
-- TAULA 7: SECTOR
-- ======================
DROP TABLE IF EXISTS Sector;

CREATE TABLE Sector (
    id_sector INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    descripcio TEXT
);

-- ======================
-- TAULA 8: PARCELA-SECTOR
-- ======================
DROP TABLE IF EXISTS Parcela_Sector;

CREATE TABLE Parcela_Sector (
    id_parcela INT NOT NULL,
    id_sector INT NOT NULL,
    superficie_m2 DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id_parcela, id_sector),
    FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
);

-- ======================
-- TAULA 9: FILA_ARBRE
-- ======================
DROP TABLE IF EXISTS Fila_Arbre;

CREATE TABLE Fila_Arbre (
    id_fila INT AUTO_INCREMENT PRIMARY KEY,
    id_sector INT NOT NULL,
    numero INT NOT NULL,
    coordenades_geo GEOMETRY COMMENT 'Coordenades de la línia d’arbres dins del sector',
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
);

-- ======================
-- TAULA 10: PLANTACIÓ
-- ======================
DROP TABLE IF EXISTS Plantacio;

CREATE TABLE Plantacio (
    id_plantacio INT AUTO_INCREMENT PRIMARY KEY,
    id_sector INT NOT NULL,
    id_varietat INT NOT NULL,
    data_plantacio DATE NOT NULL,
    marc_fila DECIMAL(5, 2) NOT NULL,
    marc_arbre DECIMAL(5, 2) NOT NULL,
    num_arbres_plantats INT,
    origen_material TEXT,
    sistema_formacio VARCHAR(100),
    previsio_entrada_produccio DATE,
    data_arrencada DATE NULL,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
    FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
);

-- ======================
-- TAULA 11: SEGUIMENT ANUAL
-- ======================
DROP TABLE IF EXISTS Seguiment_Anual;

CREATE TABLE Seguiment_Anual (
    id_seguiment INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacio INT NOT NULL,
    any YEAR NOT NULL,
    estat_fenologic VARCHAR(100),
    creixement_vegetatiu VARCHAR(100),
    rendiment_kg_ha DECIMAL(10, 2),
    incidencies TEXT,
    FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
);

-- ======================
-- TAULA A: PRODUCTE QUÍMIC
-- ======================
DROP TABLE IF EXISTS Producte_Quimic;

CREATE TABLE Producte_Quimic (
    id_producte INT AUTO_INCREMENT PRIMARY KEY,
    nom_comercial VARCHAR(255) NOT NULL,
    tipus ENUM(
        'Fitosanitari',
        'Fertilitzant',
        'Herbicida'
    ) NOT NULL,
    num_registre VARCHAR(50),
    fabricant VARCHAR(100),
    termini_seguretat_dies INT,
    classificacio_tox VARCHAR(50),
    compatibilitat_eco BOOLEAN,
    dosi_max_ha DECIMAL(10, 2),
    fitxa_seguretat_link TEXT
);

-- ======================
-- TAULA B: MATÈRIA ACTIVA
-- ======================
DROP TABLE IF EXISTS Materia_Activa;

CREATE TABLE Materia_Activa (
    id_materia_activa INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    espectre_accio TEXT,
    modes_accio TEXT,
    requeriments_normatius TEXT
);

-- ======================
-- TAULA C: PRODUCTE-MATÈRIA ACTIVA
-- ======================
DROP TABLE IF EXISTS Producte_MA;

CREATE TABLE Producte_MA (
    id_producte INT NOT NULL,
    id_materia_activa INT NOT NULL,
    concentracio DECIMAL(5, 2),
    PRIMARY KEY (
        id_producte,
        id_materia_activa
    ),
    FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE CASCADE,
    FOREIGN KEY (id_materia_activa) REFERENCES Materia_Activa (id_materia_activa) ON DELETE CASCADE
);

-- ======================
-- TAULA D: INVENTARI D’ESTOCS
-- ======================
DROP TABLE IF EXISTS Inventari_Estoc;

CREATE TABLE Inventari_Estoc (
    id_estoc INT AUTO_INCREMENT PRIMARY KEY,
    id_producte INT NOT NULL,
    num_lot VARCHAR(100) NOT NULL,
    quantitat_disponible DECIMAL(10, 2) NOT NULL,
    unitat_mesura VARCHAR(20) NOT NULL,
    data_caducitat DATE,
    ubicacio_magatzem VARCHAR(100),
    data_compra DATE,
    proveidor VARCHAR(100),
    preu_adquisicio DECIMAL(10, 2),
    FOREIGN KEY (id_producte) REFERENCES Producte_Quimic (id_producte) ON DELETE RESTRICT
);

-- ======================
-- TAULA 12: APLICACIÓ
-- ======================
DROP TABLE IF EXISTS Aplicacio;

CREATE TABLE Aplicacio (
    id_aplicacio INT AUTO_INCREMENT PRIMARY KEY,
    id_sector INT NOT NULL,
    id_fila INT NULL COMMENT 'Pot ser NULL si afecta tot el sector',
    id_estoc INT NOT NULL,
    data_event DATE NOT NULL,
    tipus_event VARCHAR(100),
    descripcio TEXT,
    dosi_aplicada DECIMAL(10, 2),
    quantitat_consumida_total DECIMAL(10, 2),
    volum_caldo DECIMAL(10, 2),
    maquinaria_utilitzada VARCHAR(100),
    operari_carnet VARCHAR(50),
    condicions_ambientals TEXT,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
    FOREIGN KEY (id_fila) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
    FOREIGN KEY (id_estoc) REFERENCES Inventari_Estoc (id_estoc) ON DELETE RESTRICT
);

-- ======================
-- TAULA 13: INVERSIÓ
-- ======================
DROP TABLE IF EXISTS Inversio;

CREATE TABLE Inversio (
    id_inversio INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacio INT NOT NULL,
    data_inversio DATE NOT NULL,
    concepte VARCHAR(255) NOT NULL,
    import DECIMAL(10, 2) NOT NULL,
    vida_util_anys INT,
    FOREIGN KEY (id_plantacio) REFERENCES Plantacio (id_plantacio) ON DELETE CASCADE
);

-- ======================
-- TAULA 14: MONITORATGE DE PLAGUES
-- ======================
DROP TABLE IF EXISTS Monitoratge_Plaga;

CREATE TABLE Monitoratge_Plaga (
    id_monitoratge INT AUTO_INCREMENT PRIMARY KEY,
    id_sector INT NULL,
    data_observacio DATETIME NOT NULL,
    tipus_problema ENUM(
        'Plaga',
        'Malaltia',
        'Deficiencia',
        'Mala Herba'
    ) NOT NULL,
    descripcio_breu VARCHAR(255),
    nivell_poblacio DECIMAL(5, 2),
    llindar_intervencio_assolit BOOLEAN,
    coordenades_geo GEOMETRY,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
);

-- ======================
-- TAULA 15: INCIDÈNCIES
-- ======================
DROP TABLE IF EXISTS Incidencia;

CREATE TABLE Incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_parcela INT NULL,
    id_sector INT NULL,
    id_fila INT NULL,
    id_monitoratge INT NULL,
    data_incidencia DATE NOT NULL,
    tipus_incidencia VARCHAR(100),
    descripcio TEXT,
    FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE,
    FOREIGN KEY (id_fila) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
    FOREIGN KEY (id_monitoratge) REFERENCES Monitoratge_Plaga (id_monitoratge) ON DELETE SET NULL
);

-- ======================
-- TAULA 16: DOCUMENTACIÓ
-- ======================
DROP TABLE IF EXISTS Documentacio;

CREATE TABLE Documentacio (
    id_document INT AUTO_INCREMENT PRIMARY KEY,
    id_parcela INT NULL,
    id_plantacio INT NULL,
    id_varietat INT NULL,
    data_doc DATE,
    tipus_doc ENUM(
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
);

-- ======================
-- TAULA 17: ANÀLISI DE LABORATORI
-- ======================
DROP TABLE IF EXISTS Analisi_Laboratori;

CREATE TABLE Analisi_Laboratori (
    id_analisi INT AUTO_INCREMENT PRIMARY KEY,
    id_parcela INT NOT NULL,
    data_mostreig DATE NOT NULL,
    tipus_analisi ENUM('Sol', 'Aigua', 'Foliar') NOT NULL,
    resultats_json JSON,
    objectiu TEXT,
    FOREIGN KEY (id_parcela) REFERENCES Parcela (id_parcela) ON DELETE CASCADE
);

-- ======================
-- TAULA 18: FILA_ARBRE-APLICACIÓ
-- ======================
DROP TABLE IF EXISTS Fila_Aplicacio;

CREATE TABLE Fila_Aplicacio (
    id_fila_aplicada INT NOT NULL COMMENT 'Identificador de la fila on s’ha aplicat el tractament (FK a Fila_Arbre)',
    id_aplicacio INT NOT NULL COMMENT 'Aplicació o tractament concret realitzat (FK a Aplicacio)',
    id_sector INT NOT NULL COMMENT 'Sector al qual pertany la fila (FK a Sector)',
    data_inici DATETIME NOT NULL,
    data_fi DATETIME NULL,
    percentatge_complet DECIMAL(5, 2) DEFAULT 100,
    longitud_tratada_m DECIMAL(10, 2) NULL,
    coordenada_final POINT NULL,
    estat ENUM(
        'pendent',
        'en procés',
        'completada',
        'aturada'
    ) DEFAULT 'completada',
    observacions TEXT,
    PRIMARY KEY (
        id_fila_aplicada,
        id_aplicacio
    ),
    FOREIGN KEY (id_fila_aplicada) REFERENCES Fila_Arbre (id_fila) ON DELETE CASCADE,
    FOREIGN KEY (id_aplicacio) REFERENCES Aplicacio (id_aplicacio) ON DELETE CASCADE,
    FOREIGN KEY (id_sector) REFERENCES Sector (id_sector) ON DELETE CASCADE
);

CREATE SPATIAL INDEX idx_fila_aplicacio_geo ON Fila_Aplicacio (coordenada_final);

-- ******************************************************
-- A1. Mòdul: Catàleg i Operació de Collita
-- ******************************************************

-- Taula A1: Catàleg d'Equips o Colles de Recol·lecció
DROP TABLE IF EXISTS Equip_Recollector;

CREATE TABLE Equip_Recollector (
    id_equip INT AUTO_INCREMENT PRIMARY KEY,
    nom_equip VARCHAR(100) NOT NULL,
    tipus ENUM(
        'Intern',
        'Extern',
        'Maquinaria'
    ) NOT NULL
);

-- Taula A2: Registre d'Operacions de Collita (L'esdeveniment físic)
DROP TABLE IF EXISTS Collita_Operacio;

CREATE TABLE Collita_Operacio (
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

-- Taula A3: Lot de Producció (Traçabilitat i Quantitat)
DROP TABLE IF EXISTS Lot_Produccio;

CREATE TABLE Lot_Produccio (
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

-- Taula B1: Definició dels Protocols de Qualitat (Què s'ha de mesurar per a cada varietat)
DROP TABLE IF EXISTS Protocol_Qualitat;

CREATE TABLE Protocol_Qualitat (
    id_protocol INT AUTO_INCREMENT PRIMARY KEY,
    id_varietat INT NOT NULL,
    nom_protocol VARCHAR(100) NOT NULL,
    paràmetres_esperats JSON COMMENT 'Ex: {calibre_min: "75mm", fermesa_min: "5kg"}',
    FOREIGN KEY (id_varietat) REFERENCES Varietat (id_varietat) ON DELETE CASCADE
);

-- Taula B2: Registre dels Controls de Qualitat (Resultats de la inspecció d'un Lot)
DROP TABLE IF EXISTS Control_Qualitat;

CREATE TABLE Control_Qualitat (
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