<?php
// db.php - connection, session, auth, helpers and shared layout (head, nav, footer, variety card)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$host = 'localhost';
$db = 'firealertadmin_Empire_Ventures';
$user = 'firealertadmin_your_db_user';
$pass = 'YOUR_DB_PASSWORD';
$charset = 'utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Check the server configuration.']);
    exit();
}

// ---- Business rules (change here) ----
const PACK_SIZES  = [5, 10, 25, 50];   // kg options on the order page
const BULK_MIN_KG = 100;               // an order of this many kg or more is a "bulk / wholesale" order
const PARTNER_STATUSES = ['approved', 'verified', 'active']; // wholesale_registrations.status values that unlock bulk orders

// ---- Basics ----
function sanitize($d) { return htmlspecialchars(trim((string)$d), ENT_QUOTES, 'UTF-8'); }
function peso($n) { return '₱' . number_format((float)$n, 2); }
function isLoggedIn() { return isset($_SESSION['user_id']); }

// Only these local pages may be used as a "go back here after login" target (prevents open redirects).
function safeNext(?string $n): string {
    return ($n && preg_match('/^(dashboard|order|registration|profile|settings|varieties)\.php(\?variety_id=\d+)?$/', $n)) ? $n : '';
}
function requireLogin() {
    if (!isLoggedIn()) {
        $path = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        $qs   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        $next = safeNext($path . ($qs ? '?' . $qs : ''));
        header('Location: login.php' . ($next ? '?next=' . urlencode($next) : ''));
        exit;
    }
}

// Always read the user (and role) from the DB, never trust the session role for admin checks.
function currentUser() {
    global $pdo; static $u = false;
    if ($u === false) {
        $u = null;
        if (isLoggedIn()) {
            $s = $pdo->prepare("SELECT id, full_name, email, role, avatar FROM users WHERE id = ?");
            $s->execute([$_SESSION['user_id']]);
            $u = $s->fetch() ?: null;
        }
    }
    return $u;
}
function isAdmin() { $u = currentUser(); return $u && $u['role'] === 'admin'; }
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) { http_response_code(403); die('403 - Admins only. <a href="dashboard.php">Back to dashboard</a>'); }
}

function csrfToken() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrfField() { return '<input type="hidden" name="csrf" value="' . csrfToken() . '">'; }
function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); die('Invalid form token. Reload the page and try again.'); }
}

// One-time messages that survive a redirect (post → redirect → get).
function flash(?string $msg = null, string $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function alertBox(string $msg, string $type = 'error', string $id = '') {
    // role=alert interrupts the screen reader (errors); role=status waits politely (success/info)
    return '<div class="alert alert-' . $type . '"' . ($id ? ' id="' . $id . '"' : '') . ' role="' . ($type === 'error' ? 'alert' : 'status') . '">' . sanitize($msg) . '</div>';
}
function renderFlash() { if ($f = flash()) echo alertBox($f[0], $f[1]); }

// Validates and stores an uploaded JPG/PNG/WebP (max 2 MB). Returns the new file name.
function saveImage(array $f, string $dir): string {
    if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed. Please try again.');
    if ($f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Image must be under 2 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Use a JPG, PNG, or WebP image.');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new RuntimeException('Could not save the image.');
    return $name;
}

// ---- Wholesale partner + pricing helpers ----

// The signed-in user's wholesale application (an approved one wins over older ones), or null.
function wholesaleRegistration(): ?array {
    global $pdo; static $r = false;
    if ($r === false) {
        $r = null;
        if ($u = currentUser()) {
            $s = $pdo->prepare("SELECT * FROM wholesale_registrations WHERE user_id = ? OR email = ? ORDER BY created_at DESC");
            $s->execute([$u['id'], $u['email']]);
            $rows = $s->fetchAll();
            foreach ($rows as $row) if (in_array(strtolower($row['status']), PARTNER_STATUSES, true)) { $r = $row; break; }
            $r = $r ?? ($rows[0] ?? null);
        }
    }
    return $r;
}
function isWholesalePartner(): bool {
    $r = wholesaleRegistration();
    return $r && in_array(strtolower($r['status']), PARTNER_STATUSES, true);
}
// Price of one pack of $kg kilograms. price_per_sack is for the variety's full sack (sack_weight_kg).
function packPrice(array $v, int $kg): float {
    return round((float)$v['price_per_sack'] * $kg / max(1, (float)$v['sack_weight_kg']), 2);
}

// ---- Shared layout ----

const SOCIALS = [
    'facebook'  => 'https://facebook.com/',          // <- put your real links here
    'instagram' => 'https://instagram.com/',
    'gmail'     => 'mailto:hello@empireventures.ph',
];

// Opens the page: <head>, saved display preferences, skip link. Call before renderNav().
function renderHead(string $title, string $bodyClass = '') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . sanitize($title) . ' | Empire Ventures</title>
<link rel="icon" href="logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300..700&family=Fraunces:opsz,wght@9..144,400..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<script>try{var d=document.documentElement,s=localStorage.getItem("ev-size"),c=localStorage.getItem("ev-contrast");if(s)d.dataset.size=s;if(c)d.dataset.contrast=c}catch(e){}</script>
</head><body class="' . sanitize($bodyClass) . '"><a class="skip-link" href="#main">Skip to main content</a>';
}

function avatarHtml(array $u, string $class = 'avatar-sm') {
    $inner = $u['avatar'] ? '<img src="uploads/avatars/' . sanitize($u['avatar']) . '" alt="">'
                          : '<span aria-hidden="true">' . sanitize(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) . '</span>';
    return '<span class="avatar ' . $class . '">' . $inner . '</span>';
}

function renderNav(string $active = '') {
    $u = currentUser();
    $links = ['home' => ['index.php', 'Home'], 'varieties' => ['varieties.php', 'Rice varieties'],
              'order' => ['order.php', 'Order rice'], 'wholesale' => ['registration.php', 'Wholesale partner']];
    echo '<header class="header"><nav class="navbar" aria-label="Main">
      <a href="index.php" class="nav-brand"><img class="brand-mark" src="logo.svg" alt="" width="36" height="36"><span class="brand-name">Empire <span>Ventures</span></span></a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-menu"><span class="sr-only">Menu</span><span class="bars" aria-hidden="true"></span></button>
      <div class="nav-menu" id="nav-menu">';
    foreach ($links as $k => [$href, $label])
        echo '<a href="' . $href . '" class="nav-links"' . ($k === $active ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    echo '</div><div class="nav-auth-group">';
    if ($u) {
        echo '<div class="user-menu"><button type="button" class="user-btn" aria-expanded="false" aria-controls="user-drop">'
           . avatarHtml($u) . '<span class="user-name">' . sanitize(explode(' ', $u['full_name'])[0]) . '</span><span class="caret" aria-hidden="true"></span></button>
          <div class="user-drop" id="user-drop" hidden>
            <p class="user-drop-who"><strong>' . sanitize($u['full_name']) . '</strong><span>' . sanitize($u['email']) . '</span></p>
            <a href="dashboard.php">My dashboard</a>
            <a href="dashboard.php#orders">My orders</a>
            <a href="dashboard.php#wholesale">Wholesale status</a>
            <hr>
            <a href="profile.php">Edit profile</a>
            <a href="settings.php">Settings</a>'
          . (isAdmin() ? '<hr><a href="admin.php">Admin panel</a><a href="admin_products.php">Product pricing</a>' : '') .
          '<hr><a href="logout.php">Log out</a>
          </div></div>';
    } else {
        echo '<a href="login.php" class="btn btn-ghost-light btn-sm">Log in</a><a href="signup.php" class="btn btn-gold btn-sm">Sign up</a>';
    }
    echo '</div></nav></header>';
}

function renderFooter() {
    $i = [
      'facebook'  => '<path fill="currentColor" stroke="none" d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.7V3.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.4H7.7V13h2.7v8z"/>',
      'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor"/>',
      'gmail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
    ];
    echo '<footer class="site-footer"><div class="footer-inner"><div><strong>Empire Ventures</strong><p>Premium rice, supplied by the sack.</p></div>
      <ul class="socials" aria-label="Social links">';
    foreach (SOCIALS as $name => $url)
        echo '<li><a href="' . sanitize($url) . '" class="social" aria-label="' . ucfirst($name) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $i[$name] . '</svg></a></li>';
    echo '</ul></div><p class="footer-copy">&copy; ' . date('Y') . ' Empire Ventures. All rights reserved.</p></footer><script src="app.js" defer></script>';
}

function productImage($v) { return $v['image'] ? 'uploads/products/' . sanitize($v['image']) : ''; }

// A framed product card: photo, description, and the price of every pack size.
function renderVarietyCard(array $v) {
    $id   = 'variety-' . (int)$v['id'];
    $img  = productImage($v);
    $sack = max(1, (int)$v['sack_weight_kg']);
    $perKg = (float)$v['price_per_sack'] / $sack;
    echo '<article class="variety" aria-labelledby="' . $id . '">
      <div class="variety-photo">' . ($img ? '<img src="' . $img . '" alt="' . sanitize($v['name']) . ' rice" loading="lazy">' : '<span class="variety-photo-empty">Photo coming soon</span>') . '</div>
      <div class="variety-body">
        <h3 id="' . $id . '">' . sanitize($v['name']) . '</h3>
        <p class="variety-desc">' . sanitize($v['description']) . '</p>
        <table class="price-table"><caption>Price by pack size <small>(' . peso($perKg) . ' per kg)</small></caption>
          <thead><tr><th scope="col">Pack</th><th scope="col">Price</th></tr></thead><tbody>';
    foreach (PACK_SIZES as $kg)
        echo '<tr><th scope="row">' . $kg . ' kg' . ($kg === $sack ? ' sack' : '') . '</th><td>' . peso(packPrice($v, $kg)) . '</td></tr>';
    echo '</tbody></table>
        <a href="order.php?variety_id=' . (int)$v['id'] . '" class="btn btn-primary btn-block">Order ' . sanitize($v['name']) . '</a>
      </div></article>';
}
