<?php
require_once 'config/database.php';

try {
    echo "Creating wa_settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS wa_settings (
        id INT PRIMARY KEY DEFAULT 1,
        token VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    
    // Insert default row if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM wa_settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO wa_settings (id, token) VALUES (1, '')");
    }
    
    echo "Table wa_settings created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
