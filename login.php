<?php

session_start();

require_once __DIR__ . '/Mailer.php';

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


/*
|--------------------------------------------------------------------------
| PASSWORD RULES
|--------------------------------------------------------------------------
*/

$passwordRules =
    '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';


/*
|--------------------------------------------------------------------------
| RESTART PASSWORD RESET
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| RESET PASSWORD STAGE
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| When the user simply opens:
|
| login.php?action=forgot
|
| we ALWAYS start at the username/email screen.
|
*/

$resetStage = 'email';


/*
|--------------------------------------------------------------------------
| OPEN FORGOT PASSWORD PAGE
|--------------------------------------------------------------------------
|
| GET = user is opening the page.
|
| Clear any old reset session so that an old "newpass" stage
| cannot appear immediately.
|
*/

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


/*
|--------------------------------------------------------------------------
| CONTINUE EXISTING RESET PROCESS
|--------------------------------------------------------------------------
|
| POST = user is submitting one of the reset forms.
|
*/

if (
    $mode === 'forgot' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $resetStage =
        $_SESSION['reset_stage'] ?? 'email';
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| POST REQUESTS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    if (
        $mode === 'register'
    ) {

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


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

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


            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING USERNAME
            |--------------------------------------------------------------------------
            */

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


                /*
                |--------------------------------------------------------------------------
                | HASH PASSWORD
                |--------------------------------------------------------------------------
                */

                $hash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | CREATE STAFF ACCOUNT
                |--------------------------------------------------------------------------
                */

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
    }


    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD
    | STEP 1 - USERNAME + EMAIL
    |--------------------------------------------------------------------------
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'email'
    ) {

        $username =
            trim(
                $_POST['username'] ?? ''
            );

        $email =
            trim(
                $_POST['email'] ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATE USERNAME
        |--------------------------------------------------------------------------
        */

        if (
            $username === ''
        ) {

            $errorMessage =
                'Username is required.';


        /*
        |--------------------------------------------------------------------------
        | VALIDATE EMAIL
        |--------------------------------------------------------------------------
        */

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


            /*
            |--------------------------------------------------------------------------
            | FIND USER BY USERNAME ONLY
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | We DO NOT check:
            |
            | WHERE username=? AND email=?
            |
            | The email is completely independent.
            |
            */

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


            /*
            |--------------------------------------------------------------------------
            | USERNAME DOES NOT EXIST
            |--------------------------------------------------------------------------
            */

            if (
                !$user
            ) {

                $errorMessage =
                    'Username not found.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | GENERATE OTP
                |--------------------------------------------------------------------------
                */

                $otp =
                    random_int(
                        100000,
                        999999
                    );


                /*
                |--------------------------------------------------------------------------
                | HASH OTP
                |--------------------------------------------------------------------------
                */

                $otpHash =
                    password_hash(
                        (string)$otp,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | OTP EXPIRES IN 2 MINUTES
                |--------------------------------------------------------------------------
                */

                $expiresAt =
                    date(
                        'Y-m-d H:i:s',
                        time() + 120
                    );


                /*
                |--------------------------------------------------------------------------
                | DELETE OLD OTP FOR THIS EMAIL
                |--------------------------------------------------------------------------
                */

                $pdo->prepare(
                    'DELETE FROM otps
                     WHERE email=?'
                )->execute([
                    $email
                ]);


                /*
                |--------------------------------------------------------------------------
                | INSERT NEW OTP
                |--------------------------------------------------------------------------
                */

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


                /*
                |--------------------------------------------------------------------------
                | SEND OTP
                |--------------------------------------------------------------------------
                */

                if (
                    sendOtpMail(
                        $email,
                        $otp
                    )
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE USERNAME
                    |--------------------------------------------------------------------------
                    |
                    | This determines which account will eventually
                    | have its password changed.
                    |
                    */

                    $_SESSION[
                        'reset_username'
                    ] =
                        $username;


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE EMAIL
                    |--------------------------------------------------------------------------
                    |
                    | This is ONLY for OTP verification.
                    |
                    */

                    $_SESSION[
                        'reset_email'
                    ] =
                        $email;


                    /*
                    |--------------------------------------------------------------------------
                    | MOVE TO OTP STAGE
                    |--------------------------------------------------------------------------
                    */

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
    }


    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD
    | STEP 2 - VERIFY OTP
    |--------------------------------------------------------------------------
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'otp'
    ) {

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


        /*
        |--------------------------------------------------------------------------
        | CHECK RESET SESSION
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | CHECK OTP FORMAT
        |--------------------------------------------------------------------------
        */

        } elseif (
            !preg_match(
                '/^\d{6}$/',
                $otpInput
            )
        ) {

            $errorMessage =
                'Enter a valid 6-digit OTP.';


        } else {


            /*
            |--------------------------------------------------------------------------
            | FIND OTP
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | OTP DOES NOT EXIST OR EXPIRED
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | WRONG OTP
            |--------------------------------------------------------------------------
            */

            } elseif (
                !password_verify(
                    $otpInput,
                    $row['otp_hash']
                )
            ) {

                $errorMessage =
                    'Incorrect OTP.';


            /*
            |--------------------------------------------------------------------------
            | OTP CORRECT
            |--------------------------------------------------------------------------
            */

            } else {


                /*
                |--------------------------------------------------------------------------
                | DELETE USED OTP
                |--------------------------------------------------------------------------
                */

                $pdo->prepare(
                    'DELETE FROM otps
                     WHERE email=?'
                )->execute([
                    $email
                ]);


                /*
                |--------------------------------------------------------------------------
                | MOVE TO NEW PASSWORD
                |--------------------------------------------------------------------------
                */

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
    }


    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD
    | STEP 3 - NEW PASSWORD
    |--------------------------------------------------------------------------
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'newpass'
    ) {

        /*
        |--------------------------------------------------------------------------
        | GET RESET USERNAME
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | CHECK RESET SESSION
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | REQUIRED FIELDS
        |--------------------------------------------------------------------------
        */

        } elseif (
            $newPass === '' ||
            $confirm === ''
        ) {

            $errorMessage =
                'All fields required.';


        /*
        |--------------------------------------------------------------------------
        | PASSWORD RULES
        |--------------------------------------------------------------------------
        */

        } elseif (
            !preg_match(
                $passwordRules,
                $newPass
            )
        ) {

            $errorMessage =
                'Password: 8+ chars, uppercase, lowercase, number, special char.';


        /*
        |--------------------------------------------------------------------------
        | PASSWORD MATCH
        |--------------------------------------------------------------------------
        */

        } elseif (
            $newPass !== $confirm
        ) {

            $errorMessage =
                'Passwords do not match.';


        } else {


            /*
            |--------------------------------------------------------------------------
            | CHECK USER STILL EXISTS
            |--------------------------------------------------------------------------
            */

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


                /*
                |--------------------------------------------------------------------------
                | HASH NEW PASSWORD
                |--------------------------------------------------------------------------
                */

                $hash =
                    password_hash(
                        $newPass,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | UPDATE PASSWORD BY USERNAME ONLY
                |--------------------------------------------------------------------------
                |
                | Notice:
                |
                | email is NOT used here.
                |
                */

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


                /*
                |--------------------------------------------------------------------------
                | CLEAR RESET SESSION
                |--------------------------------------------------------------------------
                */

                unset(
                    $_SESSION['reset_username'],
                    $_SESSION['reset_email'],
                    $_SESSION['reset_stage']
                );


                /*
                |--------------------------------------------------------------------------
                | RETURN TO LOGIN
                |--------------------------------------------------------------------------
                */

                $successMessage =
                    'Password reset successfully! You can now log in.';


                $mode =
                    'login';


                $resetStage =
                    'email';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    else {

        $username =
            trim(
                $_POST['username'] ?? ''
            );

        $password =
            $_POST['password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | VALIDATE LOGIN
        |--------------------------------------------------------------------------
        */

        if (
            $username === '' ||
            $password === ''
        ) {

            $errorMessage =
                'Both fields required.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | FIND USER
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | VERIFY PASSWORD
            |--------------------------------------------------------------------------
            */

            if (
                $user &&
                password_verify(
                    $password,
                    $user['password_hash']
                )
            ) {


                /*
                |--------------------------------------------------------------------------
                | LOGIN SESSION
                |--------------------------------------------------------------------------
                */

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


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ' .
                    (
                        $user['role'] === 'admin'
                            ? 'dashboard.php'
                            : 'staff_dashboard.php'
                    )
                );

                exit;

            } else {

                $errorMessage =
                    'Invalid username or password.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| PAGE TITLES
|--------------------------------------------------------------------------
*/

$pageTitles = [

    'login' =>
        'Login',

    'register' =>
        'Create Account',

    'forgot' =>
        'Reset Password'
];


$pageTitle =
    $pageTitles[$mode];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php
        echo htmlspecialchars(
            $pageTitle
        );
        ?>
        | IMS Nepal
    </title>

    <link
        rel="stylesheet"
        href="login-style.css"
    >

</head>


<body>


<div class="lc">


    <!-- =========================================================
         TITLE
    ========================================================== -->

    <h2>

        <?php
        echo htmlspecialchars(
            $pageTitle
        );
        ?>

    </h2>


    <!-- =========================================================
         SUBTITLE
    ========================================================== -->

    <p class="sub">


        <?php if (
            $mode === 'register'
        ): ?>

            Create your account


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'email'
        ): ?>

            Enter your username and email for OTP


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'otp'
        ): ?>

            Enter the OTP sent to your email


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'newpass'
        ): ?>

            Set your new password


        <?php else: ?>

            Welcome to IMS Nepal

        <?php endif; ?>


    </p>


    <!-- =========================================================
         ERROR
    ========================================================== -->

    <?php if (
        $errorMessage
    ): ?>

        <div class="msg me">

            <?php
            echo htmlspecialchars(
                $errorMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         SUCCESS
    ========================================================== -->

    <?php if (
        $successMessage
    ): ?>

        <div class="msg ms">

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         FORM
    ========================================================== -->

    <form
        action="login.php?action=<?php
            echo htmlspecialchars(
                $mode
            );
        ?>"
        method="POST"
        autocomplete="off"
    >


        <?php if (
            $mode === 'register'
        ): ?>


            <!-- =================================================
                 REGISTER
            ================================================== -->

            <div class="fg">

                <label>

                    Username

                    <span class="req">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="username"
                    required
                    maxlength="15"
                    value="<?php
                    echo htmlspecialchars(
                        $_POST['username'] ?? ''
                    );
                    ?>"
                >

            </div>


            <div class="fg">

                <label>
                    Email
                </label>


                <input
                    type="email"
                    name="email"
                    value="<?php
                    echo htmlspecialchars(
                        $_POST['email'] ?? ''
                    );
                    ?>"
                >

            </div>


            <div class="fg">

                <label>

                    Password

                    <span class="req">
                        *
                    </span>

                </label>


                <div class="pw-input-wrap">

                    <input
                        type="password"
                        id="password-register"
                        name="password"
                        required
                    >


                    <button
                        type="button"
                        class="pw-toggle"
                        data-target="password-register"
                    >
                        Show
                    </button>

                </div>


                <small class="ph">

                    8+ chars, uppercase,
                    lowercase, number,
                    special char.

                </small>

            </div>


            <div class="fg">

                <label>

                    Confirm Password

                    <span class="req">
                        *
                    </span>

                </label>


                <div class="pw-input-wrap">

                    <input
                        type="password"
                        id="confirm-register"
                        name="confirm_password"
                        required
                    >


                    <button
                        type="button"
                        class="pw-toggle"
                        data-target="confirm-register"
                    >
                        Show
                    </button>

                </div>

            </div>


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'email'
        ): ?>


            <!-- =================================================
                 FORGOT PASSWORD
                 STEP 1
            ================================================== -->

            <div class="fg">

                <label>

                    Username

                    <span class="req">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="username"
                    required
                    maxlength="15"
                    autocomplete="username"
                    value="<?php
                    echo htmlspecialchars(
                        $_POST['username'] ?? ''
                    );
                    ?>"
                >


                <small class="ph">

                    Enter the username whose
                    password you want to reset.

                </small>

            </div>


            <div class="fg">

                <label>

                    Email for OTP

                    <span class="req">
                        *
                    </span>

                </label>


                <input
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    value="<?php
                    echo htmlspecialchars(
                        $_POST['email'] ?? ''
                    );
                    ?>"
                >


                <small class="ph">

                    This email is only used to
                    receive the OTP. It does not
                    need to match the account email.

                </small>

            </div>


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'otp'
        ): ?>


            <!-- =================================================
                 OTP
                 STEP 2
            ================================================== -->

            <div class="fg">

                <label>

                    OTP Code

                    <span class="req">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="otp"
                    required
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="6-digit code"
                >


                <small class="ph">

                    OTP sent to

                    <?php
                    echo htmlspecialchars(
                        $_SESSION[
                            'reset_email'
                        ] ?? ''
                    );
                    ?>.

                    Valid for 2 minutes.

                </small>

            </div>


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'newpass'
        ): ?>


            <!-- =================================================
                 NEW PASSWORD
                 STEP 3
            ================================================== -->

            <div class="fg">

                <label>

                    New Password

                    <span class="req">
                        *
                    </span>

                </label>


                <div class="pw-input-wrap">

                    <input
                        type="password"
                        id="password-reset"
                        name="new_password"
                        required
                    >


                    <button
                        type="button"
                        class="pw-toggle"
                        data-target="password-reset"
                    >
                        Show
                    </button>

                </div>


                <small class="ph">

                    8+ chars, uppercase,
                    lowercase, number,
                    special char.

                </small>

            </div>


            <div class="fg">

                <label>

                    Confirm Password

                    <span class="req">
                        *
                    </span>

                </label>


                <div class="pw-input-wrap">

                    <input
                        type="password"
                        id="confirm-reset"
                        name="confirm_password"
                        required
                    >


                    <button
                        type="button"
                        class="pw-toggle"
                        data-target="confirm-reset"
                    >
                        Show
                    </button>

                </div>

            </div>


        <?php else: ?>


            <!-- =================================================
                 LOGIN
            ================================================== -->

            <div class="fg">

                <label>

                    Username

                    <span class="req">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="username"
                    required
                    autocomplete="username"
                    value="<?php
                    echo htmlspecialchars(
                        $_POST['username'] ?? ''
                    );
                    ?>"
                >

            </div>


            <div class="fg">

                <label>

                    Password

                    <span class="req">
                        *
                    </span>

                </label>


                <div class="pw-input-wrap">

                    <input
                        type="password"
                        id="password-login"
                        name="password"
                        required
                        autocomplete="current-password"
                    >


                    <button
                        type="button"
                        class="pw-toggle"
                        data-target="password-login"
                    >
                        Show
                    </button>

                </div>

            </div>


        <?php endif; ?>


        <!-- =========================================================
             BUTTONS
        ========================================================== -->

        <div class="fa">


            <?php if (
                $mode === 'register'
            ): ?>


                <a
                    href="login.php?action=login"
                    class="sl"
                >
                    Back to Login
                </a>


                <button
                    type="submit"
                    class="bs"
                >
                    Create Account
                </button>


            <?php elseif (
                $mode === 'forgot' &&
                $resetStage === 'email'
            ): ?>


                <a
                    href="login.php?action=login"
                    class="sl"
                >
                    Back to Login
                </a>


                <button
                    type="submit"
                    class="bs"
                >
                    Send OTP
                </button>


            <?php elseif (
                $mode === 'forgot' &&
                $resetStage === 'otp'
            ): ?>


                <a
                    href="login.php?action=forgot&restart=1"
                    class="sl"
                >
                    Back
                </a>


                <button
                    type="submit"
                    class="bs"
                >
                    Verify OTP
                </button>


            <?php elseif (
                $mode === 'forgot' &&
                $resetStage === 'newpass'
            ): ?>


                <a
                    href="login.php?action=login"
                    class="sl"
                >
                    Back to Login
                </a>


                <button
                    type="submit"
                    class="bs"
                >
                    Reset Password
                </button>


            <?php else: ?>


                <a
                    href="login.php?action=register"
                    class="sl"
                >
                    Create account
                </a>


                <button
                    type="submit"
                    class="bs"
                >
                    Log In
                </button>


            <?php endif; ?>


        </div>


        <!-- =========================================================
             FORGOT PASSWORD LINK
        ========================================================== -->

        <?php if (
            $mode === 'login'
        ): ?>

            <div class="fr">

                <a
                    href="login.php?action=forgot"
                    class="fl"
                >
                    Forgot password?
                </a>

            </div>

        <?php endif; ?>


    </form>


</div>


<!-- ===============================================================
     SHOW / HIDE PASSWORD
================================================================ -->

<script>

document
    .querySelectorAll('.pw-toggle')
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const target =
                    document.getElementById(
                        button.dataset.target
                    );

                if (!target) {
                    return;
                }


                if (
                    target.type === 'password'
                ) {

                    target.type =
                        'text';

                    button.textContent =
                        'Hide';

                } else {

                    target.type =
                        'password';

                    button.textContent =
                        'Show';
                }

            }
        );

    });

</script>


</body>

</html>