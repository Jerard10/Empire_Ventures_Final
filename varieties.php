<?php
require_once 'db.php';
$varieties = $pdo->query("SELECT * FROM varieties")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rice Varieties | Empire Ventures</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300..700&family=Fraunces:opsz,wght@9..144,400..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head><body>
<?php renderNav('varieties'); ?>
<main class="container page-pad">
  <h1 class="section-title">Our premium rice varieties</h1>
  <p class="section-lede">Sourced from top grain farms and packed fresh in 50kg sacks.</p>
  <div class="rice-grid">
    <?php foreach ($varieties as $v) renderVarietyCard($v); ?>
    <?php if (!$varieties): ?><p class="empty-row">No varieties available right now.</p><?php endif; ?>
  </div>
</main>
<?php renderFooter(); ?>
</body></html>
 