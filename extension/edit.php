<?php
/**
 * ASU REMS - Extension Module
 * File: extension/edit.php
 * UERC Revision and Revision After University In-House Review
 * Research Module is not modified.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/extension_roles.php';
require_once __DIR__ . '/includes/extension_statuses.php';
require_once __DIR__ . '/includes/extension_workflow.php';
require_once __DIR__ . '/../includes/notifications.php';

requireLogin();
extensionRequireCoordinator();

$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);
$pageTitle = 'Revise Extension Proposal';
$activePage = 'extension';
$errors = [];
$proposal = null;
$latestReview = null;
$revisionHistory = [];
$revisedProposalUrl = '';
$revisionRemarks = '';

$extensionId = filter_var($_GET['id'] ?? $_POST['extension_id'] ?? null, FILTER_VALIDATE_INT);
if (!$extensionId || $extensionId < 1) {
    http_response_code(400);
    exit('Invalid extension proposal.');
}
$extensionId = (int) $extensionId;

/** The external link must be a real public HTTPS URL. */
function extensionRevisionValidUrl(string $url): bool
{
    if ($url === '' || strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
        || filter_var($host, FILTER_VALIDATE_IP)
        || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    return true;
}

/** Obtain the revision stage from the current proposal status. */
function extensionRevisionStageForStatus(string $status): ?array
{
    if ($status === EXT_STATUS_UERC_REVISION) {
        return [
            'stage' => 'UERC',
            'heading' => 'UERC Comments and Suggestions',
            'next' => EXT_STATUS_PRE_INHOUSE_ENDORSEMENT,
            'history' => 'Coordinator submitted revised UERC proposal; awaiting the separate endorsement document.',
        ];
    }
    if ($status === EXT_STATUS_INHOUSE_REVISION) {
        return [
            'stage' => 'In-House',
            'heading' => 'In-House Comments and Suggestions',
            'next' => EXT_STATUS_ENDORSEMENT,
            'history' => 'Coordinator submitted revised In-House proposal; awaiting the separate final endorsement document.',
        ];
    }
    return null;
}

/** The eye action confirmation is scoped to the owner, record and stage. */
function extensionRevisionSessionKey(int $userId, int $extensionId, string $stage): string
{
    return 'extension_revision_viewed_' . $userId . '_' . $extensionId . '_' . str_replace('-', '_', $stage);
}

try {
    $stmt = $pdo->prepare('
        SELECT ep.*, ec.memo_title
        FROM extension_programs ep
        LEFT JOIN extension_calls ec ON ec.call_id = ep.call_id
        WHERE ep.extension_id = ? AND ep.coordinator_id = ?
        LIMIT 1
    ');
    $stmt->execute([$extensionId, $userId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Extension revision proposal load error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load extension proposal. Check the Extension database migrations.');
}
if (!$proposal) {
    http_response_code(404);
    exit('Extension proposal not found or access denied.');
}

$config = extensionRevisionStageForStatus((string) $proposal['status']);
$stage = $config['stage'] ?? null;
$viewSessionKey = $stage !== null ? extensionRevisionSessionKey($userId, $extensionId, $stage) : null;

if ($stage !== null) {
    try {
        $stmt = $pdo->prepare('
            SELECT er.review_id, er.review_comments, er.reviewed_at,
                   u.first_name, u.last_name
            FROM extension_reviews er
            INNER JOIN users u ON u.user_id = er.recorded_by
            WHERE er.extension_id = ? AND er.review_stage = ?
            ORDER BY er.review_id DESC LIMIT 1
        ');
        $stmt->execute([$extensionId, $stage]);
        $latestReview = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Extension revision review load error: ' . $e->getMessage());
        $errors[] = 'Unable to load review comments.';
    }
}

$canRevise = $config !== null && $latestReview
    && extensionCanRoleTransition((string) $proposal['status'], $config['next'], extensionCurrentRole());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $revisedProposalUrl = trim((string) ($_POST['revised_proposal_url'] ?? ''));
    $revisionRemarks = trim((string) ($_POST['revision_remarks'] ?? ''));
    $formReviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } elseif (!in_array($action, ['view_link', 'submit_revision'], true)) {
        $errors[] = 'Invalid form action.';
    } elseif (!$canRevise) {
        $errors[] = 'This proposal is not currently available for revision.';
    } elseif (!$formReviewId || (int) $formReviewId !== (int) $latestReview['review_id']) {
        $errors[] = 'Review information has changed. Please refresh the page.';
    } elseif (!extensionRevisionValidUrl($revisedProposalUrl)) {
        $errors[] = 'Enter a valid public HTTPS revised proposal link.';
    } elseif (mb_strlen($revisionRemarks) > 10000) {
        $errors[] = 'Revision remarks must not exceed 10,000 characters.';
    } elseif ($action === 'view_link') {
        $_SESSION[$viewSessionKey] = [
            'url' => $revisedProposalUrl,
            'review_id' => (int) $formReviewId,
            'recorded_at' => time(),
        ];
        // Session must be written before the new tab follows the redirect.
        session_write_close();
        redirect($revisedProposalUrl);
    } else {
        $viewed = $_SESSION[$viewSessionKey] ?? null;
        if (!is_array($viewed)
            || !hash_equals((string) ($viewed['url'] ?? ''), $revisedProposalUrl)
            || (int) ($viewed['review_id'] ?? 0) !== (int) $formReviewId
            || (time() - (int) ($viewed['recorded_at'] ?? 0)) > 3600) {
            $errors[] = 'You must click the eye button to view the link before submitting.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('
                    SELECT status FROM extension_programs
                    WHERE extension_id = ? AND coordinator_id = ? LIMIT 1 FOR UPDATE
                ');
                $stmt->execute([$extensionId, $userId]);
                $lockedStatus = $stmt->fetchColumn();
                if ($lockedStatus === false || $lockedStatus !== $proposal['status']) {
                    throw new RuntimeException('Proposal status changed. Please reload the page.');
                }
                $validation = extensionValidateTransition($lockedStatus, $config['next'], extensionCurrentRole());
                if (empty($validation['valid'])) {
                    throw new RuntimeException($validation['message'] ?? 'Transition not allowed.');
                }
                $stmt = $pdo->prepare('
                    SELECT review_id FROM extension_reviews
                    WHERE extension_id = ? AND review_stage = ?
                    ORDER BY review_id DESC LIMIT 1
                ');
                $stmt->execute([$extensionId, $stage]);
                if ((int) $stmt->fetchColumn() !== (int) $formReviewId) {
                    throw new RuntimeException('A newer review was recorded. Please reload the page.');
                }
                $stmt = $pdo->prepare('
                    INSERT INTO extension_revisions
                    (extension_id, review_id, revision_stage, revised_proposal_url, revision_remarks, submitted_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $extensionId,
                    (int) $formReviewId,
                    $stage,
                    $revisedProposalUrl,
                    $revisionRemarks !== '' ? $revisionRemarks : null,
                    $userId,
                ]);
                $stmt = $pdo->prepare('
                    UPDATE extension_programs SET status = ?
                    WHERE extension_id = ? AND coordinator_id = ? AND status = ?
                ');
                $stmt->execute([$config['next'], $extensionId, $userId, $lockedStatus]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Unable to update proposal status.');
                }
                $stmt = $pdo->prepare('
                    INSERT INTO extension_status_history
                    (extension_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([$extensionId, $userId, $lockedStatus, $config['next'], $config['history']]);
                // A separate Coordinator endorsement HTTPS document is still required.
                $pdo->commit();
                unset($_SESSION[$viewSessionKey]);
                try {
                    notifyUsersByRole(
                        $pdo, extensionOfficeRoles(),
                        'Revised Extension proposal endorsed',
                        fullName($user) . ' submitted the revised proposal for ' . $stage . ' processing: '
                            . (string) $proposal['program_title'],
                        'extension', $extensionId,
                        BASE_URL . '/extension/view.php?id=' . $extensionId, $userId
                    );
                } catch (Throwable $noticeError) {
                    error_log('Extension revision notification failed: ' . $noticeError->getMessage());
                }
                redirect(BASE_URL . '/extension/view.php?id=' . $extensionId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Extension revision submission error: ' . $e->getMessage());
                $errors[] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to submit the revised proposal. Please check the Extension database.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare('
        SELECT revision_stage, revised_proposal_url, revision_remarks, submitted_at
        FROM extension_revisions
        WHERE extension_id = ? AND submitted_by = ?
        ORDER BY revision_id DESC
    ');
    $stmt->execute([$extensionId, $userId]);
    $revisionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Extension revision history error: ' . $e->getMessage());
    $errors[] = 'Unable to load revision history.';
}

$initials = strtoupper(substr((string) ($user['first_name'] ?? ''), 0, 1)
    . substr((string) ($user['last_name'] ?? ''), 0, 1));

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>

<style id="rems-extension-darkmode-overrides">
/* =============================================================
   ASU-REMS — EXTENSION DARK MODE ONLY
   The existing PHP workflows, JS, and light theme are unchanged.
   Scoped to this file via .rems-extension-dark-scope.
   ============================================================= */
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope  .page-content,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-content,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-management-page,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-details-page {
    background:#0b1d15!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .asu-topbar {
    background:#11271b!important;
     border-bottom:1px solid #365541!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .topbar-user-text strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-brand-copy strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-brand strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .rems-topbar-brandline strong,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-name {
    color:#eef7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .topbar-user-text span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .system-brand-copy span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-review-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .extension-create-brand span,
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .rems-topbar-brandline span {
    color:#bdd3c2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.page-content,.extension-review-page,.extension-create-page,.call-content,.extension-management-page,.extension-details-page) :is(h1,h2,h3,h4,h5,h6) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.call-hero,.extension-review-hero,.extension-form-heading,.page-heading,.rems-view-heading) :is(h1,h2,h3,h4) {
    color:#ffffff!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card,.asu-card,.asu-panel,.panel,.extension-review-card,.call-card,.extension-form-card,.extension-form-section,.filter-card,.eso-queue-panel,.extension-panel,.extension-status-overview,.extension-document-summary,.extension-document-summary-tile,.extension-overview-row,.extension-official-documents,.workflow-card,.content-block,.document-item,.extension-document-viewer,.extension-preview-message-box,.monitoring-card,.monitoring-filter-card,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.eso-monitor-card,.eso-monitor-detail,.service-request-form-card,.eso-service-card,.eso-service-details,.my-sr-card,.my-sr-details,.my-sr-filter-card,.extension-report-card,.extension-report-details,.call-meta,.extension-review-meta-item,.review-link-summary,.review-form-row,.extension-summary-meta,.my-sr-detail,.monitoring-note,.service-request-note,.extension-upload-intro,.upload-box,.extension-upload-control,.extension-call-link,.extension-form-section-title,.review-unavailable,.monitoring-list-filters,.monitoring-list-toolbar,.my-sr-toolbar,.eso-service-toolbar,.eso-service-filters,.my-sr-filters) {
    background:#162e22!important;
     background-image:none!important;
     border-color:#395b46!important;
     color:#eaf3eb!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card-header,.panel-header,.extension-review-card-header,.extension-form-header,.call-card-head,.extension-panel-header,.eso-queue-panel-head,.monitoring-request-heading,.service-request-heading,.my-sr-records-head,.eso-service-toolbar,.monitoring-list-toolbar) {
    background:#1b3a2b!important;
     background-image:none!important;
     border-bottom-color:#41604b!important;
     color:#edf7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.card-body,.call-card-body,.extension-review-card-body,.extension-panel-body,.extension-form-body,.monitoring-card .card-body,.service-request-form-card .card-body) {
    background:transparent!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-title-cell strong,.extension-record-title,.extension-program-title,.extension-review-card-title,.extension-review-proposal-heading h2,.extension-review-subtitle,.call-card-head h2,.call-record-title,.extension-form-header h2,.extension-form-section-title,.extension-panel-title,.extension-status-overview-content strong,.extension-document-summary-name,.extension-document-summary-info strong,.workflow-text h3,.workflow-text h4,.info-item strong,.my-sr-title,.my-sr-head-title,.my-sr-detail strong,.monitoring-request-heading h2,.service-request-heading h2,.eso-queue-panel-title,.eso-queue-panel-head h2,.panel-title,.call-meta strong,.extension-review-meta-item strong,.monitor-highlight-value,.monitoring-card h2,.extension-report-card h3) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-title-cell small,.extension-coordinator small,.extension-record-muted,.extension-record-meta,.extension-review-helper,.extension-review-meta-item span,.call-description,.call-meta small,.call-card-body p,.extension-form-body .small,.extension-upload-note,.extension-document-summary-meta,.extension-summary-meta span,.info-item label,.document-info small,.workflow-text p,.monitoring-help,.service-request-help,.service-request-note,.my-sr-description,.my-sr-code,.monitoring-note,.my-sr-detail small,.monitor-report-detail .small,.eso-monitor-detail .small,.eso-service-details .small) {
    color:#bdd2c3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.text-muted,.text-secondary,.form-text,.small.text-muted,p.text-muted,small.text-muted) {
    color:#bed1c3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-label,label:not(.btn),.extension-filter-label,.call-form .form-label) {
    color:#dcebdc!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea.form-control,.input-group-text,.monitoring-search-field,.my-sr-search-field,.eso-service-search-field,.eso-queue-search-input) {
    background-color:#1c392b!important;
     color:#f3f9f2!important;
     border-color:#4c7158!important;
     box-shadow:none!important;
     color-scheme:dark;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,input,textarea)::placeholder {
    color:#bbd0be!important;
     opacity:1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope select.form-select {
    appearance:none!important;
     -webkit-appearance:none!important;
     -moz-appearance:none!important;
     padding-right:38px!important;
     background-color:#1c392b!important;
     background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3.25 6.25 4.75 4.5 4.75-4.5' fill='none' stroke='%23D9EEDF' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;
     background-repeat:no-repeat!important;
     background-position:right 14px center!important;
     background-size:14px 14px!important;
     color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope select.form-select option {
    background:#1c392b!important;
     color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope input:is([type="date"],[type="datetime-local"],[type="time"])::-webkit-calendar-picker-indicator {
    filter:invert(1) brightness(1.3);
     opacity:.85;
     cursor:pointer;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea):focus {
    border-color:#83c99d!important;
     box-shadow:0 0 0 2px rgba(91,170,114,.18)!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.table,table,.extension-table,.extension-programs-table,.call-table,.monitoring-table,.eso-service-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table,.my-sr-table) {
    --bs-table-bg:#162e22!important;
     --bs-table-color:#eaf3eb!important;
     --bs-table-border-color:#3a5f48!important;
     --bs-table-striped-bg:#1d3e2d!important;
     --bs-table-hover-bg:#234b36!important;
     color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#3a5f48!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table :is(th,td) {
    color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table thead :is(th,td) {
    background-color:#213f2d!important;
     color:#d0e5d4!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope table tbody tr:hover :is(td,th) {
    background-color:#214331!important;
     color:#f2f8f1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.table-responsive,.extension-table-wrapper,.monitoring-table-wrap,.eso-service-table-wrap,.my-sr-table-wrap,.eso-monitor-table-wrap,.monitor-flow-table-wrap,.monitor-report-table-wrap) {
    background:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.border,.border-top,.border-bottom):not(.btn):not(.badge) {
    border-color:#42624b!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(hr,.dropdown-divider) {
    border-color:#44624d!important;
     opacity:.75;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.dropdown-menu,.modal-content,.list-group-item) {
    background:#1a3628!important;
     border-color:#3d5c47!important;
     color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.dropdown-item,.dropdown-item-text,.modal-title) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .dropdown-item:hover {
    background:#25503a!important;
     color:#ffffff!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn) {
    background:#203e2c!important;
     background-image:none!important;
     color:#d7f0dc!important;
     border-color:#527861!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn):hover {
    background:#29543c!important;
     color:#ffffff!important;
     border-color:#79b28c!important;
     transform:none!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.btn-asu,.btn.btn-asu,.review-action-btn,.call-submit,.monitoring-filter-btn,.eso-service-filter-btn) {
    box-shadow:none!important;
     transform:none!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-success,.call-alert-success) {
    background:#214734!important;
     border-color:#4e8a63!important;
     color:#d8f5df!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-danger,.call-alert-danger,.form-errors) {
    background:#492b2b!important;
     border-color:#915656!important;
     color:#ffdddd!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .alert-warning {
    background:#4b3b23!important;
     border-color:#886d3c!important;
     color:#f4e3b3!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.alert-info,.extension-review-inline-notice,.workflow-notice,.design-notice) {
    background:#203b30!important;
     border-color:#4b7860!important;
     color:#dcefe1!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-empty-state,.call-empty,.eso-queue-empty,.my-sr-empty,.monitoring-empty,.monitor-report-empty,.eso-monitor-empty,.monitor-flow-empty,.extension-report-empty) {
    background:#162e22!important;
     color:#c9dfce!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-empty-state h3,.call-empty h3,.eso-queue-empty h3,.my-sr-empty h3,.monitoring-empty h3) {
    color:#edf7ef!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.monitoring-track,.my-sr-progress-track,.extension-progress-track,.progress) {
    background-color:#355442!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.progress-bar,.monitoring-value) {
    color:#ffffff!important;
}

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .card {background:#172f23!important;color:#edf6ed!important;border-color:#3e604a!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.fw-semibold,.card h2,.card strong) {color:#eef7ee!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .badge.bg-light.text-dark {background:#dcefe0!important;color:#215a35!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .rounded.border {background:#203d2c!important;border-color:#4a7559!important;color:#e9f6e9!important}

</style>

<style id="rems-extension-revision-professional-layout">
/* Page-specific presentation only. The PHP actions and review workflow are unchanged. */
.extension-revision-shell,
.extension-revision-shell button,
.extension-revision-shell input,
.extension-revision-shell textarea,
.extension-revision-shell a,
.extension-revision-shell table {font-family:"Century Gothic",sans-serif!important}
.extension-revision-shell .asu-topbar {min-height:76px!important;height:76px!important;padding:0 30px!important;background:rgba(255,255,255,.97)!important;border-bottom:1px solid rgba(0,122,51,.12)!important;box-shadow:0 10px 26px rgba(16,24,16,.035)!important}
.extension-revision-shell .topbar-left {display:flex!important;align-items:center!important;gap:14px!important}
.extension-revision-brand {position:relative;padding-left:18px;line-height:1.15}
.extension-revision-brand::before {content:"";position:absolute;top:50%;left:0;width:3px;height:38px;transform:translateY(-50%);border-radius:99px;background:#007a33}
.extension-revision-brand strong {display:block;color:#101810;font-size:17px;font-weight:800;letter-spacing:-.015em}
.extension-revision-brand span {display:block;margin-top:4px;color:#657068;font-size:13px}
.extension-revision-shell .topbar-user-text strong {font-size:15px!important;line-height:1.1!important}
.extension-revision-shell .topbar-user-text span {font-size:12px!important;margin-top:4px!important;color:#657068!important}
.extension-revision-shell .asu-topbar .user-avatar {width:46px!important;height:46px!important;min-width:46px!important;display:flex;align-items:center;justify-content:center;border-radius:50%!important;background:#ffd51f!important;color:#003f1a!important;font-size:15px!important;font-weight:900!important;box-shadow:none!important}
.extension-revision-shell .page-content.extension-revision-page {min-height:calc(100vh - 76px)!important;padding:24px 28px 36px!important;background:radial-gradient(circle at 92% 6%,rgba(255,213,31,.10),transparent 27%),radial-gradient(circle at 7% 9%,rgba(0,122,51,.055),transparent 27%),linear-gradient(135deg,#e4f1e6 0%,#eef2e8 48%,#f8efe2 100%)!important}
.extension-revision-inner {width:100%;max-width:1320px;margin:0 auto}
.extension-revision-hero {position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;min-height:145px;margin-bottom:20px;padding:24px 35px;border-radius:20px;background:linear-gradient(112deg,#005c26 0%,#007a33 68%,#2c9a39 100%);color:#fff;box-shadow:0 16px 35px rgba(0,90,40,.12)}
.extension-revision-hero::after {content:"";position:absolute;top:-150px;right:-85px;width:390px;height:330px;transform:rotate(-12deg);border-radius:45px;background:rgba(255,213,31,.09);pointer-events:none}
.extension-revision-hero>* {position:relative;z-index:1}
.extension-revision-kicker {margin-bottom:9px;color:#fff;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.extension-revision-hero h1 {margin:0 0 8px;color:#fff!important;font-size:30px;font-weight:900;line-height:1.17;letter-spacing:-.02em}
.extension-revision-hero p {margin:0;color:rgba(255,255,255,.87)!important;font-size:11px;line-height:1.55}
.extension-revision-shell .revision-back-btn {flex:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border:0;border-radius:11px;background:#ffd51f!important;color:#003f1a!important;font-size:10.5px;font-weight:800;text-decoration:none;white-space:nowrap;box-shadow:none!important}
.extension-revision-shell .revision-back-btn:hover {background:#f0bd00!important;color:#003f1a!important}
.extension-revision-shell .revision-card {overflow:hidden;margin:0 0 18px;border:1px solid rgba(0,122,51,.13);border-radius:18px;background:rgba(255,255,255,.98);box-shadow:0 12px 28px rgba(16,24,16,.045)}
.extension-revision-shell .revision-card-header {display:flex;align-items:center;min-height:70px;padding:15px 21px;border-bottom:1px solid #e8eee9;background:#fff}
.extension-revision-shell .revision-card-heading {display:flex;align-items:center;gap:12px;margin:0;color:#17221a;font-size:15px;font-weight:800;line-height:1.35}
.extension-revision-shell .revision-card-icon {width:39px;height:39px;display:inline-flex;align-items:center;justify-content:center;flex:none;border-radius:11px;background:#edf7ef;color:#007a33;font-size:18px}
.extension-revision-shell .revision-card-body {padding:20px 23px 24px}
.extension-revision-shell .revision-info-grid, .extension-revision-shell .revision-review-meta {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.extension-revision-shell .revision-review-meta {margin-bottom:16px}
.extension-revision-shell .revision-info-item {display:flex;flex-direction:column;justify-content:center;gap:6px;min-width:0;min-height:77px;padding:13px 16px;border:1px solid #e6eee8;border-radius:12px;background:#fafcfa;overflow-wrap:anywhere}
.extension-revision-shell .revision-info-item--full {grid-column:1 / -1}
.extension-revision-shell .revision-info-label {display:block;color:#77867a;font-size:9px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.extension-revision-shell .revision-info-item strong {color:#223228;font-size:11.5px;line-height:1.5;font-weight:700}
.extension-revision-shell .revision-status-badge {display:inline-flex;align-items:center;min-height:27px;padding:5px 11px;border:1px solid #f1dda0;border-radius:30px;background:#fff5ce;color:#394023;font-size:10px;font-weight:800;line-height:1.4}
.extension-revision-shell .revision-comments-box {min-height:102px;padding:16px 17px;border:1px solid #e0ebe3;border-left:3px solid #007a33;border-radius:11px;background:#fafcfb}
.extension-revision-shell .revision-comments-box p {margin:9px 0 0;color:#33483b;font-size:11.5px;line-height:1.7;overflow-wrap:anywhere}
.extension-revision-shell .revision-empty {margin:0;color:#6a7b6f;font-size:11px}
.extension-revision-shell .revision-form-field {margin-bottom:17px}
.extension-revision-shell .revision-form-field .form-label {display:block;margin:0 0 8px;color:#405348;font-size:10.5px;font-weight:800;line-height:1.4}
.extension-revision-shell .revision-required {color:#a35c24}
.extension-revision-shell .revision-optional {font-size:10px;font-weight:400;color:#798a7f}
.extension-revision-shell .revision-form-field .form-control {width:100%;min-height:44px;padding:10px 13px;border:1px solid #d7e3da!important;border-radius:11px!important;background:#fff;color:#25372a;font-size:11.5px;line-height:1.5;box-shadow:none!important}
.extension-revision-shell .revision-form-field .form-control:focus {border-color:#007a33!important;box-shadow:0 0 0 .18rem rgba(0,122,51,.10)!important}
.extension-revision-shell .revision-form-field textarea.form-control {min-height:105px;resize:vertical}
.extension-revision-shell .revision-link-row {display:flex;align-items:stretch;gap:9px;min-width:0}
.extension-revision-shell .revision-link-row .form-control {flex:1 1 auto;min-width:0}
.extension-revision-shell .revision-eye-btn {display:inline-flex;align-items:center;justify-content:center;flex:none;width:44px;height:44px;min-width:44px;padding:0;border:1px solid #cbded0;border-radius:11px;background:#fff;color:#007a33;font-size:17px;text-decoration:none;box-shadow:none!important;cursor:pointer}
.extension-revision-shell .revision-eye-btn:hover {background:#edf7ef;color:#005c26;border-color:#acd3b6}
.extension-revision-shell .revision-form-field .form-text {margin-top:8px;color:#758178;font-size:10px;line-height:1.5}
.extension-revision-shell .revision-form-footer {display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:4px;padding-top:17px;border-top:1px solid #e8eee9}
.extension-revision-shell .revision-form-footer-note {display:inline-flex;align-items:center;gap:8px;color:#6a7a70;font-size:10px;line-height:1.5}
.extension-revision-shell .revision-form-footer-note i {font-size:13px;color:#007a33}
.extension-revision-shell .revision-submit-btn {display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:43px;padding:0 17px;border:0;border-radius:10px;background:#007a33;color:#ffffff;font-size:10.5px;font-weight:800;line-height:1.2;box-shadow:none!important}
.extension-revision-shell .revision-submit-btn:hover:not(:disabled) {background:#005c26;color:#ffffff}
.extension-revision-shell .revision-submit-btn:disabled {opacity:.52;cursor:not-allowed}
.extension-revision-shell .revision-card-body--table {padding:4px 20px 12px}
.extension-revision-shell .revision-history-table {width:100%;margin:0;--bs-table-bg:transparent;--bs-table-hover-bg:#f6faf7;color:#324236;font-size:10.5px}
.extension-revision-shell .revision-history-table thead th {padding:12px 11px;border-bottom:1px solid #dce7df;background:#f7faf8;color:#506356;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.035em}
.extension-revision-shell .revision-history-table td {padding:12px 11px;border-bottom:1px solid #edf1ee;color:#33463a;line-height:1.5;overflow-wrap:anywhere}
.extension-revision-shell .revision-history-table tbody tr:last-child td {border-bottom:0}
.extension-revision-shell .revision-history-eye {width:37px;height:37px;min-width:37px;font-size:15px}
.extension-revision-shell .revision-alert {margin:0 0 18px;padding:14px 17px;border-radius:11px;font-size:10.5px;line-height:1.6}
.extension-revision-shell .content-footer {display:none!important}
.extension-revision-shell .page-content * {animation:none!important;transition:none!important}
@media(max-width:760px) {
 .extension-revision-shell .asu-topbar {padding:0 16px!important}
 .extension-revision-brand strong {font-size:13px}
 .extension-revision-brand span {font-size:10px}
 .extension-revision-shell .page-content.extension-revision-page {padding:18px 14px 26px!important}
 .extension-revision-hero {min-height:0;padding:21px;flex-direction:column;align-items:stretch}
 .extension-revision-hero h1 {font-size:25px}
 .extension-revision-shell .revision-back-btn {align-self:flex-start}
 .extension-revision-shell .revision-card-header {min-height:62px;padding:13px 16px}
 .extension-revision-shell .revision-card-body {padding:15px 16px 18px}
 .extension-revision-shell .revision-info-grid,.extension-revision-shell .revision-review-meta {grid-template-columns:1fr;gap:10px}
 .extension-revision-shell .revision-info-item--full {grid-column:auto}
 .extension-revision-shell .revision-form-footer {align-items:stretch;flex-direction:column}
 .extension-revision-shell .revision-submit-btn {width:100%}
}
/* Dark mode: same green/dark Extension palette; no white patches in these page-specific cards. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.extension-revision-shell .asu-topbar {background:#10271b!important;border-bottom-color:#395743!important;box-shadow:none!important}
html[data-rems-theme="dark"] body .extension-revision-shell .extension-revision-brand strong,html[data-rems-theme="dark"] body .extension-revision-shell .topbar-user-text strong {color:#f1f7f1!important}
html[data-rems-theme="dark"] body .extension-revision-shell .extension-revision-brand span,html[data-rems-theme="dark"] body .extension-revision-shell .topbar-user-text span {color:#bed2c3!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.extension-revision-shell .extension-revision-page {background:#0b1d15!important;background-image:none!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-card,html[data-rems-theme="dark"] body .extension-revision-shell .revision-card-header {background:#172f23!important;border-color:#3e604a!important;box-shadow:none!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-card-heading {color:#eef7ee!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-card-icon {background:#234b36!important;color:#d5f1df!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-info-item,html[data-rems-theme="dark"] body .extension-revision-shell .revision-comments-box {background:#1c392b!important;border-color:#45644d!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-info-label,html[data-rems-theme="dark"] body .extension-revision-shell .revision-form-field .form-text,html[data-rems-theme="dark"] body .extension-revision-shell .revision-form-footer-note {color:#b8d0be!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-info-item strong,html[data-rems-theme="dark"] body .extension-revision-shell .revision-comments-box p {color:#eef7ee!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-status-badge {background:#4a4323!important;border-color:#89753b!important;color:#ffe9a5!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-form-field .form-label {color:#e5f1e7!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-form-field .form-control {background:#1c392b!important;border-color:#4a7158!important;color:#f3f9f2!important;box-shadow:none!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-eye-btn {background:#203e2c!important;border-color:#527861!important;color:#d7f0dc!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-form-footer {border-top-color:#41604b!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-submit-btn {background:#007a33!important;color:#ffffff!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-submit-btn:hover:not(:disabled) {background:#005c26!important;color:#ffffff!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-history-table {--bs-table-bg:#172f23!important;--bs-table-color:#eaf3eb!important;--bs-table-hover-bg:#234b36!important;--bs-table-border-color:#3a5f48!important;color:#eaf3eb!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-history-table :is(td,th) {background:#172f23!important;border-color:#395b46!important;color:#eaf3eb!important}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-history-table thead th {background:#213f2d!important;color:#d0e5d4!important}
/* Submission form first; the two explanation cards share a balanced second row. */
.extension-revision-shell .revision-secondary-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    align-items:stretch;
    gap:18px;
    margin-bottom:18px;
}
.extension-revision-shell .revision-secondary-grid > .revision-card {
    min-width:0;
    height:100%;
    margin-bottom:0;
}
.extension-revision-shell .revision-secondary-grid .revision-card-body {
    padding:17px 19px 20px;
}
.extension-revision-shell .revision-card-help {
    margin:0 0 14px;
    color:#68796c;
    font-size:10.5px;
    line-height:1.55;
}
.extension-revision-shell .revision-secondary-grid .revision-info-item {
    min-height:69px;
    padding:11px 13px;
}
.extension-revision-shell .revision-secondary-grid .revision-comments-box {
    min-height:95px;
    padding:13px 15px;
}
@media(max-width:1080px) {
    .extension-revision-shell .revision-secondary-grid {grid-template-columns:minmax(0,1fr)}
}
@media(max-width:760px) {
    .extension-revision-shell .revision-secondary-grid {gap:14px}
    .extension-revision-shell .revision-secondary-grid .revision-card-body {padding:15px 16px 18px}
}
html[data-rems-theme="dark"] body .extension-revision-shell .revision-card-help {
    color:#bfd2c3!important;
}
</style>
<div class="main-page rems-extension-dark-scope extension-revision-shell">
    <header class="asu-topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="extension-revision-brand">
                <strong>Research &amp; Extension Management System</strong>
                <span>Aklan State University</span>
            </div>
        </div>
        <div class="topbar-user">
            <div class="topbar-user-text">
                <strong><?= e(fullName($user)); ?></strong>
                <span><?= e(roleLabel($user['role'])); ?></span>
            </div>
            <div class="user-avatar"><?= e($initials); ?></div>
        </div>
    </header>

    <main class="page-content extension-revision-page">
        <div class="extension-revision-inner">
            <div class="extension-revision-hero">
                <div>
                    <div class="extension-revision-kicker">Extension Proposal Processing</div>
                    <h1>Revise Extension Proposal</h1>
                    <p><?= $stage === 'In-House'
                        ? 'Submit your updated proposal link. In-House review comments and proposal details are below.'
                        : 'Submit your updated proposal link. UERC review comments and proposal details are below.'; ?></p>
                </div>
                <a href="<?= BASE_URL; ?>/extension/view.php?id=<?= $extensionId; ?>" class="revision-back-btn">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Proposal
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger revision-alert" role="alert">
                    <strong>Please review the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($canRevise): ?>
                <section class="revision-card">
                    <div class="revision-card-header">
                        <h2 class="revision-card-heading">
                            <span class="revision-card-icon"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i></span>
                            Submit Revised Proposal
                        </h2>
                    </div>
                    <div class="revision-card-body">
                        <form method="POST" id="revisionForm">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                            <input type="hidden" name="extension_id" value="<?= $extensionId; ?>">
                            <input type="hidden" name="review_id" value="<?= (int) $latestReview['review_id']; ?>">
                            <div class="revision-form-field">
                                <label class="form-label" for="revisedProposalUrl">Revised Proposal HTTPS Link <span class="revision-required">*</span></label>
                                <div class="revision-link-row">
                                    <input type="url" name="revised_proposal_url" id="revisedProposalUrl"
                                           class="form-control" placeholder="https://" pattern="https://.*"
                                           maxlength="1000" value="<?= e($revisedProposalUrl); ?>" required>
                                    <button type="button" id="viewRevisionLink" class="revision-eye-btn"
                                            title="View revised proposal link" aria-label="View revised proposal link">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text">You must click the eye button to view the link before submitting.</div>
                            </div>
                            <div class="revision-form-field">
                                <label class="form-label" for="revisionRemarks">Revision Remarks <span class="revision-optional">(Optional)</span></label>
                                <textarea name="revision_remarks" id="revisionRemarks" class="form-control" rows="4"
                                          maxlength="10000" placeholder="Optional summary of revisions"><?= e($revisionRemarks); ?></textarea>
                            </div>
                            <div class="revision-form-footer">
                                <span class="revision-form-footer-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Submit the updated proposal after opening its link.</span>
                                <button type="submit" name="action" value="submit_revision"
                                        id="submitRevisionButton" class="revision-submit-btn" disabled>
                                    <i class="bi bi-send" aria-hidden="true"></i> Submit Revised Proposal
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <div class="alert alert-info revision-alert">
                    <?php if ($proposal['status'] === EXT_STATUS_INHOUSE_REVIEW): ?>
                        The revised UERC proposal has been submitted for endorsement before In-House Review.
                    <?php elseif ($proposal['status'] === EXT_STATUS_ENDORSEMENT): ?>
                        The In-House revised proposal has already been submitted and is now for endorsement.
                    <?php else: ?>
                        This proposal is not currently available for revision.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="revision-secondary-grid">
                <section class="revision-card">
                    <div class="revision-card-header">
                        <h2 class="revision-card-heading">
                            <span class="revision-card-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                            Proposal Details
                        </h2>
                    </div>
                    <div class="revision-card-body">
                        <p class="revision-card-help">Basic information about the proposal you are updating.</p>
                        <div class="revision-info-grid">
                            <div class="revision-info-item">
                                <span class="revision-info-label">Proposal Title</span>
                                <strong><?= e($proposal['program_title']); ?></strong>
                            </div>
                            <div class="revision-info-item">
                                <span class="revision-info-label">Revision Status</span>
                                <span><span class="revision-status-badge"><?= e($proposal['status']); ?></span></span>
                            </div>
                            <div class="revision-info-item">
                                <span class="revision-info-label">Category</span>
                                <strong><?= e($proposal['program_category'] ?? ''); ?></strong>
                            </div>
                            <div class="revision-info-item">
                                <span class="revision-info-label">Target Community</span>
                                <strong><?= e($proposal['target_community'] ?? ''); ?></strong>
                            </div>
                            <?php if (!empty($proposal['memo_title'])): ?>
                                <div class="revision-info-item revision-info-item--full">
                                    <span class="revision-info-label">Related Call for Proposals</span>
                                    <strong><?= e($proposal['memo_title']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="revision-card">
                    <div class="revision-card-header">
                        <h2 class="revision-card-heading">
                            <span class="revision-card-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                            <?= $stage === 'In-House' ? 'In-House Review Comments' : 'UERC Review Comments'; ?>
                        </h2>
                    </div>
                    <div class="revision-card-body">
                        <p class="revision-card-help">Check the reviewer’s feedback so you know what to update.</p>
                        <?php if ($latestReview): ?>
                            <div class="revision-review-meta">
                                <div class="revision-info-item">
                                    <span class="revision-info-label">Reviewed On</span>
                                    <strong><?= e(date('M d, Y \a\t g:i A', strtotime((string)$latestReview['reviewed_at']))); ?></strong>
                                </div>
                                <div class="revision-info-item">
                                    <span class="revision-info-label">Reviewed By</span>
                                    <strong><?= e(trim($latestReview['first_name'] . ' ' . $latestReview['last_name'])); ?></strong>
                                </div>
                            </div>
                            <div class="revision-comments-box">
                                <span class="revision-info-label">Comments to Address</span>
                                <p><?= nl2br(e($latestReview['review_comments'])); ?></p>
                            </div>
                        <?php else: ?>
                            <p class="revision-empty">No review comments have been added for this revision yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <?php if ($revisionHistory): ?>
                <section class="revision-card">
                    <div class="revision-card-header">
                        <h2 class="revision-card-heading">
                            <span class="revision-card-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                            Extension Revision History
                        </h2>
                    </div>
                    <div class="revision-card-body revision-card-body--table">
                        <div class="table-responsive">
                            <table class="table revision-history-table align-middle">
                                <thead><tr><th>Stage</th><th>Submitted</th><th>Remarks</th><th class="text-center">Document</th></tr></thead>
                                <tbody>
                                <?php foreach ($revisionHistory as $revision): ?>
                                    <tr>
                                        <td><?= e($revision['revision_stage']); ?></td>
                                        <td><?= e($revision['submitted_at']); ?></td>
                                        <td><?= e($revision['revision_remarks'] ?: 'No remarks'); ?></td>
                                        <td class="text-center">
                                            <a href="<?= e($revision['revised_proposal_url']); ?>" target="_blank"
                                               rel="noopener noreferrer" class="revision-eye-btn revision-history-eye"
                                               title="View revised proposal" aria-label="View revised proposal">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($canRevise): ?>
<script>
(function () {
    'use strict';
    const form = document.getElementById('revisionForm');
    const input = document.getElementById('revisedProposalUrl');
    const viewButton = document.getElementById('viewRevisionLink');
    const submitButton = document.getElementById('submitRevisionButton');
    if (!form || !input || !viewButton || !submitButton) return;
    let viewedValue = '';
    input.addEventListener('input', function () {
        if (input.value.trim() !== viewedValue) {
            viewedValue = '';
            submitButton.disabled = true;
        }
    });
    viewButton.addEventListener('click', function () {
        const url = input.value.trim();
        if (!input.reportValidity()) return;
        try {
            const parsed = new URL(url);
            if (parsed.protocol !== 'https:' || parsed.username || parsed.password) {
                throw new Error('Invalid HTTPS URL');
            }
        } catch (error) {
            input.setCustomValidity('Enter a valid HTTPS link.');
            input.reportValidity();
            input.setCustomValidity('');
            return;
        }
        const newTab = window.open('about:blank', '_blank');
        if (!newTab) {
            alert('Please allow pop-ups so the document can open in a new tab.');
            return;
        }
        const viewForm = document.createElement('form');
        viewForm.method = 'POST';
        viewForm.action = window.location.href;
        viewForm.target = newTab.name = 'extensionRevisionView_' + Date.now();
        viewForm.style.display = 'none';
        const names = ['csrf_token', 'extension_id', 'review_id', 'revised_proposal_url', 'revision_remarks'];
        names.forEach(function (name) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            if (name === 'revised_proposal_url') field.value = url;
            else field.value = form.elements[name].value;
            viewForm.appendChild(field);
        });
        const actionField = document.createElement('input');
        actionField.type = 'hidden';
        actionField.name = 'action';
        actionField.value = 'view_link';
        viewForm.appendChild(actionField);
        document.body.appendChild(viewForm);
        viewForm.submit();
        viewForm.remove();
        viewedValue = url;
        submitButton.disabled = false;
    });
    form.addEventListener('submit', function (event) {
        if (!viewedValue || input.value.trim() !== viewedValue) {
            event.preventDefault();
            submitButton.disabled = true;
            alert('You must click the eye button to view the link before submitting.');
        }
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
