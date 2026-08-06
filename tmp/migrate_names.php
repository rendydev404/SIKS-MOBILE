<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    $renames = [
        'UTS' => 'UTS 1',
        'UAS' => 'UAS 1',
        'Ujian Semester 1' => 'UTS 2',
        'Ujian Semester 2' => 'UAS 2'
    ];

    foreach ($renames as $old => $new) {
        // Update setting_pembayaran
        $stmt1 = $pdo->prepare("UPDATE setting_pembayaran SET jenis = ? WHERE jenis = ?");
        $stmt1->execute([$new, $old]);
        echo "Updated setting_pembayaran: $old -> $new (" . $stmt1->rowCount() . " rows)\n";

        // Update pembayaran
        $stmt2 = $pdo->prepare("UPDATE pembayaran SET jenis_pembayaran = ? WHERE jenis_pembayaran = ?");
        $stmt2->execute([$new, $old]);
        echo "Updated pembayaran: $old -> $new (" . $stmt2->rowCount() . " rows)\n";
    }

    $pdo->commit();
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
