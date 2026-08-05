<?php
//  CarSoko Pakistan — login.php
require_once 'connection.php';

if (Auth::check()) {
    $u = Auth::user();
    redirect($u['role'] === 'buyer' ? BASE_URL . '/index.php' : BASE_URL . '/dashboard.php');
}

// ============================================================
// GOOGLE AUTH — Generate auth URL + CSRF state
// ============================================================
$googleAuthUrl = null;
$googleSetup   = !empty($_SESSION['google_pending']) && (isset($_GET['google_setup']) || isset($_POST['google_setup']));
$googleError   = '';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
        $gClient = include 'google-config.php';
        $state   = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $gClient->setState($state);
        $googleAuthUrl = $gClient->createAuthUrl();
    } catch (Exception $e) {
        // Google SDK not available — silently degrade
        $googleAuthUrl = null;
    }
}

// ============================================================
// GOOGLE SETUP ERROR (from URL param after redirect)
// ============================================================
$urlError = cleanInput($_GET['error'] ?? '');
$googleErrorMessages = [
    'google_failed'     => 'Google sign-in was cancelled. Please try again.',
    'google_state'      => 'Security check failed. Please try again.',
    'google_error'      => 'Google sign-in encountered an error. Please try again.',
    'account_suspended' => 'Your account has been suspended. Contact support.',
];
if ($urlError && isset($googleErrorMessages[$urlError])) {
    $googleError = $googleErrorMessages[$urlError];
}

// ============================================================
// NORMAL LOGIN — POST handler
// ============================================================
$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    CSRF::check();

    $email    = cleanInput($_POST['email']    ?? '');
    $password = $_POST['password']            ?? '';
    $remember = !empty($_POST['remember']);

    if (!$email)    $errors['email']    = 'Email is required.';
    if (!$password) $errors['password'] = 'Password is required.';

    if (empty($errors)) {
        if (!RateLimit::check('login', 10, 900)) {
            $errors['general'] = 'Too many attempts. Please wait 15 minutes or reset your password.';
        } else {
            $user = DB::selectOne("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);

            if (!$user || !Auth::verifyPassword($password, $user['password'])) {
                $errors['general'] = 'Incorrect email or password.';
                if ($user) {
                    $attempts = ($user['login_attempts'] ?? 0) + 1;
                    $lockout  = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                    DB::execute("UPDATE users SET login_attempts = ?, lockout_until = ? WHERE id = ?", [$attempts, $lockout, $user['id']]);
                }
            } elseif (in_array($user['status'], ['suspended', 'banned'])) {
                $errors['general'] = $user['status'] === 'suspended'
                    ? 'Your account is suspended. Email <a href="mailto:support@carsoko.pk" style="color:var(--accent)">support@carsoko.pk</a>.'
                    : 'Your account has been permanently banned.';
            } elseif (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
                $mins = ceil((strtotime($user['lockout_until']) - time()) / 60);
                $errors['general'] = "Account locked. Try again in {$mins} minute(s).";
            } elseif (!$user['email_verified']) {
                $errors['general'] = 'Email not verified. <a href="resend-verification.php?email=' . urlencode($email) . '" style="color:var(--accent);text-decoration:underline">Resend link</a>.';
            } else {
                // ── SUCCESS ──
                DB::execute(
                    "UPDATE users SET login_attempts = 0, lockout_until = NULL, last_login = NOW(), last_login_ip = ? WHERE id = ?",
                    [$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]
                );
                logActivity('user.login', $user['id'], 'user');
                Auth::login($user, $remember);

                // Check if must change password (from forgot password temp pwd)
                if (!empty($user['must_change_password'])) {
                    redirect(BASE_URL . '/change-password.php?forced=1');
                }

                $intended = cleanInput($_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? '');
                unset($_SESSION['redirect_after_login']);

                if ($intended && (str_starts_with($intended, '/') || str_starts_with($intended, BASE_URL))) {
                    redirect($intended);
                } elseif ($user['role'] === 'buyer') {
                    redirect(BASE_URL . '/index.php');
                } else {
                    redirect(BASE_URL . '/dashboard.php');
                }
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
<title>Sign In – CarSoko Pakistan</title>
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
    --blue: #3b82f6;
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
        radial-gradient(circle at 20% 30%, rgba(232, 184, 75, 0.05) 0%, transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(255, 107, 53, 0.05) 0%, transparent 40%);
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
    mask-image: radial-gradient(circle at center, black, transparent 80%);
}

.auth-container {
    width: 100%;
    max-width: 480px;
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
    font-size: 32px;
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
    width: 10px;
    height: 10px;
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

/* Social Login */
.soc { display: flex; gap: 12px; margin-bottom: 24px; }
.sb {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    font-weight: 600;
    color: var(--white);
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}
.sb:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
.sb.google:hover { border-color: rgba(234, 67, 53, 0.4); background: rgba(234, 67, 53, 0.08); }

.div-sep {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.div-sep::before, .div-sep::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Fields */
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
.iw input {
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
.iw input:focus {
    border-color: var(--accent);
    background: rgba(232, 184, 75, 0.05);
    box-shadow: 0 0 0 4px rgba(232, 184, 75, 0.1);
}
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

.ferr { font-size: 12px; color: var(--red); margin-top: 6px; display: flex; align-items: center; gap: 6px; }

.fmeta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.rem { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; color: var(--muted); user-select: none; }
.rem input { width: 18px; height: 18px; accent-color: var(--accent); cursor: pointer; }
.fl { font-size: 14px; color: var(--accent); cursor: pointer; background: none; border: none; font-family: var(--fb); font-weight: 600; padding: 0; transition: all 0.2s; }
.fl:hover { color: var(--accent2); text-decoration: underline; }

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
}
.sbtn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(232, 184, 75, 0.3); }
.sbtn:disabled { pointer-events: none; opacity: 0.7; }

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

.al { padding: 14px 18px; border-radius: 12px; font-size: 14px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px; line-height: 1.6; }
.ale { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
.als { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #86efac; }

.footer-links { margin-top: 32px; text-align: center; font-size: 14px; color: var(--muted); }
.footer-links a { color: var(--accent); font-weight: 700; text-decoration: none; }
.footer-links a:hover { color: var(--accent2); text-decoration: underline; }

.back-link { display: inline-flex; align-items: center; gap: 8px; margin-top: 24px; font-size: 13px; color: var(--muted); text-decoration: none; transition: color 0.2s; }
.back-link:hover { color: var(--accent); }

/* Overlays */
.ov {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(16px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.ov.open { display: flex; animation: fadeInOv 0.3s ease; }
@keyframes fadeInOv { from { opacity: 0; } to { opacity: 1; } }

.ovc {
    background: #16161a;
    border: 1px solid var(--border);
    border-radius: 32px;
    padding: 48px 40px;
    width: min(480px, 100%);
    box-shadow: 0 32px 100px rgba(0, 0, 0, 0.8);
    position: relative;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

.ovcl {
    position: absolute;
    top: 24px;
    right: 24px;
    background: rgba(255, 255, 255, 0.05);
    border: none;
    color: var(--muted);
    width: 40px;
    height: 40px;
    border-radius: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.ovcl:hover { background: rgba(255, 255, 255, 0.1); color: var(--white); }

.pwd-box {
    background: rgba(232, 184, 75, 0.08);
    border: 2px dashed rgba(232, 184, 75, 0.3);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    margin: 24px 0;
}
.pwd-val { font-family: monospace; font-size: 32px; font-weight: 800; letter-spacing: 4px; color: var(--accent); }
.pwd-copy { margin-top: 12px; font-size: 12px; color: var(--muted); cursor: pointer; transition: color 0.2s; }
.pwd-copy:hover { color: var(--white); }

@media (max-width: 480px) {
    .auth-card { padding: 40px 24px; }
    .soc { flex-direction: column; }
}
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
            <h1>Welcome Back</h1>
            <p>Sign in to your CarSoko account</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
        <div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= $errors['general'] ?></span></div>
        <?php endif; ?>

        <?php if ($googleError): ?>
        <div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= e($googleError) ?></span></div>
        <?php endif; ?>

        <!-- Google Sign-In -->
        <?php if ($googleAuthUrl): ?>
        <div class="soc">
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="sb google">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#EA4335" d="M5.27 9.76A7.08 7.08 0 0 1 12 4.9c1.76 0 3.35.63 4.59 1.67l3.41-3.41A12 12 0 0 0 .29 9.29l4.98 3.87z"/>
                    <path fill="#34A853" d="M16.04 18.01A7.07 7.07 0 0 1 12 19.1a7.08 7.08 0 0 1-6.72-4.88l-4.98 3.86A12 12 0 0 0 12 24c3.24 0 6.32-1.2 8.61-3.38z"/>
                    <path fill="#4A90D9" d="M23.71 12.27c0-.78-.07-1.56-.2-2.27H12v4.51h6.56a5.61 5.61 0 0 1-2.43 3.68l4.97 3.86c2.91-2.69 4.61-6.65 4.61-9.78z"/>
                    <path fill="#FBBC05" d="M5.28 14.22a7.1 7.1 0 0 1 0-4.46L.3 5.89A11.99 11.99 0 0 0 0 12c0 2.13.52 4.14 1.44 5.91l3.84-3.69z"/>
                </svg>
                Sign in with Google
            </a>
        </div>
        <div class="div-sep">or email</div>
        <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>
            <?= CSRF::field() ?>
            <input type="hidden" name="login" value="1">
            <?php if ($redirectParam): ?><input type="hidden" name="redirect" value="<?= $redirectParam ?>"><?php endif; ?>

            <div class="fld">
                <label for="em">Email Address</label>
                <div class="iw">
                    <input type="email" id="em" name="email" value="<?= e($email) ?>" placeholder="name@example.com" autocomplete="email" autofocus required class="<?= isset($errors['email']) || isset($errors['general']) ? 'err' : '' ?>">
                    <i class="fas fa-envelope ico"></i>
                </div>
                <?php if (isset($errors['email'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['email']) ?></div><?php endif; ?>
            </div>

            <div class="fld">
                <label for="pw">Password</label>
                <div class="iw">
                    <input type="password" id="pw" name="password" placeholder="••••••••" autocomplete="current-password" required class="<?= isset($errors['password']) ? 'err' : '' ?>">
                    <i class="fas fa-lock ico"></i>
                    <button type="button" class="pt" onclick="togglePwd('pw',this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
                <?php if (isset($errors['password'])): ?><div class="ferr"><i class="fas fa-circle-exclamation"></i><?= e($errors['password']) ?></div><?php endif; ?>
            </div>

            <div class="fmeta">
                <label class="rem"><input type="checkbox" name="remember" value="1"> Remember me</label>
                <a href="forget.php" class="fl">Forgot password?</a>
            </div>

            <button type="submit" class="sbtn" id="loginBtn">
                <div class="spin" id="loginSpin"></div>
                <i class="fas fa-sign-in-alt" id="loginIcon"></i>
                <span id="loginText">Sign In</span>
            </button>
        </form>

        <div class="footer-links">
            Don't have an account? <a href="register.php<?= $redirectParam ? '?redirect=' . urlencode($redirectParam) : '' ?>">Create one free →</a>
            <br>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>

<script>
function togglePwd(id, btn) {
    const i = document.getElementById(id);
    const ic = btn.querySelector('i');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.getElementById('loginForm').addEventListener('submit', function() {
    if (!document.getElementById('em').value || !document.getElementById('pw').value) return;
    document.getElementById('loginBtn').disabled = true;
    document.getElementById('loginSpin').style.display = 'block';
    document.getElementById('loginIcon').style.display = 'none';
    document.getElementById('loginText').textContent = 'Signing in...';
});
</script>
</body>
</html>
