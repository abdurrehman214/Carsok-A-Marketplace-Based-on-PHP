<?php
//  CarSoko Pakistan — loan-calculator.php
//  Car Loan / Financing Calculator
require_once 'connection.php';

$pageTitle = 'Car Loan Calculator Pakistan 2026 | Monthly Repayment Estimator | CarSoko';
$metaDesc  = 'Calculate your monthly car loan repayments in Pakistan. Compare banks interest rates, see total cost of financing, and plan your car purchase budget.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="<?= $metaDesc ?>">
<meta name="keywords" content="car loan calculator Pakistan, auto loan Pakistan, monthly repayment calculator, car financing Pakistan 2026">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --black:    #000000;
    --dark:     #0a0a0b;
    --card-bg:  #111114;
    --border:   rgba(255,255,255,0.08);
    --white:    #ffffff;
    --muted:    #a0a0a0;
    --accent:   #e8b84b;
    --accent2:  #ff6b35;
    --green:    #22c55e;
    --red:      #ef4444;
    --blue:     #3b82f6;
    --gradient: linear-gradient(135deg,#e8b84b 0%,#ff6b35 100%);
    --font-head:'Bebas Neue', sans-serif;
    --font-body:'Inter', sans-serif;
    --radius:   14px;
    --radius-lg:24px;
}*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--black);color:var(--white);font-family:var(--font-body);font-size:15px;line-height:1.6}
a{color:inherit;text-decoration:none}
.container{max-width:1140px;margin:0 auto;padding:0 20px}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:200;background:rgba(10,10,11,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border)}
.navbar .container{display:flex;align-items:center;height:64px;gap:24px}
.logo{font-family:var(--font-head);font-size:22px;font-weight:800;display:flex;align-items:center}
.logo span:first-child{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo-dot{width:6px;height:6px;background:var(--gradient);border-radius:50%;margin-left:3px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.4)}}
.nav-links{display:flex;align-items:center;gap:2px;flex:1}
.nav-links a{font-size:13px;font-weight:500;color:var(--muted);padding:7px 12px;border-radius:8px;transition:all .2s}
.nav-links a:hover,.nav-links a.active{color:var(--white);background:rgba(255,255,255,.06)}
.nav-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .25s}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--white)}
.btn-accent{background:var(--gradient);color:#0a0a0b;font-weight:700}
.btn-accent:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(232,184,75,.4)}

/* PAGE LAYOUT */
.page-header{padding:48px 0 32px;text-align:center}
.page-header h1{font-family:var(--font-head);font-size:clamp(26px,4vw,42px);font-weight:800;margin-bottom:12px}
.page-header h1 span{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.page-header p{color:var(--muted);font-size:16px;max-width:520px;margin:0 auto}

.calc-layout{display:grid;grid-template-columns:1fr 380px;gap:28px;padding-bottom:60px;align-items:start}

/* CALCULATOR CARD */
.calc-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:28px}
.calc-card h2{font-family:var(--font-head);font-size:17px;font-weight:700;margin-bottom:24px;display:flex;align-items:center;gap:8px}
.calc-card h2 i{color:var(--accent)}

.form-group{margin-bottom:22px}
.form-label{display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}
.form-label-right{color:var(--accent);font-size:14px;font-weight:700;text-transform:none;letter-spacing:0}
.range-input{width:100%;height:5px;-webkit-appearance:none;background:rgba(255,255,255,.1);border-radius:5px;outline:none;cursor:pointer}
.range-input::-webkit-slider-thumb{-webkit-appearance:none;width:20px;height:20px;background:var(--gradient);border-radius:50%;cursor:pointer;box-shadow:0 0 0 3px rgba(232,184,75,.2)}
.range-input::-moz-range-thumb{width:20px;height:20px;background:var(--gradient);border-radius:50%;cursor:pointer;border:none}
.range-row{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-top:4px}

.num-input{background:rgba(0,0,0,.3);border:1px solid var(--border);color:var(--white);padding:11px 14px;border-radius:8px;font-size:15px;font-weight:600;width:100%;outline:none;transition:border-color .2s;font-family:var(--font-body)}
.num-input:focus{border-color:var(--accent)}

.lender-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.lender-tab{padding:8px 14px;border-radius:8px;border:1px solid var(--border);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;color:var(--muted);background:transparent}
.lender-tab:hover{border-color:rgba(232,184,75,.3);color:var(--white)}
.lender-tab.active{background:rgba(232,184,75,.12);border-color:rgba(232,184,75,.35);color:var(--accent)}

/* RESULTS */
.results-card{background:linear-gradient(135deg,rgba(232,184,75,.08),rgba(255,107,53,.06));border:1px solid rgba(232,184,75,.2);border-radius:var(--radius);padding:28px}
.results-card h2{font-family:var(--font-head);font-size:17px;font-weight:700;margin-bottom:24px;display:flex;align-items:center;gap:8px}
.results-card h2 i{color:var(--accent)}

.metric{margin-bottom:20px}
.metric-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:4px}
.metric-value{font-family:var(--font-head);font-size:28px;font-weight:800;line-height:1}
.metric-sub{font-size:12px;color:var(--muted);margin-top:3px}
.metric-accent{background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.metric-divider{border:none;border-top:1px solid var(--border);margin:20px 0}

/* Breakdown table */
.breakdown{border-top:1px solid var(--border);padding-top:20px;margin-top:4px}
.breakdown-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13px;border-bottom:1px dashed rgba(255,255,255,.06)}
.breakdown-row:last-child{border-bottom:none;font-weight:700;font-size:14px;color:var(--white)}
.breakdown-label{color:var(--muted)}
.breakdown-val{font-weight:600}

/* Amortization */
.amort-table{width:100%;border-collapse:collapse;margin-top:24px;font-size:13px}
.amort-table th{padding:10px 12px;text-align:left;background:var(--dark);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
.amort-table td{padding:10px 12px;border-bottom:1px solid var(--border)}
.amort-table tr:hover td{background:rgba(255,255,255,.02)}
.amort-table .principal-col{color:var(--green)}
.amort-table .interest-col{color:var(--red)}

/* Info boxes */
.info-boxes{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px}
.info-box{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:16px}
.info-box h4{font-size:13px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.info-box h4 i{color:var(--accent);font-size:12px}
.info-box p{font-size:12px;color:var(--muted);line-height:1.6}

/* SEO content */
.seo-section{margin:40px 0;padding:32px 0;border-top:1px solid var(--border)}
.seo-section h2{font-family:var(--font-head);font-size:20px;font-weight:700;margin-bottom:16px}
.seo-section h3{font-family:var(--font-head);font-size:16px;font-weight:600;margin:20px 0 8px;color:var(--accent)}
.seo-section p{color:var(--muted);font-size:14px;line-height:1.8;margin-bottom:12px}
.seo-section ul{color:var(--muted);font-size:14px;line-height:2;padding-left:20px}

@media(max-width:900px){
    .calc-layout{grid-template-columns:1fr}
    .info-boxes{grid-template-columns:1fr}
    .nav-links{display:none}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo"><span>Car</span><span style="color:var(--white)">Soko</span><div class="logo-dot"></div></a>
        <div class="nav-links">
            <a href="listings.php">Browse Cars</a>
            <a href="compare.php">Compare</a>
            <a href="loan-calculator.php" class="active">Loan Calc</a>
            <a href="blog.php">Blog</a>
        </div>
        <div class="nav-right">
            <?php if (Auth::check()): $u=Auth::user(); ?>
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-user"></i> <?= e(explode(' ',$u['name'])[0]) ?></a>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline"><i class="fas fa-user"></i> Sign In</a>
            <?php endif; ?>
            <a href="listings.php" class="btn btn-accent"><i class="fas fa-search"></i> Browse Cars</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1 style="text-transform:uppercase;letter-spacing:0.02em">Car Loan <span>Calculator Pakistan</span></h1>
        <p>Estimate your monthly repayments, total interest, and full cost of financing your next car in Pakistan.</p>
    </div>

    <div class="calc-layout">
        <!-- CALCULATOR INPUTS -->
        <div>
            <div class="calc-card">
                <h2><i class="fas fa-calculator"></i> Loan Details</h2>

                <div class="form-group">
                    <div class="form-label">Car Price (Rs.) <span class="form-label-right" id="priceDisplay">Rs. 2,000,000</span></div>
                    <input type="range" class="range-input" id="carPrice" min="200000" max="20000000" step="100000" value="2000000" oninput="calcLoan()">
                    <div class="range-row"><span>Rs. 200K</span><span>Rs. 20M</span></div>
                </div>

                <div class="form-group">
                    <div class="form-label">Down Payment <span class="form-label-right" id="downDisplay">Rs. 400,000 (20%)</span></div>
                    <input type="range" class="range-input" id="downPayment" min="0" max="90" step="5" value="20" oninput="calcLoan()">
                    <div class="range-row"><span>0%</span><span>90%</span></div>
                </div>

                <div class="form-group">
                    <div class="form-label">Loan Period <span class="form-label-right" id="termDisplay">48 months (4 years)</span></div>
                    <input type="range" class="range-input" id="loanTerm" min="12" max="84" step="12" value="48" oninput="calcLoan()">
                    <div class="range-row"><span>1 year</span><span>7 years</span></div>
                </div>

                <div class="form-group">
                    <div class="form-label">Annual Interest Rate <span class="form-label-right" id="rateDisplay">13.5%</span></div>
                    <input type="range" class="range-input" id="interestRate" min="8" max="24" step="0.5" value="13.5" oninput="calcLoan()">
                    <div class="range-row"><span>8%</span><span>24%</span></div>
                </div>

                <div style="margin-bottom:20px">
                    <div class="form-label" style="margin-bottom:10px">Quick Lender Rates</div>
                    <div class="lender-tabs">
                        <button class="lender-tab" onclick="setRate(13.5)">HBL <small>13.5%</small></button>
                        <button class="lender-tab" onclick="setRate(14.0)">Meezan <small>14%</small></button>
                        <button class="lender-tab" onclick="setRate(13.0)">Alfalah <small>13%</small></button>
                        <button class="lender-tab" onclick="setRate(12.5)">UBL <small>12.5%</small></button>
                        <button class="lender-tab" onclick="setRate(12.0)">MCB <small>12%</small></button>
                        <button class="lender-tab" onclick="setRate(15.5)">Standard <small>~15.5%</small></button>
                    </div>
                </div>

                <!-- Manual input override -->
                <div class="form-group">
                    <div class="form-label">Or Type Car Price Directly</div>
                    <input type="number" class="num-input" id="carPriceInput" placeholder="e.g. 2500000" oninput="syncFromInput(this.value)">
                </div>
            </div>

            <!-- Info boxes -->
            <div class="info-boxes">
                <div class="info-box">
                    <h4><i class="fas fa-lightbulb"></i> Pakistan Rate Guide</h4>
                    <p>Commercial banks typically offer 12–18% p.a. Islamic financing options (Meezan, Alfalah) may have different structures. Negotiate a lower rate with a larger down payment (≥30%).</p>
                </div>
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Pro Tip</h4>
                    <p>A larger down payment reduces your monthly burden and total interest paid. Aim for at least 30% down payment when buying a car in Pakistan.</p>
                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div class="results-card" style="position:sticky;top:80px">
            <h2><i class="fas fa-chart-pie"></i> Your Estimate</h2>

            <div class="metric">
                <div class="metric-label">Monthly Repayment</div>
                <div class="metric-value metric-accent" id="monthlyPayment">Rs. 55,200</div>
                <div class="metric-sub">Principal + Profit/Interest per month</div>
            </div>

            <hr class="metric-divider">

            <div class="breakdown">
                <div class="breakdown-row">
                    <span class="breakdown-label">Car Price</span>
                    <span class="breakdown-val" id="b-price">Rs. 2,000,000</span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Down Payment</span>
                    <span class="breakdown-val" id="b-down" style="color:var(--green)">- Rs. 400,000</span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Loan Amount</span>
                    <span class="breakdown-val" id="b-loan">Rs. 1,600,000</span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Total Mark-up</span>
                    <span class="breakdown-val" id="b-interest" style="color:var(--red)">Rs. 451,500</span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Total Amount Payable</span>
                    <span class="breakdown-val" id="b-total">Rs. 2,451,500</span>
                </div>
            </div>

            <div style="margin-top:20px">
                <!-- Progress bar: principal vs interest -->
                <div style="font-size:11px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between">
                    <span><span style="color:var(--green)">■</span> Principal</span>
                    <span><span style="color:var(--red)">■</span> Interest</span>
                </div>
                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,.07);overflow:hidden;display:flex">
                    <div id="principalBar" style="background:var(--green);height:100%;transition:width .4s;width:78%"></div>
                    <div id="interestBar"  style="background:var(--red);height:100%;transition:width .4s;width:22%"></div>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:center" id="barLabel">78% Principal / 22% Interest</div>
            </div>

            <div style="margin-top:22px">
                <a href="listings.php" class="btn btn-accent" style="width:100%;justify-content:center;margin-bottom:10px"><i class="fas fa-search"></i> Browse Cars in Budget</a>
                <button onclick="printResults()" class="btn btn-outline" style="width:100%;justify-content:center;font-size:13px"><i class="fas fa-print"></i> Print / Save PDF</button>
            </div>
        </div>
    </div><!-- /.calc-layout -->

    <!-- AMORTIZATION SCHEDULE (hidden, toggleable) -->
    <div class="calc-card" style="margin-bottom:40px">
        <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="toggleAmort()">
            <h2 style="margin-bottom:0"><i class="fas fa-table"></i> Full Amortization Schedule</h2>
            <i class="fas fa-chevron-down" id="amortChevron" style="color:var(--muted)"></i>
        </div>
        <div id="amortWrap" style="display:none;margin-top:20px;overflow-x:auto">
            <table class="amort-table" id="amortTable">
                <thead><tr><th>#</th><th>Monthly Payment</th><th class="principal-col">Principal</th><th class="interest-col">Interest</th><th>Balance</th></tr></thead>
                <tbody id="amortBody"></tbody>
            </table>
        </div>
    </div>

    <!-- SEO CONTENT -->
    <div class="seo-section">
        <h2>Car Loan Calculator Pakistan — How It Works</h2>
        <p>This free car loan calculator helps Pakistani car buyers estimate their monthly repayments before visiting a bank. Enter your car's purchase price, how much you plan to pay upfront (down payment), your preferred loan term in months, and the annual interest rate — and we'll calculate your exact monthly instalment, total mark-up, and full repayment amount.</p>

        <h3>How Car Loans Work in Pakistan</h3>
        <p>In Pakistan, most banks offer both Conventional and Islamic Car Financing (Ijarah). In conventional banking, you pay interest on the reducing balance. In Islamic financing, the bank buys the car and leases it to you for a fixed monthly profit/rent.</p>

        <h3>Where to Get Car Financing in Pakistan</h3>
        <ul>
            <li><strong>Major Banks:</strong> HBL, Meezan Bank (Islamic), Bank Alfalah, UBL, MCB, Allied Bank.</li>
            <li><strong>Specialized Auto Finance:</strong> Many manufacturers (Suzuki, Toyota, Honda) have partner banks for faster processing.</li>
        </ul>

        <h3>Tips for Getting a Better Rate</h3>
        <ul>
            <li>Maintain a healthy CIB report — any defaults can lead to rejection or higher rates.</li>
            <li>Choose a shorter loan term (e.g. 3 years instead of 5) to save significantly on mark-up.</li>
            <li>Compare at least 3 lenders for the best processing fees and insurance rates.</li>
        </ul>
    </div>
</div>

<script>
function fmt(n) {
    return 'Rs. ' + Math.round(n).toLocaleString('en-PK');
}
function calcLoan() {
    const carPrice   = parseFloat(document.getElementById('carPrice').value);
    const downPct    = parseFloat(document.getElementById('downPayment').value);
    const months     = parseFloat(document.getElementById('loanTerm').value);
    const annualRate = parseFloat(document.getElementById('interestRate').value);

    const downAmt  = carPrice * downPct / 100;
    const loanAmt  = carPrice - downAmt;
    const monthlyR = annualRate / 100 / 12;

    // PMT formula
    let monthly;
    if (monthlyR === 0) {
        monthly = loanAmt / months;
    } else {
        monthly = loanAmt * monthlyR * Math.pow(1+monthlyR, months) / (Math.pow(1+monthlyR, months)-1);
    }

    const totalPay     = monthly * months;
    const totalInterest= totalPay - loanAmt;
    const interestPct  = Math.round(totalInterest / totalPay * 100);
    const principalPct = 100 - interestPct;

    // Update displays
    document.getElementById('priceDisplay').textContent = fmt(carPrice);
    document.getElementById('downDisplay').textContent  = fmt(downAmt) + ' (' + downPct + '%)';
    document.getElementById('termDisplay').textContent  = months + ' months (' + (months/12).toFixed(1).replace('.0','') + ' year' + (months!==12?'s':'') + ')';
    document.getElementById('rateDisplay').textContent  = annualRate + '%';
    document.getElementById('carPriceInput').value      = carPrice;

    document.getElementById('monthlyPayment').textContent = fmt(monthly);
    document.getElementById('b-price').textContent    = fmt(carPrice);
    document.getElementById('b-down').textContent     = '- ' + fmt(downAmt);
    document.getElementById('b-loan').textContent     = fmt(loanAmt);
    document.getElementById('b-interest').textContent = fmt(totalInterest);
    document.getElementById('b-total').textContent    = fmt(carPrice - downAmt + totalInterest + downAmt);

    document.getElementById('principalBar').style.width = principalPct + '%';
    document.getElementById('interestBar').style.width  = interestPct + '%';
    document.getElementById('barLabel').textContent     = principalPct + '% Principal / ' + interestPct + '% Interest';

    // Amortization
    buildAmortization(loanAmt, monthlyR, monthly, months);
}

function buildAmortization(balance, monthlyR, monthly, months) {
    const tbody = document.getElementById('amortBody');
    tbody.innerHTML = '';
    for (let i=1; i<=months; i++) {
        const interest  = balance * monthlyR;
        const principal = monthly - interest;
        balance -= principal;
        const row = `<tr>
            <td style="color:var(--muted)">${i}</td>
            <td style="font-weight:600">${fmt(monthly)}</td>
            <td class="principal-col">${fmt(principal)}</td>
            <td class="interest-col">${fmt(interest)}</td>
            <td>${fmt(Math.max(balance,0))}</td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
    }
}

function setRate(r) {
    document.getElementById('interestRate').value = r;
    document.querySelectorAll('.lender-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    calcLoan();
}

function syncFromInput(val) {
    const v = parseInt(val);
    if (v >= 200000 && v <= 20000000) {
        document.getElementById('carPrice').value = v;
        calcLoan();
    }
}

function toggleAmort() {
    const wrap = document.getElementById('amortWrap');
    const chevron = document.getElementById('amortChevron');
    if (wrap.style.display==='none') {
        wrap.style.display='block';
        chevron.style.transform='rotate(180deg)';
    } else {
        wrap.style.display='none';
        chevron.style.transform='';
    }
}

function printResults() {
    window.print();
}

// Init
calcLoan();
</script>

<!-- Structured Data for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "Car Loan Calculator Pakistan",
  "url": "<?= BASE_URL ?>/loan-calculator.php",
  "description": "<?= $metaDesc ?>",
  "applicationCategory": "FinanceApplication",
  "operatingSystem": "Any",
  "offers": {"@type":"Offer","price":"0","priceCurrency":"PKR"},
  "publisher": {"@type":"Organization","name":"CarSoko Pakistan","url":"<?= BASE_URL ?>"}
}
</script>
</body>
</html>