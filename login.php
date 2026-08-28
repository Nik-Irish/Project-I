<?php
/**
 * login.php - Entry point: Login / Register / Forgot password.
 *
 * Thin controller (same layout as dashboard.php):
 *   login/bootstrap.php          session, Mailer, DB, mode & reset-stage setup
 *   login/actions/*.php          POST handlers (one per branch)
 *   login/views/login_page.php   full HTML page
 */

define('LOGIN_CONTROLLER', true);

require __DIR__ . '/login/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'register') {
        require __DIR__ . '/login/actions/register.php';
    } elseif ($mode === 'forgot' && $resetStage === 'email') {
        require __DIR__ . '/login/actions/forgot_email.php';
    } elseif ($mode === 'forgot' && $resetStage === 'otp') {
        require __DIR__ . '/login/actions/forgot_otp.php';
    } elseif ($mode === 'forgot' && $resetStage === 'newpass') {
        require __DIR__ . '/login/actions/forgot_newpass.php';
    } else {
        require __DIR__ . '/login/actions/authenticate.php';
    }
}

require __DIR__ . '/login/views/login_page.php';
