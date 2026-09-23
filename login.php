<?php
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