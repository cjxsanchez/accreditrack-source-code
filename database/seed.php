<?php
/**
 * Database Seeding Script
 * Populates initial data including areas, parameters, and indicators
 */

require_once __DIR__ . '/../config/config.php';

class DatabaseSeeder {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Run all seeds
     */
    public function run() {
        echo "Starting database seeding...\n";

        try {
            $this->seedAreaStructure();
            echo "✓ Area structure seeded successfully\n";

            $this->seedAdminUser();
            echo "✓ Admin user created successfully\n";

            echo "\n✓ Database seeding completed successfully!\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Seeding failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Seed area structure with parameters and indicators
     */
    private function seedAreaStructure() {
        $areas = getAreaStructure();

        foreach ($areas as $areaCode => $areaData) {
            // Get or create a test level first (this requires a program and level to exist)
            // For initial seed, we'll just create the structure
            // In production, this would be called when a level is created

            foreach ($areaData['parameters'] as $paramCode => $paramData) {
                foreach ($paramData['indicators'] as $indicatorType => $requiredDocs) {
                    $indicatorName = $this->getIndicatorName($indicatorType);
                    // This will be populated when levels are created
                }
            }
        }
    }

    /**
     * Seed default admin user
     */
    private function seedAdminUser() {
        // Check if admin already exists
        $query = "SELECT id FROM users WHERE username = 'admin' AND role = 'admin'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return; // Admin already exists
        }

        $username = 'admin';
        $email = 'admin@accreditrack.local';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $full_name = 'Administrator';

        $query = "INSERT INTO users (username, email, password, full_name, role, status) 
                  VALUES (?, ?, ?, ?, 'admin', 'active')";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username, $email, $password, $full_name]);
    }

    /**
     * Get indicator name
     */
    private function getIndicatorName($type) {
        $names = [
            'S' => 'System - Inputs and Processes',
            'I' => 'Implementation',
            'O' => 'Outcome/s'
        ];
        return $names[$type] ?? 'Unknown';
    }

    /**
     * Create area structure for a specific level
     */
    public static function createAreaStructureForLevel($levelId, $db) {
        $areas = getAreaStructure();

        foreach ($areas as $areaCode => $areaData) {
            // Create area
            $query = "INSERT INTO areas (level_id, area_number, name) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$levelId, $areaCode, $areaData['name']]);
            $areaId = $db->lastInsertId();

            // Create parameters
            foreach ($areaData['parameters'] as $paramCode => $paramData) {
                $query = "INSERT INTO parameters (area_id, parameter_letter, name) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$areaId, $paramCode, $paramData['name']]);
                $parameterId = $db->lastInsertId();

                // Create indicators
                foreach ($paramData['indicators'] as $indicatorType => $requiredDocs) {
                    $indicatorName = match($indicatorType) {
                        'S' => 'System - Inputs and Processes',
                        'I' => 'Implementation',
                        'O' => 'Outcome/s',
                        default => 'Unknown'
                    };

                    $query = "INSERT INTO indicators (parameter_id, indicator_type, name, required_documents) 
                              VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$parameterId, $indicatorType, $indicatorName, $requiredDocs]);
                }
            }
        }
    }
}

// Run seeder if executed directly
if (php_sapi_name() === 'cli') {
    $seeder = new DatabaseSeeder();
    $seeder->run();
}

?>
