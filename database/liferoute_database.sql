-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: liferoute
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `booking_chat_messages`
--

DROP TABLE IF EXISTS `booking_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `booking_reference` varchar(32) NOT NULL,
  `sender` varchar(32) NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_chat_messages_booking_id_index` (`booking_id`),
  KEY `booking_chat_messages_booking_reference_index` (`booking_reference`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_chat_messages`
--

LOCK TABLES `booking_chat_messages` WRITE;
/*!40000 ALTER TABLE `booking_chat_messages` DISABLE KEYS */;
INSERT INTO `booking_chat_messages` VALUES (1,12792,'BK-12792','user','Patient is conscious, we are outside Gate 2.','2026-11-13 02:41:50','2026-11-13 02:41:50'),(2,12792,'BK-12792','driver','Ambulance arriving in 2 minutes with oxygen ready.','2026-11-13 02:41:50','2026-11-13 02:41:50'),(3,34378,'BK-34378','user','Patient is at Platform 3 entrance.','2026-11-13 02:42:36','2026-11-13 02:42:36'),(4,34378,'BK-34378','driver','Siren on, arriving in 90 seconds.','2026-11-13 02:42:37','2026-11-13 02:42:37'),(5,59176,'BK-59176','driver','Arriving in 2 minutes.','2026-11-14 04:18:45','2026-11-14 04:18:45');
/*!40000 ALTER TABLE `booking_chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_declines`
--

DROP TABLE IF EXISTS `booking_declines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_declines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `driver_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_declines_booking_id_driver_id_unique` (`booking_id`,`driver_id`),
  KEY `booking_declines_driver_id_foreign` (`driver_id`),
  CONSTRAINT `booking_declines_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_declines_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_declines`
--

LOCK TABLES `booking_declines` WRITE;
/*!40000 ALTER TABLE `booking_declines` DISABLE KEYS */;
INSERT INTO `booking_declines` VALUES (1,57662,9,'2026-11-13 01:16:12','2026-11-13 01:16:12'),(2,95917,9,'2026-11-15 08:52:27','2026-11-15 08:52:27'),(3,54356,9,'2026-11-16 01:38:37','2026-11-16 01:38:37'),(4,90602,9,'2026-11-16 01:39:14','2026-11-16 01:39:14'),(5,28468,9,'2026-11-16 01:39:17','2026-11-16 01:39:17'),(6,44389,9,'2026-11-16 01:39:22','2026-11-16 01:39:22'),(7,52718,9,'2026-11-16 01:39:25','2026-11-16 01:39:25'),(8,67911,9,'2026-11-16 01:39:30','2026-11-16 01:39:30');
/*!40000 ALTER TABLE `booking_declines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL,
  `booking_reference` varchar(32) NOT NULL,
  `reference_number` varchar(32) NOT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(48) NOT NULL DEFAULT 'finding_ambulance',
  `pickup_lat` double NOT NULL DEFAULT 12.9716,
  `pickup_lng` double NOT NULL DEFAULT 77.5946,
  `pickup_address` varchar(255) NOT NULL DEFAULT 'Current GPS Position',
  `pickup_landmark` varchar(255) NOT NULL DEFAULT '',
  `callback_phone` varchar(32) NOT NULL DEFAULT '',
  `patient_name` varchar(255) NOT NULL DEFAULT '',
  `emergency_category` varchar(100) NOT NULL DEFAULT 'General Emergency',
  `notes` text DEFAULT NULL,
  `essential_instructions` text DEFAULT NULL,
  `destination_hospital` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`destination_hospital`)),
  `destination_address` varchar(255) NOT NULL DEFAULT 'Casualty & Emergency Bay',
  `destination_latitude` double NOT NULL DEFAULT 12.9866,
  `destination_longitude` double NOT NULL DEFAULT 77.6066,
  `eta_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `distance_km` double NOT NULL DEFAULT 0,
  `distance_meters` double NOT NULL DEFAULT 0,
  `cancel_reason` text DEFAULT NULL,
  `handover_notes` text DEFAULT NULL,
  `duration_minutes` int(10) unsigned NOT NULL DEFAULT 18,
  `earnings` double NOT NULL DEFAULT 350,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `arrived_pickup_at` timestamp NULL DEFAULT NULL,
  `patient_picked_up_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_booking_reference_unique` (`booking_reference`),
  UNIQUE KEY `bookings_reference_number_unique` (`reference_number`),
  KEY `bookings_driver_id_foreign` (`driver_id`),
  KEY `bookings_status_index` (`status`),
  CONSTRAINT `bookings_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (12270,'BK-12270','#LR-12270',9,'completed',11.0376488,76.9193111,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 8728304510','Tyson','Pediatric / Child','Requires pediatric life-support stretcher, infant airway tools & pediatric paramedic.','Requires pediatric life-support stretcher, infant airway tools & pediatric paramedic.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.0526488,\"lng\":76.9313111,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',11.0526488,76.9313111,0,2.86,2860,NULL,'Handover completed at Emergency Casualty.',114,350,'2026-11-15 06:57:08','2026-11-15 06:57:30','2026-11-15 06:57:32','2026-11-16 07:24:32','2026-11-15 06:56:52','2026-11-15 08:52:02'),(12792,'BK-12792','#LR-12792',9,'completed',11.0168,76.9558,'Gandhipuram Bus Stand, Coimbatore','','+919876543210','Kavitha Raman','Cardiac','Severe chest pain','Severe chest pain','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.0425,\"lng\":77.0375,\"distance_km\":12.1,\"estimated_drive_time\":\"21 min\"}','Casualty & Emergency Bay',11.0425,77.0375,0,12.14,12140,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-13 03:11:08','2026-11-13 03:11:42','2026-11-13 03:11:47','2026-11-16 07:24:32','2026-11-13 02:41:50','2026-11-13 03:12:17'),(13461,'BK-13461','#LR-13461',9,'cancelled',11.0376469,76.9193331,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','TYSON 93','Severe Burn Injury','Requires sterile burn trauma dressing sheets, IV fluid resuscitation & pain management.','Requires sterile burn trauma dressing sheets, IV fluid resuscitation & pain management.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,16,8.85,8850,'Patient stabilized / alternate transport',NULL,18,350,'2026-11-13 05:14:43','2026-11-13 05:15:27','2026-11-13 05:15:33',NULL,'2026-11-13 05:11:22','2026-11-13 05:33:05'),(18017,'BK-18017','#LR-18017',9,'completed',11.037623,76.9193122,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 8128304510','Tyson','Pregnancy / Maternity','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.84,8840,NULL,'Handover completed at Emergency Casualty.',53,350,'2026-11-14 03:04:31','2026-11-14 03:04:43','2026-11-14 03:04:50','2026-11-16 07:24:32','2026-11-14 03:04:21','2026-11-14 03:58:01'),(20235,'BK-20235','#LR-20235',9,'completed',11.0376467,76.9193324,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tysonpaul','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"royal-care-hospital\",\"name\":\"Royal Care Super Speciality Hospital\",\"address\":\"1\\/520, L&T Bypass Road, Neelambur, Coimbatore\",\"lat\":11.065,\"lng\":77.072,\"distance_km\":21.8,\"estimated_drive_time\":\"38 min\"}','1/520, L&T Bypass Road, Neelambur, Coimbatore',11.065,77.072,0,21.83,21830,NULL,'Handover completed at Emergency Casualty.',1207,350,'2026-11-13 06:44:39','2026-11-13 06:44:50','2026-11-13 06:44:55','2026-11-16 07:24:32','2026-11-13 06:44:34','2026-11-14 02:52:19'),(25644,'BK-25644','#LR-25644',16,'completed',11.0168,76.9558,'Gandhipuram, Coimbatore','','+919876543210','Raman','Cardiac / Heart','','Emergency Cardiac / Heart. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.0318,\"lng\":76.9678,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',11.0318,76.9678,0,0,0,NULL,NULL,18,350,'2026-11-16 00:52:18',NULL,NULL,'2026-11-16 07:24:32','2026-11-16 00:51:13','2026-11-16 00:52:18'),(28468,'BK-28468','#LR-28468',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-16 00:23:17','2026-11-16 00:23:17'),(31279,'BK-31279','#LR-31279',9,'completed',11.0376488,76.9193331,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tyson','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.84,8840,NULL,'Handover completed at Emergency Casualty.',141,350,'2026-11-13 03:12:30','2026-11-13 05:33:36','2026-11-13 05:33:38','2026-11-16 07:24:32','2026-11-13 03:12:25','2026-11-13 05:33:46'),(34378,'BK-34378','#LR-34378',9,'completed',11.0168,76.9558,'Gandhipuram Bus Stand, Cross Cut Road, Coimbatore','','+919876543210','Suresh Kumar','Cardiac','Severe chest pain and difficulty breathing','Severe chest pain and difficulty breathing','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.0425,\"lng\":77.0375,\"distance_km\":12.1,\"estimated_drive_time\":\"21 min\"}','Casualty & Emergency Bay',11.0425,77.0375,0,12.14,12140,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-13 02:42:36',NULL,NULL,'2026-11-16 07:24:32','2026-11-13 02:42:36','2026-11-13 02:42:37'),(34644,'BK-34644','#LR-34644',9,'completed',11.0376482,76.9193339,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Emergency Patient','Pregnancy / Maternity','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.84,8840,NULL,'Handover completed at Emergency Casualty.',5,350,'2026-11-14 02:53:03','2026-11-14 02:53:43','2026-11-14 02:57:36','2026-11-16 07:24:32','2026-11-14 02:52:46','2026-11-14 02:58:06'),(35821,'BK-35821','#LR-35821',9,'completed',11.0318,76.9437,'Acquiring live GPS address...','GPS Pinpoint Location','+91 9789510300','Kumar','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":5.8,\"estimated_drive_time\":\"10 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,5.77,5770,NULL,'Handover completed at Emergency Casualty.',1000,350,'2026-11-15 08:59:01','2026-11-16 01:39:01','2026-11-16 01:39:02','2026-11-16 07:24:32','2026-11-15 08:54:18','2026-11-16 01:39:08'),(36236,'BK-36236','#LR-36236',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:56:25','2026-11-16 02:26:18','2026-11-16 02:26:18'),(36300,'BK-36300','#LR-36300',9,'completed',11.0377382,76.9193038,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','TYSON 93','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.9,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.86,8860,NULL,'Handover completed at Emergency Casualty.',36,350,'2026-11-13 05:34:12','2026-11-13 06:10:02','2026-11-13 06:10:14','2026-11-16 07:24:32','2026-11-13 05:34:06','2026-11-13 06:10:20'),(42078,'BK-42078','#LR-42078',9,'completed',11.037649700796,76.919278319073,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Emergency Patient','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.9,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.85,8850,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-14 07:47:17','2026-11-14 07:47:34','2026-11-14 07:47:37','2026-11-16 07:24:32','2026-11-14 07:47:10','2026-11-14 07:48:00'),(44389,'BK-44389','#LR-44389',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-16 00:07:40','2026-11-16 00:07:40'),(52718,'BK-52718','#LR-52718',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-15 09:03:22','2026-11-15 09:03:22'),(54356,'BK-54356','#LR-54356',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-16 01:09:36','2026-11-16 01:09:36'),(57662,'BK-57662','#LR-57662',NULL,'cancelled',11.0376504,76.919332,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tyson','Pregnancy / Maternity','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','{\"id\":\"richmond-hospital-cbe\",\"name\":\"Richmond Hospital & Advanced Emergency Trauma\",\"address\":\"Cross Cut Road, 7th Street, Gandhipuram, Coimbatore\",\"lat\":11.0185,\"lng\":76.9675,\"distance_km\":7.4,\"estimated_drive_time\":\"13 min\"}','Cross Cut Road, 7th Street, Gandhipuram, Coimbatore',11.0185,76.9675,0,0,0,'User cancelled',NULL,18,350,NULL,NULL,NULL,NULL,'2026-11-13 01:16:02','2026-11-13 01:16:17'),(57811,'BK-57811','#LR-57811',9,'completed',11.037648,76.9193291,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 8128304510','Tyson','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.84,8840,NULL,'Handover completed at Emergency Casualty.',3,350,'2026-11-14 03:58:37','2026-11-14 03:58:44','2026-11-14 03:58:47','2026-11-16 07:24:32','2026-11-14 03:58:30','2026-11-14 04:01:52'),(59176,'BK-59176','#LR-59176',9,'cancelled',11.0376484,76.9193359,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Matthew','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,16,8.84,8840,'Patient stabilized / alternate transport',NULL,18,350,'2026-11-14 04:17:57','2026-11-14 04:18:02','2026-11-14 04:18:04',NULL,'2026-11-14 04:17:51','2026-11-14 04:19:17'),(59182,'BK-59182','#LR-59182',10,'cancelled',11.0168,76.9558,'Gandhipuram Bus Stand, Coimbatore','Near Clock Tower','+91 98428 11223','Priya Sharma','Cardiac Emergency','Severe chest pain, urgent ambulance required','Severe chest pain, urgent ambulance required','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.0318,\"lng\":76.9678,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',11.0318,76.9678,5,2.71,2710,'Cancelled via Command Interceptor Panel',NULL,18,350,'2026-11-13 03:44:28',NULL,NULL,NULL,'2026-11-13 03:44:06','2026-11-14 03:02:55'),(59448,'BK-59448','#LR-59448',9,'completed',11.0376487,76.9193327,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tyson','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"richmond-hospital-cbe\",\"name\":\"Richmond Hospital & Advanced Emergency Trauma\",\"address\":\"Cross Cut Road, 7th Street, Gandhipuram, Coimbatore\",\"lat\":11.0185,\"lng\":76.9675,\"distance_km\":7.4,\"estimated_drive_time\":\"13 min\"}','Cross Cut Road, 7th Street, Gandhipuram, Coimbatore',11.0185,76.9675,0,7.41,7410,NULL,'Handover completed at Emergency Casualty.',31,350,'2026-11-13 01:16:30','2026-11-13 01:48:06','2026-11-13 01:48:09','2026-11-16 07:24:32','2026-11-13 01:16:22','2026-11-13 01:48:15'),(66413,'BK-66413','#LR-66413',9,'completed',11.0376521,76.9193336,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tysonpaul','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"ashwin-hospital-cbe\",\"name\":\"Ashwin Hospital & Emergency Trauma Centre\",\"address\":\"1, Sathy Road, Gandhipuram, Coimbatore\",\"lat\":11.021,\"lng\":76.962,\"distance_km\":6.6,\"estimated_drive_time\":\"12 min\"}','1, Sathy Road, Gandhipuram, Coimbatore',11.021,76.962,0,6.56,6560,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-13 06:10:39','2026-11-13 06:11:13','2026-11-13 06:11:19','2026-11-16 07:24:32','2026-11-13 06:10:34','2026-11-13 06:11:44'),(67911,'BK-67911','#LR-67911',NULL,'completed',11.027170016694,76.943586093374,'Door No. 63, Ward 12, Chinnappan Street, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9789510300','Muhi','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":5.3,\"estimated_drive_time\":\"10 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,3,1.32,1320,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-15 08:59:13','2026-11-15 08:59:13'),(70652,'BK-70652','#LR-70652',9,'completed',11.0376484,76.9193358,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tyson','Pregnancy / Maternity','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','{\"id\":\"osm-hosp-676200805\",\"name\":\"Sundakamuthur Government Hospital (Government 108)\",\"address\":\"Sundakamuthur Government Hospital, KM 6\\/8 of Coimbatore-Siruvani Road - KM 165\\/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India\",\"lat\":10.955943,\"lng\":76.9241781,\"distance_km\":11.8,\"estimated_drive_time\":\"21 min\"}','Sundakamuthur Government Hospital, KM 6/8 of Coimbatore-Siruvani Road - KM 165/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India',10.955943,76.9241781,0,11.8,11800,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-13 01:18:53','2026-11-13 01:19:08','2026-11-13 01:19:11','2026-11-16 07:24:32','2026-11-13 01:18:38','2026-11-13 01:19:57'),(74543,'BK-74543','#LR-74543',16,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,120,292.24,292240,NULL,NULL,18,350,'2026-11-16 02:03:30',NULL,NULL,'2026-11-16 07:33:45','2026-11-16 02:03:17','2026-11-16 02:03:30'),(86529,'BK-86529','#LR-86529',9,'completed',11.0376493,76.9193358,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','TYSON 93','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"osm-hosp-676200805\",\"name\":\"Sundakamuthur Government Hospital (Government 108)\",\"address\":\"Sundakamuthur Government Hospital, KM 6\\/8 of Coimbatore-Siruvani Road - KM 165\\/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India\",\"lat\":10.955943,\"lng\":76.9241781,\"distance_km\":11.8,\"estimated_drive_time\":\"21 min\"}','Sundakamuthur Government Hospital, KM 6/8 of Coimbatore-Siruvani Road - KM 165/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India',10.955943,76.9241781,0,11.8,11800,NULL,'Handover completed at Emergency Casualty.',67,350,'2026-11-13 04:07:28','2026-11-13 05:11:54','2026-11-13 05:12:04','2026-11-16 07:24:32','2026-11-13 04:06:45','2026-11-13 05:14:28'),(86844,'BK-86844','#LR-86844',9,'completed',11.0376512,76.9193331,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Matthew','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.8,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,8.84,8840,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-14 05:00:32','2026-11-14 05:00:59','2026-11-14 05:01:01','2026-11-16 07:24:32','2026-11-14 05:00:18','2026-11-14 05:01:21'),(87345,'BK-87345','#LR-87345',9,'completed',11.0376474,76.9193202,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 8728304510','Tyson','Accident / Trauma','Requires spinal board, cervical collar, trauma dressings & rapid blood transfusion kit.','Requires spinal board, cervical collar, trauma dressings & rapid blood transfusion kit.','{\"id\":\"ashwin-hospital-cbe\",\"name\":\"Ashwin Hospital & Emergency Trauma Centre\",\"address\":\"1, Sathy Road, Gandhipuram, Coimbatore\",\"lat\":11.021,\"lng\":76.962,\"distance_km\":6.6,\"estimated_drive_time\":\"12 min\"}','1, Sathy Road, Gandhipuram, Coimbatore',11.021,76.962,0,6.57,6570,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-15 06:55:27','2026-11-15 06:55:41','2026-11-15 06:55:43','2026-11-16 07:24:32','2026-11-15 06:55:15','2026-11-15 06:56:02'),(89554,'BK-89554','#LR-89554',9,'completed',11.0376494,76.9193676,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Tyson','Stroke / Neurological','Requires stroke thrombolysis protocol, blood glucose kit & rapid CT referral.','Requires stroke thrombolysis protocol, blood glucose kit & rapid CT referral.','{\"id\":\"kg-hospital\",\"name\":\"KG Hospital & Advanced Trauma Centre\",\"address\":\"Government Arts College Road, Gopalapuram, Coimbatore\",\"lat\":11.0035,\"lng\":76.9642,\"distance_km\":8.1,\"estimated_drive_time\":\"14 min\"}','Government Arts College Road, Gopalapuram, Coimbatore',11.0035,76.9642,0,8.08,8080,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-13 01:20:27','2026-11-13 01:20:37','2026-11-13 01:20:40','2026-11-16 07:24:32','2026-11-13 01:20:22','2026-11-13 01:20:49'),(90602,'BK-90602','#LR-90602',NULL,'completed',12.9716,77.5946,'Current GPS Position','','','','General Emergency','','Emergency General Emergency. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":12.986600000000001,\"lng\":77.6066,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',12.9866,77.6066,0,0,0,NULL,NULL,18,350,NULL,NULL,NULL,'2026-11-16 07:24:32','2026-11-16 01:02:32','2026-11-16 01:02:32'),(95917,'BK-95917','#LR-95917',11,'completed',11.0318,76.9437,'Acquiring live GPS address...','GPS Pinpoint Location','+91 9789510300','Kumar','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":5.8,\"estimated_drive_time\":\"10 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,120,293.75,293750,NULL,NULL,18,350,'2026-11-15 08:52:29','2026-11-15 09:01:10','2026-11-15 09:01:17','2026-11-16 07:24:32','2026-11-15 08:52:23','2026-11-15 09:01:19'),(97389,'BK-97389','#LR-97389',9,'completed',11.037649700796,76.919278319073,'Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001','GPS Pinpoint Location','+91 9840010801','Matthew','Cardiac / Heart','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','{\"id\":\"cmch-coimbatore-108\",\"name\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"lat\":11.0015,\"lng\":76.9695,\"distance_km\":8.9,\"estimated_drive_time\":\"16 min\"}','Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)',11.0015,76.9695,0,0,0,NULL,'Handover completed at Emergency Casualty.',1,350,'2026-11-14 07:05:33','2026-11-14 07:06:16','2026-11-14 07:06:19','2026-11-16 07:24:32','2026-11-14 07:05:28','2026-11-14 07:07:01'),(98578,'BK-98578','#LR-98578',16,'completed',11.0318,76.9437,'Kovilmedu, Coimbatore','','9876543210','Verification Test','Cardiac / Heart','','Emergency Cardiac / Heart. Priority ambulance needed immediately.','{\"id\":\"hosp-default\",\"name\":\"Emergency Trauma Center\",\"address\":\"Casualty & Emergency Bay\",\"lat\":11.046800000000001,\"lng\":76.95570000000001,\"distance_km\":2.9,\"estimated_drive_time\":\"5 min\"}','Casualty & Emergency Bay',11.0468,76.9557,5,2.87,2870,NULL,NULL,18,350,'2026-11-16 02:35:15',NULL,NULL,'2026-11-16 08:05:15','2026-11-16 02:35:15','2026-11-16 02:35:15');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('liferoute-cache-driver-login:127.0.0.1:9840010801','i:4;',1794813160),('liferoute-cache-driver-login:127.0.0.1:9840010801:timer','i:1794813160;',1794813160),('liferoute-cache-driver-login:127.0.0.1:8428307510','i:1;',1794809679),('liferoute-cache-driver-login:127.0.0.1:8428307510:timer','i:1794809679;',1794809679),('liferoute-cache-driver-login:127.0.0.1:9842100108','i:1;',1794810404),('liferoute-cache-driver-login:127.0.0.1:9842100108:timer','i:1794810404;',1794810404),('liferoute-cache-driver-login:127.0.0.1:9999999991','i:1;',1794750930),('liferoute-cache-driver-login:127.0.0.1:9999999991:timer','i:1794750930;',1794750930),('liferoute-cache-driver-login:127.0.0.1:9999999999','i:1;',1794752269),('liferoute-cache-driver-login:127.0.0.1:9999999999:timer','i:1794752269;',1794752269),('liferoute-cache-user-send-otp:127.0.0.1:9876543210','i:1;',1794810341),('liferoute-cache-user-send-otp:127.0.0.1:9876543210:timer','i:1794810341;',1794810341);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_documents`
--

DROP TABLE IF EXISTS `driver_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(64) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `driver_documents_driver_id_foreign` (`driver_id`),
  CONSTRAINT `driver_documents_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_documents`
--

LOCK TABLES `driver_documents` WRITE;
/*!40000 ALTER TABLE `driver_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_notifications`
--

DROP TABLE IF EXISTS `driver_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned DEFAULT NULL,
  `booking_reference` varchar(32) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(64) NOT NULL DEFAULT 'new_booking',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `driver_notifications_booking_id_index` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_notifications`
--

LOCK TABLES `driver_notifications` WRITE;
/*!40000 ALTER TABLE `driver_notifications` DISABLE KEYS */;
INSERT INTO `driver_notifications` VALUES (1,NULL,NULL,'🏥 Dispatch System Online','Emergency dispatch server and real-time GPS tracking channels are active.','system',NULL,0,'2026-11-12 08:10:06','2026-11-12 08:10:06'),(2,58204,'BK-58204','🚨 Emergency Dispatch: Kavitha Devi (BK-58204)','Emergency Critical at Test Emergency Pickup Point. Phone: +919444433221. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":58204,\"booking_reference\":\"BK-58204\",\"patient_name\":\"Kavitha Devi\",\"patient_phone\":\"+919444433221\",\"pickup_address\":\"Test Emergency Pickup Point\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Critical\",\"essential_instructions\":\"Emergency Critical. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T04:01:55+00:00\"}',0,'2026-11-12 22:31:55','2026-11-12 22:31:55'),(3,29168,'BK-29168','🚨 Emergency Dispatch: Kavitha Devi (BK-29168)','Emergency Critical at Test Emergency Pickup Point. Phone: +919444433221. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":29168,\"booking_reference\":\"BK-29168\",\"patient_name\":\"Kavitha Devi\",\"patient_phone\":\"+919444433221\",\"pickup_address\":\"Test Emergency Pickup Point\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Critical\",\"essential_instructions\":\"Emergency Critical. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T04:02:10+00:00\"}',0,'2026-11-12 22:32:10','2026-11-12 22:32:10'),(4,96663,'BK-96663','🚨 Emergency Dispatch: Kavitha Devi (BK-96663)','Emergency Critical at Test Emergency Pickup Point. Phone: +919444433221. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":96663,\"booking_reference\":\"BK-96663\",\"patient_name\":\"Kavitha Devi\",\"patient_phone\":\"+919444433221\",\"pickup_address\":\"Test Emergency Pickup Point\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Critical\",\"essential_instructions\":\"Emergency Critical. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T04:02:31+00:00\"}',0,'2026-11-12 22:32:31','2026-11-12 22:32:31'),(5,42020,'BK-42020','🚨 Emergency Dispatch: Kavitha Devi (BK-42020)','Emergency Critical at Test Emergency Pickup Point. Phone: +919444433221. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":42020,\"booking_reference\":\"BK-42020\",\"patient_name\":\"Kavitha Devi\",\"patient_phone\":\"+919444433221\",\"pickup_address\":\"Test Emergency Pickup Point\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Critical\",\"essential_instructions\":\"Emergency Critical. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T04:02:53+00:00\"}',0,'2026-11-12 22:32:53','2026-11-12 22:32:53'),(6,78151,'BK-78151','🚨 Emergency Dispatch: Kavitha Devi (BK-78151)','Emergency Critical at Test Emergency Pickup Point. Phone: +919444433221. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":78151,\"booking_reference\":\"BK-78151\",\"patient_name\":\"Kavitha Devi\",\"patient_phone\":\"+919444433221\",\"pickup_address\":\"Test Emergency Pickup Point\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Critical\",\"essential_instructions\":\"Emergency Critical. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T04:03:16+00:00\"}',0,'2026-11-12 22:33:16','2026-11-12 22:33:16'),(7,57662,'BK-57662','🚨 Emergency Dispatch: Tyson (BK-57662)','Emergency Pregnancy / Maternity at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Richmond Hospital & Advanced Emergency Trauma. Notes: Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','new_booking','{\"id\":57662,\"booking_reference\":\"BK-57662\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376504,\"pickup_longitude\":76.919332,\"destination_hospital\":\"Richmond Hospital & Advanced Emergency Trauma\",\"destination_address\":\"Cross Cut Road, 7th Street, Gandhipuram, Coimbatore\",\"destination_latitude\":11.0185,\"destination_longitude\":76.9675,\"emergency_category\":\"Pregnancy \\/ Maternity\",\"essential_instructions\":\"Requires sterile obstetric delivery kit, neonatal suction & incubator standby.\",\"distance_meters\":7410,\"estimated_eta_minutes\":13,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T06:46:02+00:00\"}',0,'2026-11-13 01:16:02','2026-11-13 01:16:02'),(8,59448,'BK-59448','🚨 Emergency Dispatch: Tyson (BK-59448)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Richmond Hospital & Advanced Emergency Trauma. Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":59448,\"booking_reference\":\"BK-59448\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376487,\"pickup_longitude\":76.9193327,\"destination_hospital\":\"Richmond Hospital & Advanced Emergency Trauma\",\"destination_address\":\"Cross Cut Road, 7th Street, Gandhipuram, Coimbatore\",\"destination_latitude\":11.0185,\"destination_longitude\":76.9675,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":7410,\"estimated_eta_minutes\":13,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T06:46:22+00:00\"}',0,'2026-11-13 01:16:22','2026-11-13 01:16:22'),(9,70652,'BK-70652','🚨 Emergency Dispatch: Tyson (BK-70652)','Emergency Pregnancy / Maternity at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Sundakamuthur Government Hospital (Government 108). Notes: Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','new_booking','{\"id\":70652,\"booking_reference\":\"BK-70652\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376484,\"pickup_longitude\":76.9193358,\"destination_hospital\":\"Sundakamuthur Government Hospital (Government 108)\",\"destination_address\":\"Sundakamuthur Government Hospital, KM 6\\/8 of Coimbatore-Siruvani Road - KM 165\\/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India\",\"destination_latitude\":10.955943,\"destination_longitude\":76.9241781,\"emergency_category\":\"Pregnancy \\/ Maternity\",\"essential_instructions\":\"Requires sterile obstetric delivery kit, neonatal suction & incubator standby.\",\"distance_meters\":11800,\"estimated_eta_minutes\":21,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T06:48:38+00:00\"}',0,'2026-11-13 01:18:38','2026-11-13 01:18:38'),(10,89554,'BK-89554','🚨 Emergency Dispatch: Tyson (BK-89554)','Emergency Stroke / Neurological at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: KG Hospital & Advanced Trauma Centre. Notes: Requires stroke thrombolysis protocol, blood glucose kit & rapid CT referral.','new_booking','{\"id\":89554,\"booking_reference\":\"BK-89554\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376494,\"pickup_longitude\":76.9193676,\"destination_hospital\":\"KG Hospital & Advanced Trauma Centre\",\"destination_address\":\"Government Arts College Road, Gopalapuram, Coimbatore\",\"destination_latitude\":11.0035,\"destination_longitude\":76.9642,\"emergency_category\":\"Stroke \\/ Neurological\",\"essential_instructions\":\"Requires stroke thrombolysis protocol, blood glucose kit & rapid CT referral.\",\"distance_meters\":8080,\"estimated_eta_minutes\":14,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T06:50:22+00:00\"}',0,'2026-11-13 01:20:22','2026-11-13 01:20:22'),(11,12792,'BK-12792','🚨 Emergency Dispatch: Kavitha Raman (BK-12792)','Emergency Cardiac at Gandhipuram Bus Stand, Coimbatore. Phone: +919876543210. Destination: Emergency Trauma Center. Notes: Severe chest pain','new_booking','{\"id\":12792,\"booking_reference\":\"BK-12792\",\"patient_name\":\"Kavitha Raman\",\"patient_phone\":\"+919876543210\",\"pickup_address\":\"Gandhipuram Bus Stand, Coimbatore\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0425,\"destination_longitude\":77.0375,\"emergency_category\":\"Cardiac\",\"essential_instructions\":\"Severe chest pain\",\"distance_meters\":6050,\"estimated_eta_minutes\":11,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T08:11:50+00:00\"}',0,'2026-11-13 02:41:50','2026-11-13 02:41:50'),(12,34378,'BK-34378','🚨 Emergency Dispatch: Suresh Kumar (BK-34378)','Emergency Cardiac at Gandhipuram Bus Stand, Cross Cut Road, Coimbatore. Phone: +919876543210. Destination: Emergency Trauma Center. Notes: Severe chest pain and difficulty breathing','new_booking','{\"id\":34378,\"booking_reference\":\"BK-34378\",\"patient_name\":\"Suresh Kumar\",\"patient_phone\":\"+919876543210\",\"pickup_address\":\"Gandhipuram Bus Stand, Cross Cut Road, Coimbatore\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0425,\"destination_longitude\":77.0375,\"emergency_category\":\"Cardiac\",\"essential_instructions\":\"Severe chest pain and difficulty breathing\",\"distance_meters\":6050,\"estimated_eta_minutes\":11,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T08:12:36+00:00\"}',0,'2026-11-13 02:42:36','2026-11-13 02:42:36'),(13,31279,'BK-31279','🚨 Emergency Dispatch: Tyson (BK-31279)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":31279,\"booking_reference\":\"BK-31279\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376488,\"pickup_longitude\":76.9193331,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T08:42:25+00:00\"}',0,'2026-11-13 03:12:25','2026-11-13 03:12:25'),(14,59182,'BK-59182','🚨 Emergency Dispatch: Priya Sharma (BK-59182)','Emergency Cardiac Emergency at Gandhipuram Bus Stand, Coimbatore. Phone: +91 98428 11223. Destination: Emergency Trauma Center. Notes: Severe chest pain, urgent ambulance required','new_booking','{\"id\":59182,\"booking_reference\":\"BK-59182\",\"patient_name\":\"Priya Sharma\",\"patient_phone\":\"+91 98428 11223\",\"pickup_address\":\"Gandhipuram Bus Stand, Coimbatore\",\"pickup_landmark\":\"Near Clock Tower\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Cardiac Emergency\",\"essential_instructions\":\"Severe chest pain, urgent ambulance required\",\"distance_meters\":2710,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T09:14:06+00:00\"}',0,'2026-11-13 03:44:06','2026-11-13 03:44:06'),(15,86529,'BK-86529','🚨 Emergency Dispatch: TYSON 93 (BK-86529)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Sundakamuthur Government Hospital (Government 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":86529,\"booking_reference\":\"BK-86529\",\"patient_name\":\"TYSON 93\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376493,\"pickup_longitude\":76.9193358,\"destination_hospital\":\"Sundakamuthur Government Hospital (Government 108)\",\"destination_address\":\"Sundakamuthur Government Hospital, KM 6\\/8 of Coimbatore-Siruvani Road - KM 165\\/8 of Salem-Cochin Road, Sundakkamuthur, Ward 89, South Zone, Perur, Coimbatore South, Coimbatore, Tamil Nadu, 641010, India\",\"destination_latitude\":10.955943,\"destination_longitude\":76.9241781,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":11800,\"estimated_eta_minutes\":21,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T09:36:45+00:00\"}',0,'2026-11-13 04:06:45','2026-11-13 04:06:45'),(16,13461,'BK-13461','🚨 Emergency Dispatch: TYSON 93 (BK-13461)','Emergency Severe Burn Injury at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires sterile burn trauma dressing sheets, IV fluid resuscitation & pain management.','new_booking','{\"id\":13461,\"booking_reference\":\"BK-13461\",\"patient_name\":\"TYSON 93\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376469,\"pickup_longitude\":76.9193331,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Severe Burn Injury\",\"essential_instructions\":\"Requires sterile burn trauma dressing sheets, IV fluid resuscitation & pain management.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T10:41:22+00:00\"}',0,'2026-11-13 05:11:22','2026-11-13 05:11:22'),(17,36300,'BK-36300','🚨 Emergency Dispatch: TYSON 93 (BK-36300)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":36300,\"booking_reference\":\"BK-36300\",\"patient_name\":\"TYSON 93\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0377382,\"pickup_longitude\":76.9193038,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8860,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T11:04:06+00:00\"}',0,'2026-11-13 05:34:06','2026-11-13 05:34:06'),(18,66413,'BK-66413','🚨 Emergency Dispatch: Tysonpaul (BK-66413)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Ashwin Hospital & Emergency Trauma Centre. Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":66413,\"booking_reference\":\"BK-66413\",\"patient_name\":\"Tysonpaul\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376521,\"pickup_longitude\":76.9193336,\"destination_hospital\":\"Ashwin Hospital & Emergency Trauma Centre\",\"destination_address\":\"1, Sathy Road, Gandhipuram, Coimbatore\",\"destination_latitude\":11.021,\"destination_longitude\":76.962,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":6560,\"estimated_eta_minutes\":12,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T11:40:34+00:00\"}',0,'2026-11-13 06:10:34','2026-11-13 06:10:34'),(19,20235,'BK-20235','🚨 Emergency Dispatch: Tysonpaul (BK-20235)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Royal Care Super Speciality Hospital. Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":20235,\"booking_reference\":\"BK-20235\",\"patient_name\":\"Tysonpaul\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376467,\"pickup_longitude\":76.9193324,\"destination_hospital\":\"Royal Care Super Speciality Hospital\",\"destination_address\":\"1\\/520, L&T Bypass Road, Neelambur, Coimbatore\",\"destination_latitude\":11.065,\"destination_longitude\":77.072,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":21830,\"estimated_eta_minutes\":38,\"countdown_seconds\":120,\"created_at\":\"2026-11-13T12:14:34+00:00\"}',0,'2026-11-13 06:44:34','2026-11-13 06:44:34'),(20,34644,'BK-34644','🚨 Emergency Dispatch: Emergency Patient (BK-34644)','Emergency Pregnancy / Maternity at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','new_booking','{\"id\":34644,\"booking_reference\":\"BK-34644\",\"patient_name\":\"Emergency Patient\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376482,\"pickup_longitude\":76.9193339,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Pregnancy \\/ Maternity\",\"essential_instructions\":\"Requires sterile obstetric delivery kit, neonatal suction & incubator standby.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T08:22:46+00:00\"}',0,'2026-11-14 02:52:46','2026-11-14 02:52:46'),(21,18017,'BK-18017','🚨 Emergency Dispatch: Tyson (BK-18017)','Emergency Pregnancy / Maternity at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 8128304510. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires sterile obstetric delivery kit, neonatal suction & incubator standby.','new_booking','{\"id\":18017,\"booking_reference\":\"BK-18017\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 8128304510\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.037623,\"pickup_longitude\":76.9193122,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Pregnancy \\/ Maternity\",\"essential_instructions\":\"Requires sterile obstetric delivery kit, neonatal suction & incubator standby.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T08:34:21+00:00\"}',0,'2026-11-14 03:04:21','2026-11-14 03:04:21'),(22,57811,'BK-57811','🚨 Emergency Dispatch: Tyson (BK-57811)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 8128304510. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":57811,\"booking_reference\":\"BK-57811\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 8128304510\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.037648,\"pickup_longitude\":76.9193291,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T09:28:30+00:00\"}',0,'2026-11-14 03:58:30','2026-11-14 03:58:30'),(23,59176,'BK-59176','🚨 Emergency Dispatch: Matthew (BK-59176)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":59176,\"booking_reference\":\"BK-59176\",\"patient_name\":\"Matthew\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376484,\"pickup_longitude\":76.9193359,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T09:47:51+00:00\"}',0,'2026-11-14 04:17:51','2026-11-14 04:17:51'),(24,86844,'BK-86844','🚨 Emergency Dispatch: Matthew (BK-86844)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":86844,\"booking_reference\":\"BK-86844\",\"patient_name\":\"Matthew\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376512,\"pickup_longitude\":76.9193331,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8840,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T10:30:18+00:00\"}',0,'2026-11-14 05:00:18','2026-11-14 05:00:18'),(25,97389,'BK-97389','🚨 Emergency Dispatch: Matthew (BK-97389)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":97389,\"booking_reference\":\"BK-97389\",\"patient_name\":\"Matthew\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.037649700796008,\"pickup_longitude\":76.91927831907324,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8850,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T12:35:28+00:00\"}',0,'2026-11-14 07:05:28','2026-11-14 07:05:28'),(26,42078,'BK-42078','🚨 Emergency Dispatch: Emergency Patient (BK-42078)','Emergency Cardiac / Heart at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9840010801. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":42078,\"booking_reference\":\"BK-42078\",\"patient_name\":\"Emergency Patient\",\"patient_phone\":\"+91 9840010801\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.037649700796008,\"pickup_longitude\":76.91927831907324,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":8850,\"estimated_eta_minutes\":16,\"countdown_seconds\":120,\"created_at\":\"2026-11-14T13:17:10+00:00\"}',0,'2026-11-14 07:47:10','2026-11-14 07:47:10'),(27,87345,'BK-87345','🚨 Emergency Dispatch: Tyson (BK-87345)','Emergency Accident / Trauma at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 8728304510. Destination: Ashwin Hospital & Emergency Trauma Centre. Notes: Requires spinal board, cervical collar, trauma dressings & rapid blood transfusion kit.','new_booking','{\"id\":87345,\"booking_reference\":\"BK-87345\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 8728304510\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376474,\"pickup_longitude\":76.9193202,\"destination_hospital\":\"Ashwin Hospital & Emergency Trauma Centre\",\"destination_address\":\"1, Sathy Road, Gandhipuram, Coimbatore\",\"destination_latitude\":11.021,\"destination_longitude\":76.962,\"emergency_category\":\"Accident \\/ Trauma\",\"essential_instructions\":\"Requires spinal board, cervical collar, trauma dressings & rapid blood transfusion kit.\",\"distance_meters\":6570,\"estimated_eta_minutes\":12,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T12:25:15+00:00\"}',0,'2026-11-15 06:55:15','2026-11-15 06:55:15'),(28,12270,'BK-12270','🚨 Emergency Dispatch: Tyson (BK-12270)','Emergency Pediatric / Child at Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 8728304510. Destination: Emergency Trauma Center. Notes: Requires pediatric life-support stretcher, infant airway tools & pediatric paramedic.','new_booking','{\"id\":12270,\"booking_reference\":\"BK-12270\",\"patient_name\":\"Tyson\",\"patient_phone\":\"+91 8728304510\",\"pickup_address\":\"Door No. 73, Ward 7, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0376488,\"pickup_longitude\":76.9193111,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0526488,\"destination_longitude\":76.9313111,\"emergency_category\":\"Pediatric \\/ Child\",\"essential_instructions\":\"Requires pediatric life-support stretcher, infant airway tools & pediatric paramedic.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T12:26:52+00:00\"}',0,'2026-11-15 06:56:52','2026-11-15 06:56:52'),(29,95917,'BK-95917','🚨 Emergency Dispatch: Kumar (BK-95917)','Emergency Cardiac / Heart at Acquiring live GPS address.... Phone: +91 9789510300. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":95917,\"booking_reference\":\"BK-95917\",\"patient_name\":\"Kumar\",\"patient_phone\":\"+91 9789510300\",\"pickup_address\":\"Acquiring live GPS address...\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0318,\"pickup_longitude\":76.9437,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":1880,\"estimated_eta_minutes\":4,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T14:22:23+00:00\"}',0,'2026-11-15 08:52:23','2026-11-15 08:52:23'),(30,35821,'BK-35821','🚨 Emergency Dispatch: Kumar (BK-35821)','Emergency Cardiac / Heart at Acquiring live GPS address.... Phone: +91 9789510300. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":35821,\"booking_reference\":\"BK-35821\",\"patient_name\":\"Kumar\",\"patient_phone\":\"+91 9789510300\",\"pickup_address\":\"Acquiring live GPS address...\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.0318,\"pickup_longitude\":76.9437,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":1880,\"estimated_eta_minutes\":4,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T14:24:18+00:00\"}',0,'2026-11-15 08:54:18','2026-11-15 08:54:18'),(31,67911,'BK-67911','🚨 Emergency Dispatch: Muhi (BK-67911)','Emergency Cardiac / Heart at Door No. 63, Ward 12, Chinnappan Street, Coimbatore North District, Coimbatore, Tamil Nadu - 641001. Phone: +91 9789510300. Destination: Government Coimbatore Medical College Hospital (CMCH 108). Notes: Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.','new_booking','{\"id\":67911,\"booking_reference\":\"BK-67911\",\"patient_name\":\"Muhi\",\"patient_phone\":\"+91 9789510300\",\"pickup_address\":\"Door No. 63, Ward 12, Chinnappan Street, Coimbatore North District, Coimbatore, Tamil Nadu - 641001\",\"pickup_landmark\":\"GPS Pinpoint Location\",\"pickup_latitude\":11.027170016694395,\"pickup_longitude\":76.94358609337378,\"destination_hospital\":\"Government Coimbatore Medical College Hospital (CMCH 108)\",\"destination_address\":\"Trichy Road, Gopalapuram, Coimbatore (Opposite Collectorate)\",\"destination_latitude\":11.0015,\"destination_longitude\":76.9695,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Requires high-flow oxygen, continuous ECG monitor, defibrillator & cardiac resuscitation kit.\",\"distance_meters\":1320,\"estimated_eta_minutes\":3,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T14:29:13+00:00\"}',0,'2026-11-15 08:59:13','2026-11-15 08:59:13'),(32,52718,'BK-52718','🚨 Emergency Dispatch: BK-52718 (BK-52718)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":52718,\"booking_reference\":\"BK-52718\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-15T14:33:22+00:00\"}',0,'2026-11-15 09:03:22','2026-11-15 09:03:22'),(33,44389,'BK-44389','🚨 Emergency Dispatch: BK-44389 (BK-44389)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":44389,\"booking_reference\":\"BK-44389\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T05:37:40+00:00\"}',0,'2026-11-16 00:07:40','2026-11-16 00:07:40'),(34,28468,'BK-28468','🚨 Emergency Dispatch: BK-28468 (BK-28468)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":28468,\"booking_reference\":\"BK-28468\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T05:53:17+00:00\"}',0,'2026-11-16 00:23:17','2026-11-16 00:23:17'),(35,25644,'BK-25644','🚨 Emergency Dispatch: Raman (BK-25644)','Emergency Cardiac / Heart at Gandhipuram, Coimbatore. Phone: +919876543210. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":25644,\"booking_reference\":\"BK-25644\",\"patient_name\":\"Raman\",\"patient_phone\":\"+919876543210\",\"pickup_address\":\"Gandhipuram, Coimbatore\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0168,\"pickup_longitude\":76.9558,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.0318,\"destination_longitude\":76.9678,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Emergency Cardiac \\/ Heart. Priority ambulance needed immediately.\",\"distance_meters\":2710,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T06:21:13+00:00\"}',0,'2026-11-16 00:51:13','2026-11-16 00:51:13'),(36,90602,'BK-90602','🚨 Emergency Dispatch: BK-90602 (BK-90602)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":90602,\"booking_reference\":\"BK-90602\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T06:32:32+00:00\"}',0,'2026-11-16 01:02:32','2026-11-16 01:02:32'),(37,54356,'BK-54356','🚨 Emergency Dispatch: BK-54356 (BK-54356)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":54356,\"booking_reference\":\"BK-54356\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T06:39:36+00:00\"}',0,'2026-11-16 01:09:36','2026-11-16 01:09:36'),(38,74543,'BK-74543','🚨 Emergency Dispatch: BK-74543 (BK-74543)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":74543,\"booking_reference\":\"BK-74543\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T07:33:17+00:00\"}',0,'2026-11-16 02:03:17','2026-11-16 02:03:17'),(39,36236,'BK-36236','🚨 Emergency Dispatch: BK-36236 (BK-36236)','Emergency General Emergency at Current GPS Position. Phone: . Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":36236,\"booking_reference\":\"BK-36236\",\"patient_name\":\"\",\"patient_phone\":\"\",\"pickup_address\":\"Current GPS Position\",\"pickup_landmark\":\"\",\"pickup_latitude\":12.9716,\"pickup_longitude\":77.5946,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":12.986600000000001,\"destination_longitude\":77.6066,\"emergency_category\":\"General Emergency\",\"essential_instructions\":\"Emergency General Emergency. Priority ambulance needed immediately.\",\"distance_meters\":2860,\"estimated_eta_minutes\":5,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T07:56:18+00:00\"}',0,'2026-11-16 02:26:18','2026-11-16 02:26:18'),(40,98578,'BK-98578','🚨 Emergency Dispatch: Verification Test (BK-98578)','Emergency Cardiac / Heart at Kovilmedu, Coimbatore. Phone: 9876543210. Destination: Emergency Trauma Center. Notes: Immediate assistance requested.','new_booking','{\"id\":98578,\"booking_reference\":\"BK-98578\",\"patient_name\":\"Verification Test\",\"patient_phone\":\"9876543210\",\"pickup_address\":\"Kovilmedu, Coimbatore\",\"pickup_landmark\":\"\",\"pickup_latitude\":11.0318,\"pickup_longitude\":76.9437,\"destination_hospital\":\"Emergency Trauma Center\",\"destination_address\":\"Casualty & Emergency Bay\",\"destination_latitude\":11.046800000000001,\"destination_longitude\":76.95570000000001,\"emergency_category\":\"Cardiac \\/ Heart\",\"essential_instructions\":\"Emergency Cardiac \\/ Heart. Priority ambulance needed immediately.\",\"distance_meters\":1880,\"estimated_eta_minutes\":4,\"countdown_seconds\":120,\"created_at\":\"2026-11-16T08:05:15+00:00\"}',0,'2026-11-16 02:35:15','2026-11-16 02:35:15');
/*!40000 ALTER TABLE `driver_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_phone_aliases`
--

DROP TABLE IF EXISTS `driver_phone_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_phone_aliases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone_key` varchar(32) NOT NULL,
  `driver_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `driver_phone_aliases_phone_key_unique` (`phone_key`),
  KEY `driver_phone_aliases_driver_id_foreign` (`driver_id`),
  CONSTRAINT `driver_phone_aliases_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_phone_aliases`
--

LOCK TABLES `driver_phone_aliases` WRITE;
/*!40000 ALTER TABLE `driver_phone_aliases` DISABLE KEYS */;
INSERT INTO `driver_phone_aliases` VALUES (2,'8128304510',8,'2026-11-13 01:13:02','2026-11-13 01:13:02'),(3,'9840010801',9,'2026-11-13 01:14:55','2026-11-13 01:14:55'),(4,'9842811111',10,'2026-11-13 03:43:53','2026-11-13 03:43:53'),(5,'9674554014',14,'2026-11-15 07:33:47','2026-11-15 07:33:47'),(6,'9842100108',16,'2026-11-16 00:51:28','2026-11-16 00:51:28');
/*!40000 ALTER TABLE `driver_phone_aliases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_tokens`
--

DROP TABLE IF EXISTS `driver_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `token` varchar(191) NOT NULL,
  `device_name` varchar(120) NOT NULL DEFAULT 'Driver Mobile App',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `driver_tokens_token_unique` (`token`),
  KEY `driver_tokens_driver_id_foreign` (`driver_id`),
  CONSTRAINT `driver_tokens_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_tokens`
--

LOCK TABLES `driver_tokens` WRITE;
/*!40000 ALTER TABLE `driver_tokens` DISABLE KEYS */;
INSERT INTO `driver_tokens` VALUES (6,9,'9|driver_sanctum_token_1794552295_676','Driver Mobile App','2026-11-13 01:14:55','2026-11-13 01:14:55'),(7,9,'9|driver_sanctum_token_1794557555_747','Driver Mobile App','2026-11-13 02:42:35','2026-11-13 02:42:35'),(8,9,'9|driver_sanctum_token_1794559262_267','Driver Mobile App','2026-11-13 03:11:02','2026-11-13 03:11:02'),(9,10,'10|driver_sanctum_token_1794561233_676','Driver Mobile App','2026-11-13 03:43:53','2026-11-13 03:43:53'),(10,10,'10|driver_sanctum_token_1794561235_427','Driver Mobile App','2026-11-13 03:43:55','2026-11-13 03:43:55'),(11,10,'10|driver_sanctum_token_1794561268_538','Driver Mobile App','2026-11-13 03:44:28','2026-11-13 03:44:28'),(12,10,'10|driver_sanctum_token_1794561313_846','Driver Mobile App','2026-11-13 03:45:13','2026-11-13 03:45:13'),(14,9,'9|driver_sanctum_token_1794562728_586','Driver Mobile App','2026-11-13 04:08:48','2026-11-13 04:08:48'),(15,9,'9|driver_sanctum_token_1794562742_447','Driver Mobile App','2026-11-13 04:09:02','2026-11-13 04:09:02'),(16,9,'9|driver_sanctum_token_1794562744_280','Driver Mobile App','2026-11-13 04:09:04','2026-11-13 04:09:04'),(17,9,'9|driver_sanctum_token_1794562745_679','Driver Mobile App','2026-11-13 04:09:05','2026-11-13 04:09:05'),(18,9,'9|driver_sanctum_token_1794562746_156','Driver Mobile App','2026-11-13 04:09:06','2026-11-13 04:09:06'),(19,9,'9|driver_sanctum_token_1794562747_744','Driver Mobile App','2026-11-13 04:09:07','2026-11-13 04:09:07'),(20,9,'9|driver_sanctum_token_1794562747_390','Driver Mobile App','2026-11-13 04:09:07','2026-11-13 04:09:07'),(21,9,'9|driver_sanctum_token_1794562748_814','Driver Mobile App','2026-11-13 04:09:08','2026-11-13 04:09:08'),(22,9,'9|driver_sanctum_token_1794562749_433','Driver Mobile App','2026-11-13 04:09:09','2026-11-13 04:09:09'),(23,9,'9|driver_sanctum_token_1794562749_988','Driver Mobile App','2026-11-13 04:09:09','2026-11-13 04:09:09'),(24,9,'9|driver_sanctum_token_1794562750_107','Driver Mobile App','2026-11-13 04:09:10','2026-11-13 04:09:10'),(25,9,'9|driver_sanctum_token_1794562752_473','Driver Mobile App','2026-11-13 04:09:12','2026-11-13 04:09:12'),(26,9,'9|driver_sanctum_token_1794562752_732','Driver Mobile App','2026-11-13 04:09:12','2026-11-13 04:09:12'),(27,9,'9|driver_sanctum_token_1794562754_645','Driver Mobile App','2026-11-13 04:09:14','2026-11-13 04:09:14'),(28,9,'9|driver_sanctum_token_1794562761_894','Driver Mobile App','2026-11-13 04:09:21','2026-11-13 04:09:21'),(29,9,'9|driver_sanctum_token_1794562762_409','Driver Mobile App','2026-11-13 04:09:22','2026-11-13 04:09:22'),(30,9,'9|driver_sanctum_token_1794562765_373','Driver Mobile App','2026-11-13 04:09:25','2026-11-13 04:09:25'),(31,9,'9|driver_sanctum_token_1794562765_738','Driver Mobile App','2026-11-13 04:09:25','2026-11-13 04:09:25'),(32,9,'9|driver_sanctum_token_1794644512_362','Driver Mobile App','2026-11-14 02:51:52','2026-11-14 02:51:52'),(33,9,'9|driver_sanctum_token_1794649654_446','Driver Mobile App','2026-11-14 04:17:34','2026-11-14 04:17:34'),(34,9,'9|driver_sanctum_token_1794652201_967','Driver Mobile App','2026-11-14 05:00:01','2026-11-14 05:00:01'),(35,9,'9|driver_sanctum_token_1794659518_468','Driver Mobile App','2026-11-14 07:01:58','2026-11-14 07:01:58'),(36,11,'11|driver_sanctum_token_1794661183_332','Driver Mobile App','2026-11-14 07:29:43','2026-11-14 07:29:43'),(37,11,'11|driver_sanctum_token_1794661382_969','Driver Mobile App','2026-11-14 07:33:02','2026-11-14 07:33:02'),(39,12,'12|driver_sanctum_token_1794662821_297','Driver Mobile App','2026-11-14 07:57:01','2026-11-14 07:57:01'),(40,11,'11|driver_sanctum_token_1794740076_231','Driver Mobile App','2026-11-15 05:24:36','2026-11-15 05:24:36'),(41,13,'13|driver_sanctum_token_1794742024_489','Driver Mobile App','2026-11-15 05:57:04','2026-11-15 05:57:04'),(42,13,'13|driver_sanctum_token_1794742029_503','Driver Mobile App','2026-11-15 05:57:09','2026-11-15 05:57:09'),(44,13,'13|driver_sanctum_token_1794742551_187','Driver Mobile App','2026-11-15 06:05:51','2026-11-15 06:05:51'),(45,9,'9|driver_sanctum_token_1794742587_195','Driver Mobile App','2026-11-15 06:06:27','2026-11-15 06:06:27'),(46,9,'9|driver_sanctum_token_1794742593_757','Driver Mobile App','2026-11-15 06:06:33','2026-11-15 06:06:33'),(47,9,'9|driver_sanctum_token_1794742603_852','Driver Mobile App','2026-11-15 06:06:43','2026-11-15 06:06:43'),(48,9,'9|driver_sanctum_token_1794742607_585','Driver Mobile App','2026-11-15 06:06:47','2026-11-15 06:06:47'),(49,9,'9|driver_sanctum_token_1794742610_842','Driver Mobile App','2026-11-15 06:06:50','2026-11-15 06:06:50'),(50,13,'13|driver_sanctum_token_1794742832_728','Driver Mobile App','2026-11-15 06:10:32','2026-11-15 06:10:32'),(52,13,'13|driver_sanctum_token_1794747540_123','Driver Mobile App','2026-11-15 07:29:00','2026-11-15 07:29:00'),(53,13,'13|driver_sanctum_token_1794747580_973','Driver Mobile App','2026-11-15 07:29:40','2026-11-15 07:29:40'),(54,14,'14|driver_sanctum_token_1794747827_815','Driver Mobile App','2026-11-15 07:33:47','2026-11-15 07:33:47'),(55,14,'14|driver_sanctum_token_1794747836_814','Driver Mobile App','2026-11-15 07:33:56','2026-11-15 07:33:56'),(57,15,'15|driver_sanctum_token_1794747885_472','Driver Mobile App','2026-11-15 07:34:45','2026-11-15 07:34:45'),(58,13,'13|driver_sanctum_token_1794750649_276','Driver Mobile App','2026-11-15 08:20:49','2026-11-15 08:20:49'),(59,13,'13|driver_sanctum_token_1794750948_678','Driver Mobile App','2026-11-15 08:25:48','2026-11-15 08:25:48'),(60,13,'13|driver_sanctum_token_1794750973_843','Driver Mobile App','2026-11-15 08:26:13','2026-11-15 08:26:13'),(61,13,'13|driver_sanctum_token_1794751971_226','Driver Mobile App','2026-11-15 08:42:51','2026-11-15 08:42:51'),(62,9,'9|driver_sanctum_token_1794752457_672','Driver Mobile App','2026-11-15 08:50:57','2026-11-15 08:50:57'),(63,9,'9|driver_sanctum_token_1794752475_349','Driver Mobile App','2026-11-15 08:51:15','2026-11-15 08:51:15'),(64,11,'11|driver_sanctum_token_1794752496_827','Driver Mobile App','2026-11-15 08:51:36','2026-11-15 08:51:36'),(65,11,'11|driver_sanctum_token_1794753059_934','Driver Mobile App','2026-11-15 09:00:59','2026-11-15 09:00:59'),(66,13,'13|driver_sanctum_token_1794758050_825','Driver Mobile App','2026-11-15 10:24:10','2026-11-15 10:24:10'),(67,16,'16|driver_sanctum_token_1794810088_303','Driver Mobile App','2026-11-16 00:51:28','2026-11-16 00:51:28'),(68,16,'16|driver_sanctum_token_1794810096_821','Driver Mobile App','2026-11-16 00:51:36','2026-11-16 00:51:36'),(69,9,'9|driver_sanctum_token_1794812906_889','Driver Mobile App','2026-11-16 01:38:26','2026-11-16 01:38:26');
/*!40000 ALTER TABLE `driver_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `phone_key` varchar(32) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(191) NOT NULL DEFAULT '',
  `emergency_contact` varchar(64) NOT NULL DEFAULT '',
  `license_number` varchar(64) NOT NULL DEFAULT '',
  `license_expiry_date` varchar(32) NOT NULL DEFAULT '',
  `rating` decimal(4,2) NOT NULL DEFAULT 4.95,
  `total_trips` int(10) unsigned NOT NULL DEFAULT 0,
  `vehicle_number` varchar(32) NOT NULL DEFAULT 'TN-01-EA-1080',
  `vehicle_plate` varchar(32) NOT NULL DEFAULT 'TN-01-EA-1080',
  `vehicle_type` varchar(32) NOT NULL DEFAULT 'als',
  `vehicle_type_display` varchar(100) NOT NULL DEFAULT 'Advanced Life Support (ALS)',
  `vehicle_model` varchar(120) NOT NULL DEFAULT 'Force Traveller ICU Ambulance',
  `vehicle_year` smallint(5) unsigned NOT NULL DEFAULT 2024,
  `equipment_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_list`)),
  `operator_affiliation` varchar(150) NOT NULL DEFAULT '108 Emergency Services',
  `permit_number` varchar(64) NOT NULL DEFAULT '',
  `permit_expiry_date` varchar(32) NOT NULL DEFAULT '',
  `insurance_policy_number` varchar(64) NOT NULL DEFAULT '',
  `insurance_expiry_date` varchar(32) NOT NULL DEFAULT '',
  `documents_uploaded` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents_uploaded`)),
  `verification_status` varchar(32) NOT NULL DEFAULT 'approved',
  `is_online` tinyint(1) NOT NULL DEFAULT 1,
  `lat` double NOT NULL DEFAULT 11.021,
  `lng` double NOT NULL DEFAULT 76.938,
  `heading` double NOT NULL DEFAULT 0,
  `speed` double NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drivers_phone_key_unique` (`phone_key`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drivers`
--

LOCK TABLES `drivers` WRITE;
/*!40000 ALTER TABLE `drivers` DISABLE KEYS */;
INSERT INTO `drivers` VALUES (8,'Tyson','+918128304510','8128304510','$2y$12$q45jnO8/unAk6y.eRNtMreig7VBhVTk/d8cHeYeS2wQgtj6/BNANS','tysonpaul493@gmail.com','9677557014','93847472939484','2027-11-13',5.00,0,'TN38CB1234','TN38CB1234','als','Advanced Life Support (ALS)','Mahindra',2018,'[\"Oxygen Cylinder & Regulators\",\"Defibrillator \\/ AED\",\"Transport Ventilator\",\"Suction Unit (Electric\\/Manual)\",\"Emergency Trauma First Aid Kit\",\"Multipara Cardiac Monitor\",\"Cervical Collars & Splints\"]','Hospital','92844773833','2027-11-13','399448738283','2027-11-13','{\"driving_license_front\":false,\"driving_license_back\":false,\"vehicle_registration\":false,\"ambulance_permit\":false,\"commercial_driver_badge\":false,\"insurance_policy\":false}','approved',1,11.0376504,76.9193318,0,0,'2026-11-13 01:13:02','2026-11-13 01:13:08'),(9,'Tyson','+919840010801','9840010801','$2y$12$XwZveIohgBAzQTDxCFGzB./CnWQXuoAt9D8J.568ErhKtWRVUA9A6','tysonpaul493@gmail.com','9677557014','938447838383','2027-11-15',5.00,19,'TN49CC1234','TN49CC1234','als','Advanced Life Support (ALS)','Mahindra',2018,'[\"Oxygen Cylinder & Regulators\",\"Defibrillator \\/ AED\",\"Transport Ventilator\",\"Suction Unit (Electric\\/Manual)\",\"Foldable Stretcher & Spine Board\",\"Multipara Cardiac Monitor\",\"Cervical Collars & Splints\"]','Hospital','394948484','2027-11-15','39948484','2027-11-15','{\"driving_license_front\":true,\"driving_license_back\":true,\"vehicle_registration\":true,\"ambulance_permit\":true,\"commercial_driver_badge\":true,\"insurance_policy\":true}','approved',1,11.0376498,76.9193242,137.17561340332,0.14920361340046,'2026-11-13 01:14:55','2026-11-16 01:39:08'),(10,'Alexander Fleming','+919842811111','9842811111','$2y$12$ShOS8yCWwsXcgpcenmOcX.ikH2SDMOYukyVbXEV6PTTOOGox5mXvC','alexander@liferoute.in','+919842800000','DL-TN38-2026-00123','2030-01-01',5.00,0,'TN 38 AF 9999','TN 38 AF 9999','als','Advanced Life Support (ALS)','Tata Winger ICU',2025,'[\"Oxygen Cylinder\",\"Defibrillator\",\"Ventilator\"]','Kovai Medical Center','','','','','{\"driving_license_front\":false,\"driving_license_back\":false,\"vehicle_registration\":false,\"ambulance_permit\":false,\"commercial_driver_badge\":false,\"insurance_policy\":false}','approved',1,11.021,76.938,0,0,'2026-11-13 03:43:53','2026-11-14 03:02:55'),(11,'Kumar','+919789510300','9789510300','$2y$12$/ppHjoL4p2nJOHTWfGPZkemOciC4YPWd.RaehoCe93V0PXoMMkF8.','','','TN382024009789','',5.00,0,'TN38CB5678','TN38CB5678','als','Advanced Life Support (ALS)','Force Traveller Ambulance',2024,NULL,'108 Emergency Services','','','','',NULL,'approved',1,12.9716,77.5946,0,0,'2026-11-14 07:29:26','2026-11-15 08:51:40'),(12,'Ambulance Pilot (9999)','+919842199999','9842199999','$2y$12$WwgLH4Uc6/fhrCAl26Hy8uG9HdVlE29vjhNlKzupfzAIlqIX63Fg6','','','','',5.00,0,'TN38-EMG-9999','TN38-EMG-9999','als','Advanced Life Support (ALS)','Emergency Response Ambulance',2026,'[\"Oxygen Cylinder\",\"First Aid Kit\",\"Stretcher\"]','108 Emergency Fleet','','','','',NULL,'approved',1,11.0376,76.9193,0,0,'2026-11-14 07:57:01','2026-11-14 07:57:01'),(13,'Tyson','+918428307510','8428307510','$2y$12$uzGjSNNkrMpsigG4RW633Osr.w9J52asfg90wcV6CPatoZ6LHF8H.','tysonpaul493@gmail.com','9677557014','934848838384','',5.00,0,'TN38CB1234','TN38CB1234','als','Advanced Life Support (ALS)','MAHINDRA Ambulance',2026,'[\"Oxygen Cylinder\",\"First Aid Kit\",\"Stretcher\"]','108 Emergency Fleet','','','','',NULL,'approved',1,11.0376485,76.9193304,90,35.5,'2026-11-15 05:57:04','2026-11-15 07:29:40'),(14,'Rose','+919674554014','9674554014','$2y$12$K.OOfLGkqifkGRJn8a2Zjur2Li0gnq8Z7xpPH68HU7M5P.4CTvpoq','tysonpaul1489@gmail.com','9840010801','394848839393','2027-11-15',5.00,0,'TN49RR2545','TN49RR2545','als','Advanced Life Support (ALS)','Mahindra',2020,'[\"Oxygen Cylinder & Regulators\",\"Transport Ventilator\",\"Suction Unit (Electric\\/Manual)\",\"Emergency Trauma First Aid Kit\",\"Multipara Cardiac Monitor\",\"Cervical Collars & Splints\"]','Hospital','39848483833','2027-11-15','934883929393','2027-11-15','{\"driving_license_front\":false,\"driving_license_back\":false,\"vehicle_registration\":false,\"ambulance_permit\":false,\"commercial_driver_badge\":false,\"insurance_policy\":false}','approved',1,11.0376485,76.9193483,0,0,'2026-11-15 07:33:47','2026-11-15 07:33:58'),(15,'Ambulance Pilot (7014)','+919677557014','9677557014','$2y$12$F4L5qwKTD2tnid.VkLtD5ek.QrwhSlyzWRDorPvzMWaiMxmoOnVNi','','','','',5.00,0,'TN38-EMG-7014','TN38-EMG-7014','als','Advanced Life Support (ALS)','Emergency Response Ambulance',2026,'[\"Oxygen Cylinder\",\"First Aid Kit\",\"Stretcher\"]','108 Emergency Fleet','','','','',NULL,'approved',1,11.0376548,76.9193207,19.224229812622,0.54146558046341,'2026-11-15 07:34:20','2026-11-15 07:34:57'),(16,'Rajesh Kumar','+919842100108','9842100108','$2y$12$PaPOoHFZWYBbyRl3UGEak.UqNWFJCvZEv2hr8ZZdquB8Q6YljR9wK','rajesh@liferoute.in','9842100000','DL-TN38-2026-9999','2029-12-31',5.00,0,'TN-38-EMG-1081','TN-38-EMG-1081','als','Advanced Life Support (ALS)','Force Traveller ICU',2025,'[]','108 Emergency Ambulance Services','','','','','{\"driving_license_front\":true,\"driving_license_back\":true,\"vehicle_registration\":true,\"ambulance_permit\":false,\"insurance_policy\":false,\"commercial_driver_badge\":true}','approved',1,11.0168,76.9558,0,0,'2026-11-16 00:51:28','2026-11-16 00:52:01');
/*!40000 ALTER TABLE `drivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_11_12_000001_create_liferoute_tables',1),(5,'2026_11_13_000002_add_password_to_drivers_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_codes`
--

DROP TABLE IF EXISTS `otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(16) NOT NULL DEFAULT 'driver',
  `phone_key` varchar(32) NOT NULL,
  `e164_phone` varchar(32) DEFAULT NULL,
  `otp_code` varchar(12) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `otp_codes_scope_phone_key_unique` (`scope`,`phone_key`),
  KEY `otp_codes_phone_key_index` (`phone_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_codes`
--

LOCK TABLES `otp_codes` WRITE;
/*!40000 ALTER TABLE `otp_codes` DISABLE KEYS */;
INSERT INTO `otp_codes` VALUES (1,'user','9876543210','+919876543210','667633','2026-11-16 00:55:41','2026-11-16 00:50:41','2026-11-16 00:50:41');
/*!40000 ALTER TABLE `otp_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(32) DEFAULT NULL,
  `blood_group` varchar(16) NOT NULL DEFAULT 'O+',
  `emergency_notes` text DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_public_id_unique` (`public_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_phone_index` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'Test Patient','+919999900001','O+',NULL,NULL,NULL,NULL,NULL,'2026-11-12 22:31:30','2026-11-12 22:31:30'),(2,'usr-1794552343815','Matthew','+91 9840010801','A+','Immediate 108 Emergency Dispatch Access (SIM Verified)',NULL,NULL,NULL,NULL,'2026-11-13 01:15:43','2026-11-14 04:16:56'),(3,'usr-1794559161943','Emergency Caller','+91 99999 10800','','',NULL,NULL,NULL,NULL,'2026-11-13 03:09:21','2026-11-13 03:09:21'),(4,NULL,'Tyson Paul','+919840010801','O+',NULL,'admin@gmail.com',NULL,'$2y$12$EqyZiHeL2I3jb7eMz.MMc.Yhrwf6j2ypAd7mD86Fr3LDX2fC/6KuG',NULL,'2026-11-14 02:50:37','2026-11-16 01:35:21'),(5,'usr-1794644782444','Tyson','+91 8128304510','','',NULL,NULL,NULL,NULL,'2026-11-14 02:56:22','2026-11-14 02:56:22'),(6,'usr-1794745456865','Tyson','+91 8728304510','','',NULL,NULL,NULL,NULL,'2026-11-15 06:54:16','2026-11-15 06:54:16'),(7,'usr-1794752535700','Muhi','+91 9789510300','','',NULL,NULL,NULL,NULL,'2026-11-15 08:52:15','2026-11-15 08:59:09'),(8,'usr-1794810051995','','+91 9876543210','O+','',NULL,NULL,NULL,NULL,'2026-11-16 00:50:51','2026-11-16 00:50:51');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-16 14:01:58
