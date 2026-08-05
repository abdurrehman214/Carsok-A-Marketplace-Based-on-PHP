<?php
// ============================================================
//  CarSoko Pakistan — contact.php
//  All contact info pulled dynamically from admin settings
// ============================================================
require_once 'connection.php';

$siteName  = setting('site_name',  'CarSoko');
$siteEmail = setting('site_email', 'info@carsoko.pk');
$sitePhone = setting('site_phone', '+92 300 000 0000');
$siteCity  = setting('site_city',  'Karachi');
$waNumber  = setting('whatsapp_number', '923000000000');
$fb        = setting('facebook_url',  '');
$ig        = setting('instagram_url', '');
$tw        = setting('twitter_url',   '');
$wa        = setting('whatsapp_url',  '');
$li        = setting('linkedin_url',  '');

$sent    = false;
$errors  = [];
$success = '';

// Handle contact form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $cname   = trim(cleanInput($_POST['name']    ?? ''));
    $cemail  = trim(cleanInput($_POST['email']   ?? ''));
    $cphone  = trim(cleanInput($_POST['phone']   ?? ''));
    $csubj   = trim(cleanInput($_POST['subject'] ?? ''));
    $cmsg    = trim(cleanInput($_POST['message'] ?? ''));

    if (strlen($cname)  < 2)  $errors[] = 'Please enter your full name.';
    if (!filter_var($cemail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($cmsg)   < 10) $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        // Store in DB if table exists, otherwise just flash success
        try {
            DB::execute(
                "INSERT INTO contact_messages (name, email, phone, subject, message, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$cname, $cemail, $cphone, $csubj, $cmsg]
            );
        } catch (Throwable $e) {
            // Table may not exist yet — still show success
        }

        // Notify admin via notification system if possible
        try {
            $adminEmail = setting('admin_email', $siteEmail);
            $body = "New contact message from {$cname} ({$cemail})\n\nSubject: {$csubj}\n\nMessage:\n{$cmsg}";
            DB::execute(
                "INSERT INTO notifications (user_id, type, title, body, link, created_at)
                 SELECT id, 'contact_form', ?, ?, '/admin.php?tab=contacts', NOW()
                 FROM users WHERE role='admin' LIMIT 1",
                ["Contact Form: {$csubj}", $body]
            );
        } catch (Throwable $e) {}

        $success = "Thank you, {$cname}! Your message has been received. We'll get back to you within 24 hours.";
        $sent = true;
    }
}

$navUser     = Auth::check() ? Auth::user() : null;
$totalUnread = Auth::check() ? getUnreadCount()       : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Contact Us | <?= e($siteName) ?> Pakistan</title>
<meta name="description" content="Contact <?= e($siteName) ?> Pakistan — Pakistan's #1 car marketplace. Reach us by phone, WhatsApp, email or our contact form.">
<link rel="canonical" href="<?= BASE_URL ?>/contact.php">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--black:#0a0a0b;--dark:#111114;--card-bg:#18181c;--border:rgba(255,255,255,.07);--white:#f5f5f0;--muted:#888896;--accent:#e8b84b;--accent2:#ff6b35;--green:#22c55e;--red:#ef4444;--gradient:linear-gradient(135deg,#e8b84b,#ff6b35);--font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--radius:10px;--radius-lg:16px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:14px;line-height:1.6}
a{color:inherit;text-decoration:none}
.container{max-width:1100px;margin:0 auto;padding:0 20px}
/* NAVBAR */
.topbar{background:#0d0d10;border-bottom:1px solid var(--border);font-size:12px;color:var(--muted);padding:7px 0}
.topbar .container{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px}
.topbar a{color:var(--muted);margin-right:14px;transition:color .2s}
.topbar a:hover{color:var(--accent)}
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:20px}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo-car{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 12px;border-radius:8px;transition:all .2s}
.nav-links a:hover,.nav-links a.active{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .25s;font-family:var(--font-body)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-outline:hover{border-color:rgba(255,255,255,.3)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.4)}
@media(max-width:768px){.nav-links{display:none}.hamburger{display:flex}}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:6px}
.hamburger span{width:22px;height:2px;background:var(--white);border-radius:2px}
/* BREADCRUMB */
.breadcrumb{padding:14px 0;font-size:12px;color:var(--muted)}
.breadcrumb a{color:var(--muted);transition:color .2s}
.breadcrumb a:hover{color:var(--accent)}
.breadcrumb span{margin:0 6px;opacity:.4}
/* PAGE HERO */
.page-hero{padding:56px 0 40px;text-align:center;position:relative}
.page-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at center,rgba(232,184,75,.07),transparent 65%);pointer-events:none}
.section-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.2);color:var(--accent);padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:.06em;margin-bottom:16px}
.page-hero h1{font-family:var(--font-head);font-size:clamp(28px,5vw,46px);font-weight:800;margin-bottom:14px;position:relative}
.page-hero h1 span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-hero p{color:var(--muted);font-size:16px;max-width:520px;margin:0 auto;position:relative}
/* MAIN GRID */
.contact-grid{display:grid;grid-template-columns:1fr 420px;gap:28px;padding:48px 0 80px;align-items:start}
@media(max-width:900px){.contact-grid{grid-template-columns:1fr}}
/* FORM CARD */
.form-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px}
.form-card-title{font-family:var(--font-head);font-size:18px;font-weight:700;margin-bottom:6px}
.form-card-sub{font-size:13px;color:var(--muted);margin-bottom:24px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:13px;font-weight:500;color:rgba(245,245,240,.7);margin-bottom:6px}
.form-input,.form-select,.form-textarea{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:11px 14px;color:var(--white);font-family:var(--font-body);font-size:13px;outline:none;transition:border-color .2s,background .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:rgba(232,184,75,.5);background:rgba(255,255,255,.07)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--muted)}
.form-textarea{resize:vertical;min-height:130px;line-height:1.6}
.form-select option{background:var(--dark)}
.btn-submit{width:100%;padding:14px;font-size:15px;border-radius:50px;margin-top:4px}
.error-box{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--red)}
.success-box{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:20px 22px;margin-bottom:20px;text-align:center}
.success-box i{font-size:28px;color:var(--green);margin-bottom:10px;display:block}
.success-box p{font-size:14px;color:rgba(245,245,240,.85);line-height:1.6}
/* SIDEBAR CARDS */
.contact-info-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px}
.ci-head{padding:16px 20px;border-bottom:1px solid var(--border);font-family:var(--font-head);font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;color:var(--accent)}
.ci-body{padding:18px 20px}
.ci-item{display:flex;align-items:flex-start;gap:14px;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.ci-item:last-child{border-bottom:none}
.ci-icon{width:36px;height:36px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.15);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--accent)}
.ci-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:3px}
.ci-val{font-size:13px;color:var(--white);font-weight:500}
.ci-val a{color:var(--white);transition:color .2s}
.ci-val a:hover{color:var(--accent)}
/* SOCIAL LINKS */
.social-row{display:flex;gap:10px;flex-wrap:wrap}
.social-btn{display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:600;background:rgba(255,255,255,.04);border:1px solid var(--border);transition:all .2s}
.social-btn:hover{border-color:rgba(232,184,75,.3);background:rgba(232,184,75,.06);color:var(--accent)}
/* FAQ */
.faq-item{border-bottom:1px solid var(--border);padding:14px 0}
.faq-item:last-child{border-bottom:none}
.faq-q{font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;user-select:none}
.faq-q i{transition:transform .2s;flex-shrink:0;color:var(--accent)}
.faq-a{font-size:13px;color:var(--muted);line-height:1.7;max-height:0;overflow:hidden;transition:max-height .3s,padding .3s}
.faq-item.open .faq-q i{transform:rotate(180deg)}
.faq-item.open .faq-a{max-height:200px;padding-top:8px}
/* FOOTER */
footer{border-top:1px solid var(--border);padding:32px 0;margin-top:0}
.footer-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-size:13px;color:var(--muted)}
.footer-links a{color:var(--muted);margin-left:18px;transition:color .2s}
.footer-links a:hover{color:var(--accent)}
/* MOBILE NAV */
.mobile-nav{display:none;position:fixed;inset:0;z-index:300;background:rgba(10,10,11,.98);backdrop-filter:blur(20px);flex-direction:column;padding:24px 20px;overflow-y:auto}
.mobile-nav.open{display:flex}
.mobile-nav a{display:flex;align-items:center;gap:12px;padding:12px 0;font-size:15px;color:var(--muted);border-bottom:1px solid var(--border);transition:color .2s}
.mobile-nav a:hover{color:var(--white)}
.mobile-nav-close{background:none;border:none;color:var(--white);font-size:22px;cursor:pointer;margin-bottom:20px;padding:0}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="container">
        <div>
            <a href="tel:<?= e($sitePhone) ?>"><i class="fas fa-phone"></i> <?= e($sitePhone) ?></a>
            <a href="mailto:<?= e($siteEmail) ?>"><i class="fas fa-envelope"></i> <?= e($siteEmail) ?></a>
            <a href="#"><i class="fas fa-map-marker-alt"></i> <?= e($siteCity) ?>, Pakistan</a>
        </div>
        <div>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="dealer-register.php">Become a Dealer</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo">
            <span class="logo-car"><?= substr($siteName,0,3) ?></span><span><?= substr($siteName,3) ?></span><div class="logo-dot"></div>
        </a>
        <div class="nav-links">
            <a href="listings.php">Browse Cars</a>
            <a href="listings.php?condition=new">New Cars</a>
            <a href="listings.php?seller=dealer">Dealers</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php">Loan Calc</a>
            <a href="contact.php" class="active">Contact</a>
        </div>
        <div class="nav-right">
            <?php if ($navUser): ?>
            <a href="messages.php" style="position:relative;width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.05);border-radius:50%;color:var(--muted)">
                <i class="fas fa-comment-dots"></i>
                <?php if ($totalUnread > 0): ?><span style="position:absolute;top:-2px;right:-2px;width:16px;height:16px;background:var(--accent);border-radius:50%;font-size:9px;font-weight:700;color:#0a0a0b;display:flex;align-items:center;justify-content:center"><?= $totalUnread ?></span><?php endif; ?>
            </a>
            <a href="profile.php" class="btn btn-outline" style="padding:6px 14px;font-size:12px"><i class="fas fa-user"></i> <?= e(explode(' ',$navUser['name'])[0]) ?></a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <a href="post-listing.php" class="btn btn-accent"><i class="fas fa-plus"></i> Sell Car</a>
            <?php endif; ?>
            <div class="hamburger" onclick="document.getElementById('mobNav').classList.add('open')">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>
</nav>

<!-- MOBILE NAV -->
<div class="mobile-nav" id="mobNav">
    <button class="mobile-nav-close" onclick="document.getElementById('mobNav').classList.remove('open')"><i class="fas fa-times"></i></button>
    <a href="listings.php"><i class="fas fa-search"></i> Browse Cars</a>
    <a href="listings.php?seller=dealer"><i class="fas fa-store"></i> Dealers</a>
    <a href="compare.php"><i class="fas fa-balance-scale"></i> Compare</a>
    <a href="loan-calculator.php"><i class="fas fa-calculator"></i> Loan Calculator</a>
    <?php if ($navUser): ?>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="messages.php"><i class="fas fa-comment"></i> Messages</a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="logout.php" style="color:var(--red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    <?php else: ?>
    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
    <?php endif; ?>
</div>

<!-- BREADCRUMB -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Home</a><span>/</span> Contact Us
    </div>
</div>

<!-- HERO -->
<div class="page-hero">
    <div class="container">
        <div class="section-tag"><i class="fas fa-headset"></i> We're Here to Help</div>
        <h1>Get in <span>Touch</span></h1>
        <p>Have a question about buying, selling, or listing a car? Our team is here Monday to Saturday, 9am – 6pm PKT.</p>
    </div>
</div>

<!-- MAIN -->
<div class="container">
    <div class="contact-grid">

        <!-- CONTACT FORM -->
        <div class="form-card">
            <div class="form-card-title">Send Us a Message</div>
            <div class="form-card-sub">We'll respond to your inquiry within 24 hours on business days.</div>

            <?php if ($success): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <p><?= e($success) ?></p>
            </div>
            <?php else: ?>

            <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?><div><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= CSRF::field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" value="<?= e($_POST['name'] ?? ($navUser['name'] ?? '')) ?>" placeholder="e.g. Ahmed Raza" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input class="form-input" type="email" name="email" value="<?= e($_POST['email'] ?? ($navUser['email'] ?? '')) ?>" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input class="form-input" type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+92 300 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <select class="form-select" name="subject">
                            <?php
                            $subjects = ['General Inquiry','Buying a Car','Selling / Listing','Dealer Partnership','Technical Support','Report a Problem','Other'];
                            $sel = $_POST['subject'] ?? '';
                            foreach ($subjects as $s): ?>
                            <option value="<?= e($s) ?>" <?= $sel===$s?'selected':'' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea class="form-textarea" name="message" placeholder="Tell us how we can help you…" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-accent btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <aside>
            <!-- Contact Details -->
            <div class="contact-info-card">
                <div class="ci-head"><i class="fas fa-address-card"></i> Contact Details</div>
                <div class="ci-body">
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-phone"></i></div>
                        <div>
                            <div class="ci-label">Phone</div>
                            <div class="ci-val"><a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a></div>
                        </div>
                    </div>
                    <?php if ($waNumber): ?>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fab fa-whatsapp"></i></div>
                        <div>
                            <div class="ci-label">WhatsApp</div>
                            <div class="ci-val">
                                <a href="https://wa.me/<?= e($waNumber) ?>?text=Hello%20<?= urlencode($siteName) ?>%2C%20I%20need%20help" target="_blank">
                                    Chat on WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div class="ci-label">Email</div>
                            <div class="ci-val"><a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></div>
                        </div>
                    </div>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <div class="ci-label">Location</div>
                            <div class="ci-val"><?= e($siteCity) ?>, Pakistan</div>
                        </div>
                    </div>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-clock"></i></div>
                        <div>
                            <div class="ci-label">Business Hours</div>
                            <div class="ci-val">Mon – Sat, 9:00am – 6:00pm PKT</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media (only shows if set) -->
            <?php $hasSocial = $fb || $ig || $tw || $wa || $li; ?>
            <?php if ($hasSocial): ?>
            <div class="contact-info-card">
                <div class="ci-head"><i class="fas fa-share-alt"></i> Follow Us</div>
                <div class="ci-body">
                    <div class="social-row">
                        <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener" class="social-btn"><i class="fab fa-facebook-f" style="color:#1877f2"></i> Facebook</a><?php endif; ?>
                        <?php if ($ig): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener" class="social-btn"><i class="fab fa-instagram" style="color:#e1306c"></i> Instagram</a><?php endif; ?>
                        <?php if ($tw): ?><a href="<?= e($tw) ?>" target="_blank" rel="noopener" class="social-btn"><i class="fab fa-x-twitter"></i> X</a><?php endif; ?>
                        <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="social-btn"><i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp</a><?php endif; ?>
                        <?php if ($li): ?><a href="<?= e($li) ?>" target="_blank" rel="noopener" class="social-btn"><i class="fab fa-linkedin-in" style="color:#0a66c2"></i> LinkedIn</a><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- FAQ -->
            <div class="contact-info-card">
                <div class="ci-head"><i class="fas fa-question-circle"></i> Quick Answers</div>
                <div class="ci-body">
                    <?php
                    $faqs = [
                        ['How do I list a car for sale?', 'Click "Sell Car" or register as a Seller/Dealer. Free listings are available for private sellers.'],
                        ['How do I contact a seller?', 'Click the phone/WhatsApp button on any listing. You can also message through our platform.'],
                        ['Is CarSoko free to use?', 'Yes — browsing is always free. Private sellers get a free listing allowance. Dealers can choose featured plans.'],
                        ['How do I become a verified dealer?', 'Register an account and go to your Profile to upgrade your role to Dealer and complete verification.'],
                        ['How long does a listing stay active?', 'Listings remain active until sold, or for 90 days — whichever comes first. You can renew anytime.'],
                    ];
                    foreach ($faqs as $i => [$q, $a]):
                    ?>
                    <div class="faq-item" id="faq-<?= $i ?>">
                        <div class="faq-q" onclick="toggleFaq(<?= $i ?>)">
                            <?= e($q) ?>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-a"><?= e($a) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </aside>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="container">
        <div class="footer-inner">
            <div>&copy; <?= date('Y') ?> <?= e($siteName) ?> Pakistan. All rights reserved.</div>
            <div class="footer-links">
                <a href="listings.php">Browse Cars</a>
                <a href="about.php">About</a>
                <a href="terms.php">Terms</a>
                <a href="privacy.php">Privacy</a>
            </div>
        </div>
    </div>
</footer>

<!-- WhatsApp Float -->
<?php if ($waNumber): ?>
<a href="https://wa.me/<?= e($waNumber) ?>?text=Hello%20<?= urlencode($siteName) ?>" class="wa-float" target="_blank" rel="noopener"
   style="position:fixed;bottom:28px;right:28px;width:52px;height:52px;background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;box-shadow:0 4px 20px rgba(37,211,102,.4);z-index:100;transition:transform .2s"
   onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
    <i class="fab fa-whatsapp"></i>
</a>
<?php endif; ?>

<script>
function toggleFaq(idx) {
    const item = document.getElementById('faq-' + idx);
    item.classList.toggle('open');
}
</script>
</body>
</html>