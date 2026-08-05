<?php
//  CarSoko Pakistan — register.php
//  - International phone numbers (all countries via intl-tel-input)
//  - Buyer → index.php after registration
//  - Private seller / dealer → dashboard.php after registration
// ============================================================
require_once 'connection.php';

if (Auth::check()) {
    // If already logged in, redirect to upgrade-role.php to change role
    $_role = cleanInput($_GET['role'] ?? '');
    if ($_role) {
        redirect(BASE_URL . '/upgrade-role.php?role=' . urlencode($_role));
    }
    $u = Auth::user();
    redirect($u['role'] === 'buyer' ? BASE_URL . '/index.php' : BASE_URL . '/dashboard.php');
}

$errors = [];
$old    = [];

// ============================================================
// HANDLE REGISTRATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();

    if (!RateLimit::check('register', 3, 600)) {
        $errors['general'] = 'Too many registration attempts. Please wait 10 minutes.';
    } else {

        $old = [
            'name'          => cleanInput($_POST['name']          ?? ''),
            'email'         => cleanInput($_POST['email']         ?? ''),
            'phone'         => cleanInput($_POST['phone_full']    ?? ''),  // intl-tel-input sends full number
            'phone_display' => cleanInput($_POST['phone_display'] ?? ''),  // formatted for display
            'role'          => cleanInput($_POST['role']          ?? 'buyer'),
            'business_name' => cleanInput($_POST['business_name'] ?? ''),
            'city'          => cleanInput($_POST['city']          ?? ''),
            'terms'         => !empty($_POST['terms']),
        ];
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // ── VALIDATE ──
        if (strlen($old['name']) < 2)
            $errors['name'] = 'Full name must be at least 2 characters.';
        elseif (strlen($old['name']) > 100)
            $errors['name'] = 'Name is too long (max 100 characters).';

        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Please enter a valid email address.';
        elseif (DB::exists("SELECT 1 FROM users WHERE email=?", [$old['email']]))
            $errors['email'] = 'This email is already registered. <a href="login.php" style="color:var(--accent);text-decoration:underline">Sign in instead?</a>';

        // Phone: accept any international number, strip non-digits for storage
        $phoneClean = preg_replace('/[^\d+]/', '', $old['phone']);
        if (strlen($phoneClean) < 7 || strlen($phoneClean) > 20)
            $errors['phone'] = 'Please enter a valid phone number including country code.';
        elseif (DB::exists("SELECT 1 FROM users WHERE phone=?", [$phoneClean]))
            $errors['phone'] = 'This phone number is already registered.';

        if (!in_array($old['role'], ['buyer','private_seller','dealer']))
            $errors['role'] = 'Please select a valid account type.';

        if ($old['role'] === 'dealer' && strlen($old['business_name']) < 2)
            $errors['business_name'] = 'Business name is required for dealers.';

        if (!$old['city'])
            $errors['city'] = 'Please enter your city.';
        elseif (strlen($old['city']) > 100)
            $errors['city'] = 'City name too long.';

        if (strlen($password) < 8)
            $errors['password'] = 'Password must be at least 8 characters.';

        if ($password !== $password2)
            $errors['password2'] = 'Passwords do not match.';

        if (!$old['terms'])
            $errors['terms'] = 'You must agree to the Terms of Use to continue.';

        // ── CREATE USER ──
        if (empty($errors)) {
            $verifyToken = bin2hex(random_bytes(32));

            $userId = DB::insert(
                "INSERT INTO users (name,email,phone,password,role,city,business_name,email_verify_token,status)
                 VALUES (?,?,?,?,?,?,?,?,'active')",
                [
                    $old['name'],
                    $old['email'],
                    $phoneClean,
                    Auth::hashPassword($password),
                    $old['role'],
                    $old['city'],
                    $old['role'] === 'dealer' ? $old['business_name'] : null,
                    $verifyToken,
                ]
            );

            if ($userId) {
                logActivity('user.register', $userId, 'user', $old['role']);

                // Auto-verify for development (no mail server on InfinityFree free tier)
                // In production: comment this block and send real email below
                DB::execute("UPDATE users SET email_verified=1 WHERE id=?", [$userId]);
                $user = DB::find('users', $userId);
                Auth::login($user, false);

                // Production email (un-comment when SMTP configured):
                // $link = BASE_URL.'/verify-email.php?token='.$verifyToken.'&email='.urlencode($old['email']);
                // mail($old['email'], 'Verify your CarSoko account', "Hi {$old['name']},\n\nVerify here: $link\n\nLink expires in 24 hours.");

                flash('welcome', 'Welcome to ' . setting('site_name','CarSoko') . ', ' . $old['name'] . '! 🎉');

                // Role-based redirect
                $redirect = cleanInput($_POST['redirect'] ?? '');
                if ($redirect && (str_starts_with($redirect,'/') || str_starts_with($redirect,BASE_URL))) {
                    redirect($redirect);
                } elseif ($old['role'] === 'buyer') {
                    redirect(BASE_URL . '/index.php');
                } else {
                    redirect(BASE_URL . '/dashboard.php');
                }
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
    }
}

$redirectParam = htmlspecialchars($_GET['redirect'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Create Account – CarSoko Pakistan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --black: #0a0a0b;
    --dark: #111114;
    --card-bg: rgba(22, 22, 26, 0.7);
    --border: rgba(255, 255, 255, 0.08);
    --white: #f5f5f0;
    --muted: #888896;
    --accent: #e8b84b;
    --accent2: #ff6b35;
    --green: #22c55e;
    --red: #ef4444;
    --gradient: linear-gradient(135deg, #e8b84b, #ff6b35);
    --fh: 'Syne', sans-serif;
    --fb: 'DM Sans', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }

body {
    background: var(--black);
    color: var(--white);
    font-family: var(--fb);
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
    padding: 40px 20px;
}

/* Background Animation */
body::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 10% 20%, rgba(232, 184, 75, 0.05) 0%, transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(255, 107, 53, 0.05) 0%, transparent 40%);
    z-index: -1;
}

body::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(232, 184, 75, 0.02) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(232, 184, 75, 0.02) 1px, transparent 1px);
    background-size: 60px 60px;
    z-index: -1;
    mask-image: radial-gradient(circle at center, black, transparent 90%);
}

.auth-container {
    width: 100%;
    max-width: 520px;
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.auth-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: 32px;
    padding: 48px 40px;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5);
}

.logo-wrapper {
    text-align: center;
    margin-bottom: 32px;
}

.logo-a {
    font-family: var(--fh);
    font-size: 28px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    color: var(--white);
}

.logo-a span:first-child {
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ld {
    width: 8px;
    height: 8px;
    background: var(--gradient);
    border-radius: 50%;
    margin-left: 2px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0.6; }
}

.auth-header {
    text-align: center;
    margin-bottom: 32px;
}

.auth-header h1 {
    font-family: var(--fh);
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
    letter-spacing: -0.02em;
}

.auth-header p {
    font-size: 14px;
    color: var(--muted);
}

/* Role Selector */
.role-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 32px;
}

.role-radio { display: none; }
.role-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 10px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.role-label:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
}

.role-radio:checked + .role-label {
    background: rgba(232, 184, 75, 0.08);
    border-color: var(--accent);
    box-shadow: 0 0 0 4px rgba(232, 184, 75, 0.1);
}

.role-emoji { font-size: 24px; }
.role-title { font-size: 13px; font-weight: 700; color: var(--white); }
.role-desc { font-size: 10px; color: var(--muted); line-height: 1.3; }

/* Forms */
.fld { margin-bottom: 20px; }
.fld label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted);
    margin-bottom: 8px;
}

.iw { position: relative; }
.iw i.ico {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: 14px;
    pointer-events: none;
    transition: color 0.3s;
}

.iw input, .iw select {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border);
    color: var(--white);
    padding: 14px 16px 14px 46px;
    border-radius: 12px;
    font-size: 15px;
    font-family: var(--fb);
    outline: none;
    transition: all 0.3s;
}

.iw input:focus, .iw select:focus {
    border-color: var(--accent);
    background: rgba(232, 184, 75, 0.05);
    box-shadow: 0 0 0 4px rgba(232, 184, 75, 0.1);
}

.iw select { cursor: pointer; -webkit-appearance: none; }
.iw:focus-within i.ico { color: var(--accent); }
.iw input.err { border-color: var(--red); }

.pt {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--muted);
    font-size: 14px;
    background: none;
    border: none;
    padding: 4px;
    transition: color 0.2s;
}

.pt:hover { color: var(--accent); }

.ferr {
    font-size: 12px;
    color: var(--red);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sbtn {
    width: 100%;
    padding: 18px;
    background: var(--gradient);
    color: #0a0a0b;
    font-weight: 700;
    font-size: 16px;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    font-family: var(--fh);
    letter-spacing: 0.02em;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.sbtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(232, 184, 75, 0.3);
}

.spin {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-top-color: #000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: none;
}

@keyframes spin { to { transform: rotate(360deg); } }

.al {
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.ale { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }

.footer-links {
    margin-top: 32px;
    text-align: center;
    font-size: 14px;
    color: var(--muted);
}

.footer-links a {
    color: var(--accent);
    font-weight: 700;
    text-decoration: none;
}

.footer-links a:hover { color: var(--accent2); text-decoration: underline; }

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 24px;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
}

.dealer-fields { display: none; animation: slideDown 0.3s ease; }
.dealer-fields.show { display: block; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 480px) {
    .auth-card { padding: 32px 24px; }
    .role-grid { grid-template-columns: 1fr; }
}

/* Steps */
.step-indicator { display: flex; align-items: center; justify-content: center; gap: 40px; margin-bottom: 40px; position: relative; }
.step-indicator::before { content: ''; position: absolute; top: 16px; left: 50%; transform: translateX(-50%); width: 100px; height: 1px; background: var(--border); z-index: 0; }
.step { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.step-num { width: 32px; height: 32px; border-radius: 50%; background: var(--dark); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: var(--muted); transition: all 0.3s; }
.step.active .step-num { background: var(--gradient); border-color: transparent; color: #000; box-shadow: 0 0 20px rgba(232, 184, 75, 0.3); }
.step.done .step-num { background: var(--green); border-color: transparent; color: #000; }
.step-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
.step.active .step-label { color: var(--accent); }
</style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="logo-wrapper">
            <a href="index.php" class="logo-a">
                <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="ld"></div>
            </a>
        </div>

        <div class="auth-header">
            <h1>Create Account</h1>
            <p>Join Pakistan's premier car marketplace</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
        <div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= $errors['general'] ?></span></div>
        <?php endif; ?>

        <form method="POST" id="regForm" novalidate>
            <?= CSRF::field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirectParam) ?>">

            <!-- Steps -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-num">1</div>
                    <div class="step-label">Account</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-num">2</div>
                    <div class="step-label">Details</div>
                </div>
            </div>

            <!-- Account Type -->
            <div class="role-grid">
                <div>
                    <input type="radio" name="role" value="buyer" id="r_buyer" class="role-radio" <?= ($old['role']??'buyer')==='buyer'?'checked':'' ?> onchange="onRole('buyer')">
                    <label for="r_buyer" class="role-label">
                        <span class="role-emoji">🛍️</span>
                        <span class="role-title">Buyer</span>
                        <span class="role-desc">I want to buy a car</span>
                    </label>
                </div>
                <div>
                    <input type="radio" name="role" value="private_seller" id="r_seller" class="role-radio" <?= ($old['role']??'')==='private_seller'?'checked':'' ?> onchange="onRole('private_seller')">
                    <label for="r_seller" class="role-label">
                        <span class="role-emoji">👤</span>
                        <span class="role-title">Seller</span>
                        <span class="role-desc">Individual seller</span>
                    </label>
                </div>
                <div>
                    <input type="radio" name="role" value="dealer" id="r_dealer" class="role-radio" <?= ($old['role']??'')==='dealer'?'checked':'' ?> onchange="onRole('dealer')">
                    <label for="r_dealer" class="role-label">
                        <span class="role-emoji">🏢</span>
                        <span class="role-title">Dealer</span>
                        <span class="role-desc">Showroom / Biz</span>
                    </label>
                </div>
            </div>

            <!-- DEALER FIELDS -->
            <div class="dealer-fields" id="dealerFields">
                <div class="fld">
                    <label for="biz">Business Name</label>
                    <div class="iw">
                        <input type="text" id="biz" name="business_name" value="<?= e($old['business_name']??'') ?>" placeholder="e.g. Karachi Motors">
                        <i class="fas fa-briefcase ico"></i>
                    </div>
                    <?php if (isset($errors['business_name'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['business_name']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="f-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="fld">
                    <label for="nm">Full Name</label>
                    <div class="iw">
                        <input type="text" id="nm" name="name" value="<?= e($old['name']??'') ?>" placeholder="Ahmed Khan" autocomplete="name" class="<?= isset($errors['name'])?'err':'' ?>">
                        <i class="fas fa-user ico"></i>
                    </div>
                    <?php if (isset($errors['name'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="fld">
                    <label for="ct">City / Town</label>
                    <div class="iw">
                        <input type="text" id="ct" name="city" value="<?= e($old['city']??'') ?>" placeholder="e.g. Karachi" autocomplete="address-level2" class="<?= isset($errors['city'])?'err':'' ?>">
                        <i class="fas fa-map-marker-alt ico"></i>
                    </div>
                    <?php if (isset($errors['city'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['city']) ?></div><?php endif; ?>
                </div>
            </div>

            <!-- EMAIL -->
            <div class="fld">
                <label for="em">Email Address</label>
                <div class="iw">
                    <input type="email" id="em" name="email" value="<?= e($old['email']??'') ?>" placeholder="you@example.com" autocomplete="email" class="<?= isset($errors['email'])?'err':'' ?>">
                    <i class="fas fa-envelope ico"></i>
                </div>
                <?php if (isset($errors['email'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= $errors['email'] ?></div><?php endif; ?>
            </div>

            <!-- PHONE -->
            <div class="fld">
                <label for="phone_input">Phone Number</label>
                <div class="iw">
                    <input type="tel" id="phone_input" name="phone_full"
                           value="<?= e($old['phone'] ?? '') ?>"
                           placeholder="+92 312 345 6789"
                           autocomplete="tel"
                           class="<?= isset($errors['phone'])?'err':'' ?>">
                    <i class="fas fa-phone ico"></i>
                </div>
                <input type="hidden" name="phone_display" id="phone_display">
                <?php if (isset($errors['phone'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['phone']) ?></div><?php endif; ?>
                <div style="font-size:11px;color:var(--muted);margin-top:5px"><i class="fas fa-info-circle"></i> Include country code e.g. +92 312 345 6789 — used for WhatsApp contact</div>
            </div>

            <!-- PASSWORD -->
            <div class="fld">
                <label for="pw">Password</label>
                <div class="iw">
                    <input type="password" id="pw" name="password" placeholder="Min. 8 characters" autocomplete="new-password" oninput="chkStr(this.value)" class="<?= isset($errors['password'])?'err':'' ?>">
                    <i class="fas fa-lock ico"></i>
                    <button type="button" class="pt" onclick="tglPwd('pw',this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pstr" id="pstr">
                    <div class="pbar" id="pb1"></div><div class="pbar" id="pb2"></div>
                    <div class="pbar" id="pb3"></div><div class="pbar" id="pb4"></div>
                    <span class="plbl" id="plbl"></span>
                </div>
                <?php if (isset($errors['password'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['password']) ?></div><?php endif; ?>
            </div>

            <!-- CONFIRM PASSWORD -->
            <div class="fld">
                <label for="pw2">Confirm Password</label>
                <div class="iw">
                    <input type="password" id="pw2" name="password2" placeholder="Repeat your password" autocomplete="new-password" oninput="chkMatch()" class="<?= isset($errors['password2'])?'err':'' ?>">
                    <i class="fas fa-lock ico"></i>
                    <button type="button" class="pt" onclick="tglPwd('pw2',this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
                <div class="match" id="match"></div>
                <?php if (isset($errors['password2'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['password2']) ?></div><?php endif; ?>
            </div>

            <!-- TERMS -->
            <label class="trow">
                <input type="checkbox" name="terms" value="1" <?= !empty($old['terms'])?'checked':'' ?>>
                <span>I agree to <?= setting('site_name','CarSoko') ?>'s <a href="terms.php" target="_blank">Terms of Use</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>. I am at least 18 years old.</span>
            </label>
            <?php if (isset($errors['terms'])): ?><div class="ferr" style="margin-top:-10px;margin-bottom:14px"><i class="fas fa-circle-exclamation"></i><?= e($errors['terms']) ?></div><?php endif; ?>

            <!-- SUBMIT -->
            <button type="submit" class="sbtn" id="sbtn">
                <div class="spin" id="sp"></div>
                <i class="fas fa-user-plus" id="si"></i>
                <span id="st">Create Free Account</span>
            </button>
        </form>

        <div class="sw">Already have an account? <a href="login.php<?= $redirectParam?'?redirect='.urlencode($redirectParam):'' ?>">Sign in →</a></div>

        <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--border);text-align:center">
            <a href="index.php" style="font-size:13px;color:var(--muted);display:inline-flex;align-items:center;gap:6px;transition:color .2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'"><i class="fas fa-arrow-left"></i> Back to <?= setting('site_name','CarSoko') ?></a>
        </div>
    </div>
</div>


<script>
// ── ROLE TOGGLE ──
function onRole(role) {
    document.getElementById('dealerFields').classList.toggle('show', role === 'dealer');
    document.getElementById('biz').required = (role === 'dealer');
    // Step animation
    const s1 = document.getElementById('step1');
    const s2 = document.getElementById('step2');
    s1.classList.remove('active'); s1.classList.add('done');
    s1.querySelector('.step-num').innerHTML = '<i class="fas fa-check" style="font-size:10px"></i>';
    s2.classList.add('active');
}
// Init on load (handles validation error repopulation)
const checked = document.querySelector('input[name="role"]:checked');
if (checked) onRole(checked.value);

// ── PASSWORD STRENGTH ──
function chkStr(v) {
    const meter = document.getElementById('pstr');
    const lbl   = document.getElementById('plbl');
    const bars  = ['pb1','pb2','pb3','pb4'].map(id => document.getElementById(id));
    if (!v) { meter.style.display='none'; return; }
    meter.style.display='flex';
    let s=0;
    if(v.length>=8)s++;
    if(/[A-Z]/.test(v))s++;
    if(/[0-9]/.test(v))s++;
    if(/[^A-Za-z0-9]/.test(v))s++;
    const c=['#ef4444','#f97316','#eab308','#22c55e'];
    const t=['Weak','Fair','Good','Strong'];
    bars.forEach((b,i)=>{ b.style.background = i<s ? c[s-1] : 'rgba(255,255,255,.07)'; });
    lbl.textContent = t[s-1]||''; lbl.style.color = c[s-1]||'var(--muted)';
}

// ── PASSWORD MATCH ──
function chkMatch() {
    const p1=document.getElementById('pw').value;
    const p2=document.getElementById('pw2').value;
    const m=document.getElementById('match');
    if(!p2){m.style.display='none';return;}
    m.style.display='flex';m.style.alignItems='center';m.style.gap='4px';
    if(p1===p2){m.style.color='var(--green)';m.innerHTML='<i class="fas fa-check"></i> Passwords match';}
    else{m.style.color='var(--red)';m.innerHTML='<i class="fas fa-times"></i> Passwords do not match';}
}

// ── PASSWORD TOGGLE ──
function tglPwd(id,btn){
    const i=document.getElementById(id);const ic=btn.querySelector('i');
    i.type=i.type==='password'?'text':'password';
    ic.className=i.type==='text'?'fas fa-eye-slash':'fas fa-eye';
}

// ── FORM SUBMIT ──
document.getElementById('regForm').addEventListener('submit', function(e) {
    // Copy phone value to hidden display field
    const phoneInput = document.getElementById('phone_input');
    document.getElementById('phone_display').value = phoneInput.value;

    // Basic client-side check — must have something
    if (!phoneInput.value.trim()) {
        e.preventDefault();
        phoneInput.classList.add('err');
        let existing = phoneInput.closest('.fld')?.querySelector('.ferr.js-err');
        if (!existing) {
            const d = document.createElement('div');
            d.className = 'ferr js-err';
            d.innerHTML = '<i class="fas fa-circle-exclamation"></i> Please enter your phone number.';
            phoneInput.closest('.fld')?.appendChild(d);
        }
        return;
    }

    // Loading state
    document.getElementById('sbtn').classList.add('ld');
    document.getElementById('sp').style.display='block';
    document.getElementById('si').style.display='none';
    document.getElementById('st').textContent='Creating account…';
});

// Clear phone error on input
document.getElementById('phone_input').addEventListener('input', function() {
    this.classList.remove('err');
    const e = this.closest('.fld')?.querySelector('.ferr.js-err');
    if(e) e.remove();
});
</script>
</body>
</html>