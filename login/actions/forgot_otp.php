<?php
/**
 * login/actions/forgot_otp.php — POST handler: forgot step 2 — verify OTP.
 * Extracted verbatim from the original login.php (lines 371-475).
 */
defined('LOGIN_CONTROLLER') || exit;

        $username =
            $_SESSION[
                'reset_username'
            ] ?? '';

        $email =
            $_SESSION[
                'reset_email'
            ] ?? '';

        $otpInput =
            trim(
                $_POST['otp'] ?? ''
            );
        if (
            $username === '' ||
            $email === ''
        ) {

            $errorMessage =
                'Password reset session expired. Start again.';
            unset(
                $_SESSION['reset_username'],
                $_SESSION['reset_email'],
                $_SESSION['reset_stage']
            );
            $resetStage =
                'email';
        } elseif (
            !preg_match(
                '/^\d{6}$/',
                $otpInput
            )
        ) {

            $errorMessage =
                'Enter a valid 6-digit OTP.';
        } else {
            $stmt =
                $pdo->prepare(
                    'SELECT *
                     FROM otps
                     WHERE email=?
                     ORDER BY id DESC
                     LIMIT 1'
                );

            $stmt->execute([
                $email
            ]);

            $row =
                $stmt->fetch();
            if (
                !$row ||
                strtotime(
                    $row['expires_at']
                ) < time()
            ) {

                $pdo->prepare(
                    'DELETE FROM otps
                     WHERE email=?'
                )->execute([
                    $email
                ]);
                $errorMessage =
                    'OTP expired. Request a new one.';
                unset(
                    $_SESSION['reset_username'],
                    $_SESSION['reset_email']
                );
                $_SESSION[
                    'reset_stage'
                ] =
                    'email';
                $resetStage =
                    'email';
            } elseif (
                !password_verify(
                    $otpInput,
                    $row['otp_hash']
                )
            ) {

                $errorMessage =
                    'Incorrect OTP.';
            } else {
                $pdo->prepare(
                    'DELETE FROM otps
                     WHERE email=?'
                )->execute([
                    $email
                ]);
                $_SESSION[
                    'reset_stage'
                ] =
                    'newpass';
                $resetStage =
                    'newpass';
                $successMessage =
                    'OTP verified. Set your new password.';
            }
        }
