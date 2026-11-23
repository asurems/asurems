<?php

require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$user = currentUser();

$pageTitle = 'Backup & Maintenance';
$activePage = 'maintenance';

$dbName =
    (string)$pdo
        ->query(
            'SELECT DATABASE()'
        )
        ->fetchColumn();

$dbVersion =
    (string)$pdo
        ->query(
            'SELECT VERSION()'
        )
        ->fetchColumn();

$tableRows =
    $pdo
        ->query(
            'SHOW TABLE STATUS'
        )
        ->fetchAll(
            PDO::FETCH_ASSOC
        );

$tableCount =
    count(
        $tableRows
    );

$dbBytes = 0;

foreach ($tableRows as $row) {

    $dbBytes +=
        (int)($row['Data_length'] ?? 0)
        +
        (int)($row['Index_length'] ?? 0);
}

function adminFormatBytes(
    int $bytes
): string {

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return number_format(
            $bytes / 1024,
            1
        ) . ' KB';
    }

    if ($bytes < 1073741824) {
        return number_format(
            $bytes / 1048576,
            1
        ) . ' MB';
    }

    return number_format(
        $bytes / 1073741824,
        2
    ) . ' GB';
}

$checks = [

    [
        'label' => 'Database connection',
        'value' => 'Connected',
        'ok' => true
    ],

    [
        'label' => 'Database',
        'value' => $dbName ?: 'Unknown',
        'ok' => $dbName !== ''
    ],

    [
        'label' => 'PHP version',
        'value' => PHP_VERSION,
        'ok' => version_compare(
            PHP_VERSION,
            '8.0.0',
            '>='
        )
    ],

    [
        'label' => 'MariaDB / MySQL',
        'value' => $dbVersion,
        'ok' => $dbVersion !== ''
    ],

    [
        'label' => 'Tables detected',
        'value' => (string)$tableCount,
        'ok' => $tableCount > 0
    ]
];

$initials = '';

if (!empty($user['first_name'])) {

    $initials .=
        strtoupper(
            substr(
                $user['first_name'],
                0,
                1
            )
        );
}

if (!empty($user['last_name'])) {

    $initials .=
        strtoupper(
            substr(
                $user['last_name'],
                0,
                1
            )
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
   GLOBAL / BACKGROUND
========================================================= */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
    font-family:
        "Century Gothic",
        sans-serif !important;
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
    min-height:
        calc(100vh - 76px);

    padding:
        24px 28px 30px !important;

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

.maintenance-page {
    max-width:
        1120px;

    margin:
        0 auto;
}

/* =========================================================
   TOPBAR
========================================================= */

.asu-topbar {
    min-height:
        76px !important;

    padding:
        0 30px !important;

    background:
        rgba(255,255,255,.97) !important;

    border-bottom:
        1px solid
        rgba(0,122,51,.12) !important;

    box-shadow:
        0 10px 26px
        rgba(16,24,16,.035) !important;
}

.topbar-left {
    display:
        flex !important;

    align-items:
        center !important;

    gap:
        14px !important;
}

.system-name {
    display:
        none !important;
}

.system-brand-copy {
    position:
        relative;

    padding-left:
        18px;

    line-height:
        1.15;
}

.system-brand-copy::before {
    content:
        "";

    position:
        absolute;

    left:
        0;

    top:
        50%;

    width:
        3px;

    height:
        38px;

    transform:
        translateY(-50%);

    border-radius:
        999px;

    background:
        var(--asu-green);
}

.system-brand-copy strong {
    display:
        block;

    color:
        var(--asu-ink);

    font-size:
        17px;

    font-weight:
        800;

    letter-spacing:
        -.015em;
}

.system-brand-copy span {
    display:
        block;

    margin-top:
        4px;

    color:
        var(--asu-muted);

    font-size:
        13px;

    font-weight:
        400;
}

.topbar-user-text strong {
    font-size:
        15px !important;

    line-height:
        1.1 !important;
}

.topbar-user-text span {
    margin-top:
        4px !important;

    font-size:
        12px !important;
}

.asu-topbar .user-avatar {
    width:
        46px !important;

    height:
        46px !important;

    min-width:
        46px !important;

    border-radius:
        50% !important;

    background:
        var(--asu-yellow) !important;

    color:
        var(--asu-green-deep) !important;

    font-size:
        15px !important;

    font-weight:
        900 !important;

    box-shadow:
        0 8px 18px
        rgba(255,213,31,.25) !important;
}

/* =========================================================
   SMALL MAIN-PAGE BANNER
========================================================= */

.maintenance-heading {
    position:
        relative;

    overflow:
        hidden;

    min-height:
        96px !important;

    margin:
        0 0 18px !important;

    padding:
        20px 26px !important;

    display:
        flex !important;

    align-items:
        center !important;

    border-radius:
        18px !important;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow:
        0 18px 42px
        rgba(0,90,40,.16) !important;
}

.maintenance-heading::after {
    content:
        "";

    position:
        absolute;

    right:
        -110px;

    top:
        -120px;

    width:
        430px;

    height:
        280px;

    transform:
        rotate(-9deg);

    border-radius:
        42px;

    background:
        linear-gradient(
            135deg,
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        );

    pointer-events:
        none;
}

.maintenance-heading > * {
    position:
        relative;

    z-index:
        2;
}

.maintenance-heading h1 {
    margin:
        0 !important;

    color:
        #ffffff !important;

    font-size:
        30px !important;

    line-height:
        1 !important;

    font-weight:
        900 !important;

    letter-spacing:
        -.03em !important;
}

.maintenance-heading p {
    display:
        none !important;
}

/* =========================================================
   TWO-CARD GRID
========================================================= */

.maintenance-grid {
    display:
        grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap:
        18px;

    align-items:
        stretch;
}

/* =========================================================
   CARDS — SAME ADMIN CARD FAMILY
========================================================= */

.maint-card {
    overflow:
        hidden;

    min-width:
        0;

    border:
        1px solid
        rgba(0,122,51,.13);

    border-radius:
        18px;

    background:
        rgba(255,255,255,.96);

    box-shadow:
        0 16px 38px
        rgba(16,24,16,.06);
}

.maint-head {
    min-height:
        66px;

    padding:
        14px 18px;

    display:
        flex;

    align-items:
        center;

    border-bottom:
        1px solid
        rgba(0,122,51,.09);

    background:
        rgba(255,255,255,.86);
}

.maint-head h2 {
    margin:
        0;

    color:
        #101810;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size:
        15px;

    font-weight:
        700;
}

.maint-body {
    padding:
        18px;
}

/* =========================================================
   SYSTEM HEALTH ROWS
========================================================= */

.health-row {
    min-height:
        52px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        14px;

    padding:
        10px 0;

    border-bottom:
        1px solid
        rgba(0,122,51,.07);
}

.health-row:last-child {
    border-bottom:
        0;
}

.health-row > span {
    color:
        #4f5b53;

    font-size:
        10.5px;

    font-weight:
        600;
}

.health-value {
    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        flex-end;

    gap:
        7px;

    color:
        #7b6250;

    font-size:
        9.5px;

    font-weight:
        800;

    text-align:
        right;
}

.health-value i {
    font-size:
        14px;
}

.health-value.ok {
    color:
        var(--asu-green);
}

/* =========================================================
   BACKUP BOX
========================================================= */

.backup-box {
    position:
        relative;

    overflow:
        hidden;

    min-height:
        100%;

    padding:
        18px;

    border:
        1px solid
        rgba(0,122,51,.11);

    border-radius:
        14px;

    background:
        #f9fcfa;
}

.backup-box::after {
    content:
        "";

    position:
        absolute;

    left:
        -5%;

    right:
        -5%;

    bottom:
        -40px;

    height:
        58px;

    border-radius:
        50% 50% 0 0;

    background:
        #ffd51f;

    opacity:
        .18;

    pointer-events:
        none;
}

.backup-box > * {
    position:
        relative;

    z-index:
        2;
}

.backup-box strong {
    display:
        block;

    color:
        #1f2b22;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size:
        13px;

    font-weight:
        700;
}

.backup-box p {
    margin:
        6px 0 15px;

    color:
        #717c74;

    font-size:
        9.5px;

    line-height:
        1.55;
}

.backup-box .btn {
    min-height:
        42px;

    height:
        42px;

    padding:
        0 14px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        6px;

    border:
        0 !important;

    border-radius:
        11px !important;

    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;

    color:
        #ffffff !important;

    font-size:
        10.5px;

    font-weight:
        800;

    box-shadow:
        0 8px 18px
        rgba(0,122,51,.14) !important;
}

.backup-box .btn i {
    margin:
        0 !important;
}

/* =========================================================
   DATABASE SIZE / TABLES
========================================================= */

.storage-stat {
    margin-top:
        17px;

    padding-top:
        15px;

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        14px;

    border-top:
        1px solid
        rgba(0,122,51,.09);
}

.storage-stat > div:first-child {
    display:
        flex;

    flex-direction:
        column;

    gap:
        4px;
}

.storage-stat strong {
    display:
        block;

    color:
        #101810;

    font-size:
        24px;

    line-height:
        1;

    font-weight:
        900;

    letter-spacing:
        -.025em;
}

.storage-stat span {
    display:
        block;

    color:
        #7c867f;

    font-size:
        9px;

    font-weight:
        600;
}

/* =========================================================
   RESTORE NOTE
========================================================= */

.maintenance-note {
    margin-top:
        18px;

    padding:
        13px 15px;

    border:
        1px solid
        rgba(191,143,0,.20);

    border-radius:
        12px;

    background:
        #fff9e8;

    color:
        #75602d;

    font-size:
        9.5px;

    line-height:
        1.6;
}

.maintenance-note strong {
    color:
        #8d6a00;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .maintenance-grid {
        grid-template-columns:
            1fr;
    }
}

@media (max-width: 760px) {

    .asu-topbar {
        padding:
            0 16px !important;
    }

    .system-brand-copy strong {
        font-size:
            13px !important;
    }

    .system-brand-copy span {
        font-size:
            10px !important;
    }

    .page-content {
        padding:
            18px 14px 24px !important;
    }

    .maintenance-heading {
        min-height:
            82px !important;

        padding:
            18px !important;

        border-radius:
            16px !important;
    }

    .maintenance-heading h1 {
        font-size:
            27px !important;
    }

    .maint-body {
        padding:
            15px;
    }

    .backup-box .btn {
        width:
            100%;
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

        <div class="maintenance-page">

            <div class="maintenance-heading">

                <div>

                    <h1>
                        Backup &amp; Maintenance
                    </h1>

                    <p>
                        Review basic system health and create a portable SQL backup of the ASU-REMS database.
                    </p>

                </div>

            </div>

            <div class="maintenance-grid">

                <section class="maint-card">

                    <div class="maint-head">

                        <h2>
                            System Health
                        </h2>

                    </div>

                    <div class="maint-body">

                        <?php foreach ($checks as $check): ?>

                            <div class="health-row">

                                <span>
                                    <?= e($check['label']); ?>
                                </span>

                                <div
                                    class="health-value <?= $check['ok'] ? 'ok' : ''; ?>"
                                >

                                    <i
                                        class="bi <?= $check['ok']
                                            ? 'bi-check-circle-fill'
                                            : 'bi-exclamation-circle-fill'; ?>"
                                    ></i>

                                    <?= e($check['value']); ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

                <section class="maint-card">

                    <div class="maint-head">

                        <h2>
                            Database Backup
                        </h2>

                    </div>

                    <div class="maint-body">

                        <div class="backup-box">

                            <strong>
                                Full SQL Backup
                            </strong>

                            <p>
                                Downloads table structures and current database records.
                                Keep backup files in a secure location and do not share
                                them publicly.
                            </p>

                            <a
                                class="btn btn-asu"
                                href="<?= BASE_URL; ?>/admin/maintenance/backup.php?token=<?= urlencode(generateCsrfToken()); ?>"
                            >
                                <i class="bi bi-download"></i>
                                Download Database Backup
                            </a>

                            <div class="storage-stat">

                                <div>

                                    <span>
                                        Estimated database size
                                    </span>

                                    <strong>
                                        <?= e(
                                            adminFormatBytes(
                                                $dbBytes
                                            )
                                        ); ?>
                                    </strong>

                                </div>

                                <div class="text-end">

                                    <span>
                                        <?= number_format(
                                            $tableCount
                                        ); ?>
                                        tables
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>

            </div>

            <div class="maintenance-note">

                <strong>
                    Restore policy:
                </strong>

                ASU-REMS does not perform automatic database restore from the browser.
                Restore backups through phpMyAdmin or the database administrator to
                reduce accidental data loss.

            </div>

        </div>

    </main>

</div>

<?php
require __DIR__ . '/../../includes/footer.php';
?>
