<?php
// ============================================================
//  CarSoko Pakistan — notifications.php
//  All notifications for logged-in user
// ============================================================
require_once 'connection.php';
Auth::requireLogin('/login.php');

$me       = Auth::user();
$myId     = Auth::id();
$isSeller = Auth::is('dealer', 'private_seller', 'admin', 'moderator');

// ── HANDLE ACTIONS ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = cleanInput($_POST['action'] ?? '');

    if ($action === 'mark_all_read') {
        DB::execute("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0", [$myId]);
        flash('success', 'All notifications marked as read.');
    }

    if ($action === 'delete_all') {
        DB::execute("DELETE FROM notifications WHERE user_id=? AND is_read=1", [$myId]);
        flash('success', 'Read notifications cleared.');
    }

    if ($action === 'mark_one') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        if ($nid) DB::execute("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?", [$nid, $myId]);
    }

    redirect(BASE_URL . '/notifications.php');
}

// ── MARK ALL AS READ ON PAGE VISIT ───────────────────────────
DB::execute("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0", [$myId]);

// ── LOAD NOTIFICATIONS (paginated) ───────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$total   = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id=?", [$myId]);
$pages   = max(1, ceil($total / $perPage));

$notifications = DB::select(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$myId, $perPage, $offset]
);

// ── COUNTS ───────────────────────────────────────────────────
$totalUnread = getUnreadCount();   // messages
$notifCount  = getNotificationCount(); // (will be 0 now after marking read)

// ── ICON / COLOUR MAP ────────────────────────────────────────
function notifIcon(string $type): array {
    switch($type) {
        case 'new_message':          return ['fas fa-comment-dots', '#3b82f6'];
        case 'test_drive_request':   return ['fas fa-car-side',    '#e8b84b'];
        case 'test_drive_confirmed': return ['fas fa-calendar-check', '#22c55e'];
        case 'test_drive_declined':  return ['fas fa-calendar-times', '#ef4444'];
        case 'car_approved':         return ['fas fa-check-circle',  '#22c55e'];
        case 'car_rejected':         return ['fas fa-times-circle',  '#ef4444'];
        case 'new_review':           return ['fas fa-star',           '#f59e0b'];
        case 'offer_received':       return ['fas fa-tag',            '#8b5cf6'];
        default:                     return ['fas fa-bell',           '#888896'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notifications | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --black:#0a0a0b;--dark:#111114;--card-bg:#18181c;
  --border:rgba(255,255,255,.07);--white:#f5f5f0;--muted:#888896;
  --accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;--blue:#3b82f6;
  --gradient:linear-gradient(135deg,#e8b84b,#ff6b35);
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:10px;--sidebar:260px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;min-height:100%}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px}
a{color:inherit;text-decoration:none}

/* ── LAYOUT ── */
.layout{display:grid;grid-template-columns:var(--sidebar) 1fr;min-height:100vh}

/* ── SIDEBAR ── */
.sidebar{background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar::-webkit-scrollbar{width:3px}
.sidebar::-webkit-scrollbar-thumb{background:var(--border)}
.sidebar-logo{padding:20px;border-bottom:1px solid var(--border)}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.sidebar-user{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:14px;font-weight:700;color:#0a0a0b;flex-shrink:0}
.user-name{font-size:13px;font-weight:600}
.user-role{font-size:11px;color:var(--muted)}
.sidebar-nav{padding:12px 10px;flex:1}
.nav-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:8px 10px 4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);color:var(--muted);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;position:relative}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,.05)}
.nav-item.active{color:var(--white);background:rgba(232,184,75,.1)}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--gradient);border-radius:0 3px 3px 0}
.nav-item.active i{color:var(--accent)}
.nav-item i{width:16px;text-align:center}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:50px;min-width:18px;text-align:center}
.sidebar-footer{padding:14px 18px;border-top:1px solid var(--border)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;padding:6px 0;transition:color .2s}
.sidebar-footer a:hover{color:var(--white)}

/* ── MAIN ── */
.main{padding:32px 36px;overflow-y:auto}
.page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-title{font-family:var(--font-head);font-size:22px;font-weight:800}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.head-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;border:none;font-family:var(--font-body)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--muted)}
.btn-outline:hover{border-color:rgba(255,255,255,.2);color:var(--white)}
.btn-ghost-red{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:var(--red)}
.btn-ghost-red:hover{background:rgba(239,68,68,.15)}

/* Alert */
.alert{padding:11px 16px;border-radius:var(--radius);font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:18px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}

/* Notif list */
.notif-list{display:flex;flex-direction:column;gap:0}
.notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border-bottom:1px solid var(--border);transition:background .15s;position:relative}
.notif-item:first-child{border-top:1px solid var(--border);border-radius:var(--radius) var(--radius) 0 0}
.notif-item:last-child{border-radius:0 0 var(--radius) var(--radius)}
.notif-item:hover{background:rgba(255,255,255,.025)}
.notif-item.unread{background:rgba(232,184,75,.04)}
.notif-item.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gradient);border-radius:0 0 0 var(--radius)}
.notif-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:rgba(255,255,255,.05)}
.notif-body{flex:1;min-width:0}
.notif-title{font-size:14px;font-weight:600;line-height:1.4;margin-bottom:3px}
.notif-text{font-size:13px;color:var(--muted);line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.notif-meta{display:flex;align-items:center;gap:10px;margin-top:6px}
.notif-time{font-size:11px;color:var(--muted)}
.notif-type-badge{font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,.06);color:var(--muted);text-transform:capitalize}
.notif-action{flex-shrink:0}
.notif-link-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:50px;background:rgba(232,184,75,.1);color:var(--accent);font-size:12px;font-weight:600;border:1px solid rgba(232,184,75,.2);transition:all .2s}
.notif-link-btn:hover{background:rgba(232,184,75,.18)}

/* Empty */
.notif-empty{text-align:center;padding:80px 24px;color:var(--muted)}
.notif-empty i{font-size:48px;opacity:.15;display:block;margin-bottom:16px}
.notif-empty h3{font-family:var(--font-head);font-size:18px;font-weight:700;color:rgba(245,245,240,.2);margin-bottom:8px}
.notif-empty p{font-size:13px;line-height:1.7;max-width:280px;margin:0 auto}

/* Pagination */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:28px}
.page-btn{width:36px;height:36px;border-radius:8px;background:var(--card-bg);border:1px solid var(--border);color:var(--muted);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;transition:all .2s}
.page-btn:hover{border-color:rgba(232,184,75,.3);color:var(--white)}
.page-btn.active{background:var(--gradient);color:#0a0a0b;border-color:transparent}

/* Type filter tabs */
.filter-tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.filter-tab{padding:6px 16px;border-radius:50px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);color:var(--muted);background:transparent;transition:all .2s;font-family:var(--font-body)}
.filter-tab:hover{color:var(--white);border-color:rgba(255,255,255,.2)}
.filter-tab.active{background:var(--gradient);color:#0a0a0b;border-color:transparent}

@media(max-width:768px){.layout{grid-template-columns:1fr}.sidebar{display:none}.main{padding:20px 16px}}
</style>
</head>
<body>
<div class="layout">

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <a href="index.php" class="logo">
      <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
    </a>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($me['name'],0,1)) ?></div>
    <div>
      <div class="user-name"><?= e($me['name']) ?></div>
      <div class="user-role"><?= ucfirst(str_replace('_',' ',$me['role'])) ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <?php if ($isSeller): ?>
    <a href="post-listing.php" class="nav-item"><i class="fas fa-plus-circle"></i> Post a Car</a>
    <?php endif; ?>
    <div class="nav-label" style="margin-top:12px">Inbox</div>
    <a href="messages.php" class="nav-item"><i class="fas fa-comment-dots"></i> Messages
      <?php if ($totalUnread > 0): ?><span class="nav-badge"><?= $totalUnread ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" class="nav-item active"><i class="fas fa-bell"></i> Notifications</a>
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

<!-- ══ MAIN ══ -->
<main class="main">

  <div class="page-head">
    <div>
      <div class="page-title"><i class="fas fa-bell" style="color:var(--accent);margin-right:10px"></i>Notifications</div>
      <div class="page-sub"><?= $total ?> total notification<?= $total !== 1 ? 's' : '' ?></div>
    </div>
    <?php if (!empty($notifications)): ?>
    <div class="head-actions">
      <form method="POST" style="display:inline">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-outline"><i class="fas fa-check-double"></i> Mark all read</button>
      </form>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete all read notifications?')">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="delete_all">
        <button type="submit" class="btn btn-ghost-red"><i class="fas fa-trash-alt"></i> Clear read</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <?php showFlash('success'); ?>

  <?php if (empty($notifications)): ?>
  <div class="notif-empty">
    <i class="fas fa-bell-slash"></i>
    <h3>All clear!</h3>
    <p>You have no notifications yet. When someone messages you or activity happens on your listings, it'll show up here.</p>
  </div>
  <?php else: ?>

  <div class="notif-list">
    <?php foreach ($notifications as $n):
      [$icon, $iconColor] = notifIcon($n['type']);
      $hasLink = !empty($n['link']);
    ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
      <div class="notif-icon" style="background:<?= $iconColor ?>18;color:<?= $iconColor ?>">
        <i class="<?= $icon ?>"></i>
      </div>
      <div class="notif-body">
        <div class="notif-title"><?= e($n['title']) ?></div>
        <?php if ($n['body']): ?>
        <div class="notif-text"><?= e($n['body']) ?></div>
        <?php endif; ?>
        <div class="notif-meta">
          <span class="notif-time"><i class="fas fa-clock" style="font-size:9px;margin-right:3px"></i><?= timeAgo($n['created_at']) ?></span>
          <span class="notif-type-badge"><?= e(str_replace('_',' ',$n['type'])) ?></span>
        </div>
      </div>
      <?php if ($hasLink): ?>
      <div class="notif-action">
        <a href="<?= e($n['link']) ?>" class="notif-link-btn">View <i class="fas fa-arrow-right" style="font-size:10px"></i></a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>

</main>
</div>
</body>
</html>