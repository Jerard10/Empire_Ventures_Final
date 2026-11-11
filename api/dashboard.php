<?php
require_once 'db.php';
requireLogin();

$u = currentUser();
$userId = $u['id'];

$orderStmt = $pdo->prepare("
    SELECT o.*, v.name AS variety_name
    FROM orders o JOIN rice_varieties v ON o.rice_variety_id = v.id
    WHERE o.user_id = ? ORDER BY o.created_at DESC
");
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

$wsStmt = $pdo->prepare("
    SELECT w.*, v.name AS variety_name
    FROM wholesale_registrations w JOIN rice_varieties v ON w.rice_variety_id = v.id
    WHERE w.user_id = ? OR w.email = ? ORDER BY w.created_at DESC
");
$wsStmt->execute([$userId, $u['email']]);
$registrations = $wsStmt->fetchAll();

// Summary numbers
$spent = 0; $open = 0;
foreach ($orders as $o) {
    $s = strtolower($o['order_status']);
    if ($s !== 'cancelled') $spent += (float)$o['total_amount'];
    if (!in_array($s, ['delivered', 'completed', 'cancelled'], true)) $open++;
}
$partner = isWholesalePartner();
$reg = wholesaleRegistration();
$partnerLabel = $partner ? 'Approved' : ($reg ? ucfirst($reg['status']) : 'Not registered');

renderHead('Dashboard');
renderNav('');
?>
<div class="dash">
    <aside class="side">
        <div class="side-who">
            <?= avatarHtml($u, 'avatar-sm') ?>
            <div><strong><?= sanitize($u['full_name']) ?></strong><small><?= $partner ? 'Wholesale partner' : 'Customer' ?></small></div>
        </div>
        <nav aria-label="Dashboard">
            <a href="dashboard.php" aria-current="page">Overview</a>
            <a href="#orders">My orders</a>
            <a href="#wholesale">Wholesale</a>
            <a href="order.php">Order rice</a>
            <a href="profile.php">Edit profile</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Log out</a>
        </nav>
    </aside>

    <main id="main">
        <?php renderFlash(); ?>

        <div class="dash-head">
            <div>
                <h1>Welcome back, <?= sanitize(explode(' ', $u['full_name'])[0]) ?></h1>
                <p class="section-lede" style="margin:0">Your orders and wholesale status in one place.</p>
            </div>
            <a href="order.php" class="btn btn-primary">Order rice</a>
        </div>

        <dl class="stats">
            <div class="stat"><dt>Total orders</dt><dd><?= count($orders) ?></dd></div>
            <div class="stat"><dt>In progress</dt><dd><?= $open ?></dd></div>
            <div class="stat"><dt>Total spent</dt><dd><?= peso($spent) ?></dd></div>
            <div class="stat"><dt>Wholesale status</dt><dd><?= sanitize($partnerLabel) ?></dd></div>
        </dl>

        <section class="dashboard-card" id="orders" aria-labelledby="orders-h">
            <h2 id="orders-h">My orders</h2>
            <div class="table-wrap" aria-label="Orders table, scrolls sideways on small screens">
                <table class="data-table">
                    <caption class="sr-only">Your rice orders, newest first</caption>
                    <thead><tr>
                        <th scope="col">Order #</th><th scope="col">Rice</th><th scope="col">Quantity</th><th scope="col">Total</th>
                        <th scope="col">Payment</th><th scope="col">Status</th><th scope="col">Date</th>
                    </tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="7" class="empty-row">You have no orders yet. <a href="order.php">Place your first order</a>.</td></tr>
                    <?php else: foreach ($orders as $o): ?>
                        <tr>
                            <th scope="row" class="cell-strong"><?= sanitize($o['order_number']) ?></th>
                            <td><?= sanitize($o['variety_name']) ?></td>
                            <td><?= (int)$o['quantity_sacks'] ?> × <?= (float)$o['sack_weight_kg'] ?> kg (<?= (float)$o['quantity_sacks'] * (float)$o['sack_weight_kg'] ?> kg)</td>
                            <td><?= peso($o['total_amount']) ?></td>
                            <td><?= sanitize(strtoupper(str_replace('_', ' ', $o['payment_method']))) ?></td>
                            <td><span class="badge-status badge-<?= sanitize(strtolower($o['order_status'])) ?>"><?= sanitize(ucfirst(str_replace('_', ' ', $o['order_status']))) ?></span></td>
                            <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-card" id="wholesale" aria-labelledby="ws-h">
            <h2 id="ws-h">Wholesale partner</h2>
            <?php if ($partner): ?>
                <div class="alert alert-success">You are an approved wholesale partner. You can place bulk orders of <?= BULK_MIN_KG ?> kg or more.</div>
            <?php elseif ($reg): ?>
                <div class="alert alert-warn">Your application is <?= sanitize(strtolower($reg['status'])) ?>. Bulk orders unlock once it is approved.</div>
            <?php else: ?>
                <div class="partner-box">
                    <p style="margin:0">Stores, restaurants and resellers must register before ordering <?= BULK_MIN_KG ?> kg or more.</p>
                    <a href="registration.php" class="btn btn-outline">Register your business</a>
                </div>
            <?php endif; ?>

            <?php if ($registrations): ?>
            <div class="table-wrap" style="margin-top:16px" aria-label="Wholesale applications, scrolls sideways on small screens">
                <table class="data-table">
                    <caption class="sr-only">Your wholesale applications</caption>
                    <thead><tr>
                        <th scope="col">Business</th><th scope="col">Contact</th><th scope="col">Main variety</th>
                        <th scope="col">Weekly volume</th><th scope="col">Status</th><th scope="col">Applied</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($registrations as $r): ?>
                        <tr>
                            <th scope="row" class="cell-strong"><?= sanitize($r['primary_name']) ?></th>
                            <td><?= sanitize($r['full_name']) ?></td>
                            <td><?= sanitize($r['variety_name']) ?></td>
                            <td><?= sanitize($r['supply_volume']) ?></td>
                            <td><span class="badge-status badge-<?= sanitize(strtolower($r['status'])) ?>"><?= sanitize(ucfirst($r['status'])) ?></span></td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php renderFooter(); ?>
</body>
</html>
