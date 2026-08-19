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

        /*
        |--------------------------------------------------------------------------
        | Gmail account
        |--------------------------------------------------------------------------
        */

        $mail->Username = 'nikrishdulal01@gmail.com';

        /*
        |--------------------------------------------------------------------------
        | Gmail APP PASSWORD
        |--------------------------------------------------------------------------
        |
        | This must be a Gmail App Password.
        | It is NOT your normal Gmail password.
        |
        */

        $mail->Password = 'ildh nnid yehh wesy';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            'nikrishdulal01@gmail.com',
            'IMS Nepal(Nirman)'
        );

        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        */

        $mail->addAddress($email);

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject =
            'IMS Nepal(Nirman) - OTP Code';

        $mail->Body =
            '<div style="font-family:Arial,sans-serif;">' .
            '<h2>Your OTP Code</h2>' .
            '<p style="font-size:24px;font-weight:bold;">' .
            htmlspecialchars((string)$otp) .
            '</p>' .
            '<p>This OTP is valid for 2 minutes.</p>' .
            '<p>From Naran And Nikrish</p>' .
            '</div>';

        $mail->AltBody =
            "Your OTP is $otp. This code is valid for 2 minutes.";

        /*
        |--------------------------------------------------------------------------
        | Send
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;

    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | SHOW ACTUAL ERROR
        |--------------------------------------------------------------------------
        |
        | Temporarily show the PHPMailer error while testing.
        |
        */

        error_log(
            'PHPMailer Error: ' . $mail->ErrorInfo
        );

        return false;
    }
}
?>