<?php
// ============================================================
//  CarSoko Pakistan — compare.php
//  Side-by-side car comparison (up to 3 cars)
// ============================================================
require_once 'connection.php';

// Parse car IDs from URL: ?ids=1,2,3
$rawIds  = cleanInput($_GET['ids'] ?? '');
$carIds  = array_filter(array_map('intval', explode(',', $rawIds)));
$carIds  = array_unique(array_slice($carIds, 0, 3));

$cars = [];
if (!empty($carIds)) {
    $placeholders = implode(',', array_fill(0, count($carIds), '?'));
    $cars = DB::select("
        SELECT c.*, m.name AS make_name, m.slug AS make_slug,
               mo.name AS model_name, mo.slug AS model_slug,
               u.name AS seller_name, u.role AS seller_type, u.phone,
               (SELECT ci.image_path FROM car_images ci WHERE ci.car_id=c.id AND ci.is_featured=1 LIMIT 1) AS featured_image
        FROM cars c
        JOIN makes m  ON m.id=c.make_id
        LEFT JOIN models mo ON mo.id=c.model_id AND mo.make_id=c.make_id
        JOIN users u   ON u.id=c.user_id
        WHERE c.id IN ($placeholders) AND c.status='active'
        GROUP BY c.id
    ", array_values($carIds));
}

// For search box autocomplete
$allMakes = DB::select("SELECT id,name,slug FROM makes ORDER BY name ASC");

$pageTitle = 'Compare Cars Side by Side | CarSoko Pakistan';
$metaDesc  = 'Compare up to 3 cars side by side — specs, price, mileage, features. Free car comparison tool in Pakistan.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="<?= e($metaDesc) ?>">
<title><?= $pageTitle ?></title>
<link rel="canonical" href="<?= BASE_URL ?>/compare.php">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--black:#0a0a0b;--dark:#111114;--card-bg:#18181c;--border:rgba(255,255,255,.07);--white:#f5f5f0;--muted:#888896;--accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;--gradient:linear-gradient(135deg,#e8b84b,#ff6b35);--font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--radius:10px;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px;line-height:1.6}
a{color:inherit;text-decoration:none}
.container{max-width:1300px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:24px}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 12px;border-radius:8px;transition:all .2s}
.nav-links a:hover,.nav-links a.active{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger span{width:20px;height:2px;background:var(--white);border-radius:2px}
html,body{max-width:100%;overflow-x:hidden}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .25s}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.4)}

/* PAGE HEADER */
.page-header{padding:40px 0 32px;text-align:center}
.page-header h1{font-family:var(--font-head);font-size:clamp(24px,4vw,38px);font-weight:800;margin-bottom:10px}
.page-header h1 span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-header p{color:var(--muted);font-size:15px;max-width:500px;margin:0 auto}

/* ADD CAR SLOTS */
.slots-row{display:grid;grid-template-columns:repeat(<?= max(count($cars),1) <= 1 ? '3' : count($cars) ?>, 1fr);gap:16px;margin-bottom:32px}
.slot-empty{background:var(--card-bg);border:2px dashed var(--border);border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 20px;min-height:200px;cursor:pointer;transition:all .2s;text-align:center}
.slot-empty:hover{border-color:rgba(232,184,75,.4);background:rgba(232,184,75,.04)}
.slot-empty i{font-size:28px;color:var(--muted);opacity:.5;margin-bottom:10px}
.slot-empty span{font-size:13px;color:var(--muted);font-weight:500}
.slot-car{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;overflow:hidden;position:relative}
.slot-car:hover{border-color:rgba(232,184,75,.2)}
.slot-img{height:200px;overflow:hidden;background:#111;position:relative}
.slot-img img{width:100%;height:100%;object-fit:cover}
.slot-remove{position:absolute;top:10px;right:10px;width:28px;height:28px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:50%;color:var(--red);display:flex;align-items:center;justify-content:center;font-size:11px;cursor:pointer;transition:all .2s}
.slot-remove:hover{background:rgba(239,68,68,.3)}
.slot-head{padding:14px 16px;border-bottom:1px solid var(--border)}
.slot-title{font-family:var(--font-head);font-size:15px;font-weight:700;margin-bottom:4px}
.slot-price{font-size:20px;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

/* COMPARISON TABLE */
.compare-table{width:100%;border-collapse:collapse;margin-bottom:40px}
.compare-table th,.compare-table td{padding:14px 16px;text-align:left;vertical-align:top;border-bottom:1px solid var(--border)}
.compare-table thead th{background:var(--dark);font-family:var(--font-head);font-weight:700;font-size:13px}
.compare-table thead th:first-child{width:180px;color:var(--muted)}
.compare-table tbody tr:nth-child(even){background:rgba(255,255,255,.02)}
.compare-table tbody tr:hover{background:rgba(232,184,75,.04)}
.compare-table .row-label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.compare-table .row-label i{color:var(--accent);width:14px;margin-right:4px}
.val{font-size:14px;font-weight:500}
.val-best{color:var(--green);font-weight:700}
.val-worst{color:var(--red)}
.badge-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:50px;font-size:11px;font-weight:600}
.pill-green{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.pill-red{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)}
.pill-gold{background:rgba(232,184,75,.1);color:var(--accent);border:1px solid rgba(232,184,75,.2)}

/* Winner card */
.winner-banner{background:linear-gradient(135deg,rgba(232,184,75,.12),rgba(255,107,53,.08));border:1px solid rgba(232,184,75,.25);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px}
.winner-banner i{font-size:22px;color:var(--accent)}
.winner-text{font-size:14px;color:var(--muted)}.winner-text strong{color:var(--accent)}

/* Search input */
.search-car-input{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--white);width:100%;outline:none;transition:border-color .2s}
.search-car-input:focus{border-color:var(--accent)}
.search-car-input::placeholder{color:var(--muted)}

.section-title{font-family:var(--font-head);font-size:18px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.section-title i{color:var(--accent)}

/* Empty compare state */
.compare-empty{text-align:center;padding:60px 24px;color:var(--muted)}
.compare-empty i{font-size:56px;opacity:.1;display:block;margin-bottom:20px}
.compare-empty h2{font-family:var(--font-head);font-size:22px;font-weight:700;color:rgba(245,245,240,.25);margin-bottom:8px}
.compare-empty p{font-size:14px;max-width:380px;margin:0 auto 24px}

/* CTA row */
.cta-row{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px}

@media(max-width:900px){
    .nav-links{display:none;position:absolute;top:64px;left:0;width:100%;background:var(--dark);flex-direction:column;padding:20px;gap:10px;border-bottom:1px solid var(--border);z-index:201}
    .nav-links.active{display:flex}
    .hamburger{display:flex}
}

@media(max-width:768px){
    .slots-row{grid-template-columns:1fr!important}
    .compare-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}
    .compare-table th,.compare-table td{padding:11px 12px;font-size:13px;white-space:nowrap}
    .compare-table thead th:first-child{width:130px}
    .page-header{padding:28px 0 22px}
    .page-header p{font-size:13px;padding:0 8px}
    .winner-banner{flex-direction:column;text-align:center;padding:14px 16px}
    .cta-row{flex-direction:column}
    .cta-row .btn{width:100%;justify-content:center}
}

@media(max-width:480px){
    .container{padding:0 14px}
    .navbar .container{gap:10px}
    .nav-right .btn span,.nav-right .btn{font-size:12px;padding:7px 12px}
    .slot-img{height:150px}
    .slot-head{padding:12px}
    .compare-table thead th:first-child{width:110px}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo"><span>Car</span><span style="color:var(--white)">Soko</span><div class="logo-dot"></div></a>
        <div class="nav-links" id="mobileNav">
            <a href="listings.php">Browse Cars</a>
            <a href="compare.php" class="active">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="blog.php">Blog</a>
        </div>
        <div class="nav-right">
            <?php if (Auth::check()): $u=Auth::user(); ?>
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-user"></i> <?= e(explode(' ',$u['name'])[0]) ?></a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="listings.php" class="btn btn-accent"><i class="fas fa-search"></i> Browse Cars</a>
            <div class="hamburger" onclick="document.getElementById('mobileNav').classList.toggle('active')"><span></span><span></span><span></span></div>
        </div>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1>Compare <span>Cars Side by Side</span></h1>
        <p>Select up to 3 cars to compare specs, price, and features instantly</p>
    </div>

    <?php if (empty($cars)): ?>
    <!-- EMPTY STATE: no cars selected yet -->
    <div class="compare-empty">
        <i class="fas fa-balance-scale"></i>
        <h2>No Cars Selected</h2>
        <p>Go to the car listings, tick the "Compare" checkbox on any car cards, then click "Compare Now" — or search for cars below.</p>
        <a href="listings.php" class="btn btn-accent"><i class="fas fa-search"></i> Browse Cars to Compare</a>
    </div>

    <?php else: ?>

    <!-- CAR SLOTS -->
    <div class="slots-row" id="slotsRow">
        <?php foreach ($cars as $car):
            $img = !empty($car['featured_image']) ? carImageUrl($car['featured_image']) : BASE_URL.'/assets/img/placeholder.jpg';
        ?>
        <div class="slot-car" id="slot-<?= $car['id'] ?>">
            <div class="slot-img">
                <img src="<?= e($img) ?>" alt="<?= e($car['make_name'].' '.$car['model_name']) ?>">
                <a href="?ids=<?= implode(',', array_filter(array_diff(array_map(fn($c)=>$c['id'],$cars), [$car['id']]))) ?>" class="slot-remove" title="Remove"><i class="fas fa-times"></i></a>
            </div>
            <div class="slot-head">
                <div class="slot-title"><?= e($car['year'].' '.$car['make_name'].' '.$car['model_name']) ?></div>
                <div class="slot-price"><?= formatPKR($car['price']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php for ($i = count($cars); $i < 3; $i++): ?>
        <div class="slot-empty" onclick="document.getElementById('searchModal').style.display='flex'">
            <i class="fas fa-plus-circle"></i>
            <span>Add Car to Compare</span>
            <span style="font-size:11px;color:var(--muted);margin-top:4px">Click to search</span>
        </div>
        <?php endfor; ?>
    </div>

    <!-- WINNER BANNER -->
    <?php if (count($cars) >= 2):
        $cheapest = $cars[0];
        foreach ($cars as $c) if ($c['price'] < $cheapest['price']) $cheapest = $c;
    ?>
    <div class="winner-banner">
        <i class="fas fa-trophy"></i>
        <div class="winner-text">
            <strong><?= e($cheapest['year'].' '.$cheapest['make_name'].' '.$cheapest['model_name']) ?></strong>
            is the most affordable at <strong><?= formatPKR($cheapest['price']) ?></strong>
            <?php
            $lowMileage = $cars[0];
            foreach ($cars as $c) if ($c['mileage'] < $lowMileage['mileage']) $lowMileage = $c;
            if ($lowMileage['id'] !== $cheapest['id']):
            ?>
            &nbsp;·&nbsp; <strong><?= e($lowMileage['year'].' '.$lowMileage['make_name'].' '.$lowMileage['model_name']) ?></strong> has the lowest mileage (<?= number_format($lowMileage['mileage']) ?> km)
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- COMPARISON TABLE -->
    <?php
    $prices   = array_column($cars, 'price');
    $mileages = array_column($cars, 'mileage');
    $minPrice = min($prices); $maxPrice = max($prices);
    $minMile  = min($mileages);
    $fields   = [
        ['label'=>'Price (Rs.)',   'icon'=>'fa-tag',           'key'=>'price',        'format'=>fn($v)=>formatPKR($v)],
        ['label'=>'Year',          'icon'=>'fa-calendar',      'key'=>'year',         'format'=>fn($v)=>$v],
        ['label'=>'Mileage',       'icon'=>'fa-tachometer-alt','key'=>'mileage',      'format'=>fn($v)=>number_format($v).' km'],
        ['label'=>'Fuel Type',     'icon'=>'fa-gas-pump',      'key'=>'fuel_type',    'format'=>fn($v)=>ucfirst($v)],
        ['label'=>'Transmission',  'icon'=>'fa-cog',           'key'=>'transmission', 'format'=>fn($v)=>ucfirst($v)],
        ['label'=>'Body Type',     'icon'=>'fa-car',           'key'=>'body_type',    'format'=>fn($v)=>ucfirst($v)],
        ['label'=>'Drive Type',    'icon'=>'fa-road',          'key'=>'drive_type',   'format'=>fn($v)=>strtoupper($v)],
        ['label'=>'Engine (cc)',   'icon'=>'fa-bolt',          'key'=>'engine_cc',    'format'=>fn($v)=>$v?$v.'cc':'N/A'],
        ['label'=>'Horsepower',    'icon'=>'fa-horse',         'key'=>'horsepower',   'format'=>fn($v)=>$v?$v.' hp':'N/A'],
        ['label'=>'Color',         'icon'=>'fa-palette',       'key'=>'color',        'format'=>fn($v)=>$v?:'-'],
        ['label'=>'Doors',         'icon'=>'fa-door-open',     'key'=>'doors',        'format'=>fn($v)=>$v?:'-'],
        ['label'=>'Seats',         'icon'=>'fa-users',         'key'=>'seats',        'format'=>fn($v)=>$v?:'-'],
        ['label'=>'Condition',     'icon'=>'fa-star',          'key'=>'condition',    'format'=>fn($v)=>str_replace('_',' ',ucfirst($v))],
        ['label'=>'City',          'icon'=>'fa-map-marker-alt','key'=>'city',         'format'=>fn($v)=>$v],
        ['label'=>'Seller Type',   'icon'=>'fa-store',         'key'=>'seller_type',  'format'=>fn($v)=>ucfirst(str_replace('_',' ',$v))],
        ['label'=>'Negotiable',    'icon'=>'fa-handshake',     'key'=>'price_negotiable','format'=>fn($v)=>$v?'Yes':'No'],
    ];
    ?>
    <div class="section-title"><i class="fas fa-table"></i> Full Spec Comparison</div>
    <div style="overflow-x:auto;margin-bottom:40px">
    <table class="compare-table">
        <thead>
            <tr>
                <th>Specification</th>
                <?php foreach ($cars as $c): ?>
                <th><?= e($c['year'].' '.$c['make_name'].' '.$c['model_name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fields as $field): ?>
        <tr>
            <td class="row-label"><i class="fas <?= $field['icon'] ?>"></i> <?= $field['label'] ?></td>
            <?php foreach ($cars as $car):
                $val = $car[$field['key']] ?? null;
                $formatted = $field['format']($val);
                $cls = '';
                if ($field['key']==='price') {
                    $cls = ($val==$minPrice&&count($cars)>1) ? 'val-best' : (($val==$maxPrice&&count($cars)>1) ? 'val-worst' : '');
                } elseif ($field['key']==='mileage') {
                    $cls = ($val==$minMile&&count($cars)>1) ? 'val-best' : '';
                } elseif ($field['key']==='year') {
                    $maxYear=max(array_column($cars,'year'));
                    $cls = ($val==$maxYear&&count($cars)>1) ? 'val-best' : '';
                }
            ?>
            <td class="val <?= $cls ?>"><?= e($formatted) ?>
                <?php if ($cls==='val-best'): ?><span class="badge-pill pill-green" style="margin-left:4px"><i class="fas fa-check"></i> Best</span><?php endif; ?>
                <?php if ($cls==='val-worst'&&$field['key']==='price'): ?><span class="badge-pill pill-red" style="margin-left:4px">Highest</span><?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- CTA BUTTONS -->
    <div class="cta-row">
        <?php foreach ($cars as $c): ?>
        <a href="listing.php?id=<?= $c['id'] ?>" class="btn btn-accent"><i class="fas fa-eye"></i> View <?= e($c['make_name'].' '.$c['model_name']) ?></a>
        <?php endforeach; ?>
        <a href="listings.php" class="btn btn-outline"><i class="fas fa-search"></i> Browse More Cars</a>
    </div>

    <!-- Add car search modal -->
    <div id="searchModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(6px)">
        <div style="background:var(--dark);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:480px;width:90%;position:relative">
            <button onclick="document.getElementById('searchModal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer"><i class="fas fa-times"></i></button>
            <h3 style="font-family:var(--font-head);font-weight:700;font-size:17px;margin-bottom:16px"><i class="fas fa-search" style="color:var(--accent)"></i> Search a Car to Add</h3>
            <input class="search-car-input" id="carSearchInput" placeholder="Type make, model, or year…" autocomplete="off" oninput="searchCarsLive(this.value)">
            <div id="carSearchResults" style="margin-top:12px;max-height:280px;overflow-y:auto"></div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
const CURRENT_IDS = [<?= implode(',', array_column($cars, 'id')) ?>];

function searchCarsLive(q) {
    const results = document.getElementById('carSearchResults');
    if (q.length < 2) { results.innerHTML=''; return; }
    results.innerHTML='<div style="color:var(--muted);font-size:13px;padding:8px">Searching…</div>';
    fetch('ajax/search-cars.php?q='+encodeURIComponent(q)+'&exclude='+CURRENT_IDS.join(','))
    .then(r=>r.json())
    .then(data => {
        if (!data.length) { results.innerHTML='<div style="color:var(--muted);font-size:13px;padding:8px">No results found</div>'; return; }
        results.innerHTML = data.map(c => {
            const alreadyAdded = CURRENT_IDS.includes(c.id);
            const newIds = alreadyAdded ? CURRENT_IDS : [...new Set([...CURRENT_IDS, c.id])];
            return `
            <a href="compare.php?ids=${newIds.join(',')}"
               style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:background .2s;margin-bottom:4px;background:rgba(255,255,255,.03);${alreadyAdded?'opacity:.5;pointer-events:none;':''}">`
                + `<img src="${c.image||'assets/img/placeholder.jpg'}" style="width:54px;height:38px;object-fit:cover;border-radius:6px" alt="">`
                + `<div style="flex:1;min-width:0">`
                + `<div style="font-size:13px;font-weight:600">${c.year} ${c.make} ${c.model}</div>`
                + `<div style="font-size:11px;color:var(--muted)">${c.city||''}</div>`
                + `</div>`
                + `<div style="font-size:12px;color:#e8b84b;font-weight:700;white-space:nowrap">${alreadyAdded?'<i class=\'fas fa-check\'></i> Added':c.price_fmt}</div>`
            + `</a>`;
        }).join('');
    })
    .catch(() => results.innerHTML='<div style="color:var(--red);font-size:13px;padding:8px">Error searching. Please try again.</div>');
}
</script>
</body>
</html>