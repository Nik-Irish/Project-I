<?php
// Initialize message variables
$errorMessage = "";
$successMessage = "";

// Determine current mode: 'login', 'register', or 'forgot'
$allowedModes = ['login', 'register', 'forgot'];
$mode = (isset($_GET['action']) && in_array($_GET['action'], $allowedModes, true))
    ? $_GET['action']
    : 'login';

// Check if a form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($mode === 'register') {
        // --- REGISTER / CREATE ACCOUNT VALIDATION ---
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 1. Check for empty fields
        if (empty($username) || empty($password) || empty($confirmPassword)) {
            $errorMessage = "All registration fields are required.";
        }
        // 2. Validate Username structure (Alphanumeric only, 3-15 chars)
        elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $username)) {
            $errorMessage = "Username must be 3-15 characters long and contain only letters and numbers.";
        }
        // 3. Strict Password Complexity Check
        // Requires: >=8 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Char
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $errorMessage = "Password must be at least 8 characters long, include an uppercase letter, a lowercase letter, a number, and a special character (@$!%*?&).";
        }
        // 4. Check if passwords match
        elseif ($password !== $confirmPassword) {
            $errorMessage = "Passwords do not match. Please try again.";
        } else {
            // Success placeholder (In a real app, you would insert this into a database here)
            $successMessage = "Account created successfully! You can now log in.";
            $mode = 'login'; // Switch back to login view on success
        }

    } elseif ($mode === 'forgot') {
        // --- FORGOT PASSWORD VALIDATION ---
        $username = trim($_POST['username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 1. Check for empty fields
        if (empty($username) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = "All fields are required to reset your password.";
        }
        // 2. Validate Username structure
        elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $username)) {
            $errorMessage = "Username must be 3-15 characters long and contain only letters and numbers.";
        }
        // 3. Same password complexity rules as registration
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
            $errorMessage = "New password must be at least 8 characters long, include an uppercase letter, a lowercase letter, a number, and a special character (@$!%*?&).";
        }
        // 4. Check if passwords match
        elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Passwords do not match. Please try again.";
        } else {
            // Dummy check for presentation (in a real app: look up user, update password hash)
            if ($username === "admin") {
                $successMessage = "Password reset successfully! You can now log in with your new password.";
                $mode = 'login';
            } else {
                // For demo: accept any valid username format as "found"
                // Real apps should not reveal whether a username exists
                $successMessage = "If an account exists for that username, the password has been reset. You can now log in.";
                $mode = 'login';
            }
        }

    } else {
        // --- LOGIN VALIDATION LOGIC ---
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $errorMessage = "Both fields are required.";
        } else {
            // Dummy check for presentation
            if ($username === "admin" && $password === "Password123!") {
                header("Location: dashboard.php");
                exit;
            } else {
                $errorMessage = "Invalid username or password.";
            }
        }
    }
}

// Page titles per mode
$pageTitles = [
    'login'    => 'Login',
    'register' => 'Create Account',
    'forgot'   => 'Forgot Password',
];
$pageTitle = $pageTitles[$mode] ?? 'Login';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="login-style.css">
</head>

<body>

    <div class="login-container">

        <!-- Screen header based on mode -->
        <?php if ($mode === 'register'): ?>
            <h2>Create Account</h2>
            <p class="subtitle">Sign up to get started</p>
        <?php elseif ($mode === 'forgot'): ?>
            <h2>Forgot Password</h2>
            <p class="subtitle">Enter your username and choose a new password</p>
        <?php else: ?>
            <h2>Login</h2>
            <p class="subtitle">Welcome back! Please sign in</p>
        <?php endif; ?>

        <!-- Status & Validation Feedback Messages -->
        <?php if (!empty($errorMessage)): ?>
            <div class="message error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="message success-message"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Form Element -->
        <form action="login.php?action=<?php echo htmlspecialchars($mode); ?>" method="POST" autocomplete="off">

            <!-- Username (all modes) -->
            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" placeholder="Your username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    required>
            </div>

            <?php if ($mode === 'forgot'): ?>
                <!-- Forgot password: new password + confirm -->
                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                    <small class="password-hint">Min. 8 characters with uppercase, lowercase, number, and symbol.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
                </div>

            <?php else: ?>
                <!-- Login / Register: password field -->
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Your password" required>
                    <?php if ($mode === 'register'): ?>
                        <small class="password-hint">Min. 8 characters with uppercase, lowercase, number, and symbol.</small>
                    <?php endif; ?>
                </div>

                <?php if ($mode === 'register'): ?>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="form-actions">
                <?php if ($mode === 'register'): ?>
                    <a href="login.php?action=login" class="switch-link">Back to Login</a>
                    <button type="submit" class="btn-submit">Create Account</button>

                <?php elseif ($mode === 'forgot'): ?>
                    <a href="login.php?action=login" class="switch-link">Back to Login</a>
                    <button type="submit" class="btn-submit">Reset Password</button>

                <?php else: ?>
                    <a href="login.php?action=register" class="switch-link">Create an account</a>
                    <button type="submit" class="btn-submit">Log In</button>
                <?php endif; ?>
            </div>

            <?php if ($mode === 'login'): ?>
                <div class="forgot-row">
                    <a href="login.php?action=forgot" class="forgot-link">Forgot password?</a>
                </div>
            <?php endif; ?>

        </form>

    </div>

</body>

</html>
