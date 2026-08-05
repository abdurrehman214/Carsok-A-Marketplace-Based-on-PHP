<?php
// ============================================================
//  CarSoko Pakistan — profile.php
// ============================================================
require_once 'connection.php';
Auth::requireLogin('/login.php');

$me   = Auth::user();
$myId = Auth::id();
$isSeller = Auth::is('dealer', 'private_seller', 'admin', 'moderator');

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = cleanInput($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name  = cleanInput($_POST['name']  ?? '');
        $phone = cleanInput($_POST['phone'] ?? '');
        $city  = cleanInput($_POST['city']  ?? '');
        $bio   = cleanInput($_POST['bio']   ?? '');
        $bname = cleanInput($_POST['business_name'] ?? '');

        if (strlen($name) < 2) {
            flash('error', 'Name must be at least 2 characters.');
        } else {
            DB::execute(
                "UPDATE users SET name=?, phone=?, city=?, bio=?, business_name=?, updated_at=NOW() WHERE id=?",
                [$name, $phone, $city, $bio, $bname, $myId]
            );
            // Refresh session
            unset($_SESSION['user_data']);
            flash('success', 'Profile updated successfully!');
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!Auth::verifyPassword($current, $me['password'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            DB::execute(
                "UPDATE users SET password=?, updated_at=NOW() WHERE id=?",
                [Auth::hashPassword($new), $myId]
            );
            flash('success', 'Password changed successfully!');
        }
    }

    redirect(BASE_URL . '/profile.php');
}

// ── STATS ────────────────────────────────────────────────────
$totalUnread = getUnreadCount();
$notifCount  = getNotificationCount();
$msgCount    = (int) DB::value(
    "SELECT COUNT(DISTINCT conversation_id) FROM messages WHERE sender_id=?", [$myId]
);
$listingCount = $isSeller
    ? (int) DB::value("SELECT COUNT(*) FROM cars WHERE user_id=? AND status='active'", [$myId])
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --black:#0a0a0b;--dark:#111114;--card-bg:#18181c;
  --border:rgba(255,255,255,.07);--white:#f5f5f0;--muted:#888896;
  --accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;
  --gradient:linear-gradient(135deg,#e8b84b,#ff6b35);
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:10px;--sidebar:260px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px}
a{color:inherit;text-decoration:none}
.layout{display:grid;grid-template-columns:var(--sidebar) 1fr;min-height:100vh}

/* SIDEBAR */
.sidebar{background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar::-webkit-scrollbar{width:3px}.sidebar::-webkit-scrollbar-thumb{background:var(--border)}
.sidebar-logo{padding:20px;border-bottom:1px solid var(--border)}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.sb-user{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-avatar{width:36px;height:36px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:14px;font-weight:700;color:#0a0a0b;flex-shrink:0}
.sb-name{font-size:13px;font-weight:600}.sb-role{font-size:11px;color:var(--muted)}
.sb-nav{padding:12px 10px;flex:1}
.nav-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:8px 10px 4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);color:var(--muted);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;position:relative}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,.05)}
.nav-item.active{color:var(--white);background:rgba(232,184,75,.1)}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--gradient);border-radius:0 3px 3px 0}
.nav-item.active i{color:var(--accent)}.nav-item i{width:16px;text-align:center}
.nbadge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:50px}
.sb-footer{padding:14px 18px;border-top:1px solid var(--border)}
.sb-footer a{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;padding:6px 0;transition:color .2s}
.sb-footer a:hover{color:var(--white)}

/* MAIN */
.main{padding:32px 36px;max-width:900px}
.page-title{font-family:var(--font-head);font-size:22px;font-weight:800;margin-bottom:4px}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:28px}

/* Alert */
.alert{padding:12px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
.stat-box{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:18px;text-align:center}
.stat-num{font-family:var(--font-head);font-size:26px;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-lbl{font-size:12px;color:var(--muted);margin-top:3px}

/* Profile card */
.profile-hero{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:24px;display:flex;align-items:center;gap:20px}
.big-avatar{width:72px;height:72px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:28px;font-weight:800;color:#0a0a0b;flex-shrink:0}
.profile-hero-info h2{font-family:var(--font-head);font-size:20px;font-weight:700}
.profile-hero-info .role-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(232,184,75,.1);color:var(--accent);border:1px solid rgba(232,184,75,.2);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-top:6px}
.profile-hero-info .joined{font-size:12px;color:var(--muted);margin-top:6px}

/* Cards */
.card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;margin-bottom:20px;overflow:hidden}
.card-head{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-head h3{font-family:var(--font-head);font-size:15px;font-weight:700}
.card-head i{color:var(--accent);font-size:15px}
.card-body{padding:22px}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-field{display:flex;flex-direction:column;gap:5px}
.form-field.full{grid-column:1/-1}
.form-field label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.form-field input,.form-field textarea,.form-field select{background:rgba(0,0,0,.3);border:1px solid var(--border);color:var(--white);padding:10px 13px;border-radius:var(--radius);font-size:13px;outline:none;transition:border-color .2s;font-family:var(--font-body);width:100%}
.form-field input:focus,.form-field textarea:focus{border-color:rgba(232,184,75,.4)}
.form-field textarea{resize:vertical;min-height:80px}
.form-actions{display:flex;justify-content:flex-end;margin-top:20px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:var(--font-body);transition:all .2s}
.btn-accent{background:var(--gradient);color:#0a0a0b}
.btn-accent:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(232,184,75,.35)}
.btn-ghost{background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--white)}
.btn-ghost:hover{border-color:rgba(255,255,255,.2)}

/* Danger zone */
.danger-item{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)}
.danger-item:last-child{border-bottom:none}
.danger-label{font-size:13px;font-weight:600}
.danger-sub{font-size:12px;color:var(--muted);margin-top:2px}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:var(--red);padding:8px 18px;border-radius:50px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--font-body);transition:all .2s}
.btn-danger:hover{background:rgba(239,68,68,.2)}

@media(max-width:900px){.form-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.layout{grid-template-columns:1fr}.sidebar{display:none}.main{padding:20px 16px}}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <a href="index.php" class="logo"><span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div></a>
  </div>
  <div class="sb-user">
    <div class="sb-avatar"><?= strtoupper(substr($me['name'],0,1)) ?></div>
    <div><div class="sb-name"><?= e($me['name']) ?></div><div class="sb-role"><?= ucfirst(str_replace('_',' ',$me['role'])) ?></div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <?php if ($isSeller): ?>
    <a href="post-listing.php" class="nav-item"><i class="fas fa-plus-circle"></i> Post a Car</a>
    <?php endif; ?>
    <div class="nav-label" style="margin-top:12px">Inbox</div>
    <a href="messages.php" class="nav-item"><i class="fas fa-comment-dots"></i> Messages
      <?php if ($totalUnread > 0): ?><span class="nbadge"><?= $totalUnread ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications
      <?php if ($notifCount > 0): ?><span class="nbadge"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <div class="nav-label" style="margin-top:12px">Account</div>
    <a href="profile.php" class="nav-item active"><i class="fas fa-user-circle"></i> My Profile</a>
    <?php if (Auth::isModerator()): ?>
    <a href="admin.php" class="nav-item" style="color:var(--accent)"><i class="fas fa-shield-halved"></i> Admin Panel</a>
    <?php endif; ?>
  </nav>
  <div class="sb-footer">
    <a href="index.php"><i class="fas fa-home"></i> Back to Site</a>
    <a href="logout.php" style="color:var(--red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="page-title">My Profile</div>
  <div class="page-sub">Manage your account information and settings</div>

  <?php showFlash('success'); showFlash('error'); ?>

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="big-avatar"><?= strtoupper(substr($me['name'],0,1)) ?></div>
    <div class="profile-hero-info">
      <h2><?= e($me['name']) ?></h2>
      <?php if ($me['email']): ?>
      <div style="font-size:13px;color:var(--muted);margin-top:4px"><i class="fas fa-envelope" style="font-size:11px;margin-right:5px"></i><?= e($me['email']) ?></div>
      <?php endif; ?>
      <div class="role-badge"><i class="fas fa-circle" style="font-size:6px"></i><?= ucfirst(str_replace('_',' ',$me['role'])) ?></div>
      <div class="joined"><i class="fas fa-calendar-alt" style="font-size:10px;margin-right:4px"></i>Member since <?= date('F Y', strtotime($me['created_at'] ?? 'now')) ?></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <?php if ($isSeller): ?>
    <div class="stat-box">
      <div class="stat-num"><?= $listingCount ?></div>
      <div class="stat-lbl">Active Listings</div>
    </div>
    <?php endif; ?>
    <div class="stat-box">
      <div class="stat-num"><?= $msgCount ?></div>
      <div class="stat-lbl">Conversations</div>
    </div>
  </div>

  <!-- Edit Profile -->
  <div class="card">
    <div class="card-head">
      <i class="fas fa-user-edit"></i>
      <h3>Edit Profile</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-field">
            <label>Full Name <span style="color:var(--red)">*</span></label>
            <input type="text" name="name" value="<?= e($me['name']) ?>" required>
          </div>
          <div class="form-field">
            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="+92 3XX XXX XXXX">
          </div>
          <div class="form-field">
            <label>City</label>
            <input type="text" name="city" value="<?= e($me['city'] ?? '') ?>" placeholder="e.g. Karachi">
          </div>
          <?php if ($isSeller): ?>
          <div class="form-field">
            <label>Business / Dealer Name</label>
            <input type="text" name="business_name" value="<?= e($me['business_name'] ?? '') ?>" placeholder="Your dealership name">
          </div>
          <?php endif; ?>
          <div class="form-field full">
            <label>Bio <span style="color:var(--muted);font-weight:400;text-transform:none">(optional)</span></label>
            <textarea name="bio" placeholder="Tell buyers a little about yourself…"><?= e($me['bio'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-head">
      <i class="fas fa-lock"></i>
      <h3>Change Password</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="form-grid">
          <div class="form-field full">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
          </div>
          <div class="form-field">
            <label>New Password</label>
            <input type="password" name="new_password" required minlength="6">
          </div>
          <div class="form-field">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-accent"><i class="fas fa-key"></i> Change Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Account Info (read-only) -->
  <div class="card">
    <div class="card-head">
      <i class="fas fa-shield-halved"></i>
      <h3>Account Details</h3>
    </div>
    <div class="card-body">
      <div class="danger-item">
        <div>
          <div class="danger-label">Email Address</div>
          <div class="danger-sub"><?= e($me['email']) ?></div>
        </div>
        <span style="font-size:11px;color:var(--muted);background:rgba(255,255,255,.05);padding:3px 10px;border-radius:20px">
          <?= $me['email_verified'] ? '✅ Verified' : '⚠️ Not verified' ?>
        </span>
      </div>
      <div class="danger-item">
        <div>
          <div class="danger-label">Account Role</div>
          <div class="danger-sub"><?= ucfirst(str_replace('_',' ',$me['role'])) ?></div>
        </div>
      </div>
      <div class="danger-item">
        <div>
          <div class="danger-label">Account Status</div>
          <div class="danger-sub">Your account is active</div>
        </div>
        <span style="font-size:11px;color:var(--green);background:rgba(34,197,94,.1);padding:3px 10px;border-radius:20px;border:1px solid rgba(34,197,94,.2)">● Active</span>
      </div>
      <div class="danger-item" style="border-bottom:none">
        <div>
          <div class="danger-label">Sign Out</div>
          <div class="danger-sub">Sign out of your account on this device</div>
        </div>
        <a href="logout.php" class="btn-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </div>
  </div>

</main>
</div>
</body>
</html>