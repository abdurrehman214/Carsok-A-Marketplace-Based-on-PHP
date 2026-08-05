<?php
require_once 'connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us – <?= setting('site_name','CarSoko') ?> Pakistan</title>
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
        .container { max-width: 1000px; margin: 0 auto; padding: 40px 24px; }
        h1, h2, h3 { font-family: var(--font-head); letter-spacing: 1px; color: var(--accent); }
        h1 { font-size: 64px; text-align: center; margin-bottom: 20px; }
        .hero { padding: 100px 0; text-align: center; background: linear-gradient(to bottom, #111, #000); }
        .content { font-size: 18px; color: #ccc; }
        .card { background: var(--card-bg); border: 1px solid var(--border); padding: 30px; border-radius: 16px; margin-bottom: 30px; }
        .accent-text { color: var(--accent); font-weight: 600; }
        footer { border-top: 1px solid var(--border); padding: 40px 0; text-align: center; color: var(--muted); font-size: 14px; }
        .back-link { display: inline-block; margin-bottom: 40px; color: var(--accent); text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="container">
            <h1>About CarSoko</h1>
            <p style="font-size: 20px; color: var(--muted);">Driving the Future of Car Buying in Pakistan</p>
        </div>
    </div>

    <div class="container content">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>

        <div class="card">
            <h2>Our Mission</h2>
            <p>CarSoko is Pakistan's premier digital automotive marketplace, designed to bring transparency, trust, and ease to the car buying and selling process. Whether you are looking for a fuel-efficient city car in Karachi, a rugged SUV for the northern areas, or a luxury sedan in Islamabad, CarSoko connects you with thousands of verified listings from both private sellers and professional dealers.</p>
        </div>

        <div class="card">
            <h2>Why Choose CarSoko?</h2>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 15px;"><i class="fas fa-check-circle accent-text"></i> <span class="accent-text">Verified Listings:</span> We strive to ensure every listing on our platform is genuine and accurate.</li>
                <li style="margin-bottom: 15px;"><i class="fas fa-check-circle accent-text"></i> <span class="accent-text">Wide Selection:</span> From budget-friendly Suzukis to premium German engineering, we have it all.</li>
                <li style="margin-bottom: 15px;"><i class="fas fa-check-circle accent-text"></i> <span class="accent-text">Regional Expertise:</span> Our platform is optimized for the Pakistani market, with regional search and local pricing.</li>
                <li style="margin-bottom: 15px;"><i class="fas fa-check-circle accent-text"></i> <span class="accent-text">Dealer Network:</span> We partner with the best dealerships across Lahore, Karachi, and Islamabad.</li>
            </ul>
        </div>

        <div class="card">
            <h2>Contact Us</h2>
            <p>Have questions or need support? Our team is here to help.</p>
            <p><i class="fas fa-envelope accent-text"></i> Email: <?= setting('site_email','info@carsoko.pk') ?></p>
            <p><i class="fas fa-phone accent-text"></i> Phone: <?= setting('site_phone','+923282630997') ?></p>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= setting('site_name','CarSoko') ?> Pakistan. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
