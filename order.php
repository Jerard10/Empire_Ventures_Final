<?php
require_once 'db.php';
requireLogin();
 
$userId = $_SESSION['user_id'];
$selectedVarietyId = isset($_GET['variety_id']) ? (int)$_GET['variety_id'] : 0;
$message = '';
$error = '';
 
// Fetch available varieties
$stmt = $pdo->query("SELECT * FROM varieties ORDER BY name ASC");
$varieties = $stmt->fetchAll();
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $varietyId       = (int)($_POST['rice_variety_id'] ?? 0);
    $quantitySacks   = (int)($_POST['quantity_sacks'] ?? 1);
    $deliveryAddress = sanitize($_POST['delivery_address'] ?? '');
    $contactPhone    = sanitize($_POST['contact_phone'] ?? '');
    $paymentMethod   = sanitize($_POST['payment_method'] ?? 'cod');
 
    if ($varietyId <= 0 || $quantitySacks < 1 || empty($deliveryAddress) || empty($contactPhone)) {
        $error = 'Please complete all required order details correctly.';
    } else {
        // Retrieve rice price
        $vStmt = $pdo->prepare("SELECT * FROM rice_varieties WHERE id = ? AND is_active = 1");
        $vStmt->execute([$varietyId]);
        $rice = $vStmt->fetch();
 
        if (!$rice) {
            $error = 'Selected rice variety is invalid or unavailable.';
        } else {
            $pricePerSack = $rice['price_per_sack'];
            $totalAmount  = $pricePerSack * $quantitySacks;
            $orderNumber  = 'EV-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
 
            $insert = $pdo->prepare("
                INSERT INTO orders (orders_number, user_id, variety_id, quantity_sacks, sack_weight_kg, price_per_sack, total_amount, delivery_address, contact_phone, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
 
            if ($insert->execute([$orderNumber, $userId, $varietyId, $quantitySacks, $rice['sack_weight_kg'], $pricePerSack, $totalAmount, $deliveryAddress, $contactPhone, $paymentMethod])) {
                $message = "Order placed successfully! Your Order ID is <strong>{$orderNumber}</strong>.";
            } else {
                $error = "Failed to process order. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Rice Sacks | Empire Ventures</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300..700&family=Fraunces:opsz,wght@9..144,400..600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php renderNav('order'); ?>
 
    <main class="reg-main">
        <div class="sheet">
            <form method="POST" action="order.php" class="sheet-form" id="orderForm">
                <h1>Order Rice by the Sack</h1>
                <p class="sheet-intro">Select your preferred variety, order volume, and delivery information.</p>
 
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>
 
                <div class="form-grid">
                    <fieldset>
                        <legend class="group-title">Order Options</legend>
 
                        <div class="field">
                            <label for="rice_variety_id">Select Rice Variety</label>
                            <select id="rice_variety_id" name="rice_variety_id" class="select" required>
                                <option value="" disabled <?= $selectedVarietyId === 0 ? 'selected' : '' ?>>-- Choose Rice Variety --</option>
                                <?php foreach ($varieties as $v): ?>
                                    <option value="<?= $v['id'] ?>" 
                                            data-price="<?= $v['price_per_sack'] ?>" 
                                            data-weight="<?= $v['sack_weight_kg'] ?>"
                                            <?= $selectedVarietyId === (int)$v['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($v['name']) ?> - ₱<?= number_format($v['price_per_sack'], 2) ?> / <?= $v['sack_weight_kg'] ?>kg Sack
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
 
                        <div class="field">
                            <label for="quantity_sacks">Number of Sacks (50kg each)</label>
                            <input class="input" type="number" id="quantity_sacks" name="quantity_sacks" min="1" max="500" value="1" required>
                        </div>
 
                        <div class="field">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="select" required>
                                <option value="cod">Cash on Delivery (COD)</option>
                                <option value="bank_transfer">Bank Transfer / Deposit</option>
                                <option value="gcash">GCash E-Wallet</option>
                            </select>
                        </div>
                    </fieldset>
 
                    <fieldset>
                        <legend class="group-title">Delivery & Contact Information</legend>
 
                        <div class="field">
                            <label for="contact_phone">Contact Phone Number</label>
                            <input class="input" type="tel" id="contact_phone" name="contact_phone" required placeholder="+63 900 000 0000">
                        </div>
 
                        <div class="field">
                            <label for="delivery_address">Complete Delivery Address</label>
                            <textarea class="input" id="delivery_address" name="delivery_address" rows="4" required placeholder="Street address, City, Province, Zip code"></textarea>
                        </div>
 
                        <!-- Price summary box -->
                        <div class="summary-card">
                            <h3>Order Summary</h3>
                            <div class="summary-row"><span>Unit Price:</span> <strong id="summaryUnitPrice">₱0.00</strong></div>
                            <div class="summary-row"><span>Quantity:</span> <strong id="summaryQty">1 Sack</strong></div>
                            <hr class="summary-divider">
                            <div class="summary-row total"><span>Total Amount:</span> <strong id="summaryTotal">₱0.00</strong></div>
                        </div>
                    </fieldset>
                </div>
 
                <div class="form-actions">
                    <button type="submit" class="btn btn-forest">Confirm & Place Sack Order</button>
                </div>
            </form>
        </div>
    </main>
 
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const varietySelect = document.getElementById('rice_variety_id');
            const qtyInput = document.getElementById('quantity_sacks');
            const unitPriceEl = document.getElementById('summaryUnitPrice');
            const qtyEl = document.getElementById('summaryQty');
            const totalEl = document.getElementById('summaryTotal');
 
            function updateCalculator() {
                const option = varietySelect.options[varietySelect.selectedIndex];
                const price = option && option.dataset.price ? parseFloat(option.dataset.price) : 0;
                const qty = parseInt(qtyInput.value) || 1;
                const total = price * qty;
 
                unitPriceEl.textContent = '₱' + price.toLocaleString('en-US', {minimumFractionDigits: 2});
                qtyEl.textContent = qty + (qty > 1 ? ' Sacks' : ' Sack');
                totalEl.textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            }
 
            varietySelect.addEventListener('change', updateCalculator);
            qtyInput.addEventListener('input', updateCalculator);
            updateCalculator();
        });
    </script>
<?php renderFooter(); ?>
</body>
</html>