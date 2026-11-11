<?php
require_once 'db.php';
 
// Fetch active rice varieties from DB
$stmt = $pdo->query("SELECT * FROM varieties");
$varieties = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empire Ventures | Premium Rice Supplier</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300..700&family=Fraunces:opsz,wght@9..144,400..600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php renderNav('home'); ?>
 
    <main>
        <section id="home" class="hero">
            <div class="hero-grains" aria-hidden="true"></div>
            <div class="hero-inner">
                <h1>
                    <span class="line"><span style="--i:0">Farm-fresh rice,</span></span>
                    <span class="line"><span style="--i:1">supplied by the sack.</span></span>
                </h1>
                <p class="hero-lede">
                    Empire Ventures is a trusted supplier of premium-grade rice, delivering high-quality,
                    farm-fresh grains at competitive wholesale and retail rates.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="orders.php">Order Rice Sacks Now</a>
                    <a class="btn btn-ghost" href="registration.php">Register as wholesale partner</a>
                </div>
            </div>
        </section>
 
        <section id="ricevarieties" class="page-section">
            <div class="container split">
                <div class="split-head">
                    <h2 class="section-title">Rice varieties</h2>
                    <p class="section-lede">Browse available grades and order directly by the 50kg sack.</p>
                </div>
 
                <div class="rice-grid"><?php foreach ($varieties as $v) renderVarietyCard($v); ?></div>
            </div>
        </section>
 
        <section id="partner" class="page-section">
            <div class="cta-panel reveal">
                <h2>Become a wholesale partner</h2>
                <p>Partner with Empire Ventures today and bring premium quality, reliability, and farm-fresh value to your business.</p>
                <a class="btn btn-forest" href="registration.php">Register your business</a>
            </div>
        </section>
    </main>
 
    
<?php renderFooter(); ?>
</body>
</html> 