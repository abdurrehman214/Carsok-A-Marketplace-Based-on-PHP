<?php
// ============================================================
//  CarSoko Pakistan — upgrade-role.php
//  Allows any logged-in user to change their role
//  (buyer → seller / dealer, seller → dealer, etc.)
// ============================================================
require_once 'connection.php';

// Must be logged in
Auth::requireLogin('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));

$user   = Auth::user();
$errors = [];
$success = false;

// ── HANDLE FORM SUBMISSION ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verify()) {
        $errors['general'] = 'Security token expired. Please refresh and try again.';
    } else {
        $newRole      = cleanInput($_POST['role']          ?? '');
        $businessName = cleanInput($_POST['business_name'] ?? '');
        $city         = cleanInput($_POST['city']          ?? $user['city'] ?? '');
        $phone        = cleanInput($_POST['phone']         ?? $user['phone'] ?? '');

        // Validate
        if (!in_array($newRole, ['buyer', 'private_seller', 'dealer'])) {
            $errors['role'] = 'Please select a valid role.';
        }
        if ($newRole === 'dealer' && strlen(trim($businessName)) < 2) {
            $errors['business_name'] = 'Business name is required for dealers.';
        }
        if (!$city) {
            $errors['city'] = 'Please enter your city.';
        }

        if (empty($errors)) {
            try {
                DB::execute(
                    "UPDATE users SET role=?, city=?, business_name=?, phone=?, updated_at=NOW() WHERE id=?",
                    [
                        $newRole,
                        $city,
                        $newRole === 'dealer' ? trim($businessName) : ($newRole === 'private_seller' ? null : null),
                        $phone ?: $user['phone'],
                        Auth::id()
                    ]
                );

                // Refresh session
                $updated = DB::find('users', Auth::id());
                Auth::login($updated, true); // re-login to refresh session data

                logActivity('role_change', Auth::id(), 'user', $newRole);

                flash('success', 'Your account has been updated to ' . ucfirst(str_replace('_', ' ', $newRole)) . '! 🎉');

                // Redirect based on new role
                if ($newRole === 'buyer') {
                    redirect(BASE_URL . '/index.php');
                } else {
                    redirect(BASE_URL . '/post-listing.php');
                }
            } catch (Throwable $ex) {
                $errors['general'] = 'Update failed. Please try again.';
                error_log('[CarSoko upgrade-role] ' . $ex->getMessage());
            }
        }
    }
}

$currentRole  = $user['role'] ?? 'buyer';
$targetRole   = cleanInput($_GET['role'] ?? ''); // pre-select from URL e.g. ?role=dealer
$preselect    = in_array($targetRole, ['buyer','private_seller','dealer']) ? $targetRole : $currentRole;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Change Account Type – CarSoko Pakistan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --black: #0a0a0b;
    --dark: #111114;
    --card-bg: rgba(22,22,26,0.85);
    --border: rgba(255,255,255,0.08);
    --white: #f5f5f0;
    --muted: #888896;
    --accent: #e8b84b;
    --accent2: #ff6b35;
    --green: #22c55e;
    --red: #ef4444;
    --gradient: linear-gradient(135deg,#e8b84b,#ff6b35);
    --fh: 'Syne',sans-serif;
    --fb: 'DM Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
    background:var(--black);color:var(--white);font-family:var(--fb);font-size:15px;
    display:flex;align-items:center;justify-content:center;
    min-height:100vh;overflow-x:hidden;position:relative;padding:40px 20px;
}
body::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(circle at 15% 20%, rgba(232,184,75,0.07) 0%,transparent 45%),
               radial-gradient(circle at 85% 80%, rgba(255,107,53,0.06) 0%,transparent 45%);
    z-index:-1;
}
.wrap{width:100%;max-width:500px;animation:fadeUp .7s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.card{
    background:var(--card-bg);backdrop-filter:blur(20px);
    border:1px solid var(--border);border-radius:28px;
    padding:44px 38px;box-shadow:0 24px 80px rgba(0,0,0,0.5);
}
/* Logo */
.logo-row{text-align:center;margin-bottom:28px}
.logo{font-family:var(--fh);font-size:26px;font-weight:800;display:inline-flex;align-items:center;gap:4px;text-decoration:none;color:var(--white)}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ld{width:7px;height:7px;background:var(--gradient);border-radius:50%;margin-left:2px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.5);opacity:.6}}

/* Current role badge */
.current-role{
    display:flex;align-items:center;gap:10px;padding:12px 16px;
    background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;
    margin-bottom:24px;font-size:13px;
}
.role-badge{
    padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;
    background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.3);color:var(--accent);
}

/* Header */
.hd{text-align:center;margin-bottom:28px}
.hd h1{font-family:var(--fh);font-size:28px;font-weight:800;margin-bottom:6px}
.hd p{font-size:13px;color:var(--muted)}

/* Role grid */
.role-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px}
.role-radio{display:none}
.role-card{
    display:flex;flex-direction:column;align-items:center;gap:7px;
    padding:18px 8px;background:rgba(255,255,255,.03);border:1px solid var(--border);
    border-radius:14px;cursor:pointer;transition:all .25s;text-align:center;position:relative;
}
.role-card:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15)}
.role-radio:checked + .role-card{
    background:rgba(232,184,75,.09);border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(232,184,75,.12);
}
.role-radio:checked + .role-card .role-check{display:flex}
.role-check{
    display:none;position:absolute;top:8px;right:8px;width:18px;height:18px;
    background:var(--green);border-radius:50%;align-items:center;justify-content:center;
    font-size:9px;color:#fff;
}
.role-emoji{font-size:22px}
.role-title{font-size:13px;font-weight:700;color:var(--white)}
.role-desc{font-size:10px;color:var(--muted);line-height:1.3}

/* Current role marker */
.current-tag{
    font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
    background:rgba(34,197,94,.15);color:var(--green);padding:2px 6px;border-radius:4px;
    display:inline-block;margin-top:2px;
}

/* Fields */
.fld{margin-bottom:18px}
.fld label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:7px}
.iw{position:relative}
.iw i.ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;pointer-events:none}
.iw input,.iw select{
    width:100%;background:rgba(255,255,255,.03);border:1px solid var(--border);
    color:var(--white);padding:13px 14px 13px 42px;border-radius:11px;
    font-size:14px;font-family:var(--fb);outline:none;transition:all .25s;
}
.iw input:focus,.iw select:focus{border-color:var(--accent);background:rgba(232,184,75,.05);box-shadow:0 0 0 3px rgba(232,184,75,.1)}
.iw input.err,.iw select.err{border-color:var(--red)}
.iw select{-webkit-appearance:none;cursor:pointer}
.iw:focus-within i.ico{color:var(--accent)}
.ferr{font-size:12px;color:var(--red);margin-top:5px;display:flex;align-items:center;gap:5px}

/* Dealer fields */
.dealer-fields{display:none;animation:slideDown .25s ease}
.dealer-fields.show{display:block}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* Info box */
.info-box{
    background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);
    border-radius:10px;padding:12px 14px;font-size:13px;color:rgba(147,197,253,.9);
    margin-bottom:22px;display:flex;align-items:flex-start;gap:8px;line-height:1.5;
}

/* Alert */
.al{padding:14px 16px;border-radius:11px;font-size:14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px}
.ale{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5}
.als{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac}

/* Submit */
.sbtn{
    width:100%;padding:16px;background:var(--gradient);color:#0a0a0b;
    font-weight:700;font-size:15px;border:none;border-radius:12px;cursor:pointer;
    font-family:var(--fh);letter-spacing:.02em;transition:all .3s;
    display:flex;align-items:center;justify-content:center;gap:9px;margin-top:4px;
}
.sbtn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(232,184,75,.3)}
.sbtn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}

/* Back link */
.back-row{text-align:center;margin-top:22px}
.back-row a{font-size:13px;color:var(--muted);display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:color .2s}
.back-row a:hover{color:var(--accent)}

/* Role info chips */
.role-features{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;min-height:30px}
.rf-chip{
    display:inline-flex;align-items:center;gap:5px;padding:4px 10px;
    border-radius:50px;font-size:11px;font-weight:600;
    background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.2);color:var(--accent);
}

@media(max-width:480px){.card{padding:28px 20px}.role-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">

        <!-- Logo -->
        <div class="logo-row">
            <a href="index.php" class="logo">
                <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="ld"></div>
            </a>
        </div>

        <!-- Current role -->
        <div class="current-role">
            <i class="fas fa-user-circle" style="color:var(--accent)"></i>
            <span style="color:var(--muted)">Logged in as <strong style="color:var(--white)"><?= e($user['name']) ?></strong></span>
            <span class="role-badge"><?= ucfirst(str_replace('_',' ',$currentRole)) ?></span>
        </div>

        <!-- Header -->
        <div class="hd">
            <h1>Change Account Type</h1>
            <p>Switch your role anytime — your listings and messages are preserved.</p>
        </div>

        <!-- Errors / alerts -->
        <?php if (!empty($errors['general'])): ?>
        <div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= e($errors['general']) ?></span></div>
        <?php endif; ?>
        <?php showFlash('success'); ?>

        <!-- Info box -->
        <div class="info-box">
            <i class="fas fa-info-circle" style="flex-shrink:0;margin-top:1px"></i>
            <span>Switching to <strong>Seller</strong> or <strong>Dealer</strong> lets you post car listings. As a <strong>Buyer</strong> you can browse, save, and compare cars.</span>
        </div>

        <!-- Role features display -->
        <div class="role-features" id="roleFeatures">
            <!-- Filled by JS -->
        </div>

        <form method="POST" id="upgradeForm" novalidate>
            <?= CSRF::field() ?>

            <!-- Role selector -->
            <div class="role-grid">
                <div>
                    <input type="radio" name="role" value="buyer" id="r_buyer" class="role-radio"
                           <?= $preselect==='buyer'?'checked':'' ?> onchange="onRole('buyer')">
                    <label for="r_buyer" class="role-card">
                        <div class="role-check"><i class="fas fa-check"></i></div>
                        <span class="role-emoji">🛍️</span>
                        <span class="role-title">Buyer</span>
                        <span class="role-desc">Browse &amp; save cars</span>
                        <?php if ($currentRole==='buyer'): ?><span class="current-tag">Current</span><?php endif; ?>
                    </label>
                </div>
                <div>
                    <input type="radio" name="role" value="private_seller" id="r_seller" class="role-radio"
                           <?= $preselect==='private_seller'?'checked':'' ?> onchange="onRole('private_seller')">
                    <label for="r_seller" class="role-card">
                        <div class="role-check"><i class="fas fa-check"></i></div>
                        <span class="role-emoji">👤</span>
                        <span class="role-title">Seller</span>
                        <span class="role-desc">Private individual</span>
                        <?php if ($currentRole==='private_seller'): ?><span class="current-tag">Current</span><?php endif; ?>
                    </label>
                </div>
                <div>
                    <input type="radio" name="role" value="dealer" id="r_dealer" class="role-radio"
                           <?= $preselect==='dealer'?'checked':'' ?> onchange="onRole('dealer')">
                    <label for="r_dealer" class="role-card">
                        <div class="role-check"><i class="fas fa-check"></i></div>
                        <span class="role-emoji">🏢</span>
                        <span class="role-title">Dealer</span>
                        <span class="role-desc">Showroom / Business</span>
                        <?php if ($currentRole==='dealer'): ?><span class="current-tag">Current</span><?php endif; ?>
                    </label>
                </div>
            </div>
            <?php if (isset($errors['role'])): ?>
            <div class="ferr" style="margin-top:-12px;margin-bottom:14px"><i class="fas fa-circle-exclamation"></i><?= e($errors['role']) ?></div>
            <?php endif; ?>

            <!-- Dealer business name -->
            <div class="dealer-fields" id="dealerFields">
                <div class="fld">
                    <label for="biz">Business / Showroom Name <span style="color:var(--red)">*</span></label>
                    <div class="iw">
                        <input type="text" id="biz" name="business_name"
                               value="<?= e($_POST['business_name'] ?? $user['business_name'] ?? '') ?>"
                               placeholder="e.g. Karachi Motors" maxlength="100">
                        <i class="fas fa-briefcase ico"></i>
                    </div>
                    <?php if (isset($errors['business_name'])): ?>
                    <div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['business_name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- City -->
            <div class="fld">
                <label for="city_in">City</label>
                <div class="iw">
                    <input type="text" id="city_in" name="city"
                           value="<?= e($_POST['city'] ?? $user['city'] ?? '') ?>"
                           placeholder="e.g. Karachi" maxlength="100">
                    <i class="fas fa-map-marker-alt ico"></i>
                </div>
                <?php if (isset($errors['city'])): ?>
                <div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['city']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Phone (pre-filled, read-only for reference) -->
            <div class="fld">
                <label for="phone_in">Phone Number</label>
                <div class="iw">
                    <input type="tel" id="phone_in" name="phone"
                           value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                           placeholder="+92 312 345 6789" maxlength="25">
                    <i class="fas fa-phone ico"></i>
                </div>
            </div>

            <button type="submit" class="sbtn" id="sbtn">
                <i class="fas fa-exchange-alt" id="sbtnIcon"></i>
                <span id="sbtnText">Update My Account</span>
            </button>
        </form>

        <div class="back-row">
            <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Go back</a>
            &nbsp;·&nbsp;
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
        </div>
    </div>
</div>

<script>
const roleFeatures = {
    buyer:          ['Browse all listings','Save favourite cars','Compare cars','Message sellers','Book test drives'],
    private_seller: ['Post car listings','Receive buyer messages','Dashboard & analytics','Browse & save cars','Compare cars'],
    dealer:         ['Unlimited listings','Dealer badge & profile','Featured listing boosts','Full dashboard','Message buyers','Browse & save cars'],
};

function onRole(role) {
    // Show/hide dealer fields
    document.getElementById('dealerFields').classList.toggle('show', role === 'dealer');
    document.getElementById('biz').required = (role === 'dealer');

    // Update feature chips
    const rf = document.getElementById('roleFeatures');
    rf.innerHTML = (roleFeatures[role] || []).map(f =>
        `<span class="rf-chip"><i class="fas fa-check" style="font-size:9px"></i>${f}</span>`
    ).join('');

    // Update button text
    const labels = {buyer:'Switch to Buyer', private_seller:'Become a Seller', dealer:'Become a Dealer'};
    document.getElementById('sbtnText').textContent = labels[role] || 'Update My Account';
}

// Init on load
const checked = document.querySelector('input[name="role"]:checked');
if (checked) onRole(checked.value);

// Submit guard
document.getElementById('upgradeForm').addEventListener('submit', function() {
    document.getElementById('sbtn').disabled = true;
    document.getElementById('sbtnIcon').className = 'fas fa-spinner fa-spin';
    document.getElementById('sbtnText').textContent = 'Updating…';
});
</script>
</body>
</html>
