<?php
require_once 'config/database.php';
$s=$pdo->query('SELECT * FROM setting_pembayaran');
print_r($s->fetchAll(PDO::FETCH_ASSOC));
