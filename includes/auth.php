<?php
/**
 * includes/auth.php - session + role gate for dashboard entry points.
 * Included by dashboard.php (admin) and staff_dashboard.php (staff).
 */

function requireRole(string $role): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: login.php');
        exit;
    }
}
