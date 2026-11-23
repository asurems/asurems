<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/notifications.php';

require_once __DIR__ . '/../includes/research_revision_deadlines.php';

requireLogin();

$user = currentUser();

$role = (string)($user['role'] ?? '');

if (!in_array($role, ['faculty', 'rps', 'admin'], true)) {

    setFlash('danger', 'You are not authorized to view research archives.');

    redirect(BASE_URL . '/dashboard.php');

}

// Archived records are retained in research_proposals and research_documents.

// This page lists the records; no files are moved or deleted.

remsArchiveExpiredUrrcRevisions($pdo);

remsArchiveExpiredInhouseRevisions($pdo);

$pageTitle = 'Research Archives';

$activePage = $role === 'rps' ? 'processing' : 'research';

$params = [];

$query = "

    SELECT rp.research_id, rp.manuscript_no, rp.research_title,

           rp.status, rp.workflow_status, rp.archived_reason, rp.archived_at,

           rp.researcher_id, rp.created_at,

           u.first_name, u.middle_name, u.last_name

      FROM research_proposals rp

      INNER JOIN users u ON u.user_id = rp.researcher_id

     WHERE (rp.is_archived = 1 OR rp.status = 'Archived')

";

if ($role === 'faculty') {

    $query .= ' AND rp.researcher_id = ?';

    $params[] = (int)$user['user_id'];

}

$query .= ' ORDER BY COALESCE(rp.archived_at, rp.created_at) DESC, rp.research_id DESC';

$stmt = $pdo->prepare($query);

$stmt->execute($params);

$archivedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/header.php';

require __DIR__ . '/../includes/sidebar.php';

?>

<style>

/* ASU-REMS Research Archives — presentation only; no workflow changes. */

:root {

    --rems-green: #007a33;

    --rems-green-dark: #005c26;

    --rems-green-deep: #003f1a;

    --rems-yellow: #ffd51f;

    --rems-ink: #101810;

    --rems-muted: #657068;

    --rems-line: rgba(0, 122, 51, .12);

}

.main-page .rems-archive-area,

.main-page .rems-archive-area button,

.main-page .rems-archive-area a,

.main-page .rems-archive-area table { font-family: 'Century Gothic', sans-serif; }

.main-page .rems-archive-topbar {

    min-height: 76px;

    padding: 0 30px;

    background: rgba(255,255,255,.97);

    border-bottom: 1px solid var(--rems-line);

    box-shadow: 0 10px 26px rgba(16,24,16,.035);

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 16px;

}

.rems-archive-topbar .topbar-left,

.rems-archive-topbar .topbar-user { display: flex; align-items: center; gap: 14px; }

.rems-archive-topbar .system-brand-copy {

    position: relative;

    padding-left: 18px;

    line-height: 1.15;

}

.rems-archive-topbar .system-brand-copy::before {

    content: '';

    width: 3px;

    height: 38px;

    background: var(--rems-green);

    position: absolute;

    top: 50%;left: 0;

    transform: translateY(-50%);

    border-radius: 999px;

}

.rems-archive-topbar .system-brand-copy strong {

    display: block; color: var(--rems-ink); font-size: 17px; font-weight: 800;

}

.rems-archive-topbar .system-brand-copy span {

    display: block; margin-top: 4px; color: var(--rems-muted); font-size: 13px;

}

.rems-archive-topbar .topbar-user-text {text-align: right;}

.rems-archive-topbar .topbar-user-text strong {

    display: block; color: var(--rems-ink);font-size: 14px;font-weight: 800;

}

.rems-archive-topbar .topbar-user-text span {

    display: block; margin-top: 4px; color: var(--rems-muted); font-size: 12px;

}

.rems-archive-topbar .user-avatar {

    width: 44px; height: 44px; min-width: 44px;

    display: inline-flex; align-items: center; justify-content: center;

    border-radius: 50%;background: var(--rems-yellow);color: var(--rems-green-deep);

    font-size: 19px;box-shadow: 0 8px 18px rgba(255,213,31,.24);

}

.main-page .rems-archive-area {

    min-height: calc(100vh - 76px);

    padding: 24px 28px 32px;

    background:

        radial-gradient(circle at 91% 4%, rgba(255,213,31,.10), transparent 24%),

        radial-gradient(circle at 8% 7%, rgba(0,122,51,.07), transparent 28%),

        linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%);

}

.rems-archive-inner { width: 100%;max-width: 1320px;margin: 0 auto; }

.rems-archive-hero {

    position: relative;overflow: hidden;

    min-height: 96px; margin-bottom: 18px;padding: 20px 26px;

    border-radius: 18px;

    background: linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);

    box-shadow: 0 18px 42px rgba(0,90,40,.16);

    display: flex;align-items: center;justify-content: space-between;gap: 16px;

}

.rems-archive-hero::after {

    content:''; position:absolute;right:-110px;top:-120px;width:430px;height:280px;

    transform:rotate(-9deg);border-radius:42px;

    background:linear-gradient(135deg,rgba(255,213,31,.17),rgba(255,255,255,.04));

    pointer-events:none;

}

.rems-archive-hero > * { position:relative;z-index:1; }

.rems-archive-hero h1 {

    margin:0;color:#fff;font-size:30px;line-height:1.15;font-weight:900;letter-spacing:-.03em;

}

.rems-archive-hero p {margin:6px 0 0;color:rgba(255,255,255,.87);font-size:12px;line-height:1.55;}

.rems-archive-hero .count {

    display:inline-flex;align-items:center;gap:7px;min-height:36px;

    border:1px solid rgba(255,255,255,.20);border-radius:999px;

    padding:0 12px;background:rgba(255,255,255,.12);color:#fff;

    font-size:10px;font-weight:800;white-space:nowrap;

}

/* Archive navigation now sits inside the records panel header. */
.rems-archive-panel-header .rems-archive-back {
    margin-left: auto;
    flex-shrink: 0;
}

.rems-archive-btn {

    display:inline-flex;align-items:center;justify-content:center;gap:7px;

    min-height:37px;padding:8px 13px;border:1px solid rgba(0,122,51,.22);

    border-radius:10px;background:#fff;color:var(--rems-green-dark);

    text-decoration:none;font-size:11px;font-weight:800;

    transition:background .16s,border-color .16s,box-shadow .16s,color .16s;

    white-space:nowrap;

}

/* Keep both actions together only on eligible, expired In-House records. */

.rems-archive-row-actions { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }

.rems-archive-row-actions form { margin:0; }

.rems-archive-row-actions button { font-family:inherit;cursor:pointer; }

.rems-archive-btn:hover,.rems-archive-btn:focus-visible {

    color:var(--rems-green-dark);background:#eff8f1;border-color:var(--rems-green);

    box-shadow:0 4px 12px rgba(0,122,51,.08);

}

.rems-archive-btn.primary {

    border-color:var(--rems-green);color:#fff;

    background:linear-gradient(135deg,var(--rems-green),var(--rems-green-dark));

    box-shadow:0 8px 18px rgba(0,122,51,.13);

}

.rems-archive-btn.primary:hover,.rems-archive-btn.primary:focus-visible {

    background:var(--rems-green-dark);color:#fff;

}

.rems-archive-panel {

    overflow:hidden;border:1px solid rgba(0,122,51,.13);border-radius:18px;

    background:rgba(255,255,255,.97);box-shadow:0 16px 38px rgba(16,24,16,.06);

}

.rems-archive-panel-header {

    display:flex;align-items:center;justify-content:space-between;gap:12px;

    min-height:66px;padding:14px 18px;

    background:rgba(255,255,255,.9);border-bottom:1px solid rgba(0,122,51,.09);

}

.rems-archive-panel h2 {

    display:flex;align-items:center;gap:10px;margin:0;

    font-family:'Century Gothic',sans-serif;

    font-size:15px;font-weight:700;color:var(--rems-ink);

}

.rems-archive-panel-icon {

    display:inline-flex;align-items:center;justify-content:center;

    width:36px;height:36px;min-width:36px;border-radius:10px;

    background:#edf7ef;color:var(--rems-green);font-size:16px;

}

.rems-archive-panel-note {font-size:11px;color:var(--rems-muted);}

.rems-archive-scroll {width:100%;overflow-x:auto;}

.rems-archive-table {width:100%;min-width:1000px;margin:0;border-collapse:collapse;}

.rems-archive-table th {

    padding:13px 15px;border-bottom:1px solid rgba(0,122,51,.1);

    background:#f7faf7;color:#687269;text-align:left;

    font-family:'Century Gothic',sans-serif;font-size:10px;font-weight:700;

    text-transform:uppercase;letter-spacing:.035em;white-space:nowrap;

}

.rems-archive-table td {

    padding:14px 15px;border-bottom:1px solid rgba(0,122,51,.07);

    color:#343c36;font-size:11px;line-height:1.55;vertical-align:middle;

}

.rems-archive-table tbody tr:last-child td {border-bottom:0;}

.rems-archive-table tbody tr:hover td {background:rgba(0,122,51,.02);}

.rems-archive-title {display:block;max-width:360px;color:#222a24;font-size:12px;font-weight:800;line-height:1.45;}

.rems-archive-manuscript {

    display:inline-flex;align-items:center;min-height:27px;padding:4px 8px;

    border-radius:8px;border:1px solid rgba(0,122,51,.1);

    background:#f4f8f5;color:var(--rems-green-dark);font-size:10px;font-weight:800;

    white-space:nowrap;

}

.rems-archive-muted {color:#768079;font-size:11px;}

.rems-archive-reason {color:#4f5a52;font-size:11px;}

.rems-archive-date {white-space:nowrap;}

.rems-archive-empty {padding:54px 20px;text-align:center;color:#7a857d;font-size:12px;}

.rems-archive-empty-icon {

    display:flex;align-items:center;justify-content:center;margin:0 auto 14px;

    width:58px;height:58px;border-radius:16px;background:#edf7ef;

    color:var(--rems-green);font-size:25px;

}

.rems-archive-empty h3 {margin:0 0 4px;color:#47534a;font-size:14px;font-weight:800;}

@media(max-width:900px) {

    .main-page .rems-archive-topbar {padding:0 18px;}

    .main-page .rems-archive-area {padding:18px 16px 26px;}

}

@media(max-width:760px) {

    .rems-archive-hero {padding:18px;min-height:82px;align-items:flex-start;flex-direction:column;}

    .rems-archive-hero h1 {font-size:25px;}

    .rems-archive-topbar .system-brand-copy strong {font-size:13px;}

    .rems-archive-topbar .system-brand-copy span {font-size:10px;}

    .rems-archive-topbar .topbar-user-text {display:none;}

    .rems-archive-panel-header {align-items:flex-start;flex-direction:column;}
    .rems-archive-panel-header .rems-archive-back {align-self:flex-end;margin-left:0;}

}

/* ================================================================

   ASU-REMS RESEARCH ARCHIVES — DARK MODE

   Scoped to the Archives page; light mode and PHP workflow unchanged.

   Colors match Research Management, Progress, and RPS Processing Queue.

   ================================================================ */

html[data-rems-theme="dark"] body:has(.rems-archive-area),

html[data-rems-theme="dark"] body:has(.rems-archive-area) .main-page,

html[data-rems-theme="dark"] .main-page .rems-archive-area {

    background: #0b1d15 !important;

    color: #eaf4eb !important;

}

/* Fixed header: readable university name and account information. */

html[data-rems-theme="dark"] .main-page .rems-archive-topbar {

    background: #10281c !important;

    border-bottom-color: #355843 !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rems-archive-topbar .system-brand-copy strong,

html[data-rems-theme="dark"] .rems-archive-topbar .topbar-user-text strong {

    color: #f1f8ef !important;

}

html[data-rems-theme="dark"] .rems-archive-topbar .system-brand-copy span,

html[data-rems-theme="dark"] .rems-archive-topbar .topbar-user-text span {

    color: #bdd0c1 !important;

}

html[data-rems-theme="dark"] .rems-archive-topbar .sidebar-toggle {

    color: #eaf4eb !important;

}

/* Green banner remains green with white content. */

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-hero {

    border: 1px solid rgba(255,255,255,.12) !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-hero h1,

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-hero p,

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-hero .count {

    color: #fff !important;

}

/* Archive action buttons, including faculty-specific workflow buttons. */

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-btn {

    background: #284b38 !important;

    border-color: #557762 !important;

    color: #e8f4e9 !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-btn:is(:hover,:focus-visible) {

    background: #396049 !important;

    border-color: #79a185 !important;

    color: #fff !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-btn.primary {

    background: #087c3b !important;

    border-color: #087c3b !important;

    color: #fff !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-btn.primary:is(:hover,:focus-visible) {

    background: #0a9850 !important;

    border-color: #0a9850 !important;

    color: #fff !important;

}

/* Main archive panel and column labels. */

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel,

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-scroll {

    background: #132b20 !important;

    border-color: #355843 !important;

    box-shadow: none !important;

    color: #eaf4eb !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel-header {

    background: #1b392a !important;

    border-bottom-color: #3c5b46 !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel h2 {

    color: #f0f7ef !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel-icon,

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-empty-icon {

    background: #244b36 !important;

    color: #bfe9c8 !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel-note {

    color: #bacdc0 !important;

}

/* Scrollable table; preserve data, row order, and action layout. */

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-table {

    background: #132b20 !important;

    color: #eaf4eb !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-table thead th {

    background: #214431 !important;

    color: #dae9de !important;

    border-bottom-color: #46664f !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-table tbody td {

    background: #132b20 !important;

    color: #dce9dd !important;

    border-bottom-color: #355643 !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-table tbody tr:hover td {

    background: #203c2c !important;

    box-shadow: none !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-title {

    color: #f2f8f0 !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-manuscript {

    background: #294d36 !important;

    border-color: #476e50 !important;

    color: #c5ebd0 !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-reason {

    color: #c9dacc !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-muted,

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-date {

    color: #b4c8b9 !important;

}

/* Empty state text when archives contain no records. */

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-empty {

    color: #b8cbbb !important;

}

html[data-rems-theme="dark"] .rems-archive-area .rems-archive-empty h3 {

    color: #f0f7ef !important;

}

/* A steady interface on this page, without shifting hovered rows. */

html[data-rems-theme="dark"] .rems-archive-area *,

html[data-rems-theme="dark"] .rems-archive-area *::before,

html[data-rems-theme="dark"] .rems-archive-area *::after {

    transition: none !important;

    animation: none !important;

    scroll-behavior: auto !important;

}


/* Requested: yellow Back to Research button, including dark mode. */
.rems-archive-panel-header > a.rems-archive-back,
html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel-header > a.rems-archive-back {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
    box-shadow: none !important;
}
.rems-archive-panel-header > a.rems-archive-back:is(:hover, :focus-visible),
html[data-rems-theme="dark"] .rems-archive-area .rems-archive-panel-header > a.rems-archive-back:is(:hover, :focus-visible) {
    background: #f0bd00 !important;
    border-color: #f0bd00 !important;
    color: #003f1a !important;
}
</style>

<div class="main-page">

    <header class="asu-topbar rems-archive-topbar">

        <div class="topbar-left">

            <button id="sidebarToggle" type="button" class="sidebar-toggle" aria-label="Open navigation">

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

                <span>Research Archives</span>

            </div>

            <div class="user-avatar"><i class="bi bi-person-fill" aria-hidden="true"></i></div>

        </div>

    </header>

    <main class="page-content rems-archive-area">

        <div class="rems-archive-inner">

            <div class="rems-archive-hero">

                <div>

                    <h1>Research Archives</h1>

                    <p>Archived proposals remain available for reference. Original documents and decisions are preserved.</p>

                </div>

                <span class="count"><i class="bi bi-folder2-open" aria-hidden="true"></i><?= count($archivedRecords); ?> Archived Record<?= count($archivedRecords) === 1 ? '' : 's'; ?></span>

            </div>

<section class="rems-archive-panel">

                <div class="rems-archive-panel-header">

                    <h2><span class="rems-archive-panel-icon"><i class="bi bi-archive" aria-hidden="true"></i></span>Archived Research Records</h2>
<a class="rems-archive-btn rems-archive-back" href="<?= BASE_URL; ?>/<?= $role === 'rps' ? 'research/rps_queue.php?queue=submissions' : 'research/index.php'; ?>">

                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Research

                </a>

                </div>

                <?php if ($archivedRecords): ?>

                    <div class="rems-archive-scroll">

                        <table class="rems-archive-table">

                            <thead><tr><th>Manuscript</th><th>Research</th><th>Researcher</th><th>Archive reason</th><th>Archived</th><th>Action</th></tr></thead>

                            <tbody>

                            <?php foreach ($archivedRecords as $record): ?>

                                <tr>

                                    <td><span class="rems-archive-manuscript"><?= e($record['manuscript_no'] ?: '#' . $record['research_id']); ?></span></td>

                                    <td><span class="rems-archive-title"><?= e($record['research_title']); ?></span></td>

                                    <td><?= e(trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))); ?></td>

                                    <td><span class="rems-archive-reason"><?= e($record['archived_reason'] ?: str_replace('_',' ',(string)$record['workflow_status'])); ?></span></td>

                                    <td class="rems-archive-date rems-archive-muted"><?= !empty($record['archived_at']) ? e(date('M d, Y h:i A', strtotime($record['archived_at']))) : '—'; ?></td>

                                    <td>

                                        <div class="rems-archive-row-actions">

                                            <?php

                                            $isRejectedRecord = in_array(

                                                (string)($record['workflow_status'] ?? ''),

                                                ['URRC_REJECTED', 'INHOUSE_FAILED', 'REJECTED', 'ADMIN_REJECTED'],

                                                true

                                            ) || ($record['status'] ?? '') === 'Rejected';

                                            ?>

                                            <?php if ($role === 'faculty' && $isRejectedRecord): ?>

                                                <a class="rems-archive-btn primary" href="<?= BASE_URL; ?>/research/create.php">

                                                    <i class="bi bi-plus-circle" aria-hidden="true"></i> Add New Proposal

                                                </a>

                                            <?php else: ?>

                                                <a class="rems-archive-btn" href="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$record['research_id']; ?>">

                                                    <i class="bi bi-eye" aria-hidden="true"></i> View Record

                                                </a>

                                            <?php endif; ?>

                                            <?php if (

                                                $role === 'faculty'

                                                && ($record['workflow_status'] ?? '') === 'INHOUSE_DID_NOT_REVISE'

                                                && remsIsNewUrrcPath($pdo, (int)$record['research_id'])

                                                && ($expiredInhouse = remsInhouseRevisionDeadline($pdo, (int)$record['research_id']))

                                                && (int)$expiredInhouse['expired'] === 1

                                            ): ?>

                                                <form method="POST" action="<?= BASE_URL; ?>/research/view.php?id=<?= (int)$record['research_id']; ?>" class="m-0"

                                                      onsubmit="return confirm('Proceed to Self-Initiated after the 180-day In-House deadline?');">

                                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">

                                                    <input type="hidden" name="action" value="proceed_to_self_initiated_from_inhouse">

                                                    <button type="submit" class="rems-archive-btn">

                                                        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Proceed to Self-Initiated

                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="rems-archive-empty">

                        <div class="rems-archive-empty-icon"><i class="bi bi-inbox" aria-hidden="true"></i></div>

                        <h3>No archived research records yet.</h3>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
