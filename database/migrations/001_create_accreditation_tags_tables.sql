-- ============================================================================
-- ACCREDITATION TAGS/REQUIREMENTS TABLES
-- ============================================================================

-- Accreditation Tag Names Table
-- Stores the list of file requirement tags/names for each indicator
CREATE TABLE IF NOT EXISTS `accreditation_tags` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `indicator_id` int NOT NULL,
  `tag_name` varchar(255) NOT NULL,
  `tag_code` varchar(50) NOT NULL,
  `description` text,
  `order_index` int NOT NULL DEFAULT 0,
  `is_active` boolean DEFAULT TRUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
  UNIQUE KEY unique_tag_per_indicator (indicator_id, tag_code),
  INDEX idx_indicator_id (indicator_id),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document Tag Mapping Table
-- Links uploaded documents to their selected tags
CREATE TABLE IF NOT EXISTS `document_tags` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `document_id` int NOT NULL,
  `accreditation_tag_id` int NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  FOREIGN KEY (accreditation_tag_id) REFERENCES accreditation_tags(id) ON DELETE CASCADE,
  UNIQUE KEY unique_document_tag (document_id, accreditation_tag_id),
  INDEX idx_document_id (document_id),
  INDEX idx_tag_id (accreditation_tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for performance
CREATE INDEX idx_tags_indicator_active ON accreditation_tags(indicator_id, is_active);
CREATE INDEX idx_document_tags_document ON document_tags(document_id);
