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

$allowedModes = ['login', 'register', 'forgot'];

$mode = in_array($_GET['action'] ?? '', $allowedModes, true)
    ? $_GET['action']
    : 'login';

$passwordRules = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';


/*
|--------------------------------------------------------------------------
| RESTART PASSWORD RESET
|--------------------------------------------------------------------------
*/

if ($mode === 'forgot' && isset($_GET['restart'])) {

    unset(
        $_SESSION['reset_username'],
        $_SESSION['reset_email'],
        $_SESSION['reset_stage']
    );
}


/*
|--------------------------------------------------------------------------
| FORGOT PASSWORD STAGE
|--------------------------------------------------------------------------
|
| email   = username + email form
| otp     = OTP verification
| newpass = new password form
|
*/

$resetStage = $_SESSION['reset_stage'] ?? 'email';

if ($mode !== 'forgot') {
    $resetStage = 'email';
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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("USE `$dbName`");

} catch (PDOException $e) {

    die(
        '<div style="
            text-align:center;
            padding:3rem;
            color:#fca5a5;
            font-family:sans-serif;
            background:#0f172a
        ">
            <h2>Database Error</h2>
            <p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>
            <p>
                Run
                <a
                    href="install.php"
                    style="color:#38bdf8"
                >
                    install.php
                </a>
                first.
            </p>
        </div>'
    );
}


/*
|--------------------------------------------------------------------------
| POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    if ($mode === 'register') {

        $username = trim(
            $_POST['username'] ?? ''
        );

        $email = trim(
            $_POST['email'] ?? ''
        );

        $password = $_POST['password'] ?? '';

        $confirm = $_POST['confirm_password'] ?? '';


        if (
            $username === '' ||
            $email === '' ||
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
            | Check username OR email already exists
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM users
                 WHERE username=? OR email=?'
            );

            $stmt->execute([
                $username,
                $email
            ]);

            if ($stmt->fetchColumn() > 0) {

                $errorMessage =
                    'Username or email already in use.';

            } else {

                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $pdo->prepare(
                    "INSERT INTO users
                    (username, email, password_hash, role)
                    VALUES (?, ?, ?, 'staff')"
                )->execute([
                    $username,
                    $email,
                    $hash
                ]);

                $successMessage =
                    'Staff account created! You can now log in.';

                $mode = 'login';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD - STEP 1
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Username is checked ONLY against the users table.
    |
    | The email is NOT matched with the database email.
    |
    | The email entered by the user is ONLY used to receive OTP.
    |
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'email'
    ) {

        $username = trim(
            $_POST['username'] ?? ''
        );

        $email = trim(
            $_POST['email'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Validate username
        |--------------------------------------------------------------------------
        */

        if ($username === '') {

            $errorMessage =
                'Username is required.';

        /*
        |--------------------------------------------------------------------------
        | Validate email
        |--------------------------------------------------------------------------
        */

        } elseif ($email === '') {

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
            | CHECK USERNAME ONLY
            |--------------------------------------------------------------------------
            |
            | We intentionally DO NOT use:
            |
            | WHERE username=? AND email=?
            |
            | The email is completely independent.
            |
            */

            $stmt = $pdo->prepare(
                'SELECT id, username
                 FROM users
                 WHERE username=?
                 LIMIT 1'
            );

            $stmt->execute([
                $username
            ]);

            $user = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Username does not exist
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $errorMessage =
                    'Username not found.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | USERNAME EXISTS
                |--------------------------------------------------------------------------
                |
                | Now generate OTP.
                |
                | The OTP will be sent to the email entered by the user.
                |
                */

                $otp = random_int(
                    100000,
                    999999
                );


                /*
                |--------------------------------------------------------------------------
                | Hash OTP
                |--------------------------------------------------------------------------
                */

                $otpHash = password_hash(
                    (string)$otp,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | OTP expires in 2 minutes
                |--------------------------------------------------------------------------
                */

                $expiresAt = date(
                    'Y-m-d H:i:s',
                    time() + 120
                );


                /*
                |--------------------------------------------------------------------------
                | Delete previous OTP for this email
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
                | Save new OTP
                |--------------------------------------------------------------------------
                */

                $pdo->prepare(
                    'INSERT INTO otps
                    (email, otp_hash, expires_at)
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
                    | Store username separately
                    |--------------------------------------------------------------------------
                    |
                    | This username determines which account gets reset.
                    |
                    */

                    $_SESSION['reset_username'] =
                        $username;


                    /*
                    |--------------------------------------------------------------------------
                    | Store email only for OTP verification
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['reset_email'] =
                        $email;


                    $_SESSION['reset_stage'] =
                        'otp';


                    $resetStage = 'otp';


                    $successMessage =
                        'OTP sent to the email address you provided. It expires in 2 minutes.';

                } else {

                    $errorMessage =
                        'Could not send the email. Try again.';
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD - STEP 2
    |--------------------------------------------------------------------------
    |
    | VERIFY OTP
    |--------------------------------------------------------------------------
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'otp'
    ) {

        $username =
            $_SESSION['reset_username'] ?? '';

        $email =
            $_SESSION['reset_email'] ?? '';

        $otpInput =
            trim($_POST['otp'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Check reset session
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


            $resetStage = 'email';


        /*
        |--------------------------------------------------------------------------
        | Validate OTP format
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
            | Get latest OTP for the email
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT *
                 FROM otps
                 WHERE email=?
                 ORDER BY id DESC
                 LIMIT 1'
            );

            $stmt->execute([
                $email
            ]);

            $row = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | OTP doesn't exist or expired
            |--------------------------------------------------------------------------
            */

            if (
                !$row ||
                strtotime($row['expires_at']) < time()
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


                $_SESSION['reset_stage'] =
                    'email';


                $resetStage =
                    'email';


            /*
            |--------------------------------------------------------------------------
            | OTP is incorrect
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
                | Delete used OTP
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
                | Move to new password
                |--------------------------------------------------------------------------
                */

                $_SESSION['reset_stage'] =
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
    | FORGOT PASSWORD - STEP 3
    |--------------------------------------------------------------------------
    |
    | SET NEW PASSWORD
    |--------------------------------------------------------------------------
    */

    elseif (
        $mode === 'forgot' &&
        $resetStage === 'newpass'
    ) {

        /*
        |--------------------------------------------------------------------------
        | Get username from session
        |--------------------------------------------------------------------------
        |
        | This is the username whose password will be changed.
        |
        */

        $username =
            $_SESSION['reset_username'] ?? '';

        $email =
            $_SESSION['reset_email'] ?? '';


        $newPass =
            $_POST['new_password'] ?? '';

        $confirm =
            $_POST['confirm_password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Verify reset session
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

            /*
            |--------------------------------------------------------------------------
            | Hash new password
            |--------------------------------------------------------------------------
            */

            $hash = password_hash(
                $newPass,
                PASSWORD_DEFAULT
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE PASSWORD BY USERNAME
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | We do NOT use email here.
            |
            | The email was only used for OTP.
            |
            */

            $stmt = $pdo->prepare(
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
            | Clear password reset session
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['reset_username'],
                $_SESSION['reset_email'],
                $_SESSION['reset_stage']
            );


            /*
            |--------------------------------------------------------------------------
            | Return to login
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


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    else {

        $username =
            trim($_POST['username'] ?? '');

        $password =
            $_POST['password'] ?? '';


        if (
            $username === '' ||
            $password === ''
        ) {

            $errorMessage =
                'Both fields required.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Find user by username
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
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
            | Verify password
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
                | Create login session
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id'] =
                    (int)$user['id'];

                $_SESSION['username'] =
                    $user['username'];

                $_SESSION['role'] =
                    $user['role'];


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

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
    }
}


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitles = [
    'login' => 'Login',
    'register' => 'Create Account',
    'forgot' => 'Reset Password'
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
        echo htmlspecialchars($pageTitle);
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


    <h2>

        <?php
        echo htmlspecialchars($pageTitle);
        ?>

    </h2>


    <p class="sub">

        <?php if ($mode === 'register'): ?>

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
         ERROR MESSAGE
    ========================================================== -->

    <?php if ($errorMessage): ?>

        <div class="msg me">

            <?php
            echo htmlspecialchars(
                $errorMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         SUCCESS MESSAGE
    ========================================================== -->

    <?php if ($successMessage): ?>

        <div class="msg ms">

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <form
        action="login.php?action=<?php
        echo htmlspecialchars($mode);
        ?>"
        method="POST"
        autocomplete="off"
    >


        <?php if ($mode === 'register'): ?>

            <!-- =================================================
                 REGISTER
            ================================================== -->

            <div class="fg">

                <label>
                    Username
                    <span class="req">*</span>
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
                    <span class="req">*</span>
                </label>

                <input
                    type="email"
                    name="email"
                    required
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
                    <span class="req">*</span>
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
                    8+ chars, uppercase, lowercase,
                    number, special char.
                </small>

            </div>


            <div class="fg">

                <label>
                    Confirm Password
                    <span class="req">*</span>
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
                 FORGOT PASSWORD - USERNAME + EMAIL
            ================================================== -->

            <div class="fg">

                <label>
                    Username
                    <span class="req">*</span>
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
                    Enter the username of the account
                    whose password you want to reset.
                </small>

            </div>


            <div class="fg">

                <label>
                    Email for OTP
                    <span class="req">*</span>
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
                    This email is only used to receive
                    the OTP. It does not need to match
                    the account email.
                </small>

            </div>


        <?php elseif (
            $mode === 'forgot' &&
            $resetStage === 'otp'
        ): ?>

            <!-- =================================================
                 OTP
            ================================================== -->

            <div class="fg">

                <label>
                    OTP Code
                    <span class="req">*</span>
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
                        $_SESSION['reset_email'] ?? ''
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
            ================================================== -->

            <div class="fg">

                <label>
                    New Password
                    <span class="req">*</span>
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
                    8+ chars, uppercase, lowercase,
                    number, special char.
                </small>

            </div>


            <div class="fg">

                <label>
                    Confirm Password
                    <span class="req">*</span>
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
                    <span class="req">*</span>
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
                    <span class="req">*</span>
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


            <?php if ($mode === 'register'): ?>

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
                    Start Over
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

        <?php if ($mode === 'login'): ?>

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
     PASSWORD SHOW / HIDE
================================================================ -->

<script>

document
    .querySelectorAll('.pw-toggle')
    .forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                var target =
                    document.getElementById(
                        button.dataset.target
                    );

                if (!target) {
                    return;
                }

                if (
                    target.type === 'password'
                ) {

                    target.type = 'text';

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