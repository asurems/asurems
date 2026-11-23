<?php







require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_avatar.php';







require_once __DIR__ . '/../includes/notifications.php';







require_once __DIR__ . '/../includes/research_revision_deadlines.php';







requireLogin();







$user = currentUser();







if (($user['role'] ?? '') !== 'rps') {







    setFlash('danger', 'Only Research Processing Staff may access the processing queue.');







    redirect(BASE_URL . '/dashboard.php');







}







$pageTitle = 'Research Processing Queue';







$activePage = 'processing';







$dashboardView = 'processing';







// Keep RPS queue counts accurate when a URRC revision deadline passes.







remsArchiveExpiredUrrcRevisions($pdo);







remsArchiveExpiredInhouseRevisions($pdo);







$queueKey = trim($_GET['queue'] ?? 'submissions');







$queueConfig = [







    'submissions' => [







        'title' => 'New Research Submissions',







        'description' => 'Submitted research proposals waiting for initial Research Office processing.',







        'icon' => 'bi-file-earmark-arrow-up',







        'statuses' => ['SUBMITTED', 'AWAITING_PATHWAY_SELECTION', 'FOR_URRC_PATH_SELECTED'],







    ],







    'urrc' => [







        'title' => 'URRC Review',







        'description' => 'Initial and revised manuscripts currently waiting for URRC processing.',







        'icon' => 'bi-journal-check',







        'statuses' => ['FOR_URRC', 'URRC_REVISED_SUBMITTED'],







    ],







    'inhouse' => [







        'title' => 'In-House Review',







        'description' => 'Research manuscripts currently waiting for In-House processing.',







        'icon' => 'bi-clipboard-check',







        'statuses' => ['FOR_INHOUSE', 'INHOUSE_REVISED_SUBMITTED'],







    ],







    'ethics' => [







        'title' => 'UREC / IACUC Review',







        'description' => 'Research currently undergoing UREC/IACUC processing.',







        'icon' => 'bi-shield-check',







        'statuses' => ['FOR_ETHICS_REVIEW', 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'],







    ],







    'ntp' => [







        'title' => 'Ready for Notice to Proceed',







        'description' => 'Approved research records ready for Notice to Proceed processing.',







        'icon' => 'bi-file-earmark-play',







        'statuses' => ['READY_FOR_NTP'],







    ],







    'permit' => [







        'title' => 'Permit Processing',







        'description' => 'Terminal reports currently waiting for publication or presentation permit processing.',







        'icon' => 'bi-file-earmark-check',







        'statuses' => ['TERMINAL_REPORT_SUBMITTED'],







    ],







];







if (!isset($queueConfig[$queueKey])) {







    $queueKey = 'submissions';







}







$currentQueue = $queueConfig[$queueKey];







$statusPlaceholders = implode(',', array_fill(0, count($currentQueue['statuses']), '?'));







// Previous Step 3 submissions already have FOR_URRC even though RPS never







// performed intake. Identify them via the precise faculty-selection history







// marker, and keep them out of the URRC queue until RPS forwards them.







$earlyUrrcAwaitingIntakeSql = "(







    rp.workflow_status = 'FOR_URRC'







    AND EXISTS (







        SELECT 1 FROM research_status_history h1







        WHERE h1.research_id = rp.research_id







          AND h1.remarks = 'Faculty selected the For URRC pathway after proposal submission.'







    )







    AND NOT EXISTS (







        SELECT 1 FROM research_status_history h2







        WHERE h2.research_id = rp.research_id







          AND h2.remarks = 'RPS forwarded selected For URRC research from New Submissions to URRC Review.'







    )







)";







$workflowQueueCondition = "rp.workflow_status IN ($statusPlaceholders)";







if ($queueKey === 'submissions') {







    $workflowQueueCondition = "($workflowQueueCondition OR $earlyUrrcAwaitingIntakeSql)";







} elseif ($queueKey === 'urrc') {







    $workflowQueueCondition = "($workflowQueueCondition AND NOT $earlyUrrcAwaitingIntakeSql)";







}







$stmt = $pdo->prepare("







    SELECT







        rp.research_id,







        rp.manuscript_no,







        rp.research_title,







        rp.research_category,







        rp.research_type,







        rp.status,







        rp.workflow_status,







        rp.workflow_stage,







        rp.submission_track,







        rp.submitted_at,







        rp.workflow_updated_at,







        rp.created_at,







        u.first_name,







        u.middle_name,







        u.last_name,







        c.college_name,







        d.department_name







    FROM research_proposals rp







    INNER JOIN users u







        ON u.user_id = rp.researcher_id







    LEFT JOIN colleges c







        ON c.college_id = rp.college_id







    LEFT JOIN departments d







        ON d.department_id = rp.department_id







    WHERE $workflowQueueCondition







      AND rp.is_archived = 0







    ORDER BY







        COALESCE(rp.workflow_updated_at, rp.submitted_at, rp.created_at) ASC,







        rp.research_id ASC







");







$stmt->execute($currentQueue['statuses']);







$queueRows = $stmt->fetchAll(PDO::FETCH_ASSOC);







$initials = '';







if (!empty($user['first_name'])) {







    $initials .= strtoupper(substr($user['first_name'], 0, 1));







}







if (!empty($user['last_name'])) {







    $initials .= strtoupper(substr($user['last_name'], 0, 1));







}







require __DIR__ . '/../includes/header.php';







require __DIR__ . '/../includes/sidebar.php';







?>







<style>







.rps-queue-page {







    max-width: 1250px;







    margin: 0 auto;







}







.rps-queue-heading {







    display: flex;







    align-items: flex-start;







    justify-content: space-between;







    gap: 20px;







    margin-bottom: 22px;







}







.rps-queue-heading h1 {







    margin: 0 0 6px;







    color: #243029;







    font-size: 25px;







    font-weight: 800;







}







.rps-queue-heading p {







    max-width: 760px;







    margin: 0;







    color: #7b847d;







    font-size: 11px;







    line-height: 1.6;







}







.rps-queue-badge {







    display: inline-flex;







    align-items: center;







    gap: 8px;







    padding: 10px 14px;







    border-radius: 10px;







    background: #edf7ef;







    color: #08752c;







    font-size: 11px;







    font-weight: 700;







    white-space: nowrap;







}







.rps-queue-tabs {







    display: flex;







    flex-wrap: wrap;







    gap: 8px;







    margin-bottom: 18px;







}







.rps-queue-tab {







    display: inline-flex;







    align-items: center;







    gap: 7px;







    padding: 9px 12px;







    border: 1px solid #dfe6e0;







    border-radius: 9px;







    background: #ffffff;







    color: #566158;







    font-size: 10px;







    font-weight: 700;







    text-decoration: none;







}







.rps-queue-tab:hover,







.rps-queue-tab.active {







    border-color: #08752c;







    background: #edf7ef;







    color: #08752c;







}







.rps-queue-table-wrap {







    overflow-x: auto;







}







.rps-queue-table {







    width: 100%;







    margin: 0;







    border-collapse: collapse;







}







.rps-queue-table th {







    padding: 12px 15px;







    border-bottom: 1px solid #e8ede9;







    background: #f8faf8;







    color: #6f7971;







    font-size: 9px;







    font-weight: 700;







    text-transform: uppercase;







    letter-spacing: .04em;







    text-align: left;







}







.rps-queue-table td {







    padding: 14px 15px;







    border-bottom: 1px solid #edf1ed;







    color: #3a443d;







    font-size: 10px;







    vertical-align: middle;







}







.rps-queue-table tr:last-child td {







    border-bottom: 0;







}







.rps-manuscript {







    color: #08752c;







    font-weight: 800;







    white-space: nowrap;







}







.rps-research-title {







    display: block;







    max-width: 390px;







    color: #29332c;







    font-size: 11px;







    font-weight: 700;







    line-height: 1.45;







}







.rps-research-meta {







    display: block;







    margin-top: 3px;







    color: #8a928c;







    font-size: 8px;







}







.rps-workflow-badge {







    display: inline-flex;







    padding: 5px 9px;







    border: 1px solid #dbe5dc;







    border-radius: 50px;







    background: #f7faf7;







    color: #4e5c52;







    font-size: 8px;







    font-weight: 700;







    white-space: nowrap;







}







.rps-empty {







    padding: 55px 20px;







    text-align: center;







}







.rps-empty-icon {







    width: 56px;







    height: 56px;







    display: flex;







    align-items: center;







    justify-content: center;







    margin: 0 auto 13px;







    border-radius: 50%;







    background: #edf7ef;







    color: #08752c;







    font-size: 23px;







}







.rps-empty h3 {







    margin: 0 0 6px;







    color: #334038;







    font-size: 13px;







    font-weight: 700;







}







.rps-empty p {







    margin: 0;







    color: #858e87;







    font-size: 10px;







}







@media (max-width: 760px) {







    .rps-queue-heading {







        flex-direction: column;







    }







}







/* =========================================================







   RPS PROFESSIONAL MINIMAL UI POLISH







   Design-only overrides. No workflow logic changed.







========================================================= */







.page-content {







    background:







        radial-gradient(circle at top right, rgba(8, 117, 44, .035), transparent 34%),







        linear-gradient(180deg, #f8fbf7 0%, #f3f7f2 100%);







}







.rps-queue-page {







    max-width: 1180px;







    padding-top: 2px;







}







.rps-queue-heading {







    align-items: center;







    margin-bottom: 16px;







    padding: 20px 22px;







    border: 1px solid #e3ebe3;







    border-radius: 18px;







    background: #ffffff;







    box-shadow: 0 12px 30px rgba(21, 55, 28, .045);







}







.rps-queue-heading h1 {







    margin-bottom: 5px;







    font-size: 26px;







    line-height: 1.15;







    letter-spacing: -.025em;







}







.rps-queue-heading p {







    max-width: 720px;







    color: #6f7b72;







    font-size: 13px;







    line-height: 1.55;







}







.rps-queue-badge {







    min-height: 42px;







    padding: 10px 15px;







    border: 1px solid #dcebdd;







    border-radius: 999px;







    background: #f1faf3;







    font-size: 12px;







}







.rps-queue-tabs {







    gap: 5px;







    margin-bottom: 16px;







    padding: 6px;







    border: 1px solid #e3ebe3;







    border-radius: 16px;







    background: #ffffff;







    box-shadow: 0 10px 24px rgba(21, 55, 28, .035);







}







.rps-queue-tab {







    padding: 10px 12px;







    border: 0;







    border-radius: 12px;







    background: transparent;







    color: #5f6b63;







    font-size: 12px;







    font-weight: 750;







    transition: background .16s ease, color .16s ease, box-shadow .16s ease;







}







.rps-queue-tab:hover {







    background: #f3f8f4;







    color: #08752c;







}







.rps-queue-tab.active {







    background: #08752c;







    color: #ffffff;







    box-shadow: 0 8px 18px rgba(8, 117, 44, .18);







}







.asu-panel {







    overflow: hidden;







    border: 1px solid #e3ebe3;







    border-radius: 18px;







    background: #ffffff;







    box-shadow: 0 12px 30px rgba(21, 55, 28, .045);







}







.panel-header {







    min-height: 58px;







    padding: 15px 20px;







    border-bottom: 1px solid #edf2ed;







    background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);







}







.panel-title {







    font-size: 15px;







    letter-spacing: -.01em;







}







.panel-title-icon {







    width: 34px;







    height: 34px;







    border-radius: 12px;







}







.rps-queue-table th {







    padding: 13px 16px;







    background: #fbfdfb;







    color: #68736b;







    font-size: 10px;







    letter-spacing: .055em;







}







.rps-queue-table td {







    padding: 14px 16px;







    color: #354039;







    font-size: 12px;







}







.rps-queue-table tbody tr {







    transition: background .16s ease;







}







.rps-queue-table tbody tr:hover td {







    background: #fbfdfb;







}







.rps-manuscript {







    display: inline-flex;







    align-items: center;







    padding: 5px 9px;







    border-radius: 999px;







    background: #f1faf3;







    color: #08752c;







    font-size: 12px;







}







.rps-research-title {







    max-width: 410px;







    font-size: 13px;







    line-height: 1.35;







}







.rps-research-meta {







    margin-top: 4px;







    font-size: 10px;







}







.rps-workflow-badge {







    padding: 6px 10px;







    border-color: #d6e5d8;







    background: #f8fbf8;







    color: #435047;







    font-size: 10px;







}







.rps-queue-table .btn-asu.btn-sm {







    padding: 8px 12px;







    border-radius: 11px;







    font-size: 12px;







    font-weight: 700;







    white-space: nowrap;







}







.rps-empty {







    padding: 54px 20px;







}







.rps-empty-icon {







    width: 54px;







    height: 54px;







    border-radius: 18px;







}







.rps-empty h3 {







    font-size: 15px;







}







.rps-empty p {







    font-size: 12px;







}







@media (max-width: 760px) {







    .rps-queue-heading {







        align-items: flex-start;







    }







    .rps-queue-tabs {







        overflow-x: auto;







        flex-wrap: nowrap;







    }







    .rps-queue-tab {







        white-space: nowrap;







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







/* =========================================================







   FINAL RPS PROCESSING QUEUE DESIGN







   Main module • Compact banner • Full-width landscape







========================================================= */







:root {







    --asu-green: #007a33;







    --asu-green-dark: #005c26;







    --asu-green-deep: #003f1a;







    --asu-yellow: #ffd51f;







    --asu-orange: #f28c28;







    --asu-ink: #101810;







    --asu-muted: #657068;







}







/* GLOBAL */







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







.rps-processing-queue-page {







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







.rps-queue-page {







    width: 100% !important;







    max-width: 1320px !important;







    margin: 0 auto !important;







    padding-top: 0 !important;







}







/* TOPBAR */







.asu-topbar {







    min-height: 76px !important;







    height: 76px !important;







    padding: 0 30px !important;







    background: rgba(255,255,255,.97) !important;







    border-bottom: 1px solid rgba(0,122,51,.12) !important;







    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;







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







}







.topbar-user-text strong {







    color: var(--asu-ink) !important;







    font-size: 15px !important;







    line-height: 1.1 !important;







}







.topbar-user-text span {







    margin-top: 4px !important;







    color: var(--asu-muted) !important;







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







    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;







}







/* COMPACT MAIN-MODULE BANNER */







.rps-queue-heading {







    position: relative !important;







    overflow: hidden !important;







    min-height: 96px !important;







    margin: 0 0 18px !important;







    padding: 20px 26px !important;







    display: flex !important;







    align-items: center !important;







    justify-content: space-between !important;







    gap: 18px !important;







    border: 0 !important;







    border-radius: 18px !important;







    background:







        linear-gradient(







            112deg,







            #005c26 0%,







            #007a33 64%,







            #2c9a39 100%







        ) !important;







    box-shadow: 0 18px 42px rgba(0,90,40,.16) !important;







}







.rps-queue-heading::after {







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







            rgba(255,213,31,.17),







            rgba(255,255,255,.04)







        ) !important;







    pointer-events: none !important;







}







.rps-queue-heading > * {







    position: relative !important;







    z-index: 2 !important;







}







.rps-queue-heading h1 {







    margin: 0 !important;







    color: #fff !important;







    font-size: 30px !important;







    line-height: 1 !important;







    font-weight: 900 !important;







    letter-spacing: -.03em !important;







}







.rps-queue-heading p {







    display: none !important;







}







/* Record count should be subtle, not a highlighted yellow chip */







.rps-queue-badge {







    min-height: 36px !important;







    padding: 0 12px !important;







    display: inline-flex !important;







    align-items: center !important;







    justify-content: center !important;







    gap: 6px !important;







    border: 1px solid rgba(255,255,255,.20) !important;







    border-radius: 999px !important;







    background: rgba(255,255,255,.12) !important;







    color: #fff !important;







    box-shadow: none !important;







    font-size: 9.5px !important;







    font-weight: 800 !important;







    white-space: nowrap !important;







}







.rps-queue-badge i {







    color: #fff !important;







}







/* QUEUE TABS */







.rps-queue-tabs {







    display: flex !important;







    flex-wrap: wrap !important;







    gap: 7px !important;







    margin: 0 0 18px !important;







    padding: 7px !important;







    border: 1px solid rgba(0,122,51,.13) !important;







    border-radius: 16px !important;







    background: rgba(255,255,255,.96) !important;







    box-shadow: 0 12px 28px rgba(16,24,16,.045) !important;







}







.rps-queue-tab {







    min-height: 38px !important;







    padding: 0 11px !important;







    display: inline-flex !important;







    align-items: center !important;







    gap: 6px !important;







    border: 0 !important;







    border-radius: 10px !important;







    background: transparent !important;







    color: #5e6961 !important;







    font-size: 9.5px !important;







    font-weight: 800 !important;







    text-decoration: none !important;







    box-shadow: none !important;







}







.rps-queue-tab:hover {







    background: #f3f8f4 !important;







    color: var(--asu-green) !important;







}







.rps-queue-tab.active {







    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;







    color: #fff !important;







    box-shadow: 0 8px 18px rgba(0,122,51,.14) !important;







}







.rps-queue-tab.active i {







    color: #fff !important;







}







/* PANEL */







.asu-panel {







    overflow: hidden !important;







    border: 1px solid rgba(0,122,51,.13) !important;







    border-radius: 18px !important;







    background: rgba(255,255,255,.96) !important;







    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;







}







.panel-header {







    min-height: 66px !important;







    padding: 14px 18px !important;







    display: flex !important;







    align-items: center !important;







    justify-content: space-between !important;







    gap: 15px !important;







    border-bottom: 1px solid rgba(0,122,51,.09) !important;







    background: rgba(255,255,255,.86) !important;







}







.panel-title {







    display: flex !important;







    align-items: center !important;







    gap: 10px !important;







    margin: 0 !important;







    color: var(--asu-ink) !important;







    font-family: "Century Gothic", sans-serif !important;







    font-size: 15px !important;







    font-weight: 700 !important;







}







.panel-title-icon {







    width: 36px !important;







    height: 36px !important;







    min-width: 36px !important;







    display: inline-flex !important;







    align-items: center !important;







    justify-content: center !important;







    border-radius: 10px !important;







    background: #edf7ef !important;







    color: var(--asu-green) !important;







    font-size: 16px !important;







}







.panel-link {







    min-height: 34px !important;







    padding: 0 10px !important;







    display: inline-flex !important;







    align-items: center !important;







    gap: 5px !important;







    border: 1px solid rgba(0,122,51,.14) !important;







    border-radius: 9px !important;







    background: #fff !important;







    color: var(--asu-green) !important;







    font-size: 9px !important;







    font-weight: 800 !important;







    text-decoration: none !important;







}







.panel-link:hover {







    background: #f2f8f3 !important;







    color: var(--asu-green-dark) !important;







}







/* LANDSCAPE TABLE */







.rps-queue-table-wrap {







    width: 100% !important;







    overflow-x: auto !important;







}







.rps-queue-table {







    width: 100% !important;







    min-width: 1160px !important;







    margin: 0 !important;







    border-collapse: collapse !important;







}







.rps-queue-table th {







    padding: 13px 15px !important;







    border-bottom: 1px solid rgba(0,122,51,.10) !important;







    background: #f7faf7 !important;







    color: #687269 !important;







    font-family: "Century Gothic", sans-serif !important;







    font-size: 9px !important;







    font-weight: 700 !important;







    text-align: left !important;







    text-transform: uppercase !important;







    letter-spacing: .03em !important;







    white-space: nowrap !important;







}







.rps-queue-table td {







    padding: 14px 15px !important;







    border-bottom: 1px solid rgba(0,122,51,.07) !important;







    color: #343c36 !important;







    font-size: 10px !important;







    vertical-align: middle !important;







}







.rps-queue-table tbody tr:hover td {







    background: rgba(0,122,51,.018) !important;







    box-shadow: none !important;







}







.rps-manuscript {







    display: inline-flex !important;







    align-items: center !important;







    min-height: 27px !important;







    padding: 3px 8px !important;







    border: 1px solid rgba(0,122,51,.10) !important;







    border-radius: 8px !important;







    background: #f4f8f5 !important;







    color: var(--asu-green-dark) !important;







    font-size: 9px !important;







    font-weight: 800 !important;







    white-space: nowrap !important;







}







.rps-research-title {







    max-width: 430px !important;







    color: #222a24 !important;







    font-size: 11px !important;







    line-height: 1.4 !important;







    font-weight: 800 !important;







}







.rps-research-meta {







    margin-top: 4px !important;







    color: #818b84 !important;







    font-size: 8.5px !important;







}







.rps-workflow-badge {







    min-height: 27px !important;







    padding: 4px 8px !important;







    display: inline-flex !important;







    align-items: center !important;







    border: 1px solid rgba(0,122,51,.11) !important;







    border-radius: 999px !important;







    background: #f4f8f5 !important;







    color: #566158 !important;







    font-size: 8.5px !important;







    font-weight: 800 !important;







    white-space: nowrap !important;







}







.rps-queue-table .btn-asu.btn-sm {







    min-height: 34px !important;







    padding: 0 10px !important;







    display: inline-flex !important;







    align-items: center !important;







    justify-content: center !important;







    border-radius: 9px !important;







    font-size: 9px !important;







    font-weight: 800 !important;







    white-space: nowrap !important;







}







/* EMPTY */







.rps-empty {







    padding: 48px 22px !important;







    text-align: center !important;







}







.rps-empty-icon {







    width: 58px !important;







    height: 58px !important;







    margin: 0 auto 13px !important;







    border-radius: 16px !important;







    background: #edf7ef !important;







    color: var(--asu-green) !important;







    font-size: 24px !important;







}







.rps-empty h3 {







    margin: 0 0 5px !important;







    color: #47534a !important;







    font-size: 13px !important;







    font-weight: 800 !important;







}







.rps-empty p {







    margin: 0 !important;







    color: #7a857d !important;







    font-size: 9.5px !important;







}







/* BUTTON FAMILY */







.btn-asu,







.btn.btn-asu {







    border-color: var(--asu-green) !important;







    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;







    color: #fff !important;







    box-shadow: 0 8px 18px rgba(0,122,51,.13) !important;







}







.btn-asu:hover,







.btn.btn-asu:hover {







    border-color: var(--asu-green-dark) !important;







    background: var(--asu-green-dark) !important;







    color: #fff !important;







}







/* ONE COPYRIGHT ONLY */







.content-footer {







    display: none !important;







}







/* RESPONSIVE */







@media (max-width: 900px) {







    .rps-queue-tabs {







        flex-wrap: nowrap !important;







        overflow-x: auto !important;







        scrollbar-width: thin !important;







    }







    .rps-queue-tab {







        flex: 0 0 auto !important;







        white-space: nowrap !important;







    }







}







@media (max-width: 760px) {







    .asu-topbar {







        padding: 0 16px !important;







    }







    .system-brand-copy strong {







        font-size: 13px !important;







    }







    .system-brand-copy span {







        font-size: 10px !important;







    }







    .rps-processing-queue-page {







        padding: 18px 14px 24px !important;







    }







    .rps-queue-heading {







        min-height: 82px !important;







        padding: 18px !important;







        border-radius: 16px !important;







        flex-direction: column !important;







        align-items: flex-start !important;







    }







    .rps-queue-heading h1 {







        font-size: 27px !important;







    }







    .panel-header {







        align-items: flex-start !important;







        flex-direction: column !important;







    }







}







/* =========================================================







   RPS QUEUE — STEADY SCROLL ONLY







   Normal scrolling stays enabled.







   Prevents the whole page from pulling/bouncing past







   the top or bottom edge.







========================================================= */







html,







body {







    overscroll-behavior-x: none !important;







    overscroll-behavior-y: none !important;







}







.rps-processing-queue-page {







    overscroll-behavior-y: contain !important;







}











/* ======================================================================

   ASU-REMS — RPS PROCESSING QUEUE DARK MODE

   Scoped to this page and only active for data-rems-theme="dark".

   Visual-only update: all PHP queries, statuses, actions and roles retained.

   ====================================================================== */

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page),

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page) .main-page,

html[data-rems-theme="dark"] .page-content.rps-processing-queue-page {

    background: #0b1d15 !important;

    color: #eaf4eb !important;

}



/* Topbar — inherited sidebar and floating theme switch are not modified. */

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page) .asu-topbar {

    background: #10281c !important;

    border-bottom-color: #355843 !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page) .asu-topbar :is(

    .system-brand-copy strong, .system-name, .topbar-user-text strong

) {

    color: #f1f8ef !important;

}

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page) .asu-topbar :is(

    .system-brand-copy span, .topbar-user-text span

) {

    color: #bdd0c1 !important;

}

html[data-rems-theme="dark"] body:has(.rps-processing-queue-page) .asu-topbar .sidebar-toggle {

    color: #eaf4eb !important;

}



/* Keep the existing green queue heading and its white title. */

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-heading {

    border-color: rgba(255, 255, 255, .13) !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-heading :is(h1,p),

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-badge,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-badge i {

    color: #fff !important;

}



/* Navigation tabs */

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-tabs {

    background: #132b20 !important;

    border-color: #355843 !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-tab {

    color: #c9decf !important;

    background: transparent !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-tab:hover {

    background: #244634 !important;

    color: #fff !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-tab.active {

    background: #087d3b !important;

    border-color: #087d3b !important;

    color: #fff !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-tab.active i {

    color: #fff !important;

}



/* Main list panel */

html[data-rems-theme="dark"] .rps-processing-queue-page .asu-panel,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table-wrap {

    background: #132b20 !important;

    border-color: #355843 !important;

    box-shadow: none !important;

    color: #eaf4eb !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .panel-header {

    background: #1b392a !important;

    border-bottom-color: #3c5b46 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .panel-title {

    color: #f0f7ef !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .panel-title-icon,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-empty-icon {

    background: #244b36 !important;

    color: #bfe9c8 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .panel-link {

    background: #294b39 !important;

    border-color: #52705d !important;

    color: #dceddf !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .panel-link:hover {

    background: #395e48 !important;

    border-color: #72987a !important;

    color: #fff !important;

}



/* Records table: readable headings, titles, researcher names, and dates. */

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table {

    background: #132b20 !important;

    color: #eaf4eb !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table thead th {

    background: #214431 !important;

    color: #dae9de !important;

    border-bottom-color: #46664f !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table tbody td {

    background: #132b20 !important;

    color: #dce9dd !important;

    border-bottom-color: #355643 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table tbody tr:hover td {

    background: #203c2c !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-research-title {

    color: #f2f8f0 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-research-meta {

    color: #afc3b5 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-manuscript {

    background: #294d36 !important;

    color: #c5ebd0 !important;

    border-color: #476e50 !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-workflow-badge {

    background: #324b39 !important;

    color: #e2ead7 !important;

    border-color: #53745d !important;

}



/* Empty queues and actions */

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-empty,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-empty p {

    color: #bbccbf !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-empty h3 {

    color: #f0f7ef !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table .btn-asu,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table .btn.btn-asu {

    background: #087c3b !important;

    border-color: #087c3b !important;

    color: #fff !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table .btn-asu:hover,

html[data-rems-theme="dark"] .rps-processing-queue-page .rps-queue-table .btn.btn-asu:hover {

    background: #0a9850 !important;

    border-color: #0a9850 !important;

    color: #fff !important;

}



/* Stable queue navigation: eliminate hover movement and page animations. */

html[data-rems-theme="dark"] .rps-processing-queue-page *,

html[data-rems-theme="dark"] .rps-processing-queue-page *::before,

html[data-rems-theme="dark"] .rps-processing-queue-page *::after {

    animation: none !important;

    transition: none !important;

    scroll-behavior: auto !important;

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
<div class="main-page">







<header class="asu-topbar">







    <div class="topbar-left">







        <button id="sidebarToggle" type="button" class="sidebar-toggle" aria-label="Open navigation">







            <i class="bi bi-list"></i>







        </button>







        <div class="system-brand-copy">







    <strong>Research &amp; Extension Management System</strong>







    <span>Aklan State University</span>







</div>







    </div>







    <div class="topbar-user">







        <div class="topbar-user-text">







            <strong><?= e(fullName($user)); ?></strong>







            <span>Research Processing Staff (RPS)</span>







        </div>







        <?= remsProfileAvatarHtml($pdo, $user, $initials ?: 'U', 'dashboard-avatar'); ?>







    </div>







</header>







<main class="page-content rps-processing-queue-page">







<div class="rps-queue-page">







    <div class="rps-queue-heading">







        <div>







            <h1><?= e($currentQueue['title']); ?></h1>







            <p><?= e($currentQueue['description']); ?></p>







        </div>







        <div class="rps-queue-badge">







            <i class="bi <?= e($currentQueue['icon']); ?>"></i>







            <?= number_format(count($queueRows)); ?> Record<?= count($queueRows) === 1 ? '' : 's'; ?>







        </div>







    </div>







    <div class="rps-queue-tabs">







        <?php foreach ($queueConfig as $key => $config): ?>







            <a







                href="<?= BASE_URL; ?>/research/rps_queue.php?queue=<?= e($key); ?>"







                class="rps-queue-tab <?= $queueKey === $key ? 'active' : ''; ?>"







            >







                <i class="bi <?= e($config['icon']); ?>"></i>







                <?= e($config['title']); ?>







            </a>







        <?php endforeach; ?>







    </div>







    <section class="asu-panel">







        <div class="panel-header">







            <h2 class="panel-title">







                <span class="panel-title-icon">







                    <i class="bi <?= e($currentQueue['icon']); ?>"></i>







                </span>







                <?= e($currentQueue['title']); ?>







            </h2>







            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">







            <a href="<?= BASE_URL; ?>/dashboard.php?view=processing" class="panel-link">







                Processing Overview







                <i class="bi bi-arrow-right"></i>







            </a>







                <a href="<?= BASE_URL; ?>/research/archive.php" class="panel-link">







                    <i class="bi bi-archive"></i> Archives







                </a>







            </div>







        </div>







        <?php if ($queueRows): ?>







            <div class="rps-queue-table-wrap">







                <table class="rps-queue-table">







                    <thead>







                        <tr>







                            <th>Manuscript</th>







                            <th>Research</th>







                            <th>Researcher</th>







                            <th>College / Department</th>







                            <th>Workflow</th>







                            <th>Submitted</th>







                            <th>Action</th>







                        </tr>







                    </thead>







                    <tbody>







                    <?php foreach ($queueRows as $row): ?>







                        <?php







                        $researcherName = trim(







                            ($row['first_name'] ?? '') . ' ' .







                            (!empty($row['middle_name']) ? $row['middle_name'] . ' ' : '') .







                            ($row['last_name'] ?? '')







                        );







                        $office = trim(







                            ($row['college_name'] ?? '') .







                            (!empty($row['department_name']) ? ' / ' . $row['department_name'] : '')







                        );







                        ?>







                        <tr>







                            <td>







                                <span class="rps-manuscript">







                                    <?= e($row['manuscript_no'] ?: '#' . $row['research_id']); ?>







                                </span>







                            </td>







                            <td>







                                <span class="rps-research-title">







                                    <?= e($row['research_title']); ?>







                                </span>







                                <span class="rps-research-meta">







                                    <?= e($row['research_category'] ?: 'No category'); ?>







                                    <?php if (!empty($row['research_type'])): ?>







                                        · <?= e($row['research_type']); ?>







                                    <?php endif; ?>







                                </span>







                            </td>







                            <td><?= e($researcherName ?: 'Unknown'); ?></td>







                            <td><?= e($office ?: 'Not assigned'); ?></td>







                            <td>







                                <span class="rps-workflow-badge">







                                    <?= e(($queueKey === 'submissions' && ($row['workflow_status'] ?? '') === 'FOR_URRC')







    ? 'Pending RPS Intake'







    : match ((string)$row['workflow_status']) {







    'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS' => 'Pending UREC/IACUC Review',







    'FOR_URRC_PATH_SELECTED' => 'Pending RPS Intake',







    'AWAITING_PATHWAY_SELECTION' => 'Awaiting Faculty Pathway',







    default => str_replace('_', ' ', (string)$row['workflow_status'])







}); ?>







                                </span>







                            </td>







                            <td>







                                <?= !empty($row['submitted_at'])







                                    ? e(date('M d, Y h:i A', strtotime($row['submitted_at'])))







                                    : '—'; ?>







                            </td>







                            <td>







                                <a







                                    href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$row['research_id']; ?>"







                                    class="btn btn-asu btn-sm"







                                >







                                    <i class="bi bi-eye me-1"></i>







                                    Open Research







                                </a>







                            </td>







                        </tr>







                    <?php endforeach; ?>







                    </tbody>







                </table>







            </div>







        <?php else: ?>







            <div class="rps-empty">







                <div class="rps-empty-icon">







                    <i class="bi bi-inbox"></i>







                </div>







                <h3>No records in this queue</h3>







                <p>Research records will appear here automatically when they reach this workflow stage.</p>







            </div>







        <?php endif; ?>







    </section>







</div>







</main>







</div>







<?php require __DIR__ . '/../includes/footer.php'; ?>
