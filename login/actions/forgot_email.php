<?php
/**
 * login/actions/forgot_email.php — POST handler: forgot step 1 — username + email, send OTP.
 * Extracted verbatim from the original login.php (lines 245-365).
 */
defined('LOGIN_CONTROLLER') || exit;

        $username =
            trim(
                $_POST['username'] ?? ''
            );

        $email =
            trim(
                $_POST['email'] ?? ''
            );
        if (
            $username === ''
        ) {

            $errorMessage =
                'Username is required.';
        } elseif (
            $email === ''
        ) {

            $errorMessage =
                'Email is required.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errorMessage =
                'Enter a valid email address.';

        } else {
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    username
                 FROM users
                 WHERE username=?
                 LIMIT 1'
            );

            $stmt->execute([
                $username
            ]);

            $user =
                $stmt->fetch();
            if (
                !$user
            ) {

                $errorMessage =
                    'Username not found.';

            } else {
                $otp =
                    random_int(
                        100000,
                        999999
                    );
                $otpHash =
                    password_hash(
                        (string)$otp,
                        PASSWORD_DEFAULT
                    );
                $expiresAt =
                    date(
                        'Y-m-d H:i:s',
                        time() + 120
                    );
                $pdo->prepare(
                    'DELETE FROM otps
                     WHERE email=?'
                )->execute([
                    $email
                ]);
                $pdo->prepare(
                    'INSERT INTO otps
                    (
                        email,
                        otp_hash,
                        expires_at
                    )
                    VALUES (?, ?, ?)'
                )->execute([
                    $email,
                    $otpHash,
                    $expiresAt
                ]);
                if (
                    sendOtpMail(
                        $email,
                        $otp
                    )
                ) {
                    $_SESSION[
                        'reset_username'
                    ] =
                        $username;
                    $_SESSION[
                        'reset_email'
                    ] =
                        $email;
                    $_SESSION[
                        'reset_stage'
                    ] =
                        'otp';
                    $resetStage =
                        'otp';
                    $successMessage =
                        'OTP sent successfully. Check your email.';

                } else {

                    $errorMessage =
                        'Could not send the email. Please check your SMTP settings.';
                }
            }
        }
