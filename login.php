<?php
session_start();

$errorMessage = '';
$successMessage = '';
$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'ims';

$allowedModes = ['login', 'register', 'forgot'];
$mode = (isset($_GET['action']) && in_array($_GET['action'], $allowedModes, true)) ? $_GET['action'] : 'login';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("USE `$dbName`");
} catch (PDOException $e) {
    die(
        '<div style="text-align:center;padding:3rem;color:#fca5a5;font-family:sans-serif;' .
        'background:#0f172a"><h2>Database Error</h2><p>' .
        htmlspecialchars($e->getMessage()) .
        '</p><p>Run <a href="install.php" style="color:#38bdf8">install.php</a> first.</p></div>'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $role = 'staff';

        if (empty($username) || empty($password) || empty($confirm)) {
            $errorMessage = 'All fields required.';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $username)) {
            $errorMessage = 'Username: 3-15 chars, letters & numbers.';
        } elseif (!preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            $password
        )) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special char.';
        } elseif ($password !== $confirm) {
            $errorMessage = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errorMessage = 'Username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)')
                    ->execute([$username, $hash, $role]);
                $successMessage = 'Staff account created! You can now log in.';
                $mode = 'login';
            }
        }
    } elseif ($mode === 'forgot') {
        $username = trim($_POST['username'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($newPass) || empty($confirm)) {
            $errorMessage = 'All fields required.';
        } elseif (!preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            $newPass
        )) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special char.';
        } elseif ($newPass !== $confirm) {
            $errorMessage = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username=?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE username=?')
                    ->execute([$hash, $username]);
                $successMessage = 'Password reset! Log in now.';
                $mode = 'login';
            } else {
                $errorMessage = 'Username not found.';
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errorMessage = 'Both fields required.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username=?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                if ($user['role'] === 'admin') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: staff_dashboard.php');
                }
                exit;
            } else {
                $errorMessage = 'Invalid username or password.';
            }
        }
    }
}

$pageTitles = ['login' => 'Login', 'register' => 'Create Account', 'forgot' => 'Reset Password'];
$pageTitle = $pageTitles[$mode] ?? 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | IMS Nepal</title>
    <link rel="stylesheet" href="login-style.css">
</head>
<body>
<div class="lc">
    <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
    <p class="sub">
        <?php if ($mode === 'register'): ?>Create your account<?php elseif ($mode === 'forgot'): ?>Reset your password<?php else: ?>Welcome to IMS Nepal<?php endif; ?>
    </p>

    <?php if ($errorMessage): ?><div class="msg me"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
    <?php if ($successMessage): ?><div class="msg ms"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>

    <form action="login.php?action=<?php echo htmlspecialchars($mode); ?>" method="POST" autocomplete="off">
        <div class="fg">
            <label>Username <span class="req">*</span></label>
            <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>

        <?php if ($mode === 'forgot'): ?>
            <div class="fg">
                <label>New Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" id="password-reset" name="new_password" required>
                    <button type="button" class="pw-toggle" data-target="password-reset">Show</button>
                </div>
                <small class="ph">8+ chars, uppercase, lowercase, number, special char.</small>
            </div>
            <div class="fg">
                <label>Confirm Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" id="confirm-reset" name="confirm_password" required>
                    <button type="button" class="pw-toggle" data-target="confirm-reset">Show</button>
                </div>
            </div>
        <?php elseif ($mode === 'register'): ?>
            <div class="fg">
                <label>Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" id="password-register" name="password" required>
                    <button type="button" class="pw-toggle" data-target="password-register">Show</button>
                </div>
                <small class="ph">8+ chars, uppercase, lowercase, number, special char.</small>
            </div>
            <div class="fg">
                <label>Confirm Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" id="confirm-register" name="confirm_password" required>
                    <button type="button" class="pw-toggle" data-target="confirm-register">Show</button>
                </div>
            </div>
        <?php else: ?>
            <div class="fg">
                <label>Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" id="password-login" name="password" required>
                    <button type="button" class="pw-toggle" data-target="password-login">Show</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="fa">
            <?php if ($mode === 'register'): ?>
                <a href="login.php?action=login" class="sl">Back to Login</a>
                <button type="submit" class="bs">Create Account</button>
            <?php elseif ($mode === 'forgot'): ?>
                <a href="login.php?action=login" class="sl">Back to Login</a>
                <button type="submit" class="bs">Reset Password</button>
            <?php else: ?>
                <a href="login.php?action=register" class="sl">Create account</a>
                <button type="submit" class="bs">Log In</button>
            <?php endif; ?>
        </div>

        <?php if ($mode === 'login'): ?>
            <div class="fr"><a href="login.php?action=forgot" class="fl">Forgot password?</a></div>
        <?php endif; ?>
    </form>
</div>
<script>
document.querySelectorAll('.pw-toggle').forEach(function(button) {
    button.addEventListener('click', function() {
        var target = document.getElementById(button.dataset.target);
        if (!target) return;
        if (target.type === 'password') {
            target.type = 'text';
            button.textContent = 'Hide';
        } else {
            target.type = 'password';
            button.textContent = 'Show';
        }
    });
});
</script>
</body>
</html>
