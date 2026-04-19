SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Forçar charset i col·lació a tota la base de dades
ALTER DATABASE cultiuconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================
-- MÒDUL 1: GESTIÓ DE PARCEL·LES I CULTIUS
-- ============================================================

-- 1: Parcela
DROP TABLE IF EXISTS parcela;
CREATE TABLE parcela (
    id_parcela      INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)  NOT NULL UNIQUE   COMMENT 'Identificador únic i nom descriptiu (ex: Sector Nord - Pomeres)',
    coordenades_geo GEOMETRY      NOT NULL          COMMENT 'Coordenades GPS que delimiten el perímetre exacte',
    superficie_ha   DECIMAL(10,2) NOT NULL CHECK (superficie_ha > 0) COMMENT 'Superfície total en hectàrees',
    pendent         VARCHAR(20)                     COMMENT 'Pendent del terreny',
    orientacio      VARCHAR(20)                     COMMENT 'Orientació de la parcel·la',
    documentacio_pdf TEXT                           COMMENT 'Ruta o URL als documents associats',
    SPATIAL INDEX idx_parcela_geo (coordenades_geo)
) ENGINE = InnoDB;

-- 2: Infraestructura
DROP TABLE IF EXISTS infraestructura;
CREATE TABLE infraestructura (
    id_infra        INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL UNIQUE,
    tipus           ENUM('reg','camin','tanca','edificacio','altres') NOT NULL,
    coordenades_geo GEOMETRY     NOT NULL,
    SPATIAL INDEX idx_infra_geo (coordenades_geo)
) ENGINE = InnoDB;

-- 3: Parcela_Infraestructura
DROP TABLE IF EXISTS parcela_infraestructura;
CREATE TABLE parcela_infraestructura (
    id_parcela INT NOT NULL,
    id_infra   INT NOT NULL,
    PRIMARY KEY (id_parcela, id_infra),
    FOREIGN KEY (id_parcela) REFERENCES parcela        (id_parcela) ON DELETE CASCADE,
    FOREIGN KEY (id_infra)   REFERENCES infraestructura (id_infra)  ON DELETE RESTRICT,
    INDEX idx_infra (id_infra)
) ENGINE = InnoDB;

-- 4: Foto_Parcela
DROP TABLE IF EXISTS foto_parcela;
CREATE TABLE foto_parcela (
    id_foto         INT AUTO_INCREMENT PRIMARY KEY,
    id_parcela      INT  NOT NULL,
    url_foto        TEXT NOT NULL,
    coordenades_geo POINT,
    data_foto       DATE,
    descripcio      VARCHAR(255),
    FOREIGN KEY (id_parcela) REFERENCES parcela (id_parcela) ON DELETE CASCADE,
    INDEX idx_parcela (id_parcela)
) ENGINE = InnoDB;

-- 5: Especie
DROP TABLE IF EXISTS especie;
CREATE TABLE especie (
    id_especie    INT AUTO_INCREMENT PRIMARY KEY,
    nom_comu      VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nom comú de l''espècie',
    nom_cientific VARCHAR(100) UNIQUE          COMMENT 'Nom científic de l''espècie'
) ENGINE = InnoDB;

-- 6: Varietat
DROP TABLE IF EXISTS varietat;
CREATE TABLE varietat (
    id_varietat                    INT AUTO_INCREMENT PRIMARY KEY,
    id_especie                     INT NOT NULL,
    nom_varietat                   VARCHAR(100) NOT NULL,
    caracteristiques_agronomiques  TEXT         COMMENT 'Necessitats hídriques, hores de fred, resistència a malalties',
    cicle_vegetatiu                TEXT         COMMENT 'Floració, quallat, maduració, recol·lecció',
    requisits_pollinitzacio        TEXT         COMMENT 'Requisits de pol·linització i varietats compatibles',
    productivitat_mitjana_esperada DECIMAL(10,2) CHECK (productivitat_mitjana_esperada >= 0) COMMENT 'kg/ha',
    qualitats_comercials           TEXT,
    FOREIGN KEY (id_especie) REFERENCES especie (id_especie) ON DELETE CASCADE,
    UNIQUE (id_especie, nom_varietat)
) ENGINE = InnoDB;

-- 7: Foto_Varietat
DROP TABLE IF EXISTS foto_varietat;
CREATE TABLE foto_varietat (
    id_foto     INT AUTO_INCREMENT PRIMARY KEY,
    id_varietat INT  NOT NULL,
    tipus       ENUM('arbre','flor','fruit') NOT NULL,
    url_foto    TEXT NOT NULL,
    descripcio  VARCHAR(255),
    FOREIGN KEY (id_varietat) REFERENCES varietat (id_varietat) ON DELETE CASCADE,
    INDEX idx_varietat (id_varietat)
) ENGINE = InnoDB;

-- 8: Sector
DROP TABLE IF EXISTS sector;
CREATE TABLE sector (
    id_sector       INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nom identificatiu del sector de cultiu',
    descripcio      TEXT,
    coordenades_geo GEOMETRY     NOT NULL,
    SPATIAL INDEX idx_sector_geo (coordenades_geo)
) ENGINE = InnoDB;

-- 9: Parcela_Sector
DROP TABLE IF EXISTS parcela_sector;
CREATE TABLE parcela_sector (
    id_parcela    INT           NOT NULL,
    id_sector     INT           NOT NULL,
    superficie_m2 DECIMAL(10,2) NOT NULL CHECK (superficie_m2 > 0) COMMENT 'Superfície de la intersecció en m²',
    PRIMARY KEY (id_parcela, id_sector),
    FOREIGN KEY (id_parcela) REFERENCES parcela (id_parcela) ON DELETE CASCADE,
    FOREIGN KEY (id_sector)  REFERENCES sector  (id_sector)  ON DELETE CASCADE,
    INDEX idx_sector_parcela (id_sector)
) ENGINE = InnoDB;

-- 10: Fila_Arbre
DROP TABLE IF EXISTS fila_arbre;
CREATE TABLE fila_arbre (
    id_fila         INT AUTO_INCREMENT PRIMARY KEY,
    id_sector       INT          NOT NULL,
    numero          INT          NOT NULL CHECK (numero > 0) COMMENT 'Número de fila dins del sector',
    descripcio      VARCHAR(100) DEFAULT NULL               COMMENT 'Identificador visual (ex: Fila A, Fila Est)',
    num_arbres      INT          DEFAULT NULL CHECK (num_arbres IS NULL OR num_arbres > 0) COMMENT 'Nombre d''arbres en aquesta fila',
    coordenades_geo GEOMETRY     NOT NULL                   COMMENT 'Traçat geolocalitzat de la fila d''arbres',
    UNIQUE (id_sector, numero),
    FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE,
    SPATIAL INDEX idx_fila_geo (coordenades_geo)
) ENGINE = InnoDB;

-- 11: Caracteristiques_Sol
DROP TABLE IF EXISTS caracteristiques_sol;
CREATE TABLE caracteristiques_sol (
    id_sector               INT  NOT NULL,
    data_analisi            DATE NOT NULL,
    textura                 VARCHAR(50)   COMMENT 'Textura del sòl',
    pH                      DECIMAL(4,2)  CHECK (pH BETWEEN 0 AND 14),
    materia_organica        DECIMAL(5,2)  CHECK (materia_organica >= 0) COMMENT 'Percentatge de matèria orgànica',
    N                       DECIMAL(6,2)  CHECK (N >= 0)  COMMENT 'Nitrogen disponible (ppm)',
    P                       DECIMAL(6,2)  CHECK (P >= 0)  COMMENT 'Fòsfor disponible (ppm)',
    K                       DECIMAL(7,2)  CHECK (K >= 0)  COMMENT 'Potassi disponible (ppm)',
    Ca                      DECIMAL(8,2)  CHECK (Ca >= 0) COMMENT 'Calci disponible (ppm)',
    Mg                      DECIMAL(7,2)  CHECK (Mg >= 0) COMMENT 'Magnesi disponible (ppm)',
    Na                      DECIMAL(6,2)  CHECK (Na >= 0) COMMENT 'Sodi disponible (ppm)',
    conductivitat_electrica DECIMAL(6,3)  CHECK (conductivitat_electrica >= 0) COMMENT 'Conductivitat elèctrica (dS/m)',
    PRIMARY KEY (id_sector, data_analisi),
    CONSTRAINT fk_sector_sol FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Anàlisi d'Aigua de Reg
DROP TABLE IF EXISTS analisi_aigua;
CREATE TABLE analisi_aigua (
    id_analisi_aigua     INT AUTO_INCREMENT PRIMARY KEY,
    id_sector            INT  NOT NULL,
    data_analisi         DATE NOT NULL,
    origen_mostra        ENUM('pou','bassa','xarxa','riu','altres') NOT NULL DEFAULT 'pou'
                         COMMENT 'Origen de la mostra d''aigua',
    pH                   DECIMAL(4,2)  CHECK (pH BETWEEN 0 AND 14),
    conductivitat_electrica DECIMAL(6,3) CHECK (conductivitat_electrica >= 0)
                         COMMENT 'Conductivitat elèctrica (dS/m) — indica salinitat total',
    duresa               DECIMAL(8,2)  CHECK (duresa >= 0)
                         COMMENT 'Duresa total (mg/L CaCO₃)',
    nitrats              DECIMAL(8,2)  CHECK (nitrats >= 0)    COMMENT 'NO₃ (ppm)',
    clorurs              DECIMAL(8,2)  CHECK (clorurs >= 0)    COMMENT 'Cl (ppm)',
    sulfats              DECIMAL(8,2)  CHECK (sulfats >= 0)    COMMENT 'SO₄ (ppm)',
    bicarbonat           DECIMAL(8,2)  CHECK (bicarbonat >= 0) COMMENT 'HCO₃ (ppm)',
    Na                   DECIMAL(8,2)  CHECK (Na >= 0)         COMMENT 'Sodi (ppm)',
    Ca                   DECIMAL(8,2)  CHECK (Ca >= 0)         COMMENT 'Calci (ppm)',
    Mg                   DECIMAL(8,2)  CHECK (Mg >= 0)         COMMENT 'Magnesi (ppm)',
    K                    DECIMAL(8,2)  CHECK (K >= 0)          COMMENT 'Potassi (ppm)',
    SAR                  DECIMAL(6,2)  CHECK (SAR >= 0)
                         COMMENT 'Sodium Adsorption Ratio — risc sòdic per al sòl',
    observacions         TEXT,
    FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE CASCADE,
    INDEX idx_sector (id_sector),
    INDEX idx_data   (data_analisi)
) ENGINE = InnoDB COMMENT = 'Anàlisis fisicoquímiques de l''aigua de reg per sector';
 
-- Anàlisi Foliar
DROP TABLE IF EXISTS analisi_foliar;
CREATE TABLE analisi_foliar (
    id_analisi_foliar INT AUTO_INCREMENT PRIMARY KEY,
    id_sector         INT  NOT NULL,
    id_plantacio      INT  NULL        COMMENT 'Plantació concreta (opcional, per afinar)',
    data_analisi      DATE NOT NULL,
    estat_fenologic   ENUM(
                          'repos_hivernal',
                          'brotacio',
                          'floracio',
                          'creixement_fruit',
                          'maduresa',
                          'post_collita'
                      ) NOT NULL       COMMENT 'Estat fenològic en el moment del mostreig',
    -- Macronutrients (%)
    N                 DECIMAL(5,2) CHECK (N  >= 0) COMMENT 'Nitrogen foliar (%)',
    P                 DECIMAL(5,2) CHECK (P  >= 0) COMMENT 'Fòsfor foliar (%)',
    K                 DECIMAL(5,2) CHECK (K  >= 0) COMMENT 'Potassi foliar (%)',
    Ca                DECIMAL(5,2) CHECK (Ca >= 0) COMMENT 'Calci foliar (%)',
    Mg                DECIMAL(5,2) CHECK (Mg >= 0) COMMENT 'Magnesi foliar (%)',
    -- Micronutrients (ppm)
    Fe                DECIMAL(7,2) CHECK (Fe >= 0) COMMENT 'Ferro (ppm)',
    Mn                DECIMAL(7,2) CHECK (Mn >= 0) COMMENT 'Manganès (ppm)',
    Zn                DECIMAL(7,2) CHECK (Zn >= 0) COMMENT 'Zinc (ppm)',
    Cu                DECIMAL(7,2) CHECK (Cu >= 0) COMMENT 'Coure (ppm)',
    B                 DECIMAL(7,2) CHECK (B  >= 0) COMMENT 'Bor (ppm)',
    -- Diagnosi
    deficiencies_detectades TEXT       COMMENT 'Deficiències identificades pel laboratori',
    recomanacions           TEXT       COMMENT 'Recomanacions d''esmena o fertilització',
    observacions            TEXT,
    FOREIGN KEY (id_sector)    REFERENCES sector    (id_sector)    ON DELETE CASCADE,
    FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE SET NULL,
    INDEX idx_sector      (id_sector),
    INDEX idx_plantacio   (id_plantacio),
    INDEX idx_data        (data_analisi),
    INDEX idx_fenologic   (estat_fenologic)
) ENGINE = InnoDB COMMENT = 'Anàlisis foliars per diagnosi nutricional del cultiu';

-- 12: Plantacio
DROP TABLE IF EXISTS plantacio;
CREATE TABLE plantacio (
    id_plantacio               INT  AUTO_INCREMENT PRIMARY KEY,
    id_sector                  INT  NOT NULL,
    id_varietat                INT  NOT NULL,
    data_plantacio             DATE NOT NULL,
    marc_fila                  DECIMAL(5,2) NOT NULL CHECK (marc_fila > 0)  COMMENT 'Distància entre files (m)',
    marc_arbre                 DECIMAL(5,2) NOT NULL CHECK (marc_arbre > 0) COMMENT 'Distància entre arbres (m)',
    num_arbres_plantats        INT  CHECK (num_arbres_plantats >= 0),
    num_falles                 INT  DEFAULT 0 CHECK (num_falles >= 0)       COMMENT 'Nombre d''arbres absents o morts',
    origen_material            TEXT                                         COMMENT 'Origen del material vegetal',
    sistema_formacio           VARCHAR(100)                                 COMMENT 'Sistema de formació: vas, palmeta, eix central',
    previsio_entrada_produccio DATE,
    data_arrencada             DATE NULL                                    COMMENT 'Data d''arrencada del cultiu',
    FOREIGN KEY (id_sector)   REFERENCES sector   (id_sector)   ON DELETE CASCADE,
    FOREIGN KEY (id_varietat) REFERENCES varietat (id_varietat) ON DELETE CASCADE,
    INDEX idx_sector   (id_sector),
    INDEX idx_varietat (id_varietat)
) ENGINE = InnoDB;

-- 13: Seguiment_Anual
DROP TABLE IF EXISTS seguiment_anual;
CREATE TABLE seguiment_anual (
    id_plantacio          INT  NOT NULL,
    `any`                 YEAR NOT NULL CHECK (`any` >= 2000),
    estat_fenologic       VARCHAR(100),
    creixement_vegetatiu  VARCHAR(100),
    rendiment_kg_ha       DECIMAL(10,2) CHECK (rendiment_kg_ha >= 0),
    incidencies           TEXT,
    intervencions         TEXT,
    condicions_climatiques TEXT,
    estimacio_collita_kg  DECIMAL(10,2) CHECK (estimacio_collita_kg >= 0),
    PRIMARY KEY (id_plantacio, `any`),
    FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE CASCADE,
    INDEX idx_any (`any`)
) ENGINE = InnoDB;

-- ============================================================
-- MÒDUL 2: GESTIÓ DE TRACTAMENTS I FERTILITZACIÓ
-- ============================================================

-- 14: Materia_Activa
DROP TABLE IF EXISTS materia_activa;
CREATE TABLE materia_activa (
    id_materia_activa     INT AUTO_INCREMENT PRIMARY KEY,
    nom                   VARCHAR(100) NOT NULL UNIQUE CHECK (CHAR_LENGTH(TRIM(nom)) > 0),
    espectre_accio        TEXT COMMENT 'Plagues o malalties que controla',
    modes_accio           TEXT COMMENT 'Mode d''acció per evitar resistències',
    requeriments_normatius TEXT
) ENGINE = InnoDB;

-- 15: Producte_Quimic
DROP TABLE IF EXISTS producte_quimic;
CREATE TABLE producte_quimic (
    id_producte              INT AUTO_INCREMENT PRIMARY KEY,
    nom_comercial            VARCHAR(255) NOT NULL UNIQUE,
    tipus                    ENUM('Fitosanitari','Fertilitzant','Herbicida') NOT NULL,
    num_registre             VARCHAR(50)   COMMENT 'Número de registre oficial',
    fabricant                VARCHAR(100),
    termini_seguretat_dies   INT  CHECK (termini_seguretat_dies >= 0)   COMMENT 'Dies de seguretat obligatoris abans de la collita',
    classificacio_tox        VARCHAR(50)   COMMENT 'Classificació toxicològica',
    compatibilitat_eco       BOOLEAN       COMMENT 'Compatible amb producció ecològica',
    compatibilitat_integrada BOOLEAN       COMMENT 'Compatible amb producció integrada',
    dosi_max_ha              DECIMAL(10,2) CHECK (dosi_max_ha >= 0)     COMMENT 'Dosi màxima legal per hectàrea',
    num_aplicacions_max      INT  CHECK (num_aplicacions_max >= 0)      COMMENT 'Nombre màxim d''aplicacions per temporada',
    cultius_autoritzats      TEXT,
    restriccions_us          TEXT,
    fitxa_seguretat_link     TEXT,
    estoc_actual             DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (estoc_actual >= 0)  COMMENT 'Estoc total disponible',
    estoc_minim              DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (estoc_minim >= 0)   COMMENT 'Quantitat mínima per sota de la qual es genera alerta',
    unitat_mesura            VARCHAR(20)   NOT NULL DEFAULT 'L'                          COMMENT 'Unitat de mesura: L, Kg, U',
    INDEX idx_tipus (tipus)
) ENGINE = InnoDB;

-- 16: Producte_MA
DROP TABLE IF EXISTS producte_ma;
CREATE TABLE producte_ma (
    id_producte       INT NOT NULL,
    id_materia_activa INT NOT NULL,
    concentracio      DECIMAL(5,2) CHECK (concentracio >= 0) COMMENT 'Concentració (%)',
    PRIMARY KEY (id_producte, id_materia_activa),
    FOREIGN KEY (id_producte)       REFERENCES producte_quimic (id_producte)       ON DELETE CASCADE,
    FOREIGN KEY (id_materia_activa) REFERENCES materia_activa  (id_materia_activa) ON DELETE CASCADE,
    INDEX idx_producte       (id_producte),
    INDEX idx_materia_activa (id_materia_activa)
) ENGINE = InnoDB;

-- 17: Inventari_Estoc
DROP TABLE IF EXISTS inventari_estoc;
CREATE TABLE inventari_estoc (
    id_estoc             INT AUTO_INCREMENT PRIMARY KEY,
    id_producte          INT          NOT NULL,
    num_lot              VARCHAR(100) NOT NULL                              COMMENT 'Número de lot',
    quantitat_disponible DECIMAL(10,2) NOT NULL CHECK (quantitat_disponible >= 0) COMMENT 'Quantitat disponible en estoc',
    unitat_mesura        VARCHAR(20)  NOT NULL                              COMMENT 'Unitat de mesura (L, kg, etc.)',
    data_caducitat       DATE                                               COMMENT 'Data de caducitat del lot',
    ubicacio_magatzem    VARCHAR(100)                                       COMMENT 'Ubicació física al magatzem',
    data_compra          DATE                                               COMMENT 'Data d''adquisició',
    preu_adquisicio      DECIMAL(10,2) CHECK (preu_adquisicio >= 0)        COMMENT 'Preu d''adquisició per al control de costos',
    FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE RESTRICT,
    UNIQUE (id_producte, num_lot)
) ENGINE = InnoDB;

-- 18: Moviment_Estoc
DROP TABLE IF EXISTS moviment_estoc;
CREATE TABLE moviment_estoc (
    id_moviment    INT AUTO_INCREMENT PRIMARY KEY,
    id_producte    INT          NOT NULL,
    tipus_moviment ENUM('Entrada','Sortida','Regularitzacio') NOT NULL
        COMMENT 'Entrada=compra, Sortida=baixa manual, Regularitzacio=inventari físic',
    quantitat      DECIMAL(10,2) NOT NULL CHECK (quantitat > 0)
        COMMENT 'Quantitat del moviment (sempre positiva)',
    data_moviment  DATE          NOT NULL,
    motiu          VARCHAR(255)  COMMENT 'Albarà, motiu de la baixa o observació',
    FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE CASCADE,
    INDEX idx_producte (id_producte),
    INDEX idx_data     (data_moviment)
) ENGINE = InnoDB COMMENT = 'Historial de moviments d''estoc global per producte';

-- 19: Proveidor
DROP TABLE IF EXISTS proveidor;
CREATE TABLE proveidor (
    id_proveidor INT AUTO_INCREMENT PRIMARY KEY,
    nom          VARCHAR(100) NOT NULL UNIQUE,
    telefon      VARCHAR(20)  CHECK (telefon REGEXP '^[0-9 +()-]*$'),
    email        VARCHAR(100) CHECK (email IS NULL OR email REGEXP '^[^@]+@[^@]+\\.[^@]+$'),
    adreca       TEXT,
    tipus        ENUM('Fitosanitari','Fertilitzant','Llavor','Maquinaria','Altres') DEFAULT 'Altres',
    INDEX idx_tipus (tipus)
) ENGINE = InnoDB;

-- 20: Compra_Producte
DROP TABLE IF EXISTS compra_producte;
CREATE TABLE compra_producte (
    id_compra    INT AUTO_INCREMENT PRIMARY KEY,
    id_proveidor INT  NOT NULL,
    id_producte  INT  NOT NULL,
    data_compra  DATE NOT NULL,
    quantitat    DECIMAL(10,2) CHECK (quantitat >= 0),
    preu_unitari DECIMAL(10,2) CHECK (preu_unitari >= 0),
    num_lot      VARCHAR(100)  COMMENT 'Número de lot adquirit',
    FOREIGN KEY (id_proveidor) REFERENCES proveidor       (id_proveidor) ON DELETE RESTRICT,
    FOREIGN KEY (id_producte)  REFERENCES producte_quimic (id_producte)  ON DELETE RESTRICT,
    INDEX idx_proveidor (id_proveidor),
    INDEX idx_producte  (id_producte)
) ENGINE = InnoDB;

-- 21: Sensor
DROP TABLE IF EXISTS sensor;
CREATE TABLE sensor (
    id_sensor            INT AUTO_INCREMENT PRIMARY KEY,
    id_sector            INT,
    tipus                ENUM('humitat_sol','conductivitat','temperatura_sol','temperatura_ambient','pluja','trampa_plaga') NOT NULL,
    protocol_comunicacio ENUM('LoRaWAN','RS485','WiFi','Altres'),
    coordenades_geo      POINT,
    profunditat_cm       INT CHECK (profunditat_cm >= 0),
    estat                ENUM('actiu','inactiu','avaria') DEFAULT 'actiu',
    FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE SET NULL,
    INDEX idx_sector (id_sector),
    INDEX idx_tipus  (tipus)
) ENGINE = InnoDB;

-- 22: Lectura_Sensor
DROP TABLE IF EXISTS lectura_sensor;
CREATE TABLE lectura_sensor (
    id_lectura INT AUTO_INCREMENT PRIMARY KEY,
    id_sensor  INT           NOT NULL,
    data_hora  DATETIME      NOT NULL,
    valor      DECIMAL(10,4) NOT NULL,
    unitat     VARCHAR(20)   NOT NULL,
    FOREIGN KEY (id_sensor) REFERENCES sensor (id_sensor) ON DELETE CASCADE,
    INDEX idx_sensor_data (id_sensor, data_hora)
) ENGINE = InnoDB;

-- 23: Monitoratge_Plaga
DROP TABLE IF EXISTS monitoratge_plaga;
CREATE TABLE monitoratge_plaga (
    id_monitoratge              INT AUTO_INCREMENT PRIMARY KEY,
    id_sector                   INT NULL,
    data_observacio             DATETIME NOT NULL               COMMENT 'Data i hora de l''observació de camp',
    tipus_problema              ENUM('Plaga','Malaltia','Deficiencia','Mala Herba') NOT NULL,
    descripcio_breu             VARCHAR(255),
    nivell_poblacio             DECIMAL(5,2) CHECK (nivell_poblacio >= 0),
    llindar_intervencio_assolit BOOLEAN NOT NULL DEFAULT FALSE  COMMENT 'S''ha assolit el llindar que justifica un tractament',
    coordenades_geo             GEOMETRY,
    FOREIGN KEY (id_sector) REFERENCES sector (id_sector) ON DELETE SET NULL
) ENGINE = InnoDB;

-- 24: Incidencia
DROP TABLE IF EXISTS incidencia;
CREATE TABLE incidencia (
    id_incidencia    INT AUTO_INCREMENT PRIMARY KEY,
    id_monitoratge   INT NOT NULL,
    data_registre    DATETIME NOT NULL,
    tipus            ENUM('Plaga','Malaltia','Deficiencia','Mala Herba','Altres') NOT NULL,
    descripcio       TEXT,
    gravetat         ENUM('Baixa','Mitjana','Alta','Critica') DEFAULT 'Mitjana',
    accio_correctiva TEXT,
    estat            ENUM('pendent','en_proces','resolt') DEFAULT 'pendent',
    FOREIGN KEY (id_monitoratge) REFERENCES monitoratge_plaga (id_monitoratge) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 25: Protocol_Tractament
DROP TABLE IF EXISTS protocol_tractament;
CREATE TABLE protocol_tractament (
    id_protocol          INT AUTO_INCREMENT PRIMARY KEY,
    nom_protocol         VARCHAR(100) NOT NULL UNIQUE,
    descripcio           TEXT,
    productes_json       JSON CHECK (JSON_VALID(productes_json)),
    condicions_ambientals TEXT
) ENGINE = InnoDB;

-- 26: Incidencia_Protocol
DROP TABLE IF EXISTS incidencia_protocol;
CREATE TABLE incidencia_protocol (
    id_incidencia INT NOT NULL,
    id_protocol   INT NOT NULL,
    PRIMARY KEY (id_incidencia, id_protocol),
    FOREIGN KEY (id_incidencia) REFERENCES incidencia          (id_incidencia) ON DELETE CASCADE,
    FOREIGN KEY (id_protocol)   REFERENCES protocol_tractament (id_protocol)   ON DELETE RESTRICT,
    INDEX idx_incidencia (id_incidencia),
    INDEX idx_protocol   (id_protocol)
) ENGINE = InnoDB;

-- 27: Tractament_Programat
DROP TABLE IF EXISTS tractament_programat;
CREATE TABLE tractament_programat (
    id_programat      INT AUTO_INCREMENT PRIMARY KEY,
    id_sector         INT  NOT NULL,
    id_protocol       INT,
    id_producte       INT           NULL
        COMMENT 'Producte fitosanitari o fertilitzant previst',
    dosi_prevista_ha  DECIMAL(10,2) NULL
        CHECK (dosi_prevista_ha IS NULL OR dosi_prevista_ha > 0)
        COMMENT 'Dosi prevista en l/ha o kg/ha',
    data_prevista     DATE NOT NULL,
    tipus             ENUM('preventiu','correctiu','fertilitzacio') NOT NULL,
    motiu             VARCHAR(255),
    estat             ENUM('pendent','completat','cancel·lat') DEFAULT 'pendent',
    dies_avis         INT DEFAULT 3 CHECK (dies_avis >= 0),
    observacions      TEXT,
    FOREIGN KEY (id_sector)    REFERENCES sector              (id_sector)    ON DELETE CASCADE,
    FOREIGN KEY (id_protocol)  REFERENCES protocol_tractament (id_protocol)  ON DELETE SET NULL,
    FOREIGN KEY (id_producte)  REFERENCES producte_quimic     (id_producte)  ON DELETE SET NULL,
    INDEX idx_sector        (id_sector),
    INDEX idx_estat         (estat),
    INDEX idx_data_prevista (data_prevista),
    INDEX idx_producte      (id_producte)
) ENGINE = InnoDB;

-- ============================================================
-- MÒDUL 4: GESTIÓ DE PERSONAL
-- ============================================================

-- 28: Treballador
DROP TABLE IF EXISTS treballador;
CREATE TABLE treballador (
    id_treballador              INT AUTO_INCREMENT PRIMARY KEY,
    nom                         VARCHAR(100) NOT NULL,
    cognoms                     VARCHAR(100),
    dni                         VARCHAR(20)  NOT NULL UNIQUE,
    data_naixement              DATE         NOT NULL,
    nacionalitat                VARCHAR(50),
    situacio_residencia         VARCHAR(100),
    adreca                      TEXT,
    telefon                     VARCHAR(20),
    email                       VARCHAR(100) CHECK (email IS NULL OR email LIKE '%_@_%._%'),
    contacte_emergencia_nom     VARCHAR(100),
    contacte_emergencia_telefon VARCHAR(20),
    info_bancaria               VARCHAR(34)  COMMENT 'IBAN per a pagaments de nòmina',
    rol                         ENUM('Operari','Supervisor','Responsable','Tecnic','Altres') DEFAULT 'Operari',
    tipus_contracte             ENUM('Indefinit','Temporal','Practicum','Altres') NOT NULL,
    data_alta                   DATE,
    data_baixa                  DATE,
    historial_laboral           TEXT,
    formacio_academic           TEXT,
    habilitats                  TEXT,
    idiomes                     VARCHAR(255),
    num_seguretat_social        VARCHAR(20)  UNIQUE,
    tipus_permis_treball        VARCHAR(50),
    estat                       ENUM('actiu','inactiu','vacances','baix') DEFAULT 'actiu',
    contracte_pdf               TEXT,
    certificat_pdf              TEXT,
    permis_pdf                  TEXT,
    CHECK (data_baixa IS NULL OR data_baixa >= data_alta)
) ENGINE = InnoDB;

-- 29: Certificacio_Treballador
DROP TABLE IF EXISTS certificacio_treballador;
CREATE TABLE certificacio_treballador (
    id_certificacio    INT AUTO_INCREMENT PRIMARY KEY,
    id_treballador     INT  NOT NULL,
    tipus_certificacio VARCHAR(100) NOT NULL COMMENT 'Aplicador fitosanitaris, carnet conduir, etc.',
    entitat_emissora   VARCHAR(100),
    data_obtencio      DATE NOT NULL,
    data_caducitat     DATE         COMMENT 'NULL si no caduca',
    ambit_aplicacio    VARCHAR(255),
    document_pdf       TEXT,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE CASCADE,
    INDEX idx_treballador (id_treballador),
    INDEX idx_caducitat   (data_caducitat)
) ENGINE = InnoDB;

-- 30: Permis_Absencia
DROP TABLE IF EXISTS permis_absencia;
CREATE TABLE permis_absencia (
    id_permis      INT AUTO_INCREMENT PRIMARY KEY,
    id_treballador INT NOT NULL,
    tipus          ENUM('vacances','permis','baixa_malaltia','baixa_accident','curs','altres') NOT NULL,
    data_inici     DATE NOT NULL,
    data_fi        DATE COMMENT 'NULL si la baixa encara està oberta',
    motiu          VARCHAR(255),
    aprovat        BOOLEAN DEFAULT FALSE,
    document_pdf   TEXT,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE CASCADE,
    CHECK (data_fi IS NULL OR data_fi >= data_inici),
    INDEX idx_treballador (id_treballador),
    INDEX idx_tipus       (tipus)
) ENGINE = InnoDB;

-- 31: Tasca
DROP TABLE IF EXISTS tasca;
CREATE TABLE tasca (
    id_tasca                    INT AUTO_INCREMENT PRIMARY KEY,
    id_sector                   INT,
    tipus                       ENUM('poda','aclarida','tractament','collita','fertilitzacio','reg','manteniment','altres') NOT NULL,
    descripcio                  TEXT,
    data_inici_prevista         DATE NOT NULL,
    data_fi_prevista            DATE,
    duracio_estimada_h          DECIMAL(5,2) CHECK (duracio_estimada_h >= 0),
    num_treballadors_necessaris INT CHECK (num_treballadors_necessaris >= 0),
    qualificacions_necessaries  TEXT,
    equipament_necessari        TEXT,
    instruccions                TEXT,
    tasca_precedent             INT  COMMENT 'ID de la tasca que cal completar prèviament',
    estat                       ENUM('pendent','en_proces','completada','cancel·lada') DEFAULT 'pendent',
    FOREIGN KEY (id_sector)       REFERENCES sector (id_sector) ON DELETE SET NULL,
    FOREIGN KEY (tasca_precedent) REFERENCES tasca  (id_tasca)  ON DELETE SET NULL,
    CHECK (data_fi_prevista IS NULL OR data_fi_prevista >= data_inici_prevista),
    INDEX idx_sector (id_sector),
    INDEX idx_estat  (estat),
    INDEX idx_data   (data_inici_prevista)
) ENGINE = InnoDB;

-- 32: Assignacio_Tasca
DROP TABLE IF EXISTS assignacio_tasca;
CREATE TABLE assignacio_tasca (
    id_assignacio   INT AUTO_INCREMENT PRIMARY KEY,
    id_tasca        INT NOT NULL,
    id_treballador  INT NOT NULL,
    data_inici_real DATETIME,
    data_fi_real    DATETIME,
    notes           TEXT,
    estat           ENUM('assignat','en_proces','completat') DEFAULT 'assignat',
    FOREIGN KEY (id_tasca)       REFERENCES tasca       (id_tasca)       ON DELETE CASCADE,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE CASCADE,
    UNIQUE (id_tasca, id_treballador),
    CHECK (data_fi_real IS NULL OR data_fi_real >= data_inici_real),
    INDEX idx_tasca       (id_tasca),
    INDEX idx_treballador (id_treballador)
) ENGINE = InnoDB;

-- 33: Jornada
DROP TABLE IF EXISTS jornada;
CREATE TABLE jornada (
    id_jornada      INT AUTO_INCREMENT PRIMARY KEY,
    id_treballador  INT NOT NULL,
    id_tasca        INT  COMMENT 'Tasca associada, si escau',
    data_hora_inici DATETIME NOT NULL,
    data_hora_fi    DATETIME COMMENT 'NULL si la jornada encara està oberta',
    pausa_minuts    INT DEFAULT 0 CHECK (pausa_minuts >= 0),
    ubicacio        VARCHAR(255),
    incidencies     TEXT,
    validada        BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE CASCADE,
    FOREIGN KEY (id_tasca)       REFERENCES tasca       (id_tasca)       ON DELETE SET NULL,
    CHECK (data_hora_fi IS NULL OR data_hora_fi >= data_hora_inici),
    INDEX idx_treballador (id_treballador),
    INDEX idx_data        (data_hora_inici),
    INDEX idx_tasca       (id_tasca)
) ENGINE = InnoDB;

-- 34: Planificacio_Personal
DROP TABLE IF EXISTS planificacio_personal;
CREATE TABLE planificacio_personal (
    id_planificacio             INT AUTO_INCREMENT PRIMARY KEY,
    temporada                   YEAR NOT NULL,
    periode                     VARCHAR(50) NOT NULL COMMENT 'Poda, aclarida, collita, etc.',
    data_inici                  DATE NOT NULL,
    data_fi                     DATE NOT NULL,
    num_treballadors_necessaris INT  NOT NULL CHECK (num_treballadors_necessaris >= 0),
    perfil_necessari            VARCHAR(255),
    observacions                TEXT,
    CHECK (data_fi >= data_inici),
    INDEX idx_temporada (temporada)
) ENGINE = InnoDB;

-- 35: Usuari (Sistema d'Autenticació)
DROP TABLE IF EXISTS `usuari`;
CREATE TABLE `usuari` (
  `id_usuari` INT NOT NULL AUTO_INCREMENT,
  `nom_usuari` VARCHAR(50) NOT NULL UNIQUE,
  `contrasenya` VARCHAR(255) NOT NULL,
  `nom_complet` VARCHAR(100) NOT NULL,
  `rol` ENUM('admin', 'tecnic', 'responsable', 'operari') NOT NULL DEFAULT 'operari',
  `estat` ENUM('actiu', 'inactiu') NOT NULL DEFAULT 'actiu',
  `id_treballador` INT DEFAULT NULL,
  PRIMARY KEY (`id_usuari`),
  CONSTRAINT `fk_usuari_treballador` FOREIGN KEY (`id_treballador`) REFERENCES `treballador` (`id_treballador`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MÒDUL 2 (continuació): MAQUINÀRIA I APLICACIÓ
-- ============================================================

-- 36: Maquinaria
DROP TABLE IF EXISTS maquinaria;
CREATE TABLE maquinaria (
    id_maquinaria            INT AUTO_INCREMENT PRIMARY KEY,
    nom_maquina              VARCHAR(100) NOT NULL UNIQUE,
    tipus                    ENUM('Tractor','Pulveritzador','Poda','Cistella','Altres') NOT NULL,
    any_fabricacio           YEAR,
    cabal_l_min              DECIMAL(8,2) CHECK (cabal_l_min >= 0)               COMMENT 'Cabal en l/min per al càlcul del volum de caldo',
    velocitat_aplicacio_km_h DECIMAL(5,2) CHECK (velocitat_aplicacio_km_h >= 0)  COMMENT 'Velocitat d''aplicació en km/h',
    capacitat_diposit_l      DECIMAL(8,2) CHECK (capacitat_diposit_l >= 0)       COMMENT 'Capacitat del dipòsit en litres',
    manteniment_json         JSON CHECK (JSON_VALID(manteniment_json))
) ENGINE = InnoDB;

-- 37: Aplicacio
DROP TABLE IF EXISTS aplicacio;
CREATE TABLE aplicacio (
    id_aplicacio           INT AUTO_INCREMENT PRIMARY KEY,
    id_sector              INT  NOT NULL,
    id_treballador         INT,
    id_monitoratge         INT,
    data_event             DATE NOT NULL,
    hora_inici_planificada DATETIME,
    hora_fi_planificada    DATETIME,
    tipus_event            VARCHAR(100) CHECK (tipus_event IS NULL OR CHAR_LENGTH(TRIM(tipus_event)) > 0),
    metode_aplicacio       ENUM('fertirrigacio','foliar','sol','altres'),
    descripcio             TEXT,
    volum_caldo            DECIMAL(10,2) CHECK (volum_caldo >= 0),
    concentracio_N         DECIMAL(5,2)  CHECK (concentracio_N >= 0),
    concentracio_P         DECIMAL(5,2)  CHECK (concentracio_P >= 0),
    concentracio_K         DECIMAL(5,2)  CHECK (concentracio_K >= 0),
    estat_fenologic        VARCHAR(100)  COMMENT 'Estat fenològic (requerit per normativa)',
    num_carnet_aplicador   VARCHAR(50)   COMMENT 'Número carnet de l''aplicador (requerit per normativa)',
    condicions_ambientals  TEXT,
    FOREIGN KEY (id_sector)      REFERENCES sector            (id_sector)      ON DELETE CASCADE,
    FOREIGN KEY (id_treballador) REFERENCES treballador       (id_treballador) ON DELETE SET NULL,
    FOREIGN KEY (id_monitoratge) REFERENCES monitoratge_plaga (id_monitoratge) ON DELETE SET NULL,
    CHECK (hora_fi_planificada IS NULL OR hora_fi_planificada >= hora_inici_planificada)
) ENGINE = InnoDB;

-- 38: Detall_Aplicacio_Producte
DROP TABLE IF EXISTS detall_aplicacio_producte;
CREATE TABLE detall_aplicacio_producte (
    id_aplicacio              INT NOT NULL,
    id_estoc                  INT NOT NULL COMMENT 'Lot específic (traçabilitat del magatzem al camp)',
    dosi_aplicada             DECIMAL(10,2) CHECK (dosi_aplicada >= 0)             COMMENT 'Dosi per unitat de superfície',
    quantitat_consumida_total DECIMAL(10,2) CHECK (quantitat_consumida_total >= 0) COMMENT 'Quantitat total d''estoc consumida',
    PRIMARY KEY (id_aplicacio, id_estoc),
    FOREIGN KEY (id_aplicacio) REFERENCES aplicacio       (id_aplicacio) ON DELETE CASCADE,
    FOREIGN KEY (id_estoc)     REFERENCES inventari_estoc (id_estoc)     ON DELETE RESTRICT,
    INDEX idx_aplicacio (id_aplicacio),
    INDEX idx_estoc     (id_estoc)
) ENGINE = InnoDB;

-- 39: Maquinaria_Aplicacio
DROP TABLE IF EXISTS maquinaria_aplicacio;
CREATE TABLE maquinaria_aplicacio (
    id_maquinaria     INT NOT NULL,
    id_aplicacio      INT NOT NULL,
    hores_utilitzades DECIMAL(5,2) CHECK (hores_utilitzades >= 0),
    PRIMARY KEY (id_maquinaria, id_aplicacio),
    FOREIGN KEY (id_maquinaria) REFERENCES maquinaria (id_maquinaria) ON DELETE RESTRICT,
    FOREIGN KEY (id_aplicacio)  REFERENCES aplicacio  (id_aplicacio)  ON DELETE CASCADE,
    INDEX idx_maquinaria (id_maquinaria),
    INDEX idx_aplicacio  (id_aplicacio)
) ENGINE = InnoDB;

-- 40: Fila_Aplicacio
DROP TABLE IF EXISTS fila_aplicacio;
CREATE TABLE fila_aplicacio (
    id_fila_aplicada    INT      NOT NULL COMMENT 'Fila on s''ha aplicat el tractament',
    id_aplicacio        INT      NOT NULL COMMENT 'Operació concreta',
    id_treballador      INT      NOT NULL COMMENT 'Treballador responsable d''aquesta fila',
    data_inici          DATETIME NOT NULL,
    data_fi             DATETIME NULL     COMMENT 'NULL si interrompuda',
    hores_treballades   DECIMAL(5,2)  CHECK (hores_treballades >= 0),
    percentatge_complet DECIMAL(5,2)  DEFAULT 100 CHECK (percentatge_complet BETWEEN 0 AND 100),
    longitud_tractada_m DECIMAL(10,2) CHECK (longitud_tractada_m >= 0) NULL,
    coordenada_final    POINT         NOT NULL COMMENT 'GPS on es va aturar',
    estat               ENUM('pendent','en_proces','completada','aturada') DEFAULT 'completada',
    observacions        TEXT,
    PRIMARY KEY (id_fila_aplicada, id_aplicacio, id_treballador),
    FOREIGN KEY (id_fila_aplicada) REFERENCES fila_arbre  (id_fila)          ON DELETE CASCADE,
    FOREIGN KEY (id_aplicacio)     REFERENCES aplicacio   (id_aplicacio)     ON DELETE CASCADE,
    FOREIGN KEY (id_treballador)   REFERENCES treballador (id_treballador)   ON DELETE CASCADE,
    CHECK (data_fi IS NULL OR data_fi >= data_inici)
) ENGINE = InnoDB;

CREATE SPATIAL INDEX idx_fila_aplicacio_geo ON fila_aplicacio (coordenada_final);

-- ============================================================
-- MÒDUL 3: GESTIÓ DE LA COLLITA I PRODUCCIÓ
-- ============================================================

-- 41: Inversio
DROP TABLE IF EXISTS inversio;
CREATE TABLE inversio (
    id_inversio    INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacio   INT  NULL                                    COMMENT 'Plantació associada (NULL = despesa global)',
    data_inversio  DATE NOT NULL,
    concepte       VARCHAR(255) NOT NULL,
    `import`       DECIMAL(10,2) NOT NULL CHECK (`import` >= 0),
    vida_util_anys INT CHECK (vida_util_anys IS NULL OR vida_util_anys >= 0),
    categoria      ENUM('Maquinaria','Fitosanitaris','Adobs','Infraestructura','Personal','Serveis','Altres')
                   NOT NULL DEFAULT 'Altres'                    COMMENT 'Categoria per a anàlisi de costos',
    proveidor      VARCHAR(150) DEFAULT NULL                    COMMENT 'Nom del proveïdor o empresa facturadora',
    id_sector      INT NULL                                     COMMENT 'Sector associat (cost per hectàrea)',
    id_maquinaria  INT NULL                                     COMMENT 'Màquina associada (despesa de manteniment)',
    observacions   TEXT DEFAULT NULL                            COMMENT 'Número de factura, albarà o notes',
    FOREIGN KEY (id_plantacio)   REFERENCES plantacio  (id_plantacio)   ON DELETE CASCADE,
    FOREIGN KEY (id_sector)      REFERENCES sector     (id_sector)      ON DELETE SET NULL,
    FOREIGN KEY (id_maquinaria)  REFERENCES maquinaria (id_maquinaria)  ON DELETE SET NULL,
    INDEX idx_plantacio  (id_plantacio),
    INDEX idx_categoria  (categoria),
    INDEX idx_data       (data_inversio)
) ENGINE = InnoDB;

-- 42: Previsio_Collita
DROP TABLE IF EXISTS previsio_collita;
CREATE TABLE previsio_collita (
    id_previsio                 INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacio                INT  NOT NULL,
    temporada                   YEAR NOT NULL,
    data_previsio               DATE NOT NULL,
    produccio_estimada_kg       DECIMAL(10,2) CHECK (produccio_estimada_kg >= 0),
    produccio_per_arbre_kg      DECIMAL(8,2)  CHECK (produccio_per_arbre_kg >= 0),
    data_inici_collita_estimada DATE,
    data_fi_collita_estimada    DATE,
    calibre_previst             VARCHAR(50),
    qualitat_prevista           ENUM('Extra','Primera','Segona','Industrial'),
    mo_necessaria_jornal        INT CHECK (mo_necessaria_jornal >= 0) COMMENT 'Mà d''obra estimada en jornals',
    factors_considerats         TEXT COMMENT 'Floració, quallat, clima, incidències',
    FOREIGN KEY (id_plantacio) REFERENCES plantacio (id_plantacio) ON DELETE CASCADE,
    UNIQUE (id_plantacio, temporada),
    INDEX idx_temporada (temporada)
) ENGINE = InnoDB;

-- 43: Collita
DROP TABLE IF EXISTS collita;
CREATE TABLE collita (
    id_collita                 INT AUTO_INCREMENT PRIMARY KEY,
    id_plantacio               INT NOT NULL,
    id_treballador_responsable INT COMMENT 'Treballador responsable de la recol·lecció',
    data_inici                 DATETIME NOT NULL,
    data_fi                    DATETIME NULL,
    quantitat                  DECIMAL(10,2) NOT NULL CHECK (quantitat >= 0),
    unitat_mesura              ENUM('kg','caixes','bins','altres') DEFAULT 'kg',
    qualitat                   ENUM('Extra','Primera','Segona','Industrial') DEFAULT 'Primera',
    condicions_ambientals      TEXT,
    estat_fenologic_maduresa   VARCHAR(100),
    incidencies                TEXT,
    observacions               TEXT,
    FOREIGN KEY (id_plantacio)               REFERENCES plantacio   (id_plantacio)   ON DELETE CASCADE,
    FOREIGN KEY (id_treballador_responsable) REFERENCES treballador (id_treballador) ON DELETE SET NULL,
    CHECK (data_fi IS NULL OR data_fi >= data_inici),
    INDEX idx_plantacio   (id_plantacio),
    INDEX idx_treballador (id_treballador_responsable)
) ENGINE = InnoDB;

-- 44: Collita_Treballador
DROP TABLE IF EXISTS collita_treballador;
CREATE TABLE collita_treballador (
    id_collita        INT NOT NULL,
    id_treballador    INT NOT NULL,
    hores_treballades DECIMAL(5,2)  CHECK (hores_treballades >= 0),
    kg_recollectats   DECIMAL(10,2) CHECK (kg_recollectats >= 0),
    PRIMARY KEY (id_collita, id_treballador),
    FOREIGN KEY (id_collita)     REFERENCES collita     (id_collita)     ON DELETE CASCADE,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE CASCADE,
    INDEX idx_treballador (id_treballador)
) ENGINE = InnoDB;

-- 45: Lot_Produccio
DROP TABLE IF EXISTS lot_produccio;
CREATE TABLE lot_produccio (
    id_lot                  INT AUTO_INCREMENT PRIMARY KEY,
    id_collita              INT NOT NULL,
    identificador           VARCHAR(100) NOT NULL UNIQUE COMMENT 'Codi intern únic del lot',
    codi_qr                 TEXT         COMMENT 'Contingut del QR per a traçabilitat',
    data_processat          DATE,
    pes_kg                  DECIMAL(10,2) CHECK (pes_kg >= 0),
    qualitat                ENUM('Extra','Primera','Segona','Industrial') DEFAULT 'Primera',
    desti                   VARCHAR(255) COMMENT 'Mercat, transformació, exportació',
    client_final            VARCHAR(255),
    tractaments_postcollita TEXT,
    transport_condicions    TEXT,
    id_parcela              INT,
    id_sector               INT,
    id_fila                 INT,
    FOREIGN KEY (id_collita) REFERENCES collita    (id_collita) ON DELETE CASCADE,
    FOREIGN KEY (id_parcela) REFERENCES parcela    (id_parcela) ON DELETE SET NULL,
    FOREIGN KEY (id_sector)  REFERENCES sector     (id_sector)  ON DELETE SET NULL,
    FOREIGN KEY (id_fila)    REFERENCES fila_arbre (id_fila)    ON DELETE SET NULL,
    INDEX idx_parcela (id_parcela),
    INDEX idx_sector  (id_sector),
    INDEX idx_fila    (id_fila)
) ENGINE = InnoDB;

-- 46: Control_Qualitat
DROP TABLE IF EXISTS control_qualitat;
CREATE TABLE control_qualitat (
    id_control        INT AUTO_INCREMENT PRIMARY KEY,
    id_lot            INT NOT NULL,
    id_inspector      INT COMMENT 'Treballador que ha realitzat el control',
    data_control      DATE NOT NULL,
    calibre_mm        DECIMAL(5,2) CHECK (calibre_mm >= 0),
    color             VARCHAR(50),
    fermesa_kg_cm2    DECIMAL(5,2) CHECK (fermesa_kg_cm2 >= 0),
    defectes_visibles TEXT,
    sabor             VARCHAR(100),
    aroma             VARCHAR(100),
    textura           VARCHAR(100),
    resultat          ENUM('Acceptat','Rebutjat','Condicional') DEFAULT 'Acceptat',
    comentaris        TEXT,
    FOREIGN KEY (id_lot)       REFERENCES lot_produccio (id_lot)         ON DELETE CASCADE,
    FOREIGN KEY (id_inspector) REFERENCES treballador   (id_treballador) ON DELETE SET NULL,
    INDEX idx_lot (id_lot)
) ENGINE = InnoDB;

-- ============================================================
-- SISTEMA DE LOG
-- ============================================================

-- 47: Log_Accions
DROP TABLE IF EXISTS log_accions;
CREATE TABLE log_accions (
    id_log              INT AUTO_INCREMENT PRIMARY KEY,
    id_treballador      INT NOT NULL,
    data_hora           DATETIME     NOT NULL,
    accio               VARCHAR(255) NOT NULL,
    taula_afectada      VARCHAR(100),
    id_registre_afectat INT,
    comentaris          TEXT,
    FOREIGN KEY (id_treballador) REFERENCES treballador (id_treballador) ON DELETE RESTRICT,
    INDEX idx_treballador (id_treballador)
) ENGINE = InnoDB;


-- ============================================================
-- MODULS ADICIONALS (Comandes, Inventari Fisic, Manteniment)
-- ============================================================

DROP FUNCTION IF EXISTS generar_num_comanda;
DROP FUNCTION IF EXISTS generar_num_factura;

-- Taules per a la gestió de clients i comandes de productes agrícoles
-- Sistema complet de facturació i gestió comercial

-- Taula de clients
DROP TABLE IF EXISTS client;
CREATE TABLE client (
    id_client INT AUTO_INCREMENT PRIMARY KEY,
    nom_client VARCHAR(100) NOT NULL,
    nif_cif VARCHAR(12) UNIQUE COMMENT 'NIF/CIF del client',
    adreca TEXT COMMENT 'Adreça completa del client',
    poblacio VARCHAR(100) COMMENT 'Població',
    codi_postal VARCHAR(10) COMMENT 'Codi postal',
    telefon VARCHAR(20) COMMENT 'Telèfon de contacte',
    email VARCHAR(100) COMMENT 'Correu electrònic',
    tipus_client ENUM('particular','empresa','cooperativa','altres') DEFAULT 'particular',
    observacions TEXT COMMENT 'Notes addicionals sobre el client',
    data_creacio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estat ENUM('actiu','inactiu') DEFAULT 'actiu',
    INDEX idx_nif_cif (nif_cif),
    INDEX idx_tipus_client (tipus_client),
    INDEX idx_estat (estat)
) ENGINE = InnoDB COMMENT = 'Clients de l\'explotació agrícola';

-- Taula de comandes
DROP TABLE IF EXISTS comanda;
CREATE TABLE comanda (
    id_comanda INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    num_comanda VARCHAR(20) UNIQUE COMMENT 'Número de comanda únic',
    data_comanda DATE NOT NULL COMMENT 'Data de la comanda',
    data_entrega_prevista DATE COMMENT 'Data prevista de lliurament',
    estat_comanda ENUM('pendent','preparacio','enviat','entregat','cancelat') DEFAULT 'pendent',
    forma_pagament ENUM('transferencia','targeta','efectiu','poder','altres') DEFAULT 'transferencia',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal sense IVA',
    iva_percentatge DECIMAL(5,2) DEFAULT 21.00 COMMENT 'Percentatge d\'IVA',
    iva_import DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Import d\'IVA',
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amb IVA',
    observacions TEXT COMMENT 'Observacions de la comanda',
    data_creacio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES client (id_client) ON DELETE RESTRICT,
    INDEX idx_client (id_client),
    INDEX idx_num_comanda (num_comanda),
    INDEX idx_data_comanda (data_comanda),
    INDEX idx_estat_comanda (estat_comanda)
) ENGINE = InnoDB COMMENT 'Comandes de clients';

-- Taula detall de comanda
DROP TABLE IF EXISTS detall_comanda;
CREATE TABLE detall_comanda (
    id_detall INT AUTO_INCREMENT PRIMARY KEY,
    id_comanda INT NOT NULL,
    id_producte INT NOT NULL COMMENT 'Producte químic o altres productes',
    quantitat DECIMAL(10,2) NOT NULL COMMENT 'Quantitat sol·licitada',
    preu_unitari DECIMAL(10,2) NOT NULL COMMENT 'Preu unitari sense IVA',
    descompte_percent DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Descompte percentual',
    descompte_import DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Import descompte',
    subtotal_linia DECIMAL(10,2) NOT NULL COMMENT 'Subtotal de la línia',
    unitat_mesura VARCHAR(20) COMMENT 'Unitat de mesura',
    observacions TEXT COMMENT 'Observacions de la línia',
    FOREIGN KEY (id_comanda) REFERENCES comanda (id_comanda) ON DELETE CASCADE,
    FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE RESTRICT,
    INDEX idx_comanda (id_comanda),
    INDEX idx_producte (id_producte)
) ENGINE = InnoDB COMMENT 'Detall de productes de cada comanda';

-- Taula de factures
DROP TABLE IF EXISTS factura;
CREATE TABLE factura (
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    id_comanda INT NOT NULL,
    num_factura VARCHAR(20) UNIQUE COMMENT 'Número de factura únic',
    data_factura DATE NOT NULL COMMENT 'Data d\'emissió de la factura',
    data_venciment DATE COMMENT 'Data de venciment',
    forma_pagament ENUM('transferencia','targeta','efectiu','poder','altres') DEFAULT 'transferencia',
    estat_factura ENUM('pendent','pagada','vençuda','cancelada') DEFAULT 'pendent',
    base_imposable DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    iva_percentatge DECIMAL(5,2) DEFAULT 21.00,
    iva_import DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_factura DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observacions TEXT COMMENT 'Observacions de la factura',
    ruta_pdf VARCHAR(255) COMMENT 'Ruta al fitxer PDF generat',
    data_creacio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_comanda) REFERENCES comanda (id_comanda) ON DELETE RESTRICT,
    INDEX idx_comanda (id_comanda),
    INDEX idx_num_factura (num_factura),
    INDEX idx_data_factura (data_factura),
    INDEX idx_estat_factura (estat_factura)
) ENGINE = InnoDB COMMENT 'Factures generades a partir de comandes';

-- Funció per generar números de comanda únics
DELIMITER //
CREATE FUNCTION generar_num_comanda() RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE num_comanda VARCHAR(20);
    DECLARE contador INT;
    
    SET contador = (SELECT COALESCE(MAX(CAST(SUBSTRING(num_comanda, 4) AS UNSIGNED)), 0) + 1 
                   FROM comanda 
                   WHERE num_comanda LIKE CONCAT(DATE_FORMAT(CURDATE(), '%y%m'), '%'));
    
    SET num_comanda = CONCAT(DATE_FORMAT(CURDATE(), '%y%m'), LPAD(contador, 4, '0'));
    
    RETURN num_comanda;
END //
DELIMITER ;

-- Funció per generar números de factura únics
DELIMITER //
CREATE FUNCTION generar_num_factura() RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE num_factura VARCHAR(20);
    DECLARE contador INT;
    
    SET contador = (SELECT COALESCE(MAX(CAST(SUBSTRING(num_factura, 4) AS UNSIGNED)), 0) + 1 
                   FROM factura 
                   WHERE num_factura LIKE CONCAT(DATE_FORMAT(CURDATE(), '%y%m'), '%'));
    
    SET num_factura = CONCAT('F', DATE_FORMAT(CURDATE(), '%y%m'), LPAD(contador, 4, '0'));
    
    RETURN num_factura;
END //
DELIMITER ;

-- Taula per guardar historial d'inventaris físics
-- Aquesta taula permet traçar les regularitzacions d'estoc realitzades

DROP TABLE IF EXISTS inventari_fisic_registre;
CREATE TABLE inventari_fisic_registre (
    id_registre      INT AUTO_INCREMENT PRIMARY KEY,
    id_producte      INT NOT NULL,
    data_inventari   DATE NOT NULL,
    estoc_teoric     DECIMAL(10,2) NOT NULL COMMENT 'Estoc segons sistema abans de regularització',
    estoc_real       DECIMAL(10,2) NOT NULL COMMENT 'Estoc comptat físicament',
    diferencia       DECIMAL(10,2) NOT NULL COMMENT 'estoc_real - estoc_teoric (positiu = sobra, negatiu = falta)',
    observacions     VARCHAR(255) COMMENT 'Observacions generals de l\'inventari',
    data_creacio     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_producte) REFERENCES producte_quimic (id_producte) ON DELETE CASCADE,
    INDEX idx_data_inventari (data_inventari),
    INDEX idx_producte (id_producte),
    INDEX idx_diferencia (diferencia)
) ENGINE = InnoDB COMMENT = 'Historial d\'inventaris físics i regularitzacions d\'estoc';

-- Taula per manteniment de maquinària agrícola
-- Permet registrar les tasques de manteniment preventiu i correctiu

DROP TABLE IF EXISTS manteniment_maquinaria;
CREATE TABLE manteniment_maquinaria (
    id_manteniment INT AUTO_INCREMENT PRIMARY KEY,
    id_maquinaria   INT NOT NULL,
    data_programada DATE NOT NULL,
    data_realitzada DATE NULL COMMENT 'Data real en que es va realitzar el manteniment',
    tipus_manteniment ENUM('preventiu','correctiu','inspeccio','reparacio') NOT NULL DEFAULT 'preventiu',
    descripcio TEXT NOT NULL COMMENT 'Descripció detallada del manteniment',
    cost DECIMAL(10,2) NULL COMMENT 'Cost del manteniment (opcional)',
    realitzat BOOLEAN DEFAULT FALSE COMMENT 'Indica si el manteniment s\'ha completat',
    observacions TEXT COMMENT 'Notes addicionals sobre el manteniment',
    data_creacio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_maquinaria) REFERENCES maquinaria (id_maquinaria) ON DELETE CASCADE,
    INDEX idx_maquinaria_data (id_maquinaria, data_programada),
    INDEX idx_realitzat (realitzat),
    INDEX idx_data_programada (data_programada)
) ENGINE = InnoDB COMMENT = 'Registre de manteniment de maquinària agrícola';


SET FOREIGN_KEY_CHECKS = 1;