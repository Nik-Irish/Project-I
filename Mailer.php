<?php

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function sendOtpMail($email, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;
        $mail->Username = 'nikrishdulal01@gmail.com';
        $mail->Password = 'smbafhafuuyblbir';
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;
        $mail->Timeout = 20;
        $mail->setFrom(
            'nikrishdulal01@gmail.com',
            'IMS Nepal'
        );
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject =
            'IMS Nepal - Password Reset OTP';
        $safeOtp = htmlspecialchars(
            (string)$otp,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->Body = '
            <div style="
                font-family: Arial, sans-serif;
                max-width: 500px;
                margin: 0 auto;
                padding: 30px;
                background: #f8fafc;
                border-radius: 10px;
            ">

                <h2 style="
                    margin-bottom: 10px;
                    color: #0f172a;
                ">
                    IMS Nepal
                </h2>

                <p>
                    You requested a password reset.
                </p>

                <p>
                    Your OTP code is:
                </p>

                <div style="
                    font-size: 32px;
                    font-weight: bold;
                    letter-spacing: 8px;
                    padding: 20px;
                    text-align: center;
                    background: white;
                    border-radius: 8px;
                    margin: 20px 0;
                ">
                    ' . $safeOtp . '
                </div>

                <p>
                    This OTP is valid for
                    <strong>2 minutes</strong>.
                </p>

                <p style="
                    color: #64748b;
                    font-size: 13px;
                ">
                    If you did not request a password reset,
                    you can safely ignore this email.
                </p>

            </div>
        ';
        $mail->AltBody =
            "IMS Nepal\n\n" .
            "Your password reset OTP is: " .
            $otp .
            "\n\n" .
            "This OTP is valid for 2 minutes.";
        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log(
            'PHPMailer Error: ' .
            $mail->ErrorInfo
        );
        return false;
    }
}
?>