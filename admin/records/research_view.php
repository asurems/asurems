<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

$user = currentUser();

$pageTitle = 'Research Record';
$activePage = 'admin_research_records';

$id =
    (int)(
        $_GET['id']
        ?? 0
    );

if ($id <= 0) {

    setFlash(
        'danger',
        'Invalid research record.'
    );

    redirect(
        BASE_URL
        . '/admin/records/research.php'
    );
}

$stmt =
    $pdo->prepare("
        SELECT
            rp.*,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.email,
            c.college_name,
            d.department_name
        FROM research_proposals rp
        INNER JOIN users u
            ON u.user_id = rp.researcher_id
        LEFT JOIN colleges c
            ON c.college_id = rp.college_id
        LEFT JOIN departments d
            ON d.department_id = rp.department_id
        WHERE rp.research_id = ?
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
        'Research record not found.'
    );

    redirect(
        BASE_URL
        . '/admin/records/research.php'
    );
}

$docs =
    $pdo->prepare("
        SELECT
            rd.*,
            u.first_name,
            u.last_name
        FROM research_documents rd
        LEFT JOIN users u
            ON u.user_id = rd.uploaded_by
        WHERE rd.research_id = ?
        ORDER BY
            rd.uploaded_at DESC,
            rd.document_id DESC
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
        FROM research_status_history h
        LEFT JOIN users u
            ON u.user_id = h.changed_by
        WHERE h.research_id = ?
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

$reviews = [];

try {

    $rv =
        $pdo->prepare("
            SELECT
                rw.*,
                u.first_name,
                u.middle_name,
                u.last_name
            FROM research_workflow_reviews rw
            LEFT JOIN users u
                ON u.user_id = rw.reviewed_by
            WHERE rw.research_id = ?
            ORDER BY
                rw.reviewed_at DESC,
                rw.review_id DESC
        ");

    $rv->execute([
        $id
    ]);

    $reviews =
        $rv->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {
}

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

$researcher =
    trim(
        ($r['first_name'] ?? '')
        . ' '
        . ($r['middle_name'] ?? '')
        . ' '
        . ($r['last_name'] ?? '')
    );

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
    max-width: 1180px;
    margin: 0 auto;
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

    transform:
        translateY(-50%);

    border-radius: 999px;

    background:
        var(--asu-green);
}

.system-brand-copy strong {
    display: block;

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
    display: block;

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
    min-width: 0;
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

    gap:
        6px;

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
   RESEARCH INFORMATION
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
   RESEARCH CONTENT
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
   DOCUMENT / HISTORY / REVIEW ROWS
========================================================= */

.doc-row,
.history-row,
.review-row {
    padding:
        12px 0;

    border-bottom:
        1px solid
        rgba(0,122,51,.07);
}

.doc-row:last-child,
.history-row:last-child,
.review-row:last-child {
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
.history-row strong,
.review-row strong {
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
.history-row small,
.review-row small {
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

.history-row p,
.review-row p {
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

/* =========================================================
   EMPTY TEXT
========================================================= */

.record-body .text-muted.small {
    padding:
        12px 0;

    color:
        #818b84 !important;

    font-size:
        9.5px !important;
}


/* =========================================================
   RESEARCH RECORD — FULL-WIDTH LANDSCAPE LAYOUT
========================================================= */

/*
 * All sections use the full available content width.
 * Visual order:
 * 1. Research Information
 * 2. Research Content
 * 3. Documents
 * 4. Workflow Reviews
 * 5. Status History
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

.record-grid > div:last-child > .record-panel:nth-child(2) {
    grid-column:
        1;

    grid-row:
        5;

    margin-bottom:
        0;
}

.record-panel {
    width:
        100%;
}

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

.doc-row {
    min-height:
        58px;
}

.review-row,
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
                        <?= e($r['research_title']); ?>
                    </h1>

                    <p>
                        <?= e(
                            $r['manuscript_no']
                            ?: 'No manuscript number'
                        ); ?>
                        ·
                        <?= e($researcher); ?>
                    </p>

                </div>

                <a
                    class="record-back-link"
                    href="<?= BASE_URL; ?>/admin/records/research.php"
                >
                    <i class="bi bi-arrow-left"></i>
                    Research Records
                </a>

            </div>

            <div class="record-grid">

                <div>

                    <section class="record-panel">

                        <div class="record-panel-head">
                            <h2>
                                Research Information
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
                                        Workflow
                                    </label>

                                    <strong>
                                        <?= e($r['workflow_status']); ?>
                                    </strong>

                                    <span>
                                        <?= e($r['workflow_stage']); ?>
                                    </span>

                                </div>

                                <div class="info-item">

                                    <label>
                                        Researcher
                                    </label>

                                    <strong>
                                        <?= e($researcher); ?>
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
                                        <?= e(
                                            $r['research_category']
                                            ?: 'Not specified'
                                        ); ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            $r['research_type']
                                            ?: ''
                                        ); ?>
                                    </span>

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
                                        Funding Source
                                    </label>

                                    <strong>
                                        <?= e(
                                            $r['funding_source']
                                            ?: 'Not specified'
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
                                Research Content
                            </h2>
                        </div>

                        <div class="record-body">

                            <div class="content-item">

                                <label>
                                    Abstract
                                </label>

                                <p>
                                    <?= e(
                                        $r['abstract']
                                        ?: 'Not provided.'
                                    ); ?>
                                </p>

                            </div>

                            <div class="content-item">

                                <label>
                                    Objectives
                                </label>

                                <p>
                                    <?= e(
                                        $r['objectives']
                                        ?: 'Not provided.'
                                    ); ?>
                                </p>

                            </div>

                            <div class="content-item">

                                <label>
                                    Methodology
                                </label>

                                <p>
                                    <?= e(
                                        $r['methodology']
                                        ?: 'Not provided.'
                                    ); ?>
                                </p>

                            </div>

                            <div class="content-item">

                                <label>
                                    Expected Output
                                </label>

                                <p>
                                    <?= e(
                                        $r['expected_output']
                                        ?: 'Not provided.'
                                    ); ?>
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
                                                <?= e(
                                                    $doc['document_type']
                                                ); ?>
                                            </strong>

                                            <small>
                                                <?= e(
                                                    $doc['original_name']
                                                ); ?>
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
                                            href="<?= BASE_URL; ?>/research/view.php?id=<?= $id; ?>&document_id=<?= (int)$doc['document_id']; ?>&file_mode=view"
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
                                Workflow Reviews
                            </h2>
                        </div>

                        <div class="record-body">

                            <?php if ($reviews): ?>

                                <?php foreach ($reviews as $rv): ?>

                                    <?php
                                    $by =
                                        trim(
                                            ($rv['first_name'] ?? '')
                                            . ' '
                                            . ($rv['middle_name'] ?? '')
                                            . ' '
                                            . ($rv['last_name'] ?? '')
                                        );
                                    ?>

                                    <div class="review-row">

                                        <strong>
                                            <?= e($rv['review_type']); ?>
                                            ·
                                            <?= e($rv['result']); ?>
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
                                                        $rv['reviewed_at']
                                                    )
                                                )
                                            ); ?>
                                        </small>

                                        <?php if (!empty($rv['remarks'])): ?>

                                            <p>
                                                <?= e($rv['remarks']); ?>
                                            </p>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="text-muted small">
                                    No detailed workflow reviews recorded.
                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

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
