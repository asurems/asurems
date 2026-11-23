<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
$pageTitle =
    $pageTitle
    ?? APP_NAME;
$activePage =
    $activePage
    ?? '';
/*
 * Shared ASU-REMS branding.
 * Load the logo from its real path on the XAMPP server, not a browser URL.
 * This avoids broken logo icons if the web path/cache is stale.
 * The fallback URL is still available if the file is not present.
 */
$asuRemsLogoFile = dirname(__DIR__) . '/assets/images/asu-rems-logo.png';
$asuRemsLogoSrc = BASE_URL . '/assets/images/asu-rems-logo.png?v=20261117';
if (is_file($asuRemsLogoFile) && is_readable($asuRemsLogoFile)) {
    $asuRemsLogoBytes = file_get_contents($asuRemsLogoFile);
    if ($asuRemsLogoBytes !== false && str_starts_with($asuRemsLogoBytes, "\x89PNG\r\n\x1a\n")) {
        $asuRemsLogoSrc = 'data:image/png;base64,' . base64_encode($asuRemsLogoBytes);
    }
    unset($asuRemsLogoBytes);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>
        <?= e($pageTitle); ?> | <?= APP_NAME; ?>
    </title>
    <!-- ASU-REMS shared brand icon (browser tab). -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <!-- ASU REMS CSS -->
    <link
        rel="stylesheet"
        href="<?= BASE_URL; ?>/assets/css/style.css"
    >
    <!-- ASU-REMS font family only: Century Gothic with safe fallbacks. -->
    <style>
        html,
        body,
        button,
        input,
        select,
        textarea,
        optgroup,
        option,
        table,
        th,
        td {
            font-family: "Century Gothic", "CenturyGothic", "AppleGothic", Arial, sans-serif !important;
        }
    </style>
    <?php require_once __DIR__ . '/rems_theme.php'; ?>
</head>
<body>
<div class="app-wrapper">
<?php if (isset($_SESSION['user_id'])): ?>
<script>
(function () {
    /*
    |--------------------------------------------------------------------------
    | ASU-REMS INACTIVITY LOGOUT
    |--------------------------------------------------------------------------
    */
    const BASE_URL =
        <?= json_encode(BASE_URL); ?>;
    const TIMEOUT_MS =
        <?= (int) SESSION_IDLE_TIMEOUT * 1000; ?>;
    /*
     * Server synchronization.
     *
     * 5-second test:
     * approximately every 2 seconds when active.
     *
     * 20-minute final:
     * maximum once per minute.
     */
    const SERVER_SYNC_INTERVAL =
        Math.min(
            60 * 1000,
            Math.max(
                1000,
                Math.floor(TIMEOUT_MS / 2)
            )
        );
    const ACTIVITY_KEY =
        'asu_rems_last_activity';
    const SYNC_KEY =
        'asu_rems_last_server_sync';
    let inactivityTimer = null;
    let logoutStarted = false;
    let eventThrottle = false;
    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    function logoutForInactivity() {
        if (logoutStarted) {
            return;
        }
        logoutStarted = true;
        localStorage.removeItem(
            ACTIVITY_KEY
        );
        localStorage.removeItem(
            SYNC_KEY
        );
        window.location.href =
            BASE_URL
            + '/logout.php?reason=timeout';
    }
    /*
    |--------------------------------------------------------------------------
    | RESET AUTOMATIC LOGOUT TIMER
    |--------------------------------------------------------------------------
    */
    function resetLogoutTimer() {
        if (inactivityTimer) {
            clearTimeout(inactivityTimer);
        }
        const lastActivity =
            Number(
                localStorage.getItem(
                    ACTIVITY_KEY
                )
                || Date.now()
            );
        const elapsed =
            Date.now()
            - lastActivity;
        const remaining =
            TIMEOUT_MS
            - elapsed;
        /*
         * Already expired.
         */
        if (remaining <= 0) {
            logoutForInactivity();
            return;
        }
        inactivityTimer =
            setTimeout(
                logoutForInactivity,
                remaining
            );
    }
    /*
    |--------------------------------------------------------------------------
    | SYNC REAL USER ACTIVITY WITH PHP SESSION
    |--------------------------------------------------------------------------
    */
    function syncActivityWithServer() {
        if (logoutStarted) {
            return;
        }
        const now =
            Date.now();
        const lastSync =
            Number(
                localStorage.getItem(
                    SYNC_KEY
                )
                || 0
            );
        if (
            now - lastSync
            < SERVER_SYNC_INTERVAL
        ) {
            return;
        }
        localStorage.setItem(
            SYNC_KEY,
            String(now)
        );
        fetch(
            BASE_URL
            + '/session_activity.php',
            {
                method: 'POST',
                credentials:
                    'same-origin',
                cache:
                    'no-store',
                headers: {
                    'X-Requested-With':
                        'XMLHttpRequest'
                }
            }
        )
        .then(function (response) {
            if (
                response.status === 401
            ) {
                logoutForInactivity();
            }
        })
        .catch(function () {
            /*
             * Do nothing if there is a temporary
             * network problem.
             */
        });
    }
    /*
    |--------------------------------------------------------------------------
    | RECORD USER ACTIVITY
    |--------------------------------------------------------------------------
    */
    function recordActivity() {
        if (logoutStarted) {
            return;
        }
        /*
         * Prevent mousemove / scroll from running
         * hundreds of times per second.
         */
        if (eventThrottle) {
            return;
        }
        eventThrottle = true;
        setTimeout(
            function () {
                eventThrottle = false;
            },
            500
        );
        const now =
            Date.now();
        const previousActivity =
            Number(
                localStorage.getItem(
                    ACTIVITY_KEY
                )
                || now
            );
        /*
         * Important:
         * If the user already exceeded the timeout,
         * do not allow a late mouse movement to
         * revive the expired session.
         */
        if (
            now - previousActivity
            >= TIMEOUT_MS
        ) {
            logoutForInactivity();
            return;
        }
        localStorage.setItem(
            ACTIVITY_KEY,
            String(now)
        );
        resetLogoutTimer();
        syncActivityWithServer();
    }
    /*
    |--------------------------------------------------------------------------
    | INITIALIZE PAGE
    |--------------------------------------------------------------------------
    */
    function initializeActivityTimer() {
        const now =
            Date.now();
        const savedActivity =
            Number(
                localStorage.getItem(
                    ACTIVITY_KEY
                )
                || 0
            );
        /*
         * First protected page after login.
         */
        if (!savedActivity) {
            localStorage.setItem(
                ACTIVITY_KEY,
                String(now)
            );
            localStorage.setItem(
                SYNC_KEY,
                '0'
            );
        } else if (
            now - savedActivity
            >= TIMEOUT_MS
        ) {
            logoutForInactivity();
            return;
        }
        resetLogoutTimer();
    }
    /*
    |--------------------------------------------------------------------------
    | ACTIVITY EVENTS
    |--------------------------------------------------------------------------
    */
    [
        'mousedown',
        'mousemove',
        'keydown',
        'scroll',
        'touchstart',
        'click'
    ].forEach(
        function (eventName) {
            window.addEventListener(
                eventName,
                recordActivity,
                {
                    passive: true
                }
            );
        }
    );
    /*
    |--------------------------------------------------------------------------
    | WHEN USER RETURNS TO TAB
    |--------------------------------------------------------------------------
    */
    document.addEventListener(
        'visibilitychange',
        function () {
            if (
                document.visibilityState
                !== 'visible'
            ) {
                return;
            }
            const lastActivity =
                Number(
                    localStorage.getItem(
                        ACTIVITY_KEY
                    )
                    || 0
                );
            if (
                !lastActivity
                ||
                Date.now() - lastActivity
                >= TIMEOUT_MS
            ) {
                logoutForInactivity();
                return;
            }
            resetLogoutTimer();
        }
    );
    /*
    |--------------------------------------------------------------------------
    | MULTIPLE TAB SUPPORT
    |--------------------------------------------------------------------------
    */
    window.addEventListener(
        'storage',
        function (event) {
            if (
                event.key
                === ACTIVITY_KEY
            ) {
                resetLogoutTimer();
            }
        }
    );
    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */
    initializeActivityTimer();
})();
</script>
<?php endif; ?>
