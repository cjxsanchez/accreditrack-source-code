-- Migration: Add Supabase storage columns to documents table
-- Run this if the columns don't exist

-- Add supabase_path column if not exists
ALTER TABLE documents ADD COLUMN IF NOT EXISTS supabase_path VARCHAR(500) NULL AFTER status;

-- Add file_size column if not exists  
ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_size BIGINT NULL AFTER supabase_path;

-- Add mime_type column if not exists
ALTER TABLE documents ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) NULL AFTER file_size;

-- Add folder_id column if not exists
ALTER TABLE documents ADD COLUMN IF NOT EXISTS folder_id INT NULL AFTER indicator_id;

-- Add requirement_id column if not exists
ALTER TABLE documents ADD COLUMN IF NOT EXISTS requirement_id INT NULL AFTER folder_id;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_documents_supabase_path ON documents(supabase_path);
CREATE INDEX IF NOT EXISTS idx_documents_folder_id ON documents(folder_id);
CREATE INDEX IF NOT EXISTS idx_documents_requirement_id ON documents(requirement_id);
