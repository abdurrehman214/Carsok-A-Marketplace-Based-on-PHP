<?php
require_once 'connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – <?= setting('site_name','CarSoko') ?> Pakistan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --black: #000000; --dark: #0a0a0b; --card-bg: #111114;
            --border: rgba(255,255,255,0.08); --white: #ffffff;
            --muted: #a0a0a0; --accent: #e8b84b;
            --font-head: 'Bebas Neue', sans-serif; --font-body: 'Inter', sans-serif;
        }
        body { background: var(--black); color: var(--white); font-family: var(--font-body); margin: 0; line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 60px 24px; }
        h1, h2, h3 { font-family: var(--font-head); letter-spacing: 1px; color: var(--accent); }
        h1 { font-size: 48px; margin-bottom: 40px; border-bottom: 2px solid var(--accent); display: inline-block; }
        .content { font-size: 16px; color: #ccc; }
        p { margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 40px; color: var(--accent); text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        footer { border-top: 1px solid var(--border); padding: 40px 0; text-align: center; color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        <h1>Privacy Policy</h1>
        
        <div class="content">
            <p>Last Updated: <?= date('F d, Y') ?></p>
            
            <h2>1. Information We Collect</h2>
            <p>At CarSoko Pakistan, we collect information to provide better services to all our users. This includes:</p>
            <ul>
                <li>Personal identifiers (Name, Email, Phone Number) when you register.</li>
                <li>Vehicle information when you post a listing.</li>
                <li>Usage data (IP address, browser type, pages visited).</li>
            </ul>

            <h2>2. How We Use Information</h2>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Maintain and improve our services.</li>
                <li>Communicate with you regarding your listings or account.</li>
                <li>Prevent fraud and ensure the safety of our marketplace.</li>
            </ul>

            <h2>3. Information Sharing</h2>
            <p>We do not share your personal information with companies, organizations, or individuals outside of CarSoko except in the following cases:</p>
            <ul>
                <li>With your consent (e.g., when you contact a seller).</li>
                <li>For legal reasons (complying with Pakistani laws).</li>
            </ul>

            <h2>4. Data Security</h2>
            <p>We work hard to protect CarSoko and our users from unauthorized access to or unauthorized alteration, disclosure, or destruction of information we hold.</p>

            <h2>5. Contact Us</h2>
            <p>If you have any questions about this Privacy Policy, please contact us at:</p>
            <p>Email: <?= setting('site_email','info@carsoko.pk') ?><br>
               Phone: <?= setting('site_phone','+923282630997') ?></p>
        </div>
    </div>

    <footer>
        <div class="container" style="padding:0">
            <p>&copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
