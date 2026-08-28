<?php
/**
 * login/actions/forgot_newpass.php — POST handler: forgot step 3 — set new password.
 * Extracted verbatim from the original login.php (lines 481-585).
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
        $newPass =
            $_POST[
                'new_password'
            ] ?? '';

        $confirm =
            $_POST[
                'confirm_password'
            ] ?? '';
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
            $newPass === '' ||
            $confirm === ''
        ) {

            $errorMessage =
                'All fields required.';
        } elseif (
            !preg_match(
                $passwordRules,
                $newPass
            )
        ) {

            $errorMessage =
                'Password: 8+ chars, uppercase, lowercase, number, special char.';
        } elseif (
            $newPass !== $confirm
        ) {

            $errorMessage =
                'Passwords do not match.';
        } else {
            $stmt =
                $pdo->prepare(
                    'SELECT id
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
                    'User account no longer exists.';

            } else {
                $hash =
                    password_hash(
                        $newPass,
                        PASSWORD_DEFAULT
                    );
                $stmt =
                    $pdo->prepare(
                        'UPDATE users
                         SET password_hash=?
                         WHERE username=?'
                    );
                $stmt->execute([
                    $hash,
                    $username
                ]);
                unset(
                    $_SESSION['reset_username'],
                    $_SESSION['reset_email'],
                    $_SESSION['reset_stage']
                );
                $successMessage =
                    'Password reset successfully! You can now log in.';
                $mode =
                    'login';
                $resetStage =
                    'email';
            }
        }
