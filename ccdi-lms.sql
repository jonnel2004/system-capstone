-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ccdi_lms
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
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_code` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `block` varchar(50) NOT NULL DEFAULT '',
  `activity_type` varchar(30) NOT NULL DEFAULT 'File',
  `level_label` varchar(50) NOT NULL DEFAULT 'Easy',
  `file_path` varchar(255) DEFAULT NULL,
  `quiz_data` longtext DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allow_submission` tinyint(1) NOT NULL DEFAULT 1,
  `program` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
INSERT INTO `activities` VALUES (2,11,'COM','CONNECT','3A','File','Easy',NULL,NULL,NULL,'2026-11-20 13:19:49',1,NULL),(4,10,'SE','config','3A','File','Easy',NULL,NULL,NULL,'2026-11-20 13:54:29',1,NULL),(6,10,'se','google laravel','3A','File','Easy',NULL,NULL,'2026-12-31 11:54:00','2026-11-21 02:55:15',1,NULL);
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_notifications`
--

DROP TABLE IF EXISTS `admin_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `target_program` varchar(30) DEFAULT '',
  `target_block` varchar(30) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_notifications`
--

LOCK TABLES `admin_notifications` WRITE;
/*!40000 ALTER TABLE `admin_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `block_subjects`
--

DROP TABLE IF EXISTS `block_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `block_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `block_name` varchar(20) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `subject_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `block_subjects`
--

LOCK TABLES `block_subjects` WRITE;
/*!40000 ALTER TABLE `block_subjects` DISABLE KEYS */;
INSERT INTO `block_subjects` VALUES (1,'BSIT',NULL,'1a','history','Minor'),(2,'BSIT',NULL,'1a','art app','Minor'),(3,'BSIT',NULL,'1b','programming','Major'),(4,'BSIT',NULL,'1a','prog 1','Major'),(5,'BSIT',NULL,'1a','programing 1','Major'),(6,'BSIT',NULL,'1a','oop','Major'),(7,'BSIT',NULL,'1a','net','Major'),(8,'BSIT',NULL,'1a','it business','Major'),(9,'BSIT',NULL,'1a','communication','Minor'),(10,'BSIT',NULL,'1a','mathmiahes','Minor'),(11,'BSIT',NULL,'','gr','Major'),(12,'BSIT',NULL,'','hyrty','Major'),(13,'BSIS',NULL,'','information technology','Major'),(14,'BSIS',NULL,'','programming','Major'),(15,'BSIS',NULL,'','tech','Major'),(16,'BSOAD',NULL,'','gdfgrf','Major'),(17,'BSOAD',NULL,'','fhyrtyrtfu','Major'),(18,'BSOAD',NULL,'','tfrhyruti','Major'),(19,'BSOAD',NULL,'','fhrthut','Major'),(20,'BSCS',NULL,'','dadwaqrf','Minor'),(21,'BSCS',NULL,'','gyhtrutfu','Minor'),(22,'BSCS',NULL,'','fhrjtj','Minor'),(23,'BSCS',NULL,'','gyutyik','Minor'),(24,'BSCS',NULL,'','hukyiu','Minor'),(25,'BSCS',NULL,'','jlul','Minor'),(26,'BSCS',NULL,'','tjyk','Minor'),(27,'BSIT','1st Year','1B','programing','Major'),(28,'BSIT','1st Year','1B','it businness','Major'),(29,'BSIT','1st Year','1B','fewrewtr','Major'),(30,'BSIT','1st Year','1B','yujiyui','Major'),(31,'BSIT','1st Year','1B','uytiy','Major'),(32,'BSCS','1st Year','1A','ggiuyi','Major'),(33,'BSCS','1st Year','1A','opopoi','Major'),(34,'BSCS','1st Year','1A','pipup','Major'),(35,'BSCS','1st Year','1A','ihoi','Major'),(36,'BSCS','1st Year','1A','ggiuyi','Major'),(37,'BSCS','1st Year','1A','opopoi','Major'),(38,'BSCS','1st Year','1A','pipup','Major'),(39,'BSCS','1st Year','1A','ihoi','Major'),(40,'BSCS','1st Year','1B','r44r5345rt5','Minor'),(41,'BSCS','1st Year','1B','yi8u7u','Minor'),(42,'BSCS','1st Year','1B','7u6io','Minor'),(43,'BSIT','1st Year','1C','rt5y4y5','Minor'),(44,'BSIT','1st Year','1C','rt5y4y5','Minor'),(45,'BSIT','1st Year','1C','rt5y4y5','Minor'),(46,'BSCS','3rd Year','3A','programming','Major');
/*!40000 ALTER TABLE `block_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `title` varchar(150) NOT NULL,
  `student_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'BSCS','4-YR BS COMPUTER SYSTEM (BSCS)',2560),(2,'BSIT','4-YR INFORMATION TECHNOLOGY (BSIT)',5560),(3,'BSIS','4-YR BS INFORMATION SYSTEM (BSIS)',1200),(4,'BSOAD','4-YR BS OFFICE ADMINISTRATION (BSOAD)',3500);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `next_activity` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,1,1,75,'Quiz - Trees (Due in 2 hrs)'),(2,1,2,60,'Project Submission (Due Tomorrow)');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `live_sessions`
--

DROP TABLE IF EXISTS `live_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(255) NOT NULL DEFAULT '',
  `block` varchar(100) NOT NULL DEFAULT '',
  `day_name` varchar(100) NOT NULL DEFAULT '',
  `time_slot` varchar(100) NOT NULL DEFAULT '',
  `room` varchar(100) NOT NULL DEFAULT '',
  `session_mode` varchar(50) NOT NULL DEFAULT 'Face to Face',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `live_sessions`
--

LOCK TABLES `live_sessions` WRITE;
/*!40000 ALTER TABLE `live_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `live_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) DEFAULT NULL,
  `sender_role` varchar(20) NOT NULL DEFAULT 'system',
  `target_role` varchar(20) NOT NULL DEFAULT 'student',
  `recipient_id` int(11) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `block` varchar(50) DEFAULT NULL,
  `notif_type` varchar(40) NOT NULL DEFAULT 'announcement',
  `title` varchar(200) NOT NULL DEFAULT '',
  `user_id` int(11) DEFAULT NULL,
  `type` enum('New Activity','Deadline Reminder','New Material') NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,NULL,'system','student',NULL,NULL,NULL,'announcement','',1,'New Activity','SE','Data Structures: Google authentication',0,'2026-11-14 12:42:10'),(2,NULL,'system','student',NULL,NULL,NULL,'announcement','',1,'Deadline Reminder','IPT','Web Dev: Project due in 24 hours!',0,'2026-11-14 12:42:10'),(3,NULL,'system','student',NULL,NULL,NULL,'announcement','',1,'New Material','SIA','Intro to AI: Handout for Week 8 uploaded',0,'2026-11-14 12:42:10'),(8,11,'teacher','student',NULL,'BSCS','3A','announcement','exam',NULL,'','','pls',0,'2026-11-23 14:54:20'),(9,10,'teacher','student',NULL,'BSCS','3A','announcement','review',NULL,'','','review for Se',0,'2026-11-23 15:00:16'),(10,11,'teacher','student',NULL,'BSCS','3A','announcement','Sleep',NULL,'','','activity is done',0,'2026-11-23 15:09:19'),(11,11,'teacher','student',NULL,'BSCS','3A','announcement','ok',NULL,'','','yes',0,'2026-11-23 15:12:23');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_schedules`
--

DROP TABLE IF EXISTS `room_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `room_name` varchar(50) NOT NULL,
  `day_of_week` varchar(20) DEFAULT 'Monday',
  `program_code` varchar(30) DEFAULT 'BSIT',
  `year_level` varchar(30) DEFAULT '1st Year',
  `subject_name` varchar(120) DEFAULT NULL,
  `block_name` varchar(60) DEFAULT NULL,
  `subject_block` varchar(100) NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `session_mode` varchar(30) DEFAULT 'Face to Face',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_schedules`
--

LOCK TABLES `room_schedules` WRITE;
/*!40000 ALTER TABLE `room_schedules` DISABLE KEYS */;
INSERT INTO `room_schedules` VALUES (2,NULL,'lab 1','Monday','BSIT','1st Year','ipt / 2e','','ipt / 2e','12; -1','Face to Face'),(4,NULL,'LAB1','TUSDAY','BSIT','1st Year','BLK 4','','BLK 4','1;00 - 5;00','Face to Face'),(5,NULL,'101','Wednesday','BSIT','1st Year','HIP','','HIP','7;00- 9;00AM','Face to Face'),(6,NULL,'102','Wednesday','BSIT','1st Year','IPT','','IPT','7:00 AM - 9;00AM','Face to Face'),(7,NULL,'101','Wednesday','BSIT','1st Year','ipt/blk 3b','','ipt/blk 3b','9:00am- 11:30am','Face to Face'),(8,NULL,'101','Monday','BSIT','1st Year','programming 3A','','programming 3A','8;00 pm','Face to Face'),(9,NULL,'202','Monday','BSIT','1st Year','programming 3A','','programming 3A','7:30 am to 10:00','Face to Face'),(10,NULL,'303','Saturday','BSIT','1st Year','steno','','steno','7:00 - 10:00','Face to Face'),(11,NULL,'lab 2','Monday','BSIT','1st Year','net','','net','8;00 to 9;00','Face to Face'),(12,11,'lab 1','Monday','BSIT','1st Year','net','','net','7;00 9;00','Face to Face'),(13,11,'101','Monday','BSIT','1st Year','software engineering bscs 3a','','software engineering bscs 3a','9:00 10:00','Face to Face'),(14,11,'202','Monday','BSIT','1st Year','sia','3A','sia - Block 3A','3;00 5;00','Face to Face'),(15,11,'303','Monday','BSIT','3rd Year','net','3C','net - BSIT 3rd Year - Block 3C','7;00 9;00','Face to Face');
/*!40000 ALTER TABLE `room_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_activities`
--

DROP TABLE IF EXISTS `student_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_name` varchar(255) DEFAULT 'GOOGLE AUTHENTICATE Activity',
  `allow_submission` tinyint(1) NOT NULL DEFAULT 1,
  `progress` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `file_opened` tinyint(1) NOT NULL DEFAULT 0,
  `opened_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_activities`
--

LOCK TABLES `student_activities` WRITE;
/*!40000 ALTER TABLE `student_activities` DISABLE KEYS */;
INSERT INTO `student_activities` VALUES (1,10,'SE',1,68,'2026-11-19 12:53:40',1,'2026-11-19 20:53:00'),(2,10,'com',1,100,'2026-11-20 11:46:38',1,'2026-11-19 23:16:52'),(3,10,'reop',1,100,'2026-11-20 11:46:33',1,'2026-11-19 23:37:18');
/*!40000 ALTER TABLE `student_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_activity_progress`
--

DROP TABLE IF EXISTS `student_activity_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_activity_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `status_label` varchar(50) NOT NULL DEFAULT 'Not Started',
  `last_action` varchar(50) NOT NULL DEFAULT 'none',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submission_note` text DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_activity` (`student_id`,`activity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_activity_progress`
--

LOCK TABLES `student_activity_progress` WRITE;
/*!40000 ALTER TABLE `student_activity_progress` DISABLE KEYS */;
INSERT INTO `student_activity_progress` VALUES (1,10,6,100,'100% Done','opened','2026-11-21 03:25:30',NULL,NULL,NULL),(5,11,6,100,'100% Done (No Re-attempt • Read Only)','mark_done','2026-11-21 03:08:03',NULL,NULL,NULL),(18,10,4,1,'Working','opened','2026-11-23 11:48:06',NULL,NULL,NULL);
/*!40000 ALTER TABLE `student_activity_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_enrollments`
--

DROP TABLE IF EXISTS `student_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(150) DEFAULT NULL,
  `block_name` varchar(50) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_enrollments`
--

LOCK TABLES `student_enrollments` WRITE;
/*!40000 ALTER TABLE `student_enrollments` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `title` varchar(100) NOT NULL,
  `instructor` varchar(100) NOT NULL,
  `card_theme` varchar(50) DEFAULT 'blue',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'SE','Software Engineering','Mr. Ben','blue'),(2,'IPT','Integrative Programming','Mr. Chan','green'),(3,'SIA','System Integration & Architecture','Mr. Joe','purple'),(4,'NET','Networking 2','Mr. Ken','red'),(5,'ENTREP','Technopreneurship','Mrs. Cia','lightblue'),(6,'HUM','Arts and Humanities','Mr. Joey','gradient');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_assignments`
--

DROP TABLE IF EXISTS `teacher_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `course_code` varchar(20) DEFAULT NULL,
  `block_name` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_assignments`
--

LOCK TABLES `teacher_assignments` WRITE;
/*!40000 ALTER TABLE `teacher_assignments` DISABLE KEYS */;
INSERT INTO `teacher_assignments` VALUES (3,9,'art app','BSCS','2v'),(5,9,'prog 1','BSIT','2B'),(6,9,'prog 1','BSIT','2B'),(7,12,'software enginnering','BSIS','3b'),(10,9,'SIA','BSCS','3A'),(11,9,'SIA','BSCS','3A'),(17,12,'SETNO','BSOAD','1a'),(18,12,'SETNO','BSOAD','1a'),(20,11,'SOFTWARE ENGINEERING','BSIT','3A'),(21,11,'SOFTWARE ENGINEERING','BSCS','3B'),(23,11,'SOFTWARE ENGENEERING','BSIS','4A'),(24,11,'software engineering','BSCS','3C'),(26,9,'ardunino','BSCS','3A'),(28,12,'programming 3','BSCS','3A');
/*!40000 ALTER TABLE `teacher_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_blocks`
--

DROP TABLE IF EXISTS `teacher_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_title` varchar(100) DEFAULT NULL,
  `course_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_blocks`
--

LOCK TABLES `teacher_blocks` WRITE;
/*!40000 ALTER TABLE `teacher_blocks` DISABLE KEYS */;
INSERT INTO `teacher_blocks` VALUES (1,'Block 3B','Information Technology','BSIT'),(2,'Block 3C','Information Technology','BSIT'),(3,'Block 2A','Information Technology','BSIT'),(4,'Block 1C','Computer Science','BSCS');
/*!40000 ALTER TABLE `teacher_blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_created_activities`
--

DROP TABLE IF EXISTS `teacher_created_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_created_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `course_code` varchar(100) DEFAULT NULL,
  `block_name` varchar(100) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `allow_submission` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_created_activities`
--

LOCK TABLES `teacher_created_activities` WRITE;
/*!40000 ALTER TABLE `teacher_created_activities` DISABLE KEYS */;
INSERT INTO `teacher_created_activities` VALUES (1,11,'cam','SE','4',NULL,'',1,'2026-11-19 20:48:10'),(2,11,'net','SE','4',NULL,'',1,'2026-11-19 20:49:13'),(3,11,'net','SE','4',NULL,'',1,'2026-11-19 20:49:13'),(4,11,'sell','SE','3B',NULL,'',1,'2026-11-19 20:52:37'),(5,11,'czm','SE','3B',NULL,'',1,'2026-11-19 21:05:55'),(6,11,'czm','SE','3B',NULL,'',1,'2026-11-19 21:05:55'),(7,11,'com','SE','3A',NULL,'',1,'2026-11-19 21:07:05'),(8,11,'reop','ENTREP','3A',NULL,'uploads/1795102618MULTO2.pptx',1,'2026-11-19 23:36:58'),(9,12,'stenoi','ENTREP','3B',NULL,'',1,'2026-11-20 15:03:24'),(10,12,'computer lab','ENTREP','1B',NULL,'',1,'2026-11-20 15:04:05'),(11,11,'com','ENTREP','3A',NULL,'',1,'2026-11-20 18:53:12'),(12,11,'com','ENTREP','3A',NULL,'',1,'2026-11-20 18:53:12');
/*!40000 ALTER TABLE `teacher_created_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_uploads`
--

DROP TABLE IF EXISTS `teacher_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `block_name` varchar(100) NOT NULL,
  `activity_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_uploads`
--

LOCK TABLES `teacher_uploads` WRITE;
/*!40000 ALTER TABLE `teacher_uploads` DISABLE KEYS */;
INSERT INTO `teacher_uploads` VALUES (1,11,'SE','3B','',NULL,NULL,'2026-11-19 12:42:11'),(2,11,'SE','4','',NULL,NULL,'2026-11-19 12:48:10'),(3,11,'SE','4','',NULL,NULL,'2026-11-19 12:49:13'),(4,11,'SE','4','',NULL,NULL,'2026-11-19 12:49:13'),(5,11,'SE','3B','',NULL,NULL,'2026-11-19 12:52:37');
/*!40000 ALTER TABLE `teacher_uploads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course` varchar(100) DEFAULT 'Bachelor of Science in Information Technology (BSIT)',
  `block` varchar(20) DEFAULT 'Block - 3B',
  `role` enum('student','teacher','admin') DEFAULT 'student',
  `program` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `STATUS` varchar(20) DEFAULT 'Approved',
  `department` varchar(50) DEFAULT 'BSIT Department',
  `year_level` varchar(20),
  `profile_pic` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_student_id` (`student_id`),
  UNIQUE KEY `uq_student_number` (`student_number`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Juan Dela Cruz','juan@ccdi.edu.ph','123456','Bachelor of Science in Information Technology (BSIT)','Block - 3c','student','','Juan Dela Cruz','Approved','BSIT Department','3rd Year',NULL,'c-2026-00001',NULL),(2,'jonnel cereza','cerezajonnel1@gmail.com','jonnel','4-yr Bachelor of Science in Information Technology (BSIT)','Block - 3C','student',NULL,'jonnel cereza','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(4,'nel','cerezajonnel69@gmail.com','jonnel','4-yr Bachelor of Science in Information Technology (BSIT)','Block - 3B','student',NULL,'nel','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(7,'john','arangocereza8@gmail.com','cereza','4-yr Bachelor of Science in Information Technology (BSIT)','Block - 3B','student',NULL,'john','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(8,'jonnel','admin@ccdi.edu.ph','admin123','Administration',NULL,'admin',NULL,'admin','Approved','BSIT Department','3rd Year',NULL,NULL,'CCDI-2026-0008'),(9,'json non','cerezajonnel247@gmail.com','sirsir','Bachelor of Science in Information Technology (BSIT)',NULL,'teacher',NULL,'json non','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(10,'aaron','aaron1@gmail.com','aaron','Bachelor of Science in Computer Science (BSCS)','Block - 3A','student','BSCS','aaron','Approved','BSIT Department','3rd Year',NULL,'c-2026-00002',NULL),(11,'james lebron','lebronjames@gmail.com','james','BS Information Systems',NULL,'teacher',NULL,'james lebron','Approved','BSCS DEPARTMENT','3rd Year','uploads/avatars/teacher_11_1795237541.png',NULL,NULL),(12,'sarah jill','imsarah@gmail.com','sarah','Bachelor of Science in Information Technology (BSIT)',NULL,'teacher',NULL,'sarah jill','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(13,'princess','princess21@gmail.com','princess','Bachelor of Science in Information Technology (BSIT)','Block - 1A','student',NULL,'princess','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(14,'emms','emms@gmail.com','emmss','Bachelor of Science in Office Administration (BSOAD)','Block - 2B','student',NULL,'emms','Approved','BSIT Department','2rd Year',NULL,NULL,NULL),(15,'phixie','phi@gmail.com','phixe','BS Information Systems','Block - 4A','student',NULL,'phixie','Approved','BSIT Department','3rd Year',NULL,NULL,NULL),(16,'jhenel bon','bon@gmail.com','bon','BS Information Systems','Block - 1B','student',NULL,'bon','Approved','BSIT Department',NULL,NULL,NULL,NULL),(17,'neal','neil@gmail.com','neil','BS Information Systems','Block - 1C','student',NULL,'neal','Approved','BSIT Department',NULL,NULL,NULL,NULL),(18,'axel','axel@gmail.com','axel','BS Information Systems','Block - 4B','student',NULL,'axel','Approved','BSIT Department',NULL,NULL,NULL,NULL),(19,'jaems ortal','ortal1@gmail.com','otral','BS Information Systems (BSIS)','Block - 4C','student',NULL,'jaems ortal','Approved','BSIT Department',NULL,NULL,NULL,NULL),(20,'fiona','fionasapatna@gmail.com','fiona','BS Information Systems (BSIS)','Block - 4C','student',NULL,'fiona','Approved','BSIT Department','4th Year',NULL,'C-2026- 00001',NULL),(21,'alexa','alexa@gmail.com','alexa','BS office administration','Block - 4A','student',NULL,'alexa','Approved','BSIT Department','4th Year',NULL,'c-2020-00001',NULL),(22,'jhona','jhona@gmail.com','jhona','BS Information Systems (BSIS)','Block - 3A','student',NULL,'jhona','Approved','BSIT Department','3rd Year',NULL,'c2026-00001','CCDI-2026-0022');
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

-- Dump completed on 2026-11-24 19:09:06
