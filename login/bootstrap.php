<?php
/**
 * login/bootstrap.php — session, Mailer, DB connection, mode
 * selection and reset-stage setup for login.php.
 * Body extracted verbatim from the original login.php (lines 1-105).
 */
defined('LOGIN_CONTROLLER') || exit;



session_start();

require_once __DIR__ . '/../Mailer.php';

$errorMessage = '';
$successMessage = '';

$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'ims';

$allowedModes = [
    'login',
    'register',
    'forgot'
];

$mode = in_array(
    $_GET['action'] ?? '',
    $allowedModes,
    true
)
    ? $_GET['action']
    : 'login';
$passwordRules =
    '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
if (
    $mode === 'forgot' &&
    isset($_GET['restart'])
) {

    unset(
        $_SESSION['reset_username'],
        $_SESSION['reset_email'],
        $_SESSION['reset_stage']
    );
}
$resetStage = 'email';
if (
    $mode === 'forgot' &&
    $_SERVER['REQUEST_METHOD'] === 'GET'
) {

    unset(
        $_SESSION['reset_username'],
        $_SESSION['reset_email'],
        $_SESSION['reset_stage']
    );

    $resetStage = 'email';
}
if (
    $mode === 'forgot' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $resetStage =
        $_SESSION['reset_stage'] ?? 'email';
}
try {

    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec(
        "USE `$dbName`"
    );

} catch (PDOException $e) {

    die(
        '<div style="
            text-align:center;
            padding:3rem;
            color:#fca5a5;
            font-family:sans-serif;
            background:#0f172a;
        ">
            <h2>Database Error</h2>
            <p>' .
            htmlspecialchars(
                $e->getMessage()
            ) .
            '</p>
            <p>
                Make sure MySQL is running and
                the IMS database exists.
            </p>
        </div>'
    );
}
