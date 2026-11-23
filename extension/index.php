<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/extension_roles.php';
require_once __DIR__ . '/includes/extension_statuses.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

// Preserve the existing Administrator records route.
// Extension proponents and ESO use this module page.
if (
    !extensionCanAccess()
    && ($_SESSION['role'] ?? '') !== 'admin'
) {
    redirect(BASE_URL . '/dashboard.php');
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

if (($user['role'] ?? '') === 'admin') {
    redirect(BASE_URL . '/admin/records/extension.php');
}

$pageTitle = 'Extension Management';
$activePage = 'extension';


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search'] ?? ''
    );

$status =
    trim(
        $_GET['status'] ?? ''
    );

$year =
    trim(
        $_GET['year'] ?? ''
    );


// Centralized Extension-only status list (legacy + proposed workflow).
$validStatuses = extensionAllStatuses();
$isProponent = extensionIsProponent();
$isOffice = extensionIsOffice();


/*
|--------------------------------------------------------------------------
| ACCESS CONDITION
|--------------------------------------------------------------------------
|
| Extension Coordinator:
| - Can see only programs they created.
|
| Administrator:
| - Can see institutional programs after submission.
| - Drafts remain private to the coordinator.
|
*/

if ($isProponent) {

    $accessSql =
        "ep.coordinator_id = ?";

    $accessParams = [
        (int)$user['user_id']
    ];

} else {

    $accessSql =
        "ep.status <> 'Draft'";

    $accessParams = [];
}


/*
|--------------------------------------------------------------------------
| BUILD PROGRAM QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        ep.extension_id,
        ep.coordinator_id,

        ep.program_title,
        ep.program_category,
        ep.program_type,

        ep.target_community,
        ep.municipality,
        ep.province,

        ep.target_participants,

        ep.start_date,
        ep.end_date,

        ep.status,
        ep.progress_percentage,

        ep.submitted_at,
        ep.created_at,
        ep.updated_at,

        u.first_name,
        u.middle_name,
        u.last_name,

        c.college_name,
        d.department_name

    FROM extension_programs ep

    INNER JOIN users u
        ON u.user_id = ep.coordinator_id

    LEFT JOIN colleges c
        ON c.college_id = ep.college_id

    LEFT JOIN departments d
        ON d.department_id = ep.department_id

    WHERE {$accessSql}
";

$params =
    $accessParams;


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            ep.program_title LIKE ?
            OR ep.program_category LIKE ?
            OR ep.program_type LIKE ?
            OR ep.target_community LIKE ?
            OR ep.municipality LIKE ?
            OR ep.province LIKE ?
            OR u.first_name LIKE ?
            OR u.middle_name LIKE ?
            OR u.last_name LIKE ?
        )
    ";

    $searchTerm =
        '%' . $search . '%';

    for ($i = 0; $i < 9; $i++) {
        $params[] = $searchTerm;
    }
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $status !== ''
    &&
    in_array(
        $status,
        $validStatuses,
        true
    )
) {

    $sql .= "
        AND ep.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| YEAR FILTER
|--------------------------------------------------------------------------
*/

if (
    $year !== ''
    &&
    preg_match(
        '/^\d{4}$/',
        $year
    )
) {

    $sql .= "
        AND (
            YEAR(ep.start_date) = ?
            OR YEAR(ep.created_at) = ?
        )
    ";

    $params[] =
        (int)$year;

    $params[] =
        (int)$year;
}


/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        ep.updated_at DESC,
        ep.created_at DESC
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$programs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );



/*
|--------------------------------------------------------------------------
| ESO PROCESSING OVERVIEW - DATABASE-DRIVEN QUEUE
|--------------------------------------------------------------------------
| All counts and rows come from extension_programs on every page load.
| Faculty/Extensionist interface stays as it was.
| Research code and tables are not changed.
*/
$esoQueues = [];
$esoQueueKey = 'submissions';
$esoQueueSearch = '';
$esoQueueRows = [];
$esoQueueCounts = [];

if ($isOffice) {
    $esoQueues = [
        'submissions' => [
            'title' => 'New Extension Submissions',
            'icon' => 'bi-inbox',
            'statuses' => ['Submitted'],
        ],
        'uerc' => [
            'title' => 'UERC Technical Review',
            'icon' => 'bi-clipboard-check',
            'statuses' => ['UERC Technical Review'],
        ],
        'pre_inhouse_endorsement' => [
            'title' => 'Chairperson / Dean / CD Endorsement',
            'icon' => 'bi-file-earmark-check',
            'statuses' => ['For Pre-In-House Endorsement'],
        ],
        'inhouse' => [
            'title' => 'In-House Review',
            'icon' => 'bi-people',
            'statuses' => ['University In-House Review'],
        ],
        'endorsement' => [
            'title' => 'Review and Endorsement',
            'icon' => 'bi-file-earmark-check',
            'statuses' => ['For Endorsement'],
        ],
        'ladco' => [
            'title' => 'LADCO/UADCO/BOR',
            'icon' => 'bi-award',
            // The second value is kept solely for older proposals in progress.
            'statuses' => ['For LADCO/ADCO Approval', 'For BOR Notation'],
        ],
        'certification' => [
            'title' => 'Certification',
            'icon' => 'bi-patch-check',
            'statuses' => ['For Certification'],
        ],
        'approved' => [
            'title' => 'Approved Proposals',
            'icon' => 'bi-check-circle',
            'statuses' => ['Approved'],
        ],
    ];

    $requestedQueue = (string) ($_GET['queue'] ?? 'submissions');
    if ($requestedQueue === 'bor') {
        $requestedQueue = 'ladco'; // Redirect legacy bookmarks to the combined queue.
    }
    $esoQueueKey = isset($esoQueues[$requestedQueue])
        ? $requestedQueue
        : 'submissions';
    $esoQueueSearch = trim((string) ($_GET['q'] ?? ''));
    $esoQueueSearch = substr($esoQueueSearch, 0, 150);

    foreach ($esoQueues as $key => $queue) {
        $placeholders = implode(', ', array_fill(0, count($queue['statuses']), '?'));
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM extension_programs ep WHERE ep.status IN ($placeholders)"
        );
        $countStmt->execute($queue['statuses']);
        $esoQueueCounts[$key] = (int) $countStmt->fetchColumn();
    }

    $selectedStatuses = $esoQueues[$esoQueueKey]['statuses'];
    $placeholders = implode(', ', array_fill(0, count($selectedStatuses), '?'));
    $queueSql = "
        SELECT ep.extension_id, ep.program_title, ep.program_type,
               ep.target_community, ep.status, ep.submitted_at,
               ep.updated_at, u.first_name, u.middle_name, u.last_name
        FROM extension_programs ep
        INNER JOIN users u ON u.user_id = ep.coordinator_id
        WHERE ep.status IN ($placeholders)
    ";
    $queueParams = $selectedStatuses;

    if ($esoQueueSearch !== '') {
        $queueSql .= "
            AND (
                ep.program_title LIKE ?
                OR ep.target_community LIKE ?
                OR CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) LIKE ?
            )
        ";
        $term = '%' . $esoQueueSearch . '%';
        array_push($queueParams, $term, $term, $term);
    }

    $queueSql .= " ORDER BY ep.updated_at DESC, ep.extension_id DESC";
    $queueStmt = $pdo->prepare($queueSql);
    $queueStmt->execute($queueParams);
    $esoQueueRows = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Only returns destinations for existing authorized workflow pages. */
function extensionEsoQueueAction(string $status): array
{
    switch ($status) {
        case 'Submitted':
            return ['review.php', 'Start UERC Review'];
        case 'UERC Technical Review':
            return ['review.php', 'Record UERC Comments'];
        case 'For Pre-In-House Endorsement':
            return ['review.php', 'Record Endorsement'];
        case 'University In-House Review':
            return ['review.php', 'Record In-House Review'];
        case 'For Endorsement':
            return ['endorsement.php', 'Record Endorsement'];
        case 'For LADCO/ADCO Approval':
        case 'For BOR Notation':
            return ['approval.php', 'Record LADCO/UADCO/BOR'];
        case 'For Certification':
            return ['certification.php', 'Record Certification'];
        default:
            return ['view.php', 'View Record'];
    }
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flash =
    getFlash();


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';

if (
    !empty(
        $user['first_name']
    )
) {

    $initials .=
        strtoupper(
            substr(
                $user['first_name'],
                0,
                1
            )
        );
}


if (
    !empty(
        $user['last_name']
    )
) {

    $initials .=
        strtoupper(
            substr(
                $user['last_name'],
                0,
                1
            )
        );
}


/*
|--------------------------------------------------------------------------
| LOAD LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../includes/header.php';

require __DIR__ . '/../includes/sidebar.php';

?>


<style id="rems-extension-darkmode-overrides">
/* =============================================================
   ASU-REMS — EXTENSION DARK MODE ONLY
   The existing PHP workflows, JS, and light theme are unchanged.
   Scoped to this file via .rems-extension-dark-scope.
   ============================================================= */
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope  .page-content,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-content,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-management-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-details-page {
    background:#0b1d15!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .asu-topbar {
    background:#11271b!important;
     border-bottom:1px solid #365541!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .topbar-user-text strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-brand-copy strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .rems-topbar-brandline strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-name {
    color:#eef7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .topbar-user-text span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-brand-copy span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .rems-topbar-brandline span {
    color:#bdd3c2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.page-content,.extension-review-page,.extension-create-page,.call-content,.extension-management-page,.extension-details-page) :is(h1,h2,h3,h4,h5,h6) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.call-hero,.extension-review-hero,.extension-form-heading,.page-heading,.rems-view-heading) :is(h1,h2,h3,h4) {
    color:#ffffff!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card,.asu-card,.asu-panel,.panel,.extension-review-card,.call-card,.extension-form-card,.extension-form-section,.filter-card,.eso-queue-panel,.extension-panel,.extension-status-overview,.extension-document-summary,.extension-document-summary-tile,.extension-overview-row,.extension-official-documents,.workflow-card,.content-block,.document-item,.extension-document-viewer,.extension-preview-message-box,.monitoring-card,.monitoring-filter-card,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.eso-monitor-card,.eso-monitor-detail,.service-request-form-card,.eso-service-card,.eso-service-details,.my-sr-card,.my-sr-details,.my-sr-filter-card,.extension-report-card,.extension-report-details,.call-meta,.extension-review-meta-item,.review-link-summary,.review-form-row,.extension-summary-meta,.my-sr-detail,.monitoring-note,.service-request-note,.extension-upload-intro,.upload-box,.extension-upload-control,.extension-call-link,.extension-form-section-title,.review-unavailable,.monitoring-list-filters,.monitoring-list-toolbar,.my-sr-toolbar,.eso-service-toolbar,.eso-service-filters,.my-sr-filters) {
    background:#162e22!important;
     background-image:none!important;
     border-color:#395b46!important;
     color:#eaf3eb!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card-header,.panel-header,.extension-review-card-header,.extension-form-header,.call-card-head,.extension-panel-header,.eso-queue-panel-head,.monitoring-request-heading,.service-request-heading,.my-sr-records-head,.eso-service-toolbar,.monitoring-list-toolbar) {
    background:#1b3a2b!important;
     background-image:none!important;
     border-bottom-color:#41604b!important;
     color:#edf7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card-body,.call-card-body,.extension-review-card-body,.extension-panel-body,.extension-form-body,.monitoring-card .card-body,.service-request-form-card .card-body) {
    background:transparent!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-title-cell strong,.extension-record-title,.extension-program-title,.extension-review-card-title,.extension-review-proposal-heading h2,.extension-review-subtitle,.call-card-head h2,.call-record-title,.extension-form-header h2,.extension-form-section-title,.extension-panel-title,.extension-status-overview-content strong,.extension-document-summary-name,.extension-document-summary-info strong,.workflow-text h3,.workflow-text h4,.info-item strong,.my-sr-title,.my-sr-head-title,.my-sr-detail strong,.monitoring-request-heading h2,.service-request-heading h2,.eso-queue-panel-title,.eso-queue-panel-head h2,.panel-title,.call-meta strong,.extension-review-meta-item strong,.monitor-highlight-value,.monitoring-card h2,.extension-report-card h3) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-title-cell small,.extension-coordinator small,.extension-record-muted,.extension-record-meta,.extension-review-helper,.extension-review-meta-item span,.call-description,.call-meta small,.call-card-body p,.extension-form-body .small,.extension-upload-note,.extension-document-summary-meta,.extension-summary-meta span,.info-item label,.document-info small,.workflow-text p,.monitoring-help,.service-request-help,.service-request-note,.my-sr-description,.my-sr-code,.monitoring-note,.my-sr-detail small,.monitor-report-detail .small,.eso-monitor-detail .small,.eso-service-details .small) {
    color:#bdd2c3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.text-muted,.text-secondary,.form-text,.small.text-muted,p.text-muted,small.text-muted) {
    color:#bed1c3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-label,label:not(.btn),.extension-filter-label,.call-form .form-label) {
    color:#dcebdc!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea.form-control,.input-group-text,.monitoring-search-field,.my-sr-search-field,.eso-service-search-field,.eso-queue-search-input) {
    background-color:#1c392b!important;
     color:#f3f9f2!important;
     border-color:#4c7158!important;
     box-shadow:none!important;
     color-scheme:dark;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,input,textarea)::placeholder {
    color:#bbd0be!important;
     opacity:1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope select.form-select {
    appearance:none!important;
     -webkit-appearance:none!important;
     -moz-appearance:none!important;
     padding-right:38px!important;
     background-color:#1c392b!important;
     background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3.25 6.25 4.75 4.5 4.75-4.5' fill='none' stroke='%23D9EEDF' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;
     background-repeat:no-repeat!important;
     background-position:right 14px center!important;
     background-size:14px 14px!important;
     color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope select.form-select option {
    background:#1c392b!important;
     color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope input:is([type="date"],[type="datetime-local"],[type="time"])::-webkit-calendar-picker-indicator {
    filter:invert(1) brightness(1.3);
     opacity:.85;
     cursor:pointer;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea):focus {
    border-color:#83c99d!important;
     box-shadow:0 0 0 2px rgba(91,170,114,.18)!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.table,table,.extension-table,.extension-programs-table,.call-table,.monitoring-table,.eso-service-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table,.my-sr-table) {
    --bs-table-bg:#162e22!important;
     --bs-table-color:#eaf3eb!important;
     --bs-table-border-color:#3a5f48!important;
     --bs-table-striped-bg:#1d3e2d!important;
     --bs-table-hover-bg:#234b36!important;
     color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#3a5f48!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table :is(th,td) {
    color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table thead :is(th,td) {
    background-color:#213f2d!important;
     color:#d0e5d4!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table tbody tr:hover :is(td,th) {
    background-color:#214331!important;
     color:#f2f8f1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.table-responsive,.extension-table-wrapper,.monitoring-table-wrap,.eso-service-table-wrap,.my-sr-table-wrap,.eso-monitor-table-wrap,.monitor-flow-table-wrap,.monitor-report-table-wrap) {
    background:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.border,.border-top,.border-bottom):not(.btn):not(.badge) {
    border-color:#42624b!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(hr,.dropdown-divider) {
    border-color:#44624d!important;
     opacity:.75;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.dropdown-menu,.modal-content,.list-group-item) {
    background:#1a3628!important;
     border-color:#3d5c47!important;
     color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.dropdown-item,.dropdown-item-text,.modal-title) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .dropdown-item:hover {
    background:#25503a!important;
     color:#ffffff!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn) {
    background:#203e2c!important;
     background-image:none!important;
     color:#d7f0dc!important;
     border-color:#527861!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn):hover {
    background:#29543c!important;
     color:#ffffff!important;
     border-color:#79b28c!important;
     transform:none!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-asu,.btn.btn-asu,.review-action-btn,.call-submit,.monitoring-filter-btn,.eso-service-filter-btn) {
    box-shadow:none!important;
     transform:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-success,.call-alert-success) {
    background:#214734!important;
     border-color:#4e8a63!important;
     color:#d8f5df!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-danger,.call-alert-danger,.form-errors) {
    background:#492b2b!important;
     border-color:#915656!important;
     color:#ffdddd!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .alert-warning {
    background:#4b3b23!important;
     border-color:#886d3c!important;
     color:#f4e3b3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-info,.extension-review-inline-notice,.workflow-notice,.design-notice) {
    background:#203b30!important;
     border-color:#4b7860!important;
     color:#dcefe1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-empty-state,.call-empty,.eso-queue-empty,.my-sr-empty,.monitoring-empty,.monitor-report-empty,.eso-monitor-empty,.monitor-flow-empty,.extension-report-empty) {
    background:#162e22!important;
     color:#c9dfce!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-empty-state h3,.call-empty h3,.eso-queue-empty h3,.my-sr-empty h3,.monitoring-empty h3) {
    color:#edf7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.monitoring-track,.my-sr-progress-track,.extension-progress-track,.progress) {
    background-color:#355442!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.progress-bar,.monitoring-value) {
    color:#ffffff!important;
}

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-programs-table,.eso-queue-table,.extension-table) :is(.extension-title-cell strong,.extension-record-title,.extension-coordinator strong) {color:#f0f8f0!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.filter-card,.eso-queue-tabs,.eso-queue-panel,.feature-card) {background:#172f23!important;border-color:#395b46!important;color:#eef6ed!important;box-shadow:none!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.eso-queue-tab:not(.active),.filter-reset,.extension-add-program-btn.btn-outline-asu) {background:#1d3b2b!important;color:#dcefe0!important;border-color:#51705a!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.eso-queue-tab.active,.eso-queue-tab:hover) {color:#fff!important;background:#08752c!important;border-color:#329a5a!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-status,.extension-status-draft,.extension-status-review,.extension-status-revision,.extension-status-approved) {opacity:1!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .page-heading {background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%)!important}

</style>
<style>

/* =========================================================
   EXTENSION TABLE
========================================================= */

.extension-table-wrapper {
    overflow-x: auto;
}


.extension-table {
    width: 100%;
    margin: 0;
}


.extension-table thead th {
    padding: 14px 16px;
    border-bottom: 1px solid #e3e9e3;
    background: #f8faf8;
    color: #687269;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .035em;
    text-transform: uppercase;
    white-space: nowrap;
}


.extension-table tbody td {
    padding: 16px;
    border-bottom: 1px solid #edf1ed;
    color: #343c36;
    font-size: 11px;
    vertical-align: middle;
}


.extension-table tbody tr:last-child td {
    border-bottom: none;
}


.extension-title-cell {
    min-width: 250px;
}


.extension-title-cell strong {
    display: block;
    margin-bottom: 4px;
    color: #222a24;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
}


.extension-title-cell small {
    display: block;
    color: #868f88;
    font-size: 9px;
    line-height: 1.5;
}


.extension-coordinator {
    min-width: 145px;
}


.extension-coordinator strong {
    display: block;
    margin-bottom: 3px;
    font-size: 11px;
    font-weight: 600;
}


.extension-coordinator small {
    color: #8a928c;
    font-size: 9px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.extension-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 27px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
}


.extension-status-draft {
    background: #eef1ef;
    color: #626b64;
}


.extension-status-submitted {
    background: #e7f0ff;
    color: #365f99;
}


.extension-status-review {
    background: #fff4c8;
    color: #78620b;
}


.extension-status-revision {
    background: #fff0e2;
    color: #9a551c;
}


.extension-status-approved {
    background: #e4f4e8;
    color: #23703e;
}


.extension-status-ongoing {
    background: #e6f5ea;
    color: #08752c;
}


.extension-status-completed {
    background: #ddf1e2;
    color: #12652e;
}


.extension-status-rejected {
    background: #fde8e8;
    color: #a13939;
}


.extension-status-archived {
    background: #ebecef;
    color: #60656e;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.extension-empty-state {
    min-height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 25px;
    text-align: center;
}


.extension-empty-icon {
    width: 68px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    border-radius: 50%;
    background: #edf7ef;
    color: var(--asu-green);
    font-size: 27px;
}


.extension-empty-state h3 {
    margin: 0 0 7px;
    color: #283029;
    font-size: 15px;
    font-weight: 700;
}


.extension-empty-state p {
    max-width: 440px;
    margin: 0 0 17px;
    color: #7d867f;
    font-size: 10px;
    line-height: 1.65;
}




/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .extension-table {
        min-width: 980px;
    }
}



/* =========================================================
   FINAL EXTENSION MANAGEMENT DESIGN
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

.extension-management-page {
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

.page-heading {
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

.page-heading::after {
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

.page-heading > * {
    position: relative !important;
    z-index: 2 !important;
}

.page-heading h1 {
    margin: 0 !important;
    color: #fff !important;
    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.page-heading p {
    display: none !important;
}

.page-heading > .btn {
    width: auto !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    flex: 0 0 auto !important;
    border: 1px solid rgba(255,255,255,.15) !important;
    border-radius: 10px !important;
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    box-shadow: 0 10px 22px rgba(255,213,31,.20) !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    white-space: nowrap !important;
}

.page-heading > .btn:hover {
    background: #ffdb3f !important;
    color: var(--asu-green-deep) !important;
}

/* FLASH */

.alert {
    margin-bottom: 16px !important;
    border-radius: 12px !important;
    font-size: 10px !important;
}

/* STATS */

.stats-row {
    display: grid !important;
    grid-template-columns: repeat(4,minmax(0,1fr)) !important;
    gap: 14px !important;
    width: 100% !important;
    margin-bottom: 18px !important;
}

.stat-card {
    position: relative !important;
    overflow: hidden !important;
    min-width: 0 !important;
    min-height: 132px !important;
    height: 132px !important;
    padding: 16px 18px 20px !important;
    display: grid !important;
    grid-template-columns: 52px minmax(0,1fr) !important;
    align-items: center !important;
    gap: 15px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.055) !important;
}

.stat-card::after {
    content: "" !important;
    position: absolute !important;
    left: -5% !important;
    right: -5% !important;
    bottom: -36px !important;
    height: 54px !important;
    border-radius: 50% 50% 0 0 !important;
    pointer-events: none !important;
}

.stat-card:nth-child(odd)::after {
    background: #007a33 !important;
    opacity: .22 !important;
}

.stat-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}

.stat-card > * {
    position: relative !important;
    z-index: 2 !important;
}

.stat-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 15px !important;
    font-size: 21px !important;
}

.stat-icon.green {
    background: linear-gradient(135deg,#007a33,#005c26) !important;
    color: #fff !important;
    box-shadow: 0 10px 18px rgba(0,122,51,.20) !important;
}

.stat-icon.yellow {
    background: linear-gradient(135deg,#ffd51f,#f0bd00) !important;
    color: var(--asu-green-deep) !important;
    box-shadow: 0 10px 18px rgba(255,213,31,.24) !important;
}

.stat-content strong {
    display: block !important;
    margin: 0 0 6px !important;
    color: var(--asu-ink) !important;
    font-size: 28px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.stat-content span {
    display: block !important;
    color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* FILTER CARD */

.filter-card {
    margin-bottom: 18px !important;
    padding: 16px 18px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.05) !important;
}

.filter-card .form-control,
.filter-card .form-select,
.filter-card .input-group-text {
    min-height: 42px !important;
    height: 42px !important;
    border-color: #d7e0d8 !important;
    background: #fff !important;
    color: #263229 !important;
    font-size: 10.5px !important;
    box-shadow: none !important;
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: 10px !important;
}

.filter-card .input-group-text {
    border-radius: 10px 0 0 10px !important;
}

.filter-card .input-group .form-control {
    border-radius: 0 10px 10px 0 !important;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: #76ac82 !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}

.filter-card .btn {
    min-height: 42px !important;
    height: 42px !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 800 !important;
}

.filter-card .small {
    font-size: 9px !important;
    font-weight: 700 !important;
}

/* MAIN PANEL */

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

.extension-result-info {
    min-height: 27px !important;
    padding: 4px 9px !important;
    display: inline-flex !important;
    align-items: center !important;
    border: 1px solid rgba(0,122,51,.10) !important;
    border-radius: 999px !important;
    background: #f4f8f5 !important;
    color: #657068 !important;
    font-size: 9px !important;
    font-weight: 800 !important;
}

/* LANDSCAPE TABLE */

.extension-table-wrapper {
    width: 100% !important;
    overflow-x: auto !important;
}

.extension-table {
    width: 100% !important;
    min-width: 1080px !important;
    margin: 0 !important;
    border-collapse: collapse !important;
}

.extension-table thead th {
    padding: 13px 15px !important;
    border-bottom: 1px solid rgba(0,122,51,.10) !important;
    background: #f7faf7 !important;
    color: #687269 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .03em !important;
    white-space: nowrap !important;
}

.extension-table tbody td {
    padding: 14px 15px !important;
    border-bottom: 1px solid rgba(0,122,51,.07) !important;
    color: #343c36 !important;
    font-size: 10px !important;
    vertical-align: middle !important;
}

.extension-table tbody tr:hover td {
    background: rgba(0,122,51,.018) !important;
}

.extension-title-cell {
    min-width: 250px !important;
}

.extension-title-cell strong {
    margin-bottom: 4px !important;
    color: #222a24 !important;
    font-size: 11px !important;
    line-height: 1.4 !important;
    font-weight: 800 !important;
}

.extension-title-cell small,
.extension-coordinator small {
    color: #818b84 !important;
    font-size: 8.5px !important;
}

.extension-coordinator strong {
    font-size: 10px !important;
    font-weight: 700 !important;
}

/* STATUS BADGES */

.extension-status {
    min-height: 28px !important;
    padding: 4px 9px !important;
    border: 1px solid transparent !important;
    border-radius: 999px !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
}

.extension-status-draft {
    background: #f1f3f1 !important;
    color: #606861 !important;
    border-color: #e1e6e2 !important;
}

.extension-status-submitted,
.extension-status-review {
    background: #fff8d7 !important;
    color: #655315 !important;
    border-color: rgba(255,213,31,.42) !important;
}

.extension-status-revision {
    background: #fff2e5 !important;
    color: #8b541e !important;
    border-color: rgba(242,140,40,.28) !important;
}

.extension-status-approved,
.extension-status-ongoing,
.extension-status-completed {
    background: #edf7ef !important;
    color: var(--asu-green-dark) !important;
    border-color: rgba(0,122,51,.15) !important;
}

.extension-status-rejected,
.extension-status-archived {
    background: #eeeeee !important;
    color: #4b4f4c !important;
    border-color: #dddddd !important;
}

/* ACTION */

.extension-table .btn {
    min-height: 34px !important;
    padding: 0 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 9px !important;
    font-size: 9px !important;
    font-weight: 800 !important;
}

/* EMPTY */

.extension-empty-state {
    min-height: 260px !important;
    padding: 38px 24px !important;
}

.extension-empty-icon {
    width: 58px !important;
    height: 58px !important;
    margin-bottom: 13px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 24px !important;
}

.extension-empty-state h3 {
    font-size: 13px !important;
    font-weight: 800 !important;
}

.extension-empty-state p {
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

/* MODULE INFO — KEEP, BUT COMPACT */

.asu-panel.mt-4 {
    margin-top: 18px !important;
}

.panel-body {
    padding: 18px !important;
}

.feature-grid {
    display: grid !important;
    grid-template-columns: repeat(3,minmax(0,1fr)) !important;
    gap: 12px !important;
}

.feature-card {
    min-height: 145px !important;
    padding: 15px !important;
    border: 1px solid rgba(0,122,51,.08) !important;
    border-radius: 13px !important;
    background: #fbfcfb !important;
    box-shadow: none !important;
}

.feature-card-icon {
    width: 38px !important;
    height: 38px !important;
    margin-bottom: 10px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 17px !important;
}

.feature-card h5 {
    margin-bottom: 5px !important;
    color: #2c352f !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
}

.feature-card p {
    margin: 0 !important;
    color: #707a73 !important;
    font-size: 8.8px !important;
    line-height: 1.55 !important;
}

/* WORKFLOW NOTICE */

.design-notice {
    margin-top: 16px !important;
    padding: 11px 13px !important;
    border: 1px solid rgba(0,122,51,.10) !important;
    border-left: 4px solid var(--asu-green) !important;
    border-radius: 12px !important;
    background: rgba(255,255,255,.88) !important;
    color: #657068 !important;
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.design-notice strong {
    color: var(--asu-green-dark) !important;
}

/* BUTTON FAMILY */

.btn-asu,
.btn.btn-asu {
    border-color: var(--asu-green) !important;
    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(0,122,51,.13) !important;
}

.btn-outline-asu,
.btn.btn-outline-asu {
    border: 1px solid rgba(0,122,51,.22) !important;
    background: #fff !important;
    color: var(--asu-green) !important;
}

.btn-outline-asu:hover,
.btn.btn-outline-asu:hover {
    background: #f2f8f3 !important;
    color: var(--asu-green-dark) !important;
}

/* Yellow Add Program button: match Research New Proposal.                    */
/* This targeted rule comes AFTER the shared .btn.btn-asu rule, which was       */
/* overriding the banner's yellow background with a green gradient.            */
.page-heading > a.btn.btn-asu.extension-add-program-btn {
    background: #ffd51f !important;
    background-color: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
    box-shadow: 0 10px 22px rgba(255, 213, 31, .20) !important;
    transition: none !important;
}
.page-heading > a.btn.btn-asu.extension-add-program-btn:hover,
.page-heading > a.btn.btn-asu.extension-add-program-btn:focus-visible,
.page-heading > a.btn.btn-asu.extension-add-program-btn:active {
    /* Same deep gold as the Research New Proposal button on hover. */
    background: #f0bd00 !important;
    background-color: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #003f1a !important;
    box-shadow: 0 10px 22px rgba(240, 189, 0, .23) !important;
}
.page-heading > a.btn.btn-asu.extension-add-program-btn i {
    color: inherit !important;
}

/* ONE COPYRIGHT ONLY */

.content-footer {
    display: none !important;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .stats-row {
        grid-template-columns: repeat(2,minmax(0,1fr)) !important;
    }

    .feature-grid {
        grid-template-columns: repeat(2,minmax(0,1fr)) !important;
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

    .extension-management-page {
        padding: 18px 14px 24px !important;
    }

    .page-heading {
        min-height: 82px !important;
        padding: 18px !important;
        border-radius: 16px !important;
        flex-direction: column !important;
        align-items: flex-start !important;
    }

    .page-heading h1 {
        font-size: 27px !important;
    }

    .stats-row,
    .feature-grid {
        grid-template-columns: 1fr !important;
    }

    .stat-card {
        height: auto !important;
        min-height: 126px !important;
    }

    .panel-header {
        align-items: flex-start !important;
        flex-direction: column !important;
    }
}


/* =========================================================
   ESO PROCESSING QUEUE — matched to RPS queue presentation.
   Visual styles only: queue definitions, SQL and workflow remain intact.
========================================================= */
.eso-queue-tabs {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    padding: 9px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 122, 51, .12);
    border-radius: 18px;
    background: rgba(255, 255, 255, .97);
    box-shadow: 0 12px 25px rgba(16, 24, 16, .035);
}
.eso-queue-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 39px;
    padding: 0 10px;
    border-radius: 11px;
    color: #5a6960;
    background: transparent;
    font-size: 10.3px;
    line-height: 1.2;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
    transition: none !important;
}
.eso-queue-tab i {
    font-size: 12px;
    line-height: 1;
    color: #819087;
}
.eso-queue-tab:hover,
.eso-queue-tab:focus-visible {
    background: #edf7ef;
    color: #005c26;
    text-decoration: none;
}
.eso-queue-tab.active,
.eso-queue-tab.active:hover,
.eso-queue-tab.active:focus-visible {
    color: #fff;
    background: linear-gradient(110deg, #007a33, #005c26);
    box-shadow: 0 7px 13px rgba(0, 110, 46, .13);
}
.eso-queue-tab.active i {color: #fff;}

.eso-queue-panel {
    overflow: hidden;
    border: 1px solid rgba(0, 122, 51, .12);
    border-radius: 19px;
    background: #fff;
    box-shadow: 0 16px 36px rgba(16, 24, 16, .045);
}
.eso-queue-panel-head {
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 17px 20px;
    border-bottom: 1px solid #e9efe9;
    background: #fff;
}
.eso-queue-panel-title {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    margin: 0;
    color: #111d14;
    font-family: 'Century Gothic', sans-serif;
    font-size: 16px;
    line-height: 1.3;
    font-weight: 800;
}
.eso-queue-panel-title .eso-queue-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #edf7ef;
    color: #007a33;
    font-size: 17px;
}
.eso-queue-search {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
    padding: 11px 20px;
    border-bottom: 1px solid #edf1ed;
    background: #fbfdfb;
}
.eso-queue-search .eso-queue-search-input {
    flex: 1 1 0%;
    width: 100%;
    min-width: 0;
    max-width: none;
    height: 44px;
    min-height: 44px;
    padding: 10px 15px;
    border: 1px solid #d8e5db;
    border-radius: 10px;
    background: #fff;
    font-size: 12px;
}
.eso-queue-search .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    gap: 6px;
    height: 44px;
    min-height: 44px;
    padding: 8px 17px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.eso-queue-panel .table {width: 100%; margin-bottom: 0; border-collapse: collapse;}
.eso-queue-panel .table thead th {
    padding: 13px 16px;
    border-bottom: 1px solid #e6eee7;
    background: #f7faf8;
    color: #657168;
    font-family: 'Century Gothic', sans-serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .025em;
    text-transform: uppercase;
    white-space: nowrap;
    vertical-align: middle;
}
.eso-queue-panel .table tbody td {
    padding: 15px 16px;
    border-bottom: 1px solid #edf1ed;
    color: #354239;
    font-size: 11px;
    vertical-align: middle;
}
.eso-queue-panel .table tbody tr:last-child td {border-bottom: 0;}
.eso-queue-panel .table tbody tr:hover td {background: #f9fcfa;}
.eso-queue-panel .table td strong {color: #1b281f; font-size: 11.5px; font-weight: 800;}
.eso-queue-panel .table td small {color: #758379; font-size: 10px;}
.eso-queue-panel .table .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 35px;
    padding: 7px 10px;
    border-radius: 9px;
    font-size: 10px;
    white-space: nowrap;
}
.eso-queue-empty {
    min-height: 260px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 35px 20px 45px;
    text-align: center;
}
.eso-queue-empty .eso-queue-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 66px;
    height: 66px;
    margin-bottom: 16px;
    border-radius: 19px;
    background: #edf7ef;
    color: #007a33;
    font-size: 31px;
}
.eso-queue-empty h3 {
    margin: 0 0 6px;
    color: #3f4d43;
    font-size: 15px;
    font-weight: 800;
}
.eso-queue-empty p {
    max-width: 490px;
    margin: 0;
    color: #829087;
    font-size: 10px;
    line-height: 1.6;
}
@media (max-width: 990px) {
    .eso-queue-tabs {justify-content: flex-start;}
    .eso-queue-panel-head {padding: 15px;}
    .eso-queue-search {padding: 10px 15px;}
    .eso-queue-panel-title {font-size: 14px;}
}
@media (max-width: 600px) {
    .eso-queue-tab {padding: 9px 10px; min-height: 34px;}
    .eso-queue-search .eso-queue-search-input {flex: 1 1 100%; min-width: 0; max-width: none; width: 100%;}
}


/* Extension records: eye-only action, consistent with the Research table. */
.extension-table .btn.extension-eye-action {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 40px !important;
    min-width: 40px !important;
    height: 40px !important;
    min-height: 40px !important;
    padding: 0 !important;
    border-radius: 10px !important;
}
.extension-table .btn.extension-eye-action i {
    font-size: 16px !important;
    line-height: 1 !important;
    margin: 0 !important;
}


/* Extensionist records: same six-column rhythm as Research Records.
   No minimum landscape width, so the table never requires horizontal dragging. */
.extension-records-table-wrap.extension-table-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
}
.extension-table.extension-programs-table {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    table-layout: fixed !important;
    margin-bottom: 0 !important;
}
.extension-programs-table th,
.extension-programs-table td {
    white-space: normal !important;
    overflow-wrap: anywhere;
    word-break: normal;
}
.extension-programs-table thead th {
    padding: 13px 13px !important;
    text-align: left !important;
}
.extension-programs-table thead th:last-child {text-align: center !important;}
.extension-programs-table tbody td {
    padding: 16px 13px !important;
    line-height: 1.5;
}
.extension-programs-table .extension-record-title {
    display: block;
    color: #1c2a21;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.5;
}
.extension-programs-table .extension-record-meta {
    display: block;
    margin-top: 4px;
    color: #819087;
    font-size: 9px;
    line-height: 1.5;
    font-weight: 400;
}
.extension-programs-table .extension-community-meta {color: #64756a;}
.extension-programs-table .extension-college-name {font-weight: 600;}
.extension-programs-table .extension-record-muted {color: #89958e;}
.extension-programs-table .extension-status {
    max-width: 100%;
    height: auto;
    min-height: 28px;
    line-height: 1.3;
    text-align: center;
    white-space: normal !important;
    overflow-wrap: anywhere;
}
.extension-programs-table .extension-progress-value {
    width: 100%;
    min-width: 0;
}
.extension-programs-table .extension-progress-label {
    margin-bottom: 5px;
    text-align: right;
    font-size: 10px;
    font-weight: 800;
    color: #007a33;
}
.extension-programs-table .extension-progress-track {
    height: 6px;
    overflow: hidden;
    border-radius: 20px;
    background: #e4eee6;
}
.extension-programs-table .extension-progress-track > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #007a33;
}
.extension-programs-table .extension-schedule-date {font-size: 10px;}
.extension-programs-table .extension-action-cell {text-align: center !important;}
.extension-programs-table .extension-eye-action {
    width: 40px !important;
    min-width: 40px !important;
    height: 40px !important;
    min-height: 40px !important;
    padding: 0 !important;
    margin: 0 auto !important;
}
@media (max-width: 1150px) {
    .extension-programs-table colgroup,
    .extension-programs-table thead {display: none !important;}
    .extension-programs-table,
    .extension-programs-table tbody,
    .extension-programs-table tr,
    .extension-programs-table td {display: block !important; width: 100% !important;}
    .extension-programs-table tbody tr {
        padding: 14px 16px;
        border-bottom: 1px solid #eaf0eb;
    }
    .extension-programs-table tbody td {
        display: grid !important;
        grid-template-columns: minmax(105px, 32%) minmax(0,1fr);
        gap: 8px;
        align-items: start;
        padding: 8px 0 !important;
        border: 0 !important;
        text-align: left !important;
    }
    .extension-programs-table tbody td::before {
        content: attr(data-label);
        grid-column: 1;
        grid-row: 1;
        color: #6c796f;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .extension-programs-table tbody td > * {grid-column: 2;}
    .extension-programs-table .extension-progress-track {min-width: 0;}
    .extension-programs-table .extension-action-cell .extension-eye-action {margin: 0 !important;}
    .extension-programs-table .extension-progress-label {text-align: left;}
}
@media (max-width: 480px) {
    .extension-programs-table tbody td {grid-template-columns: minmax(90px, 38%) minmax(0,1fr);}
}


/* Research-like filter labels, without changing existing search/status/year logic. */
.extension-filter-label {
    display: block;
    margin: 0 0 7px;
    padding-left: 1px;
    color: #53665a;
    font-size: 10px;
    font-weight: 800;
    line-height: 1.3;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.extension-management-page .filter-card .row > .col-lg-2:last-child {
    display: flex;
    align-items: flex-end;
}
@media (max-width: 991px) {
    .extension-management-page .filter-card .row > .col-lg-2:last-child {display: block;}
}


/* Keep the Status filter dropdown chevron visible, matching Research. */
.extension-management-page .filter-card select#extensionProgramStatus {
    -webkit-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath d='m6 9 6 6 6-6' fill='none' stroke='%23005c26' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.3'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 15px center !important;
    background-size: 16px 16px !important;
    padding-right: 44px !important;
    cursor: pointer;
}



/* ============================================================
   ASU-REMS — PROPOSAL PROCESSING DARK MODE FINISH
   Queue toolbar is outside the original generic dark selectors.
   Keep the ESO search, tabs, tables and records unchanged.
   ============================================================ */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tabs {
    background:#162e22 !important;
    border-color:#395945 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab:not(.active) {
    color:#d4e8d8 !important;
    background:#1d3b2c !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab:not(.active) i {
    color:#94bd9f !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab:not(.active):hover,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab:not(.active):focus-visible {
    background:#2b503a !important;
    color:#fff !important;
    transform:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel {
    background:#162e22 !important;
    border-color:#395945 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel-head {
    background:#1d3c2c !important;
    border-bottom-color:#45634e !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel-title {
    color:#f0f8f0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-title-icon {
    background:#eaf6ee !important;
    color:#087a38 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search {
    background:#1b3829 !important;
    border-bottom:1px solid #41614b !important;
    color:#edf7ef !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search .eso-queue-search-input {
    background:#1c392b !important;
    color:#f2faf2 !important;
    border-color:#4b7056 !important;
    box-shadow:none !important;
    color-scheme:dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search .eso-queue-search-input::placeholder {
    color:#bcd5c0 !important;
    opacity:1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-empty {
    background:#162e22 !important;
    color:#cbded0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-empty h3 {
    color:#eaf6ec !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-empty p {
    color:#b7cdbb !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel .table td strong {
    color:#f3fbf3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel .table td small {
    color:#bad0c0 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel .table thead th {
    background:#203e2d !important;
    color:#d9eadc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel .table tbody td {
    background:#162e22 !important;
    color:#e6f2e9 !important;
    border-bottom-color:#3a5944 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-panel .table tbody tr:hover td {
    background:#224633 !important;
}
/* On My Proposals, the page-specific light filter styles use !important. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card {
    background:#182f23 !important;
    color:#eaf3eb !important;
    border-color:#395a45 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card :is(.form-control,.form-select,.input-group-text) {
    background-color:#1c392b !important;
    color:#f4faf4 !important;
    border-color:#4c7058 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card :is(.form-control,.form-select)::placeholder {
    color:#bcd5c0 !important;
    opacity:1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card select.form-select {
    color-scheme:dark;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23DCEFE1' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-position:right 14px center !important;
    background-repeat:no-repeat !important;
    background-size:16px 16px !important;
    padding-right:42px !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card .form-select option {
    background:#1c392b !important;
    color:#f4faf4 !important;
}
/* Keep the familiar green/gold banner bright in both themes. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .page-heading {
    background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .page-heading h1 {
    color:#fff !important;
}

</style>


<style id="rems-extension-index-research-dark-finish">
/* ============================================================
   ASU-REMS / EXTENSION INDEX — RESEARCH-MATCHED DARK THEME
   Kept LAST so the light-mode !important rules cannot win.
   Scoped to extension/index.php; no workflow or layout changes.
   ============================================================ */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope {
    color: #eaf4ed !important;
    background: #0b1e16 !important;
    background-image: none !important;
    --bs-body-bg: #0b1e16;
    --bs-body-color: #eaf4ed;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-management-page {
    background: #0b1e16 !important;
    background-image: none !important;
    color: #eaf4ed !important;
}
/* Top bar follows Research's dark green rather than the light rule. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar {
    background: #10291d !important;
    background-image: none !important;
    border-bottom: 1px solid #34513f !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .system-brand-copy strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .topbar-user-text strong {
    color: #f3faf3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .system-brand-copy span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .topbar-user-text span {
    color: #bcd5c5 !important;
}
/* All major white cards and their light header strips. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.asu-panel, .filter-card, .stat-card, .feature-card, .eso-queue-panel, .eso-queue-tabs) {
    background: #183428 !important;
    background-image: none !important;
    border-color: #375c46 !important;
    color: #eaf4ed !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.panel-header, .eso-queue-panel-head) {
    background: #1c3b2c !important;
    background-image: none !important;
    border-bottom-color: #3c5d48 !important;
    color: #f2faf2 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .panel-body {
    background: #183428 !important;
    color: #eaf4ed !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.panel-title, .eso-queue-panel-title, .feature-card h5, .stat-content strong, .stat-content span) {
    color: #f0f8f1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.feature-card p, .extension-record-meta, .extension-community-meta, .extension-record-muted, .extension-college-name, .extension-schedule-date, .extension-coordinator small, .extension-title-cell small) {
    color: #c2d9c8 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.panel-title-icon, .feature-card-icon, .extension-empty-icon, .eso-queue-title-icon, .eso-queue-empty-icon) {
    background: #28513b !important;
    border: 1px solid #386a4d !important;
    color: #88daa6 !important;
    box-shadow: none !important;
}
/* Filter inputs: no white boxes in dark mode. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-filter-label, .filter-card label) {
    color: #d2e8d6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card :is(.form-control, .form-select, .input-group-text),
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search .eso-queue-search-input {
    background-color: #203d2e !important;
    border-color: #49705a !important;
    color: #f2f9f3 !important;
    box-shadow: none !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card :is(input, select)::placeholder,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search input::placeholder {
    color: #bad2c0 !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card select#extensionProgramStatus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath d='m6 9 6 6 6-6' fill='none' stroke='%23D5F0DF' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.3'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 15px center !important;
    background-size: 16px 16px !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .filter-card .form-select option {
    background: #203d2e !important;
    color: #f2f9f3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-search {
    background: #1b382a !important;
    border-bottom-color: #3e5d49 !important;
}
/* Research-style table: dark header and alternating deep-green rows. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table, .eso-queue-panel .table) {
    --bs-table-bg: #173126 !important;
    --bs-table-color: #eaf4ed !important;
    --bs-table-border-color: #3b5b46 !important;
    --bs-table-striped-bg: #214332 !important;
    --bs-table-hover-bg: #294d3a !important;
    --bs-table-hover-color: #ffffff !important;
    background: #173126 !important;
    color: #eaf4ed !important;
    border-color: #3b5b46 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table, .eso-queue-panel .table) thead th {
    background: #234332 !important;
    background-color: #234332 !important;
    color: #e0f0e2 !important;
    border-bottom-color: #44624f !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table, .eso-queue-panel .table) tbody tr:nth-child(odd) > :is(td, th) {
    background: #173126 !important;
    background-color: #173126 !important;
    border-bottom-color: #3b5b46 !important;
    color: #e5f3e8 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table, .eso-queue-panel .table) tbody tr:nth-child(even) > :is(td, th) {
    background: #214332 !important;
    background-color: #214332 !important;
    border-bottom-color: #3b5b46 !important;
    color: #e5f3e8 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table, .eso-queue-panel .table) tbody tr:hover > :is(td, th) {
    background: #2b513d !important;
    background-color: #2b513d !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-record-title, .extension-title-cell strong, .eso-queue-panel .table td strong) {
    color: #f5fbf5 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-programs-table .extension-record-meta, .eso-queue-panel .table td small) {
    color: #bfd7c6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-programs-table tbody td::before {
    color: #bfd9c7 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-records-table-wrap {
    background: #173126 !important;
}
/* Status pills match Research: outlined, muted dark badges (not white). */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-status {
    box-shadow: none !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-status-approved, .extension-status-ongoing, .extension-status-completed) {
    background: #194b32 !important;
    border-color: #43865b !important;
    color: #d4f7df !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-status-submitted, .extension-status-review) {
    background: #554a25 !important;
    border-color: #927b3d !important;
    color: #ffe3a6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-status-revision {
    background: #573c27 !important;
    border-color: #93613d !important;
    color: #ffe0be !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-status-draft, .extension-status-archived) {
    background: #304239 !important;
    border-color: #62796b !important;
    color: #e3ede6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-status-rejected {
    background: #522e34 !important;
    border-color: #98616a !important;
    color: #ffdae0 !important;
}
/* Progress indicators and eye-only action. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-progress-label {
    color: #5bdf96 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-progress-track {
    background: #365746 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-progress-track > span {
    background: #0b923c !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-eye-action, .btn-outline-asu, .eso-queue-panel .btn-outline-asu) {
    background: #28523b !important;
    background-image: none !important;
    border-color: #527963 !important;
    color: #f5fcf5 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-eye-action, .btn-outline-asu, .eso-queue-panel .btn-outline-asu):hover {
    background: #306247 !important;
    border-color: #78ab89 !important;
    color: #fff !important;
}
/* Secondary labels, panels, and info cards must stay dark. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-result-info {
    background: #28503a !important;
    border-color: #4d755a !important;
    color: #e8f6e9 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-empty-state, .eso-queue-empty) {
    background: #183428 !important;
    color: #e3f0e5 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-empty-state h3, .eso-queue-empty h3) {
    color: #f2faf3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-empty-state p, .eso-queue-empty p) {
    color: #bfd4c4 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .design-notice {
    background: #1c392b !important;
    border-color: #45634e !important;
    color: #d8ebdc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .design-notice strong {
    color: #8ee0aa !important;
}
/* ESO stage tabs remain visually consistent with the same palette. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab:not(.active) {
    background: #244331 !important;
    color: #d9eadd !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .eso-queue-tab.active {
    background: #087932 !important;
    border-color: #36915a !important;
    color: #fff !important;
}
/* Preserve the existing green banner and gold Add Program button. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .page-heading {
    background: linear-gradient(112deg, #005c26 0%, #007a33 64%, #2c9a39 100%) !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .page-heading h1 {
    color: #fff !important;
}
</style>


<div class="main-page rems-extension-dark-scope">


    <!-- =====================================================
         TOP HEADER
    ====================================================== -->

    <header class="asu-topbar">


        <div class="topbar-left">


            <button
                id="sidebarToggle"
                class="sidebar-toggle"
                type="button"
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

                    <?= e(
                        fullName($user)
                    ); ?>

                </strong>


                <span>

                    <?= e(
                        roleLabel(
                            $user['role']
                        )
                    ); ?>

                </span>


            </div>



            <div class="dropdown">


                <button
                    type="button"
                    class="user-dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <div class="user-avatar">

                        <?= e(
                            $initials
                        ); ?>

                    </div>

                </button>


                <ul
                    class="
                        dropdown-menu
                        dropdown-menu-end
                    "
                >


                    <li>

                        <div class="dropdown-item-text">

                            <strong class="d-block">

                                <?= e(
                                    fullName($user)
                                ); ?>

                            </strong>


                            <small class="text-muted">

                                <?= e(
                                    $user['email']
                                ); ?>

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

                            <i
                                class="
                                    bi
                                    bi-box-arrow-right
                                    me-2
                                "
                            ></i>

                            Logout

                        </a>

                    </li>


                </ul>


            </div>


        </div>


    </header>



    <!-- =====================================================
         PAGE CONTENT
    ====================================================== -->

    <main class="page-content extension-management-page">


        <!-- =================================================
             PAGE HEADING
        ================================================== -->

        <div class="page-heading">


            <div>


                <h1>

                    <?= $isOffice ? 'Proposal Processing' : 'Extension Management'; ?>

                </h1>


                <p>

                    <?php if (
                        $isProponent
                    ): ?>

                        Manage your extension programs,
                        documents and implementation records.

                    <?php else: ?>

                        Review submitted extension programs
                        and monitor institutional extension
                        records.

                    <?php endif; ?>

                </p>


            </div>


            <?php if (
                extensionCanSubmitProposal()
            ): ?>


                <a
                    href="<?= BASE_URL; ?>/extension/create.php"
                    class="btn btn-asu extension-add-program-btn"
                >

                    <i class="bi bi-plus-lg me-1"></i>

                    Add Program

                </a>


            <?php endif; ?>


        </div>



        <!-- =================================================
             FLASH MESSAGE
        ================================================== -->

        <?php if ($flash): ?>


            <div
                class="
                    alert
                    alert-<?= e(
                        $flash['type']
                    ); ?>
                    alert-dismissible
                    fade
                    show
                "
                role="alert"
            >

                <?= e(
                    $flash['message']
                ); ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>


            </div>


        <?php endif; ?>




        <?php if ($isOffice): ?>

        <!-- ESO-ONLY QUEUE: RPS-matched presentation, existing status-driven workflow. -->
        <nav class="eso-queue-tabs" aria-label="Extension processing stages">
            <?php foreach ($esoQueues as $key => $queue): ?>
                <a
                    class="eso-queue-tab <?= $esoQueueKey === $key ? 'active' : ''; ?>"
                    href="<?= BASE_URL; ?>/extension/index.php?queue=<?= urlencode($key); ?>"
                    <?= $esoQueueKey === $key ? 'aria-current="page"' : ''; ?>
                    title="<?= e($queue['title']); ?>"
                >
                    <i class="bi <?= e($queue['icon']); ?>" aria-hidden="true"></i>
                    <?= e($queue['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="eso-queue-panel" aria-labelledby="esoQueuePanelTitle">
            <div class="eso-queue-panel-head">
                <h2 class="eso-queue-panel-title" id="esoQueuePanelTitle">
                    <span class="eso-queue-title-icon">
                        <i class="bi <?= e($esoQueues[$esoQueueKey]['icon']); ?>" aria-hidden="true"></i>
                    </span>
                    <?= e($esoQueues[$esoQueueKey]['title']); ?>
                </h2>
            </div>

            <!-- Keep ESO search/filter functionality, in a slim toolbar beneath the heading. -->
            <form method="GET" action="<?= BASE_URL; ?>/extension/index.php" class="eso-queue-search">
                <input type="hidden" name="queue" value="<?= e($esoQueueKey); ?>">

                <input
                    type="search"
                    name="q"
                    class="form-control eso-queue-search-input"
                    placeholder="Search proposal or proponent"
                    value="<?= e($esoQueueSearch); ?>"
                    aria-label="Search extension queue"
                >
                <button type="submit" class="btn btn-asu btn-sm">
                    <i class="bi bi-search" aria-hidden="true"></i> Search
                </button>
                <?php if ($esoQueueSearch !== ''): ?>
                    <a class="btn btn-outline-asu btn-sm" href="<?= BASE_URL; ?>/extension/index.php?queue=<?= urlencode($esoQueueKey); ?>">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$esoQueueRows): ?>
                <div class="eso-queue-empty">
                    <span class="eso-queue-empty-icon">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                    </span>
                    <h3><?= $esoQueueSearch !== '' ? 'No matching proposals' : 'No proposals in this stage'; ?></h3>
                    <p><?= $esoQueueSearch !== ''
                        ? 'Try a different proposal title or proponent name.'
                        : 'Proposals will appear here when they reach this processing stage.'; ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Proposal</th>
                                <th>Proponent</th>
                                <th>Current Status</th>
                                <th>Last Updated</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($esoQueueRows as $item): ?>
                                <?php
                                    $action = extensionEsoQueueAction((string)$item['status']);
                                    $actionUrl = BASE_URL . '/extension/' . $action[0] . '?id=' . (int)$item['extension_id'];
                                    $proponentName = trim(implode(' ', array_filter([
                                        $item['first_name'],
                                        $item['middle_name'],
                                        $item['last_name']
                                    ], static fn($v) => $v !== null && $v !== '')));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['program_title']); ?></strong>
                                        <br>
                                        <small><?= e($item['target_community'] ?: 'No target community'); ?></small>
                                    </td>
                                    <td><?= e($proponentName); ?></td>
                                    <td>
                                        <span class="extension-status <?= in_array($item['status'], ['For UERC Revision', 'For In-House Revision'], true) ? 'extension-status-revision' : ($item['status'] === 'Approved' ? 'extension-status-approved' : 'extension-status-review'); ?>">
                                            <?= e(extensionStatusLabel((string)$item['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?= e($item['updated_at'] ? date('M d, Y h:i A', strtotime($item['updated_at'])) : 'Not recorded'); ?></td>
                                    <td class="text-center">
                                        <a class="btn btn-outline-asu btn-sm" href="<?= e($actionUrl); ?>">
                                            <i class="bi <?= $action[0] === 'view.php' ? 'bi-eye' : 'bi-arrow-right-circle'; ?>" aria-hidden="true"></i>
                                            <?= e($action[1]); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php else: ?>

        <!-- =================================================
             SEARCH AND FILTERS
        ================================================== -->

        <section class="filter-card">


            <form
                method="GET"
                action=""
            >


                <div class="row g-2">


                    <div class="col-lg-5">
                        <label class="extension-filter-label" for="extensionProgramSearch">Search</label>
                        <div class="input-group">


                            <span
                                class="
                                    input-group-text
                                    bg-white
                                "
                            >

                                <i class="bi bi-search"></i>

                            </span>


                            <input
                                type="search"
                                id="extensionProgramSearch"
                                name="search"
                                class="form-control"
                                placeholder="Search program, community or coordinator"
                                value="<?= e(
                                    $search
                                ); ?>"
                            >


                        </div>


                    </div>



                    <div class="col-lg-3">
                        <label class="extension-filter-label" for="extensionProgramStatus">Status</label>
                        <select
                            id="extensionProgramStatus"
                            name="status"
                            class="form-select"
                        >


                            <option value="">

                                All Status

                            </option>


                            <?php foreach (
                                $validStatuses
                                as
                                $statusOption
                            ): ?>


                                <?php

                                if (
                                    $isOffice
                                    &&
                                    $statusOption === 'Draft'
                                ) {

                                    continue;
                                }

                                ?>


                                <option
                                    value="<?= e(
                                        $statusOption
                                    ); ?>"
                                    <?= $status ===
                                        $statusOption
                                            ? 'selected'
                                            : ''; ?>
                                >

                                    <?= e(
                                        $statusOption
                                    ); ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>



                    <div class="col-lg-2">
                        <label class="extension-filter-label" for="extensionProgramYear">Year</label>
                        <input
                            type="number"
                            id="extensionProgramYear"
                            name="year"
                            class="form-control"
                            placeholder="Year"
                            min="2000"
                            max="2100"
                            value="<?= e(
                                $year
                            ); ?>"
                        >


                    </div>



                    <div class="col-lg-2">


                        <button
                            type="submit"
                            class="
                                btn
                                btn-asu
                                w-100
                            "
                        >

                            <i class="bi bi-funnel me-1"></i>

                            Filter

                        </button>


                    </div>


                </div>



                <?php if (
                    $search !== ''
                    ||
                    $status !== ''
                    ||
                    $year !== ''
                ): ?>


                    <div class="mt-3">


                        <a
                            href="<?= BASE_URL; ?>/extension/index.php"
                            class="small"
                            style="
                                color:
                                var(--asu-green);
                            "
                        >

                            <i class="bi bi-x-circle me-1"></i>

                            Clear Filters

                        </a>


                    </div>


                <?php endif; ?>


            </form>


        </section>



        <!-- =================================================
             EXTENSION PROGRAM RECORDS
        ================================================== -->

        <section class="asu-panel">


            <div class="panel-header">


                <h2 class="panel-title">


                    <span class="panel-title-icon">

                        <i class="bi bi-megaphone"></i>

                    </span>


                    Extension Programs


                </h2>



            </div>



            <?php if (
                empty(
                    $programs
                )
            ): ?>


                <div class="extension-empty-state">


                    <div class="extension-empty-icon">

                        <i class="bi bi-folder2-open"></i>

                    </div>


                    <h3>

                        <?php if (
                            $search !== ''
                            ||
                            $status !== ''
                            ||
                            $year !== ''
                        ): ?>

                            No matching extension programs

                        <?php else: ?>

                            No extension programs yet

                        <?php endif; ?>

                    </h3>


                    <p>

                        <?php if (
                            $search !== ''
                            ||
                            $status !== ''
                            ||
                            $year !== ''
                        ): ?>

                            No extension program records
                            match the selected search or
                            filter criteria.

                        <?php elseif (
                            $isProponent
                        ): ?>

                            You have not created an extension
                            program yet. Create your first
                            program and save it as a draft.

                        <?php else: ?>

                            No extension programs have been
                            submitted for Extension Service Office
                            review yet.

                        <?php endif; ?>

                    </p>



                    <?php if (
                        extensionCanSubmitProposal()
                        &&
                        $search === ''
                        &&
                        $status === ''
                        &&
                        $year === ''
                    ): ?>


                        <a
                            href="<?= BASE_URL; ?>/extension/create.php"
                            class="btn btn-asu"
                        >

                            <i class="bi bi-plus-lg me-1"></i>

                            Add First Program

                        </a>


                    <?php endif; ?>


                </div>


            <?php else: ?>


                <div class="extension-table-wrapper extension-records-table-wrap">
                    <table class="table extension-table extension-programs-table align-middle">
                        <colgroup>
                            <col style="width:42%">
                            <col style="width:20%">
                            <col style="width:16%">
                            <col style="width:14%">
                            <col style="width:8%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Program</th>
                                <th scope="col">College / Department</th>
                                <th scope="col">Status</th>
                                <th scope="col">Schedule</th>
                                <th scope="col" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $program): ?>
                                <?php
                                    $statusClass = match ((string)($program['status'] ?? '')) {
                                        'Submitted' => 'extension-status-submitted',
                                        'UERC Technical Review', 'For Pre-In-House Endorsement', 'University In-House Review',
                                        'For Endorsement', 'For LADCO/ADCO Approval',
                                        'For BOR Notation', 'For Certification', 'Under Review'
                                            => 'extension-status-review',
                                        'For UERC Revision', 'For In-House Revision', 'For Revision'
                                            => 'extension-status-revision',
                                        'Approved' => 'extension-status-approved',
                                        'Ongoing' => 'extension-status-ongoing',
                                        'Completed' => 'extension-status-completed',
                                        'Rejected' => 'extension-status-rejected',
                                        'Archived' => 'extension-status-archived',
                                        default => 'extension-status-draft',
                                    };
                                    $coordinatorName = trim(implode(' ', array_filter([
                                        $program['first_name'] ?? '',
                                        $program['middle_name'] ?? '',
                                        $program['last_name'] ?? ''
                                    ], static fn($v) => trim((string)$v) !== '')));
                                    $location = implode(', ', array_filter([
                                        $program['target_community'] ?? '',
                                        $program['municipality'] ?? '',
                                        $program['province'] ?? ''
                                    ], static fn($v) => trim((string)$v) !== ''));
                                    $startDate = !empty($program['start_date']) ? strtotime((string)$program['start_date']) : false;
                                    $endDate = !empty($program['end_date']) ? strtotime((string)$program['end_date']) : false;
                                ?>
                                <tr>
                                    <td data-label="Program" class="extension-program-primary">
                                        <strong class="extension-record-title"><?= e($program['program_title'] ?: 'Untitled Program'); ?></strong>
                                        <small class="extension-record-meta">
                                            <?= e($program['program_type'] ?: 'Extension Program'); ?>
                                            <?php if (!empty($program['program_category'])): ?>
                                                · <?= e($program['program_category']); ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($location !== ''): ?>
                                            <small class="extension-record-meta extension-community-meta">
                                                <?= e($location); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($isOffice): ?>
                                            <small class="extension-record-meta">Coordinator: <?= e($coordinatorName); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="College / Department">
                                        <?php if (!empty($program['college_name'])): ?>
                                            <span class="extension-college-name"><?= e($program['college_name']); ?></span>
                                            <small class="extension-record-meta"><?= e($program['department_name'] ?: 'No department'); ?></small>
                                        <?php else: ?>
                                            <span class="extension-record-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="extension-status <?= e($statusClass); ?>"><?= e(extensionStatusLabel((string)$program['status'])); ?></span>
                                    </td>
                                    <td data-label="Schedule" class="extension-schedule-date">
                                        <?php if ($startDate): ?>
                                            <span><?= e(date('M d, Y', $startDate)); ?></span>
                                            <?php if ($endDate): ?>
                                                <small class="extension-record-meta">to <?= e(date('M d, Y', $endDate)); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="extension-record-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action" class="extension-action-cell">
                                        <a href="<?= BASE_URL; ?>/extension/view.php?id=<?= (int)$program['extension_id']; ?>"
                                           class="btn btn-outline-asu btn-sm extension-eye-action"
                                           title="View extension program" aria-label="View extension program">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>


        </section>



        <!-- =================================================
             MODULE INFORMATION
        ================================================== -->

        <section class="asu-panel mt-4">


            <div class="panel-header">


                <h2 class="panel-title">


                    <span class="panel-title-icon">

                        <i class="bi bi-info-circle"></i>

                    </span>


                    Extension Module


                </h2>


            </div>



            <div class="panel-body">


                <div class="feature-grid">


                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-folder2-open"></i>

                        </div>


                        <h5>

                            Program Records

                        </h5>


                        <p>

                            Store actual extension program
                            information including dates,
                            target community, coordinator,
                            description and implementation
                            status.

                        </p>


                    </div>



                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-people"></i>

                        </div>


                        <h5>

                            Participant Management

                        </h5>


                        <p>

                            Participant and attendance
                            management will be connected
                            to each approved extension
                            program.

                        </p>


                    </div>



                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-file-earmark-text"></i>

                        </div>


                        <h5>

                            Documents

                        </h5>


                        <p>

                            Program proposals and supporting
                            documents uploaded by authorized
                            users will be attached to the
                            corresponding program record.

                        </p>


                    </div>



                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-check2-square"></i>

                        </div>


                        <h5>

                            Accomplishment Reports

                        </h5>


                        <p>

                            Approved programs will later
                            support accomplishment reports,
                            actual outputs and supporting
                            documentation.

                        </p>


                    </div>



                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-arrow-repeat"></i>

                        </div>


                        <h5>

                            Program Monitoring

                        </h5>


                        <p>

                            Program status and implementation
                            progress will follow the approved
                            extension workflow.

                        </p>


                    </div>



                    <div class="feature-card">


                        <div class="feature-card-icon">

                            <i class="bi bi-archive"></i>

                        </div>


                        <h5>

                            Institutional Records

                        </h5>


                        <p>

                            Completed extension records will
                            remain available to authorized
                            users for historical retrieval
                            and institutional reporting.

                        </p>


                    </div>


                </div>


            </div>


        </section>



        <!-- =================================================
             WORKFLOW NOTICE
        ================================================== -->

        <div class="design-notice">


            <strong>

                Extension Workflow:

            </strong>


            <?php if (
                $isProponent
            ): ?>

                New programs are saved as Draft records.
                Drafts remain private until they are
                formally submitted for Extension Service Office
                review.

            <?php else: ?>

                Draft programs remain private to their
                proponent. Submitted programs
                become available for Extension Service Office
                review and monitoring.

            <?php endif; ?>


        </div>



        <?php endif; // End ESO processing overview / existing proponent interface ?>

        <!-- =================================================
             FOOTER
        ================================================== -->
</main>


</div>


<?php

require __DIR__ .
    '/../includes/footer.php';

?>
