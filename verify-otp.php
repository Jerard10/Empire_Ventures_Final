
<?php
require_once 'db.php';

if (empty($_SESSION['pending_otp_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    checkCsrf();

    $otp = trim($_POST['otp'] ?? '');
    $userId = $_SESSION['pending_otp_user_id'];

    if (!preg_match('/^[0-9]{6}$/', $otp)) {

        $error = 'Please enter a valid six-digit OTP.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT * FROM login_otps
             WHERE user_id = ?
             ORDER BY id DESC LIMIT 1"
        );

        $stmt->execute([$userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {

            $error = 'OTP not found. Please log in again.';

        } elseif (strtotime($record['expires_at']) < time()) {

            $error = 'Your OTP has expired. Please log in again.';

        } elseif ((int)$record['attempts'] >= 5) {

            $error = 'Too many incorrect attempts. Please log in again.';

        } elseif (!password_verify($otp, $record['otp_hash'])) {

            $update = $pdo->prepare(
                "UPDATE login_otps
                 SET attempts = attempts + 1
                 WHERE id = ?"
            );
            $update->execute([$record['id']]);

            $error = 'Incorrect OTP. Please try again.';

        } else {

            // OTP is correct. Retrieve the user.
            $userStmt = $pdo->prepare(
                "SELECT id, full_name, email, role
                 FROM users WHERE id = ? LIMIT 1"
            );

            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                exit('User account not found.');
            }

            // Delete OTP so it cannot be reused.
            $delete = $pdo->prepare(
                "DELETE FROM login_otps WHERE user_id = ?"
            );
            $delete->execute([$userId]);

            $next = safeNext($_SESSION['pending_otp_next'] ?? '');

            unset(
                $_SESSION['pending_otp_user_id'],
                $_SESSION['pending_otp_next']
            );

            // Create the same session as your original login.
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            header(
                'Location: ' . (
                    $next ?: (
                        $user['role'] === 'admin'
                        ? 'admin.php'
                        : 'dashboard.php'
                    )
                )
            );
            exit;
        }
    }
}

renderHead('Verify OTP', 'auth-body');
?>

<div class="auth-layout">
    <main id="main" class="auth-panel">
        <div class="auth-card">

            <h1>Verify your email</h1>

            <p class="section-lede">
                Enter the six-digit OTP sent to your email address.
            </p>

            <?php
            if ($error) {
                echo alertBox($error, 'error', 'form-error');
            }
            ?>

            <form method="POST" action="verify-otp.php" novalidate>

                <?= csrfField() ?>

                <div class="field">
                    <label for="otp">Email verification code</label>

                    <input
                        class="input"
                        type="text"
                        id="otp"
                        name="otp"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        placeholder="Enter 6-digit OTP"
                        autocomplete="one-time-code"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="btn btn-primary btn-block"
                >
                    Verify OTP
                </button>

            </form>

            <p class="auth-footer-text">
                <a href="login.php">Back to login</a>
            </p>

        </div>
    </main>
</div>

<script src="app.js" defer></script>
</body>
</html>
