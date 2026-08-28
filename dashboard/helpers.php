<?php
/**
 * dashboard/helpers.php — Lookup & list helpers for the admin dashboard.
 * Extracted from dashboard.php (no side effects, only function definitions).
 * Do not open directly — included by dashboard.php and Staff_dashboard.php
 * (both define DASHBOARD_CONTROLLER before including).
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSale($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStaff($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id=? AND role='staff'");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function loadLists($pdo) {
    return [
        $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT id, username, created_at FROM users WHERE role='staff' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
    ];
}