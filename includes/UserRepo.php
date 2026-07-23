<?php
/**
 * Users table access (login / register / password reset).
 */

function users_find_by_username(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function users_create(string $username, string $password): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
    return (int)db()->lastInsertId();
}

function users_update_password(string $username, string $password): bool
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
    return $stmt->execute([$hash, $username]);
}

function users_verify(string $username, string $password): bool
{
    $user = users_find_by_username($username);
    if (!$user) {
        return false;
    }
    return password_verify($password, $user['password_hash']);
}
