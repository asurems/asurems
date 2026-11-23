
<?php
/**
 * ASU REMS — EXTENSION MODULE
 *
 * File: extension/calls.php
 *
 * Phase 2 — Step 1: Call for Proposals
 *
 * Based on ASU Extension Services Manual,
 * Section 4.1 — Call for Paper Proposal.
 *
 * Extension-only functionality.
 */

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ .
    '/includes/extension_roles.php';

requireLogin();
extensionRequireAccess();

$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);

$isOffice = extensionIsOffice();
$isProponent = extensionIsProponent();

$pageTitle = 'Call for Proposals';
$activePage = 'extension';

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$memoTitle = '';
$memoNumber = '';
$memoUrl = '';
$description = '';
$opensOn = '';
$closesOn = '';

/*
|--------------------------------------------------------------------------
| VALIDATE DATE
|--------------------------------------------------------------------------
*/

function extensionCallValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    return $date !== false
        && $date->format('Y-m-d') === $value;
}

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isOffice) {

        http_response_code(403);

        $errors[] =
            'Only the Extension Service Office can manage calls.';

    } elseif (!verifyCsrfToken(
        $_POST['csrf_token'] ?? ''
    )) {

        $errors[] =
            'Invalid request. Please refresh and try again.';

    } else {

        $action = trim(
            (string) ($_POST['action'] ?? '')
        );

        /*
        |--------------------------------------------------------------
        | ISSUE CALL FOR PROPOSALS
        |--------------------------------------------------------------
        */

        if ($action === 'issue_call') {

            $memoTitle = trim(
                (string) ($_POST['memo_title'] ?? '')
            );

            $memoNumber = trim(
                (string) ($_POST['memo_number'] ?? '')
            );

            $memoUrl = trim(
                (string) ($_POST['memo_url'] ?? '')
            );

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $opensOn = trim(
                (string) ($_POST['opens_on'] ?? '')
            );

            $closesOn = trim(
                (string) ($_POST['closes_on'] ?? '')
            );

            /*
            |----------------------------------------------------------
            | VALIDATION
            |----------------------------------------------------------
            */

            if (
                $memoTitle === ''
                || mb_strlen($memoTitle) > 255
            ) {
                $errors[] =
                    'Enter a valid memo title.';
            }

            if (mb_strlen($memoNumber) > 100) {
                $errors[] =
                    'Memo number must not exceed 100 characters.';
            }

            if (
                $memoUrl === ''
                || strlen($memoUrl) > 1000
                || !filter_var(
                    $memoUrl,
                    FILTER_VALIDATE_URL
                )
                || strtolower(
                    (string) parse_url(
                        $memoUrl,
                        PHP_URL_SCHEME
                    )
                ) !== 'https'
                || !in_array(
                    strtolower(
                        (string) parse_url(
                            $memoUrl,
                            PHP_URL_HOST
                        )
                    ),
                    ['', 'localhost'],
                    true
                ) === false
            ) {
                // URL validation is performed below.
            }

            $host = strtolower(
                (string) parse_url(
                    $memoUrl,
                    PHP_URL_HOST
                )
            );

            $scheme = strtolower(
                (string) parse_url(
                    $memoUrl,
                    PHP_URL_SCHEME
                )
            );

            if (
                strlen($memoUrl) > 1000
                || !filter_var(
                    $memoUrl,
                    FILTER_VALIDATE_URL
                )
                || $scheme !== 'https'
                || $host === ''
            ) {
                $errors[] =
                    'You must enter a valid HTTPS memo link.';
            }

            if (
                !extensionCallValidDate($opensOn)
                || !extensionCallValidDate($closesOn)
            ) {
                $errors[] =
                    'Please enter valid submission dates.';

            } elseif ($closesOn < $opensOn) {

                $errors[] =
                    'Closing date cannot be earlier than opening date.';
            }

            /*
            |----------------------------------------------------------
            | SAVE CALL
            |----------------------------------------------------------
            */

            if (!$errors) {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO extension_calls
                        (
                            memo_title,
                            memo_number,
                            memo_url,
                            description,
                            opens_on,
                            closes_on,
                            status,
                            issued_by
                        )
                        VALUES
                        (
                            ?, ?, ?, ?, ?, ?,
                            'Open', ?
                        )
                    ");

                    $stmt->execute([
                        $memoTitle,
                        $memoNumber !== ''
                            ? $memoNumber
                            : null,
                        $memoUrl,
                        $description !== ''
                            ? $description
                            : null,
                        $opensOn,
                        $closesOn,
                        $userId
                    ]);

                    $success =
                        'Call for Proposals has been issued successfully.';

                    $memoTitle = '';
                    $memoNumber = '';
                    $memoUrl = '';
                    $description = '';
                    $opensOn = '';
                    $closesOn = '';

                } catch (Throwable $e) {

                    error_log(
                        'Extension call save error: ' .
                        $e->getMessage()
                    );

                    $errors[] =
                        'Unable to save the call. Please check the database setup.';
                }
            }

        /*
        |--------------------------------------------------------------
        | CLOSE CALL
        |--------------------------------------------------------------
        */

        } elseif ($action === 'close_call') {

            $callId = filter_var(
                $_POST['call_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$callId || $callId < 1) {

                $errors[] =
                    'Invalid Call for Proposals.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE extension_calls
                        SET status = 'Closed'
                        WHERE call_id = ?
                          AND status = 'Open'
                    ");

                    $stmt->execute([$callId]);

                    if ($stmt->rowCount() > 0) {

                        $success =
                            'Call for Proposals has been closed.';

                    } else {

                        $errors[] =
                            'Call not found or already closed.';
                    }

                } catch (Throwable $e) {

                    error_log(
                        'Extension call close error: ' .
                        $e->getMessage()
                    );

                    $errors[] =
                        'Unable to close the selected call.';
                }
            }

        } else {

            $errors[] = 'Invalid action.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CALLS
|--------------------------------------------------------------------------
*/

$calls = [];

try {

    $stmt = $pdo->query("
        SELECT
            ec.*,
            u.first_name,
            u.last_name

        FROM extension_calls ec

        LEFT JOIN users u
            ON u.user_id = ec.issued_by

        ORDER BY
            ec.created_at DESC,
            ec.call_id DESC
    ");

    $calls = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    error_log(
        'Extension calls load error: ' .
        $e->getMessage()
    );

    $errors[] =
        'Unable to load the calls. Please check the database setup.';
}

/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

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

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.call-card,.call-card-body,.call-table,.call-meta) {background:#172f23!important;border-color:#3d604a!important;color:#ebf5ec!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.call-card h2,.call-record-title,.call-meta strong) {color:#eff7ef!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope .call-table :is(th,td) {border-color:#3c5d47!important}

</style>
<style>
/* ASU-REMS: Call for Proposals, same visual system as extension/create.php */
:root {
  --asu-green: #007a33;
  --asu-green-dark: #005c26;
  --asu-green-deep: #003f1a;
  --asu-yellow: #ffd51f;
  --asu-ink: #101810;
  --asu-muted: #657068;
}
.call-page, .call-page button, .call-page input, .call-page select,
.call-page textarea, .call-page a, .call-page table { font-family: "Century Gothic",sans-serif !important; }
.main-page.call-page {
  min-height:100vh !important;
  background:linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%) !important;
}
.call-page .call-content {
  min-height:calc(100vh - 76px); padding:24px 28px 30px !important;
  background:radial-gradient(circle at 90% 5%,rgba(255,213,31,.10),transparent 25%),
     radial-gradient(circle at 8% 7%,rgba(0,122,51,.07),transparent 27%),
     linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%) !important;
}
.call-shell { max-width:1120px; margin:0 auto; }
.call-page .asu-topbar {
  min-height:76px !important; height:76px !important; padding:0 30px !important;
  background:rgba(255,255,255,.97) !important;
  border-bottom:1px solid rgba(0,122,51,.12) !important;
  box-shadow:0 10px 26px rgba(16,24,16,.035) !important;
}
.call-page .topbar-left { display:flex !important; align-items:center !important; gap:14px !important; }
.call-brand { position:relative; padding-left:18px; line-height:1.15; }
.call-brand::before {
  content:""; position:absolute; left:0; top:50%; width:3px; height:38px;
  transform:translateY(-50%); border-radius:999px; background:var(--asu-green);
}
.call-brand strong { display:block; color:var(--asu-ink); font-size:17px; font-weight:800; letter-spacing:-.015em; }
.call-brand span { display:block; margin-top:4px; color:var(--asu-muted); font-size:13px; }
.call-page .topbar-user-text strong { color:var(--asu-ink); font-size:15px !important; line-height:1.1 !important; }
.call-page .topbar-user-text span { display:block; margin-top:4px; color:var(--asu-muted) !important; font-size:12px !important; }
.call-page .asu-topbar .user-avatar {
  width:46px !important; height:46px !important; min-width:46px !important;
  border-radius:50% !important; background:var(--asu-yellow) !important;
  color:var(--asu-green-deep) !important; font-size:15px !important; font-weight:900 !important;
  box-shadow:0 8px 18px rgba(255,213,31,.25) !important;
}
.call-hero {
  position:relative; display:flex; justify-content:space-between; align-items:center;
  min-height:96px; gap:18px; margin-bottom:20px; padding:20px 28px;
  overflow:hidden; border:0; border-radius:18px;
  background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);
  box-shadow:0 18px 42px rgba(0,90,40,.16);
}
.call-hero::after {
  content:""; position:absolute; right:-110px; top:-120px;
  width:430px; height:280px; transform:rotate(-9deg); border-radius:42px;
  background:linear-gradient(135deg,rgba(255,213,31,.16),rgba(255,255,255,.035)); pointer-events:none;
}
.call-hero > * { position:relative; z-index:2; }
.call-hero h1 { margin:0; color:#fff; font-size:30px; font-weight:900; line-height:1.15; letter-spacing:-.025em; }
.call-hero .call-back {
  display:inline-flex !important; align-items:center; justify-content:center; gap:5px;
  flex:0 0 auto; height:42px; min-height:42px; padding:0 14px; border:0 !important;
  border-radius:10px; background:#ffd51f !important; color:#003f1a !important;
  text-decoration:none; font-size:10.5px; font-weight:800;
  box-shadow:0 8px 18px rgba(255,213,31,.18);
}
.call-hero .call-back:hover { background:#f0bd00 !important; color:#003f1a !important; }
.call-card {
  overflow:hidden; margin-bottom:18px; border:1px solid rgba(0,122,51,.13);
  border-radius:18px; background:rgba(255,255,255,.96);
  box-shadow:0 16px 38px rgba(16,24,16,.06);
}
.call-card-head {
  min-height:68px; display:flex; align-items:center; justify-content:space-between;
  gap:12px; padding:14px 18px; border-bottom:1px solid rgba(0,122,51,.09);
  background:rgba(255,255,255,.88);
}
.call-card-head-left { display:flex; align-items:center; gap:12px; min-width:0; }
.call-card-icon {
  width:40px; height:40px; min-width:40px; display:inline-flex;
  align-items:center; justify-content:center; border-radius:11px;
  background:#edf7ef; color:#007a33; font-size:18px;
}
.call-card-head h2 { margin:0; color:var(--asu-ink); font-family:"Century Gothic",sans-serif; font-size:15px; font-weight:700; }
.call-card-body { padding:20px; }
.call-page .call-form .form-label {
  display:block; margin-bottom:5px; color:#263229; font-size:10px; font-weight:700;
}
.call-page .call-form .form-control {
  min-height:42px; border:1px solid #d7e0d8; border-radius:10px;
  background:#fff; color:#263229; font-size:10.5px; box-shadow:none;
}
.call-page .call-form .form-control:focus {
  border-color:#76ac82; box-shadow:0 0 0 3px rgba(8,117,44,.07);
}
.call-page .call-form textarea.form-control { min-height:108px; resize:vertical; line-height:1.6; }
.call-required { color:#d56800; }
.call-page .call-form .call-actions {
  display:flex; justify-content:flex-end; align-items:center;
  margin-top:22px; padding-top:16px; border-top:1px solid rgba(0,122,51,.08);
}
.call-page .call-submit {
  min-height:42px; display:inline-flex; align-items:center; gap:7px;
  padding:0 16px; border:1px solid var(--asu-green); border-radius:10px;
  background:linear-gradient(135deg,var(--asu-green),var(--asu-green-dark));
  color:#fff; font-size:10px; font-weight:800;
  box-shadow:0 8px 18px rgba(0,122,51,.14);
}
.call-page .call-submit:hover { background:#005c26; color:#fff; }
.call-page .call-alert {
  margin-bottom:16px; padding:13px 15px; border-radius:12px; font-size:10px;
}
.call-page .call-alert-danger {
  border:1px solid rgba(242,140,40,.26); border-left:4px solid #f28c28;
  background:#fff9f3; color:#7c4516;
}
.call-page .call-alert-success {
  border:1px solid rgba(0,122,51,.17); border-left:4px solid var(--asu-green);
  background:#f5faf6; color:#17652f;
}
.call-page .call-table { width:100%; margin:0; table-layout:fixed; }
.call-page .call-table th {
  padding:13px 15px; border-bottom:1px solid rgba(0,122,51,.10);
  background:#f7faf7; color:#687269; font-family:"Century Gothic",sans-serif;
  font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
}
.call-page .call-table td {
  padding:14px 15px; border-bottom:1px solid rgba(0,122,51,.07);
  vertical-align:middle; color:#343c36; font-size:10px;
  overflow-wrap:anywhere;
}
.call-page .call-table tr:last-child td { border-bottom:0; }
.call-page .call-table tbody tr:hover td { background:rgba(0,122,51,.018); }
.call-record-title { display:block; font-size:11px; font-weight:800; color:#1c2a21; }
.call-meta { display:block; margin-top:4px; color:#819087; font-size:9px; line-height:1.5; }
.call-description { display:block; margin-top:6px; white-space:pre-wrap; color:#657068; font-size:9px; }
.call-status {
  display:inline-flex; justify-content:center; align-items:center; max-width:100%;
  min-height:27px; padding:4px 10px; border-radius:999px;
  font-size:9px; font-weight:800; line-height:1.3; text-align:center;
}
.call-status-accepting { background:#edf7ef; border:1px solid rgba(0,122,51,.15); color:#005c26; }
.call-status-upcoming { background:#fff8d7; border:1px solid rgba(255,213,31,.42); color:#655315; }
.call-status-closed { background:#f1f3f1; border:1px solid #e1e6e2; color:#606861; }
.call-status-expired { background:#fff2e5; border:1px solid rgba(242,140,40,.28); color:#8b541e; }
.call-actions-table { display:flex; gap:8px; justify-content:center; align-items:center; }
.call-icon-button {
  display:inline-flex !important; align-items:center; justify-content:center;
  width:40px; min-width:40px; height:40px; min-height:40px;
  padding:0 !important; border:1px solid rgba(0,122,51,.2); border-radius:10px;
  background:#fff; color:#007a33; font-size:16px; text-decoration:none;
}
.call-icon-button:hover { border-color:#82b88e; background:#f2f8f3; color:#005c26; }
.call-icon-button.danger { border-color:#f0c8c8; color:#a13939; }
.call-icon-button.danger:hover { border-color:#e0adad; background:#fff2f2; color:#912e2e; }
.call-record-counter {
  padding:5px 10px; border:1px solid rgba(0,122,51,.1); border-radius:999px;
  background:#f4f8f5; color:#657068; font-size:10px; font-weight:800; white-space:nowrap;
}
.call-empty { min-height:160px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; text-align:center; }
.call-empty i { display:flex; align-items:center; justify-content:center; width:52px; height:52px; border-radius:14px; background:#edf7ef; color:#007a33; font-size:23px; }
.call-empty p { margin:0; color:#657068; font-size:11px; }
.call-page .content-footer { display:none !important; }
.call-page *, .call-page *::before, .call-page *::after { animation:none !important; transition:none !important; }
@media (max-width:760px) {
  .call-page .asu-topbar { padding:0 16px !important; }
  .call-brand strong { font-size:13px; }
  .call-brand span { font-size:10px; }
  .call-page .call-content { padding:18px 14px 24px !important; }
  .call-hero { flex-direction:column; align-items:flex-start; min-height:0; padding:18px; }
  .call-hero h1 { font-size:25px; }
  .call-card-body { padding:16px; }
  .call-page .call-table { min-width:700px; }
}
</style>

<style id="rems-calls-final-darkmode-fix">
/* Call for Proposals: dark-theme icon tiles match Extension Programs.
   Placed after the light-theme styles so they cannot repaint these tiles white. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-card-head .call-card-icon,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-empty > i {
    background: #224c35 !important;
    background-image: none !important;
    color: #168c4a !important;
    border: 1px solid #36694b !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-card-icon i,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-empty > i::before {
    color: inherit !important;
}
/* Readable count pill and statuses without pale white patches. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-record-counter {
    background: #244b35 !important;
    border-color: #416e52 !important;
    color: #e0f1e3 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-status-accepting {
    background: #174d30 !important;
    border-color: #3d865b !important;
    color: #d7f4df !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-status-upcoming {
    background: #51421d !important;
    border-color: #8d752f !important;
    color: #ffedb6 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-status-closed {
    background: #2a4033 !important;
    border-color: #577360 !important;
    color: #d9e8dc !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-status-expired {
    background: #563923 !important;
    border-color: #8e653e !important;
    color: #ffe1bd !important;
}
/* Native calendar picker buttons remain visible on dark green date inputs. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope.call-page
.call-form input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(1.35) !important;
    opacity: .92 !important;
    cursor: pointer;
}
</style>

<div class="main-page rems-extension-dark-scope call-page">
    <header class="asu-topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Open navigation">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="call-brand">
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

    <main class="page-content call-content">
        <div class="call-shell">
            <div class="call-hero">
                <h1>Call for Proposals</h1>
                <a class="call-back" href="<?= BASE_URL; ?>/extension/index.php">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Extension
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="call-alert call-alert-danger" role="alert">
                    <strong>Please review the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="call-alert call-alert-success" role="status"><?= e($success); ?></div>
            <?php endif; ?>

            <?php if ($isOffice): ?>
                <section class="call-card">
                    <div class="call-card-head">
                        <div class="call-card-head-left">
                            <span class="call-card-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                            <h2>Issue Call for Proposals</h2>
                        </div>
                    </div>
                    <div class="call-card-body">
                        <form class="call-form" method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                            <input type="hidden" name="action" value="issue_call">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="memo_title">Memo Title <span class="call-required">*</span></label>
                                    <input type="text" id="memo_title" name="memo_title" class="form-control"
                                           maxlength="255" value="<?= e($memoTitle); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="memo_number">Memo Number</label>
                                    <input type="text" id="memo_number" name="memo_number" class="form-control"
                                           maxlength="100" value="<?= e($memoNumber); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="memo_url">Official Memo Link <span class="call-required">*</span></label>
                                    <input type="url" id="memo_url" name="memo_url" class="form-control"
                                           placeholder="https://" pattern="https://.*" maxlength="1000"
                                           value="<?= e($memoUrl); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="opens_on">Opening Date <span class="call-required">*</span></label>
                                    <input type="date" id="opens_on" name="opens_on" class="form-control"
                                           value="<?= e($opensOn); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="closes_on">Closing Date <span class="call-required">*</span></label>
                                    <input type="date" id="closes_on" name="closes_on" class="form-control"
                                           value="<?= e($closesOn); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="description">Announcement Details</label>
                                    <textarea id="description" name="description" class="form-control" rows="3"
                                              placeholder="Optional announcement details"><?= e($description); ?></textarea>
                                </div>
                            </div>
                            <div class="call-actions">
                                <button type="submit" class="call-submit">
                                    <i class="bi bi-send" aria-hidden="true"></i> Issue Call
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <section class="call-card">
                <div class="call-card-head">
                    <div class="call-card-head-left">
                        <span class="call-card-icon"><i class="bi bi-list-check" aria-hidden="true"></i></span>
                        <h2>Issued Calls for Proposals</h2>
                    </div>
                    <span class="call-record-counter"><?= number_format(count($calls)); ?> record<?= count($calls) === 1 ? '' : 's'; ?></span>
                </div>
                <?php if (!$calls): ?>
                    <div class="call-card-body call-empty">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p>No Call for Proposals has been issued yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle call-table">
                            <colgroup><col style="width:42%"><col style="width:25%"><col style="width:18%"><col style="width:15%"></colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">Memo</th>
                                    <th scope="col">Submission Period</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($calls as $call): ?>
                                <?php
                                    $today = date('Y-m-d');
                                    $isOpen = $call['status'] === 'Open';
                                    $isAccepting = $isOpen && $today >= $call['opens_on'] && $today <= $call['closes_on'];
                                    if (!$isOpen) {
                                        $displayStatus = 'Closed';
                                        $statusClass = 'call-status-closed';
                                    } elseif ($today < $call['opens_on']) {
                                        $displayStatus = 'Upcoming';
                                        $statusClass = 'call-status-upcoming';
                                    } elseif ($isAccepting) {
                                        $displayStatus = 'Accepting Proposals';
                                        $statusClass = 'call-status-accepting';
                                    } else {
                                        $displayStatus = 'Deadline Passed';
                                        $statusClass = 'call-status-expired';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong class="call-record-title"><?= e($call['memo_title']); ?></strong>
                                        <?php if (!empty($call['memo_number'])): ?>
                                            <small class="call-meta"><?= e($call['memo_number']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($call['description'])): ?>
                                            <small class="call-description"><?= e($call['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span><?= e(date('M d, Y', strtotime($call['opens_on']))); ?></span>
                                        <small class="call-meta">to <?= e(date('M d, Y', strtotime($call['closes_on']))); ?></small>
                                    </td>
                                    <td><span class="call-status <?= e($statusClass); ?>"><?= e($displayStatus); ?></span></td>
                                    <td class="text-center">
                                        <div class="call-actions-table">
                                            <a href="<?= e($call['memo_url']); ?>" target="_blank" rel="noopener noreferrer"
                                               class="call-icon-button" title="View Official Memo" aria-label="View Official Memo">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            <?php if ($isOffice && $isOpen): ?>
                                                <form method="POST" class="m-0"
                                                      onsubmit="return confirm('Close this Call for Proposals?');">
                                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                                                    <input type="hidden" name="action" value="close_call">
                                                    <input type="hidden" name="call_id" value="<?= (int)$call['call_id']; ?>">
                                                    <button type="submit" class="call-icon-button danger"
                                                            title="Close Call" aria-label="Close Call">
                                                        <i class="bi bi-x-circle" aria-hidden="true"></i>
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
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
