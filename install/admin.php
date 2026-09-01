<?php
/**
 * install/admin.php — default admin account creation/reset.
 * Included by install.php (which defines INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function ensureAdmin(PDO $pdo, string $email): array
{
    $hash = password_hash('Password123!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute(['admin']);

    $messages = [];
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', email = ? WHERE username = 'admin'")
            ->execute([$hash, $email]);
        $messages[] = 'Admin password reset to Password123!';
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('admin', ?, ?, 'admin')")
            ->execute([$email, $hash]);
        $messages[] = 'Admin created: admin / Password123!';
    }
    $messages[] = "Admin email set to $email.";
    return $messages;
}
