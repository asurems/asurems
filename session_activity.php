<?php
require_once __DIR__ . '/config/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'expired' => true]);
    exit;
}

$currentTime = time();
$lastActivity = (int)($_SESSION['last_activity'] ?? 0);

if ($lastActivity > 0 && ($currentTime - $lastActivity) >= SESSION_IDLE_TIMEOUT) {
    // Session cookie and data should be cleared together, consistently with auth.php.
    require_once __DIR__ . '/includes/auth.php';
    destroyAuthenticatedSession();

    http_response_code(401);
    echo json_encode(['success' => false, 'expired' => true]);
    exit;
}

// Preserve the existing heartbeat contract used by the front end.
// NOTE: a heartbeat counts as activity, just like in the original file.
$_SESSION['last_activity'] = $currentTime;

// Release PHP's session lock promptly; parallel page requests won't wait.
session_write_close();

echo json_encode(['success' => true]);
exit;
