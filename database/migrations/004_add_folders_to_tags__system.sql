-- Add folders table for organizing requirements
CREATE TABLE IF NOT EXISTS `accreditation_folders` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `global_tag_id` int NULL,
  `area_number` varchar(10) NOT NULL,
  `parameter_letter` varchar(5) NOT NULL,
  `indicator_type` enum('S', 'I', 'O') NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `folder_order` int NOT NULL DEFAULT 0,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (global_tag_id) REFERENCES global_accreditation_tags(id) ON DELETE SET NULL,
  INDEX idx_indicator (area_number, parameter_letter, indicator_type),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rename old accreditation_tags table to accreditation_requirements
-- to avoid confusion with folders
ALTER TABLE `accreditation_tags` RENAME TO `accreditation_requirements`;

-- Add folder_id to requirements
ALTER TABLE `accreditation_requirements` ADD COLUMN `folder_id` int NULL AFTER `indicator_id`;
ALTER TABLE `accreditation_requirements` ADD FOREIGN KEY (`folder_id`) REFERENCES `accreditation_folders`(`id`) ON DELETE CASCADE;
ALTER TABLE `accreditation_requirements` ADD INDEX `idx_folder_id` (`folder_id`);

-- Rename tag_name to requirement_name for clarity
ALTER TABLE `accreditation_requirements` CHANGE COLUMN `tag_name` `requirement_name` varchar(255) NOT NULL;
ALTER TABLE `accreditation_requirements` CHANGE COLUMN `tag_code` `requirement_code` varchar(50) NOT NULL;

-- Update global_accreditation_tags similarly
ALTER TABLE `global_accreditation_tags` CHANGE COLUMN `tag_name` `folder_name` varchar(255) NOT NULL;
ALTER TABLE `global_accreditation_tags` CHANGE COLUMN `tag_code` `folder_code` varchar(50) NOT NULL;
ALTER TABLE `global_accreditation_tags` CHANGE COLUMN `description` `folder_description` text;

-- Create table for global requirements (inside global folders)
CREATE TABLE IF NOT EXISTS `global_requirements` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `global_folder_id` int NOT NULL,
  `requirement_name` varchar(255) NOT NULL,
  `requirement_code` varchar(50) NOT NULL,
  `requirement_description` text,
  `requirement_order` int NOT NULL DEFAULT 0,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (global_folder_id) REFERENCES global_accreditation_tags(id) ON DELETE CASCADE,
  UNIQUE KEY unique_requirement (global_folder_id, requirement_code),
  INDEX idx_global_folder_id (global_folder_id),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create program-level requirements (copies of global requirements)
CREATE TABLE IF NOT EXISTS `program_requirements` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `folder_id` int NOT NULL,
  `requirement_name` varchar(255) NOT NULL,
  `requirement_code` varchar(50) NOT NULL,
  `requirement_description` text,
  `requirement_order` int NOT NULL DEFAULT 0,
  `global_requirement_id` int NULL,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (folder_id) REFERENCES accreditation_folders(id) ON DELETE CASCADE,
  FOREIGN KEY (global_requirement_id) REFERENCES global_requirements(id) ON SET NULL,
  INDEX idx_folder_id (folder_id),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update documents table to track folder and requirement
ALTER TABLE `documents` ADD COLUMN `folder_id` int NULL AFTER `indicator_id`;
ALTER TABLE `documents` ADD COLUMN `requirement_id` int NULL AFTER `folder_id`;
ALTER TABLE `documents` ADD FOREIGN KEY (`folder_id`) REFERENCES `accreditation_folders`(`id`) ON SET NULL;
ALTER TABLE `documents` ADD FOREIGN KEY (`requirement_id`) REFERENCES `program_requirements`(`id`) ON SET NULL;
ALTER TABLE `documents` ADD INDEX `idx_folder_id` (`folder_id`);
ALTER TABLE `documents` ADD INDEX `idx_requirement_id` (`requirement_id`);
