<?php
require_once '../config/config.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if references table exists
    $query = "SHOW TABLES LIKE 'references'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Create references table
        $sql = "
        CREATE TABLE references (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $db->exec($sql);
        echo json_encode([
            'success' => true,
            'message' => 'References table created successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'References table already exists'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
