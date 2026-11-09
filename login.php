<?php
require_once 'db.php';

$next = safeNext($_GET['next'] ?? $_POST['next'] ?? '');
if (isLoggedIn()) { header('Location: ' . ($next ?: 'dashboard.php')); exit; }

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter both your email and your password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role']       = $user['role'];
            header('Location: ' . ($next ?: ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php')));
            exit;
        }
        $error = 'That email and password do not match. Check them and try again.';
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
                    <input class="input" type="email" id="email" name="email" value="<?= sanitize($email) ?>" required autocomplete="email" placeholder="name@example.com"
                           <?= $error ? 'aria-invalid="true" aria-describedby="form-error"' : '' ?> <?= $error ? '' : 'autofocus' ?>>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" required autocomplete="current-password"
                           <?= $error ? 'aria-invalid="true" aria-describedby="form-error" autofocus' : '' ?>>
                    <label class="check"><input type="checkbox" data-show-password="password"> Show password</label>
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
