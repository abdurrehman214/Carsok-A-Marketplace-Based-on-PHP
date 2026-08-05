<?php
// ajax/get-models.php — Returns models JSON for a given make slug
require_once '../connection.php';
header('Content-Type: application/json');

$make = cleanInput($_GET['make'] ?? '');
if (!$make) { echo '[]'; exit; }

$models = DB::select(
    "SELECT mo.name, mo.slug FROM models mo
     JOIN makes m ON m.id = mo.make_id
     WHERE m.slug = ? GROUP BY mo.name ORDER BY mo.name",
    [$make]
);
echo json_encode($models);
