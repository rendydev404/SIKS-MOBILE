<?php
require_once 'config/database.php';
try {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE nama LIKE ?");
    $stmt->execute(['%putra%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
