<?php
require_once 'connection.php';

// --- FETCH FEATURED LISTINGS ---
function getFeaturedCars($limit = 8) {
    $limit = (int)$limit;
    $cars = DB::select("
        SELECT c.*, u.name as seller_name, u.role as seller_type,
               m.name as make_name,
               (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name,
               (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS image_path
        FROM cars c
        LEFT JOIN users u ON u.id = c.user_id
        LEFT JOIN makes m ON m.id = c.make_id
        WHERE c.status = 'active' AND c.is_featured = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC LIMIT $limit
    ");
    return $cars ?: [];
}

// --- FETCH RECENT LISTINGS ---
function getRecentCars($limit = 8) {
    $limit = (int)$limit;
    $cars = DB::select("
        SELECT c.*, u.name as seller_name, u.role as seller_type,
               m.name as make_name,
               (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name,
               (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS image_path
        FROM cars c
        LEFT JOIN users u ON u.id = c.user_id
        LEFT JOIN makes m ON m.id = c.make_id
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.created_at DESC LIMIT $limit
    ");
    return $cars ?: [];
}

// --- FETCH STATS ---
function getSiteStats() {
    try {
        return [
            'total_cars' => DB::value("SELECT COUNT(*) FROM cars WHERE status='active'") ?: 0,
            'dealers'    => DB::value("SELECT COUNT(*) FROM users WHERE role='dealer'")  ?: 0,
            'sold'       => DB::value("SELECT COUNT(*) FROM cars WHERE status='sold'")   ?: 0,
            'cities'     => DB::value("SELECT COUNT(DISTINCT city) FROM cars")           ?: 0,
        ];
    } catch (Exception $e) {
        return ['total_cars' => 0, 'dealers' => 0, 'sold' => 0, 'cities' => 0];
    }
}

// --- DUMMY DATA FOR DEMO / DB NOT SET UP YET ---
function getDummyCars($limit) {
    $cars = [
        ['id'=>1,'make_name'=>'Toyota','model_name'=>'Corolla Fielder','year'=>2018,'price'=>1850000,'mileage'=>42000,'fuel_type'=>'Petrol','transmission'=>'Automatic','city'=>'Karachi','image_path'=>'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?w=600&q=80','seller_type'=>'dealer','seller_name'=>'AutoKarachi Ltd','body_type'=>'Station Wagon','is_featured'=>1],
        ['id'=>2,'make_name'=>'Honda','model_name'=>'Fit','year'=>2017,'price'=>980000,'mileage'=>55000,'fuel_type'=>'Petrol','transmission'=>'Automatic','city'=>'Lahore','image_path'=>'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=600&q=80','seller_type'=>'private','seller_name'=>'Ahmad R.','body_type'=>'Hatchback','is_featured'=>1],
        ['id'=>3,'make_name'=>'Mercedes','model_name'=>'C-Class','year'=>2019,'price'=>4500000,'mileage'=>28000,'fuel_type'=>'Diesel','transmission'=>'Automatic','city'=>'Karachi','image_path'=>'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=600&q=80','seller_type'=>'dealer','seller_name'=>'Premier Motors','body_type'=>'Sedan','is_featured'=>1],
        ['id'=>4,'make_name'=>'Nissan','model_name'=>'X-Trail','year'=>2016,'price'=>2200000,'mileage'=>78000,'fuel_type'=>'Petrol','transmission'=>'Automatic','city'=>'Islamabad','image_path'=>'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=600&q=80','seller_type'=>'private','seller_name'=>'Sara A.','body_type'=>'SUV','is_featured'=>0],
        ['id'=>5,'make_name'=>'Mazda','model_name'=>'Demio','year'=>2019,'price'=>1250000,'mileage'=>31000,'fuel_type'=>'Petrol','transmission'=>'Automatic','city'=>'Karachi','image_path'=>'https://images.unsplash.com/photo-1609521263047-f8f205293f24?w=600&q=80','seller_type'=>'dealer','seller_name'=>'JapanAutos PK','body_type'=>'Hatchback','is_featured'=>1],
        ['id'=>6,'make_name'=>'Subaru','model_name'=>'Forester','year'=>2017,'price'=>2800000,'mileage'=>62000,'fuel_type'=>'Petrol','transmission'=>'Automatic','city'=>'Karachi','image_path'=>'https://images.unsplash.com/photo-1606016159991-dfe4f2746ad5?w=600&q=80','seller_type'=>'private','seller_name'=>'Usman K.','body_type'=>'SUV','is_featured'=>0],
        ['id'=>7,'make_name'=>'Toyota','model_name'=>'Land Cruiser V8','year'=>2015,'price'=>9200000,'mileage'=>110000,'fuel_type'=>'Diesel','transmission'=>'Automatic','city'=>'Karachi','image_path'=>'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&q=80','seller_type'=>'dealer','seller_name'=>'Premier Motors','body_type'=>'SUV','is_featured'=>1],
        ['id'=>8,'make_name'=>'Volkswagen','model_name'=>'Golf','year'=>2018,'price'=>1650000,'mileage'=>48000,'fuel_type'=>'Petrol','transmission'=>'Manual','city'=>'Faisalabad','image_path'=>'https://images.unsplash.com/photo-1471479917193-f00955256257?w=600&q=80','seller_type'=>'private','seller_name'=>'Bilal S.','body_type'=>'Hatchback','is_featured'=>0],
    ];
    return array_slice($cars, 0, $limit);
}

// formatPKR() and formatMileage() come from connection.php
// Local fallbacks in case connection.php version differs
if (!function_exists('formatPKR')) {
    function formatPKR($price) { return 'Rs. ' . number_format($price); }
}
if (!function_exists('formatMileage')) {
    function formatMileage($km) { return number_format($km) . ' km'; }
}

// Get badge class for seller type
function sellerBadge($type) {
    return $type === 'dealer' ? 'badge-dealer' : 'badge-private';
}

// --- DATA ---
$featured_cars = getFeaturedCars(8);
$recent_cars   = getRecentCars(8);
$stats         = getSiteStats();

// Popular brands with dynamic counts and latest car image
$brands = DB::select("
    SELECT m.name, COUNT(c.id) as count,
           (SELECT ci.image_path FROM car_images ci 
            JOIN cars c2 ON c2.id = ci.car_id 
            WHERE c2.make_id = m.id AND c2.status = 'active' 
            ORDER BY c2.created_at DESC LIMIT 1) as image
    FROM makes m
    JOIN cars c ON c.make_id = m.id
    WHERE c.status = 'active'
    GROUP BY m.id
    ORDER BY count DESC
    LIMIT 8
") ?: [];

// Body types
$bodyTypes = ['Sedan','Hatchback','SUV','Pickup','Van','Wagon','Coupe','Minibus'];

// Welcome flash (used later in JS)
$welcome = flash('welcome');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="CarSoko – Pakistan's #1 Car Marketplace. Buy and sell new & used cars from private sellers and dealers across Karachi, Lahore, Islamabad and all Pakistan.">
<meta name="keywords" content="cars for sale Pakistan, used cars Karachi, buy car Pakistan, sell my car, car dealer Pakistan">
<title><?= setting('site_name','CarSoko') ?> – Pakistan's #1 Car Marketplace | Buy & Sell Cars</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ============================================================
   CSS VARIABLES & RESET
============================================================ */
:root {
    --black:     #000000;
    --dark:      #0a0a0b;
    --card-bg:   #111114;
    --border:    rgba(255,255,255,0.08);
    --white:     #ffffff;
    --muted:     #a0a0a0;
    --accent:    #e8b84b;
    --accent2:   #ff6b35;
    --green:     #22c55e;
    --red:       #ef4444;
    --gradient:  linear-gradient(135deg, #e8b84b 0%, #ff6b35 100%);
    --font-head: 'Bebas Neue', sans-serif;
    --font-body: 'Inter', sans-serif;
    --radius:    14px;
    --radius-lg: 24px;
    --shadow:    0 10px 40px rgba(0,0,0,0.6);
    --glow:      0 0 40px rgba(232,184,75,0.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    background: var(--black);
    color: var(--white);
    font-family: var(--font-body);
    font-size: 16px;
    line-height: 1.6;
    overflow-x: hidden;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
ul { list-style: none; }

.container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
}

/* ============================================================
   UTILITY
============================================================ */
.section    { padding: 80px 0; }
.section-sm { padding: 50px 0; }

.section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 44px;
    gap: 16px;
    flex-wrap: wrap;
}

.section-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 10px;
}
.section-tag::before {
    content: '';
    width: 24px; height: 2px;
    background: var(--gradient);
    border-radius: 2px;
}

.section-title {
    font-family: var(--font-head);
    font-size: clamp(32px, 5vw, 56px);
    font-weight: 400;
    line-height: 1.0;
    letter-spacing: -0.01em;
    color: var(--white);
    text-transform: uppercase;
}
.section-title span {
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.view-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--accent);
    border: 1px solid rgba(232,184,75,0.3);
    padding: 10px 20px;
    border-radius: 50px;
    transition: all 0.25s;
    white-space: nowrap;
}
.view-all-btn:hover { background: rgba(232,184,75,0.1); border-color: var(--accent); }

/* ============================================================
   TOP BAR
============================================================ */
.topbar {
    background: var(--dark);
    border-bottom: 1px solid var(--border);
    padding: 8px 0;
    font-size: 13px;
    color: var(--muted);
}
.topbar .container { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
.topbar-left { display: flex; gap: 20px; flex-wrap: wrap; }
.topbar-left a { display: flex; align-items: center; gap: 6px; color: var(--muted); transition: color 0.2s; }
.topbar-left a:hover { color: var(--accent); }
.topbar-right { display: flex; gap: 16px; }
.topbar-right a { color: var(--muted); transition: color 0.2s; }
.topbar-right a:hover { color: var(--accent); }

/* ============================================================
   NAVBAR
============================================================ */
.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(10,10,11,0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
}
.navbar .container { display: flex; align-items: center; height: 68px; gap: 32px; }

.logo {
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.logo .logo-car  { color: var(--accent); }
.logo .logo-soko { color: var(--white); }
.logo .logo-dot  {
    width: 8px; height: 8px;
    background: var(--gradient);
    border-radius: 50%;
    margin-left: 2px;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50%       { transform: scale(1.4); opacity: 0.7; }
}

.nav-links { display: flex; align-items: center; gap: 4px; flex: 1; }
.nav-links a {
    font-size: 14px;
    font-weight: 500;
    color: var(--muted);
    padding: 8px 14px;
    border-radius: 8px;
    transition: all 0.2s;
    white-space: nowrap;
}
.nav-links a:hover, .nav-links a.active { color: var(--white); background: rgba(255,255,255,0.06); }

.nav-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s;
    border: none;
    font-family: var(--font-body);
}
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--white); }
.btn-outline:hover { border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); }
.btn-accent {
    background: var(--gradient);
    color: #0a0a0b;
    font-weight: 700;
    box-shadow: 0 4px 20px rgba(232,184,75,0.3);
}
.btn-accent:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(232,184,75,0.5); }

.hamburger {
    display: none;
    flex-direction: column;
    gap: 5px;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
}
.hamburger span { width: 22px; height: 2px; background: var(--white); border-radius: 2px; transition: all 0.3s; }

/* Mobile Nav */
.mobile-nav {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: var(--dark);
    padding: 80px 24px 40px;
    flex-direction: column;
    gap: 8px;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
.mobile-nav.open { display: flex; }
.mobile-nav a {
    font-size: 20px;
    font-weight: 600;
    font-family: var(--font-head);
    color: var(--muted);
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
    transition: color 0.2s;
}
.mobile-nav a:hover { color: var(--accent); }
.mobile-nav-close {
    position: absolute;
    top: 20px; right: 24px;
    font-size: 28px;
    cursor: pointer;
    background: none;
    border: none;
    color: var(--white);
}

/* User dropdown animation */
@keyframes dropIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

/* ============================================================
   HERO
============================================================ */
.hero {
    position: relative;
    min-height: 92vh;
    display: flex;
    align-items: center;
    overflow: hidden;
}
.hero-bg {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(to bottom, rgba(10,10,11,0.3) 0%, rgba(10,10,11,0.6) 50%, rgba(10,10,11,1) 100%),
        url('https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=1800&q=85') center/cover no-repeat;
    transform: scale(1.05);
    animation: slowZoom 20s ease-in-out infinite alternate;
}
@keyframes slowZoom { from { transform: scale(1.05); } to { transform: scale(1.12); } }
.hero-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    opacity: 0.5;
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 780px;
    animation: heroFadeIn 1s ease 0.2s both;
}
@keyframes heroFadeIn { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }

.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(232,184,75,0.12);
    border: 1px solid rgba(232,184,75,0.25);
    color: var(--accent);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 8px 16px;
    border-radius: 50px;
    margin-bottom: 28px;
}
.hero-eyebrow .dot { width:6px; height:6px; background:var(--accent); border-radius:50%; animation:pulse 2s infinite; }

.hero-title {
    font-family: var(--font-head);
    font-size: clamp(52px, 10vw, 110px);
    font-weight: 400;
    line-height: 0.95;
    margin-bottom: 24px;
    letter-spacing: -0.03em;
    text-transform: uppercase;
}
.hero-title .line2 {
    display: block;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: clamp(16px, 2vw, 19px);
    color: rgba(245,245,240,0.65);
    max-width: 520px;
    margin-bottom: 44px;
    font-weight: 300;
    line-height: 1.7;
}

.hero-stats { display: flex; gap: 36px; margin-bottom: 48px; flex-wrap: wrap; }
.hero-stat { animation: heroFadeIn 1s ease calc(0.4s + var(--delay)) both; }
.hero-stat-value { font-family: var(--font-head); font-size: 28px; font-weight: 700; color: var(--white); }
.hero-stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }

/* ============================================================
   SEARCH BOX
============================================================ */
.search-box {
    background: rgba(24,24,28,0.92);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    animation: heroFadeIn 1s ease 0.6s both;
    box-shadow: var(--shadow), var(--glow);
}
.search-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    background: rgba(0,0,0,0.3);
    padding: 4px;
    border-radius: 10px;
    width: fit-content;
}
.search-tab {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: var(--muted);
    transition: all 0.2s;
    border: none;
    background: none;
    font-family: var(--font-body);
}
.search-tab.active { background: var(--gradient); color: #0a0a0b; }

.search-grid { display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 12px; align-items: end; }

.search-field { display: flex; flex-direction: column; gap: 6px; }
.search-field label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
.search-field select,
.search-field input {
    background: rgba(0,0,0,0.4);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--white);
    padding: 12px 14px;
    font-size: 14px;
    font-family: var(--font-body);
    outline: none;
    transition: border-color 0.2s;
    width: 100%;
    -webkit-appearance: none;
    cursor: pointer;
}
.search-field select:focus,
.search-field input:focus { border-color: var(--accent); }
.search-field select option { background: var(--dark); }

.search-btn {
    padding: 13px 28px;
    background: var(--gradient);
    color: #0a0a0b;
    font-weight: 700;
    font-size: 15px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-body);
    transition: all 0.25s;
    white-space: nowrap;
}
.search-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(232,184,75,0.4); }

.search-quick { margin-top: 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.search-quick-label { font-size: 12px; color: var(--muted); }
.search-quick a { font-size: 12px; color: var(--muted); padding: 5px 12px; border: 1px solid var(--border); border-radius: 50px; transition: all 0.2s; }
.search-quick a:hover { color: var(--accent); border-color: rgba(232,184,75,0.4); }

/* ============================================================
   STATS TICKER
============================================================ */
.stats-bar {
    background: var(--dark);
    border-bottom: 1px solid var(--border);
    overflow: hidden;
    position: relative;
}
.stats-bar::before, .stats-bar::after {
    content: '';
    position: absolute;
    top: 0; bottom: 0;
    width: 80px;
    z-index: 2;
}
.stats-bar::before { left:0; background: linear-gradient(to right, var(--dark), transparent); }
.stats-bar::after  { right:0; background: linear-gradient(to left, var(--dark), transparent); }

.stats-ticker { display: flex; animation: ticker 30s linear infinite; width: max-content; }
@keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.stats-ticker-item { display: flex; align-items: center; gap: 40px; padding: 16px 40px; white-space: nowrap; }
.stats-ticker-item span:first-child { font-family: var(--font-head); font-size: 22px; font-weight: 700; color: var(--accent); }
.stats-ticker-item span:last-child  { font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
.ticker-divider { width: 1px; height: 40px; background: var(--border); flex-shrink: 0; }

/* ============================================================
   CAR CARDS
============================================================ */
.cars-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }

.car-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.2, 1, 0.3, 1);
    position: relative;
    cursor: pointer;
}
.car-card:hover { transform: translateY(-6px); border-color: rgba(232,184,75,0.25); box-shadow: 0 20px 50px rgba(0,0,0,0.5), var(--glow); }

.car-card-img { position: relative; height: 200px; overflow: hidden; background: #111; }
.car-card-img img { width:100%; height:100%; object-fit:cover; transition: transform 0.5s ease; }
.car-card:hover .car-card-img img { transform: scale(1.06); }

.car-card-badges { position:absolute; top:12px; left:12px; right:12px; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }

.badge { font-size:11px; font-weight:700; letter-spacing:0.06em; padding:5px 10px; border-radius:6px; text-transform:uppercase; }
.badge-featured { background: var(--gradient); color: #0a0a0b; }
.badge-dealer  { background:rgba(34,197,94,0.15); border:1px solid rgba(34,197,94,0.3); color:var(--green); }
.badge-private { background:rgba(232,184,75,0.12); border:1px solid rgba(232,184,75,0.25); color:var(--accent); }

.car-save-btn {
    position: absolute;
    top: 12px; right: 12px;
    width: 36px; height: 36px;
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    transition: all 0.25s;
    cursor: pointer;
    font-size: 14px;
    backdrop-filter: blur(10px);
    z-index: 1;
}
.car-save-btn:hover, .car-save-btn.saved { color:#ef4444; background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); }

.car-card-body { padding: 18px; }
.car-card-title { font-family:var(--font-head); font-size:17px; font-weight:700; margin-bottom:4px; color:var(--white); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.car-card-sub   { font-size:13px; color:var(--muted); margin-bottom:14px; }
.car-card-specs { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.car-spec       { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--muted); }
.car-spec i     { font-size:11px; color:var(--accent); }

.car-card-footer { display:flex; align-items:center; justify-content:space-between; padding-top:14px; border-top:1px solid var(--border); gap:12px; }
.car-price     { font-family:var(--font-head); font-size:21px; font-weight:700; color:var(--white); line-height:1; }
.car-price-sub { font-size:11px; color:var(--muted); margin-top:2px; }

.car-contact-btn {
    display:flex; align-items:center; gap:6px;
    padding: 9px 16px;
    background: rgba(232,184,75,0.1);
    border: 1px solid rgba(232,184,75,0.25);
    border-radius: 8px;
    font-size: 13px; font-weight: 600;
    color: var(--accent);
    transition: all 0.2s;
    white-space: nowrap;
}
.car-contact-btn:hover { background:rgba(232,184,75,0.2); border-color:var(--accent); }

/* ============================================================
   BRANDS
============================================================ */
.brands-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 12px; }
.brand-card {
    display:flex; flex-direction:column; align-items:center; gap:10px;
    padding: 20px 12px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.25s;
    text-align: center;
}
.brand-card:hover { border-color:rgba(232,184,75,0.4); background:rgba(232,184,75,0.05); transform:translateY(-3px); }
.brand-img   { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; margin-bottom: 4px; background: rgba(255,255,255,0.03); }
.brand-name  { font-size: 13px; font-weight: 700; color: var(--white); }
.brand-count { font-size: 11px; color: var(--muted); }

/* ============================================================
   HOW IT WORKS
============================================================ */
.how-section { background: var(--dark); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.how-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px; }
.how-card { padding: 44px 32px; position: relative; border-right: 1px solid var(--border); transition: background 0.3s; }
.how-card:last-child { border-right: none; }
.how-card:hover { background: rgba(232,184,75,0.03); }
.how-number {
    font-family: var(--font-head); font-size: 64px; font-weight: 800; line-height: 1;
    background: var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    opacity: 0.15; position: absolute; top: 24px; right: 24px;
}
.how-icon { width:52px; height:52px; background:rgba(232,184,75,0.1); border:1px solid rgba(232,184,75,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:20px; color:var(--accent); }
.how-title { font-family:var(--font-head); font-size:18px; font-weight:700; margin-bottom:10px; color:var(--white); }
.how-desc  { font-size:14px; color:var(--muted); line-height:1.7; }

/* ============================================================
   BODY TYPES
============================================================ */
.body-types { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:36px; }
.body-type-btn {
    display:flex; align-items:center; gap:8px;
    padding: 10px 20px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 50px;
    font-size: 13px; font-weight: 500;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s;
    font-family: var(--font-body);
}
.body-type-btn:hover, .body-type-btn.active { background:rgba(232,184,75,0.1); border-color:rgba(232,184,75,0.4); color:var(--accent); }

/* ============================================================
   CTA BANNER
============================================================ */
.cta-banner {
    background: linear-gradient(135deg, rgba(232,184,75,0.12) 0%, rgba(255,107,53,0.08) 100%);
    border: 1px solid rgba(232,184,75,0.2);
    border-radius: var(--radius-lg);
    padding: 64px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.cta-banner::before {
    content:''; position:absolute; top:-60%; left:50%; transform:translateX(-50%);
    width:500px; height:300px;
    background: radial-gradient(ellipse, rgba(232,184,75,0.15), transparent 70%);
    pointer-events: none;
}
.cta-banner-title { font-family:var(--font-head); font-size:clamp(30px,4vw,50px); font-weight:800; margin-bottom:16px; line-height:1.1; }
.cta-banner-sub   { font-size:17px; color:var(--muted); margin-bottom:36px; max-width:500px; margin-left:auto; margin-right:auto; }
.cta-buttons      { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }

/* ============================================================
   TESTIMONIALS
============================================================ */
.testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.testimonial-card { background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; transition:all 0.3s; }
.testimonial-card:hover { border-color:rgba(232,184,75,0.2); transform:translateY(-3px); }
.testimonial-stars  { color:var(--accent); font-size:13px; margin-bottom:16px; letter-spacing:2px; }
.testimonial-text   { font-size:15px; color:rgba(245,245,240,0.75); line-height:1.7; margin-bottom:20px; font-style:italic; }
.testimonial-author { display:flex; align-items:center; gap:12px; }
.testimonial-avatar { width:44px; height:44px; border-radius:50%; background:var(--gradient); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:16px; color:#0a0a0b; flex-shrink:0; }
.testimonial-name   { font-weight:600; font-size:14px; color:var(--white); }
.testimonial-loc    { font-size:12px; color:var(--muted); }

/* ============================================================
   FOOTER
============================================================ */
.footer { background:var(--dark); border-top:1px solid var(--border); padding:64px 0 0; }
.footer-grid { display:grid; grid-template-columns:1.8fr 1fr 1fr 1fr; gap:48px; padding-bottom:48px; border-bottom:1px solid var(--border); }
.footer-brand .logo { font-size:24px; margin-bottom:16px; }
.footer-brand p { font-size:14px; color:var(--muted); line-height:1.7; max-width:280px; margin-bottom:24px; }
.footer-social { display:flex; gap:10px; }
.footer-social a { width:38px; height:38px; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--muted); transition:all 0.2s; font-size:15px; }
.footer-social a:hover { color:var(--accent); border-color:rgba(232,184,75,0.3); background:rgba(232,184,75,0.1); }
.footer-col h4 { font-family:var(--font-head); font-size:14px; font-weight:700; color:var(--white); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:20px; }
.footer-col ul { display:flex; flex-direction:column; gap:10px; }
.footer-col ul a { font-size:14px; color:var(--muted); transition:color 0.2s; display:flex; align-items:center; gap:6px; }
.footer-col ul a::before { content:'→'; font-size:11px; opacity:0; transition:all 0.2s; margin-right:-6px; }
.footer-col ul a:hover { color:var(--accent); }
.footer-col ul a:hover::before { opacity:1; margin-right:0; }
.footer-bottom { padding:24px 0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; font-size:13px; color:var(--muted); }
.footer-bottom a { color:var(--muted); transition:color 0.2s; }
.footer-bottom a:hover { color:var(--accent); }

/* ============================================================
   MOBILE STICKY CTA
============================================================ */
.mobile-cta {
    display: none;
    position: fixed;
    bottom:0; left:0; right:0;
    z-index: 900;
    background: rgba(17,17,20,0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--border);
    padding: 12px 16px;
    gap: 10px;
}
.mobile-cta .btn { flex:1; justify-content:center; font-size:14px; }

/* ============================================================
   FLOATING WHATSAPP
============================================================ */
.wa-float {
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 800;
    width: 56px; height: 56px;
    background: #25D366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: white;
    box-shadow: 0 4px 20px rgba(37,211,102,0.4);
    transition: all 0.3s;
    animation: waBounce 3s ease infinite 2s;
}
.wa-float:hover { transform:scale(1.1); box-shadow:0 8px 30px rgba(37,211,102,0.6); }
@keyframes waBounce { 0%,80%,100% { transform:scale(1); } 40% { transform:scale(1.1); } }

/* ============================================================
   SCROLL ANIMATIONS
============================================================ */
.reveal { opacity:0; transform:translateY(24px); transition:opacity 0.6s ease, transform 0.6s ease; }
.reveal.visible { opacity:1; transform:translateY(0); }
.reveal-delay-1 { transition-delay:0.1s; }
.reveal-delay-2 { transition-delay:0.2s; }
.reveal-delay-3 { transition-delay:0.3s; }

/* ============================================================
   PAGE LOADER
============================================================ */
#loader {
    position: fixed;
    inset: 0;
    background: var(--black);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}
#loader.hidden { opacity:0; visibility:hidden; pointer-events:none; }
.loader-logo { font-family:var(--font-head); font-size:36px; font-weight:800; animation:loaderPulse 1.2s ease-in-out infinite; }
@keyframes loaderPulse { 0%,100% { opacity:0.3; } 50% { opacity:1; } }
.loader-bar { position:absolute; bottom:0; left:0; height:3px; background:var(--gradient); animation:loaderBar 1.5s ease forwards; }
@keyframes loaderBar { from { width:0; } to { width:100%; } }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1100px) {
    .brands-grid { grid-template-columns: repeat(4, 1fr); }
    .how-grid { grid-template-columns: repeat(2, 1fr); }
    .how-card { border-right:none; border-bottom:1px solid var(--border); }
    .how-card:nth-child(odd) { border-right:1px solid var(--border); }
    .how-card:last-child, .how-card:nth-last-child(2) { border-bottom:none; }
    .footer-grid { grid-template-columns: 1fr 1fr; }
    .testimonials-grid { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
    .search-grid { grid-template-columns: 1fr 1fr; }
    .nav-links, .nav-right .btn-outline { display:none; }
    .hamburger { display:flex; }
    .mobile-cta { display:flex; }
    .wa-float { bottom:80px; }
}
@media (max-width: 640px) {
    .hero { min-height:100svh; }
    .search-grid { grid-template-columns:1fr; }
    .search-btn { width:100%; justify-content:center; }
    .brands-grid { grid-template-columns:repeat(4,1fr); }
    .how-grid { grid-template-columns:1fr; }
    .how-card { border-right:none; }
    .footer-grid { grid-template-columns:1fr; }
    .cta-banner { padding:40px 24px; }
    .section { padding:56px 0; }
    .hero-stats { gap:24px; }
    .topbar { display:none; }
}
</style>
</head>
<body>

<!-- PAGE LOADER -->
<div id="loader">
    <div style="text-align:center;position:relative;">
        <div class="loader-logo">
            <span style="background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Car</span><span style="color:var(--white);">Soko</span>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:8px;letter-spacing:0.1em;text-transform:uppercase;">Pakistan's #1 Car Marketplace</div>
    </div>
    <div class="loader-bar"></div>
</div>

<!-- TOP BAR -->
<div class="topbar">
    <div class="container">
        <div class="topbar-left">
            <a href="tel:<?= setting('site_phone','+923000000000') ?>"><i class="fas fa-phone"></i> <?= setting('site_phone','+92 300 000 0000') ?></a>
            <a href="mailto:<?= setting('site_email','info@carsoko.pk') ?>"><i class="fas fa-envelope"></i> <?= setting('site_email','info@carsoko.pk') ?></a>
            <a href="#"><i class="fas fa-map-marker-alt"></i> <?= setting('site_city','Karachi') ?>, Pakistan</a>
        </div>
        <div class="topbar-right">
            <a href="listings.php?condition=new"><i class="fas fa-star"></i> New Cars</a>
            <a href="blog.php"><i class="fas fa-newspaper"></i> Blog</a>
            <a href="register.php?role=dealer"><i class="fas fa-store"></i> Become a Dealer</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo">
            <span class="logo-car"><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span class="logo-soko"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
        </a>

        <div class="nav-links">
            <a href="listings.php" class="active">Browse Cars</a>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="listings.php?seller=dealer">Dealers</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="blog.php">Blog</a>
        </div>

        <div class="nav-right">
            <?php if (Auth::check()):
                $navUser = Auth::user(); ?>
            <!-- Notification bell -->
            <a href="notifications.php" style="position:relative;display:flex;align-items:center;padding:8px;color:var(--muted);transition:color .2s" title="Notifications">
                <i class="fas fa-bell" style="font-size:17px"></i>
                <?php $nc = getNotificationCount(); if ($nc > 0): ?>
                <span style="position:absolute;top:4px;right:4px;width:8px;height:8px;background:var(--red);border-radius:50%;border:2px solid var(--black)"></span>
                <?php endif; ?>
            </a>
            <!-- User dropdown -->
            <div class="user-dropdown-wrap" style="position:relative">
                <button class="user-dropdown-btn" onclick="toggleUserMenu()" style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:50px;padding:6px 14px 6px 6px;cursor:pointer;font-family:var(--font-body);color:var(--white);font-size:13px;font-weight:500;transition:all .2s">
                    <div style="width:30px;height:30px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:13px;color:#0a0a0b;flex-shrink:0">
                        <?= strtoupper(substr($navUser['name'], 0, 1)) ?>
                    </div>
                    <span><?= e(explode(' ', $navUser['name'])[0]) ?></span>
                    <i class="fas fa-chevron-down" style="font-size:10px;color:var(--muted)"></i>
                </button>
                <div id="userDropdown" style="display:none;position:absolute;top:calc(100% + 10px);right:0;min-width:220px;width:max-content;max-width:280px;background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 20px 48px rgba(0,0,0,.6);z-index:9999;animation:dropIn .2s ease">
                    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:14px;color:#0a0a0b;flex-shrink:0"><?= strtoupper(substr($navUser['name'],0,1)) ?></div>
                        <div style="min-width:0">
                            <div style="font-size:13px;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px"><?= e($navUser['name']) ?></div>
                            <div style="font-size:11px;color:var(--accent);margin-top:1px"><?= ucfirst(str_replace('_', ' ', $navUser['role'])) ?></div>
                        </div>
                    </div>
                    <?php if (!Auth::is('buyer')): ?>
                    <a href="dashboard.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-tachometer-alt" style="width:16px;color:var(--accent)"></i> Dashboard</a>
                    <a href="post-listing.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-plus-circle" style="width:16px;color:var(--accent)"></i> Post Listing</a>
                    <?php else: ?>
                    <a href="upgrade-role.php?role=private_seller" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-car" style="width:16px;color:var(--accent)"></i> Sell My Car</a>
                    <a href="upgrade-role.php?role=dealer" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--accent);transition:all .2s;white-space:nowrap;border-top:1px solid var(--border)" onmouseover="this.style.background='rgba(232,184,75,.08)'" onmouseout="this.style.background=''"><i class="fas fa-store" style="width:16px"></i> Become a Dealer</a>
                    <?php endif; ?>
                    <a href="upgrade-role.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-exchange-alt" style="width:16px;color:var(--accent)"></i> Change Account Type</a>
                    <a href="messages.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-comment-dots" style="width:16px;color:var(--accent)"></i> Messages</a>
                    <a href="profile.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--muted);transition:all .2s;white-space:nowrap" onmouseover="this.style.background='rgba(255,255,255,.04)';this.style.color='var(--white)'" onmouseout="this.style.background='';this.style.color='var(--muted)'"><i class="fas fa-user-circle" style="width:16px;color:var(--accent)"></i> My Profile</a>
                    <div style="border-top:1px solid var(--border)">
                        <a href="logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--red);transition:background .2s;white-space:nowrap" onmouseover="this.style.background='rgba(239,68,68,.08)'" onmouseout="this.style.background=''"><i class="fas fa-sign-out-alt" style="width:16px"></i> Sign Out</a>
                    </div>
                </div>
            </div>
            <?php elseif (Auth::is('buyer')): ?>
            <a href="upgrade-role.php?role=private_seller" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <?php endif; ?>
            <div class="hamburger" onclick="toggleMobileNav()">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav" id="mobileNav">
    <button class="mobile-nav-close" onclick="toggleMobileNav()"><i class="fas fa-times"></i></button>
    <?php if (Auth::check()):
        $navUser = Auth::user(); ?>
    <div style="display:flex;align-items:center;gap:12px;padding:0 0 16px;border-bottom:1px solid var(--border);margin-bottom:8px">
        <div style="width:42px;height:42px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:16px;color:#0a0a0b"><?= strtoupper(substr($navUser['name'], 0, 1)) ?></div>
        <div>
            <div style="font-weight:600;font-size:14px"><?= e($navUser['name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= ucfirst(str_replace('_', ' ', $navUser['role'])) ?></div>
        </div>
    </div>
    <?php if (!Auth::is('buyer')): ?>
    <a href="dashboard.php"    onclick="toggleMobileNav()"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="post-listing.php" onclick="toggleMobileNav()"><i class="fas fa-plus"></i> Post Listing</a>
    <?php else: ?>
    <a href="upgrade-role.php?role=private_seller" onclick="toggleMobileNav()"><i class="fas fa-car"></i> Sell My Car</a>
    <a href="upgrade-role.php?role=dealer" onclick="toggleMobileNav()" style="color:var(--accent)"><i class="fas fa-store"></i> Become a Dealer</a>
    <a href="upgrade-role.php" onclick="toggleMobileNav()" style="color:var(--muted)"><i class="fas fa-exchange-alt"></i> Change Account Type</a>
    <?php endif; ?>
    <a href="messages.php"   onclick="toggleMobileNav()"><i class="fas fa-comment"></i> Messages</a>
    <a href="profile.php"    onclick="toggleMobileNav()"><i class="fas fa-user"></i> My Profile</a>
    <div style="padding-top:16px;border-top:1px solid var(--border)">
        <a href="logout.php" style="color:var(--red)" onclick="toggleMobileNav()"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
    <?php else: ?>
    <a href="listings.php"          onclick="toggleMobileNav()">Browse Cars</a>
    <a href="listings.php?condition=new" onclick="toggleMobileNav()">New Cars</a>
    <a href="listings.php?seller=dealer" onclick="toggleMobileNav()">Dealers</a>
    <a href="compare.php"           onclick="toggleMobileNav()">Compare Cars</a>
    <a href="loan-calculator.php"   onclick="toggleMobileNav()">Loan Calculator</a>
    <a href="blog.php"              onclick="toggleMobileNav()">Blog</a>
    <a href="login.php"             onclick="toggleMobileNav()">Sign In</a>
    <div style="padding-top:16px">
        <a href="post-listing.php" class="btn btn-accent" style="width:100%;justify-content:center;"><i class="fas fa-plus"></i> Sell My Car</a>
    </div>
    <?php endif; ?>
</nav>

<!-- ============================================================
     HERO
============================================================ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <div class="dot"></div>
                Pakistan's Premier Car Marketplace
            </div>
            <h1 class="hero-title">
                Find Your Perfect
                <span class="line2">Ride in Pakistan</span>
            </h1>
            <p class="hero-subtitle">
                Buy and sell new &amp; used cars directly from dealers and private sellers across Karachi, Lahore, Islamabad and all major cities of Pakistan.
            </p>

            <!-- Stats Row -->
            <div class="hero-stats">
                <div class="hero-stat" style="--delay:0s">
                    <div class="hero-stat-value"><?= is_numeric($stats['total_cars']) ? number_format((int)$stats['total_cars']) . '+' : e($stats['total_cars']) ?></div>
                    <div class="hero-stat-label">Cars Listed</div>
                </div>
                <div class="hero-stat" style="--delay:0.1s">
                    <div class="hero-stat-value"><?= is_numeric($stats['dealers']) ? number_format((int)$stats['dealers']) . '+' : e($stats['dealers']) ?></div>
                    <div class="hero-stat-label">Verified Dealers</div>
                </div>
                <div class="hero-stat" style="--delay:0.2s">
                    <div class="hero-stat-value">12</div>
                    <div class="hero-stat-label">Cities</div>
                </div>
                <div class="hero-stat" style="--delay:0.3s">
                    <div class="hero-stat-value">10,000 +</div>
                    <div class="hero-stat-label">Cars Sold</div>
                </div>
            </div>

            <!-- SEARCH BOX -->
            <div class="search-box">
                <div class="search-tabs">
                    <button class="search-tab active" onclick="switchTab(this,'buy')">Buy a Car</button>
                    <button class="search-tab" onclick="switchTab(this,'sell')">Sell My Car</button>
                    <button class="search-tab" onclick="switchTab(this,'dealer')">Find Dealer</button>
                </div>

                <form action="listings.php" method="GET" id="searchForm">
                    <div class="search-grid">
                        <div class="search-field">
                            <label>Make</label>
                            <select name="make">
                                <option value="">Any Make</option>
                                <?php
                                $makes = ['Toyota','Suzuki','Honda','Nissan','KIA','Hyundai','Mercedes','BMW','Mazda','Mitsubishi','Audi','MG'];
                                foreach ($makes as $m) echo '<option>' . e($m) . '</option>';
                                ?>
                            </select>
                        </div>
                        <div class="search-field">
                            <label>Model</label>
                            <select name="model">
                                <option value="">Any Model</option>
                            </select>
                        </div>
                        <div class="search-field">
                            <label>Max Price</label>
                            <select name="max_price">
                                <option value="">Any Budget</option>
                                <option value="1000000">Under PKR 1M</option>
                                <option value="2000000">Under PKR 2M</option>
                                <option value="3000000">Under PKR 3M</option>
                                <option value="5000000">Under PKR 5M</option>
                                <option value="10000000">Under PKR 10M</option>
                                <option value="20000000">Under PKR 20M</option>
                            </select>
                        </div>
                        <div class="search-field">
                            <label>Location</label>
                            <select name="city">
                                <option value="">All Pakistan</option>
                                <option>Karachi</option>
                                <option>Lahore</option>
                                <option>Islamabad</option>
                                <option>Rawalpindi</option>
                                <option>Faisalabad</option>
                                <option>Peshawar</option>
                                <option>Quetta</option>
                                <option>Multan</option>
                                <option>Sialkot</option>
                                <option>Hyderabad</option>
                            </select>
                        </div>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>

                <div class="search-quick">
                    <span class="search-quick-label">Popular:</span>
                    <a href="listings.php?make=Toyota">Toyota</a>
                    <a href="listings.php?make=Suzuki">Suzuki</a>
                    <a href="listings.php?max_price=2000000">Under 2M</a>
                    <a href="listings.php?body=SUV">SUVs</a>
                    <a href="listings.php?seller=dealer&verified=1">Verified Dealers</a>
                    <a href="listings.php?fuel=hybrid">Hybrid Cars</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS TICKER -->
<div class="stats-bar">
    <div class="stats-ticker">
        <?php
        $tickerItems = [
            ['5,200+', 'Active Listings'],
            ['320+', 'Verified Dealers'],
            ['PKR 2.5M', 'Avg Car Price'],
            ['47', 'Cities Covered'],
            ['100%', 'Secure Transactions'],
            ['12,800+', 'Cars Sold'],
            ['4.8★', 'Avg Dealer Rating'],
            ['24/7', 'Customer Support'],
        ];
        $all = array_merge($tickerItems, $tickerItems); // duplicate for seamless loop
        foreach ($all as $i => $item): ?>
            <div class="stats-ticker-item">
                <span><?= e($item[0]) ?></span>
                <span><?= e($item[1]) ?></span>
            </div>
            <?php if ($i < count($all) - 1): ?><div class="ticker-divider"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================================
     FEATURED CARS
============================================================ -->
<section class="section">
    <div class="container">
        <div class="section-header reveal">
            <div>
                <div class="section-tag"><i class="fas fa-star"></i> Top Picks</div>
                <h2 class="section-title">Featured <span>Listings</span></h2>
            </div>
            <a href="listings.php?featured=1" class="view-all-btn">View All <i class="fas fa-arrow-right"></i></a>
        </div>

        <div class="cars-grid">
            <?php foreach ($featured_cars as $i => $car): ?>
            <div class="car-card reveal reveal-delay-<?= ($i % 3) + 1 ?>" onclick="window.location='listing.php?id=<?= (int)$car['id'] ?>'">
                <div class="car-card-img">
                    <img src="<?= e(carImageUrl($car['image_path'] ?? '')) ?>"
                         alt="<?= e(($car['make_name'] ?? '') . ' ' . ($car['model_name'] ?? '')) ?>"
                         loading="lazy">
                    <div class="car-card-badges">
                        <?php if (!empty($car['is_featured'])): ?>
                        <span class="badge badge-featured"><i class="fas fa-bolt"></i> Featured</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="car-card-body">
                    <div class="car-card-title"><?= e(($car['make_name'] ?? '') . ' ' . ($car['model_name'] ?? '')) ?></div>
                    <div class="car-card-sub">
                        <?= e($car['year'] ?? '') ?> &middot;
                        <?= e($car['city'] ?? '') ?> &middot;
                        <span class="badge <?= sellerBadge($car['seller_type'] ?? 'private') ?>"><?= ucfirst(e($car['seller_type'] ?? 'private')) ?></span>
                    </div>
                    <div class="car-card-specs">
                        <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?= formatMileage((int)($car['mileage'] ?? 0)) ?></span>
                        <span class="car-spec"><i class="fas fa-gas-pump"></i> <?= e($car['fuel_type'] ?? '') ?></span>
                        <span class="car-spec"><i class="fas fa-cog"></i> <?= e($car['transmission'] ?? '') ?></span>
                    </div>
                    <div class="car-card-footer">
                        <div>
                            <div class="car-price"><?= formatPKR((float)($car['price'] ?? 0)) ?></div>
                            <div class="car-price-sub">Negotiable</div>
                        </div>
                        <a href="listing.php?id=<?= (int)$car['id'] ?>" class="car-contact-btn" onclick="event.stopPropagation()">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     POPULAR BRANDS
============================================================ -->
<section class="section" style="background:var(--dark);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="section-header reveal">
            <div>
                <div class="section-tag"><i class="fas fa-car"></i> Top Brands</div>
                <h2 class="section-title">Browse by <span>Brand</span></h2>
            </div>
            <a href="brands.php" class="view-all-btn">All Brands <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="brands-grid reveal">
            <?php foreach ($brands as $brand): ?>
            <a href="listings.php?make=<?= urlencode($brand['name']) ?>" class="brand-card">
                <img src="<?= e(carImageUrl($brand['image'] ?? '')) ?>" alt="<?= e($brand['name']) ?>" class="brand-img" onerror="this.src='assets/images/placeholder-brand.png'">
                <div class="brand-name"><?= e($brand['name']) ?></div>
                <div class="brand-count"><?= number_format($brand['count']) ?> cars</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     HOW IT WORKS
============================================================ -->
<section class="how-section section">
    <div class="container">
        <div style="text-align:center;margin-bottom:56px;" class="reveal">
            <div class="section-tag" style="justify-content:center;"><i class="fas fa-layer-group"></i> Simple Process</div>
            <h2 class="section-title">How <span><?= setting('site_name','CarSoko') ?></span> Works</h2>
        </div>
        <div class="how-grid">
            <div class="how-card reveal">
                <div class="how-number">01</div>
                <div class="how-icon"><i class="fas fa-search"></i></div>
                <div class="how-title">Search &amp; Filter</div>
                <div class="how-desc">Browse thousands of verified listings by make, model, price, location and more. Use advanced filters to find exactly what you need.</div>
            </div>
            <div class="how-card reveal reveal-delay-1">
                <div class="how-number">02</div>
                <div class="how-icon"><i class="fas fa-comments"></i></div>
                <div class="how-title">Contact Seller</div>
                <div class="how-desc">Message sellers directly through our secure platform, call via WhatsApp, or book a test drive — all in one place.</div>
            </div>
            <div class="how-card reveal reveal-delay-2">
                <div class="how-number">03</div>
                <div class="how-icon"><i class="fas fa-car"></i></div>
                <div class="how-title">Inspect &amp; Test Drive</div>
                <div class="how-desc">Book a test drive at a convenient time. Our booking system lets sellers confirm and manage appointments easily.</div>
            </div>
            <div class="how-card reveal reveal-delay-3">
                <div class="how-number">04</div>
                <div class="how-icon"><i class="fas fa-handshake"></i></div>
                <div class="how-title">Close the Deal</div>
                <div class="how-desc">Negotiate, agree on price, and complete your purchase with confidence using our buyer protection guidelines.</div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     BROWSE BY BODY TYPE
============================================================ -->
<section class="section">
    <div class="container">
        <div class="section-header reveal">
            <div>
                <div class="section-tag"><i class="fas fa-th-large"></i> All Types</div>
                <h2 class="section-title">Browse by <span>Body Type</span></h2>
            </div>
        </div>

        <div class="body-types reveal">
            <button class="body-type-btn active" onclick="filterByBody('', this)">All Types</button>
            <?php foreach ($bodyTypes as $type): ?>
            <button class="body-type-btn" onclick="filterByBody('<?= e($type) ?>', this)"><?= e($type) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="cars-grid">
            <?php foreach ($recent_cars as $i => $car): ?>
            <div class="car-card reveal reveal-delay-<?= ($i % 3) + 1 ?>" onclick="window.location='listing.php?id=<?= (int)$car['id'] ?>'">
                <div class="car-card-img">
                    <img src="<?= e(carImageUrl($car['image_path'] ?? '')) ?>"
                         alt="<?= e(($car['make_name'] ?? '') . ' ' . ($car['model_name'] ?? '')) ?>"
                         loading="lazy">
                </div>
                <div class="car-card-body">
                    <div class="car-card-title"><?= e(($car['make_name'] ?? '') . ' ' . ($car['model_name'] ?? '')) ?></div>
                    <div class="car-card-sub"><?= e($car['year'] ?? '') ?> &middot; <?= e($car['city'] ?? '') ?></div>
                    <div class="car-card-specs">
                        <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?= formatMileage((int)($car['mileage'] ?? 0)) ?></span>
                        <span class="car-spec"><i class="fas fa-gas-pump"></i> <?= e($car['fuel_type'] ?? '') ?></span>
                        <span class="car-spec"><i class="fas fa-cog"></i> <?= e($car['transmission'] ?? '') ?></span>
                        <?php if (!empty($car['body_type'])): ?>
                        <span class="car-spec"><i class="fas fa-car"></i> <?= e($car['body_type']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="car-card-footer">
                        <div>
                            <div class="car-price"><?= formatPKR((float)($car['price'] ?? 0)) ?></div>
                        </div>
                        <a href="listing.php?id=<?= (int)$car['id'] ?>" class="car-contact-btn" onclick="event.stopPropagation()">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;margin-top:40px;" class="reveal">
            <a href="listings.php" class="btn btn-outline" style="padding:14px 36px;font-size:15px;">
                <i class="fas fa-th-large"></i> View All <?= number_format((int)$stats['total_cars']) ?> Cars
            </a>
        </div>
    </div>
</section>

<!-- ============================================================
     CTA BANNER
============================================================ -->
<section class="section-sm">
    <div class="container">
        <div class="cta-banner reveal">
            <div class="section-tag" style="justify-content:center;margin-bottom:16px;"><i class="fas fa-tag"></i> It's Free to List</div>
            <h2 class="cta-banner-title">
                Ready to <span style="background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Sell Your Car?</span>
            </h2>
            <p class="cta-banner-sub">List your car for free and reach thousands of buyers across Pakistan. Private sellers, dealers — everyone welcome.</p>
            <div class="cta-buttons">
                <?php if (!Auth::check() || Auth::is('buyer')): ?>
                <a href="upgrade-role.php?role=private_seller" class="btn btn-accent" style="font-size:16px;padding:14px 32px;">
                    <i class="fas fa-camera"></i> Post Free Listing
                </a>
                <?php else: ?>
                <a href="post-listing.php" class="btn btn-accent" style="font-size:16px;padding:14px 32px;">
                    <i class="fas fa-camera"></i> Post Free Listing
                </a>
                <?php endif; ?>
                <a href="upgrade-role.php?role=dealer" class="btn btn-outline" style="font-size:16px;padding:14px 32px;">
                    <i class="fas fa-store"></i> Register as Dealer
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     TESTIMONIALS
============================================================ -->
<section class="section">
    <div class="container">
        <div style="text-align:center;margin-bottom:48px;" class="reveal">
            <div class="section-tag" style="justify-content:center;"><i class="fas fa-quote-right"></i> Real Reviews</div>
            <h2 class="section-title">What Our <span>Users Say</span></h2>
        </div>
        <div class="testimonials-grid">
            <?php
            $testimonials = [
                ['name'=>'Ahmed Raza', 'city'=>'Karachi', 'text'=>'Found my Toyota Corolla within 3 days on CarSoko. The seller was responsive and the car was exactly as described. Best platform in Pakistan!'],
                ['name'=>'Sara Malik', 'city'=>'Lahore',  'text'=>'Sold my old Honda City in just 1 week. Got a fair price and the messaging system made it so easy to talk to buyers. Highly recommend.'],
                ['name'=>'Usman Khan', 'city'=>'Islamabad', 'text'=>'As a dealer, CarSoko has brought us way more quality leads than any other platform. The featured listing really works!'],
            ];
            foreach ($testimonials as $i => $t): ?>
            <div class="testimonial-card reveal reveal-delay-<?= $i + 1 ?>">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"<?= e($t['text']) ?>"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar"><?= strtoupper(substr($t['name'], 0, 1)) ?></div>
                    <div>
                        <div class="testimonial-name"><?= e($t['name']) ?></div>
                        <div class="testimonial-loc"><i class="fas fa-map-marker-alt" style="color:var(--accent);font-size:10px;"></i> <?= e($t['city']) ?>, Pakistan</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="index.php" class="logo">
                    <span class="logo-car">Car</span><span class="logo-soko">Soko</span><div class="logo-dot"></div>
                </a>
                <p>Pakistan's most trusted car marketplace. Buy, sell and find your perfect car across all major cities.</p>
                <div class="footer-social">
                    <?php $fb = setting('facebook_url',''); if($fb): ?>
                    <a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                    <?php $tw = setting('twitter_url',''); if($tw): ?>
                    <a href="<?= e($tw) ?>" target="_blank" rel="noopener" aria-label="X / Twitter"><i class="fab fa-x-twitter"></i></a>
                    <?php endif; ?>
                    <?php $ig = setting('instagram_url',''); if($ig): ?>
                    <a href="<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                    <?php $wa_url = setting('whatsapp_url',''); if($wa_url): ?>
                    <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <?php endif; ?>
                    <?php $li = setting('linkedin_url',''); if($li): ?>
                    <a href="<?= e($li) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-col">
                <h4>Browse Cars</h4>
                <ul>
                    <li><a href="listings.php?condition=new">New Cars</a></li>
                    <li><a href="listings.php?condition=used">Used Cars</a></li>
                    <li><a href="listings.php?body=SUV">SUVs</a></li>
                    <li><a href="listings.php?fuel=hybrid">Hybrid Cars</a></li>
                    <li><a href="listings.php?seller=dealer">Dealer Cars</a></li>
                    <li><a href="compare.php">Compare Cars</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="listings.php">Browse Cars</a></li>
                    <li><a href="post-listing.php">Sell My Car</a></li>
                    <li><a href="loan-calculator.php">Loan Calculator</a></li>
                    <li><a href="blog.php">Car Guides &amp; News</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Use</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.</span>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <a href="privacy.php">Privacy</a>
                <a href="terms.php">Terms</a>
            </div>
        </div>
    </div>
</footer>

<!-- MOBILE STICKY CTA -->
<div class="mobile-cta">
    <a href="listings.php"    class="btn btn-outline"><i class="fas fa-search"></i> Browse Cars</a>
    <?php if (!Auth::check() || Auth::is('buyer')): ?>
    <a href="upgrade-role.php?role=private_seller" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
    <?php else: ?>
    <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
    <?php endif; ?>
</div>

<!-- FLOATING WHATSAPP -->
<?php $waNum = setting('whatsapp_number','923000000000'); if($waNum): ?>
<a href="https://wa.me/<?= e($waNum) ?>?text=Hello%20<?= urlencode(setting('site_name','CarSoko')) ?>%2C%20I%20need%20help%20finding%20a%20car"
   class="wa-float"
   target="_blank"
   rel="noopener noreferrer"
   title="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>
<?php endif; ?>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
// ── PAGE LOADER (FIXED — no nested listeners, proper fallback) ──
(function () {
    function hideLoader() {
        var loader = document.getElementById('loader');
        if (loader) loader.classList.add('hidden');
    }
    // Primary: hide shortly after all resources finish loading
    window.addEventListener('load', function () {
        setTimeout(hideLoader, 800);
    });
    // Hard fallback: always hide after 3 s no matter what
    setTimeout(hideLoader, 3000);
}());

// ── MOBILE NAV ──
function toggleMobileNav() {
    var nav = document.getElementById('mobileNav');
    nav.classList.toggle('open');
    document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
}

// ── SEARCH TABS ──
function switchTab(btn, mode) {
    document.querySelectorAll('.search-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    if (mode === 'sell') {
        <?php if (Auth::is('buyer')): ?>
        window.location.href = 'upgrade-role.php?role=private_seller';
        <?php else: ?>
        window.location.href = 'post-listing.php';
        <?php endif; ?>
    }
    if (mode === 'dealer') { window.location.href = 'listings.php?seller=dealer'; }
}

// ── BODY TYPE FILTER ──
function filterByBody(type, btn) {
    document.querySelectorAll('.body-type-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    window.location.href = type ? 'listings.php?body=' + encodeURIComponent(type) : 'listings.php';
}

// ── SCROLL REVEAL ──
(function () {
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { observer.observe(el); });
}());

// ── NAVBAR SHADOW ON SCROLL ──
(function () {
    var navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', function () {
        navbar.style.boxShadow = window.scrollY > 60 ? '0 4px 30px rgba(0,0,0,0.5)' : 'none';
    });
}());

// ── DYNAMIC MODEL DROPDOWN ──
(function () {
    var makeSelect  = document.querySelector('select[name="make"]');
    var modelSelect = document.querySelector('select[name="model"]');
    if (!makeSelect || !modelSelect) return;

    var modelsByMake = {
        'Toyota':     ['Corolla','Fielder','Land Cruiser','Hilux','Axio','Prado','RAV4','Vitz','Rush','Harrier'],
        'Nissan':     ['X-Trail','Note','March','Tiida','Juke','Pathfinder','Navara','Leaf'],
        'Honda':      ['Fit/Jazz','Civic','CR-V','Freed','Stream','Vezel','Accord'],
        'Mazda':      ['Demio','CX-5','CX-3','Axela','Atenza','BT-50'],
        'Subaru':     ['Forester','Outback','Impreza','Legacy','XV'],
        'Mercedes':   ['C-Class','E-Class','GLE','A-Class','CLA','GLC'],
        'BMW':        ['3 Series','5 Series','X3','X5','1 Series','7 Series'],
        'Mitsubishi': ['Outlander','Pajero','EK Wagon','Mirage','Eclipse Cross','L200'],
        'Volkswagen': ['Golf','Polo','Tiguan','Passat','Touareg'],
        'Hyundai':    ['Tucson','Elantra','i10','Santa Fe','Creta'],
        'Isuzu':      ['D-Max','MU-X','NQR','FVR'],
        'Land Rover': ['Defender','Discovery','Range Rover','Freelander'],
    };

    makeSelect.addEventListener('change', function () {
        var models = modelsByMake[this.value] || [];
        modelSelect.innerHTML = '<option value="">Any Model</option>';
        models.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = m; opt.textContent = m;
            modelSelect.appendChild(opt);
        });
    });
}());

// ── USER DROPDOWN ──
function toggleUserMenu() {
    var d = document.getElementById('userDropdown');
    if (d) d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function (e) {
    var wrap = document.querySelector('.user-dropdown-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var d = document.getElementById('userDropdown');
        if (d) d.style.display = 'none';
    }
});

<?php if ($welcome): ?>
// ── WELCOME TOAST ──
(function () {
    var t = document.createElement('div');
    t.textContent = '🎉 <?= addslashes(e($welcome)) ?>';
    Object.assign(t.style, {
        position:'fixed', bottom:'24px', right:'24px', zIndex:'9999',
        background:'linear-gradient(135deg,#e8b84b,#ff6b35)',
        color:'#0a0a0b', fontWeight:'700', fontSize:'14px',
        padding:'14px 22px', borderRadius:'12px',
        boxShadow:'0 8px 30px rgba(232,184,75,.4)',
        fontFamily:'DM Sans,sans-serif',
        transition:'opacity .5s'
    });
    document.body.appendChild(t);
    setTimeout(function () {
        t.style.opacity = '0';
        setTimeout(function () { t.remove(); }, 500);
    }, 4000);
}());
<?php endif; ?>
</script>
</body>
</html>
