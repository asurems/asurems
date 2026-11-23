<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

$user = currentUser();

$pageTitle = 'Extension Record';
$activePage = 'admin_extension_records';

$id =
    (int)(
        $_GET['id']
        ?? 0
    );

if ($id <= 0) {

    setFlash(
        'danger',
        'Invalid extension record.'
    );

    redirect(
        BASE_URL
        . '/admin/records/extension.php'
    );
}

$stmt =
    $pdo->prepare("
        SELECT
            ep.*,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.email,
            c.college_name,
            d.department_name
        FROM extension_programs ep
        INNER JOIN users u
            ON u.user_id = ep.coordinator_id
        LEFT JOIN colleges c
            ON c.college_id = ep.college_id
        LEFT JOIN departments d
            ON d.department_id = ep.department_id
        WHERE ep.extension_id = ?
          AND ep.status <> 'Draft'
        LIMIT 1
    ");

$stmt->execute([
    $id
]);

$r =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );

if (!$r) {

    setFlash(
        'danger',
        'Extension record not found or is still private as a draft.'
    );

    redirect(
        BASE_URL
        . '/admin/records/extension.php'
    );
}

$docs =
    $pdo->prepare("
        SELECT
            ed.*,
            u.first_name,
            u.last_name
        FROM extension_documents ed
        LEFT JOIN users u
            ON u.user_id = ed.uploaded_by
        WHERE ed.extension_id = ?
        ORDER BY
            ed.uploaded_at DESC,
            ed.document_id DESC
    ");

$docs->execute([
    $id
]);

$documents =
    $docs->fetchAll(
        PDO::FETCH_ASSOC
    );

$hist =
    $pdo->prepare("
        SELECT
            h.*,
            u.first_name,
            u.middle_name,
            u.last_name
        FROM extension_status_history h
        LEFT JOIN users u
            ON u.user_id = h.changed_by
        WHERE h.extension_id = ?
        ORDER BY
            h.changed_at DESC,
            h.history_id DESC
        LIMIT 100
    ");

$hist->execute([
    $id
]);

$history =
    $hist->fetchAll(
        PDO::FETCH_ASSOC
    );

$coord =
    trim(
        ($r['first_name'] ?? '')
        . ' '
        . ($r['middle_name'] ?? '')
        . ' '
        . ($r['last_name'] ?? '')
    );

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
   GLOBAL
========================================================= */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a,
.main-page table {
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

.record-page {
    max-width:
        1180px;

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
   SIMPLE VIEW-PAGE HEADING
========================================================= */

.record-top {
    margin-bottom:
        18px;

    padding:
        4px 2px 0;

    display:
        flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap:
        18px;
}

.record-heading-main {
    min-width:
        0;
}

.record-top h1 {
    margin:
        0 0 7px;

    color:
        var(--asu-ink);

    font-size:
        28px;

    line-height:
        1.15;

    font-weight:
        900;

    letter-spacing:
        -.03em;
}

.record-top p {
    margin:
        0;

    color:
        #707a73;

    font-size:
        10.5px;

    line-height:
        1.5;
}

.record-back-link {
    min-height:
        34px;

    margin-bottom:
        0;

    padding:
        0 11px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        6px;

    flex-shrink:
        0;

    border:
        1px solid
        rgba(0,122,51,.20);

    border-radius:
        9px;

    background:
        rgba(255,255,255,.88);

    color:
        var(--asu-green);

    font-size:
        9.5px;

    font-weight:
        800;

    text-decoration:
        none;

    box-shadow:
        0 6px 14px
        rgba(16,24,16,.025);
}

.record-back-link:hover {
    background:
        #f2f8f3;

    color:
        var(--asu-green-dark);
}

/* =========================================================
   MAIN GRID
========================================================= */

.record-grid {
    display:
        grid;

    grid-template-columns:
        1fr;

    gap:
        17px;

    align-items:
        start;
}

/* =========================================================
   PANELS
========================================================= */

.record-panel {
    overflow:
        hidden;

    margin-bottom:
        17px;

    border:
        1px solid
        rgba(0,122,51,.13);

    border-radius:
        18px;

    background:
        rgba(255,255,255,.96);

    box-shadow:
        0 14px 34px
        rgba(16,24,16,.05);
}

.record-panel-head {
    min-height:
        62px;

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

.record-panel-head h2 {
    margin:
        0;

    color:
        #101810;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size:
        14px;

    font-weight:
        700;
}

.record-body {
    padding:
        17px 18px 18px;
}

/* =========================================================
   PROGRAM INFORMATION
========================================================= */

.info-grid {
    display:
        grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap:
        14px;
}

.info-item {
    padding:
        12px 13px;

    border:
        1px solid
        rgba(0,122,51,.08);

    border-radius:
        12px;

    background:
        #fbfcfb;
}

.info-item label {
    display:
        block;

    margin-bottom:
        5px;

    color:
        #7e8981;

    font-size:
        8.5px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .035em;
}

.info-item strong {
    display:
        block;

    color:
        #263229;

    font-size:
        10.5px;

    line-height:
        1.4;

    font-weight:
        700;
}

.info-item span {
    display:
        block;

    margin-top:
        2px;

    color:
        #778179;

    font-size:
        9px;

    line-height:
        1.4;
}

/* =========================================================
   PROGRAM CONTENT
========================================================= */

.content-item {
    margin-bottom:
        15px;

    padding-bottom:
        15px;

    border-bottom:
        1px solid
        rgba(0,122,51,.07);
}

.content-item:last-child {
    margin-bottom:
        0;

    padding-bottom:
        0;

    border-bottom:
        0;
}

.content-item label {
    display:
        block;

    margin-bottom:
        6px;

    color:
        #35423a;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size:
        10px;

    font-weight:
        700;
}

.content-item p {
    margin:
        0;

    color:
        #4d5850;

    font-size:
        10px;

    line-height:
        1.65;

    white-space:
        pre-line;
}

/* =========================================================
   DOCUMENT / HISTORY ROWS
========================================================= */

.doc-row,
.history-row {
    padding:
        12px 0;

    border-bottom:
        1px solid
        rgba(0,122,51,.07);
}

.doc-row:last-child,
.history-row:last-child {
    border-bottom:
        0;
}

.doc-row {
    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;
}

.doc-row strong,
.history-row strong {
    display:
        block;

    color:
        #2a352d;

    font-size:
        10.5px;

    line-height:
        1.4;

    font-weight:
        700;
}

.doc-row small,
.history-row small {
    display:
        block;

    margin-top:
        3px;

    color:
        #818b84;

    font-size:
        8.5px;

    line-height:
        1.4;
}

.history-row p {
    margin:
        6px 0 0;

    color:
        #657068;

    font-size:
        9px;

    line-height:
        1.55;
}

.doc-row a {
    min-height:
        34px;

    padding:
        0 10px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        5px;

    flex-shrink:
        0;

    border:
        1px solid
        rgba(0,122,51,.22);

    border-radius:
        9px;

    background:
        #ffffff;

    color:
        var(--asu-green);

    font-size:
        9px;

    font-weight:
        800;

    text-decoration:
        none;

    white-space:
        nowrap;
}

.doc-row a:hover {
    background:
        #f2f8f3;

    color:
        var(--asu-green-dark);
}

.record-body .text-muted.small {
    padding:
        12px 0;

    color:
        #818b84 !important;

    font-size:
        9.5px !important;
}


/* =========================================================
   EXTENSION RECORD — FULL-WIDTH LANDSCAPE LAYOUT
========================================================= */

/*
 * All record sections use the full content width.
 * The visual order remains:
 * Program Information
 * Program Content
 * Documents
 * Status History
 */
.record-grid > div {
    display:
        contents;
}

.record-grid > div:first-child > .record-panel:nth-child(1) {
    grid-column:
        1;

    grid-row:
        1;

    margin-bottom:
        0;
}

.record-grid > div:first-child > .record-panel:nth-child(2) {
    grid-column:
        1;

    grid-row:
        2;

    margin-bottom:
        0;
}

.record-grid > div:first-child > .record-panel:nth-child(3) {
    grid-column:
        1;

    grid-row:
        3;

    margin-bottom:
        0;
}

.record-grid > div:last-child > .record-panel:nth-child(1) {
    grid-column:
        1;

    grid-row:
        4;

    margin-bottom:
        0;
}

.record-panel {
    width:
        100%;
}

/* Give the wider layout a little more breathing room */
.record-body {
    padding:
        18px 20px 20px;
}

.info-item {
    min-height:
        86px;

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;
}

/* Documents can comfortably use the full landscape width */
.doc-row {
    min-height:
        58px;
}

/* History entries also span the whole width */
.history-row {
    padding:
        13px 2px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .info-grid {
        grid-template-columns:
            repeat(2,minmax(0,1fr));
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

    .record-top {
        display:
            flex;

        flex-direction:
            column;

        align-items:
            stretch;
    }

    .record-top h1 {
        font-size:
            24px;
    }

    .record-back-link {
        align-self:
            flex-start;
    }

    .info-grid {
        grid-template-columns:
            1fr;
    }

    .doc-row {
        align-items:
            flex-start;
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

        <div class="record-page">

            <div class="record-top">

                <div class="record-heading-main">

                    <h1>
                        <?= e($r['program_title']); ?>
                    </h1>

                    <p>
                        <?= e($coord); ?>
                        ·
                        <?= e($r['target_community']); ?>
                    </p>

                </div>

                <a
                    class="record-back-link"
                    href="<?= BASE_URL; ?>/admin/records/extension.php"
                >
                    <i class="bi bi-arrow-left"></i>
                    Extension Records
                </a>

            </div>

            <div class="record-grid">

                <div>

                    <section class="record-panel">

                        <div class="record-panel-head">
                            <h2>
                                Program Information
                            </h2>
                        </div>

                        <div class="record-body">

                            <div class="info-grid">

                                <div class="info-item">

                                    <label>
                                        Status
                                    </label>

                                    <strong>
                                        <?= e($r['status']); ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Progress
                                    </label>

                                    <strong>
                                        <?= (int)$r['progress_percentage']; ?>%
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Coordinator
                                    </label>

                                    <strong>
                                        <?= e($coord); ?>
                                    </strong>

                                    <span>
                                        <?= e($r['email']); ?>
                                    </span>

                                </div>

                                <div class="info-item">

                                    <label>
                                        College / Department
                                    </label>

                                    <strong>
                                        <?= e(
                                            $r['college_name']
                                            ?: 'Not assigned'
                                        ); ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            $r['department_name']
                                            ?: ''
                                        ); ?>
                                    </span>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Category / Type
                                    </label>

                                    <strong>
                                        <?= e($r['program_category']); ?>
                                    </strong>

                                    <span>
                                        <?= e($r['program_type']); ?>
                                    </span>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Target Community
                                    </label>

                                    <strong>
                                        <?= e($r['target_community']); ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            trim(
                                                ($r['municipality'] ?: '')
                                                . ' '
                                                . ($r['province'] ?: '')
                                            )
                                        ); ?>
                                    </span>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Target Participants
                                    </label>

                                    <strong>
                                        <?= number_format(
                                            (int)(
                                                $r['target_participants']
                                                ?? 0
                                            )
                                        ); ?>
                                    </strong>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Estimated Budget
                                    </label>

                                    <strong>
                                        <?= $r['estimated_budget'] !== null
                                            ? '₱'
                                                . number_format(
                                                    (float)$r['estimated_budget'],
                                                    2
                                                )
                                            : 'Not specified'; ?>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </section>

                    <section class="record-panel">

                        <div class="record-panel-head">
                            <h2>
                                Program Content
                            </h2>
                        </div>

                        <div class="record-body">

                            <div class="content-item">

                                <label>
                                    Objectives
                                </label>

                                <p>
                                    <?= e($r['objectives']); ?>
                                </p>

                            </div>

                            <div class="content-item">

                                <label>
                                    Program Description
                                </label>

                                <p>
                                    <?= e($r['program_description']); ?>
                                </p>

                            </div>

                            <div class="content-item">

                                <label>
                                    Expected Output
                                </label>

                                <p>
                                    <?= e($r['expected_output']); ?>
                                </p>

                            </div>

                        </div>

                    </section>

                    <section class="record-panel">

                        <div class="record-panel-head">
                            <h2>
                                Documents
                            </h2>
                        </div>

                        <div class="record-body">

                            <?php if ($documents): ?>

                                <?php foreach ($documents as $doc): ?>

                                    <div class="doc-row">

                                        <div>

                                            <strong>
                                                <?= e($doc['document_type']); ?>
                                            </strong>

                                            <small>
                                                <?= e($doc['original_name']); ?>
                                                ·
                                                <?= e(
                                                    date(
                                                        'M d, Y h:i A',
                                                        strtotime(
                                                            $doc['uploaded_at']
                                                        )
                                                    )
                                                ); ?>
                                            </small>

                                        </div>

                                        <a
                                            href="<?= BASE_URL; ?>/extension/view.php?id=<?= $id; ?>&document_id=<?= (int)$doc['document_id']; ?>&file_mode=view"
                                            target="_blank"
                                        >
                                            View
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="text-muted small">
                                    No documents recorded.
                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

                <div>

                    <section class="record-panel">

                        <div class="record-panel-head">
                            <h2>
                                Status History
                            </h2>
                        </div>

                        <div class="record-body">

                            <?php if ($history): ?>

                                <?php foreach ($history as $h): ?>

                                    <?php
                                    $by =
                                        trim(
                                            ($h['first_name'] ?? '')
                                            . ' '
                                            . ($h['middle_name'] ?? '')
                                            . ' '
                                            . ($h['last_name'] ?? '')
                                        );
                                    ?>

                                    <div class="history-row">

                                        <strong>
                                            <?= e(
                                                $h['old_status']
                                                ?: '—'
                                            ); ?>
                                            →
                                            <?= e(
                                                $h['new_status']
                                            ); ?>
                                        </strong>

                                        <small>
                                            <?= e(
                                                $by
                                                ?: 'System user'
                                            ); ?>
                                            ·
                                            <?= e(
                                                date(
                                                    'M d, Y h:i A',
                                                    strtotime(
                                                        $h['changed_at']
                                                    )
                                                )
                                            ); ?>
                                        </small>

                                        <?php if (!empty($h['remarks'])): ?>

                                            <p>
                                                <?= e($h['remarks']); ?>
                                            </p>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="text-muted small">
                                    No status history recorded.
                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

            </div>

        </div>

    </main>

</div>

<?php
require __DIR__ . '/../../includes/footer.php';
?>
