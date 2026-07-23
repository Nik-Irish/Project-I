<?php
/**
 * Application bootstrap — load config, repos, helpers, PDF.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ProductRepo.php';
require_once __DIR__ . '/SaleRepo.php';
require_once __DIR__ . '/MovementRepo.php';
require_once __DIR__ . '/NotificationRepo.php';
require_once __DIR__ . '/UserRepo.php';
require_once __DIR__ . '/../pdf_invoice.php';
