<?php
//  CarSoko Pakistan — forget.php
//  - Handle Forgot Password flow
// ============================================================
require_once 'connection.php';

if (Auth::check()) {
    redirect(BASE_URL . '/index.php');
}

$step = 'email';
$msg = '';
$err = '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// If link clicked with token
if ($token && $email) {
    $step = 'reset';
    
    // Check if valid
    $user = DB::selectOne("SELECT id, password_reset_expires FROM users WHERE email=? AND password_reset_token=? LIMIT 1", [$email, $token]);
    if (!$user) {
        $err = 'Invalid or expired password reset link.';
        $step = 'email';
    } elseif (strtotime($user['password_reset_expires']) < time()) {
        $err = 'Password reset link has expired. Please request a new one.';
        $step = 'email';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    
    if (isset($_POST['action']) && $_POST['action'] === 'send_link') {
        $reqEmail = cleanInput($_POST['email'] ?? '');
        if (!filter_var($reqEmail, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            // Check if user exists
            $user = DB::selectOne("SELECT id, name FROM users WHERE email=? LIMIT 1", [$reqEmail]);
            if ($user) {
                // Generate token
                $resetToken = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                
                DB::execute("UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?", [$resetToken, $expires, $user['id']]);
                
                $link = BASE_URL . '/forget.php?token=' . $resetToken . '&email=' . urlencode($reqEmail);
                
                // Send email via PHPMailer
                require_once 'vendor/autoload.php';
                
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'newzely.com@gmail.com';
                    $mail->Password   = 'spqs rttd eyaz zvhw';
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom('newzely.com@gmail.com', setting('site_name', 'CarSoko Pakistan'));
                    $mail->addAddress($reqEmail, $user['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Reset your CarSoko password';
                    
                    $siteName = setting('site_name', 'CarSoko');
                    $userName = e($user['name']);
                    
                    $html = "
                    <!DOCTYPE html><html><body style='font-family:Arial,sans-serif;background:#f5f5f0;padding:32px'>
                    <div style='max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,0.08)'>
                      <div style='font-family:Georgia,serif;font-size:24px;font-weight:bold;margin-bottom:24px;color:#e8b84b'>
                        {$siteName}
                      </div>
                      <h2 style='margin:0 0 12px;font-size:20px;color:#111'>Hi {$userName},</h2>
                      <p style='color:#555;line-height:1.6;margin-bottom:20px'>You requested a password reset. Click the button below to create a new password:</p>
                      <a href='{$link}' style='display:inline-block;background:linear-gradient(135deg,#e8b84b,#ff6b35);color:#000;padding:12px 28px;border-radius:8px;font-weight:bold;text-decoration:none;margin-bottom:24px'>Reset Password &rarr;</a>
                      <p style='color:#555;font-size:14px;line-height:1.6;margin-bottom:24px'>
                        If you did not request this, please ignore this email. This link will expire in 1 hour.
                      </p>
                    </div></body></html>";
                    
                    $mail->Body    = $html;
                    $mail->AltBody = "Hi {$user['name']},\n\nYou requested a password reset.\nClick here to reset your password: $link\n\nIf you did not request this, please ignore this email.\nThis link will expire in 1 hour.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log('CarSoko forgot password mailer error: ' . $mail->ErrorInfo);
                }
            }
            // Always show success message for security (don't reveal if email exists)
            $msg = 'If your email is registered, you will receive a password reset link shortly.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $step = 'reset';
        $newPass = $_POST['password'] ?? '';
        $newPass2 = $_POST['password2'] ?? '';
        $token = $_POST['token'] ?? '';
        $email = $_POST['email'] ?? '';
        
        $user = DB::selectOne("SELECT id, password_reset_expires FROM users WHERE email=? AND password_reset_token=? LIMIT 1", [$email, $token]);
        
        if (!$user || strtotime($user['password_reset_expires']) < time()) {
            $err = 'Invalid or expired password reset link.';
            $step = 'email';
        } elseif (strlen($newPass) < 8) {
            $err = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPass)) {
            $err = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPass)) {
            $err = 'Password must contain at least one number.';
        } elseif ($newPass !== $newPass2) {
            $err = 'Passwords do not match.';
        } else {
            // Update password
            DB::execute("UPDATE users SET password=?, password_reset_token=NULL, password_reset_expires=NULL WHERE id=?", [Auth::hashPassword($newPass), $user['id']]);
            flash('success', 'Your password has been reset successfully. You can now login.');
            redirect(BASE_URL . '/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password – CarSoko Pakistan</title>
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
    padding: 40px 20px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 50%, rgba(232, 184, 75, 0.05) 0%, transparent 50%);
    z-index: -1;
}

.auth-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: 32px;
    padding: 48px 40px;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 440px;
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.logo-wrapper { text-align: center; margin-bottom: 32px; }
.logo-a { font-family: var(--fh); font-size: 28px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: var(--white); }
.logo-a span:first-child { background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.ld { width: 8px; height: 8px; background: var(--gradient); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.6; } }

.auth-header { text-align: center; margin-bottom: 32px; }
.auth-header h1 { font-family: var(--fh); font-size: 32px; font-weight: 800; margin-bottom: 8px; }
.auth-header p { font-size: 14px; color: var(--muted); }

.fld { margin-bottom: 20px; }
.fld label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 8px; }

.iw { position: relative; }
.iw i.ico { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px; pointer-events: none; transition: color 0.3s; }
.iw input { width: 100%; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); color: var(--white); padding: 14px 16px 14px 46px; border-radius: 12px; font-size: 15px; font-family: var(--fb); outline: none; transition: all 0.3s; }
.iw input:focus { border-color: var(--accent); background: rgba(232, 184, 75, 0.05); box-shadow: 0 0 0 4px rgba(232, 184, 75, 0.1); }
.iw:focus-within i.ico { color: var(--accent); }

.sbtn { width: 100%; padding: 18px; background: var(--gradient); color: #0a0a0b; font-weight: 700; font-size: 16px; border: none; border-radius: 14px; cursor: pointer; font-family: var(--fh); letter-spacing: 0.02em; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;}
.sbtn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(232, 184, 75, 0.3); }

.al { padding: 14px 18px; border-radius: 12px; font-size: 14px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px; }
.ale { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
.als { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #86efac; }

.sw { text-align: center; margin-top: 24px; font-size: 14px; color: var(--muted); }
.sw a { color: var(--accent); font-weight: 700; text-decoration: none; }
.sw a:hover { text-decoration: underline; }

.pt { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--muted); font-size: 14px; background: none; border: none; padding: 4px; transition: color 0.2s; }
.pt:hover { color: var(--accent); }
</style>
</head>
<body>

<div class="auth-card">
    <div class="logo-wrapper">
        <a href="index.php" class="logo-a">
            <span><?= substr(setting('site_name','CarSoko'),0,3) ?></span><span style="color:var(--white)"><?= substr(setting('site_name','CarSoko'),3) ?></span><div class="ld"></div>
        </a>
    </div>

    <?php if ($step === 'email'): ?>
        <div class="auth-header">
            <h1>Forgot Password</h1>
            <p>Enter your email to receive a reset link.</p>
        </div>

        <?php if ($err): ?><div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= e($err) ?></span></div><?php endif; ?>
        <?php if ($msg): ?><div class="al als"><i class="fas fa-check-circle"></i><span><?= e($msg) ?></span></div><?php endif; ?>

        <form method="POST">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="send_link">
            
            <div class="fld">
                <label for="em">Email Address</label>
                <div class="iw">
                    <input type="email" id="em" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
                    <i class="fas fa-envelope ico"></i>
                </div>
            </div>

            <button type="submit" class="sbtn">Send Reset Link <i class="fas fa-arrow-right"></i></button>
        </form>

    <?php elseif ($step === 'reset'): ?>
        <div class="auth-header">
            <h1>Create New Password</h1>
            <p>Please enter your new password below.</p>
        </div>

        <?php if ($err): ?><div class="al ale"><i class="fas fa-exclamation-circle"></i><span><?= e($err) ?></span></div><?php endif; ?>

        <form method="POST">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            
            <div class="fld">
                <label for="pw">New Password</label>
                <div class="iw">
                    <input type="password" id="pw" name="password" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
                    <i class="fas fa-lock ico"></i>
                    <button type="button" class="pt" onclick="tglPwd('pw',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <div class="fld">
                <label for="pw2">Confirm Password</label>
                <div class="iw">
                    <input type="password" id="pw2" name="password2" placeholder="Repeat your new password" required>
                    <i class="fas fa-lock ico"></i>
                    <button type="button" class="pt" onclick="tglPwd('pw2',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="sbtn">Reset Password <i class="fas fa-check"></i></button>
        </form>
    <?php endif; ?>

    <div class="sw">Remember your password? <a href="login.php">Sign in</a></div>
</div>

<script>
function tglPwd(id,btn){
    const i=document.getElementById(id);const ic=btn.querySelector('i');
    i.type=i.type==='password'?'text':'password';
    ic.className=i.type==='text'?'fas fa-eye-slash':'fas fa-eye';
}
</script>
</body>
</html>
