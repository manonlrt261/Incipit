-- MySQL dump 10.13  Distrib 8.4.11, for macos26.6 (arm64)
--
-- Host: localhost    Database: incipit
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auteur`
--

DROP TABLE IF EXISTS `auteur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auteur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_affichage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact`
--

DROP TABLE IF EXISTS `contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evenement_outbox`
--

DROP TABLE IF EXISTS `evenement_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evenement_outbox` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifiant_evenement` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_evenement` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `donnees` json NOT NULL,
  `date_creation` datetime NOT NULL,
  `date_transmission` datetime DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en attente',
  `nombre_tentatives` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_OUTBOX_EVENEMENT` (`identifiant_evenement`),
  KEY `IDX_OUTBOX_STATUT` (`statut`),
  CONSTRAINT `CHK_OUTBOX_STATUT` CHECK ((`statut` in (_utf8mb4'en attente',_utf8mb4'transmis',_utf8mb4'erreur'))),
  CONSTRAINT `CHK_OUTBOX_TENTATIVES` CHECK ((`nombre_tentatives` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exemplaire`
--

DROP TABLE IF EXISTS `exemplaire`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exemplaire` (
  `id` int NOT NULL AUTO_INCREMENT,
  `livre_id` int NOT NULL,
  `date_acquisition` date DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `provenance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emplacement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_EXEMPLAIRE_LIVRE` (`livre_id`),
  CONSTRAINT `FK_EXEMPLAIRE_LIVRE` FOREIGN KEY (`livre_id`) REFERENCES `livre` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `livre`
--

DROP TABLE IF EXISTS `livre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livre` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sous_titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn13` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn10` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resume` text COLLATE utf8mb4_unicode_ci,
  `date_publication` date DEFAULT NULL,
  `nombre_pages` int DEFAULT NULL,
  `langue` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_couverture` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL,
  `date_modification` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `livre_auteur`
--

DROP TABLE IF EXISTS `livre_auteur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livre_auteur` (
  `livre_id` int NOT NULL,
  `auteur_id` int NOT NULL,
  PRIMARY KEY (`livre_id`,`auteur_id`),
  KEY `IDX_LIVRE_AUTEUR_AUTEUR` (`auteur_id`),
  CONSTRAINT `FK_LIVRE_AUTEUR_AUTEUR` FOREIGN KEY (`auteur_id`) REFERENCES `auteur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_LIVRE_AUTEUR_LIVRE` FOREIGN KEY (`livre_id`) REFERENCES `livre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parametre_confidentialite`
--

DROP TABLE IF EXISTS `parametre_confidentialite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parametre_confidentialite` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `afficher_livres_favoris` tinyint(1) NOT NULL DEFAULT '1',
  `afficher_auteurs_favoris` tinyint(1) NOT NULL DEFAULT '1',
  `afficher_wishlist` tinyint(1) NOT NULL DEFAULT '1',
  `date_modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_CONFIDENTIALITE_UTILISATEUR` (`utilisateur_id`),
  CONSTRAINT `FK_CONFIDENTIALITE_UTILISATEUR` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pret`
--

DROP TABLE IF EXISTS `pret`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pret` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exemplaire_id` int NOT NULL,
  `contact_id` int NOT NULL,
  `date_pret` date NOT NULL,
  `date_retour_prevue` date DEFAULT NULL,
  `date_retour_effective` date DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en cours',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_PRET_EXEMPLAIRE` (`exemplaire_id`),
  KEY `IDX_PRET_CONTACT` (`contact_id`),
  CONSTRAINT `FK_PRET_CONTACT` FOREIGN KEY (`contact_id`) REFERENCES `contact` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `FK_PRET_EXEMPLAIRE` FOREIGN KEY (`exemplaire_id`) REFERENCES `exemplaire` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `CHK_PRET_STATUT` CHECK ((`statut` in (_utf8mb4'en cours',_utf8mb4'terminé')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session_lecture`
--

DROP TABLE IF EXISTS `session_lecture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_lecture` (
  `id` int NOT NULL AUTO_INCREMENT,
  `livre_id` int NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'à lire',
  `note` decimal(2,1) DEFAULT NULL,
  `avis` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL,
  `date_modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_SESSION_LECTURE_LIVRE` (`livre_id`),
  CONSTRAINT `FK_SESSION_LECTURE_LIVRE` FOREIGN KEY (`livre_id`) REFERENCES `livre` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `CHK_SESSION_LECTURE_NOTE` CHECK (((`note` is null) or ((`note` between 0 and 5) and (((`note` * 10) % 5) = 0)))),
  CONSTRAINT `CHK_SESSION_LECTURE_STATUT` CHECK ((`statut` in (_utf8mb4'à lire',_utf8mb4'en cours',_utf8mb4'terminé',_utf8mb4'abandonné',_utf8mb4'Pas commencé',_utf8mb4'En pause')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `souhait`
--

DROP TABLE IF EXISTS `souhait`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `souhait` (
  `id` int NOT NULL AUTO_INCREMENT,
  `livre_id` int NOT NULL,
  `priorite` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prix_estime` decimal(10,2) DEFAULT NULL,
  `lien_achat` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime NOT NULL,
  `date_acquisition` date DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'souhaité',
  PRIMARY KEY (`id`),
  KEY `IDX_SOUHAIT_LIVRE` (`livre_id`),
  CONSTRAINT `FK_SOUHAIT_LIVRE` FOREIGN KEY (`livre_id`) REFERENCES `livre` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `CHK_SOUHAIT_STATUT` CHECK ((`statut` in (_utf8mb4'souhaité',_utf8mb4'acquis')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL,
  `date_modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_UTILISATEUR_EMAIL` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-14 14:48:58
