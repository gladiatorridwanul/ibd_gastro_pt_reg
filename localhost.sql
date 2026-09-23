-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 23, 2026 at 05:19 AM
-- Server version: 10.3.39-MariaDB
-- PHP Version: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ibdgastrodmchbd_pmrms`
--
CREATE DATABASE IF NOT EXISTS `ibdgastrodmchbd_pmrms` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `ibdgastrodmchbd_pmrms`;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity` varchar(100) DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `details`, `ip`, `user_agent`, `created_at`) VALUES
(0, 1, 'auth.login', NULL, NULL, '{\"email\":\"admin@example.com\"}', '103.155.99.237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 14:53:39');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `abdominal_pain` enum('Yes','No') DEFAULT 'No',
  `pain_type` enum('Mild','Moderate','Severe') DEFAULT NULL,
  `diarrhea` enum('Yes','No') DEFAULT 'No',
  `diarrhoea_details` varchar(255) DEFAULT NULL,
  `blood_in_stool` enum('Yes','No') DEFAULT 'No',
  `blood_details` varchar(255) DEFAULT NULL,
  `mucus_in_stool` enum('Yes','No') DEFAULT 'No',
  `mucus_details` varchar(255) DEFAULT NULL,
  `frequency_per_day` int(11) DEFAULT NULL,
  `weight_loss` enum('Yes','No') DEFAULT 'No',
  `weight_loss_kg` decimal(5,2) DEFAULT NULL,
  `weight_loss_duration` varchar(50) DEFAULT NULL,
  `fever` enum('Yes','No') DEFAULT 'No',
  `fever_duration` varchar(100) DEFAULT NULL,
  `fever_grade` enum('High','Low') DEFAULT NULL,
  `sub_acute_intestinal_obstruction` enum('Yes','No') DEFAULT NULL,
  `sub_acute_intestinal_obstruction_details` text DEFAULT NULL,
  `relapses_count` int(11) DEFAULT NULL,
  `last_on_year` int(11) DEFAULT NULL,
  `extraintestinal_set` set('Arthritis','Uveitis','Skin','Others') DEFAULT NULL,
  `extraintestinal_others` varchar(150) DEFAULT NULL,
  `hospitalization` enum('Yes','No') DEFAULT 'No',
  `hospitalization_date` date DEFAULT NULL,
  `hospitalization_reason` enum('Replace','Surgery','Others') DEFAULT NULL,
  `past_surgery` enum('Yes','No') DEFAULT 'No',
  `past_surgery_date` date DEFAULT NULL,
  `past_surgery_details` varchar(255) DEFAULT NULL,
  `family_history_ibd` enum('Yes','No') DEFAULT 'No',
  `family_history_type` varchar(100) DEFAULT NULL,
  `family_history_relations` varchar(100) DEFAULT NULL,
  `comorbid_set` set('DM','HTN','Hypothyroidism','Others') DEFAULT NULL,
  `comorbid_others` varchar(150) DEFAULT NULL,
  `cancer_history` enum('Yes','No') DEFAULT 'No',
  `cancer_specify` varchar(150) DEFAULT NULL,
  `hvi` varchar(255) DEFAULT NULL COMMENT 'HVI - Harvey-Bradshaw Index',
  `partial_mayo` varchar(255) DEFAULT NULL COMMENT 'Partial Mayo Score',
  `mayo_endomorphic` varchar(255) DEFAULT NULL COMMENT 'Mayo Endoscopic Score',
  `mayo_score` varchar(255) DEFAULT NULL COMMENT 'Full Mayo Score',
  `ses_ed` varchar(255) DEFAULT NULL COMMENT 'SES-ED - Simple Endoscopic Score',
  `vceis` varchar(255) DEFAULT NULL COMMENT 'VCEIS - Video Capsule Endoscopy Index',
  `mt_tb` varchar(255) DEFAULT NULL COMMENT 'MT/TB - Mycobacteria Tuberculosis',
  `vaccine` varchar(255) DEFAULT NULL COMMENT 'Vaccine Status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `diarrhoea_other` varchar(255) DEFAULT NULL,
  `blood_other` varchar(255) DEFAULT NULL,
  `mucus_other` varchar(255) DEFAULT NULL,
  `weight_loss_period` varchar(100) DEFAULT NULL,
  `fever_type` varchar(50) DEFAULT NULL,
  `last_one_year` varchar(100) DEFAULT NULL,
  `arthritis` text DEFAULT NULL,
  `uveitis` text DEFAULT NULL,
  `skin` text DEFAULT NULL,
  `extraintestinal_other` text DEFAULT NULL,
  `hospitalization_reason_other` varchar(255) DEFAULT NULL,
  `dm` text DEFAULT NULL,
  `htn` text DEFAULT NULL,
  `hypothyroidism` text DEFAULT NULL,
  `comorbid_other` text DEFAULT NULL,
  `extraintestinal_type` enum('Arthritis','Uveitis','Skin','Other') DEFAULT NULL,
  `arthritis_details` text DEFAULT NULL,
  `uveitis_details` text DEFAULT NULL,
  `skin_details` text DEFAULT NULL,
  `extraintestinal_other_details` text DEFAULT NULL,
  `comorbid_type` enum('DM','HTN','Hypothyroidism','Others') DEFAULT NULL,
  `dm_details` text DEFAULT NULL,
  `htn_details` text DEFAULT NULL,
  `hypothyroidism_details` text DEFAULT NULL,
  `comorbid_other_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `patient_id`, `abdominal_pain`, `pain_type`, `diarrhea`, `diarrhoea_details`, `blood_in_stool`, `blood_details`, `mucus_in_stool`, `mucus_details`, `frequency_per_day`, `weight_loss`, `weight_loss_kg`, `weight_loss_duration`, `fever`, `fever_duration`, `fever_grade`, `sub_acute_intestinal_obstruction`, `sub_acute_intestinal_obstruction_details`, `relapses_count`, `last_on_year`, `extraintestinal_set`, `extraintestinal_others`, `hospitalization`, `hospitalization_date`, `hospitalization_reason`, `past_surgery`, `past_surgery_date`, `past_surgery_details`, `family_history_ibd`, `family_history_type`, `family_history_relations`, `comorbid_set`, `comorbid_others`, `cancer_history`, `cancer_specify`, `hvi`, `partial_mayo`, `mayo_endomorphic`, `mayo_score`, `ses_ed`, `vceis`, `mt_tb`, `vaccine`, `created_at`, `diarrhoea_other`, `blood_other`, `mucus_other`, `weight_loss_period`, `fever_type`, `last_one_year`, `arthritis`, `uveitis`, `skin`, `extraintestinal_other`, `hospitalization_reason_other`, `dm`, `htn`, `hypothyroidism`, `comorbid_other`, `extraintestinal_type`, `arthritis_details`, `uveitis_details`, `skin_details`, `extraintestinal_other_details`, `comorbid_type`, `dm_details`, `htn_details`, `hypothyroidism_details`, `comorbid_other_details`) VALUES
(13, 31, 'Yes', 'Moderate', 'Yes', 'testing', 'Yes', 'testing', 'Yes', NULL, 4, 'Yes', 5.00, NULL, 'Yes', '3 days', 'Low', NULL, NULL, NULL, NULL, NULL, NULL, 'Yes', '2025-12-17', '', 'Yes', '2025-12-18', NULL, 'Yes', NULL, NULL, NULL, NULL, 'Yes', 'testing', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:31:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'testing', NULL, NULL, NULL, NULL, 'Uveitis', NULL, 'testing', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 31, 'Yes', 'Moderate', 'Yes', 'testiong', 'Yes', 'testing', 'Yes', NULL, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:43:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 32, NULL, NULL, 'Yes', NULL, NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Yes', '2026-07-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-26 06:34:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 33, 'Yes', 'Moderate', 'No', NULL, 'Yes', NULL, 'Yes', NULL, NULL, 'Yes', NULL, NULL, 'Yes', NULL, NULL, 'Yes', NULL, 5, NULL, NULL, NULL, 'Yes', NULL, NULL, 'No', NULL, NULL, 'No', NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-22 06:58:07', NULL, NULL, NULL, NULL, NULL, '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Arthritis', NULL, NULL, NULL, NULL, 'HTN', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `current_histories`
--

CREATE TABLE `current_histories` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `chief_complaint` varchar(255) NOT NULL,
  `onset_date` date DEFAULT NULL,
  `duration_text` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `aggravating_factors` varchar(255) DEFAULT NULL,
  `relieving_factors` varchar(255) DEFAULT NULL,
  `associated_symptoms` varchar(255) DEFAULT NULL,
  `red_flags` varchar(255) DEFAULT NULL,
  `general_condition` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bn_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `division_id`, `name`, `bn_name`, `created_at`) VALUES
(1, 1, 'Dhaka', 'ঢাকা', '2026-03-03 17:14:39'),
(2, 1, 'Gazipur', 'গাজীপুর', '2026-03-03 17:14:39'),
(3, 1, 'Narayanganj', 'নারায়ণগঞ্জ', '2026-03-03 17:14:39'),
(4, 1, 'Tangail', 'টাঙ্গাইল', '2026-03-03 17:14:39'),
(5, 1, 'Kishoreganj', 'কিশোরগঞ্জ', '2026-03-03 17:14:39'),
(6, 1, 'Manikganj', 'মানিকগঞ্জ', '2026-03-03 17:14:39'),
(7, 1, 'Munshiganj', 'মুন্সিগঞ্জ', '2026-03-03 17:14:39'),
(8, 1, 'Narsingdi', 'নরসিংদী', '2026-03-03 17:14:39'),
(9, 1, 'Faridpur', 'ফরিদপুর', '2026-03-03 17:14:39'),
(10, 1, 'Gopalganj', 'গোপালগঞ্জ', '2026-03-03 17:14:39'),
(11, 1, 'Madaripur', 'মাদারীপুর', '2026-03-03 17:14:39'),
(12, 1, 'Rajbari', 'রাজবাড়ী', '2026-03-03 17:14:39'),
(13, 1, 'Shariatpur', 'শরীয়তপুর', '2026-03-03 17:14:39'),
(14, 2, 'Chattogram', 'চট্টগ্রাম', '2026-03-03 17:14:39'),
(15, 2, 'Cox\'s Bazar', 'কক্সবাজার', '2026-03-03 17:14:39'),
(16, 2, 'Comilla', 'কুমিল্লা', '2026-03-03 17:14:39'),
(17, 2, 'Noakhali', 'নোয়াখালী', '2026-03-03 17:14:39'),
(18, 2, 'Feni', 'ফেনী', '2026-03-03 17:14:39'),
(19, 2, 'Lakshmipur', 'লক্ষ্মীপুর', '2026-03-03 17:14:39'),
(20, 2, 'Brahmanbaria', 'ব্রাহ্মণবাড়িয়া', '2026-03-03 17:14:39'),
(21, 2, 'Rangamati', 'রাঙ্গামাটি', '2026-03-03 17:14:39'),
(22, 2, 'Khagrachhari', 'খাগড়াছড়ি', '2026-03-03 17:14:39'),
(23, 2, 'Bandarban', 'বান্দরবান', '2026-03-03 17:14:39'),
(24, 2, 'Chandpur', 'চাঁদপুর', '2026-03-03 17:14:39'),
(25, 3, 'Rajshahi', 'রাজশাহী', '2026-03-03 17:14:39'),
(26, 3, 'Bogra', 'বগুড়া', '2026-03-03 17:14:39'),
(27, 3, 'Pabna', 'পাবনা', '2026-03-03 17:14:39'),
(28, 3, 'Natore', 'নাটোর', '2026-03-03 17:14:39'),
(29, 3, 'Sirajganj', 'সিরাজগঞ্জ', '2026-03-03 17:14:39'),
(30, 3, 'Joypurhat', 'জয়পুরহাট', '2026-03-03 17:14:39'),
(31, 3, 'Chapai Nawabganj', 'চাঁপাইনবাবগঞ্জ', '2026-03-03 17:14:39'),
(32, 3, 'Naogaon', 'নওগাঁ', '2026-03-03 17:14:39'),
(33, 4, 'Khulna', 'খুলনা', '2026-03-03 17:14:39'),
(34, 4, 'Jessore', 'যশোর', '2026-03-03 17:14:39'),
(35, 4, 'Kushtia', 'কুষ্টিয়া', '2026-03-03 17:14:39'),
(36, 4, 'Jhenaidah', 'ঝিনাইদহ', '2026-03-03 17:14:39'),
(37, 4, 'Satkhira', 'সাতক্ষীরা', '2026-03-03 17:14:39'),
(38, 4, 'Bagerhat', 'বাগেরহাট', '2026-03-03 17:14:39'),
(39, 4, 'Chuadanga', 'চুয়াডাঙ্গা', '2026-03-03 17:14:39'),
(40, 4, 'Meherpur', 'মেহেরপুর', '2026-03-03 17:14:39'),
(41, 4, 'Narail', 'নড়াইল', '2026-03-03 17:14:39'),
(42, 4, 'Magura', 'মাগুরা', '2026-03-03 17:14:39'),
(43, 5, 'Barishal', 'বরিশাল', '2026-03-03 17:14:39'),
(44, 5, 'Patuakhali', 'পটুয়াখালী', '2026-03-03 17:14:39'),
(45, 5, 'Bhola', 'ভোলা', '2026-03-03 17:14:39'),
(46, 5, 'Pirojpur', 'পিরোজপুর', '2026-03-03 17:14:39'),
(47, 5, 'Barguna', 'বরগুনা', '2026-03-03 17:14:39'),
(48, 5, 'Jhalokati', 'ঝালকাঠি', '2026-03-03 17:14:39'),
(49, 6, 'Sylhet', 'সিলেট', '2026-03-03 17:14:39'),
(50, 6, 'Moulvibazar', 'মৌলভীবাজার', '2026-03-03 17:14:39'),
(51, 6, 'Habiganj', 'হবিগঞ্জ', '2026-03-03 17:14:39'),
(52, 6, 'Sunamganj', 'সুনামগঞ্জ', '2026-03-03 17:14:39'),
(53, 7, 'Rangpur', 'রংপুর', '2026-03-03 17:14:39'),
(54, 7, 'Dinajpur', 'দিনাজপুর', '2026-03-03 17:14:39'),
(55, 7, 'Kurigram', 'কুড়িগ্রাম', '2026-03-03 17:14:39'),
(56, 7, 'Nilphamari', 'নীলফামারী', '2026-03-03 17:14:39'),
(57, 7, 'Lalmonirhat', 'লালমনিরহাট', '2026-03-03 17:14:39'),
(58, 7, 'Panchagarh', 'পঞ্চগড়', '2026-03-03 17:14:39'),
(59, 7, 'Thakurgaon', 'ঠাকুরগাঁও', '2026-03-03 17:14:39'),
(60, 7, 'Gaibandha', 'গাইবান্ধা', '2026-03-03 17:14:39'),
(61, 8, 'Mymensingh', 'ময়মনসিংহ', '2026-03-03 17:14:39'),
(62, 8, 'Netrokona', 'নেত্রকোনা', '2026-03-03 17:14:39'),
(63, 8, 'Jamalpur', 'জামালপুর', '2026-03-03 17:14:39'),
(64, 8, 'Sherpur', 'শেরপুর', '2026-03-03 17:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bn_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `name`, `bn_name`, `created_at`) VALUES
(1, 'Dhaka', 'ঢাকা', '2026-03-03 17:14:11'),
(2, 'Chattogram', 'চট্টগ্রাম', '2026-03-03 17:14:11'),
(3, 'Rajshahi', 'রাজশাহী', '2026-03-03 17:14:11'),
(4, 'Khulna', 'খুলনা', '2026-03-03 17:14:11'),
(5, 'Barishal', 'বরিশাল', '2026-03-03 17:14:11'),
(6, 'Sylhet', 'সিলেট', '2026-03-03 17:14:11'),
(7, 'Rangpur', 'রংপুর', '2026-03-03 17:14:11'),
(8, 'Mymensingh', 'ময়মনসিংহ', '2026-03-03 17:14:11');

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drugs`
--

INSERT INTO `drugs` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Napa', 1, '2026-01-20 05:20:02'),
(2, 'Napa 250mg', 1, '2026-01-20 05:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `drug_histories`
--

CREATE TABLE `drug_histories` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `drug_name` varchar(255) NOT NULL,
  `indication` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `stop_date` date DEFAULT NULL,
  `max_dose` varchar(100) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `response` varchar(255) DEFAULT NULL,
  `adverse_effects` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drug_histories`
--

INSERT INTO `drug_histories` (`id`, `patient_id`, `drug_name`, `indication`, `start_date`, `stop_date`, `max_dose`, `route`, `response`, `adverse_effects`, `created_at`) VALUES
(1, 4, 'Napa', '250MG', '1111-11-11', '1111-11-11', '21', '21', '21', '21', '2026-01-20 10:56:14'),
(2, 4, 'NAPA', '200MG', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20 11:01:11'),
(3, 4, 'NAPA', '250MG', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20 11:02:47'),
(4, 4, 'NAPA', '120', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20 11:04:47'),
(5, 4, 'NAPA', '1112', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20 11:29:52'),
(6, 4, 'PERA', '120', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20 11:39:49'),
(7, 4, 'NAPA', '12', '2012-12-12', '2026-01-20', NULL, NULL, NULL, NULL, '2026-01-20 11:50:53'),
(8, 5, 'Nizoder', '120', '1991-12-30', '2026-01-20', '120', NULL, NULL, 'No', '2026-01-20 16:40:34');

-- --------------------------------------------------------

--
-- Table structure for table `followups`
--

CREATE TABLE `followups` (
  `id` int(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `followup_at` datetime NOT NULL,
  `description` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `followups`
--

INSERT INTO `followups` (`id`, `patient_id`, `followup_at`, `description`, `treatment`, `created_at`) VALUES
(10, 32, '2026-10-16 13:10:00', NULL, NULL, '2026-09-22 07:10:38');

-- --------------------------------------------------------

--
-- Table structure for table `ibd_diagnoses`
--

CREATE TABLE `ibd_diagnoses` (
  `id` int(20) NOT NULL,
  `patient_id` int(20) NOT NULL,
  `diagnosis` set('Ulcerative colitis','Crohn''s disease','Indeterminate','Other') DEFAULT NULL,
  `other_diagnosis` text DEFAULT NULL,
  `onset_date` date DEFAULT NULL,
  `diagnosis_date` date DEFAULT NULL,
  `patient_type` varchar(50) DEFAULT NULL,
  `diagnostic_criteria` set('Clinical','Endoscopic','Radiologic','Histologic') DEFAULT NULL,
  `uc_location` set('E1','E2','E3') DEFAULT NULL,
  `cd_location_set` set('L1','L2','L3','L4') DEFAULT NULL,
  `upper_gi` set('Yes','No','Isolated') DEFAULT NULL,
  `cd_behavior` set('B1','B2','B3') DEFAULT NULL,
  `perianal_disease` enum('Yes','No') DEFAULT NULL,
  `perianal_other` varchar(255) DEFAULT NULL,
  `resident_3m` enum('Yes','No') DEFAULT NULL,
  `resident_other` varchar(255) DEFAULT NULL,
  `out_of_country_visits` enum('Yes','No') DEFAULT NULL,
  `out_country_other` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ibd_diagnoses`
--

INSERT INTO `ibd_diagnoses` (`id`, `patient_id`, `diagnosis`, `other_diagnosis`, `onset_date`, `diagnosis_date`, `patient_type`, `diagnostic_criteria`, `uc_location`, `cd_location_set`, `upper_gi`, `cd_behavior`, `perianal_disease`, `perianal_other`, `resident_3m`, `resident_other`, `out_of_country_visits`, `out_country_other`, `created_at`) VALUES
(22, 30, 'Crohn\'s disease', NULL, '2026-01-14', '2026-01-31', 'inpatient', 'Endoscopic', 'E2,E3', 'L2', 'No', 'B2', 'Yes', 'testing', 'Yes', 'testing', 'No', NULL, '2026-05-20 02:58:57'),
(23, 31, 'Crohn\'s disease', NULL, '2026-01-06', '2026-01-15', 'inpatient', 'Clinical', 'E2,E3', 'L2,L3', 'Yes', 'B2', 'Yes', 'tesing', 'Yes', 'testing', NULL, NULL, '2026-05-20 03:26:24'),
(24, 32, 'Ulcerative colitis', NULL, NULL, NULL, NULL, 'Endoscopic', 'E2', 'L4', NULL, 'B2', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-26 06:33:55'),
(25, 33, 'Crohn\'s disease', 'DM', '2017-09-22', '2022-09-22', 'Opd', 'Clinical,Endoscopic,Radiologic,Histologic', NULL, 'L2', 'No', 'B2', 'Yes', NULL, 'Yes', NULL, 'No', NULL, '2026-09-22 06:56:00');

-- --------------------------------------------------------

--
-- Table structure for table `investigations`
--

CREATE TABLE `investigations` (
  `id` int(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `cbc_hb` varchar(50) DEFAULT NULL COMMENT 'Hb(g/dl)',
  `cbc_esr` varchar(50) DEFAULT NULL COMMENT 'ESR (mm in 1st hr)',
  `cbc_tlc` varchar(50) DEFAULT NULL,
  `dlc` varchar(50) DEFAULT NULL,
  `platelets` varchar(50) DEFAULT NULL,
  `crp` varchar(50) DEFAULT NULL,
  `s_albumin` varchar(50) DEFAULT NULL,
  `fecal_calprotectin` varchar(50) DEFAULT NULL,
  `upper_git` varchar(255) DEFAULT NULL COMMENT 'Upper GIT',
  `endoscopy` varchar(255) DEFAULT NULL COMMENT 'Endoscopy',
  `colonoscopy` varchar(255) DEFAULT NULL COMMENT 'Colonoscopy',
  `ileoscopy` varchar(255) DEFAULT NULL COMMENT 'Ileoscopy',
  `colonoscopy_ileostomy` varchar(255) DEFAULT NULL,
  `histopathology` varchar(255) DEFAULT NULL,
  `usg` varchar(255) DEFAULT NULL,
  `ct_scan` varchar(255) DEFAULT NULL,
  `enterography` varchar(255) DEFAULT NULL COMMENT 'Enterography (MRE/CTE)',
  `endoscopic_ultrasound` varchar(255) DEFAULT NULL COMMENT 'Endoscopic Ultrasound (EUS)',
  `enteroscopy` varchar(255) DEFAULT NULL,
  `others` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investigations`
--

INSERT INTO `investigations` (`id`, `patient_id`, `cbc_hb`, `cbc_esr`, `cbc_tlc`, `dlc`, `platelets`, `crp`, `s_albumin`, `fecal_calprotectin`, `upper_git`, `endoscopy`, `colonoscopy`, `ileoscopy`, `colonoscopy_ileostomy`, `histopathology`, `usg`, `ct_scan`, `enterography`, `endoscopic_ultrasound`, `enteroscopy`, `others`, `created_at`) VALUES
(4, 25, '14.2', '15', NULL, NULL, '246', '0.6', NULL, '627', 'entral gastric errosion', 'entral gastric errosion', 'jejunal ulcerated lession', NULL, NULL, 'chronic non specific enteritis', NULL, NULL, 'jejunal enteritis with early CD', NULL, NULL, NULL, '2026-03-16 04:58:20'),
(5, 27, '11.9', '70', NULL, NULL, '389000', '10.3', NULL, '946', NULL, NULL, 'CD, TB', NULL, NULL, 'Infectious colitis', 'NORMAL', NULL, 'Multiple small bowel wall thickining, causing luminal narrowing and sourrounding fat stranding present possibility are due to CD', NULL, NULL, NULL, '2026-04-20 05:37:49'),
(6, 29, '8.8', NULL, NULL, NULL, '601000', '5.1', NULL, '1234.58', NULL, NULL, 'Ulcerative colitis', NULL, NULL, 'Chronic active colitis with ulcer', NULL, NULL, 'Perilesional mesenteric lymphadenopathy', NULL, NULL, NULL, '2026-05-09 06:03:32');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(100) NOT NULL,
  `ibd_reg_no` varchar(50) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` enum('M','F') NOT NULL,
  `dob` date DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Bangladesh',
  `religion` varchar(50) DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `education` varchar(100) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `perm_address` varchar(255) DEFAULT NULL,
  `perm_division_id` int(11) DEFAULT NULL,
  `perm_district_id` int(11) DEFAULT NULL,
  `perm_upazila_id` int(11) DEFAULT NULL,
  `pres_address` varchar(255) DEFAULT NULL,
  `pres_division_id` int(11) DEFAULT NULL,
  `pres_district_id` int(11) DEFAULT NULL,
  `pres_upazila_id` int(11) DEFAULT NULL,
  `father_husband_name` varchar(120) DEFAULT NULL,
  `father_husband_occupation` varchar(100) DEFAULT NULL,
  `mother_name` varchar(120) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `ibd_reg_no`, `name`, `age`, `sex`, `dob`, `height_cm`, `weight_kg`, `bmi`, `nationality`, `religion`, `national_id`, `occupation`, `education`, `contact_number`, `email`, `perm_address`, `perm_division_id`, `perm_district_id`, `perm_upazila_id`, `pres_address`, `pres_division_id`, `pres_district_id`, `pres_upazila_id`, `father_husband_name`, `father_husband_occupation`, `mother_name`, `mother_occupation`, `created_by`, `created_at`) VALUES
(30, NULL, 'test', 34, 'M', '1992-04-08', 167.00, 74.00, 26.50, 'Bangladesh', 'Islam', NULL, 'Service', 'Higher Secondary', '01989996898', NULL, 'Flat 3A, House 57', 4, NULL, NULL, 'Flat 3A, House 57, Road 12', 1, 1, 1, 'N/A', NULL, 'N/A', NULL, 1, '2026-05-20 02:56:37'),
(31, NULL, 'testing', 35, 'M', '1991-04-08', 167.00, 75.00, 26.90, 'Bangladesh', 'Islam', NULL, 'Service', 'Higher Secondary', '01989996798', NULL, 'house 34, road-4', 1, 1, 1, 'house 34, road-4', 1, 1, 1, 'NA', NULL, 'NA', NULL, 1, '2026-05-20 03:24:54'),
(32, NULL, 'testing', 0, 'M', '2026-07-26', 172.00, 72.00, 24.30, 'Bangladesh', 'Islam', 'N/A', 'Service', 'Higher Secondary', NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-26 03:22:13'),
(33, 'CD-009', 'Habiba', 36, 'F', '1990-07-22', 162.00, 75.00, 28.60, 'Bangladesh', 'Islam', '3738908270', 'Student', 'Tertiary', '01937273741', 'habiba121@gmail.com', 'Dmch', 1, 1, 7, 'Dmch', 1, NULL, NULL, 'Jamai', 'Driver', 'Mother', NULL, 1, '2026-09-22 06:52:34');

-- --------------------------------------------------------

--
-- Table structure for table `patient_attachments`
--

CREATE TABLE `patient_attachments` (
  `id` bigint(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `file_type` varchar(50) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vaccines`
--

CREATE TABLE `patient_vaccines` (
  `id` int(20) NOT NULL,
  `batch_id` varchar(50) DEFAULT NULL,
  `patient_id` int(100) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `dose_number` varchar(50) DEFAULT NULL,
  `dose_schedule` varchar(100) DEFAULT NULL,
  `dose_given_date` date DEFAULT NULL,
  `next_dose_date` date DEFAULT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `administered_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Pending','Completed','Overdue','Scheduled') DEFAULT 'Pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_vaccines`
--

INSERT INTO `patient_vaccines` (`id`, `batch_id`, `patient_id`, `vaccine_name`, `dose_number`, `dose_schedule`, `dose_given_date`, `next_dose_date`, `batch_no`, `administered_by`, `remarks`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BATCH_20260922125936_6ab22758a9f93', 33, 'Pneumococcal polysaccharide vaccine (PPSV23)', NULL, '1 amp IM stat (Every 5 years) [2 months after PCV-13]', NULL, NULL, '', 1, '', 'Pending', 1, '2026-09-22 06:59:36', NULL),
(2, 'BATCH_20260922131132_6ab22a243ebec', 32, 'Influenza vaccine', NULL, '1 amp IM stat (Every year)', NULL, NULL, '', 1, '', 'Pending', 1, '2026-09-22 07:11:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `category` enum('menu','button','module','export') NOT NULL DEFAULT 'button',
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `label`, `category`, `module`, `created_at`) VALUES
(1, 'menu.dashboard', 'Dashboard Menu', 'menu', 'dashboard', '2026-03-03 23:58:43'),
(2, 'menu.patients', 'Patient Management Menu', 'menu', 'patients', '2026-03-03 23:58:43'),
(3, 'menu.patients.add', 'Add Patient Menu', 'menu', 'patients', '2026-03-03 23:58:43'),
(4, 'menu.patients.manage', 'Manage Patients Menu', 'menu', 'patients', '2026-03-03 23:58:43'),
(5, 'menu.patients.followup', 'Follow-up Patients Menu', 'menu', 'patients', '2026-03-03 23:58:43'),
(6, 'button.patient.add', 'Add Patient Button', 'button', 'patients', '2026-03-03 23:58:43'),
(7, 'button.patient.edit', 'Edit Patient Button', 'button', 'patients', '2026-03-03 23:58:43'),
(8, 'button.patient.delete', 'Delete Patient Button', 'button', 'patients', '2026-03-03 23:58:43'),
(9, 'button.patient.view', 'View Patient Button', 'button', 'patients', '2026-03-03 23:58:43'),
(10, 'button.patient.fullprofile', 'Full Profile Button', 'button', 'patients', '2026-03-03 23:58:43'),
(11, 'button.patient.export', 'Export Patient Data', 'button', 'patients', '2026-03-03 23:58:43'),
(12, 'menu.modules.ibd', 'IBD Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(13, 'menu.modules.complaints', 'Complaints Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(14, 'menu.modules.treatment', 'Treatment Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(15, 'menu.modules.investigations', 'Investigations Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(16, 'menu.modules.socio', 'Socioeconomic Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(17, 'menu.modules.pregnancy', 'Pregnancy Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(18, 'menu.modules.followup', 'Follow-up Module Menu', 'menu', 'modules', '2026-03-03 23:58:43'),
(19, 'button.module.ibd.add', 'Add IBD Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(20, 'button.module.ibd.edit', 'Edit IBD Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(21, 'button.module.ibd.delete', 'Delete IBD Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(22, 'button.module.complaints.add', 'Add Complaints Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(23, 'button.module.complaints.edit', 'Edit Complaints Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(24, 'button.module.complaints.delete', 'Delete Complaints Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(25, 'button.module.treatment.add', 'Add Treatment Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(26, 'button.module.treatment.edit', 'Edit Treatment Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(27, 'button.module.treatment.delete', 'Delete Treatment Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(28, 'button.module.investigations.add', 'Add Investigations Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(29, 'button.module.investigations.edit', 'Edit Investigations Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(30, 'button.module.investigations.delete', 'Delete Investigations Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(31, 'button.module.socio.add', 'Add Socioeconomic Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(32, 'button.module.socio.edit', 'Edit Socioeconomic Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(33, 'button.module.socio.delete', 'Delete Socioeconomic Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(34, 'button.module.pregnancy.add', 'Add Pregnancy Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(35, 'button.module.pregnancy.edit', 'Edit Pregnancy Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(36, 'button.module.pregnancy.delete', 'Delete Pregnancy Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(37, 'button.module.followup.add', 'Add Follow-up Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(38, 'button.module.followup.edit', 'Edit Follow-up Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(39, 'button.module.followup.delete', 'Delete Follow-up Entry', 'button', 'modules', '2026-03-03 23:58:43'),
(40, 'menu.users', 'User Management Menu', 'menu', 'users', '2026-03-03 23:58:43'),
(41, 'menu.users.list', 'User List Menu', 'menu', 'users', '2026-03-03 23:58:43'),
(42, 'menu.roles', 'Role Management Menu', 'menu', 'users', '2026-03-03 23:58:43'),
(43, 'menu.permissions', 'Permissions Menu', 'menu', 'users', '2026-03-03 23:58:43'),
(44, 'button.user.create', 'Create User', 'button', 'users', '2026-03-03 23:58:43'),
(45, 'button.user.edit', 'Edit User', 'button', 'users', '2026-03-03 23:58:43'),
(46, 'button.user.delete', 'Delete User', 'button', 'users', '2026-03-03 23:58:43'),
(47, 'button.user.view', 'View User', 'button', 'users', '2026-03-03 23:58:43'),
(48, 'button.user.activate', 'Activate/Deactivate User', 'button', 'users', '2026-03-03 23:58:43'),
(49, 'button.user.permissions', 'Manage User Permissions', 'button', 'users', '2026-03-03 23:58:43'),
(50, 'button.role.create', 'Create Role', 'button', 'users', '2026-03-03 23:58:43'),
(51, 'button.role.edit', 'Edit Role', 'button', 'users', '2026-03-03 23:58:43'),
(52, 'button.role.delete', 'Delete Role', 'button', 'users', '2026-03-03 23:58:43'),
(53, 'button.role.permissions', 'Manage Role Permissions', 'button', 'users', '2026-03-03 23:58:43'),
(54, 'menu.audit', 'Audit Trail Menu', 'menu', 'audit', '2026-03-03 23:58:43'),
(55, 'menu.audit.logs', 'View Audit Logs', 'menu', 'audit', '2026-03-03 23:58:43'),
(56, 'menu.audit.sessions', 'View User Sessions', 'menu', 'audit', '2026-03-03 23:58:43'),
(57, 'button.audit.export', 'Export Audit Logs', 'button', 'audit', '2026-03-03 23:58:43'),
(58, 'menu.exports', 'Exports Menu', 'menu', 'exports', '2026-03-03 23:58:43'),
(59, 'export.excel', 'Export to Excel', 'export', 'exports', '2026-03-03 23:58:43'),
(60, 'export.pdf', 'Export to PDF', 'export', 'exports', '2026-03-03 23:58:43'),
(61, 'export.csv', 'Export to CSV', 'export', 'exports', '2026-03-03 23:58:43'),
(62, 'menu.settings', 'Settings Menu', 'menu', 'settings', '2026-03-03 23:58:43'),
(63, 'button.settings.general', 'General Settings', 'button', 'settings', '2026-03-03 23:58:43'),
(64, 'button.settings.backup', 'Backup Settings', 'button', 'settings', '2026-03-03 23:58:43'),
(80, 'menu.modules.vaccine', 'Vaccine Module Menu', 'menu', 'modules', '2026-05-20 03:12:32'),
(81, 'button.module.vaccine.add', 'Add Vaccine Entry', 'button', 'modules', '2026-05-20 03:12:32'),
(82, 'button.module.vaccine.edit', 'Edit Vaccine Entry', 'button', 'modules', '2026-05-20 03:12:32'),
(83, 'button.module.vaccine.delete', 'Delete Vaccine Entry', 'button', 'modules', '2026-05-20 03:12:32'),
(84, 'button.module.vaccine.view', 'View Vaccine Entry', 'button', 'modules', '2026-05-20 03:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `pregnancies`
--

CREATE TABLE `pregnancies` (
  `id` int(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `pregnancy_outcome` enum('Normal','Abortion','Premature Delivery','Still Birth','Others') DEFAULT NULL,
  `pregnancy_outcome_notes` text DEFAULT NULL,
  `mode_of_delivery` enum('NVD','LUCS','Others') DEFAULT NULL,
  `mode_of_delivery_notes` text DEFAULT NULL,
  `history` text DEFAULT NULL,
  `abortion` enum('Yes','No') DEFAULT 'No',
  `abortion_details` varchar(255) DEFAULT NULL,
  `congenital_disorder` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pregnancies`
--

INSERT INTO `pregnancies` (`id`, `patient_id`, `pregnancy_outcome`, `pregnancy_outcome_notes`, `mode_of_delivery`, `mode_of_delivery_notes`, `history`, `abortion`, `abortion_details`, `congenital_disorder`, `created_at`) VALUES
(8, 31, NULL, NULL, NULL, NULL, 'testing', 'Yes', 'testiung', 'testing', '2026-05-20 03:32:33');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Admin', 'Full system access with all permissions', '2026-03-04 05:58:43'),
(2, 'Doctor', 'Clinical access with patient management', '2026-03-04 05:58:43'),
(3, 'Medical Staff', 'Limited clinical access - view and add only', '2026-03-04 05:58:43'),
(4, 'Receptionist', 'Front desk - patient registration and viewing', '2026-03-04 05:58:43'),
(5, 'Viewer', 'Read-only access to patient records', '2026-03-04 05:58:43');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES
(1, 1, '2026-03-04 00:21:32'),
(1, 2, '2026-03-04 00:21:32'),
(1, 3, '2026-03-04 00:21:32'),
(1, 4, '2026-03-04 00:21:32'),
(1, 5, '2026-03-04 00:21:32'),
(1, 6, '2026-03-04 00:21:32'),
(1, 7, '2026-03-04 00:21:32'),
(1, 8, '2026-03-04 00:21:32'),
(1, 9, '2026-03-04 00:21:32'),
(1, 10, '2026-03-04 00:21:32'),
(1, 11, '2026-03-04 00:21:32'),
(1, 12, '2026-03-04 00:21:32'),
(1, 13, '2026-03-04 00:21:32'),
(1, 14, '2026-03-04 00:21:32'),
(1, 15, '2026-03-04 00:21:32'),
(1, 16, '2026-03-04 00:21:32'),
(1, 17, '2026-03-04 00:21:32'),
(1, 18, '2026-03-04 00:21:32'),
(1, 19, '2026-03-04 00:21:32'),
(1, 20, '2026-03-04 00:21:32'),
(1, 21, '2026-03-04 00:21:32'),
(1, 22, '2026-03-04 00:21:32'),
(1, 23, '2026-03-04 00:21:32'),
(1, 24, '2026-03-04 00:21:32'),
(1, 25, '2026-03-04 00:21:32'),
(1, 26, '2026-03-04 00:21:32'),
(1, 27, '2026-03-04 00:21:32'),
(1, 28, '2026-03-04 00:21:32'),
(1, 29, '2026-03-04 00:21:32'),
(1, 30, '2026-03-04 00:21:32'),
(1, 31, '2026-03-04 00:21:32'),
(1, 32, '2026-03-04 00:21:32'),
(1, 33, '2026-03-04 00:21:32'),
(1, 34, '2026-03-04 00:21:32'),
(1, 35, '2026-03-04 00:21:32'),
(1, 36, '2026-03-04 00:21:32'),
(1, 37, '2026-03-04 00:21:32'),
(1, 38, '2026-03-04 00:21:32'),
(1, 39, '2026-03-04 00:21:32'),
(1, 40, '2026-03-04 00:21:32'),
(1, 41, '2026-03-04 00:21:32'),
(1, 42, '2026-03-04 00:21:32'),
(1, 43, '2026-03-04 00:21:32'),
(1, 44, '2026-03-04 00:21:32'),
(1, 45, '2026-03-04 00:21:32'),
(1, 46, '2026-03-04 00:21:32'),
(1, 47, '2026-03-04 00:21:32'),
(1, 48, '2026-03-04 00:21:32'),
(1, 49, '2026-03-04 00:21:32'),
(1, 50, '2026-03-04 00:21:32'),
(1, 51, '2026-03-04 00:21:32'),
(1, 52, '2026-03-04 00:21:32'),
(1, 53, '2026-03-04 00:21:32'),
(1, 54, '2026-03-04 00:21:32'),
(1, 55, '2026-03-04 00:21:32'),
(1, 56, '2026-03-04 00:21:32'),
(1, 57, '2026-03-04 00:21:32'),
(1, 58, '2026-03-04 00:21:32'),
(1, 59, '2026-03-04 00:21:32'),
(1, 60, '2026-03-04 00:21:32'),
(1, 61, '2026-03-04 00:21:32'),
(1, 62, '2026-03-04 00:21:32'),
(1, 63, '2026-03-04 00:21:32'),
(1, 64, '2026-03-04 00:21:32'),
(1, 80, '2026-05-20 03:12:32'),
(1, 81, '2026-05-20 03:12:32'),
(1, 82, '2026-05-20 03:12:32'),
(1, 83, '2026-05-20 03:12:32'),
(1, 84, '2026-05-20 03:12:32'),
(2, 1, '2026-03-03 23:58:43'),
(2, 2, '2026-03-03 23:58:43'),
(2, 3, '2026-03-03 23:58:43'),
(2, 4, '2026-03-03 23:58:43'),
(2, 5, '2026-03-03 23:58:43'),
(2, 6, '2026-03-03 23:58:43'),
(2, 7, '2026-03-03 23:58:43'),
(2, 9, '2026-03-03 23:58:43'),
(2, 10, '2026-03-03 23:58:43'),
(2, 12, '2026-03-03 23:58:43'),
(2, 13, '2026-03-03 23:58:43'),
(2, 14, '2026-03-03 23:58:43'),
(2, 15, '2026-03-03 23:58:43'),
(2, 16, '2026-03-03 23:58:43'),
(2, 17, '2026-03-03 23:58:43'),
(2, 18, '2026-03-03 23:58:43'),
(2, 19, '2026-03-03 23:58:43'),
(2, 20, '2026-03-03 23:58:43'),
(2, 21, '2026-03-03 23:58:43'),
(2, 22, '2026-03-03 23:58:43'),
(2, 23, '2026-03-03 23:58:43'),
(2, 24, '2026-03-03 23:58:43'),
(2, 25, '2026-03-03 23:58:43'),
(2, 26, '2026-03-03 23:58:43'),
(2, 27, '2026-03-03 23:58:43'),
(2, 28, '2026-03-03 23:58:43'),
(2, 29, '2026-03-03 23:58:43'),
(2, 30, '2026-03-03 23:58:43'),
(2, 31, '2026-03-03 23:58:43'),
(2, 32, '2026-03-03 23:58:43'),
(2, 33, '2026-03-03 23:58:43'),
(2, 34, '2026-03-03 23:58:43'),
(2, 35, '2026-03-03 23:58:43'),
(2, 36, '2026-03-03 23:58:43'),
(2, 37, '2026-03-03 23:58:43'),
(2, 38, '2026-03-03 23:58:43'),
(2, 39, '2026-03-03 23:58:43'),
(2, 58, '2026-03-03 23:58:43'),
(2, 59, '2026-03-03 23:58:43'),
(2, 60, '2026-03-03 23:58:43'),
(2, 61, '2026-03-03 23:58:43'),
(2, 80, '2026-05-20 03:12:32'),
(2, 81, '2026-05-20 03:12:32'),
(2, 82, '2026-05-20 03:12:32'),
(2, 84, '2026-05-20 03:12:32'),
(3, 1, '2026-03-15 09:11:36'),
(3, 2, '2026-03-15 09:11:36'),
(3, 3, '2026-03-15 09:11:36'),
(3, 4, '2026-03-15 09:11:36'),
(3, 5, '2026-03-15 09:11:36'),
(3, 9, '2026-03-15 09:11:36'),
(3, 10, '2026-03-15 09:11:36'),
(3, 12, '2026-03-15 09:11:36'),
(3, 13, '2026-03-15 09:11:36'),
(3, 14, '2026-03-15 09:11:36'),
(3, 15, '2026-03-15 09:11:36'),
(3, 16, '2026-03-15 09:11:36'),
(3, 17, '2026-03-15 09:11:36'),
(3, 18, '2026-03-15 09:11:36'),
(3, 19, '2026-03-15 09:11:36'),
(3, 22, '2026-03-15 09:11:36'),
(3, 25, '2026-03-15 09:11:36'),
(3, 28, '2026-03-15 09:11:36'),
(3, 31, '2026-03-15 09:11:36'),
(3, 34, '2026-03-15 09:11:36'),
(3, 37, '2026-03-15 09:11:36'),
(3, 80, '2026-05-20 03:12:32'),
(3, 81, '2026-05-20 03:12:32'),
(3, 84, '2026-05-20 03:12:32'),
(4, 1, '2026-03-03 23:58:43'),
(4, 2, '2026-03-03 23:58:43'),
(4, 3, '2026-03-03 23:58:43'),
(4, 4, '2026-03-03 23:58:43'),
(4, 6, '2026-03-03 23:58:43'),
(4, 9, '2026-03-03 23:58:43'),
(4, 10, '2026-03-03 23:58:43'),
(4, 80, '2026-05-20 03:12:32'),
(4, 84, '2026-05-20 03:12:32'),
(5, 1, '2026-03-03 23:58:43'),
(5, 2, '2026-03-03 23:58:43'),
(5, 4, '2026-03-03 23:58:43'),
(5, 9, '2026-03-03 23:58:43'),
(5, 10, '2026-03-03 23:58:43'),
(5, 80, '2026-05-20 03:12:32'),
(5, 84, '2026-05-20 03:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `socioeconomic_histories`
--

CREATE TABLE `socioeconomic_histories` (
  `id` int(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `smoking` enum('Current','Former','Never') NOT NULL,
  `smoking_duration` varchar(100) DEFAULT NULL,
  `alcohol` enum('Yes','No') DEFAULT 'No',
  `alcohol_duration` varchar(100) DEFAULT NULL,
  `children_count` int(11) DEFAULT NULL,
  `family_members_total` int(11) DEFAULT NULL,
  `monthly_income_taka` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `smoking_new` enum('Current','Former','Never') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `socioeconomic_histories`
--

INSERT INTO `socioeconomic_histories` (`id`, `patient_id`, `smoking`, `smoking_duration`, `alcohol`, `alcohol_duration`, `children_count`, `family_members_total`, `monthly_income_taka`, `created_at`, `smoking_new`) VALUES
(16, 31, 'Current', '8', 'Yes', NULL, 2, 6, NULL, '2026-05-20 03:32:00', NULL),
(17, 33, 'Never', NULL, NULL, NULL, 2, NULL, 30000.00, '2026-09-22 06:59:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `treatments`
--

CREATE TABLE `treatments` (
  `id` int(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `drug_name` enum('Mesalamine/Sulfasalazine','Azathioprine','Corticosteroids','Immunosuppressants','Biologics','Others') NOT NULL,
  `custom_drug_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `current_dose` varchar(100) DEFAULT NULL,
  `maximum_dose` varchar(100) DEFAULT NULL,
  `side_effects` varchar(255) DEFAULT NULL,
  `local_treatment` enum('Yes','No') DEFAULT 'No',
  `local_treatment_specify` varchar(255) DEFAULT NULL,
  `antibiotics_quinolone` varchar(100) DEFAULT NULL,
  `antibiotics_metronidazole` varchar(100) DEFAULT NULL,
  `antibiotics_others` varchar(100) DEFAULT NULL,
  `other_therapy` varchar(255) DEFAULT NULL,
  `calcium` tinyint(1) DEFAULT 0,
  `vitamin_d` tinyint(1) DEFAULT 0,
  `vitamin_b12` tinyint(1) DEFAULT 0,
  `iron_supplement` tinyint(1) DEFAULT 0,
  `probiotics` tinyint(1) DEFAULT 0,
  `nutritional_supplements` tinyint(1) DEFAULT 0,
  `steroid_dependency` tinyint(1) DEFAULT 0,
  `steroid_dependency_details` varchar(255) DEFAULT NULL,
  `steroid_resistant` enum('Yes','No') DEFAULT 'No',
  `steroid_resistant_details` varchar(255) DEFAULT NULL,
  `ho_att` tinyint(1) DEFAULT 0,
  `att_specify_from` varchar(100) DEFAULT NULL,
  `att_specify_to` varchar(100) DEFAULT NULL,
  `att_total_months` varchar(50) DEFAULT NULL,
  `nsaids_last_4_weeks` tinyint(1) DEFAULT 0,
  `nsaids_details` varchar(255) DEFAULT NULL,
  `alt_meds` tinyint(1) DEFAULT 0,
  `alt_meds_type` enum('Homeopathy','Ayurvedic','Others') DEFAULT NULL,
  `alt_meds_duration` varchar(100) DEFAULT NULL,
  `alt_meds_other_details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `treatments`
--

INSERT INTO `treatments` (`id`, `patient_id`, `drug_name`, `custom_drug_name`, `start_date`, `current_dose`, `maximum_dose`, `side_effects`, `local_treatment`, `local_treatment_specify`, `antibiotics_quinolone`, `antibiotics_metronidazole`, `antibiotics_others`, `other_therapy`, `calcium`, `vitamin_d`, `vitamin_b12`, `iron_supplement`, `probiotics`, `nutritional_supplements`, `steroid_dependency`, `steroid_dependency_details`, `steroid_resistant`, `steroid_resistant_details`, `ho_att`, `att_specify_from`, `att_specify_to`, `att_total_months`, `nsaids_last_4_weeks`, `nsaids_details`, `alt_meds`, `alt_meds_type`, `alt_meds_duration`, `alt_meds_other_details`, `created_at`) VALUES
(19, 31, 'Azathioprine', NULL, '2026-04-14', '50', '3 times', 'No', 'Yes', 'testing', 'Quinolone', NULL, NULL, NULL, 0, 1, 1, 0, 0, 1, 1, 'testing', 'Yes', 'testing', 1, '10 April', '25 May', '3 month', 1, 'testing', 1, NULL, NULL, NULL, '2026-05-20 03:36:30'),
(20, 31, 'Mesalamine/Sulfasalazine', NULL, '2026-05-11', '50', '3 times', 'No', 'Yes', 'testing', 'Quinolone', NULL, NULL, NULL, 0, 1, 1, 0, 0, 1, 1, 'testing', 'Yes', 'testing', 1, '10 April', '25 May', '3 month', 1, 'testing', 1, NULL, NULL, NULL, '2026-05-20 03:36:30'),
(21, 33, 'Corticosteroids', NULL, '2022-09-22', '20', '40', 'No', 'Yes', NULL, NULL, 'Metronidazole', NULL, NULL, 1, 0, 0, 0, 0, 0, 1, NULL, '', NULL, 0, NULL, NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, '2026-09-22 07:06:20'),
(22, 33, 'Azathioprine', NULL, '2022-09-22', '100', NULL, 'No', 'Yes', NULL, NULL, 'Metronidazole', NULL, NULL, 1, 0, 0, 0, 0, 0, 1, NULL, '', NULL, 0, NULL, NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, '2026-09-22 07:06:20');

-- --------------------------------------------------------

--
-- Table structure for table `upazilas`
--

CREATE TABLE `upazilas` (
  `id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bn_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `upazilas`
--

INSERT INTO `upazilas` (`id`, `district_id`, `name`, `bn_name`, `created_at`) VALUES
(1, 1, 'Dhanmondi', 'ধানমন্ডি', '2026-03-03 17:15:16'),
(2, 1, 'Gulshan', 'গুলশান', '2026-03-03 17:15:16'),
(3, 1, 'Mirpur', 'মিরপুর', '2026-03-03 17:15:16'),
(4, 1, 'Mohammadpur', 'মোহাম্মদপুর', '2026-03-03 17:15:16'),
(5, 1, 'Uttara', 'উত্তরা', '2026-03-03 17:15:16'),
(6, 1, 'Motijheel', 'মতিঝিল', '2026-03-03 17:15:16'),
(7, 1, 'Ramna', 'রমনা', '2026-03-03 17:15:16'),
(8, 1, 'Tejgaon', 'তেজগাঁও', '2026-03-03 17:15:16'),
(9, 1, 'Savar', 'সাভার', '2026-03-03 17:15:16'),
(10, 1, 'Keraniganj', 'কেরানীগঞ্জ', '2026-03-03 17:15:16'),
(11, 1, 'Dohar', 'দোহার', '2026-03-03 17:15:16'),
(12, 1, 'Nawabganj', 'নবাবগঞ্জ', '2026-03-03 17:15:16'),
(13, 2, 'Gazipur Sadar', 'গাজীপুর সদর', '2026-03-03 17:15:16'),
(14, 2, 'Tongi', 'টঙ্গী', '2026-03-03 17:15:16'),
(15, 2, 'Kaliakair', 'কালিয়াকৈর', '2026-03-03 17:15:16'),
(16, 2, 'Kapasia', 'কাপাসিয়া', '2026-03-03 17:15:16'),
(17, 2, 'Sreepur', 'শ্রীপুর', '2026-03-03 17:15:16'),
(18, 2, 'Kaliganj', 'কালীগঞ্জ', '2026-03-03 17:15:16'),
(19, 3, 'Narayanganj Sadar', 'নারায়ণগঞ্জ সদর', '2026-03-03 17:15:16'),
(20, 3, 'Sonargaon', 'সোনারগাঁও', '2026-03-03 17:15:16'),
(21, 3, 'Rupganj', 'রূপগঞ্জ', '2026-03-03 17:15:16'),
(22, 3, 'Bandar', 'বন্দর', '2026-03-03 17:15:16'),
(23, 3, 'Araihazar', 'আড়াইহাজার', '2026-03-03 17:15:16'),
(24, 4, 'Tangail Sadar', 'টাঙ্গাইল সদর', '2026-03-03 17:15:16'),
(25, 4, 'Mirzapur', 'মির্জাপুর', '2026-03-03 17:15:16'),
(26, 4, 'Nagarpur', 'নাগরপুর', '2026-03-03 17:15:16'),
(27, 4, 'Delduar', 'দেলদুয়ার', '2026-03-03 17:15:16'),
(28, 4, 'Basail', 'বাসাইল', '2026-03-03 17:15:16'),
(29, 4, 'Ghatail', 'ঘাটাইল', '2026-03-03 17:15:16'),
(30, 4, 'Kalihati', 'কালিহাতী', '2026-03-03 17:15:16'),
(31, 4, 'Sakhipur', 'সখিপুর', '2026-03-03 17:15:16'),
(32, 4, 'Madhupur', 'মধুপুর', '2026-03-03 17:15:16'),
(33, 4, 'Dhanbari', 'ধনবাড়ী', '2026-03-03 17:15:16'),
(34, 4, 'Bhuapur', 'ভূঞাপুর', '2026-03-03 17:15:16'),
(35, 4, 'Gopalpur', 'গোপালপুর', '2026-03-03 17:15:16'),
(36, 5, 'Kishoreganj Sadar', 'কিশোরগঞ্জ সদর', '2026-03-03 17:15:16'),
(37, 5, 'Bajitpur', 'বাজিতপুর', '2026-03-03 17:15:16'),
(38, 5, 'Bhairab', 'ভৈরব', '2026-03-03 17:15:16'),
(39, 5, 'Hossainpur', 'হোসেনপুর', '2026-03-03 17:15:16'),
(40, 5, 'Itna', 'ইটনা', '2026-03-03 17:15:16'),
(41, 5, 'Karimganj', 'করিমগঞ্জ', '2026-03-03 17:15:16'),
(42, 5, 'Katiadi', 'কটিয়াদী', '2026-03-03 17:15:16'),
(43, 5, 'Kuliarchar', 'কুলিয়ারচর', '2026-03-03 17:15:16'),
(44, 5, 'Mithamain', 'মিঠামইন', '2026-03-03 17:15:16'),
(45, 5, 'Nikli', 'নিকলী', '2026-03-03 17:15:16'),
(46, 5, 'Pakundia', 'পাকুন্ডিয়া', '2026-03-03 17:15:16'),
(47, 5, 'Tarail', 'তাড়াইল', '2026-03-03 17:15:16'),
(48, 5, 'Austagram', 'অষ্টগ্রাম', '2026-03-03 17:15:16'),
(49, 6, 'Manikganj Sadar', 'মানিকগঞ্জ সদর', '2026-03-03 17:15:16'),
(50, 6, 'Singair', 'সিঙ্গাইর', '2026-03-03 17:15:16'),
(51, 6, 'Shibalaya', 'শিবালয়', '2026-03-03 17:15:16'),
(52, 6, 'Saturia', 'সাটুরিয়া', '2026-03-03 17:15:16'),
(53, 6, 'Harirampur', 'হরিরামপুর', '2026-03-03 17:15:16'),
(54, 6, 'Ghior', 'ঘিওর', '2026-03-03 17:15:16'),
(55, 6, 'Daulatpur', 'দৌলতপুর', '2026-03-03 17:15:16'),
(56, 7, 'Munshiganj Sadar', 'মুন্সিগঞ্জ সদর', '2026-03-03 17:15:16'),
(57, 7, 'Gazaria', 'গজারিয়া', '2026-03-03 17:15:16'),
(58, 7, 'Lohajang', 'লোহাজং', '2026-03-03 17:15:16'),
(59, 7, 'Sreenagar', 'শ্রীনগর', '2026-03-03 17:15:16'),
(60, 7, 'Tangibari', 'টংগীবাড়ী', '2026-03-03 17:15:16'),
(61, 7, 'Serajdikhan', 'সিরাজদিখান', '2026-03-03 17:15:16'),
(62, 8, 'Narsingdi Sadar', 'নরসিংদী সদর', '2026-03-03 17:15:16'),
(63, 8, 'Palash', 'পলাশ', '2026-03-03 17:15:16'),
(64, 8, 'Shibpur', 'শিবপুর', '2026-03-03 17:15:16'),
(65, 8, 'Belabo', 'বেলাবো', '2026-03-03 17:15:16'),
(66, 8, 'Monohardi', 'মনোহরদী', '2026-03-03 17:15:16'),
(67, 8, 'Raipura', 'রায়পুরা', '2026-03-03 17:15:16'),
(68, 9, 'Faridpur Sadar', 'ফরিদপুর সদর', '2026-03-03 17:15:16'),
(69, 9, 'Alfadanga', 'আলফাডাঙ্গা', '2026-03-03 17:15:16'),
(70, 9, 'Bhanga', 'ভাঙ্গা', '2026-03-03 17:15:16'),
(71, 9, 'Boalmari', 'বোয়ালমারী', '2026-03-03 17:15:16'),
(72, 9, 'Charbhadrasan', 'চরভদ্রাসন', '2026-03-03 17:15:16'),
(73, 9, 'Madhukhali', 'মধুখালী', '2026-03-03 17:15:16'),
(74, 9, 'Nagarkanda', 'নগরকান্দা', '2026-03-03 17:15:16'),
(75, 9, 'Sadarpur', 'সদরপুর', '2026-03-03 17:15:16'),
(76, 9, 'Saltha', 'সালথা', '2026-03-03 17:15:16'),
(77, 10, 'Gopalganj Sadar', 'গোপালগঞ্জ সদর', '2026-03-03 17:15:16'),
(78, 10, 'Kashiani', 'কাশিয়ানী', '2026-03-03 17:15:16'),
(79, 10, 'Kotalipara', 'কোটালীপাড়া', '2026-03-03 17:15:16'),
(80, 10, 'Muksudpur', 'মুকসুদপুর', '2026-03-03 17:15:16'),
(81, 10, 'Tungipara', 'টুংগীপাড়া', '2026-03-03 17:15:16'),
(82, 11, 'Madaripur Sadar', 'মাদারীপুর সদর', '2026-03-03 17:15:16'),
(83, 11, 'Kalkini', 'কালকিনি', '2026-03-03 17:15:16'),
(84, 11, 'Rajoir', 'রাজৈর', '2026-03-03 17:15:16'),
(85, 11, 'Shibchar', 'শিবচর', '2026-03-03 17:15:16'),
(86, 12, 'Rajbari Sadar', 'রাজবাড়ী সদর', '2026-03-03 17:15:16'),
(87, 12, 'Baliakandi', 'বালিয়াকান্দি', '2026-03-03 17:15:16'),
(88, 12, 'Pangsha', 'পাংশা', '2026-03-03 17:15:16'),
(89, 12, 'Goalanda', 'গোয়ালন্দ', '2026-03-03 17:15:16'),
(90, 12, 'Kalukhali', 'কালুখালী', '2026-03-03 17:15:16'),
(91, 13, 'Shariatpur Sadar', 'শরীয়তপুর সদর', '2026-03-03 17:15:16'),
(92, 13, 'Damudya', 'ডামুড্যা', '2026-03-03 17:15:16'),
(93, 13, 'Gosairhat', 'গোসাইরহাট', '2026-03-03 17:15:16'),
(94, 13, 'Bhedarganj', 'ভেদরগঞ্জ', '2026-03-03 17:15:16'),
(95, 13, 'Jajira', 'জাজিরা', '2026-03-03 17:15:16'),
(96, 13, 'Naria', 'নড়িয়া', '2026-03-03 17:15:16'),
(97, 13, 'Shakhipur', 'শখিপুর', '2026-03-03 17:15:16'),
(98, 14, 'Chattogram Sadar', 'চট্টগ্রাম সদর', '2026-03-03 17:15:16'),
(99, 14, 'Anwara', 'আনোয়ারা', '2026-03-03 17:15:16'),
(100, 14, 'Banshkhali', 'বাঁশখালী', '2026-03-03 17:15:16'),
(101, 14, 'Boalkhali', 'বোয়ালখালী', '2026-03-03 17:15:16'),
(102, 14, 'Chandanaish', 'চন্দনাইশ', '2026-03-03 17:15:16'),
(103, 14, 'Fatikchhari', 'ফটিকছড়ি', '2026-03-03 17:15:16'),
(104, 14, 'Hathazari', 'হাটহাজারী', '2026-03-03 17:15:16'),
(105, 14, 'Lohagara', 'লোহাগাড়া', '2026-03-03 17:15:16'),
(106, 14, 'Mirsharai', 'মীরসরাই', '2026-03-03 17:15:16'),
(107, 14, 'Patiya', 'পটিয়া', '2026-03-03 17:15:16'),
(108, 14, 'Rangunia', 'রাঙ্গুনিয়া', '2026-03-03 17:15:16'),
(109, 14, 'Raozan', 'রাউজান', '2026-03-03 17:15:16'),
(110, 14, 'Sandwip', 'সন্দ্বীপ', '2026-03-03 17:15:16'),
(111, 14, 'Satkania', 'সাতকানিয়া', '2026-03-03 17:15:16'),
(112, 14, 'Sitakunda', 'সীতাকুন্ড', '2026-03-03 17:15:16'),
(113, 14, 'Karnaphuli', 'কর্ণফুলী', '2026-03-03 17:15:16'),
(114, 15, 'Cox\'s Bazar Sadar', 'কক্সবাজার সদর', '2026-03-03 17:15:16'),
(115, 15, 'Chakaria', 'চকরিয়া', '2026-03-03 17:15:16'),
(116, 15, 'Kutubdia', 'কুতুবদিয়া', '2026-03-03 17:15:16'),
(117, 15, 'Maheshkhali', 'মহেশখালী', '2026-03-03 17:15:16'),
(118, 15, 'Pekua', 'পেকুয়া', '2026-03-03 17:15:16'),
(119, 15, 'Ramu', 'রামু', '2026-03-03 17:15:16'),
(120, 15, 'Teknaf', 'টেকনাফ', '2026-03-03 17:15:16'),
(121, 15, 'Ukhia', 'উখিয়া', '2026-03-03 17:15:16'),
(122, 16, 'Comilla Sadar', 'কুমিল্লা সদর', '2026-03-03 17:15:16'),
(123, 16, 'Barura', 'বরুড়া', '2026-03-03 17:15:16'),
(124, 16, 'Brahmanpara', 'ব্রাহ্মণপাড়া', '2026-03-03 17:15:16'),
(125, 16, 'Burichang', 'বুড়িচং', '2026-03-03 17:15:16'),
(126, 16, 'Chandina', 'চান্দিনা', '2026-03-03 17:15:16'),
(127, 16, 'Chauddagram', 'চৌদ্দগ্রাম', '2026-03-03 17:15:16'),
(128, 16, 'Daudkandi', 'দাউদকান্দি', '2026-03-03 17:15:16'),
(129, 16, 'Debidwar', 'দেবীদ্বার', '2026-03-03 17:15:16'),
(130, 16, 'Homna', 'হোমনা', '2026-03-03 17:15:16'),
(131, 16, 'Laksam', 'লাকসাম', '2026-03-03 17:15:16'),
(132, 16, 'Monohorgonj', 'মনোহরগঞ্জ', '2026-03-03 17:15:16'),
(133, 16, 'Meghna', 'মেঘনা', '2026-03-03 17:15:16'),
(134, 16, 'Muradnagar', 'মুরাদনগর', '2026-03-03 17:15:16'),
(135, 16, 'Nangalkot', 'নাঙ্গলকোট', '2026-03-03 17:15:16'),
(136, 16, 'Titas', 'তিতাস', '2026-03-03 17:15:16'),
(137, 17, 'Noakhali Sadar', 'নোয়াখালী সদর', '2026-03-03 17:15:16'),
(138, 17, 'Begumganj', 'বেগমগঞ্জ', '2026-03-03 17:15:16'),
(139, 17, 'Chatkhil', 'চাটখিল', '2026-03-03 17:15:16'),
(140, 17, 'Companiganj', 'কোম্পানীগঞ্জ', '2026-03-03 17:15:16'),
(141, 17, 'Hatiya', 'হাতিয়া', '2026-03-03 17:15:16'),
(142, 17, 'Kabirhat', 'কবিরহাট', '2026-03-03 17:15:16'),
(143, 17, 'Senbagh', 'সেনবাগ', '2026-03-03 17:15:16'),
(144, 17, 'Sonaimuri', 'সোনাইমুড়ি', '2026-03-03 17:15:16'),
(145, 17, 'Subarnachar', 'সুবর্ণচর', '2026-03-03 17:15:16'),
(146, 18, 'Feni Sadar', 'ফেনী সদর', '2026-03-03 17:15:16'),
(147, 18, 'Chhagalnaiya', 'ছাগলনাইয়া', '2026-03-03 17:15:16'),
(148, 18, 'Daganbhuiyan', 'দাগনভূঁইয়া', '2026-03-03 17:15:16'),
(149, 18, 'Parshuram', 'পরশুরাম', '2026-03-03 17:15:16'),
(150, 18, 'Fulgazi', 'ফুলগাজী', '2026-03-03 17:15:16'),
(151, 18, 'Sonagazi', 'সোনাগাজী', '2026-03-03 17:15:16'),
(152, 19, 'Lakshmipur Sadar', 'লক্ষ্মীপুর সদর', '2026-03-03 17:15:16'),
(153, 19, 'Ramganj', 'রামগঞ্জ', '2026-03-03 17:15:16'),
(154, 19, 'Ramgati', 'রামগতি', '2026-03-03 17:15:16'),
(155, 19, 'Raipur', 'রায়পুর', '2026-03-03 17:15:16'),
(156, 19, 'Kamalnagar', 'কমলনগর', '2026-03-03 17:15:16'),
(157, 20, 'Brahmanbaria Sadar', 'ব্রাহ্মণবাড়িয়া সদর', '2026-03-03 17:15:16'),
(158, 20, 'Akhaura', 'আখাউড়া', '2026-03-03 17:15:16'),
(159, 20, 'Bancharampur', 'বাঞ্ছারামপুর', '2026-03-03 17:15:16'),
(160, 20, 'Kasba', 'কসবা', '2026-03-03 17:15:16'),
(161, 20, 'Nabinagar', 'নবীনগর', '2026-03-03 17:15:16'),
(162, 20, 'Nasirnagar', 'নাসিরনগর', '2026-03-03 17:15:16'),
(163, 20, 'Sarail', 'সরাইল', '2026-03-03 17:15:16'),
(164, 20, 'Ashuganj', 'আশুগঞ্জ', '2026-03-03 17:15:16'),
(165, 20, 'Bijoynagar', 'বিজয়নগর', '2026-03-03 17:15:16'),
(166, 21, 'Rangamati Sadar', 'রাঙ্গামাটি সদর', '2026-03-03 17:15:16'),
(167, 21, 'Bagaichhari', 'বাঘাইছড়ি', '2026-03-03 17:15:16'),
(168, 21, 'Barkal', 'বরকল', '2026-03-03 17:15:16'),
(169, 21, 'Kawkhali', 'কাউখালী', '2026-03-03 17:15:16'),
(170, 21, 'Juraichhari', 'জুরাছড়ি', '2026-03-03 17:15:16'),
(171, 21, 'Rajasthali', 'রাজস্থলী', '2026-03-03 17:15:16'),
(172, 21, 'Langadu', 'লংগদু', '2026-03-03 17:15:16'),
(173, 21, 'Naniarchar', 'নানিয়ারচর', '2026-03-03 17:15:16'),
(174, 22, 'Khagrachhari Sadar', 'খাগড়াছড়ি সদর', '2026-03-03 17:15:16'),
(175, 22, 'Dighinala', 'দিঘীনালা', '2026-03-03 17:15:16'),
(176, 22, 'Lakshmichhari', 'লক্ষ্মীছড়ি', '2026-03-03 17:15:16'),
(177, 22, 'Mahalchhari', 'মহালছড়ি', '2026-03-03 17:15:16'),
(178, 22, 'Manikchhari', 'মানিকছড়ি', '2026-03-03 17:15:16'),
(179, 22, 'Matiranga', 'মাটিরাঙ্গা', '2026-03-03 17:15:16'),
(180, 22, 'Panchhari', 'পানছড়ি', '2026-03-03 17:15:16'),
(181, 22, 'Ramgarh', 'রামগড়', '2026-03-03 17:15:16'),
(182, 22, 'Guimara', 'গুইমারা', '2026-03-03 17:15:16'),
(183, 23, 'Bandarban Sadar', 'বান্দরবান সদর', '2026-03-03 17:15:16'),
(184, 23, 'Alikadam', 'আলীকদম', '2026-03-03 17:15:16'),
(185, 23, 'Naikhongchhari', 'নাইক্ষ্যংছড়ি', '2026-03-03 17:15:16'),
(186, 23, 'Rowangchhari', 'রোয়াংছড়ি', '2026-03-03 17:15:16'),
(187, 23, 'Ruma', 'রুমা', '2026-03-03 17:15:16'),
(188, 23, 'Thanchi', 'থানচি', '2026-03-03 17:15:16'),
(189, 23, 'Lama', 'লামা', '2026-03-03 17:15:16'),
(190, 24, 'Chandpur Sadar', 'চাঁদপুর সদর', '2026-03-03 17:15:16'),
(191, 24, 'Faridganj', 'ফরিদগঞ্জ', '2026-03-03 17:15:16'),
(192, 24, 'Haimchar', 'হাইমচর', '2026-03-03 17:15:16'),
(193, 24, 'Haziganj', 'হাজীগঞ্জ', '2026-03-03 17:15:16'),
(194, 24, 'Kachua', 'কচুয়া', '2026-03-03 17:15:16'),
(195, 24, 'Matlab Uttar', 'মতলব উত্তর', '2026-03-03 17:15:16'),
(196, 24, 'Matlab Dakkhin', 'মতলব দক্ষিণ', '2026-03-03 17:15:16'),
(197, 24, 'Shahrasti', 'শাহরাস্তি', '2026-03-03 17:15:16'),
(198, 25, 'Rajshahi Sadar', 'রাজশাহী সদর', '2026-03-03 17:15:16'),
(199, 25, 'Bagha', 'বাঘা', '2026-03-03 17:15:16'),
(200, 25, 'Bagmara', 'বাগমারা', '2026-03-03 17:15:16'),
(201, 25, 'Charghat', 'চারঘাট', '2026-03-03 17:15:16'),
(202, 25, 'Durgapur', 'দুর্গাপুর', '2026-03-03 17:15:16'),
(203, 25, 'Godagari', 'গোদাগাড়ী', '2026-03-03 17:15:16'),
(204, 25, 'Mohanpur', 'মোহনপুর', '2026-03-03 17:15:16'),
(205, 25, 'Paba', 'পবা', '2026-03-03 17:15:16'),
(206, 25, 'Puthia', 'পুঠিয়া', '2026-03-03 17:15:16'),
(207, 25, 'Tanore', 'তানোর', '2026-03-03 17:15:16'),
(208, 26, 'Bogra Sadar', 'বগুড়া সদর', '2026-03-03 17:15:16'),
(209, 26, 'Adamdighi', 'আদমদীঘি', '2026-03-03 17:15:16'),
(210, 26, 'Dhunat', 'ধুনট', '2026-03-03 17:15:16'),
(211, 26, 'Dhupchanchia', 'দুপচাঁচিয়া', '2026-03-03 17:15:16'),
(212, 26, 'Gabtali', 'গাবতলী', '2026-03-03 17:15:16'),
(213, 26, 'Kahaloo', 'কাহালু', '2026-03-03 17:15:16'),
(214, 26, 'Nandigram', 'নন্দীগ্রাম', '2026-03-03 17:15:16'),
(215, 26, 'Sariakandi', 'সারিয়াকান্দি', '2026-03-03 17:15:16'),
(216, 26, 'Shajahanpur', 'শাজাহানপুর', '2026-03-03 17:15:16'),
(217, 26, 'Sherpur', 'শেরপুর', '2026-03-03 17:15:16'),
(218, 26, 'Shibganj', 'শিবগঞ্জ', '2026-03-03 17:15:16'),
(219, 26, 'Sonatala', 'সোনাতলা', '2026-03-03 17:15:16'),
(220, 27, 'Pabna Sadar', 'পাবনা সদর', '2026-03-03 17:15:16'),
(221, 27, 'Atgharia', 'আটঘরিয়া', '2026-03-03 17:15:16'),
(222, 27, 'Bera', 'বেড়া', '2026-03-03 17:15:16'),
(223, 27, 'Bhangura', 'ভাঙ্গুড়া', '2026-03-03 17:15:16'),
(224, 27, 'Chatmohar', 'চাটমোহর', '2026-03-03 17:15:16'),
(225, 27, 'Faridpur', 'ফরিদপুর', '2026-03-03 17:15:16'),
(226, 27, 'Ishwardi', 'ঈশ্বরদী', '2026-03-03 17:15:16'),
(227, 27, 'Santhia', 'সাঁথিয়া', '2026-03-03 17:15:16'),
(228, 27, 'Sujanagar', 'সুজানগর', '2026-03-03 17:15:16'),
(229, 28, 'Natore Sadar', 'নাটোর সদর', '2026-03-03 17:15:16'),
(230, 28, 'Bagatipara', 'বাগাতিপাড়া', '2026-03-03 17:15:16'),
(231, 28, 'Baraigram', 'বড়াইগ্রাম', '2026-03-03 17:15:16'),
(232, 28, 'Gurudaspur', 'গুরুদাসপুর', '2026-03-03 17:15:16'),
(233, 28, 'Lalpur', 'লালপুর', '2026-03-03 17:15:16'),
(234, 28, 'Naldanga', 'নলডাঙ্গা', '2026-03-03 17:15:16'),
(235, 28, 'Singra', 'সিংড়া', '2026-03-03 17:15:16'),
(236, 29, 'Sirajganj Sadar', 'সিরাজগঞ্জ সদর', '2026-03-03 17:15:16'),
(237, 29, 'Belkuchi', 'বেলকুচি', '2026-03-03 17:15:16'),
(238, 29, 'Chauhali', 'চৌহালি', '2026-03-03 17:15:16'),
(239, 29, 'Kamarkhanda', 'কামারখন্দ', '2026-03-03 17:15:16'),
(240, 29, 'Kazipur', 'কাজীপুর', '2026-03-03 17:15:16'),
(241, 29, 'Raiganj', 'রায়গঞ্জ', '2026-03-03 17:15:16'),
(242, 29, 'Shahjadpur', 'শাহজাদপুর', '2026-03-03 17:15:16'),
(243, 29, 'Tarash', 'তারাশ', '2026-03-03 17:15:16'),
(244, 29, 'Ullahpara', 'উল্লাপাড়া', '2026-03-03 17:15:16'),
(245, 30, 'Joypurhat Sadar', 'জয়পুরহাট সদর', '2026-03-03 17:15:16'),
(246, 30, 'Akkelpur', 'আক্কেলপুর', '2026-03-03 17:15:16'),
(247, 30, 'Kalai', 'কালাই', '2026-03-03 17:15:16'),
(248, 30, 'Khetlal', 'ক্ষেতলাল', '2026-03-03 17:15:16'),
(249, 30, 'Panchbibi', 'পাঁচবিবি', '2026-03-03 17:15:16'),
(250, 31, 'Chapai Nawabganj Sadar', 'চাঁপাইনবাবগঞ্জ সদর', '2026-03-03 17:15:16'),
(251, 31, 'Bholahat', 'ভোলাহাট', '2026-03-03 17:15:16'),
(252, 31, 'Gomastapur', 'গোমস্তাপুর', '2026-03-03 17:15:16'),
(253, 31, 'Nachole', 'নাচোল', '2026-03-03 17:15:16'),
(254, 31, 'Shibganj', 'শিবগঞ্জ', '2026-03-03 17:15:16'),
(255, 32, 'Naogaon Sadar', 'নওগাঁ সদর', '2026-03-03 17:15:16'),
(256, 32, 'Atrai', 'আত্রাই', '2026-03-03 17:15:16'),
(257, 32, 'Badalgachhi', 'বদলগাছী', '2026-03-03 17:15:16'),
(258, 32, 'Dhamoirhat', 'ধামইরহাট', '2026-03-03 17:15:16'),
(259, 32, 'Manda', 'মান্দা', '2026-03-03 17:15:16'),
(260, 32, 'Mohadevpur', 'মহাদেবপুর', '2026-03-03 17:15:16'),
(261, 32, 'Niamatpur', 'নিয়ামতপুর', '2026-03-03 17:15:16'),
(262, 32, 'Patnitala', 'পত্নীতলা', '2026-03-03 17:15:16'),
(263, 32, 'Porsha', 'পোরশা', '2026-03-03 17:15:16'),
(264, 32, 'Raninagar', 'রাণীনগর', '2026-03-03 17:15:16'),
(265, 32, 'Sapahar', 'সাপাহার', '2026-03-03 17:15:16'),
(266, 33, 'Khulna Sadar', 'খুলনা সদর', '2026-03-03 17:15:16'),
(267, 33, 'Batiaghata', 'বটিয়াঘাটা', '2026-03-03 17:15:16'),
(268, 33, 'Dacope', 'দাকোপ', '2026-03-03 17:15:16'),
(269, 33, 'Dighalia', 'দিঘলিয়া', '2026-03-03 17:15:16'),
(270, 33, 'Dumuria', 'ডুমুরিয়া', '2026-03-03 17:15:16'),
(271, 33, 'Koyra', 'কয়রা', '2026-03-03 17:15:16'),
(272, 33, 'Paikgachha', 'পাইকগাছা', '2026-03-03 17:15:16'),
(273, 33, 'Phultala', 'ফুলতলা', '2026-03-03 17:15:16'),
(274, 33, 'Rupsa', 'রূপসা', '2026-03-03 17:15:16'),
(275, 33, 'Terokhada', 'তেরখাদা', '2026-03-03 17:15:16'),
(276, 34, 'Jessore Sadar', 'যশোর সদর', '2026-03-03 17:15:16'),
(277, 34, 'Abhaynagar', 'অভয়নগর', '2026-03-03 17:15:16'),
(278, 34, 'Bagherpara', 'বাঘেরপাড়া', '2026-03-03 17:15:16'),
(279, 34, 'Chaugachha', 'চৌগাছা', '2026-03-03 17:15:16'),
(280, 34, 'Jhikargachha', 'ঝিকরগাছা', '2026-03-03 17:15:16'),
(281, 34, 'Keshabpur', 'কেশবপুর', '2026-03-03 17:15:16'),
(282, 34, 'Manirampur', 'মনিরামপুর', '2026-03-03 17:15:16'),
(283, 34, 'Sharsha', 'শার্শা', '2026-03-03 17:15:16'),
(284, 35, 'Kushtia Sadar', 'কুষ্টিয়া সদর', '2026-03-03 17:15:16'),
(285, 35, 'Bheramara', 'ভেড়ামারা', '2026-03-03 17:15:16'),
(286, 35, 'Daulatpur', 'দৌলতপুর', '2026-03-03 17:15:16'),
(287, 35, 'Khoksa', 'খোকসা', '2026-03-03 17:15:16'),
(288, 35, 'Kumarkhali', 'কুমারখালী', '2026-03-03 17:15:16'),
(289, 35, 'Mirpur', 'মিরপুর', '2026-03-03 17:15:16'),
(290, 36, 'Jhenaidah Sadar', 'ঝিনাইদহ সদর', '2026-03-03 17:15:16'),
(291, 36, 'Harinakunda', 'হরিণাকুন্ডু', '2026-03-03 17:15:16'),
(292, 36, 'Kaliganj', 'কালীগঞ্জ', '2026-03-03 17:15:16'),
(293, 36, 'Kotchandpur', 'কোটচাঁদপুর', '2026-03-03 17:15:16'),
(294, 36, 'Maheshpur', 'মহেশপুর', '2026-03-03 17:15:16'),
(295, 36, 'Shailkupa', 'শৈলকুপা', '2026-03-03 17:15:16'),
(296, 37, 'Satkhira Sadar', 'সাতক্ষীরা সদর', '2026-03-03 17:15:16'),
(297, 37, 'Assasuni', 'আশাশুনি', '2026-03-03 17:15:16'),
(298, 37, 'Debhata', 'দেবহাটা', '2026-03-03 17:15:16'),
(299, 37, 'Kalaroa', 'কলারোয়া', '2026-03-03 17:15:16'),
(300, 37, 'Kaliganj', 'কালীগঞ্জ', '2026-03-03 17:15:16'),
(301, 37, 'Shyamnagar', 'শ্যামনগর', '2026-03-03 17:15:16'),
(302, 37, 'Tala', 'তালা', '2026-03-03 17:15:16'),
(303, 38, 'Bagerhat Sadar', 'বাগেরহাট সদর', '2026-03-03 17:15:16'),
(304, 38, 'Chitalmari', 'চিতলমারী', '2026-03-03 17:15:16'),
(305, 38, 'Fakirhat', 'ফকিরহাট', '2026-03-03 17:15:16'),
(306, 38, 'Kachua', 'কচুয়া', '2026-03-03 17:15:16'),
(307, 38, 'Mollahat', 'মোল্লাহাট', '2026-03-03 17:15:16'),
(308, 38, 'Mongla', 'মোংলা', '2026-03-03 17:15:16'),
(309, 38, 'Morrelganj', 'মোড়েলগঞ্জ', '2026-03-03 17:15:16'),
(310, 38, 'Rampal', 'রামপাল', '2026-03-03 17:15:16'),
(311, 38, 'Sarankhola', 'শরণখোলা', '2026-03-03 17:15:16'),
(312, 39, 'Chuadanga Sadar', 'চুয়াডাঙ্গা সদর', '2026-03-03 17:15:16'),
(313, 39, 'Alamdanga', 'আলমডাঙ্গা', '2026-03-03 17:15:16'),
(314, 39, 'Damurhuda', 'দামুড়হুদা', '2026-03-03 17:15:16'),
(315, 39, 'Jibannagar', 'জীবননগর', '2026-03-03 17:15:16'),
(316, 40, 'Meherpur Sadar', 'মেহেরপুর সদর', '2026-03-03 17:15:16'),
(317, 40, 'Gangni', 'গাংনী', '2026-03-03 17:15:16'),
(318, 40, 'Mujibnagar', 'মুজিবনগর', '2026-03-03 17:15:16'),
(319, 41, 'Narail Sadar', 'নড়াইল সদর', '2026-03-03 17:15:16'),
(320, 41, 'Kalia', 'কালিয়া', '2026-03-03 17:15:16'),
(321, 41, 'Lohagara', 'লোহাগাড়া', '2026-03-03 17:15:16'),
(322, 42, 'Magura Sadar', 'মাগুরা সদর', '2026-03-03 17:15:16'),
(323, 42, 'Mohammadpur', 'মোহাম্মদপুর', '2026-03-03 17:15:16'),
(324, 42, 'Shalikha', 'শালিখা', '2026-03-03 17:15:16'),
(325, 42, 'Sreepur', 'শ্রীপুর', '2026-03-03 17:15:16'),
(326, 43, 'Barishal Sadar', 'বরিশাল সদর', '2026-03-03 17:15:16'),
(327, 43, 'Agailjhara', 'আগৈলঝাড়া', '2026-03-03 17:15:16'),
(328, 43, 'Babuganj', 'বাবুগঞ্জ', '2026-03-03 17:15:16'),
(329, 43, 'Bakerganj', 'বাকেরগঞ্জ', '2026-03-03 17:15:16'),
(330, 43, 'Banaripara', 'বানারীপাড়া', '2026-03-03 17:15:16'),
(331, 43, 'Gaurnadi', 'গৌরনদী', '2026-03-03 17:15:16'),
(332, 43, 'Hizla', 'হিজলা', '2026-03-03 17:15:16'),
(333, 43, 'Mehendiganj', 'মেহেন্দিগঞ্জ', '2026-03-03 17:15:16'),
(334, 43, 'Muladi', 'মুলাদী', '2026-03-03 17:15:16'),
(335, 43, 'Wazirpur', 'ওয়াজিরপুর', '2026-03-03 17:15:16'),
(336, 44, 'Patuakhali Sadar', 'পটুয়াখালী সদর', '2026-03-03 17:15:16'),
(337, 44, 'Bauphal', 'বাউফল', '2026-03-03 17:15:16'),
(338, 44, 'Dashmina', 'দশমিনা', '2026-03-03 17:15:16'),
(339, 44, 'Dumki', 'দুমকি', '2026-03-03 17:15:16'),
(340, 44, 'Galachipa', 'গলাচিপা', '2026-03-03 17:15:16'),
(341, 44, 'Kalapara', 'কলাপাড়া', '2026-03-03 17:15:16'),
(342, 44, 'Mirzaganj', 'মির্জাগঞ্জ', '2026-03-03 17:15:16'),
(343, 44, 'Rangabali', 'রাঙ্গাবালী', '2026-03-03 17:15:16'),
(344, 45, 'Bhola Sadar', 'ভোলা সদর', '2026-03-03 17:15:16'),
(345, 45, 'Burhanuddin', 'বোরহানউদ্দিন', '2026-03-03 17:15:16'),
(346, 45, 'Char Fasson', 'চর ফ্যাশন', '2026-03-03 17:15:16'),
(347, 45, 'Daulatkhan', 'দৌলতখান', '2026-03-03 17:15:16'),
(348, 45, 'Lalmohan', 'লালমোহন', '2026-03-03 17:15:16'),
(349, 45, 'Manpura', 'মনপুরা', '2026-03-03 17:15:16'),
(350, 45, 'Tazumuddin', 'তজুমদ্দিন', '2026-03-03 17:15:16'),
(351, 46, 'Pirojpur Sadar', 'পিরোজপুর সদর', '2026-03-03 17:15:16'),
(352, 46, 'Bhandaria', 'ভাণ্ডারিয়া', '2026-03-03 17:15:16'),
(353, 46, 'Kawkhali', 'কাউখালী', '2026-03-03 17:15:16'),
(354, 46, 'Mathbaria', 'মঠবাড়িয়া', '2026-03-03 17:15:16'),
(355, 46, 'Nazirpur', 'নাজিরপুর', '2026-03-03 17:15:16'),
(356, 46, 'Nesarabad', 'নেছারাবাদ', '2026-03-03 17:15:16'),
(357, 46, 'Zianagar', 'জিয়ানগর', '2026-03-03 17:15:16'),
(358, 47, 'Barguna Sadar', 'বরগুনা সদর', '2026-03-03 17:15:16'),
(359, 47, 'Amtali', 'আমতলী', '2026-03-03 17:15:16'),
(360, 47, 'Bamna', 'বামনা', '2026-03-03 17:15:16'),
(361, 47, 'Betagi', 'বেতাগী', '2026-03-03 17:15:16'),
(362, 47, 'Patharghata', 'পাথরঘাটা', '2026-03-03 17:15:16'),
(363, 47, 'Taltali', 'তালতলী', '2026-03-03 17:15:16'),
(364, 48, 'Jhalokati Sadar', 'ঝালকাঠি সদর', '2026-03-03 17:15:16'),
(365, 48, 'Kathalia', 'কাঁঠালিয়া', '2026-03-03 17:15:16'),
(366, 48, 'Nalchity', 'নলছিটি', '2026-03-03 17:15:16'),
(367, 48, 'Rajapur', 'রাজাপুর', '2026-03-03 17:15:16'),
(368, 49, 'Sylhet Sadar', 'সিলেট সদর', '2026-03-03 17:15:16'),
(369, 49, 'Balaganj', 'বালাগঞ্জ', '2026-03-03 17:15:16'),
(370, 49, 'Beanibazar', 'বিয়ানীবাজার', '2026-03-03 17:15:16'),
(371, 49, 'Bishwanath', 'বিশ্বনাথ', '2026-03-03 17:15:16'),
(372, 49, 'Companiganj', 'কোম্পানীগঞ্জ', '2026-03-03 17:15:16'),
(373, 49, 'Dakshin Surma', 'দক্ষিণ সুরমা', '2026-03-03 17:15:16'),
(374, 49, 'Fenchuganj', 'ফেঞ্চুগঞ্জ', '2026-03-03 17:15:16'),
(375, 49, 'Golapganj', 'গোলাপগঞ্জ', '2026-03-03 17:15:16'),
(376, 49, 'Gowainghat', 'গোয়াইনঘাট', '2026-03-03 17:15:16'),
(377, 49, 'Jaintiapur', 'জৈন্তাপুর', '2026-03-03 17:15:16'),
(378, 49, 'Kanaighat', 'কানাইঘাট', '2026-03-03 17:15:16'),
(379, 49, 'Osmani Nagar', 'ওসমানী নগর', '2026-03-03 17:15:16'),
(380, 49, 'Zakiganj', 'জকিগঞ্জ', '2026-03-03 17:15:16'),
(381, 50, 'Moulvibazar Sadar', 'মৌলভীবাজার সদর', '2026-03-03 17:15:16'),
(382, 50, 'Barlekha', 'বড়লেখা', '2026-03-03 17:15:16'),
(383, 50, 'Juri', 'জুড়ী', '2026-03-03 17:15:16'),
(384, 50, 'Kamalganj', 'কমলগঞ্জ', '2026-03-03 17:15:16'),
(385, 50, 'Kulaura', 'কুলাউড়া', '2026-03-03 17:15:16'),
(386, 50, 'Rajnagar', 'রাজনগর', '2026-03-03 17:15:16'),
(387, 50, 'Sreemangal', 'শ্রীমঙ্গল', '2026-03-03 17:15:16'),
(388, 51, 'Habiganj Sadar', 'হবিগঞ্জ সদর', '2026-03-03 17:15:16'),
(389, 51, 'Ajmiriganj', 'আজমিরীগঞ্জ', '2026-03-03 17:15:16'),
(390, 51, 'Bahubal', 'বাহুবল', '2026-03-03 17:15:16'),
(391, 51, 'Baniyachong', 'বানিয়াচং', '2026-03-03 17:15:16'),
(392, 51, 'Chunarughat', 'চুনারুঘাট', '2026-03-03 17:15:16'),
(393, 51, 'Lakhai', 'লাখাই', '2026-03-03 17:15:16'),
(394, 51, 'Madhabpur', 'মাধবপুর', '2026-03-03 17:15:16'),
(395, 51, 'Nabiganj', 'নবীগঞ্জ', '2026-03-03 17:15:16'),
(396, 51, 'Shayestaganj', 'শায়েস্তাগঞ্জ', '2026-03-03 17:15:16'),
(397, 52, 'Sunamganj Sadar', 'সুনামগঞ্জ সদর', '2026-03-03 17:15:16'),
(398, 52, 'Bishwamvarpur', 'বিশ্বম্ভরপুর', '2026-03-03 17:15:16'),
(399, 52, 'Chhatak', 'ছাতক', '2026-03-03 17:15:16'),
(400, 52, 'Derai', 'দিরাই', '2026-03-03 17:15:16'),
(401, 52, 'Dharampasha', 'ধর্মপাশা', '2026-03-03 17:15:16'),
(402, 52, 'Dowarabazar', 'দোয়ারাবাজার', '2026-03-03 17:15:16'),
(403, 52, 'Jagannathpur', 'জগন্নাথপুর', '2026-03-03 17:15:16'),
(404, 52, 'Jamalganj', 'জামালগঞ্জ', '2026-03-03 17:15:16'),
(405, 52, 'Sulla', 'শাল্লা', '2026-03-03 17:15:16'),
(406, 52, 'Tahirpur', 'তাহিরপুর', '2026-03-03 17:15:16'),
(407, 52, 'South Sunamganj', 'দক্ষিণ সুনামগঞ্জ', '2026-03-03 17:15:16'),
(408, 53, 'Rangpur Sadar', 'রংপুর সদর', '2026-03-03 17:15:16'),
(409, 53, 'Badarganj', 'বদরগঞ্জ', '2026-03-03 17:15:16'),
(410, 53, 'Gangachara', 'গঙ্গাচড়া', '2026-03-03 17:15:16'),
(411, 53, 'Kaunia', 'কাউনিয়া', '2026-03-03 17:15:16'),
(412, 53, 'Mithapukur', 'মিঠাপুকুর', '2026-03-03 17:15:16'),
(413, 53, 'Pirgachha', 'পীরগাছা', '2026-03-03 17:15:16'),
(414, 53, 'Pirganj', 'পীরগঞ্জ', '2026-03-03 17:15:16'),
(415, 53, 'Taraganj', 'তারাগঞ্জ', '2026-03-03 17:15:16'),
(416, 54, 'Dinajpur Sadar', 'দিনাজপুর সদর', '2026-03-03 17:15:16'),
(417, 54, 'Birampur', 'বিরামপুর', '2026-03-03 17:15:16'),
(418, 54, 'Birganj', 'বীরগঞ্জ', '2026-03-03 17:15:16'),
(419, 54, 'Biral', 'বিরল', '2026-03-03 17:15:16'),
(420, 54, 'Bochaganj', 'বোচাগঞ্জ', '2026-03-03 17:15:16'),
(421, 54, 'Chirirbandar', 'চিরিরবন্দর', '2026-03-03 17:15:16'),
(422, 54, 'Phulbari', 'ফুলবাড়ী', '2026-03-03 17:15:16'),
(423, 54, 'Ghoraghat', 'ঘোড়াঘাট', '2026-03-03 17:15:16'),
(424, 54, 'Hakimpur', 'হাকিমপুর', '2026-03-03 17:15:16'),
(425, 54, 'Kaharole', 'কাহারোল', '2026-03-03 17:15:16'),
(426, 54, 'Khansama', 'খানসামা', '2026-03-03 17:15:16'),
(427, 54, 'Nawabganj', 'নবাবগঞ্জ', '2026-03-03 17:15:16'),
(428, 54, 'Parbatipur', 'পার্বতীপুর', '2026-03-03 17:15:16'),
(429, 55, 'Kurigram Sadar', 'কুড়িগ্রাম সদর', '2026-03-03 17:15:16'),
(430, 55, 'Bhurungamari', 'ভূরুঙ্গামারী', '2026-03-03 17:15:16'),
(431, 55, 'Char Rajibpur', 'চর রাজিবপুর', '2026-03-03 17:15:16'),
(432, 55, 'Chilmari', 'চিলমারী', '2026-03-03 17:15:16'),
(433, 55, 'Phulbari', 'ফুলবাড়ী', '2026-03-03 17:15:16'),
(434, 55, 'Nageshwari', 'নাগেশ্বরী', '2026-03-03 17:15:16'),
(435, 55, 'Rajarhat', 'রাজারহাট', '2026-03-03 17:15:16'),
(436, 55, 'Raomari', 'রৌমারী', '2026-03-03 17:15:16'),
(437, 55, 'Ulipur', 'উলিপুর', '2026-03-03 17:15:16'),
(438, 56, 'Nilphamari Sadar', 'নীলফামারী সদর', '2026-03-03 17:15:16'),
(439, 56, 'Dimla', 'ডিমলা', '2026-03-03 17:15:16'),
(440, 56, 'Domar', 'ডোমার', '2026-03-03 17:15:16'),
(441, 56, 'Jaldhaka', 'জলঢাকা', '2026-03-03 17:15:16'),
(442, 56, 'Kishoreganj', 'কিশোরগঞ্জ', '2026-03-03 17:15:16'),
(443, 56, 'Saidpur', 'সৈয়দপুর', '2026-03-03 17:15:16'),
(444, 57, 'Lalmonirhat Sadar', 'লালমনিরহাট সদর', '2026-03-03 17:15:16'),
(445, 57, 'Aditmari', 'আদিতমারী', '2026-03-03 17:15:16'),
(446, 57, 'Hatibandha', 'হাতীবান্ধা', '2026-03-03 17:15:16'),
(447, 57, 'Kaliganj', 'কালীগঞ্জ', '2026-03-03 17:15:16'),
(448, 57, 'Patgram', 'পাটগ্রাম', '2026-03-03 17:15:16'),
(449, 58, 'Panchagarh Sadar', 'পঞ্চগড় সদর', '2026-03-03 17:15:16'),
(450, 58, 'Atwari', 'আটোয়ারী', '2026-03-03 17:15:16'),
(451, 58, 'Boda', 'বোদা', '2026-03-03 17:15:16'),
(452, 58, 'Debiganj', 'দেবীগঞ্জ', '2026-03-03 17:15:16'),
(453, 58, 'Tetulia', 'তেতুলিয়া', '2026-03-03 17:15:16'),
(454, 59, 'Thakurgaon Sadar', 'ঠাকুরগাঁও সদর', '2026-03-03 17:15:16'),
(455, 59, 'Baliadangi', 'বালিয়াডাঙ্গী', '2026-03-03 17:15:16'),
(456, 59, 'Haripur', 'হরিপুর', '2026-03-03 17:15:16'),
(457, 59, 'Pirganj', 'পীরগঞ্জ', '2026-03-03 17:15:16'),
(458, 59, 'Ranisankail', 'রাণীশংকৈল', '2026-03-03 17:15:16'),
(459, 60, 'Gaibandha Sadar', 'গাইবান্ধা সদর', '2026-03-03 17:15:16'),
(460, 60, 'Fulchhari', 'ফুলছড়ি', '2026-03-03 17:15:16'),
(461, 60, 'Gobindaganj', 'গোবিন্দগঞ্জ', '2026-03-03 17:15:16'),
(462, 60, 'Palashbari', 'পলাশবাড়ী', '2026-03-03 17:15:16'),
(463, 60, 'Sadullapur', 'সাদুল্লাপুর', '2026-03-03 17:15:16'),
(464, 60, 'Saghata', 'সাঘাটা', '2026-03-03 17:15:16'),
(465, 60, 'Sundarganj', 'সুন্দরগঞ্জ', '2026-03-03 17:15:16'),
(466, 61, 'Mymensingh Sadar', 'ময়মনসিংহ সদর', '2026-03-03 17:15:16'),
(467, 61, 'Bhaluka', 'ভালুকা', '2026-03-03 17:15:16'),
(468, 61, 'Dhobaura', 'ধোবাউড়া', '2026-03-03 17:15:16'),
(469, 61, 'Fulbaria', 'ফুলবাড়ীয়া', '2026-03-03 17:15:16'),
(470, 61, 'Gaffargaon', 'গফরগাঁও', '2026-03-03 17:15:16'),
(471, 61, 'Gauripur', 'গৌরীপুর', '2026-03-03 17:15:16'),
(472, 61, 'Haluaghat', 'হালুয়াঘাট', '2026-03-03 17:15:16'),
(473, 61, 'Ishwarganj', 'ঈশ্বরগঞ্জ', '2026-03-03 17:15:16'),
(474, 61, 'Muktagachha', 'মুক্তাগাছা', '2026-03-03 17:15:16'),
(475, 61, 'Nandail', 'নান্দাইল', '2026-03-03 17:15:16'),
(476, 61, 'Phulpur', 'ফুলপুর', '2026-03-03 17:15:16'),
(477, 61, 'Tarakanda', 'তারাকান্দা', '2026-03-03 17:15:16'),
(478, 61, 'Trishal', 'ত্রিশাল', '2026-03-03 17:15:16'),
(479, 62, 'Netrokona Sadar', 'নেত্রকোনা সদর', '2026-03-03 17:15:16'),
(480, 62, 'Atpara', 'আটপাড়া', '2026-03-03 17:15:16'),
(481, 62, 'Barhatta', 'বারহাট্টা', '2026-03-03 17:15:16'),
(482, 62, 'Durgapur', 'দুর্গাপুর', '2026-03-03 17:15:16'),
(483, 62, 'Kalmakanda', 'কলমাকান্দা', '2026-03-03 17:15:16'),
(484, 62, 'Kendua', 'কেন্দুয়া', '2026-03-03 17:15:16'),
(485, 62, 'Khaliajuri', 'খালিয়াজুড়ি', '2026-03-03 17:15:16'),
(486, 62, 'Madan', 'মদন', '2026-03-03 17:15:16'),
(487, 62, 'Mohanganj', 'মোহনগঞ্জ', '2026-03-03 17:15:16'),
(488, 62, 'Purbadhala', 'পূর্বধলা', '2026-03-03 17:15:16'),
(489, 63, 'Jamalpur Sadar', 'জামালপুর সদর', '2026-03-03 17:15:16'),
(490, 63, 'Bakshiganj', 'বকশীগঞ্জ', '2026-03-03 17:15:16'),
(491, 63, 'Dewanganj', 'দেওয়ানগঞ্জ', '2026-03-03 17:15:16'),
(492, 63, 'Islampur', 'ইসলামপুর', '2026-03-03 17:15:16'),
(493, 63, 'Madarganj', 'মাদারগঞ্জ', '2026-03-03 17:15:16'),
(494, 63, 'Melandaha', 'মেলান্দহ', '2026-03-03 17:15:16'),
(495, 63, 'Sarishabari', 'সরিষাবাড়ী', '2026-03-03 17:15:16'),
(496, 64, 'Sherpur Sadar', 'শেরপুর সদর', '2026-03-03 17:15:16'),
(497, 64, 'Jhenaigati', 'ঝিনাইগাতী', '2026-03-03 17:15:16'),
(498, 64, 'Nakla', 'নকলা', '2026-03-03 17:15:16'),
(499, 64, 'Nalitabari', 'নালিতাবাড়ী', '2026-03-03 17:15:16'),
(500, 64, 'Sreebardi', 'শ্রীবরদী', '2026-03-03 17:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `degree` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `location1` varchar(100) DEFAULT NULL,
  `location2` varchar(100) DEFAULT NULL,
  `location3` varchar(100) DEFAULT NULL,
  `role_type` enum('Admin','Doctor','Medical Staff','Receptionist','Viewer') NOT NULL DEFAULT 'Doctor',
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `designation`, `degree`, `phone`, `email`, `address`, `location1`, `location2`, `location3`, `role_type`, `password_hash`, `is_active`, `created_at`) VALUES
(1, 'System Admin', NULL, NULL, NULL, 'admin@example.com', NULL, NULL, NULL, NULL, 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, '2026-01-19 07:39:14'),
(7, 'dmch.gastro2026', NULL, NULL, NULL, 'dmch.gastro2026@gmail.com', NULL, NULL, NULL, NULL, 'Doctor', '$2y$10$XwqArQ9N6D664l5aBh8iwuZIZ9gwCgkTuLhAJGb/S8Mms92NvQKs6', 1, '2026-09-22 07:17:21');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`, `granted`, `created_at`) VALUES
(7, 1, 1, '2026-09-22 07:26:53'),
(7, 2, 1, '2026-09-22 07:26:53'),
(7, 3, 1, '2026-09-22 07:26:53'),
(7, 4, 1, '2026-09-22 07:26:53'),
(7, 5, 1, '2026-09-22 07:26:53'),
(7, 6, 1, '2026-09-22 07:26:53'),
(7, 7, 1, '2026-09-22 07:26:53'),
(7, 9, 1, '2026-09-22 07:26:53'),
(7, 10, 1, '2026-09-22 07:26:53'),
(7, 12, 1, '2026-09-22 07:26:53'),
(7, 13, 1, '2026-09-22 07:26:53'),
(7, 14, 1, '2026-09-22 07:26:53'),
(7, 15, 1, '2026-09-22 07:26:53'),
(7, 16, 1, '2026-09-22 07:26:53'),
(7, 17, 1, '2026-09-22 07:26:53'),
(7, 18, 1, '2026-09-22 07:26:53'),
(7, 19, 1, '2026-09-22 07:26:53'),
(7, 20, 1, '2026-09-22 07:26:53'),
(7, 22, 1, '2026-09-22 07:26:53'),
(7, 23, 1, '2026-09-22 07:26:53'),
(7, 25, 1, '2026-09-22 07:26:53'),
(7, 26, 1, '2026-09-22 07:26:53'),
(7, 28, 1, '2026-09-22 07:26:53'),
(7, 29, 1, '2026-09-22 07:26:53'),
(7, 31, 1, '2026-09-22 07:26:53'),
(7, 32, 1, '2026-09-22 07:26:53'),
(7, 34, 1, '2026-09-22 07:26:53'),
(7, 35, 1, '2026-09-22 07:26:53'),
(7, 37, 1, '2026-09-22 07:26:53'),
(7, 38, 1, '2026-09-22 07:26:53'),
(7, 80, 1, '2026-09-22 07:26:53'),
(7, 81, 1, '2026-09-22 07:26:53'),
(7, 82, 1, '2026-09-22 07:26:53'),
(7, 84, 1, '2026-09-22 07:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `login_time` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_id`, `login_time`, `last_activity`, `logout_time`, `ip`, `user_agent`) VALUES
(1, 1, 'dbtcgrpnutn0rgn97dq62m4nle', '2026-01-19 13:39:27', '2026-01-20 17:54:14', '2026-01-20 11:38:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(2, 1, 'dbtcgrpnutn0rgn97dq62m4nle', '2026-01-20 11:39:09', '2026-01-20 17:54:14', '2026-01-20 17:52:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(3, 1, 'dbtcgrpnutn0rgn97dq62m4nle', '2026-01-20 17:53:46', '2026-01-20 17:54:14', '2026-03-05 15:01:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(4, 1, '3cajkbn9nfr9dt80p8nq89tgv9', '2026-01-20 21:45:09', '2026-01-20 23:08:39', '2026-01-20 21:46:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(5, 1, '3cajkbn9nfr9dt80p8nq89tgv9', '2026-01-20 21:47:06', '2026-01-20 23:08:39', '2026-03-05 15:01:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(6, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-03 22:25:55', '2026-03-04 15:52:01', '2026-03-04 02:37:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(7, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-04 09:02:01', '2026-03-04 15:52:01', '2026-03-04 09:06:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(8, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-04 09:08:43', '2026-03-04 15:52:01', '2026-03-04 09:09:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(9, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-04 09:10:11', '2026-03-04 15:52:01', '2026-03-04 13:23:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-04 13:24:08', '2026-03-04 15:52:01', '2026-03-04 15:48:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'l0759f1kp7sthbscvtt8nu1t09', '2026-03-04 15:49:08', '2026-03-04 15:52:01', '2026-03-05 15:01:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'b1fper2leh6c9rn83n9jkojh8p', '2026-03-04 17:13:10', '2026-03-04 17:55:19', '2026-03-05 15:01:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'q64h35culha9end976515ibvpm', '2026-03-04 19:50:32', '2026-03-04 20:12:09', '2026-03-04 20:12:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'suhpa9f3gv8rt4g7hplegsgt4q', '2026-03-04 22:11:51', '2026-03-05 00:24:47', '2026-03-04 23:55:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 2, 'suhpa9f3gv8rt4g7hplegsgt4q', '2026-03-04 23:56:04', '2026-03-05 00:24:47', '2026-03-04 23:59:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'suhpa9f3gv8rt4g7hplegsgt4q', '2026-03-05 00:12:03', '2026-03-05 00:24:47', '2026-03-05 00:23:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 2, 'suhpa9f3gv8rt4g7hplegsgt4q', '2026-03-05 00:23:56', '2026-03-05 00:24:47', '2026-03-05 00:24:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(0, 1, 'h0dnotaok88o98te3rkqela7ri', '2026-05-19 20:53:39', '2026-05-19 20:53:39', '2026-05-19 21:02:21', '103.155.99.237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'h0dnotaok88o98te3rkqela7ri', '2026-05-19 21:02:21', '2026-05-19 21:02:21', '2026-05-20 08:49:07', '103.155.99.237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'u0bt3hm56rruq4ib4d4l8akb5i', '2026-05-20 08:49:07', '2026-05-20 08:49:07', '2026-05-20 08:54:30', '59.152.5.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'b5871foav29uev1hksf8tmhe50', '2026-05-20 08:54:30', '2026-05-20 08:54:30', '2026-05-20 09:00:40', '27.147.137.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'u0bt3hm56rruq4ib4d4l8akb5i', '2026-05-20 09:00:40', '2026-05-20 09:00:40', '2026-05-20 09:21:30', '59.152.5.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, '0da2ro468au4futdbngeesumh4', '2026-05-20 09:21:30', '2026-05-20 09:21:30', '2026-05-20 12:52:08', '59.152.5.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'nagq8t9k84tpod1qg1h8qodltv', '2026-05-20 12:52:08', '2026-05-20 12:52:08', '2026-05-24 14:28:45', '27.147.137.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, '9kronc0bls25hrfr8vujojqkur', '2026-05-24 14:28:45', '2026-05-24 14:28:45', '2026-05-28 22:44:25', '27.147.132.185', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'lk7uvrgvn8vrake2ums4dfd3p0', '2026-05-28 22:44:25', '2026-05-28 22:44:25', '2026-06-08 11:57:29', '103.155.99.237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(0, 1, 'vtpvp88g71ug1tlmks0l1l3hhq', '2026-06-08 11:57:29', '2026-06-08 11:57:29', '2026-06-08 11:57:44', '27.147.137.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(0, 1, 'vtpvp88g71ug1tlmks0l1l3hhq', '2026-06-08 11:57:44', '2026-06-08 11:57:44', '2026-06-25 09:48:44', '27.147.137.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(0, 1, 'gbfqp427uqc1r2kmtckvn70fb2', '2026-06-25 09:48:44', '2026-06-25 09:48:44', '2026-07-26 09:19:38', '27.147.132.185', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
(0, 1, '4icttpgor4lg41j9a9147h1krs', '2026-07-26 09:19:38', '2026-07-26 09:19:38', '2026-07-26 09:25:39', '119.40.90.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36'),
(0, 1, '4icttpgor4lg41j9a9147h1krs', '2026-07-26 09:25:39', '2026-07-26 09:25:39', '2026-07-26 12:29:24', '119.40.90.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36'),
(0, 1, 'b7tl7dcjiu9ffb3d817dprqkr1', '2026-07-26 12:29:24', '2026-07-26 12:29:24', '2026-09-21 11:12:30', '27.147.137.49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36'),
(0, 1, 'qu4p6fbo0dgvfo8m7ifbnb2otf', '2026-09-21 11:12:30', '2026-09-21 11:12:30', '2026-09-21 11:13:33', '27.147.137.55', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36'),
(0, 1, 'qu4p6fbo0dgvfo8m7ifbnb2otf', '2026-09-21 11:13:33', '2026-09-21 11:13:33', '2026-09-22 05:10:03', '27.147.137.55', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36'),
(0, 1, 'hc7uf2aae0bmg3uv9ln0f1j2lj', '2026-09-22 05:10:03', '2026-09-22 05:10:03', '2026-09-22 06:30:31', '43.245.120.71', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 1, 'p909g6tgrpuhb12rk6q5sdmo7h', '2026-09-22 06:30:31', '2026-09-22 06:30:31', '2026-09-22 06:48:20', '103.37.184.190', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 1, 'bggfjpkf758pr8v9g7nn44fd1v', '2026-09-22 06:48:20', '2026-09-22 06:48:20', '2026-09-22 06:48:29', '202.134.8.192', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36'),
(0, 1, 'sfn5quuf6omle0q08aesqbhvpk', '2026-09-22 06:48:29', '2026-09-22 06:48:29', '2026-09-22 07:06:48', '202.134.10.141', 'Mozilla/5.0 (iPad; CPU OS 18_7_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/152.0.7977.64 Mobile/15E148 Safari/604.1'),
(0, 1, '996af2r7uhf1j2h1dvahmm5t3t', '2026-09-22 07:06:48', '2026-09-22 07:06:48', '2026-09-22 07:23:42', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 1, '996af2r7uhf1j2h1dvahmm5t3t', '2026-09-22 07:23:42', '2026-09-22 07:23:42', '2026-09-22 07:24:46', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 7, '4pooh4i84go0pagj8c382huau6', '2026-09-22 07:23:52', '2026-09-22 07:23:52', '2026-09-22 07:24:40', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 7, '4pooh4i84go0pagj8c382huau6', '2026-09-22 07:24:40', '2026-09-22 07:24:40', '2026-09-22 07:27:05', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 1, 'nfr0mdmhsr7o241jid9pn8koqc', '2026-09-22 07:24:46', '2026-09-22 07:24:46', '2026-09-22 07:27:00', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 1, 'nfr0mdmhsr7o241jid9pn8koqc', '2026-09-22 07:27:00', '2026-09-22 07:27:00', NULL, '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 7, '16dd5v3jd4s6n315503pvjsog8', '2026-09-22 07:27:05', '2026-09-22 07:27:05', '2026-09-22 07:28:35', '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36'),
(0, 7, '16dd5v3jd4s6n315503pvjsog8', '2026-09-22 07:28:35', '2026-09-22 07:28:35', NULL, '37.111.213.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `vaccine_master`
--

CREATE TABLE `vaccine_master` (
  `id` int(11) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `vaccine_type` enum('Hepatitis B','PCV-13','PPSV23','Influenza','Other') DEFAULT NULL,
  `schedule_description` text DEFAULT NULL,
  `dose_schedule` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccine_master`
--

INSERT INTO `vaccine_master` (`id`, `vaccine_name`, `vaccine_type`, `schedule_description`, `dose_schedule`, `is_active`, `created_at`) VALUES
(1, 'Hepatitis B vaccine', 'Hepatitis B', '3 doses at 0, 1, and 6 months', '0 month, 1 month, 6 month', 1, '2026-05-20 03:12:32'),
(2, 'Pneumococcal conjugate vaccine (PCV-13)', 'PCV-13', 'Single dose IM stat', '1 amp IM stat', 1, '2026-05-20 03:12:32'),
(3, 'Pneumococcal polysaccharide vaccine (PPSV23)', 'PPSV23', 'Single dose IM stat, repeat every 5 years, give 2 months after PCV-13', '1 amp IM stat (Every 5 years), 2 months after PCV-13', 1, '2026-05-20 03:12:32'),
(4, 'Influenza vaccine', 'Influenza', 'Annual vaccination', '1 amp IM stat (Every year)', 1, '2026-05-20 03:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `vaccine_reminders`
--

CREATE TABLE `vaccine_reminders` (
  `id` bigint(20) NOT NULL,
  `patient_id` int(100) NOT NULL,
  `vaccine_id` int(20) NOT NULL,
  `reminder_date` date NOT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `entity` (`entity`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`,`created_at`);

--
-- Indexes for table `current_histories`
--
ALTER TABLE `current_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_current_histories_patient_created` (`patient_id`,`created_at`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_division_district` (`division_id`,`name`),
  ADD KEY `idx_district_division` (`division_id`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `followups`
--
ALTER TABLE `followups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ibd_diagnoses`
--
ALTER TABLE `ibd_diagnoses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `investigations`
--
ALTER TABLE `investigations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_UNIQUE` (`id`);

--
-- Indexes for table `patient_attachments`
--
ALTER TABLE `patient_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `patient_vaccines`
--
ALTER TABLE `patient_vaccines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `vaccine_name` (`vaccine_name`),
  ADD KEY `status` (`status`),
  ADD KEY `next_dose_date` (`next_dose_date`),
  ADD KEY `administered_by` (`administered_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `socioeconomic_histories`
--
ALTER TABLE `socioeconomic_histories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `vaccine_master`
--
ALTER TABLE `vaccine_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vaccine_name` (`vaccine_name`);

--
-- Indexes for table `vaccine_reminders`
--
ALTER TABLE `vaccine_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `reminder_date` (`reminder_date`),
  ADD KEY `reminder_sent` (`reminder_sent`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `followups`
--
ALTER TABLE `followups`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `ibd_diagnoses`
--
ALTER TABLE `ibd_diagnoses`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `investigations`
--
ALTER TABLE `investigations`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `patient_attachments`
--
ALTER TABLE `patient_attachments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patient_vaccines`
--
ALTER TABLE `patient_vaccines`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `pregnancies`
--
ALTER TABLE `pregnancies`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `socioeconomic_histories`
--
ALTER TABLE `socioeconomic_histories`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vaccine_master`
--
ALTER TABLE `vaccine_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vaccine_reminders`
--
ALTER TABLE `vaccine_reminders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `patient_vaccines`
--
ALTER TABLE `patient_vaccines`
  ADD CONSTRAINT `patient_vaccines_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_vaccines_ibfk_2` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_vaccines_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccine_reminders`
--
ALTER TABLE `vaccine_reminders`
  ADD CONSTRAINT `vaccine_reminders_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccine_reminders_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `patient_vaccines` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
