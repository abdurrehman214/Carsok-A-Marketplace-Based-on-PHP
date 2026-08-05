<?php
// ============================================================
//  CarSoko Pakistan — terms.php  |  Terms of Use
// ============================================================
require_once 'connection.php';

$siteName  = setting('site_name', 'CarSoko');
$siteEmail = setting('site_email', 'legal@carsoko.pk');
$sitePhone = setting('site_phone', '+92 300 000 0000');
$siteCity  = setting('site_city', 'Karachi');
$lastUpdated = 'May 15, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Terms of Use for <?= e($siteName) ?> Pakistan — Pakistan's leading car marketplace.">
<title>Terms of Use | <?= e($siteName) ?> Pakistan</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --black:#0a0a0b;--dark:#111114;--card-bg:#18181c;
  --border:rgba(255,255,255,.08);--white:#f5f5f0;--muted:#888896;
  --accent:#e8b84b;--accent2:#ff6b35;
  --gradient:linear-gradient(135deg,#e8b84b,#ff6b35);
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.7}
a{color:var(--accent);text-decoration:none;transition:opacity .2s}
a:hover{opacity:.8}
img{max-width:100%;display:block}

.container{max-width:900px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .inner{max-width:1180px;margin:0 auto;padding:0 20px;display:flex;align-items:center;height:64px;gap:20px}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center;gap:2px;color:var(--white);text-decoration:none}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.nav-back{margin-left:auto;display:flex;align-items:center;gap:6px;color:var(--muted);font-size:13px;font-weight:500;transition:color .2s;text-decoration:none}
.nav-back:hover{color:var(--accent)}

/* PAGE HEADER */
.page-header{background:linear-gradient(135deg,rgba(232,184,75,.07) 0%,rgba(255,107,53,.04) 100%);border-bottom:1px solid var(--border);padding:52px 0 44px;text-align:center}
.header-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.2);color:var(--accent);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:6px 14px;border-radius:50px;margin-bottom:20px}
.page-title{font-family:var(--font-head);font-size:clamp(28px,5vw,50px);font-weight:800;line-height:1.05;margin-bottom:16px}
.page-title span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-meta{font-size:13px;color:var(--muted)}
.page-meta strong{color:rgba(245,245,240,.6)}

/* MAIN LAYOUT */
.main-layout{display:grid;grid-template-columns:220px 1fr;gap:48px;padding:52px 0 80px}

/* TABLE OF CONTENTS */
.toc{position:sticky;top:80px;align-self:start}
.toc-title{font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:14px}
.toc-list{list-style:none;display:flex;flex-direction:column;gap:2px}
.toc-list a{font-size:12px;color:var(--muted);padding:6px 10px;border-radius:6px;display:block;transition:all .2s;border-left:2px solid transparent}
.toc-list a:hover{color:var(--white);background:rgba(255,255,255,.04);border-left-color:rgba(232,184,75,.4)}
.toc-list a.active{color:var(--accent);background:rgba(232,184,75,.07);border-left-color:var(--accent)}

/* ARTICLE */
.article section{margin-bottom:44px;padding-bottom:44px;border-bottom:1px solid var(--border)}
.article section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}

.article h2{
  font-family:var(--font-head);font-size:clamp(17px,2.5vw,22px);font-weight:800;
  color:var(--white);margin-bottom:16px;
  display:flex;align-items:center;gap:10px;padding-top:4px;
}
.article h2 i{color:var(--accent);font-size:16px;width:22px;text-align:center;flex-shrink:0}
.article h2 .sec-num{font-size:11px;font-weight:700;color:var(--muted);font-family:var(--font-body);letter-spacing:.06em;background:rgba(255,255,255,.06);padding:2px 8px;border-radius:4px}

.article p{color:rgba(245,245,240,.78);margin-bottom:14px;font-size:14.5px}
.article p:last-child{margin-bottom:0}

.article ul,.article ol{padding-left:20px;margin-bottom:14px;color:rgba(245,245,240,.78);font-size:14.5px}
.article li{margin-bottom:8px;line-height:1.65}
.article li::marker{color:var(--accent)}

.highlight-box{background:rgba(232,184,75,.07);border:1px solid rgba(232,184,75,.18);border-left:3px solid var(--accent);border-radius:0 var(--radius) var(--radius) 0;padding:16px 18px;margin:16px 0;font-size:14px;color:rgba(245,245,240,.85)}
.highlight-box strong{color:var(--accent)}

.warn-box{background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.18);border-left:3px solid #ef4444;border-radius:0 var(--radius) var(--radius) 0;padding:16px 18px;margin:16px 0;font-size:14px;color:rgba(245,245,240,.85)}
.warn-box strong{color:#ef4444}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0}
.info-cell{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px}
.info-cell .label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:4px}
.info-cell .value{font-size:13px;color:var(--white);font-weight:500}

/* CONTACT CALLOUT */
.contact-callout{background:linear-gradient(135deg,rgba(232,184,75,.1),rgba(255,107,53,.06));border:1px solid rgba(232,184,75,.2);border-radius:16px;padding:28px 32px;text-align:center;margin-top:20px}
.contact-callout h3{font-family:var(--font-head);font-size:18px;font-weight:800;margin-bottom:8px}
.contact-callout p{font-size:14px;color:var(--muted);margin-bottom:18px}
.contact-callout a.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:var(--gradient);color:#0a0a0b;font-weight:700;border-radius:50px;font-size:13px;font-family:var(--font-body);transition:all .25s;text-decoration:none}
.contact-callout a.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,184,75,.4);opacity:1}

/* FOOTER */
.page-footer{background:var(--dark);border-top:1px solid var(--border);padding:28px 0;font-size:13px;color:var(--muted);text-align:center}
.page-footer a{color:var(--muted);transition:color .2s}
.page-footer a:hover{color:var(--accent)}

@media(max-width:800px){
  .main-layout{grid-template-columns:1fr}
  .toc{display:none}
}
@media(max-width:500px){
  .info-grid{grid-template-columns:1fr}
  .contact-callout{padding:22px 18px}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="inner">
    <a href="index.php" class="logo">
      <span><?= substr($siteName,0,3) ?></span><span><?= substr($siteName,3) ?></span><div class="logo-dot"></div>
    </a>
    <a href="javascript:history.back()" class="nav-back"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="container">
    <div class="header-tag"><i class="fas fa-file-contract"></i> Legal</div>
    <h1 class="page-title">Terms of <span>Use</span></h1>
    <p class="page-meta">
      <strong>Effective:</strong> <?= $lastUpdated ?> &nbsp;&middot;&nbsp;
      <strong>Jurisdiction:</strong> Pakistan &nbsp;&middot;&nbsp;
      <strong>Platform:</strong> <?= e($siteName) ?>.pk
    </p>
  </div>
</div>

<!-- MAIN -->
<div class="container">
  <div class="main-layout">

    <!-- TABLE OF CONTENTS -->
    <nav class="toc" aria-label="Table of contents">
      <div class="toc-title">Contents</div>
      <ul class="toc-list" id="tocList">
        <li><a href="#acceptance">1. Acceptance</a></li>
        <li><a href="#about">2. About <?= e($siteName) ?></a></li>
        <li><a href="#eligibility">3. Eligibility</a></li>
        <li><a href="#accounts">4. User Accounts</a></li>
        <li><a href="#listings">5. Listings & Content</a></li>
        <li><a href="#prohibited">6. Prohibited Conduct</a></li>
        <li><a href="#transactions">7. Transactions</a></li>
        <li><a href="#fees">8. Fees & Payments</a></li>
        <li><a href="#intellectual">9. Intellectual Property</a></li>
        <li><a href="#privacy">10. Privacy</a></li>
        <li><a href="#disclaimers">11. Disclaimers</a></li>
        <li><a href="#liability">12. Limitation of Liability</a></li>
        <li><a href="#indemnification">13. Indemnification</a></li>
        <li><a href="#termination">14. Termination</a></li>
        <li><a href="#governing">15. Governing Law</a></li>
        <li><a href="#changes">16. Changes to Terms</a></li>
        <li><a href="#contact">17. Contact Us</a></li>
      </ul>
    </nav>

    <!-- ARTICLE -->
    <article class="article">

      <div class="highlight-box" style="margin-bottom:32px">
        <strong>Please read these Terms of Use carefully.</strong> By accessing or using <?= e($siteName) ?> Pakistan, you agree to be legally bound by these terms. If you do not agree, please do not use our platform.
      </div>

      <!-- 1. Acceptance -->
      <section id="acceptance">
        <h2><i class="fas fa-handshake"></i> <span class="sec-num">01</span> Acceptance of Terms</h2>
        <p>These Terms of Use ("Terms") constitute a legally binding agreement between you ("User," "you," or "your") and <?= e($siteName) ?> Pakistan ("<?= e($siteName) ?>," "we," "us," or "our"), governing your access to and use of the <?= e($siteName) ?> website, mobile application, and related services (collectively, the "Platform").</p>
        <p>By creating an account, listing a vehicle, sending a message, or otherwise using any part of our Platform, you confirm that you have read, understood, and agree to be bound by these Terms, our <a href="privacy.php">Privacy Policy</a>, and any other policies we publish from time to time.</p>
        <p>If you are using the Platform on behalf of a business entity, you represent that you have the authority to bind that entity to these Terms, and "you" refers to both you and that entity.</p>
      </section>

      <!-- 2. About -->
      <section id="about">
        <h2><i class="fas fa-car"></i> <span class="sec-num">02</span> About <?= e($siteName) ?></h2>
        <p><?= e($siteName) ?> Pakistan is an online marketplace that connects buyers and sellers of new and used vehicles across Pakistan. We provide the technology platform and tools that enable users to list vehicles, search listings, compare cars, communicate with other users, and facilitate transactions. <strong><?= e($siteName) ?> is not a party to any transaction between buyers and sellers</strong> and does not buy, sell, or hold inventory of any vehicles.</p>
        <div class="info-grid">
          <div class="info-cell">
            <div class="label">Platform Type</div>
            <div class="value">Online Vehicle Marketplace</div>
          </div>
          <div class="info-cell">
            <div class="label">Operating Region</div>
            <div class="value">Pakistan (Nationwide)</div>
          </div>
          <div class="info-cell">
            <div class="label">Headquartered</div>
            <div class="value"><?= e($siteCity) ?>, Pakistan</div>
          </div>
          <div class="info-cell">
            <div class="label">Service Model</div>
            <div class="value">Peer-to-Peer & Dealer Listings</div>
          </div>
        </div>
      </section>

      <!-- 3. Eligibility -->
      <section id="eligibility">
        <h2><i class="fas fa-user-check"></i> <span class="sec-num">03</span> Eligibility</h2>
        <p>To use <?= e($siteName) ?>, you must:</p>
        <ul>
          <li>Be at least <strong>18 years of age</strong> or have reached the age of majority in your province or territory;</li>
          <li>Be a resident of, or conduct vehicle transactions within, the <strong>Islamic Republic of Pakistan</strong>;</li>
          <li>Have the legal capacity to enter into a binding contract;</li>
          <li>Not be prohibited from using our services under applicable Pakistani law;</li>
          <li>Provide accurate and truthful information at registration and at all times thereafter.</li>
        </ul>
        <p>We reserve the right to verify your identity and eligibility at any time. We may refuse access or terminate accounts that do not meet these requirements.</p>
      </section>

      <!-- 4. Accounts -->
      <section id="accounts">
        <h2><i class="fas fa-user-circle"></i> <span class="sec-num">04</span> User Accounts</h2>
        <p><strong>4.1 Registration.</strong> You may browse certain portions of the Platform without registering. To post listings, message sellers, or access full platform features, you must create an account by providing your name, a valid email address, and a password.</p>
        <p><strong>4.2 Account Types.</strong> We offer three account types:</p>
        <ul>
          <li><strong>Buyer:</strong> Browse, save, and inquire about vehicle listings. Buyers may upgrade to Seller or Dealer accounts at any time through their account settings.</li>
          <li><strong>Private Seller:</strong> List vehicles for sale as an individual. Subject to fair-use listing limits.</li>
          <li><strong>Dealer:</strong> Licensed businesses may register as dealers. Dealer accounts require a valid business name and are subject to additional verification and higher listing volumes.</li>
        </ul>
        <p><strong>4.3 Account Security.</strong> You are solely responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. You agree to notify us immediately at <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a> if you suspect any unauthorized access to your account.</p>
        <p><strong>4.4 Accurate Information.</strong> You agree to provide and maintain accurate, current, and complete information. We may suspend or terminate accounts associated with false, inaccurate, or misleading information.</p>
        <p><strong>4.5 One Account Per User.</strong> Each individual or business entity may maintain only one active account. Operating multiple accounts to circumvent restrictions or gain unfair advantage is strictly prohibited.</p>
      </section>

      <!-- 5. Listings -->
      <section id="listings">
        <h2><i class="fas fa-list-alt"></i> <span class="sec-num">05</span> Listings &amp; Content</h2>
        <p><strong>5.1 Listing Standards.</strong> All vehicle listings must accurately represent the vehicle being sold. You agree that your listings will:</p>
        <ul>
          <li>Describe a real vehicle that you own or have legal authority to sell;</li>
          <li>Contain accurate information regarding make, model, year, mileage, condition, price, and location;</li>
          <li>Include clear, current, and unaltered photographs of the actual vehicle;</li>
          <li>Disclose any known material defects, accident history, or outstanding finance;</li>
          <li>Comply with all applicable Pakistani laws regarding vehicle sales.</li>
        </ul>
        <p><strong>5.2 Prohibited Listings.</strong> The following are strictly prohibited on the Platform:</p>
        <ul>
          <li>Stolen, cloned, or fraudulently obtained vehicles;</li>
          <li>Vehicles with tampered chassis numbers (VINs) or odometers;</li>
          <li>Vehicles with undisclosed outstanding loans or encumbrances;</li>
          <li>Duplicate or "ghost" listings for the same vehicle;</li>
          <li>Non-vehicle items (unless expressly permitted by <?= e($siteName) ?>);</li>
          <li>Listings containing misleading, fraudulent, or defamatory content.</li>
        </ul>
        <p><strong>5.3 Content License.</strong> By submitting listings, photographs, reviews, or other content ("User Content"), you grant <?= e($siteName) ?> a non-exclusive, royalty-free, worldwide, sub-licensable licence to use, display, reproduce, modify, and distribute that content for the purposes of operating and promoting the Platform.</p>
        <p><strong>5.4 Content Moderation.</strong> We reserve the right — but not the obligation — to review, edit, refuse, or remove any listing or User Content that we believe, in our sole discretion, violates these Terms or is otherwise objectionable.</p>
        <div class="highlight-box">
          <strong>Seller's Responsibility:</strong> You are solely responsible for the accuracy of your listing, the legality of the vehicle's title, and the completion of any transaction. <?= e($siteName) ?> does not independently verify listing content.
        </div>
      </section>

      <!-- 6. Prohibited Conduct -->
      <section id="prohibited">
        <h2><i class="fas fa-ban"></i> <span class="sec-num">06</span> Prohibited Conduct</h2>
        <p>You agree not to engage in any of the following conduct while using the Platform:</p>
        <ul>
          <li><strong>Fraud &amp; Misrepresentation:</strong> Posting false listings, impersonating other users or businesses, or providing any false information;</li>
          <li><strong>Scams:</strong> Requesting advance payments through unsecured methods, conducting advance-fee fraud ("419 scams"), or otherwise defrauding other users;</li>
          <li><strong>Spam:</strong> Sending unsolicited messages, repeated identical inquiries, or bulk communications;</li>
          <li><strong>Harassment:</strong> Harassing, threatening, abusing, or discriminating against other users based on any protected characteristic;</li>
          <li><strong>System Abuse:</strong> Scraping, crawling, or data-mining the Platform; circumventing access controls; or interfering with Platform infrastructure;</li>
          <li><strong>Intellectual Property Infringement:</strong> Uploading content that infringes the copyright, trademark, or other intellectual property rights of any person;</li>
          <li><strong>Illegal Activity:</strong> Using the Platform to facilitate any activity prohibited under the laws of Pakistan, including the Pakistan Penal Code, Prevention of Electronic Crimes Act 2016 (PECA), and applicable provincial legislation;</li>
          <li><strong>Off-Platform Transactions:</strong> Circumventing Platform communications to avoid fees or protections.</li>
        </ul>
        <div class="warn-box">
          <strong>Warning:</strong> Violations of these conduct rules may result in immediate account termination, removal of all listings, and referral to relevant law enforcement authorities in Pakistan.
        </div>
      </section>

      <!-- 7. Transactions -->
      <section id="transactions">
        <h2><i class="fas fa-exchange-alt"></i> <span class="sec-num">07</span> Transactions &amp; Buyer Safety</h2>
        <p><strong>7.1 Platform is a Facilitator Only.</strong> <?= e($siteName) ?> facilitates introductions between buyers and sellers but is <strong>not a party to, and takes no responsibility for,</strong> any transaction between users. All sales are conducted directly between buyer and seller.</p>
        <p><strong>7.2 Buyer Recommendations.</strong> To protect yourself when buying a vehicle, we strongly recommend:</p>
        <ul>
          <li>Inspect the vehicle in person before making any payment;</li>
          <li>Verify the vehicle's ownership documents (registration book / log book) with the relevant provincial motor vehicle authority;</li>
          <li>Confirm that there is no outstanding bank financing or leasing on the vehicle;</li>
          <li>Conduct a test drive in a public location during daylight hours;</li>
          <li>Never transfer funds via informal methods (e.g., Easypaisa, cash) before taking possession of the vehicle;</li>
          <li>Consider engaging a professional vehicle inspector or mechanic.</li>
        </ul>
        <p><strong>7.3 No Guarantee of Accuracy.</strong> <?= e($siteName) ?> does not guarantee the accuracy of any listing information, the quality or condition of any vehicle, or the title status of any vehicle. Buyers conduct all due diligence at their own risk.</p>
        <p><strong>7.4 Dispute Resolution.</strong> Any dispute arising from a vehicle transaction is solely between the buyer and seller. While we may assist by providing communication records at our sole discretion, we are under no obligation to mediate or resolve disputes.</p>
      </section>

      <!-- 8. Fees -->
      <section id="fees">
        <h2><i class="fas fa-rupee-sign"></i> <span class="sec-num">08</span> Fees &amp; Payments</h2>
        <p><strong>8.1 Free Listings.</strong> Basic vehicle listings on <?= e($siteName) ?> are free of charge for private sellers, subject to fair-use limits.</p>
        <p><strong>8.2 Premium Services.</strong> We offer optional paid services including Featured Listings, Dealer Subscriptions, and enhanced visibility packages. Fees for these services are as published on the Platform and are subject to change with reasonable notice.</p>
        <p><strong>8.3 Payment.</strong> All fees are quoted and payable in Pakistani Rupees (PKR). We accept payment through the methods listed at checkout, which may include credit/debit cards, JazzCash, EasyPaisa, and bank transfer.</p>
        <p><strong>8.4 Non-Refundable.</strong> Except where required by applicable law or expressly stated otherwise, all fees paid for premium services are non-refundable. If a listing is removed due to a violation of these Terms, no refund will be issued.</p>
        <p><strong>8.5 Taxes.</strong> You are responsible for any applicable taxes, duties, or levies arising from your use of the Platform, including any taxes on vehicle transactions.</p>
      </section>

      <!-- 9. IP -->
      <section id="intellectual">
        <h2><i class="fas fa-copyright"></i> <span class="sec-num">09</span> Intellectual Property</h2>
        <p><strong>9.1 <?= e($siteName) ?> IP.</strong> All content, software, design, trademarks, logos, and trade names on the Platform — other than User Content — are the exclusive property of <?= e($siteName) ?> Pakistan or our licensors and are protected by Pakistani and international intellectual property laws.</p>
        <p><strong>9.2 Permitted Use.</strong> We grant you a limited, non-exclusive, non-transferable, revocable licence to access and use the Platform solely for personal, non-commercial purposes in accordance with these Terms.</p>
        <p><strong>9.3 Restrictions.</strong> You may not copy, reproduce, distribute, publicly display, modify, create derivative works of, or commercially exploit any Platform content without our prior written consent.</p>
        <p><strong>9.4 Your Content.</strong> You retain ownership of User Content you submit. However, you warrant that you own or have the necessary rights to all User Content you post, and that it does not infringe any third-party rights.</p>
      </section>

      <!-- 10. Privacy -->
      <section id="privacy">
        <h2><i class="fas fa-shield-alt"></i> <span class="sec-num">10</span> Privacy</h2>
        <p>Your privacy is important to us. Our collection, use, and disclosure of personal information is governed by our <a href="privacy.php">Privacy Policy</a>, which is incorporated into and forms part of these Terms. By using the Platform, you consent to our privacy practices as described in that policy.</p>
        <p>We comply with the Personal Data Protection Bill (PDPB) of Pakistan and take reasonable measures to protect the personal data you provide to us.</p>
      </section>

      <!-- 11. Disclaimers -->
      <section id="disclaimers">
        <h2><i class="fas fa-exclamation-triangle"></i> <span class="sec-num">11</span> Disclaimers</h2>
        <p>THE PLATFORM AND ALL CONTENT, LISTINGS, AND SERVICES ARE PROVIDED ON AN <strong>"AS IS"</strong> AND <strong>"AS AVAILABLE"</strong> BASIS WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO:</p>
        <ul>
          <li>Warranties of merchantability, fitness for a particular purpose, or non-infringement;</li>
          <li>Warranties that the Platform will be uninterrupted, error-free, or secure;</li>
          <li>Warranties regarding the accuracy, completeness, or reliability of any listing, price, or vehicle description;</li>
          <li>Warranties regarding the identity, reputation, or conduct of any user.</li>
        </ul>
        <p><?= e($siteName) ?> does not endorse any vehicle, seller, or dealer listed on the Platform. The "Verified Dealer" badge reflects only that the entity has completed our registration process and does not constitute an endorsement of their vehicles or business practices.</p>
      </section>

      <!-- 12. Liability -->
      <section id="liability">
        <h2><i class="fas fa-balance-scale"></i> <span class="sec-num">12</span> Limitation of Liability</h2>
        <p>TO THE FULLEST EXTENT PERMITTED BY APPLICABLE PAKISTANI LAW, <?= strtoupper($siteName) ?> PAKISTAN, ITS DIRECTORS, OFFICERS, EMPLOYEES, AGENTS, AND PARTNERS SHALL NOT BE LIABLE FOR:</p>
        <ul>
          <li>Any indirect, incidental, special, consequential, or punitive damages;</li>
          <li>Any loss of profits, revenue, data, goodwill, or business opportunity;</li>
          <li>Any vehicle defects, fraudulent transactions, or disputes between users;</li>
          <li>Any reliance on information contained in a listing;</li>
          <li>Any unauthorized access to or alteration of your account or data;</li>
          <li>Any Platform downtime, errors, or service interruptions.</li>
        </ul>
        <p>Where liability cannot be excluded under Pakistani law, our total aggregate liability to you for any claim arising from these Terms or your use of the Platform shall not exceed the greater of: (a) PKR 5,000, or (b) the total fees paid by you to <?= e($siteName) ?> in the 3 months preceding the event giving rise to the claim.</p>
      </section>

      <!-- 13. Indemnification -->
      <section id="indemnification">
        <h2><i class="fas fa-gavel"></i> <span class="sec-num">13</span> Indemnification</h2>
        <p>You agree to defend, indemnify, and hold harmless <?= e($siteName) ?> Pakistan and its directors, officers, employees, agents, and partners from and against any and all claims, damages, losses, liabilities, costs, and expenses (including reasonable legal fees) arising from or related to:</p>
        <ul>
          <li>Your use of, or inability to use, the Platform;</li>
          <li>Your User Content or vehicle listings;</li>
          <li>Any transaction you enter into with another user;</li>
          <li>Your breach of any representation, warranty, or obligation under these Terms;</li>
          <li>Your violation of any applicable law or the rights of any third party.</li>
        </ul>
      </section>

      <!-- 14. Termination -->
      <section id="termination">
        <h2><i class="fas fa-times-circle"></i> <span class="sec-num">14</span> Termination</h2>
        <p><strong>14.1 By You.</strong> You may delete your account at any time through your profile settings or by contacting us at <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a>. Deletion will remove your active listings.</p>
        <p><strong>14.2 By Us.</strong> We may suspend or permanently terminate your account, with or without notice, if we determine in our sole discretion that you have:</p>
        <ul>
          <li>Violated any provision of these Terms;</li>
          <li>Engaged in fraudulent, abusive, or illegal activity;</li>
          <li>Posed a risk to the safety or integrity of the Platform or its users;</li>
          <li>Provided false or misleading information.</li>
        </ul>
        <p><strong>14.3 Effect of Termination.</strong> Upon termination, your right to use the Platform immediately ceases. Provisions of these Terms that by their nature should survive termination — including Sections 9 (IP), 11 (Disclaimers), 12 (Liability), and 13 (Indemnification) — shall survive.</p>
      </section>

      <!-- 15. Governing Law -->
      <section id="governing">
        <h2><i class="fas fa-landmark"></i> <span class="sec-num">15</span> Governing Law &amp; Dispute Resolution</h2>
        <p><strong>15.1 Governing Law.</strong> These Terms are governed by and construed in accordance with the laws of the <strong>Islamic Republic of Pakistan</strong>, without regard to conflict of law principles.</p>
        <p><strong>15.2 Jurisdiction.</strong> Any legal action or proceeding arising out of or relating to these Terms or your use of the Platform shall be brought exclusively in the courts of <?= e($siteCity) ?>, Sindh, Pakistan, and you hereby consent to personal jurisdiction in such courts.</p>
        <p><strong>15.3 Informal Resolution.</strong> Before initiating any formal legal action, both parties agree to attempt good-faith resolution of any dispute by contacting us at <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a>. We will try to respond within 5 business days.</p>
      </section>

      <!-- 16. Changes -->
      <section id="changes">
        <h2><i class="fas fa-edit"></i> <span class="sec-num">16</span> Changes to These Terms</h2>
        <p>We may update these Terms from time to time to reflect changes in our practices, legal requirements, or Platform features. When we make material changes, we will:</p>
        <ul>
          <li>Update the "Effective" date at the top of this page;</li>
          <li>Display a notice on the Platform or send an email notification to registered users;</li>
          <li>Give you a reasonable opportunity to review the changes before they take effect.</li>
        </ul>
        <p>Your continued use of the Platform after the effective date of revised Terms constitutes your acceptance of the changes. If you do not agree to the updated Terms, you must stop using the Platform and may delete your account.</p>
      </section>

      <!-- 17. Contact -->
      <section id="contact">
        <h2><i class="fas fa-envelope"></i> <span class="sec-num">17</span> Contact Us</h2>
        <p>If you have any questions about these Terms, wish to report a violation, or need to make a legal inquiry, please contact us:</p>
        <div class="info-grid">
          <div class="info-cell">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></div>
          </div>
          <div class="info-cell">
            <div class="label">Phone</div>
            <div class="value"><?= e($sitePhone) ?></div>
          </div>
          <div class="info-cell">
            <div class="label">Office</div>
            <div class="value"><?= e($siteCity) ?>, Pakistan</div>
          </div>
          <div class="info-cell">
            <div class="label">Hours</div>
            <div class="value">Mon–Sat, 9 AM – 6 PM PKT</div>
          </div>
        </div>

        <div class="contact-callout">
          <h3>Still have questions?</h3>
          <p>Our support team is happy to help with any queries about our platform or these Terms.</p>
          <a href="mailto:<?= e($siteEmail) ?>" class="btn"><i class="fas fa-paper-plane"></i> Email Us</a>
        </div>
      </section>

    </article>
  </div><!-- /.main-layout -->
</div><!-- /.container -->

<!-- FOOTER -->
<footer class="page-footer">
  <div>&copy; <?= date('Y') ?> <?= e($siteName) ?> Pakistan. All rights reserved.</div>
  <div style="margin-top:8px;display:flex;gap:20px;justify-content:center;flex-wrap:wrap">
    <a href="index.php">Home</a>
    <a href="privacy.php">Privacy Policy</a>
    <a href="about.php">About Us</a>
  </div>
</footer>

<script>
// Active TOC highlighting
(function () {
  var sections = document.querySelectorAll('article section[id]');
  var links    = document.querySelectorAll('#tocList a');
  if (!sections.length || !links.length) return;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        links.forEach(function (l) { l.classList.remove('active'); });
        var active = document.querySelector('#tocList a[href="#' + entry.target.id + '"]');
        if (active) active.classList.add('active');
      }
    });
  }, { rootMargin: '-20% 0px -70% 0px' });

  sections.forEach(function (s) { observer.observe(s); });
}());
</script>
</body>
</html>
