-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 30, 2025 at 09:17 AM
-- Server version: 10.11.8-MariaDB-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `st_alphonsus_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `Classes`
--

CREATE TABLE `Classes` (
  `class_id` int(11) NOT NULL,
  `class_name` enum('Reception Year','Year One','Year Two','Year Three','Year Four','Year Five','Year Six') NOT NULL,
  `capacity` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Classes`
--

INSERT INTO `Classes` (`class_id`, `class_name`, `capacity`, `teacher_id`) VALUES
(1, 'Reception Year', 25, 1),
(2, 'Year One', 30, 2),
(3, 'Year Two', 30, 3),
(4, 'Year Three', 30, 4),
(5, 'Year Four', 32, 5),
(6, 'Year Five', 32, NULL),
(8, 'Year Six', 36, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Parents`
--

CREATE TABLE `Parents` (
  `parent_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address_line1` varchar(100) NOT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `relationship_type` enum('Mother','Father','Guardian') NOT NULL DEFAULT 'Guardian'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Parents`
--

INSERT INTO `Parents` (`parent_id`, `first_name`, `last_name`, `address_line1`, `address_line2`, `city`, `postcode`, `email`, `phone`, `relationship_type`) VALUES
(1, 'Michelle', 'Obama', '1600 Pennsylvania Ave', NULL, 'Washington', 'DC 20500', 'm.obama@example.com', '2024561111', 'Mother'),
(2, 'Barack', 'Obama', '1600 Pennsylvania Ave', NULL, 'Washington', 'DC 20500', 'b.obama@example.com', '2024561112', 'Father'),
(3, 'Angela', 'Merkel', '1 Chancellery Street', NULL, 'Berlin', 'BE 10557', 'a.merkel@example.com', '030182722720', 'Guardian'),
(4, 'Joe', 'Bloggs', '1 Any Street', NULL, 'Anytown', 'AN1 1YT', NULL, '01234567899', 'Father');

-- --------------------------------------------------------

--
-- Table structure for table `Pupils`
--

CREATE TABLE `Pupils` (
  `pupil_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address_line1` varchar(100) NOT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `date_of_birth` date NOT NULL,
  `medical_notes` text DEFAULT NULL,
  `enrollment_date` date NOT NULL DEFAULT curdate(),
  `class_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Pupils`
--

INSERT INTO `Pupils` (`pupil_id`, `first_name`, `last_name`, `address_line1`, `address_line2`, `city`, `postcode`, `date_of_birth`, `medical_notes`, `enrollment_date`, `class_id`) VALUES
(1, 'Sasha', 'Obama', '1600 Pennsylvania Ave', NULL, 'Washington', 'DC 20500', '2001-06-10', 'Peanut allergy', '2025-03-29', 4),
(2, 'Malia', 'Obama', '1600 Pennsylvania Ave', NULL, 'Washington', 'DC 20500', '1998-07-04', NULL, '2025-03-29', 6),
(3, 'Hans', 'Schmidt', '2 Brandenburg Gate', NULL, 'Berlin', 'BE 10117', '2018-03-15', 'Asthma - requires inhaler.', '2025-03-29', 1),
(4, 'Jane', 'Bloggs', '1 Any Street', '', 'Anytown', 'AN1 1YT', '2017-09-20', '', '2025-03-29', 2);

-- --------------------------------------------------------

--
-- Table structure for table `Pupil_Parent`
--

CREATE TABLE `Pupil_Parent` (
  `pp_id` int(11) NOT NULL,
  `pupil_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Pupil_Parent`
--

INSERT INTO `Pupil_Parent` (`pp_id`, `pupil_id`, `parent_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 2, 1),
(4, 2, 2),
(5, 3, 3),
(10, 4, 4);

-- --------------------------------------------------------

--
-- Table structure for table `Teachers`
--

CREATE TABLE `Teachers` (
  `teacher_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address_line1` varchar(100) NOT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `annual_salary` decimal(10,2) NOT NULL,
  `background_check_status` enum('Pending','Cleared','Expired','Not Required') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Teachers`
--

INSERT INTO `Teachers` (`teacher_id`, `first_name`, `last_name`, `address_line1`, `address_line2`, `city`, `postcode`, `phone`, `email`, `annual_salary`, `background_check_status`) VALUES
(1, 'Eleanor', 'Rigby', '1 Abbey Road', NULL, 'Liverpool', 'L1 1AA', '01514441111', 'e.rigby@stalphonsus.sch.uk', 35000.00, 'Cleared'),
(2, 'Desmond', 'Jones', '2 Penny Lane', NULL, 'Liverpool', 'L1 2BB', '01514442222', 'd.jones@stalphonsus.sch.uk', 36500.00, 'Cleared'),
(3, 'Lucy', 'Diamond Heartbroken', '3 Sky Street', NULL, 'Liverpool', 'L1 3CC', '01514443333', 'l.diamond@stalphonsus.sch.uk', 34000.00, 'Expired'),
(4, 'Jude', 'Harrison', '4 Hey Street', NULL, 'Liverpool', 'L1 4DD', '01514445555', 'j.harrison@stalphonsus.sch.uk', 37000.00, 'Cleared'),
(5, '123', '123', '123', NULL, '123', '123', '123', '123one@gmail.com', 123.00, 'Cleared');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Classes`
--
ALTER TABLE `Classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `class_name` (`class_name`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `Parents`
--
ALTER TABLE `Parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `Pupils`
--
ALTER TABLE `Pupils`
  ADD PRIMARY KEY (`pupil_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `Pupil_Parent`
--
ALTER TABLE `Pupil_Parent`
  ADD PRIMARY KEY (`pp_id`),
  ADD UNIQUE KEY `unique_pupil_parent` (`pupil_id`,`parent_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `Teachers`
--
ALTER TABLE `Teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Classes`
--
ALTER TABLE `Classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `Parents`
--
ALTER TABLE `Parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `Pupils`
--
ALTER TABLE `Pupils`
  MODIFY `pupil_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `Pupil_Parent`
--
ALTER TABLE `Pupil_Parent`
  MODIFY `pp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `Teachers`
--
ALTER TABLE `Teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Classes`
--
ALTER TABLE `Classes`
  ADD CONSTRAINT `Classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `Teachers` (`teacher_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `Pupils`
--
ALTER TABLE `Pupils`
  ADD CONSTRAINT `Pupils_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `Classes` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `Pupil_Parent`
--
ALTER TABLE `Pupil_Parent`
  ADD CONSTRAINT `Pupil_Parent_ibfk_1` FOREIGN KEY (`pupil_id`) REFERENCES `Pupils` (`pupil_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `Pupil_Parent_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `Parents` (`parent_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
