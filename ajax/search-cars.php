<?php
// ajax/search-cars.php — Live search for compare.php car picker
require_once '../connection.php';
header('Content-Type: application/json');

$q       = trim(cleanInput($_GET['q'] ?? ''));
$exclude = array_filter(array_map('intval', explode(',', $_GET['exclude'] ?? '')));

if (strlen($q) < 2) { echo '[]'; exit; }

// Build exclude clause
$exClause = '';
$params   = [];
if (!empty($exclude)) {
    $ph       = implode(',', array_fill(0, count($exclude), '?'));
    $exClause = "AND c.id NOT IN ($ph)";
    $params   = array_values($exclude);
}

$like = '%' . $q . '%';
array_push($params, $like, $like, $like, $like);

$rows = DB::select("
    SELECT c.id,
           m.name  AS make,
           mo.name AS model,
           c.year,
           c.price,
           c.city,
           (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS image_path
    FROM cars c
    JOIN makes  m  ON m.id  = c.make_id
    JOIN models mo ON mo.id = c.model_id AND mo.make_id = c.make_id
    WHERE c.status = 'active'
      $exClause
      AND (m.name LIKE ? OR mo.name LIKE ? OR c.year LIKE ? OR c.city LIKE ?)
    ORDER BY c.is_featured DESC, c.created_at DESC
    LIMIT 20
", $params) ?: [];

// Deduplicate by make+model+year — keep lowest priced per combination
$seen   = [];
$result = [];
foreach ($rows as $r) {
    $key = strtolower($r['make'] . '|' . $r['model'] . '|' . $r['year']);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $result[]   = [
            'id'        => (int)$r['id'],
            'make'      => $r['make'],
            'model'     => $r['model'],
            'year'      => (int)$r['year'],
            'city'      => $r['city'],
            'price_fmt' => formatPKR((float)$r['price']),
            'image'     => carImageUrl($r['image_path'] ?? '', true),
        ];
        if (count($result) >= 8) break;
    }
}

echo json_encode($result);
