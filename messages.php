<?php
// ============================================================
//  CarSoko Pakistan - messages.php
// ============================================================
require_once 'connection.php';
Auth::requireLogin('/login.php');

$me       = Auth::user();
$myId     = Auth::id();
$isSeller = Auth::is('dealer', 'private_seller', 'admin', 'moderator');

// Only valid if > 0
$activeConvId = (isset($_GET['conv']) && (int)$_GET['conv'] > 0) ? (int)$_GET['conv'] : 0;

// POST: send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $convId  = (int)($_POST['conv_id'] ?? 0);
    $msgBody = trim(cleanInput($_POST['message'] ?? ''));
    $isAjax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $ok    = false;
    $newId = 0;
    $error = '';

    if ($convId <= 0 || strlen($msgBody) === 0 || strlen($msgBody) > 2000) {
        $error = 'Message is empty or too long.';
    } else {
        $conv = DB::selectOne(
            "SELECT * FROM conversations WHERE id=? AND (buyer_id=? OR seller_id=?)",
            [$convId, $myId, $myId]
        );
        if (!$conv) {
            $error = 'Conversation not found.';
        } else {
            $newId = DB::insert(
                "INSERT INTO messages (conversation_id, sender_id, message, is_seen, created_at) VALUES (?,?,?,0,NOW())",
                [$convId, $myId, $msgBody]
            );
            DB::execute(
                "UPDATE conversations SET last_message=?, last_message_at=NOW() WHERE id=?",
                [substr($msgBody, 0, 120), $convId]
            );
            $otherId = ($myId === (int)$conv['buyer_id']) ? $conv['seller_id'] : $conv['buyer_id'];
            DB::execute(
                "INSERT INTO notifications (user_id, type, title, body, link, created_at) VALUES (?,?,?,?,?,NOW())",
                [$otherId, 'new_message', 'New message from ' . $me['name'], substr($msgBody, 0, 100), BASE_URL . '/messages.php?conv=' . $convId]
            );
            $ok = true;
        }
    }

    if ($isAjax) {
        if ($ok) {
            jsonResponse(true, 'Sent', ['id' => $newId, 'time' => date('g:i A')]);
        } else {
            jsonResponse(false, $error ?: 'Could not send message.', null, 400);
        }
    }

    header('Location: ' . BASE_URL . '/messages.php?conv=' . $convId . '#bottom');
    exit;
}

// Load ALL conversations where user is buyer OR seller (id must be > 0)
$conversations = DB::select("
    SELECT cv.id, cv.last_message, cv.last_message_at,
           ca.id AS car_id, ca.year, ca.price,
           COALESCE(m.name,'') AS make_name,
           COALESCE(mo.name,'') AS model_name,
           ci.image_path AS car_thumb,
           CASE WHEN cv.buyer_id = ? THEN us.name ELSE ub.name END AS other_name
    FROM conversations cv
    JOIN cars ca ON ca.id = cv.car_id
    LEFT JOIN makes m   ON m.id  = ca.make_id
    LEFT JOIN models mo ON mo.id = ca.model_id
    LEFT JOIN car_images ci ON ci.car_id = ca.id AND ci.is_featured = 1
    LEFT JOIN users us ON us.id = cv.seller_id
    LEFT JOIN users ub ON ub.id = cv.buyer_id
    WHERE cv.id > 0
      AND (
            (cv.buyer_id  = ? AND cv.buyer_deleted  = 0)
         OR (cv.seller_id = ? AND cv.seller_deleted = 0)
          )
    ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC
    LIMIT 60
", [$myId, $myId, $myId]);

// Unread counts
$unreadMap = [];
if (!empty($conversations)) {
    $ids = implode(',', array_map('intval', array_column($conversations, 'id')));
    $rows = DB::select(
        "SELECT conversation_id, COUNT(*) AS cnt FROM messages
         WHERE conversation_id IN ($ids) AND is_seen=0 AND sender_id != ?
         GROUP BY conversation_id",
        [$myId]
    );
    foreach ($rows as $r) {
        $unreadMap[(int)$r['conversation_id']] = (int)$r['cnt'];
    }
}

// Load active thread
$activeConv = null;
$messages   = [];
$otherUser  = null;

if ($activeConvId > 0) {
    $activeConv = DB::selectOne(
        "SELECT cv.*,
                ca.id AS car_id, ca.year, ca.price,
                COALESCE(m.name,'') AS make_name,
                COALESCE(mo.name,'') AS model_name,
                ci.image_path AS car_thumb,
                s.name AS seller_name, COALESCE(s.phone,'') AS seller_phone,
                b.name AS buyer_name,  COALESCE(b.phone,'') AS buyer_phone
         FROM conversations cv
         JOIN cars ca ON ca.id = cv.car_id
         LEFT JOIN makes m   ON m.id  = ca.make_id
         LEFT JOIN models mo ON mo.id = ca.model_id
         LEFT JOIN car_images ci ON ci.car_id = ca.id AND ci.is_featured = 1
         LEFT JOIN users s ON s.id = cv.seller_id
         LEFT JOIN users b ON b.id  = cv.buyer_id
         WHERE cv.id = ? AND (cv.buyer_id = ? OR cv.seller_id = ?)",
        [$activeConvId, $myId, $myId]
    );

    if ($activeConv) {
        $otherUser = ($myId === (int)$activeConv['buyer_id'])
            ? ['name' => $activeConv['seller_name'] ?: 'Seller', 'phone' => $activeConv['seller_phone']]
            : ['name' => $activeConv['buyer_name']  ?: 'Buyer',  'phone' => $activeConv['buyer_phone']];

        $messages = DB::select(
            "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC",
            [$activeConvId]
        );
        DB::execute(
            "UPDATE messages SET is_seen=1, seen_at=NOW() WHERE conversation_id=? AND sender_id!=? AND is_seen=0",
            [$activeConvId, $myId]
        );
        $unreadMap[$activeConvId] = 0;
    }
}

$totalUnread = getUnreadCount();
$notifCount  = getNotificationCount();
$lastMsgId   = !empty($messages) ? (int)end($messages)['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Messages | CarSoko Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --black:#0a0a0b;--dark:#111114;--card-bg:#18181c;
  --border:rgba(255,255,255,.07);--white:#f5f5f0;--muted:#888896;
  --accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;
  --gradient:linear-gradient(135deg,#e8b84b,#ff6b35);
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:10px;--sidebar-w:240px;--convlist-w:290px;
  --mob-nav-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100dvh;overflow:hidden}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px}
a{color:inherit;text-decoration:none}

/* ── GRID ── */
.layout{display:grid;grid-template-columns:var(--sidebar-w) var(--convlist-w) 1fr;height:100vh;height:100dvh;overflow:hidden}

/* ── SIDEBAR ── */
.sidebar{background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column;height:100vh;height:100dvh;overflow-y:auto;overflow-x:hidden}
.sidebar::-webkit-scrollbar{width:3px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px}

.sidebar-logo{padding:18px 20px;border-bottom:1px solid var(--border);flex-shrink:0}
.logo{font-family:var(--font-head);font-size:21px;font-weight:800;display:flex;align-items:center;gap:2px}
.logo-car{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:2px;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}

.sb-user{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:13px;font-weight:700;color:#0a0a0b;flex-shrink:0}
.sb-name{font-size:13px;font-weight:600;line-height:1.3}
.sb-role{font-size:11px;color:var(--muted)}

.sb-nav{padding:10px 8px;flex:1}
.nav-section{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:10px 10px 4px}
.nav-link{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:8px;color:var(--muted);font-size:13px;font-weight:500;transition:all .18s;margin-bottom:1px;position:relative;white-space:nowrap}
.nav-link:hover{color:var(--white);background:rgba(255,255,255,.05)}
.nav-link.on{color:var(--white);background:rgba(232,184,75,.1)}
.nav-link.on::before{content:'';position:absolute;left:0;top:18%;height:64%;width:3px;background:var(--gradient);border-radius:0 3px 3px 0}
.nav-link.on i{color:var(--accent)}
.nav-link i{width:15px;text-align:center;font-size:13px}
.nbadge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:50px;min-width:16px;text-align:center}

.sb-footer{padding:12px 16px;border-top:1px solid var(--border);flex-shrink:0}
.sb-footer a{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px;padding:5px 0;transition:color .2s}
.sb-footer a:hover{color:var(--white)}

/* ── CONV LIST ── */
.conv-col{background:var(--dark);border-right:1px solid var(--border);display:flex;flex-direction:column;height:100vh;height:100dvh;overflow:hidden}
.conv-col-head{padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.conv-col-head h2{font-family:var(--font-head);font-size:15px;font-weight:700}
.conv-count{font-size:11px;color:var(--muted);margin-top:1px}
.conv-search-wrap{padding:9px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.conv-search-wrap input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--white);padding:7px 12px;border-radius:50px;font-size:12px;outline:none;font-family:var(--font-body);transition:border-color .2s}
.conv-search-wrap input:focus{border-color:rgba(232,184,75,.35)}
.conv-list{flex:1;overflow-y:auto}
.conv-list::-webkit-scrollbar{width:3px}
.conv-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08)}

.conv-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.03);cursor:pointer;transition:background .15s;text-decoration:none;color:inherit}
.conv-item:hover{background:rgba(255,255,255,.035)}
.conv-item.sel{background:rgba(232,184,75,.07);border-left:2px solid var(--accent)}
.c-img{width:42px;height:32px;border-radius:6px;object-fit:cover;flex-shrink:0;background:#1e1e22}
.c-ph{width:42px;height:32px;border-radius:6px;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#0a0a0b;flex-shrink:0}
.c-body{flex:1;min-width:0}
.c-title{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.c-sub{font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.c-right{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.c-time{font-size:10px;color:var(--muted)}
.c-badge{width:17px;height:17px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#0a0a0b}
.conv-empty-msg{padding:48px 20px;text-align:center;color:var(--muted)}
.conv-empty-msg i{font-size:28px;opacity:.2;display:block;margin-bottom:10px}
.conv-empty-msg p{font-size:12px;line-height:1.7}

/* ── CHAT AREA ── */
.chat-col{display:flex;flex-direction:column;height:100vh;height:100dvh;overflow:hidden;background:var(--black);min-width:0}

/* Chat header */
.chat-hdr{padding:10px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-shrink:0;background:var(--dark);min-height:58px}
.hdr-car-img{width:48px;height:36px;border-radius:6px;object-fit:cover;flex-shrink:0;background:#1e1e22}
.hdr-car-info{flex:1;min-width:0}
.hdr-car-name{font-size:13px;font-weight:700;font-family:var(--font-head);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.hdr-car-name:hover{color:var(--accent)}
.hdr-car-price{font-size:11px;color:var(--accent);margin-top:1px}
.hdr-user{display:flex;align-items:center;gap:8px;flex-shrink:0}
.hdr-avatar{width:30px;height:30px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#0a0a0b;flex-shrink:0}
.hdr-uname{font-size:12px;font-weight:600}
.hdr-phone{font-size:10px;color:var(--muted)}
.hdr-btns{display:flex;gap:6px;flex-shrink:0}
.hbtn{width:32px;height:32px;border-radius:7px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted);display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .2s}
.hbtn:hover{color:var(--white);border-color:rgba(255,255,255,.2)}

/* Back button – mobile only */
.hdr-back{display:none;width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--white);align-items:center;justify-content:center;font-size:14px;flex-shrink:0;cursor:pointer;text-decoration:none;transition:background .2s}
.hdr-back:hover{background:rgba(255,255,255,.1)}

/* Messages */
.chat-body{flex:1;min-width:0;width:100%;overflow-y:auto;overflow-x:hidden;padding:18px 20px;display:flex;flex-direction:column;gap:8px}
.chat-body::-webkit-scrollbar{width:4px}
.chat-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:4px}

.day-sep{text-align:center;margin:6px 0}
.day-sep span{font-size:10px;color:var(--muted);background:rgba(255,255,255,.04);padding:3px 10px;border-radius:20px}

.msg-wrap{display:flex;align-items:flex-end;gap:6px;width:100%;min-width:0}
.msg-wrap.out{flex-direction:row-reverse}
.msg-ava{width:24px;height:24px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#0a0a0b;flex-shrink:0;margin-bottom:16px}

/* Wrapper around bubble+timestamp — this is what actually controls
   how wide a message can grow. Giving IT the max-width (not the
   bubble itself) and min-width:0 keeps it from collapsing to a
   sliver in flex/grid contexts, which is what caused messages to
   render as a single vertical column of letters. */
.msg-content{display:flex;flex-direction:column;min-width:0;max-width:74%}
.msg-wrap.out .msg-content{align-items:flex-end}
.msg-wrap.in  .msg-content{align-items:flex-start}

.bubble{display:inline-block;width:fit-content;max-width:100%;padding:9px 13px;border-radius:14px;font-size:13px;line-height:1.55;overflow-wrap:break-word;word-break:normal}
.msg-wrap.out .bubble{background:linear-gradient(135deg,rgba(232,184,75,.17),rgba(255,107,53,.13));border:1px solid rgba(232,184,75,.18);border-bottom-right-radius:3px}
.msg-wrap.in  .bubble{background:var(--card-bg);border:1px solid var(--border);border-bottom-left-radius:3px}
.msg-foot{font-size:10px;color:var(--muted);margin-top:3px;padding:0 3px;display:flex;align-items:center;gap:4px;white-space:nowrap}
.msg-wrap.out .msg-foot{justify-content:flex-end}
.seen-check{color:var(--accent)}

/* Empty chat */
.chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--muted)}
.chat-empty i{font-size:42px;opacity:.1}
.chat-empty h3{font-family:var(--font-head);font-size:16px;color:rgba(245,245,240,.18)}
.chat-empty p{font-size:12px;text-align:center;max-width:240px;line-height:1.7}

/* Input */
.chat-foot{flex-shrink:0;border-top:1px solid var(--border);background:var(--dark)}
.input-row{display:flex;align-items:flex-end;gap:8px;padding:12px 14px}
.msg-ta{flex:1;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--white);padding:9px 13px;border-radius:11px;font-size:13px;resize:none;max-height:110px;min-height:40px;outline:none;font-family:var(--font-body);line-height:1.5;transition:border-color .2s}
.msg-ta:focus{border-color:rgba(232,184,75,.38)}
.msg-ta::placeholder{color:var(--muted)}
.snd-btn{width:40px;height:40px;border-radius:11px;background:var(--gradient);border:none;color:#0a0a0b;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.snd-btn:active{transform:scale(.95)}
.snd-btn:hover{transform:translateY(-2px);box-shadow:0 5px 16px rgba(232,184,75,.4)}

/* No conv selected */
.no-sel{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--muted);text-align:center;padding:30px}
.no-sel i{font-size:46px;opacity:.1}
.no-sel h3{font-family:var(--font-head);font-size:16px;color:rgba(245,245,240,.16)}
.no-sel p{font-size:12px;max-width:220px;line-height:1.7}

/* ── MOBILE BOTTOM NAV ── */
.mob-nav{display:none;position:fixed;bottom:0;left:0;right:0;height:var(--mob-nav-h);background:var(--dark);border-top:1px solid var(--border);z-index:100;padding:0 8px;padding-bottom:env(safe-area-inset-bottom,0)}
.mob-nav-inner{display:flex;align-items:center;justify-content:space-around;height:var(--mob-nav-h)}
.mob-nav-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:var(--muted);padding:8px 14px;border-radius:10px;font-size:10px;font-weight:500;transition:color .18s;position:relative;min-width:56px}
.mob-nav-btn i{font-size:18px}
.mob-nav-btn.on{color:var(--accent)}
.mob-nav-badge{position:absolute;top:4px;right:8px;background:var(--red);color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:50px;min-width:15px;text-align:center}

/* ── TABLET: hide sidebar ── */
@media(max-width:1100px){
  :root{--sidebar-w:0px;--convlist-w:270px}
  .sidebar{display:none}
  .layout{grid-template-columns:var(--convlist-w) 1fr}
}
@media(max-width:768px){
  :root{--convlist-w:100%}

  .layout{
    grid-template-columns:1fr;
    grid-template-rows:1fr;
    height:100dvh;
  }

  /* Sidebar always hidden on mobile */
  .sidebar{display:none}

  /* Conv list: full screen, padded for bottom nav */
  .conv-col{
    grid-column:1;grid-row:1;
    height:100dvh;
    display:flex;
    padding-bottom:var(--mob-nav-h);
  }

  /* Chat col: full screen, sits on top when active */
  .chat-col{
    grid-column:1;grid-row:1;
    height:100dvh;
    display:none; /* hidden by default */
    padding-bottom:var(--mob-nav-h);
  }

  /* When a conv is active, show chat, hide list */
  body.has-conv .conv-col{display:none}
  body.has-conv .chat-col{display:flex}

  /* Show mobile bottom nav */
  .mob-nav{display:block}

  /* Back button visible on mobile */
  .hdr-back{display:flex}

  /* Larger touch targets on conv items */
  .conv-item{padding:13px 14px}
  .c-img{width:46px;height:36px}
  .c-ph{width:46px;height:36px}

  /* Bubble max-width wider on mobile */
  .msg-content{max-width:85%}
  .bubble{font-size:14px}

  /* Chat padding tweaks */
  .chat-body{padding:14px 12px}
  .chat-hdr{padding:10px 12px;gap:8px}

  /* Simplify hdr on small screen */
  .hdr-user .hdr-uname{font-size:13px}
  .hdr-phone{display:none}

  /* Input footer safe-area */
  .chat-foot{padding-bottom:env(safe-area-inset-bottom,0)}
  .input-row{padding:10px 12px}
  .msg-ta{font-size:16px} /* prevents iOS zoom */
  .msg-ta::placeholder{font-size:13px}
  .snd-btn{width:44px;height:44px}

  /* conv col search */
  .conv-col-head{padding:12px 14px}
  .conv-search-wrap{padding:8px 10px}
}

/* Extra small screens */
@media(max-width:380px){
  .hdr-car-img{display:none}
  .hbtn{width:36px;height:36px}
}
</style>
</head>
<body<?= $activeConvId > 0 ? ' class="has-conv"' : '' ?>>
<div class="layout">

<!-- ══════════════ LEFT SIDEBAR ══════════════ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <a href="index.php" class="logo">
      <span class="logo-car"><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="logo-dot"></div>
    </a>
  </div>

  <div class="sb-user">
    <div class="sb-avatar"><?= strtoupper(substr($me['name'], 0, 1)) ?></div>
    <div style="overflow:hidden">
      <div class="sb-name"><?= e($me['name']) ?></div>
      <div class="sb-role"><?= ucfirst(str_replace('_', ' ', $me['role'])) ?></div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <?php if ($isSeller): ?>
    <a href="post-listing.php" class="nav-link"><i class="fas fa-plus-circle"></i> Post a Car</a>
    <?php endif; ?>

    <div class="nav-section">Inbox</div>
    <a href="messages.php" class="nav-link on">
      <i class="fas fa-comment-dots"></i> Messages
      <?php if ($totalUnread > 0): ?><span class="nbadge"><?= $totalUnread ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" class="nav-link">
      <i class="fas fa-bell"></i> Notifications
      <?php if ($notifCount > 0): ?><span class="nbadge"><?= $notifCount ?></span><?php endif; ?>
    </a>

    <div class="nav-section">Account</div>
    <a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a>
    <?php if (Auth::isModerator()): ?>
    <a href="admin.php" class="nav-link" style="color:var(--accent)"><i class="fas fa-shield-halved"></i> Admin Panel</a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <a href="index.php"><i class="fas fa-home"></i> Back to Site</a>
    <a href="logout.php" style="color:var(--red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<!-- ══════════════ CONVERSATION LIST ══════════════ -->
<div class="conv-col">
  <div class="conv-col-head">
    <h2>Messages <?php if ($totalUnread > 0): ?><span style="color:var(--red);font-size:11px;font-weight:400">(<?= $totalUnread ?> new)</span><?php endif; ?></h2>
    <div class="conv-count"><?= count($conversations) ?> conversation<?= count($conversations) != 1 ? 's' : '' ?></div>
  </div>
  <div class="conv-search-wrap">
    <input type="text" id="convSearch" placeholder="Search conversations…" oninput="filterConvs(this.value)">
  </div>
  <div class="conv-list" id="convList">
    <?php if (empty($conversations)): ?>
    <div class="conv-empty-msg">
      <i class="fas fa-comments"></i>
      <p><?= $isSeller
          ? 'Buyer conversations will appear here.'
          : '<a href="index.php" style="color:var(--accent)">Browse cars</a> and message a seller.' ?></p>
    </div>
    <?php else: ?>
    <?php foreach ($conversations as $c):
      $cid    = (int)$c['id'];
      $isSel  = $cid === $activeConvId;
      $unread = $unreadMap[$cid] ?? 0;
      $srch   = strtolower($c['other_name'] . ' ' . $c['make_name'] . ' ' . $c['model_name']);
    ?>
    <a href="messages.php?conv=<?= $cid ?>"
       class="conv-item<?= $isSel ? ' sel' : '' ?>"
       data-s="<?= e($srch) ?>">
      <?php if (!empty($c['car_thumb'])): ?>
      <img class="c-img" src="<?= e(carImageUrl($c['car_thumb'], true)) ?>" alt="" onerror="this.style.display='none'">
      <?php else: ?>
      <div class="c-ph"><?= strtoupper(substr($c['make_name'] ?? 'C', 0, 1)) ?></div>
      <?php endif; ?>
      <div class="c-body">
        <div class="c-title"><?= e($c['year'] . ' ' . $c['make_name'] . ' ' . $c['model_name']) ?></div>
        <div class="c-sub">
          <span style="opacity:.55"><?= e($c['other_name']) ?>:</span>
          <?= e(mb_substr($c['last_message'] ?? '—', 0, 34)) ?>
        </div>
      </div>
      <div class="c-right">
        <div class="c-time"><?= $c['last_message_at'] ? timeAgo($c['last_message_at']) : '' ?></div>
        <?php if ($unread > 0): ?>
        <div class="c-badge"><?= $unread ?></div>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════ CHAT AREA ══════════════ -->
<div class="chat-col">

<?php if (!$activeConv): ?>
<!-- Nothing selected -->
<div class="no-sel">
  <i class="fas fa-comment-dots"></i>
  <h3><?= empty($conversations) ? 'No conversations yet' : 'Select a conversation' ?></h3>
  <p>
    <?php if (empty($conversations)): ?>
      <?= $isSeller ? 'When buyers message you about your cars, they\'ll show up here.' : '<a href="index.php" style="color:var(--accent)">Browse cars</a> and tap "Message Seller" to start chatting.' ?>
    <?php else: ?>
      Click a conversation on the left to open it.
    <?php endif; ?>
  </p>
</div>

<?php else: ?>

<!-- Header -->
<div class="chat-hdr">
  <a href="messages.php" class="hdr-back" title="Back to conversations"><i class="fas fa-chevron-left"></i></a>
  <?php if (!empty($activeConv['car_thumb'])): ?>
  <img class="hdr-car-img" src="<?= e(carImageUrl($activeConv['car_thumb'])) ?>" alt=""
       onerror="this.style.display='none'">
  <?php endif; ?>
  <div class="hdr-car-info">
    <a href="listing.php?id=<?= (int)$activeConv['car_id'] ?>" class="hdr-car-name">
      <?= e($activeConv['year'] . ' ' . $activeConv['make_name'] . ' ' . $activeConv['model_name']) ?>
    </a>
    <div class="hdr-car-price"><?= formatPKR((float)$activeConv['price'], true) ?></div>
  </div>
  <div class="hdr-user">
    <div>
      <div class="hdr-uname"><?= e($otherUser['name']) ?></div>
      <?php if (!empty($otherUser['phone'])): ?>
      <div class="hdr-phone"><i class="fas fa-phone" style="font-size:9px"></i> <?= e($otherUser['phone']) ?></div>
      <?php endif; ?>
    </div>
    <div class="hdr-avatar"><?= strtoupper(substr($otherUser['name'], 0, 1)) ?></div>
  </div>
  <div class="hdr-btns">
    <?php if (!empty($otherUser['phone'])): ?>
    <a href="tel:<?= e($otherUser['phone']) ?>" class="hbtn" title="Call <?= e($otherUser['name']) ?>">
      <i class="fas fa-phone"></i>
    </a>
    <?php endif; ?>
    <a href="listing.php?id=<?= (int)$activeConv['car_id'] ?>" class="hbtn" title="View listing">
      <i class="fas fa-external-link-alt"></i>
    </a>
  </div>
</div>

<!-- Messages -->
<div class="chat-body" id="chatBody">
  <?php if (empty($messages)): ?>
  <div class="chat-empty">
    <i class="fas fa-comment-alt"></i>
    <h3>Start the conversation</h3>
    <p>Ask about the car, negotiate a price, or arrange a viewing.</p>
  </div>
  <?php else: ?>
  <?php
  $prevDay = '';
  foreach ($messages as $msg):
    $out    = ((int)$msg['sender_id'] === $myId);
    $day    = date('Y-m-d', strtotime($msg['created_at']));
    $today  = date('Y-m-d');
    $yest   = date('Y-m-d', strtotime('-1 day'));
  ?>
  <?php if ($day !== $prevDay): $prevDay = $day; ?>
  <div class="day-sep" data-day="<?= e($day) ?>">
    <span><?= $day === $today ? 'Today' : ($day === $yest ? 'Yesterday' : date('D, j M Y', strtotime($msg['created_at']))) ?></span>
  </div>
  <?php endif; ?>
  <div class="msg-wrap <?= $out ? 'out' : 'in' ?>" data-id="<?= (int)$msg['id'] ?>">
    <?php if (!$out): ?>
    <div class="msg-ava"><?= strtoupper(substr($otherUser['name'], 0, 1)) ?></div>
    <?php endif; ?>
    <div class="msg-content">
      <div class="bubble"><?= nl2br(e($msg['message'])) ?></div>
      <div class="msg-foot">
        <?= date('g:i A', strtotime($msg['created_at'])) ?>
        <?php if ($out && $msg['is_seen']): ?>
        <i class="fas fa-check-double seen-check"></i>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <div id="chatEnd"></div>
</div>

<!-- Input -->
<div class="chat-foot">
  <form method="POST" id="msgForm"
        data-poll-url="<?= e(BASE_URL) ?>/ajax/poll-messages.php"
        data-my-id="<?= (int)$myId ?>"
        data-conv-id="<?= (int)$activeConvId ?>"
        data-last-id="<?= (int)$lastMsgId ?>"
        data-other-initial="<?= e(strtoupper(substr($otherUser['name'], 0, 1))) ?>">
    <?= CSRF::field() ?>
    <input type="hidden" name="conv_id" value="<?= (int)$activeConvId ?>">
    <div class="input-row">
      <textarea
        class="msg-ta"
        name="message"
        id="msgInput"
        rows="1"
        placeholder="Type a message…"
        oninput="taResize(this)"
        onkeydown="taEnter(event,this)"
      ></textarea>
      <button type="submit" class="snd-btn" id="sndBtn" title="Send message">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </form>
</div>

<?php endif; // active conv ?>
</div><!-- /.chat-col -->
</div><!-- /.layout -->

<!-- ══════════════ MOBILE BOTTOM NAV ══════════════ -->
<nav class="mob-nav">
  <div class="mob-nav-inner">
    <a href="index.php" class="mob-nav-btn">
      <i class="fas fa-home"></i>
      <span>Home</span>
    </a>
    <a href="messages.php" class="mob-nav-btn on">
      <i class="fas fa-comment-dots"></i>
      <span>Messages</span>
      <?php if ($totalUnread > 0): ?>
      <span class="mob-nav-badge"><?= $totalUnread ?></span>
      <?php endif; ?>
    </a>
    <a href="notifications.php" class="mob-nav-btn">
      <i class="fas fa-bell"></i>
      <span>Alerts</span>
      <?php if ($notifCount > 0): ?>
      <span class="mob-nav-badge"><?= $notifCount ?></span>
      <?php endif; ?>
    </a>
    <a href="profile.php" class="mob-nav-btn">
      <i class="fas fa-user-circle"></i>
      <span>Profile</span>
    </a>
  </div>
</nav>

<script>
// Scroll to bottom on load
(function () {
  var el = document.getElementById('chatBody');
  if (el) el.scrollTop = el.scrollHeight;
})();

// Enter = submit, Shift+Enter = newline
function taEnter(e, ta) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    var form = document.getElementById('msgForm');
    if (form) form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', {cancelable:true}));
  }
}

// Auto-grow textarea
function taResize(ta) {
  ta.style.height = 'auto';
  ta.style.height = Math.min(ta.scrollHeight, 110) + 'px';
}

// Search/filter conversations
function filterConvs(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.conv-item').forEach(function (el) {
    el.style.display = (!q || el.dataset.s.indexOf(q) !== -1) ? '' : 'none';
  });
}

// ─────────────────────────────────────────────────────────
// REAL-TIME CHAT: AJAX send + polling.
// Sending a message no longer reloads the page, and the
// other person's replies appear automatically without them
// (or you) needing to hit refresh.
// ─────────────────────────────────────────────────────────
(function () {
  var form = document.getElementById('msgForm');
  if (!form) return; // no active conversation open

  var chatBody   = document.getElementById('chatBody');
  var chatEnd    = document.getElementById('chatEnd');
  var input      = document.getElementById('msgInput');
  var sndBtn     = document.getElementById('sndBtn');

  var pollUrl    = form.dataset.pollUrl;
  var myId       = parseInt(form.dataset.myId, 10);
  var convId     = form.dataset.convId;
  var lastId     = parseInt(form.dataset.lastId, 10) || 0;
  var otherInit  = form.dataset.otherInitial || '?';
  var lastDay    = '';
  // Track the most recent day-separator already shown, based on the last rendered message
  (function initLastDay() {
    var seps = chatBody.querySelectorAll('.day-sep');
    if (seps.length) lastDay = seps[seps.length - 1].dataset.day || '';
  })();

  function scrollToEnd(smooth) {
    if (chatBody) chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function buildDaySep(dayKey, dayLabel) {
    var div = document.createElement('div');
    div.className = 'day-sep';
    div.dataset.day = dayKey;
    div.innerHTML = '<span>' + escapeHtml(dayLabel) + '</span>';
    return div;
  }

  function buildMsgNode(m) {
    var isOut = m.is_me;
    var wrap = document.createElement('div');
    wrap.className = 'msg-wrap ' + (isOut ? 'out' : 'in');
    wrap.dataset.id = m.id;

    var avaHtml = !isOut ? '<div class="msg-ava">' + escapeHtml(otherInit) + '</div>' : '';
    var seenHtml = (isOut && m.is_seen) ? ' <i class="fas fa-check-double seen-check"></i>' : '';

    wrap.innerHTML =
      avaHtml +
      '<div class="msg-content">' +
        '<div class="bubble">' + (m.message_html || escapeHtml(m.message)) + '</div>' +
        '<div class="msg-foot">' + escapeHtml(m.time) + seenHtml + '</div>' +
      '</div>';
    return wrap;
  }

  function appendIncoming(messages) {
    if (!messages || !messages.length) return;
    var frag = document.createDocumentFragment();
    messages.forEach(function (m) {
      if (m.day !== lastDay) {
        lastDay = m.day;
        frag.appendChild(buildDaySep(m.day, m.day_label));
      }
      frag.appendChild(buildMsgNode(m));
      if (m.id > lastId) lastId = m.id;
    });
    // Remove the "start the conversation" placeholder if present
    var empty = chatBody.querySelector('.chat-empty');
    if (empty) empty.remove();
    chatBody.insertBefore(frag, chatEnd);
    scrollToEnd(true);
  }

  function poll() {
    if (document.hidden) return; // save requests when tab isn't visible
    var url = pollUrl + '?conv_id=' + encodeURIComponent(convId) + '&after_id=' + lastId + '&_=' + Date.now();
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.data) {
          appendIncoming(res.data.messages);
        }
      })
      .catch(function () { /* silent — try again next tick */ });
  }

  // Poll every 3s; poll immediately when tab regains focus too
  var pollTimer = setInterval(poll, 3000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });

  // Intercept submit → send via fetch, append bubble instantly, no reload
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;

    sndBtn.disabled = true;
    var fd = new FormData(form);

    fetch(form.getAttribute('action') || window.location.pathname + window.location.search, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store',
      body: fd
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        sndBtn.disabled = false;
        if (res && res.success) {
          var today = new Date();
          var dayKey = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
          appendIncoming([{
            id: res.data.id,
            is_me: true,
            message: text,
            message_html: escapeHtml(text).replace(/\n/g, '<br>'),
            is_seen: 0,
            time: res.data.time,
            day: dayKey,
            day_label: 'Today'
          }]);
          input.value = '';
          taResize(input);
          input.focus();
        } else {
          alert((res && res.message) || 'Could not send message. Please try again.');
        }
      })
      .catch(function () {
        sndBtn.disabled = false;
        alert('Network error — message not sent. Please try again.');
      });
  });
})();
</script>
</body>
</html>