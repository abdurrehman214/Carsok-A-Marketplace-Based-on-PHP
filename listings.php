<?php
//  CarSoko Pakistan — listings.php
//  Search & Filter Results Page
//  Requires: connection.php
// ============================================================
require_once 'connection.php';

// ============================================================
// FILTER INPUTS — Sanitize all GET params
// ============================================================
$f = [
    'make'         => cleanInput($_GET['make']         ?? ''),
    'model'        => cleanInput($_GET['model']        ?? ''),
    'min_price'    => (int)   ($_GET['min_price']      ?? 0),
    'max_price'    => (int)   ($_GET['max_price']      ?? 0),
    'min_year'     => (int)   ($_GET['min_year']       ?? 0),
    'max_year'     => (int)   ($_GET['max_year']       ?? 0),
    'min_mileage'  => (int)   ($_GET['min_mileage']    ?? 0),
    'max_mileage'  => (int)   ($_GET['max_mileage']    ?? 0),
    'fuel_type'    => cleanInput($_GET['fuel_type']    ?? ''),
    'transmission' => cleanInput($_GET['transmission'] ?? ''),
    'body_type'    => cleanInput($_GET['body_type']    ?? ''),
    'drive_type'   => cleanInput($_GET['drive_type']   ?? ''),
    'condition'    => cleanInput($_GET['condition']    ?? ''),
    'city'         => cleanInput($_GET['city']         ?? ''),
    'county'       => cleanInput($_GET['county']       ?? ''),
    'seller'       => cleanInput($_GET['seller']       ?? ''),  // dealer/private
    'verified'     => (int)   ($_GET['verified']       ?? 0),
    'featured'     => (int)   ($_GET['featured']       ?? 0),
    'price_drop'   => (int)   ($_GET['price_drop']     ?? 0),
    'q'            => cleanInput($_GET['q']            ?? ''),  // keyword search
    'sort'         => cleanInput($_GET['sort']         ?? 'newest'),
    'view'         => in_array($_GET['view'] ?? '', ['grid','list']) ? $_GET['view'] : 'grid',
    'page'         => max(1, (int)($_GET['page']       ?? 1)),
    'per_page'     => 12,
];

// ============================================================
// BUILD QUERY DYNAMICALLY
// ============================================================
$where  = ["c.status = 'active'"];
$params = [];

if ($f['make']) {
    $where[]  = "m.slug = ?";
    $params[] = $f['make'];
}
if ($f['model']) {
    $where[]  = "mo.slug = ?";
    $params[] = $f['model'];
}
if ($f['min_price'] > 0) {
    $where[]  = "c.price >= ?";
    $params[] = $f['min_price'];
}
if ($f['max_price'] > 0) {
    $where[]  = "c.price <= ?";
    $params[] = $f['max_price'];
}
if ($f['min_year'] > 0) {
    $where[]  = "c.year >= ?";
    $params[] = $f['min_year'];
}
if ($f['max_year'] > 0) {
    $where[]  = "c.year <= ?";
    $params[] = $f['max_year'];
}
if ($f['min_mileage'] > 0) {
    $where[]  = "c.mileage >= ?";
    $params[] = $f['min_mileage'];
}
if ($f['max_mileage'] > 0) {
    $where[]  = "c.mileage <= ?";
    $params[] = $f['max_mileage'];
}
if ($f['fuel_type']) {
    $where[]  = "c.fuel_type = ?";
    $params[] = $f['fuel_type'];
}
if ($f['transmission']) {
    $where[]  = "c.transmission = ?";
    $params[] = $f['transmission'];
}
if ($f['body_type']) {
    $where[]  = "c.body_type = ?";
    $params[] = $f['body_type'];
}
if ($f['drive_type']) {
    $where[]  = "c.drive_type = ?";
    $params[] = $f['drive_type'];
}
if ($f['condition']) {
    $where[]  = "c.condition = ?";
    $params[] = $f['condition'];
}
if ($f['city']) {
    $where[]  = "c.city = ?";
    $params[] = $f['city'];
}
if ($f['seller'] === 'dealer') {
    $where[] = "u.role = 'dealer'";
} elseif ($f['seller'] === 'private') {
    $where[] = "u.role = 'private_seller'";
}
if ($f['verified']) {
    $where[] = "u.is_verified_seller = 1";
}
if ($f['featured']) {
    $where[] = "c.is_featured = 1";
}
if ($f['q']) {
    $where[]  = "(MATCH(c.description, c.features) AGAINST(? IN BOOLEAN MODE) OR CONCAT(m.name,' ',mo.name) LIKE ?)";
    $params[] = $f['q'] . '*';
    $params[] = '%' . $f['q'] . '%';
}

$whereSQL = implode(' AND ', $where);

// Sort
switch($f['sort']) {
    case 'price_asc':   $sortSQL = 'c.price ASC'; break;
    case 'price_desc':  $sortSQL = 'c.price DESC'; break;
    case 'year_desc':   $sortSQL = 'c.year DESC'; break;
    case 'year_asc':    $sortSQL = 'c.year ASC'; break;
    case 'mileage_asc': $sortSQL = 'c.mileage ASC'; break;
    case 'popular':     $sortSQL = 'c.views DESC'; break;
    default:            $sortSQL = 'c.is_featured DESC, c.created_at DESC'; break;
}

// ============================================================
// COUNT TOTAL (for pagination)
// ============================================================
$countSQL = "
    SELECT COUNT(DISTINCT c.id)
    FROM cars c
    JOIN makes  m  ON m.id  = c.make_id
    LEFT JOIN models mo ON mo.id = c.model_id AND mo.make_id = c.make_id
    JOIN users  u  ON u.id  = c.user_id
    WHERE $whereSQL
";
$totalCars  = (int) DB::value($countSQL, $params);
$totalPages = max(1, ceil($totalCars / $f['per_page']));
$f['page']  = min($f['page'], $totalPages);
$offset     = ($f['page'] - 1) * $f['per_page'];

// ============================================================
// FETCH CARS
// ============================================================
// ============================================================
// FETCH CARS — Corrected Syntax
// ============================================================
$carsSQL = "
    SELECT 
        c.*, 
        m.name AS make_name, m.slug AS make_slug,
        (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name,
        (SELECT slug FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_slug,
        u.name AS seller_name, u.role AS seller_type,
        u.is_verified_seller,
        (SELECT ci.image_path FROM car_images ci 
         WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS featured_image
    FROM cars c
    JOIN makes m ON m.id = c.make_id
    LEFT JOIN models mo ON mo.id = c.model_id AND mo.make_id = c.make_id
    JOIN users u ON u.id = c.user_id
    WHERE $whereSQL
    GROUP BY c.id
    ORDER BY $sortSQL
    LIMIT $offset, {$f['per_page']}
";
$cars = DB::select($carsSQL, $params);

// ============================================================
// SIDEBAR DATA — cached in PHP session (5 min) to cut DB queries
// ============================================================
$_ck  = 'sb_cache';
$_age = 300;
if (empty($_SESSION[$_ck]) || (time() - ($_SESSION[$_ck]['_ts'] ?? 0)) > $_age) {
    $_SESSION[$_ck] = [
        '_ts'    => time(),
        'makes'  => DB::select("SELECT MIN(id) AS id, name, MIN(slug) AS slug FROM makes GROUP BY LOWER(name) ORDER BY name ASC"),
        'cities' => DB::select("SELECT DISTINCT city FROM cars WHERE status='active' AND city != '' ORDER BY city LIMIT 40") ?: [
            ['city'=>'Karachi'],['city'=>'Lahore'],['city'=>'Islamabad'],
            ['city'=>'Faisalabad'],['city'=>'Rawalpindi'],['city'=>'Multan'],
            ['city'=>'Gujranwala'],['city'=>'Peshawar'],['city'=>'Quetta'],['city'=>'Sialkot'],
        ],
    ];
}
$makes  = $_SESSION[$_ck]['makes']  ?? [];
$cities = $_SESSION[$_ck]['cities'] ?? [];
$models = $f['make']
    ? DB::select("SELECT MIN(mo.id) AS id, mo.name, MIN(mo.slug) AS slug FROM models mo JOIN makes m ON m.id=mo.make_id WHERE m.slug=? GROUP BY LOWER(mo.name) ORDER BY mo.name", [$f['make']])
    : [];
$priceRange = ['min_p' => 0, 'max_p' => 20000000];

// ============================================================
// ACTIVE FILTER CHIPS (for display)
// ============================================================
$activeFilters = [];
if ($f['make'])        $activeFilters['make']         = ['label' => 'Make: ' . ucfirst($f['make']),         'remove' => 'make'];
if ($f['model'])       $activeFilters['model']        = ['label' => 'Model: ' . ucfirst($f['model']),        'remove' => 'model'];
if ($f['city'])        $activeFilters['city']         = ['label' => 'City: ' . $f['city'],                  'remove' => 'city'];
if ($f['fuel_type'])   $activeFilters['fuel_type']    = ['label' => 'Fuel: ' . ucfirst($f['fuel_type']),    'remove' => 'fuel_type'];
if ($f['transmission'])$activeFilters['transmission'] = ['label' => 'Gearbox: ' . ucfirst($f['transmission']),'remove'=> 'transmission'];
if ($f['body_type'])   $activeFilters['body_type']    = ['label' => 'Body: ' . ucfirst($f['body_type']),    'remove' => 'body_type'];
if ($f['condition'])   $activeFilters['condition']    = ['label' => 'Condition: ' . str_replace('_',' ',ucfirst($f['condition'])),'remove'=>'condition'];
if ($f['seller'])      $activeFilters['seller']       = ['label' => 'Seller: ' . ucfirst($f['seller']),     'remove' => 'seller'];
if ($f['min_price'])   $activeFilters['min_price']    = ['label' => 'Min: ' . formatPKR($f['min_price'],true),'remove'=>'min_price'];
if ($f['max_price'])   $activeFilters['max_price']    = ['label' => 'Max: ' . formatPKR($f['max_price'],true),'remove'=>'max_price'];
if ($f['min_year'])    $activeFilters['min_year']     = ['label' => 'From ' . $f['min_year'],               'remove' => 'min_year'];
if ($f['max_year'])    $activeFilters['max_year']     = ['label' => 'To ' . $f['max_year'],                 'remove' => 'max_year'];
if ($f['verified'])    $activeFilters['verified']     = ['label' => '✓ Verified Sellers',                    'remove' => 'verified'];
if ($f['featured'])    $activeFilters['featured']     = ['label' => '⭐ Featured',                           'remove' => 'featured'];
if ($f['q'])           $activeFilters['q']            = ['label' => 'Search: "' . $f['q'] . '"',            'remove' => 'q'];

// Build current URL without a specific param (for filter chip removal)
function removeFilter(string $param): string {
    $params = $_GET;
    unset($params[$param], $params['page']);
    return 'listings.php' . ($params ? '?' . http_build_query($params) : '');
}

// Build URL preserving all current filters + new param
function filterUrl(array $newParams): string {
    $params = array_merge($_GET, $newParams);
    return 'listings.php?' . http_build_query($params);
}

// SEO meta
$pageTitle = 'Cars for Sale in Pakistan';
if ($f['make'])    $pageTitle = ucfirst($f['make']) . ($f['model'] ? ' ' . ucfirst($f['model']) : '') . ' for Sale in Pakistan';
if ($f['city'])    $pageTitle .= ' – ' . $f['city'];
$metaDesc = "Browse $totalCars cars for sale in Pakistan. " . ($f['make'] ? ucfirst($f['make']) . ' ' : '') . "Used & new cars from private sellers and verified dealers.";

// Current year for year dropdowns
$curYear = (int) date('Y');

// Dummy cars for demo (when DB not connected)
// if (empty($cars)) {
//     $cars = [
//         ['id'=>1,'make_name'=>'Toyota','model_name'=>'Corolla Fielder','year'=>2018,'price'=>1850000,'mileage'=>42000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'wagon','condition'=>'used','city'=>'Nairobi','slug'=>'toyota-corolla-fielder-2018-nairobi','is_featured'=>1,'is_urgent'=>0,'views'=>342,'created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'make_slug'=>'toyota','model_slug'=>'corolla-fielder','seller_name'=>'AutoNairobi Ltd','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?w=600&q=80','drive_type'=>'2wd','engine_cc'=>1800,'color'=>'Silver','price_negotiable'=>1],
//         ['id'=>2,'make_name'=>'Honda','model_name'=>'Fit','year'=>2017,'price'=>980000,'mileage'=>55000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'hatchback','condition'=>'used','city'=>'Mombasa','slug'=>'honda-fit-2017-mombasa','is_featured'=>0,'is_urgent'=>1,'views'=>189,'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'make_slug'=>'honda','model_slug'=>'fit','seller_name'=>'James M.','seller_type'=>'private_seller','is_verified_seller'=>0,'featured_image'=>'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=600&q=80','drive_type'=>'2wd','engine_cc'=>1300,'color'=>'White','price_negotiable'=>1],
//         ['id'=>3,'make_name'=>'Mercedes-Benz','model_name'=>'C-Class','year'=>2019,'price'=>4500000,'mileage'=>28000,'fuel_type'=>'diesel','transmission'=>'automatic','body_type'=>'sedan','condition'=>'foreign_used','city'=>'Nairobi','slug'=>'mercedes-c-class-2019-nairobi','is_featured'=>1,'is_urgent'=>0,'views'=>521,'created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'make_slug'=>'mercedes','model_slug'=>'c-class','seller_name'=>'Prestige Motors','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=600&q=80','drive_type'=>'2wd','engine_cc'=>2000,'color'=>'Black','price_negotiable'=>0],
//         ['id'=>4,'make_name'=>'Nissan','model_name'=>'X-Trail','year'=>2016,'price'=>2200000,'mileage'=>78000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'suv','condition'=>'used','city'=>'Kisumu','slug'=>'nissan-x-trail-2016-kisumu','is_featured'=>0,'is_urgent'=>0,'views'=>97,'created_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'make_slug'=>'nissan','model_slug'=>'x-trail','seller_name'=>'Sarah K.','seller_type'=>'private_seller','is_verified_seller'=>0,'featured_image'=>'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=600&q=80','drive_type'=>'4wd','engine_cc'=>2000,'color'=>'Blue','price_negotiable'=>1],
//         ['id'=>5,'make_name'=>'Mazda','model_name'=>'Demio','year'=>2019,'price'=>1250000,'mileage'=>31000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'hatchback','condition'=>'foreign_used','city'=>'Nairobi','slug'=>'mazda-demio-2019-nairobi','is_featured'=>1,'is_urgent'=>0,'views'=>278,'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'make_slug'=>'mazda','model_slug'=>'demio','seller_name'=>'JapanAutos KE','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1609521263047-f8f205293f24?w=600&q=80','drive_type'=>'2wd','engine_cc'=>1300,'color'=>'Red','price_negotiable'=>0],
//         ['id'=>6,'make_name'=>'Subaru','model_name'=>'Forester','year'=>2017,'price'=>2800000,'mileage'=>62000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'suv','condition'=>'used','city'=>'Nairobi','slug'=>'subaru-forester-2017-nairobi','is_featured'=>0,'is_urgent'=>1,'views'=>156,'created_at'=>date('Y-m-d H:i:s',strtotime('-4 days')),'make_slug'=>'subaru','model_slug'=>'forester','seller_name'=>'Peter O.','seller_type'=>'private_seller','is_verified_seller'=>0,'featured_image'=>'https://images.unsplash.com/photo-1606016159991-dfe4f2746ad5?w=600&q=80','drive_type'=>'awd','engine_cc'=>2000,'color'=>'Grey','price_negotiable'=>1],
//         ['id'=>7,'make_name'=>'Toyota','model_name'=>'Land Cruiser','year'=>2015,'price'=>9200000,'mileage'=>110000,'fuel_type'=>'diesel','transmission'=>'automatic','body_type'=>'suv','condition'=>'used','city'=>'Nairobi','slug'=>'toyota-land-cruiser-2015-nairobi','is_featured'=>1,'is_urgent'=>0,'views'=>834,'created_at'=>date('Y-m-d H:i:s',strtotime('-7 days')),'make_slug'=>'toyota','model_slug'=>'land-cruiser','seller_name'=>'Prestige Motors','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&q=80','drive_type'=>'4wd','engine_cc'=>4500,'color'=>'White','price_negotiable'=>1],
//         ['id'=>8,'make_name'=>'Volkswagen','model_name'=>'Golf','year'=>2018,'price'=>1650000,'mileage'=>48000,'fuel_type'=>'petrol','transmission'=>'manual','body_type'=>'hatchback','condition'=>'used','city'=>'Nakuru','slug'=>'volkswagen-golf-2018-nakuru','is_featured'=>0,'is_urgent'=>0,'views'=>63,'created_at'=>date('Y-m-d H:i:s',strtotime('-6 days')),'make_slug'=>'volkswagen','model_slug'=>'golf','seller_name'=>'Allan W.','seller_type'=>'private_seller','is_verified_seller'=>0,'featured_image'=>'https://images.unsplash.com/photo-1471479917193-f00955256257?w=600&q=80','drive_type'=>'2wd','engine_cc'=>1400,'color'=>'White','price_negotiable'=>1],
//         ['id'=>9,'make_name'=>'BMW','model_name'=>'X5','year'=>2016,'price'=>5800000,'mileage'=>88000,'fuel_type'=>'diesel','transmission'=>'automatic','body_type'=>'suv','condition'=>'foreign_used','city'=>'Nairobi','slug'=>'bmw-x5-2016-nairobi','is_featured'=>1,'is_urgent'=>0,'views'=>412,'created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'make_slug'=>'bmw','model_slug'=>'x5','seller_name'=>'Luxury Cars KE','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=600&q=80','drive_type'=>'4wd','engine_cc'=>3000,'color'=>'Black','price_negotiable'=>1],
//         ['id'=>10,'make_name'=>'Mitsubishi','model_name'=>'Outlander','year'=>2018,'price'=>3200000,'mileage'=>45000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'suv','condition'=>'foreign_used','city'=>'Mombasa','slug'=>'mitsubishi-outlander-2018-mombasa','is_featured'=>0,'is_urgent'=>0,'views'=>88,'created_at'=>date('Y-m-d H:i:s',strtotime('-2 days')),'make_slug'=>'mitsubishi','model_slug'=>'outlander','seller_name'=>'Coast Motors','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1606611013016-969c19ba27bb?w=600&q=80','drive_type'=>'4wd','engine_cc'=>2400,'color'=>'Silver','price_negotiable'=>1],
//         ['id'=>11,'make_name'=>'Honda','model_name'=>'CR-V','year'=>2015,'price'=>1950000,'mileage'=>91000,'fuel_type'=>'petrol','transmission'=>'automatic','body_type'=>'suv','condition'=>'used','city'=>'Nairobi','slug'=>'honda-cr-v-2015-nairobi','is_featured'=>0,'is_urgent'=>0,'views'=>72,'created_at'=>date('Y-m-d H:i:s',strtotime('-8 days')),'make_slug'=>'honda','model_slug'=>'cr-v','seller_name'=>'Mary N.','seller_type'=>'private_seller','is_verified_seller'=>0,'featured_image'=>'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=600&q=80','drive_type'=>'4wd','engine_cc'=>2000,'color'=>'Brown','price_negotiable'=>1],
//         ['id'=>12,'make_name'=>'Toyota','model_name'=>'Hilux','year'=>2020,'price'=>4100000,'mileage'=>38000,'fuel_type'=>'diesel','transmission'=>'manual','body_type'=>'pickup','condition'=>'locally_used','city'=>'Eldoret','slug'=>'toyota-hilux-2020-eldoret','is_featured'=>0,'is_urgent'=>0,'views'=>198,'created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'make_slug'=>'toyota','model_slug'=>'hilux','seller_name'=>'Farm Equipment KE','seller_type'=>'dealer','is_verified_seller'=>1,'featured_image'=>'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80','drive_type'=>'4wd','engine_cc'=>2800,'color'=>'White','price_negotiable'=>0],
//     ];
//     $totalCars  = count($cars);
//     $totalPages = 1;
// }
// ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= e($metaDesc) ?>">
<title><?= e($pageTitle) ?> | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ============================================================
   VARIABLES & RESET — same as homepage
============================================================ */
:root {
    --black:    #000000;
    --dark:     #0a0a0b;
    --card-bg:  #111114;
    --border:   rgba(255,255,255,0.08);
    --white:    #ffffff;
    --muted:    #a0a0a0;
    --accent:   #e8b84b;
    --accent2:  #ff6b35;
    --green:    #22c55e;
    --red:      #ef4444;
    --blue:     #3b82f6;
    --gradient: linear-gradient(135deg,#e8b84b 0%,#ff6b35 100%);
    --font-head:'Bebas Neue', sans-serif;
    --font-body:'Inter', sans-serif;
    --radius:   14px;
    --radius-lg:24px;
    --shadow:   0 8px 32px rgba(0,0,0,0.5);
    --sidebar-w:280px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
ul{list-style:none}
input,select,button{font-family:var(--font-body)}

.container{max-width:1340px;margin:0 auto;padding:0 20px}

/* ============================================================
   TOPBAR + NAVBAR (reused from homepage)
============================================================ */
.topbar{background:var(--dark);border-bottom:1px solid var(--border);padding:7px 0;font-size:12px;color:var(--muted)}
.topbar .container{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.topbar-left{display:flex;gap:18px}
.topbar-left a,.topbar-right a{display:flex;align-items:center;gap:5px;color:var(--muted);transition:color .2s}
.topbar-left a:hover,.topbar-right a:hover{color:var(--accent)}
.topbar-right{display:flex;gap:14px}

.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:28px}
.logo{font-family:var(--font-head);font-size:24px;font-weight:800;display:flex;align-items:center;flex-shrink:0}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:7px;height:7px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 13px;border-radius:8px;transition:all .2s;white-space:nowrap}
.nav-links a:hover,.nav-links a.active{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .25s;border:none}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.05)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700;box-shadow:0 4px 16px rgba(232,184,75,.25)}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.45)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger span{width:20px;height:2px;background:var(--white);border-radius:2px}

/* ============================================================
   PAGE HEADER
============================================================ */
.page-header{background:var(--dark);border-bottom:1px solid var(--border);padding:20px 0}
.page-header .container{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.breadcrumb a{color:var(--muted);transition:color .2s}
.breadcrumb a:hover{color:var(--accent)}
.breadcrumb i{font-size:9px}
.page-header-right{display:flex;align-items:center;gap:10px}

/* Keyword search bar */
.keyword-search{display:flex;align-items:center;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;overflow:hidden;width:280px;transition:border-color .2s}
.keyword-search:focus-within{border-color:var(--accent)}
.keyword-search input{flex:1;background:none;border:none;outline:none;padding:9px 14px;font-size:13px;color:var(--white)}
.keyword-search input::placeholder{color:var(--muted)}
.keyword-search button{padding:0 14px;height:38px;background:var(--gradient);border:none;cursor:pointer;color:#0a0a0b;font-size:13px;font-weight:600;flex-shrink:0}
.keyword-search button:hover{opacity:.9}

/* ============================================================
   RESULTS META BAR
============================================================ */
.results-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0;flex-wrap:wrap}
.results-count{font-size:14px;color:var(--muted)}
.results-count strong{color:var(--white);font-weight:600}

.sort-row{display:flex;align-items:center;gap:10px}
.sort-label{font-size:12px;color:var(--muted)}
.sort-select{background:var(--card-bg);border:1px solid var(--border);color:var(--white);padding:7px 12px;border-radius:8px;font-size:13px;outline:none;cursor:pointer;-webkit-appearance:none;padding-right:28px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888896' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.sort-select:focus{border-color:var(--accent)}

/* View toggle */
.view-toggle{display:flex;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;overflow:hidden}
.view-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);border:none;background:none;transition:all .2s;font-size:14px}
.view-btn.active,.view-btn:hover{background:rgba(232,184,75,.15);color:var(--accent)}

/* ============================================================
   ACTIVE FILTER CHIPS
============================================================ */
.filter-chips{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px solid var(--border);margin-bottom:16px}
.chip{display:inline-flex;align-items:center;gap:6px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent);font-size:12px;font-weight:500;padding:5px 10px;border-radius:50px;cursor:pointer;transition:all .2s}
.chip:hover{background:rgba(232,184,75,.2)}
.chip i{font-size:10px}
.chip-clear-all{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:var(--red)}
.chip-clear-all:hover{background:rgba(239,68,68,.2)}

/* ============================================================
   MAIN LAYOUT — Sidebar + Results
============================================================ */
.listings-layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;gap:24px;padding:20px 0 60px;align-items:start}

/* ============================================================
   SIDEBAR FILTERS
============================================================ */
.sidebar{position:sticky;top:84px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}

.sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--border)}
.sidebar-header h3{font-family:var(--font-head);font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px}
.sidebar-header h3 i{color:var(--accent);font-size:14px}
.sidebar-reset{font-size:12px;color:var(--muted);cursor:pointer;transition:color .2s;border:none;background:none;padding:0}
.sidebar-reset:hover{color:var(--red)}

.filter-section{border-bottom:1px solid var(--border)}
.filter-section:last-child{border-bottom:none}

.filter-toggle{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none;transition:background .2s}
.filter-toggle:hover{background:rgba(255,255,255,.03)}
.filter-toggle-label{font-size:13px;font-weight:600;color:var(--white)}
.filter-toggle i{font-size:11px;color:var(--muted);transition:transform .25s}
.filter-toggle.open i{transform:rotate(180deg)}
.filter-toggle.open{color:var(--accent)}

.filter-body{padding:4px 20px 16px;display:none}
.filter-body.open{display:block}

/* Filter inputs */
.filter-group{margin-bottom:12px}
.filter-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:6px;display:block}
.filter-select,.filter-input{width:100%;background:rgba(0,0,0,.35);border:1px solid var(--border);color:var(--white);padding:9px 12px;border-radius:8px;font-size:13px;outline:none;transition:border-color .2s;-webkit-appearance:none}
.filter-select:focus,.filter-input:focus{border-color:var(--accent)}
.filter-select{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888896' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:28px}
.filter-select option{background:var(--dark)}
.filter-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}

/* Price range */
.price-range-inputs{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}

/* Checkbox filters */
.filter-check{display:flex;align-items:center;gap:8px;padding:7px 0;cursor:pointer;font-size:13px;color:var(--muted);transition:color .2s}
.filter-check:hover{color:var(--white)}
.filter-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0}
.filter-check input:checked ~ span{color:var(--white)}

/* Apply button */
.filter-apply{display:block;width:calc(100% - 40px);margin:12px 20px 16px;padding:10px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:13px;border:none;border-radius:8px;cursor:pointer;text-align:center;transition:opacity .2s}
.filter-apply:hover{opacity:.9}

/* ============================================================
   CAR CARDS — Grid View
============================================================ */
.cars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}
.cars-list{display:flex;flex-direction:column;gap:14px}

/* GRID CARD */
.car-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;transition:all .3s cubic-bezier(.4,0,.2,1);cursor:pointer;position:relative}
.car-card:hover{transform:translateY(-5px);border-color:rgba(232,184,75,.25);box-shadow:0 16px 40px rgba(0,0,0,.5),0 0 30px rgba(232,184,75,.08)}

.car-img{position:relative;height:186px;overflow:hidden;background:#111}
.car-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease}
.car-card:hover .car-img img{transform:scale(1.06)}

.car-badges{position:absolute;top:10px;left:10px;display:flex;gap:5px;flex-wrap:wrap}
.badge{font-size:10px;font-weight:700;letter-spacing:.05em;padding:4px 8px;border-radius:5px;text-transform:uppercase;line-height:1}
.badge-featured{background:var(--gradient);color:#0a0a0b}
.badge-urgent{background:rgba(239,68,68,.9);color:#fff}
.badge-dealer{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);color:var(--green)}
.badge-private{background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.25);color:var(--accent)}
.badge-verified{background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:var(--blue)}

.save-btn{position:absolute;top:10px;right:10px;width:32px;height:32px;background:rgba(0,0,0,.65);border:1px solid rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;font-size:13px;backdrop-filter:blur(10px);transition:all .25s;z-index:1}
.save-btn:hover,.save-btn.saved{color:#ef4444;background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3)}

.compare-check{position:absolute;bottom:10px;left:10px;display:flex;align-items:center;gap:5px;font-size:11px;color:var(--white);background:rgba(0,0,0,.65);backdrop-filter:blur(8px);padding:5px 9px;border-radius:6px;cursor:pointer}
.compare-check input{accent-color:var(--accent);cursor:pointer}

.car-body{padding:14px}
.car-title{font-family:var(--font-head);font-size:18px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;color:var(--white);letter-spacing:.3px}
.car-sub{font-size:12px;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.car-sub .sep{opacity:.4}

.car-specs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.spec{display:flex;align-items:center;gap:4px;font-size:11px;color:var(--muted)}
.spec i{font-size:10px;color:var(--accent)}

.car-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--border)}
.car-price{font-family:var(--font-head);font-size:18px;font-weight:700;line-height:1}
.car-price-neg{font-size:10px;color:var(--green);margin-top:2px}
.view-btn-sm{display:flex;align-items:center;gap:5px;padding:8px 14px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.2);border-radius:7px;font-size:12px;font-weight:600;color:var(--accent);transition:all .2s}
.view-btn-sm:hover{background:rgba(232,184,75,.2);border-color:var(--accent)}

/* ============================================================
   LIST VIEW CARD
============================================================ */
.car-card-list{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;display:grid;grid-template-columns:260px 1fr;cursor:pointer;transition:all .3s}
.car-card-list:hover{border-color:rgba(232,184,75,.25);transform:translateX(4px);box-shadow:0 8px 30px rgba(0,0,0,.4)}
.list-img{position:relative;height:180px;overflow:hidden;background:#111}
.list-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.car-card-list:hover .list-img img{transform:scale(1.05)}
.list-body{padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between}
.list-title{font-family:var(--font-head);font-size:20px;font-weight:700;margin-bottom:4px;color:var(--white);letter-spacing:.3px}
.list-sub{font-size:13px;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.list-specs{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px}
.list-spec{display:flex;align-items:center;gap:5px;font-size:13px;color:var(--muted)}
.list-spec i{color:var(--accent);font-size:12px}
.list-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.list-price{font-family:var(--font-head);font-size:24px;font-weight:700}
.list-actions{display:flex;gap:8px}
.list-actions a{display:flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;transition:all .2s}
.list-view{background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent)}
.list-view:hover{background:rgba(232,184,75,.2)}
.list-wa{background:rgba(37,211,102,.1);border:1px solid rgba(37,211,102,.25);color:#25D366}
.list-wa:hover{background:rgba(37,211,102,.2)}

/* ============================================================
   EMPTY STATE
============================================================ */
.empty-state{text-align:center;padding:80px 24px;grid-column:1/-1}
.empty-icon{font-size:56px;margin-bottom:20px;opacity:.4}
.empty-title{font-family:var(--font-head);font-size:24px;font-weight:700;margin-bottom:10px}
.empty-desc{color:var(--muted);font-size:15px;max-width:400px;margin:0 auto 28px}

/* ============================================================
   PAGINATION
============================================================ */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:40px 0 20px;flex-wrap:wrap}
.page-btn{min-width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;font-size:13px;font-weight:500;color:var(--muted);cursor:pointer;transition:all .2s;padding:0 10px}
.page-btn:hover{border-color:rgba(232,184,75,.4);color:var(--accent)}
.page-btn.active{background:var(--gradient);border-color:transparent;color:#0a0a0b;font-weight:700}
.page-btn.disabled{opacity:.35;pointer-events:none}
.page-dots{color:var(--muted);padding:0 4px}
/* ============================================================
   RESPONSIVE — TABLET & MOBILE OPTIMIZATION
============================================================ */

/* ========== LARGE TABLET (≤ 1200px) ========== */
@media (max-width: 1200px) {
    :root {
        --sidebar-w: 240px;
    }

    .container {
        padding: 0 16px;
    }
}


/* ========== TABLET (≤ 992px) ========== */
@media (max-width: 992px) {

    /* Layout becomes single column */
    .listings-layout {
        grid-template-columns: 1fr;
    }

    /* Sidebar becomes collapsible */
    .sidebar {
        position: relative;
        top: 0;
        width: 100%;
    }

    /* Navbar adjustments */
    .nav-links {
        display: none;
        position: absolute;
        top: 64px;
        left: 0;
        width: 100%;
        background: var(--dark);
        flex-direction: column;
        padding: 20px;
        gap: 10px;
        border-bottom: 1px solid var(--border);
    }

    .nav-links.active {
        display: flex;
    }

    .hamburger {
        display: flex;
    }

    .keyword-search {
        width: 100%;
    }

    .page-header .container {
        flex-direction: column;
        align-items: flex-start;
    }
}


/* ========== MOBILE (≤ 768px) ========== */
@media (max-width: 768px) {

    body {
        font-size: 14px;
    }

    .results-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }

    .sort-row {
        width: 100%;
        justify-content: space-between;
    }

    .keyword-search {
        width: 100%;
    }

    .sort-select {
        width: 100%;
    }

    .view-toggle {
        width: 100%;
        justify-content: space-between;
    }

    .page-header {
        padding: 16px 0;
    }

    .breadcrumb {
        flex-wrap: wrap;
    }

    .sidebar-header {
        padding: 14px 16px;
    }

    .filter-toggle {
        padding: 12px 16px;
    }
}


/* ========== SMALL MOBILE (≤ 480px) ========== */
@media (max-width: 480px) {

    .container {
        padding: 0 12px;
    }

    .btn {
        padding: 8px 14px;
        font-size: 12px;
    }

    .logo {
        font-size: 20px;
    }

    .keyword-search input {
        font-size: 12px;
    }

    .chip {
        font-size: 11px;
        padding: 4px 8px;
    }
}
/* ============================================================
   COMPARE TRAY (Fixed Bottom)
============================================================ */
.compare-tray{position:fixed;bottom:0;left:0;right:0;z-index:500;background:rgba(17,17,20,.97);backdrop-filter:blur(20px);border-top:1px solid rgba(232,184,75,.25);padding:12px 20px;display:none;align-items:center;justify-content:space-between;gap:16px;transform:translateY(100%);transition:transform .3s ease}
.compare-tray.show{display:flex;transform:translateY(0)}
.compare-tray-cars{display:flex;gap:10px;align-items:center;flex:1;overflow:hidden}
.compare-tray-item{display:flex;align-items:center;gap:8px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;white-space:nowrap;font-weight:500}
.compare-tray-item .remove-compare{color:var(--muted);cursor:pointer;font-size:11px;margin-left:4px;transition:color .2s}
.compare-tray-item .remove-compare:hover{color:var(--red)}
.compare-tray-empty{font-size:13px;color:var(--muted);font-style:italic}
.compare-actions{display:flex;gap:8px;flex-shrink:0}

/* ============================================================
   MOBILE FILTER DRAWER
============================================================ */
.filter-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:300;display:none;backdrop-filter:blur(4px)}
.filter-overlay.show{display:block}
.filter-drawer{position:fixed;left:0;top:0;bottom:0;width:min(320px,92vw);background:var(--dark);z-index:301;overflow-y:auto;transform:translateX(-100%);transition:transform .3s ease;border-right:1px solid var(--border)}
.filter-drawer.open{transform:translateX(0)}
.drawer-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--dark);z-index:1}
.drawer-close{background:none;border:none;color:var(--white);font-size:20px;cursor:pointer;padding:4px}

/* ============================================================
   SAVE SEARCH ALERT WIDGET
============================================================ */
.save-search-bar{background:linear-gradient(135deg,rgba(232,184,75,.08),rgba(255,107,53,.05));border:1px solid rgba(232,184,75,.2);border-radius:var(--radius);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.save-search-text{font-size:13px;color:var(--muted)}
.save-search-text strong{color:var(--accent)}
.save-search-btn{display:flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.3);border-radius:8px;font-size:12px;font-weight:600;color:var(--accent);cursor:pointer;transition:all .2s;white-space:nowrap}
.save-search-btn:hover{background:rgba(232,184,75,.2)}

/* Mobile CTA */
.mobile-cta{display:none;position:fixed;bottom:0;left:0;right:0;z-index:400;background:rgba(17,17,20,.96);backdrop-filter:blur(16px);border-top:1px solid var(--border);padding:10px 16px;gap:10px}
.mobile-cta .btn{flex:1;justify-content:center;font-size:13px}
.mobile-filter-btn{display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--card-bg);border:1px solid var(--border);border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;color:var(--white)}
.mobile-filter-btn:hover{border-color:rgba(232,184,75,.4);color:var(--accent)}
.filter-count{background:var(--gradient);color:#0a0a0b;font-size:10px;font-weight:700;padding:2px 6px;border-radius:50px;margin-left:2px}

/* ============================================================
   RESPONSIVE
============================================================ */
@media(max-width:1024px){
    .listings-layout{grid-template-columns:1fr}
    .sidebar{display:none}
    .topbar{display:none}
    .nav-links{display:none}
    .hamburger{display:flex}
    .mobile-cta{display:flex}
}
@media(max-width:640px){
    .cars-grid{grid-template-columns:1fr}
    .car-card-list{grid-template-columns:1fr}
    .list-img{height:200px}
    .keyword-search{width:200px}
    .page-header .container{flex-direction:column;align-items:flex-start}
}

/* User nav dropdown */
.user-nav-wrap:hover .user-dropdown{opacity:1!important;visibility:visible!important;transform:translateY(0)!important}
/* Saved heart pre-state */
.save-btn.saved{color:#ef4444!important;background:rgba(239,68,68,.15)!important;border-color:rgba(239,68,68,.3)!important}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="container">
        <div class="topbar-left">
            <a href="tel:<?= setting('site_phone','+254700000000') ?>"><i class="fas fa-phone"></i> <?= setting('site_phone','+254 700 000 000') ?></a>
            <a href="mailto:<?= setting('site_email','info@carsoko.co.ke') ?>"><i class="fas fa-envelope"></i> <?= setting('site_email','info@carsoko.co.ke') ?></a>
        </div>
        <div class="topbar-right">
            <a href="listings.php?type=new"><i class="fas fa-star"></i> New Cars</a>
            <a href="dealer-register.php"><i class="fas fa-store"></i> Become a Dealer</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo"><span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div></a>
        <div class="nav-links" id="mobileNav">
            <a href="listings.php" class="active">Browse Cars</a>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="listings.php?seller=dealer">Dealers</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="blog.php">Blog</a>
        </div>
        <div class="nav-right">
            <?php if (Auth::check()):
                $__navUser = Auth::user(); ?>
            <div style="position:relative;display:flex;align-items:center;gap:8px;cursor:pointer" class="user-nav-wrap">
                <div style="width:34px;height:34px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:13px;font-weight:700;color:#0a0a0b;flex-shrink:0"><?= strtoupper(substr($__navUser['name'],0,1)) ?></div>
                <span style="font-size:13px;font-weight:600;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(explode(' ',$__navUser['name'])[0]) ?></span>
                <i class="fas fa-chevron-down" style="font-size:10px;color:var(--muted)"></i>
                <div style="position:absolute;top:calc(100% + 10px);right:0;background:var(--dark);border:1px solid var(--border);border-radius:12px;min-width:180px;padding:8px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:all .2s;z-index:300" class="user-dropdown">
                    <a href="dashboard.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--muted);transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-tachometer-alt" style="width:14px;color:var(--accent)"></i> Dashboard</a>
                    <a href="messages.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--muted);transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-comment-dots" style="width:14px;color:var(--accent)"></i> Messages</a>
                    <a href="profile.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--muted);transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-user-circle" style="width:14px;color:var(--accent)"></i> Profile</a>
                    <?php if (Auth::isModerator()): ?>
                    <a href="admin.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--muted);transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-shield-halved" style="width:14px;color:var(--accent)"></i> Admin Panel</a>
                    <?php endif; ?>
                    <hr style="border:none;border-top:1px solid var(--border);margin:4px 0">
                    <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--red);transition:all .2s" onmouseover="this.style.background='rgba(239,68,68,.08)'" onmouseout="this.style.background=''"><i class="fas fa-sign-out-alt" style="width:14px"></i> Sign Out</a>
                </div>
            </div>
            <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <a href="register.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <?php endif; ?>
            <div class="hamburger" onclick="document.getElementById('mobileNav').classList.toggle('active')"><span></span><span></span><span></span></div>
        </div>
    </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container">
        <div>
            <div class="breadcrumb">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <span>Cars for Sale</span>
                <?php if ($f['make']): ?>
                    <i class="fas fa-chevron-right"></i>
                    <a href="listings.php?make=<?= e($f['make']) ?>"><?= e(ucfirst($f['make'])) ?></a>
                <?php endif; ?>
                <?php if ($f['model']): ?>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= e(ucfirst($f['model'])) ?></span>
                <?php endif; ?>
            </div>
            <h1 style="font-family:var(--font-head);font-size:clamp(18px,3vw,26px);font-weight:700;margin-top:6px">
                <?= e($pageTitle) ?>
                <span style="font-size:14px;font-weight:400;color:var(--muted);margin-left:8px">(<?= number_format($totalCars) ?> results)</span>
            </h1>
        </div>
        <div class="page-header-right">
            <!-- Keyword search -->
            <form class="keyword-search" method="GET" action="listings.php">
                <?php foreach ($f as $k => $v): ?>
                    <?php if ($k !== 'q' && $k !== 'page' && $v): ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="text" name="q" placeholder="Search make, model…" value="<?= e($f['q']) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <!-- Mobile filter trigger -->
            <button class="mobile-filter-btn" onclick="openFilterDrawer()" style="display:none" id="mobileFilterBtn">
                <i class="fas fa-sliders-h"></i> Filters
                <?php if (count($activeFilters)): ?><span class="filter-count"><?= count($activeFilters) ?></span><?php endif; ?>
            </button>
        </div>
    </div>
</div>

<!-- MAIN -->
<div class="container">

    <!-- RESULTS META -->
    <div class="results-meta">
        <div class="results-count">
            Showing <strong><?= number_format(min($offset + 1, $totalCars)) ?>–<?= number_format(min($offset + $f['per_page'], $totalCars)) ?></strong>
            of <strong><?= number_format($totalCars) ?></strong> cars
        </div>
        <div class="sort-row">
            <span class="sort-label">Sort:</span>
            <select class="sort-select" onchange="applySort(this.value)">
                <option value="newest"    <?= $f['sort']==='newest'    ?'selected':''?>>Newest First</option>
                <option value="price_asc" <?= $f['sort']==='price_asc' ?'selected':''?>>Price: Low → High</option>
                <option value="price_desc"<?= $f['sort']==='price_desc'?'selected':''?>>Price: High → Low</option>
                <option value="year_desc" <?= $f['sort']==='year_desc' ?'selected':''?>>Year: Newest</option>
                <option value="mileage_asc"<?=$f['sort']==='mileage_asc'?'selected':''?>>Lowest Mileage</option>
                <option value="popular"  <?= $f['sort']==='popular'   ?'selected':''?>>Most Popular</option>
            </select>

            <div class="view-toggle">
                <button class="view-btn <?= $f['view']==='grid'?'active':''?>" onclick="setView('grid')" title="Grid view"><i class="fas fa-th-large"></i></button>
                <button class="view-btn <?= $f['view']==='list'?'active':''?>" onclick="setView('list')" title="List view"><i class="fas fa-list"></i></button>
            </div>
        </div>
    </div>

    <!-- ACTIVE FILTER CHIPS -->
    <?php if ($activeFilters): ?>
    <div class="filter-chips">
        <span style="font-size:12px;color:var(--muted);margin-right:4px"><i class="fas fa-filter"></i> Active:</span>
        <?php foreach ($activeFilters as $chip): ?>
        <a href="<?= removeFilter($chip['remove']) ?>" class="chip">
            <?= e($chip['label']) ?> <i class="fas fa-times"></i>
        </a>
        <?php endforeach; ?>
        <a href="listings.php" class="chip chip-clear-all">
            <i class="fas fa-trash"></i> Clear All
        </a>
    </div>
    <?php endif; ?>



    <!-- LAYOUT: SIDEBAR + RESULTS -->
    <div class="listings-layout">

        <!-- ==============================
             SIDEBAR FILTERS
        ============================== -->
        <aside class="sidebar" id="filterSidebar">
            <?php $this_is_drawer = false; include_once 'includes/filter-form.php'; ?>
        </aside>

        <!-- ==============================
             RESULTS GRID / LIST
        ============================== -->
        <main>
            <?php if (empty($cars)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <div class="empty-title">No Cars Found</div>
                <div class="empty-desc">No cars match your current filters. Try removing some filters or broadening your search.</div>
                <a href="listings.php" class="btn btn-accent"><i class="fas fa-times"></i> Clear All Filters</a>
            </div>

            <?php elseif ($f['view'] === 'list'): ?>
            <!-- LIST VIEW -->
            <div class="cars-list" id="carResults">
                <?php foreach ($cars as $i => $car):
                    $imgUrl  = !empty($car['featured_image']) ? carImageUrl($car['featured_image']) : BASE_URL . '/assets/img/placeholder.jpg';
                    $carUrl  = 'listing.php?id=' . $car['id'];
                ?>
                <div class="car-card-list" style="animation-delay:<?= $i * 0.05 ?>s" onclick="window.location='<?= $carUrl ?>'">
                    <div class="list-img">
                        <img src="<?= e($imgUrl) ?>" alt="<?= e($car['make_name'].' '.$car['model_name']) ?>" loading="lazy">
                        <div style="position:absolute;top:10px;left:10px;display:flex;gap:5px">
                            <?php if ($car['is_featured']): ?><span class="badge badge-featured"><i class="fas fa-bolt"></i> Featured</span><?php endif; ?>
                            <?php if ($car['is_urgent']):   ?><span class="badge badge-urgent"><i class="fas fa-fire"></i> Urgent</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="list-body">
                        <div>
                            <div class="list-title"><?= e($car['year'].' '.$car['make_name'].' '.$car['model_name']) ?></div>
                            <div class="list-sub">
                                <span><i class="fas fa-map-marker-alt" style="color:var(--accent);font-size:11px"></i> <?= e($car['city']) ?></span>
                                <span class="badge <?= $car['seller_type']==='dealer'?'badge-dealer':'badge-private' ?>"><?= $car['seller_type']==='dealer'?'Dealer':'Private' ?></span>
                                <?php if ($car['is_verified_seller']): ?>
                                <span class="badge badge-verified"><i class="fas fa-check"></i> Verified</span>
                                <?php endif; ?>
                                <span style="color:var(--muted);font-size:11px"><i class="fas fa-eye"></i> <?= number_format($car['views']) ?> views</span>
                                <span style="color:var(--muted);font-size:11px"><?= timeAgo($car['created_at']) ?></span>
                            </div>
                            <div class="list-specs">
                                <span class="list-spec"><i class="fas fa-tachometer-alt"></i> <?= number_format($car['mileage']) ?> km</span>
                                <span class="list-spec"><i class="fas fa-gas-pump"></i> <?= ucfirst($car['fuel_type']) ?></span>
                                <span class="list-spec"><i class="fas fa-cog"></i> <?= ucfirst($car['transmission']) ?></span>
                                <span class="list-spec"><i class="fas fa-car"></i> <?= ucfirst($car['body_type']) ?></span>
                                <?php if ($car['engine_cc']): ?><span class="list-spec"><i class="fas fa-engine"></i> <?= $car['engine_cc'] ?>cc</span><?php endif; ?>
                                <?php if ($car['color']): ?><span class="list-spec"><i class="fas fa-palette"></i> <?= e($car['color']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="list-footer">
                            <div>
                                <div class="list-price"><?= formatPKR($car['price']) ?></div>
                                <?php if ($car['price_negotiable']): ?><div style="font-size:11px;color:var(--green);margin-top:2px"><i class="fas fa-handshake"></i> Negotiable</div><?php endif; ?>
                            </div>
                            <div class="list-actions" onclick="event.stopPropagation()">
                                <a href="<?= $carUrl ?>" class="list-view"><i class="fas fa-eye"></i> View Details</a>
                                <a href="https://wa.me/?text=I'm+interested+in+<?= urlencode($car['make_name'].' '.$car['model_name'].' '.formatPKR($car['price'])) ?>" target="_blank" class="list-wa"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- GRID VIEW -->
            <div class="cars-grid" id="carResults">
                <?php foreach ($cars as $i => $car):
                    $imgUrl = !empty($car['featured_image']) ? carImageUrl($car['featured_image']) : BASE_URL . '/assets/img/placeholder.jpg';
                    $carUrl = 'listing.php?id=' . $car['id'];
                ?>
                <div class="car-card" style="animation-delay:<?= $i * 0.04 ?>s" onclick="window.location='<?= $carUrl ?>'">
                    <div class="car-img">
                        <img src="<?= e($imgUrl) ?>" alt="<?= e($car['make_name'].' '.$car['model_name']) ?>" loading="lazy">
                        <div class="car-badges">
                            <?php if ($car['is_featured']): ?><span class="badge badge-featured"><i class="fas fa-bolt"></i> Featured</span><?php endif; ?>
                            <?php if ($car['is_urgent']):   ?><span class="badge badge-urgent"><i class="fas fa-fire"></i> Urgent</span><?php endif; ?>
                        </div>
                        <label class="compare-check" onclick="event.stopPropagation()">
                            <input type="checkbox" value="<?= $car['id'] ?>" data-name="<?= e($car['year'].' '.$car['make_name'].' '.$car['model_name']) ?>" onchange="toggleCompare(this)"> Compare
                        </label>
                    </div>
                    <div class="car-body">
                        <div class="car-title"><?= e($car['make_name'].' '.$car['model_name']) ?></div>
                        <div class="car-sub">
                            <span><?= e($car['year']) ?></span><span class="sep">·</span>
                            <span><i class="fas fa-map-marker-alt" style="color:var(--accent);font-size:10px"></i> <?= e($car['city']) ?></span>
                            <span class="sep">·</span>
                            <span class="badge <?= $car['seller_type']==='dealer'?'badge-dealer':'badge-private' ?>" style="font-size:9px"><?= $car['seller_type']==='dealer'?'Dealer':'Private' ?></span>
                            <?php if ($car['is_verified_seller']): ?>
                            <span class="badge badge-verified" style="font-size:9px"><i class="fas fa-check"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="car-specs">
                            <span class="spec"><i class="fas fa-tachometer-alt"></i> <?= number_format($car['mileage']) ?> km</span>
                            <span class="spec"><i class="fas fa-gas-pump"></i> <?= ucfirst($car['fuel_type']) ?></span>
                            <span class="spec"><i class="fas fa-cog"></i> <?= ucfirst($car['transmission']) ?></span>
                            <span class="spec"><i class="fas fa-car"></i> <?= ucfirst($car['body_type']) ?></span>
                        </div>
                        <div class="car-footer">
                            <div>
                                <div class="car-price"><?= formatPKR($car['price']) ?></div>
                                <?php if ($car['price_negotiable']): ?><div class="car-price-neg"><i class="fas fa-handshake"></i> Negotiable</div><?php endif; ?>
                            </div>
                            <a href="<?= $carUrl ?>" class="view-btn-sm" onclick="event.stopPropagation()"><i class="fas fa-eye"></i> View</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $prevPage = $f['page'] - 1;
                $nextPage = $f['page'] + 1;
                ?>
                <!-- Prev -->
                <?php if ($f['page'] > 1): ?>
                <a href="<?= filterUrl(['page' => $prevPage]) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                // Smart page number generation
                $range = 2;
                $pages = [];
                for ($p = 1; $p <= $totalPages; $p++) {
                    if ($p === 1 || $p === $totalPages || abs($p - $f['page']) <= $range) {
                        $pages[] = $p;
                    }
                }
                $prev = null;
                foreach ($pages as $p):
                    if ($prev !== null && $p - $prev > 1): ?>
                        <span class="page-dots">…</span>
                    <?php endif; ?>
                    <a href="<?= filterUrl(['page' => $p]) ?>"
                       class="page-btn <?= $p === $f['page'] ? 'active' : '' ?>"><?= $p ?></a>
                <?php $prev = $p; endforeach; ?>

                <!-- Next -->
                <?php if ($f['page'] < $totalPages): ?>
                <a href="<?= filterUrl(['page' => $nextPage]) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>

                <span style="font-size:12px;color:var(--muted);margin-left:8px">Page <?= $f['page'] ?> of <?= $totalPages ?></span>
            </div>
            <?php endif; ?>

        </main>
    </div><!-- /.listings-layout -->
</div><!-- /.container -->

<!-- MOBILE FILTER DRAWER OVERLAY -->
<div class="filter-overlay" id="filterOverlay" onclick="closeFilterDrawer()"></div>
<div class="filter-drawer" id="filterDrawer">
    <div class="drawer-header">
        <h3 style="font-family:var(--font-head);font-weight:700;font-size:16px"><i class="fas fa-sliders-h" style="color:var(--accent)"></i> Filters</h3>
        <button class="drawer-close" onclick="closeFilterDrawer()"><i class="fas fa-times"></i></button>
    </div>
    <?php include_once 'includes/filter-form.php'; ?>
</div>

<!-- COMPARE TRAY -->
<div class="compare-tray" id="compareTray">
    <div style="font-size:13px;font-weight:600;flex-shrink:0"><i class="fas fa-balance-scale" style="color:var(--accent)"></i> Compare</div>
    <div class="compare-tray-cars" id="compareCars">
        <span class="compare-tray-empty">Select 2–3 cars using the checkbox on cards</span>
    </div>
    <div class="compare-actions">
        <button onclick="clearCompare()" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red);padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-body)">Clear</button>
        <a href="#" id="compareLink" class="btn btn-accent" style="pointer-events:none;opacity:.5"><i class="fas fa-columns"></i> Compare Now</a>
    </div>
</div>

<!-- MOBILE BOTTOM CTA -->
<div class="mobile-cta">
    <button class="mobile-filter-btn" onclick="openFilterDrawer()">
        <i class="fas fa-sliders-h"></i> Filters
        <?php if (count($activeFilters)): ?><span class="filter-count"><?= count($activeFilters) ?></span><?php endif; ?>
    </button>
    <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
</div>

<!-- INLINE FILTER FORM (used by both sidebar and drawer via PHP include) -->
<!-- Since include_once won't work in this demo, we embed the form directly -->
<script>
// Inject filter form into both sidebar and drawer
const filterFormHTML = `
<form method="GET" action="listings.php" id="filterForm">
    <!-- Hidden fields to preserve non-filter params -->
    <input type="hidden" name="sort"  value="<?= e($f['sort']) ?>">
    <input type="hidden" name="view"  value="<?= e($f['view']) ?>">
    <input type="hidden" name="q"     value="<?= e($f['q']) ?>">

    <!-- MAKE & MODEL -->
    <div class="filter-section">
        <div class="filter-toggle open" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Make & Model</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body open">
            <div class="filter-group">
                <label class="filter-label">Make</label>
                <select name="make" class="filter-select" id="makeSelect" onchange="updateModels(this.value)">
                    <option value="">Any Make</option>
                    <?php foreach ($makes as $mk): ?>
                    <option value="<?= e($mk['slug']) ?>" <?= $f['make']===$mk['slug']?'selected':'' ?>><?= e($mk['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Model</label>
                <select name="model" class="filter-select" id="modelSelect">
                    <option value="">Any Model</option>
                    <?php foreach ($models as $mo): ?>
                    <option value="<?= e($mo['slug']) ?>" <?= $f['model']===$mo['slug']?'selected':'' ?>><?= e($mo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- PRICE -->
    <div class="filter-section">
        <div class="filter-toggle open" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Price (Rs.)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body open">
            <div class="price-range-inputs">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Min</label>
                    <input type="number" name="min_price" class="filter-input" placeholder="0" value="<?= $f['min_price']?:'' ?>" min="0" step="50000">
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Max</label>
                    <input type="number" name="max_price" class="filter-input" placeholder="Any" value="<?= $f['max_price']?:'' ?>" min="0" step="50000">
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:5px;margin-top:6px">
                <?php
                $pricePresets = [1000000=>'Under 1M', 2000000=>'Under 2M', 3000000=>'Under 3M', 5000000=>'Under 5M', 10000000=>'Under 10M'];
                foreach ($pricePresets as $val => $label): ?>
                <label class="filter-check">
                    <input type="radio" name="max_price" value="<?= $val ?>" <?= $f['max_price']==$val?'checked':'' ?> onchange="this.form.submit()">
                    <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- YEAR -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Year</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-row">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">From</label>
                    <select name="min_year" class="filter-select">
                        <option value="">Any</option>
                        <?php for ($y = $curYear; $y >= 1995; $y--): ?>
                        <option value="<?= $y ?>" <?= $f['min_year']==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">To</label>
                    <select name="max_year" class="filter-select">
                        <option value="">Any</option>
                        <?php for ($y = $curYear; $y >= 1995; $y--): ?>
                        <option value="<?= $y ?>" <?= $f['max_year']==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- FUEL & TRANSMISSION -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Fuel & Gearbox</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">Fuel Type</label>
                <select name="fuel_type" class="filter-select">
                    <option value="">Any</option>
                    <option value="petrol"  <?= $f['fuel_type']==='petrol'?'selected':''?>>Petrol</option>
                    <option value="diesel"  <?= $f['fuel_type']==='diesel'?'selected':''?>>Diesel</option>
                    <option value="hybrid"  <?= $f['fuel_type']==='hybrid'?'selected':''?>>Hybrid</option>
                    <option value="electric"<?= $f['fuel_type']==='electric'?'selected':''?>>Electric</option>
                    <option value="lpg"     <?= $f['fuel_type']==='lpg'?'selected':''?>>LPG</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Transmission</label>
                <select name="transmission" class="filter-select">
                    <option value="">Any</option>
                    <option value="automatic"    <?= $f['transmission']==='automatic'?'selected':''?>>Automatic</option>
                    <option value="manual"       <?= $f['transmission']==='manual'?'selected':''?>>Manual</option>
                    <option value="cvt"          <?= $f['transmission']==='cvt'?'selected':''?>>CVT</option>
                    <option value="semi_automatic"<?=$f['transmission']==='semi_automatic'?'selected':''?>>Semi-Automatic</option>
                </select>
            </div>
        </div>
    </div>

    <!-- BODY TYPE -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Body Type</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <?php $bodies = ['sedan'=>'🚗 Sedan','hatchback'=>'🚙 Hatchback','suv'=>'🛻 SUV','pickup'=>'🚚 Pickup','van'=>'🚐 Van','wagon'=>'🚌 Wagon','coupe'=>'🏎️ Coupe','minibus'=>'🚌 Minibus']; ?>
            <div style="display:flex;flex-direction:column;gap:4px">
                <?php foreach ($bodies as $val => $label): ?>
                <label class="filter-check">
                    <input type="radio" name="body_type" value="<?= $val ?>" <?= $f['body_type']===$val?'checked':'' ?> onchange="this.form.submit()">
                    <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- LOCATION -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Location</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">City</label>
                <select name="city" class="filter-select">
                    <option value="">All Kenya</option>
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c['city']) ?>" <?= $f['city']===$c['city']?'selected':'' ?>><?= e($c['city']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- CONDITION & SELLER -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Condition & Seller</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-group">
                <label class="filter-label">Condition</label>
                <select name="condition" class="filter-select">
                    <option value="">Any</option>
                    <option value="new"          <?= $f['condition']==='new'?'selected':''?>>Brand New</option>
                    <option value="foreign_used" <?= $f['condition']==='foreign_used'?'selected':''?>>Foreign Used</option>
                    <option value="locally_used" <?= $f['condition']==='locally_used'?'selected':''?>>Locally Used</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Seller Type</label>
                <select name="seller" class="filter-select">
                    <option value="">Any</option>
                    <option value="dealer"  <?= $f['seller']==='dealer'?'selected':''?>>Dealers Only</option>
                    <option value="private" <?= $f['seller']==='private'?'selected':''?>>Private Sellers</option>
                </select>
            </div>
            <label class="filter-check" style="margin-top:6px">
                <input type="checkbox" name="verified" value="1" <?= $f['verified']?'checked':'' ?> onchange="this.form.submit()">
                <span><i class="fas fa-check-circle" style="color:var(--blue)"></i> Verified Sellers Only</span>
            </label>
        </div>
    </div>

    <!-- MILEAGE -->
    <div class="filter-section">
        <div class="filter-toggle" onclick="toggleSection(this)">
            <span class="filter-toggle-label">Mileage (km)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="filter-body">
            <div class="filter-row">
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Min km</label>
                    <input type="number" name="min_mileage" class="filter-input" placeholder="0" value="<?= $f['min_mileage']?:'' ?>" min="0" step="10000">
                </div>
                <div class="filter-group" style="margin:0">
                    <label class="filter-label">Max km</label>
                    <input type="number" name="max_mileage" class="filter-input" placeholder="Any" value="<?= $f['max_mileage']?:'' ?>" min="0" step="10000">
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="filter-apply"><i class="fas fa-search"></i> Apply Filters</button>
</form>`;

// Inject into sidebar
document.getElementById('filterSidebar').innerHTML =
    `<div class="sidebar-header">
        <h3><i class="fas fa-sliders-h"></i> Filters</h3>
        <button class="sidebar-reset" onclick="window.location='listings.php'">Reset All</button>
    </div>` + filterFormHTML;

// Inject into drawer
document.getElementById('filterDrawer').insertAdjacentHTML('beforeend', filterFormHTML);
</script>

<style>
/* ── INSTANT LOADING OVERLAY ── */
#loadingBar{position:fixed;top:0;left:0;width:0;height:3px;background:var(--accent,#f97316);z-index:99999;transition:width .15s ease;border-radius:0 2px 2px 0}
#loadingBar.going{width:70%}
#loadingBar.done{width:100%;opacity:0;transition:width .1s,opacity .3s .1s}
.results-fading{opacity:.35;pointer-events:none;transition:opacity .15s}
</style>
<script>
// ============================================================
// INSTANT LOADING FEEDBACK
// ============================================================
const bar = document.createElement('div');
bar.id = 'loadingBar';
document.body.appendChild(bar);

function showLoading() {
    bar.className = 'going';
    const results = document.getElementById('carResults');
    if (results) results.classList.add('results-fading');
}

// Trigger loading bar on any filter form submit
document.addEventListener('submit', function(e) {
    if (e.target.id === 'filterForm') showLoading();
});
// Also on any direct window.location navigations
const _origLoc = Object.getOwnPropertyDescriptor(window, 'location');

// Fade in when page loads (came from a filter)
window.addEventListener('pageshow', function() {
    bar.className = 'done';
    setTimeout(() => bar.className = '', 400);
});

// ============================================================
// SORT & VIEW TOGGLE
// ============================================================
function applySort(val) {
    showLoading();
    const url = new URL(window.location);
    url.searchParams.set('sort', val);
    url.searchParams.set('page', 1);
    window.location = url;
}

function setView(mode) {
    showLoading();
    const url = new URL(window.location);
    url.searchParams.set('view', mode);
    url.searchParams.set('page', 1);
    window.location = url;
}

// ============================================================
// FILTER SECTION ACCORDION
// ============================================================
function toggleSection(el) {
    el.classList.toggle('open');
    const body = el.nextElementSibling;
    body.classList.toggle('open');
}

// ============================================================
// MOBILE FILTER DRAWER
// ============================================================
function openFilterDrawer() {
    document.getElementById('filterDrawer').classList.add('open');
    document.getElementById('filterOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawer').classList.remove('open');
    document.getElementById('filterOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
// Show mobile filter button
if (window.innerWidth <= 1024) {
    const btn = document.getElementById('mobileFilterBtn');
    if (btn) btn.style.display = 'flex';
}

// ============================================================
// DYNAMIC MODEL LOADER (AJAX via PHP endpoint)
// ============================================================
function updateModels(makeSlug) {
    const allModelSelects = document.querySelectorAll('#modelSelect');
    allModelSelects.forEach(sel => {
        sel.innerHTML = '<option value="">Loading...</option>';
    });
    if (!makeSlug) {
        allModelSelects.forEach(sel => { sel.innerHTML = '<option value="">Any Model</option>'; });
        return;
    }
    fetch('ajax/get-models.php?make=' + encodeURIComponent(makeSlug))
        .then(r => r.json())
        .then(data => {
            allModelSelects.forEach(sel => {
                sel.innerHTML = '<option value="">Any Model</option>';
                data.forEach(m => {
                    sel.innerHTML += `<option value="${m.slug}">${m.name}</option>`;
                });
            });
        })
        .catch(() => {
            allModelSelects.forEach(sel => { sel.innerHTML = '<option value="">Any Model</option>'; });
        });
}

// ============================================================
// SAVE BUTTON (Wishlist) — saves to saved_cars table
// ============================================================
function toggleSave(btn, carId) {
    <?php if (!Auth::check()): ?>
    // Guest: redirect to login
    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
    <?php endif; ?>

    const saving = !btn.classList.contains('saved');

    // Optimistic UI update
    btn.classList.toggle('saved', saving);
    btn.querySelector('i').className = saving ? 'fas fa-heart' : 'far fa-heart';

    // Show a quick toast
    const toast = document.getElementById('saveToast');
    if (toast) {
        toast.textContent = saving ? '❤️ Saved to your list' : 'Removed from saved';
        toast.style.opacity = '1'; toast.style.transform = 'translateY(0)';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { toast.style.opacity='0'; toast.style.transform='translateY(8px)'; }, 2500);
    }

    fetch('ajax/save-car.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'car_id=' + carId + '&action=' + (saving ? 'save' : 'unsave') + '&csrf=<?= CSRF::token() ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            // Revert on failure
            btn.classList.toggle('saved', !saving);
            btn.querySelector('i').className = saving ? 'far fa-heart' : 'fas fa-heart';
            if (toast) { toast.textContent = d.message || 'Could not save'; }
        }
    })
    .catch(() => {
        btn.classList.toggle('saved', !saving);
        btn.querySelector('i').className = saving ? 'far fa-heart' : 'fas fa-heart';
    });
}

// ============================================================
// COMPARE TOOL
// ============================================================
const compareItems = {};

function toggleCompare(checkbox) {
    const id   = checkbox.value;
    const name = checkbox.dataset.name;
    if (checkbox.checked) {
        if (Object.keys(compareItems).length >= 3) {
            checkbox.checked = false;
            alert('You can compare up to 3 cars at a time.');
            return;
        }
        compareItems[id] = name;
    } else {
        delete compareItems[id];
    }
    renderCompareTray();
}

function renderCompareTray() {
    const tray   = document.getElementById('compareTray');
    const cars   = document.getElementById('compareCars');
    const link   = document.getElementById('compareLink');
    const count  = Object.keys(compareItems).length;

    if (count === 0) {
        tray.classList.remove('show');
        return;
    }
    tray.classList.add('show');
    cars.innerHTML = '';
    for (const [id, name] of Object.entries(compareItems)) {
        cars.innerHTML += `<div class="compare-tray-item">${name} <span class="remove-compare" onclick="removeCompare('${id}')"><i class="fas fa-times"></i></span></div>`;
    }
    if (count >= 2) {
        link.href = 'compare.php?ids=' + Object.keys(compareItems).join(',');
        link.style.pointerEvents = '';
        link.style.opacity = '1';
    } else {
        link.style.pointerEvents = 'none';
        link.style.opacity = '.5';
    }
}

function removeCompare(id) {
    delete compareItems[id];
    const cb = document.querySelector(`input[value="${id}"][type=checkbox]`);
    if (cb) cb.checked = false;
    renderCompareTray();
}

function clearCompare() {
    Object.keys(compareItems).forEach(id => removeCompare(id));
}

// Save search removed
</script>




</body>
</html>