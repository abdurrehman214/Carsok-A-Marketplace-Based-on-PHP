<?php
// ============================================================
//  CarSoko Pakistan — admin.php
//  Admin Control Panel — Password: AR@12345
// ============================================================
require_once 'connection.php';

// ============================================================
// PASSWORD GATE
// ============================================================


if (isset($_POST['admin_pw'])) {
    if ($_POST['admin_pw'] === ADMIN_PASSWORD) {
        $_SESSION['admin_unlocked'] = true;
        header('Location: admin.php'); exit;
    }
    $pwError = 'Wrong password. Try again.';
}

if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_unlocked']);
    header('Location: admin.php'); exit;
}

// ---- SHOW LOGIN SCREEN IF NOT UNLOCKED ----
if (empty($_SESSION['admin_unlocked'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#000000;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.gate{background:#0a0a0b;border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:48px 40px;max-width:400px;width:100%;text-align:center}
.gate-icon{width:60px;height:60px;background:rgba(232,184,75,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:24px;color:var(--accent)}
.logo{font-family:'Bebas Neue',sans-serif;font-size:36px;font-weight:400;margin-bottom:4px;letter-spacing:1px}
.logo-car{background:linear-gradient(135deg,#e8b84b,#ff6b35);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-soko{color:#ffffff}
.gate-sub{font-size:13px;color:#a0a0a0;margin-bottom:28px;text-transform:uppercase;letter-spacing:1px}
.error-box{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;text-align:left}
.pw-wrap{position:relative;margin-bottom:14px}
.pw-wrap input{width:100%;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);color:#f5f5f0;padding:13px 48px 13px 16px;border-radius:10px;font-size:15px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s;letter-spacing:2px}
.pw-wrap input::placeholder{letter-spacing:0;color:#555}
.pw-wrap input:focus{border-color:#e8b84b}
.eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;font-size:18px;padding:4px;line-height:1}
.eye-btn:hover{color:#f5f5f0}
.submit-btn{width:100%;padding:13px;background:linear-gradient(135deg,#e8b84b,#ff6b35);color:#0a0a0b;font-weight:700;font-size:15px;border:none;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4)}
.back-link{display:inline-block;margin-top:20px;font-size:13px;color:#888896;transition:color .2s}
.back-link:hover{color:#e8b84b}
</style>
</head>
<body>
<div class="gate">
    <div class="gate-icon"><i class="fas fa-lock"></i></div>
    <div class="logo"><span class="logo-car">Car</span><span class="logo-soko">Soko</span></div>
    <div class="gate-sub">Admin Panel — Restricted Access</div>

    <?php if (!empty($pwError)): ?>
    <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($pwError) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="pw-wrap">
            <input type="password" name="admin_pw" id="pwField" placeholder="Enter admin password" autofocus>
            <button type="button" class="eye-btn" onclick="var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
        </div>
        <button type="submit" class="submit-btn">🔓 Unlock Admin Panel</button>
    </form>
    <a href="index.php" class="back-link">← Back to site</a>
</div>
</body>
</html>
<?php
exit; // Don't render the panel
endif;

// ============================================================
// ADMIN UNLOCKED — FULL PANEL BELOW
// ============================================================

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = cleanInput($_POST['action'] ?? '');

    switch ($action) {

        // ── INLINE EDIT (no ownership check — admin can edit any listing) ──
        case 'admin_edit_listing':
            $id    = (int)($_POST['car_id'] ?? 0);
            $price = (int)($_POST['price']  ?? 0);
            $city  = cleanInput($_POST['city']        ?? '');
            $desc  = cleanInput($_POST['description'] ?? '');
            $mileage = (int)($_POST['mileage'] ?? 0);
            $status = cleanInput($_POST['status'] ?? 'active');
            if (!in_array($status, ['active','pending','sold','rejected'])) $status = 'active';
            $featured   = !empty($_POST['is_featured'])      ? 1 : 0;
            $negotiable = !empty($_POST['price_negotiable']) ? 1 : 0;
            if ($id && $price > 0) {
                DB::execute(
                    "UPDATE cars SET price=?, city=?, description=?, mileage=?, status=?,
                     is_featured=?, price_negotiable=?, updated_at=NOW() WHERE id=?",
                    [$price, $city, $desc, $mileage, $status, $featured, $negotiable, $id]
                );
                flash('success', "Listing #$id updated successfully.");
                logActivity('admin_edit_listing', $id, 'car');
            } else {
                flash('error', 'Invalid data — price must be greater than 0.');
            }
            break;

        // ── LISTINGS ──
        case 'approve_listing':
            $id = (int)$_POST['car_id'];
            DB::execute("UPDATE cars SET status='active', approved_at=NOW() WHERE id=?", [$id]);
            logActivity('admin_approve_listing', $id, 'car');
            flash('success', 'Listing approved and now live.');
            break;

        case 'reject_listing':
            $id     = (int)$_POST['car_id'];
            $reason = cleanInput($_POST['reason'] ?? 'Does not meet listing standards.');
            DB::execute("UPDATE cars SET status='rejected', rejection_reason=? WHERE id=?", [$reason, $id]);
            logActivity('admin_reject_listing', $id, 'car');
            flash('success', 'Listing rejected.');
            break;

        case 'delete_listing':
            $id = (int)$_POST['car_id'];
            DB::execute("DELETE FROM car_images WHERE car_id=?", [$id]);
            DB::execute("DELETE FROM cars WHERE id=?", [$id]);
            logActivity('admin_delete_listing', $id, 'car');
            flash('success', 'Listing deleted permanently.');
            break;

        case 'toggle_featured_listing':
            $id  = (int)$_POST['car_id'];
            $cur = (int)DB::value("SELECT is_featured FROM cars WHERE id=?", [$id]);
            DB::execute("UPDATE cars SET is_featured=? WHERE id=?", [$cur ? 0 : 1, $id]);
            flash('success', 'Featured status updated.');
            break;

        case 'restore_listing':
            $id = (int)$_POST['car_id'];
            DB::execute("UPDATE cars SET status='active' WHERE id=?", [$id]);
            flash('success', 'Listing restored to active.');
            break;

        // ── USERS ──
        case 'ban_user':
            $id = (int)$_POST['user_id'];
            DB::execute("UPDATE users SET status='banned' WHERE id=?", [$id]);
            logActivity('admin_ban_user', $id, 'user');
            flash('success', 'User banned.');
            break;

        case 'unban_user':
            $id = (int)$_POST['user_id'];
            DB::execute("UPDATE users SET status='active' WHERE id=?", [$id]);
            logActivity('admin_unban_user', $id, 'user');
            flash('success', 'User unbanned.');
            break;

        case 'verify_seller':
            $id = (int)$_POST['user_id'];
            DB::execute("UPDATE users SET is_verified_seller=1 WHERE id=?", [$id]);
            flash('success', 'Seller verified.');
            break;

        case 'delete_user':
            $id = (int)$_POST['user_id'];
            DB::execute("DELETE FROM car_images WHERE car_id IN (SELECT id FROM cars WHERE user_id=?)", [$id]);
            DB::execute("DELETE FROM cars WHERE user_id=?", [$id]);
            DB::execute("DELETE FROM users WHERE id=?", [$id]);
            logActivity('admin_delete_user', $id, 'user');
            flash('success', 'User deleted.');
            break;

        case 'change_role':
            $id   = (int)$_POST['user_id'];
            $role = cleanInput($_POST['role'] ?? '');
            if (in_array($role, ['buyer','private_seller','dealer','moderator','admin'])) {
                DB::execute("UPDATE users SET role=? WHERE id=?", [$role, $id]);
                flash('success', 'Role updated.');
            }
            break;

        // ── REPORTS ──
        case 'resolve_report':
            $id = (int)$_POST['report_id'];
            DB::execute("UPDATE reports SET status='resolved', resolved_at=NOW() WHERE id=?", [$id]);
            flash('success', 'Report resolved.');
            break;

        case 'dismiss_report':
            $id = (int)$_POST['report_id'];
            DB::execute("UPDATE reports SET status='dismissed', resolved_at=NOW() WHERE id=?", [$id]);
            flash('success', 'Report dismissed.');
            break;

        // ── SETTINGS ──
        case 'save_settings':
            foreach (($_POST['settings'] ?? []) as $key => $val) {
                $key = cleanInput($key); $val = cleanInput($val);
                DB::execute(
                    "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
                    [$key, $val, $val]
                );
            }
            flash('success', 'Settings saved.');
            break;

        case 'ban_ip':
            $ip = cleanInput($_POST['ip'] ?? '');
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                DB::execute(
                    "INSERT IGNORE INTO banned_ips (ip, reason) VALUES (?,?)",
                    [$ip, cleanInput($_POST['ban_reason'] ?? 'Manual ban')]
                );
                flash('success', "IP $ip banned.");
            }
            break;
            
        case 'delete_blog':
            $id = (int)$_POST['blog_id'];
            DB::execute("DELETE FROM blog_posts WHERE id=?", [$id]);
            logActivity('admin_delete_blog', $id, 'blog');
            flash('success', 'Blog post deleted successfully.');
            break;
    }

    redirect(BASE_URL . '/admin.php?tab=' . cleanInput($_POST['tab'] ?? 'overview'));
}

// ============================================================
// TAB & PAGINATION
// ============================================================
$tab     = in_array($_GET['tab'] ?? '', ['overview','listings','users','reports','settings','blogs']) ? $_GET['tab'] : 'overview';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$search  = cleanInput($_GET['q'] ?? '');
$rows    = []; $totalRows = 0; $totalPages = 1;

// ── STATS (always loaded) ──
$stats = [
    'total_users'     => (int)DB::value("SELECT COUNT(*) FROM users"),
    'total_listings'  => (int)DB::value("SELECT COUNT(*) FROM cars"),
    'active_listings' => (int)DB::value("SELECT COUNT(*) FROM cars WHERE status='active'"),
    'pending'         => (int)DB::value("SELECT COUNT(*) FROM cars WHERE status='pending'"),
    'sold'            => (int)DB::value("SELECT COUNT(*) FROM cars WHERE status='sold'"),
    'total_messages'  => (int)DB::value("SELECT COUNT(*) FROM messages"),
    'open_reports'    => (int)DB::value("SELECT COUNT(*) FROM reports WHERE status='open'"),
    'banned_users'    => (int)DB::value("SELECT COUNT(*) FROM users WHERE status='banned'"),
    'new_users_7d'    => (int)DB::value("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'new_listings_7d' => (int)DB::value("SELECT COUNT(*) FROM cars WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
];

// ── LISTINGS TAB DATA ──
if ($tab === 'listings') {
    $statusF = cleanInput($_GET['status'] ?? '');
    $where   = "WHERE 1=1"; $params = [];
    if ($statusF) { $where .= " AND c.status=?"; $params[] = $statusF; }
    if ($search)  {
        $where .= " AND (m.name LIKE ? OR mo.name LIKE ? OR u.name LIKE ? OR c.city LIKE ?)";
        $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
    }
    $totalRows  = (int)DB::value("SELECT COUNT(DISTINCT c.id) FROM cars c JOIN makes m ON m.id=c.make_id JOIN models mo ON mo.id=c.model_id JOIN users u ON u.id=c.user_id $where", $params);
    $totalPages = max(1, ceil($totalRows / $perPage));
    $rows = DB::select("
        SELECT c.id, c.year, c.price, c.mileage, c.status, c.is_featured, c.views,
               c.created_at, c.city, c.description, c.price_negotiable,
               m.name AS make_name, mo.name AS model_name,
               u.id AS user_id, u.name AS seller_name, u.role AS seller_role,
               (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id AND ci.is_featured = 1 LIMIT 1) AS image_path
        FROM cars c
        JOIN makes m   ON m.id  = c.make_id
        JOIN models mo ON mo.id = c.model_id
        JOIN users u   ON u.id  = c.user_id
        $where
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT $perPage OFFSET $offset
    ", $params);
}

// ── USERS TAB DATA ──
if ($tab === 'users') {
    $roleF = cleanInput($_GET['role'] ?? ''); $statusF = cleanInput($_GET['status'] ?? '');
    $where = "WHERE 1=1"; $params = [];
    if ($roleF)   { $where .= " AND role=?";   $params[] = $roleF; }
    if ($statusF) { $where .= " AND status=?"; $params[] = $statusF; }
    if ($search)  { $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
    $totalRows  = (int)DB::value("SELECT COUNT(*) FROM users $where", $params);
    $totalPages = max(1, ceil($totalRows / $perPage));
    $rows = DB::select("
        SELECT u.*,
               (SELECT COUNT(*) FROM cars WHERE user_id=u.id) AS total_cars,
               (SELECT COUNT(*) FROM cars WHERE user_id=u.id AND status='active') AS active_cars
        FROM users u $where ORDER BY u.created_at DESC
        LIMIT $perPage OFFSET $offset
    ", $params);
}

// ── REPORTS TAB DATA ──
if ($tab === 'reports') {
    $totalRows  = (int)DB::value("SELECT COUNT(*) FROM reports WHERE status='open'");
    $totalPages = max(1, ceil($totalRows / $perPage));
    $rows = DB::select("
        SELECT r.*, u.name AS reporter_name, u.email AS reporter_email,
               c.year, c.city, c.id AS car_id, m.name AS make_name, mo.name AS model_name,
               cu.name AS car_owner_name
        FROM reports r
        JOIN users u   ON u.id  = r.reporter_id
        JOIN cars c    ON c.id  = r.car_id
        JOIN makes m   ON m.id  = c.make_id
        JOIN models mo ON mo.id = c.model_id
        JOIN users cu  ON cu.id = c.user_id
        WHERE r.status='open' ORDER BY r.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
}

// ── BLOGS TAB DATA ──
if ($tab === 'blogs') {
    $totalRows  = (int)DB::value("SELECT COUNT(*) FROM blog_posts");
    $totalPages = max(1, ceil($totalRows / $perPage));
    $rows = DB::select("
        SELECT b.*, u.name AS author_name 
        FROM blog_posts b 
        LEFT JOIN users u ON u.id = b.author_id 
        ORDER BY b.created_at DESC 
        LIMIT $perPage OFFSET $offset
    ");
}

// ── SETTINGS TAB DATA ──
if ($tab === 'settings') {
    $settingKeys = ['site_name','site_email','site_phone','site_city','listings_per_page','free_listing_limit','require_approval','maintenance_mode','whatsapp_number','admin_email','facebook_url','instagram_url','twitter_url','whatsapp_url','linkedin_url'];
    $siteSettings = [];
    foreach ($settingKeys as $k) {
        $val = DB::value("SELECT setting_value FROM settings WHERE setting_key=?", [$k]);
        if ($val !== null) {
            $siteSettings[$k] = $val;
        } else {
            switch ($k) {
                case 'site_name': $siteSettings[$k] = 'CarSoko'; break;
                case 'site_city': $siteSettings[$k] = 'Karachi'; break;
                case 'listings_per_page': $siteSettings[$k] = '12'; break;
                case 'free_listing_limit': $siteSettings[$k] = '3'; break;
                default: $siteSettings[$k] = ''; break;
            }
        }
    }
}

$recentActivity = DB::select("SELECT al.*, u.name AS user_name FROM activity_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 15");
$recentUsers    = DB::select("SELECT id,name,email,role,status,created_at FROM users ORDER BY created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel | CarSoko Pakistan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--black:#000000;--dark:#0a0a0b;--card-bg:#111114;--border:rgba(255,255,255,0.08);--white:#ffffff;--muted:#a0a0a0;--accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;--blue:#3b82f6;--purple:#a855f7;--gradient:linear-gradient(135deg,#e8b84b 0%,#ff6b35 100%);--font-head:'Bebas Neue',sans-serif;--font-body:'Inter',sans-serif;--radius:14px;--radius-lg:24px;--sidebar:240px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px;line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}img{max-width:100%;display:block}
.dash-layout{display:grid;grid-template-columns:var(--sidebar) 1fr;min-height:100vh}

/* SIDEBAR */
.sidebar{position:sticky;top:0;height:100vh;overflow-y:auto;background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column}
.sidebar::-webkit-scrollbar{width:4px}.sidebar::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.sidebar-logo{padding:18px 16px 14px;border-bottom:1px solid var(--border)}
.logo{font-family:var(--font-head);font-size:20px;font-weight:800;display:flex;align-items:center;gap:6px}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4);opacity:.7}}
.admin-badge{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;background:rgba(239,68,68,.15);color:var(--red);border:1px solid rgba(239,68,68,.25);padding:2px 8px;border-radius:50px;margin-left:auto}
.sidebar-user{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.user-avatar{width:34px;height:34px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:13px;color:#0a0a0b;flex-shrink:0}
.user-name{font-weight:600;font-size:12px}.user-role{font-size:10px;color:var(--red);text-transform:uppercase;font-weight:700;letter-spacing:.05em}
.sidebar-nav{padding:10px 8px;flex:1}
.nav-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:8px 8px 4px}
.nav-item{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:var(--radius);color:var(--muted);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;position:relative;cursor:pointer}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,.05)}
.nav-item.active{color:var(--white);background:rgba(232,184,75,.1)}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--gradient);border-radius:0 3px 3px 0}
.nav-item i{width:15px;text-align:center;font-size:13px}.nav-item.active i{color:var(--accent)}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:50px;line-height:1.2}
.sidebar-footer{padding:12px 16px;border-top:1px solid var(--border)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);padding:6px 0;transition:color .2s}
.sidebar-footer a:hover{color:var(--white)}

/* MAIN */
.dash-main{overflow:hidden;display:flex;flex-direction:column}
.dash-topbar{background:var(--dark);border-bottom:1px solid var(--border);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:sticky;top:0;z-index:100;flex-shrink:0}
.topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)}
.topbar-breadcrumb span{color:var(--white);font-weight:600}
.topbar-actions{display:flex;align-items:center;gap:10px}
.hamburger-dash{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:6px;border-radius:8px;background:rgba(255,255,255,.05)}
.hamburger-dash span{width:16px;height:2px;background:var(--white);border-radius:2px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;cursor:pointer;transition:all .25s;border:none;font-family:var(--font-body);white-space:nowrap}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(232,184,75,.3)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}.btn-outline:hover{border-color:rgba(255,255,255,.25)}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}.btn-danger:hover{background:rgba(239,68,68,.2)}
.btn-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}.btn-success:hover{background:rgba(34,197,94,.2)}
.btn-blue{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:var(--blue)}.btn-blue:hover{background:rgba(59,130,246,.2)}
.btn-warn{background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent)}.btn-warn:hover{background:rgba(232,184,75,.2)}
.btn-sm{padding:5px 12px;font-size:11px}

.dash-body{padding:24px;flex:1}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px;position:relative;overflow:hidden;transition:transform .2s}
.stat-card:hover{transform:translateY(-2px)}
.stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;margin-bottom:12px}
.stat-value{font-family:var(--font-head);font-size:26px;font-weight:800;line-height:1;margin-bottom:3px}
.stat-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.stat-sub{font-size:11px;margin-top:6px;color:var(--muted)}.stat-sub .up{color:var(--green)}

/* CARD */
.dash-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:20px}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px}
.card-header h3{font-family:var(--font-head);font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.card-header h3 i{color:var(--accent)}.card-body{padding:18px}

/* TABLE */
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:9px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap}
.data-table td{padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:middle;font-size:13px}
.data-table tr:last-child td{border-bottom:none}.data-table tr:hover td{background:rgba(255,255,255,.015)}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.badge-active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:var(--green)}
.badge-pending{background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);color:var(--accent)}
.badge-sold{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:var(--blue)}
.badge-rejected{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.badge-banned{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:var(--red)}
.badge-dealer{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:var(--green)}
.badge-admin{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.badge-buyer{background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted)}
.badge-featured{background:var(--gradient);color:#0a0a0b}

/* SEARCH */
.search-bar{display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.search-input{flex:1;min-width:160px;background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:9px 14px;border-radius:var(--radius);font-size:13px;font-family:var(--font-body);outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}.search-input::placeholder{color:var(--muted)}
.filter-select{background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:9px 12px;border-radius:var(--radius);font-size:12px;font-family:var(--font-body);outline:none;-webkit-appearance:none;cursor:pointer}
.filter-select option{background:var(--dark)}

.action-btns{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.quick-form{display:inline}

/* PAGINATION */
.pagination{display:flex;gap:5px;justify-content:center;margin-top:16px;padding-bottom:16px;flex-wrap:wrap}
.page-btn{padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;background:var(--card-bg);border:1px solid var(--border);color:var(--muted);transition:all .2s;text-decoration:none}
.page-btn:hover,.page-btn.active{background:rgba(232,184,75,.1);border-color:rgba(232,184,75,.3);color:var(--accent)}

/* ALERT */
.alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

.overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.activity-item{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.activity-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:6px}
.activity-text{font-size:12px;color:rgba(245,245,240,.8);line-height:1.5}
.activity-time{font-size:11px;color:var(--muted);margin-top:2px}

/* SETTINGS */
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
.form-group input,.form-group select{background:rgba(0,0,0,.4);border:1px solid var(--border);color:var(--white);padding:10px 14px;border-radius:var(--radius);font-size:13px;font-family:var(--font-body);outline:none;transition:border-color .2s;width:100%;-webkit-appearance:none}
.form-group input:focus,.form-group select:focus{border-color:var(--accent)}
.form-group select option{background:var(--dark)}.form-group .hint{font-size:11px;color:var(--muted)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}.toggle-label{font-size:13px;font-weight:500}.toggle-sub{font-size:11px;color:var(--muted);margin-top:2px}
.toggle-track{width:40px;height:22px;background:rgba(255,255,255,.1);border-radius:50px;position:relative;cursor:pointer;transition:background .25s;flex-shrink:0}
.toggle-track::after{content:'';position:absolute;width:16px;height:16px;background:#fff;border-radius:50%;top:3px;left:3px;transition:transform .25s}
.toggle-track.on{background:var(--gradient)}.toggle-track.on::after{transform:translateX(18px)}

.car-thumb{width:48px;height:38px;border-radius:6px;object-fit:cover;background:#111;flex-shrink:0}

/* ── MODALS ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;max-width:520px;width:100%;position:relative;animation:mIn .2s ease;max-height:90vh;overflow-y:auto}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--font-head);font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:4px}
.modal-sub{font-size:12px;color:var(--muted);margin-bottom:20px}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;line-height:1;transition:color .2s}
.modal-close:hover{color:var(--white)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.m-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);display:block;margin-bottom:5px}
.m-input{width:100%;background:rgba(0,0,0,.45);border:1px solid var(--border);color:var(--white);padding:10px 14px;border-radius:var(--radius);font-size:13px;font-family:var(--font-body);outline:none;transition:border-color .2s;margin-bottom:14px}
.m-input:focus{border-color:var(--accent)}.m-input::placeholder{color:var(--muted)}
.m-select{width:100%;background:rgba(0,0,0,.45);border:1px solid var(--border);color:var(--white);padding:10px 14px;border-radius:var(--radius);font-size:13px;font-family:var(--font-body);outline:none;-webkit-appearance:none;cursor:pointer;margin-bottom:14px;transition:border-color .2s}
.m-select:focus{border-color:var(--accent)}.m-select option{background:var(--dark)}
.m-textarea{width:100%;background:rgba(0,0,0,.45);border:1px solid var(--border);color:var(--white);padding:10px 14px;border-radius:var(--radius);font-size:13px;font-family:var(--font-body);outline:none;resize:vertical;min-height:80px;transition:border-color .2s;margin-bottom:14px;line-height:1.6}
.m-textarea:focus{border-color:var(--accent)}
.m-check{display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;font-size:13px;user-select:none}
.m-check input{accent-color:var(--accent);width:16px;height:16px;cursor:pointer;flex-shrink:0}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:198}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}.overview-grid{grid-template-columns:1fr}}
@media(max-width:900px){.dash-layout{grid-template-columns:1fr}.sidebar{position:fixed;left:-240px;z-index:200;transition:left .3s ease;height:100vh}.sidebar.open{left:0}.sidebar-overlay.show{display:block}.hamburger-dash{display:flex}}
@media(max-width:640px){.stats-grid{grid-template-columns:1fr 1fr}.dash-body{padding:14px}.settings-grid,.modal-grid{grid-template-columns:1fr}.data-table th:nth-child(n+5),.data-table td:nth-child(n+5){display:none}}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: EDIT LISTING (inline, no ownership check)
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    <div class="modal-title"><i class="fas fa-pen" style="color:var(--accent)"></i> Edit Listing</div>
    <div class="modal-sub" id="editSub">Loading…</div>
    <form method="POST" id="editForm">
      <?= CSRF::field() ?>
      <input type="hidden" name="action" value="admin_edit_listing">
      <input type="hidden" name="tab"    value="listings">
      <input type="hidden" name="car_id" id="editCarId">

      <div class="modal-grid">
        <div>
          <label class="m-label">Price (PKR) *</label>
          <input type="number" class="m-input" name="price" id="editPrice" min="10000" required>
        </div>
        <div>
          <label class="m-label">Mileage (km)</label>
          <input type="number" class="m-input" name="mileage" id="editMileage" min="0">
        </div>
        <div>
          <label class="m-label">City</label>
          <input type="text" class="m-input" name="city" id="editCity" placeholder="e.g. Karachi">
        </div>
        <div>
          <label class="m-label">Status</label>
          <select class="m-select" name="status" id="editStatus">
            <option value="active">Active — Live</option>
            <option value="pending">Pending — Review</option>
            <option value="sold">Sold</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <label class="m-label">Description</label>
      <textarea class="m-textarea" name="description" id="editDesc" rows="4" placeholder="Update description…"></textarea>

      <label class="m-check">
        <input type="checkbox" name="is_featured" id="editFeatured" value="1">
        Featured listing
      </label>
      <label class="m-check">
        <input type="checkbox" name="price_negotiable" id="editNegotiable" value="1">
        Price negotiable
      </label>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: REJECT LISTING
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
    <div class="modal-title"><i class="fas fa-ban" style="color:var(--red)"></i> Reject Listing</div>
    <div class="modal-sub">Give the seller a clear reason so they can fix it.</div>
    <form method="POST">
      <?= CSRF::field() ?>
      <input type="hidden" name="action" value="reject_listing">
      <input type="hidden" name="tab"    value="listings">
      <input type="hidden" name="car_id" id="rejectCarId">
      <label class="m-label">Rejection Reason</label>
      <input type="text" class="m-input" name="reason" placeholder="e.g. Missing photos, incomplete description…" required>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Reject</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: BAN IP
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="banIpModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('banIpModal')">✕</button>
    <div class="modal-title"><i class="fas fa-shield-halved" style="color:var(--red)"></i> Ban IP Address</div>
    <div class="modal-sub">Block all access from this IP.</div>
    <form method="POST">
      <?= CSRF::field() ?>
      <input type="hidden" name="action" value="ban_ip">
      <input type="hidden" name="tab"    value="users">
      <label class="m-label">IP Address</label>
      <input type="text" class="m-input" name="ip" placeholder="e.g. 192.168.1.100" required>
      <label class="m-label">Reason (optional)</label>
      <input type="text" class="m-input" name="ban_reason" placeholder="Spam, fraud, etc.">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('banIpModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Ban IP</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     LAYOUT
════════════════════════════════════════════════════════════ -->
<div class="dash-layout">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="index.php" class="logo">
      <span>Car</span><span style="color:var(--white)">Soko</span>
      <div class="logo-dot"></div>
      <div class="admin-badge">Admin</div>
    </a>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar">A</div>
    <div><div class="user-name">Administrator</div><div class="user-role">Admin</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Admin</div>
    <a href="?tab=overview"  class="nav-item <?= $tab==='overview' ?'active':'' ?>"><i class="fas fa-chart-pie"></i> Overview</a>
    <a href="?tab=listings"  class="nav-item <?= $tab==='listings'?'active':'' ?>">
      <i class="fas fa-car"></i> Listings
      <?php if ($stats['pending']): ?><span class="nav-badge"><?= $stats['pending'] ?></span><?php endif; ?>
    </a>
    <a href="?tab=users"     class="nav-item <?= $tab==='users'   ?'active':'' ?>"><i class="fas fa-users"></i> Users</a>
    <a href="?tab=reports"   class="nav-item <?= $tab==='reports' ?'active':'' ?>">
      <i class="fas fa-flag"></i> Reports
      <?php if ($stats['open_reports']): ?><span class="nav-badge"><?= $stats['open_reports'] ?></span><?php endif; ?>
    </a>
    <a href="?tab=blogs"     class="nav-item <?= $tab==='blogs'   ?'active':'' ?>"><i class="fas fa-newspaper"></i> Blogs</a>
    <a href="?tab=settings"  class="nav-item <?= $tab==='settings'?'active':'' ?>"><i class="fas fa-gear"></i> Settings</a>
    
    <div class="nav-label" style="margin-top:12px">Site</div>
    <a href="index.php"    class="nav-item"><i class="fas fa-home"></i> View Site</a>
    <a href="listings.php" class="nav-item"><i class="fas fa-list"></i> All Listings</a>
    <?php if (Auth::check()): ?>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> My Dashboard</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="?admin_logout=1" style="color:var(--red)"><i class="fas fa-lock"></i> Lock Admin Panel</a>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="hamburger-dash" onclick="openSidebar()"><span></span><span></span><span></span></div>
      <div class="topbar-breadcrumb">Admin Panel <i class="fas fa-chevron-right" style="font-size:9px;margin:0 3px"></i> <span><?= ucfirst($tab) ?></span></div>
    </div>
    <div class="topbar-actions">
      <?php if ($stats['pending']): ?>
      <a href="?tab=listings&status=pending" class="btn btn-accent btn-sm"><i class="fas fa-clock"></i> <?= $stats['pending'] ?> Pending</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-external-link-alt"></i> Site</a>
    </div>
  </div>

  <div class="dash-body">
    <?php showFlash('success'); showFlash('error'); ?>

<!-- ═══ OVERVIEW ════════════════════════════════════════════ -->
<?php if ($tab === 'overview'): ?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(232,184,75,.1);color:var(--accent)"><i class="fas fa-car"></i></div>
    <div class="stat-value"><?= number_format($stats['total_listings']) ?></div>
    <div class="stat-label">Total Listings</div>
    <div class="stat-sub"><span class="up">+<?= $stats['new_listings_7d'] ?></span> this week</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(34,197,94,.1);color:var(--green)"><i class="fas fa-users"></i></div>
    <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-sub"><span class="up">+<?= $stats['new_users_7d'] ?></span> this week</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,.1);color:var(--red)"><i class="fas fa-flag"></i></div>
    <div class="stat-value"><?= $stats['open_reports'] ?></div>
    <div class="stat-label">Open Reports</div>
    <div class="stat-sub"><?= $stats['banned_users'] ?> banned users</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--blue)"><i class="fas fa-comment-dots"></i></div>
    <div class="stat-value"><?= number_format($stats['total_messages']) ?></div>
    <div class="stat-label">Total Messages</div>
    <div class="stat-sub"><?= $stats['active_listings'] ?> active listings</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px">
  <?php foreach ([
    ['?tab=listings&status=active', '34,197,94', 'fa-circle-check', $stats['active_listings'], 'Active'],
    ['?tab=listings&status=pending','232,184,75','fa-clock',         $stats['pending'],         'Pending Review'],
    ['?tab=listings&status=sold',   '59,130,246','fa-handshake',     $stats['sold'],            'Sold'],
  ] as [$url,$rgb,$icon,$val,$lbl]): ?>
  <a href="admin.php<?= $url ?>" style="display:flex;align-items:center;gap:12px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px;transition:all .2s" onmouseover="this.style.borderColor='rgba(<?=$rgb?>,.35)'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="width:36px;height:36px;background:rgba(<?=$rgb?>,.1);border-radius:9px;display:flex;align-items:center;justify-content:center;color:rgb(<?=$rgb?>)"><i class="fas <?=$icon?>"></i></div>
    <div><div style="font-family:var(--font-head);font-size:22px;font-weight:700"><?=$val?></div><div style="font-size:11px;color:var(--muted)"><?=$lbl?></div></div>
  </a>
  <?php endforeach; ?>
</div>

<div class="overview-grid">
  <div class="dash-card">
    <div class="card-header"><h3><i class="fas fa-bolt"></i> Recent Activity</h3></div>
    <div class="card-body" style="padding:4px 18px">
      <?php if (empty($recentActivity)): ?>
      <div style="text-align:center;padding:30px;color:var(--muted);font-size:13px">No activity yet.</div>
      <?php else: foreach ($recentActivity as $act): ?>
      <div class="activity-item">
        <div class="activity-dot"></div>
        <div>
          <div class="activity-text"><strong><?= e($act['user_name'] ?? 'System') ?></strong> — <?= e(str_replace('_',' ',$act['action'])) ?><?php if ($act['target_id']): ?> <a href="<?= $act['target_type']==='car'?'listing.php?id=':'#' ?><?= $act['target_id'] ?>" style="color:var(--accent);font-size:11px">#<?= $act['target_id'] ?></a><?php endif; ?></div>
          <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="dash-card">
    <div class="card-header"><h3><i class="fas fa-user-plus"></i> Recent Registrations</h3><a href="?tab=users" style="font-size:12px;color:var(--accent)">View all →</a></div>
    <div class="card-body" style="padding:0">
      <table class="data-table">
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($recentUsers as $ru): ?>
        <tr>
          <td><div style="font-weight:600"><?= e($ru['name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= e($ru['email']) ?></div></td>
          <td><span class="badge badge-<?= $ru['role']==='dealer'?'dealer':($ru['role']==='admin'?'admin':'buyer') ?>"><?= ucfirst(str_replace('_',' ',$ru['role'])) ?></span></td>
          <td><span class="badge <?= $ru['status']==='active'?'badge-active':'badge-banned' ?>"><?= e($ru['status']) ?></span></td>
          <td style="color:var(--muted);font-size:12px"><?= timeAgo($ru['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; // overview ?>

<!-- ═══ LISTINGS ════════════════════════════════════════════ -->
<?php if ($tab === 'listings'): ?>
<div class="dash-card">
  <div class="card-header">
    <h3><i class="fas fa-car"></i> All Listings</h3>
    <span style="font-size:12px;color:var(--muted)"><?= number_format($totalRows) ?> found</span>
  </div>
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" class="search-bar">
      <input type="hidden" name="tab" value="listings">
      <input class="search-input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search make, model, seller, city…">
      <select class="filter-select" name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <?php foreach (['active','pending','sold','rejected'] as $s): ?>
        <option value="<?=$s?>" <?= ($_GET['status']??'') === $s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <div style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>Car</th><th>Seller</th><th>Price</th><th>Views</th><th>Status</th><th>Posted</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">No listings found.</td></tr>
      <?php else: foreach ($rows as $lst): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <img class="car-thumb" src="<?= carImageUrl($lst['image_path'],true) ?>" alt="">
            <div>
              <div style="font-weight:600;font-size:13px"><?= e($lst['year'].' '.$lst['make_name'].' '.$lst['model_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($lst['city']) ?> · #<?= $lst['id'] ?></div>
              <?php if ($lst['is_featured']): ?><span class="badge badge-featured" style="margin-top:3px">⭐ Featured</span><?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <div style="font-size:12px;font-weight:500"><?= e($lst['seller_name']) ?></div>
          <span class="badge badge-<?= $lst['seller_role']==='dealer'?'dealer':'buyer' ?>"><?= ucfirst(str_replace('_',' ',$lst['seller_role'])) ?></span>
        </td>
        <td style="font-weight:600;white-space:nowrap"><?= formatPKR((float)$lst['price'],true) ?></td>
        <td><?= number_format($lst['views']??0) ?></td>
        <td><span class="badge badge-<?= e($lst['status']) ?>"><?= ucfirst($lst['status']) ?></span></td>
        <td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= timeAgo($lst['created_at']) ?></td>
        <td>
          <div class="action-btns">
            <!-- VIEW -->
            <a href="listing.php?id=<?= $lst['id'] ?>" class="btn btn-sm btn-outline" title="View" target="_blank"><i class="fas fa-eye"></i></a>

            <!-- ✅ EDIT — opens modal, pre-filled, no ownership restriction -->
            <button type="button" class="btn btn-sm btn-warn" title="Edit Listing"
              onclick="openEditModal(
                <?= (int)$lst['id'] ?>,
                '<?= e(addslashes($lst['year'].' '.$lst['make_name'].' '.$lst['model_name'])) ?>',
                <?= (int)$lst['price'] ?>,
                <?= (int)($lst['mileage'] ?? 0) ?>,
                '<?= e(addslashes($lst['city'] ?? '')) ?>',
                '<?= e(addslashes(mb_substr($lst['description'] ?? '',0,400))) ?>',
                '<?= e($lst['status']) ?>',
                <?= $lst['is_featured']      ? 'true':'false' ?>,
                <?= ($lst['price_negotiable']??0) ? 'true':'false' ?>
              )">
              <i class="fas fa-pen"></i>
            </button>

            <!-- APPROVE (pending) -->
            <?php if ($lst['status']==='pending'): ?>
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="approve_listing"><input type="hidden" name="tab" value="listings"><input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></button>
            </form>
            <button class="btn btn-sm btn-danger" title="Reject" onclick="openRejectModal(<?= $lst['id'] ?>)"><i class="fas fa-ban"></i></button>
            <?php endif; ?>

            <!-- RESTORE (sold / rejected) -->
            <?php if (in_array($lst['status'],['sold','rejected'])): ?>
            <form class="quick-form" method="POST" onsubmit="return confirm('Restore to active?')">
              <?= CSRF::field() ?><input type="hidden" name="action" value="restore_listing"><input type="hidden" name="tab" value="listings"><input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
              <button type="submit" class="btn btn-sm btn-blue" title="Restore Active"><i class="fas fa-rotate-right"></i></button>
            </form>
            <?php endif; ?>

            <!-- FEATURED TOGGLE -->
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="toggle_featured_listing"><input type="hidden" name="tab" value="listings"><input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:rgba(232,184,75,.08);border:1px solid rgba(232,184,75,.2);color:var(--accent)" title="<?= $lst['is_featured']?'Remove Featured':'Make Featured' ?>"><i class="fas fa-star"></i></button>
            </form>

            <!-- DELETE -->
            <form class="quick-form" method="POST" onsubmit="return confirm('Permanently delete listing #<?= $lst['id'] ?>? This cannot be undone.')">
              <?= CSRF::field() ?><input type="hidden" name="action" value="delete_listing"><input type="hidden" name="tab" value="listings"><input type="hidden" name="car_id" value="<?= $lst['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Delete Permanently"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages>1): ?>
  <div class="pagination">
    <?php $base="admin.php?tab=listings&q=".urlencode($search)."&status=".urlencode($_GET['status']??'');
    for ($p=1;$p<=min($totalPages,10);$p++): ?>
    <a href="<?=$base?>&page=<?=$p?>" class="page-btn <?=$page===$p?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; // listings ?>

<!-- ═══ USERS ═══════════════════════════════════════════════ -->
<?php if ($tab === 'users'): ?>
<div class="dash-card">
  <div class="card-header">
    <h3><i class="fas fa-users"></i> All Users</h3>
    <button class="btn btn-sm btn-danger" onclick="openModal('banIpModal')"><i class="fas fa-shield-halved"></i> Ban IP</button>
  </div>
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" class="search-bar">
      <input type="hidden" name="tab" value="users">
      <input class="search-input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone…">
      <select class="filter-select" name="role" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <?php foreach (['buyer','private_seller','dealer','moderator','admin'] as $r): ?>
        <option value="<?=$r?>" <?= ($_GET['role']??'') === $r?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$r)) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active" <?= ($_GET['status']??'') === 'active'?'selected':'' ?>>Active</option>
        <option value="banned" <?= ($_GET['status']??'') === 'banned'?'selected':'' ?>>Banned</option>
      </select>
      <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i></button>
    </form>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>User</th><th>Role</th><th>Listings</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No users found.</td></tr>
      <?php else: foreach ($rows as $u): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-weight:700;font-size:13px;color:#0a0a0b;flex-shrink:0"><?= strtoupper(substr($u['name'],0,1)) ?></div>
            <div><div style="font-weight:600;font-size:13px"><?= e($u['name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= e($u['email']) ?></div></div>
          </div>
        </td>
        <td>
          <form class="quick-form" method="POST">
            <?= CSRF::field() ?><input type="hidden" name="action" value="change_role"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <select class="filter-select" name="role" onchange="this.form.submit()" style="font-size:11px;padding:4px 8px">
              <?php foreach (['buyer','private_seller','dealer','moderator','admin'] as $r): ?>
              <option value="<?=$r?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$r)) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><span style="font-weight:600"><?= $u['active_cars'] ?></span> <span style="color:var(--muted);font-size:11px">/ <?= $u['total_cars'] ?></span></td>
        <td>
          <span class="badge <?= $u['status']==='active'?'badge-active':'badge-banned' ?>"><?= e($u['status']) ?></span>
          <?php if ($u['is_verified_seller']??false): ?><span class="badge badge-dealer" style="margin-top:3px">✓ Verified</span><?php endif; ?>
        </td>
        <td style="font-size:11px;color:var(--muted)"><?= timeAgo($u['created_at']) ?></td>
        <td>
          <div class="action-btns">
            <?php if ($u['status']==='active'): ?>
            <form class="quick-form" method="POST" onsubmit="return confirm('Ban <?= e(addslashes($u['name'])) ?>?')">
              <?= CSRF::field() ?><input type="hidden" name="action" value="ban_user"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Ban"><i class="fas fa-ban"></i></button>
            </form>
            <?php else: ?>
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="unban_user"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success" title="Unban"><i class="fas fa-circle-check"></i></button>
            </form>
            <?php endif; ?>
            <?php if (!($u['is_verified_seller']??false) && in_array($u['role'],['dealer','private_seller'])): ?>
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="verify_seller"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-blue" title="Verify Seller"><i class="fas fa-badge-check"></i></button>
            </form>
            <?php endif; ?>
            <form class="quick-form" method="POST" onsubmit="return confirm('DELETE <?= e(addslashes($u['name'])) ?> and all their listings? CANNOT be undone.')">
              <?= CSRF::field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
  <div class="pagination">
    <?php $base="admin.php?tab=users&q=".urlencode($search)."&role=".urlencode($_GET['role']??'')."&status=".urlencode($_GET['status']??'');
    for ($p=1;$p<=min($totalPages,10);$p++): ?>
    <a href="<?=$base?>&page=<?=$p?>" class="page-btn <?=$page===$p?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; // users ?>

<!-- ═══ REPORTS ══════════════════════════════════════════════ -->
<?php if ($tab === 'reports'): ?>
<div class="dash-card">
  <div class="card-header"><h3><i class="fas fa-flag"></i> Open Reports (<?= $totalRows ?>)</h3></div>
  <?php if (empty($rows)): ?>
  <div style="text-align:center;padding:60px;color:var(--muted)">
    <i class="fas fa-shield-halved" style="font-size:44px;opacity:.15;display:block;margin-bottom:14px"></i>
    <div style="font-family:var(--font-head);font-size:18px;font-weight:700;margin-bottom:6px">All clear!</div>
    <div style="font-size:13px">No open reports.</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>Reported Listing</th><th>Reporter</th><th>Reason</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $rep): ?>
      <tr>
        <td>
          <a href="listing.php?id=<?= $rep['car_id'] ?>" target="_blank" style="color:var(--accent);font-weight:600;font-size:13px"><?= e($rep['year'].' '.$rep['make_name'].' '.$rep['model_name']) ?> <i class="fas fa-external-link-alt" style="font-size:10px"></i></a>
          <div style="font-size:11px;color:var(--muted)">Owner: <?= e($rep['car_owner_name']) ?> · <?= e($rep['city']) ?></div>
        </td>
        <td><div style="font-size:12px;font-weight:500"><?= e($rep['reporter_name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= e($rep['reporter_email']) ?></div></td>
        <td style="max-width:220px"><div style="font-size:12px;color:rgba(245,245,240,.8);line-height:1.5"><?= e(mb_substr($rep['description']??$rep['reason']??'',0,130)) ?><?= mb_strlen($rep['description']??'')>130?'…':'' ?></div></td>
        <td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= timeAgo($rep['created_at']) ?></td>
        <td>
          <div class="action-btns">
            <a href="listing.php?id=<?= $rep['car_id'] ?>" class="btn btn-sm btn-outline" target="_blank"><i class="fas fa-eye"></i></a>
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="resolve_report"><input type="hidden" name="tab" value="reports"><input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Resolve</button>
            </form>
            <form class="quick-form" method="POST">
              <?= CSRF::field() ?><input type="hidden" name="action" value="dismiss_report"><input type="hidden" name="tab" value="reports"><input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-times"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; // reports ?>

<!-- ═══ SETTINGS ═════════════════════════════════════════════ -->
<?php if ($tab === 'settings'): ?>
<form method="POST">
  <?= CSRF::field() ?>
  <input type="hidden" name="action" value="save_settings">
  <input type="hidden" name="tab"    value="settings">

  <!-- General Site Settings -->
  <div class="dash-card" style="margin-bottom:20px">
    <div class="card-header"><h3><i class="fas fa-globe"></i> General Site Settings</h3></div>
    <div class="card-body">
      <div class="settings-grid">
        <?php $genFields=[
          ['site_name',          'Site Name',                   'text',  'CarSoko'],
          ['site_email',         'Contact Email (public)',       'email', 'info@carsoko.pk'],
          ['admin_email',        'Admin Notification Email',    'email', 'admin@carsoko.pk'],
          ['site_phone',         'Contact Phone (topbar/footer)','text', '+92 300 000 0000'],
          ['site_city',          'City (shown in topbar)',       'text',  'Karachi'],
          ['whatsapp_number',    'WhatsApp Number (Floating Btn)','text','923000000000'],
          ['listings_per_page',  'Listings Per Page',           'number','12'],
          ['free_listing_limit', 'Free Listing Limit',          'number','3'],
        ];
        foreach ($genFields as [$k,$lbl,$type,$ph]): ?>
        <div class="form-group">
          <label><?= $lbl ?></label>
          <input type="<?= $type ?>" name="settings[<?= $k ?>]" value="<?= e($siteSettings[$k] ?? '') ?>" placeholder="<?= $ph ?>">
          <?php if ($k==='whatsapp_number'): ?><div class="hint">Country code + number, no + sign. e.g. 923001234567</div><?php endif; ?>
          <?php if ($k==='site_phone'): ?><div class="hint">Displayed in topbar and contact sections across the site</div><?php endif; ?>
          <?php if ($k==='free_listing_limit'): ?><div class="hint">Max free listings per private seller account</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Social Media Links -->
  <div class="dash-card" style="margin-bottom:20px">
    <div class="card-header"><h3><i class="fas fa-share-alt"></i> Social Media Links &nbsp;<small style="font-size:12px;color:#888896;font-weight:400">Leave blank to hide icon from footer</small></h3></div>
    <div class="card-body">
      <div class="settings-grid">
        <?php $socialFields=[
          ['facebook_url',  'Facebook Page URL',  'fab fa-facebook-f',  'https://facebook.com/yourpage'],
          ['instagram_url', 'Instagram Profile URL','fab fa-instagram',  'https://instagram.com/yourhandle'],
          ['twitter_url',   'X (Twitter) Profile URL','fab fa-x-twitter','https://x.com/yourhandle'],
          ['whatsapp_url',  'WhatsApp Direct Link','fab fa-whatsapp',   'https://wa.me/923001234567'],
          ['linkedin_url',  'LinkedIn Page URL',  'fab fa-linkedin-in', 'https://linkedin.com/company/yourpage'],
        ];
        foreach ($socialFields as [$k,$lbl,$icon,$ph]): ?>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px"><i class="<?= $icon ?>" style="color:#e8b84b;width:16px;text-align:center"></i> <?= $lbl ?></label>
          <input type="url" name="settings[<?= $k ?>]" value="<?= e($siteSettings[$k] ?? '') ?>" placeholder="<?= $ph ?>">
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:14px;padding:12px 16px;background:rgba(232,184,75,0.06);border:1px solid rgba(232,184,75,0.15);border-radius:8px;font-size:13px;color:#888896;">
        <i class="fas fa-info-circle" style="color:#e8b84b"></i>&nbsp; Social icons in the footer <strong style="color:#f5f5f0">automatically hide</strong> if their URL is left empty — no code changes needed.
      </div>
    </div>
  </div>

  <!-- Feature Flags -->
  <div class="dash-card" style="margin-bottom:20px">
    <div class="card-header"><h3><i class="fas fa-toggle-on"></i> Feature Flags</h3></div>
    <div class="card-body" style="padding:0 18px">
      <?php foreach ([
        ['require_approval','Require Listing Approval','New listings go to pending and need admin approval before going live'],
        ['maintenance_mode', 'Maintenance Mode',        'Temporarily closes the site to all visitors (admins still access)'],
      ] as [$k,$lbl,$sub]): ?>
      <div class="toggle-row">
        <div><div class="toggle-label"><?= $lbl ?></div><div class="toggle-sub"><?= $sub ?></div></div>
        <label style="cursor:pointer;display:flex;align-items:center">
          <input type="hidden" name="settings[<?=$k?>]" value="0">
          <input type="checkbox" name="settings[<?=$k?>]" value="1" <?= ($siteSettings[$k] ?? '')?'checked':'' ?> style="display:none"
            onchange="this.previousElementSibling.value=this.checked?1:0;this.closest('label').querySelector('.toggle-track').classList.toggle('on',this.checked)">
          <div class="toggle-track <?= ($siteSettings[$k] ?? '')?'on':'' ?>"
            onclick="var cb=this.previousElementSibling.previousElementSibling;cb.checked=!cb.checked;cb.previousElementSibling.value=cb.checked?1:0;this.classList.toggle('on',cb.checked)"></div>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <button type="submit" class="btn btn-accent" style="padding:12px 32px;font-size:14px"><i class="fas fa-save"></i> Save All Settings</button>
</form>
<?php endif; // settings ?>

<!-- ═══ BLOGS ════════════════════════════════════════════════ -->
<?php if ($tab === 'blogs'): ?>
<div class="dash-card">
  <div class="card-header">
    <h3><i class="fas fa-newspaper"></i> Manage Blog Posts</h3>
    <a href="blog.php?action=write" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i> Write New Post</a>
  </div>
  <div class="card-body" style="padding:0">
    <table class="dash-table" style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:15px 20px;font-size:12px;color:var(--muted);text-transform:uppercase">Title</th>
          <th style="padding:15px 20px;font-size:12px;color:var(--muted);text-transform:uppercase">Author</th>
          <th style="padding:15px 20px;font-size:12px;color:var(--muted);text-transform:uppercase">Status</th>
          <th style="padding:15px 20px;font-size:12px;color:var(--muted);text-transform:uppercase">Date</th>
          <th style="padding:15px 20px;font-size:12px;color:var(--muted);text-transform:uppercase;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:15px 20px">
            <div style="font-weight:600;color:var(--white)"><?= e($r['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)">/blog/<?= e($r['slug']) ?></div>
          </td>
          <td style="padding:15px 20px;color:var(--white)"><?= e($r['author_name'] ?: 'System') ?></td>
          <td style="padding:15px 20px">
            <span class="badge badge-<?= $r['status']==='published'?'success':'warning' ?>">
              <?= ucfirst($r['status']) ?>
            </span>
          </td>
          <td style="padding:15px 20px;font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          <td style="padding:15px 20px;text-align:right">
            <div style="display:flex;gap:8px;justify-content:flex-end">
              <a href="blog.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline" title="View"><i class="fas fa-eye"></i></a>
              <form method="POST" onsubmit="return confirm('Delete this blog post? This cannot be undone.')" style="display:inline">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="delete_blog">
                <input type="hidden" name="tab" value="blogs">
                <input type="hidden" name="blog_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; if (empty($rows)): ?>
        <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">No blog posts found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <?php if ($totalPages > 1): ?>
  <div class="pagination" style="padding:20px;border-top:1px solid var(--border)">
    <?php for ($p=1; $p<=$totalPages; $p++): ?>
    <a href="?tab=blogs&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; // blogs ?>

  </div><!-- /dash-body -->
</main>
</div><!-- /dash-layout -->

<script>
// ── SIDEBAR ──
function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('show'); document.body.style.overflow='hidden'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); document.body.style.overflow=''; }

// ── MODALS ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});

// ── REJECT MODAL ──
function openRejectModal(carId) {
    document.getElementById('rejectCarId').value = carId;
    openModal('rejectModal');
}

// ── EDIT MODAL ──
// Receives all field values from PHP inline onclick — no AJAX needed, no ownership check
function openEditModal(id, name, price, mileage, city, desc, status, featured, negotiable) {
    document.getElementById('editCarId').value      = id;
    document.getElementById('editSub').textContent  = 'Editing: ' + name + ' — ID #' + id;
    document.getElementById('editPrice').value      = price;
    document.getElementById('editMileage').value    = mileage;
    document.getElementById('editCity').value       = city;
    document.getElementById('editDesc').value       = desc;
    document.getElementById('editStatus').value     = status;
    document.getElementById('editFeatured').checked    = featured;
    document.getElementById('editNegotiable').checked  = negotiable;
    openModal('editModal');
}
</script>
</body>
</html>