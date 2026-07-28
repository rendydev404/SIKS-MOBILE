<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$res = hitungTunggakan($pdo, 36, true);
print_r($res);
