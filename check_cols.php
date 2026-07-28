<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM siswa");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
