<?php
// ============================================================
//  CarSoko Pakistan — seller.php
//  Public profile page for dealers & private sellers
// ============================================================
require_once 'connection.php';

$sellerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sellerId <= 0) {
    header('Location: listings.php');
    exit;
}

// Load seller/dealer
$seller = DB::selectOne(
    "SELECT id, name, email, phone, role, city, bio, business_name, created_at,
            profile_photo, is_verified_seller AS is_verified
     FROM users
     WHERE id = ? AND role IN ('dealer','private_seller','admin','moderator')",
    [$sellerId]
);

// Social columns not yet in DB — pad with empty strings so the rest of the page works fine
if ($seller) {
    $seller['facebook_url']    = '';
    $seller['instagram_url']   = '';
    $seller['whatsapp_number'] = '';
}

if (!$seller) {
    header('Location: listings.php');
    exit;
}

// Seller stats
$stats = DB::selectOne(
    "SELECT COUNT(*) AS total_listings,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_listings,
            SUM(CASE WHEN status='sold'   THEN 1 ELSE 0 END) AS cars_sold,
            AVG(price) AS avg_price
     FROM cars WHERE user_id = ?",
    [$sellerId]
) ?: ['total_listings'=>0,'active_listings'=>0,'cars_sold'=>0,'avg_price'=>0];

// Rating
$avgRating    = DB::value("SELECT AVG(rating) FROM reviews WHERE reviewed_user_id=? AND status='approved'", [$sellerId]) ?: 0;
$reviewCount  = (int)(DB::value("SELECT COUNT(*) FROM reviews WHERE reviewed_user_id=? AND status='approved'", [$sellerId]) ?: 0);
$reviews      = DB::select(
    "SELECT r.*, u.name AS reviewer_name FROM reviews r
     LEFT JOIN users u ON u.id = r.reviewer_id
     WHERE r.reviewed_user_id = ? AND r.status = 'approved'
     ORDER BY r.created_at DESC LIMIT 6",
    [$sellerId]
) ?: [];

// Active listings
$listings = DB::select("
    SELECT c.*, m.name AS make_name,
           (SELECT mo.name FROM models mo WHERE mo.id=c.model_id AND mo.make_id=c.make_id LIMIT 1) AS model_name,
           (SELECT ci.image_path FROM car_images ci WHERE ci.car_id=c.id AND ci.is_featured=1 LIMIT 1) AS image_path
    FROM cars c
    JOIN makes m ON m.id = c.make_id
    WHERE c.user_id = ? AND c.status = 'active'
    ORDER BY c.is_featured DESC, c.created_at DESC
    LIMIT 12
", [$sellerId]) ?: [];

$isDealer  = $seller['role'] === 'dealer';
$pageTitle = $isDealer
    ? (e($seller['business_name'] ?: $seller['name']) . ' — Dealer Profile | CarSoko Pakistan')
    : (e($seller['name']) . ' — Seller Profile | CarSoko Pakistan');

$wa = $seller['whatsapp_number'] ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="description" content="View <?= e($seller['business_name'] ?: $seller['name']) ?>'s car listings on CarSoko Pakistan.">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --black:#0a0a0b;--dark:#111114;--card-bg:#18181c;
  --border:rgba(255,255,255,.08);--white:#f5f5f0;--muted:#888896;
  --accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;
  --gradient:linear-gradient(135deg,#e8b84b,#ff6b35);
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:12px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.6}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}

.container{max-width:1180px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:20px}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center;gap:2px}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 12px;border-radius:8px;transition:all .2s}
.nav-links a:hover{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .25s;font-family:var(--font-body)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.4)}
.btn-green{background:#25D366;color:#fff}
.btn-green:hover{box-shadow:0 6px 20px rgba(37,211,102,.4);transform:translateY(-2px)}

/* HERO BANNER */
.profile-hero{
  background:linear-gradient(135deg,rgba(232,184,75,.08) 0%,rgba(255,107,53,.05) 100%);
  border-bottom:1px solid var(--border);
  padding:48px 0 40px;
}
.profile-inner{display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap}
.profile-avatar{
  width:110px;height:110px;border-radius:20px;
  background:var(--gradient);
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-head);font-size:42px;font-weight:800;color:#0a0a0b;
  flex-shrink:0;position:relative;overflow:hidden;
}
.profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:20px}
.profile-info{flex:1;min-width:0}
.profile-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700;letter-spacing:.04em}
.badge-dealer{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:var(--green)}
.badge-seller{background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.3);color:var(--accent)}
.badge-verified{background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#60a5fa}
.profile-name{font-family:var(--font-head);font-size:clamp(22px,4vw,34px);font-weight:800;line-height:1.1;margin-bottom:4px}
.profile-business{font-size:15px;color:var(--muted);margin-bottom:12px}
.profile-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--muted);margin-bottom:16px}
.profile-meta span{display:flex;align-items:center;gap:5px}
.profile-meta i{color:var(--accent);font-size:11px}
.profile-bio{font-size:14px;color:rgba(245,245,240,.7);max-width:560px;line-height:1.7;margin-bottom:20px}
.profile-actions{display:flex;gap:10px;flex-wrap:wrap}

/* STARS */
.stars{color:var(--accent);font-size:13px;letter-spacing:1px}
.rating-summary{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.rating-number{font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--white)}
.rating-sub{font-size:12px;color:var(--muted)}

/* STATS ROW */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin:40px 0}
.stat-box{background:var(--card-bg);padding:20px 24px;text-align:center}
.stat-val{font-family:var(--font-head);font-size:26px;font-weight:800;color:var(--white);line-height:1}
.stat-val span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:4px}

/* LISTINGS GRID */
.section-title{font-family:var(--font-head);font-size:clamp(18px,3vw,26px);font-weight:800;margin-bottom:24px;display:flex;align-items:center;gap:10px}
.section-title i{color:var(--accent);font-size:18px}
.cars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;margin-bottom:48px}
.car-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;overflow:hidden;cursor:pointer;transition:all .35s cubic-bezier(.2,1,.3,1)}
.car-card:hover{transform:translateY(-5px);border-color:rgba(232,184,75,.25);box-shadow:0 16px 40px rgba(0,0,0,.5)}
.car-card-img{height:185px;overflow:hidden;background:#111;position:relative}
.car-card-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.car-card:hover .car-card-img img{transform:scale(1.06)}
.featured-badge{position:absolute;top:10px;left:10px;background:var(--gradient);color:#0a0a0b;font-size:10px;font-weight:700;padding:3px 8px;border-radius:5px;letter-spacing:.04em}
.car-body{padding:14px}
.car-title{font-family:var(--font-head);font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.car-sub{font-size:12px;color:var(--muted);margin-bottom:10px}
.car-specs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.spec{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px}
.spec i{color:var(--accent);font-size:10px}
.car-footer{display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);padding-top:10px}
.car-price{font-family:var(--font-head);font-size:18px;font-weight:800;color:var(--white)}
.view-btn{font-size:12px;font-weight:600;color:var(--accent);display:flex;align-items:center;gap:4px;padding:6px 12px;border:1px solid rgba(232,184,75,.25);border-radius:7px;transition:all .2s}
.view-btn:hover{background:rgba(232,184,75,.1);border-color:var(--accent)}

/* REVIEWS */
.reviews-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:48px}
.review-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:20px}
.review-stars{color:var(--accent);font-size:12px;margin-bottom:10px}
.review-text{font-size:14px;color:rgba(245,245,240,.75);font-style:italic;line-height:1.7;margin-bottom:14px}
.review-author{display:flex;align-items:center;gap:8px}
.r-avatar{width:32px;height:32px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:12px;color:#0a0a0b;font-weight:700;flex-shrink:0}
.r-name{font-size:13px;font-weight:600}
.r-time{font-size:11px;color:var(--muted)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state i{font-size:48px;opacity:.15;display:block;margin-bottom:16px}
.empty-state p{font-size:14px}

/* CONTACT SIDEBAR (desktop) */
.profile-layout{display:grid;grid-template-columns:1fr 280px;gap:32px;margin-top:40px}
.contact-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:24px;position:sticky;top:84px}
.contact-card h3{font-family:var(--font-head);font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.contact-card h3 i{color:var(--accent)}
.contact-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;border-radius:10px;font-size:14px;font-weight:600;font-family:var(--font-body);cursor:pointer;border:none;transition:all .25s;margin-bottom:10px}
.contact-btn-wa{background:#25D366;color:#fff}
.contact-btn-wa:hover{box-shadow:0 6px 20px rgba(37,211,102,.4);transform:translateY(-2px)}
.contact-btn-msg{background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.25);color:var(--accent)}
.contact-btn-msg:hover{background:rgba(232,184,75,.2)}
.contact-btn-list{background:transparent;border:1px solid var(--border);color:var(--white)}
.contact-btn-list:hover{background:rgba(255,255,255,.05)}
.contact-divider{border:none;border-top:1px solid var(--border);margin:16px 0}
.contact-info-row{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);margin-bottom:10px}
.contact-info-row i{color:var(--accent);width:14px;text-align:center}
.social-row{display:flex;gap:8px;margin-top:12px}
.social-btn{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);transition:all .2s;font-size:14px}
.social-btn:hover{color:var(--accent);border-color:rgba(232,184,75,.3);background:rgba(232,184,75,.08)}

/* FOOTER */
.footer{background:var(--dark);border-top:1px solid var(--border);padding:28px 0;text-align:center;font-size:13px;color:var(--muted);margin-top:60px}
.footer a{color:var(--muted);transition:color .2s}
.footer a:hover{color:var(--accent)}

@media(max-width:900px){
  .profile-layout{grid-template-columns:1fr}
  .contact-card{position:static;margin-bottom:32px}
  .stats-row{grid-template-columns:repeat(2,1fr)}
  .profile-inner{gap:20px}
  .nav-links{display:none}
}
@media(max-width:600px){
  .stats-row{grid-template-columns:1fr 1fr}
  .profile-avatar{width:80px;height:80px;font-size:30px}
  .profile-hero{padding:28px 0 24px}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="container" style="display:flex;align-items:center;gap:20px;height:64px">
    <a href="index.php" class="logo">
      <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
    </a>
    <div class="nav-links">
      <a href="listings.php">Browse Cars</a>
      <a href="listings.php?seller=dealer">Dealers</a>
      <a href="compare.php">Compare</a>
      <a href="loan-calculator.php">Loan Calc</a>
    </div>
    <div class="nav-right">
      <?php if (Auth::check()): $nu = Auth::user(); ?>
      <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <?php else: ?>
      <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
      <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- BREADCRUMB -->
<div style="background:var(--dark);border-bottom:1px solid var(--border);padding:10px 0;font-size:12px;color:var(--muted)">
  <div class="container" style="display:flex;align-items:center;gap:6px">
    <a href="index.php" style="color:var(--muted);transition:color .2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Home</a>
    <i class="fas fa-chevron-right" style="font-size:9px;opacity:.4"></i>
    <a href="listings.php?seller=dealer" style="color:var(--muted);transition:color .2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'"><?= $isDealer ? 'Dealers' : 'Sellers' ?></a>
    <i class="fas fa-chevron-right" style="font-size:9px;opacity:.4"></i>
    <span style="color:var(--white)"><?= e($seller['business_name'] ?: $seller['name']) ?></span>
  </div>
</div>

<!-- PROFILE HERO -->
<div class="profile-hero">
  <div class="container">
    <div class="profile-inner">
      <!-- Avatar -->
      <div class="profile-avatar">
        <?php if (!empty($seller['profile_photo'])): ?>
        <img src="<?= e(carImageUrl($seller['profile_photo'])) ?>" alt="<?= e($seller['name']) ?>">
        <?php else: ?>
        <?= strtoupper(substr($seller['name'],0,1)) ?>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="profile-info">
        <div class="profile-badges">
          <?php if ($isDealer): ?>
          <span class="badge badge-dealer"><i class="fas fa-store"></i> Verified Dealer</span>
          <?php else: ?>
          <span class="badge badge-seller"><i class="fas fa-user"></i> Private Seller</span>
          <?php endif; ?>
          <?php if (!empty($seller['is_verified'])): ?>
          <span class="badge badge-verified"><i class="fas fa-shield-alt"></i> Verified</span>
          <?php endif; ?>
        </div>

        <h1 class="profile-name"><?= e($isDealer && $seller['business_name'] ? $seller['business_name'] : $seller['name']) ?></h1>
        <?php if ($isDealer && $seller['business_name'] && $seller['name'] !== $seller['business_name']): ?>
        <div class="profile-business">Managed by <?= e($seller['name']) ?></div>
        <?php endif; ?>

        <div class="profile-meta">
          <?php if (!empty($seller['city'])): ?>
          <span><i class="fas fa-map-marker-alt"></i> <?= e($seller['city']) ?>, Pakistan</span>
          <?php endif; ?>
          <span><i class="fas fa-calendar-alt"></i> Member since <?= date('M Y', strtotime($seller['created_at'])) ?></span>
          <?php if ($stats['active_listings'] > 0): ?>
          <span><i class="fas fa-car"></i> <?= number_format((int)$stats['active_listings']) ?> active listing<?= $stats['active_listings'] != 1 ? 's' : '' ?></span>
          <?php endif; ?>
          <?php if ($reviewCount > 0): ?>
          <span>
            <i class="fas fa-star"></i>
            <?= number_format((float)$avgRating, 1) ?> rating (<?= $reviewCount ?> review<?= $reviewCount != 1 ? 's' : '' ?>)
          </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($seller['bio'])): ?>
        <p class="profile-bio"><?= nl2br(e($seller['bio'])) ?></p>
        <?php endif; ?>

        <!-- Action Buttons (mobile/tablet view) -->
        <div class="profile-actions" style="display:none" id="mobileActions">
          <?php if ($wa): ?>
          <a href="https://wa.me/<?= e($wa) ?>?text=Hi%20<?= urlencode($seller['business_name'] ?: $seller['name']) ?>%2C%20I%20saw%20your%20profile%20on%20CarSoko" target="_blank" class="btn btn-green">
            <i class="fab fa-whatsapp"></i> WhatsApp
          </a>
          <?php endif; ?>
          <?php if (Auth::check() && Auth::id() !== $sellerId): ?>
          <a href="listings.php?seller_id=<?= $sellerId ?>" class="btn btn-outline">
            <i class="fas fa-car"></i> View All Cars
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- STATS ROW -->
<div class="container">
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val"><span><?= number_format((int)$stats['total_listings']) ?></span></div>
      <div class="stat-lbl">Total Listings</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><span><?= number_format((int)$stats['active_listings']) ?></span></div>
      <div class="stat-lbl">Active Now</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><span><?= number_format((int)$stats['cars_sold']) ?></span></div>
      <div class="stat-lbl">Cars Sold</div>
    </div>
    <div class="stat-box">
      <div class="stat-val">
        <?php if ($reviewCount > 0): ?>
        <span><?= number_format((float)$avgRating, 1) ?>★</span>
        <?php else: ?>
        <span style="font-size:20px;opacity:.4">—</span>
        <?php endif; ?>
      </div>
      <div class="stat-lbl">Avg Rating</div>
    </div>
  </div>
</div>

<!-- MAIN LAYOUT -->
<div class="container">
  <div class="profile-layout">

    <!-- LEFT: Listings + Reviews -->
    <div>
      <!-- Active Listings -->
      <h2 class="section-title"><i class="fas fa-car-side"></i> Active Listings</h2>
      <?php if (empty($listings)): ?>
      <div class="empty-state">
        <i class="fas fa-car"></i>
        <p>No active listings at the moment.</p>
      </div>
      <?php else: ?>
      <div class="cars-grid">
        <?php foreach ($listings as $car): ?>
        <div class="car-card" onclick="window.location='listing.php?id=<?= (int)$car['id'] ?>'">
          <div class="car-card-img">
            <img src="<?= e(carImageUrl($car['image_path'] ?? '')) ?>"
                 alt="<?= e(($car['make_name']??'').' '.($car['model_name']??'')) ?>"
                 loading="lazy"
                 onerror="this.src='https://via.placeholder.com/400x220/111/333?text=No+Image'">
            <?php if (!empty($car['is_featured'])): ?>
            <span class="featured-badge"><i class="fas fa-bolt"></i> Featured</span>
            <?php endif; ?>
          </div>
          <div class="car-body">
            <div class="car-title"><?= e(($car['make_name']??'').' '.($car['model_name']??'')) ?></div>
            <div class="car-sub"><?= e($car['year']??'') ?> &middot; <?= e($car['city']??'') ?></div>
            <div class="car-specs">
              <?php if (!empty($car['mileage'])): ?>
              <span class="spec"><i class="fas fa-tachometer-alt"></i> <?= formatMileage((int)$car['mileage']) ?></span>
              <?php endif; ?>
              <?php if (!empty($car['fuel_type'])): ?>
              <span class="spec"><i class="fas fa-gas-pump"></i> <?= e($car['fuel_type']) ?></span>
              <?php endif; ?>
              <?php if (!empty($car['transmission'])): ?>
              <span class="spec"><i class="fas fa-cog"></i> <?= e($car['transmission']) ?></span>
              <?php endif; ?>
            </div>
            <div class="car-footer">
              <div class="car-price"><?= formatPKR((float)($car['price']??0)) ?></div>
              <a href="listing.php?id=<?= (int)$car['id'] ?>" class="view-btn" onclick="event.stopPropagation()">
                <i class="fas fa-eye"></i> View
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ((int)$stats['active_listings'] > 12): ?>
      <div style="text-align:center;margin-bottom:40px">
        <a href="listings.php?seller_id=<?= $sellerId ?>" class="btn btn-outline" style="padding:12px 32px">
          <i class="fas fa-th-large"></i> View All <?= number_format((int)$stats['active_listings']) ?> Cars
        </a>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Reviews -->
      <?php if (!empty($reviews)): ?>
      <h2 class="section-title"><i class="fas fa-star"></i> Customer Reviews</h2>
      <div class="reviews-grid">
        <?php foreach ($reviews as $r): ?>
        <div class="review-card">
          <div class="review-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
          <?php if (!empty($r['comment'])): ?>
          <p class="review-text">"<?= e(mb_substr($r['comment'],0,200)) ?>"</p>
          <?php endif; ?>
          <div class="review-author">
            <div class="r-avatar"><?= strtoupper(substr($r['reviewer_name'] ?? 'U',0,1)) ?></div>
            <div>
              <div class="r-name"><?= e($r['reviewer_name'] ?? 'Anonymous') ?></div>
              <div class="r-time"><?= date('M Y', strtotime($r['created_at'])) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Contact Card -->
    <div>
      <div class="contact-card">
        <h3><i class="fas fa-phone-alt"></i> Contact <?= $isDealer ? 'Dealer' : 'Seller' ?></h3>

        <?php if ($wa): ?>
        <a href="https://wa.me/<?= e($wa) ?>?text=Hi%20<?= urlencode($seller['business_name'] ?: $seller['name']) ?>%2C%20I%20saw%20your%20profile%20on%20CarSoko" target="_blank" class="contact-btn contact-btn-wa">
          <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <?php endif; ?>

        <?php if (Auth::check() && Auth::id() !== $sellerId): ?>
        <!-- No direct messaging without a car context — link to listings -->
        <a href="listings.php?seller_id=<?= $sellerId ?>" class="contact-btn contact-btn-list" style="display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none">
          <i class="fas fa-car"></i> Browse Their Cars
        </a>
        <?php elseif (!Auth::check()): ?>
        <a href="login.php?redirect=seller.php?id=<?= $sellerId ?>" class="contact-btn contact-btn-msg" style="display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none">
          <i class="fas fa-sign-in-alt"></i> Sign In to Message
        </a>
        <?php endif; ?>

        <hr class="contact-divider">

        <?php if (!empty($seller['city'])): ?>
        <div class="contact-info-row">
          <i class="fas fa-map-marker-alt"></i>
          <span><?= e($seller['city']) ?>, Pakistan</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($seller['phone']) && Auth::check()): ?>
        <div class="contact-info-row">
          <i class="fas fa-phone"></i>
          <a href="tel:<?= e($seller['phone']) ?>" style="color:var(--muted);transition:color .2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'"><?= e($seller['phone']) ?></a>
        </div>
        <?php elseif (!empty($seller['phone'])): ?>
        <div class="contact-info-row">
          <i class="fas fa-phone"></i>
          <a href="login.php" style="color:var(--muted);transition:color .2s">Sign in to view phone</a>
        </div>
        <?php endif; ?>

        <div class="contact-info-row">
          <i class="fas fa-calendar"></i>
          <span>Member since <?= date('M Y', strtotime($seller['created_at'])) ?></span>
        </div>

        <?php if ((int)$stats['active_listings'] > 0): ?>
        <div class="contact-info-row">
          <i class="fas fa-car"></i>
          <span><?= number_format((int)$stats['active_listings']) ?> active listing<?= $stats['active_listings'] != 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>

        <!-- Social Links -->
        <?php $hasSocial = !empty($seller['facebook_url']) || !empty($seller['instagram_url']) || !empty($wa); ?>
        <?php if ($hasSocial): ?>
        <div class="social-row">
          <?php if (!empty($seller['facebook_url'])): ?>
          <a href="<?= e($seller['facebook_url']) ?>" target="_blank" class="social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <?php endif; ?>
          <?php if (!empty($seller['instagram_url'])): ?>
          <a href="<?= e($seller['instagram_url']) ?>" target="_blank" class="social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
          <?php endif; ?>
          <?php if ($wa): ?>
          <a href="https://wa.me/<?= e($wa) ?>" target="_blank" class="social-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Rating Summary (if reviews exist) -->
      <?php if ($reviewCount > 0): ?>
      <div class="contact-card" style="margin-top:16px">
        <h3><i class="fas fa-star"></i> Rating</h3>
        <div class="rating-summary">
          <div class="rating-number"><?= number_format((float)$avgRating, 1) ?></div>
          <div>
            <div class="stars"><?= str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)) ?></div>
            <div class="rating-sub"><?= $reviewCount ?> review<?= $reviewCount != 1 ? 's' : '' ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /.profile-layout -->
</div><!-- /.container -->

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <span>&copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.</span>
    &nbsp;&middot;&nbsp;
    <a href="privacy.php">Privacy</a>
    &nbsp;&middot;&nbsp;
    <a href="terms.php">Terms</a>
  </div>
</footer>

<script>
// Show mobile actions on small screens
(function(){
  if (window.innerWidth <= 900) {
    var el = document.getElementById('mobileActions');
    if (el) el.style.display = 'flex';
  }
}());
</script>
</body>
</html>