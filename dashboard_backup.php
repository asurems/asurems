<?php

require_once __DIR__ . '/includes/auth.php';

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
    |--------------------------------------------------------------------------
    | FACULTY
    |--------------------------------------------------------------------------
    */

    if (
        $userRole === 'faculty'
    ) {

        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_projects

                WHERE researcher_id = ?

                  AND is_archived = 0

                  AND current_status
                      <> 'COMPLETED'
            ");

        $stmt->execute([
            $userId
        ]);

        $activeResearch =
            (int)$stmt
                ->fetchColumn();


        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_projects

                WHERE researcher_id = ?

                  AND current_status =
                      'COMPLETED'
            ");

        $stmt->execute([
            $userId
        ]);

        $completedProjects =
            (int)$stmt
                ->fetchColumn();


        $stmt =
            $pdo->prepare("
                SELECT COUNT(*)

                FROM research_projects

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
            (int)$stmt
                ->fetchColumn();
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN / RPS
    |--------------------------------------------------------------------------
    */

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

                    FROM research_projects

                    WHERE is_archived = 0

                      AND current_status
                          <> 'COMPLETED'
                ")
                ->fetchColumn();


        $completedProjects =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status =
                        'COMPLETED'
                ")
                ->fetchColumn();


        $proposalsThisMonth =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE submitted_at >=
                        DATE_FORMAT(
                            CURRENT_DATE,
                            '%Y-%m-01'
                        )
                ")
                ->fetchColumn();
    }


    /*
    |--------------------------------------------------------------------------
    | RPS QUEUES
    |--------------------------------------------------------------------------
    */

    if (
        $userRole === 'rps'
    ) {

        /*
         * New Submissions
         */

        $rpsPendingSubmissions =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status =
                        'SUBMITTED'

                      AND is_archived = 0
                ")
                ->fetchColumn();


        /*
         * UREC / IACUC
         */

        $rpsEthicsQueue =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status =
                        'FOR_ETHICS_REVIEW'

                      AND is_archived = 0
                ")
                ->fetchColumn();


        /*
         * URRC
         */

        $rpsUrrcQueue =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status IN
                    (
                        'FOR_URRC',
                        'URRC_REVISED_SUBMITTED'
                    )

                    AND is_archived = 0
                ")
                ->fetchColumn();


        /*
         * In-House
         */

        $rpsInhouseQueue =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status IN
                    (
                        'FOR_INHOUSE',
                        'INHOUSE_REVISED_SUBMITTED'
                    )

                    AND is_archived = 0
                ")
                ->fetchColumn();


        /*
         * Ready for NTP
         */

        $rpsReadyForNtp =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status =
                        'READY_FOR_NTP'

                      AND is_archived = 0
                ")
                ->fetchColumn();


        /*
         * Permit Processing
         */

        $rpsPermitQueue =
            (int)$pdo
                ->query("
                    SELECT COUNT(*)

                    FROM research_projects

                    WHERE current_status =
                        'TERMINAL_REPORT_SUBMITTED'

                      AND is_archived = 0
                ")
                ->fetchColumn();


        $pendingApprovals =
            $rpsPendingSubmissions
            +
            $rpsEthicsQueue
            +
            $rpsUrrcQueue
            +
            $rpsInhouseQueue
            +
            $rpsReadyForNtp
            +
            $rpsPermitQueue;
    }


} catch (Throwable $e) {

    error_log(
        'Dashboard Statistics Error: '
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
                    rp.id,
                    rp.manuscript_no,
                    rp.title,
                    rp.current_status,
                    rp.current_stage,
                    rp.created_at,
                    rp.submitted_at,

                    u.first_name,
                    u.last_name

                FROM research_projects rp

                LEFT JOIN users u
                    ON u.user_id =
                        rp.researcher_id

                WHERE rp.researcher_id = ?

                ORDER BY

                    COALESCE(
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
                    rp.id,
                    rp.manuscript_no,
                    rp.title,
                    rp.current_status,
                    rp.current_stage,
                    rp.created_at,
                    rp.submitted_at,

                    u.first_name,
                    u.last_name

                FROM research_projects rp

                LEFT JOIN users u
                    ON u.user_id =
                        rp.researcher_id

                ORDER BY

                    COALESCE(
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


<style>

/*
|--------------------------------------------------------------------------
| PROCESSING VIEW
|--------------------------------------------------------------------------
*/

.processing-page-heading {

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;
}


.processing-page-heading h1 {

    margin: 0 0 6px;

    color: #1f2c22;

    font-size: 25px;
    font-weight: 800;
}


.processing-page-heading p {

    margin: 0;

    color: #788079;

    font-size: 13px;
}


.processing-total-badge {

    display: inline-flex;
    align-items: center;

    gap: 8px;

    padding: 10px 15px;

    border-radius: 10px;

    background: #eef7f0;

    color: #08752c;

    font-size: 12px;
    font-weight: 700;
}


.processing-section {
    margin-top: 24px;
}


.processing-queue-list {
    padding: 5px 0;
}


.processing-queue-item {

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 16px 20px;

    border-bottom:
        1px solid #edf1ed;
}


.processing-queue-item:last-child {
    border-bottom: 0;
}


.processing-queue-left {

    display: flex;
    align-items: center;

    gap: 13px;
}


.processing-queue-icon {

    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex: 0 0 38px;

    border-radius: 10px;

    background: #eef7f0;

    color: #08752c;

    font-size: 16px;
}


.processing-queue-icon.yellow {

    background: #fff6cf;

    color: #a17c00;
}


.processing-queue-text strong {

    display: block;

    color: #2c352e;

    font-size: 12px;
}


.processing-queue-text span {

    color: #858d87;

    font-size: 10px;
}


.processing-count {

    min-width: 34px;

    color: #08752c;

    font-size: 17px;
    font-weight: 800;

    text-align: right;
}


.processing-workflow-grid {

    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 18px;

    padding: 20px;
}


.processing-workflow-card {

    padding: 18px;

    border:
        1px solid #e7ece8;

    border-radius: 12px;

    background: #ffffff;
}


.processing-workflow-card i {

    display: inline-flex;

    align-items: center;
    justify-content: center;

    width: 38px;
    height: 38px;

    margin-bottom: 12px;

    border-radius: 10px;

    background: #eef7f0;

    color: #08752c;

    font-size: 16px;
}


.processing-workflow-card h4 {

    margin: 0 0 6px;

    color: #2c352e;

    font-size: 12px;
    font-weight: 700;
}


.processing-workflow-card p {

    margin: 0;

    color: #818983;

    font-size: 10px;

    line-height: 1.6;
}


@media (
    max-width: 900px
) {

    .processing-workflow-grid {

        grid-template-columns: 1fr;
    }


    .processing-page-heading {

        flex-direction: column;
    }
}

</style>


<div class="main-page">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

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


            <span class="system-name">

                Research & Extension Management System

            </span>


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
                        $displayRole
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
                        shadow-sm
                    "
                >


                    <li>

                        <div
                            class="
                                dropdown-item-text
                                px-3
                                py-2
                            "
                        >

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
         CONTENT
    ====================================================== -->

    <main class="page-content">


        <?php if (
            $isProcessingView
        ): ?>


            <!-- =================================================
                 RPS PROCESSING OVERVIEW PAGE
            ================================================== -->

            <div class="processing-page-heading">


                <div>


                    <h1>

                        Processing Overview

                    </h1>


                    <p>

                        Monitor research submissions
                        that currently require action
                        from the Research Office.

                    </p>


                </div>


                <div class="processing-total-badge">

                    <i class="bi bi-hourglass-split"></i>

                    <?= number_format(
                        $pendingApprovals
                    ); ?>

                    Pending Actions

                </div>


            </div>



            <!-- PROCESSING CARDS -->

            <section class="stats-row">


                <!-- NEW SUBMISSIONS -->

                <div class="stat-card">


                    <div class="stat-icon green">

                        <i
                            class="
                                bi
                                bi-file-earmark-arrow-up
                            "
                        ></i>

                    </div>


                    <div class="stat-content">

                        <strong>

                            <?= number_format(
                                $rpsPendingSubmissions
                            ); ?>

                        </strong>

                        <span>

                            New Submissions

                        </span>

                    </div>


                </div>



                <!-- ETHICS -->

                <div class="stat-card">


                    <div class="stat-icon yellow">

                        <i class="bi bi-shield-check"></i>

                    </div>


                    <div class="stat-content">

                        <strong>

                            <?= number_format(
                                $rpsEthicsQueue
                            ); ?>

                        </strong>

                        <span>

                            UREC / IACUC

                        </span>

                    </div>


                </div>



                <!-- URRC -->

                <div class="stat-card">


                    <div class="stat-icon green">

                        <i class="bi bi-journal-check"></i>

                    </div>


                    <div class="stat-content">

                        <strong>

                            <?= number_format(
                                $rpsUrrcQueue
                            ); ?>

                        </strong>

                        <span>

                            URRC Review

                        </span>

                    </div>


                </div>



                <!-- IN HOUSE -->

                <div class="stat-card">


                    <div class="stat-icon yellow">

                        <i
                            class="
                                bi
                                bi-clipboard-check
                            "
                        ></i>

                    </div>


                    <div class="stat-content">

                        <strong>

                            <?= number_format(
                                $rpsInhouseQueue
                            ); ?>

                        </strong>

                        <span>

                            In-House Review

                        </span>

                    </div>


                </div>


            </section>



            <!-- QUEUE SUMMARY -->

            <section
                class="
                    asu-panel
                    processing-section
                "
            >


                <div class="panel-header">


                    <h2 class="panel-title">

                        <span class="panel-title-icon">

                            <i class="bi bi-list-task"></i>

                        </span>

                        Processing Queues

                    </h2>


                </div>



                <div class="processing-queue-list">


                    <!-- NEW -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div class="processing-queue-icon">

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-arrow-up
                                    "
                                ></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    New Research Submissions
                                </strong>

                                <span>
                                    Submitted research waiting for initial processing.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsPendingSubmissions
                            ); ?>

                        </div>


                    </div>



                    <!-- ETHICS -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div
                                class="
                                    processing-queue-icon
                                    yellow
                                "
                            >

                                <i class="bi bi-shield-check"></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    UREC / IACUC Review
                                </strong>

                                <span>
                                    Research awaiting ethics review results.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsEthicsQueue
                            ); ?>

                        </div>


                    </div>



                    <!-- URRC -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div class="processing-queue-icon">

                                <i class="bi bi-journal-check"></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    URRC Review
                                </strong>

                                <span>
                                    Initial and revised manuscripts for URRC processing.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsUrrcQueue
                            ); ?>

                        </div>


                    </div>



                    <!-- IN HOUSE -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div
                                class="
                                    processing-queue-icon
                                    yellow
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-clipboard-check
                                    "
                                ></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    In-House Review
                                </strong>

                                <span>
                                    Research manuscripts awaiting In-House decision.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsInhouseQueue
                            ); ?>

                        </div>


                    </div>



                    <!-- NTP -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div class="processing-queue-icon">

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-play
                                    "
                                ></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    Ready for Notice to Proceed
                                </strong>

                                <span>
                                    Approved research ready for NTP processing.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsReadyForNtp
                            ); ?>

                        </div>


                    </div>



                    <!-- PERMITS -->

                    <div class="processing-queue-item">


                        <div class="processing-queue-left">


                            <div
                                class="
                                    processing-queue-icon
                                    yellow
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-check
                                    "
                                ></i>

                            </div>


                            <div class="processing-queue-text">

                                <strong>
                                    Permit Processing
                                </strong>

                                <span>
                                    Terminal reports awaiting publication or presentation permit.
                                </span>

                            </div>


                        </div>


                        <div class="processing-count">

                            <?= number_format(
                                $rpsPermitQueue
                            ); ?>

                        </div>


                    </div>


                </div>


            </section>



            <!-- WORKFLOW -->

            <section
                class="
                    asu-panel
                    processing-section
                "
            >


                <div class="panel-header">


                    <h2 class="panel-title">

                        <span class="panel-title-icon">

                            <i class="bi bi-diagram-3"></i>

                        </span>

                        Research Processing Workflow

                    </h2>


                </div>



                <div class="processing-workflow-grid">


                    <div class="processing-workflow-card">

                        <i class="bi bi-shield-check"></i>

                        <h4>
                            Ethics Processing
                        </h4>

                        <p>
                            Record UREC/IACUC results including
                            Exempt, Expedited and Full-board
                            classifications.
                        </p>

                    </div>


                    <div class="processing-workflow-card">

                        <i class="bi bi-journal-check"></i>

                        <h4>
                            Research Review
                        </h4>

                        <p>
                            Process URRC and In-House review
                            decisions, revisions, acceptance,
                            rejection or failure.
                        </p>

                    </div>


                    <div class="processing-workflow-card">

                        <i class="bi bi-file-earmark-check"></i>

                        <h4>
                            NTP & Permit Processing
                        </h4>

                        <p>
                            Process Notice to Proceed and
                            publication or presentation permits
                            after required submissions.
                        </p>

                    </div>


                </div>


            </section>



        <?php else: ?>


            <!-- =================================================
                 NORMAL DASHBOARD
            ================================================== -->


            <!-- WELCOME -->

            <section class="welcome-banner">


                <div class="welcome-content">


                    <div class="welcome-small">

                        ASU-REMS Dashboard

                    </div>


                    <h1>

                        Welcome back,
                        <?= e(
                            $firstName
                        ); ?>

                        <span class="welcome-wave">
                            👋
                        </span>

                    </h1>


                    <p>


                        <?php if (
                            $userRole === 'rps'
                        ): ?>

                            Manage and process research
                            submissions through the
                            university research workflow.

                        <?php else: ?>

                            Manage Aklan State University's
                            research and extension activities
                            in one centralized platform.

                        <?php endif; ?>


                    </p>


                </div>


                <div
                    class="welcome-decoration"
                    aria-hidden="true"
                >

                    <i class="bi bi-mortarboard"></i>

                </div>


            </section>



            <!-- STAT CARDS -->

            <section class="stats-row">


                <?php if (
                    $userRole === 'rps'
                ): ?>


                    <div class="stat-card">

                        <div class="stat-icon green">

                            <i
                                class="
                                    bi
                                    bi-file-earmark-arrow-up
                                "
                            ></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $rpsPendingSubmissions
                                ); ?>
                            </strong>

                            <span>
                                New Submissions
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon yellow">

                            <i class="bi bi-shield-check"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $rpsEthicsQueue
                                ); ?>
                            </strong>

                            <span>
                                UREC / IACUC
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon green">

                            <i class="bi bi-journal-check"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $rpsUrrcQueue
                                ); ?>
                            </strong>

                            <span>
                                URRC Review
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon yellow">

                            <i
                                class="
                                    bi
                                    bi-clipboard-check
                                "
                            ></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $rpsInhouseQueue
                                ); ?>
                            </strong>

                            <span>
                                In-House Review
                            </span>

                        </div>

                    </div>


                <?php else: ?>


                    <div class="stat-card">

                        <div class="stat-icon green">

                            <i class="bi bi-journal-text"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $activeResearch
                                ); ?>
                            </strong>

                            <span>
                                Active Research
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon yellow">

                            <i class="bi bi-megaphone"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $extensionPrograms
                                ); ?>
                            </strong>

                            <span>
                                Extension Programs
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon green">

                            <i class="bi bi-people"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $facultyResearchers
                                ); ?>
                            </strong>

                            <span>
                                Faculty Researchers
                            </span>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon yellow">

                            <i class="bi bi-check-circle"></i>

                        </div>

                        <div class="stat-content">

                            <strong>
                                <?= number_format(
                                    $completedProjects
                                ); ?>
                            </strong>

                            <span>
                                Completed Projects
                            </span>

                        </div>

                    </div>


                <?php endif; ?>


            </section>



            <!-- MAIN GRID -->

            <section class="dashboard-grid">


                <!-- RECENT SUBMISSIONS -->

                <div
                    class="
                        asu-panel
                        dashboard-main-panel
                    "
                >


                    <div class="panel-header">


                        <h2 class="panel-title">

                            <span class="panel-title-icon">

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-text
                                    "
                                ></i>

                            </span>


                            <?php if (
                                $userRole === 'rps'
                            ): ?>

                                Recent Research Submissions

                            <?php else: ?>

                                Recent Submissions

                            <?php endif; ?>


                        </h2>


                        <?php if (
                            in_array(
                                $userRole,
                                [
                                    'admin',
                                    'faculty'
                                ],
                                true
                            )
                        ): ?>


                            <a
                                href="<?= BASE_URL; ?>/research/index.php"
                                class="panel-link"
                            >

                                View Research

                                <i class="bi bi-arrow-right"></i>

                            </a>


                        <?php endif; ?>


                    </div>



                    <?php if (
                        !empty(
                            $recentResearch
                        )
                    ): ?>


                        <div class="table-responsive">


                            <table
                                class="
                                    table
                                    asu-table
                                    align-middle
                                    mb-0
                                "
                            >


                                <thead>

                                    <tr>

                                        <th>
                                            Manuscript
                                        </th>

                                        <th>
                                            Research
                                        </th>


                                        <?php if (
                                            in_array(
                                                $userRole,
                                                [
                                                    'admin',
                                                    'rps'
                                                ],
                                                true
                                            )
                                        ): ?>

                                            <th>
                                                Researcher
                                            </th>

                                        <?php endif; ?>


                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach (
                                        $recentResearch
                                        as
                                        $research
                                    ): ?>


                                        <tr>


                                            <td>

                                                <strong>

                                                    <?= e(
                                                        $research[
                                                            'manuscript_no'
                                                        ]
                                                        ?: 'Draft'
                                                    ); ?>

                                                </strong>

                                            </td>


                                            <td>

                                                <?= e(
                                                    $research[
                                                        'title'
                                                    ]
                                                ); ?>

                                            </td>


                                            <?php if (
                                                in_array(
                                                    $userRole,
                                                    [
                                                        'admin',
                                                        'rps'
                                                    ],
                                                    true
                                                )
                                            ): ?>


                                                <td>

                                                    <?= e(
                                                        trim(
                                                            (
                                                                $research[
                                                                    'first_name'
                                                                ]
                                                                ?? ''
                                                            )
                                                            .
                                                            ' '
                                                            .
                                                            (
                                                                $research[
                                                                    'last_name'
                                                                ]
                                                                ?? ''
                                                            )
                                                        )
                                                        ?: 'Unknown'
                                                    ); ?>

                                                </td>


                                            <?php endif; ?>


                                            <td>

                                                <span
                                                    class="
                                                        badge
                                                        rounded-pill
                                                        bg-light
                                                        text-dark
                                                        border
                                                    "
                                                >

                                                    <?= e(
                                                        dashboardResearchStatusLabel(
                                                            $research[
                                                                'current_status'
                                                            ]
                                                        )
                                                    ); ?>

                                                </span>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php else: ?>


                        <div class="dashboard-empty-state">


                            <div class="empty-state-icon">

                                <i class="bi bi-inbox"></i>

                            </div>


                            <h3>

                                No submissions yet

                            </h3>


                            <p>

                                Research proposals submitted
                                through ASU-REMS will appear
                                here.

                            </p>


                        </div>


                    <?php endif; ?>


                </div>



                <!-- QUICK STATS -->

                <div
                    class="
                        asu-panel
                        quick-stats-panel
                    "
                >


                    <div class="panel-header">


                        <h2 class="panel-title">

                            <span class="panel-title-icon">

                                <i class="bi bi-bar-chart"></i>

                            </span>

                            Quick Stats

                        </h2>


                    </div>



                    <div class="panel-body">


                        <?php if (
                            $userRole === 'rps'
                        ): ?>


                            <div class="quick-stat">


                                <div class="quick-stat-label">

                                    <i
                                        class="
                                            bi
                                            bi-file-earmark-play
                                        "
                                    ></i>

                                    <span>
                                        Ready for NTP
                                    </span>

                                </div>


                                <strong>

                                    <?= number_format(
                                        $rpsReadyForNtp
                                    ); ?>

                                </strong>


                            </div>



                            <div class="quick-stat">


                                <div class="quick-stat-label">

                                    <i
                                        class="
                                            bi
                                            bi-file-earmark-check
                                        "
                                    ></i>

                                    <span>
                                        Permit Processing
                                    </span>

                                </div>


                                <strong>

                                    <?= number_format(
                                        $rpsPermitQueue
                                    ); ?>

                                </strong>


                            </div>



                            <div class="quick-stat">


                                <div class="quick-stat-label">

                                    <i class="bi bi-journal-text"></i>

                                    <span>
                                        Active Research
                                    </span>

                                </div>


                                <strong>

                                    <?= number_format(
                                        $activeResearch
                                    ); ?>

                                </strong>


                            </div>



                            <div class="quick-stat">


                                <div class="quick-stat-label">

                                    <i class="bi bi-check-circle"></i>

                                    <span>
                                        Completed Research
                                    </span>

                                </div>


                                <strong>

                                    <?= number_format(
                                        $completedProjects
                                    ); ?>

                                </strong>


                            </div>


                        <?php else: ?>


                            <div class="quick-stat">

                                <div class="quick-stat-label">

                                    <i
                                        class="
                                            bi
                                            bi-file-earmark-plus
                                        "
                                    ></i>

                                    <span>
                                        Proposals this month
                                    </span>

                                </div>

                                <strong>

                                    <?= number_format(
                                        $proposalsThisMonth
                                    ); ?>

                                </strong>

                            </div>


                            <div class="quick-stat">

                                <div class="quick-stat-label">

                                    <i
                                        class="
                                            bi
                                            bi-hourglass-split
                                        "
                                    ></i>

                                    <span>
                                        Pending approvals
                                    </span>

                                </div>

                                <strong>

                                    <?= number_format(
                                        $pendingApprovals
                                    ); ?>

                                </strong>

                            </div>


                            <div class="quick-stat">

                                <div class="quick-stat-label">

                                    <i class="bi bi-people"></i>

                                    <span>
                                        Community participants
                                    </span>

                                </div>

                                <strong>

                                    <?= number_format(
                                        $communityParticipants
                                    ); ?>

                                </strong>

                            </div>


                            <div class="quick-stat">

                                <div class="quick-stat-label">

                                    <i
                                        class="
                                            bi
                                            bi-file-bar-graph
                                        "
                                    ></i>

                                    <span>
                                        Reports generated
                                    </span>

                                </div>

                                <strong>

                                    <?= number_format(
                                        $reportsGenerated
                                    ); ?>

                                </strong>

                            </div>


                        <?php endif; ?>


                    </div>



                    <div class="quick-stat-footer">


                        <a
                            href="<?= BASE_URL; ?>/reports/index.php"
                            class="quick-report-link"
                        >

                            <i
                                class="
                                    bi
                                    bi-file-earmark-bar-graph
                                "
                            ></i>

                            View Reports

                        </a>


                    </div>


                </div>


            </section>



            <!-- GETTING STARTED -->

            <section
                class="
                    asu-panel
                    getting-started-panel
                "
            >


                <div class="panel-header">


                    <h2 class="panel-title">

                        <span class="panel-title-icon">

                            <i class="bi bi-compass"></i>

                        </span>

                        Getting Started

                    </h2>


                </div>



                <div class="getting-started-grid">


                    <?php if (
                        $userRole === 'rps'
                    ): ?>


                        <div class="getting-started-item">

                            <div class="getting-icon">

                                <i class="bi bi-shield-check"></i>

                            </div>

                            <div>

                                <h4>
                                    Ethics Review
                                </h4>

                                <p>
                                    Process UREC/IACUC classifications
                                    and record official review results.
                                </p>

                            </div>

                        </div>


                        <div class="getting-started-item">

                            <div
                                class="
                                    getting-icon
                                    yellow-icon
                                "
                            >

                                <i class="bi bi-journal-check"></i>

                            </div>

                            <div>

                                <h4>
                                    Research Review
                                </h4>

                                <p>
                                    Process URRC and In-House review
                                    results, revisions and decisions.
                                </p>

                            </div>

                        </div>


                        <div class="getting-started-item">

                            <div class="getting-icon">

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-check
                                    "
                                ></i>

                            </div>

                            <div>

                                <h4>
                                    NTP & Permits
                                </h4>

                                <p>
                                    Process Notice to Proceed,
                                    terminal reports and publication
                                    or presentation permits.
                                </p>

                            </div>

                        </div>


                    <?php else: ?>


                        <div class="getting-started-item">

                            <div class="getting-icon">

                                <i class="bi bi-journal-text"></i>

                            </div>

                            <div>

                                <h4>
                                    Research Management
                                </h4>

                                <p>
                                    Research proposals and project
                                    records will be managed through
                                    the Research module.
                                </p>

                            </div>

                        </div>


                        <div class="getting-started-item">

                            <div
                                class="
                                    getting-icon
                                    yellow-icon
                                "
                            >

                                <i class="bi bi-megaphone"></i>

                            </div>

                            <div>

                                <h4>
                                    Extension Management
                                </h4>

                                <p>
                                    Extension programs, participants
                                    and accomplishment records will
                                    be managed here.
                                </p>

                            </div>

                        </div>


                        <div class="getting-started-item">

                            <div class="getting-icon">

                                <i
                                    class="
                                        bi
                                        bi-file-earmark-bar-graph
                                    "
                                ></i>

                            </div>

                            <div>

                                <h4>
                                    Institutional Reports
                                </h4>

                                <p>
                                    Generated research and extension
                                    summaries are available through
                                    the Reports module.
                                </p>

                            </div>

                        </div>


                    <?php endif; ?>


                </div>


            </section>


        <?php endif; ?>



        <!-- =================================================
             FOOTER
        ================================================== -->

        <footer class="content-footer">


            <span>

                © <?= date('Y'); ?>
                Aklan State University

            </span>


            <span class="footer-divider">
                •
            </span>


            <span>

                Research & Extension Management System

            </span>


        </footer>


    </main>


</div>


<?php

require __DIR__ . '/includes/footer.php';

?>