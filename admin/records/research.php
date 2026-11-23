<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$user = currentUser();

$pageTitle = 'Research Records';
$activePage = 'admin_research_records';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$year = trim($_GET['year'] ?? '');

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

$sql = "
    SELECT
        rp.research_id,
        rp.manuscript_no,
        rp.research_title,
        rp.research_category,
        rp.research_type,
        rp.status,
        rp.workflow_status,
        rp.workflow_stage,
        rp.progress_percentage,
        rp.submitted_at,
        rp.completed_at,
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
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            rp.research_title LIKE ?
            OR rp.manuscript_no LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params = array_merge(
        $params,
        [$term, $term, $term, $term]
    );
}

if (in_array($status, $validStatuses, true)) {
    $sql .= " AND rp.status = ?";
    $params[] = $status;
}

if ($year !== '' && ctype_digit($year)) {
    $sql .= "
        AND YEAR(
            COALESCE(
                rp.submitted_at,
                rp.created_at
            )
        ) = ?
    ";

    $params[] = (int)$year;
}

$sql .= "
    ORDER BY
        COALESCE(
            rp.workflow_updated_at,
            rp.updated_at,
            rp.created_at
        ) DESC,
        rp.research_id DESC
    LIMIT 300
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$years = $pdo->query("
    SELECT DISTINCT
        YEAR(
            COALESCE(
                submitted_at,
                created_at
            )
        ) AS y
    FROM research_proposals
    ORDER BY y DESC
")->fetchAll(PDO::FETCH_COLUMN);

$initials = '';

if (!empty($user['first_name'])) {
    $initials .= strtoupper(
        substr($user['first_name'], 0, 1)
    );
}

if (!empty($user['last_name'])) {
    $initials .= strtoupper(
        substr($user['last_name'], 0, 1)
    );
}

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
}

/* =========================================================
   GLOBAL / PAGE
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
        ) !important;
}

.page-content {
    min-height: calc(100vh - 76px);
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

.records-page {
    max-width: 1320px;
    margin: 0 auto;
}

/* =========================================================
   TOPBAR — SAME AS OTHER ADMIN PAGES
========================================================= */

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
    border-radius: 50% !important;
    background: var(--asu-yellow) !important;
    color: var(--asu-green-deep) !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}

/* =========================================================
   HERO — SAME STYLE AS USER MANAGEMENT
========================================================= */

.records-heading {
    position: relative;
    overflow: hidden;

    min-height: 150px;
    margin: 0 0 20px;
    padding: 30px 34px;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;

    border-radius: 24px;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        );

    box-shadow: 0 18px 42px rgba(0,90,40,.16);
}

.records-heading::after {
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
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        );

    pointer-events: none;
}

.records-heading > * {
    position: relative;
    z-index: 2;
}

.records-heading h1 {
    margin: 0 !important;

    color: #ffffff !important;

    font-size: clamp(34px, 3vw, 44px) !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.04em !important;
}

.records-heading p {
    display: none;
}

/* View only chip inside hero */
.readonly-chip {
    min-width: 116px;
    height: 44px;
    padding: 0 15px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    border-radius: 12px;

    background: #ffd51f;
    color: #003f1a;

    font-size: 10.5px;
    font-weight: 800;
    line-height: 1;

    box-shadow: 0 12px 26px rgba(255,213,31,.22);
}

.readonly-chip i {
    margin: 0 !important;
    font-size: 13px;
}

/* =========================================================
   FILTER PANEL
========================================================= */

.filter-row {
    display: grid;
    grid-template-columns:
        minmax(260px, 1fr)
        190px
        150px
        auto;

    gap: 10px;

    margin-bottom: 18px;
    padding: 16px 18px;

    border: 1px solid rgba(0,122,51,.13);
    border-radius: 17px;

    background: rgba(255,255,255,.94);

    box-shadow: 0 14px 34px rgba(16,24,16,.05);
}

.filter-row .form-control,
.filter-row .form-select {
    min-height: 42px;
    height: 42px;

    padding: 8px 12px;

    border: 1px solid #d7e0d8;
    border-radius: 10px;

    background: #ffffff;
    color: #263229;

    font-size: 11px;

    box-shadow: none;
}

.filter-row .form-control:focus,
.filter-row .form-select:focus {
    border-color: #76ac82;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07);
}

.filter-row .form-control::placeholder {
    color: #89928c;
}

.filter-row .d-flex {
    gap: 7px !important;
}

.filter-row .btn {
    width: 42px;
    min-width: 42px;
    height: 42px;
    min-height: 42px;

    padding: 0;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px !important;

    font-size: 13px;

    box-shadow: none !important;
}

.filter-row .btn i {
    margin: 0 !important;
    line-height: 1 !important;
}

.filter-row .btn-asu {
    border: 0 !important;
    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;

    color: #ffffff !important;
}

.filter-row .btn-outline-asu {
    border: 1px solid rgba(0,122,51,.24) !important;
    background: #ffffff !important;
    color: var(--asu-green) !important;
}

.filter-row .btn-outline-asu:hover {
    background: #f2f8f3 !important;
}

/* =========================================================
   RECORDS CARD / TABLE
========================================================= */

.records-card {
    overflow: hidden;

    border: 1px solid rgba(0,122,51,.13);
    border-radius: 18px;

    background: rgba(255,255,255,.96);

    box-shadow: 0 16px 38px rgba(16,24,16,.06);
}

.table-wrap {
    overflow: auto;
}

.records-table {
    width: 100%;
    min-width: 1080px;

    margin: 0;
    border-collapse: collapse;

    background: transparent;
}

.records-table th {
    padding: 12px 14px;

    border-bottom: 1px solid rgba(0,122,51,.12);

    background: #f8fbf8;

    color: #35423a;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .025em;
    text-transform: uppercase;

    white-space: nowrap;
}

.records-table td {
    padding: 12px 14px;

    border-bottom: 1px solid rgba(0,122,51,.07);

    color: #263229;

    font-size: 11px;
    vertical-align: middle;
}

.records-table tbody tr:hover td {
    background: rgba(0,122,51,.025);
}

.records-table tr:last-child td {
    border-bottom: 0;
}

/* =========================================================
   TITLE / META
========================================================= */

.title-cell {
    max-width: 360px;
}

.title-cell strong {
    display: block;

    color: #1f2c23;

    font-size: 11.5px;
    line-height: 1.35;
    font-weight: 700;
}

.title-cell small {
    display: block;

    margin-top: 4px;

    color: #848c86;

    font-size: 9.5px;
}

/* =========================================================
   STATUS PILLS
========================================================= */

.status-pill {
    min-width: 74px;
    min-height: 26px;
    padding: 5px 9px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 999px;

    background: #f2f7f3;
    color: #08752c;

    font-size: 9px;
    font-weight: 800;
    line-height: 1;

    white-space: nowrap;
}

.status-pill.Completed,
.status-pill.Approved {
    background: #e8f7ec;
    color: #08752c;
}

.status-pill.Ongoing,
.status-pill.Submitted,
.status-pill.Under\ Review {
    background: #eef5ff;
    color: #355c90;
}

.status-pill.For\ Revision {
    background: #fff5d8;
    color: #a56d00;
}

.status-pill.Rejected,
.status-pill.Archived {
    background: #f1f1f1;
    color: #666;
}

.status-pill.Draft {
    background: #f4f4f4;
    color: #666;
}

/* =========================================================
   VIEW RECORD LINK
========================================================= */

.record-link {
    min-height: 34px;
    padding: 0 10px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;

    border: 1px solid rgba(0,122,51,.22);
    border-radius: 9px;

    background: #ffffff;
    color: var(--asu-green);

    font-size: 9.5px;
    font-weight: 800;

    text-decoration: none;
    white-space: nowrap;
}

.record-link:hover {
    background: #f2f8f3;
    color: var(--asu-green-dark);
}

.record-link i {
    margin: 0 !important;
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 44px;

    text-align: center;

    color: #879089;

    font-size: 11px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 980px) {
    .filter-row {
        grid-template-columns:
            minmax(220px, 1fr)
            1fr
            1fr;
    }

    .filter-row .d-flex {
        grid-column: 1 / -1;
        justify-content: flex-end;
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

    .records-heading {
        min-height: auto;
        padding: 24px 20px;
        border-radius: 18px;
        flex-direction: column;
        align-items: flex-start;
    }

    .records-heading h1 {
        font-size: 31px !important;
    }

    .readonly-chip {
        width: 100%;
    }

    .filter-row {
        grid-template-columns: 1fr;
        padding: 14px;
    }

    .filter-row .d-flex {
        grid-column: auto;
        justify-content: stretch;
    }

    .filter-row .btn {
        flex: 1 1 auto;
        width: auto;
    }
}
</style>

<div class="main-page">

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
                <strong>
                    Research &amp; Extension Management System
                </strong>
                <span>
                    Aklan State University
                </span>
            </div>

        </div>

        <div class="topbar-user">

            <div class="topbar-user-text">
                <strong>
                    <?= e(fullName($user)); ?>
                </strong>
                <span>
                    System Administrator
                </span>
            </div>

            <div class="user-avatar">
                <?= e($initials ?: 'A'); ?>
            </div>

        </div>

    </header>

    <main class="page-content">

        <div class="records-page">

            <div class="records-heading">

                <div>
                    <h1>
                        Research Records
                    </h1>

                    <p>
                        Institutional research records for administrative oversight and reporting.
                    </p>
                </div>
</div>

            <form
                class="filter-row"
                method="get"
            >

                <input
                    class="form-control"
                    name="search"
                    value="<?= e($search); ?>"
                    placeholder="Search title, manuscript or researcher"
                >

                <select
                    class="form-select"
                    name="status"
                >
                    <option value="">
                        All statuses
                    </option>

                    <?php foreach ($validStatuses as $s): ?>

                        <option
                            value="<?= e($s); ?>"
                            <?= $status === $s ? 'selected' : ''; ?>
                        >
                            <?= e($s); ?>
                        </option>

                    <?php endforeach; ?>
                </select>

                <select
                    class="form-select"
                    name="year"
                >
                    <option value="">
                        All years
                    </option>

                    <?php foreach ($years as $y): ?>

                        <option
                            value="<?= (int)$y; ?>"
                            <?= $year === (string)$y ? 'selected' : ''; ?>
                        >
                            <?= (int)$y; ?>
                        </option>

                    <?php endforeach; ?>
                </select>

                <div class="d-flex">

                    <button
                        class="btn btn-asu"
                        type="submit"
                        title="Search"
                    >
                        <i class="bi bi-search"></i>
                    </button>

                    <a
                        class="btn btn-outline-asu"
                        href="<?= BASE_URL; ?>/admin/records/research.php"
                        title="Reset Filters"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>

                </div>

            </form>

            <section class="records-card">

                <div class="table-wrap">

                    <table class="records-table">

                        <thead>
                            <tr>
                                <th>Manuscript</th>
                                <th>Research</th>
                                <th>Researcher</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Workflow</th>
                                <th>Progress</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($records): ?>

                            <?php foreach ($records as $r): ?>

                                <?php
                                $researcher = trim(
                                    ($r['first_name'] ?? '')
                                    . ' '
                                    . ($r['middle_name'] ?? '')
                                    . ' '
                                    . ($r['last_name'] ?? '')
                                );
                                ?>

                                <tr>

                                    <td>
                                        <?= e(
                                            $r['manuscript_no']
                                            ?: '—'
                                        ); ?>
                                    </td>

                                    <td class="title-cell">

                                        <strong>
                                            <?= e(
                                                $r['research_title']
                                            ); ?>
                                        </strong>

                                        <small>
                                            <?= e(
                                                trim(
                                                    (
                                                        $r['research_category']
                                                        ?: 'Uncategorized'
                                                    )
                                                    . ' · '
                                                    . (
                                                        $r['research_type']
                                                        ?: 'Unspecified'
                                                    )
                                                )
                                            ); ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= e($researcher); ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $r['college_name']
                                            ?: '—'
                                        ); ?>

                                        <br>

                                        <small class="text-muted">
                                            <?= e(
                                                $r['department_name']
                                                ?: ''
                                            ); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span
                                            class="status-pill <?= e($r['status']); ?>"
                                        >
                                            <?= e($r['status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e($r['workflow_status']); ?>

                                        <br>

                                        <small class="text-muted">
                                            <?= e($r['workflow_stage']); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int)$r['progress_percentage']
                                        ); ?>%
                                    </td>

                                    <td>
                                        <a
                                            class="record-link"
                                            href="<?= BASE_URL; ?>/admin/records/research_view.php?id=<?= (int)$r['research_id']; ?>"
                                        >
                                            View Record
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        No research records match the selected filters.
                                    </div>
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </main>

</div>

<?php
require __DIR__ . '/../../includes/footer.php';
?>


<style>
/* =========================================================
   RESEARCH RECORDS — SMALL RECTANGLE HERO
========================================================= */

.records-heading {
    min-height: 96px !important;
    margin: 0 0 18px !important;
    padding: 20px 26px !important;
    border-radius: 18px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
}

.records-heading::after {
    right: -110px !important;
    top: -120px !important;
    width: 430px !important;
    height: 280px !important;
}

.records-heading h1 {
    font-size: 30px !important;
    line-height: 1 !important;
    letter-spacing: -.03em !important;
}

/* Remove any remaining View only styling/space */
.readonly-chip {
    display: none !important;
}

@media (max-width: 760px) {
    .records-heading {
        min-height: 82px !important;
        padding: 18px 18px !important;
        border-radius: 16px !important;
    }

    .records-heading h1 {
        font-size: 27px !important;
    }
}
</style>
