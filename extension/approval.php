<?php
/**
 * ASU REMS — Extension Module
 * extension/approval.php
 * Phase 6: Combined LADCO/UADCO/BOR -> For Certification
 *
 * ESO records decisions officially issued by the appropriate bodies;
 * it does not grant approval itself. Research module is untouched.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/extension_roles.php';
require_once __DIR__ . '/includes/extension_statuses.php';
require_once __DIR__ . '/includes/extension_workflow.php';

requireLogin();
extensionRequireOffice();

$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);
$pageTitle = 'Extension LADCO/UADCO/BOR';
$activePage = 'extension_approval';
$errors = [];
$success = '';
$proposal = null;
$proposals = [];
$records = [];
$endorsement = null;

$selectedId = filter_var($_GET['id'] ?? $_POST['extension_id'] ?? null, FILTER_VALIDATE_INT);
$selectedId = ($selectedId !== false && $selectedId !== null && $selectedId > 0)
    ? (int) $selectedId : null;

$reference = trim((string) ($_POST['reference_number'] ?? ''));
$decisionDate = trim((string) ($_POST['decision_date'] ?? ''));
$ladcoDate = trim((string) ($_POST['ladco_date'] ?? ''));
$documentUrl = trim((string) ($_POST['document_url'] ?? ''));
$remarks = trim((string) ($_POST['remarks'] ?? ''));

function extensionApprovalStageForStatus(string $status): ?array
{
    if ($status === EXT_STATUS_LADCO_APPROVAL) {
        return [
            'stage' => 'LADCO_ADCO',
            'decision' => 'Approved',
            'next' => EXT_STATUS_CERTIFICATION,
            'title' => 'LADCO/UADCO/BOR',
        ];
    }
    if ($status === EXT_STATUS_BOR_NOTATION) {
        return [
            'stage' => 'BOR',
            'decision' => 'Noted',
            'next' => EXT_STATUS_CERTIFICATION,
            'title' => 'LADCO/UADCO/BOR (Legacy Record)',
        ];
    }
    return null;
}

function extensionApprovalValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function extensionApprovalValidHttpsUrl(string $value): bool
{
    if ($value === '' || strlen($value) > 1000 || !filter_var($value, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
    if ($scheme !== 'https' || $host === '' || $host === 'localhost') {
        return false;
    }
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') ||
        filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    return true;
}

function extensionApprovalViewKey(int $userId, int $extensionId, string $stage): string
{
    return 'ext_approval_view_' . $userId . '_' . $extensionId . '_' . $stage;
}

// Eye click: server records the exact URL, then opens the document in a new tab.
// Regular POST + 303 redirect avoids previous JSON and [object HTMLInputElement] errors.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_view') {
    if ($selectedId === null || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request. Return to the approval page and try again.');
    }
    if (!extensionApprovalValidHttpsUrl($documentUrl)) {
        http_response_code(422);
        exit('You must enter a valid HTTPS document link.');
    }
    try {
        $check = $pdo->prepare('SELECT status FROM extension_programs WHERE extension_id = ? LIMIT 1');
        $check->execute([$selectedId]);
        $status = (string) ($check->fetchColumn() ?: '');
        $stage = extensionApprovalStageForStatus($status);
        if ($stage === null) {
            http_response_code(409);
            exit('This proposal is no longer at an approval/notation stage.');
        }
        $_SESSION[extensionApprovalViewKey($userId, $selectedId, $stage['stage'])] = $documentUrl;
        session_write_close();
        header('Location: ' . $documentUrl, true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('Extension approval link view: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to open document link. Please try again.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'record_approval') {
        $errors[] = 'Invalid action.';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } elseif ($selectedId === null) {
        $errors[] = 'Select a valid extension proposal.';
    } else {
        if ($reference === '' || mb_strlen($reference) > 150) {
            $errors[] = 'Enter the official BOR Resolution Number (maximum 150 characters).';
        }
        if (!extensionApprovalValidDate($decisionDate) || $decisionDate > date('Y-m-d')) {
            $errors[] = 'Enter a valid BOR notation date that is not in the future.';
        }
        if ($ladcoDate !== '' && (!extensionApprovalValidDate($ladcoDate) || $ladcoDate > date('Y-m-d'))) {
            $errors[] = 'Enter a valid LADCO/UADCO approval date that is not in the future.';
        }
        if (!extensionApprovalValidHttpsUrl($documentUrl)) {
            $errors[] = 'Enter a valid HTTPS supporting-document link.';
        }
        if (mb_strlen($remarks) > 20000) {
            $errors[] = 'Remarks must not exceed 20,000 characters.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare(
                    'SELECT extension_id, status FROM extension_programs WHERE extension_id = ? LIMIT 1 FOR UPDATE'
                );
                $lock->execute([$selectedId]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$current) {
                    throw new RuntimeException('Extension proposal not found.');
                }

                $oldStatus = (string) $current['status'];
                $stage = extensionApprovalStageForStatus($oldStatus);
                if ($stage === null) {
                    throw new RuntimeException('Proposal is not ready for LADCO/UADCO/BOR processing.');
                }

                $viewed = (string) ($_SESSION[extensionApprovalViewKey($userId, $selectedId, $stage['stage'])] ?? '');
                if ($viewed === '' || !hash_equals($viewed, $documentUrl)) {
                    throw new RuntimeException('You must click the eye button to view the link before submitting.');
                }

                $validation = extensionValidateTransition(
                    $oldStatus, $stage['next'], extensionCurrentRole()
                );
                if (empty($validation['valid'])) {
                    throw new RuntimeException(
                        (string) ($validation['message'] ?? 'Workflow transition is not allowed.')
                    );
                }

                $isCombined = $stage['stage'] === 'LADCO_ADCO';
                if ($isCombined && (
                    !extensionApprovalValidDate($ladcoDate) ||
                    $ladcoDate > $decisionDate ||
                    $ladcoDate > date('Y-m-d')
                )) {
                    throw new RuntimeException('Enter the LADCO/UADCO approval date; it cannot be after BOR notation.');
                }

                // Existing records may already be awaiting BOR notation.
                if ($stage['stage'] === 'BOR') {
                    $prior = $pdo->prepare(
                        "SELECT approval_id FROM extension_approvals
                         WHERE extension_id = ? AND approval_stage = 'LADCO_ADCO'
                           AND decision = 'Approved' LIMIT 1"
                    );
                    $prior->execute([$selectedId]);
                    if (!$prior->fetchColumn()) {
                        throw new RuntimeException('An approved LADCO/UADCO record is required before BOR notation.');
                    }
                }

                // A combined submission must not overwrite either existing stage.
                $duplicate = $pdo->prepare(
                    'SELECT approval_id FROM extension_approvals WHERE extension_id = ? AND approval_stage = ? LIMIT 1'
                );
                foreach ($isCombined ? ['LADCO_ADCO', 'BOR'] : ['BOR'] as $approvalStage) {
                    $duplicate->execute([$selectedId, $approvalStage]);
                    if ($duplicate->fetchColumn()) {
                        throw new RuntimeException('The LADCO/UADCO/BOR decision has already been recorded.');
                    }
                }

                $insert = $pdo->prepare(
                    'INSERT INTO extension_approvals
                     (extension_id, approval_stage, decision, reference_number, decision_date,
                      document_url, remarks, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                if ($isCombined) {
                    // One ESO form records the two actual decisions with their own
                    // dates. Keep the existing database stage codes to avoid migrations.
                    $insert->execute([
                        $selectedId, 'LADCO_ADCO', 'Approved', null, $ladcoDate,
                        $documentUrl, $remarks !== '' ? $remarks : null, $userId,
                    ]);
                }

                // Certification requires an official BOR/Noted record. Record it
                // in the same database transaction and submission as LADCO/UADCO.
                $insert->execute([
                    $selectedId, 'BOR', 'Noted', $reference, $decisionDate,
                    $documentUrl, $remarks !== '' ? $remarks : null, $userId,
                ]);

                $update = $pdo->prepare(
                    'UPDATE extension_programs SET status = ? WHERE extension_id = ? AND status = ?'
                );
                $update->execute([$stage['next'], $selectedId, $oldStatus]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Proposal status changed. Refresh and try again.');
                }

                $history = $pdo->prepare(
                    'INSERT INTO extension_status_history
                     (extension_id, changed_by, old_status, new_status, remarks)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $history->execute([
                    $selectedId,
                    $userId,
                    $oldStatus,
                    $stage['next'],
                    $isCombined
                        ? 'ESO recorded official LADCO/UADCO approval and BOR notation together.'
                        : 'ESO completed pending BOR notation for an existing approval.',
                ]);

                $pdo->commit();
                unset($_SESSION[extensionApprovalViewKey($userId, $selectedId, $stage['stage'])]);
                setFlash('success', 'LADCO/UADCO/BOR recorded. The proposal is now For Certification.');
                redirect(BASE_URL . '/extension/approval.php?id=' . $selectedId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Extension approval save: ' . $e->getMessage());
                $errors[] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to save the official approval record. Check the Phase 6 database setup.';
            }
        }
    }
}

// Load ESO queue, selected proposal, and approval history.
try {
    $query = $pdo->query(
        "SELECT ep.extension_id, ep.program_title, ep.program_category, ep.status,
                u.first_name, u.middle_name, u.last_name
         FROM extension_programs ep
         INNER JOIN users u ON u.user_id = ep.coordinator_id
         WHERE ep.status IN ('For LADCO/ADCO Approval', 'For BOR Notation',
                             'For Certification', 'Approved')
         ORDER BY CASE WHEN ep.status = 'For LADCO/ADCO Approval' THEN 0
                       WHEN ep.status = 'For BOR Notation' THEN 1 ELSE 2 END,
                  ep.extension_id DESC"
    );
    $proposals = $query->fetchAll(PDO::FETCH_ASSOC);

    if ($selectedId !== null) {
        $select = $pdo->prepare(
            "SELECT ep.*, u.first_name, u.middle_name, u.last_name
             FROM extension_programs ep
             INNER JOIN users u ON u.user_id = ep.coordinator_id
             WHERE ep.extension_id = ? AND ep.status NOT IN ('Draft', 'Archived') LIMIT 1"
        );
        $select->execute([$selectedId]);
        $proposal = $select->fetch(PDO::FETCH_ASSOC);
        if ($proposal) {
            $recordsQuery = $pdo->prepare(
                'SELECT ea.*, u.first_name, u.last_name
                 FROM extension_approvals ea
                 INNER JOIN users u ON u.user_id = ea.recorded_by
                 WHERE ea.extension_id = ? ORDER BY ea.approval_id ASC'
            );
            $recordsQuery->execute([$selectedId]);
            $records = $recordsQuery->fetchAll(PDO::FETCH_ASSOC);

            $endorsementQuery = $pdo->prepare(
                'SELECT endorsement_date, endorsement_reference, endorsement_document_url
                 FROM extension_endorsements WHERE extension_id = ?
                 ORDER BY endorsement_id DESC LIMIT 1'
            );
            $endorsementQuery->execute([$selectedId]);
            $endorsement = $endorsementQuery->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    error_log('Extension approval load: ' . $e->getMessage());
    $errors[] = 'Unable to load approval records. Confirm Phase 5 and 6 SQL migrations were applied.';
}

$flash = getFlash();
if ($flash && ($flash['type'] ?? '') === 'success') {
    $success = (string) ($flash['message'] ?? '');
}

$stage = $proposal ? extensionApprovalStageForStatus((string) $proposal['status']) : null;
$initials = '';
if (!empty($user['first_name'])) {
    $initials .= strtoupper(substr($user['first_name'], 0, 1));
}
if (!empty($user['last_name'])) {
    $initials .= strtoupper(substr($user['last_name'], 0, 1));
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

html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-review-card,.extension-review-meta-item,.extension-review-link-row,.review-link-summary,.extension-review-history-item,.review-form-row) {background:#172f23!important;border-color:#395d46!important;color:#eef6ef!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-review-card-header,.extension-review-history-top) {background:#1d3c2b!important;border-color:#44664f!important;color:#eff8ee!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-review-card-title,.extension-review-proposal-heading h2,.extension-review-subtitle,.review-link-summary strong,.extension-review-meta-item strong) {color:#eef7ef!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.extension-review-helper,.extension-review-meta-item span,.review-link-summary small) {color:#bed3c3!important}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) .main-page.rems-extension-dark-scope :is(.review-eye-btn,.review-eyebtn-control) {background:#244735!important;color:#e5f6e9!important;border-color:#587a61!important}

</style>
<style>

/* Approval and notation: same visual design as extension/review.php. */
.extension-review-shell,
.extension-review-shell button,
.extension-review-shell input,
.extension-review-shell select,
.extension-review-shell textarea,
.extension-review-shell a {
    font-family: "Century Gothic", sans-serif !important;
}
.extension-review-shell,
.extension-review-shell .extension-review-page {
    scroll-behavior: auto !important;
}
.extension-review-shell .asu-topbar {
    min-height: 76px !important;
    height: 76px !important;
    padding: 0 30px !important;
    background: rgba(255,255,255,.97) !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
}
.extension-review-shell .topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
}
.extension-review-brand {
    position: relative;
    padding-left: 18px;
    line-height: 1.15;
}
.extension-review-brand::before {
    content: "";
    position: absolute;
    top: 50%; left: 0;
    width: 3px; height: 38px;
    transform: translateY(-50%);
    border-radius: 99px;
    background: #007a33;
}
.extension-review-brand strong {
    display: block;
    color: #101810;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -.015em;
}
.extension-review-brand span {
    display: block;
    margin-top: 4px;
    color: #657068;
    font-size: 13px;
}
.extension-review-shell .topbar-user-text strong {font-size:15px !important;line-height:1.1 !important;}
.extension-review-shell .topbar-user-text span {font-size:12px !important;margin-top:4px !important;color:#657068 !important;}
.extension-review-shell .asu-topbar .user-avatar {
    width:46px !important;height:46px !important;min-width:46px !important;
    display:flex;align-items:center;justify-content:center;
    border-radius:50% !important;background:#ffd51f !important;color:#003f1a !important;
    font-size:15px !important;font-weight:900 !important;
}
.extension-review-shell .page-content.extension-review-page {
    min-height:calc(100vh - 76px) !important;
    padding:24px 28px 36px !important;
    background:
        radial-gradient(circle at 92% 6%,rgba(255,213,31,.10),transparent 27%),
        radial-gradient(circle at 7% 9%,rgba(0,122,51,.055),transparent 27%),
        linear-gradient(135deg,#e4f1e6 0%,#eef2e8 48%,#f8efe2 100%) !important;
}
.extension-review-inner {max-width:1320px;margin:0 auto;}
.extension-review-hero {
    position:relative;overflow:hidden;
    display:flex;align-items:center;justify-content:space-between;gap:18px;
    min-height:145px;margin-bottom:20px;padding:24px 35px;
    border-radius:20px;
    background:linear-gradient(112deg,#005c26 0%,#007a33 68%,#2c9a39 100%);
    color:#fff;
    box-shadow:0 16px 35px rgba(0,90,40,.12);
}
.extension-review-hero::after {
    content:"";position:absolute;top:-150px;right:-85px;width:390px;height:330px;
    transform:rotate(-12deg);border-radius:45px;
    background:rgba(255,213,31,.09);pointer-events:none;
}
.extension-review-hero > * {position:relative;z-index:1;}
.extension-review-kicker {
    margin-bottom:9px;color:#fff;font-size:10px;font-weight:800;
    letter-spacing:.05em;text-transform:uppercase;
}
.extension-review-hero h1 {margin:0 0 8px;color:#fff;font-size:30px;font-weight:900;line-height:1.17;letter-spacing:-.02em;}
.extension-review-hero p {margin:0;color:rgba(255,255,255,.84);font-size:11px;line-height:1.55;}
.extension-review-shell .review-back-btn {
    flex:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;
    min-height:46px;padding:0 17px;border:0;border-radius:12px;
    background:#ffd51f !important;color:#003f1a !important;
    font-size:10.5px;font-weight:800;text-decoration:none;
}
.extension-review-shell .review-back-btn:hover {background:#f0bd00 !important;color:#003f1a !important;}
.extension-review-card {
    overflow:hidden;margin:0 0 18px;
    border:1px solid rgba(0,122,51,.13);border-radius:20px;
    background:rgba(255,255,255,.98);
    box-shadow:0 12px 28px rgba(16,24,16,.045);
}
.extension-review-card-header {
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
    padding:17px 22px;border-bottom:1px solid #e8eee9;
}
.extension-review-card-title {display:flex;align-items:center;gap:12px;margin:0;color:#17221a;font-size:15px;font-weight:800;line-height:1.35;}
.extension-review-card-icon {
    width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;flex:none;
    border-radius:11px;background:#edf7ef;color:#007a33;font-size:17px;
}
.extension-review-card-body {padding:20px 24px 24px;}
.extension-review-proposal-heading {display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:15px;}
.extension-review-proposal-heading h2 {margin:0 0 8px;font-size:17px;font-weight:800;color:#18231c;line-height:1.4;}
.extension-review-status {
    display:inline-flex;align-items:center;justify-content:center;
    max-width:100%;padding:7px 12px;min-height:29px;
    border:1px solid rgba(255,213,31,.45);border-radius:30px;
    background:#fff5ce;color:#28301d;font-size:9.5px;font-weight:800;line-height:1.4;
}
.extension-review-meta {
    display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;
    margin:0 0 18px;
}
.extension-review-meta-item {min-width:0;padding:13px 15px;border:1px solid #e6eee8;border-radius:12px;background:#fafcfa;}
.extension-review-meta-item small {display:block;margin-bottom:5px;color:#6f7c72;font-size:9px;font-weight:800;letter-spacing:.025em;text-transform:uppercase;}
.extension-review-meta-item strong {display:block;color:#243129;font-size:11.5px;font-weight:700;line-height:1.45;overflow-wrap:anywhere;}
.extension-review-subtitle {margin:0 0 7px;color:#1b281e;font-size:14px;font-weight:800;line-height:1.4;}
.extension-review-helper {margin:0 0 17px;color:#69766d;font-size:10.5px;line-height:1.5;}
.extension-review-form-grid {display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.extension-review-shell .form-label {display:block;margin-bottom:7px;color:#405348;font-size:10px;font-weight:800;line-height:1.4;}
.extension-review-shell .form-control,
.extension-review-shell .form-select {
    min-height:43px;padding:9px 13px;border:1px solid #d7e3da !important;border-radius:11px !important;
    background:#fff;color:#25372a;font-size:11px;box-shadow:none;
}
.extension-review-shell .form-select {appearance:auto;}
.extension-review-shell textarea.form-control {height:auto;min-height:115px;resize:vertical;line-height:1.6;}
.extension-review-shell .form-control:focus,
.extension-review-shell .form-select:focus {border-color:#007a33 !important;box-shadow:0 0 0 .18rem rgba(0,122,51,.10) !important;}
.extension-review-form-actions {display:flex;justify-content:flex-end;gap:8px;margin-top:16px;}
.extension-review-shell .review-action-btn {
    display:inline-flex;align-items:center;justify-content:center;gap:7px;
    min-height:42px;padding:0 15px;border-radius:10px;
    border:1px solid #007a33;background:#007a33;color:#fff;
    font-size:10.5px;font-weight:800;text-decoration:none;line-height:1.2;
}
.extension-review-shell .review-action-btn:hover {background:#005c26;color:#fff;border-color:#005c26;}
.extension-review-shell .review-eye-btn {
    display:inline-flex;align-items:center;justify-content:center;
    width:39px;height:39px;flex:none;
    border:1px solid #cbded0;border-radius:10px;background:#fff;color:#007a33;
    font-size:15px;text-decoration:none;
}
.extension-review-shell .review-eye-btn:hover {background:#edf7ef;color:#005c26;}
.extension-review-shell .extension-review-inline-notice {
    border:1px solid #e5e9e2;border-left:4px solid #007a33;border-radius:11px;
    padding:13px 15px;background:#fbfdfb;color:#516055;font-size:10.5px;line-height:1.6;
}
.extension-review-history-item {padding:13px 0;border-bottom:1px solid #e9eee9;}
.extension-review-history-item:first-child {padding-top:0;}
.extension-review-history-item:last-child {padding-bottom:0;border-bottom:0;}
.extension-review-history-top {display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:7px;}
.extension-review-history-top strong {color:#27342b;font-size:11px;font-weight:800;}
.extension-review-history-top time {color:#758078;font-size:9.5px;}
.extension-review-history-item p {margin:0;color:#506055;font-size:10.5px;line-height:1.7;overflow-wrap:anywhere;}
.extension-review-shell .alert {font-size:10.5px;border-radius:11px;}
.extension-review-shell hr {margin:18px 0;border-color:#e6ece7;opacity:1;}
.extension-review-shell .review-unavailable {padding:23px 24px;color:#546457;font-size:11px;line-height:1.6;}
.extension-review-shell .review-unavailable p {margin:0 0 12px;}
.extension-review-shell .review-unavailable .review-action-btn {width:max-content;}
.extension-review-shell .content-footer {display:none !important;}
.extension-review-shell .page-content * {animation:none !important;transition:none !important;}
@media(max-width:760px){
    .extension-review-shell .asu-topbar {padding:0 16px !important;}
    .extension-review-brand strong {font-size:13px;}
    .extension-review-brand span {font-size:10px;}
    .extension-review-shell .extension-review-page {padding:18px 14px 26px !important;}
    .extension-review-hero {padding:19px;min-height:0;flex-direction:column;align-items:stretch;}
    .extension-review-hero h1 {font-size:25px;}
    .extension-review-shell .review-back-btn {align-self:flex-start;}
    .extension-review-card-header {padding:14px 16px;}
    .extension-review-card-body {padding:16px;}
    .extension-review-meta,.extension-review-form-grid {grid-template-columns:1fr;gap:10px;}
    .extension-review-proposal-heading {align-items:flex-start;}
    .extension-review-form-actions .review-action-btn {width:100%;}
}

/* Approval-specific link field, preview and stage actions. */
.extension-review-link-row {display:flex;align-items:stretch;gap:9px;min-width:0;}
.extension-review-link-row .form-control {flex:1 1 auto;min-width:0;width:100%;}
.extension-review-shell .extension-review-link-row .review-eye-btn {width:43px;height:43px;min-width:43px;cursor:pointer;}
.extension-review-shell .form-text {margin-top:8px;color:#758178;font-size:9.5px;line-height:1.45;}
.extension-review-shell .review-action-btn:disabled {opacity:.54;cursor:not-allowed;background:#587663;border-color:#587663;}
.extension-review-shell .review-link-summary {
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
    border:1px solid #e6eee8;border-radius:11px;padding:12px 14px;background:#fafcfa;margin:16px 0;
}
.extension-review-shell .review-link-summary strong {display:block;color:#263229;font-size:10.5px;line-height:1.4;}
.extension-review-shell .review-link-summary small {display:block;margin-top:4px;color:#748078;font-size:9.5px;}
.extension-review-shell .review-form-row {margin-bottom:16px;}
.extension-review-shell .review-form-row:last-child {margin-bottom:0;}
@media (max-width:520px) {.extension-review-link-row {gap:7px;}}
</style>

<div class="main-page rems-extension-dark-scope extension-review-shell">
    <header class="asu-topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="extension-review-brand">
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

    <main class="page-content extension-review-page">
        <div class="extension-review-inner">
            <div class="extension-review-hero">
                <div>
                    <div class="extension-review-kicker">Extension Proposal Processing</div>
                    <h1>LADCO/UADCO/BOR</h1>
                    <p>Record official LADCO/UADCO approval and BOR notation decisions for endorsed extension proposals.</p>
                </div>
                <a class="review-back-btn" href="<?= BASE_URL; ?>/extension/index.php">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Extension
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Please review the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success); ?></div>
            <?php endif; ?>

            <?php if ($proposal): ?>
                <section class="extension-review-card">
                    <div class="extension-review-card-header">
                        <h2 class="extension-review-card-title">
                            <span class="extension-review-card-icon"><i class="bi bi-award" aria-hidden="true"></i></span>
                            LADCO/UADCO/BOR Details
                        </h2>
                        <a class="review-eye-btn" href="<?= BASE_URL; ?>/extension/view.php?id=<?= (int) $proposal['extension_id']; ?>"
                           title="View proposal" aria-label="View proposal details">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="extension-review-card-body">
                        <div class="extension-review-proposal-heading">
                            <div>
                                <h2><?= e($proposal['program_title']); ?></h2>
                                <span class="extension-review-status"><?= e(in_array((string)$proposal['status'], [EXT_STATUS_LADCO_APPROVAL, EXT_STATUS_BOR_NOTATION], true)
                                    ? 'For LADCO/UADCO/BOR'
                                    : extensionStatusLabel((string)$proposal['status'])); ?></span>
                            </div>
                        </div>
                        <div class="extension-review-meta">
                            <div class="extension-review-meta-item">
                                <small>Proponent</small>
                                <strong><?= e(trim($proposal['first_name'] . ' ' . ($proposal['middle_name'] ?? '') . ' ' . $proposal['last_name'])); ?></strong>
                            </div>
                            <div class="extension-review-meta-item">
                                <small>Target Community</small>
                                <strong><?= e($proposal['target_community'] ?: 'Not specified'); ?></strong>
                            </div>
                        </div>

                        <?php if ($endorsement): ?>
                            <div class="review-link-summary">
                                <div>
                                    <strong>Latest Official Endorsement</strong>
                                    <small>
                                        Recorded on <?= e(date('M d, Y', strtotime((string)$endorsement['endorsement_date']))); ?>
                                    </small>
                                </div>
                                <?php if (!empty($endorsement['endorsement_document_url'])): ?>
                                    <a class="review-eye-btn"
                                       href="<?= e($endorsement['endorsement_document_url']); ?>"
                                       target="_blank" rel="noopener noreferrer"
                                       title="View endorsement document" aria-label="View endorsement document">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($stage !== null): ?>
                            <hr>
                            <h3 class="extension-review-subtitle">Record Official LADCO/UADCO/BOR</h3>
                            <p class="extension-review-helper">
                                <?= $stage['stage'] === 'BOR'
                                    ? 'Complete the remaining BOR notation for this proposal, which was processed under the previous workflow. The proposal then proceeds directly to Certification.'
                                    : 'Record the official LADCO/UADCO approval and BOR notation together. Once both decisions are documented, the proposal will proceed directly to For Certification.'; ?>
                            </p>
                            <form method="POST" id="approvalForm"
                                  action="<?= BASE_URL; ?>/extension/approval.php?id=<?= (int) $proposal['extension_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">
                                <input type="hidden" name="extension_id" value="<?= (int) $proposal['extension_id']; ?>">
                                <input type="hidden" name="action" value="record_approval">

                                <div class="extension-review-form-grid">
                                    <?php if ($stage['stage'] !== 'BOR'): ?>
                                        <div>
                                            <label class="form-label" for="ladcoDate">LADCO/UADCO Approval Date *</label>
                                            <input id="ladcoDate" type="date" name="ladco_date" class="form-control"
                                                   max="<?= e(date('Y-m-d')); ?>" value="<?= e($ladcoDate); ?>" required>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <label class="form-label" for="approvalDate">BOR Notation Date *</label>
                                        <input id="approvalDate" type="date" name="decision_date" class="form-control"
                                               max="<?= e(date('Y-m-d')); ?>" value="<?= e($decisionDate); ?>" required>
                                    </div>
                                </div>
                                <div class="review-form-row">
                                    <label class="form-label" for="approvalReference">BOR Resolution Number *</label>
                                    <input id="approvalReference" type="text" name="reference_number"
                                           class="form-control" maxlength="150" value="<?= e($reference); ?>" required>
                                </div>

                                <div class="review-form-row">
                                    <label class="form-label" for="approvalUrl">Official Supporting Document HTTPS Link *</label>
                                    <div class="extension-review-link-row">
                                        <input id="approvalUrl" type="url" name="document_url"
                                               class="form-control" placeholder="https://" pattern="https://.*"
                                               maxlength="1000" value="<?= e($documentUrl); ?>" required>
                                        <button id="viewApprovalLink" type="button" class="review-eye-btn"
                                                title="View supporting document link" aria-label="View supporting document link">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">You must click the eye button to view the link before submitting.</div>
                                </div>

                                <div class="review-form-row">
                                    <label class="form-label" for="approvalRemarks">Remarks</label>
                                    <textarea id="approvalRemarks" name="remarks" class="form-control" rows="4"
                                              maxlength="20000" placeholder="Optional official remarks"><?= e($remarks); ?></textarea>
                                </div>
                                <div class="extension-review-form-actions">
                                    <button id="submitApproval" type="submit" class="review-action-btn" disabled>
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                        Record LADCO/UADCO/BOR &amp; Proceed to Certification
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($records): ?>
                            <div class="extension-review-inline-notice">
                                LADCO/UADCO/BOR processing is complete. The proposal is ready for Certification.
                            </div>
                        <?php endif; ?>

                        <?php if ($proposal['status'] === EXT_STATUS_CERTIFICATION): ?>
                            <div class="extension-review-form-actions">
                                <a class="review-action-btn"
                                   href="<?= BASE_URL; ?>/extension/certification.php?id=<?= (int) $proposal['extension_id']; ?>">
                                    Record Certification <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <section class="extension-review-card review-unavailable">
                    <?php if ($selectedId !== null): ?>
                        <p>This extension proposal is unavailable for this approval or notation stage.</p>
                    <?php else: ?>
                        <p>Select a proposal from Proposal Processing to record LADCO/UADCO/BOR and proceed to Certification.</p>
                    <?php endif; ?>
                    <a class="review-action-btn" href="<?= BASE_URL; ?>/extension/index.php?queue=ladco">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Proposal Processing
                    </a>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($proposal && $stage !== null): ?>
<script>
(function () {
    'use strict';
    const form = document.getElementById('approvalForm');
    const input = document.getElementById('approvalUrl');
    const eye = document.getElementById('viewApprovalLink');
    const submit = document.getElementById('submitApproval');
    if (!form || !input || !eye || !submit) return;

    let viewedUrl = '';

    input.addEventListener('input', function () {
        if (input.value.trim() !== viewedUrl) {
            viewedUrl = '';
            submit.disabled = true;
        }
    });

    eye.addEventListener('click', function () {
        const url = input.value.trim();
        if (!input.checkValidity()) {
            input.reportValidity();
            return;
        }
        try {
            const parsed = new URL(url);
            if (parsed.protocol !== 'https:' || !parsed.hostname) {
                throw new Error('Invalid HTTPS link');
            }
        } catch (error) {
            input.setCustomValidity('Enter a valid HTTPS link.');
            input.reportValidity();
            input.setCustomValidity('');
            return;
        }

        // Explicit action URL: avoids form.action name-clash with hidden input.
        const viewForm = document.createElement('form');
        viewForm.method = 'POST';
        viewForm.action = form.getAttribute('action');
        viewForm.target = '_blank';
        viewForm.style.display = 'none';

        const fields = {
            action: 'confirm_view',
            csrf_token: form.elements['csrf_token'].value,
            extension_id: form.elements['extension_id'].value,
            document_url: url
        };
        Object.keys(fields).forEach(function (name) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = fields[name];
            viewForm.appendChild(hidden);
        });
        document.body.appendChild(viewForm);
        viewForm.submit();
        viewForm.remove();

        viewedUrl = url;
        submit.disabled = false;
    });

    form.addEventListener('submit', function (event) {
        if (!viewedUrl || viewedUrl !== input.value.trim()) {
            event.preventDefault();
            submit.disabled = true;
            alert('You must click the eye button to view the link before submitting.');
        }
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
