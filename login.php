<?php
session_start();

 $errorMessage = ''; $successMessage = '';
 $dbHost='localhost'; $dbPort=3306; $dbUser='root'; $dbPass=''; $dbName='ims';

 $allowedModes = ['login','register','forgot'];
 $mode = (isset($_GET['action']) && in_array($_GET['action'],$allowedModes,true)) ? $_GET['action'] : 'login';

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("USE `$dbName`");
} catch(PDOException $e) {
    die('<div style="text-align:center;padding:3rem;color:#fca5a5;font-family:sans-serif;background:#0f172a"><h2>Database Error</h2><p>'.htmlspecialchars($e->getMessage()).'</p><p>Run <a href="install.php" style="color:#38bdf8">install.php</a> first.</p></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── REGISTER ──
    if ($mode === 'register') {
        $username = trim($_POST['username']??'');
        $password = $_POST['password']??'';
        $confirm  = $_POST['confirm_password']??'';
        $role     = 'staff'; // ← ALWAYS staff, never admin

        if (empty($username)||empty($password)||empty($confirm)) { $errorMessage = 'All fields required.'; }
        elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/',$username)) { $errorMessage = 'Username: 3-15 chars, letters & numbers.'; }
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',$password)) { $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special char.'; }
        elseif ($password !== $confirm) { $errorMessage = 'Passwords do not match.'; }
        else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?'); $stmt->execute([$username]);
            if ($stmt->fetchColumn()>0) { $errorMessage = 'Username already exists.'; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)')->execute([$username,$hash,$role]);
                $successMessage = 'Staff account created! You can now log in.';
                $mode = 'login';
            }
        }
    }

    // ── FORGOT PASSWORD ──
    elseif ($mode === 'forgot') {
        $username = trim($_POST['username']??'');
        $newPass  = $_POST['new_password']??'';
        $confirm  = $_POST['confirm_password']??'';

        if (empty($username)||empty($newPass)||empty($confirm)) { $errorMessage = 'All fields required.'; }
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',$newPass)) { $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special char.'; }
        elseif ($newPass !== $confirm) { $errorMessage = 'Passwords do not match.'; }
        else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username=?'); $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE username=?')->execute([$hash,$username]);
                $successMessage = 'Password reset! Log in now.';
                $mode = 'login';
            } else { $errorMessage = 'Username not found.'; }
        }
    }

    // ── LOGIN ──
    else {
        $username = trim($_POST['username']??'');
        $password = $_POST['password']??'';

        if (empty($username)||empty($password)) { $errorMessage = 'Both fields required.'; }
        else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username=?'); $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                if ($user['role'] === 'admin') { header('Location: dashboard.php'); }
                else { header('Location: staff_dashboard.php'); }
                exit;
            } else { $errorMessage = 'Invalid username or password.'; }
        }
    }
}

 $pageTitles = ['login'=>'Login','register'=>'Create Account','forgot'=>'Reset Password'];
 $pageTitle = $pageTitles[$mode] ?? 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | IMS Nepal</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;font-family:"Segoe UI",system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;padding:1rem}
        .lc{max-width:440px;width:100%;background:rgba(30,41,59,.95);border:1px solid rgba(148,163,184,.15);padding:2rem 2.25rem}
        .lc h2{font-size:1.5rem;color:#f8fafc;margin-bottom:.25rem}
        .sub{color:#94a3b8;font-size:.85rem;margin-bottom:1.5rem}
        .roles{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem}
        .rc{background:rgba(15,23,42,.6);padding:.85rem;text-align:center}
        .msg{padding:.65rem .85rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem}
        .me{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#fca5a5}
        .ms{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);color:#86efac}
        .fg{margin-bottom:.9rem}
        .fg label{display:block;font-size:.8rem;font-weight:500;color:#cbd5e1;margin-bottom:.3rem}
        .req{color:#f87171}
        .fg input{width:100%;padding:.55rem .75rem;font-size:.9rem;color:#f1f5f9;background:#0f172a;border:1px solid #334155;outline:none;font-family:inherit}
        .fg input:focus{border-color:#38bdf8}
        .ph{display:block;font-size:.7rem;color:#64748b;margin-top:.25rem}
        .fa{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-top:1.25rem}
        .bs{padding:.6rem 1.25rem;font-size:.9rem;font-weight:600;color:#0f172a;background:#38bdf8;border:none;cursor:pointer;font-family:inherit}
/* was: box-shadow:0 6px 16px rgba(14,165,233,.35) */
        .bs:hover{background:#0ea5e9}
        .sl,.fl{color:#38bdf8;font-size:.8rem;text-decoration:none}
        .sl:hover,.fl:hover{text-decoration:underline}
        .fr{margin-top:.75rem;text-align:center}
        .div{height:1px;background:rgba(148,163,184,.12);margin:1rem 0}
        .staff-tag{display:inline-block;font-size:.7rem;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.3);color:#fbbf24;padding:.2rem .5rem;border-radius:4px;margin-top:.3rem}
    </style>
</head>
<body>
<div class="lc">
    <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
    <p class="sub">
        <?php if($mode==='register'): ?>Create a staff account<?php elseif($mode==='forgot'): ?>Reset your password<?php else: ?>Welcome to IMS Nepal<?php endif; ?>
    </p>

    <?php if($mode==='login'): ?>
    <div class="roles">
        <div class="rc admin">
            <div class="rl">Admin</div>
            
        </div>
        <div class="rc staff">
            <div class="rl">Staff</div>
           
        </div>
    </div>
    <div class="div"></div>
    <?php endif; ?>

    <?php if($errorMessage): ?><div class="msg me"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
    <?php if($successMessage): ?><div class="msg ms"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>

    <form action="login.php?action=<?php echo htmlspecialchars($mode); ?>" method="POST" autocomplete="off">
        <div class="fg">
            <label>Username <span class="req">*</span></label>
            <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username']??''); ?>">
        </div>

        <?php if($mode==='forgot'): ?>
            <div class="fg"><label>New Password <span class="req">*</span></label><input type="password" name="new_password" required><small class="ph">8+ chars, uppercase, lowercase, number, special char.</small></div>
            <div class="fg"><label>Confirm Password <span class="req">*</span></label><input type="password" name="confirm_password" required></div>
        <?php elseif($mode==='register'): ?>
            <div class="fg"><label>Password <span class="req">*</span></label><input type="password" name="password" required><small class="ph">8+ chars, uppercase, lowercase, number, special char.</small></div>
            <div class="fg"><label>Confirm Password <span class="req">*</span></label><input type="password" name="confirm_password" required></div>
            <!-- Role is ALWAYS staff — hidden input INSIDE the form -->
            <input type="hidden" name="role" value="staff">
            <div style="text-align:center;margin-bottom:.5rem"><span class="staff-tag">👷 Registering as Staff</span></div>
        <?php else: ?>
            <div class="fg"><label>Password <span class="req">*</span></label><input type="password" name="password" required></div>
        <?php endif; ?>

        <div class="fa">
            <?php if($mode==='register'): ?>
                <a href="login.php?action=login" class="sl">Back to Login</a>
                <button type="submit" class="bs">Create Account</button>
            <?php elseif($mode==='forgot'): ?>
                <a href="login.php?action=login" class="sl">Back to Login</a>
                <button type="submit" class="bs">Reset Password</button>
            <?php else: ?>
                <a href="login.php?action=register" class="sl">Create account</a>
                <button type="submit" class="bs">Log In</button>
            <?php endif; ?>
        </div>

        <?php if($mode==='login'): ?>
            <div class="fr"><a href="login.php?action=forgot" class="fl">Forgot password?</a></div>
        <?php endif; ?>
    </form>