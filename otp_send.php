
<?php
session_start();

require_once 'db.php';
require_once 'config/mail.php';

// Your db.php must provide a PDO connection named $pdo.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    exit('Please enter your email and password.');
}

// Find user using your existing users table.
$stmt = $pdo->prepare(
    "SELECT id, full_name, email, password, role
     FROM users
     WHERE email = ?
     LIMIT 1"
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    exit('Invalid email or password.');
}

// Generate a six-digit OTP.
$otp = (string) random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_DEFAULT);

$expiresAt = date('Y-m-d H:i:s', time() + 300);

// Remove previous OTPs for this user.
$delete = $pdo->prepare(
    "DELETE FROM login_otps WHERE user_id = ?"
);
$delete->execute([$user['id']]);

// Save hashed OTP.
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

// Send OTP email.
if (!sendLoginOTP($user['email'], $otp)) {
    $delete = $pdo->prepare(
        "DELETE FROM login_otps WHERE user_id = ?"
    );
    $delete->execute([$user['id']]);

    exit('Unable to send OTP email. Please check your email settings.');
}

// Store pending login ID, not the authenticated session.
$_SESSION['pending_otp_user_id'] = $user['id'];

header('Location: verify-otp.php');
exit;
