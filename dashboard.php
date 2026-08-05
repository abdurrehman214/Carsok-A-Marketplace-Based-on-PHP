<?php
//  CarSoko Pakistan — dashboard.php
//  Seller / Dealer Dashboard
//  Requires: connection.php
// ============================================================
require_once 'connection.php';

Auth::requireLogin('/login.php');
if (Auth::is('buyer')) redirect(BASE_URL . '/index.php');

$user = Auth::user();

// ============================================================
// HANDLE QUICK ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();

    $action = cleanInput($_POST['action'] ?? '');
    $carId  = (int)($_POST['car_id'] ?? 0);

    // Verify ownership
    $owns = $carId && DB::exists("SELECT 1 FROM cars WHERE id=? AND user_id=?", [$carId, Auth::id()]);

    if ($owns) {
        if ($action === 'mark_sold') {
            DB::execute("UPDATE cars SET status='sold', sold_at=NOW() WHERE id=? AND user_id=?", [$carId, Auth::id()]);
            flash('success', 'Car marked as sold!');
        } elseif ($action === 'relist') {
            DB::execute("UPDATE cars SET status='active', sold_at=NULL WHERE id=? AND user_id=?", [$carId, Auth::id()]);
            flash('success', 'Listing reactivated!');
        } elseif ($action === 'delete') {
            DB::execute("DELETE FROM car_images WHERE car_id=?", [$carId]);
            DB::execute("DELETE FROM cars WHERE id=? AND user_id=?", [$carId, Auth::id()]);
            flash('success', 'Listing deleted.');
        } elseif ($action === 'toggle_featured') {
            $cur = DB::value("SELECT is_featured FROM cars WHERE id=?", [$carId]);
            DB::execute("UPDATE cars SET is_featured=? WHERE id=? AND user_id=?", [!$cur, $carId, Auth::id()]);
            flash('success', 'Featured status updated.');
        }
    }
    logActivity($action, $carId, 'car');
    redirect(BASE_URL . '/dashboard.php');
}

// ============================================================
// STATS
// ============================================================
$totalListings  = (int) DB::value("SELECT COUNT(*) FROM cars WHERE user_id=?", [Auth::id()]);
$activeListings = (int) DB::value("SELECT COUNT(*) FROM cars WHERE user_id=? AND status='active'", [Auth::id()]);
$soldListings   = (int) DB::value("SELECT COUNT(*) FROM cars WHERE user_id=? AND status='sold'", [Auth::id()]);
$totalViews     = (int) DB::value("SELECT SUM(views) FROM cars WHERE user_id=?", [Auth::id()]);
$totalMessages  = (int) DB::value("SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN cars ca ON ca.id=c.car_id WHERE ca.user_id=?", [Auth::id()]);
$unreadMessages = getUnreadCount();
$totalRevenue   = DB::value("SELECT SUM(price) FROM cars WHERE user_id=? AND status='sold'", [Auth::id()]);

// ============================================================
// LISTINGS (paginated)
// ============================================================
$tab      = in_array($_GET['tab'] ?? '', ['active','sold','pending']) ? $_GET['tab'] : 'active';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

$statusFilter = "status='active'";
if ($tab === 'sold') $statusFilter = "status='sold'";
elseif ($tab === 'pending') $statusFilter = "status='pending'";
$totalTab     = (int) DB::value("SELECT COUNT(*) FROM cars WHERE user_id=? AND $statusFilter", [Auth::id()]);
$totalPages   = max(1, ceil($totalTab / $perPage));

$listings = DB::select("
    SELECT c.*, m.name AS make_name, mo.name AS model_name,
           (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS image_path,
           (SELECT COUNT(*) FROM conversations cv WHERE cv.car_id = c.id) AS msg_count,
           (SELECT COUNT(*) FROM conversations cv JOIN messages ms ON ms.conversation_id = cv.id WHERE cv.car_id = c.id AND ms.is_seen = 0 AND ms.sender_id != ?) AS unread_count
    FROM cars c
    JOIN makes m ON m.id = c.make_id
    JOIN models mo ON mo.id = c.model_id
    WHERE c.user_id = ? AND c.$statusFilter
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
", [Auth::id(), Auth::id()]);

// ============================================================
// RECENT MESSAGES
// ============================================================
$recentMessages = DB::select("
    SELECT cv.id AS conv_id, cv.last_message, cv.last_message_at,
           ca.id AS car_id, ca.year,
           m.name AS make_name, mo.name AS model_name,
           u.name AS buyer_name, u.profile_photo AS buyer_photo,
           (SELECT COUNT(*) FROM messages ms WHERE ms.conversation_id=cv.id AND ms.is_seen=0 AND ms.sender_id != ?) AS unread
    FROM conversations cv
    JOIN cars ca ON ca.id = cv.car_id
    JOIN makes m ON m.id = ca.make_id
    JOIN models mo ON mo.id = ca.model_id
    JOIN users u ON u.id = cv.buyer_id
    WHERE ca.user_id = ?
    GROUP BY cv.id
    ORDER BY cv.last_message_at DESC
    LIMIT 5
", [Auth::id(), Auth::id()]);

// TOP PERFORMING LISTINGS
// ============================================================
$topListings = DB::select("
    SELECT c.id, c.year, c.price, c.views, c.contact_clicks,
           m.name AS make_name, mo.name AS model_name
    FROM cars c
    JOIN makes m ON m.id = c.make_id
    JOIN models mo ON mo.id = c.model_id
    WHERE c.user_id = ? AND c.status = 'active'
    GROUP BY c.id
    ORDER BY c.views DESC LIMIT 5
", [Auth::id()]);

// ============================================================
// NOTIFICATIONS
// ============================================================
$notifications = DB::select("
    SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8
", [Auth::id()]);

// Mark notifications as read
DB::execute("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0", [Auth::id()]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
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
    --sidebar:  260px;
}*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px;line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}

/* LAYOUT */
.dash-layout{display:grid;grid-template-columns:var(--sidebar) 1fr;min-height:100vh;min-width:0}

/* SIDEBAR */
.sidebar{position:sticky;top:0;height:100vh;overflow-y:auto;background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0}
.sidebar::-webkit-scrollbar{width:4px}
.sidebar::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.sidebar-logo{padding:20px 20px 16px;border-bottom:1px solid var(--border)}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}

.sidebar-user{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.user-avatar{width:38px;height:38px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:15px;color:#0a0a0b;flex-shrink:0}
.user-name{font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-role{font-size:11px;color:var(--muted)}

.sidebar-nav{padding:12px 10px;flex:1}
.nav-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:8px 10px 4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);color:var(--muted);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;cursor:pointer;position:relative}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,.05)}
.nav-item.active{color:var(--white);background:rgba(232,184,75,.1);}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--gradient);border-radius:0 3px 3px 0}
.nav-item i{width:16px;text-align:center;font-size:14px}
.nav-item.active i{color:var(--accent)}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:50px}

.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);padding:8px 0;transition:color .2s}
.sidebar-footer a:hover{color:var(--white)}

/* MAIN CONTENT */
.dash-main{min-width:0;overflow-x:hidden}

/* TOP BAR */
.dash-topbar{background:var(--dark);border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:sticky;top:0;z-index:100}
.topbar-title{font-family:var(--font-head);font-size:18px;font-weight:700}
.topbar-actions{display:flex;align-items:center;gap:10px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .25s;border:none;font-family:var(--font-body)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.35)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.25)}
.btn-sm{padding:6px 14px;font-size:12px}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.btn-danger:hover{background:rgba(239,68,68,.2)}
.btn-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.btn-success:hover{background:rgba(34,197,94,.2)}
.hamburger-dash{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:8px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger-dash span{width:18px;height:2px;background:var(--white);border-radius:2px}

/* PAGE BODY */
.dash-body{padding:28px;min-width:0}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;min-width:0}
.stat-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;position:relative;overflow:hidden;transition:transform .2s;min-width:0}
.stat-card:hover{transform:translateY(-2px)}
.stat-card::after{content:'';position:absolute;top:0;right:0;width:80px;height:80px;border-radius:50%;background:var(--gradient);opacity:.06;transform:translate(30%,-30%)}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:14px}
.stat-icon.gold{background:rgba(232,184,75,.1);color:var(--accent)}
.stat-icon.green{background:rgba(34,197,94,.1);color:var(--green)}
.stat-icon.blue{background:rgba(59,130,246,.1);color:var(--blue)}
.stat-icon.orange{background:rgba(255,107,53,.1);color:var(--accent2)}
.stat-icon.red{background:rgba(239,68,68,.1);color:var(--red)}
.stat-value{font-family:var(--font-head);font-size:28px;font-weight:800;line-height:1;margin-bottom:4px}
.stat-label{font-size:12px;color:var(--muted)}
.stat-trend{font-size:11px;margin-top:6px;display:flex;align-items:center;gap:4px}
.trend-up{color:var(--green)}
.trend-down{color:var(--red)}

/* SECTION TITLES */
.section-title{font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px}

/* LISTING TABLE */
.listing-tabs{display:flex;gap:4px;background:rgba(0,0,0,.25);padding:4px;border-radius:10px;width:fit-content;max-width:100%;overflow-x:auto;white-space:nowrap;margin-bottom:20px;-webkit-overflow-scrolling:touch}
.listing-tabs::-webkit-scrollbar{display:none}
.listing-tab{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:var(--muted);transition:all .2s;text-decoration:none;flex-shrink:0}
.listing-tab.active{background:var(--gradient);color:#0a0a0b}
.listing-tab:hover:not(.active){color:var(--white);background:rgba(255,255,255,.06)}

.listings-table{width:100%;border-collapse:collapse}
.listings-table th{padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap}
.listings-table td{padding:14px;border-bottom:1px solid var(--border);vertical-align:middle}
.listings-table tr:last-child td{border-bottom:none}
.listings-table tr:hover td{background:rgba(255,255,255,.02)}

.listing-thumb{width:56px;height:44px;border-radius:7px;object-fit:cover;background:#111;flex-shrink:0}
.listing-info{display:flex;align-items:center;gap:12px;min-width:0}
.listing-name{font-weight:600;font-size:13px;color:var(--white)}
.listing-sub{font-size:11px;color:var(--muted);margin-top:2px}

.status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.status-sold{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.status-pending{background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent)}

.action-btns{display:flex;align-items:center;gap:6px;flex-wrap:wrap}

/* QUICK ACTION FORM */
.quick-form{display:inline}

/* EMPTY STATE */
.empty-state{text-align:center;padding:60px 20px}
.empty-icon{font-size:48px;opacity:.2;margin-bottom:16px}
.empty-title{font-family:var(--font-head);font-size:20px;font-weight:700;margin-bottom:8px}
.empty-sub{font-size:14px;color:var(--muted);margin-bottom:24px}

/* MESSAGES PANEL */
.messages-list{display:flex;flex-direction:column;gap:0}
.message-item{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--border);cursor:pointer;transition:all .2s}
.message-item:last-child{border-bottom:none}
.message-item:hover{opacity:.8}
.msg-avatar{width:40px;height:40px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:15px;color:#0a0a0b;flex-shrink:0;position:relative}
.msg-unread-dot{position:absolute;top:-2px;right:-2px;width:10px;height:10px;background:var(--red);border-radius:50%;border:2px solid var(--card-bg)}
.msg-name{font-size:13px;font-weight:600;color:var(--white)}
.msg-car{font-size:11px;color:var(--muted);margin-top:1px}
.msg-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.msg-time{font-size:11px;color:var(--muted);margin-left:auto;white-space:nowrap}

/* TOP LISTINGS */
.top-listing-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)}
.top-listing-row:last-child{border-bottom:none}
.top-rank{width:24px;height:24px;border-radius:50%;background:rgba(232,184,75,.1);color:var(--accent);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.top-listing-name{font-size:13px;font-weight:600;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top-stat{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;white-space:nowrap}
.top-stat i{color:var(--accent);font-size:10px}

/* NOTIFICATIONS */
.notif-item{display:flex;align-items:flex-start;gap:10px;padding:12px 0;border-bottom:1px solid var(--border)}
.notif-item:last-child{border-bottom:none}
.notif-icon{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.notif-msg-icon{background:rgba(59,130,246,.1);color:var(--blue)}
.notif-sale-icon{background:rgba(34,197,94,.1);color:var(--green)}
.notif-info-icon{background:rgba(232,184,75,.1);color:var(--accent)}
.notif-body{font-size:13px;color:rgba(245,245,240,.8);line-height:1.5;flex:1}
.notif-time{font-size:11px;color:var(--muted);margin-top:2px}

/* CARDS */
.dash-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;min-width:0}
.dash-card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px}
.dash-card-header h3{font-family:var(--font-head);font-size:15px;font-weight:700}
.dash-card-body{padding:20px;min-width:0}

/* GRID 2-col */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;min-width:0}
.dash-grid{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;min-width:0}
.dash-grid > div{min-width:0}

/* PAGINATION */
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px}
.page-btn{padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;background:var(--card-bg);border:1px solid var(--border);color:var(--muted);cursor:pointer;transition:all .2s;text-decoration:none}
.page-btn:hover,.page-btn.active{background:rgba(232,184,75,.1);border-color:rgba(232,184,75,.3);color:var(--accent)}

/* ALERT */
.alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

/* MOBILE OVERLAY */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:198}

/* RESPONSIVE */
@media(max-width:1024px){
    .dash-layout{grid-template-columns:1fr}
    .sidebar{position:fixed;left:-260px;z-index:200;transition:left .3s ease;height:100vh}
    .sidebar.open{left:0}
    .sidebar-overlay.show{display:block}
    .hamburger-dash{display:flex}
    .stats-grid{grid-template-columns:1fr 1fr}
    .grid-2{grid-template-columns:1fr}
    .dash-grid{grid-template-columns:1fr}
}

/* Mobile Listing Cards (< 768px) */
@media(max-width:768px){
    .dash-body{padding:16px 12px}
    .dash-topbar{padding:0 14px}
    .stats-grid{grid-template-columns:1fr 1fr;gap:10px}
    .stat-card{padding:14px}
    .stat-value{font-size:24px}
    
    .listings-table, .listings-table thead, .listings-table tbody, .listings-table tr, .listings-table td{
        display:block;
        width:100%;
    }
    .listings-table thead{
        display:none;
    }
    .listings-table tbody{
        display:flex;
        flex-direction:column;
        gap:12px;
        padding:12px;
    }
    .listings-table tr{
        background:rgba(255,255,255,0.02);
        border:1px solid var(--border);
        border-radius:14px;
        padding:14px;
        display:flex;
        flex-direction:column;
        gap:10px;
    }
    .listings-table tr:hover td{background:transparent}
    .listings-table td{
        padding:0;
        border:none;
    }
    .listings-table td:nth-child(1){
        border-bottom:1px solid var(--border);
        padding-bottom:10px;
    }
    .listings-table td:nth-child(2){
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size:13px;
    }
    .listings-table td:nth-child(2)::before{
        content:'Price';
        color:var(--muted);
        font-weight:500;
        font-size:12px;
    }
    .listings-table td:nth-child(3){
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size:13px;
    }
    .listings-table td:nth-child(3)::before{
        content:'Views';
        color:var(--muted);
        font-size:12px;
    }
    .listings-table td:nth-child(4){
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size:13px;
    }
    .listings-table td:nth-child(4)::before{
        content:'Messages';
        color:var(--muted);
        font-size:12px;
    }
    .listings-table td:nth-child(5){
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size:13px;
    }
    .listings-table td:nth-child(5)::before{
        content:'Status';
        color:var(--muted);
        font-size:12px;
    }
    .listings-table td:nth-child(6){
        border-top:1px solid var(--border);
        padding-top:10px;
        margin-top:2px;
    }
    .action-btns{
        width:100%;
        justify-content:flex-end;
        gap:8px;
    }
    .action-btns .btn-sm{
        padding:8px 14px;
        font-size:13px;
    }
}
@media(max-width:480px){
    .topbar-title{font-size:16px}
    .btn-sm{padding:5px 10px;font-size:11px}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="dash-layout">

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="index.php" class="logo">
            <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div style="overflow:hidden">
            <div class="user-name"><?= e($user['name']) ?></div>
            <div class="user-role"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="dashboard.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="post-listing.php" class="nav-item"><i class="fas fa-plus-circle"></i> Post a Car</a>
        <a href="dashboard.php?tab=active" class="nav-item"><i class="fas fa-car"></i> My Listings
            <?php if ($activeListings > 0): ?><span class="nav-badge"><?= $activeListings ?></span><?php endif; ?>
        </a>

        <div class="nav-label" style="margin-top:12px">Inbox</div>
        <a href="messages.php" class="nav-item"><i class="fas fa-comment-dots"></i> Messages
            <?php if ($unreadMessages > 0): ?><span class="nav-badge"><?= $unreadMessages ?></span><?php endif; ?>
        </a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications</a>

        <div class="nav-label" style="margin-top:12px">Account</div>
        <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> My Profile</a>
        <?php if (Auth::isModerator()): ?>
        <a href="admin.php" class="nav-item" style="color:var(--accent)"><i class="fas fa-shield-halved"></i> Admin Panel</a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="index.php"><i class="fas fa-home"></i> Back to Site</a>
        <a href="logout.php" style="color:var(--red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>

<!-- ============================================================
     MAIN
============================================================ -->
<main class="dash-main">

    <!-- TOP BAR -->
    <div class="dash-topbar">
        <div style="display:flex;align-items:center;gap:12px">
            <div class="hamburger-dash" onclick="openSidebar()"><span></span><span></span><span></span></div>
            <div class="topbar-title">Dashboard</div>
        </div>
        <div class="topbar-actions">
            <a href="messages.php" class="btn btn-outline btn-sm" style="position:relative">
                <i class="fas fa-comment-dots"></i> Messages
                <?php if ($unreadMessages > 0): ?>
                <span style="position:absolute;top:-4px;right:-4px;width:8px;height:8px;background:var(--red);border-radius:50%"></span>
                <?php endif; ?>
            </a>
            <a href="post-listing.php" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i> Post Car</a>
        </div>
    </div>

    <!-- BODY -->
    <div class="dash-body">

        <?php showFlash('success'); showFlash('error'); ?>

        <!-- WELCOME -->
        <div style="margin-bottom:24px">
            <div style="font-family:var(--font-head);font-size:24px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em">
                Welcome Back, <?= e(explode(' ', $user['name'])[0]) ?>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-top:4px">
                Here's an overview of your CarSoko Pakistan activity
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-car"></i></div>
                <div class="stat-value"><?= $activeListings ?></div>
                <div class="stat-label">Active Listings</div>
                <div class="stat-trend trend-up"><i class="fas fa-circle-dot"></i> Live now</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-eye"></i></div>
                <div class="stat-value"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Total Views</div>
                <div class="stat-trend trend-up"><i class="fas fa-arrow-trend-up"></i> All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-comment-dots"></i></div>
                <div class="stat-value"><?= $totalMessages ?></div>
                <div class="stat-label">Enquiries</div>
                <?php if ($unreadMessages > 0): ?>
                <div class="stat-trend" style="color:var(--red)"><i class="fas fa-circle-exclamation"></i> <?= $unreadMessages ?> unread</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-handshake"></i></div>
                <div class="stat-value"><?= $soldListings ?></div>
                <div class="stat-label">Cars Sold</div>
                <?php if ($totalRevenue): ?>
                <div class="stat-trend trend-up"><i class="fas fa-coins"></i> <?= formatPKR((float)$totalRevenue) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LISTINGS + SIDEBAR WIDGETS -->
        <div class="dash-grid">

            <!-- LISTINGS TABLE -->
            <div>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h3>My Listings</h3>
                        <a href="post-listing.php" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i> Add New</a>
                    </div>
                    <div style="padding:16px 20px 0">
                        <div class="listing-tabs">
                            <a href="?tab=active"  class="listing-tab <?= $tab === 'active'  ? 'active' : '' ?>">Active (<?= $activeListings ?>)</a>
                            <a href="?tab=sold"    class="listing-tab <?= $tab === 'sold'    ? 'active' : '' ?>">Sold (<?= $soldListings ?>)</a>
                            <a href="?tab=pending" class="listing-tab <?= $tab === 'pending' ? 'active' : '' ?>">Pending</a>
                        </div>
                    </div>

                    <?php if (empty($listings)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-car-side"></i></div>
                        <div class="empty-title">No <?= $tab ?> listings</div>
                        <div class="empty-sub">
                            <?= $tab === 'active' ? 'Post your first car to start getting enquiries.' : 'No listings in this category yet.' ?>
                        </div>
                        <?php if ($tab === 'active'): ?>
                        <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Post a Car</a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                        <table class="listings-table">
                            <thead>
                                <tr>
                                    <th>Car</th>
                                    <th>Price</th>
                                    <th>Views</th>
                                    <th>Msgs</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listings as $lst): ?>
                                <tr>
                                    <td>
                                        <div class="listing-info">
                                            <img class="listing-thumb"
                                                 src="<?= carImageUrl($lst['image_path'], true) ?>"
                                                 alt="<?= e($lst['make_name']) ?>">
                                            <div>
                                                <div class="listing-name"><?= e($lst['year'] . ' ' . $lst['make_name'] . ' ' . $lst['model_name']) ?></div>
                                                <div class="listing-sub"><?= e($lst['city']) ?> &middot; <?= timeAgo($lst['created_at']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:600;white-space:nowrap"><?= formatPKR((float)$lst['price']) ?></td>
                                    <td><?= number_format($lst['views'] ?? 0) ?></td>
                                    <td>
                                        <?= $lst['msg_count'] ?? 0 ?>
                                        <?php if (($lst['unread_count'] ?? 0) > 0): ?>
                                        <span style="color:var(--red);font-size:11px">(<?= $lst['unread_count'] ?> new)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= e($lst['status']) ?>">
                                            <?= ucfirst($lst['status']) ?>
                                        </span>
                                        <?php if ($lst['is_featured']): ?>
                                        <span class="status-badge" style="background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent);margin-top:4px">⭐ Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="listing.php?id=<?= $lst['id'] ?>" class="btn btn-sm btn-outline" title="View"><i class="fas fa-eye"></i></a>
                                            <a href="post-listing.php?edit=<?= $lst['id'] ?>" class="btn btn-sm btn-outline" title="Edit"><i class="fas fa-pen"></i></a>

                                            <?php if ($lst['status'] === 'active'): ?>
                                            <form class="quick-form" method="POST" onsubmit="return confirm('Mark this car as sold?')">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="mark_sold">
                                                <input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Mark Sold"><i class="fas fa-check"></i></button>
                                            </form>
                                            <?php elseif ($lst['status'] === 'sold'): ?>
                                            <form class="quick-form" method="POST" onsubmit="return confirm('Relist this car?')">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="relist">
                                                <input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" title="Relist"><i class="fas fa-rotate-right"></i></button>
                                            </form>
                                            <?php endif; ?>

                                            <form class="quick-form" method="POST" onsubmit="return confirm('Delete this listing? This cannot be undone.')">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination" style="padding-bottom:16px">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="?tab=<?= $tab ?>&page=<?= $p ?>" class="page-btn <?= $page === $p ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT WIDGETS -->
            <div style="display:flex;flex-direction:column;gap:20px">

                <!-- QUICK ACTIONS -->
                <div class="dash-card">
                    <div class="dash-card-header"><h3>Quick Actions</h3></div>
                    <div class="dash-card-body" style="display:flex;flex-direction:column;gap:8px;padding:14px">
                        <a href="post-listing.php" class="btn btn-accent" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Post New Listing</a>
                        <a href="messages.php" class="btn btn-outline" style="width:100%;justify-content:center"><i class="fas fa-comment-dots"></i> View Messages <?php if ($unreadMessages): ?>(<?= $unreadMessages ?>)<?php endif; ?></a>
                        <a href="profile.php" class="btn btn-outline" style="width:100%;justify-content:center"><i class="fas fa-user-pen"></i> Edit Profile</a>
                    </div>
                </div>

                <!-- RECENT MESSAGES -->
                <?php if (!empty($recentMessages)): ?>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h3>Recent Enquiries</h3>
                        <a href="messages.php" style="font-size:12px;color:var(--accent)">View all →</a>
                    </div>
                    <div class="dash-card-body" style="padding:4px 20px">
                        <div class="messages-list">
                            <?php foreach ($recentMessages as $msg): ?>
                            <a href="messages.php?conv=<?= $msg['conv_id'] ?>" class="message-item">
                                <div class="msg-avatar">
                                    <?= strtoupper(substr($msg['buyer_name'], 0, 1)) ?>
                                    <?php if ($msg['unread']): ?><div class="msg-unread-dot"></div><?php endif; ?>
                                </div>
                                <div style="flex:1;overflow:hidden">
                                    <div class="msg-name"><?= e($msg['buyer_name']) ?></div>
                                    <div class="msg-car"><?= e($msg['make_name'] . ' ' . $msg['model_name']) ?></div>
                                    <div class="msg-preview"><?= e($msg['last_message']) ?></div>
                                </div>
                                <div class="msg-time"><?= timeAgo($msg['last_message_at']) ?></div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TOP LISTINGS -->
                <?php if (!empty($topListings)): ?>
                <div class="dash-card">
                    <div class="dash-card-header"><h3>Top Performing</h3></div>
                    <div class="dash-card-body" style="padding:4px 20px">
                        <?php foreach ($topListings as $i => $tl): ?>
                        <div class="top-listing-row">
                            <div class="top-rank"><?= $i + 1 ?></div>
                            <a href="listing.php?id=<?= $tl['id'] ?>" class="top-listing-name"><?= e($tl['year'] . ' ' . $tl['make_name'] . ' ' . $tl['model_name']) ?></a>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
                                <div class="top-stat"><i class="fas fa-eye"></i> <?= number_format($tl['views']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- NOTIFICATIONS -->
                <?php if (!empty($notifications)): ?>
                <div class="dash-card">
                    <div class="dash-card-header"><h3>Notifications</h3></div>
                    <div class="dash-card-body" style="padding:4px 20px">
                        <?php foreach ($notifications as $notif): ?>
                        <?php
                        $nType = $notif['type'] ?? '';
                        switch($nType) {
                            case 'new_message': $iconClass = 'notif-msg-icon fas fa-comment'; break;
                            case 'car_sold':    $iconClass = 'notif-sale-icon fas fa-handshake'; break;
                            default:            $iconClass = 'notif-info-icon fas fa-bell'; break;
                        }
                        ?>
                        <div class="notif-item">
                            <div class="notif-icon <?= $iconClass ?>"></div>
                            <div>
                                <div class="notif-body"><?= e($notif['body'] ?? $notif['title'] ?? '') ?></div>
                                <div class="notif-time"><?= timeAgo($notif['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /right widgets -->
        </div><!-- /grid -->

    </div><!-- /dash-body -->
</main>

</div><!-- /dash-layout -->

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
</script>
</body>
</html>