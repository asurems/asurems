<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

/**
 * Clear the authenticated session and its cookie. Other modules can call
 * session_start() afterwards to create an anonymous session for flash messages.
 */
function destroyAuthenticatedSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?: '',
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
    // Do not carry the just-destroyed session ID into the new guest session.
    session_id('');
}

/**
 * End a rejected session and preserve a flash message in a new guest session.
 */
function redirectToLoginAfterSessionEnd(string $message): void
{
    destroyAuthenticatedSession();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_id('');
        session_start();
    }

    setFlash('danger', $message);
    session_write_close();
    redirect(BASE_URL . '/login.php?view=login');
}

function requireLogin(): void
{
    global $pdo;

    if (!isLoggedIn()) {
        setFlash('danger', 'Please log in to continue.');
        session_write_close();
        redirect(BASE_URL . '/login.php?view=login');
    }

    $currentTime = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);

    if ($lastActivity > 0 && ($currentTime - $lastActivity) >= SESSION_IDLE_TIMEOUT) {
        redirectToLoginAfterSessionEnd(
            'Your session expired due to inactivity. Please log in again.'
        );
    }

    // Validate the account before treating its session as still authenticated.
    $stmt = $pdo->prepare('
        SELECT status, role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (string)$user['status'] !== 'Active') {
        redirectToLoginAfterSessionEnd('Your account is inactive or unavailable.');
    }

    // Stay synchronized with the current database role if an admin changed it.
    $_SESSION['role'] = (string)$user['role'];
    $_SESSION['last_activity'] = $currentTime;
}

function requireRole($allowedRoles): void
{
    requireLogin();

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    $currentRole = $_SESSION['role'] ?? '';

    if (!in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        require __DIR__ . '/header.php';

        echo '
        <div class="container py-5">
            <div class="alert alert-danger">
                <h4>Access Denied</h4>
                <p>You are not authorized to access this page.</p>
            </div>
            <a href="' . BASE_URL . '/dashboard.php" class="btn btn-success">
                Return to Dashboard
            </a>
        </div>';

        require __DIR__ . '/footer.php';
        exit;
    }
}

function currentUser()
{
    global $pdo;

    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT u.*, c.college_name, d.department_name
        FROM users u
        LEFT JOIN colleges c ON c.college_id = u.college_id
        LEFT JOIN departments d ON d.department_id = u.department_id
        WHERE u.user_id = ?
        LIMIT 1
    ');

    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch();
}
