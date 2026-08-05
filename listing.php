<?php
//  CarSoko Pakistan — listing.php
//  Single Car Detail Page
//  Requires: connection.php
// ============================================================
require_once 'connection.php';

// ============================================================
// FETCH CAR
// ============================================================
$carId = (int)($_GET['id'] ?? 0);
if (!$carId) { header('Location: listings.php'); exit; }

$car = DB::selectOne("
    SELECT c.*,
           m.name  AS make_name,  m.slug  AS make_slug,
           (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name,
           (SELECT slug FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_slug,
           u.id    AS seller_id,  u.name  AS seller_name,
           u.role  AS seller_type, u.phone AS seller_phone,
           u.profile_photo AS seller_photo,
           u.city  AS seller_city, u.is_verified_seller,
           u.business_name, u.created_at AS seller_since,
           u.bio   AS seller_bio
    FROM cars c
    JOIN makes  m  ON m.id  = c.make_id
    JOIN users  u  ON u.id  = c.user_id
    WHERE c.id = ? AND c.status IN ('active','sold')
", [$carId]);

if (!$car) {
    http_response_code(404);
    die('<h1 style="font-family:sans-serif;text-align:center;padding:80px;color:#888">Car not found or no longer available.</h1>');
}

// ============================================================
// FETCH IMAGES
// ============================================================
$images = DB::select(
    "SELECT image_path, thumb_path, is_featured FROM car_images WHERE car_id = ? ORDER BY is_featured DESC, sort_order ASC",
    [$carId]
);

// Demo images if none in DB
if (empty($images)) {
    $demoImgs = [
        'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?w=1200&q=85',
        'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=1200&q=85',
        'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=1200&q=85',
        'https://images.unsplash.com/photo-1606016159991-dfe4f2746ad5?w=1200&q=85',
        'https://images.unsplash.com/photo-1619767886558-efdc259cde1a?w=1200&q=85',
        'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=1200&q=85',
    ];
    foreach ($demoImgs as $i => $url) {
        $images[] = ['image_path' => $url, 'thumb_path' => $url, 'is_featured' => ($i === 0 ? 1 : 0)];
    }
}
$featuredImg = carImageUrl($images[0]['image_path']);

// ============================================================
// FETCH REVIEWS
// ============================================================
$reviews = DB::select("
    SELECT r.*, u.name AS reviewer_name, u.profile_photo AS reviewer_photo
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    WHERE r.reviewed_user_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 5
", [$car['seller_id']]);

$avgRating = DB::value(
    "SELECT AVG(rating) FROM reviews WHERE reviewed_user_id = ? AND status = 'approved'",
    [$car['seller_id']]
);
$totalReviews = DB::value(
    "SELECT COUNT(*) FROM reviews WHERE reviewed_user_id = ? AND status = 'approved'",
    [$car['seller_id']]
);

// ============================================================
// SELLER STATS
// ============================================================
$sellerStats = DB::selectOne("
    SELECT COUNT(*) AS total_listings,
           SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_listings,
           SUM(CASE WHEN status='sold'   THEN 1 ELSE 0 END) AS cars_sold
    FROM cars WHERE user_id = ?
", [$car['seller_id']]) ?: ['total_listings'=>0,'active_listings'=>0,'cars_sold'=>0];

// ============================================================
// SIMILAR CARS
// ============================================================
$similar = DB::select("
    SELECT c.id, c.year, c.price, c.mileage, c.fuel_type, c.transmission, c.city,
           m.name AS make_name,
           (SELECT name FROM models WHERE id = c.model_id AND make_id = c.make_id LIMIT 1) AS model_name,
           (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS featured_image
    FROM cars c
    JOIN makes  m  ON m.id  = c.make_id
    WHERE c.status = 'active' AND c.id != ?
      AND (c.make_id = ? OR (c.price BETWEEN ? AND ?))
    GROUP BY c.id
    ORDER BY c.is_featured DESC, ABS(c.price - ?) ASC
    LIMIT 4
", [
    $carId, $car['make_id'],
    $car['price'] * 0.7, $car['price'] * 1.3,
    $car['price']
]);

// Demo similar cars
if (empty($similar)) {
    $similar = [
        ['id'=>2,'make_name'=>'Toyota','model_name'=>'Corolla Axio','year'=>2017,'price'=>1600000,'mileage'=>58000,'fuel_type'=>'petrol','transmission'=>'automatic','city'=>'Karachi','featured_image'=>'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=400&q=80'],
        ['id'=>3,'make_name'=>'Toyota','model_name'=>'Fielder','year'=>2016,'price'=>1450000,'mileage'=>72000,'fuel_type'=>'petrol','transmission'=>'automatic','city'=>'Karachi','featured_image'=>'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=400&q=80'],
        ['id'=>4,'make_name'=>'Mazda','model_name'=>'Axela','year'=>2018,'price'=>1950000,'mileage'=>44000,'fuel_type'=>'petrol','transmission'=>'automatic','city'=>'Lahore','featured_image'=>'https://images.unsplash.com/photo-1609521263047-f8f205293f24?w=400&q=80'],
        ['id'=>5,'make_name'=>'Honda','model_name'=>'Fit','year'=>2017,'price'=>980000,'mileage'=>55000,'fuel_type'=>'petrol','transmission'=>'automatic','city'=>'Islamabad','featured_image'=>'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=400&q=80'],
    ];
}

// ============================================================
// TRACK VIEW (increment counter, avoid spam)
// ============================================================
$viewKey = 'viewed_car_' . $carId;
if (empty($_SESSION[$viewKey])) {
    DB::execute("UPDATE cars SET views = views + 1 WHERE id = ?", [$carId]);
    $_SESSION[$viewKey] = true;
    // Track recently viewed for logged-in users
    if (Auth::check()) {
        DB::execute(
            "INSERT INTO recently_viewed (user_id, car_id) VALUES (?,?) ON DUPLICATE KEY UPDATE viewed_at = NOW()",
            [Auth::id(), $carId]
        );
    }
}

// Saved Cars feature removed
$isSaved = false;

// ============================================================
// CHECK MESSAGE SENT ALREADY (prevent spam)
// ============================================================
$messageSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    CSRF::check();
    if (!Auth::check()) {
        flash('msg_error', 'Please sign in to send messages.');
    } elseif (Auth::id() === $car['seller_id']) {
        flash('msg_error', 'You cannot message yourself.');
    } elseif (!RateLimit::check('msg_send', 5, 300)) {
        flash('msg_error', 'Too many messages. Please wait a few minutes.');
    } else {
        $msgText = cleanInput($_POST['message'] ?? '');
        if (strlen($msgText) < 5) {
            flash('msg_error', 'Message too short.');
        } else {
            DB::beginTransaction();
            try {
                // Get or create conversation
                $convo = DB::selectOne(
                    "SELECT id FROM conversations WHERE car_id = ? AND buyer_id = ?",
                    [$carId, Auth::id()]
                );
                if (!$convo) {
                    $convoId = DB::insert(
                        "INSERT INTO conversations (car_id, buyer_id, seller_id, last_message, last_message_at) VALUES (?,?,?,?,NOW())",
                        [$carId, Auth::id(), $car['seller_id'], $msgText]
                    );
                } else {
                    $convoId = $convo['id'];
                    DB::execute("UPDATE conversations SET last_message=?, last_message_at=NOW() WHERE id=?", [$msgText, $convoId]);
                }
                DB::insert(
                    "INSERT INTO messages (conversation_id, sender_id, message) VALUES (?,?,?)",
                    [$convoId, Auth::id(), $msgText]
                );
                DB::execute("UPDATE cars SET contact_clicks = contact_clicks + 1 WHERE id = ?", [$carId]);
                // Notification for seller
                DB::execute(
                    "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)",
                    [$car['seller_id'], 'new_message', 'New message about your car',
                     Auth::user()['name'] . ' sent you a message about your ' . $car['make_name'] . ' ' . $car['model_name'],
                     'messages.php?car=' . $carId]
                );
                DB::commit();
                $messageSent = true;
                flash('msg_success', 'Message sent! The seller will respond shortly.');
            } catch (Exception $e) {
                DB::rollback();
                flash('msg_error', 'Failed to send message. Please try again.');
            }
        }
    }
    header('Location: listing.php?id=' . $carId . '#contact');
    exit;
}

// ============================================================
// BOOK TEST DRIVE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_drive'])) {
    CSRF::check();
    if (!Auth::check()) {
        flash('book_error', 'Please sign in to book a test drive.');
    } else {
        $date     = cleanInput($_POST['drive_date'] ?? '');
        $time     = cleanInput($_POST['drive_time'] ?? '');
        $location = cleanInput($_POST['drive_location'] ?? '');
        if (!$date || !$time) {
            flash('book_error', 'Please select a date and time.');
        } else {
            DB::execute(
                "INSERT INTO bookings (car_id, buyer_id, seller_id, booking_date, booking_time, location) VALUES (?,?,?,?,?,?)",
                [$carId, Auth::id(), $car['seller_id'], $date, $time, $location]
            );

            // Also send a message to the seller about the test drive
            $buyerName  = Auth::user()['name'];
            $carTitle   = $car['year'] . ' ' . $car['make_name'] . ' ' . $car['model_name'];
            $dateFormat = date('l, j F Y', strtotime($date));
            $msgText    = "Test Drive Request\n" .
                          "Car: $carTitle\n" .
                          "Date: $dateFormat\n" .
                          "Time: $time\n" .
                          ($location ? "Location: $location\n" : '') .
                          "\nPlease reply to confirm or suggest another time.";

            // Get or create conversation
            $convo = DB::selectOne(
                "SELECT id FROM conversations WHERE car_id = ? AND buyer_id = ? AND id > 0",
                [$carId, Auth::id()]
            );
            if (!$convo) {
                DB::execute(
                    "INSERT INTO conversations (car_id, buyer_id, seller_id, last_message, last_message_at) VALUES (?,?,?,?,NOW())",
                    [$carId, Auth::id(), $car['seller_id'], $msgText]
                );
                $newConvo = DB::selectOne(
                    "SELECT id FROM conversations WHERE car_id = ? AND buyer_id = ? AND id > 0 ORDER BY id DESC LIMIT 1",
                    [$carId, Auth::id()]
                );
                $convoId = $newConvo ? (int)$newConvo['id'] : 0;
            } else {
                $convoId = (int)$convo['id'];
                DB::execute("UPDATE conversations SET last_message=?, last_message_at=NOW() WHERE id=?", [$msgText, $convoId]);
            }
            if ($convoId > 0) {
                DB::execute(
                    "INSERT INTO messages (conversation_id, sender_id, message) VALUES (?,?,?)",
                    [$convoId, Auth::id(), $msgText]
                );
                // Notify seller
                DB::execute(
                    "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)",
                    [
                        $car['seller_id'],
                        'test_drive_request',
                        'Test Drive Request — ' . $buyerName,
                        $buyerName . ' wants to test drive your ' . $carTitle . ' on ' . $dateFormat . ' at ' . $time . ($location ? ' — ' . $location : ''),
                        BASE_URL . '/messages.php?conv=' . $convoId
                    ]
                );
            }

            flash('book_success', 'Test drive requested! The seller will be notified and can confirm via messages.');
        }
    }
    header('Location: listing.php?id=' . $carId . '#contact');
    exit;
}

// ============================================================
// META
// ============================================================
$title    = $car['year'] . ' ' . $car['make_name'] . ' ' . $car['model_name'] . ' for Sale in ' . $car['city'] . ' – ' . formatPKR($car['price']);
$metaDesc = $car['year'] . ' ' . $car['make_name'] . ' ' . $car['model_name'] . ', ' . number_format($car['mileage']) . 'km, ' . ucfirst($car['fuel_type']) . ', ' . ucfirst($car['transmission']) . '. Located in ' . $car['city'] . ', Pakistan. Price: ' . formatPKR($car['price']) . '.';

// Features array
$features = !empty($car['features']) ? json_decode($car['features'], true) : [];

// Decode condition label
$conditionLabels = ['new'=>'Brand New','foreign_used'=>'Foreign Used','locally_used'=>'Locally Used','used'=>'Used'];
$condLabel = $conditionLabels[$car['condition']] ?? ucfirst($car['condition']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= e($metaDesc) ?>">
<title><?= e($title) ?> | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ============================================================
   VARIABLES & RESET
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
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}

.container{max-width:1280px;margin:0 auto;padding:0 20px}

/* ============================================================
   NAVBAR
============================================================ */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:28px}
.logo{font-family:var(--font-head);font-size:24px;font-weight:800;display:flex;align-items:center;flex-shrink:0}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:7px;height:7px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 13px;border-radius:8px;transition:all .2s}
.nav-links a:hover{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .25s;border:none;font-family:var(--font-body)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger span{width:20px;height:2px;background:var(--white);border-radius:2px}

/* ============================================================
   BREADCRUMB
============================================================ */
.breadcrumb-bar{background:var(--dark);border-bottom:1px solid var(--border);padding:12px 0}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);flex-wrap:wrap}
.breadcrumb a{color:var(--muted);transition:color .2s}
.breadcrumb a:hover{color:var(--accent)}
.breadcrumb i{font-size:9px;opacity:.5}
.breadcrumb-actions{display:flex;gap:10px;margin-left:auto;flex-shrink:0}
.bc-action{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--muted);background:rgba(255,255,255,.05);border:1px solid var(--border);padding:6px 12px;border-radius:7px;cursor:pointer;transition:all .2s;font-family:var(--font-body)}
.bc-action:hover{color:var(--accent);border-color:rgba(232,184,75,.3)}
.bc-action.saved{color:var(--red);border-color:rgba(239,68,68,.3)}

/* ============================================================
   SOLD BANNER
============================================================ */
.sold-banner{background:linear-gradient(135deg,rgba(239,68,68,.15),rgba(239,68,68,.05));border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:14px 20px;display:flex;align-items:center;gap:12px;margin-bottom:24px;font-size:14px;font-weight:500}
.sold-banner i{color:var(--red);font-size:18px}

/* ============================================================
   MAIN LAYOUT
============================================================ */
.listing-layout{display:grid;grid-template-columns:1fr 360px;gap:32px;padding:28px 0 80px;align-items:start}

/* ============================================================
   GALLERY
============================================================ */
.gallery{position:relative;margin-bottom:24px;border-radius:var(--radius-lg);overflow:hidden;background:#0d0d10}

.gallery-main{position:relative;height:500px;overflow:hidden;cursor:zoom-in}
.gallery-main img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
.gallery-main:hover img{transform:scale(1.03)}

.gallery-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.5) 0%,transparent 40%)}

.gallery-counter{position:absolute;bottom:16px;left:16px;background:rgba(0,0,0,.7);backdrop-filter:blur(10px);color:var(--white);font-size:13px;font-weight:600;padding:6px 14px;border-radius:50px;z-index:2}

.gallery-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;background:rgba(0,0,0,.65);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1);color:var(--white);width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .25s;font-size:16px}
.gallery-nav:hover{background:rgba(232,184,75,.3);border-color:var(--accent);color:var(--accent)}
.gallery-prev{left:14px}
.gallery-next{right:14px}

.gallery-fullscreen{position:absolute;top:14px;right:14px;z-index:3;background:rgba(0,0,0,.65);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1);color:var(--white);width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all .2s}
.gallery-fullscreen:hover{color:var(--accent)}

.gallery-badges{position:absolute;top:14px;left:14px;z-index:3;display:flex;gap:6px}
.badge{font-size:11px;font-weight:700;padding:5px 10px;border-radius:6px;text-transform:uppercase;letter-spacing:.05em}
.badge-featured{background:var(--gradient);color:#0a0a0b}
.badge-urgent{background:rgba(239,68,68,.9);color:#fff}
.badge-sold{background:rgba(239,68,68,.85);color:#fff}

.gallery-thumbs{display:flex;gap:8px;padding:10px;background:rgba(0,0,0,.3);overflow-x:auto;scrollbar-width:thin;scrollbar-color:var(--accent) transparent}
.gallery-thumbs::-webkit-scrollbar{height:4px}
.gallery-thumbs::-webkit-scrollbar-track{background:transparent}
.gallery-thumbs::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px}
.thumb{width:80px;height:58px;flex-shrink:0;border-radius:7px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:all .2s;opacity:.6}
.thumb.active,.thumb:hover{border-color:var(--accent);opacity:1}
.thumb img{width:100%;height:100%;object-fit:cover}

/* ============================================================
   LIGHTBOX
============================================================ */
.lightbox{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.97);display:none;align-items:center;justify-content:center;animation:fadeIn .25s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.lightbox.open{display:flex}
.lightbox-img{max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px}
.lightbox-close{position:absolute;top:20px;right:24px;font-size:28px;color:var(--white);cursor:pointer;opacity:.7;transition:opacity .2s;background:none;border:none}
.lightbox-close:hover{opacity:1}
.lightbox-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.1);border:none;color:var(--white);width:52px;height:52px;border-radius:50%;font-size:20px;cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center}
.lightbox-nav:hover{background:rgba(232,184,75,.3)}
.lightbox-prev{left:20px}
.lightbox-next{right:20px}
.lightbox-count{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);font-size:13px;color:var(--muted)}

/* ============================================================
   TITLE SECTION
============================================================ */
.car-title-block{margin-bottom:24px}
.car-main-title{font-family:var(--font-head);font-size:clamp(22px,3.5vw,32px);font-weight:800;line-height:1.1;margin-bottom:10px}
.car-meta-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--muted)}
.meta-item{display:flex;align-items:center;gap:5px}
.meta-item i{color:var(--accent);font-size:11px}
.meta-sep{opacity:.3}
.seller-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.dealer-badge{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.private-badge{background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.2);color:var(--accent)}
.verified-badge{background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.25);color:var(--blue)}

/* ============================================================
   SPECS PANEL
============================================================ */
.specs-section{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:24px;overflow:hidden}
.specs-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.specs-header h3{font-family:var(--font-head);font-size:16px;font-weight:700}
.specs-header i{color:var(--accent)}

.specs-grid{display:grid;grid-template-columns:repeat(3,1fr)}
.spec-item{padding:16px 20px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);transition:background .2s}
.spec-item:hover{background:rgba(232,184,75,.03)}
.spec-item:nth-child(3n){border-right:none}
.spec-item:nth-last-child(-n+3){border-bottom:none}
.spec-icon{font-size:18px;margin-bottom:6px}
.spec-label{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:3px}
.spec-value{font-family:var(--font-head);font-size:14px;font-weight:600;color:var(--white)}

/* ============================================================
   DESCRIPTION
============================================================ */
.desc-section{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:24px;overflow:hidden}
.section-head{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.section-head h3{font-family:var(--font-head);font-size:16px;font-weight:700}
.section-head i{color:var(--accent)}
.section-body{padding:20px}
.desc-text{color:rgba(245,245,240,.75);line-height:1.8;font-size:14px}
.desc-text.clamped{display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden}
.read-more-btn{margin-top:12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--accent);cursor:pointer;border:none;background:none;font-family:var(--font-body);padding:0}
.read-more-btn:hover{text-decoration:underline}

/* Features tags */
.features-list{display:flex;flex-wrap:wrap;gap:8px;padding:20px}
.feature-tag{display:flex;align-items:center;gap:6px;padding:7px 12px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:7px;font-size:12px;color:var(--green)}
.feature-tag i{font-size:11px}

/* History badges */
.history-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:20px}
.history-item{display:flex;align-items:center;gap:12px;padding:14px;border-radius:var(--radius);border:1px solid var(--border)}
.history-item.good{border-color:rgba(34,197,94,.25);background:rgba(34,197,94,.05)}
.history-item.bad{border-color:rgba(239,68,68,.25);background:rgba(239,68,68,.05)}
.history-icon{font-size:20px;flex-shrink:0}
.history-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px}
.history-val{font-size:13px;font-weight:600}

/* ============================================================
   LOAN CALCULATOR
============================================================ */
.calc-section{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:24px;overflow:hidden}
.calc-body{padding:20px}
.calc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
.calc-field label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:6px;font-weight:600}
.calc-field input,.calc-field select{width:100%;background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:10px 12px;border-radius:8px;font-size:13px;outline:none;font-family:var(--font-body);transition:border-color .2s}
.calc-field input:focus,.calc-field select:focus{border-color:var(--accent)}
.calc-result{background:linear-gradient(135deg,rgba(232,184,75,.1),rgba(255,107,53,.07));border:1px solid rgba(232,184,75,.2);border-radius:var(--radius);padding:18px;text-align:center}
.calc-monthly{font-family:var(--font-head);font-size:32px;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1}
.calc-label{font-size:12px;color:var(--muted);margin-top:4px}
.calc-breakdown{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.calc-item{text-align:center;padding:10px;background:rgba(0,0,0,.25);border-radius:8px}
.calc-item-val{font-size:14px;font-weight:600;color:var(--white)}
.calc-item-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}

/* ============================================================
   REVIEWS
============================================================ */
.review-summary{display:flex;align-items:center;gap:20px;padding:20px;border-bottom:1px solid var(--border)}
.review-big-score{font-family:var(--font-head);font-size:52px;font-weight:800;line-height:1;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.review-stars{color:var(--accent);font-size:18px;letter-spacing:2px;margin:4px 0}
.review-count{font-size:13px;color:var(--muted)}
.review-bars{flex:1}
.review-bar-row{display:flex;align-items:center;gap:8px;margin-bottom:5px}
.review-bar-label{font-size:11px;color:var(--muted);width:10px;text-align:right}
.review-bar-track{flex:1;height:5px;background:rgba(255,255,255,.07);border-radius:50px;overflow:hidden}
.review-bar-fill{height:100%;background:var(--gradient);border-radius:50px;transition:width .6s ease}

.review-card{padding:18px 20px;border-bottom:1px solid var(--border)}
.review-card:last-child{border-bottom:none}
.review-header{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.reviewer-avatar{width:38px;height:38px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:14px;color:#0a0a0b;flex-shrink:0}
.reviewer-name{font-size:14px;font-weight:600}
.reviewer-date{font-size:11px;color:var(--muted)}
.review-stars-sm{color:var(--accent);font-size:12px;letter-spacing:1px}
.review-text{font-size:13px;color:rgba(245,245,240,.7);line-height:1.7}

/* ============================================================
   STICKY RIGHT PANEL
============================================================ */
.sticky-panel{position:sticky;top:84px;display:flex;flex-direction:column;gap:16px}

/* Price card */
.price-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.price-card-head{padding:20px 20px 14px;border-bottom:1px solid var(--border)}
.price-main{font-family:var(--font-head);font-size:36px;font-weight:800;line-height:1;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.price-sub{display:flex;align-items:center;gap:10px;margin-top:6px;font-size:12px;color:var(--muted);flex-wrap:wrap}
.price-neg-tag{display:inline-flex;align-items:center;gap:5px;color:var(--green);font-weight:600}
.price-views{display:flex;align-items:center;gap:5px}

.price-card-body{padding:16px 20px}

/* Contact buttons */
.contact-btns{display:flex;flex-direction:column;gap:10px;margin-bottom:16px}
.cta-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;border:none;font-family:var(--font-body);width:100%}
.cta-primary{background:var(--gradient);color:#0a0a0b;box-shadow:0 4px 20px rgba(232,184,75,.25)}
.cta-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(232,184,75,.45)}
.cta-whatsapp{background:#25D366;color:#fff;box-shadow:0 4px 16px rgba(37,211,102,.2)}
.cta-whatsapp:hover{background:#20BA5A;transform:translateY(-2px)}
.cta-call{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:var(--blue)}
.cta-call:hover{background:rgba(59,130,246,.18)}
.cta-msg{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--white)}
.cta-msg:hover{background:rgba(255,255,255,.08)}

/* Quick stats in price card */
.price-quick-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding-top:14px;border-top:1px solid var(--border)}
.qs-item{text-align:center;padding:10px;background:rgba(0,0,0,.25);border-radius:8px}
.qs-val{font-family:var(--font-head);font-size:15px;font-weight:700;color:var(--white)}
.qs-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-top:2px}

/* ============================================================
   SELLER CARD
============================================================ */
.seller-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.seller-card-head{padding:18px 20px;border-bottom:1px solid var(--border)}
.seller-card-head h4{font-family:var(--font-head);font-size:14px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em}
.seller-info{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border)}
.seller-avatar{width:52px;height:52px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:800;font-size:20px;color:#0a0a0b;flex-shrink:0;overflow:hidden}
.seller-avatar img{width:100%;height:100%;object-fit:cover}
.seller-name-block{}
.seller-display-name{font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:3px}
.seller-badges{display:flex;gap:5px;flex-wrap:wrap}
.seller-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border)}
.seller-stat{background:var(--card-bg);padding:14px 12px;text-align:center}
.seller-stat-val{font-family:var(--font-head);font-size:18px;font-weight:700;color:var(--white)}
.seller-stat-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-top:2px}
.seller-actions{padding:14px 16px;display:flex;gap:8px}
.seller-action-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;font-family:var(--font-body)}
.view-profile-btn{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--white)}
.view-profile-btn:hover{background:rgba(255,255,255,.09)}

/* ============================================================
   CONTACT / MESSAGE FORM
============================================================ */
.contact-section{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.form-field{margin-bottom:14px}
.form-field label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:6px}
.form-field textarea,.form-field input,.form-field select{width:100%;background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:11px 14px;border-radius:8px;font-size:14px;font-family:var(--font-body);outline:none;transition:border-color .2s;resize:vertical}
.form-field textarea:focus,.form-field input:focus,.form-field select:focus{border-color:var(--accent)}
.form-submit{width:100%;padding:13px;background:var(--gradient);color:#0a0a0b;font-weight:700;font-size:15px;border:none;border-radius:8px;cursor:pointer;font-family:var(--font-body);transition:all .25s}
.form-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.35)}
.form-tabs{display:flex;gap:4px;padding:16px 20px 0;border-bottom:1px solid var(--border)}
.form-tab{flex:1;padding:10px;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;cursor:pointer;border:none;background:none;color:var(--muted);font-family:var(--font-body);transition:all .2s;border-bottom:2px solid transparent;text-align:center}
.form-tab.active{color:var(--accent);border-bottom-color:var(--accent);background:rgba(232,184,75,.05)}
.form-panel{padding:18px 20px;display:none}
.form-panel.active{display:block}

/* Alert messages */
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}

/* ============================================================
   SIMILAR CARS
============================================================ */
.similar-section{padding:40px 0 60px}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.section-tag{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:8px}
.section-tag::before{content:'';width:20px;height:2px;background:var(--gradient);border-radius:2px}
.section-title{font-family:var(--font-head);font-size:clamp(22px,3vw,30px);font-weight:700}
.section-title span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.view-all{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--accent);border:1px solid rgba(232,184,75,.3);padding:8px 18px;border-radius:50px;transition:all .2s}
.view-all:hover{background:rgba(232,184,75,.1)}

.similar-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.sim-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;cursor:pointer;transition:all .3s}
.sim-card:hover{transform:translateY(-5px);border-color:rgba(232,184,75,.25);box-shadow:0 16px 40px rgba(0,0,0,.4)}
.sim-img{height:160px;overflow:hidden;background:#111}
.sim-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.sim-card:hover .sim-img img{transform:scale(1.07)}
.sim-body{padding:14px}
.sim-title{font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sim-sub{font-size:12px;color:var(--muted);margin-bottom:8px}
.sim-specs{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.sim-spec{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:3px}
.sim-spec i{color:var(--accent);font-size:10px}
.sim-price{font-family:var(--font-head);font-size:16px;font-weight:700}

/* ============================================================
   REPORT LINK
============================================================ */
.report-link{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);cursor:pointer;background:none;border:none;font-family:var(--font-body);padding:14px 20px;transition:color .2s;width:100%}
.report-link:hover{color:var(--red)}

/* ============================================================
   MOBILE STICKY BAR
============================================================ */
.mobile-sticky{display:none;position:fixed;bottom:0;left:0;right:0;z-index:500;background:rgba(17,17,20,.97);backdrop-filter:blur(20px);border-top:1px solid var(--border);padding:12px 16px}
.mobile-sticky-price{font-family:var(--font-head);font-size:22px;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:8px}
.mobile-sticky-btns{display:flex;gap:8px}
.mobile-sticky-btns .cta-btn{flex:1;font-size:13px;padding:12px}

/* ============================================================
   PREVENT HORIZONTAL OVERFLOW
============================================================ */
html,body{max-width:100%;overflow-x:hidden}

/* ============================================================
   RESPONSIVE — TABLET (≤ 1100px)
============================================================ */
@media(max-width:1100px){
    .listing-layout{grid-template-columns:1fr;gap:20px;padding:20px 0 80px}
    .sticky-panel{position:static;display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .price-card{grid-column:1/-1}
    .seller-card,.contact-section{min-width:0}
    .similar-grid{grid-template-columns:repeat(2,1fr)}
    .mobile-sticky{display:block}
    body{padding-bottom:110px}
    .specs-grid{grid-template-columns:repeat(3,1fr)}
    .nav-links{display:none;position:absolute;top:64px;left:0;width:100%;background:var(--dark);flex-direction:column;padding:20px;gap:10px;border-bottom:1px solid var(--border);z-index:201}
    .nav-links.active{display:flex}
    .hamburger{display:flex}
    .breadcrumb-actions{gap:6px}
    .bc-action{padding:5px 10px;font-size:11px}
    .container{padding:0 16px}
}

/* ============================================================
   RESPONSIVE — MOBILE (≤ 768px)
============================================================ */
@media(max-width:768px){
    .container{padding:0 12px}
    .navbar .container{height:56px;gap:10px}
    .logo{font-size:20px}
    .btn{padding:8px 14px;font-size:12px}
    .breadcrumb{font-size:11px;gap:5px}
    .breadcrumb-actions{gap:5px}
    .bc-action{padding:5px 9px;font-size:11px}
    .bc-action span{display:none}
    .gallery-main{height:240px}
    .gallery-nav{width:36px;height:36px;font-size:14px}
    .thumb{width:60px;height:44px}
    .listing-layout{gap:16px;padding:16px 0 80px}
    .sticky-panel{grid-template-columns:1fr;gap:12px}
    .car-main-title{font-size:20px}
    .car-meta-row{gap:8px;font-size:12px}
    .specs-grid{grid-template-columns:repeat(2,1fr)}
    .spec-item{padding:12px 14px}
    .spec-icon{font-size:16px}
    .spec-value{font-size:13px}
    .spec-item:nth-child(2n){border-right:none}
    .spec-item:nth-child(3n){border-right:1px solid var(--border)}
    .spec-item:nth-last-child(-n+3){border-bottom:1px solid var(--border)}
    .spec-item:nth-last-child(-n+2){border-bottom:none}
    .section-body{padding:14px}
    .section-head{padding:14px 16px}
    .specs-header{padding:14px 16px}
    .history-grid{grid-template-columns:1fr}
    .calc-grid{grid-template-columns:1fr}
    .calc-body{padding:14px}
    .calc-monthly{font-size:26px}
    .calc-breakdown{grid-template-columns:repeat(3,1fr);gap:6px}
    .review-summary{flex-direction:column;align-items:flex-start;gap:12px}
    .review-big-score{font-size:40px}
    .price-main{font-size:28px}
    .cta-btn{padding:12px;font-size:14px}
    .price-card-body{padding:14px}
    .price-card-head{padding:16px}
    .seller-stat-val{font-size:15px}
    .seller-actions{flex-direction:column;padding:12px}
    .seller-action-btn{justify-content:center}
    .form-panel{padding:14px}
    .form-tab{font-size:12px;padding:8px}
    .form-submit{font-size:14px;padding:12px}
    .similar-section{padding:24px 0 40px}
    .similar-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .sim-img{height:130px}
    .sim-body{padding:10px}
    .sim-title{font-size:13px}
    .sim-price{font-size:14px}
    .section-title{font-size:20px}
    .mobile-sticky{padding:10px 12px}
    .mobile-sticky-price{font-size:18px;margin-bottom:6px}
    .mobile-sticky-btns .cta-btn{font-size:12px;padding:10px 8px}
}

/* ============================================================
   RESPONSIVE — SMALL MOBILE (≤ 480px)
============================================================ */
@media(max-width:480px){
    .container{padding:0 10px}
    .car-main-title{font-size:18px}
    .gallery-main{height:210px}
    .thumb{width:52px;height:38px}
    .specs-grid{grid-template-columns:repeat(2,1fr)}
    .similar-grid{grid-template-columns:repeat(2,1fr);gap:8px}
    .sim-img{height:110px}
    .sim-specs{display:none}
    .breadcrumb{display:none}
    .price-quick-stats{grid-template-columns:repeat(2,1fr)}
    .calc-breakdown{grid-template-columns:repeat(3,1fr)}
    .review-summary{padding:14px}
    .features-list{padding:12px}
    .feature-tag{font-size:11px;padding:5px 9px}
    .history-grid{padding:12px}
    .seller-stats{grid-template-columns:repeat(3,1fr)}
    .mobile-sticky-btns .cta-btn{font-size:11px;gap:4px;padding:9px 6px}
}

/* Reveal animations */
.reveal{opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease}
.reveal.visible{opacity:1;transform:none}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo"><span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div></a>
        <div class="nav-links" id="mobileNav">
            <a href="listings.php">Browse Cars</a>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="listings.php?seller=dealer">Dealers</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="blog.php">Blog</a>
        </div>
        <div class="nav-right">
            <?php if (Auth::check()): ?>
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-user"></i> Dashboard</a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <div class="hamburger" onclick="document.getElementById('mobileNav').classList.toggle('active')"><span></span><span></span><span></span></div>
        </div>
    </div>
</nav>

<!-- BREADCRUMB -->
<div class="breadcrumb-bar">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="listings.php">Cars for Sale</a>
            <i class="fas fa-chevron-right"></i>
            <a href="listings.php?make=<?= e($car['make_slug']) ?>"><?= e($car['make_name']) ?></a>
            <i class="fas fa-chevron-right"></i>
            <a href="listings.php?make=<?= e($car['make_slug']) ?>&model=<?= e($car['model_slug']) ?>"><?= e($car['model_name']) ?></a>
            <i class="fas fa-chevron-right"></i>
            <span style="color:var(--white)"><?= e($car['year']) ?></span>
        </div>
        <div class="breadcrumb-actions">
            <button class="bc-action" onclick="sharecar()"><i class="fas fa-share-alt"></i> Share</button>
            <a href="compare.php?ids=<?= $carId ?>" class="bc-action"><i class="fas fa-balance-scale"></i> Compare</a>
        </div>
    </div>
</div>

<!-- MAIN -->
<div class="container">
    <div class="listing-layout">

        <!-- ====================================================
             LEFT COLUMN
        ==================================================== -->
        <div>

            <!-- SOLD BANNER -->
            <?php if ($car['status'] === 'sold'): ?>
            <div class="sold-banner">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>This car has been sold.</strong>
                    Browse similar cars below or <a href="listings.php?make=<?= e($car['make_slug']) ?>" style="color:var(--accent);text-decoration:underline">search <?= e($car['make_name']) ?> listings</a>.
                </div>
            </div>
            <?php endif; ?>

            <!-- GALLERY -->
            <div class="gallery reveal">
                <div class="gallery-main" id="galleryMain" onclick="openLightbox(currentImg)">
                    <img id="mainImg" src="<?= e($featuredImg) ?>" alt="<?= e($car['make_name'].' '.$car['model_name']) ?>">
                    <div class="gallery-overlay"></div>

                    <!-- Badges -->
                    <div class="gallery-badges">
                        <?php if ($car['is_featured']): ?><span class="badge badge-featured"><i class="fas fa-bolt"></i> Featured</span><?php endif; ?>
                        <?php if ($car['is_urgent']):   ?><span class="badge badge-urgent"><i class="fas fa-fire"></i> Urgent</span><?php endif; ?>
                        <?php if ($car['status']==='sold'): ?><span class="badge badge-sold">Sold</span><?php endif; ?>
                    </div>

                    <!-- Counter -->
                    <div class="gallery-counter">
                        <span id="imgCurrent">1</span> / <span id="imgTotal"><?= count($images) ?></span>
                    </div>

                    <!-- Nav -->
                    <?php if (count($images) > 1): ?>
                    <button class="gallery-nav gallery-prev" onclick="event.stopPropagation();prevImg()"><i class="fas fa-chevron-left"></i></button>
                    <button class="gallery-nav gallery-next" onclick="event.stopPropagation();nextImg()"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>

                    <button class="gallery-fullscreen" onclick="event.stopPropagation();openLightbox(currentImg)" title="Fullscreen">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>

                <!-- Thumbnails -->
                <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs" id="thumbStrip">
                    <?php foreach ($images as $i => $img): ?>
                    <div class="thumb <?= $i===0?'active':'' ?>" onclick="goToImg(<?= $i ?>)" id="thumb-<?= $i ?>">
                        <img src="<?= e(carImageUrl($img['thumb_path'] ?: $img['image_path'])) ?>" alt="Photo <?= $i+1 ?>" loading="lazy">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TITLE -->
            <div class="car-title-block reveal">
                <h1 class="car-main-title"><?= e($car['year'].' '.$car['make_name'].' '.$car['model_name']) ?><?= $car['variant'] ? ' ' . e($car['variant']) : '' ?></h1>
                <div class="car-meta-row">
                    <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= e($car['city']) ?><?= $car['county'] ? ', '.e($car['county']) : '' ?></span>
                    <span class="meta-sep">·</span>
                    <span class="meta-item"><i class="fas fa-clock"></i> Listed <?= timeAgo($car['created_at']) ?></span>
                    <span class="meta-sep">·</span>
                    <span class="meta-item"><i class="fas fa-eye"></i> <?= number_format($car['views']) ?> views</span>
                    <span class="meta-sep">·</span>
                    <span class="seller-badge <?= $car['seller_type']==='dealer'?'dealer-badge':'private-badge' ?>">
                        <i class="fas fa-<?= $car['seller_type']==='dealer'?'store':'user' ?>"></i>
                        <?= $car['seller_type']==='dealer'?'Dealer':'Private Seller' ?>
                    </span>
                    <?php if ($car['is_verified_seller']): ?>
                    <span class="seller-badge verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KEY SPECS -->
            <div class="specs-section reveal">
                <div class="specs-header">
                    <i class="fas fa-tachometer-alt"></i>
                    <h3>Key Specifications</h3>
                </div>
                <div class="specs-grid">
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="spec-label">Year</div>
                        <div class="spec-value"><?= $car['year'] ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-road"></i></div>
                        <div class="spec-label">Mileage</div>
                        <div class="spec-value"><?= number_format($car['mileage']) ?> km</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-gas-pump"></i></div>
                        <div class="spec-label">Fuel Type</div>
                        <div class="spec-value"><?= ucfirst($car['fuel_type']) ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-cog"></i></div>
                        <div class="spec-label">Transmission</div>
                        <div class="spec-value"><?= ucfirst($car['transmission']) ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-car"></i></div>
                        <div class="spec-label">Body Type</div>
                        <div class="spec-value"><?= ucfirst($car['body_type']) ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-palette"></i></div>
                        <div class="spec-label">Color</div>
                        <div class="spec-value"><?= e($car['color'] ?: 'N/A') ?></div>
                    </div>
                    <?php if ($car['engine_cc']): ?>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-wrench"></i></div>
                        <div class="spec-label">Engine</div>
                        <div class="spec-value"><?= number_format($car['engine_cc']) ?>cc</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($car['horsepower']): ?>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-bolt"></i></div>
                        <div class="spec-label">Horsepower</div>
                        <div class="spec-value"><?= $car['horsepower'] ?> hp</div>
                    </div>
                    <?php endif; ?>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-circle-notch"></i></div>
                        <div class="spec-label">Drive Type</div>
                        <div class="spec-value"><?= strtoupper($car['drive_type'] ?? '2WD') ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-door-open"></i></div>
                        <div class="spec-label">Doors</div>
                        <div class="spec-value"><?= $car['doors'] ?? 4 ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-chair"></i></div>
                        <div class="spec-label">Seats</div>
                        <div class="spec-value"><?= $car['seats'] ?? 5 ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div class="spec-label">Condition</div>
                        <div class="spec-value"><?= $condLabel ?></div>
                    </div>
                    <?php if ($car['vin']): ?>
                    <div class="spec-item" style="grid-column:1/-1">
                        <div class="spec-icon"><i class="fas fa-key"></i></div>
                        <div class="spec-label">VIN</div>
                        <div class="spec-value" style="font-size:13px;letter-spacing:.05em"><?= e($car['vin']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DESCRIPTION -->
            <?php if ($car['description']): ?>
            <div class="desc-section reveal">
                <div class="section-head">
                    <i class="fas fa-align-left"></i>
                    <h3>Description</h3>
                </div>
                <div class="section-body">
                    <p class="desc-text clamped" id="descText"><?= nl2br(e($car['description'])) ?></p>
                    <button class="read-more-btn" id="readMoreBtn" onclick="toggleDesc()">
                        <i class="fas fa-chevron-down"></i> Read More
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- FEATURES -->
            <?php if (!empty($features)): ?>
            <div class="desc-section reveal">
                <div class="section-head">
                    <i class="fas fa-star"></i>
                    <h3>Features & Extras</h3>
                </div>
                <div class="features-list">
                    <?php foreach ($features as $feat): ?>
                    <span class="feature-tag"><i class="fas fa-check"></i> <?= e($feat) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Demo features -->
            <div class="desc-section reveal">
                <div class="section-head">
                    <i class="fas fa-star"></i>
                    <h3>Features & Extras</h3>
                </div>
                <div class="features-list">
                    <?php foreach (['Alloy Wheels','Rear Camera','Sunroof','Push Start','Apple CarPlay','Lane Assist','Cruise Control','Leather Seats','Heated Mirrors','Auto Headlights'] as $f): ?>
                    <span class="feature-tag"><i class="fas fa-check"></i> <?= $f ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- HISTORY -->
            <div class="desc-section reveal">
                <div class="section-head">
                    <i class="fas fa-history"></i>
                    <h3>Vehicle History</h3>
                </div>
                <div class="history-grid">
                    <div class="history-item <?= $car['has_accident_history']?'bad':'good' ?>">
                        <div class="history-icon"><i class="fas fa-<?= $car['has_accident_history']?'exclamation-triangle':'check-circle' ?>"></i></div>
                        <div>
                            <div class="history-label">Accident History</div>
                            <div class="history-val" style="color:<?= $car['has_accident_history']?'var(--red)':'var(--green)' ?>">
                                <?= $car['has_accident_history']?'Has Accident History':'No Accidents Reported' ?>
                            </div>
                        </div>
                    </div>
                    <div class="history-item <?= $car['has_service_history']?'good':'bad' ?>">
                        <div class="history-icon"><i class="fas fa-file-medical"></i></div>
                        <div>
                            <div class="history-label">Service History</div>
                            <div class="history-val" style="color:<?= $car['has_service_history']?'var(--green)':'var(--muted)' ?>">
                                <?= $car['has_service_history']?'Full Service History':'Not Available' ?>
                            </div>
                        </div>
                    </div>
                    <div class="history-item good">
                        <div class="history-icon"><i class="fas fa-file-alt"></i></div>
                        <div>
                            <div class="history-label">Registration</div>
                            <div class="history-val"><?= ucfirst(str_replace('_',' ',$car['registration_status']??'registered')) ?></div>
                        </div>
                    </div>
                    <?php if ($car['import_country']): ?>
                    <div class="history-item good">
                        <div class="history-icon">🌍</div>
                        <div>
                            <div class="history-label">Imported From</div>
                            <div class="history-val"><?= e($car['import_country']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LOAN CALCULATOR -->
            <div class="calc-section reveal" id="calculator">
                <div class="section-head">
                    <i class="fas fa-calculator"></i>
                    <h3>Loan Calculator</h3>
                </div>
                <div class="calc-body">
                    <div class="calc-grid">
                        <div class="calc-field">
                            <label>Car Price (Rs.)</label>
                            <input type="number" id="calcPrice" value="<?= $car['price'] ?>" oninput="calculateLoan()" step="50000">
                        </div>
                        <div class="calc-field">
                            <label>Down Payment (Rs.)</label>
                            <input type="number" id="calcDown" value="<?= round($car['price'] * 0.2) ?>" oninput="calculateLoan()" step="10000">
                        </div>
                        <div class="calc-field">
                            <label>Interest Rate (%)</label>
                            <input type="number" id="calcRate" value="14" step="0.5" min="1" max="50" oninput="calculateLoan()">
                        </div>
                        <div class="calc-field">
                            <label>Loan Period</label>
                            <select id="calcTerm" onchange="calculateLoan()">
                                <option value="12">12 months (1 year)</option>
                                <option value="24">24 months (2 years)</option>
                                <option value="36" selected>36 months (3 years)</option>
                                <option value="48">48 months (4 years)</option>
                                <option value="60">60 months (5 years)</option>
                            </select>
                        </div>
                    </div>
                    <div class="calc-result">
                        <div class="calc-monthly" id="calcMonthly">Rs. 0</div>
                        <div class="calc-label">Estimated Monthly Payment</div>
                        <div class="calc-breakdown">
                            <div class="calc-item">
                                <div class="calc-item-val" id="calcLoanAmt">—</div>
                                <div class="calc-item-label">Loan Amount</div>
                            </div>
                            <div class="calc-item">
                                <div class="calc-item-val" id="calcTotalInt">—</div>
                                <div class="calc-item-label">Total Interest</div>
                            </div>
                            <div class="calc-item">
                                <div class="calc-item-val" id="calcTotal">—</div>
                                <div class="calc-item-label">Total Payable</div>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:11px;color:var(--muted);margin-top:12px;text-align:center"><i class="fas fa-info-circle"></i> Indicative only. Contact a bank or financial institution for official rates.</p>
                </div>
            </div>

            <!-- REVIEWS -->
            <?php if ($reviews || true): ?>
            <div class="desc-section reveal">
                <div class="section-head">
                    <i class="fas fa-star"></i>
                    <h3>Seller Reviews</h3>
                    <?php if ($avgRating): ?>
                    <span style="margin-left:auto;font-size:13px;color:var(--accent);font-weight:600"><?= number_format($avgRating,1) ?> ★ (<?= $totalReviews ?>)</span>
                    <?php endif; ?>
                </div>

                <?php
                // Demo reviews if none
                $displayReviews = $reviews ?: [
                    ['reviewer_name'=>'Ahmad S.','rating'=>5,'comment'=>'Excellent seller, car was exactly as described. Very honest and transparent about the condition.','created_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'reviewer_photo'=>null,'is_verified'=>1],
                    ['reviewer_name'=>'Fatima Z.','rating'=>4,'comment'=>'Good communication, responded quickly. Test drive was easy to arrange. Minor issue with one tyre but seller sorted it.','created_at'=>date('Y-m-d H:i:s',strtotime('-3 weeks')),'reviewer_photo'=>null,'is_verified'=>1],
                    ['reviewer_name'=>'Usman R.','rating'=>5,'comment'=>'Would definitely buy from this seller again. Professional and trustworthy.','created_at'=>date('Y-m-d H:i:s',strtotime('-2 months')),'reviewer_photo'=>null,'is_verified'=>0],
                ];
                $demoAvg = $avgRating ?: 4.7;
                ?>

                <!-- Rating Summary -->
                <div class="review-summary">
                    <div>
                        <div class="review-big-score"><?= number_format($demoAvg,1) ?></div>
                        <div class="review-stars">
                            <?php for ($s=1;$s<=5;$s++) echo $s <= round($demoAvg) ? '★' : '☆'; ?>
                        </div>
                        <div class="review-count"><?= count($displayReviews) + (int)$totalReviews ?> reviews</div>
                    </div>
                    <div class="review-bars">
                        <?php foreach ([5,4,3,2,1] as $star):
                            $pct = $star >= 4 ? ($star===5?70:20) : ($star===3?7:3);
                        ?>
                        <div class="review-bar-row">
                            <span class="review-bar-label"><?= $star ?></span>
                            <div class="review-bar-track"><div class="review-bar-fill" style="width:<?= $pct ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Individual reviews -->
                <?php foreach ($displayReviews as $rev): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-avatar">
                            <?php if (!empty($rev['reviewer_photo'])): ?>
                            <img src="<?= e($rev['reviewer_photo']) ?>" alt="">
                            <?php else: ?>
                            <?= strtoupper(substr($rev['reviewer_name'],0,1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="reviewer-name"><?= e($rev['reviewer_name']) ?> <?= !empty($rev['is_verified'])?'<span style="color:var(--blue);font-size:11px"><i class="fas fa-check-circle"></i> Verified</span>':'' ?></div>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="review-stars-sm"><?php for($s=0;$s<$rev['rating'];$s++) echo '★'; ?></div>
                                <div class="reviewer-date"><?= timeAgo($rev['created_at']) ?></div>
                            </div>
                        </div>
                    </div>
                    <p class="review-text"><?= e($rev['comment']) ?></p>
                </div>
                <?php endforeach; ?>

                <?php if (Auth::check() && Auth::id() !== $car['seller_id']): ?>
                <div style="padding:16px 20px;border-top:1px solid var(--border)">
                    <a href="review.php?seller=<?= $car['seller_id'] ?>&car=<?= $carId ?>" style="font-size:13px;color:var(--accent);display:inline-flex;align-items:center;gap:6px">
                        <i class="fas fa-star"></i> Write a Review
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.left column -->

        <!-- ====================================================
             RIGHT COLUMN (Sticky Panel)
        ==================================================== -->
        <div class="sticky-panel">

            <!-- PRICE CARD -->
            <div class="price-card reveal">
                <div class="price-card-head">
                    <div class="price-main"><?= formatPKR($car['price']) ?></div>
                    <div class="price-sub">
                        <?php if ($car['price_negotiable']): ?>
                        <span class="price-neg-tag"><i class="fas fa-handshake"></i> Negotiable</span>
                        <span>·</span>
                        <?php endif; ?>
                        <span class="price-views"><i class="fas fa-eye" style="color:var(--accent)"></i> <?= number_format($car['views']) ?> views</span>
                        <span>·</span>
                        <span><i class="fas fa-clock"></i> <?= timeAgo($car['created_at']) ?></span>
                    </div>
                </div>
                <div class="price-card-body">
                    <div class="contact-btns">
                        <?php if ($car['seller_phone']): ?>
                        <a href="tel:<?= e($car['seller_phone']) ?>" class="cta-btn cta-call" onclick="trackContact('call')">
                            <i class="fas fa-phone"></i> Call Seller
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$car['seller_phone']) ?>?text=<?= urlencode('Hi, I\'m interested in your '.$car['year'].' '.$car['make_name'].' '.$car['model_name'].' listed on CarSoko for '.formatPKR($car['price'])) ?>"
                           target="_blank" class="cta-btn cta-whatsapp" onclick="trackContact('whatsapp')">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <?php endif; ?>
                        <button class="cta-btn cta-msg" onclick="scrollToContact()">
                            <i class="fas fa-comment"></i> Send Message
                        </button>
                        <button class="cta-btn cta-primary" onclick="scrollToBooking()" style="padding:12px">
                            <i class="fas fa-calendar-check"></i> Book Test Drive
                        </button>
                    </div>

                    <div class="price-quick-stats">
                        <div class="qs-item">
                            <div class="qs-val"><?= $car['year'] ?></div>
                            <div class="qs-label">Year</div>
                        </div>
                        <div class="qs-item">
                            <div class="qs-val"><?= number_format($car['mileage']/1000,0) ?>K</div>
                            <div class="qs-label">Kilometres</div>
                        </div>
                        <div class="qs-item">
                            <div class="qs-val"><?= ucfirst($car['fuel_type']) ?></div>
                            <div class="qs-label">Fuel</div>
                        </div>
                        <div class="qs-item">
                            <div class="qs-val"><?= ucfirst($car['transmission']) ?></div>
                            <div class="qs-label">Gearbox</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SELLER CARD -->
            <div class="seller-card reveal">
                <div class="seller-card-head">
                    <h4><?= $car['seller_type']==='dealer'?'Dealer':'Private Seller' ?></h4>
                </div>
                <div class="seller-info">
                    <div class="seller-avatar">
                        <?php if ($car['seller_photo']): ?>
                        <img src="<?= e($car['seller_photo']) ?>" alt="">
                        <?php else: ?>
                        <?= strtoupper(substr($car['seller_name'],0,1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="seller-name-block">
                        <div class="seller-display-name"><?= e($car['business_name'] ?: $car['seller_name']) ?></div>
                        <div class="seller-badges">
                            <?php if ($car['is_verified_seller']): ?>
                            <span class="seller-badge verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                            <?php endif; ?>
                            <?php if ($car['seller_type']==='dealer'): ?>
                            <span class="seller-badge dealer-badge"><i class="fas fa-store"></i> Dealer</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px;color:var(--muted);margin-top:4px">
                            <i class="fas fa-map-marker-alt" style="color:var(--accent)"></i> <?= e($car['seller_city'] ?: $car['city']) ?>
                            &nbsp;·&nbsp;<i class="fas fa-calendar"></i> Member since <?= date('M Y', strtotime($car['seller_since'])) ?>
                        </div>
                    </div>
                </div>
                <div class="seller-stats">
                    <div class="seller-stat">
                        <div class="seller-stat-val"><?= number_format($sellerStats['total_listings']) ?></div>
                        <div class="seller-stat-label">Listings</div>
                    </div>
                    <div class="seller-stat">
                        <div class="seller-stat-val"><?= number_format($sellerStats['cars_sold']) ?></div>
                        <div class="seller-stat-label">Sold</div>
                    </div>
                    <div class="seller-stat">
                        <div class="seller-stat-val"><?= $demoAvg ?? '—' ?>★</div>
                        <div class="seller-stat-label">Rating</div>
                    </div>
                </div>
                <div class="seller-actions">
                    <a href="seller.php?id=<?= $car['seller_id'] ?>" class="seller-action-btn view-profile-btn">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                    <a href="listings.php?seller_id=<?= $car['seller_id'] ?>" class="seller-action-btn view-profile-btn">
                        <i class="fas fa-car"></i> All Listings (<?= $sellerStats['active_listings'] ?>)
                    </a>
                </div>
            </div>

            <!-- CONTACT / MESSAGE FORM -->
            <div class="contact-section reveal" id="contact">
                <?php if ($msg = flash('msg_success')): ?>
                <div class="alert alert-success" style="margin:16px 20px 0"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
                <?php elseif ($msg = flash('msg_error')): ?>
                <div class="alert alert-error" style="margin:16px 20px 0"><i class="fas fa-exclamation-circle"></i> <?= e($msg) ?></div>
                <?php endif; ?>

                <?php if ($msg = flash('book_success')): ?>
                <div class="alert alert-success" style="margin:16px 20px 0"><i class="fas fa-calendar-check"></i> <?= e($msg) ?></div>
                <?php elseif ($msg = flash('book_error')): ?>
                <div class="alert alert-error" style="margin:16px 20px 0"><i class="fas fa-exclamation-circle"></i> <?= e($msg) ?></div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="form-tabs">
                    <button class="form-tab active" onclick="switchTab(this,'msg')"><i class="fas fa-comment"></i> Message</button>
                    <button class="form-tab" onclick="switchTab(this,'drive')"><i class="fas fa-calendar"></i> Test Drive</button>
                </div>

                <!-- Message Panel -->
                <div class="form-panel active" id="panel-msg">
                    <?php if (!Auth::check()): ?>
                    <div style="text-align:center;padding:16px 0">
                        <div style="font-size:32px;margin-bottom:10px">💬</div>
                        <p style="color:var(--muted);font-size:14px;margin-bottom:14px">Sign in to message the seller directly</p>
                        <a href="login.php?redirect=<?= urlencode('listing.php?id='.$carId) ?>" class="btn btn-accent" style="width:100%;justify-content:center">
                            <i class="fas fa-sign-in-alt"></i> Sign In to Message
                        </a>
                        <div style="margin-top:12px;font-size:12px;color:var(--muted)">No account? <a href="register.php" style="color:var(--accent)">Register free</a></div>
                    </div>
                    <?php elseif (Auth::id() === $car['seller_id']): ?>
                    <div style="padding:16px;text-align:center;color:var(--muted);font-size:13px"><i class="fas fa-info-circle" style="color:var(--accent)"></i> This is your own listing.</div>
                    <?php else: ?>
                    <form method="POST">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="send_message" value="1">
                        <div class="form-field">
                            <label>Quick Message</label>
                            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">
                                <?php $quickMsgs = ['Is this car still available?','Can we negotiate the price?','What is the lowest price?','Can I come for a test drive?']; ?>
                                <?php foreach ($quickMsgs as $qm): ?>
                                <button type="button" onclick="document.getElementById('msgArea').value='<?= $qm ?>'"
                                    style="text-align:left;padding:8px 12px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:7px;color:var(--muted);font-size:12px;cursor:pointer;font-family:var(--font-body);transition:all .2s"
                                    onmouseover="this.style.borderColor='rgba(232,184,75,.4)';this.style.color='var(--accent)'"
                                    onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'"><?= e($qm) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Your Message</label>
                            <textarea name="message" id="msgArea" rows="4" placeholder="Write your message to the seller…" required><?= e($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="form-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Test Drive Panel -->
                <div class="form-panel" id="panel-drive">
                    <?php if (!Auth::check()): ?>
                    <div style="text-align:center;padding:16px 0">
                        <div style="font-size:32px;margin-bottom:10px">🚗</div>
                        <p style="color:var(--muted);font-size:14px;margin-bottom:14px">Sign in to book a test drive</p>
                        <a href="login.php?redirect=<?= urlencode('listing.php?id='.$carId) ?>" class="btn btn-accent" style="width:100%;justify-content:center"><i class="fas fa-sign-in-alt"></i> Sign In</a>
                    </div>
                    <?php else: ?>
                    <form method="POST" id="driveForm">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="book_drive" value="1">
                        <div class="form-field">
                            <label>Preferred Date</label>
                            <input type="date" name="drive_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        </div>
                        <div class="form-field">
                            <label>Preferred Time</label>
                            <select name="drive_time" required>
                                <option value="">Select time…</option>
                                <?php for ($h=8;$h<=18;$h++): ?>
                                <option value="<?= sprintf('%02d:00:00',$h) ?>"><?= date('g:i A', mktime($h,0,0)) ?></option>
                                <option value="<?= sprintf('%02d:30:00',$h) ?>"><?= date('g:i A', mktime($h,30,0)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Meeting Location (optional)</label>
                            <input type="text" name="drive_location" placeholder="e.g. Gulshan-e-Iqbal, Karachi">
                        </div>
                        <button type="submit" class="form-submit"><i class="fas fa-calendar-check"></i> Request Test Drive</button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Report -->
                <button class="report-link" onclick="reportListing()">
                    <i class="fas fa-flag"></i> Report this listing
                </button>
            </div>

        </div><!-- /.sticky-panel -->
    </div><!-- /.listing-layout -->
</div><!-- /.container -->

<!-- ============================================================
     SIMILAR CARS
============================================================ -->
<div style="background:var(--dark);border-top:1px solid var(--border)">
    <div class="container">
        <div class="similar-section">
            <div class="section-header">
                <div>
                    <div class="section-tag">🔄 You Might Also Like</div>
                    <h2 class="section-title">Similar <span>Cars</span></h2>
                </div>
                <a href="listings.php?make=<?= e($car['make_slug']) ?>" class="view-all">More <?= e($car['make_name']) ?> <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="similar-grid">
                <?php foreach ($similar as $sim): ?>
                <div class="sim-card reveal" onclick="window.location='listing.php?id=<?= $sim['id'] ?>'">
                    <div class="sim-img">
                        <img src="<?= e(!empty($sim['featured_image']) ? carImageUrl($sim['featured_image']) : carImageUrl('')) ?>"
                             alt="<?= e($sim['make_name'].' '.$sim['model_name']) ?>"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=400&q=80'">
                    </div>
                    <div class="sim-body">
                        <div class="sim-title"><?= e($sim['make_name'].' '.$sim['model_name']) ?></div>
                        <div class="sim-sub"><?= $sim['year'] ?> · <?= e($sim['city']) ?></div>
                        <div class="sim-specs">
                            <span class="sim-spec"><i class="fas fa-tachometer-alt"></i> <?= number_format($sim['mileage']) ?> km</span>
                            <span class="sim-spec"><i class="fas fa-gas-pump"></i> <?= ucfirst($sim['fuel_type']) ?></span>
                            <span class="sim-spec"><i class="fas fa-cog"></i> <?= ucfirst($sim['transmission']) ?></span>
                        </div>
                        <div class="sim-price"><?= formatPKR($sim['price']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
    <button class="lightbox-nav lightbox-prev" onclick="event.stopPropagation();prevImg()"><i class="fas fa-chevron-left"></i></button>
    <img class="lightbox-img" id="lightboxImg" src="" alt="" onclick="event.stopPropagation()">
    <button class="lightbox-nav lightbox-next" onclick="event.stopPropagation();nextImg()"><i class="fas fa-chevron-right"></i></button>
    <div class="lightbox-count" id="lightboxCount"></div>
</div>

<!-- MOBILE STICKY BAR -->
<div class="mobile-sticky">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <div class="mobile-sticky-price"><?= formatPKR($car['price']) ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= e($car['year'].' '.$car['make_name']) ?></div>
    </div>
    <div class="mobile-sticky-btns">
        <?php if ($car['seller_phone']): ?>
        <a href="tel:<?= e($car['seller_phone']) ?>" class="cta-btn cta-call" style="flex:1;font-size:13px;padding:12px"><i class="fas fa-phone"></i> Call</a>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$car['seller_phone']) ?>?text=<?= urlencode('Hi, I\'m interested in your '.$car['year'].' '.$car['make_name'].' '.$car['model_name'].' on CarSoko') ?>"
           target="_blank" class="cta-btn cta-whatsapp" style="flex:1;font-size:13px;padding:12px"><i class="fab fa-whatsapp"></i> WhatsApp</a>
        <?php endif; ?>
        <button class="cta-btn cta-msg" style="flex:1;font-size:13px;padding:12px" onclick="scrollToContact()"><i class="fas fa-comment"></i> Message</button>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// IMAGE GALLERY
// ============================================================
const images = <?= json_encode(array_map(fn($img) => carImageUrl($img['image_path']), $images)) ?>;
let currentImg = 0;

function goToImg(index) {
    currentImg = index;
    document.getElementById('mainImg').src = images[currentImg];
    document.getElementById('imgCurrent').textContent = currentImg + 1;
    document.querySelectorAll('.thumb').forEach((t, i) => t.classList.toggle('active', i === currentImg));
    // Scroll thumb into view
    const activeThumb = document.getElementById('thumb-' + currentImg);
    if (activeThumb) activeThumb.scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'});
    // Also update lightbox if it's open
    const lb = document.getElementById('lightbox');
    if (lb && lb.classList.contains('open')) {
        document.getElementById('lightboxImg').src = images[currentImg];
        document.getElementById('lightboxCount').textContent = (currentImg + 1) + ' / ' + images.length;
    }
}

function nextImg() {
    goToImg((currentImg + 1) % images.length);
}
function prevImg() {
    goToImg((currentImg - 1 + images.length) % images.length);
}

// Keyboard navigation
document.addEventListener('keydown', e => {
    if (document.getElementById('lightbox').classList.contains('open')) {
        if (e.key === 'ArrowRight') nextImg();
        if (e.key === 'ArrowLeft')  prevImg();
        if (e.key === 'Escape') closeLightbox();
    } else {
        if (e.key === 'ArrowRight') nextImg();
        if (e.key === 'ArrowLeft')  prevImg();
    }
});

// Touch/swipe on gallery
let touchStartX = 0;
document.getElementById('galleryMain')?.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; });
document.getElementById('galleryMain')?.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) diff > 0 ? nextImg() : prevImg();
});

// ============================================================
// LIGHTBOX
// ============================================================
function openLightbox(index) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightboxImg').src = images[index];
    document.getElementById('lightboxCount').textContent = (index + 1) + ' / ' + images.length;
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

// ============================================================
// FORM TABS
// ============================================================
function switchTab(btn, panel) {
    document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('panel-' + panel).classList.add('active');
}

// ============================================================
// SCROLL HELPERS
// ============================================================
function scrollToContact() {
    document.getElementById('contact')?.scrollIntoView({behavior:'smooth', block:'center'});
    switchTab(document.querySelectorAll('.form-tab')[0], 'msg');
}
function scrollToBooking() {
    document.getElementById('contact')?.scrollIntoView({behavior:'smooth', block:'center'});
    switchTab(document.querySelectorAll('.form-tab')[1], 'drive');
}

// Saved Cars feature removed

// ============================================================
// SHARE
// ============================================================
function sharecar() {
    if (navigator.share) {
        navigator.share({
            title: '<?= e($car['year'].' '.$car['make_name'].' '.$car['model_name']) ?> – CarSoko',
            text:  '<?= e(formatPKR($car['price'])) ?> | Check out this car on CarSoko Pakistan',
            url:   window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Link copied to clipboard!');
    }
}

// ============================================================
// REPORT LISTING
// ============================================================
function reportListing() {
    <?php if (!Auth::check()): ?>
    window.location.href = 'login.php?redirect=<?= urlencode('listing.php?id='.$carId) ?>';
    return;
    <?php endif; ?>
    const reason = prompt('Why are you reporting this listing?\n\n1. Fraud / Scam\n2. Wrong information\n3. Car already sold\n4. Spam\n5. Other\n\nEnter reason:');
    if (!reason) return;
    fetch('ajax/report-listing.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'car_id=<?= $carId ?>&reason=' + encodeURIComponent(reason)
    }).then(r=>r.json()).then(d => alert(d.message || 'Report submitted. Thank you.'));
}

// ============================================================
// LOAN CALCULATOR
// ============================================================
function calculateLoan() {
    const price    = parseFloat(document.getElementById('calcPrice').value) || 0;
    const down     = parseFloat(document.getElementById('calcDown').value)  || 0;
    const rate     = parseFloat(document.getElementById('calcRate').value)  || 14;
    const termMths = parseInt(document.getElementById('calcTerm').value)    || 36;

    const principal  = Math.max(0, price - down);
    const monthlyRate = (rate / 100) / 12;

    let monthly = 0;
    if (monthlyRate > 0 && principal > 0) {
        monthly = principal * (monthlyRate * Math.pow(1 + monthlyRate, termMths)) / (Math.pow(1 + monthlyRate, termMths) - 1);
    }

    const totalPayable = monthly * termMths;
    const totalInterest = totalPayable - principal;

    const fmt = n => 'Rs. ' + Math.round(n).toLocaleString();
    document.getElementById('calcMonthly').textContent  = fmt(monthly);
    document.getElementById('calcLoanAmt').textContent  = fmt(principal);
    document.getElementById('calcTotalInt').textContent = fmt(totalInterest);
    document.getElementById('calcTotal').textContent    = fmt(totalPayable);
}
calculateLoan(); // Init on load

// ============================================================
// TRACK CONTACT CLICK
// ============================================================
function trackContact(type) {
    fetch('ajax/track-contact.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'car_id=<?= $carId ?>&type=' + type
    });
}

// ============================================================
// READ MORE / DESCRIPTION TOGGLE
// ============================================================
function toggleDesc() {
    const txt = document.getElementById('descText');
    const btn = document.getElementById('readMoreBtn');
    const clamped = txt.classList.toggle('clamped');
    btn.innerHTML = clamped
        ? '<i class="fas fa-chevron-down"></i> Read More'
        : '<i class="fas fa-chevron-up"></i> Show Less';
}

// ============================================================
// SCROLL REVEAL
// ============================================================
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
}, {threshold: 0.07, rootMargin: '0px 0px -30px 0px'});
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>