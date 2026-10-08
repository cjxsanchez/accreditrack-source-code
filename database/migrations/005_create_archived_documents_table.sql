-- Create archived_documents table for staff document archives
CREATE TABLE IF NOT EXISTS archived_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT,
    indicator_id INT,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255),
    file_path VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    folder_id INT,
    requirement_id INT,
    uploaded_by INT,
    archived_by INT NOT NULL,
    original_created_at DATETIME,
    archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX idx_archived_by ON archived_documents(archived_by);
CREATE INDEX idx_archived_at ON archived_documents(archived_at);
