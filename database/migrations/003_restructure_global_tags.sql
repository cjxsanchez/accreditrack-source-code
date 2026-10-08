-- Add global tags table and restructure to support global tags with auto-copying
-- This migration creates a master global tags table and links it to indicators

-- Create global_accreditation_tags table for master definitions
CREATE TABLE IF NOT EXISTS `global_accreditation_tags` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `area_number` varchar(10) NOT NULL,
  `parameter_letter` varchar(5) NOT NULL,
  `indicator_type` enum('S', 'I', 'O') NOT NULL,
  `tag_name` varchar(255) NOT NULL,
  `tag_code` varchar(50) NOT NULL,
  `description` text,
  `order_index` int NOT NULL DEFAULT 0,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_global_tag (area_number, parameter_letter, indicator_type, tag_code),
  INDEX idx_area_indicator (area_number, parameter_letter, indicator_type),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modify existing accreditation_tags to reference global tags
ALTER TABLE `accreditation_tags` ADD COLUMN `global_tag_id` int NULL AFTER `id`;
ALTER TABLE `accreditation_tags` ADD FOREIGN KEY (`global_tag_id`) REFERENCES `global_accreditation_tags`(`id`) ON DELETE SET NULL;
ALTER TABLE `accreditation_tags` ADD INDEX `idx_global_tag_id` (`global_tag_id`);

-- Create a table to track when global tags were copied to a level
CREATE TABLE IF NOT EXISTS `level_tag_sync` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `level_id` int NOT NULL,
  `global_tag_id` int NOT NULL,
  `accreditation_tag_id` int NOT NULL,
  `synced_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE,
  FOREIGN KEY (global_tag_id) REFERENCES global_accreditation_tags(id) ON DELETE CASCADE,
  FOREIGN KEY (accreditation_tag_id) REFERENCES accreditation_tags(id) ON DELETE CASCADE,
  UNIQUE KEY unique_level_tag_sync (level_id, global_tag_id, accreditation_tag_id),
  INDEX idx_level_id (level_id),
  INDEX idx_global_tag_id (global_tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
