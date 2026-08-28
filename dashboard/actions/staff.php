<?php
/**
 * dashboard/actions/staff.php — Staff account POST actions.
 * Extracted from dashboard.php; runs inside the POST branch of handlers.php.
 * Do not open directly — included by dashboard.php → dashboard/handlers.php.
 *
 * Helper functions used: getStaff()
 *
 * @var PDO    $pdo            Database connection (config/db.php)
 * @var string $action         POST action name (dashboard/handlers.php)
 * @var string $view           Active view slug (dashboard/bootstrap.php)
 * @var string $errorMessage   Feedback rendered by views/messages.php
 * @var string $successMessage Feedback rendered by views/messages.php
 * @var array|null $editStaff  Staff account being edited on the staff page
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$passwordRules = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';

if ($action === 'staff_create') {
    $newUser = trim($_POST['username'] ?? '');
    $newPass = trim($_POST['password'] ?? '');

    if ($newUser === '' || $newPass === '') {
        $errorMessage = 'Username and password are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
        $errorMessage = 'Username: 3-15 characters, letters and numbers only.';
    } elseif (!preg_match($passwordRules, $newPass)) {
        $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
        $stmt->execute([$newUser]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errorMessage = 'Username already taken.';
        } else {
            $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'staff')")
                ->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT)]);
            $successMessage = 'Staff account created.';
        }
    }
    $view = 'staff';
}

if ($action === 'staff_update') {
    $id = (int)($_POST['id'] ?? 0);
    $newUser = trim($_POST['username'] ?? '');
    $newPass = trim($_POST['password'] ?? '');
    $staff = getStaff($pdo, $id);

    if (!$staff) {
        $errorMessage = 'Staff account not found.';
        $view = 'staff';
    } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
        $errorMessage = 'Username: 3-15 characters, letters and numbers only.';
        $view = 'staff';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND id!=?");
        $stmt->execute([$newUser, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errorMessage = 'Username already taken.';
            $view = 'staff';
        } elseif ($newPass !== '' && !preg_match($passwordRules, $newPass)) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
            $view = 'staff';
        } else {
            if ($newPass !== '') {
                $pdo->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?")
                    ->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$newUser, $id]);
            }
            $successMessage = 'Staff account updated.';
            $view = 'staff';
            $editStaff = null;
        }
    }
}

if ($action === 'staff_password_update') {
    $id = (int)($_POST['id'] ?? 0);
    $newPass = trim($_POST['password'] ?? '');
    $staff = getStaff($pdo, $id);

    if (!$staff) {
        $errorMessage = 'Staff account not found.';
    } elseif (!preg_match($passwordRules, $newPass)) {
        $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
    } else {
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
        $successMessage = 'Password updated for staff "' . $staff['username'] . '".';
    }
    $view = 'staff';
}

if ($action === 'staff_delete') {
    $id = (int)($_POST['id'] ?? 0);
    $row = getStaff($pdo, $id);
    if (!$row) {
        $errorMessage = 'Staff account not found.';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $successMessage = 'Staff "' . $row['username'] . '" deleted.';
    }
    $view = 'staff';
}