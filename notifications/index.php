<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_avatar.php';
require_once __DIR__ . '/../includes/notifications.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Notifications';
$activePage = 'notifications';

ensureNotificationsTable($pdo);


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';

if (!empty($user['first_name'])) {

    $initials .= strtoupper(
        substr(
            $user['first_name'],
            0,
            1
        )
    );
}

if (!empty($user['last_name'])) {

    $initials .= strtoupper(
        substr(
            $user['last_name'],
            0,
            1
        )
    );
}


/*
|--------------------------------------------------------------------------
| SAFE REDIRECT HELPER
|--------------------------------------------------------------------------
*/

function notificationSafeRedirectUrl(
    ?string $url
): ?string {

    $url =
        trim(
            (string)$url
        );

    if ($url === '') {
        return null;
    }

    if (
        defined('BASE_URL')
        &&
        substr(
            $url,
            0,
            strlen(BASE_URL)
        ) === BASE_URL
    ) {
        return $url;
    }

    if (
        substr($url, 0, 1) === '/'
        &&
        substr($url, 0, 2) !== '//'
    ) {
        return $url;
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| OPEN NOTIFICATION
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['open'])
) {

    $notificationId =
        (int)$_GET['open'];

    $stmt =
        $pdo->prepare("
            SELECT
                notification_id,
                action_url
            FROM notifications
            WHERE notification_id = ?
              AND user_id = ?
            LIMIT 1
        ");

    $stmt->execute([
        $notificationId,
        (int)$user['user_id']
    ]);

    $notification =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if ($notification) {

        markNotificationAsRead(
            $pdo,
            $notificationId,
            (int)$user['user_id']
        );

        $redirectUrl =
            notificationSafeRedirectUrl(
                $notification['action_url']
                ??
                null
            );

        if ($redirectUrl !== null) {

            redirect($redirectUrl);
        }
    }

    redirect(
        BASE_URL . '/notifications/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    if (
        function_exists('verifyCsrfToken')
        &&
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        die('Invalid request token.');
    }

    $action =
        $_POST['action']
        ??
        '';

    if ($action === 'mark_all_read') {

        markAllNotificationsAsRead(
            $pdo,
            (int)$user['user_id']
        );
    }

    if ($action === 'delete_read') {

        deleteReadNotifications(
            $pdo,
            (int)$user['user_id']
        );
    }

    if ($action === 'mark_read') {

        markNotificationAsRead(
            $pdo,
            (int)($_POST['notification_id'] ?? 0),
            (int)$user['user_id']
        );
    }

    if ($action === 'delete') {

        deleteNotification(
            $pdo,
            (int)($_POST['notification_id'] ?? 0),
            (int)$user['user_id']
        );
    }

    // Delete actions can be completed without a full-page reload. The normal
    // POST/redirect remains available when JavaScript is disabled.
    if (
        in_array($action, ['delete', 'delete_read'], true)
        && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    ) {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS total_count, '
            . 'COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_count '
            . 'FROM notifications WHERE user_id = ?'
        );
        $countStmt->execute([(int)$user['user_id']]);
        $totals = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int)($totals['total_count'] ?? 0);
        $unread = (int)($totals['unread_count'] ?? 0);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'success' => true,
            'total' => $total,
            'unread' => $unread,
            'read' => max(0, $total - $unread),
        ]);
        exit;
    }

    redirect(
        BASE_URL . '/notifications/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$filter =
    $_GET['filter']
    ??
    'all';

$validFilters = [
    'all',
    'unread',
    'read'
];

if (
    !in_array(
        $filter,
        $validFilters,
        true
    )
) {

    $filter = 'all';
}

$module =
    trim(
        $_GET['module'] ?? ''
    );

$validModules = [
    '',
    'research',
    'extension',
    'reports',
    'system'
];

if (
    !in_array(
        $module,
        $validModules,
        true
    )
) {

    $module = '';
}

$search =
    trim(
        $_GET['search'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalNotifications =
    dashboardNotificationCount(
        $pdo,
        (int)$user['user_id'],
        'all'
    );

$unreadNotifications =
    dashboardNotificationCount(
        $pdo,
        (int)$user['user_id'],
        'unread'
    );

$readNotifications =
    dashboardNotificationCount(
        $pdo,
        (int)$user['user_id'],
        'read'
    );


/*
|--------------------------------------------------------------------------
| LOAD NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$whereSql =
    'user_id = ?';

$params = [
    (int)$user['user_id']
];

if ($filter === 'unread') {

    $whereSql .=
        ' AND is_read = 0';
}

if ($filter === 'read') {

    $whereSql .=
        ' AND is_read = 1';
}

if ($module !== '') {

    $whereSql .=
        ' AND module = ?';

    $params[] =
        $module;
}

if ($search !== '') {

    $whereSql .=
        ' AND (title LIKE ? OR message LIKE ?)';

    $params[] =
        '%' . $search . '%';

    $params[] =
        '%' . $search . '%';
}

$stmt =
    $pdo->prepare("
        SELECT
            notification_id,
            title,
            message,
            module,
            record_id,
            action_url,
            is_read,
            read_at,
            created_at
        FROM notifications
        WHERE {$whereSql}
        ORDER BY
            is_read ASC,
            created_at DESC,
            notification_id DESC
        LIMIT 80
    ");

$stmt->execute($params);

$notifications =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| LOCAL COUNT FUNCTION
|--------------------------------------------------------------------------
*/

function dashboardNotificationCount(
    PDO $pdo,
    int $userId,
    string $type
): int {

    $whereSql =
        'user_id = ?';

    if ($type === 'unread') {
        $whereSql .= ' AND is_read = 0';
    }

    if ($type === 'read') {
        $whereSql .= ' AND is_read = 1';
    }

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE {$whereSql}
        ");

    $stmt->execute([
        $userId
    ]);

    return (int)$stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

function notificationModuleIcon(
    ?string $module
): string {

    return match ($module) {
        'research' => 'bi-journal-text',
        'extension' => 'bi-megaphone',
        'reports' => 'bi-file-earmark-bar-graph',
        default => 'bi-bell'
    };
}


function notificationModuleColor(
    ?string $module
): string {

    return match ($module) {
        'extension' => 'yellow',
        'reports' => 'orange',
        default => 'green'
    };
}


$csrfToken =
    function_exists('generateCsrfToken')
        ? generateCsrfToken()
        : '';


/*
|--------------------------------------------------------------------------
| LOAD LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';

?>

<style>

.notification-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
}


.notification-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}


.notification-filter-card {
    margin-bottom: 20px;
}


.notification-filter-row {
    display: grid;
    grid-template-columns: 1fr 220px auto;
    gap: 12px;
}


.notification-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}


.notification-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 13px;
    border: 1px solid #dfe8e1;
    border-radius: 999px;
    color: #5f6a62;
    text-decoration: none;
    background: #fff;
    font-size: 10px;
    font-weight: 700;
}


.notification-tab:hover {
    color: #08752c;
    border-color: #c9dacd;
}


.notification-tab.active {
    color: #043f18;
    border-color: #08752c;
    background: #eef7f0;
}


.notification-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}


.notification-item {
    position: relative;
    display: flex;
    gap: 14px;
    padding: 16px;
    border: 1px solid #e8eee9;
    border-radius: 16px;
    background: #fff;
    transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
}


.notification-item.unread {
    border-color: #b9d7c0;
    background: #fbfffc;
}


.notification-item:hover {
    border-color: #cbdccf;
    box-shadow: 0 8px 22px rgba(31, 74, 39, .06);
    transform: translateY(-1px);
}


.notification-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #eef7f0;
    color: #08752c;
    font-size: 18px;
}


.notification-icon.yellow {
    background: #edf7ef;
    color: #08752c;
}


.notification-icon.blue {
    background: #eef4ff;
    color: #2769c9;
}


.notification-content {
    flex: 1;
    min-width: 0;
}


.notification-title-line {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}


.notification-title-line h3 {
    margin: 0;
    color: #263028;
    font-size: 13px;
    font-weight: 800;
}


.notification-unread-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
    border-radius: 999px;
    background: #08752c;
}


.notification-content p {
    margin: 0 0 9px;
    color: #68736b;
    font-size: 10px;
    line-height: 1.6;
}


.notification-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 14px;
    color: #8a948d;
    font-size: 9px;
}


.notification-item-actions {
    width: 118px;
    flex: 0 0 118px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
}


.notification-item-actions form {
    margin: 0;
}


.notification-item-actions .btn {
    width: 100%;
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
    text-align: center;
}


.notification-open-link {
    white-space: nowrap;
}


.notification-empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #7f8981;
}


.notification-empty-state i {
    display: block;
    margin-bottom: 12px;
    color: #a3ada5;
    font-size: 34px;
}


.notification-empty-state h3 {
    margin-bottom: 6px;
    color: #3d473f;
    font-size: 15px;
    font-weight: 800;
}


.notification-empty-state p {
    margin: 0;
    font-size: 10px;
}


.notification-summary-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}


.notification-summary-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 1px solid #e5ece6;
    border-radius: 16px;
    background: #fff;
}


.notification-summary-card strong {
    display: block;
    color: #1f2821;
    font-size: 22px;
    line-height: 1;
}


.notification-summary-card span {
    color: #78827b;
    font-size: 10px;
}


/* Keep the confirmation overlay pinned to the VIEWPORT, not the page's scroll area. */
.notification-confirm-backdrop {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(5, 55, 22, .40);
    backdrop-filter: blur(2px);
}


.notification-confirm-backdrop.show {
    display: flex;
}

/* Keep scrollbar width and layout unchanged while the popup is open.
   Background wheel/touch scrolling is blocked on the backdrop in JavaScript. */
html:has(.notifications-admin-page) {
    scrollbar-gutter: stable;
}
.notifications-admin-page {
    scrollbar-gutter: stable;
}
.notification-confirm-backdrop,
.notification-confirm-backdrop.show,
.notification-confirm-box {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}


.notification-confirm-box {
    width: min(430px, 100%);
    overflow: hidden;
    border-radius: 22px;
    background: #ffffff;
    border: 1px solid #e3ece5;
    box-shadow: 0 24px 70px rgba(8, 65, 28, .20);
    animation: none;
}


.notification-confirm-head {
    padding: 24px 24px 10px;
    text-align: center;
}


.notification-confirm-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: #edf7ef;
    color: #08752c;
    font-size: 25px;
}


.notification-confirm-head h3 {
    margin: 0 0 7px;
    color: #1f2a22;
    font-size: 18px;
    font-weight: 800;
}


.notification-confirm-head p {
    margin: 0;
    color: #6f7a72;
    font-size: 12px;
    line-height: 1.65;
}


.notification-confirm-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 18px 24px 24px;
}


.notification-confirm-actions .btn {
    min-height: 44px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}


.notification-confirm-cancel {
    border: 1px solid #d7e0d9;
    background: #ffffff;
    color: #5e6961;
}


.notification-confirm-cancel:hover {
    border-color: #08752c;
    background: #f2f8f3;
    color: #08752c;
}


.notification-confirm-delete {
    border: 0;
    background: #08752c;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(8, 117, 44, .18);
}


.notification-confirm-delete:hover {
    background: #066525;
    color: #ffffff;
}


@media (max-width: 900px) {

    .notification-filter-row {
        grid-template-columns: 1fr;
    }


    .notification-summary-row {
        grid-template-columns: 1fr;
    }


    .notification-header-row {
        display: block;
    }


    .notification-actions {
        margin-top: 12px;
    }


    .notification-item {
        align-items: flex-start;
    }


    .notification-item-actions {
        width: 100%;
        flex: 0 0 auto;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
    }


    .notification-item-actions form,
    .notification-item-actions .btn {
        width: 118px;
    }

}


@media (max-width: 575px) {

    .notification-item {
        display: block;
    }


    .notification-icon {
        margin-bottom: 12px;
    }


    .notification-item-actions {
        margin-top: 12px;
    }

}



/* =========================================================
   ASU PROFESSIONAL PALETTE POLISH
   Green dominant • Yellow secondary • Orange notifications
   Design-only override: no workflow/database logic changed.
========================================================= */

:root {
    --asu-green: #006B2E;
    --asu-green-dark: #004F22;
    --asu-green-deep: #003F1A;
    --asu-yellow: #FFD21F;
    --asu-yellow-dark: #E5B800;
    --asu-orange: #F28C28;
    --asu-orange-dark: #C96800;
    --asu-white: #FFFFFF;
    --asu-black: #111711;
    --asu-bg: #F7FAF4;
    --asu-card: #FFFFFF;
    --asu-border: rgba(0, 107, 46, .16);
    --asu-muted: #4C554F;
}

html,
body {
    color: var(--asu-black) !important;
    background:
        radial-gradient(circle at top right, rgba(255, 210, 31, .10), transparent 32%),
        linear-gradient(180deg, #FFFFFF 0%, #F7FAF4 100%) !important;
}

.page-content {
    background:
        radial-gradient(circle at top right, rgba(255, 210, 31, .10), transparent 34%),
        linear-gradient(180deg, #FAFCF8 0%, #F4F8F1 100%) !important;
}

.asu-sidebar {
    background:
        linear-gradient(180deg, #007A34 0%, #006B2E 52%, #004F22 100%) !important;
    box-shadow: 12px 0 32px rgba(0, 63, 26, .14) !important;
}

.sidebar-brand {
    border-bottom-color: rgba(255, 255, 255, .14) !important;
}

.brand-logo,
.user-avatar {
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
}

.sidebar-link {
    color: rgba(255, 255, 255, .92) !important;
}

.sidebar-link:hover {
    background: rgba(255, 255, 255, .11) !important;
    color: #FFFFFF !important;
}

.sidebar-link.active {
    background: var(--asu-yellow) !important;
    color: var(--asu-black) !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, .12) !important;
}

.notification-count,
.sidebar-notification-badge {
    background: var(--asu-orange) !important;
    color: #FFFFFF !important;
    border: 2px solid rgba(255, 255, 255, .35) !important;
    box-shadow: 0 8px 18px rgba(242, 140, 40, .32) !important;
}

.sidebar-link.active .notification-count,
.sidebar-link.active .sidebar-notification-badge {
    background: var(--asu-orange) !important;
    color: #FFFFFF !important;
}

.asu-topbar {
    background: #FFFFFF !important;
    border-bottom: 1px solid rgba(0, 107, 46, .12) !important;
    box-shadow: 0 8px 24px rgba(0, 79, 34, .05) !important;
}

.system-name,
.topbar-user-text strong,
.research-view-heading h1,
.progress-heading h1,
.monitoring-hero h1,
.rps-queue-heading h1,
.processing-hero h1,
.notification-header-row h1,
.card-title,
.research-panel-title,
.workflow-text h4,
.notification-title-line h3 {
    color: var(--asu-black) !important;
}

.topbar-user-text span,
.research-view-heading p,
.progress-heading p,
.monitoring-hero p,
.rps-queue-heading p,
.processing-hero p,
.notification-header-row p,
.info-item label,
.notification-content p,
.notification-meta {
    color: var(--asu-muted) !important;
}

.btn-asu,
.btn.btn-asu {
    background: var(--asu-green) !important;
    border-color: var(--asu-green) !important;
    color: #FFFFFF !important;
    box-shadow: 0 10px 20px rgba(0, 107, 46, .18) !important;
}

.btn-asu:hover,
.btn.btn-asu:hover {
    background: var(--asu-green-dark) !important;
    border-color: var(--asu-green-dark) !important;
    color: #FFFFFF !important;
}

.btn-outline-asu,
.btn.btn-outline-asu {
    background: #FFFFFF !important;
    border-color: rgba(0, 107, 46, .28) !important;
    color: var(--asu-green) !important;
}

.btn-outline-asu:hover,
.btn.btn-outline-asu:hover {
    background: rgba(0, 107, 46, .06) !important;
    border-color: var(--asu-green) !important;
    color: var(--asu-green-deep) !important;
}

.research-panel,
.workflow-card,
.research-document-viewer,
.processing-card,
.processing-queue-card,
.rps-queue-table-wrap,
.notification-summary-card,
.notification-filter-card,
.notification-list-card,
.monitoring-filter-card,
.monitoring-project-card,
.project-info-card,
.progress-form-card,
.progress-history-card,
.progress-update-card,
.card,
.asu-card {
    background: #FFFFFF !important;
    border: 1px solid rgba(0, 107, 46, .14) !important;
    box-shadow: 0 14px 34px rgba(0, 79, 34, .06) !important;
}

.research-panel-icon,
.research-status-overview-icon,
.processing-queue-icon,
.processing-card-icon,
.rps-queue-icon,
.monitoring-icon,
.notification-summary-card .notification-icon,
.notification-icon,
.progress-panel-icon,
.project-info-icon,
.document-icon,
.document-open-icon {
    background: rgba(0, 107, 46, .08) !important;
    color: var(--asu-green) !important;
}

.processing-queue-icon.yellow,
.notification-icon.yellow {
    background: rgba(255, 210, 31, .24) !important;
    color: var(--asu-green-deep) !important;
}

.notification-icon.blue,
.notification-icon.orange,
.notification-summary-card .notification-icon {
    background: rgba(242, 140, 40, .14) !important;
    color: var(--asu-orange-dark) !important;
}

.notification-unread-dot {
    background: var(--asu-orange) !important;
}

.notification-item.unread {
    border-color: rgba(242, 140, 40, .42) !important;
    background: #FFFBF5 !important;
}

.notification-item:hover,
.processing-queue-link:hover,
.rps-queue-table tbody tr:hover td,
.monitoring-project-card:hover {
    border-color: rgba(0, 107, 46, .28) !important;
    box-shadow: 0 18px 40px rgba(0, 79, 34, .10) !important;
}

.notification-tab.active,
.rps-queue-tab.active,
.processing-total-badge,
.rps-queue-badge,
.monitoring-stat-badge {
    background: rgba(255, 210, 31, .22) !important;
    border-color: rgba(0, 107, 46, .20) !important;
    color: var(--asu-green-deep) !important;
}

.processing-count,
.monitoring-count,
.rps-workflow-badge,
.monitoring-status,
.project-status,
.research-status {
    border-color: rgba(0, 107, 46, .18) !important;
    color: var(--asu-green-deep) !important;
}

.status-draft {
    background: #FFFFFF !important;
    color: var(--asu-black) !important;
    border-color: rgba(0, 107, 46, .18) !important;
}

.status-submitted,
.status-review,
.status-under-review,
.status-pending {
    background: rgba(255, 210, 31, .36) !important;
    color: var(--asu-black) !important;
    border-color: rgba(255, 210, 31, .70) !important;
}

.status-revision,
.status-for-revision {
    background: rgba(242, 140, 40, .16) !important;
    color: var(--asu-black) !important;
    border-color: rgba(242, 140, 40, .38) !important;
}

.status-approved,
.status-ongoing,
.status-completed {
    background: var(--asu-green) !important;
    color: #FFFFFF !important;
    border-color: var(--asu-green) !important;
}

.status-rejected,
.status-archived {
    background: var(--asu-black) !important;
    color: #FFFFFF !important;
    border-color: var(--asu-black) !important;
}

/* Professional green status/progress bar */
.research-summary,
.project-summary,
.monitoring-hero {
    background:
        radial-gradient(circle at 95% 0%, rgba(255, 210, 31, .22), transparent 30%),
        linear-gradient(135deg, #007A34 0%, #006B2E 54%, #004F22 100%) !important;
    border: 1px solid rgba(255, 255, 255, .10) !important;
    color: #FFFFFF !important;
    box-shadow: 0 18px 42px rgba(0, 79, 34, .18) !important;
}

.research-summary h2,
.project-summary h2,
.monitoring-hero h1,
.research-summary .research-summary-meta,
.project-summary .project-summary-meta,
.monitoring-hero p {
    color: #FFFFFF !important;
}

.research-summary-meta,
.project-summary-meta {
    color: rgba(255, 255, 255, .84) !important;
}

.research-summary-meta i,
.project-summary-meta i,
.workflow-stage-line i,
.main-progress-head span,
.monitoring-hero i {
    color: var(--asu-yellow) !important;
}

.workflow-status-strip .research-progress-head strong,
.main-progress-head strong,
.monitoring-progress-top strong,
.project-summary .project-status,
.research-summary .research-status {
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    border-color: rgba(255, 255, 255, .20) !important;
}

.research-summary .research-progress-bar,
.main-progress-track,
.monitoring-progress-track {
    background: rgba(255, 255, 255, .23) !important;
}

.research-summary .research-progress-value,
.main-progress-value,
.monitoring-progress-value {
    background: var(--asu-yellow) !important;
}

.workflow-card {
    border-left: 4px solid var(--asu-green) !important;
}

.workflow-card.is-warning,
.workflow-notice {
    border-left-color: var(--asu-orange) !important;
    background: rgba(242, 140, 40, .08) !important;
    color: var(--asu-black) !important;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--asu-green) !important;
    box-shadow: 0 0 0 .18rem rgba(0, 107, 46, .14) !important;
}


/* Notification page: orange is reserved for alerts */
.notificationModuleColor,
.notification-unread-dot {
    background: #F28C28 !important;
}

.notification-icon,
.notification-summary-card .notification-icon,
.notification-confirm-icon {
    background: rgba(242, 140, 40, .14) !important;
    color: #C96800 !important;
}

.notification-summary-card:nth-child(2) .notification-icon,
.notification-item.unread .notification-icon {
    background: rgba(242, 140, 40, .18) !important;
    color: #C96800 !important;
}

.btn-outline-secondary,
.btn-outline-danger {
    background: #FFFFFF !important;
    border-color: rgba(242, 140, 40, .36) !important;
    color: #111711 !important;
}

.btn-outline-secondary:hover,
.btn-outline-danger:hover {
    background: rgba(242, 140, 40, .10) !important;
    border-color: #F28C28 !important;
    color: #111711 !important;
}


/* =========================================================
   NOTIFICATIONS — INSTANT FILTER TAB SWITCHING
   Prevent fade/slide and hover movement when switching
   All / Unread / Read. Filtering and notification logic stay intact.
========================================================= */
@view-transition {
    navigation: none;
}

html,
body {
    scroll-behavior: auto !important;
}

html body .main-page,
html body .main-page .notifications-admin-page,
html body .main-page .notifications-admin-page *,
html body .main-page .notifications-admin-page *::before,
html body .main-page .notifications-admin-page *::after {
    animation: none !important;
    transition: none !important;
    scroll-behavior: auto !important;
}

html body .main-page .notifications-admin-page .notification-item:hover {
    transform: none !important;
}

html body .main-page .notifications-admin-page .notification-tab,
html body .main-page .notifications-admin-page .notification-tab:hover,
html body .main-page .notifications-admin-page .notification-tab.active {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}

::view-transition-old(root),
::view-transition-new(root) {
    animation: none !important;
}

</style>


<style>
/* =========================================================
   NOTIFICATIONS — FINAL UNIFORM ASU-REMS INTERFACE
========================================================= */

:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-ink: #101810;
    --asu-muted: #657068;
}

/* =========================================================
   GLOBAL / BACKGROUND
========================================================= */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a,
.main-page table {
    font-family: "Century Gothic", sans-serif !important;
}

.main-page {
    min-height: 100vh !important;
    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.notifications-admin-page {
    min-height: calc(100vh - 76px) !important;
    padding: 24px 28px 30px !important;

    background:
        radial-gradient(
            circle at 90% 5%,
            rgba(255,213,31,.10),
            transparent 25%
        ),
        radial-gradient(
            circle at 8% 7%,
            rgba(0,122,51,.07),
            transparent 27%
        ),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/* =========================================================
   TOPBAR
========================================================= */

.asu-topbar {
    min-height: 76px !important;
    padding: 0 30px !important;

    background: rgba(255,255,255,.97) !important;

    border-bottom:
        1px solid
        rgba(0,122,51,.12) !important;

    box-shadow:
        0 10px 26px
        rgba(16,24,16,.035) !important;
}

.topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
}

.system-name {
    display: none !important;
}

.system-brand-copy {
    position: relative !important;
    padding-left: 18px !important;
    line-height: 1.15 !important;
}

.system-brand-copy::before {
    content: "" !important;

    position: absolute !important;
    left: 0 !important;
    top: 50% !important;

    width: 3px !important;
    height: 38px !important;

    transform: translateY(-50%) !important;

    border-radius: 999px !important;

    background: var(--asu-green) !important;
}

.system-brand-copy strong {
    display: block !important;

    color: var(--asu-ink) !important;

    font-size: 17px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em !important;
}

.system-brand-copy span {
    display: block !important;

    margin-top: 4px !important;

    color: var(--asu-muted) !important;

    font-size: 13px !important;
    font-weight: 400 !important;
}

.topbar-user-text strong {
    font-size: 15px !important;
    line-height: 1.1 !important;
}

.topbar-user-text span {
    margin-top: 4px !important;
    font-size: 12px !important;
}

.asu-topbar .user-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;

    border-radius: 50% !important;

    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;

    font-size: 15px !important;
    font-weight: 900 !important;

    box-shadow:
        0 8px 18px
        rgba(255,213,31,.25) !important;
}

/* =========================================================
   COMPACT HERO — SAME MAIN-PAGE STANDARD
========================================================= */

.notification-header-row {
    position: relative !important;
    overflow: hidden !important;

    min-height: 96px !important;

    margin: 0 0 18px !important;
    padding: 20px 26px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;

    gap: 18px !important;

    border-radius: 18px !important;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow:
        0 18px 42px
        rgba(0,90,40,.16) !important;
}

.notification-header-row::after {
    content: "" !important;

    position: absolute !important;

    right: -110px !important;
    top: -120px !important;

    width: 430px !important;
    height: 280px !important;

    transform: rotate(-9deg) !important;

    border-radius: 42px !important;

    background:
        linear-gradient(
            135deg,
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        ) !important;

    pointer-events: none !important;
}

.notification-header-row > * {
    position: relative !important;
    z-index: 2 !important;
}

.notification-header-row .page-heading {
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    border: 0 !important;
}

.notification-header-row .page-heading h1 {
    margin: 0 !important;

    color: #ffffff !important;

    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.notification-header-row .page-heading p {
    display: none !important;
}

.notification-actions {
    display: flex !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    justify-content: flex-end !important;
    gap: 8px !important;
}

.notification-actions form {
    margin: 0 !important;
}

.notification-actions .btn {
    min-height: 40px !important;
    height: 40px !important;

    padding: 0 13px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    gap: 6px !important;

    border-radius: 10px !important;

    font-size: 10px !important;
    font-weight: 800 !important;
}

.notification-header-row .notification-actions .btn-outline-asu {
    border: 1px solid rgba(255,255,255,.65) !important;
    background: rgba(255,255,255,.12) !important;
    color: #ffffff !important;
}

.notification-header-row .notification-actions .btn-outline-asu:hover {
    background: rgba(255,255,255,.20) !important;
    color: #ffffff !important;
}

.notification-header-row .notification-actions .btn-outline-secondary {
    border: 0 !important;
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
}

/* =========================================================
   SUMMARY CARDS — 3 MAXIMIZED CARDS
========================================================= */

.notification-summary-row {
    display: grid !important;

    grid-template-columns:
        repeat(3,minmax(0,1fr)) !important;

    gap: 14px !important;

    margin: 0 0 18px !important;
}

.notification-summary-card {
    position: relative !important;
    overflow: hidden !important;

    min-width: 0 !important;
    min-height: 132px !important;

    padding: 16px 18px 20px !important;

    display: grid !important;
    grid-template-columns: 52px minmax(0,1fr) !important;
    align-items: center !important;
    gap: 15px !important;

    border:
        1px solid
        rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background:
        rgba(255,255,255,.96) !important;

    box-shadow:
        0 14px 34px
        rgba(16,24,16,.055) !important;
}

.notification-summary-card::after {
    content: "" !important;

    position: absolute !important;

    left: -5% !important;
    right: -5% !important;
    bottom: -36px !important;

    width: auto !important;
    height: 54px !important;

    border-radius:
        50% 50% 0 0 !important;

    pointer-events: none !important;
}

.notification-summary-card:nth-child(odd)::after {
    background: #007a33 !important;
    opacity: .22 !important;
}

.notification-summary-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}

.notification-summary-card > * {
    position: relative !important;
    z-index: 2 !important;
}

.notification-summary-card .notification-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 15px !important;

    background:
        linear-gradient(
            135deg,
            #007a33,
            #005c26
        ) !important;

    color: #ffffff !important;

    font-size: 22px !important;

    box-shadow:
        0 10px 18px
        rgba(0,122,51,.20) !important;
}

.notification-summary-card:nth-child(even) .notification-icon {
    background:
        linear-gradient(
            135deg,
            #ffd51f,
            #f0bd00
        ) !important;

    color: var(--asu-green-deep) !important;

    box-shadow:
        0 10px 18px
        rgba(255,213,31,.25) !important;
}

.notification-summary-card strong {
    display: block !important;

    margin: 0 0 6px !important;

    color: #101810 !important;

    font-size: 28px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.notification-summary-card span {
    display: block !important;

    color: #101810 !important;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* =========================================================
   FILTERS
========================================================= */

.notification-filter-card {
    margin-bottom: 14px !important;
    padding: 16px 18px !important;

    border:
        1px solid
        rgba(0,122,51,.13) !important;

    border-radius: 17px !important;

    background:
        rgba(255,255,255,.96) !important;

    box-shadow:
        0 14px 34px
        rgba(16,24,16,.05) !important;
}

.notification-filter-row {
    display: grid !important;

    grid-template-columns:
        minmax(260px,1fr)
        210px
        auto !important;

    gap: 10px !important;
}

.notification-filter-row .form-control,
.notification-filter-row .form-select {
    min-height: 42px !important;
    height: 42px !important;

    padding: 8px 12px !important;

    border:
        1px solid
        #d7e0d8 !important;

    border-radius: 10px !important;

    background: #ffffff !important;
    color: #263229 !important;

    font-size: 11px !important;

    box-shadow: none !important;
}

/* Visible dropdown arrow for the All Modules filter only. */
.notifications-admin-page .notification-filter-row select[name="module"].form-select {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    padding-right: 38px !important;
    background-color: #ffffff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23263229' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
    cursor: pointer;
}

.notification-filter-row .form-control:focus,
.notification-filter-row .form-select:focus {
    border-color: #76ac82 !important;

    box-shadow:
        0 0 0 3px
        rgba(8,117,44,.07) !important;
}

.notification-filter-row .btn {
    min-height: 42px !important;
    height: 42px !important;

    padding: 0 14px !important;

    border-radius: 10px !important;

    font-size: 10.5px !important;
    font-weight: 800 !important;
}

/* =========================================================
   FILTER TABS
========================================================= */

.notification-tabs {
    display: flex !important;
    flex-wrap: wrap !important;

    gap: 8px !important;

    margin-bottom: 14px !important;
}

.notification-tab {
    min-height: 36px !important;

    padding: 0 12px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    gap: 7px !important;

    border:
        1px solid
        rgba(0,122,51,.16) !important;

    border-radius: 999px !important;

    background: #ffffff !important;
    color: #5f6a62 !important;

    font-size: 9.5px !important;
    font-weight: 800 !important;

    text-decoration: none !important;
}

.notification-tab:hover {
    border-color: rgba(0,122,51,.32) !important;
    color: var(--asu-green) !important;
}

.notification-tab.active {
    border-color: rgba(0,122,51,.22) !important;
    background: #eaf7ed !important;
    color: var(--asu-green-deep) !important;
}

.notification-tab span {
    min-width: 19px !important;
    height: 19px !important;

    padding: 0 5px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 999px !important;

    background: rgba(0,122,51,.08) !important;

    font-size: 8px !important;
}

/* =========================================================
   NOTIFICATION CENTER PANEL
========================================================= */

.notifications-admin-page .asu-panel {
    overflow: hidden !important;

    border:
        1px solid
        rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background:
        rgba(255,255,255,.96) !important;

    box-shadow:
        0 16px 38px
        rgba(16,24,16,.06) !important;
}

.notifications-admin-page .panel-header {
    min-height: 66px !important;

    padding: 14px 18px !important;

    border-bottom:
        1px solid
        rgba(0,122,51,.09) !important;

    background:
        rgba(255,255,255,.86) !important;
}

.notifications-admin-page .panel-title {
    margin: 0 !important;

    color: #101810 !important;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size: 15px !important;
    font-weight: 700 !important;
}

.notifications-admin-page .panel-title-icon {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 10px !important;

    background: #edf7ef !important;
    color: var(--asu-green) !important;
}

.notifications-admin-page .panel-body {
    padding: 16px 18px 18px !important;
}

/* =========================================================
   INDIVIDUAL NOTIFICATION CARDS
========================================================= */

.notification-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
}

.notification-item {
    position: relative !important;
    overflow: hidden !important;

    display: flex !important;
    align-items: flex-start !important;

    gap: 14px !important;

    padding: 15px !important;

    border:
        1px solid
        rgba(0,122,51,.11) !important;

    border-radius: 15px !important;

    background: #ffffff !important;

    box-shadow:
        0 8px 20px
        rgba(16,24,16,.035) !important;

    transition:
        border-color .16s ease,
        box-shadow .16s ease,
        transform .16s ease !important;
}

.notification-item::after {
    content: "" !important;

    position: absolute !important;

    left: -3% !important;
    right: -3% !important;
    bottom: -46px !important;

    height: 56px !important;

    border-radius:
        50% 50% 0 0 !important;

    background: #007a33 !important;
    opacity: .045 !important;

    pointer-events: none !important;
}

.notification-item.unread {
    border-color:
        rgba(242,140,40,.30) !important;

    background:
        #fffcf7 !important;
}

.notification-item.unread::after {
    background: #ffd51f !important;
    opacity: .14 !important;
}

.notification-item:hover {
    transform: translateY(-1px) !important;

    border-color:
        rgba(0,122,51,.24) !important;

    box-shadow:
        0 12px 26px
        rgba(16,24,16,.06) !important;
}

.notification-item > * {
    position: relative !important;
    z-index: 2 !important;
}

.notification-item .notification-icon {
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    flex: 0 0 44px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 12px !important;

    background: #edf7ef !important;
    color: var(--asu-green) !important;

    font-size: 17px !important;
}

.notification-item.unread .notification-icon,
.notification-item .notification-icon.orange {
    background: #fff4df !important;
    color: #bd7000 !important;
}

.notification-item .notification-icon.yellow {
    background: #fff7d8 !important;
    color: #916300 !important;
}

.notification-title-line {
    display: flex !important;
    align-items: center !important;

    gap: 7px !important;

    margin-bottom: 4px !important;
}

.notification-title-line h3 {
    margin: 0 !important;

    color: #1f2b22 !important;

    font-size: 12px !important;
    line-height: 1.3 !important;
    font-weight: 800 !important;
}

.notification-unread-dot {
    width: 7px !important;
    height: 7px !important;
    flex: 0 0 7px !important;

    border-radius: 999px !important;

    background: #f28c28 !important;
}

.notification-content p {
    margin: 0 0 8px !important;

    color: #68736b !important;

    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.notification-meta {
    display: flex !important;
    flex-wrap: wrap !important;

    gap: 5px 13px !important;

    color: #89928b !important;

    font-size: 8.5px !important;
}

.notification-item-actions {
    width: 112px !important;
    flex: 0 0 112px !important;

    display: flex !important;
    flex-direction: column !important;

    gap: 7px !important;

    align-items: stretch !important;
}

.notification-item-actions form {
    margin: 0 !important;
}

.notification-item-actions .btn {
    width: 100% !important;

    min-height: 36px !important;
    height: 36px !important;

    padding: 0 9px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 9px !important;

    font-size: 9.5px !important;
    font-weight: 800 !important;
}

/* =========================================================
   CONFIRM POPUP — SAME CARD LANGUAGE
========================================================= */

.notification-confirm-backdrop {
    background:
        rgba(3,47,18,.42) !important;

    backdrop-filter:
        blur(3px) !important;
}

.notification-confirm-box {
    width:
        min(410px,100%) !important;

    border:
        1px solid
        rgba(0,122,51,.13) !important;

    border-radius:
        18px !important;

    background:
        #ffffff !important;

    box-shadow:
        0 26px 70px
        rgba(8,65,28,.20) !important;
}

.notification-confirm-head {
    padding:
        22px 22px 9px !important;
}

.notification-confirm-icon {
    width: 54px !important;
    height: 54px !important;

    margin:
        0 auto 13px !important;

    border-radius:
        15px !important;

    background:
        #edf7ef !important;

    color:
        var(--asu-green) !important;

    font-size:
        22px !important;
}

.notification-confirm-head h3 {
    margin:
        0 0 6px !important;

    color:
        #1f2a22 !important;

    font-size:
        16px !important;

    font-weight:
        800 !important;
}

.notification-confirm-head p {
    margin: 0 !important;

    color:
        #6f7a72 !important;

    font-size:
        10.5px !important;

    line-height:
        1.55 !important;
}

.notification-confirm-actions {
    display: grid !important;

    grid-template-columns:
        1fr 1fr !important;

    gap:
        9px !important;

    padding:
        16px 22px 22px !important;
}

.notification-confirm-actions .btn {
    min-height:
        40px !important;

    border-radius:
        10px !important;

    font-size:
        10.5px !important;

    font-weight:
        800 !important;
}

.notification-confirm-cancel {
    border:
        1px solid
        #d7e0d9 !important;

    background:
        #ffffff !important;

    color:
        #5e6961 !important;
}

.notification-confirm-delete {
    border:
        0 !important;

    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;

    color:
        #ffffff !important;
}

/* =========================================================
   EMPTY STATE / CLEANUP
========================================================= */

.notification-empty-state {
    padding:
        48px 20px !important;
}

.notification-empty-state i {
    font-size:
        30px !important;
}

.notification-empty-state h3 {
    font-size:
        14px !important;
}

.notification-empty-state p {
    font-size:
        9.5px !important;
}

.content-footer {
    display:
        none !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .notification-summary-row {
        grid-template-columns:
            repeat(2,minmax(0,1fr)) !important;
    }

    .notification-filter-row {
        grid-template-columns:
            1fr 200px !important;
    }

    .notification-filter-row .btn {
        grid-column:
            1 / -1 !important;
    }
}

@media (max-width: 760px) {

    .asu-topbar {
        padding:
            0 16px !important;
    }

    .system-brand-copy strong {
        font-size:
            13px !important;
    }

    .system-brand-copy span {
        font-size:
            10px !important;
    }

    .notifications-admin-page {
        padding:
            18px 14px 24px !important;
    }

    .notification-header-row {
        min-height:
            82px !important;

        padding:
            18px !important;

        border-radius:
            16px !important;

        flex-direction:
            column !important;

        align-items:
            stretch !important;
    }

    .notification-header-row .page-heading h1 {
        font-size:
            27px !important;
    }

    .notification-actions {
        justify-content:
            flex-start !important;
    }

    .notification-summary-row,
    .notification-filter-row {
        grid-template-columns:
            1fr !important;
    }

    .notification-filter-row .btn {
        grid-column:
            auto !important;
    }

    .notification-item {
        display:
            grid !important;

        grid-template-columns:
            44px minmax(0,1fr) !important;
    }

    .notification-item-actions {
        grid-column:
            1 / -1 !important;

        width:
            100% !important;

        flex:
            none !important;

        flex-direction:
            row !important;
    }

    .notification-item-actions form,
    .notification-item-actions .btn {
        flex:
            1 1 0 !important;

        width:
            auto !important;
    }
}

@media (max-width: 520px) {

    .notification-summary-row {
        grid-template-columns:
            1fr !important;
    }

    .notification-actions .btn {
        width:
            100% !important;
    }

    .notification-actions form {
        width:
            100% !important;
    }
}


/* =========================================================
   NOTIFICATIONS — BACKGROUND + STEADY SCROLL ONLY
   Match the Research page background and stop pull/bounce.
   No notification logic, cards, buttons, filters, or data changed.
========================================================= */

html,
body {
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
}

.page-content.notifications-admin-page {
    overscroll-behavior-y: contain !important;

    background:
        radial-gradient(
            circle at top right,
            rgba(255, 210, 31, .10),
            transparent 34%
        ),
        linear-gradient(
            180deg,
            #FAFCF8 0%,
            #F4F8F1 100%
        ) !important;
}



/* =========================================================
   NOTIFICATIONS — EXACT PROJECT MONITORING PAGE BACKGROUND
   Background only; steady-scroll remains unchanged.
========================================================= */

.page-content.notifications-admin-page {
    background:
        radial-gradient(
            circle at 90% 5%,
            rgba(255,213,31,.10),
            transparent 25%
        ),
        radial-gradient(
            circle at 8% 7%,
            rgba(0,122,51,.07),
            transparent 27%
        ),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}



/* =========================================================
   NOTIFICATIONS — SOFT NEUTRAL ASU BACKGROUND
   Removes the overly green cast while keeping the
   same light cream/white feel as the other pages.
   Background only; steady-scroll remains unchanged.
========================================================= */

.page-content.notifications-admin-page {
    background:
        radial-gradient(
            circle at 92% 6%,
            rgba(255, 213, 31, .09),
            transparent 26%
        ),
        radial-gradient(
            circle at 6% 8%,
            rgba(0, 122, 51, .025),
            transparent 28%
        ),
        linear-gradient(
            135deg,
            #f7faf6 0%,
            #f7f8f3 38%,
            #f7f5ed 70%,
            #fbf3e8 100%
        ) !important;
}


/* =========================================================
   NOTIFICATION ACTIONS — COMPACT ICON-ONLY BUTTONS
   Open (eye), Mark read (check), Delete (trash).
   No change to notification handling or confirmation behavior.
========================================================= */
.notifications-admin-page .notification-item-actions {
    width: 126px !important;
    flex: 0 0 126px !important;
    display: flex !important;
    flex-direction: row !important;
    justify-content: flex-end !important;
    align-items: center !important;
    align-self: center !important;
    gap: 6px !important;
}

.notifications-admin-page .notification-item-actions form {
    margin: 0 !important;
    width: 36px !important;
    flex: 0 0 36px !important;
}

.notifications-admin-page .notification-item-actions .btn,
.notifications-admin-page .notification-item-actions form .btn {
    width: 36px !important;
    min-width: 36px !important;
    max-width: 36px !important;
    height: 36px !important;
    min-height: 36px !important;
    padding: 0 !important;
    flex: 0 0 36px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 10px !important;
}

.notifications-admin-page .notification-item-actions .btn i {
    display: inline-block !important;
    margin: 0 !important;
    font-size: 15px !important;
    line-height: 1 !important;
}

@media (max-width: 760px) {
    .notifications-admin-page .notification-item-actions {
        grid-column: 1 / -1 !important;
        width: 100% !important;
        flex: none !important;
        justify-content: flex-end !important;
        margin-top: 4px !important;
    }
}


/* The bulk-read action stays visible for every user role.
   No unread notifications: clearly disabled instead of disappearing. */
.notifications-admin-page .notification-header-row .notification-actions button[type="submit"]:disabled {
    opacity: .58 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
}


/* ================================================================
   ASU-REMS NOTIFICATIONS — DARK MODE (visuals only)
   Works with includes/rems_theme.php: data-rems-theme="dark".
   Only dark-mode elements are affected; Light Mode is unchanged.
   This block is intentionally after this page's many !important rules.
================================================================ */
html[data-rems-theme="dark"] body:has(.notifications-admin-page),
html[data-rems-theme="dark"] body:has(.notifications-admin-page) .main-page,
html[data-rems-theme="dark"] .page-content.notifications-admin-page {
    background: #0b1d15 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body:has(.notifications-admin-page) .asu-topbar {
    background: #10281c !important;
    border-color: #355843 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body:has(.notifications-admin-page) .asu-topbar :is(
    .system-brand-copy strong,.system-name,.topbar-user-text strong
) { color:#f2f8f0 !important; }
html[data-rems-theme="dark"] body:has(.notifications-admin-page) .asu-topbar :is(
    .system-brand-copy span,.topbar-user-text span
) { color:#bfd1c2 !important; }
/* The green hero and its yellow action buttons are intentionally retained. */
html[data-rems-theme="dark"] .notifications-admin-page .notification-header-row :is(h1,p) {
    color:#ffffff !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-card,
html[data-rems-theme="dark"] .notifications-admin-page .asu-panel,
html[data-rems-theme="dark"] .notifications-admin-page .notification-summary-card {
    background:#132b20 !important;
    border-color:#355843 !important;
    box-shadow:none !important;
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .panel-header {
    background:#1b392a !important;
    border-color:#3c5b46 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .panel-body {
    background:#132b20 !important;
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] .notifications-admin-page :is(.panel-title,.notification-summary-card strong,.notification-summary-card span) {
    color:#f0f7ef !important;
}
html[data-rems-theme="dark"] .notifications-admin-page :is(.notification-icon,.panel-title-icon) {
    background:#244b36 !important;
    color:#bfe9c8 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-row :is(.form-control,.form-select) {
    background:#1c3a2a !important;
    border-color:#466a53 !important;
    color:#f1f8ef !important;
    color-scheme:dark;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-row input::placeholder {
    color:#bad0c0 !important;
    opacity:1 !important;
}
/* Preserve native arrow artwork but change its background to a dark surface. */
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-row select[name="module"].form-select {
    background-color:#1c3a2a !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23e3eee5' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    color-scheme:dark;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-row option,
html[data-rems-theme="dark"] .notifications-admin-page .notification-filter-row optgroup {
    background:#1c3a2a !important;
    color:#f1f8ef !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-tab {
    background:#173427 !important;
    border-color:#3e5e48 !important;
    color:#d3e3d6 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-tab:is(:hover,.active) {
    background:#294d35 !important;
    border-color:#6c9b77 !important;
    color:#ffffff !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-tab span {
    background:#385843 !important;
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item,
html[data-rems-theme="dark"] .notifications-admin-page .notification-item.unread {
    background:#183429 !important;
    border-color:#385a46 !important;
    color:#eaf4eb !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item.unread {
    background:#213e2e !important;
    border-left:3px solid #e6c254 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item:hover {
    background:#244434 !important;
    border-color:#5e8467 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-title-line h3 {
    color:#f0f7ef !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-content p {
    color:#c5d8c9 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-meta {
    color:#adbfaf !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item :is(.notification-icon,.notification-icon.yellow) {
    background:#294e38 !important;
    color:#bce6c5 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item.unread .notification-icon,
html[data-rems-theme="dark"] .notifications-admin-page .notification-item .notification-icon.orange {
    background:#4a3c1b !important;
    color:#ffda75 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-unread-dot {
    background:#ffbd59 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item-actions .btn-outline-secondary,
html[data-rems-theme="dark"] .notifications-admin-page .notification-item-actions .btn-outline-asu {
    background:#294b39 !important;
    border-color:#587661 !important;
    color:#e7f4e7 !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-item-actions .btn-outline-secondary:hover,
html[data-rems-theme="dark"] .notifications-admin-page .notification-item-actions .btn-outline-asu:hover {
    background:#3a6048 !important;
    color:#ffffff !important;
}
html[data-rems-theme="dark"] .notifications-admin-page :is(.notification-empty-state,.notification-empty-state p,.notification-empty-state i) {
    color:#b8cbbb !important;
}
html[data-rems-theme="dark"] .notifications-admin-page .notification-empty-state h3 {
    color:#f0f7ef !important;
}
/* Modal is moved under BODY by the existing script, so scope by theme alone. */
html[data-rems-theme="dark"] .notification-confirm-box {
    background:#173327 !important;
    border-color:#456550 !important;
    color:#eaf4eb !important;
    box-shadow:0 26px 70px rgba(0,0,0,.4) !important;
}
html[data-rems-theme="dark"] .notification-confirm-head h3 {
    color:#f1f8ef !important;
}
html[data-rems-theme="dark"] .notification-confirm-head p {
    color:#c4d5c6 !important;
}
html[data-rems-theme="dark"] .notification-confirm-icon {
    background:#29523a !important;
    color:#ccebd1 !important;
}
html[data-rems-theme="dark"] .notification-confirm-cancel {
    background:#294638 !important;
    border-color:#587661 !important;
    color:#f1f8ef !important;
}
html[data-rems-theme="dark"] .notification-confirm-cancel:hover {
    background:#385e47 !important;
    color:#ffffff !important;
}

</style>

<style>
/* =========================================================
   TOPBAR PHOTO — MATCH DASHBOARD.PHP EXACTLY
   Same 46px circular avatar and single soft dashboard shadow;
   NO outer yellow ring, custom halo, scaling, or animation.
   All other page styles and interactive controls are unchanged.
   ========================================================= */
html body .main-page > header.asu-topbar .dashboard-avatar {
    box-sizing: border-box !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 46px !important;
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
    animation: none !important;
    transition: none !important;
    transform: none !important;
}
html body .main-page > header.asu-topbar .dashboard-avatar img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
}
@media (max-width: 480px) {
    html body .main-page > header.asu-topbar .dashboard-avatar {
        flex: 0 0 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}
</style>
<style>
/* Clear read: retain the ASU yellow action color in both light and dark mode. */
html body .main-page .notifications-admin-page .notification-header-row
.notification-actions button.notification-clear-read,
html[data-rems-theme="dark"] body .main-page .notifications-admin-page
.notification-header-row .notification-actions button.notification-clear-read {
    background: #ffd51f !important;
    background-color: #ffd51f !important;
    border: 1px solid #ffd51f !important;
    color: #003f1a !important;
    box-shadow: none !important;
}

html body .main-page .notifications-admin-page .notification-header-row
.notification-actions button.notification-clear-read:hover,
html body .main-page .notifications-admin-page .notification-header-row
.notification-actions button.notification-clear-read:focus-visible,
html[data-rems-theme="dark"] body .main-page .notifications-admin-page
.notification-header-row .notification-actions button.notification-clear-read:hover,
html[data-rems-theme="dark"] body .main-page .notifications-admin-page
.notification-header-row .notification-actions button.notification-clear-read:focus-visible {
    background: #efc500 !important;
    background-color: #efc500 !important;
    border-color: #efc500 !important;
    color: #003f1a !important;
}

html body .main-page .notifications-admin-page .notification-header-row
.notification-actions button.notification-clear-read i {
    color: inherit !important;
}
</style>
<div class="main-page">


    <header class="asu-topbar">

        <div class="topbar-left">

            <button
                type="button"
                id="sidebarToggle"
                class="sidebar-toggle"
                aria-label="Open navigation"
            >
                <i class="bi bi-list"></i>
            </button>

            <div class="system-brand-copy">
                <strong>Research &amp; Extension Management System</strong>
                <span>Aklan State University</span>
            </div>

        </div>


        <div class="topbar-user">

            <div class="topbar-user-text">
                <strong>
                    <?= e(fullName($user)); ?>
                </strong>
                <span>
                    <?= e(($user['role'] ?? '') === 'rps' ? 'Research Processing Staff (RPS)' : roleLabel($user['role'] ?? '')); ?>
                </span>
            </div>


            <div class="dropdown">

                <button
                    type="button"
                    class="user-dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <div class="user-avatar dashboard-avatar">
                        <?php $remsHeaderAvatarPath = remsProfilePictureRelativePath($pdo, (int)($user['user_id'] ?? 0)); ?>
                        <?php if ($remsHeaderAvatarPath !== ''): ?>
                            <img class="dashboard-avatar-photo" src="<?= e(rtrim(BASE_URL, '/') . '/' . $remsHeaderAvatarPath); ?>" alt="">
                        <?php else: ?>
                            <?= e($initials); ?>
                        <?php endif; ?>
                    </div>
                </button>


                <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                    <li>
                        <div class="dropdown-item-text px-3 py-2">
                            <strong class="d-block">
                                <?= e(fullName($user)); ?>
                            </strong>
                            <small class="text-muted">
                                <?= e($user['email']); ?>
                            </small>
                        </div>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <a
                            href="<?= BASE_URL; ?>/logout.php"
                            class="dropdown-item"
                        >
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Logout
                        </a>
                    </li>

                </ul>

            </div>

        </div>

    </header>


    <main class="page-content notifications-admin-page">


        <div class="notification-header-row">

            <div class="page-heading mb-0">

                <div>

                    <h1>
                        Notifications
                    </h1>

                    <p>
                        View system updates, review alerts, and action reminders.
                    </p>

                </div>

            </div>


            <div class="notification-actions">

                <!-- Shared Research / Extension / ESO bulk-read action.
                     Keep the button visible even when nothing is unread. -->
                <form method="post" action="">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken); ?>"
                    >
                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >
                    <button
                        type="submit"
                        class="btn btn-outline-asu"
                        <?= $unreadNotifications === 0 ? 'disabled' : ''; ?>
                        title="<?= $unreadNotifications === 0 ? 'All notifications are already read' : 'Mark all your unread notifications as read'; ?>"
                    >
                        <i class="bi bi-check2-all me-1" aria-hidden="true"></i>
                        Mark all as read
                    </button>
                </form>


                <?php if ($readNotifications > 0): ?>

                    <button
                        type="button"
                        class="btn btn-outline-secondary notification-clear-read"
                        data-confirm-open
                        data-confirm-title="Clear read notifications?"
                        data-confirm-message="This will remove all notifications that are already marked as read."
                        data-confirm-action="delete_read"
                        data-confirm-id=""
                        data-confirm-button="Clear read"
                    >
                        <i class="bi bi-trash me-1"></i>
                        Clear read
                    </button>

                <?php endif; ?>

            </div>

        </div>





        <section class="filter-card notification-filter-card">

            <form
                method="get"
                action=""
                class="notification-filter-row"
            >

                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Search notifications..."
                    value="<?= e($search); ?>"
                >


                <select
                    name="module"
                    class="form-select"
                >
                    <option value="">
                        All Modules
                    </option>

                    <option
                        value="research"
                        <?= $module === 'research' ? 'selected' : ''; ?>
                    >
                        Research
                    </option>

                    <option
                        value="extension"
                        <?= $module === 'extension' ? 'selected' : ''; ?>
                    >
                        Extension
                    </option>

                    <option
                        value="reports"
                        <?= $module === 'reports' ? 'selected' : ''; ?>
                    >
                        Reports
                    </option>

                    <option
                        value="system"
                        <?= $module === 'system' ? 'selected' : ''; ?>
                    >
                        System
                    </option>
                </select>


                <button
                    type="submit"
                    class="btn btn-asu"
                >
                    <i class="bi bi-search me-1"></i>
                    Search
                </button>

            </form>

        </section>


        <div class="notification-tabs">

            <a
                href="?filter=all"
                class="notification-tab <?= $filter === 'all' ? 'active' : ''; ?>"
            >
                All
                <span>
                    <?= number_format($totalNotifications); ?>
                </span>
            </a>

            <a
                href="?filter=unread"
                class="notification-tab <?= $filter === 'unread' ? 'active' : ''; ?>"
            >
                Unread
                <span>
                    <?= number_format($unreadNotifications); ?>
                </span>
            </a>

            <a
                href="?filter=read"
                class="notification-tab <?= $filter === 'read' ? 'active' : ''; ?>"
            >
                Read
                <span>
                    <?= number_format($readNotifications); ?>
                </span>
            </a>

        </div>


        <section class="asu-panel">

            <div class="panel-header">

                <h2 class="panel-title">
                    <span class="panel-title-icon">
                        <i class="bi bi-bell"></i>
                    </span>
                    Notification Center
                </h2>

            </div>


            <div class="panel-body">

                <?php if ($notifications): ?>

                    <div class="notification-list">

                        <?php foreach ($notifications as $notification): ?>

                            <?php
                            $moduleIcon =
                                notificationModuleIcon(
                                    $notification['module']
                                    ??
                                    null
                                );

                            $moduleColor =
                                notificationModuleColor(
                                    $notification['module']
                                    ??
                                    null
                                );

                            $isUnread =
                                (int)$notification['is_read'] === 0;
                            ?>

                            <article
                                class="notification-item <?= $isUnread ? 'unread' : ''; ?>"
                                data-notification-id="<?= (int)$notification['notification_id']; ?>"
                            >

                                <div class="notification-icon <?= e($moduleColor); ?>">
                                    <i class="bi <?= e($moduleIcon); ?>"></i>
                                </div>


                                <div class="notification-content">

                                    <div class="notification-title-line">

                                        <?php if ($isUnread): ?>
                                            <span class="notification-unread-dot"></span>
                                        <?php endif; ?>

                                        <h3>
                                            <?= e($notification['title']); ?>
                                        </h3>

                                    </div>


                                    <p>
                                        <?= e($notification['message']); ?>
                                    </p>


                                    <div class="notification-meta">

                                        <span>
                                            <i class="bi bi-clock me-1"></i>
                                            <?= !empty($notification['created_at'])
                                                ? e(
                                                    date(
                                                        'M d, Y h:i A',
                                                        strtotime(
                                                            $notification['created_at']
                                                        )
                                                    )
                                                )
                                                : '—'; ?>
                                        </span>

                                        <?php if (!empty($notification['module'])): ?>

                                            <span>
                                                <i class="bi bi-folder2-open me-1"></i>
                                                <?= e(ucfirst($notification['module'])); ?>
                                            </span>

                                        <?php endif; ?>

                                        <span>
                                            <?= $isUnread ? 'Unread' : 'Read'; ?>
                                        </span>

                                    </div>

                                </div>


                                <div class="notification-item-actions">

                                    <?php if (!empty($notification['action_url'])): ?>

                                        <a
                                            href="<?= BASE_URL; ?>/notifications/index.php?open=<?= (int)$notification['notification_id']; ?>"
                                            class="btn btn-sm btn-asu notification-open-link"
                                            title="Open notification"
                                            aria-label="Open notification"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>

                                    <?php endif; ?>


                                    <?php if ($isUnread): ?>

                                        <form method="post" action="">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="mark_read"
                                            >
                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= (int)$notification['notification_id']; ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-asu"
                                                title="Mark as read"
                                                aria-label="Mark notification as read"
                                            >
                                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>


                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-confirm-open
                                        data-confirm-title="Delete this notification?"
                                        data-confirm-message="This notification will be permanently removed from your list."
                                        data-confirm-action="delete"
                                        data-confirm-id="<?= (int)$notification['notification_id']; ?>"
                                        data-confirm-button="Delete"
                                        title="Delete notification"
                                        aria-label="Delete notification"
                                    >
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="notification-empty-state">

                        <i class="bi bi-bell-slash"></i>

                        <h3>
                            No notifications found
                        </h3>

                        <p>
                            Updates from research, extension, reports, and system actions will appear here.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- =================================================
             CUSTOM CONFIRMATION POPUP
        ================================================== -->

        <div
            class="notification-confirm-backdrop"
            id="notificationConfirmBackdrop"
            aria-hidden="true"
        >

            <div
                class="notification-confirm-box"
                role="dialog"
                aria-modal="true"
                aria-labelledby="notificationConfirmTitle"
            >

                <div class="notification-confirm-head">

                    <div class="notification-confirm-icon">
                        <i class="bi bi-bell"></i>
                    </div>

                    <h3 id="notificationConfirmTitle">
                        Confirm action
                    </h3>

                    <p id="notificationConfirmMessage">
                        Please confirm this action.
                    </p>

                </div>


                <form
                    method="post"
                    action=""
                    id="notificationConfirmForm"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        id="notificationConfirmAction"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="notification_id"
                        id="notificationConfirmId"
                        value=""
                    >


                    <div class="notification-confirm-actions">

                        <button
                            type="button"
                            class="btn notification-confirm-cancel"
                            id="notificationConfirmCancel"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="btn notification-confirm-delete"
                            id="notificationConfirmSubmit"
                        >
                            Confirm
                        </button>

                    </div>

                </form>

            </div>

        </div>
</main>

</div>




<script>
document.addEventListener('DOMContentLoaded', function () {
    const backdrop = document.getElementById('notificationConfirmBackdrop');
    const form = document.getElementById('notificationConfirmForm');
    const title = document.getElementById('notificationConfirmTitle');
    const message = document.getElementById('notificationConfirmMessage');
    const actionInput = document.getElementById('notificationConfirmAction');
    const idInput = document.getElementById('notificationConfirmId');
    const submitButton = document.getElementById('notificationConfirmSubmit');
    const cancelButton = document.getElementById('notificationConfirmCancel');

    if (!backdrop || !form) return;

    // The popup stays centered on the viewport even if the page scrolls.
    document.body.appendChild(backdrop);
    let openedBy = null;
    let busy = false;

    function openConfirm(button) {
        if (busy) return;
        openedBy = button;
        title.textContent = button.dataset.confirmTitle || 'Confirm action';
        message.textContent = button.dataset.confirmMessage || 'Please confirm this action.';
        actionInput.value = button.dataset.confirmAction || '';
        idInput.value = button.dataset.confirmId || '';
        submitButton.textContent = button.dataset.confirmButton || 'Confirm';

        // Never toggle body/main overflow: removing its scrollbar causes the
        // sidebar and topbar to shift sideways.
        backdrop.classList.add('show');
        backdrop.setAttribute('aria-hidden', 'false');
        cancelButton.focus({ preventScroll: true });
    }

    function closeConfirm() {
        if (busy) return;
        backdrop.classList.remove('show');
        backdrop.setAttribute('aria-hidden', 'true');
        actionInput.value = '';
        idInput.value = '';
        if (openedBy && openedBy.isConnected) {
            openedBy.focus({ preventScroll: true });
        }
        openedBy = null;
    }

    // Prevent wheel and touch scroll chaining without modifying scrollbar widths.
    backdrop.addEventListener('wheel', function (event) {
        if (backdrop.classList.contains('show')) event.preventDefault();
    }, { passive: false });
    backdrop.addEventListener('touchmove', function (event) {
        if (backdrop.classList.contains('show')) event.preventDefault();
    }, { passive: false });

    document.querySelectorAll('[data-confirm-open]').forEach(function (button) {
        button.addEventListener('click', function () { openConfirm(button); });
    });

    function updateCounters(data) {
        const links = document.querySelectorAll('.notification-tabs .notification-tab');
        links.forEach(function (link) {
            const counter = link.querySelector('span');
            if (!counter) return;
            const url = new URL(link.href, window.location.href);
            const filter = url.searchParams.get('filter') || 'all';
            const amount = filter === 'unread' ? data.unread
                : filter === 'read' ? data.read : data.total;
            counter.textContent = Number(amount || 0).toLocaleString();
        });

        const markAll = document.querySelector(
            '.notification-actions form input[name="action"][value="mark_all_read"]'
        );
        if (markAll) {
            const button = markAll.closest('form').querySelector('button[type="submit"]');
            if (button) {
                button.disabled = Number(data.unread) === 0;
                button.title = button.disabled
                    ? 'All notifications are already read'
                    : 'Mark all your unread notifications as read';
            }
        }

        // Keep the button's place instead of shifting the header when no
        // read notifications remain. The next normal load may hide it.
        const clearRead = document.querySelector('[data-confirm-action="delete_read"]');
        if (clearRead) {
            clearRead.disabled = Number(data.read) === 0;
            clearRead.title = clearRead.disabled ? 'No read notifications to clear' : 'Clear read';
        }
    }

    function updateList(action, deletedId) {
        const list = document.querySelector('.notification-list');
        if (!list) return;

        if (action === 'delete_read') {
            list.querySelectorAll('.notification-item:not(.unread)').forEach(function (item) {
                item.remove();
            });
        } else if (action === 'delete') {
            list.querySelectorAll('.notification-item').forEach(function (item) {
                if (item.dataset.notificationId === String(deletedId)) item.remove();
            });
        }

        if (!list.querySelector('.notification-item')) {
            const empty = document.createElement('div');
            empty.className = 'notification-empty-state';
            empty.innerHTML = '<i class="bi bi-bell-slash" aria-hidden="true"></i>'
                + '<h3>No notifications found</h3>'
                + '<p>Updates from research, extension, reports, and system actions will appear here.</p>';
            list.replaceWith(empty);
        }
    }

    // Delete and Clear read run in-place: no full page refresh or scroll jump.
    // With JavaScript disabled the original PHP POST + redirect still works.
    form.addEventListener('submit', async function (event) {
        const action = actionInput.value;
        if (!['delete', 'delete_read'].includes(action) || !window.fetch) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        const deletedId = idInput.value;
        submitButton.disabled = true;
        cancelButton.disabled = true;

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new FormData(form)
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error('Deletion was not confirmed');

            updateList(action, deletedId);
            updateCounters(data);
            busy = false;
            closeConfirm();
        } catch (error) {
            message.textContent = 'Unable to complete the action. Please try again.';
        } finally {
            busy = false;
            submitButton.disabled = false;
            cancelButton.disabled = false;
        }
    });

    cancelButton.addEventListener('click', closeConfirm);
    backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop) closeConfirm();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && backdrop.classList.contains('show')) {
            closeConfirm();
        }
    });
});
</script>


<?php

require __DIR__ . '/../includes/footer.php';

?>
