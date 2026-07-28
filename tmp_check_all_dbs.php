<?php
$dbs = ['db_spp', 'ppdb_smkalamin', 'smkalaamin', 'test'];
foreach ($dbs as $db) {
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=$db", 'root', '');
        $stmt = $pdo->query('SHOW TABLES');
        echo "\nTables in $db:\n";
        print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        echo "Error $db: " . $e->getMessage() . "\n";
    }
}
?>
