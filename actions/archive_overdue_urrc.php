<?php
/**
 * Command-line only URRC 21-day archive task.
 * Run using Windows Task Scheduler with XAMPP's php.exe.
 * No web request or logged-in visitor is required.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This task can only be run by PHP CLI.');
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/research_revision_deadlines.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "ASU-REMS database connection was not initialized.\n");
    exit(1);
}

try {
    $count = remsArchiveExpiredUrrcRevisions($pdo);
    $inhouseCount = remsArchiveExpiredInhouseRevisions($pdo);
    echo date('Y-m-d H:i:s') . " — URRC archived: {$count}; In-House archived: {$inhouseCount}\n";
} catch (Throwable $e) {
    error_log('Scheduled URRC archive check failed: ' . $e->getMessage());
    fwrite(STDERR, "URRC archive check failed. Check the PHP error log.\n");
    exit(1);
}
