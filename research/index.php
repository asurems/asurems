<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $_SESSION['role'] ?? '',
        [
            'admin',
            'faculty'
        ],
        true
    )
) {

    redirect(
        BASE_URL . '/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = currentUser();

$pageTitle = 'Research Management';
$activePage = 'research';


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

$collegeId =
    !empty($_GET['college_id'])
        ? (int)$_GET['college_id']
        : null;


/*
|--------------------------------------------------------------------------
| VALID RESEARCH STATUS
|--------------------------------------------------------------------------
*/

$validStatuses = [
    'Draft',
    'Submitted',
    'Under Review',
    'For Revision',
    'Approved',
    'Ongoing',
    'Completed',
    'Rejected',
    'Archived'
];


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE COLLEGES
|--------------------------------------------------------------------------
*/

$collegeStmt = $pdo->query("
    SELECT
        college_id,
        college_name
    FROM colleges
    WHERE status = 'Active'
    ORDER BY college_name ASC
");


$colleges =
    $collegeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| BASE ACCESS CONDITION
|--------------------------------------------------------------------------
*/

if (
    $user['role'] === 'faculty'
) {

    $accessSql =
        "rp.researcher_id = ?";

    $accessParams = [
        (int)$user['user_id']
    ];

} else {

    $accessSql =
        "rp.status <> 'Draft'";

    $accessParams = [];
}


/*
|--------------------------------------------------------------------------
| STATISTICS FUNCTION
|--------------------------------------------------------------------------
*/

function researchCount(
    PDO $pdo,
    string $accessSql,
    array $accessParams,
    ?string $statusCondition = null
): int {

    $sql = "
        SELECT COUNT(*)
        FROM research_proposals rp
        WHERE {$accessSql}
    ";

    $params = $accessParams;


    if (
        $statusCondition !== null
    ) {

        $sql .= "
            AND {$statusCondition}
        ";
    }


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute(
        $params
    );


    return (int)$stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalProposals =
    researchCount(
        $pdo,
        $accessSql,
        $accessParams
    );


$underReview =
    researchCount(
        $pdo,
        $accessSql,
        $accessParams,
        "rp.status = 'Under Review'"
    );


$activeResearch =
    researchCount(
        $pdo,
        $accessSql,
        $accessParams,
        "rp.status = 'Ongoing'"
    );


$completed =
    researchCount(
        $pdo,
        $accessSql,
        $accessParams,
        "rp.status = 'Completed'"
    );


/*
|--------------------------------------------------------------------------
| BUILD RESEARCH RECORD QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        rp.research_id,
        rp.researcher_id,

        rp.research_title,
        rp.research_category,
        rp.research_type,

        rp.status,
        rp.progress_percentage,

        rp.submitted_at,
        rp.created_at,
        rp.updated_at,

        rp.start_date,
        rp.target_completion_date,

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

    WHERE {$accessSql}
";


$params =
    $accessParams;


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if (
    $search !== ''
) {

    $sql .= "
        AND (
            rp.research_title LIKE ?
            OR rp.research_category LIKE ?
            OR rp.research_type LIKE ?
            OR u.first_name LIKE ?
            OR u.middle_name LIKE ?
            OR u.last_name LIKE ?
        )
    ";


    $searchTerm =
        '%' .
        $search .
        '%';


    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
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
        AND rp.status = ?
    ";

    $params[] =
        $status;
}


/*
|--------------------------------------------------------------------------
| COLLEGE FILTER
|--------------------------------------------------------------------------
*/

if (
    $collegeId !== null
) {

    $sql .= "
        AND rp.college_id = ?
    ";

    $params[] =
        $collegeId;
}


/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        rp.updated_at DESC,
        rp.created_at DESC
";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute(
    $params
);


$researchRecords =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| FLASH
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

require __DIR__ .
    '/../includes/header.php';


require __DIR__ .
    '/../includes/sidebar.php';

?>


<style>

/* =========================================================
   RESEARCH TABLE
========================================================= */

.research-table-wrapper {
    overflow-x: auto;
}


.research-table {
    width: 100%;
    margin: 0;
}


.research-table thead th {
    padding:
        14px
        16px;

    border-bottom:
        1px solid
        #e3e9e3;

    background:
        #f8faf8;

    color:
        #687269;

    font-size:
        10px;

    font-weight:
        700;

    letter-spacing:
        .035em;

    text-transform:
        uppercase;

    white-space:
        nowrap;
}


.research-table tbody td {
    padding:
        16px;

    border-bottom:
        1px solid
        #edf1ed;

    color:
        #343c36;

    font-size:
        11px;

    vertical-align:
        middle;
}


.research-table tbody tr:last-child td {
    border-bottom:
        none;
}


/* =========================================================
   RESEARCH TITLE
========================================================= */

.research-title-cell {
    min-width:
        260px;
}


.research-title-cell strong {
    display:
        block;

    margin-bottom:
        4px;

    color:
        #222a24;

    font-size:
        12px;

    font-weight:
        700;

    line-height:
        1.45;
}


.research-title-cell small {
    display:
        block;

    color:
        #868f88;

    font-size:
        9px;

    line-height:
        1.5;
}


/* =========================================================
   RESEARCHER
========================================================= */

.researcher-name {
    min-width:
        145px;
}


.researcher-name strong {
    display:
        block;

    margin-bottom:
        3px;

    font-size:
        11px;

    font-weight:
        600;
}


.researcher-name small {
    color:
        #8a928c;

    font-size:
        9px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.research-status {
    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-height:
        27px;

    padding:
        4px
        10px;

    border-radius:
        50px;

    font-size:
        9px;

    font-weight:
        700;

    white-space:
        nowrap;
}


.research-status-draft {
    background:
        #eef1ef;

    color:
        #626b64;
}


.research-status-submitted {
    background:
        #e7f0ff;

    color:
        #365f99;
}


.research-status-review {
    background:
        #fff4c8;

    color:
        #78620b;
}


.research-status-revision {
    background:
        #fff0e2;

    color:
        #9a551c;
}


.research-status-approved {
    background:
        #e4f4e8;

    color:
        #23703e;
}


.research-status-ongoing {
    background:
        #e6f5ea;

    color:
        #08752c;
}


.research-status-completed {
    background:
        #ddf1e2;

    color:
        #12652e;
}


.research-status-rejected {
    background:
        #fde8e8;

    color:
        #a13939;
}


.research-status-archived {
    background:
        #ebecef;

    color:
        #60656e;
}


/* =========================================================
   PROGRESS
========================================================= */

.research-progress {
    min-width:
        120px;
}


.research-progress-bar {
    width:
        100%;

    max-width:
        115px;

    height:
        6px;

    margin-bottom:
        5px;

    overflow:
        hidden;

    border-radius:
        20px;

    background:
        #e7ece8;
}


.research-progress-value {
    height:
        100%;

    border-radius:
        20px;

    background:
        var(--asu-green);
}


.research-progress-percentage {
    color:
        #687269;

    font-size:
        9px;

    font-weight:
        600;
}


/* =========================================================
   PROGRESS STATE
========================================================= */

.research-progress-state {
    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    min-height:
        27px;

    font-size:
        9px;

    font-weight:
        600;

    white-space:
        nowrap;
}


.research-progress-state i {
    font-size:
        11px;
}


.research-progress-not-started {
    color:
        #737c75;
}


.research-progress-not-started i {
    color:
        #9aa29c;
}


.research-progress-na {
    color:
        #a0a7a2;
}


.research-progress-complete {
    color:
        var(--asu-green);
}


/* =========================================================
   EMPTY STATE
========================================================= */

.research-empty-state {
    min-height:
        300px;

    display:
        flex;

    flex-direction:
        column;

    align-items:
        center;

    justify-content:
        center;

    padding:
        40px
        25px;

    text-align:
        center;
}


.research-empty-icon {
    width:
        68px;

    height:
        68px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin-bottom:
        15px;

    border-radius:
        50%;

    background:
        #edf7ef;

    color:
        var(--asu-green);

    font-size:
        27px;
}


.research-empty-state h3 {
    margin:
        0
        0
        7px;

    color:
        #283029;

    font-size:
        15px;

    font-weight:
        700;
}


.research-empty-state p {
    max-width:
        430px;

    margin:
        0
        0
        17px;

    color:
        #7d867f;

    font-size:
        10px;

    line-height:
        1.65;
}


/* =========================================================
   RESULT INFO
========================================================= */

.research-result-info {
    color:
        #828b84;

    font-size:
        10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 768px
) {

    .research-table {
        min-width:
            900px;
    }

}



/* =========================================================
   FINAL RESEARCH MANAGEMENT DESIGN
   Same visual family as completed ASU-REMS interfaces
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

/* ---------------------------------------------------------
   GLOBAL / BACKGROUND
--------------------------------------------------------- */

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

.research-management-page {
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

/* ---------------------------------------------------------
   TOPBAR
--------------------------------------------------------- */

.asu-topbar {
    min-height: 76px !important;
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
    font-weight: 400 !important;
}

.topbar-user-text strong {
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

/* ---------------------------------------------------------
   COMPACT MAIN-MODULE BANNER
--------------------------------------------------------- */

.research-management-page .page-heading {
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

.research-management-page .page-heading::after {
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

.research-management-page .page-heading > * {
    position: relative !important;
    z-index: 2 !important;
}

.research-management-page .page-heading h1 {
    margin: 0 !important;
    color: #ffffff !important;
    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.research-management-page .page-heading p {
    display: none !important;
}

.research-management-page .page-heading .btn {
    min-height: 42px !important;
    height: 42px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 0 !important;
    border-radius: 10px !important;
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.18) !important;
}

.research-management-page .page-heading .btn:hover {
    background: #f0bd00 !important;
    color: var(--asu-green-deep) !important;
}

/* ---------------------------------------------------------
   FLASH
--------------------------------------------------------- */

.research-management-page .alert {
    margin: 0 0 16px !important;
    padding: 12px 15px !important;
    border-radius: 12px !important;
    font-size: 10.5px !important;
    box-shadow: 0 8px 20px rgba(16,24,16,.035) !important;
}

/* ---------------------------------------------------------
   STAT CARDS — 4 FULL-WIDTH CARDS
--------------------------------------------------------- */

.research-management-page .stats-row {
    display: grid !important;
    grid-template-columns: repeat(4,minmax(0,1fr)) !important;
    gap: 14px !important;
    width: 100% !important;
    margin: 0 0 18px !important;
    align-items: stretch !important;
}

.research-management-page .stat-card {
    position: relative !important;
    overflow: hidden !important;
    width: 100% !important;
    max-width: none !important;
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

.research-management-page .stat-card::after {
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
}

.research-management-page .stat-card:nth-child(odd)::after {
    background: #007a33 !important;
    opacity: .22 !important;
}

.research-management-page .stat-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}

.research-management-page .stat-card > * {
    position: relative !important;
    z-index: 2 !important;
}

.research-management-page .stat-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    border-radius: 15px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 22px !important;
}

.research-management-page .stat-icon.green {
    background:
        linear-gradient(135deg,#007a33,#005c26) !important;
    color: #ffffff !important;
    box-shadow: 0 10px 18px rgba(0,122,51,.20) !important;
}

.research-management-page .stat-icon.yellow {
    background:
        linear-gradient(135deg,#ffd51f,#f0bd00) !important;
    color: var(--asu-green-deep) !important;
    box-shadow: 0 10px 18px rgba(255,213,31,.25) !important;
}

.research-management-page .stat-content strong {
    display: block !important;
    margin: 0 0 6px !important;
    color: var(--asu-ink) !important;
    font-size: 28px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.research-management-page .stat-content span {
    display: block !important;
    color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* ---------------------------------------------------------
   FILTER CARD
--------------------------------------------------------- */

.research-management-page .filter-card {
    margin: 0 0 18px !important;
    padding: 16px 18px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.05) !important;
}

.research-management-page .filter-card .form-control,
.research-management-page .filter-card .form-select,
.research-management-page .filter-card .input-group-text {
    min-height: 42px !important;
    height: 42px !important;
    border-color: #d7e0d8 !important;
    background: #ffffff !important;
    color: #263229 !important;
    font-size: 10.5px !important;
    box-shadow: none !important;
}

.research-management-page .filter-card .input-group-text {
    border-radius: 10px 0 0 10px !important;
}

.research-management-page .filter-card .input-group .form-control {
    border-radius: 0 10px 10px 0 !important;
}

.research-management-page .filter-card .form-select {
    border-radius: 10px !important;
}

.research-management-page .filter-card .form-control:focus,
.research-management-page .filter-card .form-select:focus {
    border-color: #76ac82 !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}

.research-management-page .filter-card .btn {
    min-height: 42px !important;
    height: 42px !important;
    border-radius: 10px !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
}

.research-management-page .filter-card .small {
    font-size: 9.5px !important;
    font-weight: 700 !important;
    text-decoration: none !important;
}

/* ---------------------------------------------------------
   RESEARCH RECORDS PANEL
--------------------------------------------------------- */

.research-management-page .asu-panel {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;
}

.research-management-page .panel-header {
    min-height: 66px !important;
    padding: 14px 18px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.86) !important;
}

.research-management-page .panel-title {
    margin: 0 !important;
    color: var(--asu-ink) !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
}

.research-management-page .panel-title-icon {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
}

/* Match the Project Monitoring records-count pill. */
.research-management-page .research-result-info {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 auto !important;
    padding: 6px 10px !important;
    border: 1px solid rgba(0, 122, 51, .11) !important;
    border-radius: 999px !important;
    background: #edf7ef !important;
    color: #005c26 !important;
    font-size: 9px !important;
    font-weight: 800 !important;
    white-space: nowrap !important;
}

/* ---------------------------------------------------------
   TABLE
--------------------------------------------------------- */

.research-table-wrapper {
    overflow-x: auto !important;
}

.research-table {
    width: 100% !important;
    margin: 0 !important;
}

.research-table thead th {
    padding: 13px 15px !important;
    border-bottom: 1px solid rgba(0,122,51,.10) !important;
    background: #f7faf7 !important;
    color: #687269 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    letter-spacing: .03em !important;
    text-transform: uppercase !important;
    white-space: nowrap !important;
}

.research-table tbody td {
    padding: 14px 15px !important;
    border-bottom: 1px solid rgba(0,122,51,.07) !important;
    color: #343c36 !important;
    font-size: 10px !important;
    vertical-align: middle !important;
}

.research-table tbody tr:hover td {
    background: rgba(0,122,51,.018) !important;
}

.research-title-cell {
    min-width: 250px !important;
}

.research-title-cell strong {
    margin-bottom: 4px !important;
    color: #222a24 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1.4 !important;
}

.research-title-cell small,
.researcher-name small {
    color: #818b84 !important;
    font-size: 8.5px !important;
}

.researcher-name strong {
    font-size: 10px !important;
    font-weight: 700 !important;
}

/* ---------------------------------------------------------
   STATUS BADGES — SOFT PILLS (REFERENCE PICTURE 1)
   Visual changes only; database statuses and workflow stay intact.
--------------------------------------------------------- */

.research-management-page .research-status {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 28px !important;
    padding: 4px 10px !important;
    border: 1px solid #bfe0ca !important;
    border-radius: 999px !important;
    background: #ebf7ee !important;
    color: #003f1a !important;
    font-size: 9px !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    box-shadow: none !important;
}

/* Completed and Ongoing: the same pale-green appearance as Picture 1. */
.research-management-page .research-status.research-status-approved,
.research-management-page .research-status.research-status-ongoing,
.research-management-page .research-status.research-status-completed {
    background: #ebf7ee !important;
    color: #003f1a !important;
    border-color: #bfe0ca !important;
}

/* Other statuses also use gentle outlined pills instead of solid fills. */
.research-management-page .research-status.research-status-draft,
.research-management-page .research-status.research-status-rejected,
.research-management-page .research-status.research-status-archived {
    background: #f1f4f2 !important;
    color: #334138 !important;
    border-color: #d6e1da !important;
}

.research-management-page .research-status.research-status-submitted,
.research-management-page .research-status.research-status-review {
    background: #fff9e1 !important;
    color: #725c0a !important;
    border-color: #f2e3a6 !important;
}

.research-management-page .research-status.research-status-revision {
    background: #fff1e6 !important;
    color: #914d12 !important;
    border-color: #edcfb8 !important;
}

/* ---------------------------------------------------------
   PROGRESS
--------------------------------------------------------- */

.research-progress {
    min-width: 115px !important;
}

.research-progress-bar {
    max-width: 110px !important;
    height: 6px !important;
    margin-bottom: 5px !important;
    border-radius: 999px !important;
    background: #e5ebe6 !important;
}

.research-progress-value {
    border-radius: 999px !important;
    background:
        linear-gradient(
            90deg,
            #007a33,
            #2c9a39
        ) !important;
}

.research-progress-percentage,
.research-progress-state {
    font-size: 8.5px !important;
    font-weight: 700 !important;
}

/* ---------------------------------------------------------
   ACTION BUTTON
--------------------------------------------------------- */

.research-table .btn-outline-asu {
    min-height: 34px !important;
    padding: 0 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid rgba(0,122,51,.22) !important;
    border-radius: 9px !important;
    background: #ffffff !important;
    color: var(--asu-green) !important;
    font-size: 9px !important;
    font-weight: 800 !important;
}

.research-table .btn-outline-asu:hover {
    background: #f2f8f3 !important;
    color: var(--asu-green-dark) !important;
}

/* ---------------------------------------------------------
   EMPTY STATE
--------------------------------------------------------- */

.research-empty-state {
    min-height: 280px !important;
    padding: 42px 25px !important;
}

.research-empty-icon {
    width: 58px !important;
    height: 58px !important;
    margin-bottom: 13px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 24px !important;
}

.research-empty-state h3 {
    margin: 0 0 6px !important;
    color: #283029 !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}

.research-empty-state p {
    max-width: 430px !important;
    margin: 0 0 15px !important;
    color: #7d867f !important;
    font-size: 9.5px !important;
    line-height: 1.6 !important;
}

/* ---------------------------------------------------------
   WORKFLOW INFORMATION
--------------------------------------------------------- */

.design-notice {
    margin-top: 16px !important;
    padding: 13px 15px !important;
    border: 1px solid rgba(0,122,51,.11) !important;
    border-left: 4px solid var(--asu-green) !important;
    border-radius: 12px !important;
    background: rgba(255,255,255,.78) !important;
    color: #657068 !important;
    font-size: 9.5px !important;
    line-height: 1.55 !important;
}

.design-notice strong {
    color: #2f3a32 !important;
    font-size: 9.5px !important;
}

/* Shared/common footer only */
.content-footer {
    display: none !important;
}

/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 1100px) {
    .research-management-page .stats-row {
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

    .research-management-page {
        padding: 18px 14px 24px !important;
    }

    .research-management-page .page-heading {
        min-height: 82px !important;
        padding: 18px !important;
        border-radius: 16px !important;
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .research-management-page .page-heading h1 {
        font-size: 27px !important;
    }

    .research-management-page .page-heading .btn {
        align-self: flex-start !important;
    }

    .research-management-page .stats-row {
        grid-template-columns: 1fr !important;
    }

    .research-management-page .stat-card {
        height: auto !important;
        min-height: 126px !important;
    }

    .research-table {
        min-width: 900px !important;
    }
}


/* Research filters match Project Monitoring dimensions and spacing. */
.research-management-page .research-filter-grid {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) 190px 210px 110px;
    gap: 10px;
    align-items: end;
}
.research-management-page .research-filter-field {
    min-width: 0;
}
.research-management-page .research-filter-field label {
    display: block;
    margin: 0 0 5px;
    color: #667169;
    font-family: "Century Gothic", sans-serif;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.5;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.research-management-page .research-filter-field .form-control,
.research-management-page .research-filter-field .form-select,
.research-management-page .research-filter-field .btn {
    width: 100%;
    min-height: 42px !important;
    height: 42px !important;
    border-radius: 10px !important;
}
.research-management-page .research-filter-submit .btn {
    background: linear-gradient(135deg, #007a33, #005c26) !important;
    border: none !important;
    color: #fff !important;
    font-size: 10px !important;
    font-weight: 800 !important;
}
@media (max-width: 1100px) {
    .research-management-page .research-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 650px) {
    .research-management-page .research-filter-grid {
        grid-template-columns: 1fr;
    }
    .research-management-page .research-filter-submit label {
        display: none;
    }
}


/* Research action: compact eye icon only (no text). */
.research-management-page .research-table .research-eye-button {
    width: 38px !important;
    min-width: 38px !important;
    height: 34px !important;
    min-height: 34px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.research-management-page .research-table .research-eye-button i {
    margin: 0 !important;
    font-size: 15px !important;
    line-height: 1 !important;
}


/* Keep the Status and College dropdown arrows visible.
   The earlier background shorthand hides Bootstrap's default select chevron. */
.research-management-page .filter-card .research-filter-field select.form-select {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m4 6 4 4 4-4' fill='none' stroke='%23667169' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 15px 15px !important;
    padding-right: 38px !important;
    cursor: pointer;
}


/* Consistent progress display: percentage above the bar, no repeated status. */
.research-management-page .research-progress {
    width: 100% !important;
    min-width: 140px !important;
    max-width: 190px !important;
}
.research-management-page .research-progress-top {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 6px;
    line-height: 1.2;
}
.research-management-page .research-progress-percentage {
    color: var(--asu-green) !important;
    font-size: 9px !important;
    font-weight: 800 !important;
}
.research-management-page .research-progress-bar {
    width: 100% !important;
    max-width: none !important;
    height: 6px !important;
    margin-bottom: 0 !important;
}



/* ===========================================================
   ASU-REMS RESEARCH MANAGEMENT — DARK MODE CONTRAST FIX
   Presentation only. Light mode and PHP workflows are unchanged.
   Uses the same saved theme attribute as includes/rems_theme.php.
=========================================================== */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page),
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page {
    background: #0b1d15 !important;
    color: #e9f2e9 !important;
}

/* Main header: keep the brand, username and avatar readable. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page) > header.asu-topbar {
    background: #12291e !important;
    border-bottom-color: #365743 !important;
    box-shadow: 0 6px 18px rgba(0,0,0,.13) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page) .system-brand-copy strong,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page) .topbar-user-text strong {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page) .system-brand-copy span,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .main-page:has(.research-management-page) .topbar-user-text span {
    color: #bfd0c1 !important;
}

/* Keep the signature ASU green-and-gold hero, not a flat dark block. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .page-heading {
    background: linear-gradient(112deg,#004b26 0%,#006b34 64%,#167c3c 100%) !important;
    box-shadow: 0 14px 28px rgba(0,0,0,.12) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .page-heading h1 {
    color: #ffffff !important;
}

/* Search/filter form and record panel use consistent dark surfaces. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .asu-panel {
    background: #152f23 !important;
    border-color: #365744 !important;
    color: #e9f2e9 !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.12) !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-filter-field label {
    color: #c2d8c8 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .form-control,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .form-select,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .input-group-text {
    background-color: #1e3c2d !important;
    border-color: #4c7058 !important;
    color: #f1f8f0 !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .form-control::placeholder {
    color: #b7cdbb !important;
    opacity: 1;
}
/* Preserve a clearly visible chevron on both select inputs. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .research-filter-field select.form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m4 6 4 4 4-4' fill='none' stroke='%23d9e9dc' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 15px 15px !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .filter-card .small {
    color: #a9edbd !important;
}

html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .panel-header {
    background: #1b3a2a !important;
    border-bottom-color: #42624d !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .panel-title {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .panel-title-icon {
    background: #27513b !important;
    color: #a3e4b5 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-result-info {
    background: #274b35 !important;
    color: #d2efda !important;
    border-color: #4a7556 !important;
}

/* Bootstrap tables use per-cell background variables. Override BOTH those
   variables and cell backgrounds to prevent mixed bright and dark rows. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table {
    --bs-table-bg: #152f23;
    --bs-table-color: #e9f2e9;
    --bs-table-border-color: #385b45;
    --bs-table-hover-bg: #234735;
    --bs-table-hover-color: #f4fbf2;
    background: #152f23 !important;
    color: #e9f2e9 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table > thead > tr > th {
    --bs-table-bg: #234330;
    background: #234330 !important;
    box-shadow: none !important;
    border-bottom-color: #46654f !important;
    color: #d9e9dc !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table > tbody > tr > td {
    --bs-table-bg: #152f23;
    background: #152f23 !important;
    box-shadow: none !important;
    border-bottom-color: #365743 !important;
    color: #d9e9dc !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table > tbody > tr:hover > td {
    --bs-table-bg: #203f2f;
    background: #203f2f !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .research-title-cell strong,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .researcher-name strong,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table tbody td > strong {
    color: #f2f8f0 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .research-title-cell small,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .researcher-name small,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .text-muted {
    color: #b8cfbd !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-progress-bar {
    background: #345640 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-progress-percentage,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-progress-complete {
    color: #77d99a !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-progress-na,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-progress-not-started {
    color: #b7c9ba !important;
}
/* Status chips retain recognizable colors but get enough contrast. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-draft,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-archived {
    background: #304639 !important;
    border-color: #6b8571 !important;
    color: #e2eae2 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-rejected {
    background: #573137 !important;
    border-color: #996168 !important;
    color: #ffe4e4 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-submitted,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-review {
    background: #594b25 !important;
    border-color: #9b8246 !important;
    color: #fff1bc !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-revision {
    background: #563922 !important;
    border-color: #ad784b !important;
    color: #ffe1bb !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-approved,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-ongoing,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-status.research-status-completed {
    background: #214c34 !important;
    border-color: #578d67 !important;
    color: #c7f5d2 !important;
}
/* Eye action stays in the same position and remains keyboard accessible. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .research-eye-button {
    background: #27523a !important;
    border-color: #5c8365 !important;
    color: #e7f8eb !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .research-eye-button:hover,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-table .research-eye-button:focus-visible {
    background: #326b49 !important;
    color: #ffffff !important;
}

/* Empty-state text and workflow note: never dark text on dark surfaces. */
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-empty-state h3,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .design-notice strong {
    color: #f0f7ed !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-empty-state p,
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .design-notice {
    color: #c3d6c6 !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .research-empty-icon {
    background: #254d36 !important;
    color: #a6e5ba !important;
}
html[data-rems-theme="dark"] body:has(.asu-sidebar) .research-management-page .design-notice {
    background: #1b3628 !important;
    border-color: #42634b !important;
    border-left-color: #5ebd7b !important;
}


/* Research Management banner actions — Archives beside New Proposal. */
.research-management-page .research-management-heading-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    min-width: 0;
}
.research-management-page .page-heading .research-management-heading-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    min-height: 42px !important;
    white-space: nowrap;
    text-decoration: none;
}
.research-management-page .page-heading .research-management-heading-actions .btn.research-archive-btn {
    background: #ffffff !important;
    border: 1px solid rgba(255,255,255,.70) !important;
    color: #005c26 !important;
    box-shadow: none !important;
}
.research-management-page .page-heading .research-management-heading-actions .btn.research-archive-btn:hover,
.research-management-page .page-heading .research-management-heading-actions .btn.research-archive-btn:focus-visible {
    background: #f4f8f4 !important;
    border-color: #ffffff !important;
    color: #003f1a !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .research-management-page .page-heading .research-management-heading-actions .btn.research-archive-btn {
    background: #f2f8f3 !important;
    color: #005c26 !important;
    border-color: #ffffff !important;
    box-shadow: none !important;
}
@media (max-width: 760px) {
    .research-management-page .research-management-heading-actions {
        justify-content: flex-start;
        width: 100%;
    }
}

</style>



<div class="main-page">


<!-- =====================================================
     TOP HEADER
====================================================== -->

<header class="asu-topbar">


<div class="topbar-left">


<button
    id="sidebarToggle"
    type="button"
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

<?= e(
    fullName(
        $user
    )
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



<div class="user-avatar">

<?= e(
    $initials
); ?>

</div>


</div>


</header>



<!-- =====================================================
     PAGE CONTENT
====================================================== -->

<main class="page-content research-management-page">


<!-- =================================================
     PAGE HEADING
================================================== -->

<div class="page-heading">


<div>


<h1>

Research Management

</h1>


<p>

<?php if (
    $user['role']
    === 'faculty'
): ?>

Manage your research proposals,
submissions and project records.

<?php else: ?>

Review submitted research proposals
and monitor institutional research
records.

<?php endif; ?>

</p>


</div>



<!-- Quick access to saved research archives; never changes the workflow. -->
<div class="research-management-heading-actions">
    <a
        href="<?= BASE_URL; ?>/research/archive.php"
        class="btn research-archive-btn"
        title="Open research archives"
    >
        <i class="bi bi-archive" aria-hidden="true"></i>
        Archives
    </a>

    <?php if ($user['role'] === 'faculty'): ?>
        <a
            href="<?= BASE_URL; ?>/research/create.php"
            class="btn btn-asu"
        >
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            New Proposal
        </a>
    <?php endif; ?>
</div>


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
    $flash[
        'message'
    ]
); ?>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
    aria-label="Close"
></button>


</div>


<?php endif; ?>









<!-- =================================================
     SEARCH AND FILTER
================================================== -->

<section class="filter-card">


<form
    method="GET"
    action=""
>


<div class="research-filter-grid">
    <div class="research-filter-field">
        <label for="researchSearch">SEARCH</label>
        <input id="researchSearch" type="search" name="search" class="form-control"
               placeholder="Search title, category or researcher" value="<?= e($search); ?>">
    </div>
    <div class="research-filter-field">
        <label for="researchStatus">STATUS</label>
        <select id="researchStatus" name="status" class="form-select">
            <option value="">All Status</option>
            <?php foreach ($validStatuses as $statusOption): ?>
                <?php if ($user['role'] === 'admin' && $statusOption === 'Draft') continue; ?>
                <option value="<?= e($statusOption); ?>" <?= $status === $statusOption ? 'selected' : ''; ?>><?= e($statusOption); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="research-filter-field">
        <label for="researchCollege">COLLEGE</label>
        <select id="researchCollege" name="college_id" class="form-select">
            <option value="">All Colleges</option>
            <?php foreach ($colleges as $college): ?>
                <option value="<?= (int)$college['college_id']; ?>" <?= $collegeId === (int)$college['college_id'] ? 'selected' : ''; ?>><?= e($college['college_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="research-filter-field research-filter-submit">
        <label aria-hidden="true">&nbsp;</label>
        <button type="submit" class="btn btn-asu w-100"><i class="bi bi-funnel-fill me-1"></i> Filter</button>
    </div>
</div>



<?php if (
    $search !== ''
    ||
    $status !== ''
    ||
    $collegeId !== null
): ?>


<div class="mt-3">


<a
    href="<?= BASE_URL; ?>/research/index.php"
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
     RESEARCH RECORDS
================================================== -->

<section class="asu-panel">


<div class="panel-header">


<h2 class="panel-title">


<span class="panel-title-icon">

<i class="bi bi-journal-text"></i>

</span>


Research Records


</h2>



<span class="research-result-info">

<?= number_format(
    count(
        $researchRecords
    )
); ?>

record<?= count(
    $researchRecords
) === 1
    ? ''
    : 's'; ?>

</span>


</div>



<?php if (
    empty(
        $researchRecords
    )
): ?>


<!-- =================================================
     EMPTY STATE
================================================== -->

<div class="research-empty-state">


<div class="research-empty-icon">

<i class="bi bi-journal-text"></i>

</div>


<h3>

<?php if (
    $search !== ''
    ||
    $status !== ''
    ||
    $collegeId !== null
): ?>

No matching research records

<?php else: ?>

No research proposals yet

<?php endif; ?>

</h3>



<p>


<?php if (
    $search !== ''
    ||
    $status !== ''
    ||
    $collegeId !== null
): ?>

No research proposals match
the selected search or filter
criteria.

<?php elseif (
    $user['role']
    === 'faculty'
): ?>

You have not created a research
proposal yet. Create your first
proposal and save it as a draft.

<?php else: ?>

No research proposals have been
submitted for administrative
review yet.

<?php endif; ?>


</p>



<?php if (
    $user['role']
    === 'faculty'
    &&
    $search === ''
    &&
    $status === ''
    &&
    $collegeId === null
): ?>


<a
    href="<?= BASE_URL; ?>/research/create.php"
    class="btn btn-asu"
>

<i class="bi bi-plus-lg me-1"></i>

Create First Proposal

</a>


<?php endif; ?>


</div>


<?php else: ?>


<!-- =================================================
     ACTUAL DATABASE RECORDS
================================================== -->

<div class="research-table-wrapper">


<table
    class="
        table
        research-table
        align-middle
    "
>


<thead>


<tr>


<th>

Research

</th>



<?php if (
    $user['role']
    === 'admin'
): ?>


<th>

Researcher

</th>


<?php endif; ?>



<th>

College / Department

</th>


<th>

Status

</th>


<th>

Progress

</th>


<th>

Date

</th>


<th class="text-end">

Action

</th>


</tr>


</thead>



<tbody>


<?php foreach (
    $researchRecords
    as
    $research
): ?>


<?php

/*
|--------------------------------------------------------------------------
| RESEARCHER NAME
|--------------------------------------------------------------------------
*/

$researcherName =
    trim(
        $research[
            'first_name'
        ]
        .
        ' '
        .
        (
            !empty(
                $research[
                    'middle_name'
                ]
            )
                ? $research[
                    'middle_name'
                ]
                . ' '
                : ''
        )
        .
        $research[
            'last_name'
        ]
    );


/*
|--------------------------------------------------------------------------
| STATUS STYLE
|--------------------------------------------------------------------------
*/

$statusClass =
    'research-status-draft';


switch (
    $research['status']
) {

    case 'Submitted':

        $statusClass =
            'research-status-submitted';

        break;


    case 'Under Review':

        $statusClass =
            'research-status-review';

        break;


    case 'For Revision':

        $statusClass =
            'research-status-revision';

        break;


    case 'Approved':

        $statusClass =
            'research-status-approved';

        break;


    case 'Ongoing':

        $statusClass =
            'research-status-ongoing';

        break;


    case 'Completed':

        $statusClass =
            'research-status-completed';

        break;


    case 'Rejected':

        $statusClass =
            'research-status-rejected';

        break;


    case 'Archived':

        $statusClass =
            'research-status-archived';

        break;
}


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$displayDate =
    !empty(
        $research[
            'submitted_at'
        ]
    )
        ? $research[
            'submitted_at'
        ]
        : $research[
            'created_at'
        ];


/*
|--------------------------------------------------------------------------
| PROGRESS
|--------------------------------------------------------------------------
|
| Important:
|
| Proposal workflow stages do NOT represent
| project implementation percentage.
|
| Approved = ready to start implementation.
| Ongoing  = actual progress tracking.
| Completed = 100%.
|
*/

$progressPercentage =
    min(
        100,
        max(
            0,
            (int)$research[
                'progress_percentage'
            ]
        )
    );


$showProgressBar = false;

$progressLabel = '—';

$progressState =
    'not-applicable';


switch (
    $research['status']
) {


    /*
    |--------------------------------------------------------------------------
    | APPROVED
    |--------------------------------------------------------------------------
    */

    case 'Approved':

        $progressLabel =
            'Not Started';

        $progressState =
            'not-started';

        break;


    /*
    |--------------------------------------------------------------------------
    | ONGOING
    |--------------------------------------------------------------------------
    */

    case 'Ongoing':

        $showProgressBar = true;

        $progressLabel =
            $progressPercentage
            .
            '%';

        $progressState =
            'ongoing';

        break;


    /*
    |--------------------------------------------------------------------------
    | COMPLETED
    |--------------------------------------------------------------------------
    */

    case 'Completed':

        $showProgressBar = true;

        $progressPercentage = 100;

        $progressLabel =
            '100%';

        $progressState =
            'completed';

        break;


    /*
    |--------------------------------------------------------------------------
    | PROPOSAL / NON-IMPLEMENTATION STATES
    |--------------------------------------------------------------------------
    */

    default:

        $progressLabel =
            '—';

        $progressState =
            'not-applicable';

        break;
}

?>


<tr>


<!-- =================================================
     RESEARCH
================================================== -->

<td class="research-title-cell">


<strong>

<?= e(
    $research[
        'research_title'
    ]
); ?>

</strong>


<small>

<?= e(
    $research[
        'research_type'
    ]
    ?: 'No research type'
); ?>


<?php if (
    !empty(
        $research[
            'research_category'
        ]
    )
): ?>

·

<?= e(
    $research[
        'research_category'
    ]
); ?>

<?php endif; ?>


</small>


</td>



<!-- =================================================
     RESEARCHER
================================================== -->

<?php if (
    $user['role']
    === 'admin'
): ?>


<td class="researcher-name">


<strong>

<?= e(
    $researcherName
); ?>

</strong>


<small>

Faculty / Researcher

</small>


</td>


<?php endif; ?>



<!-- =================================================
     COLLEGE / DEPARTMENT
================================================== -->

<td>


<?php if (
    !empty(
        $research[
            'college_name'
        ]
    )
): ?>


<strong
    style="
        display:block;
        font-size:10px;
        font-weight:600;
    "
>

<?= e(
    $research[
        'college_name'
    ]
); ?>

</strong>


<small
    class="text-muted"
    style="
        font-size:9px;
    "
>

<?= !empty(
    $research[
        'department_name'
    ]
)
    ? e(
        $research[
            'department_name'
        ]
    )
    : 'No department'; ?>

</small>


<?php else: ?>


<span class="text-muted">

Not Assigned

</span>


<?php endif; ?>


</td>



<!-- =================================================
     STATUS
================================================== -->

<td>


<span
    class="
        research-status
        <?= e(
            $statusClass
        ); ?>
    "
>

<?= e(
    $research[
        'status'
    ]
); ?>

</span>


</td>



<!-- =================================================
     PROGRESS
================================================== -->

<td>

<?php if ($progressState === 'ongoing' || $progressState === 'completed'): ?>

<div class="research-progress">
    <div class="research-progress-top">
        <span class="research-progress-percentage"><?= $progressPercentage; ?>%</span>
    </div>
    <div class="research-progress-bar" role="progressbar"
         aria-label="Research implementation progress"
         aria-valuenow="<?= $progressPercentage; ?>"
         aria-valuemin="0" aria-valuemax="100">
        <div class="research-progress-value" style="width: <?= $progressPercentage; ?>%;"></div>
    </div>
</div>

<?php elseif (
    $progressState
    === 'not-started'
): ?>


<span
    class="
        research-progress-state
        research-progress-not-started
    "
>

<i class="bi bi-clock"></i>

Not Started

</span>



<?php else: ?>


<span
    class="
        research-progress-state
        research-progress-na
    "
    title="Implementation progress is not applicable at this stage."
>

—

</span>


<?php endif; ?>


</td>



<!-- =================================================
     DATE
================================================== -->

<td>


<?= e(
    date(
        'M d, Y',
        strtotime(
            $displayDate
        )
    )
); ?>


<div
    class="text-muted"
    style="
        margin-top:3px;
        font-size:9px;
    "
>

<?= !empty(
    $research[
        'submitted_at'
    ]
)
    ? 'Submitted'
    : 'Created'; ?>

</div>


</td>



<!-- =================================================
     ACTION
================================================== -->

<td class="text-end">


<a
    href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$research['research_id']; ?>"
    class="btn btn-outline-asu btn-sm research-eye-button"
    title="View research proposal"
    aria-label="View research proposal"
>
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
     INFORMATION
================================================== -->

<div class="design-notice">


<strong>

Research Workflow:

</strong>


<?php if (
    $user['role']
    === 'faculty'
): ?>

Draft proposals may be edited and
submitted for review. Approved research
projects may proceed to implementation
and progress monitoring.

<?php else: ?>

Draft proposals remain private to their
faculty researcher. Submitted proposals
enter the administrative review workflow,
while approved and ongoing projects may
be monitored through implementation.

<?php endif; ?>


</div>



<!-- =================================================
     FOOTER
================================================== -->
</main>


</div>


<?php

require __DIR__ .
    '/../includes/footer.php';

?>