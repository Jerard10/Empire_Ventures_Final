<?php
require_once 'db.php';
 
$message = '';
$error = '';
 
// Fetch rice varieties dropdown
$vStmt = $pdo->query("SELECT * FROM varieties");
$varieties = $vStmt->fetchAll();
 
// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName       = sanitize($_POST['fullName'] ?? '');
    $registeredName = sanitize($_POST['registeredName'] ?? '');
    $primaryName    = sanitize($_POST['primaryName'] ?? '');
    $email          = sanitize($_POST['email'] ?? '');
    $contactNumber  = sanitize($_POST['contactNumber'] ?? '');
    $riceVarietyId  = (int)($_POST['riceVarieties'] ?? 0);
    $supplyVolume   = sanitize($_POST['supplyVolume'] ?? '');
    $businessTypes  = $_POST['businessTypes'] ?? []; // Array of checked business types
 
    if (empty($fullName) || empty($registeredName) || empty($primaryName) || empty($email) || empty($contactNumber) || empty($businessTypes) || $riceVarietyId <= 0 || empty($supplyVolume)) {
        $error = 'Please fill out all required fields and select at least one business type.';
    } else {
        $typesStr = implode(', ', array_map('sanitize', $businessTypes));
        $userId   = isLoggedIn() ? $_SESSION['user_id'] : null;
 
        $stmt = $pdo->prepare("
            INSERT INTO wholesale_registrations (user_id, full_name, registered_name, primary_name, email, contact_number, business_types, rice_variety_id, supply_volume)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
 
        if ($stmt->execute([$userId, $fullName, $registeredName, $primaryName, $email, $contactNumber, $typesStr, $riceVarietyId, $supplyVolume])) {
            $message = 'Wholesale application submitted successfully! Our sales team will verify your business profile.';
        } else {
            $error = 'Failed to record registration. Please try again.';
        }
    }
}
 
// Fetch existing registered accounts from DB

$listStmt = $pdo->query("
    SELECT
        r.*,
        v.name AS variety_name
    FROM wholesale_registrations r
    LEFT JOIN varieties v
        ON r.rice_variety_id = v.id
    ORDER BY r.created_at DESC
");

$registeredAccounts = $listStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wholesale Registration | Empire Ventures</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300..700&family=Fraunces:opsz,wght@9..144,400..600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php renderNav('wholesale'); ?>
 
    <main class="reg-main">
        <div class="sheet">
            <form id="registrationForm" method="POST" action="registration.php" class="sheet-form">
                <h1>Wholesale Registration</h1>
                <p class="sheet-intro">Tell us about your business and how much rice you move each week to open a wholesale account.</p>
 
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>
 
                <div class="form-grid">
                    <fieldset>
                        <legend class="group-title">Business & Contact Information</legend>
 
                        <div class="field">
                            <label for="fullName">Full Name</label>
                            <input class="input" type="text" id="fullName" name="fullName" required placeholder="e.g. Maria Santos">
                        </div>
                        <div class="field">
                            <label for="registeredName">Registered Business Name</label>
                            <input class="input" type="text" id="registeredName" name="registeredName" required placeholder="As on your business registration">
                        </div>
                        <div class="field">
                            <label for="primaryName">Primary Business Name</label>
                            <input class="input" type="text" id="primaryName" name="primaryName" required placeholder="The name your customers know">
                        </div>
                        <div class="field">
                            <label for="email">Email Address</label>
                            <input class="input" type="email" id="email" name="email" required placeholder="name@business.com">
                        </div>
                        <div class="field">
                            <label for="contactNumber">Contact Number</label>
                            <input class="input" type="tel" id="contactNumber" name="contactNumber" required placeholder="+63 900 000 0000">
                        </div>
                    </fieldset>
 
                    <div class="stack">
                        <div>
                            <div class="group-head">
                                <h2 class="group-title" id="businessTypeTitle">Business Type</h2>
                            </div>
                            <p class="group-hint">Select all that apply.</p>
 
                            <div class="type-grid" role="group">
                                <label class="checkbox-btn">
                                    <input type="checkbox" name="businessTypes[]" value="Retail store/rice shop">
                                    <span>Retail store / rice shop</span>
                                </label>
                                <label class="checkbox-btn">
                                    <input type="checkbox" name="businessTypes[]" value="Restaurant/food catering">
                                    <span>Restaurant / food catering</span>
                                </label>
                                <label class="checkbox-btn">
                                    <input type="checkbox" name="businessTypes[]" value="Supermarket">
                                    <span>Supermarket</span>
                                </label>
                                <label class="checkbox-btn">
                                    <input type="checkbox" name="businessTypes[]" value="Wholesaler/reseller">
                                    <span>Wholesaler / reseller</span>
                                </label>
                            </div>
                        </div>
 
                        <fieldset>
                            <legend class="group-title">Supply & Logistics</legend>
 
                            <div class="field">
                                <label for="riceVarieties">Primary Rice Variety</label>
                                <select id="riceVarieties" name="riceVarieties" class="select" required>
                                    <option value="" disabled selected>Select a variety</option>
                                    <?php foreach ($varieties as $v): ?>
                                        <option value="<?= $v['id'] ?>"><?= sanitize($v['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
 
                            <div class="field">
                                <label for="supplyVolume">Weekly Supply Volume</label>
                                <select id="supplyVolume" name="supplyVolume" class="select" required>
                                    <option value="" disabled selected>Select a volume</option>
                                    <option value="50 - 200 Sacks (1.25 - 5 Tons)">50 - 200 Sacks (1.25 - 5 Tons)</option>
                                    <option value="200 - 500 Sacks (5 - 12.5 Tons)">200 - 500 Sacks (5 - 12.5 Tons)</option>
                                    <option value="500 - 1,000 Sacks (12.5 - 25 Tons)">500 - 1,000 Sacks (12.5 - 25 Tons)</option>
                                    <option value="1,000+ Sacks (>25 Tons / Container)">1,000+ Sacks (&gt;25 Tons / Container)</option>
                                </select>
                            </div>
                        </fieldset>
                    </div>
                </div>
 
                <div class="form-actions">
                    <button type="submit" class="btn btn-forest">Submit Wholesale Registration</button>
                </div>
            </form>
 
            <section class="accounts">
                <div class="accounts-head">
                    <div>
                        <h2>Registered Wholesale Partners</h2>
                        <p>Verified wholesale client applications.</p>
                    </div>
                    <div class="count-pill">Total Active: <strong><?= count($registeredAccounts) ?></strong></div>
                </div>
 
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Contact Person</th>
                                <th>Business Name</th>
                                <th>Business Type</th>
                                <th>Primary Rice Variety</th>
                                <th>Weekly Volume</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registeredAccounts)): ?>
                                <tr><td colspan="6" class="empty-row">No registered accounts found in the database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($registeredAccounts as $acc): ?>
                                    <tr>
                                        <td class="cell-strong"><?= sanitize($acc['full_name']) ?></td>
                                        <td><?= sanitize($acc['primary_name']) ?></td>
                                        <td>
                                            <div class="badge-row">
                                                <?php foreach (explode(',', $acc['business_types']) as $bt): ?>
                                                    <span class="badge-type"><?= sanitize(trim($bt)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td><?= sanitize($acc['variety_name']) ?></td>
                                        <td><?= sanitize($acc['supply_volume']) ?></td>
                                        <td>
                                            <span class="badge-status badge-<?= strtolower($acc['status']) ?>">
                                                <?= ucfirst($acc['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
<?php renderFooter(); ?>
</body>
</html>