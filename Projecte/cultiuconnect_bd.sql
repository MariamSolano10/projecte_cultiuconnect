-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-01-2026 a las 09:34:05
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `cultiuconnect_bd`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aplicacio`
--

CREATE TABLE `aplicacio` (
  `id_aplicacio` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `data_event` date NOT NULL,
  `tipus_event` varchar(100) DEFAULT NULL COMMENT 'Poda, aclarida, tractaments, fertilitzacions, etc.',
  `descripcio` text DEFAULT NULL,
  `volum_caldo` decimal(10,2) DEFAULT NULL COMMENT 'Volum de caldo utilitzat/preparat (si aplica)',
  `maquinaria_utilitzada` varchar(100) DEFAULT NULL COMMENT 'Equip o maquinaria utilitzada en l''aplicacio',
  `id_treballador` int(11) DEFAULT NULL COMMENT 'Operari responsable de l''aplicacio',
  `condicions_ambientals` text DEFAULT NULL COMMENT 'Temperatura, vent, etc. durant l''aplicacio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caracteristiques_sol`
--

CREATE TABLE `caracteristiques_sol` (
  `id_parcela` int(11) NOT NULL COMMENT 'Clau Forana a la parcela analitzada',
  `data_analisis` date NOT NULL COMMENT 'Data en que es va realitzar l''analisi',
  `textura` varchar(50) DEFAULT NULL,
  `pH` decimal(4,2) DEFAULT NULL,
  `materia_organica` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `collita`
--

CREATE TABLE `collita` (
  `id_collita` int(11) NOT NULL,
  `id_plantacio` int(11) NOT NULL,
  `data_collita` date NOT NULL,
  `quantitat_kg` decimal(10,2) NOT NULL,
  `qualitat` enum('Extra','Primera','Segona','Industrial') DEFAULT 'Primera',
  `observacions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compra_producte`
--

CREATE TABLE `compra_producte` (
  `id_compra` int(11) NOT NULL,
  `id_proveidor` int(11) NOT NULL,
  `id_producte` int(11) NOT NULL,
  `data_compra` date NOT NULL,
  `quantitat` decimal(10,2) DEFAULT NULL,
  `preu_unitari` decimal(10,2) DEFAULT NULL,
  `num_lot` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_qualitat`
--

CREATE TABLE `control_qualitat` (
  `id_control` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `data_control` date NOT NULL,
  `resultat` enum('Acceptat','Rebutjat','Condicional') DEFAULT 'Acceptat',
  `comentaris` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detall_aplicacio_producte`
--

CREATE TABLE `detall_aplicacio_producte` (
  `id_aplicacio` int(11) NOT NULL,
  `id_estoc` int(11) NOT NULL COMMENT 'Lot especific utilitzat',
  `dosi_aplicada` decimal(10,2) DEFAULT NULL COMMENT 'Dosi real aplicada per unitat',
  `quantitat_consumida_total` decimal(10,2) DEFAULT NULL COMMENT 'Quantitat total d''estoc consumida'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especie`
--

CREATE TABLE `especie` (
  `id_especie` int(11) NOT NULL,
  `nom_comu` varchar(100) NOT NULL COMMENT 'Nom comu',
  `nom_cientific` varchar(100) DEFAULT NULL COMMENT 'Nom cientific'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fila_aplicacio`
--

CREATE TABLE `fila_aplicacio` (
  `id_fila_aplicada` int(11) NOT NULL COMMENT 'Identificador de la fila on s’ha aplicat el tractament',
  `id_aplicacio` int(11) NOT NULL COMMENT 'Aplicacio o tractament concret realitzat',
  `data_inici` datetime NOT NULL COMMENT 'Hora d’inici de l’aplicacio a la fila',
  `data_fi` datetime DEFAULT NULL COMMENT 'Hora de finalitzacio de l’aplicacio a la fila',
  `percentatge_complet` decimal(5,2) DEFAULT 100.00 COMMENT 'Percentatge de la fila tractada (0–100%)',
  `longitud_tratada_m` decimal(10,2) DEFAULT NULL COMMENT 'Longitud efectiva tractada en metres',
  `coordenada_final` point NOT NULL COMMENT 'Coordenada GPS on es va aturar l’aplicacio',
  `estat` enum('pendent','en proces','completada','aturada') DEFAULT 'completada' COMMENT 'Estat de l’aplicacio a la fila',
  `observacions` text DEFAULT NULL COMMENT 'Comentaris o incidencies durant l’aplicacio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fila_arbre`
--

CREATE TABLE `fila_arbre` (
  `id_fila` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `coordenades_geo` geometry DEFAULT NULL COMMENT 'Enregistrat per on passen les diferents files d''arbres'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencia`
--

CREATE TABLE `incidencia` (
  `id_incidencia` int(11) NOT NULL,
  `id_monitoratge` int(11) NOT NULL,
  `data_registre` datetime NOT NULL,
  `tipus` enum('Plaga','Malaltia','Deficiencia','Mala Herba','Altres') NOT NULL,
  `descripcio` text DEFAULT NULL,
  `gravetat` enum('Baixa','Mitjana','Alta','Critica') DEFAULT 'Mitjana',
  `accio_correctiva` text DEFAULT NULL,
  `estat` enum('pendent','en_proces','resolt') DEFAULT 'pendent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencia_protocol`
--

CREATE TABLE `incidencia_protocol` (
  `id_incidencia` int(11) NOT NULL,
  `id_protocol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `infraestructura`
--

CREATE TABLE `infraestructura` (
  `id_infra` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `tipus` enum('reg','camin','tanca','edificacio','altres') NOT NULL COMMENT 'Sistemes de reg, camins d''acces, tanques'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventari_estoc`
--

CREATE TABLE `inventari_estoc` (
  `id_estoc` int(11) NOT NULL,
  `id_producte` int(11) NOT NULL,
  `num_lot` varchar(100) NOT NULL,
  `quantitat_disponible` decimal(10,2) NOT NULL,
  `unitat_mesura` varchar(20) NOT NULL,
  `data_caducitat` date DEFAULT NULL,
  `ubicacio_magatzem` varchar(100) DEFAULT NULL,
  `data_compra` date DEFAULT NULL,
  `proveidor` varchar(100) DEFAULT NULL,
  `preu_adquisicio` decimal(10,2) DEFAULT NULL COMMENT 'Per al control de costos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inversio`
--

CREATE TABLE `inversio` (
  `id_inversio` int(11) NOT NULL,
  `id_plantacio` int(11) NOT NULL,
  `data_inversio` date NOT NULL,
  `concepte` varchar(255) NOT NULL,
  `import` decimal(10,2) NOT NULL,
  `vida_util_anys` int(11) DEFAULT NULL COMMENT 'Periodos d''amortitzacio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_accions`
--

CREATE TABLE `log_accions` (
  `id_log` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `data_hora` datetime NOT NULL,
  `accio` varchar(255) NOT NULL,
  `taula_afectada` varchar(100) DEFAULT NULL,
  `id_registre_afectat` int(11) DEFAULT NULL,
  `comentaris` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lot_produccio`
--

CREATE TABLE `lot_produccio` (
  `id_lot` int(11) NOT NULL,
  `id_collita` int(11) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `data_processat` date DEFAULT NULL,
  `pes_kg` decimal(10,2) DEFAULT NULL,
  `qualitat` enum('Extra','Primera','Segona','Industrial') DEFAULT 'Primera',
  `desti` varchar(255) DEFAULT NULL COMMENT 'Mercat, transformació, exportació'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinaria`
--

CREATE TABLE `maquinaria` (
  `id_maquinaria` int(11) NOT NULL,
  `nom_maquina` varchar(100) NOT NULL,
  `tipus` enum('Tractor','Pulveritzador','Poda','Cistella','Altres') NOT NULL,
  `any_fabricacio` year(4) DEFAULT NULL,
  `manteniment_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Registre de manteniment i revisions' CHECK (json_valid(`manteniment_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinaria_applicacio`
--

CREATE TABLE `maquinaria_applicacio` (
  `id_maquinaria` int(11) NOT NULL,
  `id_aplicacio` int(11) NOT NULL,
  `hores_utilitzades` decimal(5,2) DEFAULT NULL COMMENT 'Durada de l’ús per aplicació'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia_activa`
--

CREATE TABLE `materia_activa` (
  `id_materia_activa` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `espectre_accio` text DEFAULT NULL COMMENT 'Plagues/malalties que controla',
  `modes_accio` text DEFAULT NULL COMMENT 'Per evitar resistencies (Requisit Herbicides)',
  `requeriments_normatius` text DEFAULT NULL COMMENT 'Restriccions d''us legals'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `monitoratge_plaga`
--

CREATE TABLE `monitoratge_plaga` (
  `id_monitoratge` int(11) NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `data_observacio` datetime NOT NULL,
  `tipus_problema` enum('Plaga','Malaltia','Deficiencia','Mala Herba') NOT NULL,
  `descripcio_breu` varchar(255) DEFAULT NULL,
  `nivell_poblacio` decimal(5,2) DEFAULT NULL COMMENT 'Nivell de poblacio o index de dany',
  `llindar_intervencio_assolit` tinyint(1) DEFAULT NULL COMMENT 'Indica si s''ha de realitzar un tractament',
  `coordenades_geo` geometry DEFAULT NULL COMMENT 'Geolocalitzacio de l''observacio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parcela`
--

CREATE TABLE `parcela` (
  `id_parcela` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL COMMENT 'Identificador unic i nom descriptiu',
  `coordenades_geo` geometry NOT NULL COMMENT 'Coordenades GPS que delimiten el perimetre',
  `superficie_ha` decimal(10,2) NOT NULL COMMENT 'Superficie total calculada automaticament',
  `pendent` varchar(20) DEFAULT NULL COMMENT 'Factor clau per a la gestio de l''aigua',
  `orientacio` varchar(20) DEFAULT NULL COMMENT 'Factor clau per a l''exposicio solar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parcela_infraestructura`
--

CREATE TABLE `parcela_infraestructura` (
  `id_parcela` int(11) NOT NULL,
  `id_infra` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parcela_sector`
--

CREATE TABLE `parcela_sector` (
  `id_parcela` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `superficie_m2` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantacio`
--

CREATE TABLE `plantacio` (
  `id_plantacio` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `id_varietat` int(11) NOT NULL,
  `data_plantacio` date NOT NULL,
  `marc_fila` decimal(5,2) NOT NULL COMMENT 'Distancia entre files (m)',
  `marc_arbre` decimal(5,2) NOT NULL COMMENT 'Distancia entre arbres (m)',
  `num_arbres_plantats` int(11) DEFAULT NULL COMMENT 'Nombre total d''arbres plantats',
  `origen_material` text DEFAULT NULL COMMENT 'Viver, certificacions',
  `sistema_formacio` varchar(100) DEFAULT NULL COMMENT 'Vas, palmeta, eix central',
  `previsio_entrada_produccio` date DEFAULT NULL COMMENT 'Previsio d''entrada en produccio',
  `data_arrencada` date DEFAULT NULL COMMENT 'Data d''arrencada del cultiu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producte_ma`
--

CREATE TABLE `producte_ma` (
  `id_producte` int(11) NOT NULL,
  `id_materia_activa` int(11) NOT NULL,
  `concentracio` decimal(5,2) DEFAULT NULL COMMENT 'Percentatge o unitat de concentracio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producte_quimic`
--

CREATE TABLE `producte_quimic` (
  `id_producte` int(11) NOT NULL,
  `nom_comercial` varchar(255) NOT NULL,
  `tipus` enum('Fitosanitari','Fertilitzant','Herbicida') NOT NULL,
  `num_registre` varchar(50) DEFAULT NULL COMMENT 'Numero de registre oficial',
  `fabricant` varchar(100) DEFAULT NULL,
  `termini_seguretat_dies` int(11) DEFAULT NULL COMMENT 'Dies de seguretat abans de collita',
  `classificacio_tox` varchar(50) DEFAULT NULL COMMENT 'Toxicologica i ecotoxicologica',
  `compatibilitat_eco` tinyint(1) DEFAULT NULL COMMENT 'Compatible amb produccio ecologica',
  `dosi_max_ha` decimal(10,2) DEFAULT NULL COMMENT 'Dosi maxima legal per hectarea',
  `fitxa_seguretat_link` text DEFAULT NULL COMMENT 'Enllac al documentacio del producte'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `protocol_tratament`
--

CREATE TABLE `protocol_tratament` (
  `id_protocol` int(11) NOT NULL,
  `nom_protocol` varchar(100) NOT NULL,
  `descripcio` text DEFAULT NULL,
  `productes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Llista de productes i dosis en format JSON' CHECK (json_valid(`productes_json`)),
  `condicions_ambientals` text DEFAULT NULL COMMENT 'Condicions ideals per aplicar el protocol'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveidor`
--

CREATE TABLE `proveidor` (
  `id_proveidor` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adreca` text DEFAULT NULL,
  `tipus` enum('Fitosanitari','Fertilitzant','Semilla','Maquinaria','Altres') DEFAULT 'Altres'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sector`
--

CREATE TABLE `sector` (
  `id_sector` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL COMMENT 'Sector de cultiu (area amb una mateixa varietat i maneig)',
  `descripcio` text DEFAULT NULL,
  `coordenades_geo` geometry DEFAULT NULL COMMENT 'Geometria que delimita el perimetre real del sector agronomic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sector`
--

INSERT INTO `sector` (`id_sector`, `nom`, `descripcio`, `coordenades_geo`) VALUES
(1, 'Olivars del Sol', 'Olivars típics de la zona de Les Borges Blanques', 0x0000000001030000000100000006000000002cc65ccd74eb3f648e0a09e5c1444000bde5929278eb3fd0b75559d4c1444000882aafec5feb3f84c3eb8e9cc1444000211211195ceb3f84f7e50aaac1444000aadb24a274eb3f14827c8ae5c14440002cc65ccd74eb3f648e0a09e5c14440),
(2, 'Ametllers del Racó', 'Plantació d’ametllers de qualitat', 0x000000000103000000010000000600000000319bcc2375eb3ff862608de6c14440006cacd20579eb3ff0ffed05d5c14440008bc5e5de82eb3f84f2dcc9ecc1444000fe7a1f707feb3f285827c7fac1444000b385044f75eb3f5073ee0be6c1444000319bcc2375eb3ff862608de6c14440),
(3, 'Poma del Camp', 'Sector dedicat a pomeres', 0x000000000103000000010000000600000000e9e9566e60eb3f0034df609bc1444000cc2401066ceb3f4c34f583b6c1444000ac2c0efd71eb3f6423867d96c1444000f8cd6bad66eb3fdc6bef067cc144400014f1ee5f60eb3f68f02bb79bc1444000e9e9566e60eb3f0034df609bc14440),
(4, 'Perers de la Serra', 'Plantació de perers i fruiters variats', 0x000000000103000000010000000600000000cff9705c6ceb3f841b8e30b7c14440008e9a553583eb3f10803befeac144400091ff1a7188eb3f647351cccfc1444000ae017e5372eb3fcc43b9d697c1444000f900094e6ceb3fc454b45bb7c1444000cff9705c6ceb3f841b8e30b7c14440),
(5, 'Parcel·la Joan', NULL, 0x0000000001030000000100000005000000da71c3efa65beb3f79af5a99f0c14440857afa08fc61eb3faed9ca4bfec14440fa0ca837a366eb3f3d2cd49ae6c1444066f84f375060eb3f8be07f2bd9c14440da71c3efa65beb3f79af5a99f0c14440);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguiment_anual`
--

CREATE TABLE `seguiment_anual` (
  `id_plantacio` int(11) NOT NULL COMMENT 'Clau forana a la plantacio',
  `any` year(4) NOT NULL COMMENT 'Any de la campanya del seguiment',
  `estat_fenologic` varchar(100) DEFAULT NULL COMMENT 'Estat fenologic actual',
  `creixement_vegetatiu` varchar(100) DEFAULT NULL COMMENT 'Creixement vegetatiu',
  `rendiment_kg_ha` decimal(10,2) DEFAULT NULL COMMENT 'Rendiments obtinguts en cada campanya'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `treballador`
--

CREATE TABLE `treballador` (
  `id_treballador` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `cognoms` varchar(100) DEFAULT NULL,
  `rol` enum('Operari','Supervisor','Responsable','Tècnic','Altres') DEFAULT 'Operari',
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `data_alta` date DEFAULT NULL,
  `estat` enum('actiu','inactiu','vacances','baix') DEFAULT 'actiu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `varietat`
--

CREATE TABLE `varietat` (
  `id_varietat` int(11) NOT NULL,
  `id_especie` int(11) NOT NULL,
  `nom_varietat` varchar(100) NOT NULL,
  `caracteristiques_agronomiques` text DEFAULT NULL COMMENT 'Necessitats hidriques, hores de fred, resistencia a malalties',
  `cicle_vegetatiu` text DEFAULT NULL COMMENT 'Floracio, quallat, maduracio, recol·leccio',
  `requisits_pollinitzacio` text DEFAULT NULL COMMENT 'Requisits de pol·linitzacio i varietats compatibles',
  `productivitat_mitjana_esperada` decimal(10,2) DEFAULT NULL COMMENT 'Esperada per hectarea',
  `qualitats_comercials` text DEFAULT NULL COMMENT 'Qualitats organoleptiques i comercials del fruit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `aplicacio`
--
ALTER TABLE `aplicacio`
  ADD PRIMARY KEY (`id_aplicacio`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `caracteristiques_sol`
--
ALTER TABLE `caracteristiques_sol`
  ADD PRIMARY KEY (`id_parcela`,`data_analisis`);

--
-- Indices de la tabla `collita`
--
ALTER TABLE `collita`
  ADD PRIMARY KEY (`id_collita`),
  ADD KEY `id_plantacio` (`id_plantacio`);

--
-- Indices de la tabla `compra_producte`
--
ALTER TABLE `compra_producte`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_proveidor` (`id_proveidor`),
  ADD KEY `id_producte` (`id_producte`);

--
-- Indices de la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  ADD PRIMARY KEY (`id_control`),
  ADD KEY `id_lot` (`id_lot`);

--
-- Indices de la tabla `detall_aplicacio_producte`
--
ALTER TABLE `detall_aplicacio_producte`
  ADD PRIMARY KEY (`id_aplicacio`,`id_estoc`),
  ADD KEY `id_estoc` (`id_estoc`);

--
-- Indices de la tabla `especie`
--
ALTER TABLE `especie`
  ADD PRIMARY KEY (`id_especie`);

--
-- Indices de la tabla `fila_aplicacio`
--
ALTER TABLE `fila_aplicacio`
  ADD PRIMARY KEY (`id_fila_aplicada`,`id_aplicacio`),
  ADD KEY `id_aplicacio` (`id_aplicacio`),
  ADD SPATIAL KEY `idx_fila_aplicacio_geo` (`coordenada_final`);

--
-- Indices de la tabla `fila_arbre`
--
ALTER TABLE `fila_arbre`
  ADD PRIMARY KEY (`id_fila`),
  ADD UNIQUE KEY `id_sector` (`id_sector`,`numero`);

--
-- Indices de la tabla `incidencia`
--
ALTER TABLE `incidencia`
  ADD PRIMARY KEY (`id_incidencia`),
  ADD KEY `id_monitoratge` (`id_monitoratge`);

--
-- Indices de la tabla `incidencia_protocol`
--
ALTER TABLE `incidencia_protocol`
  ADD PRIMARY KEY (`id_incidencia`,`id_protocol`),
  ADD KEY `id_protocol` (`id_protocol`);

--
-- Indices de la tabla `infraestructura`
--
ALTER TABLE `infraestructura`
  ADD PRIMARY KEY (`id_infra`);

--
-- Indices de la tabla `inventari_estoc`
--
ALTER TABLE `inventari_estoc`
  ADD PRIMARY KEY (`id_estoc`),
  ADD KEY `id_producte` (`id_producte`);

--
-- Indices de la tabla `inversio`
--
ALTER TABLE `inversio`
  ADD PRIMARY KEY (`id_inversio`),
  ADD KEY `id_plantacio` (`id_plantacio`);

--
-- Indices de la tabla `log_accions`
--
ALTER TABLE `log_accions`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `lot_produccio`
--
ALTER TABLE `lot_produccio`
  ADD PRIMARY KEY (`id_lot`),
  ADD UNIQUE KEY `identificador` (`identificador`),
  ADD KEY `id_collita` (`id_collita`);

--
-- Indices de la tabla `maquinaria`
--
ALTER TABLE `maquinaria`
  ADD PRIMARY KEY (`id_maquinaria`);

--
-- Indices de la tabla `maquinaria_applicacio`
--
ALTER TABLE `maquinaria_applicacio`
  ADD PRIMARY KEY (`id_maquinaria`,`id_aplicacio`),
  ADD KEY `id_aplicacio` (`id_aplicacio`);

--
-- Indices de la tabla `materia_activa`
--
ALTER TABLE `materia_activa`
  ADD PRIMARY KEY (`id_materia_activa`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Indices de la tabla `monitoratge_plaga`
--
ALTER TABLE `monitoratge_plaga`
  ADD PRIMARY KEY (`id_monitoratge`),
  ADD KEY `id_sector` (`id_sector`);

--
-- Indices de la tabla `parcela`
--
ALTER TABLE `parcela`
  ADD PRIMARY KEY (`id_parcela`);

--
-- Indices de la tabla `parcela_infraestructura`
--
ALTER TABLE `parcela_infraestructura`
  ADD PRIMARY KEY (`id_parcela`,`id_infra`),
  ADD KEY `id_infra` (`id_infra`);

--
-- Indices de la tabla `parcela_sector`
--
ALTER TABLE `parcela_sector`
  ADD PRIMARY KEY (`id_parcela`,`id_sector`),
  ADD KEY `id_sector` (`id_sector`);

--
-- Indices de la tabla `plantacio`
--
ALTER TABLE `plantacio`
  ADD PRIMARY KEY (`id_plantacio`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `id_varietat` (`id_varietat`);

--
-- Indices de la tabla `producte_ma`
--
ALTER TABLE `producte_ma`
  ADD PRIMARY KEY (`id_producte`,`id_materia_activa`),
  ADD KEY `id_materia_activa` (`id_materia_activa`);

--
-- Indices de la tabla `producte_quimic`
--
ALTER TABLE `producte_quimic`
  ADD PRIMARY KEY (`id_producte`);

--
-- Indices de la tabla `protocol_tratament`
--
ALTER TABLE `protocol_tratament`
  ADD PRIMARY KEY (`id_protocol`);

--
-- Indices de la tabla `proveidor`
--
ALTER TABLE `proveidor`
  ADD PRIMARY KEY (`id_proveidor`);

--
-- Indices de la tabla `sector`
--
ALTER TABLE `sector`
  ADD PRIMARY KEY (`id_sector`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Indices de la tabla `seguiment_anual`
--
ALTER TABLE `seguiment_anual`
  ADD PRIMARY KEY (`id_plantacio`,`any`);

--
-- Indices de la tabla `treballador`
--
ALTER TABLE `treballador`
  ADD PRIMARY KEY (`id_treballador`);

--
-- Indices de la tabla `varietat`
--
ALTER TABLE `varietat`
  ADD PRIMARY KEY (`id_varietat`),
  ADD KEY `id_especie` (`id_especie`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `aplicacio`
--
ALTER TABLE `aplicacio`
  MODIFY `id_aplicacio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `collita`
--
ALTER TABLE `collita`
  MODIFY `id_collita` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compra_producte`
--
ALTER TABLE `compra_producte`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  MODIFY `id_control` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especie`
--
ALTER TABLE `especie`
  MODIFY `id_especie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fila_arbre`
--
ALTER TABLE `fila_arbre`
  MODIFY `id_fila` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `incidencia`
--
ALTER TABLE `incidencia`
  MODIFY `id_incidencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `infraestructura`
--
ALTER TABLE `infraestructura`
  MODIFY `id_infra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventari_estoc`
--
ALTER TABLE `inventari_estoc`
  MODIFY `id_estoc` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inversio`
--
ALTER TABLE `inversio`
  MODIFY `id_inversio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_accions`
--
ALTER TABLE `log_accions`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lot_produccio`
--
ALTER TABLE `lot_produccio`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `maquinaria`
--
ALTER TABLE `maquinaria`
  MODIFY `id_maquinaria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materia_activa`
--
ALTER TABLE `materia_activa`
  MODIFY `id_materia_activa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `monitoratge_plaga`
--
ALTER TABLE `monitoratge_plaga`
  MODIFY `id_monitoratge` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `parcela`
--
ALTER TABLE `parcela`
  MODIFY `id_parcela` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `plantacio`
--
ALTER TABLE `plantacio`
  MODIFY `id_plantacio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producte_quimic`
--
ALTER TABLE `producte_quimic`
  MODIFY `id_producte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `protocol_tratament`
--
ALTER TABLE `protocol_tratament`
  MODIFY `id_protocol` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveidor`
--
ALTER TABLE `proveidor`
  MODIFY `id_proveidor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sector`
--
ALTER TABLE `sector`
  MODIFY `id_sector` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `treballador`
--
ALTER TABLE `treballador`
  MODIFY `id_treballador` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `varietat`
--
ALTER TABLE `varietat`
  MODIFY `id_varietat` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `aplicacio`
--
ALTER TABLE `aplicacio`
  ADD CONSTRAINT `aplicacio_ibfk_1` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE CASCADE,
  ADD CONSTRAINT `aplicacio_ibfk_2` FOREIGN KEY (`id_treballador`) REFERENCES `treballador` (`id_treballador`) ON DELETE SET NULL;

--
-- Filtros para la tabla `caracteristiques_sol`
--
ALTER TABLE `caracteristiques_sol`
  ADD CONSTRAINT `caracteristiques_sol_ibfk_1` FOREIGN KEY (`id_parcela`) REFERENCES `parcela` (`id_parcela`) ON DELETE CASCADE;

--
-- Filtros para la tabla `collita`
--
ALTER TABLE `collita`
  ADD CONSTRAINT `collita_ibfk_1` FOREIGN KEY (`id_plantacio`) REFERENCES `plantacio` (`id_plantacio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `compra_producte`
--
ALTER TABLE `compra_producte`
  ADD CONSTRAINT `compra_producte_ibfk_1` FOREIGN KEY (`id_proveidor`) REFERENCES `proveidor` (`id_proveidor`),
  ADD CONSTRAINT `compra_producte_ibfk_2` FOREIGN KEY (`id_producte`) REFERENCES `producte_quimic` (`id_producte`);

--
-- Filtros para la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  ADD CONSTRAINT `control_qualitat_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `lot_produccio` (`id_lot`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detall_aplicacio_producte`
--
ALTER TABLE `detall_aplicacio_producte`
  ADD CONSTRAINT `detall_aplicacio_producte_ibfk_1` FOREIGN KEY (`id_aplicacio`) REFERENCES `aplicacio` (`id_aplicacio`) ON DELETE CASCADE,
  ADD CONSTRAINT `detall_aplicacio_producte_ibfk_2` FOREIGN KEY (`id_estoc`) REFERENCES `inventari_estoc` (`id_estoc`);

--
-- Filtros para la tabla `fila_aplicacio`
--
ALTER TABLE `fila_aplicacio`
  ADD CONSTRAINT `fila_aplicacio_ibfk_1` FOREIGN KEY (`id_fila_aplicada`) REFERENCES `fila_arbre` (`id_fila`) ON DELETE CASCADE,
  ADD CONSTRAINT `fila_aplicacio_ibfk_2` FOREIGN KEY (`id_aplicacio`) REFERENCES `aplicacio` (`id_aplicacio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fila_arbre`
--
ALTER TABLE `fila_arbre`
  ADD CONSTRAINT `fila_arbre_ibfk_1` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencia`
--
ALTER TABLE `incidencia`
  ADD CONSTRAINT `incidencia_ibfk_1` FOREIGN KEY (`id_monitoratge`) REFERENCES `monitoratge_plaga` (`id_monitoratge`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencia_protocol`
--
ALTER TABLE `incidencia_protocol`
  ADD CONSTRAINT `incidencia_protocol_ibfk_1` FOREIGN KEY (`id_incidencia`) REFERENCES `incidencia` (`id_incidencia`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidencia_protocol_ibfk_2` FOREIGN KEY (`id_protocol`) REFERENCES `protocol_tratament` (`id_protocol`);

--
-- Filtros para la tabla `inventari_estoc`
--
ALTER TABLE `inventari_estoc`
  ADD CONSTRAINT `inventari_estoc_ibfk_1` FOREIGN KEY (`id_producte`) REFERENCES `producte_quimic` (`id_producte`);

--
-- Filtros para la tabla `inversio`
--
ALTER TABLE `inversio`
  ADD CONSTRAINT `inversio_ibfk_1` FOREIGN KEY (`id_plantacio`) REFERENCES `plantacio` (`id_plantacio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `log_accions`
--
ALTER TABLE `log_accions`
  ADD CONSTRAINT `log_accions_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballador` (`id_treballador`);

--
-- Filtros para la tabla `lot_produccio`
--
ALTER TABLE `lot_produccio`
  ADD CONSTRAINT `lot_produccio_ibfk_1` FOREIGN KEY (`id_collita`) REFERENCES `collita` (`id_collita`) ON DELETE CASCADE;

--
-- Filtros para la tabla `maquinaria_applicacio`
--
ALTER TABLE `maquinaria_applicacio`
  ADD CONSTRAINT `maquinaria_applicacio_ibfk_1` FOREIGN KEY (`id_maquinaria`) REFERENCES `maquinaria` (`id_maquinaria`),
  ADD CONSTRAINT `maquinaria_applicacio_ibfk_2` FOREIGN KEY (`id_aplicacio`) REFERENCES `aplicacio` (`id_aplicacio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `monitoratge_plaga`
--
ALTER TABLE `monitoratge_plaga`
  ADD CONSTRAINT `monitoratge_plaga_ibfk_1` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE CASCADE;

--
-- Filtros para la tabla `parcela_infraestructura`
--
ALTER TABLE `parcela_infraestructura`
  ADD CONSTRAINT `parcela_infraestructura_ibfk_1` FOREIGN KEY (`id_parcela`) REFERENCES `parcela` (`id_parcela`) ON DELETE CASCADE,
  ADD CONSTRAINT `parcela_infraestructura_ibfk_2` FOREIGN KEY (`id_infra`) REFERENCES `infraestructura` (`id_infra`) ON DELETE CASCADE;

--
-- Filtros para la tabla `parcela_sector`
--
ALTER TABLE `parcela_sector`
  ADD CONSTRAINT `parcela_sector_ibfk_1` FOREIGN KEY (`id_parcela`) REFERENCES `parcela` (`id_parcela`) ON DELETE CASCADE,
  ADD CONSTRAINT `parcela_sector_ibfk_2` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE CASCADE;

--
-- Filtros para la tabla `plantacio`
--
ALTER TABLE `plantacio`
  ADD CONSTRAINT `plantacio_ibfk_1` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`) ON DELETE CASCADE,
  ADD CONSTRAINT `plantacio_ibfk_2` FOREIGN KEY (`id_varietat`) REFERENCES `varietat` (`id_varietat`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producte_ma`
--
ALTER TABLE `producte_ma`
  ADD CONSTRAINT `producte_ma_ibfk_1` FOREIGN KEY (`id_producte`) REFERENCES `producte_quimic` (`id_producte`) ON DELETE CASCADE,
  ADD CONSTRAINT `producte_ma_ibfk_2` FOREIGN KEY (`id_materia_activa`) REFERENCES `materia_activa` (`id_materia_activa`) ON DELETE CASCADE;

--
-- Filtros para la tabla `seguiment_anual`
--
ALTER TABLE `seguiment_anual`
  ADD CONSTRAINT `seguiment_anual_ibfk_1` FOREIGN KEY (`id_plantacio`) REFERENCES `plantacio` (`id_plantacio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `varietat`
--
ALTER TABLE `varietat`
  ADD CONSTRAINT `varietat_ibfk_1` FOREIGN KEY (`id_especie`) REFERENCES `especie` (`id_especie`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
