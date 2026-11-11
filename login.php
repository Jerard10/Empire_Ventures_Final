
<?php
require_once 'db.php';
require_once 'config/mail.php';

$next = safeNext($_GET['next'] ?? $_POST['next'] ?? '');

if (isLoggedIn()) {
    header('Location: ' . ($next ?: 'dashboard.php'));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    checkCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter both your email and your password.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // Generate a secure six-digit OTP.
            $otp = (string) random_int(100000, 999999);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + 300);

            // Remove previous OTPs.
            $delete = $pdo->prepare(
                "DELETE FROM login_otps WHERE user_id = ?"
            );
            $delete->execute([$user['id']]);

            // Save the hashed OTP.
            $insert = $pdo->prepare(
                "INSERT INTO login_otps
                (user_id, otp_hash, expires_at, attempts)
                VALUES (?, ?, ?, 0)"
            );

            $insert->execute([
                $user['id'],
                $otpHash,
                $expiresAt
            ]);

            // Send OTP to the registered email.
            if (sendLoginOTP($user['email'], $otp)) {

                $_SESSION['pending_otp_user_id'] = $user['id'];
                $_SESSION['pending_otp_next'] = $next;

                header('Location: verify-otp.php');
                exit;

            } else {

                // Remove OTP if email delivery fails.
                $delete->execute([$user['id']]);

                $error = 'Unable to send OTP email. Please try again.';
            }

        } else {
            $error = 'That email and password do not match. Check them and try again.';
        }
    }
}

$q = $next ? '?next=' . urlencode($next) : '';

renderHead('Log in', 'auth-body');
?>

<div class="auth-layout">
    <aside class="auth-brand">
        <a href="index.php" class="nav-brand"><img class="brand-mark" src="logo.svg" alt="" width="40" height="40"><span class="brand-name">Empire <span>Ventures</span></span></a>
        <div>
            <h2>Rice, supplied by the sack.</h2>
            <ul>
                <li>Order 5, 10, 25 or 50 kg packs</li>
                <li>Track every order in your dashboard</li>
                <li>Register your business for wholesale pricing</li>
            </ul>
        </div>
        <p><a href="index.php">Back to the home page</a></p>
    </aside>

    <main id="main" class="auth-panel">
        <div class="auth-card">
            <nav class="auth-tabs" aria-label="Account">
                <a href="login.php<?= $q ?>" aria-current="page">Log in</a>
                <a href="signup.php<?= $q ?>">Sign up</a>
            </nav>

            <h1>Welcome back</h1>
            <p class="section-lede">Log in to order rice and see your dashboard.</p>

          <?php if ($error) echo alertBox($error, 'error', 'form-error'); ?>

<form method="POST" action="login.php<?= $q ?>" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="next" value="<?= sanitize($next) ?>">

    <div class="field">
        <label for="email">Email address</label>
        <input class="input" type="email" id="email" name="email"
               value="<?= sanitize($email) ?>" required
               autocomplete="email" placeholder="name@example.com">
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password"
               required autocomplete="current-password">
        <label class="check">
            <input type="checkbox" data-show-password="password">
            Show password
        </label>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Log in</button>
</form>

            <p class="auth-footer-text">New to Empire Ventures? <a href="signup.php<?= $q ?>">Create an account</a></p>
        </div>
    </main>
</div>
<script src="app.js" defer></script>
</body>
</html>
