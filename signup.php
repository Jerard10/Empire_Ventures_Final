<?php
require_once 'db.php';

$next = safeNext($_GET['next'] ?? $_POST['next'] ?? '');
if (isLoggedIn()) { header('Location: ' . ($next ?: 'dashboard.php')); exit; }

$error = '';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address, like name@example.com.';
    } elseif (strlen($password) < 8) {
        $error = 'Use a password with at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match. Type them again.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists. Log in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
            $insert->execute([$fullName, $email, $hash]);
            session_regenerate_id(true);
            $_SESSION['user_id']    = $pdo->lastInsertId();
            $_SESSION['user_name']  = $fullName;
            $_SESSION['user_email'] = $email;
            $_SESSION['role']       = 'customer';
            flash('Account created. Welcome to Empire Ventures!');
            header('Location: ' . ($next ?: 'dashboard.php'));
            exit;
        }
    }
}
$q = $next ? '?next=' . urlencode($next) : '';
renderHead('Sign up', 'auth-body');
?>
<div class="auth-layout">
    <aside class="auth-brand">
        <a href="index.php" class="nav-brand"><img class="brand-mark" src="logo.svg" alt="" width="40" height="40"><span class="brand-name">Empire <span>Ventures</span></span></a>
        <div>
            <h2>Create your account.</h2>
            <ul>
                <li>Order rice in 5, 10, 25 or 50 kg packs</li>
                <li>Keep every order in one place</li>
                <li>Stores and restaurants can apply for wholesale</li>
            </ul>
        </div>
        <p><a href="index.php">Back to the home page</a></p>
    </aside>

    <main id="main" class="auth-panel">
        <div class="auth-card">
            <nav class="auth-tabs" aria-label="Account">
                <a href="login.php<?= $q ?>">Log in</a>
                <a href="signup.php<?= $q ?>" aria-current="page">Sign up</a>
            </nav>

            <h1>Sign up</h1>
            <p class="section-lede">It takes under a minute.</p>

            <?php if ($error) echo alertBox($error, 'error', 'form-error'); ?>

            <form method="POST" action="signup.php<?= $q ?>" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="next" value="<?= sanitize($next) ?>">

                <div class="field">
                    <label for="full_name">Full name</label>
                    <input class="input" type="text" id="full_name" name="full_name" value="<?= sanitize($fullName) ?>" required autocomplete="name" placeholder="e.g. Juan Dela Cruz" <?= $error ? '' : 'autofocus' ?>>
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" value="<?= sanitize($email) ?>" required autocomplete="email" placeholder="name@example.com">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" required autocomplete="new-password" aria-describedby="pw-hint">
                    <p class="hint" id="pw-hint">At least 8 characters.</p>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm password</label>
                    <input class="input" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    <label class="check"><input type="checkbox" data-show-password="password confirm_password"> Show passwords</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p class="auth-footer-text">Already have an account? <a href="login.php<?= $q ?>">Log in</a></p>
        </div>
    </main>
</div>
<script src="app.js" defer></script>
</body>
</html>
