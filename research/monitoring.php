<?php



require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_avatar.php';



requireLogin();



$user = currentUser();



if (

    !in_array(

        $user['role'] ?? '',

        [

            'faculty',

            'rps',

            'admin'

        ],

        true

    )

) {

    redirect(BASE_URL . '/dashboard.php');

}



$pageTitle = 'Project Monitoring';

$activePage = 'project_monitoring';



$userRole = $user['role'] ?? '';

$userId = (int)($user['user_id'] ?? 0);



$search = trim($_GET['search'] ?? '');

$sort = trim($_GET['sort'] ?? 'latest');

$status = trim($_GET['status'] ?? '');



$allowedStatuses = [

    '',

    'Ongoing',

    'Completed'

];



if (!in_array($status, $allowedStatuses, true)) {

    $status = '';

}



$sortOptions = [

    'latest' => 'Latest update',

    'target' => 'Target completion',

    'manuscript' => 'Manuscript number',

    'progress_desc' => 'Highest progress',

    'progress_asc' => 'Lowest progress',

    'created' => 'Newest project'

];



if (!array_key_exists($sort, $sortOptions)) {

    $sort = 'latest';

}



$orderBy = match ($sort) {

    'target' => "

        CASE WHEN rp.target_completion_date IS NULL THEN 1 ELSE 0 END ASC,

        rp.target_completion_date ASC,

        rp.research_id DESC

    ",

    'manuscript' => "

        CASE WHEN rp.manuscript_no IS NULL OR rp.manuscript_no = '' THEN 1 ELSE 0 END ASC,

        rp.manuscript_no ASC,

        rp.research_id DESC

    ",

    'progress_desc' => "

        rp.progress_percentage DESC,

        last_activity_at DESC,

        rp.research_id DESC

    ",

    'progress_asc' => "

        rp.progress_percentage ASC,

        last_activity_at DESC,

        rp.research_id DESC

    ",

    'created' => "

        rp.created_at DESC,

        rp.research_id DESC

    ",

    default => "

        last_activity_at DESC,

        rp.research_id DESC

    "

};



$where = [];

$params = [];



$where[] = "(

    rp.workflow_status IN (

        'NTP_ISSUED',

        'COMPLETED_MANUSCRIPT_SUBMITTED',

        'TERMINAL_REPORT_SUBMITTED',

        'PERMIT_ISSUED',

        'COMPLETED'

    )

    OR rp.workflow_stage IN (

        'IMPLEMENTATION',

        'COMPLETION',

        'TERMINAL_REPORT',

        'PUBLICATION_PRESENTATION',

        'COMPLETED'

    )

    OR rp.status IN ('Ongoing', 'Completed')

)";



if ($userRole === 'faculty') {

    $where[] = 'rp.researcher_id = ?';

    $params[] = $userId;

}



if ($status !== '') {

    $where[] = 'rp.status = ?';

    $params[] = $status;

}



if ($search !== '') {

    $where[] = "(

        rp.research_title LIKE ?

        OR rp.manuscript_no LIKE ?

        OR u.first_name LIKE ?

        OR u.last_name LIKE ?

        OR c.college_name LIKE ?

        OR d.department_name LIKE ?

    )";



    $like = '%' . $search . '%';



    array_push(

        $params,

        $like,

        $like,

        $like,

        $like,

        $like,

        $like

    );

}



$whereSql = implode("\nAND ", $where);



$projects = [];

$totalMonitored = 0;

$notStarted = 0;

$inProgress = 0;

$completed = 0;

$averageProgress = 0;



try {

    $sql = "

        SELECT

            rp.research_id,

            rp.researcher_id,

            rp.research_title,

            rp.research_category,

            rp.research_type,

            rp.manuscript_no,

            rp.status,

            rp.progress_percentage,

            rp.workflow_status,

            rp.workflow_stage,

            rp.start_date,

            rp.target_completion_date,

            rp.submitted_at,

            rp.approved_at,

            rp.completed_at,

            rp.created_at,

            rp.updated_at,



            u.first_name,

            u.middle_name,

            u.last_name,

            c.college_name,

            d.department_name,



            (

                SELECT COUNT(*)

                FROM research_progress_updates rpu_count

                WHERE rpu_count.research_id = rp.research_id

            ) AS progress_update_count,



            (

                SELECT MAX(rpu_latest.reporting_date)

                FROM research_progress_updates rpu_latest

                WHERE rpu_latest.research_id = rp.research_id

            ) AS latest_reporting_date,



            (

                SELECT rpu_title.progress_title

                FROM research_progress_updates rpu_title

                WHERE rpu_title.research_id = rp.research_id

                ORDER BY

                    rpu_title.reporting_date DESC,

                    rpu_title.progress_id DESC

                LIMIT 1

            ) AS latest_progress_title,



            COALESCE(

                (

                    SELECT MAX(rpu_activity.created_at)

                    FROM research_progress_updates rpu_activity

                    WHERE rpu_activity.research_id = rp.research_id

                ),

                rp.workflow_updated_at,

                rp.approved_at,

                rp.updated_at,

                rp.created_at

            ) AS last_activity_at



        FROM research_proposals rp



        INNER JOIN users u

            ON u.user_id = rp.researcher_id



        LEFT JOIN colleges c

            ON c.college_id = rp.college_id



        LEFT JOIN departments d

            ON d.department_id = rp.department_id



        WHERE {$whereSql}



        ORDER BY {$orderBy}

    ";



    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);



    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);



} catch (Throwable $e) {

    error_log('Project Monitoring List Error: ' . $e->getMessage());

    $projects = [];

}



$totalMonitored = count($projects);



foreach ($projects as $project) {

    $progress = (int)($project['progress_percentage'] ?? 0);



    if (($project['status'] ?? '') === 'Completed' || $progress >= 100) {

        $completed++;

    } elseif ($progress <= 0) {

        $notStarted++;

    } else {

        $inProgress++;

    }



    $averageProgress += $progress;

}



if ($totalMonitored > 0) {

    $averageProgress = (int)round($averageProgress / $totalMonitored);

}



function monitoringFullName(array $row): string

{

    return trim(

        ($row['first_name'] ?? '')

        . ' '

        . (!empty($row['middle_name']) ? $row['middle_name'] . ' ' : '')

        . ($row['last_name'] ?? '')

    );

}



function monitoringDate(?string $date): string

{

    if (empty($date)) {

        return 'Not set';

    }



    return date('M d, Y', strtotime($date));

}



function monitoringDateTime(?string $date): string

{

    if (empty($date)) {

        return 'No update yet';

    }



    return date('M d, Y h:i A', strtotime($date));

}



function monitoringProgressLabel(int $progress): string

{

    if ($progress >= 100) {

        return 'Completed';

    }



    if ($progress >= 75) {

        return 'Almost done';

    }



    if ($progress >= 50) {

        return 'Midway';

    }



    if ($progress > 0) {

        return 'In progress';

    }



    return 'Not started';

}



$flash = getFlash();



require __DIR__ . '/../includes/header.php';

require __DIR__ . '/../includes/sidebar.php';



?>



<style>

:root {

    --asu-green: #007a33;

    --asu-green-dark: #005c26;

    --asu-green-deep: #003f1a;

    --asu-yellow: #ffd51f;

    --asu-orange: #f28c28;

    --asu-ink: #101810;

    --asu-muted: #657068;

}



/* =========================================================

   PROJECT MONITORING — FINAL UNIFORM LANDSCAPE DESIGN

\========================================================= */



.main-page,

.main-page button,

.main-page input,

.main-page select,

.main-page textarea,

.main-page a,

.main-page table {

    font-family: "Century Gothic", "CenturyGothic", "AppleGothic", Arial, sans-serif !important;

}



.main-page {

    min-height: 100vh;

    background:

        linear-gradient(

            135deg,

            #dff1e3 0%,

            #e8f0e3 34%,

            #f1eee2 68%,

            #f4e6d5 100%

        );

}



.page-content.project-monitoring-page {

    min-height: calc(100vh - 76px);

    padding: 24px 28px 30px;

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

        );

}



.monitoring-page {

    width: 100%;

    max-width: 1320px;

    margin: 0 auto;

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

    display: flex;

    align-items: center;

    gap: 14px;

}



.system-brand-copy {

    position: relative;

    padding-left: 18px;

    line-height: 1.15;

}



.system-brand-copy::before {

    content: "";

    position: absolute;

    left: 0;

    top: 50%;

    width: 3px;

    height: 38px;

    transform: translateY(-50%);

    border-radius: 999px;

    background: var(--asu-green);

}



.system-brand-copy strong {

    display: block;

    color: var(--asu-ink);

    font-size: 17px;

    font-weight: 800;

    letter-spacing: -.015em;

}



.system-brand-copy span {

    display: block;

    margin-top: 4px;

    color: var(--asu-muted);

    font-size: 13px;

}



.topbar-user-text strong {

    color: var(--asu-ink) !important;

    font-size: 15px !important;

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



/* COMPACT 96PX MODULE BANNER */



.monitoring-hero {

    position: relative;

    overflow: hidden;

    min-height: 96px;

    margin: 0 0 18px;

    padding: 20px 26px;

    display: flex;

    align-items: center;

    justify-content: flex-start;

    gap: 18px;

    border: 0;

    border-radius: 18px;

    background:

        linear-gradient(

            112deg,

            #005c26 0%,

            #007a33 64%,

            #2c9a39 100%

        );

    color: #fff;

    box-shadow: 0 18px 42px rgba(0,90,40,.16);

}



.monitoring-hero::after {

    content: "";

    position: absolute;

    right: -110px;

    top: -120px;

    width: 430px;

    height: 280px;

    transform: rotate(-9deg);

    border-radius: 42px;

    background:

        linear-gradient(

            135deg,

            rgba(255,213,31,.16),

            rgba(255,255,255,.035)

        );

    pointer-events: none;

}



.monitoring-hero > * {

    position: relative;

    z-index: 2;

}



.monitoring-hero h1 {

    margin: 0;

    color: #fff !important;

    font-size: 30px;

    line-height: 1;

    font-weight: 900;

    letter-spacing: -.03em;

}



.monitoring-hero p {

    display: none;

}



.monitoring-role-pill {

    min-height: 38px;

    padding: 0 13px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    border: 1px solid rgba(255,255,255,.20);

    border-radius: 999px;

    background: rgba(255,255,255,.13);

    color: #fff;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;

    backdrop-filter: blur(5px);

}





.monitoring-role-pill {

    display: none !important;

}



/* STATS — 4 FULL-WIDTH CARDS */



.monitoring-stats {

    display: grid;

    grid-template-columns: repeat(4,minmax(0,1fr));

    gap: 14px;

    width: 100%;

    margin-bottom: 18px;

}



.monitoring-stat {

    position: relative;

    overflow: hidden;

    min-width: 0;

    min-height: 132px;

    height: 132px;

    padding: 16px 18px 20px;

    display: grid;

    grid-template-columns: 52px minmax(0,1fr);

    align-items: center;

    gap: 15px;

    border: 1px solid rgba(0,122,51,.13);

    border-radius: 18px;

    background: rgba(255,255,255,.96);

    box-shadow: 0 14px 34px rgba(16,24,16,.055);

}



.monitoring-stat::after {

    content: "";

    position: absolute;

    left: -5%;

    right: -5%;

    bottom: -36px;

    height: 54px;

    border-radius: 50% 50% 0 0;

    pointer-events: none;

}



.monitoring-stat:nth-child(odd)::after {

    background: #007a33;

    opacity: .22;

}



.monitoring-stat:nth-child(even)::after {

    background: #ffd51f;

    opacity: .34;

}



.monitoring-stat > * {

    position: relative;

    z-index: 2;

}



.monitoring-stat-icon {

    width: 52px;

    height: 52px;

    min-width: 52px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    font-size: 21px;

}



.monitoring-stat:nth-child(odd) .monitoring-stat-icon {

    background: linear-gradient(135deg,#007a33,#005c26);

    color: #fff;

    box-shadow: 0 10px 18px rgba(0,122,51,.20);

}



.monitoring-stat:nth-child(even) .monitoring-stat-icon {

    background: linear-gradient(135deg,#ffd51f,#f0bd00);

    color: #003f1a;

    box-shadow: 0 10px 18px rgba(255,213,31,.25);

}



.monitoring-stat-copy span {

    display: block;

    margin: 0 0 6px;

    color: var(--asu-ink);

    font-family: "Century Gothic", sans-serif;

    font-size: 12.5px;

    line-height: 1.18;

    font-weight: 700;

}



.monitoring-stat-copy strong {

    display: block;

    color: var(--asu-ink);

    font-size: 28px;

    line-height: 1;

    font-weight: 900;

    letter-spacing: -.035em;

}





/* Average Progress — intentionally minimal, no yellow highlight */

.monitoring-stat:nth-child(4)::after {

    background: #007a33 !important;

    opacity: .10 !important;

}



.monitoring-stat:nth-child(4) .monitoring-stat-icon {

    background: #edf7ef !important;

    color: #007a33 !important;

    box-shadow: none !important;

    border: 1px solid rgba(0,122,51,.10) !important;

}



.monitoring-stat:nth-child(4) .monitoring-stat-copy strong {

    color: #253129 !important;

    font-size: 26px !important;

    font-weight: 800 !important;

}



/* Keep percentage/progress presentation simple and restrained */

.monitoring-progress-top strong {

    padding: 0 !important;

    border: 0 !important;

    background: transparent !important;

    color: #007a33 !important;

    box-shadow: none !important;

}



.monitoring-track {

    background: #e8ede9 !important;

}



.monitoring-value {

    background: #007a33 !important;

    box-shadow: none !important;

}



/* FILTERS */



.monitoring-filter-card {

    margin-bottom: 18px;

    padding: 16px 18px;

    border: 1px solid rgba(0,122,51,.13);

    border-radius: 18px;

    background: rgba(255,255,255,.96);

    box-shadow: 0 14px 34px rgba(16,24,16,.05);

}



.monitoring-filters {

    display: grid;

    grid-template-columns: minmax(280px,1fr) 190px 210px 110px;

    gap: 10px;

    align-items: end;

}



.monitoring-filters label {

    display: block;

    margin-bottom: 5px;

    color: #667169;

    font-family: "Century Gothic", sans-serif;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .03em;

}



.monitoring-filters .form-control,

.monitoring-filters .form-select {

    min-height: 42px;

    height: 42px;

    border: 1px solid #d7e0d8;

    border-radius: 10px;

    background: #fff;

    color: #263229;

    font-size: 10.5px;

    box-shadow: none;

}



/* Show a clear dropdown chevron in Status and Sort By.
   Keep the existing 42px sizing, layout, and native select behavior. */
.project-monitoring-page .monitoring-filters select.form-select {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    padding-right: 38px !important;
    background-color: #fff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23263229' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
    cursor: pointer;
}

.monitoring-filters .form-control:focus,

.monitoring-filters .form-select:focus {

    border-color: #76ac82;

    box-shadow: 0 0 0 3px rgba(8,117,44,.07);

}



.monitoring-filters .btn {

    min-height: 42px;

    height: 42px;

    border-radius: 10px;

    font-size: 10px;

    font-weight: 800;

}



/* MAIN PANEL */



.monitoring-panel {

    overflow: hidden;

    width: 100%;

    border: 1px solid rgba(0,122,51,.13);

    border-radius: 18px;

    background: rgba(255,255,255,.96);

    box-shadow: 0 16px 38px rgba(16,24,16,.06);

}



.monitoring-panel-header {

    min-height: 66px;

    padding: 14px 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom: 1px solid rgba(0,122,51,.09);

    background: rgba(255,255,255,.86);

}



.monitoring-panel-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin: 0;

    color: var(--asu-ink);

    font-family: "Century Gothic", sans-serif;

    font-size: 15px;

    font-weight: 700;

}



.monitoring-panel-title span {

    width: 36px;

    height: 36px;

    min-width: 36px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background: #edf7ef;

    color: var(--asu-green);

    font-size: 16px;

}



.monitoring-count {

    padding: 6px 10px;

    border: 1px solid rgba(0,122,51,.11);

    border-radius: 999px;

    background: #edf7ef;

    color: var(--asu-green-dark);

    font-size: 9px;

    font-weight: 800;

}



/* LANDSCAPE TABLE */



.monitoring-table-wrap {

    width: 100%;

    overflow-x: auto;

}



.monitoring-table {

    width: 100%;

    min-width: 1120px;

    border-collapse: collapse;

    margin: 0;

}



.monitoring-table th {

    padding: 13px 15px;

    background: #f7faf7;

    border-bottom: 1px solid rgba(0,122,51,.10);

    color: #687269;

    font-family: "Century Gothic", sans-serif;

    font-size: 9px;

    font-weight: 700;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .03em;

    white-space: nowrap;

}



.monitoring-table td {

    padding: 14px 15px;

    border-top: 1px solid rgba(0,122,51,.07);

    vertical-align: middle;

    color: #343c36;

    font-size: 10px;

}



.monitoring-table tbody tr:hover td {

    background: rgba(0,122,51,.018);

}



.monitoring-title {

    display: grid;

    gap: 4px;

    min-width: 250px;

}



.monitoring-title strong {

    color: #222a24;

    font-size: 11px;

    line-height: 1.4;

    font-weight: 800;

}



.monitoring-title small,

.monitoring-muted {

    color: #818b84;

    font-size: 8.5px;

    line-height: 1.45;

}



.monitoring-table td > strong {

    font-size: 10px;

    font-weight: 700;

}



.monitoring-progress {

    min-width: 125px;

}



.monitoring-progress-top {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-bottom: 6px;

    color: #687269;

    font-size: 8.5px;

}



.monitoring-progress-top strong {

    color: var(--asu-green);

    font-size: 9px;

}



.monitoring-track {

    height: 6px;

    overflow: hidden;

    border-radius: 999px;

    background: #e5ebe6;

}



.monitoring-value {

    height: 100%;

    border-radius: 999px;

    background: linear-gradient(90deg,#007a33,#2c9a39);

}



.monitoring-status {

    min-height: 28px;

    padding: 4px 9px;

    display: inline-flex;

    align-items: center;

    border: 1px solid rgba(0,122,51,.15);

    border-radius: 999px;

    background: #edf7ef;

    color: var(--asu-green-dark);

    font-size: 8.5px;

    font-weight: 800;

    white-space: nowrap;

}



/* Role-safe, presentation-only status colors to match the other ASU-REMS lists. */
.monitoring-status.monitoring-status-ongoing {

    background: #e7f1ff;

    border-color: #cddff7;

    color: #174b83;

}

/* Completed — soft light green, consistent with Extension Services and Monitoring. */
.monitoring-status.monitoring-status-completed {

    background: #e4f3e8;

    border-color: #cae6d0;

    color: #166335;

}

.monitoring-actions {

    display: flex;

    gap: 7px;

    justify-content: flex-end;

    white-space: nowrap;

}



.monitoring-actions .btn {

    min-height: 34px;

    padding: 0 10px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    font-size: 9px;

    font-weight: 800;

}



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



/* EMPTY */



.monitoring-empty {

    padding: 48px 22px;

    text-align: center;

    color: #77837b;

}



.monitoring-empty i {

    display: block;

    margin-bottom: 11px;

    color: var(--asu-green);

    font-size: 32px;

    opacity: .46;

}



.monitoring-empty strong {

    display: block;

    margin-bottom: 5px;

    color: #47534a;

    font-size: 13px;

    font-weight: 800;

}



.monitoring-empty span {

    font-size: 9.5px;

}



/* ONE COPYRIGHT ONLY */

.content-footer {

    display: none !important;

}



/* RESPONSIVE */



@media (max-width: 1100px) {

    .monitoring-stats {

        grid-template-columns: repeat(2,minmax(0,1fr));

    }



    .monitoring-filters {

        grid-template-columns: 1fr 1fr;

    }

}



@media (max-width: 760px) {

    .asu-topbar {

        padding: 0 16px !important;

    }



    .system-brand-copy strong {

        font-size: 13px;

    }



    .system-brand-copy span {

        font-size: 10px;

    }



    .page-content.project-monitoring-page {

        padding: 18px 14px 24px;

    }



    .monitoring-hero {

        min-height: 82px;

        padding: 18px;

        border-radius: 16px;

        flex-direction: column;

        align-items: flex-start;

    }



    .monitoring-hero h1 {

        font-size: 27px;

    }



    .monitoring-stats,

    .monitoring-filters {

        grid-template-columns: 1fr;

    }



    .monitoring-stat {

        height: auto;

        min-height: 126px;

    }



    .monitoring-panel-header {

        align-items: flex-start;

        flex-direction: column;

    }

}



/* =========================================================
   PROJECT MONITORING — STEADY SCROLL ONLY
   Normal scrolling stays enabled.
   Prevents the whole page from pulling/bouncing past
   the top or bottom edge.
========================================================= */

html,
body {
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
}

.page-content.project-monitoring-page {
    overscroll-behavior-y: contain !important;
}


/* =========================================================
   PROJECT MONITORING — DARK MODE
   Presentation only. Light Mode, queries, filters, progress,
   actions, role permissions, and workflow remain unchanged.
   ========================================================= */

html[data-rems-theme="dark"] .main-page {
    background: #0b1d15 !important;
    color: #eaf3eb !important;
}

html[data-rems-theme="dark"] .page-content.project-monitoring-page {
    background: linear-gradient(135deg, #0b1d15 0%, #10271c 55%, #142c20 100%) !important;
    color: #eaf3eb !important;
}

/* Fixed top header: same palette as the rest of the internal system. */
html[data-rems-theme="dark"] .asu-topbar {
    background: #11291e !important;
    border-bottom-color: #355542 !important;
    box-shadow: 0 7px 24px rgba(0, 0, 0, .14) !important;
}
html[data-rems-theme="dark"] .asu-topbar .system-brand-copy strong,
html[data-rems-theme="dark"] .asu-topbar .topbar-user-text strong {
    color: #f0f6ef !important;
}
html[data-rems-theme="dark"] .asu-topbar .system-brand-copy span,
html[data-rems-theme="dark"] .asu-topbar .topbar-user-text span {
    color: #b8cabb !important;
}
html[data-rems-theme="dark"] .asu-topbar .sidebar-toggle {
    color: #eaf3eb !important;
}

/* Statistics remain readable if shown in a monitoring role. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-stat {
    background: #183428 !important;
    border-color: #355944 !important;
    box-shadow: 0 10px 24px rgba(0,0,0,.12) !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-stat-copy span,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-stat-copy strong,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-stat:nth-child(4) .monitoring-stat-copy strong {
    color: #eef6ee !important;
}

/* Filter card and fields. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filter-card {
    background: #152f23 !important;
    border-color: #365b45 !important;
    box-shadow: 0 10px 24px rgba(0,0,0,.12) !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters label {
    color: #ccdccc !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .form-control,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .form-select {
    background-color: #203d2f !important;
    border-color: #4a6b53 !important;
    color: #f0f8f0 !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .form-control::placeholder {
    color: #b9ccbc !important;
    opacity: 1;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .form-control:focus,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .form-select:focus {
    background-color: #254735 !important;
    border-color: #81ba90 !important;
    box-shadow: 0 0 0 3px rgba(129,186,144,.15) !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters select.form-select {
    background-color: #203d2f !important;
    /* Replace the original near-black chevron with a pale one. */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23d6ead8' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters select option {
    background: #203d2f;
    color: #f0f8f0;
}

/* Monitoring records panel and header. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-panel {
    background: #142d21 !important;
    border-color: #3b6049 !important;
    box-shadow: 0 14px 30px rgba(0,0,0,.15) !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-panel-header {
    background: #1b3a2b !important;
    border-bottom-color: #3c604a !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-panel-title {
    color: #f1f7f0 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-panel-title span {
    background: #28523a !important;
    color: #9be1ad !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-count {
    background: #244933 !important;
    border-color: #50785a !important;
    color: #e1f4e4 !important;
}

/* Readable column headings, data rows, and secondary text. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table-wrap {
    background: #142d21 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table th {
    background: #203b2d !important;
    color: #d4e6d5 !important;
    border-bottom-color: #456750 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table td {
    background: #142d21 !important;
    color: #e8f1e8 !important;
    border-top-color: #365643 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table tbody tr:nth-child(even) td {
    background: #183427 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table tbody tr:hover td {
    background: #224533 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-table td > strong,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-title strong {
    color: #f1f8f0 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-title small,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-muted {
    color: #bad0bd !important;
}

/* Keep percentage green and visible on dark surfaces. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-progress-top strong {
    color: #79d69b !important;
    background: transparent !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-track {
    background: #395b46 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-value {
    background: linear-gradient(90deg, #2cb86a, #62d38a) !important;
}

/* Status colors remain meaningful instead of becoming unreadable. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-status {
    border-color: #50755c !important;
    background: #224b34 !important;
    color: #d6f5df !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-status.monitoring-status-ongoing {
    background: #183e60 !important;
    border-color: #4276a0 !important;
    color: #d8edff !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-status.monitoring-status-completed {
    background: #205137 !important;
    border-color: #4d9364 !important;
    color: #d9f6e1 !important;
}

/* Buttons and empty-state text. */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-actions .btn-outline-asu {
    background: #203e2f !important;
    border-color: #648b6f !important;
    color: #e8f6e9 !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-actions .btn-outline-asu:hover {
    background: #2b543d !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-empty {
    color: #bacfbd !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-empty strong {
    color: #edf6ec !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-empty i {
    color: #83d5a0 !important;
}


/* =============================================================
   DARK MODE: ALWAYS-VISIBLE STATUS / SORT BY DROPDOWN ARROWS
   Draw a CSS chevron on each field container. This does not
   depend on a background SVG or change the native select logic.
   The light-mode appearance is intentionally untouched.
   ============================================================= */
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .rems-monitoring-dropdown {
    position: relative !important;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .rems-monitoring-dropdown > select.form-select,
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .rems-monitoring-dropdown > select.form-select:focus {
    -webkit-appearance: none !important;
    appearance: none !important;
    background-image: none !important;
    padding-right: 43px !important;
    cursor: pointer;
}
html[data-rems-theme="dark"] .project-monitoring-page .monitoring-filters .rems-monitoring-dropdown::after {
    content: "";
    position: absolute;
    right: 19px;
    bottom: 18px;
    width: 9px;
    height: 9px;
    border-right: 2px solid #dff5e4;
    border-bottom: 2px solid #dff5e4;
    transform: rotate(45deg);
    pointer-events: none;
    z-index: 2;
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

        <button id="sidebarToggle" class="sidebar-toggle" type="button">

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

            <span><?= e($userRole === 'rps' ? 'Research Processing Staff (RPS)' : roleLabel($userRole)); ?></span>

        </div>

        <?= remsProfileAvatarHtml($pdo, $user, strtoupper(substr((string)($user['first_name'] ?? 'U'), 0, 1) . substr((string)($user['last_name'] ?? ''), 0, 1)), 'dashboard-avatar'); ?>

    </div>

</header>



<main class="page-content project-monitoring-page">



<div class="monitoring-page">



    <?php if ($flash): ?>

        <div class="alert alert-<?= e($flash['type']); ?> alert-dismissible fade show">

            <?= e($flash['message']); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>



    <section class="monitoring-hero">

        <div>

            <h1>Project Monitoring</h1>

            <p>

                <?= $userRole === 'faculty'

                    ? 'View your research projects with issued Notice to Proceed and submit implementation progress updates.'

                    : 'View all research projects that already received Notice to Proceed. RPS access is view-only for monitoring updates.'; ?>

            </p>

        </div>

    </section>







    <section class="monitoring-filter-card">

        <form method="GET" class="monitoring-filters">

            <div>

                <label>Search</label>

                <input

                    type="text"

                    name="search"

                    class="form-control"

                    value="<?= e($search); ?>"

                    placeholder="Search title, manuscript no., researcher, college"

                >

            </div>



            <div class="rems-monitoring-dropdown">

                <label>Status</label>

                <select name="status" class="form-select">

                    <option value="">All status</option>

                    <option value="Ongoing" <?= $status === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>

                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>

                </select>

            </div>



            <div class="rems-monitoring-dropdown">

                <label>Sort By</label>

                <select name="sort" class="form-select">

                    <?php foreach ($sortOptions as $sortKey => $sortLabel): ?>

                        <option value="<?= e($sortKey); ?>" <?= $sort === $sortKey ? 'selected' : ''; ?>>

                            <?= e($sortLabel); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>



            <div>

                <button type="submit" class="btn btn-asu w-100">

                    <i class="bi bi-filter me-1"></i>

                    Apply

                </button>

            </div>

        </form>

    </section>



    <section class="monitoring-panel">

        <div class="monitoring-panel-header">

            <h2 class="monitoring-panel-title">

                <span><i class="bi bi-graph-up-arrow"></i></span>

                NTP / Implementation Projects

            </h2>

            <div class="monitoring-count">

                <?= number_format($totalMonitored); ?> record<?= $totalMonitored === 1 ? '' : 's'; ?>

            </div>

        </div>



        <?php if (empty($projects)): ?>

            <div class="monitoring-empty">

                <i class="bi bi-inboxes"></i>

                <strong>No projects for monitoring yet.</strong>

                <span>Only research with issued Notice to Proceed will appear here.</span>

            </div>

        <?php else: ?>

            <div class="monitoring-table-wrap">

                <table class="monitoring-table">

                    <thead>

                        <tr>

                            <th>Research</th>

                            <th>Researcher</th>

                            <th>Status</th>

                            <th>Progress</th>

                            <th>Latest Update</th>

                            <th>Target</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($projects as $project): ?>

                            <?php

                                $projectProgress = min(

                                    100,

                                    max(

                                        0,

                                        (int)($project['progress_percentage'] ?? 0)

                                    )

                                );

                            ?>

                            <tr>

                                <td>

                                    <div class="monitoring-title">

                                        <strong><?= e($project['research_title']); ?></strong>

                                        <small>

                                            <?= e($project['manuscript_no'] ?: 'No manuscript number'); ?>

                                            <?= !empty($project['research_category']) ? ' · ' . e($project['research_category']) : ''; ?>

                                        </small>

                                    </div>

                                </td>

                                <td>

                                    <strong><?= e(monitoringFullName($project)); ?></strong>

                                    <div class="monitoring-muted">

                                        <?= e($project['college_name'] ?: 'College not assigned'); ?>

                                    </div>

                                </td>

                                <td>

                                    <span class="monitoring-status<?= (string)($project['status'] ?? '') === 'Completed' ? ' monitoring-status-completed' : ((string)($project['status'] ?? '') === 'Ongoing' ? ' monitoring-status-ongoing' : ''); ?>">

                                        <?= e($project['status']); ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="monitoring-progress">

                                        <div class="monitoring-progress-top">


                                            <strong><?= $projectProgress; ?>%</strong>

                                        </div>

                                        <div class="monitoring-track">

                                            <div class="monitoring-value" style="width: <?= $projectProgress; ?>%;"></div>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <strong><?= e($project['latest_progress_title'] ?: 'No update yet'); ?></strong>

                                    <div class="monitoring-muted">

                                        <?= e(monitoringDate($project['latest_reporting_date'] ?? null)); ?>

                                        · <?= number_format((int)$project['progress_update_count']); ?> update<?= (int)$project['progress_update_count'] === 1 ? '' : 's'; ?>

                                    </div>

                                </td>

                                <td>

                                    <?= e(monitoringDate($project['target_completion_date'] ?? null)); ?>

                                </td>

                                <td>

                                    <div class="monitoring-actions">

                                        <a

                                            href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$project['research_id']; ?>&from=project_monitoring"

                                            class="btn btn-outline-asu btn-sm"

                                        >

                                            Details

                                        </a>

                                        <a

                                            href="<?= BASE_URL; ?>/research/progress.php?id=<?= (int)$project['research_id']; ?>&from=project_monitoring"

                                            class="btn btn-asu btn-sm"

                                        >

                                            <i class="bi <?= $userRole === 'faculty' ? 'bi-pencil-square' : 'bi-eye'; ?> me-1"></i>

                                            <?= $userRole === 'faculty' ? 'Update Progress' : 'View Progress'; ?>

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>



</div>



</main>



</div>



<?php require __DIR__ . '/../includes/footer.php'; ?>
