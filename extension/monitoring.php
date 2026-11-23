<?php
/**
 * ASU REMS — Extension Module
 * File: extension/monitoring.php
 * Section 4.3 — Monitoring and Evaluation of Extension Program/Projects
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/extension_roles.php';

requireLogin();
$user = currentUser();
$role = (string)($user['role'] ?? '');
$isOffice = $role === 'extension_service_office';
$isExtensionist = in_array($role, ['extensionist', 'extension_coordinator'], true);

if (!$isOffice && !$isExtensionist) {
    redirect(BASE_URL . '/dashboard.php');
}

$sections = $isOffice ? [
    'monitoring_requests' => [
        'title' => 'Monitoring Requests',
        'description' => 'Review monitoring requests submitted by Extensionists.'
    ],
    'monitoring_schedule' => [
        'title' => 'Monitoring Schedule',
        'description' => 'View and coordinate approved monitoring activities.'
    ],
    'monitoring_reports' => [
        'title' => 'Monitoring Reports',
        'description' => 'Receive monitoring reports and complete monitoring records.'
    ],
] : [
    'request_monitoring' => [
        'title' => 'Request Monitoring',
        'description' => 'Complete the Program / Project Monitoring and Evaluation Form for an extension program or project.'
    ],
    'my_monitoring_requests' => [
        'title' => 'My Monitoring Requests',
        'description' => 'View the status and details of your monitoring requests.'
    ],
    'submit_monitoring_report' => [
        'title' => 'Submit Monitoring Report',
        'description' => 'Submit the monitoring report after the monitoring activity is conducted.'
    ],
];

$section = (string)($_GET['section'] ?? array_key_first($sections));
if (!isset($sections[$section])) {
    $section = array_key_first($sections);
}
$pageTitle = $sections[$section]['title'] . ' | Monitoring & Evaluation';
$activePage = 'extension_monitoring';

function monitoringEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function monitoringRedirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function monitoringDate(?string $value, string $format = 'M d, Y'): string
{
    if (!$value) {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : '—';
}

$errors = [];
$success = '';
$rows = [];
$viewRequest = null;
$draftId = 0;
$revisionId = 0;
$revision = null;
$officeErrors = [];
$officeSuccess = '';
$officeRows = [];
$officeViewRequest = null;
$csrfToken = '';

$form = [
    'project_code' => '',
    'project_title' => '',
    'college_unit' => '',
    'target_community' => '',
    'monitoring_purpose' => '',
    'preferred_date' => '',
    'preferred_location' => '',
    'request_letter_url' => '',
    // Fields from ASU Project Monitoring and Evaluation Form.
    'program_title' => '',
    'project_duration' => '',
    'monitored_by' => '',
    'reviewed_validated_by' => '',
];

/** Columns from the printed ASU Extension Service Project Monitoring and Evaluation Form. */
function monitoringPaperColumns(): array
{
    return [
        'specific_learning_objectives' => 'Specific Learning Objectives',
        'planned_activities' => 'Planned Activities',
        'activities_conducted' => 'Activities Conducted',
        'degree_of_accomplishment' => 'Degree of Accomplishment',
        'date_conducted' => 'Date Conducted',
        'project_activity_location' => 'Program / Project Activity Location',
        'responsible_person_unit_agency' => 'Responsible Person/Unit/Agency',
        'problems_encountered' => 'Problem(s) Encountered',
        'budget_allocation' => 'Budget - Allocation',
        'budget_utilization' => 'Budget - Utilization',
        'recommendation' => 'Recommendation',
    ];
}

/** One blank entry by default; more are added only when requested. */
function monitoringPaperBlankRows(): array
{
    return [array_fill_keys(array_keys(monitoringPaperColumns()), '')];
}

/** Read a variable number of entries, including older records saved with three rows. */
function monitoringPaperDecode($json): array
{
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) {
        return monitoringPaperBlankRows();
    }
    $rows = [];
    foreach (array_slice(array_values($decoded), 0, 20) as $source) {
        if (!is_array($source)) {
            continue;
        }
        $entry = array_fill_keys(array_keys(monitoringPaperColumns()), '');
        foreach ($entry as $key => &$value) {
            $value = trim((string)($source[$key] ?? ''));
        }
        unset($value);
        if (array_filter($entry, static fn($value) => $value !== '')) {
            $rows[] = $entry;
        }
    }
    return $rows ?: monitoringPaperBlankRows();
}

// Show the paper's content on both the Extensionist and ESO details pages.
function monitoringPaperDetailHtml(array $record): void
{
    $rows = monitoringPaperDecode($record['monitoring_entries_json'] ?? '');
    ?>
    <div class="row g-3 mb-3 monitoring-readonly-overview">
        <?php
            // Older records may have separate program/project names. Keep both visible when different.
            $savedProgram = trim((string)($record['program_title'] ?? ''));
            $savedProject = trim((string)($record['project_title'] ?? ''));
            $combinedTitle = ($savedProgram !== '' && $savedProject !== '' && $savedProgram !== $savedProject)
                ? $savedProgram . ' / ' . $savedProject
                : ($savedProject !== '' ? $savedProject : $savedProgram);
        ?>
        <div class="col-md-6 monitoring-key-detail">
            <div class="small text-muted fw-semibold monitoring-key-label">PROGRAM / PROJECT TITLE</div>
            <div class="monitoring-key-value"><?= monitoringEsc($combinedTitle !== '' ? $combinedTitle : '—'); ?></div>
        </div>
        <div class="col-md-6 monitoring-key-detail">
            <div class="small text-muted fw-semibold monitoring-key-label">PROGRAM / PROJECT DURATION</div>
            <div class="monitoring-key-value"><?= monitoringEsc(($record['project_duration'] ?? '') ?: '—'); ?></div>
        </div>
    </div>
    <div class="monitoring-activity-list mb-3">
        <?php foreach ($rows as $rowIndex => $entry): ?>
            <section class="monitoring-activity-section monitoring-activity-readonly">
                <div class="monitoring-activity-header">
                    <h3><?= $rowIndex === 0 ? 'Monitoring Details' : 'Additional Monitoring Entry'; ?></h3>
                </div>
                <div class="row g-3 monitoring-activity-fields">
                    <?php foreach (monitoringPaperColumns() as $key => $label): ?>
                        <?php // Balanced pairs on desktop; Recommendation uses a full row because it has no partner field. ?>
                        <div class="monitoring-activity-field <?= $key === 'recommendation' ? 'col-12' : 'col-md-6'; ?>">
                            <div class="monitoring-activity-label small text-muted fw-semibold"><?= monitoringEsc($label); ?></div>
                            <div class="monitoring-activity-value"><?= nl2br(monitoringEsc($entry[$key] !== '' ? $entry[$key] : '—')); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="row g-3 monitoring-readonly-footer">
        <div class="col-md-6 monitoring-key-detail">
            <div class="small text-muted fw-semibold monitoring-key-label">MONITORED BY</div>
            <div class="monitoring-key-value"><?= monitoringEsc(($record['monitored_by'] ?? '') ?: '—'); ?></div>
        </div>
        <div class="col-md-6 monitoring-key-detail">
            <div class="small text-muted fw-semibold monitoring-key-label">REVIEWED AND VALIDATED</div>
            <div class="monitoring-key-value"><?= monitoringEsc(($record['reviewed_validated_by'] ?? '') ?: '—'); ?></div>
        </div>
    </div>
    <?php
}

// Show a clear migration reminder instead of silently losing new monitoring fields.
$monitoringPaperSchemaReady = false;
if ($isExtensionist && $section === 'request_monitoring') {
    try {
        $columnCheck = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extension_monitoring_requests' AND COLUMN_NAME IN ('program_title', 'project_duration', 'monitoring_entries_json', 'monitored_by', 'reviewed_validated_by')");
        $monitoringPaperSchemaReady = (int)$columnCheck->fetchColumn() === 5;
    } catch (Throwable $e) {
        error_log('Monitoring paper schema check: ' . $e->getMessage());
    }
}

$monitoringPaperRows = monitoringPaperBlankRows();

if ($isExtensionist) {
    if (empty($_SESSION['monitoring_request_csrf'])) {
        $_SESSION['monitoring_request_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string)$_SESSION['monitoring_request_csrf'];
}

if ($isOffice) {
    if (empty($_SESSION['monitoring_office_csrf'])) {
        $_SESSION['monitoring_office_csrf'] = bin2hex(random_bytes(32));
    }
    $officeCsrf = (string)$_SESSION['monitoring_office_csrf'];
}

if ($isExtensionist && $section === 'request_monitoring') {
    $draftId = filter_input(INPUT_GET, 'draft', FILTER_VALIDATE_INT) ?: 0;
    $revisionId = filter_input(INPUT_GET, 'revise', FILTER_VALIDATE_INT) ?: 0;
    if ($draftId > 0) {
        $draftStmt = $pdo->prepare("SELECT * FROM extension_monitoring_requests WHERE request_id = ? AND requester_id = ? AND status = 'Draft' LIMIT 1");
        $draftStmt->execute([$draftId, (int)$user['user_id']]);
        $draft = $draftStmt->fetch(PDO::FETCH_ASSOC);
        if (!$draft) {
            http_response_code(404);
            exit('Monitoring request draft not found.');
        }
        foreach ($form as $key => $value) {
            if (isset($draft[$key])) {
                $form[$key] = (string)$draft[$key];
            }
        }
        $monitoringPaperRows = monitoringPaperDecode($draft['monitoring_entries_json'] ?? '');
    }

    if ($revisionId > 0) {
        $revisionStmt = $pdo->prepare("SELECT * FROM extension_monitoring_requests WHERE request_id = ? AND requester_id = ? AND status = 'Needs Information' LIMIT 1");
        $revisionStmt->execute([$revisionId, (int)$user['user_id']]);
        $revision = $revisionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$revision) {
            http_response_code(404);
            exit('This monitoring request is not awaiting additional information.');
        }
        foreach ($form as $key => $value) {
            if (isset($revision[$key])) {
                $form[$key] = (string)$revision[$key];
            }
        }
        $monitoringPaperRows = monitoringPaperDecode($revision['monitoring_entries_json'] ?? '');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            $errors[] = 'Your session token is invalid. Refresh the page and try again.';
        }
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['save_draft', 'submit_request', 'edit_resubmit'], true)) {
            $errors[] = 'Invalid form action.';
        }

        if (!$monitoringPaperSchemaReady) {
            $errors[] = 'Import extension_monitoring_paper_fields.sql in phpMyAdmin before saving this form.';
        }
        // One Program / Project Title input, synchronized to both legacy DB columns.
        // Keeping project_title populated preserves ESO lists, searches and existing workflows.
        foreach (['project_title', 'project_duration', 'monitored_by', 'reviewed_validated_by'] as $field) {
            $form[$field] = trim((string)($_POST[$field] ?? ''));
            if (mb_strlen($form[$field]) > 220) {
                $errors[] = 'One of the monitoring form fields exceeds 220 characters.';
            }
        }
        $form['program_title'] = $form['project_title'];
        $submittedRows = $_POST['entries'] ?? [];
        if (!is_array($submittedRows)) {
            $submittedRows = [];
        }
        // Preserve every user-added entry, rather than truncating to three rows.
        if (count($submittedRows) > 20) {
            $errors[] = 'A maximum of 20 monitoring entries may be added.';
        }
        $monitoringPaperRows = [];
        foreach (array_slice(array_values($submittedRows), 0, 20) as $i => $submitted) {
            $submitted = is_array($submitted) ? $submitted : [];
            $entry = array_fill_keys(array_keys(monitoringPaperColumns()), '');
            foreach (monitoringPaperColumns() as $key => $label) {
                $value = trim((string)($submitted[$key] ?? ''));
                if (mb_strlen($value) > 2000) {
                    $errors[] = $label . ' (entry ' . ($i + 1) . ') cannot exceed 2000 characters.';
                }
                if ($key === 'date_conducted' && $value !== '') {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $errors[] = 'Enter a valid Date Conducted in entry ' . ($i + 1) . '.';
                    }
                }
                $entry[$key] = $value;
            }
            $monitoringPaperRows[] = $entry;
        }
        if (!$monitoringPaperRows) {
            $monitoringPaperRows = monitoringPaperBlankRows();
        }

        if (in_array($action, ['submit_request', 'edit_resubmit'], true)) {
            if ($form['project_title'] === '') {
                $errors[] = 'Enter the Program / Project Title.';
            }
            if ($form['project_duration'] === '') {
                $errors[] = 'Enter the Program / Project Duration.';
            }
        }

        if (!$errors) {
            $status = in_array($action, ['submit_request', 'edit_resubmit'], true) ? 'Submitted' : 'Draft';
            $values = [
                // Preserve the legacy columns for earlier monitoring requests.
                null, // project_code
                $form['project_title'] !== '' ? $form['project_title'] : null,
                null, // college_unit
                null, // target_community
                null, // monitoring_purpose
                null, // preferred_date
                null, // preferred_location
                null, // request_letter_url
                $form['program_title'] !== '' ? $form['program_title'] : null,
                $form['project_duration'] !== '' ? $form['project_duration'] : null,
                json_encode(array_values(array_filter($monitoringPaperRows,
                    static fn($entry) => (bool) array_filter($entry, static fn($value) => $value !== '')
                )) ?: monitoringPaperBlankRows(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $form['monitored_by'] !== '' ? $form['monitored_by'] : null,
                $form['reviewed_validated_by'] !== '' ? $form['reviewed_validated_by'] : null,
            ];

            if ($revisionId > 0) {
                if ($action !== 'edit_resubmit') {
                    $errors[] = 'Submit the updated request to return it to the ESO.';
                } else {
                    $saveStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET project_code = ?, project_title = ?, college_unit = ?, target_community = ?, monitoring_purpose = ?, preferred_date = ?, preferred_location = ?, request_letter_url = ?, program_title = ?, project_duration = ?, monitoring_entries_json = ?, monitored_by = ?, reviewed_validated_by = ?, status = 'Submitted', submitted_at = NOW(), office_remarks = NULL, updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status = 'Needs Information'");
                    $saveStmt->execute([...$values, $revisionId, (int)$user['user_id']]);
                    $requestId = $revisionId;
                }
            } elseif ($draftId > 0) {
                $saveStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET project_code = ?, project_title = ?, college_unit = ?, target_community = ?, monitoring_purpose = ?, preferred_date = ?, preferred_location = ?, request_letter_url = ?, program_title = ?, project_duration = ?, monitoring_entries_json = ?, monitored_by = ?, reviewed_validated_by = ?, status = ?, submitted_at = CASE WHEN ? = 'Submitted' THEN NOW() ELSE NULL END, updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status = 'Draft'");
                $saveStmt->execute([...$values, $status, $status, $draftId, (int)$user['user_id']]);
                $requestId = $draftId;
            } else {
                $saveStmt = $pdo->prepare("INSERT INTO extension_monitoring_requests (requester_id, project_code, project_title, college_unit, target_community, monitoring_purpose, preferred_date, preferred_location, request_letter_url, program_title, project_duration, monitoring_entries_json, monitored_by, reviewed_validated_by, status, submitted_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'Submitted' THEN NOW() ELSE NULL END, NOW(), NOW())");
                $saveStmt->execute([(int)$user['user_id'], ...$values, $status, $status]);
                $requestId = (int)$pdo->lastInsertId();
            }

            if (!$errors) {
                $requestCode = 'MR-' . date('Y') . '-' . str_pad((string)$requestId, 5, '0', STR_PAD_LEFT);
                $codeStmt = $pdo->prepare('UPDATE extension_monitoring_requests SET request_code = ? WHERE request_id = ? AND requester_id = ?');
                $codeStmt->execute([$requestCode, $requestId, (int)$user['user_id']]);
                monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=request_monitoring&saved=' . ($status === 'Draft' ? 'draft' : 'submitted'));
            }
        }
    }

    if (isset($_GET['saved'])) {
        $success = $_GET['saved'] === 'submitted'
            ? 'Your monitoring request has been submitted to the Extension Service Office.'
            : 'Your monitoring request draft has been saved.';
    }
}

if ($isExtensionist && $section === 'my_monitoring_requests') {
    $viewId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
    if ($viewId > 0) {
        $viewStmt = $pdo->prepare('SELECT * FROM extension_monitoring_requests WHERE request_id = ? AND requester_id = ? LIMIT 1');
        $viewStmt->execute([$viewId, (int)$user['user_id']]);
        $viewRequest = $viewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $listStmt = $pdo->prepare('SELECT request_id, request_code, project_code, project_title, college_unit, target_community, preferred_date, preferred_location, status, submitted_at, created_at FROM extension_monitoring_requests WHERE requester_id = ? ORDER BY COALESCE(submitted_at, created_at) DESC, request_id DESC');
    $listStmt->execute([(int)$user['user_id']]);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isExtensionist && $section === 'submit_monitoring_report') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_report_link') {
        header('Content-Type: application/json; charset=utf-8');
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        $url = trim((string)($_POST['monitoring_report_url'] ?? ''));
        $parts = parse_url($url);
        $validHttps = $parts && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
        if (!hash_equals($csrfToken, $postedToken) || !$validHttps) {
            http_response_code(400);
            echo json_encode(['ok' => false]);
            exit;
        }
        $_SESSION['monitoring_report_viewed_link_hash'] = hash('sha256', $url);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            $errors[] = 'Your session token is invalid. Refresh the page and try again.';
        }
        if (($_POST['action'] ?? '') !== 'submit_monitoring_report') {
            $errors[] = 'Invalid monitoring report action.';
        }
        $reportRequestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $reportUrl = trim((string)($_POST['monitoring_report_url'] ?? ''));
        $parts = parse_url($reportUrl);
        $validHttps = $parts && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
        if (!$validHttps) {
            $errors[] = 'Enter a valid HTTPS link to the monitoring report.';
        } elseif (empty($_SESSION['monitoring_report_viewed_link_hash']) || !hash_equals((string)$_SESSION['monitoring_report_viewed_link_hash'], hash('sha256', $reportUrl))) {
            $errors[] = 'You must click the eye button to view the report link before submitting.';
        }
        if ($reportRequestId < 1) {
            $errors[] = 'Select the monitoring activity this report belongs to.';
        }
        if (!$errors) {
            $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET monitoring_report_url = ?, status = 'Report Submitted', report_submitted_at = NOW(), updated_at = NOW() WHERE request_id = ? AND requester_id = ? AND status = 'Monitoring Conducted'");
            $updateStmt->execute([$reportUrl, $reportRequestId, (int)$user['user_id']]);
            if ($updateStmt->rowCount() > 0) {
                unset($_SESSION['monitoring_report_viewed_link_hash']);
                monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=submit_monitoring_report&saved=submitted');
            }
            $errors[] = 'This request is not ready for a monitoring report.';
        }
    }

    if (($_GET['saved'] ?? '') === 'submitted') {
        $success = 'Your monitoring report has been submitted to the Extension Service Office.';
    }
    $reportReadyStmt = $pdo->prepare("SELECT request_id, request_code, project_title, scheduled_date, scheduled_location FROM extension_monitoring_requests WHERE requester_id = ? AND status = 'Monitoring Conducted' ORDER BY scheduled_date DESC, request_id DESC");
    $reportReadyStmt->execute([(int)$user['user_id']]);
    $reportReadyRows = $reportReadyStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isOffice) {
    if ($section === 'monitoring_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_me_form_link') {
        header('Content-Type: application/json; charset=utf-8');
        $postedToken = (string)($_POST['csrf_token'] ?? '');
        $confirmedRequestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $url = trim((string)($_POST['me_form_url'] ?? ''));
        $parts = parse_url($url);
        $validHttps = $parts && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
        $confirmationStmt = $pdo->prepare("SELECT request_id FROM extension_monitoring_requests WHERE request_id = ? AND status = 'Scheduled' LIMIT 1");
        $confirmationStmt->execute([$confirmedRequestId]);
        $isScheduledRequest = (bool)$confirmationStmt->fetchColumn();
        if (!hash_equals($officeCsrf, $postedToken) || !$validHttps || !$isScheduledRequest) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'This request must have a confirmed schedule and a valid HTTPS link.']);
            exit;
        }
        $_SESSION['monitoring_me_form_viewed_hash'] = hash('sha256', $url);
        $_SESSION['monitoring_me_form_viewed_request_id'] = $confirmedRequestId;
        echo json_encode(['ok' => true]);
        exit;
    }

    if (in_array($section, ['monitoring_requests', 'monitoring_schedule', 'monitoring_reports'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($officeCsrf, (string)($_POST['csrf_token'] ?? ''))) {
            $officeErrors[] = 'Your session token is invalid. Refresh the page and try again.';
        } else {
            $action = (string)($_POST['action'] ?? '');
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            $requestStmt = $pdo->prepare('SELECT status FROM extension_monitoring_requests WHERE request_id = ? LIMIT 1');
            $requestStmt->execute([$requestId]);
            $selected = $requestStmt->fetch(PDO::FETCH_ASSOC);
            if (!$selected) {
                $officeErrors[] = 'The monitoring request could not be found.';
            } else {
                $currentStatus = (string)$selected['status'];
                if ($action === 'request_information' && in_array($currentStatus, ['Submitted', 'Needs Information'], true)) {
                    $remarks = trim((string)($_POST['office_remarks'] ?? ''));
                    if ($remarks === '') {
                        $officeErrors[] = 'Enter the additional information needed from the Extensionist.';
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET status = 'Needs Information', office_remarks = ?, updated_at = NOW() WHERE request_id = ?");
                        $updateStmt->execute([$remarks, $requestId]);
                        monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_requests&saved=request_information');
                    }
                } elseif ($action === 'endorse_request' && $currentStatus === 'Submitted') {
                    $remarks = trim((string)($_POST['office_remarks'] ?? ''));
                    $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET status = 'Endorsed', office_remarks = ?, updated_at = NOW() WHERE request_id = ? AND status = 'Submitted'");
                    $updateStmt->execute([$remarks !== '' ? $remarks : null, $requestId]);
                    monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_schedule&view=' . $requestId . '&saved=endorsed');
                } elseif ($section === 'monitoring_schedule' && $action === 'save_schedule' && $currentStatus === 'Endorsed') {
                    $unit = trim((string)($_POST['assigned_unit'] ?? ''));
                    $personnel = trim((string)($_POST['assigned_personnel'] ?? ''));
                    $dateValue = trim((string)($_POST['scheduled_date'] ?? ''));
                    $timeValue = trim((string)($_POST['scheduled_time'] ?? ''));
                    $location = trim((string)($_POST['scheduled_location'] ?? ''));
                    $validDate = DateTime::createFromFormat('Y-m-d', $dateValue);
                    $validTime = DateTime::createFromFormat('H:i', $timeValue);
                    if ($unit === '' || $personnel === '' || !$validDate || $validDate->format('Y-m-d') !== $dateValue || !$validTime || $validTime->format('H:i') !== $timeValue || $location === '') {
                        $officeErrors[] = 'Complete the assigned unit, personnel, schedule date, time, and location.';
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET assigned_unit = ?, assigned_personnel = ?, scheduled_date = ?, scheduled_time = ?, scheduled_location = ?, status = 'Scheduled', updated_at = NOW() WHERE request_id = ? AND status = 'Endorsed'");
                        $updateStmt->execute([$unit, $personnel, $dateValue, $timeValue, $location, $requestId]);
                        if ($updateStmt->rowCount() === 1) {
                            // Keep the ESO on this request so the next step is visible immediately.
                            monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_schedule&view=' . $requestId . '&saved=schedule');
                        }
                        $officeErrors[] = 'The schedule was not saved. Reload the request and check its current status before trying again.';
                    }
                } elseif ($section === 'monitoring_schedule' && $action === 'record_monitoring_conducted' && $currentStatus === 'Scheduled') {
                    $meUrl = trim((string)($_POST['me_form_url'] ?? ''));
                    $parts = parse_url($meUrl);
                    $validHttps = $parts && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
                    if (!$validHttps) {
                        $officeErrors[] = 'Enter a valid HTTPS link to the completed M&E Form (ASU ES No. 25).';
                    } elseif (empty($_SESSION['monitoring_me_form_viewed_hash'])
                        || (int)($_SESSION['monitoring_me_form_viewed_request_id'] ?? 0) !== $requestId
                        || !hash_equals((string)$_SESSION['monitoring_me_form_viewed_hash'], hash('sha256', $meUrl))) {
                        $officeErrors[] = 'Click the eye button to view this request’s M&E Form link before recording the activity.';
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET me_form_url = ?, status = 'Monitoring Conducted', monitoring_conducted_at = NOW(), monitoring_conducted_by = ?, updated_at = NOW() WHERE request_id = ? AND status = 'Scheduled'");
                        $updateStmt->execute([$meUrl, (int)$user['user_id'], $requestId]);
                        if ($updateStmt->rowCount() === 1) {
                            unset($_SESSION['monitoring_me_form_viewed_hash'], $_SESSION['monitoring_me_form_viewed_request_id']);
                            // Show the now-read-only record, rather than returning to the schedule list.
                            monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_schedule&view=' . $requestId . '&saved=conducted');
                        }
                        $officeErrors[] = 'The M&E link was not recorded. Reload the request and check its current status.';
                    }
                } elseif ($section === 'monitoring_reports' && $action === 'receive_report' && $currentStatus === 'Report Submitted') {
                    $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET status = 'Report Received', report_received_at = NOW(), report_received_by = ?, updated_at = NOW() WHERE request_id = ? AND status = 'Report Submitted'");
                    $updateStmt->execute([(int)$user['user_id'], $requestId]);
                    monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_reports&view=' . $requestId . '&saved=received');
                } elseif ($section === 'monitoring_reports' && $action === 'mark_completed' && $currentStatus === 'Report Received') {
                    $updateStmt = $pdo->prepare("UPDATE extension_monitoring_requests SET status = 'Completed', completed_at = NOW(), completed_by = ?, updated_at = NOW() WHERE request_id = ? AND status = 'Report Received'");
                    $updateStmt->execute([(int)$user['user_id'], $requestId]);
                    monitoringRedirect(BASE_URL . '/extension/monitoring.php?section=monitoring_reports&saved=completed');
                } else {
                    $officeErrors[] = 'This action is not available for the request’s current status.';
                }
            }
        }
    }

    if (($_GET['saved'] ?? '') === 'request_information') {
        $officeSuccess = 'Additional information was requested from the Extensionist.';
    } elseif (($_GET['saved'] ?? '') === 'endorsed') {
        $officeSuccess = 'The monitoring request was endorsed.';
    } elseif (($_GET['saved'] ?? '') === 'schedule') {
        $officeSuccess = 'The monitoring personnel and schedule were saved.';
    } elseif (($_GET['saved'] ?? '') === 'conducted') {
        $officeSuccess = 'The monitoring activity and ASU ES No. 25 were recorded.';
    } elseif (($_GET['saved'] ?? '') === 'received') {
        $officeSuccess = 'The monitoring report was received.';
    } elseif (($_GET['saved'] ?? '') === 'completed') {
        $officeSuccess = 'The monitoring record was marked as completed.';
    }

    $officeViewId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
    if ($officeViewId > 0) {
        $viewStmt = $pdo->prepare('SELECT * FROM extension_monitoring_requests WHERE request_id = ? LIMIT 1');
        $viewStmt->execute([$officeViewId]);
        $officeViewRequest = $viewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($section === 'monitoring_requests') {
        $listStmt = $pdo->query("SELECT request_id, request_code, project_code, project_title, college_unit, target_community, preferred_date, preferred_location, status, submitted_at, created_at FROM extension_monitoring_requests WHERE status IN ('Submitted', 'Needs Information') ORDER BY COALESCE(submitted_at, created_at) DESC, request_id DESC");
    } elseif ($section === 'monitoring_schedule') {
        $listStmt = $pdo->query("SELECT request_id, request_code, project_title, college_unit, target_community, COALESCE(scheduled_date, preferred_date) AS preferred_date, COALESCE(scheduled_location, preferred_location) AS preferred_location, status, submitted_at, created_at FROM extension_monitoring_requests WHERE status IN ('Endorsed', 'Scheduled') ORDER BY COALESCE(scheduled_date, preferred_date, submitted_at, created_at) ASC, request_id DESC");
    } elseif ($section === 'monitoring_reports') {
        $listStmt = $pdo->query("SELECT request_id, request_code, project_title, college_unit, target_community, scheduled_date AS preferred_date, scheduled_location AS preferred_location, status, submitted_at, created_at FROM extension_monitoring_requests WHERE status IN ('Report Submitted', 'Report Received', 'Completed') ORDER BY updated_at DESC, request_id DESC");
    } else {
        $listStmt = $pdo->query("SELECT request_id, request_code, project_code, project_title, college_unit, target_community, preferred_date, preferred_location, status, submitted_at, created_at FROM extension_monitoring_requests WHERE status <> 'Draft' ORDER BY COALESCE(submitted_at, created_at) DESC, request_id DESC");
    }
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Shared list filters for Monitoring & Evaluation (display-only; workflow unchanged). */
$monitorFilterSearch = trim((string)($_GET['q'] ?? ''));
$monitorFilterStatus = trim((string)($_GET['status'] ?? ''));
$monitorAllStatuses = ['Draft', 'Submitted', 'Needs Information', 'Endorsed', 'Scheduled', 'Monitoring Conducted', 'Report Submitted', 'Report Received', 'Completed'];
$monitorStatusesForPage = match ($section) {
    'monitoring_requests' => ['Submitted', 'Needs Information'],
    'monitoring_schedule' => ['Endorsed', 'Scheduled'],
    'monitoring_reports' => ['Report Submitted', 'Report Received', 'Completed'],
    'my_monitoring_requests' => $monitorAllStatuses,
    default => [],
};
if (in_array($section, ['monitoring_requests', 'monitoring_schedule', 'monitoring_reports', 'my_monitoring_requests'], true)) {
    if ($monitorFilterStatus !== '' && !in_array($monitorFilterStatus, $monitorStatusesForPage, true)) {
        $monitorFilterStatus = '';
    }
    $rows = array_values(array_filter($rows, static function (array $record) use ($monitorFilterSearch, $monitorFilterStatus): bool {
        if ($monitorFilterStatus !== '' && (string)($record['status'] ?? '') !== $monitorFilterStatus) {
            return false;
        }
        if ($monitorFilterSearch === '') {
            return true;
        }
        foreach (['request_code', 'project_code', 'project_title', 'college_unit', 'target_community'] as $field) {
            if (stripos((string)($record[$field] ?? ''), $monitorFilterSearch) !== false) {
                return true;
            }
        }
        return false;
    }));
}

$initials = '';
if (!empty($user['first_name'])) {
    $initials .= strtoupper(substr((string)$user['first_name'], 0, 1));
}
if (!empty($user['last_name'])) {
    $initials .= strtoupper(substr((string)$user['last_name'], 0, 1));
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
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope  .page-content,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .call-content,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-review-page,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-create-page,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-management-page,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-details-page {
    background:#0b1d15!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .asu-topbar {
    background:#11271b!important;
     border-bottom:1px solid #365541!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .topbar-user-text strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .system-brand-copy strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .call-brand strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-review-brand strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-create-brand strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-topbar-brandline strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .system-name {
    color:#eef7ef!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .topbar-user-text span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .system-brand-copy span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .call-brand span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-review-brand span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .extension-create-brand span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-topbar-brandline span {
    color:#bdd3c2!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.page-content,.extension-review-page,.extension-create-page,.call-content,.extension-management-page,.extension-details-page) :is(h1,h2,h3,h4,h5,h6) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.call-hero,.extension-review-hero,.extension-form-heading,.page-heading,.rems-view-heading) :is(h1,h2,h3,h4) {
    color:#ffffff!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.card,.asu-card,.asu-panel,.panel,.extension-review-card,.call-card,.extension-form-card,.extension-form-section,.filter-card,.eso-queue-panel,.extension-panel,.extension-status-overview,.extension-document-summary,.extension-document-summary-tile,.extension-overview-row,.extension-official-documents,.workflow-card,.content-block,.document-item,.extension-document-viewer,.extension-preview-message-box,.monitoring-card,.monitoring-filter-card,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.eso-monitor-card,.eso-monitor-detail,.service-request-form-card,.eso-service-card,.eso-service-details,.my-sr-card,.my-sr-details,.my-sr-filter-card,.extension-report-card,.extension-report-details,.call-meta,.extension-review-meta-item,.review-link-summary,.review-form-row,.extension-summary-meta,.my-sr-detail,.monitoring-note,.service-request-note,.extension-upload-intro,.upload-box,.extension-upload-control,.extension-call-link,.extension-form-section-title,.review-unavailable,.monitoring-list-filters,.monitoring-list-toolbar,.my-sr-toolbar,.eso-service-toolbar,.eso-service-filters,.my-sr-filters) {
    background:#162e22!important;
     background-image:none!important;
     border-color:#395b46!important;
     color:#eaf3eb!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.card-header,.panel-header,.extension-review-card-header,.extension-form-header,.call-card-head,.extension-panel-header,.eso-queue-panel-head,.monitoring-request-heading,.service-request-heading,.my-sr-records-head,.eso-service-toolbar,.monitoring-list-toolbar) {
    background:#1b3a2b!important;
     background-image:none!important;
     border-bottom-color:#41604b!important;
     color:#edf7ef!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.card-body,.call-card-body,.extension-review-card-body,.extension-panel-body,.extension-form-body,.monitoring-card .card-body,.service-request-form-card .card-body) {
    background:transparent!important;
     color:#eaf3eb!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-title-cell strong,.extension-record-title,.extension-program-title,.extension-review-card-title,.extension-review-proposal-heading h2,.extension-review-subtitle,.call-card-head h2,.call-record-title,.extension-form-header h2,.extension-form-section-title,.extension-panel-title,.extension-status-overview-content strong,.extension-document-summary-name,.extension-document-summary-info strong,.workflow-text h3,.workflow-text h4,.info-item strong,.my-sr-title,.my-sr-head-title,.my-sr-detail strong,.monitoring-request-heading h2,.service-request-heading h2,.eso-queue-panel-title,.eso-queue-panel-head h2,.panel-title,.call-meta strong,.extension-review-meta-item strong,.monitor-highlight-value,.monitoring-card h2,.extension-report-card h3) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-title-cell small,.extension-coordinator small,.extension-record-muted,.extension-record-meta,.extension-review-helper,.extension-review-meta-item span,.call-description,.call-meta small,.call-card-body p,.extension-form-body .small,.extension-upload-note,.extension-document-summary-meta,.extension-summary-meta span,.info-item label,.document-info small,.workflow-text p,.monitoring-help,.service-request-help,.service-request-note,.my-sr-description,.my-sr-code,.monitoring-note,.my-sr-detail small,.monitor-report-detail .small,.eso-monitor-detail .small,.eso-service-details .small) {
    color:#bdd2c3!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.text-muted,.text-secondary,.form-text,.small.text-muted,p.text-muted,small.text-muted) {
    color:#bed1c3!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.form-label,label:not(.btn),.extension-filter-label,.call-form .form-label) {
    color:#dcebdc!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea.form-control,.input-group-text,.monitoring-search-field,.my-sr-search-field,.eso-service-search-field,.eso-queue-search-input) {
    background-color:#1c392b!important;
     color:#f3f9f2!important;
     border-color:#4c7158!important;
     box-shadow:none!important;
     color-scheme:dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.form-control,input,textarea)::placeholder {
    color:#bbd0be!important;
     opacity:1!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope select.form-select {
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
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope select.form-select option {
    background:#1c392b!important;
     color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope input:is([type="date"],[type="datetime-local"],[type="time"])::-webkit-calendar-picker-indicator {
    filter:invert(1) brightness(1.3);
     opacity:.85;
     cursor:pointer;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.form-control,.form-select,textarea):focus {
    border-color:#83c99d!important;
     box-shadow:0 0 0 2px rgba(91,170,114,.18)!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.table,table,.extension-table,.extension-programs-table,.call-table,.monitoring-table,.eso-service-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table,.my-sr-table) {
    --bs-table-bg:#162e22!important;
     --bs-table-color:#eaf3eb!important;
     --bs-table-border-color:#3a5f48!important;
     --bs-table-striped-bg:#1d3e2d!important;
     --bs-table-hover-bg:#234b36!important;
     color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#3a5f48!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope table :is(th,td) {
    color:#eaf3eb!important;
     background-color:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope table thead :is(th,td) {
    background-color:#213f2d!important;
     color:#d0e5d4!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope table tbody tr:hover :is(td,th) {
    background-color:#214331!important;
     color:#f2f8f1!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.table-responsive,.extension-table-wrapper,.monitoring-table-wrap,.eso-service-table-wrap,.my-sr-table-wrap,.eso-monitor-table-wrap,.monitor-flow-table-wrap,.monitor-report-table-wrap) {
    background:#162e22!important;
     border-color:#395b46!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.border,.border-top,.border-bottom):not(.btn):not(.badge) {
    border-color:#42624b!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(hr,.dropdown-divider) {
    border-color:#44624d!important;
     opacity:.75;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.dropdown-menu,.modal-content,.list-group-item) {
    background:#1a3628!important;
     border-color:#3d5c47!important;
     color:#edf7ee!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.dropdown-item,.dropdown-item-text,.modal-title) {
    color:#edf7ee!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .dropdown-item:hover {
    background:#25503a!important;
     color:#ffffff!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn) {
    background:#203e2c!important;
     background-image:none!important;
     color:#d7f0dc!important;
     border-color:#527861!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.btn-outline-asu,.btn-outline-secondary,.btn-light,.review-eye-btn,.call-icon-button,.extension-eye-action,.extension-document-summary-link,.monitor-document-view-link,.service-document-view-link,.monitor-eye-btn,.eso-service-eye-btn,.my-sr-eye-btn):hover {
    background:#29543c!important;
     color:#ffffff!important;
     border-color:#79b28c!important;
     transform:none!important;
     box-shadow:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.btn-asu,.btn.btn-asu,.review-action-btn,.call-submit,.monitoring-filter-btn,.eso-service-filter-btn) {
    box-shadow:none!important;
     transform:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.alert-success,.call-alert-success) {
    background:#214734!important;
     border-color:#4e8a63!important;
     color:#d8f5df!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.alert-danger,.call-alert-danger,.form-errors) {
    background:#492b2b!important;
     border-color:#915656!important;
     color:#ffdddd!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .alert-warning {
    background:#4b3b23!important;
     border-color:#886d3c!important;
     color:#f4e3b3!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.alert-info,.extension-review-inline-notice,.workflow-notice,.design-notice) {
    background:#203b30!important;
     border-color:#4b7860!important;
     color:#dcefe1!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-empty-state,.call-empty,.eso-queue-empty,.my-sr-empty,.monitoring-empty,.monitor-report-empty,.eso-monitor-empty,.monitor-flow-empty,.extension-report-empty) {
    background:#162e22!important;
     color:#c9dfce!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.extension-empty-state h3,.call-empty h3,.eso-queue-empty h3,.my-sr-empty h3,.monitoring-empty h3) {
    color:#edf7ef!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitoring-track,.my-sr-progress-track,.extension-progress-track,.progress) {
    background-color:#355442!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.progress-bar,.monitoring-value) {
    color:#ffffff!important;
}

html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitoring-card,.eso-monitor-card,.monitor-flow-card,.monitor-report-card,.extension-report-card,.monitoring-list-toolbar,.monitoring-list-filters,.monitor-flow-detail,.eso-monitor-detail,.monitor-report-detail) {background:#172f23!important;border-color:#3e634b!important;color:#edf7ed!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitoring-request-heading,.monitoring-list-toolbar,.monitor-flow-card .card-header,.monitor-report-card .card-header) {background:#1d3b2b!important;color:#eef7ef!important;border-color:#43664e!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td) {border-color:#41624a!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitor-highlight-value,.monitor-flow-detail strong,.monitor-report-detail strong,.monitoring-card .fw-semibold) {color:#ecf8ee!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope :is(.monitoring-help,.monitoring-note,.monitor-flow-detail .small,.monitor-report-detail .small) {color:#c0d4c3!important}

/* ---------------------------------------------------------------
   SERVICE & MONITORING SHARED: ensure complete dark contrast in
   every role/section, not only the create-request section.
   --------------------------------------------------------------- */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell {color:#e7f3e9!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar .rems-topbar-brandline strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar .topbar-user-text strong {color:#f2f8f0!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar .rems-topbar-brandline span,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar .topbar-user-text span {color:#c3d8c8!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar {background:#11271b!important;border-bottom-color:#3a5d45!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-card,.my-sr-filter-card,.eso-service-card,.service-request-form-card,.eso-service-details,.my-sr-details,.eso-monitor-card,.eso-monitor-detail,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.monitoring-card,.extension-report-card) {background:#162f23!important;background-image:none!important;color:#edf7ed!important;border-color:#3c6149!important;box-shadow:none!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-records-head,.eso-service-toolbar,.monitoring-list-toolbar,.service-request-heading,.monitoring-request-heading) {background:#1d3a2b!important;color:#edf8ed!important;border-bottom-color:#456550!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-records-head,.eso-service-toolbar,.monitoring-list-toolbar,.monitoring-card,.monitor-flow-card,.monitor-report-card,.my-sr-card,.eso-service-card) :is(h2,h3,h4) {color:#f0f8f0!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-title,.my-sr-table .my-sr-title,.my-sr-head-title,.monitor-highlight-value,.monitor-flow-detail strong,.monitor-report-detail strong,.eso-service-details strong) {color:#f0f7ee!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-code,.my-sr-description,.monitoring-help,.service-request-help,.monitor-flow-detail .small,.monitor-report-detail .small) {color:#c1d7c6!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.eso-service-grid,.my-sr-detail-grid,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) :is(label,.form-label,.small.text-muted) {color:#c4d9c8!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.eso-service-grid,.my-sr-detail-grid,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) div:not(.badge):not(.btn):not(.input-group):not(.small) {color:inherit}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table,.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) th {background:#244332!important;color:#d9e8da!important;border-color:#3e604a!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table,.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) td {background:#162f23!important;color:#eaf4eb!important;border-color:#3c5c45!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-table,.eso-service-table,.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tr:hover td {background:#1e4230!important;color:#f1f8f1!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-empty,.eso-service-empty,.monitoring-empty,.eso-monitor-empty,.monitor-flow-empty,.monitor-report-empty,.extension-report-empty) {color:#b8d2bf!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-empty,.eso-service-empty,.monitoring-empty,.eso-monitor-empty,.monitor-flow-empty,.monitor-report-empty,.extension-report-empty) :is(strong,h2,h3) {color:#e6f2e8!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-empty,.eso-service-empty,.monitoring-empty,.eso-monitor-empty,.monitor-flow-empty,.monitor-report-empty,.extension-report-empty) :is(p,.small,div) {color:#bdd3c1!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-card,.eso-service-toolbar,.monitoring-list-toolbar) :is(.form-label,label) {color:#d2e3d5!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-card,.eso-service-toolbar,.monitoring-list-toolbar) :is(.form-control,.form-select) {background-color:#1c392b!important;color:#eef7ed!important;border-color:#4b7358!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-card,.eso-service-toolbar,.monitoring-list-toolbar) select.form-select {-webkit-appearance:none!important;appearance:none!important;padding-right:38px!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23DDF2E1' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 15px center!important;background-size:14px 14px!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-reset,.eso-service-reset-btn,.monitoring-reset-btn,.my-sr-back-to-list,.monitor-back-btn) {background:#214630!important;color:#d9efde!important;border-color:#57775e!important;box-shadow:none!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-reset,.eso-service-reset-btn,.monitoring-reset-btn,.my-sr-back-to-list,.monitor-back-btn):hover {background:#2b533a!important;color:#fff!important}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.my-sr-filter-card,.my-sr-card,.eso-service-toolbar,.eso-service-card,.monitoring-list-toolbar,.monitoring-card,.monitor-flow-card,.monitor-report-card) * {transition:none!important;animation:none!important}
</style>
<div class="main-page rems-extension-dark-scope">
    <header class="asu-topbar rems-uniform-topbar">
        <div class="topbar-left rems-topbar-left">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="rems-topbar-brandline">
                <div>
                    <strong>Research &amp; Extension Management System</strong>
                    <span>Aklan State University</span>
                </div>
            </div>
        </div>
        <div class="topbar-user rems-topbar-user">
            <div class="topbar-user-text">
                <strong><?= e(fullName($user)); ?></strong>
                <span><?= e(roleLabel($role)); ?></span>
            </div>
            <div class="user-avatar"><?= e($initials ?: 'U'); ?></div>
        </div>
    </header>

    <main class="page-content">
        <div class="container-fluid rems-view-shell">
            <div class="rems-view-heading" aria-labelledby="remsExtensionPageTitle">
                <div class="rems-view-heading-copy">
                    <span class="rems-view-eyebrow">Monitoring &amp; Evaluation of Extension Programs / Projects</span>
                    <h1 id="remsExtensionPageTitle"><?= e($sections[$section]['title']); ?></h1>
                    <p><?= e($sections[$section]['description']); ?></p>
                </div>
                <a href="<?= BASE_URL; ?>/extension/index.php" class="btn btn-outline-asu rems-header-back rems-top-yellow-btn">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Extension
                </a>
            </div>

            <?php if ($isExtensionist && $section === 'request_monitoring'): ?>
                <style>
                    .monitoring-card{background:#fff;border:1px solid rgba(0,107,46,.15);border-radius:14px;box-shadow:0 12px 30px rgba(0,79,34,.06)}
                    .monitoring-card .card-body{padding:24px}
                    .monitoring-help{color:#68756c;font-size:.82rem}
                    .monitoring-note{background:#f5f8f5;border:1px solid #e5ece6;border-radius:9px;padding:12px 14px;color:#59665d;font-size:.88rem}
                    .monitoring-table-wrap{overflow-x:auto}
                    .monitoring-table{width:100%;min-width:720px;border-collapse:collapse}
                    .monitoring-table th,.monitoring-table td{padding:12px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .monitoring-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em}
                    .monitoring-table td{font-size:.88rem;color:#35443a}
                    .monitoring-status{display:inline-flex;border-radius:999px;padding:5px 10px;font-size:.74rem;font-weight:700;background:#e7f1ff;color:#174b83}
                    @media(max-width:600px){.monitoring-card .card-body{padding:17px}}
                </style>



                <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= monitoringEsc($success); ?></div><?php endif; ?>
                <?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please review the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= monitoringEsc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <?php if ($revisionId > 0 && !empty($revision['office_remarks'])): ?><div class="alert alert-warning"><strong>Additional information requested by ESO:</strong><div class="mt-1"><?= nl2br(monitoringEsc($revision['office_remarks'])); ?></div></div><?php endif; ?>
                <form method="post" action="<?= BASE_URL; ?>/extension/monitoring.php?section=request_monitoring<?= $draftId ? '&draft=' . (int)$draftId : ($revisionId ? '&revise=' . (int)$revisionId : ''); ?>" class="card monitoring-card" novalidate>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <div class="monitoring-request-heading">
                                <span class="monitoring-request-heading-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                                <h2 class="h6 fw-bold mb-0">Program / Project Monitoring and Evaluation Form</h2>
                            </div>

                        </div>
                        <input type="hidden" name="csrf_token" value="<?= monitoringEsc($csrfToken); ?>">

                        <?php if (!$monitoringPaperSchemaReady): ?>
                            <div class="alert alert-warning" role="alert">Database update needed: import <strong>extension_monitoring_paper_fields.sql</strong> into <strong>asu_rems</strong> before using Save as Draft or Submit Request.</div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="project_title">Program / Project Title <span class="text-danger">*</span></label>
                                <input class="form-control" id="project_title" name="project_title" maxlength="220" value="<?= monitoringEsc($form['project_title'] !== '' ? $form['project_title'] : $form['program_title']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="project_duration">Program / Project Duration <span class="text-danger">*</span></label>
                                <input class="form-control" id="project_duration" name="project_duration" maxlength="220" value="<?= monitoringEsc($form['project_duration']); ?>" required>
                            </div>
                            <div class="col-12">
                                <?php
                                    // Repeatable monitoring entries; preserve official form fields and workflow.
                                    $activityGroups = [
                                        'Planning and Implementation' => [
                                            'specific_learning_objectives' => 'col-md-6',
                                            'planned_activities' => 'col-md-6',
                                            'activities_conducted' => 'col-md-6',
                                            'degree_of_accomplishment' => 'col-md-6',
                                        ],
                                        'Implementation Details' => [
                                            'date_conducted' => 'col-lg-4 col-md-6',
                                            'project_activity_location' => 'col-lg-4 col-md-6',
                                            'responsible_person_unit_agency' => 'col-lg-4 col-md-12',
                                        ],
                                        'Evaluation and Budget' => [
                                            'problems_encountered' => 'col-md-6',
                                            'recommendation' => 'col-md-6',
                                            'budget_allocation' => 'col-md-6',
                                            'budget_utilization' => 'col-md-6',
                                        ],
                                    ];
                                    $activityLabels = monitoringPaperColumns();
                                ?>
                                <div class="monitoring-activity-list" id="monitoringEntries">
                                    <?php foreach ($monitoringPaperRows as $rowIndex => $entry): ?>
                                        <?php $activityHasContent = (bool)array_filter($entry, static fn($value) => trim((string)$value) !== ''); ?>
                                        <details class="monitoring-activity-section" data-monitoring-entry="<?= (int)$rowIndex; ?>" <?= ($rowIndex === 0 || $activityHasContent) ? 'open' : ''; ?>>
                                            <summary class="monitoring-activity-header">
                                                <span class="monitoring-activity-index"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                                                <span class="monitoring-activity-header-copy">
                                                    <strong data-monitoring-entry-title><?= $rowIndex === 0 ? 'Monitoring Details' : 'Additional Monitoring Entry'; ?></strong>
                                                    <small>Complete the corresponding program / project monitoring information</small>
                                                </span>
                                                <span class="monitoring-activity-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                                            </summary>
                                            <div class="monitoring-activity-content">
                                                <?php foreach ($activityGroups as $groupTitle => $fields): ?>
                                                    <div class="monitoring-activity-group">
                                                        <div class="monitoring-activity-group-title"><?= monitoringEsc($groupTitle); ?></div>
                                                        <div class="row g-3">
                                                            <?php foreach ($fields as $key => $width): ?>
                                                                <?php
                                                                    $fieldId = 'monitorField' . $rowIndex . '_' . $key;
                                                                    $fieldName = 'entries[' . $rowIndex . '][' . $key . ']';
                                                                    $isLongText = in_array($key, ['specific_learning_objectives', 'planned_activities', 'activities_conducted', 'degree_of_accomplishment', 'problems_encountered', 'recommendation'], true);
                                                                ?>
                                                                <div class="<?= monitoringEsc($width); ?>">
                                                                    <label class="form-label fw-semibold" for="<?= monitoringEsc($fieldId); ?>"><?= monitoringEsc($activityLabels[$key]); ?></label>
                                                                    <?php if ($key === 'date_conducted'): ?>
                                                                        <input class="form-control" id="<?= monitoringEsc($fieldId); ?>" type="date" name="<?= monitoringEsc($fieldName); ?>" value="<?= monitoringEsc($entry[$key]); ?>">
                                                                    <?php elseif ($isLongText): ?>
                                                                        <textarea class="form-control" id="<?= monitoringEsc($fieldId); ?>" name="<?= monitoringEsc($fieldName); ?>" rows="2" maxlength="2000"><?= monitoringEsc($entry[$key]); ?></textarea>
                                                                    <?php else: ?>
                                                                        <input class="form-control" id="<?= monitoringEsc($fieldId); ?>" type="text" name="<?= monitoringEsc($fieldName); ?>" maxlength="2000" value="<?= monitoringEsc($entry[$key]); ?>">
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                <div class="monitoring-remove-entry-wrap">
                                                    <button type="button" class="monitoring-remove-entry" data-remove-monitoring-entry <?= count($monitoringPaperRows) <= 1 ? 'hidden' : ''; ?>>
                                                        <i class="bi bi-trash3" aria-hidden="true"></i> Remove Entry
                                                    </button>
                                                </div>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                                <div class="monitoring-add-entry-wrap">
                                    <button type="button" class="btn btn-outline-asu monitoring-add-entry" id="addMonitoringEntry">
                                        <i class="bi bi-plus-circle" aria-hidden="true"></i> Add Another Entry
                                    </button>
                                    <span class="monitoring-help" id="monitoringEntryLimitNotice" aria-live="polite"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="monitored_by">Monitored by</label>
                                <input class="form-control" id="monitored_by" name="monitored_by" maxlength="220" value="<?= monitoringEsc($form['monitored_by']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="reviewed_validated_by">Reviewed and Validated</label>
                                <input class="form-control" id="reviewed_validated_by" name="reviewed_validated_by" maxlength="220" value="<?= monitoringEsc($form['reviewed_validated_by']); ?>">
                            </div>
                        </div>
                        <div class="monitoring-request-actions">
                             <?php if (!$revisionId): ?><button class="btn btn-outline-asu" type="submit" name="action" value="save_draft" <?= !$monitoringPaperSchemaReady ? 'disabled' : ''; ?>><i class="bi bi-save" aria-hidden="true"></i> Save as Draft</button><?php endif; ?>
                            <button class="btn btn-asu" type="submit" name="action" value="<?= $revisionId ? 'edit_resubmit' : 'submit_request'; ?>" id="submitMonitoringRequest" <?= !$monitoringPaperSchemaReady ? 'disabled' : ''; ?>><i class="bi <?= $revisionId ? 'bi-arrow-repeat' : 'bi-send'; ?>" aria-hidden="true"></i> <?= $revisionId ? 'Edit and Resubmit' : 'Submit Request'; ?></button>
                             <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests">Back to My Monitoring Requests</a>
                        </div>
                    </div>
                </form>
                <script>
                (() => {
                    'use strict';
                    const list = document.getElementById('monitoringEntries');
                    const add = document.getElementById('addMonitoringEntry');
                    const limitNote = document.getElementById('monitoringEntryLimitNotice');
                    if (!list || !add) return;
                    const maxEntries = 20;
                    let nextIndex = Math.max(0, ...Array.from(list.querySelectorAll('[data-monitoring-entry]'),
                        el => Number(el.dataset.monitoringEntry) || 0)) + 1;

                    function updateControls() {
                        const entries = Array.from(list.querySelectorAll('[data-monitoring-entry]'));
                        entries.forEach((section, index) => {
                            const title = section.querySelector('[data-monitoring-entry-title]');
                            if (title) title.textContent = index === 0 ? 'Monitoring Details' : 'Additional Monitoring Entry';
                            const remove = section.querySelector('[data-remove-monitoring-entry]');
                            if (remove) remove.hidden = entries.length <= 1;
                        });
                        add.disabled = entries.length >= maxEntries;
                        if (limitNote) limitNote.textContent = entries.length >= maxEntries ? 'Maximum of 20 entries reached.' : '';
                    }

                    add.addEventListener('click', () => {
                        const entries = list.querySelectorAll('[data-monitoring-entry]');
                        if (entries.length >= maxEntries) return;
                        const copy = entries[0].cloneNode(true);
                        const sourceIndex = Number(copy.dataset.monitoringEntry) || 0;
                        const newIndex = nextIndex++;
                        copy.dataset.monitoringEntry = String(newIndex);
                        copy.open = true;
                        copy.querySelectorAll('input, textarea').forEach(input => {
                            input.value = '';
                            input.name = input.name.replace(/entries\[\d+\]/, `entries[${newIndex}]`);
                            input.id = input.id.replace(new RegExp(`^monitorField${sourceIndex}_`), `monitorField${newIndex}_`);
                        });
                        copy.querySelectorAll('label[for]').forEach(label => {
                            label.htmlFor = label.htmlFor.replace(new RegExp(`^monitorField${sourceIndex}_`), `monitorField${newIndex}_`);
                        });
                        list.appendChild(copy);
                        updateControls();
                        copy.scrollIntoView({behavior:'auto', block:'nearest'});
                    });

                    list.addEventListener('click', event => {
                        const remove = event.target.closest('[data-remove-monitoring-entry]');
                        if (!remove || list.querySelectorAll('[data-monitoring-entry]').length <= 1) return;
                        remove.closest('[data-monitoring-entry]').remove();
                        updateControls();
                    });
                    updateControls();
                })();
                </script>

            <?php elseif ($isExtensionist && $section === 'my_monitoring_requests'): ?>
                <style>
                    .monitoring-card{background:#fff;border:1px solid rgba(0,107,46,.15);border-radius:14px;box-shadow:0 12px 30px rgba(0,79,34,.06);overflow:hidden}
                    .monitoring-table-wrap{overflow-x:auto}
                    .monitoring-table{width:100%;min-width:720px;border-collapse:collapse}
                    .monitoring-table th,.monitoring-table td{padding:12px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .monitoring-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em}
                    .monitoring-table td{font-size:.88rem;color:#35443a}
                    .monitoring-status{display:inline-flex;border-radius:999px;padding:5px 10px;font-size:.74rem;font-weight:700;background:#e7f1ff;color:#174b83}
                    .monitoring-empty{text-align:center;padding:42px 18px;color:#68756c}
                </style>

                <?php if ($viewRequest): ?>
                    <section class="card monitoring-card mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                <div><h2 class="h6 fw-bold text-success mb-2">Monitoring Request Details</h2><strong><?= monitoringEsc($viewRequest['project_title']); ?></strong><div class="small text-muted mt-1"><?= monitoringEsc($viewRequest['request_code'] ?: ('MR-' . (int)$viewRequest['request_id'])); ?></div></div>
                                <span class="monitoring-status" data-status="<?= monitoringEsc($viewRequest['status']); ?>"><?= monitoringEsc($viewRequest['status']); ?></span>
                            </div>
                            <?php if (!empty($viewRequest['monitoring_entries_json'])): ?>
                                <?php monitoringPaperDetailHtml($viewRequest); ?>
                            <?php else: ?>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="small text-muted fw-semibold">PROGRAM / PROJECT CODE</div><div><?= monitoringEsc($viewRequest['project_code'] ?: '—'); ?></div></div>
                                <div class="col-md-6"><div class="small text-muted fw-semibold">COLLEGE / CAMPUS / UNIT</div><div><?= monitoringEsc($viewRequest['college_unit']); ?></div></div>
                                <div class="col-md-6"><div class="small text-muted fw-semibold">TARGET COMMUNITY</div><div><?= monitoringEsc($viewRequest['target_community']); ?></div></div>
                                <div class="col-md-6"><div class="small text-muted fw-semibold">PREFERRED DATE / LOCATION</div><div><?= monitoringEsc(monitoringDate($viewRequest['preferred_date'])); ?> · <?= monitoringEsc($viewRequest['preferred_location']); ?></div></div>
                                <div class="col-12"><div class="small text-muted fw-semibold">PURPOSE</div><div class="text-break"><?= nl2br(monitoringEsc($viewRequest['monitoring_purpose'])); ?></div></div>
                                <?php if (!empty($viewRequest['office_remarks'])): ?><div class="col-12"><div class="small text-muted fw-semibold">ESO REMARKS</div><div><?= nl2br(monitoringEsc($viewRequest['office_remarks'])); ?></div></div><?php endif; ?>
                                <?php if (!empty($viewRequest['scheduled_date'])): ?><div class="col-md-6"><div class="small text-muted fw-semibold">ASSIGNED PERSONNEL / UNIT</div><div><?= monitoringEsc($viewRequest['assigned_unit'] ?: '—'); ?> · <?= monitoringEsc($viewRequest['assigned_personnel'] ?: '—'); ?></div></div><div class="col-md-6"><div class="small text-muted fw-semibold">MONITORING SCHEDULE / LOCATION</div><div><?= monitoringEsc(monitoringDate($viewRequest['scheduled_date'])); ?><?= !empty($viewRequest['scheduled_time']) ? ' · ' . monitoringEsc(date('g:i A', strtotime((string)$viewRequest['scheduled_time']))) : ''; ?><br><?= monitoringEsc($viewRequest['scheduled_location'] ?: '—'); ?></div></div><?php endif; ?>
                                <?php if (!empty($viewRequest['me_form_url'])): ?><div class="col-12"><div class="small text-muted fw-semibold">M&amp;E FORM (ASU ES NO. 25)</div><div><a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($viewRequest['me_form_url']); ?>" target="_blank" rel="noopener noreferrer" title="View M&amp;E Form" aria-label="View M&amp;E Form"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a></div></div><?php endif; ?>
                                <?php if (!empty($viewRequest['monitoring_report_url'])): ?><div class="col-12"><div class="small text-muted fw-semibold">MONITORING REPORT</div><div><a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($viewRequest['monitoring_report_url']); ?>" target="_blank" rel="noopener noreferrer" title="View monitoring report" aria-label="View monitoring report"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a></div></div><?php endif; ?>
                                <div class="col-12"><div class="small text-muted fw-semibold">REQUEST LETTER</div><div><a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($viewRequest['request_letter_url']); ?>" target="_blank" rel="noopener noreferrer" title="View request letter" aria-label="View request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a></div></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($viewRequest['monitoring_entries_json']) && !empty($viewRequest['office_remarks'])): ?>
                                <div class="small text-muted fw-semibold mt-3">ESO REMARKS</div>
                                <div><?= nl2br(monitoringEsc($viewRequest['office_remarks'])); ?></div>
                            <?php endif; ?>
                            <div class="monitor-detail-actions">
                                <?php if ((string)$viewRequest['status'] === 'Draft'): ?>
                                    <a class="btn btn-asu" href="<?= BASE_URL; ?>/extension/monitoring.php?section=request_monitoring&amp;draft=<?= (int)$viewRequest['request_id']; ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Continue Draft</a>
                                <?php endif; ?>
                                <?php if ((string)$viewRequest['status'] === 'Needs Information'): ?>
                                    <a class="btn btn-asu" href="<?= BASE_URL; ?>/extension/monitoring.php?section=request_monitoring&amp;revise=<?= (int)$viewRequest['request_id']; ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit and Resubmit</a>
                                <?php endif; ?>
                                <?php if ((string)$viewRequest['status'] === 'Monitoring Conducted'): ?>
                                    <a class="btn btn-asu" href="<?= BASE_URL; ?>/extension/monitoring.php?section=submit_monitoring_report"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Submit Monitoring Report</a>
                                <?php endif; ?>
                                <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests">Back to My Monitoring Requests</a>
                            </div>
                        </div>
                    </section>
                <?php elseif (filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT)): ?>
                    <div class="alert alert-danger">Monitoring request not found in your account.</div>
                <?php endif; ?>

                <?php if (!$viewRequest): ?>
                <section class="card monitoring-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom">
                        <h2 class="h6 fw-bold mb-0">Monitoring Request Records</h2>
                        <a class="btn btn-sm btn-asu" href="<?= BASE_URL; ?>/extension/monitoring.php?section=request_monitoring"><i class="bi bi-plus-circle" aria-hidden="true"></i> New Monitoring Request</a>
                    </div>
                    <div class="monitoring-list-toolbar">
                        <form class="monitoring-list-filters" method="get" action="<?= BASE_URL; ?>/extension/monitoring.php">
                            <input type="hidden" name="section" value="<?= monitoringEsc($section); ?>">
                            <div class="monitoring-filter-search">
                                <label class="form-label" for="monitoringListSearch">Search requests</label>
                                <div class="monitoring-search-field"><i class="bi bi-search" aria-hidden="true"></i>
                                    <input class="form-control" id="monitoringListSearch" name="q" type="search" value="<?= monitoringEsc($monitorFilterSearch); ?>" placeholder="Request number, program / project, community">
                                </div>
                            </div>
                            <div class="monitoring-filter-status">
                                <label class="form-label" for="monitoringListStatus">Status</label>
                                <select class="form-select" id="monitoringListStatus" name="status">
                                    <option value="">All relevant statuses</option>
                                    <?php foreach ($monitorStatusesForPage as $statusOption): ?>
                                        <option value="<?= monitoringEsc($statusOption); ?>" <?= $monitorFilterStatus === $statusOption ? 'selected' : ''; ?>><?= monitoringEsc($statusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="monitoring-filter-actions">
                                <button class="btn btn-asu monitoring-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                                <a class="btn btn-outline-asu monitoring-reset-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=<?= monitoringEsc($section); ?>">Reset</a>
                            </div>
                        </form>
                    </div>
                    <?php if (!$rows): ?>
                        <div class="monitoring-empty"><i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i><strong><?= ($monitorFilterSearch !== '' || $monitorFilterStatus !== '') ? 'No matching monitoring requests.' : 'No monitoring requests yet.'; ?></strong><div class="small mt-2"><?= ($monitorFilterSearch !== '' || $monitorFilterStatus !== '') ? 'Try another search or reset the filters.' : 'Create a monitoring request to start the process.'; ?></div></div>
                    <?php else: ?>
                        <div class="monitoring-table-wrap"><table class="monitoring-table"><thead><tr><th>Program / Project</th><th>Preferred Date</th><th>Request Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><strong><?= monitoringEsc($row['project_title']); ?></strong><span class="d-block small text-muted"><?= monitoringEsc($row['request_code'] ?: ('MR-' . (int)$row['request_id'])); ?></span></td>
                                <td><?= monitoringEsc(monitoringDate($row['preferred_date'])); ?></td>
                                <td><?= monitoringEsc(monitoringDate($row['submitted_at'] ?: $row['created_at'], 'M d, Y h:i A')); ?></td>
                                <td><span class="monitoring-status" data-status="<?= monitoringEsc($row['status']); ?>"><?= monitoringEsc($row['status']); ?></span></td>
                                <td><a class="btn btn-sm monitor-eye-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests&amp;view=<?= (int)$row['request_id']; ?>" title="View monitoring request" aria-label="View monitoring request"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View Details</span></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

            <?php elseif ($isOffice && $section === 'monitoring_requests'): ?>
                <style>
                    .eso-monitor-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05);overflow:hidden}
                    .eso-monitor-table-wrap{overflow-x:auto}
                    .eso-monitor-table{width:100%;min-width:650px;border-collapse:collapse}
                    .eso-monitor-table th,.eso-monitor-table td{padding:13px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .eso-monitor-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                    .eso-monitor-table td{color:#35443a;font-size:.88rem}
                    .eso-monitor-status{display:inline-flex;border-radius:999px;background:#fff3c4;color:#664f00;padding:5px 10px;font-size:.74rem;font-weight:700;white-space:nowrap}
                    .eso-monitor-empty{text-align:center;padding:42px 18px;color:#68756c}
                    .eso-monitor-detail{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;padding:20px;margin-bottom:18px}
                    @media(max-width:600px){.eso-monitor-detail{padding:16px}}
                </style>

                <?php if ($officeSuccess !== ''): ?><div class="alert alert-success" role="status"><?= monitoringEsc($officeSuccess); ?></div><?php endif; ?>
                <?php if ($officeErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($officeErrors as $officeError): ?><li><?= monitoringEsc($officeError); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <?php if ($officeViewRequest): ?>
                    <section class="eso-monitor-detail">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div><h2 class="h6 fw-bold text-success mb-2">Monitoring Request Details</h2><strong><?= monitoringEsc($officeViewRequest['project_title'] ?: 'Extension Program / Project'); ?></strong><div class="small text-muted mt-1"><?= monitoringEsc($officeViewRequest['request_code'] ?: ('MR-' . (int)$officeViewRequest['request_id'])); ?></div></div>
                            <span class="eso-monitor-status" data-status="<?= monitoringEsc($officeViewRequest['status']); ?>"><?= monitoringEsc($officeViewRequest['status']); ?></span>
                        </div>
                        <?php if (!empty($officeViewRequest['monitoring_entries_json'])): ?>
                            <?php monitoringPaperDetailHtml($officeViewRequest); ?>
                        <?php else: ?>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="small text-muted fw-semibold">PROGRAM / PROJECT CODE</div><div><?= monitoringEsc($officeViewRequest['project_code'] ?: '—'); ?></div></div>
                            <div class="col-md-6"><div class="small text-muted fw-semibold">COLLEGE / CAMPUS / UNIT</div><div><?= monitoringEsc($officeViewRequest['college_unit'] ?: '—'); ?></div></div>
                            <div class="col-md-6"><div class="small text-muted fw-semibold">TARGET COMMUNITY</div><div><?= monitoringEsc($officeViewRequest['target_community'] ?: '—'); ?></div></div>
                            <div class="col-md-6"><div class="small text-muted fw-semibold">PREFERRED DATE / LOCATION</div><div><?= monitoringEsc(monitoringDate($officeViewRequest['preferred_date'])); ?> · <?= monitoringEsc($officeViewRequest['preferred_location'] ?: '—'); ?></div></div>
                            <div class="col-12"><div class="small text-muted fw-semibold">PURPOSE OF MONITORING</div><div class="text-break"><?= nl2br(monitoringEsc($officeViewRequest['monitoring_purpose'] ?: '—')); ?></div></div>
                            <div class="col-12"><div class="small text-muted fw-semibold">REQUEST LETTER</div><div><?php $letterParts = parse_url((string)$officeViewRequest['request_letter_url']); $letterIsHttps = $letterParts && strtolower((string)($letterParts['scheme'] ?? '')) === 'https' && !empty($letterParts['host']); ?><?php if ($letterIsHttps): ?><a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($officeViewRequest['request_letter_url']); ?>" target="_blank" rel="noopener noreferrer" title="View request letter" aria-label="View request letter"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a><?php else: ?>—<?php endif; ?></div></div>
                            <?php if (!empty($officeViewRequest['office_remarks'])): ?><div class="col-12"><div class="small text-muted fw-semibold">PREVIOUS ESO REMARKS</div><div><?= nl2br(monitoringEsc($officeViewRequest['office_remarks'])); ?></div></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($officeViewRequest['monitoring_entries_json']) && !empty($officeViewRequest['office_remarks'])): ?>
                            <div class="small text-muted fw-semibold">PREVIOUS ESO REMARKS</div>
                            <div><?= nl2br(monitoringEsc($officeViewRequest['office_remarks'])); ?></div>
                        <?php endif; ?>

                        <?php if (in_array((string)$officeViewRequest['status'], ['Submitted', 'Needs Information'], true)): ?>
                            <form method="post" id="officeMonitoringReviewForm" action="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_requests" class="monitor-detail-form">
                                <input type="hidden" name="csrf_token" value="<?= monitoringEsc($officeCsrf); ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$officeViewRequest['request_id']; ?>">
                                <label class="form-label fw-semibold" for="monitorOfficeRemarks">Information needed / remarks</label>
                                <textarea class="form-control" id="monitorOfficeRemarks" name="office_remarks" rows="2" maxlength="3000" placeholder="Enter remarks or explain what the Extensionist needs to provide."><?= monitoringEsc($officeViewRequest['status'] === 'Needs Information' ? ($officeViewRequest['office_remarks'] ?? '') : ''); ?></textarea>
                                <div class="monitor-detail-actions">
                                    <?php if ((string)$officeViewRequest['status'] === 'Submitted'): ?>
                                        <button class="btn btn-asu" type="submit" name="action" value="endorse_request"><i class="bi bi-check2-circle" aria-hidden="true"></i> Review and Endorse Request</button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-asu" type="submit" name="action" value="request_information"><i class="bi bi-info-circle" aria-hidden="true"></i> Request More Information</button>
                                    <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_requests">Back to Monitoring Requests</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="monitor-detail-actions">
                                <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_requests">Back to Monitoring Requests</a>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php elseif (filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT)): ?>
                    <div class="alert alert-danger">The selected monitoring request could not be found.</div>
                <?php endif; ?>

                <?php if (!$officeViewRequest): ?>
                <section class="eso-monitor-card">
                    <div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Requests Awaiting ESO Review</h2></div>
                    <div class="monitoring-list-toolbar">
                        <form class="monitoring-list-filters" method="get" action="<?= BASE_URL; ?>/extension/monitoring.php">
                            <input type="hidden" name="section" value="<?= monitoringEsc($section); ?>">
                            <div class="monitoring-filter-search">
                                <label class="form-label" for="monitoringListSearch">Search requests</label>
                                <div class="monitoring-search-field"><i class="bi bi-search" aria-hidden="true"></i>
                                    <input class="form-control" id="monitoringListSearch" name="q" type="search" value="<?= monitoringEsc($monitorFilterSearch); ?>" placeholder="Request number or program / project">
                                </div>
                            </div>
                            <div class="monitoring-filter-status">
                                <label class="form-label" for="monitoringListStatus">Status</label>
                                <select class="form-select" id="monitoringListStatus" name="status">
                                    <option value="">All relevant statuses</option>
                                    <?php foreach ($monitorStatusesForPage as $statusOption): ?>
                                        <option value="<?= monitoringEsc($statusOption); ?>" <?= $monitorFilterStatus === $statusOption ? 'selected' : ''; ?>><?= monitoringEsc($statusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="monitoring-filter-actions">
                                <button class="btn btn-asu monitoring-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                                <a class="btn btn-outline-asu monitoring-reset-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=<?= monitoringEsc($section); ?>">Reset</a>
                            </div>
                        </form>
                    </div>
                    <?php if (!$rows): ?><div class="eso-monitor-empty"><i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i><strong>No monitoring requests need review.</strong></div><?php else: ?>
                        <div class="eso-monitor-table-wrap"><table class="eso-monitor-table"><thead><tr><th>Program / Project</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= monitoringEsc($row['project_title'] ?: 'Extension Program / Project'); ?></strong><span class="d-block small text-muted"><?= monitoringEsc($row['request_code'] ?: ('MR-' . (int)$row['request_id'])); ?></span></td><td><?= monitoringEsc(monitoringDate($row['submitted_at'] ?: $row['created_at'], 'M d, Y h:i A')); ?></td><td><span class="eso-monitor-status" data-status="<?= monitoringEsc($row['status']); ?>"><?= monitoringEsc($row['status']); ?></span></td><td><a class="btn btn-sm monitor-eye-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_requests&amp;view=<?= (int)$row['request_id']; ?>" title="View monitoring request" aria-label="View monitoring request"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View Request</span></a></td></tr><?php endforeach; ?></tbody></table></div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

            <?php elseif ($isOffice && $section === 'monitoring_schedule'): ?>
                <style>
                    .monitor-flow-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05);overflow:hidden}
                    .monitor-flow-table-wrap{overflow-x:auto}.monitor-flow-table{width:100%;min-width:760px;border-collapse:collapse}
                    .monitor-flow-table th,.monitor-flow-table td{padding:13px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .monitor-flow-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                    .monitor-flow-table td{color:#35443a;font-size:.88rem}
                    .monitor-flow-detail{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;padding:20px;margin-bottom:18px}
                    .monitor-flow-status{display:inline-flex;border-radius:999px;background:#e7f1ff;color:#174b83;padding:5px 10px;font-size:.74rem;font-weight:700;white-space:nowrap}
                    .monitor-flow-empty{text-align:center;padding:42px 18px;color:#68756c}
                    @media(max-width:600px){.monitor-flow-detail{padding:16px}}
                    /* Saved schedules remain visible, but cannot be edited. */
                    .rems-view-shell .monitor-flow-detail .monitor-schedule-readonly-section input[readonly] {
                        cursor: default;
                        background-color: #f6f9f6 !important;
                        color: #2e3c33 !important;
                        opacity: 1 !important;
                    }
                    html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-flow-detail .monitor-schedule-readonly-section input[readonly] {
                        background-color: #1c392b !important;
                        border-color: #4c7158 !important;
                        color: #edf7ef !important;
                        box-shadow: none !important;
                    }

                </style>
                <?php if ($officeSuccess !== ''): ?><div class="alert alert-success" role="status"><?= monitoringEsc($officeSuccess); ?></div><?php endif; ?>
                <?php if ($officeErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($officeErrors as $officeError): ?><li><?= monitoringEsc($officeError); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

                <?php if ($officeViewRequest): ?>
                    <?php $scheduleStatus = (string)$officeViewRequest['status']; ?>
                    <section class="monitor-flow-detail">
                        <!-- Keep the submitted program/project identity visible while scheduling. -->
                        <div class="monitor-schedule-request-summary d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
                            <div class="monitor-schedule-request-summary-copy">
                                <h2 class="h6 fw-bold text-success mb-2">Monitoring Activity</h2>
                                <div class="monitor-schedule-project-title"><?= monitoringEsc(($officeViewRequest['project_title'] ?? '') ?: (($officeViewRequest['program_title'] ?? '') ?: 'Untitled Program / Project')); ?></div>
                                <div class="small text-muted mt-1"><?= monitoringEsc(($officeViewRequest['request_code'] ?? '') ?: ('MR-' . (int)$officeViewRequest['request_id'])); ?></div>
                                <?php if (!empty($officeViewRequest['project_duration'])): ?>
                                    <div class="small text-muted mt-1">Program / Project Duration: <?= monitoringEsc($officeViewRequest['project_duration']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="monitor-flow-status" data-status="<?= monitoringEsc($scheduleStatus); ?>"><?= monitoringEsc($scheduleStatus); ?></span>
                        </div>
                        <?php if ($scheduleStatus === 'Endorsed'): ?>
                            <p class="small text-muted mb-3">This specific monitoring request is still Endorsed and does not yet have a confirmed schedule. Save its assignment and schedule once to continue to M&amp;E Form submission.</p>
                            <form method="post" id="monitorScheduleForm" action="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_schedule&amp;view=<?= (int)$officeViewRequest['request_id']; ?>" class="monitor-schedule-assignment-form">
                                <input type="hidden" name="csrf_token" value="<?= monitoringEsc($officeCsrf); ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$officeViewRequest['request_id']; ?>">
                                <h3 class="h6 fw-bold mb-3">Assign Personnel and Set Schedule</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="assignedUnit">Assigned Academic Unit</label>
                                        <input class="form-control" id="assignedUnit" name="assigned_unit" maxlength="180" value="<?= monitoringEsc($officeViewRequest['assigned_unit'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="assignedPersonnel">Assigned Monitoring Personnel</label>
                                        <input class="form-control" id="assignedPersonnel" name="assigned_personnel" maxlength="180" value="<?= monitoringEsc($officeViewRequest['assigned_personnel'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledDate">Monitoring Date</label>
                                        <input class="form-control" type="date" id="scheduledDate" name="scheduled_date" value="<?= monitoringEsc($officeViewRequest['scheduled_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledTime">Monitoring Time</label>
                                        <input class="form-control" type="time" id="scheduledTime" name="scheduled_time" value="<?= !empty($officeViewRequest['scheduled_time']) ? monitoringEsc(substr((string)$officeViewRequest['scheduled_time'], 0, 5)) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledLocation">Monitoring Location</label>
                                        <input class="form-control" id="scheduledLocation" name="scheduled_location" maxlength="255" value="<?= monitoringEsc($officeViewRequest['scheduled_location'] ?? $officeViewRequest['preferred_location'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </form>
                        <?php elseif (in_array($scheduleStatus, ['Scheduled', 'Monitoring Conducted', 'Report Submitted', 'Report Received', 'Completed'], true)): ?>
                            <!-- Saved scheduling fields remain read-only for the rest of this monitoring request's workflow. -->
                            <div class="monitor-schedule-assignment-form monitor-schedule-readonly-section">
                                <h3 class="h6 fw-bold mb-3">Confirmed Schedule and Assignment</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="assignedUnitReadonly">Assigned Academic Unit</label>
                                        <input class="form-control" type="text" id="assignedUnitReadonly" value="<?= monitoringEsc($officeViewRequest['assigned_unit'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="assignedPersonnelReadonly">Assigned Monitoring Personnel</label>
                                        <input class="form-control" type="text" id="assignedPersonnelReadonly" value="<?= monitoringEsc($officeViewRequest['assigned_personnel'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledDateReadonly">Monitoring Date</label>
                                        <input class="form-control" type="text" id="scheduledDateReadonly" value="<?= monitoringEsc(!empty($officeViewRequest['scheduled_date']) ? monitoringDate($officeViewRequest['scheduled_date'], 'm/d/Y') : '—'); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledTimeReadonly">Monitoring Time</label>
                                        <input class="form-control" type="text" id="scheduledTimeReadonly" value="<?= monitoringEsc(!empty($officeViewRequest['scheduled_time']) ? date('g:i A', strtotime((string)$officeViewRequest['scheduled_time'])) : '—'); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="scheduledLocationReadonly">Monitoring Location</label>
                                        <input class="form-control" type="text" id="scheduledLocationReadonly" value="<?= monitoringEsc($officeViewRequest['scheduled_location'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array($scheduleStatus, ['Monitoring Conducted', 'Report Submitted', 'Report Received', 'Completed'], true)): ?>
                            <div class="monitor-schedule-complete border-top pt-3 mt-3">
                                <h3 class="h6 fw-bold mb-2">M&amp;E Form Recorded</h3>
                                <?php $savedMeParts = parse_url((string)($officeViewRequest['me_form_url'] ?? '')); $savedMeValid = $savedMeParts && strtolower((string)($savedMeParts['scheme'] ?? '')) === 'https' && !empty($savedMeParts['host']); ?>
                                <?php if ($savedMeValid): ?>
                                    <a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($officeViewRequest['me_form_url']); ?>" target="_blank" rel="noopener noreferrer" title="View saved M&amp;E Form"><i class="bi bi-eye" aria-hidden="true"></i><span>View M&amp;E Form</span></a>
                                <?php else: ?>
                                    <p class="small text-muted mb-2">No valid M&amp;E Form link is saved in this record.</p>
                                <?php endif; ?>
                                <p class="small text-muted mt-2 mb-0"><?= $scheduleStatus === 'Monitoring Conducted' ? 'Monitoring is recorded. The Extension Coordinator can now submit the Monitoring Report.' : 'This monitoring activity has already advanced to the reporting stage. The schedule is locked.'; ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($scheduleStatus === 'Scheduled'): ?>
                            <form method="post" action="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_schedule&amp;view=<?= (int)$officeViewRequest['request_id']; ?>" class="border-top pt-3 mt-3" id="recordMonitoringForm"><input type="hidden" name="csrf_token" value="<?= monitoringEsc($officeCsrf); ?>"><input type="hidden" name="request_id" value="<?= (int)$officeViewRequest['request_id']; ?>"><h3 class="h6 fw-bold mb-2">Record Monitoring Conducted</h3><p class="small text-muted">After the on-site monitoring activity, add the completed M&amp;E Form (ASU ES No. 25) link.</p><label class="form-label" for="meFormUrl">M&amp;E Form Link</label><div class="input-group"><input class="form-control" type="url" id="meFormUrl" name="me_form_url" value="<?= monitoringEsc($officeViewRequest['me_form_url'] ?? ''); ?>" placeholder="https://..." required><button class="btn btn-outline-secondary" type="button" id="viewMeForm" aria-label="View M&amp;E form link" title="View M&amp;E form link"><i class="bi bi-eye" aria-hidden="true"></i></button></div><div class="small text-muted mt-1">You must click the eye button to view the link before submitting.</div></form>
                            <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                const input = document.getElementById('meFormUrl');
                                const eye = document.getElementById('viewMeForm');
                                const button = document.getElementById('recordMonitoringButton');
                                if (!input || !eye || !button) return;
                                const sync = () => { button.disabled = input.value.trim() === '' || input.dataset.viewed !== '1'; };
                                input.addEventListener('input', () => { input.dataset.viewed = '0'; sync(); });
                                eye.addEventListener('click', () => {
                                    let url;
                                    try { url = new URL(input.value.trim()); } catch (_) { alert('Enter a valid HTTPS link first.'); input.focus(); return; }
                                    if (url.protocol !== 'https:') { alert('The M&E Form link must use HTTPS.'); return; }
                                    window.open(url.href, '_blank', 'noopener,noreferrer');
                                    input.value = url.href; // Match the exact URL verified on the server.
                                    input.dataset.viewed = '0'; sync();
                                    const body = new URLSearchParams({action:'confirm_me_form_link',request_id:<?= (int)$officeViewRequest['request_id']; ?>,csrf_token:<?= json_encode($officeCsrf); ?>,me_form_url:input.value.trim()});
                                    fetch(window.location.pathname + window.location.search, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()})
                                        .then(async response => {
                                            if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Your session may have expired. Please refresh the page.');
                                            const result = await response.json();
                                            if (!response.ok || !result.ok) throw new Error(result.message || 'The link could not be confirmed.');
                                            input.dataset.viewed = '1'; sync();
                                        })
                                        .catch(error => { input.dataset.viewed = '0'; sync(); alert(error.message || 'The M&E Form link could not be confirmed.'); });
                                });
                                sync();
                            });
                            </script>
                        <?php endif; ?>
                        <div class="monitor-detail-actions">
                            <?php if ($scheduleStatus === 'Endorsed'): ?>
                                <button class="btn btn-asu" type="submit" form="monitorScheduleForm" name="action" value="save_schedule"><i class="bi bi-calendar-check" aria-hidden="true"></i> Save Schedule and Assignment</button>
                            <?php endif; ?>
                            <?php if ($scheduleStatus === 'Scheduled'): ?>
                                <button class="btn btn-asu" type="submit" form="recordMonitoringForm" id="recordMonitoringButton" name="action" value="record_monitoring_conducted" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Record Monitoring Conducted</button>
                            <?php endif; ?>
                            <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_schedule">Back to Monitoring Schedule</a>
                        </div>
                    </section>
                <?php elseif (filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT)): ?><div class="alert alert-danger">The selected monitoring request could not be found.</div><?php endif; ?>

                <?php if (!$officeViewRequest): ?>
                <section class="monitor-flow-card"><div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Endorsed and Scheduled Activities</h2></div><div class="monitoring-list-toolbar">
                        <form class="monitoring-list-filters" method="get" action="<?= BASE_URL; ?>/extension/monitoring.php">
                            <input type="hidden" name="section" value="<?= monitoringEsc($section); ?>">
                            <div class="monitoring-filter-search">
                                <label class="form-label" for="monitoringListSearch">Search requests</label>
                                <div class="monitoring-search-field"><i class="bi bi-search" aria-hidden="true"></i>
                                    <input class="form-control" id="monitoringListSearch" name="q" type="search" value="<?= monitoringEsc($monitorFilterSearch); ?>" placeholder="Request number, program / project, community">
                                </div>
                            </div>
                            <div class="monitoring-filter-status">
                                <label class="form-label" for="monitoringListStatus">Status</label>
                                <select class="form-select" id="monitoringListStatus" name="status">
                                    <option value="">All relevant statuses</option>
                                    <?php foreach ($monitorStatusesForPage as $statusOption): ?>
                                        <option value="<?= monitoringEsc($statusOption); ?>" <?= $monitorFilterStatus === $statusOption ? 'selected' : ''; ?>><?= monitoringEsc($statusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="monitoring-filter-actions">
                                <button class="btn btn-asu monitoring-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                                <a class="btn btn-outline-asu monitoring-reset-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=<?= monitoringEsc($section); ?>">Reset</a>
                            </div>
                        </form>
                    </div><?php if (!$rows): ?><div class="monitor-flow-empty"><i class="bi bi-calendar-event fs-2 d-block mb-2" aria-hidden="true"></i><strong>No monitoring activities need scheduling.</strong></div><?php else: ?><div class="monitor-flow-table-wrap"><table class="monitor-flow-table"><thead><tr><th>Program / Project</th><th>Monitoring Date</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= monitoringEsc($row['project_title']); ?></strong><span class="d-block small text-muted"><?= monitoringEsc($row['request_code'] ?: ('MR-' . (int)$row['request_id'])); ?></span></td><td><?= monitoringEsc(monitoringDate($row['preferred_date'])); ?></td><td><span class="monitor-flow-status" data-status="<?= monitoringEsc($row['status']); ?>"><?= monitoringEsc($row['status']); ?></span></td><td><a class="btn btn-sm monitor-eye-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_schedule&amp;view=<?= (int)$row['request_id']; ?>" title="View / Schedule" aria-label="View / Schedule"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View / Schedule</span></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
                <?php endif; ?>

            <?php elseif ($isOffice && $section === 'monitoring_reports'): ?>
                <style>
                    .monitor-report-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05);overflow:hidden}
                    .monitor-report-table-wrap{overflow-x:auto}.monitor-report-table{width:100%;min-width:760px;border-collapse:collapse}
                    .monitor-report-table th,.monitor-report-table td{padding:13px 14px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
                    .monitor-report-table th{background:#f7faf7;color:#455349;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
                    .monitor-report-table td{color:#35443a;font-size:.88rem}
                    .monitor-report-status{display:inline-flex;border-radius:999px;background:#e7f1ff;color:#174b83;padding:5px 10px;font-size:.74rem;font-weight:700;white-space:nowrap}
                    .monitor-report-empty{text-align:center;padding:42px 18px;color:#68756c}
                    .monitor-report-detail{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;padding:20px;margin-bottom:18px}
                    @media(max-width:600px){.monitor-report-detail{padding:16px}}
                </style>
                <?php if ($officeSuccess !== ''): ?><div class="alert alert-success" role="status"><?= monitoringEsc($officeSuccess); ?></div><?php endif; ?>
                <?php if ($officeErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($officeErrors as $officeError): ?><li><?= monitoringEsc($officeError); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <?php if ($officeViewRequest): ?>
                    <?php $reportStatus = (string)$officeViewRequest['status']; ?>
                    <section class="monitor-report-detail">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div class="monitor-report-head-copy">
                                <h2 class="h6 fw-bold text-success mb-2">Monitoring Record</h2>
                                <strong class="monitor-report-project-title"><?= monitoringEsc(($officeViewRequest['project_title'] ?? '') ?: (($officeViewRequest['program_title'] ?? '') ?: 'Extension Program / Project')); ?></strong>
                                <div class="small text-muted mt-1"><?= monitoringEsc($officeViewRequest['request_code'] ?: ('MR-' . (int)$officeViewRequest['request_id'])); ?></div>
                            </div>
                            <span class="monitor-report-status" data-status="<?= monitoringEsc($reportStatus); ?>"><?= monitoringEsc($reportStatus); ?></span>
                        </div>
                        <?php
                            // HTTPS links are the only documents linked in these records.
                            $meUrl = (string)($officeViewRequest['me_form_url'] ?? '');
                            $meParts = parse_url($meUrl);
                            $meHttps = $meParts && strtolower((string)($meParts['scheme'] ?? '')) === 'https' && !empty($meParts['host']);
                            $reportUrl = (string)($officeViewRequest['monitoring_report_url'] ?? '');
                            $reportParts = parse_url($reportUrl);
                            $reportHttps = $reportParts && strtolower((string)($reportParts['scheme'] ?? '')) === 'https' && !empty($reportParts['host']);
                        ?>
                        <div class="row g-3 monitor-report-info-grid">
                            <div class="col-md-6 monitor-report-info-item monitoring-key-detail">
                                <div class="small text-muted fw-semibold monitor-report-info-label">PROGRAM / PROJECT DURATION</div>
                                <div class="monitor-report-info-value"><?= monitoringEsc(($officeViewRequest['project_duration'] ?? '') ?: '—'); ?></div>
                            </div>
                            <div class="col-md-6 monitor-report-info-item">
                                <div class="small text-muted fw-semibold monitor-report-info-label">MONITORING DATE / TIME</div>
                                <div class="monitor-report-info-value">
                                    <?= monitoringEsc(monitoringDate($officeViewRequest['scheduled_date'] ?? null)); ?><?= !empty($officeViewRequest['scheduled_time']) ? ' · ' . monitoringEsc(date('g:i A', strtotime((string)$officeViewRequest['scheduled_time']))) : ''; ?>
                                </div>
                            </div>
                            <div class="col-md-6 monitor-report-info-item">
                                <div class="small text-muted fw-semibold monitor-report-info-label">ASSIGNED UNIT / PERSONNEL</div>
                                <div class="monitor-report-info-value"><?= monitoringEsc(($officeViewRequest['assigned_unit'] ?? '') ?: '—'); ?> · <?= monitoringEsc(($officeViewRequest['assigned_personnel'] ?? '') ?: '—'); ?></div>
                            </div>
                            <div class="col-md-6 monitor-report-info-item">
                                <div class="small text-muted fw-semibold monitor-report-info-label">MONITORING LOCATION</div>
                                <div class="monitor-report-info-value"><?= monitoringEsc(($officeViewRequest['scheduled_location'] ?? '') ?: '—'); ?></div>
                            </div>
                            <div class="col-md-6 monitor-report-info-item monitor-report-info-link">
                                <div class="small text-muted fw-semibold monitor-report-info-label">M&amp;E FORM (ASU ES NO. 25)</div>
                                <div class="monitor-report-info-value">
                                    <?php if ($meHttps): ?>
                                        <a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($meUrl); ?>" target="_blank" rel="noopener noreferrer" title="View M&amp;E Form" aria-label="View M&amp;E Form"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a>
                                    <?php else: ?>—<?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 monitor-report-info-item monitor-report-info-link">
                                <div class="small text-muted fw-semibold monitor-report-info-label">MONITORING REPORT</div>
                                <div class="monitor-report-info-value">
                                    <?php if ($reportHttps): ?>
                                        <a class="btn btn-sm btn-outline-asu monitor-document-view-link" href="<?= monitoringEsc($reportUrl); ?>" target="_blank" rel="noopener noreferrer" title="View monitoring report" aria-label="View monitoring report"><i class="bi bi-eye" aria-hidden="true"></i><span>View Link</span></a>
                                    <?php else: ?>—<?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($reportStatus === 'Completed'): ?><div class="alert alert-success mt-3 mb-0">This monitoring record is complete.</div><?php endif; ?>
                        <?php if (in_array($reportStatus, ['Report Submitted', 'Report Received'], true)): ?>
                            <form method="post" action="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_reports" class="monitor-detail-actions">
                                <input type="hidden" name="csrf_token" value="<?= monitoringEsc($officeCsrf); ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$officeViewRequest['request_id']; ?>">
                                <?php if ($reportStatus === 'Report Submitted'): ?>
                                    <button class="btn btn-asu" type="submit" name="action" value="receive_report"><i class="bi bi-check2-circle" aria-hidden="true"></i> Receive Monitoring Report</button>
                                <?php else: ?>
                                    <button class="btn btn-asu" type="submit" name="action" value="mark_completed"><i class="bi bi-check2-circle" aria-hidden="true"></i> Mark as Completed</button>
                                <?php endif; ?>
                                <a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_reports">Back to Monitoring Reports</a>
                            </form>
                        <?php else: ?>
                            <div class="monitor-detail-actions"><a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_reports">Back to Monitoring Reports</a></div>
                        <?php endif; ?>
                    </section>
                <?php elseif (filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT)): ?><div class="alert alert-danger">The selected monitoring record could not be found.</div><?php endif; ?>
                <?php if (!$officeViewRequest): ?>
                <section class="monitor-report-card"><div class="p-3 border-bottom"><h2 class="h6 fw-bold mb-0">Submitted Monitoring Reports</h2></div><div class="monitoring-list-toolbar">
                        <form class="monitoring-list-filters" method="get" action="<?= BASE_URL; ?>/extension/monitoring.php">
                            <input type="hidden" name="section" value="<?= monitoringEsc($section); ?>">
                            <div class="monitoring-filter-search">
                                <label class="form-label" for="monitoringListSearch">Search requests</label>
                                <div class="monitoring-search-field"><i class="bi bi-search" aria-hidden="true"></i>
                                    <input class="form-control" id="monitoringListSearch" name="q" type="search" value="<?= monitoringEsc($monitorFilterSearch); ?>" placeholder="Request number or program / project">
                                </div>
                            </div>
                            <div class="monitoring-filter-status">
                                <label class="form-label" for="monitoringListStatus">Status</label>
                                <select class="form-select" id="monitoringListStatus" name="status">
                                    <option value="">All relevant statuses</option>
                                    <?php foreach ($monitorStatusesForPage as $statusOption): ?>
                                        <option value="<?= monitoringEsc($statusOption); ?>" <?= $monitorFilterStatus === $statusOption ? 'selected' : ''; ?>><?= monitoringEsc($statusOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="monitoring-filter-actions">
                                <button class="btn btn-asu monitoring-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button>
                                <a class="btn btn-outline-asu monitoring-reset-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=<?= monitoringEsc($section); ?>">Reset</a>
                            </div>
                        </form>
                    </div><?php if (!$rows): ?><div class="monitor-report-empty"><i class="bi bi-file-earmark-text fs-2 d-block mb-2" aria-hidden="true"></i><strong>No monitoring reports are ready for review.</strong></div><?php else: ?><div class="monitor-report-table-wrap"><table class="monitor-report-table"><thead><tr><th>Program / Project</th><th>Monitoring Date</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= monitoringEsc($row['project_title']); ?></strong><span class="d-block small text-muted"><?= monitoringEsc($row['request_code'] ?: ('MR-' . (int)$row['request_id'])); ?></span></td><td><?= monitoringEsc(monitoringDate($row['preferred_date'])); ?></td><td><span class="monitor-report-status" data-status="<?= monitoringEsc($row['status']); ?>"><?= monitoringEsc($row['status']); ?></span></td><td><a class="btn btn-sm monitor-eye-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=monitoring_reports&amp;view=<?= (int)$row['request_id']; ?>" title="View monitoring report" aria-label="View monitoring report"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">View Report</span></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
                <?php endif; ?>

            <?php elseif ($isExtensionist && $section === 'submit_monitoring_report'): ?>
                <style>
                    .extension-report-card{background:#fff;border:1px solid rgba(0,107,46,.14);border-radius:14px;box-shadow:0 10px 28px rgba(0,79,34,.05)}
                    .extension-report-empty{text-align:center;padding:42px 18px;color:#68756c}
                    .extension-report-help{color:#68756c;font-size:.82rem}
                </style>
                <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= monitoringEsc($success); ?></div><?php endif; ?>
                <?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= monitoringEsc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <?php if (!$reportReadyRows): ?><section class="card extension-report-card"><div class="extension-report-empty"><i class="bi bi-file-earmark-arrow-up fs-2 d-block mb-2" aria-hidden="true"></i><strong>No monitoring activity is ready for a report.</strong><div class="small mt-2">The ESO must record the monitoring activity first.</div><a class="btn btn-outline-asu mt-3" href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests">My Monitoring Requests</a></div></section><?php else: ?>
                    <form method="post" action="<?= BASE_URL; ?>/extension/monitoring.php?section=submit_monitoring_report" class="card extension-report-card" novalidate><div class="card-body p-4"><h2 class="h6 fw-bold mb-4">Monitoring Report Submission</h2><input type="hidden" name="csrf_token" value="<?= monitoringEsc($csrfToken); ?>"><div class="mb-3"><label class="form-label fw-semibold" for="reportRequestId">Monitoring Activity <span class="text-danger">*</span></label><select class="form-select" id="reportRequestId" name="request_id" required><option value="">Select a monitoring activity</option><?php foreach ($reportReadyRows as $readyRow): ?><option value="<?= (int)$readyRow['request_id']; ?>"><?= monitoringEsc($readyRow['project_title']); ?> — <?= monitoringEsc($readyRow['request_code'] ?: ('MR-' . (int)$readyRow['request_id'])); ?> (<?= monitoringEsc(monitoringDate($readyRow['scheduled_date'])); ?>)</option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label fw-semibold" for="monitoringReportUrl">Monitoring Report Link (HTTPS) <span class="text-danger">*</span></label><div class="input-group"><input class="form-control" type="url" id="monitoringReportUrl" name="monitoring_report_url" maxlength="2000" placeholder="https://..." required><button class="btn btn-outline-secondary" id="viewReportLink" type="button" title="View report link" aria-label="View report link"><i class="bi bi-eye" aria-hidden="true"></i></button></div><div class="extension-report-help mt-1">You must click the eye button to view the link before submitting.</div></div><div class="monitor-detail-actions"><button class="btn btn-asu" id="submitReportButton" type="submit" name="action" value="submit_monitoring_report" disabled><i class="bi bi-send" aria-hidden="true"></i> Submit Monitoring Report</button><a class="btn btn-outline-asu monitor-back-btn" href="<?= BASE_URL; ?>/extension/monitoring.php?section=my_monitoring_requests">Back to My Monitoring Requests</a></div></div></form>
                    <script>
                    (() => {
                        const input=document.getElementById('monitoringReportUrl'), eye=document.getElementById('viewReportLink'), button=document.getElementById('submitReportButton');
                        if(!input||!eye||!button)return;
                        const sync=()=>{button.disabled=input.value.trim()===''||input.dataset.viewed!=='1';};
                        input.addEventListener('input',()=>{input.dataset.viewed='0';sync();});
                        eye.addEventListener('click',()=>{let url;try{url=new URL(input.value.trim());}catch(_){alert('Enter a valid HTTPS link first.');input.focus();return;}if(url.protocol!=='https:'){alert('The report link must use HTTPS.');return;}window.open(url.href,'_blank','noopener,noreferrer');const body=new URLSearchParams({action:'confirm_report_link',csrf_token:<?= json_encode($csrfToken); ?>,monitoring_report_url:url.href});fetch(window.location.pathname+window.location.search,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()}).then(r=>r.json()).then(result=>{input.dataset.viewed=result.ok?'1':'0';sync();if(!result.ok)alert('The report link could not be confirmed.');}).catch(()=>alert('The report link could not be confirmed.'));});sync();
                    })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<style id="rems-extension-view-uniform-styles">
/* ASU-REMS Extension: match research/view.php visual system.
   This stylesheet changes presentation only; all PHP/SQL/actions are preserved. */
:root {
    --rems-u-green: #007a33;
    --rems-u-dark: #005c26;
    --rems-u-yellow: #ffd51f;
    --rems-u-text: #1f2922;
    --rems-u-muted: #657068;
    --rems-u-border: rgba(0, 107, 46, .13);
    --rems-u-shadow: 0 14px 34px rgba(16, 24, 16, .055);
}
/* TOPBAR — same brand hierarchy, spacing, avatar as research/view.php */
.rems-uniform-topbar.asu-topbar {
    min-height: 76px !important;
    height: 76px !important;
    padding: 0 30px !important;
    background: rgba(255,255,255,.97) !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 16px !important;
}
.rems-uniform-topbar .rems-topbar-left,
.rems-uniform-topbar .rems-topbar-user {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}
.rems-uniform-topbar .rems-topbar-brandline {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}
.rems-uniform-topbar .rems-topbar-brandline::before {
    content: "";
    width: 3px;
    height: 38px;
    flex: 0 0 3px;
    border-radius: 999px;
    background: var(--rems-u-green);
}
.rems-uniform-topbar .rems-topbar-brandline strong {
    display: block;
    font: 800 17px/1.15 "Century Gothic", Arial, sans-serif;
    letter-spacing: -.015em;
    color: #101810;
}
.rems-uniform-topbar .rems-topbar-brandline span {
    display: block;
    margin-top: 4px;
    font: 400 13px/1.15 "Century Gothic", Arial, sans-serif;
    color: var(--rems-u-muted);
}
.rems-uniform-topbar .topbar-user-text {
    text-align: right;
}
.rems-uniform-topbar .topbar-user-text strong {
    display: block;
    font: 700 15px/1.1 "Century Gothic", Arial, sans-serif;
    color: #101810;
}
.rems-uniform-topbar .topbar-user-text span {
    display: block;
    margin-top: 4px;
    font: 400 12px/1.1 "Century Gothic", Arial, sans-serif;
    color: var(--rems-u-muted);
}
.rems-uniform-topbar .user-avatar {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 46px !important;
    min-width: 46px !important;
    height: 46px !important;
    border-radius: 50% !important;
    background: var(--rems-u-yellow) !important;
    color: #003f1a !important;
    font: 900 15px "Century Gothic", Arial, sans-serif !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}
.rems-uniform-topbar #sidebarToggle {
    flex: 0 0 auto;
    border-radius: 10px;
    transition: none !important;
}
/* PAGE CONTAINER — identical horizontal and vertical rhythm to view.php */
.main-page > .page-content {
    padding: 18px 28px 24px !important;
    background: radial-gradient(circle at top right, rgba(255,210,31,.10), transparent 34%),
                linear-gradient(180deg, #fafcf8 0%, #f4f8f1 100%) !important;
    overscroll-behavior-y: contain;
}
.rems-view-shell.container-fluid {
    width: 100%;
    max-width: none !important;
    padding: 0 !important;
    margin: 0 !important;
    color: var(--rems-u-text);
    font-family: "Century Gothic", Arial, sans-serif;
}
.rems-view-shell .rems-view-heading {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 96px;
    margin: 0 0 18px;
    padding: 20px 28px;
    background: linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);
    border: 0;
    border-radius: 18px;
    color: #fff;
    box-shadow: 0 18px 42px rgba(0,90,40,.18);
}
.rems-view-shell .rems-view-heading::before {
    content: "";
    position: absolute;
    z-index: -1;
    inset: 0 0 0 auto;
    width: 29%;
    background: linear-gradient(135deg,rgba(255,213,31,.11),rgba(255,255,255,.025));
    clip-path: polygon(12% 0,100% 0,100% 100%,0 100%);
    pointer-events: none;
}
.rems-view-shell .rems-view-eyebrow {
    display: block;
    margin: 0 0 5px;
    color: rgba(255,255,255,.83) !important;
    font: 700 10px/1.3 "Century Gothic",Arial,sans-serif;
    letter-spacing: .045em;
    text-transform: uppercase;
}
.rems-view-shell .rems-view-heading h1 {
    color: #fff !important;
    font: 900 30px/1.15 "Century Gothic",Arial,sans-serif !important;
    letter-spacing: -.025em !important;
    margin: 0 0 5px !important;
}
.rems-view-shell .rems-view-heading p {
    max-width: 760px;
    color: rgba(255,255,255,.84) !important;
    font: 400 10.5px/1.55 "Century Gothic",Arial,sans-serif;
    margin: 0 !important;
}
.rems-view-shell .rems-view-heading .rems-header-back {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 14px;
    background: rgba(255,255,255,.96) !important;
    border: 1px solid rgba(255,255,255,.95) !important;
    border-radius: 10px !important;
    color: #164b2a !important;
    font: 800 10px/1.2 "Century Gothic",Arial,sans-serif !important;
    box-shadow: 0 8px 18px rgba(0,65,27,.10) !important;
    text-decoration: none;
    transition: none !important;
}
.rems-view-shell .rems-view-heading .rems-header-back:hover {
    background: #fff !important;
    color: #007a33 !important;
}
/* PANELS — same soft rounded white cards as view.php */
.rems-view-shell .card,
.rems-view-shell .monitoring-card,
.rems-view-shell .eso-monitor-card,
.rems-view-shell .eso-monitor-detail,
.rems-view-shell .monitor-flow-card,
.rems-view-shell .monitor-flow-detail,
.rems-view-shell .extension-report-card,
.rems-view-shell .service-request-form-card,
.rems-view-shell .eso-service-card,
.rems-view-shell .eso-service-details,
.rems-view-shell .my-sr-card,
.rems-view-shell .my-sr-details,
.rems-view-shell .my-sr-stat {
    background: #fff !important;
    border: 1px solid var(--rems-u-border) !important;
    border-radius: 18px !important;
    box-shadow: var(--rems-u-shadow) !important;
}
.rems-view-shell .monitoring-card,
.rems-view-shell .eso-monitor-card,
.rems-view-shell .monitor-flow-card,
.rems-view-shell .eso-service-card,
.rems-view-shell .my-sr-card,
.rems-view-shell .extension-report-card {overflow: hidden;}
.rems-view-shell .eso-monitor-detail,
.rems-view-shell .monitor-flow-detail,
.rems-view-shell .eso-service-details,
.rems-view-shell .my-sr-details {
    padding: 20px 22px !important;
    margin-bottom: 18px !important;
}
.rems-view-shell .monitoring-card .card-body,
.rems-view-shell .service-request-form-card .card-body {
    padding: 20px 22px !important;
}
.rems-view-shell .card-body.p-4 {padding: 20px 22px !important;}
.rems-view-shell .card h2,
.rems-view-shell .eso-monitor-card h2,
.rems-view-shell .monitor-flow-card h2,
.rems-view-shell .monitor-flow-detail h2,
.rems-view-shell .eso-service-card h2,
.rems-view-shell .my-sr-card h2,
.rems-view-shell .eso-service-details h2,
.rems-view-shell .my-sr-details h2 {
    color: #163d25 !important;
    font-family: "Century Gothic", Arial, sans-serif !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em;
}
.rems-view-shell .eso-service-details > .d-flex:first-child,
.rems-view-shell .my-sr-details > .d-flex:first-child,
.rems-view-shell .eso-monitor-detail > .d-flex:first-child,
.rems-view-shell .monitor-flow-detail > .d-flex:first-child,
.rems-view-shell .monitoring-card .card-body > .d-flex:first-child {
    gap: 14px !important;
    padding-bottom: 13px;
    margin-bottom: 16px !important;
    border-bottom: 1px solid #eaf0e9;
}
/* DETAIL GRID — matching two-column info items from view.php */
.rems-view-shell .eso-service-grid,
.rems-view-shell .my-sr-detail-grid {
    gap: 14px 22px !important;
}
.rems-view-shell .eso-service-grid label,
.rems-view-shell .my-sr-detail label,
.rems-view-shell .small.text-muted.fw-semibold,
.rems-view-shell .row.g-3 .small.text-muted.fw-semibold {
    color: #718077 !important;
    font: 700 9px/1.4 "Century Gothic",Arial,sans-serif !important;
    letter-spacing: .035em !important;
    text-transform: uppercase;
    margin-bottom: 5px !important;
}
.rems-view-shell .eso-service-grid div,
.rems-view-shell .my-sr-detail div,
.rems-view-shell .monitor-flow-detail .row.g-3 > div,
.rems-view-shell .eso-monitor-detail .row.g-3 > div,
.rems-view-shell .monitoring-card .row.g-3 > div {
    line-height: 1.6;
}
.rems-view-shell .my-sr-detail div,
.rems-view-shell .eso-service-grid div,
.rems-view-shell .eso-monitor-detail .row.g-3 > div > div:not(.small),
.rems-view-shell .monitor-flow-detail .row.g-3 > div > div:not(.small) {
    color: #2e3c33;
    font-size: 11px;
    overflow-wrap: anywhere;
}
/* TABLES — headers, alignment and tighter density from view.php */
.rems-view-shell .monitoring-table,
.rems-view-shell .eso-monitor-table,
.rems-view-shell .monitor-flow-table,
.rems-view-shell .eso-service-table,
.rems-view-shell .my-sr-table,
.rems-view-shell table {border-collapse: collapse;}
.rems-view-shell table th {
    padding: 12px 13px !important;
    background: #f7faf7 !important;
    border-bottom: 1px solid #e3ebe4 !important;
    color: #455349 !important;
    font: 700 9px/1.45 "Century Gothic",Arial,sans-serif !important;
    letter-spacing: .035em !important;
    text-align: center !important;
    text-transform: uppercase;
    white-space: nowrap;
}
.rems-view-shell table td {
    padding: 12px 13px !important;
    border-bottom: 1px solid #edf0ed !important;
    vertical-align: middle !important;
    color: #35443a !important;
    font-size: 11px !important;
    line-height: 1.55;
}
.rems-view-shell table tbody tr:last-child td {border-bottom: 0 !important;}
.rems-view-shell table td:last-child {text-align: center;}
.rems-view-shell table td:last-child .d-flex {justify-content: center;}
.rems-view-shell table tbody tr:hover td {background: #fafcf9;}
/* BUTTONS — consistent sizing, eye controls, focus, no motion */
.rems-view-shell .btn,
.rems-view-shell button.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 10px !important;
    font: 700 11px/1.25 "Century Gothic",Arial,sans-serif !important;
    text-decoration: none;
    transition: background-color .12s, border-color .12s, color .12s !important;
    transform: none !important;
}
.rems-view-shell .btn.btn-sm,
.rems-view-shell .btn-sm {
    min-height: 34px;
    padding: 0 11px;
    font-size: 10px !important;
}
.rems-view-shell .btn-asu {box-shadow: 0 6px 14px rgba(0,107,46,.12) !important;}
.rems-view-shell .btn-outline-secondary {
    border-color: #d7dfd9 !important;
    background: #fff !important;
    color: #505b53 !important;
}
.rems-view-shell .btn-outline-secondary:hover {
    border-color: #c2cdc5 !important;
    background: #f8faf8 !important;
    color: #273229 !important;
}
.rems-view-shell .btn-light.border {
    background: #fff !important;
    border-color: #d7dfd9 !important;
    color: #425348 !important;
}
.rems-view-shell .input-group > .btn:has(.bi-eye),
.rems-view-shell a.btn:has(> .bi-eye),
.rems-view-shell .service-request-eye {
    min-width: 38px !important;
    width: 38px !important;
    padding: 0 !important;
}
.rems-view-shell a.btn:has(> .bi-eye).btn-sm {min-height: 34px;}
.rems-view-shell .btn i.bi-eye {font-size: 14px !important;}
.rems-view-shell .btn:focus-visible,
.rems-view-shell .form-control:focus-visible,
.rems-view-shell .form-select:focus-visible {outline: 3px solid rgba(0,122,51,.15);outline-offset: 2px;}
.rems-view-shell .btn:disabled {opacity: .55;cursor: not-allowed;box-shadow: none !important;}
/* FORMS — same compact input styles as view.php */
.rems-view-shell .form-control,
.rems-view-shell .form-select {
    min-height: 43px !important;
    border: 1px solid #d8e1da !important;
    border-radius: 10px !important;
    background-color: #fff;
    color: #1f2922;
    font: 400 11.5px/1.5 "Century Gothic",Arial,sans-serif !important;
}
.rems-view-shell textarea.form-control {
    min-height: 96px !important;
    padding: 11px 12px !important;
    resize: vertical;
}
.rems-view-shell .input-group > .form-control {border-radius: 10px 0 0 10px !important;}
.rems-view-shell .input-group > .btn {border-radius: 0 10px 10px 0 !important;}
.rems-view-shell .form-control::placeholder {color: #9aa39c;}
.rems-view-shell .form-control:focus,
.rems-view-shell .form-select:focus {
    border-color: var(--rems-u-green) !important;
    box-shadow: 0 0 0 .18rem rgba(0,122,51,.10) !important;
}
.rems-view-shell .form-label {
    margin-bottom: 6px !important;
    color: #465149 !important;
    font: 700 10px/1.45 "Century Gothic",Arial,sans-serif !important;
    letter-spacing: .018em;
}
.rems-view-shell .monitoring-help,
.rems-view-shell .service-request-help,
.rems-view-shell .extension-report-help,
.rems-view-shell .form-text {
    color: #7b857e !important;
    font-size: 10px !important;
    line-height: 1.6;
}
.rems-view-shell .monitoring-note,
.rems-view-shell .service-request-note {
    background: #f7faf7 !important;
    border: 1px solid #e5ece6 !important;
    border-radius: 10px !important;
    padding: 12px 14px !important;
    color: #59665d !important;
    font-size: 10.5px !important;
    line-height: 1.6;
}
/* ALERTS — restrained colors as in view.php */
.rems-view-shell .alert {
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 11px !important;
    box-shadow: none !important;
    font: 400 10.5px/1.6 "Century Gothic",Arial,sans-serif;
}
.rems-view-shell .alert-success {border-color: #cce3d3 !important;background:#f4faf6 !important;color:#275b37 !important;}
.rems-view-shell .alert-warning {border-color: #e7ddbd !important;background:#fcfaf4 !important;color:#66562d !important;}
.rems-view-shell .alert-danger {border-color: #ebd3d3 !important;background:#fdf7f7 !important;color:#7a3f3f !important;}
/* STATUS PILLS — uniform ASU gold yellow for every monitoring status */
.rems-view-shell .monitoring-status,
.rems-view-shell .eso-monitor-status,
.rems-view-shell .monitor-flow-status,
.rems-view-shell .monitor-report-status,
.rems-view-shell .eso-service-status,
.rems-view-shell .my-sr-badge {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-height: 29px;
    padding: 6px 11px !important;
    background: #ffd51f !important;
    border: 1px solid #ffd51f !important;
    color: #003f1a !important;
    border-radius: 999px !important;
    white-space: nowrap;
    font: 700 9px/1.2 "Century Gothic",Arial,sans-serif !important;
    letter-spacing: .02em;
}
.rems-view-shell .my-sr-status-completed {background: #e4f3e8 !important;border-color:#cae6d0 !important;color:#166335 !important;}
.rems-view-shell .my-sr-status-draft {background: #eef1ef !important;border-color:#e3e8e4 !important;color:#4b5750 !important;}
.rems-view-shell .my-sr-status-pending {background: #fff3c4 !important;border-color:#f0de9d !important;color:#664f00 !important;}
/* STATS AND TOOLS */
.rems-view-shell .my-sr-stat {padding: 16px 18px !important;}
.rems-view-shell .my-sr-stat span {font: 600 10px/1.5 "Century Gothic",Arial,sans-serif !important;color:#657068 !important;}
.rems-view-shell .my-sr-stat strong {font: 800 23px/1.2 "Century Gothic",Arial,sans-serif !important;color:#173b27 !important;}
.rems-view-shell .my-sr-toolbar,
.rems-view-shell .eso-service-toolbar {padding: 16px 20px !important;}
.rems-view-shell .my-sr-foot {font-size: 10px !important;}
.rems-view-shell .eso-monitor-empty,
.rems-view-shell .monitor-flow-empty,
.rems-view-shell .my-sr-empty,
.rems-view-shell .eso-service-empty,
.rems-view-shell .monitoring-empty {color: var(--rems-u-muted) !important;}
/* RESPONSIVE — same view.php collapsing banner and horizontal rhythm */
@media (max-width: 760px) {
    .rems-uniform-topbar.asu-topbar {padding: 0 16px !important;}
    .rems-uniform-topbar .rems-topbar-brandline strong {font-size: 14px !important;max-width: 240px;}
    .rems-uniform-topbar .rems-topbar-brandline span {font-size: 11px !important;}
    .rems-uniform-topbar .topbar-user-text {display: none !important;}
    .rems-uniform-topbar .user-avatar {width: 42px !important;min-width:42px !important;height:42px !important;}
    .main-page > .page-content {padding: 14px 14px 20px !important;}
    .rems-view-shell .rems-view-heading {min-height: 92px;flex-direction: column;align-items:flex-start;padding:18px;gap:12px;}
    .rems-view-shell .rems-view-heading::before {width: 38%;}
    .rems-view-shell .rems-view-heading h1 {font-size: 25px !important;}
    .rems-view-shell .rems-view-heading .rems-header-back {width:100%;}
    .rems-view-shell .eso-monitor-detail,
    .rems-view-shell .monitor-flow-detail,
    .rems-view-shell .eso-service-details,
    .rems-view-shell .my-sr-details {padding: 16px !important;}
    .rems-view-shell .monitoring-card .card-body,
    .rems-view-shell .service-request-form-card .card-body {padding: 16px !important;}
}
@media (max-width: 500px) {
    .rems-uniform-topbar .rems-topbar-brandline strong {font-size:12px !important;}
    .rems-uniform-topbar .rems-topbar-brandline span {font-size:10px !important;}
    .rems-uniform-topbar .rems-topbar-left {gap:8px;}
    .rems-uniform-topbar .rems-topbar-brandline {gap:8px;}
}

/* ===============================================================
   MONITORING & EVALUATION — visual parity with services.php
   Only presentation and optional list filtering; workflows preserved.
   =============================================================== */
.rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn,
.rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn:hover {
    background:#ffd51f !important;
    border:1px solid #ffd51f !important;
    color:#003f1a !important;
    box-shadow:none !important;
    transition:none !important;
}
.rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn:hover {background:#f0bd00 !important;border-color:#f0bd00 !important;}
.rems-view-shell .monitoring-request-heading {display:flex;align-items:center;min-width:0;gap:10px;}
.rems-view-shell .monitoring-request-heading-icon {
    display:inline-flex;align-items:center;justify-content:center;
    width:36px;height:36px;min-width:36px;border-radius:10px;
    background:#edf7ef;color:#007a33;font-size:16px;
}
.rems-view-shell .monitoring-request-heading h2 {margin:0!important;color:#101810!important;font-size:15px!important;font-weight:700!important;line-height:1.3!important;}
.rems-view-shell .monitoring-card a.monitoring-top-white-btn {background:#fff!important;border:1px solid #c9dfcf!important;color:#007a33!important;box-shadow:none!important;}
.rems-view-shell .monitoring-card a.monitoring-top-white-btn:hover {background:#f3f8f4!important;border-color:#a8cdb3!important;color:#005c26!important;}
.rems-view-shell .monitoring-request-actions {
    display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;
    gap:9px;margin-top:24px;padding-top:17px;border-top:1px solid rgba(0,122,51,.08);
}
.rems-view-shell .monitoring-request-actions .btn {min-height:42px!important;padding:0 14px!important;font-size:10px!important;font-weight:800!important;white-space:nowrap;box-shadow:none!important;}
.rems-view-shell .monitoring-request-actions .btn-asu {background:linear-gradient(135deg,#007a33,#005c26)!important;border:1px solid #007a33!important;color:#fff!important;}
.rems-view-shell .monitoring-request-actions .btn-outline-asu {background:#fff!important;border:1px solid rgba(0,122,51,.2)!important;color:#007a33!important;}
.rems-view-shell .monitoring-card .row > [class*="col-"],
.rems-view-shell .eso-monitor-detail .row > [class*="col-"],
.rems-view-shell .monitor-flow-detail .row > [class*="col-"],
.rems-view-shell .monitor-report-detail .row > [class*="col-"] {min-width:0;}
.rems-view-shell .monitoring-card :is(.form-control,.form-select),
.rems-view-shell .eso-monitor-detail :is(.form-control,.form-select),
.rems-view-shell .monitor-flow-detail :is(.form-control,.form-select),
.rems-view-shell .monitor-report-detail :is(.form-control,.form-select),
.rems-view-shell .extension-report-card :is(.form-control,.form-select) {box-sizing:border-box;width:100%;max-width:100%;min-width:0;}
.rems-view-shell :is(.monitoring-card,.monitor-flow-detail,.extension-report-card) .input-group {flex-wrap:nowrap;}
.rems-view-shell :is(.monitoring-card,.monitor-flow-detail,.extension-report-card) .input-group > .btn:has(.bi-eye) {flex:0 0 38px;width:38px!important;min-width:38px!important;}
.rems-view-shell :is(.monitoring-card,.monitor-flow-detail,.extension-report-card) .input-group > .btn:has(.bi-eye):hover {background:#edf7f0!important;color:#005225!important;border-color:#a8d2b4!important;}

/* Full-width one-line filters identical to Training & Technical Services. */
.rems-view-shell .monitoring-list-toolbar {
    padding:16px 20px;border-bottom:1px solid #edf0ed;
    background:#fff;container:monitoring-filters / inline-size;
}
.rems-view-shell .monitoring-list-filters {
    display:grid!important;
    grid-template-columns:minmax(0,1.85fr) minmax(0,.95fr) max-content!important;
    align-items:end!important;gap:12px!important;width:100%!important;margin:0!important;
}
.rems-view-shell .monitoring-filter-search,
.rems-view-shell .monitoring-filter-status {width:100%;min-width:0;}
.rems-view-shell .monitoring-list-toolbar .form-label {
    display:block;margin-bottom:7px!important;color:#526456!important;
    font:700 10px/1.4 "Century Gothic",Arial,sans-serif!important;letter-spacing:.025em;
}
.rems-view-shell .monitoring-list-toolbar :is(.form-control,.form-select) {
    box-sizing:border-box;display:block;width:100%!important;max-width:100%!important;
    min-width:0!important;height:44px!important;min-height:44px!important;
    margin:0!important;padding-top:0!important;padding-bottom:0!important;text-overflow:ellipsis;
}
.rems-view-shell .monitoring-search-field {position:relative;width:100%;min-width:0;}
.rems-view-shell .monitoring-search-field > i {
    position:absolute;top:50%;left:14px;z-index:2;transform:translateY(-50%);
    color:#658071;font-size:14px;pointer-events:none;
}
.rems-view-shell .monitoring-search-field > .form-control {padding-left:40px!important;}
.rems-view-shell .monitoring-filter-actions {
    display:flex!important;flex-wrap:nowrap!important;align-items:center!important;
    justify-content:flex-end!important;gap:9px!important;margin:0!important;
}
.rems-view-shell .monitoring-filter-actions .btn {
    display:inline-flex!important;flex:0 0 auto;align-items:center;justify-content:center;
    box-sizing:border-box;min-width:85px!important;width:auto;height:44px!important;
    min-height:44px!important;margin:0!important;padding:0 13px!important;white-space:nowrap;
}
.rems-view-shell .monitoring-filter-actions .monitoring-filter-btn {background:#006b30!important;border:1px solid #006b30!important;color:#fff!important;box-shadow:none!important;}
.rems-view-shell .monitoring-filter-actions .monitoring-filter-btn:hover {background:#005225!important;border-color:#005225!important;color:#fff!important;}
.rems-view-shell .monitoring-filter-actions .monitoring-reset-btn {background:#fff!important;border:1px solid #cde2d3!important;color:#006b30!important;box-shadow:none!important;}
.rems-view-shell .monitoring-filter-actions .monitoring-reset-btn:hover {background:#f2f8f3!important;border-color:#a8d2b4!important;color:#005225!important;}
@container monitoring-filters (max-width:780px) {
    .rems-view-shell .monitoring-list-filters {grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)!important;}
    .rems-view-shell .monitoring-filter-actions {grid-column:1/-1!important;}
}
@container monitoring-filters (max-width:500px) {
    .rems-view-shell .monitoring-list-filters {grid-template-columns:minmax(0,1fr)!important;gap:12px!important;}
    .rems-view-shell .monitoring-filter-actions {grid-column:1!important;justify-content:stretch!important;}
    .rems-view-shell .monitoring-filter-actions .btn {flex:1 1 0;min-width:0!important;}
}

/* Matching table structure: left-aligned data, centered Status and Action. */
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) {border-collapse:collapse;width:100%;}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td) {text-align:left!important;vertical-align:middle!important;}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-last-child(-n+2) {text-align:center!important;}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody tr:last-child td {border-bottom:0!important;}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) td:last-child .d-flex {justify-content:center!important;align-items:center!important;}
.rems-view-shell .monitoring-table {min-width:760px;}
.rems-view-shell .eso-monitor-table {min-width:650px;}
.rems-view-shell .monitor-flow-table {min-width:820px;}
.rems-view-shell .monitor-report-table {min-width:750px;}
.rems-view-shell .monitor-eye-btn {
    display:inline-flex!important;align-items:center!important;justify-content:center!important;
    width:38px!important;min-width:38px!important;height:36px!important;
    min-height:36px!important;padding:0!important;margin:0 auto!important;
    border:1px solid #cde2d3!important;border-radius:10px!important;
    background:#fff!important;color:#007a33!important;box-shadow:none!important;
}
.rems-view-shell .monitor-eye-btn:hover {background:#edf7f0!important;border-color:#a8d2b4!important;color:#005225!important;}
.rems-view-shell .monitor-eye-btn .bi-eye {font-size:14px!important;}
.rems-view-shell .monitor-report-status,
.rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status)[data-status="Completed"] {
    display:inline-flex!important;align-items:center;justify-content:center;min-height:29px;
    padding:6px 11px!important;border-radius:999px!important;
    font:700 9px/1.2 "Century Gothic",Arial,sans-serif!important;white-space:nowrap;
}
.rems-view-shell .monitor-report-card,
.rems-view-shell .monitor-report-detail {background:#fff!important;border:1px solid var(--rems-u-border)!important;border-radius:18px!important;box-shadow:var(--rems-u-shadow)!important;}
.rems-view-shell .monitor-report-card {overflow:hidden;}
.rems-view-shell .monitor-report-detail {padding:20px 22px!important;margin-bottom:18px!important;}
.rems-view-shell :is(.monitoring-card,.eso-monitor-card,.monitor-flow-card,.monitor-report-card) > .p-3.border-bottom {padding:14px 18px!important;min-height:62px;display:flex;align-items:center;}
@media(max-width:560px) {
    .rems-view-shell .monitoring-request-actions {flex-direction:column;align-items:stretch;}
    .rems-view-shell .monitoring-request-actions .btn {width:100%;}
    .rems-view-shell .monitor-report-detail {padding:16px!important;}
}

/* Unified Monitoring details: review.php typography + services.php right-side actions. */
.rems-view-shell .monitoring-card,
.rems-view-shell .eso-monitor-detail,
.rems-view-shell .monitor-flow-detail,
.rems-view-shell .monitor-report-detail,
.rems-view-shell .extension-report-card {margin-bottom:18px!important;}
.rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.extension-report-card) :is(h2,h3) {
    color:#19271d!important;font-family:"Century Gothic",Arial,sans-serif!important;
    font-size:14px!important;font-weight:800!important;line-height:1.4!important;
}
.rems-view-shell :is(.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.monitoring-card) .row.g-3 :is(.small.text-muted.fw-semibold) {
    font-size:9.5px!important;font-weight:800!important;letter-spacing:.025em!important;line-height:1.45!important;
}
.rems-view-shell :is(.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.monitoring-card) .row.g-3 > div > div:not(.small):not(.input-group) {
    font-size:11.5px!important;line-height:1.55!important;
}
.rems-view-shell .monitor-report-detail {
    padding:20px 22px!important;margin-bottom:18px!important;
}
.rems-view-shell .monitor-detail-form {
    margin-top:18px;padding-top:18px;border-top:1px solid #e8eee9;
}
.rems-view-shell .monitor-detail-form textarea {display:block;width:100%;min-height:110px;}
.rems-view-shell .monitor-detail-actions,
.rems-view-shell .monitoring-request-actions {
    display:flex!important;flex-direction:row!important;align-items:center!important;
    justify-content:flex-end!important;flex-wrap:wrap!important;
    gap:10px!important;width:100%;margin-top:20px!important;padding-top:16px!important;
    border-top:1px solid #e8eee9;
}
.rems-view-shell .monitor-detail-form .monitor-detail-actions {border-top:0;padding-top:0!important;margin-top:14px!important;}
.rems-view-shell .monitor-detail-actions .btn,
.rems-view-shell .monitoring-request-actions .btn {
    display:inline-flex!important;align-items:center!important;justify-content:center!important;
    gap:6px!important;min-height:42px!important;height:auto!important;
    padding:11px 15px!important;margin:0!important;border-radius:10px!important;
    font-size:10.5px!important;font-weight:800!important;line-height:1.25!important;
    text-align:center!important;white-space:normal!important;box-shadow:none!important;
}
.rems-view-shell .monitor-detail-actions .monitor-back-btn,
.rems-view-shell .monitoring-request-actions .monitor-back-btn {
    background:#fff!important;border:1px solid #d7e3d9!important;color:#31523a!important;
}
.rems-view-shell .monitor-detail-actions .btn-asu,
.rems-view-shell .monitoring-request-actions .btn-asu {
    background:#007a33!important;color:#fff!important;border-color:#007a33!important;
}
.rems-view-shell a.btn.monitor-document-view-link:has(> .bi-eye) {
    display:inline-flex!important;align-items:center!important;justify-content:center!important;
    width:auto!important;min-width:92px!important;min-height:36px!important;
    padding:0 13px!important;gap:7px!important;
    background:#fff!important;border:1px solid #cde2d3!important;border-radius:9px!important;
    color:#007a33!important;font-size:10px!important;font-weight:800!important;line-height:1.2!important;
    text-decoration:none!important;white-space:nowrap!important;box-shadow:none!important;
}
.rems-view-shell a.btn.monitor-document-view-link:has(> .bi-eye):hover {
    background:#edf7f0!important;border-color:#a8d2b4!important;color:#005225!important;
}
.rems-view-shell a.btn.monitor-document-view-link .bi-eye {flex:0 0 auto;font-size:13px!important;}
.rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.extension-report-card) :is(.form-control,.form-select) {
    font-size:11.5px!important;border-radius:10px!important;
}
.rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.extension-report-card) .form-label {
    font-size:10.5px!important;font-weight:800!important;
}
.rems-view-shell .monitor-flow-detail .row.g-3 > div > div:not(.small):has(.row),
.rems-view-shell .monitoring-card .row.g-3 > div > div:not(.small):has(.row) {font-size:inherit!important;}
@media(max-width:650px) {
    .rems-view-shell .monitor-detail-actions,
    .rems-view-shell .monitoring-request-actions {justify-content:flex-end!important;}
    .rems-view-shell .monitor-detail-actions .btn,
    .rems-view-shell .monitoring-request-actions .btn {max-width:100%!important;}
}
/* Monitoring Schedule: make assigned details and confirmed schedule values readable. */
.rems-view-shell .monitor-flow-detail .monitor-highlight-value {
    font-weight: 700 !important;
    color: #192b20 !important;
}

/* Monitoring Schedule typography: one consistent hierarchy for details and forms.
   Layout, action buttons, status values and form behaviour are unchanged. */
.rems-view-shell .monitor-flow-detail {
    font-size: 12px !important;
    line-height: 1.5 !important;
}
.rems-view-shell .monitor-flow-detail :is(h2, h3) {
    font-size: 14px !important;
    font-weight: 800 !important;
    line-height: 1.45 !important;
}
.rems-view-shell .monitor-flow-detail .row.g-3 .small.text-muted.fw-semibold {
    font-size: 10.5px !important;
    line-height: 1.45 !important;
    font-weight: 800 !important;
}
.rems-view-shell .monitor-flow-detail .row.g-3 > div > div:not(.small):not(.input-group),
.rems-view-shell .monitor-flow-detail .monitor-highlight-value {
    font-size: 12px !important;
    line-height: 1.55 !important;
}
.rems-view-shell .monitor-flow-detail :is(.form-label, .form-control, .form-select),
.rems-view-shell .monitor-flow-detail #recordMonitoringForm > p.small.text-muted {
    font-size: 12px !important;
    line-height: 1.55 !important;
}
.rems-view-shell .monitor-flow-detail .form-label {font-weight: 700 !important;}
.rems-view-shell .monitor-flow-detail .monitor-highlight-value {font-weight: 700 !important;}
/* MONITORING & EVALUATION: role-consistent, status-specific badges.
   Colors only; no changes to database values, permissions, or workflow logic.
   Applies to both Extensionist and ESO list/detail pages. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status) {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 29px !important;
    padding: 6px 11px !important;
    border-radius: 999px !important;
    font: 700 9px/1.2 "Century Gothic", Arial, sans-serif !important;
    white-space: nowrap !important;
}
/* Draft: neutral gray. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status)[data-status="Draft"] {
    background: #eef1ef !important;
    border-color: #dbe3de !important;
    color: #4b5750 !important;
}
/* Submitted, Endorsed, Monitoring Conducted, and Report Received: soft blue. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status):is([data-status="Submitted"], [data-status="Endorsed"], [data-status="Monitoring Conducted"], [data-status="Report Received"]) {
    background: #e7f1ff !important;
    border-color: #cddff8 !important;
    color: #174b83 !important;
}
/* Needs Information: gentle red to draw attention to required revisions. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status)[data-status="Needs Information"] {
    background: #fdebe9 !important;
    border-color: #f3cecb !important;
    color: #913f39 !important;
}
/* Scheduled and Report Submitted: soft yellow. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status):is([data-status="Scheduled"], [data-status="Report Submitted"]) {
    background: #fff3c4 !important;
    border-color: #f0de9d !important;
    color: #664f00 !important;
}
/* Completed: exactly the light green used by My Service Requests. */
.rems-view-shell :is(.monitoring-status, .eso-monitor-status, .monitor-flow-status, .monitor-report-status)[data-status="Completed"] {
    background: #e4f3e8 !important;
    border-color: #cae6d0 !important;
    color: #166335 !important;
}


/* ==============================================================
   ASU-REMS / MONITORING — FINAL RESEARCH-MATCHED DARK THEME
   Placed AFTER all light-mode styles because some earlier rules
   use !important. Scoped to dark mode and this page only.
   No PHP, data, form, table structure, or workflow changes.
   ============================================================== */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope {
    --monitor-dark-bg: #0b1d15;
    --monitor-dark-panel: #162e22;
    --monitor-dark-head: #1d3b2c;
    --monitor-dark-input: #1c392b;
    --monitor-dark-border: #3d5d48;
    --monitor-dark-text: #edf7ef;
    --monitor-dark-muted: #bfd6c5;
}
/* Outer canvas: the view-uniform light gradient previously overwrote dark. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope > .page-content {
    background: #0b1d15 !important;
    background-image: none !important;
    color: #edf7ef !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell {
    color: #edf7ef !important;
}
/* Same topbar as Research; don't cover the header's gold/green branding. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar.asu-topbar {
    background: #11271b !important;
    border-bottom: 1px solid #3b5945 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar :is(.rems-topbar-brandline,.topbar-user-text) strong {color:#f0f8f1!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-uniform-topbar :is(.rems-topbar-brandline,.topbar-user-text) span {color:#c3d7c7!important;}
/* Cards, records, detail pages, report forms: no white patches. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .card,.monitoring-card,.eso-monitor-card,.eso-monitor-detail,
    .monitor-flow-card,.monitor-flow-detail,.monitor-report-card,
    .monitor-report-detail,.extension-report-card
) {
    background: #162e22 !important;
    background-image: none !important;
    border-color: #3d5d48 !important;
    color: #eaf4eb !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.card-body,.monitoring-card .card-body,.extension-report-card .card-body) {
    background: transparent !important;
    background-image: none !important;
    color: #eaf4eb !important;
}
/* Request form title divider, and section detail separators. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-card .card-body > .d-flex:first-child,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) > .d-flex:first-child {
    border-bottom-color: #42634d !important;
}
/* Remove the dark rectangle behind the title; the parent card is the panel. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-card .monitoring-request-heading {
    background: transparent !important;
    background-image: none !important;
    border: 0 !important;
    box-shadow: none !important;
    color: #eff8f0 !important;
}
/* Exact icon treatment from Extension Programs: colored square, never white. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-card .monitoring-request-heading .monitoring-request-heading-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 42px !important;
    min-width: 42px !important;
    height: 42px !important;
    background: #28513b !important;
    background-image: none !important;
    border: 1px solid #386a4d !important;
    border-radius: 12px !important;
    color: #88daa6 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-card .monitoring-request-heading .monitoring-request-heading-icon i {
    color: #88daa6 !important;
    background: transparent !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-request-heading h2,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-card,.eso-monitor-detail,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.extension-report-card) :is(h2,h3) {
    color: #f0f8f1 !important;
}
/* Heading bars, separators, filter controls, and results count. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-card,.monitor-flow-card,.monitor-report-card) > .p-3.border-bottom,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-toolbar {
    background: #1d3b2c !important;
    color: #edf7ef !important;
    border-color: #42634d !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .d-flex.border-bottom,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-card,.monitor-flow-card,.monitor-report-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) :is(.border-top,.border-bottom) {
    border-color: #42634d !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-toolbar .form-label,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail,.extension-report-card) .form-label,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .row.g-3 .small.text-muted.fw-semibold {
    color: #d7e9db !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-card,.eso-monitor-detail,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.extension-report-card) :is(.small.text-muted,.text-muted,.monitoring-help,.extension-report-help,.form-text,.monitoring-note) {
    color: #bfd6c5 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-card,.eso-monitor-detail,.monitor-flow-card,.monitor-flow-detail,.monitor-report-card,.monitor-report-detail,.extension-report-card) :is(.row.g-3 > div > div:not(.small):not(.input-group),strong,.monitor-highlight-value) {
    color: #edf7ef !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.form-control,.form-select,textarea.form-control),
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-toolbar :is(.form-control,.form-select) {
    background-color: #1c392b !important;
    border-color: #4c7158 !important;
    color: #f3f9f2 !important;
    box-shadow: none !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .form-control::placeholder {color:#bcd2c1!important;opacity:1!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell select.form-select option {background:#1c392b!important;color:#f3f9f2!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell select.form-select {
    -webkit-appearance: none !important;
    appearance: none !important;
    padding-right: 38px !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23DDF2E1' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-note {
    background:#1d3b2c!important;
    border-color:#486750!important;
    color:#c9dfce!important;
}
/* Research-like zebra rows: subdued alternation and no white table cells. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) {
    background: #162e22 !important;
    color: #edf7ef !important;
    border-color: #3d5d48 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) thead th {
    background: #203f2e !important;
    border-color: #43624d !important;
    color: #daeadd !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody tr:nth-child(odd) td {
    background: #162e22 !important;
    border-color: #3b5846 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody tr:nth-child(even) td {
    background: #214231 !important;
    border-color: #3b5846 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody tr:hover td {
    background: #2a503a !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody td strong {color:#f4faf4!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody td :is(.text-muted,.small) {color:#bdd6c4!important;}
/* Eye, Back, Reset, Save as Draft and View Link buttons: dark, not white. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .btn-outline-asu,.btn-outline-secondary,.btn-light.border,.monitor-back-btn,.monitoring-reset-btn,
    .monitoring-request-actions .btn-outline-asu,.monitor-detail-actions .btn-outline-asu,
    .monitor-eye-btn,a.btn.monitor-document-view-link
) {
    background: #214330 !important;
    background-image: none !important;
    border-color: #527861 !important;
    color: #dcefe0 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(
    .btn-outline-asu,.btn-outline-secondary,.btn-light.border,.monitor-back-btn,.monitoring-reset-btn,
    .monitoring-request-actions .btn-outline-asu,.monitor-detail-actions .btn-outline-asu,
    .monitor-eye-btn,a.btn.monitor-document-view-link
):hover {
    background: #2b553c !important;
    border-color: #78ad89 !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.btn-asu,.monitoring-filter-btn) {
    background: #08752c !important;
    border-color: #168642 !important;
    color: #fff !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.btn-asu,.monitoring-filter-btn):hover {
    background: #0a652a !important;
    border-color: #238047 !important;
}
/* Banner back button stays ASU yellow in both modes. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .rems-view-heading .rems-header-back.rems-top-yellow-btn {
    background: #ffd51f !important;
    border-color: #ffd51f !important;
    color: #003f1a !important;
}
/* Dark status badges: retain status-specific meaning, avoid near-white pills. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status)[data-status="Draft"] {
    background:#304239!important;border-color:#62796b!important;color:#e3ede6!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status):is([data-status="Submitted"],[data-status="Endorsed"],[data-status="Monitoring Conducted"],[data-status="Report Received"]) {
    background:#203f56!important;border-color:#426f8c!important;color:#d8ecff!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status)[data-status="Needs Information"] {
    background:#56302e!important;border-color:#8d5b55!important;color:#ffdfda!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status):is([data-status="Scheduled"],[data-status="Report Submitted"]) {
    background:#594923!important;border-color:#8a7439!important;color:#f8e5a8!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status)[data-status="Completed"] {
    background:#194b32!important;border-color:#43865b!important;color:#d4f7df!important;
}
/* Keep validation/success notices consistent without pure white containers. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-success {background:#214734!important;border-color:#4e8a63!important;color:#d8f5df!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-warning {background:#4b3b23!important;border-color:#886d3c!important;color:#f4e3b3!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .alert-danger {background:#492b2b!important;border-color:#915656!important;color:#ffdddd!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-empty,.eso-monitor-empty,.monitor-flow-empty,.monitor-report-empty,.extension-report-empty) {background:#162e22!important;color:#bfd6c5!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-empty,.eso-monitor-empty,.monitor-flow-empty,.monitor-report-empty,.extension-report-empty) :is(strong,div) {color:#dceee1!important;}


/* Keep the monitoring filter panel a single uniform dark surface.
   Previously .monitoring-list-filters inherited its own darker background,
   drawing an unwanted rectangular patch behind the controls. */
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-toolbar {
    box-shadow: none !important;
    background-color: #1d3b2c !important;
    background-image: none !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-toolbar form.monitoring-list-filters,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-list-filters > :is(.monitoring-filter-search,.monitoring-filter-status,.monitoring-filter-actions),
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-search-field {
    background: transparent !important;
    background-image: none !important;
    border: 0 !important;
    box-shadow: none !important;
    filter: none !important;
}

/* Program / Project Monitoring & Evaluation — compact, professional activity form.
   Keeps the original site theme, form fields, database and workflow intact. */
.rems-view-shell .monitoring-activity-list {
    display:flex;
    flex-direction:column;
    gap:12px;
    min-width:0;
}
.rems-view-shell .monitoring-activity-section {
    min-width:0;
    overflow:hidden;
    border:1px solid #dbe8de;
    border-radius:14px;
    background:#fff;
    box-shadow:none;
}
.rems-view-shell .monitoring-activity-header {
    display:flex;
    align-items:center;
    gap:12px;
    min-height:68px;
    margin:0;
    padding:13px 18px;
    background:#f5f9f5;
    color:#19462b;
    border:0;
    cursor:pointer;
    list-style:none;
    user-select:none;
}
.rems-view-shell summary.monitoring-activity-header::-webkit-details-marker {display:none;}
.rems-view-shell summary.monitoring-activity-header::marker {content:'';}
.rems-view-shell .monitoring-activity-section[open] > .monitoring-activity-header {border-bottom:1px solid #e3ece5;}
.rems-view-shell .monitoring-activity-index {
    width:37px;
    height:37px;
    flex:0 0 37px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid #cfe5d5;
    border-radius:10px;
    background:#e8f5ec;
    color:#087539;
    font:800 12px/1 "Century Gothic",Arial,sans-serif;
}
.rems-view-shell .monitoring-activity-header-copy {display:flex;flex-direction:column;gap:3px;min-width:0;}
.rems-view-shell .monitoring-activity-header-copy strong,
.rems-view-shell .monitoring-activity-header h3 {
    margin:0!important;
    color:#184c2d!important;
    font:800 13px/1.4 "Century Gothic",Arial,sans-serif!important;
}
.rems-view-shell .monitoring-activity-header-copy small {
    color:#718476;
    font:500 10px/1.3 "Century Gothic",Arial,sans-serif;
}
.rems-view-shell .monitoring-activity-chevron {
    display:flex;
    align-items:center;
    justify-content:center;
    width:28px;
    height:28px;
    margin-left:auto;
    border-radius:8px;
    color:#47735a;
    font-size:14px;
}
.rems-view-shell .monitoring-activity-section[open] > summary .monitoring-activity-chevron {transform:rotate(180deg);}
.rems-view-shell .monitoring-activity-content {padding:19px 20px 21px;}
.rems-view-shell .monitoring-activity-group + .monitoring-activity-group {
    margin-top:20px;
    padding-top:17px;
    border-top:1px solid #edf1ee;
}
.rems-view-shell .monitoring-activity-group-title {
    margin-bottom:12px;
    color:#68786c;
    font:800 9.5px/1.35 "Century Gothic",Arial,sans-serif;
    letter-spacing:.06em;
    text-transform:uppercase;
}
.rems-view-shell .monitoring-activity-section .row.g-3 > div {min-width:0;}
.rems-view-shell .monitoring-activity-section .form-label {display:block;margin-bottom:6px!important;}
.rems-view-shell .monitoring-activity-section textarea.form-control {
    display:block;
    height:69px;
    min-height:69px!important;
    resize:vertical;
    padding:9px 11px!important;
}
.rems-view-shell .monitoring-activity-section input.form-control {min-height:42px!important;height:42px!important;}
.rems-view-shell .monitoring-activity-section :is(input.form-control,textarea.form-control) {
    background:#fff!important;
    border:1px solid #d9e3dc!important;
    border-radius:9px!important;
    box-shadow:none!important;
}
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-label {
    display:block;
    margin-bottom:7px;
    font-size:10px!important;
    color:#627267!important;
    text-transform:none;
}
.rems-view-shell .monitoring-activity-readonly {padding:0;}
.rems-view-shell .monitoring-activity-readonly > .row {padding:16px 18px 19px;}
/* Keep all monitoring record detail boxes aligned for BOTH ESO and Coordinator.
   Two equal columns for paired fields, with Recommendation full width. */
.rems-view-shell .monitoring-readonly-overview > [class*="col-"],
.rems-view-shell .monitoring-readonly-footer > [class*="col-"],
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-field {
    min-width:0;
}
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-field {
    display:flex;
    flex-direction:column;
}
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-field .monitoring-activity-label {
    line-height:1.4!important;
    margin-bottom:6px!important;
}
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-field .monitoring-activity-value {
    flex:1 1 auto;
    width:100%;
    box-sizing:border-box;
}
.rems-view-shell .monitoring-activity-readonly .monitoring-activity-value {
    min-height:42px;
    padding:11px 12px;
    border:1px solid #e2eae3;
    border-radius:9px;
    background:#fff;
    color:#263d2c;
    font-size:11.5px;
    line-height:1.55;
    overflow-wrap:anywhere;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-section {
    background:#172f23!important;
    border-color:#3d604a!important;
    box-shadow:none!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-header {
    background:#1c392b!important;
    border-bottom-color:#42634d!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-header-copy strong,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-header h3 {color:#d8efde!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-header-copy small,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-group-title {color:#bfd6c5!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-index {
    background:#29523b!important;
    border-color:#457354!important;
    color:#b9f1ca!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-chevron {color:#b8d5bf!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-group + .monitoring-activity-group {border-top-color:#375745!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-section :is(input.form-control,textarea.form-control) {
    background:#1c392b!important;
    border-color:#4c7158!important;
    color:#f3f9f2!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-readonly .monitoring-activity-label {color:#bfd6c5!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitoring-activity-readonly .monitoring-activity-value {
    background:#1c392b!important;
    border-color:#4c7158!important;
    color:#edf7ef!important;
}
.rems-view-shell .monitoring-add-entry-wrap {
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
    margin-top:14px;
}
.rems-view-shell button.monitoring-add-entry {
    min-height:40px!important;
    border:1px solid #a9d2b5!important;
    background:#f3faf5!important;
    color:#076b32!important;
    box-shadow:none!important;
}
.rems-view-shell button.monitoring-add-entry:hover {background:#e5f4e9!important;color:#045b27!important;}
.rems-view-shell button.monitoring-add-entry:disabled {opacity:.6;cursor:not-allowed;}
.rems-view-shell .monitoring-remove-entry-wrap {display:flex;justify-content:flex-end;margin-top:15px;}
.rems-view-shell button.monitoring-remove-entry {
    display:inline-flex;align-items:center;justify-content:center;gap:7px;
    padding:8px 12px;border:1px solid #e4c8c8;border-radius:9px;
    background:transparent;color:#96484a;
    font:700 10px/1.3 "Century Gothic",Arial,sans-serif;
    cursor:pointer;
}
.rems-view-shell button.monitoring-remove-entry:hover {background:#fbefef;}
.rems-view-shell button.monitoring-remove-entry[hidden] {display:none!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell button.monitoring-add-entry {
    background:#214330!important;border-color:#527861!important;color:#dcefe0!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell button.monitoring-add-entry:hover {background:#2b553c!important;color:#fff!important;}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell button.monitoring-remove-entry {
    background:transparent!important;border-color:#825b60!important;color:#ffbabc!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell button.monitoring-remove-entry:hover {background:#513238!important;}
@media(max-width:760px) {
    .rems-view-shell .monitoring-activity-header {padding:12px 14px;}
    .rems-view-shell .monitoring-activity-content {padding:14px!important;}
    .rems-view-shell .monitoring-activity-group + .monitoring-activity-group {margin-top:16px;padding-top:14px;}
    .rems-view-shell .monitoring-activity-readonly > .row {padding:14px;}
}

/* Prominent submitted metadata in Monitoring Request Details (Coordinator + ESO).
   Titles and sign-off names are important; no changes to saved data or grid sizes. */
.rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .row.g-3 > .monitoring-key-detail > .monitoring-key-label {
    color:#28603d!important;
    font-size:10.5px!important;
    font-weight:800!important;
    line-height:1.5!important;
    letter-spacing:.035em!important;
    margin-bottom:5px!important;
}
.rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .row.g-3 > .monitoring-key-detail > .monitoring-key-value {
    font-family:"Century Gothic",Arial,sans-serif!important;
    color:#183b26!important;
    font-size:13px!important;
    font-weight:800!important;
    line-height:1.5!important;
    overflow-wrap:anywhere;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .row.g-3 > .monitoring-key-detail > .monitoring-key-label {
    color:#b0e7c3!important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell :is(.monitoring-card,.eso-monitor-detail,.monitor-flow-detail,.monitor-report-detail) .row.g-3 > .monitoring-key-detail > .monitoring-key-value {
    color:#f2fff4!important;
}
/* Compact ESO Monitoring Requests remarks field only. */
.rems-view-shell #officeMonitoringReviewForm #monitorOfficeRemarks.form-control {
    height: 76px !important;
    min-height: 76px !important;
    resize: vertical;
}
/* ESO Monitoring Schedule: restore the submitted title and request number above assignment. */
.rems-view-shell .monitor-flow-detail > .monitor-schedule-request-summary {
    padding-bottom: 15px;
    border-bottom: 1px solid #e8eee9;
}
.rems-view-shell .monitor-flow-detail .monitor-schedule-request-summary-copy {min-width:0;flex:1 1 240px;}
.rems-view-shell .monitor-flow-detail .monitor-schedule-project-title {
    font: 800 15px/1.5 "Century Gothic",Arial,sans-serif;
    color: #153f28;
    overflow-wrap: anywhere;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-flow-detail > .monitor-schedule-request-summary {
    border-bottom-color: #42634d !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-flow-detail .monitor-schedule-project-title {
    color: #f0f8f1 !important;
}
/* Monitoring Schedule shows the assignment form next, without old details. */
.rems-view-shell .monitor-flow-detail .monitor-schedule-assignment-form {
    margin: 0;
    padding: 0;
    border: 0;
}

/* ==============================================================
   MONITORING — CONSISTENT LIST/DETAIL ALIGNMENT (ALL ROLES)
   Presentation only. No change to POST actions or status workflow.
   ============================================================== */
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) {
    width:100% !important;
    table-layout:fixed !important;
    border-collapse:collapse !important;
}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td) {
    box-sizing:border-box;
    padding:13px 16px !important;
    vertical-align:middle !important;
    overflow-wrap:anywhere;
}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-last-child(2),
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):last-child {
    text-align:center !important;
}
/* The status badge and eye action sit in the same columns as their headers. */
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody td:nth-last-child(2) > :is(.monitoring-status,.eso-monitor-status,.monitor-flow-status,.monitor-report-status) {
    margin-inline:auto !important;
}
.rems-view-shell :is(.monitoring-table,.eso-monitor-table,.monitor-flow-table,.monitor-report-table) tbody td:last-child a.monitor-eye-btn {
    display:inline-flex !important;
    margin:0 auto !important;
}
/* Standard ESO four-column table widths; request status alignment is adjusted below. */
.rems-view-shell :is(.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-child(1) {width:34% !important;}
.rems-view-shell :is(.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-child(2) {width:33% !important;}
.rems-view-shell :is(.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-child(3) {width:20% !important;}
.rems-view-shell :is(.eso-monitor-table,.monitor-flow-table,.monitor-report-table) :is(th,td):nth-child(4) {width:13% !important;}
/* Extension Coordinator list has five columns (includes legacy Preferred Date). */
.rems-view-shell .monitoring-table :is(th,td):nth-child(1) {width:34% !important;}
.rems-view-shell .monitoring-table :is(th,td):nth-child(2) {width:18% !important;}
.rems-view-shell .monitoring-table :is(th,td):nth-child(3) {width:21% !important;}
.rems-view-shell .monitoring-table :is(th,td):nth-child(4) {width:16% !important;}
.rems-view-shell .monitoring-table :is(th,td):nth-child(5) {width:11% !important;}
.rems-view-shell .monitor-report-table {min-width:640px !important;}
.rems-view-shell .monitor-flow-table {min-width:640px !important;}
.rems-view-shell .eso-monitor-table {min-width:640px !important;}
.rems-view-shell .monitoring-table {min-width:730px !important;}

/* Monitoring Reports details — compact, balanced spacing for the ESO.
   The Bootstrap row gutters plus the old minimum-height values created
   oversized vertical gaps between the three pairs of information. This
   layout change is scoped to the report DETAILS only, not list tables,
   actions, form submission, or the other monitoring screens. */
.rems-view-shell .monitor-report-detail .monitor-report-head-copy {min-width:0;}
.rems-view-shell .monitor-report-detail .monitor-report-project-title {
    display:block;
    color:#193e28;
    font:800 15px/1.5 "Century Gothic",Arial,sans-serif;
    overflow-wrap:anywhere;
}
.rems-view-shell .monitor-report-detail > .d-flex:first-child {
    padding-bottom:10px !important;
    margin-bottom:14px !important;
}
.rems-view-shell .monitor-report-detail .monitor-report-info-grid {
    display:grid !important;
    grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    column-gap:24px !important;
    row-gap:18px !important;
    align-items:start !important;
    margin:0 !important;
}
/* Cancel the grid-child widths and top margins introduced by Bootstrap .row.g-3. */
.rems-view-shell .monitor-report-detail .monitor-report-info-grid > .monitor-report-info-item {
    display:flex;
    width:100% !important;
    max-width:none !important;
    min-width:0;
    padding:0 !important;
    margin:0 !important;
    flex-direction:column;
    justify-content:flex-start;
}
.rems-view-shell .monitor-report-detail .monitor-report-info-label {
    display:block;
    margin-bottom:4px !important;
    color:#66796b !important;
    font:800 10px/1.4 "Century Gothic",Arial,sans-serif !important;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.rems-view-shell .monitor-report-detail .row.g-3 > div.monitor-report-info-item > div.monitor-report-info-value {
    min-height:0 !important;
    color:#24372a !important;
    font:600 12px/1.55 "Century Gothic",Arial,sans-serif !important;
    overflow-wrap:anywhere;
}
.rems-view-shell .monitor-report-detail .row.g-3 > div.monitor-report-info-item.monitoring-key-detail > div.monitor-report-info-value {
    color:#18492b !important;
    font-weight:800 !important;
}
.rems-view-shell .monitor-report-detail .row.g-3 > div.monitor-report-info-link > div.monitor-report-info-value {
    display:flex;
    align-items:flex-start;
}
.rems-view-shell .monitor-report-detail .monitor-detail-actions {
    clear:both;
    justify-content:flex-end !important;
    margin-top:16px !important;
    padding-top:14px !important;
}
@media (max-width:650px) {
    .rems-view-shell .monitor-report-detail .monitor-report-info-grid {
        grid-template-columns:minmax(0,1fr) !important;
        row-gap:15px !important;
    }
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-report-detail .monitor-report-project-title,
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-report-detail .monitor-report-info-value {
    color:#edf7ef !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-report-detail .monitor-report-info-label {
    color:#bdd6c5 !important;
}
html[data-rems-theme="dark"] body .main-page.rems-extension-dark-scope .rems-view-shell .monitor-report-detail .monitor-report-info-item.monitoring-key-detail .monitor-report-info-value {
    color:#b6eac7 !important;
}


/* ESO Monitoring Requests only: move the Status heading and badge to the
   marked position (start of the status column), leaving other columns fixed. */
.rems-view-shell .eso-monitor-table thead th:nth-child(3),
.rems-view-shell .eso-monitor-table tbody td:nth-child(3) {
    text-align: left !important;
    padding-left: 7px !important;
}
.rems-view-shell .eso-monitor-table tbody td:nth-child(3) > .eso-monitor-status {
    margin-left: 0 !important;
    margin-right: 0 !important;
}


/* ESO Monitoring Schedule: add breathing room between the M&E link and its eye button.
   Only this link control changes; the eye-button verification stays untouched. */
.rems-view-shell #recordMonitoringForm .input-group {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 10px;
}
.rems-view-shell #recordMonitoringForm .input-group > #meFormUrl {
    flex: 1 1 auto;
    min-width: 0;
    width: 1%;
    border-radius: 10px !important;
}
.rems-view-shell #recordMonitoringForm .input-group > #viewMeForm {
    flex: 0 0 38px;
    width: 38px !important;
    min-width: 38px !important;
    margin-left: 0 !important;
    border-radius: 10px !important;
}


/* Extension Coordinator: separate the Monitoring Report URL field and eye button.
   Match the M&E Form Link spacing; no submission or link-verification changes. */
.rems-view-shell .extension-report-card .input-group:has(#monitoringReportUrl) {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 10px;
}
.rems-view-shell .extension-report-card .input-group > #monitoringReportUrl {
    flex: 1 1 auto;
    min-width: 0;
    width: 1%;
    border-radius: 10px !important;
}
.rems-view-shell .extension-report-card .input-group > #viewReportLink {
    flex: 0 0 38px;
    width: 38px !important;
    min-width: 38px !important;
    margin-left: 0 !important;
    border-radius: 10px !important;
}

</style>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

