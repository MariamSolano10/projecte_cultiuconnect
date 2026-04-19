SET NAMES utf8mb4;
-- ==============================================================================
-- DESCRIPCIÓ: Dades de prova (Seed) per a CultiuConnect
-- Versió: 3 — charset utf8mb4 forçat, caràcters catalans corregits
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- --------------------------------------------------------
-- MÒDUL 1: TERRENY, CULTIUS I PLANTACIONS
-- --------------------------------------------------------

INSERT INTO sector (nom, descripcio, coordenades_geo) VALUES
('Olivars del Sol',    'Olivars típics de la zona de Les Borges Blanques', ST_GeomFromText('POLYGON((0.8580080806726755 41.51480210318985, 0.8584683293428554 41.51429287610483, 0.8554595395291926 41.51259027969266, 0.8549924214758278 41.51300178746081, 0.8579874725231207 41.51481753425119, 0.8580080806726755 41.51480210318985))')),
('Ametllers del Racó', 'Plantació d''ametllers de qualitat',               ST_GeomFromText('POLYGON((0.8580492969718136 41.51484839636208, 0.8585232844083066 41.514313451014345, 0.8597254264571745 41.515038712391316, 0.8593063940861043 41.515465635501016, 0.8580699051213685 41.514832965308074, 0.8580492969718136 41.51484839636208))')),
('Poma del Camp',      'Sector dedicat a pomeres',                         ST_GeomFromText('POLYGON((0.8555213639768056 41.512554272638226, 0.8569364569033269 41.513382429817824, 0.8576646115155881 41.51240510034293, 0.85628386550502 41.51159750643248, 0.855514494593649 41.512564560370095, 0.8555213639768056 41.512554272638226))')),
('Perers de la Serra', 'Plantació de perers i fruiters variats',           ST_GeomFromText('POLYGON((0.8569776732024081 41.51340300501673, 0.8597666427562558 41.51498213200841, 0.8604054953871412 41.5141539952954, 0.857705827814641 41.5124462513555, 0.8569708038192232 41.513408148815444, 0.8569776732024081 41.51340300501673))'));

INSERT INTO caracteristiques_sol (id_sector, data_analisi, textura, pH, materia_organica, N, P, K, Ca, Mg, Na, conductivitat_electrica) VALUES
(4, '2026-01-08', 'Argilós',    6.50, 3.00, 13.00, 13.00, 115.00, 1800.00, 320.00, 45.00, 0.285),
(3, '2026-01-07', 'Sandy Loam', 6.20, 3.50, 11.00, 14.50, 125.00, 2100.00, 280.00, 38.00, 0.240),
(2, '2026-01-06', 'Llimós',     7.10, 2.90, 14.00, 12.00, 110.00, 1950.00, 310.00, 52.00, 0.310),
(1, '2026-01-05', 'Argilós',    6.80, 3.20, 12.50, 15.00, 120.00, 2200.00, 340.00, 41.00, 0.295);

INSERT INTO parcela (nom, coordenades_geo, superficie_ha, pendent, orientacio) VALUES
-- Parcela 1: Rectangle (rotat diagonalment) per la Finca Nord (Sectors 1 i 2)
('Polígon 4 Parcel·la 12 - Finca Nord', ST_GeomFromText('POLYGON((0.8549924214758278 41.51300178746081, 0.8593063940861043 41.515465635501016, 0.8597254264571745 41.515038712391316, 0.8554595395291926 41.51259027969266, 0.8549924214758278 41.51300178746081))'), 2.60, 'Pla',  'Sud'),
-- Parcela 2: Rectangle (rotat diagonalment) per la Finca Sud (Sectors 3 i 4)
('Polígon 4 Parcel·la 13 - Finca Sud',  ST_GeomFromText('POLYGON((0.855514494593649 41.512564560370095, 0.8597666427562558 41.51498213200841, 0.8604054953871412 41.5141539952954, 0.85628386550502 41.51159750643248, 0.855514494593649 41.512564560370095))'), 2.60, 'Suau', 'Sud-Est');

INSERT INTO parcela_sector (id_parcela, id_sector, superficie_m2) VALUES
(1, 1, 15000.00),
(1, 2, 25000.00),
(2, 3, 20000.00),
(2, 4, 18000.00);

INSERT INTO especie (nom_comu, nom_cientific) VALUES
('Olivera',  'Olea europaea'),
('Ametller', 'Prunus dulcis'),
('Pomera',   'Malus domestica'),
('Perera',   'Pyrus communis');

INSERT INTO varietat (id_especie, nom_varietat, cicle_vegetatiu, productivitat_mitjana_esperada) VALUES
(1, 'Arbequina',        'Floració maig, recol·lecció novembre',    6000.00),
(2, 'Guara',            'Floració tardana, recol·lecció setembre', 2500.00),
(3, 'Golden Delicious', 'Floració abril, recol·lecció setembre',  45000.00),
(4, 'Conference',       'Floració març, recol·lecció agost',      35000.00);

-- Taula: foto_varietat  â†  NOVA
INSERT INTO foto_varietat (id_varietat, tipus, url_foto, descripcio) VALUES
(1, 'arbre', 'assets/fotos/varietats/arbequina_arbre.png',  'Olivera Arbequina adulta en plena producció'),
(1, 'fruit', 'assets/fotos/varietats/arbequina_fruit.png',  'Olives Arbequina en fase de maduració'),
(2, 'arbre', 'assets/fotos/varietats/guara_arbre.png',      'Ametller Guara en floració primaverenca'),
(2, 'flor',  'assets/fotos/varietats/guara_flor.png',       'Detall de la flor blanca de l''ametller Guara'),
(2, 'fruit', 'assets/fotos/varietats/guara_fruit.png',      'Ametlles Guara en fase de recol·lecció'),
(3, 'arbre', 'assets/fotos/varietats/golden_arbre.png',     'Pomera Golden Delicious amb fruit madur'),
(3, 'fruit', 'assets/fotos/varietats/golden_fruit.png',     'Pomes Golden Delicious de calibre extra'),
(4, 'fruit', 'assets/fotos/varietats/conference_fruit.png', 'Peres Conference en el moment òptim de collita');

INSERT INTO plantacio (id_sector, id_varietat, data_plantacio, marc_fila, marc_arbre, num_arbres_plantats, sistema_formacio) VALUES
(1, 1, '2015-03-15', 5.00, 4.00,  750, 'Vas lliure'),
(2, 2, '2018-11-20', 6.00, 5.00,  830, 'Vas'),
(3, 3, '2010-02-10', 4.00, 1.50, 3300, 'Eix central'),
(4, 4, '2012-03-05', 4.00, 2.00, 2250, 'Palmeta');

-- Seguiment anual (dades d'anys anteriors — tots 4 sectors)
INSERT INTO seguiment_anual (id_plantacio, `any`, estat_fenologic, rendiment_kg_ha, incidencies, condicions_climatiques, estimacio_collita_kg) VALUES
(1, 2024, 'Repòs hivernal',  5800.00,  'Mosca de l''oliva detectada al 8%',  'Hivern suau, primavera plujosa',  4350.00),
(1, 2025, 'Post-collita',    6200.00,  'Cap incidència rellevant',           'Any normal',                      4650.00),
(2, 2024, 'Repòs hivernal',  2200.00,  'Gelada tardana — pèrdua de flors 15%', 'Primavera freda i seca',       1826.00),
(2, 2025, 'Post-collita',    2600.00,  'Cap incidència rellevant',           'Bona floració',                   2158.00),
(3, 2024, 'Repòs hivernal',  42000.00, 'Mínim afectació de talpons',         'Tardor seca, hivern moderat',   138000.00),
(3, 2025, 'Post-collita',    38500.00, 'Cop de sol en 5% de producció',      'Estiu molt calorós',            115000.00),
(4, 2024, 'Repòs hivernal',  32000.00, 'Cap incidència rellevant',           'Any plujós, bona producció',     72000.00),
(4, 2025, 'Post-collita',    30000.00, 'Pugó verd als brots — controlat',   'Primavera humida',               67500.00);

INSERT INTO infraestructura (nom, tipus, coordenades_geo) VALUES
('Bassa de Reg Principal',    'reg',        ST_GeomFromText('POINT(0.8575 41.5135)')),
('Magatzem Fitosanitaris',    'edificacio', ST_GeomFromText('POINT(0.8560 41.5125)')),
('Camí principal finca',      'camin',      ST_GeomFromText('LINESTRING(0.85552 41.51257, 0.85627 41.51163)')),
('Tanca perimetral sud',      'tanca',      ST_GeomFromText('LINESTRING(0.85630 41.51165, 0.85762 41.51243)'));

-- Taula: parcela_infraestructura
INSERT INTO parcela_infraestructura (id_parcela, id_infra) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 1),
(2, 2),
(2, 3),
(2, 4);

-- Taula: foto_parcela  â†  NOVA
INSERT INTO foto_parcela (id_parcela, url_foto, data_foto, descripcio) VALUES
(1, 'assets/fotos/parceles/parcela1_ametllers.png',  '2026-03-01', 'Vista dels ametllers de la parcela 1'),
(2, 'assets/fotos/parceles/parcela2_oliveres.png',  '2026-03-02', 'Vista de les oliveres de la parcela 2'),
(3, 'assets/fotos/parceles/parcela3_pomeres.png',  '2026-03-03', 'Vista dels pomeres de la parcela 3'),
(4, 'assets/fotos/parceles/parcela4_perers.png',  '2026-03-04', 'Vista dels perers de la parcela 4');

-- --------------------------------------------------------
-- MÒDUL 2: PRODUCTES, PROVEÏDORS I SENSORS
-- --------------------------------------------------------

INSERT INTO proveidor (nom, telefon, email, tipus) VALUES
('AgroLleida Subministraments', '973123456', 'info@agrolleida.cat',     'Fitosanitari'),
('Vivers del Segrià',           '973987654', 'vendes@viverssegria.com', 'Llavor');

-- Taula: compra_producte
-- Nota: només existeixen 2 proveïdors (id=1,2) i 3 productes químics (id=1,2,3)
INSERT INTO compra_producte (id_proveidor, id_producte, data_compra, quantitat, preu_unitari, num_lot) VALUES
(1, 1, '2025-02-15',  50.00, 12.50, 'L2025-A1'),
(1, 2, '2024-09-01', 120.00,  8.75, 'L2024-C3'),
(2, 3, '2025-01-10',  80.00,  4.20, 'L2025-N1');

INSERT INTO materia_activa (nom, espectre_accio) VALUES
('Glifosat',          'Males herbes de fulla ampla i estreta'),
('Coure (Oxiclorur)', 'Fongs, repilo, bacteriosi');

-- tipus ENUM correcte: 'Fitosanitari','Fertilitzant','Herbicida'
INSERT INTO producte_quimic (nom_comercial, tipus, fabricant, termini_seguretat_dies, dosi_max_ha, estoc_actual, estoc_minim, unitat_mesura) VALUES
('HerbiMax 36',   'Herbicida',    'AgroQuim',   15, 3.00,  50.00, 10.00, 'L'),
('CobrePlus 50',  'Fitosanitari', 'FitoIberia', 21, 2.50, 120.00, 20.00, 'Kg'),
('FertiPlus NPK', 'Fertilitzant', 'NutriAgro',   0, NULL,  80.00, 15.00, 'Kg');

-- Taula: producte_ma
-- Nota: només existeixen 3 productes (id=1,2,3) i 2 matèries actives (id=1,2)
INSERT INTO producte_ma (id_producte, id_materia_activa, concentracio) VALUES
(1, 1, 36.00),
(2, 2, 50.00);

INSERT INTO inventari_estoc (id_producte, num_lot, quantitat_disponible, unitat_mesura, data_caducitat, ubicacio_magatzem, data_compra, preu_adquisicio) VALUES
(1, 'L2025-A1',  50.00, 'L',  '2028-12-31', 'Estanteria A - Baix',  '2025-02-15', 12.50),
(2, 'L2024-C3', 120.00, 'Kg', '2027-06-30', 'Estanteria B - Fongs', '2024-09-01',  8.75),
(3, 'L2025-N1',  80.00, 'Kg', '2027-03-31', 'Estanteria C - Adobs', '2025-01-10',  4.20);

-- Alguns moviments d'estoc de prova
INSERT INTO moviment_estoc (id_producte, tipus_moviment, quantitat, data_moviment, motiu) VALUES
(1, 'Entrada',  50.00, '2025-02-15', 'Compra albarà A-2025-0123'),
(2, 'Entrada', 120.00, '2024-09-01', 'Compra albarà B-2024-0887'),
(3, 'Entrada',  80.00, '2025-01-10', 'Compra albarà N-2025-0045'),
(2, 'Sortida',  10.00, '2026-03-10', 'Tractament fungicida oliveres sector 1');

-- Sensors IoT — distribuïts entre sectors
INSERT INTO sensor (id_sector, tipus, protocol_comunicacio, profunditat_cm, coordenades_geo) VALUES
(1, 'humitat_sol',         'LoRaWAN', 30, ST_GeomFromText('POINT(0.8565 41.5140)')),
(1, 'temperatura_ambient', 'LoRaWAN',  0, ST_GeomFromText('POINT(0.8565 41.5140)')),
(2, 'humitat_sol',         'LoRaWAN', 25, ST_GeomFromText('POINT(0.8588 41.5148)')),
(3, 'humitat_sol',         'LoRaWAN', 30, ST_GeomFromText('POINT(0.8565 41.5128)')),
(3, 'temperatura_ambient', 'LoRaWAN',  0, ST_GeomFromText('POINT(0.8565 41.5128)')),
(4, 'humitat_sol',         'LoRaWAN', 30, ST_GeomFromText('POINT(0.8585 41.5140)'));

INSERT INTO lectura_sensor (id_sensor, data_hora, valor, unitat) VALUES
-- Sensor 1: humitat sòl sector 1 (olivars)
(1, '2026-03-09 08:00:00', 28.5, '%'),
(1, '2026-03-09 12:00:00', 24.2, '%'),
(1, '2026-03-09 18:00:00', 22.0, '%'),
-- Sensor 2: temperatura sector 1
(2, '2026-03-09 08:00:00',  8.5, 'ÂºC'),
(2, '2026-03-09 12:00:00', 15.2, 'ÂºC'),
(2, '2026-03-09 18:00:00', 12.8, 'ÂºC'),
-- Sensor 3: humitat sòl sector 2 (ametllers)
(3, '2026-03-09 08:00:00', 18.0, '%'),
(3, '2026-03-09 12:00:00', 14.5, '%'),
-- Sensor 4: humitat sòl sector 3 (pomeres)
(4, '2026-03-09 08:00:00', 32.5, '%'),
(4, '2026-03-09 12:00:00', 28.2, '%'),
(4, '2026-03-09 18:00:00', 26.0, '%'),
-- Sensor 5: temperatura sector 3
(5, '2026-03-09 08:00:00',  9.5, 'ÂºC'),
(5, '2026-03-09 12:00:00', 16.8, 'ÂºC'),
-- Sensor 6: humitat sòl sector 4 (perers)
(6, '2026-03-09 08:00:00', 25.0, '%'),
(6, '2026-03-09 12:00:00', 20.8, '%');

-- Protocols i tractaments programats (necessaris per als KPIs del panell)
INSERT INTO protocol_tractament (nom_protocol, descripcio, condicions_ambientals) VALUES
('Tractament coure post-poda',  'Aplicació oxiclorur de coure per prevenir malalties fúngiques', 'Temperatura > 8ÂºC, sense vent fort'),
('Herbicida pre-emergència',     'Aplicació de glifosat als entresurcs',                          'Dia assolellat, sense pluja en 24h'),
('Fertilització primavera NPK',  'Aportació de nutrients en floració',                            'Temperatura > 10ÂºC');

-- Taula: incidencia_protocol
-- Nota: només existeixen 3 protocols (id=1,2,3) i 2 incidències (id=1,2)
INSERT INTO incidencia_protocol (id_incidencia, id_protocol) VALUES
(1, 1),
(2, 3);

INSERT INTO tractament_programat (id_sector, id_protocol, data_prevista, tipus, motiu, estat, dies_avis) VALUES
(1, 1, '2026-04-05', 'preventiu',     'Protecció post-poda oliveres',             'pendent', 3),
(3, 3, '2026-04-10', 'fertilitzacio', 'Fertilització inici de temporada pomeres', 'pendent', 5),
(2, 2, '2026-04-15', 'correctiu',     'Control males herbes ametllers',            'pendent', 3),
(4, 1, '2026-04-20', 'preventiu',     'Tractament fúngic perers',                 'pendent', 3);

-- Monitoratge de plagues (amb coordenades_geo per al mapa)
INSERT INTO monitoratge_plaga (id_sector, data_observacio, tipus_problema, descripcio_breu, nivell_poblacio, llindar_intervencio_assolit, coordenades_geo) VALUES
(3, '2026-03-08 09:00:00', 'Plaga',       'Possible presència de corc de la poma (Cydia pomonella)', 2.5, FALSE, ST_GeomFromText('POINT(0.8566 41.5130)')),
(1, '2026-03-09 10:30:00', 'Malaltia',    'Símptomes de repilo (Spilocaea oleagina) en fulles',      3.8, TRUE,  ST_GeomFromText('POINT(0.8568 41.5142)')),
(4, '2026-03-10 08:15:00', 'Plaga',       'Detecció de pugó verd a brots joves',                     5.1, TRUE,  ST_GeomFromText('POINT(0.8590 41.5145)')),
(2, '2026-03-05 11:00:00', 'Deficiencia', 'Clorosi fèrrica a ametllers nous',                        1.2, FALSE, ST_GeomFromText('POINT(0.8587 41.5148)'));

INSERT INTO incidencia (id_monitoratge, data_registre, tipus, descripcio, gravetat, estat) VALUES
(2, '2026-03-09 11:00:00', 'Malaltia', 'Repilo detectat al sector 1 — cal tractament coure imminent',  'Alta',   'pendent'),
(3, '2026-03-10 09:00:00', 'Plaga',    'Pugó verd a perers — vigilar evolució i tractar si s''estén',  'Mitjana', 'pendent');

-- --------------------------------------------------------
-- MÒDUL 3: PERSONAL I TASQUES
-- --------------------------------------------------------

-- Taula: treballador
INSERT INTO treballador (nom, cognoms, dni, data_naixement, nacionalitat, adreca, telefon, email, contacte_emergencia_nom, contacte_emergencia_telefon, rol, tipus_contracte, data_alta, estat) VALUES
('Marc',  'Vila Pons',    '47123456A', '1985-06-15', 'Espanyola', 'C/ Lleida 12, Les Borges Blanques', '620123456', 'marc.vila@cultiuconnect.cat',   'Rosa Pons',   '620111222', 'Responsable', 'Indefinit', '2018-01-10', 'actiu'),
('Anna',  'Serra Martí',  '48987654B', '1992-11-03', 'Espanyola', 'Av. Catalunya 5, Tàrrega',          '621987654', 'anna.serra@cultiuconnect.cat',   'Josep Serra', '621333444', 'Tecnic',      'Indefinit', '2021-05-15', 'actiu'),
('Jordi', 'García López', '39555666C', '1998-02-22', 'Espanyola', 'C/ Major 22, Mollerussa',           '622555666', 'jordi.garcia@cultiuconnect.cat', 'Maria López', '622777888', 'Operari',     'Temporal',  '2025-02-01', 'actiu');
 
-- Taula: usuari
-- Contrasenyes encriptades amb bcrypt (password_hash):
-- admin.dev -> admin1234
-- marc.vila -> responsable1234
-- anna.serra -> operari1234
-- jordi.garcia -> operari1234

INSERT INTO usuari (nom_usuari, contrasenya, nom_complet, rol, estat, id_treballador) VALUES
('admin.dev',    '$2y$10$Ivvf/I43Iw3uftQ8SdkRPeY.3HyhgXVohM.UPxQlpCEFTAUAFYJ2e',       'Administrador (Dev)', 'admin',       'actiu', NULL),
('marc.vila',    '$2y$10$nOG5TH9bhCk7F51XPuhlEuJ6v1EMbj9isPQ/LD0v1jWSY2EJgAYuq', 'Marc Vila Pons',      'responsable', 'actiu', 1),
('anna.serra',   '$2y$10$mRSxqDRveg0Cn4fvFTbxUuOGZ5EvojTYfl4I.AmbRlvntnxf0Tn6a',     'Anna Serra Martí',    'operari',     'actiu', 2),
('jordi.garcia', '$2y$10$mRSxqDRveg0Cn4fvFTbxUuOGZ5EvojTYfl4I.AmbRlvntnxf0Tn6a',     'Jordi García López',  'operari',     'actiu', 3);

-- Certificacions de treballadors (necessari per als KPIs del panell)
INSERT INTO certificacio_treballador (id_treballador, tipus_certificacio, entitat_emissora, data_obtencio, data_caducitat, ambit_aplicacio) VALUES
(1, 'Carnet aplicador fitosanitaris nivell bàsic',      'DARP Generalitat', '2020-03-15', '2026-03-15', 'Aplicació productes fitosanitaris d''ús professional'),
(2, 'Carnet aplicador fitosanitaris nivell qualificat',  'DARP Generalitat', '2022-06-01', '2026-12-01', 'Aplicació i assessorament fitosanitari'),
(1, 'Carnet de conduir B',                              'DGT',              '2005-07-20', NULL,         'Vehicles de fins a 3.500 kg'),
(3, 'Carnet de conduir B',                              'DGT',              '2018-04-10', NULL,         'Vehicles de fins a 3.500 kg');

-- Permisos i absències
INSERT INTO permis_absencia (id_treballador, tipus, data_inici, data_fi, motiu, aprovat) VALUES
(3, 'vacances',       '2026-08-01', '2026-08-15', 'Vacances estivals', TRUE),
(2, 'permis',         '2026-04-18', '2026-04-18', 'Gestió personal',   TRUE),
(1, 'baixa_malaltia', '2026-02-10', '2026-02-14', 'Grip estacional',   TRUE);

INSERT INTO tasca (id_sector, tipus, descripcio, data_inici_prevista, duracio_estimada_h, num_treballadors_necessaris, estat) VALUES
(3, 'poda',          'Poda d''hivern i esporgada de les pomeres',         '2026-03-01', 40.00, 2, 'en_proces'),
(1, 'tractament',    'Tractament amb coure post-poda oliveres',           '2026-03-15',  8.00, 1, 'pendent'),
(4, 'fertilitzacio', 'Fertilització NPK d''inici de temporada perers',    '2026-04-10',  6.00, 1, 'pendent');

INSERT INTO assignacio_tasca (id_tasca, id_treballador, estat) VALUES
(1, 3, 'en_proces'),
(2, 2, 'assignat'),
(3, 2, 'assignat');

INSERT INTO jornada (id_treballador, id_tasca, data_hora_inici, data_hora_fi, pausa_minuts, ubicacio, incidencies, validada) VALUES
-- Jornades del març (poda i tractaments)
(3, 1, '2026-03-08 07:00:00', '2026-03-08 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE),
(3, 1, '2026-03-09 07:00:00', '2026-03-09 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE),
(3, 1, '2026-03-10 07:00:00', '2026-03-10 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE),
(3, 1, '2026-03-11 07:00:00', '2026-03-11 14:30:00', 30, 'Sector 3 - Poma del Camp',   'Pluja a partir de les 14h',  TRUE),
(3, 1, '2026-03-12 07:00:00', '2026-03-12 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE),
(2, 2, '2026-03-10 08:00:00', '2026-03-10 16:00:00', 30, 'Sector 1 - Olivars del Sol', 'Tractament fungicida aplicat', TRUE),
(1, 1, '2026-03-10 07:00:00', '2026-03-10 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Supervisió poda',            TRUE),
(1, 1, '2026-03-11 07:00:00', '2026-03-11 14:30:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE),
(2, 1, '2026-03-11 07:30:00', '2026-03-11 14:30:00', 30, 'Sector 3 - Poma del Camp',   'Suport poda pomeres',        TRUE),
(2, 1, '2026-03-12 07:30:00', '2026-03-12 15:00:00', 30, 'Sector 3 - Poma del Camp',   'Cap incidència',             TRUE);

INSERT INTO planificacio_personal (temporada, periode, data_inici, data_fi, num_treballadors_necessaris, perfil_necessari, observacions) VALUES
(2026, 'Collita Poma', '2026-08-20', '2026-09-30', 10, 'Temporers amb experiència en recollida', 'Necessitem allotjament preparat per al personal temporal');

-- --------------------------------------------------------
-- MÒDUL 4: APLICACIONS AL CAMP I MAQUINÀRIA
-- --------------------------------------------------------

INSERT INTO maquinaria (nom_maquina, tipus, any_fabricacio, cabal_l_min, velocitat_aplicacio_km_h, capacitat_diposit_l, manteniment_json) VALUES
('Tractor John Deere 5090',   'Tractor',       2018, NULL,  5.50, NULL,    '{"darrera_revisio": "2025-12-15", "tipus": "Canvi d''oli i filtres", "cost": 350}'),
('Pulveritzador Gaysa 2000L', 'Pulveritzador', 2020, 80.00, 6.00, 2000.00, '{"darrera_revisio": "2026-02-10", "tipus": "Calibració de broquets", "iteaf": "Superada"}');

INSERT INTO aplicacio (id_sector, id_treballador, id_monitoratge, data_event, hora_inici_planificada, tipus_event, metode_aplicacio, volum_caldo, estat_fenologic, num_carnet_aplicador, condicions_ambientals) VALUES
(1, 2, 2, '2026-03-10', '2026-03-10 08:30:00', 'Tractament fungicida', 'foliar', 1500.00, 'Repòs hivernal / Moviment de saba', '48987654B-QF2022', 'Temp: 12ÂºC, Vent: 5 km/h N'),
(2, 2, NULL, '2026-04-15', '2026-04-15 09:00:00', 'Herbicida', 'sol', 800.00, 'Brotació', '48987654B-QF2022', 'Temp: 18ÂºC, Vent: 3 km/h S, cel clar');

INSERT INTO detall_aplicacio_producte (id_aplicacio, id_estoc, dosi_aplicada, quantitat_consumida_total) VALUES
(1, 2, 2.50, 10.00),
(2, 1, 2.00, 8.00);

INSERT INTO maquinaria_aplicacio (id_maquinaria, id_aplicacio, hores_utilitzades) VALUES
(1, 1, 4.50),
(2, 1, 4.50),
(1, 2, 3.00),
(2, 2, 3.00);

-- Files tractades en aplicacions
INSERT INTO fila_aplicacio (id_fila_aplicada, id_aplicacio, id_treballador, data_inici, data_fi, hores_treballades, percentatge_complet, longitud_tractada_m, coordenada_final, estat) VALUES
-- Aplicació 1: Tractament fungicida oliveres (files 1-3 del sector 1)
(1, 1, 2, '2026-03-10 08:45:00', '2026-03-10 10:15:00', 1.50, 100.00, 150.00, ST_GeomFromText('POINT(0.85800 41.51480)'), 'completada'),
(2, 1, 2, '2026-03-10 10:15:00', '2026-03-10 11:45:00', 1.50, 100.00, 148.00, ST_GeomFromText('POINT(0.85820 41.51460)'), 'completada'),
(3, 1, 2, '2026-03-10 12:00:00', NULL,                   0.50,  50.00,  75.00, ST_GeomFromText('POINT(0.85840 41.51440)'), 'aturada'),
-- Aplicació 2: Herbicida ametllers (files 4-6 del sector 2)
(4, 2, 2, '2026-04-15 09:15:00', '2026-04-15 10:30:00', 1.25, 100.00, 120.00, ST_GeomFromText('POINT(0.85930 41.51500)'), 'completada'),
(5, 2, 2, '2026-04-15 10:30:00', '2026-04-15 12:00:00', 1.50, 100.00, 120.00, ST_GeomFromText('POINT(0.85940 41.51480)'), 'completada'),
(6, 2, 2, '2026-04-15 13:00:00', NULL,                   0.75,  40.00,  48.00, ST_GeomFromText('POINT(0.85950 41.51460)'), 'en_proces');

-- --------------------------------------------------------
-- MÒDUL 5: COLLITA, QUALITAT I ECONOMIA
-- --------------------------------------------------------

INSERT INTO inversio (id_plantacio, data_inversio, concepte, `import`, vida_util_anys, categoria, observacions) VALUES
(3, '2010-02-10', 'Instal·lació de reg per degoteig i xarxa antipedra', 45000.00, 15, 'Infraestructura', 'Factura núm. 2010-INF-0042'),
(1, '2015-03-15', 'Plantació oliveres Arbequina (750 arbres + tutor)',   18000.00, 30, 'Maquinaria',      'Factura Vivers del Segrià 2015-V-001'),
(3, '2024-02-01', 'Tractament preventiu arbres per a campanya 2024',      3200.00,  1, 'Fitosanitaris',   'Albarà AgroLleida A-2024-0112');

INSERT INTO previsio_collita (id_plantacio, temporada, data_previsio, produccio_estimada_kg, produccio_per_arbre_kg, data_inici_collita_estimada, data_fi_collita_estimada, qualitat_prevista, mo_necessaria_jornal, factors_considerats) VALUES
(1, 2026, '2026-02-20',   4800.00,  6.40, '2026-11-01', '2026-11-20', 'Extra',   40,  'Bon estat vegetatiu, floració abundant, sense gelades'),
(2, 2026, '2026-02-25',   2400.00,  2.89, '2026-09-01', '2026-09-20', 'Primera', 30,  'Floració tardana correcta, no es preveuen gelades'),
(3, 2026, '2026-03-01', 120000.00, 36.36, '2026-08-25', '2026-09-10', 'Extra',   280, 'Bona floració, quallat del 85%, sense gelades tardanes'),
(4, 2026, '2026-03-05',  70000.00, 31.11, '2026-08-01', '2026-08-20', 'Primera', 160, 'Any normal, sense incidències destacables');

INSERT INTO collita (id_plantacio, id_treballador_responsable, data_inici, data_fi, quantitat, unitat_mesura, qualitat, condicions_ambientals, observacions) VALUES
(3, 1, '2025-08-28 07:00:00', '2025-09-05 18:00:00', 115000.00, 'kg', 'Primera', 'Temp. 24ÂºC, humitat 55%',     'Bona collita, una mica de minva per cop de sol'),
(1, 1, '2025-11-05 08:00:00', '2025-11-18 17:00:00',   4650.00, 'kg', 'Extra',   'Temp. 14ÂºC, vent suau nord', 'Collita d''oliva Arbequina per a oli verge extra'),
(4, 1, '2025-08-05 07:00:00', '2025-08-18 16:00:00',  67500.00, 'kg', 'Primera', 'Temp. 26ÂºC, humitat 50%',     'Peres Conference campanya 2025');

INSERT INTO collita_treballador (id_collita, id_treballador, hores_treballades, kg_recollectats) VALUES
-- Collita 1: pomes Golden
(1, 1, 56.00, 45000.00),
(1, 2, 48.00, 38000.00),
(1, 3, 40.00, 32000.00),
-- Collita 2: olives Arbequina
(2, 1, 32.00, 1800.00),
(2, 3, 40.00, 2850.00),
-- Collita 3: peres Conference
(3, 1, 48.00, 28000.00),
(3, 2, 48.00, 25000.00),
(3, 3, 40.00, 14500.00);

INSERT INTO lot_produccio (id_collita, identificador, data_processat, pes_kg, qualitat, desti, client_final, id_sector) VALUES
-- Lots pomes Golden (collita 1)
(1, 'LOT-POM-25-001', '2025-09-10', 20000.00, 'Extra',   'Mercat Nacional', 'Mercabarna',   3),
(1, 'LOT-POM-25-002', '2025-09-10', 15000.00, 'Primera', 'Exportació',      'Carrefour FR', 3),
-- Lot olives Arbequina (collita 2)
(2, 'LOT-OLI-25-001', '2025-11-20',  4650.00, 'Extra',   'Cooperativa',     'Cooperativa Les Borges', 1),
-- Lot peres Conference (collita 3)
(3, 'LOT-PER-25-001', '2025-08-22', 30000.00, 'Primera', 'Mercat Nacional', 'Mercabarna',   4);

INSERT INTO control_qualitat (id_lot, id_inspector, data_control, calibre_mm, color, fermesa_kg_cm2, sabor, resultat, comentaris) VALUES
(1, 1, '2025-09-11', 75.50, 'Groc verdós típic',            6.80, 'Dolç amb punt d''acidesa', 'Acceptat', 'Lot de primera qualitat exportació'),
(2, 1, '2025-09-11', 70.20, 'Groc amb tons verds',          7.10, 'Equilibrat',               'Acceptat', 'Qualitat estàndard mercat intern'),
(3, 1, '2025-11-21', NULL,  'Verd fosc oliva',              NULL, 'Oli fruitat intens',       'Acceptat', 'Oliva Arbequina per a AOVE de finca'),
(4, 1, '2025-08-23', 65.00, 'Verd amb base groguenc',       8.20, 'Dolç, textura fina',       'Acceptat', 'Pera Conference de calibre comercial');

-- --------------------------------------------------------
-- MÒDUL 6: AUDITORIA
-- --------------------------------------------------------

INSERT INTO log_accions (id_treballador, data_hora, accio, taula_afectada, id_registre_afectat) VALUES
(1, '2026-03-10 09:30:00', 'Validació de jornada laboral',          'jornada',         1),
(1, '2026-03-10 09:45:00', 'Creació de previsió de collita 2026',   'previsio_collita', 1),
(2, '2026-03-10 08:30:00', 'Registre nova aplicació fitosanitària', 'aplicacio',        1),
(1, '2026-03-10 11:00:00', 'Actualització estoc CobrePlus 50',      'moviment_estoc',   4);

-- --------------------------------------------------------
-- MÒDUL 7: ANÀLISIS D'AIGUA I FOLIARS
-- --------------------------------------------------------

-- Anàlisis fisicoquímiques de l'aigua de reg (una per sector)
INSERT INTO analisi_aigua (id_sector, data_analisi, origen_mostra, pH, conductivitat_electrica, duresa, nitrats, clorurs, sulfats, bicarbonat, Na, Ca, Mg, K, SAR, observacions) VALUES
(1, '2026-01-15', 'pou',   7.20, 0.85,  280.00,  12.50,  45.00, 120.00, 245.00, 32.00, 85.00, 28.00, 4.50, 1.10, 'Aigua de bona qualitat per a reg d''oliveres. Sense restriccions.'),
(2, '2026-01-16', 'bassa',  7.50, 1.20,  350.00,  18.00,  78.00, 185.00, 310.00, 65.00, 92.00, 35.00, 5.20, 2.40, 'Conductivitat lleugerament elevada. Vigilar acumulació de sals en ametllers joves.'),
(3, '2026-02-05', 'xarxa', 7.00, 0.45,  150.00,   8.50,  22.00,  65.00, 180.00, 18.00, 55.00, 15.00, 3.80, 0.75, 'Aigua de xarxa municipal. Qualitat excel·lent per a pomeres.'),
(4, '2026-02-06', 'pou',   7.80, 1.50,  420.00,  25.00, 110.00, 210.00, 380.00, 88.00, 98.00, 42.00, 6.10, 3.20, 'SAR elevat (3.2). Recomanable barrejar amb aigua de xarxa per reduir risc sòdic als perers.');

-- Anàlisis foliars de diagnosi nutricional (una per plantació/sector)
INSERT INTO analisi_foliar (id_sector, id_plantacio, data_analisi, estat_fenologic, N, P, K, Ca, Mg, Fe, Mn, Zn, Cu, B, deficiencies_detectades, recomanacions, observacions) VALUES
(1, 1, '2025-07-10', 'creixement_fruit', 1.85, 0.14, 1.20, 2.10, 0.28,  95.00, 42.00, 18.00, 8.50, 22.00,
 'Nivell de Zinc lleugerament baix.',
 'Aplicar quelat de Zn foliar (0.3%) en post-floració.',
 'Mostra recollida de 4 arbres representatius del sector Olivars del Sol.'),
(2, 2, '2025-07-12', 'creixement_fruit', 2.20, 0.18, 1.55, 1.80, 0.35, 110.00, 55.00, 25.00, 7.20, 35.00,
 'Sense deficiències destacables.',
 'Mantenir programa de fertilització actual.',
 'Ametllers Guara en bon estat nutricional general.'),
(3, 3, '2025-06-20', 'creixement_fruit', 2.45, 0.22, 1.80, 1.50, 0.30, 145.00, 38.00, 22.00, 6.80, 28.00,
 'Calci lleugerament baix. Risc de Bitter Pit en pomes.',
 'Aplicar 3-4 tractaments foliars de CaCl2 (0.5%) des de la floració fins a la collita.',
 'Pomeres Golden Delicious — important mantenir nivells de Ca per evitar fisiologies post-collita.'),
(4, 4, '2025-07-01', 'creixement_fruit', 2.10, 0.16, 1.40, 2.30, 0.22,  78.00, 48.00, 15.00, 9.10, 19.00,
 'Ferro baix (78 ppm < 100 ppm umbral). Possible clorosi fèrrica incipient.',
 'Aplicar quelat de Fe EDDHA al sòl (30 g/arbre) i revisió del pH de reg.',
 'Perers Conference al sector de la Serra. El pH elevat de l''aigua pot bloquejar l''absorció de Fe.');

-- ============================================================
-- DADES ADDICIONALS: CLIENTS I COMANDES
-- ============================================================
INSERT INTO client (nom_client, nif_cif, adreca, poblacio, codi_postal, telefon, email, tipus_client, estat) VALUES
('Fruiteria Maria', '45812930R', 'Carrer Major 12', 'Lleida', '25001', '612345678', 'maria@fruiteria.cat', 'particular', 'actiu'),
('Cooperativa de Fruits SCCL', 'F25123456', 'Polígon Industrial El Segre', 'Lleida', '25191', '973123456', 'info@coopfruits.cat', 'cooperativa', 'actiu'),
('Distribució Alimentària SL', 'B25987654', 'Gran Via 45', 'Barcelona', '08001', '931234567', 'compres@distalimentaria.com', 'empresa', 'actiu');

-- Note: Product references in detall_comanda should refer to existing producte_quimic IDs (1, 2, 3...)
-- To avoid foreign key issues just in case I use producte_quimic ID 1, 2, 3
INSERT INTO comanda (id_client, num_comanda, data_comanda, data_entrega_prevista, estat_comanda, forma_pagament, subtotal, iva_percentatge, iva_import, total, observacions) VALUES
(1, '26040001', '2026-04-10', '2026-04-12', 'entregat', 'targeta', 150.00, 10.00, 15.00, 165.00, 'Entrega al matí'),
(2, '26040002', '2026-04-15', '2026-04-20', 'preparacio', 'transferencia', 2500.00, 10.00, 250.00, 2750.00, 'Palets logístics'),
(3, '26040003', '2026-04-18', '2026-04-19', 'pendent', 'transferencia', 850.00, 10.00, 85.00, 935.00, 'Contactar al descarregar');

INSERT INTO detall_comanda (id_comanda, id_producte, quantitat, preu_unitari, descompte_percent, descompte_import, subtotal_linia, unitat_mesura, observacions) VALUES
(1, 1, 10.00, 15.00, 0.00, 0.00, 150.00, 'L', 'Caixes reforçades'),
(2, 2, 200.00, 12.50, 0.00, 0.00, 2500.00, 'kg', 'Format Palet'),
(3, 3, 50.00, 17.00, 0.00, 0.00, 850.00, 'L', '');

INSERT INTO factura (id_comanda, num_factura, data_factura, data_venciment, forma_pagament, estat_factura, base_imposable, iva_percentatge, iva_import, total_factura) VALUES
(1, 'F26040001', '2026-04-12', '2026-04-12', 'targeta', 'pagada', 150.00, 10.00, 15.00, 165.00),
(2, 'F26040002', '2026-04-20', '2026-05-20', 'transferencia', 'pendent', 2500.00, 10.00, 250.00, 2750.00);

-- ============================================================
-- DADES ADDICIONALS: INVENTARI FÍSIC I MANTENIMENT
-- ============================================================
INSERT INTO inventari_fisic_registre (id_producte, data_inventari, estoc_teoric, estoc_real, diferencia, observacions) VALUES
(1, '2026-04-01', 50.00, 48.00, -2.00, 'Ampolles trencades detectades al fons del magatzem'),
(2, '2026-04-01', 120.00, 120.00, 0.00, 'Revisió quadrant perfectament'),
(3, '2026-04-01', 20.00, 21.00, 1.00, 'Hi havia 1 unitat de més assignada per error anteriorment');

INSERT INTO manteniment_maquinaria (id_maquinaria, data_programada, data_realitzada, tipus_manteniment, descripcio, cost, realitzat, observacions) VALUES
(1, '2025-10-15', '2025-10-16', 'preventiu', 'Canvi d''oli i filtres del tractor principal', 150.00, TRUE, 'Tractor LL-4521-AB: filtre d''aire molt brut, cal revisar zona on treballa.'),
(2, '2026-02-10', '2026-02-12', 'correctiu', 'Reparació bomba de pressió del polvoritzador', 320.50, TRUE, 'Bomba substituïda completament per model reforçat.'),
(3, '2026-05-20', NULL, 'preventiu', 'Revisió anual pret-collita', NULL, FALSE, 'Contactar amb el servei tècnic 2 setmanes abans'),
(1, '2026-10-15', NULL, 'preventiu', 'Canvi d''oli anual 2026', NULL, FALSE, 'Toca també revisar frens');

SET FOREIGN_KEY_CHECKS = 1;
