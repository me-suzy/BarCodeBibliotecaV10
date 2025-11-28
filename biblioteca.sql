-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 28, 2025 at 06:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `biblioteca`
--

-- --------------------------------------------------------

--
-- Table structure for table `carti`
--

CREATE TABLE `carti` (
  `id` int(11) NOT NULL,
  `cod_bare` varchar(50) NOT NULL,
  `statut` varchar(2) DEFAULT '01',
  `titlu` varchar(255) NOT NULL,
  `autor` varchar(255) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `cota` varchar(50) DEFAULT NULL,
  `raft` varchar(10) DEFAULT NULL,
  `nivel` varchar(10) DEFAULT NULL,
  `pozitie` varchar(10) DEFAULT NULL,
  `locatie_completa` varchar(100) GENERATED ALWAYS AS (concat('Raft ',`raft`,' - Nivel ',`nivel`,' - Pozi??ia ',`pozitie`)) STORED,
  `sectiune` varchar(50) DEFAULT NULL,
  `observatii_locatie` text DEFAULT NULL,
  `data_adaugare` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `carti`
--

INSERT INTO `carti` (`id`, `cod_bare`, `statut`, `titlu`, `autor`, `isbn`, `cota`, `raft`, `nivel`, `pozitie`, `sectiune`, `observatii_locatie`, `data_adaugare`) VALUES
(1, 'BOOK001', '01', 'Amintiri din copilărie', 'Ion Creangă', '9789734640539', '821.135.1 CRE a', 'A', '1', '01', '', '', '2025-11-15 09:26:52'),
(2, 'BOOK002', '01', 'Maitreyi000', 'Mircea Eliade', '9789734640546', '821.135.1 ELI m', 'A', '1', '02', 'Literatur?? rom??n??', '', '2025-11-15 09:26:52'),
(3, 'BOOK003', '01', 'Pădurea spânzuraţilor', 'Liviu Rebreanu', '9789734640553', '821.135.1 REB p', 'A', '1', '03', '', '', '2025-11-15 09:26:52'),
(5, 'BOOK005', '01', 'Moromete iubitor', 'Marin Preda', '9789734640577', '821.135.1 PRE m', 'A', '1', '05', '', '', '2025-11-15 09:26:52'),
(6, 'BOOK006', '01', 'Bebe', 'Autor Bebe', '235456565', 'SL455', 'P', '1', '05', 'Filosofie', '', '2025-11-15 09:36:17'),
(9, 'BOOK009', '01', 'Ultima noapte de dragoste', 'Camil Petrescu', '9789734640607', '821.135.1 PET u', 'A', '2', '03', 'Literatur?? rom??n??', NULL, '2025-11-15 15:12:05'),
(10, 'BOOK010', '01', 'Lumini deasupra cerului', 'Mihai Eminescu', '9789734640614', '821.135.1 EMI l', 'A', '2', '04', '', '', '2025-11-15 15:12:05'),
(11, 'BOOK011', '01', 'Luceafărul', 'Mihai Eminescu', '9789734640621', '821.135.1 EMI lu', 'A', '2', '05', '', '', '2025-11-15 15:12:05'),
(12, 'BOOK012', '01', 'Moara cu noroc', 'Ioan Slavici', '9789734640638', '821.135.1 SLA m', 'B', '1', '01', 'Literatur?? rom??n??', NULL, '2025-11-15 15:12:05'),
(13, 'BOOK013', '01', 'O scrisoare pierdută', 'I.L. Caragiale', '9789734640645', '821.135.1 CAR o', 'B', '1', '02', '', '', '2025-11-15 15:12:05'),
(14, 'BOOK014', '01', 'Citadela sfărmată', 'Tudor Arghezi', '9789734640652', '821.135.1 ARG c', 'B', '1', '03', '', '', '2025-11-15 15:12:05'),
(15, 'BOOK015', '01', 'Groapa', 'Eugen Barbu', '9789734640669', '821.135.1 BAR g', 'B', '1', '04', 'Literatur?? rom??n??', NULL, '2025-11-15 15:12:05'),
(17, 'BOOK0040', '01', 'Yoga', 'Henri stahl', '', '', '', '', '', '', '', '2025-11-15 23:09:21'),
(19, 'C195082', '01', 'Antropologie stilistică / Mihai Popa : item-global-head-2 -->', 'Popa, Mihai', '978-973-27-2824-6', 'II-57001', '', '', '', 'Cărţi depozit', '', '2025-11-17 17:37:51'),
(20, '59281-10', '01', 'Om, creaţie, libertate : Cultura ca orizont ontologic uman în concepţia filozofului Lucian Blaga', 'Şandor, Ioana Camelia', '978-973-166-087-5', 'II-52455', '', '', '', 'Cărţi depozit', '', '2025-11-17 17:47:58'),
(23, 'C181337', '01', 'Lucian Blaga : Mitul poetic', 'Todoran, Eugen', '', 'SL DI II-1693', NULL, NULL, NULL, 'Cărţi sala de lectură', NULL, '2025-11-17 17:58:02'),
(24, '000030207-10', '01', 'St. Monografia Serviciului Sanitar Veterinar al Municipiului Bucureşti / Const. St. Rădulescu, medic veterinar, Inspector General : item-global-head-2 -->', 'RĂDULESCU, Const', '', 'DAB II-3878', '', '', '', 'Cărţi sala de lectură', '', '2025-11-17 20:30:47'),
(25, '000029152-10', '01', 'Raport asupra legii pentru organizarea comunelor rurale', 'gaga Mihai', '', 'DAB II-01270', '', '', '', '', '', '2025-11-17 20:35:10'),
(27, '000048576-10', '01', 'STRECHA NAD HLAVOU : PRACOVNI SETKANI O NEJSTARSI ARCHITEKTURE', 'Jelinek, Jan, ed', '', 'II-1270', NULL, NULL, NULL, '', NULL, '2025-11-18 04:03:36'),
(28, 'BOOK0002', '01', 'Sfârşitul zilei mari', 'Henri stahl', '', 'YU-7889', '', '', '', '', '', '2025-11-18 19:59:31'),
(29, 'BOOK0001', '01', 'Acasa', 'Dan Brown', '', '', '', '', '', '', '', '2025-11-18 21:36:34'),
(33, 'C198855', '01', 'Avădănei, Ioana (trad.). Economia comestibilă : poveşti despre mâncare ce îţi vor schimba felul de a vedea lumea', 'Chang, Ha-Joon', '', 'II-57981', NULL, NULL, NULL, 'Cărţi depozit', NULL, '2025-11-19 06:41:06'),
(39, '000015237-10', '01', 'Minunea Sfîntului Baudolino / Umberto Eco ; trad. din italiană de Sorin Mărculescu : item-global-head-2 -->', 'ECO, Umberto', '', 'II-48419', NULL, NULL, NULL, 'Cărţi depozit', NULL, '2025-11-22 12:50:46'),
(40, '546DFFGG', '01', 'Carte Noua', 'JIoii', '', '', '', '', '', '', '', '2025-11-22 16:09:22'),
(41, 'RV00152', '03', 'Mavrocordat, Nicolae Alexandru, domn al Ţării Româneşti (patron) &nbsp;1715-171. Marcovici, Ieremia (tip.). [Sfânta şi Dumnezeiasca Liturghie a Sfântului Ioan Zlatoust, a Marelui Vasilie şi a Sfântului Grigorie... ] / [Cu blagoslovenia prea sfinţitului Pa', 'Hrysanthos, Patriarhul Ierusalimului (patron)', '', 'RV I-136', NULL, NULL, NULL, 'Carte romanească veche', NULL, '2025-11-22 18:30:35'),
(42, 'RR03511', '03', 'Codice de comerciu al Regatului României din 1887 : Cu modificaţiunile introduse prin Legea din 20 iunie 1895 şi Regulamentul din 7 septembrie 1887 şi cel din 20 iunie 1895', 'Ministerul Justiţiei', '', 'RR III-382', NULL, NULL, NULL, 'Carte romanească rară', NULL, '2025-11-22 18:31:10'),
(43, 'C184507', '02', 'Todini, Umberto (introd.). Saglimbeni, Sebastiano (trad.). Bucoliche', 'Vergilius Maro, Publius (0070-0019 î.Hr.)', '', 'SL Irimia/1146', NULL, NULL, NULL, '2015/03', NULL, '2025-11-27 18:23:53'),
(44, 'P089010', '02', 'Academia Română. Academica : revistă de ştiinţă, cultură şi artă : fondată în 1990 de acad. Mihai Drăgănescu', 'Simion, Eugen (dir. acad.) &nbsp;(1933-)', '', '2024, An.34 : nr.1/2', NULL, NULL, NULL, '2024/16', NULL, '2025-11-27 18:24:24'),
(45, 'ISRS2812', '02', 'Maşina plană de tricotat (Strick)', 'Candel, A., ing.', '', 'ISRS-2483', NULL, NULL, NULL, '2022/05', NULL, '2025-11-27 19:30:57'),
(46, '000035839-10', '03', 'Studii : peisajul acţiunii estetico-vitalizante, armonizante Vsevald Carmăzinu Cocovschi', 'ZAMFIR, Ghorghe', '', 'MS 99', NULL, NULL, NULL, '', NULL, '2025-11-27 19:31:18'),
(47, 'RV00208', '03', 'Veniamin Costache, Mitropolitul Moldovei (trad., ed.). Gheorghie, Ierodiacon (tip.). Bîlcul, Theodor (tip.). Platenchi, Dimitrie (tip.). Adoleshia filotheos :Tomul al treilea : adecă Îndeletnicire iubitoare de Dumnezeu, adecă din cetirea cărţii celor cinc', 'Vulgaris, Evghenios', '', 'RV II-107', NULL, NULL, NULL, '', NULL, '2025-11-27 19:31:38'),
(48, '59082-10', '01', 'Clădiri civile', 'Velicu, Cristian', '', 'IV-4659', NULL, NULL, NULL, '2012/11', NULL, '2025-11-27 19:31:48'),
(49, 'BOOK004', '01', 'Sotul infidel', 'Mircea Carurescu', '', '', '', '', '', '', '', '2025-11-27 21:11:46');

-- --------------------------------------------------------

--
-- Table structure for table `cititori`
--

CREATE TABLE `cititori` (
  `id` int(11) NOT NULL,
  `cod_bare` varchar(50) NOT NULL,
  `statut` varchar(2) DEFAULT '14',
  `nume` varchar(100) NOT NULL,
  `prenume` varchar(100) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `blocat` tinyint(1) DEFAULT 0 COMMENT '0=activ, 1=blocat (din cauza ??nt??rzierilor sau alte motive)',
  `motiv_blocare` varchar(255) DEFAULT NULL COMMENT 'Motivul bloc??rii',
  `data_inregistrare` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_vizare` date DEFAULT NULL COMMENT 'Data ultimei viz??ri anuale a permisului',
  `nota_vizare` text DEFAULT NULL COMMENT 'Observa??ii despre vizare (op??ional)',
  `imagine` varchar(255) DEFAULT NULL COMMENT 'Cale către imaginea cititorului'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `cititori`
--

INSERT INTO `cititori` (`id`, `cod_bare`, `statut`, `nume`, `prenume`, `telefon`, `email`, `blocat`, `motiv_blocare`, `data_inregistrare`, `ultima_vizare`, `nota_vizare`, `imagine`) VALUES
(1, 'USER001', '14', 'FântânaruṢȚ', 'Ion', '0721123456', 'ion.popescu@email.ro', 0, NULL, '2025-11-15 09:26:52', '2025-01-15', NULL, 'imagini/USER001_F__nt__naru______Ion.jpg'),
(3, 'USER003', '14', 'Dumitrescu', 'Andrei', '0723345678', 'andrei.dumitrescu@email.ro', 0, NULL, '2025-11-15 09:26:52', '2024-12-15', NULL, 'imagini/USER003_Dumitrescu_Andrei.jpg'),
(4, 'USER004', '14', 'Gheorghe', 'Elena', '0724456789', 'elena.gheorghe@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-11-17', NULL, NULL),
(5, 'USER005', '14', 'Radu', 'Mihai', '0725567890', 'mihai.radu@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-01-12', NULL, NULL),
(14, 'USER0070', '14', 'Sebi', 'ionut', '0766334566', 'sebi@yahoo.com', 0, NULL, '2025-11-15 23:10:16', NULL, NULL, NULL),
(16, 'USER021', '14', 'Hichi', 'Ion++', '0740152808', 'hichi@yahoo.com', 0, NULL, '2025-11-16 13:09:19', '2025-02-14', NULL, 'imagini/USER021_Hichi_Ion__.jpg'),
(17, 'USER011', '14', 'Marius', 'Popa Ion', '34535446546', 'coco.babu@gmail.com', 0, NULL, '2025-11-17 17:15:34', '2025-11-17', NULL, 'imagini/USER011_Marius_Popa_Ion.jpeg'),
(18, 'USER014', '14', 'Dovleac', 'Maria', '345353', 'dovleaa@dfs.com', 0, NULL, '2025-11-18 19:56:01', '2025-11-18', NULL, 'imagini/USER014_Dovleac_Maria.jpg'),
(20, '14016038', '14', 'Fantanaru', 'Neculai', '0740152808', 'ioan.fantanaru@gmail.com', 0, NULL, '2025-11-21 11:38:12', NULL, NULL, NULL),
(21, '110000000010', '11', 'Ionut (Statut 11)', 'Persona Stiintific', '', '', 0, NULL, '2025-11-26 04:53:38', NULL, NULL, 'imagini/110000000010_Ionut__Statut_11__Persona_Stiintific.png'),
(22, '120000000011', '12', 'Super (Statut 12)', 'Bet', '34436556464', '', 0, NULL, '2025-11-26 05:00:08', NULL, NULL, 'imagini/120000000010_Super__Statut_12__Bet.png'),
(23, '170000000011', '17', 'Remus (Statut 17)', 'Coco', '', '', 0, NULL, '2025-11-26 05:01:35', NULL, NULL, 'imagini/170000000010_Remus__Statut_17__Coco.jpeg'),
(24, '130000000018', '13', 'Ioana (Statut 13)', 'Socu', '', '', 0, NULL, '2025-11-26 05:04:40', NULL, NULL, 'imagini/130000000010_Ioana__Statut_13__Socu.webp'),
(25, '160000000010', '16', 'Mumu (statut 16)', 'Susu', '', '', 0, NULL, '2025-11-26 05:10:56', NULL, NULL, 'imagini/160000000010_Mumu__statut_16__Susu.jpg'),
(26, '140000000018', '14', 'Gelu (statut 14 - cu domiciului in Iasi)', 'Maria', '', '', 0, NULL, '2025-11-26 05:11:35', NULL, NULL, 'imagini/140000000010_Gelu__statut_14___cu_domiciului_in_Iasi__Maria.jpg'),
(27, '150000006666', '15', 'Redea (statut 15 - fara domiciliu in Iasi)', 'Suzy', '', '', 0, NULL, '2025-11-26 05:15:17', NULL, NULL, 'imagini/160000000020_Redea__statut_15___fara_domiciliu_in_Iasi__Suzy.jpeg'),
(29, 'USER909', '14', 'Simonsita+', 'Iulia', '', '', 0, NULL, '2025-11-27 22:25:24', NULL, NULL, 'imagini/USER909_Simonsita_Iulia.png');

-- --------------------------------------------------------

--
-- Table structure for table `cititori_backup_normalizare`
--

CREATE TABLE `cititori_backup_normalizare` (
  `id` int(11) NOT NULL DEFAULT 0,
  `cod_bare` varchar(50) NOT NULL,
  `statut` varchar(2) DEFAULT '14',
  `nume` varchar(100) NOT NULL,
  `prenume` varchar(100) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `blocat` tinyint(1) DEFAULT 0 COMMENT '0=activ, 1=blocat (din cauza ??nt??rzierilor sau alte motive)',
  `motiv_blocare` varchar(255) DEFAULT NULL COMMENT 'Motivul bloc??rii',
  `data_inregistrare` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_vizare` date DEFAULT NULL COMMENT 'Data ultimei viz??ri anuale a permisului',
  `nota_vizare` text DEFAULT NULL COMMENT 'Observa??ii despre vizare (op??ional)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `cititori_backup_normalizare`
--

INSERT INTO `cititori_backup_normalizare` (`id`, `cod_bare`, `statut`, `nume`, `prenume`, `telefon`, `email`, `blocat`, `motiv_blocare`, `data_inregistrare`, `ultima_vizare`, `nota_vizare`) VALUES
(1, 'USER001', '14', 'Popescuffff', 'Ion', '0721123456', 'ion.popescu@email.ro', 0, NULL, '2025-11-15 09:26:52', '2025-01-15', NULL),
(2, 'USER002', '14', 'Ionescu', 'Maria', '0722234567', 'maria.ionescu@email.ro', 0, NULL, '2025-11-15 09:26:52', '2025-01-15', NULL),
(3, 'USER003', '14', 'Dumitrescu', 'Andrei', '0723345678', 'andrei.dumitrescu@email.ro', 0, NULL, '2025-11-15 09:26:52', '2024-12-15', NULL),
(4, 'USER004', '14', 'Gheorghe', 'Elena', '0724456789', 'elena.gheorghe@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-11-17', NULL),
(5, 'USER005', '14', 'Radu', 'Mihai', '0725567890', 'mihai.radu@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-01-12', NULL),
(6, 'USER006', '14', 'Stan', 'Alexandra', '0726678901', 'alexandra.stan@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-01-18', NULL),
(8, 'USER008', '14', 'Popasss', 'Diana', '0728890123', 'diana.popa@email.ro', 0, NULL, '2025-11-15 15:11:57', '2025-02-05', NULL),
(14, 'USER0070', '14', 'Sebi', 'ionut', '0766334566', 'sebi@yahoo.com', 0, NULL, '2025-11-15 23:10:16', NULL, NULL),
(16, 'USER021', '14', 'Hichi', 'Ion++', '0740152808', 'hichi@yahoo.com', 0, NULL, '2025-11-16 13:09:19', '2025-02-14', NULL),
(17, 'USER011', '14', 'Nou azi 22ssssssssss', 'Ion', '34535446546', 'ioan.fantanaru@gmail.com', 0, NULL, '2025-11-17 17:15:34', '2025-11-17', NULL),
(18, 'USER014', '14', 'Dovleac', 'Maria', '345353', 'dovleaa@dfs.com', 0, NULL, '2025-11-18 19:56:01', '2025-11-18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `imprumuturi`
--

CREATE TABLE `imprumuturi` (
  `id` int(11) NOT NULL,
  `cod_cititor` varchar(50) NOT NULL,
  `cod_carte` varchar(50) NOT NULL,
  `data_imprumut` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_scadenta` date DEFAULT NULL,
  `data_returnare` timestamp NULL DEFAULT NULL,
  `status` enum('activ','returnat') DEFAULT 'activ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `imprumuturi`
--

INSERT INTO `imprumuturi` (`id`, `cod_cititor`, `cod_carte`, `data_imprumut`, `data_scadenta`, `data_returnare`, `status`) VALUES
(1, 'USER001', 'BOOK001', '2025-11-10 08:30:00', NULL, '2025-11-15 17:00:00', 'returnat'),
(4, 'USER001', 'BOOK006', '2025-11-15 09:20:00', NULL, '2025-11-15 16:57:00', 'returnat'),
(7, 'USER001', 'BOOK002', '2025-11-05 13:00:00', NULL, '2025-11-12 10:30:00', 'returnat'),
(9, 'USER001', 'BOOK006', '2025-11-15 13:40:00', NULL, '2025-11-19 20:41:00', 'returnat'),
(35, 'USER004', 'BOOK009', '2025-11-15 08:12:00', NULL, '2025-11-15 16:58:00', 'returnat'),
(36, 'USER005', 'BOOK010', '2025-11-15 06:12:00', NULL, '2025-11-15 16:59:00', 'returnat'),
(40, 'USER001', 'BOOK014', '2025-11-12 14:12:00', NULL, '2025-11-02 22:31:00', 'returnat'),
(48, 'USER001', 'BOOK012', '2025-10-31 14:12:59', NULL, '2025-11-05 14:12:59', 'returnat'),
(51, 'USER004', 'BOOK015', '2025-10-16 13:12:59', NULL, '2025-10-21 13:12:59', 'returnat'),
(52, 'USER005', 'BOOK001', '2025-10-11 13:12:00', NULL, '2025-11-15 17:01:00', 'returnat'),
(55, 'USER001', 'BOOK001', '2025-11-15 21:58:00', NULL, '2025-11-03 22:31:00', 'returnat'),
(56, 'USER001', 'BOOK002', '2025-11-15 21:58:00', NULL, '2025-11-18 19:38:00', 'returnat'),
(63, 'USER005', 'BOOK012', '2025-11-16 09:30:00', NULL, '2025-11-16 09:31:00', 'returnat'),
(64, 'USER005', 'BOOK013', '2025-11-16 09:31:03', NULL, NULL, 'activ'),
(65, 'USER005', 'BOOK014', '2025-11-16 09:31:12', NULL, NULL, 'activ'),
(66, 'USER005', 'BOOK015', '2025-11-16 09:31:20', NULL, NULL, 'activ'),
(67, 'USER011', 'C195082', '2025-11-17 17:46:27', '2025-12-01', '2025-11-17 20:31:17', 'returnat'),
(68, 'USER011', '59281-10', '2025-11-17 17:47:58', '2025-12-01', '2025-11-17 20:32:11', 'returnat'),
(72, 'USER011', '000030207-10', '2025-11-17 20:30:00', '2025-12-01', NULL, 'activ'),
(73, 'USER011', 'C195082', '2025-11-17 20:31:28', '2025-12-01', '2025-11-17 20:31:31', 'returnat'),
(74, 'USER011', 'C195082', '2025-11-17 20:31:40', '2025-12-01', '2025-11-17 20:31:53', 'returnat'),
(75, 'USER011', '000029152-10', '2025-11-17 20:35:10', '2025-12-01', '2025-11-18 02:44:30', 'returnat'),
(76, 'USER011', '000029152-10', '2025-11-18 02:54:56', '2025-12-02', '2025-11-18 03:03:00', 'returnat'),
(77, 'USER011', '000029152-10', '2025-11-18 03:06:30', '2025-12-02', '2025-11-18 03:12:18', 'returnat'),
(80, 'USER011', '000029152-10', '2025-11-18 03:12:23', '2025-12-02', '2025-11-18 03:14:33', 'returnat'),
(82, 'USER011', '000029152-10', '2025-11-18 03:14:37', '2025-12-02', '2025-11-18 04:03:59', 'returnat'),
(83, 'USER011', '000048576-10', '2025-11-18 04:03:36', '2025-12-02', '2025-11-18 04:03:50', 'returnat'),
(86, 'USER014', 'BOOK0002', '2025-11-18 19:59:31', '2025-12-02', '2025-11-18 20:00:04', 'returnat'),
(87, 'USER014', 'BOOK0002', '2025-11-18 20:00:24', '2025-12-02', '2025-11-18 20:07:24', 'returnat'),
(88, 'USER014', 'BOOK0002', '2025-11-18 20:07:57', '2025-12-02', '2025-11-18 20:08:54', 'returnat'),
(89, 'USER014', 'BOOK0002', '2025-11-18 20:10:57', '2025-12-02', '2025-11-18 20:12:48', 'returnat'),
(90, 'USER014', 'BOOK0002', '2025-11-18 21:26:28', '2025-12-02', '2025-11-18 21:27:24', 'returnat'),
(91, 'USER014', 'BOOK0002', '2025-11-18 21:27:35', '2025-12-02', '2025-11-18 21:55:51', 'returnat'),
(92, 'USER014', 'BOOK0002', '2025-11-18 21:56:15', '2025-12-02', '2025-11-18 22:31:05', 'returnat'),
(93, 'USER011', 'BOOK0001', '2025-11-18 22:07:32', '2025-12-02', '2025-11-18 22:15:05', 'returnat'),
(94, 'USER011', 'BOOK0001', '2025-11-18 22:15:09', '2025-12-02', '2025-11-18 22:15:23', 'returnat'),
(95, 'USER014', 'BOOK0002', '2025-11-18 22:31:11', '2025-12-02', '2025-11-18 22:31:17', 'returnat'),
(96, 'USER014', 'BOOK0002', '2025-11-18 22:31:22', '2025-12-02', '2025-11-18 22:34:01', 'returnat'),
(97, 'USER011', 'BOOK0002', '2025-11-18 22:35:19', '2025-12-02', '2025-11-18 23:22:19', 'returnat'),
(98, 'USER011', 'BOOK0002', '2025-11-18 23:22:27', '2025-12-03', '2025-11-18 23:37:17', 'returnat'),
(100, 'USER011', 'BOOK0002', '2025-11-18 23:37:21', '2025-12-03', '2025-11-19 00:27:12', 'returnat'),
(101, 'USER011', 'BOOK0001', '2025-11-18 23:37:53', '2025-12-03', '2025-11-19 00:28:00', 'returnat'),
(102, 'USER011', 'BOOK0002', '2025-08-31 23:27:00', '2025-12-03', NULL, 'activ'),
(103, 'USER001', 'BOOK001', '2025-11-19 17:18:00', '2025-12-03', '2025-11-19 21:40:00', 'returnat'),
(105, 'USER001', 'BOOK002', '2025-08-01 16:28:00', '2025-12-03', '2025-11-27 23:42:48', 'returnat'),
(107, 'USER001', 'BOOK009', '2025-11-19 21:42:04', '2025-12-03', NULL, 'activ'),
(108, 'USER001', '000015237-10', '2025-11-22 12:50:00', '2025-12-06', '2025-11-22 16:06:00', 'returnat'),
(109, 'USER001', '546DFFGG', '2025-11-22 16:10:00', '2025-12-06', '2025-11-22 16:10:00', 'returnat'),
(110, 'USER001', '000015237-10', '2025-11-22 18:31:55', '2025-12-06', NULL, 'activ'),
(111, '140000000018', '59082-10', '2025-11-27 19:31:48', '2025-12-11', NULL, 'activ'),
(112, '150000006666', 'C181337', '2025-11-27 21:09:36', '2025-12-11', NULL, 'activ'),
(113, '160000000010', 'BOOK012', '2025-11-27 21:10:20', '2025-12-11', NULL, 'activ'),
(114, '160000000010', 'BOOK001', '2025-11-27 21:10:37', '2025-12-11', NULL, 'activ'),
(115, '160000000010', 'BOOK0040', '2025-11-27 21:11:03', '2025-12-11', NULL, 'activ'),
(116, '160000000010', 'BOOK003', '2025-08-06 20:11:00', '2025-12-11', NULL, 'activ'),
(117, '160000000010', 'BOOK005', '2025-11-27 21:12:11', '2025-12-11', NULL, 'activ'),
(118, '160000000010', 'BOOK006', '2025-11-27 21:12:18', '2025-12-11', NULL, 'activ');

-- --------------------------------------------------------

--
-- Table structure for table `istoric_locatii`
--

CREATE TABLE `istoric_locatii` (
  `id` int(11) NOT NULL,
  `cod_carte` varchar(50) NOT NULL,
  `raft_vechi` varchar(10) DEFAULT NULL,
  `nivel_vechi` varchar(10) DEFAULT NULL,
  `pozitie_veche` varchar(10) DEFAULT NULL,
  `raft_nou` varchar(10) DEFAULT NULL,
  `nivel_nou` varchar(10) DEFAULT NULL,
  `pozitie_noua` varchar(10) DEFAULT NULL,
  `data_mutare` timestamp NOT NULL DEFAULT current_timestamp(),
  `utilizator` varchar(100) DEFAULT NULL,
  `motiv` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modele_email`
--

CREATE TABLE `modele_email` (
  `id` int(11) NOT NULL,
  `tip_notificare` enum('imprumut','reminder','intarziere') NOT NULL,
  `subiect` varchar(255) NOT NULL,
  `template_html` text NOT NULL,
  `template_text` text DEFAULT NULL,
  `variabile_utilizate` text DEFAULT NULL COMMENT 'List?? variabile disponibile (JSON)',
  `activ` tinyint(1) DEFAULT 1,
  `data_creare` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_actualizare` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `modele_email`
--

INSERT INTO `modele_email` (`id`, `tip_notificare`, `subiect`, `template_html`, `template_text`, `variabile_utilizate`, `activ`, `data_creare`, `data_actualizare`) VALUES
(1, 'imprumut', '📚 Confirmare Împrumut - Biblioteca Academiei Române - Iași', '<!DOCTYPE html>\r\n<html lang=\"ro\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <style>\r\n        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }\r\n        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }\r\n        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }\r\n        .header h1 { margin: 0; font-size: 24px; }\r\n        .content { padding: 30px 20px; }\r\n        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }\r\n        .book-details { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }\r\n        .book-title { font-weight: bold; color: #667eea; font-size: 16px; }\r\n        .book-info { color: #666; font-size: 14px; margin-top: 5px; }\r\n        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .info-box strong { color: #1976D2; }\r\n        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-container\">\r\n        <div class=\"header\">\r\n            <h1>???? Confirmare ??mprumut</h1>\r\n            <p style=\"margin: 10px 0 0 0; opacity: 0.9;\">Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"content\">\r\n            <div class=\"greeting\">\r\n                Bun?? ziua <strong>{{NUME_COMPLET}}</strong>,\r\n            </div>\r\n            \r\n            <p>V?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre ??i v?? confirm??m c?? a??i ??mprumutat cu succes urm??toarele c??r??i:</p>\r\n            \r\n            <div class=\"book-details\">\r\n                {{LISTA_CARTI}}\r\n            </div>\r\n            \r\n            <div class=\"info-box\">\r\n                <p><strong>???? Data returnare recomandat??:</strong> {{DATA_RETURNARE}}</p>\r\n                <p><strong>???? Loca??ie bibliotec??:</strong> Biblioteca Academiei Rom??ne - Ia??i</p>\r\n                <p><strong>??? Program:</strong> Luni - Vineri: 09:00 - 17:00</p>\r\n            </div>\r\n            \r\n            <p>V?? rug??m s?? respecta??i termenul de returnare pentru a permite ??i altor cititori s?? beneficieze de aceste c??r??i.</p>\r\n            \r\n            <p>Pentru ??ntreb??ri sau prelungire termen, v?? rug??m s?? ne contacta??i.</p>\r\n            \r\n            <p style=\"margin-top: 30px;\">Cu respect,<br>\r\n            <strong>Echipa Bibliotecii</strong><br>\r\n            Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>Acest email a fost generat automat de sistemul de notific??ri al bibliotecii.</p>\r\n            <p>Pentru ??ntreb??ri: biblioteca@acadiasi.ro</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Bun?? ziua {{NUME_COMPLET}},\r\n\r\nV?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre ??i v?? confirm??m c?? a??i ??mprumutat cu succes urm??toarele c??r??i:\r\n\r\n{{LISTA_CARTI_TEXT}}\r\n\r\nData returnare recomandat??: {{DATA_RETURNARE}}\r\nLoca??ie bibliotec??: Biblioteca Academiei Rom??ne - Ia??i\r\nProgram: Luni - Vineri: 09:00 - 17:00\r\n\r\nV?? rug??m s?? respecta??i termenul de returnare pentru a permite ??i altor cititori s?? beneficieze de aceste c??r??i.\r\n\r\nCu respect,\r\nEchipa Bibliotecii\r\nBiblioteca Academiei Rom??ne - Ia??i', '[\"NUME_COMPLET\", \"LISTA_CARTI\", \"LISTA_CARTI_TEXT\", \"DATA_RETURNARE\"]', 1, '2025-11-18 11:13:40', '2025-11-18 23:20:56'),
(2, 'reminder', '⏰ Reminder: Termen Returnare Aproape - Biblioteca Academiei Române - Iași', '<!DOCTYPE html>\r\n<html lang=\"ro\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <style>\r\n        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }\r\n        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }\r\n        .header { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 30px 20px; text-align: center; }\r\n        .header h1 { margin: 0; font-size: 24px; }\r\n        .content { padding: 30px 20px; }\r\n        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }\r\n        .book-details { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }\r\n        .book-title { font-weight: bold; color: #856404; font-size: 16px; }\r\n        .book-info { color: #666; font-size: 14px; margin-top: 5px; }\r\n        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .warning-box strong { color: #856404; }\r\n        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .info-box strong { color: #1976D2; }\r\n        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-container\">\r\n        <div class=\"header\">\r\n            <h1>??? Reminder Returnare</h1>\r\n            <p style=\"margin: 10px 0 0 0; opacity: 0.9;\">Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"content\">\r\n            <div class=\"greeting\">\r\n                Bun?? ziua <strong>{{NUME_COMPLET}}</strong>,\r\n            </div>\r\n            \r\n            <p>V?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre.</p>\r\n            \r\n            <p>V?? aducem la cuno??tin???? c?? termenul de p??strare pentru urm??toarele c??r??i se apropie de scaden????:</p>\r\n            \r\n            <div class=\"book-details\">\r\n                {{LISTA_CARTI}}\r\n            </div>\r\n            \r\n            <div class=\"warning-box\">\r\n                <p><strong>???? Termen returnare:</strong> {{DATA_RETURNARE}}</p>\r\n                <p><strong>??? Zile r??mase:</strong> {{ZILE_RAMASE}} zile</p>\r\n            </div>\r\n            \r\n            <p>V?? rug??m s?? returna??i c??r??ile ??nainte de data scaden??ei pentru a permite ??i altor cititori s?? le ??mprumute pentru studiu personal.</p>\r\n            \r\n            <div class=\"info-box\">\r\n                <p><strong>???? Loca??ie bibliotec??:</strong> Biblioteca Academiei Rom??ne - Ia??i</p>\r\n                <p><strong>??? Program:</strong> Luni - Vineri: 09:00 - 17:00</p>\r\n                <p><strong>???? Contact:</strong> Pentru prelungire termen sau ??ntreb??ri, v?? rug??m s?? ne contacta??i.</p>\r\n            </div>\r\n            \r\n            <p style=\"margin-top: 30px;\">Cu respect,<br>\r\n            <strong>Echipa Bibliotecii</strong><br>\r\n            Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>Acest email a fost generat automat de sistemul de notific??ri al bibliotecii.</p>\r\n            <p>Pentru ??ntreb??ri: biblioteca@acadiasi.ro</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Bun?? ziua {{NUME_COMPLET}},\r\n\r\nV?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre.\r\n\r\nV?? aducem la cuno??tin???? c?? termenul de p??strare pentru urm??toarele c??r??i se apropie de scaden????:\r\n\r\n{{LISTA_CARTI_TEXT}}\r\n\r\nTermen returnare: {{DATA_RETURNARE}}\r\nZile r??mase: {{ZILE_RAMASE}} zile\r\n\r\nV?? rug??m s?? returna??i c??r??ile ??nainte de data scaden??ei pentru a permite ??i altor cititori s?? le ??mprumute pentru studiu personal.\r\n\r\nLoca??ie bibliotec??: Biblioteca Academiei Rom??ne - Ia??i\r\nProgram: Luni - Vineri: 09:00 - 17:00\r\n\r\nCu respect,\r\nEchipa Bibliotecii\r\nBiblioteca Academiei Rom??ne - Ia??i', '[\"NUME_COMPLET\", \"LISTA_CARTI\", \"LISTA_CARTI_TEXT\", \"DATA_RETURNARE\", \"ZILE_RAMASE\"]', 1, '2025-11-18 11:13:40', '2025-11-18 23:20:56'),
(3, 'intarziere', '🚨 URGENT: Cărți Întârziate - Acțiune Necesară - Biblioteca Academiei Române - Iași', '<!DOCTYPE html>\r\n<html lang=\"ro\">\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <style>\r\n        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }\r\n        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }\r\n        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px 20px; text-align: center; }\r\n        .header h1 { margin: 0; font-size: 24px; }\r\n        .content { padding: 30px 20px; }\r\n        .greeting { font-size: 16px; margin-bottom: 20px; color: #555; }\r\n        .book-details { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .book-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }\r\n        .book-title { font-weight: bold; color: #721c24; font-size: 16px; }\r\n        .book-info { color: #666; font-size: 14px; margin-top: 5px; }\r\n        .urgent-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .urgent-box strong { color: #721c24; }\r\n        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px; }\r\n        .info-box strong { color: #1976D2; }\r\n        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"email-container\">\r\n        <div class=\"header\">\r\n            <h1>???? Alert?? ??nt??rziere</h1>\r\n            <p style=\"margin: 10px 0 0 0; opacity: 0.9;\">Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"content\">\r\n            <div class=\"greeting\">\r\n                Bun?? ziua <strong>{{NUME_COMPLET}}</strong>,\r\n            </div>\r\n            \r\n            <p>V?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre.</p>\r\n            \r\n            <p><strong>V?? aducem la cuno??tin???? c?? a expirat termenul de p??strare</strong> pentru urm??toarele c??r??i:</p>\r\n            \r\n            <div class=\"book-details\">\r\n                {{LISTA_CARTI}}\r\n            </div>\r\n            \r\n            <div class=\"urgent-box\">\r\n                <p><strong>?????? Data returnare recomandat??:</strong> {{DATA_RETURNARE}}</p>\r\n                <p><strong>???? Data expirare:</strong> {{DATA_EXPIRARE}}</p>\r\n                <p><strong>??? Zile ??nt??rziere:</strong> {{ZILE_INTARZIERE}} zile</p>\r\n            </div>\r\n            \r\n            <p><strong>V?? rug??m urgent s?? returna??i c??r??ile</strong> pentru a permite ??i altor cititori s?? le ??mprumute pentru studiu personal.</p>\r\n            \r\n            <p>??n??elegem c?? pot ap??rea situa??ii neprev??zute, dar v?? rug??m s?? ne contacta??i c??t mai cur??nd pentru a discuta solu??ii.</p>\r\n            \r\n            <div class=\"info-box\">\r\n                <p><strong>???? Loca??ie bibliotec??:</strong> Biblioteca Academiei Rom??ne - Ia??i</p>\r\n                <p><strong>??? Program:</strong> Luni - Vineri: 09:00 - 17:00</p>\r\n                <p><strong>???? Contact:</strong> Pentru ??ntreb??ri sau prelungire termen, v?? rug??m s?? ne contacta??i urgent.</p>\r\n            </div>\r\n            \r\n            <p style=\"margin-top: 30px;\">Cu respect,<br>\r\n            <strong>Echipa Bibliotecii</strong><br>\r\n            Biblioteca Academiei Rom??ne - Ia??i</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>Acest email a fost generat automat de sistemul de notific??ri al bibliotecii.</p>\r\n            <p>Pentru ??ntreb??ri: biblioteca@acadiasi.ro</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>', 'Bun?? ziua {{NUME_COMPLET}},\r\n\r\nV?? mul??umim c?? sunte??i cititor fidel al bibliotecii noastre.\r\n\r\nV?? aducem la cuno??tin???? c?? a expirat termenul de p??strare pentru urm??toarele c??r??i:\r\n\r\n{{LISTA_CARTI_TEXT}}\r\n\r\nData returnare recomandat??: {{DATA_RETURNARE}}\r\nData expirare: {{DATA_EXPIRARE}}\r\nZile ??nt??rziere: {{ZILE_INTARZIERE}} zile\r\n\r\nV?? rug??m urgent s?? returna??i c??r??ile pentru a permite ??i altor cititori s?? le ??mprumute pentru studiu personal.\r\n\r\n??n??elegem c?? pot ap??rea situa??ii neprev??zute, dar v?? rug??m s?? ne contacta??i c??t mai cur??nd pentru a discuta solu??ii.\r\n\r\nLoca??ie bibliotec??: Biblioteca Academiei Rom??ne - Ia??i\r\nProgram: Luni - Vineri: 09:00 - 17:00\r\n\r\nCu respect,\r\nEchipa Bibliotecii\r\nBiblioteca Academiei Rom??ne - Ia??i', '[\"NUME_COMPLET\", \"LISTA_CARTI\", \"LISTA_CARTI_TEXT\", \"DATA_RETURNARE\", \"DATA_EXPIRARE\", \"ZILE_INTARZIERE\"]', 1, '2025-11-18 11:13:40', '2025-11-18 23:20:56');

-- --------------------------------------------------------

--
-- Table structure for table `notificari`
--

CREATE TABLE `notificari` (
  `id` int(11) NOT NULL,
  `cod_cititor` varchar(50) DEFAULT NULL,
  `tip_notificare` enum('imprumut','reminder','intarziere') NOT NULL,
  `canal` enum('email','sms') NOT NULL,
  `destinatar` varchar(255) DEFAULT NULL,
  `subiect` varchar(255) DEFAULT NULL,
  `mesaj` text DEFAULT NULL,
  `status` enum('trimis','esuat','in_asteptare') DEFAULT 'in_asteptare',
  `data_trimitere` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `notificari`
--

INSERT INTO `notificari` (`id`, `cod_cititor`, `tip_notificare`, `canal`, `destinatar`, `subiect`, `mesaj`, `status`, `data_trimitere`) VALUES
(1, 'USER011', 'intarziere', 'email', 'ioan.fantanaru@gmail.com', '📚 Reminder Returnare Cărți - Biblioteca', '\r\n        \r\n        \r\n            Bună Popa Ion,\r\n            \r\n            Îți trimitem o reamintire legată de cărțile împrumutate de la bibliotecă:\r\n            \r\n            \r\n            \r\n                📕 Sfârşitul zilei mari\r\n                ✍️ Henri stahl\r\n                📅 Împrumutată: 01.09.2025\r\n                ⏰ 86 zile împrumut\r\n            \r\n            \r\n            \r\n                📕 St. Monografia Serviciului Sanitar Veterinar al Municipiului Bucureşti / Const. St. Rădulescu, medic veterinar, Inspector General : item-global-head-2 -->\r\n                ✍️ RĂDULESCU, Const\r\n                📅 Împrumutată: 17.11.2025\r\n                ⏰ 9 zile împrumut\r\n            \r\n            \r\n            \r\n            \r\n                📝 Mesaj de la bibliotecar:\r\n                Dragă cititor,\r\n\r\nAm observat că aveți cărți împrumutate de mai mult de 14 zile. Vă rugăm să returnați cărțile cât mai curând posibil pentru ca și alți cititori să le poată găsi la bibliotecă și să se bucure de ele.\r\n\r\nBiblioteca noastră funcționează pe principiul partajării cunoașterii - returnând cărțile la timp, contribuiți la bunul mers al comunității noastre de lectură.\r\n\r\nVă mulțumim pentru înțelegere și vă așteptăm cu drag la bibliotecă!\r\n            \r\n            \r\n            Te așteptăm la bibliotecă pentru returnarea cărților!\r\n            \r\n            \r\n            \r\n                Biblioteca Municipală\r\n                Email: biblioteca@example.com\r\n                Telefon: 0231-123-456\r\n            \r\n        \r\n        \r\n        ', '', '2025-11-26 23:34:10'),
(2, 'USER011', 'intarziere', 'email', 'ioan.fantanaru@gmail.com', '📚 Reminder Returnare Cărți - Biblioteca', '\r\n        \r\n        \r\n            Bună Popa Ion,\r\n            \r\n            Îți trimitem o reamintire legată de cărțile împrumutate de la bibliotecă:\r\n            \r\n            \r\n            \r\n                📕 Sfârşitul zilei mari\r\n                ✍️ Henri stahl\r\n                📅 Împrumutată: 01.09.2025\r\n                ⏰ 86 zile împrumut\r\n            \r\n            \r\n            \r\n                📕 St. Monografia Serviciului Sanitar Veterinar al Municipiului Bucureşti / Const. St. Rădulescu, medic veterinar, Inspector General : item-global-head-2 -->\r\n                ✍️ RĂDULESCU, Const\r\n                📅 Împrumutată: 17.11.2025\r\n                ⏰ 9 zile împrumut\r\n            \r\n            \r\n            \r\n            \r\n                📝 Mesaj de la bibliotecar:\r\n                Dragă cititor,\r\n\r\nAm observat că aveți cărți împrumutate de mai mult de 14 zile. Vă rugăm să returnați cărțile cât mai curând posibil pentru ca și alți cititori să le poată găsi la bibliotecă și să se bucure de ele.\r\n\r\nBiblioteca noastră funcționează pe principiul partajării cunoașterii - returnând cărțile la timp, contribuiți la bunul mers al comunității noastre de lectură.\r\n\r\nVă mulțumim pentru înțelegere și vă așteptăm cu drag la bibliotecă!\r\n            \r\n            \r\n            Te așteptăm la bibliotecă pentru returnarea cărților!\r\n            \r\n            \r\n            \r\n                Biblioteca Municipală\r\n                Email: biblioteca@example.com\r\n                Telefon: 0231-123-456\r\n            \r\n        \r\n        \r\n        ', 'trimis', '2025-11-26 23:38:18'),
(3, 'USER011', 'intarziere', 'email', 'coco.babu@gmail.com', '📚 Reminder Returnare Cărți - Biblioteca', '\n        \n        \n            Bună Popa Ion,\n            \n            Îți trimitem o reamintire legată de cărțile împrumutate de la bibliotecă:\n            \n            \n            \n                📕 Sfârşitul zilei mari\n                ✍️ Henri stahl\n                📅 Împrumutată: 01.09.2025\n                ⏰ 88 zile împrumut\n            \n            \n            \n                📕 St. Monografia Serviciului Sanitar Veterinar al Municipiului Bucureşti / Const. St. Rădulescu, medic veterinar, Inspector General : item-global-head-2 -->\n                ✍️ RĂDULESCU, Const\n                📅 Împrumutată: 17.11.2025\n                ⏰ 11 zile împrumut\n            \n            \n            \n            \n                📝 Mesaj de la bibliotecar:\n                Dragă cititor,\r\n\r\nAm observat că aveți cărți împrumutate de mai mult de 14 zile. Vă rugăm să returnați cărțile cât mai curând posibil pentru ca și alți cititori să le poată găsi la bibliotecă și să se bucure de ele.\r\n\r\nBiblioteca noastră funcționează pe principiul partajării cunoașterii - returnând cărțile la timp, contribuiți la bunul mers al comunității noastre de lectură.\r\n\r\nVă mulțumim pentru înțelegere și vă așteptăm cu drag la bibliotecă!\n            \n            \n            Te așteptăm la bibliotecă pentru returnarea cărților!\n            \n            \n            \n                Biblioteca Municipală\n                Email: biblioteca@example.com\n                Telefon: 0231-123-456\n            \n        \n        \n        ', 'trimis', '2025-11-28 01:15:05');

-- --------------------------------------------------------

--
-- Table structure for table `sesiuni_biblioteca`
--

CREATE TABLE `sesiuni_biblioteca` (
  `id` int(11) NOT NULL,
  `cod_cititor` varchar(50) NOT NULL,
  `data` date NOT NULL,
  `ora_intrare` time NOT NULL,
  `timestamp_intrare` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `sesiuni_biblioteca`
--

INSERT INTO `sesiuni_biblioteca` (`id`, `cod_cititor`, `data`, `ora_intrare`, `timestamp_intrare`) VALUES
(1, 'USER001', '2025-11-04', '09:15:00', '2025-11-04 07:15:00'),
(3, 'USER003', '2025-11-04', '11:00:00', '2025-11-04 09:00:00'),
(4, 'USER004', '2025-11-04', '14:20:00', '2025-11-04 12:20:00'),
(5, 'USER001', '2025-11-05', '08:45:00', '2025-11-05 06:45:00'),
(7, 'USER005', '2025-11-05', '13:15:00', '2025-11-05 11:15:00'),
(9, 'USER001', '2025-11-06', '09:00:00', '2025-11-06 07:00:00'),
(10, 'USER003', '2025-11-06', '10:45:00', '2025-11-06 08:45:00'),
(13, 'USER001', '2025-11-07', '08:30:00', '2025-11-07 06:30:00'),
(14, 'USER004', '2025-11-07', '09:15:00', '2025-11-07 07:15:00'),
(15, 'USER005', '2025-11-07', '12:00:00', '2025-11-07 10:00:00'),
(18, 'USER001', '2025-11-08', '09:30:00', '2025-11-08 07:30:00'),
(19, 'USER003', '2025-11-08', '10:00:00', '2025-11-08 08:00:00'),
(21, 'USER0070', '2025-11-08', '14:30:00', '2025-11-08 12:30:00'),
(22, 'USER001', '2025-11-11', '08:45:00', '2025-11-11 06:45:00'),
(24, 'USER003', '2025-11-11', '10:30:00', '2025-11-11 08:30:00'),
(25, 'USER004', '2025-11-11', '11:45:00', '2025-11-11 09:45:00'),
(26, 'USER005', '2025-11-11', '13:00:00', '2025-11-11 11:00:00'),
(28, 'USER001', '2025-11-12', '09:15:00', '2025-11-12 07:15:00'),
(30, 'USER003', '2025-11-12', '11:30:00', '2025-11-12 09:30:00'),
(33, 'USER001', '2025-11-13', '08:30:00', '2025-11-13 06:30:00'),
(34, 'USER004', '2025-11-13', '09:45:00', '2025-11-13 07:45:00'),
(36, 'USER005', '2025-11-13', '12:15:00', '2025-11-13 10:15:00'),
(38, 'USER001', '2025-11-14', '09:00:00', '2025-11-14 07:00:00'),
(40, 'USER003', '2025-11-14', '10:45:00', '2025-11-14 08:45:00'),
(42, 'USER0070', '2025-11-14', '13:00:00', '2025-11-14 11:00:00'),
(43, 'USER004', '2025-11-14', '15:30:00', '2025-11-14 13:30:00'),
(44, 'USER001', '2025-11-15', '08:45:00', '2025-11-15 06:45:00'),
(46, 'USER003', '2025-11-15', '10:00:00', '2025-11-15 08:00:00'),
(47, 'USER005', '2025-11-15', '11:30:00', '2025-11-15 09:30:00'),
(49, 'USER001', '2025-11-15', '15:00:00', '2025-11-15 13:00:00'),
(50, 'USER001', '2025-11-16', '08:30:00', '2025-11-16 06:30:00'),
(52, 'USER003', '2025-11-16', '10:30:00', '2025-11-16 08:30:00'),
(53, 'USER004', '2025-11-16', '11:00:00', '2025-11-16 09:00:00'),
(55, 'USER005', '2025-11-16', '11:29:03', '2025-11-16 09:29:03'),
(56, 'USER011', '2025-11-17', '19:19:48', '2025-11-17 17:19:48'),
(57, 'USER011', '2025-11-17', '19:21:48', '2025-11-17 17:21:48'),
(58, 'USER011', '2025-11-17', '19:21:57', '2025-11-17 17:21:57'),
(59, 'USER011', '2025-11-17', '19:24:37', '2025-11-17 17:24:37'),
(60, 'USER011', '2025-11-17', '19:27:22', '2025-11-17 17:27:22'),
(61, 'USER011', '2025-11-17', '19:28:21', '2025-11-17 17:28:21'),
(62, 'USER011', '2025-11-17', '19:31:08', '2025-11-17 17:31:08'),
(63, 'USER011', '2025-11-17', '19:34:34', '2025-11-17 17:34:34'),
(64, 'USER011', '2025-11-17', '19:34:50', '2025-11-17 17:34:50'),
(65, 'USER011', '2025-11-17', '19:37:01', '2025-11-17 17:37:01'),
(66, 'USER011', '2025-11-17', '19:37:21', '2025-11-17 17:37:21'),
(67, 'USER011', '2025-11-17', '19:46:05', '2025-11-17 17:46:05'),
(68, 'USER011', '2025-11-17', '19:52:21', '2025-11-17 17:52:21'),
(69, 'USER011', '2025-11-18', '21:51:11', '2025-11-18 19:51:11'),
(70, 'USER011', '2025-11-18', '22:02:25', '2025-11-18 20:02:25'),
(71, '14016038', '2025-11-25', '18:15:22', '2025-11-25 16:15:22'),
(72, '14016038', '2025-11-25', '18:15:33', '2025-11-25 16:15:33'),
(73, '14016038', '2025-11-26', '06:28:12', '2025-11-26 04:28:12'),
(74, '14016038', '2025-11-26', '06:34:39', '2025-11-26 04:34:39'),
(75, '110000000010', '2025-11-26', '07:01:52', '2025-11-26 05:01:52'),
(76, '130000000018', '2025-11-26', '07:07:17', '2025-11-26 05:07:17'),
(77, '130000000018', '2025-11-26', '07:10:02', '2025-11-26 05:10:02'),
(78, '130000000018', '2025-11-26', '07:10:25', '2025-11-26 05:10:25'),
(79, 'USER909', '2025-11-28', '18:12:11', '2025-11-28 16:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `sesiuni_utilizatori`
--

CREATE TABLE `sesiuni_utilizatori` (
  `id` int(11) NOT NULL,
  `cod_cititor` varchar(50) NOT NULL,
  `timestamp_start` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Momentul c??nd utilizatorul a fost scanat',
  `timestamp_ultima_actiune` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Momentul ultimei ac??iuni (scanare carte)',
  `status` enum('activ','expirat','inchis') DEFAULT 'activ' COMMENT 'Statusul sesiunii',
  `numar_carti_scanate` int(11) DEFAULT 0 COMMENT 'Num??rul de c??r??i scanate ??n aceast?? sesiune'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `sesiuni_utilizatori`
--

INSERT INTO `sesiuni_utilizatori` (`id`, `cod_cititor`, `timestamp_start`, `timestamp_ultima_actiune`, `status`, `numar_carti_scanate`) VALUES
(1, 'USER011', '2025-11-18 19:51:11', '2025-11-18 19:55:18', 'expirat', 0),
(2, 'USER011', '2025-11-18 20:02:25', '2025-11-18 20:05:10', 'expirat', 0);

-- --------------------------------------------------------

--
-- Table structure for table `statute_carti`
--

CREATE TABLE `statute_carti` (
  `cod_statut` varchar(2) NOT NULL,
  `nume_statut` varchar(100) NOT NULL,
  `poate_imprumuta_acasa` tinyint(1) DEFAULT 0,
  `poate_imprumuta_sala` tinyint(1) DEFAULT 0,
  `durata_imprumut_zile` int(11) DEFAULT 14,
  `descriere` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statute_carti`
--

INSERT INTO `statute_carti` (`cod_statut`, `nume_statut`, `poate_imprumuta_acasa`, `poate_imprumuta_sala`, `durata_imprumut_zile`, `descriere`) VALUES
('01', 'Pentru împrumut acasă', 1, 0, 14, 'Se poate împrumuta acasă - durată standard 14 zile'),
('02', 'Se împr. numai la sală', 0, 1, 0, 'Se imprumuta doar la sala de lectură - nu se poate lua acasă'),
('03', 'Colecții speciale - sală 1 zi', 0, 1, 1, 'Colecții speciale - se imprumuta doar sala pentru 1 zi'),
('04', 'Nu există fizic', 0, 0, 0, 'Nu exista fizic cartea - deci nu se poate împrumuta'),
('05', 'Împrumut scurt 5 zile', 1, 0, 5, 'Se imprumuta doar 5 zile - împrumut scurt'),
('06', 'Regim special 6 luni - 1 an', 1, 0, 180, 'Regim special pentru cărți - se pot împrumuta pe o perioadă mare de timp 6 luni, maxim 1 an'),
('08', 'Ne circulat', 0, 0, 0, 'Nu se imprumuta - carte ne circulată'),
('90', 'În achiziție - depozit', 0, 0, 0, 'Cartea a fost primita, dar e inca in depozit, nu a ajuns la raft');

-- --------------------------------------------------------

--
-- Table structure for table `statute_cititori`
--

CREATE TABLE `statute_cititori` (
  `cod_statut` varchar(2) NOT NULL,
  `nume_statut` varchar(100) NOT NULL,
  `limita_depozit_carte` int(11) DEFAULT 0,
  `limita_depozit_periodice` int(11) DEFAULT 0,
  `limita_sala_lectura` int(11) DEFAULT 0,
  `limita_colectii_speciale` int(11) DEFAULT 0,
  `limita_totala` int(11) DEFAULT 6,
  `descriere` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statute_cititori`
--

INSERT INTO `statute_cititori` (`cod_statut`, `nume_statut`, `limita_depozit_carte`, `limita_depozit_periodice`, `limita_sala_lectura`, `limita_colectii_speciale`, `limita_totala`, `descriere`) VALUES
('11', 'Personal Științific Academie', 0, 0, 0, 0, 10, 'Personal științific al Academiei Române'),
('12', 'Bibliotecari BARI', 0, 0, 0, 0, 15, 'Bibliotecari din rețeaua BARI'),
('13', 'Angajați ARFI', 0, 0, 0, 0, 8, 'Angajați ARFI'),
('14', 'Nespecifici cu domiciliu în Iași', 0, 0, 0, 0, 4, 'Cititori nespecificați cu domiciliu în Iași'),
('15', 'Nespecifici fără domiciliu în Iași', 0, 0, 0, 0, 2, 'Cititori nespecificați fără domiciliu în Iași'),
('16', 'Personal departamente', 0, 0, 0, 0, 6, 'Personal din departamente'),
('17', 'ILL - Împrumut interbibliotecar', 0, 0, 0, 0, 20, 'Împrumut interbibliotecar');

-- --------------------------------------------------------

--
-- Table structure for table `tracking_sesiuni`
--

CREATE TABLE `tracking_sesiuni` (
  `id` int(11) NOT NULL,
  `cod_cititor` varchar(50) NOT NULL,
  `tip_actiune` enum('scanare_permis','scanare_carte_imprumut','scanare_carte_returnare','sesiune_expirata','sesiune_inchisa') NOT NULL,
  `cod_carte` varchar(50) DEFAULT NULL COMMENT 'NULL pentru scanare_permis, codul c??r??ii pentru scanare/returnare',
  `data_ora` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data ??i ora exact?? a ac??iunii',
  `data` date GENERATED ALWAYS AS (cast(`data_ora` as date)) STORED COMMENT 'Data pentru rapoarte zilnice',
  `ora` time GENERATED ALWAYS AS (cast(`data_ora` as time)) STORED COMMENT 'Ora pentru rapoarte',
  `sesiune_id` int(11) DEFAULT NULL COMMENT 'ID-ul sesiunii din sesiuni_utilizatori',
  `detalii` text DEFAULT NULL COMMENT 'Detalii suplimentare (JSON sau text)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_romanian_ci;

--
-- Dumping data for table `tracking_sesiuni`
--

INSERT INTO `tracking_sesiuni` (`id`, `cod_cititor`, `tip_actiune`, `cod_carte`, `data_ora`, `sesiune_id`, `detalii`) VALUES
(1, 'USER011', 'sesiune_expirata', NULL, '2025-11-18 19:42:19', NULL, '{\"motiv\":\"Timeout 30 secunde sau 5 minute\"}'),
(2, 'USER011', 'scanare_permis', NULL, '2025-11-18 19:51:11', 1, '{\"nume\":\"Nou azi 22ssssssssss\",\"prenume\":\"Ion\"}'),
(3, 'USER014', 'sesiune_expirata', NULL, '2025-11-18 20:02:03', 1, '{\"motiv\":\"Timeout 30 secunde sau 5 minute\"}'),
(4, 'USER011', 'scanare_permis', NULL, '2025-11-18 20:02:25', 2, '{\"nume\":\"Nou azi 22ssssssssss\",\"prenume\":\"Ion\"}');

-- --------------------------------------------------------

--
-- Table structure for table `utilizatori`
--

CREATE TABLE `utilizatori` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nume` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `activ` tinyint(1) DEFAULT 1,
  `data_creare` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_autentificare` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utilizatori`
--

INSERT INTO `utilizatori` (`id`, `username`, `password_hash`, `nume`, `email`, `activ`, `data_creare`, `ultima_autentificare`) VALUES
(1, 'larisa2025', '$2y$10$QJM2dCummJLYJ1qzsGqKsu.yv2f5iXlCMPKySjkq2CQTR1/I/L7Vy', 'Larisa', NULL, 1, '2025-11-19 16:55:37', '2025-11-28 17:13:39'),
(2, 'bunica20', '$2y$10$JZLQNnkzdeEAnNGROV.RPeV2odgatcSm2mJOnoRDIvy65X5eSFVfG', 'Bunica', NULL, 0, '2025-11-19 16:55:37', '2025-11-19 16:55:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carti`
--
ALTER TABLE `carti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cod_bare` (`cod_bare`),
  ADD KEY `idx_cod_bare` (`cod_bare`),
  ADD KEY `idx_locatie` (`raft`,`nivel`,`pozitie`),
  ADD KEY `idx_cota` (`cota`),
  ADD KEY `idx_statut_carte` (`statut`);

--
-- Indexes for table `cititori`
--
ALTER TABLE `cititori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cod_bare` (`cod_bare`),
  ADD KEY `idx_cod_bare` (`cod_bare`),
  ADD KEY `idx_ultima_vizare` (`ultima_vizare`),
  ADD KEY `idx_statut` (`statut`);

--
-- Indexes for table `imprumuturi`
--
ALTER TABLE `imprumuturi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cod_carte` (`cod_carte`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_cititor` (`cod_cititor`);

--
-- Indexes for table `istoric_locatii`
--
ALTER TABLE `istoric_locatii`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cod_carte` (`cod_carte`);

--
-- Indexes for table `modele_email`
--
ALTER TABLE `modele_email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tip` (`tip_notificare`),
  ADD KEY `idx_activ` (`activ`);

--
-- Indexes for table `notificari`
--
ALTER TABLE `notificari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cititor` (`cod_cititor`),
  ADD KEY `idx_data` (`data_trimitere`);

--
-- Indexes for table `sesiuni_biblioteca`
--
ALTER TABLE `sesiuni_biblioteca`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cod_cititor` (`cod_cititor`),
  ADD KEY `idx_data` (`data`);

--
-- Indexes for table `sesiuni_utilizatori`
--
ALTER TABLE `sesiuni_utilizatori`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cititor` (`cod_cititor`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_timestamp` (`timestamp_ultima_actiune`);

--
-- Indexes for table `statute_carti`
--
ALTER TABLE `statute_carti`
  ADD PRIMARY KEY (`cod_statut`),
  ADD KEY `idx_cod_statut` (`cod_statut`);

--
-- Indexes for table `statute_cititori`
--
ALTER TABLE `statute_cititori`
  ADD PRIMARY KEY (`cod_statut`),
  ADD KEY `idx_cod_statut` (`cod_statut`);

--
-- Indexes for table `tracking_sesiuni`
--
ALTER TABLE `tracking_sesiuni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cititor` (`cod_cititor`),
  ADD KEY `idx_data_ora` (`data_ora`),
  ADD KEY `idx_data` (`data`),
  ADD KEY `idx_tip_actiune` (`tip_actiune`),
  ADD KEY `idx_sesiune` (`sesiune_id`);

--
-- Indexes for table `utilizatori`
--
ALTER TABLE `utilizatori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_activ` (`activ`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carti`
--
ALTER TABLE `carti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `cititori`
--
ALTER TABLE `cititori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `imprumuturi`
--
ALTER TABLE `imprumuturi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `istoric_locatii`
--
ALTER TABLE `istoric_locatii`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modele_email`
--
ALTER TABLE `modele_email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notificari`
--
ALTER TABLE `notificari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sesiuni_biblioteca`
--
ALTER TABLE `sesiuni_biblioteca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `sesiuni_utilizatori`
--
ALTER TABLE `sesiuni_utilizatori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tracking_sesiuni`
--
ALTER TABLE `tracking_sesiuni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `utilizatori`
--
ALTER TABLE `utilizatori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `imprumuturi`
--
ALTER TABLE `imprumuturi`
  ADD CONSTRAINT `imprumuturi_ibfk_1` FOREIGN KEY (`cod_cititor`) REFERENCES `cititori` (`cod_bare`),
  ADD CONSTRAINT `imprumuturi_ibfk_2` FOREIGN KEY (`cod_carte`) REFERENCES `carti` (`cod_bare`);

--
-- Constraints for table `istoric_locatii`
--
ALTER TABLE `istoric_locatii`
  ADD CONSTRAINT `istoric_locatii_ibfk_1` FOREIGN KEY (`cod_carte`) REFERENCES `carti` (`cod_bare`);

--
-- Constraints for table `sesiuni_biblioteca`
--
ALTER TABLE `sesiuni_biblioteca`
  ADD CONSTRAINT `sesiuni_biblioteca_ibfk_1` FOREIGN KEY (`cod_cititor`) REFERENCES `cititori` (`cod_bare`);

--
-- Constraints for table `sesiuni_utilizatori`
--
ALTER TABLE `sesiuni_utilizatori`
  ADD CONSTRAINT `sesiuni_utilizatori_ibfk_1` FOREIGN KEY (`cod_cititor`) REFERENCES `cititori` (`cod_bare`) ON DELETE CASCADE;

--
-- Constraints for table `tracking_sesiuni`
--
ALTER TABLE `tracking_sesiuni`
  ADD CONSTRAINT `tracking_sesiuni_ibfk_1` FOREIGN KEY (`cod_cititor`) REFERENCES `cititori` (`cod_bare`) ON DELETE CASCADE,
  ADD CONSTRAINT `tracking_sesiuni_ibfk_2` FOREIGN KEY (`sesiune_id`) REFERENCES `sesiuni_utilizatori` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
