<?php

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/*
|--------------------------------------------------------------------------
| SEND OTP EMAIL
|--------------------------------------------------------------------------
*/

function sendOtpMail($email, $otp)
{
    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Put your Gmail address here.
        |
        */

        $mail->Username = 'nikrishdulal01@gmail.com';


        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Put your NEW Gmail App Password here.
        |
        | Do NOT use your normal Gmail password.
        |
        */

        $mail->Password = 'ildh nnid yehh wesy';


        /*
        |--------------------------------------------------------------------------
        | Gmail TLS
        |--------------------------------------------------------------------------
        */

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;


        /*
        |--------------------------------------------------------------------------
        | Optional SMTP timeout
        |--------------------------------------------------------------------------
        */

        $mail->Timeout = 20;


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
        |
        | This is the email entered by the user.
        |
        | It does NOT need to match the email in users table.
        |
        */

        $mail->addAddress($email);


        /*
        |--------------------------------------------------------------------------
        | Email format
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);


        /*
        |--------------------------------------------------------------------------
        | Subject
        |--------------------------------------------------------------------------
        */

        $mail->Subject =
            'IMS Nepal(Nirman) - Password Reset OTP';


        /*
        |--------------------------------------------------------------------------
        | HTML BODY
        |--------------------------------------------------------------------------
        */

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
                    IMS Nepal(Nirman)
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


        /*
        |--------------------------------------------------------------------------
        | Plain text version
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =
            "IMS Nepal(Nirman)\n\n" .
            "Your password reset OTP is: " .
            $otp .
            "\n\n" .
            "This OTP is valid for 2 minutes.";


        /*
        |--------------------------------------------------------------------------
        | SEND
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | LOG REAL PHPMailer ERROR
        |--------------------------------------------------------------------------
        */

        error_log(
            'PHPMailer Error: ' .
            $mail->ErrorInfo
        );


        /*
        |--------------------------------------------------------------------------
        | Return false to login.php
        |--------------------------------------------------------------------------
        */

        return false;
    }
}
?>