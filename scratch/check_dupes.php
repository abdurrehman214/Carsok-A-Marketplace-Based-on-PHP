<?php
require 'connection.php';
echo "--- DUPLICATE CAR IDs ---\n";
$res = DB::select("SELECT id, COUNT(*) as cnt FROM cars GROUP BY id HAVING cnt > 1");
print_r($res);

echo "\n--- JOIN TEST (Featured) ---\n";
$res2 = DB::select("
    SELECT c.id, COUNT(*) as row_cnt
    FROM cars c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN makes m ON m.id = c.make_id
    LEFT JOIN models mo ON mo.id = c.model_id
    WHERE c.status = 'active' AND c.is_featured = 1
    GROUP BY c.id
    HAVING row_cnt > 1
");
print_r($res2);

echo "\n--- IMAGE JOIN TEST (Old style) ---\n";
$res3 = DB::select("
    SELECT c.id, COUNT(*) as row_cnt
    FROM cars c
    LEFT JOIN car_images ci ON ci.car_id = c.id AND ci.is_featured = 1
    WHERE c.status = 'active'
    GROUP BY c.id
    HAVING row_cnt > 1
");
print_r($res3);
