-- Migration: Add home_take_received table
-- Run this SQL on your database

CREATE TABLE IF NOT EXISTS `home_take_received` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `weekly_report_id` int(11) NOT NULL,
  `received_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `weekly_report_id` (`weekly_report_id`),
  CONSTRAINT `htr_ibfk_1` FOREIGN KEY (`weekly_report_id`) REFERENCES `weekly_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
