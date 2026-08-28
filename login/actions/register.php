<?php
/**
 * login/actions/register.php — POST handler: create staff account.
 * Extracted verbatim from the original login.php (lines 112-239).
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

        $password =
            $_POST['password'] ?? '';

        $confirm =
            $_POST['confirm_password'] ?? '';
        if (
            $username === '' ||
            $password === '' ||
            $confirm === ''
        ) {

            $errorMessage =
                'All fields required.';

        } elseif (
            !preg_match(
                '/^[a-zA-Z0-9]{3,15}$/',
                $username
            )
        ) {

            $errorMessage =
                'Username: 3-15 chars, letters & numbers.';

        } elseif (
            $email !== '' &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errorMessage =
                'Enter a valid email address.';

        } elseif (
            !preg_match(
                $passwordRules,
                $password
            )
        ) {

            $errorMessage =
                'Password: 8+ chars, uppercase, lowercase, number, special char.';

        } elseif (
            $password !== $confirm
        ) {

            $errorMessage =
                'Passwords do not match.';

        } else {
            if (
                $email !== ''
            ) {

                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM users
                     WHERE username=?
                     OR email=?'
                );

                $stmt->execute([
                    $username,
                    $email
                ]);

            } else {

                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM users
                     WHERE username=?'
                );

                $stmt->execute([
                    $username
                ]);
            }
            if (
                $stmt->fetchColumn() > 0
            ) {

                $errorMessage =
                    'Username or email already in use.';

            } else {
                $hash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );
                $pdo->prepare(
                    "INSERT INTO users
                    (
                        username,
                        email,
                        password_hash,
                        role
                    )
                    VALUES (?, ?, ?, 'staff')"
                )->execute([
                    $username,
                    $email !== ''
                        ? $email
                        : null,
                    $hash
                ]);
                $successMessage =
                    'Staff account created! You can now log in.';

                $mode =
                    'login';
            }
        }
