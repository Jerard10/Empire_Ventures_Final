
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendLoginOTP($recipientEmail, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        // TEMPORARY SMTP DEBUGGING
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP DEBUG [$level]: $str");
        };

        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'YOUR_ACTUAL_GMAIL@gmail.com';
        $mail->Password   = 'YOUR_ACTUAL_APP_PASSWORD';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 20;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            'YOUR_ACTUAL_GMAIL@gmail.com',
            'Empire Ventures'
        );

        $mail->addAddress($recipientEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Empire Ventures Login Verification';

        $mail->Body = "
            <h2>Empire Ventures</h2>
            <p>Your login verification code is:</p>
            <h1>$otp</h1>
            <p>This code expires in 5 minutes.</p>
        ";

        $mail->AltBody = "Your OTP is $otp";

        $mail->send();

        error_log('OTP EMAIL SENT SUCCESSFULLY');

        return true;

    } catch (Exception $e) {

        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        error_log('Exception Error: ' . $e->getMessage());

        return false;
    }
}
