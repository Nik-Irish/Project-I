<?php
/**
 * login/actions/authenticate.php — POST handler: login — verify credentials, redirect by role.
 * Extracted verbatim from the original login.php (lines 589-656).
 */
defined('LOGIN_CONTROLLER') || exit;

        $username =
            trim(
                $_POST['username'] ?? ''
            );

        $password =
            $_POST['password'] ?? '';
        if (
            $username === '' ||
            $password === ''
        ) {

            $errorMessage =
                'Both fields required.';

        } else {
            $stmt =
                $pdo->prepare(
                    'SELECT *
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
                $user &&
                password_verify(
                    $password,
                    $user['password_hash']
                )
            ) {
                $_SESSION[
                    'user_id'
                ] =
                    (int)$user['id'];

                $_SESSION[
                    'username'
                ] =
                    $user['username'];

                $_SESSION[
                    'role'
                ] =
                    $user['role'];
                header(
                    'Location: ' .
                    (
                        $user['role'] === 'admin'
                            ? 'dashboard.php'
                            : 'Staff_dashboard.php'
                    )
                );

                exit;

            } else {

                $errorMessage =
                    'Invalid username or password.';
            }
        }
