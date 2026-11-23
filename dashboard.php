<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile_avatar.php';

requireLogin();

$user = currentUser();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';


$userRole =
    $user['role'] ?? '';

$userId =
    (int)($user['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR DASHBOARD
|--------------------------------------------------------------------------
|
| Administrators use the dedicated system-administration dashboard.
| Research workflow processing remains with RPS.
|
*/
if ($userRole === 'admin') {
    redirect(BASE_URL . '/admin/index.php');
}

/*
|--------------------------------------------------------------------------
| FACULTY / RESEARCHER DASHBOARD ACCESS
|--------------------------------------------------------------------------
|
| Faculty users may view the welcome dashboard at /dashboard.php.
| Research Management remains a separate module at /research/index.php.
| Only legacy faculty role values are normalized; other roles are unchanged.
|
*/
$legacyResearcherRoles = [
    'researcher',
    'faculty_researcher',
    'faculty researcher',
    'faculty/researcher',
    'faculty / researcher',
    'faculty-researcher'
];

$storedRole = trim((string)$userRole);
$normalizedRole = strtolower($storedRole);

if (in_array($normalizedRole, $legacyResearcherRoles, true) && $userId > 0) {
    try {
        $roleUpdate = $pdo->prepare("
            UPDATE users
            SET role = 'faculty'
            WHERE user_id = ? AND role = ?
        ");
        $roleUpdate->execute([$userId, $storedRole]);

        if ($roleUpdate->rowCount() === 1) {
            $userRole = 'faculty';
        } else {
            // Avoid granting access if the database role changed meanwhile.
            $roleCheck = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
            $roleCheck->execute([$userId]);
            $userRole = (string)$roleCheck->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log('ASU-REMS legacy researcher role migration: ' . $e->getMessage());
        // Do not change the session role if database update failed.
    }
}

if ($userRole === 'faculty') {
    // Keep the session role compatible with the Research Management module.
    // Do NOT redirect: this page is the Faculty/Researcher welcome dashboard.
    $_SESSION['role'] = 'faculty';
}


/*
|--------------------------------------------------------------------------
| DASHBOARD VIEW
|--------------------------------------------------------------------------
*/

$dashboardView =
    trim(
        $_GET['view']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| VALIDATE VIEW
|--------------------------------------------------------------------------
*/

if (
    $dashboardView !== ''
    &&
    $dashboardView !== 'processing'
) {

    $dashboardView = '';
}


/*
|--------------------------------------------------------------------------
| PROCESSING VIEW
|--------------------------------------------------------------------------
*/

$isProcessingView =
    $userRole === 'rps'
    &&
    $dashboardView === 'processing';


/*
|--------------------------------------------------------------------------
| USER INFORMATION
|--------------------------------------------------------------------------
*/

$firstName =
    $user['first_name']
    ?? 'User';


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
| ROLE LABEL
|--------------------------------------------------------------------------
*/

if (
    $userRole === 'rps'
) {

    $displayRole =
        'Research Processing Staff (RPS)';

} else {

    $displayRole =
        roleLabel(
            $userRole
        );
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$activeResearch = 0;

$extensionPrograms = 0;

$facultyResearchers = 0;

$completedProjects = 0;

$proposalsThisMonth = 0;

$pendingApprovals = 0;

$communityParticipants = 0;

$reportsGenerated = 0;

// Faculty/Researcher-only dashboard summaries (scoped to the current user).
$facultyTotalProposals = 0;
$facultyUnderReview = 0;
$facultyOngoing = 0;

if ($userRole === 'faculty' && $userId > 0) {
    try {
        $facultySummaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END), 0) AS review_count,
                COALESCE(SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END), 0) AS ongoing_count,
                COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_count
            FROM research_proposals
            WHERE researcher_id = ?
        ");
        $facultySummaryStmt->execute([$userId]);
        $facultySummary = $facultySummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $facultyTotalProposals = (int)($facultySummary['total_count'] ?? 0);
        $facultyUnderReview = (int)($facultySummary['review_count'] ?? 0);
        $facultyOngoing = (int)($facultySummary['ongoing_count'] ?? 0);
        $facultyCompleted = (int)($facultySummary['completed_count'] ?? 0);
    } catch (Throwable $e) {
        error_log('ASU-REMS Faculty Dashboard Summary: ' . $e->getMessage());
        $facultyCompleted = 0;
    }
}


/*
|--------------------------------------------------------------------------
| RPS VALUES
|--------------------------------------------------------------------------
*/

$rpsPendingSubmissions = 0;

$rpsEthicsQueue = 0;

$rpsUrrcQueue = 0;

$rpsInhouseQueue = 0;

$rpsReadyForNtp = 0;

$rpsPermitQueue = 0;


/*
|--------------------------------------------------------------------------
| FACULTY COUNT
|--------------------------------------------------------------------------
*/

try {

    $facultyStmt =
        $pdo->prepare("
            SELECT COUNT(*)

            FROM users

            WHERE role = 'faculty'

              AND status = 'Active'
        ");

    $facultyStmt->execute();

    $facultyResearchers =
        (int)$facultyStmt
            ->fetchColumn();


} catch (Throwable $e) {

    $facultyResearchers = 0;

    error_log(
        'Dashboard Faculty Count Error: '
        .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| RESEARCH COUNTS
|--------------------------------------------------------------------------
*/

try {

    /*
    |----------------------------------------------------------------------
    | IMPORTANT
    |----------------------------------------------------------------------
    | This dashboard must count the same table used by the Research module
    | and RPS queue: research_proposals.
    |
    | Queue counts are based on workflow_status, because the detailed
    | flowchart process uses:
    | SUBMITTED, FOR_ETHICS_REVIEW, FOR_URRC, FOR_INHOUSE,
    | READY_FOR_NTP, TERMINAL_REPORT_SUBMITTED.
    */

    if (
        $userRole === 'faculty'
    ) {

        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_proposals

                WHERE researcher_id = ?

                  AND COALESCE(is_archived, 0) = 0

                  AND status NOT IN
                  (
                      'Completed',
                      'Rejected',
                      'Archived'
                  )
            ");

        $stmt->execute([
            $userId
        ]);

        $activeResearch =
            (int)$stmt->fetchColumn();


        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_proposals

                WHERE researcher_id = ?

                  AND status = 'Completed'
            ");

        $stmt->execute([
            $userId
        ]);

        $completedProjects =
            (int)$stmt->fetchColumn();


        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_proposals

                WHERE researcher_id = ?

                  AND submitted_at >=
                      DATE_FORMAT(
                          CURRENT_DATE,
                          '%Y-%m-01'
                      )
            ");

        $stmt->execute([
            $userId
        ]);

        $proposalsThisMonth =
            (int)$stmt->fetchColumn();
    }


    if (
        in_array(
            $userRole,
            [
                'admin',
                'rps'
            ],
            true
        )
    ) {

        $activeResearch =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_proposals

                    WHERE COALESCE(is_archived, 0) = 0

                      AND status NOT IN
                      (
                          'Draft',
                          'Completed',
                          'Rejected',
                          'Archived'
                      )
                ")
                ->fetchColumn();


        $completedProjects =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_proposals

                    WHERE status = 'Completed'
                ")
                ->fetchColumn();


        $proposalsThisMonth =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_proposals

                    WHERE submitted_at >=
                        DATE_FORMAT(
                            CURRENT_DATE,
                            '%Y-%m-01'
                        )
                ")
                ->fetchColumn();
    }


    /*
    |----------------------------------------------------------------------
    | RPS QUEUES — KEEP COUNTS IDENTICAL TO research/rps_queue.php
    |----------------------------------------------------------------------
    | Includes Step 2 pathway-selection states and Self-Initiated pre-ethics.
    | Older For URRC records awaiting their initial RPS forwarding must be
    | counted under New Research Submissions, not URRC Review.
    |
    | These are active queue counts, NOT unread notification counts. Records
    | awaiting faculty revisions are correctly excluded until resubmission.
    */

    if ($userRole === 'rps') {

        // Same intake exception used in research/rps_queue.php.
        $rpsEarlyUrrcAwaitingIntake = "(
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

        $rpsQueueCountStmt = $pdo->query("
            SELECT
                COALESCE(SUM(CASE WHEN (
                    rp.workflow_status IN (
                        'SUBMITTED',
                        'AWAITING_PATHWAY_SELECTION',
                        'FOR_URRC_PATH_SELECTED'
                    )
                    OR $rpsEarlyUrrcAwaitingIntake
                ) THEN 1 ELSE 0 END), 0) AS submissions_count,

                COALESCE(SUM(CASE WHEN rp.workflow_status IN (
                    'FOR_ETHICS_REVIEW',
                    'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS'
                ) THEN 1 ELSE 0 END), 0) AS ethics_count,

                COALESCE(SUM(CASE WHEN (
                    rp.workflow_status IN ('FOR_URRC', 'URRC_REVISED_SUBMITTED')
                    AND NOT $rpsEarlyUrrcAwaitingIntake
                ) THEN 1 ELSE 0 END), 0) AS urrc_count,

                COALESCE(SUM(CASE WHEN rp.workflow_status IN (
                    'FOR_INHOUSE', 'INHOUSE_REVISED_SUBMITTED'
                ) THEN 1 ELSE 0 END), 0) AS inhouse_count,

                COALESCE(SUM(CASE WHEN rp.workflow_status = 'READY_FOR_NTP'
                    THEN 1 ELSE 0 END), 0) AS ntp_count,

                COALESCE(SUM(CASE WHEN rp.workflow_status = 'TERMINAL_REPORT_SUBMITTED'
                    THEN 1 ELSE 0 END), 0) AS permit_count

            FROM research_proposals rp
            WHERE rp.is_archived = 0
        ");

        $rpsQueueCounts = $rpsQueueCountStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $rpsPendingSubmissions = (int)($rpsQueueCounts['submissions_count'] ?? 0);
        $rpsEthicsQueue = (int)($rpsQueueCounts['ethics_count'] ?? 0);
        $rpsUrrcQueue = (int)($rpsQueueCounts['urrc_count'] ?? 0);
        $rpsInhouseQueue = (int)($rpsQueueCounts['inhouse_count'] ?? 0);
        $rpsReadyForNtp = (int)($rpsQueueCounts['ntp_count'] ?? 0);
        $rpsPermitQueue = (int)($rpsQueueCounts['permit_count'] ?? 0);

        $pendingApprovals = $rpsPendingSubmissions + $rpsEthicsQueue
            + $rpsUrrcQueue + $rpsInhouseQueue + $rpsReadyForNtp
            + $rpsPermitQueue;
    }


} catch (Throwable $e) {

    error_log(
        'Dashboard Research Statistics Error: '
        .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| RECENT RESEARCH
|--------------------------------------------------------------------------
*/

$recentResearch = [];


try {

    if (
        $userRole === 'faculty'
    ) {

        $stmt =
            $pdo->prepare("
                SELECT
                    rp.research_id AS id,
                    rp.manuscript_no,
                    rp.research_title AS title,
                    COALESCE(
                        NULLIF(rp.workflow_status, ''),
                        UPPER(REPLACE(rp.status, ' ', '_'))
                    ) AS current_status,
                    rp.workflow_stage AS current_stage,
                    rp.created_at,
                    rp.submitted_at,

                    u.first_name,
                    u.last_name

                FROM research_proposals rp

                LEFT JOIN users u
                    ON u.user_id = rp.researcher_id

                WHERE rp.researcher_id = ?

                ORDER BY
                    COALESCE(
                        rp.workflow_updated_at,
                        rp.submitted_at,
                        rp.created_at
                    ) DESC

                LIMIT 5
            ");

        $stmt->execute([
            $userId
        ]);

        $recentResearch =
            $stmt->fetchAll();


    } elseif (
        in_array(
            $userRole,
            [
                'admin',
                'rps'
            ],
            true
        )
    ) {

        $stmt =
            $pdo->query("
                SELECT
                    rp.research_id AS id,
                    rp.manuscript_no,
                    rp.research_title AS title,
                    COALESCE(
                        NULLIF(rp.workflow_status, ''),
                        UPPER(REPLACE(rp.status, ' ', '_'))
                    ) AS current_status,
                    rp.workflow_stage AS current_stage,
                    rp.created_at,
                    rp.submitted_at,

                    u.first_name,
                    u.last_name

                FROM research_proposals rp

                LEFT JOIN users u
                    ON u.user_id = rp.researcher_id

                WHERE rp.status <> 'Draft'

                ORDER BY
                    COALESCE(
                        rp.workflow_updated_at,
                        rp.submitted_at,
                        rp.created_at
                    ) DESC

                LIMIT 5
            ");

        $recentResearch =
            $stmt->fetchAll();
    }


} catch (Throwable $e) {

    error_log(
        'Dashboard Recent Research Error: '
        .
        $e->getMessage()
    );

    $recentResearch = [];
}


/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
*/

function dashboardResearchStatusLabel(
    string $status
): string {

    $labels = [

        'DRAFT' =>
            'Draft',

        'SUBMITTED' =>
            'Submitted',

        'FOR_ETHICS_REVIEW' =>
            'For UREC/IACUC Review',

        'ETHICS_COMPLETED' =>
            'UREC/IACUC Completed',

        'SELF_INITIATED_UPE_REQUIRED' =>
            'UPE Certificates Required',

        'SELF_INITIATED_UPE_SUBMITTED' =>
            'UPE Certificates Submitted',

        'FOR_URRC' =>
            'For URRC Review',

        'URRC_REVISION_REQUIRED' =>
            'URRC Revision Required',

        'URRC_REVISED_SUBMITTED' =>
            'URRC Revision Submitted',

        'URRC_REJECTED' =>
            'URRC Rejected',

        'FOR_INHOUSE' =>
            'For In-House Review',

        'INHOUSE_REVISION_REQUIRED' =>
            'In-House Revision Required',

        'INHOUSE_REVISED_SUBMITTED' =>
            'In-House Revision Submitted',

        'INHOUSE_FAILED' =>
            'In-House Failed',

        'READY_FOR_NTP' =>
            'Ready for Notice to Proceed',

        'NTP_ISSUED' =>
            'Notice to Proceed Issued',

        'COMPLETED_MANUSCRIPT_SUBMITTED' =>
            'Completed Manuscript Submitted',

        'TERMINAL_REPORT_SUBMITTED' =>
            'Terminal Report Submitted',

        'PERMIT_ISSUED' =>
            'Permit Issued',

        'COMPLETED' =>
            'Completed',

        'ARCHIVED_NO_REVISION' =>
            'Archived'
    ];


    return
        $labels[$status]
        ?? $status;
}


/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/includes/header.php';

require __DIR__ . '/includes/sidebar.php';

?>

<?php

/*
|--------------------------------------------------------------------------
| DASHBOARD UI HELPERS
|--------------------------------------------------------------------------
*/

$dashboardUnreadCount = 0;

try {
    require_once __DIR__ . '/includes/notifications.php';

    if (function_exists('unreadNotificationCount')) {
        $dashboardUnreadCount = unreadNotificationCount($pdo, $userId);
    }
} catch (Throwable $e) {
    $dashboardUnreadCount = 0;
}

$dashboardGreeting = 'Welcome,';

function asuDashboardTimeAgo(?string $dateTime): string
{
    if (!$dateTime) {
        return '';
    }

    $timestamp = strtotime($dateTime);

    if (!$timestamp) {
        return '';
    }

    $seconds = time() - $timestamp;

    if ($seconds < 60) {
        return 'Just now';
    }

    if ($seconds < 3600) {
        return floor($seconds / 60) . ' mins ago';
    }

    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' hours ago';
    }

    if ($seconds < 172800) {
        return '1 day ago';
    }

    return date('M d, Y', $timestamp);
}

function asuDashboardActionLabel(string $status): string
{
    return match (strtoupper($status)) {
        'SUBMITTED' => 'New research proposal submitted',
        'FOR_ETHICS_REVIEW' => 'Research moved to UREC / IACUC',
        'ETHICS_COMPLETED' => 'UREC review completed',
        'FOR_URRC' => 'Research forwarded to URRC',
        'URRC_REVISION_REQUIRED' => 'URRC revision requested',
        'URRC_REVISED_SUBMITTED' => 'URRC revision submitted',
        'FOR_INHOUSE' => 'Research moved to In-House Review',
        'INHOUSE_REVISION_REQUIRED' => 'In-House revision requested',
        'INHOUSE_REVISED_SUBMITTED' => 'In-House revision submitted',
        'READY_FOR_NTP' => 'Research ready for Notice to Proceed',
        'NTP_ISSUED' => 'Research marked as Ongoing',
        'COMPLETED_MANUSCRIPT_SUBMITTED' => 'Completed manuscript submitted',
        'TERMINAL_REPORT_SUBMITTED' => 'Terminal report submitted',
        'PERMIT_ISSUED' => 'Permit issued',
        'COMPLETED' => 'Research completed',
        default => dashboardResearchStatusLabel($status),
    };
}

$dashboardActivities = [];

try {
    if ($userRole === 'faculty') {
        $activityStmt = $pdo->prepare("
            SELECT
                rsh.new_status,
                rsh.remarks,
                rsh.changed_at,
                rp.research_title,
                rp.manuscript_no,
                u.first_name,
                u.last_name
            FROM research_status_history rsh
            INNER JOIN research_proposals rp
                ON rp.research_id = rsh.research_id
            LEFT JOIN users u
                ON u.user_id = rsh.changed_by
            WHERE rp.researcher_id = ?
            ORDER BY rsh.changed_at DESC, rsh.history_id DESC
            LIMIT 5
        ");

        $activityStmt->execute([$userId]);
    } elseif ($userRole === 'rps') {
        $activityStmt = $pdo->query("
            SELECT
                rsh.new_status,
                rsh.remarks,
                rsh.changed_at,
                rp.research_title,
                rp.manuscript_no,
                u.first_name,
                u.last_name
            FROM research_status_history rsh
            INNER JOIN research_proposals rp
                ON rp.research_id = rsh.research_id
            LEFT JOIN users u
                ON u.user_id = rsh.changed_by
            WHERE rp.status <> 'Draft'
            ORDER BY rsh.changed_at DESC, rsh.history_id DESC
            LIMIT 5
        ");
    }

    if (isset($activityStmt)) {
        $dashboardActivities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $dashboardActivities = [];
}

$rpsDashboardTotal =
    (int)$rpsPendingSubmissions
    + (int)$rpsEthicsQueue
    + (int)$rpsUrrcQueue
    + (int)$rpsInhouseQueue
    + (int)$rpsReadyForNtp
    + (int)$rpsPermitQueue;

$rpsStatCards = [
    [
        'label' => 'New Submissions',
        'sub' => 'For initial processing',
        'count' => $rpsPendingSubmissions,
        'icon' => 'bi-file-earmark-plus',
        'tone' => 'green',
        'url' => BASE_URL . '/research/rps_queue.php?queue=submissions',
    ],
    [
        'label' => 'UREC / IACUC',
        'sub' => 'For ethics review',
        'count' => $rpsEthicsQueue,
        'icon' => 'bi-shield-check',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/rps_queue.php?queue=ethics',
    ],
    [
        'label' => 'URRC Review',
        'sub' => 'For technical review',
        'count' => $rpsUrrcQueue,
        'icon' => 'bi-journal-check',
        'tone' => 'green',
        'url' => BASE_URL . '/research/rps_queue.php?queue=urrc',
    ],
    [
        'label' => 'In-House Review',
        'sub' => 'For final review',
        'count' => $rpsInhouseQueue,
        'icon' => 'bi-clipboard-check',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/rps_queue.php?queue=inhouse',
    ],
];

$rpsQueueItems = [
    [
        'label' => 'New Research Submissions',
        'sub' => 'Submitted research waiting for initial processing.',
        'count' => $rpsPendingSubmissions,
        'icon' => 'bi-file-earmark-plus',
        'tone' => 'green',
        'url' => BASE_URL . '/research/rps_queue.php?queue=submissions',
    ],
    [
        'label' => 'URRC Review',
        'sub' => 'Research for technical review.',
        'count' => $rpsUrrcQueue,
        'icon' => 'bi-check2-square',
        'tone' => 'green',
        'url' => BASE_URL . '/research/rps_queue.php?queue=urrc',
    ],
    [
        'label' => 'In-House Review',
        'sub' => 'Research for final review.',
        'count' => $rpsInhouseQueue,
        'icon' => 'bi-folder2-open',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/rps_queue.php?queue=inhouse',
    ],
    [
        'label' => 'UREC / IACUC Review',
        'sub' => 'Research awaiting ethics review results.',
        'count' => $rpsEthicsQueue,
        'icon' => 'bi-shield-check',
        'tone' => 'orange',
        'url' => BASE_URL . '/research/rps_queue.php?queue=ethics',
    ],
    [
        'label' => 'Notice to Proceed',
        'sub' => 'Passed research ready for NTP processing.',
        'count' => $rpsReadyForNtp,
        'icon' => 'bi-file-earmark-play',
        'tone' => 'green',
        'url' => BASE_URL . '/research/rps_queue.php?queue=ntp',
    ],
    [
        'label' => 'Permit Processing',
        'sub' => 'Terminal reports awaiting permit action.',
        'count' => $rpsPermitQueue,
        'icon' => 'bi-file-earmark-check',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/rps_queue.php?queue=permit',
    ],
];

$generalStatCards = [
    [
        'label' => 'Active Research',
        'sub' => 'Ongoing research records',
        'count' => $activeResearch,
        'icon' => 'bi-journal-text',
        'tone' => 'green',
        'url' => BASE_URL . '/research/index.php',
    ],
    [
        'label' => 'Extension Programs',
        'sub' => 'Community programs',
        'count' => $extensionPrograms,
        'icon' => 'bi-megaphone',
        'tone' => 'yellow',
        'url' => BASE_URL . '/extension/index.php',
    ],
    [
        'label' => 'Faculty Researchers',
        'sub' => 'Active faculty users',
        'count' => $facultyResearchers,
        'icon' => 'bi-people',
        'tone' => 'green',
        'url' => BASE_URL . '/admin/users/index.php',
    ],
    [
        'label' => 'Completed Projects',
        'sub' => 'Finished research work',
        'count' => $completedProjects,
        'icon' => 'bi-check-circle',
        'tone' => 'yellow',
        'url' => BASE_URL . '/reports/index.php',
    ],
];

$facultyStatCards = [
    [
        'label' => 'My Proposals',
        'sub' => 'My research records',
        'count' => $facultyTotalProposals,
        'icon' => 'bi-journal-text',
        'tone' => 'green',
        'url' => BASE_URL . '/research/index.php',
    ],
    [
        'label' => 'Under Review',
        'sub' => 'Submitted proposals in review',
        'count' => $facultyUnderReview,
        'icon' => 'bi-hourglass-split',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/index.php?status=Under%20Review',
    ],
    [
        'label' => 'Ongoing Research',
        'sub' => 'Projects in implementation',
        'count' => $facultyOngoing,
        'icon' => 'bi-play-circle',
        'tone' => 'green',
        'url' => BASE_URL . '/research/index.php?status=Ongoing',
    ],
    [
        'label' => 'Completed',
        'sub' => 'Finished research projects',
        'count' => $facultyCompleted ?? 0,
        'icon' => 'bi-check-circle',
        'tone' => 'yellow',
        'url' => BASE_URL . '/research/index.php?status=Completed',
    ],
];

/*
|--------------------------------------------------------------------------
| EXTENSION DASHBOARD — CUMULATIVE, ROLE-SCOPED STATISTICS
|--------------------------------------------------------------------------
| This block runs ONLY for Extensionist/Extension Coordinator and ESO.
| Faculty/Researcher and RPS queries, counters, and cards stay unchanged.
|
| Each card counts distinct proposals, not repeated workflow events:
|  - My Proposals: every proposal created by this proponent (drafts included).
|  - Submitted: proposals that have EVER been submitted.
|  - Revised: proposals with an actual revision submission, even if the
|    proposal later proceeds to approval. Multiple revisions count once.
|  - Approved: proposals that have EVER been approved, including those
|    currently undergoing implementation or marked Completed.
|
| ESO's proposal scope excludes private faculty drafts, as before.
| All historical checks use the existing Extension tables only.
*/
$isExtensionProponentDashboard = in_array(
    $userRole,
    ['extensionist', 'extension_coordinator'],
    true
);
$isEsoDashboard = $userRole === 'extension_service_office';

$extensionDashboardCounts = [
    'total_proposals'     => 0,
    'submitted_proposals' => 0,
    'revised_proposals'   => 0,
    'approved_proposals'  => 0,
];

if ($isExtensionProponentDashboard || $isEsoDashboard) {
    try {
        // Older databases may not yet have every Extension audit table.
        // Check their presence instead of failing the whole dashboard query.
        $extensionAuditTables = $pdo->query("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN (
                  'extension_status_history',
                  'extension_revisions',
                  'extension_certifications'
              )
        ")->fetchAll(PDO::FETCH_COLUMN);

        $hasExtensionHistory = in_array('extension_status_history', $extensionAuditTables, true);
        $hasExtensionRevisions = in_array('extension_revisions', $extensionAuditTables, true);
        $hasExtensionCertifications = in_array('extension_certifications', $extensionAuditTables, true);

        // Submitted is a milestone, not the proposal's CURRENT status.
        // After a proposal reaches UERC / In-House / Approved, it still counts.
        $everSubmitted = [
            "ep.submitted_at IS NOT NULL",
            "ep.status <> 'Draft'",
        ];
        if ($hasExtensionHistory) {
            $everSubmitted[] = "EXISTS (
                SELECT 1 FROM extension_status_history esh
                WHERE esh.extension_id = ep.extension_id
                  AND esh.new_status = 'Submitted'
            )";
        }

        // The actual revision upload/resubmission table is the strongest
        // evidence that a revision was DONE (not merely requested by ESO).
        $everRevised = [];
        if ($hasExtensionRevisions) {
            $everRevised[] = "EXISTS (
                SELECT 1 FROM extension_revisions er
                WHERE er.extension_id = ep.extension_id
            )";
        }
        if ($hasExtensionHistory) {
            // Backward compatibility with proposals revised before the
            // extension_revisions table became part of the workflow.
            $everRevised[] = "EXISTS (
                SELECT 1 FROM extension_status_history esh
                WHERE esh.extension_id = ep.extension_id
                  AND esh.old_status IN (
                      'For UERC Revision', 'For In-House Revision', 'For Revision'
                  )
                  AND esh.new_status NOT IN (
                      'Draft', 'For UERC Revision', 'For In-House Revision',
                      'For Revision', 'Archived', 'Rejected'
                  )
            )";
        }

        // Status history and the certification record preserve approval after
        // the current status changes to implementation / completion.
        $everApproved = [
            "ep.status IN ('Approved', 'Ongoing', 'Completed', 'Scheduled')",
        ];
        if ($hasExtensionHistory) {
            $everApproved[] = "EXISTS (
                SELECT 1 FROM extension_status_history esh
                WHERE esh.extension_id = ep.extension_id
                  AND esh.new_status = 'Approved'
            )";
        }
        if ($hasExtensionCertifications) {
            $everApproved[] = "EXISTS (
                SELECT 1 FROM extension_certifications ec
                WHERE ec.extension_id = ep.extension_id
            )";
        }

        $submittedSql = '(' . implode(' OR ', $everSubmitted) . ')';
        $revisedSql = $everRevised
            ? '(' . implode(' OR ', $everRevised) . ')'
            : 'FALSE';
        $approvedSql = '(' . implode(' OR ', $everApproved) . ')';

        $scopeSql = $isEsoDashboard
            ? "ep.status <> 'Draft'"
            : 'ep.coordinator_id = ?';

        $extensionSummaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_proposals,
                COALESCE(SUM(CASE WHEN {$submittedSql} THEN 1 ELSE 0 END), 0) AS submitted_proposals,
                COALESCE(SUM(CASE WHEN {$revisedSql} THEN 1 ELSE 0 END), 0) AS revised_proposals,
                COALESCE(SUM(CASE WHEN {$approvedSql} THEN 1 ELSE 0 END), 0) AS approved_proposals
            FROM extension_programs ep
            WHERE {$scopeSql}
        ");
        $extensionSummaryStmt->execute($isEsoDashboard ? [] : [$userId]);
        $extensionSummary = $extensionSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($extensionDashboardCounts as $key => $_) {
            $extensionDashboardCounts[$key] = (int)($extensionSummary[$key] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('ASU-REMS Extension Dashboard Cumulative Statistics Error: ' . $e->getMessage());
    }
}

$extensionStatCards = [
    [
        'label' => $isEsoDashboard ? 'Extension Proposals' : 'My Proposals',
        'sub' => $isEsoDashboard ? 'All received extension proposals' : 'All my extension proposals',
        'count' => $extensionDashboardCounts['total_proposals'],
        'icon' => 'bi-clipboard2-check',
        'tone' => 'green',
        'url' => BASE_URL . '/extension/index.php',
    ],
    [
        'label' => 'Submitted Proposals',
        'sub' => 'Total proposals ever submitted',
        'count' => $extensionDashboardCounts['submitted_proposals'],
        'icon' => 'bi-send-check',
        'tone' => 'yellow',
        'url' => BASE_URL . '/extension/index.php',
    ],
    [
        'label' => 'Revised Proposals',
        'sub' => 'Proposals with submitted revisions',
        'count' => $extensionDashboardCounts['revised_proposals'],
        'icon' => 'bi-file-earmark-text',
        'tone' => 'green',
        'url' => BASE_URL . '/extension/index.php',
    ],
    [
        'label' => 'Approved Proposals',
        'sub' => 'Total proposals ever approved',
        'count' => $extensionDashboardCounts['approved_proposals'],
        'icon' => 'bi-check-circle',
        'tone' => 'yellow',
        'url' => BASE_URL . '/extension/index.php',
    ],
];

/* Recent extension proposals: never load research records for Extensionist or ESO.
   An Extensionist sees their own records. ESO sees only submitted records. */
$recentExtensionProposals = [];
if ($isExtensionProponentDashboard || $isEsoDashboard) {
    try {
        // Discover a suitable existing date/ID column; avoid assumptions about migrations.
        $extensionColumns = $pdo->query('SHOW COLUMNS FROM extension_programs')->fetchAll(PDO::FETCH_COLUMN);
        $orderColumn = '';
        foreach (['updated_at', 'workflow_updated_at', 'submitted_at', 'created_at', 'program_id', 'extension_id', 'id'] as $candidate) {
            if (in_array($candidate, $extensionColumns, true)) {
                $orderColumn = $candidate;
                break;
            }
        }
        $orderBy = $orderColumn !== '' ? ' ORDER BY ep.`' . $orderColumn . '` DESC' : '';
        $scope = $isEsoDashboard ? "ep.status <> 'Draft'" : 'ep.coordinator_id = ?';
        $recentStmt = $pdo->prepare('SELECT ep.* FROM extension_programs ep WHERE ' . $scope . $orderBy . ' LIMIT 5');
        $recentStmt->execute($isEsoDashboard ? [] : [$userId]);
        $recentExtensionProposals = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('ASU-REMS Recent Extension Proposals: ' . $e->getMessage());
        $recentExtensionProposals = [];
    }
}

/* Extract a title from whichever title column is present in the existing database. */
function asuExtensionDashboardTitle(array $proposal): string
{
    foreach (['program_title', 'extension_title', 'proposal_title', 'project_title', 'title', 'program_name', 'project_name', 'activity_title'] as $key) {
        if (isset($proposal[$key]) && trim((string)$proposal[$key]) !== '') {
            return (string)$proposal[$key];
        }
    }
    foreach (['program_id', 'extension_id', 'proposal_id', 'id'] as $key) {
        if (!empty($proposal[$key])) {
            return 'Extension Proposal #' . $proposal[$key];
        }
    }
    return 'Extension Proposal';
}

$statCards = $userRole === 'rps'
    ? $rpsStatCards
    : ($userRole === 'faculty'
        ? $facultyStatCards
        : (($isExtensionProponentDashboard || $isEsoDashboard)
            ? $extensionStatCards
            : $generalStatCards));

?>

<style>
:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-orange: #ff6a00;
    --asu-white: #ffffff;
    --asu-black: #101810;
    --asu-muted: #657068;
    --asu-line: rgba(0, 122, 51, .16);
    --asu-soft: #f5faf6;
}

.main-page {
    background: #ffffff;
}

.page-content.dashboard-page {
    padding: 24px 28px 22px;
    background:
        radial-gradient(circle at 16% 2%, rgba(255, 213, 31, .13), transparent 28%),
        radial-gradient(circle at 92% 6%, rgba(0, 122, 51, .10), transparent 30%),
        linear-gradient(180deg, #f8fbf7 0%, #f3f8f4 100%);
}

.asu-dashboard-topbar {
    height: 76px;
    padding: 0 30px;
    border-bottom: 1px solid rgba(0, 122, 51, .12);
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 0 10px 26px rgba(16, 24, 16, .035);
}

.asu-dashboard-brandline {
    display: flex;
    align-items: center;
    gap: 14px;
}

.asu-dashboard-brandline::before {
    content: '';
    width: 3px;
    height: 38px;
    border-radius: 999px;
    background: var(--asu-green);
}

.asu-dashboard-brandline strong {
    display: block;
    color: var(--asu-black);
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -.015em;
}

.asu-dashboard-brandline span {
    display: block;
    margin-top: 2px;
    color: var(--asu-muted);
    font-size: 13px;
}

.asu-dashboard-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}

.dashboard-bell {
    position: relative;
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0, 122, 51, .13);
    border-radius: 14px;
    color: var(--asu-green-dark);
    background: #ffffff;
    text-decoration: none;
}

.dashboard-bell i {
    font-size: 20px;
}

.dashboard-bell-count {
    position: absolute;
    top: -8px;
    right: -8px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border: 2px solid #ffffff;
    border-radius: 999px;
    background: var(--asu-orange);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
}

.dashboard-user-mini {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dashboard-user-copy {
    text-align: right;
}

.dashboard-user-copy strong {
    display: block;
    color: var(--asu-black);
    font-size: 15px;
    line-height: 1.1;
}

.dashboard-user-copy span {
    display: block;
    margin-top: 4px;
    color: var(--asu-muted);
    font-size: 12px;
}

.dashboard-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--asu-yellow);
    color: var(--asu-green-deep);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25);
}

.dashboard-shell {
    max-width: 1320px;
    margin: 0 auto;
}

.dashboard-hero {
    position: relative;
    overflow: hidden;
    min-height: 154px;
    padding: 28px 32px;
    border-radius: 18px;
    background:
        linear-gradient(112deg, rgba(0, 64, 28, .98), rgba(0, 122, 51, .96)),
        radial-gradient(circle at 80% 0%, rgba(255, 213, 31, .25), transparent 26%);
    color: #ffffff;
    box-shadow: 0 18px 42px rgba(0, 90, 40, .18);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
}

.dashboard-hero::before {
    content: '';
    display: none;
}

.dashboard-hero::after {
    content: '';
    position: absolute;
    right: -90px;
    top: -80px;
    width: 420px;
    height: 280px;
    border-radius: 42px;
    transform: rotate(-10deg);
    background: linear-gradient(135deg, rgba(255, 213, 31, .16), rgba(255, 255, 255, .035));
}

.hero-copy,
.hero-mark {
    position: relative;
    z-index: 1;
}

.hero-copy .greeting {
    margin: 0 0 6px;
    font-size: 21px;
    font-weight: 500;
    opacity: .95;
}

.hero-copy h1 {
    margin: 0 0 7px;
    font-size: 34px;
    font-weight: 900;
    letter-spacing: -.035em;
}

.hero-copy .role {
    margin: 0 0 16px;
    font-size: 16px;
    opacity: .96;
}

.hero-copy .tagline {
    margin: 0;
    font-size: 14px;
    opacity: .82;
}

.hero-mark {
    min-width: 330px;
    text-align: right;
}

.hero-mark strong {
    display: block;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .42em;
}

.hero-mark span {
    display: inline-block;
    margin-top: 10px;
    padding-top: 9px;
    border-top: 2px solid var(--asu-yellow);
    color: rgba(255,255,255,.82);
    font-size: 11px;
    letter-spacing: .42em;
}

.dashboard-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.dashboard-stat-card {
    position: relative;
    overflow: hidden;
    min-height: 132px;
    padding: 18px 18px 26px;
    border: 1px solid rgba(0, 122, 51, .14);
    border-radius: 16px;
    background: #ffffff;
    color: var(--asu-black);
    text-decoration: none;
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    align-items: center;
    gap: 14px;
    box-shadow: 0 14px 35px rgba(16, 24, 16, .055);
}

.dashboard-stat-card:hover {
    border-color: rgba(0, 122, 51, .24);
    box-shadow: 0 20px 42px rgba(16, 24, 16, .085);
}

.dashboard-stat-card::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -34px;
    height: 48px;
    border-radius: 50% 50% 0 0;
    opacity: .20;
    pointer-events: none;
}

.dashboard-stat-card.tone-green::after {
    background: var(--asu-green);
}

.dashboard-stat-card.tone-yellow::after {
    background: var(--asu-yellow);
    opacity: .32;
}

.stat-badge-icon {
    position: relative;
    z-index: 1;
    width: 56px;
    height: 56px;
    flex: 0 0 56px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.tone-green .stat-badge-icon {
    background: linear-gradient(135deg, var(--asu-green), var(--asu-green-dark));
    color: #ffffff;
    box-shadow: 0 12px 20px rgba(0, 122, 51, .25);
}

.tone-yellow .stat-badge-icon {
    background: linear-gradient(135deg, var(--asu-yellow), #f0bd00);
    color: var(--asu-green-deep);
    box-shadow: 0 12px 20px rgba(255, 213, 31, .30);
}

.stat-number-wrap {
    position: relative;
    z-index: 2;
    min-width: 0;
    align-self: center;
    padding-bottom: 8px;
}

.stat-number-wrap strong {
    display: block;
    margin-bottom: 5px;
    color: var(--asu-black);
    font-size: clamp(28px, 2.3vw, 34px);
    line-height: .95;
    font-weight: 900;
    letter-spacing: -.04em;
}

.stat-number-wrap span {
    display: block;
    max-width: 100%;
    color: var(--asu-black);
    font-size: clamp(13px, .98vw, 15px);
    line-height: 1.18;
    font-weight: 850;
    overflow-wrap: anywhere;
}

.stat-number-wrap small {
    display: block;
    max-width: 100%;
    margin-top: 5px;
    padding-right: 4px;
    color: #4e5f54;
    font-size: clamp(10.5px, .82vw, 12px);
    line-height: 1.22;
    overflow-wrap: anywhere;
}

.dashboard-two-column {
    display: grid;
    grid-template-columns: minmax(0, 1.18fr) minmax(360px, .82fr);
    gap: 16px;
    margin-top: 18px;
}

.dashboard-panel {
    overflow: hidden;
    border: 1px solid rgba(0, 122, 51, .13);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 14px 35px rgba(16, 24, 16, .055);
}

.dashboard-panel-header {
    padding: 18px 22px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.dashboard-panel-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    color: var(--asu-black);
    font-size: 19px;
    font-weight: 900;
    letter-spacing: -.02em;
}

.panel-icon-soft {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: rgba(0, 122, 51, .09);
    color: var(--asu-green);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.panel-link-modern {
    padding: 9px 13px;
    border: 1px solid rgba(0, 122, 51, .13);
    border-radius: 12px;
    color: var(--asu-green-dark);
    background: #fbfefc;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 800;
}

.panel-link-modern:hover {
    color: var(--asu-green-dark);
    border-color: rgba(0, 122, 51, .24);
    background: rgba(0, 122, 51, .05);
}

.queue-list-modern,
.activity-list-modern {
    padding: 0 22px 14px;
}

.queue-row-modern {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 52px 22px;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-top: 1px solid rgba(0, 122, 51, .10);
    color: var(--asu-black);
    text-decoration: none;
}

.queue-row-modern:first-child,
.activity-row-modern:first-child {
    border-top: 0;
}

.queue-row-left {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    align-items: center;
    gap: 13px;
    min-width: 0;
}

.queue-icon-modern {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.queue-icon-modern.tone-green {
    background: rgba(0, 122, 51, .10);
    color: var(--asu-green);
}

.queue-icon-modern.tone-yellow {
    background: rgba(255, 213, 31, .25);
    color: #9a7500;
}

.queue-icon-modern.tone-orange {
    background: rgba(255, 106, 0, .12);
    color: var(--asu-orange);
}

.queue-copy-modern strong {
    display: block;
    color: var(--asu-black);
    font-size: 14px;
    font-weight: 850;
}

.queue-copy-modern span {
    display: block;
    margin-top: 2px;
    color: var(--asu-muted);
    font-size: 12px;
}

.queue-count-modern {
    width: 42px;
    min-width: 42px;
    height: 34px;
    padding: 0 10px;
    border-radius: 999px;
    background: rgba(0, 122, 51, .10);
    color: var(--asu-green-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    justify-self: end;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.queue-row-modern.has-count .queue-count-modern {
    background: var(--asu-green);
    color: #ffffff;
}

.queue-arrow-modern {
    width: 22px;
    color: var(--asu-black);
    opacity: .75;
    font-size: 18px;
    text-align: right;
}

.activity-row-modern {
    display: grid;
    grid-template-columns: 14px minmax(0, 1fr) auto;
    gap: 12px;
    padding: 13px 0;
    border-top: 1px solid rgba(0, 122, 51, .10);
    align-items: start;
}

.activity-dot-modern {
    width: 12px;
    height: 12px;
    margin-top: 4px;
    border-radius: 50%;
    background: var(--asu-green);
}

.activity-dot-modern.orange {
    background: var(--asu-orange);
}

.activity-dot-modern.yellow {
    background: var(--asu-yellow);
    box-shadow: inset 0 0 0 1px rgba(0,0,0,.07);
}

.activity-copy-modern strong {
    display: block;
    color: var(--asu-black);
    font-size: 14px;
    font-weight: 850;
}

.activity-copy-modern span {
    display: block;
    margin-top: 2px;
    color: var(--asu-muted);
    font-size: 12px;
    line-height: 1.35;
}

.activity-time-modern {
    white-space: nowrap;
    color: var(--asu-muted);
    font-size: 12px;
}

.dashboard-announcement {
    margin-top: 18px;
}

.announcement-body {
    padding: 0 22px 20px;
}

.announcement-card {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 15px 18px;
    border-left: 4px solid var(--asu-orange);
    border-radius: 12px;
    background: linear-gradient(90deg, rgba(255, 106, 0, .13), rgba(255, 213, 31, .13));
}

.announcement-card i {
    color: var(--asu-orange);
    font-size: 21px;
}

.announcement-card strong {
    display: block;
    color: var(--asu-black);
    font-size: 14px;
}

.announcement-card span,
.announcement-date {
    color: var(--asu-muted);
    font-size: 12px;
}

.dashboard-empty-modern {
    padding: 28px 22px 34px;
    border-top: 1px solid rgba(0, 122, 51, .10);
    color: var(--asu-muted);
    text-align: center;
}

.dashboard-empty-modern i {
    display: inline-flex;
    width: 48px;
    height: 48px;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    border-radius: 50%;
    background: rgba(0, 122, 51, .08);
    color: var(--asu-green);
    font-size: 22px;
}

.dashboard-footer-clean {
    padding: 20px 0 0;
    color: rgba(16, 24, 16, .55);
    text-align: center;
    font-size: 12px;
}


/* Dashboard cards and panels display immediately (no entry animation). */

@media (max-width: 1180px) {
    .dashboard-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-two-column {
        grid-template-columns: 1fr;
    }

    .hero-mark {
        display: none;
    }
}

@media (max-width: 760px) {
    .page-content.dashboard-page {
        padding: 18px 14px;
    }

    .asu-dashboard-topbar {
        padding: 0 16px;
    }

    .dashboard-user-copy {
        display: none;
    }

    .dashboard-hero {
        padding: 24px 20px;
    }

    .hero-copy h1 {
        font-size: 27px;
    }

    .dashboard-stat-grid {
        grid-template-columns: 1fr;
    }

    .activity-row-modern {
        grid-template-columns: 14px minmax(0, 1fr);
    }

    .activity-time-modern {
        grid-column: 2;
    }

    .announcement-card {
        grid-template-columns: 36px 1fr;
    }

    .announcement-date {
        grid-column: 2;
    }
}


/* ==========================================================
   STAT CARDS FIT FIX
   Keeps labels inside each card and prevents overlap with wave.
========================================================== */
.dashboard-stat-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: stretch;
}

.dashboard-stat-card {
    min-height: 145px;
    padding: 18px 18px 24px;
    grid-template-columns: 58px minmax(0, 1fr);
    align-items: center;
    gap: 16px;
}

.dashboard-stat-card::after {
    z-index: 0;
    bottom: -34px;
    height: 54px;
    opacity: .20;
}

.dashboard-stat-card.tone-yellow::after {
    opacity: .32;
}

.stat-badge-icon,
.stat-number-wrap {
    position: relative;
    z-index: 2;
}

.stat-number-wrap {
    min-width: 0;
    padding-bottom: 8px;
}

.stat-number-wrap strong {
    margin-bottom: 6px;
    font-size: clamp(28px, 2.2vw, 34px);
    line-height: .95;
}

.stat-number-wrap span {
    max-width: 100%;
    font-size: clamp(13px, 1.05vw, 15px);
    line-height: 1.2;
    font-weight: 850;
    overflow-wrap: break-word;
    word-break: normal;
}

.stat-number-wrap small {
    max-width: 100%;
    margin-top: 6px;
    padding-right: 2px;
    color: #4e5f54;
    font-size: clamp(11px, .9vw, 12px);
    line-height: 1.25;
    overflow-wrap: break-word;
}

@media (max-width: 1280px) {
    .dashboard-stat-card {
        min-height: 138px;
        padding: 16px 15px 22px;
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 14px;
    }

    .stat-badge-icon {
        width: 52px;
        height: 52px;
        flex-basis: 52px;
        font-size: 23px;
        border-radius: 15px;
    }

    .stat-number-wrap span {
        font-size: 13px;
        line-height: 1.18;
    }

    .stat-number-wrap small {
        font-size: 11px;
        line-height: 1.22;
    }
}

@media (max-width: 1100px) {
    .dashboard-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .dashboard-stat-grid {
        grid-template-columns: 1fr;
    }
}



/* ==========================================================
   FINAL ASU-REMS DASHBOARD DESIGN
   Same visual family as the completed Admin interfaces.
   Dashboard keeps a larger hero/banner by design.
========================================================== */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
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

.page-content.dashboard-page {
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

.dashboard-shell {
    max-width: 1320px !important;
    margin: 0 auto !important;
}

/* ----------------------------------------------------------
   TOPBAR
---------------------------------------------------------- */

.asu-dashboard-topbar {
    /* Keep the white dashboard header on-screen as dashboard content scrolls. */
    position: sticky !important;
    top: 0 !important;
    z-index: 1030 !important;
    min-height: 76px !important;
    height: 76px !important;
    padding: 0 30px !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    background: rgba(255,255,255,.97) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
}

.asu-dashboard-brandline {
    gap: 14px !important;
}

.asu-dashboard-brandline::before {
    width: 3px !important;
    height: 38px !important;
    border-radius: 999px !important;
    background: #007a33 !important;
}

.asu-dashboard-brandline strong {
    color: #101810 !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em !important;
}

.asu-dashboard-brandline span {
    margin-top: 4px !important;
    color: #657068 !important;
    font-size: 13px !important;
}

.dashboard-user-copy strong {
    color: #101810 !important;
    font-size: 15px !important;
    line-height: 1.1 !important;
}

.dashboard-user-copy span {
    margin-top: 4px !important;
    color: #657068 !important;
    font-size: 12px !important;
}

.dashboard-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}

/* Show the uploaded picture within the existing round dashboard avatar. */
.dashboard-avatar{overflow:hidden;flex:0 0 46px}
.dashboard-avatar-photo{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.dashboard-avatar-photo[hidden]{display:none}

/* ----------------------------------------------------------
   DASHBOARD HERO — LARGE ONLY FOR DASHBOARD
---------------------------------------------------------- */

.dashboard-hero {
    position: relative !important;
    overflow: hidden !important;
    min-height: 158px !important;
    padding: 28px 32px !important;
    border: 0 !important;
    border-radius: 18px !important;
    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;
    color: #ffffff !important;
    box-shadow: 0 18px 42px rgba(0,90,40,.18) !important;
}

.dashboard-hero::after {
    right: -110px !important;
    top: -120px !important;
    width: 430px !important;
    height: 280px !important;
    border-radius: 42px !important;
    transform: rotate(-9deg) !important;
    background:
        linear-gradient(
            135deg,
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        ) !important;
}

.hero-copy .greeting {
    margin: 0 0 5px !important;
    font-size: 18px !important;
    font-weight: 500 !important;
}

.hero-copy h1 {
    margin: 0 0 6px !important;
    color: #ffffff !important;
    font-size: 34px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.hero-copy .role {
    margin: 0 0 13px !important;
    color: #ffffff !important;
    font-size: 14px !important;
    font-weight: 600 !important;
}

.hero-copy .tagline {
    max-width: 760px !important;
    margin: 0 !important;
    color: rgba(255,255,255,.84) !important;
    font-size: 12px !important;
    line-height: 1.55 !important;
}

.hero-mark {
    min-width: 320px !important;
}

.hero-mark strong {
    color: #ffffff !important;
    font-size: 12px !important;
    font-weight: 650 !important;
    letter-spacing: .34em !important;
}

.hero-mark span {
    margin-top: 9px !important;
    padding-top: 8px !important;
    border-top: 2px solid #ffd51f !important;
    color: rgba(255,255,255,.78) !important;
    font-size: 10px !important;
    letter-spacing: .34em !important;
}

/* ----------------------------------------------------------
   STAT CARDS — SAME ADMIN CARD FAMILY
---------------------------------------------------------- */

.dashboard-stat-grid {
    display: grid !important;
    grid-template-columns: repeat(4,minmax(0,1fr)) !important;
    gap: 14px !important;
    margin-top: 18px !important;
    align-items: stretch !important;
}

.dashboard-stat-card {
    position: relative !important;
    overflow: hidden !important;
    min-width: 0 !important;
    min-height: 132px !important;
    height: 132px !important;
    padding: 16px 18px 20px !important;
    grid-template-columns: 52px minmax(0,1fr) !important;
    align-items: center !important;
    gap: 15px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.055) !important;
}

.dashboard-stat-card:hover {
    border-color: rgba(0,122,51,.24) !important;
    box-shadow: 0 18px 38px rgba(16,24,16,.08) !important;
}

.dashboard-stat-card::after {
    content: "" !important;
    position: absolute !important;
    left: -5% !important;
    right: -5% !important;
    width: auto !important;
    top: auto !important;
    bottom: -36px !important;
    height: 54px !important;
    border-radius: 50% 50% 0 0 !important;
    transform: none !important;
    pointer-events: none !important;
    z-index: 0 !important;
}

.dashboard-stat-card.tone-green::after {
    background: #007a33 !important;
    opacity: .22 !important;
}

.dashboard-stat-card.tone-yellow::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}

.stat-badge-icon {
    position: relative !important;
    z-index: 2 !important;
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    border-radius: 15px !important;
    font-size: 22px !important;
}

.tone-green .stat-badge-icon {
    background:
        linear-gradient(135deg,#007a33,#005c26) !important;
    color: #ffffff !important;
    box-shadow: 0 10px 18px rgba(0,122,51,.20) !important;
}

.tone-yellow .stat-badge-icon {
    background:
        linear-gradient(135deg,#ffd51f,#f0bd00) !important;
    color: #003f1a !important;
    box-shadow: 0 10px 18px rgba(255,213,31,.25) !important;
}

.stat-number-wrap {
    position: relative !important;
    z-index: 2 !important;
    min-width: 0 !important;
    padding-bottom: 2px !important;
}

.stat-number-wrap strong {
    margin: 0 0 6px !important;
    color: #101810 !important;
    font-size: 28px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.stat-number-wrap span {
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

.stat-number-wrap small {
    margin-top: 4px !important;
    color: #657068 !important;
    font-size: 9.5px !important;
    line-height: 1.25 !important;
    font-weight: 500 !important;
}

/* ----------------------------------------------------------
   CONTENT PANELS
---------------------------------------------------------- */

.dashboard-two-column {
    display: grid !important;
    grid-template-columns: minmax(0,1.18fr) minmax(360px,.82fr) !important;
    gap: 16px !important;
    margin-top: 18px !important;
}

.dashboard-panel {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.055) !important;
}

.dashboard-panel-header {
    min-height: 64px !important;
    padding: 14px 18px !important;
    border-bottom: 1px solid rgba(0,122,51,.08) !important;
    background: rgba(255,255,255,.82) !important;
}

.dashboard-panel-title {
    gap: 10px !important;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    letter-spacing: 0 !important;
}

.panel-icon-soft {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: #007a33 !important;
    font-size: 16px !important;
}

.panel-link-modern {
    min-height: 36px !important;
    padding: 0 11px !important;
    border: 1px solid rgba(0,122,51,.18) !important;
    border-radius: 9px !important;
    background: #ffffff !important;
    color: #007a33 !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}

.panel-link-modern:hover {
    border-color: rgba(0,122,51,.30) !important;
    background: #f2f8f3 !important;
    color: #005c26 !important;
}

.queue-list-modern,
.activity-list-modern {
    padding: 0 18px 14px !important;
}

.queue-row-modern {
    grid-template-columns: minmax(0,1fr) 46px 20px !important;
    gap: 10px !important;
    padding: 12px 0 !important;
    border-top: 1px solid rgba(0,122,51,.08) !important;
}

.queue-row-left {
    grid-template-columns: 40px minmax(0,1fr) !important;
    gap: 11px !important;
}

.queue-icon-modern {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    border-radius: 11px !important;
    font-size: 17px !important;
}

.queue-icon-modern.tone-green {
    background: #edf7ef !important;
    color: #007a33 !important;
}

.queue-icon-modern.tone-yellow {
    background: #fff7d8 !important;
    color: #8a6700 !important;
}

.queue-icon-modern.tone-orange {
    background: #fff0e5 !important;
    color: #d56800 !important;
}

.queue-copy-modern strong {
    color: #1f2b22 !important;
    font-size: 11px !important;
    line-height: 1.35 !important;
    font-weight: 800 !important;
}

.queue-copy-modern span {
    margin-top: 3px !important;
    color: #657068 !important;
    font-size: 9px !important;
    line-height: 1.35 !important;
}

.queue-count-modern {
    width: 38px !important;
    min-width: 38px !important;
    height: 30px !important;
    padding: 0 8px !important;
    background: #edf7ef !important;
    color: #005c26 !important;
    font-size: 10px !important;
}

.queue-row-modern.has-count .queue-count-modern {
    background: #007a33 !important;
    color: #ffffff !important;
}

.queue-arrow-modern {
    width: 20px !important;
    color: #59645c !important;
    font-size: 15px !important;
}

.activity-row-modern {
    grid-template-columns: 10px minmax(0,1fr) auto !important;
    gap: 10px !important;
    padding: 12px 0 !important;
    border-top: 1px solid rgba(0,122,51,.08) !important;
}

.activity-dot-modern {
    width: 9px !important;
    height: 9px !important;
    margin-top: 4px !important;
}

.activity-copy-modern strong {
    color: #1f2b22 !important;
    font-size: 11px !important;
    line-height: 1.35 !important;
    font-weight: 800 !important;
}

.activity-copy-modern span {
    margin-top: 3px !important;
    color: #657068 !important;
    font-size: 9px !important;
    line-height: 1.35 !important;
}

.activity-time-modern {
    color: #7d8780 !important;
    font-size: 9px !important;
}

/* ----------------------------------------------------------
   ANNOUNCEMENTS
---------------------------------------------------------- */

.dashboard-announcement {
    margin-top: 18px !important;
}

.announcement-body {
    padding: 14px 18px 18px !important;
}

.announcement-card {
    grid-template-columns: 38px minmax(0,1fr) auto !important;
    gap: 12px !important;
    padding: 13px 15px !important;
    border-left: 4px solid #f28c28 !important;
    border-radius: 11px !important;
    background:
        linear-gradient(
            90deg,
            rgba(242,140,40,.10),
            rgba(255,213,31,.10)
        ) !important;
}

.announcement-card i {
    color: #d56800 !important;
    font-size: 19px !important;
}

.announcement-card strong {
    color: #1f2b22 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
}

.announcement-card span,
.announcement-date {
    color: #657068 !important;
    font-size: 9px !important;
    line-height: 1.4 !important;
}

/* Shared footer only. */
.dashboard-footer-clean {
    display: none !important;
}

/* ----------------------------------------------------------
   RESPONSIVE
---------------------------------------------------------- */

@media (max-width: 1180px) {
    .dashboard-stat-grid {
        grid-template-columns: repeat(2,minmax(0,1fr)) !important;
    }

    .dashboard-two-column {
        grid-template-columns: 1fr !important;
    }

    .hero-mark {
        display: none !important;
    }
}

@media (max-width: 760px) {
    .page-content.dashboard-page {
        padding: 18px 14px 24px !important;
    }

    .asu-dashboard-topbar {
        padding: 0 16px !important;
    }

    .dashboard-user-copy {
        display: none !important;
    }

    .dashboard-hero {
        min-height: 138px !important;
        padding: 24px 20px !important;
    }

    .hero-copy h1 {
        font-size: 27px !important;
    }

    .hero-copy .greeting {
        font-size: 16px !important;
    }

    .hero-copy .role {
        font-size: 13px !important;
    }

    .dashboard-stat-grid {
        grid-template-columns: 1fr !important;
    }

    .dashboard-stat-card {
        height: auto !important;
        min-height: 126px !important;
    }

    .activity-row-modern {
        grid-template-columns: 10px minmax(0,1fr) !important;
    }

    .activity-time-modern {
        grid-column: 2 !important;
    }

    .announcement-card {
        grid-template-columns: 34px 1fr !important;
    }

    .announcement-date {
        grid-column: 2 !important;
    }
}

/* ==========================================================
   DASHBOARD MOBILE SIDEBAR CONTROL
   Show the shared sidebar menu trigger on tablets/phones.
   Desktop header and research workflows are unchanged.
========================================================== */
.asu-dashboard-topbar .dashboard-mobile-menu-toggle {
    display: none !important;
}

@media (max-width: 991.98px) {
    .asu-dashboard-topbar .asu-dashboard-brandline {
        min-width: 0;
        flex: 1 1 auto;
        gap: 9px !important;
    }

    .asu-dashboard-topbar .asu-dashboard-brandline > div {
        min-width: 0;
    }

    .asu-dashboard-topbar .dashboard-mobile-menu-toggle {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        flex: 0 0 36px;
        width: 36px;
        height: 40px;
        padding: 0;
        border: 1px solid rgba(0, 122, 51, .16);
        border-radius: 10px;
        background: #f5faf6;
        color: #006b2e;
        font-size: 25px;
        cursor: pointer;
    }

    .asu-dashboard-topbar .dashboard-mobile-menu-toggle:focus-visible {
        outline: 2px solid #007a33;
        outline-offset: 2px;
    }
}

@media (max-width: 480px) {
    .asu-dashboard-topbar {
        gap: 10px !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .asu-dashboard-topbar .asu-dashboard-brandline::before {
        flex: 0 0 3px;
    }

    .asu-dashboard-topbar .asu-dashboard-brandline strong {
        font-size: 12.5px !important;
        line-height: 1.2;
    }

    .asu-dashboard-topbar .asu-dashboard-brandline span {
        font-size: 10px !important;
    }

    .asu-dashboard-topbar .dashboard-avatar {
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}


/* Recent Submissions empty state: centered vertically with compact typography. */
.dashboard-page .dashboard-two-column > .dashboard-panel:first-child {
    display: flex;
    flex-direction: column;
}
.dashboard-page .dashboard-two-column > .dashboard-panel:first-child > .dashboard-panel-header {
    flex: 0 0 auto;
}
.dashboard-page .dashboard-submissions-empty {
    flex: 1 1 auto;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 20px 18px 26px;
    text-align: center;
}
.dashboard-page .dashboard-submissions-empty i {
    width: 48px;
    height: 48px;
    margin: 0;
    font-size: 21px;
}
.dashboard-page .dashboard-submissions-empty p {
    margin: 0 !important;
    font-size: 12px !important;
    line-height: 1.5 !important;
    font-weight: 500 !important;
    color: #657068 !important;
}

/* Recent Submissions: keep the chevron at the far right edge. */
.dashboard-page .queue-row-modern.dashboard-submission-row {
    grid-template-columns: minmax(0, 1fr) 20px !important;
}
.dashboard-page .queue-row-modern.dashboard-submission-row .queue-arrow-modern {
    justify-self: end;
    text-align: right;
}
/* ==========================================================
   DASHBOARD — INSTANT NAVIGATION AND STATIC CONTENT
   No fade-in, delayed card entries, sliding, or hover movement.
   Preserves design, hover colors, sidebar layout, and PHP logic.
========================================================== */
.dashboard-main-page,
.dashboard-main-page *,
.dashboard-main-page *::before,
.dashboard-main-page *::after {
    animation: none !important;
    animation-delay: 0s !important;
    transition: none !important;
    scroll-behavior: auto !important;
}

.dashboard-main-page .dashboard-stat-card:hover,
.dashboard-main-page .queue-row-modern:hover,
.dashboard-main-page .activity-row-modern:hover,
.dashboard-main-page .panel-link-modern:hover {
    transform: none !important;
}

/* Dashboard dark mode: overrides the existing fixed white/black !important values.
   Normal light appearance, hero identity, responsive layout, and data stay unchanged. */
html[data-rems-theme="dark"] .dashboard-main-page,
html[data-rems-theme="dark"] .dashboard-main-page .page-content.dashboard-page {
    background:linear-gradient(135deg,#071c13 0%,#102a1e 55%,#1a3022 100%)!important;
    color:#eaf4ec!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .asu-dashboard-topbar {
    background:#11291e!important;border-bottom-color:#365844!important;
    box-shadow:0 10px 25px rgba(0,0,0,.11)!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .asu-dashboard-brandline strong,
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-user-copy strong {
    color:#f0f7f0!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .asu-dashboard-brandline span,
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-user-copy span {
    color:#bbcebf!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-hero {
    background:linear-gradient(112deg,#003619 0%,#005d2d 64%,#0a773a 100%)!important;
    box-shadow:0 16px 34px rgba(0,0,0,.19)!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-stat-card,
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-panel {
    background:#172f23!important;color:#edf5ef!important;
    border-color:#385c45!important;
    box-shadow:0 12px 27px rgba(0,0,0,.15)!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-stat-card:hover {
    border-color:#66876e!important;
    box-shadow:0 12px 27px rgba(0,0,0,.15)!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-panel-header {
    background:#1a3929!important;border-bottom-color:#3a5c46!important;
}
html[data-rems-theme="dark"] .dashboard-main-page :is(
    .stat-number-wrap strong,.stat-number-wrap span,.dashboard-panel-title,
    .queue-copy-modern strong,.activity-copy-modern strong,
    .announcement-card strong,.queue-arrow-modern
) {color:#edf5ed!important;}
html[data-rems-theme="dark"] .dashboard-main-page :is(
    .stat-number-wrap small,.queue-copy-modern span,.activity-copy-modern span,
    .activity-time-modern,.announcement-card span,.announcement-date,
    .dashboard-empty-modern,.dashboard-submissions-empty p
) {color:#bdcfc0!important;}
html[data-rems-theme="dark"] .dashboard-main-page .panel-icon-soft,
html[data-rems-theme="dark"] .dashboard-main-page .queue-icon-modern.tone-green {
    background:#244a33!important;color:#a3eac2!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .queue-icon-modern.tone-yellow {
    background:#514422!important;color:#ffe08a!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .queue-icon-modern.tone-orange {
    background:#553422!important;color:#ffbd87!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .panel-link-modern {
    background:#214532!important;color:#d4efdc!important;
    border-color:#52795c!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .panel-link-modern:hover {
    background:#2b573d!important;color:#fff!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .queue-count-modern {
    background:#294b37!important;color:#eaf8ec!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .queue-row-modern.has-count .queue-count-modern {
    background:#007a33!important;color:#fff!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .queue-row-modern,
html[data-rems-theme="dark"] .dashboard-main-page .activity-row-modern,
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-empty-modern {
    border-top-color:#345744!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .announcement-card {
    background:linear-gradient(90deg,rgba(222,132,49,.17),rgba(241,201,74,.08))!important;
}
html[data-rems-theme="dark"] .dashboard-main-page .dashboard-mobile-menu-toggle {
    background:#1b3a29!important;color:#e4f6e6!important;border-color:#416e51!important;
}
/* Leave the gold counters/icons and white hero copy as meaningful accents. */

</style>

<?php if ($userRole === 'rps'): ?>
<style>
/* ==========================================================
   RPS DASHBOARD — STEADY SCROLL ONLY
   Keep normal scrolling, but stop the whole page from
   pulling/bouncing when already at the top or bottom.
   No layout/content sizing is changed.
========================================================== */

html,
body {
    overscroll-behavior-y: none !important;
    overscroll-behavior-x: none !important;
}

.page-content.dashboard-page {
    overscroll-behavior-y: contain !important;
}
</style>
<?php endif; ?>


<div class="main-page dashboard-main-page">

    <header class="asu-dashboard-topbar">
        <div class="asu-dashboard-brandline">
            <button
                type="button"
                id="sidebarToggle"
                class="sidebar-toggle dashboard-mobile-menu-toggle"
                aria-label="Open navigation menu"
                title="Open navigation menu"
            >
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div>
                <strong>Research &amp; Extension Management System</strong>
                <span>Aklan State University</span>
            </div>
        </div>

        <div class="asu-dashboard-actions">

            <button type="button" class="rems-theme-toggle dashboard-theme-toggle"
                    data-rems-theme-toggle aria-label="Switch to dark mode"
                    title="Switch to dark mode" aria-pressed="false">
                <i class="bi bi-moon-fill" aria-hidden="true"></i>
            </button>

            <div class="dashboard-user-mini">
                <div class="dashboard-user-copy">
                    <strong><?= e(fullName($user)); ?></strong>
                    <span><?= e($displayRole); ?></span>
                </div>
                <?= remsProfileAvatarHtml($pdo, $user, $initials ?: 'U', 'dashboard-avatar'); ?>
            </div>
        </div>
    </header>

    <main class="page-content dashboard-page faculty-dashboard-page">
        <div class="dashboard-shell">

            <section class="dashboard-hero">
                <div class="hero-copy">
                    <p class="greeting"><?= e($dashboardGreeting); ?></p>
                    <h1><?= e($firstName); ?>!</h1>
                    <p class="role"><?= e($displayRole); ?></p>
                    <p class="tagline">
                        <?php if ($userRole === 'rps'): ?>
                            Together for a stronger research culture.
                        <?php elseif ($userRole === 'faculty'): ?>
                            Track your research submissions, progress updates, and review results in one place.
                        <?php elseif ($isExtensionProponentDashboard): ?>
                            Track your extension proposals, service requests, and program updates in one place.
                        <?php elseif ($isEsoDashboard): ?>
                            Manage extension proposals, service requests, and program monitoring in one place.
                        <?php else: ?>
                            Manage research and extension records with clarity, security, and accountability.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="hero-mark">
                    <strong>AKLAN STATE UNIVERSITY</strong>
                    <span>TRUTH · SERVICE · EXCELLENCE</span>
                </div>
            </section>

            <section class="dashboard-stat-grid">
                <?php foreach ($statCards as $card): ?>
                    <a href="<?= e($card['url']); ?>" class="dashboard-stat-card tone-<?= e($card['tone']); ?>">
                        <div class="stat-badge-icon">
                            <i class="bi <?= e($card['icon']); ?>"></i>
                        </div>
                        <div class="stat-number-wrap">
                            <strong><?= number_format((int)$card['count']); ?></strong>
                            <span><?= e($card['label']); ?></span>
                            <small><?= e($card['sub']); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>

            <?php if ($userRole === 'rps'): ?>

                <section class="dashboard-two-column">
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-list-task"></i></span>
                                Processing Queues
                            </h2>
                            <a href="<?= BASE_URL; ?>/dashboard.php?view=processing" class="panel-link-modern">
                                View All
                            </a>
                        </div>

                        <div class="queue-list-modern">
                            <?php foreach ($rpsQueueItems as $item): ?>
                                <a
                                    href="<?= e($item['url']); ?>"
                                    class="queue-row-modern <?= ((int)$item['count'] > 0) ? 'has-count' : ''; ?>"
                                >
                                    <div class="queue-row-left">
                                        <span class="queue-icon-modern tone-<?= e($item['tone']); ?>">
                                            <i class="bi <?= e($item['icon']); ?>"></i>
                                        </span>
                                        <span class="queue-copy-modern">
                                            <strong><?= e($item['label']); ?></strong>
                                            <span><?= e($item['sub']); ?></span>
                                        </span>
                                    </div>

                                    <span class="queue-count-modern"><?= number_format((int)$item['count']); ?></span>
                                    <span class="queue-arrow-modern"><i class="bi bi-chevron-right"></i></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-clock-history"></i></span>
                                Recent Activity
                            </h2>
                            <a href="<?= BASE_URL; ?>/notifications/index.php" class="panel-link-modern">
                                View All
                            </a>
                        </div>

                        <?php if (!empty($dashboardActivities)): ?>
                            <div class="activity-list-modern">
                                <?php foreach ($dashboardActivities as $activity): ?>
                                    <?php
                                        $activityStatus = strtoupper((string)($activity['new_status'] ?? ''));
                                        $dotClass = str_contains($activityStatus, 'ETHICS') || str_contains($activityStatus, 'INHOUSE')
                                            ? 'yellow'
                                            : (str_contains($activityStatus, 'REVISION') || str_contains($activityStatus, 'REJECTED') || str_contains($activityStatus, 'FAILED') ? 'orange' : '');

                                        $activityBy = trim(
                                            (string)($activity['first_name'] ?? '') . ' ' .
                                            (string)($activity['last_name'] ?? '')
                                        );
                                    ?>
                                    <div class="activity-row-modern">
                                        <span class="activity-dot-modern <?= e($dotClass); ?>"></span>
                                        <span class="activity-copy-modern">
                                            <strong><?= e(asuDashboardActionLabel($activityStatus)); ?></strong>
                                            <span>
                                                <?= e($activityBy ?: 'System'); ?>
                                                ·
                                                <?= e($activity['manuscript_no'] ?: 'No manuscript number'); ?>
                                            </span>
                                        </span>
                                        <span class="activity-time-modern">
                                            <?= e(asuDashboardTimeAgo($activity['changed_at'] ?? null)); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-empty-modern">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No recent workflow activity yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php elseif ($isExtensionProponentDashboard || $isEsoDashboard): ?>

                <!-- Extension-only dashboard panels: no research records or research links. -->
                <section class="dashboard-two-column">
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-file-earmark-text"></i></span>
                                Recent Extension Proposals
                            </h2>
                            <a href="<?= BASE_URL; ?>/extension/index.php" class="panel-link-modern">
                                View Extension
                            </a>
                        </div>
                        <?php if (!empty($recentExtensionProposals)): ?>
                            <div class="queue-list-modern">
                                <?php foreach ($recentExtensionProposals as $proposal): ?>
                                    <div class="queue-row-modern dashboard-submission-row">
                                        <div class="queue-row-left">
                                            <span class="queue-icon-modern tone-green">
                                                <i class="bi bi-clipboard2-check"></i>
                                            </span>
                                            <span class="queue-copy-modern">
                                                <strong><?= e(asuExtensionDashboardTitle($proposal)); ?></strong>
                                                <span><?= e((string)($proposal['status'] ?? 'Pending')); ?></span>
                                            </span>
                                        </div>
                                        <span class="queue-arrow-modern"><i class="bi bi-file-earmark-text"></i></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-empty-modern dashboard-submissions-empty">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No extension proposals yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-grid"></i></span>
                                Extension Workspace
                            </h2>
                        </div>
                        <div class="queue-list-modern">
                            <a href="<?= BASE_URL; ?>/extension/index.php" class="queue-row-modern dashboard-submission-row">
                                <span class="queue-row-left">
                                    <span class="queue-icon-modern tone-green"><i class="bi bi-file-earmark-check"></i></span>
                                    <span class="queue-copy-modern">
                                        <strong><?= $isEsoDashboard ? 'Proposal Processing' : 'My Extension Proposals'; ?></strong>
                                        <span><?= $isEsoDashboard ? 'Review and process extension proposals.' : 'View and manage your submitted proposals.'; ?></span>
                                    </span>
                                </span>
                                <span class="queue-arrow-modern"><i class="bi bi-chevron-right"></i></span>
                            </a>
                            <a href="<?= BASE_URL; ?>/extension/services.php" class="queue-row-modern dashboard-submission-row">
                                <span class="queue-row-left">
                                    <span class="queue-icon-modern tone-yellow"><i class="bi bi-tools"></i></span>
                                    <span class="queue-copy-modern">
                                        <strong>Training &amp; Technical Services</strong>
                                        <span><?= $isEsoDashboard ? 'Manage service requests and schedules.' : 'Request and track extension services.'; ?></span>
                                    </span>
                                </span>
                                <span class="queue-arrow-modern"><i class="bi bi-chevron-right"></i></span>
                            </a>
                            <a href="<?= BASE_URL; ?>/extension/monitoring.php" class="queue-row-modern dashboard-submission-row">
                                <span class="queue-row-left">
                                    <span class="queue-icon-modern tone-green"><i class="bi bi-clipboard-data"></i></span>
                                    <span class="queue-copy-modern">
                                        <strong>Monitoring &amp; Evaluation</strong>
                                        <span>Check extension program progress and updates.</span>
                                    </span>
                                </span>
                                <span class="queue-arrow-modern"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </div>
                    </div>
                </section>

            <?php else: ?>

                <section class="dashboard-two-column">
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-file-earmark-text"></i></span>
                                Recent Submissions
                            </h2>
                            <a href="<?= BASE_URL; ?>/research/index.php" class="panel-link-modern">
                                View Research
                            </a>
                        </div>

                        <?php if (!empty($recentResearch)): ?>
                            <div class="queue-list-modern">
                                <?php foreach ($recentResearch as $item): ?>
                                    <a href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$item['id']; ?>" class="queue-row-modern dashboard-submission-row">
                                        <div class="queue-row-left">
                                            <span class="queue-icon-modern tone-green">
                                                <i class="bi bi-journal-text"></i>
                                            </span>
                                            <span class="queue-copy-modern">
                                                <strong><?= e($item['title']); ?></strong>
                                                <span>
                                                    <?= e($item['manuscript_no'] ?: 'Draft'); ?>
                                                    ·
                                                    <?= e(dashboardResearchStatusLabel($item['current_status'])); ?>
                                                </span>
                                            </span>
                                        </div>
                                        <span class="queue-arrow-modern"><i class="bi bi-chevron-right"></i></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-empty-modern dashboard-submissions-empty">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No submissions yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <h2 class="dashboard-panel-title">
                                <span class="panel-icon-soft"><i class="bi bi-clock-history"></i></span>
                                Recent Activity
                            </h2>
                            <a href="<?= BASE_URL; ?>/notifications/index.php" class="panel-link-modern">
                                View All
                            </a>
                        </div>

                        <?php if (!empty($dashboardActivities)): ?>
                            <div class="activity-list-modern">
                                <?php foreach ($dashboardActivities as $activity): ?>
                                    <div class="activity-row-modern">
                                        <span class="activity-dot-modern"></span>
                                        <span class="activity-copy-modern">
                                            <strong><?= e(asuDashboardActionLabel((string)($activity['new_status'] ?? ''))); ?></strong>
                                            <span><?= e($activity['manuscript_no'] ?: 'No manuscript number'); ?></span>
                                        </span>
                                        <span class="activity-time-modern">
                                            <?= e(asuDashboardTimeAgo($activity['changed_at'] ?? null)); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-empty-modern">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No recent activity yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php endif; ?>

            <section class="dashboard-panel dashboard-announcement">
                <div class="dashboard-panel-header">
                    <h2 class="dashboard-panel-title">
                        <span class="panel-icon-soft"><i class="bi bi-megaphone"></i></span>
                        Announcements
                    </h2>
                    <a href="<?= BASE_URL; ?>/notifications/index.php" class="panel-link-modern">
                        View All
                    </a>
                </div>

                <div class="announcement-body">
                    <div class="announcement-card">
                        <i class="bi bi-bell-fill"></i>
                        <div>
                            <?php if ($isExtensionProponentDashboard || $isEsoDashboard): ?>
                                <strong>Extension Proposal Updates</strong>
                                <span>Extension proposal and service updates will appear in ASU-REMS notifications.</span>
                            <?php else: ?>
                                <strong>Call for Research Proposals</strong>
                                <span>Submission and processing updates will appear in ASU-REMS notifications.</span>
                            <?php endif; ?>
                        </div>
                        <span class="announcement-date"><?= date('M d, Y'); ?></span>
                    </div>
                </div>
            </section>
</div>
    </main>
</div>

<?php
require __DIR__ . '/includes/footer.php';
?>
