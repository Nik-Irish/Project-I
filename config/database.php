<?php
/**
 * XAMPP MySQL connection (PDO).
 * Default: host=localhost, user=root, password="", database=ims
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ims');
define('DB_CHARSET', 'utf8mb4');

/**
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Helpful message if database does not exist yet
        http_response_code(500);
        die(
            '<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:3rem auto;padding:1.5rem;background:#1e293b;color:#e2e8f0;border-radius:12px;">'
            . '<h2 style="color:#f87171;margin:0 0 .75rem;">Database connection failed</h2>'
            . '<p>Could not connect to MySQL database <strong>' . htmlspecialchars(DB_NAME) . '</strong>.</p>'
            . '<ol style="line-height:1.6;color:#94a3b8;">'
            . '<li>Start <strong>Apache</strong> and <strong>MySQL</strong> in XAMPP Control Panel.</li>'
            . '<li>Open <a href="install.php" style="color:#38bdf8;">install.php</a> once to create tables.</li>'
            . '<li>Or import <code>database/schema.sql</code> in phpMyAdmin.</li>'
            . '</ol>'
            . '<p style="font-size:.85rem;color:#64748b;margin-top:1rem;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
            . '</div>'
        );
    }

    return $pdo;
}
