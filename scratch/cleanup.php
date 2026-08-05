<?php
require_once __DIR__ . '/../connection.php';

echo "<h2>CarSoko Database Cleanup</h2>";

if (!DB::isConnected()) {
    die("<span style='color:red'>Error: Database connection failed. Please ensure this script is running on the production server.</span>");
}

$tablesToDrop = ['saved_cars', 'saved_searches'];

foreach ($tablesToDrop as $table) {
    try {
        echo "Dropping table: <strong>$table</strong>... ";
        DB::execute("DROP TABLE IF EXISTS $table");
        echo "<span style='color:green'>Done.</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:red'>Failed: " . $e->getMessage() . "</span><br>";
    }
}

echo "<br>Cleanup complete.";
?>
