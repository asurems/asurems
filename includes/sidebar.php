<?php



$user = currentUser();



$activePage = $activePage ?? '';

$currentRole = $user['role'] ?? '';

$currentView = $_GET['view'] ?? '';



$sidebarScriptPath = (string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);
$sidebarServicesActive = (bool) preg_match('~/extension/services\.php$~', $sidebarScriptPath);
$sidebarServiceSection = $sidebarServicesActive ? (string)($_GET['section'] ?? '') : '';
$sidebarServiceSections = [
    'service_requests',
    'schedule_assignments',
    'completed_services',
    'request_service',
    'my_service_requests',
];
if (!in_array($sidebarServiceSection, $sidebarServiceSections, true)) {
    $sidebarServiceSection = '';
}
$sidebarMonitoringActive = (bool) preg_match('~/extension/monitoring\.php$~', $sidebarScriptPath);
$sidebarMonitoringSection = $sidebarMonitoringActive ? (string)($_GET['section'] ?? '') : '';
$sidebarMonitoringSections = $currentRole === 'extension_service_office'
    ? ['monitoring_requests', 'monitoring_schedule', 'monitoring_reports']
    : ['request_monitoring', 'my_monitoring_requests', 'submit_monitoring_report'];
if (!in_array($sidebarMonitoringSection, $sidebarMonitoringSections, true)) {
    $sidebarMonitoringSection = '';
}




/*

|--------------------------------------------------------------------------

| NOTIFICATION BADGE COUNT

|--------------------------------------------------------------------------

*/



$sidebarUnreadNotifications = 0;



$notificationHelperPath = __DIR__ . '/notifications.php';



if (is_file($notificationHelperPath)) {

    require_once $notificationHelperPath;

}



if (

    isset($pdo)

    && function_exists('unreadNotificationCount')

    && !empty($user['user_id'])

) {

    try {

        $sidebarUnreadNotifications = unreadNotificationCount(

            $pdo,

            (int)$user['user_id']

        );

    } catch (Throwable $e) {

        $sidebarUnreadNotifications = 0;

    }

}



/*
|--------------------------------------------------------------------------
| RPS PROCESSING OVERVIEW - NONEMPTY QUEUE INDICATOR
|--------------------------------------------------------------------------
| Show the dot as long as ANY RPS processing queue contains an unarchived
| record, even if the record is waiting for faculty pathway selection.
| The dot disappears only when ALL processing queues are empty.
| Updates on each page load/refresh.
*/

$sidebarHasPendingRpsResearch = false;

if ($currentRole === 'rps' && isset($pdo) && $pdo instanceof PDO) {
    try {
        $sidebarPendingResearchStmt = $pdo->query("
            SELECT 1
            FROM research_proposals rp
            WHERE rp.is_archived = 0
              AND rp.workflow_status IN (
                  'SUBMITTED',
                  'AWAITING_PATHWAY_SELECTION',
                  'FOR_URRC_PATH_SELECTED',
                  'FOR_URRC',
                  'URRC_REVISED_SUBMITTED',
                  'FOR_INHOUSE',
                  'INHOUSE_REVISED_SUBMITTED',
                  'FOR_ETHICS_REVIEW',
                  'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS',
                  'READY_FOR_NTP',
                  'TERMINAL_REPORT_SUBMITTED'
              )
            LIMIT 1
        ");
        $sidebarHasPendingRpsResearch = $sidebarPendingResearchStmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        error_log('RPS sidebar processing dot check: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| ESO PROPOSAL PROCESSING - NONEMPTY QUEUE INDICATOR
|--------------------------------------------------------------------------
| Match the eight queues defined in extension/index.php. The orange dot
| remains while ANY proposal processing queue contains a record (including
| Approved Proposals), and disappears only when ALL are empty.
| This read-only check runs on each page load or refresh.
*/
$sidebarHasEsoProposalRecords = false;

if ($currentRole === 'extension_service_office' && isset($pdo) && $pdo instanceof PDO) {
    try {
        $sidebarEsoProposalStmt = $pdo->query("
            SELECT 1
            FROM extension_programs ep
            WHERE ep.status IN (
                'Submitted',
                'UERC Technical Review',
                'For Pre-In-House Endorsement',
                'University In-House Review',
                'For Endorsement',
                'For LADCO/ADCO Approval',
                'For BOR Notation',
                'For Certification',
                'Approved'
            )
            LIMIT 1
        ");
        $sidebarHasEsoProposalRecords = $sidebarEsoProposalStmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        error_log('ESO sidebar proposal dot check: ' . $e->getMessage());
    }
}

$isDashboardActive =

    (

        $currentRole === 'admin'

        && $activePage === 'admin_dashboard'

    )

    || (

        $currentRole !== 'admin'

        && $activePage === 'dashboard'

        && $currentView !== 'processing'

    );



$isProcessingActive =

    $activePage === 'processing'

    || (

        $currentRole === 'rps'

        && $activePage === 'dashboard'

        && $currentView === 'processing'

    );



?>



<style>



.asu-sidebar {

    display: flex;

    flex-direction: column;

    height: 100vh;

    max-height: 100vh;

    overflow: hidden;

}



.asu-sidebar .sidebar-brand {

    min-height: 105px;

}



.asu-sidebar .sidebar-menu {

    flex: 1;

    min-height: 0;

    display: flex;

    flex-direction: column;

    padding-top: 12px;

    padding-bottom: 8px;

    overflow: hidden;

}



.asu-sidebar .sidebar-scroll-area {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    overflow-x: hidden;

    padding-bottom: 8px;

    scrollbar-width: thin;

    scrollbar-color: rgba(255,255,255,.28) transparent;

}



.asu-sidebar .sidebar-scroll-area::-webkit-scrollbar {

    width: 5px;

}



.asu-sidebar .sidebar-scroll-area::-webkit-scrollbar-track {

    background: transparent;

}



.asu-sidebar .sidebar-scroll-area::-webkit-scrollbar-thumb {

    background: rgba(255,255,255,.24);

    border-radius: 999px;

}



.asu-sidebar .sidebar-link {

    position: relative;

    display: flex;

    align-items: center;

}



.asu-sidebar .sidebar-menu-spacer {

    display: none;

}



.asu-sidebar .sidebar-logout {

    flex-shrink: 0;

    margin: 8px 14px 4px;

    padding-top: 14px;

    border-top: 1px solid rgba(255, 255, 255, 0.12);

    background: rgba(0, 79, 34, .16);

}



.asu-sidebar .sidebar-logout:hover {

    background: rgba(255, 255, 255, 0.10);

    color: #ffffff;

}



.asu-sidebar .sidebar-footer {

    padding-top: 10px;

    padding-bottom: 18px;

}



.asu-sidebar .sidebar-notification-badge,

.asu-sidebar .sidebar-soft-badge {

    min-width: 22px;

    height: 22px;

    margin-left: auto;

    padding: 0 7px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 999px;

    color: #ffffff;

    font-size: 10px;

    font-weight: 800;

    line-height: 1;

    box-shadow: 0 3px 8px rgba(0, 0, 0, .16);

}



.asu-sidebar .sidebar-notification-badge {

    background: #dc3545;

}



.asu-sidebar .sidebar-soft-badge {

    background: rgba(255, 255, 255, .18);

}



.asu-sidebar .sidebar-link.active .sidebar-notification-badge,

.asu-sidebar .sidebar-link.active .sidebar-soft-badge {

    background: #08752c;

    color: #ffffff;

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





/* Small orange status dot for the RPS Processing Overview and ESO Proposal Processing links. */
.asu-sidebar .sidebar-link > .sidebar-processing-dot {
    display: inline-block !important;
    flex: 0 0 10px !important;
    width: 10px !important;
    min-width: 10px !important;
    height: 10px !important;
    min-height: 10px !important;
    margin-left: auto !important;
    padding: 0 !important;
    border: 1px solid rgba(255, 255, 255, .70) !important;
    border-radius: 50% !important;
    background: #F28C28 !important;
    box-shadow: none !important;
    line-height: 0 !important;
}

/* Sidebar-specific notification color */

.sidebar-notification-badge,

.notification-count {

    background: #F28C28 !important;

    color: #FFFFFF !important;

}





/* =========================================================
   ASU-REMS SIDEBAR — STEADY / NO HOVER EXPANSION
   Desktop: always expanded at 272px; no mouse hover resizing.
   Mobile / tablet: click topbar menu to open or close.
   No width, position, text or slide animations.
========================================================= */
:root {
    --asu-sidebar-expanded-width: 272px;
}

@media (min-width: 992px) {
    .asu-sidebar {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        width: var(--asu-sidebar-expanded-width) !important;
        min-width: var(--asu-sidebar-expanded-width) !important;
        max-width: var(--asu-sidebar-expanded-width) !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
        z-index: 1050 !important;
    }

    .main-page {
        width: calc(100% - var(--asu-sidebar-expanded-width)) !important;
        margin-left: var(--asu-sidebar-expanded-width) !important;
        transition: none !important;
        animation: none !important;
    }

    .asu-sidebar .sidebar-brand {
        min-height: 105px !important;
        padding-left: 18px !important;
        padding-right: 14px !important;
        display: flex !important;
        align-items: center !important;
        gap: 11px !important;
    }

    .asu-sidebar .brand-logo {
        width: 54px !important;
        height: 54px !important;
        min-width: 54px !important;
        flex: 0 0 54px !important;
    }

    .asu-sidebar .brand-text,
    .asu-sidebar .sidebar-link > span:not(.sidebar-notification-badge):not(.sidebar-soft-badge):not(.sidebar-processing-dot),
    .asu-sidebar .sidebar-footer small {
        opacity: 1 !important;
        visibility: visible !important;
        transition: none !important;
        animation: none !important;
    }

    .asu-sidebar .brand-text h4 {
        margin: 0 !important;
        font-size: 19px !important;
        white-space: nowrap !important;
    }

    .asu-sidebar .brand-text span {
        font-size: 11px !important;
        white-space: nowrap !important;
    }

    .asu-sidebar .sidebar-scroll-area {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .asu-sidebar .sidebar-link {
        min-height: 52px !important;
        height: auto !important;
        margin: 4px 0 !important;
        padding: 12px 14px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 11px !important;
        border-radius: 11px !important;
        font-size: 14px !important;
        line-height: 1.28 !important;
        white-space: normal !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
    }

    .asu-sidebar .sidebar-link > i {
        width: 28px !important;
        min-width: 28px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 !important;
        font-size: 19px !important;
        line-height: 1 !important;
    }

    .asu-sidebar .sidebar-logout {
        min-height: 52px !important;
        margin: 8px 12px 4px !important;
        padding-left: 14px !important;
        padding-right: 14px !important;
    }
}

@media (max-width: 991.98px) {
    .asu-sidebar {
        width: 270px !important;
        max-width: min(270px, 85vw) !important;
        transform: translateX(-100%) !important;
        transition: none !important;
        animation: none !important;
        z-index: 1040 !important;
    }

    .asu-sidebar.open {
        transform: translateX(0) !important;
    }

    .main-page {
        width: 100% !important;
        margin-left: 0 !important;
        transition: none !important;
    }

    .sidebar-toggle {
        display: inline-block !important;
    }

    .sidebar-overlay {
        position: fixed !important;
        inset: 0 !important;
        background: rgba(0, 0, 0, .38) !important;
        z-index: 1035 !important;
    }

    .sidebar-overlay.show {
        display: block !important;
    }
}


/* =========================================================
   ASU-REMS SHARED MOBILE HEADER — SAME FORMAT ON EVERY PAGE
   Mobile: [menu] [green line | system + university] [avatar]
   Includes Dashboard, Research, Monitoring and other pages
   using either shared topbar. Desktop remains unchanged.
========================================================= */
@media (max-width: 991.98px) {
    html body .main-page > header.asu-topbar,
    html body .main-page > header.asu-dashboard-topbar {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        width: 100% !important;
        height: 80px !important;
        min-height: 80px !important;
        padding: 0 14px !important;
        box-sizing: border-box !important;
        background: #ffffff !important;
        border-bottom: 1px solid rgba(0, 107, 46, .12) !important;
    }

    html body .main-page > header.asu-topbar .topbar-left,
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        gap: 10px !important;
    }

    /* Menu: only appears on mobile; stable position and no animation. */
    html body .main-page > header.asu-topbar .sidebar-toggle,
    html body .main-page > header.asu-dashboard-topbar .sidebar-toggle {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        order: 0 !important;
        flex: 0 0 36px !important;
        min-width: 36px !important;
        width: 36px !important;
        min-height: 40px !important;
        height: 40px !important;
        padding: 0 !important;
        margin: 0 !important;
        color: #005c26 !important;
        background: transparent !important;
        border: 0 !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        font-size: 25px !important;
        line-height: 1 !important;
        cursor: pointer !important;
        transition: none !important;
        animation: none !important;
    }

    html body .main-page > header.asu-topbar .sidebar-toggle:focus-visible,
    html body .main-page > header.asu-dashboard-topbar .sidebar-toggle:focus-visible {
        outline: 2px solid #007a33 !important;
        outline-offset: 2px !important;
    }

    /* Standard subpage: its green divider is part of brand copy. */
    html body .main-page > header.asu-topbar .system-brand-copy {
        position: relative !important;
        display: block !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        padding-left: 18px !important;
        margin: 0 !important;
    }

    html body .main-page > header.asu-topbar .system-brand-copy::before {
        content: '' !important;
        position: absolute !important;
        display: block !important;
        left: 0 !important;
        top: 50% !important;
        width: 3px !important;
        height: 44px !important;
        transform: translateY(-50%) !important;
        border-radius: 4px !important;
        background: #007a33 !important;
    }

    /* Dashboard: reorder divider after hamburger, same as screenshot. */
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline::before {
        content: '' !important;
        display: block !important;
        order: 1 !important;
        flex: 0 0 3px !important;
        width: 3px !important;
        height: 44px !important;
        margin: 0 4px 0 0 !important;
        border-radius: 4px !important;
        background: #007a33 !important;
    }

    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline > div {
        order: 2 !important;
        display: block !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    html body .main-page > header.asu-topbar .system-brand-copy strong,
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline strong {
        display: block !important;
        margin: 0 !important;
        color: #101810 !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
        letter-spacing: 0 !important;
        overflow-wrap: normal !important;
    }

    html body .main-page > header.asu-topbar .system-brand-copy span,
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline span {
        display: block !important;
        margin-top: 3px !important;
        color: #59665d !important;
        font-size: 10px !important;
        font-weight: 400 !important;
        line-height: 1.2 !important;
    }

    /* The mobile screenshot shows the avatar only, not the full user name. */
    html body .main-page > header.asu-topbar .topbar-user,
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-actions,
    html body .main-page > header.asu-dashboard-topbar .dashboard-user-mini {
        display: flex !important;
        flex: 0 0 auto !important;
        align-items: center !important;
        min-width: 0 !important;
        gap: 0 !important;
    }

    html body .main-page > header.asu-topbar .topbar-user-text,
    html body .main-page > header.asu-dashboard-topbar .dashboard-user-copy {
        display: none !important;
    }

    html body .main-page > header.asu-topbar .user-avatar,
    html body .main-page > header.asu-dashboard-topbar .dashboard-avatar {
        display: inline-flex !important;
        flex: 0 0 42px !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 42px !important;
        width: 42px !important;
        height: 42px !important;
        margin: 0 !important;
        border-radius: 50% !important;
        background: #ffd51f !important;
        color: #003f1a !important;
        font-size: 15px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        transition: none !important;
    }
}

@media (max-width: 370px) {
    html body .main-page > header.asu-topbar,
    html body .main-page > header.asu-dashboard-topbar {
        padding-right: 10px !important;
        padding-left: 10px !important;
        gap: 7px !important;
    }
    html body .main-page > header.asu-topbar .system-brand-copy strong,
    html body .main-page > header.asu-dashboard-topbar .asu-dashboard-brandline strong {
        font-size: 12px !important;
    }
}


/* =========================================================
   ASU-REMS — INSTANT SIDEBAR NAVIGATION (NO MOTION)
   Remove inherited hover/active/menu and page-entry transitions.
   Keep mobile open/close logic, dropdowns, and data functions intact.
========================================================= */
.asu-sidebar,
.asu-sidebar .sidebar-brand,
.asu-sidebar .sidebar-brand *,
.asu-sidebar .sidebar-link,
.asu-sidebar .sidebar-link *,
.asu-sidebar .sidebar-link::before,
.asu-sidebar .sidebar-link::after,
.asu-sidebar .sidebar-menu,
.asu-sidebar .sidebar-scroll-area,
.asu-sidebar .sidebar-footer,
.sidebar-overlay,
.main-page,
.main-page > header.asu-topbar,
.main-page > header.asu-dashboard-topbar,
.main-page > .page-content,
.main-page > .page-content > * {
    animation: none !important;
    transition: none !important;
    animation-delay: 0s !important;
    transition-delay: 0s !important;
}

/* Instant active state; remove hover movement inherited from shared CSS. */
.asu-sidebar .sidebar-link,
.asu-sidebar .sidebar-link:hover,
.asu-sidebar .sidebar-link:focus,
.asu-sidebar .sidebar-link:active,
.asu-sidebar .sidebar-link.active {
    translate: none !important;
    scale: none !important;
    rotate: none !important;
}

/* Do not replace the mobile sidebar's translateX open/close behavior. */
@media (min-width: 992px) {
    .asu-sidebar .sidebar-link,
    .asu-sidebar .sidebar-link:hover,
    .asu-sidebar .sidebar-link:focus,
    .asu-sidebar .sidebar-link.active {
        transform: none !important;
    }
}


/* Keep long sidebar labels fully visible at desktop and mobile widths.
   Notification counters retain their separate flex sizing. */
.asu-sidebar .sidebar-link > span:not(.sidebar-notification-badge):not(.sidebar-soft-badge):not(.sidebar-processing-dot) {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    white-space: normal !important;
    overflow-wrap: break-word !important;
}

@media (max-width: 991.98px) {
    .asu-sidebar .sidebar-link {
        height: auto !important;
        white-space: normal !important;
    }
}

/* Extension-only expandable section: static, accessible, no animation. */
.asu-sidebar .sidebar-service-group {
    margin: 4px 0;
}
.asu-sidebar .sidebar-service-group > summary {
    list-style: none;
    cursor: pointer;
    width: 100%;
    user-select: none;
    box-sizing: border-box;
}
.asu-sidebar .sidebar-service-group > summary::-webkit-details-marker {
    display: none;
}
.asu-sidebar .sidebar-service-group > summary::marker {
    content: '';
}
.asu-sidebar .sidebar-service-group > summary > span {
    min-width: 0;
    flex: 1 1 auto;
    font-size: 12px;
    line-height: 1.35;
    white-space: normal;
}
.asu-sidebar .sidebar-service-chevron {
    width: auto !important;
    min-width: 0 !important;
    margin-left: auto !important;
    font-size: 13px !important;
    transform: none;
    transition: none !important;
}
.asu-sidebar .sidebar-service-group[open] .sidebar-service-chevron {
    transform: rotate(180deg) !important;
}
/* Highlight the selected submenu, not both parent and child. */
.asu-sidebar .sidebar-service-group > summary.active {
    background: rgba(255,255,255,.11) !important;
    color: #fff !important;
    box-shadow: none !important;
}
.asu-sidebar .sidebar-service-group > summary:focus-visible {
    outline: 2px solid #ffd51f;
    outline-offset: 2px;
}
.asu-sidebar .sidebar-service-submenu {
    margin: 2px 0 5px 20px;
    padding-left: 9px;
    border-left: 1px solid rgba(255,255,255,.25);
}
.asu-sidebar .sidebar-service-submenu .sidebar-link {
    min-height: 38px !important;
    height: auto !important;
    padding: 8px 10px !important;
    margin: 2px 0 !important;
    gap: 8px !important;
    font-size: 11px !important;
    line-height: 1.35;
    white-space: normal !important;
}
.asu-sidebar .sidebar-service-submenu .sidebar-link > i {
    width: 18px !important;
    min-width: 18px !important;
    font-size: 13px !important;
}
.asu-sidebar .sidebar-service-submenu .sidebar-link > span {
    min-width: 0;
    white-space: normal !important;
    font-size: 11px !important;
}

/* ASU-REMS SIDEBAR TYPOGRAPHY: font family only.
   Preserve existing font sizes, weights, colors, spacing and Bootstrap Icons. */
.asu-sidebar,
.asu-sidebar .brand-text,
.asu-sidebar .brand-text h4,
.asu-sidebar .brand-text span,
.asu-sidebar .sidebar-link,
.asu-sidebar .sidebar-link > span,
.asu-sidebar .sidebar-service-group > summary,
.asu-sidebar .sidebar-service-group > summary > span,
.asu-sidebar .sidebar-service-submenu .sidebar-link,
.asu-sidebar .sidebar-footer,
.asu-sidebar .sidebar-footer small {
    font-family: "Century Gothic", Arial, sans-serif !important;
}


/* =========================================================
   ASU-REMS DARK SIDEBAR
   Follows the shared data-rems-theme setting in rems_theme.php.
   Colors only: all role-based menus, scroll, layout and JS stay intact.
========================================================= */
html[data-rems-theme="dark"] body .asu-sidebar {
    background: linear-gradient(180deg, #073421 0%, #05281a 55%, #031b13 100%) !important;
    color: #edf5ec !important;
    border-right: 1px solid rgba(207, 230, 207, .13) !important;
    box-shadow: 12px 0 30px rgba(0, 0, 0, .26) !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-brand {
    border-bottom-color: rgba(220, 239, 220, .13) !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .brand-text h4 {
    color: #f5faf4 !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .brand-text span {
    color: #c2d9c6 !important;
}
/* The ASU-REMS logo keeps its original white round background. */
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-link:not(.active) {
    color: #dbeade !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-link:not(.active):hover,
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-link:not(.active):focus-visible {
    background: rgba(214, 238, 216, .11) !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-link.active,
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-service-submenu .sidebar-link.active {
    background: #ffd51f !important;
    color: #142b1d !important;
    box-shadow: 0 6px 15px rgba(0, 0, 0, .18) !important;
}
/* Expanded section title remains green; selected child stays gold. */
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-service-group > summary.active {
    background: #164730 !important;
    color: #f1f7ee !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-service-submenu {
    border-left-color: rgba(213, 231, 214, .26) !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-soft-badge {
    background: rgba(213, 233, 216, .19) !important;
    color: #edf5ec !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-logout {
    background: rgba(255, 255, 255, .045) !important;
    border-top-color: rgba(214, 231, 215, .18) !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-logout:hover {
    background: rgba(215, 237, 218, .12) !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-footer {
    border-top-color: rgba(216, 236, 216, .15) !important;
    color: #c0d2c0 !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-footer small {
    color: #c0d2c0 !important;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-scroll-area {
    scrollbar-color: rgba(185, 209, 187, .35) transparent;
}
html[data-rems-theme="dark"] body .asu-sidebar .sidebar-scroll-area::-webkit-scrollbar-thumb {
    background: rgba(185, 209, 187, .35) !important;
}

</style>



<aside class="asu-sidebar">



    <div class="sidebar-brand">

        <div class="brand-logo">

            <img src="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="ASU-REMS logo" loading="eager" decoding="async">

        </div>

        <div class="brand-text">

            <h4>ASU-REMS</h4>

            <span>Aklan State University</span>

        </div>

    </div>



    <nav class="sidebar-menu">



        <div class="sidebar-scroll-area">

        <!-- My Profile: available to all authenticated roles. -->
        <a href="<?= BASE_URL; ?>/profile.php"
           class="sidebar-link <?= ($activePage === 'my_profile' || preg_match('~/profile\.php$~', $sidebarScriptPath)) ? 'active' : ''; ?>"
           <?= ($activePage === 'my_profile' || preg_match('~/profile\.php$~', $sidebarScriptPath)) ? 'aria-current="page"' : ''; ?>>
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>My Profile</span>
        </a>




        <a

            href="<?= $currentRole === 'admin' ? BASE_URL . '/admin/index.php' : BASE_URL . '/dashboard.php'; ?>"

            class="sidebar-link <?= $isDashboardActive ? 'active' : ''; ?>"

        >

            <i class="bi bi-grid"></i>

            <span>Dashboard</span>

        </a>



        <?php if ($currentRole === 'extension_service_office'): ?>

            <a
                href="<?= BASE_URL; ?>/extension/calls.php"
                class="sidebar-link <?= preg_match('~/extension/calls\.php$~', $sidebarScriptPath) ? 'active' : ''; ?>"
            >
                <i class="bi bi-megaphone"></i>
                <span>Call for Proposals</span>
            </a>

            <a
                href="<?= BASE_URL; ?>/extension/index.php?queue=submissions"
                class="sidebar-link <?= preg_match('~/extension/index\.php$~', $sidebarScriptPath) ? 'active' : ''; ?>"
            >
                <i class="bi bi-clipboard-check"></i>
                <span>Proposal Processing</span>
                <?php if ($sidebarHasEsoProposalRecords): ?>
                    <span class="sidebar-processing-dot" title="Extension proposals in processing queues" aria-label="Extension proposal processing queues contain records"></span>
                <?php endif; ?>
            </a>

            <details class="sidebar-service-group" <?= $sidebarServicesActive ? 'open' : ''; ?>>
                <summary class="sidebar-link <?= $sidebarServicesActive ? 'active' : ''; ?>">
                    <i class="bi bi-tools"></i>
                    <span>Training &amp; Technical Services</span>
                    <i class="bi bi-chevron-down sidebar-service-chevron" aria-hidden="true"></i>
                </summary>
                <div class="sidebar-service-submenu">
                    <a href="<?= BASE_URL; ?>/extension/services.php?section=service_requests"
                       class="sidebar-link <?= $sidebarServiceSection === 'service_requests' ? 'active' : ''; ?>">
                        <i class="bi bi-inbox"></i><span>Service Requests</span>
                    </a>
                    <a href="<?= BASE_URL; ?>/extension/services.php?section=schedule_assignments"
                       class="sidebar-link <?= $sidebarServiceSection === 'schedule_assignments' ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-check"></i><span>Schedule &amp; Assignments</span>
                    </a>
                    <a href="<?= BASE_URL; ?>/extension/services.php?section=completed_services"
                       class="sidebar-link <?= $sidebarServiceSection === 'completed_services' ? 'active' : ''; ?>">
                        <i class="bi bi-check-circle"></i><span>Completed Services</span>
                    </a>
                </div>
            </details>

            <details class="sidebar-service-group" <?= $sidebarMonitoringActive ? 'open' : ''; ?>>
                <summary class="sidebar-link <?= $sidebarMonitoringActive ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Monitoring &amp; Evaluation</span>
                    <i class="bi bi-chevron-down sidebar-service-chevron" aria-hidden="true"></i>
                </summary>
                <div class="sidebar-service-submenu">
                    <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_requests"
                       class="sidebar-link <?= $sidebarMonitoringSection === 'monitoring_requests' ? 'active' : ''; ?>">
                        <i class="bi bi-inbox"></i><span>Monitoring Requests</span>
                    </a>
                    <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_schedule"
                       class="sidebar-link <?= $sidebarMonitoringSection === 'monitoring_schedule' ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-check"></i><span>Monitoring Schedule</span>
                    </a>
                    <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_reports"
                       class="sidebar-link <?= $sidebarMonitoringSection === 'monitoring_reports' ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i><span>Monitoring Reports</span>
                    </a>
                </div>
            </details>

        <?php endif; ?>



        <?php if ($currentRole === 'rps'): ?>

            <a

                href="<?= BASE_URL; ?>/research/rps_queue.php?queue=submissions"

                class="sidebar-link <?= $isProcessingActive ? 'active' : ''; ?>"

            >

                <i class="bi bi-clipboard-check"></i>

                <span>Processing Overview</span>

                <?php if ($sidebarHasPendingRpsResearch): ?>
                    <span class="sidebar-processing-dot" title="Research records in processing queues" aria-label="Research processing queues contain records"></span>
                <?php endif; ?>

            </a>

        <?php endif; ?>



        <?php if ($currentRole === 'admin'): ?>

            <a href="<?= BASE_URL; ?>/admin/users/index.php" class="sidebar-link <?= $activePage === 'users' ? 'active' : ''; ?>">

                <i class="bi bi-people"></i><span>Users</span>

            </a>



            <a href="<?= BASE_URL; ?>/admin/master_data/index.php" class="sidebar-link <?= $activePage === 'master_data' ? 'active' : ''; ?>">

                <i class="bi bi-diagram-3"></i><span>Master Data</span>

            </a>



            <a href="<?= BASE_URL; ?>/admin/records/research.php" class="sidebar-link <?= $activePage === 'admin_research_records' ? 'active' : ''; ?>">

                <i class="bi bi-journal-text"></i><span>Research Records</span>

            </a>



            <a href="<?= BASE_URL; ?>/admin/records/extension.php" class="sidebar-link <?= $activePage === 'admin_extension_records' ? 'active' : ''; ?>">

                <i class="bi bi-megaphone"></i><span>Extension Records</span>

            </a>

        <?php else: ?>

            <?php if ($currentRole === 'faculty'): ?>

                <a href="<?= BASE_URL; ?>/research/index.php" class="sidebar-link <?= $activePage === 'research' ? 'active' : ''; ?>">

                    <i class="bi bi-journal-text"></i><span>Research</span>

                </a>

            <?php endif; ?>



            <?php if (in_array($currentRole, ['faculty', 'rps'], true)): ?>

                <a href="<?= BASE_URL; ?>/research/monitoring.php" class="sidebar-link <?= $activePage === 'project_monitoring' ? 'active' : ''; ?>">

                    <i class="bi bi-graph-up-arrow"></i><span>Project Monitoring</span>

                </a>

            <?php endif; ?>



            <?php if (in_array($currentRole, ['extension_coordinator', 'extensionist'], true)): ?>

                <a href="<?= BASE_URL; ?>/extension/index.php"
                   class="sidebar-link <?= $activePage === 'extension' && !$sidebarServicesActive ? 'active' : ''; ?>">
                    <i class="bi bi-megaphone"></i><span>My Proposals</span>
                </a>

                <details class="sidebar-service-group" <?= $sidebarServicesActive ? 'open' : ''; ?>>
                    <summary class="sidebar-link <?= $sidebarServicesActive ? 'active' : ''; ?>">
                        <i class="bi bi-tools"></i>
                        <span>Training &amp; Technical Services</span>
                        <i class="bi bi-chevron-down sidebar-service-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="sidebar-service-submenu">
                        <a href="<?= BASE_URL; ?>/extension/services.php?section=request_service"
                           class="sidebar-link <?= $sidebarServiceSection === 'request_service' ? 'active' : ''; ?>">
                            <i class="bi bi-plus-circle"></i><span>Request a Service</span>
                        </a>
                        <a href="<?= BASE_URL; ?>/extension/services.php?section=my_service_requests"
                           class="sidebar-link <?= $sidebarServiceSection === 'my_service_requests' ? 'active' : ''; ?>">
                            <i class="bi bi-list-check"></i><span>My Service Requests</span>
                        </a>
                    </div>
                </details>

                <details class="sidebar-service-group" <?= $sidebarMonitoringActive ? 'open' : ''; ?>>
                    <summary class="sidebar-link <?= $sidebarMonitoringActive ? 'active' : ''; ?>">
                        <i class="bi bi-clipboard2-pulse"></i>
                        <span>Monitoring &amp; Evaluation</span>
                        <i class="bi bi-chevron-down sidebar-service-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="sidebar-service-submenu">
                        <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=request_monitoring"
                           class="sidebar-link <?= $sidebarMonitoringSection === 'request_monitoring' ? 'active' : ''; ?>">
                            <i class="bi bi-plus-circle"></i><span>Request Monitoring</span>
                        </a>
                        <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests"
                           class="sidebar-link <?= $sidebarMonitoringSection === 'my_monitoring_requests' ? 'active' : ''; ?>">
                            <i class="bi bi-list-check"></i><span>My Monitoring Requests</span>
                        </a>
                        <a href="<?= BASE_URL; ?>/extension/monitoring.php?section=submit_monitoring_report"
                           class="sidebar-link <?= $sidebarMonitoringSection === 'submit_monitoring_report' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-arrow-up"></i><span>Submit Monitoring Report</span>
                        </a>
                    </div>
                </details>

            <?php endif; ?>

        <?php endif; ?>



        <a href="<?= BASE_URL; ?>/reports/index.php"
           class="sidebar-link <?= $activePage === 'reports' ? 'active' : ''; ?>">

            <i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span>

        </a>



        <?php if (in_array($currentRole, ['admin', 'rps', 'extension_service_office'], true)): ?>

            <a href="<?= BASE_URL; ?>/activity_logs/index.php" class="sidebar-link <?= $activePage === 'activity' ? 'active' : ''; ?>">

                <i class="bi bi-clock-history"></i><span>Activity Logs</span>

            </a>

        <?php endif; ?>



        <?php if ($currentRole === 'admin'): ?>

            <a href="<?= BASE_URL; ?>/admin/settings/index.php" class="sidebar-link <?= $activePage === 'settings' ? 'active' : ''; ?>">

                <i class="bi bi-gear"></i><span>System Settings</span>

            </a>



            <a href="<?= BASE_URL; ?>/admin/maintenance/index.php" class="sidebar-link <?= $activePage === 'maintenance' ? 'active' : ''; ?>">

                <i class="bi bi-database-down"></i><span>Backup &amp; Maintenance</span>

            </a>

        <?php endif; ?>



        <a href="<?= BASE_URL; ?>/notifications/index.php" class="sidebar-link <?= $activePage === 'notifications' ? 'active' : ''; ?>">

            <i class="bi bi-bell"></i><span>Notifications</span>

            <?php if ($sidebarUnreadNotifications > 0): ?>

                <span class="sidebar-notification-badge"><?= $sidebarUnreadNotifications > 99 ? '99+' : number_format($sidebarUnreadNotifications); ?></span>

            <?php endif; ?>

        </a>




        <?php if ($currentRole === 'rps' || $currentRole === 'extension_service_office'): ?>
            <!-- Strictly scoped, read-only staff profile directory. -->
            <a href="<?= BASE_URL; ?>/profiles.php"
               class="sidebar-link <?= ($activePage === 'profile_directory' || preg_match('~/profiles\.php$~', $sidebarScriptPath)) ? 'active' : ''; ?>"
               <?= ($activePage === 'profile_directory' || preg_match('~/profiles\.php$~', $sidebarScriptPath)) ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-people" aria-hidden="true"></i>
                <span><?= $currentRole === 'rps' ? 'Researcher Profiles' : 'Extensionist Profiles'; ?></span>
            </a>
        <?php endif; ?>

        </div>



        <a href="<?= BASE_URL; ?>/logout.php" class="sidebar-link sidebar-logout">

            <i class="bi bi-box-arrow-left"></i><span>Logout</span>

        </a>



    </nav>



    <div class="sidebar-footer">

        <small>© <?= date('Y'); ?> ASU Research Office</small>

    </div>



</aside>



<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
/* ASU-REMS: Mobile sidebar opens by tap only.
   Capture clicks to avoid duplicate toggling with assets/js/app.js.
   Existing desktop sidebar behavior remains unchanged. */
(function () {
    'use strict';

    function initializeMobileSidebar() {
        const sidebar = document.querySelector('.asu-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        // Use one shared menu button for every page that uses the
        // standard subpage or dashboard header. Add it if missing.
        const header = document.querySelector(
            '.main-page > header.asu-topbar, .main-page > header.asu-dashboard-topbar'
        );
        let toggle = document.getElementById('sidebarToggle');

        if (header && !toggle) {
            const target = header.querySelector('.topbar-left, .asu-dashboard-brandline') || header;
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.id = 'sidebarToggle';
            toggle.className = 'sidebar-toggle rems-shared-mobile-menu-toggle';
            toggle.setAttribute('aria-label', 'Open navigation menu');
            toggle.setAttribute('title', 'Open navigation menu');
            toggle.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';
            target.insertBefore(toggle, target.firstChild);
        }

        if (!sidebar || !overlay || !toggle) return;

        const mobile = window.matchMedia('(max-width: 991.98px)');

        if (!sidebar.id) sidebar.id = 'asuRemsSidebar';
        toggle.setAttribute('aria-controls', sidebar.id);

        function setMobileSidebarOpen(open) {
            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        }

        // Capture phase prevents the existing app.js click listener from
        // immediately toggling the same sidebar back to its previous state.
        toggle.addEventListener('click', function (event) {
            if (!mobile.matches) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            setMobileSidebarOpen(!sidebar.classList.contains('open'));
        }, true);

        overlay.addEventListener('click', function (event) {
            if (!mobile.matches) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            setMobileSidebarOpen(false);
        }, true);

        sidebar.addEventListener('click', function (event) {
            if (mobile.matches && event.target.closest('.sidebar-link')) {
                setMobileSidebarOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mobile.matches && sidebar.classList.contains('open')) {
                setMobileSidebarOpen(false);
            }
        });

        function handleViewportChange() {
            // Only clear temporary mobile open state; desktop sizing is untouched.
            if (!mobile.matches) setMobileSidebarOpen(false);
        }
        if (typeof mobile.addEventListener === 'function') {
            mobile.addEventListener('change', handleViewportChange);
        } else if (typeof mobile.addListener === 'function') {
            mobile.addListener(handleViewportChange);
        }

        toggle.setAttribute('aria-expanded', String(sidebar.classList.contains('open')));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMobileSidebar);
    } else {
        initializeMobileSidebar();
    }
})();
</script>

<!-- Preserve the sidebar scroll position across normal PHP page navigation. -->
<script>
(function () {
    'use strict';

    const sidebarScroll = document.querySelector('.asu-sidebar .sidebar-scroll-area');
    if (!sidebarScroll) return;

    // Separate saved positions per logged-in role and per browser tab.
    const storageKey = 'asu-rems-sidebar-scroll-<?= htmlspecialchars((string)$currentRole, ENT_QUOTES, 'UTF-8'); ?>';
    let savedPosition = null;

    try {
        const stored = sessionStorage.getItem(storageKey);
        if (stored !== null) {
            const parsed = Number(stored);
            if (Number.isFinite(parsed) && parsed >= 0) savedPosition = parsed;
        }
    } catch (error) {
        // Navigation must continue even if browser storage is disabled.
    }

    function restorePosition() {
        if (savedPosition === null) return;
        sidebarScroll.scrollTop = savedPosition;
    }

    function savePosition() {
        try {
            sessionStorage.setItem(storageKey, String(sidebarScroll.scrollTop));
        } catch (error) {
            // Storage may be unavailable in private/restricted browsing.
        }
    }

    // The sidebar is already in the DOM at this point.
    restorePosition();
    sidebarScroll.addEventListener('scroll', savePosition, { passive: true });
    window.addEventListener('pagehide', savePosition);

    // Reapply after page styles/layout and browser back-forward restoration.
    window.addEventListener('load', restorePosition, { once: true });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) restorePosition();
    });
})();
</script>


<!-- ASU-REMS Shared Profile Avatar — used by every authenticated topbar. -->
<?php
/* Keep profile picture handling centralized: page header markup stays untouched. */
$sidebarProfileInitials = strtoupper(
    substr((string)($user['first_name'] ?? 'U'), 0, 1)
    . substr((string)($user['last_name'] ?? ''), 0, 1)
);
$sidebarProfileInitials = $sidebarProfileInitials !== '' ? $sidebarProfileInitials : 'U';
$sidebarProfileImageUrl = '';
$sidebarProfileHelperFile = __DIR__ . '/profile_avatar.php';
if (isset($pdo) && $pdo instanceof PDO && is_file($sidebarProfileHelperFile)) {
    require_once $sidebarProfileHelperFile;
    if (function_exists('remsProfilePictureRelativePath')) {
        $sidebarProfilePicture = remsProfilePictureRelativePath($pdo, (int)($user['user_id'] ?? 0));
        if ($sidebarProfilePicture !== '') {
            $sidebarProfileImageUrl = rtrim(BASE_URL, '/') . '/' . $sidebarProfilePicture;
        }
    }
}
$sidebarProfileUrl = rtrim(BASE_URL, '/') . '/profile.php';
$sidebarReturn = (string)($_SERVER['REQUEST_URI'] ?? '');
if ($sidebarReturn !== '' && strlen($sidebarReturn) <= 1500 && !preg_match('/[\r\n]/', $sidebarReturn)) {
    $sidebarReturnPath = parse_url($sidebarReturn, PHP_URL_PATH);
    if (is_string($sidebarReturnPath)
        && str_starts_with($sidebarReturnPath, rtrim(BASE_URL, '/') . '/')
        && $sidebarReturnPath !== rtrim(BASE_URL, '/') . '/profile.php') {
        $sidebarProfileUrl .= '?return=' . rawurlencode($sidebarReturn);
    }
}
$sidebarJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<style>
/* ASU-REMS: steady avatar rendering, no post-load replacement.
   PHP-rendered <img> avatars are left unchanged.  Older topbars that still
   contain initial letters use the stored image as a CSS background before
   the page's first paint, rather than waiting for DOMContentLoaded. */
.main-page header .rems-profile-avatar-link,
.main-page header .rems-profile-avatar-link > img {
    border-radius: 50% !important;
}
.main-page header .rems-profile-avatar-link {
    cursor: pointer !important;
    overflow: hidden !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    text-decoration: none !important;
}
.main-page header .rems-profile-avatar-link > img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
.main-page header .rems-profile-avatar-link:focus-visible {
    outline: 3px solid #007a33 !important;
    outline-offset: 3px !important;
}
<?php if ($sidebarProfileImageUrl !== ''): ?>
/* Legacy modules: style the original avatar immediately (no DOM rebuild). */
.main-page header.asu-topbar .topbar-user .user-avatar:not(.rems-profile-avatar-link),
.main-page header.asu-topbar .topbar-user .extension-reports-avatar:not(.rems-profile-avatar-link),
.main-page header.asu-dashboard-topbar .dashboard-avatar:not(.rems-profile-avatar-link) {
    background-color: #ffd51f !important;
    background-image: url(<?= json_encode($sidebarProfileImageUrl, $sidebarJsonFlags | JSON_UNESCAPED_SLASHES); ?>) !important;
    background-position: center !important;
    background-size: cover !important;
    background-repeat: no-repeat !important;
    color: transparent !important;
    font-size: 0 !important;
    overflow: hidden !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    padding: 0 !important;
}
.main-page header.asu-topbar .topbar-user .user-avatar:not(.rems-profile-avatar-link) > *,
.main-page header.asu-topbar .topbar-user .extension-reports-avatar:not(.rems-profile-avatar-link) > *,
.main-page header.asu-dashboard-topbar .dashboard-avatar:not(.rems-profile-avatar-link) > * {
    visibility: hidden !important;
}
<?php endif; ?>


/* Shared sidebar logo — applies to dashboard, RPS, ESO and proponent workspaces. */
.asu-sidebar .sidebar-brand .brand-logo {
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    border-radius:50% !important;
    padding:3px !important;
    overflow:hidden !important;
    background:#ffffff !important;
    border:2px solid rgba(255, 213, 31, .75);
}
.asu-sidebar .sidebar-brand .brand-logo img {
    display:block !important;
    width:100% !important;
    height:100% !important;
    object-fit:contain !important;
    border-radius:50% !important;
}

</style>
<script>
/* Click handling only; do not change avatar HTML, image src, or layout. */
(function () {
    'use strict';
    const profileUrl = <?= json_encode($sidebarProfileUrl, $sidebarJsonFlags); ?>;
    const avatarSelector = [
        '.main-page header.asu-topbar .topbar-user .user-avatar',
        '.main-page header.asu-topbar .topbar-user .extension-reports-avatar',
        '.main-page header.asu-dashboard-topbar .dashboard-avatar'
    ].join(',');

    document.addEventListener('click', function (event) {
        const hit = event.target instanceof Element ? event.target.closest(avatarSelector) : null;
        if (!hit) return;
        // Server-rendered links already point to My Profile; leave them native.
        if (hit.closest('a.rems-profile-avatar-link')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(profileUrl);
    }, true);

    // Support keyboard navigation for legacy <div class="user-avatar"> elements.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll(avatarSelector).forEach(function (avatar) {
            if (avatar.closest('a, button')) return;
            avatar.setAttribute('role', 'link');
            avatar.setAttribute('tabindex', '0');
            avatar.setAttribute('aria-label', 'Open My Profile');
            avatar.setAttribute('title', 'View or change profile picture');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const avatar = event.target instanceof Element && event.target.matches(avatarSelector)
            ? event.target : null;
        if (!avatar || avatar.closest('a, button')) return;
        event.preventDefault();
        window.location.assign(profileUrl);
    });
})();
</script>
