<?php
// ============================================================
// CarSoko Pakistan - Brands Directory (brands.php)
// Shows all brands with dynamic car counts
// ============================================================
require_once 'connection.php';

// Fetch all brands with car counts and a sample image
$allBrands = DB::select("
    SELECT m.id, m.name, COUNT(c.id) as count,
           (SELECT ci.image_path FROM car_images ci 
            JOIN cars c2 ON c2.id = ci.car_id 
            WHERE c2.make_id = m.id AND c2.status = 'active' 
            ORDER BY c2.created_at DESC LIMIT 1) as image
    FROM makes m
    LEFT JOIN cars c ON c.make_id = m.id AND c.status = 'active'
    GROUP BY m.id
    HAVING count > 0
    ORDER BY m.name ASC
") ?: [];

// Get total count for summary
$totalBrands = count($allBrands);
$totalCars   = array_sum(array_column($allBrands, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Browse all car brands available on CarSoko. Find Toyota, Honda, Suzuki, and more across Pakistan.">
<title>All Brands – CarSoko Pakistan</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --black: #000000;
    --dark: #0a0a0b;
    --card-bg: #111114;
    --border: rgba(255,255,255,0.08);
    --white: #ffffff;
    --muted: #a0a0a0;
    --accent: #e8b84b;
    --gradient: linear-gradient(135deg, #e8b84b 0%, #ff6b35 100%);
    --font-head: 'Bebas Neue', sans-serif;
    --font-body: 'Inter', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--black);
    color: var(--white);
    font-family: var(--font-body);
    line-height: 1.6;
    overflow-x: hidden;
}
.container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
a { text-decoration: none; color: inherit; }

/* Navbar (Simplified) */
.navbar {
    background: rgba(10,10,11,0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.navbar .container { display: flex; align-items: center; justify-content: space-between; }
.logo { font-family: var(--font-head); font-size: 26px; font-weight: 800; display: flex; align-items: center; gap: 4px; }
.logo-car { color: var(--accent); }

/* Header Section */
.brands-header {
    padding: 80px 0 40px;
    text-align: center;
    background: radial-gradient(circle at center, rgba(232,184,75,0.05) 0%, transparent 70%);
}
.header-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 16px;
}
.header-tag::before { content: ''; width: 20px; height: 1px; background: var(--accent); }
.header-title { font-family: var(--font-head); font-size: 64px; text-transform: uppercase; line-height: 1; margin-bottom: 20px; }
.header-desc { color: var(--muted); font-size: 18px; max-width: 600px; margin: 0 auto 40px; }

/* Search Bar */
.search-wrap {
    max-width: 600px;
    margin: 0 auto 60px;
    position: relative;
}
.search-input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 50px;
    padding: 18px 30px 18px 60px;
    color: var(--white);
    font-size: 16px;
    font-family: var(--font-body);
    outline: none;
    transition: all 0.3s;
}
.search-input:focus { border-color: var(--accent); background: rgba(255,255,255,0.08); box-shadow: 0 0 30px rgba(232,184,75,0.1); }
.search-icon { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 18px; }

/* Brand Grid */
.brands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 100px;
}
.brand-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.brand-card:hover {
    transform: translateY(-5px);
    border-color: rgba(232,184,75,0.3);
    background: rgba(232,184,75,0.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.brand-card::after {
    content: '→';
    position: absolute;
    right: 24px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    color: var(--accent);
    opacity: 0;
    transition: all 0.3s;
}
.brand-card:hover::after { opacity: 1; right: 32px; }

.brand-img-wrap {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    overflow: hidden;
    background: #000;
    flex-shrink: 0;
    border: 1px solid var(--border);
}
.brand-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.brand-card:hover img { transform: scale(1.1); }

.brand-info { flex: 1; }
.brand-name { font-family: var(--font-head); font-size: 24px; text-transform: uppercase; color: var(--white); margin-bottom: 4px; }
.brand-meta { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.brand-meta i { color: var(--accent); font-size: 10px; }

/* Empty State */
.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 0;
    display: none;
}
.no-results i { font-size: 48px; color: var(--muted); margin-bottom: 20px; display: block; }

/* Footer (Matching index.php) */
.footer { background: var(--dark); border-top: 1px solid var(--border); padding: 60px 0 30px; margin-top: 100px; }
.footer-bottom { text-align: center; color: var(--muted); font-size: 14px; margin-top: 40px; }

@media (max-width: 768px) {
    .header-title { font-size: 48px; }
    .brands-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .brands-grid { grid-template-columns: 1fr; }
    .brand-card { padding: 16px; }
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo">
            <span class="logo-car">Car</span><span class="logo-soko">Soko</span>
        </a>
        <a href="listings.php" class="header-tag" style="margin:0; font-size:12px;">Browse All Cars <i class="fas fa-arrow-right"></i></a>
    </div>
</nav>

<header class="brands-header">
    <div class="container">
        <div class="header-tag">Directory</div>
        <h1 class="header-title">All <span>Brands</span></h1>
        <p class="header-desc">Find your favorite car brand from our extensive collection of <?= number_format($totalCars) ?> active listings across <?= $totalBrands ?> brands.</p>
        
        <div class="search-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="brandSearch" class="search-input" placeholder="Search brands (e.g. Toyota, Honda...)" onkeyup="filterBrands()">
        </div>
    </div>
</header>

<main class="container">
    <div class="brands-grid" id="brandsGrid">
        <?php foreach ($allBrands as $brand): ?>
        <a href="listings.php?make=<?= urlencode($brand['name']) ?>" class="brand-card" data-name="<?= strtolower($brand['name']) ?>">
            <div class="brand-img-wrap">
                <img src="<?= e(carImageUrl($brand['image'] ?? '')) ?>" alt="<?= e($brand['name']) ?>" onerror="this.src='assets/img/car-placeholder.jpg'">
            </div>
            <div class="brand-info">
                <h3 class="brand-name"><?= e($brand['name']) ?></h3>
                <div class="brand-meta">
                    <i class="fas fa-circle"></i>
                    <span><?= number_format($brand['count']) ?> Active Listings</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        
        <div class="no-results" id="noResults">
            <i class="fas fa-search"></i>
            <h3>No brands found matching your search.</h3>
            <p>Try searching for a different keyword.</p>
        </div>
    </div>
</main>

<footer class="footer">
    <div class="container">
        <div style="text-align:center;">
            <div class="logo" style="justify-content:center; margin-bottom:20px;">
                <span class="logo-car">Car</span><span class="logo-soko">Soko</span>
            </div>
            <p style="color:var(--muted); font-size:14px; max-width:400px; margin:0 auto;">Pakistan's #1 marketplace to buy and sell cars. Trusted by thousands of dealers and private sellers.</p>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.
        </div>
    </div>
</footer>

<script>
function filterBrands() {
    const input = document.getElementById('brandSearch');
    const filter = input.value.toLowerCase();
    const grid = document.getElementById('brandsGrid');
    const cards = grid.getElementsByClassName('brand-card');
    const noResults = document.getElementById('noResults');
    let found = 0;

    for (let i = 0; i < cards.length; i++) {
        const name = cards[i].getAttribute('data-name');
        if (name.includes(filter)) {
            cards[i].style.display = 'flex';
            found++;
        } else {
            cards[i].style.display = 'none';
        }
    }

    noResults.style.display = found === 0 ? 'block' : 'none';
}
</script>

</body>
</html>
