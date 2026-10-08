-- ============================================================================
-- SEED INITIAL ACCREDITATION TAGS FOR ALL AREAS
-- ============================================================================
-- This script seeds the system with a comprehensive list of file requirements
-- for each area, parameter, and indicator based on accreditation standards.
-- These tags define what documents must be uploaded for each indicator.

-- AREA I: INSTITUTIONAL CONTEXT
-- Parameter A: Mission and Goals
INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index) 
SELECT i.id, 'Institutional Mission Statement', 'MISSION-001', 'Official mission statement document', 0
FROM indicators i
JOIN parameters p ON i.parameter_id = p.id
JOIN areas a ON p.area_id = a.id
WHERE a.area_number = 'Area I' AND p.parameter_letter = 'A' AND i.indicator_type = 'S'
LIMIT 1;

INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index) 
SELECT i.id, 'Strategic Goals and Objectives', 'GOALS-001', 'Strategic planning documents', 1
FROM indicators i
JOIN parameters p ON i.parameter_id = p.id
JOIN areas a ON p.area_id = a.id
WHERE a.area_number = 'Area I' AND p.parameter_letter = 'A' AND i.indicator_type = 'S'
LIMIT 1;

-- Parameter B: Administration
INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index) 
SELECT i.id, 'Board Resolution and Charter', 'BOARD-001', 'Governing board documentation', 0
FROM indicators i
JOIN parameters p ON i.parameter_id = p.id
JOIN areas a ON p.area_id = a.id
WHERE a.area_number = 'Area I' AND p.parameter_letter = 'B' AND i.indicator_type = 'S'
LIMIT 1;

INSERT INTO accreditation_tags (indicator_id, tag_name, tag_code, description, order_index) 
SELECT i.id, 'Organizational Chart', 'ORG-CHART-001', 'Administrative organizational structure', 1
FROM indicators i
JOIN parameters p ON i.parameter_id = p.id
JOIN areas a ON p.area_id = a.id
WHERE a.area_number = 'Area I' AND p.parameter_letter = 'B' AND i.indicator_type = 'S'
LIMIT 1;

-- ============================================================================
-- NOTE: This is a template showing how to seed tags for Area I.
-- To complete the seeding:
-- 1. Continue this pattern for each area (Area II through Area X)
-- 2. For each parameter in each area
-- 3. For each indicator type (S, I, O)
-- 4. Add specific file requirement tags
--
-- Use the accreditation manual to determine required documents per indicator.
-- Each indicator typically needs 2-5 file requirement tags depending on context.
-- ============================================================================

-- Create a helper view to see current structure
CREATE OR REPLACE VIEW accreditation_structure AS
SELECT 
    a.area_number,
    p.parameter_letter,
    p.name as parameter_name,
    i.indicator_type,
    i.name as indicator_name,
    COUNT(DISTINCT at.id) as total_tags
FROM areas a
JOIN parameters p ON a.id = p.area_id
JOIN indicators i ON p.id = i.parameter_id
LEFT JOIN accreditation_tags at ON i.id = at.indicator_id AND at.is_active = TRUE
GROUP BY a.id, p.id, i.id
ORDER BY a.area_number, p.parameter_letter, i.indicator_type;
