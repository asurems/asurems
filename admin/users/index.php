<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

$user = currentUser();

$pageTitle = 'User Management';
$activePage = 'users';


/*
|--------------------------------------------------------------------------
| SEARCH AND FILTER VALUES
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$role   = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| VALID FILTER VALUES
|--------------------------------------------------------------------------
*/

$validRoles = [
    'admin',
    'faculty',
    'rps',
    'extension_coordinator'
];

$validStatuses = [
    'Active',
    'Inactive'
];


/*
|--------------------------------------------------------------------------
| USERS QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.user_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.username,
        u.email,
        u.role,
        u.status,
        u.last_login,
        u.created_at,
        u.updated_at,

        c.college_name,

        d.department_name

    FROM users u

    LEFT JOIN colleges c
        ON c.college_id = u.college_id

    LEFT JOIN departments d
        ON d.department_id = u.department_id

    WHERE 1 = 1
";


$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            u.first_name LIKE ?
            OR u.middle_name LIKE ?
            OR u.last_name LIKE ?
            OR u.username LIKE ?
            OR u.email LIKE ?
        )
    ";

    $searchTerm =
        '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}


/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

if (
    $role !== ''
    &&
    in_array(
        $role,
        $validRoles,
        true
    )
) {

    $sql .= "
        AND u.role = ?
    ";

    $params[] = $role;
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
        AND u.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        u.created_at DESC,
        u.last_name ASC,
        u.first_name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers =
    (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
        ")
        ->fetchColumn();


$activeUsers =
    (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE status = 'Active'
        ")
        ->fetchColumn();


$facultyUsers =
    (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'faculty'
        ")
        ->fetchColumn();


$rpsUsers =
    (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'rps'
        ")
        ->fetchColumn();


$extensionUsers =
    (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'extension_coordinator'
        ")
        ->fetchColumn();


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flash = getFlash();

$statusFlashClass = '';

if ($flash && !empty($flash['message'])) {

    if (stripos($flash['message'], 'Inactive') !== false) {

        $statusFlashClass = 'status-flash-inactive';

    } elseif (stripos($flash['message'], 'Active') !== false) {

        $statusFlashClass = 'status-flash-active';
    }
}


/*
|--------------------------------------------------------------------------
| HEADER AND SIDEBAR
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../../includes/header.php';

require __DIR__ . '/../../includes/sidebar.php';

?>



<style>
:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-ink: #101810;
    --asu-muted: #657068;
    --asu-line: rgba(0, 122, 51, .15);
}

/* =========================================================
   USER MANAGEMENT — SAME ADMIN DASHBOARD DESIGN LANGUAGE
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

.page-content {
    min-height: calc(100vh - 76px);
    padding: 24px 28px 30px !important;
    background:
        radial-gradient(circle at 88% 4%, rgba(255, 213, 31, .10), transparent 26%),
        radial-gradient(circle at 10% 8%, rgba(0, 122, 51, .07), transparent 28%),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/* Top bar — same clean admin feel */
.asu-topbar {
    min-height: 76px;
    padding: 0 30px;
    border-bottom: 1px solid rgba(0, 122, 51, .12);
    background: rgba(255, 255, 255, .94) !important;
    box-shadow: 0 10px 26px rgba(16, 24, 16, .035);
    backdrop-filter: blur(10px);
}

.system-name {
    color: var(--asu-ink) !important;
    font-weight: 800 !important;
    letter-spacing: -.015em;
}

/* Page heading */
.page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 20px;
}

.page-heading h1 {
    margin: 0;
    color: var(--asu-ink);
    font-size: clamp(28px, 2.7vw, 36px);
    line-height: 1.05;
    font-weight: 900;
    letter-spacing: -.035em;
}

.btn-asu {
    min-height: 44px;
    padding: 0 17px;
    border: 0 !important;
    border-radius: 13px !important;
    background: linear-gradient(135deg, var(--asu-green), var(--asu-green-dark)) !important;
    color: #fff !important;
    font-weight: 800 !important;
    box-shadow: 0 10px 22px rgba(0, 122, 51, .18);
}

.btn-asu:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(0, 122, 51, .22);
}

/* =========================================================
   USER STAT CARDS — EQUAL SIZE / SAME TYPOGRAPHY
========================================================= */

.stats-row {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 14px !important;
    margin-bottom: 20px !important;
}

.stat-card {
    min-width: 0;
    min-height: 132px !important;
    padding: 17px 16px !important;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 18px !important;
    background: rgba(255, 255, 255, .93) !important;
    display: grid !important;
    grid-template-columns: 54px minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 14px !important;
    box-shadow: 0 14px 34px rgba(16, 24, 16, .055) !important;
    backdrop-filter: blur(3px);
}

.stat-icon {
    width: 54px !important;
    height: 54px !important;
    min-width: 54px !important;
    border-radius: 15px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 23px !important;
}

.stat-icon.green {
    background: linear-gradient(135deg, var(--asu-green), var(--asu-green-dark)) !important;
    color: #fff !important;
    box-shadow: 0 12px 20px rgba(0, 122, 51, .22);
}

.stat-icon.yellow {
    background: linear-gradient(135deg, var(--asu-yellow), #f0bd00) !important;
    color: var(--asu-green-deep) !important;
    box-shadow: 0 12px 20px rgba(255, 213, 31, .28);
}

.stat-content {
    min-width: 0;
}

.stat-content strong {
    display: block;
    margin-bottom: 7px;
    color: var(--asu-ink);
    font-family: "Century Gothic", sans-serif !important;
    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

/* Same idea as dashboard: headers use Century Gothic */
.stat-content span {
    display: block;
    color: var(--asu-ink);
    font-family: "Century Gothic", sans-serif !important;
    font-size: 14px !important;
    line-height: 1.2 !important;
    font-weight: 700 !important;
}

/* =========================================================
   FILTERS
========================================================= */

.filter-card {
    margin-bottom: 20px !important;
    padding: 18px !important;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 18px !important;
    background: rgba(255, 255, 255, .88) !important;
    box-shadow: 0 14px 34px rgba(16, 24, 16, .05) !important;
    backdrop-filter: blur(4px);
}

.filter-card .form-control,
.filter-card .form-select,
.filter-card .input-group-text {
    min-height: 44px;
    border-color: rgba(0, 122, 51, .14) !important;
    background: rgba(255, 255, 255, .96) !important;
    font-size: 12px;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: rgba(0, 122, 51, .42) !important;
    box-shadow: 0 0 0 3px rgba(0, 122, 51, .08) !important;
}

/* =========================================================
   USER ACCOUNTS TABLE PANEL
========================================================= */

.asu-panel {
    overflow: hidden;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 18px !important;
    background: rgba(255, 255, 255, .94) !important;
    box-shadow: 0 14px 36px rgba(16, 24, 16, .055) !important;
    backdrop-filter: blur(3px);
}

.panel-header {
    min-height: 70px;
    padding: 15px 20px !important;
    border-bottom: 1px solid rgba(0, 122, 51, .09) !important;
    background: rgba(255, 255, 255, .72);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.panel-title {
    margin: 0;
    color: var(--asu-ink);
    font-size: 17px !important;
    font-weight: 900 !important;
    letter-spacing: -.02em;
}

.panel-title i {
    color: var(--asu-green);
    margin-right: 7px;
}

.asu-table {
    margin-bottom: 0 !important;
    background: transparent !important;
}

.asu-table thead th {
    padding: 14px 16px !important;
    border-bottom: 1px solid rgba(0, 122, 51, .12) !important;
    background: rgba(245, 250, 246, .82) !important;
    color: #334139 !important;
    font-size: 10.5px !important;
    font-weight: 850 !important;
    letter-spacing: .035em;
    text-transform: uppercase;
    white-space: nowrap;
}

.asu-table tbody td {
    padding: 14px 16px !important;
    border-color: rgba(0, 122, 51, .07) !important;
    color: #263229;
    font-size: 12px;
}

.asu-table tbody tr:hover {
    background: rgba(0, 122, 51, .025) !important;
}

/* Buttons / badges */
.btn-outline-asu {
    border-color: rgba(0, 122, 51, .28) !important;
    color: var(--asu-green-dark) !important;
}

.btn-outline-asu:hover {
    background: rgba(0, 122, 51, .07) !important;
}

.status-badge,
.badge {
    font-size: 10px !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1280px) {
    .stats-row {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 900px) {
    .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .page-heading {
        align-items: flex-start;
    }
}

@media (max-width: 640px) {
    .page-content {
        padding: 18px 14px 24px !important;
    }

    .page-heading {
        flex-direction: column;
        align-items: stretch;
    }

    .page-heading .btn-asu {
        width: 100%;
        justify-content: center;
    }

    .stats-row {
        grid-template-columns: 1fr !important;
    }

    .stat-card {
        min-height: 112px !important;
    }

    .filter-card {
        padding: 14px !important;
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



            <?php

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

            ?>



            <div class="dropdown">


                <button
                    type="button"
                    class="user-dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <div class="user-avatar">

                        <?= e($initials); ?>

                    </div>

                </button>



                <ul
                    class="dropdown-menu dropdown-menu-end"
                >


                    <li>

                        <span
                            class="dropdown-item-text"
                        >

                            <strong
                                class="d-block"
                            >

                                <?= e(
                                    fullName($user)
                                ); ?>

                            </strong>


                            <small
                                class="text-muted"
                            >

                                <?= e(
                                    $user['email']
                                ); ?>

                            </small>

                        </span>

                    </li>



                    <li>

                        <hr
                            class="dropdown-divider"
                        >

                    </li>



                    <li>

                        <a
                            href="<?= BASE_URL; ?>/logout.php"
                            class="dropdown-item"
                        >

                            <i
                                class="bi bi-box-arrow-right me-2"
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

    <main class="page-content">


        <!-- =================================================
             PAGE HEADING
        ================================================== -->

        <div class="page-heading">


            <div>


                <h1>

                    User Management

                </h1>


            </div>



            <a
                href="<?= BASE_URL; ?>/admin/users/create.php"
                class="btn btn-asu"
            >

                <i
                    class="bi bi-person-plus me-1"
                ></i>

                Add User

            </a>


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
                    auto-dismiss
                    <?= e($statusFlashClass); ?>
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



        <!-- =================================================
             USER STATISTIC CARDS
        ================================================== -->

        <section class="stats-row">



            <!-- TOTAL USERS -->

            <div class="stat-card">


                <div
                    class="stat-icon green"
                >

                    <i class="bi bi-people"></i>

                </div>


                <div class="stat-content">


                    <strong>

                        <?= number_format(
                            $totalUsers
                        ); ?>

                    </strong>


                    <span>

                        Total Users

                    </span>


                </div>


            </div>



            <!-- ACTIVE USERS -->

            <div class="stat-card">


                <div
                    class="stat-icon yellow"
                >

                    <i class="bi bi-person-check"></i>

                </div>


                <div class="stat-content">


                    <strong>

                        <?= number_format(
                            $activeUsers
                        ); ?>

                    </strong>


                    <span>

                        Active Accounts

                    </span>


                </div>


            </div>



            <!-- FACULTY -->

            <div class="stat-card">


                <div
                    class="stat-icon green"
                >

                    <i class="bi bi-mortarboard"></i>

                </div>


                <div class="stat-content">


                    <strong>

                        <?= number_format(
                            $facultyUsers
                        ); ?>

                    </strong>


                    <span>

                        Faculty Researchers

                    </span>


                </div>


            </div>



            <!-- RPS -->

            <div class="stat-card">


                <div
                    class="stat-icon yellow"
                >

                    <i class="bi bi-person-gear"></i>

                </div>


                <div class="stat-content">


                    <strong>

                        <?= number_format(
                            $rpsUsers
                        ); ?>

                    </strong>


                    <span>

                        RPS Accounts

                    </span>


                </div>


            </div>



            <!-- EXTENSION -->

            <div class="stat-card">


                <div
                    class="stat-icon green"
                >

                    <i class="bi bi-person-workspace"></i>

                </div>


                <div class="stat-content">


                    <strong>

                        <?= number_format(
                            $extensionUsers
                        ); ?>

                    </strong>


                    <span>

                        Extension Coordinators

                    </span>


                </div>


            </div>


        </section>



        <!-- =================================================
             SEARCH AND FILTER
        ================================================== -->

        <section class="filter-card">


            <form
                method="GET"
                class="row g-2"
            >


                <!-- SEARCH -->

                <div class="col-lg-5">


                    <div class="input-group">


                        <span
                            class="input-group-text bg-white"
                        >

                            <i class="bi bi-search"></i>

                        </span>


                        <input
                            type="search"
                            name="search"
                            class="form-control"
                            placeholder="Search name, username or email"
                            value="<?= e(
                                $search
                            ); ?>"
                        >


                    </div>


                </div>



                <!-- ROLE FILTER -->

                <div class="col-lg-3">


                    <select
                        name="role"
                        class="form-select"
                    >


                        <option value="">

                            All Roles

                        </option>



                        <option
                            value="admin"
                            <?= $role === 'admin'
                                ? 'selected'
                                : ''; ?>
                        >

                            Administrator

                        </option>



                        <option
                            value="faculty"
                            <?= $role === 'faculty'
                                ? 'selected'
                                : ''; ?>
                        >

                            Faculty / Researcher

                        </option>



                        <option
                            value="rps"
                            <?= $role === 'rps'
                                ? 'selected'
                                : ''; ?>
                        >

                            RPS

                        </option>



                        <option
                            value="extension_coordinator"
                            <?= $role ===
                                'extension_coordinator'
                                ? 'selected'
                                : ''; ?>
                        >

                            Extension Coordinator

                        </option>


                    </select>


                </div>



                <!-- STATUS -->

                <div class="col-lg-2">


                    <select
                        name="status"
                        class="form-select"
                    >


                        <option value="">

                            All Status

                        </option>



                        <option
                            value="Active"
                            <?= $status ===
                                'Active'
                                ? 'selected'
                                : ''; ?>
                        >

                            Active

                        </option>



                        <option
                            value="Inactive"
                            <?= $status ===
                                'Inactive'
                                ? 'selected'
                                : ''; ?>
                        >

                            Inactive

                        </option>


                    </select>


                </div>



                <!-- FILTER BUTTON -->

                <div class="col-lg-2">


                    <button
                        type="submit"
                        class="
                            btn
                            btn-asu
                            w-100
                        "
                    >

                        <i
                            class="bi bi-funnel me-1"
                        ></i>

                        Filter

                    </button>


                </div>


            </form>



            <?php if (
                $search !== ''
                ||
                $role !== ''
                ||
                $status !== ''
            ): ?>


                <div class="mt-3">


                    <a
                        href="<?= BASE_URL; ?>/admin/users/index.php"
                        class="small"
                        style="
                            color:
                            var(--asu-green);
                        "
                    >

                        <i
                            class="bi bi-x-circle me-1"
                        ></i>

                        Clear Filters

                    </a>


                </div>


            <?php endif; ?>


        </section>



        <!-- =================================================
             USER TABLE
        ================================================== -->

        <section class="asu-panel">


            <div class="panel-header">


                <h2 class="panel-title">


                    <i
                        class="bi bi-people"
                    ></i>

                    User Accounts


                </h2>



                <span
                    class="text-muted small"
                >

                    <?= count($users); ?>

                    result<?= count($users) !== 1
                        ? 's'
                        : ''; ?>

                </span>


            </div>



            <div class="table-responsive">


                <table
                    class="
                        table
                        asu-table
                        align-middle
                    "
                >


                    <thead>


                        <tr>


                            <th>

                                User

                            </th>


                            <th>

                                Username

                            </th>


                            <th>

                                Role

                            </th>


                            <th>

                                College / Department

                            </th>


                            <th>

                                Status

                            </th>


                            <th>

                                Last Login

                            </th>


                            <th
                                class="text-end"
                            >

                                Actions

                            </th>


                        </tr>


                    </thead>



                    <tbody>



                    <?php if (
                        empty($users)
                    ): ?>


                        <tr>


                            <td
                                colspan="7"
                                class="
                                    text-center
                                    py-5
                                "
                            >


                                <div
                                    class="text-muted"
                                >


                                    <i
                                        class="bi bi-people"
                                        style="
                                            font-size:
                                            32px;
                                        "
                                    ></i>


                                    <div
                                        class="mt-2"
                                    >

                                        No user
                                        accounts found.

                                    </div>


                                </div>


                            </td>


                        </tr>



                    <?php else: ?>



                        <?php foreach (
                            $users
                            as
                            $account
                        ): ?>



                            <?php

                            $accountInitials = '';


                            if (
                                !empty(
                                    $account[
                                        'first_name'
                                    ]
                                )
                            ) {

                                $accountInitials .=
                                    strtoupper(
                                        substr(
                                            $account[
                                                'first_name'
                                            ],
                                            0,
                                            1
                                        )
                                    );
                            }


                            if (
                                !empty(
                                    $account[
                                        'last_name'
                                    ]
                                )
                            ) {

                                $accountInitials .=
                                    strtoupper(
                                        substr(
                                            $account[
                                                'last_name'
                                            ],
                                            0,
                                            1
                                        )
                                    );
                            }


                            $accountName =
                                trim(
                                    $account[
                                        'first_name'
                                    ]
                                    .
                                    ' '
                                    .
                                    (
                                        $account[
                                            'middle_name'
                                        ]
                                        ?
                                        $account[
                                            'middle_name'
                                        ]
                                        .
                                        ' '
                                        :
                                        ''
                                    )
                                    .
                                    $account[
                                        'last_name'
                                    ]
                                );

                            ?>



                            <tr>



                                <!-- USER -->

                                <td>


                                    <div
                                        class="
                                            d-flex
                                            align-items-center
                                            gap-3
                                        "
                                    >


                                        <div
                                            class="user-avatar"
                                            style="
                                                width:
                                                40px;

                                                height:
                                                40px;

                                                min-width:
                                                40px;
                                            "
                                        >

                                            <?= e(
                                                $accountInitials
                                            ); ?>

                                        </div>



                                        <div>


                                            <strong
                                                class="d-block"
                                            >

                                                <?= e(
                                                    $accountName
                                                ); ?>

                                            </strong>


                                            <small
                                                class="text-muted"
                                            >

                                                <?= e(
                                                    $account[
                                                        'email'
                                                    ]
                                                ); ?>

                                            </small>


                                        </div>


                                    </div>


                                </td>



                                <!-- USERNAME -->

                                <td>


                                    <span>

                                        <?= e(
                                            $account[
                                                'username'
                                            ]
                                        ); ?>

                                    </span>


                                </td>



                                <!-- ROLE -->

                                <td>



                                    <?php if (
                                        $account[
                                            'role'
                                        ]
                                        ===
                                        'admin'
                                    ): ?>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:
                                                #e5f5e9;

                                                color:
                                                #08752c;

                                                padding:
                                                7px 10px;
                                            "
                                        >

                                            Administrator

                                        </span>



                                    <?php elseif (
                                        $account[
                                            'role'
                                        ]
                                        ===
                                        'faculty'
                                    ): ?>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:
                                                #fff4c8;

                                                color:
                                                #725f00;

                                                padding:
                                                7px 10px;
                                            "
                                        >

                                            Faculty /
                                            Researcher

                                        </span>



                                    <?php elseif (
                                        $account[
                                            'role'
                                        ]
                                        ===
                                        'rps'
                                    ): ?>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:
                                                #e8f5e9;

                                                color:
                                                #176b35;

                                                padding:
                                                7px 10px;
                                            "
                                        >

                                            RPS

                                        </span>



                                    <?php elseif (
                                        $account[
                                            'role'
                                        ]
                                        ===
                                        'extension_coordinator'
                                    ): ?>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:
                                                #e8efff;

                                                color:
                                                #35588b;

                                                padding:
                                                7px 10px;
                                            "
                                        >

                                            Extension
                                            Coordinator

                                        </span>



                                    <?php else: ?>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:
                                                #eeeeee;

                                                color:
                                                #555555;

                                                padding:
                                                7px 10px;
                                            "
                                        >

                                            Unknown Role

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- COLLEGE -->

                                <td>


                                    <?php if (
                                        !empty(
                                            $account[
                                                'college_name'
                                            ]
                                        )
                                    ): ?>


                                        <strong
                                            class="d-block"
                                            style="
                                                font-size:
                                                12px;
                                            "
                                        >

                                            <?= e(
                                                $account[
                                                    'college_name'
                                                ]
                                            ); ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $account[
                                                    'department_name'
                                                ]
                                            )
                                        ): ?>


                                            <small
                                                class="text-muted"
                                            >

                                                <?= e(
                                                    $account[
                                                        'department_name'
                                                    ]
                                                ); ?>

                                            </small>


                                        <?php else: ?>


                                            <small
                                                class="text-muted"
                                            >

                                                No department

                                            </small>


                                        <?php endif; ?>



                                    <?php else: ?>


                                        <span
                                            class="text-muted"
                                        >

                                            Not assigned

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- STATUS -->

                                <td>



                                    <?php if (
                                        $account[
                                            'status'
                                        ]
                                        ===
                                        'Active'
                                    ): ?>


                                        <span
                                            class="
                                                status-badge
                                                status-completed
                                            "
                                        >

                                            Active

                                        </span>



                                    <?php else: ?>


                                        <span
                                            class="
                                                status-badge
                                                status-revision
                                            "
                                        >

                                            Inactive

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- LAST LOGIN -->

                                <td>


                                    <?php if (
                                        !empty(
                                            $account[
                                                'last_login'
                                            ]
                                        )
                                    ): ?>


                                        <?= e(
                                            date(
                                                'M d, Y',
                                                strtotime(
                                                    $account[
                                                        'last_login'
                                                    ]
                                                )
                                            )
                                        ); ?>


                                        <div
                                            class="
                                                text-muted
                                                small
                                            "
                                        >

                                            <?= e(
                                                date(
                                                    'h:i A',
                                                    strtotime(
                                                        $account[
                                                            'last_login'
                                                        ]
                                                    )
                                                )
                                            ); ?>

                                        </div>



                                    <?php else: ?>


                                        <span
                                            class="text-muted"
                                        >

                                            Never

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- ACTIONS -->

                                <td
                                    class="text-end"
                                >


                                    <div
                                        class="
                                            d-inline-flex
                                            gap-1
                                        "
                                    >



                                        <!-- EDIT USER -->

                                        <a
                                            href="<?= BASE_URL; ?>/admin/users/edit.php?id=<?= (int)$account['user_id']; ?>"
                                            class="
                                                btn
                                                btn-sm
                                                btn-outline-asu
                                            "
                                            title="Edit User"
                                        >

                                            <i
                                                class="bi bi-pencil"
                                            ></i>


                                            <span
                                                class="
                                                    d-none
                                                    d-xl-inline
                                                    ms-1
                                                "
                                            >

                                                Edit

                                            </span>


                                        </a>



                                        <!-- STATUS BUTTON -->

                                        <?php if (
                                            (int)$account[
                                                'user_id'
                                            ]
                                            !==
                                            (int)$_SESSION[
                                                'user_id'
                                            ]
                                        ): ?>



                                            <form
                                                method="POST"
                                                action="<?= BASE_URL; ?>/admin/users/toggle_status.php"
                                                class="d-inline"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e(
                                                        generateCsrfToken()
                                                    ); ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= (int)$account['user_id']; ?>"
                                                >



                                                <?php if (
                                                    $account[
                                                        'status'
                                                    ]
                                                    ===
                                                    'Active'
                                                ): ?>


                                                    <button
                                                        type="submit"
                                                        class="
                                                            btn
                                                            btn-sm
                                                            btn-outline-danger
                                                        "
                                                        title="Deactivate Account"
                                                    >

                                                        <i
                                                            class="bi bi-person-x"
                                                        ></i>

                                                    </button>



                                                <?php else: ?>


                                                    <button
                                                        type="submit"
                                                        class="
                                                            btn
                                                            btn-sm
                                                            btn-outline-success
                                                        "
                                                        title="Activate Account"
                                                    >

                                                        <i
                                                            class="bi bi-person-check"
                                                        ></i>

                                                    </button>


                                                <?php endif; ?>


                                            </form>



                                        <?php else: ?>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-light
                                                "
                                                disabled
                                                title="Current logged-in account"
                                            >

                                                <i
                                                    class="bi bi-lock"
                                                ></i>

                                            </button>


                                        <?php endif; ?>


                                    </div>


                                </td>


                            </tr>



                        <?php endforeach; ?>



                    <?php endif; ?>



                    </tbody>


                </table>


            </div>


        </section>
</main>


</div>




<style>
/* =========================================================
   USER TABLE ACTION BUTTONS — SAME SIZE
========================================================= */

.asu-table td.text-end .d-inline-flex {
    align-items: center !important;
    gap: 7px !important;
}

/* Edit / Activate / Deactivate / Lock buttons */
.asu-table td.text-end .btn-sm {
    width: 58px !important;
    min-width: 58px !important;
    max-width: 58px !important;

    height: 48px !important;
    min-height: 48px !important;
    max-height: 48px !important;

    padding: 0 !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;

    border-radius: 11px !important;

    font-size: 10px !important;
    line-height: 1 !important;
    text-align: center !important;

    box-sizing: border-box !important;
}

/* Keep Edit content neatly centered */
.asu-table td.text-end .btn-sm span {
    margin-left: 0 !important;
    line-height: 1 !important;
}

/* Icons use the same visual size */
.asu-table td.text-end .btn-sm i {
    margin: 0 !important;
    font-size: 14px !important;
    line-height: 1 !important;
}

/* Edit button can stack icon + text neatly */
.asu-table td.text-end a.btn-sm {
    flex-direction: column !important;
}

/* Status / lock buttons remain centered */
.asu-table td.text-end button.btn-sm {
    flex-direction: column !important;
}

/* Preserve same dimensions even when disabled */
.asu-table td.text-end .btn-sm:disabled,
.asu-table td.text-end .btn-sm.disabled {
    width: 58px !important;
    min-width: 58px !important;
    height: 48px !important;
    min-height: 48px !important;
}
</style>

<?php

require __DIR__ . '/../../includes/footer.php';

?>

<style>
/* =========================================================
   USER MANAGEMENT — MATCH ADMIN DASHBOARD VISUAL STYLE
========================================================= */

/* Overall page background */
.main-page {
    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.page-content {
    padding: 36px 34px 34px !important;
    background:
        radial-gradient(circle at 90% 5%, rgba(255, 213, 31, .10), transparent 25%),
        radial-gradient(circle at 8% 7%, rgba(0, 122, 51, .07), transparent 27%),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/* =========================================================
   TOPBAR — SAME FEEL AS ADMIN DASHBOARD
========================================================= */

.asu-topbar {
    min-height: 76px !important;
    padding: 0 30px !important;
    border-bottom: 1px solid rgba(0, 122, 51, .12) !important;
    background: rgba(255, 255, 255, .96) !important;
    box-shadow: 0 10px 26px rgba(16, 24, 16, .035) !important;
    backdrop-filter: blur(10px);
}

.system-name {
    position: relative;
    padding-left: 19px;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em !important;
}

.system-name::before {
    content: "";
    position: absolute;
    left: 0;
    top: 50%;
    width: 3px;
    height: 38px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: #007a33;
}

.topbar-user-text strong {
    font-size: 15px !important;
    font-weight: 800 !important;
}

.topbar-user-text span {
    font-size: 12px !important;
}

.asu-topbar .user-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
}

/* =========================================================
   USER MANAGEMENT HERO
========================================================= */

.page-heading {
    position: relative;
    overflow: hidden;
    min-height: 150px;
    margin: 0 0 28px !important;
    padding: 30px 34px !important;
    border-radius: 24px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 24px !important;
    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #258a36 100%
        ) !important;
    box-shadow: 0 18px 42px rgba(0, 90, 40, .16) !important;
}

.page-heading::after {
    content: "";
    position: absolute;
    right: -75px;
    top: -90px;
    width: 500px;
    height: 330px;
    transform: rotate(-9deg);
    border-radius: 42px;
    background:
        linear-gradient(
            135deg,
            rgba(255, 213, 31, .16),
            rgba(255,255,255,.035)
        );
    pointer-events: none;
}

.page-heading > * {
    position: relative;
    z-index: 2;
}

.page-heading h1 {
    margin: 0 !important;
    color: #ffffff !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: clamp(34px, 3vw, 44px) !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.04em !important;
}

/* Add User button inside hero */
.page-heading .btn-asu {
    min-height: 48px !important;
    padding: 0 20px !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 12px !important;
    font-weight: 850 !important;
    box-shadow: 0 12px 26px rgba(255, 213, 31, .23) !important;
}

.page-heading .btn-asu:hover {
    transform: translateY(-2px);
    background: #ffdb36 !important;
    color: #003f1a !important;
}

/* =========================================================
   USER STATS — SAME CARD FAMILY AS DASHBOARD
========================================================= */

.stats-row {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 18px !important;
    margin: 0 0 24px !important;
}

.stat-card {
    position: relative !important;
    overflow: hidden !important;
    min-width: 0 !important;
    min-height: 160px !important;
    padding: 20px 18px 26px !important;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 22px !important;
    background: rgba(255, 255, 255, .95) !important;
    display: grid !important;
    grid-template-columns: 62px minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 17px !important;
    box-shadow: 0 14px 35px rgba(16, 24, 16, .055) !important;
}

.stat-card::after {
    content: "";
    position: absolute;
    left: -5%;
    right: -5%;
    bottom: -40px;
    height: 66px;
    border-radius: 50% 50% 0 0;
    opacity: .22;
    pointer-events: none;
}

.stat-card:nth-child(odd)::after {
    background: #007a33;
}

.stat-card:nth-child(even)::after {
    background: #ffd51f;
    opacity: .34;
}

.stat-icon {
    position: relative;
    z-index: 2;
    width: 62px !important;
    height: 62px !important;
    min-width: 62px !important;
    border-radius: 18px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 27px !important;
}

.stat-content {
    position: relative;
    z-index: 2;
    min-width: 0;
}

.stat-content strong {
    display: block;
    margin: 0 0 7px !important;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 32px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.stat-content span {
    display: block;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 14px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* =========================================================
   FILTER PANEL
========================================================= */

.filter-card {
    margin-bottom: 22px !important;
    padding: 20px !important;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 20px !important;
    background: rgba(255, 255, 255, .94) !important;
    box-shadow: 0 14px 35px rgba(16, 24, 16, .05) !important;
}

.filter-card .form-control,
.filter-card .form-select,
.filter-card .input-group-text {
    min-height: 46px !important;
    border-color: rgba(0, 122, 51, .14) !important;
    background: #ffffff !important;
    font-size: 12px !important;
}

.filter-card .input-group-text {
    color: #007a33 !important;
}

.filter-card .btn-asu {
    min-height: 46px !important;
    border-radius: 12px !important;
}

/* =========================================================
   USER ACCOUNTS PANEL
========================================================= */

.asu-panel {
    overflow: hidden !important;
    border: 1px solid rgba(0, 122, 51, .13) !important;
    border-radius: 22px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 16px 38px rgba(16, 24, 16, .06) !important;
}

.panel-header {
    min-height: 74px !important;
    padding: 16px 22px !important;
    border-bottom: 1px solid rgba(0, 122, 51, .09) !important;
    background: rgba(255,255,255,.84) !important;
}

.panel-title {
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 17px !important;
    font-weight: 700 !important;
}

.panel-title i {
    color: #007a33 !important;
}

.asu-table {
    margin-bottom: 0 !important;
}

.asu-table thead th {
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-bottom-color: rgba(0, 122, 51, .12) !important;
    color: #35423a !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: .025em !important;
    background: #f8fbf8 !important;
}

.asu-table tbody td {
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-color: rgba(0, 122, 51, .07) !important;
    font-size: 12px !important;
}

.asu-table tbody tr:hover td {
    background: rgba(0, 122, 51, .025) !important;
}

/* Table avatars should stay compact, not use topbar avatar size */
.asu-table .user-avatar {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    border-radius: 50% !important;
    font-size: 12px !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1320px) {
    .stats-row {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 900px) {
    .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .page-heading {
        min-height: 132px;
        padding: 26px 24px !important;
    }
}

@media (max-width: 760px) {
    .page-content {
        padding: 18px 14px 26px !important;
    }

    .asu-topbar {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }

    .system-name {
        font-size: 14px !important;
    }

    .page-heading {
        min-height: auto;
        align-items: flex-start !important;
        flex-direction: column !important;
        padding: 24px 20px !important;
        border-radius: 18px !important;
    }

    .page-heading h1 {
        font-size: 31px !important;
    }

    .page-heading .btn-asu {
        width: 100%;
    }
}

@media (max-width: 580px) {
    .stats-row {
        grid-template-columns: 1fr !important;
    }

    .stat-card {
        min-height: 138px !important;
    }
}
</style>


<style>
/* =========================================================
   USER MANAGEMENT — FINAL UNIFORM ADMIN INTERFACE
========================================================= */

/* -------- Global typography -------- */
.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
    font-family: "Century Gothic", sans-serif !important;
}

/* -------- Topbar: match Admin Dashboard hierarchy -------- */
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
    background: #007a33;
}

.system-brand-copy strong {
    display: block;
    color: #101810;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -.015em;
}

.system-brand-copy span {
    display: block;
    margin-top: 4px;
    color: #657068;
    font-size: 13px;
    font-weight: 400;
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
    font-size: 15px !important;
}

/* -------- Page background -------- */
.page-content {
    padding: 24px 28px 28px !important;
}

/* -------- User Management hero: smaller and cleaner -------- */
.page-heading {
    min-height: 126px !important;
    margin-bottom: 20px !important;
    padding: 24px 30px !important;
    border-radius: 20px !important;
}

.page-heading h1 {
    margin: 0 !important;
    font-size: 31px !important;
    line-height: 1.05 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.page-heading p {
    display: none !important;
}

/* Add User button: exact vertical + horizontal centering */
.page-heading .btn-asu {
    min-width: 126px !important;
    height: 44px !important;
    min-height: 44px !important;
    padding: 0 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    line-height: 1 !important;
    border-radius: 12px !important;
    font-size: 11px !important;
    font-weight: 800 !important;
}

.page-heading .btn-asu i {
    margin: 0 !important;
    line-height: 1 !important;
    font-size: 14px !important;
}

/* -------- Stats: compact like Admin Dashboard -------- */
.stats-row {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 14px !important;
    margin-bottom: 20px !important;
}

.stat-card {
    min-height: 132px !important;
    padding: 16px 15px 20px !important;
    grid-template-columns: 52px minmax(0, 1fr) !important;
    gap: 13px !important;
    border-radius: 18px !important;
}

.stat-card::after {
    bottom: -36px !important;
    height: 54px !important;
}

.stat-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    border-radius: 15px !important;
    font-size: 22px !important;
}

.stat-content strong {
    margin-bottom: 6px !important;
    font-size: 28px !important;
    line-height: 1 !important;
}

.stat-content span {
    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* -------- Filter panel -------- */
.filter-card {
    margin-bottom: 18px !important;
    padding: 16px 18px !important;
    border-radius: 17px !important;
}

.filter-card .form-control,
.filter-card .form-select,
.filter-card .input-group-text,
.filter-card .btn-asu {
    min-height: 42px !important;
    height: 42px !important;
    font-size: 11px !important;
}

/* -------- User table panel -------- */
.asu-panel {
    border-radius: 18px !important;
}

.panel-header {
    min-height: 66px !important;
    padding: 14px 18px !important;
}

.panel-title {
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
}

.asu-table thead th {
    padding-top: 12px !important;
    padding-bottom: 12px !important;
    font-size: 10.5px !important;
}

.asu-table tbody td {
    padding-top: 12px !important;
    padding-bottom: 12px !important;
    font-size: 11.5px !important;
}

.asu-table .user-avatar {
    width: 38px !important;
    height: 38px !important;
    min-width: 38px !important;
    font-size: 11px !important;
}

/* Keep action buttons compact */
.asu-table .btn-sm {
    min-height: 32px !important;
    padding: 5px 9px !important;
    font-size: 10px !important;
}

/* -------- Responsive -------- */
@media (max-width: 1250px) {
    .stats-row {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 900px) {
    .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .system-brand-copy strong {
        font-size: 15px !important;
    }

    .system-brand-copy span {
        font-size: 11px !important;
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

    .page-content {
        padding: 18px 14px 24px !important;
    }

    .page-heading {
        min-height: auto !important;
        padding: 22px 18px !important;
    }

    .page-heading h1 {
        font-size: 27px !important;
    }

    .page-heading .btn-asu {
        width: 100% !important;
    }
}

@media (max-width: 580px) {
    .stats-row {
        grid-template-columns: 1fr !important;
    }

    .stat-card {
        min-height: 122px !important;
    }
}
</style>


<style>
/* =========================================================
   COMPACT STATUS / FLASH NOTIFICATIONS
========================================================= */

.page-content > .alert,
.main-page .page-content .alert {
    width: fit-content !important;
    max-width: min(100%, 420px) !important;

    min-height: 0 !important;

    margin: 0 0 12px 0 !important;
    padding: 8px 34px 8px 12px !important;

    border-radius: 10px !important;

    font-size: 10.5px !important;
    line-height: 1.35 !important;
    font-weight: 600 !important;

    box-shadow: 0 6px 16px rgba(16,24,16,.05) !important;
}

/* Make success alerts subtle and compact */
.page-content > .alert-success,
.main-page .page-content .alert-success {
    border: 1px solid rgba(0,122,51,.18) !important;
    background: #f1f8f3 !important;
    color: #176332 !important;
}

/* Smaller close button */
.page-content .alert .btn-close {
    width: 28px !important;
    height: 28px !important;
    padding: 0 !important;

    top: 50% !important;
    right: 4px !important;
    transform: translateY(-50%) scale(.65) !important;
}

/* Mobile */
@media (max-width: 640px) {
    .page-content > .alert,
    .main-page .page-content .alert {
        width: 100% !important;
        max-width: 100% !important;
        font-size: 10px !important;
        padding: 8px 32px 8px 11px !important;
    }
}
</style>


<style>
/* =========================================================
   FULL-WIDTH STATUS NOTIFICATIONS
========================================================= */

.main-page .page-content .alert {
    width: 100% !important;
    max-width: none !important;

    min-height: 44px !important;

    margin: 0 0 14px 0 !important;
    padding: 11px 42px 11px 14px !important;

    display: flex !important;
    align-items: center !important;

    border-radius: 11px !important;

    font-size: 11.5px !important;
    line-height: 1.4 !important;
    font-weight: 650 !important;

    box-shadow: 0 7px 18px rgba(16,24,16,.045) !important;
}

/* ACTIVE — green */
.main-page .page-content .alert.status-flash-active {
    border: 1px solid rgba(0,122,51,.20) !important;
    background: #f2f8f3 !important;
    color: #08752c !important;
}

/* INACTIVE — gold / orange */
.main-page .page-content .alert.status-flash-inactive {
    border: 1px solid rgba(201,143,0,.24) !important;
    background: #fff9e8 !important;
    color: #b77900 !important;
}

/* Keep generic flash messages clean */
.main-page .page-content .alert:not(.status-flash-active):not(.status-flash-inactive) {
    border-radius: 11px !important;
}

/* Close button — still compact but easier to click */
.main-page .page-content .alert .btn-close {
    width: 34px !important;
    height: 34px !important;
    padding: 0 !important;

    top: 50% !important;
    right: 6px !important;

    transform: translateY(-50%) scale(.72) !important;
}

/* Mobile */
@media (max-width: 640px) {
    .main-page .page-content .alert {
        min-height: 42px !important;
        padding: 10px 38px 10px 12px !important;
        font-size: 11px !important;
    }
}
</style>
